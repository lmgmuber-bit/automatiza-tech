<?php
/**
 * Confirmar asistencia con regalo validado (gift_mode = 'rsvp'), de punta a punta por HTTP:
 * nombre + regalo + equipo + foto de bebé opcional, y lo que ven los papás y el admin.
 * Base SQLite temporal y servidor `php -S` propio; los datos son inventados.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-rsvp-regalo-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 19300 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('e', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_ADMIN_PASSWORD_HASH' => password_hash('clave-maestra-1234', PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once $raiz . '/public/lib.rsvp.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$tests = 0;
function h_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── Fiesta de baby shower inventada, con su invitación publicada en modo rsvp ──
$slug = 'bebe-de-prueba';
h_check(cb_save_parties(['parties' => [$slug => [
    'nombre' => 'Bebé Prueba', 'tema' => 'baby-nube', 'event_type' => 'baby_shower', 'fecha' => '2026-12-05', 'activa' => true,
    'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'),
]]]), 'fiesta de prueba creada');
$partyId = (int) cb_party_db_id($slug);
$token = cb_opaque_token(16);
$ahora = gmdate('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO cc_invitations (public_token_hash,party_id,theme_slug,admin_label,birthday_person_name,
    event_type,event_date,event_time,address,message,language,channel,status,visual_version,created_at,updated_at,created_by,published_at,published_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([cb_hash_token($token), $partyId, 'baby-nube', 'Prueba', 'Bebé Prueba', 'baby_shower', '2026-12-05', '16:00',
        'Calle Ficticia 123', '', 'es', 'link', 'published', 1, $ahora, $ahora, 'test', $ahora, 'test']);
$invId = (int) $pdo->lastInsertId();
$pdo->prepare("UPDATE cc_invitations SET gift_mode='rsvp' WHERE id=?")->execute([$invId]);

// ── Servidor (límites de subida de PROD, no los de fábrica de php -S) ──────
$servidor = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=20M', '-d', 'post_max_size=24M', '-d', 'memory_limit=512M',
    '-S', '127.0.0.1:' . $puerto, '-t', $raiz . '/public'],
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

/** Un "navegador" mínimo: GET, POST de formulario, multipart y JSON. */
function pedir(string $base, string $metodo, string $ruta, ?array $datos = null, array $archivos = [], bool $json = false, array $cookies = []): array
{
    $cab = [];
    $cuerpo = '';
    if ($cookies) { $cab[] = 'Cookie: ' . implode('; ', array_map(static fn($k, $v) => $k . '=' . $v, array_keys($cookies), $cookies)); }
    if ($metodo === 'POST') {
        if ($json) {
            $cuerpo = json_encode($datos);
            $cab[] = 'Content-Type: application/json';
        } elseif ($archivos) {
            $lim = '----rsvp' . bin2hex(random_bytes(8));
            foreach ((array) $datos as $k => $v) { $cuerpo .= "--$lim\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n"; }
            foreach ($archivos as $campo => [$nombre, $mime, $bytes]) {
                $cuerpo .= "--$lim\r\nContent-Disposition: form-data; name=\"$campo\"; filename=\"$nombre\"\r\nContent-Type: $mime\r\n\r\n$bytes\r\n";
            }
            $cuerpo .= "--$lim--\r\n";
            $cab[] = 'Content-Type: multipart/form-data; boundary=' . $lim;
        } else {
            $cuerpo = http_build_query((array) $datos);
            $cab[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $cab[] = 'Content-Length: ' . strlen($cuerpo);
    }
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cab), 'content' => $cuerpo,
        'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 60]]);
    $html = (string) @file_get_contents($base . $ruta, false, $ctx);
    $estado = 0; $loc = ''; $setc = [];
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $l, $m)) { $estado = (int) $m[1]; }
        if (stripos($l, 'Location:') === 0) { $loc = trim(substr($l, 9)); }
        if (stripos($l, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $l, $m)) { $setc[$m[1]] = $m[2]; }
    }
    $j = json_decode($html, true);
    return ['estado' => $estado, 'ubicacion' => $loc, 'html' => $html, 'json' => is_array($j) ? $j : null, 'cookies' => $setc];
}
/** multipart aunque no haya archivo (el formulario real siempre es multipart). */
function confirmar_form(string $base, string $token, array $campos, array $archivos = []): array
{
    if (!$archivos) { $archivos = ['relleno' => ['x.txt', 'text/plain', 'x']]; }
    return pedir($base, 'POST', '/rsvp-api.php', ['t' => $token] + $campos, $archivos);
}
function jpeg_ruido(int $w, int $h, int $calidad): string
{
    // Ruido real (alta entropía) para que pese como una foto de teléfono: baldosa de ruido repetida.
    $tile = imagecreatetruecolor(256, 256);
    for ($x = 0; $x < 256; $x++) {
        for ($y = 0; $y < 256; $y++) {
            imagesetpixel($tile, $x, $y, (random_int(0, 255) << 16) | (random_int(0, 255) << 8) | random_int(0, 255));
        }
    }
    $im = imagecreatetruecolor($w, $h);
    imagesettile($im, $tile);
    imagefilledrectangle($im, 0, 0, $w, $h, IMG_COLOR_TILED);
    ob_start(); imagejpeg($im, null, $calidad); return (string) ob_get_clean();
}
function fotos_guardadas(string $tmp): array
{
    return glob($tmp . '/photos/rsvp-bebes/*/*.jpg') ?: [];
}

