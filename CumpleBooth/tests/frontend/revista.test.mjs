// Portada de Revista CLICK (04-10-2026): textos, medidas y orden de las capas sin navegador, y los archivos de la temática.
// El recorrido completo en la tablet (menú de portadas, vista previa y foto final) está en feria-integracion.test.mjs.
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import { resolve, join } from 'node:path'
import { textosPortada, fechaPortada, partirLineas, tamanoQueCabe, barrasDe, capasPortada, ZONAS, TEXTOS_DE_SIEMPRE } from '../../src/feria/revista.js'
import { cajaDeMascara, encuadrePortada, subjectPlacement } from '../../src/feria/segmentation.js'

const root = resolve(import.meta.dirname, '../..')
const php = process.env.PHP_PATH || 'C:/wamp64/bin/php/php8.3.28/php.exe'

// Un contexto 2D de mentira que anota lo que se dibuja: alcanza para saber qué texto va, dónde y con qué ancho máximo.
function lienzoDeMentira() {
  const llamadas = []
  let font = '10px serif'
  const ctx = {
    llamadas,
    get font() { return font },
    set font(v) { font = v },
    save: () => llamadas.push(['save']),
    restore: () => llamadas.push(['restore']),
    translate: (...a) => llamadas.push(['translate', ...a]),
    scale: (...a) => llamadas.push(['scale', ...a]),
    fillRect: (...a) => llamadas.push(['fillRect', ...a]),
    fillText: (texto, x, y, max) => llamadas.push(['fillText', texto, x, y, max]),
    measureText: (t) => {
      const px = Number(/(\d+(?:\.\d+)?)px/.exec(font)?.[1] || 10)
      return { width: String(t).length * px * 0.6, actualBoundingBoxAscent: px * 0.72 }
    },
    letterSpacing: '0px',
  }
  return ctx
}
const textosDe = (ctx) => ctx.llamadas.filter((l) => l[0] === 'fillText').map((l) => l[1])

test('los textos de la portada: título, nombre, evento y fecha', () => {
  const t = textosPortada({ titulo: 'click', nombre: 'josé', evento: 'Feria de Primavera', fecha: '2026-10-04' })
  assert.equal(t.cabecera, 'CLICK')
  assert.equal(t.titular, 'JOSÉ')
  assert.equal(t.bajada, 'Así se vivió Feria de Primavera')
  assert.equal(t.fecha, 'OCTUBRE 2026')
  // Sin nombre, la portada le habla al invitado; sin evento, una bajada que sirve de día y de noche.
  const vacio = textosPortada({})
  assert.equal(vacio.cabecera, 'CLICK')
  assert.equal(vacio.titular, 'ERES TÚ')
  assert.equal(vacio.bajada, 'Así se vivió la celebración')
  assert.doesNotMatch(JSON.stringify(vacio), /noche/i, 'nada de "la noche" fijo: la feria es de día')
})

test('la fecha de la portada sale del evento y, sin fecha, del mes en curso', () => {
  assert.equal(fechaPortada('2026-12-31'), 'DICIEMBRE 2026')
  assert.equal(fechaPortada('', new Date(2027, 0, 15)), 'ENERO 2027')
  assert.equal(fechaPortada('no-es-fecha', new Date(2026, 5, 1)), 'JUNIO 2026')
})

test('partir líneas respeta el ancho y deja sola una palabra que no cabe', () => {
  const medir = (s) => s.length
  assert.deepEqual(partirLineas('uno dos tres cuatro', 7, medir), ['uno dos', 'tres', 'cuatro'])
  assert.deepEqual(partirLineas('supercalifragilístico sí', 5, medir), ['supercalifragilístico', 'sí'])
  assert.deepEqual(partirLineas('   ', 5, medir), [])
})

