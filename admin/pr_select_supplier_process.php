<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php'; 

// 2. เรียกใช้ auth_check.php
require_once '../includes/auth_check.php';

// 3. กำหนดฟังก์ชัน hasRole
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 4. ตรวจสอบสิทธิ์ (ต้องทำก่อน xử lý POST)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to select suppliers.");
}

// 5. ตรวจสอบ PR ID และ Supplier ID
if (!isset($_GET['pr_id']) || !isset($_GET['supplier_id'])) {
    die("Error: PR ID or Supplier ID is missing.");
}
$pr_id = (int)$_GET['pr_id'];
$supplier_id = (int)$_GET['supplier_id'];
$created_by_user_id = $_SESSION['user_id'];

$conn->begin_transaction();
try {
    // 6. ดึงข้อมูลใบเสนอราคาที่ชนะ
    $stmt_quote = $conn->prepare("SELECT id FROM quotation_headers WHERE pr_id = ? AND supplier_id = ?");
    $stmt_quote->bind_param("ii", $pr_id, $supplier_id);
    $stmt_quote->execute();
    $quote_header_id = $stmt_quote->get_result()->fetch_assoc()['id'];
    $stmt_quote->close();

    if (!$quote_header_id) {
        throw new Exception("ไม่พบข้อมูลใบเสนอราคาที่ตรงกัน กรุณาบันทึกข้อมูลก่อน");
    }

    // 7. สร้างเลข PO อัตโนมัติ
    $next_po_num_result = $conn->query("SELECT MAX(id) + 1 AS next_id FROM purchase_orders");
    $next_po_num = $next_po_num_result ? $next_po_num_result->fetch_assoc()['next_id'] : 1;
    if (is_null($next_po_num)) $next_po_num = 1;
    $po_number = "PO-" . date("Y") . "-" . str_pad($next_po_num, 5, "0", STR_PAD_LEFT);

    // 8. บันทึก PO Header (⭐️ สถานะใหม่: Pending PO Approval ⭐️)
    $stmt_po = $conn->prepare("INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, status, created_by_user_id, pr_id)
                              VALUES (?, ?, CURDATE(), CURDATE(), 'Pending PO Approval', ?, ?)");
    $stmt_po->bind_param("siii", $po_number, $supplier_id, $created_by_user_id, $pr_id);
    $stmt_po->execute();
    $po_id = $conn->insert_id;
    $stmt_po->close();

    // 9. คัดลอก Item จาก Quotation ไปยัง PO Items
    $stmt_copy = $conn->prepare("INSERT INTO po_items (po_id, material_id, quantity_ordered, unit_price)
                                SELECT ?, material_id, quantity, unit_price 
                                FROM quotation_items 
                                WHERE quotation_header_id = ?");
    $stmt_copy->bind_param("ii", $po_id, $quote_header_id);
    $stmt_copy->execute();
    $stmt_copy->close();

    // 10. อัปเดตสถานะ PR เป็น 'PO Created'
    $conn->query("UPDATE purchase_requisitions SET status = 'PO Created' WHERE id = $pr_id");

    // 11. ถ้าสำเร็จ
    $conn->commit();
    $_SESSION['alert_message'] = "ส่ง PO เลขที่ $po_number (จาก PR: $pr_id) ไปยังผู้จัดการเพื่ออนุมัติสำเร็จ!";
    $_SESSION['alert_type'] = "success";
    header("Location: po_list.php"); // (ไปหน้า PO List)
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    header("Location: pr_compare.php?pr_id=" . $pr_id); // (กลับไปหน้าเทียบราคา)
    exit();
}
?>