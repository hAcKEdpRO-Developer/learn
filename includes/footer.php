<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:50 น. จำนวน 40 บรรทัด
ส่วนท้ายของหน้า (HTML Footer) - พร้อม Footer ครูโต้ง
Edit by 27 ตุลาคม 2568 22:30 น. (เพิ่มนาฬิกา Realtime)
*/

// ดึงเวลาปัจจุบัน (Asia/Bangkok) - (เก็บไว้เป็น Fallback)
$current_datetime_thai = date('j F Y \เวลา H:i:s');
?>
    </main>

    <footer class="bg-gray-200 text-gray-700 text-center p-6 mt-8">
        <p class="text-sm">ออกแบบและพัฒนาระบบโดย</p>
        <p class="text-md font-semibold my-1">
            <a href="https://www.facebook.com/suebsing" target="_blank" rel="noopener noreferrer" class="text-theme-primary hover:text-theme-secondary transition-colors">
                ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
            </a>
        </p>
        <p class="text-xs">Version 1.0 © 2025-2026 All Rights Reserved</p>
        <p class="text-xs mt-2" id="realtime-clock-footer">เวลาเซิร์ฟเวอร์: <?php echo $current_datetime_thai; ?></p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

    <script>
        // Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
        // Edit by 24 ตุลาคม 2568 22:51 น. จำนวน 15 บรรทัด
        // Edit by 27 ตุลาคม 2568 22:30 น. (เพิ่มนาฬิกา Realtime)
        // (ไฟล์นี้ควรแยกไป /assets/js/common.js)
        
        $(document).ready(function() {
            // 1. Font Sizer (ปุ่มย่อ/ขยายฟอนต์)
            let currentSize = 16;
            $('#font-increase').on('click', function() {
                currentSize = Math.min(currentSize + 2, 24); // สูงสุด 24px
                $('html').css('font-size', currentSize + 'px');
            });
            $('#font-decrease').on('click', function() {
                currentSize = Math.max(currentSize - 2, 12); // ต่ำสุด 12px
                $('html').css('font-size', currentSize + 'px');
            });

            // 2. SweetAlert Preset (สำหรับใช้ทั่วไป)
            window.showLoading = function(message = 'กำลังประมวลผล...') {
                $.LoadingOverlay("show", { text: message });
            };
            window.hideLoading = function() {
                $.LoadingOverlay("hide");
            };
            window.showAlert = function(icon, title, text = '') {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    timer: icon === 'success' ? 2000 : 3000,
                    timerProgressBar: true
                });
            };

            // --- (เพิ่ม) นาฬิกา Realtime ---
            function updateRealtimeClockFooter() {
                const now = new Date();
                // สร้าง พ.ศ. (ปี ค.ศ. + 543)
                const thaiYear = now.toLocaleString('th-TH', { year: 'numeric', timeZone: 'Asia/Bangkok' });
                // สร้างส่วน วันที่/เดือน
                const datePart = now.toLocaleString('th-TH', { day: 'numeric', month: 'long', timeZone: 'Asia/Bangkok' });
                // สร้างส่วนเวลา
                const timePart = now.toLocaleString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Bangkok' });

                const thaiDateTime = `วันที่ ${datePart} พ.ศ. ${thaiYear} | เวลา ${timePart} น.`;
                
                $('#realtime-clock-footer').text(thaiDateTime);
            }
            
            if ($('#realtime-clock-footer').length) {
                updateRealtimeClockFooter(); // แสดงผลครั้งแรก
                setInterval(updateRealtimeClockFooter, 1000); // อัปเดตทุกวินาที
            }
            // --- จบส่วนนาฬิกา ---

        });
    </script>
    
    <?php echo isset($page_scripts) ? $page_scripts : ''; ?>

</body>
</html>