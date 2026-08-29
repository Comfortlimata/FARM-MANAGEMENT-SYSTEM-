<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/flash.php';

$action  = $_GET['action'] ?? 'list';
$section = $_GET['section'] ?? 'sales';
$payment_statuses = ['pending','partial','paid'];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function validate_sale(array $p): array {
    $errors = [];
    if (trim($p['crop_name']) === '') $errors[] = 'Crop name is required.';
    if (trim($p['unit']) === '')      $errors[] = 'Unit is required.';
    if (!is_numeric($p['quantity'])  || $p['quantity']   <= 0) $errors[] = 'Quantity must be positive.';
    if (!is_numeric($p['unit_price'])|| $p['unit_price'] <= 0) $errors[] = 'Unit price must be positive.';
    if ($p['sale_date'] === '' || !strtotime($p['sale_date'])) $errors[] = 'A valid sale date is required.';
    return $errors;
}

function validate_customer(array $p): array {
    $errors = [];
    if (trim($p['name']) === '') $errors[] = 'Customer name is required.';
    if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    return $errors;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if ($section === 'customers' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM customers WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        flash_set('success', 'Customer deleted.');
        header('Location: ./?section=customers'); exit;
    }
    if ($section === 'sales' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM sales WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        flash_set('success', 'Sale deleted.');
        header('Location: ./'); exit;
    }
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────
$errors = [];
$item = ['id'=>'','customer_id'=>'','crop_name'=>'','quantity'=>'','unit'=>'',
         'unit_price'=>'','sale_date'=>'','payment_status'=>'pending','notes'=>''];
$citem = ['id'=>'','name'=>'','phone'=>'','email'=>'','address'=>''];

if ($action === 'edit') {
    if ($section === 'sales' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM sales WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt);
        $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
        mysqli_stmt_close($stmt);
    }
    if ($section === 'customers' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM customers WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt);
        $citem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $citem;
        mysqli_stmt_close($stmt);
    }
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    if ($section === 'sales') {
        $p = [
            'customer_id'    => $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null,
            'crop_name'      => trim($_POST['crop_name'] ?? ''),
            'quantity'       => $_POST['quantity'] ?? '',
            'unit'           => trim($_POST['unit'] ?? ''),
            'unit_price'     => $_POST['unit_price'] ?? '',
            'sale_date'      => trim($_POST['sale_date'] ?? ''),
            'payment_status' => $_POST['payment_status'] ?? 'pending',
            'notes'          => trim($_POST['notes'] ?? ''),
        ];
        $errors = validate_sale($p);
        if (empty($errors)) {
            $notes = $p['notes'] !== '' ? $p['notes'] : null;
            if ($action === 'add') {
                $stmt = mysqli_prepare($conn,
                    'INSERT INTO sales (customer_id, crop_name, quantity, unit, unit_price, sale_date, payment_status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'isdsdsss',
                    $p['customer_id'], $p['crop_name'], $p['quantity'],
                    $p['unit'], $p['unit_price'], $p['sale_date'], $p['payment_status'], $notes);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = mysqli_prepare($conn,
                    'UPDATE sales SET customer_id=?, crop_name=?, quantity=?, unit=?, unit_price=?, sale_date=?, payment_status=?, notes=?
                     WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'isdsdsssi',
                    $p['customer_id'], $p['crop_name'], $p['quantity'],
                    $p['unit'], $p['unit_price'], $p['sale_date'], $p['payment_status'], $notes, $id);
            }
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set('success', $action === 'add' ? 'Sale added.' : 'Sale updated.');
            header('Location: ./'); exit;
        }
        $item = array_merge($item, $p);
    }

    if ($section === 'customers') {
        $p = [
            'name'    => trim($_POST['name'] ?? ''),
            'phone'   => trim($_POST['phone'] ?? ''),
            'email'   => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
        ];
        $errors = validate_customer($p);
        if (empty($errors)) {
            $phone   = $p['phone']   !== '' ? $p['phone']   : null;
            $email   = $p['email']   !== '' ? $p['email']   : null;
            $address = $p['address'] !== '' ? $p['address'] : null;
            if ($action === 'add') {
                $stmt = mysqli_prepare($conn, 'INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssss', $p['name'], $phone, $email, $address);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = mysqli_prepare($conn, 'UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'ssssi', $p['name'], $phone, $email, $address, $id);
            }
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set('success', $action === 'add' ? 'Customer added.' : 'Customer updated.');
            header('Location: ./?section=customers'); exit;
        }
        $citem = array_merge($citem, $p);
    }
}

// ── LIST DATA ─────────────────────────────────────────────────────────────────
$sales = $customers = [];
$total_sales = $total_paid = 0;

$stmt = mysqli_prepare($conn, 'SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id ORDER BY s.sale_date DESC');
mysqli_stmt_execute($stmt);
$sales = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT * FROM customers ORDER BY name ASC');
mysqli_stmt_execute($stmt);
$customers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$total_sales = array_sum(array_column($sales, 'total_amount'));
$total_paid  = array_sum(array_map(fn($s) => $s['payment_status'] === 'paid' ? $s['total_amount'] : 0, $sales));

