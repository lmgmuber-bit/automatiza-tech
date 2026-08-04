// Motor de pase de página del Álbum Recuerdo.
//
// Portado del prototipo de Umbría (umbria/PrototipoClaudeDesign/flipbook.jsx),
// que resolvía el efecto sin ninguna librería: React + CSS 3D puro. Se
// conservó su idea central —el estado del arrastre vive en refs, no en state,
// para que no haya un re-render de React por frame— y se le agregó lo que
// aquel prototipo no necesitaba: ventana de páginas montadas, para que un
// álbum de cien fotos no arme cien páginas de una en el celular de la abuela.

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'

const FLIP_MS = 820
// Debajo de este umbral el arrastre vuelve atrás en vez de completar el giro.
const DRAG_COMMIT = 0.12
// Cuántos pliegos se mantienen montados a cada lado del actual.
const WINDOW_SPREADS = 1

export default function FlipBook({ pages, renderPage, onClose, footer }) {
  const totalPages = pages.length
  const [spread, setSpread] = useState(0)
  const [flipping, setFlipping] = useState(null) // null | 'fwd' | 'back'
  const [drag, setDrag] = useState(null)
  const [singlePage, setSinglePage] = useState(() => matchesSingle())

  const flyingRef = useRef(null)
  const bookRef = useRef(null)
  const isDragging = useRef(false)
  const dragProgress = useRef(0)
  const dragDir = useRef(null)
  const dragStartX = useRef(0)
  const rafId = useRef(null)
  // Los handlers de arrastre se registran una sola vez, así que el modo de
  // página tienen que leerlo por ref: por closure verían siempre el valor del
  // primer render.
  const singlePageRef = useRef(singlePage)

  // En pantallas angostas el pliego de dos páginas no cabe: se pasa de a una.
  function matchesSingle() {
    if (typeof window === 'undefined' || !window.matchMedia) return false
    return window.matchMedia('(max-width: 820px)').matches
  }

  useEffect(() => {
    if (typeof window === 'undefined' || !window.matchMedia) return undefined
    const query = window.matchMedia('(max-width: 820px)')
    // Se escucha el cambio del media query Y el resize: en pruebas el evento
    // `change` no llegó a dispararse al redimensionar y el pliego se quedó
    // pegado en modo de una página. De qué lado se equivoque esto define el
    // diseño completo, así que no depende de un solo evento.
    const sync = () => setSinglePage(query.matches)
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

  const step = singlePage ? 1 : 2

  const next = useCallback(() => {
    if (flipping || drag) return
    if (spread + step >= totalPages) return
    setFlipping('fwd')
    setTimeout(() => {
      setSpread((s) => s + step)
      setFlipping(null)
    }, FLIP_MS)
  }, [flipping, drag, spread, totalPages, step])

  const prev = useCallback(() => {
    if (flipping || drag) return
    if (spread <= 0) return
    setFlipping('back')
    setTimeout(() => {
      setSpread((s) => Math.max(0, s - step))
      setFlipping(null)
    }, FLIP_MS)
  }, [flipping, drag, spread, step])

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'Escape' && onClose) onClose()
      if (event.key === 'ArrowRight' || event.key === 'PageDown') next()
      if (event.key === 'ArrowLeft' || event.key === 'PageUp') prev()
      if (event.key === 'Home') setSpread(0)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [next, prev, onClose])

  // Handlers globales del arrastre. Se registran una sola vez y leen todo por
  // refs: si dependieran del estado, cada frame recrearía los listeners.
  useEffect(() => {
    function setAngle(progress) {
      const node = flyingRef.current
      if (!node) return
      const angle = -180 * progress
      node.style.transform = `rotateY(${angle}deg)`
      const intensity = Math.abs(Math.sin((angle * Math.PI) / 180)) * 0.68
      node.querySelectorAll('.flip-curl').forEach((shadow) => {
        shadow.style.opacity = String(intensity)
      })
    }

    function animate(from, to, duration, done) {
      if (rafId.current) cancelAnimationFrame(rafId.current)
      const start = performance.now()
      const delta = to - from
      const tick = (now) => {
        const t = duration <= 0 ? 1 : Math.min((now - start) / duration, 1)
        const eased = 1 - Math.pow(1 - t, 3)
        const value = from + delta * eased
        dragProgress.current = value
        setAngle(value)
        if (t < 1) {
          rafId.current = requestAnimationFrame(tick)
        } else {
          dragProgress.current = 0
          done()
        }
      }
      rafId.current = requestAnimationFrame(tick)
    }

    const onPointerMove = (event) => {
      if (!isDragging.current || !bookRef.current) return
      const half = bookRef.current.offsetWidth / (singlePageRef.current ? 1 : 2)
      const dx = event.clientX - dragStartX.current
      const raw = dragDir.current === 'fwd' ? -dx / half : dx / half
      const progress = Math.max(0, Math.min(1, raw))
      dragProgress.current = progress
      setAngle(progress)
    }

    const onPointerUp = () => {
      if (!isDragging.current) return
      isDragging.current = false
      const progress = dragProgress.current
      const dir = dragDir.current
      const stepNow = singlePageRef.current ? 1 : 2
      if (progress > DRAG_COMMIT) {
        animate(progress, 1, (1 - progress) * 320, () => {
          setDrag(null)
          setSpread((s) => (dir === 'fwd' ? s + stepNow : Math.max(0, s - stepNow)))
        })
      } else {
        animate(progress, 0, progress * 260, () => setDrag(null))
      }
    }

    window.addEventListener('pointermove', onPointerMove)
    window.addEventListener('pointerup', onPointerUp)
    window.addEventListener('pointercancel', onPointerUp)
    return () => {
      window.removeEventListener('pointermove', onPointerMove)
      window.removeEventListener('pointerup', onPointerUp)
      window.removeEventListener('pointercancel', onPointerUp)
      if (rafId.current) cancelAnimationFrame(rafId.current)
    }
  }, [])

  useEffect(() => {
    singlePageRef.current = singlePage
  }, [singlePage])

  const startDrag = (event, dir) => {
    if (flipping || drag) return
    if (dir === 'fwd' && spread + step >= totalPages) return
    if (dir === 'back' && spread <= 0) return
    // Solo el botón principal y solo si no se está tocando un control: un
    // video con sus controles debe poder usarse sin que la página gire.
    if (event.button !== undefined && event.button !== 0) return
    if (event.target.closest('video, button, a, input')) return
    event.preventDefault()
    if (rafId.current) cancelAnimationFrame(rafId.current)
    dragDir.current = dir
    dragProgress.current = 0
    isDragging.current = true
    dragStartX.current = event.clientX
    setDrag({
      dir,
      front: dir === 'fwd' ? spread + (singlePage ? 0 : 1) : spread,
      back: dir === 'fwd' ? spread + step : spread - 1,
    })
  }

  const leftIdx = spread
  const rightIdx = spread + 1

  // Ventana de montaje: fuera de este rango las páginas ni existen en el DOM.
  const visible = useMemo(() => {
    const margin = WINDOW_SPREADS * (singlePage ? 1 : 2) + 2
    const set = new Set()
    for (let i = spread - margin; i <= spread + margin; i++) {
      if (i >= 0 && i < totalPages) set.add(i)
    }
    return set
  }, [spread, totalPages, singlePage])

  const renderStatic = (index, side) => {
    if (index < 0 || index >= totalPages) {
      return <div className={`flip-page flip-page--${side} is-blank`} />
    }
    return (
      <div className={`flip-page flip-page--${side}`}>
        {visible.has(index) ? renderPage(pages[index], index) : null}
        <div className={`flip-shade flip-shade--${side}`} />
      </div>
    )
  }

  const previewLeft = flipping === 'fwd' ? rightIdx + 1 : leftIdx
  const previewRight = flipping === 'back' ? leftIdx - 1 : rightIdx

  let flyFront = null
  let flyBack = null
  if (flipping === 'fwd') {
    flyFront = singlePage ? leftIdx : rightIdx
    flyBack = flyFront + 1
  }
  if (flipping === 'back') {
    flyFront = leftIdx
    flyBack = leftIdx - 1
  }

  const busy = Boolean(flipping || drag)
  const canFwd = !busy && spread + step < totalPages
  const canBack = !busy && spread > 0
  const currentLabel = singlePage
    ? Math.min(spread + 1, totalPages)
    : Math.min(rightIdx + 1, totalPages)

  return (
    <div className={`flipbook-stage ${singlePage ? 'is-single' : ''}`}>
      <div className={`flipbook ${busy ? 'is-flipping' : ''}`} ref={bookRef}>
        {!singlePage && (
          <div
            className="flipbook__half flipbook__half--left"
            style={{ cursor: canBack ? 'grab' : 'default', touchAction: 'pan-y' }}
            onPointerDown={(event) => startDrag(event, 'back')}
          >
            {drag
              ? drag.dir === 'fwd' && renderStatic(leftIdx, 'left')
              : renderStatic(flipping === 'back' ? previewLeft : leftIdx, 'left')}
          </div>
        )}

        <div
          className={`flipbook__half ${singlePage ? 'flipbook__half--only' : 'flipbook__half--right'}`}
          style={{ cursor: canFwd ? 'grab' : 'default', touchAction: 'pan-y' }}
          onPointerDown={(event) => {
            if (!singlePage) return startDrag(event, 'fwd')
            // En una sola página se decide por la mitad tocada, como hacía el
            // prototipo: izquierda vuelve, derecha avanza.
            const box = bookRef.current?.getBoundingClientRect()
            const back = box && event.clientX < box.left + box.width / 2
            return startDrag(event, back ? 'back' : 'fwd')
          }}
        >
          {singlePage
            ? renderStatic(spread, 'only')
            : drag
              ? drag.dir === 'back' && renderStatic(rightIdx, 'right')
              : renderStatic(flipping === 'fwd' ? previewRight : rightIdx, 'right')}
        </div>

        {!singlePage && <div className="flipbook__spine" />}

        {flipping && (
          <div className={`flipbook__flying flipbook__flying--${flipping}`} key={`${flipping}-${spread}`}>
            <div className="flip-page flip-page--front">
              {flyFront >= 0 && flyFront < totalPages && renderPage(pages[flyFront], flyFront)}
              <div className="flip-curl" />
            </div>
            <div className="flip-page flip-page--back">
              {flyBack >= 0 && flyBack < totalPages && renderPage(pages[flyBack], flyBack)}
              <div className="flip-curl flip-curl--back" />
            </div>
          </div>
        )}

        {drag && (
          <div
            ref={flyingRef}
            className={`flipbook__flying flipbook__flying--drag flipbook__flying--drag-${drag.dir}`}
            key={`drag-${drag.dir}-${spread}`}
            style={{
              transform: 'rotateY(0deg)',
              transformOrigin: drag.dir === 'fwd' ? 'left center' : 'right center',
              animationName: 'none',
              left: drag.dir === 'back' || singlePage ? 0 : '50%',
              width: singlePage ? '100%' : '50%',
            }}
          >
            <div className="flip-page flip-page--front">
              {drag.front >= 0 && drag.front < totalPages && renderPage(pages[drag.front], drag.front)}
              <div className="flip-curl" />
            </div>
            <div className="flip-page flip-page--back">
              {drag.back >= 0 && drag.back < totalPages && renderPage(pages[drag.back], drag.back)}
              <div className="flip-curl flip-curl--back" />
            </div>
          </div>
        )}
      </div>

      <div className="flipbook-controls">
        <button
          type="button"
          className="flip-btn"
          onClick={prev}
          disabled={!canBack}
          aria-label="Página anterior"
        >
          <svg width="20" height="20" viewBox="0 0 18 18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 3 L5 9 L11 15" /></svg>
        </button>
        <p className="flip-indicator" aria-live="polite">
          <span>{currentLabel}</span> / {totalPages}
        </p>
        <button
          type="button"
          className="flip-btn"
          onClick={next}
          disabled={!canFwd}
          aria-label="Página siguiente"
        >
          <svg width="20" height="20" viewBox="0 0 18 18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 3 L13 9 L7 15" /></svg>
        </button>
        {footer}
      </div>
    </div>
  )
}
