# Álbum Recuerdo — propuesta técnica (pendiente de aprobación)

Estado: **propuesta, sin una sola línea de código escrita**. Rama
`feat/album-recuerdo`, creada desde `418a970` (cierre Rayo/Carreras/Hielo) para
no pisar el trabajo paralelo de Codex.

Fecha: 2026-08-04. Autor: Claude. Requiere aprobación explícita de Luis antes de
implementar cualquier fase.

---

## 1. Estado Git y documentos leídos

**Git**

- Rama base: `codex/cumpleclick-qa-rayo`, HEAD `418a970`.
- Rama nueva de trabajo: `feat/album-recuerdo` (local, no pusheada).
- `CumpleBooth/` está limpio salvo dos archivos excluidos a propósito en el
  cierre anterior (`NUL;`, `_voces.json`). Los `M` fuera de `CumpleBooth/` son
  de sesiones anteriores de otros agentes; no se tocan.
- Ninguna rama de álbum existe en `origin`.

**Documentos leídos completos**

| Documento | Qué aportó |
|---|---|
| `docs/ARQUITECTURA.md` | Límites de despliegue, tablas `cc_*`, contratos HTTP, storage privado, reglas de video/ffprobe |
| `docs/FASE1.md` | Foto premium, diploma, galería con PIN, cuotas 200 fotos/1 GiB, `cc_leads` y patrón de consentimiento |
| `docs/CUMPLECLICK-HANDOFF-CODEX.md` | Secciones 3, 4, 8 (seguridad verificada), 10, 11 (convenciones de código) |
| `docs/DEPLOY.md` | Cutover, `check-dist-parity.php`, qué nunca va al webroot |
| `docs/FTP-MANIFEST.md` | Formato de entrega y la advertencia de hashes de `assets/` |
| `AGENTS.md` | Memoria compartida, reglas duras, orden de lectura |
| `OPENCODE.md`, `CLAUDE.md` | Punteros; `CLAUDE.md` confirma que PROD corre el build del 2026-07-27 y que el cierre del 2026-08-02 **no** está desplegado |

**Código revisado** (vía graphify + lectura dirigida): `public/lib.php` (2198 L),
`public/upload.php`, `public/ver.php`, `public/invitacion.php`,
`public/galeria.php`, `public/admin/index.php`, `public/admin/invitations.php`,
`src/App.jsx` (`applyThemeVars`), `src/styles.css` (`:root`),
`public/data/themes.json`, migraciones `001`–`006`, `vite.config.js`.

---

## 2. Referencia Umbría — ruta y análisis

