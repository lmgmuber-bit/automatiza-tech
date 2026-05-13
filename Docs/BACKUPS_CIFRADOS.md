# Backups cifrados off-site — checklist operativo

> Estado: pendiente de implementacion en infraestructura. Esta guia es la
> referencia para configurar y verificar backups una vez activos.

## Objetivo

Tener copia diaria, cifrada y fuera del servidor de produccion, con
restauracion mensual probada.

## Componentes

1. **Backup en Hostinger** (incluido en el plan): activar y verificar que
   este corriendo diariamente.
2. **Copia off-site cifrada** a S3 / Backblaze B2 / Google Drive.
3. **Encriptacion en cliente** con `age` o `gpg` antes de subir.
4. **Restore drill** mensual.

## Que respaldar

- BD MySQL completa (`mysqldump --single-transaction --quick`).
- `wp-content/uploads/` (PDFs, facturas, evidencias QA).
- `wp-content/themes/automatiza-tech/`.
- `wp-content/mu-plugins/`.
- Archivos de raiz: `wp-config.php`, `wp-config-secrets.php`, `.htaccess`,
  `at-*.php`.

NO respaldar:
- `wp-content/plugins/` (se reinstalan desde repo).
- `wp-content/uploads/ai1wm-backups/`.
- `node_modules/`, `.git/`, `archive/`, `RespaldoDocs/`, etc.

## Script sugerido (cron diario en Hostinger)

```bash
#!/bin/bash
set -euo pipefail

DATE=$(date +%Y%m%d-%H%M)
BACKUP_DIR="/home/u402745362/backups"
SRC="/home/u402745362/domains/automatizatech.cl/public_html"

# 1. Dump BD
mysqldump \
  --single-transaction --quick --lock-tables=false \
  -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "$BACKUP_DIR/db-$DATE.sql.gz"

# 2. Tar uploads + tema + mu-plugins + raiz hardening
tar -czf "$BACKUP_DIR/files-$DATE.tar.gz" \
  -C "$SRC" \
    wp-content/uploads \
    wp-content/themes/automatiza-tech \
    wp-content/mu-plugins \
    wp-config.php wp-config-secrets.php .htaccess \
    at-*.php

# 3. Cifrar (age recipient publico, clave privada NO en servidor)
age -r "$AGE_PUBKEY" -o "$BACKUP_DIR/db-$DATE.sql.gz.age"   "$BACKUP_DIR/db-$DATE.sql.gz"
age -r "$AGE_PUBKEY" -o "$BACKUP_DIR/files-$DATE.tar.gz.age" "$BACKUP_DIR/files-$DATE.tar.gz"

# 4. Subir a B2 / S3
b2 upload-file at-backups "$BACKUP_DIR/db-$DATE.sql.gz.age"   "automatizatech/db-$DATE.sql.gz.age"
b2 upload-file at-backups "$BACKUP_DIR/files-$DATE.tar.gz.age" "automatizatech/files-$DATE.tar.gz.age"

# 5. Borrar plaintext y rotar local
rm -f "$BACKUP_DIR"/*.sql.gz "$BACKUP_DIR"/*.tar.gz
find "$BACKUP_DIR" -name "*.age" -mtime +14 -delete
```

## Variables a definir

- `AGE_PUBKEY` — clave publica de age (la privada vive solo en tu
  laptop personal). Generar con `age-keygen -o ~/at-backups.key`.
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` — desde wp-config-secrets.php.
- `B2_APPLICATION_KEY_ID`, `B2_APPLICATION_KEY` — credenciales Backblaze.

## Restore drill mensual

1. Descargar el ultimo `*.age` de B2.
2. Descifrar: `age -d -i ~/at-backups.key files-YYYYMMDD-HHMM.tar.gz.age | tar xz`.
3. Levantar BD en docker: `docker run --rm mariadb` y restaurar.
4. Confirmar tablas y conteos.
5. Anotar fecha y resultado en este documento.

## Bitacora

| Fecha | Resultado | Notas |
|-------|-----------|-------|
| TBD   | -         | Primera prueba pendiente |
