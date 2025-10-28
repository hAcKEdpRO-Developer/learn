<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 21:00 น.
Footer สำหรับระบบหลังบ้าน (Admin Panel)
Edit by 27 ตุลาคม 2568 22:30 น. (เพิ่มนาฬิกา Realtime)
*/
?>
    </main>

    <footer class="bg-gray-800 text-gray-300 text-center p-4 mt-8">
        <p class="text-sm">Admin Panel - <?php echo htmlspecialchars(SITE_NAME); ?></p>
        <p class="text-xs">&copy; <?php echo date('Y'); ?> All Rights Reserved</p>
        <p class="text-xs mt-2" id="realtime-clock-admin">กำลังโหลดเวลา...</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>


    <script>
        // Preset สำหรับ Admin JS
        function showLoading(message = 'กำลังประมวลผล...') {
            $.LoadingOverlay("show", { text: message });
        }
        function hideLoading() {
            $.LoadingOverlay("hide");
        }
        function showAlert(icon, title, text = '') {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                timer: icon === 'success' ? 2000 : 3000,
                timerProgressBar: true
            });
        }
        
        // --- (เพิ่ม) นาฬิกา Realtime ---
        function updateRealtimeClockAdmin() {
            try {
                const now = new Date();
                // สร้าง พ.ศ. (ปี ค.ศ. + 543)
                const thaiYear = now.toLocaleString('th-TH', { year: 'numeric', timeZone: 'Asia/Bangkok' });
                // สร้างส่วน วันที่/เดือน
                const datePart = now.toLocaleString('th-TH', { day: 'numeric', month: 'long', timeZone: 'Asia/Bangkok' });
                // สร้างส่วนเวลา
                const timePart = now.toLocaleString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Bangkok' });

                // (แก้ไข) ตรวจสอบว่าได้ค่าถูกต้องก่อนแสดงผล
                if (datePart && thaiYear && timePart) {
                    const thaiDateTime = `วันที่ ${datePart} พ.ศ. ${thaiYear} | เวลา ${timePart} น.`;
                    $('#realtime-clock-admin').text(thaiDateTime);
                } else {
                     $('#realtime-clock-admin').text('กำลังโหลดเวลา...');
                }
            } catch (e) {
                 $('#realtime-clock-admin').text('ไม่สามารถโหลดเวลาได้');
            }
        }
        
        $(document).ready(function() {
            if ($('#realtime-clock-admin').length) {
                updateRealtimeClockAdmin(); // แสดงผลครั้งแรก
                setInterval(updateRealtimeClockAdmin, 1000); // อัปเดตทุกวินาที
            }
        });
        // --- จบส่วนนาฬิกา ---
        
    </script>
    
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js"></script> 

    <?php echo isset($page_scripts) ? $page_scripts : ''; ?>

</body>
</html>