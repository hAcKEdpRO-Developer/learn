<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:47 น. จำนวน 75 บรรทัด
ไฟล์รวมฟังก์ชันช่วยเหลือ (Helper Functions)
*/

// เรียกใช้ config ก่อนเสมอ (ถ้ายังไม่ถูกเรียก)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// --- 1. การจัดการ Session (ปลอดภัย) ---
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // ตั้งค่า Cookie ให้ปลอดภัย
        session_set_cookie_params([
            'lifetime' => 86400, // 1 วัน
            'path' => '/learn/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), // True ถ้าเป็น HTTPS
            'httponly' => true, // ป้องกัน XSS
            'samesite' => 'Lax' // ป้องกัน CSRF
        ]);
        session_name(SESSION_NAME);
        session_start();
    }
    
    // ป้องกัน Session Fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

// --- 2. การสร้าง OTP 6 หลัก ---
/**
 * สร้างรหัส OTP แบบตัวเลข 6 หลัก
 * @return string
 */
function generateOTP() {
    try {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        // Fallback
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

// --- 3. การส่งอีเมล (PHPMailer) ---
// เราจะเรียกใช้ฟังก์ชันนี้ใน api/auth.php
// (ต้องติดตั้ง PHPMailer ใน /lib/PHPMailer/ ก่อน)

// --- 4. การตรวจสอบสิทธิ์ ---
/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * บังคับให้ไปหน้า login ถ้ายังไม่ล็อกอิน
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

/**
 * ตรวจสอบสิทธิ์ (Role)
 * @param string $role (เช่น 'admin', 'instructor')
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn() || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

/**
 * บังคับสิทธิ์ Admin
 */
function requireAdmin() {
    if (!hasRole('admin')) {
        // อาจจะส่งไปหน้า "ไม่มีสิทธิ์" หรือหน้า dashboard
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
    }
}

// เริ่ม Session อัตโนมัติเมื่อเรียกใช้ functions.php
startSecureSession();