**Ruta encontrada:** `C:\wamp64\www\umbria\PrototipoClaudeDesign\`

Archivos del efecto revista:

| Archivo | Líneas | Contenido |
|---|---|---|
| `flipbook.jsx` | 354 | El motor completo del pase de página |
| `magazine-page.jsx` | 213 | Despachador de layouts editoriales |
| `magazine-styles.css` | 425 | Estilos de página, container queries |
| `magazines-data.js` | 507 | Contenido de Umbría (no reutilizable) |

**Cómo está hecho (dato importante):** no usa ninguna librería, ni WebGL, ni
shaders, ni canvas. Es **React 18 + CSS 3D puro**. Exactamente el stack que ya
tiene CumpleClick, así que el port es directo y no agrega dependencias.

Mecánica:

- Dos medias páginas estáticas (`is-left` / `is-right`) más una página
  "voladora" absoluta con `rotateY()` y `transform-origin: left|right center`.
- **Dos modos de interacción**, y esto es lo bueno del prototipo:
  1. Click/teclado → animación por keyframes CSS, 820 ms fijos.
  2. Arrastre con puntero → seguimiento en tiempo real con `requestAnimationFrame`;
     `angle = -180 * progress`, `progress = dx / (ancho/2)`. Al soltar: si pasó
     el 12 % completa el giro con ease-out-cubic proporcional al resto, si no
     vuelve atrás.
- **Todo el estado del arrastre vive en `useRef`**, no en `useState`. Por eso no
  hay un solo re-render de React por frame — es el truco que hace que se sienta
  fluido. Copiar esto tal cual.
- Sombra del doblez dinámica: `opacity = |sin(ángulo)| * 0.68`.
- `bookMode`: alterna doble página (desktop) y página simple (móvil), con
  `pickResponsiveDir()` que decide dirección según en qué mitad tocaste.
- Botones "esquina doblada" como pista visual en reposo.

**Qué se aprovecha y qué no:**

| Pieza | Veredicto |
|---|---|
| Lógica de `flipbook.jsx` (drag + rAF + refs + snap) | ✅ Se porta casi tal cual a ESM |
| Enfoque CSS 3D + container queries (`cqw`) | ✅ Coincide con la regla del proyecto: el lienzo manda, nunca `vw` |
| Patrón despachador de layouts de `magazine-page.jsx` | ✅ Se copia el patrón, **no** los layouts |
| Layouts concretos (masthead, precios, créditos de estudio) | ❌ Son de revista de lujo, no de fiesta infantil |
| `magazines-data.js` | ❌ Contenido de Umbría, se descarta |
| Variables CSS (`--champagne`, `--cream`, serif editorial) | ❌ Se mapean a los tokens de temática de CumpleClick |
| Carga en `window.FlipBook` con Babel en el navegador | ⚠️ Hay que convertir a módulos ESM para Vite |
| Soporte de video en página | ⚠️ No existe en Umbría, es trabajo nuevo |
| Carga diferida | ⚠️ No existe: Umbría monta todas las páginas de una. Con 100+ fotos revienta un celular. Hay que agregar ventana de 3 páginas |

---

## 3. Qué existe hoy en CumpleClick (base sobre la que se construye)

Esto ya está y no hay que inventarlo:

| Necesidad | Qué ya existe |
|---|---|
| Fotos | `cc_photos`: token opaco 128 bits, `storage_key` fuera del webroot, `byte_size`, `width/height`, `sha256`, `deleted_at` |
| Servir foto por token | `ver.php?t=` con token opaco no enumerable |
| Subida validada | `upload.php`: magic bytes, `getimagesizefromstring`, límites, cuota atómica, escritura por `.tmp` + `rename` |
| Token público + hash | `cb_opaque_token()` / `cb_hash_token()` (SHA-256), ya usados por `cc_invitations` |
| Página pública tokenizada | `invitacion.php`: valida `hex{32}`, `X-Robots-Tag: noindex`, estados `published`/expirado, tematizada con `theme.colors` |
| Rate limit persistente | `cb_rate_limit()` sobre `cc_rate_limits`, identidad ya en HMAC (nunca IP en claro) |
| Consentimiento versionado | `cc_leads` (migración 006): `consent_version` + HMAC de IP/user-agent |
| Acceso familiar con PIN | `galeria.php` + `gallery_pin_hash` (`password_hash(HMAC(PIN, pepper))`), 5 intentos/min |
| Admin con CSRF y sesión | `admin/index.php` y `admin/invitations.php` (precedente de página admin separada) |
| Cuotas y retención | `cb_photo_usage()`, 200 fotos/1 GiB por fiesta, `retention.php` a 30 días |
| Tokens de temática | `theme.colors` (9 tokens) + `confetti[]` en `themes.json`, aplicados por `applyThemeVars()` en `App.jsx` |
| QR | `qrcode` npm ya es dependencia, usada en el kiosco |

Lo que **no** existe y hay que construir: álbum, aporte de invitados, curaduría,
revista, cartel QR imprimible, y validación de video sin ffprobe.

---

## 4. Propuesta UX

### 4.1 Admin — sección "Álbum Recuerdo" (por evento)

Página nueva `admin/album.php?party=<slug>`, siguiendo el patrón de
`admin/invitations.php` (no se infla `index.php`, que ya tiene 1397 líneas).
En `index.php` solo se agrega el enlace por fiesta.

Bloque **Recepción**:
- Interruptor "Recibir fotos y videos de invitados".
- Interruptor separado "Permitir videos".
- Fecha de cierre de recepción (por defecto: fecha de fiesta + 7 días).
- Mensaje para invitados (texto libre, 400 caracteres, con un valor sugerido
  editable: "¡Comparte tus mejores fotos y videos de esta celebración!").
- QR grande + URL pública alternativa en texto, copiable.
- Botón "Cartel para imprimir" → abre `cartel-qr.html?t=…`, hoja A4 tematizada
  con QR, la URL en texto y el mensaje.
- Botón "Regenerar enlace" → revoca el token actual (queda en histórico) y crea
  uno nuevo. Confirmación explícita, porque invalida carteles ya impresos.

Bloque **Contenido** (curaduría, ver §4.4).

Bloque **Publicación**:
- Título y subtítulo del álbum.
- Selección de portada.
- Estado: borrador → recibiendo → cerrado → publicado.
- Enlace de la revista + opción de exigir el PIN de galería existente.

### 4.2 Invitado — página de carga (al escanear)

`subir.php?t=<token>` — PHP + JS vanilla, **sin React**. Un invitado en una
fiesta con wifi mala no debe descargar un bundle de React para subir 3 fotos.
Mobile-first, tematizada con los mismos 9 tokens de la fiesta.

Flujo:
1. Cabecera con el nombre del evento y la temática (portada temática de fondo).
2. Explicación breve de qué se puede enviar y los límites reales.
3. Selector de archivos múltiple (`accept` restringido según si los videos están
   habilitados). Vista previa en miniatura antes de enviar, con opción de quitar.
4. Campos opcionales: nombre (máx. 80) y mensaje (máx. 280). Nada más — ni
   teléfono, ni correo, ni apellido.
5. Casilla obligatoria de consentimiento: "Confirmo que tengo derecho a
   compartir estas fotos y videos".
6. Barra de progreso **real** (`XMLHttpRequest.upload.onprogress`), archivo por
   archivo, con reintento por archivo fallido sin perder los ya subidos.
7. Confirmación clara: "Tu recuerdo se agregó al álbum. El organizador lo
   revisará antes de publicarlo."
8. Pantalla final cálida con la marca de la temática y opción "Enviar más".

Estados de error con mensaje humano: recepción cerrada, enlace vencido, enlace
revocado, archivo muy pesado, formato no soportado, video muy largo, demasiados
envíos seguidos.

### 4.3 Revista pública

`album.html?t=<token>` — entrada Vite separada del kiosco.

- **Portada**: foto elegida a sangre, título, subtítulo, fecha, marca visual de
  la temática.
- **Interiores**: hojas con foto a página completa, foto + mensaje del invitado,
  mosaico de 4, página de video, página de separador temático.
- **Pase de página**: motor portado de Umbría — arrastre, click, flechas,
  teclado ←/→, Escape.
- Indicador de página, botón de pantalla completa, botón de una-página/libro.
- **Video**: `preload="none"` + poster; solo la página activa recibe `src`; al
  salir de la página se le quita el `src` y se libera. Nunca más de un video con
  fuente cargada.
- **Carga diferida**: se montan solo las páginas `actual-1`, `actual`,
  `actual+1`. Miniaturas en mosaico, resolución completa solo en la página
  activa.
- **Cierre**: página final de agradecimiento con el arte de la temática.
- **Fallbacks**: sin `preserve-3d` → galería vertical elegante con el mismo
  diseño y los mismos componentes de página, sin volteo. Con
  `prefers-reduced-motion` → transición por fundido en vez de giro 3D.
  Sin JS → mensaje claro, nunca página en blanco.

### 4.4 Curaduría en admin

Grilla de todo el material del evento, con:

- Filtro por origen: **cabina · organizador · invitado**.
- Filtro por estado: pendiente · aprobado · oculto · eliminado.
- Filtro por tipo: foto · video.
- Acciones por elemento: aprobar, ocultar, eliminar (reversible), restaurar.
- Acción masiva: aprobar seleccionados.
- Reordenar por arrastre, con flechas ↑↓ como alternativa accesible.
- Marcar portada.
- Reproductor para revisar videos antes de publicarlos.
- Contadores: cuántos archivos llegaron, cuánto pesan, cuánto queda de cuota.
- Botón "Cerrar recepción".
- Subida manual del organizador por el mismo formulario del admin.

**Nada se borra de verdad desde acá.** Eliminar marca `moderation_status =
'removed'` y guarda `removed_at`; el archivo físico sigue en storage privado y
se puede restaurar. La purga real solo la hace `retention.php` junto con el
resto de la fiesta.

---

## 5. Modelo de datos

Migración nueva `007_event_album.php` + `007_event_album.down.php`. **Aditiva**
(no altera ninguna tabla existente), **reversible** (el down borra solo las
tablas nuevas), portable MySQL/SQLite como las anteriores.

Nomenclatura genérica a propósito: nada dice "cumpleañero" ni "cumpleaños".

### `cc_event_albums` — un álbum por evento

```
id, party_id (FK cc_parties ON DELETE CASCADE, UNIQUE)
status            'draft' | 'collecting' | 'closed' | 'published'
title, subtitle
cover_media_id    FK cc_event_media ON DELETE SET NULL
template_key      VARCHAR(40) DEFAULT 'kids-theme'   ← extensibilidad futura
intake_enabled, intake_videos    TINYINT
intake_closes_at  DATETIME NULL
intake_message    VARCHAR(400) NULL
view_token_hash   VARCHAR(64) UNIQUE NULL
require_pin       TINYINT DEFAULT 0    ← reutiliza gallery_pin_hash existente
published_at, created_at, updated_at
```

### `cc_event_album_tokens` — QR revocable y rotable

```
id, album_id (FK ON DELETE CASCADE)
token_hash   VARCHAR(64) UNIQUE     ← SHA-256; el token en claro nunca se guarda
purpose      'intake' | 'view'
status       'active' | 'revoked'
expires_at   DATETIME NULL
created_at, created_by, revoked_at
```

Tabla aparte en vez de una columna porque regenerar el enlace debe **revocar sin
perder el histórico**, y porque el mismo mecanismo sirve después para el enlace
compartible de la revista.

### `cc_event_media` — el material

```
id, album_id (FK ON DELETE CASCADE), party_id (FK, denormalizado para cuota)
source          'booth' | 'guest' | 'organizer'
media_kind      'image' | 'video'
photo_id        FK cc_photos ON DELETE CASCADE, NULL   ← NO duplica fotos de cabina
storage_key     VARCHAR(255) UNIQUE NULL               ← solo guest/organizer
thumb_storage_key, poster_storage_key
original_name, mime, byte_size, width, height, duration_seconds
sha256          VARCHAR(64)          ← deduplicación
contributor_name     VARCHAR(80) NULL
contributor_message  VARCHAR(280) NULL
moderation_status    'pending' | 'approved' | 'hidden' | 'removed'
sort_order      INT
consent_version VARCHAR(20) NULL
uploader_hmac   VARCHAR(64) NULL     ← HMAC, jamás IP en claro
created_at, reviewed_at, reviewed_by, removed_at
```

**Decisión clave de no-duplicación:** las fotos de cabina **no se copian**.
`photo_id` apunta a `cc_photos` y el álbum solo aporta orden, aprobación y
portada. Un mismo archivo no existe dos veces ni en disco ni en base.

**Compatibilidad:** `cc_parties`, `cc_guests`, `cc_photos`, `cc_rate_limits` y
las migraciones 001–006 quedan intactas. `retention.php` necesita un añadido
para purgar también `cc_event_media` de la fiesta anonimizada.

---

## 6. Seguridad, privacidad y límites

### Token del QR

- 128 bits de `random_bytes` vía `cb_opaque_token(16)` → `hex{32}`, no adivinable
  ni enumerable.
- Se guarda **solo el SHA-256** (`cb_hash_token()`), igual que `cc_invitations`.
- Ámbito: un único álbum, propósito `intake`. **Cero acceso de lectura** — la
  página de carga nunca lista lo que ya se subió, no muestra nombres de
  invitados, no da acceso al admin ni a otra fiesta.
- Revocable en un click; vencimiento propio; verificación en cascada: token
  activo → no vencido → álbum `collecting` → `intake_enabled` → fiesta activa →
  `intake_closes_at` no pasado.

### Validación en backend (nunca se confía en la extensión)

- Imágenes: magic bytes + `getimagesize`. Solo **JPEG, PNG, WEBP**. El nombre
  guardado es `<aleatorio>.<extensión-canónica-del-sniff>`, nunca el nombre del
  invitado.
- Videos: MP4/H.264 exclusivamente, `cb_sniff_mp4()` más lectura real de
  metadatos (ver riesgo de ffprobe en §9).
- Storage fuera de `DocumentRoot`, dentro de `photo_dir`. Se sirve solo por
  `ver-media.php?t=` con `nosniff` y `Content-Disposition`; nunca se incluye ni
  se ejecuta lo subido; `.htaccess` ya bloquea el acceso directo.
- Escritura atómica `.tmp` + `rename`, permisos `0660`, igual que `upload.php`.
- Deduplicación por `sha256`: si el mismo archivo llega dos veces, no se guarda
  dos veces.

### Privacidad

- Consentimiento obligatorio y versionado (`album-intake-v1`), patrón `cc_leads`.
- Del invitado se guarda solo lo que él escribe (nombre de pila opcional y
  mensaje opcional) más un **HMAC** de IP/user-agent para anti-abuso. Nunca IP
  en claro, nunca teléfono ni correo.
- **Nada subido se muestra públicamente sin aprobación del organizador.** Doble
  condición: `moderation_status='approved'` **y** álbum `status='published'`.
- `X-Robots-Tag: noindex, nofollow` en carga, revista y cartel.
- Recomendación fuerte: la revista con niños de terceros debería ir con token
  **más** el PIN de galería ya existente. Ver decisión D6 en §9.

### Límites propuestos (a aprobar, no asumidos)

| Límite | Propuesta | Razón |
|---|---|---|
| Fotos por envío | 10 | Suficiente sin llenar disco |
| Peso por foto | 12 MiB | Un celular moderno saca 4–8 MiB |
| Videos por envío | 2 | |
| Peso por video | 40 MiB | |
| Duración de video | 30 s | "videos cortos" |
| Resolución de video | ≤1920 px lado mayor | |
| Total por álbum | 400 archivos / 3 GiB | Hoy la fiesta tiene 200/1 GiB; el álbum necesita más |
| Rate limit de carga | 20 envíos / 10 min, bloqueo 15 min | Reutiliza `cb_rate_limit()` |
| Vigencia del token | fecha de fiesta + 7 días | Editable por evento |

### Rendimiento

- Miniaturas con GD al subir: 640 px lado mayor, JPEG q80. Sub-segundo, sin
  dependencias.
- La revista pide miniaturas en mosaico y resolución completa solo en la página
  activa.
- Ventana de 3 páginas montadas; el resto ni existe en el DOM.
- Un solo `<video>` con `src` a la vez.
- `loading="lazy"` + `decoding="async"` en todo lo que no es la página activa.

---

## 7. Coherencia visual con la temática

### Fuente única de verdad

Los tokens visuales ya existen y no hay que inventar nada:

- `public/data/themes.json` → `theme.colors` (9 tokens: `accent`, `accentSoft`,
  `yellow`, `ink`, `bgLight1`, `bgLight2`, `dark1`, `dark2`, `dark3`) y
  `confetti[]`.
- `public/themes/<slug>/` → fondos, `*-cut.png`, `grupo-personajes.png`, pósters.
- `src/App.jsx` → `applyThemeVars()` los vuelca a variables CSS.
- `src/styles.css` `:root` → los defaults (Carreras) y `--radius`, `--font`.

**Propuesta de capa compartida** (esto es lo que pide la regla "si faltan tokens
reutilizables, proponer una capa que también beneficie a las pantallas
existentes sin alterar su apariencia"):

1. Extraer `applyThemeVars()` de `App.jsx` a `src/themeVars.js` y que el kiosco
   la importe. **Misma función, mismos valores, cero cambio visual** — solo deja
   de estar duplicada cuando el álbum la necesite.
2. Agregar `cb_theme_css_vars(string $themeSlug): string` en `lib.php`, que
   emite exactamente los mismos 9 tokens como `<style>` en línea para las
   páginas PHP (carga de invitado, cartel). Precedente: `invitacion.php` ya lee
   `$colors['accent']` a mano; esto lo generaliza.
3. **Prohibido** hardcodear un color por temática dentro del álbum. Si un diseño
   necesita un color que no existe en los 9 tokens, se deriva con `color-mix()`
   a partir de ellos (patrón ya usado en `styles.css` para el overlay de copos).
4. Tipografía: Baloo 2 600/700/800 autoalojada ya está; el álbum usa `--font`.
   Ninguna fuente nueva, ningún CDN.

### Tabla de correspondencia

| Tema | Tokens y recursos existentes a reutilizar | Aplicación en Álbum Recuerdo |
|---|---|---|
| **Carreras** | `colors` (#e8000d / #ffb800 / #b30009), `confetti[]`, `fondo-banner.jpg`, `fondo-sala.jpg`, `fondo-pantalla-circuito.jpg`, `grupo-personajes.png`, 6 `*-cut.png` | Portada sobre `fondo-banner`; separadores con banda a cuadros derivada de `--yellow`/`--ink`; marcos de foto tipo tablero; página final con `grupo-personajes.png`; QR y carga con degradado `--dark1 → --dark2` |
| **Hielo** | `colors` azules, `confetti[]` (incluye blanco), `fondo-banner.jpg`, `fondo-juego-nieve.jpg`, `entrada-palacio-hielo-poster.jpg`, 6 `*-cut.png` | Portada sobre `entrada-palacio-hielo-poster`; brillo y copos reutilizando el CSS de partículas que ya existe en el kiosco; marcos con borde `--dark3` (celeste hielo); separadores de escarcha |
| **Tropical** | `colors` cálidos, `fondo-banner.jpg`, `fondo-juego-playa.jpg`, `artist-teaser.jpg`, `*-cut.png` | Portada sobre `fondo-juego-playa`; separadores con vegetación recortada del banner; página de video estilo "postal"; cierre con `artist-teaser` |
| **K-Pop** | `colors` neón, `fondo-banner.jpg`, `fondo-juego-escenario.jpg`, `entrada-escenario-poster.jpg`, `escenario-teaser.jpg` | Portada sobre `entrada-escenario-poster`; glow de `--accent` en bordes de página; sección de videos como "backstage"; folios con tipografía de escenario |
| **Héroes** | `colors`, `fondo-banner.jpg`, `fondo-juego-ciudad.jpg`, `*-cut.png` con nombres genéricos (arana, hierro, capitan, trueno, pantera, gigante) | Portada estilo cómic sobre `fondo-juego-ciudad`; páginas como viñetas con borde `--ink` grueso; onomatopeyas tipográficas. **Sin generar ni copiar personajes protegidos** — solo se usa el arte ya aprobado del proyecto |
| **Familia Canina** | `colors`, `fondo-banner.jpg`, `fondo-juego-patio.jpg`, `grupo-personajes.png`, `portrait-stage-v2.png` | Portada sobre `fondo-juego-patio`; marcos redondeados con `--radius`; separadores con huellas derivadas de `--accent`; cierre con `grupo-personajes.png` |

Las 6 temáticas sin assets (Mickey, Cachorros, Princesas, Dinos, Sirenas,
Juguetes) no se tocan: el álbum lee lo que hay y, si falta un fondo, cae al
degradado de tokens sin romperse.

---

## 8. Extensibilidad a otros tipos de evento

Lo que queda listo sin construirlo todavía:

- Nombres genéricos en base y código: `cc_event_albums`, `cc_event_media`,
  `cc_event_album_tokens`, `contributor_name`, `source`. Ni una tabla, ruta o
  variable dice "cumpleañero".
- `template_key` en `cc_event_albums`, con `kids-theme` como primera y única
  plantilla implementada. Un registro en `src/album/templates/` mapea
  `template_key` → conjunto de layouts. Agregar bodas es agregar
  `templates/wedding.jsx` y una fila de catálogo, sin tocar el motor del
  flipbook ni el backend.
- La fuente visual sigue siendo `theme_slug` de la fiesta. Para tipos de evento
  que no sean infantiles, más adelante se agrega un `event_type` en `cc_parties`
  que seleccione un catálogo de temáticas distinto — pero **eso no se hace
  ahora** y no se deja código muerto anticipándolo.
- El motor de flipbook, la carga de invitados, la curaduría y la seguridad son
  independientes del tipo de evento: se escriben una vez y sirven para todos.

---

## 9. Riesgos, dependencias y decisiones que requieren aprobación

| # | Riesgo / decisión | Detalle | Propuesta |
|---|---|---|---|
| **D1** | **ffprobe no está disponible** | `cb_inspect_video()` falla cerrado si `ffprobe_path` no está configurado — y **no está ni en `config/cumpleclick.example.php`**. En esta máquina ffprobe no existe (confirmado la sesión pasada). En Hostinger compartido casi seguro tampoco. Sin esto, **todo video de invitado se rechaza**. | Escribir un lector de átomos MP4 en PHP puro (~60 líneas, lee `moov/mvhd` para duración y `tkhd` para dimensiones, sin dependencias). Usar ffprobe cuando exista y el lector como respaldo; rechazar si ninguno funciona. **Requiere tu OK.** |
| **D2** | **HEIC de iPhone** | Los iPhone envían HEIC por defecto y GD no lo lee. Safari suele convertir a JPEG cuando el `accept` lo restringe, pero no siempre. | Restringir `accept` a JPEG/PNG/WEBP y **probar en un iPhone real** antes de prometer nada. Si falla, mensaje claro al invitado. Necesito saber si tienes un iPhone para probar. |
| **D3** | **Poster de video sin ffmpeg** | No se puede extraer el primer frame en el servidor. | Capturar el primer frame en el navegador del invitado (canvas sobre `<video>`) y subirlo como imagen acompañante. Si falla, poster temático genérico. |
| **D4** | **Espacio en disco de Hostinger** | 3 GiB por álbum × N fiestas puede llenar el hosting y afectar al sitio de AutomatizaTech. | Necesito saber el plan real y el espacio disponible antes de fijar la cuota. |
| **D5** | **Vite multipágina** | Agregar entradas cambia `vite.config.js`, el contenido de `dist/`, `check-dist-parity.php` y el manifiesto FTP. Toca el build del kiosco. | Riesgo bajo pero real: hay que verificar que el bundle del kiosco queda idéntico. Se hace en su propio commit, aislado. |
| **D6** | **Privacidad de menores** | El álbum tendrá fotos de niños de terceros. Un enlace solo-token es cómodo pero si se filtra, queda abierto. | Recomiendo **token + PIN de galería** (el mecanismo ya existe y está probado). Tú decides: ¿solo token, o token + PIN? |
| **D7** | **Retención** | Hoy `retention.php` anonimiza y borra a los 30 días. Un álbum de recuerdos probablemente quiera vivir más. | ¿El álbum tiene retención propia (¿90 días?) o sigue los 30 actuales? Si sigue los 30, hay que avisarlo en el admin. |
| **D8** | **Límites de la tabla de §6** | Están propuestos, no asumidos. | Confirmar o corregir antes de implementar. |

**Sin dependencias nuevas de npm ni de PHP.** No se genera multimedia con IA ni
se gasta un solo crédito en esta feature.

---

## 10. Archivos exactos

### Nuevos (14)

| Archivo | Qué es |
|---|---|
| `database/migrations/007_event_album.php` | Migración aditiva |
| `database/migrations/007_event_album.down.php` | Reversa |
| `public/admin/album.php` | Admin: configuración, QR y curaduría |
| `public/subir.php` | Página de carga del invitado (PHP + JS vanilla) |
| `public/album-intake.php` | Endpoint POST de carga |
| `public/album-api.php` | JSON del álbum publicado, por token de vista |
| `public/ver-media.php` | Sirve media de invitado/organizador por token opaco |
| `album.html` | Entrada Vite de la revista |
| `src/album/main.jsx` | Arranque de la revista |
| `src/album/FlipBook.jsx` | Motor portado de Umbría a ESM |
| `src/album/AlbumPage.jsx` | Layouts (portada, foto, mensaje, video, mosaico, cierre) |
| `src/album/album.css` | Estilos de la revista, sobre los tokens de temática |
| `cartel-qr.html` + `src/cartel-qr/main.jsx` | Cartel QR imprimible |
| `src/themeVars.js` | Tokens extraídos de `App.jsx`, fuente única |

### Modificados (9)

| Archivo | Cambio |
|---|---|
| `public/lib.php` | Familia `cb_album_*`, `cb_theme_css_vars()`, lector MP4 sin ffprobe |
| `public/admin/index.php` | Solo el enlace "Álbum" por fiesta |
| `public/admin/_style.css.php` | Estilos de la grilla de curaduría |
| `src/App.jsx` | Reemplazar `applyThemeVars` local por el import de `src/themeVars.js`. **Sin cambio visual** |
| `vite.config.js` | Entradas múltiples |
| `scripts/check-dist-parity.php` | Reconocer los PHP/HTML nuevos |
| `scripts/retention.php` | Purgar también `cc_event_media` de la fiesta anonimizada |
| `docs/ARQUITECTURA.md`, `docs/FASE1.md`, `docs/FTP-MANIFEST.md`, `AGENTS.md` | Documentación |

### Explícitamente NO se tocan

`src/StageConcert3D.jsx`, `src/ThemeWorld3D.jsx`, `src/ToyTrack3D.jsx`,
`src/themeFlow.js`, la lógica de Carreras/Rayo, `public/upload.php`,
`public/ver.php`, `public/api.php`, `public/galeria.php`, y las migraciones
001–006.

---

## 11. Plan por fases

Cada fase es un commit propio en `feat/album-recuerdo`. Nada se mergea ni se
sube a PROD sin tu autorización.

| Fase | Alcance | Pruebas |
|---|---|---|
| **A — Datos y admin** | Migración 007, `cb_album_*`, vista admin: activar/desactivar recepción, generar/revocar token, límites, fecha de cierre, mensaje | Migración up/down en SQLite **y** MySQL; CSRF; tests backend; nada público todavía |
| **B — Carga de invitados** | `subir.php`, `album-intake.php`, `ver-media.php`, validación real, miniaturas, rate limit, consentimiento | Negativos obligatorios: PHP renombrado a `.jpg`, path traversal, HEIC, video largo, sin consentimiento, token revocado, fuera de fecha, rate limit. Prueba en iPhone y Android reales |
| **C — Curaduría** | Aprobar/ocultar/eliminar/restaurar, reordenar, portada, título/subtítulo, filtros, contadores, cerrar recepción, subida del organizador | Reversibilidad de la eliminación; que nada no-aprobado se filtre a la API pública |
| **D — Revista** | `album.html`, FlipBook portado, layouts, tema, video en página, carga diferida, fallbacks, teclado/swipe/pantalla completa | Chrome real en móvil y desktop; sin WebGL; `prefers-reduced-motion`; álbum de 100+ fotos; consola limpia |
| **E — Cierre** | Cartel QR, ajuste fino por las 6 temáticas, build, `check-dist-parity.php`, manifiesto FTP | QA visual por temática; `npm test`; paridad de dist; verificar que el kiosco quedó idéntico |

---

## Pendiente

Aprobación explícita de Luis sobre: las decisiones **D1–D8**, el modelo de
datos, la lista de archivos y el plan por fases. Hasta entonces, ni una línea
de código.
