# ARGOS Mecánico Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a second n8n workflow ("Argos Mecánico") that, when ARGOS logs a new n8n error, tries to repair it automatically (n8n config only) up to 3 times, and emails Luis the result — solved or needs his help — with a full attempt history when it can't.

**Architecture:** All-in-n8n, no Claude Code cloud routines. A Schedule Trigger (every 3 min, native n8n — no 1h minimum like cloud routines) polls a new WordPress REST endpoint for pending errors. When one exists, an AI Agent node (Claude `claude-sonnet-5` via existing credential "AT Anthropic API", OpenAI fallback) reads and patches the failing workflow through n8n's own REST API. Two new REST endpoints on the existing `automatiza_n8n_errors` table track attempts; two branches send styled HTML emails matching ARGOS's existing template.

**Tech Stack:** PHP 8+ / WordPress REST API / `$wpdb` (existing `automatiza-tech` WP theme backend), n8n (Schedule Trigger, AI Agent, HTTP Request Tool, Switch, Email Send nodes), Anthropic API (`claude-sonnet-5`) with OpenAI GPT fallback.

## Global Constraints

- The repair agent may only change **n8n workflow configuration** (node parameters, connections, `errorWorkflow` setting) — never PHP code, never a deploy. If root cause looks external (expired credential, third-party outage), it must escalate immediately without attempting a fix.
- Maximum 3 fix attempts per error row. On the 3rd failed attempt (or an immediate-escalation case), status becomes `requiere_intervencion` and auto-retry stops.
- All `automatiza_n8n_errors` schema changes are **additive only** — no renamed or dropped columns.
- Result emails reuse ARGOS's exact visual template (dark header, severity band, info cards, dark footer), go to the same recipients (`lgonzalez@automatizatech.cl`, bcc `automatizacionesbotcore@gmail.com`), and are sent as a **separate follow-up** to ARGOS's original alert — never replacing it.
- The escalation email's attempt report is markdown-style content rendered **inside the email body** — never a file attachment.
- Every PHP change follows the repo's existing pattern: standalone `test-*.php` verification scripts at the repo root (see `test-n8n-connection.php`), not a PHPUnit suite — this repo has none for this module.

---

## File Structure

- Modify: `setup-n8n-errors-db.php:26-52` — add 4 columns to the `CREATE TABLE` SQL (dbDelta applies them as `ALTER TABLE` on re-run since the table already exists).
- Modify: `wp-content/themes/automatiza-tech/inc/admin-n8n-errors.php` — 2 new REST routes (`GET .../n8n-errors/pending-fix`, `POST .../n8n-errors/fix-attempt`) + 2 new PHP functions + a small dashboard display tweak.
- Create: `test-argos-mecanico-db.php` — standalone verification script (repo convention), root directory.
- Create (n8n, tracked as JSON in repo for version history, matching `N8N/PROD/Argos detección de Errores N8N.json`): `N8N/PROD/Argos Mecanico.json`.

---

### Task 1: Backend — columnas nuevas + endpoints REST para el Mecánico

**Files:**
- Modify: `setup-n8n-errors-db.php:26-52`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-n8n-errors.php`
- Create: `test-argos-mecanico-db.php`

**Interfaces:**
- Produces: `GET /wp-json/automatiza/v1/n8n-errors/pending-fix` → `[{id, workflow_name, workflow_id, error_node, error_message, error_stack, severity, fix_attempts, fix_history, created_at}, ...]` (max 10, oldest first).
- Produces: `POST /wp-json/automatiza/v1/n8n-errors/fix-attempt` body `{error_id, resultado: "resuelto"|"fallido", diagnostico, accion, requiere_intervencion_inmediata?: bool}` → `{success, error_id, fix_status, fix_attempts, historial}`.
- Both require the same `X-API-Key` header ARGOS already uses (`automatiza_verify_n8n_api_key`) — this is a header-only check, it does NOT require a logged-in WordPress admin session/cookie.

- [x] **Step 1: Add the 4 new columns to the table SQL**

Edit `setup-n8n-errors-db.php`, inside the `$sql` string (currently lines 26-52). Insert these 4 lines right after `notification_email tinyint(1) DEFAULT 0,` and before `created_at datetime DEFAULT CURRENT_TIMESTAMP,`:

```sql
    fix_attempts tinyint(2) unsigned DEFAULT 0,
    fix_status enum('pendiente','resuelto','requiere_intervencion') DEFAULT 'pendiente',
    fix_history longtext DEFAULT NULL,
    last_fix_attempt_at datetime DEFAULT NULL,
