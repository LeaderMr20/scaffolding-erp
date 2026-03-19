<?php
require_once str_replace('\\', '/', realpath(__DIR__ . '/../config/auth.php'));
requireLogin();

$appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..')) . '/';
$currentDir = str_replace('\\', '/', realpath('.')) . '/';
$relPath = str_replace($appRoot, '', $currentDir);
$depth = !empty(trim($relPath, '/')) ? substr_count(rtrim($relPath, '/'), '/') + 1 : 0;
$base = $depth > 0 ? str_repeat('../', $depth) : '';

function isActive($path) {
    return strpos($_SERVER['PHP_SELF'], $path) !== false ? 'active' : '';
}
function isActiveDash() {
    $p = $_SERVER['PHP_SELF'];
    return (substr($p, -9) === 'index.php' && strpos($p, '/modules/') === false) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1e3a6e">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="السقالات ERP">
<link rel="manifest" href="<?= $base ?>manifest.json">
<link rel="apple-touch-icon" href="<?= $base ?>icons/icon-192.png">
<title>نظام إدارة السقالات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --sidebar-bg:     #0b162c;
  --sidebar-hover:  #182a4d;
  --sidebar-active: #2563eb;
  --sidebar-text:   #cbd5e1;
  --sidebar-white:  #f8fafc;
  --accent:         #2563eb;
  --accent-light:   #dbeafe;
  --surface:        #ffffff;
  --bg:             #f1f5f9;
  --border:         #e2e8f0;
  --text-main:      #1e293b;
  --text-muted:     #64748b;
  --sidebar-width:  260px;
  --sidebar-collapsed-width: 80px;
  --topbar-h:       72px;
  --radius:         16px;
  --shadow-sm:      0 2px 4px rgba(0,0,0,0.02);
  --shadow-md:      0 8px 20px rgba(0,0,0,0.04);
  --shadow-lg:      0 12px 30px rgba(0,0,0,0.06);
  --transition:     all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
  font-family: 'Cairo', sans-serif;
  background: var(--bg);
  color: var(--text-main);
  min-height: 100vh;
  font-size: 14px;
  transition: var(--transition);
  overflow-x: hidden;
}
body.sidebar-collapsed {
  /* No padding on body, handled by #main margin */
}

/* ─── SIDEBAR ─────────────────────────────────── */
#sidebar {
  width: var(--sidebar-width);
  min-height: 100vh;
  background: var(--sidebar-bg);
  position: fixed;
  right: 0; top: 0; bottom: 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  transition: var(--transition);
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: none;
  box-shadow: -4px 0 24px rgba(0,0,0,0.1);
}
#sidebar::-webkit-scrollbar { display: none; }

/* Collapsed Sidebar on Desktop */
body.sidebar-collapsed #sidebar {
  width: var(--sidebar-collapsed-width);
}
body.sidebar-collapsed #main {
  margin-right: var(--sidebar-collapsed-width);
}

