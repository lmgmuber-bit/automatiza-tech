import React, { useEffect, useState } from 'react'

// Pantalla completa de la feria (Luis, 26-09: "el módulo de la feria debe tener pantalla completa").
// Misma regla que la bienvenida del kiosco: el navegador solo la concede dentro de un toque, y el
// selector y la cabina son páginas distintas, así que cada una la vuelve a pedir en su primer toque.
// Si quien atiende sale con el botón, se respeta: esa página no la vuelve a pedir sola.
let automatica = true

const raiz = () => document.documentElement
export const fullscreenAvailable = () => typeof document !== 'undefined' && Boolean(raiz().requestFullscreen || raiz().webkitRequestFullscreen)
export const fullscreenActive = () => typeof document !== 'undefined' && Boolean(document.fullscreenElement || document.webkitFullscreenElement)

export function enterFullscreen() {
  if (!fullscreenAvailable() || fullscreenActive()) return
  const pedir = raiz().requestFullscreen || raiz().webkitRequestFullscreen
  try {
    const r = pedir.call(raiz(), { navigationUI: 'hide' })
    if (r && typeof r.catch === 'function') r.catch(() => {})
  } catch { /* el navegador puede negarse; la feria sigue igual */ }
}

export function exitFullscreen() {
  const salir = document.exitFullscreen || document.webkitExitFullscreen
  if (!salir || !fullscreenActive()) return
  try {
    const r = salir.call(document)
    if (r && typeof r.catch === 'function') r.catch(() => {})
  } catch { /* idem */ }
}

/** Pide pantalla completa en cada toque de la página hasta que alguien salga a propósito. */
export function useFullscreenOnTap() {
  useEffect(() => {
    const alTocar = () => { if (automatica) enterFullscreen() }
    document.addEventListener('click', alTocar, true)
    return () => document.removeEventListener('click', alTocar, true)
  }, [])
}

export function FullscreenButton({ className = '' }) {
  const [activa, setActiva] = useState(fullscreenActive)
  useEffect(() => {
    const mirar = () => setActiva(fullscreenActive())
    document.addEventListener('fullscreenchange', mirar)
    document.addEventListener('webkitfullscreenchange', mirar)
    return () => {
      document.removeEventListener('fullscreenchange', mirar)
      document.removeEventListener('webkitfullscreenchange', mirar)
    }
  }, [])
  if (!fullscreenAvailable()) return null
  const texto = activa ? 'Salir de pantalla completa' : 'Pantalla completa'
  return <button type="button" className={`feria-fullscreen ${className}`} aria-label={texto} aria-pressed={activa} title={texto}
    onClick={(event) => {
      event.stopPropagation()
      if (activa) { automatica = false; exitFullscreen() } else { automatica = true; enterFullscreen() }
    }}><span aria-hidden="true">{activa ? '⤡' : '⛶'}</span></button>
}
