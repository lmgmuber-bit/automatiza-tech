/**
 * Videos que tienen que estar listos ANTES de que les toque: se bajan entero una vez por
 * carga del kiosco y se sirven desde memoria (un blob URL).
 *
 * Por qué: la despedida de spidey pesa 2,7 MB. La pantalla de video se rinde si el primer
 * cuadro no llega a tiempo, y en el wifi de un salón lleno (o con el servidor compartido
 * cargado, como el 2026-09-10) no llegaba: el invitado veía una tarjeta de "gracias por
 * venir" en vez del video. Bajado de antemano, el <video> arranca en el acto.
 *
 * Si la descarga falla, no pasa nada: se sigue usando la URL de red, como antes.
 */

const listos = new Map() // url de red -> blob URL
const enCurso = new Map() // url de red -> promesa de descarga

/** Baja el video en segundo plano. Devuelve la URL que conviene usar cuando termina. */
export function prepararVideo(url, { fetchFn, crearUrl } = {}) {
  if (!url) return Promise.resolve('')
  if (listos.has(url)) return Promise.resolve(listos.get(url))
  if (enCurso.has(url)) return enCurso.get(url)
  const bajar = fetchFn || (typeof fetch === 'function' ? fetch.bind(globalThis) : null)
  const aUrl = crearUrl || (typeof URL !== 'undefined' && URL.createObjectURL ? URL.createObjectURL.bind(URL) : null)
  if (!bajar || !aUrl) return Promise.resolve(url)
  const promesa = (async () => {
    try {
      const respuesta = await bajar(url)
      if (!respuesta || !respuesta.ok) return url
      const blob = await respuesta.blob()
      const local = aUrl(blob)
      listos.set(url, local)
      return local
    } catch {
      return url
    } finally {
      enCurso.delete(url)
    }
  })()
  enCurso.set(url, promesa)
  return promesa
}

/** La URL con la que hay que montar el <video>: la de memoria si ya bajó, si no la de red. */
export function urlDeVideo(url) {
  if (!url) return ''
  return listos.get(url) || url
}

/** Solo para pruebas. */
export function olvidarVideos() {
  listos.clear()
  enCurso.clear()
}
