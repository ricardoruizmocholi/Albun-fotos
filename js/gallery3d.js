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
  el:   null,
  tPanX: 0, tPanY: 0, tZoom: 0,
  mouse: { down: false, lastX: 0, lastY: 0 },
  LERP: 0.06,
};
const camPos = { x: 0, y: 0, z: 0 };
let cameraDirty = false;

function sceneInit() {
  scene.el = document.getElementById('world');
  if (!scene.el) return;
  bindMouseEvents();
  bindTouchEvents();
  requestAnimationFrame(animLoop);
}

function applyWorldTransform() {
  const clampedZ = camPos.z < -6000 ? -6000 : camPos.z;
  const scale    = camPos.z < -6000 ? Math.max(0.08, 1 + (camPos.z + 6000) / 6000) : 1;
  scene.el.style.transform = scale < 1
    ? `translate3d(${camPos.x}px,${camPos.y}px,${clampedZ}px) scale(${scale})`
    : `translate3d(${camPos.x}px,${camPos.y}px,${camPos.z}px)`;
}

function animLoop() {
  if (cameraDirty && scene.el) {
    camPos.x += (scene.tPanX - camPos.x) * scene.LERP;
    camPos.y += (scene.tPanY - camPos.y) * scene.LERP;
    camPos.z += (scene.tZoom  - camPos.z) * scene.LERP;
    applyWorldTransform();
    if (Math.abs(scene.tPanX - camPos.x) < 0.05 &&
        Math.abs(scene.tPanY - camPos.y) < 0.05 &&
        Math.abs(scene.tZoom  - camPos.z) < 0.05) {
      camPos.x = scene.tPanX; camPos.y = scene.tPanY; camPos.z = scene.tZoom;
      applyWorldTransform();
      cameraDirty = false;
    }
  }
  requestAnimationFrame(animLoop);
}

function bindMouseEvents() {
  document.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    if (e.target.closest('.photo-card') || e.target.closest('.modal-overlay') || e.target.closest('.lightbox-overlay')) return;
    scene.mouse.down  = true;
    scene.mouse.lastX = e.clientX;
    scene.mouse.lastY = e.clientY;
    document.body.style.cursor = 'grabbing';
  });
  window.addEventListener('mousemove', e => {
    if (!scene.mouse.down) return;
    scene.tPanX += e.clientX - scene.mouse.lastX;
    scene.tPanY += e.clientY - scene.mouse.lastY;
    scene.mouse.lastX = e.clientX;
    scene.mouse.lastY = e.clientY;
    cameraDirty = true;
  });
  window.addEventListener('mouseup', () => {
    if (scene.mouse.down) document.body.style.cursor = '';
    scene.mouse.down = false;
  });
  window.addEventListener('mouseleave', () => { scene.mouse.down = false; document.body.style.cursor = ''; });
  document.addEventListener('wheel', e => {
    if (e.target.closest('.modal-overlay') || e.target.closest('.lightbox-overlay')) return;
    e.preventDefault();
    scene.tZoom += e.deltaY * -1.8;
    cameraDirty = true;
  }, { passive: false });
}

function bindTouchEvents() {
  let lastTX = 0, lastTY = 0, pinchDist = 0;
  document.addEventListener('touchstart', e => {
    if (e.target.closest('.modal-overlay') || e.target.closest('.lightbox-overlay')) return;
    if (e.touches.length === 1) { lastTX = e.touches[0].clientX; lastTY = e.touches[0].clientY; }
    else if (e.touches.length === 2) {
      pinchDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
    }
  }, { passive: true });
  document.addEventListener('touchmove', e => {
    if (e.target.closest('.modal-overlay') || e.target.closest('.lightbox-overlay')) return;
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
      scene.tZoom += (d - pinchDist) * 3;
      pinchDist = d;
    }
    cameraDirty = true;
  }, { passive: false });
}

