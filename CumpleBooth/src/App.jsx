import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import QRCode from 'qrcode'
import { ensureCanvasFonts } from './fonts.js'
import { applyThemeColors } from './themeVars.js'
import {
  getSquarePhotoGeometry,
  getTrackCharacterGeometry,
  normalizeFrameBox,
} from './frameGeometry.js'
import ToyTrack3D, { supports3D } from './ToyTrack3D.jsx'
import ThemeWorld3D from './ThemeWorld3D.jsx'
import StageConcert3D from './StageConcert3D.jsx'
import { createAudioKit, createBeatClock, DEFAULT_BPM } from './gameAudio.js'
import { configurarRecords, guardarRecord, textoRecord, formatoSegundos } from './records.js'
import { resolveThemeFlow } from './themeFlow.js'
import { selectSpinnerWinnerIndex } from './spinnerWinner.js'
import { PREDICTION_OPTIONS, createPredictionSubmissionToken, predictionLabels, predictionSummary, validPrediction } from './predictions.js'

/* ============================================================
   RUNTIME CONFIG — multi-fiesta, cero rebuilds ★
   Ya NO se edita nada por cliente en este archivo. Todo llega en runtime
   desde api.php?p=<slug> (ver docs/ARQUITECTURA.md § "Front (React)").

   Componentes de pantalla (Intro, Spinner, VideoPersonaje, composeImage,
   burstConfetti, etc.) leen estas variables de MÓDULO. <BoothApp> solo se
   monta después de que buildRuntime() las haya poblado con la respuesta de
   la API — por eso es seguro que sigan siendo "constantes" desde el punto
   de vista de esos componentes, aunque técnicamente sean `let` reasignables.
   ============================================================ */
const BASE = import.meta.env.BASE_URL // base relativa './' — funciona en cualquier carpeta
// Isotipo oficial CumpleClick ("El globo dulce") — ver design/MANUAL-DE-MARCA.md
const BRAND_LOGO_SRC = BASE + 'brand/cumpleclick-mark.svg'

let CONFIG = null
let PARTY_SLUG = null
let THEME_SLUG = null
let STORAGE_KEY = null
let PERSONAJES = []
let CHAR_IMG = {}
let CHAR_VIDEO = {}
let CHAR_PNG = {} // recorte transparente OPCIONAL por personaje (solo si pngExists=true)
let CHAR_RUN_ATLAS = {} // hoja multivista OPCIONAL para el runner 3D Full
// Narración OPCIONAL (voz Alice) antes del primer juego: "Antes de tomarte
// una foto con <Personaje>, juguemos un rato. Si no, puedes oprimir Saltar."
// Mismo patrón resiliente que CHAR_VIDEO: si el mp3 no existe, playSound()
// falla en silencio y el juego arranca igual, sin narración.
let CHAR_JUEGO_AUDIO = {}
let THEME_FLOW = resolveThemeFlow(null)
let WELCOME_VIDEO_PRIMARY = BASE + 'welcome-car.mp4'
// Video de suspenso opcional entre la captura y el resultado ("Cargando tu
// foto..."). Sin asset, la pantalla no aparece — el flujo sigue igual que
// antes de esto para el resto de las temáticas.
let REVELACION_VIDEO = null
// Segundo saludo de entrada opcional. Solo Carreras lo tiene hoy; las demás
// temáticas continúan usando el saludo genérico hasta que tengan su asset.
let WELCOME_VIDEO_ALT = null
let WELCOME_VIDEO_TURN = 0 // persiste entre invitados durante la sesión del kiosco
let WELCOME_VIDEO_TURN_KEY = null
let DIPLOMA = '' // theme.diploma — título honorífico del diploma (ej "Piloto Oficial del Equipo")
let THEME_LABEL = '' // theme.franquicia (ej "Cars") con fallback a theme.nombre — rótulo sobre el marco
let INVITADOS_DEFAULT = []
let MUSIC_ENABLED = true
let GRUPO_IMG = null
let CONFETTI_COLORS = ['#e8000d', '#ffb800', '#1a1a1a', '#c0c0c0', '#ffffff', '#ffd166'] // fallback antes de runtime

const brandLogoCache = { img: null, ready: false, failed: false, promise: null }

function preloadBrandLogo() {
  if (brandLogoCache.promise) return brandLogoCache.promise
  if (typeof Image === 'undefined') return Promise.resolve(false)
  brandLogoCache.promise = new Promise((resolve) => {
    const img = new Image()
    brandLogoCache.img = img
    img.onload = () => {
      brandLogoCache.ready = true
      resolve(true)
    }
    img.onerror = () => {
      brandLogoCache.failed = true
      resolve(false)
    }
    img.src = BRAND_LOGO_SRC
  })
  return brandLogoCache.promise
}

function getBrandLogo() {
  return brandLogoCache.ready ? brandLogoCache.img : null
}

// Aplica los colores de la temática como variables CSS globales.
// Los 9 tokens de color los vuelca applyThemeColors() (src/themeVars.js), que
// comparte con el Álbum Recuerdo; acá quedan solo los fondos que son propios
// del kiosco.
function applyThemeVars(colors) {
  if (!colors) return
  const root = document.documentElement.style
  applyThemeColors(colors)
  if (THEME_SLUG) {
    // Absoluta a propósito: una URL relativa dentro de una custom property se
    // resuelve contra la hoja de estilos donde se usa var() (dist/assets/*.css
    // tras el build de Vite), no contra index.html — con ruta relativa termina
    // pidiendo dist/assets/themes/... (404). Con new URL() queda absoluta y
    // no importa dónde vive el CSS.
    const grupoUrl = new URL(BASE + 'themes/' + THEME_SLUG + '/grupo-personajes.png', document.baseURI).href
    root.setProperty('--grupo-bg', `url("${grupoUrl}")`)
  }
  if (CONFIG?.images?.roulette) {
    root.setProperty('--roulette-bg', `url("${new URL(CONFIG.images.roulette, document.baseURI).href}")`)
  } else {
    root.removeProperty('--roulette-bg')
  }
}

// Construye el RUNTIME (módulo-nivel) a partir de {party, theme} de api.php.
// Se llama UNA vez, antes de montar <BoothApp>, así que cuando los
// componentes de pantalla la lean ya está completo.
function buildRuntime(party, theme, slug) {
  PARTY_SLUG = slug
  THEME_SLUG = theme.slug || slug
  WELCOME_VIDEO_PRIMARY = theme.videos?.welcome || BASE + 'welcome-car.mp4'
  REVELACION_VIDEO = theme.videos?.revelacion ? BASE + theme.videos.revelacion : null
  WELCOME_VIDEO_ALT = THEME_SLUG === 'carreras'
    ? BASE + 'themes/carreras/saludo-rayo-mcqueen-v3.mp4'
    : null
  STORAGE_KEY = 'booth_' + slug
  // Marcador de la fiesta: los récords se guardan por slug, así una fiesta
  // nueva arranca limpia y no hereda las marcas de la anterior.
  configurarRecords(STORAGE_KEY)
  WELCOME_VIDEO_TURN_KEY = STORAGE_KEY + '_welcome_video_turn'
  // Mantiene el turno aun si el operador recarga la página mientras prueba.
  // Si el navegador bloquea sessionStorage, el contador en memoria conserva
  // la alternancia mientras el kiosco siga abierto.
  try {
    WELCOME_VIDEO_TURN = Math.max(0, Number.parseInt(sessionStorage.getItem(WELCOME_VIDEO_TURN_KEY) || '0', 10) || 0)
  } catch {
    WELCOME_VIDEO_TURN = 0
  }
  CONFIG = {
    nombre: party.nombre,
    videos: {
      saludo: BASE + 'videos/saludo.mp4',
      // Overridable por tema (theme.videos.despedida) igual que welcome y
      // revelacion. El genérico 'videos/despedida.mp4' históricamente ni
      // siquiera existe en disco — VideoScreen ya cae a una tarjeta con
      // emoji si el archivo falla, así que temas sin despedida propia
      // siguen viéndose exactamente igual que antes de esto.
      despedida: theme.videos?.despedida ? BASE + theme.videos.despedida : BASE + 'videos/despedida.mp4',
    },
    images: {
      fondo: BASE + theme.images.sala, // sala con marco dorado (compositing + transicion)
      bienvenida: BASE + theme.images.banner, // pantalla de bienvenida (intro)
      roulette: theme.images.roulette ? BASE + theme.images.roulette : null,
    },
    audio: {
      captura: BASE + 'audio/captura.mp3', // opcional, genérico (no por temática)
      confetti: BASE + 'audio/confetti.mp3', // opcional, genérico (no por temática)
      nota: BASE + 'audio/nota.mp3', // opcional, genérico: al atrapar en el juego de copos
      error: BASE + 'audio/error.mp3', // opcional, genérico: al tocar una trampa en el juego de copos
      musica: BASE + theme.musica, // música de fondo en loop
      // Música exclusiva de la pantalla de juegos (Luis, 2026-07-26: en los
      // juegos suena "Y si hacemos un muñeco", en el resto "Libre soy").
      // Sin este archivo el juego conserva la música de fondo normal.
      musicaJuego: theme.musicaJuego ? BASE + theme.musicaJuego : null,
    },
    // Endpoint PHP que guarda la foto y devuelve URL pública (solo en prod/Hostinger).
    // En localhost no existe → el QR cae a texto automáticamente.
    uploadEndpoint: BASE + 'upload.php',
    predictionEndpoint: BASE + 'prediction-api.php',
    // Geometría del marco decorativo del fondo. Ya viene resuelta desde el
    // backend (override de la fiesta o default de la temática).
    frameBox: normalizeFrameBox(party.frameBox),
    // fecha de la fiesta — el api actual NO la expone (ver docs/ARQUITECTURA.md), así
    // que normalmente queda vacía. Se lee de forma defensiva por si algún día se agrega.
    fecha: party.fecha || '',
    // Música de fondo habilitada por el admin (default true si no viene del API)
    musicaHabilitada: party.musica !== false,
    servicePlan: party.service_plan === 'full' ? 'full' : 'booth',
    eventType: party.event_type === 'baby_shower' ? 'baby_shower' : 'child_birthday',
  }
  MUSIC_ENABLED = CONFIG.musicaHabilitada
  CONFETTI_COLORS = Array.isArray(theme.confetti) && theme.confetti.length ? theme.confetti : CONFETTI_COLORS
  DIPLOMA = theme.diploma || ''
  THEME_LABEL = (theme.franquicia || theme.nombre || '').toUpperCase()
  PERSONAJES = (theme.personajes || []).map((p) => ({ emoji: p.emoji, name: p.name }))
  CHAR_IMG = {}
  CHAR_VIDEO = {}
  CHAR_PNG = {}
  CHAR_RUN_ATLAS = {}
  THEME_FLOW = resolveThemeFlow(theme)
  ;(theme.personajes || []).forEach((p) => {
    CHAR_IMG[p.name] = BASE + p.img
    // Video de saludo OPCIONAL por temática: themes/<tema>/saludo-<base-del-img>.mp4
    // (si el archivo no existe, VideoPersonaje cae a la imagen automáticamente)
    CHAR_VIDEO[p.name] = BASE + p.img.replace(/([^/]+)\.(jpe?g|png)$/i, 'saludo-$1.mp4')
    // Narración OPCIONAL antes del juego: themes/<tema>/invitacion-juego-<base-del-img>.mp3
    CHAR_JUEGO_AUDIO[p.name] = BASE + p.img.replace(/([^/]+)\.(jpe?g|png)$/i, 'invitacion-juego-$1.mp3')
    // Recorte transparente OPCIONAL del personaje (themes/<slug>/<base>-cut.png).
    // Solo se registra si el backend confirmó que el archivo existe (pngExists).
    if (p.pngExists && p.png) {
      CHAR_PNG[p.name] = BASE + p.png
    }
    // El backend publica el atlas únicamente en plan Full y tras confirmar
    // que existe en disco. ThemeWorld3D conserva fallback al recorte/JPG si
    // la carga falla después (por ejemplo, un FTP incompleto).
    if (p.runnerAtlasExists && p.runnerAtlas) {
      CHAR_RUN_ATLAS[p.name] = BASE + p.runnerAtlas
    }
  })
  INVITADOS_DEFAULT = Array.isArray(party.invitados) ? party.invitados : []
  preloadBrandLogo()
  // Precarga imagen grupal para watermark en diploma
  const grupoUrl = BASE + 'themes/' + THEME_SLUG + '/grupo-personajes.png'
  const gi = new Image()
  gi.onload = () => { GRUPO_IMG = gi }
  gi.src = grupoUrl
}

const REDUCE_MOTION =
  typeof window !== 'undefined' &&
  window.matchMedia &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

function takeWelcomeVideoTurn() {
  const turn = WELCOME_VIDEO_TURN++
  try {
    sessionStorage.setItem(WELCOME_VIDEO_TURN_KEY, String(WELCOME_VIDEO_TURN))
  } catch {
    // La alternancia en memoria sigue siendo suficiente para la sesión activa.
  }
  return turn
}

/* ============================================================
   Confetti propio (canvas, sin librerias)
   CONFETTI_COLORS es `let` de módulo — buildRuntime() lo reemplaza con la
   paleta de la temática antes de que <BoothApp> (y por tanto cualquier
   pantalla que dispare confetti) se monte.
   ============================================================ */
function burstConfetti(canvas, { duration = 2600, count = 160 } = {}) {
  if (!canvas) return
  if (REDUCE_MOTION) return
  const ctx = canvas.getContext('2d')
  const dpr = window.devicePixelRatio || 1
  const W = (canvas.width = canvas.clientWidth * dpr)
  const H = (canvas.height = canvas.clientHeight * dpr)
  const parts = Array.from({ length: count }, () => ({
    x: Math.random() * W,
    y: -20 - Math.random() * H * 0.4,
    r: (6 + Math.random() * 8) * dpr,
    vx: (-1 + Math.random() * 2) * 2 * dpr,
    vy: (2 + Math.random() * 4) * dpr,
    rot: Math.random() * Math.PI,
    vr: -0.2 + Math.random() * 0.4,
    color: CONFETTI_COLORS[(Math.random() * CONFETTI_COLORS.length) | 0],
  }))
  const start = performance.now()
  let raf
  const tick = (now) => {
    const t = now - start
    ctx.clearRect(0, 0, W, H)
    parts.forEach((p) => {
      p.x += p.vx
      p.y += p.vy
      p.vy += 0.04 * dpr
      p.rot += p.vr
      ctx.save()
      ctx.translate(p.x, p.y)
      ctx.rotate(p.rot)
      ctx.fillStyle = p.color
      ctx.fillRect(-p.r / 2, -p.r / 2, p.r, p.r * 0.6)
      ctx.restore()
    })
    if (t < duration) raf = requestAnimationFrame(tick)
    else ctx.clearRect(0, 0, W, H)
  }
  raf = requestAnimationFrame(tick)
  return () => cancelAnimationFrame(raf)
}

/* ============================================================
   helpers
   ============================================================ */
function playSound(src) {
  try {
    const a = new Audio(src)
    a.volume = 0.85
    a.play().catch(() => {})
  } catch {}
}
/* ============================================================
   Cache de precarga del PNG transparente del personaje (composeImage
   es síncrona y se llama en Preview, así que el PNG se precarga ANTES,
   apenas la ruleta elige personaje — para cuando el invitado llegue a
   Preview, normalmente ya está listo). Si no hay pngExists para ese
   personaje, o no cargó a tiempo, composeImage simplemente lo omite.
   ============================================================ */
const charPngCache = {} // name -> { img: Image, ready: bool, failed: bool }

function preloadCharPng(name) {
  if (!name) return
  const src = CHAR_PNG[name]
  if (!src) return
  if (charPngCache[name]) return // ya se inició (o terminó) la carga
  const img = new Image()
  const entry = { img, ready: false, failed: false }
  charPngCache[name] = entry
  img.onload = () => {
    entry.ready = true
  }
  img.onerror = () => {
    entry.failed = true
  }
  img.src = src
}

// Devuelve el <img> ya cargado del personaje, o null (aún cargando / sin PNG / falló).
function getCharPng(name) {
  const entry = name && charPngCache[name]
  return entry && entry.ready ? entry.img : null
}

/* ============================================================
   Invitados — lista inicial = party.invitados (buildRuntime), con
   localStorage como prioridad si la tablet ya tiene una lista guardada.
   PERSONAJES / CHAR_IMG (los 6 de la ruleta) también los puebla buildRuntime
   con theme.personajes. Todo esto ya está listo cuando <BoothApp> se monta.
   ============================================================ */
function loadInvitados() {
  try {
    const s = localStorage.getItem(STORAGE_KEY + "_invitados")
    if (s) {
      const arr = JSON.parse(s)
      if (Array.isArray(arr) && arr.length) return arr
    }
  } catch {}
  return INVITADOS_DEFAULT
}
function saveInvitados(list) {
  try {
    localStorage.setItem(STORAGE_KEY + "_invitados", JSON.stringify(list))
  } catch {}
}

const SCREENS = ['intro', 'prediccion', 'prediction-save', 'invitados', 'spinner', 'photo-session', 'video-personaje', 'juego', 'transition', 'capture', 'revelacion', 'prediction-reveal', 'preview', 'qr', 'diploma', 'farewell']

// Volumen de la música de fondo. Bajo a propósito: es ambiente, nunca compite
// con las voces de los personajes ni con la narración.
const MUSIC_VOL = 0.15
// Mientras suena la narración de entrada al juego, la música baja a ~1/4 para
// que se entienda lo que dice (Luis, 2026-07-26).
const MUSIC_VOL_NARRACION = 0.04
// Duración de esa narración con margen: los mp3 rondan los 5s.
const NARRACION_JUEGO_MS = 6000

/* ============================================================
   App — puerta de entrada RUNTIME
   1) Lee ?p=<slug> de la URL. Sin slug → NoPartyScreen.
   2) fetch('api.php?p='+slug) → LoadingScreen mientras tanto.
   3) OK → buildRuntime() + applyThemeVars() y recién ahí monta <BoothApp>.
   4) Error/404/inactiva → ErrorScreen con botón reintentar.
   ============================================================ */
export default function App() {
  const [status, setStatus] = useState('loading') // 'loading' | 'noparty' | 'ready' | 'error'
  const [errorCode, setErrorCode] = useState(null)
  const [slug, setSlug] = useState(null)
  const [retryTick, setRetryTick] = useState(0)

  useEffect(() => {
    const p = new URLSearchParams(location.search).get('p')
    if (!p) {
      setStatus('noparty')
      return
    }
    setSlug(p)
    setStatus('loading')
    let alive = true

    fetch(BASE + 'api.php?p=' + encodeURIComponent(p), { cache: 'no-store' })
      .then(async (res) => {
        const data = await res.json().catch(() => null)
        if (!data || !data.ok) {
          throw new Error((data && data.error) || 'http_' + res.status)
        }
        return data
      })
      .then((data) => {
        if (!alive) return
        buildRuntime(data.party, data.theme, p)
        applyThemeVars(data.theme && data.theme.colors)
        setStatus('ready')
      })
      .catch((err) => {
        if (!alive) return
        setErrorCode((err && err.message) || 'unknown')
        setStatus('error')
      })

    return () => {
      alive = false
    }
  }, [retryTick])

  if (status === 'noparty') return <NoPartyScreen />
  if (status === 'loading') return <LoadingScreen />
  if (status === 'error') {
    return <ErrorScreen code={errorCode} onRetry={() => setRetryTick((t) => t + 1)} />
  }
  // status === 'ready' → RUNTIME ya está poblado, es seguro montar la app
  return <BoothApp key={slug} />
}

/* ============================================================
   Pantallas de la puerta de entrada (sin RUNTIME todavía, look genérico)
   ============================================================ */
function NoPartyScreen() {
  return (
    <div className="app">
      <section className="screen gate-screen">
        <div className="gate-emoji">🎪</div>
        <h1 className="gate-title">Fiesta no configurada</h1>
        <p className="gate-text">Pide el enlace a tu organizador para entrar a la fiesta correcta.</p>
      </section>
    </div>
  )
}

function LoadingScreen() {
  return (
    <div className="app">
      <section className="screen gate-screen">
        <div className="gate-emoji">🎪</div>
        <div className="gate-spinner" aria-hidden />
        <p className="gate-text">Preparando la fiesta…</p>
      </section>
    </div>
  )
}

