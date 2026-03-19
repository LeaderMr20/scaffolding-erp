<?php
include '../../config/db.php';

if (isset($_POST['save'])) {
    $stmt = $conn->prepare("INSERT INTO equipment(name, quantity, price_day, status) VALUES(?, ?, ?, 'available')");
    $stmt->bind_param('sid', $_POST['name'], $_POST['qty'], $_POST['price']);
    $stmt->execute();
    header('Location: index.php?saved=1');
    exit;
}

if (isset($_POST['edit_save'])) {
    $id    = (int)$_POST['edit_id'];
    $name  = trim($_POST['name']);
    $qty   = (int)$_POST['qty'];
    $price = (float)$_POST['price'];
    $status = $_POST['status'] === 'available' ? 'available' : 'unavailable';
    $stmt = $conn->prepare("UPDATE equipment SET name=?, quantity=?, price_day=?, status=? WHERE id=?");
    $stmt->bind_param('sidsi', $name, $qty, $price, $status, $id);
    $stmt->execute();
    header('Location: index.php?updated=1');
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

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3">
  <i class="bi bi-check-circle me-2"></i> تم تحديث المعدة بنجاح.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="collapse mb-4 <?= !isset($_GET['saved']) && !isset($_GET['updated']) ? 'show' : '' ?>" id="addForm">
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
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary"
                        onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['name'])) ?>', <?= $row['quantity'] ?>, <?= $row['price_day'] ?>, '<?= $row['status'] ?>')"
                        title="تعديل">
                  <i class="bi bi-pencil-fill"></i> تعديل
                </button>
                <a href="?del=<?= $row['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('هل تريد حذف هذه المعدة؟')"
                   title="حذف">
                  <i class="bi bi-trash"></i>
                </a>
              </div>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:none">
      <div class="modal-header" style="background:#1e3a6e;color:#fff;border-radius:16px 16px 0 0">
        <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>تعديل المعدة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="edit_save" value="1">
        <input type="hidden" name="edit_id" id="editId">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">اسم المعدة <span class="text-danger">*</span></label>
            <input type="text" name="name" id="editName" class="form-control" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-bold">الكمية <span class="text-danger">*</span></label>
              <input type="number" name="qty" id="editQty" class="form-control" min="0" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-bold">السعر / اليوم (ر.س) <span class="text-danger">*</span></label>
              <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label fw-bold">الحالة</label>
            <select name="status" id="editStatus" class="form-select">
              <option value="available">متاح</option>
              <option value="unavailable">غير متاح</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i> حفظ التعديلات
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(id, name, qty, price, status) {
    document.getElementById('editId').value     = id;
    document.getElementById('editName').value   = name;
    document.getElementById('editQty').value    = qty;
    document.getElementById('editPrice').value  = price;
    document.getElementById('editStatus').value = status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include '../../templates/footer.php'; ?>
