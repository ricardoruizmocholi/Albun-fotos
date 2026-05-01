/* ============================================================
   gallery3d.js — Motor 3D + lógica de galería de fotos
   ============================================================ */

'use strict';

const SVG = {
  trash:    `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>`,
  download: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`,
  camera:   `<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>`,
  folder:   `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>`,
  search:   `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>`,
};

function showToast(msg, type = 'success') {
  const c = document.getElementById('toastContainer');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  c.appendChild(el);
  setTimeout(() => {
    el.classList.add('hiding');
    el.addEventListener('animationend', () => el.remove());
  }, 3200);
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ---- 3D Engine ----
const scene = {
  el:      null,
  wrapper: null,
  panX: 0, panY: 0, tPanX: 0, tPanY: 0,
  rotX: 0, rotY: 0, tRotX: 0, tRotY: 0,
  zoom: 0, tZoom: 0,
  mouse: { down: false, lastX: 0, lastY: 0 },
  LERP:     0.06,
  ZOOM_MIN: -1800,
  ZOOM_MAX:  3000,
};

function sceneInit() {
  scene.el      = document.getElementById('scene3d');
  scene.wrapper = document.getElementById('sceneWrapper');
  if (!scene.el || !scene.wrapper) return;
  bindMouseEvents();
  bindTouchEvents();
  requestAnimationFrame(animLoop);
}

function animLoop() {
  const s = scene;
  s.panX += (s.tPanX - s.panX) * s.LERP;
  s.panY += (s.tPanY - s.panY) * s.LERP;
  s.rotX += (s.tRotX - s.rotX) * s.LERP;
  s.rotY += (s.tRotY - s.rotY) * s.LERP;
  s.zoom += (s.tZoom - s.zoom) * s.LERP;
  if (s.el) {
    s.el.style.transform =
      `translate3d(${s.panX}px,${s.panY}px,${s.zoom}px) rotateX(${s.rotX}deg) rotateY(${s.rotY}deg)`;
  }
  requestAnimationFrame(animLoop);
}

function bindMouseEvents() {
  const w = scene.wrapper;
  w.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    scene.mouse.down  = true;
    scene.mouse.lastX = e.clientX;
    scene.mouse.lastY = e.clientY;
  });
  window.addEventListener('mousemove', e => {
    if (!scene.mouse.down) return;
    scene.tPanX += e.clientX - scene.mouse.lastX;
    scene.tPanY += e.clientY - scene.mouse.lastY;
    scene.mouse.lastX = e.clientX;
    scene.mouse.lastY = e.clientY;
  });
  window.addEventListener('mouseup',    () => { scene.mouse.down = false; });
  window.addEventListener('mouseleave', () => { scene.mouse.down = false; });
  w.addEventListener('wheel', e => {
    e.preventDefault();
    scene.tZoom = Math.min(scene.ZOOM_MAX, Math.max(scene.ZOOM_MIN, scene.tZoom + e.deltaY * -1.8));
  }, { passive: false });
}

function bindTouchEvents() {
  const w = scene.wrapper;
  let lastTX = 0, lastTY = 0, pinchDist = 0;
  w.addEventListener('touchstart', e => {
    if (e.touches.length === 1) { lastTX = e.touches[0].clientX; lastTY = e.touches[0].clientY; }
    else if (e.touches.length === 2) {
      pinchDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
    }
  }, { passive: true });
  w.addEventListener('touchmove', e => {
    e.preventDefault();
    if (e.touches.length === 1) {
      scene.tPanX += e.touches[0].clientX - lastTX;
      scene.tPanY += e.touches[0].clientY - lastTY;
      lastTX = e.touches[0].clientX;
      lastTY = e.touches[0].clientY;
    } else if (e.touches.length === 2) {
      const d = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      scene.tZoom = Math.min(scene.ZOOM_MAX, Math.max(scene.ZOOM_MIN, scene.tZoom + (d - pinchDist) * 3));
      pinchDist = d;
    }
  }, { passive: false });
}

// ---- Photos ----
let photos = [];

async function loadPhotos(albumId) {
  const wrapper = document.getElementById('sceneWrapper');
  wrapper.innerHTML = `<div class="empty-state"><div class="spinner"></div></div>`;

  try {
    const res = await fetch(`api/photos.php?album_id=${albumId}`);
    if (!res.ok) throw new Error('Error al cargar fotos');
    photos = await res.json();

    wrapper.innerHTML = `
      <div class="scene-perspective">
        <div class="scene-3d" id="scene3d"></div>
      </div>
    `;

    if (photos.length === 0) {
      wrapper.querySelector('.scene-3d').innerHTML = `
        <div class="empty-state">
          <div class="empty-icon" style="color:var(--text-2);opacity:0.45">${SVG.camera}</div>
          <h3>Sin fotos aún</h3>
          <p>Sube la primera foto con el botón de arriba</p>
        </div>`;
    } else {
      photos.forEach(p => renderPhotoCard(p));
    }

    sceneInit();
    initSearch();
    updatePhotoCount();
  } catch(e) {
    wrapper.innerHTML = `<div class="empty-state"><p style="color:var(--danger)">${escHtml(e.message)}</p></div>`;
  }
}

function renderPhotoCard(photo) {
  const scene3d = document.getElementById('scene3d');
  const card = document.createElement('div');
  card.className      = 'photo-card';
  card.dataset.id     = photo.id;
  card.dataset.tags   = photo.tags  || '';
  card.dataset.title  = photo.title || '';
  card.style.transform =
    `translate3d(${photo.pos_x}px,${photo.pos_y}px,${photo.pos_z}px) ` +
    `rotateX(${photo.rot_x}deg) rotateY(${photo.rot_y}deg) rotateZ(${photo.rot_z}deg)`;

  card.innerHTML = `
    <img src="${escHtml(photo.url)}" alt="${escHtml(photo.title)}" loading="lazy" draggable="false">
    <div class="photo-overlay">
      <span class="photo-overlay-title">${escHtml(photo.title || 'Sin título')}</span>
      <span class="photo-overlay-hint">Clic para ampliar</span>
    </div>
    <button class="photo-delete-btn" title="Borrar foto">${SVG.trash}</button>
  `;

  card.querySelector('.photo-delete-btn').addEventListener('click', e => {
    e.stopPropagation();
    deletePhoto(photo.id);
  });
  card.addEventListener('click', e => {
    if (e.target.closest('.photo-delete-btn')) return;
    openLightbox(photo);
  });

  scene3d.appendChild(card);
}

function updatePhotoCount() {
  const el = document.getElementById('photoCount');
  if (el) el.textContent = `${photos.length} ${photos.length === 1 ? 'foto' : 'fotos'}`;
}

async function deletePhoto(id) {
  if (!confirm('¿Eliminar esta foto?')) return;
  try {
    const res  = await fetch(`api/photos.php?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Error al eliminar');
    showToast('Foto eliminada');
    document.querySelector(`.photo-card[data-id="${id}"]`)?.remove();
    photos = photos.filter(p => p.id !== id);
    updatePhotoCount();
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Search / filter ----
let searchTimer = null;

function initSearch() {
  const input = document.getElementById('searchInput');
  if (!input) return;
  input.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => filterPhotos(input.value.trim()), 250);
  });
}

