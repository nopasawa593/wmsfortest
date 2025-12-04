<?php
// 1. เชื่อมต่อฐานข้อมูล (ไฟล์นี้จะ session_start() ให้ด้วย)
require_once '../config/db_connect.php';

// ⭐️ (เพิ่มส่วนนี้) 2. กำหนดฟังก์ชัน hasRole() ที่ขาดหายไป (คัดลอกจาก header.php) ⭐️
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        // Superuser check (ADMIN ผ่านทุกอย่าง)
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') {
            return true;
        }
        // Standard check
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// --- 3. (แก้ไข Role) ตรวจสอบสิทธิ์ ---
// (ADMIN จะผ่านอัตโนมัติจากฟังก์ชันด้านบน, WH_MANAGER จะผ่านตามสิทธิ์)
if (!hasRole(['WH_MANAGER'])) {
    die("Access Denied: คุณไม่มีสิทธิ์อนุมัติ PR");
}

$admin_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // (เพิ่มการตรวจสอบว่ามีค่าส่งมาจริง)
    if (!isset($_POST['pr_id']) || !isset($_POST['action'])) {
         die("Invalid Action: ข้อมูลที่ส่งมาไม่ครบถ้วน");
    }

    $pr_id = $_POST['pr_id'];
    $action = $_POST['action']; 

    if ($action == 'approve') {
        $new_status = 'Approved';
    } elseif ($action == 'reject') {
        $new_status = 'WH Rejected';
    } else {
        die("Invalid Action: '$action' is not a valid action.");
    }

    // --- อัปเดตเฉพาะที่รออนุมัติ (Pending WH Approval) ---
    $stmt = $conn->prepare("UPDATE purchase_requisitions 
                            SET status = ?, approved_by_user_id = ?, approval_date = CURDATE()
                            WHERE id = ? AND status = 'Pending WH Approval'");
    $stmt->bind_param("sii", $new_status, $admin_user_id, $pr_id);
    $stmt->execute();
    
    $stmt->close();
    $conn->close();

    header("Location: pr_approval_list.php");
    exit(); // (เพิ่ม exit() หลัง redirect เสมอ)

} else {
    // ถ้าเข้าหน้านี้ตรงๆ (GET) ให้เด้งกลับ
    header("Location: pr_approval_list.php");
    exit();
}
?>