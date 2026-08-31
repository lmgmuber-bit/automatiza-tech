# Handoff Codex — Temáticas «Guerreras K-Pop» y «Súper Héroes» (Vengadores)

> **ESTADO LOCAL 2026-07-26:** contratos K-Pop/Héroes, juegos `ritmo`/`escudo`,
> demos, slots del admin y prompts camuflados implementados. La multimedia de
> ambas temáticas sigue pendiente de carga manual por Luis; por eso las demos
> `demo-kpop` y `demo-heroes` son de preproducción y no deben considerarse QA
> visual aprobado hasta completar el inventario. Todos los prompts productivos
> tienen el cierre de camuflaje obligatorio y cero términos reservados; los
> `puzzle-*.jpg` se derivan localmente y no consumen una generación. No se
> consumieron créditos.
>
> **Contexto completo del proyecto (leer antes de generar nada):**
> `docs/CUMPLECLICK-HANDOFF-CODEX.md`. Ese documento es la fuente de verdad de
> todo lo demás en CumpleClick — arquitectura, whitelist de `lib.php`,
> convenciones de nombres, y el bloque "ACTUALIZACIÓN 2026-07-26" al inicio
> resume exactamente el mismo estado que este handoff: **contratos y QA a nivel
> de código ya están; multimedia en cero**. Este handoff (K-Pop/Héroes) es
> justamente el que llena ese hueco.
>
> **⚠️ REGLA DE ORDEN, no negociable: FOTOS primero, VIDEOS después — por
> personaje, no por lote.** No generes el saludo en video de un personaje
> cuya foto (`<personaje>.jpg`) todavía no exista o no esté aprobada. Un video
> parte de una `start_image`; sin la foto final no hay de dónde partir, y
> regenerar la foto después obliga a regenerar el video (gasto duplicado). El
> mismo orden aplica a las imágenes de FONDO DE LOS JUEGOS
> (`fondo-juego-escenario.jpg`, `fondo-juego-ciudad.jpg`, §5.1/§5.3): **no son
> un placeholder ni un color de relleno — son una generación de Higgsfield como
> cualquier otra**, con su propio prompt ya escrito más abajo, y tienen que
> existir ANTES de poder probar el juego nuevo (`ritmo`/`escudo`) de verdad.
> Si le falta la imagen de fondo, el juego se ve roto aunque el código esté
> perfecto — no lo dejes para el final asumiendo que "ya se generará solo".

> **Autor del encargo:** Luis · **Preparado por:** Claude · **Fecha:** 2026-07-26
> **Ejecutor:** Codex · **Proyecto:** `C:\wamp64\www\automatiza-tech\CumpleBooth`
> **Estado:** listo para ejecutar. No requiere decisiones de negocio adicionales.

---

## 0. Resumen ejecutivo

Agregar **dos temáticas completas** al kiosco CumpleClick:

| Slug | Nombre genérico (público) | Franquicia real (solo admin) | Público | Estado previo |
|------|---------------------------|------------------------------|---------|----------------|
| `kpop` | **Guerreras K-Pop** | KPop Demon Hunters | niña / mixto | ❌ no existe, se crea de cero |
| `heroes` | **Súper Héroes** | Marvel / Avengers | niño / mixto | ⚠️ **ya existe la entrada en `themes.json`** con 6 personajes definidos y **0 assets**. Se EXTIENDE, no se recrea. |

Cada temática debe quedar con:
- Set de imágenes completo (banner, sala, 6 personajes, 6 recortes PNG, referencias de puzzle, fondo de juego).
- **Experiencia inmersiva propia** (el «wow»): cómic que cobra vida para Súper Héroes, entrada a escenario de concierto para Guerreras K-Pop.
- **Juegos por personaje**: puzzle de fichas para varios + **un juego nuevo de alto impacto por temática**.
- Videos de saludo, bienvenida, revelación y despedida.
- Narración de invitación al juego (voz Alice, una por personaje).

---

## 1. ⚠️ REGLA INNEGOCIABLE: camuflaje de prompts

**Leer `docs/CUMPLECLICK-HANDOFF-CODEX.md` §5 antes de generar cualquier imagen.**

Resumen operativo:

1. **NUNCA** nombres la franquicia ni un personaje con copyright en un prompt de generación de imagen/video. Los generadores bloquean el pedido.
2. Describe al personaje por **rasgos físicos**, como diseño original de juguete coleccionable.
3. Cierra siempre con: `This is an original toy design, not based on any existing character.`
4. Si igual bloquea: (a) revisa que no quedó ningún nombre colado, (b) simplifica el rasgo más reconocible, (c) descríbelo aún más genérico.
5. **Regla de 3 fallos:** si el mismo prompt+modelo falla 3 veces de forma consistente, **pará** y cambiá de técnica (otro modelo, o generar video mudo + mux de audio local). No sigas quemando intentos.
6. Los prompts de este documento **ya vienen camuflados**. Úsalos tal cual.

**Regla de oro secundaria:** `fondo-banner.jpg` y `fondo-sala.jpg` **NUNCA** llevan texto pintado. El nombre del cumpleañero lo pone la app por encima. Todos los prompts de abajo ya incluyen el bloque `IMPORTANT: no text...` — no lo quites.

**Dónde vive cada nombre:**
- `themes.json` → `nombre`: el nombre genérico (lo ve el niño).
- `themes.json` → `franquicia`: la franquicia real. **Solo se usa en el admin**, `api.php` NO la expone al frontend (ya verificado). No la muestres en ninguna pantalla pública.

---

## 2. Arquitectura relevante (lo que necesitás saber)

### 2.1 Contrato de datos

`api.php?p=<slug>` devuelve `{party, theme}`. El objeto `theme` lo arma **`cb_build_theme_payload()` en `public/lib.php`** (línea ~615), que es una **whitelist estricta**: si un campo no está explícitamente copiado ahí, **no llega al frontend**, por más que esté en `themes.json`.

> Esto ya causó un bug real en este proyecto. Si agregás un campo nuevo a `themes.json`, tenés que agregarlo también a `cb_build_theme_payload()` **y** al resolver del frontend (`src/themeFlow.js`). Los tres lados o ninguno.

### 2.2 Paridad `public/` → `dist/`

`dist/` es el artefacto que se sirve. **Debe quedar en paridad byte a byte con `public/`.**

Después de CUALQUIER cambio de assets o build:

```bash
php scripts/check-dist-parity.php
```

Debe imprimir `OK paridad public->dist (N archivos)`. Si no, copiá lo que falte a `dist/`.

### 2.3 Juegos: cómo funcionan hoy

- **Backend:** `cb_sanitize_theme_game()` en `public/lib.php:574` — whitelist de `kind`, `seconds` (5–30), `label` (≤40 chars), `image` (basename existente en disco), y `cols`/`filas` (2–4, **solo** si `kind === 'fichas'`).
- **Frontend:** `resolveGame()` / `resolveThemeFlow()` en `src/themeFlow.js`.
- **Kinds actuales:** `copos`, `armar-muneco`, `fichas`.
- Un personaje puede traer **un objeto** (un juego) o **un array** (varios). Con array: se juega **siempre el primero**, y cada juego siguiente se **ofrece** con botones «Sí, jugar» / «Omitir» (componente `Juego` en `src/App.jsx`). No se sortea al azar.
- Prioridad: juego del personaje > juego de la temática (`theme.game`).

### 2.4 Experiencia inmersiva (`photoSession`)

Campos soportados hoy (whitelist en `lib.php` ~657):

