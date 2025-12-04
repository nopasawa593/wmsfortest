<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';

// 3. กำหนดฟังก์ชัน hasRole
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 4. ตรวจสอบสิทธิ์
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to compare prices.");
}

// (ฟังก์ชัน Helper สำหรับอัปโหลดไฟล์)
function handleFileUpload($file_key, $upload_dir, $supplier_id) {
    if (isset($_FILES[$file_key]) && isset($_FILES[$file_key]['error'][$supplier_id]) && $_FILES[$file_key]['error'][$supplier_id] == 0) {
        $filename = basename($_FILES[$file_key]['name'][$supplier_id]);
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'xls', 'xlsx'];

        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception("Error: ไฟล์ชนิด '$file_ext' ไม่ได้รับอนุญาต");
        }
        $new_filename = 'PR' . (int)$_GET['pr_id'] . '_SUP' . $supplier_id . '_' . uniqid() . '.' . $file_ext;
        $target_path_db = $upload_dir . $new_filename;
        $target_path_fs = $_SERVER['DOCUMENT_ROOT'] . $target_path_db;

        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $upload_dir)) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . $upload_dir, 0755, true);
        }
        if (move_uploaded_file($_FILES[$file_key]['tmp_name'][$supplier_id], $target_path_fs)) {
            return $target_path_db;
        } else {
            throw new Exception("Error: ไม่สามารถอัปโหลดไฟล์ใบเสนอราคาได้");
        }
    }
    return $_POST['existing_file'][$supplier_id] ?? null; 
}

// 5. (Logic การบันทึกใบเสนอราคา)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_quotations') {

    $pr_id = (int)$_GET['pr_id'];
    $supplier_ids = $_POST['supplier_id'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $pr_items = $_POST['pr_item'] ?? []; 

    $conn->begin_transaction();
    try {
        // (ลบใบเสนอราคาเก่าของ PR นี้ทิ้งก่อน เพื่อบันทึกใหม่)
        $stmt_find_old = $conn->prepare("SELECT id, quotation_file_path FROM quotation_headers WHERE pr_id = ?");
        $stmt_find_old->bind_param("i", $pr_id);
        $stmt_find_old->execute();
        $old_headers_result = $stmt_find_old->get_result();
        while ($old_header = $old_headers_result->fetch_assoc()) {
            // (Optional: ลบไฟล์เก่า หรือจะเก็บไว้ก็ได้ ในที่นี้ขอไม่ลบไฟล์จริงเพื่อความปลอดภัยของ History)
            // if ($old_header['quotation_file_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $old_header['quotation_file_path'])) { @unlink(...); }
            $conn->query("DELETE FROM quotation_items WHERE quotation_header_id = " . $old_header['id']);
            $conn->query("DELETE FROM quotation_headers WHERE id = " . $old_header['id']);
        }
        $stmt_find_old->close();

        // (วน Loop บันทึก Supplier แต่ละราย)
        foreach ($supplier_ids as $supplier_id) {
            if (empty($supplier_id)) continue; 

            $file_path = handleFileUpload('quotation_file', '/uploads/quotations/', $supplier_id);
            $total_amount = 0;

            // (บังคับแนบไฟล์ตอน Save)
            if (empty($file_path) && empty($_POST['existing_file'][$supplier_id])) {
                $sup_name_result = $conn->query("SELECT name FROM suppliers WHERE id = $supplier_id");
                $sup_name = $sup_name_result ? $sup_name_result->fetch_assoc()['name'] : "ID $supplier_id";
                throw new Exception("กรุณาแนบไฟล์ใบเสนอราคาสำหรับ '$sup_name' ก่อนบันทึก");
            }

            $stmt_header = $conn->prepare("INSERT INTO quotation_headers (pr_id, supplier_id, quotation_file_path, total_amount) VALUES (?, ?, ?, ?)");
            $stmt_header->bind_param("iisd", $pr_id, $supplier_id, $file_path, $total_amount);
            $stmt_header->execute();
            $quotation_header_id = $conn->insert_id;
            $stmt_header->close();

            $stmt_item = $conn->prepare("INSERT INTO quotation_items (quotation_header_id, material_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            foreach ($pr_items as $material_id => $quantity) {
                $price = $unit_prices[$supplier_id][$material_id] ?? 0;
                $stmt_item->bind_param("iidd", $quotation_header_id, $material_id, $quantity, $price);
                $stmt_item->execute();
                $total_amount += ($quantity * $price);
            }
            $stmt_item->close();
            
            $conn->query("UPDATE quotation_headers SET total_amount = $total_amount WHERE id = $quotation_header_id");
        }

        $conn->commit();
        $_SESSION['alert_message'] = "บันทึกข้อมูลใบเสนอราคาสำเร็จ!";
        $_SESSION['alert_type'] = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    header("Location: pr_compare.php?pr_id=" . $pr_id);
    exit();
}
// --- จบส่วน xử lý POST ---

// (ส่วนดึงข้อมูลสำหรับแสดงผล)
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}
require_once '../includes/header.php';

