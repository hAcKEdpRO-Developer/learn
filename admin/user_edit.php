<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 22:10 น.
หน้าแก้ไขข้อมูลผู้ใช้ (Admin Panel)
*/

$page_title = "แก้ไขข้อมูลผู้ใช้";
require_once __DIR__ . '/admin_header.php'; // เรียก Header (บังคับ Admin)

$user_id_to_edit = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$user_data = null;
$all_roles = [];
$error_message = null;

if ($user_id_to_edit === 0) {
    $error_message = "ไม่พบ User ID ที่ต้องการแก้ไข";
} else {
    try {
        // 1. ดึงข้อมูลผู้ใช้ที่จะแก้ไข
        $stmt_user = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt_user->execute([$user_id_to_edit]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            throw new Exception("ไม่พบข้อมูลผู้ใช้ ID: $user_id_to_edit");
        }
        
        // 2. ดึง Role ทั้งหมดมาให้เลือก
        $stmt_roles = $db->query("SELECT * FROM roles ORDER BY role_id ASC");
        $all_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6">แก้ไขข้อมูลผู้ใช้</h1>

<?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">เกิดข้อผิดพลาด</p>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        <a href="users.php" class="text-theme-secondary hover:underline">&laquo; กลับไปหน้ารายการผู้ใช้</a>
    </div>
<?php elseif ($user_data): ?>
    <form id="user-edit-form" class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
        <input type="hidden" name="user_id" value="<?php echo $user_data['user_id']; ?>">
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">อีเมล</label>
            <input type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
            <small> (ไม่สามารถแก้ไขอีเมลได้)</small>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label for="prefix" class="block text-sm font-medium text-gray-700">คำนำหน้าชื่อ</label>
                <input type="text" id="prefix" name="prefix" value="<?php echo htmlspecialchars($user_data['prefix']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="firstname" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user_data['firstname']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="lastname" class="block text-sm font-medium text-gray-700">นามสกุล</label>
                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user_data['lastname']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
        </div>
        
        <hr class="my-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
             <div>
                <label for="role_id" class="block text-sm font-medium text-gray-700">สิทธิ์ (Role)</label>
                <select id="role_id" name="role_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <?php foreach ($all_roles as $role): ?>
                        <option value="<?php echo $role['role_id']; ?>" <?php echo ($user_data['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div>
                <label for="is_active" class="block text-sm font-medium text-gray-700">สถานะ</label>
                <select id="is_active" name="is_active" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="1" <?php echo ($user_data['is_active'] == 1) ? 'selected' : ''; ?>>Active (ใช้งาน)</option>
                    <option value="0" <?php echo ($user_data['is_active'] == 0) ? 'selected' : ''; ?>>Inactive (ไม่ใช้งาน/ยังไม่ยืนยัน)</option>
                </select>
            </div>
        </div>
        
        <div class="mt-8 flex justify-between items-center">
            <a href="users.php" class="text-sm text-gray-600 hover:underline">&laquo; กลับ</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-md shadow-sm">
                <i class="fas fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง
            </button>
        </div>
        
    </form>
<?php endif; ?>

<?php
// JS สำหรับ Submit Form นี้
$page_scripts = <<<JS
<script>
$(document).ready(function() {
    $("#user-edit-form").on("submit", function(e) {
        e.preventDefault();
        showLoading("กำลังบันทึกข้อมูล...");

        $.ajax({
            type: "POST",
            url: "api_admin.php?action=update_user",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                hideLoading();
                if (response.status === "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ!',
                        text: response.message,
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        // อาจจะ Reload หรือกลับไปหน้า list
                        window.location.href = "users.php"; 
                    });
                } else {
                    showAlert("error", "ผิดพลาด!", response.message);
                }
            },
            error: function() {
                hideLoading();
                showAlert("error", "เกิดข้อผิดพลาด", "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้");
            }
        });
        return false;
    });
});
</script>
JS;
?>

<?php 
require_once __DIR__ . '/admin_footer.php'; // เรียกใช้ Admin Footer
?>