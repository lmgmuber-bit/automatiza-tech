# AutomatizaTech — Documento Técnico: Portal OmniCliente

> ⚠️ **DOCUMENTO HISTÓRICO (v2.0, Mar 2026)** — Puede contener datos desactualizados.
> La fuente única de verdad actual es **[`Docs/MASTER/`](./MASTER/00_INDEX.md)**.
> Discrepancias conocidas: rama activa real es `security/hardening-phase-0`, build Portal en `omnicliente/`, no documenta 9 helpers `at-*.php` ni módulo contratos.

> **Versión:** 2.0  
> **Última actualización:** 25 de marzo de 2026
> **Alcance:** Arquitectura técnica del **Portal OmniCliente** (SPA React + API PHP + N8N).  
> **Plataforma principal WP/CRM:** Ver `DOCUMENTO_TECNICO_AUTOMATIZATECH.md`  
> **Contexto completo del proyecto:** Ver `CONTEXTO_COMPLETO.md`  
> **Propósito:** Referencia técnica exhaustiva para desarrolladores, agentes IA o cualquier persona que necesite entender la arquitectura del portal omnicanal

---

## Índice

1. [Stack Tecnológico](#1-stack-tecnológico)
2. [Estructura del Proyecto](#2-estructura-del-proyecto)
3. [Frontend — Portal OmniCliente](#3-frontend--portal-omnicliente)
4. [Backend — API PHP](#4-backend--api-php)
5. [Base de Datos](#5-base-de-datos)
6. [Autenticación y Autorización](#6-autenticación-y-autorización)
7. [Webhooks y Mensajería](#7-webhooks-y-mensajería)
8. [Integración N8N](#8-integración-n8n)
9. [Sistema de Emails](#9-sistema-de-emails)
10. [WordPress Integration](#10-wordpress-integration)
11. [Scripts de Utilidad](#11-scripts-de-utilidad)
12. [Despliegue y Configuración](#12-despliegue-y-configuración)
13. [Seguridad](#13-seguridad)
14. [APIs Externas](#14-apis-externas)

---

## 1. Stack Tecnológico

### Frontend
| Tecnología | Versión | Propósito |
|---|---|---|
| React | 19.1.0 | Framework UI (SPA) |
| Vite | 6.4.1 | Build tool y dev server |
| Tailwind CSS | 3.4.17 | Framework CSS utility-first |
| PostCSS | 8.5.4 | Procesador CSS |
| Autoprefixer | 10.4.21 | Compatibilidad cross-browser |
| Lucide React | 0.577.0 | Iconos SVG |

### Backend
| Tecnología | Versión | Propósito |
|---|---|---|
| PHP | 8.3+ | Lenguaje backend |
| WordPress | 6.x | CMS y sistema de usuarios |
| MySQL | 9.1+ | Base de datos relacional |
| Apache | 2.4+ | Servidor web |
| LiteSpeed Cache | — | Cache en producción (Hostinger) |

### Infraestructura
| Componente | Detalle |
|---|---|
| Dev local | WAMP64 en Windows |
| Producción | Hostinger (Linux, Apache) |
| Dominio | automatizatech.cl |
| SSL | Let's Encrypt (auto-renovado) |
| Git | GitHub (lmgmuber-bit/automatiza-tech) |
| Branch principal | prod-sync-2025-06-26 |
| Branch por defecto | main |

### Servicios Externos
| Servicio | Uso |
|---|---|
| N8N | Motor de automatización (~40 workflows) |
| OpenAI | GPT-4o, GPT-4o-mini, Whisper, TTS-1 |
| YCloud | API de WhatsApp Business |
| Google Calendar | Sincronización de citas |
| Google Sheets | Base de datos ligera para bots |
| Google Drive | Almacenamiento de docs por cliente |
| Redis | Buffer de mensajes en N8N |
| Transbank | Pagos (para PetsGO) |

---

## 2. Estructura del Proyecto

```
automatiza-tech/
│
├── client-portal-omnichannel/     # React SPA (Portal OmniCliente)
│   ├── src/
│   │   ├── App.jsx                # Componente principal (~200 líneas)
│   │   ├── main.jsx               # Entry point
│   │   ├── api.js                 # Cliente API con auth management
│   │   ├── index.css              # Estilos globales
│   │   └── components/            # 17 componentes JSX
│   ├── dist/                      # Build de producción
│   ├── public/                    # Assets estáticos
│   ├── docs/                      # Manuales del portal
│   ├── .env                       # Variables de entorno
│   ├── vite.config.js             # Config Vite (proxy, base path)
│   ├── tailwind.config.js         # Config Tailwind
│   ├── postcss.config.js          # Config PostCSS
│   └── package.json               # Dependencias Node.js
│
├── omnicliente/                   # Deploy de producción (dist copiado aquí)
│   ├── assets/                    # JS/CSS compilados
│   ├── index.html                 # Entry point HTML
│   ├── logo-automatiza-tech.png   # Logo del portal
│   └── manifest.json              # PWA manifest
│
├── N8N/                           # Workflows de automatización
│   ├── TEMPLATES/                 # 13 templates reutilizables
│   │   └── kellscapilar/          # Templates específicos Kells Capilares
│   └── PROD/                      # 40+ workflows de producción
│
├── wp-content/                    # WordPress content
│   ├── themes/automatiza-tech/    # Tema personalizado
│   │   ├── inc/                   # Includes (modules)
│   │   └── lib/                   # Librerías (FPDF para PDFs)
│   ├── mu-plugins/                # Must-use plugins
│   └── uploads/                   # Media library
│
├── Docs/                          # Documentación del proyecto
├── Clientes/                      # Archivos específicos por cliente
├── sql/                           # Scripts SQL / exports
├── tools/                         # Herramientas de utilidad
├── archive/                       # Archivos archivados
├── RespaldoDocs/                  # Respaldos de documentación
│
├── ──── BACKEND PHP (raíz) ────
├── api-omnichannel.php            # API REST principal (~1380 líneas)
├── omnichannel-controller.php     # Controlador principal (~3400 líneas)
├── webhook-omnichannel.php        # Receptor de webhooks (~400 líneas)
├── openai-controller.php          # Controlador de IA
├── invoice-handlers.php           # Manejo de facturas
├── contact-form.php               # Formulario de contacto
├── admin-ai-dashboard.php         # Dashboard de IA
├── admin-approve-proposal.php     # Aprobación de propuestas
│
├── ──── SCRIPTS DE SETUP ────
├── setup-omnichannel-prod.php     # Setup unificado de producción
├── setup-omnichannel-db.php       # Tablas core
├── setup-omnichannel-v2.php       # Bot templates table
├── setup-omnichannel-v3.php       # Audit + takeover tables
├── setup-channel-types.php        # Channel types
│
├── ──── SCRIPTS DE CHECK/DEBUG/FIX ────
├── check-*.php                    # 11 scripts de verificación
├── debug-*.php                    # 6 scripts de debugging
├── fix-*.php                      # 6 scripts de reparación
├── test-*.php                     # 6 scripts de testing
│
├── .htaccess                      # Routing Apache
├── .htaccess-production           # Versión para producción
├── index.php                      # WordPress root loader
└── wp-config.php                  # Configuración WordPress
```

---

## 3. Frontend — Portal OmniCliente

### 3.1 Configuración de Build

**vite.config.js:**
```javascript
export default defineConfig({
  plugins: [react()],
  base: './',                           // Rutas relativas para subdirectorio
  build: { outDir: 'dist' },
  server: {
    port: 5173,
    proxy: {
      '/api-omnichannel.php': {
        target: 'http://localhost/automatiza-tech',
        changeOrigin: true,
        cookieDomainRewrite: 'localhost',
      }
    }
  }
})
```

**Proceso de build:**
```bash
cd client-portal-omnichannel
npm run build                           # Genera dist/
# Copiar dist/ → omnicliente/
```

### 3.2 Componentes React (17 componentes)

| Componente | Archivo | Propósito | Líneas aprox. |
|---|---|---|---|
| **App** | App.jsx | Routing, auth state, tema oscuro/claro | ~200 |
| **LoginScreen** | LoginScreen.jsx | Login por email/password o API Key | ~150 |
| **Sidebar** | Sidebar.jsx | Navegación por rol, toggle dark mode | ~120 |
| **InboxView** | InboxView.jsx | Bandeja unificada de conversaciones | ~400 |
| **ChannelsView** | ChannelsView.jsx | CRUD de canales de mensajería | ~300 |
| **ChannelTypesView** | ChannelTypesView.jsx | CRUD de tipos de canal | ~200 |
| **ChannelBadge** | ChannelBadge.jsx | Badge visual del tipo de canal | ~30 |
| **BotsView** | BotsView.jsx | Configuración de bots por canal | ~250 |
| **AgentsView** | AgentsView.jsx | CRUD de agentes con roles | ~350 |
| **AuditView** | AuditView.jsx | Logs de auditoría con búsqueda/filtros | ~250 |
| **ClientsView** | ClientsView.jsx | Gestión de clientes (solo Admin) | ~300 |
| **DashboardView** | DashboardView.jsx | Métricas y estadísticas SuperAdmin | ~200 |
| **ProfileView** | ProfileView.jsx | Perfil, avatar, cambio contraseña OTP | ~300 |
| **SupportView** | SupportView.jsx | Tickets de soporte con imágenes | ~350 |
| **ExpiryWarningModal** | ExpiryWarningModal.jsx | Alerta de vencimiento de servicio | ~80 |
| **TicketNotificationModal** | TicketNotificationModal.jsx | Notificación de tickets nuevos | ~60 |
| **ConfirmDeleteModal** | ConfirmDeleteModal.jsx | Confirmación antes de eliminar | ~50 |
| **ResultModal** | ResultModal.jsx | Resultado de operaciones (éxito/error) | ~60 |

### 3.3 Cliente API (api.js)

**Funcionalidades:**
- Auto-detección de base URL (dev vs prod)
- Headers `Content-Type: application/json`
- Inyección automática de auth headers según rol:
  - Admin: `X-Admin-Token`
  - Agente: Cookie de sesión
  - Cliente: `X-API-Key`
- Interceptor de errores 401 → redirect a login
- Funciones para todos los endpoints CRUD

**Almacenamiento Local (localStorage):**
| Key | Propósito |
|---|---|
| `omni_api_key` | API key del cliente |
| `omni_is_admin` | Flag de sesión admin WordPress |
| `omni_is_agent` | Flag de sesión agente |
| `omni_admin_token` | Token JWT-like admin |
| `omni_agent_token` | Token de sesión agente |
| `omni_agent_data` | Datos del agente (JSON: role, client_id, name) |
| `omni_theme` | Preferencia dark/light |
| `omni_period_warning` | Notificación de vencimiento |

### 3.4 PWA Manifest

**omnicliente/manifest.json:**
```json
{
  "name": "OmniCliente - AutomatizaTech",
  "short_name": "OmniCliente",
  "start_url": "./",
  "display": "standalone",
  "background_color": "#111827",
  "theme_color": "#2563eb"
}
```

---

## 4. Backend — API PHP

### 4.1 Arquitectura

El backend se compone de 3 archivos PHP principales:

```
REQUEST → api-omnichannel.php (Router)
              ↓
         omnichannel-controller.php (Lógica de negocio)
              ↓
         WordPress WPDB (Base de datos)
```

Para webhooks entrantes:
```
WEBHOOK → webhook-omnichannel.php
              ↓
         omnichannel-controller.php → receive_message()
```

### 4.2 Router (api-omnichannel.php — ~1380 líneas)

**Mecánica de routing:**
```
GET /api-omnichannel.php?route=admin/clients
                              ↑
                      Parsed como: ['admin', 'clients']
```

**Grupos de rutas:**

```
PÚBLICO (sin auth)
├── GET  health                              → {status:"ok"}
├── POST public/support-ticket               → Crear ticket
├── POST public/upload-images                → Subir imágenes (5 × 3MB)
├── POST cron/expiry-reminders               → Cron vencimientos
├── GET  webhook/{type}?channel_id&secret    → Verificación webhook
├── POST webhook/{type}?channel_id&secret    → Mensaje entrante
└── POST webhook/n8n-callback                → Callback N8N

ADMIN (X-Admin-Token o sesión WordPress)
├── POST admin/login                         → Login admin
├── GET  admin/session-check                 → Verificar sesión
├── GET  admin/stats                         → Estadísticas dashboard
│
├── CRUD admin/clients                       → Gestión clientes
├── CRUD admin/channels                      → Gestión canales
├── CRUD admin/channel-types                 → Tipos de canal
├── CRUD admin/bot-configs                   → Config bots
├── CRUD admin/bot-templates                 → Templates bot
├── CRUD admin/agents                        → Gestión agentes
├── CRUD admin/n8n-workflows                 → Workflows N8N
├── CRUD admin/tickets                       → Tickets soporte
│
├── GET  admin/conversations                 → Listar conversaciones
├── GET  admin/conversations/{id}/messages   → Mensajes
├── POST admin/conversations/{id}/messages   → Enviar mensaje
├── POST admin/conversations/{id}/takeover   → Tomar control
├── POST admin/conversations/{id}/start-intervention/{agent_id}
├── POST admin/conversations/{id}/end-intervention/{agent_id}
│
├── GET  admin/importable-wp-users           → Buscar usuarios WP
├── POST admin/import-wp-user                → Importar usuario WP
├── GET  admin/importable-crm-prospects      → Buscar prospectos CRM
├── POST admin/import-crm-prospect           → Importar prospecto CRM
│
├── POST admin/profile/update                → Actualizar perfil
└── POST admin/profile/avatar                → Subir avatar

CLIENTE (X-API-Key)
├── GET  conversations                       → Conversaciones del cliente
├── GET  conversations/{id}/messages         → Mensajes
├── POST conversations/{id}/messages         → Enviar mensaje
├── POST conversations/{id}/takeover         → Solicitar intervención
├── CRUD channels                            → Canales del cliente
├── GET  channel-types                       → Tipos disponibles
├── CRUD bot-configs                         → Config bots
├── GET  bot-templates                       → Templates
└── PUT  support-ticket                      → Actualizar ticket propio

AGENTE (Sesión)
├── POST agent/login                         → Login agente
├── POST agent/forgot-password               → Solicitar reset
├── POST agent/verify-reset                  → Verificar código
├── POST agent/reset-password                → Nueva contraseña
├── GET  agent/session-check                 → Verificar sesión
├── GET  agent/conversations                 → Conversaciones asignadas
├── GET  agent/conversations/{id}/messages   → Mensajes
├── POST agent/conversations/{id}/messages   → Enviar mensaje
├── PUT  agent/conversations/{id}/status     → Cambiar estado
├── PUT  agent/conversations/{id}/priority   → Cambiar prioridad
├── PUT  agent/conversations/{id}/assign     → Reasignar
├── CRUD agent/agents                        → Gestión agentes (supervisor)
├── GET  agent/channels                      → Canales
├── GET  agent/channel-types                 → Tipos
├── CRUD agent/bot-configs                   → Config bots (supervisor)
├── GET  agent/audit                         → Logs (supervisor)
├── GET  agent/profile                       → Perfil propio
├── POST agent/profile/update                → Actualizar perfil
├── POST agent/profile/avatar                → Subir avatar
├── POST agent/password/reset-request        → Solicitar reset
└── POST agent/password/reset                → Cambiar contraseña
```

### 4.3 Controlador (omnichannel-controller.php — ~3400 líneas)

**Clase principal:** `OmnichannelController`

**Métodos principales por categoría:**

#### Mensajería y Webhooks
| Método | Línea aprox. | Propósito |
|---|---|---|
| `receive_message()` | ~692 | Recibe mensajes de cualquier canal, guarda en DB, evalúa si derivar a N8N |
| `handle_ycloud_webhook()` | ~2350 | Procesa webhooks de YCloud |
| `forward_to_n8n()` | ~2136 | Reenvía mensaje al webhook N8N (fire-and-forget, 5s timeout) |
| `handle_n8n_callback()` | ~2181 | Recibe respuestas de N8N (2 modos: raw/simple) |
| `handle_n8n_callback_raw()` | ~2255 | Procesa payloads raw YCloud desde N8N |
| `send_ycloud_message()` | ~2437 | Envía mensaje vía API YCloud |

#### CRUD y Gestión
| Método | Propósito |
|---|---|
| `get_clients()` / `create_client()` / `update_client()` / `delete_client()` | CRUD clientes |
| `get_channels()` / `create_channel()` / `update_channel()` | CRUD canales |
| `get_conversations()` / `get_messages()` / `send_message()` | Conversaciones |
| `get_agents()` / `create_agent()` / `update_agent()` / `delete_agent()` | CRUD agentes |
| `get_bot_configs()` / `update_bot_config()` | Config bots |
| `get_audit_logs()` | Logs de auditoría |
| `get_tickets()` / `update_ticket()` / `add_ticket_message()` | Tickets soporte |
| `start_intervention()` / `end_intervention()` | Intervención humana |
| `takeover_conversation()` | Toma de control |

#### Autenticación
| Método | Propósito |
|---|---|
| `admin_login()` | Login admin con username/password → token |
| `verify_admin_token()` | Verificar token admin (HMAC-SHA256) |
| `agent_login()` | Login agente con email/password |
| `verify_agent_session()` | Verificar sesión agente |
| `agent_forgot_password()` | Email con código de reset |
| `agent_reset_password()` | Resetear con código OTP |

#### Emails (8 funciones)
| Método | Evento |
|---|---|
| `send_welcome_email()` | Cliente o agente creado |
| `send_ticket_notification()` | Ticket nuevo |
| `send_ticket_status_email()` | Cambio de estado de ticket |
| `send_ticket_comment_email()` | Nuevo comentario en ticket |
| `send_expiry_reminder_email()` | Aviso de vencimiento de servicio |
| `send_password_reset_email()` | Código OTP para reset |
| `send_agent_welcome_email()` | Bienvenida a nuevo agente |
| `send_agent_password_reset_email()` | Reset de contraseña agente |

> **Regla:** Toda función de email ejecuta la operación de DB primero. Si el DB falla, NO se envía email.

---

## 5. Base de Datos

### 5.1 Prefijo y Motor
- Prefijo: `wp_omnichannel_` (heredado de WordPress $wpdb->prefix)
- Motor: InnoDB
- Charset: utf8mb4_unicode_ci

### 5.2 Tablas (15)

#### clients
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
company_name     VARCHAR(255) NOT NULL
contact_name     VARCHAR(255) NOT NULL
email            VARCHAR(255) NOT NULL
phone            VARCHAR(50)
wp_user_id       BIGINT UNSIGNED              -- Enlace a wp_users
plan_type        ENUM('basic','pro','enterprise') DEFAULT 'basic'
status           ENUM('trial','active','suspended') DEFAULT 'trial'
max_channels     INT DEFAULT 2
max_agents       INT DEFAULT 3
api_key          VARCHAR(64) UNIQUE NOT NULL   -- Auth para cliente
trial_ends_at    DATETIME
activated_at     DATETIME
period_start     DATE                          -- Inicio período facturación
period_end       DATE                          -- Fin período facturación
is_free          TINYINT(1) DEFAULT 0          -- Plan gratuito
expiry_notified_days VARCHAR(100)              -- CSV: "7,3,1"
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP
```

#### channels
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
client_id        INT NOT NULL                  -- FK → clients.id
channel_type     VARCHAR(50) NOT NULL          -- whatsapp, instagram, telegram, messenger
channel_name     VARCHAR(255)
is_active        TINYINT(1) DEFAULT 1
credentials_json TEXT                          -- {"ycloud_api_key":"...", ...}
webhook_url      VARCHAR(500)                  -- URL del webhook externo
webhook_secret   VARCHAR(128)                  -- Secreto para validar webhooks
phone_number     VARCHAR(50)                   -- Número del canal
page_id          VARCHAR(100)                  -- Para Instagram/Facebook
bot_token        VARCHAR(500)                  -- Para Telegram
config_json      TEXT                          -- Rate limiting, delivery settings
last_synced_at   DATETIME
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP
INDEX(client_id)
```

#### conversations
```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
client_id             INT NOT NULL
channel_id            INT NOT NULL
external_contact_id   VARCHAR(255)              -- ID en la plataforma externa
contact_name          VARCHAR(255)
contact_phone         VARCHAR(50)
contact_email         VARCHAR(255)
contact_avatar_url    VARCHAR(500)
channel_type          VARCHAR(50)
status                ENUM('open','assigned','bot','closed','archived') DEFAULT 'open'
assigned_agent_id     INT                        -- FK → agents.id
priority              ENUM('low','normal','high','urgent') DEFAULT 'normal'
tags                  TEXT                        -- JSON array de tags
last_message_at       DATETIME
last_message_preview  VARCHAR(500)
unread_count          INT DEFAULT 0
metadata_json         TEXT
intervention_mode     VARCHAR(20) DEFAULT NULL   -- 'human' cuando agente toma control
created_at            DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at            DATETIME ON UPDATE CURRENT_TIMESTAMP
INDEX(client_id), INDEX(channel_id), INDEX(status)
```

#### messages
```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
conversation_id       INT NOT NULL
channel_type          VARCHAR(50)
direction             ENUM('inbound','outbound') NOT NULL
sender_type           ENUM('contact','agent','bot','system') NOT NULL
sender_id             INT
sender_name           VARCHAR(255)
message_type          VARCHAR(50) DEFAULT 'text' -- text, image, video, audio, document, interactive, template
content               TEXT
media_url             VARCHAR(500)
media_mime_type       VARCHAR(100)
external_message_id   VARCHAR(255)               -- ID en plataforma externa (para idempotencia)
is_delivered          TINYINT(1) DEFAULT 0
delivery_failed_reason VARCHAR(500)
created_at            DATETIME DEFAULT CURRENT_TIMESTAMP
INDEX(conversation_id), INDEX(created_at)
```

#### bot_configs
```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
client_id             INT NOT NULL
channel_id            INT NOT NULL
bot_enabled           TINYINT(1) DEFAULT 0
welcome_message       TEXT
auto_response_enabled TINYINT(1) DEFAULT 0
ai_provider           ENUM('openai','custom','none') DEFAULT 'none'
ai_prompt             TEXT                        -- Prompt base del bot
language              VARCHAR(10) DEFAULT 'es'
tone                  ENUM('friendly','formal','casual') DEFAULT 'friendly'
escalation_enabled    TINYINT(1) DEFAULT 0
escalation_threshold  INT DEFAULT 5               -- Nro de mensajes antes de escalar
webhook_url_outbound  VARCHAR(500)                -- URL webhook N8N
n8n_webhook_url       VARCHAR(500)                -- URL específica N8N
created_at            DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at            DATETIME ON UPDATE CURRENT_TIMESTAMP
INDEX(client_id), INDEX(channel_id)
```

#### audit_log
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
client_id        INT
user_id          INT
user_email       VARCHAR(255)
user_role        VARCHAR(50)
action           VARCHAR(100)                    -- create, update, delete, login, assign, etc.
entity_type      VARCHAR(100)                    -- conversation, channel, agent, client, etc.
entity_id        INT
description      TEXT
old_values_json  TEXT                             -- JSON con valores anteriores
new_values_json  TEXT                             -- JSON con valores nuevos
ip_address       VARCHAR(45)
user_agent       VARCHAR(500)
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
INDEX(client_id), INDEX(action), INDEX(created_at)
```

#### agents
```sql
id                     INT AUTO_INCREMENT PRIMARY KEY
client_id              INT NOT NULL
email                  VARCHAR(255) NOT NULL
name                   VARCHAR(255) NOT NULL
role                   ENUM('agent','supervisor','admin') DEFAULT 'agent'
is_active              TINYINT(1) DEFAULT 1
password_hash          VARCHAR(255)                -- bcrypt hash
password_reset_code    VARCHAR(10)                 -- OTP de 6 dígitos
password_reset_expires DATETIME
avatar_url             VARCHAR(500)
created_at             DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at             DATETIME ON UPDATE CURRENT_TIMESTAMP
UNIQUE(client_id, email)
```

#### takeovers
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
client_id        INT NOT NULL
conversation_id  INT NOT NULL
agent_id         INT NOT NULL
started_at       DATETIME NOT NULL
ended_at         DATETIME
duration_seconds INT
notes            TEXT
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
```

#### bot_templates
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
client_id        INT NOT NULL
bot_name         VARCHAR(255)
channel_type     VARCHAR(50)
template_json    LONGTEXT                         -- Configuración completa JSON
is_default       TINYINT(1) DEFAULT 0
version          INT DEFAULT 1
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP
```

#### n8n_workflows
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
client_id        INT NOT NULL
workflow_name    VARCHAR(255)
workflow_id      VARCHAR(100)                      -- ID interno de N8N
status           ENUM('active','paused','archived') DEFAULT 'active'
webhook_url      VARCHAR(500)
config_json      TEXT                               -- Variables y triggers
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP
```

#### channel_types
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
type_name        VARCHAR(50) UNIQUE NOT NULL        -- whatsapp, instagram, etc.
display_name     VARCHAR(100)
description      TEXT
icon             VARCHAR(50)
is_active        TINYINT(1) DEFAULT 1
config_schema    TEXT                                -- JSON schema para validar config
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP
```

#### support_tickets
```sql
id               INT AUTO_INCREMENT PRIMARY KEY
ticket_number    VARCHAR(20) UNIQUE                 -- TKT-YYYYMMDD-XXXX
client_id        INT
subject          VARCHAR(255) NOT NULL
description      TEXT
status           ENUM('open','in_progress','resolved','closed') DEFAULT 'open'
created_by_email VARCHAR(255)
assigned_to      INT                                -- FK → agents.id
priority         ENUM('low','normal','high','urgent') DEFAULT 'normal'
sender_email     VARCHAR(255)
sender_name      VARCHAR(255)
resolved_at      DATETIME
admin_notes      TEXT
images_json      TEXT                               -- ["/uploads/img1.jpg", ...]
created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP
```

#### ai_usage_log
```sql
id                 INT AUTO_INCREMENT PRIMARY KEY
client_identifier  VARCHAR(255)
model_used         VARCHAR(100)                     -- gpt-4o, gpt-4o-mini, whisper-1, tts-1
prompt_tokens      INT
completion_tokens  INT
total_tokens       INT
cost_estimated     DECIMAL(10,6)
cost_currency      VARCHAR(10) DEFAULT 'USD'
created_at         DATETIME DEFAULT CURRENT_TIMESTAMP
```

#### credentials_vault
```sql
id                INT AUTO_INCREMENT PRIMARY KEY
client_id         INT NOT NULL
credential_name   VARCHAR(255)                      -- whatsapp_token, api_key, etc.
credential_value  TEXT                               -- Encriptado AES-256-CBC
credential_type   VARCHAR(50)                        -- api_key, password, token, etc.
last_rotated_at   DATETIME
created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
```

#### period_management
```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
client_id             INT NOT NULL
period_start          DATE
period_end            DATE
billing_type          ENUM('monthly','annual') DEFAULT 'monthly'
notification_schedule TEXT                           -- JSON: ["7","3","1"]
created_at            DATETIME DEFAULT CURRENT_TIMESTAMP
```

---

## 6. Autenticación y Autorización

### 6.1 Tres vías de autenticación paralelas

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   ADMIN (WP)    │     │     AGENTE      │     │    CLIENTE      │
│                 │     │                 │     │                 │
│ X-Admin-Token   │     │ Cookie sesión   │     │ X-API-Key       │
│ (HMAC-SHA256)   │     │ PHP session_id  │     │ (64 chars)      │
│                 │     │                 │     │                 │
│ Secret:         │     │ Validación:     │     │ Validación:     │
│ OMNI_ADMIN_     │     │ tabla agents    │     │ tabla clients   │
│ SECRET          │     │ + is_active=1   │     │ + status≠       │
│ (wp-config)     │     │                 │     │   suspended     │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

### 6.2 Token Admin (HMAC-SHA256)

**Generación (login):**
```
payload = base64(json("user_id", "email", "role", "exp"))
signature = HMAC-SHA256(payload, OMNI_ADMIN_SECRET)
token = payload.signature
```

**Verificación:**
```
[payload, signature] = split(token, ".")
expected = HMAC-SHA256(payload, OMNI_ADMIN_SECRET)
valid = hash_equals(expected, signature) && !expired
```

### 6.3 Callback N8N (HMAC-SHA256)

**Generación:**
```
data = "channel_id:conversation_id:message_id:timestamp"
token = HMAC-SHA256(data, OMNI_ADMIN_SECRET)
```

**Verificación en callback:**
```
Reconstruye el data desde los campos del body
Calcula HMAC y compara con hash_equals()
```

---

## 7. Webhooks y Mensajería

### 7.1 Flujo de Mensaje Entrante (WhatsApp → Portal)

```
1. Usuario envía WhatsApp
2. YCloud dispara POST → /api-omnichannel.php?route=webhook/ycloud?channel_id={ID}&secret={SECRET}
3. api-omnichannel.php valida secret contra channels.webhook_secret
4. controller->handle_ycloud_webhook($body) parsea el evento YCloud
5. controller->receive_message() guarda en DB:
   - Busca/crea conversation por external_contact_id
   - Inserta en messages (direction=inbound, sender_type=contact)
   - Actualiza conversation (last_message_at, unread_count++)
6. Evalúa: ¿conv.status === 'bot' && intervention_mode !== 'human'?
   → SÍ: forward_to_n8n() — payload estandarizado al webhook N8N
   → NO: mensaje queda para agente humano
```

### 7.2 Flujo de Respuesta Bot (N8N → YCloud via Portal)

```
1. N8N procesa mensaje (GPT, Redis buffer, lógica)
2. N8N envía POST → /api-omnichannel.php?route=webhook/n8n-callback
3. controller->handle_n8n_callback() valida HMAC token
4. Detecta tipo:
   a) RAW (type+from+to): handle_n8n_callback_raw()
      - Extrae texto para DB
      - Guarda message (direction=outbound, sender_type=bot)
      - Limpia campos portal
      - Reenvía raw a api.ycloud.com/v2/whatsapp/messages
   b) SIMPLE (content): send_ycloud_message()
      - Guarda message
      - Envía texto a YCloud
5. Usuario recibe en WhatsApp
```

### 7.3 Intervención Humana

```
1. Agente → POST /conversations/{id}/start-intervention/{agent_id}
2. conversation.status = 'assigned', intervention_mode = 'human'
3. Mensajes entrantes ya NO se reenvían a N8N
4. Agente responde desde portal → YCloud → usuario
5. Agente → POST /conversations/{id}/end-intervention/{agent_id}
6. conversation.status = 'bot', intervention_mode = NULL
7. Siguiente mensaje del usuario se reenvía a N8N
```

---

## 8. Integración N8N

### 8.1 Arquitectura (Opción B — Portal como entrada)

```
WhatsApp ↔ YCloud ↔ Portal OmniCliente ↔ N8N ↔ {Redis, GPT, Google Sheets, Calendar}
                         ↕
                     MySQL DB
                         ↕
                    React SPA
```

### 8.2 Workflow v7 (Portal OmniCliente)

**Archivo:** `N8N/TEMPLATES/kellscapilar/WhatsApp_Bot_v7_Portal_OmniCliente.json`

| Métrica | Valor |
|---|---|
| Nodos totales | 86+ |
| Nodos de envío al portal | 18 |
| Nodos directos a YCloud | 1 (Upload Audio) |
| Credenciales requeridas | Google Sheets, Redis, SMTP, OpenAI, YCloud (solo audio) |

**Pipeline principal:**
```
WhatsApp Webhook → Filter Event Type → Has Message? → Extract Message Data
→ Deduplication → Redis Push → Redis Get → Check Message Status
→ Wait 7s loop → Combine Messages → Tipo de Mensaje (router)
→ [text/audio/interactive/image] → GPT Processing → Send Response → Portal Callback
```

### 8.3 Workflows de Producción (N8N/PROD/)

| Workflow | Propósito |
|---|---|
| WhatsApp_Tech_Principal | Bot principal de WhatsApp |
| WhatsApp_Tech_Agendamiento_v3 | Agendamiento de citas |
| WhatsApp_Tech_Recordatorios | Sistema de recordatorios |
| WhatsApp_Tech_Buffer_Agent | Cola de mensajes |
| Tech_Reminder_1h/24h/72h | Recordatorios por tiempo |
| Sync_Calendar_DB | Sincronización Google Calendar → DB |
| Followup_Meeting_Scheduler_v3 | Programación de seguimientos |
| Omnichannel_Expiry_Reminders_Cron | Recordatorios de vencimiento |
| Argos detección de Errores N8N | Monitoreo de errores |
| Clean_Redis_State | Limpieza de cache Redis |

### 8.4 Script de Transformación v6 → v7

**Archivo:** `N8N/TEMPLATES/kellscapilar/transform-v6-to-v7.js`

```bash
node transform-v6-to-v7.js
# Input: WhatsApp_Bot_v6_KellsCapilar.json
# Output: WhatsApp_Bot_v7_Portal_OmniCliente.json
```

Transforma automáticamente:
- Nodos de entrada para formato portal
- 18 nodos de envío de YCloud a portal callback
- 4 patrones de jsonBody (template literal, JSON.stringify, IIFE, ternary)
- Conserva Upload Audio y Download Audio con credenciales YCloud

---

## 9. Sistema de Emails

### 9.1 Configuración

Usa `wp_mail()` de WordPress con headers HTML:
```php
$headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: OmniCliente <noreply@automatizatech.cl>'
];
```

BCC automático a `OMNI_MASTER_EMAIL` (definido en wp-config.php).

### 9.2 Template HTML

Todos los emails usan un template consistente:
- Header con logo AutomatizaTech
- Cuerpo con contenido dinámico
- Footer con datos de contacto + link al portal

### 9.3 Regla DB-antes-de-Email

Todas las 8 funciones de email siguen la regla:
1. Ejecutar operación de base de datos
2. Si DB falla → retornar error, NO enviar email
3. Si DB éxito → enviar email
4. Si email falla → la operación de DB ya está guardada (se informa pero no revierte)

---

## 10. WordPress Integration

### 10.1 Must-Use Plugins

**api-appointments-management.php** (`wp-content/mu-plugins/`):
```
GET  /wp-json/automatiza-tech/v1/appointments
POST /wp-json/automatiza-tech/v1/appointments
PUT  /wp-json/automatiza-tech/v1/appointments/{id}
DELETE /wp-json/automatiza-tech/v1/appointments/{id}
```

### 10.2 Tema Personalizado

**automatiza-tech/** (`wp-content/themes/`):
- Módulo de contacto (`inc/contact-form.php`)
- Servicios frontend (`inc/services-frontend.php`)
- QA Testing módulo (`inc/admin-qa-module.php`)
- Facturación PDF con FPDF (`lib/invoice-pdf-fpdf.php`)
- Verificación de facturas (`check-facturas-db.php`)

### 10.3 Constantes wp-config.php

| Constante | Propósito |
|---|---|
| `OMNI_ADMIN_SECRET` | Secreto para HMAC-SHA256 (tokens, callbacks) |
| `OMNI_MASTER_EMAIL` | Email BCC para auditoría |
| `OMNICHANNEL_WEBHOOK_SECRET` | Secreto default para webhooks |
| `OMNICHANNEL_CRON_SECRET` | Secreto para endpoints cron |
| `OMNICHANNEL_N8N_WEBHOOK_URL` | URL del webhook N8N |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST` | Conexión MySQL |

---

## 11. Scripts de Utilidad

### 11.1 Setup (ejecución única)

| Script | Tabla(s)/Función |
|---|---|
| `setup-omnichannel-prod.php` | Setup unificado: 12+ tablas + migraciones |
| `setup-omnichannel-db.php` | Tablas core (clients, channels, conversations, messages) |
| `setup-omnichannel-v2.php` | bot_templates |
| `setup-omnichannel-v3.php` | audit_log, takeovers |
| `setup-channel-types.php` | channel_types |
| `setup-agent-password-reset.php` | Columnas reset en agents |
| `setup-period-management.php` | period_management |
| `setup-ticket-attachments.php` | Columnas attachments en support_tickets |
| `setup-credentials-vault.php` | credentials_vault |
| `setup-chat-historial.php` | Chat history para IA |
| `setup-client-details-tables.php` | Metadata de clientes |
| `setup-maxtech-tables.php` | ai_usage_log + CRM chat |
| `setup-n8n-errors-db.php` | Errores N8N (ARGOS) |
| `setup-google-drive.php` | Integración Google Drive |
| `setup-proxy-production.php` | Proxy webhook N8N |

### 11.2 Verificación

| Script | Verifica |
|---|---|
| `check-agent-cols.php` | Columnas tabla agents |
| `check-ai-tables.php` | Tabla ai_usage_log |
| `check-db-columns.php` | Estructura de tablas personalizadas |
| `check-all-services.php` | Servicios registrados en DB |

### 11.3 Debug

| Script | Depura |
|---|---|
| `debug-check-availability.php` | Validador de disponibilidad |
| `debug-reminder-followup.php` | Consultas de recordatorios |
| `debug-reminders-prod.php` | Recordatorios en producción |

### 11.4 Reparación

| Script | Repara |
|---|---|
| `fix-ai-usage-log-columns.php` | Columnas faltantes en ai_usage_log |
| `fix-omnichannel-clients-table.php` | Tabla clients |
| `fix-leads-schema.php` | Esquema de leads |

### 11.5 Mantenimiento

| Script | Función |
|---|---|
| `flush-api-routes.php` | Limpiar cache de rutas API |
| `flush-rewrite.php` | Resetear reglas rewrite WordPress |
| `purge-cache.php` | Limpiar todas las caches |
| `reset-opcache.php` | Resetear OPcache PHP |

---

## 12. Despliegue y Configuración

### 12.1 Entorno Local (Desarrollo)

```bash
# 1. Clonar repositorio
git clone https://github.com/lmgmuber-bit/automatiza-tech
cd automatiza-tech

# 2. Configurar WordPress
# Copiar wp-config-local.php → wp-config.php
# Ajustar DB_NAME, DB_USER, DB_PASSWORD, DB_HOST

# 3. Instalar dependencias frontend
cd client-portal-omnichannel
npm install

# 4. Desarrollo con hot reload
npm run dev
# → http://localhost:5173 (con proxy a API)

# 5. Setup DB
# Visitar: http://localhost/automatiza-tech/setup-omnichannel-prod.php
```

### 12.2 Producción (Hostinger)

```bash
# 1. Build frontend
cd client-portal-omnichannel
npm run build

# 2. Copiar dist → omnicliente/
cp -r dist/* ../omnicliente/

# 3. Limpiar assets antiguos del directorio omnicliente/assets/

# 4. Commit y push
git add -A
git commit -m "build: deploy v1.x"
git push origin prod-sync-2025-06-26

# 5. En Hostinger: Git pull o upload via file manager

# 6. En producción, ejecutar setup (una vez):
# https://automatizatech.cl/setup-omnichannel-prod.php

# 7. Purgar cache:
# https://automatizatech.cl/purge-cache.php
# https://automatizatech.cl/reset-opcache.php
```

### 12.3 .htaccess (Producción)

```apache
# Redirigir API
RewriteRule ^api-omnichannel\.php$ /api-omnichannel.php [L]

# SPA fallback para omnicliente
RewriteRule ^omnicliente/(.*)$ /omnicliente/index.html [L]
```

---

## 13. Seguridad

### 13.1 Medidas Implementadas

| Área | Medida |
|---|---|
| Autenticación admin | HMAC-SHA256 tokens con expiración |
| Autenticación agente | Sesiones PHP server-side |
| Autenticación cliente | API keys únicas de 64 caracteres |
| Webhook validation | Secret por canal + HMAC para N8N callback |
| Passwords | bcrypt hash (password_hash / password_verify) |
| Credenciales | AES-256-CBC + PBKDF2 en credentials_vault |
| SQL | Prepared statements via WordPress $wpdb |
| XSS | Sanitización de inputs, Content-Type JSON |
| CORS | Headers controlados en API |
| Email | BCC auditoría a OMNI_MASTER_EMAIL |
| Rate limiting | Config por canal en config_json |
| Uploads | Validación MIME type + tamaño (3MB por imagen, 5 max) |
| Audit trail | Toda acción registrada en audit_log |

### 13.2 Secretos en wp-config.php

```php
define('OMNI_ADMIN_SECRET', '...');     // NO commitear
define('OMNI_MASTER_EMAIL', '...');     // BCC auditoría
define('OMNICHANNEL_CRON_SECRET', '...');
```

---

## 14. APIs Externas

### 14.1 YCloud (WhatsApp Business API)

| Endpoint | Uso |
|---|---|
| `POST api.ycloud.com/v2/whatsapp/messages` | Enviar mensajes (text, interactive, template) |
| `POST api.ycloud.com/v2/whatsapp/media/{phone}/upload` | Subir audio/media |
| Webhook inbound | Recibir mensajes entrantes |

**Auth:** `X-API-Key` header  
**Almacenamiento:** `channels.credentials_json.ycloud_api_key`

### 14.2 OpenAI

| Modelo | Uso |
|---|---|
| GPT-4o | Análisis de imágenes (receipts, transfer IDs) |
| GPT-4o-mini | Respuestas del bot (más económico) |
| Whisper | Transcripción de audio |
| TTS-1 | Generación de audio |

### 14.3 Google Calendar

- Sincronización bidireccional (via N8N)
- Crear/actualizar/eliminar eventos
- Validación de disponibilidad

### 14.4 Google Sheets

- Base de datos ligera para bots
- Registro de citas (Kells Capilares: columna Y = transfer_id)
- Exportación de datos

### 14.5 Redis

- Buffer de mensajes en N8N (agrupa mensajes rápidos)
- Wait 7s loop para deduplicación
- Limpieza automática de estados

---

*Este documento es la referencia técnica completa del ecosistema AutomatizaTech. Para una visión general no técnica, consultar AUTOMATIZATECH_GENERAL.md*
