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
  --sidebar-bg:     #070d1a;
  --sidebar-hover:  rgba(255,255,255,0.06);
  --sidebar-active: #2563eb;
  --sidebar-text:   #94a3b8;
  --sidebar-white:  #f1f5f9;
  --accent:         #2563eb;
  --accent-light:   #dbeafe;
  --surface:        #ffffff;
  --bg:             #f0f4f8;
  --border:         #e2e8f0;
  --text-main:      #0f172a;
  --text-muted:     #64748b;
  --sidebar-width:  268px;
  --sidebar-collapsed-width: 78px;
  --topbar-h:       68px;
  --radius:         14px;
  --radius-sm:      10px;
  --shadow-sm:      0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.03);
  --shadow-md:      0 4px 16px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.04);
  --shadow-lg:      0 12px 32px rgba(0,0,0,0.1), 0 4px 12px rgba(0,0,0,0.06);
  --shadow-xl:      0 24px 48px rgba(0,0,0,0.14), 0 8px 20px rgba(0,0,0,0.08);
  --transition:     all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
  font-family: 'Cairo', sans-serif;
  background: var(--bg);
  color: var(--text-main);
  min-height: 100vh;
  font-size: 14px;
  overflow-x: hidden;
}

/* Custom scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* ─── SIDEBAR ─────────────────────────────────── */
#sidebar {
  width: var(--sidebar-width);
  min-height: 100vh;
  background: linear-gradient(180deg, #0d1526 0%, #0a1020 60%, #080d18 100%);
  position: fixed;
  right: 0; top: 0; bottom: 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  transition: var(--transition);
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: none;
  border-left: 1px solid rgba(255,255,255,0.06);
}
#sidebar::-webkit-scrollbar { display: none; }

body.sidebar-collapsed #sidebar { width: var(--sidebar-collapsed-width); }
body.sidebar-collapsed #main { margin-right: var(--sidebar-collapsed-width); }

/* Brand */
.sb-brand {
  display: flex; align-items: center; gap: 14px;
  padding: 22px 18px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
  min-height: 80px;
  transition: var(--transition);
  white-space: nowrap;
  background: rgba(255,255,255,0.025);
}
body.sidebar-collapsed .sb-brand { padding: 20px 14px; justify-content: center; }
body.sidebar-collapsed .logo-text { opacity: 0; width: 0; display: none; }

