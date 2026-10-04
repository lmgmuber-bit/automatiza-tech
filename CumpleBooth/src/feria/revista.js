// Portada de Revista CLICK (adultos, 04-10-2026). Lo que en la competencia es un "Magazine Booth" de cartón (Tu Fiesta 360)
// o un tótem con IA (Enjoy Producciones), aquí lo arma el mismo compositor de la feria: la escena de fondo, el título CLICK
// DETRÁS de la persona (la cabeza lo tapa un poco, como en una portada de verdad) y los titulares DELANTE. Los textos los
// dibuja el código, nunca la IA: la escena se generó sin una sola letra (regla de la casa). Nada de marcas ajenas: el título
// es CLICK, no el de una revista que existe.
//
// Todo se mide en fracciones del lienzo, así la misma función dibuja la foto final (1080x1920) y la vista previa de la
// cámara (360x640). Las funciones que no tocan el lienzo (textos, partir líneas, cajas) se prueban sin navegador.

export const MESES = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE']
const SERIF = "'Bodoni Moda', 'Didot', 'Bodoni MT', Georgia, serif"
const SANS = "'Oswald', 'Arial Narrow', 'Roboto Condensed', Arial, sans-serif"

/** "2026-10-04" (o una fecha) a "OCTUBRE 2026"; sin fecha válida, el mes en curso. */
export function fechaPortada(fecha, hoy = new Date()) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(fecha || ''))
  const anio = m ? Number(m[1]) : hoy.getFullYear()
  const mes = m ? Number(m[2]) - 1 : hoy.getMonth()
  return (MESES[mes] || MESES[hoy.getMonth()]) + ' ' + anio
}

/** Los textos de la portada. `nombre` es el primer nombre del invitado (opcional); `evento`, el nombre de la feria o fiesta. */
export function textosPortada({ titulo = 'CLICK', nombre = '', evento = '', fecha = '' } = {}) {
  const limpio = String(nombre || '').trim()
  const lugar = String(evento || '').trim()
  return {
    cabecera: String(titulo || 'CLICK').trim().toLocaleUpperCase('es-CL') || 'CLICK',
    fecha: fechaPortada(fecha),
    edicion: 'EDICIÓN ESPECIAL',
    exclusiva: 'EXCLUSIVA',
    // Sirve de día (una feria) y de noche (un cumpleaños): nada de "la noche" fijo.
    bajada: lugar ? `Así se vivió ${lugar}` : 'Así se vivió la celebración',
    llamados: ['Los looks que todos comentan', 'Sus mejores poses'],
    antetitulo: 'LA ESTRELLA DE HOY',
    // Sin nombre, la portada le habla al invitado: la frase entera va en el lugar del nombre.
    titular: limpio ? limpio.toLocaleUpperCase('es-CL') : 'ERES TÚ',
    numero: 'N.º 1',
  }
}

/** Parte un texto en líneas que no pasen de `ancho` según `medir(texto)`. Una palabra más larga que el ancho va sola. */
export function partirLineas(texto, ancho, medir) {
  const palabras = String(texto || '').split(/\s+/).filter(Boolean)
  const lineas = []
  let actual = ''
  for (const p of palabras) {
    const prueba = actual ? actual + ' ' + p : p
    if (!actual || medir(prueba) <= ancho) actual = prueba
    else { lineas.push(actual); actual = p }
  }
  if (actual) lineas.push(actual)
  return lineas
}

/** Tamaño de letra más grande, entre `min` y `max`, con el que `texto` cabe en `ancho` (paso de 2 %). */
export function tamanoQueCabe(texto, ancho, max, min, medirConTamano) {
  let t = max
  while (t > min && medirConTamano(texto, t) > ancho) t = Math.max(min, t * 0.98)
  return t
}

/** Zonas de la portada en fracciones del lienzo. La cara suele caer en el centro: ahí no va ningún titular delantero. */
export const ZONAS = {
  cabecera: { x: 0.04, y: 0.03, w: 0.92, h: 0.17 },
  izquierda: { x: 0.045, y: 0.29, w: 0.31 },
  derecha: { x: 0.955, y: 0.45, w: 0.27 },
  titular: { x: 0.045, y: 0.705, w: 0.66 },
  codigo: { x: 0.80, y: 0.865, w: 0.155, h: 0.06 },
  cara: { x: 0.34, y: 0.12, w: 0.32, h: 0.40 },
}

function contraste(hex) {
  const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || ''))
  if (!m) return '#FFFFFF'
  const n = parseInt(m[1], 16)
  const lum = (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255
  return lum > 0.6 ? '#16121A' : '#FFFFFF'
}

// Código de barras de mentira, estable por evento: el mismo evento, el mismo código en todas sus portadas.
export function barrasDe(semilla, cuantas = 34) {
  let h = 2166136261
  for (const c of String(semilla || 'CLICK')) h = Math.imul(h ^ c.charCodeAt(0), 16777619) >>> 0
  const barras = []
  for (let i = 0; i < cuantas; i++) {
    h = Math.imul(h ^ (h >>> 13), 1274126177) >>> 0
    barras.push(1 + (h % 3))
  }
  return barras
}

