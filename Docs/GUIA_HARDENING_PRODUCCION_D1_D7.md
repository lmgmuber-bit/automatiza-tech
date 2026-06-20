# Guía de Hardening en Producción — D1 a D7

**Rama:** `security/hardening-phase-0`  
**Última actualización:** 2026-05-13  
**Aplica a:** Hostinger (automatizatech.cl)

> ⚠️ **ANTES DE EMPEZAR — obligatorio**
> 1. Realiza un backup completo en Hostinger (cPanel → Backup Wizard o JetBackup).
> 2. Confirma que el sitio local (WAMP) funciona correctamente.
> 3. Ten acceso disponible a: Hostinger File Manager, cPanel, OpenAI Console y tu proveedor SMTP.
> 4. Trabaja en una ventana de baja actividad (ej. de madrugada o fin de semana).

---

## Orden recomendado de ejecución

| # | Tarea | Riesgo | Prerrequisito |
|---|-------|--------|---------------|
| **D4** | Aplicar AT_HARDENING en `.htaccess` Hostinger | 🟢 Bajo | Backup hecho |
| **D2** | Generar SALTs WordPress nuevos | 🟢 Bajo | — |
| **D1** | Rotar todos los secretos en producción | 🟡 Medio | D2 listo |
| **D3** | Instalar 2FA para administradores | 🟢 Bajo | D1 aplicado |
| **D7** | Eliminar carpetas de backup del servidor | 🟢 Bajo | D4 aplicado |
| **D5** | Limpiar historial Git (reescritura) | 🔴 Alto | D1 completo — secretos viejos ya inválidos |
| **D6** | Re-escaneo externo de validación | 🟢 Bajo | D1–D5 completos |

---

## D4 — Aplicar AT_HARDENING en `.htaccess` Hostinger

**Objetivo:** Bloquear scripts de mantenimiento, xmlrpc.php, backups y agregar headers de seguridad.

### Pasos

1. Abre **Hostinger → File Manager → `public_html/`**
2. Clic derecho en `.htaccess` → **Edit**
3. Pega el siguiente bloque **al inicio del archivo**, antes de cualquier otro bloque existente:

```apache
# BEGIN AT_HARDENING — Bloqueo de superficie sensible (Phase 1)
<IfModule mod_rewrite.c>
RewriteEngine On

# 1) Bloquear xmlrpc.php
RewriteRule ^xmlrpc\.php$ - [F,L]

# 2) Bloquear scripts de mantenimiento / debug en raíz
RewriteRule ^(debug|check|setup|fix|test|add|update|reset|revert|mark|purge|flush)-.*\.php$ - [F,L]
RewriteRule ^check_.*\.php$ - [F,L]
RewriteRule ^_gen_token\.php$ - [F,L]
RewriteRule ^get-migration-token\.php$ - [F,L]
RewriteRule ^qa-report-generator\.php$ - [F,L]
RewriteRule ^reporte-consumo-ai\.php$ - [F,L]

# 3) Bloquear logs accesibles vía web
RewriteRule \.log$ - [F,L]
RewriteRule (^|/)debug\.log$ - [F,L]

# 4) Bloquear backups y archivos sensibles
RewriteRule \.(bak|backup|old|orig|sql|sql\.gz|env)$ - [F,L]
RewriteRule \.php(2|3|_old)$ - [F,L]

# 4b) Bloquear carpetas de backup/respaldo a nivel URL
RewriteRule ^(tema-backup|RespaldoDocs|RespaldoTest|archivos-eliminados-backup|archive)/ - [F,L]
RewriteRule ^\.claude/ - [F,L]

# 5) Bloquear acceso directo a wp-config y similares
<FilesMatch "^(wp-config(-.*)?\.php|wp-config-secrets\.php|\.htaccess|\.htpasswd|debug\.log|debug2\.log)$">
    Require all denied
</FilesMatch>
</IfModule>

# 6) Headers de seguridad
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    # Descomentar cuando 100% del sitio sea HTTPS estable:
    # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# 7) Negar ejecución de PHP en uploads (defense-in-depth)
<Directory "wp-content/uploads">
    <FilesMatch "\.(php|phtml|phar|php3|php4|php5|php7|php8|pl|py|jsp|asp|sh|cgi)$">
        Require all denied
    </FilesMatch>
</Directory>
# END AT_HARDENING
```

