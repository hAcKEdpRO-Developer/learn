<?php
/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 20:10 น. จำนวน 180+ บรรทัด (เปลี่ยนไปใช้ mPDF)
Edit by 27 ตุลาคม 2568 20:25 น. จำนวน 180+ บรรทัด (ปรับใช้ mPDF จาก Vendor ที่มี)
Edit by 27 ตุลาคม 2568 20:45 น. จำนวน 180+ บรรทัด (เพิ่ม require autoload.php)
Edit by 27 ตุลาคม 2568 20:55 น. จำนวน 250+ บรรทัด (เพิ่ม Logic สร้าง Image ด้วย GD Library)
*/

// --- 1. เรียกไฟล์ที่จำเป็น ---
// Autoloader จำเป็นสำหรับ mPDF (แม้ว่าส่วน Image จะไม่ใช้)
require_once __DIR__ . '/vendor/autoload.php'; 

require_once __DIR__ . '/includes/functions.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
// ไม่ต้องเรียก fpdf.php หรือ minipdf.php แล้ว

// --- 2. รับค่าและตรวจสอบ ---
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'pdf'; // pdf หรือ image
$user_id = $_SESSION['user_id'];
$error_message = null;
$certificate_data = null; 

// (แก้ไข) ตรวจสอบ Format ที่รองรับ
if ($course_id === 0 || !in_array($format, ['pdf', 'image'])) {
    header('Content-Type: text/plain; charset=utf-8');
    die("Invalid parameters or unsupported format. Only 'pdf' or 'image' allowed.");
}

