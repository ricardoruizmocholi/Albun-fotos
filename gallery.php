<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$albumId = (int) ($_GET['album'] ?? 0);
if ($albumId <= 0) { header('Location: index.php'); exit; }

try {
  $pdo  = getPDO();
  $stmt = $pdo->prepare('SELECT * FROM albums WHERE id = ?');
  $stmt->execute([$albumId]);
  $album = $stmt->fetch();
} catch(Throwable $e) {
  $album = null;
}

if (!$album) { header('Location: index.php'); exit; }

$albumName  = htmlspecialchars($album['name'],  ENT_QUOTES, 'UTF-8');
$albumEmoji = htmlspecialchars($album['emoji'], ENT_QUOTES, 'UTF-8');
$albumColor = preg_match('/^#[0-9a-fA-F]{6}$/', $album['color']) ? $album['color'] : '#0071e3';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $albumEmoji ?> <?= $albumName ?> — Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Acento del álbum */
    :root { --accent: <?= $albumColor ?>; --accent-h: <?= $albumColor ?>cc; }
    /* Fondo base: el canvas animado lo cubre visualmente */
    body { background: #e8eaf6; }
    /* Elimina círculos difusos CSS del fondo anterior si existen */
    .bg-circles, .bg-blob, .bg-orb, .anim-circle { display: none !important; }
  </style>
</head>
<body>

<!-- ---- Canvas fondo animado (z-index:0, detrás de todo) ---- -->
<canvas id="bg-dots" style="position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>

<!-- ---- Site header ---- -->
<header class="site-header">
  <a href="index.php" class="logo">🖼 Mi Galería</a>
  <nav>
    <a href="index.php" class="nav-link">← Álbumes</a>
  </nav>
</header>

<!-- ---- Gallery header ---- -->
<div class="gallery-header">
  <span style="font-size:24px"><?= $albumEmoji ?></span>
  <div>
    <div class="gallery-album-name"><?= $albumName ?></div>
    <div class="gallery-photo-count" id="photoCount">Cargando…</div>
  </div>
  <div class="gallery-actions">
    <button class="btn btn-primary btn-sm" id="uploadBtn" type="button">
      + Añadir foto
    </button>
  </div>
</div>

<!-- ---- 3D Scene ---- -->
<div class="scene-wrapper" id="sceneWrapper">
  <div class="scene-perspective">
    <div class="scene-3d" id="scene3d"></div>
  </div>
</div>

<!-- ---- Controls hint ---- -->
<div class="controls-hint">
  <div class="hint-pill"><strong>Arrastrar</strong> — mover</div>
  <div class="hint-pill"><strong>Rueda</strong> — zoom</div>
  <div class="hint-pill"><strong>Clic</strong> — ampliar</div>
  <div class="hint-pill">Pellizco táctil — zoom</div>
</div>

<!-- ---- Lightbox ---- -->
<div class="lightbox-overlay" id="lightboxOverlay">
  <button class="lightbox-close" id="lightboxClose" aria-label="Cerrar">✕</button>
  <div class="lightbox-inner">
    <img id="lightboxImg" src="" alt="">
    <div class="lightbox-title" id="lightboxTitle"></div>
  </div>
</div>

<!-- ---- Upload modal ---- -->
<div class="modal-overlay" id="uploadModal" role="dialog" aria-modal="true" aria-labelledby="uploadTitle">
  <div class="modal-box">
    <h2 class="modal-title" id="uploadTitle">Añadir foto</h2>
    <form id="uploadForm" novalidate>

      <!-- Dropzone -->
      <div class="form-group">
        <label>Archivo (JPG, PNG, WebP, GIF — max 10 MB)</label>
        <div class="dropzone" id="dropzone">
          <div class="drop-icon">📁</div>
          <p>Arrastra aquí o haz clic para seleccionar</p>
        </div>
        <input type="file" id="photoFile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
      </div>

      <!-- Divisor -->
      <div style="display:flex;align-items:center;gap:10px;margin:4px 0 16px">
        <div style="flex:1;height:1px;background:rgba(0,0,0,0.08)"></div>
        <span style="font-size:12px;color:var(--text-2);font-weight:600">O</span>
        <div style="flex:1;height:1px;background:rgba(0,0,0,0.08)"></div>
      </div>

      <!-- URL externa -->
      <div class="form-group">
        <label for="photoUrl">URL de imagen externa</label>
        <input id="photoUrl" class="form-input" type="url" placeholder="https://…">
      </div>

      <!-- Preview -->
      <div id="uploadPreview" style="margin-bottom:12px;display:none"></div>

      <!-- Título -->
      <div class="form-group">
        <label for="photoTitle">Título (opcional)</label>
        <input id="photoTitle" class="form-input" type="text" placeholder="Ej: Momento especial" maxlength="200">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="cancelUpload">Cancelar</button>
        <button type="submit" class="btn btn-primary">Subir foto</button>
      </div>
    </form>
  </div>
</div>

<!-- ---- Toasts ---- -->
<div class="toast-container" id="toastContainer"></div>

<script src="js/gallery3d.js"></script>
<script type="module">
  import { init } from "./canvas-bg-dots.js";
  init(document.getElementById("bg-dots"));
</script>
</body>
</html>
