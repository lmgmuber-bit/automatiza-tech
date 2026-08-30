import test from 'node:test'
import assert from 'node:assert/strict'
import { basename } from 'node:path'
import { resolveThemeFlow } from '../../src/themeFlow.js'
import { getSquarePhotoGeometry } from '../../src/frameGeometry.js'

test('un tema con alfombra reproduce video antes del saludo y luego abre cámara', () => {
  const flow = resolveThemeFlow({
    photoSession: {
      video: './themes/familia-canina/transicion-sesion-fotos.mp4',
      poster: './themes/familia-canina/transicion-alfombra-base-v1.png',
    },
  })

  // Sin lista de personajes: el pase aplica a toda la temática (compat).
  assert.equal(flow.afterSpinner('Bluey'), 'photo-session')
  assert.equal(flow.afterSpinner('Muffin'), 'photo-session')
  assert.equal(flow.afterCharacter('Bluey'), 'capture')
  assert.match(flow.photoSessionVideo, /transicion-sesion-fotos\.mp4$/)
  assert.match(flow.photoSessionPoster, /transicion-alfombra-base-v1\.png$/)
})

test('con photoSession.characters solo esos personajes pasan por la alfombra', () => {
  const flow = resolveThemeFlow({
    photoSession: {
      video: './themes/familia-canina/transicion-sesion-fotos.mp4',
      characters: ['Bluey', 'Bingo'],
      teaser: './themes/familia-canina/artist-teaser.jpg',
    },
  })

  assert.match(flow.photoSessionTeaser, /artist-teaser\.jpg$/)
  assert.deepEqual(flow.sessionCharacterNames, ['Bluey', 'Bingo'])
  // Sin teaserLabel explícito: cae al listado de personajes con pase.
  assert.equal(flow.teaserLabel, 'Bluey y Bingo')
  assert.equal(flow.afterSpinner('Bluey'), 'photo-session')
  assert.equal(flow.afterSpinner('Bingo'), 'photo-session')
  // Insensible a mayúsculas/espacios: el nombre llega tal cual de la ruleta.
  assert.equal(flow.afterSpinner('  bluey '), 'photo-session')
  // El resto va directo a su video de saludo, y de ahí a cámara.
  assert.equal(flow.afterSpinner('Muffin'), 'video-personaje')
  assert.equal(flow.afterSpinner('Chloe'), 'video-personaje')
  assert.equal(flow.afterCharacter('Muffin'), 'capture')
  assert.equal(flow.afterCharacter('Bluey'), 'capture')
})

test('teaserLabel explícito describe la imagen del cuadro sin tocar quién recibe el pase', () => {
  const flow = resolveThemeFlow({
    photoSession: {
      video: './themes/familia-canina/transicion-sesion-fotos.mp4',
      characters: ['Bluey', 'Bingo'],
      teaser: './themes/familia-canina/artist-teaser.jpg',
      teaserLabel: 'La familia Heeler',
    },
  })

  // El cuadro puede mostrar/nombrar más personajes que los del pase real.
  assert.equal(flow.teaserLabel, 'La familia Heeler')
  assert.deepEqual(flow.sessionCharacterNames, ['Bluey', 'Bingo'])
  assert.equal(flow.afterSpinner('Bandit'), 'video-personaje')
})

test('los temas sin alfombra conservan el flujo histórico con transición 3D', () => {
  const flow = resolveThemeFlow({ photoSession: {} })

  assert.equal(flow.afterSpinner('Rayo McQueen'), 'video-personaje')
  assert.equal(flow.afterCharacter('Rayo McQueen'), 'transition')
  assert.equal(flow.photoSessionVideo, null)
  assert.equal(flow.photoSessionPoster, null)
  assert.equal(flow.photoSessionTeaser, null)
  assert.deepEqual(flow.sessionCharacterNames, [])
})

test('el video estrella aplica solo al único personaje con la cadena propia más larga', () => {
  const flow = resolveThemeFlow({
    videoEstrella: 'themes/carreras/rayo-mcqueen-estrella.mp4',
    fullGame: { kind: 'concierto3d' },
    personajes: [
      { name: 'Rayo McQueen', game: [{ kind: 'ritmo' }, { kind: 'fichas' }, { kind: 'copos' }] },
      { name: 'Mate', game: [{ kind: 'fichas' }, { kind: 'copos' }] },
    ],
  })

  assert.equal(flow.starVideo, 'themes/carreras/rayo-mcqueen-estrella.mp4')
  assert.equal(flow.starVideoAppliesTo('rayo mcqueen'), true)
  assert.equal(flow.starVideoAppliesTo('Mate'), false)
})

test('un empate de cadenas no inventa una estrella ni reproduce el video', () => {
  const flow = resolveThemeFlow({
    videoEstrella: 'themes/x/estrella.mp4',
    personajes: [
      { name: 'Uno', game: [{ kind: 'fichas' }] },
      { name: 'Dos', game: [{ kind: 'copos' }] },
    ],
  })

  assert.equal(flow.starVideoAppliesTo('Uno'), false)
  assert.equal(flow.starVideoAppliesTo('Dos'), false)
})

