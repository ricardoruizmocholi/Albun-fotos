// canvas-bg-dots.js — Puntos: grid que se deforma en ondas con curvatura esférica
export function init(canvas) {
  const ctx = canvas.getContext("2d");
  
  // Modificación 1: Color, intensidad y separación
  const COLOR = "#a0b4d6";
  const INTENSITY = 0.45;
  const SP = 32;
  
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
    t += 0.016;
    const amp = 9 * INTENSITY;
    
    for (let i = waves.length - 1; i >= 0; i--) {
      waves[i].age += .04;
      if (waves[i].age > 1) waves.splice(i, 1);
    }
    
    for (const d of dots) {
      let ox = 0, oy = Math.sin(d.bx * .018 + d.by * .009 + t * 1.2) * amp + Math.cos(d.bx * .009 - d.by * .014 + t * .9) * amp * .5;
      
      if (ma) {
        const dx = mx - d.bx, dy = my - d.by, dist = Math.hypot(dx, dy), rad = 160 * INTENSITY;
        if (dist < rad && dist > 1) {
          const f = (rad - dist) / rad;
          const wv = Math.sin(dist * .04 - t * 5) * f * amp * 1.8;
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
    const cx = W / 2;
    const cy = H / 2;
    const k = 0.0006; // Factor de curvatura

    for (const d of dots) {
      const x = d.bx + d.ox;
      const y = d.by + d.oy;

      // Modificación 2: Curvatura esférica
      // Normalización al rango [-1, 1]
      let norm_x = (x - cx) / cx;
      let norm_y = (y - cy) / cy;

      // Proyección Fisheye / Esférica
      let divisor = Math.sqrt(1 + norm_x * norm_x * k + norm_y * norm_y * k);
      let nx = norm_x / divisor;
      let ny = norm_y / divisor;

      // Desnormalización
      let proj_x = nx * cx + cx;
      let proj_y = ny * cy + cy;

      // Tamaño base según la profundidad de la curva (bordes más pequeños)
      let distNorm = Math.hypot(nx, ny);
      let factorProfundidad = 1 - (distNorm * 0.22); // En el borde (distNorm ~ 1) el factor es ~0.78
      let r_esfera = r_base * factorProfundidad;

      // Modificación 3: Opacidad por proximidad al ratón
      let distRaton = Math.hypot(proj_x - mx, proj_y - my);
      let opacidad = 0.045;

      if (distRaton < 280) {
        let f = distRaton / 280;
        opacidad = 0.85 - (f * (0.85 - 0.30));
      } else if (distRaton < 700) {
        let f = (distRaton - 280) / (700 - 280);
        opacidad = 0.30 - (f * (0.30 - 0.045));
      }

      // Tamaño final escalado con la opacidad
      let radio_final = r_esfera * (0.4 + opacidad * 0.9);

      ctx.beginPath();
      ctx.arc(proj_x, proj_y, radio_final, 0, Math.PI * 2);
      ctx.fillStyle = rgb(COLOR, opacidad);
      ctx.fill();
    }
  }

  canvas.addEventListener("mousemove", e => { mx = e.clientX; my = e.clientY; ma = true; clearTimeout(it); it = setTimeout(() => ma = false, 2000); });
  // Ocultar brillo gradualmente si el ratón sale de la pantalla
  canvas.addEventListener("mouseleave", () => { mx = -9999; my = -9999; ma = false; });
  canvas.addEventListener("click", e => { waves.push({ x: e.clientX, y: e.clientY, age: 0, s: 1 }); });
  canvas.addEventListener("contextmenu", e => { e.preventDefault(); waves.push({ x: e.clientX, y: e.clientY, age: 0, s: -1 }); });
  window.addEventListener("resize", resize);
  
  resize();
  (function loop() { update(); draw(); requestAnimationFrame(loop); })();
}