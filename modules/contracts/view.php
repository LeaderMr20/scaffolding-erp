<?php
include '../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Add payment
if (isset($_POST['add_payment'])) {
    $stmt = $conn->prepare("INSERT INTO payments(contract_id, amount, payment_date, method) VALUES(?, ?, ?, ?)");
    $stmt->bind_param('idss', $id, $_POST['amount'], $_POST['payment_date'], $_POST['method']);
    $stmt->execute();
    header("Location: view.php?id=$id&paid=1");
    exit;
}

// Delete payment (admin only)
if (isset($_GET['del_pay'])) {
    require_once '../../config/auth.php';
    if (!isAdmin()) { header("Location: view.php?id=$id"); exit; }
    $stmt = $conn->prepare("DELETE FROM payments WHERE id=? AND contract_id=?");
    $stmt->bind_param('ii', $_GET['del_pay'], $id);
    $stmt->execute();
    header("Location: view.php?id=$id");
    exit;
}

$contract = $conn->query("
    SELECT c.*, cl.name client_name, cl.phone client_phone, cl.address client_address
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    WHERE c.id = $id
")->fetch_assoc();

if (!$contract) { header('Location: index.php'); exit; }

$items    = $conn->query("SELECT ci.*, e.name equip_name FROM contract_items ci JOIN equipment e ON ci.equipment_id=e.id WHERE ci.contract_id=$id");
$payments = $conn->query("SELECT * FROM payments WHERE contract_id=$id ORDER BY payment_date DESC");
$paid     = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE contract_id=$id")->fetch_assoc()['s'];
$days     = max(1, (strtotime($contract['end_date']) - strtotime($contract['start_date'])) / 86400);

include '../../templates/header.php';
?>

<?php if (isset($_GET['new'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i> تم إنشاء العقد بنجاح! <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['paid'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i> تم تسجيل الدفعة بنجاح. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-file-earmark-text text-warning me-2"></i> العقد رقم #<?= $id ?></h4>
  <div class="d-flex gap-2">
    <a href="print.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">
      <i class="bi bi-printer me-1"></i> طباعة
    </a>
    <a href="index.php" class="btn btn-outline-dark">
      <i class="bi bi-arrow-right me-1"></i> رجوع
    </a>
  </div>
</div>

<!-- Contract Info + Financial Summary -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><strong><i class="bi bi-info-circle me-1"></i> بيانات العقد</strong></div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr><td class="text-muted w-40">العميل</td><td><strong><?= htmlspecialchars($contract['client_name']) ?></strong></td></tr>
          <tr><td class="text-muted">الجوال</td><td><?= htmlspecialchars($contract['client_phone']) ?></td></tr>
          <tr><td class="text-muted">العنوان</td><td><?= htmlspecialchars($contract['client_address']) ?></td></tr>
          <tr><td class="text-muted">تاريخ البداية</td><td><?= $contract['start_date'] ?></td></tr>
          <tr><td class="text-muted">تاريخ الانتهاء</td><td><?= $contract['end_date'] ?></td></tr>
          <tr><td class="text-muted">المدة</td><td><?= $days ?> يوم</td></tr>
          <tr>
            <td class="text-muted">الحالة</td>
            <td>
              <span class="badge bg-<?= $contract['status'] == 'active' ? 'success' : 'secondary' ?> fs-6">
                <?= $contract['status'] == 'active' ? 'نشط' : 'منتهي' ?>
              </span>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><strong><i class="bi bi-cash-stack me-1"></i> الملخص المالي</strong></div>
      <div class="card-body">
        <table class="table table-borderless mb-3">
          <tr>
            <td class="text-muted">إجمالي العقد</td>
            <td class="fs-5 fw-bold"><?= number_format($contract['total'], 2) ?> ر.س</td>
          </tr>
          <tr>
            <td class="text-muted">المبلغ المدفوع</td>
            <td class="fs-5 fw-bold text-success"><?= number_format($paid, 2) ?> ر.س</td>
          </tr>
          <tr>
            <td class="text-muted">المتبقي</td>
            <td class="fs-5 fw-bold <?= ($contract['total'] - $paid) > 0 ? 'text-danger' : 'text-success' ?>">
              <?= number_format($contract['total'] - $paid, 2) ?> ر.س
            </td>
          </tr>
        </table>
        <?php $pct = $contract['total'] > 0 ? min(100, round($paid / $contract['total'] * 100)) : 0; ?>
        <div class="progress" style="height:20px">
          <div class="progress-bar bg-success" style="width:<?= $pct ?>%">
            <?= $pct ?>% مدفوع
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Equipment Items -->
<div class="card mb-4">
  <div class="card-header">
    <strong><i class="bi bi-tools me-1"></i> بنود المعدات</strong>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead class="table-light">
        <tr><th>المعدة</th><th>الكمية</th><th>السعر/اليوم</th><th>الأيام</th><th>الإجمالي الفرعي</th></tr>
      </thead>
      <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($item['equip_name']) ?></td>
          <td><?= $item['qty'] ?></td>
          <td><?= number_format($item['price'], 2) ?> ر.س</td>
          <td><?= $days ?></td>
          <td class="fw-bold"><?= number_format($item['qty'] * $item['price'] * $days, 2) ?> ر.س</td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Payments Section -->
<div class="row g-3">
  <div class="col-md-7">
    <div class="card">
      <div class="card-header">
        <strong><i class="bi bi-receipt me-1"></i> سجل الدفعات</strong>
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>التاريخ</th><th>المبلغ</th><th>طريقة الدفع</th><th></th></tr>
          </thead>
          <tbody>
            <?php
            $has_payments = false;
            while ($pay = $payments->fetch_assoc()):
                $has_payments = true;
            ?>
            <tr>
              <td><?= $pay['id'] ?></td>
              <td><?= $pay['payment_date'] ?></td>
              <td class="text-success fw-bold"><?= number_format($pay['amount'], 2) ?> ر.س</td>
              <td><?= htmlspecialchars($pay['method']) ?></td>
              <td>
                <?php if (isAdmin()): ?>
                <a href="?id=<?= $id ?>&del_pay=<?= $pay['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('هل تريد حذف هذه الدفعة؟')">
                  <i class="bi bi-trash"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_payments): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">لا توجد دفعات مسجلة بعد.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card">
      <div class="card-header bg-success text-white">
        <strong><i class="bi bi-plus-circle me-1"></i> تسجيل دفعة</strong>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="mb-2">
            <label class="form-label">المبلغ (ر.س)</label>
            <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required
                   placeholder="أدخل المبلغ">
          </div>
          <div class="mb-2">
            <label class="form-label">تاريخ الدفعة</label>
            <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">طريقة الدفع</label>
            <select name="method" class="form-select">
              <option>نقدي</option>
              <option>تحويل بنكي</option>
              <option>شيك</option>
            </select>
          </div>
          <button name="add_payment" class="btn btn-success w-100">
            <i class="bi bi-save me-1"></i> تسجيل الدفعة
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
