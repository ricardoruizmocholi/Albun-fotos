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

// Genera posición 3D aleatoria dispersa
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

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getPDO();

    // ---- GET: listar fotos de un álbum ----
    if ($method === 'GET') {
        $albumId = (int) ($_GET['album_id'] ?? 0);
        if ($albumId <= 0) jsonOut(['error' => 'album_id requerido'], 422);

        $stmt = $pdo->prepare('SELECT * FROM photos WHERE album_id = ? ORDER BY created_at ASC');
        $stmt->execute([$albumId]);
        $rows = $stmt->fetchAll();

        // Castear floats
        foreach ($rows as &$r) {
            foreach (['pos_x','pos_y','pos_z','rot_x','rot_y','rot_z'] as $f) {
                $r[$f] = (float) $r[$f];
            }
        }
        jsonOut($rows);
    }

    // ---- POST: subir foto (multipart) o registrar URL externa ----
    if ($method === 'POST') {
        $albumId = (int) ($_POST['album_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        if ($albumId <= 0) jsonOut(['error' => 'album_id requerido'], 422);

        // Verificar que el álbum existe
        $check = $pdo->prepare('SELECT id FROM albums WHERE id = ?');
        $check->execute([$albumId]);
        if (!$check->fetch()) jsonOut(['error' => 'Álbum no encontrado'], 404);

        $url = '';

        // Opción A: archivo subido
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['photo'];
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) jsonOut(['error' => 'Archivo muy grande (max 10 MB)'], 422);

            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) jsonOut(['error' => 'Tipo no permitido'], 422);

            $ext  = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                default      => 'jpg',
            };
            $filename = uniqid('img_', true) . '.' . $ext;
            $dest     = __DIR__ . '/../uploads/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                jsonOut(['error' => 'Error al guardar archivo'], 500);
            }
            $url = 'uploads/' . $filename;

        // Opción B: URL externa
        } elseif (!empty($_POST['url'])) {
            $url = filter_var(trim($_POST['url']), FILTER_SANITIZE_URL);
            if (!filter_var($url, FILTER_VALIDATE_URL)) jsonOut(['error' => 'URL inválida'], 422);
        } else {
            jsonOut(['error' => 'Se requiere archivo o URL'], 422);
        }

        $pos = random3D();
        $stmt = $pdo->prepare(
            'INSERT INTO photos (album_id, url, title, pos_x, pos_y, pos_z, rot_x, rot_y, rot_z)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $albumId, $url, $title,
            $pos['pos_x'], $pos['pos_y'], $pos['pos_z'],
            $pos['rot_x'], $pos['rot_y'], $pos['rot_z'],
        ]);
        $newId = (int) $pdo->lastInsertId();

        $row = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
        $row->execute([$newId]);
        $photo = $row->fetch();
        foreach (['pos_x','pos_y','pos_z','rot_x','rot_y','rot_z'] as $f) {
            $photo[$f] = (float) $photo[$f];
        }
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

        // Borrar archivo si es local
        if (!str_starts_with($photo['url'], 'http')) {
            $file = __DIR__ . '/../' . ltrim($photo['url'], '/');
            if (file_exists($file) && str_starts_with(realpath($file), realpath(__DIR__ . '/../uploads'))) {
                unlink($file);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM photos WHERE id = ?');
        $stmt->execute([$id]);
        jsonOut(['ok' => true]);
    }

    jsonOut(['error' => 'Método no permitido'], 405);

} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 500);
}
