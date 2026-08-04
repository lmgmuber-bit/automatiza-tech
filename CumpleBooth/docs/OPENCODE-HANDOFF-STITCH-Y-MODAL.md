# OPENCODE — Traspaso: temática Stitch + modal de fotos en admin

**De:** Claude (diseño/dev de marca y temática Bluey) · **Para:** OpenCode
**Fecha:** 2026-07-20 · **Aprobado por:** Luis
**Ejecutor anterior de este proyecto:** Codex (Gate A, pendiente su revisión independiente — no tocar ese ticket)

---

## 0. Qué modelo usar

Este trabajo mezcla dos cosas muy distintas y ambas exigen precisión, no velocidad:

1. **Generación de assets con Higgsfield** (imágenes/video con multi-referencia, prompts largos en inglés técnico, decisiones de composición) — necesita razonamiento fuerte para mantener consistencia de personaje entre 6-8 generaciones encadenadas.
2. **Código quirúrgico en PHP + React** (validación de nombres de archivo, estado de React, CSS responsive con `cqw`) siguiendo patrones ya establecidos sin romper nada — necesita seguir instrucciones exactas, no improvisar arquitectura nueva.

**Recomendación confirmada (viendo el picker real de OpenCode Go):**
- **Primario: DeepSeek V4 Pro** — mejor relación razonamiento/consistencia para prompts largos multi-referencia + código quirúrgico en `lib.php`.
- **Backup: Kimi K2.7 Code** — si DeepSeek V4 Pro no está disponible o falla algo, es la segunda opción, especializada en código.
- **Evitar para esta tarea: DeepSeek V4 Flash Free** y cualquier variante "free/ligera" — la parte de Higgsfield requiere prompts largos con multi-imagen-referencia y control fino de proporciones (ver Incidente §4), y la parte de código toca `lib.php` que valida nombres de archivo por seguridad; un modelo débil puede romper esa validación sin darse cuenta.
- Kimi K3 / Kimi K2.6 / GLM-5.x / Qwen3.7: no probados aún en este proyecto — si Luis quiere evaluarlos, que sea en una tarea chica y aislada (ver §8), no en Stitch directamente.

---

## 0.0 Regla nueva (Luis, 2026-07-22/25) — los saludos individuales HABLAN, con voz tierna en español latino

En CADA temática, el video `saludo-<personaje>.mp4` debe tener AUDIO con el personaje
diciendo: **"¡Ahora nos tomaremos una foto!"**

**NO uses las voces preset de `seed_audio` de Higgsfield**: son angloparlantes, pronuncian
mal el español ("fodos" en vez de "fotos") y suenan frías. Luis las rechazó explícitamente.

**Método correcto (gratis, 0 créditos):** reutiliza las pistas de voz de Bluey, que ya son
las aprobadas. Hay 6 voces únicas en `public/themes/familia-canina/saludo-*.mp4`
(azulita, chispa, papa-marino, mama-coral, chloe, muffin — nube duplica a muffin y
manchita duplica a chloe). El doc `OPENCODE-CONTINUE-FAMILIA-CANINA-PERSONAJES-V1.md` §7
punto 4 autoriza explícitamente reutilizar pistas entre temáticas.

```bash
# 1) extraer la voz de Bluey
ffmpeg -i saludo-azulita.mp4 -vn -acodec pcm_s16le -ar 24000 -ac 1 voz.wav
# 2) muxearla sobre el video MUDO del personaje nuevo
ffmpeg -i saludo-nuevo-raw.mp4 -i voz.wav -c:v copy \
  -filter_complex "[1:a]apad[a]" -map 0:v -map "[a]" \
  -c:a aac -b:a 128k -ar 48000 -movflags +faststart -shortest saludo-nuevo.mp4
```

**Guarda SIEMPRE el `-raw.mp4` mudo** antes de muxear: permite rehacer el audio sin
regenerar el video (que sí cuesta créditos). Regla permanente para Codex, OpenCode y Claude.

## 0.1 Regla nueva — TODO prompt que funcione se guarda en BD (obligatorio para Codex y OpenCode)

Ya no basta con dejar el prompt que funcionó solo en un `.md`. El admin (`public/admin/index.php`) ahora tiene un sistema de prompts versionado en BD:

