export const DEFAULT_FRAME_BOX = Object.freeze({ x: 0.32, y: 0.3, w: 0.36, h: 0.28 })
export const FRAME_PHOTO_INSET_RATIO = 0.085

function isValidFrameBox(value) {
  if (!value || typeof value !== 'object') return false
  const { x, y, w, h } = value
  if (![x, y, w, h].every((part) => Number.isFinite(Number(part)))) return false
  const frame = { x: Number(x), y: Number(y), w: Number(w), h: Number(h) }
  return (
    frame.x >= 0 &&
    frame.y >= 0 &&
    frame.w >= 0.05 &&
    frame.h >= 0.05 &&
    frame.x + frame.w <= 1 &&
    frame.y + frame.h <= 1
  )
}

export function normalizeFrameBox(value, fallback = DEFAULT_FRAME_BOX) {
  const source = isValidFrameBox(value) ? value : isValidFrameBox(fallback) ? fallback : DEFAULT_FRAME_BOX
  return {
    x: Number(source.x),
    y: Number(source.y),
    w: Number(source.w),
    h: Number(source.h),
  }
}

export function getSquareFrameGeometry(frameBox, canvasWidth, canvasHeight) {
  const frame = normalizeFrameBox(frameBox)
  const width = Math.max(1, Number(canvasWidth) || 1)
  const height = Math.max(1, Number(canvasHeight) || 1)
  const ox = frame.x * width
  const oy = frame.y * height
  const ow = frame.w * width
  const oh = frame.h * height
  const side = Math.min(ow, oh)
  const left = ox + (ow - side) / 2
  const top = oy + (oh - side) / 2

  return {
    frame,
    ox,
    oy,
    ow,
    oh,
    side,
    left,
    top,
    right: left + side,
    bottom: top + side,
    cx: left + side / 2,
    cy: top + side / 2,
  }
}

/**
 * Área cuadrada útil dentro del marco decorativo que ya viene pintado en el
 * fondo. El inset evita dibujar encima del borde dorado de la imagen base.
 */
export function getSquarePhotoGeometry(frameBox, canvasWidth, canvasHeight) {
  const outer = getSquareFrameGeometry(frameBox, canvasWidth, canvasHeight)
  const inset = outer.side * FRAME_PHOTO_INSET_RATIO
  const photoSide = Math.max(1, outer.side - inset * 2)
  return {
    ...outer,
    inset,
    photoSide,
    photoLeft: outer.left + inset,
    photoTop: outer.top + inset,
  }
}

/**
 * Escala cualquier recorte transparente para apoyarlo en la zona inferior de
 * la pista, centrado y sin invadir el marco ni el texto.
 */
export function getTrackCharacterGeometry(imageWidth, imageHeight, canvasWidth, canvasHeight, lower = false) {
  const sourceWidth = Number(imageWidth)
  const sourceHeight = Number(imageHeight)
  const width = Math.max(1, Number(canvasWidth) || 1)
  const height = Math.max(1, Number(canvasHeight) || 1)
  if (!(sourceWidth > 0) || !(sourceHeight > 0)) return null

  const maxWidth = width * 0.62
  // "lower": Frozen y K-Pop un poco más abajo, resto igual (Luis, 2026-07-28).
  const drop = lower ? height * 0.05 : 0
  const trackTop = height * 0.58 + drop
  const trackBottom = height * 0.88 + drop
  const maxHeight = trackBottom - trackTop
  const scale = Math.min(maxWidth / sourceWidth, maxHeight / sourceHeight)
  const characterWidth = sourceWidth * scale
  const characterHeight = sourceHeight * scale

  return {
    left: (width - characterWidth) / 2,
    top: trackBottom - characterHeight,
    bottom: trackBottom,
    width: characterWidth,
    height: characterHeight,
  }
}
