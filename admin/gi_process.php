<?php
require_once '../config/db_connect.php'; 

if (session_status() == PHP_SESSION_NONE) session_start();

// ตรวจสอบสิทธิ์ (เฉพาะทีมพัสดุและ Admin)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['ADMIN', 'WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied");
}

$issued_by_user_id = $_SESSION['user_id']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $requisition_id = $_POST['requisition_id'];
    $material_ids = $_POST['inventory_id']; // Key คือ Material ID
    $quantities_issued = $_POST['quantity_issued']; // ยอดที่กรอกมา
    $selected_serials_json = isset($_POST['selected_serials']) ? $_POST['selected_serials'] : [];

    $conn->begin_transaction();

    try {
        // 1. ตรวจสอบสถานะใบเบิกก่อน
        $check_stmt = $conn->prepare("SELECT status FROM requisitions WHERE id = ? FOR UPDATE");
        $check_stmt->bind_param("i", $requisition_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result()->fetch_assoc();
        $current_status = $res['status'];
        $check_stmt->close();

        if ($current_status != 'Pending Issue') {
            throw new Exception("ใบเบิกนี้ไม่ได้อยู่ในสถานะรอจ่าย (อาจถูกจ่ายไปแล้วหรือยกเลิก)");
        }

        // 2. สร้าง Header การจ่าย (Goods Issuing)
        $stmt_gi = $conn->prepare("INSERT INTO goods_issuing (requisition_id, issue_date, issued_by_user_id) VALUES (?, NOW(), ?)");
        $stmt_gi->bind_param("ii", $requisition_id, $issued_by_user_id);
        $stmt_gi->execute();
        $gi_id = $conn->insert_id;
        $stmt_gi->close();

        // --- เตรียม Statement ต่างๆ ---
        
        // A. บันทึกประวัติการจ่าย (GI Log)
        $stmt_gi_item = $conn->prepare("INSERT INTO gi_items (gi_id, material_id, location_id, batch_number, quantity_issued) VALUES (?, ?, ?, ?, ?)");
        
        // B. ตัดสต็อกแบบ Batch (ลด On-Hand และ Reserved)
        $stmt_update_inv = $conn->prepare("UPDATE inventory SET quantity = quantity - ?, quantity_reserved = quantity_reserved - ? WHERE id = ?");
        
        // C. ⭐️ (สำคัญมาก) อัปเดตยอดจ่ายจริงกลับไปที่ใบเบิก
        // (บรรทัดนี้คือตัวแก้ปัญหาที่ยอดไม่ขึ้นครับ)
        $stmt_update_req_item = $conn->prepare("UPDATE requisition_items SET quantity_issued = quantity_issued + ? WHERE requisition_id = ? AND material_id = ?");
        
        // D. สำหรับ Serial: อัปเดตสถานะ Serial
        $stmt_serial_update = $conn->prepare("UPDATE product_serials SET status = 'Issued', current_location_id = NULL, gi_id = ? WHERE serial_number = ? AND material_id = ?");
        
        // E. สำหรับ Serial: ตัดสต็อกรวม (ทีละ 1)
        $stmt_inv_deduct_serial = $conn->prepare("UPDATE inventory SET quantity = quantity - 1, quantity_reserved = quantity_reserved - 1 WHERE material_id = ? AND location_id = ? LIMIT 1");

        // 3. วนลูปจ่ายของทีละรายการ
        foreach ($material_ids as $mat_id => $inv_val) {
            
            $qty_to_issue = (float)$quantities_issued[$mat_id];
            
            // ถ้าไม่มียอดจ่าย ให้ข้าม
            if ($qty_to_issue <= 0) continue;

            if ($inv_val === 'SERIAL_MODE') {
                // --- กรณีจ่ายแบบ Serial ---
                $serials = json_decode($selected_serials_json[$mat_id], true);
                
                // ตรวจสอบจำนวน Serial ที่เลือก
                if (count($serials) != $qty_to_issue) {
                    throw new Exception("จำนวน Serial ที่เลือกไม่ตรงกับยอดจ่าย (Material ID: $mat_id)");
                }

                foreach ($serials as $sn) {
                    // หา Location ของ Serial นี้
                    $loc_q = $conn->query("SELECT current_location_id FROM product_serials WHERE serial_number = '$sn' AND material_id = $mat_id");
                    $loc_row = $loc_q->fetch_assoc();
                    
                    if(!$loc_row) throw new Exception("ไม่พบข้อมูล Serial: $sn");
                    $loc_id = $loc_row['current_location_id'];

                    // 1. Update Serial Table -> Issued
                    $stmt_serial_update->bind_param("isi", $gi_id, $sn, $mat_id);
                    $stmt_serial_update->execute();

                    // 2. Update Inventory Table (ลดจำนวนรวม)
                    $stmt_inv_deduct_serial->bind_param("ii", $mat_id, $loc_id);
                    $stmt_inv_deduct_serial->execute();

                    // 3. Insert Log GI Item (ทีละชิ้น)
                    $sn_batch = "SN: " . $sn;
                    $one = 1;
                    $stmt_gi_item->bind_param("iisid", $gi_id, $mat_id, $loc_id, $sn_batch, $one);
                    $stmt_gi_item->execute();
                }

            } else {
                // --- กรณีจ่ายแบบ Batch/Lot (ทั่วไป) ---
                $inventory_id = (int)$inv_val;
                
                // ดึงข้อมูล Inventory เดิม (เพื่อเอา Location/Batch)
                $inv_info = $conn->query("SELECT location_id, batch_number FROM inventory WHERE id = $inventory_id")->fetch_assoc();
                
                if(!$inv_info) throw new Exception("ไม่พบข้อมูล Inventory ID: $inventory_id");

                // 1. ตัดสต็อก
                $stmt_update_inv->bind_param("ddi", $qty_to_issue, $qty_to_issue, $inventory_id);
                $stmt_update_inv->execute();

                // 2. Insert Log GI Item
                $stmt_gi_item->bind_param("iisid", $gi_id, $mat_id, $inv_info['location_id'], $inv_info['batch_number'], $qty_to_issue);
                $stmt_gi_item->execute();
            }

            // ⭐️ อัปเดตกลับไปที่ตารางใบเบิก (สำคัญที่สุด) ⭐️
            $stmt_update_req_item->bind_param("dii", $qty_to_issue, $requisition_id, $mat_id);
            $stmt_update_req_item->execute();
        }

        // 4. เปลี่ยนสถานะใบเบิกเป็น Issued (จ่ายแล้ว)
        $conn->query("UPDATE requisitions SET status = 'Issued', approved_by_user_id = $issued_by_user_id WHERE id = $requisition_id");

        $conn->commit();
        $_SESSION['alert_message'] = "จ่ายวัสดุสำเร็จ! (GI No: $gi_id)";
        $_SESSION['alert_type'] = "success";
        
        header("Location: requisition_list.php"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header("Location: gi_issue.php?req_id=$requisition_id");
        exit();
    }
} else {
    // ถ้าเข้าโดยตรง
    header("Location: requisition_list.php");
    exit();
}
?>