// Convierte la lista plana de recuerdos en las páginas de la revista.
//
// El orden que eligió el organizador manda: nunca se reordena para que un
// diseño quede mejor. Lo único que se hace es agrupar fotos CONSECUTIVAS sin
// mensaje en un mosaico, que preserva la secuencia y evita que un álbum de
// cien fotos sean cien páginas iguales.

const MOSAIC_MAX = 4

/**
 * @param {object} data respuesta de album-api.php
 * @returns {Array} páginas listas para FlipBook
 */
export function buildPages(data) {
  const media = Array.isArray(data.media) ? data.media : []
  const pages = []

  const cover =
    media.find((item) => item.id === data.album?.coverId && item.kind === 'image') ||
    media.find((item) => item.kind === 'image') ||
    null

  pages.push({
    layout: 'cover',
    title: data.album?.title || 'Álbum Recuerdo',
    subtitle: data.album?.subtitle || null,
    eventName: data.event?.name || '',
    date: data.event?.date || '',
    themeName: data.theme?.name || '',
    image: cover,
    fallback: data.theme?.assets?.banner || null,
  })

  // La portada no se repite adentro; el resto entra en orden.
  const rest = cover ? media.filter((item) => item.id !== cover.id) : media
  let buffer = []

  const flush = () => {
    while (buffer.length) {
      if (buffer.length === 1) {
        pages.push({ layout: 'full', items: buffer.splice(0, 1) })
      } else if (buffer.length === 2 || buffer.length === 3) {
        pages.push({ layout: 'duo', items: buffer.splice(0, 2) })
      } else {
        pages.push({ layout: 'mosaic', items: buffer.splice(0, MOSAIC_MAX) })
      }
    }
  }

  for (const item of rest) {
    if (item.kind === 'video') {
      flush()
      pages.push({ layout: 'video', items: [item] })
      continue
    }
    // Solo el MENSAJE hace una pagina de nota. Antes bastaba con el autor, y
    // como el formulario del invitado pide el nombre pero deja el mensaje
    // opcional, la mayoria de las fotos caia aca: se armaba una cita con la
    // comilla de apertura, la firma abajo y un hueco enorme en el medio donde
    // no habia nada escrito. Sin mensaje la foto sigue el flujo normal y el
    // nombre aparece como credito al pie, que es lo que corresponde.
    if (item.message) {
      flush()
      pages.push({ layout: 'note', items: [item] })
      continue
    }
    buffer.push(item)
    if (buffer.length === MOSAIC_MAX) flush()
  }
  flush()

  pages.push({
    layout: 'closing',
    eventName: data.event?.name || '',
    themeName: data.theme?.name || '',
    image: data.theme?.assets?.grupo || data.theme?.assets?.banner || null,
    count: media.length,
  })

  // El pliego de escritorio muestra dos páginas: con un total impar la última
  // quedaría sola contra un hueco. Se agrega una hoja en blanco.
  if (pages.length % 2 !== 0) {
    pages.push({ layout: 'blank' })
  }

  return pages
}
