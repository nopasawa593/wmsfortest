<?php 
// 1. เริ่ม Session และเชื่อมต่อ DB
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_connect.php'; 

// --- 2. ตรวจสอบสิทธิ์เบื้องต้น (ต้อง Login) ---
if (!isset($_SESSION['user_id'])) {
    die("Permission Denied: Please Login.");
}

// --- 3. รับค่า ID ใบเบิก ---
$req_id = isset($_GET['req_id']) ? (int)$_GET['req_id'] : 0;
if ($req_id == 0) {
    die("Invalid Requisition ID.");
}

// --- 4. ดึงข้อมูล Header ใบเบิก ---
// (ดึง Department ID ของผู้ขอด้วย เพื่อใช้เช็คสิทธิ์)
$sql = "SELECT 
            r.mr_number, r.request_date, r.department, r.status, r.approval_date,
            u_req.full_name AS requester_name,
            u_req.department_id AS req_dept_id,
            u_app.full_name AS approver_name
        FROM requisitions r
        JOIN users u_req ON r.requested_by_user_id = u_req.id
        LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
        WHERE r.id = ?"; 
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $req_id);
$stmt->execute();
$mr_header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mr_header) {
    die("Requisition not found.");
}

// --- 5. Security Check (ตรวจสอบสิทธิ์การเข้าถึงข้อมูล) ---
// อนุญาตเฉพาะ: Admin, ทีมพัสดุ (WH), หรือ คนในแผนกเดียวกับใบเบิก
$user_role = $_SESSION['role'] ?? '';
$my_dept_id = $_SESSION['department_id'] ?? 0;
$req_dept_id = $mr_header['req_dept_id'];
$allowed_roles = ['ADMIN', 'WH_MANAGER', 'WH_STAFF'];

