<?php
// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// ข้อมูลเชื่อมต่อฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_USER', 'u461147464_wms');
define('DB_PASS', '1ha!L@np1992'); // XAMPP ค่าเริ่มต้นคือไม่มีรหัสผ่าน
define('DB_NAME', 'u461147464_wms');

// สร้างการเชื่อมต่อ
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ตั้งค่า UTF-8
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// เริ่ม Session สำหรับการ Login
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>