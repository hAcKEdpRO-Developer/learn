<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:45 น. จำนวน 33 บรรทัด
ไฟล์ตั้งค่าหลักของระบบ (Core Configuration)
*/

// --- 1. การตั้งค่าโซนเวลา (Timezone) ---
// ต้องตั้งค่านี้เพื่อให้เวลาใน PHP ตรงกับ Asia/Bangkok
date_default_timezone_set('Asia/Bangkok');

// --- 2. การตั้งค่าการเชื่อมต่อฐานข้อมูล (MAMP PRO) ---
define('DB_HOST', 'localhost');
define('DB_PORT', '3306'); // Port มาตรฐานของ MAMP PRO MySQL
define('DB_NAME', 'learn_db');
define('DB_USER', 'root');
define('DB_PASS', '123456'); // รหัสผ่านตามที่คุณกำหนด
define('DB_CHARSET', 'utf8mb4');

// --- 3. การตั้งค่า Path และ URL ---
// ตรวจสอบว่าเป็น HTTPS หรือไม่ (สำหรับ MAMP PRO มักจะเป็น http)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// URL หลักของระบบ (รวม Port 80 ตามที่คุณกำหนด)
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . '/learn');
// Path จริงบน Server
define('ROOT_PATH', dirname(__DIR__)); // ชี้ไปที่โฟลเดอร์ /learn

// --- 4. การตั้งค่า Session ---
define('SESSION_NAME', 'learn_session_id');

// --- 5. การตั้งค่าทั่วไป ---
define('SITE_NAME', 'ระบบเรียนออนไลน์');
define('DEBUG_MODE', true); // ตั้งเป็น false เมื่อขึ้น Production

// --- 6. การแสดงผล Error ---
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    // ควรตั้งค่า log_errors = 1 และ error_log = /path/to/logs/error.log ใน php.ini จริง
}