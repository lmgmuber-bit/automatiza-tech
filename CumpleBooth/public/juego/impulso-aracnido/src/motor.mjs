import * as THREE from '../vendor/three.webgpu.js';
export async function crearMotor(canvas, webgl) {
  const renderer = new THREE.WebGPURenderer({ canvas, antialias: true, forceWebGL: webgl || !navigator.gpu });
  await renderer.init(); renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = 1.02;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  const scene = new THREE.Scene(); scene.background = new THREE.Color('#89999c'); scene.fog = new THREE.Fog('#a9b1aa', 68, 170);
  scene.add(new THREE.HemisphereLight('#c9dbe3', '#737567', 1.55));
  const sun = new THREE.DirectionalLight('#ffdbad', 2.6); sun.position.set(-35, 28, 15); scene.add(sun);
  const rim = new THREE.DirectionalLight('#adcbd1', .4); rim.position.set(15, 10, -25); scene.add(rim);
  const camera = new THREE.PerspectiveCamera(51, 1, .15, 210);
  let ratio = Math.min(devicePixelRatio || 1, 1.35), tiempo = 0, frames = 0, lentos = 0;
  const metricas = { backend: renderer.backend.isWebGPUBackend ? 'WebGPU' : 'WebGL2', fps: 0, calls: 0, triangles: 0, ratio, muestras: [] };
  function resize() { renderer.setPixelRatio(ratio); renderer.setSize(innerWidth, innerHeight, false); camera.aspect = innerWidth / innerHeight; camera.fov = camera.aspect < 1 ? 57 : 49; camera.updateProjectionMatrix(); }
  resize(); addEventListener('resize', resize);
  return { renderer, scene, camera, metricas, render(dt, fase) {
    renderer.render(scene, camera); frames++; tiempo += dt;
    if (tiempo >= 1) {
      const r = renderer.info.render; metricas.fps = frames / tiempo; metricas.calls = r.drawCalls ?? r.calls ?? 0; metricas.triangles = r.triangles ?? 0; metricas.ratio = ratio;
      metricas.muestras.push({ fps: metricas.fps, calls: metricas.calls, triangles: metricas.triangles, ratio, fase, instante: performance.now() });
      if (metricas.muestras.length > 1000) metricas.muestras.shift();
      lentos = metricas.fps < 45 ? lentos + 1 : 0;
      if (lentos >= 3 && ratio > .8) { ratio = Math.max(.8, ratio - .15); resize(); lentos = 0; }
      frames = 0; tiempo = 0;
    }
  }, resetReloj() { tiempo = 0; frames = 0; } };
}
