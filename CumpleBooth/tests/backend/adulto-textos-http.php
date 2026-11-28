<?php
/** AT-CUMPLECLICK-026: páginas completas con modalidad obtenida desde una fiesta real de QA.
 * SQLite y servidor locales; sin datos, correos, multimedia ni configuración reales.
 * El padre limpia después de cerrar la conexión del hijo (también en Windows).
 */
if (PHP_SAPI !== 'cli') { exit(2); }
if (!in_array('--worker', $argv, true)) {
    $base = realpath(sys_get_temp_dir());
    $tmp = $base . DIRECTORY_SEPARATOR . 'cc-adulto-textos-http-' . bin2hex(random_bytes(6));
    if (!$base || !mkdir($tmp, 0700) || dirname(realpath($tmp)) !== $base) {
        throw new RuntimeException('No se pudo crear el entorno temporal aislado.');
    }
    $remove = static function (string $path) use (&$remove, $tmp): void {
        if ($path !== $tmp && !str_starts_with($path, $tmp . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Limpieza fuera del entorno de esta prueba.');
        }
        if (is_link($path)) { throw new RuntimeException('Enlace inesperado en entorno temporal.'); }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
            }
            if (!rmdir($path)) { throw new RuntimeException('No se pudo retirar un directorio propio.'); }
        } elseif (is_file($path) && !unlink($path)) {
            throw new RuntimeException('No se pudo retirar un archivo propio.');
        }
    };
    try {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker'], [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes, null, array_merge(getenv(), ['CC_ADULTO_TEXTOS_TEST_DIR' => $tmp]));
        $code = is_resource($process) ? proc_close($process) : 1;
    } finally { $remove($tmp); }
    exit($code);
}

$root = dirname(__DIR__, 2);
$tmp = (string) getenv('CC_ADULTO_TEXTOS_TEST_DIR');
if (!is_dir($tmp) || !str_starts_with(basename($tmp), 'cc-adulto-textos-http-')) { exit(2); }
foreach (['state', 'photos', 'invitations', 'acceptances', 'profiles', 'sessions'] as $dir) {
    mkdir($tmp . '/' . $dir, 0700);
}
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) { throw new RuntimeException('No se pudo reservar un puerto local.'); }
$address = stream_socket_get_name($socket, false);
fclose($socket);
$base = 'http://' . $address;
$password = bin2hex(random_bytes(16));
$env = array_merge(getenv(), [
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/absent-config.php',
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/qa.sqlite',
    'CC_PDO_USER' => '', 'CC_PDO_PASSWORD' => '', 'CC_APP_HMAC_KEY' => bin2hex(random_bytes(32)),
    'CC_ADMIN_PASSWORD_HASH' => password_hash($password, PASSWORD_DEFAULT),
    'CC_PUBLIC_BASE_URL' => $base, 'CC_STATE_DIR' => $tmp . '/state', 'CC_PHOTO_DIR' => $tmp . '/photos',
    'CC_INVITATION_DIR' => $tmp . '/invitations', 'CC_ACCEPTANCE_DIR' => $tmp . '/acceptances',
    'CC_EVENT_PROFILE_DIR' => $tmp . '/profiles', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_SMTP_HOST' => '', 'CC_SMTP_USER' => '', 'CC_SMTP_PASSWORD' => '',
    'CC_NOTIFY_EMAIL' => '', 'CC_LEADS_NOTIFY_EMAIL' => '',
]);
foreach ($env as $key => $value) { putenv($key . '=' . $value); }
require_once $root . '/public/lib.php';
require_once $root . '/public/lib.manual.php';
require_once __DIR__ . '/_migraciones.php';
cb_test_migrar_todo(cb_pdo());
$cases = [
    'adulto' => ['adult_birthday', 'adulto-coqueteria-pop', 61],
    'infantil' => ['child_birthday', 'spidey', 6],
    'baby' => ['baby_shower', 'baby-nube', null],
];
$parties = [];
foreach ($cases as $name => [$type, $theme, $age]) {
    $slug = 'qa-textos-' . $name;
    $parties[$slug] = ['public_slug' => $slug, 'admin_label' => 'QA ' . $name,
        'birthday_person_name' => 'Persona de prueba', 'nombre' => 'Persona de prueba',
        'event_type' => $type, 'tema' => $theme, 'theme_slug' => $theme, 'edad' => $age,
        'fecha' => '2026-12-04', 'service_plan' => 'full', 'activa' => false, 'invitados' => []];
}
if (!cb_save_parties(['parties' => $parties])) { throw new RuntimeException('No se guardaron fiestas de QA.'); }
$server = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', '-d', 'sys_temp_dir=' . $tmp,
    '-d', 'session.save_path=' . $tmp . '/sessions', '-S', $address, '-t', $root . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/server.log', 'a'], 2 => ['file', $tmp . '/server.log', 'a']],
    $pipes, $root . '/public', $env);
