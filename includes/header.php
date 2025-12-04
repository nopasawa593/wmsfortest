<?php
// 1. เชื่อมต่อฐานข้อมูล (ใช้ DOCUMENT_ROOT เพื่อความแม่นยำของ Path)
require_once($_SERVER['DOCUMENT_ROOT'] . '/config/db_connect.php');

// 2. ตรวจสอบการ Login
require_once($_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php');

// 3. เริ่ม Session หากยังไม่ได้เริ่ม
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ดึงข้อมูลผู้ใช้จาก Session
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['full_name'] ?? 'Guest';
$user_id = $_SESSION['user_id'] ?? null;
$user_dept_id = $_SESSION['department_id'] ?? null;

// --- Helper Function: ตรวจสอบ Role ---
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        // ADMIN มีสิทธิ์เข้าถึงทุกอย่าง
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') {
            return true;
        }
        // ตรวจสอบตาม Array ที่ส่งมา
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// --- 4. Logic การดึงข้อมูลแจ้งเตือน (Badges) ---
// กำหนดตัวแปรเริ่มต้นเป็น 0
$pending_mr_count = 0;
$pending_po_count = 0;
$pending_pr_count = 0;
$pending_gi_count = 0;
$pending_pr_compare_count = 0;
$pending_invoice_count = 0;

// 4.1 แจ้งเตือนสำหรับ WH_MANAGER หรือ ADMIN (PO และ PR รออนุมัติ)
if ($user_role == 'WH_MANAGER' || $user_role == 'ADMIN') {
    // นับ PO รออนุมัติ
    $sql_po_count = "SELECT COUNT(id) AS count FROM purchase_orders WHERE status = 'Pending PO Approval'";
    $result_po = $conn->query($sql_po_count);
    if ($result_po) {
        $pending_po_count = (int)$result_po->fetch_assoc()['count'];
    }

    // นับ PR รออนุมัติ
    $sql_pr_count = "SELECT COUNT(id) AS count FROM purchase_requisitions WHERE status = 'Pending WH Approval'";
    $result_pr = $conn->query($sql_pr_count);
    if ($result_pr) {
        $pending_pr_count = (int)$result_pr->fetch_assoc()['count'];
    }
}

// 4.2 แจ้งเตือนสำหรับ WH_STAFF หรือ ADMIN (งานจ่ายของ และงานเทียบราคา)
if ($user_role == 'WH_STAFF' || $user_role == 'ADMIN') {
    // นับ MR รอจ่าย (Pending Issue)
    $sql_gi_count = "SELECT COUNT(id) AS count FROM requisitions WHERE status = 'Pending Issue'";
    $result_gi = $conn->query($sql_gi_count);
    if ($result_gi) {
        $pending_gi_count = (int)$result_gi->fetch_assoc()['count'];
    }

    // นับ PR ที่อนุมัติแล้ว รอเทียบราคา (Approved)
    $sql_pr_compare = "SELECT COUNT(id) AS count FROM purchase_requisitions WHERE status = 'Approved'";
    $result_pr_comp = $conn->query($sql_pr_compare);
    if ($result_pr_comp) {
        $pending_pr_compare_count = (int)$result_pr_comp->fetch_assoc()['count'];
    }
}

// 4.3 แจ้งเตือนสำหรับทีมพัสดุทุกคน (Invoice รอจ่ายเงิน)
if (hasRole(['WH_MANAGER', 'WH_STAFF', 'ADMIN'])) {
    $sql_inv_count = "SELECT COUNT(id) AS count FROM supplier_invoices WHERE status = 'Unpaid'";
    $result_inv = $conn->query($sql_inv_count);
    if ($result_inv) {
        $pending_invoice_count = (int)$result_inv->fetch_assoc()['count'];
    }
}

