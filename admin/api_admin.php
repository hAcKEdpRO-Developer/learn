<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 21:00 น.
API Endpoint สำหรับระบบหลังบ้าน (Admin)
Edit by 27 ตุลาคม 2568 22:10 น. (เพิ่ม Action update_user)
Edit by 27 ตุลาคม 2568 22:30 น. (เพิ่ม Add, Delete, Reset Pass User)
Edit by 27 ตุลาคม 2568 22:50 น. (เพิ่ม Actions สำหรับ Admin Profile)
Edit by 27 ตุลาคม 2568 23:00 น. (Final API - แก้ไข Bug require_once, เพิ่ม Course Actions, เพิ่ม Reset Email)
*/

header('Content-Type: application/json');

// --- 1. เรียกไฟล์ที่จำเป็น และ บังคับสิทธิ์ Admin ---
try {
    // *** (แก้ไข) ห้าม require 'admin_header.php' ใน API ***
    // *** เราต้องเรียกไฟล์ที่จำเป็นแยกเอง ***
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/db.php';
    
    // (ฟังก์ชันนี้อยู่ใน includes/functions.php)
    // (แก้ไข) เพิ่มการตรวจสอบ Staff ด้วย
    if (!hasRole('admin') && !hasRole('staff')) {
        throw new Exception('Access Denied. Requires Admin or Staff role.');
    }
    
} catch (Exception $e) {
    // กรณีนี้มักเกิดถ้า session หมดอายุ หรือไม่ใช่ Admin
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: ' . $e->getMessage()]);
    exit;
}

