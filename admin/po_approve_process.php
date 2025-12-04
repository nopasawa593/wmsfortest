<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php'; 

// 2. กำหนดฟังก์ชัน hasRole
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 3. ตรวจสอบสิทธิ์ (ผู้จัดการพัสดุ / Admin)
if (!hasRole(['WH_MANAGER'])) {
    die("Access Denied: คุณไม่มีสิทธิ์อนุมัติ PO");
}

$manager_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (!isset($_POST['po_id']) || !isset($_POST['action'])) {
         die("Invalid Action: ข้อมูลที่ส่งมาไม่ครบถ้วน");
    }

    $po_id = (int)$_POST['po_id'];
    $action = $_POST['action']; 

    if ($action == 'approve') {
        $new_status = 'Pending'; // (สถานะ "Pending" คือ อนุมัติแล้ว รอรับของ)
    } elseif ($action == 'reject') {
        $new_status = 'PO Rejected';
    } else {
        die("Invalid Action: '$action' is not a valid action.");
    }

    // (อัปเดตสถานะ PO)
    $stmt = $conn->prepare("UPDATE purchase_orders 
                            SET status = ?, approved_by_user_id = ?, approval_date = CURDATE()
                            WHERE id = ? AND status = 'Pending PO Approval'");
    $stmt->bind_param("sii", $new_status, $manager_user_id, $po_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['alert_message'] = "ดำเนินการ $action PO ID: $po_id สำเร็จ";
        $_SESSION['alert_type'] = "success";
    } else {
         $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: ไม่สามารถดำเนินการ $action PO ID: $po_id ได้ (อาจถูก xử lý ไปแล้ว)";
         $_SESSION['alert_type'] = "danger";
    }
    
    $stmt->close();
    $conn->close();

    header("Location: po_approval_list.php");
    exit(); 

} else {
    header("Location: po_approval_list.php");
    exit();
}
?>