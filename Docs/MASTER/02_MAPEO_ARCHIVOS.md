# 02 — Mapeo de Archivos y Carpetas

> Inventario denso de archivos clave del repositorio. Para cada archivo se indica su **propósito**, **invocadores** y **dependencias**. Archivos `.bak`, `.old`, `_v2`, `_v3` se listan al final como candidatos a limpieza.

---

## 📂 Raíz del repositorio (`/`)

### Controladores principales (PHP)

| Archivo | Tamaño | Propósito | Invocadores |
|---------|--------|-----------|-------------|
| `omnichannel-controller.php` | ~222 KB | Orquestador central: lógica de conversaciones, takeovers, asignación de agentes, bot management, vault de credenciales | `api-omnichannel.php`, AJAX backoffice WP |
| `api-omnichannel.php` | ~87 KB | Endpoint REST principal del Portal OmniCliente. Auth triple (`X-API-Key`/`X-Admin-Token`/`X-Agent-Token`) | Portal SPA, N8N workflows |
| `webhook-omnichannel.php` | ~30 KB | Receptor de webhooks WhatsApp/Instagram/Telegram/Messenger. Verifica HMAC y persiste mensajes | Meta Cloud, YCloud, Telegram, IG |
| `omnichannel-bot.php` | ~15 KB | Lógica de respuesta automática (bot) sobre conversaciones | `omnichannel-controller.php` |
| `omnichannel-config.php` | — | Constantes globales (`OMNI_ADMIN_SECRET`, `OMNI_MASTER_EMAIL`, etc.) | Toda la capa omnichannel |

### Helpers de seguridad (`at-*.php`) — 9 archivos

| Archivo | Función | Detalle en |
|---------|---------|------------|
| `at-ajax-guard.php` | Nonce + capability + token guards para AJAX | `10_SEGURIDAD_HARDENING.md` |
| `at-cors.php` | CORS whitelist (no wildcard) | `10_SEGURIDAD_HARDENING.md` |
| `at-escape.php` | `at_e`, `at_attr`, `at_url`, `at_json`, `at_kses` | `10_SEGURIDAD_HARDENING.md` |
| `at-maintenance-guard.php` | Bloquea acceso HTTP a scripts debug/setup | `10_SEGURIDAD_HARDENING.md` |
| `at-ownership.php` | Validación IDOR (`at_owns_resource`) | `10_SEGURIDAD_HARDENING.md` |
| `at-path-safe.php` | Path traversal guard (`at_path_inside`) | `10_SEGURIDAD_HARDENING.md` |
| `at-rate-limit.php` | Rate limit por IP (transients) | `10_SEGURIDAD_HARDENING.md` |
| `at-uploads-validate.php` | MIME + magic bytes + size | `10_SEGURIDAD_HARDENING.md` |
| `at-webhook-verify.php` | HMAC-SHA256 + timestamp tolerance | `10_SEGURIDAD_HARDENING.md` |

### Scripts setup / migración (28+)

Patrón: `setup-*.php`, `migrate-*.php`, `add-*.php`, `update-*.php`, `fix-*.php`, `reset-*.php`, `revert-*.php`, `mark-*.php`. **Bloqueados por `.htaccess`** (acceso HTTP); requieren CLI o `manage_options`. Incluyen:

| Archivo | Propósito |
|---------|-----------|
| `setup-omnichannel.php` | Bootstrap inicial de tablas omnichannel |
| `setup-omnichannel-v2.php` | Migración: columnas YCloud |
| `setup-omnichannel-v3.php` | Migración: agent login (campos en agents) |
| `setup-credentials-vault.php` | Crea `wp_omnichannel_vault_secrets` + master key |
| `setup-bot-templates.php` | Inserta plantillas seed de bot |
| `setup-prompts-config.php` | Tabla `wp_omnichannel_prompt_configs` |
| `setup-takeovers.php` | Tabla `wp_omnichannel_takeovers` |
| `setup-n8n-workflows-table.php` | Catálogo `wp_omnichannel_n8n_workflows` |
| `setup-ai-usage-log.php` | Tabla `ai_usage_log` |
| `migrate-clients-add-portal.php` | Añade `api_key`/`portal_enabled` a `wp_omnichannel_clients` |
| (otros) | Ver listado completo con `Get-ChildItem setup-*.php` |

