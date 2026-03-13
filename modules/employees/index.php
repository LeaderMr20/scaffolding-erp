<?php
include '../../config/db.php';

// Save employee
if (isset($_POST['save'])) {
    $stmt = $conn->prepare("INSERT INTO employees(name, phone, salary) VALUES(?, ?, ?)");
    $stmt->bind_param('ssd', $_POST['name'], $_POST['phone'], $_POST['salary']);
    $stmt->execute();
    header('Location: index.php?saved=1');
    exit;
}

// Delete employee
if (isset($_GET['del'])) {
    $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
    $stmt->bind_param('i', $_GET['del']);
    $stmt->execute();
    header('Location: index.php');
    exit;
}

$employees    = $conn->query("SELECT * FROM employees ORDER BY name");
$total_salary = $conn->query("SELECT COALESCE(SUM(salary), 0) s FROM employees")->fetch_assoc()['s'];
$count        = $conn->query("SELECT COUNT(*) c FROM employees")->fetch_assoc()['c'];

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-person-badge-fill text-purple me-2"></i> الموظفون</h4>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addForm">
    <i class="bi bi-plus-lg me-1"></i> إضافة موظف
  </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i> تم حفظ الموظف بنجاح. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="collapse mb-4 <?= !isset($_GET['saved']) ? 'show' : '' ?>" id="addForm">
  <div class="card card-body">
    <form method="post">
      <div class="row g-2">
        <div class="col-md-5">
          <input name="name" placeholder="الاسم الكامل" class="form-control" required>
        </div>
        <div class="col-md-3">
          <input name="phone" placeholder="رقم الجوال" class="form-control">
        </div>
        <div class="col-md-3">
          <div class="input-group">
            <input name="salary" placeholder="الراتب الشهري" type="number" step="0.01" min="0" class="form-control" required>
            <span class="input-group-text">ر.س</span>
          </div>
        </div>
        <div class="col-md-1">
          <button name="save" class="btn btn-success w-100" title="Save">
            <i class="bi bi-save"></i>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card text-center px-3 py-2">
      <div class="text-muted small">إجمالي الموظفين</div>
      <div class="fs-3 fw-bold text-primary"><?= $count ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-center px-3 py-2">
      <div class="text-muted small">إجمالي الرواتب الشهرية</div>
      <div class="fs-3 fw-bold text-danger"><?= number_format($total_salary, 2) ?> ر.س</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr><th>#</th><th>الاسم</th><th>رقم الجوال</th><th>الراتب الشهري</th><th></th></tr>
      </thead>
      <tbody>
        <?php
        $has = false;
        while ($row = $employees->fetch_assoc()):
            $has = true;
        ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td class="fw-bold"><?= number_format($row['salary'], 2) ?> ر.س</td>
          <td>
            <a href="?del=<?= $row['id'] ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('هل تريد حذف هذا الموظف؟')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if (!$has): ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-4">
            <i class="bi bi-people fs-3 d-block mb-2"></i>
            لا يوجد موظفون مضافون بعد.
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
