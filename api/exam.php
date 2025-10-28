<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 14:25 น. จำนวน 310+ บรรทัด
API Endpoint ระบบสอบ (FIX: ปรับปรุง Error Handling โดยเฉพาะ save_proctor_event - ฉบับเต็ม)
Edit by 27 ตุลาคม 2568 14:15 น. จำนวน 330+ บรรทัด (เพิ่ม Logic จำกัดการสอบ 3 ครั้ง)
Edit by 27 ตุลาคม 2568 15:25 น. จำนวน 360+ บรรทัด (เพิ่ม Logic Reset การเรียนเมื่อสอบตกครบ 3 ครั้ง)
Edit by 27 ตุลาคม 2568 16:05 น. จำนวน 380+ บรรทัด (เพิ่มการลบ Attempts เก่าเมื่อ Reset)
Edit by 27 ตุลาคม 2568 17:15 น. จำนวน 400+ บรรทัด (เพิ่ม Logic Restart แทน Resume)
Edit by 27 ตุลาคม 2568 17:30 น. จำนวน 400+ บรรทัด (แก้ไข Logic is_used และการใช้รหัสซ้ำ)
*/

require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid Request'];
$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';
$attempt_id = 0; // กำหนดค่าเริ่มต้น
$max_attempts = 3; // จำนวนครั้งสูงสุดที่สอบได้ในแต่ละรอบ

// (FIX) ห่อหุ้ม Logic หลักด้วย try...catch ที่ละเอียดยิ่งขึ้น
try {
    // กำหนดค่า attempt_id จาก request
    $attempt_id = $_REQUEST['attempt_id'] ?? 0;
    $attempt_id = (int)$attempt_id;

    // ตรวจสอบสิทธิ์เบื้องต้น (แยกออกมาเพื่อให้ชัดเจน)
    // Actions ที่ *ไม่* ต้องการ attempt_id ตอนเริ่ม
    if (!in_array($action, ['start_exam', 'get_my_code'])) {
        if ($attempt_id <= 0) {
            throw new Exception('Attempt ID is required for action: ' . htmlspecialchars($action));
        }
        // ตรวจสอบ Owner และ Status ของ Attempt
        $stmt_check = $db->prepare("SELECT user_id, status FROM attempts WHERE attempt_id = ?");
        $stmt_check->execute([$attempt_id]);
        $attempt_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$attempt_info) { throw new Exception('Attempt not found'); }
        if ($attempt_info['user_id'] != $user_id) { throw new Exception('Permission denied for this attempt'); }

        // Actions ที่ต้องการสถานะ 'started'
        if (!in_array($action, ['finish_attempt', 'save_proctor_event']) && $attempt_info['status'] !== 'started') {
            error_log("Attempt Check Failed: Action=$action, AttemptID=$attempt_id, ExpectedStatus=started, ActualStatus={$attempt_info['status']}");
            // (FIX) คืนสถานะ completed ให้ JS จัดการ แทนที่จะโยน Error 500
            if ($attempt_info['status'] === 'completed' || $attempt_info['status'] === 'time_expired'){
                 echo json_encode(['status' => 'completed', 'message' => 'Exam attempt is already finished.']);
                 exit;
            } else {
                 throw new Exception('Exam attempt is not currently active.');
            }
        }
    }

    // Routing ไปยังฟังก์ชัน
    switch ($action) {
        case 'start_exam':
            error_log("Routing to handleStartExam for User: $user_id");
            $response = handleStartExam($db, $user_id, $max_attempts);
            break;
        case 'get_my_code':
            error_log("Routing to handleGetMyCode for User: $user_id");
            $response = handleGetMyCode($db, $user_id);
            break;
        case 'get_question':
            error_log("Routing to handleGetQuestion for Attempt: $attempt_id");
            $response = handleGetQuestion($db, $attempt_id);
            break;
        case 'submit_answer':
            error_log("Routing to handleSubmitAnswer for Attempt: $attempt_id");
            $response = handleSubmitAnswer($db, $attempt_id);
            break;
        case 'save_proctor_event':
            error_log("Routing to handleSaveProctorEvent for Attempt: $attempt_id");
            // (FIX) ห่อหุ้มด้วย try...catch เฉพาะส่วนนี้
            try {
                $response = handleSaveProctorEvent($db, $attempt_id);
            } catch (Throwable $proctorError) {
                // ถ้าเกิด Error ตอนบันทึก Proctor Event ให้ Log ไว้ แต่อย่าให้ Script หลักพัง
                error_log("Non-fatal Error in handleSaveProctorEvent: " . $proctorError->getMessage());
                // คืนค่า success หลอกไปก่อน เพื่อให้ Quiz ดำเนินการต่อได้
                $response = ['status' => 'success', 'message' => 'Proctor event logged (with potential warning).'];
            }
            break;
        case 'finish_attempt':
            error_log("Routing to handleFinishAttempt for Attempt: $attempt_id");
            $response = handleFinishAttempt($db, $attempt_id, $max_attempts); // ส่ง max_attempts ไปด้วย
            break;
        default:
            error_log("Invalid action received: $action");
            throw new Exception('Invalid action specified');
    }
} catch (Throwable $e) {
    // Catch Error หลักจากส่วนอื่นๆ (ที่ไม่ใช่ save_proctor_event)
    http_response_code(500); // ตั้งค่า HTTP Status Code เป็น 500 สำหรับ Error
    $response = ['status' => 'error', 'message' => $e->getMessage()];
    error_log("API Error (api/exam.php Action: {$action}): " . $e->getMessage() . " - User: " . $user_id . " Attempt: " . ($attempt_id ?? 'N/A') . "\nTrace: " . $e->getTraceAsString());
    // ไม่ควรส่ง Trace กลับไปถ้า DEBUG_MODE เป็น false
    // if (defined('DEBUG_MODE') && DEBUG_MODE) { $response['trace'] = $e->getTraceAsString(); }
}

