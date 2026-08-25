# Plan — Modalidad Baby Shower + 4 temáticas nuevas

Fecha: 2026-08-25 · Estado: **plan, sin una línea de código escrita** · Autor: Claude (agente)

**Base leída para este plan:** el árbol más nuevo de CumpleBooth en esta máquina,
`.worktrees/cumplebooth-protagonista/CumpleBooth/` (es el único junto a
`codex-narracion-3-temas` que trae `008_event_profiles`, `009_invitation_gender` y
`public/lib.event-profiles.php`, y el único con el rediseño de revista del 2026-08-25).
El árbol principal `C:\wamp64\www\automatiza-tech\CumpleBooth\` está más atrás: llega
hasta la migración 007. **Antes de ejecutar este plan hay que confirmar sobre qué rama
se trabaja**, porque los números de migración dependen de eso.

---

## 1. Resumen para decidir en dos minutos

La buena noticia: **el 70% de la modalidad ya está construida y apagada.**

| Pieza | Estado real hoy | Evidencia |
|---|---|---|
| Columna de modalidad | Existe: `cc_parties.event_type VARCHAR(40) DEFAULT 'child_birthday'` | `database/migrations/008_event_profiles.php:48-51` |
| `baby_shower` como modalidad | Ya está declarada, con `status: "architecture_only"` (sin `sections`, sin `fields`, sin `intro`) | `public/data/event-profile-presets.json` → `event_types.baby_shower` |
| Ficha del protagonista | Esquema **ya genérico a propósito**: `cc_featured_people` soporta varias personas con consentimiento por persona; los campos son filas, no columnas | migración 008 (su propio docblock lo dice), `ARQUITECTURA.md:43-49` |
| Álbum Recuerdo | Nombres genéricos **puestos a propósito para baby shower** | `ARQUITECTURA.md:37-42` y `AGENTS.md` ("Vocabulary is deliberately generic ... so weddings/baby showers can reuse it") |
| Género "no sabemos" | `cc_invitations.birthday_person_gender` es `VARCHAR(1) NULL`; NULL ya cae al audio neutro | migración 009 + `invitacion.php:76-88` |

La mala noticia: **`event_type` hoy no sale de la ficha del protagonista.** No llega a
`api.php`, no llega al kiosco, no llega a la invitación. Todo el texto visible
("cumpleaños de X", "Nombre del cumpleañero/a") está escrito duro. Ese es el trabajo real
de backend, y es de plomería, no de arquitectura.

### Lo que propongo

1. **Una sola migración** (`010_invitation_event_type`). El esquema casi no cambia — esa
   es la conclusión honesta después de leer 008 y 009.
2. **Un diccionario de modalidad** (`vocab` por `event_type` en el catálogo JSON que ya
   existe) + una función `cb_event_vocab()`. Sin duplicar plantillas: `invitacion.php`
   sigue siendo **un** archivo.
3. **Un tercer valor de género** (`'x'` = "aún no lo sabemos") que hoy se descartaría en
   silencio, no con error — detalle importante, está en §4.3.
4. **4 temáticas por decoración, cero personajes con derechos.** Dos infantiles (Circo de
   Papel, Safari de Peluche) y dos de bebé (Nube de Algodón, Jardín del Bebé).
5. **Reusar escenarios 3D existentes** en vez de crear `stage` nuevos: no toco el Show 3D.
   Tiene un costo estético que declaro en §9.

### Lo que NO propongo

- No propongo tocar el Show 3D, ni Carreras, ni Hielo.
- No propongo tabla nueva para "el bebé". `cc_featured_people` ya es eso.
- No propongo un `invitacion-baby.php`. Duplicar esa plantilla (1.200+ líneas) es
  garantizar que en tres meses una se arregle y la otra no.

### Costo grueso

| Bloque | Esfuerzo | Comentario |
|---|---|---|
| Backend + vocabulario + admin (Fases 1-2) | ~2-3 sesiones de trabajo | Sin costo de IA |
| Cada temática, parte barata (catálogo + imágenes) | ~1 sesión + generación de 14 imágenes | Retratos, cortes, puzzles, fondos |
| Cada temática, parte cara | **7 videos** (6 saludos + 1 despedida) | ×4 temáticas = **28 videos**. Es el grueso del presupuesto. |
| Invitación + sitio comercial (Fase 7) | ~1 sesión + 1-3 audios | Audios opcionales, hay fallback |

---

## 2. Qué es distinto en un baby shower (y qué no)

| Dimensión | Cumpleaños infantil | Baby shower | ¿Cambia código? |
|---|---|---|---|
| Protagonista | Un niño, existe, tiene nombre | Un bebé que no nació; puede no tener nombre público | **No** (datos, no esquema) |
| Personas destacadas | Normalmente 1 | Mamá, papá y el bebé | **No** (`cc_featured_people` ya es multipersona) |
| Género del protagonista | m / f | m / f / **aún no se sabe** | **Sí**, 2 whitelists |
| Invitados | Niños | Adultos (y a veces sus hijos) | **Sí**, tono de juegos y saludos |
| Qué se regala | Juguetes, ropa del niño | Ropa 0-3m, pañales, lista de tienda | **No** (campos del preset) |
| Foto de cabina | Igual | Igual | **No** |
| Álbum Recuerdo | Igual | Igual | **No** |
| Fecha | Fija | La fiesta es fija; **la fecha de parto es estimada** | **No** (campo de texto) |

Lo que **no** cambia es más de lo que cambia. Eso es mérito de quien escribió las
migraciones 007 y 008 pensando en esto.

---

## 3. Decisión de arquitectura: la modalidad es `event_type`, y sube

Hoy `event_type` vive en `cc_parties` pero solo lo leen dos archivos:
`public/lib.event-profiles.php` y `public/admin/event-profile.php`. Verificado por grep;
`api.php`, `App.jsx` e `invitacion.php` no lo mencionan nunca.

La propuesta es que `event_type` sea **el único eje de modalidad** y que suba por tres
caminos:

```
cc_parties.event_type ──┬─→ cb_event_profile_preset()   (ya existe)
                        ├─→ cb_event_vocab()            (nuevo, §4.2)
                        │      ├─→ invitacion.php  (textos)
                        │      ├─→ admin/*.php     (etiquetas)
                        │      └─→ api.php → App.jsx (aria-labels, diploma)
                        └─→ catálogo de temáticas permitidas por modalidad (§4.4)
```

Las invitaciones sin fiesta asociada (`cc_invitations.party_id` es NULL-able, ver
`lib.invitations.php:62`) no tienen de dónde sacar la modalidad. Por eso la única
migración: copiarle la columna.

**Regla que hay que escribir en el doc y en el código:** `event_type` decide **palabras**;
`theme_slug` decide **colores y personajes**; `service_plan` decide **qué se vende**. Los
tres son ortogonales. Si mañana alguien quiere Baby Shower + Reino de Hielo, tiene que
funcionar sin tocar nada.

---

## 4. Plan de backend

### 4.1 Migración

Una sola, siguiendo la convención existente (numerada, `.down.php` hermano, idempotente,
guardia `hasColumn`, doble camino MySQL/SQLite — el molde exacto está en
`009_invitation_gender.php`).

| Archivo | Qué hace | Reversa |
|---|---|---|
| `database/migrations/010_invitation_event_type.php` | `ALTER TABLE cc_invitations ADD COLUMN event_type VARCHAR(40) NULL` — NULL = "heredar de la fiesta o caer al default" | `010_invitation_event_type.down.php`: `DROP COLUMN`, con el mismo `try/catch` que usa 009 para SQLite < 3.35 |

**Lo que deliberadamente NO migro, y por qué:**

| Tentación | Por qué no |
|---|---|
| Agregar `cc_babies` o `cc_baby_details` | `cc_event_profile_fields` ya guarda pares clave/valor por persona y por sección. Una tabla nueva sería el mismo dato con más código. |
| Ensanchar `birthday_person_gender` | Ya es `VARCHAR(1)`. `'x'` cabe. El problema es la whitelist en PHP, no la columna. |
| Renombrar `birthday_person_name` → `celebrant_name` | Toca 10+ archivos, rompe `003_invitations_and_plan.php` que ya renombró esa columna una vez, y no compra nada que no compre el vocabulario. **Propongo dejar el nombre técnico feo y arreglar solo lo visible.** Anotarlo en `ARQUITECTURA.md` para que nadie lo "arregle" después. |
| Agregar `value_type = 'date'` | El código acepta `text\|multiline\|list\|size` (`lib.event-profiles.php:411`). La columna es `VARCHAR(20)`, así que agregar `'date'` es **una línea de PHP, sin migración**. Ver §9. |

### 4.2 Vocabulario: inventario real y solución

Conteo hecho con grep sobre `public/` y `src/` en el árbol de referencia. Solo cuento
`cumplea*` porque "fiesta" sirve igual para un baby shower y no vale la pena tocarlo.

| Archivo | Nº de `cumplea*` | Qué son |
|---|---|---|
| `public/invitacion.php` | 15 | **9 son texto visible**, el resto comentarios |
| `public/admin/invitations.php` | 12 | etiquetas de formulario y listados |
| `public/data/event-profile-presets.json` | 6 | `title_suggestions` / `cta_suggestions` de `child_birthday` — **estos ya están parametrizados por modalidad, no hay nada que hacer** |
| `public/lib.invitations.php` | 4 | 1 etiqueta de validación + el prompt por defecto |
| `src/App.jsx` | 3 | 1 `aria-label` + 2 comentarios |
| `public/lib.php` | 2 | etiqueta de campo + placeholder `[NOMBRE_DEL_CUMPLEAÑERO]` |
| `public/lib.album.php`, `src/album/album.css`, `public/assets/invitation.css` | 2+3+2 | comentarios y una clase CSS |
| `public/assets/invitation.js` | 1 | nombre del `.ics` descargado (`cumpleanos`) |
| `public/admin/index.php` | 2 | etiqueta "Nombre del cumpleañero/a" |
| `public/lib.leads.php` | 1 | lista de tipos de evento del sitio comercial |

Los strings visibles concretos que hay que parametrizar (línea verificada):

| Ubicación | Texto actual |
|---|---|
| `invitacion.php:339-341` | `'Tenemos el agrado de invitarte a celebrar el cumpleaños de:'`, con un `if ($themeSlug === 'hielo')` encima |
| `invitacion.php:597` | `'¡Estás invitado al cumpleaños de ' . $birthdayName . '!'` |
| `invitacion.php:720, 1195, 1198` | título y nombre de archivo del calendario: `'Cumpleaños de X'`, `cumpleanos-X` |
| `invitacion.php:776, 783-784` | `<title>` y Open Graph: `'X te invita a su cumpleaños'` |
| `invitacion.php:1165` | `'Te invitamos al cumpleaños de:'` |
| `invitacion.php:384, 386` | fallbacks `'nuestra cumpleañera'` (hielo) y `'quien cumple años'` |
| `admin/index.php:648` | label `Nombre del cumpleañero/a` |
| `lib.php:292` / `lib.invitations.php:35` | etiqueta de campo `'nombre del cumpleañero'` |
| `lib.invitations.php:81` | prompt por defecto: `'…imagen vibrante y festiva de cumpleaños infantil para [NOMBRE_DEL_CUMPLEAÑERO]…'` |
| `lib.php:310` | placeholder `[NOMBRE_DEL_CUMPLEAÑERO]` |
| `App.jsx:1299` | `aria-label={\`Cumpleaños de ${CONFIG.nombre}\`}` |

**Solución propuesta: un bloque `vocab` por modalidad, en el catálogo que ya existe.**

Agregar a cada entrada de `event_types` en `public/data/event-profile-presets.json` un
bloque nuevo, sin tocar la forma de lo que ya está:

```jsonc
"baby_shower": {
  "status": "active",
  "label": "Baby shower",
  "vocab": {
    "celebrant_role":   "el bebé",
    "celebrant_field":  "Nombre del bebé (o cómo le dicen)",
    "invite_kicker":    "Te invitamos a esperar juntos la llegada de:",
    "invite_share":     "¡Estás invitado al baby shower de {nombre}!",
    "og_title":         "{nombre} te invita a su baby shower",
    "page_title":       "baby shower",
    "calendar_title":   "Baby shower de {nombre}",
    "calendar_file":    "baby-shower",
    "celebrant_fallback": "nuestro bebé",
    "diploma_role":     "Invitado de honor"
  }
}
```

Y una función delgada, junto a las que ya existen:

```php
cb_event_vocab(string $eventType): array   // en public/lib.php o public/lib.event-vocab.php
```

Reglas para que esto no se pudra:

1. `child_birthday` **también** lleva su bloque `vocab` con los textos de hoy, palabra por
   palabra. Así el cambio es no-funcional para lo que ya está en PROD y se puede probar
   por diff.
2. Toda clave de `vocab` tiene fallback al valor de `child_birthday`. Una modalidad nueva
   incompleta se ve genérica, nunca vacía.
3. **Un test** (`tests/frontend/eventVocab.test.mjs`, hermano del que ya existe) que
   verifica que toda modalidad `active` tenga las mismas claves de `vocab` que
   `child_birthday`. Es lo que convierte esto en algo que se cumple.

**Punto de decisión:** el archivo se llama `event-profile-presets.json` y va a pasar a
llevar cosas que no son del perfil. Dos salidas: (a) dejarlo así y anotar que el nombre
quedó corto; (b) renombrarlo a `event-modes.json` y dejar un lector compatible. Mi
recomendación es **(a)**: un archivo, una lista de modalidades, una función de validez
(`cb_event_profile_valid_event_type()`, `lib.event-profiles.php:56`). Dos archivos serían
dos listas que se van a desincronizar. Ver §9.

### 4.3 Género: el tercer estado no es un tercer valor cualquiera

Lo verificado:

| Dónde | Código exacto | Consecuencia |
|---|---|---|
| `public/lib.invitations.php:133-134` | `$gender = in_array($genderRaw, ['m','f'], true) ? $genderRaw : null;` | cualquier otro valor se convierte en NULL **en silencio** |
| `public/admin/invitations.php:266` | misma whitelist `['m','f']` | segunda copia de la regla |
| `public/invitacion.php:77-83` | `'m'` → `narracion-final-nino.mp3`, `'f'` → `-nina.mp3`, resto → `narracion-final.mp3` | |
| `public/invitacion.php:84-88` | si el archivo de la variante no existe, cae al neutro | **nada se rompe si falta un audio** |

Lo que se rompe y lo que no:

| Escenario | Qué pasa hoy | Riesgo |
|---|---|---|
| Mandar `birthday_person_gender = 'x'` | Se guarda NULL. Sin error, sin log. | **Alto.** El admin creería que quedó guardado. Es el bug clásico de whitelist silenciosa. |
| Falta `narracion-final-bebe.mp3` | Cae al neutro | Ninguno |
| Invitaciones viejas con NULL | Siguen neutras | Ninguno |

**Propuesta:**

1. Agregar `'x'` a las **dos** whitelists (no una), con el significado **"aún no lo
   sabemos"**, distinto de NULL = "no se especificó".
2. `'x'` mapea a `narracion-final-bebe.mp3` si existe, y al neutro si no.
3. El texto visible usa `vocab.celebrant_fallback` ("nuestro bebé"), no una tercera
   plantilla.
4. El `<select>` del admin (`admin/invitations.php:319-320`) gana una tercera opción, y
   **solo se muestra la tercera opción cuando la modalidad es `baby_shower`** — que un
   cumpleaños infantil ofrezca "todavía no sabemos" no tiene sentido.
5. `themes.json` tiene además un campo `publico` ("niña" en Hielo) que **solo se muestra
   en el admin** como "público objetivo" (`admin/index.php:820, 1040`); no tiene lógica.
   Las temáticas nuevas llevan `publico: "bebé"` o `"mixto"` y listo.

### 4.4 Ficha del protagonista que no nació

Cero cambios de esquema. Lo que hay que **escribir** es el preset de `baby_shower`,
espejando la forma exacta de `child_birthday` (`sections` + `fields` + `intro`).

Reemplazo propuesto de las secciones de cumpleaños:

| `child_birthday` | `baby_shower` | Por qué |
|---|---|---|
| `introduction` — "Su historia" | `expectation` — "La espera" | La historia todavía no existe; lo que hay es la espera |
| `favorites` — "Sus favoritos" | `baby` — "Sobre el bebé" | Un bebé no tiene favoritos |
| `sizes` — "Tallas" | `sizes` — "Tallas del bebé" | **Se conserva la clave**, cambia la etiqueta y los campos |
| `gifts` — "Ideas para regalar" | `registry` — "Lista de regalos" | En baby shower se regala por lista, no por idea suelta |
| — | `parents` — "Los papás" | Sección nueva |
| `custom` — "Más sobre el protagonista" | `custom` — "Más sobre este día" | |

Campos propuestos:

| `field_key` | Etiqueta | Sección | `value_type` |
|---|---|---|---|
| `chosen_name` | Nombre elegido | `expectation` | `text` |
| `name_secret` | ¿El nombre es sorpresa? | `expectation` | `text` |
| `weeks_pregnant` | Semanas de gestación | `expectation` | `text` |
| `due_date` | Fecha estimada de llegada | `expectation` | `text` (ver §9) |
| `known_sex` | ¿Ya saben si es niño o niña? | `baby` | `text` |
| `first_wish` | Lo primero que le queremos mostrar | `baby` | `multiline` |
| `clothing_size` | Talla de ropa (0-3m, 3-6m…) | `sizes` | `size` |
| `diaper_size` | Talla de pañales | `sizes` | `size` |
| `registry_store` | Lista de regalos en | `registry` | `text` |
| `registry_link` | Enlace a la lista | `registry` | `text` |
| `gift_ideas` | Otras ideas de regalo | `registry` | `multiline` |
| `parents_names` | Los papás | `parents` | `text` |
| `message_to_baby` | Un mensaje para cuando sepa leer | `custom` | `multiline` |

**Personas destacadas.** `cc_featured_people` se usa tal cual: mamá y papá como dos
personas con foto y consentimiento propio, y el bebé como una tercera con
`display_name` = nombre elegido o `vocab.celebrant_fallback` y **sin foto** (no hay).
`photo_public_consent` y `photo_ai_consent` son por persona (columnas en 008), así que la
mamá puede autorizar su foto y el papá no. Eso ya funciona.

**Consentimiento — atención.** Una ecografía es dato de salud. Si Luis quiere permitir
subirla como foto del bebé, eso merece su propia decisión, no colarse en un preset. Está
en §9.

### 4.5 Qué toca el kiosco (`api.php` / `lib.php`)

| Cambio | Archivo | Nota |
|---|---|---|
| Publicar `party.eventType` en el payload | `public/lib.php` → `cb_build_theme_payload()` | Es metadata pública, no secreto; no cae bajo la regla de `franquicia` |
| Publicar `party.vocab` (solo las 3-4 claves que el kiosco usa) | idem | No mandar el diccionario completo a la tablet |
| Consumir `eventType`/`vocab` | `src/App.jsx` (aria-label `:1299`, `DIPLOMA` `:58,180`) | |

### 4.6 Sitio comercial

`public/lib.leads.php:39` tiene la lista cerrada:
`['Cumpleaños','Navidad','Día del Niño','Colegio o jardín','Evento de empresa','Otro evento especial']`.
**No incluye baby shower.** Agregarlo ahí + en el `<select>` de `sitio/`. Es una línea,
pero si no se hace, el servicio existe y nadie lo puede pedir.

---

## 5. Plan de frontend

### 5.1 Pantallas del kiosco

Las pantallas son fijas y están en una constante:
`src/App.jsx:342` → `['intro','invitados','spinner','photo-session','video-personaje','juego','transition','capture','revelacion','preview','qr','diploma','farewell']`.

| Pantalla | ¿Sirve en baby shower? | Qué hacer |
|---|---|---|
| `intro` | Sí | Solo el `aria-label` (`:1299`) |
| `invitados` | Sí | Sin cambios. Los invitados son adultos, la lista es la misma |
| `spinner` (ruleta de 6) | Sí | Sin cambios de código. Los 6 "personajes" son decoración temática |
| `photo-session` | Sí, opcional | Es de Tabla B. **Las temáticas de bebé arrancan sin esto** |
| `video-personaje` (saludo por nombre) | Sí | Sin cambios de código. Es el tono del video lo que cambia |
| `juego` | **Discutible** | Ver abajo |
| `transition`, `capture`, `revelacion`, `preview`, `qr` | Sí | Sin cambios |
| `diploma` | Sí | El título sale de `theme.diploma`; para baby shower es un "certificado de buenos deseos" |
| `farewell` | Sí | Sin cambios |

**Los juegos son el único punto real de fricción.** El estándar
(`docs/TEMATICA-COMPLETA.md`) obliga: estrella con 3 juegos, los otros 5 con 2. Un
invitado adulto en un baby shower haciendo dos rompecabezas seguidos es la parte que
puede no funcionar. Tres salidas:

| Opción | Qué implica | Riesgo |
|---|---|---|
| **A. Respetar el estándar tal cual** (recomendada) | Elegir los juegos menos infantiles: `fichas` + `copos` para los cinco, `escudo` para la estrella. Cero código, `themeFlow.test.mjs` pasa sin cambios | Puede sentirse largo para un adulto |
| B. Cadena corta solo para `baby_shower` | Un juego por personaje | **Rompe el estándar y su test.** Habría que declarar la excepción en `TEMATICA-COMPLETA.md` y en `HOMOLOGADAS` |
| C. Sin juegos, solo foto | Es literalmente el plan Booth sin extras | Regala la mitad del valor del kiosco |

Recomiendo **A** para la primera entrega y medir en la fiesta real. Cambiar de A a B
después es barato; cambiar el estándar antes de tener evidencia, no.

### 5.2 Archivos concretos

| Archivo | Cambio | Nuevo o existente |
|---|---|---|
| `public/invitacion.php` | ~9 literales → `$V['…']`; el `if ($themeSlug === 'hielo')` de `:339` pasa a `vocab` + override por tema | Existente |
| `public/assets/invitation.js` | `'cumpleanos'` (`:765`) sale de `data-cal-filename`, que ya viene del PHP | Existente, 1 línea |
| `public/assets/invitation.css` | 2 hits, revisar si son nombres de clase (si lo son, **no tocar**) | Existente |
| `public/lib.php` | `cb_event_vocab()`, payload con `eventType`, etiqueta `:292`, placeholder `:310` | Existente |
| `public/lib.invitations.php` | whitelist de género, etiqueta `:35`, prompt por defecto `:81` | Existente |
| `public/lib.event-vocab.php` | Solo si `lib.php` queda muy grande | **Posible nuevo** |
| `public/admin/index.php` | Select "Modalidad del evento" en el alta de fiesta; label `:648` dinámico | Existente |
| `public/admin/invitations.php` | Tercera opción de género condicionada a la modalidad | Existente |
| `public/admin/event-profile.php` | Ninguno — el `<select>` de `event_type` ya está en `:405` y se llena solo desde el catálogo | Existente, **0 cambios** |
| `src/App.jsx` | `aria-label` y `DIPLOMA` desde el payload | Existente, ~3 líneas |
| `public/data/event-profile-presets.json` | `baby_shower` → `active` con `sections`/`fields`/`intro`/`vocab`; `vocab` para `child_birthday` | Existente |
| `public/data/themes.json` | 4 entradas nuevas | Existente |
| `public/themes/<slug>/` ×4 | Assets de Tabla A | **Nuevos** |
| `tests/frontend/eventProfilePresets.test.mjs` | Hoy afirma que **solo** `child_birthday` es `active` (`:31-33`). **Hay que actualizarlo o falla** | Existente |
| `tests/frontend/eventVocab.test.mjs` | Test de completitud del diccionario | **Nuevo** |
| `tests/frontend/themeFlow.test.mjs` | Sumar las 4 temáticas a `HOMOLOGADAS` (`:456-464`) cuando estén completas | Existente |

### 5.3 Álbum Recuerdo: qué hereda gratis

Verificado contra `docs/ALBUM-RECUERDO-PRUEBAS.md` (sección del 2026-08-25) y
`ARQUITECTURA.md:37-42`.

| Pieza | ¿Hereda? | Por qué |
|---|---|---|
| Esquema (`cc_event_albums`, `cc_event_media`, `cc_event_album_tokens`) | **Sí, completo** | Nombres genéricos puestos a propósito para baby shower |
| Color del papel | **Sí** | Desde 2026-08-25 se deriva de `bgLight1` del tema. Una temática de bebé nueva trae su papel sola |
| Confeti impreso, canto de página, aro del marco | **Sí** | Salen de `colors` y `confetti` del tema |
| Dedicatoria manuscrita (Caveat, cinta washi) | **Sí** | Es CSS teñido por el tema |
| Composición (fotos inclinadas, dúo asimétrico, mosaico) | **Sí** | No depende de la modalidad |
| Página de video | **Sí** | |
| Mensajes del seed | **Sí** | Se hicieron neutros en género el 2026-08-25 y toman `$NOMBRE` |
| Portada | **Parcial** | Muestra el nombre de la temática. Neutro, pero hay que **mirarlo**, no asumirlo |
| Página de cierre ("cuántos recuerdos hay") | **Sí** | Neutro |
| Cuotas (400 archivos / 3 GB, 90 días) | Sí, y **siguen sin decidirse** — es una pregunta abierta que ya estaba antes de este plan | `cb_album_limits()` en `public/lib.album.php` |

**Conclusión del álbum: 0 líneas de código previstas.** Lo que corresponde es una
verificación, y por eso es el entregable de la Fase 0.

---

## 6. Admin: cómo elige el organizador la modalidad

Hoy hay **dos** lugares donde se toca `event_type`, y es inconsistente:

| Lugar | Estado |
|---|---|
| `admin/index.php` (alta de fiesta) | **No pregunta la modalidad.** La fiesta nace `child_birthday` por el default de la columna |
| `admin/event-profile.php:405` | Sí tiene el `<select>`, pero está enterrado en la ficha del protagonista, que es opcional |

O sea: hoy se puede tener un baby shower cuya fiesta dice `child_birthday` porque nadie
abrió la ficha. Propuesta:

1. **La modalidad se elige al crear la fiesta**, en `admin/index.php`, junto a Temática y
   Plan de servicio (el formulario ya tiene ese bloque, `:662-700`).
2. Es **inmutable después de crear**, igual que `tema` (que ya se bloquea con `disabled`
   en edición, `:663`). Cambiar la modalidad a mitad de camino invalidaría los campos de
   la ficha ya cargados.
3. El `<select>` de `event-profile.php` pasa a mostrar la modalidad **en solo lectura**,
   con un link a la fiesta. Una sola fuente de verdad.
4. El `<select>` solo lista modalidades con `status: "active"`. Hoy eso es
   `child_birthday`; al terminar la Fase 1 son dos. Las otras cinco
   (`adult_birthday`, `wedding`, `baptism`, `pet_party`, `custom`) siguen en
   `architecture_only` y **no se ofrecen**.
5. El listado de fiestas (`admin/index.php:1146`) muestra un distintivo de modalidad. Sin
   eso, un baby shower y un cumpleaños se ven idénticos en la lista.

---

## 7. Las 4 temáticas

**Regla dura respetada:** cero franquicias, cero personajes con derechos. La salida
probada del proyecto es **temática por decoración, no por personaje**
(`TEMATICA-COMPLETA.md`, reglas duras). Los seis "personajes" de cada temática son
arquetipos u objetos, no criaturas registradas. Efecto lateral bueno: no hay filtro de IP
que saltar, así que **no hace falta camuflaje en los prompts** y las escenas de grupo
dejan de rebotar por nsfw — el problema documentado en la regla 4 era de personajes con
derechos.

`franquicia` en `themes.json` es metadata administrativa (no se publica en `api.php`,
`ARQUITECTURA.md:169`). Para estas cuatro va `"Original CumpleClick"`.

### 7.1 Circo de Papel — `circo-papel` · para niños

- **Dirección visual:** un circo entero recortado en papel y cartón — carpa a rayas, banderines, aros, confeti de papel picado; textura de cartulina en todo, nada plástico.
- **Qué la separa de las otras tres:** es la única **cálida y saturada**; su gesto es "hecho a mano con tijeras". Contra las existentes: no compite con Carreras (que es velocidad y asfalto) porque su energía es de espectáculo, no de carrera.
- **Los 6 (arquetipos de oficio, sin nombre propio de nadie):** Directora de Pista, Malabarista, Trapecista, Domador Amable, Mago de Sombreros, Payaso de Nariz Roja.
- **Estrella:** Directora de Pista · juego propio `escudo`.
- **`diploma`:** "Artista Estrella de la Carpa".
- **`fullGame.stage`:** `podium-night` (reusado, ver §9).

| Token | Valor |
|---|---|
| `accent` | `#d64550` |
| `accentSoft` | `#ffe6e4` |
| `yellow` | `#f4b23f` |
| `ink` | `#2b1a2e` |
| `bgLight1` | `#fff6ec` |
| `bgLight2` | `#ffe8d2` |
| `dark1` | `#3a1220` |
| `dark2` | `#5b1c2e` |
| `dark3` | `#ffb4a2` |

`confetti`: `#d64550`, `#f4b23f`, `#fff6ec`, `#7b3f61`, `#ffb4a2`, `#e8734a`

### 7.2 Safari de Peluche — `safari-peluche` · para niños

- **Dirección visual:** una expedición de safari donde todos los animales son de peluche — sabana de fieltro, catalejo de juguete, carpa de tela, pasto cosido.
- **Qué la separa:** es la única **terrosa y mate**; textil en vez de papel. Contra Familia Canina (que es patio de casa y caricatura) esta es exterior, textura y sol bajo.
- **Los 6:** León de Melena, Jirafa de Lunares, Cebra a Rayas, Elefante Gris, Mono Curioso, Hipopótamo Dormilón.
- **Estrella:** León de Melena · juego propio `escudo`.
- **`diploma`:** "Explorador de la Sabana".
- **`fullGame.stage`:** `backyard-fiesta` (reusado).

| Token | Valor |
|---|---|
| `accent` | `#6f9e5a` |
| `accentSoft` | `#eaf3e2` |
| `yellow` | `#e3a552` |
| `ink` | `#26301f` |
| `bgLight1` | `#f6f4e8` |
| `bgLight2` | `#e6e6cf` |
| `dark1` | `#1f2c1c` |
| `dark2` | `#33452c` |
| `dark3` | `#b7d59b` |

`confetti`: `#6f9e5a`, `#e3a552`, `#f6f4e8`, `#a9714b`, `#b7d59b`, `#d8c48a`

### 7.3 Nube de Algodón — `nube-algodon` · baby shower

- **Dirección visual:** cielo pastel al amanecer — nubes de algodón, globos aerostáticos de tela, lunas y estrellas de fieltro, móviles de cuna. Todo flota, nada pesa.
- **Qué la separa:** es la más **clara y aérea** de las cuatro; su papel de álbum (`#f6fbff`) es el único frío. Contra Reino de Hielo, que también es celeste: Hielo es cristal, contraste y azul saturado; esta es lana, bajo contraste y durazno.
- **Los 6 (objetos, no criaturas):** Nube Viajera, Globo Aerostático, Luna Dormida, Estrellita, Móvil de Cuna, Cometa de Papel.
- **Estrella:** Globo Aerostático · juego propio `escudo`.
- **`diploma`:** "Guardián de los Buenos Deseos".
- **`fullGame.stage`:** `ice-gala` (reusado — ver §9, es el que peor calza y puede que convenga no vender Full en esta).

| Token | Valor |
|---|---|
| `accent` | `#7fb5e6` |
| `accentSoft` | `#eaf4fd` |
| `yellow` | `#ffc9a8` |
| `ink` | `#2c3a4a` |
| `bgLight1` | `#f6fbff` |
| `bgLight2` | `#e4f0fa` |
| `dark1` | `#24384f` |
| `dark2` | `#35506d` |
| `dark3` | `#bfe0f7` |

`confetti`: `#7fb5e6`, `#ffc9a8`, `#ffffff`, `#c9b6e4`, `#eaf4fd`, `#a8d5f2`

### 7.4 Jardín del Bebé — `jardin-bebe` · baby shower

- **Dirección visual:** un jardín de invernadero suave — eucalipto, hojas de olivo, macetas de greda, guirnaldas de flores secas, luz filtrada. Estética de mesa dulce real, la que la clienta ya conoce de Pinterest.
- **Qué la separa:** es la única **neutra y botánica**, sin celeste ni rosado fuerte; la que sirve cuando no se sabe el sexo del bebé. Contra Safari de Peluche, que también es verde: Safari es fieltro, sol y aventura; esta es botánica, sombra y quietud.
- **Los 6:** Conejo del Jardín, Erizo entre Hojas, Mariposa de Papel, Rama de Eucalipto, Maceta Florida, Pajarito de Rama.
- **Estrella:** Conejo del Jardín · juego propio `escudo`.
- **`diploma`:** "Padrino del Jardín" / "Madrina del Jardín" — **ojo:** el campo `diploma` es un solo string, así que hay que elegir uno neutro: **"Guardián del Jardín"**.
- **`fullGame.stage`:** `beach-luau` (reusado, el que menos desentona por ser natural y cálido).

| Token | Valor |
|---|---|
| `accent` | `#8fae9b` |
| `accentSoft` | `#eef4ef` |
| `yellow` | `#edc4c0` |
| `ink` | `#2f3a33` |
| `bgLight1` | `#fbf8f3` |
| `bgLight2` | `#e9efe6` |
| `dark1` | `#26332b` |
| `dark2` | `#3b4d40` |
| `dark3` | `#cfe3d3` |

`confetti`: `#8fae9b`, `#edc4c0`, `#fbf8f3`, `#c2a878`, `#cfe3d3`, `#e6d5c3`

### 7.5 Comprobación de que las cuatro se distinguen

El `bgLight1` es lo que más se nota, porque desde el 2026-08-25 pinta el papel de todo el
álbum:

| Temática | `bgLight1` | Lectura del papel |
|---|---|---|
| Circo de Papel | `#fff6ec` | crema cálido |
| Safari de Peluche | `#f6f4e8` | arena |
| Nube de Algodón | `#f6fbff` | azul muy frío |
| Jardín del Bebé | `#fbf8f3` | hueso neutro |
| *(Reino de Hielo, existente)* | `#e3f2fd` | celeste saturado |

Las cuatro nuevas son más pálidas que Hielo y se separan entre sí por temperatura, no por
claridad. Eso es a propósito: un álbum de baby shower no debería competir en saturación
con el kiosco.

### 7.6 Lo que cada temática debe traer (Tabla A, sin negociar)

Por temática, según `docs/TEMATICA-COMPLETA.md`:

| Pieza | Cantidad | Costo |
|---|---|---|
| `fondo-banner.jpg`, `fondo-sala.jpg` | 2 | Barato |
| `<personaje>.jpg` | 6 | Barato |
| `<personaje>-cut.png` | 6 | Muy barato (`remove_background`) |
| `puzzle-<personaje>.jpg` | 6 | Gratis (recorte, sin IA) |
| `roulette/roulette-background-v1.png` | 1 | Gratis (reusa el banner) |
| `musica-fondo.mp3` | 1 | **La pone Luis, no se genera** |
| `saludo-<personaje>.mp4` | 6 | **Caro** |
| `despedida-<slug>.mp4` | 1 | **Caro** |
| Entrada en `themes.json` + `HOMOLOGADAS` del test | — | Gratis |

**28 videos en total** para las cuatro. Ese número es la decisión de presupuesto real de
todo este plan.

---

## 8. Fases de entrega

Cada fase termina con algo que Luis puede abrir y mirar. Ordenadas para que la primera ya
muestre algo.

### Fase 0 — Verificar lo que se hereda (sin escribir código)
**Qué:** correr `npm test` y la batería de backend en el árbol de referencia. Crear en
local una fiesta con tema Carreras, cargar el álbum con el seed y abrir la revista.
Confirmar contra `docs/ALBUM-RECUERDO-PRUEBAS.md` §5 que el papel toma el color del tema.
**Resultado verificable:** salida de los tests + la revista abierta en el navegador, y
una lista corta de "esto se hereda / esto no" corregida contra la realidad.
**Esfuerzo:** ~0,5 sesión. **Sin costo de IA.**

### Fase 1 — La modalidad existe y el admin la puede elegir
**Qué:** migración 010; `baby_shower` pasa a `active` con sus `sections`, `fields` e
`intro`; select de Modalidad en el alta de fiesta; `event-profile.php` en solo lectura;
actualizar `eventProfilePresets.test.mjs` (hoy falla si no se toca).
**Resultado verificable:** crear una fiesta "Baby Shower de prueba" en el admin local y
abrir `admin/event-profile.php?party=<slug>`: aparecen "Semanas de gestación", "Talla de
pañales" y "Lista de regalos en" en vez de "Talla de calzado".
**Nada público cambia todavía.** **Esfuerzo:** ~1 sesión. **Sin costo de IA.**

### Fase 2 — El producto deja de decir "cumpleaños"
**Qué:** `cb_event_vocab()`, los ~9 literales de `invitacion.php`, etiquetas del admin,
`eventType`/`vocab` en el payload de `api.php`, tercer valor de género en las dos
whitelists, `eventVocab.test.mjs`.
**Resultado verificable:** dos invitaciones lado a lado, una `child_birthday` y otra
`baby_shower`, con la misma temática. La primera tiene que quedar **idéntica byte a byte
a la de hoy**; la segunda no puede contener la palabra "cumpleaños" en ninguna parte
(revisable con Ctrl+F sobre el HTML).
**Esfuerzo:** ~1-1,5 sesiones. **Sin costo de IA.**

### Fase 3 — Nube de Algodón, mitad barata
**Qué:** entrada en `themes.json`, 6 retratos, 6 cortes, 6 puzzles, banner, sala, fondo de
ruleta. Sin música, sin videos. `frameBox` calibrado en el admin.
**Resultado verificable:** abrir `index.html?p=<slug>` y recorrer el kiosco: intro,
ruleta girando con los seis, juego, foto compuesta con el marco. Los saludos y la
despedida se saltan solos (todo tiene fallback). Además: la revista del álbum sale con
papel `#f6fbff`.
**Esfuerzo:** ~1 sesión + **14 imágenes generadas**.

### Fase 4 — Nube de Algodón completa
**Qué:** `musica-fondo.mp3` (la aporta Luis), 6 saludos, 1 despedida. Sumarla a
`HOMOLOGADAS` en `themeFlow.test.mjs`. Marcarla completa en `TEMATICA-COMPLETA.md`.
**Resultado verificable:** el recorrido completo con audio y video, y `npm test` en verde
con la temática dentro de la tabla de completas.
**Esfuerzo:** ~0,5 sesión + **7 videos**.

### Fase 5 — Jardín del Bebé completa
Mismo camino que 3+4 en una sola pasada, porque el pipeline ya está probado.
**Resultado verificable:** dos temáticas de baby shower ofrecibles, con dos papeles de
álbum distintos.
**Esfuerzo:** ~1 sesión + **14 imágenes + 7 videos**.

### Fase 6 — Circo de Papel y Safari de Peluche
Las dos infantiles, para `child_birthday`. Van al final a propósito: no bloquean la venta
de la modalidad nueva.
**Resultado verificable:** el catálogo de temáticas del admin pasa de 6 completas a 10.
**Esfuerzo:** ~1,5 sesiones + **28 imágenes + 14 videos**.

### Fase 7 — Invitación y canal comercial de baby shower
**Qué:** `narracion-final-bebe.mp3` (opcional, hay fallback), portada/intro por modalidad,
"Baby shower" en `lib.leads.php:39` y en el `<select>` de `sitio/`, actualizar
`docs/FTP-MANIFEST.md` y `docs/ARQUITECTURA.md`.
**Resultado verificable:** un baby shower pedido desde el formulario público del sitio y
una invitación pública abierta de punta a punta, con su `.ics` diciendo "Baby shower de X".
**Esfuerzo:** ~1 sesión + hasta 3 audios.

### Resumen de esfuerzo

| Fase | Sesiones | Imágenes | Videos | Audios |
|---|---|---|---|---|
| 0 | 0,5 | — | — | — |
| 1 | 1 | — | — | — |
| 2 | 1,5 | — | — | — |
| 3 | 1 | 14 | — | — |
| 4 | 0,5 | — | 7 | — |
| 5 | 1 | 14 | 7 | — |
| 6 | 1,5 | 28 | 14 | — |
| 7 | 1 | — | — | ≤3 |
| **Total** | **~8** | **56** | **28** | **≤3** |

Las Fases 0-2 (3 sesiones, cero costo de IA) dejan la modalidad funcionando con las
temáticas que ya existen. **Se puede vender un baby shower con Reino de Hielo o Aventura
Tropical antes de generar un solo asset nuevo.** Esa es la razón de este orden.

---

## 9. Riesgos y decisiones que necesito de Luis

### Decisiones que bloquean el arranque

1. **¿Sobre qué rama se trabaja?** El árbol principal (`CumpleBooth/`) llega a la
   migración 007. `.worktrees/cumplebooth-protagonista/` llega a 009 y trae el rediseño de
   revista del 2026-08-25. Este plan está escrito contra el segundo. Si se ejecuta sobre
   el primero, la migración no es 010 y falta la mitad del contexto.

2. **"Dos para niños y dos para bebés" — ¿interpreté bien?** Asumí: dos temáticas para la
   modalidad `child_birthday` que ya existe (Circo de Papel, Safari de Peluche) y dos para
   `baby_shower` (Nube de Algodón, Jardín del Bebé). La otra lectura posible es que las
   cuatro sean de baby shower, dos con estética infantil para los niños que asisten. Son
   planes distintos.

3. **Los juegos para invitados adultos.** §5.1 opción A (respetar el estándar) vs B
   (cadena corta solo para baby shower). Recomiendo A y medir. Si Luis ya sabe que un
   adulto no va a jugar dos veces, mejor decidirlo ahora que después del gasto.

### Decisiones de plata

4. **28 videos.** Es el grueso del presupuesto. ¿Van las cuatro temáticas, o partimos con
   las dos de bebé (14 videos) y las infantiles quedan para después?

5. **¿Se vende plan Full en baby shower?** El Show 3D es un juego de ritmo de concierto.
   Para una tarde de baby shower puede no calzar. Si la respuesta es "no", las dos
   temáticas de bebé se declaran Booth-only y nos ahorramos el problema del `stage`
   (punto 6).

6. **Los `stage` reusados.** `TEMATICA-COMPLETA.md` dice que una temática sin `stage`
   propio "sale con la paleta de K-Pop en otro mundo: no rompe, pero se ve prestado".
   Propuse reusar `podium-night`, `backyard-fiesta`, `ice-gala` y `beach-luau` para no
   tocar el Show 3D. Es un compromiso consciente: **Nube de Algodón con `ice-gala` va a
   verse como Reino de Hielo**. Las salidas son (a) aceptarlo, (b) no vender Full en esas
   temáticas, (c) autorizar tocar `SHOW_STYLES` + `allowedStages` (front) +
   `$allowedStages` (`public/lib.php`) — los tres lugares, o no pasa.

### Decisiones de contenido y privacidad

7. **Ecografías.** ¿Se permite subir una como foto del bebé en la ficha? Es dato de salud
   de una persona que no puede consentir. `cc_featured_people` ya tiene
   `photo_public_consent` y `photo_ai_consent` separados, así que técnicamente se puede.
   **No lo metí en el preset a propósito** — necesita una decisión explícita, no un campo
   que aparece.

8. **El tercer valor de género.** Propuse `'x'` = "aún no lo sabemos", visible solo en
   modalidad baby shower. ¿Está bien redactado así, o Luis prefiere "es sorpresa"?

9. **Nombres de las cuatro temáticas.** Son propuestas. Si la clienta ya tiene un nombre
   para su fiesta, gana el de ella.

### Riesgos técnicos que ya identifiqué (no necesitan decisión, sí atención)

| Riesgo | Dónde | Mitigación en el plan |
|---|---|---|
| Whitelist silenciosa de género: mandar `'x'` hoy guarda NULL sin error | `lib.invitations.php:134` y `admin/invitations.php:266` — **dos copias** | Tocar las dos, y agregar test |
| `eventProfilePresets.test.mjs:31-33` afirma que solo `child_birthday` es `active` | Test existente | Se actualiza en Fase 1 o el build falla |
| El vocabulario se puede quedar a medias en una modalidad nueva | Catálogo JSON | Fallback a `child_birthday` + test de completitud de claves |
| `value_type` no tiene `date` para la fecha estimada | `lib.event-profiles.php:411` | Va como `text`; agregar `'date'` es una línea y la columna es `VARCHAR(20)`, sin migración |
| El nombre `event-profile-presets.json` queda corto al llevar vocabulario | Catálogo | Recomiendo dejarlo y anotarlo en `ARQUITECTURA.md`. La alternativa (dos archivos) crea dos listas que se desincronizan |
| Un baby shower puede quedar marcado `child_birthday` porque nadie abrió la ficha | `admin/index.php` no pregunta la modalidad | Se elige al crear la fiesta y es inmutable (§6) |
| `docs/FTP-MANIFEST.md` desactualizado deja el kiosco en blanco | Regla dura existente | Está en la Fase 7 |
| Sin "Baby shower" en `lib.leads.php:39`, el servicio existe y nadie lo puede pedir | Sitio comercial | Fase 7 |

---

## 10. Pendiente: propuestas visuales del cliente

Luis va a mandar **dos propuestas visuales de una clienta real**. Este plan está armado
para que entren sin rehacerse.

**Dónde entran:** reemplazando o ajustando las temáticas de §7.3 y §7.4 (las de bebé).
Las fichas de §7 son **propuestas, no compromisos**: si las de la clienta son mejores,
ganan las de ella.

**Qué hay que sacarle a cada propuesta para convertirla en temática** — es la misma lista
para las dos, y sale de `TEMATICA-COMPLETA.md`:

| # | Dato | De dónde sale de la propuesta |
|---|---|---|
| 1 | `slug` (`a-z0-9-`) | Del nombre, normalizado |
| 2 | `nombre` visible | El nombre que usa la clienta |
| 3 | Los **9 tokens** de color | Muestreados de la imagen: `accent` del color dominante, `bgLight1` del fondo más claro (define el papel del álbum), `ink` del texto más oscuro |
| 4 | Los **6 colores** de `confetti` | Los mismos, más dos de apoyo |
| 5 | **Exactamente 6** "personajes" (objetos o arquetipos, nunca franquicias) | Los elementos decorativos que se repiten en la propuesta |
| 6 | Cuál de los 6 es la **estrella** | El elemento más grande o repetido |
| 7 | `diploma` (un solo string neutro) | Del tono de la propuesta |
| 8 | `fullGame.stage` | De los 6 existentes, o "sin Full" según la decisión 5 de §9 |
| 9 | `publico` | `"bebé"` / `"mixto"` |
| 10 | `franquicia` | `"Original CumpleClick"` |

**Chequeos antes de aceptar una propuesta:**

- [ ] ¿Trae algún personaje con derechos? Si sí, hay que traducirlo a decoración antes de
      generar nada (es la regla que ya costó caro una vez).
- [ ] ¿Su `bgLight1` se distingue de los otros papeles de la tabla de §7.5?
- [ ] ¿Se pueden sacar 6 elementos distintos, o solo 3-4? La ruleta, los tests y el layout
      asumen seis, ni cinco ni siete.
- [ ] ¿Los colores dan contraste suficiente para `ink` sobre `bgLight1`?

**En qué fase entran:** en la **Fase 3**. No bloquean las Fases 0-2, que son las que dejan
la modalidad funcionando. Si las propuestas llegan tarde, se arranca igual.

### Propuesta A — (pendiente)

> Espacio reservado. Al llegar, completar: nombre, slug, los 9 tokens, los 6 confetti, los
> 6 elementos, la estrella, el diploma, el stage, y el resultado de los cuatro chequeos.

### Propuesta B — (pendiente)

> Espacio reservado, mismo formato.

---

## 11. Qué NO verifiqué

Para que nadie tome esto por más de lo que es:

- **No corrí los tests.** Lo que digo de `eventProfilePresets.test.mjs` y
  `themeFlow.test.mjs` sale de leer los archivos, no de ejecutarlos.
- **No abrí el kiosco ni la revista.** Lo del álbum sale de `ARQUITECTURA.md`,
  `AGENTS.md` y `docs/ALBUM-RECUERDO-PRUEBAS.md` §2026-08-25. Por eso la Fase 0 existe.
- **No revisé el estado de PROD.** Según `CumpleBooth/CLAUDE.md`, PROD corre el build del
  2026-07-27 y el cierre del 2026-08-02 nunca se subió. Nada de este plan asume otra cosa.
- **Los 2 hits de `cumplea*` en `public/assets/invitation.css`** no los abrí uno por uno;
  pueden ser nombres de clase, y si lo son no se tocan.
- **No leí `admin/invitations.php` completo** (1.000+ líneas). Verifiqué las líneas de
  género y de `birthday_person_name`, no el resto.
- **Las paletas de §7 son diseño propuesto**, no muestreadas de ninguna referencia. Se
  reemplazan sin culpa cuando lleguen las propuestas de la clienta.