| Campo | Tipo | Qué es |
|---|---|---|
| `video` | `.mp4` | El video inmersivo. Se reproduce **entre la ruleta y el saludo del personaje**. |
| `poster` | imagen | Primer frame / fallback si el video no carga. |
| `characters` | array de nombres | **Solo estos personajes** pasan por la experiencia. Sin lista → aplica a todos. |
| `teaser` | imagen | Cuadro que se muestra en la ruleta anticipando la experiencia. |
| `teaserVideo` | `.mp4` | Versión animada del teaser. |
| `teaserLabel` | texto ≤40 | Rótulo del cuadro (independiente de `characters`). |

Referencia viva funcionando: temática `familia-canina` (Bluey) — mirá cómo está armada antes de replicar.

### 2.5 Videos de temática (`theme.videos`)

| Clave | Cuándo se ve |
|---|---|
| `welcome` | Al elegir invitado, antes de la ruleta. |
| `revelacion` | Entre la captura y la vista previa («Cargando tu foto…»). El nombre del invitado va como **texto HTML encima**, nunca grabado en el audio. |
| `despedida` | Al tocar «✨ Siguiente invitado». |

Los tres son opcionales y son **basename `.mp4`** dentro de `public/themes/<slug>/`.

### 2.6 Convención de nombres de archivo

Para un personaje con `"img": "arana.jpg"`, el sistema **deriva automáticamente**:

| Archivo | Para qué | Obligatorio |
|---|---|---|
| `arana.jpg` | Foto del personaje (ruleta, saludo) | ✅ |
| `arana-cut.png` | Recorte transparente (se compone junto al niño en la foto) | recomendado |
| `saludo-arana.mp4` | Video de saludo | recomendado |
| `invitacion-juego-arana.mp3` | Narración «Antes de tomarte una foto con…» | recomendado |

**No inventes otros nombres** — la derivación es automática en `buildRuntime()` (`src/App.jsx` ~170) y en `cb_build_theme_payload()`.

---

## 3. PARTE A — Cambios de código

> Hacer esto **antes** de generar assets: así podés probar los juegos con imágenes provisionales.

### A.1 Generalizar `copos` (emojis configurables)

Hoy `COPO_EMOJIS` está hardcodeado a copos de nieve (`src/App.jsx` ~1246). Eso ata el juego a Reino de Hielo.

**Cambio:** que `themes.json` pueda pasar `"emojis": ["⚡","🛡️","🔨"]`.

- `src/themeFlow.js` → en `resolveGame()`, si `kind === 'copos'`, copiar `rawGame.emojis` cuando sea un array de strings no vacío (máx 6, cada uno ≤4 chars). Sin `emojis` → mantener los copos de nieve por defecto.
- `public/lib.php` → en `cb_sanitize_theme_game()`, sanear igual (array de strings, máx 6, descartar vacíos).
- `src/App.jsx` → `JuegoCopos` usa `config.emojis || COPO_EMOJIS`.

**Compatibilidad:** Reino de Hielo no define `emojis` → sigue idéntico. Test de regresión obligatorio.

### A.2 Juegos nuevos — ⚠️ CONSTRUIR UNO, PROPONER EL SEGUNDO

**Instrucción de Luis (2026-07-26), leerla completa antes de codear:**

> Por cada temática, **construí UN juego nuevo completo y funcionando**, y **proponé un segundo por escrito** (sin construirlo) para que Luis elija. No arranques el segundo hasta que él lo apruebe.

Es decir, al terminar debe haber:

| Temática | Construido | Propuesto (solo descripción) |
|---|---|---|
| Guerreras K-Pop | **1 juego nuevo** funcionando | 1 alternativa descrita |
| Súper Héroes | **1 juego nuevo** funcionando | 1 alternativa descrita |

**Cómo entregar la propuesta:** en el reporte final, media carilla por juego propuesto — qué hace el niño, por qué encaja con la temática, qué tan difícil es de construir, y qué reusaría de lo que ya existe. Sin código.

#### Sugerencias de arranque (no son obligatorias)

Si no se te ocurre nada mejor, estos dos están pensados para esas temáticas. **Podés proponer otros** si te parecen más divertidos — el criterio es que un niño de 5 a 9 años quede asombrado.

**`ritmo` (K-Pop):** caen notas luminosas por 3 carriles; el niño toca el carril cuando la nota llega a la línea. Contador de combo, destello y partículas en cada acierto. Campos: `seconds`, `label`, `lanes` (2–4, default 3), `image` (fondo del escenario).

**`escudo` (Súper Héroes):** llegan proyectiles desde los bordes; el niño arrastra el escudo con el dedo para interceptarlos. Cada bloqueo da destello e impacto. Campos: `seconds`, `label`, `image` (fondo de ciudad). Reusá el arrastre de `JuegoMuneco` (`pointerdown`/`pointermove`/`pointerup` + fallback `touch*` + `getBoundingClientRect()` del stage).

#### Requisitos que cumple cualquier juego nuevo

- Registrar el `kind` en **los tres lados**: `GAME_KINDS` (`themeFlow.js`), whitelist de `cb_sanitize_theme_game()` (`lib.php`), y el despachador `Juego` (`App.jsx`).
- Componente propio en `src/App.jsx` + CSS en `src/styles.css` reusando `.screen.juego`, `.juego-hud`, `.juego-skip`.
- Botón **«Saltar ⏭» siempre visible**. La fila de invitados no se detiene porque a un niño no le interese jugar.
- Respetar `REDUCE_MOTION` (`prefers-reduced-motion`): sin animación, ofrecer saltar directo.
- Blancos de toque **≥56×56px** — son dedos de niño sobre una tablet.
- **Un solo camino de salida** (`doneRef`), para que el temporizador y el botón no disparen `onDone` dos veces y salten una pantalla de más. Copiá el patrón de `JuegoCopos`.

> ⚠️ **Trampa de CSS ya resuelta en este repo:** si tu juego arrastra algo con el dedo, ese elemento va en `position: fixed`, así que **los tamaños en porcentaje se calculan contra el viewport, no contra el stage del juego** — la pieza sale gigante. Hay que forzar el tamaño con `!important` y dejar comentado por qué. Mirá `.mp-pieza--arrastrando` en `src/styles.css`.

### A.3 Cadena de juegos y botón «Omitir» (ya implementado, respetarlo)

Un personaje puede traer **un array** de juegos en `themes.json`. El comportamiento, ya construido en el componente `Juego` de `App.jsx`, es:

1. El **primero** de la lista se juega siempre, sin preguntar.
2. Al terminarlo (o al saltarlo) aparece una pantalla **«¿Jugamos otro?»** con el nombre del siguiente y dos botones:
   - **«Sí, jugar»** → pasa al siguiente de la lista.
   - **«Omitir juegos 📸»** (o «Omitir e ir a la foto 📸» si es el último) → **descarta todos los que falten** y va directo a la cámara.
3. Se repite hasta agotar la lista.

`gamesFor(nombre)` en `themeFlow.js` devuelve la lista completa **en orden**; `gameFor(nombre)` devuelve solo el primero. **No hay azar** — hubo una versión que sorteaba y se eliminó a pedido de Luis.

Referencia viva: en Reino de Hielo, Olaf tiene 3 juegos encadenados (`armar-muneco → fichas → copos`) y el resto tiene 2 (`fichas → copos`).

### A.4 Tests

`tests/frontend/themeFlow.test.mjs` tiene hoy **27 tests en verde**. Ninguno debe romperse.

