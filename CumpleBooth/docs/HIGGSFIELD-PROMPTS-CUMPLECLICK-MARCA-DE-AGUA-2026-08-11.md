# Prompts Higgsfield — campaña de invitaciones CumpleClick

Fecha: 2026-08-11

Estado: prompts aprobados para generación manual. Este documento no autoriza
generación automática ni consumo de créditos.

## Referencias que se deben adjuntar

- Logo recomendado para Higgsfield: `design/logo/logo-icon-wordmark.png`
  (PNG transparente, encuadre ajustado, símbolo + nombre). Este es el archivo
  que se debe adjuntar como referencia del logo en todos los prompts nuevos.
- No usar `design/logo/cumpleclick-logo-master-render.png` como watermark: su
  fondo crema es opaco y puede aparecer como un recuadro dentro del video.
- En el prompt 1, adjuntar además el fondo de la temática que se quiera mostrar.
- No adjuntar fotografías de niños ni protagonistas sin autorización expresa.

## Patrón histórico que funcionó

Este es el prompt compartido por Luis que respetó correctamente la marca de agua
en otra cuenta de Higgsfield. Se conserva como referencia de comportamiento:

```text
A young boy standing in front of a colorful digital kiosk at a birthday party.
An animated 3D character magically emerges from the screen with golden sparkles
and particles. The boy's eyes widen with pure wonder, mouth open, he takes a
step back amazed. Background with balloons and party lights. The provided
reference image is the CumpleClick logo. It must appear as a very small, subtle,
translucent watermark in the top-left corner of the frame, like a professional
TV watermark. Never center it, never enlarge it. 9:16 vertical, 10 seconds,
cinematic warm lighting.
```

## Prompt 1 — apertura WOW, 15 segundos

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — premium children's
celebration commercial.

Use the supplied empty event-stage background only as the environmental visual
reference: cyan and hot-pink balloon arch, silver star garlands, violet and
electric-blue spotlights, glossy floor and colorful confetti. A sealed elegant
invitation envelope floats at the center.

0–4 seconds: the camera glides through floating glitter and soft cyan-magenta
light streaks toward the sealed envelope. Build anticipation with elegant depth
and restrained motion.

4–9 seconds: the envelope opens naturally and releases a luminous portal. The
balloon arch, star garlands and stage lights gain cinematic depth as if the
invitation has opened a complete celebration world.

9–13 seconds: travel gently through the portal into a magical, original party
space with balloons, confetti and volumetric light. Keep the center and lower
third clean for post-production copy.

13–15 seconds: settle into a stable bright hero frame with a soft celebratory
light pulse, suitable for a clean edit into real CumpleClick screen recording.

Joyful, exciting, age-appropriate, high-end cinematic lighting, realistic
particles, smooth camera, no cuts, no people or children, no readable scene
text, no UI, no personal information, no franchise references and no protected
characters.

The provided reference image is the CumpleClick logo. It must appear as a very
small, subtle, translucent watermark in the top-left corner of the frame, like a
professional TV watermark. Never center it, never enlarge it.

MANDATORY: The provided reference image is the ONLY official CumpleClick logo.
Keep the exact shape, colors and lettering from the reference. The logo must
remain very small, subtle and translucent in the top-left corner, like a
professional TV watermark, with a safe margin from the edges and a stable
position for the entire video. Never center it, enlarge it, animate it, crop it,
redesign it or invent a different logo or generic icon.
```

## Prompt 2 — Scroll se transforma en Automática, 15 segundos

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — premium visual metaphor for
two immersive invitation experiences.

0–5 seconds: layered illustrated celebration panels move upward in response to
an invisible finger-like scroll gesture. The movement is tactile, elegant and
easy to understand; each panel reveals balloons, lights, confetti and a new
chapter. No phone and no simulated interface.

5–10 seconds: the panels accelerate and fold into luminous cinematic frames.
Their motion transforms seamlessly from user-controlled vertical movement into
a self-playing sequence of living lights, particles and dimensional party
scenes.

10–13 seconds: the automatic sequence advances smoothly by itself through two
original celebration environments, communicating effortless cinematic motion.

13–15 seconds: both visual languages resolve into one polished violet, cyan and
warm-gold celebration space with clear room for the post-production labels
“Plan Básico · Scroll” and “Plan Full · Automática”. Do not render those words
inside the generated video.

Premium mobile-first advertising aesthetic, joyful and age-appropriate, smooth
transformation, no abrupt cuts, no people, no readable scene text, no phone UI,
no personal information, no franchise references and no protected characters.

The provided reference image is the CumpleClick logo. It must appear as a very
small, subtle, translucent watermark in the top-left corner of the frame, like a
professional TV watermark. Never center it, never enlarge it.

MANDATORY: The provided reference image is the ONLY official CumpleClick logo.
Keep the exact shape, colors and lettering from the reference. The logo must
remain very small, subtle and translucent in the top-left corner, like a
professional TV watermark, with a safe margin from the edges and a stable
position for the entire video. Never center it, enlarge it, animate it, crop it,
redesign it or invent a different logo or generic icon.
```

