<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:52 น. จำนวน 85 บรรทัด
หน้าฟอร์มสมัครสมาชิก
*/
$page_title = "สมัครสมาชิก";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-center text-theme-primary mb-6">สมัครสมาชิก</h1>

    <form id="register-form" method="POST">
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
            <input type="email" id="email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>
        
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
            <input type="password" id="password" name="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required minlength="6">
        </div>
        
        <div class="mb-4">
            <label for="confirm_password" class="block text-sm font-medium text-gray-700">ยืนยันรหัสผ่าน</label>
            <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>
        
        <hr class="my-4">

        <div class="mb-4">
            <label for="prefix" class="block text-sm font-medium text-gray-700">คำนำหน้าชื่อ</label>
            <input type="text" id="prefix" name="prefix" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>

        <div class="mb-4">
            <label for="firstname" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
            <input type="text" id="firstname" name="firstname" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>
        
        <div class="mb-4">
            <label for="lastname" class="block text-sm font-medium text-gray-700">นามสกุล</label>
            <input type="text" id="lastname" name="lastname" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" required>
        </div>

        <div>
            <button type="submit" class="w-full btn-primary font-semibold py-2 px-4 rounded-md shadow-sm">
                สมัครสมาชิก
            </button>
        </div>
    </form>
    <p class="text-center mt-4 text-sm">
        มีบัญชีแล้ว? <a href="login.php" class="text-theme-secondary hover:underline">เข้าสู่ระบบที่นี่</a>
    </p>
</div>

<?php
// กำหนด JS เฉพาะหน้านี้
$page_scripts = '
<script>
    // Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
    // Edit by 24 ตุลาคม 2568 22:53 น. จำนวน 30 บรรทัด
    // (ไฟล์นี้ควรแยกไป /assets/js/auth.js)
    $(document).ready(function() {
        $("#register-form").validate({
            rules: {
                email: { required: true, email: true },
                password: { required: true, minlength: 6 },
                confirm_password: { required: true, minlength: 6, equalTo: "#password" },
                firstname: { required: true },
                lastname: { required: true }
            },
            messages: {
                email: "กรุณากรอกอีเมลให้ถูกต้อง",
                password: "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร",
                confirm_password: "รหัสผ่านไม่ตรงกัน"
            },
            submitHandler: function(form) {
                showLoading("กำลังสมัครสมาชิก...");
                
                $.ajax({
                    type: "POST",
                    url: "api/auth.php?action=register",
                    data: $(form).serialize(),
                    dataType: "json",
                    success: function(response) {
                        hideLoading();
                        if (response.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "สมัครสมาชิกสำเร็จ!",
                                text: response.message,
                                allowOutsideClick: false
                            }).then((result) => {
                                // ส่งไปหน้ากรอก OTP
                                window.location.href = "verify-email.php?email=" + encodeURIComponent($("#email").val());
                            });
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