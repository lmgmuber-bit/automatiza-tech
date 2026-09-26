import React, { useCallback, useEffect, useRef, useState } from 'react'
import QRCode from 'qrcode'
import Camera from './Camera.jsx'
import { createSegmenter } from './segmentation.js'
import { filterImage } from './photo.js'
import { afterName, armIdle, firstName, initialPhotoStep, reservation, returnUrl, takeConsent, upload } from './contract.js'
import { downloadImage, withRemembrance } from './media.js'
import { FullscreenButton, useFullscreenOnTap } from './fullscreen.jsx'
import './feria.css'

// Niños en feria recupera lo de una fiesta (Luis, 26-09, ya en la feria): el minijuego del personaje antes de la
// foto y Asómate cuando la temática lo trae. Las piezas son las del kiosco normal y llegan como props, para que
// este archivo no dependa del estado de BoothApp; la foto igual sale con número, recuerdo y QR de la feria.
export default function FeriaBooth({ feria, theme, themeData, characters, filter, base, Spinner, Character, renderPhoto, renderDiploma,
  asomate = null, gameFor = null, Game = null, AsomatePick = null, Capture = null, AsomateReview = null, asomatePerson = null, prepareAsomate = null,
  welcomeSrc = null, music = null, volantinUrl = null }) {
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
  const [route, setRoute] = useState('personaje')
  const [elenco, setElenco] = useState([])
  const [fotosAsomate, setFotosAsomate] = useState([])
  const [heroe, setHeroe] = useState(null)
  const locked = useRef(false)
  const reservationRef = useRef(null)
  const uploaded = useRef('')
  const heading = useRef(null)
  const child = feria.modo === 'infantil'
  const destination = returnUrl(new URLSearchParams(location.search).get('volver'), feria.slug)
  const finish = useCallback(() => location.replace(destination), [destination])
  const winner = useCallback((value) => { setPerson(value); setStep('character') }, [])
  const camera = useCallback(() => setStep('camera'), [])
  const afterCharacter = useCallback(() => setStep(child && gameFor && person && gameFor(person.name) ? 'juego' : 'camera'), [child, gameFor, person])
  const asomateOk = Boolean(asomate && AsomatePick && Capture && AsomateReview)
  useFullscreenOnTap()
  const afterWelcome = useCallback(() => setStep(afterName(characters, asomateOk)), [characters, asomateOk])
  const { start: startMusic, muted, toggle: toggleMusic } = useFeriaMusic(music, step)
  // Tras el nombre, la intro de la temática como en una fiesta (Luis, 26-09). El toque del nombre es el gesto
  // que deja sonar el video y la música; sin intro se sigue directo.
  const afterNameStep = () => { startMusic(); setStep(welcomeSrc ? 'welcome' : afterName(characters, asomateOk)) }

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

  // `precompuesta`: la escena de Asómate ya viene armada por su propio compositor; solo le falta el recuerdo y el número.
  const prepare = async (source, precompuesta = route === 'asomate') => {
    if (locked.current) return
    locked.current = true; setBusy(true); setError(''); setRaw(source); setStep('preview')
    try {
      // Reintentar composición o volver a tomar la foto conserva la misma reserva.
      if (!reservationRef.current) reservationRef.current = await reservation(base, feria, theme, name)
      const number = reservationRef.current
      setHeld(number)
      const compositionStart = performance.now()
      const composed = precompuesta ? source : await renderPhoto(source, firstName(name), person, filter, segmenter)
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
      if (!diploma) setDiploma(await withRemembrance(await renderDiploma(firstName(name), person, route === 'asomate' ? heroe : null), feria, held))
      setStep('diploma')
    } catch { setError('No pudimos preparar el diploma. Puedes intentarlo nuevamente.') }
    finally { locked.current = false; setBusy(false) }
  }

  const startAsomate = () => {
    if (prepareAsomate) prepareAsomate()
    setRoute('asomate'); setElenco([]); setFotosAsomate([]); setStep('asomate-elegir')
  }
  const startPersonaje = () => { setRoute('personaje'); setStep(initialPhotoStep(characters)) }
  const retake = () => {
    setPhoto(null); setError('')
    if (route === 'asomate') { setFotosAsomate([]); setStep('asomate-capturar') } else setStep('camera')
  }
  const withCharacters = Boolean(characters?.length)
  // Chile en Volantín en la feria (26-09): sin `p`, así que no anota puntaje ni hay tabla con nombres de niños ajenos;
  // con kiosco=1 el juego vuelve solo a la feria 45 s después del final.
  const volantin = volantinUrl ? (() => {
    const url = new URL(volantinUrl, location.href)
    url.search = new URLSearchParams({ kiosco: '1', nombre: feria.nombre, jugador: firstName(name), volver: destination }).toString()
    return url.href
  })() : null

  return <main className={`app feria-kiosco feria-kiosco-${feria.modo}`} data-step={step} data-segmentation={segmenter ? JSON.stringify(segmenter.metrics) : undefined}
    style={{ '--feria-fondo': `url("${new URL(base + 'feria/fondo.jpg', location.href).href}")` }}>
    <header className="feria-kiosk-header"><span>CumpleClick <b>·</b> {feria.nombre}</span><span className="feria-kiosk-acciones">{music && <button className="feria-fullscreen" type="button" onClick={toggleMusic} aria-label={muted ? 'Activar música' : 'Silenciar música'} title={muted ? 'Activar música' : 'Silenciar música'}><span aria-hidden="true">{muted ? '🔇' : '🎵'}</span></button>}<FullscreenButton />{!completed && <button className="feria-quiet" disabled={busy} onClick={finish}>Salir</button>}</span></header>
    {step === 'name' && <section className="feria-panel feria-name"><img className="feria-name-logo" src={base + 'brand/cumpleclick-mark.svg'} alt="CumpleClick" /><span className="feria-eyebrow">{child ? 'TU AVENTURA COMIENZA' : 'UN RETRATO A TU MANERA'}</span><h1 ref={heading} tabIndex={-1}>¿Cómo te llamas?</h1><p>Solo tu primer nombre, si quieres.</p><label className="feria-name-label">Tu nombre <span>(opcional)</span><input value={name} maxLength={20} autoComplete="off" autoCapitalize="words" onChange={(e) => setName(e.target.value)} placeholder="Tu primer nombre" /></label>
      {child && !consent && <label className="feria-consent"><input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />Soy el adulto responsable y autorizo tomar la foto</label>}
      <button className="feria-primary" disabled={!consent} onClick={() => { setName(firstName(name)); afterNameStep() }}>Continuar <span aria-hidden="true">→</span></button><button className="feria-quiet" disabled={!consent} onClick={() => { setName(''); afterNameStep() }}>Saltar</button>
    </section>}
    {step === 'welcome' && welcomeSrc && <FeriaWelcome src={welcomeSrc} name={name} onDone={afterWelcome} />}
    {step === 'menu' && <section className="feria-panel feria-menu"><span className="feria-eyebrow">{child ? 'ELIGE TU AVENTURA' : 'ELIGE TU FOTO'}</span><h1 ref={heading} tabIndex={-1}>¿Cómo quieres tu foto?</h1>
      <div className="feria-menu-opciones">
        <button className="feria-opcion" onClick={startPersonaje}><span aria-hidden="true">{withCharacters ? '🎡' : '📸'}</span><strong>{withCharacters ? 'Foto con tu personaje' : 'Foto con la temática'}</strong><small>{withCharacters ? 'Gira la ruleta, conoce a tu personaje y juega antes de la foto' : 'Tu foto con el fondo de esta temática'}</small></button>
        {volantin && <button className="feria-opcion feria-opcion-juego" onClick={() => location.assign(volantin)}><span aria-hidden="true">🪁</span><strong>Juega Chile en Volantín</strong><small>Encumbra tu volantín de los cerros a la fonda</small></button>}
        <button className="feria-opcion feria-opcion-asomate" onClick={startAsomate}><strong>{asomate?.boton || '🦸 Asómate y sé el héroe'}</strong><small>{asomate?.titulo || 'Pon tu cara en el traje de tu personaje'}</small></button>
      </div>
    </section>}
    {step === 'roulette' && <Spinner onDone={winner} />}
    {step === 'character' && <Character personaje={person} invitado={name} onDone={afterCharacter} />}
    {step === 'juego' && Game && <Game invitado={name} personaje={person} onDone={camera} />}
    {step === 'asomate-elegir' && asomateOk && <AsomatePick onDone={(nuevo) => { setElenco(nuevo); setFotosAsomate([]); setStep('asomate-capturar') }} onCancel={() => setStep('menu')} />}
    {step === 'asomate-capturar' && asomateOk && elenco.length > 0 && <>
      {elenco.length > 1 && <p className="asomate-turno-aviso">Le toca a {elenco[fotosAsomate.length]?.emoji} <strong>{elenco[fotosAsomate.length]?.nombre}</strong> · {fotosAsomate.length + 1} de {elenco.length}</p>}
      <Capture key={fotosAsomate.length} guia={elenco[fotosAsomate.length]} onCapture={(dataUrl) => {
        const fotos = [...fotosAsomate, dataUrl]
        setFotosAsomate(fotos)
        if (fotos.length >= elenco.length) setStep('asomate-preview')
      }} />
    </>}
    {step === 'asomate-preview' && asomateOk && elenco.length > 0 && <AsomateReview elenco={elenco} fotos={fotosAsomate} invitado={name}
      onRetry={() => { setFotosAsomate([]); setStep('asomate-capturar') }}
      onSave={(compuesta, paraDiploma) => { setPerson(asomatePerson ? asomatePerson(elenco) : null); setHeroe(paraDiploma || null); setDiploma(null); prepare(compuesta, true) }} />}
    {step === 'camera' && <Camera filter={filter} segmenter={segmenter} scene={themeData.images?.escena} base={base} onCapture={prepare} />}
    {step === 'preview' && <section className="feria-panel feria-preview"><span className="feria-eyebrow">TU RECUERDO DE HOY</span><h1 ref={heading} tabIndex={-1}>{held ? `Tu foto: ${held.etiqueta}` : 'Preparando tu recuerdo'}</h1>{photo ? <img className="feria-result" src={photo} alt="Tu foto con el recuerdo y el número de la feria" /> : <p role="status">{busy ? 'Estamos preparando tu foto…' : 'La foto está lista para reintentar.'}</p>}
      {error && <p role="alert">{error}</p>}
      <div className="feria-actions">{photo ? <button className="feria-primary" disabled={busy} onClick={save}>{busy ? 'Preparando el QR…' : 'Guardar y ver mi QR'}</button> : <button className="feria-primary" disabled={busy} onClick={() => prepare(raw)}>Reintentar</button>}<button className="feria-quiet" disabled={busy} onClick={retake}>Tomar otra foto</button>{error && raw && <button className="feria-quiet" onClick={() => downloadImage(photo || raw, held?.etiqueta)}>Guardar en esta tablet</button>}</div>
    </section>}
    {completed && <section className={`feria-panel feria-complete ${step === 'diploma' ? 'feria-diploma' : ''}`}><span className="feria-eyebrow">{step === 'diploma' ? 'UN DIPLOMA PARA TI' : 'LLÉVATE ESTE MOMENTO'}</span><h1 ref={heading} tabIndex={-1}>Tu foto: {held.etiqueta}</h1>
      {step === 'qr' ? <><img className="feria-qr" src={qr} alt="Escanea este código QR para descargar tu foto" /><p>Escanea el QR con tu celular.<br />Guarda tu número para encontrar tu foto.</p>{child && <button disabled={busy} onClick={showDiploma}>{busy ? 'Preparando diploma…' : 'Ver mi diploma'}</button>}</> : <><img className="feria-result" src={diploma} alt={`Diploma en ${feria.nombre} · ${feria.fecha_texto}`} /><div className="feria-actions"><button onClick={() => downloadImage(diploma, `diploma-${held.etiqueta}`)}>Guardar diploma en la tablet</button><button className="feria-quiet" onClick={() => setStep('qr')}>Volver al QR de mi foto</button></div></>}
      {error && <p role="alert">{error}</p>}<button className="feria-primary" onClick={finish}>Terminar</button><p className="feria-countdown">Volvemos al inicio en {remaining} s</p>
    </section>}
  </main>
}

