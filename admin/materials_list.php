<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';

// 2. Helper Function
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 3. ตรวจสอบสิทธิ์
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to manage materials.");
}

// 4. ฟังก์ชันอัปโหลดไฟล์
function handleFileUpload($file_key, $upload_dir, $existing_path = null, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] != 0) {
        return $existing_path;
    }
    $file = $_FILES[$file_key];
    $filename = basename($file['name']);
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception("Error: ไฟล์ชนิด '$file_ext' ไม่ได้รับอนุญาต");
    }

    $new_filename = uniqid('file_', true) . '.' . $file_ext;
    $target_path_db = $upload_dir . $new_filename;
    $target_path_fs = $_SERVER['DOCUMENT_ROOT'] . $target_path_db;

    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $upload_dir)) {
        mkdir($_SERVER['DOCUMENT_ROOT'] . $upload_dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $target_path_fs)) {
        if ($existing_path && file_exists($_SERVER['DOCUMENT_ROOT'] . $existing_path)) {
            @unlink($_SERVER['DOCUMENT_ROOT'] . $existing_path);
        }
        return $target_path_db;
    } else {
        throw new Exception("Error: ไม่สามารถย้ายไฟล์อัปโหลดได้");
    }
}

// 5. Logic จัดการข้อมูล
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action == 'add') {
            // --- ADD ---
            $item_code = $_POST['item_code'];
            $name = $_POST['name'];
            $description = $_POST['description'];
            $unit = $_POST['unit'];
            $min = $_POST['min_stock_level'];
            $max = $_POST['max_stock_level'];
            $def_loc = !empty($_POST['default_location_id']) ? $_POST['default_location_id'] : NULL;
            $status = $_POST['status'];
            $cat_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL;
            $sup_id = !empty($_POST['preferred_supplier_id']) ? $_POST['preferred_supplier_id'] : NULL;
            $lead = $_POST['lead_time_days'];
            
            // ⭐️ รับค่า Serial Tracking (0 หรือ 1)
            $is_serial = isset($_POST['is_serial_tracking']) ? (int)$_POST['is_serial_tracking'] : 0;

            $img = handleFileUpload('image_file', '/uploads/materials/images/', null, ['jpg', 'jpeg', 'png', 'gif']);
            $dwg = handleFileUpload('drawing_file', '/uploads/materials/drawings/', null, ['pdf', 'dwg', 'dxf', 'jpg', 'png', 'zip', 'rar']);

            // ⭐️ เพิ่ม is_serial_tracking ใน SQL
            $stmt = $conn->prepare("INSERT INTO materials (item_code, name, description, unit, min_stock_level, max_stock_level, default_location_id, image_path, drawing_file_path, status, category_id, preferred_supplier_id, lead_time_days, is_serial_tracking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssddisssiiii", $item_code, $name, $description, $unit, $min, $max, $def_loc, $img, $dwg, $status, $cat_id, $sup_id, $lead, $is_serial);
            $stmt->execute();
            
            $_SESSION['alert_message'] = "เพิ่มวัสดุ ($item_code) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();

        } elseif ($action == 'edit') {
            // --- EDIT ---
            $id = $_POST['material_id'];
            $item_code = $_POST['item_code'];
            $name = $_POST['name'];
            $description = $_POST['description'];
            $unit = $_POST['unit'];
            $min = $_POST['min_stock_level'];
            $max = $_POST['max_stock_level'];
            $def_loc = !empty($_POST['default_location_id']) ? $_POST['default_location_id'] : NULL;
            $status = $_POST['status'];
            $cat_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL;
            $sup_id = !empty($_POST['preferred_supplier_id']) ? $_POST['preferred_supplier_id'] : NULL;
            $lead = $_POST['lead_time_days'];
            
            // ⭐️ รับค่า Serial Tracking
            $is_serial = isset($_POST['is_serial_tracking']) ? (int)$_POST['is_serial_tracking'] : 0;

            $img = handleFileUpload('image_file', '/uploads/materials/images/', $_POST['existing_image_path'], ['jpg', 'jpeg', 'png', 'gif']);
            $dwg = handleFileUpload('drawing_file', '/uploads/materials/drawings/', $_POST['existing_drawing_path'], ['pdf', 'dwg', 'dxf', 'jpg', 'png', 'zip', 'rar']);

            // ⭐️ อัปเดต is_serial_tracking
            $stmt = $conn->prepare("UPDATE materials SET item_code=?, name=?, description=?, unit=?, min_stock_level=?, max_stock_level=?, default_location_id=?, image_path=?, drawing_file_path=?, status=?, category_id=?, preferred_supplier_id=?, lead_time_days=?, is_serial_tracking=? WHERE id=?");
            $stmt->bind_param("ssssddisssiiiii", $item_code, $name, $description, $unit, $min, $max, $def_loc, $img, $dwg, $status, $cat_id, $sup_id, $lead, $is_serial, $id);
            $stmt->execute();
            
            $_SESSION['alert_message'] = "อัปเดตข้อมูลวัสดุ ($item_code) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();

        } elseif ($action == 'delete_material') {
            // --- DELETE ---
            $id = $_POST['material_id_to_delete'];
            $code = $_POST['item_code_to_delete'];

            $check = $conn->query("SELECT SUM(quantity) as qty FROM inventory WHERE material_id=$id")->fetch_assoc();
            if ($check['qty'] > 0) {
                throw new Exception("ไม่สามารถลบได้: ยังมีสต็อกคงเหลือ ({$check['qty']})");
            }

            $files = $conn->query("SELECT image_path, drawing_file_path FROM materials WHERE id=$id")->fetch_assoc();
            if ($files['image_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $files['image_path'])) @unlink($_SERVER['DOCUMENT_ROOT'] . $files['image_path']);
            if ($files['drawing_file_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $files['drawing_file_path'])) @unlink($_SERVER['DOCUMENT_ROOT'] . $files['drawing_file_path']);

            $conn->query("DELETE FROM materials WHERE id=$id");
            $_SESSION['alert_message'] = "ลบวัสดุ ($code) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
        }

    } catch (Exception $e) {
        if ($conn->errno == 1062) { 
            $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: รหัสวัสดุซ้ำกันในระบบ";
        } elseif (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            $_SESSION['alert_message'] = "ลบไม่ได้: วัสดุนี้ถูกใช้งานในเอกสารอื่นแล้ว";
        } else {
            $_SESSION['alert_message'] = "Error: " . $e->getMessage();
        }
        $_SESSION['alert_type'] = "danger";
    }

    header("Location: materials_list.php");
    exit();
}

// 6. แสดงผลหน้าเว็บ
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) { 
    $message = $_SESSION['alert_message']; 
    $message_type = $_SESSION['alert_type']; 
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']); 
}

require_once '../includes/header.php';

// ดึงข้อมูล (เพิ่ม is_serial_tracking)
$sql_materials = "SELECT m.*, l.location_code, c.name AS category_name 
                  FROM materials m 
                  LEFT JOIN locations l ON m.default_location_id = l.id 
                  LEFT JOIN material_categories c ON m.category_id = c.id 
                  ORDER BY m.item_code ASC";
$materials_result = $conn->query($sql_materials);

$locations_result = $conn->query("SELECT id, location_code FROM locations ORDER BY location_code ASC");
$categories_result = $conn->query("SELECT id, name FROM material_categories ORDER BY name ASC");
$suppliers_result = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");
?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0 fw-bold"><i class="bi bi-wrench me-2 text-primary"></i>จัดการข้อมูลวัสดุ (Material Master)</h1>
    <button type="button" class="btn btn-primary btn-lg rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#materialModal" id="add-material-btn">
        <i class="bi bi-plus-lg me-1"></i> เพิ่มวัสดุใหม่
    </button>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="mb-0 text-secondary fw-bold">รายการวัสดุทั้งหมด</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase small text-secondary">
                    <tr>
                        <th class="text-center" style="width:100px;">Barcode</th>
                        <th class="ps-3">รหัสวัสดุ</th>
                        <th>ชื่อวัสดุ</th>
                        <th>หมวดหมู่</th>
                        <th class="text-center">Serial</th> <th class="text-end">Min</th>
                        <th class="text-end">Max</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center pe-3">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($materials_result && $materials_result->num_rows > 0): ?>
                        <?php while($row = $materials_result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center py-2">
                                    <svg class="barcode-small"
                                         data-format="CODE128"
                                         data-value="<?php echo $row['item_code']; ?>"
                                         data-display-value="false"
                                         data-height="25"
                                         data-width="1"
                                         data-margin="0"></svg>
                                </td>
                                <td class="ps-3 fw-bold text-primary"><?php echo htmlspecialchars($row['item_code']); ?></td>
                                <td>
                                    <?php if ($row['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="img" class="rounded shadow-sm me-2" style="width: 35px; height: 35px; object-fit: cover;">
                                    <?php endif; ?>
                                    <span class="fw-medium"><?php echo htmlspecialchars($row['name']); ?></span>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['category_name'] ?? '-'); ?></span></td>
                                
                                <td class="text-center">
                                    <?php if($row['is_serial_tracking']): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-upc-scan"></i> Yes</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end text-muted"><?php echo number_format($row['min_stock_level'], 2); ?></td>
                                <td class="text-end text-muted"><?php echo number_format($row['max_stock_level'], 2); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $bg = 'bg-secondary';
                                    if ($row['status'] == 'Active') $bg = 'bg-success';
                                    if ($row['status'] == 'Obsolete') $bg = 'bg-danger';
                                    echo "<span class='badge {$bg} bg-opacity-75 rounded-pill px-3'>{$row['status']}</span>";
                                    ?>
                                </td>
                                <td class="text-center pe-3">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-light btn-sm border shadow-sm text-warning edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#materialModal"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($row['item_code']); ?>"
                                                data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                data-unit="<?php echo htmlspecialchars($row['unit']); ?>"
                                                data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                                                data-min_stock="<?php echo $row['min_stock_level']; ?>"
                                                data-max_stock="<?php echo $row['max_stock_level']; ?>"
                                                data-default_loc="<?php echo $row['default_location_id']; ?>"
                                                data-status="<?php echo $row['status']; ?>"
                                                data-category_id="<?php echo $row['category_id']; ?>"
                                                data-supplier_id="<?php echo $row['preferred_supplier_id']; ?>"
                                                data-lead_time="<?php echo $row['lead_time_days']; ?>"
                                                data-is_serial="<?php echo $row['is_serial_tracking']; ?>" 
                                                data-image_path="<?php echo $row['image_path']; ?>"
                                                data-drawing_path="<?php echo $row['drawing_file_path']; ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        
                                        <form action="materials_list.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบวัสดุนี้? \n(ลบได้เฉพาะเมื่อไม่มีสต็อกและประวัติ)');">
                                            <input type="hidden" name="action" value="delete_material">
                                            <input type="hidden" name="material_id_to_delete" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="item_code_to_delete" value="<?php echo htmlspecialchars($row['item_code']); ?>">
                                            <button type="submit" class="btn btn-light btn-sm border shadow-sm text-danger ms-1"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-5">ยังไม่มีข้อมูลวัสดุ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="materialModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="materials_list.php" method="POST" id="material-form" enctype="multipart/form-data">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary" id="modalTitle">เพิ่มข้อมูลวัสดุ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body pt-4">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="material_id" id="form-material-id" value="0">
                    <input type="hidden" name="existing_image_path" id="form-existing-image-path">
                    <input type="hidden" name="existing_drawing_path" id="form-existing-drawing-path">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">รหัสวัสดุ (Item Code)</label>
                            <input type="text" class="form-control" id="form-item-code" name="item_code" required placeholder="เช่น MAT-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ชื่อวัสดุ (Name)</label>
                            <input type="text" class="form-control" id="form-name" name="name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">หมวดหมู่</label>
                            <select id="form-category" name="category_id" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php $categories_result->data_seek(0); while($c=$categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">สถานะ</label>
                            <select id="form-status" name="status" class="form-select">
                                <option value="Active">Active (ใช้งาน)</option>
                                <option value="InActive">InActive (ระงับ)</option>
                                <option value="Obsolete">Obsolete (เลิกผลิต)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">หน่วยนับ</label>
                            <input type="text" class="form-control" id="form-unit" name="unit" required placeholder="เช่น ชิ้น, กล่อง">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Min Stock</label>
                            <input type="number" class="form-control" id="form-min-stock" name="min_stock_level" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Max Stock</label>
                            <input type="number" class="form-control" id="form-max-stock" name="max_stock_level" step="0.01">
                        </div>

                        <div class="col-md-12">
                            <div class="card bg-light border-0 p-3">
                                <label class="form-label fw-bold text-dark mb-2"><i class="bi bi-upc-scan me-2"></i> การคุม Serial (Serial Tracking)</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="is_serial_tracking" id="serial_no" value="0" checked>
                                        <label class="form-check-label" for="serial_no">ไม่คุม Serial (ใช้ Batch/Lot หรือไม่ระบุ)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="is_serial_tracking" id="serial_yes" value="1">
                                        <label class="form-check-label fw-bold text-primary" for="serial_yes">คุม Serial Number (ระบุทีละชิ้น)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">ผู้ขายหลัก</label>
                            <select id="form-supplier" name="preferred_supplier_id" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php $suppliers_result->data_seek(0); while($s=$suppliers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                         <div class="col-md-6">
                            <label class="form-label fw-bold">Lead Time (วัน)</label>
                            <input type="number" class="form-control" id="form-lead-time" name="lead_time_days" value="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">รูปภาพ (Image)</label>
                            <input type="file" class="form-control" id="form-image" name="image_file" accept="image/png, image/jpeg, image/gif">
                            <div id="image-preview" class="mt-2 text-center"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ไฟล์ Drawing</label>
                            <input type="file" class="form-control" id="form-drawing" name="drawing_file" accept=".pdf,.dwg,.dxf,.zip,.rar,.jpg,.png">
                            <div id="drawing-preview" class="mt-2 text-center"></div>
                        </div>

                        <div class="col-12">
                             <label class="form-label fw-bold">รายละเอียดเพิ่มเติม</label>
                             <textarea class="form-control" id="form-desc" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                             <label class="form-label fw-bold">ที่เก็บเริ่มต้น (Default Location)</label>
                             <select id="form-default-location" name="default_location_id" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php $locations_result->data_seek(0); while($l=$locations_result->fetch_assoc()): ?>
                                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['location_code']); ?></option>
                                <?php endwhile; ?>
                             </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 pe-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    JsBarcode(".barcode-small").init();

    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('material-form');
    const actionInput = document.getElementById('form-action');
    const idInput = document.getElementById('form-material-id');
    const codeInput = document.getElementById('form-item-code');
    const nameInput = document.getElementById('form-name');
    const unitInput = document.getElementById('form-unit');
    const minInput = document.getElementById('form-min-stock');
    const maxInput = document.getElementById('form-max-stock');
    const descInput = document.getElementById('form-desc');
    const defLocInput = document.getElementById('form-default-location');
    const statusInput = document.getElementById('form-status');
    const catInput = document.getElementById('form-category');
    const supInput = document.getElementById('form-supplier');
    const leadInput = document.getElementById('form-lead-time');
    
    // ⭐️ Radio Button สำหรับ Serial Tracking
    const serialYes = document.getElementById('serial_yes');
    const serialNo = document.getElementById('serial_no');

    const imgPreview = document.getElementById('image-preview');
    const dwgPreview = document.getElementById('drawing-preview');
    const existImgInput = document.getElementById('form-existing-image-path');
    const existDwgInput = document.getElementById('form-existing-drawing-path');

    document.getElementById('add-material-btn').addEventListener('click', function() {
        form.reset();
        modalTitle.textContent = "เพิ่มวัสดุใหม่";
        actionInput.value = "add";
        idInput.value = "0";
        codeInput.readOnly = false;
        
        serialNo.checked = true; // Default No
        
        imgPreview.innerHTML = "";
        dwgPreview.innerHTML = "";
        existImgInput.value = "";
        existDwgInput.value = "";
        statusInput.value = "Active";
    });

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;

            form.reset();
            modalTitle.textContent = "แก้ไขวัสดุ: " + data.code;
            actionInput.value = "edit";

            idInput.value = data.id;
            codeInput.value = data.code; codeInput.readOnly = true;
            nameInput.value = data.name;
            unitInput.value = data.unit;
            minInput.value = data.min_stock;
            maxInput.value = data.max_stock;
            descInput.value = data.desc;
            defLocInput.value = data.default_loc;
            statusInput.value = data.status;
            catInput.value = data.category_id;
            supInput.value = data.supplier_id;
            leadInput.value = data.lead_time;
            
            // ⭐️ Set Radio Button Value
            if (data.is_serial == '1') {
                serialYes.checked = true;
            } else {
                serialNo.checked = true;
            }
            
            existImgInput.value = data.image_path;
            imgPreview.innerHTML = data.image_path ? `<img src="${data.image_path}" class="rounded shadow-sm" style="max-height:100px;">` : '<span class="text-muted small">ไม่มีรูปภาพ</span>';
            
            existDwgInput.value = data.drawing_path;
            dwgPreview.innerHTML = data.drawing_path ? `<a href="${data.drawing_path}" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark-pdf"></i> ดูไฟล์เดิม</a>` : '<span class="text-muted small">ไม่มีไฟล์</span>';
        });
    });
});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>