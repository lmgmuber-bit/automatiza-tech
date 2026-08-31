<?php
/** Bootstrap local: crea config fuera del webroot sin mostrar credenciales. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__);
$target = $root . '/config/cumpleclick.local.php';
if (is_file($target) && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Ya existe config/cumpleclick.local.php. Usa --force para reemplazarlo.\n"); exit(1);
}
function prompt_value(string $label, string $default = ''): string {
    fwrite(STDOUT, $label . ($default !== '' ? " [$default]" : '') . ': ');
    $value = trim((string) fgets(STDIN)); return $value === '' ? $default : $value;
}
$mode = prompt_value('Storage mode (json|db)', 'db');
if (!in_array($mode, ['json', 'db'], true)) { fwrite(STDERR, "Modo inválido.\n"); exit(1); }
$dsn = $mode === 'db' ? prompt_value('PDO DSN') : '';
$user = $mode === 'db' ? prompt_value('PDO user') : '';
$pdoPassword = $mode === 'db' ? prompt_value('PDO password') : '';
$publicBaseUrl = prompt_value('URL pública base (sin slash final)');
if (filter_var($publicBaseUrl, FILTER_VALIDATE_URL) === false) { fwrite(STDERR, "URL pública inválida.\n"); exit(1); }
$privateBase = rtrim(prompt_value('Directorio privado absoluto (fuera del DocumentRoot)'), '/\\');
if ($privateBase === '' || preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $privateBase) !== 1) { fwrite(STDERR, "Directorio privado inválido.\n"); exit(1); }
$adminPassword = prompt_value('Nueva contraseña admin');
if (strlen($adminPassword) < 12) { fwrite(STDERR, "La contraseña admin debe tener al menos 12 caracteres.\n"); exit(1); }
$config = [
    'storage_mode' => $mode, 'pdo_dsn' => $dsn, 'pdo_user' => $user,
    'pdo_password' => $pdoPassword, 'admin_password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
    'app_hmac_key' => bin2hex(random_bytes(32)),
    'public_base_url' => rtrim($publicBaseUrl, '/'),
    'photo_dir' => $privateBase . '/photos', 'state_dir' => $privateBase . '/state',
    'retention_days' => 30, 'session_idle_seconds' => 7200, 'session_absolute_seconds' => 43200,
];
$php = "<?php\n// Generado localmente. No versionar.\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($target, $php, LOCK_EX) === false) { fwrite(STDERR, "No se pudo escribir la configuración.\n"); exit(1); }
@chmod($target, 0600);
fwrite(STDOUT, "Configuración local creada. No se imprimieron secretos.\n");