4. **Guarda** y abre `https://automatizatech.cl` — debe cargar normalmente.
5. **Verifica el bloqueo:**
   - `https://automatizatech.cl/xmlrpc.php` → debe devolver **403**
   - `https://automatizatech.cl/debug-n8n-flow.php` → debe devolver **403**
   - `https://automatizatech.cl/wp-admin` → debe funcionar con normalidad

> 💡 **Rollback:** Si el sitio devuelve error 500, edita `.htaccess` y borra el bloque entre
> `# BEGIN AT_HARDENING` y `# END AT_HARDENING`. El resto del archivo queda intacto.

---

## D2 — Generar nuevos SALTs WordPress

**Objetivo:** Reemplazar las claves criptográficas de sesión de WordPress.

### Pasos

1. Abre en el navegador:  
   **`https://api.wordpress.org/secret-key/1.1/salt/`**
2. El sitio devuelve 8 líneas `define(...)`. Cópialas completas.
3. Guárdalas temporalmente en un bloc de notas — las usarás en D1.

> Las líneas tienen este aspecto:
> ```php
> define('AUTH_KEY',         'Xz9...(64 chars)...');
> define('SECURE_AUTH_KEY',  'pQ3...');
> // ...8 líneas en total
> ```

---

## D1 — Rotar todos los secretos en producción

**Objetivo:** Invalidar cualquier credencial que haya podido quedar expuesta en el historial del repositorio.

### Paso 1 — Generar nuevos OMNI secrets (PowerShell local)

```powershell
# Ejecuta en PowerShell en tu máquina:
$cron  = [System.BitConverter]::ToString([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)) -replace '-',''
$admin = [System.BitConverter]::ToString([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)) -replace '-',''
Write-Host "OMNICHANNEL_CRON_SECRET=$cron"
Write-Host "OMNI_ADMIN_SECRET=$admin"
```

Copia los dos valores generados.

### Paso 2 — Rotar OPENAI_API_KEY

