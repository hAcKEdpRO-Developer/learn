<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 22:49 น. จำนวน 60 บรรทัด
ส่วนหัวของหน้า (HTML Header) - สำหรับหน้าบ้าน
Edit by 27 ตุลาคม 2568 17:50 น. จำนวน 65+ บรรทัด (เพิ่มเมนู เกียรติบัตร)
*/

// เริ่ม Session และเรียกใช้ฟังก์ชัน (ถ้ายังไม่ถูกเรียก)
if (session_status() === PHP_SESSION_NONE) {
    // require_once __DIR__ . '/config.php'; // config.php ควรถูกเรียกจาก functions.php แล้ว
    require_once __DIR__ . '/functions.php';
}
if (!isset($db)) {
    require_once __DIR__ . '/db.php';
}

// --- ดึงการตั้งค่าฟอนต์จากฐานข้อมูล ---
$font_name = 'Noto Sans Thai';
$font_url = 'https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@100..900&display=swap';
$site_name_from_db = SITE_NAME; // ใช้ค่า Default จาก config ก่อน

try {
    $stmt_settings = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('active_font_name', 'active_font_url', 'system_name')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($settings['active_font_name']) && !empty($settings['active_font_url'])) {
        $font_name = $settings['active_font_name'];
        $font_url = $settings['active_font_url'];
    }
    if (!empty($settings['system_name'])) {
         $site_name_from_db = $settings['system_name'];
    }
} catch (PDOException $e) {
    // ใช้ค่าเริ่มต้นถ้าดึง DB ไม่ได้
    error_log("Error fetching site settings: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars($site_name_from_db) : htmlspecialchars($site_name_from_db); ?></title>

    <link href="<?php echo htmlspecialchars($font_url); ?>" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css" rel="stylesheet">

    <style>
        /* ใช้ฟอนต์ที่ดึงจาก DB เป็นหลัก */
        body, html {
            font-family: '<?php echo htmlspecialchars($font_name); ?>', sans-serif;
            font-size: 16px; /* ขนาดเริ่มต้นสำหรับ Font Sizer */
        }
        /* ธีมสีเขียว Modern (ตัวอย่าง) */
        .bg-theme-primary { background-color: #004D40; } /* เขียวเข้ม */
        .bg-theme-secondary { background-color: #00796B; } /* เขียวกลาง */
        .text-theme-primary { color: #004D40; }
        .text-theme-secondary { color: #00796B; }
        .btn-primary { background-color: #00796B; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; transition: background-color 0.15s ease-in-out; }
        .btn-primary:hover { background-color: #004D40; }
        /* Style สำหรับ has-[:checked] (ต้องมีใน Tailwind V3.1+) */
        label:has(input:checked) {
             /* ตัวอย่าง Style เมื่อ Radio ถูกเลือก */
            /* background-color: #E0F2F7;
            border-color: #76D7C4; */
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
    <nav class="bg-theme-primary text-white shadow-md sticky top-0 z-40">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="<?php echo BASE_URL; ?>/index.php" class="text-xl font-bold"><?php echo htmlspecialchars($site_name_from_db); ?></a>
            <div class="flex items-center space-x-4">
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="hover:text-gray-300">แดชบอร์ด</a>
                    <a href="<?php echo BASE_URL; ?>/my_certificates.php" class="hover:text-gray-300">เกียรติบัตร</a> <?php // *** (เพิ่ม) *** ?>
                    <a href="<?php echo BASE_URL; ?>/profile.php" class="hover:text-gray-300">โปรไฟล์</a>
                    <a href="<?php echo BASE_URL; ?>/api/auth.php?action=logout" class="hover:text-gray-300">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="hover:text-gray-300">เข้าสู่ระบบ</a>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="hover:text-gray-300">สมัครสมาชิก</a>
                <?php endif; ?>

                <div class="flex items-center space-x-1">
                    <button id="font-decrease" title="ลดขนาดตัวอักษร" class="px-2 py-1 bg-theme-secondary rounded hover:bg-opacity-80">-</button>
                    <button id="font-increase" title="เพิ่มขนาดตัวอักษร" class="px-2 py-1 bg-theme-secondary rounded hover:bg-opacity-80">+</button>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4 mt-4 flex-grow">