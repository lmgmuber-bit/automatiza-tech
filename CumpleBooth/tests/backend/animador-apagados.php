<?php
/**
 * Juegos del animador apagados por fiesta (AT-CUMPLECLICK-026, pedido de Luis del 12-oct).
 *
 *  - el catálogo del servidor (cb_animador_juegos) es el mismo de src/animador/mazos.js;
 *  - el admin guarda los apagados de una fiesta de adulto y api.php los publica en
 *    `party.animadorApagados`; ids desconocidos se descartan al guardar y al publicar;
 *  - una fiesta infantil o de baby shower no lleva la clave;
 *  - editar sin tocar las casillas, guardar sin la sección o prender/apagar los juegos 3D
 *    conserva lo apagado; todas apagadas también se puede, y la ficha lo dice;
 *  - duplicar una fiesta de adulto conserva la modalidad y la lista;
 *  - migración 027: la columna existe, es repetible, y sin ella el kiosco sigue abriendo;
 *  - el código anterior (188ba45) guarda todas las fiestas y no toca la columna (rollback).
 *
 * Datos ficticios; la SQLite vive en una carpeta temporal.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-animador-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 19500 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;
$claveMaestra = 'clave-maestra-animador-' . bin2hex(random_bytes(4));

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('f', 64), 'CC_PUBLIC_BASE_URL' => $base,
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
function j_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── Catálogo: paridad con mazos.js ─────────────────────────────────────────
// Se lee como texto: pares `id: '...'` seguidos de `nombre: '...'` (las cartas usan c('..')).
$mazos = (string) file_get_contents($raiz . '/src/animador/mazos.js');
preg_match_all("/\\bid:\\s*'([^']+)',\\s*\\r?\\n\\s*nombre:\\s*'([^']+)'/u", $mazos, $m, PREG_SET_ORDER);
$enMazos = [];
foreach ($m as $par) { $enMazos[$par[1]] = $par[2]; }
$catalogo = cb_animador_juegos();
j_check(count($enMazos) >= 15, 'la regex encuentra los juegos de mazos.js (' . count($enMazos) . ')');
j_check(array_keys($catalogo) === array_keys($enMazos), 'mismos ids y mismo orden que mazos.js: '
    . implode(',', array_diff(array_keys($enMazos), array_keys($catalogo))) . ' / ' . implode(',', array_diff(array_keys($catalogo), array_keys($enMazos))));
j_check($catalogo === $enMazos, 'mismos nombres que mazos.js');
echo 'Paridad con mazos.js (sha256 ' . substr(hash('sha256', $mazos), 0, 12) . '): ' . count($enMazos) . " juegos\n";

// ── Saneado ─────────────────────────────────────────────────────────────────
j_check(cb_sanitize_animador_apagados(['globo', 'x', 'tribunal', 'globo', ['a'], null, '<b>']) === ['tribunal', 'globo'],
    'ids desconocidos fuera, sin repetir y en el orden del catálogo');
j_check(cb_sanitize_animador_apagados(null) === [] && cb_sanitize_animador_apagados('tribunal') === ['tribunal'], 'null y texto suelto');
j_check(cb_party_animador_apagados('no-existe') === [], 'fiesta inexistente: ninguno apagado');

// ── Migración 027 ───────────────────────────────────────────────────────────
$columnas = array_column(cb_pdo()->query('PRAGMA table_info(cc_parties)')->fetchAll(PDO::FETCH_ASSOC), 'name');
j_check(in_array('animador_apagados', $columnas, true), 'la migración 027 agrega la columna');
(require $raiz . '/database/migrations/027_animador_apagados.php')(cb_pdo());
j_check(true, 'la migración 027 se puede correr dos veces');

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

final class NavegadorJ
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
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $mm)) { $estado = (int) $mm[1]; }
            if (stripos($linea, 'Location:') === 0) { $destino = trim(substr($linea, 9)); }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $mm)) {
                if ($mm[2] === 'deleted' || $mm[2] === '') { unset($this->cookies[$mm[1]]); } else { $this->cookies[$mm[1]] = $mm[2]; }
            }
        }
        return ['estado' => $estado, 'html' => $html, 'destino' => $destino];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $mm) ? $mm[1] : '';
    }
}

/** Ids de las casillas marcadas en la ficha, como las enviaría el navegador. */
function j_marcadas(string $html): array
{
    preg_match_all('/name="animador_on\[\]" value="([^"]+)" data-animador-juego\s*(checked)?/', $html, $mm, PREG_SET_ORDER);
    $out = [];
    foreach ($mm as $c) { if (!empty($c[2])) { $out[] = $c[1]; } }
    return $out;
}

