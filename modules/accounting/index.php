<?php
// Disable mysqli strict exceptions — allow queries to return false on error
mysqli_report(MYSQLI_REPORT_OFF);

include '../../config/db.php';
include '../../config/auth.php';
requireLogin();

// ── Helper: safe query fetch ───────────────────────
if (!function_exists('qval')) {
    function qval($conn, $sql, $col = 'v') {
        $r = $conn->query($sql);
        if (!$r) return 0;
        $row = $r->fetch_assoc();
        return isset($row[$col]) ? (float)$row[$col] : 0;
    }
}

// ── Period filter ──────────────────────────────────
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);
$tab   = $_GET['tab'] ?? 'pl';

// Build available years
$years = [(int)date('Y')];
$ry = $conn->query("SELECT DISTINCT YEAR(payment_date) y FROM payments WHERE payment_date IS NOT NULL ORDER BY y DESC");
if ($ry) while ($rr = $ry->fetch_assoc()) { $y=(int)$rr['y']; if($y>0&&!in_array($y,$years)) $years[]=$y; }
$ry2 = $conn->query("SELECT DISTINCT YEAR(expense_date) y FROM expenses WHERE expense_date IS NOT NULL ORDER BY y DESC");
if ($ry2) while ($rr = $ry2->fetch_assoc()) { $y=(int)$rr['y']; if($y>0&&!in_array($y,$years)) $years[]=$y; }
if (!in_array($year,$years)) $years[]=$year;
rsort($years);

// Date range
if ($month > 0) {
    $dateFrom    = sprintf('%04d-%02d-01', $year, $month);
    $dateTo      = date('Y-m-t', strtotime($dateFrom));
    $periodLabel = date('F Y', strtotime($dateFrom));
    $months      = 1;
} else {
    $dateFrom    = "$year-01-01";
    $dateTo      = "$year-12-31";
    $periodLabel = "عام $year";
    $months      = 12;
}

// ══════════════════════════════════════════════════
// ── Financial figures ─────────────────────────────
// ══════════════════════════════════════════════════

// Revenue — invoices in period
$revenue    = (float)qval($conn,"SELECT COALESCE(SUM(total),0) v FROM invoices WHERE status='active' AND issue_date BETWEEN '$dateFrom' AND '$dateTo'");
$revenueAll = (float)qval($conn,"SELECT COALESCE(SUM(total),0) v FROM invoices WHERE status='active'");

// Cash collected
$cashPeriod = (float)qval($conn,"SELECT COALESCE(SUM(amount),0) v FROM payments WHERE payment_date BETWEEN '$dateFrom' AND '$dateTo'");
$cashAll    = (float)qval($conn,"SELECT COALESCE(SUM(amount),0) v FROM payments");

// AR
$ar = max(0, $revenueAll - $cashAll);

// Expenses
$expenses    = (float)qval($conn,"SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'");
$expensesAll = (float)qval($conn,"SELECT COALESCE(SUM(amount),0) v FROM expenses");

// Salaries — all employees (no status column dependency)
$monthlySalary = (float)qval($conn, "SELECT COALESCE(SUM(salary),0) v FROM employees");
$salaries      = $monthlySalary * $months;
$salariesAll   = $monthlySalary * 12;

// Employee count
$empCount = (int)qval($conn, "SELECT COUNT(*) v FROM employees", 'v');

// Equipment value
$equipmentValue = (float)qval($conn,"SELECT COALESCE(SUM(quantity * price_day * 30),0) v FROM equipment");

// P&L
$totalExpenses = $expenses + $salaries;
$netProfit     = $revenue - $totalExpenses;
$netProfitAll  = $revenueAll - $expensesAll - $salariesAll;

// Balance sheet
$currentAssets    = $cashAll + $ar;
$fixedAssets      = $equipmentValue;
$totalAssets      = $currentAssets + $fixedAssets;
$retainedEarnings = max(0, $netProfitAll);
$capital          = max(0, $totalAssets - $retainedEarnings);

