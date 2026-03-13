<?php
require_once 'config/db.php';
require_once 'config/auth.php';

if (isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    } else {
        $error = 'يرجى تعبئة جميع الحقول.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول — نظام السقالات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { font-family: 'Cairo', sans-serif; box-sizing: border-box; }
body {
    background: #0f172a;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    overflow: hidden;
}
/* Background pattern */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(59,130,246,0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(99,102,241,0.1) 0%, transparent 40%);
    pointer-events: none;
}

.login-wrap {
    width: 100%;
    max-width: 440px;
    position: relative;
    z-index: 1;
}

/* Logo area */
.login-logo {
    text-align: center;
    margin-bottom: 32px;
}
.login-logo .logo-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    margin-bottom: 14px;
    box-shadow: 0 8px 32px rgba(59,130,246,0.4);
}
.login-logo h1 {
    color: #f1f5f9;
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
}
.login-logo p {
    color: #64748b;
    font-size: 0.88rem;
    margin: 4px 0 0;
}

/* Card */
.login-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    padding: 36px 32px;
    backdrop-filter: blur(12px);
    box-shadow: 0 24px 48px rgba(0,0,0,0.4);
}
.login-card h5 {
    color: #f1f5f9;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 6px;
}
.login-card .sub {
    color: #64748b;
    font-size: 0.85rem;
    margin-bottom: 28px;
}

/* Form */
.form-label {
    color: #94a3b8;
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: 6px;
}
.input-wrap {
    position: relative;
    margin-bottom: 18px;
}
.input-wrap .input-icon {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #475569;
    font-size: 1rem;
    pointer-events: none;
}
.input-wrap .form-control {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: #f1f5f9;
    padding: 11px 42px 11px 16px;
    font-family: 'Cairo', sans-serif;
    font-size: 0.9rem;
    transition: border .2s, background .2s;
}
.input-wrap .form-control::placeholder { color: #475569; }
.input-wrap .form-control:focus {
    background: rgba(59,130,246,0.08);
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
    color: #f1f5f9;
    outline: none;
}

.btn-login {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-family: 'Cairo', sans-serif;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .2s, transform .15s;
    box-shadow: 0 4px 16px rgba(59,130,246,0.35);
    margin-top: 8px;
}
.btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-login:active { transform: translateY(0); }

.error-box {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 10px;
    color: #fca5a5;
    padding: 10px 14px;
    font-size: 0.85rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.register-link {
    text-align: center;
    margin-top: 20px;
    color: #475569;
    font-size: 0.85rem;
}
.register-link a {
    color: #60a5fa;
    text-decoration: none;
    font-weight: 600;
}
.register-link a:hover { text-decoration: underline; }

.footer-note {
    text-align: center;
    margin-top: 28px;
    color: #334155;
    font-size: 0.78rem;
}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <div class="logo-icon"><i class="bi bi-building"></i></div>
        <h1>نظام إدارة السقالات</h1>
        <p>ERP Scaffolding Management</p>
    </div>

    <div class="login-card">
        <h5>مرحباً بعودتك</h5>
        <p class="sub">سجّل دخولك للمتابعة</p>

        <?php if ($error): ?>
        <div class="error-box">
            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div>
                <label class="form-label">اسم المستخدم</label>
                <div class="input-wrap">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="username" class="form-control"
                           placeholder="أدخل اسم المستخدم"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
            </div>
            <div>
                <label class="form-label">كلمة المرور</label>
                <div class="input-wrap">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control"
                           placeholder="أدخل كلمة المرور" required>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i> دخول
            </button>
        </form>

        <div class="register-link">
            ليس لديك حساب؟ <a href="register.php">إنشاء حساب</a>
        </div>
    </div>

    <div class="footer-note">نظام إدارة السقالات &copy; <?= date('Y') ?></div>
</div>
</body>
</html>
