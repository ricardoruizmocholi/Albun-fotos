<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
requireAdmin();
require_once __DIR__ . '/config/db.php';

function adminJsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetchUsers(PDO $pdo): array {
    return $pdo->query(
        'SELECT u.id, u.username, u.role, u.storage_limit_mb, u.created_at,
                ROUND(COALESCE(SUM(p.file_size),0)/1048576, 2) AS used_mb,
                COUNT(DISTINCT a.id) AS album_count
         FROM users u
         LEFT JOIN albums a  ON a.user_id  = u.id
         LEFT JOIN photos p  ON p.album_id = a.id
         GROUP BY u.id ORDER BY u.created_at ASC'
    )->fetchAll();
}

function renderStorageCell(float $usedMb, int $limitMb): string {
    $usedStr = number_format($usedMb, 2);
    if ($limitMb === 0) {
        return $usedStr . ' MB / ∞';
    }
    $pct    = min(100, ($usedMb / $limitMb) * 100);
    $barCls = $pct >= 90 ? ' danger' : '';
    $bar    = '<div class="storage-bar"><div class="storage-bar-fill' . $barCls . '" style="width:' . $pct . '%"></div></div>';
    return $usedStr . ' MB / ' . $limitMb . ' MB' . $bar;
}

$pdo = getPDO();

// ---- GET ?api: return user list as JSON ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api'])) {
    adminJsonOut(fetchUsers($pdo));
}

// ---- POST: create / update / delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ct   = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    $body = str_contains($ct, 'application/json')
          ? (json_decode(file_get_contents('php://input'), true) ?? [])
          : $_POST;

    $action = $body['action'] ?? '';

    // ---- CREATE ----
    if ($action === 'create') {
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $role     = in_array($body['role'] ?? '', ['admin','user'], true) ? $body['role'] : 'user';
        $limitMb  = max(0, (int)($body['storage_limit_mb'] ?? 500));

        if ($username === '') adminJsonOut(['error' => 'El nombre de usuario es obligatorio'], 422);
        if ($password === '') adminJsonOut(['error' => 'La contraseña es obligatoria'], 422);
        if (strlen($username) > 60) adminJsonOut(['error' => 'Nombre demasiado largo (máx 60)'], 422);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $pdo->prepare('INSERT INTO users (username, password_hash, role, storage_limit_mb) VALUES (?,?,?,?)')
                ->execute([$username, $hash, $role, $limitMb]);
            $newId   = (int)$pdo->lastInsertId();
            $userDir = __DIR__ . '/uploads/user_' . $newId . '/';
            if (!is_dir($userDir)) @mkdir($userDir, 0755, true);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                adminJsonOut(['error' => 'El nombre de usuario ya existe'], 409);
            }
            adminJsonOut(['error' => $e->getMessage()], 500);
        }
        adminJsonOut(['ok' => true, 'users' => fetchUsers($pdo)]);
    }

    // ---- UPDATE ----
    if ($action === 'update') {
        $id       = (int)($body['id'] ?? 0);
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $role     = in_array($body['role'] ?? '', ['admin','user'], true) ? $body['role'] : 'user';
        $limitMb  = max(0, (int)($body['storage_limit_mb'] ?? 500));

        if ($id <= 0) adminJsonOut(['error' => 'ID inválido'], 422);
        if ($username === '') adminJsonOut(['error' => 'El nombre de usuario es obligatorio'], 422);

        // Guard: cannot demote last admin
        $curRoleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $curRoleStmt->execute([$id]);
        $currentRole = $curRoleStmt->fetchColumn();
        if ($currentRole === 'admin' && $role !== 'admin') {
            $otherAdmins = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "admin" AND id != ?');
            $otherAdmins->execute([$id]);
            if ((int)$otherAdmins->fetchColumn() === 0) {
                adminJsonOut(['error' => 'No puedes degradar al último administrador'], 422);
            }
        }

        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET username=?, password_hash=?, role=?, storage_limit_mb=? WHERE id=?')
                    ->execute([$username, $hash, $role, $limitMb, $id]);
            } else {
                $pdo->prepare('UPDATE users SET username=?, role=?, storage_limit_mb=? WHERE id=?')
                    ->execute([$username, $role, $limitMb, $id]);
            }
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                adminJsonOut(['error' => 'El nombre de usuario ya existe'], 409);
            }
            adminJsonOut(['error' => $e->getMessage()], 500);
        }
        adminJsonOut(['ok' => true, 'users' => fetchUsers($pdo)]);
    }

    // ---- DELETE ----
    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) adminJsonOut(['error' => 'ID inválido'], 422);
        if ($id === currentUserId()) adminJsonOut(['error' => 'No puedes eliminar tu propia cuenta'], 422);

        $targetRoleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $targetRoleStmt->execute([$id]);
        $targetRole = $targetRoleStmt->fetchColumn();
        if ($targetRole === 'admin') {
            $total = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
            if ($total <= 1) adminJsonOut(['error' => 'No puedes eliminar al último administrador'], 422);
        }

        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        adminJsonOut(['ok' => true, 'users' => fetchUsers($pdo)]);
    }

    adminJsonOut(['error' => 'Acción no reconocida'], 400);
}

