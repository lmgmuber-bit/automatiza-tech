/* Worker clásico: permite cancelar una inferencia bloqueada sin congelar la cámara. */
let segmenter
self.onmessage = async ({ data }) => {
  try {
    if (data.type === 'init') {
      self.exports = {}
      importScripts(data.base + 'feria/vision-segmenter.js')
      const { FilesetResolver, ImageSegmenter } = self.exports
      const files = await FilesetResolver.forVisionTasks(data.base + 'vendor/mediapipe')
      segmenter = await ImageSegmenter.createFromOptions(files, {
        baseOptions: { modelAssetPath: data.base + 'vendor/mediapipe/selfie_segmenter.tflite', delegate: 'CPU' },
        runningMode: 'IMAGE', outputConfidenceMasks: true, outputCategoryMask: false,
      })
      self.postMessage({ id: data.id, ready: true })
      return
    }
    const start = performance.now()
    let handedBack = false
    try {
      const result = segmenter.segment(data.image)
      try {
        const masks = result.confidenceMasks
        const personIndex = segmenter.getLabels().findIndex((label) => /person|selfie|foreground/i.test(label))
        const mask = masks[personIndex >= 0 && personIndex < masks.length ? personIndex : masks.length - 1]
        const values = mask.getAsFloat32Array().slice()
        self.postMessage({ id: data.id, image: data.image, values: values.buffer, width: mask.width, height: mask.height, inferenceMs: performance.now() - start }, [values.buffer, data.image])
        handedBack = true
      } finally { result.close() }
    } finally { if (!handedBack) data.image.close() }
  } catch { self.postMessage({ id: data.id, error: true }) }
}
