// Álbum Recuerdo — revista pública.
//
// Entrada de Vite separada del kiosco: la tablet no descarga el código del
// álbum y quien abre el álbum no descarga los tres mundos 3D del kiosco.

import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { applyThemeColors } from '../themeVars.js'
import FlipBook from './FlipBook.jsx'
import AlbumPage from './AlbumPage.jsx'
import { buildPages } from './pages.js'
import './album.css'

// Los assets y endpoints se piden relativos a donde vive album.html, así el
// álbum funciona igual en /cumpleclick/ que en cualquier subcarpeta.
const BASE = new URL('./', document.baseURI).href

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
  not_published: 'Este álbum todavía no está publicado. Pregúntale al organizador de la fiesta.',
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

function PinGate({ eventName, onUnlock, error, busy }) {
  const [pin, setPin] = useState('')
  return (
    <Shell>
      <h1 className="album-headline">
        {eventName ? `Álbum de la fiesta de ${eventName}` : 'Álbum Recuerdo'}
      </h1>
      <p className="album-lede">Ingresa el PIN de 4 dígitos que te dio el organizador.</p>
      <form
        className="album-pin-form"
        onSubmit={(event) => {
          event.preventDefault()
          if (pin.length === 4) onUnlock(pin)
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

/**
 * Aviso "gira tu celular". Solo aparece en un celular de verdad y de pie:
 * orientación vertical + ancho típico de teléfono + puntero táctil (así una
 * ventana angosta de escritorio no lo dispara). Es una sugerencia, nunca un
 * bloqueo: se cierra solo al girar a horizontal, y si la persona prefiere
 * seguir de pie la revista funciona igual en modo una página.
 *
 * El patrón de escucha es el mismo de FlipBook.jsx (media query + resize +
 * orientationchange): en pruebas el `change` del media query no siempre
 * llega solo, y de qué lado falle esto define si el aviso se queda pegado.
 */
function RotateHint() {
  const matchesHint = () => {
    if (typeof window === 'undefined' || !window.matchMedia) return false
    return window.matchMedia('(orientation: portrait) and (max-width: 640px) and (pointer: coarse)').matches
  }
  const [visible, setVisible] = useState(() => matchesHint())
  const [dismissed, setDismissed] = useState(false)

  useEffect(() => {
    if (typeof window === 'undefined' || !window.matchMedia) return undefined
    const query = window.matchMedia('(orientation: portrait) and (max-width: 640px) and (pointer: coarse)')
    const sync = () => setVisible(query.matches)
    query.addEventListener('change', sync)
    window.addEventListener('resize', sync)
    window.addEventListener('orientationchange', sync)
    sync()
    return () => {
      query.removeEventListener('change', sync)
      window.removeEventListener('resize', sync)
      window.removeEventListener('orientationchange', sync)
    }
  }, [])

  if (dismissed || !visible) return null

  return (
    <div className="rotate-hint" role="status">
      <span className="rotate-hint__icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          <rect x="8" y="3.5" width="8" height="15" rx="1.8" />
          <line x1="10.8" y1="16.2" x2="13.2" y2="16.2" />
          <path d="M18.6 6.4a7.2 7.2 0 0 1 2 4.4" />
          <path d="M21 8.2v2.7h-2.7" />
        </svg>
      </span>
      <p className="rotate-hint__text">Gira tu celular para una mejor experiencia</p>
      <button
        type="button"
        className="rotate-hint__close"
        aria-label="Cerrar aviso"
        onClick={() => setDismissed(true)}
      >
        ×
      </button>
    </div>
  )
}

function Album({ data }) {
  const pages = useMemo(() => buildPages(data), [data])
  const [flip, setFlip] = useState(() => supportsFlip())
  const [fullscreen, setFullscreen] = useState(false)

  useEffect(() => {
    applyThemeColors(data.theme?.colors)
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

  const controls = (
    <div className="album-tools">
      <button type="button" className="flip-btn flip-btn--wide" onClick={() => setFlip((value) => !value)}>
        {flip ? 'Ver como lista' : 'Ver como revista'}
      </button>
      {document.documentElement.requestFullscreen && (
        <button type="button" className="flip-btn flip-btn--wide" onClick={toggleFullscreen}>
          {fullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
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
      <RotateHint />
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
        return
      }
      const code = body?.error || 'unavailable'
      if (code === 'pin_required') {
        setState({ status: 'pin', eventName: body.eventName || '', theme: body.theme })
        applyThemeColors(body.theme?.colors)
        return
      }
      if (pin) {
        setPinError(MESSAGES[code] || MESSAGES.unavailable)
        return
      }
      setState({ status: 'error', error: MESSAGES[code] || MESSAGES.unavailable })
    } catch (e) {
      if (pin) {
        setPinError(MESSAGES.network)
        return
      }
      setState({ status: 'error', error: MESSAGES.network })
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
        eventName={state.eventName}
        error={pinError}
        busy={busy}
        onUnlock={async (pin) => {
          setBusy(true)
          setPinError(null)
          await load(pin)
          setBusy(false)
        }}
      />
    )
  }
  return <Album data={state.data} />
}

createRoot(document.getElementById('album')).render(<App />)