echo "Confirmar con regalo por HTTP en $base\n";

// ── Validaciones, con el mensaje exacto ──
$r = confirmar_form($base, $token, ['family_name' => 'Ana Prueba', 'team' => 'mama']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'regalo_requerido' && $r['json']['mensaje'] === 'Cuéntanos qué regalo llevarás.', 'sin regalo: 422 regalo_requerido');
$r = confirmar_form($base, $token, ['family_name' => 'Ana Prueba', 'gift' => 'un regalito', 'team' => 'mama']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'regalo_generico', 'regalo genérico: 422');
$r = confirmar_form($base, $token, ['family_name' => 'Ana Prueba', 'gift' => 'Coche']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'equipo_requerido' && $r['json']['mensaje'] === 'Elige Team Mamá o Team Papá.', 'sin equipo: 422 equipo_requerido');
$r = confirmar_form($base, $token, ['family_name' => '', 'gift' => 'Coche', 'team' => 'mama']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'nombre_requerido', 'sin nombre: 422 nombre_requerido');
h_check((int) $pdo->query('SELECT COUNT(*) FROM cc_rsvps')->fetchColumn() === 0, 'los rechazos no dejan confirmaciones');

// ── Coche: el primero pasa, el segundo con otro nombre y otra forma de decirlo, no ──
$r = confirmar_form($base, $token, ['family_name' => 'Ana Prueba', 'gift' => 'Coche', 'team' => 'mama']);
h_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true && $r['json']['regalo'] === 'Coche', 'coche pasa (' . $r['estado'] . ')');
$r = confirmar_form($base, $token, ['family_name' => 'Beto Prueba', 'gift' => 'coche de paseo', 'team' => 'papa']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'regalo_tomado' && $r['json']['mensaje'] === 'Ese regalo ya lo trae alguien, elige otro.', 'coche repetido: 422 regalo_tomado');
h_check((int) $pdo->query("SELECT COUNT(*) FROM cc_gift_items WHERE invitation_id=$invId")->fetchColumn() === 1, 'el rechazo no crea regalo');

// ── Pañales: 3 sí, el cuarto no; body las veces que sea ──
foreach (['Carla', 'Diego', 'Elena'] as $n) {
    $r = confirmar_form($base, $token, ['family_name' => "$n Prueba", 'gift' => 'Pañales talla RN', 'team' => 'mama']);
    h_check($r['estado'] === 200, "pañales de $n pasan");
}
$r = confirmar_form($base, $token, ['family_name' => 'Fabi Prueba', 'gift' => 'pañales', 'team' => 'papa']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'panales_completos' && $r['json']['mensaje'] === 'Ya hay 3 personas que llevan pañales. Elige otro regalo, por favor.', 'cuarto pañal: panales_completos');
foreach (['Gaby', 'Hugo'] as $n) {
    $r = confirmar_form($base, $token, ['family_name' => "$n Prueba", 'gift' => 'body', 'team' => 'papa']);
    h_check($r['estado'] === 200, "body de $n pasa");
}

// ── Nombre repetido (sin distinguir mayúsculas) ──
$r = confirmar_form($base, $token, ['family_name' => 'ANA prueba', 'gift' => 'cuna', 'team' => 'mama']);
h_check($r['estado'] === 422 && $r['json']['error'] === 'nombre_repetido'
    && $r['json']['mensaje'] === 'Ya hay una confirmación con ese nombre. Agrega tu apellido, por favor.', 'nombre repetido: 422');

