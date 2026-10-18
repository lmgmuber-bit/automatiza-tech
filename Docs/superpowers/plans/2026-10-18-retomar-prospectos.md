# Retomar prospectos — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Módulo de WordPress que, una vez por semana y como máximo 4 veces, le recuerda a un prospecto estancado su propuesta y su demo por correo (y WhatsApp con plantilla de Meta), con agenda para reunirse con Luis y baja con un botón.

**Architecture:** Módulo nuevo `inc/retomar/` del tema (funciones puras + datos + render + página pública + envío + panel), una página pública `retomar.php` en la raíz, un `document_type` nuevo `retomar` en el propuesta-renderer y tres flujos n8n (render, reloj+WhatsApp) entregados como JSON con su script, sin activar. WordPress decide todo; n8n solo da el reloj, llama al renderer y envía el WhatsApp.

**Tech Stack:** PHP 8.4 / WordPress 7.1 (tema `automatiza-tech`), Node + Playwright (renderer), n8n (JSON por script Python), MySQL/MariaDB.

**Spec:** `Docs/superpowers/specs/2026-10-18-retomar-prospectos-design.md` (aprobado por Luis el 18-oct). Léelo entero antes de empezar.

## Global Constraints

- Repo PÚBLICO: ningún nombre, correo, teléfono ni dato de prospectos o clientes en código, pruebas, commits o docs. En pruebas: `[PRUEBA]`, `@example.com`, `+569 0000 0000`.
- No tocar `wp-content/themes/automatiza-tech/functions.php`. El módulo se carga desde `inc/admin-proposals.php` (junto a `plan-trabajo/cargar.php`, línea ~92).
- Prefijo de funciones `at_rt_`; opciones `at_rt_*`; tablas `{$wpdb->prefix}at_retomar` y `{$wpdb->prefix}at_retomar_bajas`; esquema en la opción `at_rt_schema` = `'1'`.
- Envío: máximo **4** recordatorios por defecto (parámetro `max`, rango 1–4); intervalo por defecto **7** días, **mínimo 7** (el formulario y el guardado suben a 7 cualquier valor menor); solo **lunes a viernes**, ventana **10:00–13:00 hora de Chile** (`America/Santiago`).
- WhatsApp por defecto en el envío **1 y el último** (`n === 1 || n === max`), solo si la casilla **«WhatsApp autorizado»** está marcada y el canal WhatsApp está activo en el seguimiento.
- Estados del seguimiento, exactos: `borrador`, `activo`, `pausado`, `agendo`, `respondio`, `cliente`, `baja`, `agotado`, `rechazado`.
- Se detiene solo con: baja, reunión agendada (fila en `automatiza_followup_meetings` con el correo del prospecto creada después de `activado_at`), ficha CRM `tipo='cliente'`, propuesta vinculada `aceptada` o `rechazada`, tope alcanzado, pausa manual, «Respondió».
- No se activa (y se rechaza al activar) si: propuesta vinculada `rechazada`, contacto en la lista de bajas, o sin correo.
- Regla D8: ningún enlace ni texto con `easypanel` en correos ni WhatsApp (reusar `at_av_urls_internas()` de `inc/plan-trabajo/cierre-puras.php`).
- WhatsApp: estados `pendiente` → `enviado` | `error`; **ningún reintento automático**. Plantilla `retomar_propuesta`, categoría Marketing, idioma `es`.
- Baja: GET muestra confirmación con un solo botón; la baja ocurre por POST. `List-Unsubscribe` solo con URL https (sin `mailto:`) + `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, que da de baja directo. Token idempotente.
- Textos visibles en español de Chile, voz de equipo («te saluda el equipo de AutomatizaTech»).
- No producción: nada de SSH, PROD, n8n en vivo ni Meta. Los flujos se entregan como JSON y script; los crea y activa Eje con OK de Luis.
- Pruebas WordPress como en `tests/plan/`: `AT_WP_LOAD=<sitio>/wp-load.php php tests/retomar/<archivo>.php`, con `HTTP_HOST=localhost:8093` (copiar `tests/plan/wp-bootstrap.php`). Las 3 fallas previas de `main` (`datos-contexto`, `panel-render`, `firma-contrato`) no cuentan.

## Review Focus

1. Un antivirus de correo abre el enlace «No me interesa» (GET) → el prospecto **no** queda de baja; solo el POST lo da de baja. Prueba en Task 5.
2. El mismo correo aparece en dos seguimientos (o en dos fichas) y uno se da de baja → el otro tampoco recibe nada. Prueba en Task 2 y Task 6.
3. n8n llama dos veces al reloj el mismo día (reintento) → un solo correo por seguimiento y día. Prueba en Task 6.
4. Prospecto sin teléfono, con teléfono inválido o sin casilla de WhatsApp → sale solo el correo, sin error ni reintento. Prueba en Task 6.
5. El día del envío la presentación no está «listo» → ese envío se corre al día hábil siguiente y queda anotado; nunca sale un enlace roto. Prueba en Task 6.

---

### Task 0: Carga del módulo y esquema

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/retomar/cargar.php`, `inc/retomar/esquema.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php` (una línea `require_once __DIR__ . '/retomar/cargar.php';` después de la de `entregables/cargar.php`)
- Create: `tests/retomar/wp-bootstrap.php`, `tests/retomar/esquema-wp-test.php`

