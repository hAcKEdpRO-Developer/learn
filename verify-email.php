<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:56 น. จำนวน 55 บรรทัด
หน้ายืนยัน OTP 6 หลัก
*/
$page_title = "ยืนยันอีเมล";
require_once __DIR__ . '/includes/header.php';

$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
if (empty($email)) {
    echo "<p class='text-center text-red-500'>ไม่พบอีเมล</p>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-center text-theme-primary mb-6">ยืนยันอีเมลของคุณ</h1>
    <p class="text-center text-gray-600 mb-4">
        เราได้ส่งรหัส OTP 6 หลักไปยัง <strong><?php echo $email; ?></strong><br>
        (กรุณาตรวจสอบ Junk Mail หากไม่พบ)
    </p>

    <form id="verify-form" method="POST">
        <input type="hidden" name="email" value="<?php echo $email; ?>">
        <div class="mb-4">
            <label for="otp" class="block text-sm font-medium text-gray-700">รหัส OTP 6 หลัก</label>
            <input type="text" id="otp" name="otp" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-center text-2xl tracking-[1em]" required maxlength="6">
        </div>

        <div>
            <button type="submit" class="w-full btn-primary font-semibold py-2 px-4 rounded-md shadow-sm">
                ยืนยัน
            </button>
        </div>
    </form>
    <p class="text-center mt-4 text-sm">
        <a href="#" id="resend-otp" data-email="<?php echo $email; ?>" class="text-theme-secondary hover:underline">ส่งรหัสใหม่อีกครั้ง</a>
    </p>
</div>

<?php
$page_scripts = '
<script>
    // Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
    // Edit by 24 ตุลาคม 2568 22:57 น. จำนวน 55 บรรทัด
    // (ไฟล์นี้ควรแยกไป /assets/js/auth.js)
    $(document).ready(function() {
        $("#verify-form").validate({
            rules: {
                otp: { required: true, minlength: 6, maxlength: 6, digits: true }
            },
            messages: { otp: "กรุณากรอก OTP 6 หลัก" },
            submitHandler: function(form) {
                showLoading("กำลังตรวจสอบ OTP...");
                $.ajax({
                    type: "POST",
                    url: "api/auth.php?action=verify_email",
                    data: $(form).serialize(),
                    dataType: "json",
                    success: function(response) {
                        hideLoading();
                        if (response.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "ยืนยันสำเร็จ!",
                                text: response.message,
                                allowOutsideClick: false
                            }).then((result) => {
                                window.location.href = "login.php";
                            });
                        } else {
                            showAlert("error", "ผิดพลาด!", response.message);
                        }
                    },
                    error: function() { hideLoading(); showAlert("error", "เกิดข้อผิดพลาด"); }
                });
                return false;
            }
        });

        // ส่ง OTP ใหม่
        $("#resend-otp").on("click", function(e) {
            e.preventDefault();
            $(this).addClass("text-gray-400").text("กำลังส่ง...");
            showLoading("กำลังส่ง OTP ใหม่...");
            
            $.ajax({
                type: "POST",
                url: "api/auth.php?action=resend_otp",
                data: { email: $(this).data("email") },
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        showAlert("success", "ส่ง OTP ใหม่สำเร็จ", "กรุณาตรวจสอบอีเมลของคุณ");
                    } else {
                        showAlert("error", "ผิดพลาด!", response.message);
                    }
                    $("#resend-otp").removeClass("text-gray-400").text("ส่งรหัสใหม่อีกครั้ง");
                },
                error: function() {
                    hideLoading();
                    showAlert("error", "เกิดข้อผิดพลาด");
                    $("#resend-otp").removeClass("text-gray-400").text("ส่งรหัสใหม่อีกครั้ง");
                }
            });
        });
    });
</script>
';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>