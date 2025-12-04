<?php 
require_once '../includes/header.php'; 

// --- 0. ตรวจสอบสิทธิ์ ---
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// --- 1. เตรียมตัวแปร ---
$po_id = null;
$po_number = "";
$po_supplier_id = null;
$po_supplier_name = "";
$is_po_load = false;
$po_items_result = null;

// --- 2. ตรวจสอบว่าถูกส่งมาจากหน้า PO List (มี ?po_id=X) ---
if (isset($_GET['po_id']) && !empty($_GET['po_id'])) {
    $po_id = (int)$_GET['po_id'];
    $is_po_load = true;

    $stmt_po = $conn->prepare("SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?");
    $stmt_po->bind_param("i", $po_id);
    $stmt_po->execute();
    $po_header = $stmt_po->get_result()->fetch_assoc();
    $stmt_po->close();

    if ($po_header) {
        $po_number = $po_header['po_number'];
        $po_supplier_id = $po_header['supplier_id'];
        $po_supplier_name = $po_header['supplier_name'];
    }

    // ⭐️ ดึงข้อมูล is_serial_tracking มาด้วย ⭐️
    $stmt_items = $conn->prepare("SELECT 
                                    pi.material_id, m.item_code, m.name, m.unit, m.is_serial_tracking,
                                    (pi.quantity_ordered - pi.quantity_received) AS qty_pending
                                 FROM po_items pi
                                 JOIN materials m ON pi.material_id = m.id
                                 WHERE pi.po_id = ? AND pi.quantity_ordered > pi.quantity_received");
    $stmt_items->bind_param("i", $po_id);
    $stmt_items->execute();
    $po_items_result = $stmt_items->get_result();
    $stmt_items->close();
}

// --- 3. ดึงข้อมูล Master ---
$suppliers_result = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");

// ⭐️ ดึงข้อมูล is_serial_tracking ใส่ใน Option ⭐️
// ⭐️ 3.1 เพิ่ม data-code ใน Option (เหมือนเดิม) ⭐️
$materials_result = $conn->query("SELECT id, item_code, name, unit, default_location_id, is_serial_tracking FROM materials ORDER BY name ASC");
$material_options_html = "<option value=''>-- เลือกวัสดุ --</option>";
while($row = $materials_result->fetch_assoc()) {
    $is_serial = $row['is_serial_tracking'] ?? 0;
    $material_options_html .= "<option value='{$row['id']}' 
                                    data-code='{$row['item_code']}' 
                                    data-unit='{$row['unit']}' 
                                    data-default-loc='{$row['default_location_id']}'
                                    data-is-serial='{$is_serial}'>
                                {$row['item_code']} - {$row['name']}
                               </option>";
}

$locations_result = $conn->query("SELECT id, location_code FROM locations ORDER BY location_code ASC");
// ... (ส่วน Location เหมือนเดิม) ...

$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}
?>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<h1 class="mb-4"><i class="bi bi-truck me-2"></i> บันทึกการรับวัสดุ (GR)</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm mb-4 bg-light border-primary">
    <div class="card-body">
        <div class="row g-2 align-items-center">
            <div class="col-auto"><i class="bi bi-upc-scan fs-3 text-primary"></i></div>
            <div class="col">
                <input type="text" id="barcode-input" class="form-control form-control-lg" placeholder="ยิงบาร์โค้ดสินค้า เพื่อค้นหา/เพิ่มรายการ" autofocus>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-outline-primary btn-lg" id="btn-open-camera">
                    <i class="bi bi-camera-fill"></i> สแกนกล้อง
                </button>
            </div>
        </div>
        <div id="reader" style="width: 100%; display:none; margin-top:10px;"></div>
        <div id="scan-message" class="mt-2 fw-bold text-success"></div>
    </div>
</div>