**Interfaces:**
- Produces: `at_rt_migrar_esquema(): void` (crea las dos tablas con `dbDelta` y deja `at_rt_schema`='1'; idempotente) · `at_rt_esquema_listo(): bool`.
- Tabla `at_retomar`: `id`, `crm_cliente_id` INT NULL, `propuesta_id` INT NULL, `nombre` VARCHAR(190), `negocio` VARCHAR(190), `email` VARCHAR(190), `telefono` VARCHAR(40), `wa_autorizado` TINYINT, `propuesta_url` VARCHAR(500), `demo_urls` TEXT (JSON), `imagen_url` VARCHAR(500), `textos` LONGTEXT (JSON con `quedamos`, `proponemos`, `partir`), `max_envios` TINYINT default 4, `intervalo_dias` TINYINT default 7, `canales` VARCHAR(40) default `correo,whatsapp`, `estado` VARCHAR(20) default `borrador`, `token` VARCHAR(64), `render` LONGTEXT (JSON `{estado, at, view_url, pdf_url, nota}`), `envios` LONGTEXT (JSON lista), `activado_at` DATETIME NULL, `proximo_at` DATE NULL, `enviando` DATETIME NULL, `creado_at`, `actualizado_at`.
- Tabla `at_retomar_bajas`: `id`, `email` VARCHAR(190), `telefono` VARCHAR(40), `origen` VARCHAR(30), `motivo` VARCHAR(255), `creado_at`, `revertido_at` DATETIME NULL; índices por `email` y `telefono`.

- [ ] **Step 1:** Test `esquema-wp-test.php`: tras `at_rt_migrar_esquema()` dos veces, `SHOW COLUMNS` de ambas tablas trae las columnas listadas y `get_option('at_rt_schema') === '1'`.
- [ ] **Step 2:** Correrlo: FAIL (función no existe).
- [ ] **Step 3:** Implementar `esquema.php` y `cargar.php` (este último requiere los archivos del módulo en orden: `esquema`, `puras`, `datos`, `render`, `publica`, `envio`, `panel`; los que aún no existan se agregan en su task).
- [ ] **Step 4:** Correrlo: PASS.
- [ ] **Step 5:** Commit `feat(retomar): esquema y carga del módulo`.

### Task 1: Funciones puras

**Files:** Create `inc/retomar/puras.php` · Test `tests/retomar/puras-test.php` (sin WordPress, como `tests/plan/cierre-puras-test.php`).

