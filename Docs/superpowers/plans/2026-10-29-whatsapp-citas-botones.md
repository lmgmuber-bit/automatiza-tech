# Tech WhatsApp: citas con botones, enlace al portal y tipo de cambio protegido — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cuando alguien quiere cancelar o reprogramar por WhatsApp, el bot le muestra sus citas con botones y no le pide datos. Si escribe un cliente, el bot le manda el enlace a su portal. Si el tipo de cambio se cae, el bot no se queda mudo.

**Architecture:**
- **WordPress (módulo `inc/wa-contexto/`):** suma dos rutas protegidas con `X-AT-Secret`. Una lista las citas futuras del número y la otra cancela con todos los efectos. Además, `wa-contexto` devuelve los portales del cliente.
- **n8n (WhatsApp `bBcNlFgBzQ0766Mq`):** reemplaza los tres caminos que piden datos escritos por un camino de botones. La lógica de los nodos vive en un bloque JS con pruebas. Al reprogramar, la cita vieja se cancela recién cuando la nueva queda creada.
- **Script de cambios:** como en `wa-reconoce`, es idempotente, con vista previa y `--subir`.

**Tech Stack:** PHP 8.3 (WordPress, pruebas con `php` sobre `wp-load.php` de WAMP), JavaScript (nodos Code de n8n, `node --test`), Python 3.13 (script de n8n y sus pruebas), n8n API REST.

**Spec:** `Docs/superpowers/specs/2026-10-29-whatsapp-citas-botones-design.md`. Base: `Docs/superpowers/specs/2026-10-28-whatsapp-reconoce-cliente-design.md` y el código de la rama `claude/wa-reconoce-cliente`.

## Global Constraints

- **Rama `claude/wa-citas-botones`.** Worktree `C:/wamp64/www/automatiza-tech/.worktrees/wa-reconoce`. `functions.php` no se toca.
- **Nunca `hard_delete`:** cancelar es `status = 'cancelled'`.
  - Diagnóstico: `automatiza_leads.status`, `cancelled_at`.
  - Seguimiento: `automatiza_followup_meetings.status`, `updated_at`, como en `automatiza_tech_cancel_followup_meeting`.
- **Las rutas nuevas** usan `at_cc_rest_auth` como permiso, con la misma envoltura que `rest.php`, y van en `wp-content/themes/automatiza-tech/inc/wa-contexto/`, cargadas desde `cargar.php`.
- **Un error de base** lanza `AtWaConsultaNoDisponible` (con `at_wa_q`/`at_wa_ext`) y la ruta responde 503 con `estado: 'no_disponible'`.
- **Citas:** solo futuras y activas, con un tope de 10, de la más próxima a la más lejana. «Ahora» es `current_time('Y-m-d H:i')`.
- **Topes de WhatsApp:** botón de respuesta, 20 caracteres; título de lista, 24; descripción de lista, 72. Se cuentan con `Array.from`, porque los emojis cuentan como uno. Los títulos nunca se repiten.
- **Ids de botones nuevos:**
  - `cita_<c|r>_<d|s>_<id>`: elegir una cita para cancelar (c) o reprogramar (r), sea diagnóstico (d) o seguimiento (s);
  - `cita_ok_<c|r>_<d|s>_<id>`: confirmar;
  - `cita_tengo`: «Tengo una cita»;
  - «No, mantenerla» reusa `btn_mantener_cita`.