$pr_id = (int)$_GET['pr_id'];

// ดึง PR Header
$pr_header_result = $conn->query("SELECT pr_number, status FROM purchase_requisitions WHERE id = $pr_id");
$pr_header = $pr_header_result->fetch_assoc();
if (!$pr_header || $pr_header['status'] == 'Pending WH Approval') {
    die("ไม่พบ PR หรือ PR ยังไม่ได้รับการอนุมัติ");
}
$pr_number = $pr_header['pr_number'];
$po_created = ($pr_header['status'] == 'PO Created'); 

// ดึง PR Items
$pr_items_sql = "SELECT i.material_id, i.quantity_requested, m.item_code, m.name, m.unit FROM pr_items i JOIN materials m ON i.material_id = m.id WHERE i.pr_id = $pr_id";
$pr_items_result = $conn->query($pr_items_sql);
$pr_items_array = [];
while ($row = $pr_items_result->fetch_assoc()) $pr_items_array[] = $row;

// ดึง Master Suppliers
$suppliers_result = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");
$suppliers_array = [];
while ($row = $suppliers_result->fetch_assoc()) $suppliers_array[] = $row;

// ดึง Saved Quotations
$saved_quotes_sql = "SELECT qh.id, qh.supplier_id, qh.total_amount, qh.quotation_file_path, s.name AS supplier_name FROM quotation_headers qh JOIN suppliers s ON qh.supplier_id = s.id WHERE qh.pr_id = $pr_id ORDER BY qh.id ASC";
$saved_quotes_result = $conn->query($saved_quotes_sql);
$saved_quotes_array = [];
$saved_prices_array = [];
$lowest_full_total = null;

if ($saved_quotes_result && $saved_quotes_result->num_rows > 0) {
    while($quote_header = $saved_quotes_result->fetch_assoc()) {
        $saved_quotes_array[] = $quote_header; 
        if ($lowest_full_total === null || $quote_header['total_amount'] < $lowest_full_total) { $lowest_full_total = $quote_header['total_amount']; }
        
        $items_price_result = $conn->query("SELECT material_id, unit_price FROM quotation_items WHERE quotation_header_id = " . $quote_header['id']);
        while($item_price = $items_price_result->fetch_assoc()) { 
            $saved_prices_array[$quote_header['supplier_id']][$item_price['material_id']] = $item_price['unit_price']; 
        }
    }
}

// (Logic PHP คำนวณ Best Price Total เพื่อใช้แสดงผลเบื้องต้น)
$best_price_totals = [];
foreach ($saved_quotes_array as $quote) $best_price_totals[$quote['supplier_id']] = 0; 