**Interfaces (Produces):**
- `at_rt_intervalo(int $dias): int` → `max(7, $dias)`.
- `at_rt_max(int $max): int` → limitado a 1..4.
- `at_rt_es_habil(string $ymd): bool` → lunes a viernes (feriados fuera de alcance).
- `at_rt_en_ventana(string $hm): bool` → `'10:00' <= $hm < '13:00'`.
- `at_rt_proximo(string $ymd_envio, int $intervalo): string` → fecha + intervalo días; si cae sábado o domingo, el lunes siguiente.
- `at_rt_canales(int $n, int $max, string $canales, bool $wa_autorizado, string $telefono_wa): array` → `['correo']` siempre; agrega `'whatsapp'` solo si `$n===1 || $n===$max`, `$canales` contiene `whatsapp`, `$wa_autorizado` y `$telefono_wa !== ''`.
- `at_rt_motivo_parada(array $c): string` → `''` o uno de `baja`, `agendo`, `cliente`, `aceptada`→estado `cliente`, `rechazada`→estado `rechazado`, `agotado`; claves de `$c`: `baja` bool, `reunion` bool, `crm_tipo` string, `propuesta_estado` string, `enviados` int, `max` int. Orden de prioridad: baja, rechazada, aceptada/cliente, agendo, agotado.
- `at_rt_estado_por_motivo(string $motivo): string` (mapa motivo→estado de la lista global).
- `at_rt_token(int $id, string $salt): string` (HMAC-SHA256 de `"rt|$id"`, primeros 32 hex) y `at_rt_token_valido(string $t, int $id, string $salt): bool` (`hash_equals`).
- `at_rt_asunto(int $n, string $nombre): string` — copia exacta: 1 «¿Retomamos tu proyecto, {nombre}?», 2 «{nombre}, tu demo sigue lista para ti», 3 «Podemos ajustarlo a tu presupuesto, {nombre}», 4 «Último recordatorio: ¿conversamos 20 minutos?». Sin nombre: quitar «, {nombre}» / «{nombre}, ».
- `at_rt_correo_html(array $d): string` y `at_rt_correo_texto(array $d): string` — `$d`: `nombre`, `negocio`, `n`, `quedamos`, `url_ver`, `url_agendar`, `url_baja`, `logo`. Botones «Ver la presentación», «Agendar una reunión»; pie «No me interesa, no volver a escribirme» → `url_baja`. Escapar todo.
- `at_rt_cabeceras_baja(string $url_baja): array` → `['List-Unsubscribe: <URL>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']`.
- `at_rt_internas(array $d): array` → URLs/textos con `easypanel` (envuelve `at_av_urls_internas`).

- [ ] **Step 1:** Tests con valores exactos: intervalo(3)=7, intervalo(10)=10; max(9)=4, max(0)=1; proximo('2026-10-16',7)='2026-10-23'; proximo('2026-10-17',7) (sábado+7=sábado)='2026-10-26'; en_ventana('09:59')=false, ('10:00')=true, ('12:59')=true, ('13:00')=false; canales(1,4,'correo,whatsapp',true,'56900000000')=['correo','whatsapp'], canales(2,4,…)=['correo'], canales(4,4,…) incluye whatsapp, canales(1,4,…,false,…)=['correo'], canales(1,4,…,true,'')=['correo']; motivo_parada con baja y reunión → 'baja'; con `propuesta_estado='rechazada'` → 'rechazada'; enviados=4,max=4 → 'agotado'; todo vacío → ''; token válido/ inválido/ de otro id; asunto(1,'Ana')='¿Retomamos tu proyecto, Ana?', asunto(1,'')='¿Retomamos tu proyecto?'; correo_html contiene las tres URLs y no contiene `<script`; cabeceras_baja no contiene `mailto:`.
- [ ] **Step 2:** FAIL. **Step 3:** implementar. **Step 4:** PASS. **Step 5:** commit `feat(retomar): funciones puras`.

### Task 2: Datos y lista de bajas

**Files:** Create `inc/retomar/datos.php` · Test `tests/retomar/datos-wp-test.php`.

