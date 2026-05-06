// canvas-bg-dots.js — Puntos: grid que se deforma en ondas
export function init(canvas) {
  const ctx = canvas.getContext("2d");
  const COLOR = "#a0b4d6", INTENSITY = 0.45, SP = 32;
  const K = 0.0006; // factor de curvatura esférica
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
    for (let r = 0; r < rows; r++)
      for (let c = 0; c < cols; c++)
        dots.push({ bx: (c - .5) * SP, by: (r - .5) * SP, ox: 0, oy: 0 });
  }

  function update() {
    t += 0.016;
    const amp = 9 * INTENSITY;
    for (let i = waves.length - 1; i >= 0; i--) {
      waves[i].age += .04;
      if (waves[i].age > 1) waves.splice(i, 1);
    }
    for (const d of dots) {
      let ox = 0,
        oy = Math.sin(d.bx * .018 + d.by * .009 + t * 1.2) * amp
           + Math.cos(d.bx * .009 - d.by * .014 + t * .9) * amp * .5;
      if (ma) {
        const dx = mx - d.bx, dy = my - d.by,
          dist = Math.hypot(dx, dy), rad = 160 * INTENSITY;
        if (dist < rad && dist > 1) {
          const f = (rad - dist) / rad, wv = Math.sin(dist * .04 - t * 5) * f * amp * 1.8;
          ox += dx / dist * wv; oy += dy / dist * wv;
        }
      }
      for (const w of waves) {
        const dx = d.bx - w.x, dy = d.by - w.y,
          dist = Math.hypot(dx, dy), front = w.age * 400,
          band = 60, delta = Math.abs(dist - front);
        if (delta < band) {
          const iv = Math.sin((1 - delta / band) * Math.PI) * (1 - w.age) * amp * 3 * w.s,
            a = Math.atan2(dy, dx);
          ox += Math.cos(a) * iv; oy += Math.sin(a) * iv;
        }
      }
      d.ox = Math.max(-SP * .7, Math.min(SP * .7, ox));
      d.oy = Math.max(-SP * .7, Math.min(SP * .7, oy));
    }
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    const rBase = 2.5 * INTENSITY + 1;
    const cx = W / 2, cy = H / 2;
    const maxDist = Math.hypot(cx, cy);
    const R_INNER = 280, R_OUTER = 700;

    for (const d of dots) {
      const x = d.bx + d.ox;
      const y = d.by + d.oy;

      // Proyección esférica: coords centradas en pantalla
      const px = x - cx, py = y - cy;
      const denom = Math.sqrt(1 + px * px * K + py * py * K);
      const spx = px / denom + cx;
      const spy = py / denom + cy;

      // Escala de profundidad: borde → ×0.78, centro → ×1.0
      const normDist = Math.min(1, Math.hypot(px, py) / maxDist);
      const depthScale = 1 - normDist * (1 - 0.78);

      // Opacidad por distancia al cursor
      const distRaton = Math.hypot(spx - mx, spy - my);
      let opacity;
      if (distRaton < R_INNER) {
        opacity = 0.85 - (distRaton / R_INNER) * (0.85 - 0.30);
      } else if (distRaton < R_OUTER) {
        opacity = 0.30 - ((distRaton - R_INNER) / (R_OUTER - R_INNER)) * (0.30 - 0.045);
      } else {
        opacity = 0.045;
      }

      const r = rBase * depthScale * (0.4 + opacity * 0.9);

      ctx.beginPath();
      ctx.arc(spx, spy, r, 0, Math.PI * 2);
      ctx.fillStyle = rgb(COLOR, opacity);
      ctx.fill();
    }
  }

  // Listeners sobre window/document para funcionar con pointer-events:none en el canvas
  window.addEventListener("mousemove", e => {
    mx = e.clientX; my = e.clientY; ma = true;
    clearTimeout(it); it = setTimeout(() => ma = false, 2000);
  });
  document.addEventListener("mouseleave", () => { mx = -9999; my = -9999; ma = false; });
  window.addEventListener("click", e => { waves.push({ x: e.clientX, y: e.clientY, age: 0, s: 1 }); });
  canvas.addEventListener("contextmenu", e => { e.preventDefault(); waves.push({ x: e.clientX, y: e.clientY, age: 0, s: -1 }); });
  window.addEventListener("resize", resize);
  resize();
  (function loop() { update(); draw(); requestAnimationFrame(loop); })();
}