foreach ($pr_items_array as $item) {
    $material_id = $item['material_id'];
    $quantity = (float)$item['quantity_requested'];
    $min_price = null;
    
    // 1. หา Min Price ของ Item นี้
    foreach ($saved_quotes_array as $quote) {
        $supplier_id = $quote['supplier_id'];
        if (isset($saved_prices_array[$supplier_id][$material_id])) {
            $price = (float)$saved_prices_array[$supplier_id][$material_id];
            if ($price > 0 && ($min_price === null || $price < $min_price)) {
                $min_price = $price;
            }
        }
    }
    // 2. บวกยอดให้ Supplier ที่เสนอ Min Price
    if ($min_price !== null) {
        foreach ($saved_quotes_array as $quote) {
            $supplier_id = $quote['supplier_id'];
            if (isset($saved_prices_array[$supplier_id][$material_id])) {
                $price = (float)$saved_prices_array[$supplier_id][$material_id];
                if (abs($price - $min_price) < 0.001) { 
                    $best_price_totals[$supplier_id] += ($price * $quantity);
                }
            }
        }
    }
}
?>

<h1 class="mb-4"><i class="bi bi-bar-chart-fill me-2"></i>เทียบราคาใบขอซื้อ (PR: <?php echo $pr_number; ?>)</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($po_created): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <strong>สำเร็จแล้ว!</strong> PR นี้ถูกนำไปสร้าง PO แล้ว</div>
<?php endif; ?>

