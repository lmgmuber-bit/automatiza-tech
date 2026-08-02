/* CumpleClick — globo3d.js (ESM, carga diferida vía import())
   El globo-lente de la marca en 3D: misma forma y colores del isotipo
   (public/brand/cumpleclick-mark.svg), ningún objeto inventado.
   Guardas:
   - Sin WebGL / equipo modesto / reduced-motion → main.js ni siquiera lo llama.
   - Bajo 30fps sostenido → se retira solo y vuelve la imagen estática.
   - Pausa fuera de viewport y con pestaña oculta.
*/
import * as THREE from '../vendor/three.module.js';

/* Textura de gradiente idéntica al SVG de marca (canvas 2D, sin assets nuevos) */
function makeGradientTexture(stops, cx, cy, r) {
  const size = 256;
  const cv = document.createElement('canvas');
  cv.width = cv.height = size;
  const ctx = cv.getContext('2d');
  const g = ctx.createRadialGradient(size * cx, size * cy, 0, size * cx, size * cy, size * r);
  stops.forEach(function (s) { g.addColorStop(s[0], s[1]); });
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, size, size);
  const tex = new THREE.CanvasTexture(cv);
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

function buildGlobo() {
  const group = new THREE.Group();

  /* Cuerpo del globo — gradiente cc-body del SVG */
  const bodyTex = makeGradientTexture(
    [[0, '#B9A0F7'], [0.34, '#8B5CF6'], [0.72, '#C0409E'], [1, '#D6307F']],
    0.34, 0.26, 0.88
  );
  const body = new THREE.Mesh(
    new THREE.SphereGeometry(1, 48, 48),
    new THREE.MeshPhongMaterial({ map: bodyTex, shininess: 60, specular: new THREE.Color('#ffffff') })
  );
  group.add(body);

  /* Brillo superior izquierdo (como la elipse blanca del SVG) */
  const gloss = new THREE.Mesh(
    new THREE.SphereGeometry(0.32, 24, 24),
    new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.32 })
  );
  gloss.position.set(-0.42, 0.52, 0.72);
  gloss.scale.set(1.4, 0.85, 0.5);
  group.add(gloss);

  /* Lente: aro amarillo + cristal lila, al frente-izquierda como en el isotipo */
  const lensGroup = new THREE.Group();
  const ring = new THREE.Mesh(
    new THREE.TorusGeometry(0.3, 0.075, 24, 48),
    new THREE.MeshPhongMaterial({ color: 0xfbbf24, emissive: 0x7a5200, shininess: 90 })
  );
  const lensTex = makeGradientTexture(
    [[0, '#D8CCFB'], [0.6, '#A88BE8'], [1, '#8256C9']],
    0.38, 0.32, 0.85
  );
  const crystal = new THREE.Mesh(
    new THREE.CircleGeometry(0.3, 48),
    new THREE.MeshPhongMaterial({ map: lensTex, shininess: 120, specular: new THREE.Color('#ffffff') })
  );
  crystal.position.z = 0.02;
  const lensGloss = new THREE.Mesh(
    new THREE.CircleGeometry(0.09, 24),
    new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.55 })
  );
  lensGloss.position.set(-0.09, 0.1, 0.04);
  lensGroup.add(ring, crystal, lensGloss);
  lensGroup.position.set(-0.16, 0.05, 0.97);
  lensGroup.rotation.x = -0.08;
  group.add(lensGroup);

  /* Boquilla (nudo) arriba-derecha, como el isotipo */
  const knot = new THREE.Mesh(
    new THREE.ConeGeometry(0.12, 0.22, 24),
    new THREE.MeshPhongMaterial({ color: 0xc2186b, shininess: 40 })
  );
  knot.position.set(0.68, 0.66, 0.28);
  knot.rotation.z = -0.7;
  group.add(knot);

  /* Hilo dorado ondeado (Oro Hilo: detalle fino, nunca masa grande) */
  const pts = [];
  for (let i = 0; i <= 24; i++) {
    const t = i / 24;
    pts.push(new THREE.Vector3(
      0.78 + Math.sin(t * Math.PI * 4) * 0.09,
      0.7 + t * 1.1,
      0.28 + Math.cos(t * Math.PI * 3) * 0.05
    ));
  }
  const string = new THREE.Mesh(
    new THREE.TubeGeometry(new THREE.CatmullRomCurve3(pts), 48, 0.018, 8, false),
    new THREE.MeshPhongMaterial({ color: 0xe8a317, shininess: 70 })
  );
  group.add(string);

  /* Confeti flotante (los 5 puntitos del isotipo) */
  const dots = [
    { c: 0x8b5cf6, p: [-1.5, 0.9, -0.3], s: 0.09 },
    { c: 0xa78bfa, p: [-1.7, -0.4, 0.2], s: 0.07 },
    { c: 0xfbbf24, p: [-1.3, -1.2, -0.1], s: 0.08 },
    { c: 0xfbbf24, p: [1.5, -0.5, -0.2], s: 0.07 },
    { c: 0xd6307f, p: [1.4, -1.3, 0.1], s: 0.08 }
  ];
  const dotMeshes = dots.map(function (d, i) {
    const m = new THREE.Mesh(
      new THREE.SphereGeometry(d.s, 16, 16),
      new THREE.MeshPhongMaterial({ color: d.c, shininess: 80 })
    );
    m.position.set(d.p[0], d.p[1], d.p[2]);
    m.userData.phase = i * 1.3;
    group.add(m);
    return m;
  });

  return { group, dotMeshes };
}