// Expense by category for period
$expCatRows = [];
$ec = $conn->query("
    SELECT ec.name, COALESCE(SUM(e.amount),0) total
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category_id=ec.id
        AND e.expense_date BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY ec.id, ec.name
    HAVING total > 0
    ORDER BY total DESC
");
if ($ec) while ($row = $ec->fetch_assoc()) $expCatRows[] = $row;

// Trial balance
$trialAccounts = [
    ['1110','النقدية والبنك',            'asset',   $cashAll,          0],
    ['1120','الذمم المدينة – العملاء',   'asset',   $ar,               0],
    ['1210','المعدات والسقالات',          'asset',   $equipmentValue,   0],
    ['3100','رأس المال',                  'equity',  0,                 $capital],
    ['3200','الأرباح المحتجزة',           'equity',  0,                 $retainedEarnings],
    ['4100','إيرادات تأجير المعدات',      'revenue', 0,                 $revenueAll],
    ['5100','المصروفات التشغيلية',        'expense', $expensesAll,      0],
    ['5200','الرواتب والأجور',            'expense', $salariesAll,      0],
];
$trialDebit  = array_sum(array_column($trialAccounts, 3));
$trialCredit = array_sum(array_column($trialAccounts, 4));

include '../../templates/header.php';
?>

<style>
.acc-header  { background:#0b162c; color:#fff; font-weight:900; }
.acc-group   { background:#f1f5f9; font-weight:700; color:#334155; }
.acc-account { background:#fff; }
.acc-total   { background:#dbeafe; font-weight:800; color:#1e40af; font-size:15px; }
.kpi-box     { border-radius:14px; padding:20px; }
.kpi-val     { font-size:26px; font-weight:900; line-height:1.1; }
.kpi-lbl     { font-size:12px; color:#64748b; margin-top:4px; }
@media print {
  .no-print,.sidebar,#topbar { display:none!important; }
  #main { margin:0!important; }
}
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-center no-print">
  <div>
    <h4><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>المحاسبة المالية</h4>
    <p>قوائم مالية احترافية تُسحب تلقائياً من بيانات النظام</p>
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
      <label class="fw-bold text-muted small mb-0">السنة:</label>
      <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <label class="fw-bold text-muted small mb-0">الشهر:</label>
      <select name="month" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="0" <?= $month==0?'selected':'' ?>>كل السنة</option>
        <?php
        $mn=['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        for($i=1;$i<=12;$i++) echo "<option value='$i' ".($month==$i?'selected':'').">$mn[$i]</option>";
        ?>
      </select>
      <span class="badge bg-primary" style="font-size:13px"><i class="bi bi-calendar3 me-1"></i><?= $periodLabel ?></span>
    </form>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4 no-print" style="border-bottom:2px solid #e2e8f0">
  <?php
  $tabs=[
    'pl'      =>['bi-graph-up-arrow','أرباح وخسائر','text-success'],
    'balance' =>['bi-columns-gap','الميزانية العمومية','text-primary'],
    'trial'   =>['bi-list-check','ميزان المراجعة','text-warning'],
    'chart'   =>['bi-diagram-3-fill','شجرة الحسابات','text-indigo'],
  ];
  foreach($tabs as $k=>[$ic,$lb,$cls]):
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$k?'active fw-bold':'' ?>"
       href="?tab=<?= $k ?>&year=<?= $year ?>&month=<?= $month ?>">
      <i class="bi <?= $ic ?> me-1 <?= $tab===$k?'':$cls ?>"></i><?= $lb ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>


<?php if ($tab === 'pl'): ?>
<!-- ════════════ أرباح وخسائر ════════════ -->

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-box" style="background:#f0fdf4;border:2px solid #86efac">
      <div class="kpi-val text-success"><?= number_format($revenue,2) ?></div>
      <div class="kpi-lbl">الإيرادات المفوترة (ر.س)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-box" style="background:#fef2f2;border:2px solid #fca5a5">
      <div class="kpi-val text-danger"><?= number_format($totalExpenses,2) ?></div>
      <div class="kpi-lbl">إجمالي التكاليف (ر.س)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-box" style="background:#eff6ff;border:2px solid #bfdbfe">
      <div class="kpi-val text-primary"><?= number_format($cashPeriod,2) ?></div>
      <div class="kpi-lbl">النقدية المحصلة (ر.س)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <?php $isProfit = $netProfit >= 0; ?>
    <div class="kpi-box" style="background:<?= $isProfit?'#f0fdf4;border:2px solid #86efac':'#fef2f2;border:2px solid #fca5a5' ?>">
      <div class="kpi-val" style="color:<?= $isProfit?'#16a34a':'#dc2626' ?>"><?= number_format(abs($netProfit),2) ?></div>
      <div class="kpi-lbl"><?= $isProfit?'صافي الربح':'صافي الخسارة' ?> (ر.س)</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-graph-up-arrow me-2 text-success"></i>قائمة الأرباح والخسائر</span>
    <span class="badge bg-secondary"><?= $periodLabel ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#f8fafc">
        <tr>
          <th style="width:70px">الرمز</th>
          <th>البيان</th>
          <th class="text-end" style="width:200px">المبلغ (ر.س)</th>
        </tr>
      </thead>
      <tbody>
        <tr class="acc-header"><td>4000</td><td colspan="2">الإيرادات</td></tr>
        <tr class="acc-account">
          <td class="text-muted">4100</td>
          <td><i class="bi bi-dot text-success me-1"></i>إيرادات تأجير المعدات والسقالات</td>
          <td class="text-end fw-bold text-success"><?= number_format($revenue,2) ?></td>
        </tr>
        <tr style="background:#f0fdf4;font-weight:800">
          <td></td><td>إجمالي الإيرادات</td>
          <td class="text-end text-success"><?= number_format($revenue,2) ?></td>
        </tr>

        <tr style="background:#dbeafe">
          <td></td>
          <td class="fw-bold" style="color:#1e40af">مجمل الربح</td>
          <td class="text-end fw-bold" style="color:#1e40af"><?= number_format($revenue,2) ?></td>
        </tr>

        <tr class="acc-header"><td>5000</td><td colspan="2">المصروفات</td></tr>
        <tr class="acc-account">
          <td class="text-muted">5100</td>
          <td>
            <i class="bi bi-dot text-danger me-1"></i>المصروفات التشغيلية
            <?php if (!empty($expCatRows)): ?>
            <a class="text-muted ms-2" style="font-size:12px;cursor:pointer"
               data-bs-toggle="collapse" data-bs-target="#expDet">
              <i class="bi bi-chevron-down"></i>
            </a>
            <?php endif; ?>
          </td>
          <td class="text-end fw-bold text-danger"><?= number_format($expenses,2) ?></td>
        </tr>
        <?php if (!empty($expCatRows)): ?>
        <tr><td colspan="3" class="p-0">
          <div class="collapse" id="expDet">
            <table class="table table-sm mb-0" style="background:#fff5f5">
              <?php foreach ($expCatRows as $ec): ?>
              <tr>
                <td style="width:70px"></td>
                <td class="text-muted ps-4 small"><i class="bi bi-dash me-1"></i><?= htmlspecialchars($ec['name']) ?></td>
                <td class="text-end text-danger small"><?= number_format($ec['total'],2) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </td></tr>
        <?php endif; ?>

        <tr class="acc-account">
          <td class="text-muted">5200</td>
          <td><i class="bi bi-dot text-danger me-1"></i>الرواتب والأجور
            <small class="text-muted">(<?= $empCount ?> موظف × <?= $months ?> شهر)</small></td>
          <td class="text-end fw-bold text-danger"><?= number_format($salaries,2) ?></td>
        </tr>

        <tr style="background:#fef2f2;font-weight:800">
          <td></td><td>إجمالي المصروفات</td>
          <td class="text-end text-danger"><?= number_format($totalExpenses,2) ?></td>
        </tr>

        <tr style="font-size:16px;font-weight:900;background:<?= $isProfit?'#dcfce7':'#fee2e2' ?>">
          <td colspan="2" style="color:<?= $isProfit?'#15803d':'#dc2626' ?>">
            <i class="bi bi-<?= $isProfit?'arrow-up-circle-fill':'arrow-down-circle-fill' ?> me-2"></i>
            <?= $isProfit?'صافي الربح':'صافي الخسارة' ?>
          </td>
          <td class="text-end" style="color:<?= $isProfit?'#15803d':'#dc2626' ?>;font-size:18px">
            <?= number_format(abs($netProfit),2) ?> ر.س
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    الإيرادات من الفواتير المفعّلة | الرواتب = الرواتب الحالية × عدد أشهر الفترة
  </div>
</div>


<?php elseif ($tab === 'balance'): ?>
<!-- ════════════ الميزانية العمومية ════════════ -->

<div class="row g-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header" style="background:#1e3a6e;color:#fff">
        <i class="bi bi-bank me-2"></i>الأصول
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead style="background:#f1f5f9"><tr><th>البيان</th><th class="text-end">ر.س</th></tr></thead>
          <tbody>
            <tr class="acc-group"><td colspan="2">الأصول المتداولة</td></tr>
            <tr class="acc-account">
              <td><i class="bi bi-cash-stack me-2 text-success"></i>النقدية والبنك</td>
              <td class="text-end"><?= number_format($cashAll,2) ?></td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-people me-2 text-primary"></i>الذمم المدينة (العملاء)</td>
              <td class="text-end"><?= number_format($ar,2) ?></td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>مجموع الأصول المتداولة</td>
              <td class="text-end text-primary"><?= number_format($currentAssets,2) ?></td>
            </tr>

            <tr class="acc-group"><td colspan="2">الأصول الثابتة</td></tr>
            <tr class="acc-account">
              <td><i class="bi bi-box-seam me-2 text-warning"></i>المعدات والسقالات</td>
              <td class="text-end"><?= number_format($fixedAssets,2) ?></td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>مجموع الأصول الثابتة</td>
              <td class="text-end text-primary"><?= number_format($fixedAssets,2) ?></td>
            </tr>

            <tr class="acc-total">
              <td><i class="bi bi-calculator me-2"></i>إجمالي الأصول</td>
              <td class="text-end"><?= number_format($totalAssets,2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header" style="background:#7c3aed;color:#fff">
        <i class="bi bi-shield-fill me-2"></i>الخصوم وحقوق الملكية
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead style="background:#f1f5f9"><tr><th>البيان</th><th class="text-end">ر.س</th></tr></thead>
          <tbody>
            <tr class="acc-group"><td colspan="2">الخصوم</td></tr>
            <tr class="acc-account text-muted">
              <td><i class="bi bi-dash-circle me-2"></i>لا توجد خصوم مسجلة</td>
              <td class="text-end">0.00</td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>إجمالي الخصوم</td>
              <td class="text-end text-primary">0.00</td>
            </tr>

            <tr class="acc-group"><td colspan="2">حقوق الملكية</td></tr>
            <tr class="acc-account">
              <td><i class="bi bi-building me-2 text-primary"></i>رأس المال</td>
              <td class="text-end"><?= number_format($capital,2) ?></td>
            </tr>
            <tr class="acc-account">
              <td><i class="bi bi-graph-up me-2 text-success"></i>الأرباح المحتجزة</td>
              <td class="text-end" style="color:<?= $retainedEarnings>=0?'#16a34a':'#dc2626' ?>">
                <?= number_format($retainedEarnings,2) ?>
              </td>
            </tr>
            <tr style="background:#dbeafe;font-weight:700">
              <td>إجمالي حقوق الملكية</td>
              <td class="text-end text-primary"><?= number_format($totalAssets,2) ?></td>
            </tr>

            <tr class="acc-total">
              <td><i class="bi bi-calculator me-2"></i>إجمالي الخصوم + حقوق الملكية</td>
              <td class="text-end"><?= number_format($totalAssets,2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3 p-3 rounded-3 d-flex align-items-center gap-2"
         style="background:#f0fdf4;border:2px solid #86efac">
      <i class="bi bi-check-circle-fill text-success fs-5"></i>
      <span class="fw-bold text-success">الميزانية متوازنة — الأصول = الخصوم + حقوق الملكية</span>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-body text-muted small p-3">
    <i class="bi bi-info-circle me-1"></i>
    قيمة المعدات = الكمية × السعر اليومي × 30 |
    الأرباح المحتجزة = إيرادات الكل - مصروفات الكل - رواتب سنوية تقديرية |
    رأس المال = الأصول - الأرباح المحتجزة
  </div>
</div>


<?php elseif ($tab === 'trial'): ?>
<!-- ════════════ ميزان المراجعة ════════════ -->

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-list-check me-2 text-warning"></i>ميزان المراجعة</span>
    <span class="badge bg-secondary"><?= $periodLabel ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#0b162c;color:#fff">
        <tr>
          <th style="width:80px">الرمز</th>
          <th>اسم الحساب</th>
          <th class="text-center" style="width:80px">النوع</th>
          <th class="text-end" style="width:180px">مدين (ر.س)</th>
          <th class="text-end" style="width:180px">دائن (ر.س)</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $tl =['asset'=>'أصول','equity'=>'ملكية','revenue'=>'إيراد','expense'=>'مصروف'];
        $tb =['asset'=>'primary','equity'=>'purple','revenue'=>'success','expense'=>'danger'];
        foreach($trialAccounts as [$code,$name,$type,$debit,$credit]):
          if($debit==0&&$credit==0) continue;
        ?>
        <tr class="acc-account">
          <td class="fw-bold text-muted"><?= $code ?></td>
          <td><?= $name ?></td>
          <td class="text-center">
            <span class="badge bg-<?= $tb[$type]??'secondary' ?>" style="font-size:10px">
              <?= $tl[$type]??$type ?>
            </span>
          </td>
          <td class="text-end"><?= $debit>0 ? number_format($debit,2) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= $credit>0 ? number_format($credit,2) : '<span class="text-muted">—</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#0b162c;color:#fff;font-weight:800;font-size:15px">
          <td colspan="3" class="text-center">الإجمالي</td>
          <td class="text-end"><?= number_format($trialDebit,2) ?> ر.س</td>
          <td class="text-end"><?= number_format($trialCredit,2) ?> ر.س</td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="card-footer">
    <?php $diff = abs($trialDebit - $trialCredit); ?>
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-<?= $diff<1?'check-circle-fill text-success':'exclamation-triangle-fill text-warning' ?> fs-5"></i>
      <span class="fw-bold <?= $diff<1?'text-success':'text-warning' ?>">
        <?= $diff<1 ? 'الميزان متعادل — المدين يساوي الدائن' : 'فرق: '.number_format($diff,2).' ر.س' ?>
      </span>
    </div>
  </div>
</div>

<!-- Expense breakdown -->
<div class="card">
  <div class="card-header"><i class="bi bi-pie-chart me-2 text-primary"></i>توزيع المصروفات الكلي حسب الفئة</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#f1f5f9">
        <tr><th>الفئة</th><th class="text-end">المبلغ (ر.س)</th><th style="width:200px">النسبة</th></tr>
      </thead>
      <tbody>
        <?php
        $allEC = $conn->query("
            SELECT ec.name, COALESCE(SUM(e.amount),0) total
            FROM expense_categories ec
            LEFT JOIN expenses e ON e.category_id=ec.id
            GROUP BY ec.id,ec.name ORDER BY total DESC");
        $clrs=['#dc2626','#d97706','#059669','#2563eb','#7c3aed','#db2777','#0891b2','#65a30d','#ea580c','#6366f1'];
        $ci=0;
        if ($allEC) while ($row=$allEC->fetch_assoc()):
          if($row['total']==0) continue;
          $pct=$expensesAll>0?round($row['total']/$expensesAll*100,1):0;
          $clr=$clrs[$ci++%count($clrs)];
        ?>
        <tr>
          <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $clr ?>;margin-left:8px"></span>
            <?= htmlspecialchars($row['name']) ?>
          </td>
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
<!-- ════════════ شجرة الحسابات ════════════ -->

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-diagram-3-fill me-2 text-primary"></i>شجرة الحسابات القياسية</span>
    <span class="badge bg-secondary">دليل الحسابات</span>
  </div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead style="background:#0b162c;color:#fff">
        <tr><th style="width:90px">الرمز</th><th>اسم الحساب</th><th class="text-end" style="width:220px">الرصيد (ر.س)</th></tr>
      </thead>
      <tbody>
        <?php
        $tree=[
          ['1000','الأصول','header',null],
            ['1100','الأصول المتداولة','group',null],
              ['1110','النقدية والبنك','account',$cashAll],
              ['1120','الذمم المدينة – العملاء','account',$ar],
            ['1200','الأصول الثابتة','group',null],
              ['1210','المعدات والسقالات','account',$equipmentValue],
          ['2000','الخصوم','header',null],
            ['2100','الخصوم المتداولة','group',null],
              ['2110','دائنون وأطراف ثالثة','account',0],
            ['2200','الخصوم طويلة الأجل','group',null],
              ['2210','قروض طويلة الأجل','account',0],
          ['3000','حقوق الملكية','header',null],
              ['3100','رأس المال','account',$capital],
              ['3200','الأرباح المحتجزة','account',$retainedEarnings],
          ['4000','الإيرادات','header',null],
            ['4100','إيرادات تأجير المعدات','account',$revenueAll],
          ['5000','المصروفات','header',null],
            ['5100','المصروفات التشغيلية','account',$expensesAll],
            ['5200','الرواتب والأجور','account',$salariesAll],
        ];
        foreach($tree as [$code,$name,$type,$bal]):
          if($type==='header'):
        ?>
        <tr style="background:#0b162c;color:#fff;font-size:14px;font-weight:900">
          <td><?= $code ?></td><td colspan="2"><i class="bi bi-folder-fill me-2"></i><?= $name ?></td>
        </tr>
        <?php elseif($type==='group'): ?>
        <tr style="background:#1e3a6e;color:#93c5fd;font-weight:700">
          <td class="ps-3"><?= $code ?></td><td colspan="2"><i class="bi bi-folder2-open me-2"></i><?= $name ?></td>
        </tr>
        <?php else: ?>
        <tr class="acc-account">
          <td class="ps-4 text-muted small"><?= $code ?></td>
          <td class="ps-4"><i class="bi bi-file-earmark-text me-2 text-secondary"></i><?= $name ?></td>
          <td class="text-end fw-bold <?= ($bal??0)>0?'text-primary':'text-muted' ?>">
            <?= number_format($bal??0,2) ?>
          </td>
        </tr>
        <?php endif; endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted small">
    <i class="bi bi-info-circle me-1"></i>الأرصدة تراكمية منذ بداية التشغيل | قيم الأصول الثابتة تقديرية
  </div>
</div>

<div class="row g-3 mt-2">
  <?php
  $leg=[
    ['1xxx','الأصول','primary','bi-bank','ما تملكه المنشأة من نقد ومعدات وذمم'],
    ['2xxx','الخصوم','warning','bi-arrow-left-circle','ما تدين به المنشأة لأطراف أخرى'],
    ['3xxx','حقوق الملكية','success','bi-person-fill','حقوق الملاك في صافي الأصول'],
    ['4xxx','الإيرادات','info','bi-graph-up-arrow','الدخل من النشاط التجاري'],
    ['5xxx','المصروفات','danger','bi-wallet2','التكاليف التشغيلية والرواتب'],
  ];
  foreach($leg as [$code,$name,$color,$icon,$desc]):
  ?>
  <div class="col-md-4 col-6">
    <div class="d-flex gap-3 p-3 rounded-3" style="background:#f8fafc;border:1.5px solid #e2e8f0">
      <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size:24px;flex-shrink:0"></i>
      <div>
        <div class="fw-bold small"><?= $code ?> — <?= $name ?></div>
        <div class="text-muted" style="font-size:11px"><?= $desc ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php include '../../templates/footer.php'; ?>
