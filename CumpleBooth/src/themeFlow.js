function optionalAsset(value) {
  if (typeof value !== 'string') return null
  const normalized = value.trim()
  return normalized || null
}

/**
 * Resuelve el orden posterior a la ruleta sin acoplar App.jsx a un tema.
 * Si la API publica una experiencia de sesión fotográfica, se reproduce
 * antes del saludo individual y ese saludo termina directamente en cámara.
 * Los temas anteriores conservan su transición 3D actual.
 *
 * `photoSession.characters` (opcional) restringe el pase de artista a esos
 * personajes: solo ellos pasan por la alfombra; el resto va directo a su
 * video de saludo. Sin la lista, el pase aplica a toda la temática (compat).
 */
/**
 * Mini-juego opcional por temática, entre el saludo del personaje y la cámara.
 * Contrato en themes.json:  "game": { "kind": "copos", "seconds": 15 }
 * Sin `game`, o con kind desconocido, el flujo queda exactamente como antes:
 * ninguna temática existente cambia de comportamiento.
 */
// Dos juegos de "armar", distintos y coexistentes (Luis, 2026-07-25: "tienen
// que ser los 2 juegos, el anterior y este nuevo"):
//  - 'armar-muneco' → arrastrar piezas sueltas a su silueta sobre un fondo.
//  - 'fichas'        → intercambiar fichas de una imagen en una grilla.
// Cuando un personaje trae un ARRAY de juegos, el orden importa: el primero
// siempre se juega, y por cada juego adicional se OFRECE como bonus con
// botón de Sí/Omitir (Luis, 2026-07-25: "de 2do si el usuario quiere... si
// no debe estar el botón de omitir") — ya no se sortea al azar.
const GAME_KINDS = ['copos', 'armar-muneco', 'fichas', 'ritmo', 'escudo', 'mundo3d', 'concierto3d']

const GAME_LABEL_POR_DEFECTO = {
  copos: '¡Atrapa los copos!',
  'armar-muneco': '¡Ármame!',
  fichas: '¡Arma la imagen!',
  ritmo: '¡Sigue el ritmo!',
  escudo: '¡Activa el escudo!',
  mundo3d: '¡Misión 3D Full!',
  concierto3d: '¡El Show 3D!',
}

