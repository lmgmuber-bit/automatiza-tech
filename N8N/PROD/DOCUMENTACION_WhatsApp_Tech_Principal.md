# 📱 Documentación del Workflow: WhatsApp Tech - Principal

## 📋 Índice
1. [Resumen General](#resumen-general)
2. [Diagrama de Arquitectura General](#diagrama-de-arquitectura-general)
3. [Flujos Principales](#flujos-principales)
4. [Detalle Nodo por Nodo](#detalle-nodo-por-nodo)
5. [Integraciones](#integraciones)
6. [Estados Redis](#estados-redis)

---

## 🎯 Resumen General

**Nombre:** WhatsApp Tech - Principal (PROD)  
**Propósito:** Bot de WhatsApp inteligente para AutomatizaTech que maneja consultas, agendamiento de citas, cancelaciones, reprogramaciones y respuestas a recordatorios.

### Capacidades Principales:
- ✅ Recepción de mensajes de texto, audio e imágenes
- ✅ Agente IA conversacional (GPT-4o)
- ✅ **Respuestas por audio** cuando el usuario envía nota de voz
- ✅ Agendamiento de demos/reuniones
- ✅ Cancelación y reprogramación de citas
- ✅ Manejo de recordatorios con confirmación
- ✅ Información de planes y precios
- ✅ Escalamiento a soporte
- ✅ **Detección de usuarios desde la web** (mensaje predeterminado)

---

## 🏗️ Diagrama de Arquitectura General

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         WEBHOOK DE ENTRADA                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────┐    ┌──────────────────┐                              │
│   │  Verify Webhook  │    │  WhatsApp        │                              │
│   │     (GET)        │    │  Webhook (POST)  │                              │
│   └────────┬─────────┘    └────────┬─────────┘                              │
│            │                       │                                         │
│            ▼                       ▼                                         │
│   ┌──────────────────┐    ┌──────────────────┐                              │
│   │ Respond Challenge│    │   Has Message?   │                              │
│   └──────────────────┘    └────────┬─────────┘                              │
│                                    │                                         │
└────────────────────────────────────┼─────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    EXTRACCIÓN Y DEDUPLICACIÓN                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────┐    ┌──────────────────┐                              │
│   │ Extract Message  │───▶│  Deduplication   │                              │
│   │     Data         │    │   (Evita dobles) │                              │
│   └──────────────────┘    └────────┬─────────┘                              │
│                                    │                                         │
└────────────────────────────────────┼─────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      ROUTER POR TIPO DE MENSAJE                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                        ┌──────────────────┐                                  │
│                        │   Message Type   │                                  │
│                        │     (Switch)     │                                  │
│                        └────────┬─────────┘                                  │
│                                 │                                            │
│        ┌────────────┬───────────┼───────────┬────────────┐                  │
│        ▼            ▼           ▼           ▼            ▼                  │
│   ┌─────────┐ ┌──────────┐ ┌─────────┐ ┌──────────┐ ┌─────────┐           │
│   │  TEXT   │ │  AUDIO   │ │  IMAGE  │ │INTERACTIVE│ │ OTHERS  │           │
│   └────┬────┘ └────┬─────┘ └────┬────┘ └────┬─────┘ └─────────┘           │
│        │           │            │           │                               │
└────────┼───────────┼────────────┼───────────┼───────────────────────────────┘
         │           │            │           │
         ▼           ▼            ▼           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      PROCESAMIENTO POR TIPO                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  TEXT:                    AUDIO:                    IMAGE:                   │
│  ┌────────────────┐      ┌────────────────┐       ┌────────────────┐        │
│  │ Process Text   │      │ Process Audio  │       │ Process Image  │        │
│  │ + Redis Buffer │      │ + Download     │       │ + Download     │        │
│  │ + States Check │      │ + Whisper      │       │ + GPT-4 Vision │        │
│  └────────────────┘      └────────────────┘       └────────────────┘        │
│                                                                              │
│  INTERACTIVE (Botones):                                                      │
│  ┌─────────────────────────────────────────────────────────────────┐        │
│  │ Process Interactive → Button Action (17 tipos de botones)       │        │
│  └─────────────────────────────────────────────────────────────────┘        │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         AGENTE IA (GPT-4o)                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐     │
│   │  Get Exchange    │───▶│    Merge Data    │───▶│  Agente IA Tech  │     │
│   │     Rate         │    │   (chatInput)    │    │    WhatsApp      │     │
│   └──────────────────┘    └──────────────────┘    └────────┬─────────┘     │
│                                                             │                │
│                           ┌─────────────────────────────────┤                │
│                           │                                 │                │
│                           ▼                                 ▼                │
│                    ┌──────────────┐                 ┌──────────────┐        │
│                    │  Cerebro     │                 │ Redis Chat   │        │
│                    │   GPT-4o     │                 │   Memory     │        │
│                    └──────────────┘                 └──────────────┘        │
│                                                                              │
└────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PARSER DE ACCIONES ESPECIALES                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────┐    ┌──────────────────┐                              │
│   │ Parse Response   │───▶│   Route Action   │                              │
│   │    Actions       │    │    (Switch)      │                              │
│   └──────────────────┘    └────────┬─────────┘                              │
│                                    │                                         │
│     ┌──────────┬──────────┬────────┼────────┬──────────┬──────────┬──────────┐│
│     ▼          ▼          ▼        ▼        ▼          ▼          ▼          ▼│
│  ┌──────┐ ┌────────┐ ┌────────┐ ┌──────┐ ┌──────┐ ┌────────┐ ┌────────┐     │
│  │ TEXT │ │CALENDAR│ │ CANCEL │ │RESCHD│ │SUPPRT│ │ PLANS  │ │ AUDIO  │     │
│  │NORMAL│ │        │ │        │ │      │ │      │ │        │ │RESPONSE│     │
│  └──────┘ └────────┘ └────────┘ └──────┘ └──────┘ └────────┘ └────────┘     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Flujos Principales

### 1️⃣ Flujo de Agendamiento de Cita

```
Usuario dice "quiero agendar"
         │
         ▼
┌─────────────────────┐
│ Agente detecta      │
│ <<ACTION:SHOW_      │
│ CALENDAR>>          │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐     ┌─────────────────────┐
│ Get Appointments    │────▶│   Generate Days     │
│ Config (WordPress)  │     │ (3 días disponibles)│
└─────────────────────┘     └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Send Calendar       │
                            │ Buttons (Lista)     │
                            │ • Lun 30 dic        │
                            │ • Mar 31 dic        │
                            │ • Mié 1 ene         │
                            │ • Otra fecha        │
                            └──────────┬──────────┘
                                       │
                            Usuario selecciona día
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Extract Day         │
                            │ (day_2025-12-30)    │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Get Calendar Events │
                            │ + Check Availability│
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Find Available Slots│
                            │ (3 horarios libres) │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Send Times Buttons  │
                            │ • 09:00 hrs         │
                            │ • 10:00 hrs         │
                            │ • 11:00 hrs         │
                            └──────────┬──────────┘
                                       │
                            Usuario selecciona hora
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Extract Time        │
                            │ (time_2025-12-30_   │
                            │  0900)              │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Save Booking State  │
                            │ step: WAITING_NAME  │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Ask For Name        │
                            │ "Escribe tu nombre" │
                            └──────────┬──────────┘
                                       │
                              Usuario envía nombre
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Check Email Booking │
                            │ (Detecta nombre)    │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Save Name (Redis)   │
                            │ step: WAITING_EMAIL │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Ask For Email       │
                            │ "Escribe tu correo" │
                            └──────────┬──────────┘
                                       │
                              Usuario envía email
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Check Email Booking │
                            │ status: COMPLETE    │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Create Appointment  │
                            │ (Sub-workflow)      │
                            │ • BD WordPress      │
                            │ • Google Calendar   │
                            │ • Email confirm     │
                            └──────────┬──────────┘
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │ Send Booking        │
                            │ Confirmation ✅     │
                            └─────────────────────┘
```

### 2️⃣ Flujo de Cancelación de Cita

```
Usuario dice "cancelar cita"
         │
         ▼
┌─────────────────────┐
│ Agente detecta      │
│ <<ACTION:CANCEL_    │
│ APPOINTMENT>>       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Ask Cancel Data     │
│ "Envía tus datos:   │
│  • Nombre           │
│  • Email            │
│  • Fecha (DD/MM)    │
│  • Hora (HH:MM)"    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Save Cancel State   │
│ (Redis: awaiting_   │
│  cancel_data)       │
└──────────┬──────────┘
           │
  Usuario envía datos
           │
           ▼
┌─────────────────────┐
│ Parse Cancel Data   │
│ (Valida formato)    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Search Cancel       │
│ Appointment (WP API)│
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     ▼           ▼
┌─────────┐ ┌─────────┐
│ Found   │ │Not Found│
└────┬────┘ └────┬────┘
     │           │
     ▼           ▼
┌─────────┐ ┌─────────┐
│ Show    │ │ Send    │
│ Cancel  │ │ Not     │
│ Confirm │ │ Found   │
│ Buttons │ │ Message │
└────┬────┘ └─────────┘
     │
Usuario confirma
     │
     ▼
┌─────────────────────┐
│ Delete Cancelled    │
│ Appointment         │
│ (WordPress API)     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Send Cancel Success │
│ "✅ Cita cancelada" │
└─────────────────────┘
```

### 3️⃣ Flujo de Respuesta a Recordatorios

```
┌─────────────────────────────────────────────────────────────────┐
│  WORKFLOWS DE RECORDATORIO (1h/24h/72h)                         │
│  Envían mensaje con botones:                                    │
│  • btn_reminder_confirm_{leadId}                                │
│  • btn_reminder_no_{leadId}                                     │
└────────────────────────────┬────────────────────────────────────┘
                             │
                 Usuario presiona botón
                             │
                             ▼
              ┌──────────────────────────────┐
              │     Button Action (Switch)   │
              └──────────────┬───────────────┘
                             │
          ┌──────────────────┼──────────────────┐
          ▼                                     ▼
┌───────────────────┐               ┌───────────────────┐
│ Confirmar         │               │ No Puede Asistir  │
│ Recordatorio      │               │                   │
│ (btn_reminder_    │               │ (btn_reminder_    │
│  confirm_{id})    │               │  no_{id})         │
└─────────┬─────────┘               └─────────┬─────────┘
          │                                   │
          ▼                                   ▼
┌───────────────────┐               ┌───────────────────┐
│ Extract Confirm   │               │ Extract No Lead   │
│ Lead ID           │               │ ID                │
└─────────┬─────────┘               └─────────┬─────────┘
          │                                   │
          ▼                                   ▼
┌───────────────────┐               ┌───────────────────┐
│ Confirm Attendance│               │ Send Cancel Or    │
│ (WordPress API)   │               │ Reschedule Options│
│ /confirm-         │               │ (Botones)         │
│ attendance/{id}/  │               └─────────┬─────────┘
│ auto              │                         │
└─────────┬─────────┘                         ▼
          │                         ┌───────────────────┐
          ▼                         │ • btn_cancelar_   │
┌───────────────────┐               │   cita            │
│ Send Confirmation │               │ • btn_reprogramar │
│ Success           │               └───────────────────┘
│ "✅ Asistencia    │
│  confirmada"      │
└───────────────────┘
```

---

## 📝 Detalle Nodo por Nodo

### 🔹 ENTRADA Y VERIFICACIÓN

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Verify Webhook (GET)** | Webhook | Maneja verificación inicial de Meta/WhatsApp |
| **Respond Challenge** | Respond | Devuelve `hub.challenge` para verificar webhook |
| **WhatsApp Webhook** | Webhook POST | Recibe todos los mensajes entrantes |
| **Has Message?** | IF | Verifica si el payload contiene mensajes válidos |
| **Extract Message Data** | Set | Extrae: phoneNumber, messageId, messageType, contactName, timestamp |
| **Deduplication** | Code | Evita procesar mensajes duplicados usando staticData global |

### 🔹 ROUTING POR TIPO DE MENSAJE

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Message Type** | Switch | Enruta según tipo: text, audio, image, interactive |
| **Process Text** | Set | Extrae contenido de texto |
| **Process Audio** | Set | Extrae audioId |
| **Process Image** | Set | Extrae imageId y caption |
| **Process Interactive** | Set | Extrae buttonId, buttonTitle, interactiveType |

### 🔹 PROCESAMIENTO DE AUDIO

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Get Audio URL** | HTTP | Obtiene URL del archivo de audio de WhatsApp |
| **Download Audio** | HTTP | Descarga el archivo de audio |
| **Transcribe Audio (Whisper)** | OpenAI | Transcribe audio a texto con Whisper |
| **Audio to Text** | Set | Prepara textContent con la transcripción |

### 🔹 PROCESAMIENTO DE IMAGEN

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Get Image URL** | HTTP | Obtiene URL de la imagen de WhatsApp |
| **Download Image** | HTTP | Descarga la imagen |
| **Prepare Base64 Image** | Code | Convierte a base64 para Vision |
| **Analyze Image (Vision)** | HTTP (OpenAI) | Analiza imagen con GPT-4 Vision |
| **Image to Text** | Set | Prepara textContent con descripción de imagen |

### 🔹 BUFFER DE MENSAJES (Anti-typing)

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Redis Push** | Redis | Guarda mensaje en lista por teléfono |
| **Redis Get** | Redis | Obtiene mensajes acumulados |
| **Check Message Status** | Switch | Verifica si esperar más mensajes o procesar |
| **Wait 5 Seconds** | Wait | Espera para acumular mensajes |
| **Redis Delete** | Redis | Limpia buffer después de procesar |
| **Concat Messages** | Set | Concatena todos los mensajes del buffer |

### 🔹 AGENTE IA

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Get Exchange Rate** | HTTP | Obtiene tipo de cambio USD/CLP desde WordPress |
| **Merge Data** | Set | Combina chatInput + rate + phoneNumber |
| **Agente IA - Tech WhatsApp** | AI Agent | Agente principal con prompt de sistema |
| **Cerebro GPT-4o** | OpenAI Chat | Modelo de lenguaje GPT-4o |
| **Redis Chat Memory** | Memory | Memoria conversacional por teléfono (30 mensajes) |

### 🔹 PARSER DE ACCIONES

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Parse Response Actions** | Code | Detecta tags: `<<ACTION:SHOW_CALENDAR>>`, etc. También detecta si el mensaje vino de audio para responder con TTS |
| **Route Action** | Switch | Enruta según acción detectada (7 salidas) |

### 🔹 RESPUESTA POR AUDIO (TTS)

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Generate Audio (TTS)** | OpenAI | Convierte texto a audio usando TTS (voz: nova) |
| **Upload Audio to WhatsApp** | HTTP POST | Sube el MP3 a WhatsApp Media API |
| **Send Audio Response** | HTTP POST | Envía mensaje de audio al usuario |

> **Nota:** Cuando el usuario envía una nota de voz y la respuesta es texto normal (sin botones ni acciones especiales), el bot responde también con nota de voz.

### 🔹 FLUJO DE CALENDARIO

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Get Appointments Config** | HTTP | Obtiene config de WordPress (horarios, feriados) |
| **Generate Days** | Code | Genera 3 días disponibles validando horarios |
| **Send Calendar Buttons** | HTTP | Envía lista interactiva con fechas |
| **Extract Day** | Code | Extrae fecha del buttonId `day_YYYY-MM-DD` |
| **Get Calendar Events** | Google Calendar | Obtiene eventos del día |
| **Get Appointments Config 2** | HTTP | Valida disponibilidad desde WordPress |
| **Merge Calendar + Config** | Merge | Combina datos de calendar y config |
| **Find Available Slots** | Code | Encuentra horarios libres |
| **Has Slots?** | IF | Verifica si hay horarios disponibles |
| **Send Times Buttons** | HTTP | Envía botones con horarios |
| **Send No Slots** | HTTP | Informa que no hay horarios |

### 🔹 FLUJO DE BOOKING

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Extract Time** | Code | Extrae hora del buttonId `time_YYYY-MM-DD_HHMM` |
| **Save Booking State** | Redis | Guarda estado: WAITING_NAME |
| **Ask For Name** | HTTP | Pide nombre al usuario |
| **Check Pending Booking** | Redis | Verifica si hay booking pendiente |
| **Check Pending Date** | Redis | Verifica si espera fecha custom |
| **Merge Booking Data** | Set | Combina datos de booking |
| **Check Email Booking** | Code | Detecta si es nombre, email, o texto normal |
| **Booking Status** | Switch | Enruta según status del booking |
| **Save Name** | Redis | Guarda nombre, cambia a WAITING_EMAIL |
| **Ask For Email** | HTTP | Pide email al usuario |
| **Create Appointment** | Execute Workflow | Llama sub-workflow de creación |
| **Delete Booking State** | Redis | Limpia estado después de crear |
| **Send Booking Confirmation** | HTTP | Envía confirmación final |

### 🔹 FLUJO DE CANCELACIÓN

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Ask Cancel Data** | HTTP | Pide datos para cancelar |
| **Save Cancel State** | Redis | Guarda estado awaiting_cancel_data |
| **Check Cancel State** | Redis | Verifica estado de cancelación |
| **Parse Cancel Data** | Code | Parsea nombre, email, fecha, hora |
| **Search Cancel Appointment** | HTTP | Busca cita en WordPress |
| **Process Cancel Results** | Code | Procesa resultado de búsqueda |
| **Check Cancel Found** | IF | Verifica si se encontró cita |
| **Show Cancel Confirmation** | HTTP | Muestra botones de confirmación |
| **Save Cancel Event** | Redis | Guarda datos del evento a cancelar |
| **Get Cancel Event** | Redis | Obtiene evento guardado |
| **Parse Cancel Event** | Code | Parsea datos del evento |
| **Delete Cancelled Appointment** | HTTP DELETE | Elimina cita de WordPress |
| **Send Cancel Success** | HTTP | Confirma cancelación |

### 🔹 FLUJO DE REPROGRAMACIÓN

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Ask Reschedule Data** | HTTP | Pide datos para reprogramar |
| **Save Reschedule State** | Redis | Guarda estado awaiting_reschedule_data |
| **Check Reschedule State** | Redis | Verifica estado |
| **Parse Reschedule Data** | Code | Parsea datos ingresados |
| **Search User Appointment** | HTTP | Busca cita en WordPress |
| **Process Search Results** | Code | Procesa resultado |
| **Check Appointment Found** | IF | Verifica si se encontró |
| **Show Confirmation Buttons** | HTTP | Botones: confirmar / no es mi cita |
| **Save Event To Delete** | Redis | Guarda evento a eliminar |
| **Get Reschedule Event** | Redis | Obtiene evento guardado |
| **Parse Event To Delete** | Code | Parsea datos |
| **Delete Old Appointment** | HTTP DELETE | Elimina cita anterior |
| **Set Reschedule Data** | Set | Prepara datos para nuevo booking |

### 🔹 INDEXACIÓN DE ESTADOS (Para Cleanup)

| Nodo | Tipo | Descripción |
|------|------|-------------|
| **Index Booking State** | Redis Push | Indexa `booking_{phone}` en `wa_state_index` |
| **Index Save Name** | Redis Push | Indexa booking después de guardar nombre |
| **Index Pending Date** | Redis Push | Indexa `pending_date_{phone}` |
| **Index Custom Booking** | Redis Push | Indexa booking de fecha custom |
| **Index Reschedule State** | Redis Push | Indexa `reschedule_{phone}` |
| **Index Cancel State** | Redis Push | Indexa `cancel_{phone}` |

> **Nota:** Estos nodos alimentan el workflow `WhatsApp_Cleanup_States.json` que limpia estados abandonados cada 6 horas.

### 🔹 MANEJO DE BOTONES INTERACTIVOS

| ButtonId | Acción |
|----------|--------|
| `btn_agendar_demo` | Inicia flujo de calendario |
| `btn_ver_planes` | Muestra planes con botones |
| `btn_soporte` | Escala a soporte |
| `day_YYYY-MM-DD` | Selección de día |
| `time_YYYY-MM-DD_HHMM` | Selección de hora |
| `other_date` | Opción de otra fecha |
| `btn_confirmar_reprogramar` | Confirma reprogramación |
| `btn_no_es_mi_cita` | Rechaza cita encontrada |
| `btn_confirmar_cancelar` | Confirma cancelación |
| `btn_mantener_cita` | Mantiene cita |
| `btn_plan_basico/pro/enterprise` | Selección de plan |
| `btn_reminder_confirm_{id}` | Confirma asistencia desde recordatorio |
| `btn_reminder_no_{id}` | No puede asistir |
| `btn_cancelar_cita` | Cancelar cita directa |
| `btn_reprogramar` | Reprogramar cita directa |

---

## 🔗 Integraciones

### APIs Utilizadas

| Servicio | Endpoint/Uso |
|----------|--------------|
| **WhatsApp Business API** | `graph.facebook.com/v22.0/{phoneNumberId}/messages` |
| **WhatsApp Media API** | `graph.facebook.com/v22.0/{phoneNumberId}/media` (upload audio) |
| **OpenAI GPT-4o** | Agente conversacional |
| **OpenAI Whisper** | Transcripción de audio |
| **OpenAI Vision** | Análisis de imágenes |
| **OpenAI TTS** | Text-to-Speech para respuestas de audio (voz: nova) |
| **Google Calendar** | Eventos y disponibilidad |
| **WordPress REST API** | CRUD de citas, configuración |
| **Redis** | Estados de sesión y memoria |

### Endpoints WordPress Utilizados

| Endpoint | Método | Uso |
|----------|--------|-----|
| `/wp-json/automatiza-tech/v1/exchange-rate` | GET | Tipo de cambio |
| `/wp-json/automatiza-tech/v1/appointments-config` | GET | Configuración de horarios |
| `/wp-json/automatiza-tech/v1/check-availability` | POST | Disponibilidad de fecha |
| `/wp-json/automatiza-tech/v1/appointments/search` | GET | Buscar citas |
| `/wp-json/automatiza-tech/v1/appointments/{id}` | DELETE | Eliminar cita |
| `/wp-json/automatiza-tech/v1/leads/confirm-attendance/{id}/{type}` | POST | Confirmar asistencia |

---

## 💾 Estados Redis

### Claves Utilizadas

| Clave | TTL | Descripción |
|-------|-----|-------------|
| `booking_{phone}` | 600s | Estado de booking (WAITING_NAME, WAITING_EMAIL) |
| `pending_date_{phone}` | 600s | Esperando fecha custom |
| `reschedule_{phone}` | 600s | Esperando datos de reprogramación |
| `reschedule_confirmed_{phone}` | 600s | Datos de evento a reprogramar |
| `cancel_{phone}` | 600s | Esperando datos de cancelación |
| `cancel_confirmed_{phone}` | 600s | Datos de evento a cancelar |
| `{phone}` (lista) | - | Buffer de mensajes para anti-typing |
| `wa_state_index` (lista) | - | Índice de estados para cleanup (formato: `key\|timestamp`) |

### Estructura de booking_{phone}

```json
{
  "day": "2025-12-30",
  "time": "10:00",
  "name": "Juan Pérez",
  "phone": "56912345678",
  "phoneNumberId": "946501128543201",
  "step": "WAITING_NAME" | "WAITING_EMAIL"
}
```

---

## � Reglas Especiales del Agente IA

### Mensaje desde la Web
Cuando un usuario envía el mensaje predeterminado desde la web:
> "Hola! Me interesa conocer más sobre Automatiza Tech"

El agente **NO** dispara acciones automáticas. En su lugar:
1. Saluda cordialmente presentándose como Tech 🤖
2. Pregunta en texto (sin botones) qué desea:
   - 📆 Agendar una demo gratuita
   - 💼 Conocer planes y precios
   - 💡 Más información sobre automatización
3. Espera la respuesta para entonces usar la acción correspondiente

### Identidad del Bot
- **Nombre:** Tech 🤖
- El emoji 🤖 siempre acompaña al nombre cuando se presenta

---

## �🎨 Credenciales Utilizadas

| Nombre | ID | Uso |
|--------|-----|-----|
| WhatsApp Tech | `SH8OXr93p852Ll6m` | API WhatsApp Business |
| OpenAi account | `g52IEXpRfN5r7jKw` | GPT-4o, Whisper, Vision |
| Google Calendar AutomatizaTech.cl | `NrhQQuWgel9eWwzp` | Google Calendar API |
| Redis32 | `fgxjc2NeBOcUCA3v` | Cache y estados |

---

## 📊 Métricas Estimadas

- **Nodos totales:** ~110+
- **Flujos principales:** 6 (texto, audio, imagen, botones, audio response, otros)
- **Sub-flujos:** 9 (booking, cancelación, reprogramación, planes, recordatorios, TTS, cleanup index, etc.)
- **Integraciones externas:** 5 (WhatsApp, OpenAI, Google, WordPress, Redis)

---

## 🔄 Workflows Relacionados

| Workflow | Descripción |
|----------|-------------|
| **WhatsApp_Cleanup_States.json** | Limpia estados abandonados cada 6 horas |
| **WhatsApp_Reminder_1h.json** | Recordatorio 1 hora antes |
| **WhatsApp_Reminder_24h.json** | Recordatorio 24 horas antes |
| **WhatsApp_Reminder_72h.json** | Recordatorio 72 horas antes |
| **Create_Appointment.json** | Sub-workflow para crear citas |

---

## ✅ Checklist de Mantenimiento

- [ ] Verificar credenciales de WhatsApp Business
- [ ] Actualizar prompt del Agente IA si cambian servicios/precios
- [ ] Revisar horarios en WordPress config
- [ ] Monitorear uso de tokens OpenAI
- [ ] Limpiar Redis periódicamente si es necesario
- [ ] Verificar webhook URL en Meta Business

---

*Documentación actualizada el 29/12/2025*
