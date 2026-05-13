# 12 — Flujos de Negocio (Diagramas Mermaid)

> Diagramas end-to-end de los procesos críticos del ecosistema. Cada flujo cruza múltiples subsistemas (theme WP, API PHP, N8N, Portal, integraciones).

---

## 1. 🎯 Captura de lead web

```mermaid
sequenceDiagram
    actor V as Visitante
    participant F as Form WP<br/>(shortcode at_form_contacto)
    participant AJ as ajax-handlers.php<br/>at_save_lead
    participant DB as MySQL<br/>wp_automatiza_tech_leads
    participant N8 as N8N<br/>Lead_To_CRM
    participant E as Email equipo
    V->>F: Completa formulario
    F->>AJ: POST admin-ajax.php<br/>+ nonce + at-rate-limit
    AJ->>AJ: Sanitiza, valida
    AJ->>DB: INSERT lead
    AJ->>N8: Webhook firmado HMAC
    N8->>E: Email a equipo "Nuevo lead"
    AJ-->>F: { ok: true, lead_id }
    F-->>V: Mensaje "Te contactaremos"
```

---

## 2. 📅 Agendamiento de cita (vía bot WhatsApp)

```mermaid
sequenceDiagram
    actor C as Cliente (WhatsApp)
    participant M as Meta Cloud
    participant W as webhook-omnichannel.php
    participant API as api-omnichannel.php
    participant N8 as N8N Bot v8
    participant R as Redis
    participant O as OpenAI
    participant GC as Google Calendar
    participant DB as MySQL
    C->>M: "Quiero agendar mañana 10am"
    M->>W: webhook (HMAC Meta)
    W->>DB: INSERT message + UPDATE conversation
    W->>N8: trigger workflow
    N8->>R: GET bot:ctx:{client_id}:{conv_id}
    alt MISS
        N8->>API: GET contexto + bot_config (X-Admin-Token)
        API->>DB: SELECT
        API-->>N8: contexto
        N8->>R: SET ctx TTL=300
    end
    N8->>O: chat completion (system + history + user + tools)
    O-->>N8: tool_call: book_appointment(slot)
    N8->>GC: insertar evento + Meet
    GC-->>N8: event_id, meet_url
    N8->>API: POST messages.send "Cita confirmada..."
    API->>DB: INSERT outbound message
    API->>M: Meta Graph: send WA message
    M->>C: Mensaje confirmación
    N8->>API: POST appointments (FK lead)
    API->>DB: INSERT appointment
```

---

## 3. 🔁 Conversación omnicanal con takeover de agente

```mermaid
sequenceDiagram
    actor C as Cliente
    participant M as Meta/YCloud/TG
    participant W as webhook-omnichannel.php
    participant API as api-omnichannel.php
    participant SPA as Portal SPA (Inbox)
    participant A as Agente humano
    participant N8 as N8N Bot
    C->>M: Mensaje
    M->>W: webhook
    W->>API: persiste + trigger
    API->>N8: bot responde (si no takeover)
    Note over A,SPA: Agente ve conversación en inbox (polling 5s)
    A->>SPA: Click "Tomar conversación"
    SPA->>API: takeover.start
    API->>API: INSERT takeovers (TTL)
    Note over N8: Bot detecta takeover<br/>y se silencia
    A->>SPA: Escribe respuesta
    SPA->>API: messages.send
    API->>M: envía vía adapter
    M->>C: Mensaje del agente
    A->>SPA: "Soltar conversación"
    SPA->>API: takeover.end
    API->>API: UPDATE takeovers ended_at
    Note over N8: Bot vuelve a responder
```

---

## 4. 💼 Pipeline comercial: lead → propuesta → contrato

```mermaid
flowchart TD
    L[Lead capturado] --> CL[Convertir a Cliente<br/>backoffice WP]
    CL --> P[Crear Propuesta<br/>endpoint /v1/proposals]
    P --> PDF[Generar PDF + token público]
    PDF --> SEND[Enviar al cliente<br/>email branded]
    SEND --> VIEW[Cliente abre link público<br/>?token=XYZ]
    VIEW --> ACCEPT{¿Acepta?}
    ACCEPT -->|No| REJECT[status=rejected]
    ACCEPT -->|Sí| ACC[POST /proposals/id/accept]
    ACC --> N8[Workflow Proposal_Accepted_To_Contract]
    N8 --> CTR[contracts/create-contract.php]
    CTR --> ATSIGN[Revisor AT firma<br/>at-sign-contract.php]
    ATSIGN --> CSIGN[Cliente firma<br/>sign-contract.php]
    CSIGN --> FINAL[PDF FINAL + emails ambos]
    FINAL --> PROJ[Iniciar Proyecto<br/>wp_automatiza_projects]
```

