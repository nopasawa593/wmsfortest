<?php
// 1. เริ่ม Session และเชื่อมต่อ DB
require_once '../config/db_connect.php'; 

// 2. เรียกใช้ auth_check.php
require_once '../includes/auth_check.php';

// 3. กำหนดฟังก์ชัน hasRole
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'ADMIN') return true;
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
}

// 4. ตรวจสอบสิทธิ์ (ทีมพัสดุ)
if (!hasRole(['WH_MANAGER', 'WH_STAFF'])) {
    die("Access Denied: You do not have permission to view POs.");
}

// 5. ดึงข้อมูล PO
if (!isset($_GET['po_id']) || empty($_GET['po_id'])) {
    die("Invalid PO ID.");
}
$po_id = (int)$_GET['po_id'];

// (ดึง PO Header, Supplier, User)
$sql_header = "SELECT 
                    po.*, 
                    s.name AS supplier_name, s.contact_person, s.phone, s.email,
                    u_req.full_name AS requester_name,
                    u_app.full_name AS approver_name
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u_req ON po.created_by_user_id = u_req.id
                LEFT JOIN users u_app ON po.approved_by_user_id = u_app.id
                WHERE po.id = ?";
$stmt_header = $conn->prepare($sql_header);
$stmt_header->bind_param("i", $po_id);
$stmt_header->execute();
$po_header = $stmt_header->get_result()->fetch_assoc();
$stmt_header->close();

if (!$po_header) {
    die("PO Not Found.");
}

// (ดึง PO Items)
$sql_items = "SELECT 
                pi.quantity_ordered, pi.unit_price,
                m.item_code, m.name, m.unit
            FROM po_items pi
            JOIN materials m ON pi.material_id = m.id
            WHERE pi.po_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $po_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$stmt_items->close();
$conn->close();

// (Helper)
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}
$total_amount = 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO Print - <?php echo htmlspecialchars($po_header['po_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* (CSS สำหรับการพิมพ์หน้า A4) */
        @media print {
            body { margin: 0; padding: 0; width: 210mm; }
            .no-print { display: none !important; }
            table.print-table, table.print-table th, table.print-table td {
                border: 1px solid black !important;
            }
            .signature-line {
                border-bottom: 1px dotted #000;
                display: inline-block;
                min-width: 250px;
                text-align: center;
                margin-bottom: 5px;
            }
        }
        body { background-color: #f4f4f4; }
        .a4-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 20mm 15mm;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative; /* สำหรับจัดตำแหน่ง QR Absolute */
        }
        .signature-box { margin-top: 80px; }
        .signature-line {
            border-bottom: 1px dotted #000;
            display: inline-block;
            min-width: 250px;
            padding: 0 10px;
            margin-bottom: 5px;
            height: 1.2em; 
        }
    </style>
</head>
<body>
<div class="a4-container">
    
    <div class="text-center mb-4">
        <h3 class="fw-bold">ใบสั่งซื้อ (Purchase Order)</h3>
        <h5 class="text-muted">เลขที่: <?php echo htmlspecialchars($po_header['po_number']); ?></h5>
        
        <div id="qrcode-container" style="position: absolute; top: 40px; right: 40px;"></div>
    </div>

    <div class="row mb-4 border p-3">
        <div class="col-6">
            <strong>ถึง (Supplier):</strong><br>
            <?php echo htmlspecialchars($po_header['supplier_name']); ?><br>
            ติดต่อ: <?php echo htmlspecialchars($po_header['contact_person'] ?? '-'); ?><br>
            โทร: <?php echo htmlspecialchars($po_header['phone'] ?? '-'); ?><br>
        </div>
        <div class="col-6 text-end">
            <strong>วันที่สั่งซื้อ:</strong> <?php echo formatDate($po_header['order_date']); ?><br>
            <strong>วันที่คาดว่าจะได้รับ:</strong> <?php echo formatDate($po_header['expected_delivery_date']); ?><br>
            <strong>สถานะ:</strong> <?php echo htmlspecialchars($po_header['status']); ?><br>
        </div>
    </div>
    
    <h5 class="mb-3">รายการสั่งซื้อ</h5>
    <div class="table-responsive">
        <table class="table table-sm print-table align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;" class="text-center">Barcode</th> <th style="width: 20%;">รหัสวัสดุ</th>
                    <th style="width: 30%;">ชื่อวัสดุ</th>
                    <th class="text-end">จำนวน</th>
                    <th>หน่วย</th>
                    <th class="text-end">ราคา/หน่วย</th>
                    <th class="text-end">ราคารวม</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while($item = $items_result->fetch_assoc()): 
                    $line_total = $item['quantity_ordered'] * $item['unit_price'];
                    $total_amount += $line_total;
                ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td class="text-center py-2">
                            <svg class="barcode"
                                 data-format="CODE128"
                                 data-value="<?php echo $item['item_code']; ?>"
                                 data-text="false"
                                 data-height="25"
                                 data-width="1"
                                 data-margin="0"></svg>
                        </td>
                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td class="text-end"><?php echo number_format($item['quantity_ordered'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($line_total, 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-end fw-bold border-0">ราคารวมทั้งสิ้น (Total Amount)</td>
                    <td class="text-end fw-bold fs-5 border-0"><?php echo number_format($total_amount, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="signature-box">
        <div class="row">
            <div class="col-6 text-center">
                <div class="signature-line">
                    <?php echo htmlspecialchars($po_header['requester_name']); ?>
                </div>
                <br>
                ( <?php echo htmlspecialchars($po_header['requester_name']); ?> )<br>
                ผู้จัดทำ
            </div>
            
            <div class="col-6 text-center">
                <div class="signature-line">
                    <?php 
                    if ($po_header['status'] != 'Pending PO Approval' && $po_header['status'] != 'PO Rejected' && !empty($po_header['approver_name'])) {
                        echo htmlspecialchars($po_header['approver_name']);
                    } else {
                        echo "&nbsp;"; 
                    }
                    ?>
                </div>
                <br>
                ( 
                <?php 
                if ($po_header['status'] != 'Pending PO Approval' && $po_header['status'] != 'PO Rejected' && !empty($po_header['approver_name'])) {
                    echo htmlspecialchars($po_header['approver_name']);
                } else {
                    echo "......................................";
                }
                ?>
                )<br>
                ผู้อนุมัติ
            </div>
        </div>
    </div>

    <div class="text-center mt-5 no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            <i class="bi bi-printer"></i> พิมพ์ / บันทึกเป็น PDF
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg">
            ปิด
        </button>
    </div>
</div>

<script>
    // Render Barcode ทั้งหมด
    JsBarcode(".barcode").init();

    // Render QR Code (เลขที่ PO)
    new QRCode(document.getElementById("qrcode-container"), {
        text: "<?php echo $po_header['po_number']; ?>",
        width: 90,
        height: 90
    });
</script>

</body>
</html>