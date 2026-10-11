<?php
/**
 * Cumpleaños de adulto (AT-CUMPLECLICK-026), de punta a punta por HTTP:
 *
 *  - el admin crea una fiesta «Cumpleaños de adulto» con la temática adulta y guarda la edad
 *    real (antes se recortaba a 17 sin aviso, y «61» se habría perdido);
 *  - el admin NO deja cruzar temática y modalidad: temática adulta en una fiesta infantil, o
 *    un cumpleaños de adulto con temática infantil;
 *  - api.php entrega al kiosco la modalidad, la edad y el bloque `cabinaAdulto`, sin
 *    personajes y sin música (la temática todavía no tiene pista);
 *  - una fiesta infantil sigue igual: sin edad en el payload, sin `cabinaAdulto`, con música;
 *  - una base editada a mano con la temática cruzada no abre el kiosco (409).
 *
 * Datos ficticios. Nunca toca una base real: la SQLite vive en una carpeta temporal.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-adulto-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 19200 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;
$claveMaestra = 'clave-maestra-adulto-1234';

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('e', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_ADMIN_PASSWORD_HASH' => password_hash($claveMaestra, PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
cb_test_migrar_todo(cb_pdo());

$tests = 0;
function a_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── Funciones puras ─────────────────────────────────────────────────────────
a_check(cb_party_event_type('adult_birthday') === 'adult_birthday', 'la modalidad adulta se reconoce');
a_check(cb_party_event_type('baby_shower') === 'baby_shower', 'baby shower sigue igual');
a_check(cb_party_event_type('') === 'child_birthday' && cb_party_event_type('cualquiera') === 'child_birthday', 'lo desconocido cae a infantil');
a_check(cb_party_age_valid(61, 'adult_birthday') && !cb_party_age_valid(61, 'child_birthday'), '61 vale para un adulto, no para un niño');
a_check(cb_party_age_valid(6, 'child_birthday') && !cb_party_age_valid(6, 'adult_birthday'), '6 vale para un niño, no para un adulto');
a_check(!cb_party_age_valid(null, 'adult_birthday') && !cb_party_age_valid('x', 'adult_birthday'), 'sin edad o con texto: nada');
$temas = cb_load_themes()['themes'];
a_check(cb_theme_is_adult($temas['adulto-coqueteria-pop']), 'Coquetería Pop es adulta');
a_check(!cb_theme_is_adult($temas['spidey']) && !cb_theme_is_adult($temas['baby-nube']), 'las infantiles y de baby shower no lo son');
$bloque = cb_theme_cabina_adulto(['frases' => ['<b>x</b>', str_repeat('a', 49)], 'cinta' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.05],
    'panel' => ['x' => 0.1, 'y' => 0.7, 'w' => 0.8, 'h' => 0.2], 'juego' => ['emojis' => ['🍑', 'demasiado-largo']]]);
a_check($bloque !== null && $bloque['frases'] === ['<b>x</b>'], 'las frases largas se descartan (el kiosco las dibuja como texto, nunca HTML)');
a_check($bloque['juego']['emojis'] === ['🍑'], 'solo emojis cortos en el juego');
a_check(cb_theme_cabina_adulto(['frases' => ['x'], 'cinta' => ['x' => 0.9, 'y' => 0.1, 'w' => 0.5, 'h' => 0.05], 'panel' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]]) === null,
    'una cinta fuera del lienzo invalida el bloque');

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
            'content' => $cuerpo, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 15]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0;
        $destino = '';
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
            if (stripos($linea, 'Location:') === 0) { $destino = trim(substr($linea, 9)); }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === 'deleted' || $m[2] === '') { unset($this->cookies[$m[1]]); } else { $this->cookies[$m[1]] = $m[2]; }
            }
        }
        return ['estado' => $estado, 'html' => $html, 'destino' => $destino];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}

echo "Cumpleaños de adulto, por HTTP en $base\n";
$luis = new Navegador($base);
$r = $luis->pedir('GET', '/admin/maestro.php');
$luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => $claveMaestra]);
$r = $luis->pedir('GET', '/admin/index.php?action=nueva');
a_check($r['estado'] === 200 && str_contains($r['html'], 'value="adult_birthday"'), 'el admin ofrece «Cumpleaños de adulto»');
$csrf = $luis->csrf($r['html']);
$ficha = static fn (array $extra) => array_merge(['csrf' => $csrf, 'action' => 'guardar', 'modo' => 'nueva',
    'admin_label' => 'Prueba adulta', 'birthday_person_name' => 'Marta Prueba', 'fecha' => '2026-12-04', 'service_plan' => 'booth',
    // Marco por defecto de la temática, como cuando nadie lo calibra a mano.
    'frame_reset' => '1'], $extra);

// Cruces que el admin no acepta.
$r = $luis->pedir('POST', '/admin/index.php', $ficha(['event_type' => 'child_birthday', 'tema' => 'adulto-coqueteria-pop']));
a_check($r['estado'] === 200 && str_contains($r['html'], 'Esa temática es para adultos'), 'temática adulta en fiesta infantil: rechazada con aviso');
$r = $luis->pedir('POST', '/admin/index.php', $ficha(['event_type' => 'adult_birthday', 'tema' => 'spidey']));
a_check($r['estado'] === 200 && str_contains($r['html'], 'necesita una temática para adultos'), 'cumpleaños de adulto con temática infantil: rechazado con aviso');
a_check(count(cb_load_parties()['parties']) === 0, 'ninguno de los dos cruces se guardó');

// La fiesta buena, con la edad real.
$r = $luis->pedir('POST', '/admin/index.php', $ficha(['event_type' => 'adult_birthday', 'tema' => 'adulto-coqueteria-pop', 'birthday_age' => '61']));
a_check($r['estado'] === 302 && str_contains($r['destino'], 'ok=creada'), 'la fiesta adulta se crea');
$fiestas = cb_load_parties()['parties'];
$slug = array_key_first($fiestas);
a_check(($fiestas[$slug]['event_type'] ?? '') === 'adult_birthday', 'se guarda como cumpleaños de adulto');
a_check(($fiestas[$slug]['edad'] ?? null) === 61, 'la edad 61 se guarda entera (antes se recortaba a 17)');

// Lo que recibe el kiosco. La fiesta nace inactiva (sin Términos): se activa por la base.
cb_pdo()->prepare('UPDATE cc_parties SET active = 1 WHERE public_slug = ?')->execute([$slug]);
$api = json_decode($luis->pedir('GET', '/api.php?p=' . rawurlencode($slug))['html'], true);
a_check(($api['ok'] ?? false) === true, 'api.php abre la fiesta adulta');
a_check($api['party']['event_type'] === 'adult_birthday' && $api['party']['edad'] === 61, 'el kiosco recibe modalidad y edad');
a_check($api['theme']['personajes'] === [], 'sin ruleta de personajes');
a_check(is_array($api['theme']['cabinaAdulto']) && count($api['theme']['cabinaAdulto']['frases']) === 7, 'llegan las siete frases de la cabina');
a_check(in_array('🍆', $api['theme']['cabinaAdulto']['juego']['emojis'], true), 'el juego de atrapar trae sus emojis');
a_check($api['theme']['musica'] === '', 'sin pista en disco no se publica música');
a_check(is_array($api['theme']['grupal']) && str_ends_with($api['theme']['grupal']['fondo'], 'fondo-grupal.jpg'), 'la foto grupal tiene su fondo');

// La infantil no cambia.
cb_save_parties(['parties' => array_merge(cb_load_parties()['parties'], ['tomas-infantil' => [
    'nombre' => 'Tomás', 'tema' => 'spidey', 'edad' => 6, 'fecha' => '2026-12-05', 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s'),
]])]);
$api = json_decode($luis->pedir('GET', '/api.php?p=tomas-infantil')['html'], true);
a_check(($api['party']['event_type'] ?? '') === 'child_birthday', 'la fiesta infantil sigue siendo infantil');
a_check(!array_key_exists('edad', $api['party']), 'la edad de un niño no se publica en el kiosco, como hasta hoy');
a_check(!array_key_exists('cabinaAdulto', $api['theme']), 'una temática infantil no recibe ninguna broma adulta');
a_check(count($api['theme']['personajes']) === 6, 'spidey conserva sus seis personajes');
a_check(str_ends_with((string) $api['theme']['musica'], 'musica-fondo.mp3'), 'spidey conserva su música');

// Base editada a mano: temática adulta en una fiesta infantil.
cb_pdo()->prepare("UPDATE cc_parties SET theme_slug = 'adulto-coqueteria-pop' WHERE public_slug = 'tomas-infantil'")->execute();
$r = $luis->pedir('GET', '/api.php?p=tomas-infantil');
a_check($r['estado'] === 409 && str_contains($r['html'], 'theme_mismatch'), 'temática cruzada en la base: el kiosco no la abre');

echo "OK $tests\n";