---

## 5. ⏰ Recordatorios automáticos de cita

```mermaid
flowchart LR
    CRON[Cron N8N diario] --> Q24[Query appointments<br/>scheduled_at = NOW + 24h]
    CRON2[Cron N8N horario] --> Q1[Query appointments<br/>scheduled_at = NOW + 1h]
    Q24 --> SEND24[Enviar WA + Email<br/>recordatorio 24h]
    Q1 --> SEND1[Enviar WA<br/>recordatorio 1h con link Meet]
    SEND24 & SEND1 --> LOG[Log en ai_usage / messages]
```

---

## 6. 🔐 Bot WhatsApp v8 — flujo interno detallado

```mermaid
flowchart TD
    A[Webhook Meta] --> B[HMAC valid?]
    B -->|No| X[401]
    B -->|Sí| C[Idempotencia<br/>external_id?]
    C -->|Existe| OK[200 sin acción]
    C -->|Nuevo| D[Persiste mensaje]
    D --> E[Trigger N8N Bot v8]
    E --> F[Resolve client + channel]
    F --> G{Takeover<br/>activo?}
    G -->|Sí| Z[Skip - notifica agente]
    G -->|No| H[Cache Redis ctx?]
    H -->|MISS| I[GET Portal API<br/>contexto + config + prompts]
    I --> H2[SET Redis TTL 5min]
    H -->|HIT| J
    H2 --> J[Build prompt<br/>system + history + user]
    J --> K[OpenAI chat]
    K --> L{Tool call?}
    L -->|book_appointment| M1[Google Calendar]
    L -->|consultar_uf| M2[mindicador.cl cache]
    L -->|escalar_humano| M3[Crea takeover + notifica agente]
    L -->|buscar_producto| M4[GSheets catálogo]
    L -->|texto plano| N[Respuesta]
    M1 & M2 & M3 & M4 --> N
    N --> O[POST Portal API messages.send]
    O --> P[Adapter Meta envía]
    K --> Q[Log ai_usage_log]
```

---

## 7. ✍️ Firma electrónica de contrato (doble)

Ver `09_MODULO_CONTRATOS.md` (diagrama completo).

---

## 8. 📊 Generación de digest diario

```mermaid
flowchart LR
    CRON[Cron 8 AM N8N] --> SQL[Query agregadas<br/>leads, conversaciones,<br/>citas, propuestas, contratos]
    SQL --> AI[OpenAI: resumen ejecutivo]
    AI --> EMAIL[Email a equipo AT]
    AI --> TG[Mensaje Telegram canal interno]
```

---

## 9. 🔄 Sincronización de UF / indicadores

```mermaid
flowchart LR
    CRON[Cron diario N8N] --> API1[GET mindicador.cl/api]
    API1 --> CACHE[SET transient WP<br/>at_indicadores_cache 24h]
    CACHE --> EXPOSE[Endpoint REST<br/>/wp-json/automatiza-tech/v1/uf]
    EXPOSE --> WEB[Web institucional<br/>muestra UF]
    EXPOSE --> PROP[Generador propuestas<br/>convierte CLP↔UF]
```

---

## 10. 🚨 Reporte de errores N8N → AT

```mermaid
flowchart LR
    WF[Workflow N8N] -->|falla| ET[Error Trigger N8N]
    ET --> POST[POST /wp-json/automatiza-tech/v1/n8n/error<br/>HMAC firmado]
    POST --> VER[at_webhook_verify_hmac]
    VER --> INS[INSERT wp_omnichannel_n8n_errors]
    INS --> NOTIFY{severity = critical?}
    NOTIFY -->|Sí| EMAIL[Email a OMNI_MASTER_EMAIL]
    NOTIFY -->|No| LOG[Solo log]
    INS --> DASH[Visible en Portal admin]
```
