<?php
/** Shim sin secretos: la credencial se carga desde config/ o variables de entorno. */
if (!function_exists('cb_config')) {
    throw new RuntimeException('lib.php debe cargarse antes de admin/config.php');
}
require_once __DIR__ . '/../lib.admin-password.php';

// La contraseña puesta desde "recuperar" manda sobre la del archivo de configuración: es la
// unica forma de cambiarla sin entrar por SSH a reescribir un archivo con secretos. Vive como
// hash en el directorio de estado, fuera de la carpeta publica; borrar ese archivo devuelve
// la contraseña original.
$ccHashRecuperado = cb_admin_password_override();
define('ADMIN_PASSWORD_HASH', $ccHashRecuperado !== ''
    ? $ccHashRecuperado
    : (string) cb_config('admin_password_hash'));