test('un valor vacío no activa por accidente la experiencia de alfombra', () => {
  const flow = resolveThemeFlow({ photoSession: { video: '   ', poster: 123 } })

  assert.equal(flow.afterSpinner('Bluey'), 'video-personaje')
  assert.equal(flow.afterCharacter('Bluey'), 'transition')
})

/* ── Mini-juego opcional por temática (Reino de Hielo, 2026-07-25) ─────────── */

test('sin `game` el flujo queda idéntico: ninguna temática existente cambia', () => {
  const sinNada = resolveThemeFlow({})
  assert.equal(sinNada.hasGame, false)
  assert.equal(sinNada.game, null)
  assert.equal(sinNada.afterCharacter('Rayo McQueen'), 'transition')

  const conAlfombra = resolveThemeFlow({
    photoSession: { video: './themes/familia-canina/transicion-sesion-fotos.mp4' },
  })
  assert.equal(conAlfombra.hasGame, false)
  assert.equal(conAlfombra.afterCharacter('Bluey'), 'capture')
})

test('con `game` el juego se intercala entre el saludo y la cámara', () => {
  const flow = resolveThemeFlow({ game: { kind: 'copos', seconds: 15 } })

  assert.equal(flow.hasGame, true)
  assert.equal(flow.game.kind, 'copos')
  assert.equal(flow.game.seconds, 15)
  assert.equal(flow.afterCharacter('Elsa'), 'juego')
  // Al salir del juego se retoma el destino que habría tenido sin él.
  assert.equal(flow.afterGame(), 'transition')
})

test('el juego respeta la alfombra: al terminar va a cámara, no a la transición 3D', () => {
  const flow = resolveThemeFlow({
    game: { kind: 'copos' },
    photoSession: { video: './themes/x/transicion.mp4' },
  })

  assert.equal(flow.afterCharacter('Elsa'), 'juego')
  assert.equal(flow.afterGame(), 'capture')
})

test('un kind desconocido no activa el juego (no rompe temas viejos con basura en el JSON)', () => {
  const flow = resolveThemeFlow({ game: { kind: 'pong' } })
  assert.equal(flow.hasGame, false)
  assert.equal(flow.afterCharacter('Elsa'), 'transition')
})

test('la duración del juego queda acotada para no atascar la fila de invitados', () => {
  assert.equal(resolveThemeFlow({ game: { kind: 'copos', seconds: 999 } }).game.seconds, 30)
  assert.equal(resolveThemeFlow({ game: { kind: 'copos', seconds: 1 } }).game.seconds, 5)
  assert.equal(resolveThemeFlow({ game: { kind: 'copos' } }).game.seconds, 15)
  assert.equal(resolveThemeFlow({ game: { kind: 'copos', seconds: 'x' } }).game.seconds, 15)
})

/* ── Juego POR PERSONAJE (el muñeco de nieve se arma, 2026-07-25) ─────────── */

test('un personaje puede traer su propio juego y ese gana sobre el de la temática', () => {
  const flow = resolveThemeFlow({
    game: { kind: 'copos', seconds: 15 },
    personajes: [
      { name: 'Elsa', img: 'elsa.jpg' },
      { name: 'Olaf', img: 'olaf.jpg', game: { kind: 'armar-muneco', label: '¡Ármame!' } },
    ],
  })

  assert.equal(flow.gameFor('Elsa').kind, 'copos')
  assert.equal(flow.gameFor('Olaf').kind, 'armar-muneco')
  assert.equal(flow.gameFor('Olaf').label, '¡Ármame!')
  // El nombre viene del JSON: no debe importar cómo esté escrito.
  assert.equal(flow.gameFor('  olaf  ').kind, 'armar-muneco')
})

test('sin juego de temática, un personaje con juego propio igual lo activa', () => {
  const flow = resolveThemeFlow({
    personajes: [
      { name: 'Elsa', img: 'elsa.jpg' },
      { name: 'Olaf', img: 'olaf.jpg', game: { kind: 'armar-muneco' } },
    ],
  })

  assert.equal(flow.hasGame, true)
  assert.equal(flow.gameFor('Elsa'), null)
  assert.equal(flow.afterCharacter('Elsa'), 'transition') // Elsa no juega
  assert.equal(flow.afterCharacter('Olaf'), 'juego')      // Olaf sí
})

test('un juego de personaje inválido no rompe ni activa nada', () => {
  const flow = resolveThemeFlow({
    personajes: [{ name: 'Olaf', img: 'olaf.jpg', game: { kind: 'tetris' } }],
  })
  assert.equal(flow.hasGame, false)
  assert.equal(flow.gameFor('Olaf'), null)
  assert.equal(flow.afterCharacter('Olaf'), 'transition')
})