```

- [x] **Step 2: Run the migration**

Visit `http://localhost/automatiza-tech/setup-n8n-errors-db.php` (or the WAMP equivalent) while logged in as an admin. Confirm the page shows the 4 new columns in "Estructura de la Tabla".

- [x] **Step 3: Add the two new PHP functions to `admin-n8n-errors.php`**

(full code — see repo history commit 2e07bb2 for the exact functions `automatiza_get_n8n_errors_pending_fix` and `automatiza_report_fix_attempt`)

- [x] **Step 4: Register the two new routes**

(see commit 2e07bb2)

- [x] **Step 5: Show fix status in the admin dashboard modal**

(see commit 2e07bb2)

- [x] **Step 6: Write the verification script**

Create `test-argos-mecanico-db.php` following the exact pattern of `test-n8n-connection.php`:
```php
<?php
require_once __DIR__ . '/at-maintenance-guard.php';
require_once 'wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar este script.');
}
```
followed by the column-check + insert/verify/update/cleanup cycle (see commit 2e07bb2 for the full body).

- [ ] **Step 7: Run the verification script**

Visit `http://localhost/automatiza-tech/test-argos-mecanico-db.php` logged in as admin. Expected: every line says `OK`, none say `FALLA` or `FALTA`.

- [ ] **Step 8: Manually test both new REST endpoints with curl**

**Important:** these two REST endpoints do NOT need a logged-in WordPress admin session/cookie — their `permission_callback` is `automatiza_verify_n8n_api_key`, which only checks the `X-API-Key` header. A plain `curl` call with that header is sufficient; no browser login, no `current_user_can()` involved for these two routes (that check only gates the admin dashboard page and the `test-argos-mecanico-db.php` script, a separate code path).

```bash
curl -H "X-API-Key: <la key real de get_option('automatiza_n8n_api_key')>" \
  "https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors/pending-fix"
```
Expected: `200` with a JSON array (empty `[]` is fine if there are no pending errors right now).

```bash
curl -X POST -H "X-API-Key: <la key>" -H "Content-Type: application/json" \
  -d '{"error_id": 1, "resultado": "fallido", "diagnostico": "prueba manual", "accion": "ninguna"}' \
  "https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors/fix-attempt"
```
Expected: `200` with `{"success":true, "fix_status":"pendiente", "fix_attempts":1, ...}` for an existing `error_id` (use a real id from the dashboard, then reset it manually or ignore — it's a real row, so pick one you don't mind bumping, or use the temporary row `test-argos-mecanico-db.php` creates by removing its cleanup line temporarily).

For local testing (the local WAMP site is `http://localhost/automatiza-tech`, not the production `automatizatech.cl` domain), substitute the local base URL. This branch's endpoint code only exists in this worktree — if testing against the locally-served site, temporarily copy the 2 modified/created files into the actually-served checkout, test, then restore that checkout to its prior state exactly (`git checkout -- <files>` there) so no other in-progress work in that checkout is disturbed.

- [ ] **Step 9: Commit**