function resolveGame(rawGame) {
  if (!rawGame || typeof rawGame !== 'object') return null
  const kind = optionalAsset(rawGame.kind)
  if (!kind || !GAME_KINDS.includes(kind)) return null
  const seconds = Number(rawGame.seconds)
  const config = {
    kind,
    // Acotado: un juego muy largo atasca la fila de invitados en la fiesta.
    seconds: Number.isFinite(seconds) ? Math.min(30, Math.max(5, Math.round(seconds))) : 15,
    label: optionalAsset(rawGame.label) || GAME_LABEL_POR_DEFECTO[kind] || '¡A jugar!',
  }
  // BUG corregido 2026-07-25: este objeto se armaba a mano y solo copiaba
  // kind/seconds/label — la imagen (y en 'fichas' el tamaño de grilla) que
  // la API SÍ publica (cb_sanitize_theme_game en lib.php) se descartaban en
  // el camino. El juego veía config.image === undefined y caía a la
  // pantalla de emergencia, así que nunca aparecía pese a estar bien
  // configurado de punta a punta.
  if (kind === 'armar-muneco' || kind === 'escudo') {
    // Fondo del escenario de arrastre — sin cols/filas, siempre son las
    // mismas 6 piezas fijas del muñeco (ver MUNECO_PARTES en App.jsx).
    const image = optionalAsset(rawGame.image)
    if (image) config.image = image
  } else if (kind === 'fichas') {
    const image = optionalAsset(rawGame.image)
    if (image) config.image = image
    const cols = Number(rawGame.cols)
    const filas = Number(rawGame.filas)
    config.cols = Number.isFinite(cols) ? Math.min(4, Math.max(2, Math.round(cols))) : 3
    config.filas = Number.isFinite(filas) ? Math.min(4, Math.max(2, Math.round(filas))) : 3
  } else if (kind === 'ritmo') {
    const image = optionalAsset(rawGame.image)
    if (image) config.image = image
    const lanes = Number(rawGame.lanes)
    config.lanes = Number.isFinite(lanes) ? Math.min(5, Math.max(3, Math.round(lanes))) : 4
  } else if (kind === 'copos') {
    const emojis = Array.isArray(rawGame.emojis)
      ? rawGame.emojis.map((emoji) => String(emoji || '').trim()).filter(Boolean).slice(0, 8)
      : []
    if (emojis.length) config.emojis = emojis
    // Fondo OPCIONAL, igual que en el resto de los juegos. Sin `image` el
    // juego queda exactamente como antes (el degradado de la temática), así
    // que ninguna temática existente cambia por esto. El backend ya lo saneaba
    // de forma genérica para cualquier kind; lo único que faltaba era que el
    // frontend dejara de descartarlo acá.
    const image = optionalAsset(rawGame.image)
    if (image) config.image = image
  } else if (kind === 'mundo3d') {
    const world = optionalAsset(rawGame.world)
    const allowedWorlds = [
      'turbo-track',
      'puppy-park',
      'tropical-wave',
      'ice-bridge',
      'neon-stage',
      'hero-city',
    ]
    config.world = allowedWorlds.includes(world) ? world : 'puppy-park'
    const image = optionalAsset(rawGame.image)
    if (image) config.image = image
    const targetScore = Number(rawGame.targetScore)
    config.targetScore = Number.isFinite(targetScore)
      ? Math.min(30, Math.max(5, Math.round(targetScore)))
      : 12
    config.collectible = optionalAsset(rawGame.collectible) || '⭐'
    config.hazard = optionalAsset(rawGame.hazard) || '💥'
  } else if (kind === 'concierto3d') {
    // El Show solo elige VESTUARIO: `stage` (paleta y nombre del escenario) y
    // una foto de fondo opcional. El chart, el tempo y la dificultad NO son
    // configurables a propósito: son los mismos para todos los invitados de
    // la fiesta y para todas las temáticas, para que los puntajes se puedan
    // comparar. Sin `stage` válido cae a 'neon-arena'.
    const stage = optionalAsset(rawGame.stage)
    const allowedStages = [
      'neon-arena',
      'ice-gala',
      'beach-luau',
      'podium-night',
      'backyard-fiesta',
      'rooftop-city',
      'comic-city',
    ]
    config.stage = allowedStages.includes(stage) ? stage : 'neon-arena'
    const image = optionalAsset(rawGame.image)
    if (image) config.image = image
  }
  return config
}

