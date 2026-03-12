# 🐾 Instrucciones para Crear Bot(s) de PetsGo — Cliente: Alexiandra Andrade

## Contexto del Proyecto

**Cliente:** Alexiandra Andrade  
**Proyecto:** PetsGo — Marketplace de Mascotas  
**Empresa proveedora:** AutomatizaTech  
**Módulo:** Chatbot con IA (WhatsApp Business + Web)

El bot (o bots) de PetsGo deben usar **el sistema centralizado de OpenAI de AutomatizaTech**. Esto significa que **NO se usa una API Key propia del cliente**, sino que todas las llamadas pasan por un proxy PHP que registra automáticamente el consumo para facturación.

---

## ⚠️ REGLA CRÍTICA: No usar nodo OpenAI nativo de N8N

En lugar del nodo nativo `OpenAI` de N8N, **SIEMPRE** usar un nodo **HTTP Request** que apunte al proxy de AutomatizaTech. Esto es obligatorio para que el consumo quede registrado.

---

## Arquitectura

```
[Usuario WhatsApp/Web]
        │
        ▼
[Webhook N8N (entrada)]
        │
        ▼
[Lógica del Bot / Flujo conversacional]
        │
        ▼
[HTTP Request → api-chat-proxy.php]  ◄── ESTE ES EL NODO CLAVE
        │
        ├──► [OpenAI API] (el proxy hace la llamada)
        │
        └──► [ai_usage_log en MySQL] (el proxy registra consumo)
        │
        ▼
[Parsear Respuesta]
        │
        ▼
[Responder al usuario]
```

---

## Configuración del Nodo HTTP Request (OpenAI con Tracking)

### Parámetros del Nodo

| Campo | Valor |
|-------|-------|
| **Tipo de nodo** | HTTP Request |
| **Method** | `POST` |
| **URL** | `https://automatizatech.cl/api-chat-proxy.php` |
| **Authentication** | None (no requiere) |
| **Body Content Type** | JSON |
| **Timeout** | 60000 ms |

### Body Parameters (JSON)

```json
{
  "model": "gpt-4o-mini",
  "user_id": 1,
  "client_identifier": "cliente_petsgo",
  "messages": [
    {
      "role": "system",
      "content": "AQUÍ VA EL PROMPT DE SISTEMA DEL BOT DE PETSGO"
    },
    {
      "role": "user",
      "content": "{{ $json.user_message }}"
    }
  ]
}
```

### Campos críticos explicados

| Campo | Valor fijo | Descripción |
|-------|-----------|-------------|
| `model` | `gpt-4o-mini` | Modelo recomendado para bots (económico y rápido). Usar `gpt-4o` si necesita más inteligencia. |
| `user_id` | `1` | ID del admin de AutomatizaTech |
| `client_identifier` | `"cliente_petsgo"` | **OBLIGATORIO** — Identificador único para trackear el consumo de este cliente. SIEMPRE debe ser `cliente_petsgo`. |
| `messages` | array | Array de mensajes con formato OpenAI estándar (system + user) |

---

## Nodo JSON Completo para Copiar en N8N

Copia este JSON y pégalo (Ctrl+V) directamente en el canvas de N8N:

```json
{
  "meta": {
    "instanceId": "petsgo-openai-proxy"
  },
  "nodes": [
    {
      "parameters": {
        "method": "POST",
        "url": "https://automatizatech.cl/api-chat-proxy.php",
        "sendBody": true,
        "contentType": "json",
        "bodyParameters": {
          "parameters": [
            {
              "name": "model",
              "value": "gpt-4o-mini"
            },
            {
              "name": "user_id",
              "value": "1"
            },
            {
              "name": "client_identifier",
              "value": "cliente_petsgo"
            },
            {
              "name": "messages",
              "value": "={{ JSON.stringify([\n  {\n    \"role\": \"system\",\n    \"content\": \"Eres el asistente virtual de PetsGo, un marketplace de mascotas. Ayudas a los usuarios con información sobre productos, tiendas, pedidos y servicios para mascotas. Sé amable, claro y conciso.\"\n  },\n  {\n    \"role\": \"user\",\n    \"content\": $json.user_message || $json.chatInput || $json.message || $json.body?.message || ''\n  }\n]) }}"
            }
          ]
        },
        "options": {
          "timeout": 60000
        }
      },
      "id": "petsgo-http-openai-proxy",
      "name": "🤖 PetsGo AI (Con Tracking)",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [500, 300],
      "notesInFlow": true,
      "notes": "Envía a OpenAI vía proxy AutomatizaTech. Consumo registrado como cliente_petsgo."
    },
    {
      "parameters": {
        "jsCode": "// Procesar respuesta de OpenAI\nconst response = $input.first().json;\n\n// Extraer mensaje del asistente\nconst aiMessage = response.choices?.[0]?.message?.content || 'Lo siento, no pude procesar tu mensaje.';\n\n// Datos de uso (ya guardados en BD por el proxy)\nconst usage = response.usage || {};\n\nreturn {\n  json: {\n    reply: aiMessage,\n    model: response.model || 'unknown',\n    tokens_input: usage.prompt_tokens || 0,\n    tokens_output: usage.completion_tokens || 0,\n    tokens_total: usage.total_tokens || 0\n  }\n};"
      },
      "id": "petsgo-code-parse-response",
      "name": "📊 Parsear Respuesta PetsGo",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [720, 300],
      "notesInFlow": true,
      "notes": "Extrae el mensaje y tokens. El registro en BD ya fue hecho por el proxy."
    }
  ],
  "connections": {
    "🤖 PetsGo AI (Con Tracking)": {
      "main": [
        [
          {
            "node": "📊 Parsear Respuesta PetsGo",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  }
}
```