// ── Fotos ──
$antes = count(fotos_guardadas($tmp));
$grande = jpeg_ruido(3000, 2200, 95);
h_check(strlen($grande) > 3000000, 'la foto de prueba pesa más de 3 MB (' . strlen($grande) . ' bytes)');
$r = confirmar_form($base, $token, ['family_name' => 'Ines Prueba', 'gift' => 'cuna', 'team' => 'mama'], ['baby_photo' => ['bebe.jpg', 'image/jpeg', $grande]]);
h_check($r['estado'] === 200, 'foto grande de teléfono: se acepta (' . $r['estado'] . ' ' . substr($r['html'], 0, 120) . ')');
$fotos = fotos_guardadas($tmp);
h_check(count($fotos) === $antes + 1, 'la foto quedó guardada');
$nueva = array_values(array_diff($fotos, [])); $ruta = end($nueva);
$dim = getimagesize($ruta);
h_check($dim[2] === IMAGETYPE_JPEG && max($dim[0], $dim[1]) <= 1600, 'se reencodó a JPEG de máx. 1600 px (' . $dim[0] . 'x' . $dim[1] . ')');
h_check(!str_starts_with(realpath($ruta), realpath($raiz . '/public')), 'la foto vive fuera del webroot');
h_check(preg_match('#/photos/rsvp-bebes/' . $partyId . '/[a-f0-9]{32}\.jpg$#', str_replace('\\', '/', $ruta)) === 1, 'ruta rsvp-bebes/<party>/<hex>.jpg');

$im = imagecreatetruecolor(50, 50); imagesavealpha($im, true); imagealphablending($im, false);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 100));
ob_start(); imagepng($im); $png = (string) ob_get_clean();
$r = confirmar_form($base, $token, ['family_name' => 'Julia Prueba', 'gift' => 'monitor', 'team' => 'papa'], ['baby_photo' => ['b.png', 'image/png', $png]]);
h_check($r['estado'] === 200, 'PNG con transparencia: se acepta');
$antes = count(fotos_guardadas($tmp));

$r = confirmar_form($base, $token, ['family_name' => 'Kevin Prueba', 'gift' => 'trona', 'team' => 'papa'], ['baby_photo' => ['b.jpg', 'image/jpeg', "%PDF-1.4 esto no es una foto\n"]]);
h_check($r['estado'] === 422 && $r['json']['error'] === 'foto_invalida'
    && $r['json']['mensaje'] === 'No pudimos usar esa foto. Prueba con otra o confirma sin foto.', 'PDF disfrazado de JPEG: foto_invalida');
h_check((int) $pdo->query("SELECT COUNT(*) FROM cc_rsvps WHERE family_name='Kevin Prueba'")->fetchColumn() === 0, 'la foto inválida no deja confirmación');
h_check((int) $pdo->query("SELECT COUNT(*) FROM cc_gift_items WHERE title='trona'")->fetchColumn() === 0, 'ni regalo');
h_check(count(fotos_guardadas($tmp)) === $antes, 'ni archivo');
// Con el mismo regalo todavía libre, el reintento sin foto funciona (el formulario conserva lo escrito).
$r = confirmar_form($base, $token, ['family_name' => 'Kevin Prueba', 'gift' => 'trona', 'team' => 'papa']);
h_check($r['estado'] === 200, 'reintentar sin foto funciona');

// ── Lo que ven los invitados y los papás ──
$lista = pedir($base, 'POST', '/gift-api.php', ['t' => $token, 'accion' => 'listar', 'visitante' => ''], [], true);
h_check($lista['estado'] === 200 && count($lista['json']['lista']['items']) >= 5, 'listar devuelve los regalos');
h_check(!str_contains($lista['html'], 'Ana Prueba') && !str_contains($lista['html'], 'Carla'), 'cb_gift_list_public no trae nombres');
$publico = pedir($base, 'GET', '/invitacion.php?t=' . $token);
h_check(!str_contains($publico['html'], 'Ana Prueba') && !str_contains($publico['html'], 'Carla Prueba'), 'el HTML de la invitación no trae los nombres de quien confirma');

$resumen = cb_rsvp_resumen($slug);
$ana = array_values(array_filter($resumen['lista'], static fn($f) => $f['familia'] === 'Ana Prueba'))[0] ?? null;
h_check($ana !== null && $ana['equipo'] === 'mama' && $ana['regalo'] === 'Coche' && $ana['foto'] === false && $ana['id'] > 0, 'cb_rsvp_resumen trae equipo y regalo');
$ines = array_values(array_filter($resumen['lista'], static fn($f) => $f['familia'] === 'Ines Prueba'))[0] ?? null;
h_check($ines !== null && $ines['foto'] === true, 'cb_rsvp_resumen marca la foto de bebé');
h_check($resumen['team_mama'] + $resumen['team_papa'] === $resumen['familias'], 'el marcador de equipos suma todas las confirmaciones');
$l = cb_rsvp_list($partyId);
h_check(isset($l[0]['team'], $l[0]['gift_title'], $l[0]['has_photo']), 'cb_rsvp_list trae team, gift_title y has_photo');

