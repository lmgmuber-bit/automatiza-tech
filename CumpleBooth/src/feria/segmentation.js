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

export function drawScene(canvas, source, scene, mask) {
  const W = canvas.width; const H = canvas.height
  const ctx = canvas.getContext('2d')
  ctx.clearRect(0, 0, W, H)
  ctx.drawImage(scene, 0, 0, W, H)
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
  const box = subjectPlacement(sw, sh, W, H)
  ctx.drawImage(cutout, box.x, box.y, box.width, box.height)
}

export function previewDecision(frames, elapsedMs, minimum = 15) {
  const fps = frames * 1000 / Math.max(1, elapsedMs)
  return { fps, enabled: fps >= minimum }
}
