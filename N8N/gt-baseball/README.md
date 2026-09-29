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
| Buscador de planillas | Página `gtbaseball.com/buscador/` (sin enlace en la portada) y flujo «GT Baseball · Buscador de planillas» (webhook `gt-baseball-buscador`), que arma `build_buscador.py` | **En local** (29-sep, rama `claude/gt-baseball-planes`), probado con un flujo temporal contra el Sheet real. Para publicarlo falta que Luis ponga `GT_BUSCADOR_CLAVE` en Easypanel y dé el ok |

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

## Buscador de planillas

Página privada para que la academia ubique a un atleta ya inscrito y vuelva a descargar su planilla (pedido de Luis,
29-sep). No está enlazada desde la portada: Luis le pasa el enlace a Jeffer.

```
Página /buscador/ → POST /webhook/gt-baseball-buscador { clave }
  → Revisar clave: compara con {{$env.GT_BUSCADOR_CLAVE}} sin cortar en el primer carácter distinto
       · sin la variable (o con menos de 12 caracteres) → 503 { error: 'sin_configurar' }
       · clave equivocada → 401; tras 8 fallos desde una IP en 15 min → 429 (datos estáticos del flujo; la IP es la
         última de X-Forwarded-For, la que agrega el proxy, porque la primera la puede inventar el cliente)
  → Leer Sheet (Inscripciones!A2:Y, credencial Google Sheets PROD)
  → Armar lista: un objeto por fila con los 16 campos que usa el buscador (nombre, cédula, nacimiento, edad, posición,
    representante y su teléfono, fecha, pago, comprobante e ids de Drive); lo demás queda solo en el Sheet
  → { ok: true, total, atletas: [...] } con Cache-Control: no-store
```

- **La clave** la elige Luis y la pone en Easypanel, en la variable `GT_BUSCADOR_CLAVE` del servicio n8n, igual que
  `TURNSTILE_SECRET` (🔴 en el servicio **n8n**, no en el del renderer de propuestas, y redesplegar para que la lea).
  Nunca va en el repo ni en el flujo. En la página, Jeffer la escribe una vez: con «Recordar» queda en el
  `localStorage` de su teléfono y **vence a los 30 días**; sin eso dura mientras la pestaña esté abierta. «Salir» pide
  confirmación y la borra junto con la búsqueda.
- **La página** filtra, ordena y pagina sola: búsqueda por nombre (sin importar tildes) o cédula (sin importar puntos
  ni la V), posición, forma de pago, comprobante (recibido, falta o no aplica), edad y fecha de inscripción. Las
  inscripciones de prueba (columna B = PRUEBA) se ocultan salvo que se pidan.
- **Las planillas, fotos y comprobantes son enlaces de Drive**: solo abren con una cuenta de Google con acceso a la
  carpeta (Jeffer es lector). Un enlace reenviado no alcanza para ver el archivo.
- Todo se dibuja con `textContent` (nada de `innerHTML` con datos) y solo se arman enlaces con ids que tienen forma de
  id de Drive. El `?endpoint=` de prueba solo funciona en `localhost` y `127.0.0.1`.
- `buscador/.htaccess` agrega `X-Robots-Tag: noindex, nofollow` y `Cache-Control: no-store`; la CSP viene de la raíz.
- No guarda ejecuciones exitosas, con error ni manuales (traen nombres y cédulas de menores).
- `buscador/.htaccess` trae una CSP propia más estricta que la de la portada (sin Turnstile ni cdnjs; solo el script
  de tema en línea, por su hash). Si se cambia ese `<script>` de `buscador/index.html`, hay que recalcular el hash.
- Auditoría del 29-sep (4 auditores + verificador escéptico por hallazgo): 36 hallazgos confirmados, ninguno grave;
  corregidos en el commit `9a4de83`. XSS probado con 30 registros hostiles: nada se ejecuta.

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
python N8N/gt-baseball/build_buscador.py <ruta>/gt-config.json --probar    # buscador en flujo temporal (clave aleatoria)
python N8N/gt-baseball/build_buscador.py <ruta>/gt-config.json --publicar  # flujo del buscador
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
- **Portada nueva (29-sep), en la copia local:** `capturas-planes.cjs` (franja en 7 tamaños y los dos temas),
  `capturas-opciones-fondo.cjs` y `probar-fondos-arriba.cjs` (rotación de los 3 fondos, «menos movimiento» y el botón
  «Volver al inicio»).
- **Buscador, en la copia local:** `mock_buscador.py` (receptor de prueba en el puerto 8775, con 37 atletas inventados;
  se bloquea tras 8 claves malas hasta reiniciarlo) y `probar-buscador.cjs` (54 comprobaciones: clave, búsqueda, cada
  filtro, orden, paginación, enlaces, «Salir», celular, tema claro y el 429). Se abre con
  `/buscador/?endpoint=http://127.0.0.1:8775/`.
- **En PROD:** `e2e-turnstile.cjs` (el humano pasa, el bot queda retenido) y `verif-guia-dominio.cjs`. Las que
  inscriben dejan filas de prueba que hay que borrar a mano.
- 🔴 **Turnstile en modo Managed retiene al Chrome de Playwright** (`navigator.webdriver=true`), y eso es lo
  esperado. Para una prueba de punta a punta hay que lanzar Chrome con `--disable-blink-features=AutomationControlled`
  e `ignoreDefaultArgs: ['--enable-automation']`. Para diagnosticar se usa el `error-callback` y el
  `before-interactive-callback` (ver `diag-turnstile.cjs`). La sitekey de prueba de Cloudflare
  (`1x00000000000000000000AA`) siempre da token, incluso a un bot, así que no sirve para comparar.
