<?php
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /FARM-MANAGEMENT-SYSTEM-/auth/login.php');
        exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    if (!in_array($_SESSION['user_role'], $roles)) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:2rem">Access denied.</p>');
    }
}

function current_user(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
    ];
}