Agregar como mínimo:
- `copos` sin `emojis` → no aparece el campo (compatibilidad Reino de Hielo).
- `copos` con `emojis` válidos → se propagan; con basura (no-array, vacíos, >6) → se descartan/recortan.
- `ritmo`: `lanes` se acota a 2–4; `kind` desconocido sigue sin activar nada.
- `escudo`: propaga `image`; **no** publica `cols`/`filas`.
- Un personaje con array `[fichas, ritmo]` → `gamesFor()` devuelve los 2 **en orden**, y `gameFor()` devuelve **siempre el primero**.

```bash
node --test tests/frontend/themeFlow.test.mjs
```

---

## 4. PARTE B — Entradas de `themes.json`

Archivo: `public/data/themes.json` → raíz `{ "_doc": ..., "themes": { ... } }`.

> **Cuidado:** editá el JSON con un script Node (leer → mutar → escribir), **no** a mano. Ya hubo incidentes con este archivo. Y después copialo a `dist/data/themes.json`.

### B.1 `kpop` — Guerreras K-Pop (NUEVA)

```json
"kpop": {
  "nombre": "Guerreras K-Pop",
  "franquicia": "KPop Demon Hunters",
  "publico": "mixto",
  "diploma": "Guerrera Legendaria del Escenario",
  "transition": "none",
  "colors": {
    "accent": "#e0218a",
    "accentSoft": "#ffe3f3",
    "yellow": "#ffd54f",
    "ink": "#1a0b2e",
    "bgLight1": "#fce4ff",
    "bgLight2": "#e0f7ff",
    "dark1": "#3d0b52",
    "dark2": "#12043a",
    "dark3": "#00d4ff"
  },
  "confetti": ["#e0218a", "#00d4ff", "#ffd54f", "#a855f7", "#ffffff", "#ff6ec7"],
  "personajes": [
    { "emoji": "🎤", "name": "Rumi",  "img": "rumi.jpg",
      "game": [
        { "kind": "<JUEGO-NUEVO>", "label": "¡Sigue el ritmo!", "image": "fondo-juego-escenario.jpg" },
        { "kind": "fichas", "label": "¡Arma la imagen!", "image": "puzzle-rumi.jpg", "cols": 3, "filas": 3 },
        { "kind": "copos", "label": "¡Atrapa las estrellas!", "emojis": ["⭐", "💫", "🎵", "💖"] }
      ] },
    { "emoji": "⚔️", "name": "Mira",  "img": "mira.jpg",
      "game": [
        { "kind": "fichas", "label": "¡Arma la imagen!", "image": "puzzle-mira.jpg", "cols": 3, "filas": 3 },
        { "kind": "copos", "label": "¡Atrapa las estrellas!", "emojis": ["⭐", "💫", "🎵", "💖"] }
      ] },
    { "emoji": "💜", "name": "Zoey",  "img": "zoey.jpg",
      "game": [
        { "kind": "fichas", "label": "¡Arma la imagen!", "image": "puzzle-zoey.jpg", "cols": 3, "filas": 3 },
        { "kind": "copos", "label": "¡Atrapa las estrellas!", "emojis": ["⭐", "💫", "🎵", "💖"] }
      ] },
    { "emoji": "🌟", "name": "Luna",  "img": "luna.jpg",
      "game": [
        { "kind": "fichas", "label": "¡Arma la imagen!", "image": "puzzle-luna.jpg", "cols": 3, "filas": 3 },
        { "kind": "copos", "label": "¡Atrapa las estrellas!", "emojis": ["⭐", "💫", "🎵", "💖"] }
      ] },
    { "emoji": "🐯", "name": "Derpy", "img": "derpy.jpg",
      "game": [
        { "kind": "fichas", "label": "¡Arma la imagen!", "image": "puzzle-derpy.jpg", "cols": 3, "filas": 3 },
        { "kind": "copos", "label": "¡Atrapa las estrellas!", "emojis": ["⭐", "💫", "🎵", "💖"] }
      ] },
    { "emoji": "🎶", "name": "Sussie","img": "sussie.jpg",
      "game": [
        { "kind": "fichas", "label": "¡Arma la imagen!", "image": "puzzle-sussie.jpg", "cols": 3, "filas": 3 },
        { "kind": "copos", "label": "¡Atrapa las estrellas!", "emojis": ["⭐", "💫", "🎵", "💖"] }
      ] }
  ],
  "photoSession": {
    "video": "entrada-escenario.mp4",
    "poster": "entrada-escenario-poster.jpg",
    "characters": ["Rumi", "Mira", "Zoey"],
    "teaser": "escenario-teaser.jpg",
    "teaserLabel": "El trío legendario"
  },
  "videos": {
    "welcome": "welcome-kpop.mp4",
    "revelacion": "revelacion-kpop.mp4",
    "despedida": "despedida-kpop.mp4"
  },
  "frameBox": { "x": 0.30, "y": 0.34, "w": 0.40, "h": 0.24 },
  "musicaHint": "Pop coreano energético / girl group"
}
```

> `frameBox` es **provisional**. Ver §7.1: hay que calibrarlo contra el `fondo-sala.jpg` real.

### B.2 `heroes` — Súper Héroes (EXTENDER la existente)

La entrada **ya existe** con `nombre`, `franquicia`, `diploma`, `colors`, `confetti`, los 6 `personajes` y `frameBox`. **No la recrees.**

> ⛔ **No toques `"publico": "niño"`** — Luis lo confirmó el 2026-07-26. Se queda así.

Solo agregá:

1. `"transition": "none"` — la transición 3D es una **pista de autos de juguete**, nació con la temática Carreras y desentona en una ciudad de superhéroes.
2. El campo `game` a cada personaje. **Mismo criterio que Reino de Hielo: todos con al menos 2 juegos encadenados**, y el personaje "estrella" con 3.

| Personaje | `img` | Cadena de juegos |
|---|---|---|
| Capitán | `capitan.jpg` | `<JUEGO-NUEVO>` → `fichas` → `copos` (el de 3) |
| Araña | `arana.jpg` | `fichas` → `copos` |
| Gigante Verde | `gigante.jpg` | `fichas` → `copos` |
| Hombre de Hierro | `hierro.jpg` | `fichas` → `copos` |
| Trueno | `trueno.jpg` | `fichas` → `copos` |
| Pantera | `pantera.jpg` | `fichas` → `copos` |

Para esta temática, `copos` va con `"emojis": ["⚡","🔨","💥","✨"]` y `"label": "¡Atrapa los rayos!"`.
`fichas` usa `puzzle-<personaje>.jpg` con `cols: 3, filas: 3`.

3. Bloque `photoSession`:

```json
"photoSession": {
  "video": "comic-cobra-vida.mp4",
  "poster": "comic-cobra-vida-poster.jpg",
  "characters": ["Araña", "Hombre de Hierro", "Capitán"],
  "teaser": "comic-teaser.jpg",
  "teaserLabel": "Los héroes del cómic"
}
```

4. Bloque `videos`:

```json
"videos": {
  "welcome": "welcome-heroes.mp4",
  "revelacion": "revelacion-heroes.mp4",
  "despedida": "despedida-heroes.mp4"
}
```

5. Los juegos `escudo` y `fichas` usan `image`: `fondo-juego-ciudad.jpg` y `puzzle-<personaje>.jpg`.

---

## 5. PARTE C — Prompts de imágenes

**Formato de salida en todos los casos:** vertical 9:16, **1080×1920**, JPG, guardado en `public/themes/<slug>/`.
Excepción: los `-cut.png` son PNG con alpha real (ver §5.5).

