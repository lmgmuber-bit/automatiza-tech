import { useCallback, useEffect, useRef, useState } from 'react'
import { supports3D } from './ToyTrack3D.jsx'

// Carriles algo más cerrados que el ancho visual de la pista: los recortes
// infantiles suelen ser anchos y no deben salir del encuadre en 9:16.
const LANES = [-1.78, 0, 1.78]

const WORLD_STYLES = {
  'turbo-track': {
    title: 'Circuito Turbo 3D',
    sky: '#35120d',
    fog: '#7e2416',
    floor: '#20242c',
    rail: '#e53935',
    glow: '#ffd54f',
    collect: '#ffd54f',
    hazard: '#252525',
  },
  'puppy-park': {
    title: 'Parque de Aventuras 3D',
    sky: '#6ec8f2',
    fog: '#bfeeff',
    floor: '#51a950',
    rail: '#1689d8',
    glow: '#ffd34e',
    collect: '#ffd34e',
    hazard: '#f28b39',
  },
  'tropical-wave': {
    title: 'Ola Tropical 3D',
    sky: '#38b8db',
    fog: '#9be9ee',
    floor: '#e5c786',
    rail: '#ff7043',
    glow: '#4dd0e1',
    collect: '#ffda58',
    hazard: '#8c5b32',
  },
  'ice-bridge': {
    title: 'Puente de Hielo 3D',
    sky: '#1f5b8f',
    fog: '#c9efff',
    floor: '#a9e7ff',
    rail: '#d9f7ff',
    glow: '#f5fdff',
    collect: '#f5fdff',
    hazard: '#5a91bd',
  },
  'neon-stage': {
    title: 'Escenario Legendario 3D',
    sky: '#13032c',
    fog: '#37105d',
    floor: '#170b26',
    rail: '#e0218a',
    glow: '#00d4ff',
    collect: '#ffd54f',
    hazard: '#7a2f9a',
  },
  'hero-city': {
    title: 'Misión de Héroes 3D',
    sky: '#06152f',
    fog: '#173d70',
    floor: '#172238',
    rail: '#e53935',
    glow: '#4db8ff',
    collect: '#72d8ff',
    hazard: '#6d7585',
  },
}

function worldStyle(world) {
  return WORLD_STYLES[world] || WORLD_STYLES['puppy-park']
}

function createHazard(THREE, world, color) {
  const group = new THREE.Group()
  let geometry
  if (world === 'turbo-track') geometry = new THREE.TorusGeometry(0.5, 0.2, 8, 16)
  else if (world === 'puppy-park') geometry = new THREE.ConeGeometry(0.48, 1.15, 12)
  else if (world === 'tropical-wave') geometry = new THREE.BoxGeometry(0.95, 0.95, 0.95)
  else if (world === 'ice-bridge') geometry = new THREE.DodecahedronGeometry(0.62, 0)
  else if (world === 'neon-stage') geometry = new THREE.BoxGeometry(0.95, 1.1, 0.7)
  else geometry = new THREE.BoxGeometry(1, 1.05, 0.8)

  const material = new THREE.MeshStandardMaterial({
    color,
    roughness: world === 'ice-bridge' ? 0.18 : 0.62,
    metalness: world === 'neon-stage' || world === 'hero-city' ? 0.45 : 0.08,
    emissive: color,
    emissiveIntensity: 0.08,
  })
  const mesh = new THREE.Mesh(geometry, material)
  mesh.position.y = world === 'turbo-track' ? 0.52 : 0.55
  mesh.rotation.x = world === 'turbo-track' ? Math.PI / 2 : 0
  group.add(mesh)

  if (world === 'puppy-park') {
    const stripe = new THREE.Mesh(
      new THREE.TorusGeometry(0.28, 0.06, 6, 12),
      new THREE.MeshBasicMaterial({ color: '#ffffff' })
    )
    stripe.rotation.x = Math.PI / 2
    stripe.position.y = 0.45
    group.add(stripe)
  }
  return group
}

