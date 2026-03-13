<?php
include '../../config/db.php';
include '../../templates/header.php';

// Month filter
$month = $_GET['month'] ?? date('Y-m');
$ym    = explode('-', $month);
$ymWhere = "AND YEAR(p.payment_date)={$ym[0]} AND MONTH(p.payment_date)={$ym[1]}";

$payments = $conn->query("
    SELECT p.*, cl.name client_name, c.id contract_num
    FROM payments p
    JOIN contracts c ON p.contract_id = c.id
    JOIN clients cl ON c.client_id = cl.id
    WHERE 1=1 $ymWhere
    ORDER BY p.payment_date DESC, p.id DESC
");

$totalPaid = $conn->query("
    SELECT COALESCE(SUM(amount), 0) s FROM payments p
    WHERE 1=1 $ymWhere
")->fetch_assoc()['s'];

$countAll = $conn->query("SELECT COUNT(*) c FROM payments p WHERE 1=1 $ymWhere")->fetch_assoc()['c'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-cash-stack text-success me-2"></i> المدفوعات</h4>
</div>

<!-- Filter + Summary -->
<div class="row g-3 mb-3">
  <div class="col-md-auto">
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="mb-0 fw-bold">الشهر:</label>
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control" style="width:200px">
      <button class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i> تصفية</button>
    </form>
  </div>
  <div class="col-md-auto">
    <div class="card px-4 py-2 text-center border-success">
      <div class="text-muted small">إجمالي المحصّل</div>
      <div class="fs-4 fw-bold text-success"><?= number_format($totalPaid, 2) ?> ر.س</div>
    </div>
  </div>
  <div class="col-md-auto">
    <div class="card px-4 py-2 text-center">
      <div class="text-muted small">عدد المعاملات</div>
      <div class="fs-4 fw-bold"><?= $countAll ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr><th>#</th><th>العقد</th><th>العميل</th><th>التاريخ</th><th>المبلغ</th><th>طريقة الدفع</th></tr>
      </thead>
      <tbody>
        <?php
        $has = false;
        while ($row = $payments->fetch_assoc()):
            $has = true;
        ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td>
            <a href="../contracts/view.php?id=<?= $row['contract_num'] ?>">
              #<?= $row['contract_num'] ?>
            </a>
          </td>
          <td><?= htmlspecialchars($row['client_name']) ?></td>
          <td><?= $row['payment_date'] ?></td>
          <td class="text-success fw-bold"><?= number_format($row['amount'], 2) ?> ر.س</td>
          <td><?= htmlspecialchars($row['method']) ?></td>
        </tr>
        <?php endwhile; ?>
        <?php if (!$has): ?>
        <tr>
          <td colspan="6" class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            لا توجد دفعات لهذا الشهر.
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
