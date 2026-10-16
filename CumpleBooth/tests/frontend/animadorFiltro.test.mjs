// Juegos apagados desde el admin, guía ℹ️ de cada juego y los juegos nuevos del 12-oct
// (Verdad o shot, ¿Quién es más venezolano? y la ampliación de Yo nunca).
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import {
  NIVELES, abrirJuego, alternativaDisponible, anotar, cartasDelNivel, juegosActivos, juegosDelNivel,
  nivelesDelJuego, nuevaSesion, ordenarMazo, pasar, restaurarSesion, serializarSesion, siguiente, marcador,
  categoriasDelNivel, elegirCategoria, pendientesPorCategoria, cambiarNivel,
} from '../../src/animador/motor.js'
import { JUEGOS, PASO_ESTA } from '../../src/animador/mazos.js'
import { rellenar } from '../../src/cumpleAdulto.js'

const ids = (lista) => lista.map((j) => j.id)
const fijo = () => 0.42

test('apagados: los juegos apagados no aparecen; lo que no es lista de textos no apaga nada', () => {
  const activos = juegosActivos(JUEGOS, ['tribunal', 'verdad-o-shot', 'mas-venezolano'])
  assert.equal(activos.length, JUEGOS.length - 3)
  for (const id of ['tribunal', 'verdad-o-shot', 'mas-venezolano']) assert.ok(!ids(activos).includes(id), id)
  for (const raro of [undefined, null, 'tribunal', { tribunal: true }, 7]) {
    assert.equal(juegosActivos(JUEGOS, raro).length, JUEGOS.length, String(raro))
  }
  // Entradas que no son texto se ignoran, ids desconocidos no hacen nada.
  assert.equal(juegosActivos(JUEGOS, [1, null, 'no-existe', 'swing']).length, JUEGOS.length - 1)
  assert.deepEqual(juegosActivos(JUEGOS, ids(JUEGOS)), [])
})

test('apagados: la lista por nivel sale solo de los activos y puede quedar vacía', () => {
  const activos = juegosActivos(JUEGOS, ids(JUEGOS).filter((id) => id !== 'piropos'))
  // Piropos solo tiene tarjetas desde Picante: en Coqueto no queda ninguno.
  assert.deepEqual(ids(juegosDelNivel(activos, 'coqueto')), [])
  assert.deepEqual(ids(juegosDelNivel(activos, 'picante')), ['piropos'])
})

test('apagados: si la alternativa del Tribunal está apagada, no se ofrece', () => {
  const tribunal = JUEGOS.find((j) => j.id === 'tribunal')
  assert.equal(alternativaDisponible(JUEGOS, tribunal, 'picante')?.id, 'coartada')
  assert.equal(alternativaDisponible(juegosActivos(JUEGOS, ['coartada']), tribunal, 'picante'), null)
})

test('apagados: una partida guardada que apuntaba a un juego apagado vuelve a la lista sin error', () => {
  const vos = JUEGOS.find((j) => j.id === 'verdad-o-shot')
  let s = abrirJuego(nuevaSesion({ nivel: 'picante', equipos: ['A', 'B'] }), vos, fijo)
  s = anotar(s, 0, 1)
  const r = restaurarSesion(JSON.parse(JSON.stringify(serializarSesion(s))))
  assert.ok(r)
  assert.equal(r.juego, null)
  // Lo que hace Animador.jsx: el juego abierto se busca solo entre los activos.
  const activos = juegosActivos(JUEGOS, ['verdad-o-shot'])
  assert.equal(activos.find((j) => j.id === s.juego) || null, null)
  assert.deepEqual(marcador(r), [1, 0])
})

