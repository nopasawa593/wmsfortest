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

if (!isset($_GET['pr_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing pr_id']);
    exit();
}

$pr_id = (int)$_GET['pr_id'];

$conn->begin_transaction();
try {
    // 1. ดึงข้อมูล PR Header
    $sql_header = "SELECT pr.*, u.full_name AS requester_name
                   FROM purchase_requisitions pr
                   JOIN users u ON pr.requested_by_user_id = u.id
                   WHERE pr.id = ?";
    $stmt_header = $conn->prepare($sql_header);
    $stmt_header->bind_param("i", $pr_id);
    $stmt_header->execute();
    $header = $stmt_header->get_result()->fetch_assoc();
    $stmt_header->close();

    if (!$header) {
        throw new Exception("PR not found");
    }

    // 2. ⭐️ (อัปเดต Logic) ดึงข้อมูล Items (PR หรือ PO Items) ⭐️
    $items = [];
    $po_info = null; // (เก็บข้อมูล PO)

    if ($header['status'] == 'PO Created') {
        // --- A. ถ้าสร้าง PO แล้ว ---
        // (ดึงข้อมูล PO)
        $po_stmt = $conn->prepare("SELECT id, po_number, status FROM purchase_orders WHERE pr_id = ? LIMIT 1");
        $po_stmt->bind_param("i", $pr_id);
        $po_stmt->execute();
        $po_info = $po_stmt->get_result()->fetch_assoc();
        $po_stmt->close();

        if ($po_info) {
            // ⭐️ (ตามที่คุณขอ) ดึงจาก po_items เพื่อเอาราคา ⭐️
            $sql_items = "SELECT 
                            pi.quantity_ordered, 
                            pi.unit_price,
                            m.item_code, 
                            m.name, 
                            m.unit
                          FROM po_items pi
                          JOIN materials m ON pi.material_id = m.id
                          WHERE pi.po_id = ?";
            $stmt_items = $conn->prepare($sql_items);
            $stmt_items->bind_param("i", $po_info['id']); // (ใช้ PO ID)
            $stmt_items->execute();
            $items_result = $stmt_items->get_result();
            while ($row = $items_result->fetch_assoc()) {
                $items[] = $row;
            }
            $stmt_items->close();
        }

    } else {
        // --- B. ถ้ายังไม่สร้าง PO (Pending, Approved, Rejected) ---
        // (ดึงจาก pr_items เหมือนเดิม - ไม่มีราคา)
        $sql_items = "SELECT pri.quantity_requested, m.item_code, m.name, m.unit
                      FROM pr_items pri
                      JOIN materials m ON pri.material_id = m.id
                      WHERE pri.pr_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $pr_id);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        while ($row = $items_result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt_items->close();
    }
    
    $conn->commit();
    $conn->close();
    
    // 4. ส่งข้อมูลกลับเป็น JSON
    header('Content-Type: application/json');
    echo json_encode([
        'header' => $header,
        'items' => $items,
        'po_info' => $po_info // (ส่งข้อมูล PO ไปด้วย)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>