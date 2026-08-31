# OpenCode Go — Tema 02: Familia Canina Azul/Naranja

Ticket: `AT-CUMPLECLICK-007`  
Referencia humana solicitada por Luis: temática de dos perritas hermanas
azul/naranja.  
Nombre público seguro: **Aventura Perruna**.  
Slug técnico: `familia-canina`.  
Planner/analista: Codex.  
Ejecutor: OpenCode Go.  
Gate final: Luis Miguel.

> **ESTADO 2026-07-17:** Gate A fue corregido por Claude y aprobado mediante
> revisión independiente de Codex. Luis también aprobó visual v1: invitación
> maestra de cuatro miembros y fondo de ruleta con las dos hermanas. El contrato
> operativo inmediato es
> `docs/OPENCODE-CONTINUE-FAMILIA-CANINA-VISUAL-V1.md`; sustituye los prompts
> exploratorios de la primera imagen. No reabrir Gate A ni regenerar los PNG
> aprobados. OpenCode continúa con un solo video genérico en Higgsfield y se
> detiene para QA.

## 1. Objetivo y límite

Construir una segunda temática completa siguiendo la estrategia ya validada
con Carreras:

```text
tarjeta de invitación → intro → invitados → bienvenida → ruleta → personaje
→ cámara → foto compuesta → QR → diploma
```

Este ticket termina cuando **Aventura Perruna** funciona de punta a punta y
Luis aprueba su evidencia en tablet de 10 pulgadas. No generar ni implementar
una tercera temática. Carreras debe seguir funcionando sin regresiones.

## 1.1 Flujo comercial canónico

### Primera entrega: invitación

1. Generar una imagen genérica de la temática, sin nombre, fecha, hora ni
   dirección.
2. Aprobar esa imagen con Luis y convertirla en la referencia visual canónica.
3. Generar desde ella un video genérico que conserve el panel completamente
   vacío. La aplicación coloca encima los datos dinámicos validados, igual que
   en el flujo existente de Carreras; el proveedor no escribe esos datos.
4. El administrador crea una invitación independiente y completa sus datos.
5. El sistema compila `invitation.personalize.v1`, reemplazando todos los
   placeholders con los valores reales del formulario.
6. Generar una segunda imagen personalizada usando la genérica como referencia.
7. Aprobar la coincidencia exacta de nombre, fecha/hora y dirección.
8. Generar un segundo video desde la imagen personalizada aprobada, preservando
   carácter por carácter los datos reales ya impresos.
9. Publicar un enlace opaco y revocable; el administrador comparte solo ese
   enlace por WhatsApp. La aplicación no envía ni adjunta archivos.

La invitación es la primera entrega incluida para todos los clientes que
contratan CumpleClick, pero es un módulo separado del kiosco: tiene datos,
outputs, publicación, revocación y enlace propios. No implementar un plan
`solo invitación` en este ticket.

### Segunda entrega: plataforma fotográfica

Después de aprobar la invitación genérica, usarla como **única fuente visual
maestra** de la plataforma. No diseñar el kiosco por separado. Extraer y fijar
de ella paleta, personajes, marcas físicas, proporciones, materiales,
iluminación, profundidad 3D, decoración y atmósfera; producir con esa línea el
banner, sala, ruleta, retratos, recortes, videos, foto y diploma. La invitación
personalizada es solo una instancia con datos y no debe convertirse en el
máster de otra fiesta. Completar entonces todo el flujo conocido de Carreras.

### Galería opcional

El photo booth puede capturar, subir y entregar cada foto mediante QR sin una
galería pública. La galería que reúne todas las fotos solo existe si el cliente
contrató `Full con galería` y el administrador activó el switch con PIN válido.

## 2. Lectura obligatoria

1. `docs/CUMPLECLICK-HANDOFF-CODEX.md` completo.
2. `docs/FASE-INVITACIONES-DINAMICAS.md` completo.
3. Este archivo completo.
4. `docs/ARQUITECTURA.md`, `docs/FASE1.md` y `docs/PROMPTS-TEMATICAS.md`.
5. `AGENTS.md`, `OPENCODE.md` y
   `Docs/ORCHESTRATION/AT-CUMPLECLICK-007.yaml`.
6. Vault canónico:
   `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\20-Shared-Memory\Invitaciones-AI-Prompts.md`,
   sección `Tematica perritas hermanas azul/naranja`.

