<?php
/**
 * «Descargar todas las fotos (ZIP)» del admin, por HTTP (pedido de Luis, 2026-10-21). Levanta php -S con SQLite
 * temporal y comprueba:
 *  - sin sesión manda al login; la página del Álbum trae el botón;
 *  - el ZIP abre con ZipArchive (y pasa su chequeo de consistencia), pesa lo que dice Content-Length y cada foto
 *    llega idéntica byte a byte;
 *  - trae `cabina/` sin lo de la papelera e `invitados/` con lo aprobado y lo pendiente, sin lo oculto, sin videos
 *    y sin repetir las fotos de cabina que el álbum enlaza;
 *  - un operador con solo «Fotos del kiosco» recibe solo `cabina/`, y en una fiesta que no es suya, 403;
 *  - una fiesta sin fotos contesta 404 con un texto, no un ZIP vacío.
 * Uso: php tests/backend/descargar-fotos-http.php
 */
if (PHP_SAPI !== 'cli') { exit(2); }
if (!class_exists('ZipArchive')) { echo "SKIP: esta PHP no trae ZipArchive para leer el ZIP\n"; exit(0); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-descargar-fotos-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 18900 + random_int(0, 40);
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
require_once $raiz . '/public/lib.album.php';
require_once $raiz . '/public/lib.zip.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$tests = 0;
function h_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── La librería sola ────────────────────────────────────────────────────────
h_check(cb_zip_nombre_seguro('../../etc/passwd') === 'etc/passwd', 'un nombre no puede subir carpetas');
h_check(cb_zip_nombre_seguro('a\\b/c:d.jpg') === 'a/b/cd.jpg', 'sin barras invertidas ni dos puntos');
$usados = [];
h_check(cb_zip_nombre_unico('x/f.jpg', $usados) === 'x/f.jpg' && cb_zip_nombre_unico('x/F.jpg', $usados) === 'x/F-2.jpg', 'nombres repetidos se numeran');
h_check(cb_zip_slug('María José Núñez') === 'maria-jose-nunez' && cb_zip_slug('  ', 'invitado') === 'invitado', 'slug legible');

// ── Datos: dos fiestas, fotos de cabina (una en la papelera) y recuerdos del álbum ─────────
cb_save_parties(['parties' => [
    'rosa-prueba' => ['nombre' => 'Rosa Elena', 'tema' => 'spidey', 'fecha' => '2026-10-16', 'activa' => true,
        'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'), 'galeriaPin' => '1234'],
    'otra-fiesta' => ['nombre' => 'Otra', 'tema' => 'spidey', 'fecha' => '2026-10-20', 'activa' => true,
        'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'), 'galeriaPin' => '1234'],
    'sin-fotos' => ['nombre' => 'Vacía', 'tema' => 'spidey', 'fecha' => '2026-10-22', 'activa' => true,
        'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'), 'galeriaPin' => '1234'],
]]);
function jpeg(int $semilla): string
{
    $im = imagecreatetruecolor(90, 160);
    imagefilledrectangle($im, 0, 0, 90, 160, imagecolorallocate($im, (37 * $semilla) % 255, 90, 200));
    ob_start(); imagejpeg($im, null, 85); return (string) ob_get_clean();
}
$esperado = []; // nombre en el zip => sha256
$cabina = [];
foreach ([1, 2, 3] as $i) {
    $token = bin2hex(random_bytes(16));
    $key = "rosa-prueba/2026/10/$token.jpg";
    $bytes = jpeg($i);
    @mkdir(dirname(cb_photo_absolute_path($key)), 0770, true);
    file_put_contents(cb_photo_absolute_path($key), $bytes);
    h_check(cb_record_photo('rosa-prueba', ['token' => $token, 'storage_key' => $key, 'original_name' => "c$i.jpg",
        'byte_size' => strlen($bytes), 'width' => 90, 'height' => 160, 'sha256' => hash('sha256', $bytes),
        'created_at' => gmdate('Y-m-d H:i:s', strtotime("2026-10-16 23:0$i:00"))]), "foto de cabina $i");
    $cabina[$i] = hash('sha256', $bytes);
}
$fotos = cb_photos_admin('rosa-prueba');
$idDeLaTercera = (int) array_values(array_filter($fotos, static fn($f) => (string) $f['original_name'] === 'c3.jpg'))[0]['id'];
h_check(cb_photo_borrar('rosa-prueba', $idDeLaTercera), 'la tercera va a la papelera');
$esperado['cabina/001-2026-10-16_2301.jpg'] = $cabina[1];
$esperado['cabina/002-2026-10-16_2302.jpg'] = $cabina[2];

