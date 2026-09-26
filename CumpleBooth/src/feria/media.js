import { filterFor, footerLines } from './contract.js'

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
export async function withRemembrance(src, feria, held) {
  const image = await loadImage(src)
  const canvas = document.createElement('canvas')
  canvas.width = image.naturalWidth; canvas.height = image.naturalHeight
  const W = canvas.width; const H = canvas.height; const footer = Math.round(H * 0.105)
  const context = canvas.getContext('2d')
  context.fillStyle = '#fff8eb'; context.fillRect(0, 0, W, H)
  const scale = (H - footer) / H
  context.drawImage(image, (W - W * scale) / 2, 0, W * scale, H - footer)
  context.fillStyle = '#47206e'; context.fillRect(0, H - footer, W, footer)
  const [memory, organizer, label] = footerLines(feria, held)
  const line = (text, x, y, max, size, align = 'center') => {
    let font = size
    context.textAlign = align; context.textBaseline = 'middle'; context.fillStyle = '#ffffff'
    do { context.font = `700 ${font}px 'Baloo 2', sans-serif`; font -= 1 } while (context.measureText(text).width > max && font > 12)
    context.fillText(text, x, y, max)
  }
  line(memory, W / 2, H - footer * 0.70, W * 0.90, W * 0.04)
  line(organizer, W * 0.05, H - footer * 0.27, W * 0.67, W * 0.033, 'left')
  line(label, W * 0.95, H - footer * 0.27, W * 0.22, W * 0.047, 'right')
  return canvas.toDataURL('image/jpeg', 0.92)
}

export function downloadImage(src, label) {
  const link = document.createElement('a')
  link.href = src
  link.download = `cumpleclick-${String(label || 'foto').replace(/[^a-zA-Z0-9-]/g, '')}.jpg`
  document.body.appendChild(link); link.click(); link.remove()
}
