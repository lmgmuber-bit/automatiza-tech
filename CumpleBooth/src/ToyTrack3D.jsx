import { useEffect, useRef, useState } from 'react'

/* ============================================================
   ToyTrack3D — pista de juguete 3D para la pantalla de transición
   (el momento "WOW" antes de la cámara).

   Diseño: túnel de globos con parallax (dos hileras a los costados,
   recediendo en profundidad), pista con niebla de profundidad, dos
   luces de podio pulsantes en los colores de la temática, y el
   personaje ganador de la ruleta entrando desde el fondo como una
   textura plana (billboard) que crece y se acerca a cámara.

   PROGRESIVO A PROPÓSITO:
   - `three` se importa con import() dinámico: si el dispositivo no
     soporta WebGL, o el usuario pidió prefers-reduced-motion, o pasó
     ?fx3d=0 en la URL (interruptor manual para el operador si una
     tablet específica va lenta el día de la fiesta), el bundle de
     Three.js NUNCA se descarga — el llamador debe usar supports3D()
     ANTES de montar este componente y mostrar el fallback 2D si es false.
   - Sin sombras, sin post-procesado, geometría de bajo poligonaje,
     devicePixelRatio limitado a 1.5 — pensado para tablets Android
     de gama media, no para GPUs de escritorio.
   - Limpieza total (dispose de geometrías/materiales/texturas/renderer)
     en el cleanup del efecto: este componente se monta y desmonta una
     vez POR CADA INVITADO durante horas de evento, un leak de contexto
     WebGL aquí tumbaría la tablet a mitad de fiesta.
   ============================================================ */

// Cache a nivel de módulo: el resultado de "¿esta tablet soporta WebGL?"
// no cambia durante la sesión, no hace falta repetir la prueba por invitado.
let webglSupportCache = null

export function supports3D() {
  if (typeof window === 'undefined') return false
  if (webglSupportCache !== null) return webglSupportCache
  try {
    const params = new URLSearchParams(window.location.search)
    if (params.get('fx3d') === '0') {
      webglSupportCache = false
      return false
    }
    const reduceMotion =
      window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (reduceMotion) {
      webglSupportCache = false
      return false
    }
    const canvas = document.createElement('canvas')
    const gl =
      canvas.getContext('webgl2') ||
      canvas.getContext('webgl') ||
      canvas.getContext('experimental-webgl')
    webglSupportCache = !!gl
  } catch {
    webglSupportCache = false
  }
  return webglSupportCache
}

// Lee los colores de la temática ya aplicados como CSS vars por
// applyThemeVars() en App.jsx (mismo patrón que composeDiploma).
function readThemeColors() {
  const cs = getComputedStyle(document.documentElement)
  const pick = (name, fallback) => {
    const v = cs.getPropertyValue(name).trim()
    return v || fallback
  }
  return {
    accent: pick('--pink', '#e8000d'),
    yellow: pick('--yellow', '#ffb800'),
    dark1: pick('--dark1', '#b30009'),
    dark2: pick('--dark2', '#7a0008'),
    dark3: pick('--dark3', '#e8a200'),
  }
}

/**
 * @param {string|null} charSrc  textura del vehículo ganador (idealmente el
 *   recorte transparente -cut.png; si no existe, el JPG normal igual sirve,
 *   solo que se ve con fondo rectangular en vez de recortado)
 * @param {number} durationMs  debe calzar con el timeout de onDone() en
 *   TransicionWow — no cambia el ritmo del flujo, solo lo que se ve durante
 *   esos segundos
 */
