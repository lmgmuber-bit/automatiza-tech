# CumpleClick: baby shower con regalo validado (plan de implementación)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que la invitación del baby shower del 31-oct salga hoy con confirmación que exige nombre, regalo validado y
equipo (foto de bebé opcional), y que los papás vean quién trae qué.

**Architecture:** modo nuevo de regalos por invitación, `gift_mode = 'rsvp'`, sobre las tablas que ya existen
(`cc_rsvps`, `cc_gift_items`). Confirmar crea el regalo a nombre de quien confirma, dentro de una transacción que
bloquea la invitación para que dos personas no tomen el mismo regalo a la vez. Las reglas de regalos viven en una
librería pura y probada.

**Tech Stack:** PHP 8 sin framework, MySQL en PROD y SQLite en pruebas, JS vanilla en `assets/invitation.js`, pruebas PHP
CLI (`tests/backend/*.php`) y `node --test` (`tests/frontend/*.test.mjs`).

**Spec:** `Docs/superpowers/specs/2026-10-24-cumpleclick-baby-shower-invitacion-design.md` (secciones 4, 7b y 7c mandan).

## Global Constraints

- El repositorio es **público**: ni nombres de la familia, ni dirección, ni slug de PROD en código, pruebas o commits.
- La migración va antes que el código. Las migraciones son aditivas e idempotentes y corren en MySQL y SQLite.
- `gift_mode` es `VARCHAR(10)`: el valor nuevo es `'rsvp'`. Los modos `list` y `open` no cambian su comportamiento.
- El invitado ve **qué** está tomado, nunca **quién**. El nombre lo ven solo los papás y el admin.
- La foto de bebé se guarda fuera del webroot, reencodada a JPEG (sin EXIF). No la ve ningún invitado.
- Textos en español de Chile, sin rayas largas. Mensajes exactos:
  - único repetido: «Ese regalo ya lo trae alguien, elige otro.»
  - pañales completos: «Ya hay 3 personas que llevan pañales. Elige otro regalo, por favor.»
  - genérico: «Cuéntanos qué regalo llevarás, por ejemplo: un body, una mantita o un coche.»
  - sin regalo: «Cuéntanos qué regalo llevarás.»
  - sin equipo: «Elige Team Mamá o Team Papá.»
  - nombre repetido: «Ya hay una confirmación con ese nombre. Agrega tu apellido, por favor.»
  - foto ilegible o muy grande: «No pudimos usar esa foto. Prueba con otra o confirma sin foto.»
- Sin nombres de franquicias en la temática: osito genérico.

## Review Focus

1. Dos invitados confirman «coche» casi al mismo tiempo: solo uno queda con el coche (bloqueo de fila en MySQL).
2. Un invitado con un regalo rechazado vuelve a intentar: su nombre, equipo y foto siguen en el formulario.
3. Foto de iPhone de 6 MB o PNG con transparencia: se acepta y se reencoda; un PDF renombrado `.jpg` se rechaza.
4. «Mantita para la cuna» o «ropa para el coche» no chocan con cuna ni coche: la categoría la decide la palabra que
   encabeza, no cualquier palabra.
5. Los papás ocultan un regalo ya tomado en modo `rsvp`: queda libre para otro invitado.

---

### Task 1 (Forja): migración 028 y reglas de regalos

**Files:**
- Create: `CumpleBooth/database/migrations/028_rsvp_regalo.php` y `.down.php`; `CumpleBooth/database/aplicar-028.php`
  (mismo contrato que `aplicar-027.php`); `CumpleBooth/database/modo-regalo-confirmacion.php` (CLI: `php ... <slug>`
  pone `gift_mode='rsvp'` en la invitación que devuelve `cb_rsvp_invitacion_de_fiesta`).
- Create: `CumpleBooth/public/lib.regalos-confirmacion.php`
- Test: `CumpleBooth/tests/backend/regalos-confirmacion.php`

