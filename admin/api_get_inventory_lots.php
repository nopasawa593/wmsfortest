<?php
// (ไฟล์นี้ไม่มี HTML)
// ⭐️ (แก้ไข) 1. ใช้ Absolute Path ⭐️
require_once($_SERVER['DOCUMENT_ROOT'] . '/config/db_connect.php'); 

// (ตรวจสอบสิทธิ์เบื้องต้น - ต้อง Login)
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

// ⭐️ (เพิ่ม) 2. ตรวจสอบสิทธิ์ Role (ต้องเป็นทีมพัสดุ) ⭐️
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


if (!isset($_GET['material_id']) || empty($_GET['material_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing material_id']);
    exit();
}

$material_id = (int)$_GET['material_id'];

$sql = "SELECT i.id, l.location_code, i.batch_number, i.quantity, i.quantity_reserved
        FROM inventory i
        JOIN locations l ON i.location_id = l.id
        WHERE i.material_id = ?
        ORDER BY l.location_code, i.batch_number";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$lots = [];

while ($row = $result->fetch_assoc()) {
    $lots[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($lots);
?>