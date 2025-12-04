<?php
// (ไฟล์นี้ไม่มี HTML)
require_once '../config/db_connect.php'; 

// (ตรวจสอบสิทธิ์เบื้องต้น - ต้อง Login)
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

// (ตรวจสอบว่ามีการส่ง material_id มาหรือไม่)
if (!isset($_GET['material_id']) || empty($_GET['material_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing material_id']);
    exit();
}

$material_id = (int)$_GET['material_id'];

// (Query เพื่อรวมยอด On-Hand และ ยอด Reserved จากตาราง inventory)
$sql = "SELECT 
            COALESCE(SUM(quantity), 0) AS total_on_hand,
            COALESCE(SUM(quantity_reserved), 0) AS total_reserved
        FROM inventory
        WHERE material_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$on_hand = (float)$result['total_on_hand'];
$reserved = (float)$result['total_reserved'];
// (คำนวณยอดที่เบิกได้จริง)
$available = $on_hand - $reserved;

// (สร้างข้อมูล JSON เพื่อส่งกลับไปให้ JavaScript)
$response = [
    'on_hand' => $on_hand,     // คงคลัง (ยอดจริง)
    'reserved' => $reserved,   // รอจ่าย (ยอดที่ถูกจอง)
    'available' => $available  // เบิกได้จริง
];

header('Content-Type: application/json');
echo json_encode($response);
?>