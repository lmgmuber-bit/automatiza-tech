// Año Nuevo (adultos, 04-10-2026). La escena (fuegos artificiales sobre la bahía) se generó sin un solo número: el año lo
// dibuja el código arriba, en dorado y DETRÁS de la persona, y el saludo va delante, abajo. A diferencia del título de la
// revista, el año no se deja tapar: la persona se encuadra con la coronilla bajo los números, porque "2027" con la cabeza
// encima ya no se lee como año.
import { SERIF, SANS, sombra, espejar, tamanoQueCabe } from './revista.js'

/** El año que se celebra: un evento de noviembre o diciembre (las fiestas de fin de año de las empresas parten en noviembre)
 * celebra el que viene; uno de enero, el que empieza. Sin fecha válida, se mira la de hoy. */
export function anioQueSeCelebra(fecha, hoy = new Date()) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(fecha || ''))
  const anio = m ? Number(m[1]) : hoy.getFullYear()
  const mes = m ? Number(m[2]) : hoy.getMonth() + 1
  return mes >= 11 ? anio + 1 : anio
}

/** Los textos: el año, el saludo y, si lo escribió, el primer nombre del invitado (va en mayúsculas bajo el saludo). */
export function textosAnioNuevo({ nombre = '', fecha = '', hoy = new Date() } = {}) {
  return {
    anio: String(anioQueSeCelebra(fecha, hoy)),
    saludo: '¡Feliz Año Nuevo!',
    nombre: String(nombre || '').trim().toLocaleUpperCase('es-CL'),
  }
}

// Zonas en fracciones del lienzo, como en la revista: la misma función dibuja la foto (1080x1920) y la vista previa.
// `cabeza`: la coronilla al 22,5 % del alto. Los números terminan cerca del 17,6 % y el borde de arriba de la máscara (pelo,
// cabeza redonda) queda algo más alto que la caja medida: con menos aire, la cabeza alcanza a tapar el pie del año.
export const ZONAS_ANIO = { anio: { y: 0.03, h: 0.15, w: 0.9 }, saludo: { y: 0.80, w: 0.86 }, cabeza: 0.225 }

/** El año, grande y dorado, detrás de la persona. */
// `zona`: Graduación lo usa más abajo (deja lugar a GENERACIÓN) y Cumpleaños más grande (la edad tiene dos cifras).
// `fondoClaro`: sobre una escena clara (los globos del cumpleaños) el dorado se pierde; va con sombra oscura y borde marcado.
export function dibujarAnio(ctx, W, H, textos, { espejo = false, zona = ZONAS_ANIO.anio, fondoClaro = false } = {}) {
  const z = zona
  ctx.save()
  espejar(ctx, W, espejo)
  const medir = (t, s) => { ctx.font = `900 ${s}px ${SERIF}`; return ctx.measureText(t).width }
  const tam = tamanoQueCabe(textos.anio, z.w * W, z.h * H * 1.5, z.h * H * 0.6, medir)
  ctx.font = `900 ${tam}px ${SERIF}`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'alphabetic'
  const alto = ctx.measureText(textos.anio).actualBoundingBoxAscent || tam * 0.7
  const y = z.y * H + alto
  // Dorado con luz arriba, como un número de globo metálico; resplandor cálido para que se lea sobre los fuegos.
  const dorado = typeof ctx.createLinearGradient === 'function' ? ctx.createLinearGradient(0, z.y * H, 0, y) : null
  if (dorado) {
    dorado.addColorStop(0, '#FFF4C9')
    dorado.addColorStop(0.55, '#E8C66A')
    dorado.addColorStop(1, '#A8792A')
  }
  ctx.shadowColor = fondoClaro ? 'rgba(45, 18, 22, 0.7)' : 'rgba(255, 196, 92, 0.55)'
  ctx.shadowBlur = W * (fondoClaro ? 0.02 : 0.03)
  ctx.shadowOffsetY = fondoClaro ? W * 0.006 : 0
  ctx.fillStyle = dorado || '#E8C66A'
  ctx.fillText(textos.anio, W / 2, y, z.w * W)
  ctx.shadowColor = 'transparent'
  ctx.shadowOffsetY = 0
  ctx.lineWidth = Math.max(1, W * (fondoClaro ? 0.006 : 0.0025))
  ctx.strokeStyle = fondoClaro ? 'rgba(90, 40, 30, 0.9)' : 'rgba(70, 45, 5, 0.45)'
  if (typeof ctx.strokeText === 'function') ctx.strokeText(textos.anio, W / 2, y, z.w * W)
  ctx.restore()
}

/** El saludo y el nombre, delante de la persona, abajo. */
export function dibujarSaludo(ctx, W, H, textos, { espejo = false } = {}) {
  const z = ZONAS_ANIO.saludo
  ctx.save()
  espejar(ctx, W, espejo)
  ctx.textAlign = 'center'
  ctx.textBaseline = 'alphabetic'
  sombra(ctx, W, 1.4)
  const medir = (t, s) => { ctx.font = `italic 700 ${s}px ${SERIF}`; return ctx.measureText(t).width }
  const tam = tamanoQueCabe(textos.saludo, z.w * W, H * 0.07, H * 0.04, medir)
  ctx.font = `italic 700 ${tam}px ${SERIF}`
  ctx.fillStyle = '#FFFFFF'
  ctx.fillText(textos.saludo, W / 2, z.y * H, z.w * W)
  if (textos.nombre) {
    ctx.font = `700 ${H * 0.03}px ${SANS}`
    if ('letterSpacing' in ctx) ctx.letterSpacing = `${W * 0.008}px`
    ctx.fillStyle = '#F3D88A'
    ctx.fillText(textos.nombre, W / 2, z.y * H + tam * 0.95, z.w * W)
    if ('letterSpacing' in ctx) ctx.letterSpacing = '0px'
  }
  ctx.restore()
}

/** Las capas para `drawScene`, el encuadre (coronilla bajo el año) y el respaldo sin recorte. */
export function capasAnioNuevo(textos, { espejo = false } = {}, encuadre = {}) {
  return {
    antes: (ctx, W, H) => dibujarAnio(ctx, W, H, textos, { espejo }),
    despues: (ctx, W, H) => dibujarSaludo(ctx, W, H, textos, { espejo }),
    encuadre: { cabeza: ZONAS_ANIO.cabeza, zoomMax: 1.6, ...encuadre },
    // Sin recorte de la persona, la foto entera de fondo y el año y el saludo encima.
    sinRecorte: (canvas, imagen) => {
      const W = canvas.width; const H = canvas.height; const ctx = canvas.getContext('2d')
      const sw = imagen.naturalWidth || imagen.width; const sh = imagen.naturalHeight || imagen.height
      const k = Math.max(W / sw, H / sh)
      ctx.drawImage(imagen, (W - sw * k) / 2, (H - sh * k) / 2, sw * k, sh * k)
      dibujarAnio(ctx, W, H, textos)
      dibujarSaludo(ctx, W, H, textos)
    },
  }
}
