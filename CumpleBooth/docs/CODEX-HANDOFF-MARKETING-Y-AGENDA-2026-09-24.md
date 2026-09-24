# Handoff a Codex — Marketing de 30 días + Agenda de eventos (2026-09-24)

Escrito por Claude como orquestador el 2026-09-24. **Luis Miguel** es el superusuario, el aprobador
final y quien paga: nada que gaste créditos, toque PROD o se mergee pasa sin su "ok" explícito.
Codex es el ejecutor de los dos tickets de este documento. Todo lo que dice está **medido contra el
repositorio en `main` = PROD** (`21c0c1a`, 23-sep) y contra lo que se hizo en las dos primeras
fiestas (Samantha y Luciano, 13-sep-2026). No hay nada que adivinar: si algo no está aquí, está en
los archivos que se citan; si tampoco está ahí, se pregunta a Luis antes de suponer.

Tickets: `Docs/ORCHESTRATION/AT-CUMPLECLICK-017.yaml` (marketing) y `AT-CUMPLECLICK-018.yaml` (agenda).

---

## 0. Cómo trabajar sin pisar a nadie

1. **Partir siempre de `main`.** `main` es igual a PROD desde el 15-sep y se mantiene así: se
   despliega primero, se mergea después. No partir de ramas `feat/*` ni `codex/*` viejas.
2. **Una rama por ticket, un PR por ticket, worktrees separados:**
   - 017 → `codex/marketing-contenido`
   - 018 → `codex/agenda-eventos`
   `git worktree add ../wt-agenda codex/agenda-eventos` evita que un `checkout` pise al otro.
   Hacer **018 primero** (es más chico y no gasta nada) y 017 después, o los dos en paralelo en
   sus worktrees. Nunca los dos en la misma rama.
3. **Archivos compartidos, y cómo tocarlos para que los dos PR mergeen sin conflicto:**

   | Archivo | 018 (agenda) | 017 (marketing) |
   |---|---|---|
   | `database/migrations/` | crea **`024_agenda_eventos.php`** (+ `.down.php`) | crea **`025_marketing_contenido.php`** (+ `.down.php`) |
   | `database/aplicar-0NN.php` | crea **`aplicar-024.php`** (copia de `aplicar-023.php`) | crea **`aplicar-025.php`** (ídem) |
   | `public/lib.admin-usuarios.php` `CB_ADMIN_MODULOS` | agrega la clave `agenda` **justo después de `invitados`** | agrega la clave `marketing` **al final, después de `perfil`** |
   | `public/admin/_acceso.php` `admin_nav()` | agrega la pestaña Agenda **justo después de Fiestas** | agrega la pestaña Contenido **justo después de Finanzas** |
   | `docs/FTP-MANIFEST.md` | **anexa** una sección al final con fecha y ticket | **anexa** una sección al final con fecha y ticket |
   | `CLAUDE.md`, `CumpleBooth/CLAUDE.md`, `AGENTS.md` | no tocar | no tocar |

   El que mergee segundo hace `git rebase main` y resuelve lo trivial. Todo lo demás de cada ticket
   va en archivos **nuevos** (`admin/agenda.php`, `lib.agenda.php`, `admin/contenido.php`,
   `lib.marketing.php`, sus pruebas y sus docs), así que no hay más roce que ese.
4. **Nada se despliega desde Codex.** Codex entrega rama + PR + lista FTP exacta (ruta local →
   destino en PROD, `OBLIGATORIO`/`OPCIONAL`, orden). Claude despliega por SSH con el go de Luis
   (reconocer, respaldar en `~/respaldos/`, subir, `php -l`, verificar desde afuera) y después
   mergea. 🔴 **La migración va antes que el código**, o la consulta de fiestas falla entera.
   **Migraciones:** en local se aplican con `php scripts/migrate.php` (runner, tabla
   `cc_schema_migrations`); en PROD Claude corre por SSH un script de un solo uso por migración,
   `database/aplicar-0NN.php`, calcado de `database/aplicar-023.php` (comprueba la versión, corre
   la migración, la registra e imprime las tablas). Cada ticket entrega el suyo.
   **CSRF y helpers del admin:** cada página copia sus helpers (`admin_csrf_token()`,
   `admin_csrf_check()`, `admin_csrf_field()`, `h()`), como en `admin/aceptaciones.php` líneas
   26–45; no hay un include compartido para eso y no es el momento de crearlo.
