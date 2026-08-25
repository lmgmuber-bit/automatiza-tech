// Diseños de página de la revista.
//
// El patrón de despachador viene del prototipo de Umbría, pero los diseños
// son otros: aquel era una revista editorial de lujo (cabecera, precios,
// créditos de estudio) y este es el álbum de una fiesta infantil.
//
// Ningún color se escribe acá: todo sale de las variables de la temática que
// aplicó applyThemeColors() desde themes.json.

import React, { useEffect, useRef, useState } from 'react'

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

function Folio({ index }) {
  return <span className="mag__folio">{index + 1}</span>
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

function Credit({ item }) {
  if (!item.author && item.source !== 'booth') return null
  return (
    <p className="mag__credit">
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
    <div className="mag mag--cover" style={{ backgroundImage: background }}>
      <div className="mag__veil" />
      <div className="mag__cover-top">
        <span className="mag__eyebrow">Álbum Recuerdo</span>
        {page.themeName && <span className="mag__eyebrow">{page.themeName}</span>}
      </div>
      <div className="mag__cover-main">
        <h1 className="mag__title">{page.title}</h1>
        {page.subtitle && <p className="mag__subtitle">{page.subtitle}</p>}
      </div>
      <div className="mag__cover-bottom">
        {page.date && <span>{formatDate(page.date)}</span>}
        <span className="mag__brand">CumpleClick</span>
      </div>
    </div>
  )
}

function FullPage({ page, base, index }) {
  const item = page.items[0]
  return (
    <div className="mag mag--full">
      <div className="mag__frame">
        <Photo item={item} base={base} />
      </div>
      <Credit item={item} />
      <Folio index={index} />
    </div>
  )
}

function DuoPage({ page, base, index }) {
  return (
    <div className="mag mag--duo">
      {page.items.map((item, i) => (
        <div className="mag__frame" key={item.id ?? i}>
          <Photo item={item} base={base} sizeHint="thumb" />
        </div>
      ))}
      <Folio index={index} />
    </div>
  )
}

function MosaicPage({ page, base, index }) {
  return (
    <div className="mag mag--mosaic">
      <div className="mag__grid">
        {page.items.map((item, i) => (
          <div className="mag__frame" key={item.id ?? i}>
            <Photo item={item} base={base} sizeHint="thumb" />
          </div>
        ))}
      </div>
      <Folio index={index} />
    </div>
  )
}

function NotePage({ page, base, index }) {
  const item = page.items[0]
  return (
    <div className="mag mag--note">
      <div className="mag__frame mag__frame--note">
        <Photo item={item} base={base} />
      </div>
      <blockquote className="mag__note">
        {item.message && <p className="mag__note-text">{item.message}</p>}
        {item.author && <footer className="mag__note-by">— {item.author}</footer>}
      </blockquote>
      <Folio index={index} />
    </div>
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

  return (
    <div className="mag mag--video" ref={wrapRef}>
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
    <div
      className="mag mag--closing"
      style={page.image ? { backgroundImage: `url("${base + page.image}")` } : undefined}
    >
      <div className="mag__veil" />
      <div className="mag__closing-body">
        <p className="mag__eyebrow">Y colorín colorado</p>
        <h2 className="mag__title mag__title--closing">
          ¡Gracias por venir<br />
          {page.eventName ? `a la fiesta de ${page.eventName}!` : 'a la fiesta!'}
        </h2>
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
        <span className="mag__brand">CumpleClick</span>
      </div>
    </div>
  )
}
