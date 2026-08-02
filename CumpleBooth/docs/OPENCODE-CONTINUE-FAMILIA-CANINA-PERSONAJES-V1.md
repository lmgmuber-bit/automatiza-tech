# OpenCode — personajes, ruleta y saludos de Aventura Perruna v1

Ticket: `AT-CUMPLECLICK-007`
Rama: `codex/cumpleclick-secure-db-frontend`
Ejecutor: OpenCode + DeepSeek V4 Flash
Generación autorizada: Higgsfield, secuencial y con costo registrado

## 1. Estado recibido

Luis informa que el video genérico de 5 segundos ya fue generado. Codex revisó
los frames inicial, medio y final: el cartel permanece vacío, se conservan las
cuatro figuras y el movimiento es apto. El candidato incluye una pista AAC no
solicitada; el video genérico debe publicarse sin audio.

Antes de generar otro job:

1. localizar el resultado ya cobrado en el output privado o recuperar el job;
2. no regenerarlo ni volver a cobrarlo;
3. validar MP4 real, 5 s, 720p, 9:16, frames inicial/medio/final y panel vacío;
4. retirar únicamente la pista AAC, sin recodificar el video, mediante un
   remux equivalente a `ffmpeg -i candidato.mp4 -map 0:v:0 -c:v copy -an`;
5. promover el resultado silencioso como
   `public/themes/familia-canina/invitation/invitation-base-motion-v1.mp4`;
6. registrar modelo, costo real, checksum y confirmar con `ffprobe` que este
   MP4 genérico contiene video H.264 y ningún stream de audio.

No volver a llamar a Higgsfield para esta corrección.

## 2. Objetivo inmediato

Crear seis imágenes individuales para la ruleta y luego seis videos de saludo.
Todos deben compartir exactamente el mismo escenario, cámara, iluminación,
paleta y acabado aprobados. No pedir a cada generación que invente nuevamente
el fondo.

Orden:

1. crear y aprobar un único `character-stage-v1.png`, sin personajes;
2. usar ese mismo archivo como edit target/background bloqueado para cada una
   de las seis imágenes individuales;
3. generar máximo dos personajes antes de cada revisión visual;
4. aprobar las seis imágenes;
5. animar una por una, máximo un video antes de cada QA;
6. añadir y verificar la pista de voz exacta;
7. integrar ruleta, fallback, foto y diploma.

## 3. Escenario maestro común

Archivo candidato:
`public/themes/familia-canina/character-stage-v1.png`

Prompt exacto de edición, usando como referencia visual los dos PNG aprobados:

```text
Create a clean reusable vertical 9:16 character stage derived from the provided
approved birthday references. Preserve the same premium photorealistic 3D
collectible-toy finish, smooth rounded geometry, soft matte-to-satin materials,
subtle ambient occlusion, bright warm cinematic daylight, realistic soft
contact shadows and saturated sky-blue, deep navy, warm orange, coral, cream,
pastel-pink, sunny-yellow and soft-green palette.

Show the same cheerful pastel children's playground birthday environment with
a softly blurred balloon arch, colorful play structures, green grass, a few
wrapped gifts and subtle confetti. Create one clear empty standing area in the
lower center for a single full-body character. Keep the camera and background
perfectly centered, eye-level to slightly low, with a 35mm lens feel and gentle
depth of field.

Remove every character, animal and person. No cake figurines. No text, letters,
words, numbers, signs, logos, signatures, QR codes or watermarks. The empty
stage must be suitable as an identical locked background for six separate
character portraits and their animation start images.
```

No generar los personajes hasta que Luis apruebe este fondo común.

## 4. Prefijo obligatorio para las seis imágenes

Usar `character-stage-v1.png` como edit target con alta fidelidad. Agregar el
bloque físico correspondiente de la sección 5.

