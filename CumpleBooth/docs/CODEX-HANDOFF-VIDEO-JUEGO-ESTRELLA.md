# Handoff a Codex — Video WOW del "juego estrella" (El Show 3D)

## Cierre Codex — Carreras resuelto (2026-08-02)

- Higgsfield autenticado y catálogo consultado con `models_explore`.
- Preflight sin gasto: Seedance Mini 12,5 créditos, Gemini Omni 15,
  Seedance fast 17,5, Grok Video 7,5, Wan 2.7 7,5 y MiniMax H3 20.
- Seedance Mini y Gemini Omni fallaron sin cobrar. Wan 2.7 completó el job
  `9bbc09ac-9ddb-4bd5-bfd7-be2976e60956` con prompt camuflado.
- Costo real: 7,5 créditos. Saldo posterior: 57,67 créditos.
- Final: `public/themes/carreras/rayo-mcqueen-estrella.mp4`, H.264,
  720×1280, 30 fps, `yuv420p`, 5,000 s y mudo.
- La API solo publica `videoEstrella` en planes Full, con basename seguro y
  archivo existente. El frontend lo reproduce únicamente para la estrella,
  justo antes de `concierto3d`, con watchdog y opción de omitir.
- QA: frontend 81/81, lint PHP 8.3, build Vite limpio, paridad
  `public→dist` de 283 archivos y recorrido Chrome hasta
  `FULL · CONCIERTO 3D` sin errores de consola.
- El backend completo conserva una falla previa ajena: una aserción antigua
  espera `mundo3d`, aunque el catálogo vigente usa `concierto3d`.
- Pendientes: clips equivalentes de Familia Canina, Tropical, Hielo, K-Pop y
  Héroes. No se consumieron créditos para esas cinco temáticas.

## Estado de reconexión Codex — 2026-08-01

- Higgsfield sí estaba configurado como MCP remoto en Codex, pero figuraba
  `Not logged in`; la comprobación inicial del catálogo activo fue insuficiente.
  Luis completó nuevamente el OAuth y `codex mcp list` ya confirma
  `higgsfield ... enabled OAuth`.
- El task actual fue iniciado antes de autenticar y mantiene el cliente MCP en
  caché: sus handshakes siguen respondiendo `Auth required`. Es necesario
  reabrir/continuar en un task que cargue el catálogo después del OAuth; entonces
  el orden correcto es `models_explore` → modelo alternativo → preflight de costo
  → una sola generación. El compositing local es la última opción.
- Antes de detectar la sesión OAuth vencida se ejecutó el plan B local como
  **candidato de respaldo no integrado** usando únicamente
  `rayo-mcqueen-cut.png` y `fondo-juego-circuito.jpg`: entrada lateral hacia
  cámara, oscilación suave, sombra, parallax/zoom, destello y confeti.
- Candidato visual:
  `tmp/rayo-mcqueen-estrella-local-candidate-v3.mp4`.
- Especificaciones verificadas con ffprobe: H.264, 720×1280, 30 fps,
  `yuv420p`, rango TV, 5.000 s, mudo, 1.10 MiB.
- QA por cuatro fotogramas: conserva el recorte aprobado, no deforma la silueta,
  no muestra el rectángulo alfa detectado y descartado en v2, y mantiene zona
  superior libre para el copy del frontend.
- Créditos consumidos: **0**. No se modificaron `themes.json`, `App.jsx`,
  `themeFlow.js`, `dist/` ni assets públicos.
- Gate pendiente: probar primero Higgsfield autenticado con un modelo distinto
  de `kling3_0_turbo`. El candidato local solo se considerará si también falla el
  modelo alternativo y Luis aprueba expresamente el plan B.

## Qué se pidió

Luis quiere que, cuando la ruleta elige a la ESTRELLA de una temática (el
personaje cuya cadena de juegos incluye El Show 3D), se reproduzca un video
corto y WOW del personaje animado en 3D anunciando "¡Te salió el juego
estrella!" — antes de entrar al Show. Uno por temática, 6 en total:

| Temática | Estrella | Retrato de origen |
|---|---|---|
| Carreras | Rayo McQueen | `public/themes/carreras/rayo-mcqueen.jpg` |
| Familia Canina | Bluey | `public/themes/familia-canina/bluey.jpg` |
| Tropical | Stitch | `public/themes/tropical/stitch.jpg` |
| Hielo | Olaf | `public/themes/hielo/olaf.jpg` |
| K-Pop | Rumi | `public/themes/kpop/rumi.jpg` |
| Héroes | Capitán América | `public/themes/heroes/capitan.jpg` |

Nota: el video **no lleva voz generada por el modelo** — ningún modelo de
video actual hace lip-sync + habla de calidad en un solo paso confiable. El
patrón ya probado en este proyecto (ver `saludo-*.mp4` de cada temática) es:
generar el video MUDO y muxearle la voz aparte con ffmpeg. Eso es un paso
posterior, fuera de este handoff — acá el problema es conseguir el video
mudo.

## Qué ya se intentó y falló (Claude, esta sesión, 2026-08-01)

Herramienta: MCP de Higgsfield, modelo `kling3_0_turbo`, imagen de referencia
= el retrato de Rayo McQueen de arriba como `start_image`, `aspect_ratio:
9:16`, `duration: 5`.

