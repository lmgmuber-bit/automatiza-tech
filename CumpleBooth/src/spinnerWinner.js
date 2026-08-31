const LOCAL_QA_HOSTS = new Set(['localhost', '127.0.0.1', '[::1]'])

function normalize(value) {
  return String(value || '')
    .trim()
    .toLocaleLowerCase('es')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

// Atajo de QA deliberadamente limitado al navegador local: permite recorrer el
// flujo Full de Carreras sin repetir la ruleta hasta obtener a Rayo.
export function selectSpinnerWinnerIndex(personajes, {
  themeSlug = '',
  search = '',
  hostname = '',
  random = Math.random,
} = {}) {
  const list = Array.isArray(personajes) ? personajes : []
  if (!list.length) return 0

  const requested = new URLSearchParams(search).get('qaWinner')
  const canForceRayo =
    LOCAL_QA_HOSTS.has(String(hostname).toLowerCase()) &&
    normalize(themeSlug) === 'carreras' &&
    normalize(requested) === 'rayo-mcqueen'

  if (canForceRayo) {
    const rayoIndex = list.findIndex((personaje) => normalize(personaje?.name) === 'rayo-mcqueen')
    if (rayoIndex >= 0) return rayoIndex
  }

  return Math.min(list.length - 1, Math.max(0, Math.floor(random() * list.length)))
}
