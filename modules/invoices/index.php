<?php
include '../../config/db.php';

$search = '';
$where  = "WHERE 1";
if (!empty($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $where  = "WHERE i.invoice_number LIKE '%$search%' OR cl.name LIKE '%$search%'";
}

$status_filter = $_GET['status'] ?? '';
if ($status_filter === 'active')    $where .= " AND i.status='active'";
if ($status_filter === 'cancelled') $where .= " AND i.status='cancelled'";

$invoices = $conn->query("
    SELECT i.*, c.start_date, c.end_date, cl.name client_name,
           COALESCE((SELECT SUM(amount) FROM payments WHERE contract_id=c.id),0) paid
    FROM invoices i
    JOIN contracts c  ON i.contract_id = c.id
    JOIN clients  cl  ON c.client_id   = cl.id
    $where
    ORDER BY i.id DESC
");

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-3">
    <div class="stat-icon green" style="width:48px;height:48px;font-size:20px"><i class="bi bi-receipt-cutoff"></i></div>
    <div>
      <h4 class="mb-1">الفواتير الإلكترونية</h4>
      <p class="mb-0 text-muted">سجل الفواتير المنشأة تلقائياً من العقود</p>
    </div>
  </div>
</div>

<!-- Filters -->
<form class="mb-4" method="get">
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <div class="input-group shadow-sm" style="max-width:320px;border-radius:12px;overflow:hidden">
      <span class="input-group-text bg-white border-0 ps-3 pe-2"><i class="bi bi-search text-muted"></i></span>
      <input name="q" value="<?= htmlspecialchars($search) ?>" class="form-control border-0 shadow-none bg-white" placeholder="رقم الفاتورة أو اسم العميل...">
    </div>
    <select name="status" class="form-select shadow-sm" style="max-width:160px;border-radius:10px">
      <option value="">الكل</option>
      <option value="active"    <?= $status_filter==='active'    ? 'selected':'' ?>>نشطة</option>
      <option value="cancelled" <?= $status_filter==='cancelled' ? 'selected':'' ?>>ملغاة</option>
    </select>
    <button class="btn btn-primary shadow-sm"><i class="bi bi-funnel me-1"></i> تصفية</button>
    <?php if ($search || $status_filter): ?>
    <a href="index.php" class="btn btn-outline-secondary">مسح</a>
    <?php endif; ?>
  </div>
</form>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>رقم الفاتورة</th>
            <th>العميل</th>
            <th>تاريخ الإصدار</th>
            <th>فترة العقد</th>
            <th>الإجمالي</th>
            <th>المدفوع</th>
            <th>الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $has = false;
          while ($row = $invoices->fetch_assoc()):
              $has = true;
              $isActive = $row['status'] === 'active';
              $remaining = $row['total'] - $row['paid'];
          ?>
          <tr>
            <td>
              <span class="fw-bold" style="color:var(--accent);font-family:monospace;font-size:13px">
                <?= htmlspecialchars($row['invoice_number']) ?>
              </span>
            </td>
            <td><strong><?= htmlspecialchars($row['client_name']) ?></strong></td>
            <td><?= $row['issue_date'] ?></td>
            <td class="text-muted small"><?= $row['start_date'] ?> ← <?= $row['end_date'] ?></td>
            <td class="fw-bold"><?= number_format($row['total'], 2) ?> <span class="text-muted small">ر.س</span></td>
            <td class="text-success fw-bold"><?= number_format($row['paid'], 2) ?></td>
            <td>
              <?php if ($isActive): ?>
              <span class="badge" style="background:var(--accent-light);color:var(--accent)">نشطة</span>
              <?php else: ?>
              <span class="badge" style="background:#fee2e2;color:#dc2626">ملغاة</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary" title="عرض وطباعة">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-5">
              <i class="bi bi-receipt fs-3 d-block mb-2"></i>
              لا توجد فواتير بعد
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