- Tabla `cc_theme_prompts` = versión actual por `(theme_slug, asset_key)`.
- Tabla `cc_theme_prompt_history` = historial append-only, se llena solo cada vez que se llama `cb_save_theme_prompt()` — nunca la edites a mano.
- Función a usar: `cb_save_theme_prompt(string $themeSlug, array $themeData, string $assetKey, string $promptText): array` en `public/lib.php`.
- El admin ya muestra, por cada asset visual de cada temática, un textarea con el prompt actual + un `<details>` con el historial de versiones anteriores ("Usar esta versión" para restaurar sin autoguardar).

**Regla dura: cuando generes o regeneres un asset visual (imagen/PNG) y el prompt te dé un resultado aprobado, guárdalo en la BD con `cb_save_theme_prompt()` en el mismo momento** (vía un script PHP puntual tipo `php -r "..."`, o agregando al importer `scripts/import-theme-prompts.php` si prefieres mantenerlo también documentado en Markdown). No lo dejes solo en un doc de traspaso — el doc se pierde de vista, la BD es la fuente de verdad que ve Luis en el admin.

Esto aplica a Stitch (Tarea 1) y a cualquier regeneración futura de Bluey/Carreras/otras temáticas.

---

## 1. Contexto del proyecto (leer esto primero)

- **Proyecto:** `C:\wamp64\www\automatiza-tech\CumpleBooth` — kiosco de fotos para cumpleaños infantiles, marca comercial **CumpleClick**.
- **Stack:** React+Vite (`src/`) + PHP backend sin framework (`public/`). Deploy = contenido de `dist/` (build). `public/` y `dist/` deben quedar en paridad byte-a-byte — SIEMPRE correr `scripts/check-dist-parity.php` después de `npm run build`.
- **Multi-fiesta:** una sola app, cada fiesta vive en `?p=<slug>`, config viene de `api.php?p=` en runtime. Temáticas en `public/data/themes.json` + assets en `public/themes/<slug>/`.
- **Identidad de marca CumpleClick** ("El globo dulce"): manual completo en `design/MANUAL-DE-MARCA.md`, tokens en `design/tokens.css`, logo en `public/brand/cumpleclick-mark.svg`. Ya aplicada al admin (`public/admin/_style.css.php`) y a partes del kiosco. **No la cambies**, es la fuente de verdad.
- **Higgsfield MCP conectado**: generación de imagen/video con créditos reales de la cuenta. **Preflight `get_cost:true` SIEMPRE antes de generar.** Saldo al cierre de esta sesión: revisa con `list_workspaces` (última lectura ~120 créditos).

### 1.1 Reglas duras (no negociables, todo el proyecto)

- **Regla de oro del servicio**: las imágenes de temática NUNCA llevan texto ni nombres de personas. Todo texto sale del código (`CONFIG.nombre`, etc.), nunca horneado en el arte.
- Personajes con copyright (Disney, Bluey/BBC, etc.): en los prompts de Higgsfield, describir **por rasgos físicos sin nombrar la franquicia** (fórmula probada, ver `docs/PROMPTS-TEMATICAS.md`).
- `lib.php` valida SIEMPRE nombres de archivo con regex + `is_file()` antes de publicarlos por la API — nunca aceptes rutas con `/` ni `..`. Sigue el patrón exacto que ya existe para `photoSession.teaser`/`teaserVideo` (líneas ~594-630 de `public/lib.php`).
- Al deployar: NUNCA sobrescribir `data/`, `themes/` ni `fotos/` del servidor real (solo aplica si llegan a deploy — **no está autorizado deploy en esta tarea**, ver §5).
- No commit/push/merge sin instrucción explícita de Luis en el momento.

---

## 2. Cómo se construyó la temática Bluey (línea de diseño a seguir para Stitch)

Esta es la referencia completa — Stitch debe seguir el MISMO patrón, no inventar uno nuevo.

### 2.1 Estructura de una temática nueva

En `public/data/themes.json`, cada temática tiene:
```json
{
  "nombre": "...", "franquicia": "...", "diploma": "...",
  "colors": { ... }, "confetti": [...],
  "personajes": [{ "name": "...", "emoji": "...", "img": "archivo.jpg" }, ...],
  "photoSession": { ... }   // OPCIONAL, ver 2.4
}
```

