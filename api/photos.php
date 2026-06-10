<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireLogin();

// ---- ZIP download — handled before JSON headers ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_zip') {
    require_once __DIR__ . '/../config/db.php';
    $albumId = (int) ($_GET['album_id'] ?? 0);
    if ($albumId <= 0) { http_response_code(422); echo 'album_id requerido'; exit; }

    try {
        $pdo = getPDO();

        if (!isAdmin()) {
            $own = $pdo->prepare('SELECT id FROM albums WHERE id = ? AND user_id = ?');
            $own->execute([$albumId, currentUserId()]);
            if (!$own->fetch()) { http_response_code(403); echo 'Acceso denegado'; exit; }
        }

        $aStmt = $pdo->prepare('SELECT name FROM albums WHERE id = ?');
        $aStmt->execute([$albumId]);
        $album = $aStmt->fetch();
        if (!$album) { http_response_code(404); echo 'Álbum no encontrado'; exit; }

        $pStmt = $pdo->prepare('SELECT id, url, title FROM photos WHERE album_id = ?');
        $pStmt->execute([$albumId]);
        $photos = $pStmt->fetchAll();

        if (class_exists('ZipArchive')) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gallery_') . '.zip';
            $zip     = new ZipArchive();
            if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
                http_response_code(500); echo 'No se pudo crear el ZIP'; exit;
            }
            $uploadsBase = realpath(__DIR__ . '/../uploads');
            foreach ($photos as $i => $photo) {
                $ext     = pathinfo(parse_url($photo['url'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $safe    = preg_replace('/[^a-z0-9_\-]/i', '_', $photo['title'] ?: "foto_$i");
                $zipName = sprintf('%03d_%s.%s', $i + 1, $safe, $ext);

                if (str_starts_with($photo['url'], 'http')) {
                    $ctx  = stream_context_create(['http' => ['timeout' => 8], 'https' => ['timeout' => 8]]);
                    $data = @file_get_contents($photo['url'], false, $ctx);
                    if ($data !== false) $zip->addFromString($zipName, $data);
                } else {
                    $path = realpath(__DIR__ . '/../' . ltrim($photo['url'], '/'));
                    if ($path && $uploadsBase && str_starts_with($path, $uploadsBase) && file_exists($path)) {
                        $zip->addFile($path, $zipName);
                    }
                }
            }
            $zip->close();

            $safeName = preg_replace('/[^a-z0-9_\-]/i', '_', $album['name']);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $safeName . '.zip"');
            header('Content-Length: ' . filesize($tmpFile));
            header('Cache-Control: no-cache');
            readfile($tmpFile);
            unlink($tmpFile);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'fallback' => true,
                'photos'   => array_map(fn($p) => ['url' => $p['url'], 'title' => $p['title']], $photos),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Error: ' . $e->getMessage();
    }
    exit;
}

// ---- Normal JSON API ----
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/db.php';

function jsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cleanTags(string $raw): string {
    $tags = array_unique(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    return implode(',', $tags);
}

function castPhoto(array &$r): void {
    $r['tags'] = $r['tags'] ?? '';
}

function albumBelongsToUser(PDO $pdo, int $albumId): bool {
    if (isAdmin()) {
        $s = $pdo->prepare('SELECT id FROM albums WHERE id = ?');
        $s->execute([$albumId]);
    } else {
        $s = $pdo->prepare('SELECT id FROM albums WHERE id = ? AND user_id = ?');
        $s->execute([$albumId, currentUserId()]);
    }
    return (bool)$s->fetch();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getPDO();

    // ---- GET: list photos in album (with default-images fallback) ----
    if ($method === 'GET') {
        $albumId = (int) ($_GET['album_id'] ?? 0);
        if ($albumId <= 0) jsonOut(['error' => 'album_id requerido'], 422);
        if (!albumBelongsToUser($pdo, $albumId)) jsonOut(['error' => 'Álbum no encontrado'], 404);

        $stmt = $pdo->prepare(
            'SELECT id, album_id, url, title, tags, created_at FROM photos WHERE album_id = ? ORDER BY created_at ASC'
        );
        $stmt->execute([$albumId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) castPhoto($r);
        unset($r);

        // If album is empty and user has no upload folder yet, serve default sample images
        if (empty($rows)) {
            $userDir    = __DIR__ . '/../uploads/user_' . currentUserId() . '/';
            $defaultDir = __DIR__ . '/../uploads/default/';
            if (!is_dir($userDir) && is_dir($defaultDir)) {
                $i     = 0;
                $files = @scandir($defaultDir);
                if ($files) {
                    sort($files);
                    foreach ($files as $fname) {
                        if (preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $fname)) {
                            $rows[] = [
                                'id'         => --$i,   // negative = sample, not deletable
                                'album_id'   => $albumId,
                                'url'        => 'uploads/default/' . $fname,
                                'title'      => 'Foto de muestra',
                                'tags'       => 'muestra',
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                        }
                    }
                }
            }
        }

        jsonOut($rows);
    }

    // ---- POST: upload photo ----
    if ($method === 'POST') {
        $albumId = (int) ($_POST['album_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $tags    = cleanTags($_POST['tags'] ?? '');
        if ($albumId <= 0) jsonOut(['error' => 'album_id requerido'], 422);
        if (!albumBelongsToUser($pdo, $albumId)) jsonOut(['error' => 'Álbum no encontrado'], 404);

        $url      = '';
        $fileSize = 0;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['photo'];
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) jsonOut(['error' => 'Archivo muy grande (max 10 MB)'], 422);

            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) jsonOut(['error' => 'Tipo no permitido'], 422);

            // Quota check
            $userRow = $pdo->prepare('SELECT storage_limit_mb FROM users WHERE id = ?');
            $userRow->execute([currentUserId()]);
            $limitMb = (int)($userRow->fetchColumn() ?? 0);

            if ($limitMb > 0) {
                $usageStmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(p.file_size), 0)
                     FROM photos p
                     JOIN albums a ON p.album_id = a.id
                     WHERE a.user_id = ?'
                );
                $usageStmt->execute([currentUserId()]);
                $usedBytes  = (int)$usageStmt->fetchColumn();
                $limitBytes = $limitMb * 1024 * 1024;

                if (($usedBytes + $file['size']) > $limitBytes) {
                    jsonOut(['error' => "Has alcanzado tu límite de almacenamiento ({$limitMb}mb)"], 422);
                }
            }

            // Save to user's personal folder (auto-create if needed)
            $ext     = match($mime) {
                'image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/webp' => 'webp', 'image/gif' => 'gif', default => 'jpg'
            };
            $userDir = __DIR__ . '/../uploads/user_' . currentUserId() . '/';
            if (!is_dir($userDir)) mkdir($userDir, 0755, true);

            $filename = uniqid('img_', true) . '.' . $ext;
            $dest     = $userDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) jsonOut(['error' => 'Error al guardar archivo'], 500);
            $url      = 'uploads/user_' . currentUserId() . '/' . $filename;
            $fileSize = $file['size'];

        } elseif (!empty($_POST['url'])) {
            $url = filter_var(trim($_POST['url']), FILTER_SANITIZE_URL);
            if (!filter_var($url, FILTER_VALIDATE_URL)) jsonOut(['error' => 'URL inválida'], 422);
            // URL photos don't consume local quota (file_size stays 0)
        } else {
            jsonOut(['error' => 'Se requiere archivo o URL'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO photos (album_id, url, title, tags, file_size) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$albumId, $url, $title, $tags, $fileSize]);
        $newId = (int) $pdo->lastInsertId();

        $row = $pdo->prepare('SELECT id, album_id, url, title, tags, created_at FROM photos WHERE id = ?');
        $row->execute([$newId]);
        $photo = $row->fetch();
        castPhoto($photo);
        jsonOut($photo, 201);
    }

    // ---- PATCH: update tags ----
    if ($method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int) ($body['id'] ?? 0);
        $tags = cleanTags($body['tags'] ?? '');
        if ($id <= 0) jsonOut(['error' => 'ID inválido'], 422);

        $row = $pdo->prepare('SELECT album_id FROM photos WHERE id = ?');
        $row->execute([$id]);
        $photo = $row->fetch();
        if (!$photo) jsonOut(['error' => 'Foto no encontrada'], 404);
        if (!albumBelongsToUser($pdo, (int)$photo['album_id'])) jsonOut(['error' => 'Acceso denegado'], 403);

        $pdo->prepare('UPDATE photos SET tags = ? WHERE id = ?')->execute([$tags, $id]);
        jsonOut(['ok' => true, 'tags' => $tags]);
    }

    // ---- DELETE: delete photo ----
    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id === 0) jsonOut(['error' => 'ID inválido'], 422);
        if ($id < 0)  jsonOut(['error' => 'Las imágenes de muestra no se pueden eliminar'], 422);

        $row = $pdo->prepare('SELECT url, album_id FROM photos WHERE id = ?');
        $row->execute([$id]);
        $photo = $row->fetch();
        if (!$photo) jsonOut(['error' => 'Foto no encontrada'], 404);
        if (!albumBelongsToUser($pdo, (int)$photo['album_id'])) jsonOut(['error' => 'Acceso denegado'], 403);

        // Delete local file if it exists under uploads/
        if (!str_starts_with($photo['url'], 'http')) {
            $uploadsBase = realpath(__DIR__ . '/../uploads');
            $filePath    = realpath(__DIR__ . '/../' . ltrim($photo['url'], '/'));
            if ($filePath && $uploadsBase && str_starts_with($filePath, $uploadsBase) && file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
        jsonOut(['ok' => true]);
    }

    jsonOut(['error' => 'Método no permitido'], 405);

} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 500);
}
