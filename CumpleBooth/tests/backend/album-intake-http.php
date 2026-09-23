<?php
/**
 * La subida de recuerdos del invitado, de punta a punta por HTTP (2026-09-15).
 *
 * En las fiestas del 13-sep nadie logró subir nada y nadie se enteró: el panel
 * "¡Gracias!" se veía desde el principio (el CSS pisaba el atributo `hidden`).
 * Esta prueba levanta `php -S` con una SQLite temporal y recorre lo que hace un
 * invitado de verdad:
 *
 *  - subir.php con el token entrega la página con el panel de gracias oculto y
 *    la regla de CSS que lo mantiene oculto;
 *  - album-intake.php rechaza GET, tokens malos, envíos sin consentimiento y
 *    formatos ajenos, con la clave de error que la página sabe traducir;
 *  - una foto real (JPEG generado con GD) queda en disco, con miniatura y como
 *    fila `guest`/`pending` con el nombre del aportante limpio;
 *  - la misma foto otra vez contesta `duplicate` y no repite la fila;
 *  - con el álbum cerrado, la subida y la página contestan 403.
 *
 * Nunca toca una base real: la SQLite vive en una carpeta temporal que se borra al final.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-intake-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 18700 + random_int(0, 200);
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
function i_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── Fiesta, álbum recibiendo y token de aporte ──────────────────────────────
i_check(cb_save_parties(['parties' => ['luciano-spidey' => [
    'nombre' => 'Luciano', 'tema' => 'spidey', 'fecha' => '2026-09-13', 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s'),
]]]), 'fiesta de prueba creada');
$partyId = cb_party_db_id('luciano-spidey');
$album = cb_album_ensure($partyId);
$albumId = (int) $album['id'];
cb_album_update($albumId, ['status' => 'collecting', 'intake_enabled' => 1, 'intake_videos' => 1]);
$token = cb_album_issue_token($albumId, 'intake', null, 'test');
i_check(preg_match('/^[a-f0-9]{32}$/', $token) === 1, 'token de aporte emitido');

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

/** Petición cruda; con $archivos arma multipart/form-data como lo hace el navegador. */
function pedir(string $metodo, string $ruta, array $campos = [], array $archivos = []): array
{
    global $base;
    $cabeceras = [];
    $cuerpo = '';
    if ($metodo === 'POST') {
        $limite = '----cumpleclick' . bin2hex(random_bytes(8));
        foreach ($campos as $k => $v) {
            $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
        }
        foreach ($archivos as $campo => [$nombre, $mime, $bytes]) {
            $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$campo\"; filename=\"$nombre\"\r\nContent-Type: $mime\r\n\r\n$bytes\r\n";
        }
        $cuerpo .= "--$limite--\r\n";
        $cabeceras[] = 'Content-Type: multipart/form-data; boundary=' . $limite;
        $cabeceras[] = 'Content-Length: ' . strlen($cuerpo);
    }
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cabeceras),
        'content' => $cuerpo, 'ignore_errors' => true, 'timeout' => 20]]);
    $html = (string) @file_get_contents($base . $ruta, false, $ctx);
    $estado = 0;
    foreach ($http_response_header ?? [] as $linea) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
    }
    $json = json_decode($html, true);
    return ['estado' => $estado, 'html' => $html, 'json' => is_array($json) ? $json : null];
}

function jpeg(int $ancho, int $alto, int $semilla): string
{
    $im = imagecreatetruecolor($ancho, $alto);
    imagefilledrectangle($im, 0, 0, $ancho, $alto, imagecolorallocate($im, ($semilla * 37) % 255, 120, 200));
    imagefilledellipse($im, (int) ($ancho / 2), (int) ($alto / 2), (int) ($ancho / 2), (int) ($alto / 2), imagecolorallocate($im, 250, 200, 30));
    ob_start(); imagejpeg($im, null, 88); return (string) ob_get_clean();
}

echo "Subida de recuerdos por HTTP en $base\n";

// ── La página del invitado ──────────────────────────────────────────────────
$r = pedir('GET', '/subir.php?t=' . $token);
i_check($r['estado'] === 200 && str_contains($r['html'], 'Enviar mis recuerdos'), 'subir.php con token entrega el formulario (' . $r['estado'] . ')');
i_check(preg_match('/id="panel-done"[^>]*\bhidden\b/', $r['html']) === 1, 'el panel de gracias nace oculto');
i_check(preg_match('/\[hidden\]\s*\{[^}]*display\s*:\s*none\s*!important/i', $r['html']) === 1, 'la página lleva la regla que hace ganar a hidden');
i_check(preg_match('/\.panel\s*\{[^}]*display\s*:\s*flex/', $r['html']) === 1, 'el panel sigue siendo flex (la regla de hidden es la que lo contiene)');
$r = pedir('GET', '/subir.php?t=no-es-hexadecimal');
i_check($r['estado'] === 400, 'subir.php con token mal formado contesta 400 (' . $r['estado'] . ')');
$r = pedir('GET', '/subir.php?t=' . str_repeat('f', 32));
i_check($r['estado'] === 410, 'subir.php con token inexistente contesta 410 (' . $r['estado'] . ')');

