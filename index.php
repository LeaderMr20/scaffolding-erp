<?php
include 'config/db.php';
include 'templates/header.php';

$clients      = $conn->query("SELECT COUNT(*) c FROM clients")->fetch_assoc()['c'];
$equipment    = $conn->query("SELECT COUNT(*) c FROM equipment")->fetch_assoc()['c'];
$contracts    = $conn->query("SELECT COUNT(*) c FROM contracts")->fetch_assoc()['c'];
$employees    = $conn->query("SELECT COUNT(*) c FROM employees")->fetch_assoc()['c'];
$activeContr  = $conn->query("SELECT COUNT(*) c FROM contracts WHERE status='active'")->fetch_assoc()['c'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments")->fetch_assoc()['s'];
$totalExpense = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM expenses")->fetch_assoc()['s'];
$netProfit    = $totalRevenue - $totalExpense;
?>

<!-- Page header -->
<div class="page-header">
  <h4><i class="bi bi-grid-fill me-2 text-primary"></i>لوحة التحكم</h4>
  <p>مرحباً بك — نظرة عامة على أداء المشروع</p>
</div>

<!-- ── Stat Cards ─────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-xl-3 col-sm-6">
    <a href="modules/clients/index.php" class="stat-card d-flex" style="text-decoration:none">
      <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="stat-label">العملاء</div>
        <div class="stat-value"><?= number_format($clients) ?></div>
        <div class="stat-sub">إجمالي العملاء المسجلين</div>
      </div>
    </a>
  </div>
  <div class="col-xl-3 col-sm-6">
    <a href="modules/equipment/index.php" class="stat-card d-flex" style="text-decoration:none">
      <div class="stat-icon teal"><i class="bi bi-tools"></i></div>
      <div>
        <div class="stat-label">المعدات</div>
        <div class="stat-value"><?= number_format($equipment) ?></div>
        <div class="stat-sub">صنف معدات في المخزون</div>
      </div>
    </a>
  </div>
  <div class="col-xl-3 col-sm-6">
    <a href="modules/contracts/index.php" class="stat-card d-flex" style="text-decoration:none">
      <div class="stat-icon amber"><i class="bi bi-file-earmark-text-fill"></i></div>
      <div>
        <div class="stat-label">العقود</div>
        <div class="stat-value"><?= number_format($contracts) ?></div>
        <div class="stat-sub"><?= $activeContr ?> عقد نشط حالياً</div>
      </div>
    </a>
  </div>
  <div class="col-xl-3 col-sm-6">
    <a href="modules/employees/index.php" class="stat-card d-flex" style="text-decoration:none">
      <div class="stat-icon purple"><i class="bi bi-person-badge-fill"></i></div>
      <div>
        <div class="stat-label">الموظفون</div>
        <div class="stat-value"><?= number_format($employees) ?></div>
        <div class="stat-sub">موظف مسجل في النظام</div>
      </div>
    </a>
  </div>
</div>

<!-- ── Finance Row ────────────────────────────── -->
<div class="row g-4 mb-5">
  <div class="col-md-4">
    <div class="finance-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fc-label">إجمالي الإيرادات</div>
        <div class="fc-icon" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #16a34a;"><i class="bi bi-graph-up-arrow"></i></div>
      </div>
      <div class="fc-value text-success"><?= number_format($totalRevenue, 2) ?> <small class="fs-6 fw-normal text-muted">ر.س</small></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="finance-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fc-label">إجمالي المصروفات</div>
        <div class="fc-icon" style="background: linear-gradient(135deg, #fff1f2, #ffe4e6); color: #e11d48;"><i class="bi bi-graph-down-arrow"></i></div>
      </div>
      <div class="fc-value text-danger"><?= number_format($totalExpense, 2) ?> <small class="fs-6 fw-normal text-muted">ر.س</small></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="finance-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fc-label">صافي الربح</div>
        <div class="fc-icon" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb;"><i class="bi bi-cash-coin"></i></div>
      </div>
      <div class="fc-value <?= $netProfit >= 0 ? 'text-primary' : 'text-danger' ?>">
        <?= number_format(abs($netProfit), 2) ?> <small class="fs-6 fw-normal text-muted">ر.س</small>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent Contracts ───────────────────────── -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-clock-history text-primary"></i>
      <span>آخر العقود</span>
    </div>
    <a href="modules/contracts/add.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i> عقد جديد
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>العميل</th>
            <th>تاريخ البداية</th>
            <th>تاريخ الانتهاء</th>
            <th>الإجمالي</th>
            <th>الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
<?php
$recent = $conn->query("
    SELECT c.*, cl.name client_name
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    ORDER BY c.id DESC LIMIT 10
");
if ($recent && $recent->num_rows > 0):
    while ($row = $recent->fetch_assoc()):
        $isActive = $row['status'] == 'active';
?>
          <tr>
            <td><span class="text-muted">#<?= $row['id'] ?></span></td>
            <td><strong><?= htmlspecialchars($row['client_name']) ?></strong></td>
            <td><?= $row['start_date'] ?></td>
            <td><?= $row['end_date'] ?></td>
            <td><strong><?= number_format($row['total'], 2) ?></strong> <span class="text-muted">ر.س</span></td>
            <td>
              <span class="badge" style="background:<?= $isActive ? 'var(--accent-light);color:var(--accent)' : '#f1f5f9;color:#64748b' ?>">
                <?= $isActive ? 'نشط' : 'منتهي' ?>
              </span>
            </td>
            <td>
              <a href="modules/contracts/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-eye"></i> عرض
              </a>
            </td>
          </tr>
<?php
    endwhile;
else:
?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-3 d-block mb-2"></i>
              لا توجد عقود بعد
            </td>
          </tr>
<?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'templates/footer.php'; ?>