- **Marca de reprogramado en Redis:** clave `reagendar_cita_<teléfono>`, TTL 1800 s. El valor es el JSON de la cita vieja: `{tipo, id, nombre, correo, empresa, contrato_id, propuesta_id}`.
- **Límite de reuniones:** `excluir` solo vale con `X-AT-Secret`. Los parámetros son `excluir_tipo` (`diagnostico`|`seguimiento`) y `excluir_id`.
- **Enlace al portal:** solo cuando `situacion = 'cliente'`, uno por ficha `crm:<id>` cuyo `crm_clientes.telefono` coincide en los últimos 9 dígitos. La URL sale de `at_crm_url_portal($id)`, y el nombre es la empresa de la ficha (o su nombre).
- **Tipo de cambio:** los nodos «Get Exchange Rate» (web) y «Get Exchange Rate», «… Audio» y «… Image» (WhatsApp) van con `onError=continueRegularOutput`, `options.timeout=8000`, `retryOnFail=true`, `maxTries=3` y `waitBetweenTries=1000`.
- **Textos exactos** que fija el diseño:
  - «No encontramos citas agendadas con este número.»
  - «Tienes esta cita: <etiqueta>. ¿La cancelamos?»
  - «¿La cambiamos de día?»
  - «Esa cita ya no está disponible.»
  - «En este momento no pude revisar tus citas. Ya le avisamos a Luis para que te escriba y lo coordinen.»
  - «No pude cancelarla en este momento, ya le avisamos a Luis.»
- **Repositorio público:** las pruebas usan datos sintéticos (`@prueba.cl` y números `+56 9 0000 xxxx`). Nunca el número ni el correo de Luis.
- **PROD solo con el OK de Luis** y con respaldo. Se verifica la `activeVersion`. WordPress va antes que n8n. Antes de tocar `PejBgr2LZQSAbbBy`, se avisa a la sesión de Instagram.
- **Cada `*-wp-test.php`** usa `tests/cierre/wp-bootstrap.php` (`ok()`/`fin()`) y `AT_WP_LOAD`. Las llamadas HTTP salientes se interceptan con el filtro `pre_http_request` y los correos con `pre_wp_mail`: ninguna prueba toca Google Calendar ni manda correos.

## Review Focus

1. **Doble toque en «Sí, cancelar»** (WhatsApp reenvía o la persona toca dos veces). La segunda vez responde «Esa cita ya no está disponible.», sin error ni doble correo. Prueba en la Tarea 3: `doble cancelación → 409 no_vigente y un solo correo`.
2. **Una cita de hoy a una hora que ya pasó** no aparece; una de hoy más tarde, sí. Prueba en la Tarea 1: `hoy temprano fuera, hoy tarde dentro`.
3. **Un diagnóstico agendado por la web sin teléfono,** pero con el correo de la ficha, sí aparece. Prueba en la Tarea 2: `diagnóstico sin teléfono por correo de la ficha`.
4. **Reprogramado abandonado u horario ocupado:** la cita vieja queda activa. Prueba en la Tarea 8 (simulación): `ocupado → no se cancela la vieja`.
5. **Un botón viejo** de una cita cancelada desde el admin vuelve a mostrar las vigentes y no cancela otra. Prueba en la Tarea 6: `botón de cita que ya no está → mensaje de no disponible con las vigentes`.

---

### Task 1: Lógica pura de las citas (PHP)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/wa-contexto/citas-puras.php` (misma guarda que `puras.php`: `AT_WA_PURAS_TEST`)
- Test: `tests/wa-contexto/citas-puras-test.php`

**Interfaces:**
- Produces:
  - `at_wa_citas_armar(array $diagnosticos, array $seguimientos, string $ahora): array`. Recibe filas ya leídas:
    - diagnósticos: `{id, scheduled_date, scheduled_time, status, name, email, empresa}`;
    - seguimientos: `{id, meeting_date, meeting_time, status, client_name, client_email, company, contrato_id, propuesta_id, vinculo}`, donde `vinculo` es el texto de `at_wa_vinculo_texto`.
    - Devuelve hasta 10 citas: `{tipo: 'diagnostico'|'seguimiento', id: int, cuando: 'Y-m-d H:i', etiqueta, nombre, correo, empresa, contrato_id: int, propuesta_id: int}`.
  - `at_wa_cita_etiqueta(string $tipo, string $cuando, string $vinculo): string` → «Diagnóstico gratis · mar 03-nov · 17:00», «Seguimiento: <vínculo> · …» o «Seguimiento · …».
  - Constante `AT_WA_CITA_INACTIVA = ['cancelled', 'completed', 'no_show']`.

