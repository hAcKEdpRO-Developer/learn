<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 22:10 น.
หน้าจัดการผู้ใช้งาน (Admin Panel)
Edit by 27 ตุลาคม 2568 22:30 น. (เพิ่มปุ่ม Add, Search, Filter, Actions)
*/

$page_title = "จัดการผู้ใช้งาน";
require_once __DIR__ . '/admin_header.php'; // เรียก Header (บังคับ Admin)

// --- (เพิ่ม) Logic การค้นหาและกรอง ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role = isset($_GET['role']) ? (int)$_GET['role'] : 0;

// ดึงข้อมูลผู้ใช้ทั้งหมด พร้อม Role
$users = [];
$all_roles = []; // สำหรับ Dropdown Filter

try {
    // ดึง Roles ทั้งหมดก่อน
    $stmt_roles = $db->query("SELECT * FROM roles ORDER BY role_id ASC");
    $all_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง Query หลัก
    $sql = "SELECT u.user_id, u.email, u.prefix, u.firstname, u.lastname, u.is_active, r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id";
    
    $where_clauses = [];
    $params = [];

    // 1. กรองด้วย Role
    if ($filter_role > 0) {
        $where_clauses[] = "u.role_id = ?";
        $params[] = $filter_role;
    }
    
    // 2. กรองด้วย Search
    if (!empty($search_term)) {
        $where_clauses[] = "(u.email LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)";
        $search_like = '%' . $search_term . '%';
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }
    
    if (count($where_clauses) > 0) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY u.user_id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p class='text-red-500'>Error: " . $e->getMessage() . "</p>";
}

?>

<h1 class="text-3xl font-bold text-theme-admin mb-6">จัดการผู้ใช้งาน</h1>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" action="users.php" class="flex flex-col md:flex-row gap-4 items-center">
        
        <div class="flex-grow w-full md:w-auto">
            <label for="search" class="sr-only">ค้นหา</label>
            <input type="text" name="search" id="search" placeholder="ค้นหาด้วย อีเมล, ชื่อ, นามสกุล..." 
                   value="<?php echo htmlspecialchars($search_term); ?>"
                   class="w-full border-gray-300 rounded-md shadow-sm">
        </div>
        
        <div class="flex-grow w-full md:w-auto">
            <label for="role" class="sr-only">กรองตามสิทธิ์</label>
            <select name="role" id="role" class="w-full border-gray-300 rounded-md shadow-sm">
                <option value="0">--- กรองตามสิทธิ์ ---</option>
                <?php foreach ($all_roles as $role): ?>
                    <option value="<?php echo $role['role_id']; ?>" <?php echo ($filter_role == $role['role_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="w-full md:w-auto px-4 py-2 bg-blue-600 text-white rounded-md shadow-sm hover:bg-blue-700">
            <i class="fas fa-search"></i> ค้นหา
        </button>
        
        <a href="user_add.php" class="w-full md:w-auto px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700 text-center">
            <i class="fas fa-plus"></i> เพิ่มผู้ใช้งานใหม่
        </a>
    </form>
</div>


<div class="bg-white p-6 rounded-lg shadow-md">
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อ - นามสกุล</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สิทธิ์ (Role)</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลผู้ใช้งาน (ตามเงื่อนไขที่ค้นหา)</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['user_id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($user['prefix'] . $user['firstname'] . ' ' . $user['lastname']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <?php if ($user['is_active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <a href="user_edit.php?user_id=<?php echo $user['user_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="text-yellow-600 hover:text-yellow-900 mr-3 reset-pass-btn" 
                                        data-user-id="<?php echo $user['user_id']; ?>" 
                                        data-user-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                        title="Reset รหัสผ่าน">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-900 delete-user-btn" 
                                        data-user-id="<?php echo $user['user_id']; ?>" 
                                        data-user-name="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>"
                                        title="ลบผู้ใช้งาน">
                                    <i class="fas fa-trash"></i>
                                </button>
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