```bash
git add setup-n8n-errors-db.php wp-content/themes/automatiza-tech/inc/admin-n8n-errors.php test-argos-mecanico-db.php
git commit -m "feat(argos-mecanico): columnas fix_* y endpoints pending-fix/fix-attempt

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Workflow n8n "Argos Mecánico" — Schedule Trigger + chequeo barato

**Files:** n8n only (no repo files touched in this task — exported to `N8N/PROD/Argos Mecanico.json` at the end of Task 4).

**Interfaces:**
- Consumes: `GET /wp-json/automatiza/v1/n8n-errors/pending-fix` (Task 1).
- Produces: an n8n workflow id — write it down, it's referenced by name (`Argos Mecánico`) in Task 3-5 via `n8n_get_workflow`/`n8n_update_partial_workflow`.

- [ ] **Step 1: Create the workflow with the schedule + cheap check**

Use `n8n_create_workflow` (n8n-mcp tool) with:
- `name`: `"Argos Mecánico"`
- `nodes`:
  - `Schedule Trigger` (`n8n-nodes-base.scheduleTrigger`, `typeVersion: 1.3`), cron `rule.interval` = `[{field: "cronExpression", expression: "0 */3 * * * *"}]`.
  - `Buscar Pendientes` (`n8n-nodes-base.httpRequest`, `typeVersion: 4.2`): `GET https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors/pending-fix`, header `X-API-Key` = the same value already used by ARGOS's own `Buscar Historial BD` node (`argos-automatiza-2024-secret` unless it was rotated in Task 1 review — check `get_option('automatiza_n8n_api_key')` first), `continueOnFail: true`.
  - `Normalizar Pendientes` (`n8n-nodes-base.code`, `typeVersion: 2`, placed between `Buscar Pendientes` and the AI Agent in Task 3): `jsCode`:
    ```javascript
    const items = $input.first().json;
    if (!Array.isArray(items) || items.length === 0) return [];
    return items.map(e => ({ json: e }));
    ```
    (Returning `[]` means zero items flow onward — n8n stops that branch naturally, no IF node needed at all. Zero output items = the run ends here with no cost.)

- [ ] **Step 2: Verify the structure**

```
n8n_get_workflow({id: "<the new id>", mode: "structure"})
```
Expected: 3 nodes (`Schedule Trigger` → `Buscar Pendientes` → `Normalizar Pendientes`), 2 connections.

- [ ] **Step 3: Test it manually**

```
n8n_update_partial_workflow({id: "<id>", operations: [{type: "activateWorkflow"}]})
```
Trigger a manual test run (n8n UI "Test workflow" button — Schedule Trigger doesn't support external test triggering via the MCP tool, so open the workflow in the n8n editor and click "Test workflow" once).
Expected: with no pending errors right now, the run finishes with 0 items after `Normalizar Pendientes` — confirms the cheap-check path costs nothing extra.

- [ ] **Step 4: Deactivate until Task 4 is done**

```
n8n_update_partial_workflow({id: "<id>", operations: [{type: "deactivateWorkflow"}]})
```

---

### Task 3: Nodo AI Agent — herramientas de leer/modificar workflow + system prompt

**Files:** n8n only, continuing on the `Argos Mecánico` workflow from Task 2.

**Interfaces:**
- Consumes: one item per pending error from `Normalizar Pendientes` (Task 2), shape `{id, workflow_name, workflow_id, error_node, error_message, error_stack, severity, fix_attempts, fix_history, created_at}`.
- Produces: agent output text containing a JSON block `{"decision": "resuelto"|"fallido"|"requiere_intervencion", "diagnostico": "...", "accion": "..."}` — consumed by Task 4's `Parsear Resultado` node.

**Prerequisite (manual, one-time, do this in the n8n UI before Step 1):** create an n8n credential of type **Header Auth** named `AT n8n Self API`, header name `X-N8N-API-KEY`, value = the same n8n API key already configured for the n8n-mcp connector (Luis has it in his credentials vault, not the repo — ask him for it or find where the running n8n-mcp server reads `N8N_API_KEY` from). This lets the AI Agent's tools call n8n's own REST API to read/patch the failing workflow.

- [ ] **Step 1: Add the language model nodes**

Add to the `Argos Mecánico` workflow via `n8n_update_partial_workflow` with `addNode` operations:
- `Claude Mecanico` (`@n8n/n8n-nodes-langchain.lmChatAnthropic`, `typeVersion: 1.3`): `model.value` = `"claude-sonnet-5"`, credentials `{anthropicApi: {id: "<AT Anthropic API credential id — same one AT_Reel_Diario_Checkpoints_PlanB uses>"}}`.
- `OpenAI Fallback Mecanico` (`@n8n/n8n-nodes-langchain.lmChatOpenAi`, `typeVersion: 1.3`): `model.value` = `"gpt-4.1-mini"`, credentials `{openAiApi: {id: "g52IEXpRfN5r7jKw", name: "OpenAi account"}}` (same credential ARGOS itself already uses).

- [ ] **Step 2: Add the two HTTP Request Tool nodes**

- `Leer Workflow` (`n8n-nodes-base.httpRequestTool`, `typeVersion: 4.2`):
  - `toolDescription`: `"Lee la configuración completa (nodos, conexiones, settings) del workflow de n8n que falló. Úsalo primero, siempre, antes de proponer cualquier cambio."`
  - `method`: `GET`
  - `url`: `=https://n8n-n8n.kchiba.easypanel.host/api/v1/workflows/{{ $fromAI('workflow_id', 'ID del workflow a leer', 'string') }}`
  - `authentication`: `predefinedCredentialType`, `nodeCredentialType`: `httpHeaderAuth`
  - `credentials`: `{httpHeaderAuth: {name: "AT n8n Self API"}}` (resolve the id after creating the credential in the prerequisite step)

