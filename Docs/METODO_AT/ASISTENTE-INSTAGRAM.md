# Asistente de mensajes directos de Instagram

Guía única. Diseño: [`superpowers/specs/2026-10-22-asistente-instagram-design.md`](../superpowers/specs/2026-10-22-asistente-instagram-design.md)
(con los ajustes del 24-oct). Flujos de n8n: [`N8N/instagram/README.md`](../../N8N/instagram/README.md). Rama `claude/asistente-ig-directos`,
que parte de `claude/ig-comentarios-palabra-clave`: esa rama es la que está en PROD para los archivos del Portal, no `main`.

## Qué hace

Responde los directos de @automatizatech.cl las 24 horas, con el criterio del agente web («Tech»), en español de Chile y en texto corto.

- **Agenda** el diagnóstico gratis en el mismo chat: consulta la agenda real, pide nombre, correo y WhatsApp con código de país (obligatorio, para recordatorios y seguimiento; ejemplo «en Chile: +56 9 1234 5678»), confirma
  día, hora y correo, y agenda solo después del «sí». La cita sale por el mismo sub-workflow que la web y WhatsApp: Google Meet,
  lead en el CRM con origen **Instagram** y correo de confirmación.
- **Deriva a una persona** cuando la piden, hay un reclamo, es un cliente que pide seguimiento o piden algo a medida. Se despide
  («Le avisé a Luis, te va a responder por aquí mismo»), deja de responder en esa conversación y llega un correo
  «Instagram: <nombre> quiere hablar contigo» a lgonzalez@automatizatech.cl y lmgm.030@gmail.com.
- **Sabe de dónde viene la persona**: si comentó una palabra clave (MÉTODO, SPA…), el asistente recibe la palabra y el privado que
  le enviamos, y entiende que «quiero agendar» responde a «agenda tu diagnóstico gratis».
- Si mandan varios mensajes seguidos, espera 6 segundos y responde una vez a todos. Audios, fotos y stickers: «Por ahora leo solo texto».

## Para Luis

- **La conversación derivada** queda asignada a Luis (ejecutivo «Luis González» del cliente 1) en el Portal, en modo humano. Respondes
  por el Portal o por la app de Instagram. Instagram solo deja responder dentro de las 24 horas desde el último mensaje de la persona.
- **Devolverla al asistente:** «Liberar» en el Portal. Además vuelve sola si la persona escribe de nuevo después de 24 horas sin
  actividad, **solo** si la había derivado el asistente: las que toma a mano un ejecutivo siguen con él.
- **Las conversaciones que ya atiende una ejecutiva** (las 5 y 7) no cambian.
- **Costo:** gpt-4o-mini, del orden de US$0,0005 por mensaje respondido (medido en las pruebas del 24-oct). Avisar a Balance al cerrar el primer mes.

## Cambios en el Portal (Portal OmniCliente y tema)

| Archivo | Qué |
|---|---|
| `omnichannel-derivacion.php` (nuevo) | Funciones puras: configuración de la derivación, vuelta al bot, contexto del comentario y correo. |
| `omnichannel-controller.php` | Callback `action = 'escalate'` (`escalar_desde_bot`); descarte de respuestas del bot en conversaciones humanas de Instagram; bloque `contexto` en el aviso a n8n; vuelta al bot tras 24 h; `takeover_conversation(..., $notify)` para no mandar el correo genérico en la derivación. |
| `wp-content/themes/automatiza-tech/inc/lead-origen.php` (nuevo) | `automatiza_tech_lead_origen()`: `session_id` con `ig:` = Instagram. |
| `wp-content/themes/automatiza-tech/inc/api-endpoints.php` | `/leads` usa esa función (antes, la misma regla de WhatsApp en línea). |

Configuración por canal (sin migración): `bot_configs.custom_functions_json.derivacion = {"agente_id": N, "correos": [...]}`, al lado de `palabras_clave`.

## Pruebas

```bash
php tests/omnicanal/derivacion-puras-test.php                                   # 42: funciones puras y origen del lead
AT_WP_LOAD=<wp-load.php local> php tests/omnicanal/derivacion-wp-test.php       # 31: escalate, descarte, contexto y vuelta al bot
AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/rutas-http-wp-test.php     # 32: por HTTP real, con contexto y escalate
python N8N/instagram/probar.py                                                  # 23 comprobaciones del flujo, en modo prueba
```

Regresión en verde el 24-oct: `tests/ig-comments/*` (73 + 32 + 10 + 9), `tests/ig-insights/*` (80 + 28) y `tests/omnicliente/secretos-canales-wp-test.php` (39).

**No probado:** la agenda real de punta a punta (escribe en PROD: Calendar, CRM y correo; va en la prueba real con Luis) y el
correo de derivación entregado de verdad (en local `wp_mail` está bloqueado).

## Despliegue a PROD (solo con el OK de Luis)

Script fuera del repo: `~/respaldos/deploy-scripts/2026-10-24-asistente-instagram/desplegar.py`. Sube el contenido de git (LF).

