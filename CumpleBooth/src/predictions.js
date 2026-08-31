/**
 * Opciones de la apuesta.
 *
 * `label` es el texto canónico: va al canvas de la foto, al tablero de los
 * papás y está fijado por tests. No se toca.
 *
 * `short` existe solo para la cabina. En una tablet sobre un pedestal, el
 * invitado decide de pie y a un metro de distancia: "Entre 3 y 3,5 kg" en un
 * botón chico obliga a acercarse a leer. La ficha muestra el valor corto en
 * grande y debajo el label completo en pequeño, así se reconoce de lejos y
 * sigue sin ambigüedad de cerca. Si un día falta `short`, la ficha cae al
 * `label` y no se rompe nada.
 */
export const PREDICTION_OPTIONS = {
  parecido: [
    { value: 'mama', label: 'A mamá', short: 'Mamá' },
    { value: 'papa', label: 'A papá', short: 'Papá' },
    { value: 'ambos', label: 'A ambos', short: 'Los dos' },
  ],
  peso: [
    { value: 'menos3', label: 'Menos de 3 kg', short: '−3 kg' },
    { value: 'entre', label: 'Entre 3 y 3,5 kg', short: '3 – 3,5' },
    { value: 'mas35', label: 'Más de 3,5 kg', short: '+3,5 kg' },
  ],
  fecha: [
    { value: 'antes', label: 'Antes', short: 'Antes' },
    { value: 'justo', label: 'Justo en la fecha', short: 'Justo' },
    { value: 'despues', label: 'Después', short: 'Después' },
  ],
}

export function createPredictionSubmissionToken() {
  const bytes = new Uint8Array(16)
  if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes)
  else for (let index = 0; index < bytes.length; index += 1) bytes[index] = Math.floor(Math.random() * 256)
  return Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('')
}

export function predictionLabels(prediction = {}) {
  const label = (key) => PREDICTION_OPTIONS[key].find((item) => item.value === prediction[key])?.label || ''
  return { parecido: label('parecido'), peso: label('peso'), fecha: label('fecha') }
}

export function validPrediction(prediction = {}) {
  const name = String(prediction.guest_name || '').trim()
  if (!name || name.length > 80) return false
  return ['parecido', 'peso', 'fecha'].every((key) =>
    PREDICTION_OPTIONS[key].some((item) => item.value === prediction[key])
  )
}

export function predictionSummary(prediction = {}) {
  const labels = predictionLabels(prediction)
  return `${labels.parecido} · ${labels.peso} · ${labels.fecha}`
}
