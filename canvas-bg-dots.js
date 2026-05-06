// canvas-bg-dots.js — Puntos en grid con curvatura esférica y opacidad por proximidad
export function init(canvas) {
  const ctx = canvas.getContext("2d");
  const COLOR = "#a0b4d6", INTENSITY = 0.45, SP = 32;
  let W, H, dots = [], waves = [], t = 0, mx = -9999, my = -9999, ma = false, it;

  const rgb = (hex, a) => {
    const n = parseInt(hex.replace("#", ""), 16);
    return "rgba(" + ((n >> 16) & 255) + "," + ((n >> 8) & 255) + "," + (n & 255) + "," + a + ")";
  };

  function resize() {
    W = canvas.width = innerWidth;
    H = canvas.height = innerHeight;
    dots = [];
    const cols = Math.ceil(W / SP) + 2, rows = Math.ceil(H / SP) + 2;
    for (let r = 0; r < rows; r++) {
      for (let c = 0; c < cols; c++) {
        dots.push({ bx: (c - .5) * SP, by: (r - .5) * SP, ox: 0, oy: 0 });
      }
    }
  }

  function update() {
    t += 0.008;
    const amp = 9 * INTENSITY * 0.5;
    for (let i = waves.length - 1; i >= 0; i--) {
      waves[i].age += .04;
      if (waves[i].age > 1) waves.splice(i, 1);
    }
    for (const d of dots) {
      let ox = 0, oy = Math.sin(d.bx * .018 + d.by * .009 + t * 0.6) * amp + Math.cos(d.bx * .009 - d.by * .014 + t * 0.45) * amp * .5;
      if (ma) {
        const dx = mx - d.bx, dy = my - d.by, dist = Math.hypot(dx, dy), rad = 160 * INTENSITY;
        if (dist < rad && dist > 1) {
          const f = (rad - dist) / rad;
          const wv = Math.sin(dist * .04 - t * 2.5) * f * amp * 1.8;
          ox += dx / dist * wv;
          oy += dy / dist * wv;
        }
      }
      for (const w of waves) {
        const dx = d.bx - w.x, dy = d.by - w.y, dist = Math.hypot(dx, dy);
        const front = w.age * 400, band = 60, delta = Math.abs(dist - front);
        if (delta < band) {
          const iv = Math.sin((1 - delta / band) * Math.PI) * (1 - w.age) * amp * 3 * w.s;
          const a = Math.atan2(dy, dx);
          ox += Math.cos(a) * iv;
          oy += Math.sin(a) * iv;
        }
      }
      d.ox = Math.max(-SP * .7, Math.min(SP * .7, ox));
      d.oy = Math.max(-SP * .7, Math.min(SP * .7, oy));
    }
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    const r_base = 2.5 * INTENSITY + 1;
    const cx = W / 2, cy = H / 2;
    const k = 0.0006;

    for (const d of dots) {
      const x = d.bx + d.ox, y = d.by + d.oy;

      // Proyección esférica (fisheye interior)
      const norm_x = (x - cx) / cx;
      const norm_y = (y - cy) / cy;
      const divisor = Math.sqrt(1 + norm_x * norm_x * k + norm_y * norm_y * k);
      const nx = norm_x / divisor;
      const ny = norm_y / divisor;
      const proj_x = nx * cx + cx;
      const proj_y = ny * cy + cy;

      // Tamaño: bordes ligeramente más pequeños (profundidad percibida)
      const distNorm = Math.hypot(nx, ny);
      const r_esfera = r_base * (1 - distNorm * 0.22);

      // Opacidad por proximidad al ratón
      const distRaton = Math.hypot(proj_x - mx, proj_y - my);
      let opacidad;
      if (distRaton < 280) {
        opacidad = 0.85 - (distRaton / 280) * (0.85 - 0.30);
      } else if (distRaton < 700) {
        opacidad = 0.30 - ((distRaton - 280) / 420) * (0.30 - 0.045);
      } else {
        opacidad = 0.045;
      }

      const radio_final = r_esfera * (0.4 + opacidad * 0.9);

      ctx.beginPath();
      ctx.arc(proj_x, proj_y, radio_final, 0, Math.PI * 2);
      ctx.fillStyle = rgb(COLOR, opacidad);
      ctx.fill();
    }
  }

  // Listeners en window/document — el canvas tiene pointer-events:none
  window.addEventListener("mousemove", e => {
    mx = e.clientX; my = e.clientY; ma = true;
    clearTimeout(it); it = setTimeout(() => ma = false, 2000);
  });
  document.addEventListener("mouseleave", () => { mx = -9999; my = -9999; ma = false; });
  window.addEventListener("resize", resize);

  resize();
  (function loop() { update(); draw(); requestAnimationFrame(loop); })();
}
