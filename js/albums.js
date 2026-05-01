/* ============================================================
   albums.js — Lógica de la vista principal (index.php)
   ============================================================ */

'use strict';

// ---- Toast helper ----
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

// ---- State ----
const EMOJIS   = ['📷','🎂','🎉','🏖️','🌸','✈️','🎵','🍕','🌍','🐶','🦋','🌅','🏔️','🎨','🎮','📚','🌺','🏠','🌙','⭐','🎃','🎄','🌊','🍦','🎪','🦄','🌈','🎁'];
const COLORS   = ['#0071e3','#ff6b6b','#34c759','#ff9f0a','#bf5af2','#ff375f','#32ade6','#30b0c7','#a2845e','#6c6c70'];
let selectedEmoji = '📷';
let selectedColor = '#0071e3';
let albums        = [];

// ---- DOM refs ----
const grid      = document.getElementById('albumsGrid');
const modal     = document.getElementById('albumModal');
const modalForm = document.getElementById('albumForm');
const nameInput = document.getElementById('albumName');
const emojiGrid = document.getElementById('emojiGrid');
const colorRow  = document.getElementById('colorRow');

// ---- Render albums ----
function renderAlbums() {
  // Remove existing album cards (keep new-card button)
  grid.querySelectorAll('.album-card').forEach(c => c.remove());

  if (albums.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'album-card empty-placeholder';
    empty.style.cssText = 'opacity:0.5;pointer-events:none;background:transparent;border:none;box-shadow:none;';
    empty.innerHTML = '<span style="color:var(--text-2);font-size:14px;">Aún no hay álbumes. ¡Crea el primero!</span>';
    grid.insertBefore(empty, grid.firstChild);
    return;
  }

  const newBtn = grid.querySelector('.album-new-card');
  albums.forEach(album => {
    const a = document.createElement('a');
    a.className  = 'album-card';
    a.href       = `gallery.php?album=${album.id}`;
    a.style.setProperty('--card-color', album.color);
    a.innerHTML  = `
      <span class="album-emoji">${album.emoji}</span>
      <div class="album-name">${escHtml(album.name)}</div>
      <div class="album-count">${album.photo_count} ${album.photo_count === 1 ? 'foto' : 'fotos'}</div>
      <button class="btn btn-danger btn-icon btn-sm card-delete" data-id="${album.id}" title="Eliminar álbum">🗑</button>
    `;

    a.querySelector('.card-delete').addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      deleteAlbum(album.id, album.name);
    });

    grid.insertBefore(a, newBtn);
  });
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
    const res = await fetch(`api/albums.php?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Error al eliminar');
    showToast('Álbum eliminado');
    await loadAlbums();
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Modal ----
function openModal() {
  selectedEmoji = '📷';
  selectedColor = '#0071e3';
  nameInput.value = '';
  renderEmojiGrid();
  renderColorRow();
  modal.classList.add('open');
  requestAnimationFrame(() => nameInput.focus());
}

function closeModal() { modal.classList.remove('open'); }

function renderEmojiGrid() {
  emojiGrid.innerHTML = '';
  EMOJIS.forEach(em => {
    const btn = document.createElement('button');
    btn.className = 'emoji-opt' + (em === selectedEmoji ? ' active' : '');
    btn.type      = 'button';
    btn.textContent = em;
    btn.addEventListener('click', () => {
      selectedEmoji = em;
      emojiGrid.querySelectorAll('.emoji-opt').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
    emojiGrid.appendChild(btn);
  });
}

function renderColorRow() {
  colorRow.innerHTML = '';
  COLORS.forEach(c => {
    const btn = document.createElement('button');
    btn.className = 'color-opt' + (c === selectedColor ? ' active' : '');
    btn.type      = 'button';
    btn.style.background = c;
    btn.title     = c;
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
      body: JSON.stringify({ name, emoji: selectedEmoji, color: selectedColor })
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

// ---- Click outside modal closes it ----
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ---- Init ----
document.addEventListener('DOMContentLoaded', loadAlbums);
