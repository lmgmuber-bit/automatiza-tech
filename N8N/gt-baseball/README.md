# GT Baseball · Inscripciones

Formulario de inscripción de GT Baseball Academy (`demos/gt-baseball/`) y los flujos n8n que lo atienden. Diseño,
pruebas y decisiones en `Docs/superpowers/specs/2026-09-27-gt-baseball-prototipo-design.md`.

## Estado (28-sep-2026)

| Qué | Dónde | Estado |
|---|---|---|
| Sitio | `https://gtbaseball.com/` (cartel con QR en `/cartel.html`) | EN PROD. Carpeta propia `domains/gtbaseball.com/public_html` en Hostinger, aislada de los otros dominios de la cuenta |
| Flujo de inscripción | «GT Baseball · Inscripciones v2» (`jsqxDfoWvJbDVAUa`), webhook `gt-baseball-inscripcion-v2`, 20 nodos | Activo. Lo construye `build.py` |
| Respaldo del Sheet | «GT Baseball · Respaldo del Sheet» (`iefq6IxFOBrHtmOd`) | Activo, cada 6 horas: pisa un único `.xlsx` si el Sheet cambió. Lo construye `build_respaldo.py` |
| Direcciones viejas | `automatizatech.cl/demos/gt-baseball/` (v1) y `…/v2/` | Redirigen 301 a `https://gtbaseball.com/` |
| Flujo v1 | «GT Baseball · Inscripciones (prototipo)» (`1ooWxClFzc6vGb3W`) | Activo pero sin uso (su página redirige). Congelado: etiqueta git `gt-baseball-v1` |
| Google Sheet | «GT Baseball Academy · Inscripciones», Drive de `contacto@automatizatech.cl` | Jeffer es **editor** desde el 28-sep. Fila 1 bloqueada; columnas A–Y con advertencia |
| Carpeta de Drive | «GT Baseball Academy · Inscripciones (prototipo)»: fotos, planillas, comprobantes y el Sheet | Jeffer es **lector** desde el 28-sep, para que le abran los enlaces del Sheet. En el Sheet sigue siendo editor (vale el permiso mayor). La carpeta de respaldos está fuera y es privada |

El código de `demos/gt-baseball/` es el que se sirve en gtbaseball.com.

## Flujo de inscripción

```
Webhook POST /webhook/gt-baseball-inscripcion-v2
  → Verificar Turnstile: siteverify de Cloudflare con {{$env.TURNSTILE_SECRET}} y body.turnstile
  → ¿Humano? → no: 403 { ok: false, errores: ['turnstile'] }, sin escribir nada
  → Validar: solo v:2; campo trampa, obligatorios, formatos (mismas reglas que app.js), JPEG y PDF reales,
             forma de pago de gt-config.json y comprobante (JPEG o PDF de hasta 4 MB) cuando la forma lo pide
             y el apoderado no marcó «lo envío después»; anti-duplicado por id (10 min) y límite de envíos
             (10 por IP y hora, 6 por correo y día; guarda huellas, no datos) → 400 si falla.
             Un id repetido responde 200 { ok: true, duplicado: true } sin volver a guardar
  → Subir foto y planilla PDF a la carpeta de Drive
  → ¿Con comprobante? → sí: subir el comprobante a la misma carpeta
  → Agregar fila al Google Sheet (RAW: sin fórmulas)
  → Correo a la academia (siguiente paso del pago arriba; todos los datos; planilla, foto y comprobante;
    el asunto dice «comprobante adjunto», «falta el comprobante» o «pago en efectivo»)
  → Correo al apoderado (paso del pago arriba con los datos para pagar si falta el comprobante; resumen
    y planilla; «responder a» va a la academia; si falla no corta el flujo)
  → { ok: true, id, correo_apoderado }
```

- **Turnstile va antes de Validar** para que un bot no gaste el límite ni marque su id como usado. Validar lee el
  Webhook por nombre (`$('Webhook')`); en las pruebas locales, que no tienen `$`, usa `$input`.