5. **Sin secretos, nunca:** las claves viven en `C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt`
   y se leen por rótulo. No van a `.env`, config, docs, logs ni al chat. El repositorio es público.
6. **El CI de GitHub no corre** (cuenta bloqueada por facturación desde el 21-sep): correr en local
   `php -l` con el PHP de WAMP (`C:\wamp64\bin\php\php8.x\php.exe`), las pruebas de `tests/` y un
   escaneo de secretos sobre el diff antes de cada push.
7. **Skills que Codex puede usar** (y conviene): `superpowers` (brainstorming → writing-plans →
   test-driven-development → verification-before-completion), `design-taste-frontend` y
   `frontend-design` para decisiones de composición **dentro de los tokens del admin** (no traer
   Tailwind, React ni otra tipografía), `higgsfield-studio` con `references/api-rest.md` para
   generar por API, `birthday-photobooth` para el conocimiento del producto, y las de marketing que
   tenga a mano (`marketing-plan`, `social`, `content-research-writer`) para la estrategia.
8. **Al cerrar cada ticket:** resumen, pruebas corridas con su resultado, estado git, lista FTP,
   qué quedó **sin probar** (sección "No probado" como en el manifiesto), costos reales gastados con
   request_id, y preguntas abiertas para Luis. Claude revisa el PR con `/code-review` antes del deploy.

---

## 1. Ticket AT-CUMPLECLICK-017 — Estrategia de 30 días + módulo "Contenido" del admin

### 1.1 Objetivo

Que CumpleClick tenga **un plan de contenido de 30 días para Instagram (@Cumple_Click)** pensado
por un community manager experto en marcas infantiles chilenas, con **cada pieza lista para
publicar** (copy, hashtags, primer comentario, imagen o video) y **un módulo en el admin** donde
Luis, como superusuario, vea el calendario, apruebe, marque publicado y anote resultados. Meta del
mes: crecer en seguidores y en mensajes por WhatsApp; lo que se mide está en 1.4.

### 1.2 Contexto que ya existe (usarlo, no rehacerlo)

**Producto y marca**
- Qué es CumpleClick: invitación interactiva (música, voz de Alice, confirmación de los papás y
  lista de regalos), cabina de fotos en tablet con personajes por temática (ruleta, rompecabezas,
  Asómate, diploma, impresión al instante), juegos 3D en la tablet, foto grupal, y el Álbum
  Recuerdo en línea que los papás reciben con enlace y PIN. Skill `birthday-photobooth` y
  `docs/ARQUITECTURA.md`.
- Temáticas comerciales: hielo (Reino de Hielo), spidey/heroes (Aventura Arácnida), carreras,
  kpop, tropical, familia-canina y tres de baby shower (nube, safari, rosas). Catálogo en
  `public/data/themes.json`; arte real en `public/themes/<tema>/`.
- Planes y precios (fuente única: `design/generadores/arte/piezas.py::PLANES`): Mágico $69.990 →
  **$34.995**, Premium $99.990 → **$49.995**, Baby Shower $59.990 → **$29.995** (50 % de
  lanzamiento), temática a medida +$25.000. Contacto: WhatsApp +56 9 7494 0070 (`wa.me/56974940070`),
  `cumpleclick.com`, `@Cumple_Click` (`instagram.com/cumple_click`). Todo sale de
  `public/data/marca.json`; no escribirlo a mano en el código.
