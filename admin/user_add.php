<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 22:30 น.
หน้าเพิ่มผู้ใช้งานใหม่ (Admin Panel)
*/

$page_title = "เพิ่มผู้ใช้งานใหม่";
require_once __DIR__ . '/admin_header.php'; // เรียก Header (บังคับ Admin)

$all_roles = [];
try {
    // ดึง Role ทั้งหมดมาให้เลือก
    $stmt_roles = $db->query("SELECT * FROM roles ORDER BY role_id ASC");
    $all_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "<p class='text-red-500'>Error: " . $e->getMessage() . "</p>";
}

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6">เพิ่มผู้ใช้งานใหม่</h1>

<form id="user-add-form" class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
    
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700">อีเมล (ใช้เข้าระบบ)</label>
        <input type="email" id="email" name="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
    </div>
    
    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่านเริ่มต้น</label>
        <input type="text" id="password" name="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required minlength="6">
        <small class="text-gray-500">ผู้ใช้ควรเปลี่ยนรหัสผ่านนี้ในภายหลัง</small>
    </div>
    
    <hr class="my-6">
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <label for="prefix" class="block text-sm font-medium text-gray-700">คำนำหน้าชื่อ</label>
            <input type="text" id="prefix" name="prefix" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="firstname" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
            <input type="text" id="firstname" name="firstname" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        </div>
        <div>
            <label for="lastname" class="block text-sm font-medium text-gray-700">นามสกุล</label>
            <input type="text" id="lastname" name="lastname" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
         <div>
            <label for="role_id" class="block text-sm font-medium text-gray-700">สิทธิ์ (Role)</label>
            <select id="role_id" name="role_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <?php foreach ($all_roles as $role): ?>
                    <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_name'] == 'student') ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
         <div>
            <label for="is_active" class="block text-sm font-medium text-gray-700">สถานะ</label>
            <select id="is_active" name="is_active" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="1" selected>Active (ใช้งานได้เลย)</option>
                <option value="0">Inactive (ยังไม่ใช้งาน)</option>
            </select>
        </div>
    </div>
    
    <div class="mt-8 flex justify-between items-center">
        <a href="users.php" class="text-sm text-gray-600 hover:underline">&laquo; กลับ</a>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md shadow-sm">
            <i class="fas fa-plus mr-2"></i> สร้างผู้ใช้งาน
        </button>
    </div>
    
</form>

<?php
// JS สำหรับ Submit Form นี้
$page_scripts = <<<JS
<script>
$(document).ready(function() {
    $("#user-add-form").validate({
        rules: {
            email: { required: true, email: true },
            password: { required: true, minlength: 6 },
            firstname: { required: true },
            lastname: { required: true }
        },
        messages: {
            email: "กรุณากรอกอีเมลให้ถูกต้อง",
            password: "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร"
        },
        submitHandler: function(form) {
            showLoading("กำลังสร้างผู้ใช้งาน...");
            
            $.ajax({
                type: "POST",
                url: "api_admin.php?action=add_user",
                data: $(form).serialize(),
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'สร้างสำเร็จ!',
                            text: response.message,
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = "users.php"; 
                        });
                    } else {
                        showAlert("error", "ผิดพลาด!", response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                    showAlert("error", "เกิดข้อผิดพลาด", errorMsg);
                }
            });
            return false;
        }
    });
});
</script>
JS;
?>

<?php 
require_once __DIR__ . '/admin_footer.php'; // เรียกใช้ Admin Footer
?>