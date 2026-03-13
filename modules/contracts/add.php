<?php
include '../../config/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id  = (int)$_POST['client_id'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];

    $days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);

    $equip_ids = $_POST['equipment_id'] ?? [];
    $qtys      = $_POST['item_qty'] ?? [];
    $prices    = $_POST['item_price'] ?? [];

    // Calculate total
    $total = 0;
    foreach ($equip_ids as $k => $eid) {
        if (!empty($eid)) {
            $total += (float)$prices[$k] * (int)$qtys[$k] * $days;
        }
    }

    // Save contract
    $stmt = $conn->prepare("INSERT INTO contracts(client_id, start_date, end_date, total, status) VALUES(?, ?, ?, ?, 'active')");
    $stmt->bind_param('issd', $client_id, $start_date, $end_date, $total);
    $stmt->execute();
    $contract_id = $conn->insert_id;

    // Save items
    $stmt2 = $conn->prepare("INSERT INTO contract_items(contract_id, equipment_id, qty, price) VALUES(?, ?, ?, ?)");
    foreach ($equip_ids as $k => $eid) {
        if (!empty($eid)) {
            $eid   = (int)$eid;
            $qty   = (int)$qtys[$k];
            $price = (float)$prices[$k];
            $stmt2->bind_param('iiid', $contract_id, $eid, $qty, $price);
            $stmt2->execute();
        }
    }

    header('Location: view.php?id=' . $contract_id . '&new=1');
    exit;
}

// Fetch data for the form
$clients   = $conn->query("SELECT id, name FROM clients ORDER BY name");
$equip_res = $conn->query("SELECT id, name, price_day FROM equipment ORDER BY name");
$equip_list = [];
while ($e = $equip_res->fetch_assoc()) {
    $equip_list[] = $e;
}

include '../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="bi bi-file-earmark-plus text-primary me-2"></i>عقد جديد</h4>
    <p>إنشاء عقد إيجار جديد</p>
  </div>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i> رجوع</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" id="contractForm">

      <!-- Contract Header -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label fw-bold">العميل <span class="text-danger">*</span></label>
          <select name="client_id" class="form-select" required>
            <option value="">-- اختر العميل --</option>
            <?php while ($c = $clients->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">تاريخ البداية <span class="text-danger">*</span></label>
          <input type="date" name="start_date" id="start_date" class="form-control" required
                 value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">تاريخ الانتهاء <span class="text-danger">*</span></label>
          <input type="date" name="end_date" id="end_date" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold">المدة</label>
          <input type="text" id="days_count" class="form-control bg-light" readonly placeholder="— يوم">
        </div>
      </div>

      <!-- Equipment Items -->
      <h6 class="mb-3 fw-bold"><i class="bi bi-tools me-1"></i> بنود المعدات</h6>
      <div class="table-responsive">
        <table class="table table-bordered" id="itemsTable">
          <thead class="table-light">
            <tr>
              <th>المعدة</th>
              <th style="width:110px">الكمية</th>
              <th style="width:160px">السعر/اليوم (ر.س)</th>
              <th style="width:160px">الإجمالي الفرعي (ر.س)</th>
              <th style="width:45px"></th>
            </tr>
          </thead>
          <tbody id="itemsBody">
            <tr class="item-row">
              <td>
                <select name="equipment_id[]" class="form-select equip-select" onchange="fillPrice(this)">
                  <option value="">-- اختر المعدة --</option>
                  <?php foreach ($equip_list as $e): ?>
                  <option value="<?= $e['id'] ?>" data-price="<?= $e['price_day'] ?>">
                    <?= htmlspecialchars($e['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="item_qty[]" class="form-control item-qty" value="1" min="1" onchange="calcRow(this)"></td>
              <td><input type="number" name="item_price[]" class="form-control item-price" step="0.01" min="0" onchange="calcRow(this)"></td>
              <td><input type="text" class="form-control row-total bg-light" readonly value="0.00"></td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="3" class="text-end fw-bold">الإجمالي التقديري:</td>
              <td><input type="text" id="grandTotal" class="form-control fw-bold bg-warning" readonly value="0.00"></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <button type="button" class="btn btn-outline-primary btn-sm mb-4" onclick="addRow()">
        <i class="bi bi-plus-circle me-1"></i> إضافة سطر
      </button>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-4">
          <i class="bi bi-save me-1"></i> حفظ العقد
        </button>
        <a href="index.php" class="btn btn-secondary">إلغاء</a>
      </div>

    </form>
  </div>
</div>

<script>
// Equipment data indexed by ID
const equipData = <?= json_encode(array_column($equip_list, null, 'id')) ?>;

function getDays() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    if (s && e) {
        const diff = (new Date(e) - new Date(s)) / 86400000;
        return diff > 0 ? diff : 1;
    }
    return 1;
}

function updateDays() {
    const days = getDays();
    const sd = document.getElementById('start_date').value;
    const ed = document.getElementById('end_date').value;
    if (sd && ed) {
        document.getElementById('days_count').value = days + ' يوم';
    }
    document.querySelectorAll('.item-price').forEach(p => calcRow(p));
}

function fillPrice(sel) {
    const row = sel.closest('tr');
    const eid = sel.value;
    const priceInput = row.querySelector('.item-price');
    priceInput.value = (eid && equipData[eid]) ? equipData[eid].price_day : '';
    calcRow(priceInput);
}

function calcRow(input) {
    const row = input.closest('tr');
    const qty   = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const days  = getDays();
    row.querySelector('.row-total').value = (qty * price * days).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.row-total').forEach(el => total += parseFloat(el.value) || 0);
    document.getElementById('grandTotal').value = total.toFixed(2);
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const first = tbody.querySelector('tr.item-row');
    const clone = first.cloneNode(true);
    clone.querySelector('.equip-select').value = '';
    clone.querySelector('.item-qty').value = 1;
    clone.querySelector('.item-price').value = '';
    clone.querySelector('.row-total').value = '0.00';
    tbody.appendChild(clone);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('#itemsBody .item-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calcTotal();
    }
}

document.getElementById('start_date').addEventListener('change', updateDays);
document.getElementById('end_date').addEventListener('change', updateDays);
</script>

<?php include '../../templates/footer.php'; ?>