1. Ve a [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Haz clic en **"Create new secret key"** → copia el valor `sk-proj-...`
3. ⚠️ **No elimines la clave vieja todavía** — primero despliega la nueva y verifica.

### Paso 3 — Actualizar `wp-config-secrets.php` en Hostinger

1. Hostinger → File Manager → `public_html/wp-config-secrets.php`
2. Edita el archivo con **todos los valores nuevos**:

```php
<?php
/**
 * AT Secrets — wp-config-secrets.php
 * GITIGNORED. Solo existe en cada servidor.
 * Permisos: 600
 */

// WordPress SALTs (generados en D2 — api.wordpress.org/secret-key/1.1/salt/)
define('AT_AUTH_KEY',          'PEGA_SALT_1_AQUI');
define('AT_SECURE_AUTH_KEY',   'PEGA_SALT_2_AQUI');
define('AT_LOGGED_IN_KEY',     'PEGA_SALT_3_AQUI');
define('AT_NONCE_KEY',         'PEGA_SALT_4_AQUI');
define('AT_AUTH_SALT',         'PEGA_SALT_5_AQUI');
define('AT_SECURE_AUTH_SALT',  'PEGA_SALT_6_AQUI');
define('AT_LOGGED_IN_SALT',    'PEGA_SALT_7_AQUI');
define('AT_NONCE_SALT',        'PEGA_SALT_8_AQUI');

// OpenAI
define('OPENAI_API_KEY', 'sk-proj-NUEVA_KEY_AQUI');

// Base de datos (solo si cambias el password en Hostinger → Databases)
define('AT_DB_PASSWORD', 'PASSWORD_DB_ACTUAL_O_NUEVO');

// SMTP
define('AT_SMTP_PASS', 'PASSWORD_SMTP_ACTUAL_O_NUEVO');

// Omnichannel (generados en Paso 1 de D1)
define('OMNICHANNEL_CRON_SECRET', 'NUEVO_CRON_SECRET_64HEX');
define('OMNI_ADMIN_SECRET',       'NUEVO_ADMIN_SECRET_64HEX');
```

3. Guarda el archivo.
4. Clic derecho → **Change Permissions → 600** (solo el dueño puede leer).
5. Abre `https://automatizatech.cl/wp-admin` → debe cargar normalmente.
   > Los usuarios logueados serán desconectados al cambiar los SALTs. **Es el comportamiento esperado.**
6. **Verifica que el portal omnicanal funciona** (login de agente, carga de chats).
7. Una vez confirmado que todo funciona → **elimina la clave vieja en OpenAI Console**.

---

## D3 — Instalar 2FA para administradores

**Objetivo:** Proteger las cuentas de administrador con autenticación de dos factores.

### Pasos

1. WordPress Admin → **Plugins → Añadir nuevo**
2. Busca: `Wordfence Login Security`
3. **Instalar ahora → Activar**
4. Menú lateral → **Login Security → Settings**
5. Activa:
   - ☑ `Require 2FA for: administrators`
   - ☑ `Require 2FA for: at_admin` (si aparece el rol personalizado)
6. Ve a **tu perfil de usuario** (admin) → sección **Two-Factor Authentication**
7. Escanea el código QR con **Google Authenticator**, **Authy** o similar
8. Ingresa el código de 6 dígitos para confirmar la vinculación
9. **IMPORTANTE:** Descarga y guarda los **códigos de recuperación** en un lugar seguro (no en el repositorio)

> 💡 Si en algún momento pierdes el acceso, puedes desactivar el plugin vía FTP/File Manager renombrando la carpeta `wp-content/plugins/wordfence-login-security` temporalmente.

---

## D7 — Eliminar carpetas de backup del servidor

**Objetivo:** Remover físicamente los archivos de respaldo que no deben estar en el webroot.

> El `.htaccess` (D4) ya bloquea el acceso URL a estas carpetas, pero eliminarlas es la solución definitiva.

### Carpetas a revisar en `public_html/`

```
tema-backup/
RespaldoDocs/
RespaldoTest/
archivos-eliminados-backup/
archive/
```

### Pasos

1. Hostinger → File Manager → `public_html/`
2. Para cada carpeta de la lista: **verifica su contenido**
   - ¿Tiene archivos que necesitas? → Descárgalos primero a tu máquina local
   - ¿Son respaldos antiguos que ya tienes en otro lugar? → Eliminar directamente
3. Clic derecho en la carpeta → **Delete**
4. Confirma la eliminación

---

## D5 — Limpiar historial Git (reescritura de commits)

> 🔴 **RIESGO ALTO — Lee todo antes de ejecutar.**  
> Esta operación reescribe el historial del repositorio. Si hay otros colaboradores con copias locales,
> deben borrar su copia y volver a clonar después del force push.

**Objetivo:** Purgar cualquier secreto (API keys, passwords) que haya quedado en commits anteriores.

### Prerrequisito — D1 debe estar completado
Los secretos viejos deben estar **ya rotados e inválidos** antes de empezar este paso.
No tiene sentido purgar un secreto que todavía está activo.

### Paso 1 — Instalar git-filter-repo

```powershell
pip install git-filter-repo
```

### Paso 2 — Crear archivo de reemplazos

Crea el archivo `C:\Users\luis_\secrets-replace.txt` con los valores viejos (los que quieres borrar):

```
# Formato: VALOR_REAL==>TEXTO_DE_REEMPLAZO
sk-proj-TU_KEY_VIEJA_OPENAI==>REDACTED_OPENAI_KEY
TU_PASSWORD_VIEJO_DB==>REDACTED_DB_PASS
TU_SMTP_PASS_VIEJO==>REDACTED_SMTP_PASS
```

> ⚠️ Pon los valores **exactos** que aparecieron en commits anteriores (los que rotaste en D1).

### Paso 3 — Trabajar en una copia limpia del repo

```powershell
# 1. Clonar una copia de trabajo limpia en el escritorio
cd C:\Users\luis_\Desktop
git clone C:\wamp64\www\automatiza-tech repo-clean
cd repo-clean

# 2. Ejecutar filter-repo
git filter-repo --replace-text C:\Users\luis_\secrets-replace.txt

# 3. Verificar que los secretos desaparecieron
git log --all --full-history -p | Select-String "sk-proj-"
# → No debe aparecer ningún resultado

# 4. Verificar también los otros secretos
git log --all --full-history -p | Select-String "REDACTED"
# → Debe aparecer "REDACTED_*" en lugar de los valores reales
```

### Paso 4 — Force push al repositorio remoto

```powershell
# Solo si el paso 3 quedó limpio:
git remote set-url origin https://github.com/lmgmuber-bit/automatiza-tech.git
git push origin --force --all
git push origin --force --tags
```

### Paso 5 — Actualizar tu copia de trabajo local

```powershell
cd C:\wamp64\www\automatiza-tech
git fetch origin
git reset --hard origin/security/hardening-phase-0
```

### Paso 6 — Limpiar el archivo de reemplazos

```powershell
# Borra el archivo con los secretos viejos — ya no lo necesitas
Remove-Item C:\Users\luis_\secrets-replace.txt
```

---

## D6 — Re-escaneo externo de validación

**Objetivo:** Confirmar con herramientas externas que las mitigaciones están activas.

> Ejecutar después de completar D1–D5.

### Opción A — Tu propio pentest script

```powershell
cd C:\wamp64\www\automatiza-tech
.\tools\security\pentest.ps1 -BaseUrl "https://automatizatech.cl"
```

### Opción B — WPScan (requiere cuenta gratis en wpscan.com)

```powershell
# Con Docker:
docker run --rm wpscanteam/wpscan `
  --url https://automatizatech.cl `
  --api-token TU_TOKEN_WPSCAN `
  --enumerate vp,vt,u

# Alternativa: wpscan.com/wordpress-security-scanner (online, gratis)
```

### Opción C — Nikto

```powershell
# Instalar con Chocolatey:
choco install nikto

# Ejecutar:
nikto -h https://automatizatech.cl -output C:\Users\luis_\nikto-report.txt
```

### Checklist de resultados esperados post D1–D7

```
✅ https://automatizatech.cl/xmlrpc.php           → 403 Forbidden
✅ https://automatizatech.cl/debug-n8n-flow.php   → 403 Forbidden
✅ https://automatizatech.cl/check-prefix.php     → 403 Forbidden
✅ https://automatizatech.cl/wp-config.php        → 403 Forbidden
✅ Header X-Content-Type-Options: nosniff          → presente
✅ Header X-Frame-Options: SAMEORIGIN              → presente
✅ Header Referrer-Policy                          → presente
✅ git log | grep "sk-proj-"                       → sin resultados
✅ Login wp-admin requiere 2FA                     → activo
✅ wp-config-secrets.php permisos                  → 600
```

---

## Notas finales

- Este documento es la **fuente de verdad** para el proceso de hardening manual.
- Los archivos `.htaccess` y `wp-config-secrets.php` están en `.gitignore` — los cambios son solo en Hostinger.
- Si necesitas ejecutar algún script de setup bloqueado por D4, renómbralo temporalmente
  a algo que no matchee los patrones (ej. `run-migration.php`) o ejecútalo con WP-CLI:
  ```bash
  wp eval-file setup-omnichannel-ai-chats.php
  ```
- Referencia técnica completa del hardening: `Docs/MASTER/10_SEGURIDAD_HARDENING.md`
- Referencia del bloque `.htaccess`: `Docs/HARDENING_HTACCESS_DEPLOY.md`
