<?php
// (ไฟล์นี้ไม่มี HTML จึงเรียก db_connect โดยตรง)
require_once '../config/db_connect.php'; 

// --- 0. ตรวจสอบสิทธิ์ ---
// (ต้องเริ่ม Session ก่อน)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// (เช็คสิทธิ์: พนักงานพัสดุ หรือ Admin สูงสุด)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['system_admin', 'warehouse_staff'])) {
    die("Access Denied: คุณไม่มีสิทธิ์สร้าง PR");
}

// ID ของผู้สร้าง PR
$requested_by_user_id = $_SESSION['user_id']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. ดึงข้อมูล Header จากฟอร์ม
    $pr_number = $_POST['pr_number'];
    $request_date = $_POST['request_date'];
    $department = $_POST['department']; // (แผนกที่ขอซื้อ = คลังสินค้า)
    $reason = $_POST['reason'];

    // 2. ดึงข้อมูล Items (Array) จากฟอร์ม
    $material_ids = $_POST['material_id'];
    $quantities = $_POST['quantity'];

    // --- เริ่ม Transaction ---
    $conn->begin_transaction();
    try {
        // 3. บันทึก PR Header
        // (สถานะเริ่มต้นคือ 'Pending WH Approval' (รอ ผจก. พัสดุ อนุมัติ))
        $stmt_pr = $conn->prepare("INSERT INTO purchase_requisitions (pr_number, requested_by_user_id, request_date, department, reason, status)
                                  VALUES (?, ?, ?, ?, ?, 'Pending WH Approval')");
                                  
        $stmt_pr->bind_param("sisss", $pr_number, $requested_by_user_id, $request_date, $department, $reason);
        $stmt_pr->execute();
        $pr_id = $conn->insert_id; // ดึง ID ของ PR ที่เพิ่งสร้าง

        // 4. บันทึก PR Items (วน Loop)
        $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, material_id, quantity_requested) VALUES (?, ?, ?)");
        
        $item_added = false;
        foreach ($material_ids as $index => $material_id) {
            $quantity = $quantities[$index];
            if ($material_id && $quantity > 0) {
                $stmt_item->bind_param("iid", $pr_id, $material_id, $quantity);
                $stmt_item->execute();
                $item_added = true;
            }
        }

        if (!$item_added) {
            throw new Exception("กรุณาเพิ่มรายการวัสดุอย่างน้อย 1 รายการ");
        }
        
        // --- 5. ถ้าทุกอย่างสำเร็จ ---
        $conn->commit();
        
        // (ตั้งค่า Session Alert)
        $_SESSION['alert_message'] = "ส่งใบขอซื้อ (PR) เลขที่ $pr_number สำเร็จ! รอดำเนินการอนุมัติ";
        $_SESSION['alert_type'] = "success";
        
        // (ส่ง พนักงานพัสดุ กลับไปหน้า List PR)
        header("Location: /wms/admin/pr_approval_list.php"); 
        exit();

    } catch (Exception $e) {
        // --- 6. ถ้ามีข้อผิดพลาด ---
        $conn->rollback();
        
        // (ตั้งค่า Session Alert)
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาดในการสร้าง PR: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        
        // (ส่งกลับไปหน้าเดิมที่กรอก)
        header("Location: pr_create.php"); 
        exit();
    }
    
    if (isset($stmt_pr)) $stmt_pr->close();
    if (isset($stmt_item)) $stmt_item->close();
    $conn->close();
}
?>