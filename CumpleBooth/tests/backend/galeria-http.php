<?php
/**
 * La galería pública con PIN, de punta a punta por HTTP (2026-09-15):
 *
 *  - pide el PIN y rechaza uno malo;
 *  - con el PIN, muestra la pestaña "De los invitados" con lo que subieron por el
 *    Álbum Recuerdo, cada foto con el nombre y el mensaje de quien la mandó;
 *  - lo que el organizador escondió no aparece, ni se sirve por ver-media.php;
 *  - un invitado NO ve el botón de imprimir (quedó solo para el admin) y sí puede
 *    descargar el ZIP con sus elegidas;
 *  - ver-media.php entrega la miniatura a la sesión de galería y 404 a quien no la tiene.
 *
 * Nunca toca una base real: la SQLite vive en una carpeta temporal que se borra al final.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-galeria-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 18900 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('c', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_ADMIN_PASSWORD_HASH' => password_hash('clave-maestra-1234', PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$tests = 0;
function g_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── Fiesta con PIN, álbum recibiendo y token de aporte ──────────────────────
g_check(cb_save_parties(['parties' => ['luciano-spidey' => [
    'nombre' => 'Luciano', 'tema' => 'spidey', 'fecha' => '2026-09-13', 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s'),
    'galeriaPin' => '1234', 'gallery_enabled' => true,
]]]), 'fiesta de prueba creada con PIN');
$party = cb_load_party_raw('luciano-spidey');
g_check(!empty($party['galeriaHabilitada']), 'la galería queda habilitada (interruptor + PIN)');
$partyId = cb_party_db_id('luciano-spidey');
$albumId = (int) cb_album_ensure($partyId)['id'];
cb_album_update($albumId, ['status' => 'collecting', 'intake_enabled' => 1, 'intake_videos' => 1]);
$token = cb_album_issue_token($albumId, 'intake', null, 'test');

// ── Servidor ────────────────────────────────────────────────────────────────
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

/** Un "navegador": guarda cookies, manda formularios o multipart y no sigue redirecciones. */
final class Navegador
{
    public array $cookies = [];
    public function __construct(private string $base) {}
    public function pedir(string $metodo, string $ruta, ?array $datos = null, array $archivos = []): array
    {
        $cabeceras = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $k => $v) { $pares[] = $k . '=' . $v; }
            $cabeceras[] = 'Cookie: ' . implode('; ', $pares);
        }
        $cuerpo = '';
        if ($metodo === 'POST') {
            if ($archivos) {
                $limite = '----cumpleclick' . bin2hex(random_bytes(8));
                foreach ((array) $datos as $k => $v) { $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n"; }
                foreach ($archivos as $campo => [$nombre, $mime, $bytes]) {
                    $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$campo\"; filename=\"$nombre\"\r\nContent-Type: $mime\r\n\r\n$bytes\r\n";
                }
                $cuerpo .= "--$limite--\r\n";
                $cabeceras[] = 'Content-Type: multipart/form-data; boundary=' . $limite;
            } else {
                $cuerpo = http_build_query((array) $datos);
                $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            $cabeceras[] = 'Content-Length: ' . strlen($cuerpo);
        }
        $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cabeceras),
            'content' => $cuerpo, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0; $ubicacion = ''; $tipo = '';
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
            if (stripos($linea, 'Location:') === 0) { $ubicacion = trim(substr($linea, 9)); }
            if (stripos($linea, 'Content-Type:') === 0) { $tipo = trim(substr($linea, 13)); }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === 'deleted' || $m[2] === '') { unset($this->cookies[$m[1]]); } else { $this->cookies[$m[1]] = $m[2]; }
            }
        }
        $json = json_decode($html, true);
        return ['estado' => $estado, 'ubicacion' => $ubicacion, 'tipo' => $tipo, 'html' => $html, 'json' => is_array($json) ? $json : null];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}

function jpeg(int $ancho, int $alto, int $semilla): string
{
    $im = imagecreatetruecolor($ancho, $alto);
    imagefilledrectangle($im, 0, 0, $ancho, $alto, imagecolorallocate($im, ($semilla * 37) % 255, 120, 200));
    imagefilledellipse($im, (int) ($ancho / 2), (int) ($alto / 2), (int) ($ancho / 2), (int) ($alto / 2), imagecolorallocate($im, 250, 200, 30));
    ob_start(); imagejpeg($im, null, 88); return (string) ob_get_clean();
}

echo "Galería pública con aportes por HTTP en $base\n";

// ── Dos aportes de invitados; uno lo esconde el organizador ─────────────────
$rosa = new Navegador($base);
$r = $rosa->pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1', 'name' => 'Tía Rosa', 'message' => '¡Qué linda fiesta! Un abrazo para Luciano'], ['file' => ['rosa.jpg', 'image/jpeg', jpeg(80, 100, 1)]]);
g_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true, 'la tía Rosa manda su foto con mensaje');
$r = $rosa->pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1', 'name' => 'Primo Feo', 'message' => 'esto no debería verse'], ['file' => ['feo.jpg', 'image/jpeg', jpeg(60, 60, 2)]]);
g_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true, 'otro invitado manda otra');
$filas = $pdo->query("SELECT id, access_token, contributor_name FROM cc_event_media WHERE album_id=$albumId ORDER BY id")->fetchAll();
g_check(count($filas) === 2, 'hay dos aportes en la base');
[$visible, $escondido] = $filas;
$pdo->prepare("UPDATE cc_event_media SET moderation_status='hidden' WHERE id=?")->execute([(int) $escondido['id']]);

