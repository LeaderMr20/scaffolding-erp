<?php
include '../../config/db.php';
include '../../config/auth.php';
requireLogin();

// ── Add ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title = $conn->real_escape_string(trim($_POST['title']));
    $amount = (float)$_POST['amount'];
    $date   = $conn->real_escape_string($_POST['expense_date']);
    $conn->query("INSERT INTO expenses(title, amount, expense_date) VALUES('$title', $amount, '$date')");
    header('Location: index.php?ok=added&month=' . substr($date, 0, 7));
    exit;
}

// ── Edit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id     = (int)$_POST['id'];
    $title  = $conn->real_escape_string(trim($_POST['title']));
    $amount = (float)$_POST['amount'];
    $date   = $conn->real_escape_string($_POST['expense_date']);
    $conn->query("UPDATE expenses SET title='$title', amount=$amount, expense_date='$date' WHERE id=$id");
    header('Location: index.php?ok=edited&month=' . substr($date, 0, 7));
    exit;
}

// ── Delete ───────────────────────────────────────────
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM expenses WHERE id=$id");
    header('Location: index.php?ok=deleted&month=' . ($_GET['month'] ?? date('Y-m')));
    exit;
}

// ── Filter & Stats ───────────────────────────────────
$month = $_GET['month'] ?? date('Y-m');
[$y, $m] = explode('-', $month);
$where = "WHERE YEAR(expense_date)=$y AND MONTH(expense_date)=$m";

$expenses = $conn->query("SELECT * FROM expenses $where ORDER BY expense_date DESC, id DESC");
$totalMonth = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM expenses $where")->fetch_assoc()['s'];
$totalAll   = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM expenses")->fetch_assoc()['s'];
$countMonth = (int)$conn->query("SELECT COUNT(*) c FROM expenses $where")->fetch_assoc()['c'];

include '../../templates/header.php';
?>

<!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-wallet2 text-danger me-2"></i>المصروفات</h4>
    <p>تسجيل ومتابعة مصروفات المنشأة</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-lg me-1"></i> إضافة مصروف
  </button>
</div>

<!-- Alerts -->
<?php if (isset($_GET['ok'])): ?>
<?php $msgs = ['added'=>'تم إضافة المصروف بنجاح','edited'=>'تم تعديل المصروف بنجاح','deleted'=>'تم حذف المصروف']; ?>
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" style="border-radius:12px">
  <i class="bi bi-check-circle-fill fs-5"></i>
  <?= $msgs[$_GET['ok']] ?? 'تمت العملية' ?>
  <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon rose"><i class="bi bi-calendar-month"></i></div>
      <div>
        <div class="stat-label">مصروفات هذا الشهر</div>
        <div class="stat-value" style="font-size:24px;color:#e11d48"><?= number_format($totalMonth, 2) ?> <small style="font-size:14px">ر.س</small></div>
        <div class="stat-sub"><?= $countMonth ?> مصروف مسجل</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon amber"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="stat-label">إجمالي كل المصروفات</div>
        <div class="stat-value" style="font-size:24px;color:#d97706"><?= number_format($totalAll, 2) ?> <small style="font-size:14px">ر.س</small></div>
        <div class="stat-sub">منذ بداية التشغيل</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-funnel"></i></div>
      <div class="w-100">
        <div class="stat-label">تصفية بالشهر</div>
        <form method="get" class="d-flex gap-2 mt-1">
          <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"
                 class="form-control form-control-sm" style="max-width:180px">
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-list-ul me-2 text-danger"></i>سجل المصروفات</span>
    <span class="badge bg-danger" style="font-size:13px"><?= $countMonth ?> مصروف</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>الوصف</th>
          <th style="width:180px">المبلغ</th>
          <th style="width:150px">التاريخ</th>
          <th style="width:110px"></th>
        </tr>
      </thead>
      <tbody>
        <?php $n = 1; $hasRows = false; while ($row = $expenses->fetch_assoc()): $hasRows = true; ?>
        <tr>
          <td class="text-muted"><?= $n++ ?></td>
          <td>
            <div class="fw-bold"><?= htmlspecialchars($row['title']) ?></div>
          </td>
          <td>
            <span class="badge" style="background:#fee2e2;color:#dc2626;font-size:13px;padding:6px 14px">
              <?= number_format($row['amount'], 2) ?> ر.س
            </span>
          </td>
          <td>
            <i class="bi bi-calendar3 me-1 text-muted"></i>
            <?= $row['expense_date'] ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <!-- Edit button -->
              <button class="btn btn-sm btn-outline-primary"
                      onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['title'])) ?>', <?= $row['amount'] ?>, '<?= $row['expense_date'] ?>')"
                      title="تعديل">
                <i class="bi bi-pencil"></i>
              </button>
              <!-- Delete button -->
              <a href="?del=<?= $row['id'] ?>&month=<?= urlencode($month) ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('هل تريد حذف هذا المصروف؟')"
                 title="حذف">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if (!$hasRows): ?>
        <tr>
          <td colspan="5" class="text-center py-5 text-muted">
            <i class="bi bi-wallet2 fs-2 d-block mb-2 text-danger opacity-25"></i>
            لا توجد مصروفات مسجلة لهذا الشهر
            <br>
            <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="bi bi-plus-lg me-1"></i> أضف أول مصروف
            </button>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
      <?php if ($hasRows): ?>
      <tfoot>
        <tr style="background:#fff7f7">
          <td colspan="2" class="text-end fw-bold text-muted">إجمالي الشهر:</td>
          <td><span class="fw-bold text-danger"><?= number_format($totalMonth, 2) ?> ر.س</span></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- ── Add Modal ─────────────────────────────────── -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:none">
      <div class="modal-header" style="background:#0b162c;color:#fff;border-radius:16px 16px 0 0">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>إضافة مصروف جديد</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">وصف المصروف <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="مثال: وقود، إيجار مستودع..." required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-bold">المبلغ (ر.س) <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-bold">التاريخ <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i> حفظ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Edit Modal ────────────────────────────────── -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:none">
      <div class="modal-header" style="background:#1e3a6e;color:#fff;border-radius:16px 16px 0 0">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>تعديل المصروف</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">وصف المصروف <span class="text-danger">*</span></label>
            <input type="text" name="title" id="editTitle" class="form-control" required>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-bold">المبلغ (ر.س) <span class="text-danger">*</span></label>
              <input type="number" name="amount" id="editAmount" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-bold">التاريخ <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" id="editDate" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i> حفظ التعديلات
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(id, title, amount, date) {
    document.getElementById('editId').value     = id;
    document.getElementById('editTitle').value  = title;
    document.getElementById('editAmount').value = amount;
    document.getElementById('editDate').value   = date;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include '../../templates/footer.php'; ?>
