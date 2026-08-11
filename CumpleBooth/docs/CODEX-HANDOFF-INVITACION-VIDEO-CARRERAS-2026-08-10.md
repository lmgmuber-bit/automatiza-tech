# Handoff a Codex — Video de carrera nuevo para la invitación (carreras)

Fecha: 2026-08-10. Autor: Claude. Rama de trabajo:
`.worktrees/cumplebooth-protagonista` (`codex/cumplebooth-protagonista-dynamic`).

## Contexto

La invitación inmersiva (`public/invitacion.php`) tiene un hero con
scroll-scrubbing: el scroll del invitado controla `video.currentTime` de
`public/themes/<slug>/invitation/invitation-scroll-v1.mp4` (keyframe por
cuadro, `-g 1`). Es la prueba de concepto pedida por Luis, hecha primero en
`carreras`.

Luis pidió que la temática `carreras` se sienta "más inmersiva... que los
personajes estén en una carrera y lo estén llevando a la parte final, la
invitación y conoce al cumpleañero".

## Estado actual — YA APROBADO POR LUIS, no se toca sin su ok

1. **Base de la invitación** (`invitation-base-v1.jpg`): reemplazada. Antes
   tenía un cuadro dorado vacío colgado en la pared ("no con el cuadro de
   fondo", pidió Luis). Ahora es un frame extraído de
   `despedida-carreras.mp4` (t=1.5s) con el elenco completo (Mate, Rayo,
   Cruz, El Rey, Sally) sin cuadro. Panel de texto reubicado sobre la
   ventana (`text_area` en `public/data/event-profile-presets.json`, tema
   `carreras`: `x:0.15, y:0.1, w:0.7, h:0.21, tone:"#7a1c10"`). Verificado
   sin desborde con `node storage/event-profile-demo/capture-plantillas.mjs <out>`.

2. **Video de scroll-dive** (`invitation-scroll-v1.mp4`): reemplazado. Antes
   era un clip de estadio nocturno bajo lluvia con carteles de fondo con
   texto ilegible (regla dura del proyecto: cero texto en video generado).
   Ahora es un **asset ya existente y aprobado**, `saludo-rayo-mcqueen-v3.mp4`
   (720×1270, 5s), re-encodeado a 540×960 `-g 1` sin audio. Muestra al auto
   avanzando por una pista bordeada de globos y **llegando a la meta a
   cuadros justo en el último frame** — encadena perfecto con la sección
   siguiente (CTA "Ver invitación"). Luis lo vio y dijo "me gustó, sí
   dejemos ese".

   Comando usado:
   ```bash
   ffmpeg -y -i public/themes/carreras/saludo-rayo-mcqueen-v3.mp4 \
     -vf "scale=540:960" -an -g 1 -c:v libx264 -preset veryfast -crf 20 \
     -pix_fmt yuv420p public/themes/carreras/invitation/invitation-scroll-v1.mp4
   ```

3. Ajuste de nitidez del hero (afecta las 5 temáticas, no solo carreras):
   blur bajó de 7–24px a 2–5px, brillo subió (`--inv-hero-brightness` con
   multiplicador 0.22 en vez de 0.45 sobre `hero_dim`), text-shadow reforzado
   en `.inv-kicker`/`.inv-name`. Ver `public/assets/invitation.css` y
   `public/invitacion.php` (línea con `--inv-hero-brightness`).

4. Build (`npm run build`) y tests (`npm test`, 101/101) verdes con todo lo
   anterior.

**Nada de esto es urgente ni está bloqueado** — es la línea base entregable
ahora mismo. Lo que sigue es una mejora opcional.

## La tarea pendiente para Codex

Luis quiere, además del video reutilizado, un video **genuinamente nuevo**
de carrera (autos compitiendo, cámara acercándose, terminando cerca de la
meta) para reemplazar o complementar `invitation-scroll-v1.mp4`. No es
bloqueante: el asset reutilizado ya es aceptable y está en su lugar.

### Técnica documentada que ya funcionó antes (Cruz, `docs/OPENCODE-PROMPT-2026-07-14.md`)

```
model: kling3_0_turbo
medias: [{role: "start_image", value: "<media_id de rayo-mcqueen-cut.png subido>"}]
duration: 5
resolution: "720p"
aspect_ratio: "9:16"
prompt: "Cute toy race car character with big expressive cartoon eyes and a
friendly smile, parked on a race track with confetti around it. It revs its
engine happily, wiggles side to side and bounces playfully as if waving hello
to the camera, headlights blink like a cheerful blink, then settles facing
the camera with a big warm smile, festive birthday party energy, no text or
logos change."
```

Fuente de la imagen: `public/themes/carreras/rayo-mcqueen-cut.png` (recorte
real ya aprobado, sin texto quemado — NO usar `rayo-mcqueen.jpg`, trae
"MARIANO 95" quemado).

Si Higgsfield sugiere el preset "RACE TRACK" (id
`61b0d099-580f-4739-93f0-457e6f38da24`), rechazarlo — no tiene relación
(mostró una influencer real en una pista, según nota previa de Codex) — y
generar literal con `declined_preset_id`.

### Lo que Claude intentó hoy (2026-08-10) y falló — no repetir a ciegas

10 intentos consecutivos con resultado `status: "nsfw"` (falso positivo, sin
cobro de créditos), variando:

- Prompt literal de arriba (verbatim) con `rayo-mcqueen-cut.png` como
  `start_image` — nsfw.
- Prompt editado para agregar "toward the camera" / "speeds forward" — nsfw.
- Solo texto, sin imagen, describiendo el auto con los rasgos canónicos de
  `docs/PROMPTS-TEMATICAS.md` — nsfw.
- Modelo alternativo `seedance_2_5` (modo `omni_reference`) con el mismo
  prompt — falló (no nsfw, error genérico).
- Modelo alternativo `minimax_h3` con el mismo prompt — nsfw.
- Imagen re-subida (media_id nuevo, para descartar problema de caché/dedup)
  con el prompt literal — nsfw otra vez.
- Prompt simplificado sin "race track"/"confetti", solo "auto en una sala
  acogedora" — nsfw también.

**Control de cordura**: el mismo modelo (`kling3_0_turbo`), mismo momento,
con un prompt sin relación ("cielo azul con nubes, sin personas, sin auto")
**completó exitosamente**. Esto descarta un bloqueo de cuenta/sesión — el
filtro está reaccionando específicamente al personaje/tema "auto de juguete
con ojos" hoy, no al texto ni a la cuenta.

### Sugerencias para Codex

1. Simplemente reintentar más tarde (el filtro puede ser transitorio —
   Cruz sí funcionó un día distinto con esencialmente el mismo prompt).
2. Probar con `luigi-cut.png`, `mate-cut.png`, `sally-cut.png` o
   `el-rey-cut.png` en vez de `rayo-mcqueen-cut.png` — si algún otro
   personaje pasa el filtro, valdría generar ese y no insistir con Rayo.
3. `BudgetPixel` (MCP alternativo, otro proveedor/otro filtro) tiene
   `seedance-2.0` para image-to-video — requiere que Luis re-autorice el
   conector (token vencido); no lo puede hacer un agente.
4. Si Codex confirma el mismo bloqueo, no seguir insistiendo sin
   preguntarle a Luis — ya se gastó tiempo de sesión en 10 intentos sin
   resultado.

### Si Codex logra generar el video nuevo

1. Descargar el resultado, verificar 720×1280 (o superior), sin texto
   quemado, sin flashes agresivos, personajes estables.
2. Re-encodear para scroll-scrub:
   ```bash
   ffmpeg -y -i <nuevo.mp4> -vf "scale=540:960" -an -g 1 -c:v libx264 \
     -preset veryfast -crf 20 -pix_fmt yuv420p \
     public/themes/carreras/invitation/invitation-scroll-v1.mp4
   ```
3. Verificar con `node storage/event-profile-demo/capture-dive.mjs <out>`
   que `currentTime` avanza de 0 a la duración real de forma lineal con el
   scroll.
4. `npm run build`, `npm test` (debe seguir en 101/101).
5. Mostrarle el resultado a Luis antes de reemplazar el actual — el
   reutilizado ya está aprobado, así que el nuevo compite contra un
   candidato que ya gustó, no contra nada.

## Archivos tocados hoy (2026-08-10, para referencia rápida)

- `public/themes/carreras/invitation/invitation-base-v1.jpg` (reemplazado)
- `public/themes/carreras/invitation/invitation-scroll-v1.mp4` (reemplazado)
- `public/data/event-profile-presets.json` (`text_area` de `carreras` y
  `hero_dim` de `kpop` ajustados)
- `public/assets/invitation.css` (blur/brightness/text-shadow del hero)
- `public/invitacion.php` (fórmula de `--inv-hero-brightness`)

Nada de esto se ha commiteado, pusheado ni mergeado. Sigue pendiente de
cierre de sesión con Luis.
