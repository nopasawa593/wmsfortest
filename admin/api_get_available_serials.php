<?php
require_once '../config/db_connect.php'; 

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

if (!isset($_GET['material_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing material_id']);
    exit();
}

$material_id = (int)$_GET['material_id'];

// ดึง Serial ที่สถานะ 'In Stock' พร้อมข้อมูล Location
$sql = "SELECT ps.id, ps.serial_number, ps.current_location_id, l.location_code
        FROM product_serials ps
        LEFT JOIN locations l ON ps.current_location_id = l.id
        WHERE ps.material_id = ? AND ps.status = 'In Stock'
        ORDER BY ps.receive_date ASC, ps.id ASC"; // FIFO (เข้าก่อนออกก่อน)

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();

$serials = [];
while ($row = $result->fetch_assoc()) {
    $serials[] = $row;
}

header('Content-Type: application/json');
echo json_encode($serials);
?>