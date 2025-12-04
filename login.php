<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'config/db_connect.php';

$error_message = "";

// (ฟังก์ชัน Redirect เหมือนเดิม)
function redirectUser($role) {
    if ($role == 'system_admin' || $role == 'warehouse_manager' || $role == 'warehouse_staff' || $role == 'ADMIN' || $role == 'WH_MANAGER' || $role == 'WH_STAFF') {
        header("Location: admin/index.php"); 
    } else {
        header("Location: user/index.php");
    }
    exit();
}

if (isset($_SESSION['user_id'])) redirectUser($_SESSION['role']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            redirectUser($user['role']);
        } else { $error_message = "รหัสผ่านไม่ถูกต้อง"; }
    } else { $error_message = "ไม่พบชื่อผู้ใช้นี้"; }
    $stmt->close(); $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css?v=3.0" rel="stylesheet"> 
    <style>
        /* Override เฉพาะหน้า Login ให้เต็มจอ */
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.65); /* กระจกขุ่น */
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 32px;
            padding: 3rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        .brand-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #007AFF, #00C6FF);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 25px rgba(0, 122, 255, 0.3);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.8);
            border: none;
            padding: 1rem;
            font-size: 1.1rem;
        }
        .btn-login {
            background: #FF9500; /* ส้ม */
            border: none;
            padding: 1rem;
            font-size: 1.2rem;
            width: 100%;
            margin-top: 1.5rem;
            color: white;
        }
        .btn-login:hover {
            background: #E08600;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 149, 0, 0.25);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-icon">
        <i class="bi bi-box-seam-fill"></i>
    </div>
    <h2 class="text-center fw-bold mb-4" style="color: #1C1C1E;">WMS Login</h2>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger border-0 rounded-4 text-center">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="mb-3">
            <input type="text" class="form-control rounded-4" name="username" placeholder="Username" required autofocus>
        </div>
        <div class="mb-3">
            <input type="password" class="form-control rounded-4" name="password" placeholder="Password" required>
        </div>
        <button type="submit" class="btn btn-login btn-primary rounded-pill fw-bold">
            เข้าสู่ระบบ
        </button>
    </form>
    
    <p class="mt-4 text-center text-muted small">&copy; <?php echo date("Y"); ?> Warehouse Management System</p>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>
</html>