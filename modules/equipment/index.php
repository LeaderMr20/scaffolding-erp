<?php
include '../../config/db.php';

if (isset($_POST['save'])) {
    $stmt = $conn->prepare("INSERT INTO equipment(name, quantity, price_day, status) VALUES(?, ?, ?, 'available')");
    $stmt->bind_param('sid', $_POST['name'], $_POST['qty'], $_POST['price']);
    $stmt->execute();
    header('Location: index.php?saved=1');
    exit;
}

if (isset($_GET['del'])) {
    $stmt = $conn->prepare("DELETE FROM equipment WHERE id=?");
    $stmt->bind_param('i', $_GET['del']);
    $stmt->execute();
    header('Location: index.php');
    exit;
}

$equipment = $conn->query("SELECT * FROM equipment ORDER BY name");

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-3">
    <div class="stat-icon teal" style="width: 48px; height: 48px; font-size: 20px;"><i class="bi bi-tools"></i></div>
    <div>
      <h4 class="mb-1">المعدات</h4>
      <p class="mb-0 text-muted">إدارة مخزون المعدات وأسعار الإيجار</p>
    </div>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addForm">
    <i class="bi bi-plus-lg me-1"></i> إضافة معدة
  </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3">
  <i class="bi bi-check-circle me-2"></i> تم حفظ المعدة بنجاح.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="collapse mb-4 <?= !isset($_GET['saved']) ? 'show' : '' ?>" id="addForm">
  <div class="card">
    <div class="card-body p-4">
      <form method="post">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label text-muted small fw-bold">اسم المعدة</label>
            <input name="name" placeholder="أدخل اسم المعدة" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label text-muted small fw-bold">الكمية</label>
            <input name="qty" placeholder="الكمية" type="number" min="0" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold">السعر / اليوم</label>
            <div class="input-group">
              <input name="price" placeholder="القيمة" type="number" step="0.01" min="0" class="form-control" required>
              <span class="input-group-text border-start-0" style="background: var(--bg); color: var(--text-muted);">ر.س</span>
            </div>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button name="save" class="btn btn-success w-100" style="height: 38px;">
              <i class="bi bi-save me-1"></i> حفظ
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>اسم المعدة</th>
            <th>الكمية</th>
            <th>السعر / اليوم</th>
            <th>الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $has = false;
          while ($row = $equipment->fetch_assoc()):
              $has = true;
              $avail = $row['status'] == 'available';
          ?>
          <tr>
            <td><span class="text-muted">#<?= $row['id'] ?></span></td>
            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
            <td><?= number_format($row['quantity']) ?></td>
            <td><?= number_format($row['price_day'], 2) ?> <span class="text-muted">ر.س</span></td>
            <td>
              <span class="badge" style="background:<?= $avail ? '#dcfce7;color:#15803d' : '#fef9c3;color:#a16207' ?>">
                <?= $avail ? 'متاح' : 'غير متاح' ?>
              </span>
            </td>
            <td>
              <a href="?del=<?= $row['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('هل تريد حذف هذه المعدة؟')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-5">
              <i class="bi bi-tools fs-3 d-block mb-2"></i>
              لا توجد معدات مضافة بعد
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