- Identidad: `docs/BRAND-FILOSOFIA-CUMPLECLICK.md` (filosofía "Confeti Contenido") y los tokens del
  admin en `public/admin/_style.css.php` (violeta `#8B5CF6`, tinta `#4C2882`, fucsia `#D6307F`,
  amarillo `#FBBF24`, crema `#FFF8EC`, lila `#A78BFA`); tipografía Baloo 2 (`design/generadores/arte/fuente.py`
  la deriva a TTF); logo real en `design/logo/logo-transparent.png` (nunca uno inventado).
- **Voz de la marca:** "la tía entusiasta": habla de recuerdos y de la fiesta, no de tecnología;
  español de Chile; cercana, sin exagerar. Textos modelo (los tres reels de Luciano, con versión
  corta y primer comentario) en `C:\Users\luis_\Videos\cumpleclick-reels\material\luciano-spidey\textos-instagram.md`.
- **Reglas de publicación fijas** (`docs/POSFIESTA-LINEA-DE-TRABAJO.md` §5 y la guía completa
  `C:\Users\luis_\Videos\cumpleclick-reels\GUIA-POSFIESTA.md`): estructura del texto = gancho con
  emoji de la temática · qué pasó en 2–3 líneas · gracias a los papás · CTA "Agenda por WhatsApp
  (link en la bio)" + cumpleclick.com · **tres hashtags fijos y nada más**:
  `#CumpleClick #CumpleañosInfantiles #PhotoBoothChile`; el hashtag de la temática va en el
  **primer comentario** (`#AventuraAracnida`, `#ReinoDeHielo`); papás como colaboradores; portada
  con el nombre del cumpleañero; marca de agua `marca-agua.png` arriba a la izquierda en los videos.
- **Reglas duras de contenido:** caras de niños **solo con autorización de sus papás** (hoy:
  Samantha, Luciano y Luisana; cualquier otro niño lo decide Luis antes de publicar); **sin nombres
  de franquicias** en textos, prompts ni voces ("Aventura Arácnida", "Reino de Hielo", "tus amigos
  arácnidos", "cartoon toy-style figures"); sin texto ni logos dentro de las generaciones de IA (se
  agregan en post); la música de los reels es decisión de Luis (Sunflower en Luciano; si Instagram
  avisa por derechos, se sube muda y se pone desde la biblioteca de la app).

