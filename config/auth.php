<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function currentUserName() {
    return $_SESSION['user_name'] ?? 'مستخدم';
}

function currentUserRole() {
    return $_SESSION['user_role'] ?? 'user';
}

function requireLogin() {
    if (!isLoggedIn()) {
        $appRoot    = str_replace('\\', '/', realpath(__DIR__ . '/..')) . '/';
        $scriptDir  = str_replace('\\', '/', realpath(dirname($_SERVER['SCRIPT_FILENAME']))) . '/';
        $relPath    = str_replace($appRoot, '', $scriptDir);
        $depth      = !empty(trim($relPath, '/')) ? substr_count(rtrim($relPath, '/'), '/') + 1 : 0;
        $base       = $depth > 0 ? str_repeat('../', $depth) : '';
        header('Location: ' . $base . 'login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $appRoot    = str_replace('\\', '/', realpath(__DIR__ . '/..')) . '/';
        $scriptDir  = str_replace('\\', '/', realpath(dirname($_SERVER['SCRIPT_FILENAME']))) . '/';
        $relPath    = str_replace($appRoot, '', $scriptDir);
        $depth      = !empty(trim($relPath, '/')) ? substr_count(rtrim($relPath, '/'), '/') + 1 : 0;
        $base       = $depth > 0 ? str_repeat('../', $depth) : '';
        header('Location: ' . $base . 'index.php?denied=1');
        exit;
    }
}
