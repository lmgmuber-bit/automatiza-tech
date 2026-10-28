# Evento Lead por la API de Conversiones de Meta (CAPI) — diseño

**Fecha:** 28-oct-2026 · **Pedido:** Luis · **Diseño:** Eje · **Rama:** `claude/meta-capi`

## 1. Para qué

Luis va a lanzar campañas en Meta y quiere la medición lista desde el primer peso: que Meta sepa de **cada reunión agendada**, aunque el navegador bloquee el píxel (bloqueadores, Safari/iOS, página cerrada antes de tiempo), para medir cuántos leads trae cada anuncio y para que Meta optimice con leads reales.

**Éxito:** en el Administrador de eventos de Meta, cada agenda nueva (web, Instagram y WhatsApp) aparece una sola vez como `Lead`, y las de la web no se cuentan doble entre el píxel y el servidor.

## 2. Lo que decidió Luis (28-oct)

1. **Qué es un lead:** solo quien **agenda una reunión** (diagnóstico o demo). No cuentan el formulario de contacto, las conversaciones sin agenda ni las reuniones de seguimiento de clientes.
2. **Canales:** **todos**: web, asistente de Instagram y bot de WhatsApp.
3. **Consentimiento:** se cuentan **todas** las agendas, también la de quien agendó en la web habiendo **rechazado las cookies**. El píxel del navegador sigue respetando el aviso de cookies como hoy; el servidor manda el Lead siempre.
4. **Lo hace Eje** (no la sesión del asistente de Instagram).
5. **Para cuándo:** dejarlo listo antes de lanzar campañas; hoy no hay anuncios activos.

## 3. Lo que ya existe (verificado el 28-oct, solo lectura)

