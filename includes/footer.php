</div> <footer>
                <div class="container-fluid">
                    <p class="mb-0">
                        &copy; <?php echo date("Y"); ?> <strong>Warehouse Management System (Demo Test)</strong>. All rights reserved.
                    </p>
                </div>
            </footer>

        </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {

        // 1. Logic สำหรับ Toggle Sidebar (ย่อ/ขยาย เมนู)
        var sidebarToggle = document.getElementById('sidebarToggle');
        var wrapper = document.getElementById('wrapper');

        if (sidebarToggle && wrapper) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                wrapper.classList.toggle('toggled');
            });
        }

        // 2. Logic สำหรับ Active Link (ไฮไลท์เมนูที่กำลังใช้งาน)
        // (ช่วยให้ User รู้ว่าตอนนี้อยู่หน้าไหนใน Sidebar)
        const currentPage = "<?php echo basename($_SERVER['PHP_SELF']); ?>";
        const allLinks = document.querySelectorAll('.sidebar-nav-link'); // ต้องตรงกับ class ใน header.php

        allLinks.forEach(link => {
            const linkHref = link.getAttribute('href');
            // เช็คว่า URL ของลิงก์ตรงกับหน้าปัจจุบันหรือไม่
            if (linkHref && (linkHref === currentPage || linkHref.endsWith('/' + currentPage))) {
                link.classList.add('sidebar-link-active');
            } else {
                link.classList.remove('sidebar-link-active');
            }
        });
    });
    </script>
</body>
</html>