if (!is_resource($server)) { throw new RuntimeException('No se pudo iniciar el servidor local.'); }
register_shutdown_function(static function () use ($server): void {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
});
for ($i = 0; $i < 50; $i++) {
    $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
    if ($connection) { fclose($connection); break; }
    usleep(100000);
}

$cookies = [];
$request = static function (string $route, ?array $data = null) use ($base, &$cookies): array {
    $headers = [];
    if ($cookies) { $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($key, $value) => $key . '=' . $value, array_keys($cookies), $cookies)); }
    if ($data !== null) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
    $context = stream_context_create(['http' => ['method' => $data === null ? 'GET' : 'POST',
        'header' => implode("\r\n", $headers), 'content' => $data === null ? '' : http_build_query($data),
        'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10]]);
    $html = (string) @file_get_contents($base . $route, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $line, $match)) { $status = (int) $match[1]; }
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $match)) { $cookies[$match[1]] = $match[2]; }
    }
    return [$status, $html];
};
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $message); }
};
[$status, $html] = $request('/admin/maestro.php');
preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $csrf);
[$status] = $request('/admin/maestro.php', ['csrf' => $csrf[1] ?? '', 'password' => $password]);
$check($status === 302, 'el administrador de QA inicia sesión');
foreach ($cases as $name => [$type]) {
    $slug = 'qa-textos-' . $name;
    $adult = $type === 'adult_birthday';
    $party = cb_load_party_raw($slug);
    $issued = cb_create_plan_acceptance($party, ['client_name' => 'Cliente de prueba',
        'client_email' => 'qa@example.invalid', 'summary' => ['price_total' => '$50.000']], 'qa-textos-http');
    $check(!empty($issued['ok']), $name . ': emite aceptación para la fiesta de QA');
    $route = '/aceptar-plan.php?t=' . ($issued['token'] ?? '');
    [$status, $html] = $request($route);
    $check($status === 200, $name . ': abre la aceptación por su token real');
    $label = $adult ? 'Relación con la persona homenajeada' : 'Relación con el niño o niña homenajeado';
    $check(str_contains($html, '<label for="signer_relationship">' . $label . '</label>'), $name . ': relación deriva de la fiesta del token');
    preg_match('/name="nonce" value="([^"]+)"/', $html, $nonce);
    [$status, $html] = $request($route, ['nonce' => $nonce[1] ?? '', 'signer_relationship' => '']);
    $errorText = $adult ? 'Indica tu relación con la persona homenajeada.' : 'Indica tu relación con el niño o niña homenajeado.';
    $check($status === 200 && str_contains($html, $errorText), $name . ': POST real valida relación según la fiesta');
    [$status, $html] = $request('/manual.php?p=' . $slug . '&f=' . cb_manual_firma($slug));
    $visible = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $check($status === 200, $name . ': abre el manual firmado completo');
    $check(($adult ? preg_match('/\b(?:niños?|niñas?|var[oó]n(?:es)?)\b/iu', $visible) === 0
        : str_contains($visible, 'niños')), $name . ': el manual recibe la modalidad desde cb_manual_datos');
    [$status, $html] = $request('/admin/invitations.php?party=' . $slug);
    $check($status === 200, $name . ': abre el administrador de invitaciones');
    $check(str_contains($html, $adult ? 'Hombre (cumpleañero)' : 'Niño (cumpleañero)')
        && str_contains($html, $adult ? 'Mujer (cumpleañera)' : 'Niña (cumpleañera)'), $name . ': selector de género según la fiesta real');
    if ($adult) { $check(!str_contains($html, 'Niño (cumpleañero)') && !str_contains($html, 'Niña (cumpleañera)'), 'adulto: no conserva opciones infantiles'); }
    [$status, $html] = $request('/admin/event-profile.php?party=' . $slug);
    $privacy = $adult ? 'Privacidad de la persona homenajeada y sus invitados' : 'Privacidad de niños y familias';
    $check($status === 200 && str_contains($html, $privacy), $name . ': privacidad del perfil según la fiesta real');
}
echo 'OK ' . $checks . " comprobaciones HTTP textos adultos, infantil y baby shower\n";
