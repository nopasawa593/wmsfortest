<?php 
require_once '../includes/header.php'; 

// --- 1. ตรวจสอบสิทธิ์ (Access Control) ---
// อนุญาตให้ทุก Role ที่ใช้งานระบบนี้เข้าได้ (เพราะหน้านี้ทำงานแบบ Hybrid)
$allowed_roles = ['ADMIN', 'WH_MANAGER', 'WH_STAFF', 'DEPT_MANAGER', 'DEPT_STAFF'];

// เรียกฟังก์ชัน hasRole() จาก header.php
if (!hasRole($allowed_roles)) {
    // แสดง Error สวยๆ แทนการ die() ดื้อๆ
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm border-0'>";
    echo "<h4 class='alert-heading'><i class='bi bi-shield-lock-fill'></i> Access Denied</h4>";
    echo "<p>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (Role: " . ($_SESSION['role'] ?? 'Unknown') . ")</p>";
    echo "</div></div>";
    require_once '../includes/footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$is_warehouse_team = hasRole(['ADMIN', 'WH_MANAGER', 'WH_STAFF']);

// --- 2. เตรียม Query ข้อมูล (Data Fetching) ---
if ($is_warehouse_team) {
    // 🅰️ กรณีเป็นทีมคลังสินค้า (Warehouse): ดูรายการ "รอจ่าย (GI)"
    $page_title = '<i class="bi bi-box-seam-fill me-2"></i> รายการรอจ่ายวัสดุ (GI Worklist)';
    $page_desc = 'รายการใบเบิกที่ผ่านการอนุมัติแล้ว และรอการจ่ายของออกจากคลัง';
    
    // ดึงเฉพาะสถานะ Pending Issue เรียงตามวันที่เก่าสุดขึ้นก่อน (FIFO)
    $sql = "SELECT 
                r.id AS req_id,
                r.mr_number, 
                r.request_date, 
                r.department, 
                r.status,
                u_req.full_name AS person_name
            FROM requisitions r 
            JOIN users u_req ON r.requested_by_user_id = u_req.id
            WHERE r.status = 'Pending Issue' 
            ORDER BY r.request_date ASC";
    $stmt = $conn->prepare($sql);

} else {
    // 🅱️ กรณีเป็นแผนกอื่น (Department): ดู "ประวัติการเบิกของฉัน"
    $page_title = '<i class="bi bi-clock-history me-2"></i> ประวัติการเบิกวัสดุ';
    $page_desc = 'รายการใบเบิกวัสดุทั้งหมดของคุณและสถานะปัจจุบัน';

    // ดึงของตัวเองทั้งหมด เรียงใหม่สุดขึ้นก่อน
    $sql = "SELECT 
                r.id AS req_id,
                r.mr_number, 
                r.request_date, 
                r.department, 
                r.status,
                u_app.full_name AS person_name -- ชื่อคนอนุมัติ
            FROM requisitions r 
            JOIN users u_req ON r.requested_by_user_id = u_req.id
            LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
            WHERE r.requested_by_user_id = ? 
            ORDER BY r.request_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

// --- 3. จัดการ Flash Message ---
$msg_html = "";
if (isset($_SESSION['alert_message'])) {
    $type = $_SESSION['alert_type'] ?? 'info';
    $msg = $_SESSION['alert_message'];
    $msg_html = "<div class='alert alert-{$type} alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4' role='alert'>
                    <i class='bi bi-info-circle-fill me-2'></i> {$msg}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                 </div>";
    unset($_SESSION['alert_message'], $_SESSION['alert_type']);
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold mb-1 text-dark"><?php echo $page_title; ?></h2>
        <p class="text-muted small mb-0"><?php echo $page_desc; ?></p>
    </div>
    
    <?php if (!$is_warehouse_team): ?>
        <a href="requisition_create.php" class="btn btn-primary shadow-sm rounded-pill px-4 hover-scale">
            <i class="bi bi-plus-lg me-1"></i> สร้างใบเบิกใหม่
        </a>
    <?php endif; ?>
</div>

<?php echo $msg_html; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold">เลขที่ใบเบิก</th>
                        <th class="py-3 text-secondary text-uppercase small fw-bold">วันที่</th>
                        <th class="py-3 text-secondary text-uppercase small fw-bold">แผนก</th>
                        
                        <?php if ($is_warehouse_team): ?>
                            <th class="py-3 text-secondary text-uppercase small fw-bold">ผู้ขอเบิก</th>
                        <?php else: ?>
                            <th class="py-3 text-secondary text-uppercase small fw-bold">ผู้อนุมัติ/ผู้จ่าย</th>
                        <?php endif; ?>

                        <th class="py-3 text-secondary text-uppercase small fw-bold">สถานะ</th>
                        <th class="py-3 text-secondary text-uppercase small fw-bold text-center pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <i class="bi bi-file-earmark-text me-1 text-muted"></i>
                                    <?php echo htmlspecialchars($row['mr_number']); ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($row['request_date'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['department']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['person_name'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                        $st = $row['status'];
                                        $badge = 'bg-secondary';
                                        $text = $st;
                                        
                                        if ($st == 'Pending Dept Approval') { $badge = 'bg-warning text-dark'; $text = 'รอหัวหน้าอนุมัติ'; }
                                        elseif ($st == 'Pending Issue') { $badge = 'bg-info text-dark'; $text = 'รอพัสดุจ่าย'; }
                                        elseif ($st == 'Issued') { $badge = 'bg-success'; $text = 'จ่ายแล้ว (สำเร็จ)'; }
                                        elseif ($st == 'Dept Rejected') { $badge = 'bg-danger'; $text = 'ไม่อนุมัติ'; }
                                        elseif ($st == 'WH Cancelled') { $badge = 'bg-danger'; $text = 'ถูกยกเลิก'; }
                                        
                                        echo "<span class='badge {$badge} rounded-pill px-3'>{$text}</span>";
                                    ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-light btn-sm border text-secondary mr-detail-btn hover-shadow"
                                                title="ดูรายละเอียด"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#mrDetailModal"
                                                data-req-id="<?php echo $row['req_id']; ?>">
                                            <i class="bi bi-search"></i>
                                        </button>

                                        <?php if ($is_warehouse_team): ?>
                                            <?php if ($row['status'] == 'Pending Issue'): ?>
                                                <a href="gi_issue.php?req_id=<?php echo $row['req_id']; ?>" 
                                                   class="btn btn-primary btn-sm ms-1 shadow-sm hover-scale" 
                                                   title="ไปหน้าตัดจ่ายสต็อก">
                                                    <i class="bi bi-box-seam me-1"></i> จ่าย
                                                </a>
                                            <?php endif; ?>
                                        
                                        <?php else: ?>
                                            <?php if (in_array($row['status'], ['Pending Issue', 'Issued'])): ?>
                                                <a href="mr_print.php?req_id=<?php echo $row['req_id']; ?>" 
                                                   class="btn btn-light btn-sm border text-dark ms-1 hover-shadow" 
                                                   target="_blank" 
                                                   title="พิมพ์เอกสาร">
                                                    <i class="bi bi-printer-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 opacity-25 d-block mb-2"></i>
                                <p class="mb-0">ไม่พบรายการข้อมูล</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="mrDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-file-text me-2"></i>รายละเอียดใบเบิก</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4" id="mr-modal-content">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small">กำลังโหลดข้อมูล...</p>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-scale:hover { transform: scale(1.05); transition: 0.2s; }
    .hover-shadow:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: 0.2s; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const mrModal = document.getElementById('mrDetailModal');
    if (mrModal) {
        mrModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const reqId = button.getAttribute('data-req-id');
            const modalBody = mrModal.querySelector('#mr-modal-content');

            // เรียก API ดึงข้อมูล
            fetch(`api_get_mr_details.php?req_id=${reqId}`)
                .then(response => {
                    if(!response.ok) throw new Error("Network response was not ok");
                    return response.json();
                })
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    const mr = data.header;
                    
                    let statusBadge = `<span class="badge bg-secondary">${mr.status}</span>`;
                    if(mr.status === 'Pending Issue') statusBadge = `<span class="badge bg-info text-dark">รอพัสดุจ่าย</span>`;
                    else if(mr.status === 'Issued') statusBadge = `<span class="badge bg-success">จ่ายแล้ว</span>`;
                    
                    let html = `
                        <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <small class="text-muted text-uppercase fw-bold">เลขที่เอกสาร</small>
                                    <div class="fs-5 fw-bold text-primary">${mr.mr_number}</div>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <small class="text-muted text-uppercase fw-bold">สถานะ</small>
                                    <div>${statusBadge}</div>
                                </div>
                                <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>
                                <div class="col-md-6">
                                    <span class="text-muted"><i class="bi bi-person me-1"></i> ผู้ขอ:</span> 
                                    <strong>${mr.requester_name}</strong>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <span class="text-muted"><i class="bi bi-calendar3 me-1"></i> วันที่:</span> 
                                    <strong>${new Date(mr.request_date).toLocaleDateString('th-TH')}</strong>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mb-3 border-start border-4 border-primary ps-3">รายการวัสดุที่ขอเบิก</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="text-secondary small text-uppercase">
                                        <th class="text-center" style="width: 50px;">#</th>
                                        <th style="width: 20%;">ที่เก็บ (Loc)</th>
                                        <th>สินค้า</th>
                                        <th class="text-end" style="width: 15%;">จำนวนขอ</th>
                                        <th class="text-center" style="width: 10%;">หน่วย</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    
                    if (data.items.length > 0) {
                        data.items.forEach((item, index) => {
                            // ⭐️ แสดง Location (ถ้าไม่มีให้ขีด -)
                            let loc = item.location_code ? `<span class="badge bg-info text-dark font-monospace">${item.location_code}</span>` : '<span class="text-muted">-</span>';
                            
                            html += `<tr>
                                        <td class="text-center text-muted">${index + 1}</td>
                                        <td class="text-center">${loc}</td>
                                        <td>
                                            <div class="fw-bold">${item.name}</div>
                                            <div class="small text-muted code-font">${item.item_code}</div>
                                        </td>
                                        <td class="text-end fw-bold fs-6">${parseFloat(item.quantity_requested).toLocaleString()}</td>
                                        <td class="text-center text-muted small">${item.unit}</td>
                                     </tr>`;
                        });
                    } else {
                        html += `<tr><td colspan="5" class="text-center text-muted py-3">ไม่พบรายการสินค้า</td></tr>`;
                    }
                    html += `</tbody></table></div>`;
                    
                    // ส่วนท้าย แสดงผู้อนุมัติ/ผู้จ่าย
                    if(mr.approver_name) {
                        html += `<div class="mt-3 text-end text-muted small fst-italic">
                                    ดำเนินการล่าสุดโดย: ${mr.approver_name}
                                 </div>`;
                    }

                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    console.error("Error:", error);
                    modalBody.innerHTML = `<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-circle-fill me-2"></i> โหลดข้อมูลไม่สำเร็จ: ${error.message}</div>`;
                });
        });
    }
});
</script>

<?php 
$stmt->close();
$conn->close();
require_once '../includes/footer.php'; 
?>