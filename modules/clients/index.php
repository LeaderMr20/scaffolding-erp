<?php
include '../../config/db.php';

if (isset($_POST['save'])) {
    $stmt = $conn->prepare("INSERT INTO clients(name, phone, address) VALUES(?, ?, ?)");
    $stmt->bind_param('sss', $_POST['name'], $_POST['phone'], $_POST['address']);
    $stmt->execute();
    header('Location: index.php?saved=1');
    exit;
}

if (isset($_GET['del'])) {
    $stmt = $conn->prepare("DELETE FROM clients WHERE id=?");
    $stmt->bind_param('i', $_GET['del']);
    $stmt->execute();
    header('Location: index.php');
    exit;
}

$clients = $conn->query("SELECT * FROM clients ORDER BY id DESC");

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-3">
    <div class="stat-icon blue" style="width: 48px; height: 48px; font-size: 20px;"><i class="bi bi-people-fill"></i></div>
    <div>
      <h4 class="mb-1">العملاء</h4>
      <p class="mb-0 text-muted">إدارة بيانات العملاء الخاصين بالنظام</p>
    </div>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addForm">
    <i class="bi bi-plus-lg me-1"></i> إضافة عميل
  </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3">
  <i class="bi bi-check-circle me-2"></i> تم حفظ العميل بنجاح.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="collapse mb-4 <?= !isset($_GET['saved']) ? 'show' : '' ?>" id="addForm">
  <div class="card">
    <div class="card-body p-4">
      <form method="post">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold">اسم العميل</label>
            <input name="name" placeholder="أدخل اسم العميل" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold">رقم الجوال</label>
            <input name="phone" placeholder="05XXXXXXXX" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold">العنوان</label>
            <input name="address" placeholder="المدينة، الحي..." class="form-control">
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button name="save" class="btn btn-success w-100" title="حفظ" style="height: 38px;">
              <i class="bi bi-save"></i>
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
            <th>اسم العميل</th>
            <th>رقم الجوال</th>
            <th>العنوان</th>
            <th>تاريخ الإضافة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $has = false;
          while ($row = $clients->fetch_assoc()):
              $has = true;
          ?>
          <tr>
            <td><span class="text-muted">#<?= $row['id'] ?></span></td>
            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['address']) ?></td>
            <td><?= date('Y/m/d', strtotime($row['created_at'])) ?></td>
            <td>
              <a href="?del=<?= $row['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('هل تريد حذف هذا العميل؟')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-5">
              <i class="bi bi-people fs-3 d-block mb-2"></i>
              لا يوجد عملاء مضافون بعد
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
