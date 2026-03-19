<?php
// TEMPORARY DIAGNOSTIC — DELETE AFTER USE
require_once 'config/db.php';

$out = [];

// 1. DB connection
$out[] = "DB Connected: YES";
$out[] = "DB Host: " . $conn->host_info;

// 2. Users table
$r = $conn->query("SELECT COUNT(*) c FROM users");
if (!$r) { $out[] = "users table ERROR: " . $conn->error; }
else {
    $count = (int)$r->fetch_assoc()['c'];
    $out[] = "Users count: $count";
    if ($count > 0) {
        $users = $conn->query("SELECT id, username, role, LEFT(password,20) pw_preview FROM users");
        while ($u = $users->fetch_assoc()) {
            $out[] = "  User: id={$u['id']} username={$u['username']} role={$u['role']} pw={$u['pw_preview']}...";
        }
    }
}

// 3. Test password_hash
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$out[] = "password_hash works: " . (strlen($hash) > 20 ? 'YES' : 'NO');
$out[] = "password_verify works: " . (password_verify('admin123', $hash) ? 'YES' : 'NO');

// 4. Try insert if empty
$r2 = $conn->query("SELECT COUNT(*) c FROM users");
if ($r2 && (int)$r2->fetch_assoc()['c'] === 0) {
    $h = $conn->real_escape_string($hash);
    $ok = $conn->query("INSERT INTO users(full_name,username,password,role) VALUES('مدير النظام','admin','$h','admin')");
    $out[] = "Admin insert: " . ($ok ? 'SUCCESS' : 'FAILED: ' . $conn->error);
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $out);
