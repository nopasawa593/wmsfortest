<?php 
// 1. ⭐️ เริ่ม Session และเชื่อมต่อ DB (เรียกตรงนี้ก่อน เพราะเรายังไม่โหลด header)
require_once '../config/db_connect.php'; 
require_once '../includes/auth_check.php';

// 2. ⭐️ นิยามฟังก์ชัน hasRole ชั่วคราว (เพื่อให้ตรวจสอบสิทธิ์ได้ก่อนโหลด header)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 3. ตรวจสอบสิทธิ์
if (!hasRole('ADMIN')) {
    die("Access Denied: You do not have permission to access this page.");
}

// --- 4. ⭐️ AJAX Handler (ทำงานก่อนแสดงผล HTML เสมอ) ⭐️ ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_department_ajax') {
    // ปิด Buffer ขยะที่อาจติดมา
    ob_clean();
    header('Content-Type: application/json');
    
    $dept_name = trim($_POST['new_dept_name']);
    $dept_desc = trim($_POST['new_dept_desc']);
    
    if (empty($dept_name)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อแผนก']);
        exit();
    }

    // เช็คชื่อซ้ำ
    $check = $conn->prepare("SELECT id FROM departments WHERE name = ?");
    $check->bind_param("s", $dept_name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'ชื่อแผนกนี้มีอยู่แล้ว']);
        exit();
    }

    // บันทึก
    $stmt = $conn->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $dept_name, $dept_desc);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'id' => $stmt->insert_id, 
            'name' => $dept_name
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $conn->error]);
    }
    exit(); // ⭐️ สำคัญมาก: ต้องจบการทำงานทันที ห้ามให้ HTML ด้านล่างหลุดออกไป
}

// --- 5. Logic การบันทึกข้อมูลผู้ใช้ (POST Request หลัก) ---
$message = ""; 
$message_type = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    
    $department_id = $_POST['department_id'];
    $employee_ids = $_POST['employee_id'];
    $first_names = $_POST['first_name'];
    $last_names = $_POST['last_name'];
    $job_titles = $_POST['job_title'];
    $passwords = $_POST['password'];
    
    $conn->begin_transaction();
    $users_added = 0;
    
    try {
        // ดึงชื่อแผนก
        $dept_name_query = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        $dept_name_query->bind_param("i", $department_id);
        $dept_name_query->execute();
        $res = $dept_name_query->get_result();
        if ($res->num_rows == 0) throw new Exception("ไม่พบข้อมูลแผนก ID: $department_id");
        $department_name = $res->fetch_assoc()['name'];
        $dept_name_query->close();
        
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role, department_id, job_title) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($employee_ids as $index => $emp_id) {
            if (empty($emp_id) || empty($first_names[$index]) || empty($passwords[$index])) continue;

            $username = strtoupper(trim($emp_id));
            $full_name = trim($first_names[$index] . ' ' . $last_names[$index]);
            $job_title = $job_titles[$index];
            $password_hash = password_hash($passwords[$index], PASSWORD_DEFAULT);
            
            // Auto Role Logic
            $role = ''; 
            if ($department_name == 'Warehouse') {
                $role = ($job_title == 'Manager' || $job_title == 'Assistant Manager') ? 'WH_MANAGER' : 'WH_STAFF';
            } else {
                $role = ($job_title == 'Manager' || $job_title == 'Assistant Manager') ? 'DEPT_MANAGER' : 'DEPT_STAFF';
            }
            
            $stmt->bind_param("ssssis", $username, $password_hash, $full_name, $role, $department_id, $job_title);
            $stmt->execute();
            $users_added++;
        }
        
        if ($users_added == 0) {
            throw new Exception("ไม่พบข้อมูลผู้ใช้ที่ถูกต้องให้บันทึก");
        }
        $conn->commit();
        $message = "บันทึกผู้ใช้สำเร็จ {$users_added} คน ในแผนก: " . $department_name;
        $message_type = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = ($conn->errno == 1062) ? "เกิดข้อผิดพลาด: รหัสพนักงาน/Username ซ้ำกัน" : "เกิดข้อผิดพลาด: " . $e->getMessage();
        $message_type = "danger";
    }
}

// --- 6. ⭐️ เรียก Header และ HTML (ย้ายมาไว้ตรงนี้) ⭐️ ---
require_once '../includes/header.php'; 

// ดึงข้อมูล Departments (เพื่อแสดงใน Dropdown)
$departments_result = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
?>

