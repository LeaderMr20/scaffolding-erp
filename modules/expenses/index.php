<?php
include '../../config/db.php';
include '../../config/auth.php';
requireLogin();

// Auto-create expense_categories table
$conn->query("CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sort_order INT DEFAULT 0
)");

// Migration v2: replace old categories with new standard set
if (!function_exists('getSetting') || !getSetting($conn, 'cats_v2')) {
    $newCats = ['إيجار','أجور ورواتب','نثريات','مصاريف تشغيل','كهرباء','ماء','مشتريات','أخرى'];
    $conn->query("TRUNCATE TABLE expense_categories");
    foreach ($newCats as $i => $name) {
        $n = $conn->real_escape_string($name);
        $conn->query("INSERT INTO expense_categories(name, sort_order) VALUES('$n', " . ($i+1) . ")");
    }
    $conn->query("INSERT INTO settings(name,value) VALUES('cats_v2','1') ON DUPLICATE KEY UPDATE value='1'");
}

// ── Manage Categories ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_cat') {
    $name = $conn->real_escape_string(trim($_POST['cat_name']));
    if ($name) {
        $ord = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 v FROM expense_categories")->fetch_assoc()['v'];
        $conn->query("INSERT INTO expense_categories(name, sort_order) VALUES('$name', $ord)");
    }
    header('Location: index.php?tab=cats');
    exit;
}
if (isset($_GET['del_cat'])) {
    $conn->query("DELETE FROM expense_categories WHERE id=" . (int)$_GET['del_cat']);
    header('Location: index.php?tab=cats');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_cat') {
    $id   = (int)$_POST['cat_id'];
    $name = $conn->real_escape_string(trim($_POST['cat_name']));
    if ($name) $conn->query("UPDATE expense_categories SET name='$name' WHERE id=$id");
    header('Location: index.php?tab=cats');
    exit;
}

// ── Add Expense ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title  = $conn->real_escape_string(trim($_POST['title']));
    $amount = (float)$_POST['amount'];
    $date   = $conn->real_escape_string($_POST['expense_date']);
    $conn->query("INSERT INTO expenses(title, amount, expense_date) VALUES('$title', $amount, '$date')");
    header('Location: index.php?ok=added&month=' . substr($date, 0, 7));
    exit;
}

// ── Edit Expense ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id     = (int)$_POST['id'];
    $title  = $conn->real_escape_string(trim($_POST['title']));
    $amount = (float)$_POST['amount'];
    $date   = $conn->real_escape_string($_POST['expense_date']);
    $conn->query("UPDATE expenses SET title='$title', amount=$amount, expense_date='$date' WHERE id=$id");
    header('Location: index.php?ok=edited&month=' . substr($date, 0, 7));
    exit;
}

// ── Delete Expense ────────────────────────────────
if (isset($_GET['del'])) {
    $conn->query("DELETE FROM expenses WHERE id=" . (int)$_GET['del']);
    header('Location: index.php?ok=deleted&month=' . ($_GET['month'] ?? date('Y-m')));
    exit;
}

// ── Data ──────────────────────────────────────────
$month = $_GET['month'] ?? date('Y-m');
[$y, $m] = explode('-', $month);
$where = "WHERE YEAR(expense_date)=$y AND MONTH(expense_date)=$m";

$expenses   = $conn->query("SELECT * FROM expenses $where ORDER BY expense_date DESC, id DESC");
$totalMonth = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM expenses $where")->fetch_assoc()['s'];
$totalAll   = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM expenses")->fetch_assoc()['s'];
$countMonth = (int)$conn->query("SELECT COUNT(*) c FROM expenses $where")->fetch_assoc()['c'];
$categories = $conn->query("SELECT * FROM expense_categories ORDER BY sort_order, name");
$cats = [];
while ($c = $categories->fetch_assoc()) $cats[] = $c;

$activeTab = $_GET['tab'] ?? 'list';

include '../../templates/header.php';
?>

<style>
select.form-select { font-family: 'Cairo', sans-serif; }
</style>

