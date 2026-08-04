import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { supports3D } from './ToyTrack3D.jsx'
import { createAudioKit } from './gameAudio.js'
import { guardarRecord, textoRecord } from './records.js'

/* ============================================================
   EL SHOW — Concierto 3D (misión del plan Full)

   Reemplaza a ThemeWorld3D (el runner de 3 carriles) como misión Full en las
   temáticas completas. Acá el juego NO es esquivar: es tocar al ritmo. Todo
   el escenario reacciona al beat — pantallas LED, cañones de luz, público con
   lightsticks, confeti, pirotecnia — y el personaje que sacó el invitado en
   la ruleta es la estrella arriba del escenario.

   UN solo juego reskineado por temática vía SHOW_STYLES (ver abajo): cambia
   el vestuario, nunca la mecánica ni la dificultad.

   Decisiones que importan si alguien retoma esto:

   - EL RELOJ ES performance.now(), NO audioCtx.currentTime. Lo natural en un
     juego de ritmo es el reloj de audio, y así estaba: el show se CONGELABA
     entero cuando el navegador dejaba el AudioContext en 'suspended' (no
     bajaba el cronómetro ni llegaba ninguna nota). Ahora el audio se ANCLA al
     reloj de juego apenas arranca de verdad; si nunca arranca, el juego sigue
     jugable pero mudo.

   - LA MÚSICA LA HACE EL JUEGO. La base (bombo/clap/hats/bajo/riff) está
     sintetizada acá con WebAudio, no es un mp3. Así el chart SIEMPRE cae en
     el beat exacto sin tener que medir BPM ni alinear fase de un archivo que
     además viene sonando desde antes (App reanuda la música donde quedó).
     Cero assets nuevos, cero derechos de autor.

   - PRESUPUESTO DE DRAW CALLS. El público son 2 InstancedMesh (siluetas +
     lightsticks) = 2 draw calls para ~540 objetos. Los haces de luz son conos
     con blending aditivo, NO SpotLight con sombras. Sin post-procesado.
     devicePixelRatio tapado a 1.35. Objetivo: 60fps en tablet Android media.

   - VENTANAS GENEROSAS. ±170ms perfecto / ±330ms bien. Un niño de 6 años no
     tiene precisión de milisegundos y fallar no debe doler: un fallo corta la
     racha pero no resta puntos.

   - LIMPIEZA TOTAL en el cleanup. Este componente se monta y desmonta una vez
     POR INVITADO durante horas. Un contexto WebGL o un AudioContext filtrado
     acá tumba el kiosco a mitad de fiesta.
   ============================================================ */

const BPM = 112
const BEAT = 60 / BPM
const EIGHTH = BEAT / 2

/* El kiosco es 9:16. Con FOV vertical 62° el campo HORIZONTAL es de apenas
   ~32°: la mitad visible a distancia d es solo d*0.29. Por eso los carriles
   son estrechos (±1.15) y la línea de acierto está lejos de la cámara — con
   carriles anchos los aros de los extremos se salían del encuadre justo en el
   momento de tocarlos. Estos números están calzados para que los tres aros
   caigan sobre los tres botones táctiles de abajo. */
const LANE_X = [-1.15, 0, 1.15]

const HIT_Z = 0.3          // línea de acierto, al borde del escenario
const SPAWN_Z = -8.8       // las notas nacen contra la pantalla LED
const NOTE_Y = 1.78        // altura de vuelo de las notas y de los aros
const APPROACH = 1.85      // segundos que tarda una nota en llegar

const W_PERFECT = 0.17
const W_GOOD = 0.33
const LEAD_IN = 2.6        // compás y medio de intro antes de la primera nota

const STAR_COMBO = 8       // racha que enciende MODO ESTRELLA

/* ── Escenarios por temática ────────────────────────────────────────────
   El Show es UN juego reskineado, no seis juegos. Lo que cambia por
   temática es el vestuario del escenario: paleta de los tres carriles,
   cielo, materiales y el nombre del show. La mecánica, el chart, el tempo
   y las ventanas de acierto son IGUALES para todos — así los puntajes se
   pueden comparar entre hermanos aunque les toquen temáticas distintas.

   `lanes` son los tres colores de carril y mandan en todo: notas, aros,
   botones táctiles, lightsticks del público, focos y haces. Tienen que ser
   tres colores CLARAMENTE distintos entre sí (no tres azules): el niño
   identifica el carril por color antes que por posición.
   ───────────────────────────────────────────────────────────────────── */
const SHOW_STYLES = {
  'neon-arena': {
    title: 'El Show',
    lanes: ['#ff2e93', '#00e5ff', '#ffd54f'],
    sky: ['#2a0a52', '#07021a'],
    fog: '#12053a',
    stage: '#0d0620',
    riser: '#120720',
    truss: '#3b3168',
  },
  'ice-gala': {
    title: 'El Show de Hielo',
    lanes: ['#7ad7ff', '#c9a7ff', '#fff2a8'],
    sky: ['#0d3f70', '#03101f'],
    fog: '#0a2c50',
    stage: '#0a1c2e',
    riser: '#0b1d30',
    truss: '#3d5a7a',
  },
  'beach-luau': {
    title: 'El Show de la Playa',
    lanes: ['#ff7a3d', '#22e0c8', '#ffe14f'],
    sky: ['#1f6f8f', '#06202c'],
    fog: '#0d4257',
    stage: '#12301f',
    riser: '#123024',
    truss: '#3f6a4a',
  },
  'podium-night': {
    title: 'El Show del Circuito',
    lanes: ['#ff3b30', '#ffffff', '#ffb800'],
    sky: ['#2b1208', '#0a0503'],
    fog: '#231008',
    stage: '#16110e',
    riser: '#1a1210',
    truss: '#5a4636',
  },
  'backyard-fiesta': {
    title: 'El Show del Patio',
    lanes: ['#ff9f1c', '#2ec4ff', '#a4e34a'],
    sky: ['#1c5a86', '#071a2a'],
    fog: '#123c5c',
    stage: '#0f2436',
    riser: '#102638',
    truss: '#3f6485',
  },
  'rooftop-city': {
    title: 'El Show de Héroes',
    lanes: ['#e53935', '#4db8ff', '#ffd54f'],
    sky: ['#123a6e', '#040c1c'],
    fog: '#0a2246',
    stage: '#101a2c',
    riser: '#111c2e',
    truss: '#39527e',
  },
}

export const SHOW_STAGES = Object.keys(SHOW_STYLES)

function showStyle(stage) {
  return SHOW_STYLES[stage] || SHOW_STYLES['neon-arena']
}

/* ── Chart ──────────────────────────────────────────────────────────────
   Generado, no escrito a mano, pero DETERMINISTA (LCG con semilla fija):
   todos los invitados de la fiesta juegan exactamente la misma canción, así
   los hermanos pueden competir por puntaje. Arranca solo en negras y va
   metiendo corcheas de a poco — dificultad que sube sola sin config.
   ─────────────────────────────────────────────────────────────────────── */
function buildChart(showSeconds) {
  const notes = []
  let seed = 20260801
  const rnd = () => {
    seed = (seed * 1664525 + 1013904223) >>> 0
    return seed / 4294967296
  }
  let lane = 1
  const steps = Math.max(1, Math.floor(showSeconds / EIGHTH))
  for (let step = 0; step < steps; step += 1) {
    const bar = Math.floor(step / 8)
    const onBeat = step % 2 === 0
    let hit = onBeat
    if (!onBeat && bar >= 2 && rnd() < Math.min(0.4, 0.1 + bar * 0.055)) hit = true
    if (!hit) continue
    if (notes.length) {
      const move = rnd() < 0.6 ? (rnd() < 0.5 ? -1 : 1) : 0
      lane = Math.max(0, Math.min(2, lane + move))
    }
    notes.push({ t: LEAD_IN + step * EIGHTH, lane, done: false, mesh: null })
  }
  return notes
}

