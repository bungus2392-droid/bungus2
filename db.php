<?php
// db.php

// --| إظهار الأخطاء لأغراض التصحيح | --
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --| إعدادات الاتصال بقاعدة البيانات |--
// استبدل هذه القيم بالمعلومات الصحيحة لخادمك
$servername = "localhost"; // عادة ما يكون localhost
$username = "root";      // اسم مستخدم قاعدة البيانات
$password = "";          // كلمة مرور قاعدة البيانات
$dbname = "quiz_app_db"; // اسم قاعدة البيانات

// --| إنشاء الاتصال |--
$conn = new mysqli($servername, $username, $password, $dbname);

// --| التحقق من الاتصال |--
if ($conn->connect_error) {
  // إيقاف التنفيذ وإظهار رسالة خطأ في حال فشل الاتصال
  header('Content-Type: application/json');
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => "فشل الاتصال بقاعدة البيانات: " . $conn->connect_error]);
  exit();
}

// --| تعيين ترميز الأحرف إلى UTF-8 لدعم اللغة العربية |--
$conn->set_charset("utf8");

?>
