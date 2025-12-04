<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';

// 2. Helper Function ตรวจสอบสิทธิ์ (เผื่อกรณีไฟล์นี้ถูกเรียกโดดๆ)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 3. ตรวจสอบสิทธิ์ (ADMIN เท่านั้นที่มีสิทธิ์เข้าถึงหน้านี้)
if (!hasRole('ADMIN')) {
    die("Access Denied: You do not have permission to manage users.");
}

// 4. Logic การจัดการข้อมูล (POST Request: Edit / Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    $action = $_POST['action'];

    try {
        if ($action == 'edit') {
            // --- กรณีแก้ไขข้อมูล (Edit) ---
            $user_id = (int)$_POST['user_id'];
            $username = $_POST['username'];
            $full_name = $_POST['full_name'];
            $email = trim($_POST['email']); // ⭐️ รับค่า Email
            $role = $_POST['role'];
            $job_title = $_POST['job_title'];
            $password = $_POST['password'];
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : NULL;

            if ($user_id > 0) {
                // ป้องกันการลดสิทธิ์ตัวเอง
                if ($user_id == $_SESSION['user_id'] && $role != 'ADMIN') {
                     throw new Exception("ไม่สามารถลดสิทธิ์ผู้ดูแลระบบของตนเองได้");
                }
                
                // ตรวจสอบความถูกต้องของ Email
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("รูปแบบอีเมลไม่ถูกต้อง");
                }
                // (Optional) แปลง Email ว่างให้เป็น NULL ใน DB
                if (empty($email)) $email = NULL;

                if (empty($password)) {
                    // กรณีไม่เปลี่ยนรหัสผ่าน
                    $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, department_id = ?, job_title = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssisi", $username, $full_name, $email, $role, $department_id, $job_title, $user_id);
                } else {
                    // กรณีเปลี่ยนรหัสผ่าน
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, department_id = ?, job_title = ?, password_hash = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssiisi", $username, $full_name, $email, $role, $department_id, $job_title, $password_hash, $user_id);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                $_SESSION['alert_message'] = "อัปเดตข้อมูลผู้ใช้ ($username) สำเร็จ!";
                $_SESSION['alert_type'] = "success";
                $stmt->close();

            } else {
                 throw new Exception("User ID ไม่ถูกต้อง");
            }

        } elseif ($action == 'delete') {
            // --- กรณีลบข้อมูล (Delete) ---
            $user_id_to_delete = (int)$_POST['user_id_to_delete'];
            $username_to_delete = $_POST['username_to_delete'];

            if ($user_id_to_delete == $_SESSION['user_id']) {
                throw new Exception("ไม่สามารถลบบัญชีผู้ใช้ของตนเองได้");
            } elseif ($user_id_to_delete > 0) {
                // ป้องกันการลบ Admin หลัก (Admin คนสุดท้าย)
                // (Logic นี้อาจต้องปรับถ้าอนุญาตให้มี Admin หลายคนและลบกันเองได้)
                $check_role = $conn->query("SELECT role FROM users WHERE id = $user_id_to_delete")->fetch_assoc();
                if ($check_role && $check_role['role'] == 'ADMIN') {
                    // เช็คว่าเหลือ Admin กี่คน
                    $count_admin = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='ADMIN'")->fetch_assoc()['c'];
                    if ($count_admin <= 1) {
                        throw new Exception("ไม่สามารถลบผู้ใช้ ADMIN คนสุดท้ายได้");
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id_to_delete);
                
                try {
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['alert_message'] = "ลบผู้ใช้ ($username_to_delete) สำเร็จ!";
                        $_SESSION['alert_type'] = "success";
                    } else {
                        throw new Exception("ไม่พบผู้ใช้ที่ต้องการลบ");
                    }
                } catch (mysqli_sql_exception $e) {
                    // ดักจับ Error Foreign Key (กรณี User เคยทำรายการไปแล้ว)
                    throw new Exception("ไม่สามารถลบได้ เนื่องจากผู้ใช้นี้มีประวัติการทำรายการในระบบ (Foreign Key Constraint)");
                }
                $stmt->close();
            }
        }

    } catch (Exception $e) {
        if ($conn->errno == 1062) { 
            $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: Username หรือ Email ซ้ำกันในระบบ"; 
        } else { 
            $_SESSION['alert_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage(); 
        }
        $_SESSION['alert_type'] = "danger";
    }

    // Redirect กลับมาหน้าเดิม
    header("Location: users_list.php");
    exit();
}

// 5. เตรียมข้อมูลสำหรับแสดงผล (View Logic)
// ตรวจสอบว่ามี Admin อย่างน้อย 1 คนหรือไม่ (เพื่อใช้ Lock ปุ่ม Role ใน JS)
$admin_check_query = $conn->query("SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1");
$admin_exists = ($admin_check_query && $admin_check_query->num_rows > 0);
$existing_admin_id = $admin_exists ? $admin_check_query->fetch_assoc()['id'] : null;

