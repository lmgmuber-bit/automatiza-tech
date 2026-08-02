# CumpleClick — Fase 2: "Pista de juguete 3D" (WOW en la transición)

Implementado 2026-07 por Claude, sobre la propuesta que dejó Codex sin construir
(se quedó sin tokens tras el análisis de skills `frontend-design`/`3d-games`/
`agent-browser` y la nota sobre `3d-web-experience` deshabilitada).

## Qué se construyó

Un solo momento 3D pulido en vez de varios a medias: la pantalla de transición
(`TransicionWow`, justo antes de la cámara) ahora puede mostrar una escena
Three.js con:
- Túnel de globos con parallax (dos hileras a los costados, 12 filas de
  profundidad, colores de la temática activa)
- Pista con niebla de profundidad (fog) que da sensación de recorrido
- Dos luces de podio pulsantes (colores acento/amarillo de la temática)
- El personaje ganador de la ruleta entrando desde el fondo como billboard
  (usa el recorte transparente `-cut.png` si existe, o el JPG normal si no)

La ruleta 3D y el resto del "piloto" que describió Codex **no se construyeron**
— se decidió deliberadamente enfocar el esfuerzo en una sola pieza completa y
verificable en vez de repartirlo en features a medio terminar. Queda como
siguiente iteración si se quiere retomar.

## Arquitectura — por qué es seguro de desplegar

- **Archivo nuevo:** `src/ToyTrack3D.jsx`. Todo el código 3D vive ahí, no
  tocó ningún otro componente salvo `TransicionWow` (que ahora decide 2D vs
  3D) y el import en la cabecera de `App.jsx`.
- **`three` es dependencia nueva** en `package.json` (v0.185.1). Se carga con
  `import()` dinámico — Vite lo separa en su propio chunk
  (`three.module-*.js`, ~190KB gzip) que **nunca se descarga** si el
  dispositivo no soporta WebGL, si el usuario tiene
  `prefers-reduced-motion`, o si la URL lleva `?fx3d=0`.
- **Interruptor manual `?fx3d=0`**: si en la fiesta una tablet específica va
  lenta con el efecto 3D, se agrega ese parámetro a la URL de esa tablet y
  vuelve al fallback 2D (el `transition-bg` con zoom que ya existía) sin
  tocar código ni redeploy. Es la respuesta a "fallback para tablets lentas"
  — no hay detección automática de FPS en tiempo real (se consideró y se
  descartó por complejidad/riesgo dado que no se puede verificar en este
  entorno; ver sección de límites más abajo).
- **Se pausa durante la cámara de forma natural**: `ToyTrack3D` vive en la
  pantalla `transition`, que se desmonta al pasar a `capture` — React dispara
  el cleanup del `useEffect` (dispose de geometrías/materiales/texturas/
  renderer, remove del canvas del DOM) automáticamente. No hace falta lógica
  extra de "pausa", es consecuencia del ciclo de vida normal del componente.
- **Presupuesto de rendimiento para tablets de gama media:** sin sombras
  (`shadowMap.enabled = false`), sin post-procesado, geometría de bajo
  poligonaje (esferas de 10×8 segmentos), `devicePixelRatio` limitado a 1.5,
  `powerPreference: 'low-power'`.
- **Limpieza de memoria estricta:** este componente se monta y desmonta UNA
  VEZ POR CADA INVITADO durante horas de evento en la misma pestaña — un
  leak de contexto WebGL aquí tumbaría la tablet a mitad de fiesta. El
  cleanup dispone explícitamente cada geometría, material, textura y el
  renderer, y remueve el canvas del DOM.

## Verificación realizada — y sus límites (léelo antes de asumir que "ya está probado")

**Verificado con evidencia real (no solo "compiló"):**
- `npm run build` sin errores; `three.module-*.js` confirmado como chunk
  separado del bundle principal (code-splitting funciona)
