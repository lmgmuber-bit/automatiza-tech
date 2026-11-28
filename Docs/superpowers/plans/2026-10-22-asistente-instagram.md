# Asistente de mensajes directos de Instagram — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que los mensajes directos nuevos de Instagram de AutomatizaTech los responda un asistente de n8n con el criterio del agente web, que agende y que derive a Luis con aviso por correo.

**Architecture:** El Portal OmniCliente ya recibe los directos y los reenvía a la URL de n8n del canal. Se suman tres cosas:
1. Un flujo nuevo de n8n, copia del agente web, con tres herramientas. Responde por el `callback_url` del Portal.
2. En el Portal: la acción `escalate` del callback, el descarte de respuestas tardías en conversaciones humanas de Instagram y la vuelta al bot después de 24 horas.
3. Configuración por canal en `bot_configs.custom_functions_json`.

**Tech Stack:** PHP 8.3 del Portal (`omnichannel-controller.php` y `api-omnichannel.php`, en la raíz del sitio WordPress), n8n (nodos LangChain Agent, OpenAI, Redis y HTTP), MySQL.

**Spec:** `Docs/superpowers/specs/2026-10-22-asistente-instagram-design.md`

## Global Constraints

- Solo el canal 2 («IG Automatizatech», cliente 1). WhatsApp y su comportamiento no cambian.
- El flujo de n8n es nuevo e independiente. «Agente Tech Web (PROD)» `3A5Y1NPBxpTjhHdE` y «WhatsApp Tech - Principal (PROD)» `bBcNlFgBzQ0766Mq` no se modifican.
- Prompt: el del nodo «Agente IA - Tech» del agente web, copiado tal cual, más el bloque «Canal Instagram». Modelo gpt-4o-mini y memoria Redis con clave `ig:<IGSID>` y ventana de 30.
- Herramientas del agente: `ver_disponibilidad`, `agendar` (sub-workflow `PejBgr2LZQSAbbBy`, `appointmentType = 'Demo'`, `session_id = 'ig:<IGSID>'`) y `derivar_a_persona`.
- Configuración de la derivación: `bot_configs.custom_functions_json` → `{"derivacion": {"agente_id": <id de Luis>, "correos": ["lgonzalez@automatizatech.cl", "lmgm.030@gmail.com"]}}`.
- Asunto del correo: `Instagram: <nombre> quiere hablar contigo`.
- Vuelta automática al bot: la persona escribe de nuevo y han pasado más de 24 h (86400 s) desde `last_message_at` en una conversación de Instagram en modo humano.
- Las 3 conversaciones de Instagram ya asignadas no se tocan; el bot parte con las nuevas.
- Se reutilizan las credenciales de n8n que ya existen (OpenAi account, Redis32, AT REST Secret). Ningún secreto va en el repo, que es **público**: los JSON de n8n van sin valores ni URLs con clave, y la ruta del webhook no se publica.
- `functions.php` no se toca. Nada va a PROD ni se activa sin el OK de Luis. Los pasos de PROD los ejecuta Eje, no un subagente.
- Textos para el usuario en español de Chile.

## Review Focus

1. **Una ráfaga de 3 mensajes en 5 segundos** debe recibir una sola respuesta que los considere todos (Task 3, prueba «ráfaga»).
2. **«Quiero hablar con alguien» en medio de un agendamiento** debe derivar y no agendar (Task 3, prueba «derivar en medio de agenda»).
3. **Una respuesta de n8n que llega después de derivar** no se envía a la persona (Task 1, prueba `callback de texto en conversación humana de Instagram se descarta`).
4. **Una persona que vuelve a escribir 25 horas después en una conversación humana** recibe respuesta del bot; a las 2 horas, no (Task 1, prueba `vuelve al bot tras 24 h`).
5. **Pedir una hora ocupada o un fin de semana:** el agente ofrece horas libres y no crea la cita (Task 3, prueba «hora ocupada»).

---

### Task 1: Portal — derivación pedida por el bot, descarte y vuelta al bot

**Files:**
- Create: `omnichannel-derivacion.php` (raíz del sitio, junto a `omnichannel-controller.php`). Funciones puras, sin WordPress, y la plantilla del correo.
- Modify: `omnichannel-controller.php`:
  - `handle_n8n_callback()` (~línea 2700): rama `action === 'escalate'` y descarte en Instagram humano;
  - `receive_message()` (~línea 896): vuelta al bot tras 24 h antes de `forward_to_n8n()`;
  - nuevo método `escalar_desde_bot()`.
