export const MODOS = Object.freeze({ paseo: { radio: 3.5, ayuda: true, premio: 10 }, aventura: { radio: 2.5, ayuda: false, premio: 15 }, desafio: { radio: 1.65, ayuda: false, premio: 20 } });
export const carril = x => Math.sin(x / 48) * 2.4;
export const aroY = i => 7.6 + Math.sin(i * 1.7) * 1.5;
export function nuevoVuelo() { return { x: 0, y: 8, z: 0, vx: 0, vy: 0, fase: 'listo', theta: -.87, omega: 1.4, pivote: null, tiempoVuelo: 0, impulsos: 0, rebotes: 0, perfecto: false, evento: null }; }
export function enganchar(s) {
  s.pivote = { x: s.x + 6, y: Math.max(11, s.y + 5) };
  const dx = s.x - s.pivote.x, dy = s.y - s.pivote.y;
  s.largo = Math.hypot(dx, dy); s.theta = Math.atan2(dx, -dy);
  s.omega = Math.max(.85, Math.min(1.5, Math.abs(s.vx) / s.largo + .8));
  s.fase = 'balanceo'; s.evento = 'enganche';
}
export function soltar(s, modo) {
  s.perfecto = s.theta >= .2 && s.theta <= .8 && s.omega > 0;
  s.vx = Math.max(6, Math.cos(s.theta) * s.largo * Math.max(.7, s.omega));
  s.vy = Math.sin(s.theta) * s.largo * s.omega + (modo === 'paseo' ? 4 : 2.3);
  s.vx += s.perfecto ? 3 : 0; s.fase = 'vuelo'; s.tiempoVuelo = 0;
  s.impulsos++; s.evento = s.perfecto ? 'perfecto' : 'vuelo';
}
export function avanzar(s, dt, pulsado, modo = 'paseo', solto = false) {
  dt = Math.max(0, Math.min(dt, 1 / 30)); s.evento = null;
  if (s.fase === 'listo') { if (pulsado) enganchar(s); return; }
  if (s.fase === 'balanceo') {
    s.omega += (-10 / s.largo * Math.sin(s.theta) - .1 * s.omega) * dt;
    s.theta += s.omega * dt;
    if (s.theta < -1.15) s.omega = Math.abs(s.omega) + .35;
    s.x = s.pivote.x + Math.sin(s.theta) * s.largo;
    s.y = s.pivote.y - Math.cos(s.theta) * s.largo;
    if (solto || (!pulsado && modo !== 'paseo') || (modo === 'paseo' && s.theta > .42)) soltar(s, modo);
  } else {
    s.tiempoVuelo += dt; s.vy -= 10 * dt; s.x += s.vx * dt; s.y += s.vy * dt;
    if (s.y < 2.4) { s.y = 2.4; s.vy = 12; s.vx = Math.max(7, s.vx); s.rebotes++; s.evento = 'red'; }
    if (s.tiempoVuelo > .55 && ((pulsado && s.y < 13) || (modo === 'paseo' && s.y < 6 && s.vy < 0))) enganchar(s);
    if (s.y > 21) { s.y = 21; s.vy = Math.min(0, s.vy); }
  }
  s.z = carril(s.x);
}
export function resolverAros(anterior, s, aros, modo, cadena) {
  let puntos = 0, aciertos = 0, siguiente = cadena;
  const radio = MODOS[modo]?.radio ?? MODOS.paseo.radio;
  for (const a of aros) {
    if (a.hecho || anterior.x >= a.x || s.x < a.x) continue;
    a.hecho = true;
    const t = (a.x - anterior.x) / Math.max(.00001, s.x - anterior.x);
    const altura = anterior.y + (s.y - anterior.y) * t;
    a.acierto = Math.abs(altura - a.y) < radio;
    if (a.acierto) { siguiente++; aciertos++; puntos += (MODOS[modo]?.premio ?? 10) + Math.min(siguiente, 5) * 2; }
    else siguiente = 0;
  }
  return { puntos, aciertos, cadena: siguiente };
}