export function resolveThemeFlow(theme) {
  // Juego de la temática (aplica a todos) y juegos POR PERSONAJE, que tienen
  // prioridad: p.ej. si sale el muñeco de nieve, el niño lo arma en vez de
  // atrapar copos. Se indexa en minúsculas porque el nombre viene del JSON.
  //
  // Un personaje puede traer VARIOS juegos (Luis, 2026-07-25: "tienen que
  // ser los 2 juegos, el anterior y este nuevo") — themes.json publica un
  // array en vez de un solo objeto, y acá se guarda la lista completa. Cuál
  // de los dos toca cada vez se decide al azar en gameFor(), igual de
  // simple que la alternancia de WELCOME_VIDEO_ALT en App.jsx pero sin
  // depender de sessionStorage: perder la elección exacta entre partida y
  // partida no importa para un minijuego.
  const themeGame = resolveGame(theme?.game)
  // El juego 3D premium solo llega en el payload cuando la fiesta contrató
  // service_plan=full. El backend es el gate de seguridad; aquí simplemente
  // se añade como último bonus de la cadena, igual para todos los personajes.
  const fullGame = resolveGame(theme?.fullGame)
  const characterGames = new Map()
  for (const p of theme?.personajes || []) {
    const raw = p?.game
    const lista = (Array.isArray(raw) ? raw : [raw]).map(resolveGame).filter(Boolean)
    const name = String(p?.name || '').trim().toLowerCase()
    if (lista.length && name) characterGames.set(name, lista)
  }

  // Lista completa y ORDENADA de juegos de un personaje (o el de la temática
  // si no tiene uno propio). App.jsx la usa para jugar el primero y ofrecer
  // los siguientes como bonus opcional, uno a la vez.
  const gamesFor = (characterName) => {
    const key = String(characterName || '').trim().toLowerCase()
    const lista = characterGames.get(key)
    const base = lista && lista.length ? lista : (themeGame ? [themeGame] : [])
    if (!fullGame) return base
    // Rayo abre El Show primero; para el resto, Full queda como bonus final.
    if (starVideo && key === starCharacterKey) return [fullGame, ...base]
    return [...base, fullGame]
  }

  // Compat: el primer (y antes único) juego de la lista.
  const gameFor = (characterName) => gamesFor(characterName)[0] || null

  const game = themeGame
  const hasGame = Boolean(themeGame) || characterGames.size > 0 || Boolean(fullGame)
  const starVideo = optionalAsset(theme?.videoEstrella)
  // La estrella es el único personaje cuya cadena propia tiene más juegos
  // que las demás. No se infiere nada si hay empate: así un JSON incompleto
  // jamás reproduce el clip para el personaje equivocado.
  const gameCounts = (theme?.personajes || []).map((p) => {
    const key = String(p?.name || '').trim().toLowerCase()
    const ownGames = characterGames.get(key)
    return { key, count: ownGames?.length || (themeGame ? 1 : 0) }
  }).filter(({ key }) => key)
  const counts = gameCounts.map(({ count }) => count)
  const maxGameCount = counts.length ? Math.max(...counts) : 0
  const minGameCount = counts.length ? Math.min(...counts) : 0
  const starCandidates = gameCounts.filter(({ count }) => count === maxGameCount)
  const starCharacterKey = maxGameCount > minGameCount && starCandidates.length === 1
    ? starCandidates[0].key
    : null
  const starVideoAppliesTo = (characterName) => Boolean(
    starVideo
    && starCharacterKey
    && String(characterName || '').trim().toLowerCase() === starCharacterKey
  )
  const photoSessionVideo = optionalAsset(theme?.photoSession?.video)
  const photoSessionPoster = optionalAsset(theme?.photoSession?.poster)
  const photoSessionTeaser = optionalAsset(theme?.photoSession?.teaser)
  const photoSessionTeaserVideo = optionalAsset(theme?.photoSession?.teaserVideo)
  const hasPhotoSession = Boolean(photoSessionVideo)
  const sessionCharacterNames = (Array.isArray(theme?.photoSession?.characters)
    ? theme.photoSession.characters
    : [])
    .map((name) => String(name).trim())
    .filter(Boolean)
  const sessionCharacters = sessionCharacterNames.map((name) => name.toLowerCase())
  // Rótulo del cuadro: si el tema define uno explícito (p.ej. la imagen
  // muestra a más personajes que los del pase de artista), se usa ese texto;
  // si no, cae al listado de personajes con pase, unidos por "y".
  const teaserLabel = optionalAsset(theme?.photoSession?.teaserLabel) || sessionCharacterNames.join(' y ')

  const sessionAppliesTo = (characterName) => {
    if (!hasPhotoSession) return false
    if (sessionCharacters.length === 0) return true
    return sessionCharacters.includes(String(characterName || '').trim().toLowerCase())
  }

  // Transición 3D previa a cámara. Es una PISTA DE JUGUETE: nació con la
  // temática de Carreras y encaja ahí, pero en un palacio de hielo aparecía
  // una carretera sin ninguna relación con el tema. Una temática puede
  // desactivarla con "transition": "none" en themes.json. El valor por
  // defecto conserva el comportamiento histórico de los temas existentes.
  const skipTransition = optionalAsset(theme?.transition) === 'none'

  // Adónde va el invitado una vez terminado (o saltado) el juego: se retoma
  // exactamente el destino que el saludo habría tenido sin juego.
  const afterCharacterWithoutGame = () =>
    hasPhotoSession || skipTransition ? 'capture' : 'transition'

  return {
    photoSessionVideo,
    photoSessionPoster,
    photoSessionTeaser,
    photoSessionTeaserVideo,
    sessionCharacterNames,
    teaserLabel,
    sessionAppliesTo,
    game,
    fullGame,
    starVideo,
    starVideoAppliesTo,
    hasGame,
    gameFor,
    gamesFor,
    afterSpinner: (characterName) =>
      sessionAppliesTo(characterName) ? 'photo-session' : 'video-personaje',
    afterCharacter: (characterName) => {
      // El juego se intercala antes de la cámara; el niño ya conoció a su
      // personaje y todavía no posa. Es saltable desde la propia pantalla.
      // Se consulta por personaje: un tema puede no tener juego global y aun
      // así darle uno propio a un personaje concreto.
      if (gameFor(characterName)) return 'juego'
      // Mismo destino que tras el juego: una sola regla, así no se puede dar
      // el caso de que jugar cambie si aparece o no la transición 3D.
      return afterCharacterWithoutGame()
    },
    afterGame: afterCharacterWithoutGame,
  }
}
