<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 21:00 น.
หน้าจัดการหลักสูตร (Admin Panel)
Edit by 27 ตุลาคม 2568 23:00 น. (เพิ่ม Search, Filter, ปุ่ม Add)
*/

$page_title = "จัดการหลักสูตร";
require_once __DIR__ . '/admin_header.php'; // เรียก Header (บังคับ Admin)

// --- (เพิ่ม) Logic การค้นหาและกรอง ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// ดึงข้อมูลคอร์สทั้งหมด
$courses = [];
$all_years = []; // สำหรับ Filter

try {
    // 1. ดึงปีการศึกษาทั้งหมดก่อน
    $stmt_years = $db->query("SELECT DISTINCT academic_year FROM courses ORDER BY academic_year DESC");
    $all_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

    // 2. สร้าง Query หลัก
    $sql = "SELECT course_id, title, academic_year, status, cert_enabled, enroll_start, enroll_end, learn_start
            FROM courses";
    
    $where_clauses = [];
    $params = [];

    // 2.1 กรองด้วย ปี
    if ($filter_year > 0) {
        $where_clauses[] = "academic_year = ?";
        $params[] = $filter_year;
    }
    
    // 2.2 กรองด้วย Search
    if (!empty($search_term)) {
        $where_clauses[] = "(title LIKE ? OR short_code LIKE ?)";
        $search_like = '%' . $search_term . '%';
        $params[] = $search_like;
        $params[] = $search_like;
    }
    
    if (count($where_clauses) > 0) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY academic_year DESC, title ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p class='text-red-500'>Error: " . $e->getMessage() . "</p>";
}

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6">จัดการหลักสูตร</h1>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" action="courses.php" class="flex flex-col md:flex-row gap-4 items-center">
        
        <div class="flex-grow w-full md:w-auto">
            <label for="search" class="sr-only">ค้นหา</label>
            <input type="text" name="search" id="search" placeholder="ค้นหาด้วย ชื่อหลักสูตร, รหัสย่อ..." 
                   value="<?php echo htmlspecialchars($search_term); ?>"
                   class="w-full border-gray-300 rounded-md shadow-sm">
        </div>
        
        <div class="flex-grow w-full md:w-auto">
            <label for="year" class="sr-only">กรองตามปีการศึกษา</label>
            <select name="year" id="year" class="w-full border-gray-300 rounded-md shadow-sm">
                <option value="0">--- กรองตามปีการศึกษา ---</option>
                <?php foreach ($all_years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="w-full md:w-auto px-4 py-2 bg-blue-600 text-white rounded-md shadow-sm hover:bg-blue-700">
            <i class="fas fa-search"></i> ค้นหา
        </button>
        
        <a href="course_edit.php" class="w-full md:w-auto px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 text-center">
            <i class="fas fa-plus"></i> เพิ่มหลักสูตรใหม่
        </a>
    </form>
</div>


<div class="bg-white p-6 rounded-lg shadow-md">
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อหลักสูตร</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ปี</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะคอร์ส</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะเกียรติบัตร</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลหลักสูตร (ตามเงื่อนไขที่ค้นหา)</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $course['course_id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($course['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($course['academic_year']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($course['status']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center" id="cert-status-<?php echo $course['course_id']; ?>">
                                <?php if ($course['cert_enabled']): ?>
                                    <span class="text-green-600 font-semibold">เปิด</span>
                                <?php else: ?>
                                    <span class="text-red-600 font-semibold">ปิด</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <?php if ($course['cert_enabled']): ?>
                                    <button class="toggle-cert-btn px-3 py-1 text-xs font-medium rounded-full text-white bg-green-500 hover:bg-green-600" 
                                            data-course-id="<?php echo $course['course_id']; ?>" 
                                            data-current-state="1">
                                        <i class="fas fa-check-circle mr-1"></i> เปิดอยู่
                                    </button>
                                <?php else: ?>
                                    <button class="toggle-cert-btn px-3 py-1 text-xs font-medium rounded-full text-white bg-gray-500 hover:bg-gray-600" 
                                            data-course-id="<?php echo $course['course_id']; ?>" 
                                            data-current-state="0">
                                        <i class="fas fa-times-circle mr-1"></i> ปิดอยู่
                                    </button>
                                <?php endif; ?>
                                
                                <a href="course_edit.php?course_id=<?php echo $course['course_id']; ?>" class="text-indigo-600 hover:text-indigo-900 ml-3" title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php 
require_once __DIR__ . '/admin_footer.php'; // เรียกใช้ Admin Footer
?>