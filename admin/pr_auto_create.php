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

// 4. ตรวจสอบสิทธิ์
if (!hasRole(['WH_STAFF', 'WH_MANAGER'])) {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// 5. Logic การบันทึก PR (เหมือนเดิม)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selected_material_ids'])) {
    $requested_by_user_id = $_SESSION['user_id'];
    $selected_material_ids = $_POST['selected_material_ids'];
    $quantities_to_order = $_POST['quantity_to_order'];

    if (empty($selected_material_ids)) {
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: กรุณาเลือกอย่างน้อย 1 รายการ";
        $_SESSION['alert_type'] = "danger";
        header("Location: pr_auto_create.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        // สร้างเลข PR
        $next_pr_num_result = $conn->query("SELECT MAX(id) + 1 AS next_id FROM purchase_requisitions");
        $next_pr_num = $next_pr_num_result ? $next_pr_num_result->fetch_assoc()['next_id'] : 1;
        if (is_null($next_pr_num)) $next_pr_num = 1;
        $pr_number = "PR-AUTO-" . date("Y") . "-" . str_pad($next_pr_num, 5, "0", STR_PAD_LEFT);

        // บันทึก Header
        $stmt_pr = $conn->prepare("INSERT INTO purchase_requisitions (pr_number, requested_by_user_id, request_date, department, reason, status)
                                  VALUES (?, ?, CURDATE(), ?, ?, 'Pending WH Approval')");
        $department = "Warehouse";
        $reason = "Auto-PR from Low Stock";
        $stmt_pr->bind_param("siss", $pr_number, $requested_by_user_id, $department, $reason);
        $stmt_pr->execute();
        $pr_id = $conn->insert_id;
        $stmt_pr->close();

        // บันทึก Items
        $stmt_item = $conn->prepare("INSERT INTO pr_items (pr_id, material_id, quantity_requested) VALUES (?, ?, ?)");
        $item_added = false;
        foreach ($selected_material_ids as $material_id) {
            $quantity = $quantities_to_order[$material_id] ?? 0;
            if ($quantity > 0) {
                $stmt_item->bind_param("iid", $pr_id, $material_id, $quantity);
                $stmt_item->execute();
                $item_added = true;
            }
        }
        $stmt_item->close();

        if (!$item_added) {
            throw new Exception("ไม่มีรายการใดถูกสั่งซื้อ (จำนวนอาจเป็น 0)");
        }
        
        $conn->commit();
        $_SESSION['alert_message'] = "สร้างใบขอซื้อ (PR) เลขที่ $pr_number สำเร็จ!";
        $_SESSION['alert_type'] = "success";
        header("Location: pr_approval_list.php"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        header("Location: pr_auto_create.php"); 
        exit();
    }
}

// 6. ดึง Alert Message
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// 7. Include Header
require_once '../includes/header.php';

// 8. Query รายการ Low Stock
$sql_low_stock = "SELECT 
                        m.id, m.item_code, m.name, m.unit, 
                        m.min_stock_level, m.max_stock_level,
                        COALESCE(SUM(i.quantity), 0) AS current_stock
                    FROM materials m
                    LEFT JOIN inventory i ON m.id = i.material_id
                    GROUP BY m.id, m.item_code, m.name, m.unit, m.min_stock_level, m.max_stock_level
                    HAVING current_stock < m.min_stock_level
                    ORDER BY m.name ASC";
$low_stock_result = $conn->query($sql_low_stock);
?>

<style>
    /* ไฮไลท์แถวที่เลือก */
    .table-hover tbody tr:hover {
        background-color: rgba(0, 122, 255, 0.05) !important;
    }
    /* ช่องกรอกจำนวน */
    .qty-input {
        border: 1px solid #E5E5EA;
        background-color: #fff;
        text-align: center;
        font-weight: bold;
        color: var(--ios-blue);
        transition: all 0.2s;
    }
    .qty-input:focus {
        background-color: #fff;
        border-color: var(--ios-blue);
        box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15);
    }
    /* ไอคอนหน้าหัวข้อ */
    .page-icon {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #FF9500, #FF5E3A);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-right: 15px;
    }
    .table th {
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
</style>

<div class="d-flex align-items-center mb-4">
    <i class="bi bi-robot page-icon"></i>
    <div>
        <h2 class="fw-bold mb-0">Auto PR Generator</h2>
        <p class="text-muted mb-0">ระบบสร้างใบขอซื้ออัตโนมัติจากรายการ Low Stock</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 mb-4">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-bottom-0 py-3">
        <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-list-check me-2"></i> รายการวัสดุที่ควรสั่งซื้อ</h5>
    </div>
    <div class="card-body p-0">
        
        <form action="pr_auto_create.php" method="POST" id="auto-pr-form">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="select-all-checkbox" style="cursor: pointer;">
                                </div>
                            </th>
                            <th>รหัสวัสดุ</th>
                            <th>ชื่อวัสดุ</th>
                            <th class="text-end">คงคลัง</th>
                            <th class="text-end">Min</th>
                            <th class="text-end">Max</th>
                            <th class="text-end text-danger">ขาดอยู่</th>
                            <th style="width: 150px;" class="text-center">สั่งซื้อ (Qty)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($low_stock_result && $low_stock_result->num_rows > 0): ?>
                            <?php while($item = $low_stock_result->fetch_assoc()): 
                                // คำนวณยอด
                                $below_min_qty = $item['min_stock_level'] - $item['current_stock'];
                                $suggested_qty = $item['max_stock_level'] - $item['current_stock'];
                                
                                if ($suggested_qty <= 0 || $item['max_stock_level'] <= $item['min_stock_level']) {
                                    $suggested_qty = $below_min_qty; 
                                }
                                $suggested_qty = ceil($suggested_qty);
                                if ($suggested_qty < 0) $suggested_qty = 0;
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input item-checkbox" 
                                                   type="checkbox" 
                                                   name="selected_material_ids[]" 
                                                   value="<?php echo $item['id']; ?>"
                                                   style="cursor: pointer;">
                                        </div>
                                    </td>
                                    <td class="fw-bold text-secondary"><?php echo htmlspecialchars($item['item_code']); ?></td>
                                    <td>
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">
                                            <?php echo number_format($item['current_stock'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-muted"><?php echo number_format($item['min_stock_level'], 2); ?></td>
                                    <td class="text-end text-muted"><?php echo number_format($item['max_stock_level'], 2); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo number_format($below_min_qty, 2); ?></td>
                                    
                                    <td class="p-3">
                                        <div class="input-group input-group-sm">
                                            <input type="number" 
                                                   class="form-control qty-input rounded-3" 
                                                   name="quantity_to_order[<?php echo $item['id']; ?>]"
                                                   value="<?php echo $suggested_qty; ?>" 
                                                   step="0.01" min="0">
                                            <span class="input-group-text bg-transparent border-0 text-muted small">
                                                <?php echo htmlspecialchars($item['unit']); ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-check-circle display-4 text-success mb-3 d-block"></i>
                                        เยี่ยมมาก! สต็อกทุกรายการเพียงพอแล้ว
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($low_stock_result && $low_stock_result->num_rows > 0): ?>
            <div class="card-footer bg-white border-top-0 py-4 text-end">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">
                    <i class="bi bi-send-fill me-2"></i> สร้างใบขอซื้อ (Generate PR)
                </button>
            </div>
            <?php endif; ?>
            
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });
    }
});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>