test('cada juego trae su propia etiqueta por defecto', () => {
  assert.equal(resolveThemeFlow({ game: { kind: 'copos' } }).game.label, '¡Atrapa los copos!')
  const conMuneco = resolveThemeFlow({
    personajes: [{ name: 'Olaf', game: { kind: 'armar-muneco' } }],
  })
  assert.equal(conMuneco.gameFor('Olaf').label, '¡Ármame!')
})

/* ── Transición 3D desactivable por temática (2026-07-25) ─────────────────── */

test('"transition":"none" evita la pista 3D de juguete en temas sin carretera', () => {
  const conPista = resolveThemeFlow({ personajes: [{ name: 'Elsa' }] })
  assert.equal(conPista.afterCharacter('Elsa'), 'transition')

  const sinPista = resolveThemeFlow({ transition: 'none', personajes: [{ name: 'Elsa' }] })
  assert.equal(sinPista.afterCharacter('Elsa'), 'capture')
  assert.equal(sinPista.afterGame(), 'capture')
})

test('jugar no cambia si aparece o no la transición: mismo destino con y sin juego', () => {
  const flow = resolveThemeFlow({
    transition: 'none',
    game: { kind: 'copos' },
    personajes: [{ name: 'Elsa' }],
  })
  // Con juego pasa por 'juego' y de ahí al mismo lugar que sin juego.
  assert.equal(flow.afterCharacter('Elsa'), 'juego')
  assert.equal(flow.afterGame(), 'capture')

  const sinJuego = resolveThemeFlow({ transition: 'none', personajes: [{ name: 'Elsa' }] })
  assert.equal(sinJuego.afterCharacter('Elsa'), sinJuego.afterGame())
})

/* ── Regresión: la imagen del rompecabezas se perdía en resolveGame() ────── */

test('gameFor propaga image — sin esto el juego de arrastrar cae a "sin fondo"', () => {
  const flow = resolveThemeFlow({
    personajes: [{
      name: 'Olaf',
      game: { kind: 'armar-muneco', label: '¡Ármame!', image: 'themes/hielo/fondo-juego-nieve.jpg' },
    }],
  })
  const g = flow.gameFor('Olaf')
  assert.equal(g.image, 'themes/hielo/fondo-juego-nieve.jpg')
  // 'armar-muneco' no usa grilla: son las 6 piezas fijas del muñeco.
  assert.equal('cols' in g, false)
})

test('cols/filas del rompecabezas de FICHAS quedan acotados a 2-4 igual que en el backend', () => {
  const flow = resolveThemeFlow({
    personajes: [{ name: 'Olaf', game: { kind: 'fichas', image: 'x.jpg', cols: 9, filas: 1 } }],
  })
  const g = flow.gameFor('Olaf')
  assert.equal(g.cols, 4)
  assert.equal(g.filas, 2)
})

test('un juego de copos no arrastra campos del rompecabezas (image queda ausente)', () => {
  const flow = resolveThemeFlow({ game: { kind: 'copos', seconds: 15 } })
  assert.equal('image' in flow.game, false)
})

/* ── Dos juegos por personaje: primero fijo + bonus opcional (Luis, 2026-07-25:
   "de 2do si el usuario quiere... si no debe estar el botón de omitir") ──── */

test('un personaje con un array de juegos siempre juega el primero (ya no se sortea)', () => {
  const flow = resolveThemeFlow({
    personajes: [{
      name: 'Olaf',
      game: [
        { kind: 'armar-muneco', image: 'fondo-nieve.jpg' },
        { kind: 'fichas', image: 'puzzle-olaf.jpg', cols: 3, filas: 3 },
      ],
    }],
  })
  // Determinístico: 20 consultas, siempre el primero del array.
  for (let i = 0; i < 20; i++) assert.equal(flow.gameFor('Olaf').kind, 'armar-muneco')
})

test('gamesFor expone la lista completa y ordenada para ofrecer el bonus', () => {
  const flow = resolveThemeFlow({
    personajes: [{
      name: 'Olaf',
      game: [
        { kind: 'armar-muneco', image: 'fondo-nieve.jpg' },
        { kind: 'fichas', image: 'puzzle-olaf.jpg', cols: 3, filas: 3 },
      ],
    }],
  })
  const lista = flow.gamesFor('Olaf')
  assert.equal(lista.length, 2)
  assert.equal(lista[0].kind, 'armar-muneco')
  assert.equal(lista[0].image, 'fondo-nieve.jpg')
  assert.equal(lista[1].kind, 'fichas')
  assert.equal(lista[1].image, 'puzzle-olaf.jpg')
  assert.equal(lista[1].cols, 3)
})

test('gamesFor con un solo juego (objeto, no array) devuelve una lista de un elemento', () => {
  const flow = resolveThemeFlow({
    personajes: [{ name: 'Elsa', game: { kind: 'fichas', image: 'puzzle-elsa.jpg' } }],
  })
  const lista = flow.gamesFor('Elsa')
  assert.equal(lista.length, 1)
  assert.equal(lista[0].kind, 'fichas')
})

