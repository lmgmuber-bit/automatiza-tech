# Agente Tech Web: identificador de sesión de las agendas

Nodo «Limpiar sesión» en «Agente Tech Web (PROD)» (`3A5Y1NPBxpTjhHdE`), entre «Switch Action» (salida `saveLead`) y «Check Availability» (sub-workflow de agenda `PejBgr2LZQSAbbBy`). EN PROD desde el 16-nov-2026, con el OK de Luis.

WordPress decide el canal de una agenda por su `session_id` (`inc/lead-origen.php`): `ig:` es Instagram y `whatsapp` es WhatsApp. Las agendas que entran por la web quedan siempre como `web-<letras, números, - y _>`, sin un `session_id` ni un `source` paralelos. Así el canal de la web no se confunde con otro, ni en la lista de citas ni en la medición (`at_capi_canales`).

- `limpiar-sesion.js`: el código del nodo.
- `limpiar-sesion.test.js`: `node N8N/web-sesion/limpiar-sesion.test.js` (32 comprobaciones, sin red).
- `aplicar.py`: `python -P N8N/web-sesion/aplicar.py --ver` (vista previa) o sin `--ver` (respalda en `~/respaldos/n8n/web-sesion/`, sube, republica si estaba activo y verifica la versión activa). Si el nodo ya existe, solo actualiza su código.
- `N8N/cerebro-at/sincronizar.py` lee el flujo vivo y sube nodos y conexiones completos, así que respeta este nodo.