test('guía ℹ️: cada juego trae resumen, pasos, duración, jugadores, puntaje, preparar y niveles', () => {
  for (const j of JUEGOS) {
    const g = j.guia
    assert.ok(g, j.id)
    for (const k of ['resumen', 'duracion', 'jugadores', 'puntaje', 'preparar']) {
      assert.ok(typeof g[k] === 'string' && g[k].trim().length > 2, `${j.id}.${k}`)
    }
    assert.ok(Array.isArray(g.pasos) && g.pasos.length >= 3, `${j.id}.pasos`)
    assert.ok(nivelesDelJuego(j).length >= 1, `${j.id} niveles`)
    for (const texto of [g.resumen, g.duracion, g.jugadores, g.puntaje, g.preparar, ...g.pasos]) {
      for (const datos of [{ nombre: '', edad: null }, { nombre: 'Alex', edad: 50 }]) {
        const listo = rellenar(texto, datos)
        assert.doesNotMatch(listo, /[{}]/, texto)
        assert.doesNotMatch(listo, /\s(a|de|con|para|que)\s*[?.,:;)]/, `queda colgando: «${listo}»`)
        assert.doesNotMatch(listo, /—/, 'sin rayas largas')
      }
    }
  }
  assert.deepEqual(nivelesDelJuego(JUEGOS.find((j) => j.id === 'piropos')), ['picante', 'sinverguenza'])
  assert.deepEqual(nivelesDelJuego(JUEGOS.find((j) => j.id === 'beso-al-blanco')), NIVELES)
})

test('catálogo: 19 juegos con ids únicos e ids y nombres acordados con Forja', () => {
  assert.equal(JUEGOS.length, 19)
  assert.equal(new Set(ids(JUEGOS)).size, JUEGOS.length)
  assert.equal(JUEGOS.find((j) => j.id === 'verdad-o-shot')?.nombre, 'Verdad o shot')
  assert.equal(JUEGOS.find((j) => j.id === 'mas-venezolano')?.nombre, '¿Quién es más venezolano?')
  assert.equal(JUEGOS.find((j) => j.id === 'venezolano-politica')?.nombre, '¿Quién es más venezolano? Edición política')
})

test('Verdad o shot: tres niveles, un punto por verdad, nadie obligado, «Paso esta» sin castigo', () => {
  const vos = JUEGOS.find((j) => j.id === 'verdad-o-shot')
  assert.equal(vos.puntos, 'acierto')
  for (const tono of NIVELES) {
    const n = vos.cartas.filter((c) => c.tono === tono).length
    assert.ok(n >= 5 && n <= 7, `${tono}: ${n}`)
  }
  assert.match(vos.instrucciones, /Nadie está obligado a tomar/)
  assert.match(vos.instrucciones, /sin alcohol/)
  assert.ok(vos.guia.pasos.some((p) => /Nadie está obligado/.test(p)))
  assert.match(vos.guia.puntaje, /^1 punto/)
  // Un punto por equipo y ronda; pasar no suma ni resta.
  let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), vos, fijo)
  s = anotar(anotar(s, 0, 1), 0, 1)
  assert.deepEqual(marcador(s), [1, 0])
  s = pasar(s)
  assert.deepEqual(marcador(s), [1, 0])
  // El mazo no repite hasta agotarse.
  const vistas = new Set([s.carta])
  let t = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), vos, fijo)
  const total = cartasDelNivel(vos, 'sinverguenza').length
  vistas.clear()
  for (let i = 0; i < total; i++) { vistas.add(t.carta); t = siguiente(t) }
  assert.equal(vistas.size, total)
  assert.equal(t.agotado, true)
  // Preguntas sobre uno mismo: nada de terceros con nombre, salud ni parejas ajenas.
  const texto = vos.cartas.map((c) => c.texto).join(' ')
  assert.doesNotMatch(texto, /\{nombre\}|tu pareja|tu ex\b|enfermedad|sexo|desnud/i)
})

test('Yo nunca: 16 tarjetas, regla del sorbo con o sin alcohol y nadie obligado', () => {
  const yn = JUEGOS.find((j) => j.id === 'yo-nunca')
  assert.equal(yn.cartas.length, 16)
  const nuevas = yn.cartas.slice(8)
  assert.ok(nuevas.every((c) => c.tono === 'picante' || c.tono === 'sinverguenza'))
  assert.match(yn.instrucciones, /sorbo/)
  assert.match(yn.instrucciones, /con o sin alcohol/)
  assert.match(yn.instrucciones, /Nadie está obligado/)
  assert.doesNotMatch(yn.instrucciones, /no hay que beber/)
})