Assets físicos en `public/themes/<slug>/`:
- `<personaje>.jpg` — foto del personaje solo (fondo neutro), usada para saludo/referencia.
- `<personaje>-cut.png` — recorte transparente del personaje (fondo removido), usado en la foto compuesta final y en la transición 3D.
- `fondo-banner.jpg` — fondo de la portada (intro), 1080×1920, con un "cartel" central vacío para el título.
- `fondo-sala.jpg` — fondo del marco dorado donde se compone la foto final del invitado.
- `musica-fondo.mp3` — música de fondo del kiosco para esa temática.
- `roulette/roulette-background-v1.png` — fondo de la pantalla de ruleta.
- `grupo-personajes.png` — imagen de grupo, watermark de baja opacidad en la pantalla de "elegir nombre" y en la ruleta.
- `saludo-<personaje>.mp4` — video real de saludo por personaje (opcional; si falta, cae a imagen fija automáticamente, sin romper nada).

### 2.2 Cómo generé los assets de Bluey (referencia exacta de prompts/flujo)

1. **Personajes individuales**: generé cada personaje con Higgsfield (`nano_banana_pro`) en pose neutra de pie, estilo 3D "high-end collectible vinyl toy-style", fondo de parque/fiesta. Un personaje por imagen, para poder recortarlo limpio después.
2. **Recorte transparente**: subí la foto a Higgsfield con `remove_background` (params `media_type: "image"`), o alternativamente usé chroma-key local con ffmpeg cuando generé el personaje sobre fondo magenta plano (`colorkey=0xFF00FF:0.30:0.10` + `cropdetect` para encontrar el bounding box exacto). Esto último es MÁS confiable para bordes limpios — usarlo si el remove_background automático deja halos.
3. **Fondos**: generé `fondo-banner.jpg` y `fondo-sala.jpg` con la misma composición: arco de globos arriba, cartel/marco vacío al centro (para overlay de texto/foto vía CSS/canvas — NUNCA texto horneado), grupo de personajes abajo, regalos/decoración a los lados. Estilo consistente: "photorealistic collectible vinyl toy-style photograph", 9:16.
4. **Videos de saludo** (opcional pero recomendado): generé con `cinematic_studio_video_v2` a partir del personaje individual como `start_image`, prompt simple de saludo/movimiento amigable, 5s, `sound: off`.
5. **Verificación de proporciones de personaje**: ver Incidente §4 — SIEMPRE revisa visualmente cada personaje generado contra su diseño real antes de darlo por bueno.

### 2.3 Corrección de layout del intro (aprendizaje importante)

El `.app` (contenedor raíz) DEBE mantener proporción 9:16 exacta en CUALQUIER viewport (no solo en desktop/horizontal como estaba antes). Si no, el fondo con `background-size: contain` queda con letterbox y los textos posicionados por `top: %`/`left: %` (que asumen bleed completo 1080×1920) se desalinean del arte. Ya está arreglado en `src/styles.css` (`.app { width: min(100vw, 100dvh * 0.5625); height: min(100dvh, 100vw / 0.5625); }`) — **no lo toques**, aplica a TODAS las temáticas por igual.

Las posiciones específicas del cartel de portada (`.intro--familia-canina .intro-title`, `.intro-party-decoration`, `.intro-cake-name`) SÍ son por-temática porque cada fondo tiene el cartel en un lugar distinto. Para Stitch necesitarás medir tu propio `fondo-banner.jpg` (grid de líneas cada 5% de alto con ffmpeg `drawgrid` es el método que usé — ver git history de `src/styles.css` si necesitas el comando exacto) y agregar un bloque `.intro--stitch .intro-title { top: X%; ... }` análogo.

### 2.4 `photoSession` — "pase de artista" (opcional, úsalo si aplica a Stitch)