### Scripts de diagnóstico (24+)

Patrón: `debug-*.php`, `check-*.php`, `test-*.php`, `purge-*.php`, `flush-*.php`. Ej: `debug-omnichannel.php`, `check-production-readiness.php`, `test-invoice-download.php`, `purge-orphan-messages.php`.

### Configuración / hardening

| Archivo | Propósito |
|---------|-----------|
| `.htaccess` | Hardening Phase 1 + WP rewrites + LiteSpeed |
| `.htaccess-production` | Variante prod (más permisiva en checks, GZIP, ExpiresByType) |
| `.github/workflows/security-scan.yml` | gitleaks + php-lint + pattern checks |
| `.github/dependabot.yml` | Updates Actions/Composer/NPM semanales |

---

## 📂 `wp-content/themes/automatiza-tech/`

| Archivo | Propósito |
|---------|-----------|
| `functions.php` | Bootstrap del theme (~100 KB). Carga todos los módulos `inc/`, registra hooks, shortcodes, scripts |
| `style.css` | Header del theme + estilos base |
| `index.php`, `single.php`, `page.php`, etc. | Templates WordPress estándar |
| `header.php`, `footer.php` | Layout |

### Subcarpeta `inc/` (módulos)

| Archivo | Propósito |
|---------|-----------|
| `api-endpoints.php` (~2090 líneas) | **25+ endpoints REST** en `/wp-json/automatiza-tech/v1/` |
| `ajax-handlers.php` | 45+ handlers AJAX (form contacto, agendamiento, etc.) |
| `crm-functions.php` | Lógica CRM: leads, citas, propuestas |
| `dashboard-stats.php` | Métricas para backoffice |
| `email-templates.php` | Plantillas HTML de correos |
| `meta-boxes.php` | Custom fields admin WP |
| `shortcodes.php` | 3 shortcodes (formularios, dashboard, listado) |
| `theme-setup.php` | `add_theme_support`, menús, sidebars |
| `enqueue-assets.php` | Carga JS/CSS |
| (otros) | Ver `Docs/ANALISIS_MODULOS_INC.md` original |

Detalle completo de endpoints/handlers en `04_THEME_WORDPRESS.md`.

---

## 📂 `client-portal-omnichannel/` (SPA fuente)

```
client-portal-omnichannel/
├── package.json              # React 19, Vite 6, Tailwind 3
├── vite.config.js
├── index.html
└── src/
    ├── main.jsx              # Entry point
    ├── App.jsx               # Router + Layout
    ├── api.js                # Cliente HTTP (370 líneas, 60+ endpoints)
    ├── components/           # 25+ componentes (ver 06_PORTAL...)
    │   ├── InboxView.jsx     # Polling 5s
    │   ├── AgentLogin.jsx
    │   ├── AdminDashboard.jsx
    │   ├── ConversationDetail.jsx
    │   ├── ChatBubble.jsx
    │   ├── ... (21 más)
    └── hooks/, utils/, styles/
```

Detalle completo en `06_PORTAL_OMNICLIENTE_FRONTEND.md`.

---

## 📂 `omnicliente/`

Build deployado del portal (output de `npm run build` desde `client-portal-omnichannel/`). **No editar manualmente**; regenerar.

---

## 📂 `contracts/`

