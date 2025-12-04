<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php'; 

// 2. เรียกใช้ auth_check.php
require_once '../includes/auth_check.php';

// 3. กำหนดฟังก์ชัน hasRole
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 4. ตรวจสอบสิทธิ์ (ต้องทำก่อน xử lý POST)
if (!hasRole(['WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์ในการสร้างใบสั่งซื้อ (PO)");
}

// 5. ⭐️ (ย้าย Logic POST มาไว้บนสุด) ⭐️
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $created_by_user_id = $_SESSION['user_id'];
    $pr_id_posted = $_POST['pr_id']; 
    $po_number = $_POST['po_number'];
    $supplier_id = $_POST['supplier_id']; // (Supplier ID จะถูกส่งมาจาก Form เสมอ)
    $order_date = $_POST['order_date'];
    $expected_delivery_date = $_POST['expected_delivery_date'];

    $material_ids = $_POST['material_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    $conn->begin_transaction();
    try {
        // 2.1 บันทึก PO Header
        $stmt_po = $conn->prepare("INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, status, created_by_user_id, pr_id)
                                  VALUES (?, ?, ?, ?, 'Pending', ?, ?)");
        $pr_id_param = !empty($pr_id_posted) ? (int)$pr_id_posted : NULL;
        $stmt_po->bind_param("sisssi", $po_number, $supplier_id, $order_date, $expected_delivery_date, $created_by_user_id, $pr_id_param);
        $stmt_po->execute();
        $po_id = $conn->insert_id;
        $stmt_po->close();

        // 2.2 บันทึก PO Items
        $stmt_item = $conn->prepare("INSERT INTO po_items (po_id, material_id, quantity_ordered, unit_price) 
                                    VALUES (?, ?, ?, ?)");

        $item_added = false;
        foreach ($material_ids as $index => $material_id) {
            $quantity = $quantities[$index] ?? 0;
            $price = $unit_prices[$index] ?? 0;

            if ($material_id && $quantity > 0) {
                $stmt_item->bind_param("iidd", $po_id, $material_id, $quantity, $price);
                $stmt_item->execute();
                $item_added = true;
            }
        }
        $stmt_item->close(); 

        if (!$item_added) {
             throw new Exception("กรุณาเพิ่มรายการวัสดุอย่างน้อย 1 รายการ");
        }


        // 2.3 อัปเดตสถานะ PR เป็น 'PO Created'
        if ($pr_id_param) {
            $conn->query("UPDATE purchase_requisitions SET status = 'PO Created' WHERE id = $pr_id_param");
        }

        $conn->commit();
        $_SESSION['alert_message'] = "สร้างใบสั่งซื้อ (PO) เลขที่ $po_number สำเร็จ!";
        $_SESSION['alert_type'] = "success";
        header("Location: po_list.php"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        $redirect_url = "po_create.php" . ($pr_id_param ? "?pr_id=" . $pr_id_param : "");
         // (ถ้ามี supplier_id ให้ส่งกลับไปด้วย)
        if (isset($supplier_id)) {
            $redirect_url .= $pr_id_param ? "&" : "?";
            $redirect_url .= "supplier_id=" . $supplier_id;
        }
        header("Location: " . $redirect_url);
        exit();
    }
}
// --- จบส่วน xử lý POST ---


// 6. ดึง Alert Message (ถ้ามี)
$message = "";
$message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message'];
    $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
}

// 7. Include Header
require_once '../includes/header.php';

// --- 8. ดึงข้อมูลสำหรับแสดงผล ---

// (ตัวแปรเริ่มต้น)
$pr_id = null;
$supplier_id = null;
$pr_number = "";
$supplier_name = "";
$is_from_pr = false;
$po_items = []; // (Array ใหม่สำหรับเก็บรายการทั้งหมด)

// ⭐️ (Logic ใหม่: ตรวจสอบการส่งค่ามาจากหน้า เทียบราคา) ⭐️
if (isset($_GET['pr_id']) && !empty($_GET['pr_id'])) {
    $pr_id = (int)$_GET['pr_id'];
    $is_from_pr = true;
    
    // ดึง PR Number
    $pr_header_result = $conn->query("SELECT pr_number FROM purchase_requisitions WHERE id = $pr_id AND status IN ('Approved', 'PO Created')");
    $pr_header = $pr_header_result ? $pr_header_result->fetch_assoc() : null;
    $pr_number = $pr_header['pr_number'] ?? 'N/A';

    // (ถ้ามี Supplier ID ส่งมาด้วย = มาจากหน้าเทียบราคา)
    if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
        $supplier_id = (int)$_GET['supplier_id'];
        
        // (ดึงชื่อ Supplier)
        $supplier_result = $conn->query("SELECT name FROM suppliers WHERE id = $supplier_id");
        $supplier_name = $supplier_result ? $supplier_result->fetch_assoc()['name'] : 'N/A';
        
        // (ดึงรายการวัสดุ + ราคาที่ชนะ)
        $items_sql = "SELECT 
                        qi.material_id, qi.quantity, qi.unit_price,
                        m.item_code, m.name, m.unit
                    FROM quotation_items qi
                    JOIN quotation_headers qh ON qi.quotation_header_id = qh.id
                    JOIN materials m ON qi.material_id = m.id
                    WHERE qh.pr_id = ? AND qh.supplier_id = ?";
        $stmt_items = $conn->prepare($items_sql);
        $stmt_items->bind_param("ii", $pr_id, $supplier_id);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        while($row = $items_result->fetch_assoc()) {
            $po_items[] = [
                'material_id' => $row['material_id'],
                'item_code' => $row['item_code'],
                'name' => $row['name'],
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'unit_price' => $row['unit_price']
            ];
        }
        $stmt_items->close();
    }
    // (ถ้าไม่มี Supplier ID = สร้าง PO แบบ Manual ไม่ผูก PR (Flow เดิมที่คุณยังไม่ได้พัฒนาส่วน Add Row))
}

// (ดึง Supplier List (สำหรับกรณีสร้าง PO Manual))
$suppliers_result_list = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");

// (สร้างเลข PO อัตโนมัติ)
$next_po_num_result = $conn->query("SELECT MAX(id) + 1 AS next_id FROM purchase_orders");
$next_po_num = $next_po_num_result ? $next_po_num_result->fetch_assoc()['next_id'] : 1;
if (is_null($next_po_num)) $next_po_num = 1;
$po_number_generated = "PO-" . date("Y") . "-" . str_pad($next_po_num, 5, "0", STR_PAD_LEFT);
?>

<h1 class="mb-4">สร้างใบสั่งซื้อ (Purchase Order)</h1>
<?php if ($is_from_pr) : ?>
    <div class="alert alert-info" role="alert">
      <i class="bi bi-info-circle-fill"></i> กำลังสร้าง PO จากใบขอซื้อ (PR) เลขที่: <strong><?php echo $pr_number; ?></strong>
    </div>
<?php endif; ?>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<form action="po_create.php" method="POST" id="po-form">
    <input type="hidden" name="pr_id" value="<?php echo $pr_id; ?>">
    <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">ข้อมูลหลัก (PO Header)</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">เลขที่ PO</label>
                    <input type="text" class="form-control bg-light" name="po_number" value="<?php echo $po_number_generated; ?>" readonly>
                </div>
                <div class="col-md-8">
                    <label class="form-label">ผู้ขาย (Supplier)</label>
                    <?php if ($supplier_id): // (ถ้ามี Supplier ID = ล็อกช่องนี้) ?>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($supplier_name); ?>" readonly>
                    <?php else: // (ถ้าไม่มี = ให้เลือกเอง) ?>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- เลือกผู้ขาย (Manual) --</option>
                            <?php 
                            if ($suppliers_result_list) {
                                $suppliers_result_list->data_seek(0);
                                while($row = $suppliers_result_list->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">วันที่สั่งซื้อ</label>
                    <input type="date" class="form-control" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">วันที่คาดว่าจะได้รับ</label>
                    <input type="date" class="form-control" name="expected_delivery_date" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">รายการวัสดุ (PO Items)</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="po-item-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">วัสดุ</th>
                            <th style="width: 15%;">จำนวนสั่ง</th>
                            <th style="width: 15%;">หน่วย</th>
                            <th style="width: 20%;">ราคาต่อหน่วย (บาท)</th>
                            </tr>
                    </thead>
                    <tbody id="item-list">
                        <?php if (!empty($po_items)): ?>
                            <?php foreach ($po_items as $item): ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="material_id[]" value="<?php echo $item['material_id']; ?>">
                                        <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars("{$item['item_code']} - {$item['name']}"); ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control" value="<?php echo $item['quantity']; ?>" step="0.01" min="0.01" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars($item['unit']); ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="number" name="unit_price[]" class="form-control" step="0.01" min="0" value="<?php echo $item['unit_price']; ?>" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr><td colspan="4" class="text-center text-muted">ไม่พบรายการ (หากสร้าง PO Manual กรุณาพัฒนาระบบ Add Row เพิ่มเติม)</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save-fill"></i> บันทึกใบสั่งซื้อ (PO)
        </button>
    </div>
</form>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>