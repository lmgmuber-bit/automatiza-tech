# Cleanup Report — 2025-10-23

Scope: Remove dev/test utilities, installers, temporary backups, and exposed SQL/config backups that are not required by the public front site or the WordPress admin portal.

Retained: WordPress core files, active theme/plugins, `wp-config.php`, and utility `clear_cache.php`.

Intentionally kept (for now):
- debug-services-table.php (open in editor; used for local diagnostics)
- clear_cache.php (operational utility; can be removed on request)

Removed files

Root utilities, tests, and demos:
- backup-files.bat
- boton-whatsapp-corregido.html
- check-plans-db.php
- check-theme-files.php
- check_button_texts.php
- check_php.php
- check_services.php
- clean-ajax-files.php
- clean-bom.php
- clean-files.ps1
- correccion-completa-utf8.bat
- correccion-utf8-completada.html
- create-services-table.sql
- debug-console.js
- debug-contact.php
- debug-permisos-web.php
- debug-permisos.php
- debug-services.php
- debug.php
- debug_frontend_query.php
- debug_simple.php
- fix-all-files.php
- formulario-contacto-corregido.html
- full-diagnosis.php
- functions.php.backup-clean
- icono-analiticas-corregido.html
- init-contact-system.php
- install-automatiza-tech.php.old
- install-clean.php
- install-local.bat
- install-wamp.bat
- limpieza-utf8-total.bat
- readme.html
- reorder_plans.php
- services-frontend.php.backup-clean
- services-manager.php.backup-clean
- servicios-admin-simple.php
- set-highlight-profesional.php
- setup-final.php
- test-accordion-corregido.html
- test-ajax-direct.php
- test-boton-footer.html
- test-boton-simple.html
- test-caracteres-ok.html
- test-clase-especifica.html
- test-contacto.html
- test-db.php
- test-extensiones-php.php
- test-footer.html
- test-form.php
- test-iconos-corregidos.html
- test-menu-movil.html
- test-misma-config-phpmyadmin.php
- test-shortcode.php
- test-wordpress-especifico.php
- test.php
- test_frontend_direct.php
- test_shortcode.php
- verificacion-utf8-final.html
- verify_current_data.php
- WAMP-GUIA.md
- add_color_fields.php
- activate-plans.php

Backups and environment configs (security risk if exposed):
- .htaccess.backup
- wp-config PROD.php
- wp-config-backup-6195.php
- wp-config-backup.php
- wp-config-broken-20252010.php
- wp-config-local.php
- wp-config.php.bak

SQL dumps (should not live under web root):
- sql/database-setup-local.sql
- sql/database-setup.sql
- sql/automatiza_tech_local.sql

Notes
- WordPress core files and `xmlrpc.php` were not modified.
- `wp-config-sample.php` (standard with WP) was left in place.
- Theme backups under `tema-backup/` were not touched in this pass due to volume; recommend moving/deleting outside web root or removing entirely if confirmed unnecessary.

Next steps (optional)
- Remove `debug-services-table.php` and `clear_cache.php` after confirming they are no longer needed.
- Remove or relocate `tema-backup/` outside the web root.
- Add a deny rule for any future `sql/` folders in the web root (or avoid creating them).

Rollback
- If you need to restore any file, recover it from git history.