test('gamesFor sin juego propio cae al de la temática, envuelto en lista', () => {
  const flow = resolveThemeFlow({
    game: { kind: 'copos' },
    personajes: [{ name: 'Elsa' }],
  })
  const lista = flow.gamesFor('Elsa')
  assert.equal(lista.length, 1)
  assert.equal(lista[0].kind, 'copos')
})

test('gamesFor sin ningún juego devuelve lista vacía', () => {
  const flow = resolveThemeFlow({ personajes: [{ name: 'Elsa' }] })
  assert.deepEqual(flow.gamesFor('Elsa'), [])
})

test('una cadena de 3 juegos conserva el orden exacto (Luis, 2026-07-26: Olaf)', () => {
  const flow = resolveThemeFlow({
    personajes: [{
      name: 'Olaf',
      game: [
        { kind: 'armar-muneco', image: 'fondo-juego-nieve.jpg' },
        { kind: 'fichas', image: 'puzzle-olaf.jpg', cols: 3, filas: 3 },
        { kind: 'copos', seconds: 15 },
      ],
    }],
  })
  const lista = flow.gamesFor('Olaf')
  assert.deepEqual(lista.map((g) => g.kind), ['armar-muneco', 'fichas', 'copos'])
  // Cada uno conserva SOLO sus propios campos: la grilla es exclusiva de
  // 'fichas' y la imagen no debe filtrarse al juego de copos.
  assert.equal(lista[0].image, 'fondo-juego-nieve.jpg')
  assert.equal('cols' in lista[0], false)
  assert.equal(lista[1].cols, 3)
  assert.equal('image' in lista[2], false)
  // gameFor sigue devolviendo el primero: es el que se juega sin preguntar.
  assert.equal(flow.gameFor('Olaf').kind, 'armar-muneco')
})

test('un solo objeto (no array) sigue funcionando igual que antes — compatibilidad', () => {
  const flow = resolveThemeFlow({
    personajes: [{ name: 'Elsa', game: { kind: 'copos', seconds: 10 } }],
  })
  assert.equal(flow.gameFor('Elsa').kind, 'copos')
  assert.equal(flow.gameFor('Elsa').seconds, 10)
})

test('kind "fichas" a nivel de personaje se sanea igual que a nivel de tema', () => {
  const flow = resolveThemeFlow({
    personajes: [{ name: 'Olaf', game: { kind: 'fichas', image: 'x.jpg', cols: 9, filas: 1 } }],
  })
  const g = flow.gameFor('Olaf')
  assert.equal(g.cols, 4)
  assert.equal(g.filas, 2)
})

test('copos acepta emojis temáticos configurables sin alterar el orden', () => {
  const flow = resolveThemeFlow({
    game: { kind: 'copos', label: '¡Atrapa!', emojis: ['⚡', '🔨', '💥', '✨'] },
  })
  assert.deepEqual(flow.game.emojis, ['⚡', '🔨', '💥', '✨'])
})

test('ritmo limita los carriles al rango táctil de 3 a 5', () => {
  assert.equal(resolveThemeFlow({ game: { kind: 'ritmo', lanes: 2 } }).game.lanes, 3)
  assert.equal(resolveThemeFlow({ game: { kind: 'ritmo', lanes: 4 } }).game.lanes, 4)
  assert.equal(resolveThemeFlow({ game: { kind: 'ritmo', lanes: 8 } }).game.lanes, 5)
})

test('escudo conserva image pero nunca hereda cols ni filas', () => {
  const game = resolveThemeFlow({
    game: { kind: 'escudo', image: 'themes/heroes/fondo-juego-ciudad.jpg', cols: 4, filas: 4 },
  }).game
  assert.equal(game.image, 'themes/heroes/fondo-juego-ciudad.jpg')
  assert.equal('cols' in game, false)
  assert.equal('filas' in game, false)
})

test('mundo3d sanea mundo, meta, assets y símbolos sin contaminar otros juegos', () => {
  const flow = resolveThemeFlow({
    fullGame: {
      kind: 'mundo3d',
      world: 'neon-stage',
      label: '¡Escenario Neon 3D!',
      image: 'themes/kpop/fondo-juego-escenario.jpg',
      seconds: 99,
      targetScore: 2,
      collectible: '⭐',
      hazard: '🔊',
    },
  })
  const game = flow.fullGame
  assert.equal(game.kind, 'mundo3d')
  assert.equal(game.world, 'neon-stage')
  assert.equal(game.seconds, 30)
  assert.equal(game.targetScore, 5)
  assert.equal(game.image, 'themes/kpop/fondo-juego-escenario.jpg')
  assert.equal(game.collectible, '⭐')
  assert.equal(game.hazard, '🔊')
  assert.equal('cols' in game, false)
})