// ---- Random 3D position (ephemeral, not persisted) ----
function randomPos() {
  return {
    pos_x: (Math.random() * 3600) - 1800,
    pos_y: (Math.random() * 1800) - 900,
    pos_z: -400 - Math.random() * 4600,
    rot_x: (Math.random() * 18)   - 9,
    rot_y: (Math.random() * 18)   - 9,
    rot_z: (Math.random() * 22)   - 11,
  };
}

// ---- Card drag ----
const cardPos = new WeakMap();

function buildCardTransform({ x, y, z, rx, ry, rz }) {
  return `translate3d(${x}px,${y}px,${z}px) rotateX(${rx}deg) rotateY(${ry}deg) rotateZ(${rz}deg)`;
}

let isDraggingCard = false;
let dragTarget     = null;
let dragLastX      = 0;
let dragLastY      = 0;
let dragMoved      = false;

function initCardDrag() {
  document.addEventListener('mousemove', e => {
    if (!isDraggingCard || !dragTarget) return;
    const dx = e.clientX - dragLastX;
    const dy = e.clientY - dragLastY;
    dragLastX = e.clientX;
    dragLastY = e.clientY;
    if (dx !== 0 || dy !== 0) dragMoved = true;
    const pos = cardPos.get(dragTarget);
    if (!pos) return;
    const perspective = 1200;
    const cardWorldZ  = pos.z + camPos.z;
    const factor      = (perspective - cardWorldZ) / perspective;
    pos.x += dx * factor;
    pos.y += dy * factor;
    dragTarget.style.transform = buildCardTransform(pos);
  });

  document.addEventListener('mouseup', () => {
    isDraggingCard             = false;
    dragTarget                 = null;
    document.body.style.cursor = '';
  });

  // Capture phase: fires before the scene wrapper's wheel handler
  document.addEventListener('wheel', e => {
    if (!isDraggingCard || !dragTarget) return;
    e.preventDefault();
    e.stopPropagation();
    const pos = cardPos.get(dragTarget);
    if (!pos) return;
    pos.z += e.deltaY < 0 ? 120 : -120;
    dragTarget.style.transform = buildCardTransform(pos);
  }, { passive: false, capture: true });
}

// ---- Photos ----
let photos = [];

async function loadPhotos(albumId) {
  const world  = document.getElementById('world');
  const status = document.getElementById('gallery-status');
  world.innerHTML = '';
  if (status) status.innerHTML = `<div class="spinner"></div>`;

  try {
    const res = await fetch(`api/photos.php?album_id=${albumId}`);
    if (!res.ok) throw new Error('Error al cargar fotos');
    photos = await res.json();

    if (status) status.innerHTML = '';

    if (photos.length === 0) {
      if (status) status.innerHTML = `
        <div class="empty-icon" style="color:var(--text-2);opacity:0.45">${SVG.camera}</div>
        <h3>Sin fotos aún</h3>
        <p>Sube la primera foto con el botón de arriba</p>
      `;
    } else {
      photos.forEach(p => renderPhotoCard(p));
    }

    sceneInit();
    initSearch();
    updatePhotoCount();
  } catch(e) {
    if (status) status.innerHTML = `<p style="color:var(--danger)">${escHtml(e.message)}</p>`;
  }
}

function renderPhotoCard(photo) {
  const world = document.getElementById('world');
  const card = document.createElement('div');
  card.className        = 'photo-card';
  card.style.willChange = 'transform';
  card.dataset.id       = photo.id;
  card.dataset.tags   = photo.tags  || '';
  card.dataset.title  = photo.title || '';

  const rp = randomPos();
  const pos = { x: rp.pos_x, y: rp.pos_y, z: rp.pos_z, rx: rp.rot_x, ry: rp.rot_y, rz: rp.rot_z };
  cardPos.set(card, pos);
  card.style.transform = buildCardTransform(pos);

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

  card.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    if (e.target.closest('.photo-delete-btn')) return;
    isDraggingCard             = true;
    dragTarget                 = card;
    dragLastX                  = e.clientX;
    dragLastY                  = e.clientY;
    dragMoved                  = false;
    document.body.style.cursor = 'grabbing';
    e.preventDefault();  // evita selección de texto durante el drag
    e.stopPropagation(); // prevent scene pan
  });

  card.addEventListener('click', e => {
    if (e.target.closest('.photo-delete-btn')) return;
    if (dragMoved) return;
    openLightbox(photo);
  });

  world.appendChild(card);
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
  currentUrl: '', currentTitle: '', currentId: null,
  tags: [],
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
  lb.img.src           = photo.url;
  lb.img.alt           = photo.title || '';
  lb.title.textContent = photo.title || '';
  lb.currentUrl        = photo.url;
  lb.currentTitle      = photo.title || 'foto';
  lb.currentId         = photo.id;
  lb.tags              = (photo.tags || '').split(',').map(t => t.trim()).filter(Boolean);

  renderLightboxTags();
  lb.overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lb.overlay.classList.remove('open');
  document.body.style.overflow = '';
  setTimeout(() => { lb.img.src = ''; }, 350);
}

