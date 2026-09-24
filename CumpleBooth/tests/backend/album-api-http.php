<?php
/**
 * La música de fondo del Álbum Recuerdo, por HTTP (2026-09-17).
 *
 * Luis pidió que la pista del álbum (el saxo de la fiesta de Samantha) suene
 * también en el enlace que reciben los papás. La revista la toma de
 * `theme.assets.musica`, que album-api.php publica solo si la temática trae
 * `themes/<tema>/musica-album.mp3`. Esta prueba levanta `php -S` con una
 * SQLite temporal y comprueba lo que la revista necesita:
 *
 *  - con PIN pendiente, la respuesta `pin_required` YA trae la pista (el
 *    formulario del PIN la destraba en el mismo toque de "Abrir el álbum");
 *  - canjeado el PIN, el álbum completo la trae igual;
 *  - un PIN malo sigue contestando 403 bad_pin;
 *  - el archivo se sirve entero y como audio;
 *  - una temática sin pista (spidey) no publica la clave y nada cambia.
 *
 * Nunca toca una base real: la SQLite vive en una carpeta temporal que se borra al final.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-album-api-http-' . bin2hex(random_bytes(4));
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
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$tests = 0;
function m_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

$pista = $raiz . '/public/themes/hielo/musica-album.mp3';
m_check(is_file($pista) && filesize($pista) > 1000000, 'la pista de hielo está en el repositorio (' . $pista . ')');
m_check(!is_file($raiz . '/public/themes/spidey/musica-album.mp3'), 'spidey no trae pista (la prueba lo usa como temática sin música)');

// ── Dos fiestas: hielo con PIN y música, spidey sin PIN y sin música ────────
m_check(cb_save_parties(['parties' => [
    'samantha-hielo' => [
        'nombre' => 'Samantha', 'tema' => 'hielo', 'fecha' => '2026-09-13', 'activa' => true,
        'galeriaPin' => '1234', 'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'),
    ],
    'luciano-spidey' => [
        'nombre' => 'Luciano', 'tema' => 'spidey', 'fecha' => '2026-09-13', 'activa' => true,
        'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'),
    ],
]]), 'fiestas de prueba creadas');

$albumHielo = cb_album_ensure(cb_party_db_id('samantha-hielo'));
cb_album_update((int) $albumHielo['id'], ['status' => 'published', 'require_pin' => 1]);
$tokenHielo = cb_album_issue_token((int) $albumHielo['id'], 'view', null, 'test');
$albumSpidey = cb_album_ensure(cb_party_db_id('luciano-spidey'));
cb_album_update((int) $albumSpidey['id'], ['status' => 'published', 'require_pin' => 0]);
$tokenSpidey = cb_album_issue_token((int) $albumSpidey['id'], 'view', null, 'test');
m_check(preg_match('/^[a-f0-9]{32}$/', $tokenHielo) === 1 && preg_match('/^[a-f0-9]{32}$/', $tokenSpidey) === 1, 'tokens de lectura emitidos');

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

/** GET o POST (formulario clásico), como los hace la revista. */
function pedir(string $metodo, string $ruta, array $campos = []): array
{
    global $base;
    $cabeceras = [];
    $cuerpo = '';
    if ($metodo === 'POST') {
        $cuerpo = http_build_query($campos);
        $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
        $cabeceras[] = 'Content-Length: ' . strlen($cuerpo);
    }
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cabeceras),
        'content' => $cuerpo, 'ignore_errors' => true, 'timeout' => 30]]);
    $crudo = (string) @file_get_contents($base . $ruta, false, $ctx);
    $estado = 0;
    $tipo = '';
    foreach ($http_response_header ?? [] as $linea) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
        if (preg_match('/^Content-Type:\s*(.+)$/i', $linea, $m)) { $tipo = trim($m[1]); }
    }
    $json = json_decode($crudo, true);
    return ['estado' => $estado, 'tipo' => $tipo, 'crudo' => $crudo, 'json' => is_array($json) ? $json : null];
}

