<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:40 น. จำนวน 250 บรรทัด
API Endpoint (FIX: ย้าย 'use' statements กลับไปที่ Global Scope)
*/

// (FIX) 'use' statements ต้องอยู่ที่นี่ (Global Scope)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- 2. เรียกใช้ Core files ---
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/db.php';

// --- 3. ตั้งค่าการตอบกลับเป็น JSON ---
header('Content-Type: application/json');

// --- 4. ตัวแปรสำหรับตอบกลับ ---
$response = ['status' => 'error', 'message' => 'การร้องขอไม่ถูกต้อง'];

// --- 5. Router (ตาม action) ---
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'register':
            $response = handleRegister($db);
            break;
        case 'verify_email':
            $response = handleVerifyEmail($db);
            break;
        case 'resend_otp':
            $response = handleResendOTP($db);
            break;
        case 'login':
            $response = handleLogin($db);
            break;
        case 'logout':
            $response = handleLogout();
            break;
        case 'forgot_password':
            $response = ['status' => 'info', 'message' => 'ฟังก์ชันนี้ยังไม่เปิดใช้งาน'];
            break;
        case 'reset_password':
            $response = ['status' => 'info', 'message' => 'ฟังก์ชันนี้ยังไม่เปิดใช้งาน'];
            break;
        default:
            $response = ['status' => 'error', 'message' => 'ไม่พบ Action ที่ร้องขอ'];
    }
} catch (PDOException $e) {
    $response = ['status' => 'error', 'message' => 'ฐานข้อมูลมีปัญหา: ' . (DEBUG_MODE ? $e->getMessage() : 'กรุณาติดต่อผู้ดูแล')];
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดทั่วไป: ' . (DEBUG_MODE ? $e->getMessage() : 'กรุณาติดต่อผู้ดูแล')];
}

// --- 6. ส่งการตอบกลับ ---
echo json_encode($response);
exit;

// ===============================================
// ฟังก์ชันสำหรับจัดการ Logic
// ===============================================

/**
 * จัดการการสมัครสมาชิก
 */
function handleRegister($db) {
    // 1. ตรวจสอบ Input
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $prefix = filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_STRING);

    if (empty($email) || empty($password) || empty($firstname) || empty($lastname)) {
        return ['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'];
    }

    // 2. ตรวจสอบอีเมลซ้ำ
    $stmt = $db->prepare("SELECT user_id, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['is_active']) {
        return ['status' => 'error', 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'];
    }

    // 3. Hash รหัสผ่าน (BCRYPT)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT); 
    
    // 4. สร้าง OTP
    $otp = generateOTP();
    
    // 5. บันทึกข้อมูล
    $db->beginTransaction();
    
    if ($user && !$user['is_active']) {
        $stmt = $db->prepare("UPDATE users SET password = ?, firstname = ?, lastname = ?, prefix = ? WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $firstname, $lastname, $prefix, $user['user_id']]);
        $userId = $user['user_id'];
    } else {
        $stmt = $db->prepare("INSERT INTO users (email, password, firstname, lastname, prefix, role_id, is_active) VALUES (?, ?, ?, ?, ?, 4, 0)");
        $stmt->execute([$email, $hashedPassword, $firstname, $lastname, $prefix]);
        $userId = $db->lastInsertId();
    }
    
    // 6. บันทึก OTP
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $db->prepare("INSERT INTO email_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'verify_email', ?)");
    $stmt->execute([$userId, $otp, $expiresAt]);

    // 7. ส่งอีเมล OTP
    $emailSent = sendOTPEmail($db, $email, $firstname, $otp);
    
    if ($emailSent) {
        $db->commit();
        return ['status' => 'success', 'message' => 'สมัครสมาชิกสำเร็จ! กรุณาตรวจสอบอีเมลเพื่อยืนยันตัวตน (OTP 6 หลัก)'];
    } else {
        $db->rollBack();
        return ['status' => 'error', 'message' => 'ไม่สามารถส่งอีเมลยืนยันได้ (อาจตั้งค่า SMTP ผิด)'];
    }
}

/**
 * จัดการการยืนยันอีเมลด้วย OTP
 */
function handleVerifyEmail($db) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $otp = filter_input(INPUT_POST, 'otp', FILTER_SANITIZE_STRING);

    if (empty($email) || empty($otp)) {
        return ['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ'];
    }

    $stmt = $db->prepare("
        SELECT t.token_id, t.user_id 
        FROM email_tokens t
        JOIN users u ON t.user_id = u.user_id
        WHERE u.email = ? AND t.token = ? AND t.type = 'verify_email' AND t.expires_at > NOW()
    ");
    $stmt->execute([$email, $otp]);
    $token = $stmt->fetch();

    if (!$token) {
        return ['status' => 'error', 'message' => 'รหัส OTP ไม่ถูกต้อง หรือหมดอายุ'];
    }

    $db->beginTransaction();
    
    $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
    $stmt->execute([$token['user_id']]);
    
    $stmt = $db->prepare("DELETE FROM email_tokens WHERE token_id = ?");
    $stmt->execute([$token['token_id']]);
    
    $db->commit();
    
    return ['status' => 'success', 'message' => 'ยืนยันอีเมลสำเร็จ! กรุณาเข้าสู่ระบบ'];
}

/**
 * จัดการการส่ง OTP ใหม่
 */
function handleResendOTP($db) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    $stmt = $db->prepare("SELECT user_id, firstname, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || $user['is_active']) {
        return ['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ หรือยืนยันตัวตนแล้ว'];
    }
    
    $otp = generateOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $db->beginTransaction();
    $stmt = $db->prepare("DELETE FROM email_tokens WHERE user_id = ? AND type = 'verify_email'");
    $stmt->execute([$user['user_id']]);
    $stmt = $db->prepare("INSERT INTO email_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'verify_email', ?)");
    $stmt->execute([$user['user_id'], $otp, $expiresAt]);
    
    $emailSent = sendOTPEmail($db, $email, $user['firstname'], $otp);
    
    if ($emailSent) {
        $db->commit();
        return ['status' => 'success', 'message' => 'ส่ง OTP ใหม่สำเร็จ'];
    } else {
        $db->rollBack();
        return ['status' => 'error', 'message' => 'ไม่สามารถส่งอีเมลได้'];
    }
}


