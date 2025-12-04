<?php 
require_once '../includes/header.php'; 

// --- 1. ตรวจสอบสิทธิ์ (ต้องเป็นแผนกอื่น) ---
if (!hasRole(['DEPT_STAFF', 'DEPT_MANAGER'])) {
    // (ถ้าเป็นทีมพัสดุ ให้เด้งไป Dashboard ของ Admin)
    if (hasRole(['WH_STAFF', 'WH_MANAGER'])) {
        header("Location: ../admin/index.php");
        exit();
    }
    die("Access Denied.");
}

// --- 2. ดึง ID ของ User ที่ Login อยู่ ---
$user_id = $_SESSION['user_id'];

// --- 3. ⭐️ (แก้ไข SQL) ดึงข้อมูลใบเบิก "ทั้งหมด" ของ User คนนี้ (ไม่ใช่ 5 รายการ) ⭐️ ---
// (ยก Query มาจาก requisition_list.php)
$stmt = $conn->prepare("SELECT 
                            r.id AS req_id,
                            r.mr_number, 
                            r.request_date, 
                            r.department, 
                            r.status,
                            u_app.full_name AS approver_name
                        FROM requisitions r 
                        JOIN users u_req ON r.requested_by_user_id = u_req.id
                        LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
                        WHERE r.requested_by_user_id = ? 
                        ORDER BY r.request_date DESC"); // (ลบ LIMIT 5 ออก)
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history_result = $stmt->get_result();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>User Dashboard</h1>
    <a href="requisition_create.php" class="btn btn-success btn-lg">
        <i class="bi bi-plus-circle-fill"></i> สร้างใบเบิกใหม่
    </a>
</div>

<div class="row">
    <div class="col-12">
        
        <h3 class="mb-3">ประวัติการเบิกทั้งหมดของคุณ</h3>
        
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        
                        <thead class="table-light">
                            <tr>
                                <th>เลขที่ใบเบิก</th>
                                <th>วันที่ขอเบิก</th>
                                <th>สถานะ (Status)</th>
                                <th>ผู้อนุมัติ/ผู้จ่าย</th>
                                <th class="text-center">พิมพ์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result && $history_result->num_rows > 0): ?>
                                <?php while($row = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['mr_number']); ?></td>
                                        <td><?php echo $row['request_date']; ?></td>
                                        <td>
                                            <?php 
                                            // (แสดงสถานะแบบละเอียด)
                                            $status = $row['status'];
                                            $badge_class = 'bg-secondary';
                                            if ($status == 'Pending Dept Approval') $badge_class = 'bg-warning text-dark';
                                            if ($status == 'Pending Issue') $badge_class = 'bg-primary';
                                            if ($status == 'Issued') $badge_class = 'bg-success';
                                            if ($status == 'Dept Rejected' || $status == 'WH Cancelled') $badge_class = 'bg-danger';
                                            
                                            echo "<span class='badge {$badge_class}'>{$status}</span>";
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['approver_name'] ?? '-'); ?></td>
                                        <td class="text-center">
                                            <?php 
                                            // (แสดงปุ่มพิมพ์เมื่ออนุมัติแล้ว)
                                            if ($row['status'] == 'Pending Issue' || $row['status'] == 'Issued'): ?>
                                                <a href="mr_print.php?req_id=<?php echo $row['req_id']; ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   target="_blank" 
                                                   title="พิมพ์เอกสาร MR">
                                                    <i class="bi bi-printer-fill"></i>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">คุณยังไม่มีประวัติการเบิก</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        </div>
</div>

<?php 
$stmt->close();
$conn->close();
require_once '../includes/footer.php'; 
?>