// 4.4 แจ้งเตือนสำหรับ DEPT_MANAGER (MR ของลูกน้องรออนุมัติ)
if ($user_role == 'DEPT_MANAGER' && !empty($user_dept_id)) {
    $sql_mr_dept = "SELECT COUNT(r.id) AS count
                    FROM requisitions r
                    JOIN users u ON r.requested_by_user_id = u.id
                    WHERE u.department_id = ? 
                    AND r.status = 'Pending Dept Approval'";
    $stmt_mr = $conn->prepare($sql_mr_dept);
    if ($stmt_mr) {
        $stmt_mr->bind_param("i", $user_dept_id);
        $stmt_mr->execute();
        $result_mr = $stmt_mr->get_result();
        if ($result_mr) {
            $pending_mr_count = (int)$result_mr->fetch_assoc()['count'];
        }
        $stmt_mr->close();
    }
}

// --- 5. Helper Function สร้าง HTML เมนูย่อย ---
function renderMenuItem($url, $text, $badge = 0, $badgeColor = 'danger') {
    $current_page = basename($_SERVER['PHP_SELF']);
    // ตรวจสอบว่าหน้าปัจจุบันตรงกับเมนูนี้หรือไม่
    $isActive = ($current_page == $url) ? 'active' : '';
    
    // สร้าง HTML Badge ถ้ามีตัวเลข > 0
    $badgeHtml = ($badge > 0) ? "<span class='badge bg-{$badgeColor} ms-auto'>{$badge}</span>" : "";
    
    // กำหนด Path ให้ถูกต้อง (รองรับการเรียกจาก folder admin หรือ user)
    // ถ้าไฟล์ปลายทางอยู่ใน admin แต่เราเรียกจาก user หรือกลับกัน
    $base_path = '';
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/admin/' . $url)) {
        $base_path = '/admin/';
    } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/user/' . $url)) {
        $base_path = '/user/';
    } else {
        // Fallback กรณีหาไม่เจอ (เช่น logout.php อยู่ root)
        $base_path = '/';
    }
    
    $full_href = $base_path . $url;

    return "<li class='sidebar-item'>
                <a class='sidebar-link {$isActive}' href='{$full_href}'>
                    {$text} {$badgeHtml}
                </a>
            </li>";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - <?php echo htmlspecialchars($user_name); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/style.css?v=2.0" rel="stylesheet">
</head>
<body>

