<?php
include '../../config/db.php';
include '../../config/auth.php';
requireLogin();
if (!isAdmin()) { header('Location: ../../index.php'); exit; }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['company_name', 'vat_number', 'company_phone', 'company_address', 'commercial_registration'];
    foreach ($fields as $field) {
        $val = $conn->real_escape_string(trim($_POST[$field] ?? ''));
        $conn->query("UPDATE settings SET setting_value='$val' WHERE setting_key='$field'");
    }
    $success = 'تم حفظ إعدادات الشركة بنجاح';
}

// Load current values
$s = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $res->fetch_assoc()) {
    $s[$row['setting_key']] = $row['setting_value'];
}

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-gear-fill text-primary me-2"></i>إعدادات الشركة</h4>
    <p>بيانات المنشأة التي تظهر في الفواتير الإلكترونية</p>
  </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-4" style="border-radius:12px">
  <i class="bi bi-check-circle-fill fs-5"></i> <?= $success ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <i class="bi bi-building me-2 text-primary"></i>بيانات المنشأة
  </div>
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-4">

        <div class="col-md-6">
          <label class="form-label fw-bold">
            <i class="bi bi-building me-1 text-primary"></i>اسم المنشأة
            <span class="text-danger">*</span>
          </label>
          <input type="text" name="company_name" class="form-control"
                 value="<?= htmlspecialchars($s['company_name'] ?? '') ?>" required
                 placeholder="مثال: مؤسسة الجود للسقالات">
          <div class="form-text">يظهر في رأس الفاتورة الإلكترونية</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">
            <i class="bi bi-percent me-1 text-warning"></i>الرقم الضريبي (VAT)
            <span class="text-danger">*</span>
          </label>
          <input type="text" name="vat_number" class="form-control" dir="ltr"
                 value="<?= htmlspecialchars($s['vat_number'] ?? '') ?>"
                 placeholder="15 رقم يبدأ بـ 3" maxlength="15" minlength="15">
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

<!-- Preview box -->
<div class="card mt-4">
  <div class="card-header">
    <i class="bi bi-eye me-2 text-secondary"></i>معاينة — كيف ستظهر في الفاتورة
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

<?php include '../../templates/footer.php'; ?>
