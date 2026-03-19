<?php
include 'config/db.php';
include 'templates/header.php';

// ── Safe query helper ─────────────────────────────
function safeCount($conn, $sql) {
    $r = $conn->query($sql);
    return ($r && $r->num_rows) ? (int)$r->fetch_assoc()['c'] : 0;
}
function safeVal($conn, $sql) {
    $r = $conn->query($sql);
    return ($r && $r->num_rows) ? (float)$r->fetch_assoc()['s'] : 0;
}

// ── Stats ─────────────────────────────────────────
$clients      = safeCount($conn, "SELECT COUNT(*) c FROM clients");
$equipment    = safeCount($conn, "SELECT COUNT(*) c FROM equipment");
$contracts    = safeCount($conn, "SELECT COUNT(*) c FROM contracts");
$employees    = safeCount($conn, "SELECT COUNT(*) c FROM employees");
$activeContr  = safeCount($conn, "SELECT COUNT(*) c FROM contracts WHERE status='active'");
$totalRevenue = safeVal($conn,   "SELECT COALESCE(SUM(amount),0) s FROM payments");
$totalExpense = safeVal($conn,   "SELECT COALESCE(SUM(amount),0) s FROM expenses");
$netProfit    = $totalRevenue - $totalExpense;

// ── Smart Alerts Data ─────────────────────────────
$today = date('Y-m-d');

