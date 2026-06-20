# 🔄 Flujo Cliente End-to-End — AutomatizaTech

> **Propósito:** Documentar el ciclo completo **Lead → Demo → Prospecto → Cotización → Cliente → Proyecto → QA → Entrega → Pago Final → Contrato → Soporte**, mapeando lo que **YA existe** en el repo, lo que **FALTA**, y un **plan de automatización** realista basado en la infraestructura actual (WordPress + N8N + Portal OmniCliente + OpenAI).
>
> Complementa: `FLUJO_COMERCIAL_TRACKING_AI.md`, `PORTAL_OMNICANAL_DOCS.md`, `INTEGRACION_N8N_PORTAL_OMNICLIENTE.md`.

---

## 0. Diagrama macro (ASCII)

```
┌──────────┐  ┌──────────┐  ┌────────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐  ┌──────────┐  ┌──────────┐
│   LEAD   │->│  DEMO 1  │->│ PROSPECTO  │->│  PROPUESTA│->│ APROBADA │->│ CLIENTE│->│ PROYECTO │->│   QA     │
│ (web/WA) │  │ agendada │  │ demo+docs  │  │  + cotiz. │  │ + anticipo│  │ FINAL │  │ timeline │  │ informe  │
└──────────┘  └──────────┘  └────────────┘  └──────────┘  └──────────┘  └────────┘  └──────────┘  └──────────┘
                                                                                                        │
                                                                                                        ▼
                                                                ┌────────────────────────────────────────────┐
                                                                │ ENTREGA → FEEDBACK → PAGO FINAL → CONTRATO │
                                                                │   SOPORTE → FIRMA → CLIENTE EN SOPORTE     │
                                                                └────────────────────────────────────────────┘
```

---

## 1. Etapas del flujo, mapeo y automatización

Para cada etapa: **input → acciones → output → estado actual → automatización propuesta**.

### Etapa 1 — Captación del Lead

| Item | Detalle |
|---|---|
| **Trigger** | Form web, mensaje WhatsApp, Instagram DM, referido |
| **Estado en BD** | `wp_automatiza_leads.status = 'lead'` / `wp_omnichannel_conversations.status = 'open'` |
| **Lo que existe** ✅ | `contact-form.php`, webhook omnicanal, bot WhatsApp v8 (N8N), recordatorios 72/24/1h |
| **Lo que falta** ❌ | Pipeline visual (Kanban), lead-scoring, asignación automática a ejecutivo |
| **Automatización propuesta** | Al insertar en `wp_automatiza_leads` → trigger SQL/cron que: (1) llama a OpenAI con datos del lead → devuelve score 0–100 + categoría (hot/warm/cold), (2) inserta en columna `lead_score` + `lead_temperature`, (3) si score ≥ 70 → notifica a Slack/WA del comercial asignado |

**SQL nuevo necesario:**
```sql
ALTER TABLE wp_automatiza_leads
  ADD COLUMN lead_score TINYINT NULL,
  ADD COLUMN lead_temperature ENUM('cold','warm','hot') NULL,
  ADD COLUMN assigned_to BIGINT NULL,
  ADD COLUMN pipeline_stage VARCHAR(40) DEFAULT 'lead'; -- lead|demo_scheduled|demo_done|prospect|quoted|won|lost
```

---

### Etapa 2 — Demo Agendada (1ª reunión: coordinación)

| Item | Detalle |
|---|---|
| **Trigger** | Lead acepta agendar (vía bot o form) |
| **Lo que existe** ✅ | N8N workflow v8 → Google Calendar (event_id) + Google Meet link + recordatorios automáticos WA + columna `meet_link` y `event_id` en leads |
| **Lo que falta** ❌ | Anti doble-booking, sincronización bidireccional Google → BD, reschedule automático tras no-show, bloqueo por feriados/vacaciones |
| **Automatización propuesta** | (1) Al crear evento en Google Calendar, lanzar webhook N8N "calendar.updated" que actualice `scheduled_*` y `attendance_status` en BD. (2) Al marcarse `no_show` por cron (1h después del slot sin asistencia), bot WA reabre conversación y ofrece 3 nuevos slots. (3) Tabla `wp_automatiza_unavailable_slots` con días/horas bloqueadas, validar antes de confirmar. |