<!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-wallet2 text-danger me-2"></i>المصروفات</h4>
    <p>تسجيل ومتابعة مصروفات المنشأة</p>
  </div>
  <div class="d-flex gap-2">
    <a href="?tab=cats" class="btn btn-outline-secondary <?= $activeTab==='cats' ? 'active' : '' ?>">
      <i class="bi bi-tags me-1"></i> إدارة الفئات
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="bi bi-plus-lg me-1"></i> إضافة مصروف
    </button>
  </div>
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

<?php if ($activeTab === 'cats'): ?>
<!-- ══════════ CATEGORIES TAB ══════════ -->
<div class="row g-4">
  <!-- Add category -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle me-2 text-primary"></i>إضافة فئة جديدة</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="add_cat">
          <div class="mb-3">
            <label class="form-label fw-bold">اسم الفئة</label>
            <input type="text" name="cat_name" class="form-control" placeholder="مثال: وقود" required>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> حفظ</button>
        </form>
      </div>
    </div>
  </div>

  <!-- List categories -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tags me-2 text-secondary"></i>قائمة الفئات</span>
        <span class="badge bg-secondary"><?= count($cats) ?> فئة</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>اسم الفئة</th>
              <th style="width:130px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cats as $i => $cat): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-bold"><?= htmlspecialchars($cat['name']) ?></td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="openEditCat(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['name'])) ?>')"
                          title="تعديل">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <a href="?del_cat=<?= $cat['id'] ?>&tab=cats"
                     class="btn btn-sm btn-outline-danger"
                     onclick="return confirm('هل تريد حذف هذه الفئة؟')"
                     title="حذف">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cats)): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">لا توجد فئات بعد</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="mt-3">
      <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right me-1"></i> العودة للمصروفات
      </a>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════ EXPENSES TAB ══════════ -->

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
          <th>الوصف / الفئة</th>
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
          <td><i class="bi bi-calendar3 me-1 text-muted"></i><?= $row['expense_date'] ?></td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-primary"
                      onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['title'])) ?>', <?= $row['amount'] ?>, '<?= $row['expense_date'] ?>')"
                      title="تعديل">
                <i class="bi bi-pencil"></i>
              </button>
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

<?php endif; ?>

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
            <label class="form-label fw-bold">الفئة / وصف المصروف <span class="text-danger">*</span></label>
            <select name="title" class="form-select" required>
              <option value="" disabled selected>-- اختر الفئة --</option>
              <?php foreach ($cats as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">
              <i class="bi bi-tags me-1 text-secondary"></i>
              يمكنك إضافة فئات من <a href="?tab=cats">إدارة الفئات</a>
            </div>
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

<!-- ── Edit Expense Modal ─────────────────────────── -->
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
            <label class="form-label fw-bold">الفئة / وصف المصروف <span class="text-danger">*</span></label>
            <select name="title" id="editTitle" class="form-select" required>
              <option value="" disabled>-- اختر الفئة --</option>
              <?php foreach ($cats as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
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

<!-- ── Edit Category Modal ───────────────────────── -->
<div class="modal fade" id="editCatModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:16px;border:none">
      <div class="modal-header" style="background:#374151;color:#fff;border-radius:16px 16px 0 0">
        <h5 class="modal-title"><i class="bi bi-tag me-2"></i>تعديل الفئة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="edit_cat">
        <input type="hidden" name="cat_id" id="editCatId">
        <div class="modal-body p-4">
          <label class="form-label fw-bold">اسم الفئة</label>
          <input type="text" name="cat_name" id="editCatName" class="form-control" required>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary btn-sm px-4">
            <i class="bi bi-check-lg me-1"></i> حفظ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(id, title, amount, date) {
    document.getElementById('editId').value     = id;
    document.getElementById('editAmount').value = amount;
    document.getElementById('editDate').value   = date;
    var sel = document.getElementById('editTitle');
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === title) { sel.selectedIndex = i; break; }
    }
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function openEditCat(id, name) {
    document.getElementById('editCatId').value   = id;
    document.getElementById('editCatName').value = name;
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
}
</script>

<?php include '../../templates/footer.php'; ?>
