<?php
// (ไฟล์นี้ไม่มี HTML)
require_once '../config/db_connect.php'; 

// (ตรวจสอบสิทธิ์เบื้องต้น)
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}
// (ต้องเช็ค Role ด้วย)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
     http_response_code(403);
    echo json_encode(['error' => 'Access Denied (Role)']);
    exit();
}


if (!isset($_GET['pr_id']) || empty($_GET['pr_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing pr_id']);
    exit();
}

$pr_id = (int)$_GET['pr_id'];
$response = [
    'pr_items' => [],
    'suppliers' => [],
    'prices' => []
];

$conn->begin_transaction();
try {
    // 1. ดึงรายการวัสดุใน PR (Base Items)
    $pr_items_sql = "SELECT i.material_id, i.quantity_requested, m.item_code, m.name, m.unit
                     FROM pr_items i
                     JOIN materials m ON i.material_id = m.id
                     WHERE i.pr_id = ?";
    $stmt_items = $conn->prepare($pr_items_sql);
    $stmt_items->bind_param("i", $pr_id);
    $stmt_items->execute();
    $pr_items_result = $stmt_items->get_result();
    while ($row = $pr_items_result->fetch_assoc()) {
        $response['pr_items'][] = $row;
    }
    $stmt_items->close();

    // 2. ดึง Supplier ทั้งหมดที่ถูกเทียบราคาใน PR นี้
    $suppliers_sql = "SELECT qh.id, qh.supplier_id, s.name AS supplier_name, qh.total_amount, qh.quotation_file_path
                      FROM quotation_headers qh
                      JOIN suppliers s ON qh.supplier_id = s.id
                      WHERE qh.pr_id = ?
                      ORDER BY qh.total_amount ASC"; // (เรียงตามราคารวมต่ำสุด)
    $stmt_supp = $conn->prepare($suppliers_sql);
    $stmt_supp->bind_param("i", $pr_id);
    $stmt_supp->execute();
    $suppliers_result = $stmt_supp->get_result();
    
    $quote_header_ids = [];
    while ($row = $suppliers_result->fetch_assoc()) {
        $response['suppliers'][] = $row;
        $quote_header_ids[] = $row['id']; // (เก็บ ID ของ Header)
    }
    $stmt_supp->close();

    // 3. ดึงราคาทั้งหมด (ถ้ามี Supplier)
    if (!empty($quote_header_ids)) {
        $placeholders = implode(',', array_fill(0, count($quote_header_ids), '?'));
        $types = str_repeat('i', count($quote_header_ids));
        
        // ⭐️ (แก้ไข) เปลี่ยน `qh.quotation_header_id` เป็น `qi.quotation_header_id` (หรือ `qh.id`) ⭐️
        $prices_sql = "SELECT 
                            qi.material_id, 
                            qh.supplier_id, 
                            qi.unit_price
                       FROM quotation_items qi
                       JOIN quotation_headers qh ON qi.quotation_header_id = qh.id
                       WHERE qi.quotation_header_id IN ($placeholders)"; // ⭐️ (ใช้ ID จากตาราง items)
        
        $stmt_prices = $conn->prepare($prices_sql);
        $stmt_prices->bind_param($types, ...$quote_header_ids);
        $stmt_prices->execute();
        $prices_result = $stmt_prices->get_result();
        
        while ($row = $prices_result->fetch_assoc()) {
            $response['prices'][$row['material_id']][$row['supplier_id']] = $row['unit_price'];
        }
        $stmt_prices->close();
    }
    
    $conn->commit();
    $conn->close();
    
    // 4. ส่งข้อมูลกลับเป็น JSON
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>