### 5.1 Guerreras K-Pop — fondos

#### `fondo-banner.jpg`
```
A premium photorealistic collectible-figure birthday party on a dazzling concert
stage, vertical 9:16. Magenta, violet and electric-cyan balloon arch, holographic
star garlands, neon light beams sweeping across a glossy black stage floor,
wrapped gifts and confetti in pink and gold. Six stylized fashion-doll figures of
an original girl music group stand around a multi-tiered cake shaped like a
glowing microphone and stars. Vibrant stage lighting, glossy vinyl and satin
textures, sharp premium product photography.

This is an original toy design, not based on any existing character.
IMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere.
Negative prompt: text, letters, words, numbers, logo, brand, watermark, flat 2D
illustration, dark muddy image, blur, low quality.
```

#### `fondo-sala.jpg`
```
A premium photorealistic collectible-figure party hall on a concert stage,
vertical 9:16 and perfectly frontal. Magenta and cyan balloon arch, holographic
star garlands, neon light beams, glossy black stage floor with pink and gold
confetti.

THE MAIN ELEMENT: centered on the back wall, a large perfectly SQUARE picture
frame with equal width and height, straight sides, softly rounded corners and an
ornate GOLDEN border. Its interior is completely EMPTY and WHITE. Keep a clear
area beside the frame for a foreground figure overlay.

This is an original toy design, not based on any existing character.
IMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere.
Negative prompt: oval frame, circular frame, portrait rectangle, filled frame,
text, letters, logo, brand, watermark, dark lighting, low quality.
```

#### `fondo-juego-escenario.jpg` (fondo del juego de ritmo) — ⚠️ OBLIGATORIA, no placeholder
```
A photorealistic empty concert stage seen from the audience, vertical 9:16.
Glossy dark floor reflecting magenta and cyan neon beams, softly blurred crowd
lights in the distance, floating light particles. Composition intentionally
EMPTY in the center — no characters, no objects, no furniture — leaving clean
open space. Cinematic stage lighting, deep colors, sharp focus.

IMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere.
Negative prompt: people, characters, figures, text, letters, logo, watermark,
cluttered center, low quality.
```

### 5.2 Guerreras K-Pop — 6 personajes

Plantilla común (reemplazar solo el bloque `«RASGOS»`):

```
Premium photorealistic collectible fashion-doll product photo, vertical 9:16.
«RASGOS» fills the foreground. Behind her, a softly blurred concert-stage
birthday scene with magenta and cyan balloons, holographic star garlands, neon
beams, gifts and confetti. Original toy design, glossy vinyl skin and satin
fabric textures, vibrant stage lighting, sharp focus.

This is an original toy design, not based on any existing character.
No text, letters, words, numbers, logos, brands or watermark.
```

| Archivo | `«RASGOS»` |
|---|---|
| `rumi.jpg` | `A confident young woman idol figure with very long dark purple hair, a single bright magenta streak, expressive violet eyes, a warm determined smile, and a fitted stage outfit in deep violet with luminous gold trim` |
| `mira.jpg` | `A tall poised young woman idol figure with sleek long black hair, sharp calm dark eyes, a subtle confident smirk, and a sleek stage outfit in black and electric blue with silver geometric accents` |
| `zoey.jpg` | `A cheerful petite young woman idol figure with shoulder-length honey-blonde hair in a high ponytail, big bright amber eyes, a wide joyful open smile, and a playful stage outfit in hot pink and white with star-shaped accessories` |
| `luna.jpg` | `A graceful young woman idol figure with wavy silver-white hair down to her waist, luminous pale blue eyes, a serene gentle smile, and a flowing stage outfit in iridescent white and pale cyan with crescent accents` |
| `derpy.jpg` | `A tiny adorable chubby cartoon tiger cub mascot figure with oversized round eyes, a goofy lopsided open-mouth grin, tiny round ears, soft orange-and-cream striped fur and stubby paws, sitting upright` |
| `sussie.jpg` | `A small round cartoon bird mascot figure with fluffy magenta feathers, huge sparkling eyes, a tiny orange beak in a cheerful chirp, and small stubby wings raised happily` |

### 5.3 Súper Héroes — fondos

#### `fondo-banner.jpg`
```
A premium photorealistic collectible-figure birthday party on a rooftop
overlooking a bright comic-book city, vertical 9:16. Red, blue and gold balloon
arch, bunting, sunlit skyline, wrapped gifts and colorful confetti on the floor.
Six stylized action-figure heroes in bold original costumes surround a
multi-tiered cake shaped like a shield and a lightning bolt. Warm heroic
daylight, glossy vinyl and molded-plastic textures, sharp premium product
photography.

This is an original toy design, not based on any existing character.
IMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere.
Negative prompt: text, letters, words, numbers, logo, brand, watermark, flat 2D
illustration, dark image, blur, low quality.
```

#### `fondo-sala.jpg`
```
A premium photorealistic collectible-figure party hall on a rooftop overlooking a
bright comic-book city, vertical 9:16 and perfectly frontal. Red, blue and gold
balloon arch, bunting, sunlit skyline behind, gifts and colorful confetti.

THE MAIN ELEMENT: centered on the back wall, a large perfectly SQUARE picture
frame with equal width and height, straight sides, softly rounded corners and an
ornate GOLDEN border. Its interior is completely EMPTY and WHITE. Keep a clear
area beside the frame for a foreground figure overlay.

This is an original toy design, not based on any existing character.
IMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere.
Negative prompt: oval frame, circular frame, portrait rectangle, filled frame,
text, letters, logo, brand, watermark, dark lighting, low quality.
```

#### `fondo-juego-ciudad.jpg` (fondo del juego de escudo) — ⚠️ OBLIGATORIA, no placeholder
```
A photorealistic bright comic-book city rooftop at golden hour, vertical 9:16,
seen from a low heroic angle. Clean skyline, soft sunlit haze, a few floating
dust particles. Composition intentionally EMPTY in the center — no characters,
no objects — leaving clean open space. Warm cinematic lighting, sharp focus.

IMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere.
Negative prompt: people, characters, figures, text, letters, logo, watermark,
cluttered center, low quality.
```

### 5.4 Súper Héroes — 6 personajes

Plantilla común:

```
Premium photorealistic collectible action-figure product photo, vertical 9:16.
«RASGOS» fills the foreground in a confident heroic pose. Behind it, a softly
blurred rooftop birthday scene with red, blue and gold balloons, bunting, a
sunlit city skyline, gifts and confetti. Original toy design, glossy molded
plastic and fabric textures, warm heroic daylight, sharp focus.

This is an original toy design, not based on any existing character.
No text, letters, words, numbers, logos, brands or watermark.
```

| Archivo | `«RASGOS»` |
|---|---|
| `arana.jpg` | `A slender agile hero figure in a full-body red and navy-blue suit with a fine raised web-like texture pattern and large expressive white lens eyes on a full face mask` |
| `gigante.jpg` | `A huge muscular hero figure with bright green skin, black tousled hair, a fierce determined expression and torn purple shorts` |
| `hierro.jpg` | `A sleek armored hero figure in polished crimson-and-gold metallic plate armor with a glowing circular light on the chest and narrow glowing eye slits on a smooth helmet` |
| `capitan.jpg` | `A strong upright hero figure in a navy-blue uniform with red and white chest stripes, a small winged helmet, holding a large round shield with concentric red and white rings and a white star at its center` |
| `trueno.jpg` | `A tall powerful hero figure with long blond hair and a short beard, silver armor with circular embossed plates over a red flowing cape, gripping a short-handled war hammer` |
| `pantera.jpg` | `A lithe agile hero figure in a matte black textured bodysuit with subtle silver-violet accents, a sleek feline-shaped mask with small pointed ears and narrow silver eyes` |

