# Evento Lead por CAPI — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que cada primera agenda (web, Instagram y WhatsApp) llegue a Meta como `Lead` desde el servidor, deduplicada con el píxel de la web, detrás de un interruptor propio apagado.

**Architecture:** `/leads` dispara `do_action('automatiza_tech_lead_guardado', $lead_id, $data)` tras insertar. Un módulo nuevo (`inc/meta-capi/`, cargado por el mu-plugin `at-meta-capi.php`) escucha, decide si corresponde (interruptor, token, primera agenda en 30 días) y hace un POST no bloqueante a la Graph API con los datos cifrados. En el navegador, `window.atLead(origen, datos)` calcula el mismo `event_id` y recuerda 30 días que ya avisó.

**Tech Stack:** WordPress/PHP 8.3, JavaScript del navegador (Web Crypto), PixelYourSite 11.4.4 (token y píxel), pruebas PHP con `ok()`/`fin()` y una prueba de paridad en Node.

**Spec:** `Docs/superpowers/specs/2026-10-28-meta-capi-lead-design.md`

## Global Constraints

- Solo cuenta quien **agenda una reunión** nueva: `/leads`. `/followup-meetings` no se toca.
- Canales: web, Instagram y WhatsApp. Se cuentan todas las agendas, también las de quien rechazó cookies (el píxel del navegador sigue respetando el aviso).
- Primera agenda por **correo normalizado** en **30 días** (servidor y navegador).
- `event_id = sha256(correo_normalizado + '|' + AAAA-MM-DD + '|' + HH:MM)`, primeros **40** caracteres hex, igual en PHP y JS.
- `action_source`: `chat` si es `instagram` o `whatsapp`; para la web, **`other`** con `event_source_url` = `home_url('/')`. Motivo (verificado el 28-oct): Meta exige `client_user_agent` y `event_source_url` en los eventos `website` ([parámetros del evento](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/server-event), [datos del cliente](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters)), y `/leads` no recibe el navegador porque la agenda llega desde n8n (`automatiza_tech_save_lead` en `16ed868` solo lee name, email, phone, session_id, source, fechas y datos de la reunión). La deduplicación con el píxel depende solo de `event_id` + `event_name` + mismo píxel, en 48 h ([deduplicación](https://developers.facebook.com/docs/marketing-api/conversions-api/deduplicate-pixel-and-server-events)), no de `action_source`. Si algún día n8n reenvía el user agent, se pasa a `website` sin tocar lo demás.
- `user_data`: `em`, `ph`, `external_id` (sha256 del correo) y `country` (`cl` en sha256 si el teléfono es chileno). Nunca nombre ni texto en claro.
- Interruptor propio `at_capi_activo` (opción de WordPress), **nace en `0`**. Sin `1` o sin token de PYS no se envía nada.
- Token y píxel se leen de PixelYourSite (`server_access_api_token`, `pixel_id`); `test_api_event_code` si existe. El token nunca va en la URL, el repo, un log ni el chat.
- La agenda nunca falla por CAPI: envío no bloqueante; errores al log sin datos personales (solo `lead_id`, origen, código HTTP).
- Base de `api-endpoints.php` y `lead-origen.php`: copias **idénticas** a PROD (commit `16ed868`, md5 `41d46fd6949d536991ebce31b60c7c0b` / `7365a0393aafeb26d89b5299cb10fc34`), más **una sola línea** nueva (`do_action`). `functions.php` no se toca. Repo público: nada de tokens ni datos de clientes.
- «Agenda del sitio» (respaldo de contacto en `footer.php`) deja de disparar Lead.

## Review Focus

1. **Correo con mayúsculas o espacios** en el formulario y distinto en `/leads`: el `event_id` debe coincidir igual (Task 1, prueba de paridad con `" Ana@Correo.CL "`).
2. **Hora con segundos** (`10:00:00`) en `/leads` y `10:00` en el navegador: mismo `event_id` (Task 1).
3. **Graph API caída o token inválido**: `/leads` responde lo mismo que sin CAPI y no se marca la «primera agenda» (Task 2, prueba con `pre_http_request` que devuelve 500 / WP_Error).
4. **Navegador sin `crypto.subtle` o sin `localStorage`** (modo privado, http): `atLead` sigue disparando el Lead del píxel sin `eventID` y sin romper la página (Task 3).
5. **Dos agendas del mismo correo el mismo día**: una sola llamada a Meta (Task 2).
6. **`/leads` llamado con `scheduled_date`/`scheduled_time` vacíos o en otro formato** (n8n manda `null` o `03-11-2026`): no se rompe; el Lead se manda igual con un `event_id` de respaldo `sha256('lead|'+lead_id)` y queda sin deduplicar con el píxel (Task 1 y 2).

---

### Task 1: Funciones puras (PHP y JS) con la misma huella

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/meta-capi/puras.php`
- Create: `wp-content/themes/automatiza-tech/assets/js/at-lead-id.js` (funciones del navegador; sin dependencias)
- Test: `tests/meta-capi/puras-test.php`, `tests/meta-capi/paridad-test.mjs`, `tests/meta-capi/correr.sh`

**Interfaces:**
- Produces (PHP): `at_capi_norm_correo(string): string` (minúsculas, sin espacios); `at_capi_norm_telefono(string): string` (solo dígitos; si son 9 dígitos que empiezan con 9, antepone `56`; vacío si no queda nada); `at_capi_norm_fecha(string): string` (`AAAA-MM-DD` o `''`); `at_capi_norm_hora(string): string` (`HH:MM` desde `H:MM`, `HH:MM` o `HH:MM:SS`; `''` si no es hora); `at_capi_hash(string): string` (sha256 hex, `''` si entra vacío); `at_capi_event_id(string $correo, string $fecha, string $hora): string` (40 hex; `''` si falta algo); `at_capi_action_source(string $source): string`; `at_capi_payload(array $lead, string $pixel_id, string $test_code, int $ahora): array` (cuerpo para la Graph API, sin token).
- Produces (JS, en `window.AtLeadId`): `normCorreo`, `normFecha`, `normHora`, `eventId(correo, fecha, hora): Promise<string>` (mismo algoritmo con `crypto.subtle`; resuelve `''` si no hay `crypto.subtle`).

- [ ] **Step 1: Pruebas puras PHP**: normalizaciones (`" Ana@Correo.CL "` → `ana@correo.cl`; `"+56 9 1234 5678"` y `"912345678"` → `56912345678`; `"10:00:00"`, `"10:00"`, `"9:05"` → `10:00`, `10:00`, `09:05`; `"25:00"` → `''`); `at_capi_event_id` con un vector fijo (se anota el valor esperado calculado una vez y queda en la prueba); `action_source` (`web`→`other`, `instagram`/`whatsapp`→`chat`, desconocido→`other`); el payload web trae `event_source_url` y ninguno trae `client_user_agent` vacío; `at_capi_payload` trae `event_name=Lead`, el `event_id`, `em`/`ph` en hash, `custom_data.content_name` correcto y **ninguna** aparición del correo o teléfono en claro en `wp_json_encode` del resultado.
- [ ] **Step 2: Prueba de paridad Node** (`paridad-test.mjs`): carga `at-lead-id.js` en un contexto con `crypto.subtle` de Node y comprueba que `eventId(" Ana@Correo.CL ", "2026-11-03", "10:00:00")` da exactamente el vector de Step 1.
- [ ] **Step 3: Correr y ver que fallan** (`bash tests/meta-capi/correr.sh`).
- [ ] **Step 4: Implementar** `puras.php` y `at-lead-id.js`.
- [ ] **Step 5: Correr** → TODO OK.
- [ ] **Step 6: Commit** `feat(capi): funciones puras y huella del evento Lead`

### Task 2: Envío desde el servidor

**Files:**
- Create: `wp-content/mu-plugins/at-meta-capi.php` (carga `themes/automatiza-tech/inc/meta-capi/cargar.php` si existe)
- Create: `wp-content/themes/automatiza-tech/inc/meta-capi/cargar.php`, `envio.php`
- Modify: `wp-content/themes/automatiza-tech/inc/api-endpoints.php` — **primero** reemplazar por la versión de `16ed868` (`git show 16ed868:wp-content/themes/automatiza-tech/inc/api-endpoints.php`) y traer `inc/lead-origen.php` del mismo commit; comprobar md5 contra los de Global Constraints; **después** agregar la línea `do_action('automatiza_tech_lead_guardado', $lead_id, $data);` justo tras obtener `$lead_id` con éxito en `automatiza_tech_save_lead`.
- Test: `tests/meta-capi/envio-wp-test.php` (WordPress local, `AT_WP_LOAD`, carga el módulo del worktree)

**Interfaces:**
- Consumes: Task 1.
- Produces: `at_capi_config(): array` (`['activo'=>bool,'token'=>string,'pixel'=>string,'test'=>string]`, leído de `at_capi_activo` y de `\PixelYourSite\Facebook()`); `at_capi_al_guardar_lead(int $lead_id, array $data): void` (enganchado a la acción); `at_capi_ya_contado(string $correo): bool` y `at_capi_marcar_contado(string $correo): void` (transient `at_capi_lead_<sha256>` de 30 días); `at_capi_enviar(array $payload, array $cfg): bool` (`wp_remote_post` a `https://graph.facebook.com/<versión>/<pixel>/events`, cuerpo JSON con `data`, `access_token` y `test_event_code` si hay; `blocking => false`; la marca se escribe solo si `wp_remote_post` no devuelve `WP_Error`).
- La versión de la Graph API se fija en una constante `AT_CAPI_GRAPH_VERSION` con la que use el SDK de PYS 11.4.4 (verificar en `vendor_prefix/facebook/php-business-sdk`).

- [ ] **Step 1: Pruebas** (capturando con `pre_http_request`): con `at_capi_activo=1` y token de prueba en la config de PYS, una agenda web nueva produce **una** llamada a `graph.facebook.com` con `action_source=other`, `event_id` = el de Task 1 y sin correo ni teléfono en claro en el cuerpo; una de origen `instagram` produce `action_source=chat`; la segunda agenda del mismo correo (aunque cambie la hora) no llama; con `at_capi_activo=0` no llama; sin token no llama; si la llamada devuelve `WP_Error`, `/leads` responde igual y el correo **no** queda marcado; `/followup-meetings` no llama. Las pruebas guardan y restauran la configuración de PYS que tocan.
- [ ] **Step 2: Correr y ver que fallan.**
- [ ] **Step 3: Implementar**; el log de error usa `error_log('[at-capi] lead=<id> origen=<o> http=<código>')` y nada más.
- [ ] **Step 4: Correr** → TODO OK; `php -l` de los archivos nuevos y de `api-endpoints.php`.
- [ ] **Step 5: Commit** `feat(capi): Lead desde /leads con interruptor y primera agenda en 30 días`

### Task 3: Navegador

**Files:**
- Modify: `wp-content/mu-plugins/at-consentimiento.php` — imprime `at-lead-id.js` (inline con `file_get_contents`, o encolado) antes de su script; `window.atLead(origen, datos)`: sin consentimiento no hace nada (como hoy); con `datos = {email, fecha, hora}` calcula `AtLeadId.eventId`, revisa en `localStorage` la clave `at_lead_<hash del correo>` (fecha de vencimiento a 30 días) y, si no hay marca vigente, llama `fbq('track','Lead',{content_name},{eventID})` y deja la marca; sin `datos`, sin `crypto.subtle` o sin `localStorage`, dispara como hoy (sin `eventID`), envuelto en `try/catch`.
- Modify: `wp-content/themes/automatiza-tech/footer.php` — «Agenda de demo» pasa `{email, fecha: date, hora: time}`; el respaldo «Agenda del sitio» deja de llamar `atLead`.
- Modify: `wp-content/themes/automatiza-tech/assets/home-premium/at-home.js` — pasa `{email, fecha: scheduled_date, hora: scheduled_time}`.
- Test: `tests/meta-capi/atlead-test.mjs` (Node con un `window` simulado: `fbq` falso que registra llamadas, `localStorage` en memoria) y `tests/meta-capi/plantillas-test.php` (lee `footer.php` y `at-home.js`: el respaldo no contiene `atLead`; las dos agendas pasan el segundo argumento).

- [ ] **Step 1: Pruebas**: con consentimiento y datos, `atLead` llama `fbq` una vez con `eventID` igual al vector; una segunda llamada con el mismo correo no llama; sin consentimiento no llama; sin `crypto.subtle` llama sin `eventID`; `localStorage` que lanza excepción no rompe; más las comprobaciones de plantillas.
- [ ] **Step 2: Correr y ver que fallan.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Correr toda la carpeta** → TODO OK; `php -l` de `at-consentimiento.php` y `footer.php`; `node --check` de `at-home.js` y `at-lead-id.js`.
- [ ] **Step 5: Commit** `feat(capi): el píxel manda el mismo event_id y recuerda la primera agenda`

### Task 4: Guía, texto de privacidad y despliegue

**Files:**
- Create: `Docs/MARKETING/2026-10-28-META-CAPI-LEAD.md` — qué hace; cómo genera Luis el token (Administrador de eventos → píxel → Configuración → API de Conversiones → Generar token) y dónde lo pega (PixelYourSite → Meta → Conversion API); cómo probar con «Probar eventos»; cómo se enciende `at_capi_activo` (`wp option update at_capi_activo 1` por SSH, solo Eje con el OK de Luis) y cómo se apaga; propuesta de la línea de privacidad para que Luis la apruebe; despliegue y vuelta atrás.
- Modify: `Docs/MARKETING/2026-10-22-CONSENTIMIENTO-Y-EVENTO-LEAD.md` — una línea que enlaza a la guía nueva.

- [ ] **Step 1: Escribir la guía** sin tokens, ids privados ni datos de personas.
- [ ] **Step 2: Correr todas las suites** de `tests/meta-capi/` → TODO OK.
- [ ] **Step 3: Commit, push y PR** (sin merge).
- [ ] **Step 4 (PROD, solo Eje con el OK de Luis):** cotejar con PROD el md5 de `api-endpoints.php`, `at-consentimiento.php`, `footer.php` y `at-home.js`; respaldar; subir en orden: `inc/meta-capi/` → `mu-plugins/at-meta-capi.php` → `assets/js/at-lead-id.js` → `api-endpoints.php` → `at-consentimiento.php`, `footer.php`, `at-home.js`; `php -l`, md5 y purga de caché; comprobar con una sonda de solo lectura que `at_capi_activo` sigue en `0`. **No se envía nada a Meta en este paso.**
- [ ] **Step 5 (con Luis):** él pega el token y aprueba la línea de privacidad; Eje pone `at_capi_activo=1` con un código de prueba de «Probar eventos»; una agenda de prueba web (correo de prueba de AT) aparece **una vez** en Meta y una por WhatsApp como `chat`; se borra la cita de prueba y se quita el código de prueba.
