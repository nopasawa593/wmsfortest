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

// 4. ตรวจสอบสิทธิ์ (ทีมพัสดุ)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to manage payments.");
}

// 5. ⭐️ (Logic POST - อัปเกรด) ⭐️
// (นี่คือ Logic การจ่ายเงินจริง)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $paid_by_user_id = $_SESSION['user_id']; 
    $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : NULL; // ⭐️ (ใหม่)
    $po_id = !empty($_POST['po_id']) ? (int)$_POST['po_id'] : NULL; // (เก็บไว้สำหรับอัปเดต PO)
    
    // ⭐️ (แก้ไข) ดึง supplier_id จาก hidden input
    $supplier_id = (int)$_POST['supplier_id_hidden']; // (ใช้ supplier_id_hidden แทน)
    
    $payment_date = $_POST['payment_date'];
    $amount = (float)$_POST['amount'];
    $reference_number = $_POST['reference_number']; // (เลขที่โอน/เช็ค)
    $payment_method = $_POST['payment_method'];

    if (empty($supplier_id) || empty($payment_date) || $amount <= 0) {
        $_SESSION['alert_message'] = "ข้อมูลไม่ครบถ้วน (Supplier, Date, Amount)";
        $_SESSION['alert_type'] = "warning";
    } else {
        $conn->begin_transaction();
        try {
            // 1. บันทึกการจ่ายเงิน (ap_payments)
            $stmt = $conn->prepare("INSERT INTO ap_payments (supplier_id, invoice_id, payment_date, amount, reference_number, payment_method, paid_by_user_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdssi", $supplier_id, $invoice_id, $payment_date, $amount, $reference_number, $payment_method, $paid_by_user_id);
            $stmt->execute();
            $payment_id = $conn->insert_id;
            $stmt->close();

            if ($invoice_id) {
                // 2. อัปเดตสถานะ Invoice เป็น "Paid"
                $conn->query("UPDATE supplier_invoices SET status = 'Paid' WHERE id = $invoice_id");
                
                // 3. (ถ้ามี PO) อัปเดตสถานะ PO เป็น "Paid"
                if ($po_id) {
                    $conn->query("UPDATE purchase_orders SET payment_status = 'Paid' WHERE id = $po_id");
                }
            }
            
            $conn->commit();
            $_SESSION['alert_message'] = "บันทึกการชำระเงิน (ID: $payment_id) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['alert_message'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
            $_SESSION['alert_type'] = "danger";
        }
    }
    header("Location: payment_create.php");
    exit();
}
// --- จบส่วน xử lý POST ---


// 6. ดึง Alert Message (ถ้ามี)
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// 7. Include Header
require_once '../includes/header.php';

// --- 8. ⭐️ (Logic ใหม่) ดึง Invoice ที่ยังไม่จ่าย (Unpaid) ⭐️ ---
$sql_invoice_list = "SELECT 
                        inv.id AS invoice_id,
                        inv.invoice_number,
                        inv.invoice_date,
                        inv.invoice_amount,
                        inv.po_id,
                        inv.supplier_id,
                        s.name AS supplier_name,
                        po.po_number
                    FROM supplier_invoices inv
                    JOIN suppliers s ON inv.supplier_id = s.id
                    LEFT JOIN purchase_orders po ON inv.po_id = po.id
                    WHERE inv.status = 'Unpaid' -- (ดึงเฉพาะที่ยังไม่จ่าย)
                    ORDER BY inv.invoice_date ASC";
$invoice_list_result = $conn->query($sql_invoice_list);