echo "Juegos del animador apagados, por HTTP en $base\n";
$luis = new NavegadorJ($base);
$r = $luis->pedir('GET', '/admin/maestro.php');
$luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => $claveMaestra]);
// La fiesta nace inactiva (sin Términos): se activa por la base antes de pedir la API.
$apiDe = static function (string $s) use ($luis): array {
    cb_pdo()->prepare('UPDATE cc_parties SET active = 1 WHERE public_slug = ?')->execute([$s]);
    return (array) json_decode($luis->pedir('GET', '/api.php?p=' . rawurlencode($s))['html'], true);
};
$r = $luis->pedir('GET', '/admin/index.php?action=nueva');
j_check($r['estado'] === 200 && str_contains($r['html'], 'Juegos del animador'), 'la ficha nueva trae la sección');
j_check(preg_match('/<div id="f-animador"\s+hidden>/', $r['html']) === 1, 'en una ficha nueva (infantil por defecto) la sección nace oculta');
j_check(count(j_marcadas($r['html'])) === count($catalogo), 'por defecto, todos disponibles');
j_check(str_contains($r['html'], 'no aparecen en la lista de «Juegos de la fiesta»'), 'la ficha explica qué pasa con los apagados');
$csrf = $luis->csrf($r['html']);
// Incluye «Edición política», que Luis puede querer apagar según la fiesta.
$apagar = ['swing', 'globo', 'beso-al-blanco', 'venezolano-politica'];
$esperado = cb_sanitize_animador_apagados($apagar);   // en el orden del catálogo
j_check(count($esperado) === 4 && in_array('venezolano-politica', $esperado, true), 'los cuatro a apagar están en el catálogo');
$disponibles = array_values(array_diff(array_keys($catalogo), $apagar));
$ficha = static fn (array $extra) => array_merge(['csrf' => $csrf, 'action' => 'guardar', 'modo' => 'nueva',
    'admin_label' => 'Prueba Juegos', 'birthday_person_name' => 'Prueba Juegos', 'fecha' => '2026-12-04',
    'service_plan' => 'booth', 'frame_reset' => '1', 'event_type' => 'adult_birthday', 'tema' => 'adulto-coqueteria-pop',
    'birthday_age' => '50'], $extra);

// Sin CSRF no se guarda nada.
$r = $luis->pedir('POST', '/admin/index.php', array_merge($ficha(['animador_definidos' => '1', 'animador_on' => $disponibles]), ['csrf' => 'x']));
j_check(count(cb_load_parties()['parties']) === 0, 'sin CSRF válido no se crea la fiesta');

// Crear con tres apagados (y basura en el POST, que se ignora).
$r = $luis->pedir('POST', '/admin/index.php', $ficha(['animador_definidos' => '1',
    'animador_on' => array_merge($disponibles, ['inventado', '<script>', 'swing-x'])]));
j_check($r['estado'] === 302 && str_contains($r['destino'], 'ok=creada'), 'la fiesta adulta se crea con cuatro juegos apagados');
$slug = array_key_first(cb_load_parties()['parties']);
j_check(cb_party_animador_apagados($slug) === $esperado, 'se guardan los cuatro apagados, en el orden del catálogo');
$crudo = cb_pdo()->query("SELECT animador_apagados FROM cc_parties WHERE public_slug = " . cb_pdo()->quote($slug))->fetchColumn();
j_check(json_decode((string) $crudo, true) === $esperado, 'la columna guarda un arreglo JSON de ids');

$api = $apiDe($slug);
j_check(($api['ok'] ?? false) === true, 'api.php abre la fiesta adulta');
j_check(($api['party']['animadorApagados'] ?? null) === $esperado, 'api.php publica party.animadorApagados');

// Editar sin tocar las casillas: lo que manda el navegador es lo que la ficha trae marcado.
$r = $luis->pedir('GET', '/admin/index.php?action=editar&slug=' . rawurlencode($slug));
j_check(preg_match('/<div id="f-animador"\s*>/', $r['html']) === 1, 'en una fiesta de adulto la sección se ve');
$marcadas = j_marcadas($r['html']);
j_check($marcadas === $disponibles, 'la ficha trae desmarcados justo los cuatro apagados');
j_check(str_contains($r['html'], (count($catalogo) - 4) . ' de ' . count($catalogo) . ' disponibles.'), 'la ficha muestra la cuenta');
// Sin 'activa': activar exige Términos firmados; la API se prueba tras activar por la base.
$edicion = static fn (array $extra) => array_merge($ficha(['modo' => 'editar', 'slug_original' => $slug]), $extra);

$r = $luis->pedir('POST', '/admin/index.php', $edicion(['animador_definidos' => '1', 'animador_on' => $marcadas, 'admin_label' => 'Prueba Juegos 2']));
j_check($r['estado'] === 302 && str_contains($r['destino'], 'ok=editada'), 'editar otro campo guarda');
j_check(cb_party_animador_apagados($slug) === $esperado, 'editar sin tocar las casillas conserva lo apagado');

