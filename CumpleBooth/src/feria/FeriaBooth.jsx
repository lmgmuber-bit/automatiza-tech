import React, { useCallback, useEffect, useRef, useState } from 'react'
import QRCode from 'qrcode'
import Camera from './Camera.jsx'
import { createSegmenter } from './segmentation.js'
import { filterImage } from './photo.js'
import { armIdle, firstName, initialPhotoStep, reservation, returnUrl, takeConsent, upload } from './contract.js'
import { downloadImage, withRemembrance } from './media.js'
import './feria.css'

export default function FeriaBooth({ feria, theme, themeData, characters, filter, base, Spinner, Character, renderPhoto, renderDiploma }) {
  const [segmenter, setSegmenter] = useState(null)
  const [step, setStep] = useState('name')
  const [name, setName] = useState('')
  const [consent, setConsent] = useState(() => feria.modo !== 'infantil' || takeConsent(feria.slug, theme))
  const [person, setPerson] = useState(null)
  const [raw, setRaw] = useState(null)
  const [photo, setPhoto] = useState(null)
  const [diploma, setDiploma] = useState(null)
  const [held, setHeld] = useState(null)
  const [qr, setQr] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [remaining, setRemaining] = useState(60)
  const locked = useRef(false)
  const reservationRef = useRef(null)
  const uploaded = useRef('')
  const heading = useRef(null)
  const child = feria.modo === 'infantil'
  const destination = returnUrl(new URLSearchParams(location.search).get('volver'), feria.slug)
  const finish = useCallback(() => location.replace(destination), [destination])
  const winner = useCallback((value) => { setPerson(value); setStep('character') }, [])
  const camera = useCallback(() => setStep('camera'), [])

  useEffect(() => {
    if (themeData.modoFoto !== 'fondo') return
    const current = createSegmenter(base)
    setSegmenter(current)
    return () => current.dispose()
  }, [base, themeData])
  useEffect(() => { heading.current?.focus() }, [step])
  const completed = step === 'qr' || step === 'diploma'
  useEffect(() => {
    if (!completed) return
    setRemaining(60)
    const end = Date.now() + 60000
    const idle = armIdle(finish, 60000)
    const clock = setInterval(() => setRemaining(Math.max(0, Math.ceil((end - Date.now()) / 1000))), 1000)
    return () => { idle.stop(); clearInterval(clock) }
  }, [completed, finish])

  const prepare = async (source) => {
    if (locked.current) return
    locked.current = true; setBusy(true); setError(''); setRaw(source); setStep('preview')
    try {
      // Reintentar composición o volver a tomar la foto conserva la misma reserva.
      if (!reservationRef.current) reservationRef.current = await reservation(base, feria, theme, name)
      const number = reservationRef.current
      setHeld(number)
      const compositionStart = performance.now()
      const composed = await renderPhoto(source, firstName(name), person, filter, segmenter)
      const finalPhoto = await filterImage(await withRemembrance(composed, feria, number), filter)
      if (segmenter) segmenter.metrics.compositionMs = performance.now() - compositionStart
      setPhoto(finalPhoto)
    } catch { setError('No pudimos preparar tu recuerdo. La foto sigue aquí: puedes reintentar o guardar la original.') }
    finally { locked.current = false; setBusy(false) }
  }

  const save = async () => {
    if (locked.current || !photo) return
    locked.current = true; setBusy(true); setError('')
    try {
      // Copia local primero; el navegador puede pedir permiso para descargarla.
      if (!uploaded.current) {
        downloadImage(photo, held.etiqueta)
        uploaded.current = await upload(base, feria.slug, name, photo, held)
      }
      setQr(await QRCode.toDataURL(uploaded.current, { width: 420, margin: 4, errorCorrectionLevel: 'M', color: { dark: '#241137', light: '#ffffff' } }))
      setStep('qr')
    } catch { setError('No pudimos obtener el QR. Tu foto sigue disponible. Revisa la conexión y vuelve a intentar.') }
    finally { locked.current = false; setBusy(false) }
  }

  const showDiploma = async () => {
    if (locked.current) return
    locked.current = true; setBusy(true); setError('')
    try {
      if (!diploma) setDiploma(await withRemembrance(await renderDiploma(firstName(name), person), feria, held))
      setStep('diploma')
    } catch { setError('No pudimos preparar el diploma. Puedes intentarlo nuevamente.') }
    finally { locked.current = false; setBusy(false) }
  }

  return <main className={`app feria-kiosco feria-kiosco-${feria.modo}`} data-step={step} data-segmentation={segmenter ? JSON.stringify(segmenter.metrics) : undefined}>
    <header className="feria-kiosk-header"><span>CumpleClick <b>·</b> {feria.nombre}</span>{!completed && <button className="feria-quiet" disabled={busy} onClick={finish}>Salir</button>}</header>
    {step === 'name' && <section className="feria-panel feria-name"><img className="feria-name-logo" src={base + 'brand/cumpleclick-mark.svg'} alt="CumpleClick" /><span className="feria-eyebrow">{child ? 'TU AVENTURA COMIENZA' : 'UN RETRATO A TU MANERA'}</span><h1 ref={heading} tabIndex={-1}>¿Cómo te llamas?</h1><p>Solo tu primer nombre, si quieres.</p><label className="feria-name-label">Tu nombre <span>(opcional)</span><input value={name} maxLength={20} autoComplete="off" autoCapitalize="words" onChange={(e) => setName(e.target.value)} placeholder="Tu primer nombre" /></label>
      {child && !consent && <label className="feria-consent"><input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />Soy el adulto responsable y autorizo tomar la foto</label>}
      <button className="feria-primary" disabled={!consent} onClick={() => { setName(firstName(name)); setStep(initialPhotoStep(characters)) }}>Continuar <span aria-hidden="true">→</span></button><button className="feria-quiet" disabled={!consent} onClick={() => { setName(''); setStep(initialPhotoStep(characters)) }}>Saltar</button>
    </section>}
    {step === 'roulette' && <Spinner onDone={winner} />}
    {step === 'character' && <Character personaje={person} invitado={name} onDone={camera} />}
    {step === 'camera' && <Camera filter={filter} segmenter={segmenter} scene={themeData.images?.escena} base={base} onCapture={prepare} />}
    {step === 'preview' && <section className="feria-panel feria-preview"><span className="feria-eyebrow">TU RECUERDO DE HOY</span><h1 ref={heading} tabIndex={-1}>{held ? `Tu foto: ${held.etiqueta}` : 'Preparando tu recuerdo'}</h1>{photo ? <img className="feria-result" src={photo} alt="Tu foto con el recuerdo y el número de la feria" /> : <p role="status">{busy ? 'Estamos preparando tu foto…' : 'La foto está lista para reintentar.'}</p>}
      {error && <p role="alert">{error}</p>}
      <div className="feria-actions">{photo ? <button className="feria-primary" disabled={busy} onClick={save}>{busy ? 'Preparando el QR…' : 'Guardar y ver mi QR'}</button> : <button className="feria-primary" disabled={busy} onClick={() => prepare(raw)}>Reintentar</button>}<button className="feria-quiet" disabled={busy} onClick={() => { setPhoto(null); setError(''); setStep('camera') }}>Tomar otra foto</button>{error && raw && <button className="feria-quiet" onClick={() => downloadImage(photo || raw, held?.etiqueta)}>Guardar en esta tablet</button>}</div>
    </section>}
    {completed && <section className={`feria-panel feria-complete ${step === 'diploma' ? 'feria-diploma' : ''}`}><span className="feria-eyebrow">{step === 'diploma' ? 'UN DIPLOMA PARA TI' : 'LLÉVATE ESTE MOMENTO'}</span><h1 ref={heading} tabIndex={-1}>Tu foto: {held.etiqueta}</h1>
      {step === 'qr' ? <><img className="feria-qr" src={qr} alt="Escanea este código QR para descargar tu foto" /><p>Escanea el QR con tu celular.<br />Guarda tu número para encontrar tu foto.</p>{child && <button disabled={busy} onClick={showDiploma}>{busy ? 'Preparando diploma…' : 'Ver mi diploma'}</button>}</> : <><img className="feria-result" src={diploma} alt={`Diploma en ${feria.nombre} · ${feria.fecha_texto}`} /><div className="feria-actions"><button onClick={() => downloadImage(diploma, `diploma-${held.etiqueta}`)}>Guardar diploma en la tablet</button><button className="feria-quiet" onClick={() => setStep('qr')}>Volver al QR de mi foto</button></div></>}
      {error && <p role="alert">{error}</p>}<button className="feria-primary" onClick={finish}>Terminar</button><p className="feria-countdown">Volvemos al inicio en {remaining} s</p>
    </section>}
  </main>
}