test('el tamaño que cabe baja hasta caber y nunca pasa del mínimo', () => {
  const medir = (t, s) => t.length * s * 0.6
  const cabe = tamanoQueCabe('CLICK', 600, 400, 100, medir)
  assert.ok(cabe <= 400 && cabe >= 100 && medir('CLICK', cabe) <= 600, 'baja hasta caber: ' + cabe)
  assert.equal(tamanoQueCabe('CLICK', 10000, 400, 100, medir), 400, 'si cabe, el máximo')
  assert.equal(tamanoQueCabe('UN NOMBRE LARGUÍSIMO DE VERDAD', 100, 400, 100, medir), 100, 'si no cabe ni en el mínimo, el mínimo')
})

test('el código de barras es estable por evento', () => {
  assert.deepEqual(barrasDe('Feria de Primavera'), barrasDe('Feria de Primavera'))
  assert.notDeepEqual(barrasDe('Feria de Primavera'), barrasDe('Cumpleaños de Ana'))
  const b = barrasDe('x', 20)
  assert.equal(b.length, 20)
  assert.ok(b.every((n) => n >= 1 && n <= 3))
})

test('las capas: el título va antes de la persona y los titulares después, dentro de la portada', () => {
  const W = 1080, H = 1920
  const textos = textosPortada({ nombre: 'Maximiliano', evento: 'Feria de Primavera', fecha: '2026-10-04' })
  const capas = capasPortada(textos, { tinta: '#FFFFFF', acento: '#C1121F', evento: 'Feria de Primavera' })
  const antes = lienzoDeMentira(); capas.antes(antes, W, H)
  // Detrás de la persona: el título, y edición y fecha bajo sus puntas (nunca sobre una cara).
  assert.deepEqual(textosDe(antes), ['CLICK', 'EDICIÓN ESPECIAL', 'OCTUBRE 2026'])
  const despues = lienzoDeMentira(); capas.despues(despues, W, H)
  const escritos = textosDe(despues)
  for (const esperado of ['EXCLUSIVA', 'MAXIMILIANO', 'LA ESTRELLA DE HOY', 'N.º 1']) assert.ok(escritos.includes(esperado), 'falta ' + esperado)
  assert.ok(!escritos.includes('CLICK') && !escritos.some((t) => t.includes('OCTUBRE')), 'título y fecha no se repiten delante')
  // Todo save tiene su restore: si no, el volteo o la sombra se le pegarían al resto del dibujo.
  for (const ctx of [antes, despues]) {
    const pila = ctx.llamadas.reduce((n, l) => n + (l[0] === 'save') - (l[0] === 'restore'), 0)
    assert.equal(pila, 0)
  }
  // El nombre y el título llevan ancho máximo: un nombre larguísimo se angosta en vez de salirse de la portada.
  const nombre = despues.llamadas.find((l) => l[0] === 'fillText' && l[1] === 'MAXIMILIANO')
  assert.ok(nombre[4] > 0 && nombre[2] + nombre[4] <= W, 'el nombre no pasa del borde derecho')
  const titulo = antes.llamadas.find((l) => l[0] === 'fillText')
  assert.ok(titulo[4] > 0 && titulo[4] <= ZONAS.cabecera.w * W)
})

test('en la vista previa de la cámara frontal el texto se dibuja volteado (el CSS lo endereza)', () => {
  const capas = capasPortada(textosPortada({}), { espejo: true })
  const ctx = lienzoDeMentira(); capas.antes(ctx, 360, 640)
  assert.deepEqual(ctx.llamadas.find((l) => l[0] === 'translate'), ['translate', 360, 0])
  assert.deepEqual(ctx.llamadas.find((l) => l[0] === 'scale'), ['scale', -1, 1])
  const derecho = lienzoDeMentira(); capasPortada(textosPortada({})).antes(derecho, 360, 640)
  assert.ok(!derecho.llamadas.some((l) => l[0] === 'scale'), 'la foto final no se voltea')
})

