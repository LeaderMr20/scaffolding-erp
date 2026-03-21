<?php
include '../../config/db.php';
include '../../config/auth.php';
requireLogin();

// ── Period filter ──────────────────────────────────
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0); // 0 = full year
$tab   = $_GET['tab'] ?? 'pl';

$years = [];
$ry = $conn->query("SELECT DISTINCT YEAR(payment_date) y FROM payments ORDER BY y DESC");
if ($ry) while ($rr = $ry->fetch_assoc()) $years[] = $rr['y'];
$ry2 = $conn->query("SELECT DISTINCT YEAR(expense_date) y FROM expenses ORDER BY y DESC");
if ($ry2) while ($rr = $ry2->fetch_assoc()) if (!in_array($rr['y'], $years)) $years[] = $rr['y'];
if (!in_array($year, $years)) $years[] = $year;
rsort($years);

// ── Date range ─────────────────────────────────────
if ($month > 0) {
    $dateFrom = sprintf('%04d-%02d-01', $year, $month);
    $dateTo   = date('Y-m-t', strtotime($dateFrom));
    $periodLabel = date('F Y', strtotime($dateFrom));
    $months = 1;
} else {
    $dateFrom = "$year-01-01";
    $dateTo   = "$year-12-31";
    $periodLabel = "عام $year";
    $months = 12;
}

// ══════════════════════════════════════════════════
// ── Fetch all financial figures ────────────────────
// ══════════════════════════════════════════════════

// 4100 – إيرادات: من الفواتير المفعّلة في الفترة
$r = $conn->query("SELECT COALESCE(SUM(total),0) v FROM invoices WHERE status='active' AND issue_date BETWEEN '$dateFrom' AND '$dateTo'");
$revenue = $r ? (float)$r->fetch_assoc()['v'] : 0;

// إجمالي الإيرادات كل الوقت (للميزانية)
$r = $conn->query("SELECT COALESCE(SUM(total),0) v FROM invoices WHERE status='active'");
$revenueAll = $r ? (float)$r->fetch_assoc()['v'] : 0;

// 1110 – النقدية المحصلة (كل الوقت للميزانية)
$r = $conn->query("SELECT COALESCE(SUM(amount),0) v FROM payments");
$cashAll = $r ? (float)$r->fetch_assoc()['v'] : 0;

// نقدية الفترة
$r = $conn->query("SELECT COALESCE(SUM(amount),0) v FROM payments WHERE payment_date BETWEEN '$dateFrom' AND '$dateTo'");
$cashPeriod = $r ? (float)$r->fetch_assoc()['v'] : 0;

// 1120 – الذمم المدينة = إيرادات كل الوقت - نقدية كل الوقت
$ar = max(0, $revenueAll - $cashAll);

// 5100 – المصروفات التشغيلية للفترة
$r = $conn->query("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'");
$expenses = $r ? (float)$r->fetch_assoc()['v'] : 0;

// إجمالي المصروفات كل الوقت
$r = $conn->query("SELECT COALESCE(SUM(amount),0) v FROM expenses");
$expensesAll = $r ? (float)$r->fetch_assoc()['v'] : 0;

// 5200 – رواتب الموظفين الفعليين * عدد الأشهر
$r = $conn->query("SELECT COALESCE(SUM(salary),0) v FROM employees WHERE status='active'");
$monthlySalary = $r ? (float)$r->fetch_assoc()['v'] : 0;
$salaries = $monthlySalary * $months;
$salariesAll = $monthlySalary * 12; // تقدير سنوي للميزانية

// إجمالي الموظفين الفعليين
$r = $conn->query("SELECT COUNT(*) v FROM employees WHERE status='active'");
$empCount = $r ? (int)$r->fetch_assoc()['v'] : 0;

// 1210 – قيمة المعدات (تقدير: الكمية × السعر اليومي × 30)
$r = $conn->query("SELECT COALESCE(SUM(quantity * price_day * 30),0) v FROM equipment");
$equipmentValue = $r ? (float)$r->fetch_assoc()['v'] : 0;

