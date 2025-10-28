<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 14:20 น. จำนวน 130+ บรรทัด
(FIX: JS Error 'atob' & 'USER_INFO undefined' - ฉบับเต็ม)
*/

$page_title = "ทำแบบทดสอบ";
// --- 1. เรียกไฟล์ที่จำเป็น ---
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // ตรวจสอบการล็อกอิน
require_once __DIR__ . '/includes/db.php'; // เชื่อมต่อ DB

// --- 2. รับค่า Attempt ID และตรวจสอบ ---
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$user_id = $_SESSION['user_id'];
$error_message = null;
$quiz_title = 'แบบทดสอบ';
$time_limit_minutes = 30; // ค่าเริ่มต้น
$start_time_unix = time(); // ค่าเริ่มต้น
// กำหนดค่าเริ่มต้นสำหรับข้อมูล User ที่จะใช้ใน Watermark
$user_firstname = $_SESSION['firstname'] ?? 'N/A';
$user_lastname = $_SESSION['lastname'] ?? ''; // ใช้ค่าว่างถ้าไม่มี
$user_email = $_SESSION['email'] ?? 'N/A';
$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';


if ($attempt_id === 0) {
    $error_message = "ไม่พบข้อมูลการสอบ (Invalid Attempt ID)";
} else {
    // --- 3. ดึงข้อมูล Attempt และ Quiz ---
    try {
        $stmt = $db->prepare("
            SELECT a.attempt_id, a.start_time, a.status,
                   q.title as quiz_title, q.time_limit_minutes
            FROM attempts a
            JOIN quizzes q ON a.quiz_id = q.quiz_id
            WHERE a.attempt_id = :attempt_id AND a.user_id = :user_id
        ");
        $stmt->bindParam(':attempt_id', $attempt_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $attempt_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt_data) {
            throw new Exception("ไม่พบข้อมูลการสอบของคุณ หรือไม่มีสิทธิ์เข้าถึง");
        }

        // ตรวจสอบสถานะ Attempt
        if ($attempt_data['status'] !== 'started') {
             $error_message = "การสอบครั้งนี้สิ้นสุดลงแล้ว หรือยังไม่ได้เริ่มอย่างถูกต้อง";
        } else {
            $quiz_title = $attempt_data['quiz_title'];
            $time_limit_minutes = (int)$attempt_data['time_limit_minutes'];
            $start_time_unix = strtotime($attempt_data['start_time']);
        }

    } catch (Throwable $e) {
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// --- 4. เรียก Header (หลังจากมี $quiz_title) ---
$page_title = $error_message ? "เกิดข้อผิดพลาด" : "กำลังทำ: " . htmlspecialchars($quiz_title);
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error_message): ?>
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md text-center">
        <h1 class="text-2xl font-bold text-red-500">เกิดข้อผิดพลาด</h1>
        <p class="text-gray-700 mt-4"><?php echo htmlspecialchars($error_message); ?></p>
        <a href="dashboard.php" class="inline-block mt-6 btn-primary py-2 px-4 rounded-md">กลับไปหน้าแดชบอร์ด</a>
    </div>
<?php else: ?>
    <div id="quiz-container" class="relative min-h-screen">

        <div id="watermark-overlay" class="fixed inset-0 pointer-events-none z-50 opacity-10 text-[10px]" style="color: #999;">
            </div>

        <div class="max-w-3xl mx-auto bg-white p-6 md:p-8 rounded-lg shadow-md relative z-10">

            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h1 class="text-xl md:text-2xl font-bold text-theme-primary mb-2 sm:mb-0 truncate" title="<?php echo htmlspecialchars($quiz_title); ?>"><?php echo htmlspecialchars($quiz_title); ?></h1>
                <div class="text-lg font-semibold bg-red-100 text-red-700 px-3 py-1.5 rounded-md flex items-center">
                    <i class="far fa-clock mr-2"></i> เวลาที่เหลือ: <span id="timer-display" class="ml-1 font-mono">--:--</span>
                </div>
            </div>

            <div id="question-area" class="mb-8 min-h-[250px]">
                <p id="question-number" class="text-sm font-semibold text-gray-500 mb-2">คำถามข้อที่: -/-</p>
                <div id="question-text" class="text-lg md:text-xl font-medium text-gray-800 mb-6 min-h-[50px]">
                    กำลังโหลดคำถาม... <i class="fas fa-spinner fa-spin ml-2"></i>
                </div>
                <div id="choices-area" class="space-y-3">
                    <div class="text-center text-gray-500">กำลังโหลดตัวเลือก...</div>
                </div>
                 <p id="loading-error" class="text-red-500 hidden mt-4">ไม่สามารถโหลดคำถามได้ กรุณาลองอีกครั้ง</p>
            </div>

            <div class="text-center border-t border-gray-200 pt-6">
                <button id="next-button" class="btn-primary font-semibold py-2.5 px-6 sm:px-8 rounded-md shadow-sm text-base sm:text-lg opacity-50 cursor-not-allowed" disabled>
                    ข้อต่อไป <i class="fas fa-arrow-right ml-2"></i>
                </button>
                <button id="finish-button" class="hidden bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-6 sm:px-8 rounded-md shadow-sm text-base sm:text-lg opacity-50 cursor-not-allowed" disabled>
                    ส่งคำตอบ <i class="fas fa-check-circle ml-2"></i>
                </button>
            </div>
        </div>
    </div>

<?php
// --- 5. ส่งข้อมูลไปให้ JavaScript ---
// (FIX) ส่งข้อมูล USER_INFO เป็น JSON ที่เข้ารหัสโดย PHP
$user_info_for_js = [
    'name' => trim("{$user_firstname} {$user_lastname}"),
    'email' => $user_email,
    'ip' => $user_ip
];
// ใช้ json_encode เพื่อแปลง Array เป็น JSON String ที่ถูกต้อง
// ใช้ flags JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT เพื่อความปลอดภัย
$user_info_json = json_encode($user_info_for_js, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

$page_scripts = <<<JS
<script>
    // ข้อมูลจำเป็นสำหรับ quiz.js
    const ATTEMPT_ID = {$attempt_id};
    const QUIZ_START_TIME = {$start_time_unix}; // Unix timestamp
    const QUIZ_TIME_LIMIT_MINUTES = {$time_limit_minutes};
    // (FIX) ข้อมูลสำหรับ Watermark (รับค่า JSON String จาก PHP)
    const USER_INFO = JSON.parse('{$user_info_json}'); // แปลง JSON String กลับเป็น Object
</script>
<script src="assets/js/quiz.js"></script>
JS;
?>

<?php endif; // จบ else ของ if ($error_message) ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>