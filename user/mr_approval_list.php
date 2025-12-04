<?php 
require_once '../includes/header.php'; 

// --- 0. ตรวจสอบสิทธิ์ ---
// (⭐️ แก้ไข Role) ให้ DEPT_MANAGER หรือ ADMIN เข้าได้
if (!hasRole('DEPT_MANAGER')) {
    die("Access Denied: คุณไม่มีสิทธิ์ในการอนุมัติใบเบิก");
}

// --- 1. ดึงข้อมูล ผจก. และ แผนก ---
$manager_id = $_SESSION['user_id'];
$manager_dept_id = $_SESSION['department_id']; 

if (empty($manager_dept_id)) {
     die("Access Denied: บัญชีของคุณไม่ได้ผูกกับแผนกใดๆ");
}

// --- 2. ดึงรายการเบิกที่ "รออนุมัติ" จาก "แผนกของฉัน" ---
$stmt = $conn->prepare("SELECT 
                            r.id AS req_id,
                            r.mr_number, 
                            r.request_date,
                            r.status,
                            u.full_name AS requester_name
                        FROM requisitions r
                        JOIN users u ON r.requested_by_user_id = u.id
                        WHERE u.department_id = ? 
                        AND r.status = 'Pending Dept Approval'
                        ORDER BY r.request_date ASC");
$stmt->bind_param("i", $manager_dept_id);
$stmt->execute();
$result = $stmt->get_result();

// --- (ส่วน Session Alert - เหมือนเดิม) ---
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}
?>

<h1 class="mb-4">อนุมัติใบเบิกวัสดุ (MR)</h1>
<h5 class="text-muted">สำหรับแผนกของคุณ</h5>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">รายการที่รออนุมัติ</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ใบเบิก</th>
                        <th>วันที่ขอเบิก</th>
                        <th>ผู้ขอเบิก (พนักงาน)</th>
                        <th>สถานะ</th>
                        <th class="text-center">ดำเนินการ (Approve/Reject)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['mr_number']); ?></td>
                                <td><?php echo $row['request_date']; ?></td>
                                <td><?php echo htmlspecialchars($row['requester_name']); ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark"><?php echo $row['status']; ?></span>
                                </td>
                                <td class="text-center">
                                    <form action="mr_approve_process.php" method="POST" class="d-inline">
                                        <input type="hidden" name="mr_id" value="<?php echo $row['req_id']; ?>">
                                        
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" title="อนุมัติ">
                                            <i class="bi bi-check-circle"></i> อนุมัติ
                                        </button>
                                        
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" title="ปฏิเสธ">
                                            <i class="bi bi-x-circle"></i> ปฏิเสธ
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-info btn-sm mr-detail-btn" 
                                            title="ดูรายละเอียด"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#mrDetailModal"
                                            data-req-id="<?php echo $row['req_id']; ?>">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">ไม่พบรายการที่รออนุมัติ</td>
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

                <h5 class="mb-3">รายการวัสดุที่ขอเบิก</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสวัสดุ</th>
                                <th>ชื่อวัสดุ</th>
                                <th class="text-end">จำนวนขอเบิก</th>
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
$stmt->close();
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
            
            // (ดึง Element ภายใน Modal)
            const modalTitle = mrDetailModal.querySelector('.modal-title');
            const statusFlowContainer = mrDetailModal.querySelector('#mr-status-flow');
            const headerDetailsContainer = mrDetailModal.querySelector('#mr-header-details');
            const itemTableBody = mrDetailModal.querySelector('#mr-item-table-body');

            // (1. เคลียร์ค่าเก่าและแสดง Loading)
            modalTitle.textContent = 'กำลังโหลด...';
            statusFlowContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            headerDetailsContainer.innerHTML = '';
            itemTableBody.innerHTML = '<tr><td colspan="4" class="text-center">กำลังโหลด...</td></tr>';

            // (2. เรียก API)
            fetch(`api_get_mr_details.php?req_id=${reqId}`)
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
                    
                    // 3.3 สร้างตาราง Items
                    itemTableBody.innerHTML = ''; // (เคลียร์)
                    if (data.items.length > 0) {
                        data.items.forEach(item => {
                            itemTableBody.innerHTML += `
                                <tr>
                                    <td>${item.item_code}</td>
                                    <td>${item.name}</td>
                                    <td class="text-end">${parseFloat(item.quantity_requested).toFixed(2)}</td>
                                    <td>${item.unit}</td>
                                </tr>
                            `;
                        });
                    } else {
                        itemTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">ไม่พบรายการวัสดุ</td></tr>';
                    }
                })
                .catch(error => {
                    // (4. หาก Error)
                    console.error('Fetch Error:', error);
                    modalTitle.textContent = 'เกิดข้อผิดพลาด';
                    statusFlowContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                });
        });
    }

    // ⭐️ (ฟังก์ชันสร้าง Status Bar Flow สำหรับ MR) ⭐️
    function createMRStatusFlow(mrStatus) {
        let steps = [
            { id: 'step1', label: 'สร้าง MR', status: 'Pending Dept Approval' },
            { id: 'step2', label: 'รอพัสดุจ่าย', status: 'Pending Issue' },
            { id: 'step3', label: 'จ่ายแล้ว', status: 'Issued' }
        ];
        
        // (หา Step ปัจจุบัน)
        let activeIndex = -1;
        if (mrStatus === 'Dept Rejected' || mrStatus === 'WH Cancelled') {
            activeIndex = 0; // (ถ้าถูก Reject/Cancel ให้ค้างที่ Step 1)
        } else {
            activeIndex = steps.findIndex(step => step.status === mrStatus);
            if (activeIndex === -1 && mrStatus === 'Issued') { // (กรณีจ่ายของแล้ว)
                 activeIndex = 2;
            }
        }
        
        let html = '<div class="progress-container d-flex justify-content-between">';
        
        steps.forEach((step, index) => {
            let stepClass = 'step-item';
            if (index < activeIndex) {
                stepClass += ' completed'; // (ผ่านแล้ว)
            } else if (index === activeIndex) {
                stepClass += ' active'; // (กำลังทำ)
            }
            
            // (จัดการกรณี Reject / Cancel)
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