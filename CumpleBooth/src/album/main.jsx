// Álbum Recuerdo — revista pública.
//
// Entrada de Vite separada del kiosco: la tablet no descarga el código del
// álbum y quien abre el álbum no descarga los tres mundos 3D del kiosco.

import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { applyThemeColors, cssUrl } from '../themeVars.js'
import FlipBook from './FlipBook.jsx'
import AlbumPage from './AlbumPage.jsx'
import { buildPages } from './pages.js'
import { tituloAlbum } from './evento.js'
import { Reproductor, fuenteMusica } from './musica.js'
import './album.css'

// Los assets y endpoints se piden relativos a donde vive album.html, así el
// álbum funciona igual en /cumpleclick/ que en cualquier subcarpeta.
const BASE = new URL('./', document.baseURI).href

// Un solo reproductor para toda la página: el formulario del PIN lo destraba
// dentro del toque de "Abrir el álbum" y la revista lo sigue usando después.
function almacenLocal() {
  try {
    return window.localStorage
  } catch {
    return null
  }
}
const reproductor = new Reproductor({ storage: almacenLocal() })

/** Estado de la música de fondo, para el botón de la revista. */
function useMusica(src) {
  const [sonando, setSonando] = useState(reproductor.sonando)
  useEffect(() => {
    if (!src) return undefined
    reproductor.cargar(src)
    const soltar = reproductor.suscribir(setSonando)
    // Si el álbum abrió sin pedir PIN no hubo gesto: parte con el primer toque.
    reproductor.arrancar(document)
    return () => {
      soltar()
      reproductor.cancelarEspera()
    }
  }, [src])
  return { sonando, alternar: () => reproductor.alternar() }
}

function getToken() {
  const value = new URLSearchParams(window.location.search).get('t') || ''
  return /^[a-f0-9]{32}$/.test(value) ? value : ''
}

/**
 * El volteo 3D necesita preserve-3d de verdad. Si el navegador no lo soporta,
 * o el usuario pidió menos movimiento, la revista se muestra como galería
 * vertical: mismos diseños de página, sin giro.
 */
function supportsFlip() {
  if (typeof window === 'undefined') return false
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false
  return typeof CSS !== 'undefined'
    && typeof CSS.supports === 'function'
    && CSS.supports('transform-style', 'preserve-3d')
}

const MESSAGES = {
  bad_link: 'Este enlace no es válido o ya no está disponible.',
  not_published: 'Este álbum todavía no está publicado. Pregúntale a quien organiza el evento.',
  bad_pin: 'PIN incorrecto.',
  rate_limited: 'Demasiados intentos. Espera un minuto y vuelve a probar.',
  unavailable: 'El servicio no está disponible ahora. Inténtalo en unos minutos.',
  network: 'No se pudo conectar. Revisa tu conexión.',
}

function Shell({ children, tone = '' }) {
  return (
    <main className={`album-shell ${tone}`}>
      <div className="album-card">{children}</div>
      <p className="album-brand">
        <img src={`${BASE}brand/cumpleclick-mark.svg`} alt="" width="22" height="22" />
        CumpleClick
      </p>
    </main>
  )
}

function PinGate({ evento, onUnlock, error, busy, musica }) {
  const [pin, setPin] = useState('')
  return (
    <Shell>
      <h1 className="album-headline">
        {tituloAlbum(evento)}
      </h1>
      <p className="album-lede">Ingresa el PIN de 4 dígitos que te dio el organizador.</p>
      <form
        className="album-pin-form"
        onSubmit={(event) => {
          event.preventDefault()
          if (pin.length !== 4) return
          // La música se destraba acá, dentro del toque: iOS no deja sonar
          // audio que se pida recién después del fetch del PIN.
          if (musica) reproductor.destrabar(musica)
          onUnlock(pin).then((abierto) => {
            if (!abierto) reproductor.pausar()
          })
        }}
      >
        <label className="sr-only" htmlFor="pin">PIN</label>
        <input
          id="pin"
          className="album-pin"
          type="password"
          inputMode="numeric"
          pattern="\d{4}"
          maxLength={4}
          autoComplete="one-time-code"
          value={pin}
          onChange={(event) => setPin(event.target.value.replace(/\D/g, '').slice(0, 4))}
          required
        />
        {error && <p className="album-error" role="alert">{error}</p>}
        <button className="album-cta" type="submit" disabled={busy || pin.length !== 4}>
          {busy ? 'Abriendo…' : 'Abrir el álbum'}
        </button>
      </form>
    </Shell>
  )
}

function Scroller({ pages }) {
  return (
    <div className="album-scroll">
      {pages
        .filter((page) => page.layout !== 'blank')
        .map((page, index) => (
          <article className="album-scroll__page" key={index}>
            <AlbumPage page={page} index={index} base={BASE} />
          </article>
        ))}
    </div>
  )
}

/** Parlante con ondas cuando suena; tachado cuando está en silencio. */
function IconoMusica({ sonando }) {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M4 9.5v5h3.5L13 19V5L7.5 9.5H4z" />
      {sonando
        ? <><path d="M16.5 8.5a5 5 0 0 1 0 7" /><path d="M19.5 6a9 9 0 0 1 0 12" /></>
        : <path d="M16.5 9.5l5 5M21.5 9.5l-5 5" />}
    </svg>
  )
}

/** Cuatro esquinas: hacia afuera para entrar, hacia adentro para salir. */
function IconoPantalla({ completa }) {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {completa
        ? <path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" />
        : <path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" />}
    </svg>
  )
}