- [ ] **Step 1: Write the failing tests** in `citas-puras-test.php`, using `ok()` local like `puras-test.php`, with `AHORA = '2026-11-03 12:00'`:
  - `etiqueta diagnóstico`: `at_wa_cita_etiqueta('diagnostico', '2026-11-03 17:00', '') === 'Diagnóstico gratis · mar 03-nov · 17:00'`
  - `etiqueta seguimiento con vínculo` → `'Seguimiento: Propuesta: Sitio web · mar 03-nov · 17:00'`; without vínculo → `'Seguimiento · mar 03-nov · 17:00'`
  - `hoy temprano fuera, hoy tarde dentro`: diagnóstico 2026-11-03 10:00 not present, 2026-11-03 17:00 present
  - `canceladas, completadas y no_show fuera`
  - `orden de la más próxima` mixing both types: ids in order of `cuando`
  - `tope 10` with 12 future rows → 10
  - `datos de la cita vieja`: seguimiento keeps `contrato_id`/`propuesta_id` as int and `correo` from `client_email`; diagnóstico takes `correo` from `email`
- [ ] **Step 2:** Run `php tests/wa-contexto/citas-puras-test.php`. Expected: fatal «Call to undefined function».
- [ ] **Step 3:** Implement both functions. Day names in Spanish, three letters, lowercase (`lun mar mié jue vie sáb dom`), and `dd-mmm` with `ene feb mar abr may jun jul ago sep oct nov dic`.
- [ ] **Step 4:** Run again. Expected: all `ok`, and the last line says 0 fallas.
- [ ] **Step 5:** Commit: `feat(wa): lógica pura de las citas del número (etiqueta, futuras, orden, tope)`.

### Task 2: Leer las citas del número y la ruta `GET /wa-citas`

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/wa-contexto/citas.php` (data and routes); add `require_once` of `citas-puras.php` and `citas.php` in `cargar.php`
- Test: `tests/wa-contexto/citas-wp-test.php` (fixtures in `tests/wa-contexto/fixtures.php`: reuse `wa_crear`, `wa_correo`, `wa_numero_libre`; add `wa_lead($correo, $tel, $fecha, $hora, $estado = 'active')` and `wa_seguimiento($correo, $tel, $fecha, $hora, $estado = 'scheduled')` that return the id)

**Interfaces:**
- Consumes: `at_wa_fichas_por_telefono`, `at_wa_sql_ultimos9`, `at_wa_ultimos9`, `at_wa_q`, `at_wa_vinculo_texto`, `at_wa_citas_armar`.
- Produces:
  - `at_wa_citas(string $tel): array`. Lanza `AtWaConsultaNoDisponible`.
    - Lee los diagnósticos por los últimos 9 dígitos de `phone` **o** por los correos de las fichas (`$ficha['agenda']['correo']`).
    - Lee los seguimientos por `phone` **o** por `client_email` de esos correos.
  - `at_wa_cita_del_numero(string $tel, string $tipo, int $id): ?array`, que devuelve la cita si está entre las de `at_wa_citas($tel)`.
  - Ruta `GET automatiza-tech/v1/wa-citas?phone=` → 200 `{estado:'ok', citas:[…]}`, o 503 `{estado:'no_disponible', citas:[]}`.

- [ ] **Step 1: Write the failing tests** (each creates its own synthetic rows and deletes them at the end):
  - `sin clave → 401`; `con clave y número sin citas → 200, citas vacías`
  - `diagnóstico por teléfono con otro formato` (+56 9 0000 1234 vs 56900001234)
  - `diagnóstico sin teléfono por correo de la ficha`
  - `seguimiento por correo de la ficha, sin teléfono`
  - `una cita de otro número no aparece`
  - `cita_del_numero de otro número → null`
  - `base caída → 503 no_disponible`, with the proxy `WaWpdbQueFalla` from `fallos-wp-test.php`
- [ ] **Step 2:** Run `AT_WP_LOAD=C:/wamp64/www/automatiza-tech/wp-load.php /c/wamp64/bin/php/php8.3.36/php.exe tests/wa-contexto/citas-wp-test.php`. Expected: failures (route missing). If WAMP MySQL is off, record NOT_RUN in the ledger and continue with the static review.
- [ ] **Step 3:** Implement. The route callback is `at_wa_rest_citas(WP_REST_Request $r): WP_REST_Response`.
- [ ] **Step 4:** Run again. Expected: 0 fallas. Also `php -l` on both new files.
- [ ] **Step 5:** Commit: `feat(wa): citas futuras del número y ruta protegida wa-citas`.

### Task 3: Cancelar con todos los efectos — `POST /wa-citas/cancelar`

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/wa-contexto/citas.php`
- Test: `tests/wa-contexto/cancelar-wp-test.php`

