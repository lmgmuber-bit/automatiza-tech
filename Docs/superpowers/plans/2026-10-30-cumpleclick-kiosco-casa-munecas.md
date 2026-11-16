# Kiosco Casa de Muñecas — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** dejar la temática `casa-munecas` completa en el kiosco (tabla A de `CumpleBooth/docs/TEMATICA-COMPLETA.md`), como Spidey, sin recompilar el kiosco.

**Architecture:** solo datos y archivos: `CumpleBooth/public/data/themes.json` (entrada `casa-munecas`) y `CumpleBooth/public/themes/casa-munecas/`. Los assets se producen con scripts privados (fuera del repo) en `C:/Users/luis_/Videos/cumpleclick-reels/material/cumple-gabby-2026-11-29/kiosco/`: imágenes con Qwen Image 3 edit y saludos con Wan 3.0 por la API de Higgsfield, recortes y montaje con PIL/ffmpeg, voces con ElevenLabs.

**Tech Stack:** Python 3 + PIL + numpy, ffmpeg, `hf_api.py` (Higgsfield API, `--cuenta n8n`), ElevenLabs (TTS `eleven_v3`, STT `scribe_v1`), pruebas `node --test` y PHP CLI.

**Spec:** `Docs/superpowers/specs/2026-10-30-cumpleclick-kiosco-casa-munecas-design.md`

## Global Constraints

- Seis personajes, `img` = `gabby.jpg`, `pandy-paws.jpg`, `cakey.jpg`, `mercat.jpg`, `kitty-fairy.jpg`, `dj-catnip.jpg`; nombres en el producto: Gabby, Pandy Paws, Cakey, MerCat, Kitty Fairy, DJ Catnip.
- Medidas (como Hielo/Spidey): retrato `.jpg` 1080×1920; `-cut.png` RGBA 1080×1920; `puzzle-*.jpg` 900×900; `roulette/roulette-background-v1.png` 1080×1080; `fondo-sala.jpg` 1080×1920; saludos 720×1280, 24 fps, H.264 + AAC, 5–7 s, faststart.
- Sin texto en ninguna imagen generada; sin grupos en una sola generación (componer recortes si hace falta).
- Voz: la diseñada (`material/.../voz/diseno/voz-elegida.json`), `eleven_v3`, estabilidad 0.5, −21,8 LUFS, verificada con scribe_v1. Nunca clonar voces.
- Sello CumpleClick (`herramientas/sello_cumpleclick.py`) en saludos y bienvenida; no en retratos, recortes, rompecabezas ni ruleta.
- Nada de datos de la familia en el repo (barrer el diff antes de cada commit con el patrón de datos de la familia que está en el material privado).
- Presupuesto aprobado: ~US$2 de la API. Mostrar el costo antes de cada generación y anotar cada gasto (job id) para Balance. Las claves se leen por etiqueta y nunca se imprimen.
- No se toca `src/` ni el bundle del kiosco.

## Review Focus

- La invitación de la fiesta en PROD no cambia al agregar personajes → Task 6 corre `invitacion-intro-personalizado-http.php` y la validación externa antes y después.
- El CDN entrega PNG reencodados/achicados a Android → los recortes se dibujan con medidas anotadas; Task 3 deja `-cut.png` a 1080×1920 como Hielo (sin depender de `naturalWidth`).
- Un recorte con halo del color de fondo se ve sucio sobre la foto → Task 3 verifica que el borde no tenga píxeles del color clave.
- Un saludo que dice otra cosa o se corta → Task 4/5 transcriben cada saludo final con scribe_v1.
- El marco del fondo de la sala chico deja la cara chica → Task 3 exige cuadrado de foto ≥ 550 px.

---

### Task 1: Pruebas que exigen la temática completa (rojo)

**Files:**
- Modify: `CumpleBooth/tests/frontend/themeFlow.test.mjs:554` (agregar `'casa-munecas'` a `COMPLETAS`)
- Modify: `CumpleBooth/tests/frontend/cumpleCasaMunecas.test.mjs:74-82`

