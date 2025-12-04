<?php
require_once '../includes/header.php';

// --- 0. ตรวจสอบสิทธิ์ (ทีมพัสดุ) ---
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// --- ตรวจสอบ Alert Message ---
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// --- 1. ดึงข้อมูล PR ---
$result = null; 
$sql = "SELECT
            pr.id,
            pr.pr_number,
            pr.request_date,
            pr.department,
            u.full_name AS requester_name,
            pr.status,
            po.id AS po_id 
        FROM purchase_requisitions pr
        JOIN users u ON pr.requested_by_user_id = u.id
        LEFT JOIN purchase_orders po ON pr.id = po.pr_id 
        WHERE pr.status IN ('Pending WH Approval', 'Approved', 'WH Rejected', 'PO Created', 'Cancelled')
        ORDER BY
            CASE pr.status
                WHEN 'Pending WH Approval' THEN 1
                WHEN 'Approved' THEN 2
                WHEN 'PO Created' THEN 3
                WHEN 'WH Rejected' THEN 4
                WHEN 'Cancelled' THEN 5
                ELSE 99
            END ASC,
            pr.request_date ASC";

$query_result = $conn->query($sql);
if ($query_result === false) {
    $message = "Database Query Error: " . $conn->error; $message_type = "danger";
} else {
    $result = $query_result; 
}

?>

