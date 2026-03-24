# Integración N8N ↔ Portal OmniCliente — Documentación Completa

> **Fecha:** 24 de marzo de 2026  
> **Versión:** 1.0  
> **Aplica a:** OmniCliente v1.1+ / N8N Workflow v7

---

## 1. Resumen Ejecutivo

Se implementó la **Opción B (Portal como punto de entrada)** para la integración entre el Portal OmniCliente y los workflows de N8N (WhatsApp Bot). Esta arquitectura coloca al portal como intermediario obligatorio entre YCloud (API de WhatsApp) y N8N.

### Arquitectura Antes (v6)
```
Usuario WhatsApp → YCloud → N8N (directo) → YCloud → Usuario WhatsApp
```

### Arquitectura Después (v7 — Opción B)
```
Usuario WhatsApp → YCloud → Portal OmniCliente → N8N → Portal OmniCliente → YCloud → Usuario WhatsApp
                              ↓ (guarda en DB)                ↑ (guarda en DB)
                              Bandeja Unificada               Bandeja Unificada
```

### Beneficios
- **Visibilidad total**: Todos los mensajes (entrantes y salientes del bot) quedan registrados en la Bandeja Unificada
- **Control centralizado**: El portal decide si derivar a N8N o a un agente humano
- **Auditoría completa**: Registro de cada interacción en `wp_omnichannel_messages`
- **Intervención humana**: Un agente puede tomar el control en cualquier momento sin tocar N8N

---

## 2. Flujo Detallado

### 2.1 Mensaje Entrante (Usuario → Bot)

```
1. Usuario envía WhatsApp
2. YCloud webhook → POST /webhook/{channel_type}?channel_id={id}&secret={secret}
3. Portal: handle_ycloud_webhook() → receive_message()
4. Portal guarda mensaje en DB (messages table, direction=inbound)
5. Portal chequea: ¿conversation.status === 'bot' && intervention_mode !== 'human'?
   → SÍ: forward_to_n8n() envía payload al webhook N8N
   → NO: mensaje queda en cola para agente humano
```

### 2.2 Respuesta N8N → Usuario

```
1. N8N procesa mensaje (GPT, Redis buffer, lógica de negocio)
2. N8N envía POST /webhook/n8n-callback con payload
3. Portal: handle_n8n_callback() valida token HMAC-SHA256
4. Portal detecta tipo de payload:
   a) RAW YCloud (tiene 'type' + 'from' + 'to') → handle_n8n_callback_raw()
      - Extrae texto para DB
      - Guarda en messages (direction=outbound, sender_type=bot)
      - Reenvía payload RAW a API YCloud
   b) SIMPLE (tiene 'content' + 'message_type') → send_ycloud_message()
      - Guarda en messages (direction=outbound, sender_type=bot)
      - Envía texto a YCloud
5. Usuario recibe mensaje en WhatsApp
```

### 2.3 Intervención Humana

```
1. Agente presiona "Tomar control" en Bandeja Unificada
2. conversation.status → 'assigned', intervention_mode → 'human'
3. Mensajes entrantes YA NO se reenvían a N8N
4. Agente responde directamente desde el portal → YCloud → Usuario
5. Agente termina intervención → conversation.status → 'bot'
6. Siguiente mensaje del usuario se reenvía a N8N normalmente
```

---

## 3. Cambios en PHP (Backend)

### 3.1 `forward_to_n8n()` — Línea 2136 de omnichannel-controller.php

**Propósito:** Reenvía mensajes entrantes al webhook de N8N en formato estandarizado.

**Payload enviado a N8N:**
```json
{
  "event": "new_message",
  "business_phone": "+56912345678",
  "contact": {
    "phone": "+56987654321",
    "name": "Juan Pérez",
    "email": null
  },
  "message": {
    "id": 42,
    "type": "text",
    "content": "Hola, quiero agendar una cita",
    "media_url": null,
    "timestamp": "2026-03-24T01:00:00"
  },
  "conversation_id": 15,
  "channel_id": 3,
  "callback_url": "https://automatizatech.cl/api-omnichannel.php?route=webhook/n8n-callback",
  "callback_token": "HMAC-SHA256-signed-token"
}
```

**Características:**
- Fire-and-forget (timeout 5s, no bloquea)
- Token HMAC-SHA256 firmado con `OMNI_ADMIN_SECRET`
- Incluye `business_phone` del canal para que N8N lo use en respuestas
- Solo se ejecuta si `conv_status === 'bot'` y `intervention_mode !== 'human'`

### 3.2 `handle_n8n_callback()` — Línea 2181

**Propósito:** Recibe respuestas de N8N, las guarda en DB y las entrega a YCloud.

**Dos modos de operación:**

#### Modo RAW (mensajes interactivos: botones, listas, templates)
Detectado cuando el payload tiene `type` + `from` + `to`:
```json
{
  "callback_token": "HMAC-token",
  "conversation_id": 15,
  "channel_id": 3,
  "from": "+56912345678",
  "to": "+56987654321",
  "type": "interactive",
  "interactive": {
    "type": "list",
    "body": { "text": "Selecciona un servicio:" },
    "action": { "sections": [...] }
  }
}
```