- Con `javascript_tool` en un Chrome real (headless de este entorno), se
  importó el chunk `three.module-*.js` **desde su ruta servida en
  producción** (`dist/assets/...`), se construyó una escena con los MISMOS
  constructores/propiedades que usa `ToyTrack3D.jsx` (PlaneGeometry,
  SphereGeometry, CylinderGeometry, MeshStandardMaterial, MeshBasicMaterial,
  PointLight ×2, AmbientLight, DirectionalLight, TextureLoader cargando el
  asset real `themes/carreras/rayo-mcqueen-cut.png`), se ejecutó
  `renderer.render()`, y se leyó un píxel del framebuffer resultante
  (`gl.readPixels`) — confirmando que la GPU efectivamente dibujó algo (no
  un canvas negro/vacío). El ciclo completo de `dispose()` también se probó
  y no lanzó excepciones.

**NO verificado (limitación del entorno, no señal de que esté roto):**
- La animación en vivo (el loop de `requestAnimationFrame` que mueve la
  cámara/globos/vehículo/luces con el tiempo) — este entorno de pruebas
  tiene `document.hidden = true` de forma permanente, lo cual congela
  cualquier rAF (esto ya afectaba a la ruleta 2D existente, no es nuevo de
  hoy, es una limitación conocida del sandbox de este agente)
- Cómo se ve/siente en la práctica: composición, ritmo de las luces,
  si el vehículo "entra" de forma convincente, proporciones del túnel de
  globos
- Rendimiento real en una tablet física de gama media/baja
- El flujo completo React (mount → animación → unmount limpio) en vivo,
  solo se probó la mecánica Three.js de forma aislada

**Antes de dar esto por terminado, alguien con un navegador real (no este
entorno) debe:**
1. Abrir `http://localhost/automatiza-tech/CumpleBooth/dist/?p=demo`,
   completar el flujo hasta después de la ruleta, y ver la transición 3D
   funcionando con movimiento real
2. Confirmar que se ve bien (esto es 100% subjetivo, requiere ojo humano)
3. Probarlo en la tablet real del evento si es posible, o al menos en un
   Chrome de un celular Android de gama media, para juzgar el rendimiento
4. Si se ve lento/entrecortado en la tablet real: usar `?fx3d=0` en esa
   tablet específica como solución inmediata, y considerar si vale la pena
   invertir en detección automática de FPS más adelante

## Iteración 2 — auto 3D en bienvenida + más vida en la pista

Pedido: dar movimiento a los personajes de la pista y hacer 3D el auto que
aparece en el popup "¡Bienvenido!" (pantalla de lista de invitados, antes
de la ruleta).

**Auto 3D en el popup de bienvenida (`ListaInvitados` en `App.jsx`):**
- **No usa Three.js.** Es transform 3D nativo de CSS (`perspective` +
  `preserve-3d` + `rotateY`/`rotateX`/`translateZ`), verificado con
  `getComputedStyle` devolviendo una `matrix3d` real a mitad de giro.
- Decisión deliberada: este popup se monta/desmonta una vez POR CLIC en
  cada invitado — puede dispararse decenas de veces seguidas mientras el
  operador prueba nombres. Levantar un contexto WebGL nuevo cada vez sería
  desperdiciar presupuesto de rendimiento en una tablet de gama media para
  un efecto que dura 1.5-2.4s. CSS 3D da la misma sensación de profundidad
  sin ese costo.
- Usa la imagen real de un personaje aleatorio de la temática activa
  (`PERSONAJES` / `getCharPng` / `CHAR_IMG`, mismo patrón que ya existía en
  el resto de la app) con fallback a emoji si aún no hay imagen cargada.
- Respeta `prefers-reduced-motion` (mismo bloque `@media` que ya
  apagaba `.spinner-pointer`).

**Más vida en el vehículo de `ToyTrack3D.jsx`:**
- Antes: el vehículo solo cambiaba posición/escala de forma lineal-ish.
- Ahora: entra girando dos vueltas completas que se calman al acercarse
  (efecto "spin-in"), rebota con squash & stretch (se aplasta un poco en
  cada bote, como juguete de verdad, no solo sube/baja), y en el último
  tercio del recorrido aparecen chispas doradas (`THREE.Points`) que
  burbujean cerca del podio como celebración de llegada.
- Igual que el resto de la escena: `scene.traverse()` en el cleanup ya
  dispone geometría/material de las chispas automáticamente, no hizo
  falta código de limpieza extra.