function createCollectible(THREE, color) {
  const group = new THREE.Group()
  const core = new THREE.Mesh(
    new THREE.IcosahedronGeometry(0.46, 0),
    new THREE.MeshStandardMaterial({
      color,
      roughness: 0.2,
      metalness: 0.18,
      emissive: color,
      emissiveIntensity: 0.7,
    })
  )
  core.position.y = 0.85
  group.add(core)
  const halo = new THREE.Mesh(
    new THREE.TorusGeometry(0.64, 0.045, 8, 24),
    new THREE.MeshBasicMaterial({ color: '#ffffff', transparent: true, opacity: 0.8 })
  )
  halo.position.y = 0.85
  halo.rotation.x = Math.PI / 2
  group.add(halo)
  return group
}

function addWorldDecorations(THREE, scene, world, style) {
  const decorations = new THREE.Group()
  for (let i = 0; i < 11; i += 1) {
    const z = -i * 4.5 - 2
    for (const side of [-1, 1]) {
      const x = side * (4.5 + (i % 2) * 0.35)
      const item = new THREE.Group()
      if (world === 'tropical-wave') {
        const trunk = new THREE.Mesh(
          new THREE.CylinderGeometry(0.16, 0.24, 2.4, 7),
          new THREE.MeshStandardMaterial({ color: '#8d5a32', roughness: 0.9 })
        )
        trunk.position.y = 1.2
        item.add(trunk)
        for (let leaf = 0; leaf < 5; leaf += 1) {
          const crown = new THREE.Mesh(
            new THREE.ConeGeometry(0.32, 1.35, 5),
            new THREE.MeshStandardMaterial({ color: leaf % 2 ? '#37a75a' : '#167548' })
          )
          crown.position.y = 2.5
          crown.rotation.z = Math.PI / 2
          crown.rotation.y = leaf * (Math.PI * 2 / 5)
          item.add(crown)
        }
      } else if (world === 'ice-bridge') {
        const crystal = new THREE.Mesh(
          new THREE.OctahedronGeometry(0.72 + (i % 3) * 0.14),
          new THREE.MeshStandardMaterial({
            color: i % 2 ? '#d9f7ff' : '#7fcfff',
            transparent: true,
            opacity: 0.86,
            roughness: 0.08,
            metalness: 0.2,
            emissive: '#77cfff',
            emissiveIntensity: 0.18,
          })
        )
        crystal.position.y = 0.72
        crystal.scale.y = 1.7
        item.add(crystal)
      } else if (world === 'neon-stage') {
        const tower = new THREE.Mesh(
          new THREE.BoxGeometry(0.45, 3.4, 0.45),
          new THREE.MeshStandardMaterial({
            color: '#23113d',
            emissive: i % 2 ? '#e0218a' : '#00d4ff',
            emissiveIntensity: 0.72,
          })
        )
        tower.position.y = 1.7
        item.add(tower)
        const orb = new THREE.PointLight(i % 2 ? '#e0218a' : '#00d4ff', 2.2, 8)
        orb.position.y = 2.8
        item.add(orb)
      } else if (world === 'hero-city') {
        const building = new THREE.Mesh(
          new THREE.BoxGeometry(1.35, 2.4 + (i % 3), 1.35),
          new THREE.MeshStandardMaterial({ color: i % 2 ? '#183d6b' : '#293a5b', roughness: 0.7 })
        )
        building.position.y = building.geometry.parameters.height / 2
        item.add(building)
        const window = new THREE.Mesh(
          new THREE.PlaneGeometry(0.55, 0.9),
          new THREE.MeshBasicMaterial({ color: '#72d8ff' })
        )
        window.position.set(-side * 0.681, 1.5, 0)
        window.rotation.y = side * Math.PI / 2
        item.add(window)
      } else {
        const post = new THREE.Mesh(
          new THREE.CylinderGeometry(0.1, 0.12, 1.6, 7),
          new THREE.MeshStandardMaterial({ color: world === 'turbo-track' ? '#f4f4f4' : '#8d6e45' })
        )
        post.position.y = 0.8
        item.add(post)
        const balloon = new THREE.Mesh(
          new THREE.SphereGeometry(0.5, 10, 8),
          new THREE.MeshStandardMaterial({
            color: i % 2 ? style.rail : style.glow,
            emissive: i % 2 ? style.rail : style.glow,
            emissiveIntensity: 0.15,
          })
        )
        balloon.scale.y = 1.18
        balloon.position.y = 2
        item.add(balloon)
      }
      item.position.set(x, 0, z)
      decorations.add(item)
    }
  }
  scene.add(decorations)
  return decorations
}

