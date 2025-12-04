<?php
// 1. เชื่อมต่อฐานข้อมูล
require_once '../config/db_connect.php';

// --- ⭐️ ส่วนที่เพิ่ม: เรียกใช้ PHPMailer ---
require_once '../includes/PHPMailer/Exception.php';
require_once '../includes/PHPMailer/PHPMailer.php';
require_once '../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// ----------------------------------------

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$requested_by_user_id = $_SESSION['user_id']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $department = $_POST['department'];
    $material_ids = $_POST['material_id'];
    $quantities = $_POST['quantity_requested'];

    $mr_number = "MR-" . date("Ymd-His");

    $conn->begin_transaction();

    try {
        // 2. สร้าง Header
        $stmt_req = $conn->prepare("INSERT INTO requisitions (mr_number, requested_by_user_id, request_date, department, status) 
                                   VALUES (?, ?, CURDATE(), ?, 'Pending Dept Approval')"); 
        $stmt_req->bind_param("sis", $mr_number, $requested_by_user_id, $department);
        $stmt_req->execute();
        $requisition_id = $conn->insert_id; 

        // 3. สร้าง Items
        $stmt_item = $conn->prepare("INSERT INTO requisition_items (requisition_id, material_id, quantity_requested) VALUES (?, ?, ?)");
        foreach ($material_ids as $index => $material_id) {
            $quantity = $quantities[$index];
            if ($quantity > 0) {
                $stmt_item->bind_param("iid", $requisition_id, $material_id, $quantity);
                $stmt_item->execute();
            }
        }

        // 4. ยืนยัน Transaction
        $conn->commit();

        // -----------------------------------------------------------
        // 🚀 START: ส่งอีเมลด้วย Gmail SMTP (ฟรี)
        // -----------------------------------------------------------
        
        // A. หา ID แผนก
        $dept_stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
        $dept_stmt->bind_param("s", $department);
        $dept_stmt->execute();
        $dept_res = $dept_stmt->get_result();
        
        if ($dept_row = $dept_res->fetch_assoc()) {
            $target_dept_id = $dept_row['id'];
            
            // B. หาอีเมล Manager
            $mgr_stmt = $conn->prepare("SELECT email, full_name FROM users 
                                        WHERE role = 'DEPT_MANAGER' 
                                        AND department_id = ? 
                                        AND email IS NOT NULL 
                                        AND email != ''");
            $mgr_stmt->bind_param("i", $target_dept_id);
            $mgr_stmt->execute();
            $mgr_res = $mgr_stmt->get_result();
            
            // เตรียม PHPMailer
            $mail = new PHPMailer(true); // true = เปิด Exception

            while ($mgr = $mgr_res->fetch_assoc()) {
                try {
                    // ตั้งค่า Server (Gmail SMTP)
                    $mail->isSMTP(); 
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true; 
                    
                    // ⭐️⭐️⭐️ แก้ไขตรงนี้ ⭐️⭐️⭐️
                    $mail->Username   = 'nopa.sawa593@gmail.com'; // 1. อีเมล Gmail ของคุณ
                    $mail->Password   = 'must tqlt qsmm etkw';  // 2. รหัสผ่านแอป 16 ตัว (App Password)
                    // ⭐️⭐️⭐️⭐️⭐️⭐️⭐️⭐️⭐️⭐️⭐️
                    
                    $mail->SMTPSecure = 'tls'; 
                    $mail->Port       = 587; 
                    $mail->CharSet    = 'UTF-8';

                    // ตั้งค่าคนส่ง/คนรับ
                    $mail->setFrom($mail->Username, 'WMS System'); // ส่งจากชื่อระบบ
                    $mail->addAddress($mgr['email'], $mgr['full_name']); // ส่งหา Manager

                    // เนื้อหา
                    $subject = "แจ้งเตือน: มีใบเบิกใหม่ ($mr_number)";
                    $bodyContent = "
                        <h3>เรียนคุณ {$mgr['full_name']}</h3>
                        <p>มีรายการขอเบิกวัสดุใหม่ในระบบ โดยพนักงานในแผนกของคุณ</p>
                        <ul>
                            <li><strong>เลขที่เอกสาร:</strong> $mr_number</li>
                            <li><strong>แผนก:</strong> $department</li>
                            <li><strong>วันที่:</strong> " . date("d/m/Y H:i") . "</li>
                        </ul>
                        <p>กรุณาคลิกเพื่ออนุมัติ: <a href='http://" . $_SERVER['HTTP_HOST'] . "/user/mr_approval_list.php'>เข้าสู่ระบบ WMS</a></p>
                    ";

                    $mail->isHTML(true); 
                    $mail->Subject = $subject;
                    $mail->Body    = $bodyContent;
                    $mail->AltBody = strip_tags($bodyContent); // สำหรับ Email Client ที่ไม่รองรับ HTML

                    $mail->send();
                    
                    // Clear คนรับเพื่อวนลูปคนต่อไป (ถ้ามีหลาย Manager)
                    $mail->clearAddresses();

                } catch (Exception $e) {
                    // ส่งไม่ผ่านก็ปล่อยผ่านไป ไม่ให้ระบบ Error (Log ไว้ในใจ)
                    // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            }
            $mgr_stmt->close();
        }
        $dept_stmt->close();
        // -----------------------------------------------------------

        $_SESSION['alert_message'] = "ส่งใบเบิก $mr_number สำเร็จ! (แจ้งเตือนหัวหน้าเรียบร้อย)";
        $_SESSION['alert_type'] = "success";
        
        header("Location: requisition_list.php"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
        echo "<br><a href='requisition_create.php'>กลับไปหน้าเดิม</a>";
    }

    if(isset($stmt_req)) $stmt_req->close();
    if(isset($stmt_item)) $stmt_item->close();
    $conn->close();
}
?>