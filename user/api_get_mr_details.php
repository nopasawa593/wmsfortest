<?php
// 1. เชื่อมต่อฐานข้อมูล
require_once '../config/db_connect.php'; 

// 2. ตรวจสอบสิทธิ์
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied: Please Login']);
    exit();
}

if (!isset($_GET['req_id']) || empty($_GET['req_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Requisition ID']);
    exit();
}

$req_id = (int)$_GET['req_id'];
$response_data = [];

$conn->begin_transaction();

try {
    // --- 4. ดึงข้อมูล MR Header ---
    $sql_header = "SELECT 
                    r.mr_number, 
                    r.request_date, 
                    r.department, 
                    r.status,
                    u_req.full_name AS requester_name,
                    u_app.full_name AS approver_name
                   FROM requisitions r
                   JOIN users u_req ON r.requested_by_user_id = u_req.id
                   LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
                   WHERE r.id = ?";
                   
    $stmt_header = $conn->prepare($sql_header);
    $stmt_header->bind_param("i", $req_id);
    $stmt_header->execute();
    $header_result = $stmt_header->get_result();
    $header = $header_result->fetch_assoc();
    $stmt_header->close();

    if (!$header) {
        throw new Exception("ไม่พบข้อมูลใบเบิก (MR Not Found)");
    }
    $response_data['header'] = $header;

    // --- 5. ดึงข้อมูล MR Items (คำนวณยอดจ่ายจริงจาก GI Logs) ---
    // ⭐️ ใช้ Subquery (SELECT SUM...) เพื่อไปนับยอดที่จ่ายจริงๆ ในตาราง gi_items
    // วิธีนี้ต่อให้ตารางใบเบิกไม่อัปเดต ยอดก็จะตรงเสมอ
    $sql_items = "SELECT 
                    ri.quantity_requested,
                    
                    -- ⭐️ สูตรแก้บั๊ก: ดึงยอดรวมที่จ่ายจริงจาก GI Items
                    (SELECT COALESCE(SUM(gii.quantity_issued), 0)
                     FROM gi_items gii
                     JOIN goods_issuing gi ON gii.gi_id = gi.id
                     WHERE gi.requisition_id = ri.requisition_id 
                     AND gii.material_id = ri.material_id) AS quantity_issued,
                     
                    m.item_code,
                    m.name,
                    m.unit,
                    l.location_code
                  FROM requisition_items ri
                  JOIN materials m ON ri.material_id = m.id
                  LEFT JOIN locations l ON m.default_location_id = l.id
                  WHERE ri.requisition_id = ?";
                  
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $req_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();
    
    $response_data['items'] = $items;
    
    $conn->commit();
    $conn->close();
    
    // 6. ส่งข้อมูลกลับ
    header('Content-Type: application/json');
    echo json_encode($response_data);

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>