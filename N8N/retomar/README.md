# Retomar: workflows preparados e inactivos

Se entregan tres workflows. Los tres exports llevan `active: false`; ninguno se importó ni se ejecutó en n8n desde este directorio, y los generadores no usan la red ni envían mensajes.

| Export | Workflow | Disparo | Qué hace |
| --- | --- | --- | --- |
| `retomar-redactar.json` | Retomar · Redactar | Webhook `POST /webhook/retomar-redactar` (`responseMode: onReceived`) | Lee el contexto del seguimiento en WordPress, pide a GPT-4o los textos y los encargos de foto, y los guarda por el callback `.../retomar/{id}/redaccion`. |
| `retomar-render.json` | Retomar · Render | Webhook `POST /webhook/retomar-render` | Pide la presentación al renderer (hasta 300 s) y entrega a WordPress `view_url`, `pdf_url` y las fotos (`fotos` = `images.urls` del renderer). |
| `retomar-reloj.json` | Retomar · Reloj y WhatsApp | Cron `0 5 * * * *`, zona `America/Santiago`: minuto 5 de cada hora, todos los días | Llama a `retomar/tick`; WordPress decide todo y devuelve los WhatsApp que toque mandar. |

Regenerar los tres desde este directorio con Python 3 sin bytecode:

```powershell
python -B build_retomar_redactar.py
python -B build_retomar_render.py
python -B build_retomar_reloj.py
```

Probar los nodos Code de «Retomar · Redactar» (Node plano, sin red ni credenciales; sale con código distinto de cero si algo falla):

```powershell
node probar_retomar_redactar.js
```

Extrae el código de «Preparar pedido», «Leer respuesta» y «Falla de contexto» del export y lo ejecuta con datos ficticios: respuesta válida, respuestas que no son JSON o vienen incompletas (`ok:false`), portada con estadio, fachada o calle (se reemplaza y sigue llamándose `portada`), láminas repetidas o ajenas, condiciones de los IF y URLs. Correrlo siempre después de regenerar `retomar-redactar.json`. Con un argumento (`node probar_retomar_redactar.js salida.json`) deja el callback de la respuesta válida para pasarlo por el validador de WordPress en el sitio local.

`build_retomar_redactar.py` toma los ayudantes de JavaScript `json_guard.py` y `fotos_guard.py` de `../propuestas-v3` (los mismos de los flujos de propuestas y del plan de trabajo). El generador del reloj reutiliza el routing público Graph `v22.0` del nodo `Send WhatsApp` del export local `../PROD/Followup_WhatsApp_Send.json`; esa versión no se verificó en vivo y Eje debe validarla al desplegar. Ninguno instala dependencias ni lee claves.

## Retomar · Redactar

Nodos: **Webhook** (`retomar-redactar`, Header Auth) → **Leer contexto** (GET `?rest_route=/automatiza-tech/v1/retomar/{id}/contexto`) → **¿Contexto leído?** → **¿Es el pedido vigente?** → **Preparar pedido** (Code) → **Redactar** (nodo OpenAI, `gpt-4o`) → **Leer respuesta** (Code) → **Guardar redacción** (POST `.../retomar/{id}/redaccion`). Si no se pudo leer el contexto, **Falla de contexto** (Code) también termina en **Guardar redacción**, con `ok: false`.

WordPress llama al webhook con `{id, generacion}` y no espera a la IA. Reglas del flujo:

- **El contexto ya viene sin montos** (WordPress descarta las líneas con precios) y el prompt prohíbe cualquier monto: nada de `$`, `CLP`, `USD`, `UF`, «pesos», «dólares» ni cifras con separador de miles. Si aun así la IA los escribe, WordPress lo detecta, pide la redacción otra vez (una sola vez) y el flujo no hace nada más; con dos intentos pasa a «revisión» manual.
- **Prompt:** español de Chile, voz del equipo en plural («te dejamos», «podemos»), `quedamos`, `proponemos` y `partir` de hasta 600 caracteres y 6 líneas, `recomendacion` de hasta 300 caracteres y 3 líneas, ajustada al presupuesto y proponiendo partir por etapas. Un encargo de foto por lámina de `slides_foto` (`portada`, `cierre`, `demo`): escena fotográfica 16:9 del rubro del prospecto, con personas y sin texto, letreros, etiquetas ni logos. Salida estricta `{"textos": {...}, "image_briefs": [{"slide": "…", "prompt": "…"}]}`.
- **Sin modo JSON del nodo:** el nodo OpenAI heredado (`n8n-nodes-base.openAi` v1) no tiene opción de formato de respuesta (se comprobó en su definición), igual que los flujos del plan de trabajo. El prompt exige JSON y «Leer respuesta» lo lee con `leerJsonModelo` (acepta un bloque de código y una llave de más al final, nada más).
- **Fotos sin texto:** «Leer respuesta» descarta encargos de láminas no permitidas o repetidos y pasa los demás por `limpiarFotos` (`fotos_guard.py`): si un encargo pide letreros, pantallas, papeles o productos con etiqueta lo cambia por una escena neutra y lo avisa en `nota`; siempre agrega el cierre «no signs, no labels, no text, no lettering, no logos, …». Las reglas de portada del filtro (fondo sin estadios, fachadas, calles ni muros, y primer plano con el fondo desenfocado) están escritas para la lámina `cover`: el nodo traduce `portada` a `cover` al entrar y la devuelve como `portada` al salir.
- **Llegó tarde:** si la `generacion` del contexto ya no es la del webhook (WordPress pidió otra redacción), el flujo termina sin gastar en GPT-4o.
- **Falla de la IA o respuesta ilegible:** callback con `ok: false` y una `nota` fija (sin copiar mensajes de red ni de OpenAI, que pueden traer direcciones o claves) más el número de ejecución para buscarla en n8n.
- **Respuestas del callback.** 200: terminado (incluido `{reintento: true}`: WordPress vuelve a pedir la redacción por su cuenta). 409: el pedido ya no es el vigente; termina y nunca reintenta. 422: la validación falló y WordPress **ya dejó la redacción en «error» con el motivo exacto**; el flujo termina sin mandar `ok: false`, porque un segundo POST pisaría ese motivo con uno más pobre. Si el callback no llega (red o caída de WordPress), la redacción queda «pendiente» y, si el seguimiento sigue activo y ya le toca, el reloj la vuelve a pedir pasadas 6 horas (como máximo una vez cada 24 h); por eso el flujo no reintenta.

## Retomar · Render

El webhook recibe `{id, generacion, render}` y devuelve la generación en todos los callbacks, incluidos los fallos. El UID versionado tiene 57 caracteres (`token-generacion-retomar`); WordPress rechaza con 409 callbacks de una generación anterior o de contenido cambiado. Los enlaces externos conservan el wrapper con token estable.

La petición al renderer espera hasta **300 s** (puede generar hasta tres fotos). El callback a `POST .../retomar/{id}/vista` es `{ok, generacion, view_url, pdf_url, nota, fotos}` con `fotos: body.images && body.images.urls || {}`: WordPress guarda cada foto solo si es de este seguimiento y de una lámina con encargo vigente. Un renderer anterior sin `images.urls` entrega `{}` y no rompe nada.

## Retomar · Reloj y WhatsApp

Una pasada por hora (minuto 5): hace POST a `retomar/tick` y WordPress hace, dentro de esa petición, primero los envíos aprobados y después la detección de propuestas estancadas, los reintentos de redacción y el correo semanal. Por eso el nodo **Tick** espera hasta **120 s**. El horario de los envíos al prospecto (lunes a viernes, 10:00 a 13:00 en `America/Santiago`: las pasadas de las 10:05, 11:05 y 12:05) y el día y hora del correo semanal los decide WordPress, no el cron.

Un arreglo `whatsapp` vacío termina la rama. Cada elemento produce exactamente una petición Graph con dos parámetros de cuerpo y dos botones URL. El resultado se asocia al `id`/`n` original mediante enlaces `pairedItem` e `itemMatching`, aunque HTTP sustituya el JSON.

Graph usa `onError: continueRegularOutput` y respuesta completa para registrar aceptación (`wamid`) o error HTTP/red en `POST retomar/whatsapp-resultado`. Un fallo `131049` queda registrado y no se reintenta. No hay reintentos automáticos de nodos ni bucles. No reejecutar manualmente una ejecución fallida: podría repetir WhatsApp; revisar el registro en WP con Eje. Si falla el callback, el evento queda pendiente de conciliación, sin reenviar WhatsApp. Los títulos de error se normalizan sin copiar mensajes que puedan incluir tokens o datos personales. El WhatsApp automático sigue apagado por defecto en WordPress (`at_rt_wa_automatico`).