// Música de fondo de la temática, con los mismos niveles que el kiosco de fiesta (App.jsx: MUSIC_VOL 0,15 y 0,04
// bajo las voces): baja en la intro y el saludo del personaje, y al entrar al minijuego unos segundos por su narración.
const MUSICA = 0.15
const MUSICA_BAJO_VOZ = 0.04
function useFeriaMusic(src, step) {
  const audio = useRef(null)
  const [muted, setMuted] = useState(false)
  const start = useCallback(() => {
    if (!src || audio.current) return
    const a = new Audio(src)
    a.loop = true; a.volume = MUSICA
    a.play().catch(() => {})
    audio.current = a
  }, [src])
  useEffect(() => {
    const a = audio.current
    if (!a) return
    if (step === 'welcome' || step === 'character') { a.volume = MUSICA_BAJO_VOZ; return }
    if (step === 'juego') {
      a.volume = MUSICA_BAJO_VOZ
      const t = setTimeout(() => { if (audio.current) audio.current.volume = MUSICA }, 6000)
      return () => clearTimeout(t)
    }
    a.volume = MUSICA
  }, [step])
  useEffect(() => { if (audio.current) audio.current.muted = muted }, [muted])
  useEffect(() => () => { if (audio.current) { audio.current.pause(); audio.current = null } }, [])
  return { start, muted, toggle: () => setMuted((m) => !m) }
}

