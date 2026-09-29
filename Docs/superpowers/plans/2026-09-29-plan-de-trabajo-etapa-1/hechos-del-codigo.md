# Hechos verificados del código (29-sep-2026) para redactar el plan de trabajo

Copia de solo lectura del código del cierre en PROD: `<SCR>\cierre-ro` (= rama `origin/claude/cierre-cliente`),
donde `<SCR>` = `C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\be929449-8523-4c30-a49a-56a73bb785ab\scratchpad`.
Tema: `wp-content/themes/automatiza-tech` (`<T>`). Para leer n8n de PROD: `git -C C:\wamp64\www\automatiza-tech show origin/claude/propuestas-json-robusto:N8N/propuestas-v3/<archivo>`.
Para leer el renderer vigente: `git -C C:\wamp64\www\automatiza-tech show origin/main:renderer/<archivo>`.
Verifica cualquier detalle leyendo el archivo: estos hechos son un resumen.

## WordPress

1. **Carga:** `<T>/inc/admin-proposals.php:91` `require_once __DIR__ . '/cierre-cliente/cargar.php';` fuera de
   `is_admin()`; `admin-proposals.php` se carga siempre desde `functions.php:1335`. Por eso el módulo del cierre corre
   en front, REST, admin-post, admin y en `contracts/sign-contract.php` (que hace `wp-load.php`).
2. **Migraciones:** el cierre usa ALTER con opción `at_cierre_schema === '1'` y transitorio de 5 min si falla,
   enganchado en `admin_init` y llamado perezosamente. `admin-followup-meetings.php` usa `dbDelta`.
3. **Ajustes del cierre** (`<T>/inc/cierre-cliente/ajustes.php`): `register_setting` en `admin_init`,
   `add_submenu_page('automatiza-proposals', …, 'manage_options', 'at-cc-ajustes', …)` con prioridad 20; render
   con `settings_fields()` y `options.php`. `at_cc_whatsapp_at()` devuelve la opción o `'56927002984'`.
4. **Contratos** (`contracts/contract-service.php`, en la raíz del sitio, no en el tema): tabla
   `$wpdb->prefix . 'automatiza_contracts'`; `sign_as_client($token, $data)` termina así (líneas 661-665):
   ```php
           $fresh = self::get_by_id($c->id);
           ContractMailer::send_signed_copy($fresh, $signed_path);
           ContractMailer::send_signed_copy_internal($fresh, $signed_path);
           return $fresh;
   ```
   Punto del hook: entre la línea 664 y 665. Único llamador: `contracts/sign-contract.php:48`.
   Columnas relevantes: `id`, `client_id` (= id de la ficha operativa `automatiza_tech_clients`, NO del CRM),
   `proposal_id` (= `automatiza_propuestas.id`, puede ser NULL), `type` ENUM('soporte','servicios',…), `status`
   ENUM('draft','at_pending','at_signed','sent','viewed','signed','expired','cancelled'), `placeholders` LONGTEXT
   (JSON; se lee con `json_decode($c->placeholders, true)`), `signed_at`, `contract_number`.
   Placeholders útiles en contratos de servicios: `servicios_contratados`, `alcance`, `entregables`, `plazo`,
   `fases_siguientes`, `nombre_proyecto`, `razon_social_cliente`, `representante_cliente_nombre`, `email_cliente`,
   `telefono_cliente`, `fecha_firma_larga`, `fecha_firma_cliente`.
   `ContractService::get_by_id($id)` público estático.
5. **Ficha única:** `automatiza_tech_clients.crm_cliente_id` enlaza con `wp_crm_clientes.id`.
   Ayudas del cierre: `at_cc_tech_de_crm(int $crm_id): ?object`, `at_cc_techs_de_crm(int $crm_id): array`,
   `at_cc_crm_de_email(string $email): int`.
6. **Feriados:** no hay lista de feriados en el código. Viven en `get_option('automatiza_chat_schedule')['holidays']`,
   texto con una fecha `YYYY-MM-DD` por línea (`<T>/inc/chat-widget.php:154-161`). Se parsean con
   `preg_split('/\r\n|\r|\n/', …)` + `preg_match('/^\d{4}-\d{2}-\d{2}$/', …)` (`wp-content/mu-plugins/api-appointments-config.php:12-42`).