**Interfaces:**
- Consumes: `at_wa_cita_del_numero`, `automatiza_tech_delete_google_calendar_event($event_id)` (`inc/api-endpoints.php:383`).
- Produces:
  - `at_wa_cita_cancelar(string $tel, string $tipo, int $id, string $motivo): array`, con `$motivo` `cancelada`|`reprogramada`. Devuelve `{ok: bool, motivo: 'no_vigente'|'error'|'', cita: ?array}`.
  - Ruta `POST automatiza-tech/v1/wa-citas/cancelar` con cuerpo `{phone, tipo, id, motivo}`. Responde 200 con `ok:true`, 409 con `no_vigente`, 503 con `no_disponible` y 500 con `error`.
- **Efectos, en orden:**
  1. Estado cancelado, sin `hard_delete`.
  2. El evento de Calendar se borra si la fila tiene id de evento. En el diagnóstico es la columna que guarda `automatiza_tech_save_lead`, que hay que confirmar en su INSERT (`google_event_id` o `event_id`); en el seguimiento es `google_event_id`.
  3. Correo a la persona con `wp_mail`: «Tu cita fue cancelada» o «Tu cita se reprogramó», con la etiqueta.
  4. Correo a `get_option('admin_email')` con el número, la etiqueta y el motivo.
  
  Si el paso 1 falla, devuelve `ok:false, motivo:'error'` y no sigue. Los pasos 2 a 4 no cambian `ok`, pero si alguno falla se registra con `error_log`.

- [ ] **Step 1: Write the failing tests** (`pre_http_request` captures the calendar webhook URL; `pre_wp_mail` counts mails and returns true):
  - `cancelar diagnóstico propio → 200 ok, status cancelled, cancelled_at con fecha, 1 llamada al webhook de borrar evento, 2 correos`
  - `cancelar seguimiento propio → status cancelled, webhook con su google_event_id`
  - `doble cancelación → 409 no_vigente y un solo correo` (Review Focus 1)
  - `cita de otro número → 409 no_vigente y la fila sigue activa`
  - `la fila nunca se borra` (`SELECT COUNT(*)` before/after equal)
  - `sin clave → 401`
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement `at_wa_cita_cancelar` and the callback `at_wa_rest_cancelar_cita`.
- [ ] **Step 4:** Run. Expected: 0 fallas; `php -l`.
- [ ] **Step 5:** Commit: `feat(wa): cancelar una cita del número con estado, calendario y avisos, sin borrar`.

### Task 4: Excluir la cita que se mueve del límite de reuniones

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php` (`automatiza_tech_limite_reuniones_activas` L2461 and its call at L2541)
- Modify: `wp-content/themes/automatiza-tech/inc/api-endpoints.php` (call at L756 in `automatiza_tech_save_lead`)
- Modify: `wp-content/themes/automatiza-tech/inc/wa-contexto/citas.php`
- Test: `tests/wa-contexto/limite-excluir-wp-test.php`

**Interfaces:**
- Produces:
  - `automatiza_tech_limite_reuniones_activas($email, array $excluir = [])`, con `$excluir = ['tipo' => 'diagnostico'|'seguimiento', 'id' => int]`. La cita excluida no cuenta. Sin `$excluir`, el resultado es el mismo de hoy.
  - `at_wa_excluir_de_request(WP_REST_Request $r): array` devuelve `[]` salvo que `at_cc_rest_auth($r) === true` y `excluir_tipo`/`excluir_id` sean válidos.
  - Los dos lugares que llaman al límite pasan `function_exists('at_wa_excluir_de_request') ? at_wa_excluir_de_request($request) : []`.

- [ ] **Step 1: Write the failing tests:**
  - `2 activas sin excluir → alcanzado`
  - `2 activas excluyendo una → no alcanzado`
  - `excluir sin clave en /leads → se ignora y rechaza por límite` (synthetic email, never the exempt test email)
  - `excluir con clave en /leads → crea`
  - `excluir con clave al crear seguimiento → crea`
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement. Only signature and callers change. The old behavior is pinned by the first test.
- [ ] **Step 4:** Run this test plus `tests/wa-contexto/followup-vinculo-wp-test.php`. Expected: 0 fallas in both.
- [ ] **Step 5:** Commit: `feat(wa): el límite de reuniones puede excluir la cita que se reprograma, solo con clave`.

### Task 5: Portales del cliente en `wa-contexto`

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/wa-contexto/datos.php` (`at_wa_contexto`)
- Test: `tests/wa-contexto/portales-wp-test.php`