// Un formulario sin la sección (viejo, o de otro camino) no toca lo guardado.
$r = $luis->pedir('POST', '/admin/index.php', $edicion([]));
j_check($r['estado'] === 302, 'guardar sin la sección funciona');
j_check(cb_party_animador_apagados($slug) === $esperado, 'guardar sin la sección conserva lo apagado');

// Otro camino que reescribe todas las fiestas: el interruptor de los juegos 3D.
$r = $luis->pedir('GET', '/admin/index.php');
$luis->pedir('POST', '/admin/index.php', ['csrf' => $luis->csrf($r['html']), 'action' => 'juegos3d', 'slug' => $slug, 'prender' => '0']);
j_check(cb_juegos3d_activos($slug) === false, 'el interruptor de juegos 3D funcionó');
j_check(cb_party_animador_apagados($slug) === $esperado, 'apagar los juegos 3D conserva los del animador');

// Todos apagados: se puede, la API publica los dieciséis y la ficha lo dice.
$r = $luis->pedir('POST', '/admin/index.php', $edicion(['animador_definidos' => '1']));
j_check($r['estado'] === 302, 'guardar con todos apagados se puede');
j_check(cb_party_animador_apagados($slug) === array_keys($catalogo), 'quedan todos apagados');
$api = $apiDe($slug);
j_check(($api['party']['animadorApagados'] ?? null) === array_keys($catalogo), 'la API publica todos apagados');
$r = $luis->pedir('GET', '/admin/index.php?action=editar&slug=' . rawurlencode($slug));
j_check(j_marcadas($r['html']) === [] && str_contains($r['html'], '0 de ' . count($catalogo) . ' disponibles. Con todos apagados'),
    'la ficha avisa que la lista queda vacía');

// Volver a prender todos: la columna vuelve a NULL y la API publica [].
$luis->pedir('POST', '/admin/index.php', $edicion(['animador_definidos' => '1', 'animador_on' => array_keys($catalogo)]));
j_check(cb_pdo()->query("SELECT animador_apagados FROM cc_parties WHERE public_slug = " . cb_pdo()->quote($slug))->fetchColumn() === null,
    'con todos prendidos la columna queda en NULL');
$api = $apiDe($slug);
j_check(($api['party']['animadorApagados'] ?? null) === [], 'sin apagados la API publica un arreglo vacío');

// Ids desconocidos escritos a mano en la base: se descartan al publicar; JSON roto = ninguno.
cb_pdo()->prepare('UPDATE cc_parties SET animador_apagados = ? WHERE public_slug = ?')->execute(['["inventado","globo",7,"globo"]', $slug]);
$api = $apiDe($slug);
j_check(($api['party']['animadorApagados'] ?? null) === ['globo'], 'al publicar se descartan ids desconocidos y repetidos');
cb_pdo()->prepare('UPDATE cc_parties SET animador_apagados = ? WHERE public_slug = ?')->execute(['{roto', $slug]);
$api = $apiDe($slug);
j_check(($api['party']['animadorApagados'] ?? null) === [], 'JSON roto en la base: ninguno apagado, el kiosco abre');
j_check(cb_save_party_animador_apagados($slug, ['x', 'tribunal', 'tribunal', 'piropos'])['ok'] === true
    && cb_party_animador_apagados($slug) === ['tribunal', 'piropos'], 'al guardar se descartan ids desconocidos y repetidos');

// Duplicar: la copia sigue siendo de adulto y conserva la lista.
$r = $luis->pedir('GET', '/admin/index.php');
$r = $luis->pedir('POST', '/admin/index.php', ['csrf' => $luis->csrf($r['html']), 'action' => 'duplicar', 'slug' => $slug]);
j_check($r['estado'] === 302 && str_contains($r['destino'], 'ok=duplicada'), 'duplicar responde');
$copia = '';
foreach (cb_load_parties()['parties'] as $s => $p) { if ($s !== $slug) { $copia = $s; } }
j_check($copia !== '' && cb_load_parties()['parties'][$copia]['event_type'] === 'adult_birthday', 'la copia conserva la modalidad de adulto');
j_check(cb_party_animador_apagados($copia) === ['tribunal', 'piropos'], 'la copia conserva los juegos apagados');

