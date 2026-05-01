<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/db.php';

function jsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getPDO();

    // ---- GET: listar álbumes con conteo de fotos ----
    if ($method === 'GET') {
        $stmt = $pdo->query(
            'SELECT a.*, COUNT(p.id) AS photo_count
             FROM albums a
             LEFT JOIN photos p ON p.album_id = a.id
             GROUP BY a.id
             ORDER BY a.created_at DESC'
        );
        jsonOut($stmt->fetchAll());
    }

    // ---- POST: crear álbum ----
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $name  = trim($body['name']  ?? '');
        $emoji = trim($body['emoji'] ?? '📷');
        $color = trim($body['color'] ?? '#0071e3');

        if ($name === '') jsonOut(['error' => 'El nombre es obligatorio'], 422);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#0071e3';

        $stmt = $pdo->prepare('INSERT INTO albums (name, emoji, color) VALUES (?, ?, ?)');
        $stmt->execute([$name, $emoji, $color]);
        $id = (int) $pdo->lastInsertId();

        $album = $pdo->prepare('SELECT *, 0 AS photo_count FROM albums WHERE id = ?');
        $album->execute([$id]);
        jsonOut($album->fetch(), 201);
    }

    // ---- DELETE: eliminar álbum (cascade borra fotos) ----
    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) jsonOut(['error' => 'ID inválido'], 422);

        // Borrar archivos físicos del álbum
        $photos = $pdo->prepare('SELECT url FROM photos WHERE album_id = ?');
        $photos->execute([$id]);
        foreach ($photos->fetchAll() as $p) {
            $file = __DIR__ . '/../' . ltrim($p['url'], '/');
            if (file_exists($file) && str_starts_with(realpath($file), realpath(__DIR__ . '/../uploads'))) {
                unlink($file);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM albums WHERE id = ?');
        $stmt->execute([$id]);
        jsonOut(['ok' => true]);
    }

    jsonOut(['error' => 'Método no permitido'], 405);

} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 500);
}
