/**
 * Detector de caras para Asómate (MediaPipe Tasks Vision, modelo BlazeFace de corto alcance).
 *
 * Se carga en segundo plano apenas se conoce la fiesta (`prepararDetector`), porque pesa
 * unos 9 MB de WebAssembly: la primera vez tarda unos segundos en el wifi del salón; después
 * queda un mes en la tablet. El código del detector va en un trozo aparte del bundle
 * (import dinámico), así el kiosco no lo paga si la temática no tiene Asómate.
 *
 * `detectarCara` NUNCA rompe el flujo: si el detector no está listo a tiempo, falla o no ve
 * ninguna cara, devuelve null y Asómate sigue con la guía y los mandos, como siempre.
 *
 * Los archivos viven en `public/vendor/mediapipe/` (con su .htaccess: el servidor no conoce
 * .wasm ni .tflite y la raíz manda nosniff).
 */

let cargando = null

export function prepararDetector(base) {
  if (!cargando) {
    cargando = (async () => {
      const { FaceDetector, FilesetResolver } = await import('@mediapipe/tasks-vision')
      const vision = await FilesetResolver.forVisionTasks(base + 'vendor/mediapipe')
      return FaceDetector.createFromOptions(vision, {
        baseOptions: {
          modelAssetPath: base + 'vendor/mediapipe/blaze_face_short_range.tflite',
          // CPU y no GPU: un solo cuadro por foto no justifica WebGL, y hay tablets donde el
          // delegado GPU falla al arrancar.
          delegate: 'CPU',
        },
        runningMode: 'IMAGE',
        minDetectionConfidence: 0.5,
      })
    })().catch((error) => {
      cargando = null // que el próximo intento vuelva a probar
      throw error
    })
  }
  return cargando
}

/**
 * Dónde mirar. El modelo es de corto alcance: reduce el cuadro a 128 píxeles y una cara que
 * mide menos de un cuarto del alto se le pierde (probado: al 22% del alto no ve nada, al 36%
 * la ve con puntaje 0,9). Un niño a medio metro de la tablet cae justo en lo primero. Por
 * eso se mira el cuadro entero y además cuatro cuadrantes solapados, donde la misma cara
 * ocupa el doble. Cinco pasadas de ~50 ms: no se nota.
 */
function ventanas(W, H) {
  const w = Math.round(W * 0.6)
  const h = Math.round(H * 0.6)
  return [
    { x: 0, y: 0, w: W, h: H },
    { x: 0, y: 0, w, h },
    { x: W - w, y: 0, w, h },
    { x: 0, y: H - h, w, h },
    { x: W - w, y: H - h, w, h },
  ]
}

function recorte(imagen, v) {
  const c = document.createElement('canvas')
  c.width = v.w
  c.height = v.h
  c.getContext('2d').drawImage(imagen, v.x, v.y, v.w, v.h, 0, 0, v.w, v.h)
  return c
}

/**
 * La caja de la cara más segura de la imagen, en píxeles de la imagen: {x, y, w, h, puntaje}.
 * null si no hay detector, si no llega a tiempo o si no ve ninguna cara.
 */
export async function detectarCara(imagen, { base = './', plazoMs = 4000 } = {}) {
  try {
    const detector = await Promise.race([
      prepararDetector(base),
      new Promise((_, rechazar) => setTimeout(() => rechazar(new Error('detector no listo')), plazoMs)),
    ])
    const W = imagen.naturalWidth || imagen.width
    const H = imagen.naturalHeight || imagen.height
    let mejor = null
    for (const v of ventanas(W, H)) {
      const entera = v.w === W && v.h === H
      const { detections } = detector.detect(entera ? imagen : recorte(imagen, v))
      for (const d of detections || []) {
        const puntaje = d.categories && d.categories[0] ? d.categories[0].score : 0
        if (!mejor || puntaje > mejor.puntaje) {
          const caja = d.boundingBox
          mejor = { x: v.x + caja.originX, y: v.y + caja.originY, w: caja.width, h: caja.height, puntaje }
        }
      }
    }
    return mejor
  } catch {
    return null
  }
}