7. **Ficha del CRM** (`wp-content/mu-plugins/crm-ai-completo.php`, clase `AutomatizaTech_CRM_AI`, SIN hooks: hay que
   editar el archivo):
   - botones de pestañas (líneas ~1978-1986):
     ```php
                     <div class="ficha-tabs">
                         <button class="ficha-tab active" data-target="tab-identidad">🎨 Identidad</button>
                         <?php if (!$is_designer_only): ?>
                         <button class="ficha-tab" data-target="tab-general">📋 General</button>
                         <button class="ficha-tab" data-target="tab-proyectos">🚀 Proyectos <span class="ficha-tab-badge"><?php echo count($proyectos); ?></span></button>
                         <button class="ficha-tab" data-target="tab-operacion">📜 Contratos y operación</button>
                         <?php endif; ?>
                     </div>
     ```
   - el panel «Contratos y operación» termina en `</div><!-- /tab-operacion -->` seguido de `<?php endif; ?>` (cierra
     el `if (!$is_designer_only)`). Un panel nuevo va entre ese comentario y el `endif`, con la forma
     `<div class="ficha-tab-content" id="tab-plan">…</div>`. El JS de pestañas es genérico (`.ficha-tab[data-target]`).
   - el cliente es `$cliente` (ARRAY_A de `wp_crm_clientes`), id `$cliente['id']`. URL de la ficha:
     `admin.php?page=automatiza-crm-ficha&id=N`.
   - portal del cliente: `at_crm_url_portal(int $cliente_id): string` →
     `home_url('/?crm_view=timeline&cid=…&token=…')` ('' si no hay correo). El portal muestra «Tus Contratos»,
     «Tus Proyectos» (fecha de inicio, entrega y estado) e «Historial» (reuniones, seguimientos, notas, pagos).
8. **Reuniones de seguimiento** (`<T>/inc/admin-followup-meetings.php`): página `admin.php?page=automatiza-followup`
   (padre `automatiza-reminders`, `manage_options`). **No hay precarga por GET**: solo lee `edit_id`, `filter_status`,
   `filter_date`; los valores del formulario salen de `$edit_meeting->campo ?? ''`. Campos del formulario:
   `client_name`, `client_email`, `invitees_emails`, `company_name`, `phone`, `meeting_date`, `meeting_time`,
   `meet_link`, `meeting_subject` (defecto 'Reunión de Seguimiento - AutomatizaTech'), `notes`, casillas
   `create_calendar_event`, `send_email`, `send_whatsapp`. Nonce `save_followup_meeting` / `followup_nonce`. La tabla
   `wp_automatiza_followup_meetings` no tiene columna de CRM ni de propuesta (enlaza por correo).
   Hay una REST `automatiza_tech_create_followup_meeting_api` (línea ~2452) por si sirve.
9. **REST existente:** `automatiza_proposals_rest_auth(WP_REST_Request $r)` en `<T>/inc/rest-proposals.php:27-54`
   (500 si falta `AT_REST_SECRET`; 401 sin cabecera `x_at_secret`; 403 si no calza; `hash_equals`). Rutas en
   `automatiza-tech/v1` registradas en `rest_api_init`.
10. **WP → n8n:** `at_v3_llamar_n8n(string $url, int $id): string` en `<T>/inc/admin-proposals.php:16-38`
    (`wp_remote_post`, timeout 15, cabeceras `Content-Type: application/json` y `X-AT-Secret: AT_REST_SECRET`,
    body `{"id": N}`; devuelve '' si 2xx). Constantes `AT_N8N_V3_CAMBIOS`/`AT_N8N_V3_FINAL` definidas con
    `if (!defined(...)) define(...)`.
11. **Costo de fotos de la propuesta** (`<T>/inc/proposals-flow.php:114-128`):
    ```php
    function at_propuesta_costo_fotos(array $payload): array {
    	$n = 0;
    	foreach ($payload['image_briefs'] ?? [] as $b) {
    		if (!empty($b['slide']) && !empty($b['prompt'])) { $n++; }
    	}
    	$revision = $n > 0 ? 0.026 : 0.0;
    	return ['fotos' => $n, 'usd_lista' => round($n * 0.0032 + $revision, 4), 'usd_revision' => $revision,
    	        'usd_max' => round($n * 0.0032 * 2 + $revision * 2, 4)];
    }
    ```
    (El plan no hace revisión de texto con GPT-4o en la Etapa 1: su costo es solo fotos.)
12. **Pruebas del cierre** (`tests/cierre/` en la raíz del repo): puras con `require` directo de `puras.php` y funciones
    `ok($cond,$msg)` + salida `TODO OK`/`N FALLAS`; WP con `tests/cierre/wp-bootstrap.php` (exige `AT_WP_LOAD`; define
    `ok()` y `fin()`); captura de correos con `add_filter('pre_wp_mail', …)`; peticiones REST con
    `new WP_REST_Request('POST', '/…')` + `set_header('x-at-secret', AT_REST_SECRET)` + `rest_do_request()`.
    Para el plan: `tests/plan/wp-bootstrap.php` (copia adaptada que exige `at_pt_crear_plan`) y datos de prueba
    marcados `[PRUEBA]`, borrados al final. Llamadas HTTP salientes: interceptar con `pre_http_request`.

