# Guía: medición de Meta por servidor (CAPI) y casilla anti-robot (Turnstile) para un cliente

Paso a paso para repetir con un cliente lo que se hizo en automatizatech.cl el 16-nov-2026, con los errores que
cometimos y las fuentes oficiales. El caso de AT está en `Docs/MARKETING/2026-10-28-META-CAPI-LEAD.md` (CAPI) y
`N8N/web-turnstile/README.md` (Turnstile).

**Orden recomendado:** primero Turnstile y después la CAPI. Sin la casilla, cualquiera puede mandar citas falsas al
formulario y cada una se contaría como cliente potencial en las campañas.

---

## Parte A. Cloudflare Turnstile en los formularios

### A1. Qué hace el cliente (o Luis, con la cuenta del cliente)

1. Entrar a <https://dash.cloudflare.com> (cuenta gratuita). El dominio **no** necesita estar en Cloudflare.
2. Menú **Turnstile** (según la versión del panel, dentro de «Seguridad de aplicaciones») → **Agregar widget**.
3. Nombre descriptivo; dominios: el dominio con y sin `www`; modo **Gestionado**; pre-clearance **No** → Crear.
4. Copiar las dos claves:
   - **Clave del sitio**: pública, puede ir en el código del sitio.
   - **Clave secreta**: va al archivo de claves con una etiqueta propia («ETIQUETA : valor» en la misma línea). Nunca
     por chat, correo, repositorio ni documentación.

### A2. Qué hacemos nosotros

1. **En el navegador**, cada formulario que crea algo (cita, lead, inscripción):
   - Carga `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit` y dibuja la casilla con
     `turnstile.render()` cuando el formulario se ve (las ventanas emergentes no piden el desafío al cargar).
   - Manda el token (`turnstile.getResponse()`) junto con el pedido.
   - Después de cada envío, con éxito o error, llama a `turnstile.reset()`: **el token sirve una sola vez y dura 5 minutos**.
   - Sin token, no envía y pide marcar la casilla; con 403 del servidor, muestra «Vuelve a marcar la casilla».
   - Copia de referencia: `wp-content/themes/automatiza-tech/assets/js/at-turnstile.js`.
2. **En el servidor** (para AT, n8n), antes de guardar:
   - `POST https://challenges.cloudflare.com/turnstile/v0/siteverify` con `secret` y `response` (acepta JSON o formulario).
   - Solo si responde `success: true` se sigue; si no, **403** y no se guarda nada.
   - En n8n, la clave secreta va en una credencial **Custom Auth** con `{"body": {"secret": "..."}}`, limitada al dominio
     `challenges.cloudflare.com` (por API, el campo `json` va como texto). Plantilla: `N8N/web-turnstile/aplicar.py`.
3. **Fuera del dominio real** (local, pruebas) usar la clave de sitio de prueba `1x00000000000000000000AA` (siempre pasa)
   y, en el servidor de pruebas, la secreta de prueba `1x0000000000000000000000000000000AA`.

### A3. Orden de despliegue (para no rechazar a clientes reales)

1. Subir el sitio con la casilla (manda el token; el servidor todavía no lo exige).
2. Purgar la caché del sitio (LiteSpeed en Hostinger) para que todos bajen el JS nuevo.
3. Recién entonces activar la revisión en el servidor.
4. Probar: un envío sin token y otro con token inventado deben recibir 403 (`N8N/web-turnstile/probar.py`); después,
   una cita real desde el sitio debe guardarse.

### A4. Errores conocidos

- **Turnstile retiene al navegador automatizado** (Playwright normal): es lo esperado, no una clave mala. Para una prueba
  de punta a punta: Chrome con `--disable-blink-features=AutomationControlled` e `ignoreDefaultArgs: ['--enable-automation']`.
- La clave de sitio de prueba da token incluso a un bot: compararla con la real no prueba nada.
- La casilla vive dentro de un shadow DOM: el token se lee con `turnstile.getResponse()` o el campo oculto
  `cf-turnstile-response`, no buscando el `iframe`.

---

## Parte B. Meta: evento «Cliente potencial» (Lead) por la API de Conversiones

### B1. Antes de empezar

