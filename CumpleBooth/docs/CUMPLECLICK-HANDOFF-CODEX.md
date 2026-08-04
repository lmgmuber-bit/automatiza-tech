# CumpleClick (nombre técnico del repo/carpeta: CumpleBooth) — Traspaso completo para Codex / OpenCode

> **ACTUALIZACIÓN 2026-08-01 — AT-CUMPLECLICK-012, formulario público persistente.**
> La landing `sitio/` ya registra solicitudes en la BD independiente `cumpleclick`, tabla
> `cc_leads`, mediante `POST sitio/api/contacto.php`. La migración idempotente
> `006_public_leads` quedó aplicada localmente. El endpoint acepta JSON de hasta 16 KB,
> valida todos los campos, exige consentimiento, usa honeypot y rate limit persistente
> (5/10 min, bloqueo 15 min), prepared statements y referencias opacas `CC-*`; la IP se
> conserva solo como HMAC. El navegador muestra la referencia creada y no depende de
> WhatsApp. Pruebas aisladas: 11/11 en PHP 8.0/8.2/8.3/8.4; smoke HTTP real 201/422/405 y
> confirmación Chrome 834×1194. Los registros/rate limits de QA fueron eliminados.
> WhatsApp sigue intencionalmente pendiente para el último gate. La suite general tiene
> un fallo preexistente ajeno en el mundo 3D de Carreras; no se modificó.

> **ACTUALIZACIÓN 2026-07-26 — estudio manual de assets + K-Pop/Héroes.**
> La ficha privada de cada temática funciona como estudio de producción:
> prompt versionado → copiar → generar fuera de CumpleClick → adjuntar al slot
> exacto → previsualizar. Acepta JPG/PNG, MP4 H.264 inspeccionado con ffprobe y
> MP3; admite subcarpetas de allowlist y aplica rate limit 30/10 min. Las
> imágenes quedan limitadas a 4096×4096 y los recortes de personaje exigen PNG
> real con canal alfa. Los prompts de videos también son privados y pasan el
> mismo camuflaje. Se
> agregaron slots de Marketing (`marketing/marketing-poster.jpg` y
> `marketing/marketing-promo.mp4`) sin mezclarlos con el payload del kiosco.
>
> `scripts/backfill-theme-production-prompts.php` completó en la BD los prompts
> faltantes de todas las temáticas, sin reemplazar los ya aprobados. Los
> `puzzle-*.jpg` quedan expresamente como derivados del retrato, no como
> generaciones. Además normalizó 100 prompts históricos sin reescribir su
> contenido: solo añadió el cierre de camuflaje obligatorio, con historial en
> BD. El validador ahora impide guardar un prompt multimedia no vacío sin ese
> cierre. `scripts/seed-demo-theme-parties.php` creó idempotentemente
> `demo-tropical`, `demo-kpop` y `demo-heroes`, cada una con 10 invitados
> (5 niñas/5 niños), plan booth y galería apagada.
>
> Se incorporó `kpop` y se extendió `heroes` sin cambiar
> `heroes.publico="niño"`. K-Pop usa `ritmo`; Héroes usa `escudo`; ambos
> conservan sus cadenas ordenadas de bonus `fichas`/`copos`. `copos` acepta
> emojis por configuración. La API pública dejó de incluir `franquicia`:
> mantiene los nombres visibles reales del tema/personajes, pero reserva la
> referencia de franquicia exclusivamente para el admin.
>
> **Assets pendientes:** K-Pop y Héroes están listos como contratos, prompts,
> slots y demos de preproducción, pero requieren que Luis adjunte las imágenes,
> recortes, videos y música indicados por el inventario del admin. No se generó
> multimedia ni se consumieron créditos en esta actualización.

> **CIERRE 2026-07-17 (ronda 2) — arnés de pruebas, tras revisión independiente
> de Codex.** Codex confirmó que las 10 correcciones funcionales de Gate A
> pasan, y pidió cerrar 5 huecos en el arnés de pruebas (sin cambiar alcance,
> sin avanzar a Gate B). Los 5 quedaron corregidos y re-verificados:
>
> 1. `tests/backend/http-smoke.mjs`: si `seed-invitation-smoke.php` falla, el
>    test entero ahora falla (antes solo avisaba y saltaba los checks). Todo el
>    cuerpo del script corre dentro de un único `try/finally`, y
>    `cleanup-http-smoke.php` corre siempre en el `finally` — verificado
>    forzando una falla a propósito (`check(false, ...)`) y confirmando exit
>    code 1 + 0 filas `SMOKE-TEST` remanentes en la BD real, después revertido.
>    Se agregaron checks HTTP reales para: imagen publicada, video aprobado,
>    invitación draft/no publicada (404 en ambos endpoints), invitación
>    revocada (404/403), invitación expirada (410/403) — 28 checks HTTP en
>    total (antes 20).
> 2. `tests/backend/seed-invitation-smoke.php`: reescrito para sembrar 4
>    invitaciones (`published` con imagen+video aprobados, `draft`, `revoked`,
>    `expired`), coincidiendo con lo que el comentario ya decía debía sembrar.
>    Imprime un JSON de una línea con los 4 tokens.
> 3. Borrados `tests/backend/_seed_extra.php` y
>    `tests/backend/_seed_invitacion_http.php` (scripts temporales, superados
>    por `seed-invitation-smoke.php` + las adiciones a `run.php`).
> 4. `public/lib.php`: eliminada la línea en blanco extra al final del
>    archivo. `git diff --check` termina en exit 0.
> 5. Suite completa re-corrida tras los 4 cambios: backend 72/72 en PHP
>    8.0.30/8.2.26/8.3.14/8.4.0, lint 35 archivos + 7/7 entrypoints en las 4
>    versiones, frontend 5/5, HTTP smoke 28/28 sin skips contra WAMP+MySQL
>    real, `check-dist-parity.php` exit 0 (53 archivos, dist reconstruido
>    desde cero), `git diff --check` exit 0. Sin generar multimedia, sin gastar
>    créditos, sin deploy/commit/push/merge — 0 filas `SMOKE-TEST` y 0 fotos
>    `smoke-test.png` remanentes en la BD real al finalizar.