function ErrorScreen({ code, onRetry }) {
  const msg =
    code === 'not_found'
      ? 'No encontramos esta fiesta. Revisa el enlace con tu organizador.'
      : code === 'inactive'
      ? 'Esta fiesta todavía no está activa. Inténtalo más tarde.'
      : 'No pudimos cargar la fiesta. Revisa tu conexión e inténtalo de nuevo.'
  return (
    <div className="app">
      <section className="screen gate-screen">
        <div className="gate-emoji">😵‍💫</div>
        <h1 className="gate-title">Algo no salió bien</h1>
        <p className="gate-text">{msg}</p>
        <button className="cta" onClick={onRetry}>
          🔄 Reintentar
        </button>
      </section>
    </div>
  )
}

function BoothApp() {
  const isBabyShower = CONFIG.eventType === 'baby_shower'
  const [screen, setScreen] = useState('intro')
  const [invitado, setInvitado] = useState(null)
  const [personaje, setPersonaje] = useState(null)
  const [photo, setPhoto] = useState(null)
  const [result, setResult] = useState(null)
  const [prediction, setPrediction] = useState(null)
  const [gameScore, setGameScore] = useState(null)
  const [invitadosList, setInvitadosList] = useState(loadInvitados)
  const [gestion, setGestion] = useState(
    () => new URLSearchParams(location.search).has('invitados')
  )
  const [muted, setMuted] = useState(false)
  const bgRef = useRef(null)
  const musicRef = useRef(null)
  const musicTrackRef = useRef(null) // pista sonando, para no reiniciarla en cada render
  const musicPosRef = useRef({}) // { [url de la pista]: segundo en que se dejó }
  const tapCornerR = useRef(0)

  // Inicia música de fondo (requiere gesto del usuario — se llama desde Intro)
  const startMusic = useCallback(() => {
    if (musicRef.current) return
    if (!MUSIC_ENABLED) return
    const audio = new Audio(CONFIG.audio.musica)
    audio.loop = true
    audio.volume = MUSIC_VOL
    audio.play().catch(() => {})
    musicRef.current = audio
    musicTrackRef.current = CONFIG.audio.musica
  }, [])

  // Sincroniza mute/unmute
  useEffect(() => {
    if (!musicRef.current) return
    musicRef.current.muted = muted
  }, [muted])

  // Pista y volumen de la música según la pantalla.
  //  - La pantalla de juegos puede tener su propia canción (musicaJuego).
  //  - Los videos con voz propia (pase de artista, saludo, despedida) la
  //    bajan al mismo nivel que la narración del juego: se sigue oyendo de
  //    fondo, pero sin competir con la voz (Luis, 2026-07-27).
  //  - Al entrar al juego suena la narración "Antes de tomarte una foto
  //    con...", así que la música baja unos segundos y después vuelve.
  useEffect(() => {
    if (!musicRef.current) return
    const audio = musicRef.current

    const track = (screen === 'juego' && CONFIG.audio.musicaJuego) || CONFIG.audio.musica
    if (track !== musicTrackRef.current) {
      // Cada pista RETOMA donde se quedó, no vuelve a empezar (Luis,
      // 2026-07-26): en una fiesta el mismo invitado entra y sale del juego
      // varias veces, y reiniciar "Libre soy" desde el segundo 0 cada vez
      // hacía que solo se escuchara siempre la misma intro.
      if (musicTrackRef.current) {
        musicPosRef.current[musicTrackRef.current] = audio.currentTime
      }
      musicTrackRef.current = track
      audio.src = track
      const desde = musicPosRef.current[track] || 0
      // Siempre por 'loadedmetadata': cambiar `src` dispara una recarga, pero
      // `readyState` puede seguir informando el valor de la pista ANTERIOR
      // durante un tick, y el seek se aplicaría al archivo equivocado.
      audio.addEventListener(
        'loadedmetadata',
        () => {
          try {
            audio.currentTime = desde < audio.duration ? desde : 0
          } catch {}
          audio.play().catch(() => {})
        },
        { once: true },
      )
      audio.load()
    }

    const conVozPropia =
      screen === 'photo-session' || screen === 'video-personaje' || screen === 'farewell'
    if (conVozPropia) {
      audio.volume = MUSIC_VOL_NARRACION
      return
    }

    if (screen === 'juego') {
      audio.volume = MUSIC_VOL_NARRACION
      const t = setTimeout(() => {
        if (musicRef.current) musicRef.current.volume = MUSIC_VOL
      }, NARRACION_JUEGO_MS)
      return () => clearTimeout(t)
    }

    audio.volume = MUSIC_VOL
  }, [screen])

  const updateInvitados = (list) => {
    setInvitadosList(list)
    saveInvitados(list)
  }

  // precargar fondo
  useEffect(() => {
    const img = new Image()
    img.src = CONFIG.images.fondo
    img.onload = () => (bgRef.current = img)
    img.onerror = () => (bgRef.current = null)
  }, [])

  const go = (s) => setScreen(s)
  const finishPredictionSave = useCallback(() => setScreen('capture'), [])
  const reset = () => {
    setInvitado(null)
    setPersonaje(null)
    setPhoto(null)
    setResult(null)
    setPrediction(null)
    setGameScore(null)
    setScreen('intro')
  }

  // toque 4x esquina sup-DERECHA => gestión de invitados (oculto para niños)
  const cornerTapRight = () => {
    tapCornerR.current += 1
    if (tapCornerR.current >= 4) {
      tapCornerR.current = 0
      setGestion(true)
    }
    setTimeout(() => (tapCornerR.current = 0), 1500)
  }

  return (
    <div className="app">
      <div className="corner-hit-right" onClick={cornerTapRight} aria-hidden />

      {/* Botón mute flotante — siempre visible excepto en intro */}
      {screen !== 'intro' && MUSIC_ENABLED && (
        <button
          className="mute-btn"
          onClick={() => setMuted((m) => !m)}
          aria-label={muted ? 'Activar música' : 'Silenciar música'}
        >
          {muted ? '🔇' : '🎵'}
        </button>
      )}

      {gestion && (
        <GestionInvitados
          list={invitadosList}
          onSave={updateInvitados}
          onClose={() => setGestion(false)}
        />
      )}

      {screen === 'intro' && (
        <Intro onStart={() => { startMusic(); go(isBabyShower ? 'prediccion' : 'invitados') }} />
      )}
      {screen === 'prediccion' && isBabyShower && (
        <PredictionScreen
          onDone={(value) => {
            setPrediction(value)
            setInvitado(value.guest_name)
            go('juego')
          }}
        />
      )}
      {screen === 'invitados' && (
        <ListaInvitados
          invitados={invitadosList}
          onStart={(nombre) => {
            setInvitado(nombre)
            go('spinner')
          }}
        />
      )}
      {screen === 'spinner' && (
        <Spinner
          onDone={(p) => {
            setPersonaje(p)
            // precarga el PNG transparente del personaje (si existe) ya mismo:
            // para cuando el invitado llegue a Preview (tras video+transición+captura)
            // normalmente ya está listo, sin bloquear nada si no llega a tiempo.
            preloadCharPng(p.name)
            go(THEME_FLOW.afterSpinner(p.name))
          }}
        />
      )}
      {screen === 'photo-session' && (
        <PhotoSessionVideo
          invitado={invitado}
          onDone={() => go('video-personaje')}
        />
      )}
      {screen === 'video-personaje' && personaje && (
        <VideoPersonaje
          personaje={personaje}
          invitado={invitado}
          onDone={() => go(THEME_FLOW.afterCharacter(personaje.name))}
        />
      )}
      {screen === 'juego' && (
        isBabyShower ? (
          <JuegoCopos
            config={{ kind: 'copos', seconds: 15, label: '¡Atrapa los chupetes!', emojis: ['🍼'] }}
            invitado={invitado}
            personaje={null}
            onDone={(score) => {
              setGameScore(Number.isFinite(score) ? score : 0)
              go('prediction-save')
            }}
          />
        ) : (
          <Juego
            invitado={invitado}
            personaje={personaje}
            onDone={() => go(THEME_FLOW.afterGame())}
          />
        )
      )}
      {screen === 'prediction-save' && isBabyShower && prediction && (
        <PredictionSave
          prediction={prediction}
          score={gameScore}
          onDone={finishPredictionSave}
        />
      )}
      {screen === 'transition' && (
        <TransicionWow invitado={invitado} personaje={personaje} onDone={() => go('capture')} />
      )}
      {screen === 'capture' && (
        <Capture
          onCapture={(dataUrl) => {
            setPhoto(dataUrl)
            go(isBabyShower ? 'prediction-reveal' : (REVELACION_VIDEO ? 'revelacion' : 'preview'))
          }}
        />
      )}
      {screen === 'revelacion' && <Revelacion invitado={invitado} onDone={() => go('preview')} />}
      {screen === 'prediction-reveal' && isBabyShower && prediction && (
        <PredictionReveal
          photo={photo}
          bgRef={bgRef}
          prediction={prediction}
          score={gameScore}
          onRetry={() => {
            setPhoto(null)
            go('capture')
          }}
          onDone={(composed) => {
            setResult(composed)
            go('qr')
          }}
        />
      )}
      {screen === 'preview' && (
        <Preview
          photo={photo}
          bgRef={bgRef}
          invitado={invitado}
          personaje={personaje}
          onRetry={() => {
            setPhoto(null)
            go('capture')
          }}
          onSave={(composed) => {
            setResult(composed)
            go('qr')
          }}
        />
      )}
      {screen === 'qr' && (
        <QRScreen
          imageDataUrl={result}
          invitado={invitado}
          isBabyShower={isBabyShower}
          onDiploma={() => go('diploma')}
          onDone={() => go('farewell')}
        />
      )}
      {screen === 'diploma' && (
        <DiplomaScreen invitado={invitado} personaje={personaje} prediction={prediction} score={gameScore} onDone={() => go('farewell')} />
      )}
      {screen === 'farewell' && (
        <VideoScreen
          src={CONFIG.videos.despedida}
          skipLabel="Terminar"
          finale
          onDone={reset}
        />
      )}
    </div>
  )
}

function PredictionScreen({ onDone }) {
  const [value, setValue] = useState({ guest_name: '', parecido: '', peso: '', fecha: '' })
  const [touched, setTouched] = useState(false)
  const submissionTokenRef = useRef('')
  if (!submissionTokenRef.current) submissionTokenRef.current = createPredictionSubmissionToken()

  const choose = (key, option) => setValue((current) => ({ ...current, [key]: option }))
  const submit = (event) => {
    event.preventDefault()
    setTouched(true)
    if (validPrediction(value)) onDone({
      ...value,
      guest_name: value.guest_name.trim(),
      submission_token: submissionTokenRef.current,
    })
  }

  // Las tres preguntas se muestran juntas y no una por pantalla: en una fiesta
  // hay cola detras del pedestal y cada pantalla extra son dos toques mas y
  // varios segundos por invitado. Lo que si cambia es como se ven: fichas
  // grandes que se reconocen de pie y a un metro.
  const question = (key, title, kicker, orden) => (
    <fieldset className="prediction-question" style={{ '--orden': orden }}>
      <legend>
        <span className="prediction-flag">{kicker}</span>
        {title}
      </legend>
      <div className="prediction-options">
        {PREDICTION_OPTIONS[key].map((option) => {
          const elegida = value[key] === option.value
          return (
            <button
              key={option.value}
              type="button"
              className={elegida ? 'ficha is-selected' : 'ficha'}
              aria-pressed={elegida}
              onClick={() => choose(key, option.value)}
            >
              <b className="ficha__valor">{option.short || option.label}</b>
              <small className="ficha__label">{option.label}</small>
              <span className="ficha__sello" aria-hidden="true" />
            </button>
          )
        })}
      </div>
    </fieldset>
  )

  // Guirnalda de progreso. Tres banderines que se encienden al responder: en un
  // baby shower dice "te faltan dos" mucho mejor que una barra de porcentaje,
  // y ademas es el unico idioma visual que la cabina ya tiene.
  const respondidas = ['parecido', 'peso', 'fecha'].filter((key) => value[key]).length

  return (
    <section className="screen prediction-screen" style={{ backgroundImage: `url(${CONFIG.images.fondo})` }}>
      <div className="prediction-veil" />
      <div className="prediction-motas" aria-hidden="true">
        {Array.from({ length: 14 }, (unused, i) => <i key={i} style={{ '--i': i }} />)}
      </div>

      <form className="prediction-panel" onSubmit={submit}>
        <div className="prediction-guirnalda" aria-hidden="true">
          {[0, 1, 2].map((i) => (
            <span key={i} className={i < respondidas ? 'is-on' : ''} style={{ '--i': i }} />
          ))}
        </div>

        <p className="prediction-eyebrow">Una apuesta para recordar</p>
        <h1>¿Cómo imaginas al bebé?</h1>
        <p className="prediction-lead">Tres toques y listo. Tu apuesta va en tu foto y queda en el tablero de los papás.</p>

        <label className="prediction-name">
          <span>Tu nombre</span>
          <input
            value={value.guest_name}
            onChange={(event) => setValue((current) => ({ ...current, guest_name: event.target.value }))}
            maxLength={80}
            autoComplete="name"
            placeholder="Ej. Camila"
          />
        </label>

        {question('parecido', '¿A quién se parecerá?', '01', 1)}
        {question('peso', '¿Cuánto pesará?', '02', 2)}
        {question('fecha', '¿Cuándo llegará?', '03', 3)}

        {touched && !validPrediction(value) && (
          <p className="prediction-error" role="alert">Falta tu nombre o alguna respuesta.</p>
        )}
        <button className="cta prediction-submit" type="submit">Sellar mi apuesta</button>
      </form>
    </section>
  )
}

function PredictionSave({ prediction, score, onDone }) {
  const [attempt, setAttempt] = useState(0)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    let alive = true
    setError('')
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      setError('No hay conexión. Revisa la red del quiosco y vuelve a intentar.')
      return () => controller.abort()
    }
    fetch(CONFIG.predictionEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      cache: 'no-store',
      signal: controller.signal,
      body: JSON.stringify({ ...prediction, puntaje_juego: score, party: PARTY_SLUG }),
    })
      .then(async (response) => {
        const data = await response.json().catch(() => null)
        if (!response.ok || !data?.ok) throw new Error(data?.error || 'save_failed')
      })
      .then(() => { if (alive) onDone() })
      .catch((saveError) => {
        if (!alive || saveError?.name === 'AbortError') return
        setError(saveError?.message === 'rate_limited'
          ? 'Se hicieron muchos intentos seguidos. Espera un momento y vuelve a probar.'
          : 'No pudimos guardar la predicción. La foto no comenzará hasta que quede segura.')
      })
    return () => { alive = false; controller.abort() }
  }, [attempt, onDone, prediction, score])

  const etiquetas = predictionLabels(prediction)

  return (
    <section className="screen prediction-saving">
      <div className="prediction-saving__aura" aria-hidden="true" />
      {error ? (
        <div className="prediction-saving__panel is-error" role="alert">
          <p className="prediction-eyebrow">Tu apuesta sigue aquí</p>
          <h1>No se pudo guardar todavía</h1>
          <p>{error}</p>
          <button className="cta" onClick={() => setAttempt((value) => value + 1)}>Reintentar guardado</button>
        </div>
      ) : (
        <div className="prediction-saving__panel">
          <p className="prediction-eyebrow">Predicción lista</p>
          <h1>Sellando tu apuesta…</h1>

          {/* La espera es un POST y puede durar 200 ms o tres segundos. En vez
              de un spinner que no dice nada, se le muestra al invitado LO QUE
              acaba de apostar: si la red se demora, mira sus tres respuestas
              en vez de mirar puntitos. Y si vuelve al instante, la tarjeta ya
              estaba ahi y no alcanza a verse un parpadeo. */}
          <div className="boleto" aria-hidden="true">
            <p className="boleto__nombre">{prediction.guest_name}</p>
            <ul className="boleto__lineas">
              <li><span>Se parecerá</span><b>{etiquetas.parecido}</b></li>
              <li><span>Pesará</span><b>{etiquetas.peso}</b></li>
              <li><span>Llegará</span><b>{etiquetas.fecha}</b></li>
            </ul>
            <span className="boleto__lacre" />
          </div>

          <p className="sr-only">{predictionSummary(prediction)}</p>
          <div className="prediction-saving__dots" aria-label="Guardando"><i /><i /><i /></div>
        </div>
      )}
    </section>
  )
}

