<?php 
require_once '../includes/header.php'; 
// (ตรวจสอบสิทธิ์ Admin)
// if ($_SESSION['role'] != 'admin') {
//     die("Access Denied.");
// }

// --- 1. ดึงข้อมูลสรุปตามวัสดุ (Total Stock) ---
$sql_total = "SELECT 
                m.item_code, 
                m.name, 
                m.unit, 
                SUM(i.quantity) AS total_quantity
              FROM inventory i
              JOIN materials m ON i.material_id = m.id
              WHERE i.quantity > 0
              GROUP BY i.material_id, m.item_code, m.name, m.unit
              ORDER BY m.name ASC";
$total_result = $conn->query($sql_total);


// --- 2. ดึงข้อมูลสรุปแบบละเอียด (Detailed Stock by Location/Batch) ---
$sql_detail = "SELECT 
                 m.item_code, 
                 m.name, 
                 l.location_code, 
                 i.batch_number, 
                 i.quantity,
                 i.expiry_date
               FROM inventory i
               JOIN materials m ON i.material_id = m.id
               JOIN locations l ON i.location_id = l.id
               WHERE i.quantity > 0
               ORDER BY m.name ASC, l.location_code ASC, i.batch_number ASC";
$detail_result = $conn->query($sql_detail);

?>

<h1 class="mb-4">รายงานสต็อกคงคลัง (Inventory On-Hand)</h1>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h3 class="mb-0">สรุปยอดคงคลังรวม (By Material)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>รหัสวัสดุ</th>
                        <th>ชื่อวัสดุ</th>
                        <th>ยอดรวมคงเหลือ</th>
                        <th>หน่วยนับ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_result->num_rows > 0): ?>
                        <?php while($row = $total_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="fw-bold fs-5 text-end"><?php echo number_format($row['total_quantity'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">ไม่พบข้อมูลสต็อก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<hr class="my-5">

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="mb-0">สต็อกคงคลังแบบละเอียด (By Location & Batch)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>รหัสวัสดุ</th>
                        <th>ชื่อวัสดุ</th>
                        <th>ที่เก็บ (Location)</th>
                        <th>Batch / Lot</th>
                        <th>วันหมดอายุ (ถ้ามี)</th>
                        <th>จำนวน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($detail_result->num_rows > 0): ?>
                        <?php while($row = $detail_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['location_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['batch_number']); ?></td>
                                <td><?php echo $row['expiry_date'] ? $row['expiry_date'] : '-'; ?></td>
                                <td class_="text-end"><?php echo number_format($row['quantity'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ไม่พบข้อมูลสต็อก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>