---

### Etapa 3 — Demo Realizada y entrega de pendientes (Lead → Prospecto)

| Item | Detalle |
|---|---|
| **Trigger** | Comercial marca demo como "realizada" en backoffice |
| **Lo que existe** ✅ | Estados `attendance_status` (asistió/no-show), `pipeline_stage` se puede llevar manualmente |
| **Lo que falta** ❌ | Botón "convertir a prospecto" que dispare: (a) creación de carpeta Google Drive del cliente, (b) checklist de pendientes (documentos, briefing técnico), (c) tarea automática para envío de cotización |
| **Automatización propuesta** | Endpoint `POST /api-omnichannel.php?route=leads/promote` que: (1) cambia `pipeline_stage='prospect'`, (2) crea fila en `wp_automatiza_clients_details` con `detail_type='briefing_pending'`, (3) crea carpeta en Drive (Service Account ya configurada), (4) envía email de "siguiente paso" con link de carga de documentos. |

---

### Etapa 4 — Cotización / Propuesta enviada

| Item | Detalle |
|---|---|
| **Trigger** | Comercial dispara generación tras briefing |
| **Lo que existe** ✅ | `api-save-proposal.php`, `admin-approve-proposal.php`, workflow N8N `propuesta-gamma-workflow.json`, generación PDF (Gamma + FPDF), tabla `wp_automatiza_propuestas` con `unique_link_id`, página pública `ver-presentacion.php`, descuentos (`add-discount-field.php`) |
| **Lo que falta** ❌ | Versionado V1/V2/V3, tracking de aperturas (¿cuándo el cliente abrió la propuesta?), botón "Aceptar/Rechazar" desde la página pública con firma simple, generación automática de boleta de anticipo |
| **Automatización propuesta** | (1) `wp_automatiza_propuestas` add columnas `version`, `parent_proposal_id`, `viewed_at`, `accepted_at`, `rejected_at`, `accept_signature_name`, `accept_signature_ip`. (2) Pixel/beacon en `ver-presentacion.php` → marca `viewed_at`. (3) Botones Aceptar/Rechazar → endpoint que cambia status, dispara N8N: genera boleta de anticipo (`validar-boleta.php`) + email + WA + crea fila en `wp_automatiza_tech_clients` con `status='pending_payment'`. |

---

### Etapa 5 — Aprobación + Pago de Anticipo (Prospecto → Cliente Final)

| Item | Detalle |
|---|---|
| **Trigger** | Cliente aprueba propuesta y paga anticipo (típicamente 50%) |
| **Lo que existe** ✅ | Validación de comprobante de transferencia (`validate-transfer-id.js` + GPT-4V), generación de boleta/factura PDF, tabla `wp_automatiza_tech_clients` |
| **Lo que falta** ❌ | Pasarela de pago (Khipu/MercadoPago/Stripe), conciliación bancaria automática, recordatorios de pago vencido |
| **Automatización propuesta** | Fase 1 (rápido): integrar **Khipu** (mejor precio Chile) → callback marca `payment_status='paid'` y dispara onboarding. Fase 2: cron diario que revisa propuestas con anticipo pendiente > 3 días → envía recordatorio WA + email. |

---

### Etapa 6 — Onboarding del Cliente y arranque de Proyecto

| Item | Detalle |
|---|---|
| **Trigger** | Pago anticipo confirmado |
| **Lo que existe** ✅ | `wp_automatiza_tech_clients` + `wp_automatiza_clients_details`, portal OmniCliente con login por agente |
| **Lo que falta** ❌ | **Timeline visual del proyecto en el portal del cliente**, vista de hitos (milestones), subida de evidencias (capturas/videos), notificaciones de avance |
| **Automatización propuesta** | Crear módulo "Proyectos": tablas + vista React. Ver sección **§3 Esquema de BD propuesto**. |

