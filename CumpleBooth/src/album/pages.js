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

  // La portada sale del cuerpo del album para no repetir la misma foto, y por
  // eso conviene que sea una foto SIN dedicatoria: la version anterior tomaba
  // la primera imagen que hubiera y, si esa traia mensaje, el mensaje se iba
  // con ella y no aparecia en ninguna pagina. Un invitado escribia su
  // dedicatoria y no quedaba en el album, sin aviso. Paso de verdad en la
  // demo de Hielo: la tia Carolina escribio y no salia.
  //
  // Si el organizador fijo una portada a mano, esa manda igual: es su
  // decision. En ese caso la foto no se saca del cuerpo, asi que su
  // dedicatoria tiene su pagina y la foto aparece dos veces —en la tapa y
  // adentro—, que en una revista se lee de lo mas normal.
  const portadaElegida = media.find(
    (item) => item.id === data.album?.coverId && item.kind === 'image'
  )
  const cover =
    portadaElegida ||
    media.find((item) => item.kind === 'image' && !item.message) ||
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

  // La portada no se repite adentro, salvo que traiga dedicatoria: ahi vuelve a
  // entrar para que el mensaje tenga su pagina.
  const rest = cover && !cover.message
    ? media.filter((item) => item.id !== cover.id)
    : media
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
    // El cierre necesita el evento entero, no solo el nombre: con el tipo
    // decide si dice "a la fiesta" o "al baby shower".
    evento: data.event || null,
    themeName: data.theme?.name || '',
    image: data.theme?.assets?.grupo || data.theme?.assets?.banner || null,
    count: media.length,
    // Contacto de CumpleClick al cierre. Puede venir nulo: la ultima pagina
    // tiene que seguir funcionando sin el.
    marca: data.marca || null,
  })

  // El pliego de escritorio muestra dos páginas: con un total impar la última
  // quedaría sola contra un hueco. Se agrega una hoja en blanco.
  if (pages.length % 2 !== 0) {
    pages.push({ layout: 'blank' })
  }

  return pages
}
