<?php
include '../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$invoice = $conn->query("
    SELECT i.*, c.start_date, c.end_date, c.total contract_total, c.status contract_status,
           cl.name client_name, cl.phone client_phone, cl.address client_address
    FROM invoices i
    JOIN contracts c  ON i.contract_id = c.id
    JOIN clients  cl  ON c.client_id   = cl.id
    WHERE i.id = $id
")->fetch_assoc();

if (!$invoice) { header('Location: index.php'); exit; }

$items    = $conn->query("
    SELECT ci.qty, ci.price, e.name equip_name
    FROM contract_items ci
    JOIN equipment e ON ci.equipment_id = e.id
    WHERE ci.contract_id = {$invoice['contract_id']}
");
$days = max(1, (strtotime($invoice['end_date']) - strtotime($invoice['start_date'])) / 86400);

$paid = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE contract_id={$invoice['contract_id']}")->fetch_assoc()['s'];
$remaining = $invoice['total'] - $paid;
$isActive  = $invoice['status'] === 'active';

// قراءة بيانات الشركة من إعدادات النظام
$zatcaSellerName = getSetting($conn, 'company_name')            ?: 'مؤسسة الجود للسقالات';
$zatcaVatNumber  = getSetting($conn, 'vat_number')              ?: '314325455800012';
$companyPhone    = getSetting($conn, 'company_phone');
$companyAddress  = getSetting($conn, 'company_address');
$companyRegNo    = getSetting($conn, 'commercial_registration');

// بناء QR بصيغة TLV + Base64 المعتمدة من هيئة الزكاة والدخل
function zatcaTlv(int $tag, string $value): string {
    return chr($tag) . chr(strlen($value)) . $value;
}
$vatRate      = 0.15;
$totalIncVat  = (float)$invoice['total'];
$vatAmount    = round($totalIncVat * $vatRate / (1 + $vatRate), 2);
$timestamp    = $invoice['issue_date'] . 'T00:00:00Z';

$tlv = zatcaTlv(1, $zatcaSellerName)
     . zatcaTlv(2, $zatcaVatNumber)
     . zatcaTlv(3, $timestamp)
     . zatcaTlv(4, number_format($totalIncVat, 2, '.', ''))
     . zatcaTlv(5, number_format($vatAmount,   2, '.', ''));

$qrData = base64_encode($tlv);
$qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>فاتورة إلكترونية مبسطة — <?= htmlspecialchars($invoice['invoice_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { font-family: 'Cairo', sans-serif; }
body { background: #f1f5f9; color: #1e293b; }

/* ── Wrapper with full border ── */
.invoice-wrapper {
    max-width: 900px; margin: 30px auto; background: #fff;
    border: 2px solid #0b162c;
    border-radius: 4px;
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    overflow: hidden;
}

/* ── Header ── */
.inv-header { background: linear-gradient(135deg, #0b162c, #1e3a6e); color: #fff; padding: 28px 36px; border-bottom: 3px solid #2563eb; }
.inv-header h1 { font-size: 24px; font-weight: 900; margin: 0; }
.inv-header .inv-sub { font-size: 12px; color: #93c5fd; font-weight: 600; margin-top: 3px; }
.inv-header .inv-type { font-size: 13px; color: #fbbf24; font-weight: 700; margin-top: 6px; }
.inv-body { padding: 0; }

/* ── 3-column info row with borders ── */
.info-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    border-bottom: 2px solid #0b162c;
}
.info-box {
    padding: 18px 20px;
    border-left: 1px solid #cbd5e1;
}
.info-box:last-child { border-left: none; }
.info-box h6 {
    font-size: 11px; font-weight: 800; color: #fff;
    background: #0b162c; margin: -18px -20px 12px;
    padding: 7px 20px; letter-spacing: 0.5px;
}
.info-box p { margin: 4px 0; font-size: 13px; font-weight: 600; color: #1e293b; }
.info-box .val { color: #1d4ed8; font-weight: 700; }

/* ── QR box ── */
.qr-box {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 16px 20px; text-align: center;
    border-left: 1px solid #cbd5e1; min-width: 196px;
}
.qr-box img { display: block; }
.qr-label { font-size: 10px; color: #64748b; font-weight: 700; margin-top: 8px; }

/* ── Items table ── */
.items-section { padding: 0; }
.items-label {
    background: #0b162c; color: #fff; font-size: 12px;
    font-weight: 800; padding: 8px 20px; letter-spacing: 0.5px;
}
table.inv-table { width: 100%; border-collapse: collapse; }
table.inv-table thead th {
    background: #1e3a6e; color: #fff;
    padding: 11px 16px; font-size: 13px; font-weight: 700;
    border: 1px solid #2563eb;
}
table.inv-table tbody td {
    padding: 11px 16px;
    font-size: 14px; font-weight: 500;
    border: 1px solid #cbd5e1;
}
table.inv-table tbody tr:nth-child(even) { background: #f8fafc; }
table.inv-table tfoot td {
    padding: 10px 16px; font-size: 13px;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
}

/* ── Totals ── */
.totals-section {
    display: flex; justify-content: flex-end;
    border-top: 2px solid #0b162c;
}
table.totals-table { border-collapse: collapse; min-width: 320px; }
table.totals-table td {
    padding: 10px 20px; font-size: 14px; font-weight: 600;
    border: 1px solid #cbd5e1;
}
table.totals-table tr:last-child td {
    font-size: 15px; font-weight: 900;
    background: #0b162c; color: #fff;
}
table.totals-table .lbl { color: #64748b; background: #f8fafc; }
table.totals-table tr:last-child .lbl { color: #93c5fd; background: #0b162c; }

/* ── Signatures ── */
.sign-section {
    display: grid; grid-template-columns: 1fr 1fr;
    border-top: 2px solid #0b162c;
}
.sign-box { text-align: center; padding: 24px 20px; }
.sign-box:first-child { border-left: 1px solid #cbd5e1; }
.sign-box .sign-line { border-top: 1px dashed #94a3b8; margin-top: 48px; padding-top: 8px; font-size: 13px; color: #64748b; font-weight: 600; }

/* ── Cancelled ── */
.status-cancelled {
    background: #fee2e2; border-bottom: 2px solid #dc2626; color: #dc2626;
    padding: 12px 20px; text-align: center; font-size: 15px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; gap: 10px;
}

/* ── Footer ── */
.inv-footer {
    text-align: center; padding: 14px; font-size: 11px; color: #94a3b8;
    border-top: 1px solid #e2e8f0; background: #f8fafc;
}

.no-print { }
@media print {
    @page { margin: 0; size: A4; }
    body { background: #fff; padding: 10px; }
    .invoice-wrapper { box-shadow: none; margin: 0; border-radius: 0; }
    .no-print { display: none !important; }
}
@media (max-width: 640px) {
    .info-row { grid-template-columns: 1fr; }
    .qr-box { border-left: none; border-top: 1px solid #cbd5e1; }
    .inv-header { padding: 20px 16px; }
}
</style>
</head>
<body>

<!-- Action bar -->
<div class="no-print d-flex gap-2 justify-content-center py-3">
  <button onclick="window.print()" class="btn btn-primary">
    <i class="bi bi-printer me-1"></i> طباعة
  </button>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-right me-1"></i> قائمة الفواتير
  </a>
  <a href="../contracts/view.php?id=<?= $invoice['contract_id'] ?>" class="btn btn-outline-dark">
    <i class="bi bi-file-earmark-text me-1"></i> عرض العقد
  </a>
</div>

<div class="invoice-wrapper">

  <!-- Header -->
  <div class="inv-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h1><i class="bi bi-building me-2"></i><?= htmlspecialchars($zatcaSellerName) ?></h1>
        <?php if ($companyAddress): ?><div class="inv-sub"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($companyAddress) ?></div><?php endif; ?>
        <?php if ($companyPhone): ?><div class="inv-sub"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($companyPhone) ?></div><?php endif; ?>
        <div class="inv-type"><i class="bi bi-receipt me-1"></i>فاتورة إلكترونية مبسطة</div>
      </div>
      <div class="text-end">
        <div style="font-size:22px;font-weight:900;color:#fbbf24;letter-spacing:1px">
          <?= htmlspecialchars($invoice['invoice_number']) ?>
        </div>
        <div style="font-size:13px;color:#93c5fd;margin-top:4px">
          <i class="bi bi-calendar3 me-1"></i> تاريخ الإصدار: <?= $invoice['issue_date'] ?>
        </div>
        <div style="margin-top:8px">
          <?php if ($isActive): ?>
          <span style="background:rgba(34,197,94,.2);color:#4ade80;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:800">
            <i class="bi bi-check-circle me-1"></i> فاتورة نشطة
          </span>
          <?php else: ?>
          <span style="background:rgba(239,68,68,.2);color:#fca5a5;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:800">
            <i class="bi bi-x-circle me-1"></i> فاتورة ملغاة
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$isActive): ?>
  <div class="status-cancelled">
    <i class="bi bi-slash-circle-fill"></i>
    هذه الفاتورة ملغاة — تم إلغاؤها بسبب إغلاق أو حذف العقد المرتبط
  </div>
  <?php endif; ?>

  <!-- 3-column info row -->
  <div class="info-row">
    <div class="info-box">
      <h6>بيانات العميل</h6>
      <p class="val"><?= htmlspecialchars($invoice['client_name']) ?></p>
      <?php if ($invoice['client_phone']): ?>
      <p><i class="bi bi-telephone me-1 text-muted"></i><?= htmlspecialchars($invoice['client_phone']) ?></p>
      <?php endif; ?>
      <?php if ($invoice['client_address']): ?>
      <p><i class="bi bi-geo-alt me-1 text-muted"></i><?= htmlspecialchars($invoice['client_address']) ?></p>
      <?php endif; ?>
    </div>

    <div class="qr-box">
      <img src="<?= $qrUrl ?>" width="155" height="155" alt="QR Code">
      <div class="qr-label">امسح للتحقق</div>
    </div>

    <div class="info-box">
      <h6>تفاصيل العقد</h6>
      <p>رقم العقد: <span class="val">#<?= $invoice['contract_id'] ?></span></p>
      <p>من: <span class="val"><?= $invoice['start_date'] ?></span></p>
      <p>إلى: <span class="val"><?= $invoice['end_date'] ?></span></p>
      <p>المدة: <span class="val"><?= $days ?> يوم</span></p>
    </div>
  </div>

  <!-- Items table -->
  <div class="items-section">
    <div class="items-label"><i class="bi bi-tools me-1"></i> بنود الفاتورة</div>
    <table class="inv-table">
      <thead>
        <tr>
          <th style="width:40px;text-align:center">#</th>
          <th>المعدة</th>
          <th style="text-align:center;width:90px">الكمية</th>
          <th style="text-align:center;width:140px">سعر اليوم (ر.س)</th>
          <th style="text-align:center;width:80px">الأيام</th>
          <th style="text-align:center;width:150px">الإجمالي (ر.س)</th>
        </tr>
      </thead>
      <tbody>
        <?php $n=1; while ($item = $items->fetch_assoc()): $sub = $item['qty'] * $item['price'] * $days; ?>
        <tr>
          <td style="text-align:center;color:#64748b"><?= $n++ ?></td>
          <td><strong><?= htmlspecialchars($item['equip_name']) ?></strong></td>
          <td style="text-align:center"><?= number_format($item['qty']) ?></td>
          <td style="text-align:center"><?= number_format($item['price'], 2) ?></td>
          <td style="text-align:center"><?= $days ?></td>
          <td style="text-align:center;font-weight:700;color:#1d4ed8"><?= number_format($sub, 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="totals-section">
    <table class="totals-table">
      <tr>
        <td class="lbl">إجمالي الفاتورة</td>
        <td style="font-weight:700;text-align:center"><?= number_format($invoice['total'], 2) ?> ر.س</td>
      </tr>
      <tr>
        <td class="lbl">المبلغ المدفوع</td>
        <td style="color:#16a34a;font-weight:700;text-align:center"><?= number_format($paid, 2) ?> ر.س</td>
      </tr>
      <tr>
        <td><?= $remaining > 0 ? 'المبلغ المتبقي' : 'مسدد بالكامل' ?></td>
        <td style="text-align:center;color:<?= $remaining > 0 ? '#ef4444' : '#4ade80' ?>">
          <?= number_format(abs($remaining), 2) ?> ر.س
        </td>
      </tr>
    </table>
  </div>

  <!-- Signatures -->
  <div class="sign-section">
    <div class="sign-box">
      <div class="sign-line">توقيع العميل<br><?= htmlspecialchars($invoice['client_name']) ?></div>
    </div>
    <div class="sign-box">
      <div class="sign-line">توقيع الشركة<br><?= htmlspecialchars($zatcaSellerName) ?></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="inv-footer">
    تم إصدار هذه الفاتورة الإلكترونية المبسطة بتاريخ <?= $invoice['issue_date'] ?> — <?= htmlspecialchars($zatcaSellerName) ?> &copy; <?= date('Y') ?>
    &nbsp;|&nbsp; الرقم الضريبي: <?= htmlspecialchars($zatcaVatNumber) ?>
    <?= $companyRegNo ? ' &nbsp;|&nbsp; السجل التجاري: ' . htmlspecialchars($companyRegNo) : '' ?>
  </div>

</div>

</body>
</html>
