# GT Baseball · Inscripciones

Flujos n8n que reciben el formulario de `demos/gt-baseball/` (publicado en
`https://automatizatech.cl/demos/gt-baseball/`). Diseño, auditoría y alcance en
`Docs/superpowers/specs/2026-09-27-gt-baseball-prototipo-design.md`.

| Versión | Flujo en n8n | Webhook | Estado |
|---|---|---|---|
| v1 | «GT Baseball · Inscripciones (prototipo)» (`1ooWxClFzc6vGb3W`) | `gt-baseball-inscripcion` | Congelada: etiqueta git `gt-baseball-v1` y JSON en los respaldos |
| v2 | «GT Baseball · Inscripciones v2» | `gt-baseball-inscripcion-v2` | La construye `build.py` |

```
Webhook POST /webhook/gt-baseball-inscripcion-v2
  → Validar: campo trampa, obligatorios, formatos (mismas reglas que app.js), JPEG y PDF reales
             y límite de envíos (10 por IP y hora, 6 por correo y día; guarda huellas, no datos)
             → 400 si falla, sin escribir nada
  → Subir foto y planilla PDF a la carpeta de Drive
  → Agregar fila al Google Sheet (RAW: sin fórmulas)
  → Correo a la academia (todos los datos, planilla y foto)
  → Correo al apoderado (resumen y planilla; «responder a» va a la academia; si falla no corta el flujo)
  → { ok: true, id, correo_apoderado }
```

- Credenciales de n8n por id: `Google Drive account`, `Google Sheets PROD` (las dos son la cuenta
  `contacto@automatizatech.cl`) y `SMTP account PROD`.
- CORS: solo `https://automatizatech.cl` y el servidor local de prueba (`.claude/launch.json`,
  configuración `gt-baseball`).
- Un envío con `?prueba=1` en la página se marca PRUEBA: el correo de la academia va solo a Luis y el
  «responder a» del apoderado también. Los reales van a Luis y al cliente.
- La v2 exige el correo del representante (`v: 2` en el envío); un envío `v: 1` sigue aceptándose sin él.
- `saveDataSuccessExecution: none`: cada envío trae la foto de un menor, y n8n no guarda las
  ejecuciones que salen bien. Si hay que depurar, cambiarlo por un rato y volver a publicar.

## Configuración fuera del repo

El repositorio es público: los destinatarios, los ids de la carpeta y del Sheet, el correo de la
academia y su WhatsApp viven en un `gt-config.json` local, nunca aquí. Claves: `folder_id`, `sheet_id`,
`sheet_url`, `destinatarios`, `destinatarios_prueba`, `responder_a` y `whatsapp`. La copia durable
está junto a los scripts de despliegue, en `C:\Users\luis_\respaldos\deploy-scripts\2026-09-27-gt-baseball\`.

```
python N8N/gt-baseball/build.py <ruta>/gt-config.json             # escribe el JSON junto a la config
python N8N/gt-baseball/build.py <ruta>/gt-config.json --publicar  # crea o actualiza por nombre y activa
```

`index.html` va con `data-whatsapp=""` en el repo; el script de despliegue
(`deploy_gt.py`, en la misma carpeta de respaldos) pone el número solo en la copia que sube a PROD.
En local, la página acepta `?endpoint=<url>` para apuntar a un receptor de prueba; en PROD ese
parámetro se ignora.