// ---- Lightbox tag editing ----
function renderLightboxTags() {
  if (!lb.tagsEl) return;
  lb.tagsEl.innerHTML = '';

  lb.tags.forEach((tag, i) => {
    lb.tagsEl.appendChild(makePill(tag, i));
  });

  const addBtn = document.createElement('button');
  addBtn.className   = 'lb-tag-add';
  addBtn.type        = 'button';
  addBtn.textContent = '+';
  addBtn.title       = 'Añadir etiqueta';
  addBtn.addEventListener('click', () => {
    lb.tags.push('');
    renderLightboxTags();
    const pills = lb.tagsEl.querySelectorAll('.lb-tag-pill');
    if (pills.length) startEditPill(pills[pills.length - 1], lb.tags.length - 1);
  });
  lb.tagsEl.appendChild(addBtn);

  lb.tagsEl.style.display = 'flex';
}

function makePill(tag, idx) {
  const pill = document.createElement('span');
  pill.className = 'lb-tag-pill';

  const label = document.createElement('span');
  label.className   = 'lb-pill-label';
  label.textContent = tag;

  const removeBtn = document.createElement('button');
  removeBtn.className   = 'lb-pill-remove';
  removeBtn.type        = 'button';
  removeBtn.innerHTML   = '&times;';
  removeBtn.title       = 'Eliminar etiqueta';
  removeBtn.addEventListener('click', e => {
    e.stopPropagation();
    lb.tags.splice(idx, 1);
    saveTagsToServer();
    renderLightboxTags();
  });

  label.addEventListener('click', () => startEditPill(pill, idx));

  pill.appendChild(label);
  pill.appendChild(removeBtn);
  return pill;
}

function startEditPill(pill, idx) {
  if (pill.classList.contains('editing')) return;
  pill.classList.add('editing');

  const label = pill.querySelector('.lb-pill-label');
  const input = document.createElement('input');
  input.type      = 'text';
  input.className = 'lb-pill-input';
  input.value     = lb.tags[idx] || '';
  input.maxLength = 60;
  label.replaceWith(input);
  input.focus();
  input.select();

  function commit() {
    const val = input.value.trim().toLowerCase();
    if (val) {
      lb.tags[idx] = val;
    } else {
      lb.tags.splice(idx, 1);
    }
    saveTagsToServer();
    renderLightboxTags();
  }

  input.addEventListener('blur', commit);
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter')  { e.preventDefault(); input.blur(); }
    if (e.key === 'Escape') { lb.tags = lb.tags.filter(Boolean); renderLightboxTags(); }
  });
}

async function saveTagsToServer() {
  if (lb.currentId === null) return;
  try {
    const res = await fetch('api/photos.php', {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: lb.currentId, tags: lb.tags.join(',') }),
    });
    if (!res.ok) throw new Error('Error al guardar');
    const card = document.querySelector(`.photo-card[data-id="${lb.currentId}"]`);
    if (card) card.dataset.tags = lb.tags.join(',');
    const photo = photos.find(p => p.id == lb.currentId);
    if (photo) photo.tags = lb.tags.join(',');
  } catch(e) { showToast(e.message, 'error'); }
}