---

### Etapa 7 — Ejecución del Proyecto + Evidencias en Portal

| Item | Detalle |
|---|---|
| **Lo que existe** ✅ | Sistema de tickets con attachments JSON (`wp_omnichannel_ticket_messages`), auditoría completa |
| **Lo que falta** ❌ | UI de timeline tipo Trello/Linear, milestones con % avance, comentarios bilaterales (cliente ↔ AT), notificaciones email/WA al cambiar estado de un milestone |
| **Automatización propuesta** | Cuando un milestone cambia a `completed`: trigger N8N → email + WA al cliente con link al portal mostrando evidencias del milestone. Al cliente comentar en un milestone: notifica al PM por Slack/WA. |

---

### Etapa 8 — Etapa de QA (si aplica)

| Item | Detalle |
|---|---|
| **Lo que existe** ✅ | Carpeta `QA/`, `qa-report-generator.php` (parser MD → reporte consolidado), plantillas por cliente |
| **Lo que falta** ❌ | Dashboard QA en vivo, status por caso (pass/fail/blocked), bug tracking integrado con tickets, firma de aceptación QA |
| **Automatización propuesta** | (1) Tabla `wp_automatiza_qa_runs` (project_id, total_cases, passed, failed, blocked, status, report_url). (2) Al generar reporte, subir PDF a Drive y publicarlo en el portal del cliente bajo el milestone "QA". (3) Botón "Aprobar QA" con firma simple del cliente → mueve proyecto a `delivery_pending`. |

---

### Etapa 9 — Entrega + Feedback

| Item | Detalle |
|---|---|
| **Lo que falta** ❌ | Encuesta NPS/CSAT automática 24h después de marcar `delivered`, formulario de feedback dentro del portal |
| **Automatización propuesta** | Cron N8N: si `project.status='delivered'` y `delivered_at < NOW() - INTERVAL 1 DAY` y `feedback_sent=0` → envía encuesta (link a form en portal), guarda en `wp_automatiza_project_feedback`. |

---

### Etapa 10 — Pago Final + Contrato de Soporte

| Item | Detalle |
|---|---|
| **Trigger** | QA aprobado / proyecto entregado |
| **Lo que existe** ✅ | Generación de factura final |
| **Lo que falta** ❌ | **Contrato de soporte post-proyecto** (✅ creado en `Docs/CONTRATO_SOPORTE_POSTPROYECTO.md`), firma electrónica, generación automática del contrato con datos del cliente |
| **Automatización propuesta** | (1) Al confirmarse pago final: N8N renderiza la plantilla `CONTRATO_SOPORTE_POSTPROYECTO.md` rellenando placeholders desde `wp_automatiza_tech_clients` + `wp_automatiza_propuestas` → genera PDF con FPDF/DomPDF. (2) Envía vía e-firma. **Opciones**: integración SDK de **Firmavirtual** o **Acepta.com** (e-firma avanzada Chile) o como alternativa más simple firma simple en portal (canvas + IP + timestamp + hash, jurídicamente válida en Chile bajo Ley 19.799 para contratos de servicios). |

---

### Etapa 11 — Cliente en Soporte (estado estable)

| Item | Detalle |
|---|---|
| **Lo que existe** ✅ | Tickets con prioridad, takeover por agentes, auditoría |
| **Lo que falta** ❌ | SLA engine (tiempos de respuesta por prioridad), escalación automática, KB self-service, encuestas post-ticket |
| **Automatización propuesta** | Cron cada 15 min revisa tickets abiertos → si `created_at + sla_minutes < NOW()` y sin respuesta → escala (cambia `priority`, asigna a supervisor, notifica WA). Encuesta CSAT 1 mensaje al cerrar ticket. |

---

## 2. Mapa de automatizaciones (resumen ejecutivo)