**Interfaces:**
- Consumes: Task 0 tablas; Task 1 `at_rt_token`, `at_rt_intervalo`, `at_rt_max`.
- Produces: `at_rt_crear(array $campos): int` (genera token con `wp_salt('auth')`) · `at_rt_obtener(int $id): ?array` (JSON decodificados) · `at_rt_por_token(string $t): ?array` · `at_rt_guardar(int $id, array $campos): bool` (aplica `at_rt_intervalo`/`at_rt_max`) · `at_rt_lista(): array` · `at_rt_agregar_envio(int $id, array $evento): bool` · `at_rt_en_bajas(string $email, string $telefono_wa): bool` (ignora revertidas; compara correo en minúsculas y teléfono normalizado con `at_pt_telefono_wa`) · `at_rt_dar_baja(int $id, string $origen): bool` (inserta en bajas y pasa a `baja` **todos** los seguimientos con ese correo o teléfono) · `at_rt_revertir_baja(int $baja_id, string $motivo): bool` · `at_rt_contexto_parada(array $seg): array` (claves de `at_rt_motivo_parada`: lee `crm_clientes.tipo`, `automatiza_propuestas.status`, reuniones de `automatiza_followup_meetings` con `client_email` = correo y `created_at > activado_at`).

- [ ] **Step 1:** Tests: crear+obtener redondea intervalo 3→7; dos seguimientos con el mismo correo, `at_rt_dar_baja` en uno → ambos en `baja` y `at_rt_en_bajas` true; revertir → false; contexto con propuesta `rechazada` da `propuesta_estado='rechazada'`; reunión creada antes de `activado_at` no cuenta, después sí.
- [ ] **Step 2–5:** FAIL, implementar, PASS, commit `feat(retomar): datos y lista de bajas`.

### Task 3: Renderer `document_type: 'retomar'`

**Files:** Create `renderer/src/template-retomar.js` · Modify `renderer/src/server.js` (despacho por `document_type`, igual que `'estado'`), `renderer/src/schema.js` (`validateRetomarPayload`) · Test `renderer/test/template-retomar.test.js`, `renderer/test/template-retomar-layout.test.js`, fixture `renderer/test/fixtures/retomar-ejemplo.js`.

**Interfaces:**
- Payload (lo arma WordPress en Task 4): `{document_type:'retomar', unique_id:'<token>-retomar', company_name, client_name, proyecto, quedamos, proponemos, partir, demo_urls:[…], demo_imagen, propuesta_url, agenda:{web_url}, fotos:{portada, cierre}}`.
- Produces: `renderRetomarHtml(datos, opciones): string` y `validateRetomarPayload(body): {ok, errors}`.
- Láminas, en orden: portada (negocio) · «En qué quedamos» · «Tu demo» (imagen + enlaces) · «Lo que proponemos» · «Podemos partir por lo prioritario y ajustarnos a tu presupuesto» (texto `partir`) · «Agendemos 20 minutos» (botón a `agenda.web_url`). Estilo de `template-estado.js`; textos escapados; URLs solo https.

- [ ] **Step 1:** Tests: con el fixture salen 6 láminas en ese orden; un `demo_urls` con `http://` o `javascript:` no aparece; sin `partir` la lámina 5 usa la frase por defecto «Si algo no calza con lo que necesitas o con tu presupuesto, lo ajustamos y partimos por lo más importante.»; layout en Chromium 1920×1080 y modo impresión sin elementos fuera de las láminas (copiar `medir()` de `template-estado-layout.test.js`).
- [ ] **Step 2–5:** FAIL, implementar, PASS (`node --test test/template-retomar*.test.js`), commit `feat(renderer): document_type retomar`.

### Task 4: Render desde WordPress y su vuelta

**Files:** Create `inc/retomar/render.php`, `N8N/retomar/build_retomar_render.py` (genera `N8N/retomar/retomar-render.json`) · Test `tests/retomar/render-wp-test.php`.

