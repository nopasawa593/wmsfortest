<?php 
// 1. เรียก Header และเชื่อมต่อฐานข้อมูล
require_once '../includes/header.php'; 

// --- 2. ตรวจสอบสิทธิ์ (DEPT_STAFF และ DEPT_MANAGER เท่านั้น) ---
$allowed_roles = ['DEPT_STAFF', 'DEPT_MANAGER'];
if (!hasRole($allowed_roles)) {
    die("<div class='alert alert-danger m-4'>Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (Role ของคุณคือ: " . ($_SESSION['role'] ?? 'None') . ")</div>");
}

// --- 3. ดึงข้อมูลวัสดุ + คำนวณยอดคงเหลือ (Available) ---
// (JOIN ตาราง inventory เพื่อหายอด On-Hand และ Reserved)
$sql_materials = "SELECT 
                    m.id, 
                    m.item_code, 
                    m.name, 
                    m.unit,
                    (COALESCE(SUM(i.quantity), 0) - COALESCE(SUM(i.quantity_reserved), 0)) AS available_qty
                  FROM materials m
                  LEFT JOIN inventory i ON m.id = i.material_id
                  WHERE m.status = 'Active' 
                  GROUP BY m.id, m.item_code, m.name, m.unit
                  ORDER BY m.name ASC";

$materials_result = $conn->query($sql_materials);

// สร้าง HTML Option สำหรับ Dropdown
$material_options_html = "<option value=''>-- เลือกวัสดุ --</option>";

while($row = $materials_result->fetch_assoc()) {
    $qty = (float)$row['available_qty'];
    
    // Logic 1: ถ้าของหมด (<= 0) ให้ใส่ attribute disabled
    $disabled_attr = ($qty <= 0) ? "disabled" : "";
    
    // Logic 2: แต่งข้อความใน Dropdown
    if ($qty > 0) {
        $stock_text = " (คงเหลือ: " . number_format($qty, 2) . ")";
        $style = ""; // ปกติ
    } else {
        $stock_text = " (สินค้าหมด)";
        $style = "color: #adb5bd; background-color: #f8f9fa;"; // สีจางๆ
    }

    $material_options_html .= "<option value='{$row['id']}' 
                                      data-unit='{$row['unit']}' 
                                      {$disabled_attr} 
                                      style='{$style}'>
                                      {$row['item_code']} - {$row['name']} {$stock_text}
                               </option>";
}

// --- 4. ดึงชื่อแผนกของผู้ใช้ (เพื่อแสดงผล) ---
$user_department_name = '-';
if (!empty($_SESSION['department_id'])) {
    $dept_stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    $dept_stmt->bind_param("i", $_SESSION['department_id']);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    if ($dept_result->num_rows > 0) {
        $user_department_name = $dept_result->fetch_assoc()['name'];
    }
    $dept_stmt->close();
}
$conn->close();

// --- 5. ตรวจสอบ Alert Message (Flash Message) ---
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0"><i class="bi bi-cart-plus me-2 text-primary"></i>สร้างใบเบิกวัสดุ (MR)</h2>
        <p class="text-muted mt-1">กรอกรายละเอียดวัสดุที่ต้องการเบิกใช้งาน</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="requisition_process.php" method="POST" id="mr-form">
    
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom-0 py-3">
            <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-person-badge me-2"></i>ข้อมูลผู้ขอเบิก</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label fw-bold">ชื่อผู้ขอเบิก</label>
                    <input type="text" class="form-control bg-light" id="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label for="department_name" class="form-label fw-bold">แผนก</label>
                    <input type="text" class="form-control bg-light" id="department_name" name="department" value="<?php echo htmlspecialchars($user_department_name); ?>" required readonly>
                    <div class="form-text text-muted small">ระบบจะส่งใบเบิกนี้ไปยังหัวหน้าแผนก <?php echo htmlspecialchars($user_department_name); ?> เพื่ออนุมัติ</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-list-check me-2"></i>รายการวัสดุ</h5>
            <button type="button" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm hover-scale" id="add-item-btn">
                <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการ
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="mr-item-table">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 45%;" class="ps-4">วัสดุ (Material)</th>
                            <th style="width: 20%;">จำนวนที่ขอเบิก</th>
                            <th style="width: 20%;">หน่วยนับ</th>
                            <th style="width: 15%;" class="text-center">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="item-list">
                        </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm hover-scale" id="submit-btn">
                <i class="bi bi-send-fill me-2"></i> ส่งใบเบิก
            </button>
        </div>
    </div>
