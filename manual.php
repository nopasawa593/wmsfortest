<?php 
// 1. เรียก header.php (สำคัญมาก)
// เพื่อให้มีเมนู, Bootstrap, และระบบตรวจสอบ Login
require_once 'includes/header.php'; 
?>

<style>
    /* ⭐️ Custom Styles for Manual Page (iPadOS Theme) ⭐️ */
    
    /* Container หลักแบบ Glass Card */
    .manual-container {
        max-width: 900px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.8); /* พื้นหลังขาวโปร่งแสง */
        backdrop-filter: blur(20px);          /* เบลอฉากหลัง */
        -webkit-backdrop-filter: blur(20px);
        border-radius: 24px;                  /* มุมโค้งมาก */
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05); /* เงาลอยตัว */
        padding: 40px;
    }

    /* หัวข้อหลัก */
    .manual-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .manual-icon {
        font-size: 4rem;
        background: linear-gradient(135deg, #007AFF, #5AC8FA); /* ไล่สีฟ้า */
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 10px;
        display: inline-block;
    }
    .manual-title {
        font-weight: 800;
        color: #1D1D1F;
        letter-spacing: -0.5px;
        margin-bottom: 10px;
    }
    .manual-subtitle {
        color: #86868B;
        font-size: 1.1rem;
        font-weight: 500;
    }

    /* Section Title (หัวข้อแต่ละบทบาท) */
    .role-section-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 40px 0 20px;
        padding-left: 15px;
        border-left: 5px solid;
        display: flex;
        align-items: center;
    }
    .role-warehouse { border-color: #34C759; color: #34C759; } /* สีเขียว - พัสดุ */
    .role-dept { border-color: #007AFF; color: #007AFF; }      /* สีฟ้า - แผนกอื่น */

    /* Accordion (เมนูพับ) สไตล์ iOS */
    .accordion-item {
        border: none;
        background: transparent;
        margin-bottom: 15px;
    }
    .accordion-button {
        background-color: #FFFFFF;
        border-radius: 16px !important; /* มุมโค้ง */
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        font-weight: 600;
        color: #1D1D1F;
        padding: 20px 25px;
        transition: all 0.2s ease;
    }
    .accordion-button:not(.collapsed) {
        background-color: #F2F2F7; /* สีเทาอ่อนเมื่อเปิด */
        color: #007AFF;            /* ตัวหนังสือสีฟ้า */
        box-shadow: none;
    }
    .accordion-button:focus {
        box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.2); /* Focus Ring สีฟ้า */
    }
    .accordion-body {
        background-color: rgba(255, 255, 255, 0.5);
        border-radius: 0 0 16px 16px;
        padding: 20px 30px;
        color: #3A3A3C;
        line-height: 1.7;
    }

    /* Step List (รายการขั้นตอน) */
    .step-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .step-list li {
        margin-bottom: 12px;
        position: relative;
        padding-left: 30px;
    }
    .step-list li::before {
        content: "•";
        color: #007AFF;
        font-weight: bold;
        font-size: 1.5em;
        position: absolute;
        left: 0;
        top: -5px;
    }
    
    /* Badges ในคู่มือ */
    .manual-badge {
        font-size: 0.85em;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
        vertical-align: middle;
    }
</style>

<div class="manual-container">
    
    <div class="manual-header">
        <i class="bi bi-book-half manual-icon"></i>
        <h1 class="display-5 manual-title">คู่มือการใช้งานระบบ WMS</h1>
        <p class="manual-subtitle">ระบบจัดการคลังสินค้าอัจฉริยะ (Warehouse Management System)</p>
    </div>

    <?php 
    // ⭐️ Logic การแสดงผลตามสิทธิ์ (เหมือนเดิม แต่ปรับ UI) ⭐️
    
    // --- A. สำหรับ "ทีมแผนกพัสดุ" (WH_STAFF, WH_MANAGER, ADMIN) ---
    if (hasRole(['ADMIN', 'WH_MANAGER', 'WH_STAFF'])): 
    ?>
        
        <div class="alert alert-success border-0 rounded-4 text-center py-3 mb-5" style="background-color: #E8F5E9; color: #1B5E20;">
            <i class="bi bi-person-badge-fill me-2"></i> คุณกำลังดูคู่มือสำหรับ: <strong>ทีมแผนกพัสดุ (Warehouse Team)</strong>
        </div>

        <h2 class="role-section-title role-warehouse">
            <i class="bi bi-boxes me-3"></i> คู่มือการปฏิบัติงาน (Warehouse)
        </h2>

        <div class="accordion" id="warehouseManualAccordion">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWH1">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">1</div>
                            <span>Flow 1: การจ่ายวัสดุ (GI) - (เมื่อ MR ถูกอนุมัติ)</span>
                        </div>
                    </button>
                </h2>
                <div id="collapseWH1" class="accordion-collapse collapse" data-bs-parent="#warehouseManualAccordion">
                    <div class="accordion-body">
                        <p class="mb-3">กระบวนการนี้เริ่มเมื่อแผนกอื่นขอเบิกของ และผู้จัดการอนุมัติแล้ว:</p>
                        <ul class="step-list">
                            <li>ระบบจะแจ้งเตือน <span class="badge bg-danger rounded-pill">1</span> ที่เมนู <strong>"จ่ายวัสดุ (GI/MR)"</strong></li>
                            <li>เข้าไปที่เมนูนั้น จะพบรายการสถานะ <span class="badge bg-primary manual-badge">Pending Issue</span> (รอจ่าย)</li>
                            <li>คลิกปุ่ม <strong>"ดำเนินการจ่าย"</strong> (สีน้ำเงิน)</li>
                            <li>ในหน้าจ่ายของ ระบบจะแสดง Lot ที่มีของ (Available) ให้เลือกหยิบ</li>
                            <li>กรอกจำนวนที่หยิบจริง แล้วกด <strong>"ยืนยันการจ่ายวัสดุ"</strong></li>
                            <li>ระบบจะตัดสต็อกจริง (On-Hand) ทันที และปิดงานใบเบิกเป็น <span class="badge bg-success manual-badge">Issued</span></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWH2">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">2</div>
                            <span>Flow 2: กระบวนการจัดซื้อครบวงจร (PR ➔ PO ➔ GR)</span>
                        </div>
                    </button>
                </h2>
                <div id="collapseWH2" class="accordion-collapse collapse" data-bs-parent="#warehouseManualAccordion">
                    <div class="accordion-body">
                        <strong>Step 1: ขอซื้อ (PR)</strong>
                        <ul class="step-list">
                            <li><strong>Auto PR:</strong> ใช้เมนู <strong>"สร้าง PR (Auto)"</strong> ระบบจะดึงรายการที่ต่ำกว่า Min Stock มาให้เลือกสั่งซื้ออัตโนมัติ</li>
                            <li><strong>Manual PR:</strong> ใช้เมนู <strong>"สร้างใบขอซื้อ (PR)"</strong> เพื่อเลือกของเอง</li>
                        </ul>
                        <hr class="my-3 border-0 border-top">
                        
                        <strong>Step 2: เทียบราคา & สั่งซื้อ (PO)</strong>
                        <ul class="step-list">
                            <li>เมื่อ PR อนุมัติแล้ว (<span class="badge bg-info text-dark manual-badge">Approved</span>) ให้กดปุ่ม <strong>"เทียบราคา"</strong></li>
                            <li>เพิ่ม Supplier, กรอกราคา, และแนบไฟล์ใบเสนอราคา</li>
                            <li>กดปุ่ม <strong>"คำนวณราคาต่ำสุด"</strong> ระบบจะไฮไลท์สีเขียวให้</li>
                            <li>กด <strong>"สร้าง PO (ตามราคาดีที่สุด)"</strong> ระบบจะสร้าง PO แยกตามร้านที่ถูกที่สุดให้ทันที</li>
                            <li>ผู้จัดการอนุมัติ PO ➔ คุณกด <strong>"พิมพ์ PO"</strong> ส่งให้ร้านค้า</li>
                        </ul>
                        <hr class="my-3 border-0 border-top">

                        <strong>Step 3: รับของ (GR)</strong>
                        <ul class="step-list">
                            <li>เมื่อของมาส่ง เข้าเมนู <strong>"รายการสั่งซื้อ (PO)"</strong> กด <strong>"รับของ"</strong></li>
                            <li>ตรวจนับของ, ระบุที่เก็บ (Location), และกดบันทึก สต็อกจะเพิ่มทันที</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWH3">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">3</div>
                            <span>Flow 3: การปรับปรุงสต็อก (Stock Adjustment)</span>
                        </div>
                    </button>
                </h2>
                <div id="collapseWH3" class="accordion-collapse collapse" data-bs-parent="#warehouseManualAccordion">
                    <div class="accordion-body">
                        ใช้เมื่อสต็อกจริงไม่ตรงกับระบบ (ของหาย, แตกหัก, นับสต็อกประจำปี)
                        <ul class="step-list mt-2">
                            <li>ไปที่เมนู <strong>"ปรับปรุงสต็อก"</strong></li>
                            <li>เลือกวัสดุ และ Lot ที่ต้องการปรับ</li>
                            <li>เลือกประเภท: <strong>ADJ-IN</strong> (ปรับเข้า/เจอของเกิน) หรือ <strong>ADJ-OUT</strong> (ปรับออก/ของหาย)</li>
                            <li>ใส่จำนวนและ <strong>เหตุผล</strong> (จำเป็นสำหรับ Audit) แล้วกดยืนยัน</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWH4">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">4</div>
                            <span>Flow 4: การจัดการข้อมูลหลัก (Master Data)</span>
                        </div>
                    </button>
                </h2>
                <div id="collapseWH4" class="accordion-collapse collapse" data-bs-parent="#warehouseManualAccordion">
                    <div class="accordion-body">
                        เมนู <strong>"Master Data & Settings"</strong> ใช้สำหรับตั้งค่าระบบ:
                        <ul class="step-list mt-2">
                            <li><strong>จัดการวัสดุ:</strong> เพิ่มสินค้าใหม่, ตั้งค่า Min/Max Stock, แนบรูปภาพ/Drawing</li>
                            <li><strong>จัดการผู้ขาย:</strong> เพิ่มรายชื่อ Supplier</li>
                            <li><strong>จัดการที่จัดเก็บ:</strong> สร้าง Location ใหม่ในคลัง</li>
                            <li><strong>จัดการผู้ใช้งาน:</strong> (Admin เท่านั้น) เพิ่ม User, กำหนดสิทธิ์, รีเซ็ตรหัสผ่าน</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div> <?php 
    // --- B. สำหรับ "ทีมแผนกอื่น" (DEPT_STAFF, DEPT_MANAGER) ---
    elseif (hasRole(['DEPT_STAFF', 'DEPT_MANAGER'])): 
    ?>
        
        <div class="alert alert-primary border-0 rounded-4 text-center py-3 mb-5" style="background-color: #E3F2FD; color: #0D47A1;">
            <i class="bi bi-person-workspace me-2"></i> คุณกำลังดูคู่มือสำหรับ: <strong>ทีมแผนกอื่น (Department User)</strong>
        </div>

        <h2 class="role-section-title role-dept">
            <i class="bi bi-journal-text me-3"></i> คู่มือการเบิกของ
        </h2>

        <div class="accordion" id="userManualAccordion">
        
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUser1">
                         <i class="bi bi-pencil-square me-2 text-primary"></i> <strong>ขั้นตอนที่ 1: การสร้างใบเบิก (สำหรับพนักงาน)</strong>
                    </button>
                </h2>
                <div id="collapseUser1" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                    <div class="accordion-body">
                        <ul class="step-list">
                            <li>ไปที่เมนู <strong>"สร้างใบเบิก (MR)"</strong></li>
                            <li>เลือก <strong>วัสดุ</strong> ที่ต้องการ (ระบบจะโชว์ยอดคงเหลือให้เห็นทันที)</li>
                            <li>ระบุ <strong>จำนวน</strong> ที่ต้องการเบิก</li>
                            <li>กด <strong>"ส่งใบเบิก"</strong></li>
                            <li>สถานะจะเปลี่ยนเป็น <span class="badge bg-warning text-dark manual-badge">Pending Dept Approval</span> (รอหัวหน้าอนุมัติ)</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUser2">
                         <i class="bi bi-check-circle-fill me-2 text-success"></i> <strong>ขั้นตอนที่ 2: การอนุมัติ (สำหรับผู้จัดการ)</strong>
                    </button>
                </h2>
                <div id="collapseUser2" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                    <div class="accordion-body">
                        <ul class="step-list">
                            <li>เมื่อลูกน้องขอเบิกของ ระบบจะแจ้งเตือน <span class="badge bg-danger rounded-pill">1</span> ที่เมนู <strong>"อนุมัติใบเบิก (MR)"</strong></li>
                            <li>เข้าไปตรวจสอบรายการ แล้วกดปุ่ม <strong>"อนุมัติ" (Approve)</strong> หรือ "ปฏิเสธ"</li>
                            <li>เมื่ออนุมัติแล้ว ระบบจะทำการ <strong>"จองสต็อก"</strong> ไว้ให้ทันที เพื่อป้องกันของหมดก่อนไปรับ</li>
                            <li>สถานะจะเปลี่ยนเป็น <span class="badge bg-primary manual-badge">Pending Issue</span> (ส่งต่อให้คลังเตรียมของ)</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUser3">
                        <i class="bi bi-clipboard-check me-2 text-info"></i> <strong>ขั้นตอนที่ 3: ติดตามสถานะ & รับของ</strong>
                    </button>
                </h2>
                <div id="collapseUser3" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                    <div class="accordion-body">
                        <ul class="step-list">
                            <li>ดูสถานะได้ที่หน้า <strong>"Dashboard"</strong> หรือเมนู <strong>"ประวัติการเบิก"</strong></li>
                            <li>เมื่อสถานะเป็น <span class="badge bg-primary manual-badge">Pending Issue</span> แปลว่าอนุมัติแล้ว รอคลังหยิบของ</li>
                            <li>เมื่อสถานะเป็น <span class="badge bg-success manual-badge">Issued</span> แปลว่า <strong>จ่ายของแล้ว</strong> (คุณได้รับของแล้ว)</li>
                            <li>คุณสามารถกดปุ่ม <strong>"พิมพ์"</strong> <i class="bi bi-printer"></i> เพื่อปริ้นใบเบิกไปรับของที่คลังได้</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

    <?php 
    // --- C. กรณีอื่นๆ ---
    else:
    ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-lock-fill display-4 mb-3"></i><br>
            กรุณาเข้าสู่ระบบเพื่อดูคู่มือการใช้งาน
        </div>
    <?php 
    endif; 
    ?>

</div>

<?php 
// 3. เรียก footer.php
require_once 'includes/footer.php'; 
?>