$users       = fetchUsers($pdo);
$currentUser = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$myId        = currentUserId();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administración — Mi Galería</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .admin-table-wrap { overflow-x:auto; margin-top:28px; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    thead th {
      text-align:left; padding:10px 14px;
      font-size:12px; font-weight:600; color:var(--text-2);
      text-transform:uppercase; letter-spacing:0.4px;
      border-bottom:1px solid rgba(0,0,0,0.07);
    }
    tbody tr { border-bottom:1px solid rgba(0,0,0,0.04); transition:background 0.15s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(0,0,0,0.015); }
    tbody td { padding:12px 14px; vertical-align:middle; }
    .role-badge {
      display:inline-block; padding:3px 10px; border-radius:100px; font-size:12px; font-weight:600;
    }
    .role-admin { background:rgba(0,113,227,0.1); color:var(--accent); }
    .role-user  { background:rgba(0,0,0,0.06); color:var(--text-2); }
    .td-actions { display:flex; gap:6px; }
    .admin-card { padding:28px 32px; }
    .admin-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; flex-wrap:wrap; gap:12px; }
    .form-select {
      width:100%; padding:12px 14px; border-radius:var(--radius-sm);
      border:1.5px solid rgba(0,0,0,0.1); background:rgba(255,255,255,0.8);
      font-family:var(--font); font-size:16px; color:var(--text);
      outline:none; cursor:pointer;
    }
    .form-select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
    .form-input  { user-select:text; -webkit-user-select:text; }
    .modal-error {
      display:none; background:rgba(255,59,48,0.08); border:1px solid rgba(255,59,48,0.25);
      border-radius:10px; padding:9px 13px; font-size:13px; color:var(--danger); margin-bottom:14px;
    }
  </style>
</head>
<body>

<header class="site-header">
  <a href="index.php" class="logo">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Mi Galería
  </a>
  <nav>
    <a href="index.php" class="nav-link">Álbumes</a>
    <a href="admin.php" class="nav-link" style="font-weight:700;color:var(--text)">Admin</a>
    <a href="logout.php" class="btn btn-ghost btn-sm">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <div class="admin-header-row">
    <div>
      <h1 class="page-title">Usuarios</h1>
      <p class="page-subtitle">Gestión de cuentas &mdash; sesión: <strong><?= $currentUser ?></strong></p>
    </div>
    <button class="btn btn-primary" onclick="openCreateModal()">+ Nuevo usuario</button>
  </div>

  <div class="glass admin-card">
    <div class="admin-table-wrap">
      <table id="usersTable">
        <thead>
          <tr>
            <th>#</th><th>Usuario</th><th>Rol</th><th>Límite&nbsp;MB</th>
            <th>Usado&nbsp;MB</th><th>Álbumes</th><th>Creado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody id="usersBody">
          <?php foreach ($users as $u): ?>
          <?php
            $isSelf    = (int)$u['id'] === $myId;
            $limitLbl  = $u['storage_limit_mb'] == 0 ? '∞' : (int)$u['storage_limit_mb'];
            $roleCls   = $u['role'] === 'admin' ? 'role-admin' : 'role-user';
            $created   = date('d M Y', strtotime($u['created_at']));
          ?>
          <tr data-id="<?= (int)$u['id'] ?>">
            <td><?= (int)$u['id'] ?></td>
            <td><strong><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td><span class="role-badge <?= $roleCls ?>"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= $limitLbl ?></td>
            <td><?= renderStorageCell((float)($u['used_mb'] ?? 0), (int)$u['storage_limit_mb']) ?></td>
            <td><?= (int)($u['album_count'] ?? 0) ?></td>
            <td><?= $created ?></td>
            <td><div class="td-actions">
              <button class="btn btn-ghost btn-sm btn-edit-user"
                      data-id="<?= (int)$u['id'] ?>"
                      data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>"
                      data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>"
                      data-limit="<?= (int)$u['storage_limit_mb'] ?>"
                      onclick="openEditModal(this)">Editar</button>
              <button class="btn btn-danger btn-sm btn-del-user"
                      data-id="<?= (int)$u['id'] ?>"
                      data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>"
                      onclick="deleteUser(this)"
                      <?= $isSelf ? 'disabled title="No puedes eliminarte a ti mismo"' : '' ?>>Eliminar</button>
            </div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="admin-header-row" style="margin-top:40px">
    <div>
      <h2 class="page-title" style="font-size:24px;margin-bottom:4px">Galería de contenido</h2>
      <p class="page-subtitle">Explora y modera las fotos de todos los usuarios</p>
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:240px">
      <select id="galleryUserFilter" class="form-select">
        <option value="0">— Todos los usuarios —</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="glass admin-card">
    <div id="adminGalleryGrid" class="admin-gallery-grid"></div>
  </div>
