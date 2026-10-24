# Portal OmniCliente · Respuestas a comentarios con palabra clave (Instagram)

Guía única del módulo. Diseño aprobado por Luis el 24-oct-2026:
[`superpowers/specs/2026-10-24-ig-comentarios-palabra-clave-design.md`](superpowers/specs/2026-10-24-ig-comentarios-palabra-clave-design.md).
Rama `claude/ig-comentarios-palabra-clave`, que parte de `claude/ig-carrusel-ruta`. Es lo que hay en PROD para estos archivos: el 24-oct
se cotejó el md5 por SSH, en modo lectura. `main` no corresponde a PROD para estos archivos.

## 1. Qué hace

Alguien comenta `MÉTODO`, `SPA`, `CLÍNICA`, `TIENDA`, `LEADS` o `SISTEMA` en un post de @automatizatech.cl (sin importar tildes,
mayúsculas ni emojis; tiene que ser solo la palabra). La cuenta:

1. le responde en público bajo su comentario;
2. le manda la respuesta privada ligada a ese comentario (`recipient.comment_id`);
3. deja la conversación en el inbox del Portal, en modo bot, con una nota «Comentó «MÉTODO» en una publicación» y el mensaje enviado.

Si la persona contesta por mensaje directo, entra a esa misma conversación por el camino de siempre (y al asistente, cuando esté conectado).

**Reglas:**
- cada comentario se responde una sola vez (registro con `comment_id` único);
- se ignoran los comentarios de la propia cuenta, incluidas sus propias respuestas;
- solo se responden comentarios posteriores a `activo_desde` y de menos de 7 días;
- tope de 3 respuestas por persona en 24 horas;
- una privada fallida se reintenta hasta 3 veces;
- el privado nunca se manda por IGSID;
- el aviso del webhook no se cree a ciegas: el comentario se relee en Meta (`GET /<comment_id>`) y se decide con el texto, el autor
  y la hora que dice Meta. Sin autor, o sin poder identificar la cuenta propia, no se responde. Hasta 20 comentarios por aviso.

## 2. Piezas

| Archivo | Qué |
|---|---|
| `omnichannel-ig-comments-controller.php` (nuevo) | Clase `OmniIgCommentsController`: reglas, reserva, respuestas, inbox, registro, consulta del plan B. Crea la tabla `{prefix}omnichannel_ig_comment_replies` con `dbDelta` (opción `omni_ig_comments_db_version`). |
| `omnichannel-controller.php` | `reply_instagram_comment()` y `send_instagram_private_reply()` sobre `ig_api_request()`; `record_instagram_echo()` para los ecos (§2.1). |
| `webhook-omnichannel.php` | Un aviso de Instagram con `changes[].field = comments` va al controlador nuevo; los mensajes directos siguen igual. Responde 200 antes de trabajar si el servidor tiene `litespeed_finish_request`/`fastcgi_finish_request`. |
| `api-omnichannel.php` | `POST ig-comments/poll` (plan B, cabecera `X-IG-Comments-Secret` = `AT_IG_COMMENTS_SECRET`; sin la constante responde 503) y `GET admin/ig-comments` (registro, sesión de admin). |
| `tools/ig-palabras-clave-config.php` | Guarda o apaga la clave `palabras_clave` de `bot_configs.custom_functions_json` con respaldo de la fila, sin tocar otras claves. |
| `tools/ig-ecos-auditoria.php` | Cuenta (y con `--aplicar` archiva, con respaldo) las conversaciones a nombre de la propia cuenta que dejaron los ecos viejos (§2.1). |

### 2.1 Ecos de Instagram (`is_echo`)

Lo que envía la cuenta (desde el Portal, desde la app de Instagram o la respuesta privada a un comentario) vuelve por el webhook de
mensajes con `is_echo: true`. Hasta este cambio, `normalize_instagram()` lo tomaba como un mensaje **entrante** cuyo contacto era la
propia cuenta. Medido en PROD el 24-oct: una de las 3 conversaciones del canal 2 está a nombre de la cuenta. Con el asistente de directos
conectado, si esa conversación volvía al bot, el asistente le habría respondido a la propia cuenta.

Ahora un eco lo registra `record_instagram_echo()`:

- si ya está registrado (mismo `mid` que guardó el Portal o la respuesta privada), no se repite;
- si llega antes de que el Portal guarde el `mid` (mismo texto, saliente reciente sin `mid`), se le asigna a ese mensaje;
- si no, queda como saliente del ejecutivo («Instagram (app)») en la conversación del destinatario;
- si no hay conversación con el destinatario, se ignora: la abre el primer mensaje de la persona;
- nunca va a n8n ni cambia el estado de la conversación.

Lo que no cambia: una respuesta escrita desde la app del celular **no** pasa la conversación a modo humano; si está en bot, el
asistente sigue respondiendo. Tomar el control sigue siendo desde el Portal.