**Interfaces:**
- Consumes: Task 2 datos; `at_pt_llamar_n8n(string $url, array $cuerpo): string` (devuelve motivo de error o '').
- Produces: `at_rt_armar_render(array $seg): array` (payload de Task 3; `agenda.web_url` = `home_url('/retomar.php?t=<token>&agendar=1')`) · `at_rt_pedir_render(int $id): string` (marca `render.estado='pendiente'`; usa la constante `AT_N8N_RETOMAR_RENDER`; sin constante devuelve `'sin_flujo'` y no cambia nada) · ruta REST `POST automatiza-tech/v1/retomar/(?P<id>\d+)/vista` con cabecera `x-at-secret` = `AT_REST_SECRET` y cuerpo `{ok, view_url, pdf_url, nota}` → `render.estado` `listo` o `error`.
- Flujo n8n (JSON, sin activar): Webhook POST con Header Auth → HTTP POST al renderer `/render` con la credencial existente `X-AT-Render-Key` → HTTP POST al callback de arriba con `X-AT-Secret`. Copiar el patrón de `N8N/plan-trabajo/build_plan_3_render.py` (sin fotos nuevas).

- [ ] **Step 1:** Tests: `at_rt_armar_render` no incluye `easypanel` ni datos de otro seguimiento; callback sin secreto → 401; con secreto y `ok:true` → `listo` con `view_url`; `ok:false` → `error`; sin `AT_N8N_RETOMAR_RENDER` → `'sin_flujo'`.
- [ ] **Step 2–5:** FAIL, implementar, PASS (más `python N8N/retomar/build_retomar_render.py` produce JSON válido), commit `feat(retomar): render de la presentación`.

### Task 5: Página pública (ver, agendar, baja)

**Files:** Create `retomar.php` (raíz del repo, carga WordPress como `ver-plan.php`), `inc/retomar/publica.php` · Test `tests/retomar/publica-wp-test.php`.

**Interfaces:**
- Consumes: Task 2 `at_rt_por_token`, `at_rt_dar_baja`; Task 4 `render.view_url`; agenda del plan como modelo (`inc/plan-trabajo/agenda.php`: token del día, campo trampa, límite por IP, horarios hasta las 21:00).
- Produces: `at_rt_pagina(array $get, string $metodo, array $post): array` → `['http'=>int,'html'=>string,'cabeceras'=>array]` (testeable sin servidor) con modos: ver (iframe de `view_url` + botones), `agendar=1` (formulario de agenda), `baja=1` GET (confirmación con un botón, **sin dar de baja**), `baja=1` POST con nonce de la página o con cuerpo `List-Unsubscribe=One-Click` (da de baja, `origen` `pagina` o `one_click`). Token inválido → 404 con página propia. Siempre `noindex`.
- Produces: `at_rt_agendar(array $entrada, string $ip): array` (clon de `at_pt_agendar_seguimiento` sin plan: crea la reunión en `automatiza_followup_meetings` con los datos del seguimiento y pasa el seguimiento a `agendo`) y ruta REST `POST automatiza-tech/v1/retomar-agenda`.
- Modify: `inc/admin-followup-meetings.php` (~línea 2058): una ficha con `tipo='prospecto'` se avisa como `seguimiento_prospecto`, no como cliente convertido.

- [ ] **Step 1:** Tests: GET `baja=1` → 200, contiene el botón y el seguimiento **sigue `activo`** (Review Focus 1); POST con nonce → `baja`; POST `List-Unsubscribe=One-Click` → `baja`; segundo POST → 200 «ya no te escribiremos» (idempotente); token ajeno → 404; HTML con `noindex`; agendar con token del día crea fila y estado `agendo`; campo trampa lleno → no guarda.
- [ ] **Step 2–5:** FAIL, implementar, PASS, commit `feat(retomar): página pública, agenda y baja`.

### Task 6: Envíos y reloj

