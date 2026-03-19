<?php
include '../../config/db.php';
include '../../templates/header.php';
requireAdmin();

$msg = '';
$msgType = 'success';

// ── Delete user ────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del = (int)$_GET['delete'];
    if ($del === ($_SESSION['user_id'] ?? 0)) {
        $msg = 'لا يمكنك حذف حسابك الخاص.';
        $msgType = 'danger';
    } else {
        $conn->query("DELETE FROM users WHERE id=$del");
        $msg = 'تم حذف المستخدم بنجاح.';
    }
}

// ── Edit user (POST) ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $eid       = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $role      = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $newpass   = $_POST['new_password'] ?? '';

    if (!$full_name || !$username) {
        $msg = 'الاسم واسم المستخدم مطلوبان.';
        $msgType = 'danger';
    } else {
        // Check duplicate username (exclude current user)
        $chk = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>?");
        $chk->bind_param('si', $username, $eid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'اسم المستخدم مستخدم من قِبل شخص آخر.';
            $msgType = 'danger';
        } else {
            if ($newpass) {
                $hash = password_hash($newpass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, role=?, password=? WHERE id=?");
                $stmt->bind_param('ssssi', $full_name, $username, $role, $hash, $eid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, role=? WHERE id=?");
                $stmt->bind_param('sssi', $full_name, $username, $role, $eid);
            }
            $stmt->execute();
            // Update session if editing self
            if ($eid === ($_SESSION['user_id'] ?? 0)) {
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_role'] = $role;
            }
            $msg = 'تم تحديث بيانات المستخدم.';
        }
    }
}

// ── Add user (POST) ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] === 'admin' ? 'admin' : 'user';

    if (!$full_name || !$username || !$password) {
        $msg = 'جميع الحقول مطلوبة.';
        $msgType = 'danger';
    } elseif (mb_strlen($password) < 6) {
        $msg = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
        $msgType = 'danger';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
        $chk->bind_param('s', $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'اسم المستخدم مستخدم بالفعل.';
            $msgType = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users(full_name,username,password,role) VALUES(?,?,?,?)");
            $stmt->bind_param('ssss', $full_name, $username, $hash, $role);
            $stmt->execute();
            $msg = 'تم إنشاء المستخدم بنجاح.';
        }
    }
}

// ── Fetch all users ────────────────────────────────────────────────────────
$users = $conn->query("SELECT id, full_name, username, role, created_at FROM users ORDER BY id ASC");

// Edit mode?
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r   = $conn->query("SELECT id, full_name, username, role FROM users WHERE id=$eid");
    if ($r && $r->num_rows) $editUser = $r->fetch_assoc();
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-people-fill me-2 text-primary"></i>إدارة المستخدمين</h4>
    <p>إضافة وتعديل وحذف حسابات النظام</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-person-plus-fill me-1"></i> مستخدم جديد
  </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
  <i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>-fill me-2"></i>
  <?= htmlspecialchars($msg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Users Table -->
<div class="card mb-4">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>الاسم الكامل</th>
          <th>اسم المستخدم</th>
          <th>الصلاحية</th>
          <th>تاريخ الإنشاء</th>
          <th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td class="text-muted"><?= $u['id'] ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:34px;height:34px;border-radius:50%;background:<?= $u['role']==='admin'?'#1e3a6e':'#334155' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0">
                <?= mb_substr($u['full_name'], 0, 1) ?>
              </div>
              <strong><?= htmlspecialchars($u['full_name']) ?></strong>
              <?php if ($u['id'] === ($_SESSION['user_id'] ?? 0)): ?>
              <span class="badge bg-primary" style="font-size:10px">أنت</span>
              <?php endif; ?>
            </div>
          </td>
          <td><code><?= htmlspecialchars($u['username']) ?></code></td>
          <td>
            <span class="badge" style="background:<?= $u['role']==='admin'?'#1e3a6e':'#475569' ?>;padding:5px 12px">
              <?= $u['role']==='admin' ? 'مدير' : 'موظف' ?>
            </span>
          </td>
          <td class="text-muted small"><?= substr($u['created_at'],0,10) ?></td>
          <td>
            <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
              <i class="bi bi-pencil-fill"></i> تعديل
            </a>
            <?php if ($u['id'] !== ($_SESSION['user_id'] ?? 0)): ?>
            <a href="?delete=<?= $u['id'] ?>"
               onclick="return confirm('هل أنت متأكد من حذف <?= addslashes(htmlspecialchars($u['full_name'])) ?>؟')"
               class="btn btn-sm btn-outline-danger">
              <i class="bi bi-trash-fill"></i> حذف
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($editUser): ?>
<div class="modal fade show d-block" style="background:rgba(0,0,0,.5)" id="editModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>تعديل المستخدم</h5>
        <a href="?" class="btn-close"></a>
      </div>
      <form method="post">
        <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">الاسم الكامل <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control"
                   value="<?= htmlspecialchars($editUser['full_name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">اسم المستخدم <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control"
                   value="<?= htmlspecialchars($editUser['username']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">الصلاحية</label>
            <select name="role" class="form-select">
              <option value="user"  <?= $editUser['role']==='user'  ? 'selected' : '' ?>>موظف عادي</option>
              <option value="admin" <?= $editUser['role']==='admin' ? 'selected' : '' ?>>مدير</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">كلمة مرور جديدة <span class="text-muted fw-normal">(اتركها فارغة للإبقاء على الحالية)</span></label>
            <input type="password" name="new_password" class="form-control" placeholder="6 أحرف على الأقل">
          </div>
        </div>
        <div class="modal-footer">
          <a href="?" class="btn btn-secondary">إلغاء</a>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>حفظ التعديلات</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>إضافة مستخدم جديد</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="add_user" value="1">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">الاسم الكامل <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="مثال: أحمد محمد" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">اسم المستخدم <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" placeholder="مثال: ahmed123" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">كلمة المرور <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="6 أحرف على الأقل" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">الصلاحية</label>
            <select name="role" class="form-select">
              <option value="user">موظف عادي</option>
              <option value="admin">مدير</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>إنشاء الحساب</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