La conversación que ya existe a nombre de la cuenta no se puede repartir entre las personas, porque nunca se guardó a quién iba cada
mensaje. La propuesta es archivarla con `tools/ig-ecos-auditoria.php` (paso 6b): no borra nada y se deshace volviendo al status que
guarda el respaldo.

**Configuración** (por canal): `bot_configs.custom_functions_json` → `palabras_clave` =
`{activo, activo_desde, max_por_persona_24h, palabras: [{palabra, publica, privada}]}`. Los textos vienen de la bóveda
(`10-Projects/2026-10-20-Redes-AT-Claqueta-Decisiones.md`) y están en el script.

## 3. Lo que dice Meta y el estado de la cuenta (24-oct)

- **Permisos:** `instagram_business_basic` + `instagram_business_manage_comments` (los dos para leer, responder en público y en privado).
  Fuentes: [comment-moderation](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/comment-moderation) y
  [private-replies](https://developers.facebook.com/docs/instagram-platform/private-replies).
- **Webhook:** campo `comments`, con la app en modo Live. Meta exige **Advanced Access** para entregar `comments`
  ([webhooks/setup](https://developers.facebook.com/documentation/instagram-platform/webhooks/setup)).
- **Medido en PROD el 24-oct** (sonda de solo lectura, sin imprimir el token):
  - el token lee comentarios de los posts (`GET <media>/comments` responde bien), así que el permiso de comentarios está;
  - la cuenta tiene suscrito **solo `messages`** (`GET <IG_ID>/subscribed_apps`), y falta `comments`;
  - `custom_functions_json` del canal 2 está vacío.
- **No verificado:** si la app tiene Advanced Access para comentarios. Se ve en el panel (paso 4.1) o en la prueba real: si el comentario
  no llega por el webhook, se activa el plan B.

## 4. Pasos

### 4.1 En Meta (Luis)

1. developers.facebook.com → **Mis apps** → la app de AutomatizaTech con Instagram → **Casos de uso → Administrar mensajes y
   contenido en Instagram → Permisos**. Confirmar que `instagram_business_manage_comments` está agregado y anotar si dice acceso
   estándar o avanzado. La app tiene que estar en modo **Live**.
2. En el mismo caso de uso → **Configuración de la API con inicio de sesión de Instagram → Configurar webhooks**: la URL de devolución
   ya está (los mensajes directos llegan). En la lista de campos, **suscribir `comments`**.
3. Si en el paso 1 faltaba el permiso: **Generar token** junto a @automatizatech.cl, aceptar todos los permisos y pegarlo en el Portal
   (**Canales → Instagram (canal 2) → Editar → Token de Instagram**), como en `OMNICLIENTE-METRICAS-IG.md` §5.1. Con el permiso ya
   presente, no hace falta.

Supuesto sin verificar: el nombre exacto de los botones del panel de hoy.

### 4.2 Suscripción de la cuenta (Claude, con el OK de Luis)

`POST graph.instagram.com/<versión>/<IG_ID>/subscribed_apps?subscribed_fields=messages,comments`, con el token del canal desde el
servidor (no se imprime). Se mandan los dos campos, porque la llamada reemplaza la lista: sin `messages`, se cortarían los mensajes
directos. Se verifica con `GET <IG_ID>/subscribed_apps`.

## 5. Despliegue a PROD (hecho el 24-oct-2026, con el OK de Luis)

**Evidencia (24-oct):**
- Cotejo previo: md5 de PROD igual al de la mañana.
- Respaldo en `~/respaldos/ig-comentarios-antes-20261024-1249/`: los 3 archivos, la fila de `bot_configs` del canal 2 y la conversación 6.
- Los 4 archivos subidos con `php -l` y md5 de git (`540c7608…`, `2ab78087…`, `62ed90c4…`, `2e117c7e…`).
- Verificación desde afuera:
  - `ig-comments/poll` responde 503;
  - `ig-metrics` y `reel-diario/token-check` sin secreto responden 403;
  - `admin/ig-comments` sin sesión responde 403;
  - el webhook sin parámetros responde 400, y con un secreto malo, 403.
- Seis palabras guardadas (texto público «¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩», aplicado a las 13:01).
- Conversación 6, a nombre de la cuenta, archivada.
- La cuenta quedó suscrita a `["messages","comments"]` (antes, solo `["messages"]`); el token del publicador sigue `ok`.
- **Prueba real** con @chachi_morales y la palabra MÉTODO: llegó por el **webhook** (sin acceso avanzado), con la pública y la privada `sent` en el primer intento. Su respuesta por mensaje directo entró a la misma conversación (12) y el eco no se duplicó. **El plan B no hizo falta.**

**Pendiente:**
- Nadie responde automáticamente después del mensaje privado: el canal 2 no tiene flujo de n8n. Es el asistente de mensajes directos, que va en su propia sesión.
- El contador `active_chats` del ejecutivo 2 sigue contando la conversación 6 archivada.

La tabla queda como procedimiento y para la vuelta atrás:

Subir el **contenido de git** (`git show <commit>:<archivo>`, fin de línea LF, igual que PROD), no la copia de trabajo de Windows.

| Orden | Local | Destino en PROD (`domains/automatizatech.cl/`) | Clase |
|---|---|---|---|
| 0 | — | Respaldo de los 3 archivos que se reemplazan en `~/respaldos/ig-comentarios-antes-<fecha>/` y md5 de antes: controlador `d7f282ef…`, API `b8cd2390…`, webhook `a6a24587…` (24-oct). Si el md5 cambió, parar y cotejar. | OBLIGATORIO |
| 1 | `omnichannel-ig-comments-controller.php` | `public_html/omnichannel-ig-comments-controller.php` (nuevo) | OBLIGATORIO, **antes que 2–4**: la API y el webhook lo cargan; sin él, error fatal |
| 2 | `omnichannel-controller.php` | `public_html/omnichannel-controller.php` | OBLIGATORIO |
| 3 | `api-omnichannel.php` | `public_html/api-omnichannel.php` | OBLIGATORIO |
| 4 | `webhook-omnichannel.php` | `public_html/webhook-omnichannel.php` | OBLIGATORIO |
| 5 | — | `php -l` de los 4, md5 contra git; desde afuera: `POST ig-comments/poll` sin secreto → 503, `GET ig-metrics` sin secreto → 403, `POST webhook-omnichannel.php` sin parámetros → 400 JSON | OBLIGATORIO |
| 6 | `tools/ig-palabras-clave-config.php` | `~/ig-palabras-clave-config.php` (fuera de `public_html`): primero sin `--aplicar`, después `--wp=<home>/domains/automatizatech.cl/public_html/wp-load.php --host=automatizatech.cl --canal=2 --aplicar --respaldo=<carpeta del paso 0>/bot_config_canal2.json` | OBLIGATORIO |
| 6b | `tools/ig-ecos-auditoria.php` | `~/ig-ecos-auditoria.php`: primero sin `--aplicar` (solo cuenta, no muestra contenido); con el OK de Luis, `--wp=... --host=automatizatech.cl --canal=2 --aplicar --respaldo=<carpeta del paso 0>/ecos-canal2.json`. El respaldo trae los mensajes: borrarlo al verificar. | OPCIONAL (recomendado) |
| 7 | — | Pasos 4.1 (Luis) y 4.2 (con su OK) | OBLIGATORIO |
| 8 | — | Prueba real (§6) | OBLIGATORIO |
| 9 | — | **Plan B**, solo si en la prueba el comentario no llega por el webhook: `AT_IG_COMMENTS_SECRET` en `public_html/wp-config.php` (antes de `wp-settings.php`), credencial de n8n *Header Auth* `X-IG-Comments-Secret` con el mismo valor y un flujo n8n que cada 5 minutos hace `POST api-omnichannel.php?route=ig-comments/poll`. Cambio en n8n de PROD: con el OK de Luis. | OPCIONAL |

**No subir:** `tests/`, `Docs/`, `graphify-out/`. La tabla nueva se crea sola con la primera llamada a la API o al webhook.

## 6. Prueba real

Con una cuenta de Instagram que **no** sea la de AT, comentar `MÉTODO` en un post de AT. Evidencia:

- captura de la respuesta pública bajo el comentario;
- captura del mensaje privado en la cuenta de prueba;
- la fila en `GET admin/ig-comments` (o en la tabla) con `status = replied`;
- la conversación en el inbox con la nota y el mensaje.

Comentar otra vez lo mismo en otro post debe responder de nuevo (hasta 3 por día). Un comentario que no es la palabra («qué bueno») no
se responde.

## 7. Vuelta atrás

- **Inmediata, sin subir nada:** `php ~/ig-palabras-clave-config.php ... --canal=2 --aplicar --apagar --respaldo=<archivo nuevo>`
  (deja `activo: false`; el webhook sigue recibiendo y no responde).
- **Completa:** restaurar los 3 archivos del respaldo del paso 0. El controlador nuevo y la tabla pueden quedar: sin la API y el
  webhook nuevos nadie los llama.
- Quitar `comments` de la suscripción en Meta si se quiere cortar del todo.

## 8. Pruebas locales

```bash
AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/ig-comments-wp-test.php    # 73 casos: reglas, reserva, reintentos, inbox, plan B, avisos falsificados, token
AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/rutas-http-wp-test.php     # 27 casos por HTTP real con php -S: webhook, ecos, n8n y rutas
AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/config-script-wp-test.php  # 10 casos del script de configuración
AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/ecos-auditoria-wp-test.php  # 9 casos del script de los ecos viejos
```

Todas usan el WordPress local y la Graph API simulada: ninguna llamada sale a Meta. Con este cambio también pasan
`tests/ig-insights/*` (80 + 28) y `tests/omnicliente/secretos-canales-wp-test.php` (39).

## 9. Después

- Clasificar a quien escribe en frío, tibio, caliente y «no califica», y avisar de inmediato cuando llega uno caliente: se cuelga de
  `conversation_id` del registro.
- Una pestaña del Portal para editar las palabras (hoy: script o ruta de configuración del bot).
