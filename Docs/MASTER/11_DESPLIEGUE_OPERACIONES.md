# 11 — Despliegue y Operaciones

> **Hosting prod:** Hostinger (Apache + LiteSpeed Cache + MySQL).
> **Dominio:** `automatizatech.cl`.
> **Repo:** `lmgmuber-bit/automatiza-tech` (GitHub).

---

## 🌍 Ambientes

| Ambiente | URL | Stack | Branch |
|----------|-----|-------|--------|
| **Producción** | https://automatizatech.cl | Hostinger / Apache+LSCache / MySQL | (histórico: `prod-sync-2025-06-26`) |
| **Desarrollo local** | http://localhost/automatiza-tech | WAMP64 | `security/hardening-phase-0` (actual) |
| **N8N** | https://n8n-n8n.kchiba.easypanel.host | Easypanel self-hosted | — |

---

## 🚀 Estrategia de despliegue (actual)

> ⚠️ **No hay pipeline CI/CD de deploy automatizado.** El despliegue es **manual vía SFTP + Git pull en host**.

### Pasos típicos

1. **Local:** verificar `git status`, correr lint PHP, build del Portal (`npm run build`).
2. **Push** a la branch correspondiente (idealmente `main` para prod).
3. **Hostinger SSH/SFTP:**
   ```bash
   cd ~/public_html
   git fetch && git checkout main && git pull
   ```
4. **Migraciones BD:** ejecutar manualmente vía CLI:
   ```bash
   wp eval-file setup-omnichannel-v3.php   # (si aplica)
   ```
   o vía URL con `manage_options` logueado: `https://automatizatech.cl/setup-XYZ.php`.
5. **Cache:** purgar LiteSpeed (`wp litespeed-purge all` o desde admin).
6. **Verificar:** `https://automatizatech.cl/wp-json/automatiza-tech/v1/health`.

### Despliegue del Portal SPA

```powershell
cd client-portal-omnichannel
npm install
npm run build
Copy-Item -Path .\dist\* -Destination ..\omnicliente\ -Recurse -Force
git add ..\omnicliente
git commit -m "build: portal vX.Y.Z"
git push
```

> Existe `tools/regen-portal-build.ps1` para automatizar.

---

## 🗓️ Cron jobs

| Cron | Origen | Frecuencia | Propósito |
|------|--------|------------|-----------|
| `wp_cron` | WordPress | Por requests | Tareas WP (transients, posts programados) |
| `Daily_Stats_Digest` | N8N | Diario 8 AM | Resumen al equipo |
| `Appointment_Reminder_24h` | N8N | Diario | Recordatorios 24h |
| `Appointment_Reminder_1h` | N8N | Horario | Recordatorios 1h |
| `UF_Refresh` | N8N | Diario | Refresca indicadores |
| `OpenAI_Cost_Alert` | N8N | Diario | Alerta costos |
| `Backup_Workflows_Nightly` | N8N | 3 AM | Exporta workflows |
| `Inbox_Stale_Conversations` | N8N | Diario | Marca conversaciones stale |
| `Vault_Health_Check` | N8N | Horario | Health check vault |

> Recomendación: deshabilitar `wp_cron` por requests y configurar **system cron** en Hostinger (`*/15 * * * * curl https://automatizatech.cl/wp-cron.php?doing_wp_cron`).

---

## 💾 Backups

| Tipo | Origen | Frecuencia | Retention |
|------|--------|------------|-----------|
| BD | Hostinger (auto) | Diario | 7 días |
| Archivos | Hostinger (auto) | Semanal | 4 semanas |
| BD manual | `mysqldump` desde admin/SSH | A demanda | — |
| Workflows N8N | Workflow `Backup_Workflows_Nightly` | Diario | (configurar destino) |

### Restauración manual de BD a local

```powershell
# En prod (SSH Hostinger):
mysqldump -u u402745362_automatiza -p u402745362_automatizatech > backup.sql
# Descargar via SFTP a local
# En WAMP local:
mysql -u root -e "DROP DATABASE IF EXISTS automatiza_tech_local; CREATE DATABASE automatiza_tech_local;"
mysql -u root automatiza_tech_local < backup.sql
# Actualizar wp-config.php local con BD nueva
# Search-replace de URL prod→local:
wp search-replace 'https://automatizatech.cl' 'http://localhost/automatiza-tech'
```

