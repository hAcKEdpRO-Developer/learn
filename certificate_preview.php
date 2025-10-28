<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 15:35 น. จำนวน 80+ บรรทัด
หน้าแสดงข้อมูลก่อนพิมพ์เกียรติบัตร
*/

$page_title = "เตรียมพิมพ์เกียรติบัตร";
// --- 1. เรียกไฟล์ที่จำเป็น ---
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // ตรวจสอบการล็อกอิน
require_once __DIR__ . '/includes/db.php'; // เชื่อมต่อ DB

// --- 2. รับค่าและตรวจสอบ ---
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$user_id = $_SESSION['user_id'];
$error_message = null;
$course = null;
$user_data = null;
$can_print = false; // สถานะว่าพิมพ์ได้จริงหรือไม่

if ($course_id === 0) {
    header("Location: dashboard.php");
    exit;
}

// --- 3. ดึงข้อมูลและตรวจสอบสิทธิ์ ---
try {
    // 1. ดึงข้อมูลคอร์ส และตรวจสอบว่าเปิดให้พิมพ์หรือยัง
    $stmt_course = $db->prepare("SELECT title, cert_enabled FROM courses WHERE course_id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

    if (!$course) { throw new Exception("ไม่พบข้อมูลหลักสูตร"); }
    if (!$course['cert_enabled']) { throw new Exception("ระบบยังไม่เปิดให้พิมพ์เกียรติบัตรสำหรับหลักสูตรนี้"); }

    // 2. ดึงข้อมูลผู้ใช้
    $stmt_user = $db->prepare("SELECT prefix, firstname, lastname, email FROM users WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) { throw new Exception("ไม่พบข้อมูลผู้ใช้"); }

    // 3. ตรวจสอบสถานะการสอบผ่าน (สำคัญ)
    $stmt_check_pass = $db->prepare("
        SELECT a.score
        FROM attempts a
        JOIN quizzes q ON a.quiz_id = q.quiz_id
        WHERE a.user_id = :user_id AND q.course_id = :course_id AND a.status IN ('completed', 'time_expired')
        ORDER BY a.start_time DESC
        LIMIT 1
    ");
     $stmt_check_pass->bindParam(':user_id', $user_id, PDO::PARAM_INT);
     $stmt_check_pass->bindParam(':course_id', $course_id, PDO::PARAM_INT);
     $stmt_check_pass->execute();
     $latest_score_data = $stmt_check_pass->fetch(PDO::FETCH_ASSOC);

    $stmt_pass_score = $db->prepare("SELECT pass_score FROM quizzes WHERE course_id = ?");
    $stmt_pass_score->execute([$course_id]);
    $pass_score_threshold = $stmt_pass_score->fetchColumn() ?? 80;

    if (!$latest_score_data || ($latest_score_data['score'] < $pass_score_threshold)) {
        throw new Exception("คุณยังไม่ผ่านเกณฑ์การสอบสำหรับหลักสูตรนี้");
    }

    // ถ้าผ่านทุกอย่าง
    $can_print = true;
    $page_title = "พิมพ์เกียรติบัตร - " . htmlspecialchars($course['title']);


} catch (Throwable $e) {
    $error_message = $e->getMessage();
    $page_title = "เกิดข้อผิดพลาด";
}

// --- 4. เรียก Header ---
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white p-6 md:p-8 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-center text-theme-primary mb-6"><?php echo htmlspecialchars($page_title); ?></h1>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">เกิดข้อผิดพลาด</p>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
        <div class="text-center">
             <a href="course.php?id=<?php echo $course_id; ?>" class="text-theme-secondary hover:underline">&laquo; กลับไปหน้าหลักสูตร</a>
        </div>
    <?php elseif ($can_print && $user_data && $course): ?>
        <div class="space-y-4 mb-8">
            <div>
                <h2 class="text-lg font-semibold text-gray-700">ข้อมูลผู้รับเกียรติบัตร</h2>
                <p class="text-gray-900 text-xl">
                    <?php echo htmlspecialchars($user_data['prefix'] . $user_data['firstname'] . ' ' . $user_data['lastname']); ?>
                </p>
                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($user_data['email']); ?></p>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-700">หลักสูตรที่ผ่านการอบรม</h2>
                <p class="text-gray-900 text-xl"><?php echo htmlspecialchars($course['title']); ?></p>
                <?php // อาจจะเพิ่มข้อมูลอื่นๆ ของคอร์สที่นี่ ?>
            </div>
             <div>
                <h2 class="text-lg font-semibold text-gray-700">คะแนนที่ได้</h2>
                <p class="text-gray-900 text-xl font-bold"><?php echo number_format((float)($latest_score_data['score'] ?? 0), 2); ?>% (ผ่านเกณฑ์)</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="generate_certificate.php?course_id=<?php echo $course_id; ?>&format=pdf" target="_blank"
               class="w-full text-center bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-md shadow-sm text-lg transition duration-150">
               <i class="fas fa-file-pdf mr-2"></i> ดาวน์โหลด PDF
            </a>
             <a href="generate_certificate.php?course_id=<?php echo $course_id; ?>&format=image" target="_blank"
               class="w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-md shadow-sm text-lg transition duration-150">
               <i class="fas fa-file-image mr-2"></i> ดาวน์โหลด รูปภาพ
            </a>
        </div>
         <div class="text-center mt-6">
             <a href="course.php?id=<?php echo $course_id; ?>" class="text-sm text-gray-500 hover:underline">กลับไปหน้าหลักสูตร</a>
         </div>

    <?php else: // กรณีไม่ควรเกิดขึ้น แต่ใส่เผื่อไว้ ?>
         <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p>ไม่สามารถดำเนินการได้ กรุณาตรวจสอบข้อมูล</p>
        </div>
         <div class="text-center">
             <a href="course.php?id=<?php echo $course_id; ?>" class="text-theme-secondary hover:underline">&laquo; กลับไปหน้าหลักสูตร</a>
        </div>
    <?php endif; ?>
</div>

<?php
// ไม่มี JS เฉพาะหน้า
$page_scripts = '';
require_once __DIR__ . '/includes/footer.php';
?>