<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

$action = $_GET['action'] ?? 'list';
$categories = ['seed','fertilizer','chemical','feed','veterinary','packaging','fuel','tool','other'];

// ── Helpers ──────────────────────────────────────────────────────────────────

function validate_form(array $p): array {
    $errors = [];
    if (trim($p['name']) === '') $errors[] = 'Name is required.';
    if (!is_numeric($p['quantity']) || $p['quantity'] < 0) $errors[] = 'Quantity must be a non-negative number.';
    if (!is_numeric($p['reorder_level']) || $p['reorder_level'] < 0) $errors[] = 'Reorder level must be a non-negative number.';
    if ($p['expiry_date'] !== '' && !strtotime($p['expiry_date'])) $errors[] = 'Expiry date is not a valid date.';
    return $errors;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ── DELETE ────────────────────────────────────────────────────────────────────

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($GLOBALS['conn'], 'DELETE FROM inventory_items WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ./');
    exit;
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────

$errors = [];
$item = ['id'=>'','name'=>'','category'=>'seed','unit'=>'','quantity'=>'','reorder_level'=>'','expiry_date'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM inventory_items WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result) ?: $item;
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $p = [
        'name'          => trim($_POST['name'] ?? ''),
        'category'      => $_POST['category'] ?? 'seed',
        'unit'          => trim($_POST['unit'] ?? ''),
        'quantity'      => $_POST['quantity'] ?? '',
        'reorder_level' => $_POST['reorder_level'] ?? '',
        'expiry_date'   => trim($_POST['expiry_date'] ?? ''),
    ];
    $errors = validate_form($p);

    if (empty($errors)) {
        $expiry = $p['expiry_date'] !== '' ? $p['expiry_date'] : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO inventory_items (name, category, unit, quantity, reorder_level, expiry_date)
                 VALUES (?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssids',
                $p['name'], $p['category'], $p['unit'],
                $p['quantity'], $p['reorder_level'], $expiry);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE inventory_items SET name=?, category=?, unit=?, quantity=?, reorder_level=?, expiry_date=?
                 WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sssidsi',
                $p['name'], $p['category'], $p['unit'],
                $p['quantity'], $p['reorder_level'], $expiry, $id);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: ./');
        exit;
    }

    // Re-populate form on error
    $item = array_merge($item, $p);
}

// ── LIST QUERY ────────────────────────────────────────────────────────────────

$items = [];
if ($action === 'list') {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM inventory_items ORDER BY name ASC');
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once '../includes/header.php';
?>

<main>

<?php if ($action === 'list'): ?>

    <div class="page-header">
        <h2>Inventory</h2>
        <a href="?action=add" class="btn">+ Add New Item</a>
    </div>

    <?php if (empty($items)): ?>
        <p class="empty">No inventory items yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Category</th><th>Quantity</th><th>Unit</th>
                <th>Reorder Level</th><th>Expiry Date</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $row):
            $low = $row['quantity'] <= $row['reorder_level'];
        ?>
            <tr class="<?= $low ? 'low-stock' : '' ?>">
                <td><?= h($row['name']) ?></td>
                <td><?= h($row['category']) ?></td>
                <td><?= h($row['quantity']) ?><?= $low ? ' <span class="badge">LOW STOCK</span>' : '' ?></td>
                <td><?= h($row['unit']) ?></td>
                <td><?= h($row['reorder_level']) ?></td>
                <td><?= $row['expiry_date'] ? h($row['expiry_date']) : '—' ?></td>
                <td class="actions">
                    <a href="?action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?action=delete&id=<?= (int)$row['id'] ?>"
                       onclick="return confirm('Delete <?= h(addslashes($row['name'])) ?>?')"
                       class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add','edit'])): ?>

    <div class="page-header">
        <h2><?= $action === 'add' ? 'Add New Item' : 'Edit Item' ?></h2>
        <a href="./" class="btn btn-secondary">← Back</a>
    </div>

    <?php if ($errors): ?>
        <ul class="errors">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?>
        </ul>
    <?php endif ?>

    <form method="POST" action="?action=<?= h($action) ?><?= $action === 'edit' ? '&id='.(int)$item['id'] : '' ?>">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <?php endif ?>

        <label>Name
            <input type="text" name="name" value="<?= h($item['name']) ?>" required>
        </label>

        <label>Category
            <select name="category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $item['category'] === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Unit (e.g. kg, litres, bags)
            <input type="text" name="unit" value="<?= h($item['unit']) ?>">
        </label>

        <label>Quantity
            <input type="number" name="quantity" value="<?= h($item['quantity']) ?>" min="0" step="any" required>
        </label>

        <label>Reorder Level
            <input type="number" name="reorder_level" value="<?= h($item['reorder_level']) ?>" min="0" step="any" required>
        </label>

        <label>Expiry Date (optional)
            <input type="date" name="expiry_date" value="<?= h($item['expiry_date']) ?>">
        </label>

        <button type="submit" class="btn"><?= $action === 'add' ? 'Add Item' : 'Save Changes' ?></button>
    </form>

<?php endif ?>

</main>

<?php require_once '../includes/footer.php'; ?>