/* ── Texturas procedurales ──────────────────────────────────────────────
   Canvas 2D en vez de PNG: son cuatro texturas chicas, generarlas cuesta
   menos que otra petición HTTP y encima se tiñen por temática sin exportar
   variantes.
   ─────────────────────────────────────────────────────────────────────── */
function makeGridTexture(THREE, hexA, hexB, bg) {
  const c = document.createElement('canvas')
  c.width = 128
  c.height = 256
  const g = c.getContext('2d')
  g.fillStyle = bg
  g.fillRect(0, 0, 128, 256)
  g.lineWidth = 3
  for (let y = 0; y < 256; y += 16) {
    g.strokeStyle = (y / 16) % 2 ? hexA : hexB
    g.globalAlpha = 0.55
    g.beginPath()
    g.moveTo(0, y)
    g.lineTo(128, y)
    g.stroke()
  }
  g.globalAlpha = 0.32
  for (let x = 0; x < 128; x += 16) {
    g.strokeStyle = hexB
    g.beginPath()
    g.moveTo(x, 0)
    g.lineTo(x, 256)
    g.stroke()
  }
  const t = new THREE.CanvasTexture(c)
  t.wrapS = THREE.RepeatWrapping
  t.wrapT = THREE.RepeatWrapping
  return t
}

function makeFadeTexture(THREE) {
  const c = document.createElement('canvas')
  c.width = 8
  c.height = 128
  const g = c.getContext('2d')
  const grad = g.createLinearGradient(0, 0, 0, 128)
  grad.addColorStop(0, 'rgba(255,255,255,0)')
  grad.addColorStop(0.55, 'rgba(255,255,255,0.28)')
  grad.addColorStop(1, 'rgba(255,255,255,0.95)')
  g.fillStyle = grad
  g.fillRect(0, 0, 8, 128)
  return new THREE.CanvasTexture(c)
}

/* Cielo dibujado por código para las temáticas que NO tienen una foto de
   fondo propia. No es un placeholder feo: degradado del color de la temática
   + neblina de luces, que es justo lo que se ve arriba de un escenario. Una
   foto real de arena queda mejor, pero esto evita tener que generar (y pagar)
   una imagen por temática solo para que el fondo no sea negro. */
function makeSkyTexture(THREE, top, bottom) {
  const c = document.createElement('canvas')
  c.width = 64
  c.height = 128
  const g = c.getContext('2d')
  const grad = g.createLinearGradient(0, 0, 0, 128)
  grad.addColorStop(0, bottom)
  grad.addColorStop(0.55, top)
  grad.addColorStop(1, top)
  g.fillStyle = grad
  g.fillRect(0, 0, 64, 128)
  g.globalAlpha = 0.5
  for (let i = 0; i < 90; i += 1) {
    const y = Math.random() * 78
    g.fillStyle = i % 3 ? '#ffffff' : '#ffe9a8'
    g.fillRect(Math.random() * 64, y, 1, 1)
  }
  const t = new THREE.CanvasTexture(c)
  t.colorSpace = THREE.SRGBColorSpace
  return t
}

/* Caja (en UV 0..1) que ocupa realmente el contenido opaco de un recorte.

   Los `*-cut.png` se exportaron con el encuadre 9:16 del retrato original, no
   ajustados a la silueta: el de Rayo, por ejemplo, trae el auto solo entre el
   64% y el 93% del alto y el resto es aire transparente. Plantado tal cual
   sobre el pedestal, el personaje sale diminuto y descentrado hacia abajo.
   Midiendo la caja se puede encuadrar el sprite en el contenido real en vez
   de en el lienzo.

   Se mide sobre una copia reducida (256 px de ancho) porque solo interesan
   los bordes, no la precisión de píxel: son ~115k muestras una sola vez al
   cargar, frente al millón del asset completo. */
function measureOpaqueBox(image) {
  if (!image?.width || !image?.height) return null
  const w = Math.min(256, image.width)
  const h = Math.max(1, Math.round((w * image.height) / image.width))
  const c = document.createElement('canvas')
  c.width = w
  c.height = h
  const g = c.getContext('2d', { willReadFrequently: true })
  if (!g) return null
  g.drawImage(image, 0, 0, w, h)
  let data
  // Un asset servido desde otro origen sin CORS deja el canvas "tainted" y
  // getImageData lanza. En ese caso se sigue con el encuadre completo de
  // siempre, que es exactamente el comportamiento previo.
  try {
    data = g.getImageData(0, 0, w, h).data
  } catch {
    return null
  }
  let minX = w
  let maxX = -1
  let minY = h
  let maxY = -1
  for (let y = 0; y < h; y += 1) {
    for (let x = 0; x < w; x += 1) {
      // 12/255: el mismo criterio laxo que alphaTest — los bordes del recorte
      // traen un halo de alfa muy bajo que no debe agrandar la caja.
      if (data[(y * w + x) * 4 + 3] <= 12) continue
      if (x < minX) minX = x
      if (x > maxX) maxX = x
      if (y < minY) minY = y
      if (y > maxY) maxY = y
    }
  }
  if (maxX < 0) return null
  return {
    left: minX / w,
    right: (maxX + 1) / w,
    top: minY / h,
    bottom: (maxY + 1) / h,
  }
}

function makeSparkTexture(THREE) {
  const c = document.createElement('canvas')
  c.width = 64
  c.height = 64
  const g = c.getContext('2d')
  const grad = g.createRadialGradient(32, 32, 0, 32, 32, 32)
  grad.addColorStop(0, 'rgba(255,255,255,1)')
  grad.addColorStop(0.35, 'rgba(255,255,255,0.75)')
  grad.addColorStop(1, 'rgba(255,255,255,0)')
  g.fillStyle = grad
  g.fillRect(0, 0, 64, 64)
  return new THREE.CanvasTexture(c)
}

