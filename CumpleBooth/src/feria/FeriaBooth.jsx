import React, { useCallback, useEffect, useRef, useState } from 'react'
import QRCode from 'qrcode'
import Camera from './Camera.jsx'
import { createSegmenter } from './segmentation.js'
import { filterImage } from './photo.js'
import { afterName, armIdle, firstName, initialPhotoStep, reservation, returnUrl, takeConsent, upload } from './contract.js'
import { downloadImage, withRemembrance } from './media.js'
import { FullscreenButton, useFullscreenOnTap } from './fullscreen.jsx'
import { prepararVideo, urlDeVideo } from '../videoListo.js'
import { fondoEstilo, pendonesDe } from './fondo.js'
import { textosPortada } from './revista.js'
import { INTRO_ARRANQUE_MS, alVencer, esperaPorDuracion, restanteMinimo } from './intro.js'
import './feria.css'

// Niños en feria recupera lo de una fiesta (Luis, 26-09, ya en la feria): el minijuego del personaje antes de la
// foto y Asómate cuando la temática lo trae. Las piezas son las del kiosco normal y llegan como props, para que
// este archivo no dependa del estado de BoothApp; la foto igual sale con número, recuerdo y QR de la feria.
export default function FeriaBooth({ feria, theme, themeData, characters, filter, base, Spinner, Character, renderPhoto, renderDiploma,
  asomate = null, gameFor = null, Game = null, AsomatePick = null, Capture = null, AsomateReview = null, asomatePerson = null, prepareAsomate = null,
  welcomeSrc = null, despedidaSrc = null, music = null, volantinUrl = null }) {
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
  // Portada de Revista (04-10): el fondo lo elige cada invitado en el menú (alfombra roja, estudio de color, blanco y negro).
  const [variante, setVariante] = useState(null)
  const locked = useRef(false)
  const reservationRef = useRef(null)
  const uploaded = useRef('')
  const heading = useRef(null)
  const child = feria.modo === 'infantil'
  const destination = returnUrl(new URLSearchParams(location.search).get('volver'), feria.slug)
  const leave = useCallback(() => location.replace(destination), [destination])
  // Al terminar, la despedida de la temática como en una fiesta (Luis, 28-09); sin video se vuelve directo al selector.
  const finish = useCallback(() => { if (despedidaSrc && held) setStep('despedida'); else leave() }, [despedidaSrc, held, leave])
  const winner = useCallback((value) => { setPerson(value); setStep('character') }, [])
  const camera = useCallback(() => setStep('camera'), [])
  const afterCharacter = useCallback(() => setStep(child && gameFor && person && gameFor(person.name) ? 'juego' : 'camera'), [child, gameFor, person])
  const asomateOk = Boolean(asomate && AsomatePick && Capture && AsomateReview)
  const revista = themeData?.revista || null
  const variantes = Array.isArray(revista?.variantes) ? revista.variantes : []
  const conVariantes = variantes.length > 1
  const conMenu = asomateOk || conVariantes
  const filtroFoto = variante?.filtro ?? filter
  const portada = revista ? { textos: textosPortada({ titulo: revista.titulo, nombre: firstName(name), evento: feria.nombre, fecha: feria.fecha, propios: revista.textos }),
    estilo: { tinta: variante?.tinta, acento: variante?.acento, evento: feria.nombre } } : null
  useFullscreenOnTap()
  // Los videos de la temática se bajan enteros apenas se abre el kiosco, como la despedida en una fiesta: la intro
  // arranca en el acto aunque el wifi del salón esté lento y no se cae a media descarga (Luis, 28-09).
  useEffect(() => { prepararVideo(welcomeSrc); prepararVideo(despedidaSrc) }, [welcomeSrc, despedidaSrc])
  const afterWelcome = useCallback(() => setStep(afterName(characters, conMenu)), [characters, conMenu])
  const { start: startMusic, muted, toggle: toggleMusic } = useFeriaMusic(music, step)
  // Tras el nombre, la intro de la temática como en una fiesta (Luis, 26-09). El toque del nombre es el gesto
  // que deja sonar el video y la música; sin intro se sigue directo.
  const afterNameStep = () => { startMusic(); setStep(welcomeSrc ? 'welcome' : afterName(characters, conMenu)) }

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
      const composed = precompuesta ? source : await renderPhoto(source, firstName(name), person, filtroFoto, segmenter, { variante, portada })
      const finalPhoto = await filterImage(await withRemembrance(composed, feria, number, pendonesDe(themeData?.confetti)), filtroFoto)
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
      if (!diploma) setDiploma(await withRemembrance(await renderDiploma(firstName(name), person, route === 'asomate' ? heroe : null), feria, held, pendonesDe(themeData?.confetti)))
      setStep('diploma')
    } catch { setError('No pudimos preparar el diploma. Puedes intentarlo nuevamente.') }
    finally { locked.current = false; setBusy(false) }
  }

  const startAsomate = () => {
    if (prepareAsomate) prepareAsomate()
    setRoute('asomate'); setElenco([]); setFotosAsomate([]); setStep('asomate-elegir')
  }
  const startPersonaje = () => { setRoute('personaje'); setStep(initialPhotoStep(characters)) }
  const startPortada = (v) => { setVariante(v); setRoute('personaje'); setStep('camera') }
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
    style={fondoEstilo(base, location.href, themeData?.images?.fondoEvento, feria.fondo)}>
    <header className="feria-kiosk-header"><span>CumpleClick <b>·</b> {feria.nombre}</span><span className="feria-kiosk-acciones">{music && <button className="feria-fullscreen" type="button" onClick={toggleMusic} aria-label={muted ? 'Activar música' : 'Silenciar música'} title={muted ? 'Activar música' : 'Silenciar música'}><span aria-hidden="true">{muted ? '🔇' : '🎵'}</span></button>}<FullscreenButton />{!completed && <button className="feria-quiet" disabled={busy} onClick={finish}>Salir</button>}</span></header>
    {step === 'name' && <section className="feria-panel feria-name"><img className="feria-name-logo" src={base + 'brand/cumpleclick-mark.svg'} alt="CumpleClick" /><span className="feria-eyebrow">{child ? 'TU AVENTURA COMIENZA' : 'UN RETRATO A TU MANERA'}</span><h1 ref={heading} tabIndex={-1}>¿Cómo te llamas?</h1><p>Solo tu primer nombre, si quieres.</p><label className="feria-name-label">Tu nombre <span>(opcional)</span><input value={name} maxLength={20} autoComplete="off" autoCapitalize="words" onChange={(e) => setName(e.target.value)} placeholder="Tu primer nombre" /></label>
      {child && !consent && <label className="feria-consent"><input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />Soy el adulto responsable y autorizo tomar la foto</label>}
      <button className="feria-primary" disabled={!consent} onClick={() => { setName(firstName(name)); afterNameStep() }}>Continuar <span aria-hidden="true">→</span></button><button className="feria-quiet" disabled={!consent} onClick={() => { setName(''); afterNameStep() }}>Saltar</button>
    </section>}
    {step === 'welcome' && welcomeSrc && <FeriaVideo marca="welcome" src={welcomeSrc} titulo={name ? '¡Te damos la bienvenida!' : '¡Bienvenidos!'} name={name} emoji="🎉" onDone={afterWelcome} />}
    {step === 'despedida' && despedidaSrc && <FeriaVideo marca="despedida" src={despedidaSrc} titulo="¡Gracias por venir!" name={name} emoji="👋" onDone={leave} />}
    {step === 'menu' && <section className="feria-panel feria-menu"><span className="feria-eyebrow">{conVariantes ? 'ELIGE TU PORTADA' : child ? 'ELIGE TU AVENTURA' : 'ELIGE TU FOTO'}</span><h1 ref={heading} tabIndex={-1}>{conVariantes ? `¿Dónde sales en la portada de ${revista.titulo}?` : '¿Cómo quieres tu foto?'}</h1>
      <div className="feria-menu-opciones">
        {conVariantes && variantes.map((v) => <button key={v.clave} className="feria-opcion feria-opcion-portada" onClick={() => startPortada(v)}><span aria-hidden="true">{v.emoji || '📸'}</span><strong>{v.etiqueta}</strong>{v.detalle && <small>{v.detalle}</small>}</button>)}
        {!conVariantes && <button className="feria-opcion" onClick={startPersonaje}><span aria-hidden="true">{withCharacters ? '🎡' : '📸'}</span><strong>{withCharacters ? 'Foto con tu personaje' : 'Foto con la temática'}</strong><small>{withCharacters ? 'Gira la ruleta, conoce a tu personaje y juega antes de la foto' : 'Tu foto con el fondo de esta temática'}</small></button>}
        {volantin && <button className="feria-opcion feria-opcion-juego" onClick={() => location.assign(volantin)}><span aria-hidden="true">🪁</span><strong>Juega Chile en Volantín</strong><small>Encumbra tu volantín de los cerros a la fonda</small></button>}
        {asomateOk && <button className="feria-opcion feria-opcion-asomate" onClick={startAsomate}><strong>{asomate?.boton || '🦸 Asómate y sé el héroe'}</strong><small>{asomate?.titulo || 'Pon tu cara en el traje de tu personaje'}</small></button>}
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
    {step === 'camera' && <Camera filter={filtroFoto} segmenter={segmenter} scene={variante?.escena || themeData.images?.escena} base={base} onCapture={prepare} portada={portada} />}
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
    if (step === 'welcome' || step === 'character' || step === 'despedida') { a.volume = MUSICA_BAJO_VOZ; return }
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

// La intro y la despedida de la temática, con las mismas piezas que ListaInvitados y VideoScreen en App.jsx, y una
// regla más (Luis, 28-09: en la feria la intro se saltaba y caía directo a la ruleta): el video se monta desde memoria
// si ya bajó, se ve aunque la tablet pida menos animaciones, y si no arranca o falla queda una tarjeta con el nombre
// en vez de saltar. Un toque la termina, como en una fiesta. Los tiempos viven en intro.js.
function FeriaVideo({ marca, src, titulo, name, emoji, onDone }) {
  const video = useRef(null)
  const timer = useRef(null)
  const estado = useRef({ t: -1, desde: Date.now(), listo: false })
  const [tarjeta, setTarjeta] = useState(false)
  const terminar = useCallback(() => {
    if (estado.current.listo) return
    estado.current.listo = true
    clearTimeout(timer.current)
    onDone()
  }, [onDone])
  const terminarTras = useCallback((ms) => { clearTimeout(timer.current); timer.current = setTimeout(terminar, ms) }, [terminar])
  const vigilar = useCallback((ms) => {
    clearTimeout(timer.current)
    timer.current = setTimeout(() => {
      const v = video.current
      const paso = alVencer({ video: v ? { ended: v.ended, paused: v.paused, currentTime: v.currentTime, duration: v.duration } : null,
        ultimoTiempo: estado.current.t, desde: estado.current.desde, ahora: Date.now() })
      if (paso.accion === 'esperar') { estado.current.t = paso.tiempo; vigilar(paso.ms); return }
      if (paso.accion === 'tarjeta') { setTarjeta(true); terminarTras(paso.ms); return }
      terminar()
    }, ms)
  }, [terminar, terminarTras])
  useEffect(() => {
    vigilar(INTRO_ARRANQUE_MS)
    return () => clearTimeout(timer.current)
  }, [vigilar])
  const fallo = () => { setTarjeta(true); terminarTras(restanteMinimo(estado.current.desde, Date.now())) }
  const extra = marca === 'welcome' ? { 'data-feria-welcome': '' } : {}
  return <div className="welcome-popup" onClick={terminar} data-feria-video={marca} {...extra}>
    <h2>{titulo}</h2>
    {name && <p className="welcome-name">{name}</p>}
    <div className="welcome-car3d" aria-hidden="true">
      {tarjeta ? <span className="welcome-car3d-emoji">{emoji}</span> : <video className="welcome-car3d-video" src={urlDeVideo(src)} autoPlay playsInline ref={video}
        onLoadedMetadata={(e) => { estado.current.t = -1; const ms = esperaPorDuracion(e.currentTarget?.duration); if (ms) vigilar(ms) }}
        onEnded={() => terminarTras(restanteMinimo(estado.current.desde, Date.now()))} onError={fallo} />}
    </div>
  </div>
}
