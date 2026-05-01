/* ============================================================
   albums.js — Lógica de la vista principal (index.php)
   ============================================================ */

'use strict';

const SVG = {
  trash:    `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>`,
  download: `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`,
};

function showToast(msg, type = 'success') {
  const container = document.getElementById('toastContainer');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  container.appendChild(el);
  setTimeout(() => {
    el.classList.add('hiding');
    el.addEventListener('animationend', () => el.remove());
  }, 3200);
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ---- State ----
const COLORS = ['#0071e3','#ff6b6b','#34c759','#ff9f0a','#bf5af2','#ff375f','#32ade6','#30b0c7','#a2845e','#6c6c70'];
let selectedColor = '#0071e3';
let albums = [];

// ---- DOM refs ----
const grid      = document.getElementById('albumsGrid');
const modal     = document.getElementById('albumModal');
const modalForm = document.getElementById('albumForm');
const nameInput = document.getElementById('albumName');
const colorRow  = document.getElementById('colorRow');

// ---- Render albums ----
function renderAlbums() {
  grid.querySelectorAll('.album-card, .empty-placeholder').forEach(c => c.remove());

  if (albums.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'empty-placeholder';
    empty.style.cssText = 'grid-column:1/-1;text-align:center;padding:40px 0;color:var(--text-2);font-size:14px;';
    empty.textContent = 'Aún no hay álbumes. Crea el primero.';
    grid.insertBefore(empty, grid.firstChild);
    return;
  }

  const newBtn = grid.querySelector('.album-new-card');
  albums.forEach(album => {
    const a = document.createElement('a');
    a.className = 'album-card';
    a.href = `gallery.php?album=${album.id}`;
    a.style.setProperty('--card-color', album.color);
    a.innerHTML = `
      <div class="album-color-dot" style="background:${escHtml(album.color)}"></div>
      <div class="album-name">${escHtml(album.name)}</div>
      <div class="album-count">${album.photo_count} ${album.photo_count === 1 ? 'foto' : 'fotos'}</div>
      <div class="album-actions">
        <button class="btn-album-action btn-album-dl" data-id="${album.id}" title="Descargar todo">
          ${SVG.download} Descargar
        </button>
        <button class="btn-album-action btn-album-del" data-id="${album.id}" title="Eliminar álbum">
          ${SVG.trash} Eliminar
        </button>
      </div>
    `;

    a.querySelector('.btn-album-dl').addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      downloadAlbum(album.id, album.name);
    });
    a.querySelector('.btn-album-del').addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      deleteAlbum(album.id, album.name);
    });

    grid.insertBefore(a, newBtn);
  });
}

// ---- Load albums ----
async function loadAlbums() {
  try {
    const res = await fetch('api/albums.php');
    if (!res.ok) throw new Error('Error al cargar álbumes');
    albums = await res.json();
    renderAlbums();
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Delete album ----
async function deleteAlbum(id, name) {
  if (!confirm(`¿Eliminar el álbum "${name}" y todas sus fotos?`)) return;
  try {
    const res  = await fetch(`api/albums.php?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Error al eliminar');
    showToast('Álbum eliminado');
    await loadAlbums();
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Download album ZIP ----
async function downloadAlbum(id, name) {
  showToast('Preparando descarga…');
  try {
    const res = await fetch(`api/photos.php?action=download_zip&album_id=${id}`);
    const ct  = res.headers.get('Content-Type') ?? '';

    if (ct.includes('application/zip')) {
      const blob = await res.blob();
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href     = url;
      a.download = name.replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '_') + '.zip';
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 2000);
      showToast('Descarga iniciada');
    } else {
      // Fallback: ZipArchive no disponible — descarga fotos individuales
      const data = await res.json();
      if (data.fallback && Array.isArray(data.photos) && data.photos.length > 0) {
        data.photos.forEach((p, i) => {
          setTimeout(() => {
            const a    = document.createElement('a');
            a.href     = p.url;
            a.download = (p.title || `foto_${i + 1}`).replace(/[^\w\s-]/g,'').trim() || `foto_${i + 1}`;
            a.target   = '_blank';
            document.body.appendChild(a);
            a.click();
            a.remove();
          }, i * 300);
        });
        showToast(`Descargando ${data.photos.length} foto(s)…`);
      } else {
        showToast('No hay fotos para descargar', 'error');
      }
    }
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Modal ----
function openModal() {
  selectedColor = '#0071e3';
  nameInput.value = '';
  renderColorRow();
  modal.classList.add('open');
  requestAnimationFrame(() => nameInput.focus());
}

function closeModal() { modal.classList.remove('open'); }

function renderColorRow() {
  colorRow.innerHTML = '';
  COLORS.forEach(c => {
    const btn = document.createElement('button');
    btn.className = 'color-opt' + (c === selectedColor ? ' active' : '');
    btn.type = 'button';
    btn.style.background = c;
    btn.title = c;
    btn.addEventListener('click', () => {
      selectedColor = c;
      colorRow.querySelectorAll('.color-opt').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
    colorRow.appendChild(btn);
  });
}

// ---- Create album ----
modalForm.addEventListener('submit', async e => {
  e.preventDefault();
  const name = nameInput.value.trim();
  if (!name) { nameInput.focus(); return; }

  const submitBtn = modalForm.querySelector('[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Guardando…';

  try {
    const res = await fetch('api/albums.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, emoji: '', color: selectedColor }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Error al crear');
    closeModal();
    showToast(`Álbum "${data.name}" creado`);
    await loadAlbums();
  } catch(e) {
    showToast(e.message, 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Crear álbum';
  }
});

modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

document.addEventListener('DOMContentLoaded', loadAlbums);
