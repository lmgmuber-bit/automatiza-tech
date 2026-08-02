# OpenCode + DeepSeek — Gate B/C de Aventura Perruna

Ticket: `AT-CUMPLECLICK-007`
Ruta: `C:\wamp64\www\automatiza-tech\CumpleBooth`
Rama exclusiva: `codex/cumpleclick-secure-db-frontend`
Planner y revisor: Codex
Ejecutor: OpenCode
Modelo de ejecución seleccionado por Luis: DeepSeek V4 Flash
Gate visual, costo y aceptación final: Luis Miguel

> **ACTUALIZACIÓN VISUAL V1 APROBADA — 2026-07-17:** Gate B1 ya no está
> pendiente. Luis aprobó la invitación maestra de cuatro miembros y el fondo de
> ruleta con las dos hermanas. El contrato vigente que sustituye las
> instrucciones de regeneración y los prompts exploratorios de Gate B1 es
> `docs/OPENCODE-CONTINUE-FAMILIA-CANINA-VISUAL-V1.md`. No volver a ejecutar el
> prompt genérico de seis personajes de este documento. Los seis personajes se
> conservan como resultados de ruleta y assets individuales.

## 1. Estado recibido

Gate A está cerrado y aprobado. No reescribir PHP, migraciones, seguridad,
tokens, admin ni tests salvo que una prueba nueva demuestre una regresión real.
La matriz aprobada es:

- backend 72/72 en PHP 8.0/8.2/8.3/8.4;
- lint 35 archivos y 7 entrypoints;
- frontend 5/5;
- HTTP real 28/28 sin skips;
- paridad public→dist 53 archivos;
- `git diff --check` limpio.

El trabajo actual es producción visual secuencial de una sola temática. No
iniciar Tema 03, no generar en lote y no alterar Carreras.

## 2. Lectura obligatoria antes de cualquier llamada externa

Leer completos, en este orden:

1. `docs/CUMPLECLICK-HANDOFF-CODEX.md`
2. `docs/FASE-INVITACIONES-DINAMICAS.md`
3. `docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`
4. `docs/OPENCODEGO-INVITACIONES-EXECUTOR.md`
5. `docs/ARQUITECTURA.md`
6. `docs/FASE1.md`
7. `docs/PROMPTS-TEMATICAS.md`
8. `AGENTS.md`
9. `OPENCODE.md`
10. `Docs/ORCHESTRATION/AT-CUMPLECLICK-007.yaml` desde la raíz del monorepo.

No responder preguntando cuál es la tarea después de leer: está definida aquí.

## 3. Autoridad y límites

Luis autoriza expresamente el uso de créditos de **Higgsfield** para crear las
imágenes y darles movimiento, siempre de forma secuencial. Antes de cada job,
OpenCode debe
mostrarle:

- proveedor disponible;
- modelo exacto;
- dimensiones y relación;
- costo vigente de una generación;
- saldo disponible sanitizado;
- prompt principal y negative prompt después del escáner;
- ruta privada donde quedará la candidata.

La autorización permite consumir créditos, pero no permite lotes ni gasto
ciego: primero se informa costo/saldo del job concreto y luego se genera
**una sola candidata**. Después debe detenerse para QA. No
interpretar la aprobación de una imagen como autorización para una segunda,
una personalizada, un video o el lote del Photo Booth.

## 3.1 Ruleta y continuidad del personaje

La experiencia debe incluir una ruleta funcional con seis resultados internos:
`azulita`, `chispa`, `papa-marino`, `mama-coral`, `manchita` y `nube`. La ruleta
reutiliza el patrón funcional de la temática existente, pero esos nombres
internos y cualquier referencia histórica quedan fuera del payload enviado al
proveedor.

Para cada personaje deben existir y quedar conectados:

- segmento, color y thumbnail de ruleta;
- retrato vertical aprobado;
- recorte PNG transparente;
- saludo MP4 con fallback al retrato;
- aparición del personaje elegido en la parte inferior de la foto compuesta;
- fondo/identidad del diploma correspondiente al resultado de la ruleta.

El personaje seleccionado debe mantenerse como una sola fuente de verdad desde
la ruleta hasta cámara, Preview, QR y Diploma. No se permite elegir uno en la
ruleta y mostrar otro en la foto o diploma.