- Test: `tests/omnicanal/derivacion-puras-test.php` (sin WP) y `tests/omnicanal/derivacion-wp-test.php` (con WP local).

**Interfaces:**
- Produces (puras, en `omnichannel-derivacion.php`, guardadas con `function_exists`):
  - `omni_derivacion_config(?string $custom_functions_json): array`. Devuelve `['agente_id' => int|0, 'correos' => string[]]`, solo con correos válidos (`filter_var`) y sin repetidos. Con un JSON inválido o sin la clave `derivacion` devuelve `['agente_id' => 0, 'correos' => []]`.
  - `omni_debe_volver_al_bot(string $channel_type, string $intervention_mode, ?string $last_message_at_gmt, int $ahora_ts, int $umbral = 86400): bool`. Es `true` solo si el canal es `instagram`, el modo es `human` y `ahora − last_message_at > umbral`.
  - `omni_derivacion_correo(array $conv, string $motivo, array $ultimos, string $url_portal): array{asunto:string, html:string}`.
    - `$conv` trae `contact_name` y `external_contact_id`.
    - `$ultimos` es una lista de `['dir' => 'in'|'out', 'texto' => string]`, con un máximo de 6.
    - El asunto es exactamente `Instagram: <nombre> quiere hablar contigo` (si falta el nombre: `Instagram: una persona quiere hablar contigo`).
    - El HTML escapa todo con `htmlspecialchars` y lleva la línea «Instagram solo permite responder dentro de las 24 horas desde el último mensaje de la persona.».
- Produces (controller):
  - `public function escalar_desde_bot(int $conversation_id, int $channel_id, string $motivo): array`.
    - Lee la config de `get_bot_config($channel_id)->custom_functions_json`.
    - Con `agente_id > 0` llama a `takeover_conversation($conversation_id, $agente_id, 'Derivado por el asistente: ' . $motivo)`. Si es 0, deja `status = 'open'`, `intervention_mode = 'human'` y `assigned_agent_id = NULL`.
    - Agrega un mensaje `sender_type = 'system'` con «El asistente derivó: <motivo>».
    - Envía `wp_mail` a cada correo de la config con `Content-Type: text/html`.
    - Si la conversación ya estaba en `human`, no repite el correo y devuelve `['ya_derivada' => true]`.
    - En éxito devuelve `['derivada' => true, 'agente_id' => int, 'correos' => int]`.
  - En `handle_n8n_callback($body)`, después de validar el token:
    - `action === 'escalate'` → `escalar_desde_bot(...)` con `motivo` (texto, máximo 300).
    - Sin `action`, en una conversación `channel_type = 'instagram'` con `intervention_mode = 'human'` → no envía nada y devuelve `['ignorada' => 'conversacion_humana']`. En WhatsApp sigue como hoy.
  - En `receive_message()`, antes del `if ($conv_status === 'bot' ...)`: si `omni_debe_volver_al_bot(...)` es verdadero, pone `status = 'bot'`, `intervention_mode = ''` y `assigned_agent_id = NULL`, y descuenta `active_chats` del ejecutivo asignado (con `GREATEST(..., 0)`). Así el mensaje se reenvía a n8n.
    - Ojo: hay que usar el `last_message_at` **anterior** al mensaje que está entrando. Leerlo antes de actualizarlo.
  - `api-omnichannel.php` ya enruta `webhook/n8n-callback` a `handle_n8n_callback()`; no se toca salvo que la acción `escalate` necesite pasar por otra validación.
  - El controller hace `require_once __DIR__ . '/omnichannel-derivacion.php'`.

- [ ] **Step 1: Pruebas puras que fallan** — `tests/omnicanal/derivacion-puras-test.php`, con el mismo estilo `ok()` y `TODO OK` de `tests/finanzas/puras-test.php`. Casos:
  - `omni_derivacion_config('{"derivacion":{"agente_id":7,"correos":["a@b.cl","a@b.cl","malo"]}}')` debe ser `['agente_id' => 7, 'correos' => ['a@b.cl']]`;
  - `omni_derivacion_config(null)`, `('{')` y `('{"otra":1}')` devuelven `['agente_id' => 0, 'correos' => []]`;
  - `omni_debe_volver_al_bot('instagram', 'human', '2026-10-21 10:00:00', strtotime('2026-10-22 10:00:01 UTC'))` es `true`;
  - con 2 horas de diferencia es `false`; con `'whatsapp'` es `false`; con modo `''` es `false`; con `last_message_at` nulo es `false`;
  - `omni_derivacion_correo(['contact_name' => 'Ana <b>', ...], 'Quiere precio a medida', [...], 'https://x')['asunto'] === 'Instagram: Ana <b> quiere hablar contigo'`, y el HTML contiene `Ana &lt;b&gt;` y la línea de las 24 horas;
  - sin nombre, el asunto es el genérico.
