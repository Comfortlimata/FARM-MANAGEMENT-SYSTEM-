<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

$action = $_GET['action'] ?? 'list';
$types    = ['tractor','harvester','irrigation','sprayer','vehicle','hand_tool','power_tool','other'];
$statuses = ['operational','under_maintenance','out_of_service'];

// ── Helpers ───────────────────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function validate_equipment(array $p): array {
    $errors = [];
    if (trim($p['name']) === '') $errors[] = 'Name is required.';
    if ($p['purchase_date']      !== '' && !strtotime($p['purchase_date']))      $errors[] = 'Purchase date is not valid.';
    if ($p['last_service_date']  !== '' && !strtotime($p['last_service_date']))  $errors[] = 'Last service date is not valid.';
    if ($p['next_service_date']  !== '' && !strtotime($p['next_service_date']))  $errors[] = 'Next service date is not valid.';
    return $errors;
}

function date_or_null(string $v): ?string { return $v !== '' ? $v : null; }

// ── DELETE ────────────────────────────────────────────────────────────────────

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM equipment WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ./');
    exit;
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────

$errors = [];
$item = ['id'=>'','name'=>'','type'=>'tractor','status'=>'operational',
         'purchase_date'=>'','last_service_date'=>'','next_service_date'=>'','notes'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM equipment WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
    mysqli_stmt_close($stmt);
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $p = [
        'name'              => trim($_POST['name'] ?? ''),
        'type'              => $_POST['type'] ?? 'tractor',
        'status'            => $_POST['status'] ?? 'operational',
        'purchase_date'     => trim($_POST['purchase_date'] ?? ''),
        'last_service_date' => trim($_POST['last_service_date'] ?? ''),
        'next_service_date' => trim($_POST['next_service_date'] ?? ''),
        'notes'             => trim($_POST['notes'] ?? ''),
    ];
    $errors = validate_equipment($p);

    if (empty($errors)) {
        $pd  = date_or_null($p['purchase_date']);
        $lsd = date_or_null($p['last_service_date']);
        $nsd = date_or_null($p['next_service_date']);
        $notes = $p['notes'] !== '' ? $p['notes'] : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO equipment (name, type, status, purchase_date, last_service_date, next_service_date, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssssss',
                $p['name'], $p['type'], $p['status'], $pd, $lsd, $nsd, $notes);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE equipment SET name=?, type=?, status=?, purchase_date=?, last_service_date=?, next_service_date=?, notes=?
                 WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sssssssi',
                $p['name'], $p['type'], $p['status'], $pd, $lsd, $nsd, $notes, $id);
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
if ($action === 'list') {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM equipment ORDER BY name ASC');
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once '../includes/header.php';
?>

<main>

<?php if ($action === 'list'): ?>

    <div class="page-header">
        <h2>Equipment</h2>
        <a href="?action=add" class="btn">+ Add Equipment</a>
    </div>

    <?php if (empty($items)): ?>
        <p class="empty">No equipment records yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Type</th><th>Status</th>
                <th>Purchase Date</th><th>Last Service</th><th>Next Service</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr class="<?= $row['status'] === 'out_of_service' ? 'low-stock' : '' ?>">
                <td><?= h($row['name']) ?></td>
                <td><?= h(str_replace('_', ' ', $row['type'])) ?></td>
                <td>
                    <?php
                    $badges = ['operational'=>'badge-ok','under_maintenance'=>'badge-warn','out_of_service'=>'badge'];
                    $labels = ['operational'=>'Operational','under_maintenance'=>'Maintenance','out_of_service'=>'Out of Service'];
                    ?>
                    <span class="<?= $badges[$row['status']] ?>"><?= $labels[$row['status']] ?></span>
                </td>
                <td><?= $row['purchase_date']     ? h($row['purchase_date'])     : '—' ?></td>
                <td><?= $row['last_service_date'] ? h($row['last_service_date']) : '—' ?></td>
                <td><?= $row['next_service_date'] ? h($row['next_service_date']) : '—' ?></td>
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
        <h2><?= $action === 'add' ? 'Add Equipment' : 'Edit Equipment' ?></h2>
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

        <label>Type
            <select name="type">
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= $item['type'] === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Status
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Purchase Date (optional)
            <input type="date" name="purchase_date" value="<?= h($item['purchase_date']) ?>">
        </label>

        <label>Last Service Date (optional)
            <input type="date" name="last_service_date" value="<?= h($item['last_service_date']) ?>">
        </label>

        <label>Next Service Date (optional)
            <input type="date" name="next_service_date" value="<?= h($item['next_service_date']) ?>">
        </label>

        <label>Notes (optional)
            <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn"><?= $action === 'add' ? 'Add Equipment' : 'Save Changes' ?></button>
    </form>

<?php endif ?>

</main>

<?php require_once '../includes/footer.php'; ?>