// ── gift-api en modo rsvp: solo lectura; modo list sigue como antes ──
$r = pedir($base, 'POST', '/gift-api.php', ['t' => $token, 'accion' => 'agregar', 'titulo' => 'algo', 'visitante' => ''], [], true);
h_check($r['estado'] === 409 && $r['json']['error'] === 'modo_confirmacion', 'agregar en modo rsvp: 409 modo_confirmacion');
$r = pedir($base, 'POST', '/gift-api.php', ['t' => $token, 'accion' => 'reservar', 'id' => 1, 'nombre' => 'X', 'visitante' => ''], [], true);
h_check($r['estado'] === 409 && $r['json']['error'] === 'modo_confirmacion', 'reservar en modo rsvp: 409');
h_check(cb_gift_set_mode($invId, 'list')['error'] === 'modo_fijo', 'cb_gift_set_mode no saca una invitación del modo rsvp');
h_check(cb_gift_set_mode($invId, 'rsvp')['error'] === 'modo_invalido', 'cb_gift_set_mode nunca pone rsvp');
h_check(cb_gift_mode(['gift_mode' => 'rsvp']) === 'rsvp' && cb_gift_mode(['gift_mode' => 'raro']) === 'list', 'cb_gift_mode reconoce rsvp');

// Una segunda invitación en modo list confirma por JSON como siempre.
$token2 = cb_opaque_token(16);
$pdo->prepare('INSERT INTO cc_invitations (public_token_hash,party_id,theme_slug,admin_label,birthday_person_name,
    event_type,event_date,event_time,address,message,language,channel,status,visual_version,created_at,updated_at,created_by,published_at,published_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([cb_hash_token($token2), $partyId, 'baby-nube', 'Lista', 'Bebé Prueba', 'baby_shower', '2026-12-05', '16:00',
        'Calle Ficticia 123', '', 'es', 'link', 'published', 1, $ahora, $ahora, 'test', $ahora, 'test']);
$r = pedir($base, 'POST', '/rsvp-api.php', ['t' => $token2, 'family_name' => 'Mario Clasico', 'guest_names' => ''], [], true);
h_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true && !isset($r['json']['regalo']), 'invitación en modo list: confirma por JSON como antes');
h_check((int) $pdo->query("SELECT COUNT(*) FROM cc_rsvps WHERE family_name='Mario Clasico' AND team IS NULL AND gift_item_id IS NULL")->fetchColumn() === 1, 'sin equipo ni regalo en modo list');

// ── Ocultar un regalo tomado en modo rsvp lo libera ──
$cocheId = (int) $pdo->query("SELECT id FROM cc_gift_items WHERE title='Coche'")->fetchColumn();
h_check(cb_gift_set_hidden($invId, $cocheId, true)['ok'] === true, 'los papás pueden ocultar un regalo tomado en modo rsvp');
$r = confirmar_form($base, $token, ['family_name' => 'Lucia Prueba', 'gift' => 'carrito', 'team' => 'mama']);
h_check($r['estado'] === 200, 'el coche oculto quedó libre para otro invitado');

// ── Cuatro intentos seguidos por el mismo regalo: solo uno gana. (php -S atiende de a uno: el bloqueo FOR UPDATE de MySQL no se puede ejercitar aquí.) ──
$pdo->prepare("DELETE FROM cc_gift_items WHERE title IN ('cuna')")->execute();
$procesos = [];
foreach (['Mati', 'Nico', 'Omar', 'Pablo'] as $n) {
    $lim = '----c' . bin2hex(random_bytes(6));
    $cuerpo = '';
    foreach (['t' => $token, 'family_name' => "$n Carrera", 'gift' => 'extractor de leche', 'team' => 'papa'] as $k => $v) {
        $cuerpo .= "--$lim\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
    }
    $cuerpo .= "--$lim--\r\n";
    $procesos[] = ['n' => $n, 'cuerpo' => $cuerpo, 'lim' => $lim];
}
$ok = 0;
foreach ($procesos as $p) {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: multipart/form-data; boundary={$p['lim']}\r\nContent-Length: " . strlen($p['cuerpo']),
        'content' => $p['cuerpo'], 'ignore_errors' => true, 'timeout' => 30]]);
    $resp = json_decode((string) file_get_contents($base . '/rsvp-api.php', false, $ctx), true);
    $ok += !empty($resp['ok']) ? 1 : 0;
}
h_check($ok === 1, 'cuatro intentos seguidos por el mismo extractor: exactamente uno queda con él (' . $ok . ')');
h_check((int) $pdo->query("SELECT COUNT(*) FROM cc_gift_items WHERE invitation_id=$invId AND title='extractor de leche' AND moderation_status='approved'")->fetchColumn() === 1, 'un solo regalo extractor aprobado en la base');

echo "OK $tests checks rsvp-regalo-http\n";
