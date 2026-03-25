# AutomatizaTech — Contexto Completo del Proyecto

> **Versión:** 2.0  
> **Última actualización:** 25 de marzo de 2026  
> **Propósito:** Documento maestro de contexto para cualquier IA, desarrollador o equipo que tome este proyecto. Contiene TODO lo necesario para continuar sin perder contexto.  
> **Rama activa:** `prod-sync-2025-06-26` | **Commit referencia:** `b1c05b9`

---

## ÍNDICE RÁPIDO

1. [¿Qué es AutomatizaTech?](#1-qué-es-automatizatech)
2. [Entornos y Accesos](#2-entornos-y-accesos)
3. [Stack Tecnológico Completo](#3-stack-tecnológico-completo)
4. [Arquitectura General](#4-arquitectura-general)
5. [Portal OmniCliente — Estructura Completa](#5-portal-omnicliente--estructura-completa)
6. [Base de Datos OmniCliente](#6-base-de-datos-omnicliente)
7. [API OmniCliente — Endpoints](#7-api-omnicliente--endpoints)
8. [Sistema de Autenticación](#8-sistema-de-autenticación)
9. [Integración N8N ↔ Portal](#9-integración-n8n--portal)
10. [KellsCapilar — Cliente Activo](#10-kellscapilar--cliente-activo)
11. [Bot WhatsApp v8 — Arquitectura](#11-bot-whatsapp-v8--arquitectura)
12. [Sistema de Prompts (PromptsView)](#12-sistema-de-prompts-promptsview)
13. [Plataforma Principal WP/CRM](#13-plataforma-principal-wpcrm)
14. [Estado Actual y Tareas Pendientes](#14-estado-actual-y-tareas-pendientes)
15. [Convenciones y Reglas del Proyecto](#15-convenciones-y-reglas-del-proyecto)
16. [Scripts de Utilidad PHP](#16-scripts-de-utilidad-php)
17. [Seguridad y Credenciales](#17-seguridad-y-credenciales)

---

## 1. ¿Qué es AutomatizaTech?

**AutomatizaTech** es una consultora tecnológica chilena que opera la plataforma `automatizatech.cl`. Ofrece:

- **Para sí misma (AT):** Un sistema CRM + automatización + agente IA para gestionar sus propios clientes y proyectos.
- **Para sus clientes (B2B):** Bots de WhatsApp personalizados, sistema de agendamiento, y el **Portal OmniCliente** que cada cliente usa para ver y gestionar sus conversaciones.

### Identidad
- **Dominio:** automatizatech.cl
- **País:** Chile (moneda CLP + USD con conversión automática desde Banco Central)
- **Slogan:** "Conectamos tus ventas, web y CRM."
- **Tagline:** "Bots inteligentes para negocios que no se detienen."
- **Colores:** Azul eléctrico, blanco, verde lima

---

## 2. Entornos y Accesos

| Parámetro | Local | Producción |
|-----------|-------|------------|
| URL | `http://localhost/automatiza-tech/` | `https://automatizatech.cl/` |
| BD | `automatiza_tech_local` | `u402745362_automatizatech` |
| Servidor | WAMP64 (Windows) PHP 8.3 MySQL 9.1 | Hostinger LiteSpeed PHP 8.2+ |
| Prefijo tablas | `wp_` | `wp_` |
| Banner de entorno | ⚠️ Banner naranja "AMBIENTE LOCAL" | No visible |
| WP_DEBUG | true (no display) | false |

### Detección de entorno (wp-config.php)
```php
// Si HTTP_HOST contiene 'localhost' → BD local (root, sin password)
// De lo contrario → BD Hostinger con credenciales PROD
```

### Deploy
**Manual**: Los archivos se suben por SFTP a Hostinger. NO hay CI/CD automático desde Git.  
El `.gitignore` excluye: `wp-admin/`, `wp-includes/`, core WP, uploads, `wp-config.php`, `.htaccess`, scripts temporales (`test-*.php`, `debug-*.php`, etc.), backups.

### N8N
- **URL:** `https://n8n-n8n.kchiba.easypanel.host`
- **Plataforma:** Self-hosted en EasyPanel (VPS)
- **Estado Redis:** Usa Redis para buffer de mensajes WhatsApp

---

## 3. Stack Tecnológico Completo

### Plataforma Principal (WordPress)
| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| CMS | WordPress | 6.8.3 |
| Backend | PHP | 8.2+ (PROD) / 8.3 (LOCAL) |
| Frontend tema | Bootstrap 5 + jQuery 3.6 | — |
| PDFs | FPDF 1.86 | Facturas, cotizaciones, boletas |
| Gráficos | Chart.js (CDN) | Dashboard |
| SMTP | PHPMailer | smtp.hostinger.com:587 TLS |

### Portal OmniCliente (SPA sobre WP)
| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| UI Framework | React | 19.1.0 |
| Build tool | Vite | 6.4.1 |
| CSS | Tailwind CSS | 3.4.17 |
| Iconos | Lucide React | 0.577.0 |
| Routing | Estado interno React (`currentView`) — sin react-router | — |
| Build output | `portal-omnichannel/` | `index-{hash}.js` (~434KB) |

### IA y Automatización
| Servicio | Uso |
|---------|-----|
| OpenAI GPT-4o | Chat MAXTECH, agente IA, procesamiento complejo |
| OpenAI GPT-4o-mini | Respuestas rápidas de texto |
| OpenAI GPT-4V | Validación comprobantes de pago (imágenes) |
| OpenAI Whisper | Transcripción de audio |
| OpenAI TTS | Respuestas en audio |
| N8N | Automatización (WhatsApp, email, Google Calendar) |
| Redis | Estado de conversación en N8N |
| Google Drive SA | Lectura de archivos de clientes (Service Account) |
| Google Drive OAuth | OCR de PDFs (conversión a Google Doc) |
| Google Calendar | Agenda de citas vía N8N |
| YCloud | API de WhatsApp Business oficial |

---

## 4. Arquitectura General

```
┌─────────────────────────────────────────────────────────────────┐
│                     Usuario Final (WhatsApp)                     │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                    ┌───────▼────────┐
                    │   YCloud API   │  (API WhatsApp Business)
                    └───────┬────────┘
                            │ webhook
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│              Portal OmniCliente (automatizatech.cl)              │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  webhook-omnichannel.php → omnichannel-controller.php   │    │
│  │   → guarda mensaje en DB → decide si bot o humano       │    │
│  └───────────────────┬─────────────────────────────────────┘    │
│                      │ forward_to_n8n() (si bot activo)          │
└──────────────────────┼───────────────────────────────────────────┘
                       │
              ┌────────▼────────┐
              │   N8N Workflow  │  WhatsApp_Bot_v8_Portal_OmniCliente
              │  (GPT, Redis,   │  - Fetch Portal Config (API)
              │   GSheets)      │  - Google Sheets fallback
              └────────┬────────┘
                       │ callback POST /api-omnichannel.php?route=webhook/n8n-callback
                       ▼
              ┌─────────────────┐
              │   Portal → DB   │  guarda respuesta
              │   Portal → YCloud│ → Usuario
              └─────────────────┘


Agentes/Supervisores:
    Portal React SPA ──HTTP──► api-omnichannel.php ──► Controller ──► MySQL
```

---

## 5. Portal OmniCliente — Estructura Completa

### Archivos Backend (raíz WordPress)
| Archivo | Líneas | Propósito |
|---------|--------|-----------|
| `api-omnichannel.php` | ~1380 | Router API — parsea `?route=...`, valida auth, llama al controller |
| `omnichannel-controller.php` | ~3150 | Toda la lógica: CRUD, webhooks, N8N callbacks, auditoría |
| `webhook-omnichannel.php` | — | Receptor webhooks YCloud (WhatsApp, Instagram, Telegram, Messenger) |
| `admin-omnichannel-superadmin.php` | — | Panel Super Admin WordPress |

### Frontend React (`client-portal-omnichannel/`)
```
src/
├── App.jsx              # Layout, auth state, routing por currentView
├── api.js               # Todas las llamadas HTTP + helpers de auth (~330 líneas)
├── index.css            # Estilos globales + responsive + dark mode
├── main.jsx             # Entry point React
└── components/
    ├── Sidebar.jsx           # Nav lateral con badges por rol
    ├── LoginScreen.jsx        # 3 tabs (Agente / Admin / Cliente) + soporte público
    ├── InboxView.jsx          # Bandeja unificada de conversaciones
    ├── ChannelsView.jsx       # CRUD canales (WhatsApp, etc.)
    ├── ChannelTypesView.jsx   # CRUD tipos de canal
    ├── BotsView.jsx           # Config de bots por canal
    ├── PromptsView.jsx        # ⭐ Config de prompts IA por canal (7 secciones, 40+ campos)
    ├── AgentsView.jsx         # Gestión agentes
    ├── AuditView.jsx          # Log auditoría completo
    ├── ClientsView.jsx        # Gestión clientes (solo admin AT)
    ├── DashboardView.jsx      # Stats y métricas
    ├── ProfileView.jsx        # Perfil del agente
    ├── SupportView.jsx        # Tickets soporte + notificaciones
    ├── ConfirmDeleteModal.jsx
    ├── ResultModal.jsx
    ├── ExpiryWarningModal.jsx
    ├── TicketNotificationModal.jsx
    └── ChannelBadge.jsx
```

### Build / Deploy
```bash
cd client-portal-omnichannel
npm run build
# Output → dist/ → copiar a portal-omnichannel/
# Archivos actuales: index-MEpVV2Od.js (434KB), index-BjandwI9.css (71KB)
```

**Importante:** El `vite.config.js` tiene `base: '/portal-omnichannel/'` para que los assets se resuelvan correctamente desde la ruta de producción.

---

## 6. Base de Datos OmniCliente

Prefijo: `wp_omnichannel_`

| Tabla | Propósito |
|-------|-----------|
| `clients` | Empresas/clientes con acceso al portal (API Key hasheada) |
| `channels` | Canales configurados por cada cliente (WhatsApp, IG, etc.) |
| `conversations` | Conversaciones unificadas (una por usuario+canal) |
| `messages` | Todos los mensajes (inbound + outbound, bot + human) |
| `prompt_configs` | ⭐ Configuraciones de prompts IA por canal (`prompt_data` JSON) |
| `audit_log` | Registro completo de acciones |
| `takeovers` | Registro de toma de control por agentes |
| `agents` | Agentes/usuarios del portal (email + password hash) |
| `support_tickets` | Tickets internos de soporte |
| `channel_types` | Tipos de canal disponibles |

### Tabla `prompt_configs` (clave para el bot)
```sql
CREATE TABLE wp_omnichannel_prompt_configs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel_id INT NOT NULL,           -- FK → channels.id
  config_name VARCHAR(255),
  prompt_data LONGTEXT,              -- JSON con todos los campos del prompt
  version INT DEFAULT 1,
  is_active TINYINT DEFAULT 1,
  created_by INT,
  updated_by INT,
  created_at DATETIME,
  updated_at DATETIME
)
```

#### Estructura de `prompt_data` (campos del JSON)
```json
{
  "nombre_negocio": "Kellscapilar",
  "nombre_asistente": "Kells 👑",
  "emoji_principal": "👑",
  "saludo": "Hola, soy Kells...",
  "max_parrafos": "2-3",
  "emojis": "👑❤️🌹✨",
  "funcion_asistente": "Dar información sobre tratamientos capilares...",
  "tono": "cálido y profesional",
  "horario": "Lunes a Viernes 10:00 - 18:00",
  "duracion_servicios": "Entre 30 minutos a 3 horas...",
  "requerimientos": "...",
  "respuesta_agendar": "¡Perfecto! Te muestro los servicios...",
  "respuesta_cancelar": "Entiendo. Buscaré tu cita...",
  "respuesta_escalacion": "En los próximos minutos...",
  "categorias_servicios": "🌟 *Alisados*...",
  "catalogo_servicios_detallado": "[{\"id\":1,\"nombre\":\"Alisado Corto\",\"duracion_min\":120,\"precio\":45000}]",
  "info_servicios": "Tenemos 24 servicios disponibles...",
  "info_tecnica": "*Botox Capilar:...",
  "restricciones": "- Dar paso a paso técnico...",
  "capacidades": "- Explicar tratamientos...",
  "ejemplo_conversacion": "**Cliente**: Necesito alisar...",
  "negocio_telefono": "56 9 75991137",
  "negocio_direccion": "Argomedo 320...",
  "negocio_instagram": "@kellscapilar",
  "negocio_facebook": "facebook.com/kellscapilar",
  "negocio_tiktok": "0",
  "negocio_enlace_maps": "https://maps.app.goo.gl/...",
  "negocio_cuenta_bancaria": "KELLYS TIRADO\n26.312.327-1\nBanco Itaú...",
  "negocio_cuenta_bancaria2": "0",
  "condiciones_agendamiento": "Para hacer efectiva la reserva...",
  "condiciones_reembolso": "Tomar en cuenta que este monto...",
  "pago_abono": "20000",
  "horario_inicio": "9:00",
  "horario_fin": "18:00",
  "dias_habiles": "1,2,3,4,5",
  "intervalo_slots": "60",
  "buffer_entre_citas": "10",
  "moneda_codigo": "CLP",
  "moneda_simbolo": "$",
  "moneda_nombre": "Pesos Chilenos",
  "bloqueos_horario": "[{\"fecha\":\"*\",\"hora_inicio\":\"13:00\",\"hora_fin\":\"14:00\",\"motivo\":\"Almuerzo\",\"recurrente\":\"diario\"}]"
}
```

---

## 7. API OmniCliente — Endpoints

**Base URL:** `https://automatizatech.cl/api-omnichannel.php?route={RUTA}`

### Rutas de Cliente (header: `X-API-Key`)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `conversations` | Listar conversaciones |
| GET | `conversations/{id}/messages` | Ver mensajes |
| POST | `conversations/{id}/messages` | Enviar mensaje |
| POST | `conversations/{id}/takeover` | Tomar control (bot → humano) |
| POST | `conversations/{id}/release` | Devolver al bot |
| GET | `channels` | Listar canales |
| POST | `channels` | Crear canal |
| PUT | `channels/{id}` | Editar canal |
| GET | `prompt-configs` | Listar configs de prompts del canal |
| GET | `prompt-config/{id}` | Obtener config de prompt (incluye autenticación HMAC para N8N) |
| POST | `prompt-configs` | Crear config de prompt |
| PUT | `prompt-configs/{id}` | Actualizar config de prompt |
| GET | `agents` | Listar agentes |
| POST | `agents` | Crear agente |
| GET | `audit` | Ver auditoría |

### Rutas Admin WP (sesión WordPress o `X-Admin-Token`)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `admin/stats` | Estadísticas globales |
| GET | `admin/clients` | Listar todos los clientes |
| POST | `admin/clients` | Crear cliente |
| PUT | `admin/clients/{id}` | Editar cliente |
| GET | `admin/audit` | Auditoría global |

### Webhook (token HMAC-SHA256 en query string)
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST/GET | `webhook/{channel_type}?channel_id={id}&secret={secret}` | Entrada de mensajes YCloud |
| POST | `webhook/n8n-callback` | Respuestas de N8N al portal |

### Endpoint de Prompt Config para N8N
```
GET /api-omnichannel.php?route=prompt-config/{channel_id}&token={HMAC}
```
**Token:** `hash_hmac('sha256', "prompt-config:{channel_id}", OMNI_ADMIN_SECRET)`  
**Respuesta:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "channel_id": 1,
    "config_name": "KellsCapilar - Bot Principal",
    "prompt_data": { /* todos los campos del bot */ },
    "version": 5,
    "is_active": 1
  }
}
```

---

## 8. Sistema de Autenticación

### Tres modos de autenticación

#### A) Cliente (empresa) — API Key
- **Header:** `X-API-Key`
- **Almacenamiento frontend:** `localStorage.omni_api_key`
- **Generación:** Super Admin AT genera API Key aleatoria, se hashea (SHA-256) en BD
- **Acceso:** Solo sus propios recursos (canales, conversaciones, agentes)

#### B) Administrador AT — Admin Token
- **Header:** `X-Admin-Token` (HMAC-SHA256, TTL 7 días)
- **Almacenamiento:** `localStorage.omni_admin_token`
- **Flag:** `localStorage.omni_is_admin = 'true'`
- **Generación:** Login WordPress → genera token vía `generate_admin_token()`
- **Acceso:** Todo (clientes, stats globales, auditoría global)

#### C) Agente — Agent Token
- **Header:** `X-Agent-Token`
- **Almacenamiento:** `localStorage.omni_agent_token`
- **Generación:** Login email+password en portal → `generate_agent_token()`
- **Acceso:** Conversaciones asignadas, perfil propio

#### D) Supervisor
- Mismo mecanismo que Agente pero con rol `supervisor` en BD
- Acceso: Bandeja unificada de TODO el cliente, config bots, agentes, auditoría del cliente

#### E) N8N → Portal (HMAC callback token)
- `callback_token` en payload `forward_to_n8n()`
- Validado en `handle_n8n_callback()` con `OMNI_ADMIN_SECRET`
- Sin expiración de sesión pero token es de un solo uso efectivo

---

## 9. Integración N8N ↔ Portal

### Flujo completo de un mensaje

```
1. Usuario escribe en WhatsApp
2. YCloud → POST webhook-omnichannel.php?channel_id=X&secret=Y
3. Portal: handle_ycloud_webhook() → receive_message()
4. Portal guarda mensaje en DB (messages, direction=inbound)
5. ¿conversation.status === 'bot' && intervention_mode !== 'human'?
   SÍ → forward_to_n8n() (fire-and-forget, timeout 5s)
   NO → espera agente humano
6. N8N procesa: GPT-4o, cálculo disponibilidad, agenda, etc.
7. N8N POST /api-omnichannel.php?route=webhook/n8n-callback
8. Portal valida token HMAC
9. Portal detecta modo:
   - RAW (tiene 'type'+'from'+'to') → reenvía directo a YCloud + guarda en DB
   - SIMPLE (tiene 'content') → send_ycloud_message() + guarda en DB
10. Usuario recibe respuesta
```

### Payload `forward_to_n8n()`
```json
{
  "event": "new_message",
  "business_phone": "+56912345678",
  "contact": { "phone": "+56987654321", "name": "Juan Pérez", "email": null },
  "message": {
    "id": 42,
    "type": "text",
    "content": "Hola, quiero agendar una cita",
    "media_url": null,
    "timestamp": "2026-03-25T01:00:00"
  },
  "conversation_id": 15,
  "channel_id": 3,
  "callback_url": "https://automatizatech.cl/api-omnichannel.php?route=webhook/n8n-callback",
  "callback_token": "HMAC-SHA256-firmado"
}
```

### Intervención humana
```
Agente presiona "Tomar control" → conversation.status → 'assigned', intervention_mode → 'human'
Mensajes entrantes NO se reenvían a N8N
Agente responde desde portal → YCloud → Usuario
Agente termina → conversation.status → 'bot'
Siguiente mensaje del usuario → se reenvía a N8N normalmente
```

---

## 10. KellsCapilar — Cliente Activo

### Descripción del negocio
| Campo | Valor |
|-------|-------|
| Negocio | Kellscapilar (peluquería/estética capilar) |
| Asistente | Kells 👑 |
| Dirección | Argomedo 320, Santiago Centro, Región Metropolitana |
| Teléfono | 56 9 75991137 |
| Instagram | @kellscapilar |
| Maps | https://maps.app.goo.gl/XRBYtfccyMNGTAEy5 |
| Horario | Lunes a Viernes 10:00 - 18:00 |
| Cuenta bancaria | KELLYS TIRADO / 26.312.327-1 / Banco Itaú / 000224080048 |
| Abono reserva | $20.000 CLP (no reembolsable) |

### Servicios (21 en catálogo)
| Categoría | Ejemplos | Precio |
|-----------|---------|--------|
| Alisados | Corto/Medio/Largo | $45k-$65k |
| Botox Capilar | Corto/Medio/Largo | $35k-$55k |
| Cortes | Bordado/Puntas | $5k-$15k |
| Complementos | Hidratación, Nutrición, Restauración, Nano | incluidos en combos |
| Combos | Alisado+Corte, Botox+Corte | $45k-$75k |
| Combos Premium | Con Hidratación | $55k-$65k |

### Agenda
- Horario: 9:00 - 18:00
- Días hábiles: Lunes a Viernes (1,2,3,4,5)
- Intervalo slots: 60 min
- Buffer entre citas: 10 min
- Bloqueo diario: 13:00-14:00 (Almuerzo)
- Feriados bloqueados: 1 Ene, 1 May, 25 Dic + otros puntuales

### Estado en el portal
- `channel_id`: 1 (confirmar en producción)
- `config_name`: "KellsCapilar - Bot Principal"
- Versión actual del config: v3-5 (depende de cuántos scripts se ejecutaron)
- Google Sheets: `1ww6qJe057_HUaPTWgxT9pU1cfp8-HqmLecZjmYGB6Ps`

### Flujo de pago
1. Cliente elige servicio → día → hora
2. Bot genera cita con estado **"Pendiente Pago"** en `staticData.pendingPayments`
3. Bot envía instrucciones bancarias (cuenta, monto $20k)
4. Cliente envía captura de pantalla del comprobante
5. **GPT-4 Vision** valida la imagen (cuenta correcta + transferencia reciente)
6. Si válido → guarda cita en Google Sheets + envía email al negocio
7. Si inválido → pide nuevo comprobante (timeout 30 min)

---

## 11. Bot WhatsApp v8 — Arquitectura

### Archivo
`N8N/TEMPLATES/kellscapilar/WhatsApp_Bot_v8_Portal_OmniCliente.json`

### Versiones
| Versión | Archivo | Estado | Diferencia |
|---------|---------|--------|------------|
| v6 | `WhatsApp_Bot_v6_KellsCapilar.json` | Legado | Solo Google Sheets, sin portal |
| v7 | `WhatsApp_Bot_v7_Portal_OmniCliente.json` | Anterior | Con portal (Opción B), GSheets |
| **v8** | `WhatsApp_Bot_v8_Portal_OmniCliente.json` | **ACTUAL** | Portal API first + GSheets fallback |

### Cambios v7 → v8

#### 1. Nuevo nodo: `Fetch Portal Config` (Code node)
- Posición: entre `Combine Messages` y `Tipo de Mensaje`
- Llama `GET /api-omnichannel.php?route=prompt-config/{CHANNEL_ID}&token={HMAC}`
- Token HMAC generado con `require('crypto')` y `$env.OMNI_ADMIN_SECRET`
- **Cache de 5 minutos** en `staticData.portalConfig` (no llama al portal en cada mensaje)
- En error/timeout → `staticData.portalConfig = null` (fallback silencioso)
- Parsea y almacena:
  - `config`: objeto flat `{clave: valor}` de todos los campos del prompt
  - `configRows`: array `[{parametro, valor}]` para Compute Availability
  - `servicios`: array parseado de `catalogo_servicios_detallado`
  - `bloqueos`: array parseado de `bloqueos_horario`

#### 2. `Merge Config` (modificado)
```javascript
// Portal first:
if (staticData.portalConfig) config = { ...staticData.portalConfig.config };
// Fallback Google Sheets:
else { $input.all().forEach(item => { config[item.json.parametro] = item.json.valor; }); }
```

#### 3. `Compute Availability` (modificado)
```javascript
const _staticDataPC = $getWorkflowStaticData('global');
// configRows: portal o GSheets
const configRows = (_staticDataPC.portalConfig)
  ? _staticDataPC.portalConfig.configRows
  : $('Read Configuracion').all().map(i => i.json);
// blockRows: portal o GSheets
const blockRows = (_staticDataPC.portalConfig)
  ? _staticDataPC.portalConfig.bloqueos
  : $('Read Bloqueos').all().map(i => i.json);
```

#### 4. `Build Services List` (modificado)
```javascript
const rows = (_staticDataPC.portalConfig && _staticDataPC.portalConfig.servicios.length)
  ? _staticDataPC.portalConfig.servicios
  : $input.all().map(i => i.json);
```

#### 5. `Save Selected Service` (modificado)
```javascript
const services = (_staticDataPC.portalConfig && _staticDataPC.portalConfig.servicios.length)
  ? _staticDataPC.portalConfig.servicios
  : $input.all().map(i => i.json);
```

### Configuración requerida para importar v8 en N8N
1. En el nodo `Fetch Portal Config`, línea 10: cambiar `const CHANNEL_ID = 1` por el `id` real del canal en la BD del portal.
2. En N8N → Settings → Environment Variables: añadir `OMNI_ADMIN_SECRET` con el valor de la constante en `wp-config.php`.

### Nodos principales del workflow (92 nodos total)
```
Webhook YCloud
  → Extract Message Data
    → Redis Push (buffer de mensajes)
      → Combine Messages ← ─────────── Entrada unificada
        → Fetch Portal Config (NUEVO v8)
          → Tipo de Mensaje [Switch - 8 outputs]
              0: Get Servicios → Save Selected Service
              7: Prepare Chat Input → Read Bot Config → Merge Config → Agente IA

              [Flujo de disponibilidad]
              → Validate Service Selection → Service Valid? [Switch]
                  0: Read Servicios → Build Services List
                  1: Read Configuracion → Read Bloqueos → Read Citas del Día → Compute Availability
```

---

## 12. Sistema de Prompts (PromptsView)

`client-portal-omnichannel/src/components/PromptsView.jsx`

La vista permite a Supervisores y Admins AT configurar el prompt del bot por canal. Los datos se persisten en `wp_omnichannel_prompt_configs.prompt_data` (JSON).

### 7 Secciones y sus campos

| Sección | ID | Campos |
|---------|-----|--------|
| 🏢 Información del Negocio | `negocio` | nombre_negocio, negocio_telefono, negocio_direccion, negocio_instagram, negocio_facebook, negocio_tiktok, negocio_enlace_maps, horario |
| 🤖 Configuración del Asistente | `asistente` | nombre_asistente, emoji_principal, emojis, tono, max_parrafos, funcion_asistente |
| 💬 Mensajes Predefinidos | `mensajes` | saludo, respuesta_agendar, respuesta_cancelar, respuesta_escalacion |
| 📋 Servicios e Información | `servicios` | categorias_servicios, info_servicios, catalogo_servicios_detallado (JSON), info_tecnica, duracion_servicios, requerimientos |
| 📅 Agenda y Reservas | `agenda` | horario_inicio, horario_fin, dias_habiles, intervalo_slots, buffer_entre_citas, moneda_codigo, moneda_simbolo, moneda_nombre, bloqueos_horario (JSON) |
| 💰 Pagos y Condiciones | `pagos` | condiciones_agendamiento, condiciones_reembolso, pago_abono, negocio_cuenta_bancaria, negocio_cuenta_bancaria2 |
| ⚠️ Reglas y Capacidades | `reglas` | restricciones, capacidades, ejemplo_conversacion |

### Permisos
- **Ver:** Admin AT + Supervisor
- **Editar/Guardar:** Solo Admin AT (`canEdit = getIsAdmin()`)

---

## 13. Plataforma Principal WP/CRM

### MU-Plugins (7 archivos, carga automática)
| Archivo | Líneas | Propósito |
|---------|--------|-----------|
| `crm-ai-completo.php` | 7,305 | CRM principal: ficha cliente, proyectos, dashboard, portal público |
| `aria-agente-core.php` | 2,378 | Motor IA MAXTECH: GPT-4o, RAG Drive, audio, agenda autónoma |
| `aria-widget-flotante.php` | 1,141 | Widget flotante admin: chat, grabación audio, TTS |
| `google-drive-integration.php` | 764 | Drive Service Account: lista/lee archivos |
| `api-appointments-management.php` | 711 | API REST citas (9 endpoints públicos para N8N) |
| `google-drive-oauth.php` | 285 | Drive OAuth: OCR PDFs |
| `api-appointments-config.php` | 151 | API disponibilidad (slots, feriados, horarios) |

### MAXTECH — Agente IA propietario
- Accesible desde panel WP de cualquier usuario
- Conoce: todos los clientes, proyectos, propuestas, leads, agenda, workflows N8N activos
- Puede: analizar documentos (DOCX, XLSX, PPTX, PDF), leer Google Drive de clientes, agendar reuniones con `[AGENDAR_SEGUIMIENTO]`, generar respuestas de voz (TTS), transcribir audio (Whisper)
- Privacidad por rol: non-admins no ven emails, RUTs, montos

### Módulos del Tema (24 archivos inc/)
Los más importantes:
| Módulo | Líneas | Función |
|--------|--------|---------|
| `contact-form.php` | 6,866 | Formulario contacto + CRM leads + seguimiento |
| `admin-followup-meetings.php` | 3,495 | Gestión reuniones de seguimiento |
| `services-manager.php` | 2,267 | Admin de servicios/planes |
| `credentials-vault-module.php` | 2,053 | Bóveda encriptada AES-256-CBC |
| `client-details-module.php` | 1,796 | Detalles operativos de clientes |
| `client-operations-module.php` | 1,514 | Info operativa (DNS, herramientas, accesos) |
| `admin-qa-module.php` | 3,114 | QA testing: proyectos, módulos, casos, evidencias |

### Tablas BD principales (WP core + tema)
```
wp_crm_clientes                  → CRM clientes AT
wp_crm_historial                 → Timeline de acciones
wp_crm_proyectos                 → Proyectos activos
wp_crm_chat_historial            → Historial MAXTECH
wp_automatiza_leads              → Leads del bot + formulario
wp_automatiza_propuestas         → Propuestas comerciales
wp_automatiza_propuestas_details → Items de propuesta
wp_automatiza_followup_meetings  → Reuniones de seguimiento
wp_automatiza_tech_clients       → Clientes AT (duplicado de crm_clientes)
wp_automatiza_clients_details    → Detalles operativos
wp_automatiza_n8n_errors         → ARGOS: errores N8N
wp_ai_usage_log                  → Tracking consumo OpenAI
wp_at_qa_projects                → Proyectos QA
wp_at_qa_modules                 → Módulos QA
wp_at_qa_cases                   → Casos de prueba
// + tablas OmniCliente (prefijo wp_omnichannel_)
```

### Sistem de Facturación (FPDF)
- Cotizaciones: numeración `C-AT-YYYYMMDD-XXXX`
- Facturas/Boletas: PDF con QR verificable
- IVA: 19% auto-calculado
- Validación pública: `validar-factura.php`, `validar-boleta.php`
- Email automático al cliente con adjunto PDF

---

## 14. Estado Actual y Tareas Pendientes

### Estado a 25 de marzo de 2026

| Componente | Estado | Notas |
|------------|--------|-------|
| Portal OmniCliente v1.1 | ✅ Prod | `portal-omnichannel/` activo |
| React build | ✅ Deploy | `index-MEpVV2Od.js` (434KB) |
| Sistema de Prompts | ✅ Completo | 7 secciones, 40+ campos |
| PromptsView "Agenda" | ✅ Desplegado | 9 campos nuevos (Feb-Mar 2026) |
| Workflow v8 | ✅ Listo | Archivo JSON generado, pendiente importar |
| Config KellsCapilar (servicios/bloqueos/agenda) | ✅ Listo | Script `update-kells-prompt-config.php` |
| Config KellsCapilar (personalidad bot) | 🔄 Pendiente | Script `update-kells-bot-config.php` listo para ejecutar |
| Workflow v8 importado en N8N | 🔄 Pendiente | Ver instrucciones en sección 11 |
| Transfer ID bancario | ✅ Integrado | En v6+, absorbido en prompt validation |

### Scripts PHP pendientes de ejecutar en producción
```
1. update-kells-prompt-config.php  → servicios + bloqueos + agenda (puede ya estar ejecutado)
2. update-kells-bot-config.php     → 27 campos de personalidad bot
   SECUENCIA: subir → ejecutar → verificar → eliminar
```

### Tareas técnicas pendientes
1. **Importar v8 en N8N:** Subir `WhatsApp_Bot_v8_Portal_OmniCliente.json` via N8N UI
2. **Configurar env vars N8N:** `OMNI_ADMIN_SECRET` en N8N Settings
3. **Configurar CHANNEL_ID** en el nodo `Fetch Portal Config` (línea 10 del code node)
4. **Verificar HMAC endpoint** en producción: `GET /api-omnichannel.php?route=prompt-config/1&token=...`

---

## 15. Convenciones y Reglas del Proyecto

### PHP/WordPress
- Siempre `require_once $wp_load` verificando que exista primero (`file_exists()`)
- Scripts temporales (migrations): agregar `die()` al final, subir → ejecutar → eliminar del servidor
- Nunca credenciales hardcodeadas: usar constantes de `wp-config.php`
- Prefijo tablas portal: `$wpdb->prefix . 'omnichannel_'`
- CORS configurado en `api-omnichannel.php` (header `Access-Control-Allow-Origin`)

### React/Frontend
- Sin react-router: navegación por `currentView` state en `App.jsx`
- `localStorage` para tokens: `omni_api_key`, `omni_admin_token`, `omni_agent_token`, `omni_is_admin`
- Roles: `getIsAdmin()`, `isSupervisorOrAdmin()` desde `api.js`
- Dark mode: clase en `document.documentElement`, preferencia guardada en `localStorage.omni_dark_mode`
- Build: `npm run build` → copiar `dist/` a `portal-omnichannel/`

### N8N
- Flujos en `N8N/PROD/` son producción — NO modificar sin documentar
- Datos de estado global en `$getWorkflowStaticData('global')` (persisten entre ejecuciones)
- `staticData.selectedServiceByPhone[phoneNumber]` → servicio elegido por usuario (TTL 30min)
- `staticData.portalConfig` → configuración cacheada desde el portal (TTL 5min)
- HMAC para seguridad en callback: `hash_hmac('sha256', payload, OMNI_ADMIN_SECRET)`
- Redis: usado para buffer y deduplication de mensajes WhatsApp

### Git
- **Rama activa:** `prod-sync-2025-06-26`
- **No mezclar** ramas sin revisar conflictos en ficheros grandes (crm-ai-completo.php, etc.)
- El `.gitignore` excluye todos los scripts `test-`, `debug-`, `check-`, `fix-`, `add-`, `setup-` de la raíz
- Commit frecuente en rama de trabajo, merge a `prod-sync-*` cuando esté listo

---

## 16. Scripts de Utilidad PHP

Scripts en la raíz WordPress para operaciones puntuales. **Siempre ejecutar y luego eliminar.**

| Script | Propósito | Estado |
|--------|-----------|--------|
| `update-kells-prompt-config.php` | Insertar servicios (21) + bloqueos (5) + agenda para KellsCapilar | Listo, ejecutar |
| `update-kells-bot-config.php` | Insertar 27 campos personalidad bot KellsCapilar | Listo, ejecutar |
| `setup-maxtech-tables.php` | Crear tablas MAXTECH/CRM | Solo si nueva instalación |
| `setup-propuestas-db.php` | Crear tablas propuestas | Solo si nueva instalación |
| `setup-credentials-vault.php` | Crear tabla bóveda | Solo si nueva instalación |
| `setup-chat-historial.php` | Crear tabla historial chat | Solo si nueva instalación |
| `purge-cache.php` | Purga caché LiteSpeed | Producción |
| `flush-rewrite.php` | Flush de rewrite rules WP | Post-deploy |
| `reporte-consumo-ai.php` | Reporte de uso OpenAI | Admin |
| `admin-ai-dashboard.php` | Dashboard visual consumo IA | Admin |

---

## 17. Seguridad y Credenciales

### Variables de entorno / constantes (en wp-config.php)
```php
define('OMNI_ADMIN_SECRET', '...');     // Firmado de tokens HMAC portal↔N8N
define('OPENAI_API_KEY', '...');         // Clave OpenAI (también en Bóveda)
define('GOOGLE_MAPS_API_KEY', '...');    
// + credenciales BD, salts WP, etc.
```

### HMAC entre Portal y N8N
- **Algoritmo:** SHA-256
- **Secreto compartido:** `OMNI_ADMIN_SECRET`
- **Usos:**
  1. Portal → N8N: `callback_token` en `forward_to_n8n()`
  2. N8N → Portal: validación en `handle_n8n_callback()`
  3. N8N → Portal API: `token = hash_hmac('sha256', "prompt-config:{ID}", SECRET)` para `GET prompt-config`
  4. Admin Token WP: `hmac(user_data + timestamp, SECRET)` TTL 7 días

### Bóveda de Credenciales
- Tabla: `wp_automatiza_clients_details` con campos encriptados AES-256-CBC
- Módulo: `inc/credentials-vault-module.php` (2,053 líneas)
- Las credenciales del cliente (SMTP, API keys, etc.) se guardan encriptadas

### OWASP consideraciones activas
- SQL: uso exclusivo de `$wpdb->prepare()` y métodos wpdb en PHP
- XSS: `esc_html()`, `esc_attr()`, `esc_url()` en outputs PHP; React escapa por defecto
- CSRF: nonces WP en formularios admin
- Autenticación: tokens con TTL, HMAC para N8N, API Keys hasheadas (SHA-256) en BD
- Archivos PHPs de configuración: protegidos por `.htaccess` en Hostinger

---

## Archivos de Documentación por Carpeta

```
automatiza-tech/
├── 00_COMIENZA_AQUI.md                     ← Índice de navegación de toda la docs
├── CONTEXTO_COMPLETO.md                    ← ⭐ ESTE ARCHIVO
├── MANUAL_CONTEXTO_IA.md                   ← Contexto técnico Portal OmniCliente
├── MANUAL_PROGRAMADOR.md                   ← Guía dev Portal OmniCliente
├── MANUAL_USUARIO.md                       ← Manual usuario final
├── PORTAL_OMNICANAL_DOCS.md               ← Arquitectura y endpoints portal
├── AUDITORIA_OPENAI_N8N.md               ← Tracking consumo OpenAI
├── INTEGRACION-WORDPRESS-WHATSAPP.md      ← Integración WP ↔ WhatsApp
├── INSTRUCCIONES_GEM_GEMINI.md            ← Contexto para Gemini
├── ESTRATEGIA_VENTAS_AGENDAMIENTO.md      ← Estrategia comercial
├── MATERIAL_PUBLICIDAD_AGENDAMIENTO.md    ← Marketing
├── PROMPTS_VENTAS_AGENDAMIENTO.md         ← Prompts de venta
├── Docs/
│   ├── DOCUMENTO_TECNICO_AUTOMATIZATECH.md  ← Arquitectura WP+CRM completa (Feb 2026)
│   ├── AUTOMATIZATECH_TECNICO.md            ← Técnico Portal OmniCliente (Mar 2026)
│   ├── GUIA_GENERAL_AUTOMATIZATECH.md       ← Visión general plataforma
│   ├── INTEGRACION_N8N_PORTAL_OMNICLIENTE.md← Integración N8N↔Portal detallada
│   ├── ANALISIS_MODULOS_INC.md              ← Inventario módulos inc/ tema
│   ├── MANUAL-USUARIO.md                    ← Manual usuario completo
│   ├── FLUJO_COMERCIAL_TRACKING_AI.md       ← Flujo comercial con IA
│   ├── GUIA_DEPLOY_MOBILE.md               ← Deploy mobile
│   └── CONTEXTO_QA_TESTER_CLAUDE.md        ← QA testing con IA
├── N8N/
│   ├── PROD/                                ← Workflows producción
│   └── TEMPLATES/
│       ├── kellscapilar/
│       │   ├── WhatsApp_Bot_v8_Portal_OmniCliente.json  ← ⭐ WORKFLOW ACTIVO
│       │   ├── WORKFLOW_V8_PORTAL_README.md             ← Docs del v8
│       │   ├── SISTEMA-VALIDACION-PAGO.md               ← Flujo pago
│       │   └── GUIA-INTERVENCION-HUMANA.md              ← Intervención humana
│       └── genericos/                        ← Templates reutilizables
└── RespaldoDocs/                             ← Respaldos históricos (no editar)
```

---

> **Nota para IA:** Si necesitas contexto adicional sobre un módulo específico, los archivos en `Docs/` tienen detalles exhaustivos. El archivo `Docs/DOCUMENTO_TECNICO_AUTOMATIZATECH.md` tiene el árbol completo de código con conteo de líneas por archivo.
