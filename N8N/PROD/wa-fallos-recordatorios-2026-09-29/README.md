# Aviso a Luis cuando un recordatorio de WhatsApp no se entrega (2026-09-29)

**EN PROD desde el 29-sep, con autorización de Luis.**

- **08:13 (hora de Chile):** el PHP (commit `57800e3`). Respaldo en el servidor: `~/respaldos/wa-fallos-antes-20260929-081312.tar.gz`.
- **08:14:** los 6 flujos. Respaldos en la bóveda privada: `90-Archive/respaldos-n8n/2026-09-29-wa-fallos-recordatorios/`.
- **Verificado:**
  - Las huellas de los 3 archivos coinciden, `php -l` pasa con el PHP de PROD (8.3.33) y el sitio y `wp-json` responden 200.
  - Las rutas `whatsapp-envio` y de estados responden 401 sin clave.
  - En cada flujo, la versión publicada solo cambió en la rama nueva, y `n8n_validate_workflow` da 0 errores en los 6.
  - `debug.log` no tiene errores nuevos.
- **Prueba real autorizada por Luis (08:15):** un wamid falso de la cita 2000000000, que no existe, anotado y marcado `failed` (131026).
  - WordPress guardó la anotación y la marca de «ya avisado».
  - No registró fallo del correo en `debug.log`.
  - **A Luis le llegó el correo** con el asunto «El cliente de la cita 2000000000 no recibió el recordatorio de WhatsApp» (lo confirmó él el 29-sep): la cadena funciona de punta a punta.

## Qué resuelve

Desde el 25-sep los recordatorios de WhatsApp salen con plantillas de Meta (`N8N/PROD/wa-plantillas-2026-09-24/`, en `main`). Aun así, Meta puede aceptar un envío (`200` + `wamid`) y no entregarlo, por ejemplo porque el número no tiene WhatsApp (131026). Ese fallo solo llega después, por el webhook del bot.

Desde el 27-sep (Task 19 del cierre de cliente) el bot reenvía todos los `failed` a WordPress, pero WordPress solo avisaba los de las propuestas y descartaba el resto. **Decisión de Luis del 29-sep:** el mismo camino para los recordatorios.

## Cómo funciona

1. Cada flujo, justo después de enviar, llama a `POST at/v1/whatsapp-envio` con `{wamid, tipo, id}`. La llamada usa la credencial de cabecera "AT REST Secret (header)" de n8n, la misma del bot, y WordPress guarda el envío 7 días.
2. Cuando llega un `failed` de ese `wamid` a `at/v1/propuesta-whatsapp-estado`, sale **un correo a Luis** con el cliente, la cita, el teléfono, el motivo en simple y un enlace a la cita en el panel. Sale una sola vez por `wamid`: un candado con `add_option()` y una marca de 30 días lo impiden repetir.
3. Si el `failed` le gana a la anotación, la ruta de estados lo deja en espera 15 minutos, con el mecanismo de la Task 19, y el aviso sale al anotar.
4. El recordatorio **no se reintenta ni se desmarca**: marcarlo como no enviado haría que su flujo lo mandara de nuevo en cada pasada.
5. Los WhatsApp de ARGOS a Luis no se anotan. Por eso un fallo de esos nunca dispara otro aviso y no hay bucle.

| `tipo` | Tabla del `id` | Flujo |
|---|---|---|
| `cita_72h`, `cita_24h`, `cita_1h` | `automatiza_leads` | WhatsApp Recordatorio 72h / 24h / 1h |
| `seguimiento_8am`, `seguimiento_8pm` | `automatiza_followup_meetings` | Seguimiento 8 AM / 8 PM |
| `reunion_demo` | `automatiza_leads` | Followup WhatsApp Send (demo) |
| `reunion_prospecto`, `reunion_seguimiento` | `automatiza_followup_meetings` | Followup WhatsApp Send |

## Archivos

- **WordPress:**
  - `inc/cierre-cliente/whatsapp-recordatorios.php` es nuevo.
  - `inc/cierre-cliente/whatsapp.php` suma unas líneas en la ruta de estados y corrige comentarios que decían que los recordatorios se descartan.
  - `inc/cierre-cliente/cargar.php` suma una línea que carga el archivo nuevo.
- **n8n:** `aplicar_anotar_envio.py` agrega el nodo "Anotar envío en WP" a los 6 flujos.
  - El nodo va en una rama propia que termina ahí, con `onError: continueRegularOutput` y `neverError`: si WordPress no responde, el recordatorio sigue igual que hoy.
  - En 8 AM y 8 PM, que recorren las reuniones de a una con una espera de 5 s, va **arriba** de "Mark as Sent" para correr primero en cada vuelta (orden de ejecución v1: de arriba hacia abajo).
  - En el Followup va **después** de "Respond Success", para no demorar la respuesta a WordPress.
  - Sin argumentos solo lee. Con `aplicar` respalda, escribe, publica y verifica la versión publicada. Se niega a escribir si la ruta de WordPress todavía da 404.
- **Pruebas:**
  - `tests/cierre/whatsapp-recordatorio-wp-test.php`: 46 comprobaciones, en el WordPress local de la Task 0 del plan del cierre.
  - `pruebas/test_aplicar_anotar.py`: 71 comprobaciones, sin red. Evalúa las expresiones en Node y compara los tipos con los del PHP.
  - Regresión: `whatsapp-estado-wp-test.php` pasa sus 83. Tres suites del cierre (contrato, visor de firma y plantilla del contrato) fallan igual sin este cambio, sobre la base `d135e5c`.

## Orden de despliegue

1. Subir el PHP en este orden (`cargar.php` exige el archivo nuevo; subido antes, todo WordPress da error fatal):

   | Archivo local | Destino en PROD (`public_html/`) | Clasificación | Orden |
   |---|---|---|---|
   | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | mismo | OBLIGATORIO | 1 |
   | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | mismo | OBLIGATORIO | 2 |
   | `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php` | mismo | OBLIGATORIO | 3 |

   No se suben `tests/`, esta carpeta ni `respaldos-antes-del-cambio/`.
2. Comprobar desde afuera que `POST /wp-json/at/v1/whatsapp-envio` sin clave responde 401 (la ruta existe) y que el sitio sigue respondiendo.
3. `python aplicar_anotar_envio.py aplicar`, y después `n8n_validate_workflow` sobre los 6 flujos.

## Revertir

- **n8n:** borrar el nodo "Anotar envío en WP" de cada flujo, que es una rama que termina en él, o hacer `PUT` con el respaldo de la bóveda (sin `timeSavedMode`, `availableInMCP` ni `binaryMode` en `settings`) y `POST /activate`.
- **WordPress:** volver a poner `whatsapp.php` y `cargar.php` desde el respaldo. `whatsapp-recordatorios.php` puede quedarse, porque sin la línea de `cargar.php` no se carga.

Los respaldos quedan en la bóveda privada, no en este repo, porque es público.