test('¿Quién es más venezolano?: categorías, respuestas ocultas, niveles y puntaje', () => {
  const ve = JUEGOS.find((j) => j.id === 'mas-venezolano')
  assert.equal(ve.puntos, 'acierto')
  assert.equal(ve.conCategorias, true)
  assert.equal(ve.cierre, 'Canta conmigo')
  const orden = ['Comida', 'Palabras', 'Tele y reinas', 'Música', 'País', 'Chile o Venezuela', 'Retos', 'Canta conmigo']
  assert.deepEqual(categoriasDelNivel(ve, 'sinverguenza'), orden)
  // Todas las preguntas traen respuesta, salvo «Canta conmigo», los RETO y la imagen del personaje
  // (su respuesta la elige Luis junto con la imagen).
  for (const c of ve.cartas) {
    if (c.categoria === 'Canta conmigo' || c.tipo === 'reto' || c.id === 've77') assert.equal(c.respuesta, '', c.id)
    else assert.ok(c.respuesta, c.id)
  }
  const tono = (id) => ve.cartas.find((c) => c.id === id).tono
  const echale = ve.cartas.find((c) => /échale bolas/i.test(c.texto))
  const jala = ve.cartas.find((c) => /jala bolas/i.test(c.texto))
  assert.equal(echale.tono, 'picante')
  assert.equal(jala.tono, 'sinverguenza')
  const juanas = ve.cartas.find((c) => /Las Juanas/.test(c.texto))
  assert.equal(juanas.tono, 'picante')
  assert.ok(ve.cartas.filter((c) => c !== echale && c !== jala && c !== juanas).every((c) => tono(c.id) === 'coqueto'))
  assert.ok(!cartasDelNivel(ve, 'coqueto').includes(echale))
  assert.ok(!cartasDelNivel(ve, 'picante').includes(jala))
  assert.ok(cartasDelNivel(ve, 'sinverguenza').includes(jala))
  // Un punto por respuesta, uno por equipo y ronda; «Paso esta» no castiga.
  let s = abrirJuego(nuevaSesion({ nivel: 'coqueto', equipos: ['Los de allá', 'Los de acá'] }), ve, fijo)
  s = anotar(anotar(s, 1, 1), 1, 1)
  s = pasar(s)
  assert.deepEqual(marcador(s), [0, 1])
  // Los juegos sin categorías no tienen selector y siguen barajándose enteros.
  const swing = JUEGOS.find((j) => j.id === 'swing')
  assert.deepEqual(categoriasDelNivel(swing, 'sinverguenza'), [])
  assert.equal(ordenarMazo(swing, swing.cartas, fijo).length, swing.cartas.length)
  assert.deepEqual(categoriasDelNivel(JUEGOS.find((j) => j.id === 'venezolano-politica'), 'sinverguenza'), [])
})

// Generador fijo distinto por semilla, para probar varios barajados.
const semilla = (n) => () => ((n = (n * 1103515245 + 12345) % 2147483648) / 2147483648)
const catDe = (juego, id) => juego.cartas.find((c) => c.id === id).categoria

