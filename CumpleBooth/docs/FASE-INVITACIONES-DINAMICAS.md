# AT-CUMPLECLICK-006 — Invitaciones dinámicas por temática

Estado: `PLAN_READY / AWAITING_HUMAN_GATE`  
Fecha: 2026-07-15  
Planner y analista: Codex  
Ejecutor: OpenCode Go  
Revisor técnico: Codex  
Gate final y autorización de gasto/deploy: Luis Miguel

## 1. Resultado de producto

CumpleClick incorporará dos módulos relacionados por la temática, pero con
ciclos de vida y enlaces independientes. Cada temática se completa de punta a
punta antes de comenzar la siguiente.

### Fase 1 — invitación entregable al cliente

1. Se genera y aprueba una imagen genérica 9:16 de la temática, sin datos.
2. El administrador crea una invitación independiente y completa nombre del
   cumpleañero, fecha, hora y dirección.
3. CumpleClick compila el prompt privado reemplazando los placeholders por los
   datos reales, sin dejar variables pendientes.
4. A partir de la imagen genérica se genera una segunda imagen personalizada.
5. Desde la imagen personalizada aprobada se puede generar el video, conservando
   exactamente su composición y texto.
6. El administrador aprueba los resultados y publica un enlace opaco de
   descarga; comparte solo ese enlace con el cliente, por ejemplo por WhatsApp.

La invitación está incluida como primera entrega de CumpleClick; no se define
en esta fase como un plan comercial independiente.

### Fase 2 — experiencia fotográfica CumpleClick

La imagen genérica aprobada es el **máster visual obligatorio del tema**. El
Photo Booth no se diseña en paralelo ni desde cero: desde ese máster se extraen
y bloquean paleta, personajes, proporciones, materiales, iluminación, lenguaje
de formas, decoración y atmósfera. Con esa misma línea se producen portada,
sala/marco, grupo, personajes, recortes, videos, música autorizada, foto
compuesta y diploma. Después se valida el recorrido completo del kiosco igual
que en Carreras.

La imagen personalizada no reemplaza al máster: es una instancia derivada con
datos de un cliente. Así, nuevas fiestas reutilizan la identidad visual sin
heredar nombres, fechas o direcciones anteriores.

### Servicio opcional — galería de la fiesta

La captura, descarga individual y QR pertenecen al photo booth. La galería que
lista todas las fotos solo se activa cuando el cliente contrató el plan `full`
y el administrador la habilitó explícitamente. Tener PIN o fotos almacenadas
no debe activarla automáticamente.

Cada temática tendrá exactamente dos estados de imagen de invitación:

1. **genérica**: asset reutilizable 9:16, con panel limpio y sin datos;
2. **personalizada**: resultado generado desde la genérica con los datos reales
   compilados en el prompt y aprobado expresamente por el administrador.

La invitación no es la pantalla de entrada ni una ruta del kiosco. Puede
asociarse opcionalmente a una fiesta para comodidad administrativa, pero se
publica, revoca y retiene con su propio enlace de descarga.

La opción `Tarjeta de invitación` tendrá cuatro datos mínimos obligatorios:

1. nombre del cumpleañero o cumpleañera;
2. fecha del cumpleaños o evento;
3. hora de inicio;
4. dirección donde se realizará.

Se permite guardar un borrador incompleto, pero no previsualizar como pieza
final, publicar, compartir ni exportar mientras falte alguno de esos cuatro
datos. Edad, nombre del salón/lugar, RSVP y mensaje adicional son opcionales.

El formulario compila el segundo prompt usando, como mínimo,
`[NOMBRE_DEL_CUMPLEAÑERO]`, `[FECHA_Y_HORA]` y `[DIRECCIÓN]`. La interfaz debe
mostrar el prompt compilado para copiarlo y permitir subir el resultado creado
manualmente por Luis/OpenCode/Higgsfield. Una integración directa con proveedor
es opcional y siempre queda detrás del gate de costo. Si el modelo altera una
letra, inventa datos o deja un placeholder, el resultado no se puede publicar.
Un overlay determinista por software queda solo como recuperación opcional,
previa decisión de Luis; no es el flujo principal de esta fase.

