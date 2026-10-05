<?php
/**
 * Modo feria de punta a punta (AT-CUMPLECLICK-020, 2026-09-26):
 *
 *  - migración 026 repetible;
 *  - una fiesta SIN feria responde exactamente igual en api.php, con o sin `tema` y `modo`;
 *  - con feria: api.php cambia de temática según `tema`/`modo`, rechaza lo no habilitado y suma `feria`;
 *  - feria-api.php: datos + mundos por modo, número F-### correlativo, nombre limpio;
 *  - upload.php: liga la foto a su número, nunca pierde una foto por una reserva mala, usa el tope de la feria;
 *  - galeria.php no existe para una feria;
 *  - galería del admin: búsqueda por número, nombre, modo, temática y hora; contador de impresiones;
 *  - duplicar; y retention.php borra las fotos de feria a los 7 días sin tocar una fiesta normal.
 *
 * Nunca toca una base real: la SQLite vive en una carpeta temporal que se borra al final.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-ferias-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 19100 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('f', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_SMTP_HOST' => '',
    'CC_ADMIN_PASSWORD_HASH' => password_hash('clave-maestra-feria-1234', PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require $raiz . '/public/lib.ferias.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);

$tests = 0;
function f_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

// ── Migración ───────────────────────────────────────────────────────────────
$up = require $raiz . '/database/migrations/026_modo_feria.php';
$up($pdo); $up($pdo);
f_check(cb_ferias_listo(), 'las dos tablas existen y la migración se puede repetir');

// ── Una fiesta normal y una feria ───────────────────────────────────────────
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d');
f_check(cb_save_parties(['parties' => ['luciano-spidey' => [
    'nombre' => 'Luciano', 'tema' => 'spidey', 'fecha' => $hoy, 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s'),
]]]), 'fiesta normal creada');
f_check(cb_feria_de_fiesta('luciano-spidey') === null, 'una fiesta normal no es feria');

[$datos, $errores] = cb_feria_validar([
    'nombre' => 'Mini Paseo Dieciochero', 'organizador' => 'Royal Art Academy', 'organizador_ig' => 'royalart.cl',
    'lugar' => 'Grecia 3348', 'fecha' => $hoy, 'hora_inicio' => '10:00', 'mesa' => '6',
    'mundos_infantil' => ['hielo', 'spidey', 'no-existe', 'hielo'], 'mundos_adulto' => ['hielo', 'baby-nube'],
    'retencion_dias' => 7, 'max_fotos' => 10, 'activa' => '1',
]);
f_check($errores === [], 'la ficha de la feria valida: ' . implode(' ', $errores));
f_check($datos['mundos_infantil'] === ['hielo', 'spidey'], 'se descarta la temática que no existe y la repetida');
f_check($datos['organizador_ig'] === '@royalart.cl', 'el Instagram del organizador queda con @');
[, $malos] = cb_feria_validar(['nombre' => '', 'fecha' => '2026-02-30', 'activa' => '1', 'max_fotos' => 3]);
f_check(count($malos) >= 3, 'sin nombre, con fecha imposible y sin mundos no se guarda');

$feria = cb_feria_guardar($datos, null, 'test');
$slug = $feria['slug'];
f_check(cb_valid_public_slug($slug) && str_contains($slug, 'feria'), 'la feria tiene su fiesta con slug propio');
f_check($feria['activa'] && $feria['party_activa'], 'feria y fiesta quedan activas');
require_once $raiz . '/public/lib.acceptance.php';
f_check(cb_party_can_activate($slug), 'la fiesta de la feria queda eximida de firma (evento propio)');
f_check(cb_feria_recuerdo($feria) === 'Mini Paseo Dieciochero · Royal Art Academy · ' . cb_feria_fecha_texto($hoy), 'línea de recuerdo');
f_check(cb_feria_fecha_texto('2026-09-26') === '26 sep 2026', 'fecha en texto');

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

function pedir(string $metodo, string $ruta, ?array $json = null): array
{
    global $base;
    $cuerpo = $json !== null ? json_encode($json) : '';
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'ignore_errors' => true, 'timeout' => 20,
        'header' => $json !== null ? "Content-Type: application/json\r\nContent-Length: " . strlen($cuerpo) : '',
        'content' => $cuerpo]]);
    $texto = (string) @file_get_contents($base . $ruta, false, $ctx);
    $estado = 0;
    foreach ($http_response_header ?? [] as $linea) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
    }
    $data = json_decode($texto, true);
    return ['estado' => $estado, 'texto' => $texto, 'json' => is_array($data) ? $data : null];
}

function jpeg(int $semilla): string
{
    $im = imagecreatetruecolor(90, 160);
    imagefilledrectangle($im, 0, 0, 90, 160, imagecolorallocate($im, ($semilla * 37) % 255, 120, 200));
    ob_start(); imagejpeg($im, null, 85); return (string) ob_get_clean();
}

function subir(string $party, int $semilla, ?string $reserva = null): array
{
    $cuerpo = ['party' => $party, 'name' => 'visita', 'image' => 'data:image/jpeg;base64,' . base64_encode(jpeg($semilla))];
    if ($reserva !== null) { $cuerpo['feria_reserva'] = $reserva; }
    return pedir('POST', '/upload.php', $cuerpo);
}

echo "Modo feria por HTTP en $base\n";

// ── Fiesta normal: api.php igual que antes ──────────────────────────────────
$normal = pedir('GET', '/api.php?p=luciano-spidey');
f_check($normal['estado'] === 200 && ($normal['json']['ok'] ?? false) === true, 'la fiesta normal responde');
f_check(!array_key_exists('feria', $normal['json']), 'la fiesta normal no trae feria');
$normalConParametros = pedir('GET', '/api.php?p=luciano-spidey&tema=hielo&modo=adulto');
f_check($normalConParametros['texto'] === $normal['texto'], 'en una fiesta normal, tema y modo no cambian nada (misma respuesta byte a byte)');
f_check(pedir('GET', '/feria-api.php?f=luciano-spidey')['estado'] === 404, 'feria-api no atiende fiestas normales');

// ── api.php con feria ───────────────────────────────────────────────────────
$r = pedir('GET', '/api.php?p=' . $slug);
f_check($r['estado'] === 200 && ($r['json']['theme']['slug'] ?? '') === 'hielo', 'sin tema, la primera temática infantil');
f_check(($r['json']['feria']['modo'] ?? '') === 'infantil', 'sin modo, infantil');
f_check(($r['json']['feria']['modos'] ?? null) === ['infantil' => ['hielo', 'spidey'], 'adulto' => ['hielo', 'baby-nube']], 'modos habilitados');
f_check(($r['json']['feria']['recuerdo'] ?? '') === cb_feria_recuerdo($feria), 'recuerdo en la respuesta');
f_check(($r['json']['feria']['organizador_ig'] ?? '') === '@royalart.cl' && ($r['json']['feria']['mesa'] ?? '') === '6', 'organizador y mesa');
f_check(($r['json']['party']['invitados'] ?? null) === [] && ($r['json']['party']['nombre'] ?? '') === 'Mini Paseo Dieciochero', 'sin lista de invitados, nombre de la feria');
$r = pedir('GET', '/api.php?p=' . $slug . '&tema=spidey&modo=infantil');
f_check(($r['json']['theme']['slug'] ?? '') === 'spidey', 'el visitante elige spidey');
$conJuego = 0;
foreach (($r['json']['theme']['personajes'] ?? []) as $p) { if (!empty((array) $p['game'])) { $conJuego++; } }
$fiesta = cb_build_theme_payload('spidey', cb_load_themes()['themes']['spidey'], null, 'booth');
$enFiesta = count(array_filter($fiesta['personajes'], static fn($p) => !empty((array) $p['game'])));
f_check($enFiesta > 0 && $conJuego === $enFiesta, 'en Niños los personajes traen el mismo minijuego que en una fiesta');
$r = pedir('GET', '/api.php?p=' . $slug . '&tema=baby-nube&modo=adulto');
f_check(($r['json']['theme']['slug'] ?? '') === 'baby-nube' && ($r['json']['theme']['personajes'] ?? null) === [], 'adulto con temática sin personajes');
$r = pedir('GET', '/api.php?p=' . $slug . '&tema=hielo&modo=adulto');
$adultoJuega = false;
foreach (($r['json']['theme']['personajes'] ?? []) as $p) { if (!empty((array) $p['game'])) { $adultoJuega = true; } }
f_check(($r['json']['theme']['slug'] ?? '') === 'hielo' && !$adultoJuega, 'en Adultos no aparece minijuego aunque la temática traiga personajes');
f_check(empty((array) ($r['json']['theme']['game'] ?? [])), 'en Adultos tampoco llega el juego general de la temática');
f_check(pedir('GET', '/api.php?p=' . $slug . '&tema=baby-nube&modo=infantil')['json']['error'] === 'tema_no_habilitado', 'temática no habilitada en ese modo: 403');
f_check(pedir('GET', '/api.php?p=' . $slug . '&tema=spidey&modo=adulto')['estado'] === 403, 'spidey no está en adultos');
f_check(pedir('GET', '/api.php?p=' . $slug . '&tema=hielo&modo=abuelos')['estado'] === 400, 'modo inválido: 400');

// ── feria-api.php GET ───────────────────────────────────────────────────────
$r = pedir('GET', '/feria-api.php?f=' . $slug);
f_check($r['estado'] === 200 && ($r['json']['ok'] ?? false) === true, 'feria-api responde');
f_check(!isset($r['json']['feria']['modo']) && !isset($r['json']['feria']['modos']), 'la ficha del selector no trae modo ni modos');
$mundos = $r['json']['mundos'] ?? [];
f_check(array_column($mundos['infantil'] ?? [], 'slug') === ['hielo', 'spidey'], 'mundos infantiles');
f_check(($mundos['adulto'][1]['slug'] ?? '') === 'baby-nube' && ($mundos['adulto'][1]['personajes'] ?? true) === false, 'baby-nube va sin personajes');
f_check(($mundos['infantil'][0]['personajes'] ?? false) === true && str_starts_with((string) $mundos['infantil'][0]['imagen'], 'themes/hielo/'), 'hielo con personajes e imagen');
// 29-09: cada temática publica su fondo de pantalla y el evento usa el de su primera temática con fondo propio.
f_check(($mundos['infantil'][0]['fondo'] ?? '') === 'themes/hielo/fondo-evento.jpg', 'hielo publica su fondo de evento');
f_check(($r['json']['feria']['fondo'] ?? '') === 'themes/hielo/fondo-evento.jpg', 'el evento usa el fondo de su primera temática');
f_check(cb_feria_fondo_mundo('no-existe') === '', 'una temática sin archivo no publica fondo');
f_check(array_key_exists('video_espera', $r['json']), 'trae el video de espera (vacío si el archivo aún no está)');

// ── Número F-### ────────────────────────────────────────────────────────────
$n1 = pedir('POST', '/feria-api.php', ['accion' => 'numero', 'f' => $slug, 'modo' => 'infantil', 'tema' => 'hielo', 'nombre' => 'Sofía']);
f_check(($n1['json']['numero'] ?? 0) === 1 && ($n1['json']['etiqueta'] ?? '') === 'F-001', 'primer número F-001');
f_check((bool) preg_match('/^[a-f0-9]{32}$/', (string) ($n1['json']['reserva'] ?? '')), 'reserva de 32 hex');
$n2 = pedir('POST', '/feria-api.php', ['accion' => 'numero', 'f' => $slug, 'modo' => 'adulto', 'tema' => 'baby-nube', 'nombre' => '<b>Ro</b>berto 😀 Pérez González y más']);
f_check(($n2['json']['numero'] ?? 0) === 2, 'segundo número');
$nombre2 = (string) $pdo->query('SELECT nombre FROM cc_feria_fotos WHERE numero = 2')->fetchColumn();
f_check(!str_contains($nombre2, '<') && mb_strlen($nombre2) <= 20, 'el nombre queda sin símbolos y con 20 caracteres como máximo: ' . $nombre2);
f_check(strlen((string) $pdo->query('SELECT reserva FROM cc_feria_fotos WHERE numero = 1')->fetchColumn()) === 64
    && (string) $pdo->query('SELECT reserva FROM cc_feria_fotos WHERE numero = 1')->fetchColumn() !== $n1['json']['reserva'], 'la base guarda la huella, no el token');
f_check(pedir('POST', '/feria-api.php', ['accion' => 'numero', 'f' => $slug, 'modo' => 'infantil', 'tema' => 'baby-nube'])['estado'] === 403, 'no reserva con una temática no habilitada');
f_check(pedir('POST', '/feria-api.php', ['accion' => 'otra', 'f' => $slug])['estado'] === 400, 'acción desconocida: 400');

// ── upload.php ──────────────────────────────────────────────────────────────
$u1 = subir($slug, 1, $n1['json']['reserva']);
f_check($u1['estado'] === 200 && ($u1['json']['ok'] ?? false) === true, 'la foto de Sofía se sube');
f_check((int) $pdo->query('SELECT COUNT(*) FROM cc_feria_fotos WHERE numero = 1 AND photo_id IS NOT NULL')->fetchColumn() === 1, 'y queda ligada a F-001');
$u1b = subir($slug, 2, $n1['json']['reserva']);
f_check(($u1b['json']['ok'] ?? false) === true, 'reusar la reserva no impide guardar la foto');
$ligada = (int) $pdo->query('SELECT photo_id FROM cc_feria_fotos WHERE numero = 1')->fetchColumn();
f_check($ligada === (int) $pdo->query("SELECT MIN(id) FROM cc_photos")->fetchColumn(), 'pero F-001 sigue con la primera foto');
f_check((subir($slug, 3, 'no-es-un-token')['json']['ok'] ?? false) === true, 'una reserva inválida no pierde la foto');
f_check((subir($slug, 4)['json']['ok'] ?? false) === true, 'sin reserva también se guarda');
f_check((subir($slug, 5, $n2['json']['reserva'])['json']['ok'] ?? false) === true, 'la foto de F-002 se sube');
// Una reserva de OTRA feria no liga una foto de esta.
$otra = cb_feria_guardar(array_merge($datos, ['nombre' => 'Otra feria']), null, 'test');
$nOtra = pedir('POST', '/feria-api.php', ['accion' => 'numero', 'f' => $otra['slug'], 'modo' => 'infantil', 'tema' => 'hielo', 'nombre' => 'X']);
f_check(($nOtra['json']['numero'] ?? 0) === 1, 'cada feria numera desde 1');
subir($slug, 6, $nOtra['json']['reserva']);
f_check((int) $pdo->query('SELECT COUNT(*) FROM cc_feria_fotos WHERE feria_id = ' . $otra['id'] . ' AND photo_id IS NOT NULL')->fetchColumn() === 0, 'una reserva de otra feria no se liga');
f_check((subir($otra['slug'], 13, $nOtra['json']['reserva'])['json']['ok'] ?? false) === true, 'la otra feria sube su propia foto');
// Tope de la feria (10 en esta prueba) en vez de 200.
for ($i = 7; $i <= 10; $i++) { subir($slug, $i); }
$lleno = subir($slug, 11);
f_check($lleno['estado'] === 507 && ($lleno['json']['max_photos'] ?? 0) === 10, 'la feria usa su propio tope de fotos');
f_check((subir('luciano-spidey', 12)['json']['ok'] ?? false) === true, 'la fiesta normal sigue subiendo con su tope de siempre');

// ── Sin galería pública ─────────────────────────────────────────────────────
$g = pedir('GET', '/galeria.php?p=' . $slug);
f_check($g['estado'] === 404 && str_contains($g['texto'], 'no tienen galería pública'), 'galeria.php no existe para una feria');

// ── Galería del admin ───────────────────────────────────────────────────────
$feria = cb_feria_por_id($feria['id']);
$todo = cb_feria_galeria($feria);
f_check($todo['total'] === 2 && $todo['fotos'][0]['etiqueta'] === 'F-002', 'la galería lista las fotos con número, la más nueva primero');
f_check(cb_feria_galeria($feria, ['q' => 'F-001'])['total'] === 1 && cb_feria_galeria($feria, ['q' => '1'])['fotos'][0]['nombre'] === 'Sofía', 'busca por número');
f_check(cb_feria_galeria($feria, ['q' => 'sof'])['total'] === 1, 'busca por nombre sin importar mayúsculas');
f_check(cb_feria_galeria($feria, ['modo' => 'adulto'])['total'] === 1 && cb_feria_galeria($feria, ['tema' => 'hielo'])['total'] === 1, 'filtra por modo y temática');
$idSofia = (int) $pdo->query('SELECT photo_id FROM cc_feria_fotos WHERE numero = 1 AND feria_id = ' . $feria['id'])->fetchColumn();
$pdo->prepare('UPDATE cc_photos SET created_at = ? WHERE id = ?')->execute(['2026-09-26 14:00:00', $idSofia]); // 11:00 en Chile
// Las demás fotos a una hora fija lejos del rango: con la hora real, la prueba fallaba entre 10:30 y 11:30.
$pdo->prepare('UPDATE cc_photos SET created_at = ? WHERE id IN (SELECT photo_id FROM cc_feria_fotos WHERE feria_id = ?) AND id <> ?')->execute(['2026-09-26 20:00:00', $feria['id'], $idSofia]); // 17:00 en Chile
f_check(cb_feria_galeria($feria, ['desde' => '10:30', 'hasta' => '11:30'])['total'] === 1, 'filtra por hora de Chile');
$idFila = (int) $todo['fotos'][1]['id'];
f_check(cb_feria_sumar_impresion($feria, $idFila, 2) === 2 && cb_feria_sumar_impresion($feria, $idFila) === 3, 'cuenta las impresiones');
f_check(cb_feria_sumar_impresion($otra, $idFila) === null, 'no suma impresiones de otra feria');
$lista = cb_ferias_listar();
$fila = array_values(array_filter($lista, static fn($f) => $f['id'] === $feria['id']))[0];
f_check($fila['fotos'] === 2 && $fila['impresiones'] === 3, 'el listado cuenta fotos numeradas e impresiones');

// ── Duplicar y desactivar ───────────────────────────────────────────────────
$copia = cb_feria_duplicar($feria['id'], 'test');
f_check(!$copia['activa'] && !$copia['party_activa'] && $copia['duplicada_de'] === $feria['id'], 'la copia nace inactiva y recuerda su origen');
f_check($copia['mundos'] === $feria['mundos'] && $copia['slug'] !== $feria['slug'], 'misma configuración, fiesta nueva');
f_check(pedir('GET', '/feria-api.php?f=' . $copia['slug'])['estado'] === 404, 'una feria inactiva no se ofrece');
f_check(pedir('GET', '/api.php?p=' . $copia['slug'])['estado'] === 403, 'ni el kiosco la abre');

// ── Panel: admin/ferias.php con la clave maestra ────────────────────────────
final class Panel
{
    public array $cookies = [];
    public function __construct(private string $base) {}
    public function pedir(string $metodo, string $ruta, ?array $datos = null): array
    {
        return $this->enviar($metodo, $ruta, $datos !== null ? http_build_query($datos) : null, 'application/x-www-form-urlencoded');
    }
    /** Formulario con archivos, como lo manda el navegador. `$archivos`: campo => [nombre, tipo, bytes]. */
    public function pedirMultipart(string $ruta, array $campos, array $archivos): array
    {
        $limite = '----cc' . bin2hex(random_bytes(8));
        $cuerpo = '';
        foreach ($campos as $k => $v) {
            foreach ((array) $v as $item) {
                $nombre = is_array($v) ? $k . '[]' : $k;
                $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$nombre\"\r\n\r\n" . $item . "\r\n";
            }
        }
        foreach ($archivos as $k => [$nombre, $tipo, $bytes]) {
            $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$k\"; filename=\"$nombre\"\r\nContent-Type: $tipo\r\n\r\n" . $bytes . "\r\n";
        }
        return $this->enviar('POST', $ruta, $cuerpo . "--$limite--\r\n", 'multipart/form-data; boundary=' . $limite);
    }
    private function enviar(string $metodo, string $ruta, ?string $cuerpo, string $tipo): array
    {
        $cab = [];
        if ($this->cookies) { $cab[] = 'Cookie: ' . implode('; ', array_map(fn($k) => $k . '=' . $this->cookies[$k], array_keys($this->cookies))); }
        if ($cuerpo !== null) { $cab[] = 'Content-Type: ' . $tipo; $cab[] = 'Content-Length: ' . strlen($cuerpo); }
        $cuerpo ??= '';
        $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cab), 'content' => $cuerpo,
            'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0; $ubicacion = '';
        foreach ($http_response_header ?? [] as $l) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $l, $m)) { $estado = (int) $m[1]; }
            if (stripos($l, 'Location:') === 0) { $ubicacion = trim(substr($l, 9)); }
            if (stripos($l, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $l, $m)) { $this->cookies[$m[1]] = $m[2]; }
        }
        return ['estado' => $estado, 'ubicacion' => $ubicacion, 'html' => $html];
    }
    public function csrf(string $html): string { return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : ''; }
}
$panel = new Panel($base);
f_check($panel->pedir('GET', '/admin/ferias.php')['estado'] === 302, 'sin sesión, el panel de ferias manda al login');
$r = $panel->pedir('GET', '/admin/maestro.php');
$r = $panel->pedir('POST', '/admin/maestro.php', ['csrf' => $panel->csrf($r['html']), 'password' => 'clave-maestra-feria-1234']);
f_check($r['estado'] === 302, 'entra con la clave maestra');
$r = $panel->pedir('GET', '/admin/ferias.php');
f_check($r['estado'] === 200 && str_contains($r['html'], 'Mini Paseo Dieciochero') && str_contains($r['html'], 'href="ferias.php"'), 'la lista muestra la feria y la pestaña Ferias');
$r = $panel->pedir('GET', '/admin/ferias.php?nueva=1');
f_check(str_contains($r['html'], 'value="adulto-glam-dorado"') && substr_count($r['html'], 'name="mundos_infantil[]" value="adulto-') === 0, 'la ficha ofrece las adultas solo en Adultos');
$r = $panel->pedir('POST', '/admin/ferias.php', ['csrf' => $panel->csrf($r['html']), 'action' => 'guardar', 'nombre' => 'Feria del panel',
    'fecha' => $hoy, 'organizador' => 'Junta de vecinos', 'mundos_infantil' => ['spidey'], 'mundos_adulto' => ['adulto-glam-dorado'], 'activa' => '1',
    'retencion_dias' => '7', 'max_fotos' => '500']);