| # | Automatización | Disparador | Tecnología | Esfuerzo | Impacto |
|---|---|---|---|---|---|
| 1 | Lead scoring IA | INSERT en leads | OpenAI + cron | Bajo | Alto |
| 2 | Promover lead → prospecto | Botón backoffice | Endpoint PHP + N8N | Bajo | Alto |
| 3 | Tracking apertura propuesta | Pixel en `ver-presentacion.php` | PHP simple | Muy bajo | Medio |
| 4 | Aceptar/Rechazar propuesta online | Click en página pública | PHP + N8N + boleta | Medio | **Crítico** |
| 5 | Pasarela Khipu para anticipo | Botón "Pagar" | Khipu API | Medio | **Crítico** |
| 6 | Timeline visual del proyecto | UI nueva | React + tablas nuevas | **Alto** | **Crítico** |
| 7 | Notificación de milestone | UPDATE estado milestone | N8N + WA + email | Bajo | Alto |
| 8 | Dashboard QA + aprobación | Genera reporte | PHP + tabla nueva | Medio | Alto |
| 9 | Encuesta CSAT/NPS post-entrega | Cron 24h post-delivery | N8N + form | Bajo | Medio |
| 10 | Generación de contrato auto + firma | Pago final | FPDF + e-firma | Medio | **Crítico** |
| 11 | SLA engine en soporte | Cron 15 min | PHP + N8N | Medio | Alto |

**Orden recomendado de implementación:** 4 → 5 → 6 → 10 → 1 → 7 → 8 → 11 → 2 → 9 → 3.

---

## 3. Esquema de BD propuesto (deltas)

