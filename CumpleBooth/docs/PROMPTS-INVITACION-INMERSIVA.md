# Prompts de video — invitación inmersiva y perfil del protagonista

Fecha: 2026-08-09. Estado: **listos para generar, nada generado todavía.**

Hay **dos familias de video** y no se pueden confundir: tienen formato, duración
y destino distintos.

| Familia | Archivo | Formato | Audio | Uso |
|---|---|---|---|---|
| A — Fondo de la invitación | `themes/<slug>/invitation/invitation-motion-v1.mp4` | 720×1280, 5–7 s | **No** | Loop detrás del hero de `invitacion.php` |
| B — Intro del perfil | media privada del evento | 720×1280, 5 s | Opcional | Se reproduce al abrir "Conoce a…" |

La familia A se define en `FASE-INVITACIONES-DINAMICAS.md` §5.1. La familia B
en `EVENT-PROFILES.md`.

## Reglas duras (aplican a los diez prompts)

1. **Sin texto de ningún tipo.** Ni nombres, ni fechas, ni números, ni carteles,
   ni letreros. Todo el texto va en HTML encima del video. Un video con texto
   quemado no sirve: se reutiliza entre fiestas distintas.
2. **Sin personajes reconocibles ni elementos de franquicia.** Solo rasgos
   físicos, materiales, paleta y atmósfera. Si el resultado se parece a un
   personaje protegido, se descarta y se regenera.
3. **Sin personas reales ni menores.** Ningún rostro humano.
4. **Sin logo.** El contrato §5.1 es explícito: el logo AT se superpone después
   como overlay del sistema, nunca se genera. Esto es distinto de la regla de
   Reels de marketing de AT, que aquí no aplica porque es un asset de producto.
5. **9:16 vertical**, movimiento de cámara lento y continuo.
6. **Familia A: loop.** El último frame debe poder encadenar con el primero sin
   salto. Movimiento de deriva suave, nunca un gesto que "termine".
7. **Familia A: zona segura limpia.** El centro y el tercio inferior quedan sin
   detalle fuerte: encima va el nombre, la fecha y el botón.

Bloque que antecede a todos los prompts:

```text
NEGATIVE / MUST NOT APPEAR: any written text, letters, numbers, captions,
signage, banners with words, logos, watermarks, brand marks, recognizable
copyrighted characters or franchise elements, real people, children, human
faces, distorted anatomy, harsh strobing, abrupt cuts.
```

---

## Familia A — fondo de la invitación (5 clips, uno por temática)

Parámetros: `cinematic_studio_video_v2`, 9:16, 5 s, `sound: off`, `mode: std`,
`genre` indicado en cada uno.

### A1 · carreras — `themes/carreras/invitation/invitation-motion-v1.mp4`

`genre: action`

```text
Slow cinematic drift across an original championship celebration paddock at
sunset, seen from a low hero angle. Deep charcoal asphalt with wet reflections,
glossy scarlet red and warm gold accents, tall plain pennant flags rippling with
no symbols on them, floating balloons, restrained golden confetti drifting
downward, faint warm speed trails of light crossing the far background.
Volumetric dusk light, shallow depth of field, gentle continuous camera push
with no cuts. Keep the central and lower third clean, uncluttered and softly
out of focus. Seamless loop: the motion drifts, it never completes a gesture.
```

### A2 · familia-canina — `themes/familia-canina/invitation/invitation-motion-v1.mp4`

`genre: comedy`

```text
Slow cinematic drift across an original sunny backyard celebration built from
soft rounded shapes, in a palette of clear sky blue, warm orange and golden
yellow. Handcrafted paper garlands, pastel balloons tied to a wooden fence,
paw-shaped confetti drifting slowly, soft cotton clouds passing far above, warm
grass and dappled afternoon light. No animals, no characters. Gentle continuous
camera drift, shallow depth of field, playful but calm. Keep the central and
lower third clean and softly out of focus. Seamless loop.
```

### A3 · tropical — `themes/tropical/invitation/invitation-motion-v1.mp4`

`genre: intimate`

```text
Slow cinematic drift across an original tropical island celebration at golden
hour, in a palette of deep indigo, coral and warm orange. Palm fronds swaying
in the foreground, hibiscus-like flowers, paper lanterns floating and glowing,
balloons drifting, calm ocean water reflecting the low sun, fine warm sand.
No creatures, no characters. Gentle continuous camera drift, shallow depth of
field, warm and magical. Keep the central and lower third clean and softly out
of focus. Seamless loop.
```

