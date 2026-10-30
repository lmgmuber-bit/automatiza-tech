# Invitación «Casa de Muñecas» con la Gabby animada: plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** una invitación de cumpleaños en un solo enlace: al abrir el sobre, Gabby animada invita a la cumpleañera por su
nombre; debajo, datos, aviso de la media vieja, «Conoce a la cumpleañera», confirmación y enlace de los papás.

**Architecture:** temática nueva `casa-munecas` en `themes.json` con dos banderas nuevas. `introPersonalizado` hace que el
video personalizado de la invitación (output `personalized_video`, privado y servido por token) reemplace el intro genérico.
`guiaSinRegalos` permite la «guía hasta confirmar» sin el modo de regalos `rsvp`. Los medios (voz, video y despedida) se
hacen fuera del repo y el video con el nombre nunca entra a git.

**Tech Stack:** PHP 8 (invitacion.php), JS de la invitación, `node:test`, pruebas HTTP en PHP con base temporal, ffmpeg local,
ElevenLabs `eleven_v3` + `scribe_v1`, Higgsfield API (`hf_api.py --cuenta n8n`) con MCP de respaldo.

**Spec:** `Docs/superpowers/specs/2026-10-29-cumpleclick-cumple-casa-munecas-design.md`

**Ruta:** C2. Lo ejecuta Confeti (Opus 5.5) en esta sesión; la revisión B la hace Codex.
**Cargos:**
- Lienzo: tareas 1 y 2.
- Plano: tareas 3 y 4.
- Forja: tarea 5.
- Centinela: pruebas de las tareas 1 y 5.
- Vigía: privacidad de la tarea 4 y regla de la actriz.
- Balance: gastos de las tareas 3 y 4.
- Contralor: gates de la tarea 6.

## Global Constraints

- Repo **público**: ningún nombre, dirección, teléfono, slug ni enlace de la familia en código, pruebas, commits ni docs.
  Barrer el diff antes de cada push con `grep -iE` de los datos de `datos-evento.md`.
- Datos privados en `C:/Users/luis_/Videos/cumpleclick-reels/material/cumple-gabby-2026-11-29/`.
- Gabby **solo de dibujo**, fiel a la serie y nunca fotorrealista. No se clona la voz de la actriz ni la del doblaje.
- Slug `casa-munecas`, nombre público «Casa de Muñecas». `themes.json` es CRLF: insertar con `\r\n`.
- Sin lista de regalos (el modo de regalos queda apagado).
- Voces: `eleven_v3`, niveladas a −21,8 LUFS y verificadas con `scribe_v1`. La música va a −18 LUFS con ducking al 45 %
  bajo la voz.
- Higgsfield:
  - Primero la API, `--cuenta n8n` (US$8,60 al 29-oct).
  - Si la API rechaza por saldo, se le avisa a Luis para que recargue.
  - El MCP se usa **solo para el lipsync**, si la API no lo hace (autorizado por Luis).
  - Costo presentado y ok de Luis antes de cada generación.
  - Cada gasto se registra con Balance como cortesía.
- PROD solo con go de Luis: cotejar, respaldar en `~/respaldos/`, subir, cotejar sha256 y verificar desde afuera.
- Nada de confirmaciones de prueba después de mandar el enlace. La de antes se borra.

## Review Focus

1. Una invitación de **otra temática** con `personalized_video` (los adultos, por ejemplo) tiene que verse igual que hoy.
   Prueba en la tarea 1.
2. Temática con `introPersonalizado` y la invitación **todavía sin video**: cae al intro genérico de la temática si existe,
   o al sobre de siempre. Nunca un `<video src="">`. Prueba en la tarea 1.
3. El video con el nombre se sirve por token, **nunca desde `themes/`**. Prueba en la tarea 1 y verificación en la tarea 5.
4. **Una sola voz:** la guía no habla encima del intro ni de la despedida. Prueba en la tarea 1, sobre el `algoSonando`
   existente, y verificación en el navegador en la tarea 5.
5. Celular con datos: el video personalizado pesa **10 MB o menos**, a 720×1280. Se verifica en la tarea 4.

---

### Task 1: Intro personalizado y guía sin regalos (código)

**Files:**
- Modify: `CumpleBooth/public/invitacion.php`:
  - guía, líneas 273 a 281;
  - intro, líneas 528 a 545;
  - marco de la portada, líneas 1659 a 1667.
- Create: `CumpleBooth/tests/frontend/cumpleCasaMunecas.test.mjs`
- Create: `CumpleBooth/tests/backend/invitacion-intro-personalizado-http.php`. Se arma con el andamio de
  `tests/backend/rsvp-regalo-http.php`: base temporal, `php -S` y `pedir()`.

