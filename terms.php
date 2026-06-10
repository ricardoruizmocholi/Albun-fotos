<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Términos de uso — Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .legal-main { padding: 40px 24px 100px; display: flex; justify-content: center; }
    .legal-card { width: 100%; max-width: 780px; padding: 40px; }
    .legal-card h1 { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 24px; }
    .legal-card h2 { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; margin: 28px 0 10px; }
    .legal-card h2:first-of-type { margin-top: 0; }
    .legal-card p, .legal-card li { font-size: 15px; line-height: 1.6; color: var(--text-2); }
    .legal-card ul { padding-left: 20px; margin-top: 8px; }
    .legal-card a { color: var(--accent); }
    .legal-warning {
      background: rgba(255,59,48,0.06);
      border: 1px solid rgba(255,59,48,0.25);
      border-radius: var(--radius-sm);
      padding: 16px 18px;
      margin-top: 10px;
    }
    .legal-warning p, .legal-warning li { color: var(--text); }
    .legal-warning a { font-weight: 600; }
  </style>
</head>
<body>

<header class="site-header">
  <a href="index.php" class="logo">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Mi Galería
  </a>
  <nav>
    <a href="index.php" class="nav-link">← Volver</a>
  </nav>
</header>

<main class="legal-main">
  <div class="glass legal-card">
    <h1>Términos de uso</h1>

    <h2>1. Aceptación de los términos</h2>
    <p>Al registrarte y utilizar esta aplicación aceptas los presentes términos de uso. Si no estás de acuerdo con alguno de los puntos aquí descritos, no debes utilizar el servicio.</p>

    <h2>2. Descripción del servicio</h2>
    <p>Esta plataforma permite a usuarios registrados crear álbumes y subir fotografías para visualizarlas en una galería 3D interactiva. El acceso a los álbumes está restringido al propietario de la cuenta y a los administradores de la plataforma.</p>

    <h2>3. Cuentas de usuario</h2>
    <p>Cada usuario es responsable de mantener la confidencialidad de sus credenciales de acceso (usuario y contraseña) y de toda la actividad realizada desde su cuenta. Cada cuenta dispone de un límite de almacenamiento (cuota) que debe respetarse.</p>

    <h2>4. Contenido subido por el usuario</h2>
    <p>Conservas todos los derechos sobre las fotografías que subas y eres el único responsable de garantizar que dispones de los derechos necesarios sobre dicho contenido. Queda prohibido subir contenido que infrinja derechos de propiedad intelectual, que sea difamatorio, violento o ilegal.</p>

    <h2>5. ⚠️ Prohibición absoluta de contenido de abuso sexual infantil (CSAM)</h2>
    <div class="legal-warning">
      <p>Está terminantemente y de forma absoluta prohibido subir, almacenar, compartir o distribuir cualquier imagen o material que represente abuso sexual infantil (CSAM) o que sexualice de cualquier forma a menores de edad.</p>
      <p>Cualquier cuenta en la que se detecte este tipo de contenido será suspendida y eliminada de forma inmediata y permanente, sin previo aviso, y el contenido y los datos disponibles serán reportados a las autoridades y organizaciones competentes, incluyendo:</p>
      <ul>
        <li>INHOPE — <a href="https://www.inhope.org" target="_blank" rel="noopener">https://www.inhope.org</a></li>
        <li>Policía Nacional — <a href="https://www.policia.es" target="_blank" rel="noopener">https://www.policia.es</a></li>
        <li>Europol — <a href="https://www.europol.europa.eu" target="_blank" rel="noopener">https://www.europol.europa.eu</a></li>
      </ul>
      <p>La tolerancia ante este tipo de contenido es cero.</p>
    </div>

    <h2>6. Modificaciones y limitación de responsabilidad</h2>
    <p>El titular de la plataforma podrá modificar estos términos de uso en cualquier momento. El uso continuado del servicio tras la publicación de los cambios implica la aceptación de los nuevos términos. El servicio se ofrece «tal cual», sin garantía de disponibilidad continua, y el titular no se hace responsable de la pérdida de contenido derivada de fallos técnicos.</p>
  </div>
</main>

</body>
</html>
