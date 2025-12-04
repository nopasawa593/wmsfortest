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

// 4. ตรวจสอบสิทธิ์ (ต้องทำก่อน xử lý POST)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied.");
}

// 5. ⭐️ (ย้าย Logic POST มาไว้บนสุด) ⭐️
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $po_id = !empty($_POST['po_id']) ? (int)$_POST['po_id'] : NULL;
    $supplier_id = (int)$_POST['supplier_id'];
    $invoice_number = $_POST['invoice_number'];
    $invoice_date = $_POST['invoice_date'];
    $invoice_amount = (float)$_POST['invoice_amount'];
    $created_by_user_id = $_SESSION['user_id'];

    try {
        if (empty($supplier_id) || empty($invoice_number) || empty($invoice_date) || $invoice_amount <= 0) {
            throw new Exception("กรุณากรอกข้อมูล Invoice ให้ครบถ้วน");
        }
        
        $stmt = $conn->prepare("INSERT INTO supplier_invoices (po_id, supplier_id, invoice_number, invoice_date, invoice_amount, created_by_user_id) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdi", $po_id, $supplier_id, $invoice_number, $invoice_date, $invoice_amount, $created_by_user_id);
        $stmt->execute();
        
        $_SESSION['alert_message'] = "บันทึก Invoice ($invoice_number) สำเร็จ! รอดำเนินการชำระเงิน";
        $_SESSION['alert_type'] = "success";
        
        // (Redirect ที่นี่ (ก่อน header.php) - ถูกต้องแล้ว)
        header("Location: payment_create.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header("Location: invoice_entry.php"); // (กลับมาหน้าเดิม)
        exit();
    }
}
// --- จบส่วน xử lý POST ---


// 6. ดึง Alert Message (ถ้ามี)
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// 7. ⭐️ (ย้าย header.php มาไว้ตรงนี้) ⭐️
require_once '../includes/header.php'; 

// --- 8. ดึงข้อมูล POs (เหมือนเดิม) ---
$po_list_sql = "SELECT 
                    po.id, 
                    po.po_number, 
                    po.supplier_id,
                    s.name AS supplier_name, 
                    (SELECT SUM(pi.quantity_ordered * pi.unit_price) FROM po_items pi WHERE pi.po_id = po.id) AS total_amount,
                    (SELECT gr.notes FROM goods_receiving gr WHERE gr.po_id = po.id ORDER BY gr.receive_date ASC LIMIT 1) AS gr_notes
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN supplier_invoices inv ON po.id = inv.po_id
                WHERE po.status IN ('Completed', 'Partial') AND inv.id IS NULL
                ORDER BY po.order_date DESC";
$po_list_result = $conn->query($po_list_sql);
?>

<h1 class="mb-4"><i class="bi bi-receipt-cutoff me-2"></i>บันทึกใบแจ้งหนี้ (Invoice Entry)</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">รายการ PO ที่รับของแล้ว (รอ Invoice)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ PO</th>
                        <th>ผู้ขาย</th>
                        <th>ใบส่งของ (GR Note)</th>
                        <th class="text-end">ยอดรวม PO</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($po_list_result && $po_list_result->num_rows > 0): ?>
                        <?php while($row = $po_list_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['gr_notes'] ?? '-'); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['total_amount'], 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary btn-sm invoice-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#invoiceModal"
                                            data-po-id="<?php echo $row['id']; ?>"
                                            data-supplier-id="<?php echo $row['supplier_id']; ?>"
                                            data-amount="<?php echo $row['total_amount']; ?>"
                                            data-gr-note="<?php echo htmlspecialchars($row['gr_notes'] ?? ''); ?>">
                                        <i class="bi bi-plus-circle"></i> บันทึก Invoice
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted">ไม่พบรายการ PO ที่รอการบันทึก Invoice</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="invoice_entry.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">บันทึกใบแจ้งหนี้ (Invoice)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="po_id" id="modal-po-id">
                    <input type="hidden" name="supplier_id" id="modal-supplier-id">
                    
                    <div class="mb-3">
                        <label for="modal-invoice-number" class="form-label">เลขที่ใบแจ้งหนี้ (Invoice No.)</label>
                        <input type="text" class="form-control" id="modal-invoice-number" name="invoice_number" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal-invoice-date" class="form-label">วันที่ในใบแจ้งหนี้</label>
                            <input type="date" class="form-control" id="modal-invoice-date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal-invoice-amount" class="form-label">ยอดเงิน (บาท)</label>
                            <input type="number" class="form-control" id="modal-invoice-amount" name="invoice_amount" step="0.01" min="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> บันทึก Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const invoiceModal = document.getElementById('invoiceModal');
    if (invoiceModal) {
        invoiceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const data = button.dataset;

            const poIdInput = document.getElementById('modal-po-id');
            const supplierIdInput = document.getElementById('modal-supplier-id');
            const amountInput = document.getElementById('modal-invoice-amount');
            const invoiceNumInput = document.getElementById('modal-invoice-number'); 
            
            poIdInput.value = data.poId;
            supplierIdInput.value = data.supplierId;
            amountInput.value = data.amount;
            
            if (data.grNote && data.grNote.trim() !== '') {
                invoiceNumInput.value = data.grNote.trim();
            } else {
                invoiceNumInput.value = ''; 
            }
        });
    }
});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>