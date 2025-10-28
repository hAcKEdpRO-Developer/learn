<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 21:00 น.
Header สำหรับระบบหลังบ้าน (Admin Panel)
Edit by 27 ตุลาคม 2568 22:10 น. (เพิ่ม Link โปรไฟล์กลับเข้ามา)
Edit by 27 ตุลาคม 2568 22:30 น. (แก้ไข Path โปรไฟล์ให้ถูกต้อง และเพิ่มเมนู)
Edit by 27 ตุลาคม 2568 22:50 น. (Final Admin Header - แก้ไข Link โปรไฟล์ไปหน้า Admin)
*/

// --- 1. เรียกไฟล์ที่จำเป็น ---
// เรียก functions.php (ซึ่งจะเรียก config.php และ db.php)
require_once __DIR__ . '/../includes/functions.php';

// --- 2. บังคับสิทธิ์ Admin ---
// (ฟังก์ชันนี้อยู่ใน includes/functions.php)
// (แก้ไข) เพิ่มการตรวจสอบ Staff ด้วย
if (!hasRole('admin') && !hasRole('staff')) {
    // ถ้าไม่ใช่ Admin หรือ Staff ให้ออก
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// --- 3. ดึงข้อมูล Site Name (ถ้าต้องการ) ---
$site_name_from_db = SITE_NAME; // ใช้ค่า Default
try {
    // ตรวจสอบว่า $db ถูกสร้างจาก functions.php หรือยัง
    if (!isset($db)) {
        require_once __DIR__ . '/../includes/db.php';
    }
    
    $stmt = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'system_name'");
    $db_site_name = $stmt->fetchColumn();
    if ($db_site_name) {
        $site_name_from_db = $db_site_name;
    }
} catch (PDOException $e) { /* ใช้ค่า Default */ }

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars($site_name_from_db) : htmlspecialchars($site_name_from_db) . " (Admin)"; ?></title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css" rel="stylesheet">
    
    <style>
        body, html {
            font-family: 'Noto Sans Thai', sans-serif; /* (ใช้ Font เริ่มต้นไปก่อน) */
        }
        .bg-theme-admin { background-color: #4A5568; } /* สีเทาเข้มสำหรับ Admin */
        .bg-theme-admin-secondary { background-color: #2D3748; }
        .text-theme-admin { color: #4A5568; }
        
        /* Style สำหรับ has-[:checked] (ต้องมีใน Tailwind V3.1+) */
        label:has(input:checked) {
            /* (ไม่มี style เริ่มต้น) */
        }
        
        /* jQuery Validate Error Style */
        label.error {
            color: #EF4444; /* text-red-500 */
            font-size: 0.875rem; /* text-sm */
            margin-top: 0.25rem; /* mt-1 */
        }
        input.error, select.error {
            border-color: #EF4444; /* border-red-500 */
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
    <nav class="bg-theme-admin text-white shadow-md sticky top-0 z-40">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="text-xl font-bold"><?php echo htmlspecialchars($site_name_from_db); ?> - (ระบบหลังบ้าน)</a>
            <div class="flex items-center space-x-4">
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="hover:text-gray-300">หน้าหลัก Admin</a>
                <a href="<?php echo BASE_URL; ?>/admin/courses.php" class="hover:text-gray-300">หลักสูตร</a>
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="hover:text-gray-300">ผู้ใช้งาน</a>
                <a href="<?php echo BASE_URL; ?>/dashboard.php" target="_blank" class="hover:text-gray-300">(ดูหน้าบ้าน)</a>
                <span class="text-yellow-300">สวัสดี, <?php echo htmlspecialchars($_SESSION['firstname']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
                
                <a href="<?php echo BASE_URL; ?>/admin/profile.php" class="hover:text-gray-300" title="แก้ไขโปรไฟล์">
                    <i class="fas fa-user-circle"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>/api/auth.php?action=logout" class="hover:text-gray-300" title="ออกจากระบบ">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4 mt-4 flex-grow">