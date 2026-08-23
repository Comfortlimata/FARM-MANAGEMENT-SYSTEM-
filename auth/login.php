<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /FARM-MANAGEMENT-SYSTEM-/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: /FARM-MANAGEMENT-SYSTEM-/');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Farm Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>🌱 Farm Management</h1>
    <h2>Sign In</h2>

    <?php if ($error): ?>
        <ul class="errors"><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li></ul>
    <?php endif ?>

    <form method="POST" action="">
        <label>Email
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn">Sign In</button>
    </form>
    <p class="auth-link">No account? <a href="register.php">Register</a></p>
</div>
</body>
</html>
