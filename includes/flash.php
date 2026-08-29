<?php
function flash_set(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    return null;
}

function flash_html(): string {
    $flash = flash_get();
    if (!$flash) return '';
    $cls = $flash['type'] === 'success' ? 'flash-success' : 'flash-error';
    $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    return "<div class=\"flash {$cls}\">{$msg}</div>";
}