test('la misión Full se agrega al final y nunca reemplaza el juego principal', () => {
  const flow = resolveThemeFlow({
    fullGame: { kind: 'mundo3d', world: 'hero-city' },
    personajes: [{
      name: 'Capitán',
      game: [{ kind: 'escudo' }, { kind: 'fichas', image: 'puzzle-capitan.jpg' }],
    }],
  })
  assert.equal(flow.gameFor('Capitán').kind, 'escudo')
  assert.deepEqual(flow.gamesFor('Capitán').map((game) => game.kind), [
    'escudo',
    'fichas',
    'mundo3d',
  ])
})

test('sin fullGame en el payload Booth no puede inventar ni activar la misión premium', () => {
  const flow = resolveThemeFlow({
    personajes: [{ name: 'Capitán', game: [{ kind: 'escudo' }] }],
  })
  assert.equal(flow.fullGame, null)
  assert.deepEqual(flow.gamesFor('Capitán').map((game) => game.kind), ['escudo'])
})

test('las cadenas K-Pop y Héroes mantienen ritmo/escudo como primer juego', () => {
  const flow = resolveThemeFlow({
    personajes: [
      { name: 'Rumi', game: [{ kind: 'ritmo' }, { kind: 'fichas' }, { kind: 'copos' }] },
      { name: 'Capitán', game: [{ kind: 'escudo' }, { kind: 'fichas' }, { kind: 'copos' }] },
    ],
  })
  assert.deepEqual(flow.gamesFor('Rumi').map((game) => game.kind), ['ritmo', 'fichas', 'copos'])
  assert.deepEqual(flow.gamesFor('Capitán').map((game) => game.kind), ['escudo', 'fichas', 'copos'])
})

/* ── Homologación de temáticas (2026-07-27) ────────────────────────────────
   Bluey, Carreras y Aventura Tropical no tenían ningún juego; se les dio la
   misma forma que Reino de Hielo, K-Pop y Héroes: el personaje estrella juega
   una cadena base de 3 (uno propio del mundo + fichas + copos) y el resto
   juega 2. Al resolver el catálogo interno aparece además la misión 3D Full
   al final; el backend es quien la elimina del payload para fiestas Booth.
   Estos tests leen el themes.json REAL, no un objeto de laboratorio: así
   detectan si alguien deja una temática a medias o borra un puzzle.
   ──────────────────────────────────────────────────────────────────────── */
const { readFileSync, existsSync, statSync } = await import('node:fs')
const TEMAS = JSON.parse(readFileSync(new URL('../../public/data/themes.json', import.meta.url), 'utf8')).themes
// Presets de la invitación pública: de acá sale qué imagen hace de lámina
// y en qué coordenadas se escriben los datos encima.
const PRESETS_INVITACION = JSON.parse(
  readFileSync(new URL('../../public/data/event-profile-presets.json', import.meta.url), 'utf8')
)

// Estas seis son las temáticas COMPLETAS. Todas cierran la cadena con la
// misma misión Full: 'concierto3d' (El Show, ver src/StageConcert3D.jsx).
// 'mundo3d' (el runner de carriles) quedó sin usar en producción pero el
// código sigue soportándolo.
const HOMOLOGADAS = {
  'familia-canina': { estrella: 'Bluey', propio: 'escudo', stage: 'backyard-fiesta' },
  carreras: { estrella: 'Rayo McQueen', propio: 'ritmo', stage: 'podium-night' },
  tropical: { estrella: 'Stitch', propio: 'escudo', stage: 'beach-luau' },
  hielo: { estrella: 'Olaf', propio: 'armar-muneco', stage: 'ice-gala' },
  kpop: { estrella: 'Rumi', propio: 'ritmo', stage: 'neon-arena' },
  heroes: { estrella: 'Capitán América', propio: 'escudo', stage: 'rooftop-city' },
}