// (ดึง Suppliers สำหรับ Modal "ชำระเงินอื่นๆ")
$suppliers_result = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-credit-card me-2"></i>บันทึกการชำระหนี้ (AP Payment)</h1>
    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#paymentModal" id="add-manual-payment-btn">
        <i class="bi bi-plus-circle-fill"></i> ชำระเงินอื่นๆ (Non-Invoice)
    </button>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">รายการใบแจ้งหนี้ที่รอชำระ (Unpaid Invoices)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ Invoice</th>
                        <th>วันที่ Invoice</th>
                        <th>ผู้ขาย (Supplier)</th>
                        <th>อ้างอิง (PO)</th>
                        <th class="text-end">ยอดค้างชำระ</th>
                        <th class="text-center">จัดการ (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoice_list_result && $invoice_list_result->num_rows > 0): ?>
                        <?php while($row = $invoice_list_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                <td><?php echo $row['invoice_date']; ?></td>
                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['po_number'] ?? 'N/A'); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['invoice_amount'], 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary btn-sm pay-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#paymentModal"
                                            data-invoice-id="<?php echo $row['invoice_id']; ?>"
                                            data-po-id="<?php echo $row['po_id']; ?>"
                                            data-reference="<?php echo htmlspecialchars($row['invoice_number']); ?>"
                                            data-supplier-id="<?php echo $row['supplier_id']; ?>"
                                            data-amount="<?php echo $row['invoice_amount']; ?>">
                                        <i class="bi bi-wallet-fill"></i> จ่ายเงิน
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ไม่พบรายการค้างชำระ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="payment_create.php" method="POST" id="payment-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">บันทึกการชำระเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="invoice_id" id="modal-invoice-id">
                    <input type="hidden" name="po_id" id="modal-po-id">
                    <input type="hidden" name="supplier_id_hidden" id="modal-supplier-id-hidden">

                    <div class="mb-3">
                        <label for="modal-supplier-id" class="form-label">ผู้ขาย (Supplier)</label>
                        <select id="modal-supplier-id" name="supplier_id_display" class="form-select" required>
                            <option value="">-- เลือกผู้ขาย --</option>
                            <?php 
                            if ($suppliers_result && $suppliers_result->num_rows > 0) {
                                $suppliers_result->data_seek(0);
                                while($row = $suppliers_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="modal-payment-date" class="form-label">วันที่ชำระเงิน</label>
                            <input type="date" class="form-control" id="modal-payment-date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal-amount" class="form-label">จำนวนเงิน (บาท)</label>
                            <input type="number" class="form-control" id="modal-amount" name="amount" step="0.01" min="0.01" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal-payment-method" class="form-label">ช่องทางการชำระเงิน</label>
                        <select id="modal-payment-method" name="payment_method" class="form-select">
                            <option value="Bank Transfer">โอนผ่านธนาคาร (Bank Transfer)</option>
                            <option value="Cash">เงินสด (Cash)</option>
                            <option value="Cheque">เช็ค (Cheque)</option>
                            <option value="Other">อื่นๆ</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal-reference-number" class="form-label">เลขที่อ้างอิง (เช่น เลขที่โอน, เลขที่เช็ค)</label>
                        <input type="text" class="form-control" id="modal-reference-number" name="reference_number" required>
                        <div class="form-text" id="ref-help-text"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="save-btn">
                        <i class="bi bi-save-fill"></i> บันทึกการชำระเงิน
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const paymentModal = document.getElementById('paymentModal');
    if (!paymentModal) return;

    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('payment-form');
    
    // ⭐️ (แก้ไข) เปลี่ยนชื่อตัวแปร Dropdown 
    const supplierSelectDisplay = document.getElementById('modal-supplier-id');
    const supplierHiddenInput = document.getElementById('modal-supplier-id-hidden'); // ⭐️ (เพิ่ม)
    
    const amountInput = document.getElementById('modal-amount');
    const refInput = document.getElementById('modal-reference-number');
    const dateInput = document.getElementById('modal-payment-date');
    const invoiceIdInput = document.getElementById('modal-invoice-id');
    const poIdInput = document.getElementById('modal-po-id');
    const refHelpText = document.getElementById('ref-help-text');

    paymentModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; 
        
        form.reset();
        dateInput.value = '<?php echo date('Y-m-d'); ?>';
        
        // ⭐️ (แก้ไข) Reset Dropdown
        supplierSelectDisplay.disabled = false;
        supplierSelectDisplay.value = "";
        supplierHiddenInput.value = "";
        
        amountInput.readOnly = false;
        refInput.placeholder = "เช่น เลขที่โอน, เลขที่เช็ค";
        refHelpText.textContent = "";
        
        if (button.id === 'add-manual-payment-btn') {
            // 1. ถ้ากด "ชำระเงินอื่นๆ"
            modalTitle.textContent = "บันทึกการชำระเงิน (Non-Invoice)";
            invoiceIdInput.value = null;
            poIdInput.value = null;
            // (ผู้ใช้ต้องเลือก Supplier เอง)
            
        } else if (button.classList.contains('pay-btn')) {
            // 2. ถ้ากด "จ่ายเงิน" จากตาราง
            const data = button.dataset;
            modalTitle.textContent = `ชำระเงินสำหรับ Invoice: ${data.reference}`;
            
            // ⭐️ (แก้ไข) 2.1 เติมค่าที่ถูกต้อง ⭐️
            invoiceIdInput.value = data.invoiceId;
            poIdInput.value = data.poId;
            
            // (เติมค่าทั้ง Dropdown (เพื่อโชว์) และ Hidden (เพื่อส่งค่า))
            supplierSelectDisplay.value = data.supplierId;
            supplierSelectDisplay.disabled = true; // (ล็อก Dropdown)
            supplierHiddenInput.value = data.supplierId; // ⭐️ (สำคัญ!)
            
            amountInput.value = data.amount;
            amountInput.readOnly = true; 
            
            refInput.placeholder = "เช่น เลขที่โอน, เลขที่เช็ค";
            refHelpText.textContent = `อ้างอิง Invoice No: ${data.reference}`;
        }
        
        // ⭐️ (เพิ่ม) 3. Logic สำหรับปุ่ม Add Manual ⭐️
        // (ถ้าผู้ใช้เปลี่ยน Dropdown (ตอน Add Manual) ให้ Aupdate Hidden Input ด้วย)
        supplierSelectDisplay.onchange = function() {
            if (!supplierSelectDisplay.disabled) {
                supplierHiddenInput.value = this.value;
            }
        };
    });
});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>