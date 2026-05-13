# 13 — Glosario y Convenciones

## 📖 Glosario

| Término | Definición |
|---------|-----------|
| **AT** | AutomatizaTech (la empresa / la marca). |
| **Cliente** (del SaaS) | Empresa que usa AutomatizaTech (multi-tenant). Vive en `wp_omnichannel_clients`. |
| **Cliente final** | Persona que conversa con un bot/agente del SaaS. Vive en `wp_omnichannel_conversations`. |
| **Lead** | Prospecto capturado desde web/bot, candidato a ser cliente. `wp_automatiza_tech_leads`. |
| **Agente** | Usuario humano del Portal que atiende conversaciones. `wp_omnichannel_agents`. |
| **Bot** | Respondedor automático IA (por cliente). Configurado en `wp_omnichannel_bot_configs`. |
| **Takeover** | Acción del agente de pausar al bot y atender personalmente. `wp_omnichannel_takeovers`. |
| **Channel** | Canal de mensajería (WhatsApp Meta, WhatsApp YCloud, IG, TG, Messenger). `wp_omnichannel_channels`. |
| **Vault** | Almacén cifrado de secretos por cliente. `wp_omnichannel_vault_secrets`. |
| **Portal OmniCliente** | SPA React (`omnicliente/`) — UI de inbox/admin. |
| **Workflow N8N** | Automatización en N8N (JSON). |
| **HMAC** | Hash con secret compartido para autenticación de webhooks. |
| **Helper at-*** | Archivos `at-*.php` raíz con utilidades de seguridad. |
| **Migración** | Script `setup-*.php` o `migrate-*.php` que altera el schema. |
| **Plantilla de bot** | Configuración base reutilizable en `wp_omnichannel_bot_templates`. |
| **Propuesta** | Cotización formal al cliente final. `wp_automatiza_proposals`. |
| **Contrato** | Acuerdo firmado electrónicamente. `wp_automatiza_contracts`. |

---

## 🏷️ Convenciones de naming

### Tablas BD

| Prefijo | Dominio |
|---------|---------|
| `wp_omnichannel_*` | Mensajería omnicanal |
| `wp_automatiza_*` o `wp_automatiza_tech_*` | CRM, propuestas, contratos, leads, citas |
| `ai_usage_log` (sin prefijo) | Tracking IA (excepción histórica) |

### PHP

| Patrón | Uso |
|--------|-----|
| `at_*` | Funciones globales del theme/helpers AT (`at_e`, `at_ajax_require_nonce`) |
| `omni_*` | Funciones/clases de la capa omnichannel (`OmniVault::get`, `omni_send_message`) |
| `AT_*` (CONSTANTES) | Constantes globales del theme |
| `OMNI_*` (CONSTANTES) | Constantes de la capa omnichannel |
| `setup-*.php`, `migrate-*.php`, `add-*.php`, `fix-*.php`, `update-*.php` | Scripts de migración (bloqueados por `.htaccess`) |
| `debug-*.php`, `check-*.php`, `test-*.php` | Scripts de diagnóstico |

### JavaScript / React

| Patrón | Uso |
|--------|-----|
| `omni_*` (localStorage keys) | `omni_api_key`, `omni_admin_token`, `omni_agent_token`, `omni_theme` |
| `<PascalCase>` componentes | `InboxView.jsx`, `BotConfigPanel.jsx` |
| `use*` hooks | `useAuth`, `usePolling` |

### N8N

| Patrón | Uso |
|--------|-----|
| `<Tema>_<Acción>_v<N>` | `WhatsApp_Bot_v8`, `Appointment_Reminder_24h` |
| Sufijo `_DEV` | Workflows en desarrollo |
| Sufijo `_PROD` | (Implícito si está en `N8N/PROD/`) |

### URLs / endpoints

| Patrón | Uso |
|--------|-----|
| `/wp-json/automatiza-tech/v1/*` | REST API del theme |
| `?action=<dot.notation>` en `api-omnichannel.php` | API Portal (ej: `conversations.list`) |
| `/omnicliente/` | SPA build |
| `/portal-omnichannel/?contract=ID` | Vista cliente de contrato |