// --- 2. Router ---
$response = ['status' => 'error', 'message' => 'Invalid Action'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id_session = $_SESSION['user_id']; // เก็บ ID ของ Admin ที่กำลังทำรายการ

try {
    // Course Actions
    if ($action === 'toggle_cert') {
        $response = handleToggleCert($db);
    } elseif ($action === 'update_course') {
        $response = handleUpdateCourse($db);
    } elseif ($action === 'add_course') {
        $response = handleAddCourse($db);
    }
    
    // User Actions
    elseif ($action === 'update_user') {
        $response = handleUpdateUser($db, $user_id_session);
    } elseif ($action === 'add_user') {
        $response = handleAddUser($db);
    } elseif ($action === 'delete_user') {
        $response = handleDeleteUser($db, $user_id_session);
    } elseif ($action === 'reset_pass_manual') {
        $response = handleResetPassManual($db);
    } elseif ($action === 'reset_pass_email') {
        $response = handleResetPassEmail($db);
    }
    
    // Admin Profile Actions
    elseif ($action === 'update_admin_text') {
        $response = handleUpdateAdminProfileText($db, $user_id_session);
    } elseif ($action === 'change_admin_password') {
        $response = handleChangeAdminPassword($db, $user_id_session);
    } elseif ($action === 'upload_admin_picture') {
        $response = handleUploadAdminPicture($db, $user_id_session);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    // (เพิ่ม) ตรวจสอบ Error Code สำหรับ Unique Constraint
    if ($e->getCode() == 23000) { // Integrity constraint violation
         $response = ['status' => 'error', 'message' => 'ข้อมูลซ้ำ (เช่น อีเมลนี้ถูกใช้แล้ว)'];
    } else {
         $response = ['status' => 'error', 'message' => 'Database Error: ' . (DEBUG_MODE ? $e->getMessage() : 'Error')];
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    $response = ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
}

echo json_encode($response);
exit;

// ===============================================
// ฟังก์ชันจัดการ Logic หลักสูตร (Course)
// ===============================================

/**
 * สลับสถานะ (toggle) cert_enabled ของคอร์ส
 */
function handleToggleCert($db) {
    $course_id = $_POST['course_id'] ?? 0;
    if (empty($course_id)) {
        throw new Exception('Missing Course ID');
    }
    
    $stmt = $db->prepare("UPDATE courses SET cert_enabled = NOT cert_enabled WHERE course_id = ?");
    $stmt->execute([$course_id]);
    
    $stmt_check = $db->prepare("SELECT cert_enabled FROM courses WHERE course_id = ?");
    $stmt_check->execute([$course_id]);
    $new_state = $stmt_check->fetchColumn();
    
    $message = $new_state == 1 ? 'เปิดใช้งานการพิมพ์เกียรติบัตรแล้ว' : 'ปิดใช้งานการพิมพ์เกียรติบัตรแล้ว';
    
    return [
        'status' => 'success',
        'message' => $message,
        'course_id' => $course_id,
        'new_state' => (int)$new_state
    ];
}

/**
 * (เพิ่ม) อัปเดตข้อมูลหลักสูตร
 */
function handleUpdateCourse($db) {
    $course_id = $_POST['course_id'] ?? 0;
    if (empty($course_id)) {
        throw new Exception('Missing Course ID');
    }
    
    // (เรียกฟังก์ชัน helper เพื่อ Bind ข้อมูล)
    $params = bindCourseDataFromPost();
    $params[] = $course_id; // เพิ่ม ID ที่ท้ายสำหรับ WHERE
    
    $sql = "UPDATE courses SET 
                title = ?, short_code = ?, description = ?, 
                academic_year = ?, status = ?, 
                enroll_start = ?, enroll_end = ?, 
                learn_start = ?, learn_end = ?, 
                exam_start = ?, exam_end = ?, 
                cert_start = ?, cert_end = ?
            WHERE course_id = ?";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return ['status' => 'success', 'message' => 'อัปเดตข้อมูลหลักสูตรสำเร็จ'];
}

/**
 * (เพิ่ม) เพิ่มหลักสูตรใหม่
 */
function handleAddCourse($db) {
    // (เรียกฟังก์ชัน helper เพื่อ Bind ข้อมูล)
    $params = bindCourseDataFromPost();
    
     $sql = "INSERT INTO courses 
                (title, short_code, description, 
                academic_year, status, 
                enroll_start, enroll_end, 
                learn_start, learn_end, 
                exam_start, exam_end, 
                cert_start, cert_end, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return ['status' => 'success', 'message' => 'เพิ่มหลักสูตรใหม่สำเร็จ'];
}

/**
 * (เพิ่ม) Helper: ดึงข้อมูลคอร์สจาก $_POST (ป้องกัน Null)
 */
function bindCourseDataFromPost() {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $short_code = filter_input(INPUT_POST, 'short_code', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $academic_year = filter_input(INPUT_POST, 'academic_year', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    // Function to convert empty string to null
    $emptyToNull = function($value) {
        return (empty(trim($value))) ? null : trim($value);
    };

    $enroll_start = $emptyToNull($_POST['enroll_start'] ?? null);
    $enroll_end = $emptyToNull($_POST['enroll_end'] ?? null);
    $learn_start = $emptyToNull($_POST['learn_start'] ?? null);
    $learn_end = $emptyToNull($_POST['learn_end'] ?? null);
    $exam_start = $emptyToNull($_POST['exam_start'] ?? null);
    $exam_end = $emptyToNull($_POST['exam_end'] ?? null);
    $cert_start = $emptyToNull($_POST['cert_start'] ?? null);
    $cert_end = $emptyToNull($_POST['cert_end'] ?? null);

    if (empty($title) || empty($short_code) || empty($academic_year) || empty($status)) {
         throw new Exception('ข้อมูลหลัก (ชื่อ, รหัสย่อ, ปี, สถานะ) ไม่ครบถ้วน');
    }
    
    return [
        $title, $short_code, $description,
        $academic_year, $status,
        $enroll_start, $enroll_end,
        $learn_start, $learn_end,
        $exam_start, $exam_end,
        $cert_start, $cert_end
    ];
}

// ===============================================
// ฟังก์ชันจัดการ Logic ผู้ใช้งาน (User)
// ===============================================

/**
 * อัปเดตข้อมูลผู้ใช้ (โดย Admin)
 */
function handleUpdateUser($db, $admin_user_id) {
    $user_id = $_POST['user_id'] ?? 0;
    $prefix = filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_STRING);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
    $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);

    if (empty($user_id) || empty($firstname) || empty($lastname) || $role_id === false || $is_active === null) { // is_active = 0 ถือว่า valid
         throw new Exception('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง (is_active: ' . $is_active . ')');
    }
    
    // (เพิ่มการป้องกัน) ห้าม Admin แก้ไข Role หรือ Status ของตัวเอง
    if ($user_id == $admin_user_id) {
        $stmt = $db->prepare("UPDATE users SET prefix = ?, firstname = ?, lastname = ? WHERE user_id = ?");
        $stmt->execute([$prefix, $firstname, $lastname, $user_id]);
        $_SESSION['firstname'] = $firstname; // อัปเดต Session ของตัวเอง
        return ['status' => 'success', 'message' => 'บันทึกข้อมูลส่วนตัว (ชื่อ-นามสกุล) สำเร็จ'];
    }
    
    // ถ้าแก้ไขคนอื่น อนุญาตให้แก้ Role และ Status ได้
    $stmt = $db->prepare("
        UPDATE users 
        SET prefix = ?, firstname = ?, lastname = ?, role_id = ?, is_active = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$prefix, $firstname, $lastname, $role_id, $is_active, $user_id]);

    return ['status' => 'success', 'message' => 'บันทึกข้อมูลผู้ใช้ ID ' . $user_id . ' สำเร็จ'];
}

/**
 * (เพิ่ม) เพิ่มผู้ใช้งานใหม่ (โดย Admin)
 */
function handleAddUser($db) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $prefix = filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_STRING);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
    $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);

    if (empty($email) || empty($password) || empty($firstname) || empty($lastname) || $role_id === false || $is_active === null) {
         throw new Exception('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง');
    }
    if (strlen($password) < 6) {
        throw new Exception('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
    }

    // Hash รหัสผ่าน
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // (อีเมลซ้ำจะถูกดักโดย PDOException 23000)
    
    $stmt = $db->prepare("
        INSERT INTO users (email, password, prefix, firstname, lastname, role_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$email, $hashedPassword, $prefix, $firstname, $lastname, $role_id, $is_active]);

    return ['status' => 'success', 'message' => 'เพิ่มผู้ใช้งาน ' . $email . ' สำเร็จ'];
}

/**
 * (เพิ่ม) ลบผู้ใช้งาน (โดย Admin)
 */
function handleDeleteUser($db, $admin_user_id) {
    $user_id = $_POST['user_id'] ?? 0;
    
    if (empty($user_id)) {
        throw new Exception('Missing User ID');
    }
    
    // (เพิ่มการป้องกัน) ห้ามลบตัวเอง
    if ($user_id == $admin_user_id) {
        throw new Exception('ไม่สามารถลบบัญชีของตัวเองได้');
    }
    
    // (เพิ่มการป้องกัน) ห้ามลบ Admin คนอื่น (ถ้าต้องการ)
    // $stmt_check = $db->prepare("SELECT role_id FROM users WHERE user_id = ?");
    // $stmt_check->execute([$user_id]);
    // $role_to_delete = $stmt_check->fetchColumn();
    // if ($role_to_delete == 1) { // สมมติ 1 คือ Admin
    //    throw new Exception('ไม่สามารถลบ Admin คนอื่นได้');
    // }

    // ทำการลบ (Hard Delete) - (ข้อควรระวัง: ข้อมูล Enrollment, Progress ฯลฯ จะหายไปด้วยเพราะ ON DELETE CASCADE)
    $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 'success', 'message' => 'ลบผู้ใช้งาน ID ' . $user_id . ' สำเร็จ'];
    } else {
        throw new Exception('ไม่พบผู้ใช้งาน ID ' . $user_id . ' ที่จะลบ');
    }
}