## Configuración manual recomendada

- Modelo: Seedance 2.5 en la interfaz web de Higgsfield.
- Formato: vertical 9:16.
- Resolución: 720p.
- Duración: 15 segundos por clip.
- Generar primero el prompt 1 y revisar logo, composición y continuidad antes de
  generar el prompt 2.
- No pedir textos comerciales dentro del video. Agregar textos nítidos en
  edición: `Plan Básico · Scroll` y `Plan Full · Automática`.

## Control de calidad

- El logo está arriba a la izquierda durante todo el clip.
- Se percibe muy pequeño y discreto, como una marca de agua de televisión, sin
  imponer un porcentaje numérico en la primera generación.
- Es sutil y translúcido, no protagonista.
- No fue rediseñado, animado, agrandado ni trasladado al centro.
- El símbolo circular conserva su forma y el wordmark conserva sus colores:
  `Cumple` violeta/morado y `Click` rosado/magenta. Gris, blanco, plateado,
  monocromático o una huella inventada son fallos de marca.
- Antes de generar, la miniatura del PNG debe estar visible y seleccionada en
  `Elements`. Si el historial registra `medias: []` o `reference_elements: []`,
  la referencia no llegó al modelo y la generación falla QA aunque aparezca un
  texto parecido a CumpleClick.
- Si el video es bueno pero el logo no es exacto, conservar el video limpio y
  superponer el PNG oficial en postproducción. Es el método determinista y no
  requiere regenerar la escena.
- No aparecen marcas, franquicias, personajes protegidos ni datos privados.
- La orientación es 9:16 y la duración efectiva es 15 segundos.
- El último cuadro permite una transición limpia a la grabación de pantalla.

## Regla de intros temáticos de invitación

Desde 2026-08-11, cada intro se genera **desde cero**, sin reutilizar el fondo
como imagen de partida, pero debe sentirse parte de la misma temática de la
invitación. El prompt toma del fondo vigente su paleta, iluminación, profundidad,
materiales, energía y composición general; nunca copia personajes, vehículos ni
elementos protegidos. Carreras usa rojo, amarillo, negro y dorado con energía de
pista; Reino de Hielo usa celeste, blanco, cristal y luz invernal luminosa.

La integración pública es genérica por convención. Un tema activa su intro al
incorporar estos archivos:

- `public/themes/<tema>/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/<tema>/invitation/intro-invitacion-wow-v1-poster.jpg`

Al tocar el sobre, el video se reproduce vertical con audio y controles HTML
superpuestos: logo CumpleClick real, progreso y `Omitir intro`. Al finalizar,
omitir o fallar el video, continúa la invitación. Si los archivos no existen, el
tema conserva el flujo anterior sin cambios. Esta convención aplica a las cinco
temáticas actuales y a cualquier temática futura, sin agregar condicionales por
nombre en el frontend.

## Regla de congruencia visual para todos los intros

Cada video se genera desde cero, pero debe adoptar la paleta, iluminación,
materiales, energía y lenguaje espacial de `invitation-base-v1.jpg`,
`fondo-banner.jpg` y `fondo-sala.jpg` de su tema. El sobre es el puente visual
entre la portada y el mundo del kiosco. No se copian personajes, franquicias,
vehículos, vestuarios ni símbolos protegidos. Esta regla aplica a las cinco
temáticas actuales y a las futuras.

Configuración común: Seedance 2.5, modo Unlimited, 15 s, 9:16, 720p, una toma
continua y audio original instrumental/ambiental sin voces ni letras. Adjuntar
únicamente `design/logo/logo-icon-wordmark.png` como referencia oficial del
logo. No adjuntar el fondo como imagen inicial. Antes de pulsar `Generate`,
confirmar visualmente que la miniatura del logo continúa activa en `Elements`.