Si algunos personajes de la temática merecen una experiencia especial (alfombra/video antes de la cámara), agrega:
```json
"photoSession": {
  "video": "transicion-sesion-fotos.mp4",
  "poster": "transicion-alfombra-base-v1.png",
  "characters": ["NombreExacto1", "NombreExacto2"],
  "teaser": "artist-teaser.jpg",
  "teaserVideo": "artist-teaser.mp4",
  "teaserLabel": "Texto corto libre (máx 40 caracteres)"
}
```
- `characters`: SOLO esos personajes activan la alfombra al salir en la ruleta; el resto va directo a su saludo normal. **Nombres deben coincidir exacto** (case-insensitive) con los `personajes[].name` de la temática.
- `teaser`/`teaserVideo`: imagen/video que se muestra en un cuadro fijo DEBAJO de la ruleta (`.spinner-artist-teaser` en CSS), mostrando a los personajes especiales bailando/animados — es publicidad visual de "esto te puede tocar". El video es MUDO, se genera igual que cualquier video Higgsfield (`cinematic_studio_video_v2`, start_image = foto de los personajes juntos en pose de baile, prompt de baile alegre).
- `teaserLabel` es INDEPENDIENTE de `characters` — puede nombrar a más personajes de los que realmente tienen pase (ej. mostrar a toda la familia en la imagen aunque el pase real sea solo para 2). Lógica en `src/themeFlow.js`.
- Toda esta sección es 100% opcional. Si Stitch no necesita pase de artista especial, simplemente no incluyas `photoSession` en el JSON de esa temática.

### 2.5 Checklist de una temática nueva completa

1. Generar los 8 JPG (6 personajes + banner + sala) + 6 cut-PNG + música + roulette-background + grupo-personajes.
2. Agregar entrada en `public/data/themes.json` con paleta de colores (`colors`, `confetti`) coherente con la temática.
3. Medir y agregar CSS del cartel de portada (`.intro--<slug> ...`) si el layout genérico no calza bien.
4. `npm run build` + `php scripts/check-dist-parity.php` (debe dar exit 0).
5. `npm test` (backend + frontend) — no debe romper nada existente.
6. Crear una fiesta de prueba en la BD local (`cb_save_parties()`, patrón en `tests/backend/run.php` o como hice yo vía `php -r`) y probar el flujo completo end-to-end en el navegador real (no solo capturas): elegir nombre → ruleta → (pase si aplica) → cámara → foto compuesta → diploma.

---

## 3. Tarea 1 — Temática Stitch (Lilo & Stitch) — **CORRECCIÓN: ya no es "crear desde cero"**

**Importante — esto invalida lo que decía antes esta sección.** El tema Lilo & Stitch YA existe, camuflado como **`tropical`** ("Aventura Tropical") en `public/data/themes.json:580-643`, con `franquicia: "Lilo & Stitch"` y sus 6 personajes ya definidos:

| Nombre camuflado | Archivo | Personaje real |
|---|---|---|
| Alien Azul | `alienazul.jpg` | Stitch |
| Alien Rosa | `alienrosa.jpg` | Angel |
| Niña Hawaiana | `hawaiana.jpg` | Lilo |
| Tortuga Marina | `tortugamar.jpg` | (relleno original) |
| Loro Tropical | `loro.jpg` | (relleno original) |
| Surfista | `surfista.jpg` | (relleno original) |

**No hay nada que decidir sobre slug ni cantidad de personajes — eso ya está cerrado.** Lo único que falta es **generar los assets físicos**: `public/themes/tropical/` está vacío en disco.

Los 8 prompts de imagen (banner, sala, 6 personajes) **ya están guardados en BD** (`cc_theme_prompts`, ver §0.1) — son la versión pulida en inglés, con la misma fórmula "collectible vinyl toy-style" que usan todas las demás temáticas del catálogo. Cárgalos así en vez de reescribirlos:
```php
require 'public/lib.php';
$themesData = cb_load_themes();
$prompts = cb_load_theme_prompts('tropical', $themesData['themes']['tropical']);
// $prompts['fondo-banner.jpg']['prompt'], $prompts['alienazul.jpg']['prompt'], etc.
```
o revísalos directo en el admin: `index.php?view=tema&slug=tropical`.

**Guía paso a paso completa del pipeline de assets (personajes, cortes, saludos, música, ruleta, grupo):** `docs/OPENCODE-CONTINUE-LILOSTITCH-ADMIN-MODAL.md`, Tarea 1 (§"PASO A PASO"). Esa guía sigue siendo válida para el proceso — solo ignora su Tarea 2 (modal), ya está hecha (ver §5 de este doc), y para los prompts de personajes usa los de la BD (arriba) en vez de la tabla en español de esa guía, para mantener consistencia de estilo con el resto del catálogo.

Cuando generes/ajustes un prompt y el resultado quede aprobado, guárdalo en BD con `cb_save_theme_prompt()` (regla §0.1) — aunque ya exista una versión previa, así queda el historial de qué cambió y por qué salió mejor.

---

## 4. Incidente importante a evitar — verificación visual de proporciones

