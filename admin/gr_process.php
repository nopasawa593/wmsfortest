<?php
require_once '../config/db_connect.php'; 

if (session_status() == PHP_SESSION_NONE) session_start();

if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied");
}

$received_by_user_id = $_SESSION['user_id']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // รับค่า Header
    $po_id = !empty($_POST['po_id']) ? $_POST['po_id'] : NULL;
    $supplier_id = $_POST['supplier_id'];
    $notes = $_POST['notes'];
    
    // รับค่า Items
    $material_ids = $_POST['material_id']; 
    $quantities = $_POST['quantity'];
    $batch_numbers = $_POST['batch_number'];
    $location_ids = $_POST['location_id'];
    $serials_json_array = isset($_POST['serials']) ? $_POST['serials'] : [];

    $conn->begin_transaction();

    try {
        // 1. สร้าง GR Header
        $stmt_gr = $conn->prepare("INSERT INTO goods_receiving (po_id, supplier_id, received_by_user_id, notes) VALUES (?, ?, ?, ?)");
        $stmt_gr->bind_param("iiis", $po_id, $supplier_id, $received_by_user_id, $notes);
        $stmt_gr->execute();
        $gr_id = $conn->insert_id;
        $stmt_gr->close();

        // เตรียม Statements
        $stmt_gr_item = $conn->prepare("INSERT INTO gr_items (gr_id, material_id, quantity_received, batch_number, putaway_location_id) VALUES (?, ?, ?, ?, ?)");
        
        $stmt_inv = $conn->prepare(
            "INSERT INTO inventory (material_id, location_id, batch_number, quantity) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
        );
        
        // ⭐️ Logic ใหม่สำหรับ product_serials: เก็บเฉพาะถ้า Qty = 1 ⭐️
        $stmt_serial = $conn->prepare(
            "INSERT INTO product_serials (material_id, serial_number, current_location_id, status, gr_id, po_id, receive_date) 
             VALUES (?, ?, ?, 'In Stock', ?, ?, CURDATE())"
        );
        
        if ($po_id) {
            $stmt_po_update = $conn->prepare("UPDATE po_items SET quantity_received = quantity_received + ? 
                                              WHERE po_id = ? AND material_id = ?");
        }

        // 2. วนลูปบันทึกแต่ละรายการ
        foreach ($material_ids as $index => $material_id) {
            
            $quantity = (float)$quantities[$index];
            $batch = $batch_numbers[$index];
            $location_id = (int)$location_ids[$index];
            $serials_json = isset($serials_json_array[$index]) ? $serials_json_array[$index] : '[]';
            
            if (empty($material_id) || $quantity <= 0) continue;

            // แปลง JSON
            $serial_list = json_decode($serials_json, true);
            
            // ⭐️ กรณีมีการระบุ Serial Detail (Object Array) ⭐️
            if (!empty($serial_list) && is_array($serial_list)) {
                
                // วนลูปย่อย เพื่อบันทึกทีละ "Serial/Lot" ที่ระบุใน Modal
                foreach ($serial_list as $item) {
                    // รองรับทั้งแบบ Object ใหม่ {serial, qty} และแบบเก่า (เผื่อไว้)
                    $sn_code = is_array($item) ? $item['serial'] : $item;
                    $sn_qty  = is_array($item) ? (float)$item['qty'] : 1;
                    
                    $sn_code = trim($sn_code);
                    if (empty($sn_code)) continue;

                    // (A) Insert gr_items (Log รายการย่อย)
                    $stmt_gr_item->bind_param("iidss", $gr_id, $material_id, $sn_qty, $sn_code, $location_id);
                    $stmt_gr_item->execute();

                    // (B) Insert/Update inventory (Inventory รวม)
                    // ใช้ Serial เป็น Batch Number
                    $stmt_inv->bind_param("isdi", $material_id, $location_id, $sn_code, $sn_qty);
                    $stmt_inv->execute();

                    // (C) Insert product_serials (เฉพาะถ้า Qty == 1)
                    // ถ้า Qty > 1 ถือว่าเป็น Batch ไม่ใช่ Serial รายชิ้น จึงไม่ลงตารางนี้
                    if ($sn_qty == 1) {
                        $stmt_serial->bind_param("isiis", $material_id, $sn_code, $location_id, $gr_id, $po_id);
                        // ใช้ try-catch ย่อย เพื่อกัน Error Duplicate แล้วระบบล่ม (ข้ามตัวซ้ำไปเลย)
                        try {
                            $stmt_serial->execute();
                        } catch (Exception $ex) {
                            // อาจจะ Log ไว้ หรือปล่อยผ่าน (ถือว่ารับเป็น Batch)
                        }
                    }
                }

            } else {
                // ⭐️ กรณีรับแบบ Batch ปกติ (ไม่ได้ใช้ Modal Serial) ⭐️
                
                // (A) Insert gr_items
                $stmt_gr_item->bind_param("iidss", $gr_id, $material_id, $quantity, $batch, $location_id);
                $stmt_gr_item->execute();

                // (B) Inventory
                $stmt_inv->bind_param("isdi", $material_id, $location_id, $batch, $quantity);
                $stmt_inv->execute();
            }

            // (D) Update PO Items (อัปเดตยอดรวม)
            if ($po_id && isset($stmt_po_update)) {
                $stmt_po_update->bind_param("dii", $quantity, $po_id, $material_id);
                $stmt_po_update->execute();
            }
        }

        // 3. อัปเดตสถานะ PO
        if ($po_id) {
            $check_status_sql = "SELECT SUM(quantity_ordered) AS total_ordered, SUM(quantity_received) AS total_received FROM po_items WHERE po_id = $po_id";
            $po_summary = $conn->query($check_status_sql)->fetch_assoc();
            $new_po_status = ($po_summary['total_received'] >= $po_summary['total_ordered'] - 0.001) ? 'Completed' : 'Partial';
            $conn->query("UPDATE purchase_orders SET status = '$new_po_status' WHERE id = $po_id");
        }

        $conn->commit();
        $_SESSION['alert_message'] = "บันทึกรับของสำเร็จ! (GR ID: $gr_id)";
        $_SESSION['alert_type'] = "success";
        header("Location: gr_receive.php"); 

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        $redirect = "gr_receive.php" . ($po_id ? "?po_id=$po_id" : "");
        header("Location: $redirect");
    }

    // Close Statements
    if (isset($stmt_gr_item)) $stmt_gr_item->close();
    if (isset($stmt_inv)) $stmt_inv->close();
    if (isset($stmt_serial)) $stmt_serial->close();
    if (isset($stmt_po_update)) $stmt_po_update->close();
    $conn->close();

} else {
    header("Location: gr_receive.php");
}
?>