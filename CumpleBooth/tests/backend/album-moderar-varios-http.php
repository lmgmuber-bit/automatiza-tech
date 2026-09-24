<?php
/**
 * Curaduría por HTTP: marcar varios recuerdos y aprobarlos/ocultarlos/eliminarlos de una
 * (2026-09-20). Levanta php -S con SQLite temporal, entra con la clave maestra, siembra un
 * álbum con tres aportes pendientes y comprueba:
 *  - la página trae la barra "Marcar todos" y una casilla por recuerdo, atada al formulario;
 *  - aprobar dos de tres deja exactamente esos dos aprobados;
 *  - sin nada marcado avisa y no cambia nada;
 *  - eliminar el que es portada la suelta.
 * Uso: php tests/backend/album-moderar-varios-http.php
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-moderar-varios-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 18700 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;
$claveMaestra = 'clave-maestra-1234';

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('c', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_ADMIN_PASSWORD_HASH' => password_hash($claveMaestra, PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

cb_save_parties(['parties' => ['luciano-spidey' => [
    'nombre' => 'Luciano', 'tema' => 'spidey', 'fecha' => '2026-09-13', 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s'), 'galeriaPin' => '1234',
]]]);
$partyId = cb_party_db_id('luciano-spidey');
$albumId = (int) cb_album_ensure($partyId)['id'];

function foto(int $semilla): string
{
    $im = imagecreatetruecolor(120, 160);
    imagefilledrectangle($im, 0, 0, 120, 160, imagecolorallocate($im, 40 * $semilla % 255, 90, 200));
    ob_start(); imagejpeg($im, null, 80); return (string) ob_get_clean();
}
$ids = [];
for ($i = 1; $i <= 3; $i++) {
    $bytes = foto($i);
    $tmpFile = tempnam(sys_get_temp_dir(), 'ccv');
    file_put_contents($tmpFile, $bytes);
    $key = cb_album_storage_key('luciano-spidey', 'jpg');
    $stored = cb_album_store_file($tmpFile, $key, false);
    $thumb = cb_album_make_thumbnail($stored, 'luciano-spidey', 'jpg');
    cb_album_record_media($albumId, $partyId, [
        'source' => 'guest', 'media_kind' => 'image', 'access_token' => cb_opaque_token(16),
        'storage_key' => $key, 'thumb_storage_key' => $thumb, 'poster_storage_key' => null,
        'original_name' => "f$i.jpg", 'mime' => 'image/jpeg', 'byte_size' => strlen($bytes), 'width' => 120, 'height' => 160,
        'duration_seconds' => null, 'sha256' => hash('sha256', $bytes),
        'contributor_name' => 'Mamá de Luciano', 'contributor_message' => $i === 1 ? 'Gracias a todos' : null,
        'moderation_status' => 'pending', 'consent_version' => cb_album_consent_version(),
        'uploader_hmac' => cb_hmac('test', 'album-intake'),
    ]);
}
// record_media devuelve el token de acceso, no el id: los ids salen de la lista del álbum.
$ids = array_map(static fn(array $r): int => (int) $r['id'], cb_album_list_media($albumId));

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
function estados(int $albumId): array
{
    $out = [];
    // Todos los estados: la lista por defecto no trae los eliminados.
    foreach (cb_album_list_media($albumId, cb_album_moderation_states()) as $row) { $out[(int) $row['id']] = (string) $row['moderation_status']; }
    return $out;
}

echo "Curaduría, marcar varios, por HTTP en $base\n";
$luis = new Navegador($base);
$r = $luis->pedir('GET', '/admin/maestro.php');
$r = $luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => $claveMaestra]);
$r = $luis->pedir('GET', '/admin/album.php?party=luciano-spidey');
h_check($r['estado'] === 200 && str_contains($r['html'], 'id="form-moderar-varios"'), 'la Curaduría trae la barra de marcar varios');
h_check(substr_count($r['html'], 'form="form-moderar-varios"') === 3, 'hay una casilla por recuerdo, atada al formulario de la barra');
h_check(str_contains($r['html'], 'id="curacion-marcar-todos"'), 'está la casilla "Marcar todos"');
$csrf = $luis->csrf($r['html']);

// Aprobar dos de tres.
$r = $luis->pedir('POST', '/admin/album.php?party=luciano-spidey', ['csrf' => $csrf, 'action' => 'moderar-varios', 'estado' => 'approved', 'media' => [$ids[0], $ids[1]]]);
h_check(in_array($r['estado'], [200, 302, 303], true), 'aprobar marcados contesta');
$e = estados($albumId);
$diag = $r['estado'] . ' ' . json_encode($e) . ' | ' . (preg_match('/class="(?:alert|flash|error|notice)[^"]*"[^>]*>(.*?)<\/(?:div|p)>/s', $r['html'], $m) ? trim(strip_tags($m[1])) : substr(preg_replace('/\s+/', ' ', strip_tags($r['html'])), 0, 240));
h_check($e[$ids[0]] === 'approved' && $e[$ids[1]] === 'approved' && $e[$ids[2]] === 'pending', 'aprobó exactamente los dos marcados y dejó el tercero pendiente: ' . $diag);

// Sin nada marcado: aviso y nada cambia.
$r = $luis->pedir('POST', '/admin/album.php?party=luciano-spidey', ['csrf' => $csrf, 'action' => 'moderar-varios', 'estado' => 'approved']);
h_check(str_contains($r['html'], 'Marca al menos un recuerdo'), 'sin marcar nada, avisa');
h_check(estados($albumId)[$ids[2]] === 'pending', 'sin marcar nada, no cambia nada');

// Un id ajeno no cuenta ni rompe.
$r = $luis->pedir('POST', '/admin/album.php?party=luciano-spidey', ['csrf' => $csrf, 'action' => 'moderar-varios', 'estado' => 'approved', 'media' => [999999, $ids[2]]]);
h_check(estados($albumId)[$ids[2]] === 'approved', 'con un id ajeno en la lista, igual aprueba el propio');

// La portada se suelta si se elimina de a varios.
cb_album_update($albumId, ['cover_media_id' => $ids[0]]);
$r = $luis->pedir('POST', '/admin/album.php?party=luciano-spidey', ['csrf' => $csrf, 'action' => 'moderar-varios', 'estado' => 'removed', 'media' => [$ids[0]]]);
h_check(estados($albumId)[$ids[0]] === 'removed', 'eliminar marcados marca el estado');
h_check((int) (cb_album_find_by_id($albumId)['cover_media_id'] ?? 0) === 0, 'al eliminar la portada de a varios, la portada se suelta');

// Sin sesión no se puede.
$anon = new Navegador($base);
$r = $anon->pedir('POST', '/admin/album.php?party=luciano-spidey', ['csrf' => $csrf, 'action' => 'moderar-varios', 'estado' => 'approved', 'media' => [$ids[2]]]);
h_check($r['estado'] !== 200 || !str_contains($r['html'], 'id="form-moderar-varios"'), 'sin sesión no entra a la Curaduría');

echo "OK $tests checks album-moderar-varios-http\n";
