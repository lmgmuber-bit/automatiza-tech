# CumpleClick database

Las migraciones se ejecutan exclusivamente por CLI con `php scripts/migrate.php`.
La tabla `cc_schema_migrations` es creada por el runner y registra cada versión.
Los secretos y la cadena PDO viven en `config/cumpleclick.local.php` o variables de entorno.

`002_theme_prompts.php` crea el almacenamiento privado de prompts. Después de
migrar, ejecutar `php scripts/import-theme-prompts.php` y revisar el dry-run antes
de repetir con `--apply`; el importador es idempotente mediante upsert.
