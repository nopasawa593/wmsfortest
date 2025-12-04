<?php 
require_once '../includes/header.php'; 

// --- 0. ตรวจสอบสิทธิ์ (ทีมพัสดุ) ---
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// --- 1. ดึงข้อมูล MR ที่เกี่ยวข้องกับคลัง ---
$sql = "SELECT 
            r.id,
            r.mr_number, 
            r.request_date, 
            r.department, 
            r.status,
            u_req.full_name AS requester_name,
            u_app.full_name AS approver_name
        FROM requisitions r
        JOIN users u_req ON r.requested_by_user_id = u_req.id
        LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
        WHERE r.status IN ('Pending Issue', 'Issued', 'Dept Rejected', 'WH Cancelled') -- (เพิ่ม WH Cancelled)
        ORDER BY 
            CASE r.status 
                WHEN 'Pending Issue' THEN 1
                WHEN 'Issued' THEN 2
                WHEN 'Dept Rejected' THEN 3
                WHEN 'WH Cancelled' THEN 4
            END, 
            r.request_date DESC";
$result = $conn->query($sql);

// --- ตรวจสอบ Alert Message จาก Session (ถ้ามี) ---
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-box-arrow-right me-2"></i> จ่ายวัสดุ (GI) / รายการเบิก (MR)</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">รายการเบิกทั้งหมด (สำหรับแผนกพัสดุ)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ใบเบิก</th>
                        <th>วันที่ขอเบิก</th>
                        <th>ผู้ขอเบิก</th>
                        <th>แผนก</th>
                        <th>สถานะ (Status)</th>
                        <th class="text-center">จัดการ (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['mr_number']); ?></td>
                                <td><?php echo $row['request_date']; ?></td>
                                <td><?php echo htmlspecialchars($row['requester_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td>
                                    <?php 
                                    $status = $row['status'];
                                    $badge_class = 'bg-secondary';
                                    if ($status == 'Pending Issue') $badge_class = 'bg-primary';
                                    if ($status == 'Issued') $badge_class = 'bg-success';
                                    if ($status == 'Dept Rejected') $badge_class = 'bg-danger';
                                    if ($status == 'WH Cancelled') $badge_class = 'bg-dark'; // (เพิ่ม)
                                    echo "<span class='badge {$badge_class}'>{$status}</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['status'] == 'Pending Issue'): ?>
                                        <a href="gi_issue.php?req_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm me-1" title="ดำเนินการจ่าย">
                                            <i class="bi bi-truck"></i> ดำเนินการจ่าย
                                        </a>
                                        
                                        <form action="mr_cancel_process.php" method="POST" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการ \'ยกเลิก\' ใบเบิกนี้? สต็อกที่จองไว้จะถูกคืนเข้าระบบ');">
                                            <input type="hidden" name="mr_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="ยกเลิกใบเบิก (คืนสต็อก)">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                        
                                    <?php elseif ($row['status'] == 'Issued'): ?>
                                        <span class="text-success small">จ่ายของแล้ว</span>
                                    <?php elseif ($row['status'] == 'WH Cancelled'): ?>
                                        <span class="text-dark small">ยกเลิกโดยคลัง</span>
                                    <?php else: // (Dept Rejected) ?>
                                        <span class="text-danger small">ถูกปฏิเสธโดยแผนก</span>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-info btn-sm ms-1 mr-detail-btn" 
                                            title="ดูรายละเอียด"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#mrDetailModal"
                                            data-req-id="<?php echo $row['id']; ?>">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ไม่พบรายการใบเบิก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="mrDetailModal" tabindex="-1" aria-labelledby="mrDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mrDetailModalLabel">รายละเอียดใบเบิก (MR)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="mr-modal-content">
                <div id="mr-status-flow" class="mb-4">
                    </div>
                
                <div id="mr-header-details" class="mb-3">
                    </div>

                <h5 class="mb-3">รายการวัสดุ</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสวัสดุ</th>
                                <th>ชื่อวัสดุ</th>
                                <th class="text-end">จำนวนขอเบิก</th>
                                <th class="text-end">จำนวนจ่ายจริง</th>
                                <th>หน่วย</th>
                            </tr>
                        </thead>
                        <tbody id="mr-item-table-body">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>
<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const mrDetailModal = document.getElementById('mrDetailModal');
    if (mrDetailModal) {
        
        mrDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const reqId = button.getAttribute('data-req-id');
            
            const modalTitle = mrDetailModal.querySelector('.modal-title');
            const statusFlowContainer = mrDetailModal.querySelector('#mr-status-flow');
            const headerDetailsContainer = mrDetailModal.querySelector('#mr-header-details');
            const itemTableBody = mrDetailModal.querySelector('#mr-item-table-body');

            // (1. เคลียร์ค่าเก่าและแสดง Loading)
            modalTitle.textContent = 'กำลังโหลด...';
            statusFlowContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            headerDetailsContainer.innerHTML = '';
            itemTableBody.innerHTML = '<tr><td colspan="5" class="text-center">กำลังโหลด...</td></tr>';

            // (2. เรียก API - ⭐️ แก้ไข Path เป็น ../user/ ⭐️)
            fetch(`../user/api_get_mr_details.php?req_id=${reqId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    const mr = data.header;
                    
                    modalTitle.textContent = `รายละเอียดใบเบิก (MR): ${mr.mr_number}`;
                    
                    // 3.1 สร้าง Status Bar Flow
                    statusFlowContainer.innerHTML = createMRStatusFlow(mr.status);
                    
                    // 3.2 สร้าง Header Details
                    headerDetailsContainer.innerHTML = `
                        <p class="mb-1"><strong>ผู้ขอเบิก:</strong> ${mr.requester_name}</p>
                        <p class="mb-1"><strong>แผนก:</strong> ${mr.department}</p>
                        <p class="mb-1"><strong>วันที่ขอเบิก:</strong> ${mr.request_date}</p>
                    `;
                    
                    // 3.3 สร้างตาราง Items (พร้อมยอดจ่ายจริง)
                    itemTableBody.innerHTML = ''; 
                    if (data.items.length > 0) {
                        data.items.forEach(item => {
                            itemTableBody.innerHTML += `
                                <tr>
                                    <td>${item.item_code}</td>
                                    <td>${item.name}</td>
                                    <td class="text-end">${parseFloat(item.quantity_requested).toFixed(2)}</td>
                                    <td class="text-end fw-bold text-success">${parseFloat(item.quantity_issued).toFixed(2)}</td>
                                    <td>${item.unit}</td>
                                </tr>
                            `;
                        });
                    } else {
                        itemTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ไม่พบรายการวัสดุ</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    modalTitle.textContent = 'เกิดข้อผิดพลาด';
                    statusFlowContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                });
        });
    }

    // (ฟังก์ชันสร้าง Status Bar Flow สำหรับ MR)
    function createMRStatusFlow(mrStatus) {
        let steps = [
            { id: 'step1', label: 'สร้าง MR', status: 'Pending Dept Approval' },
            { id: 'step2', label: 'รอพัสดุจ่าย', status: 'Pending Issue' },
            { id: 'step3', label: 'จ่ายแล้ว', status: 'Issued' }
        ];
        
        let activeIndex = -1;
        if (mrStatus === 'Dept Rejected' || mrStatus === 'WH Cancelled') {
            activeIndex = 0; 
        } else {
            activeIndex = steps.findIndex(step => step.status === mrStatus);
            if (activeIndex === -1 && mrStatus === 'Issued') {
                 activeIndex = 2;
            }
        }
        
        let html = '<div class="progress-container d-flex justify-content-between">';
        
        steps.forEach((step, index) => {
            let stepClass = 'step-item';
            if (index < activeIndex) {
                stepClass += ' completed'; 
            } else if (index === activeIndex) {
                stepClass += ' active'; 
            }
            
            if ((mrStatus === 'Dept Rejected' || mrStatus === 'WH Cancelled') && index === 0) {
                stepClass += ' rejected';
            }

            html += `<div class="${stepClass}">
                        <div class="step-circle">${index + 1}</div>
                        <div class="step-label">${step.label}</div>
                     </div>`;
            if (index < steps.length - 1) {
                html += '<div class="step-connector"></div>';
            }
        });
        
        html += '</div>';
        return html;
    }
});
</script>

<style>
    .progress-container { align-items: flex-start; text-align: center; }
    .step-item { color: #adb5bd; flex: 1; position: relative; }
    .step-circle { width: 30px; height: 30px; line-height: 28px; border-radius: 50%; background-color: #fff; border: 2px solid #adb5bd; display: inline-block; font-weight: bold; }
    .step-label { font-size: 0.8rem; margin-top: 5px; }
    .step-connector { flex-grow: 1; height: 2px; background-color: #adb5bd; position: relative; top: 15px; margin: 0 -10px; }
    .step-item.active .step-circle { border-color: var(--theme-blue); background-color: var(--theme-blue); color: white; }
    .step-item.active .step-label { color: var(--theme-blue); font-weight: bold; }
    .step-item.completed .step-circle { border-color: var(--bs-success); background-color: var(--bs-success); color: white; }
    .step-item.completed .step-label { color: #212529; }
    .step-item.completed + .step-connector { background-color: var(--bs-success); }
    .step-item.rejected .step-circle { border-color: var(--bs-danger); background-color: var(--bs-danger); color: white; }
    .step-item.rejected .step-label { color: var(--bs-danger); font-weight: bold; }
</style>