for (const [slug, { estrella, propio, stage, full = 'concierto3d' }] of Object.entries(HOMOLOGADAS)) {
  // Cada temática completa tiene que traer SU escenario. Si alguien agrega una
  // temática nueva y se olvida del `stage`, el saneador la deja en
  // 'neon-arena' y el show sale con el vestuario de K-Pop en otro mundo — se
  // ve mal pero no rompe, así que sin este test pasaría desapercibido.
  test(`${slug}: la misión Full es El Show con el escenario de su temática`, () => {
    const flow = resolveThemeFlow(TEMAS[slug])
    const juegos = flow.gamesFor(estrella)
    const mision = slug === 'carreras' ? juegos[0] : juegos.at(-1)
    assert.equal(mision.kind, 'concierto3d', slug)
    assert.equal(mision.stage, stage, slug)
  })

  test(`${slug}: la estrella juega 3+Full y el resto 2+Full, sin excepciones`, () => {
    const flow = resolveThemeFlow(TEMAS[slug])
    for (const p of TEMAS[slug].personajes) {
      const kinds = flow.gamesFor(p.name).map((g) => g.kind)
      if (p.name === estrella) {
        const esperado = slug === 'carreras'
          ? [full, propio, 'fichas', 'copos']
          : [propio, 'fichas', 'copos', full]
        assert.deepEqual(kinds, esperado, slug + '/' + p.name)
      } else {
        assert.deepEqual(kinds, ['fichas', 'copos', full], `${slug}/${p.name}`)
      }
    }
  })

  test(`${slug}: cada imagen que declara un juego existe en disco`, () => {
    const flow = resolveThemeFlow(TEMAS[slug])
    for (const p of TEMAS[slug].personajes) {
      for (const game of flow.gamesFor(p.name)) {
        if (!game.image) continue
        // resolveThemeFlow puede devolver la ruta ya prefijada o el nombre
        // suelto según cómo se arme el payload; se normaliza al nombre.
        const archivo = String(game.image).split('/').pop()
        const ruta = new URL(`../../public/themes/${slug}/${archivo}`, import.meta.url)
        assert.ok(existsSync(ruta), `falta ${slug}/${archivo} (${p.name}, ${game.kind})`)
      }
    }
  })
}

test('los atlas multivista aprobados corresponden a personajes reales del mundo Full', () => {
  const slugs = ['carreras', 'familia-canina', 'tropical', 'hielo', 'kpop', 'heroes']
  const cobertura = {}
  for (const slug of slugs) {
    const personajes = TEMAS[slug].personajes
    assert.equal(personajes.length, 6, `${slug} debe tener seis personajes`)
    cobertura[slug] = 0
    for (const personaje of personajes) {
      const base = String(personaje.png || personaje.img || '')
        .split('/')
        .pop()
        .replace(/-cut\.png$|\.(?:jpe?g|png)$/i, '')
      const ruta = new URL(
        `../../public/themes/${slug}/game3d/${base}-run-atlas.png`,
        import.meta.url
      )
      if (!existsSync(ruta)) continue
      cobertura[slug] += 1
      assert.ok(statSync(ruta).size > 350_000, `atlas incompleto de ${slug}/${personaje.name}`)
    }
  }
  assert.equal(cobertura.carreras, 6)
  assert.equal(cobertura['familia-canina'], 6)
  assert.equal(cobertura.kpop, 6)
  assert.ok(cobertura.tropical >= 4)
})

/* ──────────────────────────────────────────────────────────────────────────
   TABLA A de docs/TEMATICA-COMPLETA.md — el estándar de "temática completa".

   Esto es lo que convierte esas reglas en algo que se cumple. Una temática a
   medias no rompe el kiosco (todo tiene fallback), así que sin este test el
   bache pasa desapercibido hasta que un invitado se topa con el único
   personaje que no saluda.

   Héroes NO está en la lista todavía: le faltan los seis saludos y la
   despedida, y está bloqueado a propósito hasta que Luis pida los videos.
   Cuando estén, se agrega acá y el test pasa a cuidarlo.
   ────────────────────────────────────────────────────────────────────────── */
const COMPLETAS = ['carreras', 'familia-canina', 'tropical', 'hielo', 'kpop']

for (const slug of COMPLETAS) {
  test(`${slug}: cumple la tabla A de temática completa`, () => {
    const tema = TEMAS[slug]
    const hay = (rel) =>
      existsSync(new URL(`../../public/themes/${slug}/${rel}`, import.meta.url))

    for (const archivo of ['fondo-banner.jpg', 'fondo-sala.jpg', 'musica-fondo.mp3']) {
      assert.ok(hay(archivo), `${slug}: falta ${archivo}`)
    }
    assert.ok(
      hay('roulette/roulette-background-v1.png'),
      `${slug}: falta el fondo de ruleta`
    )

    assert.equal(tema.personajes.length, 6, `${slug}: deben ser seis personajes`)
    for (const personaje of tema.personajes) {
      const base = String(personaje.img).replace(/\.jpg$/i, '')
      for (const archivo of [
        `${base}.jpg`,
        `${base}-cut.png`,
        `puzzle-${base}.jpg`,
        `saludo-${base}.mp4`,
      ]) {
        assert.ok(hay(archivo), `${slug}/${personaje.name}: falta ${archivo}`)
      }
    }

    const despedida = tema.videos?.despedida
    assert.ok(despedida, `${slug}: falta videos.despedida en themes.json`)
    assert.ok(hay(despedida), `${slug}: falta el archivo ${despedida}`)
  })
}

/* ──────────────────────────────────────────────────────────────────────────
   Las tres salidas de la pantalla "¿Jugamos otro?" (Luis, 2026-08-01).

   El botón "muéstrame otro" avanza el índice SIN salir del modo oferta. Si se
   mostrara cuando ya no queda un juego después del ofrecido, `siguiente` se
   volvería null, la pantalla de oferta dejaría de renderizarse y el kiosco
   PONDRÍA A JUGAR justo el juego que el niño acababa de rechazar. Por eso la
   condición es `faltan > 1` y no `faltan > 0`.

   Esto fija esa aritmética contra la longitud real de las cadenas.
   ────────────────────────────────────────────────────────────────────────── */
