<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_login();

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$tab = $_GET['tab'] ?? 'harvest';

// ── Harvest: monthly totals for current year ──────────────────────────────────
$harvest_rows = [];
$r = mysqli_query($conn, 'SELECT MONTH(harvest_date) m, SUM(quantity) qty FROM harvest WHERE YEAR(harvest_date) = YEAR(CURDATE()) GROUP BY m ORDER BY m');
while ($row = mysqli_fetch_assoc($r)) $harvest_rows[] = $row;

// ── Harvest by crop (top 8) ───────────────────────────────────────────────────
$crop_rows = [];
$r = mysqli_query($conn, 'SELECT crop_name, SUM(quantity) qty FROM harvest GROUP BY crop_name ORDER BY qty DESC LIMIT 8');
while ($row = mysqli_fetch_assoc($r)) $crop_rows[] = $row;

// ── Finances: monthly income vs expense (last 6 months) ──────────────────────
$fin_rows = [];
$r = mysqli_query($conn, "SELECT DATE_FORMAT(transaction_date,'%Y-%m') ym, type, SUM(amount) total FROM finances WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym, type ORDER BY ym");
$fin_map = [];
while ($row = mysqli_fetch_assoc($r)) $fin_map[$row['ym']][$row['type']] = $row['total'];
foreach ($fin_map as $ym => $v) $fin_rows[] = ['ym'=>$ym,'income'=>$v['income']??0,'expense'=>$v['expense']??0];

// ── Labour: payroll by worker (top 10) ───────────────────────────────────────
$labour_rows = [];
$r = mysqli_query($conn, 'SELECT worker_name, SUM(total_pay) pay FROM labour GROUP BY worker_name ORDER BY pay DESC LIMIT 10');
while ($row = mysqli_fetch_assoc($r)) $labour_rows[] = $row;

// ── Inventory: quantity by category ──────────────────────────────────────────
$inv_rows = [];
$r = mysqli_query($conn, 'SELECT category, SUM(quantity) qty FROM inventory_items GROUP BY category ORDER BY qty DESC');
while ($row = mysqli_fetch_assoc($r)) $inv_rows[] = $row;

require_once '../includes/header.php';
?>

<main>
    <div class="page-header">
        <h2>📊 Reports &amp; Analytics</h2>
    </div>

    <div class="tabs">
        <a href="?tab=harvest"  class="tab <?= $tab==='harvest'  ? 'tab-active':'' ?>">🌾 Harvest</a>
        <a href="?tab=finances" class="tab <?= $tab==='finances' ? 'tab-active':'' ?>">💰 Finances</a>
        <a href="?tab=labour"   class="tab <?= $tab==='labour'   ? 'tab-active':'' ?>">👷 Labour</a>
        <a href="?tab=inventory"class="tab <?= $tab==='inventory'? 'tab-active':'' ?>">📦 Inventory</a>
    </div>

    <?php if ($tab === 'harvest'): ?>
    <!-- ── Harvest Report ── -->
    <div class="report-grid">
        <div class="report-card">
            <h3>Monthly Harvest (<?= date('Y') ?>)</h3>
            <?php if (empty($harvest_rows)): ?>
                <p class="empty">No harvest data for this year.</p>
            <?php else: ?>
                <canvas id="chartHarvestMonthly"></canvas>
            <?php endif ?>
        </div>
        <div class="report-card">
            <h3>Harvest by Crop</h3>
            <?php if (empty($crop_rows)): ?>
                <p class="empty">No harvest data.</p>
            <?php else: ?>
                <canvas id="chartHarvestCrop"></canvas>
            <?php endif ?>
        </div>
    </div>
    <?php if (!empty($harvest_rows)): ?>
    <div class="report-card" style="margin-top:1rem">
        <h3>Monthly Breakdown</h3>
        <table>
            <thead><tr><th>Month</th><th>Total Quantity</th></tr></thead>
            <tbody>
            <?php
            $months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            foreach ($harvest_rows as $row): ?>
                <tr><td><?= $months[(int)$row['m']] ?></td><td><?= number_format($row['qty'],2) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>

    <?php elseif ($tab === 'finances'): ?>
    <!-- ── Finance Report ── -->
    <div class="report-grid">
        <div class="report-card">
            <h3>Income vs Expense (Last 6 Months)</h3>
            <?php if (empty($fin_rows)): ?>
                <p class="empty">No finance data.</p>
            <?php else: ?>
                <canvas id="chartFinances"></canvas>
            <?php endif ?>
        </div>
        <div class="report-card">
            <h3>Summary</h3>
            <?php
            $total_income  = array_sum(array_column($fin_rows, 'income'));
            $total_expense = array_sum(array_column($fin_rows, 'expense'));
            $net = $total_income - $total_expense;
            ?>
            <div class="stat-block">
                <div class="stat-row"><span>Total Income</span><span class="stat-val ok">$<?= number_format($total_income,2) ?></span></div>
                <div class="stat-row"><span>Total Expense</span><span class="stat-val danger">$<?= number_format($total_expense,2) ?></span></div>
                <div class="stat-row stat-total"><span>Net <?= $net>=0?'Profit':'Loss' ?></span><span class="stat-val <?= $net>=0?'ok':'danger' ?>">$<?= number_format(abs($net),2) ?></span></div>
            </div>
            <?php if (!empty($fin_rows)): ?>
            <table style="margin-top:1rem">
                <thead><tr><th>Month</th><th>Income</th><th>Expense</th><th>Net</th></tr></thead>
                <tbody>
                <?php foreach ($fin_rows as $row):
                    $n = $row['income'] - $row['expense']; ?>
                    <tr>
                        <td><?= h($row['ym']) ?></td>
                        <td>$<?= number_format($row['income'],2) ?></td>
                        <td>$<?= number_format($row['expense'],2) ?></td>
                        <td style="color:<?= $n>=0?'#2d6a2d':'#c0392b' ?>">$<?= number_format(abs($n),2) ?> <?= $n>=0?'▲':'▼' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>

    <?php elseif ($tab === 'labour'): ?>
    <!-- ── Labour Report ── -->
    <div class="report-grid">
        <div class="report-card">
            <h3>Top Workers by Payroll</h3>
            <?php if (empty($labour_rows)): ?>
                <p class="empty">No labour data.</p>
            <?php else: ?>
                <canvas id="chartLabour"></canvas>
            <?php endif ?>
        </div>
        <div class="report-card">
            <h3>Payroll Breakdown</h3>
            <?php if (empty($labour_rows)): ?>
                <p class="empty">No labour data.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Worker</th><th>Total Pay</th></tr></thead>
                <tbody>
                <?php foreach ($labour_rows as $row): ?>
                    <tr><td><?= h($row['worker_name']) ?></td><td>$<?= number_format($row['pay'],2) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>

    <?php elseif ($tab === 'inventory'): ?>
    <!-- ── Inventory Report ── -->
    <div class="report-grid">
        <div class="report-card">
            <h3>Stock by Category</h3>
            <?php if (empty($inv_rows)): ?>
                <p class="empty">No inventory data.</p>
            <?php else: ?>
                <canvas id="chartInventory"></canvas>
            <?php endif ?>
        </div>
        <div class="report-card">
            <h3>Category Breakdown</h3>
            <?php if (empty($inv_rows)): ?>
                <p class="empty">No inventory data.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Category</th><th>Total Quantity</th></tr></thead>
                <tbody>
                <?php foreach ($inv_rows as $row): ?>
                    <tr><td><?= h($row['category']) ?></td><td><?= number_format($row['qty'],2) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>
    <?php endif ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