| Orden | Local | Destino en PROD (`domains/automatizatech.cl/public_html/`) | Clase |
|---|---|---|---|
| 0 | — | `desplegar.py cotejar`: controlador `2ab78087`, `api-endpoints.php` `80952740` (24-oct). Si cambió, parar. | OBLIGATORIO |
| 1 | `omnichannel-derivacion.php` | `omnichannel-derivacion.php` (nuevo) | OBLIGATORIO, **antes que 3**: el controlador lo carga; sin él, error fatal en el Portal y en el webhook |
| 2 | `wp-content/themes/automatiza-tech/inc/lead-origen.php` | misma ruta (nuevo) | OBLIGATORIO, **antes que 4** |
| 3 | `omnichannel-controller.php` | `omnichannel-controller.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/api-endpoints.php` | misma ruta | OBLIGATORIO |
| 5 | — | `desplegar.py codigo` hace 1–4 con respaldo, `php -l`, md5 y humo (callback 400, `/leads` 400 `missing_params`, disponibilidad 200); si falla, restaura | — |
| 6 | — | `desplegar.py datos`: respalda la fila de `bot_configs` del canal 2, crea el ejecutivo «Luis González» (rol `agent`, sin contraseña) y escribe `derivacion` | OBLIGATORIO |
| 7 | — | `desplegar.py conectar`: activa los flujos de herramientas y principal, y escribe `n8n_webhook_url` del canal 2 | OBLIGATORIO |
| 8 | — | Prueba real desde otra cuenta de Instagram (abajo) | OBLIGATORIO |

**No subir:** `tests/`, `Docs/`, `N8N/`, `graphify-out/`. `functions.php` no se toca.

**Rol del ejecutivo:** `agent` y no `supervisor`, porque los supervisores reciben copia de cada asignación del cliente 1 y Luis ya recibe copia oculta de todas.

## Prueba real

Desde una cuenta de Instagram que no sea la de AT: (1) escribir «hola» → responde; (2) «quiero agendar el diagnóstico» → ofrece
horas reales, pide datos, confirma y agenda (la cita de prueba se borra después); (3) «quiero hablar con una persona» → llega el
correo a las dos direcciones y la conversación aparece asignada a Luis; (4) otro mensaje → el asistente ya no responde. Las
conversaciones 5 y 7 siguen asignadas.

## Vuelta atrás

- **Inmediata:** `desplegar.py desconectar` (vacía `n8n_webhook_url` y desactiva los dos flujos). El Portal vuelve a solo mostrar los directos.
- **Código:** restaurar `omnichannel-controller.php` y `api-endpoints.php` desde `~/respaldos/asistente-ig-antes-<fecha>/`. Los dos archivos nuevos pueden quedar.
- **Datos:** la fila de `bot_configs` del canal 2 está en `~/respaldos/asistente-ig-datos-<fecha>/`; el ejecutivo creado se puede desactivar.

## Puesta en marcha (28-oct-2026, con el OK de Luis)

- **09:31:** código del Portal subido (respaldo `~/respaldos/asistente-ig-antes-20261028-0931`). **09:32:** ejecutivo «Luis González» (id 6), `derivacion` y conexión de n8n.
- **Primera prueba real fallida:** la foto de perfil de una cuenta nueva medía 523 caracteres y `contact_avatar_url` admite 500. WordPress rechazaba la conversación y el mensaje quedaba huérfano (`conversation_id = 0`): no se veía en el Portal y n8n lo descartaba. Era un error anterior al asistente. Se corrigió en `1916a8c` (la foto que no cabe no se guarda; sin conversación no se guarda el mensaje ni se avisa a n8n), subido a las 12:15 (respaldo `asistente-ig-foto-antes-20261028-1215`).
- **Mensajes perdidos recuperados:** el mismo error había dejado sin conversación los mensajes de 6 personas desde el 21-jun (21 mensajes). Se crearon sus conversaciones en modo humano, asignadas a Luis, con una nota (16 a 21; respaldo `asistente-ig-recuperar-20261028-1628`). Hoy no queda ningún mensaje huérfano.
- **Cuentas sin bot:** `bot_configs.custom_functions_json.sin_bot` = lista de IGSID (texto o `{igsid, nota}`). Sus mensajes se guardan en el Portal, pero nunca van a n8n. La primera es @luis_miguel33, la cuenta personal de Luis (commit `b6f6cdb`, subido a las 13:32; respaldos `asistente-ig-sinbot-antes-20261028-1332` y `asistente-ig-sinbot-datos-20261028-1632`).
- **md5 en PROD tras el 28-oct:** `omnichannel-controller.php` `0811614c`, `omnichannel-derivacion.php` `f4290fd3`, `inc/api-endpoints.php` `41d46fd6`, `inc/lead-origen.php` `7365a039`.
- **Prompt:** el agente no conoce el Método AT (a «¿En qué consiste su método AT?» respondió que estaba fuera de su área). Lo actualiza Eje en el agente web y lo propaga a Instagram con `aplicar.py`.

## Después

- Clasificar en frío, tibio, caliente o «no califica» y avisar al instante cuando llega uno caliente.
- Audios e imágenes; mensajes después de las 24 horas (etiqueta HUMAN_AGENT de Meta).
