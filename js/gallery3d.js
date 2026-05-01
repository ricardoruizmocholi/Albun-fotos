/* ============================================================
   gallery3d.js — Motor 3D + lógica de galería de fotos
   ============================================================ */

'use strict';

// ---- Toast ----
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
  el:       null,   // .scene-3d
  wrapper:  null,   // .scene-wrapper
  rotX:     0, rotY: 0,   // current rotation (mouse pan)
  tRotX:    0, tRotY: 0,  // target rotation
  panX:     0, panY: 0,   // panning offset
  tPanX:    0, tPanY: 0,
  zoom:     0, tZoom: 0,  // translateZ (wheel)
  mouse:    { x: 0, y: 0, down: false, lastX: 0, lastY: 0 },
  raf:      null,
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
  s.rotX  += (s.tRotX  - s.rotX)  * s.LERP;
  s.rotY  += (s.tRotY  - s.rotY)  * s.LERP;
  s.panX  += (s.tPanX  - s.panX)  * s.LERP;
  s.panY  += (s.tPanY  - s.panY)  * s.LERP;
  s.zoom  += (s.tZoom  - s.zoom)  * s.LERP;

  s.el.style.transform =
    `translate3d(${s.panX}px, ${s.panY}px, ${s.zoom}px) ` +
    `rotateX(${s.rotX}deg) rotateY(${s.rotY}deg)`;

  s.raf = requestAnimationFrame(animLoop);
}

// ---- Mouse navigation ----
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
    const dx = e.clientX - scene.mouse.lastX;
    const dy = e.clientY - scene.mouse.lastY;
    scene.mouse.lastX = e.clientX;
    scene.mouse.lastY = e.clientY;
    scene.tPanX += dx;
    scene.tPanY += dy;
  });

  window.addEventListener('mouseup',   () => { scene.mouse.down = false; });
  window.addEventListener('mouseleave', () => { scene.mouse.down = false; });

  w.addEventListener('wheel', e => {
    e.preventDefault();
    scene.tZoom = Math.min(scene.ZOOM_MAX, Math.max(scene.ZOOM_MIN, scene.tZoom + e.deltaY * -1.8));
  }, { passive: false });
}