**Interfaces:**
- Produces:
  - Bandera `introPersonalizado` (bool) de la temática en `themes.json`.
  - Bandera `guiaSinRegalos` (bool) de la temática en `themes.json`.
  - En `invitacion.php`, la variable `$introPersonalizado` vale `!empty(<bandera>) && $hasVideo`.

- [ ] **Step 1: Pruebas que fallan**
  - En `cumpleCasaMunecas.test.mjs`:
    - `test('introPersonalizado: el video de la invitación reemplaza el intro de la temática')`. Assert:
      `invitacion.php` contiene `introPersonalizado` y, en el bloque del intro, asigna `$themeIntroUrl = (string) $videoUrl`
      y `$themeIntroPosterUrl = $imageUrl`.
    - `test('con introPersonalizado la portada no repite el video')`. Assert: la condición de `data-inv-video-final` incluye
      `!$introPersonalizado`.
    - `test('la guía también carga con guiaSinRegalos')`. Assert: el regex
      `/\(\$modoRegalos === 'rsvp' \|\| \$guiaSinRegalos\)[\s\S]{0,300}guia-confirmar/` encuentra coincidencia.
  - En `invitacion-intro-personalizado-http.php`, cuatro casos con `h_check` sobre el HTML de `invitacion.php?t=`:
    - (a) Temática con la bandera y un output `personalized_video` aprobado: `data-inv-theme-intro-video` con `src` igual a
      `cb_invitation_download_url($token, 'video')`, y sin `data-inv-video-final`.
    - (b) Misma temática sin video: el `src` del intro empieza con `themes/casa-munecas/` si existe el intro genérico, o no
      hay `data-inv-theme-intro`. En ningún caso `src=''`.
    - (c) Temática sin la bandera (`baby-osito`) con video: HTML igual que hoy, con `data-inv-video-final` presente.
    - (d) El `src` del intro personalizado no empieza con `themes/`.
  - El output se registra como lo hace `cc-crear-baby-shower.php` (privado): `cb_invitation_storage_key()` más
    `cb_invitation_file_path()`, con `status` aprobado.
- [ ] **Step 2:** `node --test tests/frontend/cumpleCasaMunecas.test.mjs` y `php tests/backend/invitacion-intro-personalizado-http.php`.
  Expected: FALLAN.
- [ ] **Step 3: Implementación**
  - Leer las dos banderas desde `$themesData['themes'][$themeSlug]`.
  - Guía: la condición pasa a `($modoRegalos === 'rsvp' || $guiaSinRegalos)`.
  - Intro: después del bloque del intro genérico, `if ($introPersonalizado) { $themeIntroUrl = (string) $videoUrl;
    $themeIntroPosterUrl = $imageUrl; }`.
  - Portada: el `<video data-inv-video-final>` se dibuja con `$hasVideo && !$introPersonalizado`.
  - «Descargar video» y compartir siguen como están: la familia sí puede bajar el video.
- [ ] **Step 4:** las dos pruebas nuevas, más `node --test tests/frontend/` completo y `php tests/backend/rsvp-regalo-http.php`.
  Expected: todo en verde. La prueba de la guía del baby shower (`babyShowerRegaloRsvp.test.mjs:149`) sigue verde.
- [ ] **Step 5:** commit `feat(cumpleclick): intro personalizado por invitación y guía sin regalos`.

### Task 2: Temática `casa-munecas` (datos y fondos)

**Files:**
- Modify: `CumpleBooth/public/data/themes.json` (CRLF). La entrada `casa-munecas` copia la forma de `baby-osito`:
  - `nombre` «Casa de Muñecas», `franquicia` «La casa de muñecas de Gabby», `publico` «mixto»;
  - `colors` sacados de la referencia (rosado, morado y turquesa, con `ink` legible sobre blanco), `confetti` y `frameBox`;
  - `vozPropia: true`, `introPersonalizado: true` y `guiaSinRegalos: true`.
- Modify: `CumpleBooth/public/data/event-profile-presets.json`: la temática en `themes`, con el mismo `text_area` que el
  `frameBox`.
- Modify: `CumpleBooth/public/invitacion.php`: `$playlistOrdersByTheme['casa-munecas']`, solo
  `'despedida-casa-munecas.mp4' => '¡Te esperamos!'`.
- Modify: `CumpleBooth/public/assets/invitation.css`. **Aviso de la media:** va en el campo `message` de la invitación,
  que ya existe y se dibuja como `.inv-message` en `invitacion.php:1737`, así que no hace falta migración.
  - Con `.inv-body[data-theme="casa-munecas"] .inv-message`, la cita pasa a ser una **tarjeta destacada**: borde y fondo
    de la paleta, ícono 🧦 y texto grande.
  - Se escribe al crear la invitación (tarea 5): «Importante: cada invitado trae una media vieja. En el cumpleaños
    haremos un títere de mano».
  - El video y la voz de la guía lo repiten.