El contrato final contiene exactamente dos estados de imagen y dos de video:

1. imagen genérica maestra sin datos, reutilizable;
2. imagen personalizada regenerada cuando exista un pedido con datos reales;
3. video genérico derivado de la imagen maestra, con panel vacío para que la
   aplicación renderice encima los datos dinámicos;
4. video personalizado derivado de la imagen personalizada, conservando los
   datos reales ya impresos.

Los cuatro estados quedan documentados desde ahora, pero cada generación tiene
su propio preflight, aprobación de costo y revisión. En este turno se mantiene
autorizado únicamente el preflight de la primera imagen.

Prohibido:

- deploy, commit, push o merge;
- guardar claves, URLs firmadas o secretos;
- borrar o recrear BD;
- modificar archivos ajenos al ticket;
- generar más de un asset antes de revisión;
- aceptar presets automáticos que cambien la escena;
- reintentar automáticamente un job cobrado;
- usar nombres oficiales en prompts, negativos, metadatos o payloads.

## 4. Regla de camuflaje y escáner

Todo texto enviado a cualquier proveedor describe únicamente rasgos físicos,
colores, materiales, emociones, encuadre y escena. No enviar nombres de serie,
franquicia, estudio, canal, personajes, canciones, logos ni productos.

El escáner se ejecuta sobre:

- prompt principal;
- negative prompt;
- prompt de movimiento;
- nombre del job;
- metadatos y campos auxiliares enviados al proveedor.

Si encuentra un término reservado, detenerse y mostrar solo el nombre de la
regla interna que falló; no enviar el texto y no intentar evadir el filtro con
faltas ortográficas.

Nombres permitidos solo dentro del proyecto y nunca enviados al proveedor:
`familia-canina`, `Aventura Perruna`, `azulita`, `chispa`, `papa-marino`,
`mama-coral`, `manchita`, `nube`.

## 5. Flujo obligatorio y gates

### Gate B0 — preflight sin gasto

1. Confirmar ruta, rama y worktree; conservar cambios existentes.
2. Confirmar que Gate A sigue pasando sin reescribirlo.
3. Detectar el generador de imagen realmente disponible; no inventar tools ni
   modelos.
4. Ejecutar escáner de términos reservados.
5. Obtener costo/saldo sin generar.
6. Mostrar paquete de preflight y esperar aprobación escrita de Luis.

### Gate B1 — una imagen genérica

Tras aprobación del costo:

1. Generar exactamente una imagen vertical 9:16, objetivo 1080×1920.
2. Guardarla como candidata fuera del webroot, bajo el directorio privado
   configurado por `cb_invitation_dir()`; no inventar otra raíz.
3. No copiarla aún a `public/themes/familia-canina/`.
4. Verificar dimensiones, MIME, checksum y ausencia de texto.
5. Crear una vista previa/contact sheet local.
6. Entregar imagen, costo real, job ref sanitizado y QA; detenerse.

Solo si Luis la aprueba:

- promoverla a
  `public/themes/familia-canina/invitation/invitation-base-v1.png`;
- generar `invitation-thumb-v1.webp` 270×480;
- guardar checksum y congelar `visual_version=1` con manifiesto de paleta,
  personajes, proporciones, materiales, iluminación y decoración.

### Gate B2 — invitación personalizada

No inventar datos. Solicitar a Luis los cuatro valores reales:

- nombre del cumpleañero;
- fecha;
- hora;
- dirección.

Compilar el prompt y rechazar cualquier `[...]` pendiente. Usar la imagen base
aprobada como referencia. Generar una sola candidata personalizada, guardarla
fuera del webroot y comparar carácter por carácter. No promover ni publicar
sin aprobación.

### Gate C0 — video genérico reutilizable

Solo después de aprobar y promover la imagen genérica:

1. Usarla como `start_image`.
2. Consultar saldo y costo con `get_cost:true`.
3. Mostrar modelo, 5 s, 720p, 9:16 y costo; esperar aprobación.
4. Generar un solo `invitation-base-motion-v1.mp4`.
5. El panel crema debe permanecer completamente vacío. La aplicación será la
   responsable de superponer nombre, fecha/hora y dirección dinámicos.
6. Revisar frames inicial/medio/final y detenerse.

### Gate C1 — video personalizado del pedido real