## 3. Regla de prompts que no se rompe

En cualquier texto enviado a Gemini, Nano Banana, Higgsfield u otro proveedor:

- no escribir nombres oficiales de serie, personajes, estudio, productora,
  canal, logo, canción o franquicia;
- no pedir una copia exacta ni un estilo de un estudio;
- describir únicamente rasgos físicos, colores, materiales, personalidad y
  escena;
- el filtro se aplica también al negative prompt y a metadatos enviados al
  proveedor;
- ejecutar un escáner de términos reservados antes de cada llamada y detenerse
  si encuentra uno.

Los nombres oficiales pueden aparecer únicamente en la conversación humana o
en una nota privada no enviada al proveedor. No deben aparecer en `themes.json`,
el API público, nombres de archivos, prompts guardados ni assets generados.

## 4. Contrato del tema

Agregar a `public/data/themes.json` sin modificar los slugs existentes:

```json
{
  "nombre": "Aventura Perruna",
  "franquicia": "Familia Canina",
  "publico": "mixto",
  "diploma": "Amigo Oficial de la Familia Canina",
  "colors": {
    "accent": "#1689d8",
    "accentSoft": "#dff4ff",
    "yellow": "#ffd34e",
    "ink": "#17324d",
    "bgLight1": "#ecf9ff",
    "bgLight2": "#fff0d7",
    "dark1": "#0e5f9f",
    "dark2": "#123a64",
    "dark3": "#f28b39"
  },
  "confetti": ["#1689d8", "#75cfff", "#f28b39", "#ffd34e", "#ffffff", "#17324d"],
  "personajes": [
    {"emoji": "💙", "name": "Azulita", "img": "azulita.jpg"},
    {"emoji": "🧡", "name": "Chispa", "img": "chispa.jpg"},
    {"emoji": "🎸", "name": "Papá Marino", "img": "papa-marino.jpg"},
    {"emoji": "🌺", "name": "Mamá Coral", "img": "mama-coral.jpg"},
    {"emoji": "🐾", "name": "Manchita", "img": "manchita.jpg"},
    {"emoji": "☁️", "name": "Nube", "img": "nube.jpg"}
  ],
  "videos": {"welcome": "welcome-familia-canina.mp4"},
  "visualSource": {
    "kind": "invitation_generic",
    "version": 1,
    "asset": "invitation/invitation-base-v1.png"
  }
}
```

No copiar el `frameBox` numérico de Carreras. Generar el fondo, calibrar el
marco real con el admin y guardar el resultado normalizado. Lo mismo aplica a
las nuevas cajas opcionales de layout descritas en el paso 7.

`visualSource` es obligatorio para nuevas temáticas. Su checksum y el manifiesto
visual se guardan en la capa privada/BD, no en el payload público. Todo asset
derivado registra esa versión; cambiar la invitación maestra crea una versión
nueva y no altera fiestas publicadas sin migración administrativa explícita.

## 5. Datos obligatorios de la invitación

La tarjeta usa datos dinámicos y nunca los quema en la imagen genérica:

- nombre del cumpleañero o cumpleañera;
- fecha;
- hora;
- dirección.

Edad, salón/lugar, RSVP y mensaje son opcionales. Se permite guardar borrador,
pero se bloquean publicación, compartir, PNG y MP4 si falta un obligatorio.

Flujo de assets:

```text
imagen genérica sin datos
  → video genérico sin datos + overlay dinámico de la aplicación
  → formulario con datos reales
  → prompt personalizado compilado sin placeholders pendientes
  → segunda imagen personalizada y aprobada
  → segundo video personalizado desde la imagen personalizada
  → enlace opaco de descarga compartido manualmente
  → kit visual del photo booth derivado de la misma referencia
```

No regenerar la imagen ni el video genéricos por cada cliente. Ambos assets
aprobados se reutilizan con overlay dinámico de la aplicación. Cada pedido real
produce su propia segunda imagen personalizada y su segundo video personalizado.

## 6. Inventario final de assets

Directorio aprobado: `public/themes/familia-canina/`.

