<?php
/** Shim sin secretos: la credencial se carga desde config/ o variables de entorno. */
if (!function_exists('cb_config')) {
    throw new RuntimeException('lib.php debe cargarse antes de admin/config.php');
}
define('ADMIN_PASSWORD_HASH', (string) cb_config('admin_password_hash'));