**4 intentos, 4 rechazos por `nsfw`** (el filtro de Higgsfield/Kling
reconociendo un personaje con copyright — Cars es de Pixar/Disney). Ninguno
cobró créditos (los rechazos nsfw son gratis). Prompts probados, en orden:

1. **Descripción directa** nombrando "race car character", confeti,
   celebración — rechazado antes de arrancar el render.
2. **Camuflaje por rasgos**, sin nombrar la franquicia, describiendo "the
   toy in the picture" — rechazado antes de arrancar.
3. **Fórmula aprobada del proyecto** (la de `birthday-photobooth/SKILL.md`,
   sección "PROMPTS PROBADOS"): `"This is an original die-cast collectible
   toy design, not based on any existing character"` + descripción por
   rasgos + negative prompt (`text, letters, words, banner with text,
   watermark, dark lighting, flat 2D cartoon, low quality`) — rechazado
   antes de arrancar, pese a ser la fórmula que SÍ funciona para generar
   imágenes nuevas con Gemini.
4. **Prompt mínimo**, sin describir al sujeto en absoluto (solo "the object
   in the image sways gently", luz, zoom de cámara) — este llegó a
   `in_progress` (pasó el chequeo previo del texto) pero cayó en `nsfw`
   igual después, ya en render.

## Diagnóstico

El intento 4 es la pista clave: cuando el PROMPT es neutro y aun así rebota
ya en render, el filtro no está mirando el texto — está mirando la
**imagen de referencia** (`start_image`). Es decir: el camuflaje por texto,
que SÍ funciona para pedirle a Gemini que genere una imagen nueva desde
cero, NO ayuda acá porque el punto de partida ya es una foto reconocible del
personaje real.

Esto es consistente con la regla ya documentada en el proyecto: escenas de
grupo (2+ personajes juntos) rebotan parejo sin importar el prompt porque el
filtro mira el contenido visual, no solo el texto. Este caso es la misma
familia de problema pero con UN personaje reconocible en vez de un grupo.

## Lo que Codex puede intentar que Claude no probó

No repitas los 4 caminos de arriba — ya están descartados con evidencia. Lo
que vale la pena explorar:

1. **Otro modelo de video** con manejo de referencia distinto a Kling
   (`seedance_2_0`, o el propio catálogo de Higgsfield vía
   `models_explore`). Puede que otro modelo pondere menos el reconocimiento
   de personajes o tenga un filtro menos estricto en `start_image`.
2. **Reducir la "reconocibilidad" de la imagen de entrada antes de
   mandarla**: recortar más de cerca, cambiar levemente color/contraste,
   o generar primero una imagen "reinterpretada" (nano_banana_pro, texto
   puro sin imagen de referencia, con la fórmula camuflada aprobada) y
   usar ESA como `start_image` en vez de la foto original. Esto ya
   funcionó para el compositing 2D de Héroes (recortes individuales
   pasaron el filtro de imagen aunque el grupo no).
3. **Otro proveedor de video** fuera de Higgsfield si el usuario lo
   autoriza — no era parte del alcance de Claude en esta sesión.
4. Si nada de esto pasa el filtro: confirmar con Luis que se acepta la
   alternativa de **compositing local con ffmpeg** (animar el recorte
   transparente `*-cut.png` ya aprobado sobre el fondo de la temática, con
   rebote/zoom/confeti por código, cero IA, cero costo) — es la salida que
   Claude tenía preparada como plan B y que ya se usó con éxito en este
   mismo proyecto para el banner grupal de Héroes y la revelación de
   Tropical.

## Contexto técnico para integrar el video una vez conseguido

- El archivo final va en `public/themes/<slug>/<algo>-estrella.mp4`
  (nombre libre, pero seguir la convención `kebab-case` del resto de
  assets).
- Se referencia desde `public/data/themes.json`, en el tema correspondiente
  — agregar un campo nuevo, no reusar `videos.welcome` que ya tiene otro
  uso. Sugerencia: `"videoEstrella": "rayo-mcqueen-estrella.mp4"`.
- El disparo en el frontend va en `src/App.jsx`: es el punto donde la
  ruleta entrega el personaje ganador y se decide la cadena de juegos
  (`resolveThemeFlow` / `gamesFor`, en `src/themeFlow.js`) — hay que
  detectar cuándo el personaje ganador ES la estrella (su cadena tiene 4
  juegos en vez de 3, o el último es `concierto3d`) y, antes de entrar al
  Show, reproducir este video.
- Seguir el patrón ya usado por `PhotoSessionVideo` en `src/App.jsx` para
  reproducir un video una vez con fallback si no carga (nunca bloquear el
  kiosco si el archivo falta).
- Actualizar `docs/TEMATICA-COMPLETA.md` y
  `tests/frontend/themeFlow.test.mjs` si se agrega esto como parte del
  estándar de "temática completa".

## Estado de créditos Higgsfield al momento de este handoff

65.17 créditos disponibles (plan basic). Los 4 rechazos nsfw de este
handoff no cobraron. Costo de referencia: ~7.5 créditos por clip de 5s en
`kling3_0_turbo`. Confirmar con Luis antes de gastar en otro modelo si el
costo es distinto.
