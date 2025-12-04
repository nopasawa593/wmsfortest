<?php
require_once '../includes/header.php'; 

// 0. ตรวจสอบสิทธิ์ (ผู้จัดการพัสดุ / Admin)
if (!hasRole(['WH_MANAGER'])) {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// 1. ⭐️ (แก้ไข Query) ดึง pr_id มาด้วย ⭐️
$sql = "SELECT 
            po.id AS po_id,
            po.pr_id, -- ⭐️ (เพิ่ม)
            po.po_number, 
            po.order_date, 
            po.status,
            s.name AS supplier_name,
            u.full_name AS created_by_name,
            (SELECT SUM(quantity_ordered * unit_price) FROM po_items WHERE po_id = po.id) AS total_amount
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN users u ON po.created_by_user_id = u.id
        WHERE po.status = 'Pending PO Approval'
        ORDER BY po.order_date ASC";
$result = $conn->query($sql);

// (ดึง Alert Message)
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}
?>

<h1 class="mb-4"><i class="bi bi-check-all me-2"></i> อนุมัติใบสั่งซื้อ (PO Approval)</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">รายการ PO ที่รออนุมัติ</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ PO</th>
                        <th>ผู้ขาย (Supplier)</th>
                        <th>วันที่สั่ง</th>
                        <th class="text-end">ยอดรวม</th>
                        <th>ผู้สร้าง</th>
                        <th>สถานะ</th>
                        <th class="text-center">จัดการ (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td><?php echo $row['order_date']; ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                                <td>
                                    <span class='badge bg-warning text-dark'><?php echo $row['status']; ?></span>
                                </td>
                                <td class="text-center">
                                    <form action="po_approve_process.php" method="POST" class="d-inline">
                                        <input type="hidden" name="po_id" value="<?php echo $row['po_id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" title="อนุมัติ PO นี้">
                                            <i class="bi bi-check-circle"></i> อนุมัติ
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" title="ปฏิเสธ PO นี้">
                                            <i class="bi bi-x-circle"></i> ปฏิเสธ
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-info btn-sm po-detail-btn" 
                                            title="ดูรายละเอียดการเทียบราคา"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#poCompareDetailModal"
                                            data-pr-id="<?php echo $row['pr_id']; ?>"
                                            data-po-id="<?php echo $row['po_id']; ?>">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    
                                    <a href="po_print.php?po_id=<?php echo $row['po_id']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="ดู/พิมพ์ PO">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">ไม่พบรายการที่รออนุมัติ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="poCompareDetailModal" tabindex="-1" aria-labelledby="poCompareDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"> 
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poCompareDetailModalLabel">ตรวจสอบการเทียบราคา (PR)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="po-compare-modal-content">
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
    
    const poCompareModal = document.getElementById('poCompareDetailModal');
    if (poCompareModal) {
        
        poCompareModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const prId = button.getAttribute('data-pr-id');
            const poId = button.getAttribute('data-po-id');
            
            const modalTitle = poCompareModal.querySelector('.modal-title');
            const modalBody = poCompareModal.querySelector('#po-compare-modal-content');

            // 1. เคลียร์ค่าเก่าและแสดง Loading
            modalTitle.textContent = 'กำลังโหลด...';
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>กำลังโหลดข้อมูลการเทียบราคา...</p></div>';

            if (!prId || prId == '0' || prId == null) {
                 modalBody.innerHTML = '<div class="alert alert-warning">PO นี้ไม่ได้ถูกสร้างจาก PR (สร้าง PO Manual) จึงไม่มีข้อมูลการเทียบราคา</div>';
                 modalTitle.textContent = 'รายละเอียด PO';
                 return;
            }

            // 2. เรียก API ใหม่
            fetch(`api_get_comparison_details.php?pr_id=${prId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    modalTitle.textContent = `ตรวจสอบการเทียบราคา (สำหรับ PR: ${data.pr_items[0]?.pr_number || prId})`;
                    
                    // 3. เริ่มสร้างตาราง
                    let tableHtml = '<div class="table-responsive"><table class="table table-bordered table-sm">';
                    
                    // 3.1 สร้าง Header
                    tableHtml += '<thead class="table-light"><tr><th style="min-width: 250px;">วัสดุ</th><th class="text-end">จำนวน</th>';
                    data.suppliers.forEach(sup => {
                        tableHtml += `<th class="text-center">${sup.supplier_name}</th>`;
                    });
                    tableHtml += '</tr></thead>';
                    
                    // 3.2 สร้าง Body (รายการวัสดุ)
                    tableHtml += '<tbody>';
                    
                    // ⭐️ (อัปเดต) 1. สร้าง Array เก็บ Total ของ Best Price ⭐️
                    let bestPriceTotals = new Array(data.suppliers.length).fill(0);
                    
                    data.pr_items.forEach(item => {
                        const quantity = parseFloat(item.quantity_requested) || 0;
                        tableHtml += `<tr>
                                        <td>${item.item_code} - ${item.name}</td>
                                        <td class="text-end">${quantity.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`;
                        
                        let minPrice = Infinity;
                        let prices = [];
                        
                        // (หา Min Price)
                        data.suppliers.forEach(sup => {
                            let price = data.prices[item.material_id] ? (data.prices[item.material_id][sup.supplier_id] || 0) : 0;
                            price = parseFloat(price);
                            prices.push({ price: price, sup_id: sup.supplier_id });
                            if (price > 0 && price < minPrice) {
                                minPrice = price;
                            }
                        });

                        // (สร้าง Cell ราคา + ไฮไลท์ + ⭐️ บวกยอด Best Price ⭐️)
                        prices.forEach((p, colIndex) => {
                            let cellClass = 'text-end';
                            if (p.price > 0 && Math.abs(p.price - minPrice) < 0.001) {
                                cellClass += ' table-success fw-bold';
                                // ⭐️ (อัปเดต) บวกยอดเฉพาะช่องที่ถูกที่สุด ⭐️
                                bestPriceTotals[colIndex] += (quantity * p.price);
                            }
                            tableHtml += `<td class="${cellClass}">${p.price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`;
                        });
                        
                        tableHtml += '</tr>';
                    });
                    tableHtml += '</tbody>';

                    // 3.3 ⭐️ (อัปเดต) สร้าง Footer (ราคารวม Best Price) ⭐️
                    tableHtml += '<tfoot class="table-light">';
                    
                    // (หา Min Total ที่ดีที่สุด)
                    let minTotal = Infinity;
                    bestPriceTotals.forEach(total => {
                        if (total > 0 && total < minTotal) {
                            minTotal = total;
                        }
                    });

                    // (สร้างแถวราคารวม)
                    tableHtml += '<tr><td colspan="2" class="text-end fw-bold">ราคารวม (Total - Best Price)</td>';
                    bestPriceTotals.forEach(total => {
                        let cellClass = 'text-end fw-bold fs-5';
                        if (total > 0 && Math.abs(total - minTotal) < 0.001) {
                            cellClass += ' table-success';
                        }
                        // ⭐️ (อัปเดต) ใช้ toLocaleString() สำหรับ Format Number ⭐️
                        tableHtml += `<td class="${cellClass}">${total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`;
                    });
                    tableHtml += '</tr>';

                    // (สร้างแถวไฟล์แนบ)
                    tableHtml += '<tr><td colspan="2" class="text-end fw-bold">ไฟล์แนบ</td>';
                    data.suppliers.forEach(sup => {
                        if (sup.quotation_file_path) {
                            tableHtml += `<td><a href="${sup.quotation_file_path}" target="_blank" class="btn btn-info btn-sm w-100"><i class="bi bi-file-earmark-arrow-down"></i> ดูไฟล์แนบ</a></td>`;
                        } else {
                            tableHtml += '<td>-</td>';
                        }
                    });
                    tableHtml += '</tr>';
                    
                    tableHtml += '</tfoot></table></div>';
                    modalBody.innerHTML = tableHtml;
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    modalTitle.textContent = 'เกิดข้อผิดพลาด';
                    modalBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                });
        });
    }
});
</script>