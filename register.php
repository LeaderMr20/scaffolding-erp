<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Allow access only if: no users exist yet, OR logged-in admin
$userCount = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$firstSetup = ($userCount == 0);

if (!$firstSetup) {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password']  ?? '';
    $confirm   = $_POST['confirm']   ?? '';
    $role      = $firstSetup ? 'admin' : ($_POST['role'] ?? 'user');

    if (!$full_name || !$username || !$password) {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة.';
    } elseif (mb_strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    } elseif ($password !== $confirm) {
        $error = 'كلمة المرور وتأكيدها غير متطابقتين.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param('s', $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'اسم المستخدم مستخدم بالفعل، اختر اسماً آخر.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users(full_name, username, password, role) VALUES(?,?,?,?)");
            $stmt->bind_param('ssss', $full_name, $username, $hash, $role);
            $stmt->execute();
            if ($firstSetup) {
                // Auto-login the first admin
                $uid = $conn->insert_id;
                $_SESSION['user_id']   = $uid;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_role'] = 'admin';
                header('Location: index.php');
                exit;
            }
            $success = "تم إنشاء الحساب بنجاح.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $firstSetup ? 'إعداد النظام' : 'إنشاء مستخدم' ?> — نظام السقالات</title>
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
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(59,130,246,0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(99,102,241,0.1) 0%, transparent 40%);
    pointer-events: none;
}
.login-wrap { width: 100%; max-width: 480px; position: relative; z-index: 1; }
.login-logo { text-align: center; margin-bottom: 32px; }
.login-logo .logo-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 28px; color: #fff; margin-bottom: 14px;
    box-shadow: 0 8px 32px rgba(59,130,246,0.4);
}
.login-logo h1 { color: #f1f5f9; font-size: 1.4rem; font-weight: 700; margin: 0; }
.login-logo p  { color: #64748b; font-size: 0.88rem; margin: 4px 0 0; }
.login-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px; padding: 36px 32px;
    backdrop-filter: blur(12px);
    box-shadow: 0 24px 48px rgba(0,0,0,0.4);
}
.login-card h5 { color: #f1f5f9; font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
.login-card .sub { color: #64748b; font-size: 0.85rem; margin-bottom: 24px; }

/* First setup badge */
.setup-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);
    color: #fbbf24; font-size: 0.78rem; font-weight: 600;
    padding: 4px 12px; border-radius: 20px; margin-bottom: 20px;
}

.form-label { color: #94a3b8; font-size: 0.82rem; font-weight: 600; margin-bottom: 6px; }
.input-wrap { position: relative; margin-bottom: 16px; }
.input-wrap .input-icon {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    color: #475569; font-size: 1rem; pointer-events: none;
}
.input-wrap .form-control {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px; color: #f1f5f9; padding: 11px 42px 11px 16px;
    font-family: 'Cairo', sans-serif; font-size: 0.9rem; transition: border .2s, background .2s;
}
.input-wrap .form-control::placeholder { color: #475569; }
.input-wrap .form-control:focus {
    background: rgba(59,130,246,0.08); border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15); color: #f1f5f9; outline: none;
}
.form-select-dark {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px; color: #f1f5f9; padding: 11px 16px;
    font-family: 'Cairo', sans-serif; font-size: 0.9rem; width: 100%;
    appearance: none; cursor: pointer;
}
.form-select-dark option { background: #1e293b; color: #f1f5f9; }

.btn-register {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: none; border-radius: 10px; color: #fff;
    font-family: 'Cairo', sans-serif; font-size: 0.95rem; font-weight: 700;
    cursor: pointer; transition: opacity .2s, transform .15s;
    box-shadow: 0 4px 16px rgba(59,130,246,0.35); margin-top: 8px;
}
.btn-register:hover { opacity: 0.9; transform: translateY(-1px); }

.error-box {
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25);
    border-radius: 10px; color: #fca5a5; padding: 10px 14px; font-size: 0.85rem;
    margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
}
.success-box {
    background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.25);
    border-radius: 10px; color: #86efac; padding: 10px 14px; font-size: 0.85rem;
    margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
}
.back-link { text-align: center; margin-top: 20px; color: #475569; font-size: 0.85rem; }
.back-link a { color: #60a5fa; text-decoration: none; font-weight: 600; }
.footer-note { text-align: center; margin-top: 28px; color: #334155; font-size: 0.78rem; }
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
        <?php if ($firstSetup): ?>
        <div class="setup-badge"><i class="bi bi-stars"></i> إعداد النظام — الحساب الأول</div>
        <h5>إنشاء حساب المدير العام</h5>
        <p class="sub">هذا الحساب سيحصل تلقائياً على صلاحية المدير العام</p>
        <?php else: ?>
        <h5>إنشاء مستخدم جديد</h5>
        <p class="sub">إضافة حساب للنظام</p>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="error-box"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="success-box"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div>
                <label class="form-label">الاسم الكامل <span style="color:#ef4444">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-person-badge input-icon"></i>
                    <input type="text" name="full_name" class="form-control" placeholder="الاسم كما في الهوية"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
            </div>
            <div>
                <label class="form-label">اسم المستخدم <span style="color:#ef4444">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-at input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="مثال: ahmed123"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
            </div>
            <div>
                <label class="form-label">كلمة المرور <span style="color:#ef4444">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control"
                           placeholder="6 أحرف على الأقل" required>
                </div>
            </div>
            <div>
                <label class="form-label">تأكيد كلمة المرور <span style="color:#ef4444">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <input type="password" name="confirm" class="form-control"
                           placeholder="أعد كتابة كلمة المرور" required>
                </div>
            </div>

            <?php if (!$firstSetup): ?>
            <div style="margin-bottom:16px">
                <label class="form-label">الصلاحية</label>
                <select name="role" class="form-select-dark">
                    <option value="user"  <?= (($_POST['role'] ?? '') == 'user')  ? 'selected' : '' ?>>موظف عادي</option>
                    <option value="admin" <?= (($_POST['role'] ?? '') == 'admin') ? 'selected' : '' ?>>مدير عام</option>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-register">
                <i class="bi bi-person-plus me-2"></i>
                <?= $firstSetup ? 'إنشاء حساب المدير والدخول' : 'إنشاء الحساب' ?>
            </button>
        </form>

        <?php if (!$firstSetup): ?>
        <div class="back-link"><a href="index.php"><i class="bi bi-arrow-right me-1"></i>العودة للرئيسية</a></div>
        <?php else: ?>
        <div class="back-link">لديك حساب؟ <a href="login.php">تسجيل الدخول</a></div>
        <?php endif; ?>
    </div>

    <div class="footer-note">نظام إدارة السقالات &copy; <?= date('Y') ?></div>
</div>
</body>
</html>
