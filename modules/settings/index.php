<?php
include '../../config/db.php';
include '../../config/auth.php';
requireLogin();
if (!isAdmin()) { header('Location: ../../index.php'); exit; }

$success = '';
$error   = '';

// ── Save company settings ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $fields = ['company_name', 'vat_number', 'company_phone', 'company_address', 'commercial_registration'];
    foreach ($fields as $field) {
        $val = $conn->real_escape_string(trim($_POST[$field] ?? ''));
        $conn->query("INSERT INTO settings(setting_key,setting_value) VALUES('$field','$val')
                      ON DUPLICATE KEY UPDATE setting_value='$val'");
    }
    $success = 'تم حفظ إعدادات الشركة بنجاح';
}

// ── Reset system ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_system') {
    $confirm = trim($_POST['confirm_word'] ?? '');
    if ($confirm === 'إعادة ضبط') {
        // Clear all operational data — keep users, equipment, settings, expense_categories
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE invoices");
        $conn->query("TRUNCATE TABLE payments");
        $conn->query("TRUNCATE TABLE contract_items");
        $conn->query("TRUNCATE TABLE contracts");
        $conn->query("TRUNCATE TABLE clients");
        $conn->query("TRUNCATE TABLE employees");
        $conn->query("TRUNCATE TABLE expenses");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        // Reset invoice counter in settings if exists
        $conn->query("DELETE FROM settings WHERE setting_key='invoice_counter'");
        $success = 'تمت إعادة ضبط النظام بنجاح — تم الاحتفاظ بالمستخدمين والمعدات والفئات';
    } else {
        $error = 'كلمة التأكيد غير صحيحة. اكتب: إعادة ضبط';
    }
}

// ── Load settings ──────────────────────────────────
$s = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($res) while ($row = $res->fetch_assoc()) $s[$row['setting_key']] = $row['setting_value'];