<form action="gr_process.php" method="POST" id="gr-form">
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">ข้อมูลการรับ (GR Header)</h5></div>
        <div class="card-body">
            <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">เลขที่ PO</label>
                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($po_number); ?>" <?php echo $is_po_load ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-8">
                    <label class="form-label">ผู้ขาย</label>
                    <?php if ($is_po_load): ?>
                        <input type="hidden" name="supplier_id" value="<?php echo $po_supplier_id; ?>">
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($po_supplier_name); ?>" readonly>
                    <?php else: ?>
                        <select id="supplier_id" name="supplier_id" class="form-select" required>
                            <option value="">-- เลือกผู้ขาย --</option>
                            <?php $suppliers_result->data_seek(0); while($row = $suppliers_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">หมายเหตุ</label>
                    <textarea class="form-control" name="notes" rows="1"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">รายการวัสดุที่รับ</h5>
            <button type="button" class="btn btn-success btn-sm" id="add-item-btn"><i class="bi bi-plus-circle"></i> เพิ่มรายการ</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0 align-middle" id="gr-item-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">วัสดุ</th>
                            <th style="width: 10%; text-align:right;">ค้างรับ</th>
                            <th style="width: 15%;">จำนวนรับ</th>
                            <th style="width: 20%;">Lot/Batch หรือ Serial</th>
                            <th style="width: 20%;">ที่จัดเก็บ</th>
                            <th style="width: 5%;">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="item-list">
                        <?php if ($is_po_load && $po_items_result->num_rows > 0): ?>
                            <?php while($item = $po_items_result->fetch_assoc()): 
                                $is_serial = $item['is_serial_tracking'];
                            ?>
                                <tr data-code="<?php echo strtoupper($item['item_code']); ?>" data-is-serial="<?php echo $is_serial; ?>">
                                    <td>
                                        <input type="hidden" name="material_id[]" value="<?php echo $item['material_id']; ?>">
                                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars("{$item['item_code']} - {$item['name']}"); ?>" readonly>
                                        
                                        <?php if($is_serial): ?>
                                            <span class="badge bg-info text-dark mt-1"><i class="bi bi-upc"></i> Serial Tracking</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><input type="text" class="form-control-plaintext text-end" value="<?php echo $item['qty_pending']; ?> <?php echo htmlspecialchars($item['unit']); ?>" readonly></td>
                                    
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control scan-qty" step="0.01" min="0.01" 
                                               value="<?php echo $is_serial ? '0' : $item['qty_pending']; ?>" 
                                               <?php echo $is_serial ? 'readonly' : 'required'; ?>>
                                    </td>

                                    <td>
                                        <?php if(!$is_serial): ?>
                                            <input type="text" name="batch_number[]" class="form-control batch-input" value="BCH-<?php echo date('Ymd'); ?>" placeholder="Batch No.">
                                            <input type="hidden" name="serials[]" value="">
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-primary w-100 btn-manage-serial">
                                                <i class="bi bi-list-ol"></i> ระบุ Serial/Batch (0)
                                            </button>
                                            <input type="hidden" name="batch_number[]" value="-">
                                            <input type="hidden" name="serials[]" class="serial-data" value="[]">
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <select name="location_id[]" class="form-select" required>
                                            <?php 
                                                $locations_result->data_seek(0);
                                                $default_loc_id = $conn->query("SELECT default_location_id FROM materials WHERE id={$item['material_id']}")->fetch_assoc()['default_location_id'];
                                                echo "<option value=''>-- เลือก --</option>";
                                                while($loc = $locations_result->fetch_assoc()) {
                                                    $selected = ($loc['id'] == $default_loc_id) ? 'selected' : '';
                                                    echo "<option value='{$loc['id']}' {$selected}>{$loc['location_code']}</option>";
                                                }
                                            ?>
                                        </select>
                                    </td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="text-center mt-4 mb-5">
        <button type="submit" class="btn btn-primary btn-lg px-5"><i class="bi bi-save-fill"></i> บันทึกการรับของ</button>
    </div>
</form>

<table style="display: none;">
    <tbody id="item-template">
        <tr>
            <td>
                <select name="material_id[]" class="form-select material-select" required>
                    <?php echo $material_options_html; ?>
                </select>
                <div class="badge-container mt-1"></div>
            </td>
            <td><input type="text" class="form-control-plaintext text-center" value="-" readonly></td>
            <td>
                <input type="number" name="quantity[]" class="form-control scan-qty" step="0.01" min="0.01" required>
            </td>
            <td class="input-mode-cell">
                <input type="text" name="batch_number[]" class="form-control batch-input" value="BCH-<?php echo date('Ymd'); ?>">
                <input type="hidden" name="serials[]" class="serial-data" value="">
            </td>
            <td>
                <select name="location_id[]" class="form-select location-select" required>
                     <?php 
                     $locations_result->data_seek(0);
                     while($loc = $locations_result->fetch_assoc()) {
                         echo "<option value='{$loc['id']}'>{$loc['location_code']}</option>";
                     }
                     ?>
                </select>
            </td>
            <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="bi bi-trash"></i></button></td>
        </tr>
    </tbody>
</table>

<div class="modal fade" id="serialModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-list-ul"></i> ระบุ Serial / Batch Detail</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-hover" id="modal-serial-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60%;">Serial Number / Lot No.</th>
                                <th style="width: 30%;">จำนวน (Qty)</th>
                                <th style="width: 10%;">ลบ</th>
                            </tr>
                        </thead>
                        <tbody id="modal-serial-tbody">
                            </tbody>
                    </table>
                </div>
                
                <button type="button" class="btn btn-success btn-sm" id="btn-add-serial-row">
                    <i class="bi bi-plus-circle"></i> เพิ่มแถว
                </button>
                
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">รวมจำนวนทั้งหมด: <span id="serial-total-qty" class="badge bg-success fs-5">0</span></span>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="clear-serials">ล้างทั้งหมด</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button type="button" class="btn btn-primary" id="save-serials-btn">ยืนยันข้อมูล</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // ... (ตัวแปรเดิม) ...
    const addBtn = document.getElementById('add-item-btn');
    const itemList = document.getElementById('item-list');
    const templateRow = document.getElementById("item-template").firstElementChild.cloneNode(true);
    const isPoMode = <?php echo $is_po_load ? 'true' : 'false'; ?>;

    // Modal Elements
    const serialModal = new bootstrap.Modal(document.getElementById('serialModal'));
    const modalTbody = document.getElementById('modal-serial-tbody');
    const btnAddSerialRow = document.getElementById('btn-add-serial-row');
    const serialTotalBadge = document.getElementById('serial-total-qty');
    const saveSerialBtn = document.getElementById('save-serials-btn');
    const clearSerialBtn = document.getElementById('clear-serials');
    
    let currentSerialBtn = null; 

    // 1. ฟังก์ชันเพิ่มแถว (เหมือนเดิม)
    function addRow() {
        const newRow = templateRow.cloneNode(true);
        itemList.appendChild(newRow);
        return newRow;
    }
    if (!isPoMode && itemList.children.length === 0) addRow(); 
    addBtn.addEventListener("click", addRow);

    // 2. จัดการ Event ในตาราง
    itemList.addEventListener("click", function(e) {
        if (e.target.closest(".remove-item-btn")) {
            if (itemList.children.length > 1 || isPoMode) {
                 e.target.closest("tr").remove();
            } else { alert("ต้องเหลืออย่างน้อย 1 แถว"); }
        }
        // ปุ่มเปิด Modal
        if (e.target.closest(".btn-manage-serial")) {
            openSerialModal(e.target.closest(".btn-manage-serial"));
        }
    });
    
    // 3. Logic เปลี่ยน Mode (เหมือนเดิม)
    itemList.addEventListener("change", function(e) {
        if (e.target.classList.contains("material-select")) {
            const selected = e.target.options[e.target.selectedIndex];
            const isSerial = selected.getAttribute('data-is-serial') == '1';
            const defaultLoc = selected.getAttribute('data-default-loc');
            const row = e.target.closest("tr");
            
            const locSelect = row.querySelector(".location-select");
            if (locSelect && defaultLoc) locSelect.value = defaultLoc;

            const inputCell = row.querySelector(".input-mode-cell");
            const qtyInput = row.querySelector(".scan-qty");
            const badgeContainer = row.querySelector(".badge-container");

            if (isSerial) {
                qtyInput.readOnly = true;
                qtyInput.value = 0;
                qtyInput.classList.add('bg-light');
                badgeContainer.innerHTML = '<span class="badge bg-info text-dark mt-1"><i class="bi bi-upc"></i> Serial Tracking</span>';
                inputCell.innerHTML = `
                    <button type="button" class="btn btn-outline-primary w-100 btn-manage-serial">
                        <i class="bi bi-list-ol"></i> ระบุ Serial (0)
                    </button>
                    <input type="hidden" name="batch_number[]" value="-">
                    <input type="hidden" name="serials[]" class="serial-data" value="[]">
                `;
            } else {
                qtyInput.readOnly = false;
                qtyInput.value = '';
                qtyInput.classList.remove('bg-light');
                badgeContainer.innerHTML = '';
                inputCell.innerHTML = `
                    <input type="text" name="batch_number[]" class="form-control batch-input" value="BCH-<?php echo date('Ymd'); ?>">
                    <input type="hidden" name="serials[]" value="">
                `;
            }
        }
    });

    // --- ⭐️ 4. Serial Modal Logic (แบบใหม่: ตาราง) ⭐️ ---
    
    // ฟังก์ชันสร้างแถวใน Modal
    function createModalRow(serial = '', qty = 1) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" class="form-control modal-serial-input" value="${serial}" placeholder="ระบุ Serial / Lot">
            </td>
            <td>
                <input type="number" class="form-control modal-qty-input text-end" value="${qty}" step="0.01" min="0.01">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-modal-row"><i class="bi bi-trash"></i></button>
            </td>
        `;
        
        // ผูก Event คำนวณยอดรวมเมื่อเปลี่ยนเลข
        tr.querySelector('.modal-qty-input').addEventListener('input', calculateTotal);
        tr.querySelector('.btn-remove-modal-row').addEventListener('click', () => {
            tr.remove();
            calculateTotal();
        });
        
        return tr;
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.modal-qty-input').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        serialTotalBadge.textContent = total.toFixed(2);
    }

    function openSerialModal(btn) {
        currentSerialBtn = btn;
        const row = btn.closest('tr');
        const hiddenInput = row.querySelector('.serial-data');
        
        // Clear old data
        modalTbody.innerHTML = '';
        
        // Load existing data (JSON Array of Objects: [{serial: "A", qty: 10}, ...])
        let serialsData = [];
        try { 
            serialsData = JSON.parse(hiddenInput.value || '[]'); 
        } catch(e) {}
        
        // ถ้ามีข้อมูลเดิม ให้สร้างแถวตามนั้น
        if (serialsData.length > 0) {
            serialsData.forEach(item => {
                // รองรับทั้งแบบเก่า (String array) และแบบใหม่ (Object array)
                let sName = (typeof item === 'object') ? item.serial : item;
                let sQty = (typeof item === 'object') ? item.qty : 1;
                modalTbody.appendChild(createModalRow(sName, sQty));
            });
        } else {
            // ถ้าไม่มีข้อมูล ให้สร้างแถวว่าง 1 แถว
            modalTbody.appendChild(createModalRow());
        }
        
        calculateTotal();
        serialModal.show();
    }

    btnAddSerialRow.addEventListener('click', () => {
        modalTbody.appendChild(createModalRow());
        calculateTotal();
    });

    clearSerialBtn.addEventListener('click', () => {
        if(confirm("ล้างข้อมูลทั้งหมด?")) {
            modalTbody.innerHTML = '';
            modalTbody.appendChild(createModalRow());
            calculateTotal();
        }
    });

    saveSerialBtn.addEventListener('click', function() {
        if (!currentSerialBtn) return;
        
        const rows = modalTbody.querySelectorAll('tr');
        let dataToSave = [];
        let totalQty = 0;
        
        rows.forEach(tr => {
            const sInput = tr.querySelector('.modal-serial-input').value.trim();
            const qInput = parseFloat(tr.querySelector('.modal-qty-input').value) || 0;
            
            if (sInput !== '' && qInput > 0) {
                dataToSave.push({ serial: sInput, qty: qInput });
                totalQty += qInput;
            }
        });

        const row = currentSerialBtn.closest('tr');
        const qtyInput = row.querySelector('.scan-qty');
        const hiddenInput = row.querySelector('.serial-data');

        // Save JSON Object to hidden input
        hiddenInput.value = JSON.stringify(dataToSave);
        qtyInput.value = totalQty;
        
        // Update button text
        currentSerialBtn.innerHTML = `<i class="bi bi-list-ol"></i> ระบุ Serial (${totalQty})`;
        currentSerialBtn.classList.remove('btn-outline-primary', 'btn-success');
        currentSerialBtn.classList.add(dataToSave.length > 0 ? 'btn-success' : 'btn-outline-primary');

        serialModal.hide();
    });

    // ... (Barcode Scan Logic อื่นๆ เก็บไว้เหมือนเดิม หรือปิดไว้ก่อนถ้าเน้นใช้ Modal) ... 
    // (ถ้าต้องการให้ Barcode ยิงเข้า Modal ได้ ให้แก้ฟังก์ชัน handleScan ให้เช็คว่า Modal เปิดอยู่ไหม แล้วยิงเข้า input ใหม่)
});
</script>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>