<form action="pr_compare.php?pr_id=<?php echo $pr_id; ?>" method="POST" enctype="multipart/form-data" id="compare-form">
    <input type="hidden" name="action" value="save_quotations">
    <?php foreach ($pr_items_array as $item): ?>
        <input type="hidden" name="pr_item[<?php echo $item['material_id']; ?>]" value="<?php echo $item['quantity_requested']; ?>">
    <?php endforeach; ?>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">ตารางเทียบราคา (Quotation Comparison)</h5>
            <?php if (!$po_created): ?>
            <button type="button" class="btn btn-success btn-sm" id="add-supplier-btn">
                <i class="bi bi-plus-circle"></i> เพิ่ม Supplier
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="compare-table">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 250px;">วัสดุ (Material)</th>
                            <th style="width: 100px;">จำนวน (Qty)</th>
                            
                            <?php foreach ($saved_quotes_array as $index => $quote): ?>
                            <th class="text-center supplier-col" style="min-width: 220px;" data-col-index="<?php echo $index; ?>">
                                <div class="d-flex align-items-center">
                                    <select name="supplier_id[]" class="form-select form-select-sm me-2" <?php if ($po_created) echo 'disabled'; ?> required>
                                        <option value="">-- เลือก Supplier <?php echo $index + 1; ?> --</option>
                                        <?php foreach ($suppliers_array as $sup): 
                                            $selected = ($sup['id'] == $quote['supplier_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $sup['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($sup['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!$po_created): ?>
                                    <button type="button" class="btn btn-danger btn-sm remove-supplier-btn p-1" title="ลบคอลัมน์นี้"><i class="bi bi-x-lg"></i></button>
                                    <?php endif; ?>
                                </div>
                            </th>
                            <?php endforeach; ?>
                            
                            <?php if (!$po_created && empty($saved_quotes_array)): ?>
                            <th class="text-center supplier-col" style="min-width: 220px;" data-col-index="0">
                                <div class="d-flex align-items-center">
                                    <select name="supplier_id[]" class="form-select form-select-sm me-2" required>
                                        <option value="">-- เลือก Supplier 1 --</option>
                                        <?php foreach ($suppliers_array as $sup): ?>
                                            <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-danger btn-sm remove-supplier-btn p-1" title="ลบคอลัมน์นี้"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pr_items_array as $item): 
                            $mat_id = $item['material_id'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_code'] . " - " . $item['name']); ?></td>
                                <td class="text-end fw-bold qty-cell">
                                    <?php echo $item['quantity_requested']; ?>
                                    <span class="text-muted small"><?php echo $item['unit']; ?></span>
                                </td>
                                
                                <?php foreach ($saved_quotes_array as $index => $quote):
                                    $sup_id = $quote['supplier_id'];
                                    $price = $saved_prices_array[$sup_id][$mat_id] ?? '0.00';
                                ?>
                                <td class="price-cell">
                                    <input type="number" name="unit_price[<?php echo $sup_id; ?>][<?php echo $mat_id; ?>]" 
                                           class="form-control form-control-sm text-end price-input" 
                                           value="<?php echo $price; ?>" step="0.01" min="0" <?php if ($po_created) echo 'readonly'; ?>>
                                </td>
                                <?php endforeach; ?>
                                
                                <?php if (!$po_created && empty($saved_quotes_array)): ?>
                                <td class="price-cell">
                                    <input type="number" name="unit_price[new_0][<?php echo $mat_id; ?>]" 
                                           class="form-control form-control-sm text-end price-input" 
                                           value="0.00" step="0.01" min="0">
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="text-end fw-bold" colspan="2">แนบใบเสนอราคา (PDF/JPG)</td>
                            <?php foreach ($saved_quotes_array as $index => $quote): 
                                $sup_id = $quote['supplier_id'];
                            ?>
                            <td class="supplier-col">
                                <input type="hidden" name="existing_file[<?php echo $sup_id; ?>]" class="existing-file-input" value="<?php echo $quote['quotation_file_path'] ?? ''; ?>">
                                <div class="d-flex align-items-center">
                                    <input type="file" name="quotation_file[<?php echo $sup_id; ?>]" class="form-control form-control-sm new-file-input" <?php if ($po_created) echo 'disabled'; ?>>
                                    <span class="file-warning text-danger ms-1" style="display:none;" title="กรุณาแนบไฟล์">⚠️</span>
                                </div>
                                <?php if ($quote && $quote['quotation_file_path']): ?>
                                    <a href="<?php echo $quote['quotation_file_path']; ?>" target="_blank" class="btn btn-info btn-sm mt-1 w-100">
                                        <i class="bi bi-file-earmark-arrow-down"></i> ดูไฟล์แนบ
                                    </a>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            
                            <?php if (!$po_created && empty($saved_quotes_array)): ?>
                            <td class="supplier-col">
                                <input type="hidden" name="existing_file[new_0]" class="existing-file-input" value="">
                                <div class="d-flex align-items-center">
                                    <input type="file" name="quotation_file[new_0]" class="form-control form-control-sm new-file-input">
                                    <span class="file-warning text-danger ms-1" style="display:none;" title="กรุณาแนบไฟล์">⚠️</span>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        
                        <tr>
                            <td class="text-end fw-bold" colspan="2">
                                <i class="bi bi-calculator-fill me-2"></i> ราคารวม (เฉพาะรายการที่ถูกที่สุด)
                            </td>
                            <?php 
                            $min_best_total = null;
                            if (!empty($best_price_totals)) {
                                $filtered_totals = array_filter($best_price_totals, fn($v) => $v > 0);
                                if (!empty($filtered_totals)) {
                                    $min_best_total = min($filtered_totals);
                                }
                            }
                            ?>
                            <?php foreach ($saved_quotes_array as $index => $quote): 
                                $sup_id = $quote['supplier_id'];
                                $total = $best_price_totals[$sup_id] ?? 0;
                                $is_lowest = ($total > 0 && abs($total - $min_best_total) < 0.001);
                            ?>
                            <td class="text-end fs-5 fw-bold total-cell <?php echo $is_lowest ? 'table-success' : ''; ?>">
                                <?php echo number_format($total, 2); ?>
                            </td>
                            <?php endforeach; ?>
                            
                             <?php if (!$po_created && empty($saved_quotes_array)): ?>
                             <td class="text-end fs-5 fw-bold total-cell">0.00</td>
                             <?php endif; ?>
                        </tr>
                        
                        <?php if (!$po_created): ?>
                        <tr>
                            <td class="text-end" colspan="2"></td>
                            <?php foreach ($saved_quotes_array as $index => $quote): 
                                $sup_id = $quote['supplier_id'];
                                // (ใช้ $total_amount จาก DB จริง เพื่อเปรียบเทียบ "ราคารวมทั้งหมด")
                                $total_all = $quote['total_amount'] ?? 0;
                                $is_lowest_all = ($saved_quotes_result->num_rows > 0 && $total_all > 0 && $total_all == $lowest_full_total);
                            ?>
                            <td class="text-center supplier-col">
                                <a href="pr_select_supplier_process.php?pr_id=<?php echo $pr_id; ?>&supplier_id=<?php echo $sup_id; ?>" 
                                   class="btn <?php echo $is_lowest_all ? 'btn-outline-success' : 'btn-secondary'; ?> w-100 action-button select-supplier-btn"
                                   onclick="return confirm('คุณแน่ใจหรือไม่ที่จะเลือก Supplier นี้ (สำหรับสินค้าทุกรายการ) เพื่อส่งอนุมัติ PO?')">
                                    <i class="bi bi-check-circle-fill"></i> เลือก Supplier นี้ (ทั้งหมด)
                                </a>
                            </td>
                            <?php endforeach; ?>
                            
                             <?php if (!$po_created && empty($saved_quotes_array)): ?>
                             <td class="text-center supplier-col"></td>
                             <?php endif; ?>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <?php if (!$po_created): ?>
    <div class="text-center mt-4 d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-info btn-lg" id="calculate-btn">
             <i class="bi bi-calculator"></i> คำนวณราคาต่ำสุด
        </button>
        
        <a href="pr_create_po_best_price.php?pr_id=<?php echo $pr_id; ?>" 
           class="btn btn-success btn-lg action-button" 
           id="best-price-btn"
           onclick="return confirm('ยืนยันการสร้าง PO (หลายฉบับ) ตามราคาที่ดีที่สุดของแต่ละรายการ?')">
            <i class="bi bi-lightning-fill"></i> สร้าง PO (ตามราคาดีที่สุด)
        </a>
        
        <button type="submit" class="btn btn-primary btn-lg action-button" id="save-data-btn">
            <i class="bi bi-save-fill"></i> บันทึกข้อมูล
        </button>
    </div>
    <?php endif; ?>
</form>


<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const calculateBtn = document.getElementById('calculate-btn');
    const addSupplierBtn = document.getElementById('add-supplier-btn');
    const table = document.getElementById('compare-table');
    const theadRow = table.querySelector('thead tr');
    const tbody = table.querySelector('tbody');
    const tfootFileRow = table.querySelector('tfoot tr:nth-child(1)');
    const tfootTotalRow = table.querySelector('tfoot tr:nth-child(2)');
    const tfootActionRow = table.querySelector('tfoot tr:nth-child(3)');
    
    const saveBtn = document.getElementById('save-data-btn');
    const bestPriceBtn = document.getElementById('best-price-btn');
    const selectSupplierBtns = document.querySelectorAll('.select-supplier-btn'); 

    const supplierOptions = <?php echo json_encode($suppliers_array); ?>;
    const prItems = <?php echo json_encode($pr_items_array); ?>;

    // Flag ตรวจสอบการแก้ไข
    let isFormDirty = false;

    // ⭐️ ฟังก์ชันตั้งค่าเมื่อมีการแก้ไข (Dirty State) ⭐️
    function setDirtyState() {
        if (!isFormDirty) {
            isFormDirty = true;
            
            // 1. ปิดปุ่ม "สร้าง PO" และ "เลือก Supplier" ทันทีที่เริ่มแก้ไข
            if (bestPriceBtn) {
                disablePoButton(bestPriceBtn);
            }
            selectSupplierBtns.forEach(btn => {
                disablePoButton(btn);
            });

            // 2. เปลี่ยนปุ่ม Save ให้เป็นสีเหลือง (แจ้งเตือนให้กด)
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="bi bi-save-fill"></i> บันทึกการเปลี่ยนแปลงเดี๋ยวนี้';
                saveBtn.classList.add('btn-warning');
                saveBtn.classList.remove('btn-primary');
            }
        }
        // ⭐️ เรียกตรวจสอบปุ่ม Save ใหม่ทุกครั้งที่มีการแก้ไข ⭐️
        checkButtonStates();
    }
    
    // Helper สำหรับปิดปุ่ม PO
    function disablePoButton(btn) {
        btn.classList.add('disabled');
        btn.removeAttribute('href');
        btn.style.pointerEvents = 'none';
        btn.innerHTML = '<i class="bi bi-exclamation-circle"></i> กรุณาบันทึกข้อมูลก่อน';
        btn.classList.remove('btn-success', 'btn-outline-success', 'btn-secondary');
        btn.classList.add('btn-secondary');
    }

    // จับ Event การแก้ไข
    const formInputs = document.querySelectorAll('#compare-form input, #compare-form select');
    formInputs.forEach(input => {
        input.addEventListener('change', setDirtyState);
        input.addEventListener('input', setDirtyState); 
    });

    function createSupplierSelect(colIndex) {
        let select = document.createElement('select');
        select.name = `supplier_id[]`;
        select.className = 'form-select form-select-sm me-2';
        select.required = true;
        let defaultOption = document.createElement('option');
        defaultOption.value = "";
        defaultOption.textContent = `-- เลือก Supplier ${colIndex + 1} --`;
        select.appendChild(defaultOption);
        supplierOptions.forEach(sup => {
            let option = document.createElement('option');
            option.value = sup.id;
            option.textContent = sup.name;
            select.appendChild(option);
        });
        select.addEventListener('change', setDirtyState);
        return select;
    }

    function updateInputNames() {
        const supplierDropdowns = table.querySelectorAll('thead select[name="supplier_id[]"]');
        supplierDropdowns.forEach((select, colIndex) => {
            const supplierId = select.value || `new_${colIndex}`;
            tbody.querySelectorAll('tr').forEach((row, rowIndex) => {
                const priceInput = row.querySelectorAll('.price-cell')[colIndex].querySelector('input');
                const materialId = prItems[rowIndex].material_id;
                priceInput.name = `unit_price[${supplierId}][${materialId}]`;
                priceInput.removeEventListener('input', setDirtyState); 
                priceInput.addEventListener('input', setDirtyState);
            });
            const fileCell = tfootFileRow.querySelectorAll('.supplier-col')[colIndex];
            const fileInput = fileCell.querySelector('input[type="file"]');
            fileInput.name = `quotation_file[${supplierId}]`;
            fileCell.querySelector('input[type="hidden"]').name = `existing_file[${supplierId}]`;
            
             fileInput.removeEventListener('change', setDirtyState);
             fileInput.addEventListener('change', setDirtyState);
        });
    }
    
    // ⭐️ ฟังก์ชันตรวจสอบสถานะปุ่ม (แก้ไขใหม่) ⭐️
    function checkButtonStates() {
        // ❌ ลบบรรทัดนี้ออก: if (isFormDirty) return; 
        // เราต้องให้มันทำงานต่อเพื่อเช็คว่าไฟล์แนบครบหรือยัง จะได้เปิดปุ่ม Save ได้

        const fileColumns = tfootFileRow.querySelectorAll('.supplier-col');
        let allFilesAttached = true; 
        let allDataIsSaved = true; 
        
        if (fileColumns.length === 0) {
            allFilesAttached = false;
            allDataIsSaved = false;
        }

        fileColumns.forEach(col => {
            const fileInput = col.querySelector('.new-file-input');
            const hiddenInput = col.querySelector('.existing-file-input');
            const warningSpan = col.querySelector('.file-warning'); 

            const newFileSelected = fileInput && fileInput.files.length > 0;
            const existingFile = hiddenInput && hiddenInput.value.trim() !== '';

            // ถ้าไม่มีไฟล์ใหม่ และ ไม่มีไฟล์เก่า = ยังไม่แนบ
            if (!newFileSelected && !existingFile) {
                allFilesAttached = false;
                if (warningSpan) warningSpan.style.display = 'inline'; 
            } else {
                if (warningSpan) warningSpan.style.display = 'none'; 
            }
            
            // ถ้าไม่มีไฟล์เก่า (คือเพิ่งเลือกไฟล์ใหม่ หรือยังไม่ได้เลือก) ถือว่ายังไม่ได้ Save ลง DB
            if (!existingFile) {
                allDataIsSaved = false;
            }
        });

        // 1. ควบคุมปุ่ม Save: เปิดใช้งานได้ถ้าแนบไฟล์ครบ (ไม่สน Dirty)
        if (saveBtn) {
            saveBtn.disabled = !allFilesAttached;
        }

        // 2. ควบคุมปุ่ม PO: ต้องแนบไฟล์ครบ + เคย Save แล้ว + ห้าม Dirty
        if (bestPriceBtn) {
            // ถ้า Dirty หรือ ข้อมูลยังไม่เคยถูก Save ลง DB -> ปิดปุ่ม PO
            if (isFormDirty || !allDataIsSaved) {
                disablePoButton(bestPriceBtn);
            } 
            // (ถ้าไม่ Dirty และ Saved แล้ว ไม่ต้องทำอะไร ปล่อยให้เป็นสถานะปกติ)
        }
        
        selectSupplierBtns.forEach(btn => {
             if (isFormDirty || !allDataIsSaved) {
                disablePoButton(btn);
            }
        });
    }

    // Event Listeners
    table.addEventListener('change', function(e) {
        if (e.target && e.target.tagName === 'SELECT' && e.target.name === 'supplier_id[]') {
            updateInputNames();
            checkButtonStates();
        }
        if (e.target && e.target.classList.contains('new-file-input')) {
            checkButtonStates();
        }
    });
    
    if (calculateBtn) {
        calculateBtn.addEventListener('click', function() {
            updateInputNames(); 
            const rows = tbody.querySelectorAll('tr');
            const totalCells = tfootTotalRow.querySelectorAll('.total-cell');
            let totals = new Array(totalCells.length).fill(0);

            rows.forEach(row => {
                const qtyCell = row.querySelector('.qty-cell');
                const quantity = parseFloat(qtyCell.textContent.trim()) || 0;
                const priceCells = row.querySelectorAll('.price-cell');
                let minPrice = Infinity;
                
                priceCells.forEach(cell => {
                    const price = parseFloat(cell.querySelector('.price-input').value) || 0;
                    if (price > 0 && price < minPrice) {
                        minPrice = price;
                    }
                });

                priceCells.forEach((cell, colIndex) => {
                    const price = parseFloat(cell.querySelector('.price-input').value) || 0;
                    cell.classList.remove('table-success'); 
                    
                    if (price > 0 && Math.abs(price - minPrice) < 0.001) { 
                        cell.classList.add('table-success');
                        totals[colIndex] += (quantity * price);
                    }
                });
            });

            let minTotal = Infinity;
            totals.forEach((total, i) => {
                if (total > 0 && total < minTotal) {
                    minTotal = total;
                }
            });

            totalCells.forEach((cell, i) => {
                const total = totals[i];
                cell.textContent = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                cell.classList.remove('table-success'); 
                if (total > 0 && Math.abs(total - minTotal) < 0.001) {
                    cell.classList.add('table-success'); 
                }
            });
        });
    }
    
    if (addSupplierBtn) {
        addSupplierBtn.addEventListener('click', function() {
            setDirtyState(); 
            const colIndex = table.querySelectorAll('thead .supplier-col').length;
            
            const newTh = document.createElement('th');
            newTh.className = 'text-center supplier-col'; newTh.style.minWidth = '220px'; newTh.dataset.colIndex = colIndex;
            const headerDiv = document.createElement('div');
            headerDiv.className = 'd-flex align-items-center';
            headerDiv.appendChild(createSupplierSelect(colIndex));
            headerDiv.innerHTML += ` <button type="button" class="btn btn-danger btn-sm remove-supplier-btn p-1" title="ลบคอลัมน์นี้"><i class="bi bi-x-lg"></i></button>`;
            newTh.appendChild(headerDiv);
            theadRow.appendChild(newTh);

            tbody.querySelectorAll('tr').forEach((row, rowIndex) => {
                const mat_id = prItems[rowIndex].material_id;
                const newTd = document.createElement('td');
                newTd.className = 'price-cell';
                const newInput = document.createElement('input');
                newInput.type = 'number';
                newInput.name = `unit_price[new_${colIndex}][${mat_id}]`;
                newInput.className = 'form-control form-control-sm text-end price-input';
                newInput.value = '0.00';
                newInput.step = '0.01';
                newInput.min = '0';
                newInput.addEventListener('input', setDirtyState);
                newTd.appendChild(newInput);
                row.appendChild(newTd);
            });
            
            const newFileTd = document.createElement('td');
            newFileTd.className = 'supplier-col';
            newFileTd.innerHTML = `<input type="hidden" name="existing_file[new_${colIndex}]" class="existing-file-input" value="">
                                 <div class="d-flex align-items-center">
                                     <input type="file" name="quotation_file[new_${colIndex}]" class="form-control form-control-sm new-file-input">
                                     <span class="file-warning text-danger ms-1" style="display:none;" title="กรุณาแนบไฟล์">⚠️</span>
                                 </div>`;
            const newFileInput = newFileTd.querySelector('.new-file-input');
            newFileInput.addEventListener('change', setDirtyState);
            tfootFileRow.appendChild(newFileTd);
            
            const newTotalTd = document.createElement('td');
            newTotalTd.className = 'text-end fs-5 fw-bold total-cell';
            newTotalTd.textContent = '0.00';
            tfootTotalRow.appendChild(newTotalTd);
            
            const newActionTd = document.createElement('td');
            newActionTd.className = 'text-center supplier-col';
            newActionTd.innerHTML = ``;
            tfootActionRow.appendChild(newActionTd);
            
            updateInputNames(); 
            checkButtonStates();
        });
    }

    table.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-supplier-btn');
        if (!removeBtn) return; 

        setDirtyState();

        const allSupplierCols = table.querySelectorAll('thead .supplier-col');
        if (allSupplierCols.length <= 1) {
            alert("ต้องมีอย่างน้อย 1 Supplier เพื่อทำการเทียบราคา");
            return;
        }

        const thToRemove = removeBtn.closest('th.supplier-col');
        const relativeIndex = Array.from(allSupplierCols).indexOf(thToRemove);

        thToRemove.remove();
        tbody.querySelectorAll('tr').forEach(row => {
            row.querySelectorAll('.price-cell')[relativeIndex].remove();
        });
        tfootFileRow.querySelectorAll('.supplier-col')[relativeIndex].remove();
        tfootTotalRow.querySelectorAll('.total-cell')[relativeIndex].remove();
        tfootActionRow.querySelectorAll('.supplier-col')[relativeIndex].remove();

        if(calculateBtn) calculateBtn.click();
        updateInputNames();
        checkButtonStates();
    });

    updateInputNames();
    checkButtonStates();
});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>