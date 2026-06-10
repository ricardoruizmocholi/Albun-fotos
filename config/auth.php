<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        if (str_contains($script, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}