### Contratos / Propuestas

| Formato | Patrón |
|---------|--------|
| Nº contrato | `AT-CTR-YYYYMMDD-XXXXX` |
| Nº propuesta | `AT-PROP-YYYYMMDD-XXXXX` |
| Token público | 64 caracteres hex (SHA-256) |

---

## 🔐 Variables de entorno y secretos

| Variable | Dónde | Propósito |
|----------|-------|-----------|
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST` | `wp-config.php` | Conexión MySQL |
| `OMNI_ADMIN_SECRET` | `wp-config.php` + N8N env | Token admin Portal + N8N |
| `OMNI_VAULT_PASSPHRASE` | `wp-config.php` | Master key vault (NUNCA rotar sin migrar secretos) |
| `OMNI_MASTER_EMAIL` | `wp-config.php` | Email destino notificaciones internas |
| `OMNI_N8N_SECRET` / `AT_WEBHOOK_SECRET` | `wp-config.php` + N8N env | HMAC webhooks N8N↔AT |
| `SMTP_*` | `wp-config.php` | Envío email |
| `OPENAI_DEFAULT_MODEL` | N8N env | `gpt-4o-mini` por defecto |
| `AT_BASE_URL` | N8N env | `https://automatizatech.cl` |
| `REDIS_TTL_BOT_CONTEXT` | N8N env | `300` segundos |

> **Nunca commitear `wp-config.php` ni `wp-config-secrets.php`.**

---

## 📨 Códigos de respuesta API

| Code | Significado |
|------|-------------|
| `OK` | Éxito |
| `ERR_AUTH` | Falta/incorrecto token |
| `ERR_PERM` | Token válido pero sin permisos |
| `ERR_NOT_FOUND` | Recurso no existe |
| `ERR_VALIDATION` | Body inválido |
| `ERR_RATE_LIMIT` | 429 — demasiadas requests |
| `ERR_HMAC` | Firma webhook inválida |
| `ERR_INTERNAL` | Error interno (con `error_id` correlacionable) |

---

## 📌 Estados (status enums)

### Conversaciones
`open`, `pending`, `closed`, `archived`

### Mensajes (status de envío)
`sent`, `delivered`, `read`, `failed`

### Mensajes (sender_type)
`contact`, `bot`, `agent`, `system`

### Leads
`nuevo`, `contactado`, `cualificado`, `cliente`, `descartado`

### Citas
`pending`, `confirmed`, `cancelled`, `no_show`, `completed`

### Propuestas
`draft`, `sent`, `viewed`, `accepted`, `rejected`, `expired`

### Contratos
`draft → at_pending → at_signed → sent → viewed → signed`

### N8N errors (severity)
`info`, `warn`, `error`, `critical`

---

## 🌐 Idiomas y localización

- **Idioma principal:** Español (Chile).
- **Moneda:** CLP (default), soporte USD para clientes internacionales.
- **Identificadores:** RUT chileno con dígito verificador.
- **Timezone:** `America/Santiago`.
- **Formato fecha:** `dd/MM/YYYY` UI; `Y-m-d H:i:s` BD.

---

## ✅ Checklist para colaborador nuevo / IA

- [ ] Leí `00_INDEX.md` y `01_ARQUITECTURA_GENERAL.md`.
- [ ] Identifiqué qué subsistema voy a tocar.
- [ ] Conozco las convenciones de naming y los helpers `at-*`.
- [ ] Sé que no debo commitear secretos (`gitleaks` lo bloquea).
- [ ] Sé que cualquier endpoint nuevo requiere auth + escape + sanitización.
- [ ] Si toco BD: voy a actualizar `03_BASE_DE_DATOS.md`.
- [ ] Si toco endpoint: voy a actualizar `04_THEME_WORDPRESS.md` o `05_API_BACKEND_PHP.md`.
- [ ] Si toco workflow N8N: voy a actualizar fila en `07_N8N_WORKFLOWS.md`.
- [ ] Voy a probar localmente (WAMP) antes de pushear.