**Interfaces:**
- Consumes: `at_crm_url_portal(int): string` (`crm-ai-completo.php:8834`).
- Produces:
  - `at_wa_portales(string $tel, array $fichas): array`, que devuelve `[{nombre: string, url: string}]`.
  - `at_wa_contexto()` suma la clave `portales`: la lista para un cliente y `[]` en cualquier otro caso, `no_disponible` incluido.

- [ ] **Step 1: Write the failing tests:**
  - `cliente con su teléfono en la ficha → 1 portal con url que contiene crm_view=timeline&cid=<id>`
  - `cliente encontrado solo por la propuesta (teléfono de la ficha distinto) → sin portales`
  - `prospecto → portales vacío`
  - `dos fichas con el mismo teléfono → 2 portales con nombres distintos`
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement. The `nombre` is the ficha's `empresa`, or `nombre` when there is no empresa.
- [ ] **Step 4:** Run this test plus `datos-wp-test.php` and `rest-wp-test.php`. Expected: 0 fallas.
- [ ] **Step 5:** Commit: `feat(wa): wa-contexto devuelve el enlace al portal de cada ficha del cliente`.

### Task 6: Lógica JS de los nodos de citas y la línea del portal

**Files:**
- Create: `N8N/wa-citas/nodos/citas.js` (block between `// <<WA CITAS: inicio>>` and `// <<WA CITAS: fin>>`, exported like `contexto.js`)
- Create: `N8N/wa-citas/nodos/citas.test.js`
- Modify: `N8N/wa-reconoce/nodos/contexto.js` (`waLineaContexto`) and `contexto.test.js`

**Interfaces:**
- Produces, in `citas.js`:
  - `waLeerBotonCita(id: string)`. Devuelve `{confirmar: boolean, accion: 'cancelar'|'reprogramar', tipo: 'diagnostico'|'seguimiento', id: number}`, o `{tengo: true}`, o `null`.
  - `waMensajeCitas(r: {estado, citas, situacion}, accion, to)`. Devuelve `{mensaje, avisarLuis: boolean, cita: object|null}`.
    - 0 citas: texto más los botones `btn_agendar_demo` (persona nueva) o `seg_general` (prospecto o cliente), y siempre `cita_tengo`.
    - 1 cita: la confirmación.
    - 2 o 3: botones `cita_<c|r>_<d|s>_<id>`.
    - 4 o más: lista.
    - `no_disponible`: texto y `avisarLuis: true`.
  - `waConfirmarCita(cita, accion, to)`: mensaje con «Sí, cancelar» o «Sí, cambiar» (`cita_ok_…`) y «No, mantenerla» (`btn_mantener_cita`).
  - `waDecidirBoton(boton, r, to)`. Devuelve `{paso: 'confirmar'|'cancelar'|'reprogramar'|'no_disponible'|'tengo', mensaje, cita, avisarLuis}`. Usa la lista fresca `r`: si la cita del botón ya no está en `r.citas`, va a `no_disponible` con las vigentes.
- Produces, in `contexto.js`: `waLineaContexto(ctx, prop)` adds, when `ctx.portales.length`:
  - con un portal: «Si pregunta por el avance o cualquier detalle de su proyecto, mándale el enlace a su portal, donde ve la línea de tiempo y puede preguntarle a MAXTECH: <url>»;
  - con varios: la misma frase y los enlaces con el nombre de cada portal.

