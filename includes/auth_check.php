<?php
// (ไฟล์นี้จะถูกเรียกโดย header.php *หลังจาก* db_connect.php)
// (db_connect.php ได้ทำการ session_start() ไปแล้ว)

// ตรวจสอบว่ามี 'user_id' ใน Session หรือไม่
if (!isset($_SESSION['user_id'])) {
    
    // ⭐️ (แก้ไข) 1. เปลี่ยน Path ให้ถูกต้อง (ชี้ไปที่ Root)
    header("Location: /login.php"); 
    
    // ⭐️ (สำคัญ) 2. ต้องมี exit() เสมอ
    exit(); 
}

// (ถัดจากนี้คือคนท่ี Login แล้ว)
?>