- **PixelYourSite 11.4.4** (gratuito) activo, píxel **1347922513241779**. Trae CAPI (Facebook Business SDK) pero está **apagado**: `use_server_api = false`, sin `server_access_api_token`, sin `test_api_event_code`. La «coincidencia avanzada» está apagada. El CAPI del plugin solo reenvía los eventos que dispara el propio plugin (PageView, eventos automáticos); **no** el Lead nuestro.
- **Aviso de cookies** (`wp-content/mu-plugins/at-consentimiento.php`, PR #104, EN PROD sin merge): sin «Aceptar», PYS no carga ningún píxel. `window.atLead(origen)` hace `fbq('track','Lead',{content_name})` solo con consentimiento. Se llama en tres lugares: «Agenda de demo» y «Agenda del sitio» (`footer.php`) y «Agenda diagnóstico (portada)» (`assets/home-premium/at-home.js`).
- **Camino de cada agenda:** todas terminan en el mismo lugar.
  - Web (aviso emergente, portada y chat): POST al webhook del «Agente Tech Web (PROD)» `3A5Y1NPBxpTjhHdE` con `action: 'saveLead'` → `Switch Action` → sub-workflow «Agendar Reunión AutomatizaTech - Cliente (PROD)» `PejBgr2LZQSAbbBy`.
  - WhatsApp (`bBcNlFgBzQ0766Mq`) e Instagram (flujos del asistente, EN PROD 28-oct): el mismo sub-workflow.
  - El sub-workflow, en «Save Appointment to WP», hace POST a `/wp-json/automatiza-tech/v1/leads` (o a `/followup-meetings` si `appointmentType === 'Seguimiento'`).
- **`/leads`** (`automatiza_tech_save_lead`, `wp-content/themes/automatiza-tech/inc/api-endpoints.php`): valida, aplica el límite de reuniones activas por correo, revisa choque de horario e **inserta siempre una fila nueva**; el origen sale de `automatiza_tech_lead_origen()` (`inc/lead-origen.php`: `ig:` → instagram, WhatsApp, web). Un reagendamiento por WhatsApp borra la cita vieja y crea otra fila. `/leads` **no recibe** el consentimiento de cookies.
- **Base de PROD de `api-endpoints.php`:** commit `16ed868` de `claude/asistente-ig-directos` (md5 `41d46fd6949d536991ebce31b60c7c0b`, desplegado 28-oct 09:31). PROD lleva además ramas sin merge (#103, #104, #107, #108, #109): cotejar cada archivo con PROD antes de tocarlo.

## 4. Diseño

### 4.1 Encender el CAPI del plugin (sin código)

Luis genera el **token de la API de Conversiones** en el Administrador de eventos (píxel → Configuración → API de Conversiones → Generar token) y lo pega **él** en PixelYourSite → Meta → «Conversion API». Activa «Send events directly from your web server». Así PYS manda por el servidor sus propios eventos (PageView y automáticos), solo para quien aceptó cookies (PYS sigue apagado sin consentimiento). El token vive solo en la configuración de PYS: nunca en el repo, el chat ni un log.

### 4.2 Lead desde el servidor, en `/leads`

- **Dónde:** después de que `/leads` inserta la fila con éxito. `api-endpoints.php` agrega **una línea**: `do_action('automatiza_tech_lead_guardado', $lead_id, $data)`. Todo lo demás vive en un módulo nuevo que escucha esa acción (mu-plugin `wp-content/mu-plugins/at-meta-capi.php`, que carga `inc/meta-capi/`), para no seguir engordando `api-endpoints.php`. `/followup-meetings` no se toca: los seguimientos de clientes no son leads.
- **Solo la primera agenda:** se manda el Lead solo si ese correo no generó un Lead de CAPI en los últimos **30 días** (marca por hash del correo, p. ej. transient de 30 días). Un reagendamiento o una segunda agenda no vuelven a contar.
- **Qué se manda** (POST no bloqueante a `https://graph.facebook.com/<versión>/<pixel>/events`, token en el cuerpo, nunca en la URL):
  - `event_name: 'Lead'`, `event_time` (hora del guardado), `event_id` (ver 4.3).
  - `action_source`: `website` si el origen es web; `chat` si es Instagram o WhatsApp.
  - `user_data`: `em` y `ph` normalizados y en **SHA-256** (correo en minúsculas sin espacios; teléfono solo dígitos con código de país), `external_id` = SHA-256 del correo, `country` = `cl` en hash si el teléfono es chileno.
  - `custom_data`: `content_name` = `Agenda (web)`, `Agenda (Instagram)` o `Agenda (WhatsApp)`, y `lead_source` con el origen.
  - Si existe `test_api_event_code` en PYS, se incluye (para «Probar eventos»).
- **Token y píxel:** se leen de la configuración de PYS (`server_access_api_token` y `pixel_id` del módulo Facebook). Sin token, el módulo no hace nada (falla cerrada, sin error para la persona que agenda).
- **Nunca rompe la agenda:** el envío es no bloqueante y cualquier error se registra sin datos personales (solo `lead_id`, origen y código HTTP) y no cambia la respuesta de `/leads`.

### 4.3 Que la web no se cuente doble

El navegador (píxel) y el servidor deben mandar el mismo `event_id`. Sin tocar n8n, los dos lo calculan igual: **`event_id = SHA-256(correo normalizado + '|' + fecha AAAA-MM-DD + '|' + hora HH:MM)`**, recortado a 40 caracteres. El navegador conoce esos tres datos al enviar la agenda; `/leads` los recibe.

- `window.atLead(origen, datos)` acepta un segundo argumento opcional `{email, fecha, hora}`, calcula el hash con `crypto.subtle` y llama `fbq('track','Lead',{content_name}, {eventID})`. Sin `datos`, se comporta como hoy.
- Los dos formularios de agenda que de verdad agendan («Agenda de demo» y la portada) pasan esos datos.
- **«Agenda del sitio»** (el respaldo de `footer.php` que solo manda un formulario de contacto cuando n8n no está disponible) **deja de disparar Lead**: no es una agenda *(decisión de Eje, por la regla 1)*.

## 5. Privacidad

- Se manda correo y teléfono **solo cifrados** (SHA-256), como exige Meta. Nunca nombre ni texto de la conversación.
- Las agendas de quien rechazó cookies y las de Instagram/WhatsApp **sí** se mandan (decisión de Luis). Se recomienda sumar a la política de privacidad (`/privacidad/`) una línea: «Para medir nuestros anuncios compartimos con Meta, de forma cifrada, el correo y el teléfono de quienes agendan una reunión». El texto exacto lo aprueba Luis antes de activar el envío.

## 6. Fuera de esta etapa

Contar compras o clientes cerrados (evento `Purchase` u otros), audiencias personalizadas, Google Ads, y mandar el Lead de los seguimientos de clientes.

## 7. Pruebas

- **Puras:** normalización y hash de correo y teléfono (casos con mayúsculas, espacios, `+56 9`, sin código de país); `event_id` igual en PHP y en JavaScript para los mismos datos (mismo vector de prueba en los dos); elección de `action_source` por origen.
- **Con WordPress local:** una agenda nueva dispara exactamente un envío (capturado con `pre_http_request`) con los campos esperados y sin correo ni teléfono en claro; una segunda agenda del mismo correo en 30 días no envía; sin token no envía; un error HTTP no cambia la respuesta de `/leads`; `/followup-meetings` no envía.
- **Con Meta:** con el código de «Probar eventos», una agenda de prueba desde la web aparece **una vez** (píxel + servidor deduplicados) y una por WhatsApp aparece como `chat`. Se hace con el OK de Luis, con un correo de prueba de AT, y la cita de prueba se borra después.

## 8. Despliegue y vuelta atrás

- Solo con el OK de Luis, por SSH, con respaldo, `php -l` y md5, **cotejando cada archivo con PROD** (no con `main`).
- Orden: primero el módulo (`inc/meta-capi/` y el mu-plugin), después `api-endpoints.php` con la línea del `do_action`, después el JS (`at-consentimiento.php`, `footer.php`, `at-home.js`) y purga de caché.
- El envío real empieza recién cuando Luis pega el token en PYS: hasta entonces, todo queda inerte.
- **Vuelta atrás:** borrar el mu-plugin `at-meta-capi.php` (el `do_action` sin oyentes no hace nada) y apagar «Conversion API» en PYS.

## 9. Decisiones tras la revisión del constructor (28-oct, Eje)

Las marcadas *Eje* las puede corregir Luis.

- **Interruptor propio** *(Eje)*: el módulo solo envía si la opción `at_capi_activo` vale `1` **y** hay token en PYS. Nace en `0`. Se enciende después de que Luis apruebe la línea de privacidad y pruebe en «Probar eventos». No depende del interruptor `use_server_api` de PYS, que solo gobierna los eventos del plugin.
- **Correo siempre presente**: `/leads` exige nombre y correo (400 `missing_params` si faltan), así que toda agenda guardada trae correo. La clave de «primera agenda» y el `external_id` son el correo normalizado; dos correos distintos de la misma persona cuentan como dos leads *(Eje: caso raro, se acepta)*.
- **Reagendamientos y segundas agendas en la web** *(Eje)*: además de la marca de 30 días del servidor, el navegador guarda en `localStorage` el hash del correo con su fecha y no vuelve a disparar `atLead` para ese correo en 30 días. Así el píxel y el servidor cuentan la misma «primera agenda». Si el navegador borró su memoria, puede quedar un Lead de más del píxel; se acepta. Los reagendamientos de hoy ocurren por WhatsApp, donde no hay píxel.
- **Formato de fecha y hora para el `event_id`** *(Eje)*: los dos lados normalizan antes del hash a `AAAA-MM-DD` y `HH:MM` (24 h, sin segundos). El constructor verifica en el código qué formato manda el formulario y qué recibe `/leads`; si no coinciden, ajusta la normalización y no toca n8n.
- **Chat web** *(Eje)*: si el chat agenda sin pasar por el formulario que llama `atLead`, esa agenda la cuenta solo el servidor (una vez, sin duplicado). No se agrega `atLead` al chat en esta etapa.

## 10. Lo que queda al constructor

- La versión exacta de la Graph API (la que use el SDK de PYS 11.4.4) y el formato final del log.
- Verificar en la documentación de Meta qué campos exige cada `action_source` (en particular si `website` exige `client_user_agent` y `event_source_url`, que el servidor no tiene porque la agenda llega desde n8n). Si los exige, mandar las agendas web por el servidor con el `action_source` que Meta acepte sin esos campos, o pasar el navegador por n8n, y anotar la decisión.
- Dónde guardar la marca de 30 días (transient u opción) y su nombre.