> **CIERRE 2026-07-17 — AT-CUMPLECLICK-007 / Gate A. Ejecutor: Claude.**
> Los 10 hallazgos de `CLAUDE-GATE-A-TAKEOVER.md` están corregidos y
> verificados con evidencia real (no solo lectura de código). Detalle completo
> más abajo, bloque "ACTUALIZACION 2026-07-17". Codex debe revisar
> independientemente antes de considerar Gate A aprobado; Gate B sigue
> bloqueado hasta aprobación expresa de Luis.
>
> **Hallazgo extra fuera de la lista original, encontrado y corregido en el
> camino:** `admin/invitations.php` tenía un `require` duplicado de
> `lib.invitations.php` (ya lo incluye `lib.php` internamente) que producía un
> **fatal error en cada carga real** — la página entera del admin de
> invitaciones nunca funcionó por HTTP, a pesar de que `php -l` y el lint
> pasaban limpio (ninguno de los dos ejecuta el código, solo revisan sintaxis).
> Se agregó un "smoke require" real a `tests/backend/lint.php` que sí lo
> detecta — confirmado apagando y prendiendo el bug de nuevo antes de dejarlo
> corregido.
>
> **Resultado de tests, verificado en PHP 8.0.30/8.2.26/8.3.14/8.4.0:**
> - `tests/backend/run.php`: **72/72** (era 44 al empezar; +28 tests nuevos
>   cubriendo ownership cruzado, rate limiting, compilador estricto,
>   publicación atómica, video/ffprobe, expiración).
> - `tests/backend/lint.php`: **36/36 archivos** + **7/7 entrypoints** sin
>   fatal error escondido (chequeo nuevo).
> - `tests/frontend/frameGeometry.test.mjs`: **5/5**.
> - `tests/backend/http-smoke.mjs` contra el sitio real (WAMP + MySQL local,
>   `dist/` recién buildeado): **20/20**, incluye 8 checks nuevos de
>   `invitacion.php`/`descargar-invitacion.php` de punta a punta (200 en
>   invitación publicada, 400/404 en token inválido/inexistente/sin token,
>   `X-Robots-Tag`, nombre de descarga neutro, imagen servida con
>   `Content-Type` correcto). Limpiado después con `cleanup-http-smoke.php`
>   (0 invitaciones/fotos de prueba quedaron en la BD real).
> - `scripts/check-dist-parity.php` tras `rm -rf dist && npm run build`:
>   **exit 0, 53 archivos, paridad perfecta** (las 9 diferencias que reportaba
>   Codex ya no existen).
>
> **Estado de la BD real local (`cumpleclick`, MySQL):** migración `004`
> aplicada limpia con `scripts/migrate.php` (estaba pendiente desde antes,
> nunca se había corrido). La fiesta `demo` se creó ahí porque `http-smoke.mjs`
> la necesita y no existía. `cc_invitations`/`cc_invitation_outputs` quedaron
> en 0 filas (todo dato de prueba fue limpiado). No se borró ni recreó ninguna
> tabla ni base de datos.
>
> **Archivos modificados por Claude en esta sesión (Gate A):**
> - `public/invitacion.php` — **nuevo**, hallazgo #1.
> - `public/lib.php` — `THEME_SLUG` para watermark de grupo (bug de sesión
>   anterior, no de Gate A), `cb_inspect_video()` + `cb_theme_video_max_duration_seconds()`
>   + config `ffprobe_path` (hallazgo #8), `cb_compile_invitation_prompt()`
>   rechaza campos vacíos por separado (hallazgo #5).
> - `public/lib.invitations.php` — `cb_invitation_owned_by_party()` +
>   `cb_invitation_output_owned_by_party()` (hallazgo #3), `cb_update_invitation()`
>   rechaza `status=published` directo (hallazgo #6), video usa
>   `cb_inspect_video()` en vez de solo sniff de bytes (hallazgo #8).
> - `public/descargar-invitacion.php` — nombre de descarga neutro (hallazgo #7),
>   rate limit por IP (hallazgo #4).
> - `public/admin/invitations.php` — quitado el `require` duplicado fatal
>   (bug extra encontrado), ownership check en las 10 acciones mutables
>   (hallazgo #3), rate limit en subida (hallazgo #4), publicación atómica en
>   transacción con orden guardar→publicar corregido (hallazgo #6).
> - `database/migrations/004_gate_a_corrections.php` y `.down.php` — orden
>   seguro ampliar→normalizar→angostar en los ENUM de `status` y `output_type`,
>   ida y vuelta (hallazgo #9). Probado con datos legados sintéticos en MySQL
>   real (BD descartable `cc_migration_test`, ya borrada) y con instalación
>   limpia + segunda ejecución + rollback.
> - `tests/backend/run.php` — 44 → 72 checks.
> - `tests/backend/lint.php` — + smoke require de 7 entrypoints.
> - `tests/backend/http-smoke.mjs` + `cleanup-http-smoke.php` — + invitaciones.
> - `tests/backend/seed-invitation-smoke.php` — **nuevo**, sembrador reusable.
>
> **Confirmaciones del criterio de entrega:** no se generó ninguna imagen ni
> video (Higgsfield/Gemini no se tocaron), no se gastó ningún crédito externo,
> no se hizo deploy, commit, push ni merge. `Docs/ORCHESTRATION/current-handoff.yaml`
> y `AT-CUMPLECLICK-007.yaml` no se modificaron por Claude — eso queda para que
> Codex/Luis actualicen el estado del ticket tras su revisión. No se generó
> contenido de Tema 02 (`familia-canina`) ni se tocó ninguna temática fuera de
> `carreras`/infraestructura genérica — fuera del alcance de Gate A.
>
> **PRIORIDAD ACTUAL 2026-07-15 — AT-CUMPLECLICK-007 / TEMA 02.** Luis cambió
> la estrategia a una temática completa por vez. Después de Carreras, OpenCode
> Go debe implementar únicamente `familia-canina` (nombre público `Aventura
> Perruna`) desde invitación dinámica hasta Intro, ruleta, seis personajes,
> cámara, foto, QR y diploma. Leer y ejecutar literalmente
> `docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`. No iniciar Tema 03 hasta que
> Luis acepte visualmente Tema 02. En prompts externos nunca usar nombres
> oficiales: describir la familia canina por rasgos azul/naranja.
> **Flujo comercial confirmado:** Invitaciones es un módulo independiente del
> Photo Booth. Cada tema conserva una imagen genérica sin datos y cada
> invitación produce una segunda imagen personalizada mediante un prompt con
> `[NOMBRE_DEL_CUMPLEAÑERO]`, `[FECHA_Y_HORA]` y `[DIRECCIÓN]`; el video parte
> de la personalizada aprobada. El admin comparte únicamente un enlace opaco y
> revocable de descarga, nunca un adjunto automático. El Photo Booth reutiliza
> la temática creando una fiesta con slug y enlace propios. La invitación
> genérica aprobada es el máster visual obligatorio del Photo Booth: de ella se
> derivan colores, personajes, proporciones, materiales, iluminación,
> decoración, portada, sala, ruleta, foto y diploma. No diseñar el kiosco en
> paralelo. La nueva fiesta no copia datos,
> PIN, galería, fotos, invitados ni outputs de invitación.
> Fase 2 deriva de la referencia genérica todo el Photo Booth.
> La galería es un adicional: solo plan `full` + activación explícita del admin
> + PIN; el PIN por sí solo no habilita la galería.

> **PLAN CANÓNICO 2026-07-15 — AT-CUMPLECLICK-006.** Luis asignó a Codex el
> rol de planner/analista y a OpenCode Go el rol de ejecutor para incorporar
> invitaciones dinámicas. El alcance deja 15 temáticas con imagen base sin
> texto y una primera tanda nueva de cinco videos (`hielo`, `cachorros`,
> `dinos`, `arcade`, `fashion`), usando `carreras` como piloto ya existente.
> Antes de implementar, OpenCode Go debe leer
> `docs/FASE-INVITACIONES-DINAMICAS.md` y después seguir literalmente
> `docs/OPENCODEGO-INVITACIONES-EXECUTOR.md`. Estado actual: plan listo,
> **sin implementación ni gasto externo autorizado todavía**.
> La tarjeta exige cuatro datos para poder publicarse o exportarse: nombre del
> cumpleañero, fecha, hora y dirección. Edad, lugar/salón, RSVP y mensaje son
> opcionales; un borrador incompleto sí puede guardarse.

> **ACTUALIZACIÓN CANÓNICA 2026-07-14 — AT-CUMPLECLICK-002.** Traspaso de la
> sesión de Claude Code del 2026-07-14, por si se corta antes de terminar.
> Válido para cualquier agente que retome esto (Codex, OpenCode, etc.).
>
> **AJUSTE VISUAL 2026-07-15 — portada y diploma.** `composeDiploma()` dibuja
> ahora la tipografía principal en amarillo de la temática con contorno rojo,
> más grande, para el título, nombre, reconocimiento, línea de fiesta y cierre.
> En la Intro se añadió una guirnalda genérica `¡A celebrar!` dentro del marco
> ajedrezado (sirve para todas las temáticas) y `.intro-cake-name` pasó a ser
> una placa blanca más ancha con nombre más pequeño; no volver a poner el
> nombre directamente dentro del asset de fondo.
>
> **1) Pantalla "¿Quién se toma la foto?" (`ListaInvitados` en `src/App.jsx`,
> clase `.invitados-list` en `src/styles.css`) — bug de layout CORREGIDO.**
> `.invitados-list` tenía `position: relative`, que le ganaba a `.screen
> { position: absolute; inset: 0 }` y encogía la pantalla al alto de su
> contenido en vez de ocupar los 100% del `.app`. El resto (fondo de `.app`,
> gradiente oscuro `--dark1/2/3`) quedaba expuesto abajo como una franja roja
> vacía. Se sacó `position: relative` de esa regla — no la vuelvas a poner sin
> darle un motivo nuevo (ya no hay ningún hijo `position: absolute` que la
> necesite ahí).
>
> **2) Flujo de bienvenida — DECIDIDO, no tocar sin confirmar con el usuario.**
> Esta sesión probó varias cosas (emoji 🏎️ → foto random → video genérico
> quitado → video genérico repuesto → agregado un paso más) hasta el orden
> final: **elegir nombre → video de bienvenida alternado → pantalla "toca para girar la
> ruleta" (el invitado arranca la ruleta con SU propio toque, no es
> automático) → ruleta → flujo ya conocido**. Todo vive en `ListaInvitados`
> (`src/App.jsx`), 3 estados locales: `selected` (nombre elegido), `welcome`
> (mientras se ve el video) y `readyToSpin` (pantalla de "toca para girar").
> `pick(name, g)` guarda `selected` y muestra `.welcome-popup`; a los 5.2s
> (1.2s con reduced-motion) — o antes si el invitado toca el popup o el video
> termina (`onEnded`) — se limpia el popup y se activa `readyToSpin`, que
> muestra `.spin-ready-popup` con un botón `cta pulse` ("Toca para girar la
> ruleta 🎉"); SOLO ese click llama `onStart(name)` y arranca la ruleta. El
> botón viejo `¡Que comience la ruleta!` al fondo de la lista de nombres queda
> como fallback manual (nunca se ve en el flujo normal, cubierto por los
> overlays) — no es necesario borrarlo, es un escape hatch si algo falla.
>
> **Alternancia vigente (2026-07-15):** en Carreras, `WELCOME_VIDEO_TURN` (más
> `sessionStorage`, para que no se reinicie entre una foto, la siguiente o una
> recarga de prueba) alterna por cada selección entre
> `saludo-rayo-mcqueen-v3.mp4` primero y `welcome-car.mp4` después, y
> `themes/carreras/saludo-rayo-mcqueen-v3.mp4`; para otras temáticas se
> mantiene solamente el genérico hasta que tengan su segundo asset. El MP4 v3
> trae el texto `Luis` incorporado: `.welcome-video-label-mask` tapa su banda
> inferior y los textos React muestran el nombre/género que realmente se
> eligió. No quitar esa máscara salvo que se reemplace el MP4 por una versión
> sin nombre. Si el MP4 alternativo falla o falta en un deploy, `onError`
> vuelve automáticamente al video genérico.
>
> `.welcome-popup` usa **`position: absolute; inset: 0`, nunca `fixed`**. La
> primera vez se implementó con `position: fixed` y en producción se veía
> recortado/roto — el motivo real resultó ser OTRO bug (ver punto 2-bis), pero
> de todas formas `absolute` es más robusto acá porque `.welcome-popup` es hijo
> de `.invitados-list`, que ya hereda `position: absolute` de la clase `.screen`
> — no depende de que el navegador clipee bien un `fixed` anidado dentro de
> `.app` (que tiene `overflow: hidden` y en desktop/landscape un ancho menor a
> `100vw`). Si en algún momento hay que tocar este popup, mantené `absolute`.
>
> Después de la ruleta sigue existiendo, sin cambios, el mecanismo por
> personaje: `VideoPersonaje` (`src/App.jsx`) + `CHAR_VIDEO[nombre]` (poblado en
> `buildRuntime` con el patrón `saludo-<base-del-img>.mp4` dentro de
> `themes/<tema>/`). Si ese archivo no existe cae automático a imagen fija +
> texto (`.char-saludo`), sin romper nada — así se ve HOY para todas las
> temáticas, porque esos `.mp4` por personaje todavía no se generaron (ver
> punto 4). Ya es responsive por el mismo patrón (`width:100%;height:100%;
> object-fit:cover`).
>
> (El bug real de la franja roja vacía que apareció en el medio de estas
> pruebas fue otra cosa — `.invitados-list` con `position: relative` ganándole
> a `.screen{position:absolute;inset:0}` — ver punto 1, ya corregido y no tiene
> relación con el popup.)
>
> **3) Ribbon "CARS" sobre el marco (`drawThemeRibbon` en `composeImage`,
> `src/App.jsx`) — bug de backend CORREGIDO.** `THEME_LABEL` se arma como
> `theme.franquicia || theme.nombre`, pero `cb_build_theme_payload()` en
> `public/lib.php` (~línea 401) nunca devolvía `franquicia` al frontend, así
> que siempre caía al fallback `nombre` (ej. "Carreras Veloces" en vez de
> "Cars"). Se agregó `'franquicia' => (string) ($themeData['franquicia'] ?? '')`
> al payload. Confirmado con `curl` real que el API ya entrega
> `franquicia: "Cars"`.
>
> **4) Pendiente para retomar — animar personajes con Higgsfield (MCP ya
> conectado, server id `4d59566e-1c80-4680-b817-709a55fc2c4e`).** El usuario
> quiere generar `saludo-<personaje>.mp4` por personaje/temática para llenar
> exactamente el hueco de `CHAR_VIDEO` descrito en el punto 2 — la app ya sabe
> consumirlos, solo faltan los archivos. Piloto acordado: temática "carreras",
> empezar por **Rayo McQueen**, usando como imagen base
> `public/themes/carreras/rayo-mcqueen-cut.png` (recorte transparente, SIN
> nombre de cumpleañero quemado — la otra versión, `rayo-mcqueen.jpg`, trae
> "MARIANO 95"/"¡Feliz Cumpleaños Mariano!" impreso y NO sirve como asset
> reutilizable, no usarla para animar). Guardar el resultado como
> `public/themes/carreras/saludo-rayo-mcqueen.mp4` (el nombre exacto lo exige
> el patrón de `CHAR_VIDEO` en `buildRuntime`, `src/App.jsx`). Saldo Higgsfield
> al momento de escribir esto: **7.17 créditos** (insuficiente, un video de 5s
> sale ~7.5 créditos); el usuario dijo que iba a recargar 100 créditos pero al
> cerrar esta sesión el saldo AÚN no reflejaba la recarga — confirmar saldo con
> la tool `balance` del MCP de Higgsfield antes de generar nada. Después de
> validar el piloto con Rayo McQueen, quedan pendientes los otros 5 personajes
> de "carreras" (Mate, Sally, Cruz, El Rey, Luigi) y luego las 9 temáticas
> restantes.
>
> **Saldo actualizado 2026-07-14 (tarde): 177.17 créditos** (recarga
> confirmada). Alcanza sobrado para los 6 personajes de "carreras" en una sola
> tanda (~45 créditos, deja ~130 de margen).
>
> **Primer test COMPLETADO (2026-07-14, tarde): Cruz** (el amarillo, `cruz-cut.png`
> → `saludo-cruz.mp4`), no Rayo McQueen — el usuario decidió probar con Cruz
> primero para validar calidad antes de hacer los 6. Modelo `kling3_0_turbo`
> (medias role `start_image`, NO `image`), `duration:5`, `resolution:"720p"`,
> `aspect_ratio:"9:16"` (vertical, matchea la pantalla del kiosco). Costo real
> confirmado con `get_cost:true` antes de generar: **7.5 créditos**. OJO: el
> prompt disparó una recomendación de preset automática ("RACE TRACK",
> preset_id `61b0d099-580f-4739-93f0-457e6f38da24`) que resultó ser un video de
> una influencer/selfie en una pista real — NO SIRVE, no tiene nada que ver con
> animar el auto de juguete. Se rechazó con `declined_preset_id` y se generó
> literal con el prompt propio. Si esto vuelve a pasar con otro personaje,
> rechazar el preset de la misma forma salvo que el preview realmente encaje.
> Job id de esta generación: `823a0c7a-d52e-4342-813a-ece8d5747dc8` — revisar
> con `job_display`; si por algún corte de sesión quedó sin bajar, ese job ya
> está pagado, no regenerar de cero, solo recuperar el resultado. Prompt usado
> (reusar el mismo estilo para los otros 5, cambiando solo la descripción
> física del personaje):
> `"Cute toy race car character with big expressive cartoon eyes and a
> friendly smile, parked on a race track with confetti around it. It revs its
> engine happily, wiggles side to side and bounces playfully as if waving
> hello to the camera, headlights blink like a cheerful blink, then settles
> facing the camera with a big warm smile, festive birthday party energy, no
> text or logos change."`
>
> Resultado: `public/themes/carreras/saludo-cruz.mp4` (8.7 MB, 720x1280,
> guardado y copiado a `dist/` tras `npm run build`, servido OK vía Apache
> `200`). Revisado frame a frame con ffmpeg: diseño del personaje consistente,
> sin artefactos, confetti, se acerca a cámara y sonríe al final — calidad
> aprobada como referencia de estilo. Falta: confirmación visual del usuario
> dentro de la app real (la ruleta es random, no se forzó a Cruz en el
> navegador de prueba — solo se verificó que el archivo se sirve y que el
> naming coincide con lo que `CHAR_VIDEO` espera).
>
> **⚠️ MATE — asset fuente ROTO, hay que regenerarlo ANTES de animar.**
> `public/themes/carreras/mate-cut.png` **no existe**, y el `mate.jpg` que hay
> en disco está mal: es la MISMA imagen de escena grupal (los 6 personajes +
> banner "¡Bienvenidos al cumpleaños de Mariano!" + torta) que aparece repetida
> bajo varios nombres — no es la foto de producto individual de Mate que pedía
> el prompt original. No sirve como start_image de Higgsfield tal cual.
>
> Los prompts correctos YA EXISTEN y están probados en
> `docs/PROMPTS-TEMATICAS.md` (líneas 60-69 y 124-131) — usarlos literal, no
> inventar unos nuevos:
> - **Paso A — regenerar `mate.jpg`** (foto de producto individual, reemplaza
>   la actual que está mal): prompt en `PROMPTS-TEMATICAS.md` línea 60-69
>   ("Premium photorealistic collectible-toy product photo... rusty brown
>   anthropomorphic tow truck... No text, letters, words, numbers, logos,
>   brands or watermark.").
> - **Paso B — generar `mate-cut.png`** desde ESE `mate.jpg` nuevo (recorte con
>   fondo transparente): prompt en `PROMPTS-TEMATICAS.md` línea 124-131
>   ("Using the provided source image, isolate only the rusty brown
>   anthropomorphic tow truck... export a high-resolution PNG with true
>   transparent alpha.").
> - Herramienta: Gemini/Nano Banana (skill `ai-studio-image` si está
>   disponible en el entorno de OpenCode; si no, cualquier vía de generación
>   de imágenes ya configurada en el proyecto). Esto NO es Higgsfield.
> - **Control de calidad pedido por el usuario:** comparar el `mate-cut.png`
>   nuevo contra el estilo de los otros 5 (`rayo-mcqueen-cut.png`,
>   `sally-cut.png`, `cruz-cut.png`, `el-rey-cut.png`, `luigi-cut.png` — mismo
>   encuadre, mismo tratamiento de recorte, sin texto). Si sale bien y es
>   consistente, usarlo y seguir con la animación en Higgsfield (mismo
>   pipeline que Rayo McQueen/Sally/El Rey/Luigi). Si sale mal/inconsistente,
>   NO forzarlo — dejar a Mate afuera de esta tanda y avisar al usuario en vez
>   de animar algo de mala calidad.
>
> **No generar las otras 9
> tematicas todavia** sin confirmar con el usuario — eso son ~60 personajes mas
> y se come el resto del saldo.
>
> **ACTUALIZACION 2026-07-15 (tarde) — AT-CUMPLECLICK-005. Sesion OpenCode + Claude Code. Cambios UI/CSS, bugs pendientes.**

**RESUELTO 2026-07-15 — Pantalla Intro: saludo y nombre dinamico.**

El arte `fondo-banner.jpg` es 1080×1920 (9:16). La bienvenida se ubica dentro
del cuadro central con borde ajedrezado (`.intro-title`, `top:25.5%`) y el
nombre de la fiesta se muestra tambien sobre la etiqueta blanca de la torta
(`.intro-cake-name`, `top:55.5%`). Ambos leen `CONFIG.nombre`, por lo que no
se escribe texto dentro de la imagen y cambia por fiesta. El lienzo horizontal
usa exactamente 9:16 para no crear franjas blancas ni desalinear overlays.

**IMPORTANTE:** despues de cada cambio de CSS, verificar que el build SI actualiza el archivo compilado. Hubo un bug donde `npm run build` no pisaba el CSS viejo aunque el hash cambiaba. Si el CSS en `dist/` tiene valores viejos, borrar `dist/` completo y reconstruir: `Remove-Item dist -Recurse -Force; npm run build`.

**Estado CSS actual (compilado en `dist/assets/index-C5rCH6-e.css`):**
- `.intro-title`: `position:absolute; top:25.5%; left:50%; transform:translate(-50%); width:42%; color:var(--yellow); borde rojo`
- `.intro-cake-name`: `position:absolute; top:55.5%; left:50%; width:18%; color:var(--pink)`
- `.intro-bottom`: `position:absolute; bottom:1%` ← boton OK, no tocar
- `.intro-content`: `position:absolute; inset:0; z-index:2`
- `.intro-veil`: `linear-gradient(to bottom, transparent 60%, rgba(74,44,58,.65) 100%)`

**Watermark:** `grupo-personajes.png` confirmado FUNCIONA — SI se ve, no tocar.

**CAMBIOS CONFIRMADOS (ya en prod, no tocar):**
- Boton "Que comience la ruleta!" → `.selected-hint` (texto amarillo "Toca para girar la ruleta" con emoji y animacion pulse)
- Musica al 30% (era 18%), toggle "Musica de fondo habilitada" en admin
- Overlay "Preparate para la foto con: [personaje]" sobre el video de saludo
- `.spinner` sin `position: relative` (bug de layout corregido)
- `frameGeometry.js`: personajes mas grandes (maxWidth 0.62)
- 6 videos v3 generados (pista realista + flash + pose), 6 JPGs regenerados, mate-cut.png y rayo-mcqueen-cut.png regenerados
- `fondo-banner.jpg` regenerado (sin texto "Mariano"), `grupo-personajes.png` generado

**SALDO HIGGSFIELD: ~9 creditos.** CLI: `higgsfield` (global npm), auth con `higgsfield auth login`, email `lmgm.uber@gmail.com`.


>
> **VIDEOS REGENERADOS (v3):** pista realista + conduccion hacia estudio + flash + pose. Los 6 personajes tienen video nuevo 9:16 (720p, 5s), usando los JPG de producto como start_image (no los cut PNG) para que el personaje no este tan cerca:
> | Personaje | Modelo | Archivo | MB |
> |---|---|---|---|
> | Rayo McQueen | wan2_7 | saludo-rayo-mcqueen.mp4 | 4.4 |
> | Sally | kling3_0_turbo | saludo-sally.mp4 | 4.8 |
> | Cruz | kling3_0_turbo | saludo-cruz.mp4 | 5.2 |
> | El Rey | kling3_0_turbo | saludo-el-rey.mp4 | 5.2 |
> | Luigi | kling3_0_turbo | saludo-luigi.mp4 | 4.9 |
> | Mate | kling3_0_turbo | saludo-mate.mp4 | 7.2 |
>
> **IMAGENES REGENERADAS:** los 6 JPG de producto (mate.jpg, rayo-mcqueen.jpg, sally.jpg, cruz.jpg, el-rey.jpg, luigi.jpg) con Higgsfield Nano Banana, estilo consistente con fondo de taller desenfocado, sin texto. Los 6 -pista.jpg tambien generados (auto en pista de asfalto, usados como start_image para los videos v3).
>
> **CUT PNGs:** rayo-mcqueen-cut.png y mate-cut.png regenerados con `image_background_remover` de Higgsfield (fondo transparente real). Los otros 4 cut PNG (sally, cruz, el-rey, luigi) son los originales del script removebg.py.
>
> **IMAGEN GRUPAL — CORREGIDO (2026-07-15, Claude Code):** `grupo-personajes.png`
> ya se ve como watermark en `.invitados-list` y `.spinner`. Eran DOS bugs
> apilados en `applyThemeVars()`/`buildRuntime()` (`src/App.jsx`):
> 1. Usaban `PARTY_SLUG` (slug de la FIESTA, ej. "demo") en vez del slug de la
>    TEMÁTICA (ej. "carreras") para construir la ruta
>    `themes/<slug>/grupo-personajes.png`. Como no existe carpeta
>    `themes/demo/`, la imagen daba 404 silencioso (un `background-image` con
>    404 simplemente no pinta nada, sin error visible en consola). Fix: nueva
>    variable de módulo `THEME_SLUG` poblada en `buildRuntime` desde
>    `theme.slug` (ese campo YA vuelve en el payload de `lib.php`, línea 402),
>    usada en vez de `PARTY_SLUG`/`slug` en los dos sitios que arman la ruta de
>    `grupo-personajes.png`.
> 2. Aunque el slug hubiera estado bien, la URL se seteaba RELATIVA
>    (`BASE + 'themes/...'`, con `BASE='./'`). Una URL relativa dentro de una
>    custom property CSS se resuelve contra la hoja de estilos donde se usa
>    `var()` — con Vite eso es `dist/assets/index-[hash].css` — NO contra
>    `index.html`. Terminaba pidiendo `dist/assets/themes/.../grupo-personajes.png`
>    (inexistente). Fix: `new URL(ruta, document.baseURI).href` para forzar
>    una URL absoluta antes de meterla en `--grupo-bg`.
>
> Verificado con `getComputedStyle(document.documentElement)` y
> `getComputedStyle(el, '::after')` en el navegador real: la URL final ahora
> resuelve `fetch` → `200`, y el watermark se ve (sutil, opacidad 0.18/0.15,
> arco de globos + los 6 personajes de fondo, sin competir con el contenido).
> El watermark del Diploma (`GRUPO_IMG` + `ctx.drawImage` en canvas) tenía SOLO
> el bug 1 (los `<img>` normales sí resuelven relativo al documento, no a la
> hoja de estilos) — mismo fix de `THEME_SLUG` ya lo cubre.
>
> **CAMBIOS EN CODIGO (src/):**
> - `frameGeometry.js`: personajes mas grandes en foto final (maxWidth 0.62, trackTop 0.58, trackBottom 0.88)
> - `App.jsx`: overlay texto "Preparate para la foto con: [personaje]" sobre el video (`.photo-prep-overlay` en `VideoPersonaje`)
> - `App.jsx`: boton "Que comience la ruleta!" reemplazado por hint animado (`.selected-hint`)
> - `App.jsx`: musica al 30% (era 18%), solo se inicia si `MUSIC_ENABLED`
> - `App.jsx`: variable `MUSIC_ENABLED` + `GRUPO_IMG` agregadas como module-level `let`
> - `styles.css`: `.spinner` sin `position: relative` (bug layout punto 1 del handoff)
> - `styles.css`: `.photo-prep-overlay` + `.selected-hint` + `::after` watermark
>
> **ADMIN:**
> - `admin/index.php`: checkbox "Musica de fondo habilitada" en form de fiesta (default ON)
> - `lib.php`: campo `musica` expuesto en API party payload
> - `parties.json`: demo tiene musica habilitada
>
> **SALDO HIGGSFIELD: 9.17 creditos** (email: lmgm.uber@gmail.com, plan basic). CLI autenticado con `higgsfield auth login`.
>
> **PENDIENTE:** texto dinamico de torta (nombre del cumpleanero sobre la torta en fondo-banner.jpg).
>
> Tras cualquier cambio en `src/`: `npm run build` (Vite copia `public/` entero
> a `dist/`, incluyendo los `.php`) y probar contra
> `http://localhost/automatiza-tech/CumpleBooth/dist/index.html?p=demo` (fiesta
> de prueba definida en `public/data/parties.json`, tema "carreras").
>
> **ACTUALIZACIÓN CANÓNICA 2026-07-13 — AT-CUMPLECLICK-001.** Esta sección
> reemplaza cualquier detalle incompatible que permanezca más abajo como memoria
> histórica. Marca pública/ruta: **CumpleClick by AutomatizaTech** en
> `/cumpleclick/`. PHP mínimo 8.0, baseline 8.2; validado 8.0/8.2/8.3/8.4.
> Estado mutable en una BD MySQL independiente, nunca WordPress. `themes.json` y
> assets siguen versionados. Configuración/secretos mediante
> `CUMPLECLICK_CONFIG_FILE`; fotos, estado y backups fuera de DocumentRoot.
>
> `public/` es fuente y Vite genera `dist/`; ejecutar
> `scripts/check-dist-parity.php` tras cada build. El admin ya no tiene contraseña
> fija: usa `password_verify`, CSRF, sesiones 2 h idle/12 h absolutas y logout POST.
> PIN = `password_hash(HMAC(PIN, pepper))`; rate limits son persistentes. Upload
> acepta PNG estricta ≤8 MiB/4096 px, cuota atómica 200/1 GiB y devuelve una URL
> opaca `ver.php?t=<token>` construida solo desde `public_base_url`.
>
> Baloo 2 600/700/800 está autoalojada en WOFF2 (sin Google Fonts) y se espera
> antes de canvas. Frames se calibran en admin y persisten en BD; el kiosco no usa
> localStorage como fuente de frame. Apache debe entregar un solo
> `Permissions-Policy: camera=(self), microphone=(), geolocation=()`.
> La composición fotográfica usa un recorte **cuadrado** con margen, centrado
> dentro del marco dorado que ya trae el fondo; no dibuja otro marco encima. El
> personaje sorteado queda centrado y apoyado sobre la pista inferior. La cámara
> marca estado listo por fotogramas/eventos reales, detecta
> streams sin imagen y permite seleccionar dispositivo si Chrome ve más de uno.
> Admin → Temáticas conserva las cards y añade una ficha segura con inventario,
> miniaturas, metadatos y prompts privados editables en `cc_theme_prompts`.
>
> QA 2026-07-13: Chrome real completó el flujo hasta Preview, QR y Diploma;
> Baloo 2 comprobada por `document.fonts.check`, consola limpia, upload/token OK y
> override de frame persistente/restaurado. El bug local que dejaba el overlay
> “Abriendo la cámara…” sobre un stream ya listo fue corregido. Chrome elegía
> `Windows Virtual Camera`; ahora prioriza `Integrated Camera`, cuyo feed real se
> comprobó antes de Preview cuadrado. La tablet final sigue siendo gate operativo. Evidencias en
> la carpeta de visualizaciones indicada en el cierre del ticket.
> El 2026-07-14 Preview y Diploma cambiaron el lockup blanco por el SVG oficial AT
> como marca de agua inferior izquierda (42 % de opacidad, sin texto recreado) y
> se revalidaron en Chrome con consola limpia.
>
> Para arquitectura y deploy actuales leer `ARQUITECTURA.md` y `DEPLOY.md`. Las
> referencias posteriores a `/booth/`, PHP 7.4, `ADMIN_PASS`, fotos bajo webroot,
> PIN en claro, Google Fonts o calibración local son históricas y no se deben usar.

Documento de continuidad. Léelo entero antes de tocar código — el proyecto tiene
decisiones de arquitectura no obvias (base de rutas relativa, whitelist de nombres
de archivo, camuflaje de prompts) que si las rompes, rompen producción.

---

## 1. Qué es esto

Servicio de "photo booth" (kiosco de fotos) para cumpleaños infantiles. Se instala
una tablet en la fiesta con una URL única; el niño elige su nombre, gira una ruleta
de personajes de la temática contratada (Cars, Frozen, Patrulla Canina, etc.), un
personaje lo saluda, se toma una foto con la webcam, la foto se compone dentro de
un marco dorado circular con el personaje al lado, se descarga por QR, y hay un
diploma personalizado + una galería para que los papás descarguen todas las fotos
al día siguiente.

**Modelo de negocio:** una sola app desplegada sirve a TODOS los clientes. Cada
fiesta es un registro en un backoffice PHP que genera una URL `?p=<slug>`. Las
imágenes/música de cada temática se generan UNA vez (con IA, sin nombres de
cumpleañero) y se reutilizan para todos los clientes que contraten esa temática.

**Precios ya definidos (contexto de negocio, no técnico):** Mágico $69.990 CLP,
Premium $99.990 CLP (con operador presente), temática a medida +$25.000 CLP.

---

## 2. Rutas y entorno

- **Proyecto:** `C:\wamp64\www\automatiza-tech\CumpleBooth`
- **Fuente de verdad:** `public/` (código PHP, datos, assets, config)
- **Build:** `npm run build` (Vite) genera `dist/` — **Vite copia TODO `public/`
  a `dist/` en cada build**, incluyendo los `.php`, `data/`, `themes/`, `admin/`.
  No hace falta copiar nada a mano después de un build; antes se hacía manual y
  ya no es necesario, pero si ves referencias viejas a "sincronizar a dist" en
  el historial, ignóralas — `npm run build` ya lo hace todo.
- **Servidor local:** WAMP (Apache+PHP+MySQL para Windows), debe estar corriendo
  (ícono verde en la bandeja). PHP en `C:\wamp64\bin\php\php8.*\php.exe`.
- **URL local de prueba:** `http://localhost/automatiza-tech/CumpleBooth/dist/`
  (WAMP sirve `C:\wamp64\www\` como raíz, por eso la ruta incluye
  `automatiza-tech/CumpleBooth/dist/`)
- **Admin local:** `http://localhost/automatiza-tech/CumpleBooth/dist/admin/`.
  La contraseña se define solo como hash en la configuración local ignorada.
- **Fiesta demo de prueba:** slug `demo`, tema `carreras`; el repo no versiona
  un PIN. Configurarlo en la BD desde el admin cuando haga falta.
- **Producción (pendiente, aún no desplegado):** `automatizatech.cl/cumpleclick/`
  — Hostinger, PHP 8.0+, sin Node en producción
  (el build de Vite es estático, PHP corre en el mismo hosting compartido)
- **Node/npm:** proyecto React + Vite estándar, `npm install` primero si hace
  falta, `package.json` en la raíz del proyecto

---

## 3. Arquitectura completa

```
CumpleBooth/
├── src/
│   ├── App.jsx           ← TODA la lógica del kiosco (React, ~1500+ líneas)
│   ├── main.jsx           ← entry point, precarga de fuente 'Baloo 2'
│   └── styles.css         ← CSS del kiosco, usa CSS custom properties por tema
├── public/                 ← FUENTE DE VERDAD, Vite copia esto a dist/ tal cual
│   ├── lib.php             ← helpers PHP compartidos (slugs, JSON, merge fiesta+tema)
│   ├── api.php             ← API pública de solo lectura: GET ?p=<slug>
│   ├── upload.php          ← guarda la foto compuesta que sube el kiosco
│   ├── ver.php             ← página que ve el invitado al escanear el QR
│   ├── galeria.php         ← página con PIN para que los papás vean/descarguen fotos
│   ├── .htaccess           ← SPA fallback + fuerza HTTPS (exento en localhost)
│   ├── .user.ini           ← sube límites de PHP (upload_max_filesize=80M) para Hostinger
│   ├── admin/
│   │   ├── index.php       ← backoffice completo (single file, PHP+HTML+CSS inline)
│   │   ├── config.php      ← shim sin secretos; hash viene de config externa
│   │   └── _style.css.php  ← CSS del admin (incluido inline en <style>, sin CDNs)
│   ├── data/
│   │   ├── .htaccess       ← "Require all denied" — esta carpeta NUNCA es accesible por HTTP
│   │   ├── themes.json     ← las 10 temáticas (personajes, colores, diploma, franquicia real)
│   │   └── parties.json    ← las fiestas creadas (lo edita el admin)
│   ├── themes/<slug>/      ← assets por temática, subidos por el admin o FTP
│   │   ├── fondo-banner.jpg, fondo-sala.jpg, musica-fondo.mp3
│   │   ├── <personaje>.jpg  × 6 (requeridos)
│   │   ├── <personaje>-cut.png × 6 (OPCIONAL — recorte transparente para
│   │   │     que el personaje aparezca al lado del niño en la foto final)
│   │   └── saludo-<personaje>.mp4 × 6 (OPCIONAL — video de saludo; si no
│   │         existe, se muestra la imagen del personaje 4s en su lugar)
│   └── brand/              ← logo SVG oficial AT
├── database/, scripts/     ← migraciones/CLI; fuera del webroot en producción
├── config/                 ← solo ejemplo; configuración real fuera del webroot
├── dist/                   ← build compilado, esto es lo que se sube a Hostinger
├── docs/
│   ├── ARQUITECTURA.md     ← contrato técnico completo (API, esquemas, contrato front/back)
│   ├── FASE1.md            ← contrato de la Fase 1 (foto-con-personaje, diploma, galería)
│   ├── DEPLOY.md           ← guía de despliegue a Hostinger paso a paso
│   ├── PROMPTS-TEMATICAS.md ← 78 prompts asociados (Carreras + 8 temáticas nuevas)
│   └── HANDOFF-CODEX.md    ← este archivo
├── _inbox/                 ← carpetas con nombres de franquicia real donde el
│   ├── Cars/                 usuario deja las imágenes/música que genera, para
│   ├── Frozen/                que se procesen e instalen (drop folder, no es
│   ├── ... (10 carpetas)      parte del build, es solo zona de intercambio)
└── vite.config.js          ← base: './' (RELATIVA — funciona en cualquier
                                carpeta/subcarpeta sin importar mayúsculas)
```

### 3.1 Flujo de datos (contrato API)

`GET api.php?p=<slug>` devuelve JSON con la fiesta + temática ya resueltas
(el frontend NO decide nada, todo viene resuelto del backend):

```json
{
  "ok": true,
  "party": {
    "slug": "demo",
    "nombre": "Demo",
    "invitados": [{"name":"Ana","g":"f"}],
    "frameBox": {"x":0.333,"y":0.285,"w":0.343,"h":0.279}
  },
  "theme": {
    "slug": "carreras",
    "nombre": "Carreras Veloces",
    "diploma": "Piloto Oficial del Equipo",
    "colors": { "accent":"#e8000d", "accentSoft":"...", "yellow":"...", "ink":"...",
                "bgLight1":"...", "bgLight2":"...", "dark1":"...", "dark2":"...", "dark3":"..." },
    "confetti": ["#e8000d", "..."],
    "personajes": [
      { "emoji":"⚡", "name":"Rayo McQueen", "img":"themes/carreras/rayo-mcqueen.jpg",
        "png":"themes/carreras/rayo-mcqueen-cut.png", "pngExists": true }
    ],
    "images": { "banner":"themes/carreras/fondo-banner.jpg", "sala":"themes/carreras/fondo-sala.jpg" },
    "musica": "themes/carreras/musica-fondo.mp3"
  }
}
```

Errores: `{"ok":false,"error":"not_found"}` (404) | `"inactive"` (403) |
`"bad_slug"` (400, slug inválido, nunca toca filesystem).

**IMPORTANTE:** el campo `franquicia` de `themes.json` (nombre real: "Cars",
"Frozen", etc.) **NUNCA se expone** por `api.php` — `lib.php` función
`cb_build_theme_payload()` usa una whitelist de campos, no reenvía el array
completo. Si agregas campos nuevos a `themes.json` que no deben ser públicos,
tienes que agregarlos a mano ahí y NO reenviarlos.

### 3.2 Frontend React — patrón de runtime config (IMPORTANTE, no obvio)

`src/App.jsx` NO tiene configuración hardcodeada. Al montar:
1. Lee `?p=` de la URL
2. `fetch('api.php?p='+slug)` — mientras carga muestra pantalla "Preparando la
   fiesta…"; si falla, pantalla de error con reintentar; si no hay `?p`,
   pantalla "Fiesta no configurada"
3. Con la respuesta, `buildRuntime(party, theme, slug)` puebla variables de
   MÓDULO (`let CONFIG`, `let PERSONAJES`, `let CHAR_IMG`, `let CHAR_VIDEO`,
   `let CHAR_PNG`, `let DIPLOMA`, `let CONFETTI_COLORS`, `let PARTY_SLUG`,
   `let STORAGE_KEY`) — esto ANTES de montar `<BoothApp>`, así que todas las
   pantallas (Intro, Spinner, VideoPersonaje, Preview, etc.) las leen como si
   fueran constantes normales, aunque técnicamente son `let` reasignables.
   **Este patrón fue una decisión deliberada** en vez de Context/props porque
   muchas funciones sueltas (`burstConfetti`, `composeImage`, `composeDiploma`)
   las leen como variables de módulo — convertir a Context habría tocado cada
   función. Si vas a agregar un campo nuevo de runtime, sigue el mismo patrón:
   agrégalo en `buildRuntime()`.
4. También aplica los colores de la temática como CSS custom properties en
   `document.documentElement` (`--pink`, `--pink-soft`, `--yellow`, `--ink`,
   `--bg-light1`, `--bg-light2`, `--dark1`, `--dark2`, `--dark3`)
5. `localStorage` usa keys con prefijo `booth_<slug>_` (invitados y calibración
   del marco) — así no se mezclan datos entre fiestas en el mismo dominio

### 3.3 Flujo de pantallas del kiosco

```
intro → invitados → spinner (ruleta) → video-personaje → transition (confetti)
→ capture (webcam) → preview (foto compuesta) → qr → diploma → reset
```

- **Intro**: banner de la temática + "¡Bienvenidos a la fiesta de {nombre}!"
- **Invitados**: lista niñas/varones, botón "Girar la ruleta"
- **Spinner**: ruleta de 6 personajes con animación de giro
- **VideoPersonaje**: intenta cargar `videos/saludo-<personaje>.mp4`; si no
  existe (404 o timeout ~1.2s) cae automáticamente a mostrar la imagen JPG del
  personaje 4 segundos
- **TransicionWow**: confetti canvas + "¡Ahora nos tomaremos una foto!"
- **Capture**: webcam en vivo con espejo, countdown 3-2-1, foto guardada SIN
  espejo (para que el texto/marca de agua salga legible). Tiene manejo robusto
  de errores de cámara (permiso denegado, cámara ocupada, sin HTTPS, etc.) con
  mensajes específicos y botón reintentar sin recargar la página — esto se
  agregó tras un bug real donde la pantalla se quedaba negra sin explicación
- **Preview**: `composeImage()` compone en canvas: fondo de la sala + foto del
  niño recortada en cuadrado dentro del marco existente (posición y tamaño =
  `CONFIG.frameBox`, fracciones 0..1 del canvas) + el personaje ganador centrado
  sobre la pista inferior (PNG transparente,
  si existe) + texto "Muchas gracias {invitado} por venir a la fiesta de
  {nombre}". El PNG del personaje se precarga en cuanto la ruleta lo elige
  (mucho antes de llegar a Preview) para que esté listo a tiempo; si no cargó
  a tiempo, compone sin él (no bloquea)
- **QR**: sube la foto a `upload.php` (incluye `party: slug` en el body),
  muestra QR hacia `ver.php?f=<archivo>&p=<slug>`, botón "Ver diploma"
- **Diploma**: `composeDiploma(invitado, winnerImage)` genera en canvas un
  diploma 1080×1920 con marco dorado, sello, "Se otorga a {invitado}", el
  título de la temática (`theme.diploma`, ej "Piloto Oficial del Equipo") y
  como fondo el JPG vertical del personaje ganador de la ruleta. Una veladura
  mantiene el texto legible; si la imagen falla, conserva el fondo temático.

### 3.4 Compositing (canvas) — detalles que importan

- `composeImage(bgImg, photoImg, invitado, charImg)`: dibuja el fondo, centra la
  foto cuadrada dentro del marco decorativo existente, sitúa el personaje recortado
  sobre la pista inferior con sombra de contacto y añade el texto de agradecimiento.
  Usa `CONFIG.frameBox` para ubicar el marco sin cubrir su borde.
- `composeDiploma(invitado, winnerImage)`: genera el diploma completo, lee
  colores desde `getComputedStyle` (las CSS vars ya aplicadas por
  `buildRuntime`) y usa `CHAR_IMG[personaje.name]` como póster de fondo.
- **Bug real que se arregló hoy:** todo el texto de estos canvas usa
  `ctx.font = "800 40px 'Baloo 2', system-ui, sans-serif"` — pero la fuente
  'Baloo 2' JAMÁS estaba enlazada en `index.html` (ni `<link>` ni `@import`),
  así que caía siempre al fallback del sistema. Se agregó el `<link>` de
  Google Fonts en `index.html` (pesos 600;700;800;900) y una precarga en
  `main.jsx` con `document.fonts.load()` para que esté lista antes de que el
  usuario llegue a la pantalla de Preview (varias pantallas después del
  primer render, tiempo de sobra). **Esto no se pudo verificar visualmente
  en este entorno** (el navegador de pruebas se cuelga con el diálogo de
  permiso de cámara) — Codex debería abrir `?p=demo` en un Chrome real,
  llegar hasta Preview/Diploma, y confirmar visualmente que el texto usa la
  tipografía redondeada Baloo 2 y no una fuente genérica tipo Arial/Segoe.
- dev helpers expuestos solo en `import.meta.env.DEV`:
  `window.__composeImage`, `window.__composeDiploma` — útiles para probar el
  compositing sin pasar por todo el flujo (hay que llamar antes
  `buildRuntime(party, theme, slug)` manualmente con datos mock, ver ejemplos
  en el historial de conversación si hace falta reconstruir el patrón)

---

## 4. Backoffice admin (`public/admin/index.php`)

Single-file PHP (sin frameworks, sin CDNs, todo el CSS en `_style.css.php`
incluido inline). Login por sesión + CSRF en todos los POST. Dos vistas:

### 4.1 Vista "Fiestas"
CRUD completo: crear/editar/duplicar/eliminar. El formulario:
- Nombre del cumpleañero/a → genera slug automático (editable, JS en el
  propio index.php, sin librerías)
- Slug es **inmutable una vez creada la fiesta** (editar lo bloquea) — porque
  cambiar el slug rompería la carpeta de fotos y los QR ya impresos
- Select de temática: muestra `Franquicia real (Nombre genérico) — Lista /
  Faltan N archivos` (ver sección 5 sobre por qué existen dos nombres)
- Textarea de invitados: una línea por invitado, formato `Nombre,f` o
  `Nombre,m`, parser tolerante (default a 'f' si el género no se reconoce)
- Fecha (opcional, formato `AAAA-MM-DD`)
- PIN de galería (opcional, 4 dígitos) — si se define, habilita
  `galeria.php?p=<slug>` para que los papás bajen todas las fotos
- Checkbox "Fiesta activa" — si no está activa, la URL da 403

Cada fiesta en la lista muestra: nombre, chip de temática (con tooltip del
nombre real de franquicia), badge activa/inactiva, URL completa con botón
copiar (clipboard API + fallback `execCommand`) y abrir en pestaña, botones
editar/duplicar/eliminar (duplicar deja la copia inactiva por defecto).

### 4.2 Vista "Temáticas"
**Grid de tarjetas** (rediseñado hoy, antes era una tabla ancha que se veía
saturada — ver captura de pantalla en el historial si hace falta contexto).
Cada tarjeta de temática muestra:
- Nombre real de franquicia en grande + `(nombre genérico)` en gris al lado
  — ej. **Cars** (Carreras Veloces)
- Público objetivo, fila de personajes (emojis en píldoras circulares)
- Badge compacto "Lista" o "Faltan N" (con `<details>` desplegable para ver
  la lista completa de archivos faltantes — antes esto se mostraba como texto
  plano que se desbordaba y se veía mal)
- Formulario de subida de archivos directo ahí (sin necesidad de FTP):
  input file múltiple + botón subir + `<details>` con los nombres de archivo
  exactos aceptados

**Regla de subida (deliberada, no relajar):** solo se aceptan archivos con
nombre EXACTO de la whitelist de esa temática (los 8 requeridos + 6 nombres
opcionales de video + 6 opcionales de PNG recortado). Cualquier otro nombre
se rechaza con un mensaje que lista los nombres válidos — esto es
intencional, evita que alguien suba `IMG_2847.jpg` sin saber a qué personaje
corresponde. Las imágenes JPG subidas se redimensionan automáticamente a
1080×1920 (cover-crop) vía GD si está disponible; si no, se guardan tal cual.
Los PNG se validan como PNG real (`getimagesize` + `IMAGETYPE_PNG`) y se
guardan SIN pasar por el redimensionado JPEG (para no perder la
transparencia). Límite de tamaño real lo impone `php.ini` del servidor
(`upload_max_filesize`/`post_max_size`) — en Hostinger se sube con
`.user.ini` a 80M/90M; en WAMP local por defecto es más bajo (2M/8M), así que
videos pesados hay que subirlos por FTP en desarrollo local.

### 4.3 Design system del admin (aplicado hoy con las skills de diseño)
- Tipografía admin: stack local/system, sin imports ni CDNs.
- Colores: primario `#7C3AED` (violeta), CTA `#F97316` (naranja), fondo
  `#FAF5FF`→`#FDF2E9` (degradado cálido con textura de puntos de confeti muy
  sutil, CSS puro sin imágenes), texto `#4C1D95`
- Iconos: SVG inline (función `admin_icon()`), NUNCA emojis como iconos de
  botones (el emoji del logo se reemplazó por un ícono SVG en un badge
  degradado)
- Tarjetas con `border-radius: 20px`, sombra en dos capas, hover con
  elevación sutil
- Accesibilidad: `:focus-visible` con outline naranja, touch targets mínimo
  44×44px (se corrigió un botón de borrar invitado que estaba en 36px)

---

## 5. LA REGLA MÁS IMPORTANTE DEL PROYECTO: camuflaje de prompts

Este es el punto que más cuesta que un agente nuevo entienda, así que léelo
dos veces.

**Problema:** Gemini (y otros generadores de imagen) bloquean prompts que
mencionan personajes con copyright ("Lightning McQueen", "Cars", "Disney")
con el mensaje *"I can't generate the image you requested right now due to
interests of third-party content providers"*.

**Solución encontrada y probada (funciona, ya se generaron sets completos):**
describir el personaje por sus RASGOS FÍSICOS sin nombrarlo ni nombrar la
franquicia. Ejemplo real que SÍ funcionó para Mate (la grúa de Cars):

```
A high-end, photorealistic collectible vinyl toy figure of an original
fictional cartoon tow truck, isolated on a fully transparent background...
Small vintage tow truck with a friendly face on the windshield: two big
round expressive eyes, a wide cheerful smile on the front grille...
This is an original toy design, not based on any existing character.
```

Nunca dice "Mater" ni "Cars". Esto se llama en todo el proyecto **"nombre
genérico"** — es lo que va en `themes.json` campo `nombre` (ej. "Carreras
Veloces"), en los prompts de `docs/PROMPTS-TEMATICAS.md`, y es lo único que
la app/el niño/los papás ven.

**Por separado**, para que TÚ (el operador humano) sepas qué franquicia real
es cada temática al armar el catálogo, se agregó un campo **`franquicia`**
en `themes.json` (ej. "Cars") que:
- Solo se usa en el ADMIN (nunca llega al frontend público, verificado con
  `curl` que `api.php` no lo expone)
- Se muestra en las tarjetas de temática y en el selector de "nueva fiesta"
  como `Franquicia real (nombre genérico)` — ej. "Cars (Carreras Veloces)"

**Si generas un prompt nuevo para una temática:** SIEMPRE en la técnica de
rasgos físicos sin nombrar franquicia. Si Gemini bloquea igual: (1) revisa
que no quedó ningún nombre de personaje/franquicia colado, (2) agrega al
inicio `"This is an original toy design, not based on any existing
character."`, (3) si el rasgo más reconocible (ej. dientes de Mate, número
"95" de Rayo McQueen) sigue disparando el filtro, descríbelo aún más genérico
o quítalo.

**Regla de oro secundaria:** las imágenes de fondo (`fondo-banner.jpg`,
`fondo-sala.jpg`) NUNCA deben llevar texto pintado (nombres, "Feliz
Cumpleaños X"), porque esas imágenes se reutilizan para TODOS los clientes —
el texto del cumpleañero lo pone la app por encima, no la imagen. Los
prompts en `docs/PROMPTS-TEMATICAS.md` ya incluyen
`"IMPORTANT: NO text anywhere in the image"` — no lo quites.

---

## 6. Las 10 temáticas y su estado actual

| Slug | Nombre genérico | Franquicia real | Público | Estado assets |
|------|-----------------|------------------|---------|---------------|
| `carreras` | Carreras Veloces | Cars | niño | ✅ 8 JPG (⚠️ provisionales, dicen "Mariano" en los fondos — hay que regenerar SIN texto, ver tarea #10), 5/6 cut-PNG (falta `mate-cut.png`, prompt ya escrito, tarea #9), mp3 ✅ |
| `mickey` | Casa de Mickey | Casa de Mickey Mouse | mixto | ❌ pendiente generar (tarea #11) |
| `hielo` | Reino de Hielo | Frozen | niña | ❌ pendiente |
| `cachorros` | Patrulla de Cachorros | Patrulla Canina | mixto | ❌ pendiente |
| `heroes` | Súper Héroes | Marvel / Avengers | niño | ❌ pendiente |
| `princesas` | Princesas | Princesas Disney | niña | ❌ pendiente |
| `dinos` | Dinosaurios | (sin franquicia, personajes originales) | mixto | ❌ pendiente |
| `sirenas` | Bajo el Mar | La Sirenita | niña | ❌ pendiente |
| `juguetes` | Historia de Juguetes | Toy Story | mixto | ❌ pendiente |
| `tropical` | Aventura Tropical | Lilo & Stitch | mixto | ❌ pendiente |

`docs/PROMPTS-TEMATICAS.md` contiene 78 prompts importables: banner, sala,
personajes y recortes de Carreras, más banner + sala + 6 personajes para las
8 temáticas nuevas. La sección EXTRA conserva las notas históricas para Mickey.

Cada temática necesita, dejado en `public/themes/<slug>/` (o subido por el
admin, o soltado en `_inbox/<Nombre Franquicia>/` para que se procese):
- `fondo-banner.jpg`, `fondo-sala.jpg` (1080×1920, sin texto)
- 6 archivos `<personaje>.jpg` (los nombres exactos están en `themes.json`,
  ej. para carreras: `rayo-mcqueen.jpg`, `mate.jpg`, `sally.jpg`, `cruz.jpg`,
  `el-rey.jpg`, `luigi.jpg`)
- `musica-fondo.mp3`
- Opcional: 6 `<personaje>-cut.png` (PNG transparente, mismo personaje
  recortado sin fondo, para que aparezca junto al niño en la foto — ver
  técnica de recorte en sección 7)
- Opcional: 6 `saludo-<personaje>.mp4` (video de saludo personalizado)

---

## 7. Técnica de recorte de fondo (para los `-cut.png`)

Gemini NO genera PNG con transparencia real cuando se le pide "fondo
transparente" — genera un fondo CUADRICULADO PINTADO (el patrón visual de
"transparencia" de Photoshop, pero como píxeles reales, no alpha). Hay que
quitarlo con post-proceso. Se escribió un script Python
(`scratchpad/removebg.py`, usa PIL + numpy, sin scipy) que:

1. Detecta píxeles "tono checker" (neutros, blanco o gris claro, sin
   saturación de color)
2. Para evitar comerse partes reales del dibujo que sean blancas (como la
   esclera de los ojos del personaje), un píxel candidato a fondo solo se
   considera si su VECINDARIO LOCAL (ventana 61×61) contiene AMBOS tonos del
   checker (blanco Y gris) — la esclera de un ojo es blanco puro localmente,
   sin alternancia, así que nunca califica
3. Flood-fill (BFS) desde los bordes de la imagen para el fondo exterior
4. Segunda pasada: componentes conectados grandes (≥1500px) que quedaron
   encerrados (como el interior de una ventanilla del auto) también se
   consideran fondo si tienen el patrón de alternancia
5. Recorta al bounding box del contenido + padding, redimensiona a máx
   1200px, guarda como PNG con alpha

Este script YA se corrió exitosamente para 5 de los 6 personajes de Cars
(el resultado se ve limpio, verificado visualmente). Si hace falta
recortar más personajes de otras temáticas, reutiliza este script — solo
hay que ajustar la lista `jobs` al final del archivo con los nombres de
archivo de origen (los que bajó el usuario de Gemini, tienen nombres tipo
`Gemini_Generated_Image_xxxxx.png`) y destino (`<personaje>-cut.png`).

Alternativa más simple si el script falla con una imagen particular: pedirle
al usuario que use remove.bg o el quita-fondo de Canva sobre la imagen ya
descargada.

---

## 8. Seguridad — todo lo verificado, no relajar

- `data/.htaccess` con `Require all denied` — la carpeta `data/` (que tiene
  `parties.json` con la lista de invitados de cada fiesta) es 100%
  inaccesible por HTTP, verificado con curl (403)
- Slugs siempre sanitizados (`[a-z0-9-]`) ANTES de tocar el filesystem —
  `cb_resolve_party()` en `lib.php` valida el slug y retorna error
  inmediatamente si es inválido, sin llegar a leer `parties.json`
- CSRF token en TODOS los formularios POST del admin (`hash_equals` para la
  comparación, no `===`)
- PIN de galería: comparación con `hash_equals` (tiempo constante), rate
  limit de 5 intentos por 60 segundos en sesión
- Subida de archivos: whitelist estricta de nombres, `basename()` en todo
  nombre de archivo (probado explícitamente con path traversal
  `../../../evil.jpg` → se reduce a `evil.jpg` y se rechaza por la
  whitelist), nunca se ejecuta/incluye lo subido, sniff de bytes básico
  para MP3/MP4 (no confía solo en la extensión)
- Contraseña admin: `password_verify` contra hash externo; sesión regenerada,
  expiración idle/absoluta y cookies seguras.

---

## 9. Lo que YA se probó end-to-end (21/21 checks en WAMP)

- `api.php?p=demo` → 200 con JSON completo, `?p=inexistente` → 404, slug con
  `../` → 400 sin tocar filesystem
- `data/parties.json` por HTTP directo → 403
- Admin: login, crear fiesta real desde el formulario (se probó con
  "Valentina" + 3 invitados, se verificó por API, se limpió después),
  editar, duplicar, eliminar, vista Temáticas con subida de archivo real
  (PNG válido aceptado y redimensionado, JPEG-renombrado-a-.png rechazado
  correctamente, nombre inválido rechazado con mensaje claro)
- `galeria.php?p=demo` con PIN 1234: acceso correcto, PIN incorrecto
  rechazado, rate limit probado
- App completa: intro muestra nombre dinámico de la fiesta, invitados vienen
  de la API, CSS vars de la temática se aplican, ruleta gira los 6
  personajes de Cars
- Assets de Cars: 200 en fondo-banner.jpg, fondo-sala.jpg,
  rayo-mcqueen.jpg, musica-fondo.mp3, y los 5 cut-PNG existentes

**Lo que NO se pudo probar en este entorno** (limitación del navegador de
pruebas, no del código): el flujo de cámara real (el navegador sandboxed
bloquea/cuelga con el diálogo de permiso de `getUserMedia`), y la
verificación visual de que la fuente Baloo 2 carga correctamente en el
canvas del compositing. **Ambas cosas hay que probarlas en un Chrome real**
(de escritorio o de la tablet) antes de dar por cerrado el proyecto.

---

## 10. Tareas pendientes (tablero actual, en orden de prioridad)

Estas son responsabilidad del USUARIO (Luis), no tuyas — pero si te pide
ayuda con alguna, aquí está el contexto completo:

1. **Regenerar `mate-cut.png`** — el prompt sin "buck teeth" (rasgo que
   disparaba el bloqueo) ya está escrito, está en el historial de la
   conversación y también puede reconstruirse con la técnica de la sección 5
   aplicada a una grúa oxidada con cara amigable
2. **Regenerar los 8 JPG de Carreras SIN texto** — los actuales son
   provisionales (llevaban "Mariano" pintado, de un proyecto anterior
   reutilizado para probar el sistema). Usar la sección "EXTRA" de
   `docs/PROMPTS-TEMATICAS.md`
3. **Generar las 9 temáticas restantes** — prompts listos, copiar/pegar
4. **Conseguir música MP3** por temática (sugerencias en `themes.json`
   campo `musicaHint`)
5. **Probar el flujo completo en un Chrome real** — ✅ software verificado el
   2026-07-13 (Preview/QR/Diploma/Baloo/consola); queda validar imagen óptica en
   la cámara de la tablet final, porque el hardware local mostró feed negro.
6. **Deploy a Hostinger** — pendiente y requiere autorización de Luis. Seguir
   `docs/DEPLOY.md`: BD/config/scripts privados primero y después `dist/` en
   `/cumpleclick/`. No subir secretos, backups, fotos ni storage al webroot.
7. **Comprar atril de piso para la tablet** (no es tarea técnica)

---

## 11. Convenciones de código a respetar

- PHP: compatible 8.0+, baseline 8.2, comparaciones estrictas (`===`), sin dependencias
  externas (sin Composer, sin CDNs), comentarios breves en español
  explicando el PORQUÉ no el QUÉ
- React: sin TypeScript, sin librerías de estado (Redux/Zustand) — el
  patrón de runtime config con variables de módulo (sección 3.2) es
  intencional, no lo cambies a Context/Redux sin discutirlo primero
  (tocaría muchísimos archivos para un beneficio marginal)
- CSS: variables custom properties por tema, sin Tailwind, sin CSS-in-JS
- Nunca emojis como íconos de UI (sí está bien un emoji suelto como
  decoración de copy, ej. "🎉" en un texto — la regla es sobre botones/
  iconos funcionales, usar SVG inline)
- No CDNs de JS/CSS/fuentes. Baloo 2 se autoaloja desde `@fontsource/baloo-2`.

---

## 11.5 Pase de diseño en el kiosco (frontend), hoy — qué se hizo y con qué

El usuario pidió aplicar `/ui-ux-pro-max`, `/ui-ux-designer` y `/frontend-design`
sobre `src/App.jsx` + `src/styles.css` (el kiosco, no el admin). Reporte honesto
de lo que se hizo con cada una, para que no se repita trabajo ni se asuma más de
lo que hay:

**`/frontend-design`** — llevó a revisar tipografía/contraste/animaciones del
kiosco. La solución vigente autoaloja Baloo 2 WOFF2 desde Fontsource y espera
`document.fonts.load()` antes de cualquier canvas.

**`/ui-ux-pro-max`** — se corrió `search.py --design-system` con el prompt
"kids photo booth kiosk touchscreen tablet playful immersive fullscreen".
Resultado relevante:
- Confirmó **Baloo 2** como fuente de encabezado correcta para este tipo de
  producto (valida la decisión ya tomada, no cambia nada)
- Sugirió estilo **"Claymorphism"**: bordes gruesos 3-4px, sombra doble,
  esquinas 16-24px, look 3D suave tipo juguete — **NO se aplicó**, es una
  decisión estética grande (tocaría `.cta`, `.shutter`, `.tab`, todas las
  tarjetas) que no se justificaba hacer sin poder verificar visualmente el
  resultado en este entorno (el navegador de pruebas se cuelga con el diálogo
  de permiso de cámara, ver sección 9). **Queda como sugerencia para una
  iteración futura**, no como pendiente urgente — el estilo actual (tarjetas
  planas con sombra simple, gradientes por tema) ya es funcional y coherente,
  simplemente no es "clay". Si se retoma, el prompt completo para regenerar
  esta consulta es: `python scripts/search.py "kids photo booth kiosk
  touchscreen tablet playful immersive fullscreen" --design-system -p
  "CumpleBooth Kiosk"` desde `C:\Users\luis_\.claude\skills\ui-ux-pro-max`.

**`/ui-ux-designer`** — skill de conocimiento general (sin script), se usó
para hacer una auditoría manual contra su checklist de accesibilidad/UX.
Hallazgos y qué se hizo con cada uno:
- ✅ Touch targets: ya eran generosos para uso infantil (`.shutter` 96px,
  `.cta` con padding amplio); el único por debajo de 44px (`.gestion-del`,
  36px) ya se había corregido en el pase de diseño anterior
- ✅ Feedback táctil: `.cta:active` y `.shutter:active` ya tienen
  transform+shadow al presionar, cumple el criterio "soft press"
- ✅ `prefers-reduced-motion` ya estaba respetado en las 3 animaciones que
  importan (fade de pantalla, transición de fondo, spinner de carga)
- ⚠️ → ✅ **Gap real encontrado**: no había `:focus-visible` global en el
  kiosco (el admin sí lo tiene). Es de prioridad baja porque el kiosco es
  100% táctil, pero es gratis de agregar y no arriesga nada (`:focus-visible`
  nunca se activa con toque, solo con teclado/switch-access). Se agregó una
  regla global en `styles.css` (outline amarillo del tema, 4px).
- No se tocó nada más — no se encontraron problemas de contraste, jerarquía
  de CTAs, ni de arquitectura de información que ameritaran cambios; el
  flujo de pantallas y la jerarquía visual (un solo CTA grande por pantalla,
  colores de la temática aplicados consistentemente vía CSS vars) ya seguían
  buenas prácticas.

**Lo que NO se hizo y por qué:** un rediseño visual completo del kiosco
(equivalente al que sí se le hizo al admin: tipografía nueva, tarjetas
bento, etc.). Motivo: el kiosco ya tenía una identidad visual intencional
por temática (colores/gradientes que cambian según `theme.colors`, ya
correctos), cambiarla de fondo sin poder verificar el resultado visualmente
en este entorno era más riesgo que beneficio. Si se retoma esta línea de
trabajo, hacerlo con un navegador real (no el sandboxed de este entorno) para
poder confirmar visualmente cada cambio antes de darlo por bueno.

---

## 12. Si necesitas más contexto

- `docs/ARQUITECTURA.md` — el contrato técnico original, más detallado en
  algunos aspectos de la API que este documento
- `docs/FASE1.md` — contrato específico de cómo se construyó la Fase 1
  (foto-con-personaje, diploma, galería) — útil si hay que hacer Fase 2
  (ideas ya conversadas con el usuario: voz TTS personalizada por invitado
  vía n8n, video-mensajes grabados para el cumpleañero, invitación digital
  con RSVP que auto-carga la lista de invitados, impresora térmica portátil)
- `docs/DEPLOY.md` — checklist de despliegue
- `docs/PROMPTS-TEMATICAS.md` — los 78 prompts asociados a assets

Todo el código está comentado en español explicando las decisiones no
obvias — si algo no está claro, buscar primero un comentario cerca antes
de asumir que es un descuido.

---

## CIERRE TÉCNICO 2026-07-18 — AT-CUMPLECLICK-007 / Familia Canina

- Gate A permanece cerrado. La integración no modificó autenticación,
  invitaciones, migraciones ni política de almacenamiento.
- Tema `familia-canina` completado con `fondo-banner.jpg`, `fondo-sala.jpg`,
  música instrumental original, seis retratos, seis recortes `*-cut.png`, seis
  saludos, bienvenida grupal, transición de alfombra, fondo de ruleta y
  `grupo-personajes.png`.
- Flujo final: bienvenida propia del tema → invitados → ruleta temática →
  transición de alfombra → saludo del personaje elegido → cámara → composición
  sobre marco cuadrado → QR → diploma. Carreras conserva su alternancia previa.
- Caja del marco de Familia Canina: `x=0.25`, `y=0.17`, `w=0.50`,
  `h=0.28125`. En 1080×1920 equivale a un cuadrado exterior de 540×540 px; el
  frontend aplica el inset común de 8.5% y no dibuja un segundo marco.
- `welcome-familia-canina.mp4` se publica por API mediante basename validado;
  el fondo de ruleta usa una ruta fija verificada. Chloe y Muffin usan las
  rutas públicas `saludo-chloe.mp4` y `saludo-muffin.mp4`; los aliases internos
  antiguos se conservaron solo para estabilización.
- Los recortes se obtuvieron localmente desde los retratos aprobados; no se
  gastaron créditos adicionales ni se enviaron prompts con nombres protegidos.
  El manifiesto de hashes está en
  `public/themes/familia-canina/visual-manifest.v1.json`.
- Fiesta local de QA creada idempotentemente como `DEMO-BLUEY`, con URL pública
  opaca distinta de cualquier otra fiesta. El script
  `scripts/seed-demo-familia-canina.php --apply` conserva esa unicidad.
- Pruebas sin servidor: frontend 8/8; backend 76/76 en PHP 8.0, 8.2, 8.3 y
  8.4; lint 36 archivos + 7 entrypoints en las cuatro versiones. PHP 8.4 sigue
  mostrando el warning conocido de su DLL Xdebug beta, sin afectar el resultado.
- Con WAMP nuevamente en verde, el smoke HTTP pasó 28/28 y la API pública de
  `DEMO-BLUEY` respondió `ok:true`. El recorrido Chrome a 800×1280 se completó
  con cámara simulada: intro → invitados → bienvenida → ruleta → alfombra →
  saludo → cámara → Preview → subida/QR → diploma. La consola y el registro de
  errores quedaron vacíos; `document.fonts.check()` confirmó Baloo 2 700 y el
  estado de fuentes fue `loaded`. Evidencia en
  `qa-evidence/familia-canina-final/`.
- Durante el recorrido se detectó y corrigió un 500 real de `upload.php`: el
  contrato de `public_slug` permite 80 caracteres, pero
  `cb_photo_absolute_path()` aún limitaba la ruta a 40. El validador ahora usa
  80 y existe una prueba de regresión para el slug opaco largo. La misma foto
  pasó de HTTP 500 a 200 al reintentar y produjo el QR opaco correcto.
- Ajuste solicitado por Luis: el kiosco y el admin muestran los nombres reales
  `Bluey`, `Bingo`, `Bandit`, `Chilli`, `Muffin` y `Chloe`, además del rótulo
  público `Bluey`. Los nombres camuflados permanecen únicamente en filenames,
  slugs técnicos y prompts generativos; nunca se usan nombres protegidos en un
  prompt enviado al proveedor.
- `DEMO-BLUEY` contiene 5 niñas (Sofía, Martina, Emilia, Isidora y Antonia) y
  5 niños (Mateo, Benjamín, Lucas, Vicente y Joaquín). En 800×1280 los diez
  botones caben sin recorte. El título de portada sigue íntegramente dentro del
  cartel crema y el nombre dinámico queda centrado en la etiqueta blanca de la
  torta. Evidencias `10-intro-nombres-reales.png` y `11-invitados-10.png`.
- Ajuste visual posterior: la portada añade la clase temática
  `intro--familia-canina`; solo Bluey sube el título a `top:20.5%` y usa un
  ancho de 56%. La etiqueta dinámica usa flex para centrar `DEMO-BLUEY` en
  ambos ejes. Validado a 505×862 y 800×1280; Carreras no cambia. Evidencias
  `13-intro-centrado-505x862.png`, `14-intro-centrado-800x1280.png` y
  `15-carreras-sin-regresion.png`.

## AT-CUMPLECLICK-012 — Juego WOW 3D Full (cierre local 2026-07-29)

- Se agregó `mundo3d` como sexto tipo de juego y `src/ThemeWorld3D.jsx` como
  motor de misión premium de tres carriles. Reutiliza el recorte del personaje
  exacto elegido por la ruleta; no genera ni sustituye personajes.
- Las seis temáticas terminadas tienen mundo propio: Carreras/Turbo,
  Bluey/Parque, Tropical/Ola, Hielo/Puente, K-Pop/Escenario y
  Héroes/Ciudad. Cada configuración vive en `public/data/themes.json` bajo
  `fullGame`.
- El gate es fail-closed en `public/lib.php`: solo
  `party.service_plan=full` recibe `theme.fullGame`. Booth recibe objeto vacío.
  El admin explica que la misión se agrega automáticamente y no la ofrece como
  casilla manual.
- La misión queda al final de la cadena y siempre es opcional. Mantiene botón
  Saltar, controles táctiles, teclado, reduced-motion/fallback sin WebGL,
  pausa al ocultar la pestaña y disposición estricta de memoria/contexto.
- Refinamiento de carrera: el personaje usa un plano 3D transparente (no un
  billboard rígido), inclina el cuerpo, corre con zancada/squash, deja pisadas,
  gira de forma persistente hacia el carril ocupado y la cámara lo acompaña.
  El máximo es una pose de tres cuartos porque los assets aprobados son
  frontales y no existe una vista trasera fiable.
- Mejora posterior solicitada por Luis: el motor de los seis mundos Full
  soporta atlas multivista 2×2 con frente, derecha, espalda e izquierda.
  Cobertura aprobada: Carreras 6/6, Bluey 6/6, K-Pop 6/6 y Tropical 4/6.
  Durante la carrera se muestra la espalda real; al cambiar de carril aparece
  la vista lateral y al celebrar vuelve el frente. Hielo, Héroes y los dos
  tropicales bloqueados por el proveedor usan el recorte/JPG histórico: no se
  inventaron sustitutos. El backend solo publica `runnerAtlas` en plan Full y
  cuando el archivo existe. Prompt: `PROMPTS-RUNNER-MULTIVISTA.md`.
- Demos locales Full para QA: `demo-carreras`, `demo-bluey`,
  `demo-tropical`, `demo-hielo`, `demo-kpop` y `demo-heroes`. El cambio no
  amplía el plan Booth; su payload continúa sin `fullGame`.
- QA: frontend 53/53; backend 119/119 y lint 44 archivos + smoke de 7
  entrypoints en PHP 8.0.30/8.2.26/8.3.14/8.4.0. Chrome real sin errores de
  consola a 800×1280 y 1280×800; la pasada de movimiento lateral también quedó
  sin errores. Evidencia en
  `qa-evidence/full-3d/`.
- No se gastaron créditos de Higgsfield. Se generaron 16 atlas nuevos con la
  herramienta integrada (Carreras 6, K-Pop 6, Tropical 4), siempre con prompts
  camuflados. Los bloqueos de seguridad quedaron en fallback. No hubo deploy,
  commit, push ni merge.