## n8n (lo que corre en PROD = `origin/claude/propuestas-json-robusto:N8N/propuestas-v3/`)

- Constantes de `build_1_borrador.py`:
  ```python
  CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
  CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
  CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
  CRED_RENDER = {'httpHeaderAuth': {'id': 'fj2orzbsjlnHaiLd', 'name': 'X-AT-Render-Key'}}
  WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
  RENDERER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/render'
  LUIS = 'lmgm.0303@gmail.com'
  ```
- Nodos: webhook v2 (`authentication:'headerAuth'`, `responseMode:'onReceived'`, `webhookId` = path); OpenAI
  `n8n-nodes-base.openAi` v1 `resource:'chat'`, `model:'gpt-4o'`; Code v2 (`jsCode`); httpRequest v4.2
  (`authentication:'genericCredentialType'`, `genericAuthType:'httpHeaderAuth'`, `sendBody`, `specifyBody:'json'`,
  `jsonBody:'={{ JSON.stringify(...) }}'`); emailSend v1 (`fromEmail:'contacto@automatizatech.cl'`).
  `build_3_final.py` tiene helpers `http(id_, name, pos, method, url, body_expr=None, cred=True, timeout=None)` que
  fijan `options.response.response = {fullResponse: True, neverError: True}` y `onError='continueRegularOutput'`,
  `iff(...)` (If v2.2) y `link(a, b, output=0)`; bucle de reintentos con `MAX_RENDERS = 3`.
- `json_guard.JS_LEER_JSON` define `CLAVES_PROPUESTA`, `esObjetoPlano(v)` y `leerJsonModelo(raw)` (quita cercos
  ```json; única tolerancia: `}` de más al final). `fotos_guard.JS_LIMPIAR_FOTOS` define `limpiarFotos(briefs)` →
  `{limpias, reemplazadas, neutralizadas}`; slides sin entrada en `SEGURAS` usan `SEGURAS.extra`; cada prompt limpio
  termina en `'no signs, no labels, no text, no lettering, no logos, no watermarks, no subtitles, no captions'`.
- Correos: `email_tpl.py` (`etiqueta`, `boton(url, texto, primario=True)`, `parrafo`, `caja(html, tono)`, `nota`,
  `marco`, `JS_ESC`, `js_correo(prep, asunto_expr, titulo, etiqueta_html, cuerpo)` → devuelve JS que produce
  `{asunto, html}`); regla: nunca enlazar a `*.easypanel.host` en correos.
- `deploy.py`: `python N8N/propuestas-v3/deploy.py <archivo.json> [--sin-activar]`; identifica por **nombre**; crea si
  no existe; activa; **no respalda** (el respaldo lo hace quien despliega con `GET /workflows/{id}` antes).
- Workflow de avisos de error: `settings: {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}`.
- Pruebas del JS embebido: `probar_json.py` corre `node -e 'const DATOS = …;\n' + codigo` y compara.

## Renderer (`origin/main:renderer/`)

- `POST /render` (`src/server.js`): clave `X-AT-Render-Key` (si `RENDER_KEY` vacío, abierto); valida con
  `validatePayload`; `unique_id` debe calzar `/^[A-Za-z0-9_-]{6,64}$/`; `image_briefs` [{slide,prompt}]; `images`
  {slide: url} gana sobre el manifiesto y evita generar esa foto; genera con Higgsfield las pendientes;
  `persistImages` descarga URLs http(s) a `img/<slide>.<ext>`; escribe `img/manifest.json` {slide:{file,hash}};
  `html = renderProposalHtml(data, images)`; `renderToFiles(html, outputDir)` (1920×1080, PDF); responde
  `{view_url, pdf_url, images:{requested, stored_local, kept_remote, missing, reused}}`. `server.js` no lee `draft`.
- `src/template.js`: `STYLE` (CSS, L176-360), `SCRIPT` (IIFE de navegación con aviso `at-deck-lamina`), `LOGO_URL`,
  `CONTACTS`, `backgroundStyle`, `logoMark`, `renderDeckControls`, … **no exportados**; exporta
  `renderProposalHtml, renderCoverSlide, renderContentSlide, renderClosingSlide, renderParagraphBody,
  renderBulletListBody, renderPricingBody`. Borrador: `<body class="is-draft">` y aviso «Borrador · vista previa sin
  fotos».
- Pruebas con `node:test` y `require('../src/…')`; `server-images.test.js` simula `global.fetch`;
  `createApp({ publicDir, baseUrl, higgsfieldCredentials, renderKey })`.
- Despliegue: `git archive --format=zip -o propuesta-renderer-<commit>.zip <commit>:renderer`, Luis lo sube a
  Easypanel (Source → Upload).
