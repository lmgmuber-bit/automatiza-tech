# Reseña · Resumen de envíos por correo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Después de cada tanda de invitaciones a reseña (diario de las 10:00 o envío manual desde la ficha), Luis recibe un correo con un cuadro por cliente y ✓ / ✗ / ⏳ / — en Correo y WhatsApp.

**Architecture:** El sitio arma el resumen (ruta REST nueva `GET /at/v1/resena/resumen?desde=`) con funciones puras ya probadas; avisa a n8n después de un envío manual. Un flujo n8n nuevo «Reseña · Resumen» recibe el aviso, espera 15 minutos, pide el resumen y manda el correo. El flujo diario llama al mismo webhook al terminar si hubo pendientes.

**Tech Stack:** PHP 8 (tema WordPress `automatiza-tech`), n8n (generador Python como los de `N8N/resena-google/`), JavaScript del nodo Code probado con Node.

**Spec:** `Docs/superpowers/specs/2026-11-24-resena-resumen-envios-design.md`

**Rama y carpeta:** `codex/resena-google-impl`, worktree `C:/wamp64/www/automatiza-tech/.worktrees/guias`. PHP local: `C:/wamp64/bin/php/php8.5.0/php.exe`.

## Global Constraints

- Repo público: sin correos reales, teléfonos, nombres de clientes ni claves en código, pruebas o documentos. En pruebas, solo datos ficticios `@ejemplo.cl` y `+5690000000x`.
- Autenticación de la ruta nueva: la misma `permission_callback` `at_rg_auth` que las otras rutas de reseña (`X-AT-Secret`).
- Webhook nuevo: path `at-resena-resumen`, POST, cabecera `X-AT-Secret` (misma credencial de entrada que `at-resena-whatsapp`), `responseMode: onReceived`.
- Espera en n8n: 15 minutos.
- Símbolos exactos: `✓` llegó · `✗` no salió o Meta avisó que no se entregó · `⏳` sin confirmar · `—` no correspondía (con motivo).
- Correo ✓ = el servidor lo aceptó (`aceptado`). WhatsApp ✓ = `entregado` o `leido`.
- Asunto: `Reseñas enviadas: N de M con todo OK` (N = filas con todo OK, M = filas). Las filas de prueba llevan «(prueba)» junto al cliente.
- Sin envíos en la tanda → no hay correo.
- Destinatario: `aviso_a` = `at_cc_correo_avisos()`, leído de la base, nunca escrito en el repo.
- n8n: no usar `$env`; ninguna expresión `={{ … }}` puede tener `}}` interno (lo verifica `tests/plan-trabajo/resena-n8n-expresiones-test.js`).
- Nada se despliega ni se publica en n8n sin el OK de Luis (sección final). Las tareas 1–5 son solo locales.
- Sin `git stash`. Un commit por tarea, mensaje en español, terminado en `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Nombre de cliente con HTML (`<b>`, `<script>`, comillas): el correo lo muestra como texto, sin romper la tabla → prueba en Task 3.
2. `desde` vacío, mal formado, futuro o de hace más de 48 h: la ruta responde 400 y no entrega datos → prueba en Task 1 y Task 2.
3. Registro de reseña ilegible en la tabla: la ruta lo salta y sigue con los demás (no 500) → prueba en Task 2.
4. Un envío manual que falla (`WP_Error`) o una prueba (`operacion=prueba`) no pide resumen; solo «Enviar ahora» y «Volver a enviar» aceptados → prueba en Task 2.
5. El flujo diario sin pendientes no llama al webhook; con 3 pendientes lo llama una sola vez → prueba en Task 4.

---

### Task 1: Funciones puras del resumen

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` (agregar al final)
- Create: `tests/plan-trabajo/resena-resumen-puras-test.php`
- Modify: `tests/plan-trabajo/resena-suite.ps1` (agregar el test nuevo a la lista del `foreach`)

