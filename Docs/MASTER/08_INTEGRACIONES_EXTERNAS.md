# 08 — Integraciones Externas

> Catálogo de SaaS y APIs externas conectadas al ecosistema. Cada uno indica: **dónde se usa**, **dónde viven las credenciales**, **endpoints clave**, **límites/quotas**.

---

## 🤖 OpenAI

| Atributo | Valor |
|----------|-------|
| **Uso** | Bot conversacional (N8N), asistente IA backoffice, embeddings (futuro) |
| **Modelos** | `gpt-4o-mini` (default), `gpt-4o` (premium) |
| **Credencial N8N** | `g52IEXpRfN5r7jKw` |
| **Credencial backend** | Vault: `openai_api_key` por cliente |
| **Endpoint** | `https://api.openai.com/v1/chat/completions` |
| **Tracking de costos** | Tabla `ai_usage_log` (cliente, modelo, tokens, USD) |
| **Alertas** | Workflow `OpenAI_Cost_Alert` (umbral diario) |
| **Rate limits** | Por organización OpenAI; backend respeta retry-after |

---

## 📱 Meta Cloud API (WhatsApp / Instagram / Messenger)

| Atributo | Valor |
|----------|-------|
| **Uso** | Recepción y envío de mensajes WhatsApp Business, IG DM, FB Messenger |
| **Credencial N8N** | `TVTLZP26kDJjR0KP` (WA Cloud) |
| **Credencial backend** | Vault: `meta_access_token`, `meta_phone_id`, `meta_app_secret` por cliente/canal |
| **Endpoints** | `https://graph.facebook.com/v20.0/{phone_id}/messages` (envío), Webhooks entrantes en `webhook-omnichannel.php` |
| **Verificación webhook** | `X-Hub-Signature-256: sha256=<hmac>` con `app_secret` |
| **Rate limits** | Tier-based (1k–10k msg/día según calidad) |
| **Templates HSM** | Aprobados en Meta Business Manager; lista en `omnichannel-controller.php` |

---

## 💬 YCloud (BSP alternativo de WhatsApp)

| Atributo | Valor |
|----------|-------|
| **Uso** | Canal alternativo WA (cuando cliente prefiere BSP en vez de Meta directo) |
| **Credencial N8N** | `cDHuTtbqeib255B5` |
| **Credencial backend** | Vault: `ycloud_api_key` |
| **Endpoint** | `https://api.ycloud.com/v2/whatsapp/messages` |
| **Verificación** | `X-YCloud-Signature` |
| **Workflow asociado** | `YCloud_Receiver` |

---

## 📅 Google Calendar (+ Meet)

| Atributo | Valor |
|----------|-------|
| **Uso** | Agendamiento de citas (bot, portal, backoffice) + generación link Meet |
| **Credencial N8N** | `NrhQQuWgel9eWwzp` (OAuth2) |
| **Credencial backend** | Vault: `google_oauth_refresh_token` por cliente |
| **Endpoint** | `https://www.googleapis.com/calendar/v3/calendars/{id}/events` |
| **Eventos generados** | `conferenceData.createRequest.conferenceSolutionKey.type = "hangoutsMeet"` |
| **Sincronización** | Workflow `Appointment_Booking` actualiza tabla `wp_automatiza_tech_appointments` |

---

## 📊 Google Sheets

| Atributo | Valor |
|----------|-------|
| **Uso** | Logs fallback bot, catálogos de productos/servicios cliente, exportes |
| **Credencial N8N** | `xWQj9WmGzqGKwQtb` |
| **Hojas activas** | Variable por cliente (registradas en config bot) |

---

## 📁 Google Drive

| Atributo | Valor |
|----------|-------|
| **Uso** | Almacenamiento PDFs propuestas/contratos (opcional, principal es uploads WP) |
| **Credencial** | OAuth2 compartida |

---

## 💬 Telegram

| Atributo | Valor |
|----------|-------|
| **Uso** | Canal Telegram para clientes que lo usen + alertas internas a equipo AT |
| **Credencial N8N** | `nFQXlS5PE97ruk0W` |
| **Credencial backend** | Vault: `telegram_bot_token` por bot |
| **Endpoint** | `https://api.telegram.org/bot{token}/sendMessage` |
| **Verificación webhook** | `X-Telegram-Bot-Api-Secret-Token` (configurado en setWebhook) |

---

## 🟥 Redis

| Atributo | Valor |
|----------|-------|
| **Uso** | Cache de contexto del bot (TTL 5 min), locks de takeover |
| **Credencial N8N** | `fgxjc2NeBOcUCA3v` |
| **Hosting** | Mismo Easypanel que N8N |
| **Keys patrón** | `bot:ctx:{client_id}:{conv_id}`, `takeover:lock:{conv_id}` |

---

## 💱 mindicador.cl

| Atributo | Valor |
|----------|-------|
| **Uso** | Valores UF, USD, EUR, IPC para propuestas y portal |
| **Endpoint** | `https://mindicador.cl/api` |
| **Auth** | Pública (sin key) |
| **Cache** | 1h en transient WP (`at_indicadores_cache`) |
| **Workflow** | `UF_Refresh` (cron diario) refresca cache |
| **Endpoint REST AT** | `GET /wp-json/automatiza-tech/v1/uf` |

---

## 📧 Email (SMTP)

| Atributo | Valor |
|----------|-------|
| **Proveedor** | SMTP Hostinger (`smtp.hostinger.com:465` SSL) |
| **From** | `contacto@automatizatech.cl` |
| **Plugin WP** | (a confirmar: WP Mail SMTP o config manual) |
| **Usado por** | Notificaciones leads, propuestas, contratos (`ContractMailer`), recordatorios |

---

## 💳 Pagos (futuro)

| Atributo | Valor |
|----------|-------|
| **Proveedor planificado** | Transbank Webpay Plus / OnePay |
| **Estado** | No implementado aún |

---

## 🗺️ Tabla resumen de credenciales

| Servicio | Vive en | Rotación |
|----------|---------|----------|
| OpenAI | Vault `openai_api_key` (por cliente) + N8N `g52IE...` | Trimestral |
| Meta WA/IG/Messenger | Vault `meta_*` | Token largo (60 días auto-refresh) |
| YCloud | Vault `ycloud_api_key` | Trimestral |
| Google (todos) | OAuth refresh tokens en vault | N/A (renovación automática) |
| Telegram | Vault `telegram_bot_token` | A demanda |
| Redis | N8N credential `fgxjc...` | Semestral |
| SMTP Hostinger | `wp-config.php` o WP Mail SMTP | A demanda |
| mindicador.cl | Sin auth | N/A |