test('la temática adulto-revista: tres portadas con su escena 1080x1920, fuentes libres y sin franquicia', () => {
  const temas = JSON.parse(readFileSync(join(root, 'public/data/themes.json'), 'utf8')).themes
  const revista = temas['adulto-revista']
  assert.ok(revista, 'adulto-revista registrada')
  assert.equal(revista.audiencia, 'adulto')
  assert.equal(revista.franquicia, null)
  assert.deepEqual(revista.personajes, [])
  assert.equal(revista.modoFoto, 'fondo')
  assert.equal(revista.revista.titulo, 'CLICK')
  assert.deepEqual(revista.revista.variantes.map((v) => v.clave), ['alfombra', 'estudio', 'bn'])
  assert.deepEqual(revista.revista.variantes.filter((v) => v.filtro).map((v) => v.clave), ['bn'], 'solo el blanco y negro lleva filtro')
  const dimensiones = (archivo) => {
    // Alto y ancho del JPEG desde su marcador SOF, sin dependencias.
    const b = readFileSync(archivo)
    for (let i = 2; i < b.length;) {
      const marca = b[i + 1], largo = b.readUInt16BE(i + 2)
      if (marca >= 0xc0 && marca <= 0xc3) return [b.readUInt16BE(i + 7), b.readUInt16BE(i + 5)]
      i += 2 + largo
    }
    return null
  }
  const carpeta = join(root, 'public/themes/adulto-revista')
  for (const archivo of [...revista.revista.variantes.map((v) => v.escena), revista.fondoEscena, 'fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-evento.jpg']) {
    assert.ok(existsSync(join(carpeta, archivo)), 'falta ' + archivo)
    assert.deepEqual(dimensiones(join(carpeta, archivo)), [1080, 1920], archivo + ' mide 1080x1920')
  }
  const fuentes = join(root, 'public/fonts/revista')
  for (const archivo of ['bodoni-moda-latin-900-normal.woff2', 'bodoni-moda-latin-700-italic.woff2', 'oswald-latin-500-normal.woff2', 'oswald-latin-700-normal.woff2']) {
    assert.equal(readFileSync(join(fuentes, archivo)).subarray(0, 4).toString('latin1'), 'wOF2', archivo + ' es WOFF2')
  }
  for (const licencia of ['OFL-bodoni-moda.txt', 'OFL-oswald.txt']) assert.match(readFileSync(join(fuentes, licencia), 'utf8'), /SIL OPEN FONT LICENSE/i)
})

test('la caja de la persona sale de la máscara y un punto suelto no la estira', () => {
  const mw = 100, mh = 100, valores = new Float32Array(mw * mh)
  for (let y = 20; y < 100; y++) for (let x = 30; x < 70; x++) valores[y * mw + x] = 0.9
  valores[5 * mw + 5] = 1 // un afiche detrás: un solo píxel
  assert.deepEqual(cajaDeMascara(valores, mw, mh), { top: 0.2, bottom: 1, left: 0.3, right: 0.7 })
  assert.equal(cajaDeMascara(new Float32Array(mw * mh), mw, mh), null, 'sin persona, sin caja')
})

