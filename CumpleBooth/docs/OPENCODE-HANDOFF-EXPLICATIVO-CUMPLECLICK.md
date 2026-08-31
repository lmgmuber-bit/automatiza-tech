# HANDOFF OPENCODE — Material explicativo "¿Qué es CumpleClick?"

**Fecha:** 2026-07-25 · **Autor del brief:** Claude · **Ejecutor:** OpenCode
**Objetivo:** producir el paquete explicativo del servicio (infografías + carrusel + video explicativo)
usando **pantallazos reales del kiosco** y el **logo real**, gastando **0 créditos de IA**.

**Ticket:** `Docs/ORCHESTRATION/AT-CUMPLECLICK-008.yaml` · **Clase:** C1 · **Riesgo:** bajo

---

## −1. ANTES DE LEER NADA MÁS — de dónde sacas el contexto

Este handoff te explica **la tarea**. No te explica el **producto** ni el **ecosistema**.
Esos viven en otro lado y son obligatorios. Léelos en este orden:

### 1. La bóveda compartida (fuente de verdad de todos los agentes)

`C:\Users\luis_\Documents\Codex\AI-Memory-Vault`

| Archivo | Qué te da |
|---|---|
| `10-Projects/CumpleClick.md` | **Empieza por aquí.** Qué es CumpleClick, arquitectura, estado real, reglas duras, trucos aprendidos |
| `30-Agent-Protocols/AGENTS.md` | Cómo se trabaja entre agentes |
| `30-Agent-Protocols/Multi-Agent-Workflow.md` | Ramas, locks, cierres de sesión |
| `10-Projects/Project-Index.md` | Mapa de todos los proyectos |

La bóveda es la **única memoria compartida** entre Claude, Codex, OpenCode y Copilot.
La memoria privada de Claude (`.claude/projects/…/memory/`) **tú no puedes leerla** — por eso todo
lo que importa tiene que estar en la bóveda. Si descubres algo importante, escríbelo ahí.

### 2. El tablero de orquestación

