<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php'; 

// 2. กำหนดฟังก์ชัน hasRole
if (session_status() == PHP_SESSION_NONE) session_start();
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 3. ตรวจสอบสิทธิ์ (ทีมพัสดุ)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์ยกเลิกใบเบิก");
}

$cancelled_by_user_id = $_SESSION['user_id']; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mr_id'])) {

    $mr_id = (int)$_POST['mr_id'];
    $new_status = 'WH Cancelled'; // (สถานะใหม่: ถูกยกเลิกโดยคลัง)

    $conn->begin_transaction(); 

    try {
        // 1. ตรวจสอบสถานะปัจจุบัน (ต้องเป็น Pending Issue เท่านั้น)
        $check_stmt = $conn->prepare("SELECT status FROM requisitions WHERE id = ? FOR UPDATE");
        $check_stmt->bind_param("i", $mr_id);
        $check_stmt->execute();
        $current_status = $check_stmt->get_result()->fetch_assoc()['status'];
        $check_stmt->close();

        if ($current_status != 'Pending Issue') {
            throw new Exception("ไม่สามารถยกเลิกได้ สถานะใบเบิกไม่ใช่ 'Pending Issue'");
        }

        // --- 2. ⭐️ (ขั้นตอนสำคัญ) การคืนสต็อก (Un-reservation) ⭐️ ---
        // (ดึงรายการวัสดุ (items) ทั้งหมดในใบเบิกนี้)
        $items_stmt = $conn->prepare("SELECT material_id, quantity_requested FROM requisition_items WHERE requisition_id = ?");
        $items_stmt->bind_param("i", $mr_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        // (วน Loop เพื่อ "คืน" สต็อกที่จองไว้)
        // (เราจะคืนสตอกจาก Lot ที่เก่าที่สุดก่อน - FIFO)
        while ($item = $items_result->fetch_assoc()) {
            $mat_id = $item['material_id'];
            $qty_to_unreserve = $item['quantity_requested'];

            // (ดึง Lot ที่ *ถูกจองไว้* เรียงตาม FIFO/FEFO)
            $lots_stmt = $conn->prepare("SELECT id, quantity_reserved
                                        FROM inventory
                                        WHERE material_id = ? AND quantity_reserved > 0
                                        ORDER BY expiry_date ASC, last_updated ASC
                                        FOR UPDATE"); // (Lock แถวที่จะอัปเดต)
            $lots_stmt->bind_param("i", $mat_id);
            $lots_stmt->execute();
            $lots_result = $lots_stmt->get_result();

            while ($lot = $lots_result->fetch_assoc()) {
                if ($qty_to_unreserve <= 0) break; // (คืนครบแล้ว)

                $inv_id = $lot['id'];
                $lot_reserved = $lot['quantity_reserved'];

                if ($lot_reserved >= $qty_to_unreserve) {
                    // (Lot นี้มีที่จองไว้พอ)
                    $conn->query("UPDATE inventory SET quantity_reserved = quantity_reserved - $qty_to_unreserve WHERE id = $inv_id");
                    $qty_to_unreserve = 0;
                } else {
                    // (Lot นี้จองไว้ไม่พอ, คืนทั้งหมดที่มี)
                    $conn->query("UPDATE inventory SET quantity_reserved = 0 WHERE id = $inv_id");
                    $qty_to_unreserve -= $lot_reserved;
                }
            }
            $lots_stmt->close();
        }
        $items_stmt->close();
        // --- ⭐️ (จบขั้นตอนการคืนสต็อก) ⭐️ ---

        // 3. อัปเดตสถานะใบเบิก (MR)
        $stmt_update = $conn->prepare("UPDATE requisitions 
                                      SET status = ?, approved_by_user_id = ?, approval_date = CURDATE()
                                      WHERE id = ? AND status = 'Pending Issue'"); 
        $stmt_update->bind_param("sii", $new_status, $cancelled_by_user_id, $mr_id);
        $stmt_update->execute();
        
        $conn->commit(); // ⭐️ ยืนยัน Transaction

        $_SESSION['alert_message'] = "ยกเลิกใบเบิก MR ID: $mr_id และคืนสต็อกเรียบร้อยแล้ว!";
        $_SESSION['alert_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback(); // ⭐️ ย้อนกลับ Transaction
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    $conn->close();

    // กลับไปหน้า List
    header("Location: requisition_list.php");
    exit();
}
?>