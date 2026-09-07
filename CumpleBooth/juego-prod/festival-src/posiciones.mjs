/**
 * Manda el puntaje de un jugador a la tabla de posiciones de la fiesta.
 *
 * Todo acá está pensado para que **nada de esto pueda arruinar una partida en curso**. La
 * tabla es un adorno bonito; el juego es lo que el niño está mirando. Por eso:
 *
 *  - Nunca lanza. Si el servidor no responde, si no hay red, si la respuesta viene rota:
 *    se pierde ese puntaje y el juego sigue como si nada.
 *  - Usa `sendBeacon` cuando existe. Un turno termina justo cuando la pantalla cambia y el
 *    niño le pasa la tablet al siguiente; un `fetch` normal ahí se cancela a mitad de vuelo.
 *    El beacon lo entrega el navegador por su cuenta, aunque la página ya se haya ido.
 *  - No espera respuesta ni bloquea nada.
 *
 * Sin `?p=` en la dirección no hay fiesta a la cual reportar y la función no hace nada: así
 * el juego abierto suelto, para probar, no ensucia la tabla de ningún cumpleaños.
 */

const RUTA = '../../puntajes.php';
const JUEGO = 'festival';

/** El slug de la fiesta, saneado igual que en el servidor. Vacío si no viene. */
function fiesta() {
    try {
        const p = new URLSearchParams(location.search).get('p') || '';
        return p.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 60);
    } catch {
        return '';
    }
}

/** Anota un puntaje. No devuelve nada y nunca falla hacia afuera. */
export function anotar(jugador, puntaje) {
    const p = fiesta();
    const nombre = String(jugador || '').trim().slice(0, 40);
    const puntos = Math.max(0, Math.min(999999, Math.round(Number(puntaje) || 0)));
    if (!p || !nombre) { return; }

    try {
        const datos = new FormData();
        datos.append('p', p);
        datos.append('juego', JUEGO);
        datos.append('jugador', nombre);
        datos.append('puntaje', String(puntos));

        if (navigator.sendBeacon && navigator.sendBeacon(RUTA, datos)) { return; }
        // `keepalive` cumple el mismo papel que el beacon si este no está disponible.
        fetch(RUTA, { method: 'POST', body: datos, keepalive: true }).catch(() => {});
    } catch {
        // Un puntaje perdido no vale interrumpir la fiesta.
    }
}

/** Anota a varios de una vez, al cerrar la partida. */
export function anotarTodos(jugadores) {
    for (const j of jugadores || []) { anotar(j?.name, j?.score); }
}