**Files:** Create `inc/retomar/envio.php` · Modify `wp-content/mu-plugins/at-timeline-origen.php` (etiquetas `retomar_correo` «🔁 Recordatorio enviado», `retomar_whatsapp` «🔁 Recordatorio por WhatsApp», `retomar_baja` «🔁 No le interesa», `retomar_agendo` «🔁 Agendó reunión») · Test `tests/retomar/envio-wp-test.php`.

**Interfaces:**
- Consumes: Tasks 1, 2, 4, 5; `at_cc_historial_crm(int,string,string,string)`, `at_cc_correo_avisos()`.
- Produces: `at_rt_enviar(int $id, bool $prueba): array` → `['ok'=>bool,'motivo'=>string,'whatsapp'=>?array]` (prueba: va a `at_cc_correo_avisos()` con «[Prueba] » en el asunto y no cuenta como envío) · `at_rt_tick(string $ahora): array` → `['enviados'=>int,'diferidos'=>int,'detenidos'=>int,'whatsapp'=>[{id,n,telefono,nombre,negocio,token}]]` · ruta REST `POST automatiza-tech/v1/retomar/tick` (`x-at-secret`) · ruta REST `POST automatiza-tech/v1/retomar/whatsapp-resultado` (`x-at-secret`; cuerpo `{id,n,wamid?,codigo?,titulo?}` → evento `enviado` o `error`, sin reintento).
- Reglas del tick, en orden: fuera de ventana o no hábil → nada; por cada `activo` con `proximo_at <= hoy`: calcular parada (`at_rt_contexto_parada` + bajas) → si hay motivo, cambiar estado y anotar; si `render.estado !== 'listo'` → `proximo_at` = siguiente hábil, `diferidos++`; reclamar con `enviando` (UPDATE condicional, 10 minutos) para no duplicar; si ya hay un envío con fecha de hoy → saltar; enviar correo (cabeceras `at_rt_cabeceras_baja`, D8 bloquea); registrar evento; historial CRM; `proximo_at = at_rt_proximo(hoy, intervalo)`; si `n === max` → `agotado`; agregar WhatsApp a la respuesta si `at_rt_canales` lo incluye.

- [ ] **Step 1:** Tests (capturar correo con `pre_wp_mail`): tick a las 09:30 → 0; sábado → 0; dos ticks seguidos el mismo día → 1 correo (Review Focus 3); seguimiento con correo en bajas → 0 correos y estado `baja` (Review Focus 2); sin teléfono o sin casilla → correo sí, `whatsapp` vacío (Review Focus 4); render `pendiente` → 0 correos, `diferidos=1`, `proximo_at` siguiente hábil (Review Focus 5); 4.º envío → estado `agotado` y WhatsApp incluido; correo trae `List-Unsubscribe` sin `mailto:`; resultado de WhatsApp con `codigo:131049` → evento `error` y ningún nuevo pedido de WhatsApp para ese n; texto con `easypanel` → no envía, motivo `interno`.
- [ ] **Step 2–5:** FAIL, implementar, PASS, commit `feat(retomar): envíos semanales y reloj`.

### Task 7: Flujo n8n del reloj y WhatsApp, y la plantilla

**Files:** Create `N8N/retomar/build_retomar_reloj.py` (genera `N8N/retomar/retomar-reloj.json`), `N8N/retomar/PLANTILLA-retomar_propuesta.md`, `N8N/retomar/README.md`.

**Interfaces:**
- Consumes: rutas `retomar/tick` y `retomar/whatsapp-resultado` (Task 6).
- Flujo: Schedule Trigger lunes a viernes 10:15 `America/Santiago` → HTTP POST `retomar/tick` con `X-AT-Secret` → por cada ítem de `whatsapp`: HTTP POST Graph API `messages` con plantilla `retomar_propuesta` (`es`), parámetros de cuerpo `{{1}}`=nombre, `{{2}}`=negocio, botones URL con sufijo `t=<token>&agendar=1` y `t=<token>&baja=1`, usando la credencial de WhatsApp Cloud API que ya usa el flujo de propuestas (por nombre, sin valores) → HTTP POST `retomar/whatsapp-resultado` con wamid o error (`onError: continueRegularOutput`). Sin reintentos.
- `PLANTILLA-retomar_propuesta.md`: el texto exacto de la plantilla del spec §4.4, los dos botones URL y la categoría, listo para que Eje lo mande a Meta.

