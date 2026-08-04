<?php
/** Lint recursivo con el mismo PHP_BINARY que ejecuta este archivo. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__, 2);
$files = [];
foreach (['public', 'database', 'scripts', 'tests', 'config', 'sitio'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) { if ($file->isFile() && strtolower($file->getExtension()) === 'php') { $files[] = $file->getPathname(); } }
}
sort($files, SORT_STRING);
foreach ($files as $file) {
    $output = []; $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file), $output, $code);
    if ($code !== 0) { fwrite(STDERR, implode("\n", $output) . "\n"); exit(1); }
}
fwrite(STDOUT, 'OK lint PHP ' . PHP_VERSION . ': ' . count($files) . " archivos.\n");

/**
 * Smoke require: php -l solo detecta errores de sintaxis, NUNCA una función
 * redeclarada (dos requires del mismo archivo) — ese es un fatal error que
 * solo aparece al ejecutar de verdad. Encontramos exactamente ese bug en
 * admin/invitations.php (Gate A) escondido detrás de un lint 100% verde, así
 * que cada entrypoint público se hace requires de verdad, en un subproceso
 * aislado (storage_mode=json + carpetas temporales, nunca la BD real) para
 * no arrastrar estado entre archivos ni tocar producción/local real.
 */
$entrypoints = ['public/admin/index.php', 'public/admin/invitations.php', 'public/upload.php', 'public/galeria.php', 'public/ver.php', 'public/invitacion.php', 'public/descargar-invitacion.php', 'sitio/api/contacto.php'];
$smokeTmp = sys_get_temp_dir() . '/cumpleclick-lint-smoke-' . bin2hex(random_bytes(4));
mkdir($smokeTmp, 0770, true);
$smokeEnv = [
    'PATH' => (string) getenv('PATH'),
    'CC_STORAGE_MODE' => 'json',
    'CC_APP_HMAC_KEY' => str_repeat('a', 64),
    'CC_PUBLIC_BASE_URL' => 'https://example.test/cumpleclick',
    'CC_PHOTO_DIR' => $smokeTmp . '/photos',
    'CC_STATE_DIR' => $smokeTmp . '/state',
    'CC_INVITATION_DIR' => $smokeTmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => '',
];
foreach ($entrypoints as $rel) {
    $abs = $root . '/' . $rel;
    if (!is_file($abs)) { continue; }
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $cmd = [PHP_BINARY, '-r', 'chdir(' . var_export(dirname($abs), true) . '); require ' . var_export($abs, true) . ';'];
    $process = proc_open($cmd, $descriptors, $pipes, null, $smokeEnv);
    if (!is_resource($process)) { fwrite(STDERR, "FAIL no se pudo lanzar smoke require de $rel\n"); exit(1); }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    $combined = $stdout . $stderr;
    if (strpos($combined, 'Fatal error') !== false || strpos($combined, 'Cannot redeclare') !== false || strpos($combined, 'Parse error') !== false) {
        fwrite(STDERR, "FAIL smoke require de $rel disparó un fatal error:\n$combined\n");
        exit(1);
    }
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($smokeTmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($smokeTmp);
fwrite(STDOUT, 'OK smoke require de ' . count($entrypoints) . " entrypoints (sin fatal errors escondidos).\n");