- **El límite y el anti-duplicado no son atómicos.** Viven en los datos estáticos del flujo, y n8n corre las
  ejecuciones en paralelo: una ráfaga exactamente simultánea los pasa (medido el 28-sep). Sirven para los envíos
  separados, como el reintento después de un timeout. La barrera contra ráfagas es Turnstile.
- **Credenciales de n8n por id:** `Google Drive account` y `Google Sheets PROD` (las dos son la cuenta
  `contacto@automatizatech.cl`) y `SMTP account PROD`. La Secret de Turnstile es la variable de entorno
  `TURNSTILE_SECRET` del servicio n8n en Easypanel; nunca va en el repo ni en el flujo.
- **CORS:** `https://gtbaseball.com`, `https://www.gtbaseball.com`, `https://automatizatech.cl` (transición) y
  el servidor local de prueba.
- **Modo prueba:** un envío con `?prueba=1` se marca PRUEBA; el correo de la academia y el «responder a» del
  apoderado van solo a Luis. Los envíos reales van a Luis y al cliente.
- **Sheet:** 25 columnas (A–Y); las dos últimas son «Forma de pago» y «Comprobante» (enlace de Drive, `Pendiente`
  o `No aplica`). El sistema agrega por API como dueño, así que las protecciones no lo frenan (verificado el 28-sep).
  Jeffer puede anotar lo suyo de la columna Z en adelante.
  🔴 No renombrar la pestaña «Inscripciones»: el flujo agrega filas a `Inscripciones!A1`.
- **Pago:** después de verificar el pago, la academia agrega al representante al grupo de WhatsApp. La página y los
  dos correos lo dicen.
- **Firma:** desde los 15 años el atleta también firma la planilla. La edad es `EDAD_FIRMA_ATLETA`, igual en
  `build.py` y en `assets/planilla.js`.
- **`saveDataSuccessExecution: none`:** cada envío trae la foto de un menor y a veces un comprobante de pago, así
  que n8n no guarda las ejecuciones que salen bien.

## Respaldo del Sheet

`build_respaldo.py` arma un flujo que **pisa un único archivo** para no llenar el Drive (decisión de Luis, 28-sep).
Cada 6 horas (minuto 5, hora de Venezuela):
1. mira el `modifiedTime` del Sheet;
2. si cambió desde el último respaldo, lo exporta como Excel y **sobrescribe** «GT Baseball · Inscripciones ·
   respaldo.xlsx», que está en la carpeta privada «GT Baseball · Respaldos del Sheet de inscripciones».

La carpeta es de `contacto@` y no está compartida con la academia. El respaldo anota en `appProperties.origenModified`
de qué versión del Sheet salió; si el Sheet no cambió, no sube nada. El archivo se creó una sola vez: su id está en
gt-config (`respaldo_file_id`). Si alguien lo borra, el flujo falla a la vista en n8n.