.sb-brand .logo-icon {
  width: 48px; height: 48px;
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #fff;
  flex-shrink: 0;
  box-shadow: 0 6px 20px rgba(59,130,246,0.55);
  transition: var(--transition);
}
body.sidebar-collapsed .sb-brand .logo-icon { width: 40px; height: 40px; font-size: 18px; }
.sb-brand .logo-text { transition: var(--transition); }
.sb-brand .logo-text strong { display: block; font-size: 17px; font-weight: 900; line-height: 1.3; color: #f8fafc; letter-spacing: 0.3px; }
.sb-brand .logo-text span { font-size: 12px; color: #60a5fa; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }

/* Nav sections */
.sb-section {
  padding: 18px 18px 5px;
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #2d4a6a;
  white-space: nowrap;
  transition: var(--transition);
}
body.sidebar-collapsed .sb-section { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }

.sb-nav { list-style: none; padding: 0 8px; }
.sb-nav li { margin-bottom: 2px; }
.sb-nav a {
  display: grid;
  grid-template-columns: 38px 1fr;
  align-items: center;
  gap: 11px;
  padding: 8px 10px;
  border-radius: 11px;
  color: #8aa3c0;
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 700;
  transition: var(--transition);
  position: relative;
  white-space: nowrap;
  overflow: hidden;
}
.sb-nav a .nav-text { transition: var(--transition); text-align: right; }
body.sidebar-collapsed .sb-nav a { padding: 10px; grid-template-columns: 1fr; justify-content: center; }
body.sidebar-collapsed .sb-nav a .nav-text { opacity: 0; width: 0; display: none !important; }

/* Colored icons — each link has data-color */
.sb-nav a .nav-icon {
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  font-size: 16px;
  transition: var(--transition);
  flex-shrink: 0;
}
body.sidebar-collapsed .sb-nav a .nav-icon { width: 42px; height: 42px; font-size: 18px; border-radius: 12px; }

/* Individual icon colors */
.nav-icon.ic-blue   { background: rgba(59,130,246,0.15);  color: #60a5fa; }
.nav-icon.ic-green  { background: rgba(34,197,94,0.15);   color: #4ade80; }
.nav-icon.ic-orange { background: rgba(249,115,22,0.15);  color: #fb923c; }
.nav-icon.ic-purple { background: rgba(168,85,247,0.15);  color: #c084fc; }
.nav-icon.ic-cyan   { background: rgba(6,182,212,0.15);   color: #22d3ee; }
.nav-icon.ic-yellow { background: rgba(234,179,8,0.15);   color: #facc15; }
.nav-icon.ic-emerald{ background: rgba(16,185,129,0.15);  color: #34d399; }
.nav-icon.ic-rose   { background: rgba(244,63,94,0.15);   color: #fb7185; }
.nav-icon.ic-indigo { background: rgba(99,102,241,0.15);  color: #818cf8; }
.nav-icon.ic-amber  { background: rgba(245,158,11,0.15);  color: #fbbf24; }
.nav-icon.ic-slate  { background: rgba(148,163,184,0.12); color: #94a3b8; }

.sb-nav a:hover {
  background: rgba(255,255,255,0.06);
  color: #e2e8f0;
}
.sb-nav a:hover .nav-icon { filter: brightness(1.2); transform: scale(1.08); }
body.sidebar-collapsed .sb-nav a:hover { transform: scale(1.05); }

.sb-nav a.active {
  background: rgba(255,255,255,0.07);
  color: #f1f5f9;
}
.sb-nav a.active .nav-icon { filter: brightness(1.3); box-shadow: 0 3px 10px rgba(0,0,0,0.3); }
.sb-nav a.active::before {
  content: '';
  position: absolute;
  right: 0; top: 50%; transform: translateY(-50%);
  width: 3px; height: 22px;
  background: linear-gradient(180deg, #7dd3fc, #2563eb);
  border-radius: 3px 0 0 3px;
}
body.sidebar-collapsed .sb-nav a.active::before { display: none; }

/* Sidebar footer */
.sb-footer {
  margin-top: auto;
  padding: 14px 18px;
  border-top: 1px solid rgba(255,255,255,0.05);
  transition: var(--transition);
  white-space: nowrap;
}
body.sidebar-collapsed .sb-footer { opacity: 0; visibility: hidden; padding: 0; height: 0; display: none; }
.sb-version { font-size: 11px; color: #1e3a5a; text-align: center; font-weight: 700; }

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
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(226,232,240,0.8);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 28px;
  gap: 16px;
  position: sticky; top: 0; z-index: 900;
  box-shadow: 0 1px 0 rgba(0,0,0,0.04);
}
.desktop-toggle {
  background: transparent; border: none;
  font-size: 19px; color: var(--text-muted);
  cursor: pointer; width: 38px; height: 38px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 9px;
  transition: var(--transition);
  position: relative; z-index: 1050;
}
.desktop-toggle:hover { background: var(--bg); color: var(--accent); }
#sidebarToggle {
  display: none;
  background: transparent; border: none;
  font-size: 19px; color: var(--text-muted);
  cursor: pointer; width: 38px; height: 38px;
  align-items: center; justify-content: center;
  border-radius: 9px;
  transition: var(--transition);
}
#sidebarToggle:hover { background: var(--bg); color: var(--accent); }

.topbar-title {
  font-size: 16px; font-weight: 800;
  color: var(--text-main);
  margin-right: 8px;
}
.topbar-right { display: flex; align-items: center; gap: 10px; }

.topbar-chip {
  display: flex; align-items: center; gap: 7px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 5px 14px;
  font-size: 12.5px;
  color: var(--text-muted);
  font-weight: 600;
}
.topbar-chip i { font-size: 14px; color: var(--accent); }

/* User pill in topbar */
.user-pill {
  display: flex; align-items: center; gap: 9px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 22px;
  padding: 4px 14px 4px 6px;
  transition: var(--transition);
  cursor: default;
}
.user-pill:hover { border-color: #bfdbfe; background: #f0f7ff; }
.user-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; color: #fff; font-weight: 800;
}
.user-pill-name { font-size: 13px; font-weight: 700; color: var(--text-main); }
.user-pill-badge {
  font-size: 10px; background: linear-gradient(135deg,#dbeafe,#ede9fe);
  color: #4338ca; padding: 2px 8px; border-radius: 20px; font-weight: 800;
}

/* ─── CONTENT ────────────────────────────────── */
#content { padding: 28px 32px; flex: 1; }

/* ─── CARDS ──────────────────────────────────── */
.card {
  border: 1px solid rgba(226,232,240,0.7) !important;
  border-radius: var(--radius) !important;
  box-shadow: var(--shadow-md) !important;
  background: var(--surface);
  transition: var(--transition);
}
.card:hover { box-shadow: var(--shadow-lg) !important; }
.card-header {
  background: #fafbfc !important;
  border-bottom: 1px solid var(--border) !important;
  padding: 16px 22px !important;
  font-weight: 700;
  font-size: 15px;
  color: var(--text-main);
  border-radius: var(--radius) var(--radius) 0 0 !important;
}

/* Stat cards */
.stat-card {
  background: var(--surface);
  border: 1px solid rgba(226,232,240,0.7);
  border-radius: var(--radius);
  padding: 22px 24px;
  display: flex; align-items: center; gap: 18px;
  box-shadow: var(--shadow-md);
  transition: var(--transition);
  text-decoration: none; color: inherit;
  position: relative; overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; right: 0; left: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, rgba(37,99,235,0.15), transparent);
  opacity: 0;
  transition: var(--transition);
}
.stat-card:hover::before { opacity: 1; }
.stat-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-3px);
  color: inherit;
  border-color: #bfdbfe;
}
.stat-icon {
  width: 52px; height: 52px;
  border-radius: 13px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; flex-shrink: 0;
}
.stat-icon.blue   { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.15); }
.stat-icon.green  { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; box-shadow: 0 4px 12px rgba(22,163,74,0.15); }
.stat-icon.amber  { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; box-shadow: 0 4px 12px rgba(217,119,6,0.15); }
.stat-icon.purple { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); color: #9333ea; box-shadow: 0 4px 12px rgba(147,51,234,0.15); }
.stat-icon.rose   { background: linear-gradient(135deg, #ffe4e6, #fecdd3); color: #e11d48; box-shadow: 0 4px 12px rgba(225,29,72,0.15); }
.stat-icon.teal   { background: linear-gradient(135deg, #ccfbf1, #99f6e4); color: #0d9488; box-shadow: 0 4px 12px rgba(13,148,136,0.15); }

.stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 4px; letter-spacing: 0.3px; }
.stat-value { font-size: 30px; font-weight: 800; line-height: 1; color: var(--text-main); }
.stat-sub   { font-size: 11.5px; color: var(--text-muted); margin-top: 5px; font-weight: 500; }

/* Finance cards */
.finance-card {
  background: var(--surface);
  border: 1px solid rgba(226,232,240,0.7);
  border-radius: var(--radius);
  padding: 22px 24px;
  box-shadow: var(--shadow-md);
  transition: var(--transition);
}
.finance-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
.finance-card .fc-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; }
.finance-card .fc-value { font-size: 28px; font-weight: 800; }
.finance-card .fc-icon { width: 48px; height: 48px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

/* Tables */
.table { font-size: 13.5px; margin-bottom: 0; }
.table thead th {
  font-weight: 700;
  font-size: 11.5px;
  letter-spacing: .8px;
  color: var(--text-muted);
  text-transform: uppercase;
  background: #fafbfc !important;
  border-bottom: 1px solid var(--border) !important;
  border-top: none !important;
  padding: 13px 22px !important;
  white-space: nowrap;
}
.table tbody td {
  padding: 14px 22px !important;
  vertical-align: middle;
  border-bottom: 1px solid #f1f5f9 !important;
  color: var(--text-main);
  font-weight: 500;
}
.table tbody tr { transition: background 0.15s; }
.table tbody tr:hover { background: #f8faff; }
.table tbody tr:last-child td { border-bottom: none !important; }
.table-striped tbody tr:nth-of-type(even) { background: #fafbfc; }

/* Badges */
.badge { font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.2px; }

/* Buttons */
.btn { font-family: 'Cairo', sans-serif; font-weight: 700; border-radius: var(--radius-sm); font-size: 13.5px; padding: 8px 18px; transition: var(--transition); letter-spacing: 0.2px; }
.btn:active { transform: scale(0.97) !important; }
.btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; color: #fff; box-shadow: 0 3px 10px rgba(37,99,235,0.35); }
.btn-primary:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); transform: translateY(-1px); box-shadow: 0 5px 14px rgba(37,99,235,0.4); color: #fff; }
.btn-success { background: linear-gradient(135deg, #16a34a, #15803d); border: none; color: #fff; box-shadow: 0 3px 10px rgba(22,163,74,0.3); }
.btn-success:hover { background: linear-gradient(135deg, #15803d, #166534); transform: translateY(-1px); box-shadow: 0 5px 14px rgba(22,163,74,0.35); color: #fff; }
.btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); border: none; color: #fff; box-shadow: 0 3px 10px rgba(220,38,38,0.3); }
.btn-danger:hover { background: linear-gradient(135deg, #b91c1c, #991b1b); transform: translateY(-1px); color: #fff; }
.btn-outline-primary { border: 1.5px solid #bfdbfe; color: #2563eb; background: transparent; }
.btn-outline-primary:hover { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; transform: translateY(-1px); }
.btn-outline-danger { border: 1.5px solid #fecdd3; color: #dc2626; background: transparent; }
.btn-outline-danger:hover { background: #fff1f2; border-color: #fca5a5; color: #b91c1c; transform: translateY(-1px); }
.btn-outline-secondary { border: 1.5px solid var(--border); color: var(--text-muted); background: transparent; }
.btn-outline-secondary:hover { background: var(--bg); color: var(--text-main); transform: translateY(-1px); }
.btn-sm { padding: 5px 12px; font-size: 12.5px; border-radius: 8px; }

/* Form inputs */
.form-control, .form-select {
  font-family: 'Cairo', sans-serif;
  font-size: 13.5px;
  border: 1.5px solid #e2e8f0;
  border-radius: var(--radius-sm);
  padding: 9px 14px;
  color: var(--text-main);
  background: #fff;
  transition: var(--transition);
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.form-control:focus, .form-select:focus {
  border-color: #93c5fd;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
  outline: none;
}
.form-label { font-weight: 700; font-size: 12.5px; color: var(--text-muted); margin-bottom: 6px; letter-spacing: 0.2px; }
.input-group-text { font-family: 'Cairo', sans-serif; font-size: 13px; border: 1.5px solid #e2e8f0; background: #f8fafc; color: var(--text-muted); }

/* Alerts */
.alert { border-radius: var(--radius-sm); border: none; font-weight: 600; font-size: 13.5px; padding: 12px 18px; box-shadow: var(--shadow-sm); }
.alert-success { background: #f0fdf4; color: #15803d; border-right: 3px solid #4ade80; }
.alert-danger  { background: #fff1f2; color: #b91c1c; border-right: 3px solid #f87171; }
.alert-warning { background: #fffbeb; color: #b45309; border-right: 3px solid #fbbf24; }
.alert-info    { background: #eff6ff; color: #1d4ed8; border-right: 3px solid #60a5fa; }

/* Page header */
.page-header { margin-bottom: 28px; }
.page-header h4 {
  font-size: 24px; font-weight: 800;
  color: var(--text-main); margin: 0;
  letter-spacing: -0.5px;
}
.page-header p { color: var(--text-muted); font-size: 13px; margin: 4px 0 0; font-weight: 500; }

/* Modals */
.modal-content { border: none !important; border-radius: var(--radius) !important; box-shadow: var(--shadow-xl) !important; }
.modal-header { padding: 20px 24px !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
.modal-body { padding: 24px !important; }
.modal-footer { padding: 16px 24px !important; border-top: 1px solid var(--border) !important; background: #fafbfc; border-radius: 0 0 var(--radius) var(--radius); }
.modal-backdrop { backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }

/* Fix Bootstrap RTL select */
select { direction: rtl !important; text-align: right !important; text-align-last: right !important; }
select option { color: #1e293b !important; background: #ffffff !important; direction: rtl !important; text-align: right !important; }

/* ─── MOBILE & RESPONSIVE ────────────────────── */
.sb-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(7,13,26,0.5); z-index: 999;
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  opacity: 0; transition: opacity 0.25s ease;
}
@media (max-width: 991px) {
  #sidebarToggle { display: flex; }
  .desktop-toggle { display: none; }
  #sidebar { transform: translateX(100%); width: 280px; box-shadow: none; }
  body.sidebar-collapsed #sidebar { transform: translateX(100%); width: 280px; }
  #sidebar.open { transform: translateX(0); box-shadow: -8px 0 40px rgba(0,0,0,0.2); }
  #main { margin-right: 0 !important; width: 100% !important; }
  body.sidebar-collapsed #main { margin-right: 0 !important; width: 100% !important; }
  .sb-overlay.open { display: block; opacity: 1; }
  #content { padding: 20px 16px; }
  #topbar { padding: 0 16px; }
  .topbar-chip { display: none; }
  .stat-card { padding: 18px; gap: 14px; }
  .stat-icon { width: 46px; height: 46px; font-size: 20px; }
  .finance-card { padding: 18px; }
  .table thead th { padding: 11px 14px !important; }
  .table tbody td { padding: 11px 14px !important; }
}
@media (max-width: 575px) {
  .page-header h4 { font-size: 20px; }
  .topbar-title { font-size: 15px; }
  .stat-value { font-size: 24px; }
  .finance-card .fc-value { font-size: 22px; }
  .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
  .page-header > div:last-child { width: 100%; }
  .page-header .btn { width: 100%; }
  .user-pill-name { display: none; }
}
.table-responsive { -webkit-overflow-scrolling: touch; }
.card .table-responsive { overflow-x: auto; }
#content { padding-bottom: calc(28px + env(safe-area-inset-bottom)); }
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
        <div class="nav-icon ic-blue"><i class="bi bi-speedometer2"></i></div>
        <div class="nav-text">لوحة التحكم</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/clients/index.php" class="<?= isActive('/clients/') ?>" title="العملاء">
        <div class="nav-icon ic-emerald"><i class="bi bi-people-fill"></i></div>
        <div class="nav-text">العملاء</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/equipment/index.php" class="<?= isActive('/equipment/') ?>" title="المعدات">
        <div class="nav-icon ic-orange"><i class="bi bi-wrench-adjustable-circle-fill"></i></div>
        <div class="nav-text">المعدات</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/contracts/index.php" class="<?= isActive('/contracts/') ?>" title="العقود">
        <div class="nav-icon ic-purple"><i class="bi bi-file-earmark-text-fill"></i></div>
        <div class="nav-text">العقود</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">التقارير</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/reports/index.php" class="<?= isActive('/reports/') ?>" title="التقارير">
        <div class="nav-icon ic-cyan"><i class="bi bi-bar-chart-line-fill"></i></div>
        <div class="nav-text">التقارير والمخزون</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">المالية</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/invoices/index.php" class="<?= isActive('/invoices/') ?>" title="الفواتير">
        <div class="nav-icon ic-yellow"><i class="bi bi-receipt-cutoff"></i></div>
        <div class="nav-text">الفواتير الإلكترونية</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/payments/index.php" class="<?= isActive('/payments/') ?>" title="المدفوعات">
        <div class="nav-icon ic-green"><i class="bi bi-cash-coin"></i></div>
        <div class="nav-text">المدفوعات</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/expenses/index.php" class="<?= isActive('/expenses/') ?>" title="المصروفات">
        <div class="nav-icon ic-rose"><i class="bi bi-wallet2"></i></div>
        <div class="nav-text">المصروفات</div>
      </a>
    </li>
  </ul>

  <p class="sb-section">الموارد البشرية</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/employees/index.php" class="<?= isActive('/employees/') ?>" title="الموظفون">
        <div class="nav-icon ic-indigo"><i class="bi bi-person-badge-fill"></i></div>
        <div class="nav-text">الموظفون</div>
      </a>
    </li>
  </ul>

  <?php if (isAdmin()): ?>
  <p class="sb-section">الإدارة</p>
  <ul class="sb-nav">
    <li>
      <a href="<?= $base ?>modules/users/index.php" class="<?= isActive('/users/') ?>" title="إدارة المستخدمين">
        <div class="nav-icon ic-amber"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="nav-text">إدارة المستخدمين</div>
      </a>
    </li>
    <li>
      <a href="<?= $base ?>modules/settings/index.php" class="<?= isActive('/settings/') ?>" title="إعدادات الشركة">
        <div class="nav-icon ic-slate"><i class="bi bi-gear-wide-connected"></i></div>
        <div class="nav-text">إعدادات الشركة</div>
      </a>
    </li>
  </ul>
  <?php endif; ?>

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
      <div class="user-pill">
        <div class="user-avatar"><?= mb_substr(currentUserName(), 0, 1) ?></div>
        <span class="user-pill-name"><?= htmlspecialchars(currentUserName()) ?></span>
        <?php if (isAdmin()): ?>
        <span class="user-pill-badge">مدير</span>
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
