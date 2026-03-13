<?php
include '../../config/db.php';

if (isset($_GET['close'])) {
    $stmt = $conn->prepare("UPDATE contracts SET status='closed' WHERE id=?");
    $stmt->bind_param('i', $_GET['close']);
    $stmt->execute();
    header('Location: index.php');
    exit;
}

if (isset($_GET['del'])) {
    require_once '../../config/auth.php';
    if (!isAdmin()) { header('Location: index.php'); exit; }
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM contract_items WHERE contract_id=$id");
    $conn->query("DELETE FROM payments WHERE contract_id=$id");
    $conn->query("DELETE FROM contracts WHERE id=$id");
    header('Location: index.php');
    exit;
}

$search = '';
$where  = '';
if (!empty($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $where  = "WHERE cl.name LIKE '%$search%' OR c.status LIKE '%$search%'";
}

$contracts = $conn->query("
    SELECT c.*,
           cl.name client_name,
           COALESCE((SELECT SUM(amount) FROM payments WHERE contract_id=c.id), 0) paid
    FROM contracts c
    JOIN clients cl ON c.client_id = cl.id
    $where
    ORDER BY c.id DESC
");

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-3">
    <div class="stat-icon amber" style="width: 48px; height: 48px; font-size: 20px;"><i class="bi bi-file-earmark-text-fill"></i></div>
    <div>
      <h4 class="mb-1">العقود</h4>
      <p class="mb-0 text-muted">إدارة عقود الإيجار والمتابعة</p>
    </div>
  </div>
  <a href="add.php" class="btn btn-primary shadow-sm">
    <i class="bi bi-plus-lg me-1"></i> عقد جديد
  </a>
</div>

<form class="mb-4" method="get">
  <div class="input-group shadow-sm" style="max-width:400px; border-radius: 12px; overflow: hidden;">
    <span class="input-group-text bg-white border-0 ps-3 pe-2"><i class="bi bi-search text-muted"></i></span>
    <input name="q" value="<?= htmlspecialchars($search) ?>" class="form-control border-0 shadow-none bg-white" placeholder="بحث باسم العميل...">
    <?php if ($search): ?>
    <a href="index.php" class="btn btn-white border-0 text-danger bg-white px-3"><i class="bi bi-x-lg"></i></a>
    <?php endif; ?>
  </div>
</form>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>العميل</th>
            <th>تاريخ البداية</th>
            <th>تاريخ الانتهاء</th>
            <th>الإجمالي</th>
            <th>المدفوع</th>
            <th>المتبقي</th>
            <th>الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $has = false;
          while ($row = $contracts->fetch_assoc()):
              $has = true;
              $remaining = $row['total'] - $row['paid'];
              $isActive  = $row['status'] == 'active';
          ?>
          <tr>
            <td><span class="text-muted">#<?= $row['id'] ?></span></td>
            <td><strong><?= htmlspecialchars($row['client_name']) ?></strong></td>
            <td><?= $row['start_date'] ?></td>
            <td><?= $row['end_date'] ?></td>
            <td><?= number_format($row['total'], 2) ?> <span class="text-muted small">ر.س</span></td>
            <td class="text-success fw-bold"><?= number_format($row['paid'], 2) ?></td>
            <td class="<?= $remaining > 0 ? 'text-danger' : 'text-success' ?> fw-bold">
              <?= number_format($remaining, 2) ?>
            </td>
            <td>
              <span class="badge" style="background:<?= $isActive ? 'var(--accent-light);color:var(--accent)' : '#f1f5f9;color:#64748b' ?>">
                <?= $isActive ? 'نشط' : 'منتهي' ?>
              </span>
            </td>
            <td class="text-nowrap">
              <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary" title="عرض">
                <i class="bi bi-eye"></i>
              </a>
              <a href="print.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light border text-secondary shadow-sm" title="طباعة" target="_blank">
                <i class="bi bi-printer"></i>
              </a>
              <?php if ($isActive): ?>
              <a href="?close=<?= $row['id'] ?>" class="btn btn-sm btn-warning shadow-sm text-white" title="إغلاق العقد"
                 onclick="return confirm('هل تريد إغلاق هذا العقد؟')">
                <i class="bi bi-check-circle"></i>
              </a>
              <?php endif; ?>
              <?php if (isAdmin()): ?>
              <a href="?del=<?= $row['id'] ?>" class="btn btn-sm btn-danger shadow-sm" title="حذف"
                 onclick="return confirm('هل تريد حذف هذا العقد وجميع بياناته؟')">
                <i class="bi bi-trash"></i>
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-5">
              <i class="bi bi-inbox fs-3 d-block mb-2"></i>
              لا توجد عقود بعد
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
