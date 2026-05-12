# 10 — Seguridad y Hardening

> **Branch activa:** `security/hardening-phase-0`
> **Estrategia:** defensa en profundidad — helpers PHP + .htaccess + CI/CD + Vault.

---

## 🛡️ Helpers `at-*.php` (9 archivos en raíz)

### `at-ajax-guard.php`

| Función | Propósito |
|---------|-----------|
| `at_ajax_require_nonce($action, $field='_wpnonce')` | Verifica nonce; aborta 403 si falla |
| `at_ajax_require_cap($cap)` | Valida capability del usuario; aborta 403 |
| `at_ajax_require_token($field, $expected)` | Compara con `hash_equals()` (constant-time) |

**Uso obligatorio** en todos los handlers `wp_ajax_*`.

### `at-cors.php`

```php
$origins = at_cors_allowed_origins(); // por env: prod / dev
at_cors_apply([
  'origins' => $origins,
  'methods' => ['GET','POST','OPTIONS'],
  'headers' => ['Content-Type','X-API-Key','X-Admin-Token','X-Agent-Token'],
  'credentials' => false,
]);
```

- **Whitelist por ambiente** (NO wildcard `*`).
- Refleja `Access-Control-Allow-Origin` solo si Origin está en lista.

### `at-escape.php`

| Función | Equivalente WP |
|---------|----------------|
| `at_e($v)` | `echo esc_html($v)` |
| `at_attr($v)` | `echo esc_attr($v)` |
| `at_url($v)` | `echo esc_url($v)` |
| `at_json($v)` | `echo wp_json_encode($v)` (uso dentro de `<script>`) |
| `at_kses($v)` | `echo wp_kses_post($v)` |

### `at-maintenance-guard.php`

Incluido al principio de cada `setup-*.php`, `debug-*.php`, `check-*.php`. Lógica:

- Si **no es CLI** → exige `is_user_logged_in()` + `manage_options`.
- Si falla → `wp_die('Acceso denegado', 403)`.

> Combinado con `.htaccess` que bloquea acceso HTTP a estos patrones, hay doble barrera.

### `at-ownership.php`

| Función | Propósito |
|---------|-----------|
| `at_owns_resource($table, $id, $user_id, $col='user_id')` | Devuelve bool — el user es owner del registro |
| `at_require_ownership($table, $id, $user_id, $col='user_id')` | Aborta 403 si no es owner |

Mitiga **IDOR** en endpoints que aceptan IDs en URL/body.

### `at-path-safe.php`

| Función | Propósito |
|---------|-----------|
| `at_path_inside($candidate, $base)` | `realpath()` + `strncmp` — devuelve path absoluto seguro o `false` |
| `at_safe_basename($name)` | Whitelist `[A-Za-z0-9._-]`, longitud máx |

Protege descargas, uploads, includes dinámicos.

### `at-rate-limit.php`

| Función | Propósito |
|---------|-----------|
| `at_rate_limit_client_ip()` | Resuelve IP real (CF-Connecting-IP, X-Forwarded-For, REMOTE_ADDR) |
| `at_rate_limit_check($bucket, $max, $window_sec)` | Cuenta hits en transient; bool |
| `at_rate_limit_reject($retry_after=60)` | HTTP 429 + `Retry-After` header |

Aplicado en: form contacto, login agente, endpoints públicos del Portal.

### `at-uploads-validate.php`

| Función | Propósito |
|---------|-----------|
| `at_validate_upload($file, $allowed_exts, $max_bytes)` | Valida ext + MIME (`finfo`) + magic bytes (PDF: `%PDF-`) + size |
| `at_uploads_allowed_mimes()` | Whitelist: pdf, png, jpg, jpeg, gif, webp, svg, csv, txt, xlsx, docx |

Mitiga **upload de binarios maliciosos**, bypass por extensión.

### `at-webhook-verify.php`

| Función | Propósito |
|---------|-----------|
| `at_webhook_verify_hmac($body, $secret, $tolerance_sec=300)` | Verifica `X-AT-Signature` (HMAC-SHA256) + `X-AT-Timestamp` (anti-replay) |