f_check($r['estado'] === 303 && str_contains($r['ubicacion'], 'ok=creada'), 'crea una feria desde el formulario');
$creada = array_values(array_filter(cb_ferias_listar(), static fn($f) => $f['nombre'] === 'Feria del panel'))[0] ?? null;
f_check($creada !== null && $creada['activa'] && $creada['mundos']['infantil'] === ['spidey'], 'queda guardada, activa y con sus mundos');
$r = $panel->pedir('POST', '/admin/ferias.php', ['csrf' => 'malo', 'action' => 'guardar', 'nombre' => 'Intrusa', 'fecha' => $hoy]);
f_check($r['estado'] === 403, 'sin CSRF válido no guarda');
$r = $panel->pedir('GET', '/admin/ferias.php?galeria=' . $feria['id'] . '&q=F-001');
f_check($r['estado'] === 200 && str_contains($r['html'], 'F-001') && !str_contains($r['html'], '>F-002<'), 'la galería busca por número');
f_check(str_contains($r['html'], 'data-qr="' . $base . '/ver.php?t='), 'cada foto trae su QR');
$idF1 = (int) $pdo->query('SELECT id FROM cc_feria_fotos WHERE numero = 1 AND feria_id = ' . $feria['id'])->fetchColumn();
$antes = (int) $pdo->query("SELECT impresiones FROM cc_feria_fotos WHERE id = $idF1")->fetchColumn();
$r = $panel->pedir('POST', '/admin/ferias.php', ['csrf' => $panel->csrf($r['html']), 'action' => 'imprimir', 'feria' => $feria['id'], 'foto' => $idF1, 'copias' => 2]);
$j = json_decode($r['html'], true);
f_check(($j['ok'] ?? false) === true && $j['impresiones'] === $antes + 2, 'reimprimir suma las copias al contador');
$r = $panel->pedir('GET', '/admin/ferias.php?galeria=999999');
f_check($r['estado'] === 404, 'una feria que no existe da 404');