// ── Stats for reset preview ────────────────────────
$stats = [];
foreach (['clients'=>'العملاء','contracts'=>'العقود','payments'=>'الدفعات',
          'employees'=>'الموظفين','expenses'=>'المصروفات','invoices'=>'الفواتير'] as $tbl => $label) {
    $r = $conn->query("SELECT COUNT(*) c FROM $tbl");
    $stats[$label] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

$activeTab = $_GET['tab'] ?? 'company';

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-gear-fill text-primary me-2"></i>الإعدادات</h4>
    <p>إعدادات المنشأة وإدارة النظام</p>
  </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-4" style="border-radius:12px">
  <i class="bi bi-check-circle-fill fs-5"></i> <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:12px">
  <i class="bi bi-x-circle-fill fs-5"></i> <?= $error ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" style="border-bottom:2px solid #e2e8f0">
  <li class="nav-item">
    <a class="nav-link <?= $activeTab==='company' ? 'active fw-bold' : '' ?>" href="?tab=company">
      <i class="bi bi-building me-1"></i> بيانات المنشأة
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab==='reset' ? 'active fw-bold text-danger' : 'text-danger' ?>" href="?tab=reset">
      <i class="bi bi-arrow-counterclockwise me-1"></i> إعادة ضبط النظام
    </a>
  </li>
</ul>

<?php if ($activeTab === 'company'): ?>
<!-- ══════════ COMPANY SETTINGS ══════════ -->
<div class="card">
  <div class="card-header">
    <i class="bi bi-building me-2 text-primary"></i>بيانات المنشأة
  </div>
  <div class="card-body p-4">
    <form method="post">
      <input type="hidden" name="action" value="save_settings">
      <div class="row g-4">

        <div class="col-md-6">
          <label class="form-label fw-bold">
            <i class="bi bi-building me-1 text-primary"></i>اسم المنشأة <span class="text-danger">*</span>
          </label>
          <input type="text" name="company_name" class="form-control"
                 value="<?= htmlspecialchars($s['company_name'] ?? '') ?>"
                 placeholder="مثال: مؤسسة الجود للسقالات" required>
          <div class="form-text">يظهر في رأس الفاتورة الإلكترونية</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">
            <i class="bi bi-percent me-1 text-warning"></i>الرقم الضريبي (VAT)
          </label>
          <input type="text" name="vat_number" class="form-control" dir="ltr"
                 value="<?= htmlspecialchars($s['vat_number'] ?? '') ?>"
                 placeholder="15 رقم يبدأ بـ 3" maxlength="15">
          <div class="form-text">يُشفَّر داخل QR Code لهيئة الزكاة والدخل</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">
            <i class="bi bi-telephone me-1 text-success"></i>رقم الهاتف
          </label>
          <input type="text" name="company_phone" class="form-control" dir="ltr"
                 value="<?= htmlspecialchars($s['company_phone'] ?? '') ?>"
                 placeholder="05xxxxxxxx">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">
            <i class="bi bi-card-list me-1 text-info"></i>رقم السجل التجاري
          </label>
          <input type="text" name="commercial_registration" class="form-control" dir="ltr"
                 value="<?= htmlspecialchars($s['commercial_registration'] ?? '') ?>"
                 placeholder="10 أرقام">
        </div>

        <div class="col-12">
          <label class="form-label fw-bold">
            <i class="bi bi-geo-alt me-1 text-danger"></i>العنوان
          </label>
          <input type="text" name="company_address" class="form-control"
                 value="<?= htmlspecialchars($s['company_address'] ?? '') ?>"
                 placeholder="المدينة، الحي، الشارع">
        </div>

      </div>
      <div class="mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary px-5">
          <i class="bi bi-save me-2"></i>حفظ الإعدادات
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Invoice preview -->
<div class="card mt-4">
  <div class="card-header">
    <i class="bi bi-eye me-2 text-secondary"></i>معاينة — كيف تظهر في الفاتورة
  </div>
  <div class="card-body p-4">
    <div style="background:#0b162c;color:#fff;border-radius:12px;padding:24px 32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
      <div>
        <div style="font-size:20px;font-weight:900"><?= htmlspecialchars($s['company_name'] ?? 'اسم المنشأة') ?></div>
        <?php if (!empty($s['company_address'])): ?>
        <div style="font-size:12px;color:#93c5fd;margin-top:4px"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($s['company_address']) ?></div>
        <?php endif; ?>
        <?php if (!empty($s['company_phone'])): ?>
        <div style="font-size:12px;color:#93c5fd;margin-top:2px"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['company_phone']) ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:#fbbf24;margin-top:6px;font-weight:700">فاتورة إلكترونية مبسطة</div>
      </div>
      <div style="text-align:left">
        <div style="font-size:11px;color:#64748b">الرقم الضريبي</div>
        <div style="font-size:13px;color:#fff;font-weight:700;direction:ltr"><?= htmlspecialchars($s['vat_number'] ?? '—') ?></div>
        <?php if (!empty($s['commercial_registration'])): ?>
        <div style="font-size:11px;color:#64748b;margin-top:6px">السجل التجاري</div>
        <div style="font-size:13px;color:#fff;font-weight:700;direction:ltr"><?= htmlspecialchars($s['commercial_registration']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════ RESET TAB ══════════ -->

<!-- Warning banner -->
<div class="alert d-flex align-items-start gap-3 mb-4" style="background:#fef2f2;border:2px solid #fca5a5;border-radius:12px;color:#7f1d1d">
  <i class="bi bi-exclamation-triangle-fill fs-4 mt-1" style="color:#dc2626;flex-shrink:0"></i>
  <div>
    <div class="fw-bold fs-6 mb-1">تحذير — هذه العملية لا يمكن التراجع عنها</div>
    <div>سيتم حذف جميع بيانات العمليات نهائياً. <strong>سيتم الاحتفاظ بالمستخدمين والمعدات وفئات المصروفات والإعدادات فقط.</strong></div>
  </div>
</div>

<!-- Current data stats -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-bar-chart me-2 text-secondary"></i>البيانات الحالية التي ستُحذف</div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $icons  = ['العملاء'=>'bi-people','العقود'=>'bi-file-text','الدفعات'=>'bi-cash-coin',
                 'الموظفين'=>'bi-person-badge','المصروفات'=>'bi-wallet2','الفواتير'=>'bi-receipt'];
      $colors = ['العملاء'=>'#2563eb','العقود'=>'#7c3aed','الدفعات'=>'#059669',
                 'الموظفين'=>'#d97706','المصروفات'=>'#dc2626','الفواتير'=>'#0891b2'];
      foreach ($stats as $label => $count):
        $color = $colors[$label];
        $icon  = $icons[$label];
      ?>
      <div class="col-md-4 col-6">
        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:#f8fafc;border:1.5px solid #e2e8f0">
          <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>20;display:flex;align-items:center;justify-content:center">
            <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:18px"></i>
          </div>
          <div>
            <div class="fw-bold" style="font-size:20px;color:<?= $count > 0 ? '#dc2626' : '#64748b' ?>"><?= $count ?></div>
            <div class="text-muted small"><?= $label ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- What stays -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-shield-check me-2 text-success"></i>ما سيُحتفظ به بعد إعادة الضبط</div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $kept = [
          ['icon'=>'bi-people-fill','label'=>'المستخدمين','color'=>'#059669'],
          ['icon'=>'bi-box-seam','label'=>'المعدات والأسعار','color'=>'#2563eb'],
          ['icon'=>'bi-tags-fill','label'=>'فئات المصروفات','color'=>'#7c3aed'],
          ['icon'=>'bi-gear-fill','label'=>'إعدادات الشركة','color'=>'#d97706'],
      ];
      foreach ($kept as $k):
      ?>
      <div class="col-md-3 col-6">
        <div class="d-flex align-items-center gap-2 p-3 rounded-3" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
          <i class="bi <?= $k['icon'] ?>" style="color:<?= $k['color'] ?>;font-size:20px"></i>
          <span class="fw-bold small" style="color:<?= $k['color'] ?>"><?= $k['label'] ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Reset form -->