</main>

<!-- ---- User modal (create / edit) ---- -->
<div class="modal-overlay" id="userModal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
  <div class="modal-box">
    <h2 class="modal-title" id="userModalTitle">Nuevo usuario</h2>
    <div class="modal-error" id="modalError"></div>
    <form id="userForm" novalidate autocomplete="off">
      <input type="hidden" id="userId" value="">

      <div class="form-group">
        <label for="uUsername">Usuario</label>
        <input id="uUsername" class="form-input" type="text" maxlength="60" required autocomplete="off">
      </div>

      <div class="form-group">
        <label for="uPassword" id="uPasswordLabel">Contraseña</label>
        <input id="uPassword" class="form-input" type="password" autocomplete="new-password">
      </div>

      <div class="form-group">
        <label for="uRole">Rol</label>
        <select id="uRole" class="form-select">
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
      </div>

      <div class="form-group">
        <label for="uLimit">Límite de almacenamiento (MB, 0 = ilimitado)</label>
        <input id="uLimit" class="form-input" type="number" min="0" value="500">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeUserModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="userSubmitBtn">Crear</button>
      </div>
    </form>
  </div>
</div>

<!-- ---- Toasts ---- -->
<div class="toast-container" id="toastContainer"></div>

<!-- ---- Lightbox (admin gallery) ---- -->
<div class="lightbox-overlay" id="adminLightboxOverlay">
  <div class="lightbox-top-actions">
    <button class="btn-lb-action btn-lb-close" id="adminLightboxClose" aria-label="Cerrar">&#x2715;</button>
  </div>
  <div class="lightbox-inner">
    <img id="adminLightboxImg" src="" alt="">
    <div class="lightbox-title" id="adminLightboxTitle"></div>
  </div>
</div>

<footer class="site-footer-links">
  <a href="privacy.php">Política de privacidad</a>
  <a href="terms.php">Términos de uso</a>
</footer>

<script>
const MY_ID = <?= $myId ?>;

// ---- Re-render helpers ----
function renderRoleBadge(role) {
  const cls = role === 'admin' ? 'role-admin' : 'role-user';
  return `<span class="role-badge ${cls}">${escHtml(role)}</span>`;
}

function renderStorageCell(usedMb, limitMb) {
  const used = Number(usedMb || 0).toFixed(2);
  if (limitMb == 0) return `${used} MB / &#8734;`;
  const pct = Math.min(100, (Number(usedMb || 0) / limitMb) * 100);
  const cls = pct >= 90 ? ' danger' : '';
  return `${used} MB / ${limitMb} MB<div class="storage-bar"><div class="storage-bar-fill${cls}" style="width:${pct}%"></div></div>`;
}