- [ ] **Step 1: Write the failing tests** (`node:test`):
  - `0 citas, persona nueva: btn_agendar_demo y cita_tengo`
  - `0 citas, prospecto: seg_general y cita_tengo`
  - `1 cita cancelar: texto exacto «Tienes esta cita: …. ¿La cancelamos?» y botones cita_ok_c_d_7 / btn_mantener_cita`
  - `1 cita reprogramar: «¿La cambiamos de día?» y cita_ok_r_s_9`
  - `3 citas → 3 botones con títulos ≤20 y distintos`
  - `5 citas → lista con títulos ≤24, descripciones ≤72`
  - `no_disponible → texto exacto y avisarLuis`
  - `waLeerBotonCita: cita_ok_r_d_12, cita_c_s_3, cita_tengo, basura → null`
  - `botón de cita que ya no está → mensaje de no disponible con las vigentes` (Review Focus 5)
  - `emojis en la etiqueta no rompen el tope`
  - in `contexto.test.js`: `cliente con un portal → la línea trae la url`, `prospecto con portales vacío → sin línea de portal`
- [ ] **Step 2:** Run `node --test N8N/wa-citas/nodos/citas.test.js` and `node --test N8N/wa-reconoce/nodos/contexto.test.js`. Expected: fail.
- [ ] **Step 3:** Implement. Reuse `waTituloCorto` (copy it into the `WA CITAS` block; the two blocks go to different nodes).
- [ ] **Step 4:** Run both. Expected: all pass, and the 27 existing tests in `contexto.test.js` still pass.
- [ ] **Step 5:** Commit: `feat(wa): lógica de los botones de citas y línea del portal, con pruebas`.

### Task 7: Script n8n — cancelar con botones y tipo de cambio

**Files:**
- Create: `N8N/wa-citas/aplicar.py`. It imports the helpers from `N8N/wa-reconoce/aplicar.py` (`api_con`, `nodo_nuevo`, `conectar`, `comprobar_js`, `simular_nodo`, `nodos_tocados`, `verificar_publicacion`) by adding its folder to `sys.path`. Preview by default; `--subir` uploads web and WhatsApp.
- Create: `N8N/wa-citas/aplicar_test.py`
- Create: `N8N/wa-citas/README.md`

**Interfaces:**
- Consumes: Tasks 2, 3 and 6.
- Produces:
  - `cambiar_whatsapp_citas(w, bloque, registro)`;
  - `cambiar_web_cambio(w, registro)`;
  - `comprobar_citas(w)`;
  - the WhatsApp node names:
    - Route Action outputs 2 (`cancel_appointment`), 3 (`reschedule_appointment`) and 8 (`reschedule_followup`) → «Citas del Número» (HTTP GET `/wa-citas`, credential «AT REST Secret (header)», `neverError`, `onError=continueRegularOutput`) → «Mensaje Citas» (Code, `WA CITAS` block; the action comes from `$('Route Action').first().json.action`) → «Send Citas», with «¿Avisar a Luis (citas)?» → «Notify Admin Citas»;
    - «Button Action» gets the rule `startsWith cita_` → «Leer Botón Cita» → «Citas Vigentes» → «Decidir Botón Cita» → «Ruta Botón Cita» (Switch on `paso`);
    - the `cancelar` path → «Cancelar Cita» (POST `/wa-citas/cancelar`) → «¿Cita cancelada?» → «Send Cita Cancelada» or «Send Cita No Cancelada» + «Notify Admin Citas»;
    - the `confirmar` and `no_disponible` paths → «Send Citas» with the message from «Decidir Botón Cita»;
    - the `tengo` path → «Send Citas» («Le avisamos a Luis para que te escriba.») + «Notify Admin Citas» with the number.
- Exchange rate: the four nodes with the values from Global Constraints. The agent prompt, outside the Cerebro marks, adds the line: «Si no tienes el tipo de cambio (la tasa viene vacía o en 0), da los precios solo en USD; nunca escribas "$0 CLP".»
- «Ask Cancel Data», «Ask Reschedule Data» and «Ask Reschedule Followup Data» stay in the flow, disconnected from Route Action, as rollback.