function composePredictionImage(bgImg, photoImg, prediction, score) {
  const W = bgImg?.naturalWidth || 1080
  const H = bgImg?.naturalHeight || 1920
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')
  if (bgImg) ctx.drawImage(bgImg, 0, 0, W, H)
  else { ctx.fillStyle = cssVar('--bg-light1', '#fff3f7'); ctx.fillRect(0, 0, W, H) }

  const geometry = getSquarePhotoGeometry(CONFIG.frameBox, W, H)
  ctx.save()
  roundedSquarePath(ctx, geometry.photoLeft, geometry.photoTop, geometry.photoSide, W * 0.008)
  ctx.clip()
  const cropSide = Math.min(photoImg.width, photoImg.height)
  ctx.drawImage(
    photoImg,
    (photoImg.width - cropSide) / 2,
    (photoImg.height - cropSide) / 2,
    cropSide,
    cropSide,
    geometry.photoLeft,
    geometry.photoTop,
    geometry.photoSide,
    geometry.photoSide,
  )
  ctx.restore()
  roundedSquarePath(ctx, geometry.photoLeft, geometry.photoTop, geometry.photoSide, W * 0.008)
  ctx.lineWidth = Math.max(3, W * 0.006)
  ctx.strokeStyle = 'rgba(255,255,255,.92)'
  ctx.stroke()
  if (THEME_LABEL) drawThemeRibbon(ctx, geometry.cx, geometry.top, geometry.side, W)

  const labels = predictionLabels(prediction)
  const panelX = W * 0.075
  const panelY = H * 0.69
  const panelW = W * 0.85
  const panelH = H * 0.22
  ctx.save()
  ctx.fillStyle = 'rgba(255,255,255,.91)'
  ctx.shadowColor = 'rgba(24,12,47,.28)'
  ctx.shadowBlur = W * 0.035
  roundRectPath(ctx, panelX, panelY, panelW, panelH, W * 0.045)
  ctx.fill()
  ctx.restore()
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillStyle = cssVar('--dark1', '#38244f')
  ctx.font = `800 ${Math.round(W * 0.052)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText(`La predicción de ${prediction.guest_name}`, W / 2, panelY + panelH * 0.18)
  ctx.font = `700 ${Math.round(W * 0.034)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText(`Se parecerá: ${labels.parecido}`, W / 2, panelY + panelH * 0.40)
  ctx.fillText(`Peso: ${labels.peso}`, W / 2, panelY + panelH * 0.58)
  ctx.fillText(`Llegará: ${labels.fecha}`, W / 2, panelY + panelH * 0.76)
  ctx.fillStyle = cssVar('--pink', '#8c5de8')
  ctx.font = `800 ${Math.round(W * 0.037)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText(`${Number.isFinite(score) ? score : 0} puntos en Atrapa los chupetes`, W / 2, panelY + panelH * 0.91)
  drawBrandWatermark(ctx, W, H)
  return canvas.toDataURL('image/png')
}

function PredictionReveal({ photo, bgRef, prediction, score, onRetry, onDone }) {
  const [composed, setComposed] = useState(null)
  const [failed, setFailed] = useState(false)
  const confettiRef = useRef(null)

  useEffect(() => {
    if (!photo) return undefined
    let alive = true
    const image = new Image()
    image.onload = async () => {
      await Promise.all([ensureCanvasFonts(), preloadBrandLogo()])
      if (!alive) return
      try {
        setComposed(composePredictionImage(bgRef.current, image, prediction, score))
        burstConfetti(confettiRef.current, { duration: 2300, count: 150 })
      } catch {
        setFailed(true)
      }
    }
    image.onerror = () => setFailed(true)
    image.src = photo
    return () => { alive = false }
  }, [photo, bgRef, prediction, score])

  const saveAndContinue = () => {
    if (!composed) return
    const link = document.createElement('a')
    link.href = composed
    link.download = `prediccion-${prediction.guest_name}-${Date.now()}.png`
    document.body.appendChild(link)
    link.click()
    link.remove()
    onDone(composed)
  }

  return (
    <section className={composed ? 'screen prediction-reveal is-listo' : 'screen prediction-reveal'}>
      <div className="prediction-reveal__headline">
        <p className="prediction-eyebrow">Así imaginas el gran día</p>
        <h1>¡Predicción revelada!</h1>
      </div>

      {/* La foto compuesta llegaba y aparecia, sin mas. Es el momento de mayor
          pago del recorrido —el invitado lleva un minuto esperando verse— y se
          resolvia como cargar una imagen. Ahora entra girando desde el canto,
          como una foto que alguien da vuelta sobre la mesa, y un destello la
          recorre una sola vez al asentarse. Una vez: repetirlo lo convierte en
          un banner publicitario. */}
      <div className="revelado">
        {composed ? (
          <>
            <img src={composed} alt={`Predicción de ${prediction.guest_name}`} />
            <span className="revelado__brillo" aria-hidden="true" />
          </>
        ) : (
          <div className="revelado__espera">
            <span className="revelado__marco" aria-hidden="true" />
            <p>{failed ? 'No pudimos preparar la imagen.' : 'Revelando tu predicción…'}</p>
          </div>
        )}
      </div>

      <div className="prediction-reveal__actions">
        <button className="cta ghost" onClick={onRetry}>Repetir foto</button>
        <button className="cta" disabled={!composed} onClick={saveAndContinue}>Guardar y ver mi QR</button>
      </div>
      <canvas ref={confettiRef} className="confetti-canvas" />
    </section>
  )
}

/* ============================================================
   1-B) LISTA INVITADOS — muestra quiénes vinieron + bienvenida
   ============================================================ */
function ListaInvitados({ invitados, onStart }) {
  const [selected, setSelected] = useState(null)
  const [welcome, setWelcome] = useState(null)
  const [readyToSpin, setReadyToSpin] = useState(false)
  const advanceTimer = useRef(null)

  useEffect(() => () => clearTimeout(advanceTimer.current), [])

  // Red de seguridad: normalmente avanza `onEnded` del video. Este timer solo
  // cubre el caso de un video que ni termina ni falla. Antes era un valor fijo
  // de 5.2s, lo que CORTABA cualquier bienvenida más larga (la intro inmersiva
  // de Reino de Hielo dura 14s y se veía apenas un tercio). Ahora se reajusta
  // con la duración real en cuanto el navegador lee los metadatos.
  const scheduleAdvance = (ms) => {
    clearTimeout(advanceTimer.current)
    advanceTimer.current = setTimeout(() => {
      setWelcome(null)
      setReadyToSpin(true)
    }, ms)
  }

  // Nombre → video de bienvenida → al terminar, pantalla de "toca para girar"
  // (el invitado arranca la ruleta con su propio toque).
  const pick = (name, g) => {
    // Se alterna por selección: video de pista, video clásico, y así
    // sucesivamente. El segundo solo se ofrece cuando el asset existe.
    const useAlternateVideo = Boolean(WELCOME_VIDEO_ALT) && takeWelcomeVideoTurn() % 2 === 0
    setSelected(name)
    setWelcome({
      name,
      g,
      src: useAlternateVideo ? WELCOME_VIDEO_ALT : WELCOME_VIDEO_PRIMARY,
      isAlternateVideo: useAlternateVideo,
    })
    // Margen amplio hasta conocer la duración; se ajusta en onLoadedMetadata.
    scheduleAdvance(REDUCE_MOTION ? 1200 : 20000)
  }

  const ninas = invitados.filter((i) => i.g === 'f')
  const varones = invitados.filter((i) => i.g === 'm')

  const renderGroup = (titulo, arr) =>
    arr.length > 0 && (
      <>
        <p className="invitados-group">{titulo}</p>
        {arr.map(({ name, g }) => (
          <button
            key={name}
            className={`invitado-item ${selected === name ? 'checked' : ''}`}
            onClick={() => pick(name, g)}
          >
            <span className="check">{selected === name ? '✓' : '○'}</span>
            <span>{name}</span>
          </button>
        ))}
      </>
    )

  const skipWelcome = () => {
    clearTimeout(advanceTimer.current)
    setWelcome(null)
    setReadyToSpin(true)
  }

  return (
    <section className="screen invitados-list">
      {welcome && (
        <div className={`welcome-popup${welcome.isAlternateVideo ? ' welcome-popup--personalized-video' : ''}`} onClick={skipWelcome}>
          {welcome.isAlternateVideo && <div className="welcome-video-label-mask" aria-hidden="true" />}
          <h2>{welcome.g === 'f' ? '¡Bienvenida!' : '¡Bienvenido!'}</h2>
          <p className="welcome-name">{welcome.name}</p>
          <div className="welcome-car3d" aria-hidden="true">
            {REDUCE_MOTION ? (
              <span className="welcome-car3d-emoji">🏎️</span>
            ) : (
              <video
                className="welcome-car3d-video"
                src={welcome.src}
                autoPlay
                muted
                playsInline
                onLoadedMetadata={(e) => {
                  // Ya se conoce cuánto dura: el watchdog se ajusta para no
                  // cortar bienvenidas largas ni esperar de más en las cortas.
                  const dur = Number(e.currentTarget?.duration)
                  if (Number.isFinite(dur) && dur > 0) {
                    scheduleAdvance(Math.min(30000, dur * 1000 + 600))
                  }
                }}
                onEnded={skipWelcome}
                onError={() => {
                  // Si el video alternativo faltara al desplegar, el kiosco
                  // conserva el saludo original en vez de dejar una pantalla vacía.
                  if (welcome.isAlternateVideo) {
                    setWelcome((current) => current && ({
                      ...current,
                      src: BASE + 'welcome-car.mp4',
                      isAlternateVideo: false,
                    }))
                  } else {
                    skipWelcome()
                  }
                }}
              />
            )}
          </div>
        </div>
      )}
      {readyToSpin && (
        <div className="spin-ready-popup">
          <p className="spin-ready-emoji pulse">🎡</p>
          <h2>¡{selected}, es tu turno!</h2>
          <button className="cta pulse" onClick={() => onStart(selected)}>
            Toca para girar la ruleta 🎉
          </button>
        </div>
      )}
      <div className="invitados-header">
        <h2>¿Quién se toma la foto?</h2>
        <p>Elige tu nombre 🎉</p>
      </div>
      <div className="invitados-scroll">
        {renderGroup('Niñas', ninas)}
        {renderGroup('Varones', varones)}
      </div>
      {!welcome && !readyToSpin && selected && (
        <div className="selected-hint">
          <span className="selected-hint-emoji">🏎️</span>
          <span>¡{selected} es tu turno!</span>
        </div>
      )}
      {!welcome && !readyToSpin && !selected && (
        <div className="selected-hint">
          <span className="selected-hint-emoji">👆</span>
          <span>Elige tu nombre para empezar</span>
        </div>
      )}
    </section>
  )
}

/* ============================================================
   GESTIÓN DE INVITADOS — agregar / eliminar (overlay)
   ============================================================ */
function GestionInvitados({ list, onSave, onClose }) {
  const [items, setItems] = useState(list)
  const [nombre, setNombre] = useState('')
  const [genero, setGenero] = useState('f')

  const add = () => {
    const n = nombre.trim()
    if (!n) return
    if (items.some((i) => i.name.toLowerCase() === n.toLowerCase())) {
      setNombre('')
      return
    }
    setItems((arr) => [...arr, { name: n, g: genero }])
    setNombre('')
  }
  const remove = (name) => setItems((arr) => arr.filter((i) => i.name !== name))

  const guardar = () => {
    onSave(items)
    onClose()
  }

  const ninas = items.filter((i) => i.g === 'f')
  const varones = items.filter((i) => i.g === 'm')

  const grupo = (titulo, arr) => (
    <>
      <p className="gestion-group">{titulo} ({arr.length})</p>
      {arr.length === 0 && <p className="gestion-empty">— sin invitados —</p>}
      {arr.map(({ name }) => (
        <div key={name} className="gestion-item">
          <span>{name}</span>
          <button className="gestion-del" onClick={() => remove(name)} aria-label={`Eliminar ${name}`}>
            ✕
          </button>
        </div>
      ))}
    </>
  )

  return (
    <div className="gestion">
      <div className="gestion-head">
        <h2>Gestión de invitados</h2>
        <p>{items.length} en total</p>
      </div>

      <div className="gestion-add">
        <input
          className="gestion-input"
          type="text"
          placeholder="Nombre del invitado"
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && add()}
        />
        <div className="gestion-gen">
          <button
            className={`gen-btn ${genero === 'f' ? 'on' : ''}`}
            onClick={() => setGenero('f')}
          >
            👧 Niña
          </button>
          <button
            className={`gen-btn ${genero === 'm' ? 'on' : ''}`}
            onClick={() => setGenero('m')}
          >
            👦 Varón
          </button>
        </div>
        <button className="cta gestion-addbtn" onClick={add}>
          ＋ Agregar
        </button>
      </div>

      <div className="gestion-scroll">
        {grupo('Niñas', ninas)}
        {grupo('Varones', varones)}
      </div>

      <div className="gestion-foot">
        <button className="cta ghost" onClick={onClose}>
          Cancelar
        </button>
        <button className="cta" onClick={guardar}>
          💾 Guardar
        </button>
      </div>
    </div>
  )
}

/* ============================================================
   1-C) SPINNER — ruleta gira personajes Disney
   ============================================================ */
function Spinner({ onDone }) {
  const [winner, setWinner] = useState(null)
  const rotRef = useRef(null)
  const n = PERSONAJES.length
  const angle = 360 / n
  // se elige el GANADOR una sola vez; la rueda gira para dejarlo bajo la flecha
  const winIdx = useRef(selectSpinnerWinnerIndex(PERSONAJES, {
    themeSlug: THEME_SLUG,
    search: location.search,
    hostname: location.hostname,
  }))

  useEffect(() => {
    const win = winIdx.current
    // slot win está a (win*angle) en sentido horario desde arriba.
    // para dejarlo arriba (bajo la flecha): girar 5 vueltas - win*angle
    const finalR = 360 * 5 - win * angle
    const dur = REDUCE_MOTION ? 600 : 3600
    const start = performance.now()
    const easeOut = (t) => 1 - Math.pow(1 - t, 3)
    let raf
    const tick = (now) => {
      const t = Math.min(1, (now - start) / dur)
      const r = finalR * easeOut(t)
      if (rotRef.current) rotRef.current.style.setProperty('--spin', r + 'deg')
      if (t < 1) {
        raf = requestAnimationFrame(tick)
      } else {
        setWinner(PERSONAJES[win])
        raf = setTimeout(() => onDone(PERSONAJES[win]), REDUCE_MOTION ? 700 : 1600)
      }
    }
    raf = requestAnimationFrame(tick)
    return () => {
      cancelAnimationFrame(raf)
      clearTimeout(raf)
    }
  }, [onDone])

  return (
    <section className={`screen spinner${CONFIG.images.roulette ? ' has-themed-background' : ''}`}>
      <div className="spinner-wrapper">
        <div className="spinner-pointer">▼</div>
        <div className="spinner-rotator" ref={rotRef} style={{ '--spin': '0deg' }}>
          <div className="spinner-wheel-v2" />
          {PERSONAJES.map((p, i) => (
            <div
              key={i}
              className="spinner-slot-char"
              style={{
                // Radio proporcional a la rueda (--slot-radius), no px fijos.
                transform: `rotate(${i * angle}deg) translateY(calc(var(--slot-radius) * -1)) rotate(-${i * angle}deg)`,
              }}
            >
              <div className="slot-inner">
                <span className="char-emoji">{p.emoji}</span>
                <span className="char-name">{p.name}</span>
              </div>
            </div>
          ))}
        </div>
        {winner && (
          <div className="spinner-winner">
            <p className="winner-emoji" style={{ fontSize: '60px' }}>{winner.emoji}</p>
            <p className="winner-text">¡Ganador!</p>
            <h2 className="winner-name">{winner.name}</h2>
          </div>
        )}
      </div>
      {(THEME_FLOW.photoSessionTeaserVideo || THEME_FLOW.photoSessionTeaser) && THEME_FLOW.teaserLabel && (
        <div className="spinner-artist-teaser">
          {THEME_FLOW.photoSessionTeaserVideo ? (
            // Video en loop mudo: los artistas bailando mientras gira la ruleta.
            // Si el MP4 faltara en un deploy parcial, cae al poster (imagen fija).
            <video
              src={THEME_FLOW.photoSessionTeaserVideo}
              poster={THEME_FLOW.photoSessionTeaser || undefined}
              autoPlay
              loop
              muted
              playsInline
              preload="auto"
              aria-label={`${THEME_FLOW.teaserLabel} bailando`}
            />
          ) : (
            <img src={THEME_FLOW.photoSessionTeaser} alt={THEME_FLOW.teaserLabel} />
          )}
          <span className="spinner-artist-teaser__ribbon">
            {THEME_FLOW.teaserLabel}
          </span>
        </div>
      )}
    </section>
  )
}

/* ============================================================
   Video personalizado — saludo del personaje al invitado
   ============================================================ */
function PhotoSessionVideo({ invitado, onDone }) {
  const videoRef = useRef(null)
  const doneRef = useRef(false)
  const [failed, setFailed] = useState(!THEME_FLOW.photoSessionVideo)
  const [needsTap, setNeedsTap] = useState(false)

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  const startPlayback = useCallback(() => {
    const video = videoRef.current
    if (!video) return
    const promise = video.play()
    if (promise && typeof promise.catch === 'function') {
      promise
        .then(() => setNeedsTap(false))
        .catch(() => setNeedsTap(true))
    }
  }, [])

  useEffect(() => {
    if (failed) return
    startPlayback()
    const watchdog = setTimeout(() => {
      const video = videoRef.current
      if (video && video.readyState < 2 && !doneRef.current) setFailed(true)
    }, 1800)
    return () => {
      clearTimeout(watchdog)
      videoRef.current?.pause()
    }
  }, [failed, startPlayback])

  // Si el MP4 faltara durante un despliegue parcial, el póster conserva el
  // contexto y el kiosco continúa sin quedar bloqueado.
  useEffect(() => {
    if (!failed) return
    const timer = setTimeout(finish, REDUCE_MOTION ? 1000 : 2800)
    return () => clearTimeout(timer)
  }, [failed, finish])

  return (
    <section className="screen video-screen photo-session-screen">
      {!failed ? (
        <video
          ref={videoRef}
          className="video"
          src={THEME_FLOW.photoSessionVideo}
          poster={THEME_FLOW.photoSessionPoster || undefined}
          autoPlay
          playsInline
          preload="auto"
          aria-label="Invitación animada para entrar a la sesión de fotos"
          onEnded={finish}
          onError={() => setFailed(true)}
        />
      ) : THEME_FLOW.photoSessionPoster ? (
        <img
          className="photo-session-poster"
          src={THEME_FLOW.photoSessionPoster}
          alt="Alfombra de entrada a la sesión de fotos"
        />
      ) : (
        <div className="video-fallback"><div className="big-emoji">📸✨</div></div>
      )}

      <div className="photo-session-copy" aria-live="polite">
        <span className="photo-session-kicker">✨ Pase de artista ✨</span>
        <h2>¡{invitado || 'Artista'}, las cámaras te esperan!</h2>
        <p>Tus anfitrionas te acompañan a la sesión.</p>
      </div>

      {needsTap && !failed && (
        <button className="photo-session-play" onClick={startPlayback}>
          🔊 Toca para escuchar
        </button>
      )}

      <button className="skip" onClick={finish}>
        Continuar ⏭
      </button>
    </section>
  )
}

function VideoPersonaje({ personaje, invitado, onDone }) {
  const vRef = useRef(null)
  // Video de saludo por temática (themes/<tema>/saludo-<personaje>.mp4)
  const videoSrc = CHAR_VIDEO[personaje.name] || null
  const imgSrc = CHAR_IMG[personaje.name] || null
  // sin ruta de video → directo a la imagen (sin esperar el watchdog)
  const [failed, setFailed] = useState(!videoSrc)
  const [needsTap, setNeedsTap] = useState(false)
  const doneRef = useRef(false)

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  // Igual que PhotoSessionVideo: en una tablet real sin gesto reciente, Chrome
  // rechaza el autoplay CON sonido (play() se resuelve rejected, no dispara
  // onError) y el video queda congelado en silencio sin avisar. Se detecta el
  // rechazo y se ofrece un botón para reintentar con un toque directo.
  const startPlayback = useCallback(() => {
    const v = vRef.current
    if (!v) return
    const promise = v.play()
    if (promise && typeof promise.catch === 'function') {
      promise.then(() => setNeedsTap(false)).catch(() => setNeedsTap(true))
    }
  }, [])

  useEffect(() => {
    if (failed) return
    startPlayback()
    const t = setTimeout(() => {
      const v = vRef.current
      if (v && v.readyState < 2 && !doneRef.current) setFailed(true)
    }, 1200)
    return () => clearTimeout(t)
  }, [failed, startPlayback])

  // sin video: mostrar imagen 4s y avanzar
  useEffect(() => {
    if (!failed) return
    const t = setTimeout(finish, 4000)
    return () => clearTimeout(t)
  }, [failed, finish])

  return (
    <section className="screen video-screen">
      {!failed ? (
        <>
          <video
            ref={vRef}
            className="video"
            src={videoSrc}
            autoPlay
            playsInline
            onEnded={finish}
            onError={() => setFailed(true)}
          />
          <div className="photo-prep-overlay">
            <p className="photo-prep-name">¡{invitado}!</p>
            <p className="photo-prep-sub">Prepárate para la foto con:</p>
            <p className="photo-prep-char">{personaje.name}</p>
          </div>
          {needsTap && (
            <button className="photo-session-play" onClick={startPlayback}>
              🔊 Toca para escuchar
            </button>
          )}
        </>
      ) : (
        <div className="char-saludo">
          {imgSrc && <img className="char-saludo-img" src={imgSrc} alt={personaje.name} />}
          <div className="char-saludo-text">
            <h2 className="char-saludo-hola">¡Hola {invitado}!</h2>
            <p className="char-saludo-sub">{personaje.name} te da la bienvenida 🎉</p>
          </div>
        </div>
      )}
      <button className="skip" onClick={finish}>
        Continuar ⏭
      </button>
    </section>
  )
}

/* Presentación WOW previa a El Show 3D. El MP4 final es mudo para que la
   música del kiosco continúe; si falta o no carga, el watchdog avanza sin
   bloquear la fila de invitados. */
function VideoJuegoEstrella({ src, personaje, onDone }) {
  const videoRef = useRef(null)
  const doneRef = useRef(false)

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  useEffect(() => {
    const video = videoRef.current
    if (!video || !src) {
      finish()
      return undefined
    }
    const playback = video.play()
    if (playback && typeof playback.catch === 'function') playback.catch(finish)
    const watchdog = setTimeout(() => {
      if (video.readyState < 2) finish()
    }, 1800)
    return () => {
      clearTimeout(watchdog)
      video.pause()
    }
  }, [finish, src])

  return (
    <section className="screen star-game-video-screen">
      <video
        ref={videoRef}
        className="star-game-video"
        src={src}
        autoPlay
        muted
        playsInline
        preload="auto"
        aria-label={`Presentación del juego estrella con ${personaje?.name || 'el personaje'}`}
        onEnded={finish}
        onError={finish}
      />
      <div className="star-game-video-copy" aria-live="polite">
        <span>⭐ Experiencia Full ⭐</span>
        <h2>¡Te salió el juego estrella!</h2>
        <p>{personaje?.name} te espera en El Show 3D</p>
      </div>
      <button className="skip" onClick={finish}>Omitir ⏭</button>
    </section>
  )
}

/* ============================================================
   1) INTRO — gate de toque (desbloquea audio/autoplay)
   ============================================================ */
function Intro({ onStart }) {
  const start = () => {
    // desbloquea audio con un sonido silencioso dentro del gesto
    try {
      const a = new Audio()
      a.play().catch(() => {})
    } catch {}
    onStart()
  }
  return (
    <section
      className={`screen intro intro--${THEME_SLUG}`}
      style={{ backgroundImage: `url(${CONFIG.images.bienvenida})` }}
      onClick={start}
    >
      <div className="intro-veil" />
      <CumpleClickBrand className="intro-brand" inverse />
      <div className="intro-content">
        <h1 className="intro-title">
          {/* Un baby shower no es "la fiesta de Valentina": Valentina todavia
              no nacio. La bienvenida cambia de forma segun la modalidad. */}
          {esBabyShower()
            ? <>¡Bienvenidos al<br />baby shower de<br />{CONFIG.nombre}!</>
            : <>¡Bienvenidos a la<br />fiesta de<br />{CONFIG.nombre}!</>}
        </h1>
        <div className="intro-party-decoration" aria-hidden="true">
          <div className="intro-party-flags">
            <i /><i /><i /><i /><i />
          </div>
          <span>¡A celebrar!</span>
        </div>
        <p className="intro-cake-name" aria-label={`Cumpleaños de ${CONFIG.nombre}`}>
          {CONFIG.nombre}
        </p>
        <div className="intro-bottom">
          <button
            className="cta pulse"
            onClick={(event) => {
              event.stopPropagation()
              start()
            }}
          >
            🎉 Toca para entrar
          </button>
          <p className="hint">Te tomaremos una foto de recuerdo 📸</p>
        </div>
      </div>
    </section>
  )
}

/**
 * Como se nombra el evento en los textos que ve el invitado.
 *
 * "la fiesta de Valentina" no sirve para un baby shower: Valentina todavia no
 * nacio. Se resuelve una vez y no en cada pantalla porque lo usan tanto los
 * componentes como las funciones que pintan el recuerdito en canvas, que no
 * reciben props.
 */
// Funciones y no constantes: CONFIG se llena con la respuesta de api.php, y a
// la hora en que el modulo se evalua todavia vale null. Resueltas al importar,
// el kiosco entero moria con "Cannot read properties of null".
const esBabyShower = () => CONFIG?.eventType === 'baby_shower'
// Dos variantes porque el articulo cambia con la preposicion: "por venir AL
// baby shower" pero "EN EL baby shower". Con una sola quedaba "en al baby
// shower de Valentina" impreso en el recuerdito que el invitado se lleva.
const eventoFraseA = () => (esBabyShower()
  ? `al baby shower de ${CONFIG.nombre}`
  : `a la fiesta de ${CONFIG.nombre}`)
const eventoFraseEn = () => (esBabyShower()
  ? `el baby shower de ${CONFIG.nombre}`
  : `la fiesta de ${CONFIG.nombre}`)

function CumpleClickBrand({ className = '', inverse = false }) {
  return (
    <div className={`brand-lockup ${inverse ? 'brand-lockup--inverse' : ''} ${className}`.trim()}>
      <img src={BRAND_LOGO_SRC} alt="CumpleClick" />
      <span className="brand-lockup__copy">
        <strong>CumpleClick</strong>
        <small>by AutomatizaTech</small>
      </span>
    </div>
  )
}

/* ============================================================
   2/6) VIDEO (saludo / despedida) — resiliente si falta el archivo
   ============================================================ */
function VideoScreen({ src, onDone, skipLabel, finale }) {
  const vRef = useRef(null)
  const [failed, setFailed] = useState(false)
  const doneRef = useRef(false)
  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  useEffect(() => {
    const v = vRef.current
    if (!v) return
    v.play().catch(() => {})
    // red de seguridad: si el video no carga en 1.2s, no bloquear el flujo
    const t = setTimeout(() => {
      if (v.readyState < 2 && !doneRef.current) setFailed(true)
    }, 1200)
    return () => clearTimeout(t)
  }, [])

  // si el video falta, mostrar tarjeta animada y auto-avanzar
  useEffect(() => {
    if (!failed) return
    const t = setTimeout(finish, 3200)
    return () => clearTimeout(t)
  }, [failed, finish])

  return (
    <section className={`screen video-screen ${finale ? 'finale' : ''}`}>
      {!failed ? (
        <video
          ref={vRef}
          className="video"
          src={src}
          autoPlay
          playsInline
          onEnded={finish}
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="video-fallback">
          <div className="big-emoji">{finale ? '👋✨' : '🏎️🎈'}</div>
          <h2>
            {finale
              ? `¡Gracias por venir ${eventoFraseA()}!`
              : `¡Hola! Bienvenido ${eventoFraseA()}`}
          </h2>
        </div>
      )}
      <button className="skip" onClick={finish}>
        {skipLabel} ⏭
      </button>
    </section>
  )
}

/* ============================================================
   2-B) JUEGO "ATRAPA LOS COPOS" — opcional por temática
   Se intercala entre el saludo del personaje y la cámara.

   Decisiones:
   - La caída usa animación CSS, no requestAnimationFrame: son ~10 copos vivos
     y mover estado de React 60 veces por segundo recalentaría el árbol en una
     tablet modesta. El toque solo cambia estado al atrapar.
   - SIEMPRE hay botón de saltar visible: la fila de invitados no se detiene
     porque a un niño no le interese jugar.
   - Con prefers-reduced-motion no se anima nada: se ofrece saltar directo.
   ============================================================ */
const COPO_EMOJIS = ['❄️', '❅', '❆', '✻']
// Copo "trampa": no es de la temática, no suma y da error al tocarlo — mismo
// símbolo en las 10 temáticas (Luis, 2026-07-28), para que un niño aprenda a
// distinguirlo aunque cambie el set de emojis correctos.
const COPO_TRAMPA_EMOJI = '💣'
const COPO_TRAMPA_PROB = 0.18

/* Recortes de Carreras que conservan el encuadre 9:16 del retrato en vez de
   ajustarse a la silueta. Medido 2026-08-03 sobre el canal alfa: el auto ocupa
   apenas el 28,6% del alto en Rayo y el 37,8% en Mate, el resto es aire
   transparente — sin recortarlo salen diminutos sobre el pedestal de El Show.
   Los otros cuatro (`sally`, `cruz`, `el-rey`, `luigi`) vienen del removebg
   original, ya ajustados al 96-98%, y por eso NO entran acá: recortarlos otra
   vez no cambiaría nada. Si algún día se regeneran estos dos ya ajustados,
   sacarlos de esta lista y `charTrim` deja de hacer falta. */
const CARRERAS_CUT_CON_AIRE = new Set(['Rayo McQueen', 'Mate'])

/**
 * Despachador: elige el juego según el personaje que salió en la ruleta.
 * Un personaje puede traer el suyo propio (el muñeco de nieve se arma) y si no,
 * cae al juego de la temática. Si no hay ninguno, avanza sin estorbar.
 *
 * Un personaje puede traer VARIOS juegos (array en themes.json): el primero
 * siempre se juega, y por cada juego siguiente se OFRECE como bonus opcional
 * con botón de Sí/Omitir — nunca se sortea al azar (Luis, 2026-07-25).
 */
function Juego({ invitado, personaje, onDone }) {
  const lista = THEME_FLOW.gamesFor?.(personaje?.name) || []
  const [idx, setIdx] = useState(0)
  const [ofreciendo, setOfreciendo] = useState(false)
  const [videoEstrellaVisto, setVideoEstrellaVisto] = useState(false)

  useEffect(() => {
    if (!lista.length) onDone()
  }, [lista.length, onDone])

  // Narración una sola vez, justo al entrar (antes del PRIMER juego, no del
  // bonus): "Antes de tomarte una foto con <Personaje>, juguemos un rato..."
  useEffect(() => {
    if (!lista.length) return
    const src = personaje?.name && CHAR_JUEGO_AUDIO[personaje.name]
    if (src) playSound(src)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [personaje?.name])

  if (!lista.length) return null
  const config = lista[idx]
  const siguiente = lista[idx + 1] || null

  const alTerminarJuego = () => {
    if (siguiente) setOfreciendo(true)
    else onDone()
  }

  if (ofreciendo && siguiente) {
    /* Tres salidas, siempre las mismas en TODAS las temáticas (esto es código
       compartido: lo único que cambia por temática es qué juegos hay en la
       cadena, nunca las opciones):

         1. Jugar el que se ofrece.
         2. Saltar SOLO ese y ver el siguiente — se avanza el índice pero se
            sigue ofreciendo. Solo aparece si de verdad queda otro después,
            porque si no `siguiente` se vuelve null y la pantalla caería a
            JUGAR ese juego en vez de saltarlo.
         3. Irse a la foto, descartando todo lo que falte.

       La 3 existe para que la fila de invitados nunca se trabe porque a un
       niño no le interesó jugar. */
    const faltan = lista.length - (idx + 1)
    return (
      <div className="screen juego juego-oferta">
        <div className="juego-panel">
          <p className="juego-title">¿Jugamos otro?</p>
          {siguiente.label && <p className="juego-sub">{siguiente.label}</p>}
          <div className="juego-oferta__botones">
            <button
              className="juego-oferta__btn juego-oferta__btn--si"
              onClick={() => { setIdx(idx + 1); setOfreciendo(false) }}
            >
              Sí, jugar
            </button>
            {faltan > 1 && (
              <button
                className="juego-oferta__btn juego-oferta__btn--otro"
                onClick={() => setIdx(idx + 1)}
              >
                Ese no, muéstrame otro 🔀
              </button>
            )}
            <button className="juego-oferta__btn juego-oferta__btn--omitir" onClick={onDone}>
              Ir a mi foto 📸
            </button>
          </div>
        </div>
      </div>
    )
  }

  if (config.kind === 'armar-muneco') {
    return <JuegoMuneco config={config} invitado={invitado} personaje={personaje} onDone={alTerminarJuego} />
  }
  if (config.kind === 'fichas') {
    return <JuegoFichas config={config} invitado={invitado} personaje={personaje} onDone={alTerminarJuego} />
  }
  if (config.kind === 'ritmo') {
    return <JuegoRitmo config={config} invitado={invitado} personaje={personaje} onDone={alTerminarJuego} />
  }
  if (config.kind === 'escudo') {
    return <JuegoEscudo config={config} invitado={invitado} personaje={personaje} onDone={alTerminarJuego} />
  }
  // 'concierto3d' (El Show) y 'mundo3d' (runner de carriles) comparten los
  // mismos assets del personaje: el atlas 2×2 sirve de recorrido de carriles
  // en uno y de poses de baile en el otro.
  if (config.kind === 'concierto3d') {
    const charName = personaje?.name
    const charSrc = (charName && (CHAR_PNG[charName] || CHAR_IMG[charName])) || null
    const esCarreras = THEME_SLUG === 'carreras'
    // Rayo usa su recorte aprobado, no el atlas de auto generico.
    const runnerAtlasSrc = esCarreras && charName === 'Rayo McQueen'
      ? null
      : ((charName && CHAR_RUN_ATLAS[charName]) || null)
    /* La pantalla LED del escenario mostraba la portada de la fiesta. En
       Carreras esa portada es la sala de cumpleaños (arco de globos, torta,
       regalos y los seis autos chiquitos), así que detrás del personaje se
       leía como una foto de otra fiesta colgada del escenario y la estrella
       quedaba compitiendo con ella (Luis, 2026-08-03). Se cambia por una recta
       de circuito propia de la pantalla: banderas a cuadros, tribuna y asfalto
       mojado, con el centro oscuro y vacío justamente para que el personaje
       recorte encima. Aplica a TODA la temática y no solo a Rayo: el asset no
       tiene personajes, así que sirve igual para los seis, y limitarlo a la
       estrella dejaba a los otros cinco con la sala de cumpleaños detrás. */
    const bannerSrc = esCarreras
      ? BASE + 'themes/carreras/fondo-pantalla-circuito.jpg'
      : CONFIG.images.bienvenida
    // Solo los recortes que traen aire de sobra necesitan reencuadre; ver
    // CARRERAS_CUT_CON_AIRE, que explica por qué son exactamente dos.
    const charTrim = esCarreras && CARRERAS_CUT_CON_AIRE.has(charName)
    if (!videoEstrellaVisto && THEME_FLOW.starVideoAppliesTo?.(charName)) {
      return (
        <VideoJuegoEstrella
          src={THEME_FLOW.starVideo}
          personaje={personaje}
          onDone={() => setVideoEstrellaVisto(true)}
        />
      )
    }
    return (
      <StageConcert3D
        config={config}
        invitado={invitado}
        personaje={personaje}
        charSrc={charSrc}
        runnerAtlasSrc={runnerAtlasSrc}
        bannerSrc={bannerSrc}
        bannerFallbackSrc={CONFIG.images.bienvenida}
        charTrim={charTrim}
        onDone={alTerminarJuego}
      />
    )
  }
  if (config.kind === 'mundo3d') {
    const charName = personaje?.name
    const charSrc = (charName && (CHAR_PNG[charName] || CHAR_IMG[charName])) || null
    // Rayo usa su recorte aprobado, no el atlas de auto generico.
    const runnerAtlasSrc = THEME_SLUG === 'carreras' && charName === 'Rayo McQueen'
      ? null
      : ((charName && CHAR_RUN_ATLAS[charName]) || null)
    return (
      <ThemeWorld3D
        config={config}
        invitado={invitado}
        personaje={personaje}
        charSrc={charSrc}
        runnerAtlasSrc={runnerAtlasSrc}
        onDone={alTerminarJuego}
      />
    )
  }
  return <JuegoCopos config={config} invitado={invitado} personaje={personaje} onDone={alTerminarJuego} />
}

/* ── Armar al muñeco de nieve — ARRASTRAR (rediseño 2026-07-25) ────────────
   Reemplaza el rompecabezas de fichas: ahora hay siluetas fijas mostrando
   dónde va cada pieza sobre un fondo de nieve, y piezas sueltas abajo que
   se arrastran hasta su silueta. El radio de "acierto" es generoso a
   propósito — un dedo de niño no suelta en el píxel exacto.

   Coordenadas en % del escenario (no en px): así el juego escala igual en
   tablet que en teléfono sin recalcular nada. Ojos y botones van
   pre-dibujados dentro de cabeza/panza (decoración estática, no son piezas)
   para no inflar el juego a más de 6 piezas.
   ───────────────────────────────────────────────────────────────────────── */
const MUNECO_PARTES = [
  { id: 'cabeza',    forma: 'cabeza', target: { x: 50, y: 18 } },
  { id: 'panza',     forma: 'panza',  target: { x: 50, y: 44 } },
  { id: 'base',      forma: 'base',   target: { x: 50, y: 77 } },
  { id: 'brazo-izq', forma: 'brazo',  target: { x: 21, y: 45 }, rot: -24 },
  { id: 'brazo-der', forma: 'brazo',  target: { x: 79, y: 45 }, rot: 24 },
  // Corrida a la derecha del centro: así la zanahoria sobresale del rostro en
  // vez de quedar encima de los ojos tapándole la cara.
  { id: 'nariz',     forma: 'nariz',  target: { x: 57, y: 20 } },
]

// Una sola función de forma para silueta, pieza suelta y pieza colocada:
// las tres son el mismo dibujo, solo cambia la clase que las envuelve.
function FormaMuneco({ forma }) {
  if (forma === 'brazo') return <span className="mp-brazo" />
  if (forma === 'nariz') return <span className="mp-nariz" />
  if (forma === 'cabeza') {
    return (
      <span className="mp-cabeza">
        {/* Los tres mechones, las cejas y el diente son lo que hace que se
            lea como Olaf y no como un muñeco de nieve cualquiera. */}
        <span className="mp-pelo mp-pelo--izq" />
        <span className="mp-pelo mp-pelo--centro" />
        <span className="mp-pelo mp-pelo--der" />
        <span className="mp-ceja mp-ceja--izq" />
        <span className="mp-ceja mp-ceja--der" />
        <span className="mp-ojo mp-ojo--izq" />
        <span className="mp-ojo mp-ojo--der" />
        <span className="mp-diente" />
      </span>
    )
  }
  return (
    <span className={`mp-cuerpo mp-cuerpo--${forma}`}>
      <span className="mp-boton" />
    </span>
  )
}

function JuegoMuneco({ config, invitado, personaje, onDone }) {
  const [colocadas, setColocadas] = useState([])
  const [arrastre, setArrastre] = useState(null) // { id, x, y } en px de pantalla
  const stageRef = useRef(null)
  const doneRef = useRef(false)
  const inicioRef = useRef(performance.now())
  const [esRecord, setEsRecord] = useState(false)
  const [record] = useState(() => textoRecord('armar-muneco', formatoSegundos))

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  const listo = colocadas.length === MUNECO_PARTES.length

  useEffect(() => {
    if (!listo || REDUCE_MOTION) return undefined
    // Igual que en fichas: sin puntaje, el récord es el mejor tiempo.
    setEsRecord(guardarRecord('armar-muneco', performance.now() - inicioRef.current, invitado, 'menor'))
    const t = setTimeout(finish, 3000)
    return () => clearTimeout(t)
  }, [listo, finish, invitado])

  const soltar = (id, clientX, clientY) => {
    const stage = stageRef.current
    const parte = MUNECO_PARTES.find((p) => p.id === id)
    if (!stage || !parte) return false
    const rect = stage.getBoundingClientRect()
    const targetX = rect.left + (parte.target.x / 100) * rect.width
    const targetY = rect.top + (parte.target.y / 100) * rect.height
    const dist = Math.hypot(clientX - targetX, clientY - targetY)
    // Radio de acierto generoso: 24% del ancho del escenario. Un dedo de
    // niño en tablet no suelta en el punto exacto.
    const radio = rect.width * 0.24
    if (dist <= radio) {
      setColocadas((prev) => (prev.includes(id) ? prev : [...prev, id]))
      return true
    }
    return false
  }

  const empezarArrastre = (id) => (e) => {
    if (colocadas.includes(id)) return
    e.preventDefault()
    const point = e.touches ? e.touches[0] : e
    setArrastre({ id, x: point.clientX, y: point.clientY })
  }

  useEffect(() => {
    if (!arrastre) return undefined
    const mover = (e) => {
      const point = e.touches ? e.touches[0] : e
      setArrastre((prev) => (prev ? { ...prev, x: point.clientX, y: point.clientY } : prev))
    }
    const terminar = (e) => {
      const point = e.changedTouches ? e.changedTouches[0] : e
      soltar(arrastre.id, point.clientX, point.clientY)
      setArrastre(null)
    }
    window.addEventListener('pointermove', mover)
    window.addEventListener('pointerup', terminar)
    window.addEventListener('touchmove', mover, { passive: false })
    window.addEventListener('touchend', terminar)
    return () => {
      window.removeEventListener('pointermove', mover)
      window.removeEventListener('pointerup', terminar)
      window.removeEventListener('touchmove', mover)
      window.removeEventListener('touchend', terminar)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [arrastre?.id])

  const faltantes = MUNECO_PARTES.filter((p) => !colocadas.includes(p.id))
  const arrastrandoParte = arrastre && MUNECO_PARTES.find((p) => p.id === arrastre.id)

  return (
    <section
      className="screen juego juego--muneco"
      style={config.image ? { backgroundImage: `url("${config.image}")` } : undefined}
    >
      <div className="juego-hud">
        <span className="juego-hud__item">⛄ {colocadas.length}/{MUNECO_PARTES.length}</span>
        {record && <span className="juego-hud__item juego-hud__record">🏆 {record}</span>}
      </div>

      {listo && esRecord && <p className="juego-record-nuevo">🏆 ¡NUEVO RÉCORD!</p>}
      <h2 className="juego-title">{listo ? '¡Lo armaste!' : config.label}</h2>
      <p className="juego-sub">
        {listo
          ? `${personaje?.name || 'Tu amigo'} está listo para la foto`
          : `${invitado ? invitado + ', a' : 'A'}rrastra las piezas a su lugar`}
      </p>

      <div ref={stageRef} className={`mp-stage${listo ? ' mp-stage--listo' : ''}`}>
        {/* Siluetas: solo se ven mientras su pieza no está colocada. */}
        {MUNECO_PARTES.filter((p) => !colocadas.includes(p.id)).map((p) => (
          <span
            key={`silueta-${p.id}`}
            className="mp-silueta"
            data-parte={p.id}
            style={{
              left: `${p.target.x}%`,
              top: `${p.target.y}%`,
              transform: `translate(-50%,-50%) rotate(${p.rot || 0}deg)`,
            }}
          >
            <FormaMuneco forma={p.forma} />
          </span>
        ))}
        {/* Piezas ya colocadas: fijas en su lugar final. */}
        {MUNECO_PARTES.filter((p) => colocadas.includes(p.id)).map((p) => (
          <span
            key={`fija-${p.id}`}
            className="mp-pieza mp-pieza--fija"
            data-parte={p.id}
            style={{
              left: `${p.target.x}%`,
              top: `${p.target.y}%`,
              transform: `translate(-50%,-50%) rotate(${p.rot || 0}deg)`,
            }}
          >
            <FormaMuneco forma={p.forma} />
          </span>
        ))}
      </div>

      {!listo && (
        <div className="mp-bandeja">
          {faltantes.map((p) => (
            <button
              key={p.id}
              type="button"
              className="mp-pieza mp-pieza--suelta"
              onPointerDown={empezarArrastre(p.id)}
              onTouchStart={empezarArrastre(p.id)}
              style={{ visibility: arrastre?.id === p.id ? 'hidden' : 'visible' }}
              aria-label={`Pieza ${p.id}`}
            >
              <FormaMuneco forma={p.forma} />
            </button>
          ))}
        </div>
      )}

      {/* Pieza fantasma que sigue al dedo mientras se arrastra. */}
      {arrastrandoParte && (
        <span
          className="mp-pieza mp-pieza--arrastrando"
          data-parte={arrastrandoParte.id}
          style={{
            left: arrastre.x,
            top: arrastre.y,
            transform: `translate(-50%,-50%) rotate(${arrastrandoParte.rot || 0}deg)`,
          }}
        >
          <FormaMuneco forma={arrastrandoParte.forma} />
        </span>
      )}

      {listo && <button className="cta pulse" onClick={finish}>Ahora sí, mi foto 📸</button>}

      <button className="juego-skip" onClick={finish}>Saltar ⏭</button>
    </section>
  )
}


/* ── Rompecabezas de fichas (kind: "fichas") ───────────────────────────────
   El niño arma la imagen del personaje intercambiando piezas: toca una,
   toca otra y se cambian de lugar. Se eligió intercambio y no arrastre
   porque el drag sobre una tablet falla mucho con dedos chicos. No hay
   forma de perder ni de trabarse: cualquier permutación se resuelve con
   intercambios. La imagen la publica la API en game.image.
   Coexiste con "armar-muneco" (arrastre) como un tipo de juego distinto,
   asignable a cualquier personaje vía themes.json.
   ───────────────────────────────────────────────────────────────────────── */
function barajarPiezas(total) {
  const orden = Array.from({ length: total }, (_, i) => i)
  for (let i = orden.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[orden[i], orden[j]] = [orden[j], orden[i]]
  }
  // Un barajado que devuelva la imagen ya armada arruinaría el juego.
  const yaResuelto = orden.every((v, i) => v === i)
  if (yaResuelto && total > 1) {
    ;[orden[0], orden[1]] = [orden[1], orden[0]]
  }
  return orden
}

function JuegoFichas({ config, invitado, personaje, onDone }) {
  const cols = config.cols || 3
  const filas = config.filas || 3
  const total = cols * filas
  const [orden, setOrden] = useState(() => barajarPiezas(total))
  const [elegida, setElegida] = useState(null)
  const [esRecord, setEsRecord] = useState(false)
  // En los rompecabezas no hay puntaje: el récord es el MEJOR TIEMPO, y por
  // eso se guarda con modo 'menor'.
  const [record] = useState(() => textoRecord('fichas', formatoSegundos))
  const doneRef = useRef(false)
  const inicioRef = useRef(performance.now())

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  const listo = orden.every((v, i) => v === i)

  useEffect(() => {
    if (!listo || REDUCE_MOTION) return undefined
    setEsRecord(guardarRecord('fichas', performance.now() - inicioRef.current, invitado, 'menor'))
    const t = setTimeout(finish, 3200)
    return () => clearTimeout(t)
  }, [listo, finish, invitado])

  const tocar = (pos) => {
    if (listo) return
    if (elegida === null) {
      setElegida(pos)
      return
    }
    if (elegida === pos) {
      setElegida(null)
      return
    }
    setOrden((prev) => {
      const next = [...prev]
      ;[next[elegida], next[pos]] = [next[pos], next[elegida]]
      return next
    })
    setElegida(null)
  }

  const bienPuestas = orden.filter((v, i) => v === i).length

  if (!config.image) {
    // Sin imagen publicada no hay rompecabezas posible: se avanza sin trabar
    // al invitado. Mejor saltarse el juego que mostrar un tablero roto.
    return (
      <section className="screen juego juego--muneco juego--fichas">
        <div className="juego-panel">
          <h2 className="juego-title">¡Vamos a tu foto!</h2>
          <button className="cta pulse" onClick={finish}>Continuar 📸</button>
        </div>
      </section>
    )
  }

  return (
    <section className="screen juego juego--muneco juego--fichas">
      <div className="juego-hud">
        <span className="juego-hud__item">🧩 {bienPuestas}/{total}</span>
        {record && <span className="juego-hud__item juego-hud__record">🏆 {record}</span>}
      </div>

      {/* Pista: cómo debe quedar armado. Una vez resuelto sobra — el propio
          tablero ya es la imagen completa. */}
      {!listo && (
        <div className="juego-pista">
          <span className="juego-pista__etiqueta">Así se ve</span>
          <img className="juego-pista__img" src={config.image} alt="Referencia del rompecabezas" />
        </div>
      )}

      {listo && esRecord && <p className="juego-record-nuevo">🏆 ¡NUEVO RÉCORD!</p>}
      <h2 className="juego-title">{listo ? '¡Lo armaste!' : config.label}</h2>
      <p className="juego-sub">
        {listo
          ? `${personaje?.name || 'Tu amigo'} está listo para la foto`
          : `${invitado ? invitado + ', t' : 'T'}oca dos piezas para cambiarlas`}
      </p>

      <div
        className={`puzzle-tablero${listo ? ' puzzle-tablero--listo' : ''}`}
        style={{ '--puzzle-cols': cols, '--puzzle-filas': filas }}
      >
        {orden.map((pieza, pos) => {
          const col = pieza % cols
          const fila = Math.floor(pieza / cols)
          return (
            <button
              key={pos}
              type="button"
              className={`puzzle-pieza${elegida === pos ? ' puzzle-pieza--elegida' : ''}${pieza === pos ? ' puzzle-pieza--ok' : ''}`}
              onClick={() => tocar(pos)}
              aria-label={`Pieza ${pos + 1}`}
              style={{
                backgroundImage: `url("${config.image}")`,
                backgroundSize: `${cols * 100}% ${filas * 100}%`,
                backgroundPosition: `${cols > 1 ? (col * 100) / (cols - 1) : 0}% ${filas > 1 ? (fila * 100) / (filas - 1) : 0}%`,
              }}
            />
          )
        })}
      </div>

      {listo && <button className="cta pulse" onClick={finish}>Ahora sí, mi foto 📸</button>}

      <button className="juego-skip" onClick={finish}>Saltar ⏭</button>
    </section>
  )
}

/* ── Juego de ritmo (reescrito 2026-08-01) ────────────────────────────────
   La versión anterior se llamaba "ritmo" pero no tenía ritmo: encendía un
   carril al azar cada 680ms y no sonaba nada. Ahora es un juego de ritmo de
   verdad — notas que caen hacia los pads, música sintetizada a 112 BPM y
   puntaje por precisión.

   Comparte motor con El Show 3D (`src/gameAudio.js`): misma batería, mismo
   reloj anclado, mismas ventanas de acierto. Así los dos juegos "de tocar al
   beat" del kiosco se sienten igual y hay un solo lugar donde tocar el tempo.

   Las notas se posicionan en % del campo (no en px) y todo escala con el
   lienzo: funciona igual en un celular que en la tablet de 10".
   ─────────────────────────────────────────────────────────────────────── */
const RITMO_W_PERFECT = 0.17   // ±170ms — dedos de niño, no de músico
const RITMO_W_GOOD = 0.33
const RITMO_APPROACH = 1.7     // segundos que tarda una nota en bajar
const RITMO_LEAD_IN = 2.2

function construirChartRitmo(segundos, carriles, eighth) {
  const notas = []
  let seed = 20260801
  const rnd = () => {
    seed = (seed * 1664525 + 1013904223) >>> 0
    return seed / 4294967296
  }
  let carril = Math.floor(carriles / 2)
  const pasos = Math.max(1, Math.floor(segundos / eighth))
  for (let paso = 0; paso < pasos; paso += 1) {
    const compas = Math.floor(paso / 8)
    const enTiempo = paso % 2 === 0
    let hay = enTiempo
    if (!enTiempo && compas >= 2 && rnd() < Math.min(0.38, 0.1 + compas * 0.05)) hay = true
    if (!hay) continue
    if (notas.length) {
      const salto = rnd() < 0.6 ? (rnd() < 0.5 ? -1 : 1) : 0
      carril = Math.max(0, Math.min(carriles - 1, carril + salto))
    }
    notas.push({ t: RITMO_LEAD_IN + paso * eighth, carril, hecha: false })
  }
  return notas
}

function JuegoRitmo({ config, invitado, personaje, onDone }) {
  const lanes = config.lanes || 4
  const segundos = config.seconds || 15
  // Pool fijo de notas manipuladas por DOM directo, NO por estado de React.
  // A lo sumo hay APPROACH/corchea ≈ 7 notas en vuelo; 12 sobra. Con estado
  // de React esto re-renderizaba el juego 60 veces por segundo y en una
  // tablet Android de gama media se notaba el tirón.
  const notaRefs = useRef([])
  const [score, setScore] = useState(0)
  const [combo, setCombo] = useState(0)
  const [best, setBest] = useState(0)
  const [aciertos, setAciertos] = useState(0)
  const [left, setLeft] = useState(segundos)
  const [golpe, setGolpe] = useState(null)   // {carril, tipo, id} para el flash del pad
  const [fase, setFase] = useState(REDUCE_MOTION ? 'done' : 'playing')
  const [esRecord, setEsRecord] = useState(false)
  // Se lee UNA vez al montar: es el récord a batir, no debe cambiar bajo los
  // pies del jugador cuando él mismo lo supere a mitad de partida.
  const [record] = useState(() => textoRecord('ritmo'))
  const doneRef = useRef(false)
  const apiRef = useRef(null)

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  useEffect(() => {
    if (REDUCE_MOTION) return undefined
    const kit = createAudioKit()
    const reloj = createBeatClock(kit)
    const chart = construirChartRitmo(segundos, lanes, reloj.eighth)
    const total = chart.length
    const fin = RITMO_LEAD_IN + segundos + 0.8
    const st = { proxima: 0, score: 0, combo: 0, best: 0, aciertos: 0, seg: -1 }
    let raf = 0
    let vivo = true
    const timers = new Set()

    const flash = (carril, tipo) => {
      const id = Math.random()
      setGolpe({ carril, tipo, id })
      const t = window.setTimeout(() => {
        timers.delete(t)
        if (vivo) setGolpe((g) => (g && g.id === id ? null : g))
      }, 320)
      timers.add(t)
    }

    const romperCombo = () => {
      st.combo = 0
      setCombo(0)
    }

    reloj.start()

    apiRef.current = (carril) => {
      if (!vivo) return
      if (kit && kit.ctx.state === 'suspended') kit.resume()
      const t = reloj.now()
      let objetivo = null
      let mejor = Infinity
      for (let i = st.proxima; i < chart.length; i += 1) {
        const n = chart[i]
        if (n.t > t + RITMO_W_GOOD) break
        if (n.hecha || n.carril !== carril) continue
        const d = Math.abs(n.t - t)
        if (d < mejor) { mejor = d; objetivo = n }
      }
      const ahora = kit ? kit.ctx.currentTime : 0
      if (!objetivo) {
        romperCombo()
        flash(carril, 'miss')
        if (kit) kit.miss(ahora)
        return
      }
      objetivo.hecha = true
      const perfecto = mejor <= RITMO_W_PERFECT
      st.aciertos += 1
      st.combo += 1
      st.best = Math.max(st.best, st.combo)
      st.score += perfecto ? 100 : 50
      setScore(st.score)
      setCombo(st.combo)
      setAciertos(st.aciertos)
      flash(carril, perfecto ? 'perfect' : 'good')
      if (kit) (perfecto ? kit.perfect(ahora, st.combo) : kit.good(ahora))
      if (navigator.vibrate) navigator.vibrate(perfecto ? 16 : 10)
    }

    const loop = () => {
      if (!vivo) return
      const t = reloj.now()
      reloj.tick(t)

      const seg = Math.max(0, Math.ceil(RITMO_LEAD_IN + segundos - t))
      if (seg !== st.seg) { st.seg = seg; setLeft(seg) }

      // Notas que pasaron de largo: cortan la racha, no restan puntos.
      while (st.proxima < chart.length && chart[st.proxima].t < t - RITMO_W_GOOD) {
        if (!chart[st.proxima].hecha) romperCombo()
        st.proxima += 1
      }

      if (t >= fin) {
        setBest(st.best)
        // El récord se cierra acá, con el puntaje final: si se guardara en
        // cada acierto, el marcador mostraría marcas de partidas a medias.
        setEsRecord(guardarRecord('ritmo', st.score, invitado, 'mayor'))
        setFase('done')
        if (kit) kit.cheer(kit.ctx.currentTime)
        return
      }

      // Notas en vuelo, escritas directo al DOM. `p` va de 0 (arriba) a 1 (pad).
      let slot = 0
      for (let i = st.proxima; i < chart.length && slot < notaRefs.current.length; i += 1) {
        const n = chart[i]
        const dt = n.t - t
        if (dt > RITMO_APPROACH) break
        if (n.hecha) continue
        const el = notaRefs.current[slot]
        if (el) {
          const p = Math.min(1, 1 - dt / RITMO_APPROACH)
          el.style.display = 'block'
          el.style.setProperty('--lane', n.carril)
          el.style.top = `${p * 100}%`
          el.style.opacity = p < 0.06 ? String(p / 0.06) : '1'
        }
        slot += 1
      }
      for (let i = slot; i < notaRefs.current.length; i += 1) {
        const el = notaRefs.current[i]
        if (el) el.style.display = 'none'
      }

      raf = requestAnimationFrame(loop)
    }
    raf = requestAnimationFrame(loop)

    return () => {
      vivo = false
      if (raf) cancelAnimationFrame(raf)
      timers.forEach((t) => window.clearTimeout(t))
      timers.clear()
      apiRef.current = null
      if (kit) kit.close()
    }
  }, [lanes, segundos])

  const tocar = (carril) => apiRef.current?.(carril)

  if (REDUCE_MOTION) {
    return (
      <section className="screen juego juego--ritmo">
        <div className="juego-panel">
          <h2 className="juego-title">{config.label}</h2>
          <p className="juego-sub">Puedes seguir directo a tu foto.</p>
          <button className="cta pulse" onClick={finish}>Ir a mi foto 📸</button>
        </div>
      </section>
    )
  }

  // El chart es determinista: se cuenta una vez, no en cada render del HUD.
  const totalNotas = useMemo(
    () => Math.max(1, construirChartRitmo(segundos, lanes, 60 / DEFAULT_BPM / 2).length),
    [segundos, lanes],
  )
  const precision = aciertos / totalNotas
  const estrellas = precision >= 0.7 ? 3 : precision >= 0.4 ? 2 : 1

  return (
    <section
      className={`screen juego juego--ritmo${combo >= 8 ? ' is-fuego' : ''}`}
      style={config.image ? { backgroundImage: `url("${config.image}")` } : undefined}
      onKeyDown={(e) => {
        const n = Number(e.key)
        if (n >= 1 && n <= lanes) tocar(n - 1)
      }}
      tabIndex={0}
    >
      <div className="juego-hud">
        <span className="juego-hud__item">🎵 {score}</span>
        <span className="juego-hud__item">⏱ {left}s</span>
        {record && <span className="juego-hud__item juego-hud__record">🏆 {record}</span>}
      </div>

      {fase === 'playing' ? (
        <>
          <h2 className="juego-title">{config.label}</h2>
          <p className="juego-sub">
            {invitado ? `¡Vamos ${invitado}! ` : ''}Toca cuando la nota llegue abajo
          </p>
          {combo >= 3 && <div className="ritmo-combo">x{combo}</div>}

          <div className="ritmo-pista" style={{ '--ritmo-lanes': lanes }}>
            {/* Pool fijo: el bucle de animación les escribe top/--lane directo,
                sin pasar por React. Van en % del alto de la pista, así que
                escalan solas con el lienzo. */}
            {/* La zona de caída excluye la altura de los pads: así top:100%
                cae EXACTO sobre la línea de acierto y no detrás de los pads. */}
            <div className="ritmo-caida" aria-hidden="true">
              {Array.from({ length: 12 }, (_, i) => (
                <span
                  key={i}
                  ref={(el) => { notaRefs.current[i] = el }}
                  className="ritmo-nota"
                  style={{ display: 'none' }}
                />
              ))}
            </div>
            <div className="ritmo-pads">
              {Array.from({ length: lanes }, (_, carril) => {
                const g = golpe && golpe.carril === carril ? golpe.tipo : null
                return (
                  <button
                    key={carril}
                    type="button"
                    className={`ritmo-pad${g ? ` ritmo-pad--${g}` : ''}`}
                    onPointerDown={() => tocar(carril)}
                    aria-label={`Carril ${carril + 1}`}
                  />
                )
              })}
            </div>
          </div>
        </>
      ) : (
        <div className="juego-panel">
          {esRecord && <p className="juego-record-nuevo">🏆 ¡NUEVO RÉCORD!</p>}
          <p className="juego-resultado-emoji">{'★'.repeat(estrellas)}{'☆'.repeat(3 - estrellas)}</p>
          <h2 className="juego-title">¡Gran presentación!</h2>
          <p className="juego-sub">
            {score > 0 ? `${score} puntos` : '¡Casi!'}
            {best >= 3 ? ` · racha de ${best}` : ''}
            {personaje?.name ? ` · ${personaje.name} te espera` : ''}
          </p>
          <button className="cta pulse" onClick={finish}>Ahora sí, mi foto 📸</button>
        </div>
      )}
      <button className="juego-skip" onClick={finish}>Saltar ⏭</button>
    </section>
  )
}

/* ── Juego de escudo (reescrito 2026-08-01) ───────────────────────────────
   Antes era UN emoji que se teletransportaba cada 850ms: sin racha, sin
   riesgo y sin nada que mirar. Ahora hay varios escudos a la vez, cada uno
   con su propia vida — si uno se apaga solo, corta la racha. Eso le da al
   niño una razón para moverse rápido en vez de esperar el salto.

   Las metas viven en % del campo, así que el área de juego crece con el
   lienzo: en la tablet de 10" hay más espacio real, no los mismos píxeles.
   ─────────────────────────────────────────────────────────────────────── */
const ESCUDO_META = 10

function JuegoEscudo({ config, invitado, personaje, onDone }) {
  const segundos = config.seconds || 15
  const [metas, setMetas] = useState([])
  const [chispas, setChispas] = useState([])
  const [score, setScore] = useState(0)
  const [combo, setCombo] = useState(0)
  const [best, setBest] = useState(0)
  const [left, setLeft] = useState(segundos)
  const [fase, setFase] = useState(REDUCE_MOTION ? 'done' : 'playing')
  const [esRecord, setEsRecord] = useState(false)
  const [record] = useState(() => textoRecord('escudo'))
  const doneRef = useRef(false)
  const idRef = useRef(0)
  const comboRef = useRef(0)
  const bestRef = useRef(0)
  const scoreRef = useRef(0)

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  // Cuenta regresiva.
  useEffect(() => {
    if (fase !== 'playing') return undefined
    const t = setInterval(() => {
      setLeft((v) => {
        if (v <= 1) {
          clearInterval(t)
          setBest(bestRef.current)
          setEsRecord(guardarRecord('escudo', scoreRef.current, invitado, 'mayor'))
          setFase('done')
          return 0
        }
        return v - 1
      })
    }, 1000)
    return () => clearInterval(t)
  }, [fase])

  // Aparición de escudos. Cada uno se apaga solo si nadie lo toca: ahí está
  // la tensión que antes no existía.
  useEffect(() => {
    if (fase !== 'playing') return undefined
    const timers = new Set()
    const spawn = setInterval(() => {
      idRef.current += 1
      const id = idRef.current
      const vidaMs = 1500 + Math.random() * 900
      setMetas((list) => [
        ...list.slice(-5),
        { id, x: 14 + Math.random() * 72, y: 16 + Math.random() * 66, vidaMs },
      ])
      const t = window.setTimeout(() => {
        timers.delete(t)
        setMetas((list) => {
          if (!list.some((m) => m.id === id)) return list // ya la tocaron
          comboRef.current = 0
          setCombo(0)
          return list.filter((m) => m.id !== id)
        })
      }, vidaMs)
      timers.add(t)
    }, 720)
    return () => {
      clearInterval(spawn)
      timers.forEach((t) => window.clearTimeout(t))
    }
  }, [fase])

  useEffect(() => {
    if (fase !== 'done' || REDUCE_MOTION) return undefined
    const t = setTimeout(finish, 2600)
    return () => clearTimeout(t)
  }, [fase, finish])

  const activar = (meta) => {
    setMetas((list) => list.filter((m) => m.id !== meta.id))
    comboRef.current += 1
    bestRef.current = Math.max(bestRef.current, comboRef.current)
    setCombo(comboRef.current)
    // scoreRef espeja el puntaje porque el temporizador cierra el récord desde
    // un setInterval, donde el `score` del closure ya estaría viejo.
    scoreRef.current += comboRef.current >= 5 ? 2 : 1
    setScore(scoreRef.current)
    playSound(CONFIG.audio.nota)
    if (navigator.vibrate) navigator.vibrate(14)
    // Chispa en el lugar exacto del impacto: feedback sin costo de layout.
    const chispaId = meta.id + 1e6
    setChispas((list) => [...list, { id: chispaId, x: meta.x, y: meta.y }])
    setTimeout(() => setChispas((list) => list.filter((c) => c.id !== chispaId)), 520)
  }

  if (REDUCE_MOTION) {
    return (
      <section className="screen juego juego--escudo">
        <div className="juego-panel">
          <h2 className="juego-title">{config.label}</h2>
          <button className="cta pulse" onClick={finish}>Ir a mi foto 📸</button>
        </div>
      </section>
    )
  }

  const estrellas = score >= ESCUDO_META * 1.6 ? 3 : score >= ESCUDO_META ? 2 : 1

  return (
    <section
      className={`screen juego juego--escudo${combo >= 5 ? ' is-fuego' : ''}`}
      style={config.image ? { backgroundImage: `url("${config.image}")` } : undefined}
    >
      <div className="juego-hud">
        <span className="juego-hud__item">🛡️ {score}</span>
        <span className="juego-hud__item">⏱ {left}s</span>
        {record && <span className="juego-hud__item juego-hud__record">🏆 {record}</span>}
      </div>
      {fase === 'playing' ? (
        <>
          <h2 className="juego-title">{config.label}</h2>
          <p className="juego-sub">
            {invitado ? `${invitado}, a` : 'A'}ctiva los escudos antes de que se apaguen
          </p>
          {combo >= 3 && <div className="ritmo-combo">x{combo}</div>}
          <div className="escudo-field">
            {metas.map((m) => (
              <button
                key={m.id}
                type="button"
                className="escudo-target"
                style={{
                  left: `${m.x}%`,
                  top: `${m.y}%`,
                  // La barra de vida del escudo es la propia animación: se
                  // encoge hasta apagarse, así se ve cuál está por vencer.
                  animationDuration: `${m.vidaMs}ms`,
                }}
                onPointerDown={() => activar(m)}
                aria-label="Activar escudo"
              >
                🛡️
              </button>
            ))}
            {chispas.map((c) => (
              <span
                key={c.id}
                className="escudo-chispa"
                style={{ left: `${c.x}%`, top: `${c.y}%` }}
                aria-hidden="true"
              />
            ))}
          </div>
        </>
      ) : (
        <div className="juego-panel">
          {esRecord && <p className="juego-record-nuevo">🏆 ¡NUEVO RÉCORD!</p>}
          <p className="juego-resultado-emoji">{'★'.repeat(estrellas)}{'☆'.repeat(3 - estrellas)}</p>
          <h2 className="juego-title">¡Ciudad protegida!</h2>
          <p className="juego-sub">
            {score} escudos{best >= 3 ? ` · racha de ${best}` : ''}
            {personaje?.name ? ` · ${personaje.name} te espera` : ''}
          </p>
          <button className="cta pulse" onClick={finish}>Ahora sí, mi foto 📸</button>
        </div>
      )}
      <button className="juego-skip" onClick={finish}>Saltar ⏭</button>
    </section>
  )
}

function JuegoCopos({ config, invitado, personaje, onDone }) {
  const flow = config || THEME_FLOW.game || { seconds: 15, label: '¡Atrapa los copos!' }
  // El HUD y la pantalla de resultado usaban ❄️ fijo aunque la temática atrape
  // otra cosa (estrellas, cocos...). Ahora toman el propio emoji de la
  // temática y solo caen en ❄️ si no definió ninguno (Luis, 2026-07-28).
  const iconoJuego = flow.emojis?.[0] || COPO_EMOJIS[0]
  const [copos, setCopos] = useState([])
  const [avisos, setAvisos] = useState([]) // 🚫 flotante transitorio al tocar una trampa
  const [score, setScore] = useState(0)
  const [combo, setCombo] = useState(0)
  const [left, setLeft] = useState(flow.seconds)
  const [phase, setPhase] = useState(REDUCE_MOTION ? 'done' : 'playing')
  const [esRecord, setEsRecord] = useState(false)
  const [record] = useState(() => textoRecord('copos'))
  const idRef = useRef(0)
  const doneRef = useRef(false)
  const comboRef = useRef(0)
  const scoreRef = useRef(0)

  // Un solo camino de salida: evita que el temporizador y el botón disparen
  // onDone dos veces y salten una pantalla de más.
  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone(scoreRef.current)
  }, [onDone])

  // Cuenta regresiva.
  useEffect(() => {
    if (phase !== 'playing') return undefined
    const t = setInterval(() => {
      setLeft((v) => {
        if (v <= 1) {
          clearInterval(t)
          setEsRecord(guardarRecord('copos', scoreRef.current, invitado, 'mayor'))
          setPhase('done')
          return 0
        }
        return v - 1
      })
    }, 1000)
    return () => clearInterval(t)
  }, [phase])

  // Aparición de copos + limpieza de los que ya cayeron enteros.
  useEffect(() => {
    if (phase !== 'playing') return undefined
    const spawn = setInterval(() => {
      idRef.current += 1
      const id = idRef.current
      const trampa = Math.random() < COPO_TRAMPA_PROB
      const copo = {
        id,
        trampa,
        leftPct: 6 + Math.random() * 84,
        durationS: 3.4 + Math.random() * 1.8,
        sizeRem: 2.2 + Math.random() * 1.6,
        emoji: trampa
          ? COPO_TRAMPA_EMOJI
          : (flow.emojis?.length ? flow.emojis : COPO_EMOJIS)[id % (flow.emojis?.length || COPO_EMOJIS.length)],
      }
      setCopos((list) => [...list.slice(-14), copo])
      // Se descarta solo cuando terminó su caída; no queda basura en el DOM.
      setTimeout(() => {
        setCopos((list) => list.filter((c) => c.id !== id))
      }, copo.durationS * 1000 + 200)
    }, 430)
    return () => clearInterval(spawn)
  }, [phase])

  // Al terminar el tiempo se muestra el resultado un instante y se avanza solo.
  useEffect(() => {
    if (phase !== 'done' || REDUCE_MOTION) return undefined
    const t = setTimeout(finish, 2600)
    return () => clearTimeout(t)
  }, [phase, finish])

  const atrapar = (id, ev) => {
    const item = copos.find((c) => c.id === id)
    setCopos((list) => list.filter((c) => c.id !== id))

    // Trampa: no suma, avisa con sonido + 🚫 flotante donde tocó. Nunca cuenta
    // como acierto (Luis, 2026-07-28: "que no tome como correcto").
    if (item?.trampa) {
      comboRef.current = 0
      setCombo(0)
      playSound(CONFIG.audio.error)
      const campo = ev?.currentTarget?.closest('.juego-campo')
      const rect = campo?.getBoundingClientRect()
      if (rect && ev) {
        const avisoId = id + 1e6
        const x = ev.clientX - rect.left
        const y = ev.clientY - rect.top
        setAvisos((list) => [...list, { id: avisoId, x, y }])
        setTimeout(() => {
          setAvisos((list) => list.filter((a) => a.id !== avisoId))
        }, 550)
      }
      return
    }

    // Racha: a partir de 5 seguidos cada acierto vale doble. Mismo lenguaje
    // que Ritmo, Escudo y El Show 3D, para que los juegos se sientan de la
    // misma familia y el niño entienda el premio sin que nadie se lo explique.
    comboRef.current += 1
    setCombo(comboRef.current)
    // Igual que en Escudo: el temporizador cierra el récord desde un
    // setInterval y ahí el `score` del closure ya está viejo.
    scoreRef.current += comboRef.current >= 5 ? 2 : 1
    setScore(scoreRef.current)
    playSound(CONFIG.audio.nota)
  }

  if (REDUCE_MOTION) {
    return (
      <section className="screen juego">
        <div className="juego-panel">
          <h2 className="juego-title">{flow.label}</h2>
          <p className="juego-sub">Este juego usa animación. Puedes seguir directo a tu foto.</p>
          <button className="cta pulse" onClick={finish}>Ir a mi foto 📸</button>
        </div>
      </section>
    )
  }

  return (
    <section
      className={`screen juego${flow.image ? ' juego--copos' : ''}${combo >= 5 ? ' is-fuego' : ''}`}
      style={flow.image ? { backgroundImage: `url("${flow.image}")` } : undefined}
    >
      <div className="juego-hud">
        <span className="juego-hud__item">{iconoJuego} {score}</span>
        <span className="juego-hud__item">⏱ {left}s</span>
        {record && <span className="juego-hud__item juego-hud__record">🏆 {record}</span>}
      </div>

      {phase === 'playing' && combo >= 3 && <div className="ritmo-combo">x{combo}</div>}

      {phase === 'playing' && (
        <>
          <h2 className="juego-title">{flow.label}</h2>
          <p className="juego-sub">
            {invitado ? `¡Vamos ${invitado}!` : '¡Vamos!'} Tócalos antes de que lleguen al suelo
          </p>
          <div className="juego-campo" aria-hidden="true">
            {copos.map((c) => (
              <button
                key={c.id}
                type="button"
                className={`copo${c.trampa ? ' copo--trampa' : ''}`}
                style={{
                  left: `${c.leftPct}%`,
                  fontSize: `${c.sizeRem}rem`,
                  animationDuration: `${c.durationS}s`,
                }}
                onPointerDown={(ev) => atrapar(c.id, ev)}
                aria-label={c.trampa ? 'Objeto incorrecto' : 'Copo de nieve'}
              >
                {c.emoji}
              </button>
            ))}
            {avisos.map((a) => (
              <span key={a.id} className="copo-aviso" style={{ left: a.x, top: a.y }}>
                🚫
              </span>
            ))}
          </div>
        </>
      )}

      {phase === 'done' && (
        <div className="juego-panel">
          {esRecord && <p className="juego-record-nuevo">🏆 ¡NUEVO RÉCORD!</p>}
          <p className="juego-resultado-emoji">{score >= 10 ? '🏆' : iconoJuego}</p>
          <h2 className="juego-title">
            {score === 0 ? '¡Casi!' : `¡Atrapaste ${score}!`}
          </h2>
          <p className="juego-sub">
            {personaje?.name ? `${personaje.name} está orgulloso de ti` : '¡Muy bien!'}
          </p>
          <button className="cta pulse" onClick={finish}>Ahora sí, mi foto 📸</button>
        </div>
      )}

      <button className="juego-skip" onClick={finish}>
        Saltar ⏭
      </button>
    </section>
  )
}

/* ============================================================
   3) TRANSICION WOW — parallax + confetti, auto-avanza
   ============================================================ */
const TRANSITION_DURATION_MS = 3800 // no reduced-motion; el path 3D usa el mismo valor

function TransicionWow({ invitado, personaje, onDone }) {
  const confRef = useRef(null)
  // Se calcula una sola vez por pantalla (supports3D() ya cachea el resultado
  // a nivel de módulo — no vuelve a probar WebGL en cada invitado).
  const [use3D] = useState(() => supports3D())

  useEffect(() => {
    const stop = burstConfetti(confRef.current, { duration: 3500, count: 200 })
    const t = setTimeout(onDone, REDUCE_MOTION ? 1200 : TRANSITION_DURATION_MS)
    return () => {
      stop && stop()
      clearTimeout(t)
    }
  }, [onDone])

  // Preferimos el recorte transparente del ganador (mismo que usa la foto
  // compuesta); si aún no cargó o no existe, el JPG normal también sirve
  // como textura (se ve con fondo rectangular, pero no rompe la escena).
  const charName = personaje && personaje.name
  const charSrc = (charName && (getCharPng(charName)?.src || CHAR_IMG[charName])) || null

  return (
    <section className="screen transition">
      {use3D ? (
        <ToyTrack3D charSrc={charSrc} durationMs={TRANSITION_DURATION_MS} />
      ) : (
        <div
          className="transition-bg"
          style={{ backgroundImage: `url(${CONFIG.images.fondo})` }}
        />
      )}
      <div className="transition-overlay" />
      <div className="transition-text">
        <h2>¡Ahora nos tomaremos una foto!</h2>
        <p>Sonríe {invitado},</p>
        <h1>{CONFIG.nombre}</h1>
      </div>
      <canvas ref={confRef} className="confetti-canvas" />
    </section>
  )
}

/* ============================================================
   4) CAPTURE — webcam en vivo + capturar
   ============================================================ */
function Capture({ onCapture }) {
  const videoRef = useRef(null)
  const streamRef = useRef(null)
  const [error, setError] = useState(null)
  const [count, setCount] = useState(null)
  const [ready, setReady] = useState(false)
  const [attempt, setAttempt] = useState(0) // reintentos sin recargar la página
  const [cameras, setCameras] = useState([])
  const [selectedCamera, setSelectedCamera] = useState('')

  useEffect(() => {
    let cancelled = false
    setError(null)
    setReady(false)

    // Si en 10s no llegan fotogramas, avisar aunque getUserMedia haya
    // entregado un stream. Algunos Chrome dejan play() pendiente y antes la
    // UI quedaba para siempre detrás de "Abriendo la cámara…".
    const watchdog = setTimeout(() => {
      const video = videoRef.current
      if (!cancelled && (!video || video.readyState < 2 || !video.videoWidth)) {
        setError(streamRef.current
          ? 'La cámara fue detectada, pero no está entregando imagen. Revisa la tapa física de privacidad, elige otra cámara o cierra Zoom/Teams y reintenta.'
          : 'Chrome todavía no entrega acceso a la cámara. Acepta el permiso en la barra de dirección y toca Reintentar.')
      }
    }, 10000)

    async function startCam() {
      try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          throw Object.assign(new Error('insecure'), { name: 'InsecureContext' })
        }
        if (streamRef.current) streamRef.current.getTracks().forEach((track) => track.stop())
        const videoConstraints = selectedCamera
          ? { deviceId: { exact: selectedCamera }, width: { ideal: 1280 }, height: { ideal: 720 } }
          : { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        const stream = await navigator.mediaDevices.getUserMedia({
          video: videoConstraints,
          audio: false,
        })
        if (cancelled) {
          stream.getTracks().forEach((t) => t.stop())
          return
        }
        streamRef.current = stream
        if (videoRef.current) {
          const video = videoRef.current
          const markReady = () => {
            if (!cancelled && video.videoWidth > 0 && video.readyState >= 2) {
              clearTimeout(watchdog)
              setReady(true)
            }
          }
          video.onloadedmetadata = markReady
          video.oncanplay = markReady
          video.onplaying = markReady
          video.srcObject = stream
          video.play().then(markReady).catch(() => {
            // loadedmetadata/canplay siguen siendo la fuente de verdad.
          })

          const devices = await navigator.mediaDevices.enumerateDevices()
          if (!cancelled) {
            const videoDevices = devices
              .filter((device) => device.kind === 'videoinput')
              .map((device, index) => ({
                id: device.deviceId,
                label: device.label || `Cámara ${index + 1}`,
              }))
            setCameras(videoDevices)

            // Chrome puede elegir primero una cámara virtual (teléfono/OBS) que
            // entrega un lienzo negro. En modo automático priorizamos hardware
            // físico; el usuario conserva siempre el selector para cambiarlo.
            if (!selectedCamera) {
              const activeId = stream.getVideoTracks()[0]?.getSettings?.().deviceId || ''
              const active = videoDevices.find((device) => device.id === activeId)
              const isVirtual = (label) => /virtual|obs|manycam|snap camera/i.test(label || '')
              const physical = videoDevices.find((device) => !isVirtual(device.label))
              if (active && isVirtual(active.label) && physical && physical.id !== active.id) {
                setSelectedCamera(physical.id)
              }
            }
          }
        }
      } catch (e) {
        if (cancelled) return
        const name = e && e.name
        setError(
          name === 'NotAllowedError'
            ? 'Permiso de cámara denegado. Toca el ícono de cámara/candado en la barra de dirección, elige "Permitir" y reintenta.'
            : name === 'NotReadableError'
            ? 'La cámara está ocupada por otra aplicación. Ciérrala (Zoom, Teams, otra pestaña) y reintenta.'
            : name === 'NotFoundError'
            ? 'No se encontró ninguna cámara en este dispositivo.'
            : name === 'InsecureContext'
            ? 'El navegador bloquea la cámara en esta dirección. Usa http://localhost o la URL HTTPS de producción.'
            : 'No se pudo abrir la cámara. Reintenta o recarga la página.'
        )
      }
    }
    startCam()
    // IMPORTANTE: apagar la camara al salir de la pantalla
    return () => {
      cancelled = true
      clearTimeout(watchdog)
      if (streamRef.current) streamRef.current.getTracks().forEach((t) => t.stop())
      streamRef.current = null
    }
  }, [attempt, selectedCamera])

  const doCapture = () => {
    const v = videoRef.current
    if (!v || !v.videoWidth) return
    const c = document.createElement('canvas')
    c.width = v.videoWidth
    c.height = v.videoHeight
    // se guarda SIN espejo (texto legible); la vista en vivo si va espejada
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height)
    playSound(CONFIG.audio.captura)
    onCapture(c.toDataURL('image/png'))
  }

  const startCountdown = () => {
    let n = 3
    setCount(n)
    const iv = setInterval(() => {
      n -= 1
      if (n <= 0) {
        clearInterval(iv)
        setCount(null)
        doCapture()
      } else setCount(n)
    }, 800)
  }

  return (
    <section className="screen capture">
      {cameras.length > 1 && (
        <label className="camera-picker">
          <span>Cámara</span>
          <select
            value={selectedCamera}
            onChange={(event) => setSelectedCamera(event.target.value)}
            aria-label="Elegir cámara"
          >
            <option value="">Automática</option>
            {cameras.map((camera) => (
              <option key={camera.id} value={camera.id}>{camera.label}</option>
            ))}
          </select>
        </label>
      )}
      {error ? (
        <div className="cam-error">
          <div className="big-emoji">📷❌</div>
          <p>{error}</p>
          <button className="cta" onClick={() => setAttempt((a) => a + 1)}>
            Reintentar
          </button>
          <button className="cta ghost" onClick={() => location.reload()} style={{ marginTop: 12 }}>
            Recargar página
          </button>
        </div>
      ) : (
        <>
          <video ref={videoRef} className="cam mirror" playsInline muted autoPlay />
          {!ready && (
            <div className="cam-waiting">
              <div className="big-emoji">📷</div>
              <p>Abriendo la cámara…</p>
              <p className="cam-waiting-sub">Si el navegador pide permiso, toca “Permitir”</p>
            </div>
          )}
          {count !== null && <div className="countdown">{count}</div>}
          <div className="capture-bar">
            <button className="shutter" onClick={startCountdown} aria-label="Capturar foto" disabled={!ready}>
              📸
            </button>
            <p className="capture-hint">¡Sonríe! Toca el botón</p>
          </div>
        </>
      )}
    </section>
  )
}

/* ============================================================
   5) PREVIEW — compone foto DENTRO del marco sobre el fondo
   ============================================================ */
function roundedSquarePath(ctx, x, y, size, radius) {
  const r = Math.max(0, Math.min(radius, size / 2))
  ctx.beginPath()
  ctx.moveTo(x + r, y)
  ctx.lineTo(x + size - r, y)
  ctx.quadraticCurveTo(x + size, y, x + size, y + r)
  ctx.lineTo(x + size, y + size - r)
  ctx.quadraticCurveTo(x + size, y + size, x + size - r, y + size)
  ctx.lineTo(x + r, y + size)
  ctx.quadraticCurveTo(x, y + size, x, y + size - r)
  ctx.lineTo(x, y + r)
  ctx.quadraticCurveTo(x, y, x + r, y)
  ctx.closePath()
}

function composeImage(bgImg, photoImg, invitado = '', charImg = null, charName = '') {
  const W = bgImg?.naturalWidth || 1080
  const H = bgImg?.naturalHeight || 1920
  const c = document.createElement('canvas')
  c.width = W
  c.height = H
  const ctx = c.getContext('2d')

  if (bgImg) ctx.drawImage(bgImg, 0, 0, W, H)
  else {
    ctx.fillStyle = '#ffe9d9'
    ctx.fillRect(0, 0, W, H)
  }

  // La imagen base YA contiene el marco dorado. Solo rellenamos su área útil:
  // dibujar otro aro encima era lo que hacía ver la foto desalineada.
  const {
    oy,
    oh,
    photoLeft,
    photoTop,
    photoSide,
    top: frameTop,
    left: frameLeft,
    right: frameRight,
    cx: frameCx,
    cy: frameCy,
    side: frameSide,
  } = getSquarePhotoGeometry(CONFIG.frameBox, W, H)
  const photoRadius = W * 0.006

  // Foto del invitado con recorte cuadrado y cover-fit, centrada dentro del
  // marco existente y dejando visible todo su borde decorativo.
  ctx.save()
  roundedSquarePath(ctx, photoLeft, photoTop, photoSide, photoRadius)
  ctx.clip()
  const cropSide = Math.min(photoImg.width, photoImg.height)
  const sx = (photoImg.width - cropSide) / 2
  const sy = (photoImg.height - cropSide) / 2
  ctx.drawImage(photoImg, sx, sy, cropSide, cropSide, photoLeft, photoTop, photoSide, photoSide)
  ctx.restore()

  // Filo fino: integra la foto con el marco sin fabricar un segundo marco.
  roundedSquarePath(ctx, photoLeft, photoTop, photoSide, photoRadius)
  ctx.lineWidth = Math.max(2, W * 0.004)
  ctx.strokeStyle = 'rgba(255,246,211,0.92)'
  ctx.stroke()

  // Cintillo con el nombre de la temática (ej "CARS"), a caballo sobre el
  // borde superior del marco dorado — como una placa de trofeo.
  if (THEME_LABEL) drawThemeRibbon(ctx, frameCx, frameTop, frameSide, W)

  // Agradecimiento al costado del marco: K-Pop a la derecha (Luis, 2026-07-28),
  // Héroes a la izquierda y alineado a la izquierda (Luis, 2026-08-01, con
  // captura). El resto sigue como siempre: centrado debajo de la foto completa.
  const textSide = THEME_SLUG === 'kpop' ? 'right' : THEME_SLUG === 'heroes' ? 'left' : null
  const textBeside = textSide !== null
  const textSideMargin = W * 0.045
  const textSideCx = textSide === 'right' ? (frameRight + W) / 2 : frameLeft / 2
  const textSideMaxW = textSide === 'left' ? Math.max(W * 0.18, frameLeft - textSideMargin * 2) : W * 0.3
  const textSideX = textSideMargin
  // El texto comienza debajo del marco completo, no encima de la foto.
  const textBaseY = oy + oh + H * 0.018

  // Pie de foto con el personaje que salió en la ruleta. La pista del
  // personaje termina en 88% del alto, así que la placa vive en la franja
  // libre de abajo sin taparlo ni chocar con el watermark de marca (que va
  // pegado al borde izquierdo).
  let charPlateBottom = null
  if (charName) {
    charPlateBottom = H * 0.945
    drawCharacterNamePlate(ctx, charName, W / 2, charPlateBottom, W)
  }

  // El personaje sorteado queda centrado y apoyado en la pista inferior. Si el
  // PNG aún no cargó o no existe, se omite sin romper el compositing.
  if (charImg && charImg.naturalWidth) {
    const placement = getTrackCharacterGeometry(
      charImg.naturalWidth,
      charImg.naturalHeight,
      W,
      H,
      THEME_SLUG === 'hielo' || THEME_SLUG === 'kpop'
    )
    if (placement) {
      // Sombra de contacto para que ruedas/pies se sientan apoyados en la pista.
      ctx.save()
      ctx.fillStyle = 'rgba(0,0,0,0.28)'
      ctx.filter = `blur(${Math.max(3, W * 0.009)}px)`
      ctx.beginPath()
      ctx.ellipse(
        W / 2,
        placement.bottom + H * 0.006,
        placement.width * 0.34,
        Math.max(H * 0.008, placement.height * 0.055),
        0,
        0,
        Math.PI * 2
      )
      ctx.fill()
      ctx.restore()

      ctx.save()
      ctx.shadowColor = 'rgba(0,0,0,0.32)'
      ctx.shadowBlur = W * 0.016
      ctx.shadowOffsetY = W * 0.01
      ctx.drawImage(charImg, placement.left, placement.top, placement.width, placement.height)
      ctx.restore()
    }
  }

  ctx.textAlign = textSide === 'left' ? 'left' : 'center'
  ctx.textBaseline = 'middle'

  const textCx = textSide === 'left' ? textSideX : textBeside ? textSideCx : W / 2
  const maxW1 = textBeside ? textSideMaxW : W * 0.86
  const maxW2 = textBeside ? textSideMaxW : W * 0.88

  // Línea 1: "Muchas gracias {invitado}"
  const line1 = invitado ? `Muchas gracias ${invitado}` : 'Muchas gracias'
  let fs1 = Math.round(W * 0.046)
  ctx.font = `800 ${fs1}px 'Baloo 2', system-ui, sans-serif`
  while (ctx.measureText(line1).width > maxW1 && fs1 > (textBeside ? 12 : 14)) {
    fs1 -= 2
    ctx.font = `800 ${fs1}px 'Baloo 2', system-ui, sans-serif`
  }
  const line1Y = textBeside ? frameCy - fs1 * 0.75 : textBaseY
  ctx.shadowColor = 'rgba(0,0,0,0.5)'
  ctx.shadowBlur = 10
  ctx.shadowOffsetY = 3
  ctx.lineJoin = 'round'
  ctx.lineWidth = Math.max(3, fs1 * 0.09)
  ctx.strokeStyle = cssVar('--dark2', '#7a0008')
  ctx.strokeText(line1, textCx, line1Y)
  ctx.fillStyle = cssVar('--yellow', '#ffb800')
  ctx.fillText(line1, textCx, line1Y)

  // Línea 2: "por venir al baby shower de X" o "a la fiesta de X" segun la
  // modalidad. Es el texto que el invitado se lleva impreso o en el celular,
  // asi que decirle "fiesta" a un baby shower se nota.
  const line2 = `por venir ${eventoFraseA()}`
  let fs2 = Math.round(W * 0.036)
  ctx.font = `800 ${fs2}px 'Baloo 2', system-ui, sans-serif`
  while (ctx.measureText(line2).width > maxW2 && fs2 > (textBeside ? 10 : 12)) {
    fs2 -= 2
    ctx.font = `800 ${fs2}px 'Baloo 2', system-ui, sans-serif`
  }
  const line2Y = line1Y + fs1 * 1.35
  ctx.lineWidth = Math.max(2, fs2 * 0.09)
  ctx.strokeStyle = cssVar('--dark2', '#7a0008')
  ctx.strokeText(line2, textCx, line2Y)
  ctx.fillStyle = cssVar('--yellow', '#ffb800')
  ctx.fillText(line2, textCx, line2Y)
  ctx.shadowColor = 'transparent'

  drawBrandWatermark(ctx, W, H)

  return c.toDataURL('image/png')
}

/**
 * Placa inferior con el nombre del personaje que salió en la ruleta (ej
 * "Chilli"). El cintillo superior lleva la TEMÁTICA (ej "BLUEY"); esta placa
 * lleva el personaje concreto que acompaña al invitado en su foto.
 */
function drawCharacterNamePlate(ctx, name, cx, centerY, W, maxTextWidthRatio = 0.6) {
  const label = String(name).trim()
  if (!label) return

  let fs = Math.round(W * 0.042)
  ctx.font = `800 ${fs}px 'Baloo 2', system-ui, sans-serif`
  while (ctx.measureText(label).width > W * maxTextWidthRatio && fs > 14) {
    fs -= 2
    ctx.font = `800 ${fs}px 'Baloo 2', system-ui, sans-serif`
  }

  const padX = W * 0.05
  const plateW = Math.min(W * (maxTextWidthRatio + 0.18), ctx.measureText(label).width + padX * 2)
  const plateH = fs * 1.9
  const left = cx - plateW / 2
  const top = centerY - plateH / 2
  const radius = plateH / 2

  ctx.save()
  ctx.shadowColor = 'rgba(0,0,0,0.35)'
  ctx.shadowBlur = W * 0.012
  ctx.shadowOffsetY = W * 0.004
  ctx.beginPath()
  // Pill: dos semicírculos y el cuerpo recto (compatible sin roundRect).
  ctx.moveTo(left + radius, top)
  ctx.lineTo(left + plateW - radius, top)
  ctx.arc(left + plateW - radius, top + radius, radius, -Math.PI / 2, Math.PI / 2)
  ctx.lineTo(left + radius, top + plateH)
  ctx.arc(left + radius, top + radius, radius, Math.PI / 2, -Math.PI / 2)
  ctx.closePath()
  const grad = ctx.createLinearGradient(left, top, left, top + plateH)
  grad.addColorStop(0, cssVar('--pink', '#e8000d'))
  grad.addColorStop(1, cssVar('--dark1', '#b30009'))
  ctx.fillStyle = grad
  ctx.fill()
  ctx.lineWidth = Math.max(2, W * 0.0035)
  ctx.strokeStyle = cssVar('--yellow', '#ffb800')
  ctx.stroke()
  ctx.restore()

  ctx.save()
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.font = `800 ${fs}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillStyle = '#fff'
  ctx.shadowColor = 'rgba(0,0,0,0.4)'
  ctx.shadowBlur = 6
  ctx.shadowOffsetY = 2
  ctx.fillText(label, cx, centerY + fs * 0.04)
  ctx.restore()
}

/**
 * Cintillo/placa con el nombre de la temática (ej "CARS"), centrado sobre
 * el borde superior del marco dorado — como una placa de trofeo, no compite
 * con el arco de globos ni el texto de agradecimiento debajo de la foto.
 */
function drawThemeRibbon(ctx, cx, frameTop, frameSide, W) {
  const ribbonW = frameSide * 0.74
  const ribbonH = frameSide * 0.135
  const left = cx - ribbonW / 2
  const top = frameTop - ribbonH / 2
  const notch = ribbonH * 0.4

  ctx.save()
  ctx.shadowColor = 'rgba(0,0,0,0.35)'
  ctx.shadowBlur = W * 0.012
  ctx.shadowOffsetY = W * 0.004

  // Cinta: rectángulo con muescas triangulares en los extremos.
  ctx.beginPath()
  ctx.moveTo(left, top)
  ctx.lineTo(left + ribbonW, top)
  ctx.lineTo(left + ribbonW - notch, top + ribbonH / 2)
  ctx.lineTo(left + ribbonW, top + ribbonH)
  ctx.lineTo(left, top + ribbonH)
  ctx.lineTo(left + notch, top + ribbonH / 2)
  ctx.closePath()
  const grad = ctx.createLinearGradient(left, top, left, top + ribbonH)
  grad.addColorStop(0, cssVar('--pink', '#e8000d'))
  grad.addColorStop(1, cssVar('--dark1', '#b30009'))
  ctx.fillStyle = grad
  ctx.fill()
  ctx.lineWidth = Math.max(2, W * 0.003)
  ctx.strokeStyle = cssVar('--yellow', '#ffb800')
  ctx.stroke()
  ctx.restore()

  ctx.save()
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  let fs = Math.round(ribbonH * 0.62)
  ctx.font = `800 ${fs}px 'Baloo 2', system-ui, sans-serif`
  while (ctx.measureText(THEME_LABEL).width > ribbonW * 0.82 && fs > 10) {
    fs -= 2
    ctx.font = `800 ${fs}px 'Baloo 2', system-ui, sans-serif`
  }
  ctx.fillStyle = cssVar('--yellow', '#ffb800')
  ctx.fillText(THEME_LABEL, cx, top + ribbonH / 2 + fs * 0.04)
  ctx.restore()
}

function drawBrandWatermark(ctx, W, H) {
  const logo = getBrandLogo()
  if (!logo) return

  // Isotipo CumpleClick oficial como marca de agua: sin tarjeta ni recreaciones.
  const markW = W * 0.085
  const markH = markW * (logo.naturalHeight / logo.naturalWidth)
  const x = W * 0.03
  const y = H - markH - H * 0.025

  ctx.save()
  ctx.globalAlpha = 0.42
  ctx.drawImage(logo, x, y, markW, markH)
  ctx.restore()
}

// dev: permite verificar el compositing sin webcam desde la consola
if (import.meta.env.DEV && typeof window !== 'undefined') {
  window.__composeImage = composeImage
}

/* ============================================================
   REVELACIÓN — suspenso opcional entre la captura y el resultado
   ("Cargando tu foto..."). Solo aparece si el tema publicó el video;
   ninguna temática sin él ve una pantalla nueva.
   ============================================================ */
function Revelacion({ invitado, onDone }) {
  const doneRef = useRef(null)
  const vRef = useRef(null)
  const [needsTap, setNeedsTap] = useState(false)

  const finish = useCallback(() => {
    if (doneRef.current) return
    doneRef.current = true
    onDone()
  }, [onDone])

  // Red de seguridad si el video no carga: no deja al invitado varado
  // mirando una pantalla que nunca avanza.
  useEffect(() => {
    const t = setTimeout(finish, REDUCE_MOTION ? 500 : 6000)
    return () => clearTimeout(t)
  }, [finish])

  // Igual que VideoPersonaje/PhotoSessionVideo: sin gesto reciente, Chrome
  // rechaza el autoplay con sonido (play() rejected, no dispara onError) y el
  // video queda congelado en silencio. Se ofrece un botón para reintentar con
  // un toque directo; el watchdog de arriba igual avanza aunque no se toque.
  const startPlayback = useCallback(() => {
    const v = vRef.current
    if (!v) return
    const promise = v.play()
    if (promise && typeof promise.catch === 'function') {
      promise.then(() => setNeedsTap(false)).catch(() => setNeedsTap(true))
    }
  }, [])

  useEffect(() => {
    if (REDUCE_MOTION || !REVELACION_VIDEO) return undefined
    startPlayback()
    return undefined
  }, [startPlayback])

  if (REDUCE_MOTION || !REVELACION_VIDEO) {
    finish()
    return null
  }

  return (
    <section className="screen revelacion">
      <video
        ref={vRef}
        className="revelacion-video"
        src={REVELACION_VIDEO}
        autoPlay
        muted={false}
        playsInline
        onEnded={finish}
        onError={finish}
      />
      {/* El nombre va en HTML, nunca horneado en el video: así sirve para
          cualquier invitado sin regenerar nada. */}
      <div className="revelacion-overlay">
        <p className="revelacion-texto">Cargando tu foto{invitado ? `, ${invitado}` : ''}...</p>
      </div>
      {needsTap && (
        <button className="photo-session-play" onClick={startPlayback}>
          🔊 Toca para escuchar
        </button>
      )}
    </section>
  )
}

function Preview({ photo, bgRef, invitado, personaje, onRetry, onSave }) {
  const [composed, setComposed] = useState(null)
  const confRef = useRef(null)

  useEffect(() => {
    if (!photo) return
    let cancelled = false
    let detachLoad = null
    const charName = personaje && personaje.name
    const p = new Image()
    p.onload = async () => {
      if (cancelled) return
      await Promise.all([ensureCanvasFonts(), preloadBrandLogo()])
      if (cancelled) return
      const draw = () => {
        if (cancelled) return
        setComposed(composeImage(bgRef.current, p, invitado, getCharPng(charName), charName))
      }
      draw()
      // el PNG del personaje puede seguir cargando (precargado desde el spinner):
      // si aún no está listo, recompone en cuanto termine, sin bloquear la vista inicial.
      const entry = charName && charPngCache[charName]
      if (entry && !entry.ready && !entry.failed) {
        const onLoad = () => !cancelled && draw()
        entry.img.addEventListener('load', onLoad, { once: true })
        detachLoad = () => entry.img.removeEventListener('load', onLoad)
      }
    }
    p.src = photo
    return () => {
      cancelled = true
      if (detachLoad) detachLoad()
    }
  }, [photo, bgRef, invitado, personaje])

  const save = () => {
    if (!composed) return
    const a = document.createElement('a')
    a.href = composed
    a.download = `foto-${invitado}-${Date.now()}.png`
    document.body.appendChild(a)
    a.click()
    a.remove()
    playSound(CONFIG.audio.confetti)
    burstConfetti(confRef.current, { duration: 2600, count: 180 })
    setTimeout(() => onSave(composed), REDUCE_MOTION ? 300 : 1600)
  }

  return (
    <section className="screen preview">
      {composed ? (
        <img className="preview-img" src={composed} alt="Tu foto en la fiesta" />
      ) : (
        <div className="loading">Preparando tu foto…</div>
      )}
      <div className="preview-bar">
        <button className="cta ghost" onClick={onRetry}>
          🔄 Otra foto
        </button>
        <button className="cta" onClick={save} disabled={!composed}>
          💾 Guardar
        </button>
      </div>
      <canvas ref={confRef} className="confetti-canvas" />
    </section>
  )
}

/* ============================================================
   QR Screen — genera QR code para descargar la foto en el celular
   ============================================================ */
async function uploadPhoto(imageDataUrl, invitado) {
  const res = await fetch(CONFIG.uploadEndpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ image: imageDataUrl, name: invitado || 'foto', party: PARTY_SLUG }),
  })
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    const error = new Error((data && data.error) || 'upload_failed')
    error.status = res.status
    throw error
  }
  if (!data || !data.url) throw new Error('no_url')
  return data.url
}