/**
 * (เพิ่ม) Reset รหัสผ่านโดย Admin (กำหนดเอง)
 */
function handleResetPassManual($db) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_password = $_POST['new_password'] ?? '';

    if (empty($user_id) || empty($new_password)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน (User ID or New Password)');
    }
     if (strlen($new_password) < 6) {
        throw new Exception('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
    }
    
    // Hash รหัสผ่านใหม่
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$hashedPassword, $user_id]);

    return ['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำหรับผู้ใช้ ID ' . $user_id . ' สำเร็จ'];
}

/**
 * (เพิ่ม) Reset รหัสผ่านโดย Admin (ส่งอีเมล)
 * (หมายเหตุ: ฟังก์ชันนี้ต้องการการตั้งค่า SMTP ที่ถูกต้อง)
 */
function handleResetPassEmail($db) {
    $user_id = $_POST['user_id'] ?? 0;
    $email = $_POST['email'] ?? '';
    
    if (empty($user_id) || empty($email)) {
        throw new Exception('Missing User ID or Email');
    }
    
    // (Logic ส่วนนี้คล้ายกับ Resend OTP / Forgot Password)
    // 1. สร้าง Token/Password ชั่วคราว
    $new_password = generateOTP(); // ใช้ OTP 6 หลัก เป็นรหัสผ่านชั่วคราว
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

    // 2. อัปเดตฐานข้อมูล
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ? AND email = ?");
        $stmt->execute([$hashedPassword, $user_id, $email]);
        
        if ($stmt->rowCount() == 0) {
            throw new Exception('ไม่พบผู้ใช้ที่ตรงกับ ID และ Email นี้');
        }

        // 3. ส่งอีเมลแจ้งรหัสผ่านใหม่
        // (ต้องเรียกใช้ PHPMailer - ใช้ฟังก์ชันจาก api/auth.php มาประยุกต์)
        $emailSent = sendNewPasswordEmail($db, $email, $new_password);
        
        if (!$emailSent) {
             throw new Exception('ไม่สามารถส่งอีเมลรหัสผ่านใหม่ได้ (SMTP Error)');
        }

        $db->commit();
        // (ไม่ควรแสดงรหัสผ่านใน Message แต่แสดงเพื่อ Debug)
        return ['status' => 'success', 'message' => 'ส่งรหัสผ่านใหม่ไปยัง ' . $email . ' สำเร็จแล้ว (รหัสผ่านชั่วคราว: ' . $new_password . ')'];
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}