- `Modificar Workflow` (`n8n-nodes-base.httpRequestTool`, `typeVersion: 4.2`):
  - `toolDescription`: `"Aplica un cambio a la configuración del workflow que falló. SOLO para arreglos de configuración de n8n (parámetros de nodo, URLs, expresiones, credenciales mal referenciadas, Error Workflow faltante, nodos desconectados). Envía el objeto 'nodes' completo con el nodo corregido."`
  - `method`: `PATCH`
  - `url`: `=https://n8n-n8n.kchiba.easypanel.host/api/v1/workflows/{{ $fromAI('workflow_id', 'ID del workflow a modificar', 'string') }}`
  - `authentication`: `predefinedCredentialType`, `nodeCredentialType`: `httpHeaderAuth`
  - `credentials`: `{httpHeaderAuth: {name: "AT n8n Self API"}}`
  - `sendBody`: `true`, `specifyBody`: `json`, `jsonBody`: `={{ $fromAI('body_json', 'JSON completo del PATCH: {\"nodes\": [...], \"connections\": {...}}', 'string') }}`

- [ ] **Step 3: Add the AI Agent node with the system prompt**

`Agente Reparador` (`@n8n/n8n-nodes-langchain.agent`, `typeVersion: 3`):

```
promptType: define
text: =Repara este error de n8n si puedes, o indica que requiere intervención humana.

**Workflow:** {{ $json.workflow_name }} (id: {{ $json.workflow_id }})
**Nodo:** {{ $json.error_node }}
**Error:** {{ $json.error_message }}
**Stack:** {{ $json.error_stack }}
**Intento número:** {{ $json.fix_attempts + 1 }} de 3
**Historial de intentos previos:** {{ $json.fix_history || 'Ninguno, primer intento.' }}
```

`options.systemMessage`:

```
# 🔧 MECÁNICO - Reparador automático de errores n8n

Trabajas para AutomatizaTech junto a ARGOS (que detecta errores y te los pasa).
Tu trabajo: diagnosticar y, si puedes, reparar errores de workflows n8n.

## LO ÚNICO QUE PUEDES TOCAR
Configuración del workflow que falló, y SOLO eso:
- Parámetros de nodo (URLs mal puestas, valores incorrectos)
- Expresiones rotas ({{ }} con referencias a nodos que ya no existen o mal escritas)
- Credenciales mal referenciadas dentro del propio workflow
- Nodos desconectados que deberían estar conectados
- El campo Error Workflow si falta

## LO QUE NUNCA DEBES HACER
- Tocar código PHP, WordPress, o cualquier archivo fuera de n8n
- Hacer un deploy o publicar nada a producción
- Inventar credenciales nuevas o cambiar a qué cuenta/API apunta un nodo
- Intentar "arreglar" algo borrando nodos o conexiones sin estar seguro

## CUÁNDO ESCALAR EN VEZ DE INTENTAR (usa decision: "requiere_intervencion" directo)
- El error es de una credencial vencida o inválida (401/403 de un servicio externo)
- El error es de un servicio externo caído (timeout, 500 de una API que no es tuya)
- El error requiere una decisión de negocio (qué valor poner, qué lógica cambiar)
- Ya es el intento número 3 y sigue sin arreglarse

## CÓMO TRABAJAR
1. SIEMPRE usa la herramienta "Leer Workflow" primero. Nunca propongas un cambio sin haber leído la configuración real.
2. Diagnostica la causa exacta antes de tocar nada.
3. Si el diagnóstico cae en "cuándo escalar" (arriba), NO uses "Modificar Workflow" — responde directo con decision "requiere_intervencion".
4. Si es reparable, usa "Modificar Workflow" con el cambio mínimo necesario — no reescribas nodos que no tienen relación con el error.
5. Responde SIEMPRE con este JSON al final de tu mensaje (nada más después):

{"decision": "resuelto" | "fallido" | "requiere_intervencion", "diagnostico": "causa raíz en 1-2 frases", "accion": "qué hiciste o qué encontraste, en 1-3 frases, lenguaje simple para Luis"}

"resuelto" = usaste Modificar Workflow y quedó arreglado.
"fallido" = lo intentaste pero no funcionó o no encontraste la causa (cuenta como intento).
"requiere_intervencion" = no lo intentaste porque cae fuera de tu alcance (escalamiento inmediato).
```

- [ ] **Step 4: Wire the connections**

- `Claude Mecanico` → `Agente Reparador` (`ai_languageModel`, index 0)
- `OpenAI Fallback Mecanico` → `Agente Reparador` (`ai_languageModel`, index 1 — fallback slot)
- `Leer Workflow` → `Agente Reparador` (`ai_tool`)
- `Modificar Workflow` → `Agente Reparador` (`ai_tool`)
- `Normalizar Pendientes` → `Agente Reparador` (`main`)

Use `n8n_update_partial_workflow` with `addConnection` operations, `sourceOutput` set per above.

- [ ] **Step 5: Validate**

```
n8n_validate_workflow({id: "<id>"})
```
Expected: no errors about missing `ai_languageModel` or `ai_tool` connections.

---

### Task 4: Ramas de resultado — actualizar BD y enviar correo (éxito / escalamiento)

**Files:** n8n only, continuing on `Argos Mecánico`. At the end of this task, export the finished workflow to `N8N/PROD/Argos Mecanico.json` in the repo.

**Interfaces:**
- Consumes: `Agente Reparador` output text (Task 3), must contain the JSON block `{"decision", "diagnostico", "accion"}`.
- Consumes: the exact HTML-building pattern already validated live in the "TEST - Mecanico Email Preview" workflow earlier in this project (dark header + severity band + info cards + dark footer — see the design spec for the full reference markup).
- Produces: `POST /wp-json/automatiza/v1/n8n-errors/fix-attempt` calls (Task 1) and 2 possible outbound emails.

- [ ] **Step 1: Parse the agent's JSON decision**