// ── Temáticas adultas propias (26-09) ───────────────────────────────────────
$temas = cb_load_themes()['themes'];
$adultas = ['adulto-estudio-bn', 'adulto-glam-dorado', 'adulto-noche-brujas'];
foreach ($adultas as $t) {
    f_check(($temas[$t]['audiencia'] ?? '') === 'adulto' && ($temas[$t]['personajes'] ?? null) === [] && array_key_exists('franquicia', $temas[$t]) && $temas[$t]['franquicia'] === null,
        "$t: adulta, sin personajes y sin franquicia");
    foreach (['fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-escena.jpg'] as $archivo) {
        $ruta = $raiz . "/public/themes/$t/$archivo";
        $info = is_file($ruta) ? getimagesize($ruta) : false;
        f_check($info !== false && $info[0] === 1080 && $info[1] === 1920, "$t/$archivo existe y mide 1080x1920");
    }
    f_check(cb_normalize_frame_box($temas[$t]['frameBox'] ?? null) !== null, "$t: frameBox válido para el modo marco");
}
[$datosAdultos, $erroresAdultos] = cb_feria_validar(['nombre' => 'Feria de adultos', 'fecha' => $hoy, 'activa' => '1',
    'mundos_infantil' => ['hielo', 'adulto-glam-dorado'], 'mundos_adulto' => $adultas, 'max_fotos' => 100]);
