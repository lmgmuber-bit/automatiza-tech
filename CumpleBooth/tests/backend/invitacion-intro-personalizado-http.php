<?php
/**
 * Intro propio de una fiesta, por HTTP.
 *
 * El video con el nombre de la cumpleañera es `themes/<tema>/invitation/intro-fiesta-<slug>.mp4`: vive solo
 * en el servidor (.gitignore), como la despedida propia de una fiesta, y el servidor web lo entrega con Range
 * como cualquier video. Si existe, reemplaza el intro genérico y no se repite en la portada; si no, todo
 * queda como antes. Base SQLite temporal y `php -S` propio; los datos son inventados. La prueba crea el
 * archivo de la fiesta dentro de `public/themes/spidey/invitation/` y lo borra al terminar.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-intro-personalizado-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 19500 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('f', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once $raiz . '/public/lib.invitations.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$tests = 0;
function h_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// Las tres fiestas van en UNA llamada: cb_save_parties guarda la lista completa y borra las que no vienen.
// `spidey` no tiene lámina base, así que su portada muestra la imagen y el video (la rama que cambia);
// `familia-canina` sí tiene intro genérico, que es lo que debe quedar cuando la fiesta no trae uno propio.
$fiesta = static fn(string $tema): array => ['nombre' => 'Niña Prueba', 'tema' => $tema, 'event_type' => 'child_birthday',
    'fecha' => '2026-12-06', 'activa' => true, 'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s')];
h_check(cb_save_parties(['parties' => ['spidey-con-intro' => $fiesta('spidey'), 'canina-sin-intro' => $fiesta('familia-canina'),
    'spidey-video-sin-intro' => $fiesta('spidey'), 'casa-con-voces' => $fiesta('casa-munecas')]]), 'fiestas de prueba creadas');

/** Invitación publicada de una fiesta ya creada; con $conVideo agrega el output personalized_video aprobado. Devuelve el token. */
function invitacion(PDO $pdo, string $slug, string $tema, string $tipo, bool $conVideo): string
{
    h_check(cb_party_db_id($slug) !== null, 'la fiesta ' . $slug . ' existe');
    $token = cb_opaque_token(16);
    $ahora = gmdate('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO cc_invitations (public_token_hash,party_id,theme_slug,admin_label,birthday_person_name,
        event_type,event_date,event_time,address,message,language,channel,status,visual_version,created_at,updated_at,created_by,published_at,published_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([cb_hash_token($token), (int) cb_party_db_id($slug), $tema, 'Prueba', 'Niña Prueba', $tipo, '2026-12-06', '15:00',
            'Calle Ficticia 123', 'Trae algo para jugar', 'es', 'link', 'published', 1, $ahora, $ahora, 'test', $ahora, 'test']);
    $invId = (int) $pdo->lastInsertId();
    $salidas = [['personalized_image', 'personalized-image', 'jpg', 'image/jpeg']];
    if ($conVideo) { $salidas[] = ['personalized_video', 'personalized-video', 'mp4', 'video/mp4']; }
    foreach ($salidas as [$tipoSalida, $asset, $ext, $mime]) {
        $key = cb_invitation_storage_key($slug, $asset, 1, $ext);
        $ruta = cb_invitation_file_path($key);
        if (!is_dir(dirname($ruta))) { mkdir(dirname($ruta), 0770, true); }
        file_put_contents($ruta, 'contenido de prueba ' . $asset);
        $r = cb_save_invitation_output($invId, ['output_type' => $tipoSalida, 'asset_key' => $asset, 'file_storage_key' => $key,
            'status' => 'approved', 'file_mime' => $mime, 'file_byte_size' => filesize($ruta), 'file_sha256' => hash_file('sha256', $ruta)]);
        h_check(!empty($r['ok']), 'output ' . $tipoSalida . ' de ' . $slug);
    }
    return $token;
}

$conIntro = invitacion($pdo, 'spidey-con-intro', 'spidey', 'child_birthday', true);
$sinIntro = invitacion($pdo, 'canina-sin-intro', 'familia-canina', 'child_birthday', false);
$conVideoSinIntro = invitacion($pdo, 'spidey-video-sin-intro', 'spidey', 'child_birthday', true);
$casaConVoces = invitacion($pdo, 'casa-con-voces', 'casa-munecas', 'child_birthday', false);

// El intro propio de la primera fiesta, con el slug real que le dio la base (lleva 96 bits al azar).
$slugConIntro = (string) $pdo->query("SELECT public_slug FROM cc_parties WHERE id=" . (int) cb_party_db_id('spidey-con-intro'))->fetchColumn();
$carpetaIntro = $raiz . '/public/themes/spidey/invitation';
$creoCarpeta = !is_dir($carpetaIntro);
if ($creoCarpeta) { mkdir($carpetaIntro, 0775, true); }
$introFiesta = $carpetaIntro . '/intro-fiesta-' . $slugConIntro . '.mp4';
$posterFiesta = $carpetaIntro . '/intro-fiesta-' . $slugConIntro . '-poster.jpg';
file_put_contents($introFiesta, 'video de prueba');
file_put_contents($posterFiesta, 'poster de prueba');

$servidor = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $raiz . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/servidor.log', 'a'], 2 => ['file', $tmp . '/servidor.log', 'a']], $pipes, $raiz . '/public', $env);
register_shutdown_function(static function () use ($servidor, $tmp, $introFiesta, $posterFiesta, $carpetaIntro, $creoCarpeta): void {
    @unlink($introFiesta);
    @unlink($posterFiesta);
    if ($creoCarpeta) { @rmdir($carpetaIntro); }
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

function pagina(string $base, string $token): string
{
    $html = @file_get_contents($base . '/invitacion.php?t=' . urlencode($token), false,
        stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]));
    h_check(is_string($html) && str_contains($html, '<main class="inv">'), 'la invitación carga');
    return (string) $html;
}

/** El src del <video> del intro, o null si la página no trae intro. */
function srcIntro(string $html): ?string
{
    return preg_match("/data-inv-theme-intro-video src='([^']*)'/", $html, $m) ? html_entity_decode($m[1]) : null;
}

try {
    // (a) La fiesta tiene su intro: reemplaza el genérico, con su póster, y no se repite en la portada.
    $html = pagina($base, $conIntro);
    $src = (string) srcIntro($html);
    h_check(str_starts_with($src, 'themes/spidey/invitation/intro-fiesta-' . $slugConIntro . '.mp4?v='),
        'el intro es el de la fiesta (src=' . var_export($src, true) . ')');
    h_check(str_contains($html, "poster='themes/spidey/invitation/intro-fiesta-" . $slugConIntro . "-poster.jpg?v="), 'con su póster');
    h_check(!str_contains($html, 'data-inv-video-final'), 'la portada no repite el video');
    h_check(str_contains($html, 'data-inv-theme-intro-personal'), 'el intro propio va marcado (suena aunque se pida reducir movimiento)');
    h_check(str_contains($html, 'Descargar video'), 'la familia puede seguir bajando el video');

    // (b) Otra fiesta de la misma temática, sin intro propio: el genérico de siempre, nunca vacío.
    $html = pagina($base, $sinIntro);
    h_check(str_starts_with((string) srcIntro($html), 'themes/familia-canina/invitation/intro-invitacion-wow-v1.mp4?v='), 'sin intro propio: el genérico');
    h_check(!str_contains($html, "src=''"), 'nunca un src vacío');
    h_check(!str_contains($html, 'data-inv-theme-intro-personal'), 'el intro genérico no va marcado como propio');

    // (c) Con video aprobado pero sin intro propio: igual que antes, el video sigue en la portada.
    $html = pagina($base, $conVideoSinIntro);
    h_check(str_contains($html, 'data-inv-video-final'), 'sin intro propio el video sigue en la portada');
    h_check(srcIntro($html) === null, 'y sin intro, porque spidey no tiene genérico');

    // (d) La vista previa para WhatsApp (og:image) lleva la versión de la imagen aprobada. Esa respuesta sale
    //     `public, max-age=86400` y el CDN la guarda un día; WhatsApp guarda la suya por dirección. Sin la versión,
    //     corregir la lámina dejaba la vieja en toda tarjeta compartida (30-oct-2026, invitación Casa de Muñecas).
    $ogImagen = static fn(string $html): string
        => preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $m) ? html_entity_decode($m[1]) : '';
    $bajar = static fn(string $url): string
        => (string) @file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]));
    $img = cb_invitation_approved_outputs((int) cb_load_invitation_by_public_token($conIntro)['id'], 'personalized_image')[0];
    $og1 = $ogImagen(pagina($base, $conIntro));
    h_check(str_contains($og1, '&preview=1&v=' . substr((string) $img['file_sha256'], 0, 12)),
        'la vista previa lleva la versión de la imagen (og:image=' . var_export($og1, true) . ')');
    h_check($bajar($og1) === 'contenido de prueba personalized-image', 'y con la versión sigue entregando la imagen');
    $ruta = cb_invitation_file_path((string) $img['file_storage_key']);
    file_put_contents($ruta, 'lámina corregida');
    $pdo->prepare('UPDATE cc_invitation_outputs SET file_sha256=?, file_byte_size=? WHERE id=?')
        ->execute([hash_file('sha256', $ruta), filesize($ruta), (int) $img['id']]);
    $og2 = $ogImagen(pagina($base, $conIntro));
    h_check($og2 !== $og1 && str_contains($og2, '&v=' . substr(hash_file('sha256', $ruta), 0, 12)),
        'corregir la lámina cambia la dirección de la vista previa');
    h_check($bajar($og2) === 'lámina corregida', 'y la dirección nueva entrega la lámina corregida');

    // (e) Toda voz de narracion-video/ lleva ?v=: los estáticos de las temáticas salen con 30 días de caché, y la voz
    //     de un capítulo (la despedida de Casa de Muñecas, 30-oct) se reemplaza con el mismo nombre.
    $html = pagina($base, $casaConVoces);
    preg_match_all('#narracion-video(?:/|\\\\/)[A-Za-z0-9._%-]+\.mp3(\?v=)?#', $html, $voces, PREG_SET_ORDER);
    $sinVersion = array_values(array_filter($voces, static fn(array $v): bool => ($v[1] ?? '') === ''));
    h_check(str_contains($html, 'despedida-casa-munecas.mp3'), 'la despedida de Casa de Muñecas trae su voz');
    h_check(count($voces) >= 4 && $sinVersion === [],
        'las ' . count($voces) . ' voces llevan versión (sin ?v=: ' . implode(', ', array_column($sinVersion, 0)) . ')');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
echo "invitacion-intro-personalizado-http: $tests checks OK" . PHP_EOL;