<h1 class="mb-4">เพิ่มผู้ใช้งานหลายคน (Batch Entry)</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="user_batch_add.php" method="POST" id="batch-form">
    
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-building"></i> กำหนดแผนก</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="department_id" class="form-label">แผนก (Department)</label>
                    <div class="input-group">
                        <select id="department_id" name="department_id" class="form-select" required>
                            <option value="">-- เลือกแผนกที่จะเพิ่มผู้ใช้ --</option>
                            <?php 
                            if($departments_result) {
                                $departments_result->data_seek(0);
                                while($dept = $departments_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                            <i class="bi bi-plus-lg"></i> เพิ่มแผนกใหม่
                        </button>
                    </div>
                    <div class="form-text">หากไม่มีแผนกที่ต้องการ สามารถกดปุ่ม "+" เพื่อเพิ่มได้ทันที</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-people"></i> รายการผู้ใช้งานที่จะเพิ่ม</h5>
            <button type="button" class="btn btn-success btn-sm" id="add-item-btn">
                <i class="bi bi-person-add"></i> เพิ่มแถวผู้ใช้
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle" id="user-item-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">รหัสพนักงาน/Username</th>
                            <th style="width: 15%;">ชื่อจริง</th>
                            <th style="width: 15%;">นามสกุล</th>
                            <th style="width: 15%;">ตำแหน่ง</th>
                            <th style="width: 20%;">รหัสผ่าน</th>
                            <th style="width: 10%;">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="item-list"></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save-fill"></i> บันทึกผู้ใช้ทั้งหมด
        </button>
    </div>
</form>

<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-building-add"></i> เพิ่มแผนกใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-dept-form">
                    <div class="mb-3">
                        <label for="new_dept_name" class="form-label">ชื่อแผนก (Department Name)</label>
                        <input type="text" class="form-control" id="new_dept_name" name="new_dept_name" required placeholder="เช่น Marketing, Accounting">
                    </div>
                    <div class="mb-3">
                        <label for="new_dept_desc" class="form-label">รายละเอียด (Description)</label>
                        <textarea class="form-control" id="new_dept_desc" name="new_dept_desc" rows="3" placeholder="รายละเอียดเพิ่มเติมเกี่ยวกับแผนก"></textarea>
                    </div>
                    <div id="dept-msg" class="text-danger"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success" id="save-dept-btn">บันทึกแผนก</button>
            </div>
        </div>
    </div>
</div>

<table style="display: none;">
    <tbody id="item-template">
        <tr>
            <td><input type="text" name="employee_id[]" class="form-control form-control-sm" placeholder="เช่น E001" required></td>
            <td><input type="text" name="first_name[]" class="form-control form-control-sm" required></td>
            <td><input type="text" name="last_name[]" class="form-control form-control-sm" required></td>
            <td>
                <select name="job_title[]" class="form-select form-select-sm" required>
                    <option value="Staff">พนักงาน</option>
                    <option value="Assistant Manager">รอง ผจก.แผนก</option>
                    <option value="Manager">ผจก.แผนก</option>
                </select>
            </td>
            <td><input type="password" name="password[]" class="form-control form-control-sm" required></td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="bi bi-trash-fill"></i></button>
            </td>
        </tr>
    </tbody>
</table>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Logic เพิ่ม/ลบแถวผู้ใช้
    const addBtn = document.getElementById("add-item-btn");
    const itemList = document.getElementById("item-list");
    const templateRow = document.getElementById("item-template").firstElementChild.cloneNode(true);

    function addRow() {
        const newRow = templateRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value = '');
        newRow.querySelector('select').value = 'Staff';
        itemList.appendChild(newRow);
    }
    addRow(); 

    addBtn.addEventListener("click", addRow);

    itemList.addEventListener("click", function(e) {
        if (e.target.closest(".remove-item-btn")) {
            if (itemList.children.length > 1) {
                 e.target.closest("tr").remove();
            } else {
                 alert("ต้องเหลือรายการอย่างน้อย 1 แถว");
            }
        }
    });

    // 2. Logic AJAX เพิ่มแผนก
    const saveDeptBtn = document.getElementById('save-dept-btn');
    const deptMsg = document.getElementById('dept-msg');
    const deptSelect = document.getElementById('department_id');
    const deptModalEl = document.getElementById('addDeptModal');
    const deptModal = new bootstrap.Modal(deptModalEl);

    saveDeptBtn.addEventListener('click', function() {
        const name = document.getElementById('new_dept_name').value;
        const desc = document.getElementById('new_dept_desc').value;
        
        if(!name) {
            deptMsg.textContent = "กรุณากรอกชื่อแผนก";
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_department_ajax');
        formData.append('new_dept_name', name);
        formData.append('new_dept_desc', desc);

        saveDeptBtn.disabled = true;
        saveDeptBtn.textContent = 'กำลังบันทึก...';

        fetch('user_batch_add.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                // 1. เพิ่ม Option ใหม่เข้า Dropdown
                const option = new Option(data.name, data.id);
                deptSelect.add(option);
                
                // 2. เลือก Option นั้นทันที
                deptSelect.value = data.id;
                
                // 3. ปิด Modal และเคลียร์ค่า
                document.getElementById('add-dept-form').reset();
                deptMsg.textContent = '';
                deptModal.hide();
                
                // แจ้งเตือน (Optional)
                // alert('เพิ่มแผนก ' + data.name + ' เรียบร้อยแล้ว');
            } else {
                deptMsg.textContent = data.message;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            deptMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ (JSON Error)';
        })
        .finally(() => {
            saveDeptBtn.disabled = false;
            saveDeptBtn.textContent = 'บันทึกแผนก';
        });
    });
});
</script>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>