<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

$action = $_GET['action'] ?? 'list';
$roles  = ['permanent', 'casual', 'contractor'];

// ── Helpers ───────────────────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function validate_labour(array $p): array {
    $errors = [];
    if (trim($p['worker_name']) === '')          $errors[] = 'Worker name is required.';
    if (trim($p['task']) === '')                  $errors[] = 'Task is required.';
    if ($p['work_date'] === '' || !strtotime($p['work_date'])) $errors[] = 'A valid work date is required.';
    if (!is_numeric($p['hours_worked']) || $p['hours_worked'] < 0) $errors[] = 'Hours worked must be a non-negative number.';
    if (!is_numeric($p['hourly_rate'])  || $p['hourly_rate']  < 0) $errors[] = 'Hourly rate must be a non-negative number.';
    return $errors;
}

// ── DELETE ────────────────────────────────────────────────────────────────────

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM labour WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ./');
    exit;
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────

$errors = [];
$item = ['id'=>'','worker_name'=>'','role'=>'casual','task'=>'',
         'work_date'=>'','hours_worked'=>'','hourly_rate'=>'','notes'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM labour WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
    mysqli_stmt_close($stmt);
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    $p = [
        'worker_name'  => trim($_POST['worker_name'] ?? ''),
        'role'         => $_POST['role'] ?? 'casual',
        'task'         => trim($_POST['task'] ?? ''),
        'work_date'    => trim($_POST['work_date'] ?? ''),
        'hours_worked' => $_POST['hours_worked'] ?? '',
        'hourly_rate'  => $_POST['hourly_rate'] ?? '',
        'notes'        => trim($_POST['notes'] ?? ''),
    ];
    $errors = validate_labour($p);

    if (empty($errors)) {
        $notes = $p['notes'] !== '' ? $p['notes'] : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO labour (worker_name, role, task, work_date, hours_worked, hourly_rate, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssdds',
                $p['worker_name'], $p['role'], $p['task'],
                $p['work_date'], $p['hours_worked'], $p['hourly_rate'], $notes);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE labour SET worker_name=?, role=?, task=?, work_date=?, hours_worked=?, hourly_rate=?, notes=?
                 WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssssddsi',
                $p['worker_name'], $p['role'], $p['task'],
                $p['work_date'], $p['hours_worked'], $p['hourly_rate'], $notes, $id);
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
$total_payroll = 0;
if ($action === 'list') {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM labour ORDER BY work_date DESC, worker_name ASC');
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $total_payroll = array_sum(array_column($items, 'total_pay'));
}

require_once '../includes/header.php';
?>

<main>

<?php if ($action === 'list'): ?>

    <div class="page-header">
        <h2>Labour</h2>
        <a href="?action=add" class="btn">+ Add Record</a>
    </div>

    <?php if (empty($items)): ?>
        <p class="empty">No labour records yet.</p>
    <?php else: ?>
    <p class="summary">Total Payroll: <strong>$<?= number_format($total_payroll, 2) ?></strong></p>
    <table>
        <thead>
            <tr>
                <th>Worker</th><th>Role</th><th>Task</th><th>Date</th>
                <th>Hours</th><th>Rate/hr</th><th>Total Pay</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr>
                <td><?= h($row['worker_name']) ?></td>
                <td><?= ucfirst(h($row['role'])) ?></td>
                <td><?= h($row['task']) ?></td>
                <td><?= h($row['work_date']) ?></td>
                <td><?= h($row['hours_worked']) ?></td>
                <td>$<?= number_format($row['hourly_rate'], 2) ?></td>
                <td>$<?= number_format($row['total_pay'], 2) ?></td>
                <td class="actions">
                    <a href="?action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?action=delete&id=<?= (int)$row['id'] ?>"
                       onclick="return confirm('Delete record for <?= h(addslashes($row['worker_name'])) ?>?')"
                       class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add', 'edit'])): ?>

    <div class="page-header">
        <h2><?= $action === 'add' ? 'Add Labour Record' : 'Edit Labour Record' ?></h2>
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

        <label>Worker Name
            <input type="text" name="worker_name" value="<?= h($item['worker_name']) ?>" required>
        </label>

        <label>Role
            <select name="role">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= $item['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Task
            <input type="text" name="task" value="<?= h($item['task']) ?>" required>
        </label>

        <label>Work Date
            <input type="date" name="work_date" value="<?= h($item['work_date']) ?>" required>
        </label>

        <label>Hours Worked
            <input type="number" name="hours_worked" value="<?= h($item['hours_worked']) ?>" min="0" step="0.5" required>
        </label>

        <label>Hourly Rate ($)
            <input type="number" name="hourly_rate" value="<?= h($item['hourly_rate']) ?>" min="0" step="0.01" required>
        </label>

        <label>Notes (optional)
            <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn"><?= $action === 'add' ? 'Add Record' : 'Save Changes' ?></button>
    </form>

<?php endif ?>

</main>

<?php require_once '../includes/footer.php'; ?>