function uploadErrorMessage(error) {
  const code = error && error.message
  if (code === 'party_quota_exceeded' || code === 'quota_exceeded') {
    return 'La galería de esta fiesta alcanzó su límite. Tu foto sigue guardada en la tablet.'
  }
  if (code === 'rate_limited') {
    return 'Se subieron muchas fotos seguidas. Espera un momento y vuelve a intentarlo.'
  }
  if (code === 'inactive' || code === 'party_inactive') {
    return 'La galería de esta fiesta ya no está activa. Tu foto sigue guardada en la tablet.'
  }
  if (code === 'image_too_large' || code === 'too_big' || code === 'bad_image'
      || code === 'bad_base64' || code === 'png_required' || code === 'invalid_png'
      || code === 'invalid_dimensions') {
    return 'La foto no pudo validarse para la galería. Puedes conservar la descarga de la tablet.'
  }
  return 'No pudimos subir la foto. Revisa la conexión y vuelve a intentarlo; la descarga local no se perdió.'
}

function QRScreen({ imageDataUrl, invitado, isBabyShower = false, onDiploma, onDone }) {
  const [qrUrl, setQrUrl] = useState(null)
  const [mode, setMode] = useState('loading') // loading | ready | error
  const [errorText, setErrorText] = useState('')
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    if (!imageDataUrl) return
    let alive = true
    setMode('loading')
    setQrUrl(null)
    setErrorText('')

    const makeQR = (text, ecl = 'M') =>
      QRCode.toDataURL(text, {
        width: 320,
        margin: 2,
        errorCorrectionLevel: ecl,
        color: { dark: '#3a1f2b', light: '#ffffff' },
      })

    // Solo se muestra QR si el backend confirmó una URL pública real.
    uploadPhoto(imageDataUrl, invitado)
      .then((publicUrl) => makeQR(publicUrl, 'M'))
      .then((q) => {
        if (!alive) return
        setQrUrl(q)
        setMode('ready')
      })
      .catch((error) => {
        if (!alive) return
        setErrorText(uploadErrorMessage(error))
        setMode('error')
      })

    return () => {
      alive = false
    }
  }, [imageDataUrl, invitado, attempt])

  return (
    <section
      className="screen qr-screen"
      style={{ backgroundImage: `url(${CONFIG.images.fondo})` }}
    >
      <div className="qr-veil" />
      <div className="qr-content">
        <CumpleClickBrand />
        <h1 className="qr-brand">{isBabyShower ? CONFIG.nombre : `Fiesta de ${CONFIG.nombre}`}</h1>
        <h2 className="qr-title">{isBabyShower ? '¡Tu predicción está lista!' : '¡Tu foto está lista! 📸'}</h2>
        <p className="qr-sub">
          {mode === 'error'
            ? 'La descarga local está segura'
            : 'Escanéalo con tu celular para descargarla'}
        </p>
        <div className="qr-card">
          {qrUrl ? (
            <img className="qr-img" src={qrUrl} alt="Código QR de tu foto" />
          ) : mode === 'error' ? (
            <div className="qr-placeholder qr-placeholder--error" role="alert">
              <strong>No se generó un QR falso</strong>
              <span>{errorText}</span>
              <button className="qr-retry" onClick={() => setAttempt((value) => value + 1)}>
                Reintentar subida
              </button>
            </div>
          ) : (
            <div className="qr-placeholder">Generando QR…</div>
          )}
        </div>
        <div className="qr-actions">
          <button className="cta" onClick={onDiploma}>
            {isBabyShower ? 'Ver mi recuerdito' : '🎓 Ver diploma'}
          </button>
          <button className="cta ghost" onClick={onDone}>
            ✨ Siguiente invitado
          </button>
        </div>
      </div>
    </section>
  )
}