### 5.5 Recortes transparentes (`-cut.png`) — 12 en total

Se necesitan los 6 de cada temática: `rumi-cut.png`, `mira-cut.png`, … / `arana-cut.png`, `gigante-cut.png`, …

Al prompt de cada personaje agregarle:

```
Isolated on a fully transparent background, full body visible, centered, no
shadow on the ground, no background elements at all.
```

> ⚠️ **Trampa documentada:** Gemini **no** produce alpha real — pinta un fondo cuadriculado tipo Photoshop como píxeles. Hay que quitarlo en post-proceso.
> El script ya existe y está probado: ver `docs/CUMPLECLICK-HANDOFF-CODEX.md` §7 (detección de tono checker + flood-fill desde bordes + segunda pasada de componentes conectados). **Reutilizalo, no lo reescribas.**

Verificación de cada PNG: debe tener canal alpha real, el personaje centrado y sin halo blanco en los bordes. Revisalo visualmente uno por uno.

### 5.6 Imágenes de referencia del puzzle (`puzzle-<personaje>.jpg`)

**No se generan con IA.** Se recortan de la foto ya aprobada del personaje, cuadrado 900×900:

```bash
ffmpeg -y -i public/themes/<slug>/<personaje>.jpg \
  -vf "crop=900:900:90:250" -update 1 -frames:v 1 \
  public/themes/<slug>/puzzle-<personaje>.jpg
```

El offset `90:250` funcionó bien para retratos verticales 1080×1920 (encuadra cara + torso). **Verificá cada recorte visualmente** y ajustá el offset si la cara queda cortada.

Necesarios: los que use cada temática según §4 (K-Pop: `mira`, `zoey`, `luna`, `sussie` · Héroes: `arana`, `gigante`, `capitan`, `pantera`).

---

## 6. PARTE D — Experiencias inmersivas y videos

### 6.1 Reglas de generación de video (aprendidas a golpes en este proyecto)

1. **Guardá SIEMPRE el video mudo original** en `design/renders/<slug>-raw/` antes de mezclarle audio. Si el audio no gusta, se rehace sin volver a pagar el video.
2. **Preflight de costo obligatorio** (`get_cost: true`) antes de cada generación paga.
3. Un rechazo por filtro (`status: 'nsfw'`) **no cobra créditos** — reintentar es gratis. Pero **3 fallos consistentes con el mismo personaje = pará**. Es el filtro reaccionando a esa imagen, no mala suerte.
4. **Normalización de volumen:** todos los saludos deben quedar al mismo nivel (~**-25.3 dB**) o unos suenan mucho más fuerte que otros en la fiesta. Medí con `volumedetect` y aplicá un delta directo:
   ```bash
   # medir
   ffmpeg -i entrada.mp4 -af volumedetect -f null - 2>&1 | grep mean_volume
   # corregir (delta = -25.3 - medido)
   ffmpeg -y -i entrada.mp4 -af "volume=-2.7dB" -c:v libx264 -pix_fmt yuv420p \
     -movflags +faststart -c:a aac -b:a 128k -ar 48000 salida.mp4
   ```
   > ⚠️ **No apliques `loudnorm` a material que ya viene normalizado** (todo lo que devuelve Higgsfield lo está): lo deja demasiado bajo. Pasó el 2026-07-26 — Anna quedó en -29 dB contra -25 del resto. `loudnorm` solo para audio crudo de TTS.

### 6.1-bis 🔴 LIP-SYNC — leer entero, es donde más se falla

Luis rechaza cualquier saludo cuya boca no acompañe lo que dice. **Muxear audio sobre un video mudo NO es lip-sync** y él lo nota en el primer segundo.

**Único método que funciona:**
```
model: wan2_7
medias: [{role: 'start_image', value: <media_id>}, {role: 'audio_references', value: <media_id>}]
```
Cuesta ~7.5cr y **el video vuelve con el audio ya incrustado y sincronizado**. No hay que muxear nada después (solo ajustar volumen).

**Cuatro reglas que salieron de errores reales el 2026-07-26:**

1. **⚠️ REVISÁ EL HISTORIAL ANTES DE GENERAR.** `show_generations(type:'video')`, paginando con `cursor`. Ese día aparecieron **3 lip-sync ya terminados y nunca instalados** — se instalaron gratis en vez de gastar 22.5 créditos. Los jobs quedan en el historial aunque la sesión que los pidió haya terminado.

2. **⚠️ VERIFICÁ ANTES DE AFIRMAR QUE TIENE LIP-SYNC.** Nunca lo marques de memoria. Extraé un frame del video instalado y otro del `-raw.mp4` mudo **al mismo segundo** y comparalos:
   ```bash
   ffmpeg -y -ss 2.0 -i raw.mp4       -frames:v 1 -vf scale=280:-2 -update 1 a.jpg
   ffmpeg -y -ss 2.0 -i instalado.mp4 -frames:v 1 -vf scale=280:-2 -update 1 b.jpg
   # mirarlos: si son la misma imagen → es un mux, NO hay lip-sync
   ```
   **Comparar hashes no sirve** — el re-encode cambia los bytes aunque el contenido sea idéntico. Y a resoluciones chicas (64px) el ruido de compresión da falsos positivos. Mirá los frames a ~280px.

3. **El prompt importa.** Incluí siempre: *"his/her mouth moving in sync with the audio"* + *"static camera, no zoom"* + *"Keep his/her face, hair, outfit and proportions exactly as in the reference image"*. Si el servidor responde con un `preset_recommendation` (p.ej. «IN THE DARK»), reenviá el mismo pedido agregando `declined_preset_id: <id>`.

4. **Hay personajes que el filtro rechaza siempre.** En Reino de Hielo, Elsa (4 intentos) y Olaf (3+2) dieron `nsfw` sin excepción, con distintas imágenes y prompts. Esos dos quedaron **sin** lip-sync, con la voz montada encima, y Luis lo aceptó sabiendo el motivo. Si te pasa: pará, dejalo con mux, **y avisale explicando por qué** — no lo escondas ni sigas quemando intentos.

**Fallback cuando `wan2_7` no pasa:** generar con `kling3_0_turbo` (sin audio) y muxear localmente:
```bash
ffmpeg -y -i video-mudo.mp4 -i voz.mp3 \
  -filter_complex "[1:a]apad[a]" \
  -map 0:v -map "[a]" -c:v libx264 -pix_fmt yuv420p \
  -movflags +faststart -c:a aac -b:a 128k -ar 48000 -shortest salida.mp4
```
Después ajustá el volumen al nivel del resto con el delta directo del punto 4.

### 6.2 Súper Héroes — experiencia «el cómic cobra vida»

**Archivo:** `comic-cobra-vida.mp4` · **Duración objetivo:** 10–14s · **Formato:** 1080×1920.

Es el «wow» de esta temática: arranca como páginas de cómic pasando rápido y termina reventando en acción real.

Se arma con **3 clips** concatenados con crossfade:

**Clip 1 — páginas de cómic (~4s)**
```
Cinematic vertical 9:16. Fast montage of blank comic-book panels flipping and
sliding past the camera, bold halftone dot texture, vivid red, blue and gold ink,
dynamic speed lines, paper grain. The panels are completely EMPTY — no drawings,
no text, no speech bubbles. Camera pushes forward through the pages. Energetic,
punchy, high contrast.

IMPORTANT: no text, letters, words, numbers, logos or watermarks anywhere.
```

**Clip 2 — la página se vuelve real (~5s)**
```
Cinematic vertical 9:16. A flat comic-book page with halftone texture slowly
transforms into a real three-dimensional city rooftop at golden hour, the ink
lines dissolving into real light and depth. Camera pushes forward through the
transition. Warm heroic lighting, cinematic depth of field, sharp focus.

IMPORTANT: no text, letters, words, numbers, logos or watermarks anywhere.
```

**Clip 3 — aterrizaje heroico (~5s)**
```
Cinematic vertical 9:16, low heroic angle. A shockwave of golden light and dust
bursts outward from a rooftop surface as if someone just landed hard, debris and
sparks flying upward in slow motion, sunlit haze, lens flare. No characters
visible. Epic, triumphant, cinematic.

IMPORTANT: no text, letters, words, numbers, logos or watermarks anywhere.
```

Concatenado con crossfade:

```bash
ffmpeg -y -i clip1.mp4 -i clip2.mp4 -i clip3.mp4 -filter_complex \
"[0:v][1:v]xfade=transition=fade:duration=0.5:offset=3.5[a]; \
 [a][2:v]xfade=transition=fade:duration=0.5:offset=8[v]" \
-map "[v]" -c:v libx264 -pix_fmt yuv420p -movflags +faststart comic-cobra-vida.mp4
```

Ajustá los `offset` a la duración real de cada clip.

**`comic-cobra-vida-poster.jpg`** = primer frame:
```bash
ffmpeg -y -i comic-cobra-vida.mp4 -frames:v 1 -update 1 comic-cobra-vida-poster.jpg
```

**`comic-teaser.jpg`**: cuadro de cómic vacío con los 3 héroes del pase, mismo estilo que los personajes.

### 6.3 Guerreras K-Pop — experiencia «entrada al escenario»

**Archivo:** `entrada-escenario.mp4` · **Duración objetivo:** 10–14s.

> ✅ **Confirmado por Luis (2026-07-26):** entrada a escenario de concierto con show de luces. Los 3 clips de abajo van tal cual.

**Clip 1 — pasillo al escenario (~4s)**
```
Cinematic vertical 9:16, first-person walk. Moving forward down a dark backstage
corridor toward a bright doorway, magenta and cyan light spilling through, dust
particles floating in the beams, cables and speakers softly blurred at the sides.
Anticipation, shallow depth of field, cinematic.

IMPORTANT: no text, letters, words, numbers, logos or watermarks anywhere.
```

**Clip 2 — se abre el escenario (~5s)**
```
Cinematic vertical 9:16. Emerging onto a huge concert stage as the lights explode
on: sweeping magenta and cyan beams, bursts of gold sparks, a glossy reflective
floor, a vast blurred sea of glowing lights in the distance. Camera rises slowly.
Euphoric, dazzling, cinematic.

IMPORTANT: no text, letters, words, numbers, logos or watermarks anywhere.
```

**Clip 3 — estallido de luz (~5s)**
```
Cinematic vertical 9:16. Ribbons of magenta and cyan light spiral upward around
the camera, glowing star-shaped particles bursting outward and drifting down in
slow motion, holographic shimmer, deep dark background. No characters visible.
Magical, triumphant, cinematic.

IMPORTANT: no text, letters, words, numbers, logos or watermarks anywhere.
```

Mismo concatenado, mismo poster (`entrada-escenario-poster.jpg`), y `escenario-teaser.jpg` con las 3 protagonistas.

### 6.4 Videos `welcome` / `revelacion` / `despedida`

Uno de cada por temática:

| Archivo | Contenido | Duración |
|---|---|---|
| `welcome-<slug>.mp4` | Bienvenida: puede reutilizar el clip inmersivo o un plano corto propio | 5–14s |
| `revelacion-<slug>.mp4` | Suspenso: cámara acercándose al marco dorado **vacío** del `fondo-sala` | ~5s |
| `despedida-<slug>.mp4` | Despedida: un personaje saluda / efecto de cierre | ~5s |

> ⚠️ **`revelacion`:** la voz dice solo «Cargando tu foto…». **El nombre del invitado NUNCA va grabado en el audio ni quemado en el video** — se pinta como texto HTML encima (`.revelacion-texto`), porque el video se reutiliza para todos los invitados.

### 6.5 Videos de saludo (`saludo-<personaje>.mp4`) — 12 en total

Uno por personaje: plano medio, el personaje saluda a cámara, ~5s, con lip-sync (§6.1 punto 4).

**Guion sugerido** (adaptar el nombre y el tono):
> «¡Hola! Soy <Nombre>. ¡Qué bueno que viniste a la fiesta!»

**Voces:** generar con ElevenLabs, modelo `eleven_multilingual_v2`, y **buscar acento neutro**:

```
GET /v1/shared-voices?language=es&search=<término>
```
filtrando por `accent: "latin american"` (neutro) y **evitando** `"colombian"` / `"mexican"` (acentos muy marcados — ya fueron rechazados en este proyecto).

Parámetros que dieron buen resultado para voces con emoción:
```json
{ "stability": 0.25, "similarity_boost": 0.75, "style": 0.65, "use_speaker_boost": true, "speed": 1.05 }
```

> ⚠️ **Regla de proceso obligatoria (Luis, 2026-07-25):** **NO asumas que una voz está aprobada.** Generá candidatas, armá un HTML autocontenido con el audio embebido en base64, mandáselo a Luis, y **esperá aprobación explícita** antes de gastar créditos en el video con lip-sync. «No se quejó» ≠ «aprobó».

### 6.6 Narración de invitación al juego — 12 audios

`invitacion-juego-<personaje>.mp3`, uno por personaje. **Voz Alice**, `voice_id: Xb7hH8MSUJpSbSDYk0k2` (ya aprobada por Luis para este uso).

**Texto exacto** (solo cambia el nombre):

> `Antes de tomarte una foto con <Nombre>, juguemos un rato. Si no, puedes oprimir Saltar.`

Parámetros:
```json
{ "stability": 0.4, "similarity_boost": 0.75, "style": 0.35, "use_speaker_boost": true, "speed": 1.0 }
```

> ⚠️ **Trampa:** el body JSON hay que **escribirlo a un archivo UTF-8 con Node** (`JSON.stringify` + `writeFileSync`) y mandarlo con `--data-binary @archivo.json`. Si armás el JSON inline en bash, los acentos y `¡` salen corruptos.

### 6.7 Música — ⛔ los MP3 los sube Luis, pero el CABLEADO lo hacés vos

**Decisión de Luis (2026-07-26): los archivos de música los aporta él.** Codex **no** busca, genera ni descarga música.

Pero desde el 2026-07-26 hay **dos pistas por temática**, así que sí hay trabajo de código y de configuración:

| Archivo | Dónde suena | Campo publicado |
|---|---|---|
| `musica-fondo.mp3` | Todo el recorrido | `theme.musica` |
| `musica-juego.mp3` | **Solo la pantalla de juegos** | `theme.musicaJuego` |

En Reino de Hielo quedó: «Libre soy» de fondo y «Y si hacemos un muñeco» en los juegos.