**Interfaces:**
- Produces:
  - Migración: `cc_rsvps` + `team VARCHAR(8) NULL`, `gift_item_id` (BIGINT UNSIGNED / INTEGER) `NULL`,
    `baby_photo_key VARCHAR(190) NULL`.
  - `const CB_REGALO_TOPE_PANALES = 3;`
  - `cb_regalo_normalizar(string $texto): string` — minúsculas, sin tildes (ñ→n), sin puntuación, sin palabras de
    relleno (de, del, para, el, la, los, las, un, una, unos, unas, mi, al, y, con, bebe, baby), plural a singular
    (`-es` tras l/r/n/d/z/j se quita entero; si no, `-s` final en palabras de más de 3 letras).
  - `cb_regalo_clasificar(string $texto): array{categoria:string, clave:string}`, en este orden:
    1. grupo único por **prefijo de tokens**, gana el sinónimo más largo: coche (coche, cochecito, carrito, carriola,
       coche paseo, carro paseo, travel system), cuna (cuna, cunita), corral (corral, cuna corral, practicuna,
       cuna viaje), moises (moises, cuna moises), silla-auto (silla auto, silla carro, silla coche, butaca),
       banera (banera, tina, tinita), mochila-portabebe (mochila portabebe, portabebe, canguro, mochila ergonomica),
       bolso-maternal (bolso maternal, mochila maternal, panalera), monitor (monitor, intercomunicador, babycall),
       cambiador (cambiador, mudador), silla-comer (silla comer, silla alta, trona), extractor (extractor leche,
       sacaleche), esterilizador, silla-nido (silla nido, nido, mecedora, bouncer) → `unico`.
    2. algún token `panal` → `panales`, clave `panales`.
    3. el **primer** token o un bigrama en la lista libre → `libre`, clave = el texto normalizado. Lista libre: ropa,
       ropita, body, pilucho, osito, pijama, calcetin, gorro, mantita, manta, frazada, enterito, conjunto, polera,
       pantalon, chaleco, zapatito, babero, toallita, toallita humeda, shampoo, jabon, crema, colonia, aceite,
       algodon, aseo, kit aseo, dinero, plata, efectivo, gift card, giftcard, tarjeta regalo, transferencia, aporte.
       Si el primer token no es libre pero algún token sí, también es `libre` («set ropa», «kit aseo»).
    4. todos los tokens en {sorpresa, sorpresita, regalo, regalito, juguete, algo, cosa, detalle, nose, sabe,
       lindo, bonito, pequeno, grande, especial, otro} o texto vacío → `generico`.
    5. si no → `unico`, clave = texto normalizado.
  - `cb_regalo_validar(int $invitationId, string $texto): array` → `['ok'=>true,'categoria'=>..,'clave'=>..]` o
    `['ok'=>false,'error'=>'regalo_requerido'|'regalo_generico'|'regalo_tomado'|'panales_completos','mensaje'=>..]`.
    Compara contra los `cc_gift_items` de la invitación con `moderation_status='approved'` (un regalo oculto queda
    libre), clasificando cada `title` con la misma función.

- [ ] **Step 1: prueba en rojo** `tests/backend/regalos-confirmacion.php` (arranque igual que `album.php`, migra todo):
  - `clasificar('Coche de paseo')['clave'] === clasificar('cochecito')['clave'] === clasificar('COCHES')['clave'] === 'coche'`
  - `clasificar('Silla para auto')['clave'] === clasificar('silla de auto')['clave']`
  - `clasificar('Cuna corral')['clave'] === 'corral'` y `clasificar('cuna')['clave'] === 'cuna'` y `clasificar('Moisés')['clave'] === 'moises'`
  - `clasificar('Pañales talla RN')['categoria'] === 'panales'`, `clasificar('torta de pañales')['categoria'] === 'panales'`, `clasificar('pañalera')['clave'] === 'bolso-maternal'`
  - `clasificar('Mantita para la cuna')['categoria'] === 'libre'`, `clasificar('body')`, `clasificar('Gift card')`, `clasificar('dinero')`, `clasificar('toallitas húmedas')` → `libre`
  - `clasificar('sorpresa')`, `clasificar('un regalito')`, `clasificar('Juguete')` → `generico`; `clasificar('juguete de madera')['categoria'] === 'unico'`
  - `validar`: con «coche» ya anotado, «Cochecito» da `regalo_tomado` con el mensaje exacto; con 3 «pañales» el cuarto da `panales_completos`; «body» pasa con 5 bodies anotados; un coche oculto (`moderation_status='hidden'`) ya no bloquea.
- [ ] **Step 2:** `php tests/backend/regalos-confirmacion.php` → FAIL (función no definida).
- [ ] **Step 3:** implementar migración y librería.
- [ ] **Step 4:** la prueba pasa; `php tests/backend/run.php` sigue verde.
- [ ] **Step 5:** commit de esos archivos solamente.

### Task 2 (Forja): confirmar con regalo, API y modo `rsvp`

**Files:**
- Modify: `public/lib.regalos-confirmacion.php`, `public/lib.gifts.php` (`cb_gift_mode`, `cb_gift_set_mode`,
  `cb_gift_set_hidden`), `public/rsvp-api.php`, `public/gift-api.php`, `public/lib.rsvp.php` (`cb_rsvp_list`,
  `cb_rsvp_resumen`)
