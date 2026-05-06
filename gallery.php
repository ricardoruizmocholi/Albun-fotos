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
} catch(Throwable $e) { $album = null; }

if (!$album) { header('Location: index.php'); exit; }

$albumName  = htmlspecialchars($album['name'], ENT_QUOTES, 'UTF-8');
$albumColor = preg_match('/^#[0-9a-fA-F]{6}$/', $album['color']) ? $album['color'] : '#0071e3';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $albumName ?> — Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    :root { --accent: <?= $albumColor ?>; --accent-h: <?= $albumColor ?>cc; }
    body  { background: transparent; cursor: grab; }
  </style>
</head>
<body>

<canvas id="bg-dots" style="position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>
<!-- ---- Site header ---- -->
<header class="site-header">
  <a href="index.php" class="logo">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Mi Galería
  </a>
  <nav>
    <a href="index.php" class="nav-link">← Álbumes</a>
  </nav>
</header>

<!-- ---- Gallery header ---- -->
<div class="gallery-header">
  <?php if (!empty($album['cover_url'] ?? '')): ?>
  <img src="<?= htmlspecialchars($album['cover_url'], ENT_QUOTES, 'UTF-8') ?>" alt=""
       style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.15)">
  <?php else: ?>
  <div style="width:32px;height:32px;border-radius:8px;background:<?= $albumColor ?>;flex-shrink:0"></div>
  <?php endif; ?>
  <div>
    <div class="gallery-album-name"><?= $albumName ?></div>
    <div class="gallery-photo-count" id="photoCount">Cargando…</div>
  </div>
  <div class="gallery-actions">
    <button class="btn btn-primary btn-sm" id="uploadBtn" type="button">+ Añadir foto</button>
  </div>
</div>

<!-- ---- Search ---- -->
<div class="search-wrap">
  <div class="search-input-row">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:var(--text-2)"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input id="searchInput" type="text" placeholder="Buscar por etiqueta…" autocomplete="off" spellcheck="false">
  </div>
  <div id="searchBadge"></div>
</div>

<!-- ---- 3D Scene ---- -->
<div id="gallery-status" class="empty-state" style="position:fixed;inset:104px 0 56px;z-index:2;pointer-events:none;"></div>
<div id="scene">
  <div id="world"></div>
</div>

<!-- ---- Controls hint ---- -->
<div class="controls-hint">
  <div class="hint-pill"><strong>Arrastrar</strong> — mover</div>
  <div class="hint-pill"><strong>Rueda</strong> — zoom</div>
  <div class="hint-pill"><strong>Clic</strong> — ampliar</div>
  <div class="hint-pill">Pellizco — zoom táctil</div>
</div>

<!-- ---- Lightbox ---- -->
<div class="lightbox-overlay" id="lightboxOverlay">
  <div class="lightbox-top-actions">
    <button class="btn-lb-action" id="lightboxDownload" aria-label="Descargar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Descargar
    </button>
    <button class="btn-lb-action btn-lb-close" id="lightboxClose" aria-label="Cerrar">&#x2715;</button>
  </div>
  <div class="lightbox-inner">
    <img id="lightboxImg" src="" alt="">
    <div class="lightbox-title" id="lightboxTitle"></div>
    <div class="lb-tags-row" id="lightboxTags" style="display:none"></div>
  </div>
</div>

<!-- ---- Upload modal ---- -->
<div class="modal-overlay" id="uploadModal" role="dialog" aria-modal="true" aria-labelledby="uploadTitle">
  <div class="modal-box">
    <h2 class="modal-title" id="uploadTitle">Añadir fotos</h2>
    <form id="uploadForm" novalidate>

      <!-- Dropzone -->
      <div class="form-group">
        <label>Archivos (JPG, PNG, WebP, GIF — max 10 MB c/u)</label>
        <div class="dropzone" id="dropzone">
          <div class="drop-icon"></div>
          <p>Arrastra aquí o haz clic para seleccionar</p>
        </div>
        <input type="file" id="photoFile" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none">
        <div id="uploadFileWarning" style="display:none;font-size:12px;color:var(--danger);margin-top:6px"></div>
      </div>

      <!-- Preview grid (modo multi-archivo) -->
      <div id="uploadPreviewGrid" class="upload-preview-grid" style="display:none"></div>

      <!-- Divisor -->
      <div style="display:flex;align-items:center;gap:10px;margin:4px 0 16px">
        <div style="flex:1;height:1px;background:rgba(0,0,0,0.08)"></div>
        <span style="font-size:12px;color:var(--text-2);font-weight:600">O</span>
        <div style="flex:1;height:1px;background:rgba(0,0,0,0.08)"></div>
      </div>

      <!-- URL externa (modo URL) -->
      <div class="form-group" id="uploadUrlGroup">
        <label for="photoUrl">URL de imagen externa</label>
        <input id="photoUrl" class="form-input" type="url" placeholder="https://…">
      </div>

      <!-- Preview URL -->
      <div id="uploadPreview" style="margin-bottom:12px;display:none"></div>

      <!-- Título (modo URL) -->
      <div class="form-group" id="uploadTitleGroup">
        <label for="photoTitle">Título (opcional)</label>
        <input id="photoTitle" class="form-input" type="text" placeholder="Ej: Momento especial" maxlength="200">
      </div>

      <!-- Etiquetas (siempre visible) -->
      <div class="form-group">
        <label for="photoTags">Etiquetas (opcional, para todas)</label>
        <input id="photoTags" class="form-input" type="text" placeholder="playa, verano, familia" maxlength="500">
      </div>

      <!-- Barra de progreso -->
      <div id="uploadProgressWrap" style="display:none;margin-bottom:12px">
        <div style="height:4px;background:rgba(0,0,0,0.08);border-radius:2px;overflow:hidden">
          <div id="uploadProgressBar" style="height:100%;width:0%;background:#0071e3;border-radius:2px;transition:width 0.25s ease"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="cancelUpload">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">Subir foto</button>
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
