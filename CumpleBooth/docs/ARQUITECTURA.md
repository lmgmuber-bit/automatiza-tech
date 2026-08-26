# CumpleClick — arquitectura vigente

CumpleClick by AutomatizaTech es una SPA de kiosco más un backend PHP 8+ sin
framework. Producción usa `/cumpleclick/`; `CumpleBooth` permanece como nombre
técnico. WordPress/AutomatizaTech y CumpleClick no comparten BD ni credenciales.

## Límites de despliegue

```text
public_html/cumpleclick/        artefacto dist/: HTML, JS, CSS, PHP y assets
<directorio privado>/config.php CUMPLECLICK_CONFIG_FILE; secretos y hashes
<directorio privado>/photos/    PNG compuestos, nunca servidos directamente
<directorio privado>/state/     índices JSON solo para rollback temporal
<directorio privado>/backups/   snapshots de import/cutover
<app privado>/scripts|database  CLI y migraciones; nunca dentro del webroot
```

Vite copia `public/` a `dist/`. `scripts/check-dist-parity.php` impide publicar
PHP o `.htaccess` obsoletos. HTTPS se fuerza en el vhost/proxy con host canónico;
la app nunca construye redirects o URLs desde `HTTP_HOST`.

## Persistencia

`themes.json` y los assets temáticos son catálogo versionado. El estado mutable
vive en MySQL/InnoDB/utf8mb4:

- `cc_parties`: slug inmutable, tema, fecha, actividad, override `frame_box_json`,
  hash de PIN y marcas de retención.
- `cc_guests`: invitados ordenados por fiesta.
- `cc_photos`: token opaco, storage key privada, tamaño, dimensiones y SHA-256.
- `cc_rate_limits`: buckets HMAC; nunca almacena IP en claro.
- `cc_theme_prompts`: prompts privados editables por temática + asset visual;
  la clave de asset se valida contra la whitelist versionada y nunca llega a la API.
- `cc_leads`: solicitudes comerciales del sitio público con referencia opaca,
  consentimiento versionado y huellas HMAC de IP/user-agent; no comparte tablas
  ni credenciales con WordPress.
- `cc_event_albums`, `cc_event_album_tokens`, `cc_event_media`: Álbum Recuerdo
  por evento. Nomenclatura genérica a propósito, para servir después a bodas,
  baby shower o corporativos sin renombrar. Los tokens se guardan solo como
  SHA-256 y son revocables sin perder el histórico. Las fotos de cabina **no se
  copian**: `cc_event_media.photo_id` referencia `cc_photos` y el álbum solo
  aporta orden, aprobación y portada.
- `cc_event_profiles`, `cc_event_profile_sections`, `cc_featured_people`,
  `cc_event_profile_fields`, `cc_event_profile_media` y
  `cc_event_profile_generations`: Perfil del protagonista opcional por fiesta.
  Admite varias personas, campos/secciones ordenables, consentimiento público e
  IA por separado y cotizaciones aprobables sin invocar al proveedor. El
  catálogo visual/textual vive en `event-profile-presets.json`, con cinco temas
  infantiles activos y fallback para temas futuros.
- `cc_schema_migrations`: versiones aplicadas.

`storage_mode=db|json` permite rollback temporal, sin doble escritura. La
importación JSON es idempotente, valida slugs/temas/invitados/frame/PIN, crea
backups privados fechados y aplica el reemplazo dentro de una transacción.

## Contratos HTTP

- `GET api.php?p=<slug>` conserva `ok`, `party` y `theme`; `party.frameBox`
  siempre llega resuelto (override de fiesta o default de tema). Nunca devuelve
  PIN, hash, pepper, franquicia interna o secretos.
- `POST upload.php` recibe `{image, name, party}`. Acepta data URL PNG estricta,
  máximo 8 MiB y 4096×4096, fiesta activa, 30/10 min y cuota atómica de 200
  fotos o 1 GiB. Devuelve `{ok:true,url}`.
- `GET ver.php?t=<token-128-bit>` muestra/descarga la foto desde storage privado.
  Los enlaces legacy `?p=&f=` siguen en modo solo lectura.
- `GET/POST galeria.php?p=<slug>` usa PIN, sesión corta, CSRF y límite 5/min.
- `POST sitio/api/contacto.php` recibe JSON del formulario comercial, exige
  consentimiento, valida tamaño/tipos/campos, aplica honeypot y rate limit
  persistente 5/10 min y devuelve una referencia pública `CC-...`; no envía ni
  expone IDs incrementales.
