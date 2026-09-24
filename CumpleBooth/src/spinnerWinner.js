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
  excludeIndex = -1,
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

  // Reintento de la ruleta: el personaje recién rechazado no puede volver a
  // salir en el giro siguiente — un niño que dijo "ese no" y lo ve salir de
  // nuevo siente que la ruleta está rota. Se sortea entre los demás.
  if (Number.isInteger(excludeIndex) && excludeIndex >= 0 && excludeIndex < list.length && list.length > 1) {
    const pick = Math.min(list.length - 2, Math.max(0, Math.floor(random() * (list.length - 1))))
    return pick >= excludeIndex ? pick + 1 : pick
  }

  return Math.min(list.length - 1, Math.max(0, Math.floor(random() * list.length)))
}