- [ ] **Step 1: Write the failing tests** in `aplicar_test.py`, over the fixture `wa-actual.json` saved without secrets in `N8N/wa-citas/fixtures/` (the same minimal-fixture style as `wa-reconoce/aplicar_test.py`):
  - `rutas de cancelar y reprogramar ya no van a Ask … Data`
  - `todo $('…') lee un nodo que existe`
  - `todas las conexiones van a nodos que existen`
  - `bloque idéntico a citas.js y node --check`
  - `consulta caída → Mensaje Citas da no_disponible y avisa`
  - `los cuatro nodos de tipo de cambio con onError, timeout y reintentos`
  - `idempotente: aplicar dos veces = una`
  - `Button Action con la regla cita_ antes del fallback`
- [ ] **Step 2:** Run `python -I -B -X utf8 N8N/wa-citas/aplicar_test.py`. Expected: fail.
- [ ] **Step 3:** Implement. Preview: `python -I -B -X utf8 N8N/wa-citas/aplicar.py` makes the GET, applies in memory, runs the checks and saves previews in `~/respaldos/n8n/wa-citas/<fecha>/`.
- [ ] **Step 4:** Run the tests and the preview. Expected: tests OK, and the preview lists the touched nodes with no failed check.
- [ ] **Step 5:** Commit: `feat(n8n): cancelar con botones de las citas del número y tipo de cambio protegido (vista previa)`.

### Task 8: Script n8n — reprogramar sin perder la cita vieja

**Files:**
- Modify: `N8N/wa-citas/aplicar.py`, `N8N/wa-citas/aplicar_test.py`
- Modify (only through the script): agenda `PejBgr2LZQSAbbBy`

**Interfaces:**
- Consumes: Tasks 3, 4, 6 and 7.
- Produces, in WhatsApp:
  - the `reprogramar` path of «Ruta Botón Cita» → «Marcar Reagendar» (Redis set `reagendar_cita_<tel>`, TTL 1800, with the old cita). Then:
    - seguimiento: write `followup_resched_client_<tel>` with name, email, company, subject, notes, `contrato_id` and `propuesta_id` of the old cita;
    - then the existing calendar («Get Appointments Config» → «Generate Days»).
  - In «Is Seguimiento?», the `false` output first goes through «¿Reagendando diagnóstico?», which reads the mark.
    - With a diagnóstico mark: «Datos Cita Vieja» builds the booking with the old name and email and `excluir_tipo`/`excluir_id` → «Create Appointment» (existing).
    - Without a mark: «Ask For Name», as today.
  - After «Create Appointment» → «¿Agendada nueva?» (`$json.agendada === true`).
    - With a mark: «Cancelar Cita Vieja» (POST `/wa-citas/cancelar`, `motivo: reprogramada`) → «Borrar Marca Reagendar» → «Send Booking Confirmation».
    - If it was not booked: the old cita is not touched.
  - After «¿Seguimiento botón agendado?» (true) → «¿Reagendando seguimiento?» → «Cancelar Cita Vieja (seguimiento)» → «Borrar Marca Reagendar» → «Send Seguimiento Confirmation». «Prepare Seguimiento Data Direct» and «… Custom» add `excluir_tipo`/`excluir_id` when a mark exists.
  - The reminder buttons: «Delete Lead For Reschedule» and «Cancel Followup For Reschedule» are replaced by «Marcar Reagendar (botón)». It reads the cita with `/wa-citas`; if it does not belong to the number, it goes to «Esa cita ya no está disponible.».
- Produces, in `PejBgr2LZQSAbbBy` (additive; Instagram does not send these fields):
  - «Set Success Message» adds `agendada: true`;
  - the HTTP node that creates the diagnóstico sends the header `X-AT-Secret` (same credential) and `excluir_tipo`/`excluir_id` when they come in the input.

