import { loadImage } from './media.js'
import { drawScene } from './segmentation.js'
import { filterFor } from './contract.js'
import { capasPortada, dibujarPortadaSinRecorte } from './revista.js'

export async function filterImage(src, filter) {
  if (filter !== 'bn') return src
  const image = await loadImage(src)
  const canvas = document.createElement('canvas')
  canvas.width = image.naturalWidth; canvas.height = image.naturalHeight
  const ctx = canvas.getContext('2d')
  ctx.filter = filterFor(filter); ctx.drawImage(image, 0, 0)
  return canvas.toDataURL('image/jpeg', 0.92)
}

// `variante` (Portada de Revista): la escena que eligió el invitado en el menú. `portada`: textos y estilo del título y los
// titulares; sin recorte de la persona, la portada va igual sobre la foto entera en vez del marco.
export async function createFeriaPhoto({ source, segmenter, base, theme, frame, variante = null, portada = null }) {
  const start = performance.now()
  const image = await loadImage(source)
  let output
  const escena = variante?.escena || theme.images?.escena
  if (theme.modoFoto === 'fondo' && escena && segmenter) {
    let mask
    try {
      mask = await segmenter.mask(image)
      if (mask) {
        const scene = await loadImage(base + escena)
        const canvas = document.createElement('canvas')
        canvas.width = 1080; canvas.height = 1920
        drawScene(canvas, image, scene, mask, portada ? capasPortada(portada.textos, portada.estilo) : null)
        output = canvas.toDataURL('image/jpeg', 0.92)
      }
    } catch { /* marco de respaldo: misma foto, sin pedir otra captura */ }
    finally { mask?.image?.close() }
  }
  if (!output && portada) {
    const canvas = document.createElement('canvas')
    canvas.width = 1080; canvas.height = 1920
    dibujarPortadaSinRecorte(canvas, image, portada.textos, portada.estilo)
    output = canvas.toDataURL('image/jpeg', 0.92)
    if (segmenter) segmenter.metrics.fallback ||= 'portada'
  }
  if (!output) {
    if (segmenter) segmenter.metrics.fallback ||= 'frame'
    output = await frame(image)
  }
  if (segmenter) segmenter.metrics.sceneMs = performance.now() - start
  return output
}
