<?php 
require_once '../includes/header.php'; 

// --- 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin และทีมพัสดุ) ---
if (!hasRole(['ADMIN', 'WH_MANAGER', 'WH_STAFF'])) {
    die("<div class='alert alert-danger m-4'>Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>");
}

// --- 2. เตรียมข้อมูลสำหรับ Dropdown ---
$sql_materials = "SELECT id, item_code, name FROM materials ORDER BY item_code ASC";
$materials_result = $conn->query($sql_materials);

$stock_card_result = null;
$mat_info = null;
$selected_material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;

// --- 3. ดึงข้อมูล Stock Card เมื่อเลือกวัสดุ ---
if ($selected_material_id > 0) {
    // 3.1 ดึงรายละเอียดวัสดุ
    $mat_info = $conn->query("SELECT * FROM materials WHERE id = $selected_material_id")->fetch_assoc();
    
    if ($mat_info) {
        // ---------------------------------------------------------
        // ⭐️ สร้าง Query แบบ UNION (รวม 3 ตารางเข้าด้วยกัน) ⭐️
        // ---------------------------------------------------------

        // (A) ประวัติการรับเข้า (GR)
        $sql_in = "SELECT 
                     gr.receive_date AS trans_date, 
                     CONCAT('GR-', gr.id) AS doc_ref, 
                     CONCAT('รับเข้า (PO: ', IFNULL(po.po_number, 'No PO'), ')') AS trans_desc,
                     gri.quantity_received AS qty_in, 
                     0 AS qty_out
                   FROM gr_items gri
                   JOIN goods_receiving gr ON gri.gr_id = gr.id
                   LEFT JOIN purchase_orders po ON gr.po_id = po.id
                   WHERE gri.material_id = $selected_material_id";

        // (B) ประวัติการจ่ายออก (GI)
        // ดึงจาก gi_items ซึ่งเป็นยอดตัดจริง
        $sql_out = "SELECT 
                      gi.issue_date AS trans_date, 
                      CONCAT('GI-', gi.id) AS doc_ref, 
                      CONCAT('จ่ายให้ (MR: ', IFNULL(r.mr_number, 'No MR'), ')') AS trans_desc, 
                      0 AS qty_in, 
                      gii.quantity_issued AS qty_out
                    FROM gi_items gii
                    JOIN goods_issuing gi ON gii.gi_id = gi.id
                    LEFT JOIN requisitions r ON gi.requisition_id = r.id
                    WHERE gii.material_id = $selected_material_id";
        
        // (C) ประวัติการปรับปรุงสต็อก (Adjustment)
        $sql_adj = "SELECT
                       adj.adjustment_date AS trans_date,
                       CONCAT('ADJ-', adj.id) AS doc_ref,
                       CONCAT('ปรับปรุง: ', adj.reason) AS trans_desc,
                       CASE WHEN adj.adjustment_type = 'ADJ-IN' THEN adj.quantity_adjusted ELSE 0 END AS qty_in,
                       CASE WHEN adj.adjustment_type = 'ADJ-OUT' THEN adj.quantity_adjusted ELSE 0 END AS qty_out
                    FROM stock_adjustment_log adj
                    WHERE adj.material_id = $selected_material_id";

        // รวมทั้งหมดและเรียงตามวันที่เก่า -> ใหม่
        $sql_union = "SELECT * FROM (
                        ($sql_in) 
                        UNION ALL 
                        ($sql_out) 
                        UNION ALL
                        ($sql_adj)
                      ) AS stock_history
                      ORDER BY trans_date ASC, doc_ref ASC";
                      
        $stock_card_result = $conn->query($sql_union);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i> รายงานความเคลื่อนไหว (Stock Card)</h2>
</div>

<div class="card shadow-sm mb-4 border-0 rounded-4">
    <div class="card-body bg-light rounded-4">
        <form action="report_stock_card.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-10">
                <label for="material_id" class="form-label fw-bold text-secondary">เลือกวัสดุที่ต้องการตรวจสอบ</label>
                <select name="material_id" id="material_id" class="form-select shadow-sm border-0" onchange="this.form.submit()">
                    <option value="">-- กรุณาเลือกวัสดุ --</option>
                    <?php 
                    if($materials_result) {
                        $materials_result->data_seek(0);
                        while($mat = $materials_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $mat['id']; ?>" 
                                <?php echo ($selected_material_id == $mat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mat['item_code'] . " - " . $mat['name']); ?>
                        </option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 shadow-sm rounded-pill">
                    <i class="bi bi-search me-1"></i> ดูรายงาน
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($mat_info): ?>
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom-0 py-3">
            <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                    <i class="bi bi-box-seam fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($mat_info['name']); ?></h5>
                    <span class="text-muted small">รหัส: <?php echo htmlspecialchars($mat_info['item_code']); ?> | หน่วย: <?php echo htmlspecialchars($mat_info['unit']); ?></span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-uppercase small text-secondary">
                            <th class="ps-4" style="width: 15%;">วัน-เวลา</th>
                            <th style="width: 10%;">เลขที่เอกสาร</th>
                            <th style="width: 35%;">รายละเอียด / หมายเหตุ</th>
                            <th class="text-end text-success" style="width: 10%;">รับเข้า (IN)</th>
                            <th class="text-end text-danger" style="width: 10%;">จ่ายออก (OUT)</th>
                            <th class="text-end pe-4 fw-bold" style="width: 15%;">คงเหลือ (Balance)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $balance = 0; 
                        $has_data = false;

                        if ($stock_card_result && $stock_card_result->num_rows > 0):
                            $has_data = true;
                            while($row = $stock_card_result->fetch_assoc()): 
                                // คำนวณยอดคงเหลือสะสม (Running Balance)
                                $balance += $row['qty_in'];
                                $balance -= $row['qty_out'];
                                
                                // กำหนดสี Badge ตามประเภทเอกสาร
                                $doc_badge = "bg-secondary";
                                if(strpos($row['doc_ref'], 'GR') !== false) $doc_badge = "bg-success";
                                elseif(strpos($row['doc_ref'], 'GI') !== false) $doc_badge = "bg-primary";
                                elseif(strpos($row['doc_ref'], 'ADJ') !== false) $doc_badge = "bg-warning text-dark";
                        ?>
                                <tr>
                                    <td class="ps-4 text-muted small">
                                        <?php echo date("d/m/Y H:i", strtotime($row['trans_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $doc_badge; ?> rounded-pill px-3 shadow-sm">
                                            <?php echo htmlspecialchars($row['doc_ref']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars($row['trans_desc']); ?>
                                    </td>
                                    
                                    <td class="text-end">
                                        <?php if($row['qty_in'] > 0): ?>
                                            <span class="text-success fw-bold">+<?php echo number_format($row['qty_in'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted opacity-25">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end">
                                        <?php if($row['qty_out'] > 0): ?>
                                            <span class="text-danger fw-bold">-<?php echo number_format($row['qty_out'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted opacity-25">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <span class="fw-bold fs-6 text-dark bg-light px-2 py-1 rounded border">
                                            <?php echo number_format($balance, 2); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php 
                            endwhile;
                        endif; 
                        
                        // กรณีไม่มีข้อมูล
                        if (!$has_data):
                        ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x fs-1 d-block mb-3 opacity-25"></i>
                                    ยังไม่มีความเคลื่อนไหวสำหรับวัสดุนี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-3 text-end">
            <small class="text-muted">ข้อมูล ณ วันที่ <?php echo date('d/m/Y H:i'); ?></small>
        </div>
    </div>
<?php elseif(isset($_GET['material_id'])): ?>
    <div class="alert alert-warning m-3 shadow-sm border-0">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> ไม่พบข้อมูลวัสดุที่ระบุ
    </div>
<?php endif; ?>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>