<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';

// 3. กำหนดฟังก์ชัน hasRole (ถ้ายังไม่มี)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 4. ตรวจสอบสิทธิ์ (ต้องทำก่อน xử lý POST)
if (!hasRole(['WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์ในการสร้างใบขอซื้อ (PR)");
}

// 5. ย้ายการ xử lý POST มาไว้บนสุด
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_pr') {

    $requested_by_user_id = $_SESSION['user_id'];

    // ข้อมูล Header
    $pr_number = $_POST['pr_number'];
    $request_date = $_POST['request_date'];
    $department = $_POST['department']; 
    $reason = $_POST['reason'];

    // ข้อมูล Items
    $material_ids = $_POST['material_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    $conn->begin_transaction();
    try {
        // บันทึก Header
        $stmt_pr = $conn->prepare("INSERT INTO purchase_requisitions (pr_number, requested_by_user_id, request_date, department, reason, status)
                                  VALUES (?, ?, ?, ?, ?, 'Pending WH Approval')");
        $stmt_pr->bind_param("sisss", $pr_number, $requested_by_user_id, $request_date, $department, $reason);
        $stmt_pr->execute();
        $pr_id = $conn->insert_id;
        $stmt_pr->close();

        // บันทึก Items
        $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, material_id, quantity_requested) VALUES (?, ?, ?)");
        
        $item_added = false;
        foreach ($material_ids as $index => $material_id) {
            $quantity = $quantities[$index] ?? 0;
            if ($material_id && $quantity > 0) {
                $stmt_item->bind_param("iid", $pr_id, $material_id, $quantity);
                $stmt_item->execute();
                $item_added = true;
            }
        }
        $stmt_item->close();

        if (!$item_added) {
            throw new Exception("กรุณาเพิ่มรายการวัสดุอย่างน้อย 1 รายการ");
        }
        
        $conn->commit();
        $_SESSION['alert_message'] = "ส่งใบขอซื้อ (PR) เลขที่ $pr_number สำเร็จ! รอดำเนินการอนุมัติ";
        $_SESSION['alert_type'] = "success";
        header("Location: pr_approval_list.php"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header("Location: pr_create.php"); 
        exit();
    }
}

// 6. ดึง Alert Message
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// 7. Include Header
require_once '../includes/header.php';

// --- ดึงข้อมูลสำหรับแสดงฟอร์ม ---
$sql_materials = "SELECT id, item_code, name, unit FROM materials ORDER BY name ASC";
$materials_result = $conn->query($sql_materials);
$material_options_html = "";
if ($materials_result && $materials_result->num_rows > 0) {
    while($row = $materials_result->fetch_assoc()) {
        $material_options_html .= "<option value='{$row['id']}' data-unit='{$row['unit']}'>{$row['item_code']} - {$row['name']}</option>";
    }
}

$next_pr_num_result = $conn->query("SELECT MAX(id) + 1 AS next_id FROM purchase_requisitions");
$next_pr_num = $next_pr_num_result ? $next_pr_num_result->fetch_assoc()['next_id'] : 1;
if (is_null($next_pr_num)) $next_pr_num = 1;
$pr_number_generated = "PR-" . date("Y") . "-" . str_pad($next_pr_num, 5, "0", STR_PAD_LEFT);
?>

<style>
    .page-icon {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #0A84FF, #5AC8FA);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-right: 15px;
    }
    .table th {
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #8E8E93;
        font-weight: 600;
    }
    .form-control-plaintext {
        font-weight: 500;
        color: #1D1D1F;
    }
</style>

<div class="d-flex align-items-center mb-4">
    <i class="bi bi-journal-plus page-icon"></i>
    <div>
        <h2 class="fw-bold mb-0">สร้างใบขอซื้อ (Manual PR)</h2>
        <p class="text-muted mb-0">กรอกรายละเอียดเพื่อขอซื้อวัสดุเข้าระบบ</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="pr_create.php" method="POST" id="pr-form">
    <input type="hidden" name="action" value="create_pr">
    
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom-0 py-3">
            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-file-earmark-text me-2"></i> ข้อมูลเอกสาร (PR Header)</h5>
        </div>
        <div class="card-body pt-0">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">เลขที่ PR</label>
                    <input type="text" class="form-control bg-light fw-bold text-primary" name="pr_number" value="<?php echo $pr_number_generated; ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">วันที่ขอ</label>
                    <input type="date" class="form-control" name="request_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                 <div class="col-md-4">
                    <label class="form-label">แผนก (Department)</label>
                    <input type="text" class="form-control" name="department" value="Warehouse" required>
                </div>
                 <div class="col-12">
                    <label class="form-label">เหตุผลที่ขอซื้อ (Reason)</label>
                    <textarea class="form-control" name="reason" rows="2" placeholder="เช่น สต็อกใกล้หมด, เตรียมสำหรับโปรเจกต์ใหม่..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-list-check me-2"></i> รายการวัสดุที่ขอซื้อ</h5>
            <button type="button" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm" id="add-item-btn">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มรายการ
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="pr-item-table">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 45%; padding-left: 20px;">วัสดุ (Material)</th>
                            <th style="width: 20%;" class="text-center">จำนวนขอซื้อ</th>
                            <th style="width: 20%;" class="text-center">หน่วย</th>
                            <th style="width: 15%;" class="text-center">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="item-list">
                        <tr class="initial-row">
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-basket3 display-4 d-block mb-2 opacity-50"></i>
                                ยังไม่มีรายการ กรุณากดปุ่ม "เพิ่มรายการ"
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">
                <i class="bi bi-send-fill me-2"></i> ส่งขออนุมัติ (PR)
            </button>
        </div>
    </div>
    
</form>

<table style="display: none;">
    <tbody id="item-template">
        <tr>
            <td style="padding-left: 20px;">
                <select name="material_id[]" class="form-select material-select rounded-3" required>
                    <option value="">-- เลือกวัสดุ --</option>
                    <?php echo $material_options_html; ?>
                </select>
            </td>
            <td>
                <input type="number" name="quantity[]" class="form-control text-center rounded-3 fw-bold text-primary" step="0.01" min="0.01" placeholder="0.00" required>
            </td>
            <td>
                <input type="text" class="form-control-plaintext text-center unit-display text-muted" readonly value="-">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-light text-danger btn-sm rounded-circle remove-item-btn shadow-sm border" style="width: 32px; height: 32px; padding: 0;">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </td>
        </tr>
    </tbody>
</table>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const addBtn = document.getElementById("add-item-btn");
    const itemList = document.getElementById("item-list");
    const templateRow = document.getElementById("item-template").firstElementChild.cloneNode(true);
    const initialRow = itemList.querySelector('.initial-row');

    function addRow() {
        // ลบแถว placeholder ถ้ามี
        if (initialRow && initialRow.parentNode === itemList) {
            itemList.removeChild(initialRow);
        }
        
        if (templateRow) {
            const newRow = templateRow.cloneNode(true);
            itemList.appendChild(newRow);
        } else {
            console.error("Template row not found!");
        }
    }
    
    // เพิ่มแถวแรกอัตโนมัติ
    addRow();

    if (addBtn) {
        addBtn.addEventListener("click", addRow);
    }

    // ลบแถว
    itemList.addEventListener("click", function(e) {
        const removeButton = e.target.closest(".remove-item-btn");
        if (removeButton) {
            if (itemList.children.length > 1) {
                removeButton.closest("tr").remove();
            } else if (itemList.children.length === 1 && !initialRow) {
                // ถ้าเป็นแถวสุดท้าย ลบแล้วใส่ placeholder กลับ
                removeButton.closest("tr").remove();
                if(initialRow) itemList.appendChild(initialRow);
            }
        }
    });

    // อัปเดตหน่วยนับ
    itemList.addEventListener("change", function(e) {
        if (e.target && e.target.classList.contains("material-select")) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const unit = selectedOption.getAttribute('data-unit') || '-';
            const unitInput = e.target.closest("tr").querySelector(".unit-display");
            unitInput.value = unit;
        }
    });
});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>