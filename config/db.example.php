<?php
// انسخ هذا الملف باسم db.php وأدخل بيانات قاعدة البيانات الخاصة بك
$host     = "localhost";         // مضيف قاعدة البيانات
$user     = "DB_USERNAME";       // اسم مستخدم قاعدة البيانات
$password = "DB_PASSWORD";       // كلمة مرور قاعدة البيانات
$database = "DB_NAME";           // اسم قاعدة البيانات

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Auto-create users table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>