function renderRow(u) {
  const limitLbl = u.storage_limit_mb == 0 ? '∞' : u.storage_limit_mb;
  const created  = new Date(u.created_at).toLocaleDateString('es-ES',{day:'2-digit',month:'short',year:'numeric'});
  const isSelf   = u.id == MY_ID;
  return `<tr data-id="${u.id}">
    <td>${u.id}</td>
    <td><strong>${escHtml(u.username)}</strong></td>
    <td>${renderRoleBadge(u.role)}</td>
    <td>${limitLbl}</td>
    <td>${renderStorageCell(u.used_mb, u.storage_limit_mb)}</td>
    <td>${u.album_count ?? 0}</td>
    <td>${created}</td>
    <td><div class="td-actions">
      <button class="btn btn-ghost btn-sm btn-edit-user"
              data-id="${u.id}"
              data-username="${escAttr(u.username)}"
              data-role="${escAttr(u.role)}"
              data-limit="${u.storage_limit_mb}"
              onclick="openEditModal(this)">Editar</button>
      <button class="btn btn-danger btn-sm btn-del-user"
              data-id="${u.id}"
              data-username="${escAttr(u.username)}"
              onclick="deleteUser(this)"
              ${isSelf ? 'disabled title="No puedes eliminarte a ti mismo"' : ''}>Eliminar</button>
    </div></td>
  </tr>`;
}

function setRows(users) {
  document.getElementById('usersBody').innerHTML = users.map(renderRow).join('');
}

function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function escAttr(s) { return escHtml(s); }

// ---- Modal ----
let editMode = false;

function openCreateModal() {
  editMode = false;
  document.getElementById('userModalTitle').textContent = 'Nuevo usuario';
  document.getElementById('userSubmitBtn').textContent  = 'Crear';
  document.getElementById('uPasswordLabel').textContent = 'Contraseña';
  document.getElementById('userId').value    = '';
  document.getElementById('uUsername').value = '';
  document.getElementById('uPassword').value = '';
  document.getElementById('uRole').value     = 'user';
  document.getElementById('uLimit').value    = 500;
  hideModalError();
  document.getElementById('userModal').classList.add('open');
  setTimeout(() => document.getElementById('uUsername').focus(), 80);
}

function openEditModal(btn) {
  editMode = true;
  const id    = btn.dataset.id;
  const uname = btn.dataset.username;
  const role  = btn.dataset.role;
  const limit = btn.dataset.limit;

  document.getElementById('userModalTitle').textContent = 'Editar usuario';
  document.getElementById('userSubmitBtn').textContent  = 'Guardar';
  document.getElementById('uPasswordLabel').textContent = 'Contraseña (dejar vacío para no cambiar)';
  document.getElementById('userId').value    = id;
  document.getElementById('uUsername').value = uname;
  document.getElementById('uPassword').value = '';
  document.getElementById('uRole').value     = role;
  document.getElementById('uLimit').value    = limit;
  hideModalError();
  document.getElementById('userModal').classList.add('open');
  setTimeout(() => document.getElementById('uUsername').focus(), 80);
}

function closeUserModal() {
  document.getElementById('userModal').classList.remove('open');
}
function showModalError(msg) {
  const el = document.getElementById('modalError');
  el.textContent = msg; el.style.display = 'block';
}
function hideModalError() {
  const el = document.getElementById('modalError');
  el.style.display = 'none'; el.textContent = '';
}

document.getElementById('userModal').addEventListener('click', function(e) {
  if (e.target === this) closeUserModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeUserModal(); });

// ---- Form submit ----
document.getElementById('userForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  hideModalError();

  const id       = document.getElementById('userId').value;
  const username = document.getElementById('uUsername').value.trim();
  const password = document.getElementById('uPassword').value;
  const role     = document.getElementById('uRole').value;
  const limitMb  = parseInt(document.getElementById('uLimit').value) || 0;
  const action   = editMode ? 'update' : 'create';

  const payload = { action, username, role, storage_limit_mb: limitMb };
  if (editMode) payload.id = parseInt(id);
  if (!editMode || password !== '') payload.password = password;

  const btn = document.getElementById('userSubmitBtn');
  btn.disabled = true;
  try {
    const res  = await fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok || data.error) { showModalError(data.error || 'Error desconocido'); return; }
    setRows(data.users);
    closeUserModal();
    showToast(editMode ? 'Usuario actualizado' : 'Usuario creado', 'success');
  } catch {
    showModalError('Error de red. Inténtalo de nuevo.');
  } finally {
    btn.disabled = false;
  }
});

