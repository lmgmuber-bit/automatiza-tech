# Guía: Implementar Tracking de Consumo OpenAI en Nuevos Workflows N8N

## Estrategia de Facturación
✅ **Decisión:** Una sola cuenta OpenAI + Una sola API Key + Tracking por `client_identifier`

```
[Bot Cliente A] ──┐
[Bot Cliente B] ──┼──► [api-chat-proxy.php] ──► [OpenAI API]
[Bot Cliente C] ──┘           │
                              ▼
                    [ai_usage_log en MySQL]
                    (registra quién gastó qué)
```

**Ventajas:**
- Facturación centralizada (tú pagas, tú cobras con margen)
- El cliente no necesita cuenta OpenAI
- Control total del consumo por cliente
- Un solo lugar para administrar todo

---

## Paso 1: Asegurarse de que la BD esté lista

Ejecutar una sola vez en MySQL:

```sql
CREATE TABLE IF NOT EXISTS ai_usage_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    client_identifier VARCHAR(100) DEFAULT NULL,
    prompt_tokens INT DEFAULT 0,
    completion_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    model_used VARCHAR(50) NOT NULL,
    cost_estimated DECIMAL(10, 6) DEFAULT 0.000000,
    request_endpoint VARCHAR(100) DEFAULT 'chat/completions',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_client (client_identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Paso 2: En N8N - Usar HTTP Request en lugar de nodo OpenAI

### Configuración del Nodo HTTP Request

| Campo | Valor |
|-------|-------|
| **Method** | POST |
| **URL** | `https://automatizatech.cl/api-chat-proxy.php` |
| **Authentication** | None |
| **Body Content Type** | JSON |

### Body Parameters (JSON)

```json
{
  "model": "gpt-4o",
  "user_id": 1,
  "client_identifier": "{{ $json.client_email || $json.phone || 'interno' }}",
  "messages": [
    {
      "role": "system",
      "content": "Tu prompt de sistema aquí..."
    },
    {
      "role": "user", 
      "content": "{{ $json.user_message }}"
    }
  ]
}
```

### Mapeo de Variables Comunes

| Variable N8N | Descripción | Ejemplo |
|--------------|-------------|---------|
| `$json.client_email` | Email del cliente desde Webhook | cliente@email.com |
| `$json.phone` | Teléfono desde WhatsApp | +56912345678 |
| `$json.contactName` | Nombre del contacto | Juan Pérez |
| `$('Webhook').item.json.body.email` | Email desde form web | - |

---

## Paso 3: Procesar la Respuesta

El proxy devuelve la respuesta completa de OpenAI. Para extraer el mensaje:

```javascript
// En un nodo Code después del HTTP Request
const response = $json;
const aiMessage = response.choices[0].message.content;
const tokensUsados = response.usage.total_tokens;

return {
  json: {
    reply: aiMessage,
    tokens: tokensUsados,
    model: response.model
  }
};
```

---

## Identificadores de Cliente (IMPORTANTE)

Usa un formato consistente para que el dashboard agrupe bien los datos:

| Tipo | Formato | Ejemplo |
|------|---------|---------|
| **Cliente Externo** | `cliente_nombreempresa` | `cliente_kells_capilar` |
| **Uso Interno** | `interno_nombreflujo` | `interno_propuestas` |
| **Demo/Pruebas** | `demo_nombreprospecto` | `demo_mg_muebles` |

### Ejemplos de client_identifier por flujo:

```javascript
// Bot WhatsApp para Kells Capilar
"client_identifier": "cliente_kells_capilar"

// Bot WhatsApp para MG Muebles
"client_identifier": "cliente_mg_muebles"

// Generador de propuestas interno
"client_identifier": "interno_propuestas"

// Agente web de AutomatizaTech
"client_identifier": "interno_agente_web"

// Demo para prospecto nuevo
"client_identifier": "demo_clinica_dental"
```

---

## Facturación Sugerida

| Costo OpenAI | Tu Precio (30% margen) | Tu Precio (50% margen) |
|--------------|------------------------|------------------------|
| $1.00 USD | $1.30 USD | $1.50 USD |
| $5.00 USD | $6.50 USD | $7.50 USD |
| $10.00 USD | $13.00 USD | $15.00 USD |

Consulta SQL para generar reporte de facturación:
```sql
SELECT 
    client_identifier AS cliente,
    COUNT(*) AS peticiones,
    SUM(total_tokens) AS tokens_totales,
    ROUND(SUM(cost_estimated), 4) AS costo_openai_usd,
    ROUND(SUM(cost_estimated) * 1.30, 2) AS facturar_30_pct,
    ROUND(SUM(cost_estimated) * 1.50, 2) AS facturar_50_pct
FROM ai_usage_log
WHERE MONTH(created_at) = MONTH(NOW())
  AND client_identifier LIKE 'cliente_%'
GROUP BY client_identifier
ORDER BY costo_openai_usd DESC;
```

---

## Ver el Consumo

### Dashboard Visual
Acceder a: `https://automatizatech.cl/admin-ai-dashboard.php`
(Requiere estar logueado como admin en WordPress)

### Consulta SQL Directa
```sql
-- Consumo por cliente este mes
SELECT 
    client_identifier,
    COUNT(*) as requests,
    SUM(total_tokens) as tokens,
    SUM(cost_estimated) as costo_usd
FROM ai_usage_log
WHERE MONTH(created_at) = MONTH(NOW())
GROUP BY client_identifier
ORDER BY costo_usd DESC;
```

---

## Template de Nodo Listo para Copiar

Archivo disponible en: `N8N/TEMPLATES/node-openai-proxy-template.json`

Instrucciones:
1. Abrir N8N
2. Ctrl+V en el canvas
3. Conectar a tu flujo
4. Ajustar el `client_identifier` según tu caso

---

## Modelos y Precios (Enero 2026)

| Modelo | Input (por 1M tokens) | Output (por 1M tokens) |
|--------|----------------------|------------------------|
| gpt-4o | $2.50 | $10.00 |
| gpt-4o-mini | $0.15 | $0.60 |
| gpt-4-turbo | $10.00 | $30.00 |
| gpt-3.5-turbo | $0.50 | $1.50 |

El proxy calcula automáticamente el costo según el modelo usado.
