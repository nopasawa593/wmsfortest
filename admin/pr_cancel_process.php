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
    die("Access Denied: คุณไม่มีสิทธิ์ยกเลิก PR");
}

$cancelled_by_user_id = $_SESSION['user_id']; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pr_id'])) {

    $pr_id = (int)$_POST['pr_id'];
    $new_status = 'Cancelled'; // (สถานะใหม่: ถูกยกเลิก)

    $conn->begin_transaction(); 

    try {
        // 1. ตรวจสอบสถานะปัจจุบัน (ต้องเป็น Pending หรือ Approved เท่านั้น)
        $check_stmt = $conn->prepare("SELECT status FROM purchase_requisitions WHERE id = ? FOR UPDATE");
        $check_stmt->bind_param("i", $pr_id);
        $check_stmt->execute();
        $current_status = $check_stmt->get_result()->fetch_assoc()['status'];
        $check_stmt->close();

        if ($current_status != 'Pending WH Approval' && $current_status != 'Approved') {
            throw new Exception("ไม่สามารถยกเลิกได้ สถานะ PR ไม่ใช่ 'Pending' หรือ 'Approved' (อาจถูกสร้าง PO ไปแล้ว)");
        }

        // 2. ⭐️ (สำคัญ) การยกเลิก PR ไม่จำเป็นต้องคืนสต็อก ⭐️
        // (เพราะ Flow PR ของเรา ไม่ได้ "จอง" สต็อกเหมือน Flow MR)
        // เราแค่เปลี่ยนสถานะก็พอ

        // 3. อัปเดตสถานะใบเบิก (MR)
        $stmt_update = $conn->prepare("UPDATE purchase_requisitions 
                                      SET status = ?, approved_by_user_id = ?, approval_date = CURDATE()
                                      WHERE id = ?"); 
        $stmt_update->bind_param("sii", $new_status, $cancelled_by_user_id, $pr_id);
        $stmt_update->execute();
        
        $conn->commit(); // ⭐️ ยืนยัน Transaction

        $_SESSION['alert_message'] = "ยกเลิก PR ID: $pr_id เรียบร้อยแล้ว!";
        $_SESSION['alert_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback(); // ⭐️ ย้อนกลับ Transaction
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    $conn->close();

    // กลับไปหน้า List
    header("Location: pr_approval_list.php");
    exit();
}
?>