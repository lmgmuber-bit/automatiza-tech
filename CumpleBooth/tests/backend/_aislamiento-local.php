<?php
/** Entorno efímero para pruebas CLI; nunca carga configuración ni datos de producción. */
if (PHP_SAPI !== 'cli') { exit(2); }
$ccTestDir = sys_get_temp_dir() . '/cc-backoffice-test-' . bin2hex(random_bytes(8));
if (!mkdir($ccTestDir, 0700, true)) { throw new RuntimeException('No se creó el entorno de prueba'); }
foreach (['state', 'photos', 'acceptances', 'profiles', 'invitations'] as $dir) {
    mkdir($ccTestDir . '/' . $dir, 0700);
}
foreach ([
    'CUMPLECLICK_CONFIG_FILE' => $ccTestDir . '/no-config.php',
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $ccTestDir . '/test.sqlite',
    'CC_PDO_USER' => '', 'CC_PDO_PASSWORD' => '', 'CC_APP_HMAC_KEY' => bin2hex(random_bytes(32)),
    'CC_PUBLIC_BASE_URL' => 'http://127.0.0.1',
    'CC_STATE_DIR' => $ccTestDir . '/state', 'CC_PHOTO_DIR' => $ccTestDir . '/photos',
    'CC_ACCEPTANCE_DIR' => $ccTestDir . '/acceptances', 'CC_EVENT_PROFILE_DIR' => $ccTestDir . '/profiles',
    'CC_INVITATION_DIR' => $ccTestDir . '/invitations', 'CC_AJUSTES_PATH' => $ccTestDir . '/ajustes.json',
    'CC_SMTP_HOST' => '', 'CC_SMTP_USER' => '', 'CC_SMTP_PASSWORD' => '',
    'CC_NOTIFY_EMAIL' => '', 'CC_LEADS_NOTIFY_EMAIL' => '',
] as $key => $value) { putenv($key . '=' . $value); }
register_shutdown_function(static function () use ($ccTestDir): void {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ccTestDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) { @rmdir($item->getPathname()); }
        else { @unlink($item->getPathname()); }
    }
    @rmdir($ccTestDir);
});