// ── La galería: PIN, pestaña de invitados, sin imprimir ─────────────────────
$inv = new Navegador($base);
$r = $inv->pedir('GET', '/galeria.php?p=luciano-spidey');
g_check($r['estado'] === 200 && str_contains($r['html'], 'name="pin"') && !str_contains($r['html'], 'tab-invitado'), 'sin PIN se ve solo el formulario del PIN');
$r = $inv->pedir('POST', '/galeria.php?p=luciano-spidey', ['csrf' => $inv->csrf($r['html']), 'action' => 'login', 'pin' => '9999']);
g_check($r['estado'] === 200 && str_contains($r['html'], 'PIN incorrecto'), 'un PIN malo se rechaza');
$r = $inv->pedir('POST', '/galeria.php?p=luciano-spidey', ['csrf' => $inv->csrf($r['html']), 'action' => 'login', 'pin' => '1234']);
g_check($r['estado'] === 302 && str_starts_with($r['ubicacion'], 'galeria.php?p=luciano-spidey'), 'con el PIN correcto redirige a la galería (' . $r['estado'] . ')');
$r = $inv->pedir('GET', '/galeria.php?p=luciano-spidey');
$html = $r['html'];
g_check($r['estado'] === 200 && str_contains($html, 'id="tab-invitado"'), 'la galería abierta tiene la pestaña "De los invitados"');
g_check(preg_match('/De los invitados<b>1<\/b>/', $html) === 1, 'la pestaña cuenta 1 aporte visible (el escondido no cuenta)');
g_check(str_contains($html, '💌 Tía Rosa') && str_contains($html, '¡Qué linda fiesta! Un abrazo para Luciano'), 'la tarjeta lleva el nombre y el mensaje de quien la mandó');
g_check(!str_contains($html, 'Primo Feo') && !str_contains($html, 'esto no debería verse'), 'el aporte escondido por el organizador no aparece');
g_check(str_contains($html, 'ver-media.php?t=' . $visible['access_token'] . '&amp;v=thumb'), 'la miniatura apunta a ver-media.php con el token del aporte');
g_check(!str_contains($html, 'id="imprimir"') && !str_contains($html, 'Preparando la impresión') && !str_contains($html, 'Copias <select'), 'un invitado NO ve nada de imprimir');
g_check(str_contains($html, 'id="descargar"') && str_contains($html, 'puedes descargar las elegidas'), 'sí puede descargar las elegidas');
g_check(str_contains($html, 'Descargar todas (1)'), '"Descargar todas" cuenta el aporte visible');

// ── ver-media.php: miniatura para la sesión de galería, 404 para el resto ───
$r = $inv->pedir('GET', '/ver-media.php?t=' . $visible['access_token'] . '&v=thumb');
g_check($r['estado'] === 200 && str_starts_with($r['tipo'], 'image/'), 'la sesión de galería recibe la miniatura (' . $r['estado'] . ' ' . $r['tipo'] . ')');
$r = $inv->pedir('GET', '/ver-media.php?t=' . $escondido['access_token']);
g_check($r['estado'] === 404, 'el aporte escondido no se sirve ni con la galería abierta');
$anon = new Navegador($base);
$r = $anon->pedir('GET', '/ver-media.php?t=' . $visible['access_token'] . '&v=thumb');
g_check($r['estado'] === 404, 'sin sesión de galería, 404 (el álbum sigue armándose)');

// ── ZIP con el aporte elegido ───────────────────────────────────────────────
$r = $inv->pedir('GET', '/galeria.php?p=luciano-spidey&zip=1&sel[]=' . rawurlencode('aporte:' . $visible['id']));
g_check($r['estado'] === 200 && str_starts_with($r['tipo'], 'application/zip') && strlen($r['html']) > 100, 'el ZIP con el aporte elegido se descarga');

// ── El organizador (clave maestra) sigue teniendo imprimir ─────────────────
$luis = new Navegador($base);
$r = $luis->pedir('GET', '/admin/maestro.php');
$r = $luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => 'clave-maestra-1234']);
g_check($r['estado'] === 302, 'la clave maestra entra al admin (' . $r['estado'] . ')');
$r = $luis->pedir('GET', '/galeria.php?p=luciano-spidey');
g_check($r['estado'] === 200 && str_contains($r['html'], 'id="tab-invitado"'), 'el admin entra a la galería sin PIN y ve la pestaña de invitados');
g_check(str_contains($r['html'], 'id="imprimir"') && str_contains($r['html'], 'Preparando la impresión') && str_contains($r['html'], 'puedes imprimir o descargar'), 'el admin conserva imprimir');

echo "OK $tests checks galeria-http\n";
