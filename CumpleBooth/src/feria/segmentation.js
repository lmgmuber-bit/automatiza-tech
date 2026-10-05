// Se instancia por temática de feria; nunca lo carga una fiesta normal.
export function createSegmenter(base, { makeWorker = () => new Worker(new URL('./segment.worker.js', import.meta.url)), timeoutMs = 4000 } = {}) {
  let worker; let disposed = false; let sequence = 0
  const waiting = new Map()
  const metrics = { available: false, previewFps: null, inferenceMs: null, compositionMs: null, preview: 'loading', fallback: '' }
  function dispose(reason = 'closed') {
    disposed = true; metrics.available = false
    worker?.terminate()
    for (const pending of waiting.values()) { clearTimeout(pending.timer); pending.reject(new Error(reason)) }
    waiting.clear()
  }
  function request(payload, transfers = []) {
    return new Promise((resolve, reject) => {
      if (disposed) { reject(new Error('closed')); return }
      const id = ++sequence
      const timer = setTimeout(() => { metrics.fallback = 'timeout'; dispose('timeout') }, timeoutMs)
      waiting.set(id, { resolve, reject, timer })
      worker.postMessage({ ...payload, id }, transfers)
    })
  }
  let ready
  try {
    worker = makeWorker()
    worker.onmessage = ({ data }) => {
      const pending = waiting.get(data.id)
      if (!pending) { data.image?.close(); return }
      clearTimeout(pending.timer); waiting.delete(data.id)
      data.error ? pending.reject(new Error('segmentation')) : pending.resolve(data)
    }
    worker.onerror = () => { metrics.fallback = 'unavailable'; dispose('worker') }
    ready = request({ type: 'init', base: new URL(base, globalThis.location?.href || 'http://localhost/').href }).then(() => {
      metrics.available = true; metrics.preview = 'measuring'; return true
    }).catch(() => { metrics.preview = 'frame'; metrics.fallback ||= 'unavailable'; return false })
  } catch { metrics.preview = 'frame'; metrics.fallback = 'unavailable'; ready = Promise.resolve(false) }
  return {
    ready, metrics, dispose,
    async mask(image) {
      if (!await ready || disposed) return null
      try {
        const bitmap = await createImageBitmap(image)
        if (disposed) { bitmap.close(); return null }
        const result = await request({ type: 'mask', image: bitmap }, [bitmap])
        metrics.inferenceMs = result.inferenceMs
        return { image: result.image, values: new Float32Array(result.values), width: result.width, height: result.height }
      } catch { metrics.preview = 'frame'; metrics.fallback = 'inference'; return null }
    },
  }
}

export function softAlpha(confidence) {
  const value = Math.max(0, Math.min(1, (confidence - 0.2) / 0.6))
  return Math.round(255 * value * value * (3 - 2 * value))
}

export function subjectPlacement(sourceWidth, sourceHeight, width, height) {
  // No agrandar al sujeto según su máscara: conserva la escala del encuadre de cámara.
  const scale = Math.min(width / sourceWidth, height / sourceHeight)
  return { x: (width - sourceWidth * scale) / 2, y: height - sourceHeight * scale, width: sourceWidth * scale, height: sourceHeight * scale }
}

// Caja de la persona en la máscara, en fracciones del cuadro de la cámara. Cuenta filas y columnas con al menos un 2 % de
// píxeles de persona, así un punto suelto (un afiche detrás) no estira la caja. Sin persona, null.
export function cajaDeMascara(valores, mw, mh, umbral = 0.5) {
  const filas = new Uint32Array(mh); const columnas = new Uint32Array(mw)
  for (let i = 0; i < valores.length; i++) if (valores[i] >= umbral) { filas[(i / mw) | 0]++; columnas[i % mw]++ }
  const minFila = Math.max(2, mw * 0.02); const minColumna = Math.max(2, mh * 0.02)
  let top = -1; let bottom = -1; let left = -1; let right = -1
  for (let y = 0; y < mh; y++) if (filas[y] >= minFila) { if (top < 0) top = y; bottom = y }
  for (let x = 0; x < mw; x++) if (columnas[x] >= minColumna) { if (left < 0) left = x; right = x }
  return top < 0 || left < 0 ? null : { top: top / mh, bottom: (bottom + 1) / mh, left: left / mw, right: (right + 1) / mw }
}