- `GET subir.php?t=<token-128-bit>` es la página de carga del invitado y
  `POST album-intake.php` recibe **un archivo por petición**. Valida por bytes
  (lista blanca JPEG/PNG/WEBP/MP4), exige consentimiento versionado, aplica
  30 archivos/10 min y deduplica por SHA-256. El nombre en disco lo genera el
  servidor; el del invitado nunca construye rutas.
- `GET ver-media.php?t=<token>&v=full|thumb|poster` sirve material aportado.
  Cierra por defecto: sin sesión de admin solo entrega material `approved` de
  un álbum `published`.
- `GET/POST album-api.php` entrega el álbum publicado por token de lectura, y
  con `?cartel=1` los datos del cartel por token de aporte. El PIN reutiliza el
  de galería y la sesión `cc_gallery`.
- `admin/` usa `password_verify`, CSRF, sesión regenerada, cookie HttpOnly +
  SameSite Strict, 2 h de inactividad/12 h absolutas y logout POST.
  `admin/album.php` concentra recepción, QR, curaduría y publicación; cada
  acción es un POST con CSRF, sin enlaces GET que muten estado.

La vista de temáticas conserva sus cards y añade una ficha privada con inventario,
miniaturas, peso/dimensiones y prompts asociados. Solo permite editar prompts de
slots JPG/PNG conocidos; rechaza path traversal, textos de más de 20.000 bytes y
nombres internos de franquicia/personaje. `scripts/import-theme-prompts.php` migra
los 78 prompts asociados desde Markdown, con dry-run por defecto.

- `GET invitacion.php?t=<token>` incorpora el perfil solo cuando el feature flag,
  el perfil y el contenido público están activos; sin ellos conserva exactamente
  el contrato anterior. `GET event-profile-media.php?t=<token>&mt=<token>` exige
  una invitación publicada, vigente y del mismo evento antes de servir foto,
  poster o video desde storage privado.
- El intro cinematográfico de la invitación se activa por convención, no por
  condicionales de tema: `public/themes/<slug>/invitation/intro-invitacion-wow-v1.mp4`
  y su póster opcional. Así las cinco temáticas actuales y las futuras pueden
  incorporarlo sin cambiar PHP o JavaScript. Si falta el MP4, el sobre conserva
  su flujo anterior; si existe, reproduce con audio y permite `Omitir intro`.
- `admin/event-profile.php?party=<slug>` administra textos, varias personas,
  orden, visibilidad, consentimientos y el borrador/cotización del intro. Todas
  las mutaciones usan sesión admin, CSRF, rate limit y ownership de la fiesta.

## Frontend

El build tiene **tres entradas** (`vite.config.js`): `index.html` (kiosco),
`album.html` (revista del Álbum Recuerdo) y `cartel-qr.html` (cartel QR
imprimible). Están separadas a propósito: la tablet no descarga el código del
álbum y quien abre el álbum desde su celular no descarga `three.js`. Los 9
tokens de color de la temática los comparte `src/themeVars.js`, y
`cb_theme_css_vars()` emite los mismos para las páginas PHP.

**Al desplegar:** los bundles se llaman `main-*`, `album-*` y `cartel-*`. Los
`index-*.js` de builds anteriores quedan huérfanos y hay que borrarlos del
servidor.


La geometría `x/y/w/h` es normalizada al canvas 1080×1920 y exige ancho/alto
mínimos 0.05. El admin calibra visualmente y persiste en BD; el kiosco consume
exactamente la API, nunca `localStorage` para frames. El compositor centra un
recorte cuadrado con inset dentro del marco decorativo ya presente en el fondo y
coloca el personaje sorteado, escalado, en el centro de la pista inferior. No
dibuja un segundo borde sobre la imagen base. La cámara se considera lista solo al
recibir metadatos/fotogramas y ofrece selector cuando existen varios dispositivos.
Baloo 2 (600/700/800) está autoalojada en WOFF2 y se espera con `document.fonts`
antes de dibujar. Intro y QR conservan el lockup CumpleClick; Preview y Diploma
dibujan únicamente el SVG oficial AT como marca de agua inferior izquierda, al
42 % de opacidad, sin cápsula ni texto recreado.

Si el upload falla, el PNG local se conserva, se muestra un mensaje sanitizado
y se permite reintentar; jamás se genera un QR con texto falso.

El sitio comercial vive aislado en `sitio/`. Su formulario persiste en la misma
BD privada de CumpleClick mediante la migración `006_public_leads`; WhatsApp es
un canal comercial complementario y su número real continúa pendiente de
configuración, no una dependencia para registrar solicitudes.

