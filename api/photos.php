<?php
declare(strict_types=1);

// ---- ZIP download — handled before JSON headers ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_zip') {
    require_once __DIR__ . '/../config/db.php';
    $albumId = (int) ($_GET['album_id'] ?? 0);
    if ($albumId <= 0) { http_response_code(422); echo 'album_id requerido'; exit; }

    try {
        $pdo   = getPDO();
        $aStmt = $pdo->prepare('SELECT name FROM albums WHERE id = ?');
        $aStmt->execute([$albumId]);
        $album = $aStmt->fetch();
        if (!$album) { http_response_code(404); echo 'Álbum no encontrado'; exit; }

        $pStmt = $pdo->prepare('SELECT * FROM photos WHERE album_id = ?');
        $pStmt->execute([$albumId]);
        $photos = $pStmt->fetchAll();

        if (class_exists('ZipArchive')) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gallery_') . '.zip';
            $zip     = new ZipArchive();
            if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
                http_response_code(500); echo 'No se pudo crear el ZIP'; exit;
            }
            foreach ($photos as $i => $photo) {
                $ext      = pathinfo(parse_url($photo['url'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $safe     = preg_replace('/[^a-z0-9_\-]/i', '_', $photo['title'] ?: "foto_$i");
                $zipName  = sprintf('%03d_%s.%s', $i + 1, $safe, $ext);

                if (str_starts_with($photo['url'], 'http')) {
                    $ctx  = stream_context_create(['http' => ['timeout' => 8], 'https' => ['timeout' => 8]]);
                    $data = @file_get_contents($photo['url'], false, $ctx);
                    if ($data !== false) $zip->addFromString($zipName, $data);
                } else {
                    $path = realpath(__DIR__ . '/../' . ltrim($photo['url'], '/'));
                    if ($path && str_starts_with($path, realpath(__DIR__ . '/../uploads')) && file_exists($path)) {
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
            // Fallback: JSON list so the browser can handle downloads individually
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
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/db.php';

function jsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function random3D(): array {
    return [
        'pos_x' => mt_rand(-600, 600),
        'pos_y' => mt_rand(-300, 300),
        'pos_z' => mt_rand(-500,  50),
        'rot_x' => mt_rand(-12,   12) + (mt_rand(0, 9) / 10),
        'rot_y' => mt_rand(-15,   15) + (mt_rand(0, 9) / 10),
        'rot_z' => mt_rand(-6,     6) + (mt_rand(0, 9) / 10),
    ];
}

function cleanTags(string $raw): string {
    $tags = array_unique(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    return implode(',', $tags);
}

function castPhoto(array &$r): void {
    foreach (['pos_x','pos_y','pos_z','rot_x','rot_y','rot_z'] as $f) {
        $r[$f] = (float) $r[$f];
    }
    $r['tags'] = $r['tags'] ?? '';
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getPDO();

    // ---- GET: listar fotos del álbum ----
    if ($method === 'GET') {
        $albumId = (int) ($_GET['album_id'] ?? 0);
        if ($albumId <= 0) jsonOut(['error' => 'album_id requerido'], 422);

        $stmt = $pdo->prepare('SELECT * FROM photos WHERE album_id = ? ORDER BY created_at ASC');
        $stmt->execute([$albumId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) castPhoto($r);
        jsonOut($rows);
    }

    // ---- POST: subir foto ----
    if ($method === 'POST') {
        $albumId = (int) ($_POST['album_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $tags    = cleanTags($_POST['tags'] ?? '');
        if ($albumId <= 0) jsonOut(['error' => 'album_id requerido'], 422);

        $check = $pdo->prepare('SELECT id FROM albums WHERE id = ?');
        $check->execute([$albumId]);
        if (!$check->fetch()) jsonOut(['error' => 'Álbum no encontrado'], 404);

        $url = '';

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['photo'];
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) jsonOut(['error' => 'Archivo muy grande (max 10 MB)'], 422);

            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) jsonOut(['error' => 'Tipo no permitido'], 422);

            $ext      = match($mime) { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',default=>'jpg' };
            $filename = uniqid('img_', true) . '.' . $ext;
            $dest     = __DIR__ . '/../uploads/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) jsonOut(['error' => 'Error al guardar archivo'], 500);
            $url = 'uploads/' . $filename;

        } elseif (!empty($_POST['url'])) {
            $url = filter_var(trim($_POST['url']), FILTER_SANITIZE_URL);
            if (!filter_var($url, FILTER_VALIDATE_URL)) jsonOut(['error' => 'URL inválida'], 422);
        } else {
            jsonOut(['error' => 'Se requiere archivo o URL'], 422);
        }

        $pos  = random3D();
        $stmt = $pdo->prepare(
            'INSERT INTO photos (album_id, url, title, tags, pos_x, pos_y, pos_z, rot_x, rot_y, rot_z)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $albumId, $url, $title, $tags,
            $pos['pos_x'], $pos['pos_y'], $pos['pos_z'],
            $pos['rot_x'], $pos['rot_y'], $pos['rot_z'],
        ]);
        $newId = (int) $pdo->lastInsertId();

        $row = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
        $row->execute([$newId]);
        $photo = $row->fetch();
        castPhoto($photo);
        jsonOut($photo, 201);
    }

    // ---- DELETE: borrar foto ----
    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) jsonOut(['error' => 'ID inválido'], 422);

        $row = $pdo->prepare('SELECT url FROM photos WHERE id = ?');
        $row->execute([$id]);
        $photo = $row->fetch();
        if (!$photo) jsonOut(['error' => 'Foto no encontrada'], 404);

        if (!str_starts_with($photo['url'], 'http')) {
            $file = realpath(__DIR__ . '/../' . ltrim($photo['url'], '/'));
            if ($file && str_starts_with($file, realpath(__DIR__ . '/../uploads')) && file_exists($file)) {
                unlink($file);
            }
        }

        $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
        jsonOut(['ok' => true]);
    }

    jsonOut(['error' => 'Método no permitido'], 405);

} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 500);
}
