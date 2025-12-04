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
    die("Access Denied: You do not have permission to manage suppliers.");
}

// 5. ⭐️ ย้ายการ xử lý POST (Add/Edit) มาไว้บนสุด ⭐️
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // ดึงข้อมูลจากฟอร์ม
    $action = $_POST['action'];
    $name = $_POST['name'];
    $contact_person = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $supplier_id = $_POST['supplier_id'] ?? 0;

    try {
        if ($action == 'add') {
            // --- ADD (เพิ่มข้อมูลใหม่) ---
            $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $contact_person, $phone, $email);
            $stmt->execute();
            $_SESSION['alert_message'] = "เพิ่มผู้ขาย ($name) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();

        } elseif ($action == 'edit' && $supplier_id > 0) {
            // --- EDIT (อัปเดตข้อมูล) ---
            $stmt = $conn->prepare("UPDATE suppliers 
                                   SET name = ?, contact_person = ?, phone = ?, email = ?
                                   WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $contact_person, $phone, $email, $supplier_id);
            $stmt->execute();
            $_SESSION['alert_message'] = "อัปเดตข้อมูลผู้ขาย ($name) สำเร็จ!";
            $_SESSION['alert_type'] = "success";
            $stmt->close();
        } else {
             throw new Exception("Invalid action or Supplier ID.");
        }
    } catch (Exception $e) {
        $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirect กลับมาที่หน้าเดิมเสมอ
    header("Location: suppliers_list.php");
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

// --- 8. ดึงข้อมูลผู้ขายทั้งหมดมาแสดง ---
$sql_suppliers = "SELECT * FROM suppliers ORDER BY name ASC";
$suppliers_result = $conn->query($sql_suppliers);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0"><i class="bi bi-building me-2"></i>จัดการข้อมูลผู้ขาย (Suppliers)</h1>
    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#supplierModal" id="add-supplier-btn">
        <i class="bi bi-plus-circle-fill"></i> เพิ่มผู้ขายใหม่
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
        <h5 class="mb-0">รายการผู้ขายทั้งหมด</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ชื่อผู้ขาย</th>
                        <th>ผู้ติดต่อ</th>
                        <th>เบอร์โทร</th>
                        <th>อีเมล</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($suppliers_result && $suppliers_result->num_rows > 0): ?>
                        <?php while($row = $suppliers_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_person'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#supplierModal"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                            data-contact="<?php echo htmlspecialchars($row['contact_person'] ?? ''); ?>"
                                            data-phone="<?php echo htmlspecialchars($row['phone'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </button>
                                    </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูลผู้ขาย</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="suppliers_list.php" method="POST" id="supplier-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มข้อมูลผู้ขาย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="supplier_id" id="form-supplier-id" value="0">
                    
                    <div class="mb-3">
                        <label for="form-name" class="form-label">ชื่อผู้ขาย (Name)</label>
                        <input type="text" class="form-control" id="form-name" name="name" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="form-contact" class="form-label">ผู้ติดต่อ (Contact Person)</label>
                            <input type="text" class="form-control" id="form-contact" name="contact_person">
                        </div>
                        <div class="col-md-6">
                            <label for="form-phone" class="form-label">เบอร์โทรศัพท์ (Phone)</label>
                            <input type="tel" class="form-control" id="form-phone" name="phone">
                        </div>
                    </div>
                    <div class="mt-3">
                         <label for="form-email" class="form-label">อีเมล (Email)</label>
                         <input type="email" class="form-control" id="form-email" name="email">
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
    
    const modal = document.getElementById('supplierModal');
    const modalTitle = document.getElementById('modalTitle');
    const saveBtn = document.getElementById('save-btn');
    const form = document.getElementById('supplier-form');
    
    // ดึงฟิลด์ในฟอร์ม
    const actionInput = document.getElementById('form-action');
    const idInput = document.getElementById('form-supplier-id');
    const nameInput = document.getElementById('form-name');
    const contactInput = document.getElementById('form-contact');
    const phoneInput = document.getElementById('form-phone');
    const emailInput = document.getElementById('form-email');

    // --- 1. เมื่อคลิกปุ่ม "เพิ่มผู้ขายใหม่" ---
    document.getElementById('add-supplier-btn').addEventListener('click', function() {
        form.reset();
        modalTitle.textContent = "เพิ่มข้อมูลผู้ขาย";
        saveBtn.textContent = "บันทึกข้อมูล";
        actionInput.value = "add";
        idInput.value = "0";
    });

    // --- 2. เมื่อคลิกปุ่ม "แก้ไข" (ในตาราง) ---
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            
            form.reset();
            modalTitle.textContent = "แก้ไขข้อมูลผู้ขาย";
            saveBtn.textContent = "อัปเดตข้อมูล";
            actionInput.value = "edit";
            
            idInput.value = data.id || '0';
            nameInput.value = data.name || '';
            contactInput.value = data.contact || '';
            phoneInput.value = data.phone || '';
            emailInput.value = data.email || '';
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