- Test: `tests/backend/rsvp-regalo-http.php` (patrón de `galeria-http.php`: servidor `php -S` con base temporal)

**Interfaces:**
- Consumes: Task 1.
- Produces:
  - `cb_gift_mode()` devuelve `'list'|'open'|'rsvp'`. `cb_gift_set_mode()` devuelve `['ok'=>false,'error'=>'modo_fijo']`
    si la invitación está en `rsvp` y nunca pone `rsvp` (eso lo hace solo el CLI). `cb_gift_set_hidden()` deja ocultar
    un regalo tomado cuando el modo es `rsvp`. `gift-api.php` responde 409 `modo_confirmacion` a claim/add/release en
    modo `rsvp`.
  - `cb_rsvp_confirmar_con_regalo(array $invitation, array $datos, ?array $foto): array` con
    `$datos = ['family_name'=>..,'gift'=>..,'team'=>'mama'|'papa']` y `$foto` = entrada de `$_FILES` o null.
    Devuelve `['ok'=>true]` o `['ok'=>false,'error'=>código,'mensaje'=>texto exacto]`. Códigos nuevos:
    `nombre_requerido`, `nombre_repetido`, `equipo_requerido`, `foto_invalida` más los de `cb_regalo_validar`.
    Dentro de una transacción: `SELECT ... FROM cc_invitations WHERE id=? FOR UPDATE` (solo MySQL), nombre repetido
    (`LOWER(family_name)`), `cb_regalo_validar`, `INSERT cc_gift_items` (title tal cual lo escribió, `added_by='guest'`,
    `status='taken'`, `claimed_name`=nombre, `claimed_at`), `INSERT cc_rsvps` con team, gift_item_id y baby_photo_key.
    La foto se valida (≤ 10 MB, `getimagesize` JPEG/PNG/WebP), se reencoda a JPEG ≤ 1600 px de lado, calidad 85, y se
    guarda en `<raíz privada de fotos>/rsvp-bebes/<party_id>/<hex32>.jpg`; si la transacción falla, se borra.
  - `cb_rsvp_foto_path(string $key): ?string` y `cb_rsvp_borrar(int $partyId, int $rsvpId): bool` (borra la fila, su
    regalo y su foto).
  - `cb_rsvp_list()` agrega `id`, `team`, `gift_title` (LEFT JOIN), `has_photo`. `cb_rsvp_resumen()['lista'][]` agrega
    `id`, `equipo`, `regalo`, `foto` (bool), y el resumen agrega `team_mama`, `team_papa`.
  - `rsvp-api.php`: acepta `multipart/form-data` (`$_POST`, `$_FILES['baby_photo']`) además de JSON. Si
    `cb_gift_mode($invitation) === 'rsvp'` usa `cb_rsvp_confirmar_con_regalo` y responde 422
    `{ok:false,error,mensaje}` o 200 `{ok:true, regalo:<title>}`. Si no, el camino de siempre.

- [ ] **Step 1: prueba HTTP en rojo** `tests/backend/rsvp-regalo-http.php`: invitación publicada en modo `rsvp`;
  - POST multipart sin `gift` → 422 `regalo_requerido`; sin `team` → 422 `equipo_requerido`;
  - «Coche» pasa (200); «coche de paseo» de otro nombre → 422 `regalo_tomado`, mensaje exacto;
  - 3 pañales pasan, el cuarto → 422 `panales_completos`; «body» dos veces pasa;
  - mismo nombre otra vez → 422 `nombre_repetido`;
  - con foto JPEG válida → 200 y el archivo existe fuera del webroot; con texto disfrazado de JPEG → 422 `foto_invalida`
    y no queda confirmación;
  - `cb_gift_list_public` no trae nombres; `cb_rsvp_list` trae equipo y regalo;
  - `gift-api.php` claim en modo `rsvp` → 409; invitación en modo `list` sigue confirmando por JSON como antes.
- [ ] **Step 2:** correrla → FAIL. **Step 3:** implementar. **Step 4:** pasa, y `run.php`, `album.php`,
  `galeria-http.php` siguen verdes. **Step 5:** commit.

### Task 3 (Forja): pantallas de los papás y borrado en el admin

**Files:** Modify `public/asistencia-papas.php`, `public/regalos-papas.php`, `public/admin/index.php` (bloque de
confirmados cerca de la línea 1289). Test: ampliar `tests/backend/rsvp-regalo-http.php`.