Usado en endpoints `/wp-json/automatiza-tech/v1/n8n/*`.

---

## 🔒 `.htaccess` — Hardening Phase 1 (ACTIVO)

```apache
# === BLOQUEOS ===
RewriteRule ^xmlrpc\.php$ - [F,L]
RewriteRule ^(debug|check|setup|fix|test|add|update|reset|revert|mark|purge|flush)-.*\.php$ - [F,L]
RewriteRule \.log$ - [F,L]
RewriteRule \.(bak|backup|old|orig|sql|sql\.gz|env)$ - [F,L]
RewriteRule ^(tema-backup|RespaldoDocs|RespaldoTest|archivos-eliminados-backup|archive)/ - [F,L]
RewriteRule ^\.claude/ - [F,L]

# === HEADERS DE SEGURIDAD ===
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
# HSTS comentado hasta certificar 100% HTTPS:
# Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# === PROTECCIÓN DE ARCHIVOS DE CONFIG ===
<FilesMatch "^(wp-config(-.*)?\.php|wp-config-secrets\.php|\.htaccess|\.htpasswd|debug\.log)$">
    Require all denied
</FilesMatch>

# === LiteSpeed Cache (auto) ===
CacheLookup on
```

### `wp-content/uploads/.htaccess` (deny PHP exec en uploads)

```apache
<FilesMatch "\.(php|php5|phtml|cgi|pl|sh)$">
    Require all denied
</FilesMatch>
```

Versionado en repo con `.gitignore` configurado para excluirlo de ignorar. Previene ejecución de PHP subido vía upload.

### `.htaccess-production` (variante prod)

- Permite explícitamente `check-production-readiness.php`, `test-invoice-download.php`.
- Bloquea acceso a `/wp-content/uploads/` que **no** sea imagen/pdf/svg.
- GZIP + `ExpiresByType` largos para assets.

---

## 🧩 MU-Plugins WordPress (`wp-content/mu-plugins/`)

> Estos mu-plugins se cargan automáticamente en cada request WP, antes de los plugins regulares.

### `at-security-headers.php`

Headers HTTP aplicados vía WordPress (capa adicional a `.htaccess`):

| Header | Valor |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |
| `X-XSS-Protection` | `1; mode=block` |
| `Content-Security-Policy` | **Report-Only** en modo gradual → activar progresivamente |
| `Strict-Transport-Security` | Solo en PROD con HTTPS confirmado |
| `X-Powered-By` | **Eliminado** (`header_remove()`) |

**Extras:**
- `expose_php = 0` vía PHP settings
- Cookies `Secure/HttpOnly/SameSite=Lax` en producción
- Bloqueo de `/wp-json/wp/v2/users` para usuarios no autenticados (evita enumeración de usuarios)

### `at-login-hardening.php`

Protección anti-bruteforce en wp-login.php:

- **5 fallos en 15 minutos** → bloqueo de IP con transient WP
- Responde 403 + mensaje genérico (no confirma si usuario existe)
- Usa `wp_login_failed` hook nativo de WP

### `at-security-monitor.php`

Monitor de eventos de seguridad en tiempo real:

| Evento detectado | Umbral alerta/hora | Canal |
|-----------------|-------------------|-------|
| Probes a `.env`, `.git`, `wp-config`, `phpmyadmin` | 10 probes/h | Email admin |
| `wp_login_failed` | 20 fallos/h | Email admin |
| AJAX sin nonce (`ajax_nopriv_*`) | 50 peticiones/h | Email admin |

**Almacenamiento:** rolling 200 eventos en `wp_options` key `at_security_events`.  
**Cron:** hook horario; si supera umbral → email a `get_option('admin_email')`.  
**Dashboard:** widget "Eventos de Seguridad" con últimos 25 eventos.

---

## 🔐 Credentials Vault

**Tabla:** `wp_omnichannel_vault_secrets` (cifrado AES-256-CBC) + `wp_omnichannel_vault_master` (master key cifrada).

### Operaciones