/* Brand */
.sb-brand {
  display: flex; align-items: center; gap: 14px;
  padding: 24px;
  border-bottom: 1px solid rgba(255,255,255,.04);
  min-height: var(--topbar-h);
  transition: var(--transition);
  white-space: nowrap;
}
body.sidebar-collapsed .sb-brand {
  padding: 24px 18px;
  justify-content: center;
}
body.sidebar-collapsed .logo-text {
  opacity: 0;
  width: 0;
  display: none;
}
.sb-brand .logo-icon {
  width: 44px; height: 44px;
  background: linear-gradient(135deg, var(--accent), #60a5fa);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
  transition: var(--transition);
}
body.sidebar-collapsed .sb-brand .logo-icon {
  width: 36px; height: 36px; font-size: 16px;
}
.sb-brand .logo-text { color: var(--sidebar-white); transition: var(--transition); }
.sb-brand .logo-text strong { display: block; font-size: 16px; font-weight: 700; line-height: 1.2; letter-spacing: 0.5px; }
.sb-brand .logo-text span { font-size: 11px; color: var(--sidebar-text); font-weight: 500; }

/* Nav sections */
.sb-section {
  padding: 24px 24px 8px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #64748b;
  white-space: nowrap;
  transition: var(--transition);
}
body.sidebar-collapsed .sb-section {
  opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden;
}

.sb-nav { list-style: none; padding: 0 12px; }
.sb-nav li { margin-bottom: 4px; }
.sb-nav a {
  display: grid;
  grid-template-columns: 34px 1fr;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 10px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  transition: var(--transition);
  position: relative;
  white-space: nowrap;
  overflow: hidden;
}
.sb-nav a .nav-text {
  transition: var(--transition);
  text-align: right;
}
body.sidebar-collapsed .sb-nav a {
  padding: 12px;
  grid-template-columns: 1fr;
  justify-content: center;
}
body.sidebar-collapsed .sb-nav a .nav-text {
  opacity: 0; width: 0; display: none !important;
}
.sb-nav a .nav-icon {
  width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  font-size: 16px;
  background: transparent;
  transition: var(--transition);
}


body.sidebar-collapsed .sb-nav a .nav-icon {
  width: 40px; height: 40px; font-size: 18px;
}
.sb-nav a:hover { background: var(--sidebar-hover); color: #fff; transform: translateX(-4px); }
body.sidebar-collapsed .sb-nav a:hover { transform: translateY(-3px); }

.sb-nav a:hover .nav-icon { background: rgba(255,255,255,.05); }
.sb-nav a.active {
  background: var(--sidebar-hover);
  color: #fff;
}
.sb-nav a.active .nav-icon {
  background: var(--accent);
  color: #fff;
  box-shadow: 0 4px 12px rgba(37,99,235,.3);
}
.sb-nav a.active::before {
  content: '';
  position: absolute;
  right: 0; top: 50%; transform: translateY(-50%);
  width: 4px; height: 24px;
  background: var(--accent);
  border-radius: 4px 0 0 4px;
}
body.sidebar-collapsed .sb-nav a.active::before { display: none; }

/* Sidebar footer */
.sb-footer {
  margin-top: auto;
  padding: 24px;
  border-top: 1px solid rgba(255,255,255,.04);
  transition: var(--transition);
  white-space: nowrap;
}
body.sidebar-collapsed .sb-footer {
  opacity: 0; visibility: hidden; padding: 0; height: 0; display: none;
}
.sb-version {
  font-size: 12px; color: #64748b; text-align: center; font-weight: 600;
}

/* ─── MAIN ───────────────────────────────────── */
#main {
  margin-right: var(--sidebar-width);
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  transition: var(--transition);
}

/* ─── TOPBAR ─────────────────────────────────── */
#topbar {
  height: var(--topbar-h);
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 32px;
  gap: 16px;
  position: sticky; top: 0; z-index: 900;
  box-shadow: var(--shadow-sm);
}
.desktop-toggle {
  background: var(--bg); border: 1px solid var(--border);
  font-size: 20px; color: var(--text-main);
  cursor: pointer; width: 40px; height: 40px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  transition: var(--transition);
  position: relative; z-index: 1050; /* Ensure clickability */
}
.desktop-toggle:hover { background: #e2e8f0; color: var(--accent); }
#sidebarToggle {
  display: none;
  background: var(--bg); border: 1px solid var(--border);
  font-size: 20px; color: var(--text-main);
  cursor: pointer; width: 40px; height: 40px;
  align-items: center; justify-content: center;
  border-radius: 10px;
  transition: var(--transition);
}
#sidebarToggle:hover { background: #e2e8f0; color: var(--accent); }

.topbar-title {
  font-size: 18px; font-weight: 800;
  color: var(--text-main);
  letter-spacing: 0.5px;
  margin-right: 12px;
}
.topbar-right { display: flex; align-items: center; gap: 12px; }

