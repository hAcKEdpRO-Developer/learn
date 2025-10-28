<?php
/* Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 15:32:35
จำนวน 27 บรรทัด
Edit by 27 ตุลาคม 2568 21:00 น. (ปรับปรุงให้ใช้ Admin Header/Footer)
Edit by 27 ตุลาคม 2568 22:10 น. (เปิดใช้งาน Link จัดการผู้ใช้)
Edit by 27 ตุลาคม 2568 23:00 น. (เพิ่มสถิติ Dashboard)
*/

$page_title = "Admin Dashboard";
require_once __DIR__ . '/admin_header.php'; // เรียกใช้ Admin Header

// --- (เพิ่ม) Logic ดึงสถิติ ---
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'total_courses' => 0,
    'total_enrollments' => 0,
    'enrollments_by_year' => [],
    'users_by_gender' => [] // (หมายเหตุ: ฐานข้อมูล users ไม่มีคอลัมน์ gender, position, amphoe, network)
];

try {
    // 1. ผู้ใช้ทั้งหมด
    $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    // 2. ผู้เรียนทั้งหมด (role_id = 4)
    $stats['total_students'] = $db->query("SELECT COUNT(*) FROM users WHERE role_id = 4")->fetchColumn();
    // 3. คอร์สทั้งหมด
    $stats['total_courses'] = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    // 4. การลงทะเบียนทั้งหมด
    $stats['total_enrollments'] = $db->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    
    // 5. สถิติแยกตามปีการศึกษา (จากคอร์ส)
    $stmt_year = $db->query("
        SELECT c.academic_year, COUNT(e.enrollment_id) as enroll_count
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        GROUP BY c.academic_year
        ORDER BY c.academic_year DESC
    ");
    $stats['enrollments_by_year'] = $stmt_year->fetchAll();
    
    // 6. สถิติแยกตามเพศ (จาก users)
    // (*** หมายเหตุ: คอลัมน์ gender, position ฯลฯ ไม่มีใน SQL ที่คุณให้มา (learn_db (2).sql) ***)
    // (ผมจะเพิ่ม Query จำลองไว้ ถ้าคุณเพิ่มคอลัมน์นี้ทีหลัง)
    /*
    $stmt_gender = $db->query("
        SELECT gender, COUNT(*) as user_count
        FROM users
        WHERE role_id = 4 
        GROUP BY gender
    ");
    $stats['users_by_gender'] = $stmt_gender->fetchAll();
    */
    
} catch (PDOException $e) {
     echo "<p class='text-red-500'>Error loading stats: " . $e->getMessage() . "</p>";
}
// --- จบ Logic สถิติ ---

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6">Admin Dashboard</h1>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <i class="fas fa-users fa-3x text-blue-500 mb-2"></i>
        <p class="text-3xl font-bold"><?php echo $stats['total_users']; ?></p>
        <p class="text-gray-500">ผู้ใช้งานทั้งหมด</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
         <i class="fas fa-user-graduate fa-3x text-green-500 mb-2"></i>
        <p class="text-3xl font-bold"><?php echo $stats['total_students']; ?></p>
        <p class="text-gray-500">ผู้เรียน (Student)</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
         <i class="fas fa-book fa-3x text-indigo-500 mb-2"></i>
        <p class="text-3xl font-bold"><?php echo $stats['total_courses']; ?></p>
        <p class="text-gray-500">หลักสูตรทั้งหมด</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
         <i class="fas fa-clipboard-check fa-3x text-yellow-500 mb-2"></i>
        <p class="text-3xl font-bold"><?php echo $stats['total_enrollments']; ?></p>
        <p class="text-gray-500">การลงทะเบียนทั้งหมด</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">การลงทะเบียน แยกตามปีการศึกษา</h2>
        <ul class="space-y-2">
            <?php if (empty($stats['enrollments_by_year'])): ?>
                <li class="text-gray-500">ยังไม่มีข้อมูลการลงทะเบียน</li>
            <?php else: ?>
                <?php foreach ($stats['enrollments_by_year'] as $row): ?>
                    <li class="flex justify-between">
                        <span>ปี <?php echo htmlspecialchars($row['academic_year']); ?>:</span>
                        <span class="font-bold"><?php echo $row['enroll_count']; ?> คน</span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
         <h2 class="text-xl font-semibold mb-4">สถิติอื่นๆ (เพศ, ตำแหน่ง)</h2>
         <p class="text-gray-500">(ยังไม่สามารถแสดงผลได้ เนื่องจากไม่มีคอลัมน์ gender, position, ฯลฯ ในตาราง users)</p>
         </div>
</div>


<h2 class="text-2xl font-bold mb-4 mt-12">เมนูการจัดการ</h2>
<div class="grid md:grid-cols-3 gap-4">
    
    <a href="courses.php" class="p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
        <i class="fas fa-book fa-2x text-blue-500 mb-2"></i>
        <h2 class="text-xl font-semibold text-theme-admin">จัดการหลักสูตร</h2>
        <p class="text-gray-500 text-sm">เพิ่ม/แก้ไขคอร์ส, เปิด/ปิด เกียรติบัตร, ตั้งค่าวันที่</p>
    </a>
    
    <a href="users.php" class="p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
        <i class="fas fa-users fa-2x text-green-500 mb-2"></i>
        <h2 class="text-xl font-semibold text-theme-admin">จัดการผู้ใช้งาน</h2>
        <p class="text-gray-500 text-sm">เพิ่ม/ลบ/แก้ไข, Reset รหัสผ่าน</p>
    </a>
    
    <a href="#" class="p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow opacity-50 cursor-not-allowed">
        <i class="fas fa-certificate fa-2x text-purple-500 mb-2"></i>
        <h2 class="text-xl font-semibold text-theme-admin">จัดการเทมเพลตเกียรติบัตร</h2>
        <p class="text-gray-500 text-sm">(ยังไม่เปิดใช้งาน)</p>
    </a>
    
</div>

<?php 
require_once __DIR__ . '/admin_footer.php'; // เรียกใช้ Admin Footer
?>