// ===============================================
// (เพิ่ม) FUNCTIONS FOR ADMIN PROFILE (admin/profile.php)
// ===============================================

/**
 * อัปเดตข้อมูล Text (ชื่อ, นามสกุล) (สำหรับ Admin)
 */
function handleUpdateAdminProfileText($db, $admin_user_id) {
  $prefix = filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_STRING);
  $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
  $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
  
  if (empty($firstname) || empty($lastname)) {
    return ['status' => 'error', 'message' => 'กรุณากรอกชื่อและนามสกุล'];
  }

  $stmt = $db->prepare("UPDATE users SET prefix = ?, firstname = ?, lastname = ? WHERE user_id = ?");
  $stmt->execute([$prefix, $firstname, $lastname, $admin_user_id]);
  
  // อัปเดต Session ด้วย
  $_SESSION['firstname'] = $firstname;
  
  return ['status' => 'success', 'message' => 'บันทึกข้อมูลสำเร็จ', 'new_firstname' => $firstname];
}

/**
 * เปลี่ยนรหัสผ่านของ Admin เอง
 */
function handleChangeAdminPassword($db, $admin_user_id) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_new_password)) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    if ($new_password !== $confirm_new_password) {
        throw new Exception('รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
    }
     if (strlen($new_password) < 6) {
        throw new Exception('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
    }
    
    // 1. ดึงรหัสผ่านเดิม
    $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$admin_user_id]);
    $hashed_password_db = $stmt->fetchColumn();

    // 2. ตรวจสอบรหัสผ่านเดิม
    if (!password_verify($old_password, $hashed_password_db)) {
         throw new Exception('รหัสผ่านเดิมไม่ถูกต้อง');
    }
    
    // 3. Hash และอัปเดตรหัสผ่านใหม่
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt_update = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt_update->execute([$new_hashed_password, $admin_user_id]);
    
    return ['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ!'];
}


/**
 * รับ Base64 (รูปโปรไฟล์ Admin)
 */