```sql
-- ───────────── PIPELINE ─────────────
ALTER TABLE wp_automatiza_leads
  ADD COLUMN lead_score TINYINT NULL,
  ADD COLUMN lead_temperature ENUM('cold','warm','hot') NULL,
  ADD COLUMN assigned_to BIGINT NULL,
  ADD COLUMN pipeline_stage VARCHAR(40) DEFAULT 'lead';

-- ───────────── PROPUESTAS V2 ─────────────
ALTER TABLE wp_automatiza_propuestas
  ADD COLUMN version INT DEFAULT 1,
  ADD COLUMN parent_proposal_id BIGINT NULL,
  ADD COLUMN viewed_at DATETIME NULL,
  ADD COLUMN accepted_at DATETIME NULL,
  ADD COLUMN rejected_at DATETIME NULL,
  ADD COLUMN accept_signature_name VARCHAR(120) NULL,
  ADD COLUMN accept_signature_ip VARCHAR(45) NULL,
  ADD COLUMN accept_signature_hash CHAR(64) NULL;

-- ───────────── PROYECTOS ─────────────
CREATE TABLE wp_automatiza_projects (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  client_id BIGINT NOT NULL,                -- FK a wp_automatiza_tech_clients
  proposal_id BIGINT NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('planning','in_progress','qa','delivered','closed','cancelled') DEFAULT 'planning',
  start_date DATE NULL,
  estimated_end_date DATE NULL,
  delivered_at DATETIME NULL,
  total_amount DECIMAL(12,2) NULL,
  paid_amount DECIMAL(12,2) DEFAULT 0,
  currency CHAR(3) DEFAULT 'CLP',
  pm_user_id BIGINT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (client_id), KEY (status)
);

CREATE TABLE wp_automatiza_project_milestones (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  project_id BIGINT NOT NULL,
  ord INT DEFAULT 0,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('pending','in_progress','completed','blocked') DEFAULT 'pending',
  progress TINYINT DEFAULT 0,           -- 0..100
  due_date DATE NULL,
  completed_at DATETIME NULL,
  KEY (project_id, ord)
);

CREATE TABLE wp_automatiza_project_evidence (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  milestone_id BIGINT NOT NULL,
  type ENUM('image','video','document','link') NOT NULL,
  title VARCHAR(200) NULL,
  url VARCHAR(500) NOT NULL,
  uploaded_by BIGINT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (milestone_id)
);

CREATE TABLE wp_automatiza_project_comments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  milestone_id BIGINT NOT NULL,
  author_type ENUM('client','agent','system') NOT NULL,
  author_id BIGINT NULL,
  body TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (milestone_id, created_at)
);

-- ───────────── QA ─────────────
CREATE TABLE wp_automatiza_qa_runs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  project_id BIGINT NOT NULL,
  total_cases INT DEFAULT 0,
  passed INT DEFAULT 0,
  failed INT DEFAULT 0,
  blocked INT DEFAULT 0,
  status ENUM('draft','running','passed','failed','approved_by_client') DEFAULT 'draft',
  report_url VARCHAR(500) NULL,
  approved_by_client_at DATETIME NULL,
  approved_signature_hash CHAR(64) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (project_id, status)
);

-- ───────────── PAGOS ─────────────
CREATE TABLE wp_automatiza_payments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  project_id BIGINT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'CLP',
  type ENUM('anticipo','intermedio','final','soporte_mensual') NOT NULL,
  method ENUM('khipu','transferencia','mercadopago','stripe','otro') NOT NULL,
  status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  external_ref VARCHAR(120) NULL,
  paid_at DATETIME NULL,
  invoice_url VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (project_id, status)
);

-- ───────────── CONTRATOS ─────────────
CREATE TABLE wp_automatiza_contracts (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  client_id BIGINT NOT NULL,
  project_id BIGINT NULL,
  type ENUM('servicios','soporte','sla','nda') NOT NULL,
  template_id VARCHAR(80) NULL,           -- ej: 'soporte_v1'
  pdf_url VARCHAR(500) NULL,
  status ENUM('draft','sent','signed','expired','cancelled') DEFAULT 'draft',
  sent_at DATETIME NULL,
  signed_at DATETIME NULL,
  signer_name VARCHAR(120) NULL,
  signer_rut VARCHAR(20) NULL,
  signer_email VARCHAR(120) NULL,
  signer_ip VARCHAR(45) NULL,
  signature_hash CHAR(64) NULL,
  signature_image_url VARCHAR(500) NULL,
  starts_at DATE NULL,
  ends_at DATE NULL,
  monthly_amount DECIMAL(10,2) NULL,
  currency CHAR(3) DEFAULT 'CLP',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (client_id, status)
);

-- ───────────── SLA / SOPORTE ─────────────
ALTER TABLE wp_omnichannel_support_tickets
  ADD COLUMN sla_minutes INT NULL,
  ADD COLUMN first_response_at DATETIME NULL,
  ADD COLUMN resolved_at DATETIME NULL,
  ADD COLUMN escalated TINYINT(1) DEFAULT 0,
  ADD COLUMN csat_score TINYINT NULL,
  ADD COLUMN csat_comment TEXT NULL;
```

---

## 4. Endpoints nuevos (mínimos)

| Método | Ruta | Función |
|---|---|---|
| POST | `/api-omnichannel.php?route=leads/{id}/promote` | Lead → Prospecto |
| POST | `/api-omnichannel.php?route=proposals/{id}/accept` | Cliente acepta propuesta |
| POST | `/api-omnichannel.php?route=proposals/{id}/reject` | Cliente rechaza |
| POST | `/api-omnichannel.php?route=projects` | Crear proyecto desde propuesta aceptada |
| GET  | `/api-omnichannel.php?route=projects/{id}` | Detalle proyecto + milestones |
| POST | `/api-omnichannel.php?route=milestones/{id}/evidence` | Subir evidencia |
| POST | `/api-omnichannel.php?route=qa/{id}/approve` | Cliente aprueba QA |
| POST | `/api-omnichannel.php?route=payments/khipu/callback` | Callback Khipu |
| POST | `/api-omnichannel.php?route=contracts/{id}/sign` | Firma simple del contrato |

---

## 5. Workflows N8N a crear

