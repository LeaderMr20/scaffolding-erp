<?php
include '../../config/db.php';

// Save expense
if (isset($_POST['save'])) {
    $stmt = $conn->prepare("INSERT INTO expenses(title, amount, expense_date) VALUES(?, ?, ?)");
    $stmt->bind_param('sds', $_POST['title'], $_POST['amount'], $_POST['expense_date']);
    $stmt->execute();
    header('Location: index.php?saved=1&month=' . substr($_POST['expense_date'], 0, 7));
    exit;
}

// Delete expense
if (isset($_GET['del'])) {
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
    $stmt->bind_param('i', $_GET['del']);
    $stmt->execute();
    header('Location: index.php?month=' . ($_GET['month'] ?? date('Y-m')));
    exit;
}

// Month filter
$month = $_GET['month'] ?? date('Y-m');
$ym    = explode('-', $month);
$where = "WHERE YEAR(expense_date)={$ym[0]} AND MONTH(expense_date)={$ym[1]}";

$expenses    = $conn->query("SELECT * FROM expenses $where ORDER BY expense_date DESC");
$total       = $conn->query("SELECT COALESCE(SUM(amount), 0) s FROM expenses $where")->fetch_assoc()['s'];
$totalAll    = $conn->query("SELECT COALESCE(SUM(amount), 0) s FROM expenses")->fetch_assoc()['s'];

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-wallet2 text-danger me-2"></i> المصروفات</h4>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addForm">
    <i class="bi bi-plus-lg me-1"></i> إضافة مصروف
  </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i> تم حفظ المصروف بنجاح. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="collapse mb-4 <?= !isset($_GET['saved']) ? 'show' : '' ?>" id="addForm">
  <div class="card card-body">
    <form method="post">
      <div class="row g-2">
        <div class="col-md-5">
          <input name="title" placeholder="وصف المصروف" class="form-control" required>
        </div>
        <div class="col-md-3">
          <div class="input-group">
            <input name="amount" placeholder="المبلغ" type="number" step="0.01" min="0.01" class="form-control" required>
            <span class="input-group-text">ر.س</span>
          </div>
        </div>
        <div class="col-md-3">
          <input name="expense_date" type="date" class="form-control" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-1">
          <button name="save" class="btn btn-danger w-100" title="Save">
            <i class="bi bi-save"></i>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Month filter + Summary -->
<div class="row g-3 mb-3 align-items-center">
  <div class="col-md-auto">
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="mb-0 fw-bold">الشهر:</label>
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control" style="width:200px">
      <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
    </form>
  </div>
  <div class="col-md-auto">
    <div class="card px-4 py-2 text-center border-danger">
      <div class="text-muted small">هذا الشهر</div>
      <div class="fs-5 fw-bold text-danger"><?= number_format($total, 2) ?> ر.س</div>
    </div>
  </div>
  <div class="col-md-auto">
    <div class="card px-4 py-2 text-center">
      <div class="text-muted small">الإجمالي الكلي</div>
      <div class="fs-5 fw-bold text-secondary"><?= number_format($totalAll, 2) ?> ر.س</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr><th>#</th><th>الوصف</th><th>المبلغ</th><th>التاريخ</th><th></th></tr>
      </thead>
      <tbody>
        <?php
        $has = false;
        while ($row = $expenses->fetch_assoc()):
            $has = true;
        ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td class="text-danger fw-bold"><?= number_format($row['amount'], 2) ?> ر.س</td>
          <td><?= $row['expense_date'] ?></td>
          <td>
            <a href="?del=<?= $row['id'] ?>&month=<?= htmlspecialchars($month) ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('هل تريد حذف هذا المصروف؟')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if (!$has): ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-4">
            <i class="bi bi-wallet2 fs-3 d-block mb-2"></i>
            لا توجد مصروفات مسجلة لهذا الشهر.
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
