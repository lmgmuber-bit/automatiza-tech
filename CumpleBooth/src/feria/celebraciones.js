// Graduación y Cumpleaños con número (05-10-2026). Las dos usan el número dorado de Año Nuevo detrás de la persona y el saludo
// delante (anioNuevo.js): en Graduación el número es el año de la generación, con "GENERACIÓN" encima y el logo del colegio
// (el mismo campo de logo de la ficha del evento) en una placa abajo; en Cumpleaños es la edad que se celebra, que se escribe
// en la ficha del evento junto con el nombre del festejado. Como en Año Nuevo, la coronilla queda bajo el número.
import { SANS, espejar, sombra } from './revista.js'
import { dibujarAnio, dibujarSaludo } from './anioNuevo.js'
import { dibujarPlacaLogo } from './muroLogos.js'

export const ZONAS_GRAD = { etiqueta: 0.045, numero: { y: 0.06, h: 0.12, w: 0.8 }, cabeza: 0.225, logo: 0.885 }
export const ZONAS_CUMPLE = { numero: { y: 0.025, h: 0.17, w: 0.7 }, cabeza: 0.24 }

/** El año de la generación: el de la fecha del evento (una licenciatura de diciembre es de ese año); sin fecha, el de hoy. */
export function anioDeGraduacion(fecha, hoy = new Date()) {
  const m = /^(\d{4})-\d{2}-\d{2}/.exec(String(fecha || ''))
  return m ? Number(m[1]) : hoy.getFullYear()
}

export function textosGraduacion({ nombre = '', fecha = '', hoy = new Date() } = {}) {
  return {
    anio: String(anioDeGraduacion(fecha, hoy)),
    etiqueta: 'GENERACIÓN',
    saludo: '¡Felicitaciones!',
    nombre: String(nombre || '').trim().toLocaleUpperCase('es-CL'),
  }
}

/** La edad, solo si es un número de 1 a 120; si no, '' (la portada sale sin número). */
export function edadValida(numero) {
  const n = String(numero ?? '').trim()
  return /^\d{1,3}$/.test(n) && Number(n) >= 1 && Number(n) <= 120 ? String(Number(n)) : ''
}

/** Los textos del cumpleaños: la edad grande y el saludo al festejado (no al invitado de la foto: él está de visita). */
export function textosCumpleanos({ numero = '', festejado = '' } = {}) {
  const quien = String(festejado || '').trim()
  return { anio: edadValida(numero), saludo: quien ? `¡Feliz cumpleaños, ${quien}!` : '¡Feliz cumpleaños!', nombre: '' }
}

function dibujarEtiqueta(ctx, W, H, texto, { espejo = false } = {}) {
  ctx.save()
  espejar(ctx, W, espejo)
  ctx.textAlign = 'center'
  ctx.textBaseline = 'alphabetic'
  sombra(ctx, W, 1.4)
  ctx.font = `700 ${H * 0.022}px ${SANS}`
  if ('letterSpacing' in ctx) ctx.letterSpacing = `${W * 0.012}px`
  ctx.fillStyle = '#F3D88A'
  ctx.fillText(texto, W / 2, ZONAS_GRAD.etiqueta * H, W * 0.8)
  if ('letterSpacing' in ctx) ctx.letterSpacing = '0px'
  ctx.restore()
}

function fotoEntera(canvas, imagen) {
  const W = canvas.width; const H = canvas.height; const ctx = canvas.getContext('2d')
  const sw = imagen.naturalWidth || imagen.width; const sh = imagen.naturalHeight || imagen.height
  const k = Math.max(W / sw, H / sh)
  ctx.drawImage(imagen, (W - sw * k) / 2, (H - sh * k) / 2, sw * k, sh * k)
  return ctx
}

/** Graduación: GENERACIÓN y el año detrás, el saludo delante y, si el evento tiene logo, la placa del colegio abajo al centro. */
export function capasGraduacion(textos, { logo = null, tono = 'claro', espejo = false } = {}, encuadre = {}) {
  const antes = (ctx, W, H) => {
    dibujarEtiqueta(ctx, W, H, textos.etiqueta, { espejo })
    dibujarAnio(ctx, W, H, textos, { espejo, zona: ZONAS_GRAD.numero })
  }
  const despues = (ctx, W, H) => {
    dibujarSaludo(ctx, W, H, textos, { espejo })
    if (logo) dibujarPlacaLogo(ctx, W, H, logo, { tono, espejo, y: ZONAS_GRAD.logo, ancho: 0.24, alto: 0.05, centrado: true })
  }
  return {
    antes, despues,
    encuadre: { cabeza: ZONAS_GRAD.cabeza, zoomMax: 1.6, ...encuadre },
    sinRecorte: (canvas, imagen) => { const ctx = fotoEntera(canvas, imagen); antes(ctx, canvas.width, canvas.height); despues(ctx, canvas.width, canvas.height) },
  }
}

/** Cumpleaños: la edad detrás y el saludo al festejado delante. Sin edad, solo el saludo y sin encuadre de portada. */
export function capasCumpleanos(textos, { espejo = false } = {}, encuadre = {}) {
  const antes = textos.anio ? (ctx, W, H) => dibujarAnio(ctx, W, H, textos, { espejo, zona: ZONAS_CUMPLE.numero, fondoClaro: true }) : null
  const despues = (ctx, W, H) => dibujarSaludo(ctx, W, H, textos, { espejo })
  return {
    antes, despues,
    encuadre: textos.anio ? { cabeza: ZONAS_CUMPLE.cabeza, zoomMax: 1.6, ...encuadre } : null,
    sinRecorte: (canvas, imagen) => { const ctx = fotoEntera(canvas, imagen); antes?.(ctx, canvas.width, canvas.height); despues(ctx, canvas.width, canvas.height) },
  }
}