// 1. Contracts expiring in <= 7 days (critical)
$expiring7 = $conn->query("
    SELECT c.id, c.end_date, c.total, cl.name client_name,
           DATEDIFF(c.end_date, CURDATE()) days_left
    FROM contracts c JOIN clients cl ON c.client_id=cl.id
    WHERE c.status='active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY c.end_date ASC
");

// 2. Contracts expiring in 8–30 days (warning)
$expiring30 = $conn->query("
    SELECT c.id, c.end_date, cl.name client_name,
           DATEDIFF(c.end_date, CURDATE()) days_left
    FROM contracts c JOIN clients cl ON c.client_id=cl.id
    WHERE c.status='active' AND c.end_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY c.end_date ASC
");

// 3. Active contracts past end_date (overdue — not closed yet)
$overdue = $conn->query("
    SELECT c.id, c.end_date, cl.name client_name,
           DATEDIFF(CURDATE(), c.end_date) days_over
    FROM contracts c JOIN clients cl ON c.client_id=cl.id
    WHERE c.status='active' AND c.end_date < CURDATE()
    ORDER BY c.end_date ASC
");

// 4. Contracts with unpaid balance > 0
$unpaid = $conn->query("
    SELECT id, total, client_name, paid FROM (
        SELECT c.id, c.total, cl.name client_name,
               COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.contract_id=c.id),0) AS paid
        FROM contracts c JOIN clients cl ON c.client_id=cl.id
        WHERE c.status='active'
    ) sub
    WHERE paid < total
    ORDER BY (total - paid) DESC
    LIMIT 5
");

// 5. No expenses this month?
$expThisMonth = safeCount($conn, "SELECT COUNT(*) c FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())");

// 6. Unpaid invoices count
$unpaidInvoices = safeCount($conn, "SELECT COUNT(*) c FROM invoices i WHERE i.status='active' AND (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.contract_id=i.contract_id) < i.total");

// Collect all alerts
$alerts = [];

// Overdue contracts (red — urgent)
if ($overdue && $overdue->num_rows > 0) {
    while ($r = $overdue->fetch_assoc()) {
        $alerts[] = [
            'level' => 'danger',
            'icon'  => 'bi-exclamation-octagon-fill',
            'title' => 'عقد متأخر ولم يُغلق',
            'body'  => "عقد <strong>" . htmlspecialchars($r['client_name']) . "</strong> — انتهى منذ <strong>{$r['days_over']} يوم</strong> ولا يزال نشطاً",
            'link'  => "modules/contracts/view.php?id={$r['id']}",
            'link_text' => 'عرض العقد',
        ];
    }
}

// Expiring in 7 days (red)
if ($expiring7 && $expiring7->num_rows > 0) {
    while ($r = $expiring7->fetch_assoc()) {
        $days = (int)$r['days_left'];
        $dayText = $days == 0 ? 'ينتهي اليوم!' : "ينتهي خلال {$days} يوم";
        $alerts[] = [
            'level' => 'danger',
            'icon'  => 'bi-alarm-fill',
            'title' => 'عقد على وشك الانتهاء',
            'body'  => "عقد <strong>" . htmlspecialchars($r['client_name']) . "</strong> — <strong>{$dayText}</strong> ({$r['end_date']})",
            'link'  => "modules/contracts/view.php?id={$r['id']}",
            'link_text' => 'عرض العقد',
        ];
    }
}

// Expiring in 8–30 days (warning)
if ($expiring30 && $expiring30->num_rows > 0) {
    while ($r = $expiring30->fetch_assoc()) {
        $alerts[] = [
            'level' => 'warning',
            'icon'  => 'bi-clock-history',
            'title' => 'عقد يقترب موعد انتهائه',
            'body'  => "عقد <strong>" . htmlspecialchars($r['client_name']) . "</strong> — ينتهي بعد <strong>{$r['days_left']} يوم</strong> ({$r['end_date']})",
            'link'  => "modules/contracts/view.php?id={$r['id']}",
            'link_text' => 'عرض',
        ];
    }
}

// Unpaid balances (info)
if ($unpaid && $unpaid->num_rows > 0) {
    while ($r = $unpaid->fetch_assoc()) {
        $remaining = $r['total'] - $r['paid'];
        $alerts[] = [
            'level' => 'info',
            'icon'  => 'bi-cash-coin',
            'title' => 'مبلغ غير محصّل',
            'body'  => "عقد <strong>" . htmlspecialchars($r['client_name']) . "</strong> — متبقي <strong>" . number_format($remaining, 2) . " ر.س</strong>",
            'link'  => "modules/contracts/view.php?id={$r['id']}",
            'link_text' => 'تسجيل دفعة',
        ];
    }
}

// No expenses this month
if ($expThisMonth == 0) {
    $alerts[] = [
        'level' => 'secondary',
        'icon'  => 'bi-wallet2',
        'title' => 'تذكير',
        'body'  => 'لم يتم تسجيل أي مصروفات لهذا الشهر — تأكد من إدخال المصروفات الشهرية',
        'link'  => 'modules/expenses/index.php',
        'link_text' => 'إضافة مصروف',
    ];
}
?>

<!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-grid-fill me-2 text-primary"></i>لوحة التحكم</h4>
    <p>مرحباً بك — نظرة عامة على أداء المنشأة</p>
  </div>
  <?php if (!empty($alerts)): ?>
  <div>
    <?php $hasDanger = count(array_filter($alerts, function($a){ return $a['level']==='danger'; })) > 0; ?>
    <span class="badge rounded-pill"
          style="background:<?= $hasDanger ? '#dc2626' : '#d97706' ?>;font-size:13px;padding:8px 16px">
      <i class="bi bi-bell-fill me-1"></i>
      <?= count($alerts) ?> تنبيه<?= count($alerts) > 1 ? 'ات' : '' ?>
    </span>
  </div>
  <?php endif; ?>
</div>

<!-- ══ SMART ALERTS ══════════════════════════════ -->
<?php if (!empty($alerts)): ?>
<div class="mb-4">
  <div class="d-flex align-items-center gap-2 mb-3">
    <div style="width:4px;height:20px;background:linear-gradient(180deg,#dc2626,#f97316);border-radius:4px"></div>
    <span style="font-size:15px;font-weight:800;color:#0b162c">التنبيهات الذكية</span>
    <span style="font-size:12px;color:#64748b;font-weight:500">— يتم التحديث تلقائياً</span>
  </div>

  <?php
  $colors = [
    'danger'    => ['bg'=>'#fef2f2','border'=>'#fca5a5','icon_bg'=>'#dc2626','text'=>'#991b1b','icon_color'=>'#fff'],
    'warning'   => ['bg'=>'#fffbeb','border'=>'#fcd34d','icon_bg'=>'#d97706','text'=>'#92400e','icon_color'=>'#fff'],
    'info'      => ['bg'=>'#eff6ff','border'=>'#93c5fd','icon_bg'=>'#2563eb','text'=>'#1e40af','icon_color'=>'#fff'],
    'secondary' => ['bg'=>'#f8fafc','border'=>'#cbd5e1','icon_bg'=>'#64748b','text'=>'#334155','icon_color'=>'#fff'],
  ];
  foreach ($alerts as $alert):
    $c = $colors[$alert['level']];
  ?>
  <div style="
    background:<?= $c['bg'] ?>;
    border:1.5px solid <?= $c['border'] ?>;
    border-radius:14px;
    padding:14px 18px;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:14px;
  ">
    <div style="
      width:38px;height:38px;flex-shrink:0;
      background:<?= $c['icon_bg'] ?>;
      border-radius:10px;
      display:flex;align-items:center;justify-content:center;
      font-size:17px;color:<?= $c['icon_color'] ?>;
    ">
      <i class="bi <?= $alert['icon'] ?>"></i>
    </div>
    <div style="flex:1">
      <div style="font-size:12px;font-weight:800;color:<?= $c['icon_bg'] ?>;margin-bottom:2px;letter-spacing:.3px">
        <?= $alert['title'] ?>
      </div>
      <div style="font-size:13px;color:<?= $c['text'] ?>;font-weight:500;line-height:1.5">
        <?= $alert['body'] ?>
      </div>
    </div>
    <a href="<?= $alert['link'] ?>" style="
      flex-shrink:0;
      background:<?= $c['icon_bg'] ?>;color:#fff;
      border-radius:8px;padding:6px 14px;
      font-size:12px;font-weight:700;
      text-decoration:none;white-space:nowrap;
    ">
      <?= $alert['link_text'] ?> →
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:14px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px">
  <div style="width:38px;height:38px;background:#16a34a;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0">
    <i class="bi bi-check-circle-fill"></i>
  </div>
  <div>
    <div style="font-size:13px;font-weight:800;color:#15803d">كل شيء على ما يرام</div>
    <div style="font-size:12px;color:#166534;font-weight:500">لا توجد تنبيهات حالياً — جميع العقود والمدفوعات في حالة جيدة</div>
  </div>
</div>
<?php endif; ?>

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
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="finance-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fc-label">إجمالي الإيرادات</div>
        <div class="fc-icon" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#16a34a"><i class="bi bi-graph-up-arrow"></i></div>
      </div>
      <div class="fc-value text-success"><?= number_format($totalRevenue, 2) ?> <small class="fs-6 fw-normal text-muted">ر.س</small></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="finance-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fc-label">إجمالي المصروفات</div>
        <div class="fc-icon" style="background:linear-gradient(135deg,#fff1f2,#ffe4e6);color:#e11d48"><i class="bi bi-graph-down-arrow"></i></div>
      </div>
      <div class="fc-value text-danger"><?= number_format($totalExpense, 2) ?> <small class="fs-6 fw-normal text-muted">ر.س</small></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="finance-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fc-label">صافي الربح</div>
        <div class="fc-icon" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb"><i class="bi bi-cash-coin"></i></div>
      </div>
      <div class="fc-value <?= $netProfit >= 0 ? 'text-primary' : 'text-danger' ?>">
        <?= number_format(abs($netProfit), 2) ?> <small class="fs-6 fw-normal text-muted">ر.س</small>
      </div>
    </div>
  </div>
</div>

<!-- ── Quick Insight Row ───────────────────────── -->
<div class="row g-3 mb-4">
  <?php
  $r = $conn->query("SELECT COALESCE(SUM(c.total - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.contract_id=c.id),0)),0) r FROM contracts c WHERE c.status='active'");
  $totalUnpaid  = ($r && $r->num_rows) ? (float)$r->fetch_assoc()['r'] : 0;
  $monthRevenue = safeVal($conn, "SELECT COALESCE(SUM(amount),0) s FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())");
  $monthExpense = safeVal($conn, "SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())");
  ?>
  <div class="col-md-4">
    <div class="stat-card" style="background:linear-gradient(135deg,#fff7ed,#fed7aa)">
      <div class="stat-icon" style="background:#fff;color:#ea580c"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-label" style="color:#9a3412">المبالغ غير المحصّلة</div>
        <div class="stat-value" style="font-size:22px;color:#ea580c"><?= number_format($totalUnpaid, 2) ?> <small style="font-size:13px">ر.س</small></div>
        <div class="stat-sub" style="color:#c2410c">من العقود النشطة</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7)">
      <div class="stat-icon" style="background:#fff;color:#16a34a"><i class="bi bi-calendar-check"></i></div>
      <div>
        <div class="stat-label" style="color:#166534">تحصيل هذا الشهر</div>
        <div class="stat-value" style="font-size:22px;color:#16a34a"><?= number_format($monthRevenue, 2) ?> <small style="font-size:13px">ر.س</small></div>
        <div class="stat-sub" style="color:#15803d"><?= date('F Y') ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card" style="background:linear-gradient(135deg,#fdf4ff,#f3e8ff)">
      <div class="stat-icon" style="background:#fff;color:#9333ea"><i class="bi bi-receipt-cutoff"></i></div>
      <div>
        <div class="stat-label" style="color:#6b21a8">فواتير تنتظر السداد</div>
        <div class="stat-value" style="font-size:22px;color:#9333ea"><?= $unpaidInvoices ?></div>
        <div class="stat-sub" style="color:#7e22ce">فاتورة لم تُسدَّد بالكامل</div>
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
    SELECT c.*, cl.name client_name,
           DATEDIFF(c.end_date, CURDATE()) days_left
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    ORDER BY c.id DESC LIMIT 10
");
if ($recent && $recent->num_rows > 0):
    while ($row = $recent->fetch_assoc()):
        $isActive  = $row['status'] == 'active';
        $daysLeft  = (int)$row['days_left'];
        $isExpiring = $isActive && $daysLeft >= 0 && $daysLeft <= 7;
        $isOverdue  = $isActive && $daysLeft < 0;
?>
          <tr style="<?= $isOverdue ? 'background:#fef2f2' : ($isExpiring ? 'background:#fffbeb' : '') ?>">
            <td><span class="text-muted">#<?= $row['id'] ?></span></td>
            <td><strong><?= htmlspecialchars($row['client_name']) ?></strong></td>
            <td><?= $row['start_date'] ?></td>
            <td>
              <?= $row['end_date'] ?>
              <?php if ($isOverdue): ?>
                <span style="font-size:10px;background:#fee2e2;color:#dc2626;border-radius:6px;padding:1px 6px;font-weight:700;margin-right:4px">متأخر</span>
              <?php elseif ($isExpiring): ?>
                <span style="font-size:10px;background:#fef3c7;color:#d97706;border-radius:6px;padding:1px 6px;font-weight:700;margin-right:4px"><?= $daysLeft == 0 ? 'اليوم' : $daysLeft.' يوم' ?></span>
              <?php endif; ?>
            </td>
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
