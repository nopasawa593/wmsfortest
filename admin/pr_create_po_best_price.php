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
    die("Access Denied: You do not have permission to create POs.");
}

// 5. ตรวจสอบ PR ID
if (!isset($_GET['pr_id']) || empty($_GET['pr_id'])) {
    die("Error: PR ID is missing.");
}
$pr_id = (int)$_GET['pr_id'];
$created_by_user_id = $_SESSION['user_id'];

$conn->begin_transaction();
try {
    
    // 6. ดึงข้อมูล PR และ Items
    $pr_check_stmt = $conn->prepare("SELECT status FROM purchase_requisitions WHERE id = ?");
    $pr_check_stmt->bind_param("i", $pr_id);
    $pr_check_stmt->execute();
    $pr_status = $pr_check_stmt->get_result()->fetch_assoc()['status'];
    $pr_check_stmt->close();

    if ($pr_status != 'Approved') {
        throw new Exception("PR นี้ไม่อยู่ในสถานะ 'Approved' (อาจถูกสร้าง PO ไปแล้ว)");
    }
    
    $pr_items_stmt = $conn->prepare("SELECT material_id, quantity_requested FROM pr_items WHERE pr_id = ?");
    $pr_items_stmt->bind_param("i", $pr_id);
    $pr_items_stmt->execute();
    $pr_items_result = $pr_items_stmt->get_result();
    
    if ($pr_items_result->num_rows == 0) {
        throw new Exception("ไม่พบรายการวัสดุใน PR นี้");
    }
    
    $pr_items = [];
    while ($item = $pr_items_result->fetch_assoc()) {
        $pr_items[$item['material_id']] = $item['quantity_requested'];
    }
    $pr_items_stmt->close();

    // 7. ⭐️ (Logic หลัก) ค้นหาราคาที่ดีที่สุดของ "แต่ละรายการ" ⭐️
    $best_prices = []; // [material_id] => ['supplier_id' => X, 'price' => Y]
    $supplier_groups = []; // [supplier_id] => [ item1, item2 ]
    
    $find_best_stmt = $conn->prepare(
        "SELECT qi.material_id, qh.supplier_id, qi.unit_price
         FROM quotation_items qi
         JOIN quotation_headers qh ON qi.quotation_header_id = qh.id
         WHERE qh.pr_id = ? AND qi.material_id = ? AND qi.unit_price > 0
         ORDER BY qi.unit_price ASC
         LIMIT 1"
    );

    foreach ($pr_items as $material_id => $quantity) {
        $find_best_stmt->bind_param("ii", $pr_id, $material_id);
        $find_best_stmt->execute();
        $best_offer = $find_best_stmt->get_result()->fetch_assoc();

        if ($best_offer) {
            $supplier_id = $best_offer['supplier_id'];
            // (จัดกลุ่มวัสดุตาม Supplier ที่ชนะ)
            $supplier_groups[$supplier_id][] = [
                'material_id' => $material_id,
                'quantity' => $quantity,
                'unit_price' => $best_offer['unit_price']
            ];
        } else {
            // (กรณีไม่มีใครยื่นราคา (ราคา=0) หรือไม่มีข้อมูล)
            throw new Exception("วัสดุ ID: $material_id ไม่มีใบเสนอราคาที่ถูกต้อง (ราคาต้องมากกว่า 0)");
        }
    }
    $find_best_stmt->close();

    if (empty($supplier_groups)) {
        throw new Exception("ไม่พบข้อมูลใบเสนอราคา (Quotation) ที่บันทึกไว้สำหรับ PR นี้");
    }

    $created_po_numbers = [];

    // 8. ⭐️ (Loop สร้าง PO แยกตาม Supplier) ⭐️
    foreach ($supplier_groups as $supplier_id => $items) {
        
        // 8.1 สร้างเลข PO อัตโนมัติ (เพิ่ม Suffix)
        $next_po_num_result = $conn->query("SELECT MAX(id) + 1 AS next_id FROM purchase_orders");
        $next_po_num = $next_po_num_result ? $next_po_num_result->fetch_assoc()['next_id'] : 1;
        if (is_null($next_po_num)) $next_po_num = 1;
        // (เพิ่ม Suffix -SUP{ID} ต่อท้าย)
        $po_number = "PO-" . date("Y") . "-" . str_pad($next_po_num, 5, "0", STR_PAD_LEFT) . "-S" . $supplier_id;

        // 8.2 บันทึก PO Header (สถานะ: Pending PO Approval)
        $stmt_po = $conn->prepare("INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, status, created_by_user_id, pr_id)
                                  VALUES (?, ?, CURDATE(), CURDATE(), 'Pending PO Approval', ?, ?)");
        $stmt_po->bind_param("siii", $po_number, $supplier_id, $created_by_user_id, $pr_id);
        $stmt_po->execute();
        $po_id = $conn->insert_id;
        $stmt_po->close();
        
        $created_po_numbers[] = $po_number; // (เก็บไว้แสดงผล)

        // 8.3 บันทึก PO Items
        $stmt_item = $conn->prepare("INSERT INTO po_items (po_id, material_id, quantity_ordered, unit_price) 
                                    VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt_item->bind_param("iidd", $po_id, $item['material_id'], $item['quantity'], $item['unit_price']);
            $stmt_item->execute();
        }
        $stmt_item->close();
    }

    // 9. อัปเดตสถานะ PR เป็น 'PO Created'
    $conn->query("UPDATE purchase_requisitions SET status = 'PO Created' WHERE id = $pr_id");

    // 10. ถ้าสำเร็จ
    $conn->commit();
    $_SESSION['alert_message'] = "สร้าง PO (ตามราคาดีที่สุด) สำเร็จ " . count($created_po_numbers) . " ฉบับ (" . implode(', ', $created_po_numbers) . ") และส่งให้ผู้จัดการอนุมัติแล้ว";
    $_SESSION['alert_type'] = "success";
    
    // ⭐️ (แก้ไข) 11. Redirect ไปหน้า po_list.php ⭐️
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