- `asistencia-papas.php` en modo `rsvp`: cada fila con nombre, «Team Mamá»/«Team Papá», regalo y 📷 si trajo foto
  (sin mostrar la foto); arriba el marcador «Team Mamá N · Team Papá M».
- `regalos-papas.php` en modo `rsvp`: sin interruptor de modo ni formulario de agregar; texto «Los invitados te dicen
  qué llevan al confirmar.»; cada regalo con quién lo trae y botón Ocultar (lo libera).
- Admin: botón «Borrar» por confirmación (POST con el CSRF que ya usa la página, `confirm()` antes) que llama a
  `cb_rsvp_borrar`.
- [ ] Prueba: los papás ven nombre + equipo + regalo; el HTML de la invitación no contiene el nombre; borrar desde el
  admin elimina fila, regalo y foto. Commit.

### Task 4 (Lienzo): invitación en modo `rsvp` y temática `baby-osito`

**Files:** Modify `public/invitacion.php`, `public/assets/invitation.js`, `public/assets/invitation.css`,
`public/data/themes.json`, `public/data/event-profile-presets.json`, `public/admin/agenda.php` (mapa de nombres).
Test: `tests/frontend/babyShowerRegaloRsvp.test.mjs`.

**Interfaces:** consumes el contrato de `rsvp-api.php` de la Task 2 y `cb_gift_mode() === 'rsvp'`.

- Sección de regalos en `rsvp`: se muestra aunque esté vacía; solo lectura (sin «Yo lo regalo», sin agregar, sin
  campo de nombre). Gancho `'¿Qué le vas a llevar' . $aQuienRegalo . '?'`; bajada «Nos lo cuentas al confirmar. Así
  nadie repite.»; reglas «Coche, cuna y otros regalos grandes: uno por persona. Pañales: hasta 3 personas. Ropa, aseo,
  dinero o gift card: libre.»; vacío «Todavía nadie anota su regalo.»; insignia «Ya lo traen».
- Modal en `rsvp` (`data-rsvp-modo="rsvp"` en el form): nombre; regalo (`name="gift"`, requerido, máx. 120,
  placeholder «Ej: un coche, pañales talla RN, un body»); equipo con dos botones grandes tipo radio (`name="team"`,
  `mama`/`papa`, «Team Mamá» / «Team Papá»); foto opcional (`name="baby_photo"`, `accept="image/*"`) con «¿Juegas a
  "¿Quién es este bebé?"? Sube una foto tuya de bebé (opcional). Solo se verá en la fiesta.» y vista previa.
  `invitation.js` envía `FormData` cuando el modo es `rsvp`, muestra `mensaje` del 422 en `[data-rsvp-error]` sin
  borrar lo escrito, marca el campo culpable, y al confirmar agrega el regalo a la lista de solo lectura. Archivo
  > 10 MB: aviso local sin enviar.
- Temática `baby-osito` («Osito de Bienvenida»): `themes.json` (modalidad `baby_shower`, colores salvia, amarillo y
  café osito; `frameBox` provisional `{x:0.2,y:0.18,w:0.6,h:0.42}` que el orquestador recalibra), preset en
  `event-profile-presets.json` igual que `baby-nube`, kicker «Con mucho amor, te esperamos para recibir a:», entrada en
  `$playlistOrdersByTheme` copiando la estructura de `baby-nube` con `despedida-baby-osito.mp4`, chispas propias en
  `invitation.css` (globos o huellitas que suben, con `prefers-reduced-motion`) y nombre en `admin/agenda.php`.
  Los archivos de imagen y música los pone el orquestador en `public/themes/baby-osito/`.
- [ ] Prueba node: el modal en modo `rsvp` trae los tres campos y la sección no trae botones de reserva; `themes.json`
  y presets traen `baby-osito`. Revisión con `php -S` a 375 px. Commit.

### Task 5 (orquestador): imágenes, voz, despliegue

- Soul 2: `fondo-banner.jpg` (portada sin texto) y `fondo-sala.jpg` (marco grande rectangular centrado) en
  `public/themes/baby-osito/`; música de `baby-nube` copiada mientras Luis elige; recalibrar `frameBox`/`text_area`.
- Dos muestras de voz (venezolana y chilena, neutras) para que Luis elija.
- Respaldo de PROD, `aplicar-028.php`, subir archivos, crear la fiesta y la invitación con los datos privados,
  `modo-regalo-confirmacion.php <slug>`, confirmación de prueba, borrarla, verificación desde afuera, entregar enlaces.