`C:\wamp64\www\automatiza-tech\Docs\ORCHESTRATION\`

| Archivo | Qué es |
|---|---|
| `current-handoff.yaml` | El lease: quién manda, qué está activo, riesgos conocidos |
| `AT-CUMPLECLICK-008.yaml` | **Tu ticket.** Alcance, gates, criterios de aceptación |
| `README.md` | Cómo funciona el tablero |
| `model-routing.yaml` | Clases C0-C3 y qué modelo va en cada una |

**Advertencia honesta:** el tablero se desactualiza. El 2026-07-25 se encontró que llevaba 8 días
describiendo un estado que ya no existía — pedía regenerar assets que hacía días estaban
terminados y aprobados. **Contrasta siempre el tablero con el filesystem real** antes de ejecutar.
Si el tablero te pide hacer algo que ya está hecho, no lo hagas: repórtalo.

### 3. El knowledge graph

`C:\wamp64\www\automatiza-tech\graphify-out\graph.json` — 4.331 nodos, 7.563 edges.
Cubre CumpleBooth porque está dentro del repo AT. Sirve para **ahorrar tokens**: consultas el grafo
en vez de leer archivos enteros.

```powershell
graphify query "cómo se generan los diplomas"
graphify explain "lib.php"
graphify path "App.jsx" "upload.php"
```

Tienes el recordatorio automático en `.opencode/plugins/graphify.js`.
**Ojo:** el grafo es del 2026-07-16, o sea anterior a buena parte del trabajo reciente. Úsalo para
orientarte, no como verdad sobre el estado actual.

### 4. Al cerrar tu sesión — obligatorio

1. Actualiza `Docs/ORCHESTRATION/AT-CUMPLECLICK-008.yaml` con el estado real.
2. Actualiza la sección de estado en `10-Projects/CumpleClick.md` del vault.
3. Escribe el cierre en `50-Daily-Logs/2026-07-XX.md` con este formato:
   ```md
   ## YYYY-MM-DD - OpenCode
   - Proyecto: CumpleClick (AT-CUMPLECLICK-008)
   - Qué se hizo:
   - Archivos tocados:
   - Decisiones:
   - Problemas:
   - Próximo paso recomendado:
   ```
4. Si tomaste una decisión de peso, regístrala en `40-Decisions/Decision-Log.md`.

**Esto es lo que permite que otro agente continúe donde tú te quedaste.** Sin esto, el próximo
agente empieza a ciegas — que es exactamente el problema que se detectó el 2026-07-25.

### Reglas de convivencia

- Rama nueva antes de tocar código (`feature/`, `fix/`, `chore/`).
- **Sin push, sin merge, sin deploy** sin permiso explícito de Luis.
- No sobrescribas la decisión de otro agente sin registrar el cambio y el motivo.
- Nunca guardes secretos, tokens ni contraseñas en el repo ni en la bóveda.

---

## 0. LO PRIMERO QUE TIENES QUE ENTENDER

Este paquete es **distinto de los reels** que ya existen. Los reels son *hook* de 10-14s para IG.
Esto es el material **explicativo**: la persona ya se interesó y ahora pregunta *"¿pero qué es
exactamente? ¿cómo funciona? ¿qué me llevo?"*. Sirve para:

- el sitio de clientes (ver `OPENCODE-HANDOFF-SITIO-CLIENTES.md`),
- responder por WhatsApp con una imagen en vez de un párrafo,
- carrusel de Instagram "fijado" en el perfil,
- un video de 45-60s para enviar a mamás/colegios/salones de eventos.

### 0.1 Regla de créditos (LA MÁS IMPORTANTE)

**El grueso de este trabajo es 0 créditos** y así debe quedarse: pantallazos reales, HTML→PNG con
la tipografía y el logo reales, y montaje con ffmpeg de clips que **ya están pagados y generados**.

Sobre generación con IA hay dos escenarios. **Averigua en cuál estás antes de empezar** —
prueba `get_credit_balance` / `show_plans_and_credits` y dilo en tu primer mensaje:

- **Sin acceso a Higgsfield/BudgetPixel** → haces §3, §4, §5 y §6. La §7 no la ejecutas: se la
  dejas a Claude o a Luis. Entregas y avisas.
- **Con acceso a Higgsfield** → además ejecutas la §7 y la §9, respetando al pie de la letra las
  reglas de crédito de la §9. **Ojo: los créditos son dinero real de Luis.**

Nunca gastes un crédito en algo que se puede hacer con HTML, ffmpeg o un pantallazo.

### 0.2 Regla del logo (INNEGOCIABLE)

**El logo NUNCA se genera con IA. Nunca.** Ni en un prompt, ni "de fondo", ni "aproximado".
Siempre se compone encima desde el vectorial real:

| Archivo | Cuándo |
|---|---|
| `design/logo/cumpleclick-globo-lockup.svg` | Portadas, cierres, cabeceras de infografía |
| `design/logo/cumpleclick-globo-mark.svg` | Marca de agua chica, esquina, favicon |
| `public/brand/cumpleclick-mark.svg` | El mismo isotipo servido por el kiosco |
| `design/logo/cumpleclick-logo-master-render.png` | Solo si necesitas el render con volumen (avatar, mockup) |

Reglas de uso obligatorias (`design/MANUAL-DE-MARCA.md` §2): zona de respeto = diámetro del aro
amarillo; isotipo mín. 24px, lockup mín. 120px de alto; **prohibido** rotar, estirar, recolorear,
poner texto encima del globo, o meterlo en una caja blanca sobre fondo oscuro.

### 0.3 Regla de nombres — CORREGIDA 2026-07-25, lee esto con cuidado

**El nombre genérico/camuflado es SOLO para los prompts de generación** (lo que se le manda a
Higgsfield/Gemini, para que el filtro no lo rechace). **El producto y el marketing público usan
el nombre REAL de la franquicia** — Bluey, Stitch — porque es lo que el papá busca y lo que el
niño reconoce; es parte de la venta, no algo que esconder.

Una versión anterior de este documento decía lo contrario ("jamás nombrar la franquicia en
marketing"). Estaba mal — corregido por Luis el 2026-07-25. Si ves esa regla en otro documento
viejo, no la sigas.

Entonces, en las piezas de este handoff:

- Los textos e infografías **sí pueden decir "Bluey", "Stitch"**, etc. cuando corresponda al tema.
- Los pantallazos y video **sí muestran a los personajes de cerca**, con su nombre en pantalla si
  el producto ya lo muestra así (el diploma y la foto real del kiosco ya dicen el nombre real).
- Lo único que sigue prohibido: escribir el nombre de la franquicia **dentro de un prompt de
  generación de imagen/video** (§7/§9) — ahí sigue aplicando la descripción física camuflada, para
  no activar el filtro del proveedor. Prompt ≠ pieza final: son dos cosas distintas con reglas
  distintas.
- `mickey` sigue siendo el slug interno; confirma con Luis si el nombre público es "Mickey" o
  "Casa del Ratón" antes de usarlo en una pieza — no lo asumas.

### 0.4 Regla de niños

Cero caras de niños reales. Si necesitas presencia humana: manos, espaldas, siluetas, o los
renders de personajes. Está en `design/social/ESPECIFICACIONES.md` y no se discute.

---

## 1. INVENTARIO — lo que YA existe y debes reutilizar

No regeneres nada de esto. Está pagado y aprobado.

### Clips de video (todos h264 + yuv420p + faststart)

```
design/video/clip-01-kiosco.mp4                 kiosco bajo arco de globos
design/video/clip-01-kiosco-alt-horizontal.mp4  la misma toma en horizontal
design/video/clip-02-flash.mp4                  el flash mágico del globo-lente
design/video/clip-03-endcard.mp4                bumper de cierre con la marca
design/video/campania-fase1/v1-clip-fiesta-detenida.mp4
design/video/campania-fase1/v3a-clip-sala-carreras.mp4
design/video/campania-fase1/v3b-clip-sala-bluey.mp4
design/video/campania-fase1/v4-clip-fechas.mp4
```

### Frames sueltos (PNG, sirven de fondo para infografías)

```
design/video/frame-sala-kiosco.png
design/video/frame-sala-kiosco-alt-horizontal.png
design/video/frame-flash-magico.png
design/video/frame-endcard.png
design/video/campania-fase1/v1-frame-fiesta-detenida.png
design/video/campania-fase1/v4-frame-fechas.png
```

### Reels terminados (referencia de estilo, no los toques)

```
design/video/reels/reel-00-presentacion.mp4 … reel-04-fechas.mp4
```

### Fuentes de verdad de marca

```
design/MANUAL-DE-MARCA.md      paleta, tipografía, voz, reglas de logo
design/tokens.css              variables CSS listas para copiar
design/social/ESPECIFICACIONES.md  tamaños IG, grilla de contenido
design/ESTRATEGIA-VIDEO-MARKETING.md  embudo TOFU/MOFU/BOFU
docs/BRAND-FILOSOFIA-CUMPLECLICK.md
```

### Paleta (memorízala, la vas a escribir mucho)

```
--violeta:  #8B5CF6    --tinta:  #4C2882    --fucsia: #D6307F
--amarillo: #FBBF24    --oro:    #E8A317    --crema:  #FFF8EC
--lila:     #A78BFA    --nudo:   #C2186B
```
Proporción obligatoria: **70% crema / 20% violeta-fucsia / 8% amarillo / 2% oro.**
El oro es condimento. Si tu pieza se ve dorada, está mal.

### Tipografía

**Baloo 2** — la real, ya está en el repo, no uses Google Fonts por `<link>`:

```
node_modules/@fontsource/baloo-2/files/baloo-2-latin-800-normal.woff2   (titulares)
node_modules/@fontsource/baloo-2/files/baloo-2-latin-600-normal.woff2   (UI, subtítulos)
node_modules/@fontsource/baloo-2/files/baloo-2-latin-400-normal.woff2   (cuerpo)
```

Embébelas en el HTML como `@font-face` con `url("file:///…")` absoluta o en base64.
Si una infografía sale en Arial, está mal hecha y se rehace.

---

## 2. ENTREGABLES (esto es lo que tienes que producir)

Crea las carpetas `design/screens/` y `design/explicativo/`.

| # | Pieza | Archivo | Tamaño | Créditos |
|---|---|---|---|---|
| **A** | Pantallazos del flujo real | `design/screens/*.png` | 768×1024 | 0 |
| **B** | Infografía "¿Cómo funciona?" (4 pasos) | `design/explicativo/info-01-como-funciona.png` | 1080×1350 | 0 |
| **C** | Infografía "Qué se lleva cada invitado" | `design/explicativo/info-02-que-se-lleva.png` | 1080×1350 | 0 |
| **D** | Infografía "Planes y precios" | `design/explicativo/info-03-planes.png` | 1080×1350 | 0 |
| **E** | Carrusel IG "Qué es CumpleClick" (6 láminas) | `design/explicativo/carrusel-01..06.png` | 1080×1350 | 0 |
| **F** | Video explicativo 45-60s | `design/explicativo/video-explicativo.mp4` | 1080×1920 | 0 |
| **G** | Versión horizontal del video (WhatsApp/web) | `design/explicativo/video-explicativo-16x9.mp4` | 1920×1080 | 0 |
| — | 6 imágenes IA de apoyo | §7, **NO las ejecutas** | — | 12 cr |

---

## 3. PIEZA A — Pantallazos reales del kiosco (haz esto PRIMERO)

Todo lo demás depende de estos. Son el corazón del material: la gente quiere ver el producto
real, no un render.

### 3.1 Levantar el entorno

WAMP debe estar corriendo. El kiosco vive en `http://localhost/automatiza-tech/CumpleBooth/dist/`.

### 3.2 Las 3 fiestas demo — YA EXISTEN, reales y permanentes (arregladas 2026-07-25)

No crees fiestas temporales ni las borres al terminar. Hasta hoy `gallery_enabled` estaba en `true`
pero sin PIN real (la galería igual daba 404), y las fiestas tenían 2-4 invitados nada más — se
corrigió: las 3 tienen ahora 10 invitados y una galería que funciona de verdad. Úsalas tal cual:

| Slug | Temática | URL kiosco | Galería | PIN |
|---|---|---|---|---|
| `demo` | Familia Canina (Bluey) | `?p=demo` | `galeria.php?p=demo` | `2026` |
| `demo-tropical` | Aventura Tropical (Lilo & Stitch) | `?p=demo-tropical` | `galeria.php?p=demo-tropical` | `2026` |
| `demo-carreras` | Carreras Veloces | `?p=demo-carreras` | `galeria.php?p=demo-carreras` | `2026` |

Si en algún momento necesitas más invitados o photos de otra temática, edítalas desde
`admin/` (pass `booth2026`) — no las borres ni cambies el slug, otros documentos y capturas ya las
referencian por nombre.

### 3.3 Las galerías están vacías de fotos — hay que sembrarlas primero

Las 3 tienen PIN y funcionan, pero **cero fotos** (nadie ha pasado por el flujo completo de estas
fiestas todavía). `screen-10-galeria.png` de cada tema saldría vacío si la capturas ahora. Antes de
ese pantallazo, para cada una de las 3 fiestas:

1. Completa el flujo real 2-3 veces (invitados distintos cada vez) hasta llegar a la foto y que
   quede subida — eso la agrega a la galería automáticamente.
2. **Sobre la cámara — lee esto con cuidado:** si capturas con Chrome usando
   `--use-fake-device-for-media-stream`, la "foto" sale un cuadro **verde** (el patrón sintético de
   Chrome), no una cara. Eso es exactamente lo esperado en automatización — no es un bug tuyo, no
   intentes arreglarlo con CSS ni con un mockup dibujado. Súbelas así, 2-3 por fiesta, para que la
   galería tenga contenido real (aunque sea un cuadro verde) y quede con el conteo correcto.
3. Después de sembrar las 3 galerías, recién ahí captura `screen-10-galeria-<tema>.png` (una por
   temática, no solo la de Bluey).
4. **Deja un aviso claro en tu reporte final** listando cuáles fotos quedaron con el cuadro verde
   Chrome — Claude o Luis las reemplaza después por fotos de ejemplo presentables (mismo mecanismo,
   solo se vuelve a subir una foto distinta con el mismo flujo). No es tu responsabilidad conseguir
   caras reales para esto — el cuadro verde es la entrega correcta de tu parte.

### 3.4 Las capturas que necesito

Viewport **768×1024** (tablet vertical, que es como se usa de verdad), `deviceScaleFactor: 2`.
Repite las filas marcadas (*) una vez por cada una de las 3 fiestas de la §3.2.

| Archivo | Pantalla | Cómo llegar |
|---|---|---|
| `screen-01-intro-<tema>.png` (*) | Portada con el nombre del cumpleañero | Carga inicial |
| `screen-02-invitados-<tema>.png` (*) | Grilla de invitados para elegir quién eres | Toca "Empezar" |
| `screen-03-ruleta-<tema>.png` (*) | La ruleta girando con los personajes | Elige un invitado |
| `screen-04-personaje-<tema>.png` (*) | El personaje saludando en video | Espera a que pare la ruleta |
| `screen-05-captura-<tema>.png` (*) | Cámara con el personaje al lado y la cuenta regresiva | Continúa |
| `screen-06-preview-<tema>.png` (*) | La foto lista con el marco de la temática | Después del flash |
| `screen-07-qr.png` | El QR para descargar la foto al celular | Toca "Compartir" (una sola vez basta) |
| `screen-08-diploma.png` | El diploma con el nombre del invitado | Continúa (una sola vez basta) |
| `screen-09-diploma-qr.png` | El diploma con su QR de descarga | Toca "Descargar diploma" (una sola vez basta) |
| `screen-10-galeria-<tema>.png` (*) | La galería de los papás, ya sembrada (§3.3) | `galeria.php?p=<slug>` + PIN `2026` |
| `screen-11-admin.png` | El backoffice (vista de fiestas) | `admin/` — **tapa la contraseña** |

`<tema>` = `bluey`, `tropical`, `carreras` según la fiesta de la §3.2.

**Nota:** más abajo (§4, §5) vas a ver referencias sueltas como `screen-02-invitados.png` sin
sufijo de tema — para esas piezas (que muestran UN solo flujo, no comparan temáticas) usa siempre
la versión `bluey` (ej. `screen-02-invitados-bluey.png`) como la representativa por defecto, salvo
que la pieza pida explícitamente otra cosa.

**Sobre `screen-05-captura`:** va a mostrar el cuadro verde de Chrome si usaste la cámara sintética
— está bien, es lo esperado (mismo criterio de la §3.3). No inventes un mockup de la cámara ni lo
dibujes en HTML: si no logras la captura real, deja el archivo faltante y decláralo en el reporte
final. Un pantallazo falso es peor que uno faltante.

### 3.5 Cómo capturar

**Ruta recomendada — puppeteer-core** (usa el Chrome que ya está instalado, no descarga nada):

```bash
npm i -D puppeteer-core
```

Chrome está en `C:\Program Files\Google\Chrome\Application\chrome.exe`.

Escribe el script en `scripts/capture-screens.mjs`. Esqueleto:

```js
import puppeteer from 'puppeteer-core'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'design/screens'

const browser = await puppeteer.launch({
  executablePath: CHROME,
  headless: false,               // el kiosco usa video/audio; headless da problemas
  args: [
    '--use-fake-device-for-media-stream',
    '--use-fake-ui-for-media-stream',
    '--autoplay-policy=no-user-gesture-required',
  ],
  defaultViewport: { width: 768, height: 1024, deviceScaleFactor: 2 },
})
const page = await browser.newPage()
await page.goto(`${BASE}/?p=demo`, { waitUntil: 'networkidle2' })
await page.screenshot({ path: `${OUT}/screen-01-intro.png` })
// … avanza el flujo con page.click() y esperas explícitas
```

Avanza con selectores/textos reales — léelos de `src/App.jsx`, no los adivines.
Espera con `page.waitForSelector()`, **no** con `setTimeout` a ciegas.

**Ruta alternativa si puppeteer te da guerra:** captura a mano con Chrome (F12 → device toolbar →
768×1024 → menú ⋮ → "Capture screenshot"). Es igual de válido y toma 10 minutos.

### 3.6 Antes de seguir

Revisa cada PNG con tus propios ojos. Descarta el que salga con un spinner a medias, un video en
negro, o texto cortado. Estos pantallazos se van a ver ampliados en el sitio: si se ven mediocres,
el servicio se ve mediocre.

---

## 4. PIEZAS B/C/D/E — Infografías y carrusel (HTML → PNG)

### 4.1 El método

Un HTML por pieza en `design/explicativo/src/`, renderizado a PNG con Chrome headless.
Determinista, editable, versionable y con la tipografía real. **Nada de generar texto con IA:
la IA escribe mal en español y deforma las letras.**

```bash
"/c/Program Files/Google/Chrome/Application/chrome.exe" \
  --headless --disable-gpu --force-device-scale-factor=1 \
  --screenshot="design/explicativo/info-01-como-funciona.png" \
  --window-size=1080,1350 \
  --default-background-color=00000000 \
  "file:///C:/wamp64/www/automatiza-tech/CumpleBooth/design/explicativo/src/info-01.html"
```

Notas: `--default-background-color=00000000` solo si quieres fondo transparente (para overlays).
Para las infografías el fondo es **crema opaco**. Y el `<body>` debe medir exacto 1080×1350 con
`margin:0`, si no Chrome recorta mal.

### 4.2 Base común de estilos

Crea `design/explicativo/src/base.css` y compártela entre todas las piezas:

```css
@font-face{font-family:"Baloo 2";font-weight:800;font-display:block;
  src:url("file:///C:/wamp64/www/automatiza-tech/CumpleBooth/node_modules/@fontsource/baloo-2/files/baloo-2-latin-800-normal.woff2") format("woff2")}
@font-face{font-family:"Baloo 2";font-weight:600;font-display:block;
  src:url("file:///C:/wamp64/www/automatiza-tech/CumpleBooth/node_modules/@fontsource/baloo-2/files/baloo-2-latin-600-normal.woff2") format("woff2")}
@font-face{font-family:"Baloo 2";font-weight:400;font-display:block;
  src:url("file:///C:/wamp64/www/automatiza-tech/CumpleBooth/node_modules/@fontsource/baloo-2/files/baloo-2-latin-400-normal.woff2") format("woff2")}

:root{--violeta:#8B5CF6;--tinta:#4C2882;--fucsia:#D6307F;--amarillo:#FBBF24;
      --oro:#E8A317;--crema:#FFF8EC;--lila:#A78BFA;--nudo:#C2186B}

*{margin:0;padding:0;box-sizing:border-box}
body{width:1080px;height:1350px;background:var(--crema);font-family:"Baloo 2",system-ui;
     color:var(--tinta);overflow:hidden}
```

Los pantallazos van dentro con `<img src="file:///…/design/screens/screen-0X.png">`, siempre
dentro de un marco de tablet redondeado (`border-radius:28px; border:10px solid var(--tinta);
box-shadow:0 24px 60px rgba(76,40,130,.22)`). Un pantallazo suelto flotando se ve pobre; dentro de
un marco de tablet se lee como producto.

### 4.3 Pieza B — "¿Cómo funciona?" (1080×1350)

Cabecera: lockup del logo (140px de alto) + titular **"Así funciona CumpleClick"**.
Cuerpo: 4 pasos en grilla 2×2, cada uno con número en círculo fucsia, pantallazo enmarcado, título
y una línea de texto.

| Paso | Título | Texto | Pantallazo |
|---|---|---|---|
| 1 | Elige quién eres | Cada invitado toca su nombre en la pantalla | `screen-02-invitados.png` |
| 2 | La ruleta decide | Un personaje de la temática sale a recibirlo | `screen-03-ruleta.png` |
| 3 | ¡Click! | Se toma la foto junto a su personaje favorito | `screen-06-preview.png` |
| 4 | Se la lleva | Escanea el QR y la foto queda en su celular | `screen-07-qr.png` |

Pie: isotipo chico + `automatizatech.cl` o el WhatsApp (confirma con Luis cuál va).

### 4.4 Pieza C — "Qué se lleva cada invitado" (1080×1350)

Titular: **"Cada invitado se va con algo en la mano"**.
Tres bloques verticales, imagen a la izquierda y texto a la derecha:

1. **Su foto con el personaje** — marco de la temática, lista para imprimir. → `screen-06-preview.png`
2. **Un diploma con su nombre** — impreso o al celular, con su propio QR. → `screen-08-diploma.png`
3. **El álbum para los papás** — galería privada con PIN, todas las fotos de la fiesta. → `screen-10-galeria.png`

### 4.5 Pieza D — "Planes y precios" (1080×1350)

Dos tarjetas, la Premium destacada con borde fucsia y una cinta "Más elegido".

| | Mágico | Premium |
|---|---|---|
| Precio | **$69.990** | **$99.990** |
| Temáticas | 1 a elección | 1 a elección |
| Fotos | Ilimitadas | Ilimitadas |
| Diplomas | Sí | Sí |
| Galería para papás | Sí | Sí |

Al pie, badge: **"¿Quieres una temática a la medida? +$25.000"**.

**Verifica los precios y qué diferencia realmente a Premium de Mágico con Luis antes de publicar
esta pieza.** El manual §7 tiene los precios; la lista de features de arriba es mi reconstrucción y
puede estar incompleta. Si no lo confirmas, entrega la pieza pero **márcala como borrador** en el
reporte.

### 4.6 Pieza E — Carrusel IG (6 láminas, 1080×1350)

Mismo sistema, una lámina por HTML:

1. **Portada** — fondo `frame-sala-kiosco.png` oscurecido + lockup + "¿Qué es CumpleClick?" + "Desliza →"
2. **El problema** — "En cada cumpleaños hay 20 niños… y ninguna foto de ellos." Fondo crema, tipografía grande.
3. **La idea** — "Un photo booth que los reconoce por su nombre." + `screen-01-intro.png`
4. **El momento** — "Su personaje favorito sale a recibirlo." + `screen-04-personaje.png`
5. **El recuerdo** — "Se lleva su foto y su diploma." + `screen-08-diploma.png`
6. **Cierre / CTA** — Lockup grande, "Agenda la fecha por WhatsApp 📲", precios chicos abajo.

Voz de marca: `MANUAL-DE-MARCA.md` §5. Habla de recuerdos, no de tecnología. **Prohibido** escribir
"IA", "software", "app" o "sistema" en una pieza pública.

---

## 5. PIEZAS F/G — El video explicativo (ffmpeg, 0 créditos)

45-60s, 1080×1920, montaje de material existente + los pantallazos + overlays de texto.

### 5.1 Guion (escaleta con tiempos)

| t | Visual | Texto en pantalla |
|---|---|---|
| 0.0-3.5 | `v1-clip-fiesta-detenida.mp4` | "En cada cumpleaños pasa lo mismo…" |
| 3.5-7.0 | mismo clip, corte | "20 niños. Cero fotos de ellos." |
| 7.0-11.0 | `clip-01-kiosco.mp4` | "CumpleClick" (lockup entra) |
| 11.0-16.0 | `screen-01-intro` → `screen-02-invitados` (push-in) | "1. Cada invitado toca su nombre" |
| 16.0-21.0 | `screen-03-ruleta` (zoom lento) | "2. La ruleta elige su personaje" |
| 21.0-26.0 | `screen-04-personaje` | "Y sale a recibirlo por su nombre" |
| 26.0-31.0 | `clip-02-flash.mp4` | "3. ¡Click!" |
| 31.0-36.0 | `screen-06-preview` | "Su foto, con el marco de la temática" |
| 36.0-41.0 | `screen-07-qr` + `screen-08-diploma` (split o corte) | "4. Se la lleva al celular. Y su diploma." |
| 41.0-46.0 | `screen-10-galeria` | "Los papás reciben el álbum completo" |
| 46.0-52.0 | `v3a-clip-sala-carreras` + `v3b-clip-sala-bluey` | "11 temáticas para elegir" |
| 52.0-60.0 | `clip-03-endcard.mp4` | "Agenda tu fecha 📲" + precios |

### 5.2 Reglas técnicas (las mismas de todo el proyecto)

- Salida: `libx264`, `-pix_fmt yuv420p`, `-movflags +faststart`, 25fps.
- **Sin audio**, igual que los reels — la música se agrega nativa en IG.
  Para la versión 16:9 de WhatsApp, pregúntale a Luis si quiere música;
  **él genera todos los MP3, no gastes créditos en música jamás.**
- Overlays de texto: **NO uses `drawtext`**. Renderiza cada overlay como PNG transparente con el
  mismo pipeline HTML→Chrome de la §4 (así queda la Baloo 2 real) y móntalos con `overlay`.
  Es exactamente cómo se hicieron los reels.
- Los pantallazos son estáticos: dales vida con un push-in suave
  (`zoompan=z='min(zoom+0.0006,1.12)':d=125:s=1080x1920`), no los dejes congelados.
- Transiciones: corte seco o `xfade=transition=fade:duration=0.25`. Nada de estrellitas ni wipes.

### 5.3 Versión G (16:9)

No recortes el 9:16. Remonta con los clips en horizontal donde existan
(`clip-01-kiosco-alt-horizontal.mp4`) y los pantallazos centrados sobre fondo crema con el isotipo
en una esquina. El texto se reposiciona, no se estira.

### 5.4 Verifica antes de entregar

```bash
ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt,r_frame_rate \
        -show_entries format=duration -of default=noprint_wrappers=1 \
        design/explicativo/video-explicativo.mp4
```

Debe decir `h264`, `1080x1920`, `yuv420p`. Ábrelo y míralo entero. Si un texto se corta, se
solapa, o un pantallazo aparece un frame en negro, se arregla antes de entregar.

---

## 6. ORDEN DE TRABAJO Y CIERRE

1. §3 pantallazos (bloquea todo lo demás)
2. §4.2 base.css + §4.3 pieza B ← **muéstrale la B a Luis antes de seguir.** Si el estilo no le
   gusta, mejor descubrirlo en una pieza que en nueve.
3. §4.4 y §4.5 (C y D)
4. §4.6 carrusel (E)
5. §5 videos (F y G)
6. Restaura `parties.json`, borra los `.bak`, corre `php scripts/check-dist-parity.php`
7. Escribe `design/explicativo/README.md`: qué es cada archivo, para qué canal, y cómo regenerarlo
   (con los comandos exactos, igual que `design/video/reels/README.md`)

### Lo que NO debes hacer

- No toques `src/`, `public/` ni `dist/` salvo la fiesta temporal de §3.2 (que restauras).
  Este trabajo vive **solo** en `design/`.
- No instales nada global. `puppeteer-core` va como `devDependency` y es la única dependencia nueva autorizada.
- No inventes datos: fechas disponibles, cantidad de fiestas hechas, testimonios. Si no existe, no se escribe.
- No generes imágenes con IA. No tienes acceso y no es necesario.

### Tu reporte final debe decir

- Qué archivos creaste (ruta exacta).
- Qué pantallazos **no** lograste y por qué.
- Qué piezas quedan **marcadas como borrador** por datos sin confirmar (mínimo la D, §4.5).
- El `ffprobe` de los dos videos, pegado tal cual.

**Sé honesto.** Si algo no te resultó, dilo. Una pieza faltante se hace en 20 minutos; una pieza
que dijiste que estaba lista y no lo estaba se descubre delante de un cliente.

---

## 7. ANEXO — Prompts de imágenes IA (NO los ejecutas tú)

Para Claude o para Luis en Higgsfield. Modelo: `nano_banana_pro`, **2 cr cada una**, 12 cr total.
Ejecutar con `get_cost: true` primero. Los rechazos por filtro **no cobran**, así que reintentar es gratis.

Reglas que aplican a las 6:
- **Sin logo, sin texto, sin letras.** El logo se compone después desde el SVG (§0.2). Los modelos
  deforman el texto en español y arruinan la marca.
- **Sin caras de niños** — manos, espaldas, siluetas, desenfoque.
- **Sin personajes con derechos** — nada reconocible de ninguna franquicia.
- Paleta de marca en el prompt, siempre.

Guardar en `design/explicativo/ia/`.

---

**IMG-1 — Hero: el kiosco en la fiesta** (para portada del sitio y lámina 1 del carrusel)

```
Photorealistic product photograph, 4:5 vertical. A modern white tablet on an elegant
matte-white floor stand, positioned in a bright children's birthday party room. The tablet
screen glows softly (screen content blurred and indistinct, no readable text). Around it,
a lush balloon arch in cream, violet #8B5CF6, magenta #D6307F and warm yellow #FBBF24.
Soft confetti on a cream #FFF8EC wall behind. Warm afternoon window light from the left,
shallow depth of field, gentle bokeh on the background balloons. Premium, clean, joyful,
not cluttered. No people, no text, no logos, no letters anywhere.
```

**IMG-2 — Manos de niño en la pantalla** (paso 1)

```
Photorealistic close-up, 4:5 vertical. Two small child hands reaching up to touch a tablet
screen mounted on a white stand. Shot from behind and slightly above the child; only the
hands and a bit of the shoulder are visible, the face is completely out of frame. The tablet
screen glows with soft violet and magenta light, content blurred and unreadable. Cream
#FFF8EC background with soft party balloons out of focus. Warm natural light, shallow depth
of field, tender and premium mood. No faces, no text, no logos, no letters.
```

**IMG-3 — La foto en el celular de la mamá** (paso 4)

```
Photorealistic close-up, 4:5 vertical. An adult hand holding a modern smartphone at a
children's party. The phone screen is a soft glowing white rectangle, deliberately blank
and out of focus (no visible image, no text, no UI). Background: warm cream party room with
violet and magenta balloons in soft bokeh. Golden hour light, shallow depth of field, warm
and emotional. No faces, no text, no logos, no letters.
```

**IMG-4 — El diploma en las manos** (pieza C)

```
Photorealistic close-up, 4:5 vertical. Small child hands proudly holding a blank cream-white
certificate card with a subtle gold border, held up toward the camera. The card face is
completely blank — no text, no seal, no illustration. Behind: soft-focus birthday party in
cream #FFF8EC, violet #8B5CF6 and magenta #D6307F tones, balloons out of focus. Warm light,
shallow depth of field, joyful and premium. No faces, no text, no logos, no letters.
```

**IMG-5 — Textura de fondo con confeti** (fondo de las infografías)

```
Flat lay background texture, 4:5 vertical, seamless and airy. Cream #FFF8EC paper surface
with scattered small paper confetti pieces in violet #8B5CF6, magenta #D6307F, soft lilac
#A78BFA and warm yellow #FBBF24, concentrated at the corners and sparse in the center to
leave clean empty space for text. Very soft top-down light, subtle paper grain, gentle
shadows. Premium stationery mood, minimal, uncluttered. No objects, no text, no logos.
```

**IMG-6 — Mesa dulce con la tablet** (portada del carrusel, versión alternativa)

```
Photorealistic wide shot, 4:5 vertical. An elegant children's birthday dessert table seen
from the front: cream #FFF8EC tablecloth, a two-tier cake, macarons and cupcakes in violet
#8B5CF6 and magenta #D6307F, and at the right end a white tablet on a slim stand with a
softly glowing blank screen. Balloon garland above in cream, violet, magenta and warm yellow
#FBBF24. Warm even light, premium party styling, clean composition with empty space in the
upper third. No people, no text, no logos, no letters.
```

---

## 8. CHECKLIST FINAL

- [ ] Pantallazos capturados y revisados uno por uno
- [ ] Baloo 2 real en todas las piezas (ninguna en Arial/system-ui por accidente)
- [ ] Logo compuesto desde el SVG en todas, nunca dibujado ni generado
- [ ] Prompts de generación (§7/§9) no nombran la franquicia — las piezas finales sí pueden
- [ ] Ninguna cara de niño real
- [ ] Proporción 70/20/8/2 respetada (nada se ve dorado)
- [ ] Cero jerga técnica en textos públicos
- [ ] `parties.json` restaurado y `.bak` borrados
- [ ] `php scripts/check-dist-parity.php` en verde
- [ ] `ffprobe` de F y G pegado en el reporte
- [ ] `design/explicativo/README.md` escrito con los comandos de regeneración
- [ ] Piezas con datos sin confirmar marcadas como borrador
- [ ] Si ejecutaste §9: comparativa IA-vs-HTML entregada y saldo de créditos reportado

---

## 9. SI TIENES ACCESO A HIGGSFIELD — reglas duras + prueba de infografía con IA

### 9.1 Reglas de crédito (romper una de estas es gastar plata de Luis)

1. **`get_cost: true` SIEMPRE antes de generar.** Sin excepción. Reporta el costo antes de disparar.
2. **Nunca generes música.** Luis genera todos los MP3 él mismo. Cero excepciones.
3. **Los rechazos por filtro (nsfw/failed) NO cobran** — verificado comparando saldo. Reintentar es
   gratis. Pero si el mismo prompt falla **3 veces**, el filtro es consistente, no aleatorio:
   **detente y reporta.** No quemes tiempo insistiendo (hubo un caso de 6 intentos fallidos con
   3 modelos distintos — el filtro no cedió nunca).
4. **Imagen primero, video después.** Un video malo cuesta 7.5 cr; la imagen que lo origina cuesta 2.
   Aprueba el frame antes de animarlo.
5. **Reporta el saldo** al empezar y al terminar. Si el gasto pasa de **20 cr**, detente y pregunta.
6. Costos de referencia: `nano_banana_pro` 2 cr · `cinematic_studio_video_v2` y `kling3_0_turbo`
   7.5 cr · `remove_background` gratis.

### 9.2 REGLA PERMANENTE DEL PROYECTO — todo prompt que funciona se guarda en la BD

Vale para **cualquier** agente (Claude, Codex, OpenCode) y para **cualquier** asset del proyecto.

Si generas un asset que se aprueba, **su prompt exacto se guarda** con `cb_save_theme_prompt()`
(tabla `cc_theme_prompts` + historial versionado en `cc_theme_prompt_history`). Detalle completo en
`OPENCODE-HANDOFF-STITCH-Y-MODAL.md` §0.1.

Dos advertencias ganadas a golpes:

- **Guarda el prompt REAL, el que efectivamente generó la imagen aprobada.** Hubo un caso donde se
  guardó texto de otro personaje y se reportó como éxito; se descubrió al abrir las imágenes y hubo
  que reconstruir los prompts a mano. Si no tienes el prompt exacto, **di que no lo tienes.**
- Los assets de marketing de `design/` **no** van a `cc_theme_prompts` (esa tabla es de temáticas
  del kiosco). Los prompts de marketing van documentados en `design/explicativo/README.md`, con el
  modelo, el costo y la fecha.

### 9.3 La prueba: ¿Nano Banana Pro sirve para las infografías?

Mi instrucción original decía "las infografías nunca con IA porque el texto en español se deforma".
**Eso ya no es automáticamente cierto:** `nano_banana_pro` (Gemini 3 Pro Image) renderiza texto
mucho mejor que la generación anterior y acepta imágenes de referencia. Así que **hay que probarlo,
no opinarlo.**

Pero la duda de fondo sigue en pie y es la que la prueba tiene que responder:

| | HTML→PNG | Nano Banana Pro |
|---|---|---|
| Texto y tildes exactos | Garantizado | Hay que verificar letra por letra |
| Precio correcto | Garantizado | Hay que verificar dígito por dígito |
| Logo idéntico al SVG | Garantizado | **Lo redibuja** — nunca sale idéntico |
| Cambiar "$69.990" el próximo mes | Editar 1 línea, 0 cr | Regenerar todo, 2 cr, y vuelve a cambiar todo lo demás |
| Belleza / textura / profundidad | Limitada | Muy superior |

Por eso mi apuesta es **híbrido**, y es lo que la prueba debe medir:
**IA para el fondo y la atmósfera + HTML encima para texto, precios y logo.**
Lo mejor de los dos, y editable para siempre.

### 9.4 Protocolo de la prueba (6 cr máximo)

Genera **3 imágenes**, una por variante, con la MISMA pieza (la infografía "Cómo funciona"):

- **V-A — IA completa**: prompt `TEST-A` de la §9.5. La IA hace todo, texto incluido.
- **V-B — IA de fondo + HTML encima**: prompt `TEST-B` (fondo sin texto) y montas encima el HTML
  de la §4.3 con fondo transparente.
- **V-C — HTML puro**: la §4.3 tal cual, sin IA. **0 cr.**

Ponlas lado a lado en `design/explicativo/prueba-infografia/` y **entrégale las tres a Luis para
que él elija.** No decidas tú.

Al evaluar V-A, revisa con lupa y repórtalo explícitamente:
- ¿Las tildes y la ñ están bien? ("Cómo", "Elige", "número")
- ¿El precio dice exactamente `$69.990`? (los modelos inventan dígitos)
- ¿El logo quedó idéntico al SVG o lo redibujó?
- ¿Hay texto inventado que tú no pediste? (pasa mucho)

### 9.5 Prompts de la prueba

**TEST-A — infografía completa con texto (2 cr)**

Referencias a adjuntar: `screen-02-invitados.png`, `screen-03-ruleta.png`, `screen-06-preview.png`,
`screen-07-qr.png` y `design/logo/cumpleclick-logo-master-render.png`. Aspect ratio **4:5**, 2K.

```
Create a clean, premium infographic poster for a children's birthday photo booth service,
4:5 vertical, for Instagram.

Background: warm cream #FFF8EC with a few soft paper confetti pieces in violet #8B5CF6,
magenta #D6307F and warm yellow #FBBF24 scattered in the corners only, leaving the center airy.

At the top, place the provided balloon-camera logo image, centered, exactly as given.
Below it, a large rounded bold headline in dark violet #4C2882 reading exactly:
"Así funciona CumpleClick"

Below the headline, a 2x2 grid of four steps. Each step shows one of the four provided tablet
screenshots, displayed inside a rounded white tablet frame with a soft violet shadow, with a
small magenta #D6307F circle containing the step number, and a short caption underneath.
Use these four steps in this exact order, with this exact Spanish text:

1. "Elige quién eres"
2. "La ruleta decide"
3. "¡Click!"
4. "Se la lleva"

Typography: a rounded friendly sans-serif, bold, similar to Baloo 2. All text must be perfectly
spelled Spanish with correct accents. Do not add any text that is not listed above.

Style: clean, premium, generous white space, soft shadows, flat design with subtle depth.
Not cluttered, not childish-cheap, no cartoon clipart, no rainbow gradients.
```

**TEST-B — solo el fondo, sin nada de texto (2 cr)**

Sin referencias. Aspect ratio **4:5**, 2K.

```
Create a premium empty infographic background, 4:5 vertical, for a children's birthday brand.

Warm cream #FFF8EC paper surface with subtle grain. Soft paper confetti pieces in violet
#8B5CF6, magenta #D6307F, soft lilac #A78BFA and warm yellow #FBBF24, densest at the top-left
and bottom-right corners, fading to completely empty clean space across the whole center.
A very subtle violet radial glow behind the center. Gentle soft shadows, elegant stationery
feel, airy and uncluttered.

Absolutely no text, no letters, no numbers, no logos, no characters, no objects.
The entire center must stay clean and empty for text to be placed on top later.
```

**TEST-C — portada de carrusel, titular grande (2 cr)**

Referencia: `design/logo/cumpleclick-logo-master-render.png`. Aspect ratio **4:5**, 2K.

```
Create a premium Instagram carousel cover, 4:5 vertical, for a children's birthday photo
booth brand.

Background: a softly blurred children's birthday party room in warm cream #FFF8EC tones, with
a balloon arch in violet #8B5CF6, magenta #D6307F and warm yellow #FBBF24, heavily out of
focus with creamy bokeh. A soft cream overlay makes the background gentle so text reads clearly.

Centered, place the provided balloon-camera logo image exactly as given, at a generous size.
Below it, a large rounded bold headline in dark violet #4C2882 reading exactly:
"¿Qué es CumpleClick?"

At the bottom, small rounded text in magenta #D6307F reading exactly:
"Desliza →"

Typography: rounded friendly bold sans-serif, similar to Baloo 2, perfect Spanish spelling
with correct accents and the opening question mark. Do not add any other text.

Style: premium, warm, joyful, lots of breathing room. No people, no children's faces,
no cartoon characters, no clipart.
```

### 9.6 Si la prueba sale bien

Si Luis elige V-A o V-B, aplica el mismo tratamiento a las piezas C, D y E — **pero la pieza D
(precios) se hace SIEMPRE en HTML**, gane quien gane la prueba. Un precio mal renderizado por un
modelo generativo es un problema comercial, no estético, y esa pieza va a cambiar cada mes.
