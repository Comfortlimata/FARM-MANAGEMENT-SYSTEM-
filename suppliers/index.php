<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/flash.php';

$action  = $_GET['action'] ?? 'list';
$section = $_GET['section'] ?? 'suppliers';
$order_statuses = ['pending','delivered','cancelled'];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function validate_supplier(array $p): array {
    $errors = [];
    if (trim($p['name']) === '') $errors[] = 'Supplier name is required.';
    if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    return $errors;
}

function validate_order(array $p): array {
    $errors = [];
    if (trim($p['item_name']) === '')  $errors[] = 'Item name is required.';
    if (trim($p['unit']) === '')       $errors[] = 'Unit is required.';
    if (!is_numeric($p['quantity'])  || $p['quantity']   <= 0) $errors[] = 'Quantity must be positive.';
    if (!is_numeric($p['unit_price'])|| $p['unit_price'] <= 0) $errors[] = 'Unit price must be positive.';
    if ($p['order_date'] === '' || !strtotime($p['order_date'])) $errors[] = 'A valid order date is required.';
    if ($p['delivery_date'] !== '' && !strtotime($p['delivery_date'])) $errors[] = 'Invalid delivery date.';
    return $errors;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if ($section === 'orders' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM purchase_orders WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        flash_set('success', 'Order deleted.');
        header('Location: ./?section=orders'); exit;
    }
    if ($section === 'suppliers' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM suppliers WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        flash_set('success', 'Supplier deleted.');
        header('Location: ./'); exit;
    }
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────
$errors = [];
$sitem = ['id'=>'','name'=>'','contact_person'=>'','phone'=>'','email'=>'','address'=>'','category'=>'','notes'=>''];
$oitem = ['id'=>'','supplier_id'=>'','item_name'=>'','quantity'=>'','unit'=>'','unit_price'=>'','order_date'=>'','delivery_date'=>'','status'=>'pending','notes'=>''];

if ($action === 'edit') {
    if ($section === 'suppliers' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM suppliers WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt);
        $sitem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $sitem;
        mysqli_stmt_close($stmt);
    }
    if ($section === 'orders' && isset($_GET['id'])) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM purchase_orders WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
        mysqli_stmt_execute($stmt);
        $oitem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $oitem;
        mysqli_stmt_close($stmt);
    }
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    if ($section === 'suppliers') {
        $p = [
            'name'           => trim($_POST['name'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
            'category'       => trim($_POST['category'] ?? ''),
            'notes'          => trim($_POST['notes'] ?? ''),
        ];
        $errors = validate_supplier($p);
        if (empty($errors)) {
            $cp    = $p['contact_person'] !== '' ? $p['contact_person'] : null;
            $phone = $p['phone']          !== '' ? $p['phone']          : null;
            $email = $p['email']          !== '' ? $p['email']          : null;
            $addr  = $p['address']        !== '' ? $p['address']        : null;
            $cat   = $p['category']       !== '' ? $p['category']       : null;
            $notes = $p['notes']          !== '' ? $p['notes']          : null;
            if ($action === 'add') {
                $stmt = mysqli_prepare($conn, 'INSERT INTO suppliers (name, contact_person, phone, email, address, category, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'sssssss', $p['name'], $cp, $phone, $email, $addr, $cat, $notes);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = mysqli_prepare($conn, 'UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, category=?, notes=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'sssssssi', $p['name'], $cp, $phone, $email, $addr, $cat, $notes, $id);
            }
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set('success', $action === 'add' ? 'Supplier added.' : 'Supplier updated.');
            header('Location: ./'); exit;
        }
        $sitem = array_merge($sitem, $p);
    }

    if ($section === 'orders') {
        $p = [
            'supplier_id'   => (int)($_POST['supplier_id'] ?? 0),
            'item_name'     => trim($_POST['item_name'] ?? ''),
            'quantity'      => $_POST['quantity'] ?? '',
            'unit'          => trim($_POST['unit'] ?? ''),
            'unit_price'    => $_POST['unit_price'] ?? '',
            'order_date'    => trim($_POST['order_date'] ?? ''),
            'delivery_date' => trim($_POST['delivery_date'] ?? ''),
            'status'        => $_POST['status'] ?? 'pending',
            'notes'         => trim($_POST['notes'] ?? ''),
        ];
        $errors = validate_order($p);
        if (empty($errors)) {
            $delivery = $p['delivery_date'] !== '' ? $p['delivery_date'] : null;
            $notes    = $p['notes']         !== '' ? $p['notes']         : null;
            if ($action === 'add') {
                $stmt = mysqli_prepare($conn,
                    'INSERT INTO purchase_orders (supplier_id, item_name, quantity, unit, unit_price, order_date, delivery_date, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'isdsdsss s',
                    $p['supplier_id'], $p['item_name'], $p['quantity'],
                    $p['unit'], $p['unit_price'], $p['order_date'], $delivery, $p['status'], $notes);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = mysqli_prepare($conn,
                    'UPDATE purchase_orders SET supplier_id=?, item_name=?, quantity=?, unit=?, unit_price=?, order_date=?, delivery_date=?, status=?, notes=?
                     WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'isdsdsssi',
                    $p['supplier_id'], $p['item_name'], $p['quantity'],
                    $p['unit'], $p['unit_price'], $p['order_date'], $delivery, $p['status'], $notes, $id);
            }
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set('success', $action === 'add' ? 'Order added.' : 'Order updated.');
            header('Location: ./?section=orders'); exit;
        }
        $oitem = array_merge($oitem, $p);
    }
}

// ── LIST DATA ─────────────────────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, 'SELECT * FROM suppliers ORDER BY name ASC');
mysqli_stmt_execute($stmt);
$suppliers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT po.*, s.name as supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id ORDER BY po.order_date DESC');
mysqli_stmt_execute($stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

require_once '../includes/header.php';
?>
<main>
    <div class="page-header">
        <h2>Suppliers</h2>
        <div style="display:flex;gap:0.5rem">
            <a href="./?section=suppliers&action=add" class="btn">+ Add Supplier</a>
            <a href="./?section=orders&action=add" class="btn btn-secondary">+ Add Order</a>
        </div>
    </div>
    <?= flash_html() ?>

    <div class="tabs">
        <a href="./" class="tab <?= $section==='suppliers'?'tab-active':'' ?>">Suppliers</a>
        <a href="./?section=orders" class="tab <?= $section==='orders'?'tab-active':'' ?>">Purchase Orders</a>
    </div>

<?php if ($section === 'suppliers' && $action === 'list'): ?>
    <?php if (empty($suppliers)): ?><p class="empty">No suppliers yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Category</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $row): ?>
            <tr>
                <td><?= h($row['name']) ?></td>
                <td><?= $row['contact_person'] ? h($row['contact_person']) : '—' ?></td>
                <td><?= $row['phone'] ? h($row['phone']) : '—' ?></td>
                <td><?= $row['email'] ? h($row['email']) : '—' ?></td>
                <td><?= $row['category'] ? h($row['category']) : '—' ?></td>
                <td class="actions">
                    <a href="?section=suppliers&action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?section=suppliers&action=delete&id=<?= (int)$row['id'] ?>" onclick="return confirm('Delete supplier?')" class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif ($section === 'orders' && $action === 'list'): ?>
    <?php if (empty($orders)): ?><p class="empty">No purchase orders yet.</p>
    <?php else: ?>
    <?php $total_orders = array_sum(array_column($orders, 'total_amount')); ?>
    <p class="summary">Total Procurement: <strong>$<?= number_format($total_orders, 2) ?></strong></p>
    <table>
        <thead><tr><th>Date</th><th>Supplier</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Delivery</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php
        $st_badge = ['pending'=>'badge-warn','delivered'=>'badge-ok','cancelled'=>'badge'];
        foreach ($orders as $row): ?>
            <tr>
                <td><?= h($row['order_date']) ?></td>
                <td><?= h($row['supplier_name']) ?></td>
                <td><?= h($row['item_name']) ?></td>
                <td><?= h($row['quantity']) ?> <?= h($row['unit']) ?></td>
                <td>$<?= number_format($row['unit_price'],2) ?></td>
                <td>$<?= number_format($row['total_amount'],2) ?></td>
                <td><?= $row['delivery_date'] ? h($row['delivery_date']) : '—' ?></td>
                <td><span class="<?= $st_badge[$row['status']] ?>"><?= ucfirst(h($row['status'])) ?></span></td>
                <td class="actions">
                    <a href="?section=orders&action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?section=orders&action=delete&id=<?= (int)$row['id'] ?>" onclick="return confirm('Delete order?')" class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add','edit']) && $section === 'suppliers'): ?>
    <div class="page-header">
        <h2><?= $action==='add'?'Add Supplier':'Edit Supplier' ?></h2>
        <a href="./" class="btn btn-secondary">← Back</a>
    </div>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul><?php endif ?>
    <form method="POST" action="?section=suppliers&action=<?= h($action) ?><?= $action==='edit'?'&id='.(int)$sitem['id']:'' ?>">
        <?php if ($action==='edit'): ?><input type="hidden" name="id" value="<?= (int)$sitem['id'] ?>"><?php endif ?>
        <label>Name <input type="text" name="name" value="<?= h($sitem['name']) ?>" required></label>
        <label>Contact Person <input type="text" name="contact_person" value="<?= h($sitem['contact_person'] ?? '') ?>"></label>
        <label>Phone <input type="text" name="phone" value="<?= h($sitem['phone'] ?? '') ?>"></label>
        <label>Email <input type="email" name="email" value="<?= h($sitem['email'] ?? '') ?>"></label>
        <label>Category (e.g. Seeds, Chemicals) <input type="text" name="category" value="<?= h($sitem['category'] ?? '') ?>"></label>
        <label>Address <textarea name="address" rows="2"><?= h($sitem['address'] ?? '') ?></textarea></label>
        <label>Notes <textarea name="notes" rows="2"><?= h($sitem['notes'] ?? '') ?></textarea></label>
        <button type="submit" class="btn"><?= $action==='add'?'Add Supplier':'Save Changes' ?></button>
    </form>

<?php elseif (in_array($action, ['add','edit']) && $section === 'orders'): ?>
    <div class="page-header">
        <h2><?= $action==='add'?'Add Purchase Order':'Edit Order' ?></h2>
        <a href="./?section=orders" class="btn btn-secondary">← Back</a>
    </div>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul><?php endif ?>
    <form method="POST" action="?section=orders&action=<?= h($action) ?><?= $action==='edit'?'&id='.(int)$oitem['id']:'' ?>">
        <?php if ($action==='edit'): ?><input type="hidden" name="id" value="<?= (int)$oitem['id'] ?>"><?php endif ?>
        <label>Supplier
            <select name="supplier_id" required>
                <option value="">— Select Supplier —</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$oitem['supplier_id']===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Item Name <input type="text" name="item_name" value="<?= h($oitem['item_name']) ?>" required></label>
        <label>Quantity <input type="number" name="quantity" value="<?= h($oitem['quantity']) ?>" min="0.01" step="0.01" required></label>
        <label>Unit <input type="text" name="unit" value="<?= h($oitem['unit']) ?>" required></label>
        <label>Unit Price ($) <input type="number" name="unit_price" value="<?= h($oitem['unit_price']) ?>" min="0.01" step="0.01" required></label>
        <label>Order Date <input type="date" name="order_date" value="<?= h($oitem['order_date']) ?>" required></label>
        <label>Expected Delivery Date <input type="date" name="delivery_date" value="<?= h($oitem['delivery_date'] ?? '') ?>"></label>
        <label>Status
            <select name="status">
                <?php foreach ($order_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $oitem['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Notes <textarea name="notes" rows="2"><?= h($oitem['notes'] ?? '') ?></textarea></label>
        <button type="submit" class="btn"><?= $action==='add'?'Add Order':'Save Changes' ?></button>
    </form>
<?php endif ?>
</main>
<?php require_once '../includes/footer.php'; ?>