require_once '../includes/header.php';
?>
<main>
    <div class="page-header">
        <h2>Sales</h2>
        <div style="display:flex;gap:0.5rem">
            <a href="./?section=sales&action=add" class="btn">+ Add Sale</a>
            <a href="./?section=customers&action=add" class="btn btn-secondary">+ Add Customer</a>
        </div>
    </div>
    <?= flash_html() ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="./" class="tab <?= $section==='sales'?'tab-active':'' ?>">Sales Orders</a>
        <a href="./?section=customers" class="tab <?= $section==='customers'?'tab-active':'' ?>">Customers</a>
    </div>

<?php if ($section === 'sales' && $action === 'list'): ?>
    <div class="cards" style="margin-bottom:1rem">
        <div class="card"><div class="card-value">$<?= number_format($total_sales,2) ?></div><div class="card-label">Total Sales</div></div>
        <div class="card"><div class="card-value" style="color:#2d6a2d">$<?= number_format($total_paid,2) ?></div><div class="card-label">Paid</div></div>
        <div class="card card-warn"><div class="card-value">$<?= number_format($total_sales-$total_paid,2) ?></div><div class="card-label">Outstanding</div></div>
    </div>
    <?php if (empty($sales)): ?><p class="empty">No sales records yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Crop</th><th>Customer</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Payment</th><th>Actions</th></tr></thead>
        <tbody>
        <?php
        $pay_badge = ['pending'=>'badge','partial'=>'badge-warn','paid'=>'badge-ok'];
        foreach ($sales as $row): ?>
            <tr>
                <td><?= h($row['sale_date']) ?></td>
                <td><?= h($row['crop_name']) ?></td>
                <td><?= $row['customer_name'] ? h($row['customer_name']) : '—' ?></td>
                <td><?= h($row['quantity']) ?> <?= h($row['unit']) ?></td>
                <td>$<?= number_format($row['unit_price'],2) ?></td>
                <td>$<?= number_format($row['total_amount'],2) ?></td>
                <td><span class="<?= $pay_badge[$row['payment_status']] ?>"><?= ucfirst(h($row['payment_status'])) ?></span></td>
                <td class="actions">
                    <a href="?section=sales&action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?section=sales&action=delete&id=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this sale?')" class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif ($section === 'customers' && $action === 'list'): ?>
    <?php if (empty($customers)): ?><p class="empty">No customers yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($customers as $row): ?>
            <tr>
                <td><?= h($row['name']) ?></td>
                <td><?= $row['phone'] ? h($row['phone']) : '—' ?></td>
                <td><?= $row['email'] ? h($row['email']) : '—' ?></td>
                <td><?= $row['address'] ? h($row['address']) : '—' ?></td>
                <td class="actions">
                    <a href="?section=customers&action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?section=customers&action=delete&id=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this customer?')" class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add','edit']) && $section === 'sales'): ?>
    <div class="page-header">
        <h2><?= $action==='add'?'Add Sale':'Edit Sale' ?></h2>
        <a href="./" class="btn btn-secondary">← Back</a>
    </div>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul><?php endif ?>
    <form method="POST" action="?section=sales&action=<?= h($action) ?><?= $action==='edit'?'&id='.(int)$item['id']:'' ?>">
        <?php if ($action==='edit'): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif ?>
        <label>Customer (optional)
            <select name="customer_id">
                <option value="">— No Customer —</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$item['customer_id']===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Crop / Product <input type="text" name="crop_name" value="<?= h($item['crop_name']) ?>" required></label>
        <label>Quantity <input type="number" name="quantity" value="<?= h($item['quantity']) ?>" min="0.01" step="0.01" required></label>
        <label>Unit <input type="text" name="unit" value="<?= h($item['unit']) ?>" required></label>
        <label>Unit Price ($) <input type="number" name="unit_price" value="<?= h($item['unit_price']) ?>" min="0.01" step="0.01" required></label>
        <label>Sale Date <input type="date" name="sale_date" value="<?= h($item['sale_date']) ?>" required></label>
        <label>Payment Status
            <select name="payment_status">
                <?php foreach ($payment_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $item['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Notes (optional) <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea></label>
        <button type="submit" class="btn"><?= $action==='add'?'Add Sale':'Save Changes' ?></button>
    </form>

<?php elseif (in_array($action, ['add','edit']) && $section === 'customers'): ?>
    <div class="page-header">
        <h2><?= $action==='add'?'Add Customer':'Edit Customer' ?></h2>
        <a href="./?section=customers" class="btn btn-secondary">← Back</a>
    </div>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul><?php endif ?>
    <form method="POST" action="?section=customers&action=<?= h($action) ?><?= $action==='edit'?'&id='.(int)$citem['id']:'' ?>">
        <?php if ($action==='edit'): ?><input type="hidden" name="id" value="<?= (int)$citem['id'] ?>"><?php endif ?>
        <label>Name <input type="text" name="name" value="<?= h($citem['name']) ?>" required></label>
        <label>Phone <input type="text" name="phone" value="<?= h($citem['phone'] ?? '') ?>"></label>
        <label>Email <input type="email" name="email" value="<?= h($citem['email'] ?? '') ?>"></label>
        <label>Address <textarea name="address" rows="2"><?= h($citem['address'] ?? '') ?></textarea></label>
        <button type="submit" class="btn"><?= $action==='add'?'Add Customer':'Save Changes' ?></button>
    </form>
<?php endif ?>
</main>
<?php require_once '../includes/footer.php'; ?>
