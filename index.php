<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:10 น. จำนวน 35 บรรทัด (ฉบับแก้ไข 500 Error)
หน้าหลัก (Landing Page) ของระบบ
*/

// *** (FIX) ***
// ต้องเรียก functions.php (ซึ่งจะเรียก config.php) ขึ้นมาก่อน
// เพื่อให้ระบบรู้จัก SITE_NAME และ isLoggedIn()
require_once __DIR__ . '/includes/functions.php';

// ตอนนี้ $page_title จะรู้จัก SITE_NAME แล้ว
$page_title = "หน้าแรก - " . SITE_NAME;

// และ header.php จะทำงานต่อได้
require_once __DIR__ . '/includes/header.php'; 
?>

<div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md text-center">
    <h1 class="text-4xl font-bold text-theme-primary mb-4">
        ยินดีต้อนรับสู่ <?php echo SITE_NAME; ?>
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        ระบบจัดการการเรียนรู้ออนไลน์ ที่ครบวงจร
    </p>

    <?php if (isLoggedIn()): ?> 
        <p class="text-lg mb-4">
            สวัสดีคุณ <?php echo htmlspecialchars($_SESSION['firstname']); ?>, คุณเข้าสู่ระบบแล้ว
        </p>
        <a href="dashboard.php" class="inline-block btn-primary font-semibold py-3 px-6 rounded-md shadow-sm text-lg">
            ไปที่แดชบอร์ด
        </a>
    <?php else: ?>
        <p class="text-lg mb-4">
            กรุณาเข้าสู่ระบบ หรือ สมัครสมาชิก เพื่อเริ่มต้นการเรียนรู้
        </p>
        <a href="login.php" class="inline-block btn-primary font-semibold py-3 px-6 rounded-md shadow-sm text-lg">
            <svg class="inline-block w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
            เข้าสู่ระบบ
        </a>
        <a href="register.php" class="inline-block ml-4 bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-md shadow-sm text-lg">
            สมัครสมาชิก
        </a>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>