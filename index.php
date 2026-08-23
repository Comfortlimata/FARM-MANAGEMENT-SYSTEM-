<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_login();

// ── Summary queries ───────────────────────────────────────────────────────────

function count_query(mysqli $conn, string $sql): int {
    $r = mysqli_query($conn, $sql);
    return (int)(mysqli_fetch_row($r)[0] ?? 0);
}

$stats = [
    'inventory_total'    => count_query($conn, 'SELECT COUNT(*) FROM inventory_items'),
    'inventory_low'      => count_query($conn, 'SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level'),
    'equipment_total'    => count_query($conn, 'SELECT COUNT(*) FROM equipment'),
    'equipment_service'  => count_query($conn, 'SELECT COUNT(*) FROM equipment WHERE status = "under_maintenance"'),
    'labour_this_month'  => count_query($conn, 'SELECT COUNT(*) FROM labour WHERE MONTH(work_date) = MONTH(CURDATE()) AND YEAR(work_date) = YEAR(CURDATE())'),
    'pest_active'        => count_query($conn, 'SELECT COUNT(*) FROM pest_disease WHERE status = "active"'),
    'pest_critical'      => count_query($conn, 'SELECT COUNT(*) FROM pest_disease WHERE severity = "critical" AND status = "active"'),
    'weather_today'      => count_query($conn, 'SELECT COUNT(*) FROM weather WHERE record_date = CURDATE()'),
    'harvest_this_month' => count_query($conn, 'SELECT COUNT(*) FROM harvest WHERE MONTH(harvest_date) = MONTH(CURDATE()) AND YEAR(harvest_date) = YEAR(CURDATE())'),
];

// Payroll this month
$r = mysqli_query($conn, 'SELECT SUM(total_pay) FROM labour WHERE MONTH(work_date) = MONTH(CURDATE()) AND YEAR(work_date) = YEAR(CURDATE())');
$stats['payroll_month'] = (float)(mysqli_fetch_row($r)[0] ?? 0);

// Recent pest/disease alerts
$stmt = mysqli_prepare($conn, 'SELECT name, severity, affected_crop, date_observed FROM pest_disease WHERE status = "active" ORDER BY date_observed DESC LIMIT 5');
mysqli_stmt_execute($stmt);
$alerts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Recent harvests
$stmt = mysqli_prepare($conn, 'SELECT crop_name, quantity, unit, harvest_date FROM harvest ORDER BY harvest_date DESC LIMIT 5');
mysqli_stmt_execute($stmt);
$recent_harvests = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

require_once 'includes/header_root.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>

<main>
    <div class="page-header">
        <h2>Dashboard</h2>
        <span class="text-muted">Welcome, <?= h(current_user()['name']) ?> &mdash; <?= date('l, d F Y') ?></span>
    </div>

    <!-- Summary Cards -->
    <div class="cards">
        <a href="inventory/" class="card <?= $stats['inventory_low'] > 0 ? 'card-warn' : '' ?>">
            <div class="card-value"><?= $stats['inventory_total'] ?></div>
            <div class="card-label">Inventory Items</div>
            <?php if ($stats['inventory_low'] > 0): ?>
                <div class="card-sub"><?= $stats['inventory_low'] ?> low stock</div>
            <?php endif ?>
        </a>

        <a href="equipment/" class="card <?= $stats['equipment_service'] > 0 ? 'card-warn' : '' ?>">
            <div class="card-value"><?= $stats['equipment_total'] ?></div>
            <div class="card-label">Equipment</div>
            <?php if ($stats['equipment_service'] > 0): ?>
                <div class="card-sub"><?= $stats['equipment_service'] ?> in maintenance</div>
            <?php endif ?>
        </a>

        <a href="labour/" class="card">
            <div class="card-value"><?= $stats['labour_this_month'] ?></div>
            <div class="card-label">Labour Records</div>
            <div class="card-sub">This month</div>
        </a>

        <a href="labour/" class="card">
            <div class="card-value">$<?= number_format($stats['payroll_month'], 0) ?></div>
            <div class="card-label">Payroll</div>
            <div class="card-sub">This month</div>
        </a>

        <a href="pest-disease/" class="card <?= $stats['pest_active'] > 0 ? 'card-danger' : '' ?>">
            <div class="card-value"><?= $stats['pest_active'] ?></div>
            <div class="card-label">Active Pest/Disease</div>
            <?php if ($stats['pest_critical'] > 0): ?>
                <div class="card-sub"><?= $stats['pest_critical'] ?> critical</div>
            <?php endif ?>
        </a>

        <a href="weather/" class="card">
            <div class="card-value"><?= $stats['weather_today'] > 0 ? '✓' : '—' ?></div>
            <div class="card-label">Weather</div>
            <div class="card-sub"><?= $stats['weather_today'] > 0 ? 'Logged today' : 'Not logged today' ?></div>
        </a>

        <a href="harvest/" class="card">
            <div class="card-value"><?= $stats['harvest_this_month'] ?></div>
            <div class="card-label">Harvests</div>
            <div class="card-sub">This month</div>
        </a>
    </div>

    <!-- Alerts & Recent -->
    <div class="dash-grid">
        <div class="dash-panel">
            <h3>🚨 Active Pest &amp; Disease Alerts</h3>
            <?php if (empty($alerts)): ?>
                <p class="empty">No active alerts.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Name</th><th>Crop</th><th>Severity</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($alerts as $a):
                        $badge = ['low'=>'badge-ok','medium'=>'badge-warn','high'=>'badge','critical'=>'badge'];
                    ?>
                        <tr>
                            <td><?= h($a['name']) ?></td>
                            <td><?= h($a['affected_crop']) ?></td>
                            <td><span class="<?= $badge[$a['severity']] ?>"><?= ucfirst(h($a['severity'])) ?></span></td>
                            <td><?= h($a['date_observed']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>

        <div class="dash-panel">
            <h3>🌾 Recent Harvests</h3>
            <?php if (empty($recent_harvests)): ?>
                <p class="empty">No harvest records yet.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Crop</th><th>Quantity</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_harvests as $hr): ?>
                        <tr>
                            <td><?= h($hr['crop_name']) ?></td>
                            <td><?= h($hr['quantity']) ?> <?= h($hr['unit']) ?></td>
                            <td><?= h($hr['harvest_date']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer_root.php'; ?>