- El formulario ya tiene Turnstile (Parte A) y el endpoint que guarda la cita exige credencial.
- El cliente aprobó el texto de privacidad que dice que se comparten con Meta correo y teléfono **cifrados** (SHA-256).
- Decidir qué cuenta como Lead (AT: primera cita de cada correo en 30 días) y qué canales (AT: Instagram y WhatsApp; la
  web cuando hay Turnstile).

### B2. Generar el token (lo hace el cliente o Luis, nunca por chat)

1. Administrador de eventos de Meta → **Conjuntos de datos**.
2. Elegir el **píxel del sitio** (ícono de web). 🔴 No confundirlo con una **app** (ícono de celular, dice
   «Identificador de la app»): el 16-nov abrimos primero la app por error.
   - Para saber cuál es el píxel del sitio: buscar su identificador en el código público de la página (PixelYourSite lo
     publica como `pixelIds`).
3. **Configuración** → **API de Conversiones** → **Generar token de acceso**.
4. En el diálogo de conjuntos de datos, dejar marcado **solo** el píxel del sitio. Meta avisa que esa configuración «no
   se puede desactivar»; no envía nada por sí sola.
5. Pegar el token en el sitio. En WordPress con PixelYourSite: bloque de Meta → **Conversion API Token** → guardar.
   Dejar apagados «Token-based Conversion API» y «Meta-Enabled Conversion API» si el envío lo hace nuestro módulo.

### B3. Probar antes de encender

1. Comprobar en el servidor, sin imprimir el token: módulo cargado, píxel correcto, token guardado, interruptor apagado.
   - Leer el píxel con ese token puede responder «(#100) Missing Permission»: ese token sirve para **enviar** eventos,
     no prueba que esté malo.
2. **Probar eventos** → copiar el código `TEST…` (está en **Meta**) → pegarlo en el campo «Test Event Code» del sitio.
3. Mandar **un** Lead de prueba desde el servidor, con datos inventados del equipo y ese código. Éxito = HTTP 200 y
   `events_received: 1`.
   - 🔴 El 16-nov la pantalla «Probar eventos» no mostró los eventos (enviados como canal `chat`) aunque Meta respondió
     200 / 1 recibido tres veces. Si no aparecen, la respuesta de Meta es la evidencia; no repetir envíos sin fin.
4. **Borrar el código de prueba** del sitio. Si queda, los Leads reales llegan como prueba y no cuentan para campañas.
5. Con el OK del cliente, encender el interruptor (AT: `wp option update at_capi_activo 1`) y comprobar de nuevo.
6. Primera comprobación real: la próxima cita por un canal que cuenta debe mover «Última recepción» del evento Cliente
   potencial en el **Resumen** del píxel, y no dejar errores de envío en el registro del servidor.

### B4. Después

- Si el **Diagnóstico** del píxel pide confirmar el dominio, el cliente lo agrega a los dominios autorizados.
- Apagar es volver el interruptor a 0, sin tocar código.
- Correos y teléfonos van cifrados con SHA-256; el mismo `event_id` en píxel y servidor evita contar dos veces.

---

## Fuentes oficiales (verificadas el 16-nov-2026)

- Cloudflare Turnstile, primeros pasos: <https://developers.cloudflare.com/turnstile/get-started/>
- Turnstile, dibujar la casilla en el navegador: <https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/>
- Turnstile, validación en el servidor (token de 5 minutos y de un solo uso; JSON o formulario): <https://developers.cloudflare.com/turnstile/get-started/server-side-validation/>
- Turnstile, claves de prueba: <https://developers.cloudflare.com/turnstile/troubleshooting/testing/>
- n8n, credenciales Custom Auth (`headers`, `body`, `qs`): <https://docs.n8n.io/integrations/builtin/credentials/httprequest/>
- Meta, API de Conversiones, primeros pasos: <https://developers.facebook.com/docs/marketing-api/conversions-api/get-started>
- Meta, uso de la API y eventos de prueba: <https://developers.facebook.com/docs/marketing-api/conversions-api/using-the-api>
- Meta, datos del cliente y cifrado: <https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters>
- Meta, deduplicar píxel y servidor: <https://developers.facebook.com/docs/marketing-api/conversions-api/deduplicate-pixel-and-server-events>