test('«Todas»: nunca dos seguidas de la misma categoría mientras queden de otras, y Canta conmigo al cierre', () => {
  const ve = JUEGOS.find((j) => j.id === 'mas-venezolano')
  for (const nivel of NIVELES) {
    for (let n = 1; n <= 12; n++) {
      let s = abrirJuego(nuevaSesion({ nivel, equipos: ['A', 'B'] }), ve, semilla(n))
      const salidas = []
      while (s.carta) {
        // Lo que quedaba en el mazo ANTES de esta tarjeta, para saber si había alternativa.
        salidas.push(catDe(ve, s.carta))
        s = n % 2 ? siguiente(s) : pasar(s)
      }
      const total = cartasDelNivel(ve, nivel).length
      assert.equal(salidas.length, total, `${nivel}/${n}: todas salen una vez`)
      assert.equal(s.agotado, true)
      // Canta conmigo solo cuando ya no queda nada más.
      const primeraCanta = salidas.indexOf('Canta conmigo')
      assert.ok(salidas.slice(primeraCanta).every((c) => c === 'Canta conmigo'), `${nivel}/${n}: cierre al final`)
      // Sin dos seguidas iguales mientras quedaban otras (fuera del cierre).
      for (let i = 1; i < primeraCanta; i++) {
        const quedaban = new Set(salidas.slice(i, primeraCanta))
        if (quedaban.size > 1) assert.notEqual(salidas[i], salidas[i - 1], `${nivel}/${n}: posición ${i}`)
      }
      // Van por turno: las primeras tarjetas pasan por todas las categorías antes de repetir una.
      const vuelta = categoriasDelNivel(ve, nivel).filter((c) => c !== 'Canta conmigo').length
      assert.equal(new Set(salidas.slice(0, vuelta)).size, vuelta, `${nivel}/${n}: una vuelta completa`)
      // Las primeras cinco ya cambian de categoría en cada tarjeta.
      const cinco = salidas.slice(0, 5)
      assert.ok(cinco.every((c, i) => i === 0 || c !== cinco[i - 1]), cinco.join(' > '))
    }
  }
})

test('elegir categoría: solo salen de esa, sin repetir; agotada avisa y «Todas» sigue', () => {
  const ve = JUEGOS.find((j) => j.id === 'mas-venezolano')
  let s = abrirJuego(nuevaSesion({ nivel: 'picante', equipos: ['A', 'B'] }), ve, semilla(7))
  const primera = s.carta
  s = elegirCategoria(s, 'Chile o Venezuela')
  assert.equal(catDe(ve, s.carta), 'Chile o Venezuela')
  // La tarjeta que estaba en pantalla, sin puntos ni revelar, vuelve al mazo: cambiar de chip no gasta.
  assert.ok(s.mazo.includes(primera))
  assert.ok(!s.usadas['mas-venezolano'].includes(primera))
  const chile = cartasDelNivel(ve, 'picante').filter((c) => c.categoria === 'Chile o Venezuela').length
  const vistas = [s.carta]
  for (let i = 1; i < chile; i++) { s = siguiente(s); vistas.push(s.carta); assert.equal(catDe(ve, s.carta), 'Chile o Venezuela') }
  assert.equal(new Set(vistas).size, chile)
  assert.equal(pendientesPorCategoria(s)['Chile o Venezuela'] || 0, 0)
  // Agotada: sin tarjeta, con aviso, sin dar por agotado el juego.
  s = pasar(s)
  assert.equal(s.carta, null)
  assert.equal(s.categoriaAgotada, true)
  assert.equal(s.agotado, false)
  // Volver a «Todas» sigue con el resto, sin repetir ninguna de las ya vistas.
  s = elegirCategoria(s, null)
  assert.ok(s.carta && !vistas.includes(s.carta))
  assert.equal(s.categoriaAgotada, false)
  // Elegir otra con la tarjeta ya revelada: esa sí cuenta como usada.
  s = elegirCategoria(revelarYDejar(s), 'Comida')
  assert.equal(catDe(ve, s.carta), 'Comida')
  const usadas = s.usadas['mas-venezolano']
  assert.equal(new Set(usadas).size, usadas.length, 'ninguna tarjeta se usa dos veces')
})
const revelarYDejar = (s) => ({ ...s, revelada: true })

