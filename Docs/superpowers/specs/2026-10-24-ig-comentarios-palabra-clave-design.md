# Respuestas automáticas a comentarios con palabra clave en Instagram (diseño)

**Fecha:** 24-oct-2026 · **Pedido:** plan de 30 días de Instagram aprobado por Luis el 24-oct (cada reel y carrusel cierra con «comenta PALABRA»).
**Encaja con:** el asistente de mensajes directos (`2026-10-22-asistente-instagram-design.md`, rama `claude/asistente-instagram`).

## Qué pasa

1. Alguien comenta `MÉTODO` (o `metodo 🙌`, `Método!`) en un post de @automatizatech.cl.
2. La cuenta le responde en público bajo su comentario («¡Te lo enviamos por mensaje! 📩»).
3. Acto seguido le llega un mensaje privado ligado a ese comentario con lo prometido.
4. En el Portal aparece la conversación con esa persona: una nota «Comentó MÉTODO en una publicación» y el mensaje enviado. Si la persona contesta, el mensaje entra a esa misma conversación y sigue el asistente de mensajes directos (cuando esté conectado) o una persona.

## Lo que dice Meta (documentación vigente, revisada el 24-oct)

| Tema | Dato | Fuente |
|---|---|---|
| Permisos | `instagram_business_basic` + `instagram_business_manage_comments` (comentarios, respuesta pública y respuesta privada) | [comment-moderation](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/comment-moderation), [private-replies](https://developers.facebook.com/docs/instagram-platform/private-replies) |
| Respuesta pública | `POST graph.instagram.com/<versión>/<COMMENT_ID>/replies` con `{"message": "..."}` | comment-moderation |
| Respuesta privada | `POST graph.instagram.com/<versión>/me/messages` con `{"recipient":{"comment_id":"..."},"message":{"text":"..."}}`; **una sola** por comentario y dentro de **7 días**; el mensaje lleva un enlace al post | private-replies |
| Webhook | campo `comments`: `entry[].changes[]` con `field = "comments"` y `value = {id, from:{id, username}, text, media:{id, media_product_type}}` | [webhooks/examples](https://developers.facebook.com/documentation/instagram-platform/webhooks/examples) |
| Suscripción | en el panel de la app (Webhooks → Instagram → `comments`) y en la cuenta con `POST /<IG_ID>/subscribed_apps?subscribed_fields=...` | [webhooks/setup](https://developers.facebook.com/documentation/instagram-platform/webhooks/setup) |
| 🔴 Requisito | «Advanced Access is required for `comments` and `live_comments` notifications», y la app debe estar en modo Live | webhooks/setup |

**Riesgo principal:** si la app de AT no tiene Advanced Access para `instagram_business_manage_comments`, Meta no entrega el webhook de comentarios aunque el permiso esté en el token. Pedir Advanced Access es una revisión de la app (App Review) que toma días. Por eso el diseño tiene un **plan B por consulta periódica** que funciona con el permiso en acceso estándar, porque la cuenta es de la dueña de la app (supuesto: se comprueba con la sonda de lectura del paso 0).

## Diseño

Todo vive en el Portal OmniCliente (PHP), no en n8n: el webhook ya llega al Portal, el token está en la base, la detección de la palabra es determinista y no necesita IA. n8n solo entra en el plan B como reloj.

### Archivo nuevo `omnichannel-ig-comments-controller.php` (clase `OmniIgCommentsController`)

Mismo patrón que `omnichannel-ig-insights-controller.php`: recibe el controlador principal, crea su tabla con `dbDelta` según una versión guardada en opciones, y llama a Meta solo a través del controlador (el token nunca sale del servidor).

- **`handle_webhook($channel_id, $payload)`:** recorre todos los `entry[].changes[]` con `field = comments` y pasa cada uno a `process_comment()`.
- **`poll($channel_id)` (plan B):** lee las publicaciones de los últimos 7 días y sus comentarios nuevos, y pasa cada uno a `process_comment()`. Es el mismo camino que el webhook, así que las reglas son una sola.
- **`process_comment($channel_id, $comment)`**, en este orden:
  1. **Configuración** del canal (`bot_configs.custom_functions_json`, clave `palabras_clave`). Si no existe o `activo` es falso, no hace nada.
  2. **Propia cuenta:** si `from.id` es el de la cuenta (el `entry.id` o el `user_id` de `/me`) o el usuario es el de la cuenta, se ignora. Así no se responde a sí misma ni entra en bucle con sus propias respuestas.
  3. **Antigüedad:** solo comentarios posteriores a `activo_desde` y de menos de 7 días (lo que permite Meta). Evita que la primera consulta responda comentarios viejos.
  4. **Palabra:** el comentario, sin tildes, mayúsculas, emojis ni signos, tiene que ser **exactamente una** de las palabras configuradas. «Quiero el método» no cuenta.
  5. **Tope por persona:** como máximo `max_por_persona_24h` respuestas (3 por omisión) a la misma persona en 24 horas.
  6. **Reserva atómica:** `INSERT` en la tabla de registro con `comment_id` como clave única. Si ya existía, se termina ahí: nunca se responde dos veces el mismo comentario, aunque Meta repita el aviso o el webhook y la consulta lleguen a la vez.
  7. **Respuesta pública** al comentario y, después, **respuesta privada** con `recipient.comment_id`. Nunca se escribe por IGSID: Meta entrega el privado solo a quien escribió ese comentario. Si la pública falla, igual se intenta la privada (es lo que vale), y se registran las dos por separado.
  8. **Portal:** busca o crea la conversación de esa persona (por el `recipient_id` que devuelve Meta; si falta, el `from.id`), en estado `bot`, y guarda la nota del comentario y el mensaje enviado. Deja un registro en la auditoría.

### Tabla nueva `{prefix}omnichannel_ig_comment_replies`

`comment_id` (único), `channel_id`, `client_id`, `media_id`, `from_igsid`, `from_username`, `keyword`, `comment_text`, `source` (webhook o consulta), `public_reply_id`, `public_status`, `dm_message_id`, `dm_status`, `error`, `conversation_id`, `created_at`, `updated_at`. Es el registro de quién, cuándo y qué palabra. Más adelante la clasificación (frío, tibio, caliente, no califica) se cuelga de `conversation_id`; no se agrega nada por ahora.

### Configuración (por canal, sin código fijo)

En `bot_configs.custom_functions_json` del canal, junto a la clave `derivacion` del asistente:

```json
{"palabras_clave": {
  "activo": true,
  "activo_desde": "2026-10-24T00:00:00-03:00",
  "max_por_persona_24h": 3,
  "palabras": [
    {"palabra": "MÉTODO", "publica": "¡Te lo enviamos por mensaje! 📩", "privada": "Hola, gracias por escribirnos. …"}
  ]}}
```

Las seis palabras y sus textos salen de la tabla de la bóveda (`10-Projects/2026-10-20-Redes-AT-Claqueta-Decisiones.md`). Un script (`tools/ig-palabras-clave-config.php`) escribe la clave sin tocar las demás y respalda la fila antes. Se puede editar también con la ruta que ya existe para la configuración del bot.

### Cambios en archivos que ya existen

- `webhook-omnichannel.php`: si el canal es de Instagram y el aviso trae `changes` con `field = comments`, lo pasa a `handle_webhook()` en vez de `normalize_instagram()`. Los mensajes directos siguen igual.
- `api-omnichannel.php`: ruta `POST ig-comments/poll` (plan B) con su propio secreto `AT_IG_COMMENTS_SECRET` en `X-IG-Comments-Secret`, y `GET ig-comments/log` para ver el registro desde el portal con la sesión de administrador.
- `omnichannel-controller.php`: dos métodos públicos chicos, `reply_instagram_comment()` y `send_instagram_private_reply()`, sobre el `ig_api_request()` que ya existe.

## Paso 0: verificación de lectura en PROD (antes de construir sobre supuestos)

Con un script que no imprime el token: `GET me` (cuenta), `GET <IG_ID>/subscribed_apps` (qué campos tiene suscritos la cuenta hoy) y `GET <media>/comments?limit=1` (si el token puede leer comentarios: si falta el permiso, Meta responde con error). Si falta el permiso o la suscripción, se preparan los pasos para que Luis lo agregue en el panel de Meta; nosotros no tocamos credenciales.

## Pruebas

- **Local, con WordPress local y la API de Meta simulada** (igual que Métricas IG): palabra exacta, con tilde y sin tilde, con emoji, frase que no cuenta, comentario de la propia cuenta, comentario viejo, aviso repetido (una sola respuesta), webhook y consulta a la vez, tope por persona, falla de la pública con privada enviada, falla de la privada, configuración apagada, conversación creada con nota y mensaje, y que el token solo vaya en la cabecera.
- **Por HTTP real** contra `webhook-omnichannel.php` local con un aviso de Meta falso.
- **Prueba real (con el OK de Luis, después del despliegue):** una cuenta de prueba comenta una palabra en un post de AT; evidencia: captura de la respuesta pública, captura del mensaje privado y la fila del registro.

## Despliegue y vuelta atrás

- Solo con el OK de Luis. Respaldo de los tres archivos que se reemplazan, `php -l`, md5 contra la rama, subida del controlador nuevo antes que el webhook y la API, y después el script de configuración con respaldo de la fila.
- Suscribir el campo `comments` (panel de Meta por Luis; en la cuenta, la llamada a `subscribed_apps` conservando los campos que ya tiene).
- **Vuelta atrás inmediata:** `"activo": false` en la configuración. Vuelta atrás completa: restaurar los tres archivos; la tabla nueva queda y no estorba.

## Fuera de esta etapa

- Clasificar frío/tibio/caliente y avisar al instante cuando llega uno caliente (queda previsto vía `conversation_id`).
- Responder con IA comentarios que no son palabra clave, menciones e historias.
- Validar la firma `X-Hub-Signature-256` de Meta (el Portal no guarda el secreto de la app; hoy el webhook se protege con el secreto del canal en la URL).
- Una pestaña del Portal para editar las palabras (por ahora, script o ruta de configuración).