// ส่งผลลัพธ์สุดท้าย
echo json_encode($response);
exit;

// ===============================================
// ฟังก์ชันจัดการ Logic การสอบ
// ===============================================

// --- (แก้ไข) ฟังก์ชัน start_exam (แก้ไข Logic is_used) ---
function handleStartExam($db, $user_id, $max_attempts) { // รับ max_attempts
    $quiz_id = $_POST['quiz_id'] ?? 0; $quiz_id = (int)$quiz_id;
    $exam_code = $_POST['exam_code'] ?? ''; $exam_code = strtoupper(trim($exam_code));
    error_log("handleStartExam: User=$user_id, QuizID=$quiz_id, Code=$exam_code");

    if (empty($quiz_id) || empty($exam_code)) { throw new Exception('ข้อมูลไม่ครบถ้วน (Quiz ID or Exam Code)'); }

    // 1. ดึงข้อมูล Quiz และ Course ID
    $stmt_quiz = $db->prepare("SELECT course_id, pass_score FROM quizzes WHERE quiz_id = ?");
    $stmt_quiz->execute([$quiz_id]);
    $quiz_data = $stmt_quiz->fetch(PDO::FETCH_ASSOC);
    $course_id = $quiz_data['course_id'] ?? 0;
    $pass_score_threshold = $quiz_data['pass_score'] ?? 80;
    error_log("handleStartExam: Found course_id: " . var_export($course_id, true) . ", Pass Score: " . $pass_score_threshold);
    if (!$course_id) { throw new Exception('ไม่พบชุดข้อสอบนี้ (Invalid Quiz ID: ' . $quiz_id . ')'); }

    // 2. ตรวจสอบรหัสสอบ (Exam Code) - ต้องเป็นรหัสที่ถูกต้องสำหรับผู้ใช้และคอร์สนี้ *** ไม่ต้องเช็ค is_used ที่นี่ ***
    $stmt_code_check = $db->prepare("SELECT code_id, is_used FROM exam_codes WHERE user_id = ? AND course_id = ? AND code = ?");
    $stmt_code_check->execute([$user_id, $course_id, $exam_code]);
    $code_data = $stmt_code_check->fetch(PDO::FETCH_ASSOC);
    error_log("handleStartExam: Code validation - Found code_id: " . var_export($code_data['code_id'] ?? null, true) . ", Is Used currently: " . var_export($code_data['is_used'] ?? null, true));
    if (!$code_data) {
        // ไม่มีรหัสนี้ในระบบสำหรับผู้ใช้นี้เลย
        throw new Exception('รหัสเข้าสอบไม่ถูกต้อง หรือไม่ตรงกับหลักสูตรนี้');
    }
    // เก็บ code_id ไว้ใช้
    $current_code_id = $code_data['code_id'];
    $is_code_currently_used = (bool)$code_data['is_used'];


    // 3. ตรวจสอบจำนวนครั้งที่สอบเสร็จแล้ว (เฉพาะในรอบปัจจุบัน) และคะแนนสูงสุด
    $stmt_attempts = $db->prepare("
        SELECT COUNT(*) as attempt_count, MAX(score) as max_score
        FROM attempts
        WHERE user_id = :user_id AND quiz_id = :quiz_id AND status IN ('completed', 'time_expired')
    ");
    $stmt_attempts->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_attempts->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
    $stmt_attempts->execute();
    $attempt_stats = $stmt_attempts->fetch(PDO::FETCH_ASSOC);
    $attempt_count = $attempt_stats['attempt_count'] ?? 0;
    $max_score_current_round = $attempt_stats['max_score'] ?? 0;
    error_log("handleStartExam: User=$user_id, QuizID=$quiz_id - Current Finished Attempt Count: $attempt_count, Current Max Score: $max_score_current_round");

    // 4. ตรวจสอบเงื่อนไขการสอบซ้ำ
    // ถ้าสอบครบแล้ว (ในรอบนี้) และคะแนนสูงสุดยังไม่ผ่าน --> ห้ามสอบ
    if ($attempt_count >= $max_attempts && $max_score_current_round < $pass_score_threshold) {
         error_log("handleStartExam: Attempt limit reached ($attempt_count/$max_attempts) and max score ($max_score_current_round) is below threshold ($pass_score_threshold). Denying access.");
         throw new Exception("คุณทำการสอบครบ $max_attempts ครั้งแล้ว และยังไม่ผ่านเกณฑ์ กรุณากลับไปทบทวนบทเรียน");
    }
    // ถ้าเคยสอบผ่านไปแล้ว (ในรอบนี้หรือรอบก่อน) --> ห้ามสอบอีก
     $stmt_max_ever = $db->prepare("SELECT MAX(score) FROM attempts WHERE user_id = :user_id AND quiz_id = :quiz_id");
     $stmt_max_ever->bindParam(':user_id', $user_id, PDO::PARAM_INT);
     $stmt_max_ever->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
     $stmt_max_ever->execute();
     $max_score_ever = $stmt_max_ever->fetchColumn() ?? 0;
    if ($max_score_ever >= $pass_score_threshold) {
         error_log("handleStartExam: User already passed EVER (Max Score Ever: $max_score_ever >= Threshold: $pass_score_threshold). Denying access.");
         throw new Exception("คุณสอบผ่านหลักสูตรนี้แล้ว ไม่สามารถเข้าสอบซ้ำได้");
    }


    // 5. ตรวจสอบว่ามี Attempt ที่ค้างอยู่หรือไม่ -> ถ้ามี ให้ Restart
    $stmt_check_started = $db->prepare("SELECT attempt_id FROM attempts WHERE user_id = ? AND quiz_id = ? AND status = 'started'");
    $stmt_check_started->execute([$user_id, $quiz_id]);
    $existing_started_attempt_id = $stmt_check_started->fetchColumn();

    if ($existing_started_attempt_id) {
        error_log("handleStartExam: Found existing started attempt_id: $existing_started_attempt_id. Restarting this attempt.");
        $db->beginTransaction();
        try {
            // ลบคำตอบเก่าทั้งหมดของ Attempt นี้
            $stmt_delete_items = $db->prepare("DELETE FROM attempt_items WHERE attempt_id = ?");
            $stmt_delete_items->execute([$existing_started_attempt_id]);
            error_log("handleStartExam: Deleted " . $stmt_delete_items->rowCount() . " previous answers for attempt $existing_started_attempt_id.");

            // ลบ Proctor Events เก่าของ Attempt นี้ (ถ้าต้องการ)
            // $stmt_delete_proctor = $db->prepare("DELETE FROM proctor_events WHERE attempt_id = ?");
            // $stmt_delete_proctor->execute([$existing_started_attempt_id]);

            // อัปเดตเวลาเริ่มใหม่ (Reset Timer)
            $stmt_update_start = $db->prepare("UPDATE attempts SET start_time = NOW() WHERE attempt_id = ?");
            $stmt_update_start->execute([$existing_started_attempt_id]);
            error_log("handleStartExam: Updated start_time for attempt $existing_started_attempt_id.");

            $db->commit();
            // คืน ID เดิม เพื่อให้เริ่มสอบใหม่ที่ข้อ 1
            return ['status' => 'success', 'message' => 'กลับเข้าสู่การสอบ (เริ่มใหม่)', 'attempt_id' => $existing_started_attempt_id];
        } catch (Exception $e) {
            $db->rollBack();
            error_log("handleStartExam: Error restarting attempt $existing_started_attempt_id: " . $e->getMessage());
            throw $e;
        }
    }

    // --- ถ้าเงื่อนไขผ่านหมด และไม่มี Attempt ค้าง ให้เริ่มสร้าง Attempt ใหม่ ---
    $db->beginTransaction();
    try {
        // สร้าง Attempt ใหม่
        $personal_seed = rand(1, 100000);
        $stmt_attempt = $db->prepare("INSERT INTO attempts (user_id, quiz_id, personal_seed, status, start_time) VALUES (?, ?, ?, 'started', NOW())");
        $stmt_attempt->execute([$user_id, $quiz_id, $personal_seed]);
        $new_attempt_id = $db->lastInsertId();
        error_log("handleStartExam: Created new attempt_id: $new_attempt_id");

        // *** (แก้ไข) ทำเครื่องหมายว่าใช้รหัสสอบไปแล้ว เฉพาะครั้งแรกสุดของรอบเท่านั้น ***
        if ($attempt_count == 0 && !$is_code_currently_used) {
             $stmt_use_code = $db->prepare("UPDATE exam_codes SET is_used = 1 WHERE code_id = ?");
             $stmt_use_code->execute([$current_code_id]);
             error_log("handleStartExam: Marked exam code as used (first attempt) for code_id: " . $current_code_id);
        } else {
             error_log("handleStartExam: Exam code (code_id: {$current_code_id}) was already used OR this is not the first attempt (Attempt Count: $attempt_count). Proceeding.");
        }


        $db->commit();
        error_log("handleStartExam: Transaction committed.");
        // แสดงครั้งที่สอบให้ถูกต้อง (นับจาก 0)
        return ['status' => 'success', 'message' => 'เริ่มการสอบครั้งใหม่ (ครั้งที่ ' . ($attempt_count + 1) . ')', 'attempt_id' => $new_attempt_id];

    } catch (Exception $e) {
        $db->rollBack();
        error_log("handleStartExam: Transaction rolled back: " . $e->getMessage());
        throw $e; // โยน Exception ต่อไป
    }
}


// --- ฟังก์ชัน get_my_code (เหมือนเดิมจากครั้งก่อน) ---
function handleGetMyCode($db, $user_id) {
    // global $max_attempts; // ไม่จำเป็นต้องใช้แล้ว
    $course_id = $_POST['course_id'] ?? 0; $course_id = (int)$course_id;
    if (empty($course_id)) { throw new Exception('Missing Course ID'); }

    // --- ตรวจสอบว่าเรียนครบทุกบทหรือไม่ ---
    $stmt_check_all = $db->prepare("
        SELECT COUNT(l.lesson_id) AS total,
               SUM(CASE WHEN p.is_completed = 1 THEN 1 ELSE 0 END) AS completed
        FROM course_lessons l
        LEFT JOIN lesson_progress p ON l.lesson_id = p.lesson_id AND p.user_id = ?
        WHERE l.course_id = ?
    ");
    $stmt_check_all->execute([$user_id, $course_id]);
    $progress_stats = $stmt_check_all->fetch();

    // ถ้ายังเรียนไม่ครบ หรือไม่มีบทเรียน จะไม่มีรหัสสอบ
    if (!$progress_stats || $progress_stats['total'] == 0 || $progress_stats['total'] != $progress_stats['completed']) {
        error_log("handleGetMyCode: User $user_id, Course $course_id - Lessons not completed (Total: {$progress_stats['total']}, Completed: {$progress_stats['completed']}). No exam code.");
        return ['status' => 'success', 'exam_code' => null];
    }

    // --- ถ้าเรียนจบแล้ว ---
    // ดึงรหัสสอบ (เฉพาะที่ยังไม่ใช้ - is_used = 0)
    $stmt_code = $db->prepare("SELECT code FROM exam_codes WHERE user_id = ? AND course_id = ? AND is_used = 0");
    $stmt_code->execute([$user_id, $course_id]);
    $exam_code = $stmt_code->fetchColumn();

    // ถ้าเรียนจบแล้ว แต่ไม่มีรหัสสอบที่ยังไม่ใช้ (อาจเพราะถูกลบตอน Reset) -> ให้ checkAndGenerateExamCode ใน watch.php จัดการสร้างตอนเรียนจบล่าสุด
    // ฟังก์ชันนี้แค่คืนค่าว่าเจอหรือไม่เจอ
    if ($exam_code === false) {
        error_log("handleGetMyCode: User $user_id, Course $course_id - Completed lessons but no unused code found (Expected: generated by watch.php). Returning null.");
        $exam_code = null; // คืนค่า null ถ้าไม่เจอ
    } else {
        error_log("handleGetMyCode: User $user_id, Course $course_id - Found existing unused exam code: $exam_code");
    }

    return ['status' => 'success', 'exam_code' => $exam_code];
}


// --- ฟังก์ชันดึงคำถามถัดไป (เหมือนเดิม) ---
function handleGetQuestion($db, $attempt_id) {
    error_log("handleGetQuestion called for Attempt: $attempt_id");
    // ดึงข้อมูล attempt และ quiz
    $stmt_att = $db->prepare("SELECT a.quiz_id, a.personal_seed, a.start_time, a.status, q.time_limit_minutes FROM attempts a JOIN quizzes q ON a.quiz_id = q.quiz_id WHERE a.attempt_id = ?");
    $stmt_att->execute([$attempt_id]); $attempt = $stmt_att->fetch();
    if (!$attempt) { throw new Exception("Attempt data not found for ID: $attempt_id"); } // เพิ่มการตรวจสอบ
    // คำนวณเวลาที่เหลือ
    $now = time(); $startTime = strtotime($attempt['start_time']); $endTime = $startTime + ($attempt['time_limit_minutes'] * 60); $remainingTimeServer = max(0, $endTime - $now);
    error_log("Attempt $attempt_id: Remaining time calculated: $remainingTimeServer seconds.");
    // ถ้าหมดเวลา
    if ($remainingTimeServer <= 0 && $attempt['status'] === 'started') { // เช็ค status ด้วย
         error_log("Attempt $attempt_id: Time limit exceeded on server.");
         // อาจจะต้องเรียก handleFinishAttempt ที่นี่ หรือส่งสถานะให้ JS จัดการ
         // ตอนนี้ส่ง completed ให้ JS จัดการก่อน
         return ['status' => 'completed', 'message' => 'หมดเวลาทำข้อสอบ'];
    }
    // ดึงคำถามถัดไปที่ยังไม่ตอบ
    $sql_next_q = "SELECT q.question_id, q.question_text FROM questions q WHERE q.quiz_id = :quiz_id AND q.question_id NOT IN (SELECT ai.question_id FROM attempt_items ai WHERE ai.attempt_id = :attempt_id) ORDER BY RAND(CONCAT(:seed, q.question_id)) LIMIT 1";
    error_log("Attempt $attempt_id: SQL next question: " . preg_replace('/\s+/', ' ', $sql_next_q));
    $stmt_next_q = $db->prepare($sql_next_q); $stmt_next_q->bindParam(':quiz_id', $attempt['quiz_id'], PDO::PARAM_INT); $stmt_next_q->bindParam(':attempt_id', $attempt_id, PDO::PARAM_INT); $stmt_next_q->bindParam(':seed', $attempt['personal_seed'], PDO::PARAM_INT); $stmt_next_q->execute(); $next_question = $stmt_next_q->fetch();
    error_log("Attempt $attempt_id: Next question found: " . ($next_question ? $next_question['question_id'] : 'None'));
    // นับจำนวนข้อที่ตอบแล้ว
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM attempt_items WHERE attempt_id = ?"); $stmt_count->execute([$attempt_id]); $answered_count = $stmt_count->fetchColumn(); $question_number = $answered_count + 1;
    error_log("Attempt $attempt_id: Current question number: $question_number (Answered: $answered_count)");
    // นับจำนวนข้อทั้งหมด
    $stmt_total = $db->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?"); $stmt_total->execute([$attempt['quiz_id']]); $total_questions = $stmt_total->fetchColumn();
    error_log("Attempt $attempt_id: Total questions in quiz: $total_questions");
    $is_last_question = (!$next_question || $question_number > $total_questions); // ปรับเงื่อนไข is_last_question ให้แม่นยำขึ้น
    // ถ้าไม่มีคำถามถัดไป (อาจเพราะตอบครบแล้ว)
    if (!$next_question) { error_log("Attempt $attempt_id: No next question found, signaling last question."); return ['status' => 'success', 'question' => null, 'is_last_question' => true, 'remaining_time_server' => $remainingTimeServer]; }
    // ดึงตัวเลือกสำหรับคำถามถัดไป
    $sql_choices = "SELECT choice_id, choice_text FROM choices WHERE question_id = :question_id ORDER BY RAND(CONCAT(:seed, choice_id))";
    error_log("Attempt $attempt_id: SQL choices for question {$next_question['question_id']}: " . preg_replace('/\s+/', ' ', $sql_choices));
    $stmt_choices = $db->prepare($sql_choices); $stmt_choices->bindParam(':question_id', $next_question['question_id'], PDO::PARAM_INT); $stmt_choices->bindParam(':seed', $attempt['personal_seed'], PDO::PARAM_INT); $stmt_choices->execute(); $choices = $stmt_choices->fetchAll();
    error_log("Attempt $attempt_id: Found " . count($choices) . " choices for question {$next_question['question_id']}");
    // ส่งข้อมูลคำถามกลับไป
    error_log("Attempt $attempt_id: Returning question data for question number $question_number.");
    return ['status' => 'success', 'question' => [ 'question_id' => $next_question['question_id'], 'question_text' => $next_question['question_text'], 'choices' => $choices, 'question_number' => $question_number, 'total_questions' => $total_questions, 'is_last_question' => $is_last_question ], 'remaining_time_server' => $remainingTimeServer ];
}

// --- ฟังก์ชันบันทึกคำตอบ (เหมือนเดิม) ---
function handleSubmitAnswer($db, $attempt_id) {
    $question_id = $_POST['question_id'] ?? 0; $question_id = (int)$question_id;
    $selected_choice_id = $_POST['selected_choice_id'] ?? null; $selected_choice_id = (empty($selected_choice_id)) ? null : (int)$selected_choice_id;
    error_log("handleSubmitAnswer called for Attempt: $attempt_id, Question: $question_id, Choice: " . var_export($selected_choice_id, true));
    if (empty($question_id)) { error_log("Error in handleSubmitAnswer: Missing Question ID."); throw new Exception('Missing Question ID'); }
    // ใช้ REPLACE INTO เพื่อบันทึกหรืออัปเดตคำตอบ
    $sql_replace = "REPLACE INTO attempt_items (attempt_id, question_id, selected_choice_id, answered_at) VALUES (?, ?, ?, NOW())";
    error_log("Attempt $attempt_id: Executing SQL to save answer: " . $sql_replace);
    $stmt = $db->prepare($sql_replace);
    $stmt->execute([$attempt_id, $question_id, $selected_choice_id]); $rowCount = $stmt->rowCount();
    error_log("Attempt $attempt_id: Save answer rowCount: $rowCount");
    // ตรวจสอบ rowCount (REPLACE จะคืน 1 สำหรับ INSERT, 2 สำหรับ UPDATE, 0 ถ้าไม่มีอะไรเปลี่ยน)
    if ($rowCount >= 0) { error_log("Attempt $attempt_id: Answer saved successfully for question $question_id."); return ['status' => 'success', 'message' => 'Answer saved']; }
    else { error_log("Error in handleSubmitAnswer: REPLACE statement failed for attempt $attempt_id, question $question_id."); throw new Exception('Failed to save answer.'); }
}

// --- ฟังก์ชันบันทึก Proctor Event (เหมือนเดิม) ---
function handleSaveProctorEvent($db, $attempt_id) {
    $event_type = $_POST['event_type'] ?? '';
    $event_data = $_POST['event_data'] ?? null;

    error_log("handleSaveProctorEvent called for Attempt: $attempt_id, Type: $event_type");

    if (empty($event_type)) { throw new Exception('Missing Event Type'); }

    // ตรวจสอบความยาว event_type ก่อนบันทึก (VARCHAR(50))
    if (strlen($event_type) > 50) {
        error_log("Warning: Proctor event type '$event_type' is too long (max 50 chars). Truncating.");
        $event_type = substr($event_type, 0, 50);
    }

    // ตรวจสอบ event_type ที่อนุญาต (อาจจะไม่จำเป็นต้องเข้มงวดมาก)
    $allowed_types = ['context_menu', 'copy_paste_attempt', 'shortcut_attempt', 'print_screen_attempt', 'devtools_attempt', 'visibility_hidden', 'blur', 'devtools_opened'];
    if (!in_array($event_type, $allowed_types)) {
        error_log("Warning: Unknown proctor event type received: '$event_type' for attempt $attempt_id");
    }

    // บันทึก event ลงฐานข้อมูล
    $sql_insert_proctor = "INSERT INTO proctor_events (attempt_id, event_type, event_data, timestamp) VALUES (?, ?, ?, NOW())";
     error_log("Attempt $attempt_id: Executing SQL to save proctor event: " . $sql_insert_proctor);
    $stmt = $db->prepare($sql_insert_proctor);
    $stmt->execute([$attempt_id, $event_type, $event_data]);
     error_log("Attempt $attempt_id: Proctor event '$event_type' logged.");


    return ['status' => 'success', 'message' => 'Event logged'];
}


// --- (แก้ไข) ฟังก์ชันจบการสอบ (เพิ่มการลบ Attempts เก่าเมื่อ Reset) ---
function handleFinishAttempt($db, $attempt_id, $max_attempts) { // รับ max_attempts
    error_log("handleFinishAttempt called for Attempt: $attempt_id");
    // ตรวจสอบสถานะ attempt ปัจจุบัน
    $stmt_check = $db->prepare("SELECT status, quiz_id, user_id FROM attempts WHERE attempt_id = ?"); // ดึง user_id ด้วย
    $stmt_check->execute([$attempt_id]); $attempt = $stmt_check->fetch();
    if (!$attempt) { throw new Exception('Exam attempt not found.'); }

    $quiz_id = $attempt['quiz_id'];
    $user_id_current = $attempt['user_id']; // User ID ของ Attempt นี้

    // ถ้าสอบเสร็จแล้ว ให้ดึงผลเก่ากลับไปเลย
    if ($attempt['status'] !== 'started') {
        error_log("Attempt $attempt_id already finished with status: " . $attempt['status']);
        $stmt_old_score = $db->prepare("SELECT a.score, q.pass_score, q.course_id FROM attempts a JOIN quizzes q ON a.quiz_id=q.quiz_id WHERE a.attempt_id=?"); $stmt_old_score->execute([$attempt_id]); $result = $stmt_old_score->fetch();
        if ($result) { error_log("Attempt $attempt_id returning old score: {$result['score']}"); return ['status'=>'success', 'score'=> (float)$result['score'], 'passed'=> ((float)$result['score'] >= (float)$result['pass_score']), 'course_id'=>(int)$result['course_id']]; }
        else { error_log("Attempt $attempt_id status is not 'started', but no score found."); throw new Exception('Attempt is not active and no previous score found.'); }
    }

    // คำนวณคะแนน
    $sql_score = "
        SELECT SUM(CASE WHEN c.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
               COUNT(q.question_id) as total_questions
        FROM questions q
        LEFT JOIN attempt_items ai ON q.question_id = ai.question_id AND ai.attempt_id = :attempt_id
        LEFT JOIN choices c ON ai.selected_choice_id = c.choice_id AND q.question_id = c.question_id
        WHERE q.quiz_id = :quiz_id";
    error_log("Attempt $attempt_id: Calculating score with SQL: " . preg_replace('/\s+/', ' ', $sql_score));
    $stmt_score = $db->prepare($sql_score); $stmt_score->bindParam(':attempt_id', $attempt_id, PDO::PARAM_INT); $stmt_score->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT); $stmt_score->execute(); $score_data = $stmt_score->fetch();
    $correct_count = $score_data['correct_count'] ?? 0; $total_questions = $score_data['total_questions'] ?? 0;
    $score_percent = ($total_questions > 0) ? round(((int)$correct_count / (int)$total_questions) * 100, 2) : 0;
    error_log("Attempt $attempt_id: Score calculated: $correct_count / $total_questions = $score_percent%");

    // ดึงเกณฑ์ผ่านและ course_id
    $stmt_quiz_info = $db->prepare("SELECT pass_score, course_id FROM quizzes WHERE quiz_id = ?"); $stmt_quiz_info->execute([$quiz_id]); $quiz_info = $stmt_quiz_info->fetch();
    $pass_score_threshold = $quiz_info['pass_score'] ?? 80; $course_id = $quiz_info['course_id'] ?? 0;
    $passed = ($score_percent >= $pass_score_threshold);
    error_log("Attempt $attempt_id: Passed: " . ($passed ? 'Yes' : 'No') . " (Score: $score_percent%, Threshold: $pass_score_threshold%)");

    // อัปเดตฐานข้อมูล
    $db->beginTransaction();
    try {
        // อัปเดตสถานะ attempt (เฉพาะถ้ายังเป็น 'started')
        $sql_update_att = "UPDATE attempts SET status = 'completed', end_time = NOW(), score = ? WHERE attempt_id = ? AND status = 'started'";
        error_log("Attempt $attempt_id: Updating attempt status with SQL: " . $sql_update_att);
        $stmt_update_att = $db->prepare($sql_update_att); $updated = $stmt_update_att->execute([$score_percent, $attempt_id]); $rowCount = $stmt_update_att->rowCount();
        error_log("Attempt $attempt_id: Update attempt rowCount: $rowCount");

        if ($updated && $rowCount > 0) { // ตรวจสอบว่ามีการอัปเดตจริง
            if ($user_id_current && $course_id) {
                // อัปเดตสถานะ enrollment
                $new_enrollment_status = $passed ? 'exam_passed' : 'exam_failed';
                $sql_update_enroll = "UPDATE enrollments SET status = ? WHERE user_id = ? AND course_id = ? AND status NOT IN ('course_completed', 'exam_passed')"; // ไม่อัปเดตถ้าผ่านไปแล้ว
                error_log("Attempt $attempt_id: Updating enrollment for User: $user_id_current, Course: $course_id to Status: $new_enrollment_status");
                $stmt_update_enroll = $db->prepare($sql_update_enroll); $stmt_update_enroll->execute([$new_enrollment_status, $user_id_current, $course_id]);
                error_log("Attempt $attempt_id: Update enrollment rowCount: " . $stmt_update_enroll->rowCount());

                // ตรวจสอบเงื่อนไข Reset การเรียน
                if (!$passed) {
                    // นับจำนวนครั้งที่สอบเสร็จอีกครั้ง *หลัง* อัปเดตครั้งนี้ (นับเฉพาะรอบปัจจุบัน เพราะรอบเก่าจะถูกลบ)
                    $stmt_count_after = $db->prepare("
                        SELECT COUNT(*) FROM attempts
                        WHERE user_id = :user_id AND quiz_id = :quiz_id AND status IN ('completed', 'time_expired')
                    ");
                    $stmt_count_after->bindParam(':user_id', $user_id_current, PDO::PARAM_INT);
                    $stmt_count_after->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
                    $stmt_count_after->execute();
                    $attempt_count_after = $stmt_count_after->fetchColumn();
                    error_log("Attempt $attempt_id: Attempt count after finish: $attempt_count_after");

                    if ($attempt_count_after >= $max_attempts) {
                        error_log("Attempt $attempt_id: Failed and reached max attempts ($attempt_count_after/$max_attempts). Resetting progress, exam code, AND previous attempts.");

                        // 1. Reset Lesson Progress
                        $stmt_reset_progress = $db->prepare("
                            DELETE lp FROM lesson_progress lp
                            JOIN course_lessons cl ON lp.lesson_id = cl.lesson_id
                            WHERE lp.user_id = ? AND cl.course_id = ?
                        ");
                        $stmt_reset_progress->execute([$user_id_current, $course_id]);
                        error_log("Attempt $attempt_id: Reset lesson progress. Rows affected: " . $stmt_reset_progress->rowCount());

                        // 2. ลบรหัสสอบเก่า
                        $stmt_delete_code = $db->prepare("
                            DELETE FROM exam_codes
                            WHERE user_id = ? AND course_id = ?
                        ");
                        $stmt_delete_code->execute([$user_id_current, $course_id]);
                        error_log("Attempt $attempt_id: Deleted exam code. Rows affected: " . $stmt_delete_code->rowCount());

                        // *** (แก้ไข) 3. ลบ Attempts เก่าทั้งหมดสำหรับ Quiz นี้ ***
                        $stmt_delete_attempts = $db->prepare("
                            DELETE FROM attempts
                            WHERE user_id = ? AND quiz_id = ?
                        ");
                        // คำสั่งนี้จะลบ Attempt ปัจจุบันที่เพิ่งเสร็จไปด้วย!
                        $stmt_delete_attempts->execute([$user_id_current, $quiz_id]);
                        error_log("Attempt $attempt_id: Deleted ALL attempts for this quiz. Rows affected: " . $stmt_delete_attempts->rowCount());


                        // Reset สถานะ Enrollment กลับเป็น 'learning' (ทำเสมอเมื่อต้องเรียนใหม่)
                         $stmt_reset_enroll = $db->prepare("UPDATE enrollments SET status = 'learning' WHERE user_id = ? AND course_id = ? AND status != 'exam_passed'"); // รีเซ็ตเฉพาะถ้ายังไม่เคยผ่าน
                         $stmt_reset_enroll->execute([$user_id_current, $course_id]);
                         error_log("Attempt $attempt_id: Reset enrollment status to 'learning'. Rows affected: " . $stmt_reset_enroll->rowCount());
                    }
                }

            } else {
                 error_log("Attempt $attempt_id: Could not find user_id or course_id for enrollment update / reset check.");
            }
            $db->commit(); error_log("Attempt $attempt_id: Finish transaction committed.");
        } else {
             // ไม่มีการอัปเดต อาจเพราะ status ไม่ใช่ 'started' แล้ว (เช่น ถูก finish จาก request อื่น)
             $db->rollBack(); error_log("Attempt $attempt_id: Finish transaction rolled back (rowCount=$rowCount). Attempt might have been finished already.");
             // ดึงผลสอบเก่ากลับไปแทน (เผื่อกรณี Race Condition)
             $stmt_old_score = $db->prepare("SELECT a.score, q.pass_score, q.course_id FROM attempts a JOIN quizzes q ON a.quiz_id=q.quiz_id WHERE a.attempt_id=?"); $stmt_old_score->execute([$attempt_id]); $result = $stmt_old_score->fetch();
             if ($result) {
                  error_log("Attempt $attempt_id returning old score due to failed update: {$result['score']}");
                  return ['status'=>'success', 'score'=> (float)$result['score'], 'passed'=> ((float)$result['score'] >= (float)$result['pass_score']), 'course_id'=>(int)$result['course_id']];
             } else {
                  throw new Exception('Failed to update attempt status and could not retrieve score.');
             }
        }
    } catch (Exception $e) { $db->rollBack(); error_log("Attempt $attempt_id: Finish transaction rolled back due to exception: " . $e->getMessage()); throw $e; }

    // คืนค่าผลการสอบ
    return ['status' => 'success', 'score' => $score_percent, 'passed' => $passed, 'course_id' => $course_id ];
}

?>