function handleUploadAdminPicture($db, $admin_user_id) {
  $base64Image = $_POST['image_base64'] ?? '';
  if (empty($base64Image)) {
    return ['status' => 'error', 'message' => 'ไม่พบข้อมูลรูปภาพ'];
  }

  // 1. ตรวจสอบและถอดรหัส Base64
  if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
    $data = substr($base64Image, strpos($base64Image, ',') + 1);
    $type = strtolower($type[1]); // png, jpeg, gif
    if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
      return ['status' => 'error', 'message' => 'รูปแบบไฟล์ไม่ถูกต้อง'];
    }
    $data = base64_decode($data);
    if ($data === false) {
      return ['status' => 'error', 'message' => 'ข้อมูล Base64 ไม่ถูกต้อง'];
    }
  } else {
    return ['status' => 'error', 'message' => 'รูปแบบ Base64 ไม่ถูกต้อง'];
  }

  // 2. สร้างชื่อไฟล์และ Path
  $upload_dir = ROOT_PATH . '/uploads/profile/';
  if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
  }
  
  $filename = 'profile_' . $admin_user_id . '_' . time() . '_' . uniqid() . '.' . $type;
  $filepath = $upload_dir . $filename;

  // 3. บันทึกไฟล์
  if (file_put_contents($filepath, $data)) {
    
        // (เพิ่ม Logic ลบรูปเก่า)
        $stmt_old = $db->prepare("SELECT profile_pic FROM users WHERE user_id = ?");
        $stmt_old->execute([$admin_user_id]);
        $old_pic = $stmt_old->fetchColumn();
        if ($old_pic && $old_pic !== 'default.png' && file_exists($upload_dir . $old_pic)) {
            @unlink($upload_dir . $old_pic);
        }

    // 4. อัปเดตฐานข้อมูล
    $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
    $stmt->execute([$filename, $admin_user_id]);
    
        $new_image_url = BASE_URL . '/uploads/profile/' . $filename;
    return ['status' => 'success', 'message' => 'อัปโหลดรูปภาพสำเร็จ', 'new_image_url' => $new_image_url];
  } else {
    return ['status' => 'error', 'message' => 'ไม่สามารถบันทึกไฟล์ลงเซิร์ฟเวอร์ได้'];
  }
}

// (เพิ่ม) ฟังก์ชันสำหรับส่งอีเมล Reset Pass (ต้องใช้ PHPMailer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendNewPasswordEmail($db, $toEmail, $new_password) {
    // เรียกใช้ PHPMailer
    require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
    require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // ดึงการตั้งค่า SMTP จาก DB
        $settings = [];
        $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%'");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // ตั้งค่า Server (เหมือนใน api/auth.php)
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // (DEBUG_SERVER ถ้าต้องการดู Log)
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'] ?? 'localhost';
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'] ?? 'user';
        $mail->Password   = $settings['smtp_pass'] ?? 'pass';
        $mail->SMTPSecure = $settings['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $settings['smtp_port'] ?? 587;
        
        $mail->setFrom($settings['smtp_user'] ?? 'noreply@learn.system', SITE_NAME);
        $mail->addAddress($toEmail);
        
        $mail->isHTML(true);
        $mail->CharSet = "UTF-8";
        $mail->Subject = 'รหัสผ่านใหม่สำหรับ ' . SITE_NAME;
        $mail->Body    = "สวัสดีครับ,<br><br>
                          นี่คือรหัสผ่านชั่วคราวใหม่ของคุณ (Admin ได้ทำการ Reset ให้): <br><br>
                          <h1 style='font-size: 2.5em; letter-spacing: 0.1em; color: #DC2626;'>$new_password</h1>
                          <br>
                          กรุณาใช้รหัสผ่านนี้เพื่อเข้าสู่ระบบ และเปลี่ยนรหัสผ่านทันทีที่หน้าโปรไฟล์ของคุณ<br><br>
                          ขอแสดงความนับถือ,<br>
                          ทีมงาน " . SITE_NAME;
        
        $mail->AltBody = "รหัสผ่านใหม่ของคุณคือ: $new_password";
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("PHPMailer (Reset Pass) Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>