</form>

<table style="display: none;">
    <tbody id="item-template">
        <tr>
            <td class="ps-4">
                <select name="material_id[]" class="form-select material-select shadow-sm" required>
                    <?php echo $material_options_html; ?>
                </select>
                
                <div class="stock-info-display mt-2 p-2 rounded bg-info bg-opacity-10 border border-info border-opacity-25" style="display: none; font-size: 0.9rem;">
                    <i class="bi bi-box-seam me-1 text-info"></i>
                    <span class="text-dark">คงเหลือเบิกได้: <strong class="stock-available">0</strong></span>
                    <span class="text-muted ms-2 small">(จองไว้: <span class="stock-reserved">0</span>)</span>
                </div>
            </td>
            <td>
                <div class="input-group">
                    <input type="number" name="quantity_requested[]" class="form-control qty-input text-center fw-bold text-primary" step="0.01" min="0.01" required>
                </div>
                <div class="invalid-feedback fw-bold">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> เกินยอดคงเหลือ!
                </div>
            </td>
            <td>
                <input type="text" class="form-control-plaintext unit-display text-muted" readonly>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-light text-danger btn-sm border shadow-sm remove-item-btn rounded-circle" style="width: 32px; height: 32px; padding: 0;">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </td>
        </tr>
    </tbody>
</table>

