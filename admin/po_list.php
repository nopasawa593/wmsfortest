<?php 
require_once '../includes/header.php'; 

// --- 0. ตรวจสอบสิทธิ์ (ทีมพัสดุ) ---
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// --- 1. ⭐️ (อัปเดต Query) ดึง payment_status มาด้วย ⭐️ ---
$sql = "SELECT 
            po.id AS po_id,
            po.po_number, 
            po.order_date, 
            po.expected_delivery_date, 
            po.status,
            po.payment_status, -- ⭐️ (เพิ่ม)
            s.name AS supplier_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        ORDER BY 
            -- (จัดเรียงใหม่: ให้ PO ที่ยังไม่จ่ายและรออนุมัติ ขึ้นก่อน)
            CASE 
                WHEN po.status = 'Pending PO Approval' THEN 1
                WHEN po.status = 'Pending' AND po.payment_status = 'Unpaid' THEN 2
                WHEN po.status = 'Partial' AND po.payment_status = 'Unpaid' THEN 3
                ELSE 99 
            END,
            po.order_date DESC";
$result = $conn->query($sql);

// --- ตรวจสอบ Alert Message จาก Session (ถ้ามี) ---
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-list-stars me-2"></i> รายการใบสั่งซื้อ (Purchase Orders)</h1>
    </div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">รายการสั่งซื้อทั้งหมด</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ PO</th>
                        <th>ผู้ขาย (Supplier)</th>
                        <th>วันที่สั่ง</th>
                        <th>สถานะ (รับของ)</th>
                        <th>สถานะ (การจ่าย)</th> <th class="text-center">จัดการ (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['order_date'])); ?></td>
                                <td>
                                    <?php 
                                    // (Badge สถานะรับของ)
                                    $status = $row['status'];
                                    $badge_class = 'bg-secondary';
                                    if ($status == 'Pending PO Approval') $badge_class = 'bg-warning text-dark';
                                    if ($status == 'PO Rejected') $badge_class = 'bg-danger';
                                    if ($status == 'Pending') $badge_class = 'bg-primary';
                                    if ($status == 'Partial') $badge_class = 'bg-info';
                                    if ($status == 'Completed') $badge_class = 'bg-success';
                                    if ($status == 'Cancelled') $badge_class = 'bg-dark';
                                    echo "<span class='badge {$badge_class}'>{$status}</span>";
                                    ?>
                                </td>
                                
                                <td>
                                    <?php 
                                    $pay_status = $row['payment_status'];
                                    $pay_badge = 'bg-secondary';
                                    if ($pay_status == 'Paid') $pay_badge = 'bg-success';
                                    if ($pay_status == 'Partial') $pay_badge = 'bg-info';
                                    if ($pay_status == 'Unpaid') $pay_badge = 'bg-danger';
                                    echo "<span class='badge {$pay_badge}'>{$pay_status}</span>";
                                    ?>
                                </td>
                                
                                <td class="text-center">
                                    <?php 
                                    // (สถานะ: อนุมัติแล้ว รอรับของ หรือ รับบางส่วน)
                                    if ($row['status'] == 'Pending' || $row['status'] == 'Partial'): ?>
                                        <a href="gr_receive.php?po_id=<?php echo $row['po_id']; ?>" class="btn btn-primary btn-sm me-1" title="ดำเนินการรับของ">
                                            <i class="bi bi-truck"></i> รับของ
                                        </a>
                                        <a href="po_print.php?po_id=<?php echo $row['po_id']; ?>" target="_blank" class="btn btn-info btn-sm" title="พิมพ์ PO">
                                            <i class="bi bi-printer-fill"></i>
                                        </a>
                                    <?php 
                                    // (สถานะ: รับครบแล้ว)
                                    elseif ($row['status'] == 'Completed'): ?>
                                        <span class="text-success small me-2">รับครบแล้ว</span>
                                        <a href="po_print.php?po_id=<?php echo $row['po_id']; ?>" target="_blank" class="btn btn-info btn-sm" title="พิมพ์ PO">
                                            <i class="bi bi-printer-fill"></i>
                                        </a>
                                    <?php 
                                    // (สถานะ: รอดำเนินการอนุมัติ)
                                    elseif ($row['status'] == 'Pending PO Approval'): ?>
                                        <span class="text-muted small">รอดำเนินการอนุมัติ</span>
                                    <?php 
                                    // (สถานะ: ถูกปฏิเสธ)
                                    elseif ($row['status'] == 'PO Rejected'): ?>
                                        <span class="text-danger small">ถูกปฏิเสธ</span>
                                    <?php 
                                    // (สถานะ: ยกเลิก)
                                    else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">ไม่พบรายการใบสั่งซื้อ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>