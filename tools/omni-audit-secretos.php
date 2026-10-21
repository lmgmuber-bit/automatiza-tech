<?php
/**
 * Tacha los secretos que quedaron guardados en claro en la auditoría del Portal
 * OmniCliente (wp_omnichannel_audit_log) antes de que audit_log() los tachara:
 * tokens de canal, API keys, sesión y códigos de reseteo de agentes, hashes de contraseña.
 * La lista de campos es OmnichannelController::AUDIT_SECRET_KEYS.
 *
 * Solo por línea de comandos y fuera del webroot. Nunca imprime un valor: solo conteos.
 *
 *   php omni-audit-secretos.php --wp=<ruta a wp-load.php>
 *       Cuenta lo que tacharía, sin escribir.
 *   php omni-audit-secretos.php --wp=<ruta a wp-load.php> --aplicar --respaldo=<archivo.jsonl>
 *       Guarda antes cada fila original en el respaldo (archivo nuevo, permisos 0600,
 *       fuera de public_html) y después tacha. El respaldo contiene los secretos viejos:
 *       borrarlo cuando la tabla esté verificada.
 *   --ids=12,15  limita el barrido a esas filas de la auditoría (pruebas o corrección puntual).
 *   --controlador=<ruta>  omnichannel-controller.php a usar (por defecto, el del sitio de --wp).
 *
 * Guía: Docs/OMNICLIENTE-METRICAS-IG.md, sección «Secretos de canales y auditoría».
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de comandos');
}

$opts = getopt('', ['wp:', 'aplicar', 'respaldo:', 'ids:', 'controlador:']);
$only_ids = isset($opts['ids']) ? array_map('intval', explode(',', (string) $opts['ids'])) : null;
$wp_load = $opts['wp'] ?? '';
if ($wp_load === '' || !is_file($wp_load)) {
    fwrite(STDERR, "Falta --wp=<ruta a wp-load.php>\n");
    exit(2);
}
$apply = array_key_exists('aplicar', $opts);
$backup_path = $opts['respaldo'] ?? '';

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define('WP_USE_THEMES', false);
require $wp_load;
require_once $opts['controlador'] ?? (dirname($wp_load) . '/omnichannel-controller.php');

if (!method_exists('OmnichannelController', 'redact_audit_history')) {
    fwrite(STDERR, "El omnichannel-controller.php de ese sitio no trae redact_audit_history(): sube primero el controlador nuevo.\n");
    exit(2);
}

$backup = null;
if ($apply) {
    if ($backup_path === '') {
        fwrite(STDERR, "--aplicar exige --respaldo=<archivo.jsonl>\n");
        exit(2);
    }
    $dir = realpath(dirname($backup_path));
    $webroot = realpath(ABSPATH);
    if ($dir === false || ($webroot !== false && strpos($dir . DIRECTORY_SEPARATOR, $webroot . DIRECTORY_SEPARATOR) === 0)) {
        fwrite(STDERR, "El respaldo debe ir en una carpeta existente fuera del sitio web.\n");
        exit(2);
    }
    $fh = @fopen($backup_path, 'x');
    if (!$fh) {
        fwrite(STDERR, "No se pudo crear el respaldo (¿ya existe?).\n");
        exit(2);
    }
    @chmod($backup_path, 0600);
    $backup = static function ($row) use ($fh) {
        fwrite($fh, wp_json_encode([
            'id'              => (int) $row->id,
            'entity_type'     => $row->entity_type,
            'old_values_json' => $row->old_values_json,
            'new_values_json' => $row->new_values_json,
        ]) . "\n");
    };
}

$result = (new OmnichannelController())->redact_audit_history($apply, $backup, $only_ids);
if (isset($fh)) {
    fclose($fh);
}
$result['modo'] = $apply ? 'aplicado' : 'solo conteo';
echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
