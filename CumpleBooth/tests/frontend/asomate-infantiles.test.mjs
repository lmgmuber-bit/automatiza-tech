// Asómate de Noche de Brujas (brujitas) y Navidad del Viejito Pascuero (navidad), 30-09-2026.
// No abre el navegador: lee themes.json y los PNG. Lo que cuida es lo que falló o pudo fallar al armarlos:
//  - cada cuerpo mide lo anotado y su hueco es transparente por dentro y cae sobre la figura por fuera (si el óvalo saliera de la
//    silueta, la foto del niño asomaría fuera del muñeco);
//  - en una foto de grupo manda la figura más ancha (`alturaComunAsomate` en src/App.jsx): con la pose de los saludos, el
//    murciélago con las alas abiertas y la calabaza redonda dejaban a un trío en 450 px de alto; con los cuerpos de pie, 583 a 637.
import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { inflateSync } from 'node:zlib'

const RAIZ = new URL('../../public/', import.meta.url)
const TEMAS = JSON.parse(readFileSync(new URL('data/themes.json', RAIZ), 'utf8')).themes

/** PNG RGBA de 8 bits sin entrelazado (lo que escribe el instalador). Devuelve el ancho, el alto y el alfa de cada píxel. */
function leerPng(ruta) {
  const b = readFileSync(ruta)
  assert.equal(b.subarray(1, 4).toString(), 'PNG', ruta)
  let pos = 8, w = 0, h = 0, tipo = 0, profundidad = 0, entrelazado = 0
  const idat = []
  while (pos < b.length) {
    const largo = b.readUInt32BE(pos)
    const nombre = b.subarray(pos + 4, pos + 8).toString()
    const datos = b.subarray(pos + 8, pos + 8 + largo)
    if (nombre === 'IHDR') { w = datos.readUInt32BE(0); h = datos.readUInt32BE(4); profundidad = datos[8]; tipo = datos[9]; entrelazado = datos[12] }
    if (nombre === 'IDAT') idat.push(datos)
    pos += 12 + largo
  }
  assert.deepEqual([profundidad, tipo, entrelazado], [8, 6, 0], 'PNG RGBA de 8 bits sin entrelazado: ' + ruta)
  const crudo = inflateSync(Buffer.concat(idat))
  const ancho = w * 4
  const px = Buffer.alloc(h * ancho)
  for (let y = 0; y < h; y++) {
    const filtro = crudo[y * (ancho + 1)]
    for (let x = 0; x < ancho; x++) {
      const izq = x >= 4 ? px[y * ancho + x - 4] : 0
      const arr = y > 0 ? px[(y - 1) * ancho + x] : 0
      const diag = x >= 4 && y > 0 ? px[(y - 1) * ancho + x - 4] : 0
      let v = crudo[y * (ancho + 1) + 1 + x]
      if (filtro === 1) v += izq
      else if (filtro === 2) v += arr
      else if (filtro === 3) v += (izq + arr) >> 1
      else if (filtro === 4) {
        const p = izq + arr - diag
        const pa = Math.abs(p - izq), pb = Math.abs(p - arr), pc = Math.abs(p - diag)
        v += pa <= pb && pa <= pc ? izq : pb <= pc ? arr : diag
      }
      px[y * ancho + x] = v & 255
    }
  }
  return { w, h, alfa: (x, y) => (x < 0 || y < 0 || x >= w || y >= h ? 0 : px[y * ancho + x * 4 + 3]) }
}

