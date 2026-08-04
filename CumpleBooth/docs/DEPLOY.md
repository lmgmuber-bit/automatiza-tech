# CumpleClick — deploy seguro a Hostinger

Objetivo: `https://automatizatech.cl/cumpleclick/`. No hay deploy realizado aún.

## 1. Preparación privada

1. Crear una BD y usuario exclusivos para CumpleClick; InnoDB + utf8mb4. No usar
   credenciales de WordPress/AutomatizaTech.
2. Colocar `scripts/`, `database/` y una copia de
   `config/cumpleclick.example.php` fuera de `public_html`.
3. Ejecutar `php scripts/bootstrap.php` o crear manualmente la configuración
   externa. Definir `CUMPLECLICK_CONFIG_FILE`; `public_base_url` debe ser
   `https://automatizatech.cl/cumpleclick` y `photo_dir`/`state_dir` deben estar
   fuera de DocumentRoot.
4. No subir ni mostrar el archivo real, passwords, HMAC key, dumps o backups.

## 2. Migración y cutover

```bash
php scripts/migrate.php
php scripts/import-theme-prompts.php          # dry-run obligatorio
php scripts/import-theme-prompts.php --apply  # carga prompts privados en la BD
php scripts/import-json-to-db.php          # dry-run obligatorio
php scripts/import-json-to-db.php --apply  # crea backups privados fechados
php scripts/parity-check.php
php scripts/retention.php                  # dry-run
```

Confirmar `storage_mode=db`. Programar `retention.php --apply` diariamente por
cron. Durante estabilización conservar BD, JSON y backups.

Rollback: `php scripts/rollback.php` (dry-run), después `--apply`; si la BD está
caída usar `--snapshot=<snapshot-cutover.json>` y documentar que el RPO es la
fecha del snapshot. El script no borra tablas ni fotos.

## 3. Build y publicación web

```bash
npm ci
npm test
npm run build
php scripts/check-dist-parity.php
```

Subir el contenido de `dist/` a `/public_html/cumpleclick/`, incluidos
`.htaccess` y `.user.ini`. No subir `src/`, `tests/`, `node_modules/`, config,
scripts, migraciones, fotos ni backups al webroot. Configurar HTTPS en el
vhost/proxy con host canónico; no usar el Host header para redirects.

## 4. Gate posterior

- `api.php?p=demo` → 200 y `ok:true` sin campos internos.
- `/data/parties.json`, `/admin/config.php`, config y storage → 403/404.
- Un único header `Permissions-Policy: camera=(self), microphone=(), geolocation=()`.
- Login/CSRF/logout admin; editar frame y confirmar persistencia.
- Upload PNG válido y negativos; QR abre `ver.php?t=<token>`.
- Chrome/tablet real: Preview, personaje, Baloo 2, QR, Diploma, consola limpia y
  cámara apagada al salir de Capture.

Nunca afirmar que PROD fue actualizado sin evidencia HTTP/FTP explícita.
