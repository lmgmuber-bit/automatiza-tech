# 🛡️ Reporte Final de Hardening — Automatiza Tech

**Rama:** `security/hardening-phase-0`  
**Estado:** Listo para merge a `develop` tras tareas manuales (rotación de secretos, SALTs, 2FA).

---

## 1. Resultado de la Suite de Pentest Automatizada

`tools/security/pentest.ps1` ejecutado contra `http://localhost/automatiza-tech`.

```
=== Resumen ===
  PASS: 25
  FAIL: 0
```

**Cobertura (25 controles):**

| Categoría | Controles | PASS |
|---|---|---|
| Acceso público bloqueado (.htaccess + guards) | 9 | 9 |
| Autenticación / autorización | 4 | 4 |
| Endpoints públicos responden correctamente | 6 | 6 |
| CORS no refleja orígenes maliciosos | 1 | 1 |
| Headers de seguridad | 5 | 5 |

---

## 2. Cambios Aplicados (por fase)

### F0 — Contención (en código)
- Guards de mantenimiento aplicados a 85 scripts (`debug-*`, `check-*`, `setup-*`, `fix-*`, `test-*`, `add-*`, `update-*`, `reset-*`, `revert-*`, `mark-*`, `purge-*`, `flush-*`, `_gen_token`, `get-migration-token`).
- Bloque `AT_HARDENING` en `.htaccess` (deny por patrón + carpetas backup).
- Plantilla `wp-config-secrets.example.php`.

### F1 — Cierre de superficie pública
- `wp-content/uploads/.htaccess` versionado (`deny PHP exec`).
- `.gitignore` actualizado para versionar el `.htaccess` de uploads.
- Documentación operativa: `Docs/HARDENING_HTACCESS_DEPLOY.md`.

### F2 — Hardening de configuración
- `wp-content/mu-plugins/at-security-headers.php`:
  - `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-XSS-Protection`.
  - HSTS solo en producción HTTPS.
  - CSP en modo `Report-Only` (preparado para activación gradual).
  - `header_remove('X-Powered-By')` + `expose_php=0`.
  - Cookies `Secure/HttpOnly/SameSite=Lax` en producción.
  - Bloqueo de `/wp-json/wp/v2/users` para no autenticados.
- `wp-content/mu-plugins/at-login-hardening.php`: 5 fallos / 15 min ⇒ bloqueo IP.
- `wp-config.php` refactorizado: secretos vía `getenv()` / archivo `wp-config-secrets.php` separado.

### F3 — Refactor de código
- `at-cors.php`: whitelist de orígenes (sin wildcard).
- `at-rate-limit.php`: limitador por IP/endpoint con transients.
- `at-path-safe.php`: validación realpath con prefijo permitido.
- `at-uploads-validate.php`: whitelist extensión + MIME real (`finfo_file`) + renombrado a hash.
- `at-webhook-verify.php`: HMAC-SHA256 de body+timestamp (anti-replay).
- `at-ajax-guard.php`: helpers `at_ajax_require_nonce/cap/token` con `hash_equals`.
- `at-ownership.php`: helper IDOR (admins bypass; valida owner_col == user_id).
- `at-escape.php`: wrappers `at_e/at_attr/at_url/at_json/at_kses`.
- `webhook-omnichannel.php`: HMAC + rate limit + log de fallback legacy.
- `validar-factura.php`, `validar-factura2.php`, `validar-boleta.php`: rate limit + path-safe; eliminados ~12 `error_log($_GET)`.
- `contact-form.php` (3 copias): eliminado `error_log(print_r($_POST))`.
- `mpdf-simple.php`: `shell_exec` con `escapeshellarg`.
- `crm-ai-completo.php`: 4 SELECTs migrados a `$wpdb->prepare()` (defensa en profundidad sobre intval previo).
- Auditoría XSS: 0 `echo $_GET/$_POST` directos en tema y root.

