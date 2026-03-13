<?php
include '../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid contract ID.');

$contract = $conn->query("
    SELECT c.*, cl.name client_name, cl.phone client_phone, cl.address client_address
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    WHERE c.id = $id
")->fetch_assoc();

if (!$contract) die('Contract not found.');

$items = $conn->query("
    SELECT ci.*, e.name equip_name
    FROM contract_items ci
    JOIN equipment e ON ci.equipment_id = e.id
    WHERE ci.contract_id = $id
");
$payments = $conn->query("SELECT * FROM payments WHERE contract_id=$id ORDER BY payment_date");
$paid     = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE contract_id=$id")->fetch_assoc()['s'];
$days     = max(1, (strtotime($contract['end_date']) - strtotime($contract['start_date'])) / 86400);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>عقد رقم #<?= $id ?> - نظام السقالات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<style>
  body { font-family: 'Cairo', sans-serif; padding: 30px; background: white; }
  .company-header { border-bottom: 3px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
  .signature-box { border-top: 1px solid #000; margin-top: 50px; padding-top: 10px; text-align: center; }
  @media print {
    .no-print { display: none !important; }
    body { padding: 15px; }
  }
</style>
</head>
<body>

<!-- Company Header -->
<div class="company-header d-flex justify-content-between align-items-center">
  <div>
    <h3 class="mb-0 fw-bold">نظام إدارة السقالات</h3>
    <p class="text-muted mb-0">نظام إدارة تأجير المعدات</p>
  </div>
  <div class="text-start">
    <h4 class="mb-0">عقد إيجار</h4>
    <div class="fs-5 fw-bold text-primary">#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></div>
  </div>
</div>

<!-- Client & Contract Info -->
<div class="row mb-4">
  <div class="col-md-6">
    <table class="table table-borderless table-sm">
      <tr><td class="text-muted fw-bold" style="width:120px">العميل:</td><td><?= htmlspecialchars($contract['client_name']) ?></td></tr>
      <tr><td class="text-muted fw-bold">الجوال:</td><td><?= htmlspecialchars($contract['client_phone']) ?></td></tr>
      <tr><td class="text-muted fw-bold">العنوان:</td><td><?= htmlspecialchars($contract['client_address']) ?></td></tr>
    </table>
  </div>
  <div class="col-md-6">
    <table class="table table-borderless table-sm">
      <tr><td class="text-muted fw-bold" style="width:120px">تاريخ البداية:</td><td><?= $contract['start_date'] ?></td></tr>
      <tr><td class="text-muted fw-bold">تاريخ الانتهاء:</td><td><?= $contract['end_date'] ?></td></tr>
      <tr><td class="text-muted fw-bold">المدة:</td><td><?= $days ?> يوم</td></tr>
      <tr><td class="text-muted fw-bold">الحالة:</td><td><?= $contract['status'] == 'active' ? 'نشط' : 'منتهي' ?></td></tr>
    </table>
  </div>
</div>

<!-- Equipment Items -->
<h6 class="fw-bold mb-2">بنود المعدات:</h6>
<table class="table table-bordered">
  <thead class="table-dark">
    <tr><th>#</th><th>المعدة</th><th>الكمية</th><th>السعر/اليوم</th><th>الأيام</th><th>الإجمالي الفرعي</th></tr>
  </thead>
  <tbody>
    <?php $i = 1; while ($item = $items->fetch_assoc()): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= htmlspecialchars($item['equip_name']) ?></td>
      <td><?= $item['qty'] ?></td>
      <td><?= number_format($item['price'], 2) ?> ر.س</td>
      <td><?= $days ?></td>
      <td class="fw-bold"><?= number_format($item['qty'] * $item['price'] * $days, 2) ?> ر.س</td>
    </tr>
    <?php endwhile; ?>
  </tbody>
  <tfoot class="table-light">
    <tr>
      <td colspan="5" class="text-end fw-bold">إجمالي العقد:</td>
      <td class="fw-bold fs-6"><?= number_format($contract['total'], 2) ?> ر.س</td>
    </tr>
    <tr>
      <td colspan="5" class="text-end fw-bold">المبلغ المدفوع:</td>
      <td class="fw-bold text-success"><?= number_format($paid, 2) ?> ر.س</td>
    </tr>
    <tr>
      <td colspan="5" class="text-end fw-bold">المتبقي:</td>
      <td class="fw-bold text-danger"><?= number_format($contract['total'] - $paid, 2) ?> ر.س</td>
    </tr>
  </tfoot>
</table>

<!-- Payments Made -->
<?php
$payments->data_seek(0);
if ($payments->num_rows > 0):
?>
<h6 class="fw-bold mb-2 mt-3">سجل الدفعات:</h6>
<table class="table table-bordered table-sm">
  <thead class="table-light">
    <tr><th>التاريخ</th><th>المبلغ</th><th>طريقة الدفع</th></tr>
  </thead>
  <tbody>
    <?php while ($pay = $payments->fetch_assoc()): ?>
    <tr>
      <td><?= $pay['payment_date'] ?></td>
      <td><?= number_format($pay['amount'], 2) ?> ر.س</td>
      <td><?= htmlspecialchars($pay['method']) ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Signatures -->
<div class="row mt-5">
  <div class="col-md-5">
    <div class="signature-box">
      <strong>توقيع العميل</strong><br>
      <small class="text-muted"><?= htmlspecialchars($contract['client_name']) ?></small>
    </div>
  </div>
  <div class="col-md-5 ms-auto">
    <div class="signature-box">
      <strong>توقيع الشركة</strong><br>
      <small class="text-muted">نظام إدارة السقالات</small>
    </div>
  </div>
</div>

<div class="text-center mt-4 no-print">
  <button onclick="window.print()" class="btn btn-primary me-2">
    <i class="bi bi-printer me-1"></i> طباعة
  </button>
  <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">رجوع</a>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
