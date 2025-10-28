<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 02:18 น. จำนวน 120+ บรรทัด
(FIX: PHP Warnings & JS Syntax Error - รวม PHP Block - ฉบับเต็ม)
Edit by 27 ตุลาคม 2568 13:55 น. จำนวน 140+ บรรทัด (เพิ่มการแสดงผลสอบ)
Edit by 27 ตุลาคม 2568 14:15 น. จำนวน 160+ บรรทัด (เพิ่ม Logic สอบซ้ำ 3 ครั้ง)
Edit by 27 ตุลาคม 2568 15:25 น. จำนวน 160+ บรรทัด (ปรับปรุง UI สอบซ้ำ)
Edit by 27 ตุลาคม 2568 15:35 น. จำนวน 180+ บรรทัด (เพิ่ม Logic ปุ่มเกียรติบัตร + cert_enabled)
Edit by 27 ตุลาคม 2568 15:55 น. จำนวน 200+ บรรทัด (แก้ไข Logic แสดงผลหลังสอบตกครบ 3 ครั้ง + เรียนใหม่)
Edit by 27 ตุลาคม 2568 17:15 น. จำนวน 220+ บรรทัด (ปรับปรุงเงื่อนไขแสดงผลสถานะสอบทั้งหมด + ปุ่ม Restart)
Edit by 27 ตุลาคม 2568 17:30 น. จำนวน 220+ บรรทัด (ปรับปรุงเงื่อนไขแสดงปุ่มสอบใหม่)
*/

$page_title = "รายละเอียดหลักสูตร";
// --- 1. เรียกไฟล์ที่จำเป็น ---
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // ตรวจสอบการล็อกอิน
require_once __DIR__ . '/includes/db.php'; // เชื่อมต่อ DB

// --- 2. รับค่าและตรวจสอบ ---
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id']; // ดึง User ID จาก Session
$error_message = null; // ตัวแปรเก็บข้อผิดพลาด
$course_title = 'N/A'; // ค่าเริ่มต้น
$quiz_id = 0; // ค่าเริ่มต้น
$user_exam_code_unused = null; // รหัสที่ยังไม่ใช้ (is_used=0)
$user_exam_code_latest = null; // รหัสล่าสุดของผู้ใช้ (ไม่สน is_used)
$lessons = []; // กำหนดค่าเริ่มต้น
$course = null; // กำหนดค่าเริ่มต้น (รวม cert_enabled)
$latest_attempt = null; // ตัวแปรเก็บผลสอบล่าสุด
$attempt_count = 0; // จำนวนครั้งที่สอบเสร็จแล้ว (ในรอบปัจจุบัน)
$max_attempts = 3; // จำนวนครั้งสูงสุดที่สอบได้ในแต่ละรอบ
$cert_enabled_for_course = false; // สถานะการเปิดให้พิมพ์เกียรติบัตร
$all_lessons_completed_current = false; // สถานะว่าเรียนจบรอบปัจจุบันหรือไม่

if ($course_id === 0) {
    // ถ้าไม่มี course_id ส่งกลับไป dashboard
    header("Location: dashboard.php");
    exit;
}

