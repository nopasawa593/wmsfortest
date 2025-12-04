<?php 
require_once '../includes/header.php'; 

// --- 0. ตรวจสอบสิทธิ์การเข้าถึงเบื้องต้น ---
// (ต้อง Login แล้วเท่านั้น)
if (!isset($_SESSION['user_id'])) {
    die("Access Denied: Please login first.");
}

// --- 1. รับ ID ใบเบิก ---
if (!isset($_GET['req_id']) || empty($_GET['req_id'])) {
    die("ไม่พบ ID ใบเบิกที่ต้องการดู");
}
$req_id = (int)$_GET['req_id'];

// --- 2. ดึงข้อมูล MR Header พร้อมตรวจสอบ Department ID ของผู้ขอเบิก ---
// ⭐️ (สำคัญ) เพิ่ม u_req.department_id เพื่อใช้ตรวจสอบสิทธิ์
$stmt_header = $conn->prepare("SELECT 
                                    r.*, 
                                    u_req.full_name AS requester_name,
                                    u_req.department_id AS req_dept_id, 
                                    u_app.full_name AS approver_name
                                FROM requisitions r
                                JOIN users u_req ON r.requested_by_user_id = u_req.id
                                LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
                                WHERE r.id = ?");
$stmt_header->bind_param("i", $req_id);
$stmt_header->execute();
$header_result = $stmt_header->get_result();

if ($header_result->num_rows === 0) {
    die("ไม่พบใบเบิกเลขที่ #$req_id");
}
$mr_header = $header_result->fetch_assoc();
$stmt_header->close();

// --- ⭐️ 3. Security Check (IDOR Prevention) ⭐️ ---
// ป้องกันการแก้ URL เพื่อดูใบเบิกของแผนกอื่น
// กฎ: Admin และ ทีมพัสดุ ดูได้ทั้งหมด / แผนกอื่นดูได้เฉพาะของแผนกตัวเอง
$is_warehouse_or_admin = hasRole(['ADMIN', 'WH_MANAGER', 'WH_STAFF']);
$my_dept_id = $_SESSION['department_id'] ?? 0;
$req_dept_id = $mr_header['req_dept_id'];

if (!$is_warehouse_or_admin) {
    // ถ้าเป็น DEPT_STAFF หรือ DEPT_MANAGER
    if ($req_dept_id != $my_dept_id) {
        // ถ้าแผนกของใบเบิก ไม่ตรงกับ แผนกของผู้ใช้ปัจจุบัน
        die("<div class='alert alert-danger m-4'>Access Denied: คุณไม่มีสิทธิ์ดูใบเบิกของต่างแผนก</div>");
    }
}

// --- 4. ดึงรายการวัสดุ (MR Items) ---
$stmt_items = $conn->prepare("SELECT 
                                ri.quantity_requested,
                                ri.quantity_issued,
                                m.item_code,
                                m.name,
                                m.unit
                            FROM requisition_items ri
                            JOIN materials m ON ri.material_id = m.id
                            WHERE ri.requisition_id = ?");
$stmt_items->bind_param("i", $req_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();


// Helper function สำหรับแสดง Badge สถานะ
function displayStatusBadge($status) {
    $badge_class = 'bg-secondary';
    if ($status == 'Pending Dept Approval') $badge_class = 'bg-warning text-dark';
    if ($status == 'Pending Issue') $badge_class = 'bg-primary';
    if ($status == 'Issued') $badge_class = 'bg-success';
    if ($status == 'Dept Rejected') $badge_class = 'bg-danger';
    if ($status == 'WH Cancelled') $badge_class = 'bg-danger'; // เพิ่มสถานะยกเลิก
    return "<span class='badge {$badge_class}'>{$status}</span>";
}
?>

<h1 class="mb-4">รายละเอียดใบเบิกวัสดุ (MR: <?php echo htmlspecialchars($mr_header['mr_number']); ?>)</h1>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>ข้อมูลหลัก</h5>
            </div>
            <div class="card-body">
                <p><strong>สถานะปัจจุบัน:</strong> <?php echo displayStatusBadge($mr_header['status']); ?></p>
                <p><strong>ผู้ขอเบิก:</strong> <?php echo htmlspecialchars($mr_header['requester_name']); ?></p>
                <p><strong>แผนก:</strong> <?php echo htmlspecialchars($mr_header['department']); ?></p>
                <p><strong>วันที่ขอเบิก:</strong> <?php echo date('d/m/Y', strtotime($mr_header['request_date'])); ?></p>
                <p><strong>ผู้อนุมัติ/ผู้จ่าย:</strong> <?php echo htmlspecialchars($mr_header['approver_name'] ?? 'รอดำเนินการ'); ?></p>
                
                <?php if ($mr_header['approval_date']): ?>
                    <p><strong>วันที่ดำเนินการล่าสุด:</strong> <?php echo date('d/m/Y', strtotime($mr_header['approval_date'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <a href="javascript:history.back()" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>รายการวัสดุที่ขอเบิก</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสวัสดุ</th>
                                <th>ชื่อวัสดุ</th>
                                <th class="text-end">ขอเบิก</th>
                                <th class="text-end">จ่ายจริง</th>
                                <th>หน่วย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items_result->num_rows > 0): ?>
                                <?php while($item = $items_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format($item['quantity_requested'], 2); ?></td>
                                        <td class="text-end text-success"><?php echo number_format($item['quantity_issued'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">ไม่พบรายการวัสดุในใบเบิกนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$stmt_items->close();
$conn->close();
require_once '../includes/footer.php'; 
?>