// ---- Touch navigation ----
function bindTouchEvents() {
  const w = scene.wrapper;
  let lastTX = 0, lastTY = 0;
  let pinchDist = 0;

  w.addEventListener('touchstart', e => {
    if (e.touches.length === 1) {
      lastTX = e.touches[0].clientX;
      lastTY = e.touches[0].clientY;
    } else if (e.touches.length === 2) {
      pinchDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
    }
  }, { passive: true });

  w.addEventListener('touchmove', e => {
    e.preventDefault();
    if (e.touches.length === 1) {
      const dx = e.touches[0].clientX - lastTX;
      const dy = e.touches[0].clientY - lastTY;
      lastTX = e.touches[0].clientX;
      lastTY = e.touches[0].clientY;
      scene.tPanX += dx;
      scene.tPanY += dy;
    } else if (e.touches.length === 2) {
      const d = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      const delta = (d - pinchDist) * 3;
      pinchDist = d;
      scene.tZoom = Math.min(scene.ZOOM_MAX, Math.max(scene.ZOOM_MIN, scene.tZoom + delta));
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

    // Re-build scene
    wrapper.innerHTML = `
      <div class="scene-perspective">
        <div class="scene-3d" id="scene3d"></div>
      </div>
    `;

    if (photos.length === 0) {
      wrapper.querySelector('.scene-3d').innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">📷</div>
          <h3>Sin fotos aún</h3>
          <p>Sube la primera foto con el botón de arriba</p>
        </div>`;
    } else {
      photos.forEach(p => renderPhotoCard(p));
    }

    sceneInit();
    updatePhotoCount();
  } catch(e) {
    wrapper.innerHTML = `<div class="empty-state"><p style="color:var(--danger)">${e.message}</p></div>`;
  }
}

function renderPhotoCard(photo) {
  const scene3d = document.getElementById('scene3d');
  const card = document.createElement('div');
  card.className = 'photo-card';
  card.dataset.id = photo.id;
  card.style.transform =
    `translate3d(${photo.pos_x}px, ${photo.pos_y}px, ${photo.pos_z}px) ` +
    `rotateX(${photo.rot_x}deg) rotateY(${photo.rot_y}deg) rotateZ(${photo.rot_z}deg)`;

  const src = photo.url.startsWith('http') ? photo.url : photo.url;
  card.innerHTML = `
    <img src="${escHtml(src)}" alt="${escHtml(photo.title)}" loading="lazy" draggable="false">
    <div class="photo-overlay">
      <span class="photo-overlay-title">${escHtml(photo.title || 'Sin título')}</span>
      <span class="photo-overlay-hint">Clic para ampliar</span>
    </div>
    <button class="photo-delete-btn" title="Borrar foto">🗑</button>
  `;

  card.querySelector('.photo-delete-btn').addEventListener('click', e => {
    e.stopPropagation();
    deletePhoto(photo.id);
  });

  card.addEventListener('click', e => {
    if (e.target.classList.contains('photo-delete-btn')) return;
    openLightbox(photo);
  });

  scene3d.appendChild(card);
}

function updatePhotoCount() {
  const el = document.getElementById('photoCount');
  if (el) el.textContent = `${photos.length} ${photos.length === 1 ? 'foto' : 'fotos'}`;
}

// ---- Delete photo ----
async function deletePhoto(id) {
  if (!confirm('¿Eliminar esta foto?')) return;
  try {
    const res = await fetch(`api/photos.php?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Error al eliminar');
    showToast('Foto eliminada');
    const card = document.querySelector(`.photo-card[data-id="${id}"]`);
    if (card) card.remove();
    photos = photos.filter(p => p.id !== id);
    updatePhotoCount();
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Lightbox ----
const lb = {
  overlay: null,
  img:     null,
  title:   null,
};

function initLightbox() {
  lb.overlay = document.getElementById('lightboxOverlay');
  lb.img     = document.getElementById('lightboxImg');
  lb.title   = document.getElementById('lightboxTitle');

  document.getElementById('lightboxClose').addEventListener('click', closeLightbox);
  lb.overlay.addEventListener('click', e => { if (e.target === lb.overlay) closeLightbox(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
}

function openLightbox(photo) {
  const src = photo.url.startsWith('http') ? photo.url : photo.url;
  lb.img.src      = src;
  lb.img.alt      = photo.title || '';
  lb.title.textContent = photo.title || '';
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

  document.getElementById('uploadBtn').addEventListener('click', () => {
    modal.classList.add('open');
    form.reset();
    preview.innerHTML = '';
    preview.style.display = 'none';
  });

  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
  document.getElementById('cancelUpload').addEventListener('click', () => modal.classList.remove('open'));

  // Dropzone
  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
  dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) setFile(file);
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) setFile(fileInput.files[0]);
  });

  urlInput.addEventListener('input', () => {
    const url = urlInput.value.trim();
    if (url) {
      preview.style.display = 'block';
      preview.innerHTML = `<img src="${escHtml(url)}" style="max-width:100%;border-radius:10px;max-height:160px;object-fit:cover;" onerror="this.style.display='none'">`;
    } else {
      preview.innerHTML = ''; preview.style.display = 'none';
    }
  });

  function setFile(file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    const reader = new FileReader();
    reader.onload = ev => {
      preview.style.display = 'block';
      preview.innerHTML = `<img src="${ev.target.result}" style="max-width:100%;border-radius:10px;max-height:160px;object-fit:cover;">`;
    };
    reader.readAsDataURL(file);
    dropzone.querySelector('p').textContent = file.name;
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const albumId = form.dataset.albumId;
    const title   = document.getElementById('photoTitle').value.trim();
    const submit  = form.querySelector('[type="submit"]');

    submit.disabled = true; submit.textContent = 'Subiendo…';

    try {
      const fd = new FormData();
      fd.append('album_id', albumId);
      fd.append('title', title);

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