test('la categoría elegida se guarda en la sesión y se valida al restaurar (CX-18)', () => {
  const ve = JUEGOS.find((j) => j.id === 'mas-venezolano')
  let s = abrirJuego(nuevaSesion({ nivel: 'coqueto', equipos: ['A', 'B'] }), ve, fijo)
  s = elegirCategoria(s, 'Tele y reinas')
  const datos = JSON.parse(JSON.stringify(serializarSesion(s)))
  assert.deepEqual(datos.categorias, { 'mas-venezolano': 'Tele y reinas' })
  const r = restaurarSesion(datos)
  assert.deepEqual(r.categorias, { 'mas-venezolano': 'Tele y reinas' })
  // Al volver a abrir el juego, sigue en esa categoría.
  const otra = abrirJuego(r, ve, semilla(3))
  assert.equal(otra.categoria, 'Tele y reinas')
  assert.equal(catDe(ve, otra.carta), 'Tele y reinas')
  // Partidas guardadas antes del 12-oct, sin la clave: valen.
  const vieja = { ...datos }; delete vieja.categorias
  assert.deepEqual(restaurarSesion(vieja).categorias, {})
  // Formas raras: se descarta la partida entera, como el resto de CX-18.
  for (const mala of [[], 'Comida', { 'mas-venezolano': 7 }, { 'mas-venezolano': '' }, { 'mas-venezolano': 'x'.repeat(65) }]) {
    assert.equal(restaurarSesion({ ...datos, categorias: mala }), null, JSON.stringify(mala))
  }
  // Una categoría guardada que no existe en el nivel se ignora: «Todas».
  const rara = restaurarSesion({ ...datos, categorias: { 'mas-venezolano': 'No existe' } })
  assert.equal(abrirJuego(rara, ve, fijo).categoria, null)
})

test('cambiar de nivel a uno sin la categoría elegida vuelve a «Todas»', () => {
  const ve = JUEGOS.find((j) => j.id === 'mas-venezolano')
  // Una categoría que solo exista desde Picante no la hay; se prueba con una ficticia.
  const juego = { ...ve, cartas: [...ve.cartas, { id: 'zz1', tono: 'sinverguenza', texto: 'x', respuesta: 'y', categoria: 'Solo atrevida' }] }
  let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), juego, fijo)
  s = elegirCategoria(s, 'Solo atrevida')
  assert.equal(s.carta, 'zz1')
  s = cambiarNivel(s, 'coqueto', juego)
  assert.equal(s.categoria, null)
  assert.ok(s.carta && s.carta !== 'zz1')
})

test('venezolano: dos RETO sin respuesta, Kino Táchira y la edición política aparte y apagable', () => {
  const ve = JUEGOS.find((j) => j.id === 'mas-venezolano')
  const retos = ve.cartas.filter((c) => c.tipo === 'reto')
  assert.equal(retos.length, 3)
  assert.ok(retos.some((c) => /Charly Mata/.test(c.texto)))
  assert.ok(retos.every((c) => c.respuesta === '' && /^RETO: /.test(c.texto)))
  assert.match(ve.cartas.find((c) => /Kino Táchira/.test(c.texto)).respuesta, /^12 aciertos/)
  const pol = JUEGOS.find((j) => j.id === 'venezolano-politica')
  assert.equal(pol.cartas.length, 8)
  assert.ok(pol.cartas.every((c) => c.respuesta && c.tono === 'coqueto'))
  assert.equal(pol.cartas.find((c) => /Salas Römer/.test(c.texto)).respuesta, 'Frijolillo')
  assert.ok(pol.cartas.some((c) => c.texto === '¿Qué frase de Maduro usó Trump para burlarse de él tras su captura en enero de 2026?'))
  assert.equal(ids(juegosActivos(JUEGOS, ['venezolano-politica'])).includes('venezolano-politica'), false)
  assert.ok(ids(juegosActivos(JUEGOS, ['venezolano-politica'])).includes('mas-venezolano'))
})