---

## Si hay Múltiples Bots para PetsGo

Si el proyecto requiere más de un bot (ej: bot de atención, bot de ventas, bot de soporte), usar sub-identificadores:

| Bot | `client_identifier` |
|-----|---------------------|
| Bot principal / general | `cliente_petsgo` |
| Bot de ventas | `cliente_petsgo_ventas` |
| Bot de soporte | `cliente_petsgo_soporte` |
| Bot de tracking de pedidos | `cliente_petsgo_tracking` |

Todos deben empezar con `cliente_petsgo` para que el sistema los agrupe en los reportes de facturación.

---

## Modelos Disponibles y Costos

| Modelo | Input (por 1M tokens) | Output (por 1M tokens) | Recomendado para |
|--------|----------------------|------------------------|------------------|
| `gpt-4o-mini` | $0.15 | $0.60 | **Bots conversacionales** (recomendado) |
| `gpt-4o` | $2.50 | $10.00 | Tareas complejas, análisis |
| `gpt-4-turbo` | $10.00 | $30.00 | Máxima calidad (costoso) |
| `gpt-3.5-turbo` | $0.50 | $1.50 | Legacy, no recomendado |

**Recomendación para PetsGo:** Usar `gpt-4o-mini` para el bot conversacional. Es 16x más barato que `gpt-4o` y suficientemente inteligente para atención al cliente.

---

## Convención para el System Prompt

El prompt de sistema debe incluir:

1. **Identidad**: Nombre del bot y de la empresa
2. **Contexto**: Qué es PetsGo (marketplace de mascotas)
3. **Capacidades**: Qué puede hacer el bot
4. **Limitaciones**: Qué NO debe hacer
5. **Tono**: Amable, profesional, conciso
6. **Idioma**: Español

Ejemplo base:

```
Eres el asistente virtual de PetsGo, un marketplace de mascotas en Chile. 
Tu nombre es [NOMBRE DEL BOT].

Puedes ayudar con:
- Información sobre productos para mascotas
- Estado de pedidos
- Información de tiendas registradas
- Preguntas frecuentes sobre envíos y pagos
- Recomendaciones de productos

NO debes:
- Inventar precios o disponibilidad
- Dar consejos veterinarios médicos
- Compartir datos personales de otros usuarios
- Procesar pagos directamente

Si no sabes algo, indica amablemente que derivarás la consulta a un agente humano.
Responde siempre en español y de forma concisa.
```

---

## Cómo Procesar la Respuesta

La respuesta del proxy tiene el mismo formato que la API de OpenAI:

```json
{
  "id": "chatcmpl-...",
  "object": "chat.completion",
  "model": "gpt-4o-mini",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "¡Hola! Soy el asistente de PetsGo..."
      },
      "finish_reason": "stop"
    }
  ],
  "usage": {
    "prompt_tokens": 150,
    "completion_tokens": 80,
    "total_tokens": 230
  }
}
```

Para extraer el mensaje de respuesta en N8N:
- **Expresión directa**: `{{ $json.choices[0].message.content }}`  
- **Nodo Code**: `$input.first().json.choices[0].message.content`

---

## Conexión con WhatsApp (si aplica)

Si el bot se conecta por WhatsApp Business API:

```
[WhatsApp Webhook] → [Extraer mensaje] → [🤖 PetsGo AI] → [📊 Parsear] → [Responder WhatsApp]
```

El campo del mensaje del usuario típicamente viene en:
- `$json.body.message` (Evolution API)
- `$json.entry[0].changes[0].value.messages[0].text.body` (Meta Cloud API)
- `$json.message.text` (Baileys/WPPConnect)

Ajustar la expresión del `messages` según la API de WhatsApp que se use.

---

## Verificación Post-Implementación

Una vez el bot esté funcionando, verificar que el tracking funciona:

1. Enviar un mensaje de prueba al bot
2. Verificar en la base de datos:
```sql
SELECT * FROM ai_usage_log 
WHERE client_identifier LIKE 'cliente_petsgo%' 
ORDER BY created_at DESC 
LIMIT 5;
```
3. Verificar en el dashboard: `https://automatizatech.cl/admin-ai-dashboard.php`
4. O en el reporte: `https://automatizatech.cl/reporte-consumo-ai.php`

Si no aparecen registros, revisar:
- Que la URL del proxy sea correcta y accesible
- Que el `client_identifier` sea exactamente `cliente_petsgo`
- Que el body sea JSON válido
- Logs de error en el servidor de AutomatizaTech

---

## Resumen de Checklist

- [ ] Crear workflow en N8N para el bot de PetsGo
- [ ] Usar nodo **HTTP Request** (NO nodo OpenAI nativo)
- [ ] URL: `https://automatizatech.cl/api-chat-proxy.php`
- [ ] `client_identifier`: `"cliente_petsgo"`
- [ ] Model: `"gpt-4o-mini"` (recomendado)
- [ ] Agregar nodo Code para parsear respuesta
- [ ] Personalizar system prompt para PetsGo
- [ ] Conectar con webhook de WhatsApp/Web
- [ ] Probar y verificar que el consumo aparece en `ai_usage_log`
- [ ] NO poner API Key de OpenAI en ningún lado del workflow

---

*Documento generado para el equipo de desarrollo de bots — AutomatizaTech*  
*Proyecto: PetsGo (Alexiandra Andrade)*  
*Fecha: Febrero 2026*
