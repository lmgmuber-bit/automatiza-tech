/**
 * Mazos de los juegos del animador para cumpleaños de adulto (AT-CUMPLECLICK-026).
 *
 * Catálogo propuesto por Codex (paquete del piloto, 11-oct-2026) y aprobado por Luis («haz
 * todas»). Genérico: el nombre y la edad de quien cumple entran por `{nombre}` y `{edad}`.
 * Nada de datos personales: las respuestas del Tribunal se confirman antes de la fiesta y las
 * tiene el animador (la tablet solo guarda que dijo «las tengo»), nunca quedan escritas acá.
 * El texto es neutro: no supone el género de quien está de cumpleaños.
 *
 * Tono = nivel mínimo para que aparezca: coqueto < picante < sinverguenza.
 * Puntos: 'acierto' (1 por acierto), 'rubrica' (0 a 3: creatividad, consigna y gracia; nunca el
 * cuerpo ni el atractivo), 'lanzamiento' (1 a 3 según la zona), 'ninguno'.
 * Ninguna prueba exige contacto, besos, alcohol, desnudez ni contar cosas íntimas, y «Paso esta»
 * siempre está a mano.
 */

const c = (id, tono, texto, respuesta = '') => ({ id, tono, texto, respuesta })

export const PASO_ESTA = 'Tú decides: juega o pasa esta.'

