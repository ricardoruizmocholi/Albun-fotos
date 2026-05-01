<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ---- Header ---- -->
<header class="site-header">
  <a href="index.php" class="logo">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Mi Galería
  </a>
  <nav>
    <a href="index.php" class="nav-link">Álbumes</a>
  </nav>
</header>

<!-- ---- Main ---- -->
<main class="main">
  <h1 class="page-title">Mis álbumes</h1>
  <p class="page-subtitle">Selecciona un álbum para ver la galería 3D</p>

  <div class="albums-grid" id="albumsGrid">

    <!-- Botón nuevo álbum -->
    <button class="album-new-card" onclick="openModal()" type="button">
      <span class="plus">+</span>
      <span>Nuevo álbum</span>
    </button>
  </div>
</main>

<!-- ---- Modal: nuevo álbum ---- -->
<div class="modal-overlay" id="albumModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="modal-box">
    <h2 class="modal-title" id="modalTitle">Nuevo álbum</h2>
    <form id="albumForm" novalidate>

      <div class="form-group">
        <label for="albumName">Nombre</label>
        <input id="albumName" class="form-input" type="text" placeholder="Ej: Cumpleaños 2025" maxlength="120" required autocomplete="off">
      </div>

      <div class="form-group">
        <label>Color de acento</label>
        <div class="color-row" id="colorRow"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear álbum</button>
      </div>
    </form>
  </div>
</div>

<!-- ---- Modal: editar álbum ---- -->
<div class="modal-overlay" id="editAlbumModal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
  <div class="modal-box">
    <h2 class="modal-title" id="editModalTitle">Editar álbum</h2>
    <form id="editAlbumForm" novalidate>

      <div class="form-group">
        <label for="editAlbumName">Nombre</label>
        <input id="editAlbumName" class="form-input" type="text" maxlength="120" required autocomplete="off">
      </div>

      <div class="form-group">
        <label for="editAlbumColor">Color</label>
        <input id="editAlbumColor" type="color" class="color-native">
      </div>

      <div class="form-group">
        <label>Imagen de portada</label>
        <div class="cover-tabs">
          <button type="button" class="cover-tab active" data-tab="url">URL</button>
          <button type="button" class="cover-tab" data-tab="file">Subir</button>
        </div>
        <div class="cover-tab-panel" data-panel="url">
          <input id="editCoverUrl" class="form-input" type="url" placeholder="https://…" style="margin-top:8px">
        </div>
        <div class="cover-tab-panel" data-panel="file" style="display:none;margin-top:8px">
          <input type="file" id="editCoverFile" accept="image/jpeg,image/png,image/webp" style="display:none">
          <button type="button" class="btn btn-ghost btn-sm" id="editCoverFileTrigger">Seleccionar archivo</button>
        </div>
        <div id="editCoverPreview" style="margin-top:10px;display:none">
          <img id="editCoverPreviewImg" src="" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:8px;display:block;">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="cancelEditAlbum">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- ---- Toasts ---- -->
<div class="toast-container" id="toastContainer"></div>

<script src="js/albums.js"></script>
</body>
</html>
