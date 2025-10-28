<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 01:30 น. จำนวน 110+ บรรทัด
(FIX: ส่ง is_completed ไปให้ JS + เพิ่มแถบแจ้งเตือน Review Mode)
*/

// --- 1. เรียกไฟล์ที่จำเป็นทั้งหมดก่อน ---
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

// --- 2. ตรวจสอบการล็อกอิน ---
requireLogin();

// --- 3. ดำเนินการ Logic ของหน้า ---
$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$user_id = $_SESSION['user_id'];
$error_message = null; // ตัวแปรดัก Error
$lesson = null; // กำหนดค่าเริ่มต้น
$course = null; // กำหนดค่าเริ่มต้น
$is_completed = false; // กำหนดค่าเริ่มต้น
$last_watched_time = 0; // กำหนดค่าเริ่มต้น
$course_id = 0; // กำหนดค่าเริ่มต้น

if ($lesson_id === 0) {
    header("Location: dashboard.php");
    exit;
}

// (FIX) เราจะใช้ try...catch (Throwable) เพื่อดักทุก Error
try {
    // 1. ดึงข้อมูลบทเรียน
    $stmt_lesson = $db->prepare("SELECT * FROM course_lessons WHERE lesson_id = ?");
    $stmt_lesson->execute([$lesson_id]);
    $lesson = $stmt_lesson->fetch();

    if (!$lesson || $lesson['video_type'] !== 'youtube') {
        throw new Exception("ไม่พบบทเรียน (ID: $lesson_id) หรือ บทเรียนนี้ไม่ใช่ YouTube");
    }

    // 2. ดึงข้อมูลความก้าวหน้า (สำหรับ Resume และ Review Mode)
    $stmt_progress = $db->prepare("SELECT * FROM lesson_progress WHERE lesson_id = ? AND user_id = ?");
    $stmt_progress->execute([$lesson_id, $user_id]);
    $progress = $stmt_progress->fetch();

    $last_watched_time = $progress ? (float)$progress['last_watched_time'] : 0;
    // (FIX) ดึง is_completed มาใช้ตัดสิน Review Mode
    $is_completed = $progress ? (bool)$progress['is_completed'] : false;

    // 3. ดึงชื่อคอร์ส (สำหรับ Breadcrumb และ JS)
    $stmt_course = $db->prepare("SELECT c.course_id, c.title FROM courses c JOIN course_lessons l ON c.course_id = l.course_id WHERE l.lesson_id = ?");
    $stmt_course->execute([$lesson_id]);
    $course = $stmt_course->fetch();

    if (!$course) {
        throw new Exception("ข้อมูลบทเรียนไม่สมบูรณ์: ไม่พบคอร์สที่เชื่อมโยงกับบทเรียนนี้");
    }
    // (FIX) เก็บ Course ID ไว้ใช้
    $course_id = $course['course_id'];

} catch (Throwable $e) {
    $error_message = $e->getMessage();
    // กำหนด page_title ที่นี่ เผื่อ header ต้องการใช้
    $page_title = "เกิดข้อผิดพลาด";
}

// --- 4. เรียก Header เพียงครั้งเดียว ---
// ถ้าไม่มี Error $page_title จะเป็นชื่อบทเรียน
// ถ้ามี Error $page_title จะเป็น "เกิดข้อผิดพลาด"
// ตรวจสอบว่า $lesson มีค่าก่อนเรียกใช้
$page_title = $error_message ? "เกิดข้อผิดพลาด" : ($lesson ? $lesson['title'] : "กำลังโหลด...");
require_once __DIR__ . '/includes/header.php';

// --- 5. แสดงผลตาม Logic ---
if ($error_message):
    // (แสดงหน้า Error ที่ปลอดภัย)
    echo "<div class='max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md text-center'>";
    echo "<h1 class='text-2xl font-bold text-red-500'>เกิดข้อผิดพลาด</h1>";
    echo "<p class='text-gray-700 mt-4'>" . htmlspecialchars($error_message) . "</p>";
    echo "<a href='dashboard.php' class='inline-block mt-6 btn-primary py-2 px-4 rounded-md'>กลับไปหน้าแดชบอร์ด</a>";
    echo "</div>";

else:
    // (แสดงหน้า Watch Video ปกติ - เพราะ $lesson และ $course ถูกต้อง)