- [ ] **Step 2:** `C:/wamp64/bin/php/php8.3.28/php.exe tests/omnicanal/derivacion-puras-test.php` debe FALLAR (falta el archivo).
- [ ] **Step 3:** Implementar `omnichannel-derivacion.php` con las tres funciones de arriba.
- [ ] **Step 4:** Las pruebas puras deben dar TODO OK.
- [ ] **Step 5: Pruebas con WordPress que fallan** — `tests/omnicanal/derivacion-wp-test.php`.
  - **Arranque:**
    - `$_SERVER['HTTP_HOST'] = 'localhost'`;
    - `require` de `getenv('AT_WP_LOAD') ?: 'C:/wamp64/www/automatiza-tech/wp-load.php'`;
    - después, `require` del `omnichannel-controller.php` **del worktree**. Si la clase `OmnichannelController` ya existe porque la cargó WordPress, la prueba se detiene con el mensaje «el controller ya estaba cargado: ajusta el arranque».
  - Crea su propio cliente, canal de Instagram, `bot_configs` con `derivacion`, ejecutivo y conversación, todos marcados `[PRUEBA]`, y los borra en `register_shutdown_function`.
  - Captura `wp_mail` con el filtro `pre_wp_mail` y bloquea toda salida HTTP con `pre_http_request`, devolviendo un 200 falso.
  - El callback se genera con `hash_hmac('sha256', "$conv:$canal", OMNI_ADMIN_SECRET ?? 'omni_default_secret')`, la misma fórmula del controller.
  - Casos:
    - `escalate` con token válido: la conversación queda `intervention_mode = 'human'`, `status = 'assigned'` y `assigned_agent_id` = ejecutivo de la config; hay un mensaje `system` que contiene «El asistente derivó»; se capturan 2 correos con el asunto exacto;
    - `escalate` con token inválido: devuelve error y la conversación no cambia;
    - `escalate` dos veces: el segundo devuelve `ya_derivada` y no captura correos nuevos;
    - `escalate` con config sin `agente_id`: `status = 'open'` y `assigned_agent_id` NULL;
    - callback de texto en una conversación humana de Instagram: devuelve `ignorada` y no se agrega ningún mensaje saliente;
    - callback de texto en una conversación humana de **WhatsApp**: se comporta como antes (regresión: se guarda el mensaje saliente);
    - `receive_message` en una conversación humana de Instagram con `last_message_at` de hace 25 h: queda `status = 'bot'` y se intenta el POST a n8n (capturado con `pre_http_request`);
    - con `last_message_at` de hace 2 h: sigue en modo humano y no hay POST a n8n.
- [ ] **Step 6:** Ejecutar con WAMP encendido (MySQL `wampmysqld64`; si está apagado, pedirle a Luis que lo inicie: requiere administrador). Debe FALLAR.
- [ ] **Step 7:** Implementar los cambios del controller según «Interfaces».
- [ ] **Step 8:** `php -l` de los dos archivos; las dos suites deben dar TODO OK.
- [ ] **Step 9: Commit** — `feat(omnicliente): derivación pedida por el bot, descarte en Instagram humano y vuelta al bot tras 24 h`.

### Task 2: Flujo n8n «Agente Tech Instagram (PROD)» — entrada, ráfaga y respuesta por callback

**Files:**
- Create: `N8N/instagram/agente-tech-instagram.json`, el flujo exportado y saneado (sin credenciales, con la ruta del webhook reemplazada por `RUTA_WEBHOOK`).
- Create: `N8N/instagram/aplicar.py`. Crea o actualiza el flujo por la API de n8n:
  - lee la clave de n8n de `~/.claude.json` sin imprimirla, como los scripts de `N8N/retomar/`;
  - genera la ruta del webhook con `secrets.token_urlsafe(24)` la primera vez y la guarda fuera del repo, en `~/respaldos/deploy-scripts/2026-10-22-asistente-instagram/ruta.txt`;
  - deja el flujo **desactivado**.
- Create: `N8N/instagram/probar.py`. Manda payloads falsos al webhook con un `callback_url` de captura y valida las respuestas (ver Task 3).

