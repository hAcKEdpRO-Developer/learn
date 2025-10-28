<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 22:50 น.
หน้าแก้ไขข้อมูลส่วนตัว (Admin Panel) - แยกจากของผู้ใช้
*/

$page_title = "แก้ไขข้อมูลส่วนตัว (Admin)";
require_once __DIR__ . '/admin_header.php'; // เรียก Header (บังคับ Admin)

$user_id = $_SESSION['user_id'];

// --- ดึงข้อมูลผู้ใช้ปัจจุบัน ---
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6">แก้ไขข้อมูลส่วนตัว</h1>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <div class="md:col-span-1 bg-white p-6 rounded-lg shadow-md text-center">
        <h2 class="text-xl font-semibold mb-4">รูปโปรไฟล์</h2>
        
        <img id="current-profile-pic" src="<?php echo BASE_URL; ?>/uploads/profile/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" class="w-40 h-40 rounded-full mx-auto mb-4 object-cover">
        
        <label for="upload-image" class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded-md">
            เลือกรูปภาพ
        </label>
        <input type="file" id="upload-image" class="hidden" accept="image/png, image/jpeg">
        
        <div id="croppie-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white p-4 rounded-lg shadow-xl">
                <h3 class="text-lg font-medium mb-2">ครอบตัดรูปภาพ</h3>
                <div id="croppie-container" class="w-[300px] h-[300px] md:w-[400px] h-[400px]"></div>
                <div class="my-2">
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="1:1">1:1 (จัตุรัส)</button>
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="4:3">4:3</button>
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="16:9">16:9</button>
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="free">อิสระ</button>
                </div>
                <button id="upload-result-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md">
                    ยืนยันและบันทึกรูปภาพ
                </button>
                <button id="croppie-cancel-btn" class="w-full bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-md mt-2">
                    ยกเลิก
                </button>
            </div>
        </div>
    </div>

    <div class="md:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
             <h2 class="text-xl font-semibold mb-4">ข้อมูลทั่วไป</h2>
            <form id="profile-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">อีเมล</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                    <small> (ไม่สามารถแก้ไขอีเมลได้)</small>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="prefix" class="block text-sm font-medium text-gray-700">คำนำหน้าชื่อ</label>
                        <input type="text" id="prefix" name="prefix" value="<?php echo htmlspecialchars($user['prefix']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="firstname" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
                        <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div>
                        <label for="lastname" class="block text-sm font-medium text-gray-700">นามสกุล</label>
                        <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                </div>
                
                <div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm">
                        บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">เปลี่ยนรหัสผ่าน</h2>
            <form id="password-change-form">
                <div class="mb-4">
                    <label for="old_password" class="block text-sm font-medium text-gray-700">รหัสผ่านเดิม</label>
                    <input type="password" id="old_password" name="old_password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                </div>
                 <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700">รหัสผ่านใหม่</label>
                    <input type="password" id="new_password" name="new_password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required minlength="6">
                </div>
                 <div class="mb-4">
                    <label for="confirm_new_password" class="block text-sm font-medium text-gray-700">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                </div>
                <div>
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm">
                        เปลี่ยนรหัสผ่าน
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// เราจะเรียกใช้ JS สำหรับ Profile (Croppie + Forms) จาก admin.js
// แต่ต้องกำหนด $page_scripts เป็น '' เพื่อไม่ให้ footer.php พยายามเรียกซ้ำ
$page_scripts = '';
?>

<?php require_once __DIR__ . '/admin_footer.php'; ?>