## 2. Ownership y clasificación

- Ticket: `AT-CUMPLECLICK-006`.
- Clase: C3.
- Riesgo: alto (datos de menores, administración, BD, assets externos y gasto
  de créditos).
- Codex define arquitectura, contratos, prompts, criterios de aceptación y
  revisa la ejecución.
- OpenCode Go implementa PHP/BD/admin/render, prepara/genera los assets y deja
  evidencia reproducible.
- Luis aprueba el plan, el presupuesto antes de consumir créditos, cada lote
  visual y cualquier commit, push o deploy.
- No existe comunicación automática entre chats externos. El contrato
  operativo de OpenCode Go es `docs/OPENCODEGO-INVITACIONES-EXECUTOR.md`.

## 3. Catálogo inicial de 15 temáticas

Los 10 slugs existentes se conservan para no romper fiestas ni URLs. Los cinco
nuevos se añaden al final. Los nombres de referencia protegidos pueden existir
solo como metadato privado administrativo; nunca entran en prompts generativos,
archivos públicos, logos ni texto visible generado.

| # | Slug | Nombre público genérico | Estado | Paleta base | Escena central |
|---|---|---|---|---|---|
| 1 | `carreras` | Carreras veloces | Existente / piloto video | rojo, amarillo, negro | pista festiva, autos de juguete |
| 2 | `mickey` | Casa divertida | Existente | rojo, amarillo, negro, rosa | casa club y amigos ratones originales |
| 3 | `hielo` | Reino de hielo | Existente / video lote 1 | celeste, blanco, lavanda | palacio de hielo y amigos invernales |
| 4 | `cachorros` | Equipo de rescate | Existente / video lote 1 | azul, rojo, amarillo | torre de rescate y cachorros originales |
| 5 | `heroes` | Súper héroes | Existente | azul, rojo, dorado, violeta | ciudad y equipo heroico infantil |
| 6 | `princesas` | Princesas de cuento | Existente | rosa, lavanda, dorado | salón real y princesas originales |
| 7 | `dinos` | Aventura jurásica | Existente / video lote 1 | verde, naranja, turquesa | selva volcánica con dinosaurios bebés |
| 8 | `sirenas` | Reino bajo el mar | Existente | aqua, coral, violeta | palacio submarino y sirenas originales |
| 9 | `juguetes` | Mundo de juguetes | Existente | azul, amarillo, rojo | dormitorio festivo y juguetes originales |
| 10 | `tropical` | Aventura tropical | Existente | turquesa, coral, verde | playa hawaiana y criatura fantástica original |
| 11 | `arcade` | Mundo arcade | Nuevo / video lote 1 | rojo, verde, azul cielo, dorado | plataformas, tuberías y aventureros chibi originales |
| 12 | `bloques` | Mundo de bloques | Nuevo | verde, celeste, marrón | paisaje voxel con constructores originales |
| 13 | `criaturas` | Criaturas mágicas | Nuevo | amarillo, aqua, verde, violeta | jardín mágico y compañeros elementales originales |
| 14 | `guerreros` | Guerreros de energía | Nuevo | naranja, azul, dorado | valle rocoso y artistas marciales de energía |
| 15 | `familia-canina` | Aventura perruna | Nuevo / prioridad operativa 2 | azul, naranja, crema, amarillo | familia canina original en patio de juegos |

La selección combina el catálogo que CumpleClick ya posee con categorías que
se repiten en referencias de invitaciones y tendencias de fiestas: carreras,
dinosaurios, princesas, sirenas, superhéroes y gaming continúan siendo
familias de alta demanda. No se reemplaza un tema existente sin datos reales
de uso de CumpleClick.

Fuentes de señal, no equivalentes a una estadística específica de Chile:

- [Instant Party Kit — temas infantiles 2026](https://instantpartykit.com/blog/50-best-kids-birthday-party-themes-2026/)
- [Party Baby Essentials — tendencias 2026](https://partybabyessentials.com/blogs/party-baby-essentials/kids-birthday-party-trends-2026)
- [Pinterest Predicts 2025](https://newsroom.pinterest.com/news/pinterest-predicts-20-bold-trends-for-2025/)

La priorización definitiva debe recalibrarse cuando CumpleClick tenga datos
propios de consultas, ventas y uso por temática.

## 4. Estrategia secuencial por temática

Luis reemplazó la producción por lotes por verticales completos, uno a uno:

1. Tema 01 `carreras`: referencia ya implementada.
2. Tema 02 `familia-canina`: invitación, intro, ruleta, personajes, cámara,
   foto, QR y diploma. Contrato en
   `docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`.
3. Tema 03 y siguientes: Luis los elige únicamente después de aprobar el tema
   anterior.

La meta continúa siendo 15 temáticas activas. `fashion` pasa al backlog de
candidatos y no se elimina ningún asset existente. No se comienza una tercera
temática mientras el Tema 02 siga sin aceptación visual.

## 5. Contrato visual de la invitación

### 5.1 Formato maestro

- Relación: 9:16.
- Imagen maestra: 1080×1920 PNG o WebP de alta calidad.
- Video maestro: 720×1280 MP4 H.264, 5–7 segundos, sin audio y apto para loop.
- Miniatura: 270×480 WebP.
- Área de texto: coordenadas normalizadas `{x,y,w,h}` relativas a 1080×1920.
- Ningún fondo base contiene nombre, edad, fecha, hora, dirección, RSVP,
  letras, números, logos, marcas ni watermark generado.
- El logo AT real se agrega como overlay del sistema, discreto, fuera de la
  generación y según las reglas de marca vigentes.

### 5.2 Archivos por temática

```text
public/themes/<slug>/invitation/
  invitation-base-v1.png
  invitation-thumb-v1.webp
  invitation-motion-v1.mp4        # solo lote de video
```

Los candidatos todavía no aprobados viven fuera del webroot. OpenCode Go solo
promueve al directorio público el asset seleccionado, después de QA y checksum.
Las imágenes de ejemplo entregadas por Luis en `Downloads` son referencias y
**no se despliegan** ni se copian como assets finales.

### 5.3 Personalización y publicación

La imagen genérica aprobada es el `start_image` del prompt personalizado. El
modelo debe conservar escena, personajes, paleta, encuadre y panel, y escribir
solo los datos compilados. El video usa como `start_image` la imagen
personalizada ya aprobada y debe preservar su texto sin reescribirlo.

El preview admin muestra lado a lado la imagen genérica y la personalizada,
el prompt compilado, la fuente del archivo y su estado de aprobación. Los
controles administrativos nunca forman parte del archivo descargable.

### 5.4 Linaje visual obligatorio hacia el Photo Booth

Al aprobar la imagen genérica, crear una versión inmutable del manifiesto visual
con:

- checksum y versión de la imagen maestra;
- paleta principal/secundaria en valores concretos;
- biblia física de cada personaje y reglas de color;
- materiales, iluminación, profundidad y tratamiento 3D;
- motivos de fondo, globos, torta, regalos y decoración;
- tipografía, contornos y contraste de textos dinámicos;
- exclusiones: marcas, nombres oficiales, elementos o estilos no permitidos.

Todo prompt y asset del kiosco debe declarar la versión del manifiesto y usar
la imagen genérica aprobada como referencia visual cuando el proveedor lo
permita. Portada, sala, ruleta, retratos, recortes, videos, composición final y
diploma se rechazan si cambian la paleta dominante, rediseñan personajes o
rompen el lenguaje visual del máster.

Una nueva imagen genérica aprobada crea una nueva versión; no modifica en
silencio fiestas ya publicadas. El admin puede migrarlas explícitamente después
de previsualizar y aprobar la regresión completa.

## 6. Datos y migraciones

Crear migraciones incrementales, reversibles y compatibles con MySQL/MariaDB y
SQLite de pruebas. No modificar `001_initial.php` ni `002_theme_prompts.php`.

### 6.1 `cc_invitation_assets`

- `id`, `theme_slug`, `kind` (`image|thumbnail|video`), `version`.
- `storage_key`, `mime_type`, `byte_size`, `width`, `height`, `duration_ms`.
- `sha256`, `provider`, `model`, `external_job_ref` sanitizado.
- `estimated_credits`, `actual_credits`.
- `status` (`candidate|approved|rejected|retired`).
- `created_at`, `approved_at`, `retired_at`.
- Único por `(theme_slug, kind, version)`.

### 6.2 `cc_invitation_templates`

- `id`, `theme_slug`, `version`, `image_asset_id`, `video_asset_id` nullable.
- `text_box_json`, `text_style_json`, `overlay_config_json`.
- `visual_manifest_json`, `visual_source_sha256`, `visual_version`.
- `status` (`draft|active|retired`), `created_at`, `updated_at`.
- Solo una versión activa por temática.
- Los assets derivados del Photo Booth guardan `visual_version` para auditar
  exactamente de qué invitación maestra provienen.

### 6.3 `cc_invitations`

- `id`, `party_id` nullable y no obligatorio, `template_id`.
- `birthday_person_name`, `event_date`, `event_time`, `address`.
- `age`, `venue_name`, `rsvp_label`, `rsvp_value`, `message`, todos nullable.
- `status` (`draft|published|revoked|archived`).
- `public_token_hash`, `published_at`, `revoked_at`, `created_at`, `updated_at`.
- Es fuente de verdad de sus propios datos: no depende de `cc_parties` para
  poder crearse, personalizarse, descargarse ni revocarse.
- Para permitir borradores, los datos pueden ser nulos en BD; el servicio de
  compilación/publicación exige nombre, fecha, hora y dirección válidos en una
  misma transacción.
- La retención/anonimización existente debe incluir estos datos.

### 6.4 `cc_invitation_outputs`

- `id`, `invitation_id`, `kind` (`personalized_image|personalized_video`).
- `storage_key`, `mime_type`, `byte_size`, dimensiones o duración y `sha256`.
- `prompt_key`, `prompt_revision_id`, `prompt_snapshot_checksum`.
- `provider`, `model`, `external_job_ref` sanitizado y costos nullable.
- `status` (`candidate|approved|rejected|retired`) y fechas de auditoría.
- Los archivos viven fuera del webroot y solo se sirven por el controlador de
  descarga; nunca se exponen rutas físicas ni se listan directorios.

### 6.5 Plan contratado y galería

Agregar mediante migración incremental:

- `cc_parties.service_plan`: `booth|full`, default `booth`.
- `cc_parties.gallery_enabled`: booleano explícito, default `false`.
- `gallery_enabled=true` solo se acepta si `service_plan=full` y existe un PIN
  válido; cambiar a otro plan lo desactiva, sin borrar fotos inmediatamente.
- El PIN protege el acceso, pero no representa la contratación ni habilita la
  galería por sí solo.
- `upload.php` y las URLs opacas individuales continúan disponibles para
  `booth` y `full`; `galeria.php` exige además `gallery_enabled=true`.
- En modo JSON temporal usar `servicePlan` y `galleryEnabled` con los mismos
  defaults y migración idempotente.

### 6.6 Reutilización del Photo Booth

La acción admin `Nueva fiesta desde esta temática` crea una nueva fila en
`cc_parties`, reutiliza los assets y la geometría versionada del tema y genera
un slug/enlace de kiosco único. No duplica archivos del tema.

La nueva fiesta exige sus propios datos y nace con plan `booth`, galería
desactivada y sin PIN. Nunca copia invitados, fotos, tokens de invitación,
outputs personalizados, rate limits ni enlaces de descarga. Si se asocia una
invitación, la relación es explícita y ambos enlaces siguen siendo distintos:
uno descarga la invitación y el otro abre la experiencia Photo Booth.

### 6.7 Prompts y auditoría

Reutilizar `cc_theme_prompts` como valor actual y ampliar su whitelist con
claves lógicas controladas:

```text
invitation.generic.v1
invitation.personalize.v1
invitation.negative.v1
invitation.motion.personalized.v1
```

Agregar `cc_theme_prompt_revisions` para historial append-only: tema, clave,
texto anterior/nuevo, checksum, actor administrativo y fecha. Los prompts no
forman parte del payload público de `api.php`.

## 7. Panel administrativo

Agregar una navegación superior `Invitaciones` sin eliminar las cards actuales
de `Temáticas`.

### Biblioteca de invitaciones

- 15 cards con miniatura, estado de imagen, estado de video, versión activa,
  checksum, proveedor/modelo y última aprobación.
- Filtros: todas, falta imagen, falta video, en revisión, aprobadas.
- Ficha de temática con preview 9:16, inventario, prompts imagen/negativo/video,
  historial, metadatos y acción para subir reemplazo.
- Pestaña `Línea visual` con imagen maestra, checksum, manifiesto, versión y
  matriz de todos los assets del Photo Booth derivados de ella.

### Editor de invitación independiente

- Columna izquierda: nombre, fecha, hora y dirección obligatorios; edad, lugar,
  RSVP y nota opcionales. La asociación a una fiesta existente es nullable.
- Centro: comparación 9:16 de imagen genérica e imagen personalizada, con
  selector de video cuando exista.
- Derecha: plantilla, versión, prompt con placeholders, prompt compilado,
  safe area, estado y detalles de cada output.
- Acciones: guardar borrador, compilar/copiar prompt, subir resultado, aprobar
  o rechazar, solicitar video, publicar/revocar y copiar enlace de descarga.
- Si falta un dato obligatorio, mostrarlo junto al campo y deshabilitar
  publicar, compartir y descargar; no completar con datos de ejemplo.
- Selector de servicio: `Photo Booth` o `Full con galería`. Ambos incluyen la
  invitación en imagen/video como primera entrega.
- El switch `Activar galería` solo se habilita para `Full` y exige PIN.
- Mostrar claramente qué está contratado y qué funciones están activas.
- La V1 comparte **solo el enlace opaco de descarga**. El admin usa `Copiar
  enlace` y lo pega manualmente en WhatsApp u otro canal.
- La V1 no adjunta archivos, no abre contactos, no envía mensajes
  automáticamente, no usa WhatsApp Business API y no almacena agenda.
- Mobile/tablet: en 7–10 pulgadas las tres columnas pasan a pasos; el preview
  mantiene 9:16 y los controles táctiles tienen al menos 44×44 CSS px.

## 8. Seguridad y operación

- PHP mínimo 8.0; baseline 8.2 y pruebas 8.3/8.4.
- Toda mutación admin: sesión autenticada, POST, CSRF y PRG.
- Validar MIME real, dimensiones, duración y tamaño; nombres físicos opacos.
- Directorio de candidatos y personalizados fuera del webroot.
- Rutas públicas construidas desde `public_base_url`, nunca `HTTP_HOST`.
- Token público aleatorio de 128 bits; guardar solo hash y permitir revocación.
- El endpoint `descargar-invitacion.php?t=<token>` sirve únicamente outputs
  aprobados, usa `public_base_url`, agrega `X-Robots-Tag: noindex, nofollow` y
  no revela IDs incrementales, prompts, rutas ni nombres físicos.
- Escapar todo texto por contexto HTML/atributo/Canvas/ffmpeg.
- Ninguna API key de Gemini/Higgsfield vive en PHP, JS, BD, prompt, log o Git.
- V1 no llama proveedores desde el navegador ni el hosting. OpenCode Go opera
  el proveedor de forma controlada y sube el resultado aprobado.
- Antes de cada generación: dry-run, cotización real, saldo, costo máximo y
  aprobación de Luis. No reintentar automáticamente un job cobrado.
- Registrar job id, costo y resultado sin token ni URL firmada persistente.

## 9. Sistema de prompts

### 9.1 Prompt maestro de imagen

Es provider-neutral y puede usarse en el generador de imagen de Higgsfield o
en otro proveedor aprobado. No autoriza por sí mismo ninguna generación.

```text
Create a premium vertical 9:16 children's birthday invitation background,
1080x1920, polished cinematic 3D collectible-toy style, joyful and suitable
for children. [THEME_SCENE]

Reserve a large, centered, completely blank invitation panel in the upper
third, with generous inner margins and even contrast for later personalized
text generation. Keep the main character group, cake, gifts and themed props in
the lower half without covering that panel. Balanced depth, clean silhouettes,
soft studio-quality lighting, rich but controlled colors, high detail.

The blank panel must contain absolutely no text, letters, words, numbers,
symbols, logos, brands or watermark. All characters and props must be original
designs described only by physical traits; do not reproduce official costumes,
emblems, product marks or exact copyrighted compositions.
```

### 9.2 Negative prompt maestro

```text
text, letters, words, numbers, typography, captions, logo, brand, watermark,
signature, QR code, official emblem, trademark, copied poster composition,
exact copyrighted character design, malformed hands, duplicated characters,
extra limbs, distorted faces, cropped cake, cropped blank panel, objects inside
the blank panel, low resolution, blur, compression artifacts, horizontal frame
```

### 9.3 Prompt maestro de personalización

```text
Edit the provided approved generic vertical 9:16 invitation image. Preserve
the exact original characters, colors, faces, props, cake, background,
lighting, framing, blank panel and overall composition. Do not add, remove or
redesign characters.

Place only this exact Spanish text inside the blank invitation panel, centered,
sharp, correctly accented and perfectly legible:

"¡[NOMBRE_DEL_CUMPLEAÑERO] está de cumpleaños!"
"[FECHA_Y_HORA]"
"[DIRECCIÓN]"

Optional lines, only when supplied:
"Cumple [EDAD] años"
"[LUGAR]"
"[MENSAJE]"

Use a friendly rounded font, strong contrast, balanced line breaks and safe
inner margins. Do not translate, paraphrase, autocorrect, invent, omit or
duplicate any supplied data. No extra text, logo, watermark, QR or signature.
All depicted characters remain original designs described only by physical
traits; never add official names, marks or costumes.
```

El compilador sustituye los placeholders con datos escapados y normalizados.
Debe rechazar la operación si queda cualquier patrón `[...]` sin resolver.

### 9.4 Prompt maestro Higgsfield para invitación personalizada

```text
Animate only the provided approved personalized 9:16 invitation image for 5
seconds at 720p.
Preserve the exact characters, faces, outfits, props, palette, camera angle and
composition. Preserve every visible Spanish letter, accent, number, line break
and punctuation exactly as shown; do not rewrite or regenerate the text. Use a
very slow cinematic push-in, subtle layered parallax and
small theme-specific motions: [THEME_MOTION]. Keep every movement gentle and
loop-friendly. The invitation panel must remain perfectly stable, sharp,
unobstructed and evenly lit.
Do not add, remove or redesign characters or props. Do not create text, letters,
numbers, logos, watermarks, camera cuts, lip-sync, large gestures, fast zooms,
warping, flicker or new objects. Finish close to the starting pose for a clean
loop.
```

Parámetros iniciales validados por el piloto: `kling3_0_turbo`, 5 s, 720p,
9:16. El modelo final puede cambiar solo después de una prueba A/B cotizada;
“mejor modelo” significa mejor resultado aprobado, no mayor costo automático.

## 10. Bloques visuales por temática

Estos bloques se insertan en `[THEME_SCENE]`. No contienen nombres de
franquicias ni personajes.

| Slug | `THEME_SCENE` |
|---|---|
| `carreras` | Six friendly original anthropomorphic toy vehicles at a birthday finish line: a glossy red sport coupe, an elegant sky-blue coupe, a rusty brown tow truck, a cheerful yellow compact car, a navy vintage racer and a sleek golden-yellow racer; expressive windshield eyes, checkered bunting, trophies, balloons and confetti, no numbers or sponsor marks. |
| `mickey` | An original cheerful clubhouse party with a family of round-eared cartoon mouse friends in coordinated red, yellow, pink and blue outfits, rounded doors, balloons, gifts and a tiered cake; no glove symbols, monograms or recognizable logos. |
| `hielo` | An original winter-fantasy celebration with a platinum-braided young ice guardian in a sparkling pale-blue gown, her auburn-haired adventurous friend, a friendly round snow creature and tiny arctic companions; crystal palace, snowflakes, icy balloons and cake. |
| `cachorros` | A team of six original rescue puppies of different breeds wearing simple color-coded utility vests and helmets without badges, gathered at a bright rescue lookout with toy vehicles, balloons, paw-shaped confetti and cake. |
| `heroes` | A diverse team of original child-friendly heroes with distinct elemental abilities—strength, flight, lightning, technology, nature and shields—wearing unique unbranded suits, on a festive city rooftop with balloons and cake. |
| `princesas` | Six original storybook princess friends with varied skin tones, hair textures and jewel-colored gowns, gathered in a luminous royal ballroom with floral arches, crowns, balloons, gifts and an elegant cake. |
| `dinos` | Friendly baby dinosaurs—green tyrannosaur, blue triceratops, orange stegosaur, yellow long-neck and small purple flyer—in a lush birthday jungle with a distant gentle volcano, balloons, footprints, gifts and cake. |
| `sirenas` | Original mermaid friends with diverse skin tones, flowing aqua, coral, violet and teal tails, joined by a smiling seahorse, turtle and octopus in a bright underwater palace with bubbles, shells, balloons and pearl cake. |
| `juguetes` | A warm original toy-room birthday with a wooden western adventurer, a retro space explorer, a plush dinosaur, a porcelain shepherd, a toy robot and a springy dog toy; blocks, balloons, gifts and cake, all designs unbranded. |
| `tropical` | A playful original small blue tropical fantasy creature with large soft ears, joined by island animal friends at a Hawaiian beach party with hibiscus flowers, palms, surf props, balloons, gifts and cake; no official symbols. |
| `arcade` | Two original chibi platform adventurers in plain red and green caps without letters, accompanied by friendly mushroom-like forest creatures in a colorful arcade landscape with floating blocks, pipes, stars, coins without markings, balloons and cake. |
| `bloques` | Original blocky voxel builders and friendly cubic animals in a bright grass-and-stone world, with gem props, crafting blocks, balloons, gifts and a layered pixel-style cake; no game UI, logos or weapon focus. |
| `criaturas` | Five original cute elemental companion creatures—sun-yellow electric, aqua water, leafy green, ember-orange and violet night—celebrating in an enchanted garden with glowing orbs, balloons, gifts and cake; no capture devices or official markings. |
| `guerreros` | An original team of chibi energy martial artists with varied spiky hairstyles and orange, blue, purple and white training outfits without insignia, in a rocky sunset valley with soft glowing energy orbs, balloons, gifts and cake. |
| `familia-canina` | Six original anthropomorphic cattle-dog family and friend figures: vivid sky-blue older puppy, smaller warm-orange sister, navy-blue speckled father, coral-orange speckled mother, cream puppy with caramel patches and charcoal-white speckled puppy, celebrating in a sunny backyard playground. |

## 11. Biblioteca de movimientos candidatos

Esta tabla no autoriza generación en lote. Mientras `AT-CUMPLECLICK-007` esté
activo, solo se ejecuta `familia-canina`; los demás bloques quedan como
referencia para futuros tickets aprobados por Luis.

Estos bloques se insertan en `[THEME_MOTION]`:

| Slug | `THEME_MOTION` |
|---|---|
| `hielo` | tiny snow particles drift, crystal reflections shimmer slowly, fabric and loose hair move in a faint breeze, the snow companion blinks once |
| `cachorros` | puppies blink and gently tilt their heads, one tail wags softly, lookout lights pulse once, balloons sway |
| `dinos` | leaves sway, distant volcano releases a very small soft plume, baby dinosaurs blink and breathe gently, confetti drifts |
| `arcade` | platforms shift with minimal parallax, unmarked coins rotate slowly, small ambient lights glow, characters blink and make a tiny celebratory bounce |
| `familia-canina` | puppies blink, tails wag gently, two small paw waves, balloons sway and a little confetti drifts while the blank invitation panel stays fixed |

## 12. Orden de ejecución de OpenCode Go

1. Gate 0: releer handoff, revisar rama/diff/locks y registrar presupuesto.
2. Ejecutar únicamente `AT-CUMPLECLICK-007` para el Tema 02.
3. Completar infraestructura, imágenes, videos y QA en los gates definidos en
   `OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`.
4. Detenerse en `AWAITING_LUIS_ACCEPTANCE` con evidencia completa.
5. Solo después de la aceptación, Luis define Tema 03 y se crea otro ticket.

## 13. Criterios de aceptación

- Existen 15 cards activas, cada una con imagen aprobada y prompt auditable.
- Las cinco seleccionadas tienen video aprobado; las otras diez degradan a
  imagen sin mostrar controles rotos.
- Cada tema conserva una imagen genérica; cada invitación aprobada tiene una
  segunda imagen personalizada con sus datos reales y, opcionalmente, video.
- Cada asset del Photo Booth identifica la imagen maestra y `visual_version`
  de la cual deriva; no hay assets temáticos diseñados de forma independiente.
- Portada, sala, personajes, videos, foto y diploma respetan la paleta, biblia
  de personajes, materiales, iluminación y atmósfera del manifiesto aprobado.
- Una invitación solo se publica si tiene nombre, fecha, hora y dirección;
  los cuatro aparecen exactamente en la imagen y el video aprobados.
- Ningún asset base contiene texto o logo generado.
- No aparecen términos reservados en ningún prompt enviado a un proveedor.
- No se exponen prompts, referencias privadas, tokens, rutas físicas ni claves
  por la API pública.
- El enlace de descarga es opaco, revocable y separado del enlace del kiosco;
  solo entrega outputs aprobados y no revela rutas, prompts ni metadatos.
- El admin comparte solo ese enlace; no existe envío ni adjunto automático.
- Responsive verificado en 7, 8, 9 y especialmente 10 pulgadas, portrait y
  landscape, con controles táctiles accesibles.
- CRUD, publicación, revocación, validaciones, auth/CSRF, migración y rollback
  tienen pruebas positivas y negativas.
- El flujo actual del kiosco Carreras continúa funcionando sin regresiones.
- La galería devuelve no disponible cuando `gallery_enabled=false`, incluso si
  hay PIN o fotos; solo el admin puede activarla para un plan `full`.
- Luis aprueba evidencia visual y costo antes de cerrar el ticket.

## 14. Rollback y exclusiones

- Feature flag `invitations_enabled=false` oculta el módulo sin afectar kiosco.
- Desactivar una plantilla vuelve al último asset aprobado; nunca se borra el
  anterior durante estabilización.
- Down migrations solo se usan en entorno controlado y después de backup.
- No generar 15×6 saludos de personajes, audio personalizado ni voces TTS en
  este ticket.
- No hacer deploy, merge, push, borrado masivo ni gasto externo sin permiso.