### A4 · hielo — `themes/hielo/invitation/invitation-motion-v1.mp4`

`genre: spectacle`

```text
Slow cinematic drift through a luminous crystalline winter palace hall, in a
palette of cyan, pale blue and white. Translucent ice arches, faceted frost
columns, snow crystals floating slowly through the air, soft aurora reflections
sliding across polished ice, magical volumetric light beams from above.
No creatures, no characters. Gentle continuous camera drift, shallow depth of
field, serene and enchanted. Keep the central and lower third clean and softly
out of focus. Seamless loop.
```

### A5 · kpop — `themes/kpop/invitation/invitation-motion-v1.mp4`

`genre: spectacle`

```text
Slow cinematic drift across an empty premium neon concert stage, in a palette of
magenta, cyan and deep violet. Moving spotlight beams sweeping through light
haze, holographic particles rising, plain glowing light sticks with no logos
scattered in the blurred foreground, restrained metallic confetti falling,
glossy black stage floor with mirrored reflections. No performers, no audience
faces, no characters. Gentle continuous camera drift, shallow depth of field,
energetic but elegant. Keep the central and lower third clean and softly out of
focus. Seamless loop.
```

---

## Familia B — intro del perfil del protagonista (5 clips)

Parámetros: `cinematic_studio_video_v2`, 9:16, 5 s, `mode: std`. El audio puede
ir en `on`: la ficha ya tiene botón de silencio y arranca en mute.

A diferencia de la familia A, aquí **sí** hay un gesto que termina: el clip
cierra en una revelación, y en ese momento el frontend superpone el título
"Conoce a …". Por eso el último segundo debe quedar visualmente limpio y
centrado.

El esqueleto es común y cambia solo el bloque de escena:

```text
ONE SHOT — 5 seconds — vertical 9:16 — cinematic premium celebration.

<ESCENA DE LA TEMÁTICA>

Smooth cinematic push-in, layered foreground and background, elegant particles,
clear visual center and a graceful final reveal that settles into stillness in
the last second. Leave a clean, empty safe area in the centre and lower third
for an HTML title overlay added later.

No written text, names, numbers, gift information or private information. No
recognizable characters, franchises, logos, emblems or protected designs. No
real person appears.
```

| Temática | Bloque de escena | Estilo emocional |
|---|---|---|
| carreras | `Original championship racetrack celebration at sunset opening up ahead, glossy red, gold and charcoal palette, plain flags without symbols, balloons, restrained confetti and subtle speed trails converging toward the centre.` | épico |
| familia-canina | `Playful original backyard celebration with rounded shapes, blue, orange and golden-yellow palette, soft clouds, balloons, paw-shaped confetti and handcrafted decorations opening toward a warm empty centre.` | divertido |
| tropical | `Magical tropical-island celebration at golden hour, indigo, coral and warm orange palette, palms parting, flowers, floating lanterns and ocean reflections revealing a calm bright centre.` | mágico |
| hielo | `Luminous crystalline winter palace, cyan and pale-blue palette, floating snow crystals, icy arches opening, soft aurora reflections and magical volumetric light gathering at the centre.` | mágico |
| kpop | `Premium neon concert stage, magenta, cyan and deep-violet palette, moving spotlights converging, holographic particles, plain light sticks without logos and restrained metallic confetti settling toward a clear centre.` | épico |

---

## Coste y orden sugerido

Con el saldo actual **no alcanza para los diez**. Orden por retorno:

1. **A5 kpop** y **A4 hielo** — las dos temáticas con demo armada y las que más
   ganan con fondo en movimiento.
2. **A1 carreras**, **A2 familia-canina**, **A3 tropical**.
3. Familia B completa, cuando el perfil se venda como extra.

La invitación inmersiva **funciona sin ningún video**: cae en el fondo estático
de la temática. Los videos son mejora progresiva, no requisito.

## Post-proceso obligatorio (familia A)

```bash
ffmpeg -i entrada.mp4 -an -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280" -c:v libx264 -profile:v high -pix_fmt yuv420p -movflags +faststart -crf 23 invitation-motion-v1.mp4
```

`-an` elimina el audio: el hero es un loop silencioso y un video con pista de
audio se bloquea en autoplay en móvil. Verificar con
`scripts/normalize-event-profile-video.mjs` antes de publicar.