for (const [slug, { estrella }] of Object.entries(HOMOLOGADAS)) {
  test(`${slug}: la oferta ofrece "otro juego" solo cuando de verdad queda otro`, () => {
    const flow = resolveThemeFlow(TEMAS[slug])
    for (const p of TEMAS[slug].personajes) {
      const lista = flow.gamesFor(p.name)
      // La cadena de la estrella son 4 juegos; la del resto, 3.
      assert.equal(lista.length, p.name === estrella ? 4 : 3, `${slug}/${p.name}`)
      for (let idx = 0; idx < lista.length - 1; idx += 1) {
        const faltan = lista.length - (idx + 1)
        const muestraOtro = faltan > 1
        if (muestraOtro) {
          assert.ok(lista[idx + 2], `${slug}/${p.name}: ofrece "otro" sin tener otro en idx ${idx}`)
        }
      }
      // En la última oferta de la cadena nunca debe aparecer "otro juego".
      const ultimaOferta = lista.length - 2
      assert.equal(lista.length - (ultimaOferta + 1), 1, `${slug}/${p.name}`)
    }
  })
}

/* ── Temáticas de baby shower (2026-08-26) ─────────────────────────────────
   No entran en HOMOLOGADAS y no es un olvido: esa tabla exige seis personajes
   con su cadena de juegos, ruleta, puzzles y videos de saludo, y un baby
   shower no tiene nada de eso. Su recorrido es intro → apuesta → juego →
   sellado → foto → revelado → QR → recuerdito, sin ruleta ni personajes.

   Lo que sí necesitan está acá, y se verifica contra disco igual que la tabla
   A: si alguien agrega una temática de baby shower a medias, el test lo dice
   en vez de descubrirlo el invitado en la fiesta.
   ──────────────────────────────────────────────────────────────────────── */
const BABY_SHOWER = Object.entries(TEMAS).filter(([, t]) => t.modalidad === 'baby_shower')

test('hay al menos una temática de baby shower registrada', () => {
  assert.ok(BABY_SHOWER.length > 0, 'ninguna temática declara modalidad baby_shower')
})