Solo después de aprobar la personalizada:

1. Usarla como `start_image`; nunca usar la genérica.
2. Consultar saldo y costo con `get_cost:true`.
3. Mostrar modelo, 5 s, 720p, 9:16, costo y margen; esperar aprobación.
4. Generar un solo MP4.
5. Revisar frames inicial/medio/final, texto exacto, loop y estabilidad.
6. Detenerse antes de cualquier video adicional.

Parámetros iniciales conocidos: `kling3_0_turbo`, 5 segundos, 720p, 9:16,
sin audio. Si el proveedor ofrece otro modelo, no cambiar sin comparación y
cotización aprobada. Rechazar cualquier preset que sustituya la composición.

## 6. Prompt exacto — imagen genérica

Enviar literalmente el siguiente prompt después de superar el escáner:

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

Negative prompt exacto:

```text
official names, franchise names, studio names, logos, trademarks, exact branded
copy, text, letters, words, numbers, captions, symbols, watermark, signature,
QR code, gray main puppy, teal main puppy, lavender main puppy, washed-out blue,
wrong orange sister color, duplicated characters, extra puppies, missing family
member, flat 2D drawing, thick outlines, sticker look, cheap plastic, low-poly,
distorted eyes, malformed paws, extra limbs, scary expressions, cropped blank
panel, objects inside blank panel, cropped cake, blurry image, horizontal frame
```

## 7. Prompt exacto — personalización

Reemplazar los placeholders exclusivamente con datos validados por el admin.
No enviar este template sin compilar:

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

## 8. Prompts exactos — dos videos de invitación

### 8.1 Video genérico sin datos

Enviar solo después de aprobar la imagen genérica y su costo:

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

### 8.2 Video personalizado con datos reales

Enviar solo después de aprobar imagen personalizada y costo:

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

## 9. QA de la primera imagen

Rechazar la candidata si falla cualquiera:

- 9:16 real y resolución objetivo 1080×1920;
- exactamente seis personajes, sin duplicados ni ausentes;
- protagonista claramente azul y hermana claramente naranja;
- familia completa con diseños originales y consistentes;
- panel crema grande, centrado, vacío, recto y sin obstrucciones;
- ningún texto, letra, número, símbolo, logo, marca, firma o watermark;
- cake y personajes íntegros, sin recortes accidentales;
- patas, ojos, orejas y colas correctos;
- materiales 3D premium, no dibujo plano ni plástico barato;
- safe area suficiente para nombre, fecha/hora y dirección;
- composición infantil, luminosa y reutilizable en tablet 10 pulgadas.

No corregir defectos graves con software para hacer pasar una mala generación.
Reportar `REJECTED` y esperar decisión; no regenerar automáticamente.

## 10. Lo que sigue después del video

No ejecutarlo todavía. Tras aprobación de invitación y video, continuar
literalmente el Gate B/C del contrato principal:

1. `fondo-banner.jpg`;
2. `fondo-sala.jpg` y calibración de cajas;
3. `grupo-personajes.png`;
4. `azulita.jpg`, recorte y escena de video;
5. los otros cinco personajes, máximo dos antes de revisión;
6. bienvenida grupal y seis saludos, uno por gate;
7. integración Photo Booth y QA tablet 10 pulgadas.

Los prompts exactos de banner, sala, grupo, retrato, recorte, escena y saludos
están en las secciones 9.4–9.9 y 10.2–10.3 de
`docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`; copiarlos literalmente, no
resumirlos ni improvisarlos.

## 11. Primera respuesta obligatoria de OpenCode

Antes de modificar o generar, responder solo con:

1. ruta y rama confirmadas;
2. documentos leídos completos;
3. confirmación de que Gate A no se reabre;
4. proveedor y modelo de imagen realmente disponibles;
5. costo y saldo del preflight, sin generar;
6. resultado del escáner de prompts;
7. ruta privada exacta de la candidata;
8. confirmación de que generará como máximo una imagen después de aprobación;
9. confirmación de que el video genérico, la imagen personalizada, el video
   personalizado y el Photo Booth siguen bloqueados hasta sus gates propios.

Si no puede consultar costo/saldo sin generar, debe detenerse y decirlo. Nunca
simular una cotización ni afirmar que creó un asset sin evidencia verificable.
