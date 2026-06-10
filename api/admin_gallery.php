<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonOut(['error' => 'Método no permitido'], 405);

$userId = (int) ($_GET['user_id'] ?? 0);

try {
    $pdo = getPDO();

    if ($userId > 0) {
        $stmt = $pdo->prepare(
            'SELECT a.id, a.name, a.user_id, u.username
             FROM albums a
             JOIN users u ON u.id = a.user_id
             WHERE a.user_id = ?
             ORDER BY a.sort_order ASC, a.created_at DESC'
        );
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query(
            'SELECT a.id, a.name, a.user_id, u.username
             FROM albums a
             JOIN users u ON u.id = a.user_id
             ORDER BY u.username ASC, a.sort_order ASC, a.created_at DESC'
        );
    }
    $albums = $stmt->fetchAll();

    $photoStmt = $pdo->prepare(
        'SELECT id, url, title, file_size FROM photos WHERE album_id = ? ORDER BY created_at ASC'
    );
    foreach ($albums as &$album) {
        $photoStmt->execute([$album['id']]);
        $album['photos'] = $photoStmt->fetchAll();
    }
    unset($album);

    jsonOut($albums);
} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 500);
}