Resultado QA 2026-08-12: las generaciones `99c23a70...` (Familia Canina),
`3e8ea986...` (Tropical) y `cdc222ad...` (K-Pop) registraron `medias: []` y
`reference_elements: []`. Seedance inventó wordmarks grises/blancos y una
huella no oficial. No repetir esas versiones sin el gate de referencia y las
correcciones siguientes.

## Prompt corregido v2 — Familia Canina

Referencias visuales internas: `public/themes/familia-canina/invitation/invitation-base-v1.jpg`,
`public/themes/familia-canina/fondo-banner.jpg` y
`public/themes/familia-canina/fondo-sala.jpg`.

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — 720p — premium magical
invitation intro for a joyful children's backyard celebration.

Create the scene from scratch. Match this visual language: vivid sky blue,
warm orange, cream, sunny yellow and deep navy accents; soft rounded shapes,
friendly handcrafted decorations, a bright family backyard, playful garden
depth, balloon clusters, bunting, tiny paw-shaped confetti and warm afternoon
light. Keep the celebration environment original and free of third-party
branding. The exact supplied CumpleClick watermark is the only permitted brand.

0–4 seconds: the camera glides gently through blue, orange and yellow balloons
toward a sealed cream invitation envelope floating above a playful garden path.
The envelope has subtle blue-orange trim and a small generic paw-shaped wax
seal. Build anticipation with soft wind, moving bunting and restrained sparkle.

4–9 seconds: the envelope opens naturally. A warm blue-and-gold light portal
unfolds from inside, revealing a cheerful original backyard party world with a
playhouse silhouette, rounded trees, paper garlands, balloons and drifting
confetti. Use cinematic depth while preserving the friendly illustrated 3D
look of the invitation and kiosk.

9–13 seconds: travel smoothly through the portal as decorations gain life:
balloons sway, small lights twinkle and colorful paw-shaped particles trace a
gentle arc. No animals, no people and no recognizable characters.

13–15 seconds: settle on a bright stable hero frame in the backyard, with the
center and lower third clean for the CumpleClick invitation transition.

Original instrumental celebration sound design with light percussion, soft
chimes and garden ambience; no lyrics and no voices. Smooth camera, no cuts, no
readable scene text, no UI, no personal information, no franchise references,
no protected characters and no logos other than the exact supplied CumpleClick
watermark.

The provided reference image is the CumpleClick logo. It must appear as a very
small, subtle, translucent watermark in the top-left corner of the frame, like
a professional TV watermark. Never center it, never enlarge it.

MANDATORY: The provided reference image is the ONLY official CumpleClick logo.
Keep the exact shape, colors and lettering from the reference. The logo must
remain very small, subtle and translucent in the top-left corner, with a safe
margin and a stable position for the entire video. Never center it, enlarge it,
animate it, crop it, redesign it or invent a different logo or generic icon.

COLOR FIDELITY IS MANDATORY: preserve the original full-color wordmark exactly.
“Cumple” must remain deep violet/purple and “Click” must remain vivid
pink/magenta, together with the original purple-pink circular symbol. Never
render the wordmark in gray, white, silver, black, monochrome or theme colors.
Never replace the symbol with a paw, flower, star or any decorative icon.
```

## Prompt corregido v2 — Aventura Tropical

Referencias visuales internas: `public/themes/tropical/invitation/invitation-base-v1.jpg`,
`public/themes/tropical/fondo-banner.jpg` y
`public/themes/tropical/fondo-sala.jpg`.

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — 720p — premium cinematic
invitation intro for an original tropical children's celebration.

Create the scene from scratch. Match this visual language: deep indigo and
royal blue, turquoise water, coral orange, hibiscus pink, fresh tropical green,
sunset gold and soft violet shadows; lush island foliage, playful handcrafted
party decor, ocean reflections and a magical golden-hour atmosphere. Keep the
world original and free of third-party branding. The exact supplied CumpleClick
watermark is the only permitted brand.

0–4 seconds: the camera floats above a sparkling turquoise shoreline at golden
hour. Coral, pink and indigo ribbons, hibiscus petals and warm lantern lights
guide the eye toward a sealed deep-indigo invitation envelope resting upright
on a small polished wooden party stand. The envelope has coral trim and a
simple original flower seal.

4–9 seconds: a gentle wave of golden light circles the envelope and it opens
naturally. A luminous turquoise-coral portal blooms from inside, revealing an
original island celebration with palms, flowers, balloons, woven decorations,
soft lanterns and distant ocean reflections.

9–13 seconds: travel smoothly through the portal between layered leaves and
floating petals. Lanterns glow, balloons sway and tiny droplets turn into
golden confetti, creating cinematic depth without showing any people,
creatures or recognizable characters.

13–15 seconds: settle into a stable bright beach-party hero frame with a clean
center and lower third for the CumpleClick invitation transition.

Original instrumental island celebration sound design with light ukulele-like
plucks, soft hand percussion, ocean ambience and magical chimes; no lyrics and
no voices. Smooth camera, no cuts, no readable scene text, no UI, no personal
information, no franchise references, no protected characters and no logos
other than the exact supplied CumpleClick watermark.

The provided reference image is the CumpleClick logo. It must appear as a very
small, subtle, translucent watermark in the top-left corner of the frame, like
a professional TV watermark. Never center it, never enlarge it.

MANDATORY: The provided reference image is the ONLY official CumpleClick logo.
Keep the exact shape, colors and lettering from the reference. The logo must
remain very small, subtle and translucent in the top-left corner, with a safe
margin and a stable position for the entire video. Never center it, enlarge it,
animate it, crop it, redesign it or invent a different logo or generic icon.

COLOR FIDELITY IS MANDATORY: preserve the original full-color wordmark exactly.
“Cumple” must remain deep violet/purple and “Click” must remain vivid
pink/magenta, together with the original purple-pink circular symbol. Never
render the wordmark in gray, white, silver, black, monochrome or theme colors.
Never replace the symbol with a paw, flower, star or any decorative icon.
```