// ---- Delete ----
async function deleteUser(btn) {
  const id       = parseInt(btn.dataset.id);
  const username = btn.dataset.username;
  if (!confirm(`¿Eliminar el usuario "${username}"?\n\nSe eliminarán también todos sus álbumes y fotos.`)) return;

  try {
    const res  = await fetch('admin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id }),
    });
    const data = await res.json();
    if (!res.ok || data.error) { showToast(data.error || 'Error', 'error'); return; }
    setRows(data.users);
    showToast('Usuario eliminado', 'success');
  } catch {
    showToast('Error de red', 'error');
  }
}

// ---- Toast ----
function showToast(msg, type = 'success') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className   = `toast ${type}`;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => { t.classList.add('hiding'); setTimeout(() => t.remove(), 280); }, 3000);
}

// ---- Content gallery ----
function formatBytes(bytes) {
  bytes = Number(bytes) || 0;
  if (bytes === 0) return '–';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

async function loadAdminGallery(userId) {
  const grid = document.getElementById('adminGalleryGrid');
  grid.innerHTML = '<p style="grid-column:1/-1;color:var(--text-2);padding:20px 0">Cargando…</p>';
  try {
    const res    = await fetch(`api/admin_gallery.php?user_id=${userId}`);
    const albums = await res.json();
    if (!res.ok) throw new Error(albums.error || 'Error al cargar la galería');
    renderAdminGallery(albums);
  } catch (e) {
    grid.innerHTML = `<p style="grid-column:1/-1;color:var(--danger);padding:20px 0">${escHtml(e.message)}</p>`;
  }
}

function renderAdminGallery(albums) {
  const grid = document.getElementById('adminGalleryGrid');
  grid.innerHTML = '';
  let count = 0;

  albums.forEach(album => {
    (album.photos || []).forEach(photo => {
      count++;
      const thumb = document.createElement('div');
      thumb.className = 'admin-thumb';
      thumb.innerHTML = `
        <img src="${escAttr(photo.url)}" alt="" loading="lazy">
        <div class="admin-thumb-meta">👤 ${escHtml(album.username)} · 📁 ${escHtml(album.name)} · 📦 ${formatBytes(photo.file_size)}</div>
        <button class="thumb-delete" type="button" title="Eliminar foto">🗑</button>
      `;
      thumb.addEventListener('click', e => {
        if (e.target.closest('.thumb-delete')) return;
        openAdminLightbox(photo, album);
      });
      thumb.querySelector('.thumb-delete').addEventListener('click', async e => {
        e.stopPropagation();
        if (!confirm('¿Eliminar esta foto?')) return;
        try {
          const res  = await fetch(`api/photos.php?id=${photo.id}`, { method: 'DELETE' });
          const data = await res.json();
          if (!res.ok || data.error) { showToast(data.error || 'Error al eliminar', 'error'); return; }
          thumb.remove();
          showToast('Foto eliminada');
        } catch {
          showToast('Error de red', 'error');
        }
      });
      grid.appendChild(thumb);
    });
  });

  if (count === 0) {
    grid.innerHTML = '<p style="grid-column:1/-1;color:var(--text-2);padding:20px 0">No hay fotos para mostrar</p>';
  }
}

// ---- Admin lightbox ----
function openAdminLightbox(photo, album) {
  document.getElementById('adminLightboxImg').src           = photo.url;
  document.getElementById('adminLightboxImg').alt           = photo.title || '';
  document.getElementById('adminLightboxTitle').textContent = photo.title || `${album.username} — ${album.name}`;
  document.getElementById('adminLightboxOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeAdminLightbox() {
  document.getElementById('adminLightboxOverlay').classList.remove('open');
  document.body.style.overflow = '';
  setTimeout(() => { document.getElementById('adminLightboxImg').src = ''; }, 350);
}
document.getElementById('adminLightboxClose').addEventListener('click', closeAdminLightbox);
document.getElementById('adminLightboxOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAdminLightbox();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAdminLightbox(); });

// ---- Gallery filter + initial load ----
document.getElementById('galleryUserFilter').addEventListener('change', e => {
  loadAdminGallery(parseInt(e.target.value, 10) || 0);
});
loadAdminGallery(0);
</script>

</body>
</html>