export const JUEGOS = [
  {
    id: 'tribunal',
    nombre: 'Tribunal de la coquetería',
    emoji: '⚖️',
    segundos: 20,
    puntos: 'acierto',
    instrucciones: '¿Quién conoce mejor los gustos de {nombre}? Cada equipo tiene 20 segundos para responder; después el animador revela la respuesta confirmada. Un punto por acierto.',
    premio: 'Cómplice que más sabe',
    nota: 'Las respuestas las tiene el animador, confirmadas antes de la fiesta: no están en la tablet. Pregunta sin respuesta confirmada: «Paso esta».',
    // Sin al menos seis respuestas confirmadas antes de la fiesta no se juega: se ofrece La coartada perfecta.
    // La tablet solo guarda el sí del animador, nunca las respuestas.
    preparacion: {
      minimo: 6,
      confirmar: 'Tengo las respuestas de {nombre} preparadas (mínimo 6)',
      explicacion: 'Para el Tribunal, el animador necesita al menos seis respuestas confirmadas antes de la fiesta por quien está de cumpleaños o por quien organiza. Las respuestas no se escriben en la tablet.',
      alternativa: 'coartada',
    },
    cartas: [
      c('tr1', 'coqueto', '¿Salsa o merengue?'),
      c('tr2', 'coqueto', '¿Qué canción hace que {nombre} salte primero a la pista?'),
      c('tr3', 'coqueto', '¿Qué frase dice cuando está de broma?'),
      c('tr4', 'coqueto', '¿Qué accesorio no falta cuando sale?'),
      c('tr5', 'coqueto', '¿Qué comida elegiría para celebrar?'),
      c('tr6', 'coqueto', '¿Cuál es su paso de baile favorito?'),
      c('tr7', 'coqueto', '¿Qué plan elige para una noche con amigos?'),
      c('tr8', 'coqueto', '¿Cuál es su foto favorita de la invitación?'),
    ],
  },
  {
    id: 'piropos',
    nombre: 'Piropos bajo arresto',
    emoji: '🚨',
    segundos: 20,
    puntos: 'rubrica',
    instrucciones: 'Con estas dos palabras, un piropo de máximo 15 segundos, a quien quiera recibirlo o a un personaje imaginario. El tribunal absuelve con 0 a 3 puntos.',
    premio: 'Piropo del año',
    cartas: [
      c('pi1', 'picante', 'Wi-Fi + sonrisa'),
      c('pi2', 'picante', 'Salsa + incendio'),
      c('pi3', 'picante', 'Dorado + terremoto'),
      c('pi4', 'picante', 'Pastel + travesura'),
      c('pi5', 'picante', 'Ventilador + mirada'),
      c('pi6', 'picante', 'Semáforo + beso'),
      c('pi7', 'picante', 'Café + peligro'),
      c('pi8', 'picante', 'Tacón + coartada'),
    ],
  },
  {
    id: 'doble-sentido',
    nombre: 'Doble sentido, respuesta inocente',
    emoji: '😏',
    segundos: 20,
    puntos: 'acierto',
    instrucciones: 'Suena atrevido, pero la respuesta es inocente. 20 segundos y una respuesta por equipo. Si otra respuesta tiene sentido, el animador la puede aceptar.',
    premio: 'Mente inocente… supuestamente',
    cartas: [
      c('ds01', 'picante', 'Cuanto más me frotas, más espuma hago.', 'El jabón'),
      c('ds02', 'picante', 'Me metes en un agujero, me das una vuelta y te abro el camino.', 'La llave'),
      c('ds03', 'picante', 'Entro seco, salgo mojado y dejo la taza perfumada.', 'La bolsita de té'),
      c('ds04', 'picante', 'Me aprietas por la mitad y me sale la pasta por la punta.', 'El tubo de pasta de dientes'),
      c('ds05', 'picante', 'Me agitas, me destapas y puedo mojar a todos.', 'Una bebida con gas'),
      c('ds06', 'coqueto', 'Tengo dientes, paso por tu cabeza y dejo tu pelo impecable.', 'El peine'),
      c('ds07', 'picante', 'Subo y bajo entre tus manos para que nada se escape.', 'El cierre de una bolsa'),
      c('ds08', 'coqueto', 'Me soplas, me pongo grande y aparezco en todas las fiestas.', 'El globo'),
      c('ds09', 'picante', 'Me estiras para que llegue y luego vuelvo a enrollarme.', 'La huincha de medir'),
      c('ds10', 'sinverguenza', 'Tengo cabeza, no pienso y me hunden a golpes.', 'El clavo'),
      c('ds11', 'sinverguenza', 'Cuanto más caliente estoy, más rápido derrito lo que me pones encima.', 'La sartén'),
      c('ds12', 'sinverguenza', 'Me abrazas toda la noche y nunca te pido explicaciones.', 'La almohada'),
    ],
  },
  {
    id: 'swing',
    nombre: 'La dueña y el dueño del swing',
    emoji: '💃',
    segundos: 20,
    puntos: 'rubrica',
    instrucciones: 'Música del DJ y 20 segundos por turno, solos, en dúo o en equipo. Se puede bailar sentado: brazos, hombros y cara. Se puntúa ritmo, creatividad y consigna, nunca el cuerpo.',
    premio: 'Dueña o dueño del swing · Rey o reina del merengue',
    cartas: [
      c('sw1', 'coqueto', 'Baila como si acabaras de conquistar la pista.'),
      c('sw2', 'coqueto', 'Salsa con cara de «yo no fui».'),
      c('sw3', 'coqueto', 'Merengue de alguien que llegó tarde y quiere disimular.'),
      c('sw4', 'coqueto', 'El paso de la victoria por cumplir {edad}.'),
      c('sw5', 'picante', 'Baila como si la pista fuera solo tuya.'),
      c('sw6', 'picante', 'Cumbia en cámara lenta, con mirada de telenovela.'),
    ],
  },
  {
    id: 'yo-nunca',
    nombre: 'Yo nunca: edición cómplices',
    emoji: '🙈',
    segundos: 0,
    puntos: 'ninguno',
    instrucciones: 'Todos sentados. Levanta «Culpable» o «Inocente». No hay que contar nada, no hay que beber, y siempre se puede pasar. La tablet no guarda quién levantó qué.',
    premio: 'Aquí nadie es santo',
    cartas: [
      c('yn1', 'picante', 'Yo nunca he practicado una mirada coqueta frente al espejo.'),
      c('yn2', 'picante', 'Yo nunca he borrado un mensaje por miedo a enviarlo.'),
      c('yn3', 'coqueto', 'Yo nunca he bailado solo cuando creía que nadie miraba.'),
      c('yn4', 'picante', 'Yo nunca he fingido entender una indirecta que no entendí.'),
      c('yn5', 'picante', 'Yo nunca he revisado dos veces quién vio una historia.'),
      c('yn6', 'sinverguenza', 'Yo nunca he usado «me equivoqué de chat» como excusa.'),
      c('yn7', 'coqueto', 'Yo nunca me he demorado más eligiendo ropa que llegando a la fiesta.'),
      c('yn8', 'coqueto', 'Yo nunca he dicho «una canción más» y me quedé otra hora.'),
    ],
  },
  {
    id: 'coartada',
    nombre: 'La coartada perfecta',
    emoji: '🕵️',
    segundos: 30,
    puntos: 'rubrica',
    instrucciones: 'Una situación inventada y tres objetos: 30 segundos para preparar la defensa y 30 para convencer al tribunal. Nada de hechos reales de nadie.',
    premio: 'Abogado de lo indefendible',
    cartas: [
      c('co1', 'picante', 'Te encontraron junto a la torta antes de soplar las velas: explica el labial, la cucharita y el abanico.'),
      c('co2', 'picante', 'Te vieron saliendo de la pista con una corona, un zapato y un número de teléfono ficticio.'),
      c('co3', 'sinverguenza', 'Sonó una serenata y tú escondiste un ramo, una linterna y un colador.'),
      c('co4', 'picante', 'Apareciste en la foto de todos con lentes de sol, una rosa y la servilleta de otro.'),
    ],
  },
  {
    id: 'caballeros',
    nombre: 'Caballeros bajo sospecha',
    emoji: '🎩',
    segundos: 20,
    puntos: 'rubrica',
    instrucciones: 'Para quien quiera participar (pensado para ellos, abierto a todos). Elige una actuación de 20 segundos. Sin desvestirse ni besar a nadie.',
    premio: 'Galán sin antecedentes',
    cartas: [
      c('ca1', 'picante', 'Preséntate como galán de telenovela.'),
      c('ca2', 'picante', 'Deja una nota de voz romántica a una empanada imaginaria.'),
      c('ca3', 'picante', 'Pide permiso para entrar a una pista llena de divas.'),
      c('ca4', 'sinverguenza', 'Declárale tu amor a la torta de {nombre}, de rodillas.'),
    ],
  },
  {
    id: 'divas',
    nombre: 'Divas con prontuario',
    emoji: '👠',
    segundos: 15,
    puntos: 'rubrica',
    instrucciones: 'Para quien quiera participar (pensado para ellas, abierto a todos). Desfile de 15 segundos con una frase de teatro, o entrada de diva sentada con manos y cara. No es concurso de belleza.',
    premio: 'Culpable y con encanto',
    cartas: [
      c('di1', 'picante', 'Llegaste tarde, pero se nota que la fiesta te esperaba.'),
      c('di2', 'coqueto', 'Di «yo no fui» como protagonista de telenovela.'),
      c('di3', 'picante', 'Lanza tu comercial de perfume imaginario: «Coquetería {edad}».'),
      c('di4', 'coqueto', 'Recibe un premio por exceso de encanto.'),
    ],
  },
  {
    id: 'indirectas',
    nombre: 'Duelo de indirectas',
    emoji: '🤺',
    segundos: 15,
    puntos: 'rubrica',
    instrucciones: 'Un equipo responde con doble sentido simpático en 15 segundos y el otro replica. Tres intercambios por ronda. Picardía sí, ataques personales no.',
    premio: 'Lengua más rápida del salón',
    cartas: [
      c('in1', 'picante', '¿Tú vienes siempre por aquí?'),
      c('in2', 'picante', 'Esta noche hace calor.'),
      c('in3', 'picante', 'Creo que perdí mi número.'),
      c('in4', 'coqueto', '¿Me guardas un baile?'),
      c('in5', 'sinverguenza', 'Dicen que aquí hay gente peligrosa.'),
      c('in6', 'coqueto', 'Yo solo vine por la torta.'),
    ],
  },
  {
    id: 'bolso',
    nombre: 'El bolso del delito',
    emoji: '👜',
    segundos: 20,
    puntos: 'acierto',
    instrucciones: 'El primer equipo que muestre el objeto gana un punto. Vale un objeto propio que quieran mostrar o uno de la mesa de accesorios: no se revisan bolsos ajenos ni teléfonos.',
    premio: 'Cómplices preparados para todo',
    cartas: [
      c('bo1', 'coqueto', 'Un labial'), c('bo2', 'coqueto', 'Un pañuelo'), c('bo3', 'coqueto', 'Un espejo chico'),
      c('bo4', 'coqueto', 'Un peine'), c('bo5', 'coqueto', 'Una moneda'), c('bo6', 'coqueto', 'Algo dorado'),
      c('bo7', 'coqueto', 'Un abanico'), c('bo8', 'coqueto', 'Un dulce envuelto'), c('bo9', 'coqueto', 'Una servilleta con un corazón dibujado'),
    ],
  },
  {
    id: 'mimica',
    nombre: 'Adivina la frase con mímica',
    emoji: '🤫',
    segundos: 30,
    puntos: 'acierto',
    privado: true,
    instrucciones: 'Sin hablar, 30 segundos. Solo quien actúa mira la frase (mantén apretado «Ver la frase»). Vale adivinar la idea, no la frase exacta.',
    premio: 'Mímica de oro',
    cartas: [
      c('mi1', 'picante', 'Me hago de rogar'), c('mi2', 'coqueto', 'Amor a primera vista'),
      c('mi3', 'picante', 'Se me subió la coquetería'), c('mi4', 'coqueto', 'Yo no fui'),
      c('mi5', 'picante', 'Me dejó con ganas de bailar'), c('mi6', 'coqueto', '{edad} y todavía doy guerra'),
      c('mi7', 'coqueto', 'Vine por la torta'), c('mi8', 'picante', 'Culpable, pero con encanto'),
    ],
  },
  {
    id: 'beso-al-blanco',
    nombre: 'Beso al blanco',
    emoji: '🎯',
    segundos: 0,
    puntos: 'lanzamiento',
    sinCartas: true,
    instrucciones: 'Diana de cartón con corazones y fichas con forma de beso, lanzadas con la mano desde una línea. Tres lanzamientos: 1, 2 o 3 puntos según la zona. La tablet solo lleva el marcador.',
    premio: 'Puntería de conquistador',
    cartas: [],
  },
  {
    id: 'globo',
    nombre: 'El globo cómplice',
    emoji: '🎈',
    segundos: 60,
    puntos: 'acierto',
    instrucciones: 'En duplas, llevar un globo hasta la marca sujetándolo entre las espaldas, sin correr; el contacto es voluntario. Sin contacto: llevarlo con dos abanicos sobre la mesa. Gana el mejor tiempo.',
    premio: 'Socios del delito',
    cartas: [
      c('gl1', 'picante', 'Globo entre las espaldas, hasta la marca.'),
      c('gl2', 'coqueto', 'Globo con dos abanicos, sobre la mesa.'),
    ],
  },
  {
    id: 'expediente',
    nombre: 'Expediente «Se busca»',
    emoji: '📁',
    segundos: 60,
    puntos: 'ninguno',
    instrucciones: 'Cierre. Cada equipo inventa un cargo divertido y una recompensa para el expediente de {nombre}. Se leen tres o cuatro y quien está de cumpleaños elige el ganador. Después, foto de todos los cómplices.',
    premio: 'Cómplice de honor',
    cartas: [
      c('ex1', 'coqueto', 'Se busca por robarse la pista. Recompensa: el primer baile.'),
      c('ex2', 'coqueto', 'Se busca por exceso de coquetería. Recompensa: ¿?'),
      c('ex3', 'coqueto', 'Se busca por cumplir {edad} y seguir dando guerra. Recompensa: ¿?'),
    ],
  },
]