/* ============================================================
   6-B) DIPLOMA — segunda descarga tras la foto: diploma vertical
   premium, personalizado con el título de la temática (DIPLOMA).
   ============================================================ */
function cssVar(name, fallback) {
  try {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim()
    return v || fallback
  } catch {
    return fallback
  }
}

function formatFecha(iso) {
  if (!iso) return ''
  try {
    const d = new Date(iso + 'T00:00:00')
    if (isNaN(d.getTime())) return iso
    return d.toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' })
  } catch {
    return iso
  }
}

function roundRectPath(ctx, x, y, w, h, r) {
  ctx.beginPath()
  ctx.moveTo(x + r, y)
  ctx.arcTo(x + w, y, x + w, y + h, r)
  ctx.arcTo(x + w, y + h, x, y + h, r)
  ctx.arcTo(x, y + h, x, y, r)
  ctx.arcTo(x, y, x + w, y, r)
  ctx.closePath()
}

// Cubre un área sin deformar la ilustración vertical del personaje.
function drawImageCover(ctx, image, x, y, width, height) {
  const imageWidth = image.naturalWidth || image.width
  const imageHeight = image.naturalHeight || image.height
  if (!imageWidth || !imageHeight) return false
  const scale = Math.max(width / imageWidth, height / imageHeight)
  const drawnWidth = imageWidth * scale
  const drawnHeight = imageHeight * scale
  ctx.drawImage(image, x + (width - drawnWidth) / 2, y + (height - drawnHeight) / 2, drawnWidth, drawnHeight)
  return true
}