`Parsear Decision` (`n8n-nodes-base.code`, `typeVersion: 2`):
```javascript
const raw = $json.output || '';
const match = raw.match(/\{[\s\S]*\}\s*$/);
let parsed = null;
try { parsed = match ? JSON.parse(match[0]) : null; } catch (e) {}
if (!parsed || !['resuelto', 'fallido', 'requiere_intervencion'].includes(parsed.decision)) {
  parsed = { decision: 'fallido', diagnostico: 'El agente no devolvió un JSON válido.', accion: 'Sin acción — respuesta inválida del modelo.' };
}
const source = $('Normalizar Pendientes').item.json;
return [{ json: { ...source, decision: parsed.decision, diagnostico: parsed.diagnostico, accion: parsed.accion } }];
```

- [ ] **Step 2: Report the attempt to WordPress**

`Reportar Intento` (`n8n-nodes-base.httpRequest`, `typeVersion: 4.2`):
- `POST https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors/fix-attempt`
- header `X-API-Key` = same as `Buscar Pendientes`
- body (json): `={{ JSON.stringify({ error_id: $json.id, resultado: $json.decision === 'resuelto' ? 'resuelto' : 'fallido', diagnostico: $json.diagnostico, accion: $json.accion, requiere_intervencion_inmediata: $json.decision === 'requiere_intervencion' }) }}`

- [ ] **Step 3: Branch on the API's returned `fix_status`**

`Se Resolvio?` (`n8n-nodes-base.if`, `typeVersion: 2.3`): condition `{{ $json.fix_status }}` equals `resuelto`.
- true branch → Step 4 (success email)
- false branch → `Requiere Intervencion?` (`n8n-nodes-base.if`): condition `{{ $json.fix_status }}` equals `requiere_intervencion` → true branch = Step 5 (escalation email), false branch = end (still under 3 attempts, wait for next 3-min cycle — no email yet, matches the spec: only email on final outcome).

- [ ] **Step 4: Build and send the success email**

`Construir Correo Exito` (`n8n-nodes-base.code`, `typeVersion: 2`) — reuse the exact HTML structure validated in the design spec's test email (green banner, "✅ Solucionado automáticamente", workflow/node info cards, "🔧 Qué se hizo" section using `$json.accion`), building `html` and `subject` fields the same way the `TEST - Mecanico Email Preview` workflow's `Build Correo Exito` node did — swap the hardcoded example text for `{{ $('Parsear Decision').item.json.workflow_name }}`, `.error_node`, `.accion`.

`Enviar Correo Exito` (`n8n-nodes-base.emailSend`, `typeVersion: 1`): same `SMTP account PROD` credential (id `dyhVFWmjRNC45ccA`), `toEmail`: `lgonzalez@automatizatech.cl`, `bccEmail`: `automatizacionesbotcore@gmail.com`, `subject`: `={{ $json.subject }}`, `html`: `={{ $json.html }}`.

- [ ] **Step 5: Build and send the escalation email**