**Verificación de esta iteración:**
- Auto 3D bienvenida: verificado end-to-end en Chrome real (headless de
  este entorno) — clic en invitado → popup aparece → `getComputedStyle`
  confirma `animationName: welcomeCarSpin` corriendo y una `matrix3d` no
  identidad a mitad de giro → imagen real de la temática (`el-rey.jpg`)
  cargada. A diferencia del canvas WebGL, las animaciones CSS SÍ corren en
  este sandbox (no dependen de `requestAnimationFrame`), así que esto se
  verificó con movimiento real, no solo mecánica estática.
- Motion de `ToyTrack3D`: mismo método que la Iteración 1 (ejecución
  aislada del chunk real de Three.js, simulando el loop `animate()` en 6
  puntos del timeline con la textura real `rayo-mcqueen-cut.png`) — sin
  errores, pixel de framebuffer no vacío, opacidad de chispas sube
  correctamente a 0.9 cerca del final, dispose limpio incluyendo la
  geometría nueva de `Points`. **Sigue sin verificarse la animación en
  vivo dentro de React** (mismo límite de siempre: `document.hidden` en
  este entorno congela el rAF real del canvas WebGL — el popup CSS de
  bienvenida esquiva esto por completo, la pista 3D no).

**BudgetPixel:** seguía sin autorización (token expirado) al hacer este
trabajo — no se generó ningún asset de imagen/video nuevo. El auto de
bienvenida reutiliza las fotos de personajes que ya existen en
`public/themes/carreras/`.