**Ya está implementado — replicalo, no lo reinventes:**
- `public/lib.php` → `cb_build_theme_payload()` publica `musicaJuego` solo si el archivo existe en disco; si no, cadena vacía.
- `src/App.jsx` → `CONFIG.audio.musicaJuego`, y un `useEffect` sobre `screen` que cambia la pista y ajusta el volumen.

**Volúmenes (constantes en `src/App.jsx`, respetarlas):**

| Situación | Volumen |
|---|---|
| Normal | `MUSIC_VOL = 0.15` |
| Videos con voz propia (pase de artista, saludo, despedida) | **0** — ya traen su audio |
| Primeros 6s del juego, mientras suena la narración | `MUSIC_VOL_NARRACION = 0.04` |

> La regla de fondo es de Luis: **cuando habla un personaje, la música baja o se apaga**. Nunca compite con una voz.

**Cada pista retoma donde quedó (Luis, 2026-07-26):** al volver del juego, la canción general **no vuelve a empezar** — sigue desde el segundo en que se cortó. Sin esto, en una fiesta donde el mismo invitado entra y sale del juego varias veces solo se escucharía siempre la intro. Se guarda la posición de la pista saliente en un `useRef` (`{ [url]: segundos }`) y se restaura al volver.

> ⚠️ **El seek va SIEMPRE dentro de `loadedmetadata`, nunca chequeando `readyState` antes.** Cambiar `src` dispara una recarga, pero `readyState` puede seguir informando el valor de la pista *anterior* durante un tick, y el `currentTime` se aplicaría al archivo equivocado. Verificado en navegador: con el atajo de `readyState` la posición salía mal; con el evento sale exacta.

```js
audio.addEventListener('loadedmetadata', () => {
  try { audio.currentTime = desde < audio.duration ? desde : 0 } catch {}
  audio.play().catch(() => {})
}, { once: true })
audio.load()
```

**Qué tenés que hacer por cada temática nueva:**
1. Elegir qué canción va en cada pantalla y **decírselo a Luis** para que te pase los 2 MP3.
2. Dejar `musicaHint` en `themes.json` describiendo el estilo buscado.
3. Al recibirlos, normalizarlos e instalarlos:
   ```bash
   ffmpeg -y -i original.mp3 -af "loudnorm=I=-20:TP=-2:LRA=11" \
     -c:a libmp3lame -b:a 128k -ar 44100 public/themes/<slug>/musica-fondo.mp3
   ```
4. Verificar que `api.php` publique ambos campos y que los dos archivos respondan HTTP 200.

> El kiosco tolera la ausencia de los MP3 sin romperse (simplemente no suena música), así que **no es bloqueante** para cerrar el resto.

---

## 7. PARTE E — Calibración y verificación

### 7.1 Calibrar `frameBox` (obligatorio, uno por temática)

`frameBox` define dónde se pega la foto del niño dentro del marco dorado de `fondo-sala.jpg`. Los valores del §4 son **estimaciones** — hay que medirlos contra la imagen real.

Método:
1. Abrí el `fondo-sala.jpg` generado y medí en píxeles el rectángulo **interior blanco** del marco.
2. Convertí a fracciones del total (1080×1920):
   - `x = izquierda / 1080`
   - `y = arriba / 1920`
   - `w = ancho / 1080`
   - `h = alto / 1920`
3. Escribí los valores con **4 decimales** en `themes.json`.
4. Verificá tomando una foto de prueba en el kiosco: la cara debe quedar centrada y **nada** debe salirse del marco.

> Referencia de precisión: Reino de Hielo quedó en `{ "x": 0.3315, "y": 0.3948, "w": 0.3407, "h": 0.1995 }`.

### 7.2 Checklist de verificación por temática

Correr **todo** esto y pegar la salida en el reporte final:

```bash
# 1. Tests del frontend — deben pasar los 27 previos + los nuevos
node --test tests/frontend/themeFlow.test.mjs

# 2. Build
npm run build

# 3. Paridad public -> dist (OBLIGATORIO, debe decir OK)
php scripts/check-dist-parity.php

# 4. La API sirve la temática completa
curl -s "http://localhost/automatiza-tech/CumpleBooth/dist/api.php?p=<slug-de-prueba>" | node -e "let d='';process.stdin.on('data',c=>d+=c);process.stdin.on('end',()=>{const j=JSON.parse(d);console.log('videos:',JSON.stringify(j.theme.videos));console.log('photoSession:',JSON.stringify(j.theme.photoSession));j.theme.personajes.forEach(p=>console.log(p.name,JSON.stringify(p.game)))})"
```

**Verificación manual en el navegador** (recorrido completo, por temática):

- [ ] Intro → lista de invitados → **video welcome** se ve completo (no se corta).
- [ ] Ruleta gira y cae en un personaje.
- [ ] Si el personaje tiene pase: se ve la **experiencia inmersiva** completa.
- [ ] Se ve el **video de saludo** con audio sincronizado.
- [ ] Suena la **narración de invitación al juego**.
- [ ] Aparece el **juego correcto** para ese personaje.
- [ ] Si tiene 2 juegos: al terminar el 1º aparece la oferta con **«Sí, jugar» / «Omitir»**, y ambos botones funcionan.
- [ ] El botón **«Saltar ⏭»** funciona en todos los juegos.
- [ ] Cámara → captura → **video de revelación** con el nombre en texto encima.
- [ ] Vista previa → QR → diploma.
- [ ] «✨ Siguiente invitado» → se ve el **video de despedida** → vuelve a la intro.
- [ ] **No hay errores en la consola del navegador.**
- [ ] Probar con `prefers-reduced-motion` activo: nada se rompe, todo es saltable.

### 7.3 Trampas conocidas (leelas antes de debuggear)

| Síntoma | Causa real | Dónde está |
|---|---|---|
| «Cambié `themes.json` pero el frontend no lo ve» | `cb_build_theme_payload()` es whitelist estricta — el campo nuevo no está copiado | `public/lib.php` ~615 |
| «El juego no aparece aunque está configurado» | `resolveGame()` no propaga el campo (pasó con `image`) | `src/themeFlow.js` |
| «Sigue viéndose la versión vieja» | `index.html` sin `Cache-Control` + fallback SPA devolvía **200** en vez de 404 para assets inexistentes, así que la tablet corría código viejo en silencio | **ya corregido** en `.htaccess` (2026-07-26) — ver nota abajo |
| «No se ve el video de despedida» | Los botones «Siguiente invitado» de `QRScreen` y `DiplomaScreen` llamaban a `reset()` salteándose `go('farewell')` | **ya corregido** en `src/App.jsx` (2026-07-26) |
| «La pieza arrastrada sale gigante» | `position: fixed` calcula los % contra el viewport, no contra el stage | `.mp-pieza--arrastrando` en `src/styles.css` |
| «Sale una carretera de autos que no tiene nada que ver» | Transición 3D heredada de Carreras | poner `"transition": "none"` |
| «Este video ya tiene lip-sync» (y no lo tiene) | Se asumió sin verificar | comparar frames contra el `-raw.mp4`, ver §6.1-bis punto 2 |
| Un saludo suena mucho más fuerte que los demás | Se aplicó `loudnorm` sobre material ya normalizado | usar delta directo, ver §6.1 punto 4 |
| Acentos corruptos en las voces | JSON armado inline en bash | escribir el body con Node a archivo UTF-8 |
| PHP del terminal es 7.4, el proyecto pide 8.0+ | Entorno | usar el PHP de WAMP para los scripts |