```text
fondo-banner.jpg
fondo-sala.jpg
grupo-personajes.png
musica-fondo.mp3
welcome-familia-canina.mp4

azulita.jpg                 azulita-cut.png                 azulita-patio.jpg
chispa.jpg                  chispa-cut.png                  chispa-patio.jpg
papa-marino.jpg             papa-marino-cut.png             papa-marino-patio.jpg
mama-coral.jpg              mama-coral-cut.png              mama-coral-patio.jpg
manchita.jpg                manchita-cut.png                manchita-patio.jpg
nube.jpg                    nube-cut.png                    nube-patio.jpg

saludo-azulita.mp4
saludo-chispa.mp4
saludo-papa-marino.mp4
saludo-mama-coral.mp4
saludo-manchita.mp4
saludo-nube.mp4

invitation/invitation-base-v1.png
invitation/invitation-thumb-v1.webp
```

Los candidatos no aprobados viven fuera del webroot. Solo promover assets
aprobados, con checksum y metadatos. `musica-fondo.mp3` debe ser propia,
generada o con licencia documentada; no usar banda sonora oficial.

Las imágenes y videos personalizados no pertenecen a `public/themes/`. Se
guardan fuera del webroot como `cc_invitation_outputs` y se entregan solamente
mediante `descargar-invitacion.php?t=<token>` después de ser aprobados.

## 7. Cambios reutilizables que OpenCode debe implementar

No resolver el tema con condiciones hardcodeadas como
`THEME_SLUG === 'familia-canina'`. Generalizar una vez:

1. `theme.videos.welcome`: sustituye el hardcode actual exclusivo de Carreras.
   Si falta o falla, conservar el video genérico.
2. `theme.layout.introTitleBox`: geometría normalizada para el saludo de Intro.
3. `theme.layout.introCakeNameBox`: geometría normalizada para el nombre sobre
   la torta o placa del fondo.
4. `theme.layout.photoCharacterBox`: zona normalizada donde se apoya el
   personaje seleccionado dentro de la foto final.
5. Mantener `party.frameBox` como fuente exacta del marco de cámara.
6. Backend: normalizar y validar todas las cajas; valores inválidos caen al
   default actual de Carreras sin romper compatibilidad.
7. Admin: calibradores visuales separados para marco de cámara, saludo Intro,
   nombre de torta y personaje sobre el suelo.
8. Inventario admin: mostrar banner, sala, grupo, música, invitación,
   thumbnails, seis retratos, seis recortes, seis escenas y siete videos.
9. Prompts privados: asociar prompt principal, negative prompt, modelo,
   proveedor, revisión y checksum a cada asset; nunca exponerlos en API.
10. Implementar la infraestructura mínima reutilizable de `Invitaciones`
    descrita en `FASE-INVITACIONES-DINAMICAS.md`, usando este tema como piloto.
11. Agregar `service_plan` y `gallery_enabled` mediante migración reversible.
12. Desacoplar la galería del PIN: el PIN autentica, el booleano habilita.
13. `galeria.php` devuelve 404 segura si la función no fue contratada/activada.
14. Mantener QR y foto individual operativos para planes `booth` y `full`.
15. La invitación tiene entidad, outputs y token propios; `party_id` es nullable.
16. `Nueva fiesta desde esta temática` reutiliza los assets, crea nuevo slug y
    enlace de kiosco, y no copia PIN, galería, invitados, fotos ni invitaciones.
17. El enlace de invitación y el enlace de Photo Booth nunca son intercambiables.
18. Al aprobar la invitación genérica, congelar un manifiesto visual versionado
    con paleta, personajes, proporciones, materiales, iluminación, motivos y
    exclusiones; asociarlo a cada asset derivado del Photo Booth.
19. Admin: mostrar la línea de origen `invitación maestra → assets derivados`
    y marcar como `visual_drift` cualquier candidato que no la respete.

La API pública mantiene compatibilidad. Los campos nuevos son opcionales y el
frontend debe degradar correctamente cuando una temática antigua no los tenga.

## 8. Personajes originales y color lock

Usar estos bloques físicos en prompts. Los nombres de la izquierda son solo
claves internas del proyecto; el proveedor recibe únicamente la descripción:

| Clave | Descripción física para el proveedor |
|---|---|
| `azulita` | energetic young female cattle-dog puppy, saturated sky-blue body, deep navy ears and rounded head patches, light-blue belly, cream muzzle, small white eyebrow patches, black oval nose, large warm oval eyes, unmistakably blue |
| `chispa` | smaller cheerful female cattle-dog puppy, warm orange body, darker orange ears and head patches, cream face, muzzle and belly, small brown nose, tall rounded ears, compact proportions, sweet curious expression |
| `papa-marino` | tall playful adult male cattle-dog, deep navy and medium-blue speckled coat, cream muzzle and chest, broad friendly face, relaxed ears, warm brown eyes, humorous fatherly expression |
| `mama-coral` | graceful adult female cattle-dog, coral-orange and warm tan speckled coat, cream muzzle and chest, soft upright ears, kind oval eyes, calm caring expression |
| `manchita` | adventurous young puppy with cream coat, caramel-brown ear and eye patches, short rounded ears, bright hazel eyes, compact agile body, joyful grin |
| `nube` | musical young puppy with charcoal-gray and white speckled coat, white muzzle and chest, one dark ear and one light ear, large dark eyes, gentle playful smile |

Todos deben ser diseños originales, con proporciones y marcas distintas entre
sí. No agregar ropa, insignias ni objetos que copien diseños oficiales.

## 9. Prompts de imagen

### 9.1 Invitación base — prompt principal

```text
Create an original premium children's birthday invitation background,
vertical 9:16, 1080x1920, high-end photorealistic 3D collectible-toy scene.
No humans and no phone.

SCENE:
A bright Australian-suburban-inspired backyard playground decorated for a
birthday party, with pastel sky-blue, navy, warm orange, cream and sunny-yellow
balloon arches, soft green grass, a small playhouse, wrapped gifts, confetti,
cupcakes and a central multi-tier cake. Warm daylight, soft contact shadows,
clean cinematic depth and joyful family energy.

CHARACTERS:
Six completely original anthropomorphic cattle-dog family and friend figures.
The main protagonist is an energetic young female puppy with a saturated
sky-blue body, deep navy ears and rounded head patches, light-blue belly,
cream muzzle, small white eyebrow patches, black oval nose and large warm oval
eyes. She must read immediately as vivid blue, never gray, teal or lavender.
Beside her is a smaller warm-orange female puppy sister with darker orange
ears and head patches, cream face and belly, compact proportions and a sweet
curious expression. Behind them are a tall navy-blue speckled father, a
graceful coral-orange speckled mother, a cream puppy with caramel patches and a
charcoal-white speckled puppy. Every design must be original and distinct.

LAYOUT:
Reserve a large centered completely blank cream invitation panel in the upper
third. It must be fully visible, evenly lit and unobstructed, with generous
inner margins for later personalized Spanish text. Keep the family, cake and
gifts in the lower half without covering the panel. Balanced symmetrical
composition, premium matte-to-satin materials, rounded clean geometry.

The panel and the whole image must contain absolutely no text, letters,
numbers, symbols, logos, brands, signatures or watermark. Do not reproduce
official characters, costumes, marks or a known poster composition.
```

### 9.2 Invitación personalizada — prompt con datos reales

Guardar este texto como `invitation.personalize.v1`. El admin ve tanto la
plantilla con placeholders como el prompt compilado y puede copiarlo. No
enviar a ningún proveedor si queda un `[...]` sin resolver.

```text
Edit the provided approved generic vertical 9:16 invitation image.

PRESERVATION LOCK:
Preserve exactly the six original canine characters, their blue, navy, orange,
coral, cream and charcoal-white colors, faces, markings, proportions, poses,
the cake, balloons, gifts, background, lighting, camera angle, framing, blank
cream panel and overall composition. Do not add, remove, duplicate or redesign
any character or prop.

TEXT TASK:
Place only this exact Spanish text inside the blank cream panel, centered,
sharp, perfectly legible and correctly accented:

"¡[NOMBRE_DEL_CUMPLEAÑERO] está de cumpleaños!"
"[FECHA_Y_HORA]"
"[DIRECCIÓN]"

Add these lines only when their values were supplied:
"Cumple [EDAD] años"
"[LUGAR]"
"[MENSAJE]"

TYPOGRAPHY:
Use Baloo 2 or a similar rounded friendly font, dark navy main text, warm-orange
accents, strong contrast, balanced line breaks and generous safe margins.

EXACTNESS:
Do not translate, paraphrase, autocorrect, invent, omit, abbreviate or duplicate
any supplied data. Do not create extra words, labels, logos, watermarks, QR
codes, signatures, badges, brand marks or official names.
```

Antes de aprobar, comparar el texto visible con el formulario carácter por
carácter, incluyendo tildes, `ñ`, números, hora y puntuación. El renderer por
software queda solo como fallback de recuperación si Luis lo autoriza.