?>
    <nav class="text-sm mb-4">
        <a href="dashboard.php" class="text-theme-secondary hover:underline">แดชบอร์ด</a> &gt;
        <a href="course.php?id=<?php echo htmlspecialchars($course['course_id']); ?>" class="text-theme-secondary hover:underline"><?php echo htmlspecialchars($course['title']); ?></a> &gt;
        <span class="text-gray-600"><?php echo htmlspecialchars($lesson['title']); ?></span>
    </nav>

    <div class="bg-white p-6 rounded-lg shadow-md">

        <?php if ($is_completed): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded-md" role="alert">
                <p class="font-bold"><i class="fas fa-info-circle mr-2"></i>โหมดทบทวน</p>
                <p class="text-sm">คุณเรียนจบบทเรียนนี้แล้ว สามารถเลื่อนวิดีโอได้อย่างอิสระ</p>
            </div>
        <?php endif; ?>

        <div class="w-full aspect-video bg-black">
            <div id="youtube-player"></div>
        </div>

        <div class="mt-4">
            <h1 class="text-3xl font-bold text-theme-primary"><?php echo htmlspecialchars($lesson['title']); ?></h1>
            <p class="text-gray-500">เกณฑ์การผ่าน: ดูอย่างน้อย <?php echo htmlspecialchars($lesson['min_watch_percent']); ?>%</p>

            <div class="text-lg mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-blue-800">ความก้าวหน้า (ดูไปแล้ว):</span>
                    <span id="progress-percent" class="text-2xl font-bold text-blue-800">0.0%</span>
                </div>
                <div class="flex justify-between items-center mt-1">
                    <span class="text-sm text-gray-600">ที่เหลืออีก:</span>
                    <span id="progress-remaining" class="text-sm font-bold text-gray-600">100.0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-3">
                    <div id="progress-bar-inner" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                </div>
            </div>

            <div class="text-sm mt-4 p-4 bg-gray-50 rounded-md">
                <h3 class="font-semibold">สถานะการเรียน (Debug)</h3>
                <p>สถานะ: <span id="player-status" class="font-bold">Loading...</span></p>
                <p>เวลาปัจจุบัน: <span id="current-time-display">0</span> วินาที</p>
                <p>จุดที่เล่นได้สูงสุด (Lock Seek): <span id="allow-until-display">0</span> วินาที</p>
                <p>ผ่านบทเรียนนี้: <span id="completed-display"><?php echo $is_completed ? 'ใช่' : 'ยัง'; ?></span></p>
                <p>Violation Count: <span id="violation-count">0</span></p>
            </div>
        </div>
    </div>

<?php
    // --- เริ่มต้น PHP Block ที่นี่ ---

    // 4. ใช้ Heredoc
    // เตรียมตัวแปร PHP ให้ปลอดภัยก่อน
    $video_id_js = htmlspecialchars($lesson['video_path']);
    $start_time_js = (float)$last_watched_time;
    $min_percent_js = (int)$lesson['min_watch_percent'];
    $lesson_id_js = (int)$lesson_id;
    $course_id_js = (int)$course_id;
    // (FIX) ส่ง is_completed เป็น boolean (true/false)
    $is_completed_js = $is_completed ? 'true' : 'false';

    $page_scripts = <<<JS
    <script>
        const PHP_DATA = {
            lesson_id: $lesson_id_js,
            video_id: "$video_id_js",
            start_time: $start_time_js,
            min_watch_percent: $min_percent_js,
            course_id: $course_id_js,
            is_completed: $is_completed_js // (FIX) เพิ่ม is_completed
        };

        const PLAYER_VARS = {
            'playsinline': 1,
            'modestbranding': 1,
            'controls': 1,
            'disablekb': 1, // ปิดคีย์บอร์ดเสมอ (กัน Spacebar Pause)
            'start': $start_time_js
        };
    </script>
    <script src="https://www.youtube.com/iframe_api"></script>
    <script src="assets/js/watch.js"></script>
JS; // (Heredoc End) - บรรทัดนี้ต้องไม่มีการย่อหน้า

    endif; // (จบ if ($error_message))

    // --- 6. เรียก Footer เพียงครั้งเดียว ---
    require_once __DIR__ . '/includes/footer.php';

// --- ปิด PHP Block ที่นี่ ---
?>