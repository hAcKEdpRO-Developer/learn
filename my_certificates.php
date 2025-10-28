<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 17:50 น. จำนวน 60+ บรรทัด
หน้าแสดงรายการเกียรติบัตรของฉัน
*/

$page_title = "เกียรติบัตรของฉัน";
// --- 1. เรียกไฟล์ที่จำเป็น ---
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // ตรวจสอบการล็อกอิน
require_once __DIR__ . '/includes/db.php'; // เชื่อมต่อ DB

// --- 2. ดึงข้อมูลเกียรติบัตรของผู้ใช้ ---
$user_id = $_SESSION['user_id'];
$certificates = [];
$error_message = null;

try {
    // ดึงข้อมูลเกียรติบัตร พร้อมชื่อคอร์ส
    $stmt = $db->prepare("
        SELECT cert.certificate_id, cert.code, cert.issued_at, cert.pdf_path,
               c.title as course_title, c.course_id
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.course_id
        WHERE cert.user_id = ? AND cert.is_revoked = 0 -- ดึงเฉพาะที่ไม่ถูกยกเลิก
        ORDER BY cert.issued_at DESC
    ");
    $stmt->execute([$user_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// --- 3. เรียก Header ---
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-theme-primary mb-6">เกียรติบัตรของฉัน</h1>

<?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">เกิดข้อผิดพลาด</p>
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow-md">
    <?php if (empty($certificates)): ?>
        <p class="text-gray-500 text-center">คุณยังไม่มีเกียรติบัตร</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">หลักสูตร</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัสเกียรติบัตร</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่ออก</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ดาวน์โหลด</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($certificates as $cert): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="course.php?id=<?php echo $cert['course_id']; ?>" class="text-theme-secondary hover:underline">
                                    <?php echo htmlspecialchars($cert['course_title']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($cert['code']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($cert['issued_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                <a href="generate_certificate.php?course_id=<?php echo $cert['course_id']; ?>&format=pdf" target="_blank" title="ดาวน์โหลด PDF"
                                   class="text-red-600 hover:text-red-900">
                                   <i class="fas fa-file-pdf fa-lg"></i>
                                </a>
                                <?php // ปุ่มรูปภาพ (ยังไม่ทำ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// ไม่มี JS เฉพาะหน้า
$page_scripts = '';
require_once __DIR__ . '/includes/footer.php';
?>