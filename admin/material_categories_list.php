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
    die("Access Denied: You do not have permission to manage categories.");
}

// 5. ⭐️ ย้ายการ xử lý POST (Add/Edit/Delete) มาไว้บนสุด ⭐️
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {

    $action = $_POST['action'];

    try {
        if ($action == 'add') {
            // --- ADD (เพิ่มข้อมูลใหม่) ---
            $name = $_POST['name'];
            $description = $_POST['description'];
            if (empty($name)) throw new Exception("กรุณากรอกชื่อหมวดหมู่");

            $stmt = $conn->prepare("INSERT INTO material_categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            $stmt->execute();
            $_SESSION['alert_message'] = "เพิ่มหมวดหมู่ ($name) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();

        } elseif ($action == 'edit' && isset($_POST['category_id'])) {
            // --- EDIT (อัปเดตข้อมูล) ---
            $category_id = (int)$_POST['category_id'];
            $name = $_POST['name'];
            $description = $_POST['description'];
            if ($category_id <= 0) throw new Exception("Invalid Category ID.");
            if (empty($name)) throw new Exception("กรุณากรอกชื่อหมวดหมู่");

            $stmt = $conn->prepare("UPDATE material_categories SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $description, $category_id);
            $stmt->execute();
            $_SESSION['alert_message'] = "อัปเดตข้อมูลหมวดหมู่ ($name) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();
            
        } elseif ($action == 'delete' && isset($_POST['category_id'])) {
            // --- DELETE (ลบข้อมูล) ---
            $category_id = (int)$_POST['category_id'];
            $name = $_POST['name']; // (สำหรับแสดงผล)
            if ($category_id <= 0) throw new Exception("Invalid Category ID.");

            // (เช็คก่อนว่ามี Material ใช้งานหมวดหมู่นี้หรือไม่)
            $check_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM materials WHERE category_id = ?");
            $check_stmt->bind_param("i", $category_id);
            $check_stmt->execute();
            $count = $check_stmt->get_result()->fetch_assoc()['count'];
            $check_stmt->close();
            
            if ($count > 0) {
                 throw new Exception("ไม่สามารถลบหมวดหมู่ ($name) ได้ เพราะมีวัสดุ ($count รายการ) ใช้งานอยู่");
            }

            // (ถ้าไม่มี, ลบได้)
            $stmt = $conn->prepare("DELETE FROM material_categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            
            $_SESSION['alert_message'] = "ลบหมวดหมู่ ($name) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();
            
        } else {
             throw new Exception("Invalid action or Category ID.");
        }

    } catch (Exception $e) {
        if ($conn->errno == 1062) { $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: ชื่อหมวดหมู่ '$name' นี้มีอยู่แล้ว"; }
        else { $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage(); }
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect กลับมาที่หน้าเดิมเสมอ
    header("Location: material_categories_list.php");
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

// --- 8. ดึงข้อมูลหมวดหมู่ทั้งหมดมาแสดง ---
$sql_categories = "SELECT * FROM material_categories ORDER BY name ASC";
$categories_result = $conn->query($sql_categories);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-tags-fill me-2"></i>จัดการหมวดหมู่วัสดุ</h1>
    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#categoryModal" id="add-btn">
        <i class="bi bi-plus-circle-fill"></i> เพิ่มหมวดหมู่ใหม่
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
        <h5 class="mb-0">รายการหมวดหมู่ทั้งหมด</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ชื่อหมวดหมู่ (Name)</th>
                        <th>รายละเอียด (Description)</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                        <?php while($row = $categories_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#categoryModal"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                            data-desc="<?php echo htmlspecialchars($row['description'] ?? ''); ?>">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </button>
                                     
                                    <form action="material_categories_list.php" method="POST" class="d-inline" onsubmit="return confirmDelete('<?php echo htmlspecialchars(addslashes($row['name'])); ?>')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($row['name']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash-fill"></i> ลบ
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูลหมวดหมู่</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="material_categories_list.php" method="POST" id="category-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มหมวดหมู่ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="category_id" id="form-category-id" value="0">
                    
                    <div class="mb-3">
                        <label for="form-name" class="form-label">ชื่อหมวดหมู่ (Name)</label>
                        <input type="text" class="form-control" id="form-name" name="name" required>
                    </div>
                    <div class="mb-3">
                         <label for="form-desc" class="form-label">รายละเอียด (Description)</label>
                         <textarea class="form-control" id="form-desc" name="description" rows="3"></textarea>
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
// Function for Delete Confirmation
function confirmDelete(name) {
    return confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบหมวดหมู่ "${name}" ? \nคุณจะลบได้ก็ต่อเมื่อไม่มีวัสดุใดใช้งานหมวดหมู่นี้อยู่`);
}

document.addEventListener("DOMContentLoaded", function() {
    
    const modal = document.getElementById('categoryModal');
    const modalTitle = document.getElementById('modalTitle');
    const saveBtn = document.getElementById('save-btn');
    const form = document.getElementById('category-form');
    
    const actionInput = document.getElementById('form-action');
    const idInput = document.getElementById('form-category-id');
    const nameInput = document.getElementById('form-name');
    const descInput = document.getElementById('form-desc');

    // --- 1. เมื่อคลิกปุ่ม "เพิ่ม" ---
    document.getElementById('add-btn').addEventListener('click', function() {
        form.reset();
        modalTitle.textContent = "เพิ่มหมวดหมู่ใหม่";
        saveBtn.textContent = "บันทึกข้อมูล";
        actionInput.value = "add";
        idInput.value = "0";
    });

    // --- 2. เมื่อคลิกปุ่ม "แก้ไข" ---
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            
            form.reset();
            modalTitle.textContent = "แก้ไขข้อมูลหมวดหมู่";
            saveBtn.textContent = "อัปเดตข้อมูล";
            actionInput.value = "edit";
            
            idInput.value = data.id || '0';
            nameInput.value = data.name || '';
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