- Create: `CumpleBooth/public/themes/casa-munecas/` con `fondo-banner.jpg` y `fondo-sala.jpg` (Soul 2 por la API, sin
  personajes, cuarto de casa de muñecas rosado; 2 a 6 imágenes a US$0,0032 cada una).
- Test: `CumpleBooth/tests/frontend/cumpleCasaMunecas.test.mjs`

- [ ] **Step 1:** `test('casa-munecas existe en themes.json y en el preset con las tres banderas')`.
  - Assert: la entrada existe y `vozPropia`, `introPersonalizado` y `guiaSinRegalos` son `true`.
  - Assert: la temática está en `event-profile-presets.json`.
  - Assert: `themes.json` no tiene un `\n` suelto (cada salto de línea es `\r\n`).
  - Además, `test('casa-munecas destaca el mensaje como aviso')`. Assert: `invitation.css` contiene
    `.inv-body[data-theme="casa-munecas"] .inv-message`.
- [ ] **Step 2:** correr la prueba. Expected: FALLA.
- [ ] **Step 3:** agregar las entradas y la playlist. Generar los fondos con el costo presentado (unos US$0,02) y bajarlos
  de inmediato.
- [ ] **Step 4:** correr las pruebas. Expected: PASA. Abrir una invitación local de la temática a 375 px y mirar los
  colores.
- [ ] **Step 5:** commit `feat(cumpleclick): temática Casa de Muñecas`.

### Task 3: Voz de Gabby (muestras, elección y líneas)

**Files (privados, fuera del repo):** `material/cumple-gabby-2026-11-29/voz/`.
**Files (repo, genéricos y sin nombre):** `CumpleBooth/public/themes/casa-munecas/narracion-video/`:
- `gracias-confirmar.mp3`
- `guia-confirmar.mp3`
- `narracion-final.mp3`

- [ ] **Step 1: Muestras.**
  - Tres muestras de unos 120 caracteres con la misma frase de saludo de Gabby (sin nombre de la familia), con
    `eleven_v3` en tono de niña alegre y acento latino neutro:
    - Matilda (`XrExE9yKIg1WjnnlVkGX`);
    - otra voz ya disponible en la cuenta, por ejemplo Jessica;
    - una tercera de la misma cuenta.
  - Nivelar las tres a −21,8 LUFS y mandárselas a Luis.
  - Si ninguna lo convence: Luis agrega una voz de la biblioteca a su cuenta y pasa el id.
- [ ] **Step 2: Gate.** Luis elige la voz. Anotarla en `datos-evento.md`.
- [ ] **Step 3: Guion.**
  - El guion de la invitación va en `voz/guion.md`, privado. Dura unos 20 a 25 segundos y lleva el nombre, «cumple 4
    años», el día, la hora, «en el salón del condominio» y el pedido de la media vieja para el títere.
  - Las tres líneas genéricas no llevan ningún nombre:
    - gracias: «¡Gracias por confirmar! Te esperamos»;
    - guía: «Baja hasta el final, toca Confirma tu asistencia, y no olvides tu media vieja»;
    - final: el cierre de la invitación.
  - **Gate:** Luis aprueba los textos.
- [ ] **Step 4:** grabar, nivelar a −21,8 LUFS y transcribir con `scribe_v1`. El nombre y la fecha tienen que salir bien
  escritos; si no, regrabar cambiando la ortografía de la frase. Registrar los caracteres para Balance.
- [ ] **Step 5:** commit de los tres MP3 genéricos: `feat(cumpleclick): voces de Casa de Muñecas`.

### Task 4: Video de Gabby (invitación personal y despedida genérica)

**Files (privados):** `material/cumple-gabby-2026-11-29/video/`, con los clips, la mezcla, `intro-fiesta.mp4`,
`intro-fiesta-poster.jpg` y `reporte.md` (request_id y costo).
**Files (repo):** `CumpleBooth/public/themes/casa-munecas/despedida-casa-munecas.mp4`, de 5 a 10 s, sin nombre.

- [ ] **Step 1: Averiguar el lipsync por la API sin gastar.**
  - Buscar en la documentación o en la especificación OpenAPI de `api.higgsfield.ai` si `alibaba/wan-3.0/reference-to-video`
    o `image-to-video` aceptan audio de referencia.
  - Si no se puede confirmar, la ruta del lipsync es el MCP.
  - Anotar la fuente en `reporte.md`.
