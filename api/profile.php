<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:05 น. จำนวน 110 บรรทัด
API Endpoint สำหรับจัดการโปรไฟล์ (อัปเดตข้อมูล และ รับ Base64)
*/

require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'การร้องขอไม่ถูกต้อง'];
$user_id = $_SESSION['user_id'];

// --- Logic การล็อกโปรไฟล์ (เหมือนหน้า profile.php) ---
$is_profile_locked = false;
try {
	$stmt_lock = $db->prepare("SELECT MIN(c.learn_start) FROM enrollments e JOIN courses c ON e.course_id = c.course_id WHERE e.user_id = ? AND c.learn_start IS NOT NULL");
	$stmt_lock->execute([$user_id]);
	$earliest_start = $stmt_lock->fetchColumn();
	
	if ($earliest_start) {
		$now = new DateTime("now", new DateTimeZone("Asia/Bangkok"));
		$learn_start_date = new DateTime($earliest_start, new DateTimeZone("Asia/Bangkok"));
		if ($now >= $learn_start_date) {
			$is_profile_locked = true;
		}
	}
} catch (PDOException $e) {}

// Admin/Staff ไม่ถูกล็อก
if (hasRole('admin') || hasRole('staff')) {
	$is_profile_locked = false;
}


// --- Router ---
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
	if ($action === 'update_text') {
		if ($is_profile_locked) {
			throw new Exception('โปรไฟล์ถูกล็อก ไม่สามารถแก้ไขข้อมูลได้');
		}
		$response = handleUpdateText($db, $user_id);
	} 
	elseif ($action === 'upload_base64_croppie') {
		// การอัปโหลดรูปภาพ อาจจะยังอนุญาต แม้โปรไฟล์จะล็อก (ขึ้นอยู่กับนโยบาย)
		// ในที่นี้ เราจะอนุญาตให้อัปเดตรูปได้
		$response = handleUploadBase64($db, $user_id);
	}
} catch (Exception $e) {
	$response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;

// --- Logic Functions ---

/**
 * อัปเดตข้อมูล Text (ชื่อ, นามสกุล)
 */
function handleUpdateText($db, $user_id) {
	$prefix = filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_STRING);
	$firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
	$lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
	
	if (empty($firstname) || empty($lastname)) {
		return ['status' => 'error', 'message' => 'กรุณากรอกชื่อและนามสกุล'];
	}

	$stmt = $db->prepare("UPDATE users SET prefix = ?, firstname = ?, lastname = ? WHERE user_id = ?");
	$stmt->execute([$prefix, $firstname, $lastname, $user_id]);
	
	// อัปเดต Session ด้วย
	$_SESSION['firstname'] = $firstname;
	
	return ['status' => 'success', 'message' => 'บันทึกข้อมูลสำเร็จ'];
}

/**
 * (โค้ดตัวอย่างข้อ 7/12)
 * รับ Base64, ถอดรหัส, และบันทึกเป็นไฟล์
 */
function handleUploadBase64($db, $user_id) {
	$base64Image = $_POST['image_base64'] ?? '';
	if (empty($base64Image)) {
		return ['status' => 'error', 'message' => 'ไม่พบข้อมูลรูปภาพ'];
	}

	// 1. ตรวจสอบและถอดรหัส Base64
	// (รูปแบบ: data:image/png;base64,iVBORw0KGgo...)
	if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
		// $type[1] คือนามสกุล (เช่น 'png' หรือ 'jpeg')
		$data = substr($base64Image, strpos($base64Image, ',') + 1);
		$type = strtolower($type[1]); // png, jpeg, gif

		if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
			return ['status' => 'error', 'message' => 'รูปแบบไฟล์ไม่ถูกต้อง'];
		}

		$data = base64_decode($data); // ถอดรหัส

		if ($data === false) {
			return ['status' => 'error', 'message' => 'ข้อมูล Base64 ไม่ถูกต้อง'];
		}
	} else {
		return ['status' => 'error', 'message' => 'รูปแบบ Base64 ไม่ถูกต้อง'];
	}

	// 2. สร้างชื่อไฟล์และ Path
	$upload_dir = ROOT_PATH . '/uploads/profile/';
	if (!is_dir($upload_dir)) {
		mkdir($upload_dir, 0755, true); // (ต้องตั้งค่า chmod Writable ด้วย)
	}
	
	// สร้างชื่อไฟล์ Unique (ตามข้อกำหนด)
	$filename = 'profile_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $type;
	$filepath = $upload_dir . $filename;

	// 3. บันทึกไฟล์
	if (file_put_contents($filepath, $data)) {
		
		// (ควรเพิ่ม Logic ลบรูปเก่าที่นี่)
		
		// 4. อัปเดตฐานข้อมูล
		$stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
		$stmt->execute([$filename, $user_id]);
		
		return ['status' => 'success', 'message' => 'อัปโหลดรูปภาพสำเร็จ'];
	} else {
		return ['status' => 'error', 'message' => 'ไม่สามารถบันทึกไฟล์ลงเซิร์ฟเวอร์ได้ (กรุณาตรวจสอบสิทธิ์ chmod)'];
	}
}
?>