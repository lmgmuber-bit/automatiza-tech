/*
 * Anota el puntaje de la ronda en la tabla de posiciones de la fiesta.
 *
 * Este archivo lo agregó la integración con CumpleClick, no venía en el juego. El juego se
 * sigue jugando igual sin él: si el envío falla, si no hay red o si la dirección no trae
 * fiesta, no pasa absolutamente nada visible.
 *
 * Se usa `sendBeacon` y no un `fetch` normal por un motivo concreto: el puntaje se manda
 * justo cuando la ronda termina y la pantalla cambia, y un `fetch` común se cancela en ese
 * momento. El beacon lo entrega el navegador aunque la página ya no esté mirando.
 *
 * DOS PARÁMETROS DISTINTOS, y esto es lo que más confunde:
 *
 *   - `nombre` es DE QUIÉN ES LA FIESTA. El juego lo pinta como "La fiesta de Luciano".
 *   - `jugador` es QUIÉN ESTÁ JUGANDO, elegido en el menú antes de entrar.
 *
 * Para la tabla sirve el segundo. Anotar el primero llenaría la tabla con el nombre del
 * cumpleañero repetido, sin importar quién jugó.
 *
 * Y solo se anota en formato individual: en el de turnos el juego no pregunta el nombre de
 * cada participante, así que todos los turnos quedarían atribuidos al mismo niño, que es
 * peor que no anotar nada.
 */

// Se resuelve contra la dirección del documento (no la del módulo): desde
// /app/juego/impulso-aracnido/ esto llega a /app/puntajes.php.
const RUTA = '../../puntajes.php';
const JUEGO = 'impulso';

/** Un valor de la dirección, saneado igual que en el servidor. Vacío si no viene. */
function param(nombre, largo) {
    try {
        return (new URLSearchParams(location.search).get(nombre) || '').trim().slice(0, largo);
    } catch {
        return '';
    }
}

export function anotar(puntaje, formato) {
    if (formato && formato !== 'individual') { return; }

    const fiesta = param('p', 60).toLowerCase().replace(/[^a-z0-9-]/g, '');
    const jugador = param('jugador', 40);
    const puntos = Math.max(0, Math.min(999999, Math.round(Number(puntaje) || 0)));
    // Sin fiesta o sin jugador no hay a quién ni dónde anotar. Abrir el juego suelto para
    // probarlo no debe ensuciar la tabla de ningún cumpleaños.
    if (!fiesta || !jugador) { return; }

    try {
        const datos = new FormData();
        datos.append('p', fiesta);
        datos.append('juego', JUEGO);
        datos.append('jugador', jugador);
        datos.append('puntaje', String(puntos));

        if (navigator.sendBeacon && navigator.sendBeacon(RUTA, datos)) { return; }
        // `keepalive` cumple el mismo papel que el beacon cuando este no está disponible.
        fetch(RUTA, { method: 'POST', body: datos, keepalive: true }).catch(() => {});
    } catch {
        // Un puntaje perdido no vale interrumpir la fiesta.
    }
}