**Red de seguridad del archivo único:** como es un `.xlsx` y no un archivo de Google, Drive guarda sus versiones
anteriores 30 días o hasta 100 versiones ([ayuda de Google](https://support.google.com/drive/answer/2409045)).
Si el Sheet se daña y el respaldo copia el daño, en Drive → clic derecho sobre el respaldo → **Administrar versiones**
se baja una versión de antes del error. Además, el Sheet tiene su propio historial (Archivo → Historial de versiones).

Para recuperar algo: bajar la versión buena del respaldo y copiar de vuelta las filas al Sheet original.

## Configuración fuera del repo

El repositorio es público: destinatarios, ids de la carpeta, del Sheet y de la carpeta de respaldos, el correo de la
academia, su WhatsApp y los datos para pagar (cédula, teléfono de pago móvil, correo de Zelle) viven en un
`gt-config.json` local. Claves:
- `folder_id`, `sheet_id`, `sheet_url`, `respaldo_folder_id`, `respaldo_file_id`;
- `destinatarios`, `destinatarios_prueba`, `responder_a`, `whatsapp`;
- `pago` (`{monto, permitir_despues, metodos: [{id, nombre, detalle, monto, comprobante, datos: [{etiqueta, valor, copiar}], texto}]}`);
- `turnstile_sitekey`: pública. Sin ella, Turnstile se apaga en la página y en el flujo;
- `archivos_extra`: el video guía (mp4, póster y subtítulos), que muestra datos de pago y por eso no va al repo.

La copia durable y todos los scripts están en `C:\Users\luis_\respaldos\deploy-scripts\2026-09-27-gt-baseball\`.

## Publicar y desplegar

```
python N8N/gt-baseball/build.py <ruta>/gt-config.json --publicar           # flujo de inscripción
python N8N/gt-baseball/build_respaldo.py <ruta>/gt-config.json --publicar  # flujo de respaldo
python N8N/gt-baseball/build_respaldo.py <ruta>/gt-config.json --probar    # respaldo una vez, flujo temporal
python deploy_dominio.py reconocer | subir | verificar                     # sitio en gtbaseball.com
```

- `deploy_dominio.py` tiene la carpeta destino fija, con un candado que aborta si la ruta real no es la de
  gtbaseball.com. Pone `data-whatsapp`, `#datos-pago` y `data-turnstile` solo en la copia que sube. Antes de subir,
  respalda y quita el `default.php` de Hostinger.
- **Orden al tocar el pago o Turnstile:** primero el sitio, después el flujo. Así nunca hay un flujo que exija algo
  que la página todavía no manda.
- 🔴 `deploy_gt.py` ya no sube (se niega). Su destino, `/demos/gt-baseball/v2/`, tiene el `.htaccess` que redirige
  al dominio, y subir ahí lo pisaría. Queda solo para `armar <carpeta>`: una copia local para probar, con Turnstile
  apagado.
- Los redirects de las direcciones viejas son un `.htaccess` con `RewriteRule` en cada carpeta de automatizatech.cl.
  No están en el repo; su respaldo quedó en `~/respaldos/gt-redirect-*` del servidor.
- `n8n_temp.py` (en los respaldos) llama a las APIs de Google con las credenciales de n8n, mediante un flujo
  temporal que se borra. Hace falta porque el conector de Google Drive de Claude está en otra cuenta y no puede
  tocar archivos de `contacto@`.
- En local, la página acepta `?endpoint=<url>` para apuntar a un receptor de prueba; en PROD ese parámetro se ignora.

## Pruebas

Viven en `…\respaldos\…\pago\`, fuera del repo, porque usan la configuración con datos reales:
- **Sin efectos, sobre el código de los nodos:** `probar-seguridad.cjs`, `probar-reglas-v2-con-pago.cjs`,
  `probar-flujo-pago.cjs`.
- **Sin efectos, en la página local** (puerto 8774, armada con `deploy_gt.py armar`): `probar-pago.cjs`,
  `probar-campos-navegador.cjs`, `probar-salir.cjs`, `probar-firma-aviso.cjs`, `probar-video-guia.cjs`.
- **Concurrencia:** `carga-concurrencia.py` (flujo temporal aislado que se borra al terminar).
- **En PROD:** `e2e-turnstile.cjs` (el humano pasa, el bot queda retenido) y `verif-guia-dominio.cjs`. Las que
  inscriben dejan filas de prueba que hay que borrar a mano.
- 🔴 **Turnstile en modo Managed retiene al Chrome de Playwright** (`navigator.webdriver=true`), y eso es lo
  esperado. Para una prueba de punta a punta hay que lanzar Chrome con `--disable-blink-features=AutomationControlled`
  e `ignoreDefaultArgs: ['--enable-automation']`. Para diagnosticar se usa el `error-callback` y el
  `before-interactive-callback` (ver `diag-turnstile.cjs`). La sitekey de prueba de Cloudflare
  (`1x00000000000000000000AA`) siempre da token, incluso a un bot, así que no sirve para comparar.
