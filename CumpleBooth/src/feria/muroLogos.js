// Empresa (adultos, 04-10-2026): el muro de prensa ("step and repeat") de los eventos de empresa, armado por el código con el
// logo del cliente repetido detrás de la persona, como el lienzo impreso de un lanzamiento. Ninguna IA toca el logo: se sube
// en la ficha del evento (Admin -> Ferias) y el kiosco lo repite tal cual. Sin logo, el muro repite en letras el nombre de
// quien organiza. La escena de fondo (fondo-escena.jpg) es un muro claro y liso; si el logo es claro (por ejemplo blanco
// sobre transparente), el muro pasa a oscuro para que se vea.
import { SANS, espejar, tamanoQueCabe } from './revista.js'
import { loadImage } from './media.js'

export const MURO = { claro: '#F6F5F2', oscuro: '#17171D', letras: { claro: '#2B2B33', oscuro: '#F1F1F1' } }

/**
 * 'oscuro' si el logo es claro (sobre un muro blanco no se vería), 'claro' si no. `rgba` son los píxeles del logo en chico.
 * Un PNG con transparencia cuenta todo lo opaco, también lo blanco (es dibujo). Un logo sin transparencia (un JPG) trae
 * fondo blanco: lo casi blanco se ignora y se mira solo el dibujo.
 */
export function tonoDeLogo(rgba) {
  const total = rgba.length / 4
  let opacos = 0
  for (let i = 3; i < rgba.length; i += 4) if (rgba[i] >= 128) opacos++
  const conAlfa = total - opacos > total * 0.05
  let dibujo = 0; let claros = 0
  for (let i = 0; i < rgba.length; i += 4) {
    if (rgba[i + 3] < 128) continue
    const l = (0.299 * rgba[i] + 0.587 * rgba[i + 1] + 0.114 * rgba[i + 2]) / 255
    if (!conAlfa && l > 0.92) continue
    dibujo++
    if (l > 0.72) claros++
  }
  if (!dibujo) return conAlfa ? 'oscuro' : 'claro'
  return claros / dibujo > 0.4 ? 'oscuro' : 'claro'
}

/** Centros de los logos del muro: filas a `fila` del alto y columnas a `paso` del ancho, una fila corrida medio paso. Pasa de
 * los bordes para que no queden huecos en las orillas. */
export function grillaMuro(W, H, { paso = 0.34, fila = 0.105 } = {}) {
  const dx = W * paso; const dy = H * fila
  const centros = []
  for (let r = 0, y = dy * 0.45; y < H + dy * 0.5; r++, y += dy) {
    for (let x = (r % 2 ? dx : dx / 2) - dx; x < W + dx; x += dx) centros.push({ x, y })
  }
  return centros
}

/** Lo que mide una imagen de `iw`x`ih` metida en una caja de `cw`x`ch` sin deformarla. */
export function caber(iw, ih, cw, ch) {
  const k = Math.min(cw / iw, ch / ih)
  return { w: iw * k, h: ih * k }
}

function dibujarMuro(ctx, W, H, { logo, texto, tono }, { espejo = false } = {}) {
  ctx.save()
  espejar(ctx, W, espejo)
  if (tono === 'oscuro') { ctx.fillStyle = MURO.oscuro; ctx.fillRect(0, 0, W, H) }
  const cw = W * 0.22; const ch = H * 0.055
  if (logo) {
    const lw = logo.naturalWidth || logo.width; const lh = logo.naturalHeight || logo.height
    const m = caber(lw, lh, cw, ch)
    for (const c of grillaMuro(W, H)) ctx.drawImage(logo, c.x - m.w / 2, c.y - m.h / 2, m.w, m.h)
  } else {
    const letras = String(texto || 'CumpleClick').toLocaleUpperCase('es-CL')
    const medir = (t, s) => { ctx.font = `700 ${s}px ${SANS}`; return ctx.measureText(t).width }
    const tam = tamanoQueCabe(letras, cw, H * 0.028, H * 0.012, medir)
    ctx.font = `700 ${tam}px ${SANS}`
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillStyle = MURO.letras[tono === 'oscuro' ? 'oscuro' : 'claro']
    for (const c of grillaMuro(W, H)) ctx.fillText(letras, c.x, c.y, cw)
  }
  // Luz de muro iluminado: el centro más claro y las orillas un poco más oscuras, como en una foto de prensa.
  if (typeof ctx.createRadialGradient === 'function') {
    const luz = ctx.createRadialGradient(W / 2, H * 0.42, W * 0.2, W / 2, H * 0.42, H * 0.75)
    luz.addColorStop(0, 'rgba(0,0,0,0)')
    luz.addColorStop(1, 'rgba(0,0,0,0.16)')
    ctx.fillStyle = luz
    ctx.fillRect(0, 0, W, H)
  }
  ctx.restore()
}

/** Las capas para `drawScene`. El muro va detrás de la persona; sin encuadre de portada, para que el logo se vea alrededor
 * de la cabeza. Sin recorte, la foto entera y el logo en una placa arriba a la izquierda. */
export function capasMuroLogos({ logo = null, texto = '', tono = 'claro' } = {}, { espejo = false } = {}) {
  return {
    antes: (ctx, W, H) => dibujarMuro(ctx, W, H, { logo, texto, tono }, { espejo }),
    despues: null,
    encuadre: null,
    sinRecorte: (canvas, imagen) => {
      const W = canvas.width; const H = canvas.height; const ctx = canvas.getContext('2d')
      const sw = imagen.naturalWidth || imagen.width; const sh = imagen.naturalHeight || imagen.height
      const k = Math.max(W / sw, H / sh)
      ctx.drawImage(imagen, (W - sw * k) / 2, (H - sh * k) / 2, sw * k, sh * k)
      if (!logo) return
      const m = caber(logo.naturalWidth || logo.width, logo.naturalHeight || logo.height, W * 0.26, H * 0.06)
      const pad = W * 0.02; const x = W * 0.04; const y = H * 0.03
      ctx.fillStyle = tono === 'oscuro' ? MURO.oscuro : '#FFFFFF'
      ctx.globalAlpha = 0.92
      ctx.fillRect(x, y, m.w + pad * 2, m.h + pad * 2)
      ctx.globalAlpha = 1
      ctx.drawImage(logo, x + pad, y + pad, m.w, m.h)
    },
  }
}

/** El logo del evento, o null si no hay o no carga (el muro sigue con letras). */
export async function cargarLogo(url) {
  if (!url) return null
  try { return await loadImage(url) } catch { return null }
}

/** El tono del logo ya cargado, mirando una copia chica del mismo formato (sin márgenes: un margen transparente haría pasar
 * un JPG por un PNG con transparencia). */
export function tonoDeImagen(imagen) {
  try {
    const m = caber(imagen.naturalWidth || imagen.width, imagen.naturalHeight || imagen.height, 48, 48)
    const c = document.createElement('canvas'); c.width = Math.max(1, Math.round(m.w)); c.height = Math.max(1, Math.round(m.h))
    const ctx = c.getContext('2d', { willReadFrequently: true })
    ctx.drawImage(imagen, 0, 0, c.width, c.height)
    return tonoDeLogo(ctx.getImageData(0, 0, c.width, c.height).data)
  } catch { return 'claro' }
}
