// Cómo se nombra el evento en el Álbum Recuerdo.
//
// El álbum estaba escrito entero para cumpleaños y decía "¡Gracias por venir a
// la fiesta de Amanda!" en el cierre de un baby shower. Amanda todavía no
// nace: no hubo ninguna fiesta suya, y la frase suena a error del que armó el
// álbum, no del software.
//
// Es el mismo problema que `eventoFraseA()` ya resolvió en el kiosco
// (`src/App.jsx`), y se resuelve igual: el vocabulario se decide UNA vez a
// partir del tipo de evento y lo consumen todas las pantallas. Repartir el
// condicional por cada texto es cómo se llega a que uno quede sin arreglar.
//
// El tipo llega en `event.type` desde `album-api.php`. Si no llega —un álbum
// servido por una versión anterior de la API— cae a cumpleaños, que es el
// comportamiento que había antes y el caso mayoritario.

const BABY_SHOWER = 'baby_shower'

export function esBabyShower(evento) {
  return String(evento?.type || '') === BABY_SHOWER
}

/**
 * "al baby shower de Amanda" / "a la fiesta de Amanda".
 *
 * El artículo cambia con la preposición —"al" contra "a la"— así que la frase
 * se arma entera acá en vez de concatenar un nombre de evento suelto afuera.
 * Sin nombre queda "al baby shower" / "a la fiesta", que sigue siendo español
 * correcto y no deja un "de" colgando.
 */
export function fraseA(evento) {
  const nombre = String(evento?.name || '').trim()
  if (esBabyShower(evento)) {
    return nombre ? `al baby shower de ${nombre}` : 'al baby shower'
  }
  return nombre ? `a la fiesta de ${nombre}` : 'a la fiesta'
}

/** "de esta fiesta" / "de este baby shower", para hablar del evento en sí. */
export function esteEvento(evento) {
  return esBabyShower(evento) ? 'este baby shower' : 'esta fiesta'
}

/** El sustantivo suelto: "el baby shower" / "la fiesta". */
export function elEvento(evento) {
  return esBabyShower(evento) ? 'el baby shower' : 'la fiesta'
}

/** Título de la pestaña: "Álbum del baby shower de X" / "de la fiesta de X". */
export function tituloAlbum(evento) {
  const nombre = String(evento?.name || '').trim()
  if (!nombre) return 'Álbum Recuerdo'
  return esBabyShower(evento)
    ? `Álbum del baby shower de ${nombre}`
    : `Álbum de la fiesta de ${nombre}`
}
