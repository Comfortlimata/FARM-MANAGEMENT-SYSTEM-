<?php
$_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav>
    <a href="./" class="nav-brand">🌱 FMS</a>
    <a href="inventory/">Inventory</a>
    <a href="equipment/">Equipment</a>
    <a href="labour/">Labour</a>
    <a href="pest-disease/">Pest &amp; Disease</a>
    <a href="weather/">Weather</a>
    <a href="harvest/">Harvest</a>
    <a href="finances/">Finances</a>
    <a href="sales/">Sales</a>
    <a href="suppliers/">Suppliers</a>
    <a href="reports/">Reports</a>
    <div class="nav-right">
        <span class="nav-user">👤 <?= htmlspecialchars($_user['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($_user['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
        <a href="auth/logout.php" class="nav-logout">Logout</a>
    </div>
</nav>