/** Las preguntas atrevidas para el nivel Sinvergüenza: hipótesis, nunca confesiones. */
JUEGOS.push({
  id: 'atrevidas',
  nombre: 'Preguntas sin vergüenza',
  emoji: '🌶️',
  segundos: 20,
  puntos: 'rubrica',
  instrucciones: 'Una pregunta hipotética para quien quiera responder. No se pregunta por parejas, mensajes ni experiencias de nadie. «Paso» siempre vale.',
  premio: 'Sin vergüenza y con gracia',
  cartas: [
    c('at01', 'sinverguenza', '¿Qué conquista más: una mirada descarada o un baile bien pegado, si los dos quieren?'),
    c('at02', 'sinverguenza', '¿Beso inolvidable o conversación hasta el amanecer?'),
    c('at03', 'sinverguenza', '¿Piropo fino o indirecta que se entienda desde la otra mesa?'),
    c('at04', 'sinverguenza', '¿Qué es más sospechoso: «yo casi no bailo» o «a mí me da vergüenza»?'),
    c('at05', 'sinverguenza', 'Inventa el nombre de tu perfume para causar problemas esta noche.'),
    c('at06', 'sinverguenza', '¿Qué excusa usaría un galán de telenovela para pedir un segundo baile?'),
    c('at07', 'sinverguenza', 'Completa: «A esta edad yo ya no persigo; yo…».'),
    c('at08', 'sinverguenza', 'Completa: «No estoy difícil, estoy…».'),
    c('at09', 'sinverguenza', 'Vende un abanico como el accesorio más seductor del mundo.'),
    c('at10', 'sinverguenza', 'Di «mañana madrugo» de una forma que nadie te crea.'),
    c('at11', 'sinverguenza', '¿Cuál sería tu multa por exceso de coquetería?'),
    c('at12', 'sinverguenza', 'Anuncia tu llegada a la pista como si fueras una celebridad buscada.'),
  ],
})

export const NIVEL_TEXTO = {
  coqueto: { nombre: 'Coqueto', detalle: 'Piropo y humor ligero.' },
  picante: { nombre: 'Picante', detalle: 'Doble sentido y baile.' },
  sinverguenza: { nombre: 'Sinvergüenza', detalle: 'Preguntas atrevidas y teatro, sin nada explícito.' },
}

export const EQUIPOS_SUGERIDOS = ['Caballeros sospechosos', 'Cómplices peligrosas', 'Los que todavía dan guerra', 'Culpables y encantados']
