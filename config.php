<?php
/* 
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing 
Edit by 2025-10-24 15:32:35 
จำนวน 42 บรรทัด
*/
?>
<?php
// ค่าคงที่ของระบบสำหรับ MAMP PRO และโฮสต์จริง
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding("UTF-8");
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

define('DB_HOST','localhost');
define('DB_NAME','learn_db');
define('DB_USER','root');
define('DB_PASS','123456');
define('APP_BASE','/learn/'); // โฟลเดอร์ราก
define('APP_TITLE','ระบบเรียนออนไลน์ + สอบ + เกียรติบัตร + ส่งงาน');

// โฟลเดอร์เก็บไฟล์
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('PUBLIC_UPLOAD_PATH','/learn/uploads/');

// ฟอนต์เริ่มต้น
define('DEFAULT_FONT_FAMILY','Noto Sans Thai');

// การตั้งค่า Rate-limit ง่ายๆ: 5 ครั้งภายใน 30 วินาที ต่อ IP + endpoint
define('RATE_WINDOW',30);
define('RATE_MAX',5);
?>
<?php
/* 
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing 
Edit by 2025-10-24 15:32:35 
จำนวน 42 บรรทัด
*/
?>