| Archivo | Propósito |
|---------|-----------|
| `setup-contracts-db.php` | Crea tabla `wp_automatiza_contracts` (clave `AT_SETUP_2026`) |
| `contract-service.php` | Clase central `ContractService` (CRUD + flujo firmas) |
| `contract-mailer.php` | Clase `ContractMailer` (4 tipos de correo) |
| `create-contract.php` | API endpoint POST (admin only) |
| `at-sign-contract.php` | UI firma interna (login WP + nonce) |
| `sign-contract.php` | UI firma cliente (público con token 64-hex) |
| `admin-contracts.php` | Backoffice WP (`Contactos > Contratos`) |
| `client-contracts-widget.php` | Widget en ficha cliente |
| `templates/` | Plantillas Markdown de contrato |

Detalle en `09_MODULO_CONTRATOS.md`.

---

## 📂 `N8N/`

```
N8N/
├── PROD/                                 # 38 workflows productivos
│   ├── WhatsApp_Bot_v8_Portal_OmniCliente.json
│   ├── (37 más)
├── TEMPLATES/
│   ├── kellscapilar/                     # 8 plantillas (cliente piloto)
│   └── (6 genéricos)
└── (11 workflows en raíz, históricos / sandbox)
```

Detalle en `07_N8N_WORKFLOWS.md`.

---

## 📂 `sql/`

| Archivo | Propósito |
|---------|-----------|
| `schema_omnichannel.sql` | DDL inicial omnichannel |
| `schema_clients.sql` | DDL clientes/leads |
| `schema_proposals.sql` | DDL propuestas |
| `migrations/` | Migraciones incrementales numeradas |

> Nota: Las migraciones reales se ejecutan vía scripts `setup-*.php` / `migrate-*.php` en raíz, no por SQL plano.

Schema completo en `03_BASE_DE_DATOS.md`.

---

## 📂 `Docs/`

| Archivo / carpeta | Estado | Acción recomendada |
|---|---|---|
| `MASTER/` | ✅ **Vigente (esta documentación)** | Fuente única de verdad |
| `AUTOMATIZATECH_TECNICO.md` | ⚠️ Parcialmente vigente (Mar 2026) | Marcar como histórico |
| `DOCUMENTO_TECNICO_AUTOMATIZATECH.md` | ⚠️ Parcial (Feb 2026) | Marcar como histórico |
| `INTEGRACION_N8N_PORTAL_OMNICLIENTE.md` | ✅ Vigente | Mantener; referenciado desde `07_N8N_WORKFLOWS.md` |
| `ANALISIS_MODULOS_INC.md` | ✅ Vigente | Mantener; referencia detalle inc/ |
| `CONTRATO_SOPORTE_POSTPROYECTO.md` | ✅ Plantilla activa de contrato | Usado por `contracts/create-contract.php` |
| Otros (`CONTEXTO_COMPLETO.md`, `MANUAL_PROGRAMADOR.md`, `MANUAL_CONTEXTO_IA.md`, etc.) | ⚠️ Mezclados | Ver `99_AUDIT_REPORT.md` |

---

## 📂 `tools/`

Scripts utilitarios CLI (no expuestos por HTTP). Ej: `tools/regen-portal-build.ps1`, `tools/sync-prod-db.ps1`.

---

## 🧹 Archivos candidatos a limpieza

Patrones a auditar y eliminar (con respaldo previo):
- `*.bak`, `*.old`, `*-backup.*`
- `setup-*-v2.php`, `setup-*-v3.php` (si la migración ya está aplicada en prod)
- Carpetas `tema-backup/`, `RespaldoDocs/`, `RespaldoTest/`, `archivos-eliminados-backup/`, `archive/` (ya bloqueadas en `.htaccess`)
- Workflows N8N en raíz de `N8N/` (consolidar en `PROD/` o `TEMPLATES/`)
- Versiones antiguas del bot WhatsApp v1–v7 (mantener solo v8 + 1 backup previo)

> **Importante:** Antes de eliminar, comprobar referencias con `grep` y validar en staging.
