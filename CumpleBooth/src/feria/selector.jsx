import React, { useEffect, useRef, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { armIdle, enabledModes, grantConsent, kioskUrl, returnUrl } from './contract.js'
import './feria.css'

const BASE = import.meta.env.BASE_URL
const labels = { infantil: 'Niños', adulto: 'Adultos' }

function Selector() {
  const slug = new URLSearchParams(location.search).get('f') || ''
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [retry, setRetry] = useState(0)
  const [screen, setScreen] = useState('idle')
  const [mode, setMode] = useState('')
  const [world, setWorld] = useState(null)
  const [consent, setConsent] = useState(false)
  const [videoFailed, setVideoFailed] = useState(false)
  const reduced = useRef(matchMedia('(prefers-reduced-motion: reduce)').matches)
  const heading = useRef(null)

  useEffect(() => {
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), 15000)
    setError('')
    if (!slug) { setError('Falta el enlace de la feria. Pídeselo al equipo de CumpleClick.'); clearTimeout(timer); return }
    fetch(BASE + 'feria-api.php?' + new URLSearchParams({ f: slug }), { cache: 'no-store', signal: controller.signal })
      .then(async (res) => {
        const result = await res.json()
        if (!res.ok || !result.ok || !enabledModes(result.mundos).length) throw new Error('Esta feria no está disponible. Consulta al equipo de CumpleClick.')
        setData(result)
      })
      .catch(() => { if (!controller.signal.aborted) setError('No pudimos abrir la feria. Revisa la conexión e inténtalo otra vez.') })
      .finally(() => clearTimeout(timer))
    controller.signal.addEventListener('abort', () => setError('La conexión demoró demasiado. Inténtalo otra vez.'), { once: true })
    return () => { clearTimeout(timer); controller.abort() }
  }, [slug, retry])

  useEffect(() => {
    if (screen === 'idle') return
    heading.current?.focus()
    const idle = armIdle(() => { setScreen('idle'); setMode(''); setWorld(null); setConsent(false) }, 45000)
    window.addEventListener('pointerdown', idle.touch)
    window.addEventListener('keydown', idle.touch)
    return () => { idle.stop(); window.removeEventListener('pointerdown', idle.touch); window.removeEventListener('keydown', idle.touch) }
  }, [screen])

  const enter = () => {
    if (!world || (mode === 'infantil' && !consent)) return
    if (mode === 'infantil') grantConsent(slug, world.slug)
    location.assign(kioskUrl(slug, world.slug, mode, returnUrl('', slug)))
  }
  const brand = <div className="feria-brand"><img src={BASE + 'brand/cumpleclick-mark.svg'} alt="" /><span>CumpleClick</span></div>

  if (error || !data) return <main className="feria-selector feria-center">{brand}<h1>{error ? 'Volvamos a intentarlo' : 'Preparando la feria…'}</h1><p role={error ? 'alert' : 'status'}>{error || 'Un momento, ya comenzamos.'}</p>{error && <button onClick={() => setRetry(retry + 1)}>Reintentar</button>}</main>

  return <main className={`feria-selector ${screen === 'idle' ? 'feria-idle' : 'feria-screen-' + screen}`}>
    {screen === 'idle' ? <>
      {data.video_espera && !videoFailed && !reduced.current && <video className="feria-idle-video" src={BASE + data.video_espera} autoPlay loop muted playsInline onError={() => setVideoFailed(true)} />}
      <button className="feria-idle-touch" onClick={() => setScreen('modes')} aria-label="Toca para empezar">
        {brand}<span className="feria-eyebrow">UN RECUERDO PARA LLEVAR</span><h1>{data.feria.nombre}</h1><p>Tu foto. Tu momento.<br />Un recuerdo de hoy.</p><span className="feria-start">Toca para empezar <span aria-hidden="true">→</span></span>
        <span className="feria-location">{[data.feria.fecha_texto, data.feria.lugar].filter(Boolean).join(' · ')}</span>
      </button>
    </> : <>
      <header>{brand}<span className="feria-event-name">{data.feria.nombre}</span></header>
      <section className="feria-content">
        <span className="feria-eyebrow">{screen === 'modes' ? '01 / TU EXPERIENCIA' : '02 / TU MUNDO'}</span>
        <h1 ref={heading} tabIndex={-1}>{screen === 'modes' ? 'Este momento es tuyo' : 'Elige cómo recordarlo'}</h1>
        <p>{screen === 'modes' ? 'Elige una experiencia para comenzar.' : 'Toca la temática que más te guste.'}</p>
        {screen === 'modes' ? <div className="feria-modes">{enabledModes(data.mundos).map((value) => <button className={`feria-mode feria-mode-${value}`} key={value} onClick={() => { setMode(value); setScreen('worlds') }}><span className="feria-mode-art" aria-hidden="true">{value === 'infantil' ? '✦' : '◉'}</span><strong>{labels[value]}</strong><span>{value === 'infantil' ? 'Personajes, fotos y un diploma' : 'Un retrato con tu estilo'}</span><span className="feria-mode-arrow" aria-hidden="true">↗</span></button>)}</div> : <>
          <div className="feria-worlds">{data.mundos[mode].map((item) => <button aria-pressed={world?.slug === item.slug} className="feria-world" key={item.slug} onClick={() => { setWorld(item); setConsent(false) }}><img src={BASE + item.imagen} alt="" onError={(event) => { event.currentTarget.style.visibility = 'hidden' }} /><span>{item.nombre}</span>{world?.slug === item.slug && <b aria-hidden="true">✓</b>}</button>)}</div>
          {mode === 'infantil' && <label className="feria-consent"><input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />Soy el adulto responsable y autorizo tomar la foto</label>}
          <button className="feria-primary" disabled={!world || (mode === 'infantil' && !consent)} onClick={enter}>Continuar a mi foto <span aria-hidden="true">→</span></button>
        </>}
      </section>
      <footer><button className="feria-quiet" onClick={() => { setScreen(screen === 'worlds' ? 'modes' : 'idle'); setWorld(null); setConsent(false) }}>← Volver</button><span>Una foto, un recuerdo, una sonrisa.</span></footer>
    </>}
  </main>
}

createRoot(document.getElementById('root')).render(<Selector />)
