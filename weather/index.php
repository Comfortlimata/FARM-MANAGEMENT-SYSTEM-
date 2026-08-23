<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

$action     = $_GET['action'] ?? 'list';
$conditions = ['sunny','cloudy','rainy','stormy','windy','foggy','other'];

// ── Helpers ───────────────────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function validate_weather(array $p): array {
    $errors = [];
    if ($p['record_date'] === '' || !strtotime($p['record_date'])) $errors[] = 'A valid record date is required.';
    $decimals = ['temperature_min','temperature_max','rainfall_mm','humidity_percent','wind_speed_kmh'];
    foreach ($decimals as $f) {
        if ($p[$f] !== '' && !is_numeric($p[$f])) $errors[] = ucfirst(str_replace('_',' ',$f)) . ' must be a number.';
    }
    if ($p['humidity_percent'] !== '' && ($p['humidity_percent'] < 0 || $p['humidity_percent'] > 100))
        $errors[] = 'Humidity must be between 0 and 100.';
    if ($p['rainfall_mm'] !== '' && $p['rainfall_mm'] < 0)
        $errors[] = 'Rainfall must be non-negative.';
    if ($p['wind_speed_kmh'] !== '' && $p['wind_speed_kmh'] < 0)
        $errors[] = 'Wind speed must be non-negative.';
    return $errors;
}

function num_or_null(string $v): ?float { return $v !== '' ? (float)$v : null; }

// ── DELETE ────────────────────────────────────────────────────────────────────

if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM weather WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ./');
    exit;
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────────

$errors = [];
$item = ['id'=>'','record_date'=>'','temperature_min'=>'','temperature_max'=>'',
         'rainfall_mm'=>'','humidity_percent'=>'','wind_speed_kmh'=>'','weather_condition'=>'sunny','notes'=>''];

if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM weather WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_GET['id']);
    mysqli_stmt_execute($stmt);
    $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $item;
    mysqli_stmt_close($stmt);
}

// ── ADD / EDIT POST ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $p = [
        'record_date'      => trim($_POST['record_date'] ?? ''),
        'temperature_min'  => trim($_POST['temperature_min'] ?? ''),
        'temperature_max'  => trim($_POST['temperature_max'] ?? ''),
        'rainfall_mm'      => trim($_POST['rainfall_mm'] ?? ''),
        'humidity_percent' => trim($_POST['humidity_percent'] ?? ''),
        'wind_speed_kmh'   => trim($_POST['wind_speed_kmh'] ?? ''),
        'weather_condition' => $_POST['weather_condition'] ?? 'sunny',
        'notes'            => trim($_POST['notes'] ?? ''),
    ];
    $errors = validate_weather($p);

    if (empty($errors)) {
        $tmin  = num_or_null($p['temperature_min']);
        $tmax  = num_or_null($p['temperature_max']);
        $rain  = num_or_null($p['rainfall_mm']);
        $hum   = num_or_null($p['humidity_percent']);
        $wind  = num_or_null($p['wind_speed_kmh']);
        $notes = $p['notes'] !== '' ? $p['notes'] : null;

        if ($action === 'add') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO weather (record_date, temperature_min, temperature_max, rainfall_mm, humidity_percent, wind_speed_kmh, weather_condition, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sdddddss',
                $p['record_date'], $tmin, $tmax, $rain, $hum, $wind, $p['weather_condition'], $notes);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn,
                'UPDATE weather SET record_date=?, temperature_min=?, temperature_max=?, rainfall_mm=?,
                 humidity_percent=?, wind_speed_kmh=?, weather_condition=?, notes=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sdddddssi',
                $p['record_date'], $tmin, $tmax, $rain, $hum, $wind, $p['weather_condition'], $notes, $id);
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
    $stmt = mysqli_prepare($conn, 'SELECT * FROM weather ORDER BY record_date DESC');
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once '../includes/header.php';
?>

<main>

<?php if ($action === 'list'): ?>

    <div class="page-header">
        <h2>Weather</h2>
        <a href="?action=add" class="btn">+ Add Record</a>
    </div>

    <?php if (empty($items)): ?>
        <p class="empty">No weather records yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th><th>Condition</th><th>Min °C</th><th>Max °C</th>
                <th>Rainfall (mm)</th><th>Humidity (%)</th><th>Wind (km/h)</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $condition_badge = [
            'sunny'=>'badge-ok','cloudy'=>'badge-warn','rainy'=>'badge-warn',
            'stormy'=>'badge','windy'=>'badge-warn','foggy'=>'badge-warn','other'=>'badge-warn'
        ];
        foreach ($items as $row):
        ?>
            <tr class="<?= $row['weather_condition'] === 'stormy' ? 'low-stock' : '' ?>">
                <td><?= h($row['record_date']) ?></td>
                <td><span class="<?= $condition_badge[$row['weather_condition']] ?>"><?= ucfirst(h($row['weather_condition'])) ?></span></td>
                <td><?= $row['temperature_min'] !== null ? h($row['temperature_min']) : '—' ?></td>
                <td><?= $row['temperature_max'] !== null ? h($row['temperature_max']) : '—' ?></td>
                <td><?= $row['rainfall_mm']      !== null ? h($row['rainfall_mm'])     : '—' ?></td>
                <td><?= $row['humidity_percent'] !== null ? h($row['humidity_percent']): '—' ?></td>
                <td><?= $row['wind_speed_kmh']   !== null ? h($row['wind_speed_kmh'])  : '—' ?></td>
                <td class="actions">
                    <a href="?action=edit&id=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?action=delete&id=<?= (int)$row['id'] ?>"
                       onclick="return confirm('Delete record for <?= h(addslashes($row['record_date'])) ?>?')"
                       class="danger">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

<?php elseif (in_array($action, ['add','edit'])): ?>

    <div class="page-header">
        <h2><?= $action === 'add' ? 'Add Weather Record' : 'Edit Weather Record' ?></h2>
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

        <label>Record Date
            <input type="date" name="record_date" value="<?= h($item['record_date']) ?>" required>
        </label>

        <label>Condition
            <select name="weather_condition">
                <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c ?>" <?= $item['weather_condition'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach ?>
            </select>
        </label>

        <label>Min Temperature (°C)
            <input type="number" name="temperature_min" value="<?= h($item['temperature_min']) ?>" step="0.1">
        </label>

        <label>Max Temperature (°C)
            <input type="number" name="temperature_max" value="<?= h($item['temperature_max']) ?>" step="0.1">
        </label>

        <label>Rainfall (mm)
            <input type="number" name="rainfall_mm" value="<?= h($item['rainfall_mm']) ?>" min="0" step="0.1">
        </label>

        <label>Humidity (%)
            <input type="number" name="humidity_percent" value="<?= h($item['humidity_percent']) ?>" min="0" max="100" step="0.1">
        </label>

        <label>Wind Speed (km/h)
            <input type="number" name="wind_speed_kmh" value="<?= h($item['wind_speed_kmh']) ?>" min="0" step="0.1">
        </label>

        <label>Notes (optional)
            <textarea name="notes" rows="3"><?= h($item['notes'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn"><?= $action === 'add' ? 'Add Record' : 'Save Changes' ?></button>
    </form>

<?php endif ?>

</main>

<?php require_once '../includes/footer.php'; ?>
