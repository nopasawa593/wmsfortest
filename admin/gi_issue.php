<?php
require_once '../includes/header.php';

// --- 1. ตรวจสอบสิทธิ์ (เฉพาะทีมพัสดุ) ---
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("<div class='alert alert-danger m-4'>Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>");
}

// --- 2. รับค่า ID ใบเบิก ---
$req_id = isset($_GET['req_id']) ? (int)$_GET['req_id'] : 0;
if (!$req_id) {
    die("<div class='alert alert-danger m-4'>Invalid Request: ไม่พบ ID ใบเบิก</div>");
}

// --- 3. ดึงข้อมูล Header ใบเบิก ---
$sql_header = "SELECT 
                r.mr_number, r.request_date, r.status,
                u.full_name, 
                d.name AS department_name 
               FROM requisitions r 
               JOIN users u ON r.requested_by_user_id = u.id 
               LEFT JOIN departments d ON u.department_id = d.id
               WHERE r.id = ?";
               
$stmt = $conn->prepare($sql_header);
$stmt->bind_param("i", $req_id);
$stmt->execute();
$req_header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req_header) {
    die("<div class='alert alert-danger m-4'>ไม่พบข้อมูลใบเบิก</div>");
}

// --- 4. ดึงรายการวัสดุ + Location ---
$sql_items = "SELECT 
                ri.material_id, ri.quantity_requested,
                m.name, m.item_code, m.unit, m.is_serial_tracking, 
                l.location_code 
              FROM requisition_items ri 
              JOIN materials m ON ri.material_id = m.id 
              LEFT JOIN locations l ON m.default_location_id = l.id
              WHERE ri.requisition_id = ?";
              
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $req_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$stmt_items->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0 text-primary">
            <i class="bi bi-box-seam-fill me-2"></i>จ่ายวัสดุ (GI)
        </h2>
        <div class="text-muted mt-2">
            ใบเบิก: <span class="fw-bold text-dark"><?php echo htmlspecialchars($req_header['mr_number']); ?></span> | 
            ผู้ขอ: <?php echo htmlspecialchars($req_header['full_name']); ?> | 
            แผนก: <span class="badge bg-secondary"><?php echo htmlspecialchars($req_header['department_name'] ?? '-'); ?></span>
        </div>
    </div>
    <a href="requisition_list.php" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> กลับ
    </a>
</div>