function Album({ data }) {
  const pages = useMemo(() => buildPages(data), [data])
  const [flip, setFlip] = useState(() => supportsFlip())
  const [fullscreen, setFullscreen] = useState(false)
  const musica = fuenteMusica(data.theme, BASE)
  const { sonando, alternar: alternarMusica } = useMusica(musica)

  useEffect(() => {
    applyThemeColors(data.theme?.colors)
    // El logo se publica como variable CSS en vez de escribirlo en la hoja de
    // estilos: un `url()` relativo dentro del CSS se resuelve contra
    // dist/assets/, no contra el HTML, y terminaría pidiendo
    // dist/assets/brand/... con 404. Así se arma contra document.baseURI y
    // funciona igual en /cumpleclick/ que en cualquier subcarpeta.
    document.documentElement.style.setProperty('--marca-logo', cssUrl('brand/cumpleclick-mark.svg'))
    document.title = data.album?.title || 'Álbum Recuerdo'
  }, [data])

  const toggleFullscreen = useCallback(() => {
    const el = document.documentElement
    if (!document.fullscreenElement && el.requestFullscreen) {
      el.requestFullscreen().then(() => setFullscreen(true)).catch(() => {})
    } else if (document.exitFullscreen) {
      document.exitFullscreen().then(() => setFullscreen(false)).catch(() => {})
    }
  }, [])

  useEffect(() => {
    const onChange = () => setFullscreen(Boolean(document.fullscreenElement))
    document.addEventListener('fullscreenchange', onChange)
    return () => document.removeEventListener('fullscreenchange', onChange)
  }, [])

  // Pantalla completa y música van como iconos chicos, sin texto (Luis, 17-sep):
  // en el celular tres pastillas con texto se apilaban en tres filas y le
  // quitaban alto a la hoja. El nombre queda en aria-label y title.
  const rotuloPantalla = fullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'
  const rotuloMusica = sonando ? 'Silenciar música' : 'Poner música'
  const controls = (
    <div className="album-tools">
      <button type="button" className="flip-btn flip-btn--wide" onClick={() => setFlip((value) => !value)}>
        {flip ? 'Ver como lista' : 'Ver como revista'}
      </button>
      {document.documentElement.requestFullscreen && (
        <button
          type="button"
          className="flip-btn flip-btn--icono"
          onClick={toggleFullscreen}
          aria-label={rotuloPantalla}
          title={rotuloPantalla}
        >
          <IconoPantalla completa={fullscreen} />
        </button>
      )}
      {musica && (
        <button
          type="button"
          className="flip-btn flip-btn--icono flip-btn--musica"
          aria-pressed={sonando}
          aria-label={rotuloMusica}
          title={rotuloMusica}
          onClick={alternarMusica}
        >
          <IconoMusica sonando={sonando} />
        </button>
      )}
    </div>
  )

  if (!flip) {
    return (
      <div className="album-root">
        <Scroller pages={pages} />
        <div className="flipbook-controls flipbook-controls--scroll">{controls}</div>
      </div>
    )
  }

  return (
    <div className="album-root">
      <FlipBook
        pages={pages}
        renderPage={(page, index) => <AlbumPage page={page} index={index} base={BASE} />}
        footer={controls}
      />
    </div>
  )
}

function App() {
  const token = getToken()
  const [state, setState] = useState({ status: token ? 'loading' : 'error', error: token ? null : MESSAGES.bad_link })
  const [pinError, setPinError] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(async (pin) => {
    try {
      const options = pin
        ? { method: 'POST', body: new URLSearchParams({ t: token, pin }) }
        : { method: 'GET' }
      const url = pin ? `${BASE}album-api.php` : `${BASE}album-api.php?t=${encodeURIComponent(token)}`
      const response = await fetch(url, { ...options, credentials: 'same-origin' })
      const body = await response.json().catch(() => null)

      if (body && body.ok) {
        setState({ status: 'ready', data: body })
        return true
      }
      const code = body?.error || 'unavailable'
      if (code === 'pin_required') {
        setState({
          status: 'pin',
          evento: { name: body.eventName || '', type: body.eventType || 'child_birthday' },
          theme: body.theme,
        })
        applyThemeColors(body.theme?.colors)
        return false
      }
      if (pin) {
        setPinError(MESSAGES[code] || MESSAGES.unavailable)
        return false
      }
      setState({ status: 'error', error: MESSAGES[code] || MESSAGES.unavailable })
      return false
    } catch (e) {
      if (pin) {
        setPinError(MESSAGES.network)
        return false
      }
      setState({ status: 'error', error: MESSAGES.network })
      return false
    }
  }, [token])

  useEffect(() => {
    if (token) load(null)
  }, [token, load])

  if (state.status === 'loading') {
    return <Shell><p className="album-lede">Abriendo el álbum…</p></Shell>
  }
  if (state.status === 'error') {
    return (
      <Shell>
        <h1 className="album-headline">Álbum Recuerdo</h1>
        <p className="album-lede">{state.error}</p>
      </Shell>
    )
  }
  if (state.status === 'pin') {
    return (
      <PinGate
        evento={state.evento}
        error={pinError}
        busy={busy}
        musica={fuenteMusica(state.theme, BASE)}
        onUnlock={async (pin) => {
          setBusy(true)
          setPinError(null)
          const abierto = await load(pin)
          setBusy(false)
          return abierto
        }}
      />
    )
  }
  return <Album data={state.data} />
}

createRoot(document.getElementById('album')).render(<App />)
