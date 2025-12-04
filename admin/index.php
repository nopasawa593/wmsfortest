<?php 
require_once '../includes/header.php'; 

// --- 1. ตรวจสอบสิทธิ์ ---
if (!hasRole(['ADMIN', 'WH_MANAGER', 'WH_STAFF'])) {
    echo "<script>window.location.href = '../user/index.php';</script>";
    exit();
}

// --- 2. ดึงข้อมูลสรุป (KPIs) ---
$low_stock = 0; $pending_po = 0; $pending_gi = 0; $unpaid_inv = 0;

// (เพิ่ม Error Handling เผื่อ Table ยังไม่สมบูรณ์)
$check_mat = $conn->query("SELECT COUNT(*) as count FROM materials m LEFT JOIN (SELECT material_id, SUM(quantity) as qty FROM inventory GROUP BY material_id) i ON m.id = i.material_id WHERE IFNULL(i.qty, 0) < m.min_stock_level AND m.status = 'Active'");
if($check_mat) $low_stock = $check_mat->fetch_assoc()['count'];

$check_po = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'Pending PO Approval'");
if($check_po) $pending_po = $check_po->fetch_assoc()['count'];

$check_gi = $conn->query("SELECT COUNT(*) as count FROM requisitions WHERE status = 'Pending Issue'");
if($check_gi) $pending_gi = $check_gi->fetch_assoc()['count'];

$check_inv = $conn->query("SELECT COUNT(*) as count FROM supplier_invoices WHERE status = 'Unpaid'");
if($check_inv) $unpaid_inv = $check_inv->fetch_assoc()['count'];

// --- 3. ดึงข้อมูลกราฟ ---
$cat_labels = []; $cat_data = [];
$chart_res = $conn->query("SELECT c.name, COUNT(m.id) as mat_count FROM material_categories c LEFT JOIN materials m ON c.id = m.category_id GROUP BY c.id ORDER BY mat_count DESC LIMIT 5");
if($chart_res){
    while($row = $chart_res->fetch_assoc()){
        $cat_labels[] = $row['name'];
        $cat_data[] = $row['mat_count'];
    }
}

// --- 4. ดึงความเคลื่อนไหวล่าสุด ---
$activities = [];
$res_gr = $conn->query("SELECT 'Receive (GR)' as type, gr.receive_date as date, u.username as user, s.name as ref FROM goods_receiving gr JOIN users u ON gr.received_by_user_id = u.id JOIN suppliers s ON gr.supplier_id = s.id ORDER BY gr.receive_date DESC LIMIT 5");
if($res_gr) while($row = $res_gr->fetch_assoc()) $activities[] = $row;

$res_gi = $conn->query("SELECT 'Issue (GI)' as type, gi.issue_date as date, u.username as user, r.mr_number as ref FROM goods_issuing gi JOIN users u ON gi.issued_by_user_id = u.id JOIN requisitions r ON gi.requisition_id = r.id ORDER BY gi.issue_date DESC LIMIT 5");
if($res_gi) while($row = $res_gi->fetch_assoc()) $activities[] = $row;