<form action="gi_process.php" method="POST" id="gi-form">
    <input type="hidden" name="requisition_id" value="<?php echo $req_id; ?>">
    
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom-0 py-3">
            <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-list-check me-2"></i>รายการที่ต้องจ่าย</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 35%;" class="ps-4">สินค้า / ที่เก็บ (Location)</th>
                            <th style="width: 15%; text-align:right;">จำนวนขอ</th>
                            <th style="width: 35%;">ตัดสต็อกจาก (Source)</th>
                            <th style="width: 15%;">จ่ายจริง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($items_result->num_rows > 0):
                            while($item = $items_result->fetch_assoc()): 
                                $mat_id = $item['material_id'];
                                $is_serial = $item['is_serial_tracking'];
                                // ⭐️ เตรียมค่า Default ยอดจ่าย (เท่ากับยอดขอ)
                                $default_qty = $item['quantity_requested'];
                        ?>
                        <tr data-material-id="<?php echo $mat_id; ?>">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="small text-muted code-font"><?php echo htmlspecialchars($item['item_code']); ?></div>
                                
                                <div class="mt-1">
                                    <?php if(!empty($item['location_code'])): ?>
                                        <span class="badge bg-info text-dark bg-opacity-25 border border-info">
                                            <i class="bi bi-geo-alt-fill me-1"></i> 
                                            Loc: <?php echo htmlspecialchars($item['location_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i> ไม่ระบุที่เก็บ</span>
                                    <?php endif; ?>
                                    
                                    <?php if($is_serial): ?>
                                        <span class="badge bg-warning text-dark ms-1"><i class="bi bi-upc"></i> Serial</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="text-end">
                                <span class="fs-5 fw-bold text-secondary req-qty-text"><?php echo number_format($item['quantity_requested'], 2); ?></span>
                                <small class="text-muted ms-1"><?php echo $item['unit']; ?></small>
                            </td>
                            
                            <td>
                                <?php if(!$is_serial): ?>
                                    <select name="inventory_id[<?php echo $mat_id; ?>]" class="form-select inventory-select shadow-sm" required>
                                        <option value="">-- เลือก Lot ที่จะหยิบ --</option>
                                        <?php
                                        // ดึง Lot ที่มีของ (Available > 0)
                                        $stock_sql = "SELECT i.id, l.location_code, i.batch_number, (i.quantity - i.quantity_reserved) AS available 
                                                      FROM inventory i 
                                                      JOIN locations l ON i.location_id = l.id 
                                                      WHERE i.material_id = $mat_id AND (i.quantity - i.quantity_reserved) > 0
                                                      ORDER BY i.expiry_date ASC, i.id ASC"; // FEFO
                                        $stock_res = $conn->query($stock_sql);
                                        
                                        if ($stock_res->num_rows > 0) {
                                            while($stk = $stock_res->fetch_assoc()){
                                                echo "<option value='{$stk['id']}'>Loc: {$stk['location_code']} | Batch: {$stk['batch_number']} (มี: {$stk['available']})</option>";
                                            }
                                        } else {
                                            echo "<option value='' disabled>❌ สินค้าหมดสต็อก</option>";
                                        }
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-primary w-100 btn-select-serial shadow-sm">
                                        <i class="bi bi-barcode-scan me-2"></i> เลือก Serial Numbers
                                    </button>
                                    <input type="hidden" name="selected_serials[<?php echo $mat_id; ?>]" class="serial-data" value="[]">
                                    <input type="hidden" name="inventory_id[<?php echo $mat_id; ?>]" value="SERIAL_MODE">
                                <?php endif; ?>
                            </td>
                            
                            <td class="pe-3">
                                <input type="number" name="quantity_issued[<?php echo $mat_id; ?>]" 
                                       class="form-control text-end fw-bold text-primary qty-input" 
                                       value="<?php echo $is_serial ? 0 : $default_qty; ?>" 
                                       step="0.01" min="0"
                                       <?php echo $is_serial ? 'readonly' : 'required'; ?>> 
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-5">ไม่พบรายการวัสดุ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm hover-scale">
                <i class="bi bi-check-circle-fill me-2"></i> ยืนยันการจ่ายวัสดุ
            </button>
        </div>
    </div>
</form>

<div class="modal fade" id="serialSelectModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-bottom-0">
                <h5 class="modal-title"><i class="bi bi-upc-scan me-2"></i>เลือก Serial Numbers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center">
                    <div>จำนวนที่ขอ: <strong id="modal-req-qty" class="fs-5">0</strong></div>
                    <div>เลือกแล้ว: <strong id="selected-count" class="fs-5 text-primary">0</strong></div>
                </div>
                
                <div class="table-responsive border rounded-3" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 60px;" class="text-center">เลือก</th>
                                <th>Serial Number</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody id="serial-list-body">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="confirm-serial-selection">ยืนยันการเลือก</button>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-scale:hover { transform: scale(1.02); transition: 0.2s; }
    .code-font { font-family: monospace; letter-spacing: 0.5px; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Elements
    const serialModal = new bootstrap.Modal(document.getElementById('serialSelectModal'));
    const serialBody = document.getElementById('serial-list-body');
    const selectedCountSpan = document.getElementById('selected-count');
    const modalReqQtySpan = document.getElementById('modal-req-qty');
    const confirmBtn = document.getElementById('confirm-serial-selection');
    
    let currentButton = null;
    let currentRequestedQty = 0;

    // 1. ปุ่มเลือก Serial
    document.querySelectorAll('.btn-select-serial').forEach(btn => {
        btn.addEventListener('click', function() {
            currentButton = this;
            const row = this.closest('tr');
            const materialId = row.dataset.materialId;
            const hiddenInput = row.querySelector('.serial-data');
            
            // ดึงจำนวนขอ
            const reqText = row.querySelector('.req-qty-text').innerText.replace(/,/g, '');
            currentRequestedQty = parseFloat(reqText) || 0;
            modalReqQtySpan.textContent = currentRequestedQty;

            // โหลดข้อมูลเก่า
            let selectedSerials = [];
            try { selectedSerials = JSON.parse(hiddenInput.value || '[]'); } catch(e) {}

            // Reset Modal UI
            serialBody.innerHTML = '<tr><td colspan="3" class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted">กำลังโหลดข้อมูล...</div></td></tr>';
            selectedCountSpan.textContent = selectedSerials.length;
            updateCountColor(selectedSerials.length);
            
            serialModal.show();

            // เรียก API
            fetch(`api_get_available_serials.php?material_id=${materialId}`)
                .then(res => res.json())
                .then(data => {
                    serialBody.innerHTML = '';
                    
                    if (data.error) {
                        serialBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">${data.error}</td></tr>`;
                        return;
                    }
                    if (data.length === 0) {
                        serialBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4"><i class="bi bi-x-circle fs-1 d-block mb-2"></i>ไม่พบ Serial ในสต็อก</td></tr>';
                        return;
                    }

                    data.forEach(item => {
                        const isChecked = selectedSerials.includes(item.serial_number) ? 'checked' : '';
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input serial-checkbox" value="${item.serial_number}" ${isChecked} style="cursor:pointer; transform: scale(1.2);">
                            </td>
                            <td class="fw-bold text-primary font-monospace">${item.serial_number}</td>
                            <td><span class="badge bg-light text-dark border">${item.location_code || '-'}</span></td>
                        `;
                        serialBody.appendChild(tr);
                    });

                    // ผูก Event Checkbox
                    document.querySelectorAll('.serial-checkbox').forEach(cb => {
                        cb.addEventListener('change', function() {
                             const count = document.querySelectorAll('.serial-checkbox:checked').length;
                             selectedCountSpan.textContent = count;
                             updateCountColor(count);
                        });
                    });
                })
                .catch(err => {
                    console.error(err);
                    serialBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการเชื่อมต่อ</td></tr>';
                });
        });
    });

    function updateCountColor(count) {
        selectedCountSpan.className = "fs-5 fw-bold " + 
            (count === currentRequestedQty ? "text-success" : (count > currentRequestedQty ? "text-danger" : "text-warning"));
    }

    // 2. ยืนยันการเลือก
    confirmBtn.addEventListener('click', function() {
        if (!currentButton) return;
        
        const checkboxes = document.querySelectorAll('.serial-checkbox:checked');
        const selectedValues = Array.from(checkboxes).map(cb => cb.value);
        const count = selectedValues.length;
        
        if (count > currentRequestedQty) {
            alert(`⚠️ เลือกเกินจำนวน! \nต้องการ: ${currentRequestedQty} \nเลือกไป: ${count}`);
            return;
        }
        
        // Save Data
        const row = currentButton.closest('tr');
        row.querySelector('.serial-data').value = JSON.stringify(selectedValues);
        row.querySelector('.qty-input').value = count;
        
        // Update Button Style
        if (count > 0) {
            currentButton.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i> เลือกแล้ว (${count})`;
            currentButton.classList.remove('btn-outline-primary', 'btn-success');
            currentButton.classList.add('btn-success');
        } else {
            currentButton.innerHTML = `<i class="bi bi-barcode-scan me-2"></i> เลือก Serial Numbers`;
            currentButton.classList.remove('btn-success');
            currentButton.classList.add('btn-outline-primary');
        }
        
        serialModal.hide();
    });
});
</script>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>