<div class="card" style="border:2px solid #fca5a5">
  <div class="card-header" style="background:#fef2f2">
    <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>
    <span class="fw-bold text-danger">تنفيذ إعادة الضبط</span>
  </div>
  <div class="card-body p-4">
    <p class="text-muted mb-4">
      لتأكيد العملية، اكتب <strong style="color:#dc2626;background:#fee2e2;padding:2px 8px;border-radius:6px">إعادة ضبط</strong> في الحقل أدناه ثم اضغط الزر.
    </p>
    <button class="btn btn-danger px-5" onclick="document.getElementById('resetConfirmModal').style.display='flex'">
      <i class="bi bi-arrow-counterclockwise me-2"></i>إعادة ضبط النظام
    </button>
  </div>
</div>

<!-- Confirm Modal (pure CSS, no Bootstrap needed) -->
<div id="resetConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:32px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
    <div class="text-center mb-3">
      <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:28px;color:#dc2626"></i>
      </div>
      <h5 class="fw-bold text-danger mb-1">تأكيد إعادة الضبط</h5>
      <p class="text-muted small mb-0">هذا الإجراء نهائي ولا يمكن التراجع عنه</p>
    </div>

    <form method="post" id="resetForm">
      <input type="hidden" name="action" value="reset_system">
      <div class="mb-3">
        <label class="form-label fw-bold text-center d-block">
          اكتب <span style="color:#dc2626">إعادة ضبط</span> للتأكيد
        </label>
        <input type="text" name="confirm_word" id="confirmInput" class="form-control text-center"
               placeholder="إعادة ضبط" autocomplete="off"
               oninput="checkConfirm(this.value)">
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary flex-fill"
                onclick="document.getElementById('resetConfirmModal').style.display='none'">
          إلغاء
        </button>
        <button type="submit" id="confirmBtn" class="btn btn-danger flex-fill" disabled>
          <i class="bi bi-arrow-counterclockwise me-1"></i>تأكيد إعادة الضبط
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function checkConfirm(val) {
    document.getElementById('confirmBtn').disabled = (val.trim() !== 'إعادة ضبط');
}
</script>

<?php endif; ?>

<?php include '../../templates/footer.php'; ?>