function sombra(ctx, W, fuerte = 1) {
  ctx.shadowColor = `rgba(0,0,0,${0.38 * fuerte})`
  ctx.shadowBlur = W * 0.012 * fuerte
  ctx.shadowOffsetX = 0
  ctx.shadowOffsetY = W * 0.003 * fuerte
}

function espejar(ctx, W, espejo) {
  // La vista previa de la cámara frontal se voltea con CSS para que se vea como un espejo: el texto se dibuja ya
  // volteado, así queda derecho después del volteo.
  if (espejo) { ctx.translate(W, 0); ctx.scale(-1, 1) }
}

/** El título CLICK, detrás de la persona. */
export function dibujarCabecera(ctx, W, H, textos, { tinta = '#FFFFFF', espejo = false } = {}) {
  const z = ZONAS.cabecera
  ctx.save()
  espejar(ctx, W, espejo)
  const medir = (t, s) => { ctx.font = `900 ${s}px ${SERIF}`; return ctx.measureText(t).width }
  const tam = tamanoQueCabe(textos.cabecera, z.w * W, z.h * H * 1.45, z.h * H * 0.5, medir)
  ctx.font = `900 ${tam}px ${SERIF}`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'alphabetic'
  const m = ctx.measureText(textos.cabecera)
  const alto = m.actualBoundingBoxAscent || tam * 0.7
  ctx.fillStyle = tinta
  sombra(ctx, W, 0.6)
  ctx.fillText(textos.cabecera, W / 2, z.y * H + alto, z.w * W)
  // Edición y fecha en letra chica bajo las puntas del título, como en una revista: a los lados, donde no suele haber
  // cabeza (al centro la cortaba a media palabra). Van DETRÁS de la persona, como el título: un texto delante nunca debe
  // cruzar una cara. Sombra más fuerte porque caen sobre flashes y luces.
  const yInfo = z.y * H + alto + H * 0.03
  ctx.font = `700 ${H * 0.017}px ${SANS}`
  if ('letterSpacing' in ctx) ctx.letterSpacing = `${W * 0.005}px`
  sombra(ctx, W, 1.6)
  ctx.textAlign = 'left'
  ctx.fillText(textos.edicion, z.x * W + W * 0.01, yInfo, W * 0.3)
  if (textos.fecha) { ctx.textAlign = 'right'; ctx.fillText(textos.fecha, (z.x + z.w) * W - W * 0.01, yInfo, W * 0.3) }
  if ('letterSpacing' in ctx) ctx.letterSpacing = '0px'
  ctx.restore()
}

