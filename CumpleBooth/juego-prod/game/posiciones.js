// Manda el resultado de la aventura a la tabla de posiciones de la fiesta.
//
// Este juego no es por turnos como el Festival: es una aventura de un jugador con
// objetivos. Su "puntaje" es cuanto alcanzo a completar, no una cuenta de puntos, asi que
// se calcula aca a partir del progreso guardado. La escala no importa para la tabla
// general: cada juego reparte su propio podio y solo se comparan las medallas.
//
// Nada de esto puede arruinar una partida. Si el servidor no responde, si no hay red o si
// falta la fiesta, se pierde el puntaje y el juego sigue igual. La tabla es un adorno; lo
// que el nino esta mirando es el juego.

const RUTA = "../puntajes.php";

/** Cuanto vale cada cosa. Un rescate pesa mas que un copo porque cuesta mas. */
const VALOR = { copo: 10, criatura: 40, cascada: 60, tormenta: 60, castillo: 80, fiesta: 100 };

function parametro(nombre) {
  try {
    return new URLSearchParams(location.search).get(nombre) || "";
  } catch {
    return "";
  }
}

/** El slug de la fiesta, saneado igual que en el servidor. */
function fiesta() {
  return parametro("p").toLowerCase().replace(/[^a-z0-9-]/g, "").slice(0, 60);
}

/**
 * Cual de los juegos es. Lo dice el menu al abrir (`&juego=`), porque el motor solo conoce
 * su tema ("hielo" o "heroes") y un mismo tema del motor sirve a dos tematicas de fiesta
 * distintas: spidey y heroes comparten mundo pero son juegos distintos en la tabla.
 */
function cualJuego() {
  const id = parametro("juego").replace(/[^a-z-]/g, "");
  return ["reino-hielo", "aracnida", "mision"].includes(id) ? id : "";
}

/** Puntaje a partir del progreso guardado. */
export function puntajeDe(save, copoTotal) {
  if (!save) return 0;
  const copos = Array.isArray(save.copos) ? save.copos.length : 0;
  const criaturas = Array.isArray(save.criaturas) ? save.criaturas.length : 0;
  let p = copos * VALOR.copo + criaturas * VALOR.criatura;
  if (save.cascada) p += VALOR.cascada;
  if (save.tormenta) p += VALOR.tormenta;
  if (save.fiesta) p += VALOR.fiesta;
  // Terminar la aventura completa vale un extra: es la diferencia entre pasear y lograrlo.
  if (copoTotal && copos >= copoTotal && criaturas >= 3) p += VALOR.castillo;
  return Math.max(0, Math.min(999999, Math.round(p)));
}

/**
 * Anota el resultado. Se llama al terminar la aventura.
 *
 * Usa `sendBeacon` porque el final del juego coincide con la pantalla de fiesta y con que
 * el nino le pase la tablet a otro: un `fetch` normal ahi se cancela a mitad de vuelo.
 */
export function anotarResultado(save, copoTotal) {
  const p = fiesta();
  const juego = cualJuego();
  const jugador = String(save?.nombre || "").trim().slice(0, 40);
  if (!p || !juego || !jugador) return;

  try {
    const datos = new FormData();
    datos.append("p", p);
    datos.append("juego", juego);
    datos.append("jugador", jugador);
    datos.append("puntaje", String(puntajeDe(save, copoTotal)));

    if (navigator.sendBeacon && navigator.sendBeacon(RUTA, datos)) return;
    fetch(RUTA, { method: "POST", body: datos, keepalive: true }).catch(() => {});
  } catch {
    // Un puntaje perdido no vale interrumpir la fiesta.
  }
}