### 9.3 Negative prompt común

```text
official names, franchise names, studio names, logos, trademarks, exact branded
copy, text, letters, words, numbers, captions, symbols, watermark, signature,
QR code, gray main puppy, teal main puppy, lavender main puppy, washed-out blue,
wrong orange sister color, duplicated characters, extra puppies, missing family
member, flat 2D drawing, thick outlines, sticker look, cheap plastic, low-poly,
distorted eyes, malformed paws, extra limbs, scary expressions, cropped blank
panel, objects inside blank panel, cropped cake, blurry image, horizontal frame
```

### 9.4 `fondo-banner.jpg`

```text
Create a premium vertical 9:16, 1080x1920 birthday welcome background in a
bright original canine-family playroom opening into a sunny backyard. Use
sky-blue, navy, warm orange, cream and yellow decorations, balloon garlands,
paper paw shapes without letters, gifts, confetti and six original speckled
cattle-dog family figures arranged near the lower half around a cake.

Place a large clean blank framed panel in the upper-middle for a later software
welcome message, plus a separate wide blank cream plaque on the front of the
cake for the birthday child's name. Both areas must be unobstructed and have
simple straight geometry that can be calibrated. No text, letters, numbers,
logos, brands or watermark anywhere. Premium cinematic 3D collectible-toy
finish, warm daylight, exact vertical composition.
```

### 9.5 `fondo-sala.jpg`

```text
Create a premium vertical 9:16, 1080x1920 photo-booth room for an original
canine-family birthday. Bright modern playroom and backyard atmosphere,
sky-blue and orange balloon arch, cream walls, soft wood and green play-mat
floor, gifts and subtle confetti.

In the upper-middle place one large, perfectly square decorative photo frame
facing the camera, with a clearly empty dark-neutral opening and a visible
cream-and-gold border. Keep the entire square border unobstructed. Below the
frame, reserve a clean central landing area on the floor where software will
place one selected puppy character. Do not place a character in that landing
area. Leave safe space for a short thank-you line. No text, letters, numbers,
logos, brands or watermark. Straight-on camera, no perspective distortion,
premium photorealistic 3D party-room quality.
```

### 9.6 `grupo-personajes.png`

```text
Create a high-resolution transparent PNG group of exactly six completely
original anthropomorphic cattle-dog family and friend characters: vivid
sky-blue young female puppy, smaller warm-orange sister puppy, tall navy-blue
speckled father, coral-orange speckled mother, cream puppy with caramel patches,
and charcoal-white speckled puppy. Arrange them in a friendly shallow arc,
full bodies visible, looking toward camera, natural contact positions, matching
premium 3D collectible-toy lighting. True transparent alpha, no background,
no floor rectangle, no text, no numbers, no logos, no watermark, no cropping.
```

### 9.7 Retrato individual — plantilla

Generar uno por cada bloque físico del paso 8:

```text
Premium photorealistic 3D collectible-toy character portrait, vertical 9:16.
[CHARACTER_PHYSICAL_BLOCK] fills the lower-middle foreground, full body visible,
looking warmly toward camera with a joyful birthday expression. Behind the
character, a softly blurred original canine-family backyard birthday with
sky-blue and orange balloons, gifts, cake, playhouse and confetti. Warm daylight,
clean rounded geometry, matte-to-satin materials, realistic contact shadow,
sharp character focus. Completely original design. No text, letters, words,
numbers, logos, brands or watermark.
```

### 9.8 Recorte transparente — plantilla

```text
Using the provided approved character portrait, isolate only the single puppy
character. Preserve every ear, paw, tail, fur-color patch, eye and facial
feature. Remove the entire background cleanly and export a high-resolution PNG
with true transparent alpha. Do not redesign the character, add a floor,
external shadow, text, logo or watermark, and do not crop any body part.
```

### 9.9 Escena de inicio para video — plantilla

```text
Create a vertical 9:16 cinematic start frame using the exact approved character
design from the provided reference. The single character stands full-body on a
sunny backyard party play-mat, centered and looking at camera, with softly
blurred sky-blue and orange balloons, playhouse, gifts and confetti behind it.
Preserve exact fur colors, patches, face, proportions and material. Leave room
around ears, paws and tail for gentle animation. No text, numbers, logos,
watermark or new characters.
```

## 10. Prompts Higgsfield