/**
 * จัดการการเข้าสู่ระบบ
 */
function handleLogin($db) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("
        SELECT u.user_id, u.email, u.password, u.firstname, u.is_active, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ'];
    }
    
    if ($user['is_active'] == 0) {
        return ['status' => 'error', 'message' => 'บัญชีนี้ยังไม่ได้ยืนยันอีเมล (ถ้าเพิ่งสมัคร กรุณาเช็กอีเมล)'];
    }

    // 3. ตรวจสอบรหัสผ่าน (BCRYPT)
    if (password_verify($password, $user['password'])) {
        // 4. สร้าง Session
        session_regenerate_id(true); 
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['firstname'] = $user['firstname'];
        $_SESSION['role'] = $user['role_name'];
        
        return ['status' => 'success', 'message' => 'เข้าสู่ระบบสำเร็จ', 'role' => $user['role_name']];
    } else {
        return ['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง'];
    }
}

/**
 * จัดการการออกจากระบบ
 */
function handleLogout() {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

/**
 * ฟังก์ชันส่งอีเมล OTP ด้วย PHPMailer
 */
function sendOTPEmail($db, $toEmail, $toName, $otp) {
    
    // (FIX) 'require' อยู่ที่นี่ (ในฟังก์ชัน)
    require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
    
    // 2. ดึงการตั้งค่า SMTP จาก DB
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%'");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // 3. ตั้งค่า Server
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'] ?? 'localhost';
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'] ?? 'user';
        $mail->Password   = $settings['smtp_pass'] ?? 'pass';
        $mail->SMTPSecure = $settings['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $settings['smtp_port'] ?? 587;
        
        $mail->setFrom($settings['smtp_user'] ?? 'noreply@learn.system', SITE_NAME);
        $mail->addAddress($toEmail, $toName);
        
        $mail->isHTML(true);
        $mail->CharSet = "UTF-8";
        $mail->Subject = 'รหัส OTP สำหรับยืนยันตัวตน - ' . SITE_NAME;
        $mail->Body    = "สวัสดีครับ คุณ $toName,<br><br>
                          รหัส OTP 6 หลัก สำหรับการยืนยันตัวตนของคุณคือ: <br><br>
                          <h1 style='font-size: 2.5em; letter-spacing: 0.1em; color: #004D40; margin: 10px 0;'>$otp</h1>
                          <br>
                          รหัสนี้จะหมดอายุใน 10 นาที<br><br>
                          หากคุณไม่ได้ร้องขอ โปรดเพิกเฉยอีเมลนี้<br><br>
                          ขอแสดงความนับถือ,<br>
                          ทีมงาน " . SITE_NAME;
        
        $mail->AltBody = "รหัส OTP 6 หลักของคุณคือ: $otp (หมดอายุใน 10 นาที)";
        
        return $mail->send();
        
    } catch (Exception $e) {
        return false;
    }
}
?>