export default function ToyTrack3D({ charSrc, durationMs = 3800 }) {
  const mountRef = useRef(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let disposed = false
    let raf = null
    let renderer = null
    let handleResize = null
    const mount = mountRef.current
    if (!mount) return undefined

    const colors = readThemeColors()

    // import() dinámico: Three.js (~150KB gzip) solo se descarga si
    // supports3D() ya dio true — el caller garantiza eso, pero igual
    // protegemos con try/catch por si la red falla a mitad de fiesta.
    import('three')
      .then((THREE) => {
        if (disposed || !mount) return

        renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'low-power' })
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5))
        renderer.setSize(mount.clientWidth, mount.clientHeight)
        renderer.shadowMap.enabled = false // perf: nada de sombras en tablets
        mount.appendChild(renderer.domElement)

        const scene = new THREE.Scene()
        scene.background = new THREE.Color(colors.dark2)
        scene.fog = new THREE.Fog(new THREE.Color(colors.dark2).getHex(), 7, 24)

        const camera = new THREE.PerspectiveCamera(
          52,
          mount.clientWidth / Math.max(1, mount.clientHeight),
          0.1,
          100
        )
        camera.position.set(0, 2.3, 8.5)
        camera.lookAt(0, 1.1, -8)

        // ---- Pista (piso con profundidad) ----
        const trackGeo = new THREE.PlaneGeometry(6.4, 44)
        const trackMat = new THREE.MeshStandardMaterial({
          color: colors.dark1,
          roughness: 0.7,
          metalness: 0.05,
        })
        const track = new THREE.Mesh(trackGeo, trackMat)
        track.rotation.x = -Math.PI / 2
        track.position.set(0, 0, -12)
        scene.add(track)

        // línea central de la pista, un poco emisiva para que se note el avance
        const stripeGeo = new THREE.PlaneGeometry(0.22, 44)
        const stripeMat = new THREE.MeshBasicMaterial({ color: colors.yellow, transparent: true, opacity: 0.55 })
        const stripe = new THREE.Mesh(stripeGeo, stripeMat)
        stripe.rotation.x = -Math.PI / 2
        stripe.position.set(0, 0.01, -12)
        scene.add(stripe)

        // ---- Túnel de globos (dos hileras, parallax al avanzar la cámara) ----
        const balloonGroup = new THREE.Group()
        const balloonPalette = [colors.accent, colors.yellow, '#ffffff']
        const balloonGeo = new THREE.SphereGeometry(0.42, 10, 8)
        const stringGeo = new THREE.CylinderGeometry(0.012, 0.012, 0.9, 4)
        const stringMat = new THREE.MeshBasicMaterial({ color: '#3a2a1a' })
        const ROWS = 12
        for (let i = 0; i < ROWS; i++) {
          const z = -i * 2.6 - 2
          ;[-1, 1].forEach((side) => {
            const color = balloonPalette[(i + (side > 0 ? 0 : 1)) % balloonPalette.length]
            const mat = new THREE.MeshStandardMaterial({
              color,
              roughness: 0.35,
              metalness: 0.05,
              emissive: color,
              emissiveIntensity: 0.18,
            })
            const balloon = new THREE.Mesh(balloonGeo, mat)
            const y = 2.3 + Math.sin(i * 0.8 + side) * 0.25
            balloon.position.set(side * 2.7, y, z)
            balloonGroup.add(balloon)
            const string = new THREE.Mesh(stringGeo, stringMat)
            string.position.set(side * 2.7, y - 0.65, z)
            balloonGroup.add(string)
          })
        }
        scene.add(balloonGroup)

        // ---- Luces de podio (pulsantes, colores de la temática) ----
        const podiumLeft = new THREE.PointLight(colors.yellow, 3, 18)
        podiumLeft.position.set(-2.4, 3.2, -1.5)
        scene.add(podiumLeft)
        const podiumRight = new THREE.PointLight(colors.accent, 3, 18)
        podiumRight.position.set(2.4, 3.2, -1.5)
        scene.add(podiumRight)
        const ambient = new THREE.AmbientLight('#ffffff', 0.6)
        scene.add(ambient)
        const key = new THREE.DirectionalLight('#ffffff', 0.35)
        key.position.set(0, 6, 5)
        scene.add(key)

        // ---- Chispas de celebración: burbujean cerca del podio en el
        // último tramo, cuando el vehículo ya está cerca de cámara ----
        const SPARK_COUNT = 60
        const sparkGeo = new THREE.BufferGeometry()
        const sparkPos = new Float32Array(SPARK_COUNT * 3)
        const sparkSeed = new Float32Array(SPARK_COUNT)
        for (let i = 0; i < SPARK_COUNT; i++) {
          sparkPos[i * 3] = (Math.random() - 0.5) * 5.2
          sparkPos[i * 3 + 1] = 0.2 + Math.random() * 0.3
          sparkPos[i * 3 + 2] = -3 - Math.random() * 3
          sparkSeed[i] = Math.random() * Math.PI * 2
        }
        sparkGeo.setAttribute('position', new THREE.BufferAttribute(sparkPos, 3))
        const sparkMat = new THREE.PointsMaterial({
          color: colors.yellow,
          size: 0.09,
          transparent: true,
          opacity: 0,
          sizeAttenuation: true,
        })
        const sparks = new THREE.Points(sparkGeo, sparkMat)
        scene.add(sparks)

        // ---- Vehículo ganador: billboard que entra desde el fondo ----
        let vehicle = null
        if (charSrc) {
          const loader = new THREE.TextureLoader()
          loader.load(
            charSrc,
            (tex) => {
              if (disposed) {
                tex.dispose()
                return
              }
              tex.colorSpace = THREE.SRGBColorSpace
              const aspect = (tex.image && tex.image.width / tex.image.height) || 1
              const h = 2.1
              const geo = new THREE.PlaneGeometry(h * aspect, h)
              const mat = new THREE.MeshBasicMaterial({ map: tex, transparent: true })
              vehicle = new THREE.Mesh(geo, mat)
              vehicle.position.set(0, 1.05, -26)
              vehicle.scale.setScalar(0.4)
              scene.add(vehicle)
            },
            undefined,
            () => {
              /* textura no cargó a tiempo: la escena sigue sin vehículo,
                 no rompe nada, el texto/overlay 2D encima ya cuenta la historia */
            }
          )
        }

        const start = performance.now()
        const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3)

        const animate = (now) => {
          if (disposed) return
          const t = Math.min(1, (now - start) / durationMs)
          const ease = easeOutCubic(t)

          // avance de cámara/túnel: simula recorrer la pista hacia adelante
          balloonGroup.position.z = ease * 16
          track.position.z = -12 + ease * 5
          stripe.position.z = -12 + ease * 5

          if (vehicle) {
            vehicle.position.z = -26 + ease * 24 // llega cerca de z ≈ -2
            // rebote tipo "juguete con resorte": más notorio al llegar cerca
            const bob = Math.sin(t * Math.PI * 6) * (0.05 + ease * 0.07)
            vehicle.position.y = 1.05 + bob
            // giro de entrada: dos vueltas completas al arrancar, se calma al acercarse
            const spinIn = (1 - ease) * Math.PI * 4
            vehicle.rotation.y = spinIn + Math.sin(t * Math.PI * 3) * 0.08
            // squash & stretch: se "aplasta" un poco en cada rebote, como juguete real
            const squash = 1 - Math.abs(Math.sin(t * Math.PI * 6)) * 0.06
            vehicle.scale.set((0.4 + ease * 1.1) * (2 - squash), (0.4 + ease * 1.1) * squash, 1)
          }

          podiumLeft.intensity = 2.6 + Math.sin(now * 0.005) * 1.1
          podiumRight.intensity = 2.6 + Math.cos(now * 0.0052) * 1.1

          // chispas: aparecen y burbujean en el último tercio, cuando el
          // vehículo ya está cerca del podio — celebración de llegada
          if (t > 0.6) {
            sparkMat.opacity = Math.min(1, (t - 0.6) / 0.15) * 0.9
            const posAttr = sparkGeo.attributes.position
            for (let i = 0; i < SPARK_COUNT; i++) {
              posAttr.array[i * 3 + 1] = 0.2 + Math.abs(Math.sin(now * 0.003 + sparkSeed[i])) * 1.6
            }
            posAttr.needsUpdate = true
          } else {
            sparkMat.opacity = 0
          }

          renderer.render(scene, camera)
          raf = requestAnimationFrame(animate)
        }
        raf = requestAnimationFrame(animate)

        handleResize = () => {
          if (!mount || !renderer) return
          camera.aspect = mount.clientWidth / Math.max(1, mount.clientHeight)
          camera.updateProjectionMatrix()
          renderer.setSize(mount.clientWidth, mount.clientHeight)
        }
        window.addEventListener('resize', handleResize)

        // guardamos referencias para el cleanup (closure de este .then)
        mount.__toyTrackCleanup = () => {
          scene.traverse((obj) => {
            if (obj.geometry) obj.geometry.dispose()
            if (obj.material) {
              const mats = Array.isArray(obj.material) ? obj.material : [obj.material]
              mats.forEach((m) => {
                if (m.map) m.map.dispose()
                m.dispose()
              })
            }
          })
        }
      })
      .catch(() => {
        if (!disposed) setFailed(true)
      })

    return () => {
      disposed = true
      if (raf) cancelAnimationFrame(raf)
      if (handleResize) window.removeEventListener('resize', handleResize)
      if (mount && mount.__toyTrackCleanup) {
        mount.__toyTrackCleanup()
        delete mount.__toyTrackCleanup
      }
      if (renderer) {
        renderer.dispose()
        if (renderer.domElement && renderer.domElement.parentNode) {
          renderer.domElement.parentNode.removeChild(renderer.domElement)
        }
      }
    }
  }, [charSrc, durationMs])

  // Si Three.js no pudo cargar (red caída a mitad de fiesta, ej.), no
  // dejamos un div vacío negro: el caller debe seguir mostrando su overlay
  // de texto encima, así que solo devolvemos null y el fondo oscuro base
  // del contenedor .transition ya sirve de respaldo visual.
  if (failed) return null

  return <div ref={mountRef} className="toy-track-3d" aria-hidden="true" />
}