- [ ] **Step 1: Write the failing tests** (simulating with `simular_nodo`/`evaluar`, as `comprobar_fallos_y_reagendar`):
  - `reprogramar diagnóstico: con marca no se pide nombre`
  - `ocupado → no se cancela la vieja` (Review Focus 4: PejBgr returns the Busy message without `agendada`)
  - `agendada → se cancela la vieja con motivo reprogramada y se borra la marca`
  - `reprogramar seguimiento conserva contrato_id y propuesta_id`
  - `sin marca, el diagnóstico sigue a Ask For Name como hoy`
  - `botón de recordatorio de reprogramar ya no cancela antes`
  - `PejBgr: el objeto del diagnóstico sin excluir es idéntico al de hoy`
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the tests and the preview of both flows. Expected: OK, no failed check.
- [ ] **Step 5:** Commit: `feat(n8n): reprogramar con botones; la cita vieja se cancela cuando la nueva queda agendada (vista previa)`.

### Task 9: Tipo de cambio guardado en WordPress

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/api-endpoints.php` (`automatiza_tech_get_exchange_rate`, L841)
- Test: `tests/wa-contexto/tipo-cambio-wp-test.php`

**Interfaces:**
- Produces: transient `at_tipo_cambio_usd_clp`, valid 6 h (`6 * HOUR_IN_SECONDS`). With a valid transient, `get_current_exchange_rate()` is not called. A successful lookup updates the transient and `automatiza_tech_last_exchange_rate`. The response is the same as today.

- [ ] **Step 1: Write the failing tests:**
  - `con transient no consulta` (`pre_http_request` counts 0 calls)
  - `sin transient guarda la tasa`
  - `consulta caída → última tasa conocida`
  - `respuesta con las mismas claves de hoy`
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run. Expected: 0 fallas; `php -l`.
- [ ] **Step 5:** Commit: `perf(wp): el tipo de cambio se guarda 6 h y si el banco falla responde la última tasa`.

### Task 10: Regla del portal, documentación y despliegue

**Files:**
- Modify: `N8N/cerebro-at/personalidad-whatsapp.md`, which adds: «Si es cliente y pregunta por su proyecto, mándale el enlace a su portal que viene en el contexto; nunca inventes un enlace.»
- Create: `Docs/METODO_AT/WHATSAPP-CITAS-Y-PORTAL.md` (guide: what it does, pieces, how to apply, rollback); pointer in `CLAUDE.md` (Punteros de proyecto)
- Modify: `N8N/wa-citas/README.md`

- [ ] **Step 1:** Run every test: `node --test` on both JS files; `aplicar_test.py` for wa-reconoce and wa-citas; `php` on `puras-test.php` and `citas-puras-test.php`; and the `*-wp-test.php` in `tests/wa-contexto/` when WAMP is on. Expected: all OK, or NOT_RUN recorded with the reason.
- [ ] **Step 2:** Codex B review request in `Docs/superpowers/reviews/2026-10-29-whatsapp-citas-botones-pedido-revision-codex.md` (checklists A/B, PASS/FAIL/NOT_RUN, report outside the repo). Fix with TDD until `REVIEWED`.
- [ ] **Step 3: Deploy, with Luis's OK in chat:**
  1. Notify the Instagram session about `PejBgr2LZQSAbbBy`.
  2. WordPress by SSH, with a backup: the new and modified files of `inc/wa-contexto/`, `admin-followup-meetings.php` and `api-endpoints.php`. Each file goes through `php -l`, and the md5 is checked after.
  3. Verify `wa-citas` without the key → 401, and with the key → 200.
  4. `python -I -B -X utf8 N8N/wa-citas/aplicar.py --subir` (agenda, then WhatsApp, then web).
  5. `sincronizar.py --subir` for the personality.
  6. Verify the `activeVersion`.
- [ ] **Step 4: Real test with Luis's number:**
  - cancel meeting 69 → status cancelled, event removed, emails;
  - schedule and reschedule a test meeting → the old one is cancelled only after;
  - Fase B as a client → portal link.

  Then clean up the test data with `~/respaldos/wa-prueba-luis.json`.
- [ ] **Step 5:** Commit the docs, push, and open the PR with a minimal body (public repo). Then run the Aseo checkpoints `before-push` and `after-prod`.
