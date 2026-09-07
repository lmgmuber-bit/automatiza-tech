import { T } from './textos.mjs';

/**
 * La partida se guarda por fiesta, no por dispositivo.
 *
 * La misma tablet atiende varios cumpleaños y todos viven en el mismo dominio, así que con
 * una sola clave la segunda fiesta abría con los invitados y los puntajes de la primera. El
 * identificador sale de `?p=` (el slug de la fiesta, que no es un dato personal) y se sanea
 * para que no pueda construirse una clave rara desde la barra de direcciones.
 *
 * Sin `?p=` se usa la clave de siempre: así una partida abierta antes de este cambio se
 * sigue encontrando y nadie pierde una fiesta a medio jugar.
 */
const BASE = 'cumpleclick-festival-v1';
const KEY = (() => {
    try {
        const p = new URLSearchParams(location.search).get('p') || '';
        const slug = p.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 40);
        return slug ? `${BASE}:${slug}` : BASE;
    } catch {
        return BASE;
    }
})();
export function nombreSeguro(value, fallback = T.predeterminado) { return typeof value === 'string' ? value.replace(/[<>\u0000-\u001f]/g, '').trim().slice(0, 24) || fallback : fallback; }
export function cargar() { try {
    const d = JSON.parse(localStorage.getItem(KEY));
    if (!d || d.version !== 1 || !Array.isArray(d.players) || d.players.length < 8)
        return null;
    const remaining = Number(d.remaining);
    return { version: 1, name: nombreSeguro(d.name), age: Math.min(12, Math.max(4, Number(d.age) || 5)), players: d.players.slice(0, 12).map((p, i) => ({ name: nombreSeguro(p.name, `Invitado ${i + 1}`), score: Math.max(0, Math.min(9999, Number(p.score) || 0)) })), turn: Math.max(0, Math.min(Math.min(12, d.players.length) - 1, Number(d.turn) || 0)), remaining: Math.max(0, Math.min(60, Number.isFinite(remaining) ? remaining : 60)), phase: ['inicio', 'tutorial', 'corte', 'wow', 'entrega', 'turno', 'final'].includes(d.phase) ? d.phase : 'entrega', sound: d.sound !== false, reduced: d.reduced === true, rise: Number(d.rise) || 0 };
}
catch {
    return null;
} }
export function guardar(state) { try {
    localStorage.setItem(KEY, JSON.stringify({ version: 1, name: state.name, age: state.age, players: state.players, turn: state.turn, remaining: state.remaining, phase: state.phase, reduced: state.reduced, sound: state.sound, rise: state.rise }));
    return true;
}
catch {
    return false;
} }
