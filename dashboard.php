<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:02 น. จำนวน 50 บรรทัด
หน้าแดชบอร์ดของผู้เรียน (คอร์สของฉัน)
Edit by 27 ตุลาคม 2568 22:50 น. (เพิ่มการแสดงคอร์สที่ยังไม่ลงทะเบียน)
*/

$page_title = "แดชบอร์ด";
require_once __DIR__ . '/includes/functions.php'; // (เรียก header.php ภายใน)
requireLogin(); // บังคับล็อกอิน
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// ดึงคอร์สที่ผู้เรียนลงทะเบียน
$user_id = $_SESSION['user_id'];
$enrolled_courses = [];
$available_courses = [];
$all_years = []; // สำหรับ Filter

try {
    // 1. ดึงคอร์สที่ "ลงทะเบียนแล้ว"
    $stmt_enrolled = $db->prepare("
        SELECT c.course_id, c.title, c.description, c.cover_image, c.status, e.status as enrollment_status, c.academic_year
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        WHERE e.user_id = ?
        ORDER BY c.academic_year DESC, c.title ASC
    ");
    $stmt_enrolled->execute([$user_id]);
    $enrolled_courses = $stmt_enrolled->fetchAll();
    
    // 2. ดึง ID คอร์สที่ลงทะเบียนแล้วทั้งหมด
    $enrolled_course_ids = array_column($enrolled_courses, 'course_id');
    
    // 3. สร้าง Placeholder สำหรับ Query (ถ้ามี)
    $params = [];
    $placeholders = '';
    if (!empty($enrolled_course_ids)) {
         $placeholders = ' AND c.course_id NOT IN (' . implode(',', array_fill(0, count($enrolled_course_ids), '?')) . ') ';
         $params = $enrolled_course_ids;
    }
    
    // 4. ดึงคอร์สที่ "ยังไม่ลงทะเบียน" และ "เปิดให้ลงทะเบียน"
    $sql_available = "
        SELECT c.course_id, c.title, c.description, c.cover_image, c.status, c.academic_year, c.enroll_end
        FROM courses c
        WHERE c.status = 'ENROLL_OPEN' 
          AND (c.enroll_end IS NULL OR c.enroll_end >= CURDATE())
          $placeholders
        ORDER BY c.academic_year DESC, c.title ASC
    ";
    
    $stmt_available = $db->prepare($sql_available);
    $stmt_available->execute($params);
    $available_courses = $stmt_available->fetchAll();
    
    // 5. ดึงปีการศึกษาทั้งหมดสำหรับ Filter
    $all_years = array_unique(array_merge(array_column($enrolled_courses, 'academic_year'), array_column($available_courses, 'academic_year')));
    rsort($all_years); // เรียงจากมากไปน้อย

} catch (PDOException $e) {
    echo "<p class='text-red-500'>Error: " . $e->getMessage() . "</p>";
}

?>

<h1 class="text-3xl font-bold text-theme-primary mb-6">แดชบอร์ด</h1>

<div class="mb-4">
    <label for="filter_year" class="text-sm font-medium">กรองตามปีการศึกษา:</label>
    <select id="filter_year" class="border rounded-md p-1">
        <option value="all">ทั้งหมด</option>
        <?php foreach ($all_years as $year): ?>
            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<section id="my-courses" class="mb-12">
    <h2 class="text-2xl font-semibold text-theme-secondary mb-4">คอร์สของฉัน (ที่ลงทะเบียนแล้ว)</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($enrolled_courses)): ?>
            <p class="text-gray-600 col-span-full">คุณยังไม่ได้ลงทะเบียนเรียนในหลักสูตรใดเลย</p>
        <?php else: ?>
            <?php foreach ($enrolled_courses as $course): ?>
                <div class="bg-white p-6 rounded-lg shadow-md course-card flex flex-col justify-between" data-year="<?php echo $course['academic_year']; ?>">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($course['title']); ?> (<?php echo $course['academic_year']; ?>)</h3>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                    </div>
                    <div class="flex justify-between items-center mt-4">
                        <span class="text-sm font-medium text-gray-500">สถานะ: <?php echo htmlspecialchars($course['enrollment_status']); ?></span>
                        <a href="course.php?id=<?php echo $course['course_id']; ?>" class="btn-primary text-sm font-medium py-2 px-4 rounded-md">
                            เข้าสู่บทเรียน
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section id="available-courses">
    <h2 class="text-2xl font-semibold text-theme-secondary mb-4">หลักสูตรที่เปิดให้อบรม (ยังไม่ลงทะเบียน)</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($available_courses)): ?>
            <p class="text-gray-600 col-span-full">ไม่มีหลักสูตรอื่นที่เปิดให้ลงทะเบียนในขณะนี้</p>
        <?php else: ?>
            <?php foreach ($available_courses as $course): ?>
                <div class="bg-white p-6 rounded-lg shadow-md course-card flex flex-col justify-between" data-year="<?php echo $course['academic_year']; ?>">
                     <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($course['title']); ?> (<?php echo $course['academic_year']; ?>)</h3>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                    </div>
                    <div class="flex justify-between items-center mt-4">
                         <span class="text-sm font-medium text-gray-500">
                            <?php if ($course['enroll_end']): ?>
                                ปิดรับ: <?php echo date('d/m/Y', strtotime($course['enroll_end'])); ?>
                            <?php else: ?>
                                เปิดตลอด
                            <?php endif; ?>
                        </span>
                        <form class="enroll-form" method="POST">
                            <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-4 rounded-md">
                                ลงทะเบียนเรียน
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>


<?php
// (แก้ไข) JS สำหรับกรองปี และ JS สำหรับ Enroll
$page_scripts = '
<script>
$(document).ready(function() {
    // 1. JS สำหรับกรองปี
    $("#filter_year").on("change", function() {
        let selectedYear = $(this).val();
        if (selectedYear === "all") {
            $(".course-card").show();
        } else {
            $(".course-card").hide();
            $(".course-card[data-year=\'" + selectedYear + "\']").show();
        }
    });

    // 2. จัดการการ Enroll
    $(".enroll-form").on("submit", function(e) {
        e.preventDefault();
        const $form = $(this);
        const courseId = $form.find("input[name=\'course_id\']").val();
        
        Swal.fire({
            title: "ยืนยันการลงทะเบียน?",
            text: "คุณต้องการลงทะเบียนเรียนในหลักสูตรนี้ใช่หรือไม่?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "ใช่, ลงทะเบียน",
            cancelButtonText: "ยกเลิก"
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading("กำลังลงทะเบียน...");
                $.ajax({
                    url: "api/course.php?action=enroll_course", // เรียก API ใหม่
                    type: "POST",
                    data: { course_id: courseId },
                    dataType: "json",
                    success: function(response) {
                        hideLoading();
                        if (response.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "ลงทะเบียนสำเร็จ!",
                                text: response.message
                            }).then(() => {
                                window.location.reload(); // Reload หน้าเพื่อย้ายคอร์สไป "คอร์สของฉัน"
                            });
                        } else {
                            showAlert("error", "ผิดพลาด!", response.message);
                        }
                    },
                    error: function(jqXHR) {
                        hideLoading();
                        const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                        showAlert("error", "เกิดข้อผิดพลาด", errorMsg);
                    }
                });
            }
        });
    });
});
</script>
';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>