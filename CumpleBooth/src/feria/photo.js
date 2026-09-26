import { loadImage } from './media.js'
import { drawScene } from './segmentation.js'
import { filterFor } from './contract.js'

export async function filterImage(src, filter) {
  if (filter !== 'bn') return src
  const image = await loadImage(src)
  const canvas = document.createElement('canvas')
  canvas.width = image.naturalWidth; canvas.height = image.naturalHeight
  const ctx = canvas.getContext('2d')
  ctx.filter = filterFor(filter); ctx.drawImage(image, 0, 0)
  return canvas.toDataURL('image/jpeg', 0.92)
}

export async function createFeriaPhoto({ source, segmenter, base, theme, frame }) {
  const start = performance.now()
  const image = await loadImage(source)
  let output
  if (theme.modoFoto === 'fondo' && theme.images?.escena && segmenter) {
    let mask
    try {
      mask = await segmenter.mask(image)
      if (mask) {
        const scene = await loadImage(base + theme.images.escena)
        const canvas = document.createElement('canvas')
        canvas.width = 1080; canvas.height = 1920
        drawScene(canvas, image, scene, mask)
        output = canvas.toDataURL('image/jpeg', 0.92)
      }
    } catch { /* marco de respaldo: misma foto, sin pedir otra captura */ }
    finally { mask?.image?.close() }
  }
  if (!output) {
    if (segmenter) segmenter.metrics.fallback ||= 'frame'
    output = await frame(image)
  }
  if (segmenter) segmenter.metrics.sceneMs = performance.now() - start
  return output
}