test('el encuadre de portada: la coronilla sobre el pie del título, sin achicar ni dejar ver los bordes de la cámara', () => {
  const W = 1080, H = 1920, sw = 600, sh = 800
  const fit = subjectPlacement(sw, sh, W, H).width / sw
  const cubre = (b) => b.x <= 0.001 && b.x + b.width >= W - 0.001 && Math.abs(b.y + b.height - H) < 0.001
  // Persona normal: la coronilla queda en el 9,5 % del alto.
  const normal = encuadrePortada({ top: 0.05, bottom: 1, left: 0.3, right: 0.7 }, sw, sh, W, H)
  assert.ok(Math.abs(normal.y + 0.05 * normal.height - 0.095 * H) < 1, 'coronilla en el 9,5 %: ' + (normal.y + 0.05 * normal.height))
  assert.ok(cubre(normal), 'el cuadro cubre el ancho y llega abajo: no se ve un borde de la cámara')
  // Persona lejos: el zoom tiene tope para no quedar borrosa.
  const lejos = encuadrePortada({ top: 0.5, bottom: 1, left: 0.4, right: 0.6 }, sw, sh, W, H)
  assert.ok(Math.abs(lejos.width / sw - fit * 1.6) < 1e-9, 'tope de 1,6 veces la escala de siempre')
  // Cabeza cortada por la cámara: el borde de arriba del cuadro queda en el borde del lienzo, el corte no se ve.
  const cerca = encuadrePortada({ top: 0, bottom: 1, left: 0.2, right: 0.8 }, sw, sh, W, H)
  assert.ok(cerca.y <= 0.001, 'el corte de la cabeza queda fuera del lienzo: y=' + cerca.y)
  // Nunca más chica que la escala de siempre, aunque la persona llene el cuadro.
  const grande = encuadrePortada({ top: 0.02, bottom: 1, left: 0, right: 1 }, sw, sh, W, H)
  assert.ok(grande.width / sw >= fit - 1e-9)
  // Corrida a un lado: se centra lo que se puede sin destapar el borde del cuadro.
  const corrida = encuadrePortada({ top: 0.05, bottom: 1, left: 0, right: 0.4 }, sw, sh, W, H)
  assert.ok(cubre(corrida) && corrida.x === 0, 'pegada al borde izquierdo de la cámara: el cuadro no se despega del lienzo')
})

test('las capas de la portada piden el encuadre, suave solo en la vista previa', () => {
  assert.deepEqual(capasPortada(textosPortada({})).encuadre, { cabeza: 0.095, zoomMax: 1.6 })
  assert.equal(capasPortada(textosPortada({}), {}, { suave: true }).encuadre.suave, true)
})

test('los textos de Ajustes reemplazan a los de siempre; uno vacío deja el de siempre', () => {
  const t = textosPortada({ nombre: '', evento: 'Feria de Primavera', propios: {
    antetitulo: 'la reina de hoy', llamado1: 'Moda de feria', bajada: 'Lo mejor de {evento}', sinNombre: 'tú', numero: 'N.º 25', etiqueta: '  ' } })
  assert.equal(t.antetitulo, 'LA REINA DE HOY', 'en mayúsculas aunque se escriba en minúsculas')
  assert.deepEqual(t.llamados, ['Moda de feria', 'Sus mejores poses'])
  assert.equal(t.bajada, 'Lo mejor de Feria de Primavera', '{evento} se cambia por el nombre del evento')
  assert.equal(t.titular, 'TÚ', 'sin nombre, el texto propio en su lugar')
  assert.equal(t.numero, 'N.º 25')
  assert.equal(t.exclusiva, 'EXCLUSIVA', 'en blanco vale como vacío')
  assert.equal(textosPortada({ propios: { bajada: 'Lo mejor de {evento}' } }).bajada, 'Lo mejor de la celebración')
})

test('los textos de siempre son los mismos en el kiosco y en Ajustes (PHP)', { skip: !existsSync(php) && 'sin PHP' }, () => {
  const codigo = 'require $argv[1]; echo json_encode(array_map(fn ($c) => $c[2], cb_revista_textos_campos()), JSON_UNESCAPED_UNICODE);'
  const salida = execFileSync(php, ['-r', codigo, join(root, 'public/lib.ajustes.php')], { encoding: 'utf8' })
  assert.deepEqual(JSON.parse(salida), TEXTOS_DE_SIEMPRE)
})

test('un llamado largo achica la letra y no pierde palabras', () => {
  const W = 1080, H = 1920
  const largo = 'Los looks que todos comentan esta vez'
  const ctx = lienzoDeMentira(); capasPortada(textosPortada({ propios: { llamado1: largo } })).despues(ctx, W, H)
  const escritos = textosDe(ctx)
  const inicio = escritos.findIndex((t) => t.startsWith('LOS LOOKS'))
  const lineas = escritos.slice(inicio, inicio + 3).filter((t) => !t.startsWith('SUS MEJORES'))
  assert.ok(lineas.length <= 3)
  assert.equal(lineas.join(' '), largo.toLocaleUpperCase('es-CL'), 'todas las palabras en a lo más tres líneas')
})