// Portada de Revista (04-10). Las otras temáticas conservan la escala de la cámara (subjectPlacement); en una portada la
// persona se encuadra como en revista: la coronilla queda a `cabeza` del alto, montada sobre el pie del título para taparlo
// un poco, y el cuerpo centrado. Nunca se achica bajo la escala de siempre ni se agranda más de `zoomMax` veces (la imagen
// se pondría borrosa), salvo cuando la cabeza ya toca el borde de arriba de la cámara: ahí el cuadro se estira hasta el
// borde del lienzo para que el corte no quede a la vista.
export function encuadrePortada(caja, sw, sh, W, H, { cabeza = 0.095, zoomMax = 1.6 } = {}) {
  const base = subjectPlacement(sw, sh, W, H)
  const fit = base.width / sw
  let s
  if (caja.top <= 0.01) s = Math.max(fit, H / sh)
  else s = Math.min(Math.max((H * (1 - cabeza)) / (sh * (1 - caja.top)), fit), fit * zoomMax)
  const width = sw * s; const height = sh * s
  const centro = ((caja.left + caja.right) / 2) * width
  // Más ancho que el lienzo: se corre para centrar a la persona sin dejar ver los bordes del cuadro de la cámara.
  const x = width >= W ? Math.min(0, Math.max(W - width, W / 2 - centro)) : (W - width) / 2
  return { x, y: H - height, width, height }
}

function encuadreDe(canvas, mask, sw, sh, W, H, opciones) {
  const caja = cajaDeMascara(mask.values, mask.width, mask.height)
  const nuevo = caja ? encuadrePortada(caja, sw, sh, W, H, opciones) : subjectPlacement(sw, sh, W, H)
  // En la vista previa (el mismo lienzo cuadro a cuadro) se suaviza, para que el zoom no tiemble con el borde de la máscara.
  const previo = opciones.suave ? canvas.__encuadre : null
  const box = previo ? { x: previo.x + (nuevo.x - previo.x) * 0.25, y: previo.y + (nuevo.y - previo.y) * 0.25,
    width: previo.width + (nuevo.width - previo.width) * 0.25, height: previo.height + (nuevo.height - previo.height) * 0.25 } : nuevo
  if (opciones.suave) canvas.__encuadre = box
  return box
}

// `capas` (Portada de Revista, 04-10): `antes` dibuja entre la escena y la persona (el título que la cabeza tapa) y
// `despues` encima de la persona (los titulares); con `encuadre`, la persona se encuadra como en portada. Sin capas, la
// escena de siempre.
export function drawScene(canvas, source, scene, mask, capas = null) {
  const W = canvas.width; const H = canvas.height
  const ctx = canvas.getContext('2d')
  ctx.clearRect(0, 0, W, H)
  ctx.drawImage(scene, 0, 0, W, H)
  capas?.antes?.(ctx, W, H)
  const mw = mask.width; const mh = mask.height
  const alpha = document.createElement('canvas'); alpha.width = mw; alpha.height = mh
  const ac = alpha.getContext('2d'); const pixels = ac.createImageData(mw, mh)
  for (let i = 0; i < mask.values.length; i++) {
    pixels.data[i * 4] = pixels.data[i * 4 + 1] = pixels.data[i * 4 + 2] = 255
    pixels.data[i * 4 + 3] = softAlpha(mask.values[i])
  }
  ac.putImageData(pixels, 0, 0)
  const sw = source.videoWidth || source.naturalWidth || source.width
  const sh = source.videoHeight || source.naturalHeight || source.height
  const cutout = document.createElement('canvas'); cutout.width = sw; cutout.height = sh
  const cut = cutout.getContext('2d')
  cut.drawImage(source, 0, 0, sw, sh)
  cut.globalCompositeOperation = 'destination-in'
  cut.filter = 'blur(1px)'
  cut.drawImage(alpha, 0, 0, sw, sh)
  const box = capas?.encuadre ? encuadreDe(canvas, mask, sw, sh, W, H, capas.encuadre) : subjectPlacement(sw, sh, W, H)
  ctx.drawImage(cutout, box.x, box.y, box.width, box.height)
  capas?.despues?.(ctx, W, H)
}

export function previewDecision(frames, elapsedMs, minimum = 15) {
  const fps = frames * 1000 / Math.max(1, elapsedMs)
  return { fps, enabled: fps >= minimum }
}