for (const slug of ['brujitas', 'navidad']) {
  const tema = TEMAS[slug]
  const bloque = tema.asomate

  test(`${slug}: Asómate trae los seis personajes, con su cuerpo y su fondo`, () => {
    assert.ok(bloque, `${slug}: falta el bloque asomate`)
    const claves = tema.personajes.map((p) => String(p.img).replace(/\.jpg$/i, ''))
    assert.equal(claves.length, 6)
    assert.deepEqual(Object.keys(bloque.personajes), claves, 'mismas claves y mismo orden que la ruleta: el nombre sale de la temática')
    assert.ok(existsSync(new URL(`themes/${slug}/${bloque.fondo}`, RAIZ)), `${slug}: falta el fondo ${bloque.fondo}`)
    assert.ok(bloque.boton && bloque.titulo, `${slug}: el modo lleva su propio nombre, no el neutro`)
    for (const clave of claves) assert.ok(existsSync(new URL(`themes/${slug}/asomate/${clave}.png`, RAIZ)), `${slug}: falta asomate/${clave}.png`)
  })

  test(`${slug}: cada cuerpo mide lo anotado y su hueco cae dentro de la figura`, () => {
    for (const [clave, g] of Object.entries(bloque.personajes)) {
      const png = leerPng(new URL(`themes/${slug}/asomate/${clave}.png`, RAIZ))
      assert.deepEqual([png.w, png.h], [g.w, g.h], `${clave}: mide lo anotado`)
      assert.deepEqual([g.arriba, g.pies, g.izq, g.der], [0, g.h - 1, 0, g.w - 1], `${clave}: el PNG está recortado al contorno`)
      // Por dentro del óvalo (al 60 %) el PNG es transparente: ahí va la cara del niño.
      let vacios = 0, total = 0
      for (let i = 0; i < 400; i++) {
        const t = (i / 400) * 2 * Math.PI, r = Math.sqrt((i % 20) / 20) * 0.6
        total++
        if (png.alfa(Math.round(g.cx + g.rx * r * Math.cos(t)), Math.round(g.cy + g.ry * r * Math.sin(t))) < 40) vacios++
      }
      assert.ok(vacios / total >= 0.98, `${clave}: el hueco es transparente (${Math.round((vacios / total) * 100)} %)`)
      // Por fuera (radio 1,08) hay figura: si el óvalo saliera de la silueta la foto asomaría fuera del muñeco. El pelo de lana de la
      // Señora Pascuera tiene huecos entre las hebras, por eso el umbral no es 100 %.
      let sobre = 0
      for (let i = 0; i < 360; i++) {
        const t = (i / 360) * 2 * Math.PI
        if (png.alfa(Math.round(g.cx + g.rx * 1.08 * Math.cos(t)), Math.round(g.cy + g.ry * 1.08 * Math.sin(t))) >= 128) sobre++
      }
      assert.ok(sobre / 360 >= 0.9, `${clave}: el borde del hueco cae sobre la figura (${Math.round((sobre / 360) * 100)} %)`)
      assert.ok(g.cx - g.rx > 0 && g.cx + g.rx < g.w && g.cy - g.ry > 0, `${clave}: el óvalo cabe en el PNG`)
    }
  })

  test(`${slug}: en una foto de grupo ninguna figura achica a las demás`, () => {
    // Mismas cuentas que alturaComunAsomate: cada uno tiene un carril de 0,94 del ancho dividido entre los que se asoman.
    const W = 1080, H = 1920
    const lista = Object.values(bloque.personajes)
    const peor = (n) => {
      const carril = (W / n) * (n === 1 ? 0.86 : 0.94)
      let peor = Infinity
      const combos = (desde, elegidos) => {
        if (elegidos.length === n) { peor = Math.min(peor, H * (n === 1 ? 0.8 : 0.62), ...elegidos.map((g) => (g.pies - g.arriba) * (carril / (g.der - g.izq)))); return }
        for (let i = desde; i < lista.length; i++) combos(i + 1, [...elegidos, lista[i]])
      }
      combos(0, [])
      return peor
    }
    assert.ok(peor(1) >= 1400, `solo: ${Math.round(peor(1))} px`)
    assert.ok(peor(2) >= 800, `de a dos: ${Math.round(peor(2))} px`)
    assert.ok(peor(3) >= 560, `de a tres: ${Math.round(peor(3))} px`)
  })
}

test('navidad: el grupo pisa el piso de su escena y no flota delante de la pared', () => {
  // El piso de fondo-escena.jpg de Navidad empieza en 0,86 del alto; los pies de un grupo caían en 0,8 (29-09, mirado en el navegador).
  const suelo = TEMAS.navidad.asomate.suelo
  assert.ok(suelo >= 0.85 && suelo <= 0.9, `suelo ${suelo}`)
  assert.equal(TEMAS.brujitas.asomate.suelo, undefined, 'Noche de Brujas tiene el piso desde 0,78 y le sirve el de siempre (0,8)')
})