echo "Música del Álbum Recuerdo por HTTP en $base\n";

// ── Hielo: con el PIN pendiente la pista ya viaja ───────────────────────────
$r = pedir('GET', '/album-api.php?t=' . $tokenHielo);
m_check($r['estado'] === 200 && ($r['json']['error'] ?? '') === 'pin_required', 'álbum con PIN: 200 pin_required (' . $r['estado'] . ')');
$assets = $r['json']['theme']['assets'] ?? [];
m_check(($assets['musica'] ?? '') === 'themes/hielo/musica-album.mp3', 'pin_required trae theme.assets.musica');
m_check(($assets['banner'] ?? '') === 'themes/hielo/fondo-banner.jpg', 'y los assets de siempre siguen ahí');
m_check(!isset($r['json']['media']), 'pero ningún material antes del PIN');

// ── Hielo: PIN malo y PIN bueno ─────────────────────────────────────────────
$r = pedir('POST', '/album-api.php', ['t' => $tokenHielo, 'pin' => '9999']);
m_check($r['estado'] === 403 && ($r['json']['error'] ?? '') === 'bad_pin', 'PIN malo: 403 bad_pin (' . $r['estado'] . ')');
$r = pedir('POST', '/album-api.php', ['t' => $tokenHielo, 'pin' => '1234']);
m_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true, 'PIN bueno: 200 ok (' . $r['estado'] . ' ' . substr($r['crudo'], 0, 120) . ')');
m_check(($r['json']['theme']['assets']['musica'] ?? '') === 'themes/hielo/musica-album.mp3', 'el álbum completo trae la misma pista');
m_check(($r['json']['theme']['slug'] ?? '') === 'hielo', 'de la temática de la fiesta');

// ── La pista se sirve entera y como audio ───────────────────────────────────
$r = pedir('GET', '/themes/hielo/musica-album.mp3');
m_check($r['estado'] === 200, 'la pista contesta 200 (' . $r['estado'] . ')');
m_check(strlen($r['crudo']) === filesize($pista), 'y llega entera (' . strlen($r['crudo']) . ' de ' . filesize($pista) . ' bytes)');
m_check(str_starts_with(strtolower($r['tipo']), 'audio/'), 'con Content-Type de audio (' . $r['tipo'] . ')');

// ── Spidey: sin pista, sin clave ────────────────────────────────────────────
$r = pedir('GET', '/album-api.php?t=' . $tokenSpidey);
m_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true, 'álbum sin PIN: 200 ok (' . $r['estado'] . ')');
$assets = $r['json']['theme']['assets'] ?? [];
m_check(!array_key_exists('musica', (array) $assets), 'una temática sin pista no publica la clave musica');
m_check(($assets['banner'] ?? '') === 'themes/spidey/fondo-banner.jpg', 'y sus otros assets siguen igual');

// ── Spidey: una fiesta con su propia pista (musica-album-<slug>.mp3) ────────
// La pista por fiesta le gana a la de la temática y no exige que la temática tenga una.
$propia = $raiz . '/public/themes/spidey/musica-album-luciano-spidey.mp3';
copy($pista, $propia);
try {
    $r = pedir('GET', '/album-api.php?t=' . $tokenSpidey);
    m_check(($r['json']['theme']['assets']['musica'] ?? '') === 'themes/spidey/musica-album-luciano-spidey.mp3', 'la fiesta con pista propia la publica aunque la temática no tenga');
    $r = pedir('GET', '/album-api.php?t=' . $tokenHielo);
    m_check(($r['json']['theme']['assets']['musica'] ?? '') === 'themes/hielo/musica-album.mp3', 'y la otra fiesta sigue con la de su temática');
} finally {
    @unlink($propia);
}
m_check(!is_file($propia), 'la pista de prueba se borró');

echo "OK $tests checks album-api-http\n";
