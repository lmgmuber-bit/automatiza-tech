export const PREDICTION_OPTIONS = {
  parecido: [
    { value: 'mama', label: 'A mamá' },
    { value: 'papa', label: 'A papá' },
    { value: 'ambos', label: 'A ambos' },
  ],
  peso: [
    { value: 'menos3', label: 'Menos de 3 kg' },
    { value: 'entre', label: 'Entre 3 y 3,5 kg' },
    { value: 'mas35', label: 'Más de 3,5 kg' },
  ],
  fecha: [
    { value: 'antes', label: 'Antes' },
    { value: 'justo', label: 'Justo en la fecha' },
    { value: 'despues', label: 'Después' },
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