// La intro de la temática con las mismas piezas y reglas que ListaInvitados en App.jsx: avanza al terminar, con un
// toque o si el video falla; el temporizador de seguridad le da lo que le falta a un video que sigue avanzando
// (tope 45 s), para no cortar a media frase en una tablet lenta.
function FeriaWelcome({ src, name, onDone }) {
  const video = useRef(null)
  const timer = useRef(null)
  const progreso = useRef({ t: -1, desde: Date.now() })
  const listo = useRef(false)
  const reduce = useRef(typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches)
  const terminar = useCallback(() => {
    if (listo.current) return
    listo.current = true
    clearTimeout(timer.current)
    onDone()
  }, [onDone])
  const vigilar = useCallback((ms) => {
    clearTimeout(timer.current)
    timer.current = setTimeout(() => {
      const v = video.current
      const avanza = v && !v.ended && !v.paused && v.currentTime > progreso.current.t + 0.05
      if (avanza && Date.now() - progreso.current.desde < 45000 && Number.isFinite(v.duration)) {
        progreso.current.t = v.currentTime
        vigilar(Math.max(800, (v.duration - v.currentTime) * 1000 + 600))
        return
      }
      terminar()
    }, ms)
  }, [terminar])
  useEffect(() => {
    vigilar(reduce.current ? 1200 : 20000)
    return () => clearTimeout(timer.current)
  }, [vigilar])
  return <div className="welcome-popup" onClick={terminar} data-feria-welcome="">
    <h2>{name ? '¡Te damos la bienvenida!' : '¡Bienvenidos!'}</h2>
    {name && <p className="welcome-name">{name}</p>}
    <div className="welcome-car3d" aria-hidden="true">
      {reduce.current ? <span className="welcome-car3d-emoji">🎉</span> : <video className="welcome-car3d-video" src={src} autoPlay playsInline ref={video}
        onLoadedMetadata={(e) => {
          const d = Number(e.currentTarget?.duration)
          progreso.current = { t: -1, desde: Date.now() }
          if (Number.isFinite(d) && d > 0) vigilar(Math.min(30000, d * 1000 + 600))
        }}
        onEnded={terminar} onError={terminar} />}
    </div>
  </div>
}