f_check($erroresAdultos === [] && $datosAdultos['mundos_infantil'] === ['hielo'], 'una temática adulta no entra en Niños');
f_check($datosAdultos['mundos_adulto'] === $adultas, 'las tres adultas entran en Adultos');
$feriaAdultos = cb_feria_guardar($datosAdultos, null, 'test');
$r = pedir('GET', '/api.php?p=' . $feriaAdultos['slug'] . '&tema=adulto-estudio-bn&modo=adulto');
f_check(($r['json']['theme']['filtro'] ?? '') === 'bn', 'el estudio llega con filtro blanco y negro');
f_check(($r['json']['theme']['modoFoto'] ?? '') === 'fondo' && ($r['json']['theme']['images']['escena'] ?? '') === 'themes/adulto-estudio-bn/fondo-escena.jpg', 'modo fondo con su escena');
f_check(($r['json']['party']['musica'] ?? true) === false, 'sin archivo de música, la tablet no la pide');
$r = pedir('GET', '/api.php?p=' . $feriaAdultos['slug'] . '&tema=adulto-glam-dorado&modo=adulto');
f_check(!isset($r['json']['theme']['filtro']) && ($r['json']['theme']['personajes'] ?? null) === [], 'glam a color y sin ruleta');
$r = pedir('GET', '/api.php?p=' . $slug . '&tema=hielo&modo=infantil');
f_check(($r['json']['theme']['modoFoto'] ?? '') === 'marco', 'una temática infantil sigue con marco');
$r = pedir('GET', '/feria-api.php?f=' . $feriaAdultos['slug']);
f_check(array_column($r['json']['mundos']['adulto'] ?? [], 'personajes') === [false, false, false], 'el selector sabe que las adultas van sin ruleta');