### 10.1 Invitación animada

#### 10.1.a Video genérico reutilizable, sin datos

```text
Animate only the provided approved generic vertical 9:16 invitation start image
for 5 seconds at 720p. Preserve exactly the six original canine characters,
their fur colors and patches, cake, gifts, backyard, palette, camera and
composition. Keep the cream invitation panel in the upper third completely
blank, stable, sharp, unobstructed and evenly lit for a separate application
overlay. The video itself must contain absolutely no text, letters, numbers,
symbols, logos, watermarks, signatures or QR codes.

Use a very slow cinematic push-in and subtle layered parallax. Balloons sway
gently, a few pieces of confetti drift, candle flames flicker and the puppies
blink or make tiny friendly movements. Do not add, remove, duplicate or
redesign characters. Do not create camera cuts, lip-sync, fast zooms, warping,
flicker or new objects. End close to the starting pose for a clean loop.
```

#### 10.1.b Video personalizado con datos reales

```text
Animate only the provided approved personalized vertical 9:16 invitation start
image for 5 seconds at 720p. Preserve the exact six original canine characters,
their fur colors and patches, cake, gifts, backyard, palette, camera and
composition. Preserve every visible Spanish letter, accent, number, line break
and punctuation exactly as shown; never rewrite or regenerate the text.
Use a very slow cinematic push-in and subtle layered parallax. Balloons sway
gently, a few pieces of confetti drift, candle flames flicker and the puppies
blink or make tiny friendly movements.

The cream invitation panel in the upper third must remain perfectly stable,
sharp, unobstructed and evenly lit. Do not add, remove or redesign characters.
Do not create additional text, letters, numbers, logos, watermarks, camera cuts,
lip-sync, fast zooms, warping, flicker or new objects.
End close to the starting pose for a clean loop.
```

### 10.2 Bienvenida grupal

```text
Animate the provided approved vertical 9:16 group welcome scene for 5 seconds
at 720p. Preserve exactly the six original canine family characters and all
fur colors, patches, faces and proportions. The vivid blue older puppy and the
smaller orange sister take one small happy step forward; the family behind them
blinks, smiles, gently wags tails and gives tiny paw waves. Balloons sway and a
little confetti drifts. Stable camera with a subtle push-in, warm playful family
energy. No speech or lip-sync, no text, no names, no logos, no new characters,
no design changes, no fast motion, no camera cuts. Finish facing the camera.
```

### 10.3 Saludo individual — plantilla

```text
Animate only the provided approved vertical 9:16 start image for 5 seconds at
720p. Preserve the exact single original puppy character, fur colors, patches,
face, eyes, proportions and backyard party composition. The character blinks,
gently wags its tail, makes one small joyful bounce and a subtle friendly paw
wave toward the camera, then settles into the original pose with a warm smile.
Add only slight balloon sway and drifting confetti. Stable camera, subtle
cinematic push-in, child-friendly motion. No speech, lip-sync, text, letters,
numbers, logos, watermark, new characters, redesign, fast zoom, cuts, warping
or extra limbs.
```

## 11. Orden de producción y gates

### Gate A — infraestructura sin gasto

1. Crear migraciones, repositorios y admin de invitaciones independientes,
   outputs, planes y asociación opcional a fiesta.
2. Generalizar layout y video de bienvenida por configuración.
3. Implementar prompt con placeholders, compilador estricto, carga/aprobación
   de outputs y enlace opaco/revocable de descarga.
4. Implementar `service_plan` y el switch explícito de galería.
5. Implementar `visualSource`, manifiesto versionado, linaje por asset y estado
   `visual_drift` sin generar todavía ningún asset externo.
6. Agregar el tema con placeholders locales no desplegables.
7. Ejecutar pruebas y revisar diff antes de generar.

### Gate B — imágenes

1. Generar solo `invitation-base-v1.png` y pedir aprobación de Luis.
2. Congelar paleta, biblia de personajes, materiales, iluminación, decoración,
   checksum y `visual_version=1` antes de crear cualquier asset del kiosco.
3. Compilar el prompt con datos de prueba aprobados, generar una segunda imagen
   personalizada, verificar texto carácter por carácter y aprobarla.
4. Generar `fondo-banner.jpg` y `fondo-sala.jpg` usando la invitación genérica
   como referencia obligatoria; calibrar cajas y aprobar.