### F4 — Monitoreo y procesos
- `wp-content/mu-plugins/at-security-monitor.php`:
  - Detector de scanners (probe a `.env`, `.git`, `wp-config`, `phpmyadmin`).
  - Hook `wp_login_failed` y `ajax_nopriv_*` sin nonce.
  - Persistencia rolling 200 eventos en `wp_options`.
  - Cron horario con alertas por email si supera umbrales (20 login_failed/h, 10 probes/h, 50 ajax sin nonce/h).
  - Widget en dashboard con últimos 25 eventos.
- `.github/workflows/security-scan.yml`: 3 jobs CI (gitleaks, php -l, regresión de patrones).
- `.github/dependabot.yml`: updates semanales (github-actions, composer, npm).
- `.github/PULL_REQUEST_TEMPLATE.md`: checklist de seguridad.
- `Docs/BACKUPS_CIFRADOS.md`: script `mysqldump + tar + age + B2` + drill mensual + bitácora.

---

## 3. Pendientes Manuales (según instrucción del usuario, al final)

> Estas tareas requieren acceso a paneles externos (OpenAI, Hostinger, GitHub) y rotación de credenciales. Se documentan aquí para ejecución posterior.

1. **Rotar secretos:**
   - `OPENAI_API_KEY` (revocar en dashboard OpenAI + generar nueva).
   - `DB_PASSWORD` (Hostinger MySQL).
   - `SMTP_PASS` (cuenta SMTP saliente).
   - `OMNICHANNEL_CRON_SECRET`, `OMNI_ADMIN_SECRET`.

2. **WordPress SALTs reales:**
   - Generar desde https://api.wordpress.org/secret-key/1.1/salt/
   - Cargar en `wp-config-secrets.php` (local + Hostinger).

3. **2FA para admins:**
   - Instalar plugin (Wordfence Login Security o WP 2FA).
   - Forzar enrolment para todos los `administrator`.

4. **`.htaccess` en Hostinger:**
   - Aplicar bloque `AT_HARDENING` documentado en `Docs/HARDENING_HTACCESS_DEPLOY.md`.

5. **Limpieza de historia git:**
   - Ejecutar `git filter-repo --replace-text` para purgar `OPENAI_API_KEY` antigua y password DB legacy.
   - Forzar push y notificar al equipo (re-clonar).

6. **Mover/borrar carpetas backup del webroot:**
   - `tema-backup/`, `RespaldoDocs/`, `RespaldoTest/`, `archivos-eliminados-backup/`, `archive/` ⇒ fuera del docroot. Ya bloqueadas vía `.htaccess` como mitigación temporal.

7. **Re-escaneo externo:**
   - `wpscan`, `nikto`, `nuclei` con templates OWASP contra producción.
   - Documentar hallazgos y abrir issues.

---

## 4. Cómo verificar el blindaje

**Local:**
```powershell
.\tools\security\pentest.ps1
```

**Producción (después de aplicar manuales):**
```powershell
.\tools\security\pentest.ps1 -BaseUrl https://automatizatech.cl
```

**CI:** se ejecuta automáticamente en cada push a `main`/`develop`/`security/**` y en cada PR.

---

## 5. Métricas finales

| Métrica | Antes | Después |
|---|---|---|
| Endpoints públicos sin auth (mantenimiento) | 85+ | 0 |
| Secretos hardcodeados en `wp-config.php` | 6 | 0 (vía env) |
| CORS wildcard `*` | 3 endpoints | 0 |
| Headers de seguridad activos | 0 | 6 |
| `error_log($_POST)` filtrando PII | ~14 | 0 |
| `shell_exec` con interpolación | 1 | 0 |
| Detector de scanners | ❌ | ✅ |
| CI con escaneo de secretos | ❌ | ✅ |
| Pen-test reproducible | ❌ | ✅ (25/25) |
| Documentación de backups cifrados | ❌ | ✅ |

---

**Próximo paso:** ejecutar tareas manuales (sección 3) y abrir PR `security/hardening-phase-0 → develop`.