Al generar la imagen de "familia bailando" para el teaser de Bluey, el primer intento (Bingo + Bluey solos) salió con las orejas de Bingo desproporcionadamente grandes — Luis lo notó viendo el video ya integrado en la app, no en la imagen aislada. La corrección fue: regenerar con un prompt que EXPLÍCITAMENTE especifica proporciones ("ears must be SMALL and proportionate to a young puppy, NOT oversized — same scale as the other characters' ears") y verificar la imagen ANTES de gastar créditos en el video (el video cuesta ~5x más que la imagen).

**Para Stitch: genera y revisa la imagen fija primero, pídele confirmación a Luis o compárala tú mismo cuidadosamente contra el diseño real del personaje, y solo después genera el video.** No asumas que salió bien porque el prompt "sonaba correcto".

---

## 5. Tarea 2 — Modal de vista previa de fotos en el admin (responsive) — ✅ COMPLETADA por OpenCode

**Estado: hecha y verificada.** OpenCode implementó el modal completo (overlay/close/responsive en `_style.css.php`, JS vanilla en `index.php`, Escape y click-fuera cierran, `role="dialog"`). Claude verificó en navegador real (desktop 1280px y mobile 375px) sin modificar el código — sigue en pie, no tocar salvo bug real.

Lo único que quedó como "plus no bloqueante" y NO se hizo todavía: **navegación siguiente/anterior dentro del modal**. Ver §8 — es la tarea propuesta para probar un modelo nuevo (Kimi) de forma acotada.

<details><summary>Requisitos originales (referencia, ya cumplidos)</summary>

### Requisitos

- Grilla de miniaturas de las fotos de una fiesta (ya existe algo de esto en la galería pública `public/galeria.php` — revisa ese patrón de `<img>` + `download=inline` como referencia de cómo se sirven las fotos).
- Al hacer click/tap en una miniatura, abrir un **modal** (overlay) con la foto a tamaño completo.
- **Responsive de verdad**: probar en móvil y desktop. En móvil el modal debe ocupar la pantalla razonablemente (no desbordar), con botón de cerrar accesible al pulgar. En desktop puede ser más chico centrado con fondo oscurecido.
- Navegación entre fotos dentro del modal (siguiente/anterior) es un plus deseable pero no bloqueante — priorizar que el modal básico funcione bien primero.
- Sin dependencias externas (mismo criterio que todo el admin: `public/admin/_style.css.php` es autocontenido, sin CDNs). CSS/JS vanilla o inline, siguiendo el estilo del resto de `admin/index.php` (que ya es single-file PHP con JS inline mínimo).
- Usar la paleta/tipografía de marca ya aplicada al admin (`var(--primary)`, `var(--cta)`, `--font-display`, etc. de `_style.css.php`) — el modal debe verse integrado, no como un componente ajeno.
- Accesibilidad básica: cerrar con Escape, click fuera del modal cierra, foco visible.

</details>

### Dónde mirar primero

- `public/admin/index.php` — estructura completa del admin, cómo se listan fiestas y sus acciones (botón "Galería" ya existe y linkea a `galeria.php?p=<slug>` en una pestaña nueva — probablemente lo que Luis quiere es una alternativa /complemento que muestre las fotos SIN salir del admin).
- `public/galeria.php` — cómo se cargan y sirven las fotos hoy (protegidas por PIN, vía token).
- `public/admin/_style.css.php` — tokens de diseño a reusar.

---

## 6. Qué NO tocar en esta tarea

- El ticket **AT-CUMPLECLICK-007 / Gate A** (seguridad de invitaciones) — cerrado por mí, pendiente de revisión independiente de Codex. No lo tocar.
- La temática **Carreras** ni **Bluey/familia-canina** (ya completas y probadas) — solo consultar como referencia, no modificar sus assets ni JSON salvo que encuentres un bug real (en cuyo caso, avisar antes de tocar).
- `Docs/ORCHESTRATION/*` — protocolo multi-agente, fuera de esta tarea.
- No hacer **deploy, commit, push ni merge** — todo el trabajo queda en local hasta que Luis lo revise y apruebe explícitamente.
- No generar contenido fuera de lo pedido (nada de marketing/reels — eso ya está hecho, ver `design/video/reels/`).

---

## 8. Tarea acotada para evaluar un modelo nuevo en frontend (Kimi K2.7 Code / Kimi K3)

