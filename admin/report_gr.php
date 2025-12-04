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
    die("Access Denied: You do not have permission to view reports.");
}

// 5. Include Header (หลังจากเช็คสิทธิ์)
require_once '../includes/header.php';

// --- 6. ⭐️ (อัปเดต Query) ดึง "เลขที่ Invoice (AP)" มาด้วย ⭐️ ---

$date_start = $_GET['date_start'] ?? date('Y-m-01'); 
$date_end = $_GET['date_end'] ?? date('Y-m-d');     

$report_result = null;

// (เตรียม SQL)
$sql = "SELECT 
            gr.receive_date,
            po.po_number,
            gr.notes AS gr_notes, -- (เปลี่ยนชื่อ)
            inv.invoice_number AS ap_invoice_number, -- ⭐️ (เพิ่ม) ดึงจากตาราง Invoice
            s.name AS supplier_name,
            m.item_code,
            m.name AS material_name,
            gri.quantity_received,
            m.unit,
            COALESCE(poi.unit_price, 0) AS unit_price,
            (gri.quantity_received * COALESCE(poi.unit_price, 0)) AS total_price
        FROM gr_items AS gri
        JOIN goods_receiving AS gr ON gri.gr_id = gr.id
        JOIN materials AS m ON gri.material_id = m.id
        JOIN suppliers AS s ON gr.supplier_id = s.id
        LEFT JOIN purchase_orders AS po ON gr.po_id = po.id
        LEFT JOIN po_items AS poi ON po.id = poi.po_id AND gri.material_id = poi.material_id
        -- ⭐️ (เพิ่ม) JOIN ตาราง Invoice (อาจจะ JOIN หลายครั้งถ้า 1 PO มีหลาย GR/INV)
        LEFT JOIN supplier_invoices AS inv ON po.id = inv.po_id 
        WHERE DATE(gr.receive_date) BETWEEN ? AND ? 
        -- (ใช้ GROUP BY เพื่อป้องกันแถวซ้ำ ถ้า 1 PO/GR มีหลาย Item หรือหลาย Invoice)
        GROUP BY gri.id
        ORDER BY gr.receive_date DESC, gr.id DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $date_start, $date_end);
    $stmt->execute();
    $report_result = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Query Error: " . $e->getMessage() . "</div>";
}
?>

<h1 class="mb-4"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i>รายงานการรับวัสดุ (GR Report)</h1>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">กรองข้อมูล</h5></div>
    <div class="card-body">
        <form action="report_gr.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="date_start" class="form-label">ตั้งแต่วันที่:</label>
                <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>">
            </div>
            <div class="col-md-4">
                <label for="date_end" class="form-label">ถึงวันที่:</label>
                <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> ดูรายงาน</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">ประวัติการรับวัสดุ (GR) (<?php echo htmlspecialchars($date_start); ?> ถึง <?php echo htmlspecialchars($date_end); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                
                <thead class="table-light">
                    <tr>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>เลขที่ PO</th>
                        <th>เลขที่ใบส่งของ (GR Note)</th>
                        <th>เลขที่ Invoice (AP)</th> <th>ร้านค้า (Supplier)</th>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อวัสดุ</th>
                        <th class="text-end">จำนวน</th>
                        <th>หน่วยนับ</th>
                        <th class="text-end">ราคา/หน่วย</th>
                        <th class="text-end">ราคารวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($report_result && $report_result->num_rows > 0): ?>
                        <?php while($row = $report_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($row['receive_date'])); ?></td>
                                <td><?php echo date('H:i:s', strtotime($row['receive_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['po_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['gr_notes'] ?? '-'); ?></td> <td><?php echo htmlspecialchars($row['ap_invoice_number'] ?? '-'); ?></td> <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['material_name']); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['quantity_received'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td class="text-end"><?php echo number_format($row['unit_price'], 2); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['total_price'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-3">
                                ไม่พบข้อมูลการรับของ ในช่วงวันที่ที่เลือก
                            </td>
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