# WhatsApp Bot v8 — Portal OmniCliente + Google Sheets Fallback

> **Archivo:** `WhatsApp_Bot_v8_Portal_OmniCliente.json`  
> **Versión de N8N target:** Compatible con N8N v1.x+  
> **Última actualización:** 25 de marzo de 2026  
> **Cliente:** KellsCapilar  
> **Nodos totales:** 92

---

## Diferencias respecto a v7

| v7 | v8 |
|----|----|
| Solo Google Sheets como fuente de config | **Portal API primero**, Google Sheets como fallback |
| Sin cache de configuración | **Cache 5 minutos** en staticData |
| Config estática en GSheets | Config editable en tiempo real desde PromptsView |
| 91 nodos | 92 nodos (+1 `Fetch Portal Config`) |

---

## Nuevas funcionalidades v8

### Nodo `Fetch Portal Config` (Code node)
Ubicado entre `Combine Messages` y `Tipo de Mensaje`. Se ejecuta en cada mensaje pero usa cache de 5 minutos.

**Lógica:**
```javascript
const CHANNEL_ID = 1;  // ← Configurar con el ID real del canal
const OMNI_SECRET = $env.OMNI_ADMIN_SECRET;

// Cache hit: reutilizar configuración si es menor a 5 minutos
if (staticData.portalConfig && (now - staticData.portalConfigAt) < 5*60*1000)
  return $input.all();

// Fetch: llamar al portal con token HMAC-SHA256
const token = crypto.createHmac('sha256', OMNI_SECRET)
  .update(`prompt-config:${CHANNEL_ID}`)
  .digest('hex');

const response = await $helpers.httpRequest({
  method: 'GET',
  url: `https://automatizatech.cl/api-omnichannel.php?route=prompt-config/${CHANNEL_ID}&token=${token}`,
  timeout: 5000
});

// Almacenar en staticData para uso downstream
staticData.portalConfig = {
  config: { ...camposFlat },
  configRows: [{ parametro, valor }],  // para Compute Availability
  servicios: [...],                     // para Build Services List / Save Selected Service
  bloqueos: [...]                       // para Compute Availability
};
```

**Fallback silencioso:** Si el portal no está disponible o responde con error, `staticData.portalConfig = null` y el workflow sigue usando Google Sheets exactamente como antes.

---

## Flujo de mensajes (92 nodos)

```
YCloud Webhook
  └─► Extract Message Data
      └─► Redis Push (buffer mensajes)
          └─► Combine Messages ← punto de entrada unificado
              └─► Fetch Portal Config ★ NUEVO
                  └─► Tipo de Mensaje [Switch — 8 salidas]
                      │
                      ├─[0]─► Get Servicios → Save Selected Service
                      │         ↑ usa staticData.portalConfig.servicios (portal)
                      │         ↑ o $input.all() (GSheets fallback)
                      │
                      ├─[1]─► Validación selección día
                      │         └─► Validate Service Selection
                      │             └─► Service Valid? [Switch]
                      │                 ├─[0]─► Read Servicios → Build Services List
                      │                 │         ↑ usa staticData.portalConfig.servicios
                      │                 └─[1]─► Read Configuracion → Read Bloqueos
                      │                             └─► Read Citas del Día
                      │                                 └─► Compute Availability
                      │                                       ↑ usa staticData.portalConfig.configRows
                      │                                       ↑ usa staticData.portalConfig.bloqueos
                      │
                      └─[7]─► Prepare Chat Input
                                └─► Read Bot Config
                                    └─► Merge Config ★ MODIFICADO
                                        └─► Agente IA (GPT-4o)
                                              ↑ usa staticData.portalConfig.config (portal)
                                              ↑ o rows de GSheets
```

---

## Cómo importar en N8N

### Paso 1: Preparar configuración
Antes de importar, edita el archivo JSON y busca el nodo `Fetch Portal Config`. En la propiedad `jsCode`, línea ~10:
```js
const CHANNEL_ID = 1; // ← cambiar al ID real del canal de KellsCapilar en la BD
```

### Paso 2: Configurar Variable de Entorno en N8N
1. N8N Settings → Environment Variables
2. Añadir: `OMNI_ADMIN_SECRET` = `{valor de OMNI_ADMIN_SECRET en wp-config.php}`

### Paso 3: Importar el workflow
1. N8N → Workflows → Import from File
2. Seleccionar `WhatsApp_Bot_v8_Portal_OmniCliente.json`
3. El workflow se importa como **inactivo**

### Paso 4: Verificar credenciales
Confirmar que estas credenciales N8N siguen configuradas:
- Credencial OpenAI (para Agente IA y GPT-4 Vision)
- Credencial Google Sheets (para fallback y lectura de citas)
- Credencial Redis (para buffer de mensajes)
- Credencial YCloud/HTTP (para envío de mensajes)

### Paso 5: Verificar webhook
Confirmar que el webhook de entrada sigue configurado en YCloud apuntando al nuevo workflow.

### Paso 6: Activar
Activar el workflow v8 y **desactivar el v7** para evitar procesamiento duplicado.

---

## Prueba de la integración portal

Una vez importado y activo, verificar que el portal responda correctamente:
```
GET https://automatizatech.cl/api-omnichannel.php?route=prompt-config/1&token={HMAC}
```

El HMAC se calcula así (Node.js para prueba):
```js
const crypto = require('crypto');
const token = crypto.createHmac('sha256', 'TU_OMNI_ADMIN_SECRET')
  .update('prompt-config:1')
  .digest('hex');
console.log(token);
```

Respuesta esperada:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "channel_id": 1,
    "config_name": "KellsCapilar - Bot Principal",
    "prompt_data": { "nombre_negocio": "Kellscapilar", ... },
    "version": 5
  }
}
```

---

## Notas sobre el fallback a Google Sheets

El fallback es **completamente transparente**. Si la llamada al portal falla:
- Google Sheets: `1ww6qJe057_HUaPTWgxT9pU1cfp8-HqmLecZjmYGB6Ps`
- Hojas usadas: `Configuracion_Bot`, `Servicios`, `Bloqueos_Horario`, `Citas`
- Los nodos `Read Bot Config`, `Read Servicios`, `Read Configuracion`, `Read Bloqueos`, `Read Citas del Día` siguen presentes y activos como fallback.

---

## Historial de versiones

| Versión | Cambios principales |
|---------|-------------------|
| v6 | Flujo original KellsCapilar. Solo Google Sheets. Transfer ID bancario (extracción con GPT-4 Vision). 91 nodos. |
| v7 | Migración a arquitectura OmniCliente (Opción B). Portal como intermediario obligatorio. YCloud → Portal → N8N → Portal → YCloud. |
| **v8** | Portal API como fuente de configuración (portal-first). Cache 5min. Fallback silencioso a GSheets. 92 nodos. |
