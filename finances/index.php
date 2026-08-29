<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/flash.php';

$action = $_GET['action'] ?? 'list';
$types  = ['income', 'expense'];
$categories = [
    'income'  => ['Crop Sales','Livestock Sales','Government Grant','Loan','Other Income'],
    'expense' => ['Seeds','Fertilizer','Chemicals','Labour','Equipment','Fuel','Irrigation','Veterinary','Packaging','Loan Repayment','Other Expense'],
];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function validate_finance(array $p): array {
    $errors = [];
    if (trim($p['category']) === '')    $errors[] = 'Category is required.';
    if (!is_numeric($p['amount']) || $p['amount'] <= 0) $errors[] = 'Amount must be a positive number.';
    if ($p['transaction_date'] === '' || !strtotime($p['transaction_date'])) $errors[] = 'A valid date is required.';
    return $errors;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM finances WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    flash_set('success', 'Record deleted.');
    header('Location: ./'); exit;
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────
$errors = [];
$item = ['id'=>'','type'=>'income','category'=>'','amount'=>'','transaction_date'=>'','description'=>'','reference'=>'','notes'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM finances WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
    mysqli_stmt_close($stmt);
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $p = [
        'type'             => $_POST['type'] ?? 'income',
        'category'         => trim($_POST['category'] ?? ''),
        'amount'           => $_POST['amount'] ?? '',
        'transaction_date' => trim($_POST['transaction_date'] ?? ''),
        'description'      => trim($_POST['description'] ?? ''),
        'reference'        => trim($_POST['reference'] ?? ''),
        'notes'            => trim($_POST['notes'] ?? ''),
    ];
    $errors = validate_finance($p);

    if (empty($errors)) {
        $desc = $p['description'] !== '' ? $p['description'] : null;
        $ref  = $p['reference']   !== '' ? $p['reference']   : null;
        $notes = $p['notes']      !== '' ? $p['notes']       : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO finances (type, category, amount, transaction_date, description, reference, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssdssss',
                $p['type'], $p['category'], $p['amount'], $p['transaction_date'], $desc, $ref, $notes);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE finances SET type=?, category=?, amount=?, transaction_date=?, description=?, reference=?, notes=?
                 WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssdssssi',
                $p['type'], $p['category'], $p['amount'], $p['transaction_date'], $desc, $ref, $notes, $id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash_set('success', $action === 'add' ? 'Record added.' : 'Record updated.');
        header('Location: ./'); exit;
    }
    $item = array_merge($item, $p);
}

// ── LIST ──────────────────────────────────────────────────────────────────────
$items = $totals = [];
$filter_type = $_GET['type'] ?? '';
$filter_month = $_GET['month'] ?? '';

if ($action === 'list') {
    $where = []; $params = []; $types_str = '';
    if ($filter_type !== '') { $where[] = 'type = ?'; $params[] = $filter_type; $types_str .= 's'; }
    if ($filter_month !== '') { $where[] = 'DATE_FORMAT(transaction_date, "%Y-%m") = ?'; $params[] = $filter_month; $types_str .= 's'; }
    $sql = 'SELECT * FROM finances' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY transaction_date DESC';
    $stmt = mysqli_prepare($conn, $sql);
    if ($params) mysqli_stmt_bind_param($stmt, $types_str, ...$params);
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $r = mysqli_query($conn, 'SELECT type, SUM(amount) as total FROM finances GROUP BY type');
    while ($row = mysqli_fetch_assoc($r)) $totals[$row['type']] = $row['total'];
}

require_once '../includes/header.php';
?>
<main>
<?php if ($action === 'list'): ?>
    <div class="page-header">
        <h2>Finances</h2>
        <a href="?action=add" class="btn">+ Add Record</a>
    </div>
    <?= flash_html() ?>

    <!-- Summary -->
    <div class="cards" style="margin-bottom:1.25rem">
        <div class="card">
            <div class="card-value" style="color:#2d6a2d">$<?= number_format($totals['income'] ?? 0, 2) ?></div>
            <div class="card-label">Total Income</div>
        </div>
        <div class="card card-danger">
            <div class="card-value">$<?= number_format($totals['expense'] ?? 0, 2) ?></div>
            <div class="card-label">Total Expenses</div>
        </div>
        <div class="card <?= (($totals['income'] ?? 0) - ($totals['expense'] ?? 0)) >= 0 ? '' : 'card-danger' ?>">
            <div class="card-value">$<?= number_format(($totals['income'] ?? 0) - ($totals['expense'] ?? 0), 2) ?></div>
            <div class="card-label">Net Profit / Loss</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="" style="display:flex;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap">
        <select name="type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach ?>
        </select>
        <input type="month" name="month" value="<?= h($filter_month) ?>" onchange="this.form.submit()">
        <?php if ($filter_type || $filter_month): ?>
            <a href="./" class="btn btn-secondary">Clear</a>
        <?php endif ?>
    </form>

    <?php if (empty($items)): ?>
        <p class="empty">No financial records found.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Reference</th><th>Amount</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr>
                <td><?= h($row['transaction_date']) ?></td>
                <td><span class="<?= $row['type'] === 'income' ? 'badge-ok' : 'badge' ?>"><?= ucfirst(h($row['type'])) ?></span></td>
                <td><?= h($row['category']) ?></td>
                <td><?= $row['description'] ? h($row['description']) : '—' ?></td>
                <td><?= $row['reference'] ? h($row['reference']) : '—' ?></td>
                <td style="font-weight:600;color:<?= $row['type']==='income'?'#2d6a2d':'#c0392b' ?>">
                    <?= $row['type']==='income'?'+':'-' ?>$<?= number_format($row['amount'], 2) ?>
                </td>
                <td class="actions">
                    <a href="?action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?action=delete&id=<?= (int)$row['id'] ?>"
                       onclick="return confirm('Delete this record?')" class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add','edit'])): ?>
    <div class="page-header">
        <h2><?= $action === 'add' ? 'Add Financial Record' : 'Edit Record' ?></h2>
        <a href="./" class="btn btn-secondary">← Back</a>
    </div>
    <?php if ($errors): ?><ul class="errors"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul><?php endif ?>

    <form method="POST" action="?action=<?= h($action) ?><?= $action==='edit'?'&id='.(int)$item['id']:'' ?>">
        <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif ?>
        <label>Type
            <select name="type" id="type-select">
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= $item['type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Category
            <select name="category">
                <?php
                $all_cats = array_merge($categories['income'], $categories['expense']);
                foreach ($all_cats as $cat):
                ?>
                    <option value="<?= h($cat) ?>" <?= $item['category']===$cat?'selected':'' ?>><?= h($cat) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Amount ($)
            <input type="number" name="amount" value="<?= h($item['amount']) ?>" min="0.01" step="0.01" required>
        </label>
        <label>Date
            <input type="date" name="transaction_date" value="<?= h($item['transaction_date']) ?>" required>
        </label>
        <label>Description (optional)
            <input type="text" name="description" value="<?= h($item['description'] ?? '') ?>">
        </label>
        <label>Reference # (optional)
            <input type="text" name="reference" value="<?= h($item['reference'] ?? '') ?>">
        </label>
        <label>Notes (optional)
            <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea>
        </label>
        <button type="submit" class="btn"><?= $action==='add'?'Add Record':'Save Changes' ?></button>
    </form>
<?php endif ?>
</main>
<?php require_once '../includes/footer.php'; ?>
