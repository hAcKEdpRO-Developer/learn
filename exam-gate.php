<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 01:32 น. จำนวน 80+ บรรทัด
(FIX: แสดงรหัสสอบให้ผู้ใช้เห็นก่อนกรอก - ฉบับเต็ม)
*/

$page_title = "ยืนยันการเข้าสอบ";
// --- 1. เรียกไฟล์ที่จำเป็น ---
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // ตรวจสอบการล็อกอิน
require_once __DIR__ . '/includes/db.php'; // เชื่อมต่อ DB

// --- 2. รับค่าและตรวจสอบ ---
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$user_id = $_SESSION['user_id']; // ดึง User ID จาก Session
$error_message = null; // ตัวแปรเก็บข้อผิดพลาด
$course_title = 'N/A'; // ค่าเริ่มต้น
$quiz_id = 0; // ค่าเริ่มต้น
$user_exam_code = null; // ค่าเริ่มต้น

if ($course_id === 0) {
    // ถ้าไม่มี course_id ส่งกลับไป dashboard
    header("Location: dashboard.php");
    exit;
}

// --- 3. ดึงข้อมูลจากฐานข้อมูล ---
try {
    // ดึงชื่อคอร์ส, Quiz ID, และ รหัสสอบของผู้ใช้คนนี้ สำหรับคอร์สนี้
    $stmt = $db->prepare("
        SELECT c.title, q.quiz_id, ec.code as exam_code
        FROM courses c
        LEFT JOIN quizzes q ON c.course_id = q.course_id
        LEFT JOIN exam_codes ec ON c.course_id = ec.course_id AND ec.user_id = :user_id
        WHERE c.course_id = :course_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("ไม่พบข้อมูลหลักสูตรหรือชุดข้อสอบ");
    }

    $course_title = $data['title'];
    $quiz_id = $data['quiz_id'] ?? 0; // ใช้ ?? เพื่อป้องกัน null
    $user_exam_code = $data['exam_code']; // รหัสสอบของผู้ใช้

    if ($quiz_id === 0) {
         throw new Exception("ยังไม่มีชุดข้อสอบสำหรับหลักสูตรนี้");
    }

} catch (Throwable $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// --- 4. เรียก Header ---
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-lg mx-auto bg-white p-6 md:p-8 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-center text-theme-primary mb-4">ยืนยันการเข้าสอบ</h1>
    <p class="text-center text-lg text-gray-700 mb-6"><?php echo htmlspecialchars($course_title); ?></p>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">เกิดข้อผิดพลาด</p>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
        <div class="text-center">
             <a href="course.php?id=<?php echo $course_id; ?>" class="text-theme-secondary hover:underline">&laquo; กลับไปหน้าหลักสูตร</a>
        </div>
    <?php elseif (!$user_exam_code): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p class="font-bold">ยังไม่ได้รับรหัสสอบ</p>
            <p>กรุณาตรวจสอบว่าคุณได้เรียนบทเรียนทั้งหมดครบถ้วนแล้ว</p>
        </div>
         <div class="text-center">
             <a href="course.php?id=<?php echo $course_id; ?>" class="text-theme-secondary hover:underline">&laquo; กลับไปหน้าหลักสูตร</a>
        </div>
    <?php else: ?>
        <div class="mb-6 text-center">
            <p class="text-sm text-gray-600 mb-1">รหัสเข้าสอบ (Exam Code) ของคุณคือ:</p>
            <p class="text-3xl font-bold text-green-600 bg-green-50 p-3 rounded-md border border-green-200 tracking-widest break-all">
                <?php echo htmlspecialchars($user_exam_code); ?>
            </p>
            <p class="text-xs text-gray-500 mt-1">(รหัสนี้ใช้ได้ครั้งเดียว)</p>
        </div>

        <form id="exam-gate-form" method="POST" class="mt-8">
            <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz_id); ?>">
            <div class="mb-4">
                <label for="exam_code" class="block text-sm font-medium text-gray-700">
                    กรุณาพิมพ์รหัสเข้าสอบด้านบน เพื่อยืนยัน:
                </label>
                <input type="text" id="exam_code" name="exam_code"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-center text-2xl tracking-[0.2em] uppercase focus:ring-theme-secondary focus:border-theme-secondary"
                       required
                       autocomplete="off"
                       maxlength="20"
                       style="text-transform: uppercase;"
                       placeholder="พิมพ์รหัสที่เห็นด้านบน">
            </div>

            <div class="mt-6">
                <button type="submit" class="w-full btn-primary font-semibold py-3 px-4 rounded-md shadow-sm text-lg hover:bg-theme-primary transition duration-150">
                    <i class="fas fa-play-circle mr-2"></i> เริ่มทำข้อสอบ
                </button>
            </div>
            <div class="text-center mt-4">
                 <a href="course.php?id=<?php echo $course_id; ?>" class="text-sm text-gray-500 hover:underline">ยกเลิก</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
// ส่วน JavaScript สำหรับ Submit Form (ฉบับเต็ม)
$page_scripts = <<<JS
<script>
    // Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
    // Edit by 25 ตุลาคม 2568 01:32 น. จำนวน 30 บรรทัด
    $(document).ready(function() {
        // ทำให้ Input เป็นตัวพิมพ์ใหญ่เสมอ
        $('#exam_code').on('input', function() {
            this.value = this.value.toUpperCase();
        });

        $("#exam-gate-form").on("submit", function(e) {
            e.preventDefault();
            // ตรวจสอบเบื้องต้นว่ากรอกรหัสหรือไม่
            if ($('#exam_code').val().trim() === '') {
                 showAlert("warning", "กรุณากรอกรหัสสอบ");
                 return false;
            }

            showLoading("กำลังตรวจสอบรหัส...");

            $.ajax({
                type: "POST",
                url: "api/exam.php?action=start_exam",
                data: $(this).serialize(),
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        // เมื่อรหัสถูก ส่งไปหน้าสอบจริง
                        Swal.fire({
                            icon: 'success',
                            title: 'รหัสถูกต้อง!',
                            text: 'กำลังนำคุณไปยังหน้าทำข้อสอบ...',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false
                        }).then(() => {
                            window.location.href = "quiz.php?attempt_id=" + response.attempt_id;
                        });
                    } else {
                        // แสดงข้อผิดพลาดจาก Server
                        showAlert("error", "ผิดพลาด!", response.message || "รหัสเข้าสอบไม่ถูกต้อง");
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    hideLoading();
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    showAlert("error", "เกิดข้อผิดพลาด", "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ หรือมีข้อผิดพลาดเกิดขึ้น");
                }
            });
            return false; // ป้องกันการ Submit ปกติ
        });

        // ฟังก์ชัน showAlert (ถ้ายังไม่มีใน common.js)
        if (typeof showAlert === 'undefined') {
            window.showAlert = function(icon, title, text = '') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: icon, title: title, text: text });
                } else {
                    alert(title + (text ? "\\n" + text : ""));
                }
            }
        }
        if (typeof showLoading === 'undefined') {
            window.showLoading = function(message = 'Loading...') {
                 if (typeof $.LoadingOverlay !== 'undefined') $.LoadingOverlay("show", { text: message });
            }
            window.hideLoading = function() {
                if (typeof $.LoadingOverlay !== 'undefined') $.LoadingOverlay("hide");
            }
        }
    });
</script>
JS;
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>