- [ ] **Step 2: Presupuesto a Luis.**
  - Cálculo: segundos × US$0,05 (Wan 3.0 por la API), más un 50 % de reintentos.
  - Si va por el MCP: créditos medidos con `get_cost`.
  - **Gate:** ok de Luis.
- [ ] **Step 3: Generar.**
  - Gabby de dibujo con Pandy Paws en su casa de muñecas, 9:16, hablando y saludando a cámara.
  - Referencia: `referencia-gabby-captura.webp`. Prompt sin texto en pantalla.
  - Primero un clip de prueba de 5 s. Después los clips que falten hasta cubrir la voz.
  - Dos rechazos (`nsfw` o de calidad) = cambiar de modelo o de fuente.
  - `bajar` de inmediato (el CDN guarda 7 días).
  - Despedida genérica: Gabby y los gatos diciendo adiós, sin voz con nombre.
- [ ] **Step 4: Componer con ffmpeg.**
  - Clips, voz de la tarea 3 y música de Luis (−18 LUFS, ducking al 45 %).
  - La misma música es el fondo de la página (`themes/casa-munecas/musica-fondo.mp3`). Si es una canción con derechos, va
    **solo al servidor**, con su línea en `.gitignore`, como la música por fiesta del álbum; no se commitea.
  - Salida 720×1280, H.264 + AAC 48 kHz, **10 MB o menos**, más un póster JPG del cuadro de Gabby.
  - Verificar con `ffprobe` la duración, el tamaño y el `sample_rate`.
- [ ] **Step 5:**
  - Registrar el gasto real con Balance (`registrar.py`, cortesía).
  - Commit solo de `despedida-casa-munecas.mp4`: `feat(cumpleclick): despedida de Casa de Muñecas`.
  - Barrer el diff: `intro-fiesta.mp4` **no** se commitea.

### Task 5: PROD (fiesta, invitación y despliegue)

**Files (privados):** copias adaptadas de `herramientas/prod/baby-shower/`, en `herramientas/prod/cumple-gabby/`:
`crear.py`, `cc-crear-cumple.php`, `eximir-activar.py`, `desplegar.py` y `pruebas-limpiar.py`.

- [ ] **Step 1: Gate.** Go de Luis para desplegar. Antes, `estado-prod.py` coteja `main` contra PROD en los archivos que se
  tocan.
- [ ] **Step 2: Desplegar.**
  - Respaldo en `~/respaldos/` de cada archivo que se sobrescribe.
  - Subir `invitacion.php`, `themes.json`, `event-profile-presets.json` y `themes/casa-munecas/` completo, con escritura
    atómica (`.subiendo` y luego `mv`) y cotejo por sha256.
- [ ] **Step 3: Crear la fiesta en PROD.**
  - Cumpleaños infantil, plan `full`, fecha, hora y dirección de `datos-evento.md`, y lugar «Salón del condominio».
  - Exención de Términos y activación (cortesía, aprobada por Luis).
  - `message` de la invitación: el aviso de la media, con el texto de la tarea 2.
  - La invitación con el output `personalized_video` aprobado (`intro-fiesta.mp4` y su póster) y **sin**
    `personalized_narration_intro`.
  - Perfil de la cumpleañera con los gustos, las tallas y los zapatos, cuando lleguen de la mamá.
  - Guardar los enlaces en `enlaces-prod.json`, que es privado.
- [ ] **Step 4: Verificar desde afuera, en un celular de 375 px.**
  - El sobre, el intro de Gabby con sonido y la portada sin video repetido.
  - Los datos, el mapa, el aviso de la media, el perfil y la despedida.
  - La guía después de 10 s quieto, con una sola voz a la vez.
  - El `src` del video empieza con la URL de descarga por token.
  - Una confirmación de prueba, el «gracias» que suena y el enlace de los papás que la muestra. Después,
    `pruebas-limpiar.py --borrar` y volver a leer para confirmar que quedó en cero.
- [ ] **Step 5:** mandarle a Luis el texto de WhatsApp para los invitados y el de los papás, con sus enlaces.

### Task 6: Cierre

- [ ] **Step 1:** `CumpleBooth/docs/FTP-MANIFEST.md`, sección «DESPLEGADO <fecha> Casa de Muñecas»: archivos, respaldos,
  rollback y lo no probado (iPhone real), **sin datos de la familia**.
- [ ] **Step 2:** barrer el diff, hacer push y abrir el PR. Pedir la revisión B a Codex sobre ese commit.
- [ ] **Step 3: Gate.** Merge solo con el ok de Luis; `main` igual a PROD.
- [ ] **Step 4:** actualizar la memoria (`project_confeti_identidad.md` o una nota propia) y hacer el aseo de los
  temporales.