<style>
    .hover-scale:hover { transform: scale(1.03); transition: transform 0.2s; }
    /* สไตล์สำหรับ input ที่ Error */
    .form-control.is-invalid {
        border-color: #dc3545;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const addBtn = document.getElementById("add-item-btn");
    const itemList = document.getElementById("item-list");
    const templateRow = document.getElementById("item-template").firstElementChild.cloneNode(true);
    const form = document.getElementById('mr-form');

    // ฟังก์ชันเพิ่มแถว
    function addRow() {
        const newRow = templateRow.cloneNode(true);
        // Reset ค่าต่างๆ
        newRow.querySelector('select').value = '';
        newRow.querySelector('.stock-info-display').style.display = 'none';
        newRow.querySelector('.qty-input').value = '';
        newRow.querySelector('.qty-input').classList.remove('is-invalid');
        newRow.querySelector('.unit-display').value = '-';
        
        itemList.appendChild(newRow);
    }
    
    // เพิ่มแถวแรกอัตโนมัติ
    addRow(); 

    // Event: ปุ่มเพิ่มรายการ
    addBtn.addEventListener("click", addRow);

    // Event Delegation: ปุ่มลบรายการ
    itemList.addEventListener("click", function(e) {
        const removeButton = e.target.closest(".remove-item-btn");
        if (removeButton) {
            if (itemList.children.length > 1) {
                removeButton.closest("tr").remove();
            } else {
                 alert("ต้องเหลือรายการอย่างน้อย 1 แถว");
            }
        }
    });

    // --- ⭐️ Logic หลัก: ตรวจสอบสต็อกและจำนวน ---
    itemList.addEventListener("change", function(e) {
        // เมื่อมีการเปลี่ยน Dropdown เลือกวัสดุ
        if (e.target && e.target.classList.contains("material-select")) {
            
            const selectElement = e.target;
            const row = selectElement.closest("tr");
            const unitInput = row.querySelector(".unit-display");
            const qtyInput = row.querySelector(".qty-input");
            
            // ดึง Unit จาก data-attribute ที่ PHP สร้างไว้
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const unit = selectedOption.getAttribute('data-unit') || '-';
            unitInput.value = unit;

            const materialId = selectElement.value;
            const stockInfoDiv = row.querySelector(".stock-info-display");
            const stockAvailableSpan = stockInfoDiv.querySelector(".stock-available");
            const stockReservedSpan = stockInfoDiv.querySelector(".stock-reserved");

            // Reset ค่า Input จำนวน
            qtyInput.value = '';
            qtyInput.classList.remove('is-invalid');
            qtyInput.removeAttribute('max'); // ล้างค่า Max เดิมก่อน

            if (!materialId) {
                stockInfoDiv.style.display = 'none';
                return;
            }
            
            // แสดง Loading
            stockInfoDiv.style.display = 'block';
            stockAvailableSpan.textContent = 'กำลังโหลด...';
            stockReservedSpan.textContent = '...';

            // เรียก API เพื่อดึงยอดล่าสุด (Real-time Check)
            fetch(`api_get_material_stock.php?material_id=${materialId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        stockAvailableSpan.textContent = 'N/A';
                    } else {
                        const available = parseFloat(data.available);
                        const reserved = parseFloat(data.reserved);
                        
                        // แสดงผลตัวเลข
                        stockAvailableSpan.textContent = available.toLocaleString(undefined, {minimumFractionDigits: 2});
                        stockReservedSpan.textContent = reserved.toLocaleString(undefined, {minimumFractionDigits: 2});
                        
                        // ⭐️ ตั้งค่า Max ให้ input เท่ากับยอดคงเหลือ
                        qtyInput.setAttribute('max', available);
                        qtyInput.setAttribute('data-limit', available); // เก็บค่าไว้อ้างอิง
                        
                        // ถ้าของหมด (เป็นไปได้ยากเพราะ PHP กรองแล้ว แต่กันไว้ก่อน)
                        if(available <= 0) {
                            qtyInput.disabled = true;
                            qtyInput.placeholder = "สินค้าหมด";
                        } else {
                            qtyInput.disabled = false;
                            qtyInput.placeholder = "ไม่เกิน " + available;
                        }
                    }
                })
                .catch(error => {
                    console.error('API Error:', error);
                    stockAvailableSpan.textContent = 'Error';
                });
        }
    });

    // --- ⭐️ Logic Validation: ตรวจสอบตอนพิมพ์ตัวเลข ---
    itemList.addEventListener("input", function(e) {
        if (e.target && e.target.classList.contains("qty-input")) {
            const input = e.target;
            const limit = parseFloat(input.getAttribute('data-limit')); // ดึงค่า limit ที่เก็บไว้
            const currentVal = parseFloat(input.value);

            if (!isNaN(limit) && currentVal > limit) {
                // ถ้ากรอกเกิน -> แดง
                input.classList.add('is-invalid');
            } else {
                // ถ้าปกติ -> เอาแดงออก
                input.classList.remove('is-invalid');
            }
        }
    });

    // --- ⭐️ Logic Submit: ป้องกันการส่งถ้าข้อมูลผิด ---
    form.addEventListener('submit', function(e) {
        let hasError = false;
        
        // เช็คทุกช่อง input
        const inputs = itemList.querySelectorAll('.qty-input');
        inputs.forEach(input => {
            // เช็คว่ามี class error หรือค่าเกิน limit ไหม
            const limit = parseFloat(input.getAttribute('data-limit'));
            const val = parseFloat(input.value);
            
            if (input.classList.contains('is-invalid') || (limit !== undefined && val > limit)) {
                hasError = true;
                input.classList.add('is-invalid'); // ย้ำแดงอีกที
            }
        });

        if (hasError) {
            e.preventDefault(); // หยุดการส่งฟอร์ม
            alert("⚠️ ไม่สามารถส่งใบเบิกได้ \n\nกรุณาตรวจสอบจำนวนที่ขอเบิก ต้องไม่เกินยอดคงเหลือ");
        }
    });
});
</script>

<?php 
require_once '../includes/footer.php'; 
?>