## Prompt corregido v2 — Guerreras K-Pop

Referencias visuales internas: `public/themes/kpop/invitation/invitation-base-v1.jpg`,
`public/themes/kpop/fondo-banner.jpg` y
`public/themes/kpop/fondo-sala.jpg`.

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — 720p — premium cinematic
invitation intro for an original neon pop-fantasy children's celebration.

Create the scene from scratch. Match this visual language: electric magenta,
cyan, violet, deep plum, midnight navy, white sparkle and restrained warm-gold
accents; a glossy concert stage, luminous geometric panels, soft haze, elegant
light beams, star particles and rhythmic but age-appropriate energy. Keep the
design original, polished and free of third-party branding. The exact supplied
CumpleClick watermark is the only permitted brand.

0–4 seconds: the camera glides through a dark-plum corridor of magenta and cyan
light beams toward a sealed glossy violet invitation envelope floating above a
reflective stage. The envelope has thin cyan-magenta edges and a simple
original starburst seal. Light pulses build anticipation without rapid flashes.

4–9 seconds: the envelope opens naturally on a musical light pulse. A radiant
magenta-cyan portal expands from inside, revealing an original futuristic pop
celebration stage with layered LED-like shapes, floating ribbons, balloons,
crystalline star particles and cinematic depth.

9–13 seconds: travel smoothly through the portal while geometric panels unfold
like elegant wings, spotlights sweep slowly and glitter trails move in rhythm.
Do not show performers, people, weapons, symbols, costumes or recognizable
characters.

13–15 seconds: settle into a stable luminous hero frame on the empty stage,
with the center and lower third clean for the CumpleClick invitation transition.

Original instrumental electro-pop celebration sound design with a clean beat,
soft synth rise and magical chimes; no lyrics and no voices. Smooth camera, no
cuts, no strobing, no readable scene text, no UI, no personal information, no
franchise references, no protected characters and no logos other than the exact
supplied CumpleClick watermark.

The provided reference image is the CumpleClick logo. It must appear as a very
small, subtle, translucent watermark in the top-left corner of the frame, like
a professional TV watermark. Never center it, never enlarge it.

MANDATORY: The provided reference image is the ONLY official CumpleClick logo.
Keep the exact shape, colors and lettering from the reference. The logo must
remain very small, subtle and translucent in the top-left corner, with a safe
margin and a stable position for the entire video. Never center it, enlarge it,
animate it, crop it, redesign it or invent a different logo or generic icon.

COLOR FIDELITY IS MANDATORY: preserve the original full-color wordmark exactly.
“Cumple” must remain deep violet/purple and “Click” must remain vivid
pink/magenta, together with the original purple-pink circular symbol. Never
render the wordmark in gray, white, silver, black, monochrome or theme colors.
Never replace the symbol with a paw, flower, star or any decorative icon.
```

Destinos esperados, después de aprobación y normalización:

- `public/themes/familia-canina/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/tropical/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/kpop/invitation/intro-invitacion-wow-v1.mp4`

No integrar una generación hasta validar 15 s, 9:16, 720p, audio, peso, logo y
coherencia visual. El póster se deriva localmente del video aprobado.