**Interfaces:**
- Consumes: el JSON del Portal (spec, «Lo que ya existe»): `conversation_id`, `channel_id`, `contact.external_id`, `contact.name`, `message.type`, `message.content`, `callback_url` y `callback_token`.
- Produces:
  - **respuesta normal:** `POST callback_url` con `{conversation_id, channel_id, callback_token, content}`, el campo de texto que lee `handle_n8n_callback` (verificar el nombre exacto en el controller: `content`, `text` o `output`);
  - **derivación:** el mismo POST con `action: 'escalate'` y `motivo`.
- Nodos, en orden:
  1. Webhook POST (`RUTA_WEBHOOK`) con respuesta inmediata 200.
  2. Filtro: `channel_id == 2`, mensaje no vacío.
  3. Si no es texto: callback con «Por ahora leo solo texto: ¿me lo escribes? 🙂». Se responde una sola vez por persona cada 10 minutos, usando la clave Redis `ig:notexto:<IGSID>`.
  4. Ráfaga: igual que en WhatsApp (push a `ig:rafaga:<IGSID>`, esperar 6 s, solo la última ejecución sigue y une los textos).
  5. `GET /wp-json/automatiza-tech/v1/exchange-rate`.
  6. Agente (Task 3).
  7. Callback.
  8. Si el callback falla: no reintentar y dejar el error a ARGOS.

- [ ] **Step 1:** Exportar el agente web `3A5Y1NPBxpTjhHdE` (solo lectura) como base.
- [ ] **Step 2:** Armar el JSON con los nodos de arriba, sin el agente todavía (un Set que devuelva el texto recibido).
- [ ] **Step 3:** `python N8N/instagram/aplicar.py` debe crear el flujo desactivado e imprimir su id, no la ruta. Para probar se activa solo durante la prueba y se desactiva al terminar.
- [ ] **Step 4:** En `probar.py`, caso «eco»:
  - un payload de texto debe producir un callback capturado con el mismo `conversation_id` y `callback_token`;
  - caso «ráfaga»: 3 POST en 5 s dan **un** callback, con los tres textos unidos;
  - caso «no texto»: el tipo `image` da el mensaje fijo, y un segundo `image` en menos de 10 min no da callback.

  La captura es un flujo temporal de n8n «IG Captura (borrar)» que guarda el último cuerpo, o un archivo de captura que `probar.py` lee por la API de ejecuciones. El flujo temporal se archiva y se borra al terminar, como los flujos temporales de Retomar.
- [ ] **Step 5: Commit** — `feat(n8n): flujo del asistente de Instagram con ráfaga y respuesta por callback`.

### Task 3: Agente, prompt y herramientas

**Files:**
- Modify: `N8N/instagram/agente-tech-instagram.json`, que pasa a usar el agente.
- Create: `N8N/instagram/prompt-canal-instagram.md`, el bloque «Canal Instagram» que se agrega al final del prompt del agente web.
- Modify: `N8N/instagram/probar.py`, con los casos del agente.

**Interfaces:**
- Herramientas, como nodos tool de n8n conectados al agente:
  - `ver_disponibilidad(fecha: 'AAAA-MM-DD' | '')`: `GET /automatiza-tech/v1/appointments-config` y `POST /automatiza-tech/v1/check-availability {date}`. Devuelve hasta 8 horas libres, en `America/Santiago`; con la fecha vacía, los próximos 3 días hábiles sin feriados.
  - `agendar(nombre, correo, telefono, fecha 'AAAA-MM-DD', hora 'HH:MM')`: Execute Workflow `PejBgr2LZQSAbbBy` con `{date, time, email, name, phone, session_id: 'ig:<IGSID>', appointmentType: 'Demo'}`. Devuelve el mensaje de éxito u ocupado del sub-workflow.
    - **Verificar antes de usarlo:** con `phone` vacío, ¿falla o duplica? Leer `Normalize Data`, `Get Client Data` y `Save Appointment to WP` del sub-workflow. Si exige teléfono, el bloque del prompt lo pide como obligatorio.
  - `derivar_a_persona(motivo: string)`: callback con `action: 'escalate'`. Después el agente se despide con «Le avisé a Luis, te va a responder por aquí mismo».
- Bloque «Canal Instagram»:
  - respuestas cortas, de 1 a 3 párrafos breves, sin formato de página ni enlaces largos;
  - no ofrecer el formulario web, sino agendar en el chat;
  - antes de llamar a `agendar`, confirmar fecha, hora y correo, y pedir un teléfono o WhatsApp para recordatorios (opcional, salvo que el sub-workflow lo exija);
  - derivar con `derivar_a_persona` cuando la persona pida una persona o un ejecutivo, tenga un reclamo o sea cliente y pida seguimiento;
  - no inventar disponibilidad: siempre consultar `ver_disponibilidad`.
  - La etiqueta `<<SHOW_DEMO_FORM>>` del prompt web se reemplaza por esas reglas.

