<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

function jsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uid    = currentUserId();
$admin  = isAdmin();

try {
    $pdo = getPDO();

    // ---- GET: list albums (own only) ----
    if ($method === 'GET') {
        if ($admin) {
            $stmt = $pdo->query(
                'SELECT a.*, COUNT(p.id) AS photo_count
                 FROM albums a
                 LEFT JOIN photos p ON p.album_id = a.id
                 GROUP BY a.id
                 ORDER BY a.sort_order ASC, a.created_at DESC'
            );
        } else {
            $stmt = $pdo->prepare(
                'SELECT a.*, COUNT(p.id) AS photo_count
                 FROM albums a
                 LEFT JOIN photos p ON p.album_id = a.id
                 WHERE a.user_id = ?
                 GROUP BY a.id
                 ORDER BY a.sort_order ASC, a.created_at DESC'
            );
            $stmt->execute([$uid]);
        }
        jsonOut($stmt->fetchAll());
    }

    // ---- POST: create album / upload cover ----
    if ($method === 'POST') {
        // Upload cover image
        if (($_GET['action'] ?? '') === 'upload_cover') {
            if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
                jsonOut(['error' => 'Archivo requerido'], 422);
            }
            $file    = $_FILES['cover'];
            $maxSize = 5 * 1024 * 1024;
            if ($file['size'] > $maxSize) jsonOut(['error' => 'Archivo muy grande (max 5 MB)'], 422);

            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) jsonOut(['error' => 'Tipo no permitido'], 422);

            $ext     = match($mime) { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>'jpg' };
            $userDir = __DIR__ . '/../uploads/user_' . $uid . '/';
            if (!is_dir($userDir)) mkdir($userDir, 0755, true);
            $filename = uniqid('cover_', true) . '.' . $ext;
            $dest     = $userDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) jsonOut(['error' => 'Error al guardar archivo'], 500);
            jsonOut(['url' => 'uploads/user_' . $uid . '/' . $filename]);
        }

        // Create album
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $name  = trim($body['name']  ?? '');
        $emoji = trim($body['emoji'] ?? '');
        $color = trim($body['color'] ?? '#0071e3');

        if ($name === '') jsonOut(['error' => 'El nombre es obligatorio'], 422);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#0071e3';

        $stmt = $pdo->prepare('INSERT INTO albums (user_id, name, emoji, color) VALUES (?, ?, ?, ?)');
        $stmt->execute([$uid, $name, $emoji, $color]);
        $id = (int) $pdo->lastInsertId();

        $album = $pdo->prepare('SELECT *, 0 AS photo_count FROM albums WHERE id = ?');
        $album->execute([$id]);
        jsonOut($album->fetch(), 201);
    }

    // ---- PATCH: reorder albums ----
    if ($method === 'PATCH' && ($_GET['action'] ?? '') === 'reorder') {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $order = array_values(array_filter(array_map('intval', $body['order'] ?? []), fn($v) => $v > 0));
        if (empty($order)) jsonOut(['error' => 'Order vacío'], 422);

        // Verify all IDs belong to current user (unless admin)
        if (!$admin) {
            $placeholders = implode(',', array_fill(0, count($order), '?'));
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM albums WHERE id IN ($placeholders) AND user_id != ?"
            );
            $check->execute([...$order, $uid]);
            if ((int)$check->fetchColumn() > 0) jsonOut(['error' => 'Acceso denegado'], 403);
        }

        $stmt = $pdo->prepare('UPDATE albums SET sort_order = ? WHERE id = ?');
        foreach ($order as $i => $id) {
            $stmt->execute([$i, $id]);
        }
        jsonOut(['ok' => true]);
    }

    // ---- PATCH: edit album ----
    if ($method === 'PATCH') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $id       = (int) ($body['id']    ?? 0);
        $name     = trim($body['name']    ?? '');
        $color    = trim($body['color']   ?? '#0071e3');
        $hasCover = array_key_exists('cover_url', $body);
        $coverUrl = $hasCover ? trim($body['cover_url']) : null;

        if ($id <= 0) jsonOut(['error' => 'ID inválido'], 422);
        if ($name === '') jsonOut(['error' => 'El nombre es obligatorio'], 422);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#0071e3';

        // Ownership check
        if (!$admin) {
            $own = $pdo->prepare('SELECT id FROM albums WHERE id = ? AND user_id = ?');
            $own->execute([$id, $uid]);
            if (!$own->fetch()) jsonOut(['error' => 'Álbum no encontrado'], 404);
        }

        if ($hasCover) {
            $pdo->prepare('UPDATE albums SET name = ?, color = ?, cover_url = ? WHERE id = ?')
                ->execute([$name, $color, $coverUrl === '' ? null : $coverUrl, $id]);
        } else {
            $pdo->prepare('UPDATE albums SET name = ?, color = ? WHERE id = ?')
                ->execute([$name, $color, $id]);
        }

        $album = $pdo->prepare(
            'SELECT a.*, COUNT(p.id) AS photo_count
             FROM albums a LEFT JOIN photos p ON p.album_id = a.id
             WHERE a.id = ? GROUP BY a.id'
        );
        $album->execute([$id]);
        jsonOut($album->fetch());
    }

    // ---- DELETE: delete album ----
    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) jsonOut(['error' => 'ID inválido'], 422);

        // Ownership check
        if (!$admin) {
            $own = $pdo->prepare('SELECT id FROM albums WHERE id = ? AND user_id = ?');
            $own->execute([$id, $uid]);
            if (!$own->fetch()) jsonOut(['error' => 'Álbum no encontrado'], 404);
        }

        $photos = $pdo->prepare('SELECT url FROM photos WHERE album_id = ?');
        $photos->execute([$id]);
        foreach ($photos->fetchAll() as $p) {
            if (!str_starts_with($p['url'], 'http')) {
                $file = __DIR__ . '/../' . ltrim($p['url'], '/');
                if (file_exists($file) && str_starts_with(realpath($file), realpath(__DIR__ . '/../uploads'))) {
                    unlink($file);
                }
            }
        }

        $albumRow = $pdo->prepare('SELECT cover_url FROM albums WHERE id = ?');
        $albumRow->execute([$id]);
        $al = $albumRow->fetch();
        if ($al && !empty($al['cover_url']) && !str_starts_with($al['cover_url'], 'http')) {
            $cf = realpath(__DIR__ . '/../' . ltrim($al['cover_url'], '/'));
            if ($cf && str_starts_with($cf, realpath(__DIR__ . '/../uploads')) && file_exists($cf)) {
                unlink($cf);
            }
        }

        $pdo->prepare('DELETE FROM albums WHERE id = ?')->execute([$id]);
        jsonOut(['ok' => true]);
    }

    jsonOut(['error' => 'Método no permitido'], 405);

} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 500);
}
