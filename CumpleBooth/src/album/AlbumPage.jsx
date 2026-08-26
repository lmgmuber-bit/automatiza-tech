// Diseños de página de la revista.
//
// El patrón de despachador viene del prototipo de Umbría, pero los diseños
// son otros: aquel era una revista editorial de lujo (cabecera, precios,
// créditos de estudio) y este es el álbum de una fiesta infantil.
//
// Ningún color se escribe acá: todo sale de las variables de la temática que
// aplicó applyThemeColors() desde themes.json.
//
// Dos páginas seguidas nunca se ven iguales. La paridad del folio decide la
// inclinación de las fotos, el lado de la composición y si una foto sola va
// montada sobre el papel o a sangre. Es la diferencia entre un álbum y un
// listado de fotos con margen blanco.

import React, { useEffect, useRef, useState } from 'react'
import Lockup from '../brand/Lockup.jsx'

export default function AlbumPage({ page, index, base }) {
  if (!page) return null
  switch (page.layout) {
    case 'cover':
      return <CoverPage page={page} base={base} />
    case 'full':
      return <FullPage page={page} base={base} index={index} />
    case 'duo':
      return <DuoPage page={page} base={base} index={index} />
    case 'mosaic':
      return <MosaicPage page={page} base={base} index={index} />
    case 'note':
      return <NotePage page={page} base={base} index={index} />
    case 'video':
      return <VideoPage page={page} base={base} index={index} />
    case 'closing':
      return <ClosingPage page={page} base={base} />
    default:
      return <div className="mag mag--blank" />
  }
}

function formatDate(raw) {
  if (!raw) return ''
  const parts = String(raw).split('-')
  if (parts.length !== 3) return raw
  const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
  const mes = meses[Number(parts[1]) - 1]
  return mes ? `${Number(parts[2])} de ${mes} de ${parts[0]}` : raw
}

/**
 * Envoltorio de página. Marca la paridad del folio como clase para que el CSS
 * alterne inclinaciones y composiciones sin que cada diseño tenga que
 * calcularlo por su cuenta.
 */
function Page({ kind, index, extra = '', style, children }) {
  const alt = typeof index === 'number' && index % 2 === 1
  const clases = ['mag', `mag--${kind}`, alt ? 'is-alt' : '', extra]
  return (
    <div className={clases.filter(Boolean).join(' ')} style={style}>
      {children}
    </div>
  )
}

function Folio({ index, tone }) {
  const clase = tone === 'light' ? 'mag__folio mag__folio--light' : 'mag__folio'
  return <span className={clase}>{index + 1}</span>
}

/**
 * Imagen de la revista. Pide la miniatura mientras la página no es la activa
 * y sube al original recién cuando se ve: en un álbum de cien fotos, cargar
 * todos los originales de entrada deja el celular clavado.
 */
function Photo({ item, base, sizeHint = 'full' }) {
  const [src, setSrc] = useState(() => base + (item.thumb || item.url))
  const ref = useRef(null)

  useEffect(() => {
    if (sizeHint !== 'full') return undefined
    const node = ref.current
    if (!node || typeof IntersectionObserver === 'undefined') {
      setSrc(base + item.url)
      return undefined
    }
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        setSrc(base + item.url)
        observer.disconnect()
      }
    }, { rootMargin: '200px' })
    observer.observe(node)
    return () => observer.disconnect()
  }, [item.url, base, sizeHint])

  return (
    <img
      ref={ref}
      className="mag__photo"
      src={src}
      alt={item.author ? `Recuerdo compartido por ${item.author}` : 'Recuerdo de la fiesta'}
      loading="lazy"
      decoding="async"
      draggable="false"
    />
  )
}

function Credit({ item, onDark }) {
  if (!item.author && item.source !== 'booth') return null
  const clase = onDark ? 'mag__credit mag__credit--light' : 'mag__credit'
  return (
    <p className={clase}>
      {item.source === 'booth' ? 'Cabina CumpleClick' : item.author}
    </p>
  )
}