.topbar-chip {
  display: flex; align-items: center; gap: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 6px 16px;
  font-size: 13px;
  color: var(--text-main);
  font-weight: 600;
  box-shadow: var(--shadow-sm);
}
.topbar-chip i { font-size: 15px; color: var(--accent); }

/* ─── CONTENT ────────────────────────────────── */
#content { padding: 32px; flex: 1; }

/* ─── CARDS ──────────────────────────────────── */
.card {
  border: none !important;
  border-radius: var(--radius) !important;
  box-shadow: var(--shadow-md) !important;
  background: var(--surface);
  transition: var(--transition);
}
.card:hover {
  box-shadow: var(--shadow-lg) !important;
}
.card-header {
  background: transparent !important;
  border-bottom: 1px solid #f1f5f9 !important;
  padding: 20px 24px !important;
  font-weight: 700;
  font-size: 16px;
  color: var(--text-main);
}

/* Stat cards */
.stat-card {
  background: var(--surface);
  border: none;
  border-radius: var(--radius);
  padding: 24px;
  display: flex; align-items: center; gap: 20px;
  box-shadow: var(--shadow-md);
  transition: var(--transition);
  text-decoration: none; color: inherit;
  position: relative;
  overflow: hidden;
}
.stat-card::after {
  content: '';
  position: absolute; right: 0; bottom: 0; width: 100px; height: 100px;
  background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0) 70%);
  opacity: 0; transition: var(--transition);
  border-radius: 50%;
  transform: translate(30%, 30%);
}
.stat-card:hover::after { opacity: 0.2; }
.stat-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-4px);
  color: inherit;
}
.stat-icon {
  width: 56px; height: 56px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
  box-shadow: inset 0 2px 4px rgba(255,255,255,0.4);
}
.stat-icon.blue   { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
.stat-icon.green  { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #16a34a; }
.stat-icon.amber  { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
.stat-icon.purple { background: linear-gradient(135deg, #faf5ff, #f3e8ff); color: #9333ea; }
.stat-icon.rose   { background: linear-gradient(135deg, #fff1f2, #ffe4e6); color: #e11d48; }
.stat-icon.teal   { background: linear-gradient(135deg, #f0fdfa, #ccfbf1); color: #0d9488; }

.stat-label { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; letter-spacing: 0.3px; }
.stat-value { font-size: 32px; font-weight: 800; line-height: 1; color: var(--text-main); }
.stat-sub   { font-size: 12px; color: var(--text-muted); margin-top: 6px; font-weight: 500; }

/* Finance cards */
.finance-card {
  background: var(--surface);
  border: none;
  border-radius: var(--radius);
  padding: 24px;
  box-shadow: var(--shadow-md);
  transition: var(--transition);
}
.finance-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
.finance-card .fc-label { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; letter-spacing: 0.3px; }
.finance-card .fc-value { font-size: 30px; font-weight: 800; }
.finance-card .fc-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
}

/* Tables */
.table { font-size: 14px; margin-bottom: 0; }
.table thead th {
  font-weight: 700;
  font-size: 13px;
  letter-spacing: .5px;
  color: var(--text-muted);
  text-transform: uppercase;
  background: transparent !important;
  border-bottom: 2px solid #f1f5f9 !important;
  padding: 16px 24px !important;
  white-space: nowrap;
}
.table tbody td {
  padding: 16px 24px !important;
  vertical-align: middle;
  border-bottom: 1px solid #f8fafc !important;
  color: var(--text-main);
  font-weight: 500;
}
.table tbody tr { transition: var(--transition); }
.table tbody tr:hover { background: #f8fafc; transform: scale(1.002); }
.table tbody tr:last-child td { border-bottom: none !important; }

/* Badges */
.badge { font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 20px; letter-spacing: 0.3px; }

/* Buttons */
.btn { font-family: 'Cairo', sans-serif; font-weight: 700; border-radius: 10px; font-size: 14px; padding: 8px 16px; transition: var(--transition); }
.btn-primary { background: linear-gradient(135deg, var(--accent), #3b82f6); border: none; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
.btn-primary:hover { background: linear-gradient(135deg, #1d4ed8, var(--accent)); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37,99,235,0.4); }

/* Page header */
.page-header { margin-bottom: 32px; }
.page-header h4 {
  font-size: 26px; font-weight: 800;
  color: var(--text-main); margin: 0;
  letter-spacing: -0.5px;
}
.page-header p { color: var(--text-muted); font-size: 14px; margin: 6px 0 0; font-weight: 500; }

/* Fix Bootstrap RTL select option visibility and text clipping */
select { direction: rtl !important; text-align: right !important; text-align-last: right !important; }
select option { color: #1e293b !important; background: #ffffff !important; direction: rtl !important; text-align: right !important; unicode-bidi: embed !important; }

/* ─── MOBILE & RESPONSIVE ────────────────────── */
.sb-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.4); z-index: 999;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  opacity: 0; transition: opacity 0.3s ease;
}
@media (max-width: 991px) {
  #sidebarToggle { display: flex; }
  .desktop-toggle { display: none; }
  /* In RTL, translating to positive 100% moves right. */
  #sidebar { transform: translateX(100%); width: 280px; box-shadow: none; }
  body.sidebar-collapsed #sidebar { transform: translateX(100%); width: 280px; }
  #sidebar.open { transform: translateX(0); box-shadow: -8px 0 32px rgba(0,0,0,0.15); }
  #main { margin-right: 0 !important; width: 100% !important; }
  body.sidebar-collapsed #main { margin-right: 0 !important; width: 100% !important; }
  .sb-overlay.open { display: block; opacity: 1; }
  #content { padding: 24px 16px; }
  
  #topbar { padding: 0 16px; }
  .topbar-chip { display: none; } /* hide date chip on small mobile */
  
  .stat-card { padding: 20px; flex-direction: column; align-items: flex-start; gap: 12px; }
  .stat-icon { width: 44px; height: 44px; font-size: 20px; }
  
  .finance-card { padding: 20px; }
  
  .table thead th { padding: 12px 16px !important; }
  .table tbody td { padding: 12px 16px !important; }
}
@media (max-width: 575px) {
  .page-header h4 { font-size: 22px; }
  .topbar-title { font-size: 16px; }
  .stat-value { font-size: 26px; }
  .finance-card .fc-value { font-size: 24px; }
  .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
  .page-header > div:last-child { width: 100%; }
  .page-header .btn { width: 100%; }
}
/* Mobile table: horizontal scroll */
.table-responsive { -webkit-overflow-scrolling: touch; }
.card .table-responsive, .card > .card-body > .table-responsive { overflow-x: auto; }
/* Safe area for notch phones */
#content { padding-bottom: calc(32px + env(safe-area-inset-bottom)); }
</style>
</head>
<body class="">

<!-- ═══ SIDEBAR ═══════════════════════════════════ -->
<aside id="sidebar">
  <div class="sb-brand">
    <div class="logo-icon"><i class="bi bi-building"></i></div>
    <div class="logo-text">
      <strong>نظام السقالات</strong>
      <span>ERP Management</span>
    </div>
  </div>

  <p class="sb-section">الرئيسية</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>index.php" class="<?= isActiveDash() ?>" title="لوحة التحكم">
        <div class="nav-icon"><i class="bi bi-grid-fill"></i></div>
        <div class="nav-text">لوحة التحكم</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/clients/index.php" class="<?= isActive('/clients/') ?>" title="العملاء">
        <div class="nav-icon"><i class="bi bi-people-fill"></i></div>
        <div class="nav-text">العملاء</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/equipment/index.php" class="<?= isActive('/equipment/') ?>" title="المعدات">
        <div class="nav-icon"><i class="bi bi-tools"></i></div>
        <div class="nav-text">المعدات</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/contracts/index.php" class="<?= isActive('/contracts/') ?>" title="العقود">
        <div class="nav-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
        <div class="nav-text">العقود</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">التقارير</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/reports/index.php" class="<?= isActive('/reports/') ?>" title="التقارير">
        <div class="nav-icon"><i class="bi bi-bar-chart-fill"></i></div>
        <div class="nav-text">التقارير والمخزون</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">المالية</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/invoices/index.php" class="<?= isActive('/invoices/') ?>" title="الفواتير">
        <div class="nav-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <div class="nav-text">الفواتير الإلكترونية</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/payments/index.php" class="<?= isActive('/payments/') ?>" title="المدفوعات">
        <div class="nav-icon"><i class="bi bi-cash-stack"></i></div>
        <div class="nav-text">المدفوعات</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/expenses/index.php" class="<?= isActive('/expenses/') ?>" title="المصروفات">
        <div class="nav-icon"><i class="bi bi-wallet2"></i></div>
        <div class="nav-text">المصروفات</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">الموارد البشرية</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/employees/index.php" class="<?= isActive('/employees/') ?>" title="الموظفون">
        <div class="nav-icon"><i class="bi bi-person-badge-fill"></i></div>
        <div class="nav-text">الموظفون</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">الإدارة</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/users/index.php" class="<?= isActive('/users/') ?>" title="إدارة المستخدمين">
        <div class="nav-icon"><i class="bi bi-people-fill"></i></div>
        <div class="nav-text">إدارة المستخدمين</div>
      </a>
    </li>
    <?php if (isAdmin()): ?>
    <li>
      <a href="<?= $base ?>modules/settings/index.php" class="<?= isActive('/settings/') ?>" title="إعدادات الشركة">
        <div class="nav-icon"><i class="bi bi-gear-fill"></i></div>
        <div class="nav-text">إعدادات الشركة</div>
      </a>
    </li>
    <?php endif; ?>
  </ul>

  <div class="sb-footer">
    <div class="sb-version"><i class="bi bi-circle-fill text-success" style="font-size:7px"></i> v1.0 &nbsp;·&nbsp; <?= date('Y') ?></div>
  </div>
</aside>

<!-- Overlay (mobile) -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- ═══ MAIN ════════════════════════════════════ -->
<div id="main">
  <header id="topbar">
    <div class="d-flex align-items-center">
      <button class="desktop-toggle" onclick="toggleDesktopSidebar()" title="تصغير/تكبير القائمة">
        <i class="bi bi-list"></i>
      </button>
      <button id="sidebarToggle" onclick="toggleSidebar()" title="القائمة">
        <i class="bi bi-list"></i>
      </button>
      <span class="topbar-title">نظام إدارة السقالات</span>
    </div>
    <div class="topbar-right">
      <div class="topbar-chip">
        <i class="bi bi-calendar3"></i>
        <?= date('d / m / Y') ?>
      </div>
      <div class="topbar-chip" style="gap:10px;">
        <i class="bi bi-person-circle" style="font-size:18px;color:var(--accent)"></i>
        <span style="font-size:13px;font-weight:700;"><?= htmlspecialchars(currentUserName()) ?></span>
        <?php if (isAdmin()): ?>
        <span style="font-size:10px;background:#dbeafe;color:#2563eb;padding:2px 8px;border-radius:20px;font-weight:800;">مدير</span>
        <?php endif; ?>
      </div>
      <button id="pwaInstallBtn" onclick="installPWA()" title="تثبيت التطبيق"
        style="display:none;align-items:center;gap:6px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:10px;padding:6px 14px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">
        <i class="bi bi-download"></i> <span class="d-none d-sm-inline">تثبيت</span>
      </button>
      <a href="<?= $base ?>logout.php" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:none;font-weight:700;border-radius:10px;" title="تسجيل الخروج">
        <i class="bi bi-box-arrow-left me-1"></i> خروج
      </a>
    </div>
  </header>

  <div id="content">