/** Titulares, nombre y código de barras, delante de la persona. */
export function dibujarTitulares(ctx, W, H, textos, { tinta = '#FFFFFF', acento = '#E11D74', espejo = false, evento = '' } = {}) {
  ctx.save()
  espejar(ctx, W, espejo)
  ctx.textBaseline = 'alphabetic'
  sombra(ctx, W)

  // Columna izquierda: etiqueta EXCLUSIVA y la bajada con el nombre del evento.
  const izq = ZONAS.izquierda
  let y = izq.y * H
  const etiqueta = H * 0.022
  ctx.font = `700 ${etiqueta}px ${SANS}`
  const anchoEtiqueta = ctx.measureText(textos.exclusiva).width + W * 0.03
  ctx.save()
  ctx.shadowColor = 'transparent'
  ctx.fillStyle = acento
  ctx.fillRect(izq.x * W, y - etiqueta * 1.05, anchoEtiqueta, etiqueta * 1.45)
  ctx.fillStyle = contraste(acento)
  ctx.textAlign = 'left'
  ctx.fillText(textos.exclusiva, izq.x * W + W * 0.015, y + etiqueta * 0.12)
  ctx.restore()
  y += etiqueta * 1.6
  ctx.fillStyle = tinta
  ctx.textAlign = 'left'
  let tamBajada = H * 0.031
  ctx.font = `700 ${tamBajada}px ${SANS}`
  let lineas = partirLineas(textos.bajada.toLocaleUpperCase('es-CL'), izq.w * W, (t) => ctx.measureText(t).width)
  while (lineas.length > 4 && tamBajada > H * 0.02) {
    tamBajada *= 0.92
    ctx.font = `700 ${tamBajada}px ${SANS}`
    lineas = partirLineas(textos.bajada.toLocaleUpperCase('es-CL'), izq.w * W, (t) => ctx.measureText(t).width)
  }
  for (const l of lineas.slice(0, 4)) { y += tamBajada * 1.08; ctx.fillText(l, izq.x * W, y) }

  // Columna derecha: dos llamados cortos.
  const der = ZONAS.derecha
  ctx.textAlign = 'right'
  let yd = der.y * H
  textos.llamados.forEach((llamado, i) => {
    const tam = H * (i === 0 ? 0.027 : 0.023)
    ctx.font = `${i === 0 ? 700 : 500} ${tam}px ${SANS}`
    for (const l of partirLineas(llamado.toLocaleUpperCase('es-CL'), der.w * W, (t) => ctx.measureText(t).width).slice(0, 3)) {
      yd += tam * 1.1
      ctx.fillText(l, der.x * W, yd)
    }
    yd += tam * 0.9
  })

  // Abajo: la frase y el nombre, el titular grande de la portada.
  const tit = ZONAS.titular
  ctx.textAlign = 'left'
  ctx.font = `700 ${H * 0.024}px ${SANS}`
  if ('letterSpacing' in ctx) ctx.letterSpacing = `${W * 0.004}px`
  ctx.fillText(textos.antetitulo, tit.x * W, tit.y * H)
  if ('letterSpacing' in ctx) ctx.letterSpacing = '0px'
  const medirNombre = (t, s) => { ctx.font = `italic 700 ${s}px ${SERIF}`; return ctx.measureText(t).width }
  const tamNombre = tamanoQueCabe(textos.titular, tit.w * W, H * 0.1, H * 0.05, medirNombre)
  ctx.font = `italic 700 ${tamNombre}px ${SERIF}`
  // Con el ancho máximo, un nombre que no cabe ni en la letra mínima se angosta en vez de salirse de la portada.
  ctx.fillText(textos.titular, tit.x * W, tit.y * H + tamNombre * 1.02, tit.w * W)

  // Código de barras con el número de la edición.
  const cod = ZONAS.codigo
  ctx.save()
  ctx.shadowColor = 'transparent'
  ctx.fillStyle = '#FFFFFF'
  ctx.fillRect(cod.x * W, cod.y * H, cod.w * W, cod.h * H)
  ctx.fillStyle = '#111111'
  const barras = barrasDe(evento || textos.bajada)
  const total = barras.reduce((a, b) => a + b + 1, 0)
  const unidad = (cod.w * W * 0.86) / total
  let x = cod.x * W + cod.w * W * 0.07
  for (const b of barras) { ctx.fillRect(x, cod.y * H + cod.h * H * 0.12, b * unidad, cod.h * H * 0.58); x += (b + 1) * unidad }
  ctx.font = `500 ${cod.h * H * 0.2}px ${SANS}`
  ctx.textAlign = 'center'
  ctx.fillText(textos.numero, cod.x * W + cod.w * W / 2, cod.y * H + cod.h * H * 0.93)
  ctx.restore()
  ctx.restore()
}

/** Las capas para `drawScene`: el título antes de la persona, los titulares después y el encuadre de portada. `encuadre`
 * lleva `suave: true` en la vista previa de la cámara (el mismo lienzo cuadro a cuadro). */
export function capasPortada(textos, estilo = {}, encuadre = {}) {
  return {
    antes: (ctx, W, H) => dibujarCabecera(ctx, W, H, textos, estilo),
    despues: (ctx, W, H) => dibujarTitulares(ctx, W, H, textos, estilo),
    encuadre: { cabeza: 0.095, zoomMax: 1.6, ...encuadre },
  }
}

// Las tipografías son libres (OFL) y viven en public/fonts/revista/ con su licencia. Sin ellas la portada igual sale, con
// las de respaldo del sistema.
const FUENTES = [
  ['Bodoni Moda', 'bodoni-moda-latin-900-normal.woff2', { weight: '900', style: 'normal' }],
  ['Bodoni Moda', 'bodoni-moda-latin-700-italic.woff2', { weight: '700', style: 'italic' }],
  ['Oswald', 'oswald-latin-500-normal.woff2', { weight: '500', style: 'normal' }],
  ['Oswald', 'oswald-latin-700-normal.woff2', { weight: '700', style: 'normal' }],
]
let cargando = null
export function cargarFuentesRevista(base) {
  if (cargando) return cargando
  if (typeof FontFace === 'undefined' || typeof document === 'undefined') return Promise.resolve(false)
  cargando = Promise.all(FUENTES.map(([familia, archivo, opciones]) => {
    const cara = new FontFace(familia, `url("${new URL(base + 'fonts/revista/' + archivo, location.href).href}")`, opciones)
    document.fonts.add(cara)
    return cara.load()
  })).then(() => true).catch(() => false)
  return cargando
}

/** Sin recorte de la persona (el segmentador no está o falló): la foto entera de fondo y la portada encima. */
export function dibujarPortadaSinRecorte(canvas, imagen, textos, estilo) {
  const W = canvas.width; const H = canvas.height
  const ctx = canvas.getContext('2d')
  const sw = imagen.naturalWidth || imagen.width; const sh = imagen.naturalHeight || imagen.height
  const k = Math.max(W / sw, H / sh)
  ctx.drawImage(imagen, (W - sw * k) / 2, (H - sh * k) / 2, sw * k, sh * k)
  dibujarCabecera(ctx, W, H, textos, estilo)
  dibujarTitulares(ctx, W, H, textos, estilo)
}