function CoverPage({ page, base }) {
  const background = page.image
    ? `url("${base + (page.image.url)}")`
    : page.fallback
      ? `url("${base + page.fallback}")`
      : 'none'
  return (
    <Page kind="cover" style={{ backgroundImage: background }}>
      <div className="mag__veil" />
      <div className="mag__cover-top">
        <span className="mag__eyebrow">Álbum Recuerdo</span>
        {page.themeName && <span className="mag__eyebrow">{page.themeName}</span>}
      </div>
      <div className="mag__cover-main">
        <h1 className="mag__title">{page.title}</h1>
        <span className="mag__rule" aria-hidden="true" />
        {page.subtitle && <p className="mag__subtitle">{page.subtitle}</p>}
      </div>
      <div className="mag__cover-bottom">
        {page.date && <span>{formatDate(page.date)}</span>}
        <span className="mag__brand">CumpleClick</span>
      </div>
    </Page>
  )
}

/**
 * Foto sola. Alterna dos tratamientos según el folio: montada sobre el papel
 * con su marco blanco, o a sangre ocupando la hoja entera con el crédito
 * sobre un degradado. Dos páginas de foto sola seguidas con el mismo marco
 * blanco es lo que hacía que el álbum se leyera como una planilla.
 */
function FullPage({ page, base, index }) {
  const item = page.items[0]
  if (index % 2 === 1) {
    return (
      <Page kind="full" index={index} extra="mag--bleed">
        <Photo item={item} base={base} />
        <div className="mag__scrim" />
        <Credit item={item} onDark />
        <Folio index={index} tone="light" />
      </Page>
    )
  }
  return (
    <Page kind="full" index={index}>
      <div className="mag__frame mag__frame--tilt">
        <Photo item={item} base={base} />
      </div>
      <Credit item={item} />
      <Folio index={index} />
    </Page>
  )
}

function DuoPage({ page, base, index }) {
  return (
    <Page kind="duo" index={index}>
      {page.items.map((item, i) => (
        <div className="mag__frame mag__frame--tilt" key={item.id ?? i}>
          <Photo item={item} base={base} sizeHint="thumb" />
        </div>
      ))}
      <Folio index={index} />
    </Page>
  )
}

function MosaicPage({ page, base, index }) {
  return (
    <Page kind="mosaic" index={index}>
      <div className="mag__grid">
        {page.items.map((item, i) => (
          <div className="mag__frame mag__frame--tilt" key={item.id ?? i}>
            <Photo item={item} base={base} sizeHint="thumb" />
          </div>
        ))}
      </div>
      <Folio index={index} />
    </Page>
  )
}

/**
 * Página de dedicatoria. Sólo llega acá un recuerdo que trae mensaje escrito:
 * la tarjeta está diseñada alrededor del texto y sin él queda un hueco.
 */
function NotePage({ page, base, index }) {
  const item = page.items[0]
  return (
    <Page kind="note" index={index}>
      <div className="mag__frame mag__frame--note mag__frame--tilt">
        <Photo item={item} base={base} />
      </div>
      <blockquote className="mag__note">
        <span className="mag__tape" aria-hidden="true" />
        <p className="mag__note-text">{item.message}</p>
        {item.author && (
          <footer className="mag__note-by">
            <span className="mag__note-dash" aria-hidden="true" />
            {item.author}
          </footer>
        )}
      </blockquote>
      <Folio index={index} />
    </Page>
  )
}

/**
 * Página de video. El `src` solo se pone cuando la página está realmente a la
 * vista y se quita al salir: así nunca hay más de un video con fuente cargada
 * y el pase de página no arrastra descargas de fondo.
 */
