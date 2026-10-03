// Contrato público AT-CUMPLECLICK-020. Nunca se activa feria por parámetros de URL.
export function feriaFromResponse(data) {
  const feria = data?.feria
  return feria?.slug && ['infantil', 'adulto'].includes(feria.modo) ? feria : null
}

export function apiQuery(search) {
  const input = new URLSearchParams(search)
  const query = new URLSearchParams({ p: input.get('p') || '' })
  for (const key of ['tema', 'modo']) if (input.get(key)) query.set(key, input.get(key))
  return query.toString()
}

export function firstName(value) {
  const clean = String(value || '').replace(/[^\p{L}\p{M}\s'-]/gu, '').trim().split(/\s+/)[0] || ''
  return [...clean].slice(0, 20).join('')
}

export function returnUrl(candidate, slug, here = location.href) {
  const expected = new URL('feria.html', here)
  expected.search = new URLSearchParams({ f: slug }).toString()
  // Se devuelve siempre la forma canónica, sin parámetros adicionales ni origen externo.
  try {
    const url = new URL(candidate || expected.href, here)
    if (url.origin === expected.origin && url.pathname === expected.pathname && url.searchParams.get('f') === slug) return expected.href
  } catch { /* enlace inválido: selector de esta feria */ }
  return expected.href
}

export function kioskUrl(slug, theme, mode, selector) {
  const url = new URL('./', selector)
  url.search = new URLSearchParams({ p: slug, tema: theme, modo: mode, volver: selector }).toString()
  return url.href
}

export const enabledModes = (worlds) => ['infantil', 'adulto'].filter((mode) => Array.isArray(worlds?.[mode]) && worlds[mode].length)
export const initialPhotoStep = (characters) => characters?.length ? 'roulette' : 'camera'
export const filterFor = (filter) => filter === 'bn' ? 'grayscale(1) contrast(1.08)' : 'none'
export const footerLines = (feria, held) => [feria.recuerdo || feria.nombre || '', feria.organizador_ig || feria.organizador || '', held.etiqueta]

export function armIdle(onIdle, milliseconds, clock = globalThis) {
  let timer
  const stop = () => clock.clearTimeout(timer)
  const touch = () => { stop(); timer = clock.setTimeout(onIdle, milliseconds) }
  touch()
  return { touch, stop }
}

async function post(url, body, fetcher) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), 25000)
  try {
    const res = await fetcher(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), signal: controller.signal, cache: 'no-store' })
    const data = await res.json()
    if (!res.ok || !data.ok) throw new Error('No se pudo completar la solicitud. Inténtalo nuevamente.')
    return data
  } finally { clearTimeout(timer) }
}

export async function reservation(base, feria, theme, name, fetcher = fetch) {
  const data = await post(base + 'feria-api.php', { accion: 'numero', f: feria.slug, modo: feria.modo, tema: theme, nombre: firstName(name) }, fetcher)
  if (!Number.isSafeInteger(data.numero) || data.numero < 1 || !/^F-\d+$/.test(data.etiqueta || '') || !/^[a-f0-9]{32}$/.test(data.reserva || '')) throw new Error('No se recibió el número de la foto. Inténtalo nuevamente.')
  return data
}

export async function upload(base, slug, name, image, held, fetcher = fetch) {
  const data = await post(base + 'upload.php', { image, name: firstName(name) || 'foto', party: slug, feria_reserva: held.reserva }, fetcher)
  if (!/^https?:\/\//i.test(data.url || '')) throw new Error('No se recibió un enlace válido para la foto.')
  return data.url
}

const consentKey = (slug, theme) => `cc-feria-consent:${slug}:${theme}`
export function grantConsent(slug, theme) {
  try { sessionStorage.setItem(consentKey(slug, theme), String(Date.now())) } catch { /* se vuelve a pedir en cámara */ }
}
export function takeConsent(slug, theme) {
  try {
    const key = consentKey(slug, theme)
    const stamp = Number(sessionStorage.getItem(key))
    sessionStorage.removeItem(key)
    return stamp > 0 && Date.now() >= stamp && Date.now() - stamp < 120000
  } catch { return false }
}
