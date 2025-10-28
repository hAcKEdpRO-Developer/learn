<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:54 น. จำนวน 65 บรรทัด
หน้าฟอร์มเข้าสู่ระบบ
Edit by 27 ตุลาคม 2568 21:58 น. (เพิ่ม Logic Redirect Admin)
*/
$page_title = "เข้าสู่ระบบ";
require_once __DIR__ . '/includes/header.php';

// ถ้าล็อกอินแล้ว ให้เด้งไป dashboard
if (isLoggedIn()) {
    // (เพิ่ม) เช็ค Role ที่นี่ด้วยเผื่อเข้ามาตรงๆ
    if (hasRole('admin') || hasRole('staff')) {
        header("Location: " . BASE_URL . "/admin/index.php");
    } else {
        header("Location: " . BASE_URL . "/dashboard.php");
    }
    exit;
}
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-center text-theme-primary mb-6">
        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
    </h1>

    <form id="login-form" method="POST">
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
            <input type="email" id="email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>
        
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
            <input type="password" id="password" name="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>

        <div>
            <button type="submit" class="w-full btn-primary font-semibold py-2 px-4 rounded-md shadow-sm">
                เข้าสู่ระบบ
            </button>
        </div>
    </form>
    <p class="text-center mt-4 text-sm">
        <a href="forgot-password.php" class="text-theme-secondary hover:underline">ลืมรหัสผ่าน?</a>
    </p>
    <p class="text-center mt-2 text-sm">
        ยังไม่มีบัญชี? <a href="register.php" class="text-theme-secondary hover:underline">สมัครสมาชิกที่นี่</a>
    </p>
</div>

<?php
// (แก้ไข) ปรับปรุง JS submitHandler
$page_scripts = '
<script>
    // Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
    // Edit by 24 ตุลาคม 2568 22:55 น. จำนวน 30 บรรทัด
    // Edit by 27 ตุลาคม 2568 21:58 น. (เพิ่ม Logic Redirect Admin)
    // (ไฟล์นี้ควรแยกไป /assets/js/auth.js)
    $(document).ready(function() {
        $("#login-form").validate({
            rules: {
                email: { required: true, email: true },
                password: { required: true }
            },
            submitHandler: function(form) {
                showLoading("กำลังตรวจสอบ...");
                
                $.ajax({
                    type: "POST",
                    url: "api/auth.php?action=login",
                    data: $(form).serialize(),
                    dataType: "json",
                    success: function(response) {
                        hideLoading();
                        if (response.status === "success") {
                            showAlert("success", "เข้าสู่ระบบสำเร็จ!");
                            
                            // *** (แก้ไข) ตรวจสอบ Role เพื่อ Redirect ***
                            if (response.role === "admin" || response.role === "staff") {
                                // ถ้าเป็น Admin หรือ Staff ให้ไปหน้า Admin
                                window.location.href = "admin/index.php";
                            } else {
                                // ถ้าเป็นผู้ใช้ทั่วไป ให้ไปหน้า Dashboard
                                window.location.href = "dashboard.php";
                            }

                        } else {
                            showAlert("error", "ผิดพลาด!", response.message);
                        }
                    },
                    error: function() {
                        hideLoading();
                        showAlert("error", "เกิดข้อผิดพลาด", "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้");
                    }
                });
                return false;
            }
        });
    });
</script>
';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>