const CANVAS_FONT_SPECS = ['600 40px "Baloo 2"', '700 40px "Baloo 2"', '800 40px "Baloo 2"']

let canvasFontsPromise = null

export function ensureCanvasFonts() {
  if (typeof document === 'undefined' || !document.fonts) return Promise.resolve(false)
  if (!canvasFontsPromise) {
    canvasFontsPromise = Promise.all(CANVAS_FONT_SPECS.map((spec) => document.fonts.load(spec)))
      .then(() => CANVAS_FONT_SPECS.every((spec) => document.fonts.check(spec)))
      .catch(() => false)
  }
  return canvasFontsPromise
}

export function canvasFontsAreReady() {
  return Boolean(
    typeof document !== 'undefined' &&
      document.fonts &&
      CANVAS_FONT_SPECS.every((spec) => document.fonts.check(spec))
  )
}