$partyId = cb_party_db_id('rosa-prueba');
$albumId = (int) cb_album_ensure($partyId)['id'];
cb_album_sync_booth_photos($albumId, $partyId); // el álbum enlaza las de cabina: no deben salir dos veces
$recuerdos = [['Tía Rosa', 'approved', 'image', 11], ['', 'pending', 'image', 12], ['Pepe', 'hidden', 'image', 13], ['Lola', 'approved', 'video', 14]];
foreach ($recuerdos as [$quien, $estado, $tipo, $semilla]) {
    $bytes = $tipo === 'image' ? jpeg($semilla) : str_repeat("\x00", 64) . 'ftypmp42' . random_bytes(200);
    $f = tempnam(sys_get_temp_dir(), 'ccz');
    file_put_contents($f, $bytes);
    $key = cb_album_storage_key('rosa-prueba', $tipo === 'image' ? 'jpg' : 'mp4');
    cb_album_store_file($f, $key, false);
    cb_album_record_media($albumId, $partyId, [
        'source' => 'guest', 'media_kind' => $tipo, 'access_token' => cb_opaque_token(16), 'storage_key' => $key,
        'thumb_storage_key' => null, 'poster_storage_key' => null, 'original_name' => "r$semilla", 'mime' => $tipo === 'image' ? 'image/jpeg' : 'video/mp4',
        'byte_size' => strlen($bytes), 'width' => 90, 'height' => 160, 'duration_seconds' => $tipo === 'video' ? 3 : null,
        'sha256' => hash('sha256', $bytes), 'contributor_name' => $quien, 'contributor_message' => null,
        'moderation_status' => $estado, 'consent_version' => cb_album_consent_version(), 'uploader_hmac' => cb_hmac('t', 'album-intake'),
    ]);
    if ($estado === 'approved' && $tipo === 'image') { $esperado['invitados/001-tia-rosa.jpg'] = hash('sha256', $bytes); }
    if ($estado === 'pending') { $esperado['invitados/002-invitado.jpg'] = hash('sha256', $bytes); }
}

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
            'content' => $cuerpo, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0; $ubicacion = ''; $tipo = ''; $largo = -1; $disp = '';
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
            if (stripos($linea, 'Location:') === 0) { $ubicacion = trim(substr($linea, 9)); }
            if (stripos($linea, 'Content-Type:') === 0) { $tipo = trim(substr($linea, 13)); }
            if (stripos($linea, 'Content-Length:') === 0) { $largo = (int) trim(substr($linea, 15)); }
            if (stripos($linea, 'Content-Disposition:') === 0) { $disp = trim(substr($linea, 20)); }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === 'deleted' || $m[2] === '') { unset($this->cookies[$m[1]]); } else { $this->cookies[$m[1]] = $m[2]; }
            }
        }
        return ['estado' => $estado, 'ubicacion' => $ubicacion, 'tipo' => $tipo, 'largo' => $largo, 'disp' => $disp, 'html' => $html];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}
/** Abre el ZIP recibido y devuelve [nombre => sha256], o lanza si está roto. */
function leer_zip(string $bytes, string $tmp): array
{
    $f = $tmp . '/recibido-' . bin2hex(random_bytes(3)) . '.zip';
    file_put_contents($f, $bytes);
    $z = new ZipArchive();
    $abrio = $z->open($f, ZipArchive::CHECKCONS);
    if ($abrio !== true) { throw new RuntimeException('FAIL: el ZIP no abre (código ' . $abrio . ')'); }
    $out = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        $contenido = $z->getFromIndex($i);
        if ($contenido === false) { throw new RuntimeException('FAIL: no se puede leer ' . $n); }
        $out[$n] = hash('sha256', $contenido);
    }
    $z->close();
    return $out;
}

echo "Descargar todas las fotos (ZIP) por HTTP en $base\n";
$anon = new Navegador($base);
$r = $anon->pedir('GET', '/admin/descargar-fotos.php?party=rosa-prueba');
h_check($r['estado'] === 302 && str_starts_with($r['ubicacion'], 'login.php'), 'sin sesión manda al login (' . $r['estado'] . ')');