```text
Edit the provided approved vertical 9:16 empty character-stage image. Preserve
the background pixel composition as closely as the tool permits: same balloon
arch, playground structures, grass, gifts, confetti, lighting, shadows, camera,
depth of field and colors. Do not redesign, crop, recolor or replace the stage.

Add exactly one original full-body anthropomorphic cattle-dog figure in the
empty lower-center standing area. The figure faces the camera in a friendly
three-quarter pose, completely visible from both ears to both paws and tail,
with generous padding. Use premium photorealistic 3D collectible-toy rendering,
smooth rounded child-friendly geometry, clean edges, soft matte-to-satin fur,
subtle ambient occlusion and a realistic contact shadow on the grass.

No additional characters, animals or humans. No text, letters, words, numbers,
logos, signatures, QR codes or watermarks. No flat 2D drawing, thick outlines,
sticker look, cheap plastic, low-poly geometry, malformed paws, extra limbs,
distorted eyes, cropped ears or floating feet.
```

## 5. Seis bloques físicos y archivos

Los títulos e IDs son internos. Enviar a Higgsfield solo el bloque físico, sin
el título ni el ID.

### 5.1 `azulita.jpg`

```text
The single figure is an energetic young female puppy with a saturated vivid
sky-blue body, deep navy upright ears and rounded navy head and back patches,
pale-blue belly, cream rounded muzzle, small white eyebrow patches, black oval
nose, large clean oval brown eyes, compact child proportions and a joyful
confident smile. Her blue must read immediately as bold blue, never gray, teal,
lavender, white or washed out.
```

### 5.2 `chispa.jpg`

```text
The single figure is a smaller warm-orange female puppy sister with darker
burnt-orange upright ears and rounded head and back patches, cream face, muzzle
and belly, small dark-brown oval nose, large clean oval brown eyes, compact
younger-child proportions and a sweet curious happy expression.
```

### 5.3 `papa-marino.jpg`

```text
The single figure is a tall friendly adult father with deep navy-blue and
medium cobalt fur, pale-blue speckled patches across arms, torso and legs,
cream rounded muzzle and chest, black oval nose, large warm oval eyes, upright
ears, broad gentle adult proportions and a protective cheerful expression.
```

### 5.4 `mama-coral.jpg`

```text
The single figure is a graceful adult mother with rich coral-orange and
burnt-orange fur, subtle lighter speckled markings, cream rounded muzzle, chest
and belly, dark-brown oval nose, large warm oval eyes with gentle eyelashes,
upright ears, slender adult proportions and a caring joyful expression.
```

### 5.5 `manchita.jpg`

```text
The single figure is a friendly cream-colored young puppy with caramel-orange
upright ears, irregular caramel patches across the head, back and limbs, cream
muzzle and belly, dark-brown oval nose, large warm oval eyes, compact playful
proportions and a gentle enthusiastic smile.
```

### 5.6 `nube.jpg`

```text
The single figure is a playful charcoal-and-white speckled young cattle-dog
puppy with dark charcoal upright ears and rounded head patches, crisp irregular
black-and-gray mottling over a white body, white rounded muzzle and belly,
black oval nose, large warm oval eyes and a bright mischievous smile. Keep the
design clearly canine and consistent with the same rounded premium toy family.
```

Destino aprobado de JPG y MP4: la raíz del tema, igual que Carreras:

```text
public/themes/familia-canina/azulita.jpg
public/themes/familia-canina/chispa.jpg
public/themes/familia-canina/papa-marino.jpg
public/themes/familia-canina/mama-coral.jpg
public/themes/familia-canina/manchita.jpg
public/themes/familia-canina/nube.jpg

public/themes/familia-canina/saludo-azulita.mp4
public/themes/familia-canina/saludo-chispa.mp4
public/themes/familia-canina/saludo-papa-marino.mp4
public/themes/familia-canina/saludo-mama-coral.mp4
public/themes/familia-canina/saludo-manchita.mp4
public/themes/familia-canina/saludo-nube.mp4
```

`public/data/themes.json` no debe usar `characters/azulita.jpg` ni rutas
equivalentes. El backend actual deriva `<base>-cut.png` y
`saludo-<base>.mp4` desde la raíz de `themes/<slug>/`; por eso las seis entradas
`img` deben ser exactamente `azulita.jpg`, `chispa.jpg`, `papa-marino.jpg`,
`mama-coral.jpg`, `manchita.jpg` y `nube.jpg`.

## 6. Prompt común de animación

Usar cada JPG aprobado como `start_image`. Cotizar cada job por separado.