**Material listo para usar (cero costo)**
- Videos finales (1080×1920, ya con marca de agua y sonoridad −14 LUFS) en
  `C:\Users\luis_\Videos\cumpleclick-reels\finales\`: `reel-luciano-v4-instagram.mp4` (fiesta, 89 s),
  `reel-cabina-luciano-v10-instagram.mp4` (producto, 91,5 s), `reel-album-luciano-v2-instagram.mp4`
  (álbum, 71,7 s), `reel-samantha-v6b-instagram.mp4` (fiesta) y `reel-album-samantha-v2-instagram.mp4`.
  Con sus textos ya escritos. Son las piezas de "prueba social" del mes; el plan las programa, no
  las rehace. Cortes de 15–30 s de esos mismos videos (ffmpeg, sin recodificar más de lo necesario)
  sirven para historias y reels cortos.
- Piezas de Instagram ya generadas (logo real + Baloo 2): `public/brand/carteles/instagram-*.png`
  (avatar, "marca", "cómo funciona", "planes", carrusel de 7 láminas) hechas por
  `design/generadores/arte/redes.py` y `piezas.py`; los carteles A4; y las de esta semana:
  `cartel-feria.py`, `whatsapp-logo.py`, `whatsapp-sigueme.py`. Los generadores son la forma
  **preferida** de hacer piezas con texto: gratis, en la marca y con la tipografía real.
- Fotos reales de las fiestas (kiosco y Álbum) en PROD; se bajan por script con `prod_ssh.py`
  (modelo: `C:\Users\luis_\Videos\cumpleclick-reels\herramientas\prod\album-916\bajar-cabina-luciano.py`).
  Solo Claude las baja (credenciales); Codex pide la lista que necesita.
- Fotos de Higgsfield ya pagadas y sus request_id en
  `C:\Users\luis_\Videos\cumpleclick-reels\herramientas\prod\luciano-spidey-hf\reporte.md`
  (ciudad, telarañas, personajes de la temática): reutilizables como fondos.
- Feria: sábado 26-sep-2026, Mini Feria de Emprendedores del Paseo Dieciochero (Royal Art Academy),
  10:00–14:30. Es contenido del mes (previa, en vivo, después).

**Estudio de marketing de la casa**
- `Docs/MARKETING/2026-07-22-AT-ESTUDIO-NICHOS-Y-ESTRATEGIA-REELS-INSTAGRAM.md` es de AutomatizaTech,
  no de CumpleClick, pero sus secciones 8 (siete pilares), 9 (fórmulas de reel), 10 (atención y
  compartibilidad), 11 (banco de hooks), 17 (métricas) y 20 (reglas de copy) valen tal cual.
  Adaptarlas a mamás y papás de niños de 2 a 10 años en la RM, no a empresas.

### 1.3 Entregable 1 — la estrategia (documento)

`CumpleBooth/docs/MARKETING-CUMPLECLICK-30-DIAS.md`, en español, con:

1. Audiencia (mamás y papás de 28 a 42, RM; quién decide, quién paga, qué mira en Instagram),
   competencia local (cabinas de fotos, animadores, "photobooth" en Santiago) y posicionamiento:
   "no una cabina, un recuerdo: invitación + cabina con personajes + álbum".
2. Pilares y proporción sugerida (a ajustar con datos): **informativo/educativo** (qué incluye,
   cómo funciona, cuánto cuesta, cómo se agenda), **prueba social** (fiestas reales), **detrás de
   cámaras** (montaje, la tablet, la impresora, la feria), **temáticas** (una por semana), **oferta**
   (lanzamiento 50 %), **comunidad** (preguntas, encuestas en historias, respuestas).
3. Formatos y cadencia: reels (3/semana), posts y carruseles (2/semana), historias (diarias, con
   encuestas y "¿cuál temática?"), destacados (Cómo funciona, Temáticas, Planes, Fiestas, Álbum).
   Horarios de publicación para Chile y por qué.
4. Banco de ganchos y de CTA, adaptados a la voz de la marca, y la plantilla de texto (§1.2).
5. **Calendario día a día de 30 días** (el día 1 lo fija Luis), con por pieza: fecha, hora,
   formato, pilar, gancho/título, **copy completo**, hashtags, primer comentario, asset (ruta o
   prompt), estado. Mínimo 30 piezas de feed + el guion de historias por día.
6. KPI y cómo se miden **a mano** (Instagram Insights): seguidores, alcance, guardados,
   compartidos, clics al enlace, mensajes por WhatsApp; meta del mes y revisión semanal.
7. Experimentos (2 o 3): hora de publicación, gancho de precio vs gancho emocional, reel largo vs
   corte de 20 s.
8. Costos: qué es gratis (generadores, videos ya hechos, fotos reales autorizadas) y qué pediría a
   Higgsfield, con estimación (`estimar`) **antes** de generar nada.

### 1.4 Entregable 2 — los assets

- Regla de oro: **primero lo gratis**. Generadores en `design/generadores/arte/` (copiar la
  estructura de `redes.py`: `base_lamina`, tokens, `guardar`), cortes de los videos existentes,
  fotos reales autorizadas. Cada pieza nueva con texto se hace con un generador, no con IA.
- IA solo para imágenes de concepto sin texto (fondos de temática, escenas "cartoon toy-style",
  mockups del kiosco): **Higgsfield por API REST con las reglas del Apéndice A**, Soul 2 por
  defecto ($0,0032/imagen; 9:16 o 4:5 recortado después). Presentar el plan completo con su costo
  y esperar el "ok" de Luis; un ok cubre ese plan, no lo que se descubra después. `bajar` de
  inmediato (7 días de CDN) y anotar request_id y costo en `docs/MARKETING-CUMPLECLICK-30-DIAS.md`.
  Si Codex tiene su propio generador de imágenes, puede usarlo para conceptos, con las mismas reglas
  de marca (sin texto, sin logos, sin franquicias, sin caras reales de niños).
- Salida: `design/generadores/arte/salida/marketing/` (gitignored) y, para lo que Luis va a subir
  desde el celular, la carpeta del módulo (1.5). Formatos: feed 1080×1350, cuadrado 1080×1080,
  historia y reel 1080×1920; portadas de reel con el nombre.

### 1.5 Entregable 3 — módulo "Contenido" del admin (superusuario)

- **Acceso:** página nueva `public/admin/contenido.php`, con la cabecera estándar (ver
  `admin/finanzas.php` líneas 1–30: sesión `cc_admin`, cabeceras, `require _acceso.php`) y
  `admin_exigir_super()` como `admin/usuarios.php`. Clave de módulo `marketing` en
  `CB_ADMIN_MODULOS` (para que un día un operador pueda tenerlo) y pestaña **Contenido** en
  `admin_nav()` después de Finanzas (`_acceso.php` línea ~244).
- **Datos** (migración `025_marketing_contenido.php`, idempotente, con `.down.php`, MySQL y
  SQLite como la 023): tabla `cc_marketing_piezas` (id, fecha_programada DATE, hora TIME NULL,
  formato ENUM-texto: reel/post/carrusel/historia, pilar, titulo, gancho, copy TEXT, hashtags,
  primer_comentario, asset_key (ruta relativa dentro del almacén), asset_url_externa NULL, estado:
  idea/lista/aprobada/programada/publicada/descartada, publicado_url NULL, publicado_at NULL,
  metricas_json NULL, notas, created_at, updated_at) y `cc_marketing_semanas` (semana, seguidores,
  alcance, guardados, mensajes_whatsapp, notas) para la revisión semanal a mano. 🔴 Nunca columnas
  nuevas en `cc_parties`: las fiestas viven con lista fija de columnas (trampa 1).
- **Pantallas:** calendario mensual (grilla con una tarjeta chica por pieza, color por formato),
  lista de la semana, ficha de pieza (editar todo, botón **Copiar texto** que arma copy + hashtags
  listos para pegar en Instagram, vista previa del asset, **Marcar publicada** con la URL del post,
  registrar métricas), y una pestaña "Semana" para las métricas manuales con un gráfico simple
  (como Finanzas). Filtros por estado y pilar. Todo server-rendered en PHP + CSS del admin; JS
  mínimo y sin `<?` dentro de `.js` incluidos (trampa: PHP lo interpreta).
- **Assets:** se guardan **fuera del webroot** en el almacén, bajo `almacen/marketing/<año>/<mes>/`, con
  la misma raíz que usa el Álbum (ver cómo la resuelve `cb_album_media_path()` en `lib.album.php`
  línea ~129 y cómo guarda `cb_album_store_file()`; no hay una función `cb_storage_root`: escribir
  una `cb_marketing_media_path()` equivalente en `lib.marketing.php`) y se sirven por
  `admin/contenido-media.php` con sesión de admin (modelo:
  `ver-media.php`, Content-Type por extensión canónica, `Content-Disposition: inline`). Subida desde
  la ficha (imagen o mp4 ≤ 60 MB) y también por script (`scripts/seed-marketing-30-dias.php`, que
  carga las 30 piezas desde un JSON exportado del documento del entregable 1, idempotente por
  `fecha+titulo`).
- **Pruebas:** `tests/backend/marketing.php` (funciones de `lib.marketing.php`: alta, estados,
  transiciones válidas, copia de texto, límites del asset) y `tests/backend/marketing-http.php`
  (entra con la clave maestra por HTTP como `album-moderar-varios-http.php`, crea, edita, marca
  publicada, baja el asset). `php -l` en todo lo nuevo.
- **Fase 2, fuera de este ticket:** publicar solo desde el admin vía Instagram Graph API o n8n
  (AutomatizaTech ya tiene un Plan B en n8n para su Reel Diario; la app de Instagram de CumpleClick
  no existe todavía). Dejar la tabla preparada (`publicado_url`, `estado programada`) y nada más.

### 1.6 Aceptación del ticket 017

- Documento de estrategia completo con las 30 piezas y su copy; Luis puede publicar la primera
  semana con solo copiar y pegar.
- Assets de la primera semana listos (los demás pueden quedar como prompt/generador aprobado).
- Módulo funcionando en local con la migración aplicada, las 30 piezas cargadas por el seed,
  pruebas en verde, `php -l` limpio, sin secretos, lista FTP y sección en `FTP-MANIFEST.md` con
  "No probado" explícito (nadie lo habrá visto en PROD hasta el deploy).
- Costos: cero sin el ok de Luis; lo gastado, anotado con request_id.

---

## 2. Ticket AT-CUMPLECLICK-018 — Agenda de eventos (módulo del admin)

### 2.1 Objetivo

Un **calendario de agendamientos** en el admin para tener todo bajo control: qué fiesta hay qué
día, a qué hora se monta, dónde, en qué estado está la reserva (consulta, reservada con abono,
confirmada, realizada, cancelada), qué falta por hacer antes (invitación, términos firmados,
manual enviado, álbum entregado) y los compromisos que no son fiestas (visitas, reuniones, ferias,
entregas, días bloqueados). Con la identidad de la marca y el mismo aspecto que el resto del admin.

### 2.2 Lo que ya existe y hay que reutilizar, no duplicar

- Las fiestas viven en `cc_parties` (`public/lib.php` línea ~409: `public_slug`, `admin_label`,
  `birthday_person_name`, `birthday_age`, `event_type`, `theme_slug`, `event_date`, `active`,
  `service_plan`, …). 🔴 **Lista fija de columnas**: no agregar columnas ahí. La hora, la dirección
  y el resto de la logística van en una tabla nueva que referencia `party_id`.
- La dirección y la hora de la invitación están en los valores de la invitación (`lib.php` ~319:
  `event_date`, `address`, …); mostrarlas como referencia, no copiarlas.
- El cobro y el abono ya existen: `cb_party_billing(string $publicSlug)` en `lib.cliente.php`
  línea ~172 (lo usan el comprobante y Finanzas). La agenda **lee** el estado de pago de ahí; no
  inventa un segundo campo de pago.
- Términos firmados: `cc_plan_acceptances` (`lib.acceptance.php`); manual y correos:
  `lib.manual.php`; enlace de aportes y álbum: `lib.album.php`. El checklist de la agenda se calcula
  **leyendo** esas fuentes (firmado sí/no, álbum publicado sí/no) y solo guarda a mano lo que la app
  no puede saber (montaje, contacto, notas).
- Estados de otras pantallas y sus colores: `admin_status_label()`/`admin_status_class()` en
  `admin/invitations.php` y `admin/aceptaciones.php`; formato de fecha `admin_format_datetime()`.

### 2.3 Diseño

- **Migración `024_agenda_eventos.php`** (+ `.down.php`): tabla `cc_agenda_eventos` (id, party_id
  NULL, tipo: fiesta/visita/reunion/feria/entrega/bloqueo, titulo, fecha DATE, hora_inicio TIME NULL,
  hora_fin TIME NULL, hora_montaje TIME NULL, lugar, contacto_nombre, contacto_telefono, estado:
  consulta/reservada/confirmada/realizada/cancelada, notas TEXT, created_at, updated_at). Una fiesta
  de `cc_parties` aparece en el calendario **aunque no tenga fila** en la agenda (se une por
  `event_date`); al editarla se crea su fila con `party_id`. Un `party_id` como máximo una fila
  (índice único).
- **`public/lib.agenda.php`**: listar por mes y por rango, próximos N días, crear/editar/cancelar,
  detección de choques (dos eventos el mismo día con horas que se solapan → aviso, no bloqueo),
  checklist calculado por fiesta, export ICS.
- **`public/admin/agenda.php`**: vista **mes** (grilla de 7 columnas, semana empieza lunes, feriados
  de Chile no hace falta), vista **próximos 30 días** (lista con la ficha resumida: fecha, hora,
  cumpleañero, temática, plan, estado, checklist), ficha para crear/editar (formulario server-side,
  CSRF como las demás páginas), y un botón **Suscribir en el celular** que entrega un enlace `.ics`
  con token HMAC (`admin/agenda-ics.php?f=<hmac>`, firmado con `cb_hmac(string $value, string
  $purpose)` de `lib.php` línea ~137, el mismo patrón del enlace fijo de confirmados que arma
  `lib.rsvp.php` línea ~225) para agregarlo al calendario del teléfono. Acceso: `admin_exigir('agenda')`; el superusuario lo tiene
  todo y la clave `agenda` entra en `CB_ADMIN_MODULOS` después de `invitados`. Pestaña **Agenda**
  después de Fiestas en `admin_nav()`.
- **Identidad:** tokens de `_style.css.php`; tarjetas como las de Fiestas; colores de estado
  coherentes con los que ya usa el admin (violeta = confirmada, amarillo = reservada, gris = consulta,
  verde suave = realizada, fucsia = cancelada); en el celular la grilla pasa a lista (el admin se usa
  desde la tablet y el teléfono).
- **Pruebas:** `tests/backend/agenda.php` (unión con fiestas, choques, checklist, ICS válido) y
  `tests/backend/agenda-http.php` (clave maestra por HTTP: crear, editar, cancelar, bajar el ICS,
  operador sin módulo → 403).

### 2.4 Aceptación del ticket 018

- Las dos fiestas reales (`samantha-hielo`, `luciano-spidey`) aparecen en el mes de septiembre sin
  cargar nada a mano; se puede crear la feria del 26-sep como evento tipo `feria`.
- Pruebas en verde, `php -l` limpio, migración idempotente en MySQL y SQLite, sin secretos, lista
  FTP con el orden (migración → lib → admin) y sección en `FTP-MANIFEST.md` con "No probado".

---

## Apéndice A — Reglas de Higgsfield (texto de Luis, 2026-09-24, se aplican tal cual)

> Necesito generar imágenes/videos con Higgsfield. Reglas para esta tarea:
>
> 1. NO uses el MCP ni el conector de Higgsfield: el plan no tiene créditos. Usa la API REST
>    (api.higgsfield.ai) con el cliente del repo automatiza-tech:
>    `python scratchpad/higgsfield-api/hf_api.py <comando>`
>    Lee primero `scratchpad/higgsfield-api/README.md` y, si tienes la skill higgsfield-studio,
>    `references/api-rest.md` (mapa de modelos MCP -> API, precios, límites).
> 2. Cuenta: la del rótulo "API KEY Higgsfield + N8N" del archivo de claves (`--cuenta n8n`, es
>    la opción por defecto). No uses el par HF_API_KEY_ID del bloque "API N8N": es otra cuenta
>    y está en $0. Nunca copies, imprimas ni pegues las claves en ningún archivo, log o chat.
> 3. Antes de gastar: elige el modelo con `models --filtro <texto>`, calcula el costo con
>    `estimar <slug> --segundos N` o `--imagenes N` (precio de lista, la cuenta tiene 15 % de
>    descuento) y muéstrame el total del plan completo. Corre `generar <slug> --prompt "..."
>    --param k=v` SIN `--si` para que la API valide el cuerpo gratis. Solo cuando yo diga "ok"
>    corres el mismo comando con `--si`. Un "ok" cubre ese plan, no lo que descubras después.
> 4. Modelos por defecto: imagen -> `higgsfield-ai/soul/v2/standard` ($0,0032/img, 16:9 o 9:16,
>    720p/1080p); video -> `kling-video/v3.0/std/image-to-video` ($0,063/s) partiendo de una
>    imagen aprobada, nunca iterando en video. Sin texto ni logos dentro de la generación:
>    se agregan en post (ffmpeg). Todo texto visible en español de Chile y el logo AT como
>    marca de agua pequeña arriba a la izquierda, según las reglas de video de AutomatizaTech.
> 5. Después de generar: `bajar <request_id> --a <archivo>` de inmediato, porque los archivos
>    duran 7 días en el CDN. Guarda el request_id y el costo estimado en el reporte final.
> 6. Límites que no debes intentar rodear: la API no tiene saldo consultable ni get_cost, y no
>    tiene Nano Banana, Veo, 3D, voces, lipsync, upscale ni quitar fondo. Si la tarea necesita
>    algo de eso, dímelo y proponme budgetpixel o ElevenLabs en vez de improvisar. Un 403
>    "not_enough_credits" es saldo cero: detente y avísame. Un 403 "error code: 1010" es
>    Cloudflare por el User-Agent, no un problema de claves.

Aclaración de Claude para CumpleClick: la marca de agua de estos contenidos es la de **CumpleClick**
(`marca-agua.png` en `Videos\cumpleclick-reels\material\luciano-spidey\recursos\`), arriba a la
izquierda, no el logo AT; el resto de las reglas del punto 4 se mantiene.

## Apéndice B — Lo que ya nos mordió (consejos de Claude, medidos)

- La validación de la API de Higgsfield **encola trabajos reales** si el cuerpo va completo, aunque
  los valores sean basura; el cliente ya quita el prompt y las claves con URL sin `--si`. Kling no
  se cancela. Una imagen con los personajes de la temática puede caer por `nsfw` a los 11 s en
  Kling; Hailuo 2.3 la aceptó. Kling O3 video-edit cartooniza todo (sirve como "entra al cómic");
  Seedance 2.5 video-edit cambia fondos conservando a la persona y la tablet. Detalle en
  `scratchpad/higgsfield-api/README.md` ("Trampas medidas el 2026-09-21").
- El CDN de Hostinger **reencoda los PNG** y a Android se los entrega achicados en WebP: las
  carpetas públicas de imágenes llevan `.htaccess` con `no-transform` (`brand/carteles/.htaccess`).
  Para saber si una imagen llegó, comparar píxeles, no md5.
- El repositorio va en **CRLF**; un patrón de búsqueda escrito en LF no calza. Los heredocs de bash
  se comen los backslashes: escribir los scripts a archivo y correrlos.
- `_acceso.php` es el portero único: toda página nueva del admin lo carga después de
  `session_start()` y declara su exigencia (`admin_exigir('modulo')` o `admin_exigir_super()`).
- PHP interpreta `<?` dentro de un `.js` incluido desde PHP: nada de eso en scripts inline.
- Antes de decir que algo "no existe", buscar en todas las ramas (`git log --all`, `git branch
  --contains`): ya pasó que una pantalla existía sin fusionar.
- Un test que pasa contra el archivo equivocado no prueba nada: cotejar PROD (o `main`) antes de
  editar, y verificar el mecanismo, no solo el resultado final.
- En el admin, las cosas que el usuario espera ver **al abrir** deben verse sin depender de un
  observer o de rAF (una página que arranca en `opacity: 0` queda en blanco si el evento no llega).
- Las fotos de la cabina llevan texto horneado (título, agradecimiento): no ponerles texto encima.

## Apéndice C — Identidad, en corto

Tokens: `--primary #8B5CF6`, `--cta #D6307F`, `--accent #FBBF24`, `--bg #FFF8EC`, `--text #4C2882`,
`--border #E9D8FD`, sombra suave; radios de 28–44 px; Baloo 2 en piezas gráficas; el admin usa la
tipografía que ya declara `_style.css.php`. Confeti disperso y suave como único adorno. Logo real,
siempre el mismo archivo. Nada de rosa chicle ni de degradés violeta→azul genéricos.