- [ ] **Step 1:** Agregar `'casa-munecas'` a `COMPLETAS`.
- [ ] **Step 2:** Reemplazar la aserción `assert.equal(caja.y, area.y)` por una prueba aparte `'el marco de la foto del kiosco es grande y cabe en el lienzo'`: `frameBox` con `x,y ≥ 0`, `x+w ≤ 1`, `y+h ≤ 1` y `Math.min(w*1080, h*1920) * (1 - 2*0.085) >= 550` (el inset de `src/frameGeometry.js`). La prueba de la lámina conserva `area.y` entre 0,32 y 0,34 y `area.y + area.h <= 0.62`.
- [ ] **Step 3:** `node --test tests/frontend/themeFlow.test.mjs tests/frontend/cumpleCasaMunecas.test.mjs` → FALLA (faltan personajes y archivos; el marco actual mide menos de 550).
- [ ] **Step 4:** Commit `test(cumpleclick): exigir el kiosco completo de Casa de Muñecas`.

### Task 2: Retratos y fondo de la sala (imágenes) — GATE de Luis

**Files:**
- Create: `material/cumple-gabby-2026-11-29/kiosco/generar-imagenes.py` (privado)
- Salidas: `kiosco/img/<slug>-v<N>.png`, `kiosco/img/fondo-sala-v<N>.png`, `kiosco/gastos.json`

**Interfaces:**
- Produces: un PNG aprobado por personaje sobre fondo liso verde `#00B140` (cuerpo entero, centrado, de frente, sin texto) y un `fondo-sala` aprobado; `gastos.json` con `{job_id, modelo, usd}` por generación.

- [ ] **Step 1:** Subir como referencia `hf/gabby-flux-1.png` y `hf/gabby-flux-2.png` (y la referencia de Luis) con `hf_api.py`; generar con `alibaba/qwen-image-3/edit` (US$0,04) un personaje por imagen, 9:16, fondo verde liso, estilo idéntico a la referencia. Kitty Fairy (gatita hada rosada con alas) y DJ Catnip (gatito lila con audífonos) se describen por texto. Fondo de la sala: la sala de juegos rosada de la referencia con un marco grande, rectangular, vacío y centrado (ocupa ~60 % del ancho), sin personajes ni texto.
- [ ] **Step 2:** Antes de correr, mostrar a Luis el costo (7 × US$0,04 = US$0,28 + reintentos). Tras 2 rechazos de una misma referencia, cambiar de referencia, no reescribir el prompt.
- [ ] **Step 3:** Mandar a Luis la hoja con los 6 retratos y el fondo. **No seguir a la Task 5 sin su aprobación.**

### Task 3: Recortes, rompecabezas, ruleta y marco (local, sin costo)

**Files:**
- Create: `kiosco/armar-assets.py` (privado)
- Create: `CumpleBooth/public/themes/casa-munecas/{gabby,pandy-paws,cakey,mercat,kitty-fairy,dj-catnip}.jpg`, `*-cut.png`, `puzzle-*.jpg`, `fondo-sala.jpg`, `roulette/roulette-background-v1.png`

- [ ] **Step 1:** Recorte por clave de color (verde) con borde suavizado y quitando el halo verde (despill); retrato `.jpg` = el personaje sobre `fondo-banner.jpg`; `-cut.png` 1080×1920 RGBA; `puzzle` 900×900 recortado del retrato centrado en la cara; ruleta 1080×1080 desde `fondo-banner.jpg`.
- [ ] **Step 2:** Verificar: en cada `-cut.png`, ningún píxel con alfa > 0 del borde tiene G > R+40 y G > B+40 (sin halo verde); medir el hueco del marco en `fondo-sala.jpg` y anotar el `frameBox` que da un cuadrado ≥ 550 px.
- [ ] **Step 3:** Commit de los assets (`feat(cumpleclick): imágenes del kiosco de Casa de Muñecas`).

### Task 4: Voces y saludo de Gabby (sin costo de video)