// --- 3. ตรวจสอบสิทธิ์ สร้าง/ดึงข้อมูลเกียรติบัตร (ใช้ร่วมกัน) ---
try {
    // 1. ดึงข้อมูลคอร์ส, Template ID, และตรวจสอบว่าเปิดให้พิมพ์
    $stmt_course = $db->prepare("SELECT title, cert_enabled, cert_template_id, short_code FROM courses WHERE course_id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);
    if (!$course) { throw new Exception("Course not found."); }
    if (!$course['cert_enabled']) { throw new Exception("Certificate printing is not enabled for this course."); }
    $course_title = $course['title'];
    $course_short_code = $course['short_code'] ?: 'COURSE';
    $template_id = $course['cert_template_id'] ?? 1;

    // 2. ดึงข้อมูล Template (ต้องการ background image)
    $stmt_template = $db->prepare("SELECT background_image FROM certificate_templates WHERE template_id = ?");
    $stmt_template->execute([$template_id]);
    $background_image_path_from_db = $stmt_template->fetchColumn();
    if (!$background_image_path_from_db) { throw new Exception("Certificate template not found."); }

    // สร้าง Path เต็มไปยัง Background Image (สำหรับ GD)
    if (!defined('ROOT_PATH')) { throw new Exception("ROOT_PATH constant is not defined."); }
    $background_image_path = ROOT_PATH . '/' . ltrim($background_image_path_from_db, '/');
    if (!file_exists($background_image_path)) {
        throw new Exception("Background image file not found at: $background_image_path");
    }

    // 3. ดึงข้อมูลผู้ใช้
    $stmt_user = $db->prepare("SELECT prefix, firstname, lastname FROM users WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) { throw new Exception("User data not found."); }
    $user_full_name_utf8 = $user_data['prefix'] . $user_data['firstname'] . ' ' . $user_data['lastname'];

    // 4. ตรวจสอบว่าสอบผ่าน (เหมือนเดิม)
    $stmt_check_pass = $db->prepare("SELECT a.score FROM attempts a JOIN quizzes q ON a.quiz_id = q.quiz_id WHERE a.user_id = :user_id AND q.course_id = :course_id AND a.status IN ('completed', 'time_expired') ORDER BY a.start_time DESC LIMIT 1");
     $stmt_check_pass->bindParam(':user_id', $user_id, PDO::PARAM_INT);
     $stmt_check_pass->bindParam(':course_id', $course_id, PDO::PARAM_INT);
     $stmt_check_pass->execute();
     $latest_score_data = $stmt_check_pass->fetch(PDO::FETCH_ASSOC);
    $stmt_pass_score = $db->prepare("SELECT pass_score FROM quizzes WHERE course_id = ?");
    $stmt_pass_score->execute([$course_id]);
    $pass_score_threshold = $stmt_pass_score->fetchColumn() ?? 80;
    if (!$latest_score_data || ($latest_score_data['score'] < $pass_score_threshold)) {
        throw new Exception("You have not passed the exam for this course.");
    }

    // --- ถ้าผ่านทุกอย่าง ---

    // 5. ตรวจสอบว่ามี Certificate ใน DB หรือยัง (เหมือนเดิม)
    $stmt_find_cert = $db->prepare("SELECT certificate_id, code, pdf_path FROM certificates WHERE user_id = ? AND course_id = ?");
    $stmt_find_cert->execute([$user_id, $course_id]);
    $existing_cert = $stmt_find_cert->fetch(PDO::FETCH_ASSOC);
    $cert_code_to_display = '';

    // 6. ถ้ายังไม่มี -> สร้างข้อมูลเตรียมไว้ และ บันทึกลง DB
    if (!$existing_cert) {
        error_log("No existing certificate record found for User: $user_id, Course: $course_id. Will generate code and save to DB.");
        $cert_code_to_display = strtoupper($course_short_code) . '-' . $user_id . '-' . time();
        
         // *** (แก้ไข) บันทึก DB ทันทีที่สร้าง Code ***
         $db->beginTransaction();
         try {
             $upload_dir_relative = 'uploads/certificates/' . date('Y/m');
             $pdf_filename = 'cert_' . $user_id . '_' . $course_id . '_' . uniqid() . '.pdf'; // ใช้ Path นี้เป็นตัวแทน
             $pdf_path_relative = $upload_dir_relative . '/' . $pdf_filename;

             $stmt_insert_cert = $db->prepare("
                INSERT INTO certificates (user_id, course_id, template_id, code, pdf_path, issued_at)
                VALUES (?, ?, ?, ?, ?, NOW())
             ");
             $stmt_insert_cert->execute([
                $user_id, $course_id, $template_id, $cert_code_to_display, $pdf_path_relative
             ]);
             $new_cert_id = $db->lastInsertId();
             $db->commit();
             error_log("Certificate record created with ID: $new_cert_id");
         } catch (Exception $e) {
             $db->rollBack();
             error_log("Error saving certificate record to DB: " . $e->getMessage());
             throw new Exception("Could not save certificate record: " . $e->getMessage());
         }
    } else {
        error_log("Existing certificate found. Code: {$existing_cert['code']}");
        $cert_code_to_display = $existing_cert['code'];
        $certificate_data = $existing_cert;
    }
    
    // --- 7. แยก Logic ตาม Format ---

    // ===================================
    // CASE 1: สร้าง PDF (ใช้ mPDF)
    // ===================================
    if ($format === 'pdf') {
        error_log("Generating PDF on the fly for User: $user_id, Course: $course_id using mPDF");

        // สร้าง URL สัมพัทธ์จาก Web Root สำหรับ mPDF (ถ้า BASE_URL ถูกต้อง)
        if (!defined('BASE_URL')) { throw new Exception("BASE_URL constant is not defined."); }
        $background_image_web_path = BASE_URL . '/' . ltrim($background_image_path_from_db, '/');
        error_log("Background Image Web Path for mPDF: " . $background_image_web_path);
        
        // ตั้งค่า mPDF
        $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        $myFontPath = ROOT_PATH . '/assets/fonts';
        $tempDir = ROOT_PATH . '/uploads/temp/';
        if (!is_dir($tempDir)) { mkdir($tempDir, 0775, true); }
        if (!is_writable($tempDir)) { throw new Exception("mPDF temporary directory is not writable: " . $tempDir); }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8', 'format' => 'A4-L',
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'tempDir' => $tempDir,
            'fontDir' => array_merge($fontDirs, [$myFontPath]),
            'fontdata' => $fontData + [
                'sarabun' => ['R' => 'Sarabun-Regular.ttf', 'B' => 'Sarabun-Bold.ttf', 'I' => 'Sarabun-Italic.ttf', 'BI' => 'Sarabun-BoldItalic.ttf',],
                'notosansthai' => ['R' => 'NotoSansThai-Regular.ttf', 'B' => 'NotoSansThai-Bold.ttf',]
            ],
            'default_font' => 'sarabun'
        ]);

        $mpdf->SetDefaultBodyCSS('background', "url('".$background_image_web_path."')");
        $mpdf->SetDefaultBodyCSS('background-image-resize', 6);
        $mpdf->SetDefaultBodyCSS('background-repeat', 'no-repeat');

        // สร้าง HTML (ปรับ CSS ตำแหน่งที่นี่)
        $html = '
        <!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
        <style>
            body { font-family: "sarabun", sans-serif; margin: 0; padding: 50pt 50pt; color: #333; }
            .content-wrapper { width: 100%; height: 100%; position: relative; text-align: center; }
            .user-name { position: absolute; top: 250pt; left: 0; right: 0; font-size: 36pt; font-weight: bold; color: #004D40; }
            .course-line1 { position: absolute; top: 320pt; left: 0; right: 0; font-size: 20pt; }
            .course-line2 { position: absolute; top: 350pt; left: 0; right: 0; font-size: 24pt; font-weight: bold; }
            .cert-code { position: absolute; bottom: 50pt; left: 50pt; font-size: 10pt; text-align: left; color: #555; }
            .issue-date { position: absolute; bottom: 35pt; left: 50pt; font-size: 10pt; text-align: left; color: #555; }
        </style>
        </head><body>
        <div class="content-wrapper">
            <div class="user-name">' . htmlspecialchars($user_full_name_utf8) . '</div>
            <div class="course-line1">ผ่านการอบรมหลักสูตร</div>
            <div class="course-line2">' . htmlspecialchars($course_title) . '</div>
            <div class="cert-code">รหัสเกียรติบัตร: ' . htmlspecialchars($cert_code_to_display) . '</div>
            <div class="issue-date">วันที่ออก: ' . date('d') . ' ' . thai_month(date('m')) . ' ' . (date('Y') + 543) . '</div>
        </div>
        </body></html>
        ';

        $mpdf->WriteHTML($html);

        // ส่ง PDF ให้ Browser โดยตรง
        $download_filename = 'Certificate-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course_title) . '-' . $user_id . '.pdf';
        error_log("Sending PDF directly to browser using mPDF Output('$download_filename', 'D')");
        $mpdf->Output($download_filename, 'D');
        exit;
    }
    
    // ===================================
    // (เพิ่ม) CASE 2: สร้าง Image (ใช้ GD)
    // ===================================
    elseif ($format === 'image') {
        error_log("Generating Image for User: $user_id, Course: $course_id using GD");

        // 1. ตรวจสอบว่ามี GD Library หรือไม่
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            throw new Exception("GD Library is not installed or enabled in PHP.");
        }
        
        // 2. โหลด Background Image
        $image_extension = strtolower(pathinfo($background_image_path, PATHINFO_EXTENSION));
        $image = null;
        if ($image_extension === 'jpg' || $image_extension === 'jpeg') {
            $image = @imagecreatefromjpeg($background_image_path);
        } elseif ($image_extension === 'png') {
            $image = @imagecreatefrompng($background_image_path);
        } else {
            throw new Exception("Unsupported background image format: $image_extension (GD only supports JPEG/PNG here)");
        }
        
        if (!$image) {
            throw new Exception("Failed to load background image with GD from: $background_image_path");
        }
        
        // 3. ตั้งค่า Path ฟอนต์ (ต้องเป็น Path เต็ม)
        $font_path_regular = ROOT_PATH . '/assets/fonts/Sarabun-Regular.ttf';
        $font_path_bold = ROOT_PATH . '/assets/fonts/Sarabun-Bold.ttf';
        
        if (!file_exists($font_path_regular) || !file_exists($font_path_bold)) {
             throw new Exception("Sarabun font files (.ttf) not found in /assets/fonts/");
        }

        // 4. ตั้งค่าสี (ตัวอย่าง - ควรปรับตาม Template)
        // (R, G, B: 0-255)
        $color_dark_green = imagecolorallocate($image, 0, 77, 64); // #004D40
        $color_black = imagecolorallocate($image, 51, 51, 51); // #333333
        $color_gray = imagecolorallocate($image, 85, 85, 85); // #555555

        // 5. "วาด" ข้อความลงบนภาพ
        // *** คุณต้องปรับแก้ X, Y, FontSize, Angle (มุม) ให้ตรงกับ Template ***
        // imagettftext(image, size, angle, x, y, color, fontfile, text)
        
        // ขนาดรูปภาพ (สำหรับคำนวณกึ่งกลาง)
        $image_width = imagesx($image);
        // $image_height = imagesy($image);
        
        // 5.1 ชื่อผู้ใช้ (ตัวหนา, 36pt, กึ่งกลาง)
        $font_size_name = 36;
        $text_box_name = imagettfbbox($font_size_name, 0, $font_path_bold, $user_full_name_utf8);
        $text_width_name = $text_box_name[2] - $text_box_name[0];
        $x_name = ($image_width - $text_width_name) / 2; // กึ่งกลางแนวนอน
        $y_name = 450; // *** ปรับ Y ***
        imagettftext($image, $font_size_name, 0, $x_name, $y_name, $color_dark_green, $font_path_bold, $user_full_name_utf8);

        // 5.2 บรรทัด 1 (ธรรมดา, 20pt, กึ่งกลาง)
        $font_size_line1 = 20;
        $text_line1 = "ผ่านการอบรมหลักสูตร";
        $text_box_line1 = imagettfbbox($font_size_line1, 0, $font_path_regular, $text_line1);
        $text_width_line1 = $text_box_line1[2] - $text_box_line1[0];
        $x_line1 = ($image_width - $text_width_line1) / 2;
        $y_line1 = 520; // *** ปรับ Y ***
        imagettftext($image, $font_size_line1, 0, $x_line1, $y_line1, $color_black, $font_path_regular, $text_line1);

        // 5.3 ชื่อคอร์ส (ตัวหนา, 24pt, กึ่งกลาง)
        $font_size_line2 = 24;
        $text_line2 = $course_title;
        $text_box_line2 = imagettfbbox($font_size_line2, 0, $font_path_bold, $text_line2);
        $text_width_line2 = $text_box_line2[2] - $text_box_line2[0];
        $x_line2 = ($image_width - $text_width_line2) / 2;
        $y_line2 = 560; // *** ปรับ Y ***
        imagettftext($image, $font_size_line2, 0, $x_line2, $y_line2, $color_black, $font_path_bold, $text_line2);

        // 5.4 รหัส (ธรรมดา, 10pt, ชิดซ้าย)
        $font_size_footer = 10;
        $text_code = "รหัสเกียรติบัตร: " . $cert_code_to_display;
        $x_footer = 80; // *** ปรับ X ***
        $y_code = 800; // *** ปรับ Y ***
        imagettftext($image, $font_size_footer, 0, $x_footer, $y_code, $color_gray, $font_path_regular, $text_code);
        
        // 5.5 วันที่ (ธรรมดา, 10pt, ชิดซ้าย)
        $text_date = "วันที่ออก: " . date('d') . ' ' . thai_month(date('m')) . ' ' . (date('Y') + 543);
        $y_date = 820; // *** ปรับ Y ***
        imagettftext($image, $font_size_footer, 0, $x_footer, $y_date, $color_gray, $font_path_regular, $text_date);


        // 6. ส่งไฟล์ Image ให้ Browser
        $download_filename = 'Certificate-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course_title) . '-' . $user_id . '.jpg';
        
        // ตั้งค่า Header สำหรับ JPG (หรือ PNG ถ้าต้องการ)
        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $download_filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // ล้าง Output Buffer ก่อนส่ง Image
        while (ob_get_level()) {
            ob_end_clean();
        }

        // สร้างและส่งรูปภาพ JPG
        imagejpeg($image);
        
        // 7. ทำลาย Object รูปภาพ
        imagedestroy($image);
        exit;
    }


} catch (Throwable $e) {
    // แสดง Error หรือ Redirect
    error_log("Fatal Error generating certificate: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    // แสดง Error ให้ User เห็น
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>Error Generating Certificate</h1>";
    echo "<p>Sorry, an error occurred while generating your certificate.</p>";
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        echo "<p><strong>Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        echo "<p>Please contact the administrator.</p>";
    }
    if ($course_id > 0) {
        echo "<p><a href='course.php?id=" . $course_id . "'>Return to Course</a></p>";
    } else {
        echo "<p><a href='dashboard.php'>Return to Dashboard</a></p>";
    }
    exit; // จบการทำงาน
}


// --- ฟังก์ชันเสริม แปลงเดือนเป็นภาษาไทย ---
function thai_month($month_num) {
    $months = [
        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
        '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
        '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
    ];
    return $months[$month_num] ?? '';
}

?>