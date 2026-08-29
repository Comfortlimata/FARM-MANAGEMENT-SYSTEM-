<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/flash.php';

$action     = $_GET['action'] ?? 'list';
$types      = ['pest', 'disease'];
$severities = ['low', 'medium', 'high', 'critical'];
$statuses   = ['active', 'treated', 'resolved'];

// ── Helpers ───────────────────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function validate_pd(array $p): array {
    $errors = [];
    if (trim($p['name']) === '')          $errors[] = 'Name is required.';
    if (trim($p['affected_crop']) === '') $errors[] = 'Affected crop is required.';
    if ($p['date_observed'] === '' || !strtotime($p['date_observed'])) $errors[] = 'A valid observation date is required.';
    if ($p['treatment_date'] !== '' && !strtotime($p['treatment_date'])) $errors[] = 'Treatment date is not valid.';
    return $errors;
}

function date_or_null(string $v): ?string { return $v !== '' ? $v : null; }

// ── DELETE ────────────────────────────────────────────────────────────────────

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM pest_disease WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    flash_set('success', 'Record deleted.');
    header('Location: ./');
    exit;
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────

$errors = [];
$item = ['id'=>'','name'=>'','type'=>'pest','affected_crop'=>'','severity'=>'low',
         'date_observed'=>'','treatment'=>'','treatment_date'=>'','status'=>'active','notes'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM pest_disease WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
    mysqli_stmt_close($stmt);
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    $p = [
        'name'           => trim($_POST['name'] ?? ''),
        'type'           => $_POST['type'] ?? 'pest',
        'affected_crop'  => trim($_POST['affected_crop'] ?? ''),
        'severity'       => $_POST['severity'] ?? 'low',
        'date_observed'  => trim($_POST['date_observed'] ?? ''),
        'treatment'      => trim($_POST['treatment'] ?? ''),
        'treatment_date' => trim($_POST['treatment_date'] ?? ''),
        'status'         => $_POST['status'] ?? 'active',
        'notes'          => trim($_POST['notes'] ?? ''),
    ];
    $errors = validate_pd($p);

    if (empty($errors)) {
        $treatment      = $p['treatment']      !== '' ? $p['treatment']      : null;
        $treatment_date = date_or_null($p['treatment_date']);
        $notes          = $p['notes']          !== '' ? $p['notes']          : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO pest_disease (name, type, affected_crop, severity, date_observed, treatment, treatment_date, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssssssss',
                $p['name'], $p['type'], $p['affected_crop'], $p['severity'],
                $p['date_observed'], $treatment, $treatment_date, $p['status'], $notes);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE pest_disease SET name=?, type=?, affected_crop=?, severity=?, date_observed=?,
                 treatment=?, treatment_date=?, status=?, notes=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sssssssssi',
                $p['name'], $p['type'], $p['affected_crop'], $p['severity'],
                $p['date_observed'], $treatment, $treatment_date, $p['status'], $notes, $id);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash_set('success', $action === 'add' ? 'Record added.' : 'Record updated.');
        header('Location: ./');
        exit;
    }

    $item = array_merge($item, $p);
}

// ── LIST ──────────────────────────────────────────────────────────────────────

$items = [];
if ($action === 'list') {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM pest_disease ORDER BY date_observed DESC');
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once '../includes/header.php';
?>

<main>

<?php if ($action === 'list'): ?>

    <div class="page-header">
        <h2>Pest &amp; Disease</h2>
        <a href="?action=add" class="btn">+ Add Record</a>
    </div>
    <?= flash_html() ?>

    <?php if (empty($items)): ?>
        <p class="empty">No pest or disease records yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Type</th><th>Affected Crop</th><th>Severity</th>
                <th>Date Observed</th><th>Treatment</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $severity_badge = ['low'=>'badge-ok','medium'=>'badge-warn','high'=>'badge','critical'=>'badge'];
        $status_badge   = ['active'=>'badge','treated'=>'badge-warn','resolved'=>'badge-ok'];
        foreach ($items as $row):
        ?>
            <tr class="<?= in_array($row['severity'], ['high','critical']) ? 'low-stock' : '' ?>">
                <td><?= h($row['name']) ?></td>
                <td><?= ucfirst(h($row['type'])) ?></td>
                <td><?= h($row['affected_crop']) ?></td>
                <td><span class="<?= $severity_badge[$row['severity']] ?>"><?= ucfirst(h($row['severity'])) ?></span></td>
                <td><?= h($row['date_observed']) ?></td>
                <td><?= $row['treatment'] ? h($row['treatment']) : '—' ?></td>
                <td><span class="<?= $status_badge[$row['status']] ?>"><?= ucfirst(h($row['status'])) ?></span></td>
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

<?php elseif (in_array($action, ['add', 'edit'])): ?>

    <div class="page-header">
        <h2><?= $action === 'add' ? 'Add Pest/Disease Record' : 'Edit Record' ?></h2>
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
                    <option value="<?= $t ?>" <?= $item['type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Affected Crop
            <input type="text" name="affected_crop" value="<?= h($item['affected_crop']) ?>" required>
        </label>

        <label>Severity
            <select name="severity">
                <?php foreach ($severities as $s): ?>
                    <option value="<?= $s ?>" <?= $item['severity'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Date Observed
            <input type="date" name="date_observed" value="<?= h($item['date_observed']) ?>" required>
        </label>

        <label>Treatment (optional)
            <input type="text" name="treatment" value="<?= h($item['treatment'] ?? '') ?>">
        </label>

        <label>Treatment Date (optional)
            <input type="date" name="treatment_date" value="<?= h($item['treatment_date'] ?? '') ?>">
        </label>

        <label>Status
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Notes (optional)
            <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn"><?= $action === 'add' ? 'Add Record' : 'Save Changes' ?></button>
    </form>

<?php endif ?>

</main>

<?php require_once '../includes/footer.php'; ?>
