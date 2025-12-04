<?php 
require_once '../includes/header.php'; 
// (header.php จะเช็ค Login ให้อัตโนมัติ)

// ⭐️ แก้ไข Role: (ADMIN จะผ่านอัตโนมัติ)
if (!hasRole(['WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์ในการสร้างใบขอซื้อ (PR)");
}

// (ดึงข้อมูลสำหรับ Dropdown)
$sql_materials = "SELECT id, item_code, name, unit FROM materials ORDER BY name ASC";
$materials_result = $conn->query($sql_materials);
$material_options_html = "";
while($row = $materials_result->fetch_assoc()) {
    $material_options_html .= "<option value='{$row['id']}' data-unit='{$row['unit']}'>{$row['item_code']} - {$row['name']}</option>";
}

// (สร้างเลข PR อัตโนมัติ)
$next_pr_num_result = $conn->query("SELECT MAX(id) + 1 AS next_id FROM purchase_requisitions");
$next_pr_num = $next_pr_num_result->fetch_assoc()['next_id'];
if (is_null($next_pr_num)) $next_pr_num = 1;
$pr_number_generated = "PR-" . date("Y") . "-" . str_pad($next_pr_num, 5, "0", STR_PAD_LEFT);
?>

<h1 class="mb-4">สร้างใบขอซื้อ (Purchase Requisition)</h1>

<form action="pr_process.php" method="POST" id="pr-form">
    
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">ข้อมูลหลัก (PR Header)</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">เลขที่ PR</label>
                    <input type="text" class="form-control bg-light" name="pr_number" value="<?php echo $pr_number_generated; ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">วันที่ขอ</label>
                    <input type="date" class="form-control" name="request_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                 <div class="col-md-4">
                    <label class="form-label">แผนก (Department)</label>
                    <input type="text" class="form-control" name="department" required>
                </div>
                 <div class="col-12">
                    <label class="form-label">เหตุผลที่ขอซื้อ (Reason)</label>
                    <textarea class="form-control" name="reason" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">รายการวัสดุที่ขอซื้อ</h5>
            <button type="button" class="btn btn-success btn-sm" id="add-item-btn">
                <i class="bi bi-plus-circle"></i> เพิ่มรายการ
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="pr-item-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">วัสดุ</th>
                            <th style="width: 20%;">จำนวนขอซื้อ</th>
                            <th style="width: 20%;">หน่วย</th>
                            <th style="width: 10%;">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="item-list">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-send-fill"></i> ส่งขออนุมัติ (PR)
        </button>
    </div>
</form>

<table style="display: none;">
    <tbody id="item-template">
        <tr>
            <td>
                <select name="material_id[]" class="form-select material-select" required>
                    <option value="">-- เลือกวัสดุ --</option>
                    <?php echo $material_options_html; ?>
                </select>
            </td>
            <td>
                <input type="number" name="quantity[]" class="form-control" step="0.01" min="0.01" required>
            </td>
            <td>
                <input type="text" class="form-control-plaintext unit-display" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-item-btn">
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

    function addRow() {
        const newRow = templateRow.cloneNode(true);
        itemList.appendChild(newRow);
    }
    
    addRow(); // เพิ่มแถวแรกอัตโนมัติ

    addBtn.addEventListener("click", addRow);

    itemList.addEventListener("click", function(e) {
        if (e.target && e.target.closest(".remove-item-btn")) {
            e.target.closest("tr").remove();
        }
    });

    itemList.addEventListener("change", function(e) {
        if (e.target && e.target.classList.contains("material-select")) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const unit = selectedOption.getAttribute('data-unit') || '';
            const unitInput = e.target.closest("tr").querySelector(".unit-display");
            unitInput.value = unit;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>