export function mountHeroGlobo(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 50);
  camera.position.set(0, 0.1, 4.6);

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  container.appendChild(renderer.domElement);
  container.classList.add('hero__visual--3d');

  scene.add(new THREE.AmbientLight(0xfff8ec, 1.1));
  const key = new THREE.DirectionalLight(0xffffff, 1.4);
  key.position.set(-3, 4, 5);
  scene.add(key);
  const rim = new THREE.PointLight(0xd6307f, 12, 12);
  rim.position.set(3, -1, 2);
  scene.add(rim);

  const globo = buildGlobo();
  scene.add(globo.group);

  /* Parallax suave al mouse (no táctil) */
  let targetRX = 0, targetRY = 0;
  const isTouch = window.matchMedia('(pointer: coarse)').matches;
  if (!isTouch) {
    window.addEventListener('pointermove', function (ev) {
      const nx = (ev.clientX / window.innerWidth) * 2 - 1;
      const ny = (ev.clientY / window.innerHeight) * 2 - 1;
      targetRY = nx * 0.35;
      targetRX = ny * 0.2;
    }, { passive: true });
  }

  function resize() {
    const w = container.clientWidth || 1;
    const h = container.clientHeight || 1;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }
  resize();
  window.addEventListener('resize', resize, { passive: true });

  /* Pausas: fuera de viewport o pestaña oculta */
  let inView = true;
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      inView = entries[0].isIntersecting;
    }, { threshold: 0.05 }).observe(container);
  }

  /* Guardia de fps: bajo 30fps sostenido → fuera 3D, vuelve la estática */
  let frames = 0, accum = 0, last = performance.now(), degraded = false;
  const WARMUP = 90, WINDOW = 150;

  function degrade() {
    degraded = true;
    renderer.dispose();
    if (renderer.domElement.parentNode) renderer.domElement.parentNode.removeChild(renderer.domElement);
    container.classList.remove('hero__visual--3d');
    window.removeEventListener('resize', resize);
  }

  const timer = new THREE.Timer();
  timer.connect(document);
  function tick(timestamp) {
    if (degraded) return;
    requestAnimationFrame(tick);
    if (!inView || document.hidden) { last = performance.now(); return; }

    timer.update(timestamp);
    const t = timer.getElapsed();
    globo.group.position.y = Math.sin(t * 0.9) * 0.08;
    globo.group.rotation.y += (targetRY + t * 0.15 - globo.group.rotation.y) * 0.04;
    globo.group.rotation.x += (targetRX - globo.group.rotation.x) * 0.05;
    globo.dotMeshes.forEach(function (m) {
      m.position.y += Math.sin(t * 1.4 + m.userData.phase) * 0.0016;
    });
    renderer.render(scene, camera);

    /* medición fps (tras warmup) */
    const now = performance.now();
    const dt = now - last;
    last = now;
    frames++;
    if (frames > WARMUP) {
      accum += dt;
      if (frames % WINDOW === 0) {
        const avgFps = 1000 / (accum / (WINDOW - WARMUP > 0 ? Math.min(frames - WARMUP, WINDOW) : WINDOW));
        if (avgFps < 30) degrade();
        accum = 0;
      }
    }
  }
  tick();

  /* API mínima para QA: fps actual */
  window.__ccGlobo = {
    getFps: function () {
      return accum > 0 ? Math.round(1000 / (accum / Math.max(1, frames % WINDOW))) : null;
    },
    degrade: degrade
  };
}