1. **`lead-scoring`** — Trigger HTTP/cron, llama OpenAI, actualiza score.
2. **`proposal-accepted`** — Trigger webhook desde endpoint accept → crea proyecto + boleta + email + WA.
3. **`payment-anticipo-confirmed`** — Khipu callback → marca `paid_amount`, crea milestones desde plantilla, abre acceso al portal.
4. **`milestone-status-changed`** — Trigger BD → notifica al cliente (email + WA con link al portal).
5. **`qa-approved`** — Genera link de pago final + agenda envío de contrato.
6. **`payment-final-confirmed`** — Genera PDF del contrato (renderiza plantilla con datos del cliente) → envía a firma.
7. **`contract-signed`** — Marca cliente como `in_support`, crea suscripción mensual recurrente, da de alta SLA.
8. **`sla-watchdog`** — Cron 15 min, escala tickets vencidos.
9. **`csat-survey`** — Cron 24h post-entrega o post-cierre de ticket, envía encuesta.

---

## 6. Recomendaciones (opinión)

### Prioridad **inmediata** (lo que más duele hoy)
1. **Aceptación de propuesta online + tracking de apertura** → cierra ventas más rápido (semanas → días).
2. **Pasarela Khipu** → cobrar anticipo en 1 click sin perseguir comprobantes.
3. **Contrato de soporte + firma simple** → ya está la plantilla, solo falta el render + firma.

### Prioridad **media** (escala el negocio)
4. **Módulo Proyectos con timeline** en el portal cliente → reduce 50% de mensajes "¿cómo va lo mío?".
5. **Lead scoring IA** → ya tienes OpenAI integrado y tracking de tokens, costo marginal cercano a 0.
6. **SLA engine** → diferenciador comercial real, justifica precio del soporte.

### Prioridad **baja** (nice to have)
7. NPS/CSAT, KB self-service, dashboard QA visual, A/B de prompts.

### Decisiones técnicas que recomiendo
- **NO comprar HubSpot/Salesforce todavía.** Tu stack ya tiene 70% de un CRM funcional; agregar las tablas de §3 te da un CRM propio integrado con bots y tickets.
- **Firma electrónica:** empezar con **firma simple** (canvas HTML5 + hash SHA-256 + IP + timestamp + envío de PDF firmado al email del cliente). Es válida legalmente en Chile para contratos de prestación de servicios bajo Ley 19.799. Migrar a Firmavirtual/Acepta solo si un cliente corporativo lo exige.
- **Pagos:** Khipu primero (transferencia bancaria automática Chile, comisión ~1%). MercadoPago después si hay clientes que quieran tarjeta. Stripe solo si hay clientes USD.
- **Generación de PDFs:** seguir con FPDF (ya está en uso). Para contratos, usar `setasign/fpdi` para mergear plantilla + páginas dinámicas.
- **Portal del cliente:** la SPA React ya existe; agregar las rutas `/projects/:id` reutilizando los componentes de `client-portal-omnichannel/`.

### Métricas a instrumentar desde el día 1
- **Tiempo lead → demo agendada**
- **Tasa de show / no-show**
- **Tiempo demo → propuesta enviada**
- **Tasa de aceptación de propuestas** (y tiempo desde envío → aceptación)
- **Tiempo proyecto → entrega**
- **NPS post-entrega**
- **Tickets/mes por cliente y SLA cumplimiento %**

Todo se puede agregar al `admin-ai-dashboard.php` existente con queries adicionales.

---

## 7. Documentos y plantillas relacionados

- **Contrato de soporte:** `Docs/CONTRATO_SOPORTE_POSTPROYECTO.md` (creado junto con este documento).
- **Flujo comercial original:** `Docs/FLUJO_COMERCIAL_TRACKING_AI.md`.
- **Portal Omnicanal:** `PORTAL_OMNICANAL_DOCS.md`.
- **N8N ↔ Portal:** `Docs/INTEGRACION_N8N_PORTAL_OMNICLIENTE.md`.

---

_Última actualización: 2026-04-29_