// Portada de Revista CLICK (04-10): adulta, con tres portadas que el invitado elige en el menú. El servidor publica solo las
// que tienen su escena en disco y el filtro únicamente en la de blanco y negro.
$rev = $temas['adulto-revista'] ?? [];
f_check(($rev['audiencia'] ?? '') === 'adulto' && ($rev['personajes'] ?? null) === [] && array_key_exists('franquicia', $rev) && $rev['franquicia'] === null,
    'adulto-revista: adulta, sin personajes y sin franquicia');
foreach (['fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-evento.jpg', 'revista-alfombra.jpg', 'revista-estudio.jpg', 'revista-bn.jpg'] as $archivo) {
    $info = @getimagesize($raiz . "/public/themes/adulto-revista/$archivo");
    f_check($info !== false && $info[0] === 1080 && $info[1] === 1920, "adulto-revista/$archivo existe y mide 1080x1920");
}
[$datosRev] = cb_feria_validar(['nombre' => 'Fiesta de la oficina', 'fecha' => $hoy, 'activa' => '1',
    'mundos_infantil' => ['hielo', 'adulto-revista'], 'mundos_adulto' => ['adulto-revista'], 'max_fotos' => 100]);
f_check($datosRev['mundos_infantil'] === ['hielo'] && $datosRev['mundos_adulto'] === ['adulto-revista'], 'la revista entra en Adultos y no en Niños');
$feriaRev = cb_feria_guardar($datosRev, null, 'test');
$r = pedir('GET', '/api.php?p=' . $feriaRev['slug'] . '&tema=adulto-revista&modo=adulto');
$tr = $r['json']['theme'] ?? [];
f_check(($tr['modoFoto'] ?? '') === 'fondo' && ($tr['images']['escena'] ?? '') === 'themes/adulto-revista/revista-alfombra.jpg',
    'la revista va sobre fondo, con la alfombra roja como escena por defecto');
f_check(!isset($tr['filtro']), 'la revista no lleva filtro general: solo su portada en blanco y negro');
f_check(($tr['revista']['titulo'] ?? '') === 'CLICK', 'el título de la revista es CLICK');
$variantesRev = $tr['revista']['variantes'] ?? [];
f_check(array_column($variantesRev, 'clave') === ['alfombra', 'estudio', 'bn'], 'tres portadas, en el orden del menú');
f_check(array_column($variantesRev, 'escena') === ['themes/adulto-revista/revista-alfombra.jpg', 'themes/adulto-revista/revista-estudio.jpg',
    'themes/adulto-revista/revista-bn.jpg'], 'cada portada con su escena publicada');
f_check(array_column($variantesRev, 'filtro') === ['bn'], 'solo la portada en blanco y negro lleva filtro');
f_check(($tr['personajes'] ?? null) === [] && empty($tr['asomate']['personajes'] ?? []), 'sin ruleta ni Asómate: el menú es el de las portadas');
$r = pedir('GET', '/feria-api.php?f=' . $feriaRev['slug']);
$mundoRev = array_values(array_filter($r['json']['mundos']['adulto'] ?? [], fn ($m) => ($m['slug'] ?? '') === 'adulto-revista'))[0] ?? [];
f_check(($mundoRev['imagen'] ?? '') === 'themes/adulto-revista/fondo-banner.jpg' && ($mundoRev['personajes'] ?? true) === false,
    'el selector muestra la portada de muestra y sabe que no hay ruleta');
$r = pedir('GET', '/api.php?p=' . $feriaAdultos['slug'] . '&tema=adulto-glam-dorado&modo=adulto');
f_check(!isset($r['json']['theme']['revista']), 'una temática sin bloque de revista no publica portadas');
// El bloque se valida pieza por pieza: escena inexistente o con ruta, título con marcas, colores, filtro y tope de cuatro.
f_check(cb_feria_revista('adulto-revista', ['titulo' => 'CLICK', 'variantes' => [['clave' => 'x', 'escena' => 'no-existe.jpg']]]) === null,
    'sin escenas en disco no hay portadas');
f_check(cb_feria_revista('adulto-revista', ['titulo' => 'CLICK', 'variantes' => [['clave' => 'x', 'escena' => '../adulto-glam-dorado/fondo-escena.jpg']]]) === null,
    'una escena con ruta no pasa');
f_check(cb_feria_revista('adulto-revista', ['titulo' => '<b>CLICK</b>', 'variantes' => [['clave' => 'a', 'escena' => 'revista-bn.jpg']]]) === null,
    'un título con marcas no pasa');
$vRev = cb_feria_revista('adulto-revista', ['titulo' => 'click', 'variantes' => [['clave' => 'a', 'escena' => 'revista-bn.jpg',
    'tinta' => 'red', 'acento' => '#ABCDEF', 'filtro' => 'sepia']]]);
f_check(($vRev['titulo'] ?? '') === 'CLICK' && !isset($vRev['variantes'][0]['tinta']) && ($vRev['variantes'][0]['acento'] ?? '') === '#ABCDEF'
    && !isset($vRev['variantes'][0]['filtro']), 'colores y filtro se validan uno por uno');
$cincoRev = array_map(fn ($i) => ['clave' => 'v' . $i, 'escena' => 'revista-estudio.jpg'], range(1, 5));
f_check(count(cb_feria_revista('adulto-revista', ['titulo' => 'CLICK', 'variantes' => $cincoRev])['variantes'] ?? []) === 4, 'máximo cuatro portadas');
// Los textos fijos de la portada se editan en Admin -> Ajustes (04-10): sin nada escrito llegan los de siempre; lo que Luis
// escribe llega al kiosco; lo que no cabe se rechaza; un guardado que no los trae no los borra; vacío vuelve al de siempre.
$siempre = array_map(fn ($c) => $c[2], cb_revista_textos_campos());
$apiRev = fn () => pedir('GET', '/api.php?p=' . $feriaRev['slug'] . '&tema=adulto-revista&modo=adulto')['json']['theme']['revista']['textos'] ?? null;
f_check($apiRev() === $siempre, 'sin ajustes, la portada llega con los textos de siempre');
$r = $panel->pedir('GET', '/admin/ajustes.php');
f_check($r['estado'] === 200 && str_contains($r['html'], 'Portada de Revista') && str_contains($r['html'], 'name="revista_textos[antetitulo]"')
    && str_contains($r['html'], 'placeholder="LA ESTRELLA DE HOY"'), 'Ajustes muestra los textos de la portada con el de siempre de ejemplo');
