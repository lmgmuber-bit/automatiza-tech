# Retomar: workflows preparados e inactivos

Se entregan dos workflows según Tasks 4 y 7: `retomar-render.json` y `retomar-reloj.json`. La especificación menciona tres; el reloj y WhatsApp se combinan en un workflow, sin cambiar reglas de negocio. Ambos exports se entregan con `active: false`. No se importaron ni ejecutaron en n8n ni se enviaron mensajes reales.

El webhook de render recibe `{id,generacion,render}` y devuelve la generación en todos los callbacks, incluidos fallos. El UID versionado tiene 57 caracteres (`token-generacion-retomar`); WordPress rechaza con 409 callbacks de una generación anterior o de contenido cambiado. Importar este flujo junto con el PHP de esta entrega: un flujo anterior sin `generacion` no es compatible. Los enlaces externos conservan el wrapper con token estable.

Regenerar desde este directorio con Python 3 sin bytecode:

```powershell
python -B build_retomar_render.py
python -B build_retomar_reloj.py
```

El generador del reloj reutiliza exclusivamente el routing público Graph `v22.0` del nodo `Send WhatsApp` del export local `../PROD/Followup_WhatsApp_Send.json`. Esa versión y el routing no se verificaron en vivo; Eje debe validarlos al desplegar. Requiere el export local para regenerar; no instala dependencias ni lee claves.

Reloj único: lunes a viernes, 10:15 en `America/Santiago` (cron de seis campos `0 15 10 * * 1-5`). Hace POST a `retomar/tick`. El PHP decide elegibilidad, consentimiento y orden; un arreglo WhatsApp vacío termina la rama. Cada elemento produce exactamente una petición Graph con dos parámetros de cuerpo y dos botones URL. El resultado se asocia al `id`/`n` original mediante enlaces `pairedItem` e `itemMatching`, aunque HTTP sustituya el JSON.

Graph usa `onError: continueRegularOutput` y respuesta completa para registrar aceptación (`wamid`) o error HTTP/red en POST `retomar/whatsapp-resultado`. Un fallo `131049` queda registrado y no se reintenta. No hay reintentos automáticos de nodos ni bucles. No reejecutar manualmente una ejecución fallida: podría repetir WhatsApp; revisar el registro en WP con Eje. Si falla el callback, el evento queda pendiente de conciliación, sin reenviar WhatsApp. Los títulos de error se normalizan sin copiar mensajes que puedan incluir tokens/datos personales.

Credenciales existentes, referidas sólo por nombre:

| Nodo | Tipo n8n | Nombre |
| --- | --- | --- |
| Tick / Registrar resultado / Webhook render | httpHeaderAuth | AT REST Secret (header) |
| Enviar WhatsApp | whatsAppApi | WhatsApp Tech |
| Render | httpHeaderAuth | X-AT-Render-Key |

Eje debe vincular esas credenciales en el destino y comprobar que `AT REST Secret (header)` configura `X-AT-Secret`. Nunca copiar valores al export. La plantilla y ejemplos ficticios están en `PLANTILLA-retomar_propuesta.md`.

Antes de activar, Luis/Eje debe autorizar el piloto y aprobación de la plantilla Marketing `retomar_propuesta` en español `es`; validar credenciales, routing/version Graph, compatibilidad n8n de los nodos/expresiones, enlaces de ítems y resultados de red en el destino. La aceptación local no prueba importación, aprobación Meta ni entrega. Mantener inactivo hasta ese gate.

Superficies: exports/generadores y renderer son internos; el workflow no crea rutas públicas. Los enlaces a `retomar.php` usan el token del sistema existente; no publicar exports con datos de ejecuciones. SEO para estos artefactos internos: NOT_APPLICABLE.

Entrega: los cuatro archivos Task 7 de este directorio son entregables permanentes para Eje; **no se suben por FTP a PROD**. El JSON se importa únicamente en n8n bajo autorización. Los generadores y la documentación tampoco se suben al webroot público. No hay temporales ni secretos que subir.
