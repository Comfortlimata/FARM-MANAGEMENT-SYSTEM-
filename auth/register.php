<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /FARM-MANAGEMENT-SYSTEM-/');
    exit;
}

$roles  = ['admin', 'manager', 'worker', 'accountant'];
$errors = [];
$data   = ['name'=>'','email'=>'','role'=>'worker'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'  => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role'  => $_POST['role'] ?? 'worker',
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($data['name'] === '')    $errors[] = 'Name is required.';
    if ($data['email'] === '')   $errors[] = 'Email is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 6)  $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check email not already taken
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ?');
        mysqli_stmt_bind_param($stmt, 's', $data['email']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if ($exists) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn,
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssss', $data['name'], $data['email'], $hash, $data['role']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Farm Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>🌱 Farm Management</h1>
    <h2>Create Account</h2>

    <?php if ($errors): ?>
        <ul class="errors">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach ?>
        </ul>
    <?php endif ?>

    <form method="POST" action="">
        <label>Full Name
            <input type="text" name="name" value="<?= htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
        </label>
        <label>Email
            <input type="email" name="email" value="<?= htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label>Role
            <select name="role">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= $data['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <label>Confirm Password
            <input type="password" name="confirm_password" required>
        </label>
        <button type="submit" class="btn">Create Account</button>
    </form>
    <p class="auth-link">Already have an account? <a href="login.php">Sign In</a></p>
</div>
</body>
</html>
