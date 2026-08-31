# Prompt para OpenCode — copiar y pegar tal cual

Proyecto: `C:\wamp64\www\automatiza-tech\CumpleBooth` (CumpleClick). Lee primero
`docs/CUMPLECLICK-HANDOFF-CODEX.md` completo (bloque `AT-CUMPLECLICK-002` al
principio) para contexto de arquitectura y de lo que se hizo hoy — esta tarea
es la continuación directa de esa sesión.

## Objetivo

Terminar de animar los personajes de la temática "carreras" con Higgsfield
(MCP ya conectado, server id `4d59566e-1c80-4680-b817-709a55fc2c4e`, saldo
~169 créditos). Ya se hizo y quedó aprobado el de Cruz
(`public/themes/carreras/saludo-cruz.mp4`) — replicar el mismo patrón.

## Paso 0 — arreglar el asset de Mate ANTES de animarlo

`public/themes/carreras/mate.jpg` está mal: es la foto grupal de los 6
personajes con el banner "¡Bienvenidos al cumpleaños de Mariano!", no la foto
de producto individual que pedía el prompt original. `mate-cut.png` no existe.

1. Regenerar `public/themes/carreras/mate.jpg` (Gemini/Nano Banana — usar la
   skill `ai-studio-image` si está disponible en este entorno, o el camino de
   generación de imágenes que ya esté configurado en el proyecto) con este
   prompt EXACTO (ya está probado, está en `docs/PROMPTS-TEMATICAS.md`
   líneas 60-69, no inventar uno nuevo):

   ```
   Premium photorealistic collectible-toy product photo, vertical 9:16. A
   lovable rusty brown anthropomorphic tow truck fills the foreground, with
   mismatched body panels, one headlight, a sturdy towing hook, large
   friendly windshield eyes and a wide innocent smile. Behind it, a softly
   blurred racing birthday workshop with red and yellow balloons, checkered
   bunting, trophies, gifts and confetti. Original toy design, weathered
   die-cast texture, warm daylight, sharp focus. No text, letters, words,
   numbers, logos, brands or watermark.
   ```

2. Con ESE `mate.jpg` nuevo como imagen fuente, generar
   `public/themes/carreras/mate-cut.png` con este prompt EXACTO (línea
   124-131 del mismo doc):

   ```
   Using the provided source image, isolate only the rusty brown
   anthropomorphic tow truck. Remove the entire background cleanly, preserve
   every weathered edge, wheel, towing hook, windshield eye and smile, and
   export a high-resolution PNG with true transparent alpha. Do not
   redesign, add text, add logos, add shadows outside the vehicle or crop
   any part of it.
   ```

3. Control de calidad: comparar visualmente `mate-cut.png` contra
   `cruz-cut.png` / `rayo-mcqueen-cut.png` (mismo encuadre, recorte limpio,
   fondo transparente real, sin texto). **Si sale bien, seguir con Mate en el
   paso 1. Si sale mal o inconsistente, NO forzarlo — dejar a Mate afuera de
   esta tanda y avisarle al usuario en vez de animar algo de mala calidad.**

## Paso 1 — animar con Higgsfield (repetir por cada personaje)

Personajes y archivo de salida (imagen fuente → video):

| Imagen fuente | Video de salida |
|---|---|
| `rayo-mcqueen-cut.png` | `saludo-rayo-mcqueen.mp4` |
| `sally-cut.png` | `saludo-sally.mp4` |
| `el-rey-cut.png` | `saludo-el-rey.mp4` |
| `luigi-cut.png` | `saludo-luigi.mp4` |
| `mate-cut.png` (si el Paso 0 salió bien) | `saludo-mate.mp4` |

Todos van a `public/themes/carreras/`. NO animar Rayo McQueen usando
`rayo-mcqueen.jpg` (ese trae "MARIANO 95" quemado) — solo la versión
`-cut.png`.

Por cada uno:

1. `media_upload` (filename, content_type `image/png`) → subir los bytes con
   `curl -X PUT` al `upload_url` que devuelve → `media_confirm` (type
   `image`).
2. `generate_video` con estos parámetros exactos (ya validado con Cruz):
   ```
   model: kling3_0_turbo
   medias: [{role: "start_image", value: "<media_id del paso anterior>"}]
   duration: 5
   resolution: "720p"
   aspect_ratio: "9:16"
   get_cost: true
   prompt: "Cute toy race car character with big expressive cartoon eyes and a friendly smile, parked on a race track with confetti around it. It revs its engine happily, wiggles side to side and bounces playfully as if waving hello to the camera, headlights blink like a cheerful blink, then settles facing the camera with a big warm smile, festive birthday party energy, no text or logos change."
   ```
   Confirmar que el costo ronda ~7.5 créditos.
3. Si Higgsfield sugiere un preset (pasó con Cruz: recomendó "RACE TRACK",
   que resultó ser una influencer real en una pista de autos, no tenía nada
   que ver): revisarlo brevemente y si no encaja, generar igual pasando
   `declined_preset_id` con el id del preset rechazado.
4. Repetir el `generate_video` SIN `get_cost` (generación real).
5. `job_display` con el id devuelto, hacer poll hasta `status: "completed"`
   (tarda unos minutos, no es instantáneo).
6. Descargar `results.rawUrl` y guardarlo exactamente como el nombre de la
   tabla de arriba, en `public/themes/carreras/`.

## Paso 2 — cerrar

1. `npm run build` (Vite copia `public/` completo a `dist/`, incluidos los
   `.php` — no hace falta copiar nada a mano).
2. Probar en `http://localhost/automatiza-tech/CumpleBooth/dist/index.html?p=demo`:
   elegir un nombre → ver el video del auto de bienvenida → tocar "Toca para
   girar la ruleta" → esperar a que gire → confirmar que el personaje
   sorteado muestra VIDEO real (no la imagen fija de respaldo).
3. Actualizar `docs/CUMPLECLICK-HANDOFF-CODEX.md` con qué se generó, qué
   costó en créditos, y el saldo final — mismo formato que ya usó Claude Code
   para dejar registro de Cruz.

## No tocar sin preguntar

- `ListaInvitados`, `.welcome-popup`, `.spin-ready-popup` en
  `src/App.jsx`/`src/styles.css` — el flujo (nombre → video del auto → "toca
  para girar" → ruleta) ya está decidido y probado, no reabrir esa discusión.
- No generar las otras 9 temáticas todavía, solo "carreras".
- No gastar créditos de Higgsfield en nada que no sea esta lista sin
  confirmar antes.
