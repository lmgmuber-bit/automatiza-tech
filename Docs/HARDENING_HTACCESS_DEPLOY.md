# Despliegue de hardening en `.htaccess` (Hostinger)

`.htaccess` está en `.gitignore`, así que los cambios de seguridad se aplican manualmente
al servidor de producción (Hostinger). Este archivo es la fuente versionada del bloque
`AT_HARDENING` que **debe** existir en el `.htaccess` raíz del sitio.

## Cómo aplicar en producción

1. Conéctate por File Manager o SFTP a `public_html/`.
2. Edita `.htaccess` y, **al inicio del archivo** (antes del bloque LSCACHE), pega el bloque
   marcado entre `# BEGIN AT_HARDENING` y `# END AT_HARDENING`.
3. Guarda y verifica que el sitio sigue cargando.
4. Verifica con `curl -I https://automatizatech.cl/debug-n8n-flow.php` → debe responder `403`.
5. Verifica `curl -I https://automatizatech.cl/xmlrpc.php` → debe responder `403`.

## Bloque a pegar

```apache
# BEGIN AT_HARDENING — Bloqueo de superficie sensible (Phase 1)
<IfModule mod_rewrite.c>
RewriteEngine On

# 1) Bloquear xmlrpc.php
RewriteRule ^xmlrpc\.php$ - [F,L]

# 2) Bloquear scripts de mantenimiento / debug en raiz
RewriteRule ^(debug|check|setup|fix|test|add|update|reset|revert|mark|purge|flush)-.*\.php$ - [F,L]
RewriteRule ^check_.*\.php$ - [F,L]
RewriteRule ^_gen_token\.php$ - [F,L]
RewriteRule ^get-migration-token\.php$ - [F,L]
RewriteRule ^qa-report-generator\.php$ - [F,L]
RewriteRule ^reporte-consumo-ai\.php$ - [F,L]

# 3) Bloquear logs accesibles via web
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
    # Habilitar cuando 100% del sitio sea HTTPS:
    # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# 7) Negar ejecucion de PHP en directorios de uploads (defense-in-depth)
<Directory "wp-content/uploads">
    <FilesMatch "\.(php|phtml|phar|php3|php4|php5|php7|php8|pl|py|jsp|asp|sh|cgi)$">
        Require all denied
    </FilesMatch>
</Directory>
# END AT_HARDENING
```

## Notas

- En **local (WAMP)** el bloque ya está aplicado en `c:\wamp64\www\automatiza-tech\.htaccess`.
  Pruebas a realizar:
  - `http://localhost/automatiza-tech/debug-n8n-flow.php` → 403
  - `http://localhost/automatiza-tech/xmlrpc.php` → 403
  - `http://localhost/automatiza-tech/check-prefix.php` → 403
  - El sitio normal y `wp-admin` siguen funcionando.
- Si en el futuro **necesitas** ejecutar un script de mantenimiento, hazlo desde
  `wp-cli` o renómbralo temporalmente a algo que no matchee los patrones bloqueados.
- Esta es una **mitigación** mientras se completa la Fase 1 (mover los scripts fuera
  del webroot). No es la solución final.