---

## 🔧 Variables de entorno

### `wp-config.php` (NO commitear)

```php
// BD
define('DB_NAME', 'u402745362_automatizatech');
define('DB_USER', 'u402745362_automatiza');
define('DB_PASSWORD', '...');
define('DB_HOST', 'localhost');

// Omnichannel
define('OMNI_ADMIN_SECRET', '...');           // Token admin Portal + N8N
define('OMNI_VAULT_PASSPHRASE', '...');       // Master key vault
define('OMNI_MASTER_EMAIL', 'contacto@automatizatech.cl');
define('OMNI_N8N_SECRET', '...');             // HMAC para webhooks N8N

// SMTP
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'contacto@automatizatech.cl');
define('SMTP_PASS', '...');

// WordPress
define('WP_DEBUG', false);                    // true SOLO en local
define('WP_DEBUG_LOG', false);
define('DISALLOW_FILE_EDIT', true);           // Hardening
define('FORCE_SSL_ADMIN', true);
```

### `wp-config-secrets.php` (opcional, gitignored)

Si separas secretos del wp-config principal, el `.htaccess` ya bloquea su acceso HTTP.

### N8N (Easypanel)

| Variable | Valor |
|----------|-------|
| `OMNI_ADMIN_SECRET` | (mismo que prod AT) |
| `AT_BASE_URL` | `https://automatizatech.cl` |
| `AT_WEBHOOK_SECRET` | (HMAC AT) |
| `OPENAI_DEFAULT_MODEL` | `gpt-4o-mini` |

---

## 📊 Monitoreo

| Métrica | Herramienta | Acción |
|---------|-------------|--------|
| Uptime sitio | Hostinger panel + UptimeRobot externo | Alerta email |
| Errores PHP | `debug.log` (solo local) + log Apache prod | Revisar semanal |
| Errores N8N | `wp_omnichannel_n8n_errors` | Workflow `N8N_Error_To_AT` |
| Costos OpenAI | `ai_usage_log` | `OpenAI_Cost_Alert` daily |
| Backups BD | Hostinger panel | Verificar mensual |
| Latencia bot | Métricas N8N + Redis hit rate | Revisar mensual |
| Conversaciones stale | Workflow + dashboard portal | Diario |

---

## 🔧 Operaciones comunes

### Limpiar cache

```bash
wp litespeed-purge all
wp cache flush
```

### Regenerar build Portal

```powershell
.\tools\regen-portal-build.ps1
```

### Rotar token admin

1. Generar nuevo: `php -r "echo bin2hex(random_bytes(32));"`
2. Actualizar `wp-config.php` `OMNI_ADMIN_SECRET`.
3. Actualizar variable en N8N (Easypanel UI).
4. Reiniciar workers N8N si aplica.

### Crear nuevo cliente del SaaS

1. Insert en `wp_omnichannel_clients` (vía admin Portal o CLI).
2. Generar `api_key` (64-hex random).
3. Configurar canales en `wp_omnichannel_channels`.
4. Cargar secretos en Vault.
5. Asignar template de bot en `wp_omnichannel_bot_configs`.
6. Compartir URL: `https://automatizatech.cl/omnicliente/?key=<api_key>`.

---

## 🚨 Incident response (resumen)

| Incidente | Acción inmediata |
|-----------|------------------|
| Sitio caído | Verificar Hostinger panel; revertir último deploy si reciente |
| Bot no responde | Verificar N8N workflow + Redis + Portal API health |
| Webhook proveedor falla | Revisar `wp_omnichannel_n8n_errors` + logs Apache |
| Sospecha de leak | Rotar `OMNI_ADMIN_SECRET`, `OMNI_VAULT_PASSPHRASE`, todos los secretos del Vault |
| Spam masivo en form | Revisar rate-limits, considerar Cloudflare WAF |

---

## ⚠️ Pendientes operacionales

1. **CI/CD de deploy** automatizado (GitHub Actions → Hostinger via SFTP/SSH).
2. **Sistema de versionado de migraciones** (tabla `db_version`).
3. **Logs centralizados** (Loki + Grafana o ELK).
4. **Monitoring APM** (Sentry o New Relic).
5. **Staging environment** separado de prod.
