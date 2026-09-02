// El rompe-cache de los assets de temática.
//
// Los archivos de un tema se reemplazan CON EL MISMO NOMBRE (saludo-arana.mp4
// es convención, no configuración), así que el navegador de la tablet y el CDN
// siguen sirviendo la versión vieja hasta que su cache expira. Pasó dos veces
// el mismo día: la tablet mostrando el fondo-banner anterior de Héroes recién
// corregido, y el CDN de Hostinger sirviendo el welcome previo.
//
// El backend publica theme.assetsVersion (el mtime más nuevo de la carpeta del
// tema) y buildRuntime pasa cada URL de asset por acá: cambia un archivo,
// cambia la URL, el cache queda fuera de la jugada.
//
// Sin versión (API vieja, valor 0) la URL sale intacta: mismo comportamiento
// de siempre, nada que se rompa por un backend desactualizado.

/** Agrega ?v=<version> a una URL de asset. Respeta querys existentes. */
export function conVersion(url, version) {
  if (!url || typeof url !== 'string') return url
  const v = Number(version)
  if (!Number.isFinite(v) || v <= 0) return url
  return url + (url.includes('?') ? '&' : '?') + 'v=' + v
}
