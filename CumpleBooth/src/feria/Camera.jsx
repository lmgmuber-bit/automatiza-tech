import React, { useEffect, useRef, useState } from 'react'
import { filterFor } from './contract.js'
import { loadImage } from './media.js'
import { drawScene, previewDecision } from './segmentation.js'

export default function Camera({ filter, segmenter, scene, base, onCapture }) {
  const video = useRef(null)
  const preview = useRef(null)
  const [showScene, setShowScene] = useState(false)
  const [previewMode, setPreviewMode] = useState('loading')
  const shooting = useRef(false)
  const countdown = useRef(null)
  const [ready, setReady] = useState(false)
  const [error, setError] = useState('')
  const [facing, setFacing] = useState('user')
  const [attempt, setAttempt] = useState(0)
  const [count, setCount] = useState(0)

  useEffect(() => {
    let alive = true; let stream
    setReady(false); setError(''); setCount(0); shooting.current = false
    const watchdog = setTimeout(() => { if (alive) setError('La cámara no responde. Revisa el permiso y vuelve a intentar.') }, 12000)
    async function open() {
      try {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('camera')
        stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 960 } } })
        if (!alive) { stream.getTracks().forEach((track) => track.stop()); return }
        video.current.srcObject = stream
        await video.current.play()
        if (alive) { clearTimeout(watchdog); setReady(true); setError('') }
      } catch { if (alive) setError('No pudimos abrir la cámara. Permite su uso en este navegador y vuelve a intentar.') }
    }
    open()
    return () => { alive = false; clearTimeout(watchdog); clearTimeout(countdown.current); stream?.getTracks().forEach((track) => track.stop()) }
  }, [facing, attempt])

  useEffect(() => {
    if (!ready || !segmenter || !scene) return
    let stopped = false; let frame; let count = 0; let start
    setShowScene(false)
    async function begin() {
      const available = await segmenter.ready
      if (stopped) return
      if (!available) { setPreviewMode('frame'); return }
      const background = await loadImage(base + scene).catch(() => null)
      if (!background || stopped) return
      const canvas = preview.current
      canvas.width = 360; canvas.height = 640
      async function tick() {
        if (stopped || !video.current?.videoWidth) return
        start ??= performance.now()
        const mask = await segmenter.mask(video.current)
        if (stopped) { mask?.image?.close(); return }
        if (!mask) { setShowScene(false); return }
        try { drawScene(canvas, mask.image, background, mask) } finally { mask.image.close() }
        setShowScene(true)
        count += 1
        const elapsed = performance.now() - start
        if (elapsed >= 2000) {
          const decision = previewDecision(count, elapsed)
          segmenter.metrics.previewFps = Math.round(decision.fps * 10) / 10
          segmenter.metrics.preview = decision.enabled ? 'live' : 'final-only'
          canvas.dataset.fps = String(segmenter.metrics.previewFps)
          canvas.dataset.preview = segmenter.metrics.preview
          setPreviewMode(segmenter.metrics.preview)
          if (!decision.enabled) { setShowScene(false); return }
          count = 0; start = performance.now()
        }
        frame = requestAnimationFrame(tick)
      }
      frame = requestAnimationFrame(tick)
    }
    begin()
    return () => { stopped = true; cancelAnimationFrame(frame) }
  }, [ready, segmenter, scene, base, facing])

  const capture = () => {
    if (!ready || shooting.current) return
    shooting.current = true
    let value = 3; setCount(value)
    const tick = () => {
      value -= 1
      if (value > 0) { setCount(value); countdown.current = setTimeout(tick, 800); return }
      const current = video.current
      if (!current?.videoWidth) { shooting.current = false; setCount(0); setError('La cámara se desconectó. Vuelve a intentar.'); return }
      const canvas = document.createElement('canvas')
      canvas.width = current.videoWidth; canvas.height = current.videoHeight
      // El filtro se aplica en el compositor una sola vez, no sobre un JPEG ya filtrado.
      canvas.getContext('2d').drawImage(current, 0, 0)
      onCapture(canvas.toDataURL('image/jpeg', 0.95))
    }
    countdown.current = setTimeout(tick, 800)
  }

  return <section className="feria-panel feria-camera">
    <span className="feria-eyebrow">TU MOMENTO</span><h1>Una sonrisa para recordar</h1>
    <p>{filter === 'bn' ? 'Retrato en blanco y negro' : 'Mira a la cámara y acomódate al centro.'}</p>
    {previewMode === 'final-only' && <p>El fondo se aplicará al guardar tu foto.</p>}
    {previewMode === 'frame' && <p>Tu foto irá en el marco de esta temática.</p>}
    <div className="feria-camera-frame"><video ref={video} autoPlay muted playsInline style={{ filter: filterFor(filter), transform: facing === 'user' ? 'scaleX(-1)' : undefined }} /><canvas ref={preview} aria-label="Vista previa sobre el fondo de la temática" style={{ display: showScene ? 'block' : 'none', filter: filterFor(filter), transform: facing === 'user' ? 'scaleX(-1)' : undefined }} /><span className="feria-camera-guide" aria-hidden="true" />{count > 0 && <strong className="feria-count" role="status">{count}</strong>}</div>
    {error && <div role="alert"><p>{error}</p><button onClick={() => setAttempt(attempt + 1)}>Reintentar cámara</button></div>}
    <button className="feria-primary" disabled={!ready || count > 0} onClick={capture}>Tomar mi foto</button>
    <button className="feria-quiet" disabled={count > 0} onClick={() => setFacing(facing === 'user' ? 'environment' : 'user')}>Cambiar cámara</button>
  </section>
}