// Infantil y baby shower: la clave no existe aunque la columna tenga algo.
cb_save_parties(['parties' => array_merge(cb_load_parties()['parties'], [
    'tomas-infantil' => ['nombre' => 'Tomás', 'tema' => 'spidey', 'edad' => 6, 'fecha' => '2026-12-05', 'activa' => true,
        'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s')],
    'baby-prueba' => ['nombre' => 'Bebé', 'tema' => 'baby-nube', 'event_type' => 'baby_shower', 'fecha' => '2026-12-06', 'activa' => true,
        'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s')],
])]);
cb_pdo()->prepare('UPDATE cc_parties SET animador_apagados = ? WHERE public_slug IN (?, ?)')->execute(['["globo"]', 'tomas-infantil', 'baby-prueba']);
foreach (['tomas-infantil', 'baby-prueba'] as $s) {
    $api = json_decode($luis->pedir('GET', '/api.php?p=' . $s)['html'], true);
    j_check(($api['ok'] ?? false) === true && !array_key_exists('animadorApagados', $api['party']), "$s: sin la clave animadorApagados");
}
$r = $luis->pedir('GET', '/admin/index.php?action=editar&slug=tomas-infantil');
j_check(preg_match('/<div id="f-animador"\s+hidden>/', $r['html']) === 1, 'en una fiesta infantil la sección está oculta');

// Rollback: el lib.php de 188ba45 reescribe todas las fiestas y no toca la columna.
$antes = cb_pdo()->query('SELECT public_slug, animador_apagados FROM cc_parties ORDER BY public_slug')->fetchAll(PDO::FETCH_KEY_PAIR);
// Los lib*.php de esa versión (lib.php carga otros al final), en una carpeta aparte.
$viejo = $tmp . '/viejo-188ba45';
mkdir($viejo, 0770, true);
$git = proc_open(['git', '-C', $raiz, 'ls-tree', '--name-only', '188ba45', '--', 'public/'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $gp);
$archivosViejos = array_filter(preg_split('/\R/', (string) stream_get_contents($gp[1])), static fn ($a) => preg_match('#/lib[^/]*\.php$#', $a) === 1);
proc_close($git);
foreach ($archivosViejos as $archivo) {
    $g = proc_open(['git', '-C', $raiz, 'show', '188ba45:./' . substr($archivo, (int) strpos($archivo, 'public/'))], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $gp);
    file_put_contents($viejo . '/' . basename($archivo), stream_get_contents($gp[1]));
    proc_close($g);
}
$libViejo = $viejo . '/lib.php';
j_check(count($archivosViejos) > 10 && is_file($libViejo) && filesize($libViejo) > 10000, 'se extrajo el código de 188ba45 (' . count($archivosViejos) . ' lib*.php)');
j_check(!str_contains((string) file_get_contents($libViejo), 'animador_apagados'), 'el lib.php de 188ba45 no conoce la columna');
file_put_contents($tmp . '/viejo.php', '<?php require ' . var_export($libViejo, true) . ';'
    . '$d = cb_load_parties(); echo count($d["parties"]), " ", cb_save_parties($d) ? "ok" : "falla";');
$p = proc_open([PHP_BINARY, $tmp . '/viejo.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, $tmp, $env);
$salida = trim(stream_get_contents($pp[1]) . stream_get_contents($pp[2]));
proc_close($p);
j_check(str_ends_with($salida, ' ok'), 'el código anterior guarda todas las fiestas: ' . $salida);
$despues = cb_pdo()->query('SELECT public_slug, animador_apagados FROM cc_parties ORDER BY public_slug')->fetchAll(PDO::FETCH_KEY_PAIR);
j_check($despues === $antes, 'tras el guardado del código anterior, la columna sigue igual en todas las fiestas');

// Sin la migración 027 (código nuevo subido antes que la migración): el kiosco sigue abriendo
// y el admin avisa en vez de perder el cambio en silencio.
cb_pdo()->exec('ALTER TABLE cc_parties DROP COLUMN animador_apagados');
cb_pdo()->prepare('UPDATE cc_parties SET event_type = ?, theme_slug = ? WHERE public_slug = ?')->execute(['adult_birthday', 'adulto-coqueteria-pop', $slug]);
$api = $apiDe($slug);
j_check(($api['ok'] ?? false) === true && ($api['party']['animadorApagados'] ?? null) === [], 'sin la columna, api.php abre y publica []');
$r = $luis->pedir('GET', '/admin/index.php?action=editar&slug=' . rawurlencode($slug));
$csrf = $luis->csrf($r['html']);
$r = $luis->pedir('POST', '/admin/index.php', $edicion(['csrf' => $csrf, 'animador_definidos' => '1', 'animador_on' => ['tribunal']]));
j_check($r['estado'] === 200 && str_contains($r['html'], 'falta aplicar la migración 027'), 'sin la columna, el admin avisa que falta la migración 027');
(require $raiz . '/database/migrations/027_animador_apagados.php')(cb_pdo());
j_check(in_array('animador_apagados', array_column(cb_pdo()->query('PRAGMA table_info(cc_parties)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true),
    'la migración 027 vuelve a crear la columna');

echo "OK $tests\n";