if (!in_array($user_role, $allowed_roles)) {
    // ถ้าไม่ใช่ Admin/WH ต้องเช็คว่าอยู่แผนกเดียวกันไหม
    if ($req_dept_id != $my_dept_id) {
        die("<div style='text-align:center; margin-top:50px; color:red; font-family:sans-serif;'>
                <h3>Access Denied</h3>
                คุณไม่มีสิทธิ์พิมพ์ใบเบิกของต่างแผนก
             </div>");
    }
}

// --- 6. ดึงรายการวัสดุ (Items) + Location ---
// ⭐️ เพิ่มการ JOIN ตาราง locations เพื่อเอา location_code
$sql_items = "SELECT 
                ri.quantity_requested,
                ri.quantity_issued,
                m.item_code,
                m.name,
                m.unit,
                l.location_code  -- ⭐️ ดึงรหัสที่เก็บ
            FROM requisition_items ri
            JOIN materials m ON ri.material_id = m.id
            LEFT JOIN locations l ON m.default_location_id = l.id
            WHERE ri.requisition_id = ?";
            
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $req_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$stmt_items->close();
$conn->close();

// Helper Function: จัดรูปแบบวันที่
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเบิกวัสดุ - <?php echo htmlspecialchars($mr_header['mr_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* CSS สำหรับหน้าจอปกติ */
        body { 
            background-color: #525659; /* สีพื้นหลังเทาเข้ม เหมือนโปรแกรมดู PDF */
            font-family: 'Sarabun', 'Tahoma', sans-serif;
        }
        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 30px auto;
            background: white;
            padding: 20mm;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            position: relative;
        }

        /* CSS สำหรับการสั่งพิมพ์ (Printer) */
        @media print {
            body { 
                background: none;
                margin: 0; 
                padding: 0;
            }
            .page-container {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none !important;
            }
            /* บังคับให้พิมพ์เส้นขอบตารางและสีพื้นหลัง */
            table.table-bordered {
                border: 1px solid #000 !important;
            }
            table.table-bordered th, table.table-bordered td {
                border: 1px solid #000 !important;
            }
            .table-light th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
        }

        /* Utility Classes */
        .header-title { font-size: 24px; font-weight: bold; text-align: center; margin-bottom: 5px; }
        .header-subtitle { font-size: 16px; text-align: center; color: #555; margin-bottom: 30px; }
        .info-box { margin-bottom: 20px; }
        .signature-section { margin-top: 60px; }
        .sig-line { border-bottom: 1px dotted #000; width: 80%; margin: 40px auto 10px auto; }
    </style>
</head>
<body>

    <div class="container mt-3 mb-3 no-print text-center">
        <button onclick="window.print()" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
            <i class="bi bi-printer-fill me-2"></i> พิมพ์เอกสาร
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg rounded-pill px-4 shadow-sm ms-2">
            ปิดหน้าต่าง
        </button>
    </div>

    <div class="page-container">
        
        <div class="header-title">ใบเบิกวัสดุ (Material Requisition)</div>
        <div class="header-subtitle">เลขที่เอกสาร: <strong><?php echo htmlspecialchars($mr_header['mr_number']); ?></strong></div>

        <div class="info-box">
            <div class="row">
                <div class="col-6">
                    <p class="mb-1"><strong>ผู้ขอเบิก:</strong> <?php echo htmlspecialchars($mr_header['requester_name']); ?></p>
                    <p class="mb-1"><strong>แผนก:</strong> <?php echo htmlspecialchars($mr_header['department']); ?></p>
                </div>
                <div class="col-6 text-end">
                    <p class="mb-1"><strong>วันที่ขอเบิก:</strong> <?php echo formatDate($mr_header['request_date']); ?></p>
                    <p class="mb-1"><strong>สถานะ:</strong> <?php echo htmlspecialchars($mr_header['status']); ?></p>
                </div>
            </div>
        </div>

        <table class="table table-bordered table-sm align-middle" style="font-size: 14px;">
            <thead class="table-light text-center">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;">ที่เก็บ (Loc)</th> <th style="width: 20%;">รหัสวัสดุ</th>
                    <th style="width: 30%;">รายการวัสดุ</th>
                    <th style="width: 10%;">ขอเบิก</th>
                    <th style="width: 10%;">จ่ายจริง</th>
                    <th style="width: 10%;">หน่วย</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1; 
                if ($items_result->num_rows > 0):
                    while($item = $items_result->fetch_assoc()): 
                ?>
                    <tr>
                        <td class="text-center"><?php echo $i++; ?></td>
                        
                        <td class="text-center fw-bold">
                            <?php echo htmlspecialchars($item['location_code'] ?? '-'); ?>
                        </td>
                        
                        <td class="text-center"><?php echo htmlspecialchars($item['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td class="text-end"><?php echo number_format($item['quantity_requested'], 2); ?></td>
                        
                        <td class="text-end fw-bold">
                            <?php echo ($item['quantity_issued'] > 0) ? number_format($item['quantity_issued'], 2) : '-'; ?>
                        </td>
                        
                        <td class="text-center"><?php echo htmlspecialchars($item['unit']); ?></td>
                    </tr>
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">ไม่พบรายการวัสดุ</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="signature-section">
            <div class="row">
                <div class="col-4 text-center">
                    <div class="sig-line"></div>
                    <p class="mb-0">( <?php echo htmlspecialchars($mr_header['requester_name']); ?> )</p>
                    <p class="small text-muted">ผู้ขอเบิก</p>
                    <p class="small text-muted">วันที่: <?php echo formatDate($mr_header['request_date']); ?></p>
                </div>

                <div class="col-4 text-center">
                    <div class="sig-line"></div>
                    <p class="mb-0">
                        ( 
                        <?php echo !empty($mr_header['approver_name']) ? htmlspecialchars($mr_header['approver_name']) : "................................................"; ?> 
                        )
                    </p>
                    <p class="small text-muted">ผู้อนุมัติ / หัวหน้าแผนก</p>
                    <p class="small text-muted">วันที่: <?php echo formatDate($mr_header['approval_date']); ?></p>
                </div>

                <div class="col-4 text-center">
                    <div class="sig-line"></div>
                    <p class="mb-0">( ................................................ )</p>
                    <p class="small text-muted">เจ้าหน้าที่คลังสินค้า (ผู้จ่าย)</p>
                    <p class="small text-muted">วันที่: ....... / ....... / ...........</p>
                </div>
            </div>
        </div>

    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>
</html>