**Interfaces:**
- Consumes: `at_rg_email()`, `at_rg_telefono()`, `at_rg_fecha()`, `at_rg_canales()` (ya existen en `resena-puras.php`).
- Produces:
  - `at_rg_resumen_desde(string $v, DateTimeImmutable $ahora): ?DateTimeImmutable` — `null` si no parsea, si es más de 5 minutos en el futuro o más de 48 horas en el pasado.
  - `at_rg_motivo_sin_canal(string $canal, array $c, array $ctx): string` — texto del «—».
  - `at_rg_resumen_canal(string $canal, array $estado, string $motivo): array` → `['simbolo' => string, 'texto' => string]`.
  - `at_rg_resumen(array $proyectos, DateTimeImmutable $desde): array` → `['total' => int, 'ok' => int, 'filas' => array]`. Cada elemento de `$proyectos` es `['id' => int, 'cliente' => array (fila crm: nombre, empresa, email, telefono), 'ctx' => array (como `at_rg_contexto`), 'datos' => array (como `at_rg_datos`)]`. Cada fila: `['proyecto_id','cliente','proyecto','modo','tipo','creado_at','correo'=>['simbolo','texto'],'whatsapp'=>['simbolo','texto']]`. `cliente` = `nombre` del CRM (o `empresa` si no hay nombre); `proyecto` = `empresa` (o `'—'`). Solo intentos con `creado_at >= $desde`, ordenados por `creado_at` ascendente, tope 50 filas. Fila OK = ningún canal en `✗`/`⏳` y al menos un `✓`.

Reglas de `at_rg_resumen_canal` (el `texto` de ✗ y ⏳ es el `detalle` del canal si no está vacío; si está vacío, el texto por defecto de la tabla):

| Canal | Estado | Símbolo | Texto por defecto |
|---|---|---|---|
| correo | aceptado | ✓ | Aceptado por el servidor de correo |
| correo | fallido | ✗ | No salió |
| correo | en_curso, desconocido | ⏳ | Sin confirmar |
| whatsapp | entregado | ✓ | Entregado |
| whatsapp | leido | ✓ | Leído |
| whatsapp | aceptado, en_curso, desconocido | ⏳ | Meta lo aceptó; sin confirmación de entrega |
| whatsapp | fallido | ✗ | No se entregó |
| ambos | no_intentado | — | `$motivo` |

Reglas de `at_rg_motivo_sin_canal`, en este orden: correo sin email válido → `sin correo`; WhatsApp sin teléfono válido → `sin teléfono`; WhatsApp con permiso incompleto (mismas condiciones que `at_rg_canales` con `bajas = []`) → `sin permiso de WhatsApp`; el canal sale `true` con `bajas = []` y `false` con las bajas del `$ctx` → `pidió la baja`; si no → `no correspondía`.

- [ ] **Step 1: Escribir la prueba que falla** en `tests/plan-trabajo/resena-resumen-puras-test.php`, con el mismo arnés `ok()` de `resena-puras-test.php` (requiere solo `resena-puras.php`, sin WordPress) y al final `echo "$n comprobaciones, $fallas fallas\n"; exit($fallas ? 1 : 0);`. Casos mínimos (zona `America/Santiago`, `$ahora = 2026-11-24 10:15:00`):
  - `at_rg_resumen_desde('2026-11-24T10:00:00-03:00', $ahora)` no es null; `''`, `'ayer'`, `'2026-11-24T11:00:00-03:00'` (futuro > 5 min) y `'2026-11-22T10:00:00-03:00'` (> 48 h) devuelven null.
  - Cada fila de la tabla de `at_rg_resumen_canal` da el símbolo exacto; un `fallido` con detalle `'Meta: código 131049'` devuelve ese texto.
  - `at_rg_motivo_sin_canal`: cliente sin email → `sin correo`; sin teléfono → `sin teléfono`; permiso `['autorizado'=>false]` → `sin permiso de WhatsApp`; permiso válido y una baja `['alcance'=>'resenas','canal'=>'whatsapp','telefono'=>'+56900000001']` del mismo teléfono → `pidió la baja`.
  - `at_rg_resumen`: dos proyectos; uno con un intento antes de `desde` (no aparece) y uno después con correo `aceptado` y WhatsApp `leido` (✓/✓, cuenta en `ok`); otro con correo `aceptado` y WhatsApp `aceptado` (⏳, no cuenta en `ok`); otro intento `modo=prueba` aparece con `modo` `'prueba'`. `total === 3`, `ok === 1`, orden por `creado_at`.
  - 60 intentos después de `desde` → `count(filas) === 50`.

- [ ] **Step 2: Correr la prueba y ver que falla**
  Run: `C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-resumen-puras-test.php`
  Expected: error fatal «Call to undefined function at_rg_resumen_desde».