## Seguridad y ciclo de vida

### Modalidad baby shower y predicciones

`event_type` conserva `child_birthday` como default compatible y activa la rama
`baby_shower` sólo cuando el evento lo declara. La cabina sigue resolviendo el
evento por `public_slug`; para baby shower omite ruleta, videos de personaje y
Show 3D, y encadena predicción, juego corto, guardado, foto, revelación, QR y
recuerdito. El router histórico de cumpleaños no cambia.

`POST prediction-api.php` vuelve a resolver la fiesta activa y guarda la apuesta
en `cc_predictions.party_id`; no acepta ids internos desde el cliente. Esta
propiedad por evento es deliberada porque una fiesta puede tener varias
invitaciones. Los enlaces del tablero siguen siendo invitation-owned: un token
opaco de `cc_invitation_tokens` resuelve invitación → evento y
`GET predicciones.php?t=<token>` reúne todas las apuestas de la fiesta. La
decisión completa está en
`docs/DECISION-PREDICCIONES-POR-EVENTO-2026-08-25.md`.

El guardado usa una clave aleatoria por recorrido y conserva sólo su hash con
unicidad por evento, por lo que doble toque y reintento de red son idempotentes.
Cuando cambia la modalidad de una fiesta, sus invitaciones vinculadas se
sincronizan; una invitación sin fiesta conserva su modalidad independiente.

La lista futura de regalos queda preparada en `cc_gift_items`, sin interfaz ni
mutaciones públicas en esta fase. Sus datos y sus tokens pertenecen a la
invitación.

La configuración externa es obligatoria para admin/uploads. PDO usa prepared
statements nativos y errores fail-closed. PIN =
`password_hash(HMAC(PIN, pepper))`; duplicar limpia PIN. `Permissions-Policy`
efectiva: `camera=(self), microphone=(), geolocation=()`.

`scripts/retention.php` es dry-run por defecto. A los 30 días desde la fecha de
fiesta —o creación si falta— desactiva y anonimiza la fiesta, borra invitados y
PIN, marca metadata de fotos y elimina archivos, reintentando un unlink fallido.
La misma retención elimina por cascada datos del perfil y después borra sus
archivos privados; el script sigue siendo dry-run salvo `--apply` explícito.


## Estudio manual de producción de temáticas (2026-07-26)

El catálogo versionado continúa en `public/data/themes.json`; los archivos
públicos viven en `public/themes/<slug>/` y los prompts privados en
`cc_theme_prompts`/`cc_theme_prompt_history`. El admin construye una allowlist a
partir del contrato del tema: fondos, personajes, recortes, juegos, experiencia
inmersiva, videos de bienvenida/revelación/despedida, Marketing y audio.

Los slots pueden usar subcarpetas seguras, pero nunca rutas libres. Una carga por
card renombra al destino versionado y valida contenido real. Los videos requieren
MP4 H.264, dimensiones 360×640–2160×3840, ffprobe disponible y duración máxima
por slot (10 s para saludos, 15 s para experiencias/Marketing). El flujo aplica
CSRF, sesión admin y rate limit persistente 30 subidas/10 minutos.

`franquicia` es metadata estrictamente administrativa y no forma parte del
payload de `api.php`. Los nombres visibles de temas/personajes sí se publican.

Los juegos aceptados son `copos`, `armar-muneco`, `fichas`, `ritmo`, `escudo`
y `mundo3d`.
`ritmo` publica 3–5 carriles; `escudo` puede publicar una imagen de fondo pero
nunca `cols/filas`; `copos` permite hasta ocho emojis temáticos. Todos mantienen
botón de omitir, objetivos táctiles de al menos 56 px, reduced-motion y una sola
ruta de salida protegida con `doneRef`.

`mundo3d` es una misión premium de tres carriles implementada por
`src/ThemeWorld3D.jsx`. Cada temática terminada declara `fullGame` en el
catálogo, pero `cb_build_theme_payload()` solo lo publica cuando
`party.service_plan=full`; una fiesta Booth no recibe esa configuración. El
frontend la añade como último bonus, conserva el personaje exacto de la ruleta
mediante su `*-cut.png` y dispone renderer, geometrías, materiales y texturas al
salir. Los seis mundos activos son `turbo-track`, `puppy-park`,
`tropical-wave`, `ice-bridge`, `neon-stage` y `hero-city`. `?fx3d=0`, WebGL no
disponible o reduced-motion activan una salida segura sin bloquear el flujo.
