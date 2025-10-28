<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 22:50 น.
API Endpoint สำหรับจัดการคอร์ส (เช่น ลงทะเบียน)
*/

header('Content-Type: application/json');

// --- 1. เรียกไฟล์ที่จำเป็น และ บังคับ Login ---
try {
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/db.php';
    requireLogin(); // บังคับล็อกอิน
    
} catch (Exception $e) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: ' . $e->getMessage()]);
    exit;
}

// --- 2. Router ---
$response = ['status' => 'error', 'message' => 'Invalid Action'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'enroll_course') {
        $response = handleEnrollCourse($db, $user_id);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    if ($e->getCode() == 23000) {
         $response = ['status' => 'error', 'message' => 'คุณได้ลงทะเบียนหลักสูตรนี้ไปแล้ว'];
    } else {
         $response = ['status' => 'error', 'message' => 'Database Error: ' . (DEBUG_MODE ? $e->getMessage() : 'Error')];
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    $response = ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);
exit;

// --- Logic Functions ---

/**
 * ลงทะเบียนเรียน
 */
function handleEnrollCourse($db, $user_id) {
    $course_id = $_POST['course_id'] ?? 0;
    if (empty($course_id)) {
        throw new Exception('Missing Course ID');
    }
    
    // 1. ตรวจสอบคอร์สว่าเปิดให้ลงทะเบียนหรือไม่
    $stmt_check = $db->prepare("
        SELECT status, enroll_end 
        FROM courses 
        WHERE course_id = ?
    ");
    $stmt_check->execute([$course_id]);
    $course = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        throw new Exception('ไม่พบหลักสูตรนี้');
    }
    if ($course['status'] !== 'ENROLL_OPEN') {
         throw new Exception('หลักสูตรนี้ยังไม่เปิดให้ลงทะเบียน (Status: ' . $course['status'] . ')');
    }
    if ($course['enroll_end'] !== null && strtotime($course['enroll_end']) < time()) {
         throw new Exception('เลยกำหนดเวลาลงทะเบียนสำหรับหลักสูตรนี้แล้ว');
    }
    
    // 2. (ตรวจสอบซ้ำ) ว่าเคยลงทะเบียนหรือยัง
    // (ปกติ PDOException 23000 จะดักจับ Unique Key `uq_user_course` ให้อยู่แล้ว)
    
    // 3. เพิ่มข้อมูลการลงทะเบียน
    $stmt_insert = $db->prepare("
        INSERT INTO enrollments (user_id, course_id, enrolled_at, status)
        VALUES (?, ?, NOW(), 'learning') 
    ");
    // (หมายเหตุ: status 'learning' หรือ 'enrolled' ขึ้นอยู่กับ Logic ที่ต้องการ)
    
    $stmt_insert->execute([$user_id, $course_id]);

    return ['status' => 'success', 'message' => 'ลงทะเบียนเรียนสำเร็จ!'];
}

?>