export default function StageConcert3D({
  config,
  charSrc,
  runnerAtlasSrc,
  bannerSrc,
  bannerFallbackSrc,
  charTrim = false,
  invitado,
  personaje,
  onDone,
}) {
  const mountRef = useRef(null)
  const apiRef = useRef(null)
  const onDoneRef = useRef(onDone)

  const [ready, setReady] = useState(false)
  const [phase, setPhase] = useState('intro')
  const [score, setScore] = useState(0)
  const [combo, setCombo] = useState(0)
  const [best, setBest] = useState(0)
  const [hits, setHits] = useState(0)
  const [starMode, setStarMode] = useState(false)
  const [remaining, setRemaining] = useState(config.seconds || 20)
  const [judge, setJudge] = useState(null)
  const [unavailable, setUnavailable] = useState(false)
  const [esRecord, setEsRecord] = useState(false)
  // Récord de la fiesta para este juego, leído al montar: es la marca a batir.
  const [record] = useState(() => textoRecord('concierto3d'))

  const showSeconds = Math.max(14, config.seconds || 20)
  // Vestuario del escenario según la temática. LANE_HEX manda en notas, aros,
  // botones, público y luces: sale de acá, no de una constante global, para
  // que el mismo juego se vea propio de cada mundo.
  const style = showStyle(config.stage)
  const LANE_HEX = style.lanes

  useEffect(() => {
    onDoneRef.current = onDone
  }, [onDone])

  useEffect(() => {
    if (!supports3D()) {
      setUnavailable(true)
      return undefined
    }
    const mount = mountRef.current
    if (!mount) return undefined

    let disposed = false
    let raf = 0
    let renderer = null
    let scene = null
    let resizeObserver = null
    let kit = null
    let visibility = null
    const judgeTimers = new Set()

    const chart = buildChart(showSeconds)
    const endsAt = LEAD_IN + showSeconds + 0.9

    const st = {
      phase: 'intro',
      t0: 0,
      now: 0,
      audioAnchor: 0,   // audioCtx.currentTime que corresponde a t = 0
      audioReady: false,
      audioStep: 0,
      score: 0,
      combo: 0,
      best: 0,
      hits: 0,
      star: false,
      beatIndex: -1,
      beatPulse: 0,
      flash: 0,
      shake: 0,
      lastSecond: -1,
      next: 0, // primera nota aún no juzgada
    }

    import('three')
      .then((THREE) => {
        if (disposed) return

        /* ── Render ───────────────────────────────────────────────── */
        renderer = new THREE.WebGLRenderer({
          antialias: true,
          alpha: false,
          powerPreference: 'low-power',
        })
        renderer.outputColorSpace = THREE.SRGBColorSpace
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.35))
        renderer.setSize(Math.max(1, mount.clientWidth), Math.max(1, mount.clientHeight))
        renderer.shadowMap.enabled = false
        renderer.domElement.setAttribute('aria-hidden', 'true')
        mount.appendChild(renderer.domElement)

        scene = new THREE.Scene()
        // Cielo de la temática por defecto. Si además hay foto propia de
        // fondo, la reemplaza abajo — nunca queda un fondo negro.
        scene.background = makeSkyTexture(THREE, style.sky[0], style.sky[1])
        scene.fog = new THREE.Fog(style.fog, 16, 42)

        const camera = new THREE.PerspectiveCamera(
          62,
          Math.max(1, mount.clientWidth) / Math.max(1, mount.clientHeight),
          0.1,
          80
        )
        // Altura de ojos de alguien parado en el foso, mirando LEVEMENTE hacia
        // arriba al escenario. Las notas NO van por el piso: vuelan a la altura
        // de la cara (NOTE_Y). Con la cámara mirando hacia abajo para ver un
        // carril en el suelo, el público de primer plano quedaba fuera de
        // cuadro y se perdía todo el punto de estar en un concierto.
        camera.position.set(0, 2.1, 6.2)

        // Una foto real de arena/paisaje cierra el fondo mucho mejor que el
        // cielo procedural. Opcional: si la temática no la tiene, se queda con
        // el degradado y el show se ve igual de bien.
        if (config.image) {
          new THREE.TextureLoader().load(
            config.image,
            (texture) => {
              if (disposed) {
                texture.dispose()
                return
              }
              texture.colorSpace = THREE.SRGBColorSpace
              const previous = scene.background
              scene.background = texture
              // Las fotos de fondo son de día (la nieve de Hielo, la ciudad de
              // Héroes) y a brillo pleno se comen el escenario: quedaba una
              // franja clarísima arriba y todo oscuro abajo. Bajarlas las manda
              // al fondo, que es donde tienen que estar.
              scene.backgroundIntensity = 0.55
              if (previous?.isTexture) previous.dispose()
            },
            undefined,
            () => {}
          )
        }

        const gridTex = makeGridTexture(THREE, LANE_HEX[0], LANE_HEX[1], style.sky[1])
        const fadeTex = makeFadeTexture(THREE)
        const sparkTex = makeSparkTexture(THREE)

        /* ── Luces ────────────────────────────────────────────────── */
        scene.add(new THREE.HemisphereLight('#b48cff', '#0a0424', 0.75))
        const keyLight = new THREE.DirectionalLight('#ffffff', 1.15)
        keyLight.position.set(0, 7, 2)
        scene.add(keyLight)
        const washL = new THREE.PointLight(LANE_HEX[0], 4.2, 26)
        washL.position.set(-4.4, 4.2, -3)
        scene.add(washL)
        const washR = new THREE.PointLight(LANE_HEX[1], 4.2, 26)
        washR.position.set(4.4, 4.2, -3)
        scene.add(washR)
        const starLight = new THREE.PointLight('#ffffff', 2.6, 16)
        starLight.position.set(0, 4.6, -5.4)
        scene.add(starLight)

        /* ── Escenario ────────────────────────────────────────────── */
        // Tapa metálica y poco rugosa a propósito: así los point lights
        // rosados/cyan se leen como reflejos de piso mojado de concierto.
        const stage = new THREE.Mesh(
          new THREE.BoxGeometry(10.4, 1.3, 13),
          new THREE.MeshStandardMaterial({
            color: style.stage,
            roughness: 0.26,
            metalness: 0.72,
          })
        )
        stage.position.set(0, -0.65, -3.6)
        scene.add(stage)

        const lip = new THREE.Mesh(
          new THREE.BoxGeometry(10.4, 0.16, 0.16),
          new THREE.MeshBasicMaterial({ color: LANE_HEX[0] })
        )
        lip.position.set(0, -0.04, 2.9)
        scene.add(lip)

        // Pantalla LED central: el banner real de la temática, como en un
        // concierto de verdad donde la pantalla muestra a la banda.
        const screenMat = new THREE.MeshBasicMaterial({ color: '#8f8f8f' })
        const screen = new THREE.Mesh(new THREE.PlaneGeometry(3.7, 6.4), screenMat)
        screen.position.set(0, 3.5, -9.7)
        scene.add(screen)
        /* Sin textura la pantalla queda como un rectángulo gris plano, así que
           si el asset propio de la temática falta en el servidor se reintenta
           con el de bienvenida antes de resignarse. Importa porque los assets
           se suben por FTP a mano y una tanda incompleta no debe dejar un
           hueco gris en mitad del escenario. */
        const loadBanner = (src, fallback) => {
          if (!src) return
          new THREE.TextureLoader().load(
            src,
            (texture) => {
              if (disposed) {
                texture.dispose()
                return
              }
              texture.colorSpace = THREE.SRGBColorSpace
              screenMat.map = texture
              screenMat.needsUpdate = true
            },
            undefined,
            () => {
              if (!disposed && fallback && fallback !== src) loadBanner(fallback, null)
            }
          )
        }
        loadBanner(bannerSrc, bannerFallbackSrc)

        const screenFrame = new THREE.Mesh(
          new THREE.PlaneGeometry(4.05, 6.75),
          new THREE.MeshBasicMaterial({ color: LANE_HEX[1] })
        )
        screenFrame.position.set(0, 3.5, -9.78)
        scene.add(screenFrame)

        const sidePanels = []
        for (const side of [-1, 1]) {
          const panel = new THREE.Mesh(
            new THREE.PlaneGeometry(3, 5.6),
            new THREE.MeshBasicMaterial({ map: gridTex.clone(), toneMapped: false })
          )
          panel.material.map.needsUpdate = true
          panel.position.set(side * 3.9, 3.2, -9.3)
          panel.rotation.y = -side * 0.3
          scene.add(panel)
          sidePanels.push(panel)
        }

        // Truss + torres
        // Clara y fina: en oscuro y gruesa leía como una barra negra cruzando
        // el cuadro en vez de estructura de escenario.
        const trussMat = new THREE.MeshStandardMaterial({
          color: style.truss,
          roughness: 0.45,
          metalness: 0.7,
          emissive: '#241a44',
          emissiveIntensity: 0.5,
        })
        const truss = new THREE.Mesh(new THREE.BoxGeometry(11.6, 0.12, 0.12), trussMat)
        truss.position.set(0, 7.55, -7.6)
        scene.add(truss)
        for (const side of [-1, 1]) {
          const tower = new THREE.Mesh(new THREE.BoxGeometry(0.2, 8, 0.2), trussMat)
          tower.position.set(side * 5.5, 3.5, -7.6)
          scene.add(tower)
        }

        /* ── Cañones de luz ───────────────────────────────────────────
           Conos aditivos, NO SpotLight. Un SpotLight por haz con sombras
           costaría más que toda la escena junta y en una tablet se nota.
           ─────────────────────────────────────────────────────────── */
        const beamGeo = new THREE.ConeGeometry(0.9, 13, 14, 1, true)
        beamGeo.translate(0, -6.5, 0) // vértice en el origen: el foco pivotea desde ahí
        const beams = []
        for (let i = 0; i < 6; i += 1) {
          const beam = new THREE.Mesh(
            beamGeo,
            new THREE.MeshBasicMaterial({
              color: LANE_HEX[i % LANE_HEX.length],
              transparent: true,
              opacity: 0.14,
              blending: THREE.AdditiveBlending,
              depthWrite: false,
              side: THREE.DoubleSide,
              toneMapped: false,
            })
          )
          beam.position.set(-4.6 + i * 1.84, 7.4, -7.5)
          beam.userData.phase = i * 0.7
          beam.userData.dir = i % 2 ? 1 : -1
          scene.add(beam)
          beams.push(beam)
        }

        /* ── Público ──────────────────────────────────────────────────
           Dos InstancedMesh = 2 draw calls para ~540 objetos. Con Mesh
           sueltos serían 540 draw calls y la tablet se cae sola.
           ─────────────────────────────────────────────────────────── */
        /* El público va en las DOS ESQUINAS delanteras, no repartido por todo
           el foso. Motivo geométrico: con este encuadre vertical la mitad
           visible a distancia d es d*0.29, así que gente a |x| > 3 cerca de la
           cámara cae fuera de cuadro y gente en el centro taparía las notas.
           Esta cuña (|x| 1.5–3.4, z 0–5) es la única zona donde el público
           SE VE y además no estorba: entra por las esquinas de abajo, que es
           exactamente como se ve un concierto desde el foso. */
        const crowdX = () => (Math.random() < 0.5 ? -1 : 1) * (1.45 + Math.random() * 1.6)
        const crowdZ = () => 0.4 + Math.random() * 4.2

        // Poca cantidad a propósito: en esta cuña la mayoría queda fuera de
        // cuadro igual, y un InstancedMesh no se recorta por instancia (se
        // dibujan todas o ninguna). 90+120 alcanza para el efecto y no se
        // paga por gente que nadie ve.
        const CROWD = 90
        const crowd = new THREE.InstancedMesh(
          new THREE.CapsuleGeometry(0.23, 0.62, 3, 7),
          new THREE.MeshBasicMaterial({ color: '#04010f' }),
          CROWD
        )
        crowd.instanceMatrix.setUsage(THREE.DynamicDrawUsage)
        const crowdSeed = new Float32Array(CROWD * 4)
        for (let i = 0; i < CROWD; i += 1) {
          crowdSeed[i * 4] = crowdX()
          crowdSeed[i * 4 + 1] = crowdZ()
          crowdSeed[i * 4 + 2] = Math.random() * Math.PI * 2     // fase
          crowdSeed[i * 4 + 3] = 0.86 + Math.random() * 0.3      // escala
        }
        scene.add(crowd)

        const STICKS = 120
        const sticks = new THREE.InstancedMesh(
          new THREE.BoxGeometry(0.045, 0.4, 0.045),
          new THREE.MeshBasicMaterial({
            transparent: true,
            opacity: 0.95,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            toneMapped: false,
          }),
          STICKS
        )
        sticks.instanceMatrix.setUsage(THREE.DynamicDrawUsage)
        const stickSeed = new Float32Array(STICKS * 3)
        const tmpColor = new THREE.Color()
        for (let i = 0; i < STICKS; i += 1) {
          stickSeed[i * 3] = crowdX()
          stickSeed[i * 3 + 1] = crowdZ()
          stickSeed[i * 3 + 2] = Math.random() * Math.PI * 2
          tmpColor.set(LANE_HEX[i % LANE_HEX.length])
          sticks.setColorAt(i, tmpColor)
        }
        if (sticks.instanceColor) sticks.instanceColor.needsUpdate = true
        scene.add(sticks)

        /* ── Carriles y pads ──────────────────────────────────────── */
        const highways = []
        const pads = []
        for (let i = 0; i < 3; i += 1) {
          const highway = new THREE.Mesh(
            new THREE.PlaneGeometry(0.82, 9.4),
            new THREE.MeshBasicMaterial({
              map: fadeTex,
              color: LANE_HEX[i],
              transparent: true,
              opacity: 0.42,
              blending: THREE.AdditiveBlending,
              depthWrite: false,
              toneMapped: false,
            })
          )
          highway.rotation.x = -Math.PI / 2
          highway.position.set(LANE_X[i], 0.02, HIT_Z - 4.7)
          scene.add(highway)
          highways.push(highway)

          const pad = new THREE.Mesh(
            new THREE.TorusGeometry(0.46, 0.06, 8, 26),
            new THREE.MeshBasicMaterial({
              color: LANE_HEX[i],
              transparent: true,
              opacity: 0.75,
              toneMapped: false,
            })
          )
          pad.position.set(LANE_X[i], NOTE_Y, HIT_Z)
          scene.add(pad)
          pads.push(pad)
        }

        /* ── Pool de notas ────────────────────────────────────────────
           A lo sumo APPROACH/EIGHTH ≈ 7 notas en vuelo; 14 da margen de
           sobra y evita crear/destruir geometría en pleno juego.
           ─────────────────────────────────────────────────────────── */
        const noteRing = new THREE.TorusGeometry(0.34, 0.085, 8, 22)
        const noteDisc = new THREE.CircleGeometry(0.27, 20)
        const pool = []
        for (let i = 0; i < 14; i += 1) {
          const group = new THREE.Group()
          const ring = new THREE.Mesh(
            noteRing,
            new THREE.MeshBasicMaterial({ color: '#ffffff', transparent: true, toneMapped: false })
          )
          const disc = new THREE.Mesh(
            noteDisc,
            new THREE.MeshBasicMaterial({
              color: '#ffffff',
              transparent: true,
              opacity: 0.5,
              blending: THREE.AdditiveBlending,
              depthWrite: false,
              toneMapped: false,
            })
          )
          group.add(ring)
          group.add(disc)
          group.visible = false
          group.userData = { ring, disc, free: true }
          scene.add(group)
          pool.push(group)
        }
        const takeNote = () => pool.find((n) => n.userData.free) || null
        const freeNote = (group) => {
          group.visible = false
          group.userData.free = true
        }

        /* ── La estrella ──────────────────────────────────────────── */
        /* La estrella va sobre una plataforma ALTA (2.1) por dos razones: queda
           por encima del vuelo de las notas (NOTE_Y = 1.78), así nunca se
           cruzan, y sube la mirada del jugador hacia el personaje en vez de
           dejarlo perdido a media pantalla contra el banner. */
        const RISER_TOP = 2.1
        const riser = new THREE.Mesh(
          new THREE.CylinderGeometry(1.15, 1.35, RISER_TOP, 22),
          // Mate y oscuro: con metalness alto los focos rosa/cyan lo volvían
          // un cilindro rosa brillante justo detrás del aro central, y ya no
          // se distinguía qué era escenografía y qué había que tocar.
          new THREE.MeshStandardMaterial({
            color: style.riser,
            roughness: 0.85,
            metalness: 0.15,
          })
        )
        riser.position.set(0, RISER_TOP / 2, -7.4)
        scene.add(riser)
        const riserRing = new THREE.Mesh(
          new THREE.TorusGeometry(1.2, 0.06, 8, 32),
          new THREE.MeshBasicMaterial({ color: LANE_HEX[2], toneMapped: false })
        )
        riserRing.rotation.x = -Math.PI / 2
        riserRing.position.set(0, RISER_TOP + 0.02, -7.4)
        scene.add(riserRing)

        // Halo detrás del personaje. Sin esto el recorte se confunde con el
        // banner de la pantalla LED, que tiene otras cuatro figuras parecidas:
        // el halo recorta la silueta y deja claro quién es la estrella.
        const halo = new THREE.Mesh(
          new THREE.CircleGeometry(2.1, 36),
          new THREE.MeshBasicMaterial({
            map: sparkTex,
            color: LANE_HEX[1],
            transparent: true,
            opacity: 0.75,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            toneMapped: false,
          })
        )
        halo.position.set(0, RISER_TOP + 1.6, -7.9)
        scene.add(halo)

        const performer = new THREE.Group()
        performer.position.set(0, RISER_TOP, -7.4)
        scene.add(performer)

        let starTexture = null
        let starUsesAtlas = false
        let starFrame = ''
        const ATLAS_FRAMES = { front: [0, 0.5], right: [0.5, 0.5], back: [0, 0], left: [0.5, 0] }
        const setStarFrame = (frame) => {
          if (!starTexture || !starUsesAtlas || frame === starFrame) return
          const [u, v] = ATLAS_FRAMES[frame] || ATLAS_FRAMES.front
          starTexture.offset.set(u, v)
          starFrame = frame
        }
        const STAR_H = 3.3       // alto del sprite con el encuadre completo del asset
        const STAR_TRIM_W = 3.2  // ancho al que se lleva la silueta ya recortada
        const loadStar = (src, atlas, onError) => {
          if (!src) {
            onError()
            return
          }
          new THREE.TextureLoader().load(
            src,
            (texture) => {
              if (disposed) {
                texture.dispose()
                return
              }
              texture.colorSpace = THREE.SRGBColorSpace
              texture.wrapS = THREE.ClampToEdgeWrapping
              texture.wrapT = THREE.ClampToEdgeWrapping
              const aspect = texture.image?.width / texture.image?.height || 0.9
              let planeH = STAR_H
              let planeW = STAR_H * aspect
              let trimmed = false
              if (atlas) {
                texture.repeat.set(0.5, 0.5)
                texture.offset.set(0, 0.5)
              } else if (charTrim) {
                /* Encuadre en la silueta real, no en el lienzo del asset. Sin
                   esto el recorte de Rayo (65% de aire transparente arriba)
                   dejaba al auto diminuto y hundido contra el pedestal, que es
                   justamente lo que se veía como "Rayo chico". Se limita por
                   ancho porque estos personajes son anchos y bajos: llevarlos
                   al alto completo los sacaría del encuadre vertical. */
                const box = measureOpaqueBox(texture.image)
                if (box) {
                  const rw = box.right - box.left
                  const rh = box.bottom - box.top
                  if (rw > 0 && rh > 0) {
                    texture.repeat.set(rw, rh)
                    // El origen UV está abajo-izquierda; `top`/`bottom` se
                    // miden desde arriba, de ahí el 1 - bottom.
                    texture.offset.set(box.left, 1 - box.bottom)
                    const contentAspect = aspect * (rw / rh)
                    planeH = Math.min(STAR_H, STAR_TRIM_W / contentAspect)
                    planeW = planeH * contentAspect
                    trimmed = true
                  }
                }
              }
              const sprite = new THREE.Mesh(
                new THREE.PlaneGeometry(planeW, planeH),
                new THREE.MeshBasicMaterial({
                  map: texture,
                  transparent: true,
                  alphaTest: 0.04,
                  side: THREE.DoubleSide,
                  depthWrite: false,
                })
              )
              sprite.position.y = planeH / 2
              // El halo existe para recortar la silueta contra el fondo: si el
              // sprite cambia de alto hay que recentrarlo, o queda flotando por
              // encima de la estrella en vez de detrás.
              if (trimmed) halo.position.y = RISER_TOP + planeH * 0.55
              starTexture = texture
              starUsesAtlas = !!atlas
              starFrame = atlas ? 'front' : ''
              performer.add(sprite)
            },
            undefined,
            onError
          )
        }
        if (runnerAtlasSrc) loadStar(runnerAtlasSrc, true, () => loadStar(charSrc, false, () => {}))
        else loadStar(charSrc, false, () => {})

        /* ── Partículas (confeti + pirotecnia) ────────────────────────
           Un solo Points de 320 con pool manual. Las muertas se mandan a
           y = -999 en vez de reconstruir el buffer.
           ─────────────────────────────────────────────────────────── */
        const PMAX = 320
        const pPos = new Float32Array(PMAX * 3)
        const pCol = new Float32Array(PMAX * 3)
        const pVel = new Float32Array(PMAX * 3)
        const pLife = new Float32Array(PMAX)
        for (let i = 0; i < PMAX; i += 1) pPos[i * 3 + 1] = -999
        const pGeo = new THREE.BufferGeometry()
        pGeo.setAttribute('position', new THREE.BufferAttribute(pPos, 3))
        pGeo.setAttribute('color', new THREE.BufferAttribute(pCol, 3))
        const particles = new THREE.Points(
          pGeo,
          new THREE.PointsMaterial({
            map: sparkTex,
            size: 0.13,
            vertexColors: true,
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending,
            sizeAttenuation: true,
            toneMapped: false,
          })
        )
        scene.add(particles)
        let pCursor = 0
        const emit = (count, x, y, z, spread, up, hex) => {
          tmpColor.set(hex)
          for (let i = 0; i < count; i += 1) {
            const k = pCursor
            pCursor = (pCursor + 1) % PMAX
            pPos[k * 3] = x + (Math.random() - 0.5) * 0.5
            pPos[k * 3 + 1] = y
            pPos[k * 3 + 2] = z + (Math.random() - 0.5) * 0.5
            pVel[k * 3] = (Math.random() - 0.5) * spread
            pVel[k * 3 + 1] = up * (0.55 + Math.random() * 0.9)
            pVel[k * 3 + 2] = (Math.random() - 0.5) * spread
            pLife[k] = 1
            pCol[k * 3] = tmpColor.r
            pCol[k * 3 + 1] = tmpColor.g
            pCol[k * 3 + 2] = tmpColor.b
          }
        }

        /* ── Juicio ───────────────────────────────────────────────── */
        const showJudge = (kind, text) => {
          setJudge({ kind, text, id: Math.random() })
          const timer = window.setTimeout(() => {
            judgeTimers.delete(timer)
            if (!disposed) setJudge(null)
          }, 520)
          judgeTimers.add(timer)
        }

        const breakCombo = () => {
          st.combo = 0
          if (st.star) {
            st.star = false
            setStarMode(false)
          }
          setCombo(0)
        }

        const tap = (lane) => {
          if (st.phase !== 'playing') return
          // Segunda oportunidad de desbloquear el audio: en algunas tablets el
          // gesto del botón de intro no alcanza y sí lo hace el primer toque
          // dentro del juego.
          if (kit && kit.ctx.state === 'suspended') kit.resume()
          const t = st.now
          let target = null
          let bestDist = Infinity
          for (let i = st.next; i < chart.length; i += 1) {
            const note = chart[i]
            if (note.t > t + W_GOOD) break
            if (note.done || note.lane !== lane) continue
            const dist = Math.abs(note.t - t)
            if (dist < bestDist) {
              bestDist = dist
              target = note
            }
          }
          const audioNow = kit ? kit.ctx.currentTime : 0
          if (!target || bestDist > W_GOOD) {
            st.shake = 0.35
            breakCombo()
            showJudge('miss', '¡Ups!')
            if (kit) kit.miss(audioNow)
            return
          }
          target.done = true
          if (target.mesh) {
            freeNote(target.mesh)
            target.mesh = null
          }
          const perfect = bestDist <= W_PERFECT
          st.hits += 1
          st.combo += 1
          st.best = Math.max(st.best, st.combo)
          const base = perfect ? 100 : 50
          st.score += st.star ? base * 2 : base
          setScore(st.score)
          setCombo(st.combo)
          setHits(st.hits)
          st.flash = perfect ? 0.55 : 0.28
          st.beatPulse = 1

          emit(perfect ? 16 : 8, LANE_X[lane], NOTE_Y, HIT_Z, 2.2, 1.5, LANE_HEX[lane])
          if (kit) {
            if (perfect) kit.perfect(audioNow, st.combo)
            else kit.good(audioNow)
          }
          showJudge(perfect ? 'perfect' : 'good', perfect ? '¡PERFECTO!' : '¡Bien!')
          if (navigator.vibrate) navigator.vibrate(perfect ? 16 : 10)

          if (!st.star && st.combo >= STAR_COMBO) {
            st.star = true
            setStarMode(true)
            if (kit) kit.cheer(audioNow)
            for (const side of [-1, 1]) {
              emit(26, side * 4.2, 0.2, -2.5, 1.1, 4.4, '#ffd54f')
            }
          }
        }

        const start = () => {
          if (kit) kit.resume()
          // El tiempo de juego SIEMPRE sale de performance.now(). Antes salía
          // de audioCtx.currentTime y eso congelaba el show entero cuando el
          // navegador dejaba el contexto en 'suspended' (política de autoplay):
          // el reloj no avanzaba, no bajaba el cronómetro y no llegaba ninguna
          // nota. Ahora el audio se ANCLA a este reloj apenas arranca de
          // verdad — si nunca arranca, el juego sigue perfectamente jugable,
          // solo que mudo.
          st.t0 = performance.now() / 1000
          st.audioReady = false
          st.audioAnchor = 0
          st.phase = 'playing'
          st.audioStep = 0
          st.next = 0
          st.score = 0
          st.combo = 0
          st.best = 0
          st.hits = 0
          st.star = false
          st.lastSecond = -1
          setPhase('playing')
          setScore(0)
          setCombo(0)
          setHits(0)
          setStarMode(false)
          setRemaining(showSeconds)
        }

        kit = createAudioKit()
        apiRef.current = { start, tap }
        setReady(true)

        // Handle de depuración SOLO en dev (mismo criterio que
        // window.__composeImage en App.jsx). Sin esto, averiguar por qué un
        // objeto no aparece en cuadro es adivinar a ciegas: acá se puede
        // inspeccionar la escena viva desde la consola o desde Playwright.
        if (import.meta.env.DEV && typeof window !== 'undefined') {
          window.__show3d = { scene, camera, performer, chart, st, renderer }
        }

        /* ── Loop ─────────────────────────────────────────────────── */
        const dummy = new THREE.Object3D()
        const stickColor = new THREE.Color()
        let lastFrame = performance.now()

        const animate = (rafNow) => {
          if (disposed) return
          const dt = Math.min(0.05, Math.max(0.001, (rafNow - lastFrame) / 1000))
          lastFrame = rafNow
          const playing = st.phase === 'playing'
          st.now = playing ? performance.now() / 1000 - st.t0 : 0
          const t = st.now

          /* Scheduler de batería: 0.25s de lookahead, tiempos en el reloj del
             AudioContext (sample-accurate) pero ANCLADOS al reloj de juego.
             Los pasos que quedaron atrás mientras el contexto estaba
             suspendido se descartan, no se disparan todos juntos. */
          if (playing && kit && kit.ctx.state === 'running') {
            if (!st.audioReady) {
              st.audioReady = true
              st.audioAnchor = kit.ctx.currentTime - t
              st.audioStep = Math.max(st.audioStep, Math.ceil(t / EIGHTH))
            }
            const horizon = t + 0.25
            while (st.audioStep * EIGHTH < horizon) {
              const when = st.audioAnchor + st.audioStep * EIGHTH
              if (when >= kit.ctx.currentTime) kit.step(when, st.audioStep)
              st.audioStep += 1
            }
          }

          // Beat visual (sirve también en modo mudo)
          const beatIndex = Math.floor(t / BEAT)
          if (playing && beatIndex !== st.beatIndex) {
            st.beatIndex = beatIndex
            st.beatPulse = Math.max(st.beatPulse, 0.85)
          }
          st.beatPulse = Math.max(0, st.beatPulse - dt * 3.4)
          st.flash = Math.max(0, st.flash - dt * 2.6)
          st.shake = Math.max(0, st.shake - dt * 2.2)
          const pulse = st.beatPulse
          const gold = st.star

          if (playing) {
            const second = Math.max(0, Math.ceil(LEAD_IN + showSeconds - t))
            if (second !== st.lastSecond) {
              st.lastSecond = second
              setRemaining(second)
            }
            // Notas que pasaron de largo sin tocarse
            while (st.next < chart.length && chart[st.next].t < t - W_GOOD) {
              const missed = chart[st.next]
              if (!missed.done) {
                missed.done = true
                if (missed.mesh) {
                  freeNote(missed.mesh)
                  missed.mesh = null
                }
                breakCombo()
              }
              st.next += 1
            }
            if (t >= endsAt) {
              st.phase = 'result'
              setPhase('result')
              setBest(st.best)
              setEsRecord(guardarRecord('concierto3d', st.score, invitado, 'mayor'))
              setRemaining(0)
              pool.forEach(freeNote)
              if (kit) kit.cheer(kit.ctx.currentTime)
              emit(120, 0, 7.5, -4, 7, 0.2, '#ffd54f')
            }
          }

          /* Notas en vuelo */
          if (playing) {
            for (let i = st.next; i < chart.length; i += 1) {
              const note = chart[i]
              const dtToHit = note.t - t
              if (dtToHit > APPROACH) break
              if (note.done) continue
              if (!note.mesh) {
                const mesh = takeNote()
                if (!mesh) continue
                mesh.userData.free = false
                mesh.visible = true
                mesh.userData.ring.material.color.set(LANE_HEX[note.lane])
                mesh.userData.disc.material.color.set(LANE_HEX[note.lane])
                note.mesh = mesh
              }
              // p se CLAVA en 1: una nota que ya pasó su tiempo se queda en la
              // línea y se desvanece. Sin este tope seguía viajando hacia la
              // cámara durante la ventana de gracia y crecía hasta tapar media
              // pantalla — con dos o tres seguidas no se veía nada más.
              const raw = 1 - dtToHit / APPROACH
              const p = Math.min(1, raw)
              const past = Math.max(0, raw - 1) // 0..~0.18 en la ventana de gracia
              note.mesh.position.set(
                LANE_X[note.lane],
                NOTE_Y + Math.sin(t * 3 + i) * 0.05,
                SPAWN_Z + (HIT_Z - SPAWN_Z) * p
              )
              note.mesh.rotation.z = t * 1.6 + i
              const near = Math.max(0, (p - 0.72) / 0.28)
              const fade = Math.max(0, 1 - past * 6)
              note.mesh.scale.setScalar((0.7 + p * 0.3) * (0.6 + fade * 0.4))
              note.mesh.userData.ring.material.opacity = fade
              note.mesh.userData.disc.material.opacity = (0.25 + near * 0.5) * fade
            }
          }

          /* Pads y carriles. El pad es el BLANCO (tenue, fijo) y la nota es lo
             que hay que tocar (brillante, en movimiento): si los dos brillan
             igual el niño no sabe cuál mirar. */
          for (let i = 0; i < 3; i += 1) {
            pads[i].scale.setScalar(1 + pulse * 0.16)
            pads[i].material.opacity = 0.42 + pulse * 0.26
            highways[i].material.opacity = 0.28 + pulse * 0.22 + (gold ? 0.18 : 0)
            if (gold) highways[i].material.color.set('#ffd54f')
            else highways[i].material.color.set(LANE_HEX[i])
          }

          /* Pantallas. Nivel base bajo a propósito: la pantalla LED es fondo,
             no protagonista — a brillo pleno se comía al personaje. */
          const screenLevel = 0.34 + pulse * 0.3
          if (gold) screenMat.color.setRGB(screenLevel, screenLevel * 0.86, screenLevel * 0.45)
          else screenMat.color.setScalar(screenLevel)
          screenFrame.material.color.set(gold ? '#ffd54f' : LANE_HEX[1])
          sidePanels.forEach((panel, i) => {
            const map = panel.material.map
            map.offset.y = (t * (i ? 0.2 : -0.2)) % 1
            panel.material.color.setScalar(0.5 + pulse * 0.5)
          })

          /* Haces */
          beams.forEach((beam, i) => {
            const ph = t * 0.9 + beam.userData.phase
            beam.rotation.z = Math.sin(ph) * 0.42 * beam.userData.dir
            beam.rotation.x = 0.28 + Math.cos(ph * 0.7) * 0.14
            beam.material.opacity = 0.15 + pulse * 0.2
            if (gold) beam.material.color.set(i % 2 ? '#ffd54f' : '#fff3b0')
            else beam.material.color.set(LANE_HEX[(i + beatIndex) % LANE_HEX.length])
          })
          washL.intensity = 3 + pulse * 3.4
          washR.intensity = 3 + pulse * 3.4
          if (gold) {
            washL.color.set('#ffd54f')
            washR.color.set('#ffe9a8')
          } else {
            washL.color.set(LANE_HEX[0])
            washR.color.set(LANE_HEX[1])
          }
          starLight.intensity = 2.2 + pulse * 1.8 + st.flash * 3

          /* Público: salto en el beat + ola que recorre el estadio */
          for (let i = 0; i < CROWD; i += 1) {
            const x = crowdSeed[i * 4]
            const z = crowdSeed[i * 4 + 1]
            const ph = crowdSeed[i * 4 + 2]
            const sc = crowdSeed[i * 4 + 3]
            const jump = Math.abs(Math.sin(t * Math.PI / BEAT + ph * 0.3)) * (0.16 + pulse * 0.16)
            dummy.position.set(x, -0.15 + jump, z)
            dummy.rotation.set(0, 0, Math.sin(t * 1.4 + ph) * 0.08)
            dummy.scale.set(sc, sc, sc)
            dummy.updateMatrix()
            crowd.setMatrixAt(i, dummy.matrix)
          }
          crowd.instanceMatrix.needsUpdate = true

          for (let i = 0; i < STICKS; i += 1) {
            const x = stickSeed[i * 3]
            const z = stickSeed[i * 3 + 1]
            const ph = stickSeed[i * 3 + 2]
            // La "ola" es una función de x: el estadio se mueve en bloque,
            // no cada persona por su cuenta. Eso es lo que lo hace leer
            // como público real y no como ruido.
            const wave = Math.sin(t * 2.4 + x * 0.42 + ph * 0.2)
            const jump = Math.abs(Math.sin(t * Math.PI / BEAT + ph * 0.2)) * (0.12 + pulse * 0.2)
            dummy.position.set(x, 0.85 + jump, z)
            dummy.rotation.set(0, 0, wave * 0.5)
            dummy.scale.set(1, 1 + pulse * 0.18, 1)
            dummy.updateMatrix()
            sticks.setMatrixAt(i, dummy.matrix)
            if (gold && i % 3 === 0) {
              stickColor.set('#ffd54f')
              sticks.setColorAt(i, stickColor)
            }
          }
          sticks.instanceMatrix.needsUpdate = true
          if (gold && sticks.instanceColor) sticks.instanceColor.needsUpdate = true

          /* La estrella baila: salto en negras, pose que cambia en el
             compás y giro completo en modo estrella. */
          const bounce = Math.abs(Math.sin(t * Math.PI / BEAT))
          performer.position.y = RISER_TOP + bounce * (playing ? 0.2 : 0.07)
          halo.scale.setScalar(1 + pulse * 0.12)
          halo.material.opacity = 0.5 + pulse * 0.35
          halo.material.color.set(gold ? '#ffd54f' : LANE_HEX[1])
          performer.scale.set(1 + (1 - bounce) * 0.05, 1 - (1 - bounce) * 0.06, 1)
          if (starUsesAtlas) {
            if (!playing) setStarFrame('front')
            else setStarFrame(['front', 'right', 'front', 'left'][beatIndex % 4])
          }
          performer.rotation.y = gold
            ? t * 2.2
            : Math.sin(t * 0.9) * 0.18
          riserRing.material.color.set(gold ? '#ffd54f' : LANE_HEX[2])
          riserRing.scale.setScalar(1 + pulse * 0.06)

          /* Partículas */
          let anyAlive = false
          for (let i = 0; i < PMAX; i += 1) {
            if (pLife[i] <= 0) continue
            anyAlive = true
            pLife[i] -= dt * 0.55
            pVel[i * 3 + 1] -= dt * 3.1
            pPos[i * 3] += pVel[i * 3] * dt
            pPos[i * 3 + 1] += pVel[i * 3 + 1] * dt
            pPos[i * 3 + 2] += pVel[i * 3 + 2] * dt
            if (pLife[i] <= 0 || pPos[i * 3 + 1] < -2.4) {
              pLife[i] = 0
              pPos[i * 3 + 1] = -999
            }
          }
          if (anyAlive) {
            pGeo.attributes.position.needsUpdate = true
            pGeo.attributes.color.needsUpdate = true
          }

          /* Cámara: grúa desde el público en la intro, plano fijo con
             respiración durante el juego, órbita lenta en el resultado. */
          let camX = 0
          let camY = 2.1
          let camZ = 6.2
          let lookY = 2.4
          if (st.phase === 'intro') {
            // Grúa lenta desde el foso: entra el estadio antes que el juego.
            const s = (rafNow / 1000) % 14
            camX = Math.sin(s * 0.26) * 1.2
            camY = 1.7 + Math.sin(s * 0.36) * 0.3
            camZ = 7
            lookY = 3
          } else if (st.phase === 'result') {
            const s = rafNow / 1000
            camX = Math.sin(s * 0.35) * 3
            camZ = 3.4 + Math.cos(s * 0.35) * 2
            camY = 2.5
            lookY = 3
          } else {
            camX = Math.sin(t * 0.5) * 0.16 + (st.shake ? Math.sin(rafNow * 0.06) * st.shake * 0.28 : 0)
            camY = 2.1 + pulse * 0.05
            camZ = 6.2 - pulse * 0.12 - (gold ? 0.3 : 0)
          }
          camera.position.set(camX, camY, camZ)
          camera.lookAt(0, lookY, -5)

          renderer.render(scene, camera)
          raf = requestAnimationFrame(animate)
        }
        raf = requestAnimationFrame(animate)

        /* ── Resize / visibilidad ─────────────────────────────────── */
        const resize = () => {
          if (!renderer || !mount) return
          const width = Math.max(1, mount.clientWidth)
          const height = Math.max(1, mount.clientHeight)
          camera.aspect = width / height
          camera.updateProjectionMatrix()
          renderer.setSize(width, height)
        }
        if (typeof ResizeObserver !== 'undefined') {
          resizeObserver = new ResizeObserver(resize)
          resizeObserver.observe(mount)
        } else {
          window.addEventListener('resize', resize)
          resizeObserver = { disconnect: () => window.removeEventListener('resize', resize) }
        }

        // Si la tablet se bloquea a mitad de canción no tiene sentido
        // "recuperar" el tiempo: se corta el show y se muestra el resultado.
        visibility = () => {
          if (document.hidden && st.phase === 'playing') {
            st.phase = 'result'
            setPhase('result')
            setBest(st.best)
            pool.forEach(freeNote)
          }
        }
        document.addEventListener('visibilitychange', visibility)
      })
      .catch(() => {
        if (!disposed) setUnavailable(true)
      })

    return () => {
      disposed = true
      if (raf) cancelAnimationFrame(raf)
      judgeTimers.forEach((timer) => window.clearTimeout(timer))
      judgeTimers.clear()
      if (resizeObserver) resizeObserver.disconnect()
      if (visibility) document.removeEventListener('visibilitychange', visibility)
      if (kit) kit.close()
      if (scene) {
        scene.traverse((object) => {
          if (object.geometry) object.geometry.dispose()
          if (object.material) {
            const materials = Array.isArray(object.material) ? object.material : [object.material]
            materials.forEach((material) => {
              if (material.map) material.map.dispose()
              material.dispose()
            })
          }
        })
        if (scene.background?.isTexture) scene.background.dispose()
      }
      apiRef.current = null
      if (import.meta.env.DEV && typeof window !== 'undefined') delete window.__show3d
      if (renderer) {
        renderer.dispose()
        renderer.forceContextLoss()
        if (renderer.domElement.parentNode) {
          renderer.domElement.parentNode.removeChild(renderer.domElement)
        }
      }
    }
  }, [
    bannerSrc,
    bannerFallbackSrc,
    charSrc,
    charTrim,
    config.image,
    config.stage,
    runnerAtlasSrc,
    showSeconds,
  ])

  const start = useCallback(() => apiRef.current?.start(), [])
  const tap = useCallback((lane) => apiRef.current?.tap(lane), [])

  // Tocar el canvas también cuenta: el tercio de pantalla que tocaste es tu
  // carril. Los botones de abajo son el blanco accesible garantizado, pero un
  // niño va a tocar la nota que ve, no el botón.
  const canvasTap = (event) => {
    if (phase !== 'playing') return
    const rect = event.currentTarget.getBoundingClientRect()
    const rel = (event.clientX - rect.left) / rect.width
    tap(rel < 1 / 3 ? 0 : rel < 2 / 3 ? 1 : 2)
  }

  // El chart es determinista: se calcula una vez para poder mostrar precisión
  // en el resultado sin volver a generarlo en cada render del HUD.
  const totalNotes = useMemo(() => Math.max(1, buildChart(showSeconds).length), [showSeconds])
  const accuracy = hits / totalNotes
  const stars = accuracy >= 0.7 ? 3 : accuracy >= 0.4 ? 2 : 1
  const hype = Math.min(100, Math.round((combo / STAR_COMBO) * 100))

  return (
    <section
      className={`screen juego juego--show3d${starMode ? ' is-star' : ''}`}
      tabIndex={0}
      onKeyDown={(event) => {
        if (event.key === 'ArrowLeft' || event.key === '1') tap(0)
        if (event.key === 'ArrowDown' || event.key === '2' || event.key === ' ') tap(1)
        if (event.key === 'ArrowRight' || event.key === '3') tap(2)
      }}
    >
      <div ref={mountRef} className="show3d-canvas" onPointerDown={canvasTap} />
      <div className="show3d-vignette" />

      {/* El rótulo de plan es marketing para el adulto, no información de
          juego. Solo en la intro: jugando estorbaba al puntaje y al botón
          Saltar, y en el resultado el puntaje de 5 dígitos se lo comía. */}
      {phase === 'intro' && <div className="show3d-brand">FULL · CONCIERTO 3D</div>}

      <div className="show3d-hud" aria-live="polite">
        <span className="show3d-score">👏 {score}</span>
        <span>⏱ {remaining}</span>
        {record && <span className="show3d-record">🏆 {record}</span>}
      </div>

      {phase === 'playing' && (
        <>
          <div className="show3d-hype" aria-hidden="true">
            <div className="show3d-hype__bar" style={{ width: `${hype}%` }} />
            <span>{starMode ? '★ MODO ESTRELLA' : 'HYPE'}</span>
          </div>
          {combo >= 2 && <div className="show3d-combo">x{combo}</div>}
        </>
      )}

      {judge && (
        <div key={judge.id} className={`show3d-judge show3d-judge--${judge.kind}`}>
          {judge.text}
        </div>
      )}

      {phase === 'intro' && !unavailable && (
        <div className="show3d-card show3d-card--intro">
          <p className="show3d-kicker">El escenario es tuyo, {invitado || 'estrella'}</p>
          <h2>{style.title}</h2>
          <p>Toca cada nota cuando llegue al aro.</p>
          <button className="show3d-start" onClick={start} disabled={!ready}>
            {ready ? '¡Que empiece el show! 🎤' : 'Encendiendo luces…'}
          </button>
        </div>
      )}

      {phase === 'playing' && (
        <div className="show3d-lanes" aria-label="Carriles">
          {LANE_HEX.map((hex, i) => (
            <button
              key={hex}
              style={{ '--lane': hex }}
              onPointerDown={(event) => {
                event.stopPropagation()
                tap(i)
              }}
              aria-label={`Tocar carril ${i + 1}`}
            />
          ))}
        </div>
      )}

      {phase === 'result' && (
        <div className="show3d-card show3d-card--result">
          {esRecord && <p className="juego-record-nuevo">🏆 ¡NUEVO RÉCORD DE LA FIESTA!</p>}
          <div className="show3d-stars" aria-label={`${stars} estrellas`}>
            {'★'.repeat(stars)}
            {'☆'.repeat(3 - stars)}
          </div>
          <h2>{stars === 3 ? '¡Eres la estrella!' : '¡Qué show!'}</h2>
          <p>
            {invitado || 'Jugador'}, el público te dio <strong>{score} aplausos</strong>
            {best >= 2 ? ` con una racha de ${best}.` : '.'}
          </p>
          <button className="cta pulse" onClick={() => onDoneRef.current?.()}>
            Ahora sí, mi foto 📸
          </button>
        </div>
      )}

      {unavailable && (
        <div className="show3d-card show3d-card--intro">
          <div className="show3d-stars">✨</div>
          <h2>Tu aventura está lista</h2>
          <p>Esta tablet tiene desactivados los efectos 3D. Puedes continuar sin detener la fiesta.</p>
          <button className="cta" onClick={() => onDoneRef.current?.()}>Continuar 📸</button>
        </div>
      )}

      {phase !== 'result' && (
        <button
          className="juego-skip show3d-skip"
          onPointerDown={(event) => event.stopPropagation()}
          onClick={() => onDoneRef.current?.()}
        >
          Saltar ⏭
        </button>
      )}
    </section>
  )
}