function filterPhotos(query) {
  const cards = document.querySelectorAll('.photo-card');
  const badge = document.getElementById('searchBadge');
  const q     = query.toLowerCase();
  let count   = 0;

  cards.forEach(card => {
    const tags  = (card.dataset.tags  || '').toLowerCase();
    const title = (card.dataset.title || '').toLowerCase();
    const match = !q || tags.includes(q) || title.includes(q);
    card.style.opacity       = match ? '1'    : '0.15';
    card.style.pointerEvents = match ? 'auto' : 'none';
    if (match) count++;
  });

  if (badge) {
    if (q) {
      badge.textContent   = `${count} foto${count !== 1 ? 's' : ''} encontrada${count !== 1 ? 's' : ''}`;
      badge.style.display = 'block';
    } else {
      badge.style.display = 'none';
    }
  }
}

// ---- Lightbox ----
const lb = {
  overlay: null, img: null, title: null, tagsEl: null,
  currentUrl: '', currentTitle: '',
};

function initLightbox() {
  lb.overlay = document.getElementById('lightboxOverlay');
  lb.img     = document.getElementById('lightboxImg');
  lb.title   = document.getElementById('lightboxTitle');
  lb.tagsEl  = document.getElementById('lightboxTags');

  document.getElementById('lightboxClose').addEventListener('click', closeLightbox);

  document.getElementById('lightboxDownload').addEventListener('click', () => {
    const a = document.createElement('a');
    a.href     = lb.currentUrl;
    a.download = (lb.currentTitle.replace(/[^\w\s-]/g,'').trim() || 'foto').replace(/\s+/g,'_');
    document.body.appendChild(a);
    a.click();
    a.remove();
  });

  lb.overlay.addEventListener('click', e => { if (e.target === lb.overlay) closeLightbox(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
}

function openLightbox(photo) {
  lb.img.src         = photo.url;
  lb.img.alt         = photo.title || '';
  lb.title.textContent = photo.title || '';
  lb.currentUrl      = photo.url;
  lb.currentTitle    = photo.title || 'foto';

  if (lb.tagsEl) {
    lb.tagsEl.innerHTML = '';
    const tags = (photo.tags || '').split(',').map(t => t.trim()).filter(Boolean);
    tags.forEach(tag => {
      const pill = document.createElement('span');
      pill.className   = 'lb-tag-pill';
      pill.textContent = tag;
      lb.tagsEl.appendChild(pill);
    });
    lb.tagsEl.style.display = tags.length ? 'flex' : 'none';
  }

  lb.overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lb.overlay.classList.remove('open');
  document.body.style.overflow = '';
  setTimeout(() => { lb.img.src = ''; }, 350);
}

// ---- Upload modal ----
function initUploadModal() {
  const modal     = document.getElementById('uploadModal');
  const form      = document.getElementById('uploadForm');
  const dropzone  = document.getElementById('dropzone');
  const fileInput = document.getElementById('photoFile');
  const urlInput  = document.getElementById('photoUrl');
  const preview   = document.getElementById('uploadPreview');

  // Inject SVG folder icon into dropzone
  const dropIcon = dropzone.querySelector('.drop-icon');
  if (dropIcon) dropIcon.innerHTML = SVG.folder;

  document.getElementById('uploadBtn').addEventListener('click', () => {
    modal.classList.add('open');
    form.reset();
    preview.innerHTML = '';
    preview.style.display = 'none';
    const p = dropzone.querySelector('p');
    if (p) p.textContent = 'Arrastra aquí o haz clic para seleccionar';
  });

  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
  document.getElementById('cancelUpload').addEventListener('click', () => modal.classList.remove('open'));

  dropzone.addEventListener('click',    () => fileInput.click());
  dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
  dropzone.addEventListener('drop', e => {
    e.preventDefault(); dropzone.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
  });
  fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

  urlInput.addEventListener('input', () => {
    const url = urlInput.value.trim();
    if (url) {
      preview.style.display = 'block';
      preview.innerHTML = `<img src="${escHtml(url)}" style="max-width:100%;border-radius:10px;max-height:160px;object-fit:cover;" onerror="this.style.display='none'">`;
    } else { preview.innerHTML = ''; preview.style.display = 'none'; }
  });

  function setFile(file) {
    const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files;
    const reader = new FileReader();
    reader.onload = ev => {
      preview.style.display = 'block';
      preview.innerHTML = `<img src="${ev.target.result}" style="max-width:100%;border-radius:10px;max-height:160px;object-fit:cover;">`;
    };
    reader.readAsDataURL(file);
    const p = dropzone.querySelector('p');
    if (p) p.textContent = file.name;
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const albumId = form.dataset.albumId;
    const title   = document.getElementById('photoTitle').value.trim();
    const rawTags = document.getElementById('photoTags').value.trim();
    const tags    = [...new Set(rawTags.toLowerCase().split(',').map(t => t.trim()).filter(Boolean))].join(',');
    const submit  = form.querySelector('[type="submit"]');
    submit.disabled = true; submit.textContent = 'Subiendo…';

    try {
      const fd = new FormData();
      fd.append('album_id', albumId);
      fd.append('title', title);
      fd.append('tags', tags);

      if (fileInput.files[0]) {
        fd.append('photo', fileInput.files[0]);
      } else if (urlInput.value.trim()) {
        fd.append('url', urlInput.value.trim());
      } else {
        throw new Error('Selecciona un archivo o escribe una URL');
      }

      const res  = await fetch('api/photos.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? 'Error al subir');

      modal.classList.remove('open');
      showToast('Foto agregada');
      photos.push(data);
      renderPhotoCard(data);
      updatePhotoCount();
    } catch(e) {
      showToast(e.message, 'error');
    } finally {
      submit.disabled = false; submit.textContent = 'Subir foto';
    }
  });
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
  const params  = new URLSearchParams(location.search);
  const albumId = parseInt(params.get('album') ?? '0', 10);
  if (!albumId) { location.href = 'index.php'; return; }

  document.getElementById('uploadForm').dataset.albumId = albumId;
  initLightbox();
  initUploadModal();
  loadPhotos(albumId);
});