## Constantes de WordPress

Son URL de configuración, no secretas; se definen en el `wp-config.php` del destino (una línea, antes del `require_once` de `wp-settings.php`) y nunca llevan claves:

- `AT_N8N_RETOMAR_RENDER`: URL de producción del webhook de «Retomar · Render». Sin ella la presentación queda pendiente.
- `AT_N8N_RETOMAR_REDACTAR`: URL de producción del webhook de «Retomar · Redactar» (`…/webhook/retomar-redactar`, el mismo servidor n8n que la anterior). Sin ella WordPress responde `sin_flujo` y no pide ninguna redacción: no se rompe nada, solo no se redacta con IA y los textos se escriben a mano en el panel.

## Orden de activación

Nada de esto se activa sin autorización de Luis. Importar en n8n crea un workflow nuevo: antes de activar uno, **desactivar y archivar el anterior del mismo nombre** (dos activos con el mismo webhook o el mismo cron no pueden convivir).

1. WordPress de la fase 2 en PROD, con sus rutas `retomar/{id}/contexto` y `retomar/{id}/redaccion`, y el ZIP del renderer con `images.urls` y `recomendacion`. Los flujos llaman a esas rutas: sin ellas fallan.
2. **«Retomar · Redactar»:** importar, vincular las credenciales (tabla de abajo) y **activarlo**; después agregar `AT_N8N_RETOMAR_REDACTAR` en `wp-config.php`. Con el flujo activo y la constante, WordPress ya puede pedir redacciones.
3. **«Retomar · Render»:** reemplazar por el export nuevo (300 s y `fotos`) y dejarlo **activo**. Solo guarda fotos si WordPress y el renderer ya son los de la fase 2.
4. **«Retomar · Reloj y WhatsApp»:** reemplazar por el export nuevo (cron cada hora, `Tick` de 120 s) y dejarlo **inactivo** hasta que Luis dé el visto bueno del piloto. Activarlo es lo que pone en marcha la detección, el correo semanal de aprobación y los envíos aprobados.

## Credenciales (solo id y nombre; nunca valores)

| Nodo | Tipo n8n | Nombre | Referencia en el export |
| --- | --- | --- | --- |
| Redactar: Webhook, Leer contexto, Guardar redacción | httpHeaderAuth | AT REST Secret (header) | id y nombre |
| Redactar: Redactar | openAiApi | OpenAi account | id y nombre |
| Render: Webhook, Guardar vista · Reloj: Tick, Registrar resultado | httpHeaderAuth | AT REST Secret (header) | solo nombre |
| Render: Render | httpHeaderAuth | X-AT-Render-Key | solo nombre |
| Reloj: Enviar WhatsApp | whatsAppApi | WhatsApp Tech | solo nombre |

Eje debe vincular esas credenciales en el destino y comprobar que `AT REST Secret (header)` configura `X-AT-Secret`. Nunca copiar valores al export. La plantilla y ejemplos ficticios están en `PLANTILLA-retomar_propuesta.md`.

Antes de activar el reloj con WhatsApp, Luis/Eje debe autorizar el piloto y la aprobación de la plantilla Marketing `retomar_propuesta` en español `es`; validar credenciales, routing/versión Graph, compatibilidad n8n de los nodos y expresiones, enlaces de ítems y resultados de red en el destino. La aceptación local no prueba importación, aprobación de Meta ni entrega.

Superficies: exports, generadores y renderer son internos; los workflows no crean rutas públicas. Los enlaces a `retomar.php` usan el token del sistema existente; no publicar exports con datos de ejecuciones. SEO para estos artefactos internos: NOT_APPLICABLE.

Entrega: los exports (`retomar-redactar.json`, `retomar-render.json`, `retomar-reloj.json`), sus generadores, `probar_retomar_redactar.js` y este README son entregables permanentes para Eje; **no se suben por FTP a PROD**. Los JSON se importan únicamente en n8n bajo autorización. Los generadores y la documentación tampoco se suben al webroot público. No hay temporales ni secretos que subir.