test('tarjetas de música e imagen: pocas, sin audio en la app, respuesta oculta y archivos fuera del repo', async () => {
  const todas = JUEGOS.flatMap((j) => j.cartas.map((c) => ({ ...c, juego: j.id })))
  // Las canciones de la ruleta del baile son aparte: las nombra la tablet y las pone el DJ (se prueban abajo).
  const musica = todas.filter((c) => c.tipo === 'musica' && c.juego !== 'ruleta-baile')
  const canciones = todas.filter((c) => c.juego === 'ruleta-baile')
  assert.equal(canciones.length, 37)
  for (const c of canciones) {
    assert.equal(c.tipo, 'musica')
    assert.match(c.texto, /^¡DJ, que suene «.+»/)
    assert.ok(c.pista && !/\.(mp3|m4a|ogg|wav)/i.test(JSON.stringify(c)), 'la ruleta tampoco trae audio de música')
  }
  const imagenes = todas.filter((c) => c.tipo === 'imagen')
  assert.deepEqual(musica.map((c) => c.respuesta), ['Por estas calles', 'Kassandra'])
  for (const c of musica) {
    assert.match(c.pista, /el tema de/)
    assert.ok(!/\.(mp3|m4a|ogg|wav)/i.test(JSON.stringify(c)), 'sin archivos de audio')
  }
  assert.deepEqual(imagenes.map((c) => [c.juego, c.imagen]), [['mas-venezolano', 'personaje-1.jpg'], ['venezolano-politica', 'firma.jpg']])
  assert.equal(imagenes.find((c) => c.imagen === 'firma.jpg').respuesta, 'Hugo Chávez')
  // La interfaz: la música solo da la instrucción; la imagen se prueba y, si falta, pide el celular.
  const fuente = await readFile(new URL('../../src/animador/Animador.jsx', import.meta.url), 'utf8')
  assert.match(fuente, /Pon en el parlante \{carta\.pista\} o pídeselo al DJ\. La app no trae audio\./)
  assert.doesNotMatch(fuente, /new Audio|<audio/)
  assert.match(fuente, /img\.onerror = \(\) => \{ if \(vivo\) setEstado\('falta'\) \}/)
  assert.match(fuente, /Muestra la imagen en tu celular/)
  assert.match(fuente, /carta\.respuesta && carta\.tipo !== 'reto'/)
  // Las imágenes no van al repositorio público: solo el .gitkeep.
  const ignorar = await readFile(new URL('../../.gitignore', import.meta.url), 'utf8')
  assert.match(ignorar, /^public\/themes\/\*\/animador\/\*\r?$/m)
  assert.match(ignorar, /^!public\/themes\/\*\/animador\/\.gitkeep\r?$/m)
})

test('«Paso esta» y el mazo sin repetir siguen igual con tarjetas de música, imagen y reto', () => {
  for (const id of ['mas-venezolano', 'venezolano-politica']) {
    const juego = JUEGOS.find((j) => j.id === id)
    let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), juego, fijo)
    const total = cartasDelNivel(juego, 'sinverguenza').length
    const vistas = []
    for (let i = 0; i < total; i++) { vistas.push(s.carta); s = pasar(s) }
    assert.equal(new Set(vistas).size, total, id)
    assert.equal(s.agotado, true, id)
    assert.deepEqual(marcador(s), [0, 0], 'pasar no suma ni resta')
    const tipos = new Set(vistas.map((v) => juego.cartas.find((c) => c.id === v).tipo || 'pregunta'))
    if (id === 'mas-venezolano') for (const t of ['pregunta', 'reto', 'musica', 'imagen']) assert.ok(tipos.has(t), t)
    else assert.ok(tipos.has('imagen'))
  }
})

test('las tarjetas nuevas no traen letras de canciones ni marcas de programas', async () => {
  const fuente = await readFile(new URL('../../src/animador/mazos.js', import.meta.url), 'utf8')
  const canta = JUEGOS.find((j) => j.id === 'mas-venezolano').cartas.filter((c) => c.categoria === 'Canta conmigo')
  assert.equal(canta.length, 5)
  for (const c of canta) assert.match(c.texto, /^Todos a cantar /, 'solo nombra la canción')
  assert.doesNotMatch(fuente, /podcast/i)
  assert.equal(PASO_ESTA, 'Tú decides: juega o pasa esta.')
})

test('la ficha ℹ️ se cierra con Escape, vuelve el foco y tiene etiqueta por juego', async () => {
  // Sin DOM en node: se cuida la estructura. El recorrido real se prueba en el navegador.
  const fuente = await readFile(new URL('../../src/animador/Animador.jsx', import.meta.url), 'utf8')
  assert.match(fuente, /aria-label=\{texto\}/)
  assert.match(fuente, /`Cómo se juega: \$\{t\(juego\.nombre\)\}`/)
  assert.match(fuente, /e\.key === 'Escape'/)
  assert.match(fuente, /role="dialog" aria-modal="true"/)
  assert.match(fuente, /el\.focus\(\)/)
  assert.match(fuente, />Entendido</)
})