<?php if ($tab === 'harvest' && !empty($harvest_rows)):
    $months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $labels = array_map(fn($r) => $months[(int)$r['m']], $harvest_rows);
    $data   = array_column($harvest_rows, 'qty');
?>
new Chart(document.getElementById('chartHarvestMonthly'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{ label: 'Quantity', data: <?= json_encode($data) ?>, backgroundColor: '#2d6a2d' }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
<?php endif ?>

<?php if ($tab === 'harvest' && !empty($crop_rows)):
    $clabels = array_column($crop_rows, 'crop_name');
    $cdata   = array_column($crop_rows, 'qty');
    $colors  = ['#2d6a2d','#4caf50','#81c784','#a5d6a7','#e67e22','#f39c12','#3498db','#9b59b6'];
?>
new Chart(document.getElementById('chartHarvestCrop'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($clabels) ?>,
        datasets: [{ data: <?= json_encode($cdata) ?>, backgroundColor: <?= json_encode($colors) ?> }]
    },
    options: { responsive: true }
});
<?php endif ?>

<?php if ($tab === 'finances' && !empty($fin_rows)):
    $flabels  = array_column($fin_rows, 'ym');
    $fincome  = array_column($fin_rows, 'income');
    $fexpense = array_column($fin_rows, 'expense');
?>
new Chart(document.getElementById('chartFinances'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($flabels) ?>,
        datasets: [
            { label: 'Income',  data: <?= json_encode($fincome) ?>,  backgroundColor: '#2d6a2d' },
            { label: 'Expense', data: <?= json_encode($fexpense) ?>, backgroundColor: '#c0392b' }
        ]
    },
    options: { responsive: true, scales: { x: { stacked: false }, y: { stacked: false } } }
});
<?php endif ?>

<?php if ($tab === 'labour' && !empty($labour_rows)):
    $llabels = array_column($labour_rows, 'worker_name');
    $lpay    = array_column($labour_rows, 'pay');
?>
new Chart(document.getElementById('chartLabour'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($llabels) ?>,
        datasets: [{ label: 'Total Pay ($)', data: <?= json_encode($lpay) ?>, backgroundColor: '#3498db' }]
    },
    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
});
<?php endif ?>

<?php if ($tab === 'inventory' && !empty($inv_rows)):
    $ilabels = array_column($inv_rows, 'category');
    $idata   = array_column($inv_rows, 'qty');
    $icolors = ['#2d6a2d','#4caf50','#e67e22','#3498db','#9b59b6','#f39c12','#1abc9c','#e74c3c'];
?>
new Chart(document.getElementById('chartInventory'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($ilabels) ?>,
        datasets: [{ data: <?= json_encode($idata) ?>, backgroundColor: <?= json_encode(array_slice($icolors,0,count($inv_rows))) ?> }]
    },
    options: { responsive: true }
});
<?php endif ?>
</script>

<?php require_once '../includes/footer.php'; ?>