> ⚠️ **Sobre el `.htaccess`:** la regla que devuelve 404 para assets faltantes **necesita** su `RewriteCond %{REQUEST_FILENAME} !-f` delante. Sin esa condición 404ea *todos* los assets, incluido el bundle actual, y la app deja de cargar por completo. Pasó el 2026-07-26. Si tocás ese archivo, verificá con `curl -I` que el bundle vigente dé 200 y uno inventado dé 404.

---

## 8. Definition of Done

Se considera terminado cuando, **para cada una de las dos temáticas**:

1. ✅ Entrada en `themes.json` completa y válida (`kpop` creada, `heroes` extendida).
2. ✅ Assets en `public/themes/<slug>/`:
   - `fondo-banner.jpg`, `fondo-sala.jpg`, `fondo-juego-*.jpg`
   - 6 `<personaje>.jpg` + 6 `<personaje>-cut.png` (con alpha real, verificados a ojo)
   - `puzzle-<personaje>.jpg` para **los 6** (todos usan `fichas` en su cadena)
   - 6 `saludo-<personaje>.mp4` (con voz **aprobada por Luis** y **lip-sync verificado**, §6.1-bis)
   - 6 `invitacion-juego-<personaje>.mp3` (voz Alice)
   - `welcome-*.mp4`, `revelacion-*.mp4`, `despedida-*.mp4`
   - Experiencia inmersiva + su poster + su teaser
   - ⛔ `musica-fondo.mp3` y `musica-juego.mp3` — **los sube Luis, no Codex** (§6.7). El cableado sí es tuyo. No bloquea el cierre.
3. ✅ Videos mudos originales preservados en `design/renders/<slug>-raw/`.
4. ✅ Código: `copos` generalizado + **1 juego nuevo construido por temática** en los **tres** lados (`lib.php`, `themeFlow.js`, `App.jsx`) + CSS.
5. ✅ **1 juego adicional PROPUESTO por escrito** para cada temática, sin construir, en el reporte final (§A.2).
6. ✅ Cadenas de juegos armadas: todos los personajes con **al menos 2 juegos**, el estrella con 3, y el flujo «¿Jugamos otro?» → «Sí, jugar» / «Omitir juegos» funcionando.
7. ✅ Música cableada: `theme.musicaJuego` publicado, y los volúmenes 0.15 / 0 / 0.04 respetados (§6.7).
8. ✅ Tests en verde (27 previos + nuevos).
9. ✅ `php scripts/check-dist-parity.php` → `OK`.
10. ✅ `frameBox` calibrado y verificado con foto de prueba real.
11. ✅ Recorrido manual completo del §7.2 sin errores de consola.
12. ✅ Fiesta demo creada por temática (`demo-kpop`, `demo-heroes`) para que Luis pruebe.
13. ✅ Docs actualizados: agregar los prompts nuevos a `docs/PROMPTS-TEMATICAS.md` y actualizar la tabla de estado de temáticas en `docs/CUMPLECLICK-HANDOFF-CODEX.md` §6.
14. ✅ **Reporte final a Luis** con: qué juego construiste y cuál proponés, qué personajes quedaron sin lip-sync y por qué, y qué archivos faltan de su lado.

---

## 9. Orden de trabajo sugerido

**Dentro de cada temática, y dentro de cada personaje: FOTO antes que VIDEO, siempre.** No es solo un orden de bloques a nivel de documento — es una regla por archivo. Ver el recuadro de arriba ("ESTADO LOCAL") para el porqué.

| # | Bloque | Por qué en este orden |
|---|---|---|
| 1 | **Código** (§3): generalizar `copos` + construir **1 juego nuevo por temática** + tests | Se prueba con imágenes provisionales; desbloquea todo lo demás |
| 2 | **`themes.json`** (§4) con las cadenas de juegos + fiestas demo | Permite ver las temáticas en el kiosco aunque falten assets |
| 3 | **Imágenes** (§5), en este orden interno: fondos → **fondo de cada juego nuevo** → personajes → cut-PNG → puzzles | Los `-cut.png`, los `puzzle-*.jpg` y los videos de saludo derivan de la foto del personaje. El fondo del juego (`fondo-juego-escenario.jpg`/`fondo-juego-ciudad.jpg`) es una imagen igual de real que las demás — generarla acá, no después "cuando haga falta" |
| 4 | **Calibrar `frameBox`** (§7.1) | Necesita el `fondo-sala.jpg` final |
| 5 | **Voces** (§6.5): candidatas → **aprobación de Luis** → recién ahí video | Regla explícita: no gastar créditos de video sin voz aprobada |
| 6 | **Videos** (§6), personaje por personaje, cada uno DESPUÉS de tener su foto final: revisar historial → saludos con lip-sync → inmersivos → welcome/revelación/despedida | Lo más caro, va al final. **Revisar el historial primero puede ahorrar decenas de créditos** (§6.1-bis punto 1). Generar el video antes que la foto obliga a regenerarlo si la foto cambia |
| 7 | **Verificación** (§7.2) + docs + **reporte con el 2º juego propuesto** | Cierre |

---

## 10. Decisiones tomadas por Luis (2026-07-26)

Las cuatro dudas abiertas quedaron resueltas. **No hay nada pendiente de consultar.**

1. ✅ **Experiencia inmersiva K-Pop** = **entrada a escenario de concierto** con show de luces. Confirmado. Los 3 clips del §6.3 van tal cual.
2. ✅ **Nombres de las Guerreras K-Pop**: se mantienen `Rumi`, `Mira`, `Zoey`, `Luna`, `Derpy`, `Sussie`. **No se camuflan** (mismo criterio que la temática Bluey, que también usa los nombres reales). El camuflaje sigue siendo **obligatorio en los prompts de generación** — ahí nunca se nombra ni la franquicia ni los personajes.
3. ✅ **Música**: **la sube Luis**. Codex **no** busca ni genera MP3. Pero sí cablea las **dos pistas por temática** (`musica-fondo.mp3` general + `musica-juego.mp3` en los juegos) y respeta los volúmenes de §6.7.
4. ✅ **Público de `heroes`**: se mantiene `"niño"`. No tocar ese campo.
4-bis. ✅ **Alcance: LAS DOS temáticas juntas** (Luis, 2026-07-26). No entregar solo una y esperar validación — se hacen `kpop` y `heroes` en el mismo trabajo.

### Observaciones agregadas el 2026-07-26, después de cerrar Reino de Hielo

Todo lo de abajo salió de errores reales cometidos ese día. Están detallados en las secciones indicadas.

5. ✅ **Juegos: construir 1, proponer el 2º** (§A.2). No construyas los dos: Luis elige.
6. ✅ **Cadenas de juegos**: todos los personajes con al menos 2 juegos encadenados, el estrella con 3. Flujo «¿Jugamos otro?» con «Sí, jugar» / «Omitir juegos» (§A.3).
7. ✅ **Lip-sync (§6.1-bis)** — la sección más importante que se agregó:
   - Revisar `show_generations` **antes** de generar: puede haber trabajo ya pago sin instalar.
   - Verificar comparando frames contra el `-raw.mp4`; nunca afirmarlo de memoria.
   - Hay personajes que el filtro rechaza siempre: parar a los 3 intentos y avisar.
8. ✅ **Volumen (§6.1 punto 4)**: igualar todo a ~-25.3 dB con delta directo. `loudnorm` sobre material ya normalizado lo arruina.
9. ✅ **Dos bugs corregidos** que conviene no reintroducir (§7.3): el `Cache-Control` del `.htaccess` y la pantalla `farewell` inalcanzable.
