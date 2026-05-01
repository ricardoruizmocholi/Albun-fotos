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
  <a href="index.php" class="logo">🖼 Mi Galería</a>
  <nav>
    <a href="index.php" class="nav-link">Álbumes</a>
  </nav>
</header>

<!-- ---- Main ---- -->
<main class="main">
  <h1 class="page-title">Mis álbumes</h1>
  <p class="page-subtitle">Selecciona un álbum para ver la galería 3D</p>

  <div class="albums-grid" id="albumsGrid">
    <!-- Álbumes cargados por JS -->

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
        <label>Emoji</label>
        <div class="emoji-grid" id="emojiGrid"></div>
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

<!-- ---- Toasts ---- -->
<div class="toast-container" id="toastContainer"></div>

<script src="js/albums.js"></script>
</body>
</html>
