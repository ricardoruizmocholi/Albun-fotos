<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once __DIR__ . '/config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Completa todos los campos';
    } else {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['role']     = $user['role'];
                header('Location: index.php');
                exit;
            }
            $error = 'Usuario o contraseña incorrectos';
        } catch (Throwable) {
            $error = 'Error de conexión. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión — Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .login-wrap { width:100%; max-width:400px; padding:40px 36px; }
    .login-logo  { display:flex; align-items:center; gap:8px; font-size:20px; font-weight:700; letter-spacing:-0.5px; margin-bottom:32px; }
    .login-title { font-size:26px; font-weight:700; letter-spacing:-0.5px; margin-bottom:6px; }
    .login-sub   { font-size:14px; color:var(--text-2); margin-bottom:28px; }
    .login-error { background:rgba(255,59,48,0.08); border:1px solid rgba(255,59,48,0.25); border-radius:10px; padding:10px 14px; font-size:14px; color:var(--danger); margin-bottom:16px; }
    .login-btn   { width:100%; justify-content:center; margin-top:4px; }
    .form-input  { user-select:text; -webkit-user-select:text; }
  </style>
</head>
<body>

<div class="glass login-wrap">
  <div class="login-logo">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    Mi Galería
  </div>

  <h1 class="login-title">Bienvenido</h1>
  <p class="login-sub">Inicia sesión para continuar</p>

  <?php if ($error !== ''): ?>
  <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" novalidate autocomplete="on">
    <div class="form-group">
      <label for="username">Usuario</label>
      <input id="username" name="username" class="form-input" type="text"
             value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             autocomplete="username" autocapitalize="off" spellcheck="false"
             autofocus required>
    </div>

    <div class="form-group">
      <label for="password">Contraseña</label>
      <input id="password" name="password" class="form-input" type="password"
             autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn btn-primary login-btn">Iniciar sesión</button>
  </form>
</div>

</body>
</html>
