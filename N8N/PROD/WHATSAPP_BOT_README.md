# 📱 WhatsApp Tech Bot - Guía de Implementación

## 📁 Archivos Creados

| Archivo | Descripción |
|---------|-------------|
| `WhatsApp_Tech_Principal.json` | Recepción de mensajes WhatsApp (texto, audio, imagen) |
| `WhatsApp_Tech_Buffer_Agent.json` | Buffer de 5 segundos + Agente IA |
| `WhatsApp_Tech_Agendamiento.json` | Gestión de citas (crear, cancelar, reprogramar) |
| `WhatsApp_Tech_Recordatorios.json` | Envío de recordatorios con rate limiting |

---

## 🔧 Configuración Requerida

### 1. Meta Business / WhatsApp API

1. Ve a [Meta for Developers](https://developers.facebook.com/)
2. Crea una app de tipo "Business"
3. Agrega el producto "WhatsApp"
4. Obtén:
   - **Phone Number ID**: El ID de tu número de WhatsApp Business
   - **Business Account ID**: Tu ID de cuenta de negocios
   - **Access Token**: Token permanente (no el temporal de prueba)

### 2. Configurar Webhook en Meta

1. En tu app de Meta, ve a WhatsApp > Configuration
2. Configura el Webhook:
   - **Callback URL**: `https://tu-n8n-url.com/webhook/whatsapp-webhook-tech`
   - **Verify Token**: El mismo que configures en N8N
3. Suscríbete a los campos:
   - `messages`
   - `messaging_postbacks`

### 3. Credenciales en N8N

Crea las siguientes credenciales en N8N:

#### WhatsApp Business Cloud API
```
Nombre: WhatsApp Business Cloud API
Access Token: [Tu token permanente de Meta]
Business Account ID: [Tu Business Account ID]
```

#### OpenAI API (ya la tienes)
```
ID: g52IEXpRfN5r7jKw
Nombre: OpenAi account
```

#### Google Calendar OAuth2 (para agendamiento)
```
Scopes: https://www.googleapis.com/auth/calendar
```

#### Google Sheets OAuth2 (para registro de citas)
```
Scopes: https://www.googleapis.com/auth/spreadsheets
```

#### Redis (para buffer de mensajes)
```
Host: localhost (o tu servidor Redis)
Port: 6379
```

---

## 🔄 Reemplazos Necesarios en los JSON

Busca y reemplaza estos valores en todos los archivos:

| Placeholder | Reemplazar con |
|-------------|----------------|
| `YOUR_WHATSAPP_CREDENTIAL_ID` | ID de credencial WhatsApp en N8N |
| `YOUR_PHONE_NUMBER_ID` | Phone Number ID de Meta |
| `YOUR_GOOGLE_CALENDAR_CREDENTIAL_ID` | ID de credencial Google Calendar |
| `YOUR_GOOGLE_SHEETS_CREDENTIAL_ID` | ID de credencial Google Sheets |
| `YOUR_GOOGLE_SHEET_ID` | ID de tu Google Sheet de citas |
| `YOUR_REDIS_CREDENTIAL_ID` | ID de credencial Redis |

---

## 📊 Estructura Google Sheets (Citas)

Crea una hoja llamada "Citas" con las siguientes columnas:

| Columna | Tipo | Descripción |
|---------|------|-------------|
| Fecha | DateTime | Fecha y hora de la cita |
| Nombre | String | Nombre del contacto |
| Telefono | String | Número de WhatsApp |
| Estado | String | Agendada / Cancelada / Completada |
| Tipo | String | Demo / Consultoría / Soporte |
| EventId | String | ID del evento en Google Calendar |
| FechaCreacion | DateTime | Cuándo se creó |
| FechaCancelacion | DateTime | Cuándo se canceló (si aplica) |
| Recordatorio24h | DateTime | Cuándo se envió recordatorio 24h |
| Recordatorio1h | DateTime | Cuándo se envió recordatorio 1h |

---

## 📝 Plantillas de WhatsApp (requeridas por Meta)

⚠️ **Corrección del 2026-09-24.** Esta sección decía que los recordatorios usaban las plantillas `recordatorio_24h` y `recordatorio_1h`. **Nunca fue así**: esas plantillas no se crearon y los flujos mandaban `text` o `interactive`. Meta solo entrega un mensaje que no es plantilla si el cliente escribió al número en las últimas 24 h; a quien agenda por la web lo acepta (`200` + `wamid`) y lo descarta 3 s después con el error **131047**, que llega por el webhook y nadie procesaba.

Las plantillas reales (9, Utility), su estado, el código de cada flujo, las pruebas y cómo aplicar o revertir están en [`wa-plantillas-2026-09-24/README.md`](wa-plantillas-2026-09-24/README.md): `recordatorio_cita_72h`, `recordatorio_cita_24h`, `recordatorio_cita_1h`, `recordatorio_seguimiento_hoy`, `recordatorio_seguimiento_manana`, `aviso_demo`, `aviso_reunion_prospecto`, `aviso_reunion_seguimiento` y `alerta_argos`.

---

## 🛡️ Protección Anti-Ban de WhatsApp

El sistema incluye varias medidas para evitar bloqueos:

### 1. **Buffer de Mensajes (5 segundos)**
- Espera 5 segundos antes de responder
- Agrupa mensajes fragmentados del usuario
- Evita respuestas múltiples seguidas

### 2. **Rate Limiting en Recordatorios**
- Espera aleatoria de 10-30 segundos entre cada envío
- Máximo 10 recordatorios por hora
- Ejecución cada hora (no continua)

### 3. **Plantillas aprobadas para escribir fuera de la ventana de 24 h**
- Recordatorios, seguimientos, avisos de reunión y alertas de ARGOS pasan a plantillas Utility (estado y detalle en `wa-plantillas-2026-09-24/`).
- Un mensaje que no es plantilla solo llega si el cliente escribió en las últimas 24 h; con WhatsApp, `success` + `wamid` **no** significa entregado.

---

## 🔌 Flujo de Funcionamiento

```
┌─────────────────────────────────────────────────────────────┐
│                    USUARIO EN WHATSAPP                       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              WhatsApp_Tech_Principal                         │
│  • Recibe webhook de Meta                                    │
│  • Identifica tipo: texto / audio / imagen / interactivo    │
│  • Transcribe audio con Whisper                             │
│  • Analiza imágenes con GPT-4 Vision                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              WhatsApp_Tech_Buffer_Agent                      │
│  • Buffer en Redis (espera 5 segundos)                      │
│  • Combina mensajes fragmentados                            │
│  • Consulta tipo de cambio USD/CLP                          │
│  • Procesa con Agente IA (GPT-4o-mini)                      │
│  • Detecta acciones especiales                              │
│  • Envía respuesta por WhatsApp                             │
└─────────────────────────────────────────────────────────────┘
                              │
                  ┌───────────┴───────────┐
                  ▼                       ▼
┌─────────────────────────┐   ┌─────────────────────────┐
│ WhatsApp_Tech_          │   │ WhatsApp_Tech_          │
│ Agendamiento            │   │ Recordatorios           │
│                         │   │                         │
│ • Ver horarios          │   │ • Cron cada hora        │
│ • Crear cita            │   │ • Rate limit 10-30s     │
│ • Cancelar cita         │   │ • Max 10/hora           │
│ • Reprogramar           │   │ • Templates Meta        │
│ • Google Calendar sync  │   │ • Marca enviados        │
└─────────────────────────┘   └─────────────────────────┘
```

---

## 🚀 Orden de Importación en N8N

1. Importa `WhatsApp_Tech_Recordatorios.json`
2. Importa `WhatsApp_Tech_Agendamiento.json`
3. Importa `WhatsApp_Tech_Buffer_Agent.json`
4. Importa `WhatsApp_Tech_Principal.json`

---

## ⚠️ Notas Importantes

1. **Redis es opcional**: Si no tienes Redis, puedes usar el nodo "Static Data" de N8N o almacenar el buffer en Google Sheets.

2. **Ventana de 24 horas**: WhatsApp permite mensajes gratuitos solo dentro de 24h desde el último mensaje del usuario. Para recordatorios fuera de esta ventana, necesitas templates aprobados.

3. **Pruebas**: Usa primero números de prueba de Meta antes de conectar tu número real.

4. **Monitoreo**: Activa las notificaciones de error en N8N para detectar problemas rápidamente.

---

## 📞 Soporte

Si tienes problemas con la configuración:
- Web: https://www.automatizatech.cl
- WhatsApp: +56 9 2700 2984
- Email: contacto@automatizatech.cl