**Temáticas:** solo `carreras` tiene assets reales hoy — es la única con
la que se pudo probar el 3D con imágenes de verdad. Las otras 9 siguen
vacías (tarea #11 del tablero, pendiente de Luis/Gemini); el código de
ambos efectos (popup y pista) ya es agnóstico de temática — lee colores
vía CSS vars y toma la imagen que exista — así que funcionarán solos en
cuanto esos assets aparezcan, pero eso no se pudo probar visualmente hoy.

## Iteración 3 — video real (Higgsfield) en vez de CSS

El auto de la Iteración 2 (CSS 3D) no convenció visualmente. Pedido final:
auto en movimiento real que termina con bandera de cuadros.

- Generado con **Higgsfield** (ya conectado, MCP `4d59566e-...`), no
  BudgetPixel (bloqueado en plan Free, requiere Premium $16-22/mes) ni
  Gemini directo (cuenta de facturación nueva con problemas de
  sincronización de cuota, sin resolver al momento de este trabajo).
- Flujo: imagen start-frame (`z_image`, 0.15 cr) → video image-to-video
  (`cinematic_studio_video_v2`, 5s, 7.5 cr) = **7.65 créditos totales**
  (~USD 0.15). Saldo Higgsfield usado: de 14.82 cr a ~7.17 cr.
- Archivo: `public/welcome-car.mp4` (4MB, 960×960, 5s, sin audio activo en
  el kiosco — se sirve `muted` por política de autoplay del navegador).
- `ListaInvitados` en `App.jsx`: el popup de bienvenida ahora reproduce
  este video (`autoPlay muted playsInline`) en vez del emoji giratorio.
  Timeout del popup extendido de 2.4s a 5.2s para que se vea el video
  completo incluida la bandera de cuadros final.
- `prefers-reduced-motion`: NO se reproduce el video (autoplay de video
  cuenta como movimiento) — fallback al emoji 🏎️ estático, igual que
  antes.
- Verificado en Chrome real: `readyState:4`, `paused:false`,
  `currentTime` avanzando, captura de pantalla confirma el auto y la
  bandera visibles dentro del popup.

**Pendiente para producción:** 4MB es aceptable para un kiosco local
(WAMP, misma red), pero pesado para un deploy web normal — si el auto no
convence del todo o se quiere optimizar, comprimir con ffmpeg antes de
subir a Hostinger (`docs/DEPLOY.md`), o generar una versión más corta/liviana.

## Deploy

Cambia el bundle: `index.html` referencia nuevos hashes de `assets/index-*.js`
y `assets/index-*.css`, y aparece el chunk nuevo `assets/three.module-*.js`.
Como con cualquier build: subir el contenido de `dist/` reemplaza estos
archivos; no hace falta ningún paso especial más allá del build normal.
Nada de esto toca PHP, `data/`, `themes/` ni `fotos/` — mismo aviso de
siempre: no sobrescribir esas carpetas en el servidor con la carga de
`dist/`.

## Iteración 4 — Misiones WOW 3D exclusivas del plan Full (2026-07-29)

Se añadió un juego premium real, no una transición pasiva. Después de la cadena
normal, el invitado puede aceptar una misión de 20 segundos con tres carriles:
desliza o toca izquierda/derecha, recoge objetos temáticos y esquiva obstáculos.
El jugador visible es el mismo personaje que salió en la ruleta, reutilizando su
recorte `*-cut.png`.

Mundos habilitados:

| Temática | Mundo | Coleccionable | Obstáculo |
|---|---|---:|---:|
| Carreras | `turbo-track` | ⚡ | 🛞 |
| Bluey | `puppy-park` | 🎈 | 🚧 |
| Aventura Tropical | `tropical-wave` | 🌺 | 🥥 |
| Reino de Hielo | `ice-bridge` | ❄️ | 🧊 |
| Guerreras K-Pop | `neon-stage` | ⭐ | 🔊 |
| Súper Héroes | `hero-city` | ⚡ | 💥 |

El gate es de servidor: `public/lib.php` publica `theme.fullGame` únicamente
cuando `party.service_plan` es `full`. El plan Booth recibe un objeto vacío, por
lo que no basta con manipular el frontend para activar el beneficio. El admin no
muestra `mundo3d` como casilla manual; Full lo agrega automáticamente como bonus
final y el operador puede seguir eligiendo los juegos normales.

`src/ThemeWorld3D.jsx` usa Three.js por importación dinámica, geometría de bajo
poligonaje, sin sombras ni postprocesado, DPR máximo 1.35 y limpieza explícita
del contexto WebGL. El HUD y los controles permanecen en DOM para conservar
legibilidad y blancos táctiles grandes en tablets de 7–10+ pulgadas.

El personaje no permanece pegado a la cámara: el recorte se renderiza sobre un
plano 3D transparente, inclina el cuerpo, hace zancada con squash/stretch,
deja pisadas luminosas y conserva una pose de tres cuartos hacia el carril
ocupado. La cámara acompaña lateralmente y amplía el campo visual al arrancar.
El giro se limita deliberadamente para no inventar una espalda inexistente a
partir de un recorte frontal.

Los seis mundos Full soportan atlas 2×2 de renders 3D multivista. Cuando está
aprobado, el personaje se ve de espaldas mientras avanza hacia la meta, usa
una pose lateral al cambiar de carril y solo vuelve a mirar al invitado en la
introducción/celebración. Los atlas viven en `themes/<slug>/game3d/`.
Cobertura actual: Carreras 6/6, Bluey 6/6, K-Pop 6/6 y Tropical 4/6. Hielo,
Héroes y los dos tropicales bloqueados conservan el recorte/JPG anterior; el
backend no publica rutas inexistentes. Prompt camuflado y cuadrantes:
`docs/PROMPTS-RUNNER-MULTIVISTA.md`.

Las fiestas locales de QA son `demo-carreras`, `demo-bluey`,
`demo-tropical`, `demo-hielo`, `demo-kpop` y `demo-heroes`. Todas usan plan
Full solo para demostrar el beneficio; el backend continúa ocultando
`theme.fullGame` a cualquier fiesta Booth.

QA local realizado:

- Frontend: 53/53 pruebas.
- Backend: 119/119 checks en PHP 8.0.30, 8.2.26, 8.3.14 y 8.4.0.
- Lint y smoke require: 44 archivos + 7 entrypoints en las cuatro versiones.
- Chrome real a 800×1280 y 1280×800: canvas 720×1280, controles táctiles,
  personaje recortado y HUD visibles; consola sin errores. Se corrigió una
  superposición de la insignia Full con el HUD en orientación horizontal.
- Evidencia local: `qa-evidence/full-3d/`.
- Segunda pasada Chrome real del movimiento: giro lateral persistente visible,
  pista y controles completos, consola sin errores (`04-running-center.png`,
  `05-running-turn-right.png`, `06-running-right-lane.png`).

No se consumieron créditos de Higgsfield. La herramienta integrada produjo 16
atlas nuevos aprobados; los casos bloqueados por seguridad conservaron el
fallback existente y no se reemplazaron por imitaciones genéricas.