$formAjustes = ['csrf' => $panel->csrf($r['html']), 'action' => 'guardar', 'bcc_email' => '', 'recovery_email' => '',
    'manual_anticipacion_min' => '', 'manual_dias_lista' => '',
    'revista_textos' => ['antetitulo' => 'La reina de hoy', 'llamado1' => 'Moda de feria', 'bajada' => '', 'etiqueta' => '', 'llamado2' => '',
        'sinNombre' => '', 'edicion' => '', 'numero' => 'N.º 25']];
$r = $panel->pedir('POST', '/admin/ajustes.php', $formAjustes);
f_check(str_contains($r['html'], 'Ajustes guardados.') && str_contains($r['html'], 'value="La reina de hoy"'), 'Ajustes guarda los textos de la portada');
$propios = $apiRev();
f_check(($propios['antetitulo'] ?? '') === 'La reina de hoy' && ($propios['llamado1'] ?? '') === 'Moda de feria' && ($propios['numero'] ?? '') === 'N.º 25'
    && ($propios['bajada'] ?? '') === $siempre['bajada'], 'el kiosco recibe lo que se escribió y el de siempre en lo vacío');
$r = $panel->pedir('POST', '/admin/ajustes.php', array_replace_recursive($formAjustes, ['csrf' => $panel->csrf($r['html']),
    'revista_textos' => ['antetitulo' => str_repeat('a', 25)]]));
f_check(str_contains($r['html'], '«Sobre el nombre» tiene 25 letras') && ($apiRev()['antetitulo'] ?? '') === 'La reina de hoy',
    'un texto que no cabe se rechaza y no cambia nada');
f_check(!empty(cb_guardar_ajustes(['bcc_email' => '', 'recovery_email' => ''])['ok']) && ($apiRev()['antetitulo'] ?? '') === 'La reina de hoy',
    'un guardado que no trae los textos no los borra');
cb_guardar_ajustes(['revista_textos' => array_fill_keys(array_keys($siempre), '')]);
f_check($apiRev() === $siempre, 'vacío vuelve a los textos de siempre');

// Año Nuevo y Muro de prensa (04-10): el rótulo de cada una, y el logo del evento subido por la ficha (multipart, como el
// navegador), servido por feria-logo.php ya vuelto a codificar, rechazado si no es imagen, copiado al duplicar y borrable.
foreach (['adulto-anio-nuevo' => 'anioNuevo', 'adulto-empresa' => 'muroLogos'] as $t => $diseno) {
    f_check(($temas[$t]['audiencia'] ?? '') === 'adulto' && ($temas[$t]['personajes'] ?? null) === [] && ($temas[$t]['rotulo'] ?? null) === ['diseno' => $diseno],
        "$t: adulta, sin personajes, con el rótulo $diseno");
    foreach (['fondo-escena.jpg', 'fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-evento.jpg'] as $archivo) {
        $info = @getimagesize($raiz . "/public/themes/$t/$archivo");
        f_check($info !== false && $info[0] === 1080 && $info[1] === 1920, "$t/$archivo existe y mide 1080x1920");
    }
}
[$datosEmp] = cb_feria_validar(['nombre' => 'Fiesta de fin de año', 'organizador' => 'Empresa Demo', 'fecha' => $hoy, 'activa' => '1',
    'mundos_adulto' => ['adulto-anio-nuevo', 'adulto-empresa'], 'max_fotos' => 100]);
$feriaEmp = cb_feria_guardar($datosEmp, null, 'test');
$temaEmp = fn (string $tema) => pedir('GET', '/api.php?p=' . $feriaEmp['slug'] . '&tema=' . $tema . '&modo=adulto')['json']['theme'] ?? [];
f_check(($temaEmp('adulto-anio-nuevo')['rotulo'] ?? null) === ['diseno' => 'anioNuevo'], 'Año Nuevo publica su rótulo');
f_check(($temaEmp('adulto-empresa')['rotulo'] ?? null) === ['diseno' => 'muroLogos', 'logo' => ''], 'sin logo, el muro va con letras');
f_check(pedir('GET', '/feria-logo.php?f=' . $feriaEmp['slug'])['estado'] === 404 && pedir('GET', '/feria-logo.php?f=no-existe')['estado'] === 404,
    'sin logo, o con un evento que no existe, feria-logo.php da 404');
$pngDe = function (int $ancho, int $alto): string {
    $lienzo = imagecreatetruecolor($ancho, $alto);
    imagealphablending($lienzo, false);
    imagesavealpha($lienzo, true);
    imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
    imagefilledrectangle($lienzo, 10, 10, (int) ($alto * 0.9), $alto - 10, imagecolorallocatealpha($lienzo, 30, 58, 138, 0));
    ob_start();
    imagepng($lienzo);
    imagedestroy($lienzo);
    return (string) ob_get_clean();
};
$fichaEmp = function () use ($panel, $feriaEmp, $hoy): array {
    $r = $panel->pedir('GET', '/admin/ferias.php?editar=' . $feriaEmp['id']);
    return ['csrf' => $panel->csrf($r['html']), 'action' => 'guardar', 'id' => (string) $feriaEmp['id'], 'nombre' => 'Fiesta de fin de año',
        'organizador' => 'Empresa Demo', 'fecha' => $hoy, 'mundos_adulto' => ['adulto-anio-nuevo', 'adulto-empresa'], 'retencion_dias' => '7',
        'max_fotos' => '100', 'activa' => '1'];
};
$crudo = function (string $ruta) use ($base): array {
    $cuerpo = (string) @file_get_contents($base . $ruta, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20]]));
    return ['cabeceras' => strtolower(implode("\n", $http_response_header ?? [])), 'cuerpo' => $cuerpo];
};
$r = $panel->pedir('GET', '/admin/ferias.php?editar=' . $feriaEmp['id']);
f_check(str_contains($r['html'], 'enctype="multipart/form-data"') && str_contains($r['html'], 'name="logo"')
    && str_contains($r['html'], 'hasta ' . cb_feria_logo_max_texto()), 'la ficha del evento tiene el campo del logo, con el tope real del servidor');