<div id="wrapper">
    
    <nav id="sidebar-wrapper">
        <div class="sidebar-heading">
            <div class="d-flex align-items-center">
                <i class="bi bi-box-seam-fill fs-3 me-2 text-primary"></i>
                <span class="fw-bold">WMS System</span>
            </div>
        </div>

        <div class="sidebar-content">
            
            <a class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
               href="<?php echo hasRole(['WH_MANAGER', 'WH_STAFF', 'ADMIN']) ? '/admin/index.php' : '/user/index.php'; ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
            </a>

            <?php if (hasRole(['WH_MANAGER', 'WH_STAFF', 'ADMIN'])): ?>
                
                <div class="sidebar-header">Main Menu</div>

                <a class="sidebar-link collapsed" data-bs-toggle="collapse" href="#transactionMenu" role="button" aria-expanded="false" aria-controls="transactionMenu">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Transactions</span>
                    <i class="bi bi-chevron-right arrow"></i>
                </a>
                <div class="collapse" id="transactionMenu">
                    <ul class="sidebar-dropdown">
                        <?php if (hasRole(['WH_STAFF', 'ADMIN'])): ?>
                            <?php echo renderMenuItem('pr_auto_create.php', 'สร้าง PR (Auto)'); ?>
                            <?php echo renderMenuItem('pr_create.php', 'สร้างใบขอซื้อ (PR)'); ?>
                        <?php endif; ?>
                        
                        <?php 
                            $pr_text = "รายการใบขอซื้อ (PR)";
                            // ถ้าเป็น Manager แสดงยอดรออนุมัติ
                            if(hasRole(['WH_MANAGER'])) {
                                echo renderMenuItem('pr_approval_list.php', $pr_text, $pending_pr_count);
                            } else {
                                // ถ้าเป็น Staff แสดงยอดรอเทียบราคา (Approved)
                                echo renderMenuItem('pr_approval_list.php', $pr_text, $pending_pr_compare_count, 'primary');
                            }
                        ?>

                        <?php 
                            if(hasRole(['WH_MANAGER', 'ADMIN'])) {
                                echo renderMenuItem('po_approval_list.php', 'อนุมัติใบสั่งซื้อ (PO)', $pending_po_count);
                            }
                        ?>

                        <?php echo renderMenuItem('po_list.php', 'รายการสั่งซื้อ (PO)'); ?>
                        <?php echo renderMenuItem('gr_receive.php', 'รับวัสดุ (GR)'); ?>
                        <?php echo renderMenuItem('requisition_list.php', 'จ่ายวัสดุ (GI)', $pending_gi_count); ?>
                    </ul>
                </div>

                <a class="sidebar-link collapsed" data-bs-toggle="collapse" href="#financeMenu" role="button" aria-expanded="false" aria-controls="financeMenu">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>Finance & Reports</span>
                    <i class="bi bi-chevron-right arrow"></i>
                </a>
                <div class="collapse" id="financeMenu">
                    <ul class="sidebar-dropdown">
                        <?php echo renderMenuItem('invoice_entry.php', 'บันทึก Invoice'); ?>
                        <?php echo renderMenuItem('payment_create.php', 'ชำระหนี้ (AP)', $pending_invoice_count); ?>
                        <?php echo renderMenuItem('report_stock.php', 'รายงานคงคลัง'); ?>
                        <?php echo renderMenuItem('report_stock_card.php', 'Stock Card'); ?>
                        <?php echo renderMenuItem('report_gr.php', 'รายงานการรับ (GR)'); ?>
                        <?php echo renderMenuItem('stock_adjustment.php', 'ปรับปรุงสต็อก'); ?>
                    </ul>
                </div>

                <a class="sidebar-link collapsed" data-bs-toggle="collapse" href="#masterMenu" role="button" aria-expanded="false" aria-controls="masterMenu">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>Master Data</span>
                    <i class="bi bi-chevron-right arrow"></i>
                </a>
                <div class="collapse" id="masterMenu">
                    <ul class="sidebar-dropdown">
                        <?php if (hasRole('ADMIN')) echo renderMenuItem('users_list.php', 'จัดการผู้ใช้งาน'); ?>
                        <?php echo renderMenuItem('materials_list.php', 'จัดการวัสดุ'); ?>
                        <?php echo renderMenuItem('material_categories_list.php', 'จัดการหมวดหมู่'); ?>
                        <?php echo renderMenuItem('suppliers_list.php', 'จัดการผู้ขาย'); ?>
                        <?php echo renderMenuItem('locations_list.php', 'จัดการที่จัดเก็บ'); ?>
                    </ul>
                </div>

            <?php endif; ?>

            <?php if (hasRole(['DEPT_MANAGER', 'DEPT_STAFF']) && !hasRole('ADMIN')): ?>
                
                <div class="sidebar-header">Requisition</div>
                
                <a class="sidebar-link" data-bs-toggle="collapse" href="#deptMenu" role="button" aria-expanded="true" aria-controls="deptMenu">
                    <i class="bi bi-basket"></i>
                    <span>เบิกของ (MR)</span>
                    <i class="bi bi-chevron-right arrow"></i>
                </a>
                <div class="collapse show" id="deptMenu">
                    <ul class="sidebar-dropdown">
                        <?php 
                            if (hasRole('DEPT_MANAGER')) {
                                echo renderMenuItem('mr_approval_list.php', 'อนุมัติใบเบิก', $pending_mr_count);
                            }
                        ?>
                        <?php echo renderMenuItem('requisition_create.php', 'สร้างใบเบิก'); ?>
                        <?php echo renderMenuItem('requisition_list.php', 'ประวัติการเบิก'); ?>
                    </ul>
                </div>
            <?php endif; ?>

        </div>
    </nav>

    <div id="page-content-wrapper">
        
        <nav class="navbar navbar-expand-lg navbar-light navbar-top">
            <div class="container-fluid">
                <button class="btn btn-link text-dark" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>

                <div class="ms-auto d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle text-dark fw-bold d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                <?php 
                                    // แสดงตัวอักษรแรกของชื่อ
                                    echo strtoupper(mb_substr($user_name, 0, 1, 'UTF-8')); 
                                ?>
                            </div>
                            <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 animated-dropdown">
                            <li>
                                <div class="px-3 py-2">
                                    <div class="fw-bold"><?php echo htmlspecialchars($user_name); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($user_role); ?></div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid-content">
            