- [ ] **Step 1:** Prueba: `python N8N/retomar/build_retomar_reloj.py` genera JSON que carga con `json.load`, con exactamente un Schedule Trigger, ningún nodo con `retryOnFail: true` y ningún valor de secreto (grep de `Bearer `, `EAA`, `sk-` da 0).
- [ ] **Step 2–3:** implementar y correr. **Step 4:** commit `feat(n8n): reloj y WhatsApp de retomar (sin activar)`.

### Task 8: Panel «🔁 Retomar prospectos»

**Files:** Create `inc/retomar/panel.php`, `wp-content/themes/automatiza-tech/assets/css/retomar.css` · Test `tests/retomar/panel-wp-test.php`.

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: submenú `add_submenu_page('automatiza-crm', 'Retomar prospectos', '🔁 Retomar prospectos', 'manage_options', 'at-retomar', …)`; acciones `admin_post_at_rt_guardar`, `at_rt_previa`, `at_rt_prueba`, `at_rt_activar`, `at_rt_pausar`, `at_rt_respondio`, `at_rt_baja`, `at_rt_revertir_baja`, cada una con nonce y `manage_options`.
- Lista: prospecto, estado, envíos `n/max`, próximo envío, último resultado (incluye errores de WhatsApp). Formulario: ficha CRM (buscar o crear prospecto), propuesta vinculada o URL externa, hasta 2 URL de demo, imagen de la demo (subida a la biblioteca de medios), tres textos, `max` (1–4), intervalo (mín. 7), canales, casilla «WhatsApp autorizado» con ayuda «Márcala solo si esta persona te escribió o conversó contigo por WhatsApp».
- `at_rt_activar`: rechaza con aviso claro si propuesta `rechazada`, contacto en bajas, sin correo, o render que no está `listo`; si pasa: `estado=activo`, `activado_at=now`, `proximo_at`=hoy si es hábil y antes de las 13:00, si no el siguiente hábil.

- [ ] **Step 1:** Tests: guardar con intervalo 3 deja 7; activar con propuesta rechazada → sigue `borrador` y aviso; activar sin render listo → aviso; activar válido → `activo` con `proximo_at`; «Respondió» → `respondio`; prueba no suma envío.
- [ ] **Step 2–5:** FAIL, implementar, PASS, commit `feat(retomar): panel del CRM`.

### Task 9: Guía y cierre

**Files:** Create `Docs/METODO_AT/RETOMAR-PROSPECTOS.md` · Modify `Docs/METODO_AT/README.md` (enlace), `CLAUDE.md` y `AGENTS.md` (un puntero en «Punteros de proyecto»: LOCAL, rama, guía).

- La guía dice: para qué sirve, cómo agregar un prospecto, cómo probar, qué detiene los envíos, la lista de archivos para PROD en orden (`inc/retomar/*`, `inc/admin-proposals.php`, `inc/admin-followup-meetings.php`, `mu-plugins/at-timeline-origen.php`, `retomar.php`, `assets/css/retomar.css`; migración con `wp eval 'at_rt_migrar_esquema();'`), el zip del renderer, los dos flujos n8n, las constantes `AT_N8N_RETOMAR_RENDER` (URL, no es secreto) y la plantilla de Meta; rollback; y «no probado».
- [ ] **Step 1:** Correr todas las pruebas de `tests/retomar/`, `tests/plan/` y `tests/entregables/correr.sh` y `node --test renderer/test/` (Playwright desde `C:\wamp64\www\automatiza-tech\.worktrees\estado-e2\renderer\node_modules` vía `NODE_PATH` si falta). Anotar resultados reales en el informe.
- [ ] **Step 2:** `graphify update .` y commit `docs(retomar): guía, punteros y grafo`.