- [ ] **Step 3: Implementar las cuatro funciones** al final de `resena-puras.php` (sin tocar las existentes, sin llamadas a WordPress).

- [ ] **Step 4: Correr la prueba y la suite**
  Run: `C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-resumen-puras-test.php` → `0 fallas`.
  Run: `powershell -File tests/plan-trabajo/resena-suite.ps1` → `Suite offline completa: 0 fallos`.

- [ ] **Step 5: Commit** `feat(resena): funciones puras del resumen de envíos`.

---

### Task 2: Ruta `/resena/resumen` y aviso tras envío manual

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` (función de lectura)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` (constante, ruta, aviso)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` (`at_rg_panel_accion`, ~línea 903)
- Create: `tests/plan-trabajo/resena-v8-resumen-casos.php`
- Modify: `tests/plan-trabajo/resena-estado-test.php` (requerir el archivo de casos nuevo antes del `echo` final; si hace falta, que el doble `wp_safe_remote_post` guarde también `$p` en `$GLOBALS['remote_args'][]` sin cambiar lo que ya guarda)

**Interfaces:**
- Consumes: Task 1 (`at_rg_resumen_desde`, `at_rg_resumen`); existentes `at_rg_fila`, `at_rg_datos`, `at_rg_cliente_proyecto`, `at_rg_contexto`, `at_rg_auth`, `at_rg_error`, `at_rg_ahora`, `at_cc_correo_avisos`.
- Produces:
  - `at_rg_todas_filas(): ?array` en `resena-datos.php` — `SELECT * FROM at_rg_tabla() ORDER BY id`; `null` si `$wpdb->last_error`.
  - `const AT_N8N_RESENA_RESUMEN = 'https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-resumen';` en `resena.php`, junto a `AT_N8N_RESENA_WHATSAPP`.
  - `at_rg_rest_resumen(WP_REST_Request $r)` → `['desde' => string ISO, 'aviso_a' => string, 'total' => int, 'ok' => int, 'filas' => array]`, o `at_rg_error('datos', 400)` si `desde` no pasa `at_rg_resumen_desde`, o `at_rg_error('base', 503)` si la lectura falla o `at_rg_contexto` da null. Las filas cuyo `at_rg_datos` lanza excepción se saltan.
  - Ruta: `register_rest_route('at/v1', '/resena/resumen', ['methods' => 'GET', 'callback' => 'at_rg_rest_resumen', 'permission_callback' => 'at_rg_auth'])`, dentro del `rest_api_init` existente.
  - `at_rg_pedir_resumen(string $desde, string $origen): void` — `wp_safe_remote_post(AT_N8N_RESENA_RESUMEN, ['timeout' => 3, 'blocking' => false, 'redirection' => 0, 'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET], 'body' => wp_json_encode(['desde' => $desde, 'origen' => $origen])])`; no hace nada si `AT_REST_SECRET` no está definido o vacío; nunca lanza (try/catch).
  - En `at_rg_panel_accion`: antes de `at_rg_reclamar`, `$desde = at_rg_ahora()->modify('-1 minute')->format(DATE_ATOM)`; si `$op` es `enviar` o `reenviar` y `$r` no es `WP_Error`, llamar `at_rg_pedir_resumen($desde, 'manual')`.

- [ ] **Step 1: Escribir los casos que fallan** en `resena-v8-resumen-casos.php`, con el doble de base y el arnés de `resena-estado-test.php` (mirar `resena-v7-casos.php` para armar proyectos e intentos):
  - Ruta con `desde` hace 10 minutos y dos proyectos con intentos recientes → `total === 2`, `aviso_a === 'operacion@ejemplo.cl'`, cada fila trae `correo.simbolo` y `whatsapp.simbolo`.
  - `desde` `''`, `'ayer'` y de hace 3 días → `WP_Error` con código `resena_datos` y status 400.
  - Una fila con `datos` = `'{roto'` en la tabla → la ruta responde igual con las otras filas.
  - Tras disparar `rest_api_init` con los dobles, la ruta registrada `/resena/resumen` tiene `permission_callback === 'at_rg_auth'` y `methods === 'GET'` (si el doble de `register_rest_route` no guarda los registros, agregarlo en `$GLOBALS['rutas']`).
  - Panel `operacion=enviar` aceptado → `$GLOBALS['remote']` contiene la URL `…/at-resena-resumen` una vez y su cuerpo trae `"origen":"manual"`.
  - Panel `operacion=prueba` aceptado → no hay llamada a `at-resena-resumen`.
  - Panel `operacion=enviar` que devuelve `WP_Error` (por ejemplo, proyecto sin término) → no hay llamada a `at-resena-resumen`.

- [ ] **Step 2: Correr y ver que falla**
  Run: `C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php`
  Expected: FAIL o fatal en los casos nuevos («undefined function at_rg_rest_resumen»); los casos antiguos siguen en PASS.

- [ ] **Step 3: Implementar** las piezas de «Produces».

- [ ] **Step 4: Correr la integración y la suite**
  Run: `C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php` → `… 0 fallas`.
  Run: `powershell -File tests/plan-trabajo/resena-suite.ps1` → `0 fallos` (incluye el lint de los PHP de reseña y de `avance-panel.php`).

- [ ] **Step 5: Commit** `feat(resena): ruta del resumen y aviso a n8n tras envío manual`.

---

### Task 3: Armado del correo (JavaScript del nodo Code)

**Files:**
- Create: `N8N/resena-google/resumen-correo.js`
- Create: `tests/plan-trabajo/resena-resumen-correo-test.js`

**Interfaces:**
- Consumes: la respuesta de la ruta de Task 2 (`{desde, aviso_a, total, ok, filas}`).
- Produces: `function armarCorreo(resumen, origen)` → `{ aviso_a, asunto, html, enviar }`, sin `require`, sin red, sin `}}` en el código fuente. Al final del archivo: `if (typeof module !== 'undefined') module.exports = { armarCorreo };` (Task 4 inserta el texto del archivo en el nodo Code sin esa línea).
  - `enviar` = `resumen.total > 0`.
  - `asunto` = `Reseñas enviadas: ${ok} de ${total} con todo OK`, y si `origen === 'manual'` se agrega ` (envío manual)`.
  - `html`: tabla de correo (estilos en línea, ancho máximo 640 px, encabezado azul AT `#0B2A4A`, acento turquesa `#14B8A6`), columnas Cliente · Proyecto · Correo · WhatsApp; cada celda de canal muestra el símbolo y debajo el texto en gris; filas `modo === 'prueba'` con « (prueba)» junto al cliente; pie con la hora de `desde` y «Correo automático de AutomatizaTech». Todo texto que viene de `filas` pasa por una función `esc()` que reemplaza `& < > " '`.

- [ ] **Step 1: Escribir la prueba que falla** `tests/plan-trabajo/resena-resumen-correo-test.js` (Node puro, `assert`):
  - Con `total 2, ok 1` y `origen 'diario'` → `asunto === 'Reseñas enviadas: 1 de 2 con todo OK'`.
  - Con `origen 'manual'` → el asunto termina en ` (envío manual)`.
  - Cliente `'<script>x</script> & "Co"'` → el `html` contiene `&lt;script&gt;` y no contiene `<script>`.
  - Las cuatro marcas `✓ ✗ ⏳ —` de las filas aparecen en el `html`.
  - Fila de prueba → `(prueba)` en el `html`.
  - `total 0` → `enviar === false`.
  - `fs.readFileSync` del archivo no contiene `}}` ni `$env`.

- [ ] **Step 2: Correr y ver que falla**
  Run: `node tests/plan-trabajo/resena-resumen-correo-test.js`
  Expected: error «Cannot find module» o `armarCorreo is not a function`.

- [ ] **Step 3: Implementar** `resumen-correo.js`.

- [ ] **Step 4: Correr la prueba** → sale con código 0 e imprime `OK`.

- [ ] **Step 5: Commit** `feat(resena): armado del correo del resumen`.

---

### Task 4: Flujo n8n «Reseña · Resumen» y llamada desde el diario (generador)

**Files:**
- Create: `N8N/resena-google/resumen.py`
- Modify: `N8N/resena-google/aplicar.py` (función `workflows()`, flujo diario)
- Create: `tests/plan-trabajo/resena-resumen-n8n-test.py`
- Modify: `tests/plan-trabajo/resena-suite.ps1` (correr el test Python nuevo, el test Node de Task 3 y `python -B N8N/resena-google/resumen.py --dry-run | node tests/plan-trabajo/resena-n8n-expresiones-test.js`)
- Modify (si hace falta): `tests/plan-trabajo/resena-n8n-expresiones-test.js`, para que sus comprobaciones propias del flujo de WhatsApp (`workflows[1]`) solo corran cuando ese flujo esté en la entrada

**Interfaces:**
- Consumes: `node`, `edge`, `http`, `WP` de `aplicar.py` (importarlos, no copiarlos); `resumen-correo.js` de Task 3.
- Produces:
  - `resumen.workflow(rest_credential, webhook_credential, smtp_credential, remitente) -> dict` — flujo «Reseña · Resumen», `active: False`, mismos `settings` que los de `aplicar.py`. Nodos y conexiones en orden:
    1. `Webhook resumen` — webhook, path `at-resena-resumen`, POST, `authentication: headerAuth` con `webhook_credential`, `responseMode: onReceived`, `webhookId` determinístico `uuid5(NAMESPACE_URL, 'at-resena-resumen')`.
    2. `Esperar 15 minutos` — `n8n-nodes-base.wait`, `amount: 15`, `unit: minutes`.
    3. `Pedir resumen` — GET `={{ "<WP>/resumen?desde=" + encodeURIComponent($('Webhook resumen').first().json.body.desde) }}` con `rest_credential`, **sin** `continueRegularOutput` (si WordPress falla, la ejecución queda en error).
    4. `Armar correo` — Code: el texto de `resumen-correo.js` sin la línea de `module.exports`, más `return [{ json: armarCorreo($json, $('Webhook resumen').first().json.body.origen) }];`.
    5. `¿Hubo envíos?` — IF `={{ $json.enviar }}` igual a `true` (boolean).
    6. `Enviar correo a Luis` — `n8n-nodes-base.emailSend`, `fromEmail: remitente`, `toEmail: ={{ $json.aviso_a }}`, `subject: ={{ $json.asunto }}`, `emailFormat: html`, `html: ={{ $json.html }}`, credencial `smtp` con `smtp_credential`. Solo en la salida verdadera.
  - `resumen.patch_daily(daily: dict, rest_credential) -> dict` — copia del flujo diario con: el código de `Separar proyectos` agrega `desde: new Date(Date.now() - 60000).toISOString()` a cada ítem; nodo nuevo `Pedir resumen (una vez)` — HTTP POST a `https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-resumen` con `rest_credential`, `executeOnce: true`, cuerpo `={{ {desde: $('Separar proyectos').first().json.desde, origen: 'diario'} }}`, conectado después de `Reclamar (WP envía correo y dispara WA)`. Si el nodo ya existe, levanta `ValueError` (no duplica).
  - En `aplicar.py`, `workflows()` devuelve el diario ya parcheado (llamando `patch_daily` o con los mismos nodos), para que el repo describa lo que corre.
  - CLI de `resumen.py`: `--dry-run` imprime `{"workflows": [resumen, diario_parchado]}` con credenciales `CONFIGURAR_*` y remitente `CONFIGURAR_REMITENTE`, sin red ni archivos. `--apply` exige `--rest-credential`, `--webhook-credential`, `--smtp-credential`, `--remitente`, `--daily-id` y `--backup-dir` fuera del repo; lee `AT_N8N_URL` y `AT_N8N_API_KEY` del entorno (como `aplicar.py`, sin abrir archivos de claves); respalda el diario remoto en `--backup-dir` antes de tocarlo; crea «Reseña · Resumen» **inactivo** (falla si ya existe uno con ese nombre); aplica `patch_daily` al diario remoto con PUT (solo `name`, `nodes`, `connections`, `settings`); vuelve a leer el diario y falla si `versionId != activeVersionId`. Nunca activa el flujo nuevo. Nunca imprime respuestas de la API.

- [ ] **Step 1: Escribir la prueba que falla** `tests/plan-trabajo/resena-resumen-n8n-test.py` (`unittest`, sin red; cargar `resumen.py` con `importlib` como lo hace `resena-n8n-test.py`):
  - `test_flujo_resumen`: nombres y orden de nodos de arriba; webhook path `at-resena-resumen`, `responseMode` `onReceived`, `authentication` `headerAuth`; wait `15` `minutes`; `Pedir resumen` sin `onError: continueRegularOutput`; `active is False`; el correo solo cuelga de la salida 0 del IF.
  - `test_codigo_incluye_armar_correo`: el `jsCode` de `Armar correo` contiene `function armarCorreo` y no contiene `module.exports`.
  - `test_sin_datos_privados`: el JSON del dry-run del flujo «Reseña · Resumen» no contiene `@` ni `WA_EMISOR`.
  - `test_patch_daily`: `patch_daily(workflows()[0] sin parche)` agrega exactamente un nodo `Pedir resumen (una vez)` con `executeOnce` `True`, conectado desde `Reclamar (WP envía correo y dispara WA)`; aplicarlo dos veces levanta `ValueError`; `Separar proyectos` contiene `desde`.
  - `test_diario_sin_pendientes_no_llama` y `test_diario_tres_pendientes_una_llamada`: ejecutar con Node (vía `subprocess`) el `jsCode` de `Separar proyectos` con `$input.first().json = {pendientes: []}` → lista vacía (n8n no corre los nodos siguientes); con 3 pendientes → 3 ítems con el mismo `desde`; y comprobar `executeOnce: True` en `Pedir resumen (una vez)`.
  - `test_apply_sin_entorno`: `main(['--apply', …])` sin `AT_N8N_API_KEY` sale con error de argumentos y sin red (parchear `urllib.request.urlopen` para que falle si se llama).

- [ ] **Step 2: Correr y ver que falla**
  Run: `python -B tests/plan-trabajo/resena-resumen-n8n-test.py`
  Expected: error al cargar `resumen.py` (no existe).

- [ ] **Step 3: Implementar** `resumen.py` y el cambio en `aplicar.py`.

- [ ] **Step 4: Correr todo**
  Run: `python -B tests/plan-trabajo/resena-resumen-n8n-test.py` → `OK`.
  Run: `python -B tests/plan-trabajo/resena-n8n-test.py` → `OK` (el diario cambió: ajustar solo las aserciones que fijaban sus nodos, sin borrar comprobaciones).
  Run: `powershell -File tests/plan-trabajo/resena-suite.ps1` → `0 fallos`.

- [ ] **Step 5: Commit** `feat(resena): flujo n8n del resumen y llamada desde el diario`.

---

### Task 5: Guía y lista de despliegue

**Files:**
- Modify: `Docs/superpowers/plans/2026-11-19-resena-google-cierre.md` (sección nueva al final, «12. Resumen de envíos por correo (24-nov-2026)»)

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: una sección corta que explica qué hace el resumen, cuándo llega, qué significa cada símbolo, cómo se prueba, y la lista de despliegue de abajo con su orden.

- [ ] **Step 1: Escribir la sección** (español, en la voz de las secciones anteriores, sin correos ni nombres reales).
- [ ] **Step 2: Comprobar** `git diff --stat` (solo ese archivo) y que no haya `@` de personas reales: `git diff | grep -n "@" ` → solo `@ejemplo.cl` o ninguno.
- [ ] **Step 3: Commit** `docs(resena): guía del resumen de envíos`.

---

## Despliegue (no lo hacen los subagentes; requiere el OK de Luis)

Orden obligatorio, porque el webhook manual y el diario no deben llamar a un flujo que no existe:

1. **WordPress por SSH con llave** (`prod_ssh.py`), con respaldo de cada archivo en `~/respaldos/resena-resumen-antes-<fecha>/`, en este orden: `resena-puras.php` → `resena-datos.php` → `resena.php` → `avance-panel.php` (todos en `wp-content/themes/automatiza-tech/inc/plan-trabajo/`). Verificar desde afuera: `GET /wp-json/at/v1/resena/resumen?desde=…` sin cabecera → 401.
2. **n8n:** `resumen.py --apply` con las credenciales de los flujos actuales (la de entrada `X-AT-Secret` del webhook `at-resena-whatsapp`, la REST, y la SMTP de los avisos de AT), respaldo fuera del repo. Queda «Reseña · Resumen» inactivo y el diario parcheado y publicado (`versionId == activeVersionId`).
3. **Activar** «Reseña · Resumen» y probar: POST al webhook con `{desde: <hace 1 hora>, origen: "manual"}`. Si no hay envíos en esa hora, no llega correo (comportamiento correcto); la prueba real es el siguiente envío (los dos clientes pendientes, cuando Luis dé las fechas de término).
4. Recién después, push y PR con el OK de Luis.