// Sello circular con estrella de 5 puntas — usado arriba y abajo del diploma.
function drawStarSeal(ctx, cx, cy, r, colorA, colorB) {
  ctx.save()
  const g = ctx.createRadialGradient(cx, cy, r * 0.1, cx, cy, r)
  g.addColorStop(0, colorB)
  g.addColorStop(1, colorA)
  ctx.shadowColor = 'rgba(0,0,0,0.25)'
  ctx.shadowBlur = r * 0.25
  ctx.shadowOffsetY = r * 0.08
  ctx.beginPath()
  ctx.arc(cx, cy, r, 0, Math.PI * 2)
  ctx.fillStyle = g
  ctx.fill()
  ctx.shadowColor = 'transparent'
  ctx.lineWidth = Math.max(1, r * 0.06)
  ctx.strokeStyle = 'rgba(255,255,255,0.85)'
  ctx.stroke()

  const spikes = 5
  const outerR = r * 0.6
  const innerR = outerR * 0.48
  let rot = -Math.PI / 2
  const step = Math.PI / spikes
  ctx.beginPath()
  ctx.moveTo(cx + Math.cos(rot) * outerR, cy + Math.sin(rot) * outerR)
  for (let i = 0; i < spikes; i++) {
    rot += step
    ctx.lineTo(cx + Math.cos(rot) * innerR, cy + Math.sin(rot) * innerR)
    rot += step
    ctx.lineTo(cx + Math.cos(rot) * outerR, cy + Math.sin(rot) * outerR)
  }
  ctx.closePath()
  ctx.fillStyle = 'rgba(255,255,255,0.92)'
  ctx.fill()
  ctx.restore()
}

