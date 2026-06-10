<?php
declare(strict_types=1);
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>404 — Página no encontrada — Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { display:flex; flex-direction:column; min-height:100vh; }
    .error-main { flex:1; display:flex; align-items:center; justify-content:center; padding:24px; }
    .error-wrap { width:100%; max-width:420px; padding:48px 36px; text-align:center; }
    .error-wrap .empty-icon { font-size:64px; margin-bottom:8px; }
    .error-wrap h1 { font-size:28px; font-weight:700; letter-spacing:-0.5px; margin-bottom:8px; }
    .error-wrap p { font-size:15px; color:var(--text-2); margin-bottom:24px; }
  </style>
</head>
<body>

<header class="site-header">
  <a href="index.php" class="logo">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Mi Galería
  </a>
</header>

<main class="error-main">
  <div class="glass error-wrap">
    <div class="empty-icon">🔍</div>
    <h1>404 — Página no encontrada</h1>
    <p>La página que buscas no existe o ha sido eliminada.</p>
    <a href="index.php" class="btn btn-primary">← Volver al inicio</a>
  </div>
</main>

</body>
</html>
