# Corrección OpenCode — retratos individuales v2

Ticket: `AT-CUMPLECLICK-007`
Rama: `codex/cumpleclick-secure-db-frontend`
Estado: primer par rechazado; no animar ni crear recortes desde v1

## 1. Diagnóstico aprobado

Los archivos `azulita.jpg` y `chispa.jpg` v1 quedan rechazados porque:

- el personaje ocupa muy poco espacio y el fondo domina la imagen;
- el cartel crema y la mesa pertenecen a la invitación, no a un retrato de
  ruleta;
- las caras derivaron a cachorro genérico con ojos redondos brillantes;
- se perdieron los grandes ojos ovalados blancos, el hocico redondeado y las
  proporciones robustas del máster aprobado;
- la figura parece estar exhibida sobre una mesa, en vez de integrada en el
  parque;
- la resolución es 768×1344, no 1080×1920.

OpenCode ejecutó el prompt anterior de forma razonable. El error principal fue
el contrato de composición. No intentar arreglar v1 mediante animación,
upscale o recorte transparente.

## 2. Preservación de los rechazados

No borrar evidencia. Mover los archivos actuales a:

```text
storage/invitations/rejected/personajes-v1/azulita.jpg
storage/invitations/rejected/personajes-v1/chispa.jpg
storage/invitations/rejected/personajes-v1/character-stage-v1.png
```

No promoverlos a `dist`, no animarlos y no crear `*-cut.png` desde ellos.

## 3. Nuevo fondo bloqueado de retratos

Crear primero `portrait-stage-v2.png`, 1080×1920, y detenerse. Usar
`character-stage-v1.png` únicamente como referencia de paleta y parque, no como
composición que deba preservarse.

Prompt exacto:

```text
Create a premium reusable vertical 9:16 single-character portrait stage,
1080x1920, derived from the provided approved pastel playground birthday
reference. Preserve its bright warm cinematic daylight, soft realistic contact
shadows, gentle depth of field and saturated sky-blue, deep navy, warm orange,
coral, cream, pastel-pink, sunny-yellow and soft-green palette.

SCENE:
Show a cheerful children's playground with soft green grass, colorful play
structures in the middle distance, a few tasteful wrapped gifts at the far
lower corners, subtle confetti and small balloon clusters framing only the top
corners. Keep the central area open and uncluttered for one large full-body
figure standing directly on the grass. Use an eye-level to slightly low camera
with a 35mm lens feel.

COMPOSITION LOCK:
There must be no invitation sign, no blank panel, no banner, no table, no cake,
no platform, no pedestal and no furniture. The empty grass standing area must
extend from roughly 16% to 90% of the frame height. Preserve generous padding
around the future ears, paws and tail. Keep the background symmetric and
identical for all six portrait edits.

No characters, animals or humans. No text, letters, words, numbers, symbols,
logos, signatures, QR codes or watermarks. No flat 2D drawing, thick outlines,
sticker look, cheap plastic, low-poly geometry or dark dramatic lighting.
```

QA del fondo:

- PNG real 1080×1920;
- césped libre en el centro;
- sin cartel, mesa, torta ni pedestal;
- parque y globos reconocibles, pero subordinados;
- sin texto ni personaje.

## 4. Método obligatorio para cada personaje

Usar dos referencias en modo edición de alta fidelidad:

1. `public/themes/familia-canina/invitation/invitation-base-v1.png` como
   referencia de identidad, cara, ojos, cuerpo, materiales y colores;
2. `portrait-stage-v2.png` como fondo que debe permanecer bloqueado.

No usar `azulita.jpg` ni `chispa.jpg` v1 como referencia porque contienen la
deriva que se quiere corregir.

El personaje debe medir entre 68% y 74% del alto total del frame. La punta de
las orejas queda aproximadamente a 14–18% del borde superior y los pies quedan
aproximadamente a 88–91%. Debe estar de pie sobre el césped, nunca sobre una
mesa. Cuerpo completo, centrado y suficientemente grande para la ruleta y el
video de saludo.

## 5. Prompt v2 — primera protagonista azul

El título y filename son internos. Enviar a Higgsfield solo el bloque siguiente:

```text
Edit the provided approved vertical 9:16 empty playground portrait stage. Keep
the background composition locked: preserve the same balloons, playground,
grass, gifts, confetti, lighting, shadows, camera and depth of field. Do not add
a sign, panel, banner, table, cake, platform, pedestal or furniture.

Add exactly one large original full-body young female cattle-dog puppy standing
directly on the grass in the center. Match the approved family reference's
specific premium 3D collectible-toy facial language and body construction.

IDENTITY LOCK:
She has a saturated vivid sky-blue body, a broad deep-navy mask covering both
sides of the upper face, deep-navy upright triangular ears, a vertical sky-blue
stripe centered between the ears and eyes, a pale-blue oval belly, cream
rounded muzzle, two small white rounded eyebrow patches and a black oval nose.
Her eyes are two very large vertical WHITE oval eye shapes with small simple
black oval pupils. The white eye shapes are essential and clearly visible. Do
not use glossy spherical anime eyes, dark eye sockets or tiny round eyes.

Use a short wide rounded muzzle, broad rounded head, sturdy rounded rectangular
torso, short simple arms and legs, compact child proportions and a joyful
confident closed-mouth smile. She must read immediately as bold blue, never
gray, teal, lavender, white or washed out.

SCALE AND POSE:
The character fills approximately 70% of the frame height. Ear tips near 15%
from the top, feet near 90% from the top. Friendly three-quarter pose facing the
camera, one paw slightly raised, both feet planted on the grass, tail fully
visible, no cropping. Add one realistic soft contact shadow beneath her feet.

Premium photorealistic 3D collectible-toy rendering, smooth rounded geometry,
clean edges, soft matte-to-satin material, subtle ambient occlusion and the same
warm cinematic daylight as the approved family image.

Exactly one figure. No additional animals or humans. No text, letters, words,
numbers, symbols, logos, signatures, QR codes or watermarks. No generic kitten,
no fox proportions, no pointed muzzle, no glossy anime eyes, no oversized head
on a tiny body, no table, no pedestal, no floating feet, no flat 2D drawing,
thick outlines, cheap plastic, low-poly geometry, malformed paws or extra limbs.
```

Generar una sola candidata como
`storage/invitations/candidates/personajes-v2/azulita-v2.jpg` y detenerse. No
sobrescribir `public/themes/familia-canina/azulita.jpg` hasta aprobación.

## 6. Prompt v2 — hermana naranja

Ejecutar solo después de aprobar la candidata azul:

```text
Edit the provided approved vertical 9:16 empty playground portrait stage. Keep
the background composition locked: preserve the same balloons, playground,
grass, gifts, confetti, lighting, shadows, camera and depth of field. Do not add
a sign, panel, banner, table, cake, platform, pedestal or furniture.

Add exactly one large original full-body younger female cattle-dog puppy
standing directly on the grass in the center. Match the approved family
reference's specific premium 3D collectible-toy facial language and body
construction.

IDENTITY LOCK:
She has a saturated warm-orange body, darker burnt-orange upright triangular
ears and broad rounded patches on both sides of the upper face, a narrow cream
vertical stripe centered between the ears and eyes, a large cream oval belly,
cream short rounded muzzle, two small cream rounded eyebrow patches and a dark
brown oval nose. Her eyes are two very large vertical WHITE oval eye shapes
with small simple dark oval pupils. The white eye shapes are essential and
clearly visible. Do not use glossy spherical anime eyes, dark eye sockets or
tiny round eyes.

Use a short wide rounded muzzle, broad rounded head, sturdy rounded rectangular
torso, short simple arms and legs, compact younger-child proportions and a
sweet curious closed-mouth smile. She must read immediately as warm orange,
never brown, beige, red, pale yellow or washed out.

SCALE AND POSE:
The character fills approximately 68% of the frame height. Ear tips near 17%
from the top, feet near 90% from the top. Friendly three-quarter pose facing the
camera, one paw slightly raised, both feet planted on the grass, tail fully
visible, no cropping. Add one realistic soft contact shadow beneath her feet.

Premium photorealistic 3D collectible-toy rendering, smooth rounded geometry,
clean edges, soft matte-to-satin material, subtle ambient occlusion and the same
warm cinematic daylight as the approved family image.

Exactly one figure. No additional animals or humans. No text, letters, words,
numbers, symbols, logos, signatures, QR codes or watermarks. No generic kitten,
no fox proportions, no pointed muzzle, no glossy anime eyes, no oversized head
on a tiny body, no table, no pedestal, no floating feet, no flat 2D drawing,
thick outlines, cheap plastic, low-poly geometry, malformed paws or extra limbs.
```

Guardar como candidata privada `chispa-v2.jpg` y detenerse para QA.

## 7. Criterios de rechazo v2

Rechazar sin animar si ocurre cualquiera:

- resolución diferente de 1080×1920;
- personaje menor a 65% del alto;
- cartel, mesa, torta o pedestal visible;
- fondo distinto al `portrait-stage-v2.png`;
- ojos negros redondos sin grandes formas ovaladas blancas;
- hocico puntiagudo, cara de gato/zorro o cuerpo diminuto;
- colores apagados o incorrectos;
- patas flotando o figura recortada;
- texto, marca o personaje adicional.

No hacer upscale para salvar una mala generación. No animar hasta que la
identidad y la escala estén aprobadas.
