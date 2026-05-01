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

// 24 orbs for animated background
$orbs = [
  ['w'=>140,'h'=>140,'t'=>  7,'l'=> 4,'c'=>'rgb(173,216,230)','a'=>'floatA','d'=>'14s','dl'=>'0s'],
  ['w'=> 80,'h'=> 80,'t'=> 18,'l'=>88,'c'=>'rgb(255,182,193)','a'=>'floatB','d'=>'11s','dl'=>'-3s'],
  ['w'=>180,'h'=>180,'t'=> 55,'l'=>45,'c'=>'rgb(144,238,144)','a'=>'floatC','d'=>'17s','dl'=>'-7s'],
  ['w'=> 60,'h'=> 60,'t'=> 72,'l'=>12,'c'=>'rgb(255,218,185)','a'=>'floatD','d'=> '9s','dl'=>'-2s'],
  ['w'=>100,'h'=>100,'t'=> 30,'l'=>70,'c'=>'rgb(221,160,221)','a'=>'floatA','d'=>'13s','dl'=>'-5s'],
  ['w'=> 50,'h'=> 50,'t'=> 85,'l'=>60,'c'=>'rgb(176,224,230)','a'=>'floatB','d'=> '8s','dl'=>'-1s'],
  ['w'=>120,'h'=>120,'t'=> 10,'l'=>55,'c'=>'rgb(255,239,213)','a'=>'floatC','d'=>'16s','dl'=>'-9s'],
  ['w'=> 70,'h'=> 70,'t'=> 45,'l'=>25,'c'=>'rgb(230,230,250)','a'=>'floatD','d'=>'12s','dl'=>'-4s'],
  ['w'=>160,'h'=>160,'t'=> 65,'l'=>80,'c'=>'rgb(255,228,225)','a'=>'floatA','d'=>'18s','dl'=>'-6s'],
  ['w'=> 45,'h'=> 45,'t'=> 20,'l'=>35,'c'=>'rgb(204,255,204)','a'=>'floatB','d'=>'10s','dl'=>'-8s'],
  ['w'=> 90,'h'=> 90,'t'=> 78,'l'=> 3,'c'=>'rgb(255,204,229)','a'=>'floatC','d'=>'15s','dl'=>'-2s'],
  ['w'=>110,'h'=>110,'t'=>  3,'l'=>78,'c'=>'rgb(204,229,255)','a'=>'floatD','d'=>'11s','dl'=>'-7s'],
  ['w'=> 55,'h'=> 55,'t'=> 50,'l'=>50,'c'=>'rgb(255,255,204)','a'=>'floatA','d'=> '9s','dl'=>'-3s'],
  ['w'=>130,'h'=>130,'t'=> 88,'l'=>40,'c'=>'rgb(204,255,255)','a'=>'floatB','d'=>'16s','dl'=>'-5s'],
  ['w'=> 75,'h'=> 75,'t'=> 38,'l'=>92,'c'=>'rgb(255,220,200)','a'=>'floatC','d'=>'13s','dl'=>'-1s'],
  ['w'=> 95,'h'=> 95,'t'=> 15,'l'=>18,'c'=>'rgb(220,200,255)','a'=>'floatD','d'=>'10s','dl'=>'-6s'],
  ['w'=> 40,'h'=> 40,'t'=> 60,'l'=>68,'c'=>'rgb(200,240,220)','a'=>'floatA','d'=> '8s','dl'=>'-4s'],
  ['w'=>150,'h'=>150,'t'=> 25,'l'=>62,'c'=>'rgb(255,210,210)','a'=>'floatB','d'=>'17s','dl'=>'-9s'],
  ['w'=> 65,'h'=> 65,'t'=> 92,'l'=>22,'c'=>'rgb(210,230,255)','a'=>'floatC','d'=>'12s','dl'=>'-2s'],
  ['w'=> 85,'h'=> 85,'t'=> 42,'l'=> 8,'c'=>'rgb(255,240,200)','a'=>'floatD','d'=>'14s','dl'=>'-7s'],
  ['w'=>115,'h'=>115,'t'=> 70,'l'=>55,'c'=>'rgb(200,255,230)','a'=>'floatA','d'=>'11s','dl'=>'-5s'],
  ['w'=> 48,'h'=> 48,'t'=>  5,'l'=>42,'c'=>'rgb(255,200,240)','a'=>'floatB','d'=> '9s','dl'=>'-3s'],
  ['w'=>170,'h'=>170,'t'=> 48,'l'=>30,'c'=>'rgb(230,210,255)','a'=>'floatC','d'=>'18s','dl'=>'-8s'],
  ['w'=> 58,'h'=> 58,'t'=> 82,'l'=>82,'c'=>'rgb(200,220,255)','a'=>'floatD','d'=>'13s','dl'=>'-1s'],
];
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
    body  { background: transparent; }
  </style>
</head>
<body>

<!-- ---- Animated background (gallery only) ---- -->
<div class="gallery-bg" aria-hidden="true">
  <?php foreach ($orbs as $o): ?>
  <div class="orb" style="
    width:<?= $o['w'] ?>px;height:<?= $o['h'] ?>px;
    top:<?= $o['t'] ?>%;left:<?= $o['l'] ?>%;
    background:<?= $o['c'] ?>;
    animation:<?= $o['a'] ?> <?= $o['d'] ?> <?= $o['dl'] ?> ease-in-out infinite alternate;
  "></div>
  <?php endforeach; ?>
</div>

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
    <h2 class="modal-title" id="uploadTitle">Añadir foto</h2>
    <form id="uploadForm" novalidate>

      <!-- Dropzone -->
      <div class="form-group">
        <label>Archivo (JPG, PNG, WebP, GIF — max 10 MB)</label>
        <div class="dropzone" id="dropzone">
          <div class="drop-icon"></div>
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

      <!-- Etiquetas -->
      <div class="form-group">
        <label for="photoTags">Etiquetas (opcional)</label>
        <input id="photoTags" class="form-input" type="text" placeholder="playa, verano, familia" maxlength="500">
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
</body>
</html>
