<?php
include '../../config/db.php';
include '../../templates/header.php';

$companyName = getSetting($conn, 'company_name');
$today = date('Y-m-d');
$todayAr = date('d/m/Y');

// ── Summary Stats ─────────────────────────────────────────────────────────
$totalEquip   = (int)$conn->query("SELECT COUNT(*) c FROM equipment")->fetch_assoc()['c'];
$totalQty     = (int)$conn->query("SELECT COALESCE(SUM(quantity),0) c FROM equipment")->fetch_assoc()['c'];
$activeContr  = (int)$conn->query("SELECT COUNT(*) c FROM contracts WHERE status='active'")->fetch_assoc()['c'];
$totalClients = (int)$conn->query("SELECT COUNT(*) c FROM clients")->fetch_assoc()['c'];
$totalRevenue = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments")->fetch_assoc()['s'];
$totalExpense = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM expenses")->fetch_assoc()['s'];

// ── Equipment Inventory Detail ─────────────────────────────────────────────
// Total deployed per equipment (in active contracts)
$equipReport = $conn->query("
    SELECT
        e.id, e.name, e.quantity AS total_qty,
        COALESCE(SUM(ci.qty),0) AS deployed_qty,
        (e.quantity - COALESCE(SUM(ci.qty),0)) AS available_qty
    FROM equipment e
    LEFT JOIN contract_items ci ON ci.equipment_id = e.id
    LEFT JOIN contracts c ON c.id = ci.contract_id AND c.status = 'active'
    GROUP BY e.id, e.name, e.quantity
    ORDER BY deployed_qty DESC, e.name ASC
");

// Deployed items detail: which client has what equipment
$deployedDetail = $conn->query("
    SELECT
        e.id AS equip_id,
        e.name AS equip_name,
        cl.name AS client_name,
        cl.phone AS client_phone,
        ci.qty,
        c.id AS contract_id,
        c.start_date,
        c.end_date,
        DATEDIFF(c.end_date, CURDATE()) AS days_left
    FROM contract_items ci
    JOIN equipment e  ON e.id  = ci.equipment_id
    JOIN contracts c  ON c.id  = ci.contract_id AND c.status = 'active'
    JOIN clients cl   ON cl.id = c.client_id
    ORDER BY e.name ASC, c.end_date ASC
");

// Group deployed detail by equipment id
$deployedByEquip = [];
while ($row = $deployedDetail->fetch_assoc()) {
    $deployedByEquip[$row['equip_id']][] = $row;
}

// ── Monthly Revenue ────────────────────────────────────────────────────────
$monthlyRev = $conn->query("
    SELECT DATE_FORMAT(payment_date,'%Y-%m') AS mon,
           COALESCE(SUM(amount),0) AS total
    FROM payments
    GROUP BY mon ORDER BY mon DESC LIMIT 6
");
$months = [];
while ($r = $monthlyRev->fetch_assoc()) $months[] = $r;
$months = array_reverse($months);
$maxRev = max(array_column($months, 'total') ?: [1]);
?>

<!-- Print styles -->
<style>
@media print {
  @page { margin: 15mm; size: A4; }
  .no-print, .sidebar, .sb-overlay, nav, .page-header-actions { display: none !important; }
  .main-content { margin: 0 !important; padding: 0 !important; }
  .print-page-break { page-break-before: always; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; }
  body { background: #fff !important; }
}
.report-header { display:none; }
@media print { .report-header { display:block; } }

.stat-pill {
  display:flex; align-items:center; gap:14px;
  background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
  padding:16px 20px; transition:.2s;
}
.stat-pill:hover { border-color:#3b82f6; box-shadow:0 4px 16px rgba(59,130,246,.1); }
.pill-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.pill-val  { font-size:22px; font-weight:800; color:#0b162c; line-height:1; }
.pill-lbl  { font-size:11px; color:#64748b; font-weight:600; margin-top:3px; }

.equip-row { background:#fff; border:1.5px solid #e2e8f0; border-radius:12px; margin-bottom:12px; overflow:hidden; }
.equip-head { display:grid; grid-template-columns:1fr repeat(3,120px); align-items:center; padding:14px 20px; gap:8px; cursor:pointer; }
.equip-head:hover { background:#f8fafc; }
.equip-body { border-top:1px solid #f1f5f9; display:none; }
.equip-body.open { display:block; }
.qty-badge { text-align:center; border-radius:8px; padding:6px 14px; font-weight:800; font-size:14px; }
.bar-track { height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden; margin-top:4px; }
.bar-fill  { height:100%; border-radius:4px; background:linear-gradient(90deg,#dc2626,#f97316); transition:.4s; }
.bar-fill.green { background:linear-gradient(90deg,#16a34a,#22c55e); }
</style>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center no-print">
  <div>
    <h4><i class="bi bi-bar-chart-fill me-2 text-primary"></i>التقارير والمخزون</h4>
    <p>نظرة تفصيلية على المعدات والأداء المالي — <?= $todayAr ?></p>
  </div>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary no-print">
      <i class="bi bi-printer-fill me-1"></i> طباعة التقرير
    </button>
  </div>
</div>

<!-- Print Header (shows only on print) -->
<div class="report-header mb-4" style="border-bottom:2px solid #0b162c;padding-bottom:12px">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div style="font-size:22px;font-weight:900;color:#0b162c"><?= htmlspecialchars($companyName) ?></div>
      <div style="font-size:13px;color:#64748b">تقرير المخزون والأداء الشامل</div>
    </div>
    <div style="text-align:left;font-size:12px;color:#64748b">
      <div>تاريخ الطباعة: <?= $todayAr ?></div>
      <div>عدد العقود النشطة: <?= $activeContr ?></div>
    </div>
  </div>
</div>

<!-- ══ Summary Cards ══════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
  <div class="col-md-4 col-6">
    <div class="stat-pill">
      <div class="pill-icon" style="background:#eff6ff;color:#2563eb"><i class="bi bi-tools"></i></div>
      <div>
        <div class="pill-val"><?= $totalEquip ?> <span style="font-size:13px;color:#64748b">صنف</span></div>
        <div class="pill-lbl">إجمالي أصناف المعدات</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6">
    <div class="stat-pill">
      <div class="pill-icon" style="background:#fef2f2;color:#dc2626"><i class="bi bi-arrow-up-right-circle-fill"></i></div>
      <div>
        <div class="pill-val"><?= $totalQty ?> <span style="font-size:13px;color:#64748b">قطعة</span></div>
        <div class="pill-lbl">إجمالي الكميات بالمخزون</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6">
    <div class="stat-pill">
      <div class="pill-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-file-earmark-check-fill"></i></div>
      <div>
        <div class="pill-val"><?= $activeContr ?></div>
        <div class="pill-lbl">عقد نشط حالياً</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6">
    <div class="stat-pill">
      <div class="pill-icon" style="background:#fff7ed;color:#ea580c"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="pill-val"><?= $totalClients ?></div>
        <div class="pill-lbl">إجمالي العملاء</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6">
    <div class="stat-pill">
      <div class="pill-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-graph-up-arrow"></i></div>
      <div>
        <div class="pill-val" style="font-size:17px"><?= number_format($totalRevenue,0) ?></div>
        <div class="pill-lbl">إجمالي الإيرادات (ر.س)</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6">
    <div class="stat-pill">
      <div class="pill-icon" style="background:#fdf2f8;color:#9333ea"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="pill-val" style="font-size:17px;color:<?= ($totalRevenue-$totalExpense)>=0?'#16a34a':'#dc2626' ?>">
          <?= number_format($totalRevenue-$totalExpense,0) ?>
        </div>
        <div class="pill-lbl">صافي الربح (ر.س)</div>
      </div>
    </div>
  </div>
</div>

<!-- ══ Equipment Inventory ════════════════════════════════════════════════ -->
<div class="d-flex align-items-center gap-2 mb-3">
  <div style="width:4px;height:22px;background:linear-gradient(180deg,#2563eb,#7c3aed);border-radius:4px"></div>
  <h5 class="mb-0 fw-800">تفاصيل مخزون المعدات</h5>
  <span class="badge bg-primary ms-1"><?= $totalEquip ?> صنف</span>
</div>

<!-- Summary table for print -->
<div style="display:none" class="print-table">
<table class="table table-bordered table-sm mb-4" style="font-size:12px">
  <thead style="background:#0b162c;color:#fff">
    <tr>
      <th>الصنف</th>
      <th class="text-center">الكمية الإجمالية</th>
      <th class="text-center">المُسلَّم للعملاء</th>
      <th class="text-center">المتاح في المخزون</th>
      <th class="text-center">نسبة الاستخدام</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $equipReport->data_seek(0);
  while ($eq = $equipReport->fetch_assoc()):
      $pct = $eq['total_qty'] > 0 ? round($eq['deployed_qty']/$eq['total_qty']*100) : 0;
  ?>
    <tr>
      <td><strong><?= htmlspecialchars($eq['name']) ?></strong></td>
      <td class="text-center"><?= $eq['total_qty'] ?></td>
      <td class="text-center" style="color:#dc2626;font-weight:700"><?= $eq['deployed_qty'] ?></td>
      <td class="text-center" style="color:#16a34a;font-weight:700"><?= $eq['available_qty'] ?></td>
      <td class="text-center"><?= $pct ?>%</td>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>
</div>
<style>@media print { .print-table { display:block !important; } .interactive-equip { display:none; } }</style>

<!-- Interactive equipment rows (screen only) -->
<div class="interactive-equip mb-4">
<?php
$equipReport->data_seek(0);
while ($eq = $equipReport->fetch_assoc()):
    $pct       = $eq['total_qty'] > 0 ? round($eq['deployed_qty']/$eq['total_qty']*100) : 0;
    $clients   = $deployedByEquip[$eq['id']] ?? [];
    $isOveruse = $eq['available_qty'] < 0;
?>
<div class="equip-row">
  <!-- Header row -->
  <div class="equip-head" onclick="toggleEquip(<?= $eq['id'] ?>)">
    <div>
      <div style="font-weight:800;font-size:15px;color:#0b162c"><?= htmlspecialchars($eq['name']) ?></div>
      <div class="bar-track" style="max-width:200px">
        <div class="bar-fill <?= $pct<=70?'green':'' ?>" style="width:<?= min($pct,100) ?>%"></div>
      </div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px">
        نسبة الاستخدام: <strong><?= $pct ?>%</strong>
        <?= count($clients) > 0 ? ' · ' . count($clients) . ' عميل' : '' ?>
      </div>
    </div>
    <div class="text-center">
      <div class="qty-badge" style="background:#f1f5f9;color:#334155"><?= $eq['total_qty'] ?></div>
      <div style="font-size:10px;color:#94a3b8;margin-top:4px">الإجمالي</div>
    </div>
    <div class="text-center">
      <div class="qty-badge" style="background:#fef2f2;color:#dc2626"><?= $eq['deployed_qty'] ?></div>
      <div style="font-size:10px;color:#94a3b8;margin-top:4px">بالخارج</div>
    </div>
    <div class="text-center">
      <div class="qty-badge" style="background:<?= $isOveruse?'#fef2f2;color:#dc2626':($eq['available_qty']==0?'#fff7ed;color:#d97706':'#f0fdf4;color:#16a34a') ?>"><?= $eq['available_qty'] ?></div>
      <div style="font-size:10px;color:#94a3b8;margin-top:4px">متاح</div>
    </div>
  </div>

  <!-- Clients detail -->
  <?php if (count($clients) > 0): ?>
  <div class="equip-body" id="equip-<?= $eq['id'] ?>">
    <table class="table mb-0" style="font-size:13px">
      <thead style="background:#f8fafc">
        <tr>
          <th style="padding:10px 20px">العميل</th>
          <th style="padding:10px 16px">رقم العقد</th>
          <th style="padding:10px 16px">الكمية المُسلَّمة</th>
          <th style="padding:10px 16px">تاريخ البداية</th>
          <th style="padding:10px 16px">تاريخ الانتهاء</th>
          <th style="padding:10px 16px">الأيام المتبقية</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($clients as $cl): ?>
        <?php
          $dl = (int)$cl['days_left'];
          $dlColor = $dl < 0 ? '#dc2626' : ($dl <= 7 ? '#d97706' : '#16a34a');
          $dlText  = $dl < 0 ? 'متأخر '.abs($dl).' يوم' : ($dl == 0 ? 'ينتهي اليوم' : $dl.' يوم');
        ?>
        <tr>
          <td style="padding:10px 20px">
            <div style="font-weight:700"><?= htmlspecialchars($cl['client_name']) ?></div>
            <?php if ($cl['client_phone']): ?>
            <div style="font-size:11px;color:#64748b"><?= htmlspecialchars($cl['client_phone']) ?></div>
            <?php endif; ?>
          </td>
          <td style="padding:10px 16px">
            <a href="../contracts/view.php?id=<?= $cl['contract_id'] ?>" style="font-weight:700;color:#2563eb">#<?= $cl['contract_id'] ?></a>
          </td>
          <td style="padding:10px 16px">
            <span style="background:#fef2f2;color:#dc2626;font-weight:800;border-radius:8px;padding:4px 14px">
              <?= $cl['qty'] ?> قطعة
            </span>
          </td>
          <td style="padding:10px 16px;color:#64748b"><?= $cl['start_date'] ?></td>
          <td style="padding:10px 16px;color:#64748b"><?= $cl['end_date'] ?></td>
          <td style="padding:10px 16px">
            <span style="background:<?= $dl<0?'#fef2f2':($dl<=7?'#fffbeb':'#f0fdf4') ?>;color:<?= $dlColor ?>;font-weight:700;border-radius:8px;padding:4px 12px;font-size:12px">
              <?= $dlText ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="equip-body" id="equip-<?= $eq['id'] ?>">
    <div style="padding:14px 20px;color:#94a3b8;font-size:13px">
      <i class="bi bi-check-circle-fill text-success me-2"></i>
      هذا الصنف متوفر بالكامل في المخزون — لا يوجد في عقود نشطة حالياً
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endwhile; ?>
</div>

<!-- ══ Monthly Revenue Chart ══════════════════════════════════════════════ -->
<?php if (count($months) > 0): ?>
<div class="print-page-break"></div>
<div class="d-flex align-items-center gap-2 mb-3 mt-2">
  <div style="width:4px;height:22px;background:linear-gradient(180deg,#16a34a,#22c55e);border-radius:4px"></div>
  <h5 class="mb-0 fw-800">الإيرادات الشهرية</h5>
</div>
<div class="card mb-4">
  <div class="card-body">
    <div style="display:flex;align-items:flex-end;gap:12px;height:160px;padding-bottom:8px">
    <?php foreach ($months as $m): ?>
      <?php
        $barH = $maxRev > 0 ? round($m['total']/$maxRev*140) : 4;
        $label = substr($m['mon'], 0, 7);
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="font-size:11px;font-weight:700;color:#16a34a"><?= number_format($m['total'],0) ?></div>
        <div style="width:100%;height:<?= max($barH,4) ?>px;background:linear-gradient(180deg,#22c55e,#16a34a);border-radius:6px 6px 0 0"></div>
        <div style="font-size:10px;color:#94a3b8;white-space:nowrap"><?= $label ?></div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ Deployed Equipment Print Table ════════════════════════════════════ -->
<div style="display:none" class="print-deployed">
<div class="print-page-break"></div>
<div style="font-size:16px;font-weight:800;color:#0b162c;margin-bottom:12px;border-bottom:1px solid #e2e8f0;padding-bottom:6px">
  تفاصيل المعدات المُسلَّمة للعملاء
</div>
<?php foreach ($deployedByEquip as $eid => $rows): ?>
<div style="margin-bottom:16px">
  <div style="font-weight:700;font-size:13px;background:#f1f5f9;padding:6px 10px;border-radius:4px;margin-bottom:6px">
    <?= htmlspecialchars($rows[0]['equip_name']) ?>
  </div>
  <table class="table table-bordered table-sm" style="font-size:11px">
    <thead><tr><th>العميل</th><th>العقد</th><th>الكمية</th><th>تاريخ الانتهاء</th><th>المتبقي</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $dl = (int)$r['days_left'];
      $dlTxt = $dl < 0 ? 'متأخر '.abs($dl).' يوم' : $dl.' يوم';
    ?>
    <tr>
      <td><?= htmlspecialchars($r['client_name']) ?></td>
      <td>#<?= $r['contract_id'] ?></td>
      <td style="text-align:center;font-weight:700"><?= $r['qty'] ?></td>
      <td><?= $r['end_date'] ?></td>
      <td style="color:<?= $dl<0?'#dc2626':($dl<=7?'#d97706':'#16a34a') ?>;font-weight:700"><?= $dlTxt ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>
</div>
<style>@media print { .print-deployed { display:block !important; } }</style>

<!-- Footer -->
<div style="text-align:center;padding:24px 0 8px;color:#94a3b8;font-size:12px;border-top:1px solid #f1f5f9" class="no-print">
  آخر تحديث: <?= $todayAr ?> &nbsp;·&nbsp; <?= htmlspecialchars($companyName) ?>
</div>

<script>
function toggleEquip(id) {
  const body = document.getElementById('equip-' + id);
  if (body) body.classList.toggle('open');
}
// Auto-open rows with deployed equipment
document.addEventListener('DOMContentLoaded', function() {
  <?php foreach ($deployedByEquip as $eid => $rows): ?>
  document.getElementById('equip-<?= $eid ?>').classList.add('open');
  <?php endforeach; ?>
});
</script>

<?php include '../../templates/footer.php'; ?>
