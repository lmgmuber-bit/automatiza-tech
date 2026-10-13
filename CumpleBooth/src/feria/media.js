import { filterFor, souvenirLines } from './contract.js'

export function loadImage(src) {
  return new Promise((resolve, reject) => {
    if (!src) { resolve(null); return }
    const image = new Image()
    const timer = setTimeout(() => { image.onload = image.onerror = null; reject(new Error('La imagen demoró demasiado en cargar.')) }, 15000)
    image.onload = () => { clearTimeout(timer); resolve(image) }
    image.onerror = () => { clearTimeout(timer); reject(new Error('No pudimos cargar la imagen. Revisa la conexión.')) }
    image.src = src
  })
}

export async function filteredPhoto(src, filter) {
  const image = await loadImage(src)
  if (filter !== 'bn') return image
  const canvas = document.createElement('canvas')
  canvas.width = image.naturalWidth; canvas.height = image.naturalHeight
  const context = canvas.getContext('2d')
  context.filter = filterFor(filter)
  context.drawImage(image, 0, 0)
  return canvas
}

// Se reserva espacio NUEVO bajo la composición completa; no se tapa la cara,
// el nombre del personaje ni el sello del diploma existente.
// Franja de recuerdo (Luis, 26-09, en la feria): pendones de la feria, el logo real de CumpleClick y el
// organizador nombrado, sin su logo. Es la misma imagen para la foto impresa y para la digital del QR.
const PENDONES = ['#D52B1E', '#FFFFFF', '#0039A6']
let logoMarca = null
const cargarLogo = () => (logoMarca ||= loadImage(new URL('brand/cumpleclick-mark.svg', document.baseURI).href).catch(() => null))

export async function withRemembrance(src, feria, held) {
  const [image, logo] = await Promise.all([loadImage(src), cargarLogo()])
  const canvas = document.createElement('canvas')
  canvas.width = image.naturalWidth; canvas.height = image.naturalHeight
  const W = canvas.width; const H = canvas.height; const footer = Math.round(H * 0.13)
  const top = H - footer
  const context = canvas.getContext('2d')
  context.fillStyle = '#fff8eb'; context.fillRect(0, 0, W, H)
  const scale = top / H
  context.drawImage(image, (W - W * scale) / 2, 0, W * scale, top)
  context.fillStyle = '#47206e'; context.fillRect(0, top, W, footer)

  // Guirnalda: un hilo y banderines triangulares colgando del borde de la franja.
  const cuantos = 22; const paso = W / cuantos; const alto = footer * 0.2
  context.strokeStyle = '#f4e3c3'; context.lineWidth = Math.max(1, W * 0.002)
  context.beginPath(); context.moveTo(0, top + 1); context.lineTo(W, top + 1); context.stroke()
  for (let i = 0; i < cuantos; i++) {
    const x = i * paso
    context.fillStyle = PENDONES[i % 3]
    context.beginPath(); context.moveTo(x + paso * 0.1, top + 1); context.lineTo(x + paso * 0.9, top + 1); context.lineTo(x + paso * 0.5, top + alto); context.closePath(); context.fill()
  }

  const { titulo, organiza, etiqueta } = souvenirLines(feria, held)
  const line = (text, x, y, max, size, align = 'center') => {
    if (!text) return
    let font = size
    context.textAlign = align; context.textBaseline = 'middle'; context.fillStyle = '#ffffff'
    do { context.font = `700 ${font}px 'Baloo 2', sans-serif`; font -= 1 } while (context.measureText(text).width > max && font > 12)
    context.fillText(text, x, y, max)
  }
  line(titulo, W / 2, top + footer * 0.44, W * 0.92, W * 0.042)

  // Fila de abajo: marca a la izquierda, organizador al centro, número a la derecha.
  const filaY = top + footer * 0.77
  const lado = footer * 0.3
  let x = W * 0.04
  if (logo) { context.drawImage(logo, x, filaY - lado / 2, lado, lado); x += lado + W * 0.012 }
  line('CumpleClick', x, filaY, W * 0.2, W * 0.03, 'left')
  line(organiza, W * 0.54, filaY, W * 0.42, W * 0.027)
  line(etiqueta, W * 0.96, filaY, W * 0.16, W * 0.045, 'right')
  return canvas.toDataURL('image/jpeg', 0.92)
}

export function downloadImage(src, label) {
  const link = document.createElement('a')
  link.href = src
  link.download = `cumpleclick-${String(label || 'foto').replace(/[^a-zA-Z0-9-]/g, '')}.jpg`
  document.body.appendChild(link); link.click(); link.remove()
}
