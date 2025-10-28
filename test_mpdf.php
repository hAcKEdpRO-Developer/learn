<?php
// --- 1. แสดง Error ทั้งหมด ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Test Script Started...<br>";

// --- 2. ลองเรียก Autoloader ---
$autoload_path = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoload_path)) {
    die("<strong>ERROR:</strong> File not found: <code>" . $autoload_path . "</code><br>Please check your 'vendor' folder location.");
}

try {
    require_once $autoload_path;
    echo "Autoloader loaded successfully.<br>";
} catch (Throwable $e) {
    die("<strong>ERROR loading autoloader:</strong> " . $e->getMessage());
}

// --- 3. ลองเรียก Class ที่มีปัญหา ---
$class_to_test = 'Mpdf\Config\ConfigVariables';

if (class_exists($class_to_test)) {
    echo "<strong>SUCCESS:</strong> Class <code>" . $class_to_test . "</code> was found!<br><br>";
    echo "This means your 'vendor' folder and 'autoload.php' are working correctly.<br>";
    echo "The problem is likely that <strong>generate_certificate.php</strong> is not loading the autoloader (missing <code>require_once</code> at the top).";
} else {
    echo "<strong>FAILURE:</strong> Class <code>" . $class_to_test . "</code> was <strong>NOT found</strong>.<br><br>";
    echo "This means there is a problem with your Composer installation or the 'vendor' directory itself, even after running 'composer install'.";
}

?>