export default function ThemeWorld3D({
  config,
  charSrc,
  runnerAtlasSrc,
  invitado,
  personaje,
  onDone,
}) {
  const mountRef = useRef(null)
  const controllerRef = useRef(null)
  const pointerRef = useRef(null)
  const onDoneRef = useRef(onDone)
  const [ready, setReady] = useState(false)
  const [phase, setPhase] = useState('intro')
  const [score, setScore] = useState(0)
  const [remaining, setRemaining] = useState(config.seconds || 20)
  const [feedback, setFeedback] = useState('')
  const [unavailable, setUnavailable] = useState(false)
  const style = worldStyle(config.world)
  const targetScore = config.targetScore || 12

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
    let resizeObserver = null
    let scene = null
    let pauseStarted = null
    let lastFrame = performance.now()
    const feedbackTimers = new Set()
    const phaseRef = { current: 'intro' }
    const laneRef = { current: 1 }
    const startRef = { current: 0 }
    const lastSecondRef = { current: -1 }
    const scoreRef = { current: 0 }
    const hitUntilRef = { current: 0 }
    const turnImpulseRef = { current: 0 }

    import('three')
      .then((THREE) => {
        if (disposed) return

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
        scene.background = new THREE.Color(style.sky)
        scene.fog = new THREE.Fog(style.fog, 11, 37)

        if (config.image) {
          new THREE.TextureLoader().load(
            config.image,
            (texture) => {
              if (disposed) {
                texture.dispose()
                return
              }
              texture.colorSpace = THREE.SRGBColorSpace
              scene.background = texture
            },
            undefined,
            () => {}
          )
        }

        const camera = new THREE.PerspectiveCamera(
          52,
          Math.max(1, mount.clientWidth) / Math.max(1, mount.clientHeight),
          0.1,
          100
        )
        camera.position.set(0, 4.15, 8.4)
        camera.lookAt(0, 1, -7)

        const ambient = new THREE.HemisphereLight('#ffffff', style.sky, 1.35)
        scene.add(ambient)
        const key = new THREE.DirectionalLight('#ffffff', 1.2)
        key.position.set(-3, 8, 5)
        scene.add(key)
        const accentLeft = new THREE.PointLight(style.rail, 3, 18)
        accentLeft.position.set(-3.4, 3.5, 1)
        scene.add(accentLeft)
        const accentRight = new THREE.PointLight(style.glow, 3, 18)
        accentRight.position.set(3.4, 3.5, 1)
        scene.add(accentRight)

        const floor = new THREE.Mesh(
          new THREE.PlaneGeometry(8.6, 58),
          new THREE.MeshStandardMaterial({
            color: style.floor,
            roughness: config.world === 'ice-bridge' ? 0.22 : 0.72,
            metalness: config.world === 'neon-stage' ? 0.42 : 0.05,
            transparent: true,
            opacity: config.image ? 0.94 : 1,
          })
        )
        floor.rotation.x = -Math.PI / 2
        floor.position.set(0, 0, -19)
        scene.add(floor)

        const rails = []
        for (const side of [-1, 1]) {
          const rail = new THREE.Mesh(
            new THREE.BoxGeometry(0.13, 0.12, 58),
            new THREE.MeshStandardMaterial({
              color: style.rail,
              emissive: style.rail,
              emissiveIntensity: 0.42,
            })
          )
          rail.position.set(side * 4.15, 0.08, -19)
          scene.add(rail)
          rails.push(rail)
        }

        const laneMarkers = []
        for (let i = 0; i < 22; i += 1) {
          for (const x of [-1.08, 1.08]) {
            const marker = new THREE.Mesh(
              new THREE.BoxGeometry(0.08, 0.025, 1.15),
              new THREE.MeshBasicMaterial({ color: style.glow, transparent: true, opacity: 0.75 })
            )
            marker.position.set(x, 0.025, -i * 2.7 + 5)
            scene.add(marker)
            laneMarkers.push(marker)
          }
        }

        const decorations = addWorldDecorations(THREE, scene, config.world, style)

        const player = new THREE.Group()
        player.position.set(0, 0.12, 2.65)
        // El recorte vive dentro de un rig separado: así puede girar, inclinarse
        // y hacer zancada sin deformar la sombra ni el aro del carril.
        const runnerVisual = new THREE.Group()
        const shadow = new THREE.Mesh(
          new THREE.CircleGeometry(0.9, 20),
          new THREE.MeshBasicMaterial({ color: '#000000', transparent: true, opacity: 0.28 })
        )
        shadow.rotation.x = -Math.PI / 2
        shadow.position.y = 0.015
        player.add(shadow)
        const playerGlow = new THREE.Mesh(
          new THREE.TorusGeometry(0.95, 0.055, 8, 32),
          new THREE.MeshBasicMaterial({ color: style.glow, transparent: true, opacity: 0.9 })
        )
        playerGlow.rotation.x = Math.PI / 2
        playerGlow.position.y = 0.04
        player.add(playerGlow)
        player.add(runnerVisual)
        scene.add(player)

        let runnerTexture = null
        let runnerUsesAtlas = false
        let runnerFrame = ''
        const setAtlasFrame = (frame) => {
          if (!runnerTexture || !runnerUsesAtlas || frame === runnerFrame) return
          const frames = {
            front: [0, 0.5],
            right: [0.5, 0.5],
            back: [0, 0],
            left: [0.5, 0],
          }
          const [u, v] = frames[frame] || frames.back
          runnerTexture.offset.set(u, v)
          runnerTexture.needsUpdate = true
          runnerFrame = frame
        }
        const loadRunnerPlane = (src, atlas = false, onError = () => {}) => {
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
              if (atlas) {
                texture.repeat.set(0.5, 0.5)
                texture.offset.set(0, 0.5)
              }
              const sourceAspect = (texture.image?.width / texture.image?.height) || 0.9
              const aspect = atlas ? sourceAspect : sourceAspect
              const h = atlas ? 2.9 : 2.45
              const sprite = new THREE.Mesh(
                new THREE.PlaneGeometry(h * aspect, h),
                new THREE.MeshBasicMaterial({
                  map: texture,
                  transparent: true,
                  alphaTest: 0.03,
                  side: THREE.DoubleSide,
                  depthWrite: false,
                })
              )
              sprite.position.y = atlas ? 1.3 : 1.18
              runnerTexture = texture
              runnerUsesAtlas = atlas
              runnerFrame = atlas ? 'front' : ''
              runnerVisual.add(sprite)
            },
            undefined,
            onError
          )
        }

        if (runnerAtlasSrc) {
          loadRunnerPlane(runnerAtlasSrc, true, () => loadRunnerPlane(charSrc))
        } else if (charSrc) {
          loadRunnerPlane(charSrc)
        } else {
          const fallback = new THREE.Mesh(
            new THREE.CapsuleGeometry(0.58, 1.1, 5, 10),
            new THREE.MeshStandardMaterial({
              color: style.rail,
              emissive: style.rail,
              emissiveIntensity: 0.2,
            })
          )
          fallback.position.y = 1.1
          runnerVisual.add(fallback)
        }

        // Pisadas luminosas detrás del personaje. Son planos baratos, no
        // partículas con shader: seis draw calls pequeños y sin sombras.
        const footsteps = new THREE.Group()
        const footstepItems = []
        for (let i = 0; i < 6; i += 1) {
          const material = new THREE.MeshBasicMaterial({
            color: i % 2 ? style.glow : style.rail,
            transparent: true,
            opacity: 0,
            depthWrite: false,
          })
          const step = new THREE.Mesh(new THREE.RingGeometry(0.12, 0.24, 12), material)
          step.rotation.x = -Math.PI / 2
          step.position.set((i % 2 ? 1 : -1) * 0.28, 0.035, 3.05 + i * 0.44)
          step.userData.offset = i / 6
          footsteps.add(step)
          footstepItems.push(step)
        }
        scene.add(footsteps)

        const speedLines = new THREE.Group()
        for (let i = 0; i < 28; i += 1) {
          const line = new THREE.Mesh(
            new THREE.BoxGeometry(0.035, 0.035, 0.7 + Math.random() * 1.2),
            new THREE.MeshBasicMaterial({
              color: i % 2 ? style.glow : '#ffffff',
              transparent: true,
              opacity: 0.42,
            })
          )
          line.position.set((Math.random() - 0.5) * 8, 0.3 + Math.random() * 3.5, -Math.random() * 32)
          speedLines.add(line)
        }
        scene.add(speedLines)

        const objects = []
        for (let i = 0; i < 13; i += 1) {
          const isCollectible = i % 3 !== 0
          const root = isCollectible
            ? createCollectible(THREE, style.collect)
            : createHazard(THREE, config.world, style.hazard)
          root.userData.type = isCollectible ? 'collect' : 'hazard'
          root.userData.hit = false
          root.position.set(LANES[i % 3], 0, -8 - i * 3.35)
          scene.add(root)
          objects.push(root)
        }

        const sparklePositions = new Float32Array(90 * 3)
        for (let i = 0; i < 90; i += 1) {
          sparklePositions[i * 3] = (Math.random() - 0.5) * 8
          sparklePositions[i * 3 + 1] = 0.2 + Math.random() * 4
          sparklePositions[i * 3 + 2] = -Math.random() * 35
        }
        const sparkleGeometry = new THREE.BufferGeometry()
        sparkleGeometry.setAttribute('position', new THREE.BufferAttribute(sparklePositions, 3))
        const sparkles = new THREE.Points(
          sparkleGeometry,
          new THREE.PointsMaterial({
            color: style.glow,
            size: 0.055,
            transparent: true,
            opacity: 0.72,
            sizeAttenuation: true,
          })
        )
        scene.add(sparkles)

        const recycle = (object, index) => {
          const farthest = objects.reduce((min, item) => Math.min(min, item.position.z), -16)
          object.position.z = farthest - 3.4 - Math.random() * 2.8
          object.position.x = LANES[Math.floor(Math.random() * LANES.length)]
          object.userData.hit = false
          object.visible = true
          object.rotation.set(0, Math.random() * Math.PI, 0)
        }

        const moveLane = (direction) => {
          if (phaseRef.current !== 'playing') return
          laneRef.current = Math.max(0, Math.min(2, laneRef.current + direction))
          turnImpulseRef.current = direction * 0.68
          if (navigator.vibrate) navigator.vibrate(10)
        }

        const start = () => {
          scoreRef.current = 0
          laneRef.current = 1
          turnImpulseRef.current = 0
          startRef.current = performance.now()
          lastSecondRef.current = -1
          phaseRef.current = 'playing'
          setScore(0)
          setRemaining(config.seconds || 20)
          setFeedback('')
          setPhase('playing')
          camera.fov = 58
          camera.updateProjectionMatrix()
        }

        controllerRef.current = { start, move: moveLane }
        setReady(true)

        const durationMs = (config.seconds || 20) * 1000
        const animate = (now) => {
          if (disposed) return
          const playing = phaseRef.current === 'playing'
          const delta = Math.min(0.04, Math.max(0.001, (now - lastFrame) / 1000))
          lastFrame = now
          const elapsed = playing ? now - startRef.current : 0
          const remainingMs = Math.max(0, durationMs - elapsed)

          if (playing) {
            const second = Math.ceil(remainingMs / 1000)
            if (second !== lastSecondRef.current) {
              lastSecondRef.current = second
              setRemaining(second)
            }
            if (remainingMs <= 0) {
              phaseRef.current = 'result'
              setPhase('result')
              setRemaining(0)
              camera.fov = 52
              camera.updateProjectionMatrix()
            }
          }

          const speed = playing ? 9.7 : 1.25
          const frameMove = speed * delta
          const laneDelta = LANES[laneRef.current] - player.position.x
          player.position.x += laneDelta * 0.16
          const runPhase = now * 0.014
          const stride = playing ? Math.abs(Math.sin(runPhase)) : 0
          player.position.y = playing
            ? 0.12 + stride * 0.105
            : 0.12 + Math.sin(now * 0.006) * 0.035
          player.rotation.z += ((playing ? laneDelta * -0.115 : 0) - player.rotation.z) * 0.16

          // El personaje ya no mira rígidamente a cámara: anticipa el carril
          // y hace pequeños cambios de orientación como quien mira la pista.
          // Se limita a tres cuartos porque el asset es un recorte frontal;
          // un giro de 180° mostraría una espalda falsa/espejada.
          const runningLook = playing ? Math.sin(now * 0.00155) * 0.38 : Math.sin(now * 0.0007) * 0.05
          const steering = Math.abs(laneDelta) > 0.08
            ? Math.sign(laneDelta)
            : Math.abs(turnImpulseRef.current) > 0.08
              ? Math.sign(turnImpulseRef.current)
              : 0
          if (runnerUsesAtlas) {
            if (!playing) setAtlasFrame('front')
            else if (steering > 0) setAtlasFrame('right')
            else if (steering < 0) setAtlasFrame('left')
            else setAtlasFrame('back')
          }

          // Con atlas, el volumen percibido viene de vistas generadas reales:
          // espalda al avanzar y tres cuartos al cambiar de carril. El recorte
          // histórico mantiene el giro de plano como fallback.
          const laneFacing = runnerUsesAtlas ? 0 : (laneRef.current - 1) * 0.58
          const desiredYaw = runnerUsesAtlas
            ? steering * 0.08
            : Math.max(
              -1.05,
              Math.min(1.05, laneDelta * 0.28 + laneFacing + runningLook + turnImpulseRef.current)
            )
          runnerVisual.rotation.y += (desiredYaw - runnerVisual.rotation.y) * 0.12
          turnImpulseRef.current *= Math.pow(0.94, delta * 60)
          runnerVisual.rotation.x += ((playing ? -0.105 : 0) - runnerVisual.rotation.x) * 0.1
          const squash = playing ? Math.sin(runPhase * 2) * 0.028 : 0
          runnerVisual.scale.set(1 - squash * 0.55, 1 + squash, 1)
          runnerVisual.position.z = playing ? Math.sin(runPhase) * 0.055 : 0

          shadow.scale.set(
            1 + stride * 0.12,
            1 - stride * 0.16,
            1
          )
          shadow.material.opacity = playing ? 0.22 + (1 - stride) * 0.09 : 0.28
          footstepItems.forEach((step) => {
            const cycle = (now * 0.0019 + step.userData.offset) % 1
            step.visible = playing
            step.position.z = 2.95 + cycle * 3.2
            step.position.x = player.position.x + (Math.round(step.userData.offset * 6) % 2 === 1 ? 0.25 : -0.25)
            step.scale.setScalar(0.75 + cycle * 1.35)
            step.material.opacity = playing ? Math.max(0, 0.62 * (1 - cycle)) : 0
          })
          playerGlow.rotation.z += 0.018
          playerGlow.material.opacity = 0.62 + Math.sin(now * 0.006) * 0.24

          laneMarkers.forEach((marker) => {
            marker.position.z += frameMove
            if (marker.position.z > 7) marker.position.z -= 59.4
          })
          speedLines.children.forEach((line) => {
            line.position.z += frameMove * 1.6
            if (line.position.z > 8) line.position.z = -34 - Math.random() * 8
          })
          decorations.children.forEach((item) => {
            item.rotation.y = Math.sin(now * 0.0008 + item.position.z) * 0.025
          })

          objects.forEach((object, index) => {
            if (!playing) {
              object.rotation.y += 0.006
              return
            }
            object.position.z += frameMove
            object.rotation.y += object.userData.type === 'collect' ? 0.045 : 0.012
            if (
              !object.userData.hit &&
              object.position.z > 1.65 &&
              object.position.z < 3.6 &&
              Math.abs(object.position.x - player.position.x) < 0.82
            ) {
              object.userData.hit = true
              object.visible = false
              if (object.userData.type === 'collect') {
                scoreRef.current += 1
                setScore(scoreRef.current)
                setFeedback(`+1 ${config.collectible || '⭐'}`)
                if (navigator.vibrate) navigator.vibrate(18)
              } else {
                scoreRef.current = Math.max(0, scoreRef.current - 1)
                setScore(scoreRef.current)
                setFeedback(`¡Esquiva! ${config.hazard || '💥'}`)
                hitUntilRef.current = now + 300
                if (navigator.vibrate) navigator.vibrate([30, 20, 30])
              }
              const feedbackTimer = window.setTimeout(() => {
                feedbackTimers.delete(feedbackTimer)
                if (!disposed) setFeedback('')
              }, 420)
              feedbackTimers.add(feedbackTimer)
            }
            if (object.position.z > 7) recycle(object, index)
          })

          const shake = hitUntilRef.current > now ? Math.sin(now * 0.08) * 0.11 : 0
          camera.position.x = shake + player.position.x * 0.22
          camera.position.y = 4.15 + Math.sin(now * 0.0016) * 0.06
          camera.lookAt(player.position.x * 0.2, 1, -7)
          accentLeft.intensity = 2.4 + Math.sin(now * 0.004) * 0.8
          accentRight.intensity = 2.4 + Math.cos(now * 0.0043) * 0.8
          sparkles.rotation.y += 0.0009

          renderer.render(scene, camera)
          raf = requestAnimationFrame(animate)
        }
        raf = requestAnimationFrame(animate)

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

        const visibility = () => {
          if (document.hidden && phaseRef.current === 'playing') {
            pauseStarted = performance.now()
          } else if (pauseStarted && phaseRef.current === 'playing') {
            startRef.current += performance.now() - pauseStarted
            pauseStarted = null
          }
        }
        document.addEventListener('visibilitychange', visibility)
        mount.__themeWorldVisibility = visibility
      })
      .catch(() => {
        if (!disposed) setUnavailable(true)
      })

    return () => {
      disposed = true
      if (raf) cancelAnimationFrame(raf)
      feedbackTimers.forEach((timer) => window.clearTimeout(timer))
      feedbackTimers.clear()
      if (resizeObserver) resizeObserver.disconnect()
      if (mount.__themeWorldVisibility) {
        document.removeEventListener('visibilitychange', mount.__themeWorldVisibility)
        delete mount.__themeWorldVisibility
      }
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
      controllerRef.current = null
      if (renderer) {
        renderer.dispose()
        renderer.forceContextLoss()
        if (renderer.domElement.parentNode) renderer.domElement.parentNode.removeChild(renderer.domElement)
      }
    }
  }, [
    charSrc,
    config.collectible,
    config.hazard,
    config.image,
    config.seconds,
    config.world,
    style.collect,
    style.floor,
    style.fog,
    style.glow,
    style.hazard,
    style.rail,
    style.sky,
  ])

  const start = useCallback(() => {
    controllerRef.current?.start()
  }, [])

  const move = useCallback((direction) => {
    controllerRef.current?.move(direction)
  }, [])

  const pointerDown = (event) => {
    pointerRef.current = { x: event.clientX, y: event.clientY }
  }

  const pointerUp = (event) => {
    if (!pointerRef.current || phase !== 'playing') return
    const dx = event.clientX - pointerRef.current.x
    const rect = event.currentTarget.getBoundingClientRect()
    pointerRef.current = null
    if (Math.abs(dx) > 28) move(dx > 0 ? 1 : -1)
    else move(event.clientX - rect.left > rect.width / 2 ? 1 : -1)
  }

  const stars = score >= targetScore ? 3 : score >= Math.ceil(targetScore * 0.6) ? 2 : 1

  return (
    <section
      className={`screen juego juego--mundo3d mundo3d--${config.world}`}
      onPointerDown={pointerDown}
      onPointerUp={pointerUp}
      onKeyDown={(event) => {
        if (event.key === 'ArrowLeft') move(-1)
        if (event.key === 'ArrowRight') move(1)
      }}
      tabIndex={0}
    >
      <div ref={mountRef} className="mundo3d-canvas" />
      <div className="mundo3d-vignette" />

      <div className="mundo3d-brand">FULL · EXPERIENCIA 3D</div>
      <div className="mundo3d-hud" aria-live="polite">
        <span>{config.collectible || '⭐'} {score}</span>
        <span>⏱ {remaining}</span>
      </div>

      {feedback && <div className="mundo3d-feedback">{feedback}</div>}

      {phase === 'intro' && !unavailable && (
        <div className="mundo3d-card">
          <p className="mundo3d-kicker">Misión especial para {invitado || 'ti'}</p>
          <h2>{style.title}</h2>
          <p>
            Muévete a los lados, recoge {config.collectible || 'estrellas'} y esquiva{' '}
            {config.hazard || 'obstáculos'} junto a {personaje?.name || 'tu personaje'}.
          </p>
          <div className="mundo3d-instructions">
            <span>👈 Desliza</span>
            <span>👉 o toca</span>
          </div>
          <button
            className="cta pulse"
            onPointerDown={(event) => event.stopPropagation()}
            onPointerUp={(event) => event.stopPropagation()}
            onClick={start}
            disabled={!ready}
          >
            {ready ? 'Entrar al mundo 3D ✨' : 'Preparando mundo…'}
          </button>
        </div>
      )}

      {phase === 'playing' && (
        <div className="mundo3d-controls" aria-label="Controles de movimiento">
          <button
            onPointerDown={(event) => event.stopPropagation()}
            onPointerUp={(event) => event.stopPropagation()}
            onClick={() => move(-1)}
            aria-label="Mover a la izquierda"
          >◀</button>
          <div className="mundo3d-target">META {targetScore}</div>
          <button
            onPointerDown={(event) => event.stopPropagation()}
            onPointerUp={(event) => event.stopPropagation()}
            onClick={() => move(1)}
            aria-label="Mover a la derecha"
          >▶</button>
        </div>
      )}

      {phase === 'result' && (
        <div className="mundo3d-card mundo3d-card--result">
          <div className="mundo3d-stars" aria-label={`${stars} estrellas`}>
            {'★'.repeat(stars)}{'☆'.repeat(3 - stars)}
          </div>
          <h2>{score >= targetScore ? '¡Misión completada!' : '¡Gran aventura!'}</h2>
          <p>{invitado || 'Jugador'}, conseguiste {score} {config.collectible || 'estrellas'}.</p>
          <button className="cta pulse" onClick={() => onDoneRef.current?.()}>
            Ahora sí, mi foto 📸
          </button>
        </div>
      )}

      {unavailable && (
        <div className="mundo3d-card">
          <div className="mundo3d-stars">✨</div>
          <h2>Tu aventura está lista</h2>
          <p>Esta tablet tiene desactivados los efectos 3D. Puedes continuar sin detener la fiesta.</p>
          <button className="cta" onClick={() => onDoneRef.current?.()}>Continuar 📸</button>
        </div>
      )}

      {phase !== 'result' && (
        <button
          className="juego-skip mundo3d-skip"
          onPointerDown={(event) => event.stopPropagation()}
          onPointerUp={(event) => event.stopPropagation()}
          onClick={() => onDoneRef.current?.()}
        >
          Saltar ⏭
        </button>
      )}
    </section>
  )
}