#### Modo SIMPLE (mensajes de texto)
Detectado cuando el payload tiene `content`:
```json
{
  "callback_token": "HMAC-token",
  "conversation_id": 15,
  "channel_id": 3,
  "content": "¡Hola! Bienvenido a nuestro servicio...",
  "message_type": "text"
}
```

### 3.3 `handle_n8n_callback_raw()` — Línea 2255

**Propósito:** Procesa payloads RAW de YCloud y los reenvía tal cual a la API de YCloud.

**Proceso:**
1. Extrae texto legible del payload para guardarlo en DB (soporta text, interactive, template)
2. Elimina campos portal (`callback_token`, `conversation_id`, `channel_id`)
3. Envía payload limpio a `https://api.ycloud.com/v2/whatsapp/messages`
4. Usa `ycloud_api_key` del canal desde `credentials_json`

### 3.4 Ruta API — api-omnichannel.php

```php
// Ruta del callback N8N (antes de la ruta genérica de webhook)
POST /api-omnichannel.php?route=webhook/n8n-callback

// Health check para contingencia N8N
GET /api-omnichannel.php?route=health
```

---

## 4. Cambios en N8N Workflow (v6 → v7)

### 4.1 Resumen de Cambios

| Componente | v6 (Original) | v7 (Portal) |
|---|---|---|
| **Nombre** | WhatsApp Bot - Demo Template v6 Redis | WhatsApp Bot - v7 Portal OmniCliente |
| **Entrada** | YCloud webhook directo | Portal webhook (formato estandarizado) |
| **Salida** | 19 nodos envían a api.ycloud.com | 18 nodos envían al portal (callbackUrl) |
| **Excepción** | — | Upload Audio sigue directo a YCloud |
| **Autenticación salida** | X-API-Key header (YCloud) | Sin auth (token en body) |
| **Credenciales** | httpHeaderAuth en todos los envíos | Solo en Upload Audio y Download Audio |

### 4.2 Nodos de Entrada Modificados

#### Filter Event Type
- **Antes:** `$json.body.whatsappInboundMessage.type === 'text'`
- **Después:** `$json.body.event === 'new_message'`

#### Has Message?
- **Antes:** Chequeaba campos nativos YCloud
- **Después:** `$json.body.message?.content || $json.body.message?.id`

#### Extract Message Data
- **Antes:** Parseaba estructura YCloud (`whatsappInboundMessage`, `whatsappBusinessAccount`)
- **Después:** Parsea estructura portal:
  ```
  phoneNumber ← body.contact.phone
  contactName ← body.contact.name
  messageType ← body.message.type
  textContent ← body.message.content
  callbackUrl ← body.callback_url
  callbackToken ← body.callback_token
  conversationId ← body.conversation_id
  channelId ← body.channel_id
  ```

#### Redis Push
- **Antes:** Solo datos del mensaje
- **Después:** También almacena `callbackUrl`, `callbackToken`, `conversationId`, `channelId`

#### Combine Messages
- **Antes:** Solo combinaba mensajes del buffer
- **Después:** También extrae y expone `callbackUrl`, `callbackToken`, `conversationId`, `channelId` para que los nodos de envío los usen

### 4.3 Nodos de Envío Convertidos (18 nodos)

Todos los nodos HTTP que enviaban a YCloud fueron transformados así:

| Cambio | Antes | Después |
|---|---|---|
| URL | `https://api.ycloud.com/v2/whatsapp/messages` | `={{ $('Combine Messages').first().json.callbackUrl }}` |
| Authentication | `predefinedCredentialType` | `none` |
| Credentials | `httpHeaderAuth: "Header Auth WB YcloudDemo"` | Eliminado |
| Body | Payload YCloud original | Payload YCloud + `callback_token` + `conversation_id` + `channel_id` |

#### Lista de 18 nodos convertidos:
1. Send Cancel Denied
2. Send Terms Response
3. Send Audio Response
4. Send Escalation Message
5. Send Text Response
6. Send Payment Expired
7. Send Payment Invalid
8. Send Duplicate Transfer ID
9. Send Payment Confirmed
10. Send Keep Message
11. Send Cancel Success
12. Send Cancel Confirmation (ternary con `JSON.stringify`)
13. Send Terms (IIFE con lógica condicional)
14. Send Limit Message
15. Send No Slots
16. Send Available Times (lista interactiva)
17. Send Day Buttons (lista interactiva)
18. Send Services List (lista interactiva)

#### Nodo NO convertido:
- **Upload Audio to WhatsApp**: Sigue enviando directo a `api.ycloud.com/v2/whatsapp/media/` porque es una subida de archivo binario que requiere autenticación directa.

### 4.4 Patrones de jsonBody Transformados

Se identificaron y transformaron 4 patrones distintos de jsonBody:

1. **Template literal** (`={\n "from":...`): Inyección directa de campos portal
2. **JSON.stringify** (`JSON.stringify({...})`): Inyección dentro del objeto serializado
3. **IIFE** (`(function(){...})()`): Inyección en ambas ramas del condicional
4. **Ternary** (`condicion ? JSON.stringify(A) : JSON.stringify(B)`): Inyección en ambas opciones

---

## 5. Script de Transformación

**Archivo:** `N8N/TEMPLATES/kellscapilar/transform-v6-to-v7.js`

Script Node.js que transforma automáticamente el JSON del workflow v6 al v7.

```bash
cd N8N/TEMPLATES/kellscapilar
node transform-v6-to-v7.js
# Output: WhatsApp_Bot_v7_Portal_OmniCliente.json
```

**Qué hace:**
1. Cambia el nombre del workflow
2. Actualiza Filter Event Type para formato portal
3. Actualiza Has Message? para formato portal
4. Reescribe Extract Message Data para parsear payload portal
5. Actualiza Redis Push para almacenar campos callback
6. Actualiza Combine Messages para exponer campos callback
7. Actualiza Audio-to-Text Set node para pasar campos callback
8. Transforma 18 nodos de envío (URL, auth, credentials, body)
9. Actualiza nota de documentación interna

---

## 6. Configuración Necesaria en N8N

### 6.1 Importar Workflow
1. Abrir N8N → Workflows → Import from File
2. Seleccionar `WhatsApp_Bot_v7_Portal_OmniCliente.json`
3. Activar el workflow

### 6.2 Credenciales Requeridas
| Credencial | Uso | Nodos |
|---|---|---|
| Google Sheets OAuth | Lectura/escritura de datos | Múltiples nodos |
| Redis | Buffer de mensajes | Redis Push/Get/Delete |
| SMTP | Envío de emails | Email nodes |
| OpenAI | GPT-4o Mini para respuestas | AI Agent |
| YCloud Header Auth | Solo para Upload/Download Audio | 2 nodos |

### 6.3 Variables de Entorno N8N
- El webhook de entrada del workflow se configura automáticamente
- La URL del webhook aparece en el nodo "WhatsApp Webhook"

---

## 7. Configuración en Portal OmniCliente

### 7.1 Crear Canal WhatsApp
1. Login como Admin → Canales → Nuevo Canal
2. Tipo: WhatsApp
3. Teléfono: número del negocio
4. API Key YCloud: en credentials_json
5. Guardar → se genera `webhook_secret`

### 7.2 Configurar YCloud Webhook
En la consola de YCloud:
- URL: `https://automatizatech.cl/api-omnichannel.php?route=webhook/ycloud?channel_id={ID}&secret={SECRET}`
- Events: `whatsapp.inbound_message.received`

### 7.3 Configurar Bot N8N
1. Admin → Bots → Seleccionar bot del canal
2. En "Webhook N8N (opcional)": pegar la URL del webhook N8N
3. Activar bot

---

## 8. Plan de Contingencia (Fallback)

Si el portal no responde, N8N puede usar un health check:

```
GET https://automatizatech.cl/api-omnichannel.php?route=health
→ {"status":"ok","timestamp":"2026-03-24T01:00:00"}
```

En N8N se puede agregar un nodo de verificación antes de enviar la respuesta:
- Si health check falla → enviar directamente a YCloud (fallback a v6)
- Si health check OK → enviar al portal (v7 normal)

---

## 9. Diagrama de Componentes

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   WhatsApp   │     │  Portal          │     │     N8N         │
│   (YCloud)   │────▶│  OmniCliente     │────▶│  Workflow v7    │
│              │◀────│  (PHP Backend)   │◀────│                 │
└──────────────┘     └──────────────────┘     └─────────────────┘
                            │                        │
                            ▼                        ▼
                     ┌──────────────┐         ┌─────────────┐
                     │   MySQL DB   │         │   Redis     │
                     │  (messages,  │         │  (buffer)   │
                     │  convs, etc) │         └─────────────┘
                     └──────────────┘               │
                            │                       ▼
                     ┌──────────────┐         ┌─────────────┐
                     │   React SPA  │         │ Google      │
                     │  (Bandeja    │         │ Sheets/Cal  │
                     │  Unificada)  │         └─────────────┘
                     └──────────────┘               │
                                                    ▼
                                              ┌─────────────┐
                                              │  OpenAI     │
                                              │  GPT-4o     │
                                              └─────────────┘
```

---

## 10. Archivos Relacionados

| Archivo | Ubicación | Descripción |
|---|---|---|
| omnichannel-controller.php | `/` (raíz) | Controlador PHP con funciones N8N |
| api-omnichannel.php | `/` (raíz) | Rutas API incluyendo `/webhook/n8n-callback` |
| WhatsApp_Bot_v6_KellsCapilar.json | `/N8N/TEMPLATES/kellscapilar/` | Workflow original (v6) |
| WhatsApp_Bot_v7_Portal_OmniCliente.json | `/N8N/TEMPLATES/kellscapilar/` | Workflow transformado (v7) |
| transform-v6-to-v7.js | `/N8N/TEMPLATES/kellscapilar/` | Script de transformación |
