# Recordatorios y alertas de WhatsApp con plantillas de Meta (2026-09-24)

## Qué pasaba

Los recordatorios de citas (72h, 24h, 1h), los de seguimiento (8 AM, 8 PM), el aviso de reunión agendada (Followup) y las alertas de ARGOS mandaban mensajes `interactive` o `text`. Meta solo entrega un mensaje que no es plantilla si el cliente escribió al número en las últimas 24 horas. Un lead que agenda por la web nunca lo hizo, así que **nunca le llegó un recordatorio de WhatsApp**.

- Meta responde `200` con `wamid` y **3 segundos después** manda al webhook un status `failed` con **131047 "Re-engagement message"**. El bot principal no procesa `statuses`, n8n marca `success` y WordPress anota el recordatorio como enviado: nadie se entera.
- Caso real del 23-sep: un lead que agendó por la web (`source=web`) recibió los recordatorios por correo y ninguno por WhatsApp; los dos envíos de WhatsApp volvieron `failed` 131047 a los 3 segundos.
- Git muestra que estos flujos **nunca** usaron plantillas (`text` en dic-2025, `interactive` desde ene-2026). "Antes funcionaba" porque en **enero los 6 leads agendaron por el bot de WhatsApp** (uno confirmó con el botón). Desde febrero todos llegan por la web.
- Las alertas de ARGOS a Luis también fallaban con 131047: solo le llegaba el correo.

## Qué se hizo

| Pieza | Estado (verificado leyendo la versión publicada) |
|---|---|
| Bot principal `bBcNlFgBzQ0766Mq`: entiende el botón de plantilla (`type: "button"`, `button.payload`) además del `interactive` | **En PROD** desde el 24-sep; retrocompatibilidad probada |
| 9 plantillas Utility en Meta (tabla abajo) | **Todas APPROVED** el 25-sep, dentro de las 24 h que da Meta |
| Recordatorios de citas 72h / 24h / 1h | **En PROD** desde el 25-sep 16:42 UTC |
| Seguimiento 8 AM, 8 PM y Followup | **En PROD** desde el 25-sep 17:13 UTC |
| ARGOS | **En PROD** desde el 25-sep 18:25 UTC (18:28: se quitó el `continueOnFail` heredado de "Buscar Historial BD", que chocaba con el `onError` nuevo) |

`n8n_validate_workflow` sobre los siete flujos: todos válidos, 0 errores. Queda un aviso heredado en el Followup ("Mark WA Sent in WP" usa `continueOnFail`), un nodo que este cambio no toca. En PROD ningún flujo había enviado todavía un mensaje con plantilla al cerrar el 25-sep: las primeras ejecuciones no tenían citas en la ventana.

| Plantilla | ID en Meta | Flujo |
|---|---|---|
| `recordatorio_cita_72h` | 1269500156254629 | WhatsApp Recordatorio 72h (`KFqFTgc0o4bOe0xv`) |
| `recordatorio_cita_24h` | 3808226865984791 | WhatsApp Recordatorio 24h (`cbFi7tpGGn7pEDnr`) |
| `recordatorio_cita_1h` | 980638938396901 | WhatsApp Recordatorio 1h (`ljNN7NedbUHzytwY`) |
| `recordatorio_seguimiento_hoy` | 2040023340051108 | Seguimiento 8 AM (`MchisrkbuXDOqKvQ`) |
| `recordatorio_seguimiento_manana` | 2699521603852344 | Seguimiento 8 PM (`uU39aWmvJ4gGj8T2`) |
| `aviso_demo` | 1453766079930695 | Followup WhatsApp Send (`daSz1OJSeaQckDVy5q0uS`) |
| `aviso_reunion_prospecto` | 1641335567335978 | Followup WhatsApp Send |
| `aviso_reunion_seguimiento` | 1089178533524186 | Followup WhatsApp Send |
| `alerta_argos` | 1132521559213596 | Argos detección de Errores (`p7ISUf0J5GycHscc`) |

Cuenta de WhatsApp Business `1294451839384217` ("AT Soluciones"), número `946501128543201`.

## Aplicar y revertir

```
python aplicar_recordatorios.py            # REVISAR: solo lee y muestra qué haría
python aplicar_recordatorios.py aplicar    # escribe en PROD, pero solo los flujos con su plantilla APPROVED
```

- Es idempotente y respalda cada workflow antes de escribirlo en `respaldos-antes-del-cambio/`, carpeta **ignorada por git**: este repo es público y esos JSON traen rutas de webhook y teléfonos. Después verifica la **versión publicada**, no el borrador.
- Los respaldos de este cambio están en la bóveda privada: `AI-Memory-Vault/90-Archive/respaldos-n8n/2026-09-24-wa-plantillas/`, con una nota que dice qué es cada uno.
- Antes de tocar ARGOS comprueba que su "Preparar Datos" sigue igual a `codigo-nodos/preparar_datos_actual.js`; si alguien lo cambió, no lo toca.
- Usa la API de n8n (clave en `~/.claude.json`, nunca en el repo) y la credencial "WhatsApp Tech" de n8n mediante workflows temporales que se borran siempre. Ningún script contiene secretos.
- Para revertir: `PUT /api/v1/workflows/{id}` con el JSON del respaldo de la bóveda (sin `timeSavedMode`, `availableInMCP` ni `binaryMode` en `settings`) y luego `POST /activate`.

