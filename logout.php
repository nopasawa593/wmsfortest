<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. ล้างค่า Session ทั้งหมด
session_unset();

// 2. ทำลาย Session
session_destroy();

// 3. กลับไปหน้า Login
header("Location: login.php");
exit();
?>