| Op | Función PHP | Auth requerido |
|----|-------------|----------------|
| Lectura | `OmniVault::get($client_id, $key)` | `manage_options` o llamada interna |
| Escritura | `OmniVault::set($client_id, $key, $value)` | `manage_options` |
| Listado | `OmniVault::list($client_id)` | `manage_options` |
| Eliminar | `OmniVault::delete($client_id, $key)` | `manage_options` |

### Master key

- Generada en `setup-credentials-vault.php`.
- Almacenada cifrada con **passphrase derivada de `OMNI_VAULT_PASSPHRASE`** (env / `wp-config.php`).
- Sin la passphrase, los secretos no son recuperables.

### Secretos típicos por cliente

- `openai_api_key`
- `meta_access_token`, `meta_phone_id`, `meta_app_secret`
- `ycloud_api_key`
- `telegram_bot_token`
- `google_oauth_refresh_token`

---

## 🔁 CI/CD — `.github/workflows/security-scan.yml`

**Triggers:** push a `main`, `develop`, `security/**`; PRs a `main`/`develop`; cron lunes 9 AM.

| Job | Acción |
|-----|--------|
| `gitleaks` | Detecta secretos hardcodeados (sk-*, AKIA*, etc.) |
| `php-syntax` | Lint todos los PHP excepto `vendor`, `plugins`, `.claude` |
| `security-patterns` | Busca `sk_live_*`, `AKIA*`, CORS `*`, `error_log(print_r($_POST...))`, scripts maintenance sin `at-maintenance-guard` |

**Dependabot** activo: actualiza GitHub Actions, Composer, NPM semanalmente.

---

## ✅ Checklist de seguridad (estado actual)

| Vector | Mitigación | Estado |
|--------|-----------|--------|
| CSRF | Nonces WP + `at_ajax_require_nonce` | ✅ |
| XSS | `at_e/at_attr/at_url/at_json/at_kses` | ✅ |
| SQL Injection | `$wpdb->prepare()` consistente | ✅ |
| IDOR | `at_owns_resource` / `at_require_ownership` | ✅ |
| Path traversal | `at_path_inside`, `at_safe_basename` | ✅ |
| Upload malicioso | `at_validate_upload` + `.htaccess` | ✅ |
| Fuerza bruta | `at_rate_limit_check` | ✅ |
| CORS abuse | `at_cors_apply` whitelist | ✅ |
| Webhook spoofing | HMAC-SHA256 + timestamp | ✅ |
| Secretos en repo | `gitleaks` CI + Vault | ✅ |
| Scripts debug expuestos | `.htaccess` + `at-maintenance-guard` | ✅ |
| Headers de seguridad | `.htaccess` Header always set | ✅ |
| HSTS | Comentado | ⚠️ Activar tras 100% HTTPS |
| MFA admin | Pendiente | ⚠️ |
| WAF / DDoS | Pendiente Phase 1 | ⚠️ |

---

## 🚀 Roadmap de hardening

| Fase | Estado | Contenido |
|------|--------|-----------|
| **Phase 0** | ✅ Actual | 9 helpers, .htaccess, CI security scan, Vault, contratos |
| Phase 1 | Planificado | WAF (Cloudflare), DDoS protection, logs centralizados (Loki/ELK) |
| Phase 2 | Futuro | API gateway con rate-limit centralizado, SSL pinning, MFA backoffice |
| Phase 3 | Futuro | IDS, behavioral analysis, alertas en tiempo real |

---

## 📋 Reglas para colaboradores / IAs

1. **Nunca** uses `echo $variable` directo — usa `at_e()` u otro escape.
2. **Nunca** ejecutes `$wpdb->query()` con concatenación — usa `prepare()`.
3. **Cualquier** AJAX `priv` debe tener nonce + capability check.
4. **Cualquier** archivo nuevo `setup-*.php` / `debug-*.php` debe `require_once at-maintenance-guard.php` en la primera línea efectiva.
5. **Nunca** commitees secretos — usa el Vault o `wp-config-secrets.php` (gitignored).
6. **Nunca** uses CORS `*` — añade origen al helper `at-cors.php`.
7. **Webhooks** que reciben de N8N deben validar HMAC con `at_webhook_verify_hmac`.
8. **Uploads** siempre vía `at_validate_upload` con whitelist explícita.
