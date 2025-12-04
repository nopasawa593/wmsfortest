<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php';

// 2. เรียกใช้ auth_check.php เพื่อให้แน่ใจว่า Login แล้ว
require_once '../includes/auth_check.php';

// 3. กำหนดฟังก์ชัน hasRole (ถ้ายังไม่มี)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 4. ⭐️ ตรวจสอบสิทธิ์ (ต้องทำก่อน xử lý POST) ⭐️
// (อนุญาตให้ทีมพัสดุทั้งหมดเข้าได้)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to manage locations.");
}

// 5. ⭐️ ย้ายการ xử lý POST (Add/Edit) มาไว้บนสุด ⭐️
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {

    $action = $_POST['action'];
    $location_code = $_POST['location_code'];
    $description = $_POST['description'];
    $location_id = $_POST['location_id'] ?? 0;

    try {
        if ($action == 'add') {
            // --- ADD (เพิ่มข้อมูลใหม่) ---
            $stmt = $conn->prepare("INSERT INTO locations (location_code, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $location_code, $description);
            $stmt->execute();
            $_SESSION['alert_message'] = "เพิ่มที่จัดเก็บ ($location_code) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();

        } elseif ($action == 'edit' && $location_id > 0) {
            // --- EDIT (อัปเดตข้อมูล) ---
            // (ปกติเราไม่ควรอัปเดต location_code แต่ในตัวอย่างนี้อนุญาตให้แก้ description)
            $stmt = $conn->prepare("UPDATE locations SET location_code = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $location_code, $description, $location_id);
            $stmt->execute();
            $_SESSION['alert_message'] = "อัปเดตข้อมูลที่จัดเก็บ ($location_code) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();
        } else {
             throw new Exception("Invalid action or Location ID.");
        }
    } catch (Exception $e) {
        // (ดักจับ Error กรณี location_code ซ้ำ)
        if ($conn->errno == 1062) { 
             $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: รหัสที่จัดเก็บ '$location_code' นี้มีอยู่แล้ว";
        } else {
             $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect กลับมาที่หน้าเดิมเสมอ
    header("Location: locations_list.php");
    exit();
}
// --- จบส่วน xử lý POST ---


// 6. ดึง Alert Message (ถ้ามี)
$message = "";
$message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message'];
    $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
}

// 7. *หลังจาก* xử lý POST แล้ว ค่อย include header.php
require_once '../includes/header.php'; 

// --- 8. ดึงข้อมูลที่จัดเก็บทั้งหมดมาแสดง ---
$sql_locations = "SELECT * FROM locations ORDER BY location_code ASC";
$locations_result = $conn->query($sql_locations);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-geo-alt me-2"></i>จัดการที่จัดเก็บ (Locations)</h1>
    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#locationModal" id="add-location-btn">
        <i class="bi bi-plus-circle-fill"></i> เพิ่มที่จัดเก็บใหม่
    </button>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">รายการที่จัดเก็บทั้งหมด</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>รหัสที่จัดเก็บ (Code)</th>
                        <th>รายละเอียด (Description)</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($locations_result && $locations_result->num_rows > 0): ?>
                        <?php while($row = $locations_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['location_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#locationModal"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($row['location_code']); ?>"
                                            data-desc="<?php echo htmlspecialchars($row['description'] ?? ''); ?>">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </button>
                                     </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูลที่จัดเก็บ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="locations_list.php" method="POST" id="location-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มข้อมูลที่จัดเก็บ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="location_id" id="form-location-id" value="0">
                    
                    <div class="mb-3">
                        <label for="form-location-code" class="form-label">รหัสที่จัดเก็บ (Location Code)</label>
                        <input type="text" class="form-control" id="form-location-code" name="location_code" placeholder="เช่น A-01-R1-B01" required>
                    </div>
                    <div class="mb-3">
                         <label for="form-desc" class="form-label">รายละเอียด (Description)</label>
                         <textarea class="form-control" id="form-desc" name="description" rows="3" placeholder="เช่น โซน A แถว 1 ชั้น 1 ช่อง 1"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="save-btn">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const modal = document.getElementById('locationModal');
    const modalTitle = document.getElementById('modalTitle');
    const saveBtn = document.getElementById('save-btn');
    const form = document.getElementById('location-form');
    
    const actionInput = document.getElementById('form-action');
    const idInput = document.getElementById('form-location-id');
    const codeInput = document.getElementById('form-location-code');
    const descInput = document.getElementById('form-desc');

    // --- 1. เมื่อคลิกปุ่ม "เพิ่ม" ---
    document.getElementById('add-location-btn').addEventListener('click', function() {
        form.reset();
        modalTitle.textContent = "เพิ่มข้อมูลที่จัดเก็บ";
        saveBtn.textContent = "บันทึกข้อมูล";
        actionInput.value = "add";
        idInput.value = "0";
        codeInput.readOnly = false;
    });

    // --- 2. เมื่อคลิกปุ่ม "แก้ไข" ---
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            
            form.reset();
            modalTitle.textContent = "แก้ไขข้อมูลที่จัดเก็บ";
            saveBtn.textContent = "อัปเดตข้อมูล";
            actionInput.value = "edit";
            
            idInput.value = data.id || '0';
            codeInput.value = data.code || '';
            // ⭐️ (แก้ไข) ทำให้ code readOnly เมื่อแก้ไข
            codeInput.readOnly = true; 
            descInput.value = data.desc || '';
        });
    });

});
</script>

<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php'; 
?>