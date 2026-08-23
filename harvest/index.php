<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

$action    = $_GET['action'] ?? 'list';
$qualities = ['excellent', 'good', 'fair', 'poor'];

// ── Helpers ───────────────────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function validate_harvest(array $p): array {
    $errors = [];
    if (trim($p['crop_name']) === '')   $errors[] = 'Crop name is required.';
    if (trim($p['unit']) === '')        $errors[] = 'Unit is required.';
    if ($p['harvest_date'] === '' || !strtotime($p['harvest_date'])) $errors[] = 'A valid harvest date is required.';
    if (!is_numeric($p['quantity']) || $p['quantity'] < 0) $errors[] = 'Quantity must be a non-negative number.';
    return $errors;
}

// ── DELETE ────────────────────────────────────────────────────────────────────

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM harvest WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ./');
    exit;
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────

$errors = [];
$item = ['id'=>'','crop_name'=>'','variety'=>'','field_location'=>'',
         'harvest_date'=>'','quantity'=>'','unit'=>'','quality'=>'good',
         'storage_location'=>'','notes'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM harvest WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
    mysqli_stmt_close($stmt);
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    $p = [
        'crop_name'        => trim($_POST['crop_name'] ?? ''),
        'variety'          => trim($_POST['variety'] ?? ''),
        'field_location'   => trim($_POST['field_location'] ?? ''),
        'harvest_date'     => trim($_POST['harvest_date'] ?? ''),
        'quantity'         => $_POST['quantity'] ?? '',
        'unit'             => trim($_POST['unit'] ?? ''),
        'quality'          => $_POST['quality'] ?? 'good',
        'storage_location' => trim($_POST['storage_location'] ?? ''),
        'notes'            => trim($_POST['notes'] ?? ''),
    ];
    $errors = validate_harvest($p);

    if (empty($errors)) {
        $variety          = $p['variety']          !== '' ? $p['variety']          : null;
        $field_location   = $p['field_location']   !== '' ? $p['field_location']   : null;
        $storage_location = $p['storage_location'] !== '' ? $p['storage_location'] : null;
        $notes            = $p['notes']            !== '' ? $p['notes']            : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO harvest (crop_name, variety, field_location, harvest_date, quantity, unit, quality, storage_location, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssdssss',
                $p['crop_name'], $variety, $field_location,
                $p['harvest_date'], $p['quantity'], $p['unit'],
                $p['quality'], $storage_location, $notes);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE harvest SET crop_name=?, variety=?, field_location=?, harvest_date=?,
                 quantity=?, unit=?, quality=?, storage_location=?, notes=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssssdssssi',
                $p['crop_name'], $variety, $field_location,
                $p['harvest_date'], $p['quantity'], $p['unit'],
                $p['quality'], $storage_location, $notes, $id);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: ./');
        exit;
    }

    $item = array_merge($item, $p);
}

// ── LIST ──────────────────────────────────────────────────────────────────────

$items = [];
$total_quantity = 0;
if ($action === 'list') {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM harvest ORDER BY harvest_date DESC');
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $total_quantity = array_sum(array_column($items, 'quantity'));
}

require_once '../includes/header.php';
?>

<main>

<?php if ($action === 'list'): ?>

    <div class="page-header">
        <h2>Harvest</h2>
        <a href="?action=add" class="btn">+ Add Record</a>
    </div>

    <?php if (empty($items)): ?>
        <p class="empty">No harvest records yet.</p>
    <?php else: ?>
    <p class="summary">Total Harvested: <strong><?= number_format($total_quantity, 2) ?></strong> units</p>
    <table>
        <thead>
            <tr>
                <th>Crop</th><th>Variety</th><th>Field</th><th>Date</th>
                <th>Quantity</th><th>Unit</th><th>Quality</th><th>Storage</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $quality_badge = ['excellent'=>'badge-ok','good'=>'badge-ok','fair'=>'badge-warn','poor'=>'badge'];
        foreach ($items as $row):
        ?>
            <tr class="<?= $row['quality'] === 'poor' ? 'low-stock' : '' ?>">
                <td><?= h($row['crop_name']) ?></td>
                <td><?= $row['variety']        ? h($row['variety'])          : '—' ?></td>
                <td><?= $row['field_location'] ? h($row['field_location'])   : '—' ?></td>
                <td><?= h($row['harvest_date']) ?></td>
                <td><?= h($row['quantity']) ?></td>
                <td><?= h($row['unit']) ?></td>
                <td><span class="<?= $quality_badge[$row['quality']] ?>"><?= ucfirst(h($row['quality'])) ?></span></td>
                <td><?= $row['storage_location'] ? h($row['storage_location']) : '—' ?></td>
                <td class="actions">
                    <a href="?action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?action=delete&id=<?= (int)$row['id'] ?>"
                       onclick="return confirm('Delete harvest record for <?= h(addslashes($row['crop_name'])) ?>?')"
                       class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add', 'edit'])): ?>

    <div class="page-header">
        <h2><?= $action === 'add' ? 'Add Harvest Record' : 'Edit Harvest Record' ?></h2>
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

        <label>Crop Name
            <input type="text" name="crop_name" value="<?= h($item['crop_name']) ?>" required>
        </label>

        <label>Variety (optional)
            <input type="text" name="variety" value="<?= h($item['variety'] ?? '') ?>">
        </label>

        <label>Field Location (optional)
            <input type="text" name="field_location" value="<?= h($item['field_location'] ?? '') ?>">
        </label>

        <label>Harvest Date
            <input type="date" name="harvest_date" value="<?= h($item['harvest_date']) ?>" required>
        </label>

        <label>Quantity
            <input type="number" name="quantity" value="<?= h($item['quantity']) ?>" min="0" step="0.01" required>
        </label>

        <label>Unit (e.g. kg, tonnes, bags)
            <input type="text" name="unit" value="<?= h($item['unit']) ?>" required>
        </label>

        <label>Quality
            <select name="quality">
                <?php foreach ($qualities as $q): ?>
                    <option value="<?= $q ?>" <?= $item['quality'] === $q ? 'selected' : '' ?>><?= ucfirst($q) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Storage Location (optional)
            <input type="text" name="storage_location" value="<?= h($item['storage_location'] ?? '') ?>">
        </label>

        <label>Notes (optional)
            <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn"><?= $action === 'add' ? 'Add Record' : 'Save Changes' ?></button>
    </form>

<?php endif ?>

</main>

<?php require_once '../includes/footer.php'; ?>