// ---- Upload modal ----
function initUploadModal() {
  const modal        = document.getElementById('uploadModal');
  const form         = document.getElementById('uploadForm');
  const dropzone     = document.getElementById('dropzone');
  const fileInput    = document.getElementById('photoFile');
  const urlInput     = document.getElementById('photoUrl');
  const urlGroup     = document.getElementById('uploadUrlGroup');
  const titleGroup   = document.getElementById('uploadTitleGroup');
  const singlePreview = document.getElementById('uploadPreview');
  const previewGrid  = document.getElementById('uploadPreviewGrid');
  const fileWarning  = document.getElementById('uploadFileWarning');
  const progressWrap = document.getElementById('uploadProgressWrap');
  const progressBar  = document.getElementById('uploadProgressBar');
  const submitBtn    = document.getElementById('uploadSubmitBtn');

  const dropIcon = dropzone.querySelector('.drop-icon');
  if (dropIcon) dropIcon.innerHTML = SVG.folder;

  let selectedFiles = []; // [{file, title}]
  let objectUrls    = [];

  function resetModal() {
    form.reset();
    selectedFiles = [];
    objectUrls.forEach(u => URL.revokeObjectURL(u));
    objectUrls = [];
    previewGrid.innerHTML    = '';
    previewGrid.style.display = 'none';
    fileWarning.textContent  = '';
    fileWarning.style.display = 'none';
    singlePreview.innerHTML  = '';
    singlePreview.style.display = 'none';
    progressWrap.style.display  = 'none';
    progressBar.style.width     = '0%';
    titleGroup.style.display    = '';
    urlGroup.style.display      = '';
    submitBtn.textContent        = 'Subir foto';
    submitBtn.disabled           = false;
    const p = dropzone.querySelector('p');
    if (p) p.textContent = 'Arrastra aquí o haz clic para seleccionar';
  }

  function setFiles(fileList) {
    let files = Array.from(fileList).filter(f => f.type.startsWith('image/'));
    if (files.length > 50) {
      fileWarning.textContent   = 'Máximo 50 fotos por subida. Se han recortado los excedentes.';
      fileWarning.style.display = 'block';
      files = files.slice(0, 50);
    } else {
      fileWarning.style.display = 'none';
    }
    selectedFiles = files.map(f => ({ file: f, title: '' }));
    renderPreviewGrid();
    updateSubmitLabel();
    const multiMode = selectedFiles.length > 0;
    titleGroup.style.display = multiMode ? 'none' : '';
    urlGroup.style.display   = multiMode ? 'none' : '';
  }

  function renderPreviewGrid() {
    objectUrls.forEach(u => URL.revokeObjectURL(u));
    objectUrls = [];
    previewGrid.innerHTML = '';

    if (selectedFiles.length === 0) {
      previewGrid.style.display = 'none';
      return;
    }
    previewGrid.style.display = 'grid';

    selectedFiles.forEach(({ file, title }, i) => {
      const objUrl = URL.createObjectURL(file);
      objectUrls.push(objUrl);

      const item = document.createElement('div');
      item.className = 'upload-preview-item';

      const img = document.createElement('img');
      img.src = objUrl;
      img.alt = file.name;

      const titleInput = document.createElement('input');
      titleInput.type        = 'text';
      titleInput.className   = 'upload-preview-item-title';
      titleInput.placeholder = 'Título';
      titleInput.maxLength   = 200;
      titleInput.value       = title;
      titleInput.addEventListener('input', ev => { selectedFiles[i].title = ev.target.value; });

      const removeBtn = document.createElement('button');
      removeBtn.type      = 'button';
      removeBtn.className = 'upload-preview-item-remove';
      removeBtn.innerHTML = '&times;';
      removeBtn.title     = 'Quitar';
      removeBtn.addEventListener('click', () => {
        selectedFiles.splice(i, 1);
        renderPreviewGrid();
        updateSubmitLabel();
        if (selectedFiles.length === 0) {
          titleGroup.style.display = '';
          urlGroup.style.display   = '';
        }
      });

      item.appendChild(img);
      item.appendChild(titleInput);
      item.appendChild(removeBtn);
      previewGrid.appendChild(item);
    });
  }

  function updateSubmitLabel() {
    const n = selectedFiles.length;
    submitBtn.textContent = n > 0 ? `Subir ${n} foto${n !== 1 ? 's' : ''}` : 'Subir foto';
  }

  // ---- Open / close ----
  document.getElementById('uploadBtn').addEventListener('click', () => {
    resetModal();
    modal.classList.add('open');
  });
  const closeModal = () => { resetModal(); modal.classList.remove('open'); };
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.getElementById('cancelUpload').addEventListener('click', closeModal);

  // ---- Dropzone ----
  dropzone.addEventListener('click',    () => fileInput.click());
  dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
  dropzone.addEventListener('drop', e => {
    e.preventDefault(); dropzone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) setFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', () => { if (fileInput.files.length) setFiles(fileInput.files); });

  // ---- URL preview ----
  urlInput.addEventListener('input', () => {
    const url = urlInput.value.trim();
    if (url) {
      singlePreview.style.display = 'block';
      singlePreview.innerHTML = `<img src="${escHtml(url)}" style="max-width:100%;border-radius:10px;max-height:160px;object-fit:cover;" onerror="this.style.display='none'">`;
    } else { singlePreview.innerHTML = ''; singlePreview.style.display = 'none'; }
  });

  // ---- Submit ----
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const albumId = form.dataset.albumId;
    const rawTags = document.getElementById('photoTags').value.trim();
    const tags    = [...new Set(rawTags.toLowerCase().split(',').map(t => t.trim()).filter(Boolean))].join(',');

    submitBtn.disabled = true;

    if (selectedFiles.length > 0) {
      // Multi-file upload
      const total = selectedFiles.length;
      let completed = 0;
      progressWrap.style.display = 'block';
      progressBar.style.width    = '0%';

      const tick = () => {
        completed++;
        progressBar.style.width = `${Math.round((completed / total) * 100)}%`;
      };

      const results = await Promise.allSettled(
        selectedFiles.map(async ({ file, title }) => {
          try {
            const fd = new FormData();
            fd.append('album_id', albumId);
            fd.append('title',    title.trim());
            fd.append('tags',     tags);
            fd.append('photo',    file);
            const res  = await fetch('api/photos.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error ?? 'Error');
            return data;
          } finally { tick(); }
        })
      );

      const ok  = results.filter(r => r.status === 'fulfilled');
      const bad = results.filter(r => r.status === 'rejected').length;

      ok.forEach(r => { photos.push(r.value); renderPhotoCard(r.value); });
      updatePhotoCount();
      closeModal();

      if (bad === 0) {
        showToast(`${ok.length} foto${ok.length !== 1 ? 's' : ''} subida${ok.length !== 1 ? 's' : ''} correctamente`);
      } else if (ok.length === 0) {
        showToast(`${bad} foto${bad !== 1 ? 's' : ''} fallida${bad !== 1 ? 's' : ''}`, 'error');
      } else {
        showToast(`${ok.length} correcta${ok.length !== 1 ? 's' : ''}, ${bad} fallida${bad !== 1 ? 's' : ''}`, 'error');
      }

    } else {
      // Modo URL — subida individual
      const title = document.getElementById('photoTitle').value.trim();
      const url   = urlInput.value.trim();
      if (!url) {
        showToast('Selecciona archivos o escribe una URL', 'error');
        submitBtn.disabled = false;
        return;
      }
      submitBtn.textContent = 'Subiendo…';
      try {
        const fd = new FormData();
        fd.append('album_id', albumId);
        fd.append('title',    title);
        fd.append('tags',     tags);
        fd.append('url',      url);
        const res  = await fetch('api/photos.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error ?? 'Error al subir');
        closeModal();
        showToast('Foto agregada');
        photos.push(data);
        renderPhotoCard(data);
        updatePhotoCount();
      } catch(err) {
        showToast(err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Subir foto';
      }
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
  initCardDrag();
  loadPhotos(albumId);
});