usort($activities, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
$activities = array_slice($activities, 0, 7);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .kpi-card { border: none; border-radius: 20px; transition: transform 0.2s; overflow: hidden; }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .icon-circle { width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
    
    .bg-gradient-orange { background: linear-gradient(135deg, #FF9500, #FF5E3A); }
    .bg-gradient-blue { background: linear-gradient(135deg, #007AFF, #00C6FF); }
    .bg-gradient-green { background: linear-gradient(135deg, #34C759, #30B0C7); }
    .bg-gradient-purple { background: linear-gradient(135deg, #AF52DE, #5856D6); }
    
    /* ⭐️ แก้ไข: ล็อคความสูงตารางกิจกรรมให้พอดีกับกราฟ */
    .activity-container { height: 100%; min-height: 380px; }
    .table-activity td { vertical-align: middle; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Dashboard Overview</h2>
        <p class="text-muted">ภาพรวมสถานะระบบคลังสินค้า</p>
    </div>
    <div class="text-end">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="bi bi-circle-fill small me-1"></i> System Online</span>
        <div class="text-muted small mt-1"><?php echo date('d F Y'); ?></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-gradient-orange me-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div><h6 class="text-muted mb-0">สินค้าใกล้หมด</h6><h3 class="fw-bold mb-0"><?php echo $low_stock; ?></h3></div>
                <a href="pr_auto_create.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-gradient-blue me-3"><i class="bi bi-file-earmark-check-fill"></i></div>
                <div><h6 class="text-muted mb-0">PO รออนุมัติ</h6><h3 class="fw-bold mb-0"><?php echo $pending_po; ?></h3></div>
                <a href="po_approval_list.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-gradient-green me-3"><i class="bi bi-box-seam-fill"></i></div>
                <div><h6 class="text-muted mb-0">รอจ่ายของ (MR)</h6><h3 class="fw-bold mb-0"><?php echo $pending_gi; ?></h3></div>
                <a href="requisition_list.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-gradient-purple me-3"><i class="bi bi-credit-card-fill"></i></div>
                <div><h6 class="text-muted mb-0">Invoice รอจ่าย</h6><h3 class="fw-bold mb-0"><?php echo $unpaid_inv; ?></h3></div>
                <a href="payment_create.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>สัดส่วนวัสดุตามหมวดหมู่</h5>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0 rounded-4 h-100 activity-container">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-warning"></i>ความเคลื่อนไหวล่าสุด</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-activity">
                        <thead class="table-light">
                            <tr><th class="ps-3">กิจกรรม</th><th>อ้างอิง</th><th>เวลา</th></tr>
                        </thead>
                        <tbody>
                            <?php if(count($activities) > 0): ?>
                                <?php foreach($activities as $act): ?>
                                <tr>
                                    <td class="ps-3">
                                        <?php if($act['type'] == 'Receive (GR)'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill">รับของเข้า</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill">จ่ายของออก</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-truncate" style="max-width: 120px;"><?php echo $act['ref']; ?></td>
                                    <td class="small text-muted"><?php echo date('d/m H:i', strtotime($act['date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3 text-secondary">เมนูจัดการด่วน</h5>
<div class="row g-3">
    <div class="col-6 col-md-3 col-lg-2">
        <a href="pr_create.php" class="card shadow-sm border-0 text-center text-decoration-none h-100 py-3 hover-zoom">
            <div class="card-body"><i class="bi bi-journal-plus fs-2 text-primary"></i><h6 class="mt-2 text-dark">สร้าง PR</h6></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="po_create.php" class="card shadow-sm border-0 text-center text-decoration-none h-100 py-3 hover-zoom">
            <div class="card-body"><i class="bi bi-shop fs-2 text-info"></i><h6 class="mt-2 text-dark">สร้าง PO</h6></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="gr_receive.php" class="card shadow-sm border-0 text-center text-decoration-none h-100 py-3 hover-zoom">
            <div class="card-body"><i class="bi bi-truck fs-2 text-success"></i><h6 class="mt-2 text-dark">รับของ (GR)</h6></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="requisition_list.php" class="card shadow-sm border-0 text-center text-decoration-none h-100 py-3 hover-zoom">
            <div class="card-body"><i class="bi bi-box-seam fs-2 text-warning"></i><h6 class="mt-2 text-dark">จ่ายของ (GI)</h6></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="report_stock.php" class="card shadow-sm border-0 text-center text-decoration-none h-100 py-3 hover-zoom">
            <div class="card-body"><i class="bi bi-bar-chart-line fs-2 text-secondary"></i><h6 class="mt-2 text-dark">เช็คสต็อก</h6></div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <a href="users_list.php" class="card shadow-sm border-0 text-center text-decoration-none h-100 py-3 hover-zoom">
            <div class="card-body"><i class="bi bi-people fs-2 text-dark"></i><h6 class="mt-2 text-dark">ผู้ใช้งาน</h6></div>
        </a>
    </div>
</div>

<style>
    .hover-zoom { transition: transform 0.2s; }
    .hover-zoom:hover { transform: scale(1.05); background-color: #f8f9fa; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    
    // ข้อมูลจำลอง ถ้าฐานข้อมูลยังไม่มีข้อมูล เพื่อให้กราฟโชว์สวยๆ ก่อน
    let labels = <?php echo json_encode($cat_labels); ?>;
    let data = <?php echo json_encode($cat_data); ?>;
    
    if(labels.length === 0) {
        labels = ['วัสดุสิ้นเปลือง', 'อะไหล่เครื่องจักร', 'อุปกรณ์ไฟฟ้า', 'เครื่องเขียน', 'วัตถุดิบ'];
        data = [0, 0, 0, 0, 0]; // ใส่ 0 ไว้ก่อน
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'จำนวนรายการ (SKU)',
                data: data,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 99, 132, 0.8)'
                ],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // ⭐️ แก้ไขจุดที่ 2: ให้กราฟยืดตาม Container ที่เราล็อคความสูงไว้
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>