$luis = new Navegador($base);
$r = $luis->pedir('GET', '/admin/maestro.php');
$r = $luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => $claveMaestra]);
$r = $luis->pedir('GET', '/admin/album.php?party=rosa-prueba');
h_check($r['estado'] === 200 && str_contains($r['html'], 'href="descargar-fotos.php?party=rosa-prueba"') && str_contains($r['html'], 'Descargar todas las fotos (ZIP)'), 'el Álbum trae el botón de descarga');

$r = $luis->pedir('GET', '/admin/descargar-fotos.php?party=rosa-prueba');
h_check($r['estado'] === 200 && $r['tipo'] === 'application/zip', 'responde un ZIP (' . $r['estado'] . ' ' . $r['tipo'] . ')');
h_check($r['largo'] === strlen($r['html']), 'Content-Length es exacto (' . $r['largo'] . ' vs ' . strlen($r['html']) . ')');
h_check(str_contains($r['disp'], 'attachment; filename="fotos-rosa-elena-2026-10-16.zip"'), 'se descarga con nombre legible (' . $r['disp'] . ')');
$recibido = leer_zip($r['html'], $tmp);
ksort($recibido); ksort($esperado);
h_check(array_keys($recibido) === array_keys($esperado), 'trae cabina sin la papelera e invitados sin lo oculto ni videos: ' . implode(', ', array_keys($recibido)));
h_check($recibido === $esperado, 'cada foto llega idéntica, byte a byte');

$r = $luis->pedir('GET', '/admin/descargar-fotos.php?party=sin-fotos');
h_check($r['estado'] === 404 && str_contains($r['html'], 'todavía no tiene fotos'), 'sin fotos: 404 con texto, no un ZIP vacío');
$r = $luis->pedir('GET', '/admin/descargar-fotos.php?party=..%2F..%2Fetc');
h_check($r['estado'] === 404 && $r['tipo'] !== 'application/zip', 'un slug inválido no descarga nada');

// ── Un operador con solo «Fotos del kiosco» ────────────────────────────────
$r = $luis->pedir('GET', '/admin/usuarios.php');
$r = $luis->pedir('POST', '/admin/usuarios.php', ['csrf' => $luis->csrf($r['html']), 'action' => 'crear', 'nombre' => 'Carla', 'correo' => 'carla@example.test',
    'rol' => 'operador', 'fiestas' => ['rosa-prueba'], 'modulos' => ['fotos']]);
$r = $luis->pedir('GET', '/admin/usuarios.php');
h_check(preg_match('/<span class="temporal">([A-Za-z0-9-]+)<\/span>/', $r['html'], $m) === 1, 'operadora creada (temporal en pantalla)');
$temporal = $m[1];
$carla = new Navegador($base);
$r = $carla->pedir('GET', '/admin/login.php');
$r = $carla->pedir('POST', '/admin/login.php', ['csrf' => $carla->csrf($r['html']), 'correo' => 'carla@example.test', 'password' => $temporal]);
$r = $carla->pedir('GET', '/admin/perfil.php?cambiar=1');
$r = $carla->pedir('POST', '/admin/perfil.php', ['csrf' => $carla->csrf($r['html']), 'action' => 'clave', 'actual' => $temporal, 'nueva' => 'fotos-de-rosa-1', 'repetida' => 'fotos-de-rosa-1']);
h_check($r['estado'] === 302, 'la operadora cambia su contraseña');
$r = $carla->pedir('GET', '/admin/descargar-fotos.php?party=rosa-prueba');
h_check($r['estado'] === 200 && $r['tipo'] === 'application/zip', 'la operadora descarga su fiesta');
$soloCabina = leer_zip($r['html'], $tmp);
h_check(array_keys($soloCabina) === ['cabina/001-2026-10-16_2301.jpg', 'cabina/002-2026-10-16_2302.jpg'], 'sin el módulo Álbum recibe solo cabina/: ' . implode(', ', array_keys($soloCabina)));
$r = $carla->pedir('GET', '/admin/descargar-fotos.php?party=otra-fiesta');
h_check($r['estado'] === 403 && $r['tipo'] !== 'application/zip', 'una fiesta que no es suya: 403 (' . $r['estado'] . ')');

echo "OK ($tests comprobaciones)\n";