// ── Rechazos con clave de error conocida ────────────────────────────────────
$r = pedir('GET', '/album-intake.php');
i_check($r['estado'] === 405 && ($r['json']['error'] ?? '') === 'method_not_allowed', 'GET al endpoint: 405 method_not_allowed');
$foto = jpeg(64, 48, 1);
$r = pedir('POST', '/album-intake.php', ['t' => str_repeat('f', 32), 'consent' => '1'], ['file' => ['foto.jpg', 'image/jpeg', $foto]]);
i_check($r['estado'] === 404 && ($r['json']['error'] ?? '') === 'bad_link', 'token inexistente: 404 bad_link');
$r = pedir('POST', '/album-intake.php', ['t' => $token], ['file' => ['foto.jpg', 'image/jpeg', $foto]]);
i_check($r['estado'] === 400 && ($r['json']['error'] ?? '') === 'consent_required', 'sin consentimiento: 400 consent_required');
$r = pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1']);
i_check($r['estado'] === 400 && ($r['json']['error'] ?? '') === 'no_file', 'sin archivo: 400 no_file');
$r = pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1'], ['file' => ['nota.txt', 'text/plain', "hola\n"]]);
i_check($r['estado'] === 415 && ($r['json']['error'] ?? '') === 'format', 'un .txt: 415 format');

// ── La foto real llega ──────────────────────────────────────────────────────
$r = pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1', 'name' => "  Tía Rosa \n", 'message' => '¡Qué linda fiesta!'],
    ['file' => ['IMG_0001.jpg', 'image/jpeg', $foto]]);
i_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true && ($r['json']['kind'] ?? '') === 'image', 'la foto se acepta: ok, kind=image (' . $r['estado'] . ' ' . substr($r['html'], 0, 120) . ')');
$filas = $pdo->query("SELECT * FROM cc_event_media WHERE album_id=$albumId")->fetchAll();
i_check(count($filas) === 1, 'queda exactamente una fila en cc_event_media');
$fila = $filas[0];
i_check($fila['source'] === 'guest' && $fila['moderation_status'] === 'pending', 'la fila es guest y pending (la revisa el organizador)');
i_check($fila['contributor_name'] === 'Tía Rosa' && $fila['contributor_message'] === '¡Qué linda fiesta!', 'nombre y mensaje quedan limpios');
i_check($fila['original_name'] === 'IMG_0001.jpg' && (int) $fila['width'] === 64 && (int) $fila['height'] === 48, 'nombre original y medidas reales');
$ruta = cb_album_media_path((string) $fila['storage_key']);
i_check($ruta !== null && is_file($ruta) && hash_file('sha256', $ruta) === hash('sha256', $foto), 'el archivo está en disco, byte a byte');
i_check($fila['thumb_storage_key'] !== null && is_file((string) cb_album_media_path((string) $fila['thumb_storage_key'])), 'la miniatura existe');

// ── Repetir la misma foto no la duplica ─────────────────────────────────────
$r = pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1'], ['file' => ['otra-vez.jpg', 'image/jpeg', $foto]]);
i_check($r['estado'] === 200 && ($r['json']['duplicate'] ?? false) === true, 'la misma foto contesta duplicate');
i_check((int) $pdo->query("SELECT COUNT(*) FROM cc_event_media WHERE album_id=$albumId")->fetchColumn() === 1, 'y no agrega otra fila');
$r = pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1'], ['file' => ['segunda.jpg', 'image/jpeg', jpeg(80, 80, 2)]]);
i_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true, 'una segunda foto distinta sí entra');
i_check((int) $pdo->query("SELECT COUNT(*) FROM cc_event_media WHERE album_id=$albumId")->fetchColumn() === 2, 'ahora hay dos filas');

// ── Álbum cerrado ───────────────────────────────────────────────────────────
cb_album_update($albumId, ['status' => 'closed']);
$r = pedir('POST', '/album-intake.php', ['t' => $token, 'consent' => '1'], ['file' => ['tarde.jpg', 'image/jpeg', jpeg(40, 40, 3)]]);
i_check($r['estado'] === 403 && ($r['json']['error'] ?? '') === 'closed', 'con el álbum cerrado: 403 closed');
$r = pedir('GET', '/subir.php?t=' . $token);
i_check($r['estado'] === 403 && str_contains($r['html'], 'cerrada'), 'y la página avisa que la recepción está cerrada');

echo "OK $tests checks album-intake-http\n";