// ดึง Alert Message
$message = ""; $message_type = "";
if (isset($_SESSION['alert_message'])) {
    $message = $_SESSION['alert_message']; 
    $message_type = $_SESSION['alert_type'];
    unset($_SESSION['alert_message']); unset($_SESSION['alert_type']);
}

// 6. เรียก Header
require_once '../includes/header.php';

// ดึงข้อมูล Master Data (แผนก)
$departments_result = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");

// ดึงข้อมูล Users ทั้งหมด (เพิ่ม field email)
$sql_users = "SELECT u.id, u.username, u.full_name, u.email, u.role, u.job_title, u.department_id, d.name AS department_name
              FROM users u LEFT JOIN departments d ON u.department_id = d.id
              ORDER BY u.username ASC";
$users_result = $conn->query($sql_users);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill me-2 text-primary"></i>จัดการข้อมูลผู้ใช้งาน</h1>
    <a href="user_batch_add.php" class="btn btn-primary btn-lg shadow-sm rounded-pill hover-scale">
        <i class="bi bi-person-plus-fill me-2"></i> เพิ่มผู้ใช้หลายคน
    </a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="mb-0 text-secondary fw-bold">รายการผู้ใช้งานทั้งหมด</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary">
                    <tr>
                        <th class="ps-4 text-uppercase font-size-sm">Username</th>
                        <th class="text-uppercase font-size-sm">ชื่อ-นามสกุล</th>
                        <th class="text-uppercase font-size-sm">อีเมล</th> <th class="text-uppercase font-size-sm">ตำแหน่งงาน</th>
                        <th class="text-uppercase font-size-sm">สิทธิ์ (Role)</th>
                        <th class="text-uppercase font-size-sm">แผนก</th>
                        <th class="text-center pe-4 text-uppercase font-size-sm">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                        <?php while($row = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="fw-medium"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="text-muted small">
                                    <?php 
                                        if(!empty($row['email'])) {
                                            echo '<i class="bi bi-envelope me-1"></i>' . htmlspecialchars($row['email']);
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['job_title'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                        $role_badge = 'bg-secondary';
                                        if($row['role'] == 'ADMIN') $role_badge = 'bg-danger';
                                        elseif(strpos($row['role'], 'MANAGER') !== false) $role_badge = 'bg-success';
                                        elseif(strpos($row['role'], 'STAFF') !== false) $role_badge = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?php echo $role_badge; ?> bg-opacity-75 rounded-pill px-3"><?php echo $row['role']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'ส่วนกลาง'); ?></td>
                                <td class="text-center pe-4">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-light btn-sm text-warning border hover-shadow edit-btn"
                                                title="แก้ไข"
                                                data-bs-toggle="modal" data-bs-target="#userModal"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                data-full_name="<?php echo htmlspecialchars($row['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>" 
                                                data-role="<?php echo $row['role']; ?>"
                                                data-job_title="<?php echo htmlspecialchars($row['job_title'] ?? ''); ?>"
                                                data-dept_id="<?php echo $row['department_id'] ?? ''; ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                        <form action="users_list.php" method="POST" class="d-inline" onsubmit="return confirmDelete('<?php echo htmlspecialchars(addslashes($row['username'])); ?>')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id_to_delete" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="username_to_delete" value="<?php echo htmlspecialchars($row['username']); ?>">
                                            <button type="submit" class="btn btn-light btn-sm text-danger border hover-shadow ms-1" title="ลบ">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">ยังไม่มีข้อมูลผู้ใช้งานในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="users_list.php" method="POST" id="user-form">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary" id="modalTitle"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลผู้ใช้</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-4">
                    <input type="hidden" name="action" id="form-action" value="edit">
                    <input type="hidden" name="user_id" id="form-user-id" value="0">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Username</label>
                            <input type="text" class="form-control bg-light" id="form-username" name="username" readonly required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control" id="form-full-name" name="full_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">อีเมล (Email)</label>
                            <input type="email" class="form-control" id="form-email" name="email" placeholder="user@company.com">
                            <div class="form-text small">ใช้สำหรับรับการแจ้งเตือนงานต่างๆ</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">ตำแหน่งงาน (Job Title)</label>
                            <select id="form-job-title" name="job_title" class="form-select">
                                <option value="">-- ไม่ระบุ --</option>
                                <option value="Staff">พนักงาน (Staff)</option>
                                <option value="Assistant Manager">รอง ผจก. (Assistant Manager)</option>
                                <option value="Manager">ผู้จัดการ (Manager)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">สิทธิ์ (Role) <span class="text-danger">*</span></label>
                            <select id="form-role" name="role" class="form-select" required>
                                <optgroup label="Admin & Warehouse">
                                    <option value="ADMIN">ADMIN (ผู้ดูแลระบบ)</option>
                                    <option value="WH_MANAGER">WH_MANAGER (ผจก. พัสดุ)</option>
                                    <option value="WH_STAFF">WH_STAFF (พนักงานพัสดุ)</option>
                                </optgroup>
                                <optgroup label="Department Users">
                                    <option value="DEPT_MANAGER">DEPT_MANAGER (ผจก. แผนกอื่น)</option>
                                    <option value="DEPT_STAFF">DEPT_STAFF (พนักงานแผนกอื่น)</option>
                                </optgroup>
                            </select>
                            <div id="role-warning" class="form-text text-warning" style="display:none;"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">แผนก (Department)</label>
                            <select id="form-department" name="department_id" class="form-select">
                                <option value="">-- ไม่ระบุ (ส่วนกลาง) --</option>
                                <?php
                                if ($departments_result && $departments_result->num_rows > 0) {
                                    $departments_result->data_seek(0);
                                    while($dept = $departments_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endwhile; } ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-3 mt-2 border border-light" style="background-color: #f8f9fa;">
                                <label class="form-label fw-bold text-danger"><i class="bi bi-key-fill me-1"></i> เปลี่ยนรหัสผ่านใหม่</label>
                                <input type="password" class="form-control" id="form-password" name="password" placeholder="กรอกเฉพาะเมื่อต้องการเปลี่ยนรหัสผ่าน">
                                <div class="form-text">หากไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นว่างไว้</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 pe-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="save-btn">
                        <i class="bi bi-save-fill me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .font-size-sm { font-size: 0.85rem; letter-spacing: 0.5px; }
    .hover-shadow:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; transform: translateY(-2px); transition: all 0.2s; }
    .hover-scale:hover { transform: scale(1.02); transition: transform 0.2s; }
</style>

<script>
// ฟังก์ชันยืนยันการลบ
function confirmDelete(username) {
    return confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ "${username}" ? \n\n⚠️ คำเตือน: หากผู้ใช้นี้เคยทำรายการในระบบแล้ว จะไม่สามารถลบได้ (Foreign Key Constraint)`);
}

document.addEventListener("DOMContentLoaded", function() {
    
    // รับค่า PHP ตัวแปร Admin Check
    const adminExists = <?php echo json_encode($admin_exists); ?>;
    const existingAdminId = <?php echo json_encode($existing_admin_id); ?>;
    
    // Elements
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('user-form');
    const actionInput = document.getElementById('form-action');
    const idInput = document.getElementById('form-user-id');
    const usernameInput = document.getElementById('form-username');
    const fullNameInput = document.getElementById('form-full-name');
    const emailInput = document.getElementById('form-email'); // ⭐️
    const roleInput = document.getElementById('form-role');
    const jobTitleInput = document.getElementById('form-job-title');
    const deptInput = document.getElementById('form-department');
    const passwordInput = document.getElementById('form-password');
    const roleWarning = document.getElementById('role-warning');

    // Event Listener ปุ่มแก้ไข
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;

            form.reset(); 
            // modalTitle.textContent = "แก้ไขข้อมูลผู้ใช้: " + data.username;
            actionInput.value = "edit";

            // Populate Data
            idInput.value = data.id || '';
            usernameInput.value = data.username || '';
            fullNameInput.value = data.full_name || '';
            emailInput.value = data.email || ''; // ⭐️ ใส่ค่า Email
            roleInput.value = data.role || ''; 
            jobTitleInput.value = data.job_title || ''; 
            deptInput.value = data.dept_id || ''; 

            // Logic ป้องกันการตั้ง Admin ซ้ำ (Optional: ขึ้นอยู่กับ Business Rule)
            const adminOption = roleInput.querySelector('option[value="ADMIN"]');
            roleWarning.style.display = 'none';
            roleWarning.textContent = '';

            if (adminExists && data.id != existingAdminId) {
                // ถ้ามี Admin อยู่แล้ว และคนนี้ไม่ใช่ Admin คนนั้น -> ห้ามเลือก Admin
                adminOption.disabled = true;
                // adminOption.textContent = "ADMIN (Locked - มีผู้ดูแลระบบแล้ว)";
            } else {
                adminOption.disabled = false;
                adminOption.textContent = "ADMIN (ผู้ดูแลระบบ)";
            }
            
            // กรณีเป็น Admin อยู่แล้ว จะแจ้งเตือนนิดหน่อย
            if (data.role === 'ADMIN') {
                roleWarning.style.display = 'block';
                roleWarning.textContent = '⚠️ บัญชีนี้คือผู้ดูแลระบบสูงสุด';
            }
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