Luis quiere probar cómo trabaja un modelo Kimi (K2.7 Code, o K3 si aparece en el picker) específicamente en frontend, con una tarea real pero chica y de bajo riesgo — NO usar Kimi para Stitch ni para nada que toque `lib.php` o la validación de archivos todavía; primero verificar la calidad con esto.

**Tarea:** navegación siguiente/anterior dentro del modal de fotos del admin (el "plus no bloqueante" que quedó pendiente de la Tarea 2, §5).

### Alcance exacto
- Archivo: `public/admin/index.php` (JS del modal, cerca de donde OpenCode implementó `.modal-overlay`) + `public/admin/_style.css.php` si necesita estilos nuevos (botones prev/next).
- Botones "‹" / "›" (o flechas) a los lados del modal, visibles solo si hay más de una foto en la grilla actual.
- Navegación con teclado: flecha izquierda/derecha además del click.
- Debe seguir funcionando todo lo que ya hace el modal (Escape, click fuera, responsive mobile/desktop) — no romper nada existente.
- Sin dependencias externas, mismo criterio del resto del admin (vanilla JS/CSS, autocontenido).
- Reusar tokens de marca ya existentes en `_style.css.php` (`var(--primary)`, `var(--cta)`, etc.), no inventar colores nuevos.

### Cómo evaluar el resultado (esto es para que Luis compare modelos, no solo para que quede funcionando)
1. ¿Siguió el patrón visual/JS existente del modal o inventó una arquitectura distinta sin necesidad?
2. ¿Tocó SOLO lo necesario o se puso a "mejorar" cosas no pedidas (refactors no solicitados)?
3. ¿El resultado es responsive de verdad en mobile (probar en el navegador, no solo asumir)?
4. ¿Corrió `npm test` / `php tests/backend/run.php` antes de decir que terminó?

Reporta a Luis con esas 4 preguntas respondidas, no solo "listo".

---

## 7. Estado actual del repo al momento de este traspaso

- Familia Canina (Bluey) 100% funcional: 6 personajes, pase de artista Bluey+Bingo, cuadro-teaser "La familia Heeler" con video de 4 personajes bailando (recién regenerado, orejas de Bingo corregidas), portada con layout ajustado, música con auto-mute durante videos con voz.
- Identidad de marca CumpleClick aplicada al kiosco y al admin (login + panel).
- 5 reels de marketing terminados y verificados en `design/video/reels/` (formato universal `yuv420p` + audio + faststart — **usa siempre ese mismo estándar de encoding si generas video para compartir/descargar**, aprendido de un bug real que impedía reproducirlos).
- Tests: 10/10 frontend, 77/77 backend, lint 36 archivos + 7 entrypoints, paridad `public↔dist` en verde.
- **Modal de fotos (Tarea 2): completo y verificado** — solo falta prev/next, ver §8.
- **Sistema de prompts versionado en BD: completo y funcionando** (`cc_theme_prompts` + `cc_theme_prompt_history`, ver §0.1). Backfill de prompts reales ya hecho para: Carreras (14/14 completo), Hielo/Cachorros/Héroes/Princesas/Dinos/Sirenas/Juguetes/Tropical (8/14 c/u — banner+sala+6 personajes; sus `-cut.png` nunca quedaron documentados como prompt de texto en `docs/PROMPTS-TEMATICAS.md`, puede que se hayan generado con la herramienta `remove_background` sin prompt propio, no asumas que falta algo que en realidad nunca existió como texto), Bluey/familia-canina (6/8 — falta `muffin.jpg` y `chloe.jpg`, cuyos prompts originales quedaron con otro nombre de personaje en los docs viejos y no se pudieron mapear con certeza; si Stitch o quien sea regenera esos dos, guardar el prompt nuevo en BD). **Mickey: 0/14, sin prompts recuperables** (el doc solo dice "usa los que ya funcionaron, están en el historial", sin el texto real — si alguien tiene esos prompts en otro lado, hay que pasárselos a Luis para backfill manual).
- Nada commiteado — todo en working tree local, tal como pidió Luis.

**Antes de empezar tu tarea**: corre `npm test`, `php tests/backend/run.php`, `php scripts/check-dist-parity.php` para confirmar que partes de una base verde. Si algo falla, avisa antes de seguir — no asumas que es tu culpa ni sigas construyendo sobre una base rota.