Pruebas locales (Node, sin tocar n8n): `node pruebas/test_builds.js` (12), `test_prepares.js` (10), `test_argos.js` (4), `test_argos_freno.js` (3). `node pruebas/test_bot_expresiones.js <bot_publicado.json> [respaldo_previo.json]` compara el bot publicado con su respaldo de antes del cambio (por defecto, el de la bóveda).

## Decisiones de Luis (24-sep)

- **Siempre plantilla.** Meta no cobra la plantilla Utility dentro de la ventana de 24 h ("Utility templates delivered within an open customer service window are free"), así que mezclar con `interactive` no ahorra nada. Fuera de la ventana cuesta ~US$0,02 en Chile según Sleekflow (no es la tabla oficial).
- Textos aprobados uno por uno; los botones van **sin emoji** porque Meta los rechaza (error 100/2388060).
- **ARGOS: WhatsApp una sola vez por error; correo siempre.** "El mismo error" = mismo workflow, nodo y primeros 50 caracteres del mensaje en 30 días (`automatiza_search_similar_errors()`).
- Botones del Followup: la demo usa las acciones de lead del bot (`btn_reminder_confirm_`, `btn_reprogramar_lead_`, `btn_cancelar_lead_`); el prospecto, `btn_followup_*`. Antes `btn_demo_*` y `btn_prospect_*` no tenían ruta y no hacían nada.

## Cambios por flujo

- **Citas 72h/24h/1h:** el nodo Build arma la plantilla; se agrega un freno: si Meta rechaza el envío, el lead se marca igual (no se reintenta cada 15–30 min) y un Stop and Error avisa **una vez** a ARGOS.
- **8 AM, 8 PM, Followup:** solo cambian el nodo que prepara los datos y el `jsonBody` del Send. No necesitan freno: corren una vez al día sobre citas de hoy o mañana, o responden el error a WordPress.
- **ARGOS:** plantilla con el mensaje recortado a ≤1000 caracteres (el correo y la base guardan el análisis completo), nodo "¿Primera vez de este error?", y dos bugs corregidos: "Buscar Historial BD" ahora también lee `trigger.error` (antes el freno nunca habría frenado una tormenta de fallos de trigger como la del 6-sep), y un fallo del WhatsApp ya no corta el correo.

## Cosas a saber

- El estado de la cuenta de WhatsApp Business (verificación, límites de envío, facturación) está en la nota privada de la bóveda, junto a los respaldos.
- Comportamiento heredado del bot: **cancelar una demo la borra sin preguntar**; **reagendar borra la cita antes** de mostrar el calendario.
- Los fallos que Meta avisa **después** del envío (webhook `statuses`) siguen sin registrarse. Hacerlo con ARGOS arma un bucle (el aviso de ARGOS fallaría y dispararía otro): hay que excluir los mensajes a Luis y avisar por correo.

## Trampas encontradas al aplicar

- **`binaryMode` también rebota en el PUT** (400 `settings must NOT have additional properties`, sin escribir nada). El 72h y el 24h la tienen. El aplicador la filtra junto con `timeSavedMode` y `availableInMCP`, y verifica que los ajustes queden idénticos después (el servidor conserva los omitidos).
- **`continueOnFail` y `onError` a la vez** los marca como error el validador. Si un nodo ya traía `continueOnFail`, hay que quitarlo al poner `onError` (el aplicador ya lo hace).
- **Un corte de red** (`getaddrinfo failed`) detuvo la espera automática el 25-sep a las 17:44 UTC. La petición no salió del equipo; se comprobó que ARGOS no quedó a medias y se reintentó.

## Pendiente

1. Prueba de punta a punta: una cita por la web con el número de Luis y tocar "Sí, confirmo"; verificar en el webhook de statuses que el mensaje quedó **entregado** y en el CRM que se registró la confirmación.
2. Registrar los `statuses` `failed` con la exclusión anti-bucle.
3. Pendientes de la cuenta de Meta, de Luis: ver la nota de la bóveda.

Fuentes: [precios de Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing), [revisión de plantillas (hasta 24 h)](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-review), [botón de plantilla en el webhook](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages/button), [límites de mensajería](https://developers.facebook.com/docs/whatsapp/messaging-limits/), [health status](https://developers.facebook.com/documentation/business-messaging/whatsapp/support/health-status/), [códigos de error](https://developers.facebook.com/documentation/business-messaging/whatsapp/support/error-codes), [precios por país (Sleekflow)](https://sleekflow.io/blog/whatsapp-business-price).