```text
Animate only the provided approved vertical 9:16 single-character start image
for 5 seconds at 720p. Preserve the exact single canine figure, every fur color
and patch, face, eyes, proportions and the complete approved playground party
background. Do not add, remove, duplicate or redesign the character or any
background object.

The character notices the camera, smiles, makes one small friendly paw wave,
takes one tiny step toward the lens and finishes in a cheerful stable pose as a
soft camera flash glows. Use a subtle cinematic push-in, gentle balloon sway
and minimal confetti drift. Keep all motion smooth and child-friendly.

The spoken Spanish line must be exactly: “¡Ahora nos tomaremos una foto!”
Use a warm original family-friendly Latin American Spanish voice, clear and
natural, without imitating any known performer or character voice. Keep speech
fully intelligible and synchronized with a small natural mouth movement.

No additional speech, names, words, captions, on-screen text, letters, numbers,
logos, watermarks, music lyrics, camera cuts, fast zooms, body warping, extra
limbs, new characters or background replacement. End facing the camera.
```

## 7. Contrato de audio

El MP4 final debe contener video H.264 y audio AAC. La frase exacta es:

```text
¡Ahora nos tomaremos una foto!
```

No confiar ciegamente en audio generativo:

1. si Higgsfield ofrece audio nativo cotizado, revisar que la frase sea exacta;
2. si pronuncia mal, agrega palabras o el modelo no soporta audio, conservar el
   video aprobado y sustituir únicamente la pista sonora;
3. crear una sola voz maestra original en español latino, no imitativa, y
   reutilizarla en los seis videos;
4. también se puede reutilizar una pista de Carreras solo si se transcribe y se
   confirma que contiene exactamente la frase, sin nombres, motores ni música
   temática;
5. normalizar la voz aproximadamente a -16 LUFS, evitar clipping y muxear AAC
   48 kHz, 128 kbps con `ffmpeg`;
6. comprobar con `ffprobe` que cada MP4 tiene stream `video` y stream `audio`;
7. probar en Chrome con sonido habilitado tras el toque inicial del kiosco.

El `<video>` de `VideoPersonaje` ya se reproduce sin `muted`; no agregar una
segunda reproducción paralela de la frase desde React.

## 7.1 Mensajes dinámicos iguales al flujo Carreras

Conservar el overlay React existente sobre cada saludo. No quemarlo dentro del
JPG ni del MP4:

```text
¡{NOMBRE_DEL_INVITADO}!
Prepárate para la foto con:
{PERSONAJE_ELEGIDO}
```

El nombre del invitado y el personaje provienen del estado actual de la fiesta
y de la ruleta. El audio fijo incorporado en cada MP4 dice únicamente:

```text
¡Ahora nos tomaremos una foto!
```

No reemplazar el overlay por texto generativo, no escribir esos mensajes en la
imagen y no cambiar un personaje después de la ruleta.

## 8. Recortes, ruleta, foto y diploma

Después de aprobar los seis JPG y seis MP4:

- crear `*-cut.png` transparente desde cada JPG aprobado;
- registrar seis entradas en `themes.json` con `img` y `png` correctos;
- usar `roulette-background-v1.png` como fondo de la ruleta;
- mantener el personaje seleccionado como única fuente de verdad;
- mostrar su MP4 y caer al JPG si falla;
- colocar su recorte centrado en la parte inferior de la foto compuesta;
- usar el mismo personaje en el diploma;
- no copiar PIN, galería, fotos ni invitados al duplicar una fiesta.

## 9. Gates y prohibiciones

- Máximo dos JPG antes de revisión visual.
- Máximo un MP4 antes de revisión audiovisual.
- Consultar costo/saldo antes de cada job y registrar costo real.
- No reintentar un job cobrado; recuperar el resultado.
- No enviar IDs internos ni nombres reservados al proveedor.
- No generar en lote, no cambiar Carreras y no iniciar Tema 03.
- Sin deploy, commit, push o merge.

## 10. Primera respuesta de OpenCode

Responder primero con:

1. ruta y rama;
2. ubicación y validación del video genérico ya generado;
3. modelo, saldo y costo del fondo común;
4. prompt escaneado;
5. confirmación de que creará primero solo `character-stage-v1.png`;
6. plan de audio elegido y prueba de que no duplicará la frase en React.