`Construir Informe` (`n8n-nodes-base.code`, `typeVersion: 2`): parse `fix_history` (returned by `Reportar Intento`'s response, field `historial`) into the same markdown-style `<ul><li>` block used in the validated test escalation email — one `<p><strong>Intento N · fecha</strong></p><ul>...</ul>` block per historial entry, plus a closing "Recomendación" paragraph built from the last entry's `diagnostico`/`accion`.

`Construir Correo Escalacion` (`n8n-nodes-base.code`, `typeVersion: 2`): same amber-banner HTML structure as the validated test email, embedding `Construir Informe`'s output inside the "📋 Informe de intentos" box.

`Enviar Correo Escalacion` (`n8n-nodes-base.emailSend`, `typeVersion: 1`): same credential/recipients as Step 4.

- [ ] **Step 6: Wire it all together**

`Agente Reparador` → `Parsear Decision` → `Reportar Intento` → `Se Resolvio?` → (`Construir Correo Exito` → `Enviar Correo Exito`) / (`Requiere Intervencion?` → `Construir Informe` → `Construir Correo Escalacion` → `Enviar Correo Escalacion`).

- [ ] **Step 7: Validate and activate**

```
n8n_validate_workflow({id: "<id>"})
n8n_update_partial_workflow({id: "<id>", operations: [{type: "activateWorkflow"}]})
```

- [ ] **Step 8: Export to the repo for version tracking**

```
n8n_get_workflow({id: "<id>", mode: "full"})
```
Save the returned JSON as `N8N/PROD/Argos Mecanico.json` (matches the existing `N8N/PROD/Argos detección de Errores N8N.json` convention). Commit:

```bash
git add "N8N/PROD/Argos Mecanico.json"
git commit -m "feat(argos-mecanico): workflow n8n completo (schedule + agente reparador + correos)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Prueba end-to-end con un error real simulado

**Files:** none permanent — uses a disposable test workflow, deleted after (same pattern as the email-preview test earlier in this project).

**Interfaces:** exercises the full chain: real n8n error → ARGOS → `automatiza_n8n_errors` → Mecánico → repair attempt → email.

- [ ] **Step 1: Create a disposable workflow with a deliberately broken, easily fixable config**

Via `n8n_create_workflow`: name `"TEST - Mecanico E2E (borrar)"`, one Manual Trigger → one HTTP Request node with an intentionally broken parameter (e.g. a `url` pointing at an invalid host, or a JS expression like `{{ $json.doesNotExist.value }}` in a Set node) → `settings.errorWorkflow` = ARGOS's id (`p7ISUf0J5GycHscc`). Activate it.

- [ ] **Step 2: Trigger it and let it fail**

```
n8n_test_workflow({workflowId: "<test id>", triggerType: ...})
```
(or trigger manually in the n8n UI if it's a Manual Trigger — Manual Trigger can't be triggered externally, so open it in the editor and click "Test workflow" once).
Confirm via `n8n_executions({action: "list", workflowId: "<test id>"})` that it shows `status: "error"`.

- [ ] **Step 3: Confirm ARGOS logged it**

Within ~1 minute, check:
```bash
curl -H "X-API-Key: <key>" "https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors?limit=5"
```
Expected: a new row with `workflow_name = "TEST - Mecanico E2E (borrar)"`, `status = "new"`, `fix_status = "pendiente"`.

- [ ] **Step 4: Wait for the Mecánico's next 3-min cycle (or run it manually)**

```
n8n_update_partial_workflow({id: "<Mecanico id>", operations: [{type: "activateWorkflow"}]})
```
(if not already active), then either wait up to 3 minutes or open the `Argos Mecánico` workflow in the editor and click "Test workflow" once to run it immediately.

- [ ] **Step 5: Verify the outcome**

```bash
curl -H "X-API-Key: <key>" "https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors?limit=5"
```
Expected: the test row's `fix_status` moved to `resuelto` (if the injected bug was config-fixable, e.g. the broken expression) — confirm the underlying test workflow's node was actually patched via `n8n_get_workflow({id: "<test id>", mode: "structure"})`. Confirm the "✅ Solucionado" email arrived at `lgonzalez@automatizatech.cl`.

- [ ] **Step 6: Clean up**

```
n8n_update_partial_workflow({id: "<test id>", operations: [{type: "deactivateWorkflow"}]})
n8n_delete_workflow({id: "<test id>"})
```
Also delete the test row from `automatiza_n8n_errors` (via the ARGOS admin dashboard "🚫 Ignorar" or a direct DB cleanup) so it doesn't skew the real error stats.

- [ ] **Step 7: Report to Luis**

Summarize: what was injected, what the Mecánico diagnosed, what it changed, and paste/screenshot the resulting email. Ask for final sign-off before merging `feat/argos-mecanico` into `main` via PR (per the repo's standing rule — no direct merge without Luis's approval).