$r = $panel->pedirMultipart('/admin/ferias.php', $fichaEmp(), ['logo' => ['logo empresa.png', 'image/png', $pngDe(2000, 500)]]);
f_check($r['estado'] === 303, 'la ficha se guarda con el logo');
$logoUrl = $temaEmp('adulto-empresa')['rotulo']['logo'] ?? '';
f_check(str_starts_with($logoUrl, 'feria-logo.php?f=' . rawurlencode($feriaEmp['slug']) . '&v='), 'el muro recibe la dirección del logo');
$servido = $crudo('/' . $logoUrl);
$infoLogo = @getimagesizefromstring($servido['cuerpo']);
f_check(str_contains($servido['cabeceras'], 'content-type: image/png') && str_contains($servido['cabeceras'], 'x-content-type-options: nosniff')
    && str_contains($servido['cabeceras'], 'no-transform'), 'feria-logo.php sirve un PNG, sin olfateo y sin que el CDN lo re-codifique');
f_check($infoLogo !== false && $infoLogo[2] === IMAGETYPE_PNG && $infoLogo[0] === 1000 && $infoLogo[1] === 250, 'un logo de 2000 px queda en 1000 px, sin deformarse');
$imLogo = imagecreatefromstring($servido['cuerpo']);
f_check(((imagecolorat($imLogo, 900, 125) >> 24) & 0x7F) === 127 && ((imagecolorat($imLogo, 60, 125) >> 24) & 0x7F) === 0,
    'el logo conserva su transparencia: lo vacío sigue vacío y el dibujo opaco');
$r = $panel->pedirMultipart('/admin/ferias.php', $fichaEmp(), ['logo' => ['logo.png', 'image/png', '<?php echo "esto no es una imagen"; ?>']]);
f_check($r['estado'] === 200 && str_contains($r['html'], 'El logo tiene que ser una imagen PNG, JPG o WebP.')
    && $crudo('/' . $logoUrl)['cuerpo'] === $servido['cuerpo'], 'un archivo que no es imagen se rechaza y el logo anterior sigue igual');
if (function_exists('imagewebp')) {
    $lienzo = imagecreatetruecolor(300, 120);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 200, 30, 30));
    ob_start();
    imagewebp($lienzo);
    imagedestroy($lienzo);
    $webp = (string) ob_get_clean();
    $panel->pedirMultipart('/admin/ferias.php', $fichaEmp(), ['logo' => ['logo.webp', 'image/webp', $webp]]);
    $infoWebp = @getimagesizefromstring($crudo('/' . ($temaEmp('adulto-empresa')['rotulo']['logo'] ?? ''))['cuerpo']);
    f_check($infoWebp !== false && $infoWebp[2] === IMAGETYPE_PNG && $infoWebp[0] === 300, 'un logo WebP se acepta y queda guardado como PNG');
}
$copiaEmp = cb_feria_duplicar((int) $feriaEmp['id'], 'test');
f_check(is_file(cb_feria_logo_ruta((int) $copiaEmp['id']))
    && file_get_contents(cb_feria_logo_ruta((int) $copiaEmp['id'])) === file_get_contents(cb_feria_logo_ruta((int) $feriaEmp['id'])),
    'duplicar el evento copia el logo');
$r = $panel->pedirMultipart('/admin/ferias.php', $fichaEmp() + ['quitar_logo' => '1'], []);
f_check($r['estado'] === 303 && pedir('GET', '/feria-logo.php?f=' . $feriaEmp['slug'])['estado'] === 404
    && ($temaEmp('adulto-empresa')['rotulo']['logo'] ?? 'x') === '', 'quitar el logo lo borra y el muro vuelve a las letras');

// Fiestas Patrias: temática chilena sin personajes, para Niños y Adultos.
$chile = $temas['fiestas-patrias'] ?? [];
f_check(!isset($chile['audiencia']) && ($chile['personajes'] ?? null) === [] && array_key_exists('franquicia', $chile) && $chile['franquicia'] === null,
    'fiestas-patrias: para todos, sin personajes y sin franquicia');
foreach (['fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-escena.jpg'] as $archivo) {
    $info = @getimagesize($raiz . "/public/themes/fiestas-patrias/$archivo");
    f_check($info !== false && $info[0] === 1080 && $info[1] === 1920, "fiestas-patrias/$archivo existe y mide 1080x1920");
}
[$datosChile] = cb_feria_validar(['nombre' => 'Feria dieciochera', 'fecha' => $hoy, 'activa' => '1',
    'mundos_infantil' => ['fiestas-patrias'], 'mundos_adulto' => ['fiestas-patrias'], 'max_fotos' => 100]);
f_check($datosChile['mundos_infantil'] === ['fiestas-patrias'] && $datosChile['mundos_adulto'] === ['fiestas-patrias'], 'Fiestas Patrias entra en los dos modos');
$feriaChile = cb_feria_guardar($datosChile, null, 'test');
$r = pedir('GET', '/api.php?p=' . $feriaChile['slug'] . '&tema=fiestas-patrias&modo=infantil');
f_check(($r['json']['theme']['modoFoto'] ?? '') === 'fondo' && ($r['json']['theme']['personajes'] ?? null) === []
    && ($r['json']['feria']['modo'] ?? '') === 'infantil', 'en Niños llega con fondo completo y sin ruleta');
$aso = $r['json']['theme']['asomate'] ?? null;
f_check(is_array($aso) && count($aso['personajes'] ?? []) === 6, 'Fiestas Patrias trae Asómate con seis personajes');
f_check(array_column($aso['personajes'] ?? [], 'nombre') === ['Huasita de vestido floreado', 'Huasita de chupalla', 'Huasita de manta',
    'Huaso de chamanto', 'Huaso de chupalla', 'Huaso de gala'], 'los nombres salen del bloque de Asómate, sin ruleta');
$okGeo = true;
foreach (($aso['personajes'] ?? []) as $p) {
    $png = $raiz . '/public/themes/fiestas-patrias/asomate/' . $p['clave'] . '.png';
    $info = @getimagesize($png);
    $okGeo = $okGeo && $info !== false && $info[0] === (int) $p['w'] && $info[1] === (int) $p['h']
        && $p['cx'] - $p['rx'] > 0 && $p['cx'] + $p['rx'] < $p['w'] && $p['cy'] - $p['ry'] > 0;
}
f_check($okGeo, 'cada recorte mide lo anotado y su hueco cae dentro de la figura');
f_check(str_starts_with((string) ($aso['fondo'] ?? ''), 'themes/fiestas-patrias/asomate/fondo.jpg?v='), 'el fondo de Asómate va con sello de versión');

// Noche de Brujas y Navidad (30-09): Asómate con seis cuerpos de pie, el nombre de cada uno sale de la propia temática (no de la clave) y
// Navidad publica dónde pisan los pies, porque el piso de su escena empieza más abajo que el de siempre.
[$datosInf] = cb_feria_validar(['nombre' => 'Feria del colegio', 'fecha' => $hoy, 'activa' => '1',
    'mundos_infantil' => ['brujitas', 'navidad'], 'mundos_adulto' => ['fiestas-patrias'], 'max_fotos' => 100]);
