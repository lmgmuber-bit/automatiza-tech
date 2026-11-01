<?php
/**
 * Aceptaciones del admin por HTTP: el enlace de firma se puede volver a ver (Luis, 30-oct-2026: «una vez que se
 * genera el enlace… no puedo verlo otra vez»). Levanta php -S con SQLite temporal, entra con la clave maestra y
 * comprueba:
 *  - un enlace pendiente emitido ahora aparece en el historial, con copiar, WhatsApp (al teléfono del cliente),
 *    copiar el mensaje y enviar por correo;
 *  - uno emitido antes (token al azar) no se puede mostrar y ofrece «Emitir enlace nuevo»;
 *  - emitir uno nuevo muestra el enlace, el anterior deja de servir y el nuevo queda en el historial.
 * Datos inventados. Uso: php tests/backend/aceptaciones-enlace-http.php
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-aceptaciones-enlace-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 18900 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;
$claveMaestra = 'clave-maestra-1234';

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('d', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations', 'CC_ACCEPTANCE_DIR' => $tmp . '/acceptances',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_ADMIN_PASSWORD_HASH' => password_hash($claveMaestra, PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once $raiz . '/public/lib.acceptance.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$fiesta = static fn(string $nombre): array => ['nombre' => $nombre, 'tema' => 'spidey', 'fecha' => '2026-12-06', 'activa' => false,
    'service_plan' => 'full', 'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s')];
cb_save_parties(['parties' => ['fiesta-nueva' => $fiesta('Niña Prueba'), 'fiesta-antigua' => $fiesta('Niño Prueba')]]);
$partes = cb_load_parties()['parties'];
$emision = ['client_name' => 'Ana Prueba', 'client_email' => 'ana@example.test', 'client_phone' => '+56 9 1111 2222',
    'expires_days' => 14, 'summary' => ['price_total' => '$0']];
$nueva = cb_create_plan_acceptance($partes['fiesta-nueva'], $emision, 'test');
$antigua = cb_create_plan_acceptance($partes['fiesta-antigua'], $emision, 'test');
// Así quedaban los enlaces antes del 30-oct-2026: un token al azar del que solo se guardaba el hash.
$tokenAntiguo = cb_opaque_token(16);
$pdo->prepare('UPDATE cc_plan_acceptances SET public_token_hash=? WHERE id=?')->execute([cb_hash_token($tokenAntiguo), (int) $antigua['id']]);
$slugNueva = (string) $partes['fiesta-nueva']['public_slug'];
$slugAntigua = (string) $partes['fiesta-antigua']['public_slug'];

$servidor = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $raiz . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/servidor.log', 'a'], 2 => ['file', $tmp . '/servidor.log', 'a']], $pipes, $raiz . '/public', $env);
register_shutdown_function(static function () use ($servidor, $tmp): void {
    if (is_resource($servidor)) { proc_terminate($servidor); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
for ($i = 0; $i < 50; $i++) {
    $s = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);
    if ($s) { fclose($s); break; }
    usleep(100000);
}

$tests = 0;
function h_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

final class Navegador
{
    public array $cookies = [];
    public function __construct(private string $base) {}
    public function pedir(string $metodo, string $ruta, ?array $datos = null): array
    {
        $cabeceras = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $k => $v) { $pares[] = $k . '=' . $v; }
            $cabeceras[] = 'Cookie: ' . implode('; ', $pares);
        }
        $cuerpo = '';
        if ($datos !== null) {
            $cuerpo = http_build_query($datos);
            $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
            $cabeceras[] = 'Content-Length: ' . strlen($cuerpo);
        }
        $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cabeceras),
            'content' => $cuerpo, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 15]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0;
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === 'deleted' || $m[2] === '') { unset($this->cookies[$m[1]]); } else { $this->cookies[$m[1]] = $m[2]; }
            }
        }
        return ['estado' => $estado, 'html' => $html];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}
/** El bloque del historial que muestra el enlace de una aceptación. */
function filaEnlace(string $html, int $id): string
{
    return preg_match('#<tr class="acc-enlace-fila" data-acc-enlace="' . $id . '">.*?</tr>#s', $html, $m) ? $m[0] : '';
}

try {
    $luis = new Navegador($base);
    $r = $luis->pedir('GET', '/admin/maestro.php');
    $luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => $claveMaestra]);

    // (a) Enlace emitido ahora: se vuelve a ver en el historial, con todo para mandarlo.
    $r = $luis->pedir('GET', '/admin/aceptaciones.php?party=' . rawurlencode($slugNueva));
    h_check($r['estado'] === 200, 'la página de aceptaciones carga');
    $bloque = filaEnlace($r['html'], (int) $nueva['id']);
    h_check(str_contains($bloque, 'value="' . htmlspecialchars($nueva['url'], ENT_QUOTES) . '"'), 'el historial vuelve a mostrar el enlace pendiente');
    h_check(str_contains($bloque, 'href="https://wa.me/56911112222?text='), 'WhatsApp abre el chat del teléfono del cliente');
    h_check(str_contains(html_entity_decode($bloque), rawurlencode($nueva['url'])), 'y el mensaje lleva el enlace');
    h_check(str_contains($bloque, 'Copiar el mensaje') && str_contains($bloque, 'name="action" value="enviar_firma"'), 'copiar el mensaje y enviar por correo');

    // (b) Enlace de antes (token al azar): no se puede mostrar; se ofrece emitir uno nuevo.
    $r = $luis->pedir('GET', '/admin/aceptaciones.php?party=' . rawurlencode($slugAntigua));
    $bloque = filaEnlace($r['html'], (int) $antigua['id']);
    h_check($bloque !== '' && !str_contains($bloque, 'aceptar-plan.php?t='), 'el enlace de antes no se muestra');
    h_check(str_contains($bloque, 'name="action" value="reemitir"'), 'se ofrece emitir uno nuevo');

    // (c) Emitir uno nuevo: se muestra, el anterior deja de servir y queda en el historial.
    $r = $luis->pedir('POST', '/admin/aceptaciones.php?party=' . rawurlencode($slugAntigua),
        ['csrf' => $luis->csrf($r['html']), 'action' => 'reemitir', 'id' => (int) $antigua['id']]);
    h_check($r['estado'] === 302, 'emitir uno nuevo redirige');
    $r = $luis->pedir('GET', '/admin/aceptaciones.php?party=' . rawurlencode($slugAntigua) . '&ok=reemitido');
    $vigente = cb_acceptance_enlace_vigente(cb_load_acceptance_by_id((int) $antigua['id']));
    h_check($vigente !== null && str_contains($r['html'], 'El anterior ya no funciona'), 'avisa que el anterior ya no funciona');
    h_check(str_contains(filaEnlace($r['html'], (int) $antigua['id']), 'value="' . htmlspecialchars((string) $vigente, ENT_QUOTES) . '"'), 'el enlace nuevo queda en el historial');
    h_check(cb_load_acceptance_by_token_hash(cb_hash_token($tokenAntiguo)) === null, 'el enlace anterior deja de servir');
    $pagina = (string) @file_get_contents((string) $vigente, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 15]]));
    h_check(str_contains($pagina, 'Ana Prueba') || str_contains($pagina, 'Términos'), 'el enlace nuevo abre la página de firma');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
echo "aceptaciones-enlace-http: $tests checks OK" . PHP_EOL;
