# GT Baseball · Inscripciones (prototipo)

Flujo n8n que recibe el formulario de `demos/gt-baseball/` (publicado en
`https://automatizatech.cl/demos/gt-baseball/`). Diseño y alcance en
`Docs/superpowers/specs/2026-09-27-gt-baseball-prototipo-design.md`.

```
Webhook POST /webhook/gt-baseball-inscripcion
  → Validar (campo trampa, obligatorios, largos, JPEG y PDF reales)  → 400 si falla, sin escribir nada
  → Subir foto y planilla PDF a la carpeta de Drive
  → Agregar fila al Google Sheet (RAW: sin fórmulas)
  → Correo con los dos adjuntos → { ok: true, id }
```

- Credenciales de n8n por id: `Google Drive account`, `Google Sheets PROD` (las dos son la cuenta
  `contacto@automatizatech.cl`) y `SMTP account PROD`.
- CORS: solo `https://automatizatech.cl` y el servidor local de prueba (`.claude/launch.json`,
  configuración `gt-baseball`).
- Un envío con `?prueba=1` en la página se marca PRUEBA y va solo a Luis. Los reales van a Luis y
  al cliente.
- `saveDataSuccessExecution: none`: cada envío trae la foto de un menor, y n8n no guarda las
  ejecuciones que salen bien. Si hay que depurar, cambiarlo por un rato y volver a publicar.

## Configuración fuera del repo

El repositorio es público: los destinatarios, los ids de la carpeta y del Sheet y el WhatsApp de la
academia viven en un `gt-config.json` local, nunca aquí. Tiene estas claves:
`folder_id`, `sheet_id`, `sheet_url`, `destinatarios`, `destinatarios_prueba` y `whatsapp`. La
copia durable está junto a los scripts de despliegue del 27-sep, en
`C:\Users\luis_\respaldos\deploy-scripts\2026-09-27-gt-baseball\`.

```
python N8N/gt-baseball/build.py <ruta>/gt-config.json             # escribe el JSON junto a la config
python N8N/gt-baseball/build.py <ruta>/gt-config.json --publicar  # crea o actualiza por nombre y activa
```

`index.html` va con `data-whatsapp=""` en el repo; el script de despliegue
(`deploy_gt.py`, en la misma carpeta de respaldos) pone el número solo en la copia que sube a PROD.