5. Generar `grupo-personajes.png` desde la misma referencia y manifiesto.
6. Generar primero `azulita.jpg`, su recorte y su escena de video; aprobar el
   color lock azul.
7. Repetir personaje por personaje, nunca más de dos sin revisión.

### Gate C — videos

1. Consultar saldo y cotizar cada video con `get_cost:true`.
2. Tras aprobar la imagen genérica, generar primero
   `invitation-base-motion-v1.mp4` desde ella. El panel debe seguir vacío para
   que la aplicación renderice encima los datos dinámicos.
3. Cuando exista un pedido real y se apruebe su segunda imagen personalizada,
   generar `invitation-motion-v1.mp4` desde ella y comprobar que conserva el
   texto exacto.
4. Generar `welcome-familia-canina.mp4` y aprobar.
5. Generar primero `saludo-azulita.mp4`; luego los otros cinco de uno en uno.
6. Si un job ya fue cobrado, recuperar el resultado antes de reintentar.

Referencia histórica: ocho videos de 5 s a 7,5 créditos serían cerca de 60
créditos. Exigir al menos 72 créditos disponibles con el margen del 20%, pero
usar siempre la cotización vigente. No generar con el último saldo documentado
de ~9 créditos.

## 12. QA obligatorio

### Invitación

- nombre, fecha, hora y dirección obligatorios y exactos;
- datos largos, acentos y caracteres chilenos;
- existen dos imágenes distinguibles: genérica sin datos y personalizada;
- existen dos videos distinguibles: genérico sin datos para overlay dinámico y
  personalizado con datos reales impresos;
- el compilador reemplaza todos los placeholders y bloquea cualquier `[...]`;
- la imagen personalizada coincide carácter por carácter con el formulario;
- el MP4 genérico mantiene el panel vacío y la aplicación alinea el overlay;
- el MP4 personalizado parte de la personalizada y conserva su texto exacto;
- token público revocable, prompts privados y rutas físicas no expuestos;
- el admin copia solo el enlace opaco; no hay Web Share, adjunto ni envío;
- descargar el enlace entrega exclusivamente outputs aprobados.

### Plan contratado y galería

- `booth`: invitación + experiencia fotográfica + QR individual; sin listado
  de galería;
- `full`: lo anterior y posibilidad de activar galería con PIN;
- PIN presente con `gallery_enabled=false` no habilita el listado;
- `gallery_enabled=true` con plan distinto de `full` debe rechazarse;
- cambiar `full` a otro plan desactiva galería y preserva fotos para rollback.

### Kiosco completo

- cada asset declara `visual_version=1` y deriva de la invitación genérica;
- paleta, personajes, proporciones, materiales, iluminación y decoración son
  coherentes desde Intro hasta Diploma, sin `visual_drift`;
- intro con saludo dentro de su caja y nombre centrado en placa;
- grupo visible como watermark sutil;
- invitados → bienvenida temática/fallback → toque para ruleta;
- seis personajes y distribución correcta en ruleta;
- cada personaje usa video si existe y JPG como fallback;
- cámara física abre y se apaga al abandonar Capture;
- foto cuadrada alineada exactamente con el marco;
- ganador aparece apoyado dentro de `photoCharacterBox`, no flotando ni a un
  costado;
- QR real solo después de subida exitosa; descarga local en error;
- diploma usa el retrato del personaje ganador como fondo y textos legibles;
- consola y red sin errores inesperados.

### Responsive y seguridad

- viewports 600×960, 768×1024, 800×1280, 900×1440 y 1280×800;
- prueba principal en tablet física de 10 pulgadas, portrait y landscape;
- controles táctiles mínimos 44×44 CSS px;
- PHP 8.2/8.3/8.4, auth, CSRF, XSS, SQLi, path traversal, MIME, cuotas,
  publicación/revocación y rollback.

## 13. Cierre requerido

Entregar:

- matriz de assets con `missing/candidate/approved/rejected`;
- contact sheet y checksums de imágenes;
- filmstrip inicial/medio/final de cada video y costo real;
- capturas de invitación, intro, ruleta, cámara, Preview, QR y Diploma;
- resultado en tablet de 10 pulgadas;
- pruebas, diff, Graphify, documentación y manifest FTP exacto;
- ningún deploy, push o merge sin autorización.

Después del QA, detenerse en `AWAITING_LUIS_ACCEPTANCE`. Solo Luis decide cuál
será el Tema 03.
