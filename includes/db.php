<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:46 น. จำนวน 25 บรรทัด
ไฟล์เชื่อมต่อฐานข้อมูลด้วย PDO
*/

// เรียกใช้ไฟล์ config
require_once __DIR__ . '/config.php';

/**
 * ฟังก์ชันสำหรับเชื่อมต่อฐานข้อมูล PDO
 * @return PDO|null
 */
function getDBConnection() {
    static $pdo = null; // ใช้ static เพื่อให้เชื่อมต่อครั้งเดียว

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // โยน Exception เมื่อเกิด Error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // ดึงข้อมูลเป็น Array
            PDO::ATTR_EMULATE_PREPARES   => false,                // ใช้ Prepared Statements จริง
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // ในโหมด DEBUG_MODE จะแสดงข้อผิดพลาด
            if (DEBUG_MODE) {
                die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
            } else {
                // ในโหมด Production ให้แสดงข้อความทั่วไป
                die("ระบบขัดข้อง กรุณาลองใหม่อีกครั้ง");
            }
        }
    }
    
    return $pdo;
}

// เริ่มการเชื่อมต่อทันทีที่ไฟล์นี้ถูกเรียก
$db = getDBConnection();