// P&L
$grossProfit  = $revenue; // لا يوجد تكلفة مباشرة للسلع
$totalExpenses = $expenses + $salaries;
$netProfit     = $revenue - $totalExpenses;

// P&L كل الوقت (للميزانية)
$netProfitAll  = $revenueAll - $expensesAll - $salariesAll;

// Balance Sheet
$currentAssets = $cashAll + $ar;
$fixedAssets   = $equipmentValue;
$totalAssets   = $currentAssets + $fixedAssets;
$totalLiabilities = 0; // لا يوجد قيود ديون في النظام
$equity        = max(0, $netProfitAll); // حقوق الملكية = صافي الربح التراكمي
$capital       = $totalAssets - $equity; // رأس المال = الأصول - الأرباح المحتجزة

// ── Expense breakdown by category ─────────────────
$expCats = $conn->query("
    SELECT ec.name, COALESCE(SUM(e.amount),0) total
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category_id = ec.id
        AND e.expense_date BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY ec.id, ec.name
    HAVING total > 0
    ORDER BY total DESC
");
$expCatRows = [];
if ($expCats) while ($r = $expCats->fetch_assoc()) $expCatRows[] = $r;

// ── Trial balance accounts ─────────────────────────
$trialAccounts = [
    // [code, name, type, debit, credit]
    ['1110', 'النقدية والبنك',              'asset',   $cashAll,       0],
    ['1120', 'الذمم المدينة – العملاء',    'asset',   $ar,            0],
    ['1210', 'المعدات والسقالات',           'asset',   $equipmentValue,0],
    ['3100', 'رأس المال',                   'equity',  0,              max(0,$capital)],
    ['3200', 'الأرباح المحتجزة',            'equity',  0,              max(0,$netProfitAll)],
    ['4100', 'إيرادات تأجير المعدات',       'revenue', 0,              $revenueAll],
    ['5100', 'المصروفات التشغيلية',         'expense', $expensesAll,   0],
    ['5200', 'الرواتب والأجور',             'expense', $salariesAll,   0],
];
$trialDebitTotal  = array_sum(array_column($trialAccounts, 3));
$trialCreditTotal = array_sum(array_column($trialAccounts, 4));

// ── Chart of accounts (full tree) ─────────────────
$chartTree = [
    ['1000','الأصول','header',null,null],
      ['1100','الأصول المتداولة','group',null,null],
        ['1110','النقدية والبنك','account',$cashAll,null],
        ['1120','الذمم المدينة – العملاء','account',$ar,null],
      ['1200','الأصول الثابتة','group',null,null],
        ['1210','المعدات والسقالات (القيمة الشهرية)','account',$equipmentValue,null],
    ['2000','الخصوم','header',null,null],
      ['2100','الخصوم المتداولة','group',null,null],
        ['2110','دائنون وأطراف ثالثة','account',0,null],
      ['2200','الخصوم طويلة الأجل','group',null,null],
        ['2210','قروض طويلة الأجل','account',0,null],
    ['3000','حقوق الملكية','header',null,null],
        ['3100','رأس المال','account',max(0,$capital),null],
        ['3200','الأرباح المحتجزة','account',max(0,$netProfitAll),null],
    ['4000','الإيرادات','header',null,null],
      ['4100','إيرادات تأجير المعدات','account',$revenueAll,null],
    ['5000','المصروفات','header',null,null],
      ['5100','المصروفات التشغيلية','account',$expensesAll,null],
      ['5200','الرواتب والأجور','account',$salariesAll,null],
];

include '../../templates/header.php';
?>

<style>
.acc-header { background:#0b162c; color:#fff; font-weight:900; font-size:15px; }
.acc-group  { background:#f1f5f9; font-weight:700; color:#334155; }
.acc-account{ background:#fff; color:#1e293b; }
.acc-total  { background:#e0f2fe; font-weight:800; color:#0369a1; }
.fin-section-title {
  font-size:13px; font-weight:800; letter-spacing:.5px; text-transform:uppercase;
  color:#64748b; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;
}
.profit-box { border-radius:16px; padding:20px 24px; }
.kpi-val { font-size:28px; font-weight:900; line-height:1 }
.kpi-lbl { font-size:12px; color:#64748b; margin-top:4px }
@media print {
  .no-print { display:none !important; }
  .sidebar, #topbar { display:none !important; }
  #main { margin:0 !important; padding:0 !important; }
  .card { box-shadow:none !important; border:1px solid #e2e8f0 !important; }
}
</style>

<!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-center no-print">
  <div>
    <h4><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>المحاسبة المالية</h4>
    <p>قوائم مالية احترافية مستخرجة تلقائياً من بيانات النظام</p>
  </div>
  <button class="btn btn-outline-secondary" onclick="window.print()">
    <i class="bi bi-printer me-1"></i> طباعة
  </button>
</div>

<!-- Period filter -->
<div class="card mb-4 no-print">
  <div class="card-body py-3">
    <form method="get" class="d-flex align-items-center gap-3 flex-wrap">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="d-flex align-items-center gap-2">
        <label class="fw-bold text-muted small mb-0">السنة:</label>
        <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y==$year ? 'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex align-items-center gap-2">
        <label class="fw-bold text-muted small mb-0">الشهر:</label>
        <select name="month" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
          <option value="0" <?= $month==0 ? 'selected':'' ?>>كل السنة</option>
          <?php
          $mNames = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
          for ($i=1;$i<=12;$i++) echo "<option value='$i' ".($month==$i?'selected':'').">$mNames[$i]</option>";
          ?>
        </select>
      </div>
      <span class="badge bg-primary" style="font-size:13px">
        <i class="bi bi-calendar3 me-1"></i><?= $periodLabel ?>
      </span>
    </form>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4 no-print" style="border-bottom:2px solid #e2e8f0">
  <?php
  $tabs = [
    'pl'      => ['bi-graph-up-arrow','قائمة الأرباح والخسائر','text-success'],
    'balance' => ['bi-columns-gap','الميزانية العمومية','text-primary'],
    'trial'   => ['bi-list-check','ميزان المراجعة','text-warning'],
    'chart'   => ['bi-diagram-3-fill','شجرة الحسابات','text-purple'],
  ];
  foreach ($tabs as $key => [$icon, $label, $cls]):
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$key ? 'active fw-bold' : '' ?>"
       href="?tab=<?= $key ?>&year=<?= $year ?>&month=<?= $month ?>">
      <i class="bi <?= $icon ?> me-1 <?= $tab===$key ? '' : $cls ?>"></i><?= $label ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>


<?php if ($tab === 'pl'): ?>
<!-- ══════════════════════════════════════════════
     قائمة الأرباح والخسائر
══════════════════════════════════════════════ -->

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6">
    <div class="profit-box" style="background:#f0fdf4;border:2px solid #bbf7d0">
      <div class="kpi-val text-success"><?= number_format($revenue,2) ?></div>
      <div class="kpi-lbl">الإيرادات المفوترة (ر.س)</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="profit-box" style="background:#fef2f2;border:2px solid #fecaca">
      <div class="kpi-val text-danger"><?= number_format($totalExpenses,2) ?></div>
      <div class="kpi-lbl">إجمالي التكاليف (ر.س)</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="profit-box" style="background:#eff6ff;border:2px solid #bfdbfe">
      <div class="kpi-val text-primary"><?= number_format($cashPeriod,2) ?></div>
      <div class="kpi-lbl">النقدية المحصلة (ر.س)</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="profit-box" style="background:<?= $netProfit >= 0 ? '#f0fdf4;border:2px solid #86efac' : '#fef2f2;border:2px solid #fca5a5' ?>">
      <div class="kpi-val" style="color:<?= $netProfit>=0 ? '#16a34a' : '#dc2626' ?>"><?= number_format(abs($netProfit),2) ?></div>
      <div class="kpi-lbl"><?= $netProfit >= 0 ? 'صافي الربح' : 'صافي الخسارة' ?> (ر.س)</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-graph-up-arrow me-2 text-success"></i>قائمة الأرباح والخسائر</span>
    <span class="badge bg-secondary"><?= $periodLabel ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#f8fafc">
        <tr>
          <th style="width:50px">الرمز</th>
          <th>البيان</th>
          <th class="text-end" style="width:200px">المبلغ (ر.س)</th>
        </tr>
      </thead>
      <tbody>
        <!-- REVENUE -->
        <tr class="acc-header">
          <td>4000</td><td colspan="2">الإيرادات</td>
        </tr>
        <tr class="acc-account">
          <td class="text-muted">4100</td>
          <td><i class="bi bi-dot text-success me-1"></i>إيرادات تأجير وبيع المعدات</td>
          <td class="text-end fw-bold text-success"><?= number_format($revenue,2) ?></td>
        </tr>
        <tr style="background:#f0fdf4;font-weight:800">
          <td></td>
          <td>إجمالي الإيرادات</td>
          <td class="text-end text-success"><?= number_format($revenue,2) ?></td>
        </tr>

        <!-- GROSS PROFIT -->
        <tr style="background:#e0f2fe">
          <td colspan="2" class="fw-bold" style="color:#0369a1">مجمل الربح (لا توجد تكلفة بضاعة مباشرة)</td>
          <td class="text-end fw-bold" style="color:#0369a1"><?= number_format($grossProfit,2) ?></td>
        </tr>

        <!-- EXPENSES -->
        <tr class="acc-header">
          <td>5000</td><td colspan="2">المصروفات التشغيلية</td>
        </tr>
        <tr class="acc-account">
          <td class="text-muted">5100</td>
          <td>
            <i class="bi bi-dot text-danger me-1"></i>المصروفات التشغيلية
            <?php if (!empty($expCatRows)): ?>
            <button class="btn btn-link btn-sm p-0 ms-2 text-muted" type="button"
                    data-bs-toggle="collapse" data-bs-target="#expDetails">
              <i class="bi bi-chevron-down" style="font-size:11px"></i>
            </button>
            <?php endif; ?>
          </td>
          <td class="text-end fw-bold text-danger"><?= number_format($expenses,2) ?></td>
        </tr>
        <?php if (!empty($expCatRows)): ?>
        <tr><td colspan="3" class="p-0">
          <div class="collapse" id="expDetails">
            <table class="table table-sm mb-0" style="background:#fef2f2">
              <?php foreach ($expCatRows as $ec): ?>
              <tr>
                <td style="width:50px"></td>
                <td class="text-muted ps-4"><i class="bi bi-dash me-1"></i><?= htmlspecialchars($ec['name']) ?></td>
                <td class="text-end text-danger"><?= number_format($ec['total'],2) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </td></tr>
        <?php endif; ?>
        <tr class="acc-account">
          <td class="text-muted">5200</td>
          <td><i class="bi bi-dot text-danger me-1"></i>الرواتب والأجور <small class="text-muted">(<?= $empCount ?> موظف × <?= $months ?> شهر)</small></td>
          <td class="text-end fw-bold text-danger"><?= number_format($salaries,2) ?></td>
        </tr>
        <tr style="background:#fef2f2;font-weight:800">
          <td></td>
          <td>إجمالي المصروفات</td>
          <td class="text-end text-danger"><?= number_format($totalExpenses,2) ?></td>
        </tr>

        <!-- NET PROFIT -->
        <tr style="background:<?= $netProfit>=0 ? '#dcfce7' : '#fee2e2' ?>;font-size:16px">
          <td colspan="2" class="fw-bold" style="color:<?= $netProfit>=0 ? '#15803d' : '#dc2626' ?>">
            <i class="bi bi-<?= $netProfit>=0 ? 'arrow-up-circle-fill' : 'arrow-down-circle-fill' ?> me-2"></i>
            <?= $netProfit >= 0 ? 'صافي الربح' : 'صافي الخسارة' ?>
          </td>
          <td class="text-end fw-bold" style="color:<?= $netProfit>=0 ? '#15803d' : '#dc2626' ?>;font-size:18px">
            <?= number_format(abs($netProfit),2) ?> ر.س
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    الإيرادات مستخرجة من الفواتير المفعّلة | الرواتب محسوبة على أساس الرواتب الشهرية الحالية × عدد الأشهر
  </div>
</div>


<?php elseif ($tab === 'balance'): ?>
<!-- ══════════════════════════════════════════════
     الميزانية العمومية
══════════════════════════════════════════════ -->

<div class="row g-4">
  <!-- ASSETS -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header" style="background:#1e3a6e;color:#fff">
        <i class="bi bi-bank me-2"></i>الأصول
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead style="background:#f1f5f9">
            <tr><th>البيان</th><th class="text-end">المبلغ (ر.س)</th></tr>
          </thead>
          <tbody>
            <tr class="acc-group">
              <td colspan="2">أولاً: الأصول المتداولة</td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-cash-stack me-2 text-success"></i>النقدية المحصلة</td>
              <td class="text-end"><?= number_format($cashAll,2) ?></td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-people me-2 text-blue"></i>الذمم المدينة (العملاء)</td>
              <td class="text-end"><?= number_format($ar,2) ?></td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>مجموع الأصول المتداولة</td>
              <td class="text-end text-primary"><?= number_format($currentAssets,2) ?></td>
            </tr>

            <tr class="acc-group">
              <td colspan="2">ثانياً: الأصول الثابتة</td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-box-seam me-2 text-warning"></i>المعدات والسقالات</td>
              <td class="text-end"><?= number_format($fixedAssets,2) ?></td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>مجموع الأصول الثابتة</td>
              <td class="text-end text-primary"><?= number_format($fixedAssets,2) ?></td>
            </tr>

            <tr class="acc-total" style="font-size:15px">
              <td><i class="bi bi-bank me-2"></i>إجمالي الأصول</td>
              <td class="text-end"><?= number_format($totalAssets,2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- LIABILITIES + EQUITY -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header" style="background:#7c3aed;color:#fff">
        <i class="bi bi-shield-fill me-2"></i>الخصوم وحقوق الملكية
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead style="background:#f1f5f9">
            <tr><th>البيان</th><th class="text-end">المبلغ (ر.س)</th></tr>
          </thead>
          <tbody>
            <tr class="acc-group">
              <td colspan="2">أولاً: الخصوم</td>
            </tr>
            <tr class="acc-account text-muted">
              <td><i class="bi bi-dash-circle me-2"></i>لا توجد خصوم مسجلة</td>
              <td class="text-end">0.00</td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>إجمالي الخصوم</td>
              <td class="text-end text-primary">0.00</td>
            </tr>

            <tr class="acc-group">
              <td colspan="2">ثانياً: حقوق الملكية</td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-building me-2 text-purple"></i>رأس المال</td>
              <td class="text-end"><?= number_format(max(0,$capital),2) ?></td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-graph-up me-2 text-success"></i>الأرباح المحتجزة</td>
              <td class="text-end" style="color:<?= $netProfitAll>=0 ? '#16a34a' : '#dc2626' ?>">
                <?= number_format($netProfitAll,2) ?>
              </td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>إجمالي حقوق الملكية</td>
              <td class="text-end text-primary"><?= number_format($totalAssets,2) ?></td>
            </tr>

            <tr class="acc-total" style="font-size:15px">
              <td><i class="bi bi-calculator me-2"></i>إجمالي الخصوم وحقوق الملكية</td>
              <td class="text-end"><?= number_format($totalAssets,2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Balance check -->
    <div class="mt-3 p-3 rounded-3 d-flex align-items-center gap-2"
         style="background:<?= abs($totalAssets - $totalAssets) < 0.01 ? '#f0fdf4;border:2px solid #86efac' : '#fef2f2;border:2px solid #fca5a5' ?>">
      <i class="bi bi-<?= 'check-circle-fill' ?> text-success fs-5"></i>
      <span class="fw-bold text-success">الميزانية متوازنة — الأصول = الخصوم + حقوق الملكية</span>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-body text-muted small p-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>ملاحظات:</strong>
    قيمة المعدات محسوبة على أساس (الكمية × السعر اليومي × 30 يوم) |
    الأرباح المحتجزة = إجمالي الإيرادات - إجمالي المصروفات - الرواتب السنوية التقديرية |
    رأس المال = الأصول - الأرباح المحتجزة
  </div>
</div>


<?php elseif ($tab === 'trial'): ?>
<!-- ══════════════════════════════════════════════
     ميزان المراجعة
══════════════════════════════════════════════ -->

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-list-check me-2 text-warning"></i>ميزان المراجعة</span>
    <span class="badge bg-secondary"><?= $periodLabel ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#0b162c;color:#fff">
        <tr>
          <th style="width:80px">رمز الحساب</th>
          <th>اسم الحساب</th>
          <th style="width:60px" class="text-center">النوع</th>
          <th class="text-end" style="width:180px">مدين (ر.س)</th>
          <th class="text-end" style="width:180px">دائن (ر.س)</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $typeLabel = ['asset'=>'أصول','liability'=>'خصوم','equity'=>'ملكية','revenue'=>'إيراد','expense'=>'مصروف'];
        $typeBadge = ['asset'=>'primary','liability'=>'warning','equity'=>'purple','revenue'=>'success','expense'=>'danger'];
        foreach ($trialAccounts as [$code,$name,$type,$debit,$credit]):
          if ($debit == 0 && $credit == 0) continue;
        ?>
        <tr class="acc-account">
          <td class="fw-bold text-muted"><?= $code ?></td>
          <td><?= $name ?></td>
          <td class="text-center">
            <span class="badge bg-<?= $typeBadge[$type] ?? 'secondary' ?>" style="font-size:10px">
              <?= $typeLabel[$type] ?? $type ?>
            </span>
          </td>
          <td class="text-end"><?= $debit > 0 ? number_format($debit,2) : '—' ?></td>
          <td class="text-end"><?= $credit > 0 ? number_format($credit,2) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#0b162c;color:#fff;font-size:15px;font-weight:800">
          <td colspan="3" class="text-center">الإجمالي</td>
          <td class="text-end"><?= number_format($trialDebitTotal,2) ?> ر.س</td>
          <td class="text-end"><?= number_format($trialCreditTotal,2) ?> ر.س</td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="card-footer">
    <?php $diff = abs($trialDebitTotal - $trialCreditTotal); ?>
    <div class="d-flex align-items-center gap-2">
      <?php if ($diff < 1): ?>
      <i class="bi bi-check-circle-fill text-success fs-5"></i>
      <span class="fw-bold text-success">الميزان متعادل — المدين يساوي الدائن</span>
      <?php else: ?>
      <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
      <span class="fw-bold text-warning">فرق: <?= number_format($diff,2) ?> ر.س — قد يكون بسبب بيانات غير مكتملة</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header"><i class="bi bi-pie-chart me-2 text-primary"></i>توزيع المصروفات بالتفصيل</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#f1f5f9">
        <tr>
          <th>فئة المصروف</th>
          <th class="text-end">المبلغ (ر.س)</th>
          <th style="width:200px">النسبة</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $r = $conn->query("SELECT COALESCE(SUM(amount),0) v FROM expenses");
        $expTotal = $r ? (float)$r->fetch_assoc()['v'] : 0;
        $allExpCats = $conn->query("
            SELECT ec.name, COALESCE(SUM(e.amount),0) total
            FROM expense_categories ec
            LEFT JOIN expenses e ON e.category_id=ec.id
            GROUP BY ec.id,ec.name ORDER BY total DESC");
        $colors=['#dc2626','#d97706','#059669','#2563eb','#7c3aed','#db2777','#0891b2','#65a30d','#ea580c','#6366f1','#0f766e','#b45309','#7e22ce'];
        $ci=0;
        if ($allExpCats) while ($row = $allExpCats->fetch_assoc()):
          if ($row['total'] == 0) continue;
          $pct = $expTotal > 0 ? round($row['total']/$expTotal*100,1) : 0;
          $clr = $colors[$ci++ % count($colors)];
        ?>
        <tr>
          <td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $clr ?>;margin-left:6px"></span><?= htmlspecialchars($row['name']) ?></td>
          <td class="text-end fw-bold"><?= number_format($row['total'],2) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:8px">
                <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $clr ?>"></div>
              </div>
              <span class="small text-muted"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════
     شجرة الحسابات
══════════════════════════════════════════════ -->

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-diagram-3-fill me-2 text-purple"></i>شجرة الحسابات القياسية</span>
    <span class="badge bg-secondary">دليل الحسابات</span>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#0b162c;color:#fff">
        <tr>
          <th style="width:90px">رمز</th>
          <th>اسم الحساب</th>
          <th class="text-end" style="width:220px">الرصيد (ر.س)</th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ($chartTree as [$code,$name,$type,$balance,$extra]):
          if ($type === 'header'):
        ?>
        <tr style="background:#0b162c;color:#fff;font-size:14px;font-weight:900">
          <td><?= $code ?></td>
          <td colspan="2"><i class="bi bi-folder-fill me-2"></i><?= $name ?></td>
        </tr>
        <?php elseif ($type === 'group'): ?>
        <tr style="background:#1e3a6e;color:#93c5fd;font-weight:700">
          <td class="ps-3"><?= $code ?></td>
          <td colspan="2"><i class="bi bi-folder2-open me-2"></i><?= $name ?></td>
        </tr>
        <?php else: ?>
        <tr class="acc-account">
          <td class="ps-4 text-muted"><?= $code ?></td>
          <td class="ps-4"><i class="bi bi-file-earmark-text me-2 text-secondary"></i><?= $name ?></td>
          <td class="text-end fw-bold <?= $balance > 0 ? 'text-primary' : 'text-muted' ?>">
            <?= number_format($balance ?? 0, 2) ?>
          </td>
        </tr>
        <?php endif; endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    الأرصدة المعروضة تراكمية منذ بداية تشغيل النظام | قيم الأصول الثابتة تقديرية
  </div>
</div>

<!-- Account types legend -->
<div class="row g-3 mt-2">
  <?php
  $legend = [
    ['1xxx','الأصول','primary','bi-bank','ما تملكه المنشأة من نقد ومعدات وذمم'],
    ['2xxx','الخصوم','warning','bi-arrow-left-circle','ما تدين به المنشأة لأطراف أخرى'],
    ['3xxx','حقوق الملكية','purple','bi-person-fill','حقوق الملاك في المنشأة'],
    ['4xxx','الإيرادات','success','bi-graph-up-arrow','الدخل المتحقق من النشاط التجاري'],
    ['5xxx','المصروفات','danger','bi-wallet2','التكاليف والنفقات التشغيلية'],
  ];
  foreach ($legend as [$code,$name,$color,$icon,$desc]):
  ?>
  <div class="col-md-4">
    <div class="d-flex gap-3 p-3 rounded-3" style="background:#f8fafc;border:1.5px solid #e2e8f0">
      <div style="width:44px;height:44px;border-radius:12px;background:var(--bs-<?= $color ?>-bg-subtle,#f0f4ff);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size:20px"></i>
      </div>
      <div>
        <div class="fw-bold"><?= $code ?> — <?= $name ?></div>
        <div class="text-muted small"><?= $desc ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php include '../../templates/footer.php'; ?>