// --- 3. ดึงข้อมูลจากฐานข้อมูล ---
try {
    // 1. ดึงข้อมูลคอร์ส (รวม cert_enabled)
    $stmt_course = $db->prepare("SELECT *, cert_enabled FROM courses WHERE course_id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        throw new Exception("ไม่พบหลักสูตรนี้");
    }
    // กำหนดชื่อ Page Title และสถานะ cert_enabled
    $page_title = $course['title'];
    $cert_enabled_for_course = (bool)$course['cert_enabled'];

    // 2. ดึงข้อมูลบทเรียน (พร้อมความก้าวหน้า) และตรวจสอบว่าเรียนจบรอบปัจจุบันหรือไม่
    $stmt_lessons = $db->prepare("
        SELECT
            l.lesson_id, l.title, l.video_type,
            p.is_completed, p.last_watched_time
        FROM course_lessons l
        LEFT JOIN lesson_progress p ON l.lesson_id = p.lesson_id AND p.user_id = ?
        WHERE l.course_id = ?
        ORDER BY l.lesson_order ASC
    ");
    $stmt_lessons->execute([$user_id, $course_id]);
    $lessons = $stmt_lessons->fetchAll();
    // ตรวจสอบว่าเรียนจบรอบปัจจุบันหรือไม่
    $total_lessons_in_course = count($lessons);
    $completed_lessons_count = 0;
    if ($total_lessons_in_course > 0) {
        foreach ($lessons as $lesson) {
            if ($lesson['is_completed'] ?? false) {
                $completed_lessons_count++;
            }
        }
    }
    $all_lessons_completed_current = ($total_lessons_in_course > 0 && $completed_lessons_count == $total_lessons_in_course);


    // 3. ดึง Quiz ID, เกณฑ์ผ่าน และรหัสสอบ
    $stmt_quiz_info = $db->prepare("SELECT quiz_id, pass_score FROM quizzes WHERE course_id = ?");
    $stmt_quiz_info->execute([$course_id]);
    $quiz_info = $stmt_quiz_info->fetch(PDO::FETCH_ASSOC);
    $quiz_id = $quiz_info['quiz_id'] ?? 0;
    $pass_score_threshold = $quiz_info['pass_score'] ?? 80;

    // ดึงรหัสสอบ (ทั้งที่ใช้แล้วและยังไม่ใช้)
    if ($quiz_id > 0) { // ดึงเฉพาะเมื่อมี Quiz
        $stmt_codes = $db->prepare("SELECT code, is_used FROM exam_codes WHERE user_id = ? AND course_id = ? ORDER BY code_id DESC LIMIT 1");
        $stmt_codes->execute([$user_id, $course_id]);
        $code_info = $stmt_codes->fetch(PDO::FETCH_ASSOC);
        if ($code_info) {
             $user_exam_code_latest = $code_info['code']; // รหัสล่าสุดที่เคยได้รับ
             if ($code_info['is_used'] == 0) {
                 $user_exam_code_unused = $code_info['code']; // รหัสล่าสุดที่ยังไม่ถูกใช้
             }
        }
    }


    // 4. ดึงข้อมูลการสอบครั้งล่าสุด และนับจำนวนครั้งที่สอบเสร็จแล้ว (ถ้ามี Quiz ID)
    // การนับครั้งนี้จะนับเฉพาะ attempts ที่ยังไม่ถูกลบ (คือรอบปัจจุบัน)
    if ($quiz_id > 0) {
        // ดึงครั้งล่าสุด (อาจจะไม่มี ถ้าเพิ่ง Reset มา)
        $stmt_attempt = $db->prepare("
            SELECT attempt_id, status, score, end_time
            FROM attempts
            WHERE user_id = :user_id AND quiz_id = :quiz_id
            ORDER BY start_time DESC
            LIMIT 1
        ");
        $stmt_attempt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_attempt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
        $stmt_attempt->execute();
        $latest_attempt = $stmt_attempt->fetch(PDO::FETCH_ASSOC);

        // นับจำนวนครั้งที่สอบเสร็จแล้ว (ในรอบปัจจุบัน)
        $stmt_count = $db->prepare("
            SELECT COUNT(*)
            FROM attempts
            WHERE user_id = :user_id AND quiz_id = :quiz_id AND status IN ('completed', 'time_expired')
        ");
         $stmt_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
         $stmt_count->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
         $stmt_count->execute();
         $attempt_count = (int)$stmt_count->fetchColumn(); // แปลงเป็น int
    }


} catch (Throwable $e) { // ใช้ Throwable เพื่อดัก Error ทุกประเภท
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    // กำหนด page_title กรณีเกิด Error ก่อนเรียก header
    $page_title = "เกิดข้อผิดพลาด";
}

// --- 4. เรียก Header ---
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error_message): ?>
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md text-center">
        <h1 class="text-2xl font-bold text-red-500">เกิดข้อผิดพลาด</h1>
        <p class="text-gray-700 mt-4"><?php echo htmlspecialchars($error_message); ?></p>
        <a href="dashboard.php" class="inline-block mt-6 btn-primary py-2 px-4 rounded-md">กลับไปหน้าแดชบอร์ด</a>
    </div>
<?php elseif (!$course): ?>
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md text-center">
        <h1 class="text-2xl font-bold text-red-500">ไม่พบข้อมูล</h1>
        <p class="text-gray-700 mt-4">ไม่พบข้อมูลหลักสูตรที่คุณร้องขอ</p>
        <a href="dashboard.php" class="inline-block mt-6 btn-primary py-2 px-4 rounded-md">กลับไปหน้าแดชบอร์ด</a>
    </div>
<?php else: ?>
    <h1 class="text-3xl font-bold text-theme-primary mb-2"><?php echo htmlspecialchars($course['title']); ?></h1>
    <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($course['description']); ?></p>

    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-md shadow-sm">
        <h3 class="text-xl font-semibold mb-2">สถานะการสอบ</h3>
        <?php
            $passed_latest = false;
            $score = 0;
            $is_attempt_finished = false;
            $is_attempt_started = false;

            if ($latest_attempt) {
                if ($latest_attempt['status'] === 'completed' || $latest_attempt['status'] === 'time_expired') {
                    $is_attempt_finished = true;
                    $score = (float)$latest_attempt['score'];
                    $passed_latest = ($score >= $pass_score_threshold);
                } elseif ($latest_attempt['status'] === 'started') {
                    $is_attempt_started = true;
                }
            }

            // --- ลำดับการแสดงผล ---

            // 1. กรณีไม่มีข้อสอบ
            if ($quiz_id === 0) {
                echo "<p>ยังไม่มีชุดข้อสอบสำหรับหลักสูตรนี้</p>";
            }
            // 2. สอบผ่านแล้ว (ไม่ว่าจะครั้งไหนก็ตาม)
            elseif ($is_attempt_finished && $passed_latest) {
                echo "<p>คุณทำการสอบเสร็จสิ้นแล้ว (ครั้งที่ $attempt_count/$max_attempts) เมื่อ " . date('d/m/Y H:i', strtotime($latest_attempt['end_time'])) . " น.</p>";
                echo "<p class=\"my-2\">คะแนนที่ได้:</p>";
                echo "<div class=\"text-3xl font-bold text-center p-3 rounded-md border bg-green-100 border-green-300 text-green-700\">";
                echo number_format($score, 2) . "%";
                echo "<span class=\"text-lg font-medium\">(เกณฑ์ผ่าน $pass_score_threshold%)</span>";
                echo "</div>";
                echo "<p class=\"text-center font-semibold mt-2 text-green-600\"><i class=\"fas fa-check-circle mr-1\"></i> ผ่านเกณฑ์</p>";

                if ($cert_enabled_for_course) {
                    echo "<a href=\"certificate_preview.php?course_id=$course_id\" class=\"mt-4 inline-block w-full text-center bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-md transition duration-150\">";
                    echo "<i class=\"fas fa-print mr-2\"></i> พิมพ์เกียรติบัตร";
                    echo "</a>";
                } else {
                    echo "<button disabled class=\"mt-4 inline-block w-full text-center bg-gray-400 text-white font-bold py-3 px-4 rounded-md cursor-not-allowed\">";
                    echo "<i class=\"fas fa-print mr-2\"></i> พิมพ์เกียรติบัตร (ยังไม่เปิดให้พิมพ์)";
                    echo "</button>";
                }
            }
            // 3. มีการสอบค้างอยู่ (Status: started)
            elseif ($is_attempt_started) {
                 echo "<p class=\"text-yellow-700\"><i class=\"fas fa-exclamation-circle mr-1\"></i> คุณมีการสอบที่ยังทำไม่เสร็จ (ครั้งที่ " . ($attempt_count + 1) . "/$max_attempts)</p>";
                 // แสดงรหัสล่าสุดที่ใช้ (เผื่อผู้ใช้ลืม)
                 if ($user_exam_code_latest) {
                     echo "<p class=\"my-1 text-sm\">รหัสสอบที่ใช้ล่าสุด: <span class=\"font-mono\">$user_exam_code_latest</span></p>";
                 }
                 echo "<a href=\"exam-gate.php?course_id=$course_id\" class=\"mt-4 inline-block w-full text-center bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-4 rounded-md transition duration-150\">";
                     echo "<i class=\"fas fa-play-circle mr-2\"></i> กลับไปทำข้อสอบต่อ (เริ่มใหม่)";
                 echo "</a>";
            }
            // 4. สอบตกครบ 3 ครั้งแล้ว (attempt_count >= max_attempts และ !passed_latest)
            elseif ($is_attempt_finished && !$passed_latest && $attempt_count >= $max_attempts) {
                 // 4.1 เรียนจบรอบใหม่แล้ว และมีรหัสใหม่ (unused code)
                if ($all_lessons_completed_current && $user_exam_code_unused) {
                     echo "<p class=\"mt-4 text-green-600 font-semibold\"><i class=\"fas fa-check-circle mr-1\"></i> คุณเรียนทบทวนบทเรียนครบแล้ว สามารถเข้าสอบใหม่ได้ (เริ่มนับครั้งที่ 1 ใหม่)</p>";
                      echo "<p class=\"my-2\">รหัสเข้าสอบใหม่ของคุณคือ:</p>";
                      echo "<div class=\"text-2xl font-bold text-center bg-white text-blue-800 p-3 rounded-md border border-blue-200 tracking-widest break-all\">";
                          echo htmlspecialchars($user_exam_code_unused);
                      echo "</div>";
                     echo "<a href=\"exam-gate.php?course_id=$course_id\" class=\"mt-4 inline-block w-full text-center btn-primary font-bold py-3 px-4 rounded-md hover:bg-theme-primary transition duration-150\">";
                         echo "<i class=\"fas fa-pencil-alt mr-2\"></i> ไปที่หน้ายืนยันรหัสสอบ (เริ่มครั้งที่ 1 ใหม่)";
                     echo "</a>";
                }
                // 4.2 เรียนจบรอบใหม่แล้ว แต่ยังไม่ได้รหัส (รอระบบสร้าง/แสดง)
                elseif ($all_lessons_completed_current && !$user_exam_code_unused) {
                     echo "<p class=\"mt-4 text-green-600 font-semibold\"><i class=\"fas fa-check-circle mr-1\"></i> คุณเรียนทบทวนบทเรียนครบแล้ว กำลังดำเนินการออกรหัสสอบใหม่ กรุณา Refresh หน้าจอ</p>";
                     echo "<button disabled class=\"mt-2 inline-block w-full text-center bg-gray-400 text-white font-bold py-3 px-4 rounded-md cursor-not-allowed\">";
                         echo "รอรหัสสอบ";
                     echo "</button>";
                }
                // 4.3 ยังไม่ได้เรียนจบรอบใหม่ (แสดงผลสอบครั้งสุดท้ายที่ตก)
                else {
                    echo "<p>คุณทำการสอบเสร็จสิ้นแล้ว (ครั้งที่ $attempt_count/$max_attempts) เมื่อ " . date('d/m/Y H:i', strtotime($latest_attempt['end_time'])) . " น.</p>";
                    echo "<p class=\"my-2\">คะแนนครั้งล่าสุด:</p>";
                    echo "<div class=\"text-3xl font-bold text-center p-3 rounded-md border bg-red-100 border-red-300 text-red-700\">";
                    echo number_format($score, 2) . "%";
                    echo "<span class=\"text-lg font-medium\">(เกณฑ์ผ่าน $pass_score_threshold%)</span>";
                    echo "</div>";
                    echo "<p class=\"text-center font-semibold mt-2 text-red-600\"><i class=\"fas fa-times-circle mr-1\"></i> ยังไม่ผ่านเกณฑ์</p>";
                     echo "<p class=\"mt-4 text-center text-red-600 font-semibold\">";
                           echo "<i class=\"fas fa-exclamation-triangle mr-1\"></i> คุณสอบครบ $max_attempts ครั้งแล้ว และยังไม่ผ่านเกณฑ์ กรุณากลับไปทบทวนบทเรียนเพื่อรับสิทธิ์สอบใหม่";
                      echo "</p>";
                     echo "<button disabled class=\"mt-2 inline-block w-full text-center bg-gray-400 text-white font-bold py-3 px-4 rounded-md cursor-not-allowed\">";
                         echo "สอบใหม่";
                     echo "</button>";
                }
            }
            // 5. สอบตก แต่ยังไม่ครบ 3 ครั้ง ($is_attempt_finished && !$passed_latest && $attempt_count < $max_attempts)
            elseif ($is_attempt_finished && !$passed_latest && $attempt_count < $max_attempts) {
                echo "<p>คุณทำการสอบเสร็จสิ้นแล้ว (ครั้งที่ $attempt_count/$max_attempts) เมื่อ " . date('d/m/Y H:i', strtotime($latest_attempt['end_time'])) . " น.</p>";
                echo "<p class=\"my-2\">คะแนนที่ได้:</p>";
                echo "<div class=\"text-3xl font-bold text-center p-3 rounded-md border bg-red-100 border-red-300 text-red-700\">";
                echo number_format($score, 2) . "%";
                echo "<span class=\"text-lg font-medium\">(เกณฑ์ผ่าน $pass_score_threshold%)</span>";
                echo "</div>";
                echo "<p class=\"text-center font-semibold mt-2 text-red-600\"><i class=\"fas fa-times-circle mr-1\"></i> ยังไม่ผ่านเกณฑ์</p>";
                // แสดงรหัสล่าสุดที่ใช้ (รหัสเดิม)
                if ($user_exam_code_latest) {
                     echo "<p class=\"my-1 text-sm\">ใช้รหัสสอบเดิม: <span class=\"font-mono\">$user_exam_code_latest</span></p>";
                 }
                echo "<a href=\"exam-gate.php?course_id=$course_id\" class=\"mt-4 inline-block w-full text-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-md transition duration-150\">";
                    echo "<i class=\"fas fa-redo mr-2\"></i> สอบใหม่ (ครั้งที่ " . ($attempt_count + 1) . "/$max_attempts)";
                echo "</a>";
            }
            // 6. เรียนจบแล้ว + มีรหัสที่ยังไม่ใช้ (กรณีสอบครั้งแรก)
            elseif ($all_lessons_completed_current && $user_exam_code_unused) {
                 echo "<p>คุณเรียนจบบทเรียนทั้งหมดแล้ว!</p>";
                 echo "<p class=\"my-2\">รหัสเข้าสอบ (Exam Code) ของคุณคือ:</p>";
                 echo "<div class=\"text-2xl font-bold text-center bg-white text-blue-800 p-3 rounded-md border border-blue-200 tracking-widest break-all\">";
                     echo htmlspecialchars($user_exam_code_unused);
                 echo "</div>";
                 $next_attempt_num = $attempt_count + 1; // ควรเป็น 1
                 echo "<a href=\"exam-gate.php?course_id=$course_id\" class=\"mt-4 inline-block w-full text-center btn-primary font-bold py-3 px-4 rounded-md hover:bg-theme-primary transition duration-150\">";
                     echo "<i class=\"fas fa-pencil-alt mr-2\"></i> ไปที่หน้ายืนยันรหัสสอบ (ครั้งที่ $next_attempt_num/$max_attempts)";
                 echo "</a>";
            }
             // 7. เรียนจบแล้ว แต่ไม่มีรหัสสอบที่ยังไม่ใช้ (รอระบบสร้าง/แสดง - กรณีที่ไม่ใช่สอบตกครบ)
            elseif ($all_lessons_completed_current && !$user_exam_code_unused) {
                 echo "<p class=\"text-green-600 font-semibold\"><i class=\"fas fa-check-circle mr-1\"></i> คุณเรียนบทเรียนครบแล้ว กำลังดำเนินการออกรหัสสอบ กรุณา Refresh หน้าจอ</p>";
                  echo "<button disabled class=\"mt-2 inline-block w-full text-center bg-gray-400 text-white font-bold py-3 px-4 rounded-md cursor-not-allowed\">";
                      echo "รอรหัสสอบ";
                  echo "</button>";
            }
            // 8. ยังเรียนไม่จบ (และไม่ใช่กรณีสอบตกครบ 3 ครั้ง)
            else {
                 echo "<p>กรุณาเรียนบทเรียนทั้งหมดให้ครบ (สถานะ <i class=\"fas fa-check-circle text-green-500\"></i>) เพื่อรับรหัสเข้าสอบอัตโนมัติ</p>";
            }
        ?>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mt-6">
        <h2 class="text-2xl font-semibold text-theme-secondary mb-4">รายการบทเรียน</h2>

        <?php if (empty($lessons)): ?>
            <p class="text-gray-500">ยังไม่มีบทเรียนในหลักสูตรนี้</p>
        <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($lessons as $lesson): ?>
                    <li class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 border rounded-md <?php echo ($lesson['is_completed'] ?? false) ? 'bg-green-50 border-green-200' : 'border-gray-200'; ?>">
                        <div class="flex items-center mb-2 sm:mb-0">
                            <?php if ($lesson['is_completed'] ?? false): ?>
                                <span class="text-green-500 mr-3 flex-shrink-0">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400 mr-3 flex-shrink-0">
                                     <i class="far fa-circle fa-lg"></i>
                                </span>
                            <?php endif; ?>

                            <span class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($lesson['title']); ?></span>
                        </div>

                        <?php
                        // กำหนดค่าเริ่มต้นภายใน Loop เสมอ
                        $button_text = "เริ่มเรียน";
                        $button_class = "btn-primary"; // (สีน้ำเงิน/เขียวธีม)
                        $button_icon = "fa-play-circle"; // ไอคอนเริ่มต้น
                        $extra_attrs = ""; // Attribute เพิ่มเติมสำหรับ JS

                        if ($lesson['is_completed'] ?? false) {
                            $button_text = "ผ่านแล้ว"; // ข้อความเริ่มต้น
                            // สีเริ่มต้น: เขียว / Hover: สี Teal (เขียวน้ำทะเล) เข้มขึ้น
                            $button_class = "bg-green-500 hover:bg-teal-700 text-white review-button"; // เพิ่มคลาส review-button
                            $button_icon = "fa-check-circle"; // ไอคอนเริ่มต้น
                            // Attribute สำหรับ JS เก็บข้อความ Hover
                            $extra_attrs = ' data-hover-text="ทบทวนเนื้อหา" data-hover-icon="fa-redo-alt" ';
                        } elseif (isset($lesson['last_watched_time']) && $lesson['last_watched_time'] > 0) { // ตรวจสอบ isset ก่อนใช้
                            $button_text = "เรียนต่อ";
                            $button_class = "bg-red-600 hover:bg-red-700 text-white"; // (สีแดง)
                            $button_icon = "fa-play-circle"; // ไอคอนเล่นต่อ
                        }
                        ?>

                        <a href="watch.php?lesson_id=<?php echo $lesson['lesson_id']; ?>"
                           class="w-full sm:w-auto text-center text-sm font-medium py-2 px-4 rounded-md shadow-sm transition duration-150 <?php echo $button_class; ?>"
                           <?php echo $extra_attrs; ?> >
                           <i class="button-icon fas <?php echo $button_icon; ?> mr-1"></i> <span class="button-text"><?php echo $button_text; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; // (จบ if empty($lessons)) ?>
    </div>

<?php
// Script สำหรับ Hover effect (เหมือนเดิม)
if (!empty($lessons)) {
    $page_scripts = <<<JS
<script>
    // Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
    // Edit by 25 ตุลาคม 2568 02:18 น. จำนวน 20 บรรทัด
    $(document).ready(function() {
        $('body').on('mouseenter', '.review-button', function() {
            let \$button = $(this);
            let \$textSpan = \$button.find('.button-text');
            let \$iconElement = \$button.find('.button-icon');
            if (!\$textSpan.length || !\$iconElement.length) return;
            let originalText = \$textSpan.text();
            let classList = \$iconElement.attr('class').split(' ');
            let originalIcon = classList.length > 1 ? classList[classList.length - 1] : '';
            let hoverText = \$button.data('hover-text');
            let hoverIcon = \$button.data('hover-icon');
            if (!\$button.data('original-text')) { \$button.data('original-text', originalText); }
            if (!\$button.data('original-icon')) { \$button.data('original-icon', originalIcon); }
            if (hoverText) { \$textSpan.text(hoverText); }
            if (hoverIcon && originalIcon) { \$iconElement.removeClass(originalIcon).addClass(hoverIcon); }
        });
        $('body').on('mouseleave', '.review-button', function() {
            let \$button = $(this);
            let \$textSpan = \$button.find('.button-text');
            let \$iconElement = \$button.find('.button-icon');
            if (!\$textSpan.length || !\$iconElement.length) return;
            let originalText = \$button.data('original-text');
            let originalIcon = \$button.data('original-icon');
            let hoverIcon = \$button.data('hover-icon');
            if (originalText) { \$textSpan.text(originalText); }
            if (originalIcon && hoverIcon) { \$iconElement.removeClass(hoverIcon).addClass(originalIcon); }
        });
    });
</script>
JS;
} else {
    $page_scripts = '';
}

// --- เรียก Footer ---
require_once __DIR__ . '/includes/footer.php';

endif; // (จบ else ของ if ($error_message) หรือ if (!$course))
?>