<div class="d-flex align-items-center mb-4">
    <div class="icon-box bg-gradient-blue me-3" style="width: 50px; height: 50px; border-radius: 14px; font-size: 1.5rem; display:flex; align-items:center; justify-content:center; color:white; box-shadow: 0 4px 10px rgba(0,122,255,0.3);">
        <i class="bi bi-file-earmark-text"></i>
    </div>
    <div>
        <h2 class="fw-bold mb-0" style="color: #1C1C1E;">รายการใบขอซื้อ (PR)</h2>
        <p class="text-muted mb-0">ตรวจสอบและอนุมัติคำขอซื้อ</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-uppercase text-secondary" style="font-size: 0.85rem;">เลขที่ PR</th>
                        <th class="text-uppercase text-secondary" style="font-size: 0.85rem;">วันที่ขอ</th>
                        <th class="text-uppercase text-secondary" style="font-size: 0.85rem;">ผู้ขอ</th>
                        <th class="text-uppercase text-secondary" style="font-size: 0.85rem;">สถานะ</th>
                        <th class="text-center pe-4 text-uppercase text-secondary" style="font-size: 0.85rem;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['pr_number']); ?></td>
                                <td class="text-secondary"><?php echo date('d/m/Y', strtotime($row['request_date'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 30px; height: 30px; font-size: 0.8rem; color: #555;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <?php echo htmlspecialchars($row['requester_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['status'];
                                    $badge_class = 'bg-secondary';
                                    if ($status == 'Pending WH Approval') $badge_class = 'bg-warning text-dark';
                                    if ($status == 'Approved') $badge_class = 'bg-info text-white';
                                    if ($status == 'PO Created') $badge_class = 'bg-success text-white';
                                    if ($status == 'WH Rejected' || $status == 'Cancelled') $badge_class = 'bg-danger text-white';
                                    echo "<span class='badge {$badge_class} rounded-pill px-3 py-2'>{$status}</span>";
                                    ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-inline-flex align-items-center gap-2">
                                        
                                        <?php 
                                        // 1. สถานะ: รออนุมัติ (Manager: อนุมัติ/ปฏิเสธ, Staff: ยกเลิก)
                                        if ($row['status'] == 'Pending WH Approval'): 
                                            if (hasRole(['ADMIN', 'WH_MANAGER'])): ?>
                                                <form action="pr_approve_process.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="pr_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm" title="อนุมัติ"><i class="bi bi-check-lg"></i></button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm" title="ปฏิเสธ"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form action="pr_cancel_process.php" method="POST" class="d-inline" onsubmit="return confirmCancelPR('<?php echo $row['pr_number']; ?>')">
                                                <input type="hidden" name="pr_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-light text-danger btn-sm rounded-circle border shadow-sm" style="width:32px;height:32px;padding:0;" title="ยกเลิก"><i class="bi bi-trash"></i></button>
                                            </form>
                                        
                                        <?php 
                                        // 2. สถานะ: อนุมัติแล้ว (Staff: เทียบราคา/ยกเลิก)
                                        elseif ($row['status'] == 'Approved' && hasRole(['ADMIN', 'WH_STAFF'])): ?>
                                            <a href="pr_compare.php?pr_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                                <i class="bi bi-bar-chart-fill me-1"></i> เทียบราคา
                                            </a>
                                            <form action="pr_cancel_process.php" method="POST" class="d-inline" onsubmit="return confirmCancelPR('<?php echo $row['pr_number']; ?>')">
                                                <input type="hidden" name="pr_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-light text-danger btn-sm rounded-circle border shadow-sm" style="width:32px;height:32px;padding:0;" title="ยกเลิก"><i class="bi bi-trash"></i></button>
                                            </form>
                                            
                                        <?php 
                                        // 3. สถานะ: สร้าง PO แล้ว (ดู PDF)
                                        elseif ($row['status'] == 'PO Created' && !empty($row['po_id'])): ?>
                                            <a href="po_print.php?po_id=<?php echo $row['po_id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                <i class="bi bi-printer-fill me-1"></i> พิมพ์ PO
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-light text-secondary btn-sm rounded-circle border shadow-sm pr-detail-btn" 
                                                style="width: 32px; height: 32px; padding: 0;"
                                                title="ดูรายละเอียด"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#prDetailModal" 
                                                data-pr-id="<?php echo $row['id']; ?>">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">ไม่พบรายการขอซื้อ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div> 
    </div> 
</div> 


<div class="modal fade" id="prDetailModal" tabindex="-1" aria-labelledby="prDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="prDetailModalLabel">รายละเอียดใบขอซื้อ (PR)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-4" id="pr-modal-content">
                
                <div id="pr-status-flow" class="mb-4 px-2"></div>
                
                <div class="card bg-light border-0 rounded-3 mb-4">
                    <div class="card-body" id="pr-header-details">
                        </div>
                </div>

                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-box-seam me-2"></i>รายการวัสดุ</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light" id="pr-item-table-head">
                            </thead>
                        <tbody id="pr-item-table-body">
                            </tbody>
                        <tfoot id="pr-item-table-foot">
                            </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>


<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>

<script>
function confirmCancelPR(prNumber) {
    return confirm(`คุณแน่ใจหรือไม่ว่าต้องการ "ยกเลิก" PR เลขที่: ${prNumber} ?`);
}

document.addEventListener("DOMContentLoaded", function() {
    
    // Modal Logic
    const prDetailModal = document.getElementById('prDetailModal');
    if (prDetailModal) {
        
        prDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const prId = button.getAttribute('data-pr-id');
            
            const modalTitle = prDetailModal.querySelector('.modal-title');
            const statusFlowContainer = prDetailModal.querySelector('#pr-status-flow');
            const headerDetailsContainer = prDetailModal.querySelector('#pr-header-details');
            
            const tableHead = prDetailModal.querySelector('#pr-item-table-head');
            const itemTableBody = prDetailModal.querySelector('#pr-item-table-body');
            const tableFoot = prDetailModal.querySelector('#pr-item-table-foot');

            // Clear & Loading
            statusFlowContainer.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
            headerDetailsContainer.innerHTML = '';
            tableHead.innerHTML = '';
            itemTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">กำลังโหลดข้อมูล...</td></tr>';
            tableFoot.innerHTML = '';

            // Fetch API
            fetch(`api_get_pr_details.php?pr_id=${prId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    const pr = data.header;
                    const po = data.po_info;
                    
                    modalTitle.textContent = `PR: ${pr.pr_number}`;
                    
                    // 1. Status Flow
                    statusFlowContainer.innerHTML = createStatusFlow(pr.status, po ? po.status : null);
                    
                    // 2. Header Details
                    headerDetailsContainer.innerHTML = `
                        <div class="row g-2">
                            <div class="col-md-6"><strong>ผู้ขอ:</strong> ${pr.requester_name}</div>
                            <div class="col-md-6"><strong>วันที่ขอ:</strong> ${new Date(pr.request_date).toLocaleDateString('th-TH')}</div>
                            <div class="col-12"><strong>เหตุผล:</strong> ${pr.reason || '-'}</div>
                            ${po ? `<div class="col-12 text-success mt-2"><i class="bi bi-link-45deg"></i> <strong>PO:</strong> ${po.po_number} <span class="badge bg-success ms-1">${po.status}</span></div>` : ''}
                        </div>
                    `;
                    
                    // 3. Items Table
                    itemTableBody.innerHTML = '';
                    
                    if (data.items.length > 0) {
                        // Check if it has price (PO Created)
                        if (data.items[0].unit_price !== undefined) {
                            tableHead.innerHTML = `<tr><th>รหัส</th><th>ชื่อวัสดุ</th><th class="text-end">จำนวน</th><th class="text-end">หน่วยละ</th><th class="text-end">รวม</th></tr>`;
                            
                            let grandTotal = 0;
                            data.items.forEach(item => {
                                const qty = parseFloat(item.quantity_ordered);
                                const price = parseFloat(item.unit_price);
                                const total = qty * price;
                                grandTotal += total;
                                
                                itemTableBody.innerHTML += `
                                    <tr>
                                        <td><span class="badge bg-light text-dark border">${item.item_code}</span></td>
                                        <td>${item.name}</td>
                                        <td class="text-end">${qty.toLocaleString(undefined, {minimumFractionDigits:2})} ${item.unit}</td>
                                        <td class="text-end">${price.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                                        <td class="text-end fw-bold">${total.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                                    </tr>`;
                            });
                            tableFoot.innerHTML = `<tr class="table-light"><td colspan="4" class="text-end fw-bold">ยอดรวมสุทธิ</td><td class="text-end fw-bold text-primary">${grandTotal.toLocaleString(undefined, {minimumFractionDigits:2})}</td></tr>`;
                            
                        } else {
                            // No Price (Pending)
                            tableHead.innerHTML = `<tr><th>รหัส</th><th>ชื่อวัสดุ</th><th class="text-end">จำนวนขอ</th><th>หน่วย</th></tr>`;
                            data.items.forEach(item => {
                                itemTableBody.innerHTML += `
                                    <tr>
                                        <td><span class="badge bg-light text-dark border">${item.item_code}</span></td>
                                        <td>${item.name}</td>
                                        <td class="text-end">${parseFloat(item.quantity_requested).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                                        <td>${item.unit}</td>
                                    </tr>`;
                            });
                        }
                    } else {
                        itemTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ไม่พบรายการวัสดุ</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusFlowContainer.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                });
        });
    }

    // Status Flow Function
    function createStatusFlow(prStatus, poStatus) {
        let steps = [
            { label: 'สร้าง PR', status: 'Pending WH Approval' },
            { label: 'อนุมัติ PR', status: 'Approved' },
            { label: 'เลือก Supplier', status: 'PO Created' },
            { label: 'อนุมัติ PO', status: 'Pending PO Approval' },
            { label: 'รอรับของ', status: 'Pending' }
        ];
        
        let currentState = prStatus;
        if (prStatus === 'PO Created' && poStatus) currentState = poStatus;
        
        let activeIndex = -1;
        if (prStatus === 'WH Rejected' || prStatus === 'Cancelled') activeIndex = 0;
        else {
            activeIndex = steps.findIndex(s => s.status === currentState);
            if (activeIndex === -1 && ['Issued', 'Partial', 'Completed'].includes(currentState)) activeIndex = 4;
        }
        
        let html = '<div class="progress-container d-flex justify-content-between">';
        steps.forEach((step, index) => {
            let cls = 'step-item';
            if (index < activeIndex) cls += ' completed';
            else if (index === activeIndex) cls += ' active';
            
            if ((prStatus === 'WH Rejected' || prStatus === 'Cancelled') && index === 0) cls += ' rejected';
            if (poStatus === 'PO Rejected' && index === 3) cls += ' rejected';

            html += `<div class="${cls}"><div class="step-circle">${index + 1}</div><div class="step-label">${step.label}</div></div>`;
            if (index < steps.length - 1) html += '<div class="step-connector"></div>';
        });
        html += '</div>';
        return html;
    }
});
</script>