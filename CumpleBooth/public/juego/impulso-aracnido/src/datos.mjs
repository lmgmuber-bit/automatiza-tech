import { personajeValido } from './avatares.mjs';
const PREFIJO = 'cumpleclick:impulso:v1:';
export const limpiarNombre = v => String(v ?? '').replace(/[<>\u0000-\u001f]/g, '').trim().slice(0, 32) || 'Tu fiesta';
export function parametros(search) {
  const q = new URLSearchParams(search), raw = q.get('p') || '';
  const fiesta = /^[a-zA-Z0-9_-]{1,120}$/.test(raw) ? raw : null;
  return { fiesta, nombre: limpiarNombre(q.get('nombre')), edad: Math.min(15, Math.max(3, Number(q.get('edad')) || 5)), debug: q.get('debug') === '1', webgl: q.get('backend') === 'webgl' };
}
export function clave(fiesta) { return fiesta && /^[a-zA-Z0-9_-]{1,120}$/.test(fiesta) ? PREFIJO + fiesta : null; }
export function guardar(storage, fiesta, datos) {
  try { const k = clave(fiesta); if (!k) return false; storage.setItem(k, JSON.stringify({ ...datos, version: 1 })); return true; } catch { return false; }
}
export function cargar(storage, fiesta) {
  try {
    const k = clave(fiesta); if (!k) return null;
    const d = JSON.parse(storage.getItem(k));
    if (!d || d.version !== 1 || !['paseo', 'aventura', 'desafio'].includes(d.modo) || !['individual', 'turnos'].includes(d.formato)) return null;
    const n = d.formato === 'turnos' ? Number(d.cantidad) : 1;
    if (!Number.isInteger(n) || (n !== 1 && (n < 8 || n > 12))) return null;
    if (!Array.isArray(d.resultados) || d.resultados.length > n || d.resultados.some(x => !Number.isFinite(x) || x < 0 || x > 100000)) return null;
    if (!Number.isInteger(d.turno) || d.turno < 0 || d.turno >= n || d.resultados.length !== d.turno) return null;
    if (!Number.isFinite(d.restante) || d.restante < 0 || d.restante > (n === 1 ? 90 : 60)) return null;
    return { avatar: personajeValido(d.avatar), modo: d.modo, formato: d.formato, cantidad: n, turno: d.turno, resultados: d.resultados, restante: d.restante, puntos: Math.max(0, Math.min(100000, Number(d.puntos) || 0)), sonido: d.sonido !== false, suave: d.suave === true };
  } catch { return null; }
}
export function borrar(storage, fiesta) { try { const k = clave(fiesta); if (k) storage.removeItem(k); } catch {} }
export function regreso(p, href) {
  const base = new URL('../', href);
  if (p.fiesta) base.searchParams.set('p', p.fiesta);
  base.searchParams.set('nombre', p.nombre); base.searchParams.set('edad', String(p.edad));
  return base.href;
}