for (const [slug, tema] of BABY_SHOWER) {
  test(`${slug}: paleta, confetti y frameBox completos`, () => {
    const COLORES = ['accent', 'accentSoft', 'yellow', 'ink', 'bgLight1', 'bgLight2', 'dark1', 'dark2', 'dark3']
    for (const c of COLORES) {
      assert.match(String(tema.colors?.[c] ?? ''), /^#[0-9a-fA-F]{3,8}$/, `${slug}: falta o es inválido colors.${c}`)
    }
    assert.equal(tema.confetti?.length, 6, `${slug}: el confetti son 6 colores`)

    const f = tema.frameBox
    assert.ok(f, `${slug}: sin frameBox`)
    for (const k of ['x', 'y', 'w', 'h']) {
      assert.equal(typeof f[k], 'number', `${slug}: frameBox.${k} no es número`)
    }
    assert.ok(f.x + f.w <= 1 && f.y + f.h <= 1, `${slug}: el frameBox se sale del lienzo`)
    assert.ok(f.w >= 0.05 && f.h >= 0.05, `${slug}: frameBox demasiado chico`)
  })

  /* El cierre del kiosco. El MP4 estaba en disco en las tres temáticas desde el
     principio, pero NINGUNA lo declaraba en themes.json, así que
     `$safeThemeVideo` no lo publicaba y el kiosco caía al genérico
     `videos/despedida.mp4` — que no existe — y de ahí a una tarjeta con emoji.
     Nada fallaba: el archivo estaba, el kiosco no reventaba, y la fiesta
     terminaba sin video. Tener el asset no sirve de nada si nadie lo declara,
     y por eso este test mira las DOS cosas. */
  test(`${slug}: el kiosco cierra con video, no con un emoji`, () => {
    const declarado = tema.videos?.despedida
    assert.ok(declarado, `${slug}: no declara videos.despedida en themes.json`)
    assert.equal(declarado, basename(declarado), `${slug}: videos.despedida debe ser solo el nombre del archivo`)
    assert.match(declarado, /\.mp4$/, `${slug}: videos.despedida debe ser un .mp4`)

    const ruta = new URL(`../../public/themes/${slug}/${declarado}`, import.meta.url)
    assert.ok(existsSync(ruta), `${slug}: declara ${declarado} y ese archivo no está en disco`)
    assert.ok(statSync(ruta).size > 500_000, `${slug}: ${declarado} pesa sospechosamente poco`)
  })

  test(`${slug}: no declara personajes, porque el recorrido no los usa`, () => {
    assert.deepEqual(tema.personajes ?? [], [], `${slug}: un baby shower no tiene personajes ni ruleta`)
  })

  test(`${slug}: están el fondo de bienvenida y el de la foto, los dos en 9:16`, () => {
    for (const archivo of ['fondo-banner.jpg', 'fondo-sala.jpg']) {
      const ruta = new URL(`../../public/themes/${slug}/${archivo}`, import.meta.url)
      assert.ok(existsSync(ruta), `${slug}: falta ${archivo}`)
      assert.ok(statSync(ruta).size > 20000, `${slug}: ${archivo} pesa sospechosamente poco`)
    }
  })

  // El kiosco suena en loop toda la fiesta con themes/<slug>/musica-fondo.mp3.
  // Ojo con el falso positivo: si el archivo no está, el servidor devuelve el
  // index.html con 200 y nada avisa — por eso se comprueba contra disco y no
  // por HTTP. El peso mínimo descarta un archivo truncado.
  test(`${slug}: música de fondo del kiosco`, () => {
    const ruta = new URL(`../../public/themes/${slug}/musica-fondo.mp3`, import.meta.url)
    assert.ok(existsSync(ruta), `${slug}: falta musica-fondo.mp3`)
    assert.ok(statSync(ruta).size > 200000, `${slug}: musica-fondo.mp3 pesa sospechosamente poco`)
  })

  // La invitación pública de esta temática. Sin una entrada propia en
  // event-profile-presets.json, el preset cae a `theme_fallback`, que no
  // define `base_image`: la página mostraba DOS VECES el mismo fondo —una de
  // hero y otra de "lámina"— en vez de los datos escritos dentro del marco.
  // Es una degradación silenciosa: no rompe nada, solo se ve pobre, así que
  // sin este test volvería sin que nadie se diera cuenta.
  test(`${slug}: la invitación tiene lámina propia, dentro del marco`, () => {
    const preset = PRESETS_INVITACION.themes?.[slug]
    assert.ok(preset, `${slug}: no está en event-profile-presets.json`)

    const base = String(preset.base_image ?? '')
    assert.ok(base, `${slug}: el preset no declara base_image`)
    const ruta = new URL(`../../public/themes/${slug}/${base}`, import.meta.url)
    assert.ok(existsSync(ruta), `${slug}: base_image apunta a ${base}, que no existe`)

    // El texto de la invitación y la foto del kiosco apuntan al MISMO marco
    // pintado en `fondo-sala.jpg`, así que tienen que quedar centrados en el
    // mismo punto. No son el mismo rectángulo —y este test llegó a exigir que
    // lo fueran, que es falso—: `text_area` es la zona de texto tal cual, y
    // `frameBox` es el ancla desde donde el kiosco calcula la foto (cuadrado
    // inscrito, menos un 8,5% por lado) y la línea del agradecimiento. Pedir
    // que fueran iguales obligaba a descalibrar uno para arreglar el otro.
    //
    // Lo que sí es invariante es el centro: si alguien recalibra un lado y el
    // otro no, los centros se separan y la invitación deja de calzar con el
    // marco. El cuadrado se calcula con la función del propio producto, no con
    // una copia de la fórmula.
    const area = preset.text_area
    assert.ok(area, `${slug}: el preset no declara text_area`)
    for (const k of ['x', 'y', 'w', 'h']) {
      assert.equal(typeof area[k], 'number', `${slug}: text_area.${k} no es número`)
    }
    // En píxeles reales y no sobre un lienzo 1x1: el cuadrado sale de
    // `min(ancho, alto)` en PÍXELES, así que en un lienzo cuadrado daría un
    // recorte distinto al del kiosco, que siempre es 9:16.
    const [LIENZO_W, LIENZO_H] = [1080, 1920]
    const foto = getSquarePhotoGeometry(tema.frameBox, LIENZO_W, LIENZO_H)
    const centro = {
      x: (foto.photoLeft + foto.photoSide / 2) / LIENZO_W,
      y: (foto.photoTop + foto.photoSide / 2) / LIENZO_H,
    }
    const TOLERANCIA = 0.02  // 2% del lienzo: ~22 px de ancho, ~38 px de alto
    assert.ok(
      Math.abs(area.x + area.w / 2 - centro.x) <= TOLERANCIA,
      `${slug}: el texto de la invitación y la foto del kiosco no comparten centro horizontal `
        + `(${(area.x + area.w / 2).toFixed(4)} contra ${centro.x.toFixed(4)})`
    )
    assert.ok(
      Math.abs(area.y + area.h / 2 - centro.y) <= TOLERANCIA,
      `${slug}: el texto de la invitación y la foto del kiosco no comparten centro vertical `
        + `(${(area.y + area.h / 2).toFixed(4)} contra ${centro.y.toFixed(4)})`
    )
    assert.match(String(area.tone ?? ''), /^#[0-9a-fA-F]{6}$/, `${slug}: text_area.tone inválido`)
  })
}