$feriaInf = cb_feria_guardar($datosInf, null, 'test');
$esperados = [
    'brujitas' => ['Brujita Luna', 'Pepa Calabaza', 'Fantasmín', 'Gato Medianoche', 'Murci', 'Momi'],
    'navidad'  => ['Viejito Pascuero', 'Señora Pascuera', 'Reno Cascabel', 'Duende Ayudante', 'Copito', 'Pingüi'],
];
foreach ($esperados as $temaInf => $nombresInf) {
    $r = pedir('GET', '/api.php?p=' . $feriaInf['slug'] . '&tema=' . $temaInf . '&modo=infantil');
    $asoInf = $r['json']['theme']['asomate'] ?? null;
    f_check(is_array($asoInf) && count($asoInf['personajes'] ?? []) === 6, "$temaInf trae Asómate con seis personajes");
    f_check(array_column($asoInf['personajes'] ?? [], 'nombre') === $nombresInf, "$temaInf: los nombres de Asómate salen de la temática");
    $okInf = true;
    foreach (($asoInf['personajes'] ?? []) as $p) {
        $info = @getimagesize($raiz . "/public/themes/$temaInf/asomate/" . $p['clave'] . '.png');
        $okInf = $okInf && $info !== false && $info[0] === (int) $p['w'] && $info[1] === (int) $p['h']
            && $p['cx'] - $p['rx'] > 0 && $p['cx'] + $p['rx'] < $p['w'] && $p['cy'] - $p['ry'] > 0;
    }
    f_check($okInf, "$temaInf: cada cuerpo mide lo anotado y su hueco cae dentro");
    f_check(str_starts_with((string) ($asoInf['fondo'] ?? ''), "themes/$temaInf/fondo-escena.jpg?v="), "$temaInf: Asómate usa la escena despejada, con sello de versión");
    f_check(($asoInf['boton'] ?? '') !== '' && ($asoInf['titulo'] ?? '') !== '', "$temaInf: el modo lleva su propio nombre");
}
$r = pedir('GET', '/api.php?p=' . $feriaInf['slug'] . '&tema=navidad&modo=infantil');
f_check(($r['json']['theme']['asomate']['suelo'] ?? null) === 0.865, 'Navidad publica dónde pisan los pies del grupo');
$r = pedir('GET', '/api.php?p=' . $feriaInf['slug'] . '&tema=brujitas&modo=infantil');
f_check(!array_key_exists('suelo', $r['json']['theme']['asomate'] ?? []), 'Noche de Brujas no lo trae: usa el de siempre');

// ── Retención: 7 días para la feria, nada para la fiesta normal ─────────────
$pdo->prepare('UPDATE cc_ferias SET fecha = ? WHERE id = ?')->execute([gmdate('Y-m-d', time() - 8 * 86400), $feria['id']]);
$archivos = $pdo->query('SELECT storage_key FROM cc_photos WHERE party_id = ' . $feria['party_id'])->fetchAll(PDO::FETCH_COLUMN);
$proc = proc_open([PHP_BINARY, $raiz . '/scripts/retention.php', '--apply'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, $raiz, $env);
$salida = stream_get_contents($tuberias[1]) . stream_get_contents($tuberias[2]);
$codigo = proc_close($proc);
f_check($codigo === 0 && str_contains($salida, 'ferias: 1'), 'retention.php corre y cuenta la feria vencida: ' . trim($salida));
f_check((int) $pdo->query('SELECT COUNT(*) FROM cc_photos WHERE party_id = ' . $feria['party_id'] . ' AND deleted_at IS NULL')->fetchColumn() === 0, 'las fotos de la feria vencida se borran');
$quedan = 0;
foreach ($archivos as $k) { $p = cb_photo_absolute_path((string) $k); if ($p && is_file($p)) { $quedan++; } }
f_check($quedan === 0 && count($archivos) > 0, 'y sus archivos también');
f_check((string) $pdo->query("SELECT GROUP_CONCAT(nombre, '') FROM cc_feria_fotos WHERE feria_id = " . $feria['id'])->fetchColumn() === '', 'los nombres de los visitantes se borran');
f_check((int) $pdo->query('SELECT COUNT(*) FROM cc_feria_fotos WHERE feria_id = ' . $feria['id'])->fetchColumn() === 2, 'los números quedan para contar');
$lucianoId = cb_party_db_id('luciano-spidey');
f_check((int) $pdo->query("SELECT COUNT(*) FROM cc_photos WHERE party_id = $lucianoId AND deleted_at IS NULL")->fetchColumn() === 1, 'la fiesta normal de hoy no se toca');
f_check((int) $pdo->query('SELECT COUNT(*) FROM cc_photos WHERE party_id = ' . $otra['party_id'] . ' AND deleted_at IS NULL')->fetchColumn() === 1, 'ni la feria que no ha vencido');
f_check(pedir('GET', '/api.php?p=' . $slug)['estado'] === 403, 'la feria archivada ya no abre');

// ── Chile en Volantín en la temática Fiestas Patrias (26-09) ─────────────────────────────────────────
$fiestas = cb_load_parties()['parties'];
$fiestas['fp-volantin'] = ['nombre' => 'Fiesta dieciochera', 'tema' => 'fiestas-patrias', 'fecha' => date('Y-m-d'), 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']], 'creada' => gmdate('Y-m-d H:i:s')];
cb_save_parties(['parties' => $fiestas]);
$menu = pedir('GET', '/puntajes.php?p=fp-volantin');
f_check(in_array('volantin', array_column($menu['json']['juegos_disponibles'] ?? [], 'id'), true), 'el menú de una fiesta de Fiestas Patrias ofrece Chile en Volantín');
// El juego manda un formulario (URLSearchParams), no JSON: puntajes.php lee $_POST.
$anota = static function (int $n) use ($base): int {
    $cuerpo = http_build_query(['p' => 'fp-volantin', 'juego' => 'volantin', 'jugador' => 'Ana', 'puntaje' => (string) $n]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'timeout' => 20,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($cuerpo), 'content' => $cuerpo]]);
    @file_get_contents($base . '/puntajes.php', false, $ctx);
    return preg_match('/^HTTP\/\S+ (\d{3})/', $http_response_header[0] ?? '', $m) ? (int) $m[1] : 0;
};
f_check($anota(512) === 200, 'el volantín anota un puntaje real');
f_check($anota(1500) === 400, 'y rechaza uno imposible (tope 1.000)');
$api = pedir('GET', '/api.php?p=fp-volantin');
f_check(($api['json']['theme']['personajes'] ?? null) === [] && ($api['json']['theme']['slug'] ?? '') === 'fiestas-patrias', 'la fiesta normal de Fiestas Patrias abre sin personajes (el kiosco salta la ruleta)');

echo "OK: $tests comprobaciones\n";
