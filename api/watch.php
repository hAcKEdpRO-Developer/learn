<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:55 น. จำนวน 100+ บรรทัด
API Endpoint (อัปเดต: เพิ่ม Logic สร้างรหัสสอบอัตโนมัติ)
*/

require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid Request'];
$user_id = $_SESSION['user_id'];

try {
    $lesson_id = $_POST['lesson_id'] ?? 0;
    $last_watched_time = $_POST['last_watched_time'] ?? 0;
    $is_completed = $_POST['is_completed'] ?? 0;
    $violation_count = $_POST['violation_count'] ?? 0;

    if (empty($lesson_id)) {
        throw new Exception('Missing Lesson ID');
    }

    // 1. บันทึกความก้าวหน้า (เหมือนเดิม)
    $stmt = $db->prepare("
        INSERT INTO lesson_progress (user_id, lesson_id, last_watched_time, is_completed, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            last_watched_time = VALUES(last_watched_time),
            is_completed = VALUES(is_completed),
            updated_at = NOW()
    ");

    $stmt->execute([
        $user_id,
        $lesson_id,
        $last_watched_time,
        $is_completed
    ]);
    
    // 2. (ส่วนที่เพิ่มใหม่) ถ้าบทเรียนนี้เพิ่ง "ผ่าน" (is_completed = 1)
    if ($is_completed == 1) {
        // ให้ไปตรวจสอบว่าเรียนจบคอร์สหรือยัง เพื่อสร้างรหัสสอบ
        checkAndGenerateExamCode($db, $user_id, $lesson_id);
    }
    
    $response = ['status' => 'success', 'message' => 'Progress saved'];

} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;

// ===============================================
// (ฟังก์ชันใหม่) ตรวจสอบและสร้างรหัสสอบ
// ===============================================

/**
 * ตรวจสอบว่าผู้เรียน เรียนจบคอร์สหรือยัง (จากบทเรียนล่าสุดที่เพิ่งผ่าน)
 * ถ้าจบแล้ว ให้สร้างรหัสสอบ (Exam Code)
 */
function checkAndGenerateExamCode($db, $user_id, $completed_lesson_id) {
    try {
        // 1. ค้นหา Course ID จาก Lesson ID
        $stmt_course = $db->prepare("SELECT course_id FROM course_lessons WHERE lesson_id = ?");
        $stmt_course->execute([$completed_lesson_id]);
        $course = $stmt_course->fetch();
        
        if (!$course) return; // ไม่พบคอร์ส
        
        $course_id = $course['course_id'];

        // 2. ตรวจสอบว่ามีรหัสสอบสำหรับคอร์สนี้ + ผู้นี้ แล้วหรือยัง
        $stmt_check_code = $db->prepare("SELECT code_id FROM exam_codes WHERE user_id = ? AND course_id = ?");
        $stmt_check_code->execute([$user_id, $course_id]);
        if ($stmt_check_code->fetch()) {
            return; // มีรหัสแล้ว ไม่ต้องสร้างซ้ำ
        }

        // 3. ตรวจสอบว่า "ทุกบทเรียน" ในคอร์สนี้ "ผ่าน" (is_completed = 1) หรือยัง
        $stmt_check_all = $db->prepare("
            SELECT COUNT(l.lesson_id) AS total_lessons, 
                   SUM(CASE WHEN p.is_completed = 1 THEN 1 ELSE 0 END) AS completed_lessons
            FROM course_lessons l
            LEFT JOIN lesson_progress p ON l.lesson_id = p.lesson_id AND p.user_id = ?
            WHERE l.course_id = ?
        ");
        $stmt_check_all->execute([$user_id, $course_id]);
        $progress_stats = $stmt_check_all->fetch();

        // 4. ถ้าจำนวนที่ผ่าน == จำนวนทั้งหมด (และมีบทเรียน > 0)
        if ($progress_stats && $progress_stats['total_lessons'] > 0 && $progress_stats['total_lessons'] == $progress_stats['completed_lessons']) {
            
            // 5. สร้างรหัสสอบ (ตามข้อกำหนด: SHORT_CODE + สุ่ม)
            $stmt_short_code = $db->prepare("SELECT short_code FROM courses WHERE course_id = ?");
            $stmt_short_code->execute([$course_id]);
            $short_code = $stmt_short_code->fetchColumn();
            
            // สร้างรหัสสุ่ม 6-10 ตัว (เช่น PHP101 + สุ่ม 3 ตัว = 8 ตัว)
            $random_suffix = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
            $exam_code = strtoupper($short_code) . '_' . $random_suffix; // เช่น "PHP101_A8Z"

            // 6. บันทึกลงฐานข้อมูล
            $stmt_insert_code = $db->prepare("
                INSERT INTO exam_codes (user_id, course_id, code)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE code = code -- ไม่ทำอะไรถ้าซ้ำ
            ");
            $stmt_insert_code->execute([$user_id, $course_id, $exam_code]);
        }
        
    } catch (Exception $e) {
        // (ไม่จำเป็นต้องแจ้งผู้ใช้ แค่ log ไว้)
        error_log("Error generating exam code: " . $e->getMessage());
    }
}
?>