<?php
// 1. ตรวจสอบและเริ่ม Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// 2. เชื่อมต่อฐานข้อมูล
require_once '../config/db_connect.php';

// (Helper function hasRole)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 0. ตรวจสอบสิทธิ์ (ต้องเป็น ผจก. แผนก)
if (!hasRole('DEPT_MANAGER')) {
    $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: คุณไม่มีสิทธิ์ในการดำเนินการนี้";
    $_SESSION['alert_type'] = "danger";
    header("Location: mr_approval_list.php");
    exit();
}

$manager_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $mr_id = $_POST['mr_id'];
    $action = $_POST['action']; 

    if (empty($mr_id) || empty($action)) {
        // ... (Error handling) ...
        header("Location: mr_approval_list.php");
        exit();
    }
    
    $conn->begin_transaction(); // ⭐️ เริ่ม Transaction

    try {
        $new_status = "";
        $alert_action = "";
        
        if ($action == 'approve') {
            $new_status = 'Pending Issue'; // ⭐️ สถานะรอพัสดุจ่ายของ
            $alert_action = "อนุมัติ";

            // --- ⭐️ (ขั้นตอนใหม่) การจองสต็อก (Stock Reservation) ⭐️ ---
            // 1. ดึงรายการวัสดุ (items) ทั้งหมดในใบเบิกนี้
            $items_stmt = $conn->prepare("SELECT material_id, quantity_requested FROM requisition_items WHERE requisition_id = ?");
            $items_stmt->bind_param("i", $mr_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();

            // 2. ตรวจสอบสต็อกที่เบิกได้ (Available Stock) ก่อน
            while ($item = $items_result->fetch_assoc()) {
                $mat_id = $item['material_id'];
                $qty_requested = $item['quantity_requested'];

                // (เช็คยอด Available = OnHand - Reserved)
                $stock_check_stmt = $conn->prepare("SELECT SUM(quantity - quantity_reserved) AS available_stock 
                                                   FROM inventory 
                                                   WHERE material_id = ? 
                                                   LOCK IN SHARE MODE"); // (Lock อ่าน)
                $stock_check_stmt->bind_param("i", $mat_id);
                $stock_check_stmt->execute();
                $available_stock = (float)$stock_check_stmt->get_result()->fetch_assoc()['available_stock'];
                $stock_check_stmt->close();

                if ($available_stock < $qty_requested) {
                    throw new Exception("สต็อกไม่เพียงพอสำหรับวัสดุ ID: $mat_id (ต้องการ $qty_requested, เบิกได้ $available_stock)");
                }
            }
            $items_result->data_seek(0); // (ย้อนกลับไปแถวแรก)

            // 3. (ถ้าสต็อกพอ) วน Loop เพื่อ "จอง" สต็อก
            // (เราจะจองจาก Lot ที่เก่าที่สุดก่อน - FIFO)
            while ($item = $items_result->fetch_assoc()) {
                $mat_id = $item['material_id'];
                $qty_to_reserve = $item['quantity_requested'];

                // (ดึง Lot ที่มีของให้เบิก เรียงตาม FIFO/FEFO)
                $lots_stmt = $conn->prepare("SELECT id, (quantity - quantity_reserved) AS available_qty
                                            FROM inventory
                                            WHERE material_id = ? AND (quantity - quantity_reserved) > 0
                                            ORDER BY expiry_date ASC, last_updated ASC
                                            FOR UPDATE"); // (Lock แถวที่จะอัปเดต)
                $lots_stmt->bind_param("i", $mat_id);
                $lots_stmt->execute();
                $lots_result = $lots_stmt->get_result();

                while ($lot = $lots_result->fetch_assoc()) {
                    if ($qty_to_reserve <= 0) break; // (จองครบแล้ว)

                    $inv_id = $lot['id'];
                    $lot_available = $lot['available_qty'];

                    if ($lot_available >= $qty_to_reserve) {
                        // (Lot นี้มีพอ)
                        $conn->query("UPDATE inventory SET quantity_reserved = quantity_reserved + $qty_to_reserve WHERE id = $inv_id");
                        $qty_to_reserve = 0;
                    } else {
                        // (Lot นี้ไม่พอ, จองทั้งหมดที่มี)
                        $conn->query("UPDATE inventory SET quantity_reserved = quantity_reserved + $lot_available WHERE id = $inv_id");
                        $qty_to_reserve -= $lot_available;
                    }
                }
                $lots_stmt->close();
            }
            // --- ⭐️ (จบขั้นตอนการจองสต็อก) ⭐️ ---

        } elseif ($action == 'reject') {
            $new_status = 'Dept Rejected';
            $alert_action = "ปฏิเสธ";
            // (ถ้าปฏิเสธ ไม่ต้องจองสต็อก)
        } else {
            throw new Exception("การดำเนินการไม่ถูกต้อง");
        }

        // --- (อัปเดตสถานะใบเบิก) ---
        $stmt_update = $conn->prepare("UPDATE requisitions 
                                      SET status = ?, approved_by_user_id = ?, approval_date = CURDATE()
                                      WHERE id = ? AND status = 'Pending Dept Approval'"); 
        $stmt_update->bind_param("sii", $new_status, $manager_user_id, $mr_id);
        $stmt_update->execute();
        
        if ($stmt_update->affected_rows > 0) {
            $conn->commit(); // ⭐️ ยืนยัน Transaction
            $mr_number = $conn->query("SELECT mr_number FROM requisitions WHERE id = $mr_id")->fetch_assoc()['mr_number'];
            $_SESSION['alert_message'] = "ดำเนินการ <b>$alert_action</b> ใบเบิก $mr_number สำเร็จแล้ว!";
            $_SESSION['alert_type'] = "success";
        } else {
            throw new Exception("ไม่สามารถดำเนินการได้: ใบเบิกอาจไม่อยู่ในสถานะที่รออนุมัติแล้ว");
        }
        $stmt_update->close();

    } catch (Exception $e) {
        $conn->rollback(); // ⭐️ ย้อนกลับ Transaction
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาดทางเทคนิค: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    $conn->close();

    // กลับไปหน้าอนุมัติ
    header("Location: mr_approval_list.php");
    exit();
}
?>