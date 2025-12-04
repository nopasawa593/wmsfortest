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

// 4. ตรวจสอบสิทธิ์ (ต้องทำก่อน xử lý POST)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to adjust stock.");
}

// 5. ⭐️ (Logic POST - เหมือนเดิม) ⭐️
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'adjust_stock') {

    $user_id = $_SESSION['user_id'];
    $material_id = (int)$_POST['material_id'];
    $inventory_id = (int)$_POST['inventory_id']; 
    $adjustment_type = $_POST['adjustment_type'];
    $quantity_adjusted = (float)$_POST['quantity_adjusted'];
    $reason = $_POST['reason'];

    if ($material_id <= 0 || $inventory_id <= 0 || $quantity_adjusted <= 0 || empty($reason)) {
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: กรุณากรอกข้อมูลให้ครบถ้วน (วัสดุ, Lot, จำนวน, และเหตุผล)";
        $_SESSION['alert_type'] = "danger";
        header("Location: stock_adjustment.php");
        exit();
    }
    
    $conn->begin_transaction();
    try {
        $stmt_check = $conn->prepare("SELECT quantity FROM inventory WHERE id = ? AND material_id = ? FOR UPDATE");
        $stmt_check->bind_param("ii", $inventory_id, $material_id);
        $stmt_check->execute();
        $stock_result = $stmt_check->get_result();
        
        if ($stock_result->num_rows == 0) {
            throw new Exception("ไม่พบ Lot สต็อกที่เลือก (ID: $inventory_id)");
        }
        $current_stock = (float)$stock_result->fetch_assoc()['quantity'];
        $stmt_check->close();

        $sql_update_inv = "";
        if ($adjustment_type == 'ADJ-IN') {
            $sql_update_inv = "UPDATE inventory SET quantity = quantity + ? WHERE id = ?";
        } elseif ($adjustment_type == 'ADJ-OUT') {
            if ($current_stock < $quantity_adjusted) {
                 throw new Exception("สต็อกไม่พอ! คงเหลือ $current_stock แต่ต้องการปรับออก $quantity_adjusted");
            }
            $sql_update_inv = "UPDATE inventory SET quantity = quantity - ? WHERE id = ?";
        } else {
             throw new Exception("ประเภทการปรับปรุงไม่ถูกต้อง");
        }

        $stmt_update = $conn->prepare($sql_update_inv);
        $stmt_update->bind_param("di", $quantity_adjusted, $inventory_id);
        $stmt_update->execute();
        $stmt_update->close();

        $stmt_log = $conn->prepare("INSERT INTO stock_adjustment_log 
                                    (material_id, inventory_id, user_id, adjustment_type, quantity_adjusted, reason)
                                   VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_log->bind_param("iiisds", $material_id, $inventory_id, $user_id, $adjustment_type, $quantity_adjusted, $reason);
        $stmt_log->execute();
        $stmt_log->close();

        $conn->commit();
        $_SESSION['alert_message'] = "ปรับปรุงสต็อก (<b>$adjustment_type</b> จำนวน <b>$quantity_adjusted</b>) สำเร็จ!";
        $_SESSION['alert_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }

    header("Location: stock_adjustment.php");
    exit();
}
// --- จบส่วน xử lý POST ---


// 6. ดึง Alert Message (ถ้ามี)
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// 7. Include Header
require_once '../includes/header.php';

// --- 8. ดึงข้อมูลสำหรับ Form ---
$materials_result = $conn->query("SELECT id, item_code, name FROM materials WHERE status = 'Active' ORDER BY name ASC");
?>

<h1 class="mb-4"><i class="bi bi-arrow-left-right me-2"></i>ปรับปรุงสต็อก (Stock Adjustment)</h1>
<p class="text-muted">ใช้สำหรับปรับยอดคงคลัง (On-Hand) ให้ตรงกับความเป็นจริง เช่น กรณีของ หาย, แตกหัก, หรือ นับสต็อก</p>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">กรอกรายละเอียดการปรับปรุง</h5>
            </div>
            <div class="card-body">
                <form action="stock_adjustment.php" method="POST" id="adj-form">
                    <input type="hidden" name="action" value="adjust_stock">
                    
                    <div class="mb-3">
                        <label for="material_id" class="form-label">1. เลือกวัสดุ (Material)</label>
                        <select id="material_id" name="material_id" class="form-select" required>
                            <option value="">-- กรุณาเลือกวัสดุ --</option>
                            <?php if ($materials_result && $materials_result->num_rows > 0): ?>
                                <?php while($row = $materials_result->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars("{$row['item_code']} - {$row['name']}"); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_id" class="form-label">2. เลือกที่เก็บ / Lot / Batch ที่จะปรับ</label>
                        <select id="inventory_id" name="inventory_id" class="form-select" required disabled>
                            <option value="">-- (กรุณาเลือกวัสดุก่อน) --</option>
                        </select>
                        <div id="stock-info" class="form-text text-primary fw-bold mt-2"></div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="mb-3">
                         <label class="form-label">3. ประเภทการปรับ</label>
                         <div class="form-check">
                            <input class="form-check-input" type="radio" name="adjustment_type" id="type_in" value="ADJ-IN" required>
                            <label class="form-check-label text-success fw-bold" for="type_in">
                                ADJ-IN (ปรับเข้า) <span class="fw-normal text-muted">- เช่น นับสต็อกเจอของเกิน</span>
                            </label>
                         </div>
                         <div class="form-check">
                            <input class="form-check-input" type="radio" name="adjustment_type" id="type_out" value="ADJ-OUT" required>
                            <label class="form-check-label text-danger fw-bold" for="type_out">
                                ADJ-OUT (ปรับออก) <span class="fw-normal text-muted">- เช่น แตกหัก, เสียหาย, นับสต็อกขาด</span>
                            </label>
                         </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity_adjusted" class="form-label">4. จำนวนที่จะปรับ</label>
                        <input type="number" class="form-control" id="quantity_adjusted" name="quantity_adjusted" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">5. เหตุผล (สำคัญมาก)</label>
                        <input type="text" class="form-control" id="reason" name="reason" placeholder="เช่น 'นับสต็อกประจำปี', 'แตกเสียหายระหว่างจัดเก็บ'" required>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="bi bi-save-fill me-2"></i> ยืนยันการปรับปรุงสต็อก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const materialSelect = document.getElementById('material_id');
    const lotSelect = document.getElementById('inventory_id');
    const stockInfo = document.getElementById('stock-info');

    // (เมื่อเลือก Material)
    materialSelect.addEventListener('change', function() {
        const materialId = this.value;
        lotSelect.disabled = true;
        lotSelect.innerHTML = '<option value="">-- กำลังโหลด... --</option>';
        stockInfo.innerHTML = '';

        if (!materialId) {
            lotSelect.innerHTML = '<option value="">-- (กรุณาเลือกวัสดุก่อน) --</option>';
            return;
        }

        // ⭐️ (เอา Comment ออก) ⭐️
        // (ยิง API ไปยังไฟล์ที่เราสร้างไว้)
        fetch(`api_get_inventory_lots.php?material_id=${materialId}`)
            .then(response => {
                if (!response.ok) { throw new Error('Network error'); }
                return response.json();
            })
            .then(data => {
                lotSelect.disabled = false;
                lotSelect.innerHTML = '<option value="">-- กรุณาเลือก Lot/Batch ที่จะปรับ --</option>';
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                if (data.length === 0) {
                     lotSelect.innerHTML = '<option value="">-- ไม่พบสต็อกของวัสดุนี้ --</option>';
                     return;
                }

                data.forEach(lot => {
                    // (คำนวณยอด Available)
                    let onHand = parseFloat(lot.quantity) || 0;
                    let reserved = parseFloat(lot.quantity_reserved) || 0;
                    let available = onHand - reserved;
                    
                    let option = document.createElement('option');
                    option.value = lot.id;
                    option.textContent = `${lot.location_code} | Batch: ${lot.batch_number} (On-Hand: ${onHand}, Reserved: ${reserved}, Available: ${available})`;
                    option.dataset.onHand = onHand; // (เก็บค่าไว้แสดงผล)
                    option.dataset.reserved = reserved;
                    lotSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Fetch error:', error);
                lotSelect.innerHTML = `<option value="">-- เกิดข้อผิดพลาด: ${error.message} --</option>`;
            });
    });
    
    // (เมื่อเลือก Lot)
    lotSelect.addEventListener('change', function() {
         const selectedOption = this.options[this.selectedIndex];
         if (selectedOption && selectedOption.value) {
             const onHand = selectedOption.dataset.onHand;
             const reserved = selectedOption.dataset.reserved;
             stockInfo.innerHTML = `(Lot นี้มี On-Hand: <b>${onHand}</b> / ถูกจอง: <b>${reserved}</b>)`;
         } else {
             stockInfo.innerHTML = '';
         }
    });

});
</script>


<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>