function VideoPage({ page, base, index }) {
  const item = page.items[0]
  const wrapRef = useRef(null)
  const videoRef = useRef(null)
  const [active, setActive] = useState(false)

  useEffect(() => {
    const node = wrapRef.current
    if (!node || typeof IntersectionObserver === 'undefined') {
      setActive(true)
      return undefined
    }
    const observer = new IntersectionObserver((entries) => {
      setActive(entries.some((entry) => entry.isIntersecting))
    }, { threshold: 0.25 })
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    const video = videoRef.current
    if (!video) return
    if (!active) {
      video.pause()
      video.removeAttribute('src')
      video.load()
    }
  }, [active])

  const clase = index % 2 === 1 ? 'mag mag--video is-alt' : 'mag mag--video'
  return (
    <div className={clase} ref={wrapRef}>
      <span className="mag__badge">Video</span>
      <div className="mag__frame mag__frame--video">
        <video
          ref={videoRef}
          className="mag__video"
          controls
          playsInline
          preload="none"
          poster={item.poster ? base + item.poster : undefined}
          src={active ? base + item.url : undefined}
        />
      </div>
      {item.message && <p className="mag__note-text mag__note-text--video">{item.message}</p>}
      <Credit item={item} />
      <Folio index={index} />
    </div>
  )
}

function ClosingPage({ page, base }) {
  return (
    <Page
      kind="closing"
      style={page.image ? { backgroundImage: `url("${base + page.image}")` } : undefined}
    >
      <div className="mag__veil" />
      <div className="mag__closing-body">
        <p className="mag__eyebrow">Y colorín colorado</p>
        <h2 className="mag__title mag__title--closing">
          ¡Gracias por venir<br />
          {page.eventName ? `a la fiesta de ${page.eventName}!` : 'a la fiesta!'}
        </h2>
        <span className="mag__rule" aria-hidden="true" />
        <p className="mag__closing-note">
          {/* Con cero recuerdos, contar en número quedaba en "0 recuerdos
              guardados para siempre". Pasa si el organizador publica el álbum
              antes de aprobar nada: el invitado abre el enlace y lo primero que
              lee es un cero. */}
          {page.count === 0
            ? 'Los recuerdos de esta fiesta están por llegar.'
            : page.count === 1
              ? 'Un recuerdo guardado para siempre.'
              : `${page.count} recuerdos guardados para siempre.`}
        </p>
        <Colofon marca={page.marca} base={base} />
      </div>
    </Page>
  )
}

/**
 * Cierre de la revista: quien hizo esto y como ubicarlo.
 *
 * Los datos vienen de data/marca.json por la API, no compilados en el bundle,
 * porque el hosting no tiene build: si estuvieran acá habría que recompilar y
 * resubir assets cada vez que cambie un teléfono.
 *
 * Cada dato es un enlace sólo si el JSON trae su `_url`. El `stopPropagation`
 * del pointerdown es necesario: el pliego arranca el arrastre de página en el
 * pointerdown de la mitad, y sin esto tocar un enlace empezaría a dar vuelta la
 * hoja en vez de abrirlo.
 */
function Colofon({ marca, base }) {
  // El nombre viaja dentro del lockup, asi que el lockup solo sirve mientras la
  // marca se siga llamando CumpleClick. Si alguien cambia `nombre` en el admin
  // —una marca blanca, por ejemplo— el logo diria una cosa y el dueno del
  // album seria otro, asi que en ese caso gana el texto.
  const nombre = (marca?.nombre || 'CumpleClick').trim()
  const marcaBloque = nombre.toLowerCase() === 'cumpleclick'
    ? <Lockup base={base} tono="claro" className="mag__colofon-marca" />
    : <span className="mag__brand">{nombre}</span>

  if (!marca) {
    return <div className="mag__colofon">{marcaBloque}</div>
  }

  const datos = [
    { valor: marca.web, url: marca.web_url },
    { valor: marca.instagram, url: marca.instagram_url },
    { valor: marca.whatsapp, url: marca.whatsapp_url },
  ].filter((dato) => dato.valor)

  return (
    <div className="mag__colofon">
      <span className="mag__rule mag__rule--colofon" aria-hidden="true" />
      {marca.invitacion && <p className="mag__colofon-invita">{marca.invitacion}</p>}
      {marcaBloque}
      {marca.lema && <p className="mag__colofon-lema">{marca.lema}</p>}
      {datos.length > 0 && (
        <ul className="mag__colofon-datos">
          {datos.map((dato) => (
            <li key={dato.valor}>
              {dato.url
                ? (
                  <a
                    href={dato.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    onPointerDown={(event) => event.stopPropagation()}
                  >
                    {dato.valor}
                  </a>
                  )
                : dato.valor}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
