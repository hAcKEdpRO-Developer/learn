<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 23:00 น.
หน้าเพิ่ม/แก้ไขหลักสูตร (Admin Panel)
*/

$page_title = "เพิ่ม/แก้ไขหลักสูตร";
require_once __DIR__ . '/admin_header.php'; // เรียก Header (บังคับ Admin)

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$is_editing = $course_id > 0;
$course_data = null;
$error_message = null;

// Enum Values for Status
$course_statuses = ['DRAFT','ENROLL_OPEN','ENROLL_CLOSED_WAIT_LEARN','LEARN_OPEN','LEARN_CLOSED_WAIT_EXAM','EXAM_OPEN','EXAM_CLOSED_WAIT_CERT','CERT_OPEN','ARCHIVED'];

// Helper function to format date for input
function format_date_for_input($datetime) {
    if (empty($datetime)) return '';
    return date('Y-m-d\TH:i', strtotime($datetime));
}

if ($is_editing) {
    // --- โหมดแก้ไข: ดึงข้อมูลเดิม ---
    try {
        $stmt_course = $db->prepare("SELECT * FROM courses WHERE course_id = ?");
        $stmt_course->execute([$course_id]);
        $course_data = $stmt_course->fetch(PDO::FETCH_ASSOC);

        if (!$course_data) {
            throw new Exception("ไม่พบข้อมูลหลักสูตร ID: $course_id");
        }
         $page_title = "แก้ไขหลักสูตร: " . htmlspecialchars($course_data['title']);
    } catch (Throwable $e) {
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else {
    // --- โหมดเพิ่มใหม่: ใช้ค่าว่าง ---
    $page_title = "เพิ่มหลักสูตรใหม่";
    $course_data = [
        'course_id' => 0, 'title' => '', 'short_code' => '', 'description' => '',
        'academic_year' => date('Y') + 543, // ปี พ.ศ. ปัจจุบัน
        'status' => 'DRAFT',
        'enroll_start' => null, 'enroll_end' => null,
        'learn_start' => null, 'learn_end' => null,
        'exam_start' => null, 'exam_end' => null,
        'cert_start' => null, 'cert_end' => null,
    ];
}

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6"><?php echo $page_title; ?></h1>

<?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">เกิดข้อผิดพลาด</p>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        <a href="courses.php" class="text-theme-secondary hover:underline">&laquo; กลับไปหน้ารายการหลักสูตร</a>
    </div>
<?php elseif ($course_data): ?>
    <form id="course-edit-form" class="bg-white p-6 rounded-lg shadow-md max-w-4xl mx-auto">
        
        <?php if ($is_editing): ?>
            <input type="hidden" name="course_id" value="<?php echo $course_data['course_id']; ?>">
        <?php endif; ?>
        
        <h2 class="text-xl font-semibold mb-4">ข้อมูลหลักสูตร</h2>
        
        <div class="mb-4">
            <label for="title" class="block text-sm font-medium text-gray-700">ชื่อหลักสูตร (เต็ม)</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($course_data['title']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label for="short_code" class="block text-sm font-medium text-gray-700">รหัสย่อ (สำหรับ Exam Code)</label>
                <input type="text" id="short_code" name="short_code" value="<?php echo htmlspecialchars($course_data['short_code']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div>
                <label for="academic_year" class="block text-sm font-medium text-gray-700">ปีการศึกษา (พ.ศ.)</label>
                <input type="number" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($course_data['academic_year']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
             <div>
                <label for="status" class="block text-sm font-medium text-gray-700">สถานะหลักสูตร</label>
                <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    <?php foreach ($course_statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo ($course_data['status'] == $status) ? 'selected' : ''; ?>>
                            <?php echo $status; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label for="description" class="block text-sm font-medium text-gray-700">คำอธิบายหลักสูตร</label>
            <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><?php echo htmlspecialchars($course_data['description']); ?></textarea>
        </div>
        
        <hr class="my-6">
        <h2 class="text-xl font-semibold mb-4">การตั้งค่าวันที่ (กำหนดการ)</h2>
        <p class="text-sm text-gray-500 mb-4">(เว้นว่างไว้ หากไม่มีกำหนด)</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div>
                <label for="enroll_start" class="block text-sm font-medium text-gray-700">วันที่เปิดลงทะเบียน</label>
                <input type="datetime-local" id="enroll_start" name="enroll_start" value="<?php echo format_date_for_input($course_data['enroll_start']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
             <div>
                <label for="enroll_end" class="block text-sm font-medium text-gray-700">วันที่ปิดลงทะเบียน</label>
                <input type="datetime-local" id="enroll_end" name="enroll_end" value="<?php echo format_date_for_input($course_data['enroll_end']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            
            <div class="border-t pt-4 mt-2">
                <label for="learn_start" class="block text-sm font-medium text-gray-700">วันที่เริ่มเรียน (ใช้ล็อกโปรไฟล์)</label>
                <input type="datetime-local" id="learn_start" name="learn_start" value="<?php echo format_date_for_input($course_data['learn_start']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
             <div class="border-t pt-4 mt-2">
                <label for="learn_end" class="block text-sm font-medium text-gray-700">วันที่ปิดเรียน</label>
                <input type="datetime-local" id="learn_end" name="learn_end" value="<?php echo format_date_for_input($course_data['learn_end']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            
            <div class="border-t pt-4 mt-2">
                <label for="exam_start" class="block text-sm font-medium text-gray-700">วันที่เปิดสอบ</label>
                <input type="datetime-local" id="exam_start" name="exam_start" value="<?php echo format_date_for_input($course_data['exam_start']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
             <div class="border-t pt-4 mt-2">
                <label for="exam_end" class="block text-sm font-medium text-gray-700">วันที่ปิดสอบ</label>
                <input type="datetime-local" id="exam_end" name="exam_end" value="<?php echo format_date_for_input($course_data['exam_end']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            
            <div class="border-t pt-4 mt-2">
                <label for="cert_start" class="block text-sm font-medium text-gray-700">วันที่เปิดพิมพ์เกียรติบัตร</label>
                <input type="datetime-local" id="cert_start" name="cert_start" value="<?php echo format_date_for_input($course_data['cert_start']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
             <div class="border-t pt-4 mt-2">
                <label for="cert_end" class="block text-sm font-medium text-gray-700">วันที่ปิดพิมพ์เกียรติบัตร</label>
                <input type="datetime-local" id="cert_end" name="cert_end" value="<?php echo format_date_for_input($course_data['cert_end']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
        </div>
        
        <div class="mt-8 flex justify-between items-center">
            <a href="courses.php" class="text-sm text-gray-600 hover:underline">&laquo; กลับ</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-md shadow-sm">
                <i class="fas fa-save mr-2"></i> 
                <?php echo $is_editing ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างหลักสูตร'; ?>
            </button>
        </div>
        
    </form>
<?php endif; ?>

<?php
// JS สำหรับ Submit Form นี้
$page_scripts = <<<JS
<script>
// (Logic validate form นี้ถูกย้ายไปรวมใน admin.js แล้ว)
</script>
JS;
?>

<?php 
require_once __DIR__ . '/admin_footer.php'; // เรียกใช้ Admin Footer
?>