- [ ] **Step 1:** Escribir los casos en `probar.py`. Cada uno corre con el callback de captura y valida el último callback:
  - «precio»: «¿Cuánto cuesta un chatbot?» → el texto contiene `USD` y `CLP`;
  - «fuera de tema»: «Dame una receta» → se rechaza con amabilidad, sin receta;
  - «persona»: «Quiero hablar con una persona» → hay un callback con `action: 'escalate'`;
  - «derivar en medio de agenda»: después de pedir una reunión, «mejor que me llame alguien» → `escalate` y ninguna llamada al sub-workflow (revisar en las ejecuciones de n8n que no se haya ejecutado `PejBgr2LZQSAbbBy`);
  - «hora ocupada»: pedir el sábado → ofrece días hábiles y no agenda;
  - «agenda completa»: con un correo de prueba de AT, la cita se crea. Después se borra con `DELETE /automatiza-tech/v1/appointments/{id}`, junto con el evento de Calendar y el lead de prueba. Este caso corre **solo con el OK de Luis**, porque escribe en PROD.
- [ ] **Step 2:** Ejecutarlos con el flujo de la Task 2: deben fallar los del agente.
- [ ] **Step 3:** Agregar el nodo agente (gpt-4o-mini, memoria Redis `ig:<IGSID>` ventana 30), el prompt (web + bloque) y las tres herramientas. `aplicar.py` actualiza el flujo, que sigue desactivado.
- [ ] **Step 4:** Todos los casos salvo «agenda completa» deben pasar. Registrar los resultados en `N8N/instagram/README.md`.
- [ ] **Step 5: Commit** — `feat(n8n): agente de Instagram con disponibilidad, agenda y derivación`.

### Task 4: Documentación y puesta en marcha (PROD, la hace Eje con el OK de Luis)

**Files:**
- Create: `N8N/instagram/README.md`: qué hace, cómo se prueba, cómo se activa y cómo se desactiva.
- Create: `Docs/METODO_AT/ASISTENTE-INSTAGRAM.md`:
  - guía corta para Luis: qué responde el asistente, cómo llega la derivación y cómo devolver la conversación al bot;
  - despliegue y vuelta atrás.
- Modify: `CLAUDE.md` y `AGENTS.md`: un puntero en «Punteros de proyecto».

- [ ] **Step 1:** Escribir los documentos sin rutas de webhook, claves ni datos de personas.
- [ ] **Step 2:** Correr todas las suites: `tests/omnicanal/*`, `tests/finanzas/*` y `tests/retomar/*` (regresión de WordPress). Deben dar TODO OK.
- [ ] **Step 3: Commit, push y PR**, sin merge.
- [ ] **Step 4 (PROD, con el OK de Luis):** pasos de Eje.
  1. Cotejar con PROD el md5 de `omnichannel-controller.php` y `api-omnichannel.php` contra `main` y respaldarlos.
  2. Subir `omnichannel-derivacion.php` primero y después `omnichannel-controller.php`, con `php -l` y md5.
  3. Respaldar las filas de `bot_configs` del canal 2 y de `agents` del cliente 1.
  4. Crear el ejecutivo «Luis González» (correo `lgonzalez@automatizatech.cl`, cliente 1, rol `supervisor`, activo, **sin contraseña**: entra como superadministrador).
  5. Escribir `custom_functions_json.derivacion` del canal 2 con su id y los dos correos.
  6. Escribir `bot_configs.n8n_webhook_url` del canal 2 con la URL del flujo y activar el flujo.
- [ ] **Step 5:** Prueba de punta a punta con Luis.
  - Desde una cuenta de Instagram de prueba se escribe a AutomatizaTech:
    - responde;
    - consulta horarios;
    - «quiero hablar con una persona» llega a los dos correos y la conversación aparece asignada a Luis en el Portal;
    - el asistente ya no responde en esa conversación.
  - Las 3 conversaciones existentes siguen asignadas.
- [ ] **Step 6:** Checkpoint after-prod de Aseo; actualizar la memoria y la bóveda.
  - **Vuelta atrás:** vaciar `n8n_webhook_url`, desactivar el flujo y restaurar el controller desde el respaldo.
