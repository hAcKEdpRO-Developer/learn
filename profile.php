<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:03 น. จำนวน 130 บรรทัด
หน้าแก้ไขข้อมูลส่วนตัว (พร้อมระบบ Croppie.js และ Logic การล็อกโปรไฟล์)
*/

$page_title = "แก้ไขข้อมูลส่วนตัว";
require_once __DIR__ . '/includes/functions.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';

$user_id = $_SESSION['user_id'];

// --- Logic การล็อกโปรไฟล์ (ตามข้อกำหนด) ---
$is_profile_locked = false;
try {
    // หาวันที่ "เริ่มเรียน" (learn_start) ที่เร็วที่สุดของผู้เรียน
    $stmt_lock = $db->prepare("
        SELECT MIN(c.learn_start) as earliest_learn_start
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        WHERE e.user_id = ? AND c.learn_start IS NOT NULL
    ");
    $stmt_lock->execute([$user_id]);
    $earliest_start = $stmt_lock->fetchColumn();

    if ($earliest_start) {
        $now = new DateTime("now", new DateTimeZone("Asia/Bangkok"));
        $learn_start_date = new DateTime($earliest_start, new DateTimeZone("Asia/Bangkok"));
        
        // ถ้า "วันนี้" เลย "วันเริ่มเรียน" ไปแล้ว = ล็อก
        if ($now >= $learn_start_date) {
            $is_profile_locked = true;
        }
    }
} catch (PDOException $e) {
    // Error
}

// (Admin/Staff ไม่ควรถูกล็อก - ต้องเพิ่ม Logic เช็ก Role)
if (hasRole('admin') || hasRole('staff')) {
    $is_profile_locked = false; 
}

// --- ดึงข้อมูลผู้ใช้ปัจจุบัน ---
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-theme-primary mb-6">แก้ไขข้อมูลส่วนตัว</h1>

<?php if ($is_profile_locked): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p class="font-bold">ระบบล็อกการแก้ไขข้อมูล</p>
        <p>คุณได้เริ่มการอบรมแล้ว จึงไม่สามารถแก้ไขข้อมูลส่วนตัวได้ (ตามข้อกำหนด) กรุณาติดต่อผู้ดูแลระบบหากต้องการแก้ไข</p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <div class="md:col-span-1 bg-white p-6 rounded-lg shadow-md text-center">
        <h2 class="text-xl font-semibold mb-4">รูปโปรไฟล์</h2>
        
        <img id="current-profile-pic" src="uploads/profile/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" class="w-40 h-40 rounded-full mx-auto mb-4 object-cover">
        
        <label for="upload-image" class="cursor-pointer btn-primary text-sm font-medium py-2 px-4 rounded-md">
            เลือกรูปภาพ
        </label>
        <input type="file" id="upload-image" class="hidden" accept="image/png, image/jpeg">
        
        <div id="croppie-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white p-4 rounded-lg shadow-xl">
                <h3 class="text-lg font-medium mb-2">ครอบตัดรูปภาพ</h3>
                <div id="croppie-container" class="w-[300px] h-[300px] md:w-[400px] md:h-[400px]"></div>
                <div class="my-2">
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="1:1">1:1 (จัตุรัส)</button>
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="4:3">4:3</button>
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="16:9">16:9</button>
                    <button class="croppie-aspect-btn px-2 py-1 text-xs bg-gray-200 rounded" data-ratio="free">อิสระ</button>
                </div>
                <button id="upload-result-btn" class="w-full btn-primary font-semibold py-2 px-4 rounded-md">
                    ยืนยันและบันทึกรูปภาพ
                </button>
                <button id="croppie-cancel-btn" class="w-full bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-md mt-2">
                    ยกเลิก
                </button>
            </div>
        </div>
    </div>

    <div class="md:col-span-2 bg-white p-6 rounded-lg shadow-md">
        <form id="profile-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">อีเมล</label>
                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                <small> (ไม่สามารถแก้ไขอีเมลได้)</small>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="prefix" class="block text-sm font-medium text-gray-700">คำนำหน้าชื่อ</label>
                    <input type="text" id="prefix" name="prefix" value="<?php echo htmlspecialchars($user['prefix']); ?>" class="mt-1 block w-full border-gray-300 rounded-md" <?php echo $is_profile_locked ? 'disabled' : ''; ?>>
                </div>
                <div>
                    <label for="firstname" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
                    <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" class="mt-1 block w-full border-gray-300 rounded-md" <?php echo $is_profile_locked ? 'disabled' : ''; ?>>
                </div>
                <div>
                    <label for="lastname" class="block text-sm font-medium text-gray-700">นามสกุล</label>
                    <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" class="mt-1 block w-full border-gray-300 rounded-md" <?php echo $is_profile_locked ? 'disabled' : ''; ?>>
                </div>
            </div>
            
            <?php if (!$is_profile_locked): ?>
            <div>
                <button type="submit" class="w-full btn-primary font-semibold py-2 px-4 rounded-md shadow-sm">
                    บันทึกข้อมูล
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php
// เราจะเรียกใช้ JS เฉพาะ (upload.js)
$page_scripts = '<script src="assets/js/upload.js"></script>';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>