**Files:**
- Create: `kiosco/grabar-saludos.py`, `kiosco/componer-saludos.py` (privados)
- Create: `CumpleBooth/public/themes/casa-munecas/saludo-gabby.mp4`

- [ ] **Step 1:** Grabar las 5 frases del spec con la voz diseñada (≈300 caracteres), niveladas y transcritas; anotar los caracteres.
- [ ] **Step 2:** `saludo-gabby.mp4`: `video/parte-a-voz2.mp4` de 0 a ~4,7 s (la pausa va de 4,28 a 5,08 s) con el audio limpio `voz/parte-a.mp3` del mismo tramo, música de fondo baja, sello, 720×1280.
- [ ] **Step 3:** Transcribir el saludo final: debe decir «Hola, hola, soy Gabby, bienvenido a mi casa de muñecas» y nada más.

### Task 5: Saludos de los gatitos (Wan 3.0) — después del GATE

**Files:**
- Create: `kiosco/generar-saludos.py` (privado); `CumpleBooth/public/themes/casa-munecas/saludo-{pandy-paws,cakey,mercat,kitty-fairy,dj-catnip}.mp4`

- [ ] **Step 1:** Costo a Luis antes: 5 × 5 s × US$0,05 = US$1,25. Wan 3.0 imagen-a-video (`alibaba/wan-3.0/image-to-video`), 5 s, 9:16, desde el retrato aprobado (personaje sobre su fondo): saluda con la mano, alegre, cámara fija, sin texto.
- [ ] **Step 2:** Componer cada saludo: video + su frase + música baja + sello, 720×1280, 24 fps, ≤ 7 s; transcribir y revisar cuadros (sin letras estampadas).
- [ ] **Step 3:** Commit de los 6 saludos (`feat(cumpleclick): saludos del kiosco de Casa de Muñecas`).

### Task 6: themes.json y pruebas en verde

**Files:**
- Modify: `CumpleBooth/public/data/themes.json` (entrada `casa-munecas`; CRLF, editar sin cambiar los fines de línea)

- [ ] **Step 1:** `personajes` (6): Gabby estrella `['ritmo' (image fondo-banner.jpg, lanes 4, seconds 15), 'fichas' (puzzle-gabby.jpg, 3×3), 'copos']`; los demás `['fichas', 'copos']` con emojis por personaje (Pandy 🐼🎀⭐💖, Cakey 🧁🍓🍰⭐, MerCat 🫧🐚🐟💎, Kitty Fairy 🌸🦋✨🌷, DJ Catnip 🎵🎶🎧⭐, Gabby 🎀🐱💖⭐). `fullGame`: `{kind: 'concierto3d', stage: 'neon-arena', label: '¡El Show de la Casa de Muñecas 3D!', seconds: 24, image: 'fondo-banner.jpg'}`. `frameBox` medido en la Task 3.
- [ ] **Step 2:** `node --test tests/frontend/*.test.mjs` → las de Casa de Muñecas y `themeFlow` en verde (las 5 que fallan por dependencias sin instalar se informan aparte). `php tests/backend/invitacion-intro-personalizado-http.php` → OK.
- [ ] **Step 3:** Commit `feat(cumpleclick): kiosco de Casa de Muñecas en themes.json`.

### Task 7: Despliegue y verificación

**Files:**
- Create: `herramientas/prod/cumple-gabby/desplegar-kiosco.py` (privado)
- Modify: `CumpleBooth/docs/FTP-MANIFEST.md` (sección del despliegue, sin datos de la familia)

- [ ] **Step 1:** Script con el método de esta rama: cotejar `data/themes.json` contra lo desplegado, respaldar, subir la carpeta de la temática (archivos nuevos) y `themes.json`, validar JSON en el servidor, instalar con `mv`, verificar desde afuera (`themes.json` trae los 6 personajes; cada archivo nuevo responde 200; la invitación de la fiesta sigue igual).
- [ ] **Step 2:** Luis corre el script; validar desde afuera; registrar gastos para Balance.