// Genera el diploma vertical (9:16) en canvas, con la paleta de la temática
// activa (leída de las CSS vars ya aplicadas por applyThemeVars).
function composeRecuerdito(invitado = '', prediction = null, score = 0, background = null) {
  const W = 1080
  const H = 1920
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')
  const accent = cssVar('--pink', '#8c5de8')
  const accent2 = cssVar('--yellow', '#f0a9c8')
  const ink = cssVar('--dark1', '#302442')

  const gradient = ctx.createLinearGradient(0, 0, W, H)
  gradient.addColorStop(0, cssVar('--bg-light1', '#fff4f8'))
  gradient.addColorStop(1, cssVar('--bg-light2', '#efe9ff'))
  ctx.fillStyle = gradient
  ctx.fillRect(0, 0, W, H)
  if (background?.complete && (background.naturalWidth || background.width)) {
    drawImageCover(ctx, background, 0, 0, W, H)
    ctx.fillStyle = 'rgba(255,250,253,.76)'
    ctx.fillRect(0, 0, W, H)
  }

  const margin = W * 0.065
  ctx.strokeStyle = accent
  ctx.lineWidth = W * 0.012
  roundRectPath(ctx, margin, margin, W - margin * 2, H - margin * 2, W * 0.055)
  ctx.stroke()
  ctx.strokeStyle = 'rgba(255,255,255,.88)'
  ctx.lineWidth = W * 0.005
  roundRectPath(ctx, margin + 20, margin + 20, W - (margin + 20) * 2, H - (margin + 20) * 2, W * 0.045)
  ctx.stroke()

  drawStarSeal(ctx, W / 2, H * 0.13, W * 0.09, accent, accent2)
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillStyle = ink
  ctx.font = `800 ${Math.round(W * 0.105)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText('RECUERDITO', W / 2, H * 0.245)
  ctx.fillStyle = accent
  ctx.font = `800 ${Math.round(W * 0.065)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText(invitado || 'Invitado', W / 2, H * 0.33)
  ctx.fillStyle = ink
  ctx.font = `700 ${Math.round(W * 0.041)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText('Cronista del gran día', W / 2, H * 0.385)

  const labels = predictionLabels(prediction || {})
  const rows = [
    ['Se parecerá', labels.parecido],
    ['Pesará', labels.peso],
    ['Llegará', labels.fecha],
  ]
  rows.forEach(([label, value], index) => {
    const y = H * (0.49 + index * 0.105)
    ctx.fillStyle = 'rgba(255,255,255,.88)'
    roundRectPath(ctx, W * 0.14, y - H * 0.039, W * 0.72, H * 0.078, W * 0.035)
    ctx.fill()
    ctx.fillStyle = accent
    ctx.font = `700 ${Math.round(W * 0.029)}px 'Baloo 2', system-ui, sans-serif`
    ctx.fillText(label.toUpperCase(), W / 2, y - H * 0.012)
    ctx.fillStyle = ink
    ctx.font = `800 ${Math.round(W * 0.042)}px 'Baloo 2', system-ui, sans-serif`
    ctx.fillText(value || '—', W / 2, y + H * 0.016)
  })

  ctx.fillStyle = accent
  ctx.font = `800 ${Math.round(W * 0.052)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText(`${Number.isFinite(score) ? score : 0} puntos`, W / 2, H * 0.82)
  ctx.fillStyle = ink
  ctx.font = `600 ${Math.round(W * 0.033)}px 'Baloo 2', system-ui, sans-serif`
  ctx.fillText(`Una predicción para ${CONFIG.nombre}`, W / 2, H * 0.875)
  drawBrandWatermark(ctx, W, H)
  return canvas.toDataURL('image/png')
}

function composeDiploma(invitado = '', winnerImage = null) {
  const W = 1080
  const H = 1920
  const c = document.createElement('canvas')
  c.width = W
  c.height = H
  const ctx = c.getContext('2d')

  const bg1 = cssVar('--bg-light1', '#fff3d6')
  const bg2 = cssVar('--bg-light2', '#ffe0cc')
  const accent = cssVar('--pink', '#e8000d')
  const yellow = cssVar('--yellow', '#ffb800')
  const ink = cssVar('--ink', '#2b1a12')
  const dark1 = cssVar('--dark1', '#b30009')

  // Fondo degradado temático, usado como respaldo si falta la imagen del ganador.
  const bgGrad = ctx.createLinearGradient(0, 0, W, H)
  bgGrad.addColorStop(0, bg1)
  bgGrad.addColorStop(1, bg2)
  ctx.fillStyle = bgGrad
  ctx.fillRect(0, 0, W, H)

  // El personaje ganador de la ruleta protagoniza el Diploma. La veladura
  // cálida mantiene el texto legible sin esconder la escena de celebración.
  const hasWinnerBackground = winnerImage && winnerImage.complete && (winnerImage.naturalWidth || winnerImage.width)
  if (hasWinnerBackground) {
    ctx.save()
    drawImageCover(ctx, winnerImage, 0, 0, W, H)
    const posterVeil = ctx.createLinearGradient(0, 0, 0, H)
    posterVeil.addColorStop(0, 'rgba(255,250,239,0.68)')
    posterVeil.addColorStop(0.5, 'rgba(255,250,239,0.50)')
    posterVeil.addColorStop(1, 'rgba(255,248,232,0.36)')
    ctx.fillStyle = posterVeil
    ctx.fillRect(0, 0, W, H)
    ctx.restore()
  }

  // Confeti sutil de fondo (paleta de la temática)
  const rand = mulberry32(1234567)
  for (let i = 0; i < 46; i++) {
    const rx = rand() * W
    const ry = rand() * H
    const rr = 4 + rand() * 7
    ctx.save()
    ctx.globalAlpha = 0.16 + rand() * 0.12
    ctx.translate(rx, ry)
    ctx.rotate(rand() * Math.PI)
    ctx.fillStyle = CONFETTI_COLORS[(rand() * CONFETTI_COLORS.length) | 0]
    ctx.fillRect(-rr / 2, -rr / 2, rr, rr * 0.6)
    ctx.restore()
  }

  // Marca de agua de respaldo: con ganador se usa su ilustración completa.
  if (!hasWinnerBackground && GRUPO_IMG && GRUPO_IMG.complete && GRUPO_IMG.naturalWidth) {
    const imgAspect = GRUPO_IMG.naturalWidth / GRUPO_IMG.naturalHeight
    const maxW = W * 0.85
    const maxH = H * 0.65
    let gw, gh
    if (imgAspect > maxW / maxH) { gw = maxW; gh = gw / imgAspect }
    else { gh = maxH; gw = gh * imgAspect }
    ctx.save()
    ctx.globalAlpha = 0.12
    ctx.drawImage(GRUPO_IMG, (W - gw) / 2, (H - gh) / 2, gw, gh)
    ctx.restore()
  }

  // Marco decorativo dorado (doble línea)
  const pad = W * 0.055
  ctx.save()
  ctx.strokeStyle = '#c6922e'
  ctx.lineWidth = W * 0.012
  roundRectPath(ctx, pad, pad, W - pad * 2, H - pad * 2, W * 0.05)
  ctx.stroke()
  const pad2 = pad + W * 0.02
  ctx.strokeStyle = '#fbe7ab'
  ctx.lineWidth = W * 0.004
  roundRectPath(ctx, pad2, pad2, W - pad2 * 2, H - pad2 * 2, W * 0.044)
  ctx.stroke()
  ctx.restore()

  // Sello superior
  drawStarSeal(ctx, W / 2, pad + H * 0.075, W * 0.085, accent, yellow)

  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'

  // Diploma infantil: relleno amarillo y contorno rojo de alto contraste,
  // legible tanto en pantalla como al descargar/imprimir el recuerdo.
  const drawDiplomaText = (text, x, y, strokeWidth = W * 0.007) => {
    ctx.save()
    ctx.lineJoin = 'round'
    ctx.miterLimit = 2
    ctx.strokeStyle = accent
    ctx.lineWidth = strokeWidth
    ctx.strokeText(text, x, y)
    ctx.fillStyle = yellow
    ctx.fillText(text, x, y)
    ctx.restore()
  }

  // Título "DIPLOMA"
  ctx.font = `800 ${Math.round(W * 0.145)}px 'Baloo 2', system-ui, sans-serif`
  ctx.shadowColor = 'rgba(0,0,0,0.18)'
  ctx.shadowBlur = W * 0.01
  ctx.shadowOffsetY = W * 0.006
  drawDiplomaText('DIPLOMA', W / 2, H * 0.235, W * 0.009)
  ctx.shadowColor = 'transparent'

  // "Se otorga a"
  ctx.font = `700 ${Math.round(W * 0.047)}px 'Baloo 2', system-ui, sans-serif`
  drawDiplomaText('Se otorga a', W / 2, H * 0.3, W * 0.0045)

  // Nombre del invitado (destacado, shrink-to-fit)
  const nameText = invitado || 'Invitado'
  let fsName = Math.round(W * 0.105)
  ctx.font = `800 ${fsName}px 'Baloo 2', system-ui, sans-serif`
  while (ctx.measureText(nameText).width > W * 0.82 && fsName > 30) {
    fsName -= 2
    ctx.font = `800 ${fsName}px 'Baloo 2', system-ui, sans-serif`
  }
  drawDiplomaText(nameText, W / 2, H * 0.375, W * 0.007)

  // Línea decorativa dorada bajo el nombre
  ctx.strokeStyle = yellow
  ctx.lineWidth = W * 0.006
  ctx.lineCap = 'round'
  ctx.beginPath()
  ctx.moveTo(W * 0.28, H * 0.4)
  ctx.lineTo(W * 0.72, H * 0.4)
  ctx.stroke()

  // Título honorífico de la temática (theme.diploma), ej "Piloto Oficial del Equipo"
  const diplomaTitle = DIPLOMA || ''
  if (diplomaTitle) {
    let fsTitle = Math.round(W * 0.064)
    ctx.font = `800 ${fsTitle}px 'Baloo 2', system-ui, sans-serif`
    while (ctx.measureText(diplomaTitle).width > W * 0.8 && fsTitle > 22) {
      fsTitle -= 2
      ctx.font = `800 ${fsTitle}px 'Baloo 2', system-ui, sans-serif`
    }
    drawDiplomaText(diplomaTitle, W / 2, H * 0.465, W * 0.0055)
  }

  // "en la fiesta de {nombre} · {fecha si está}"
  const fecha = formatFecha(CONFIG && CONFIG.fecha)
  const fiestaLine = CONFIG
    ? `en ${eventoFraseEn()}${fecha ? ' · ' + fecha : ''}`
    : ''
  if (fiestaLine) {
    let fsFiesta = Math.round(W * 0.043)
    ctx.font = `600 ${fsFiesta}px 'Baloo 2', system-ui, sans-serif`
    while (ctx.measureText(fiestaLine).width > W * 0.84 && fsFiesta > 16) {
      fsFiesta -= 2
      ctx.font = `600 ${fsFiesta}px 'Baloo 2', system-ui, sans-serif`
    }
    drawDiplomaText(fiestaLine, W / 2, H * 0.58, W * 0.004)
  }

  // Sello inferior + agradecimiento final
  drawStarSeal(ctx, W / 2, H * 0.8, W * 0.105, dark1, yellow)
  ctx.font = `700 ${Math.round(W * 0.035)}px 'Baloo 2', system-ui, sans-serif`
  drawDiplomaText('¡Gracias por celebrar con nosotros!', W / 2, H * 0.895, W * 0.0035)

  drawBrandWatermark(ctx, W, H)

  return c.toDataURL('image/png')
}

// PRNG determinístico simple (mismo confeti de fondo en cada render del diploma)
function mulberry32(seed) {
  let a = seed
  return function () {
    a |= 0
    a = (a + 0x6d2b79f5) | 0
    let t = Math.imul(a ^ (a >>> 15), 1 | a)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

// dev: permite verificar el diploma sin pasar por todo el flujo
if (import.meta.env.DEV && typeof window !== 'undefined') {
  window.__composeDiploma = composeDiploma
}

function DiplomaScreen({ invitado, personaje, prediction = null, score = 0, onDone }) {
  const isBabyShower = CONFIG.eventType === 'baby_shower'
  const [diplomaUrl, setDiplomaUrl] = useState(null)
  // El QR del diploma se genera recién al descargar: sube el PNG y muestra el
  // enlace público, igual que la foto (QRScreen). 'idle' = aún no lo pidió.
  const [qrUrl, setQrUrl] = useState(null)
  const [qrMode, setQrMode] = useState('idle') // idle | loading | ready | error
  const [qrError, setQrError] = useState('')
  const aliveRef = useRef(true)

  useEffect(() => {
    let alive = true
    const render = (winnerImage = null) => {
      if (!alive) return
      setDiplomaUrl(isBabyShower
        ? composeRecuerdito(invitado, prediction, score, winnerImage)
        : composeDiploma(invitado, winnerImage))
    }

    Promise.all([ensureCanvasFonts(), preloadBrandLogo()]).then(() => {
      if (!alive) return
      const winnerSrc = isBabyShower ? CONFIG.images.fondo : (personaje && CHAR_IMG[personaje.name])
      if (!winnerSrc) {
        render()
        return
      }
      const winnerImage = new Image()
      winnerImage.onload = () => render(winnerImage)
      winnerImage.onerror = () => render()
      winnerImage.src = winnerSrc
    })
    return () => {
      alive = false
    }
  }, [invitado, personaje, isBabyShower, prediction, score])

  useEffect(() => () => { aliveRef.current = false }, [])

  // Sube el diploma y arma el QR. Se llama tras la descarga local; si falla,
  // la descarga ya ocurrió y el mensaje lo deja explícito (no se inventa un QR).
  const publishQr = useCallback(() => {
    if (!diplomaUrl) return
    setQrMode('loading')
    setQrUrl(null)
    setQrError('')
    uploadPhoto(diplomaUrl, `${isBabyShower ? 'recuerdito' : 'diploma'}-${invitado || 'invitado'}`)
      .then((publicUrl) =>
        QRCode.toDataURL(publicUrl, {
          width: 320,
          margin: 2,
          errorCorrectionLevel: 'M',
          color: { dark: '#3a1f2b', light: '#ffffff' },
        })
      )
      .then((q) => {
        if (!aliveRef.current) return
        setQrUrl(q)
        setQrMode('ready')
      })
      .catch((error) => {
        if (!aliveRef.current) return
        setQrError(uploadErrorMessage(error))
        setQrMode('error')
      })
  }, [diplomaUrl, invitado, isBabyShower])

  const download = () => {
    if (!diplomaUrl) return
    const a = document.createElement('a')
    a.href = diplomaUrl
    a.download = `${isBabyShower ? 'recuerdito' : 'diploma'}-${invitado}-${Date.now()}.png`
    document.body.appendChild(a)
    a.click()
    a.remove()
    publishQr()
  }

  return (
    <section className="screen diploma-screen">
      <div className={`diploma-content${qrMode === 'idle' ? '' : ' diploma-content--with-qr'}`}>
        {diplomaUrl ? (
          <img className="diploma-img" src={diplomaUrl} alt={`${isBabyShower ? 'Recuerdito' : 'Diploma'} de ${invitado}`} />
        ) : (
          <div className="loading">Preparando tu {isBabyShower ? 'recuerdito' : 'diploma'}…</div>
        )}

        {qrMode !== 'idle' && (
          <div className="diploma-qr">
            {qrMode === 'ready' && qrUrl ? (
              <>
                <img className="diploma-qr__img" src={qrUrl} alt={`Código QR del ${isBabyShower ? 'recuerdito' : 'diploma'}`} />
                <span className="diploma-qr__hint">Escanéalo para bajarlo a otro celular</span>
              </>
            ) : qrMode === 'error' ? (
              <div className="diploma-qr__msg diploma-qr__msg--error" role="alert">
                <strong>{isBabyShower ? 'Recuerdito' : 'Diploma'} descargado en la tablet</strong>
                <span>{qrError}</span>
                <button className="qr-retry" onClick={publishQr}>
                  Reintentar QR
                </button>
              </div>
            ) : (
              <div className="diploma-qr__msg">Generando QR…</div>
            )}
          </div>
        )}

        <div className="diploma-bar">
          <button className="cta" onClick={download} disabled={!diplomaUrl}>
            💾 Descargar {isBabyShower ? 'recuerdito' : 'diploma'}
          </button>
          <button className="cta ghost" onClick={onDone}>
            ✨ Siguiente invitado
          </button>
        </div>
      </div>
    </section>
  )
}
