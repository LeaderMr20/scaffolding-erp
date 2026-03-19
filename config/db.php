<?php
// Auto-detect environment
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'erpmanagement.wuaze.com') {
    $host     = "sql212.infinityfree.com";
    $user     = "if0_41384283";
    $password = "nsri2030";
    $database = "if0_41384283_scaffolding_erp";
} else {
    $host     = "localhost";
    $user     = "root";
    $password = "";
    $database = "scaffolding_erp";
}

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Force UTF-8 on connection — must happen before ANY query
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("SET CHARACTER SET utf8mb4");
$conn->query("SET SESSION character_set_client     = utf8mb4");
$conn->query("SET SESSION character_set_connection = utf8mb4");
$conn->query("SET SESSION character_set_results    = utf8mb4");

// ── Create tables ─────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    phone VARCHAR(50),
    address TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    quantity INT,
    price_day DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    start_date DATE,
    end_date DATE,
    total DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS contract_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT,
    equipment_id INT,
    qty INT,
    price DECIMAL(10,2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT,
    amount DECIMAL(10,2),
    payment_date DATE,
    method VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    position VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    phone VARCHAR(30),
    salary DECIMAL(12,2) DEFAULT 0,
    hire_date DATE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    amount DECIMAL(10,2),
    expense_date DATE,
    description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    contract_id INT NOT NULL,
    issue_date DATE NOT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── One-time migration: convert existing tables + fix corrupted data ───────
$mig = $conn->query("SELECT setting_value FROM settings WHERE setting_key='charset_v4' LIMIT 1");
if (!$mig || $mig->num_rows === 0) {
    $tables = ['clients','equipment','contracts','contract_items','payments',
               'employees','expenses','expense_categories','settings','users','invoices'];
    foreach ($tables as $t) {
        $conn->query("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    // Fix corrupted Arabic rows stored as '????' in latin1
    $conn->query("UPDATE payments  SET method='نقدي'          WHERE method    LIKE '?%' OR method=''");
    $conn->query("UPDATE users     SET full_name='مدير النظام' WHERE full_name LIKE '?%' OR full_name=''");
    $conn->query("UPDATE clients   SET name=CONCAT('عميل-',id)  WHERE name     LIKE '?%' OR name=''");
    $conn->query("UPDATE equipment SET name=CONCAT('معدة-',id)  WHERE name     LIKE '?%' OR name=''");
    $conn->query("UPDATE expenses  SET title='مصروف'           WHERE title    LIKE '?%' OR title=''");
    $conn->query("UPDATE expense_categories SET name=CONCAT('فئة-',id) WHERE name LIKE '?%' OR name=''");
    $conn->query("UPDATE settings  SET setting_value='مؤسسة الجود للسقالات' WHERE setting_key='company_name' AND (setting_value LIKE '?%' OR setting_value='')");
    $conn->query("UPDATE settings  SET setting_value='314325455800012'       WHERE setting_key='vat_number'   AND (setting_value LIKE '?%' OR setting_value='')");
    $conn->query("UPDATE settings  SET setting_value=''  WHERE setting_key IN ('company_address','company_phone','commercial_registration') AND setting_value LIKE '?%'");
    $conn->query("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('charset_v4','1')");
}

// ── Default settings ────────────────────────────────────────────────────────
$defs = [
    'company_name'            => 'مؤسسة الجود للسقالات',
    'vat_number'              => '314325455800012',
    'company_phone'           => '',
    'company_address'         => '',
    'commercial_registration' => '',
];
foreach ($defs as $k => $v) {
    $k2 = $conn->real_escape_string($k);
    $v2 = $conn->real_escape_string($v);
    $conn->query("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('$k2','$v2')");
}

// ── Default expense categories ──────────────────────────────────────────────
$cc = $conn->query("SELECT COUNT(*) c FROM expense_categories");
if ($cc && (int)$cc->fetch_assoc()['c'] === 0) {
    $cats = ['وقود ومحروقات','صيانة المعدات والسقالات','إيجار مستودع','رواتب وأجور',
             'نقل وشحن','كهرباء وماء','قطع غيار وتوريدات','تأمين',
             'اتصالات وإنترنت','مستلزمات مكتبية','رسوم حكومية وتراخيص',
             'صيانة سيارات','مصاريف متنوعة'];
    foreach ($cats as $i => $name) {
        $n = $conn->real_escape_string($name);
        $conn->query("INSERT INTO expense_categories(name,sort_order) VALUES('$n',".($i+1).")");
    }
}

// ── Default admin user ──────────────────────────────────────────────────────
$au = $conn->query("SELECT id FROM users WHERE username='admin' LIMIT 1");
if ($au && $au->num_rows === 0) {
    $hash = $conn->real_escape_string(password_hash('admin123', PASSWORD_DEFAULT));
    $conn->query("INSERT INTO users(full_name,username,password,role) VALUES('مدير النظام','admin','$hash','admin')");
}

// ── getSetting helper ───────────────────────────────────────────────────────
if (!function_exists('getSetting')) {
    function getSetting($conn, $key) {
        $k   = $conn->real_escape_string($key);
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$k' LIMIT 1");
        return $res && $res->num_rows ? $res->fetch_assoc()['setting_value'] : '';
    }
}
