# GT Baseball · Inscripciones

Flujos n8n que reciben el formulario de `demos/gt-baseball/`. Diseño, auditoría y alcance en
`Docs/superpowers/specs/2026-09-27-gt-baseball-prototipo-design.md`.

| Versión | Página | Flujo en n8n | Webhook | Estado |
|---|---|---|---|---|
| v1 | `https://automatizatech.cl/demos/gt-baseball/` (la que tiene Jeffer) | «GT Baseball · Inscripciones (prototipo)» (`1ooWxClFzc6vGb3W`) | `gt-baseball-inscripcion` | En PROD y activa. Congelada: etiqueta git `gt-baseball-v1` y JSON en los respaldos. No se toca |
| v2 | `https://automatizatech.cl/demos/gt-baseball/v2/` | «GT Baseball · Inscripciones v2» | `gt-baseball-inscripcion-v2` | La construye `build.py`; se publica solo con el ok de Luis |

La v2 va en su propia carpeta y su propio flujo para que la v1 siga funcionando en la dirección que ya se
le pasó al cliente (pedido de Luis, 27-sep). El código del repo en `demos/gt-baseball/` es la v2.

```
Webhook POST /webhook/gt-baseball-inscripcion-v2
  → Validar: campo trampa, obligatorios, formatos (mismas reglas que app.js), JPEG y PDF reales,
             forma de pago de gt-config.json y comprobante (JPEG o PDF de hasta 4 MB) cuando la forma
             lo pide y el apoderado no marcó «lo envío después»; límite de envíos (10 por IP y hora,
             6 por correo y día; guarda huellas, no datos) → 400 si falla, sin escribir nada
  → Subir foto y planilla PDF a la carpeta de Drive
  → ¿Con comprobante? → sí: subir el comprobante a la misma carpeta
  → Agregar fila al Google Sheet (RAW: sin fórmulas)
  → Correo a la academia (siguiente paso del pago arriba; todos los datos; planilla, foto y comprobante;
    el asunto dice «comprobante adjunto», «falta el comprobante» o «pago en efectivo»)
  → Correo al apoderado (paso del pago arriba con los datos para pagar si falta el comprobante; resumen
    y planilla; «responder a» va a la academia; si falla no corta el flujo)
  → { ok: true, id, correo_apoderado }
```

- Credenciales de n8n por id: `Google Drive account`, `Google Sheets PROD` (las dos son la cuenta
  `contacto@automatizatech.cl`) y `SMTP account PROD`.
- CORS: solo `https://automatizatech.cl` y el servidor local de prueba (`.claude/launch.json`,
  configuración `gt-baseball`).
- Un envío con `?prueba=1` en la página se marca PRUEBA: el correo de la academia va solo a Luis y el
  «responder a» del apoderado también. Los reales van a Luis y al cliente.
- La v2 exige el correo del representante y la forma de pago; un envío `v: 1` sigue aceptándose sin ellos.
- Sheet: las 23 columnas de la v1 y, al final, «Forma de pago» y «Comprobante» (enlace de Drive,
  `Pendiente` o `No aplica`). Los encabezados de esas dos columnas se agregan en el Sheet al publicar.
- Pago: después de verificar el pago, la academia agrega al representante al grupo de WhatsApp
  (lo pidió Jeffer por audio el 27-sep). La página y los dos correos lo dicen.
- Firma: desde los 15 años el atleta también firma la planilla (línea propia en el PDF) y el correo al
  representante lo avisa. La edad es `EDAD_FIRMA_ATLETA`, igual en `build.py` y en `assets/planilla.js`.
- `saveDataSuccessExecution: none`: cada envío trae la foto de un menor y a veces un comprobante de pago;
  n8n no guarda las ejecuciones que salen bien. Si hay que depurar, cambiarlo por un rato y volver a publicar.

## Configuración fuera del repo

El repositorio es público: los destinatarios, los ids de la carpeta y del Sheet, el correo de la
academia, su WhatsApp y los datos para pagar (cédula, teléfono de pago móvil, correo de Zelle) viven en
un `gt-config.json` local, nunca aquí. Claves: `folder_id`, `sheet_id`, `sheet_url`, `destinatarios`,
`destinatarios_prueba`, `responder_a`, `whatsapp` y `pago`
(`{monto, permitir_despues, metodos: [{id, nombre, detalle, comprobante, datos: [{etiqueta, valor, copiar}], texto}]}`).
Con `permitir_despues: false` el comprobante pasa a ser obligatorio en la página y en el flujo; con
`monto` lleno, el monto aparece en el paso de pago y en los correos. La copia durable está junto a los
scripts de despliegue, en `C:\Users\luis_\respaldos\deploy-scripts\2026-09-27-gt-baseball\`.

```
python N8N/gt-baseball/build.py <ruta>/gt-config.json             # escribe el JSON junto a la config
python N8N/gt-baseball/build.py <ruta>/gt-config.json --publicar  # crea o actualiza por nombre y activa
```

`index.html` va con `data-whatsapp=""` y `<script type="application/json" id="datos-pago">{}</script>`
en el repo; el script de despliegue (`deploy_gt.py`, en la misma carpeta de respaldos) pone el número y
el bloque `pago` solo en la copia que sube a PROD (`subir`) o en una copia local para probar
(`armar <carpeta>`). `subir` va a `/demos/gt-baseball/v2/` y compara la huella md5 de cada archivo de la
v1 antes y después de subir; `verificar` revisa la v2 contra lo local y la v1 contra su respaldo congelado.
En local, la página acepta `?endpoint=<url>` para apuntar a un receptor de prueba; en PROD ese
parámetro se ignora.
