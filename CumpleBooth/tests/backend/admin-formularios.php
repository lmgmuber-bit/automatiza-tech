<?php
/**
 * Que los formularios del admin no se pisen entre ellos, y que la ficha guarde lo que dice.
 *
 * Esto existe por un incidente concreto (2026-09-08). El panel de correos se dibujó dentro
 * del formulario de la ficha, con un `<form>` propio por cada botón. HTML **no permite
 * formularios anidados**: el navegador descarta el interno y se queda con sus campos, así que
 * los `<input name="action" value="reenviar_...">` terminaron dentro del formulario grande,
 * después del `action=guardar`. PHP se queda con el último valor repetido, de modo que
 * apretar **Guardar** no guardaba nada y en su lugar mandaba un correo al cliente. Salieron
 * dos manuales de verdad antes de que se notara.
 *
 * No se puede probar con un navegador acá, así que se comprueba la propiedad estructural en
 * el código: ningún `<form>` dentro de otro, y ningún formulario con dos `action` distintos.
 * Además se prueba que guardar la ficha conserva de verdad la edad y la galería, que es el
 * síntoma que delató todo.
 */
if (PHP_SAPI !== 'cli') { exit(2); }

$raiz = dirname(__DIR__, 2);
$fallos = 0;
$total = 0;
function ok(string $que, bool $cond): void
{
    global $fallos, $total;
    $total++;
    if (!$cond) { $fallos++; echo "  FALLA: $que\n"; }
}

echo "Formularios del admin\n";

// ---------- Ningún formulario dentro de otro ----------
foreach (glob($raiz . '/public/admin/*.php') ?: [] as $archivo) {
    $nombre = basename($archivo);
    $src = str_replace("\r\n", "\n", (string) file_get_contents($archivo));
    $profundidad = 0;
    $maxima = 0;
    $lineaMala = 0;
    foreach (explode("\n", $src) as $n => $linea) {
        if (preg_match('/<form\b/', $linea) === 1) {
            $profundidad++;
            if ($profundidad > 1 && $lineaMala === 0) { $lineaMala = $n + 1; }
            $maxima = max($maxima, $profundidad);
        }
        if (strpos($linea, '</form>') !== false) { $profundidad--; }
    }
    ok("$nombre: sin formularios anidados" . ($lineaMala ? " (linea $lineaMala)" : ''), $maxima <= 1);
    ok("$nombre: abre y cierra la misma cantidad de formularios", $profundidad === 0);
}

// ---------- Un formulario, un `action` ----------
// El del panel de correos se engancha con `form="cc-envios"`, que asocia el control a un
// formulario de fuera; por eso no cuenta como campo de la ficha.
$admin = str_replace("\r\n", "\n", (string) file_get_contents($raiz . '/public/admin/index.php'));
preg_match_all('/<form\b.*?<\/form>/s', $admin, $formularios);
foreach ($formularios[0] as $i => $formulario) {
    preg_match_all('/name="action"/', $formulario, $acciones);
    $sueltos = 0;
    // Los botones con `form="..."` pertenecen a otro formulario, no a este.
    foreach (preg_split('/(?=<(?:input|button)\b)/', $formulario) as $control) {
        if (strpos($control, 'name="action"') !== false && strpos($control, 'form="') === false) {
            $sueltos++;
        }
    }
    ok("el formulario #$i tiene un solo `action` propio (tiene $sueltos)", $sueltos <= 1);
}

// ---------- Guardar la ficha conserva lo que se escribió ----------
$tmp = sys_get_temp_dir() . '/cumpleclick-ficha-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/ficha.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('f', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/app');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CUMPLECLICK_CONFIG_FILE=' . $tmp . '/no-config.php');
require_once $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
cb_test_migrar_todo(cb_pdo());

$base = ['public_slug' => 'prueba-ficha', 'nombre' => 'Samantha', 'admin_label' => 'Fiesta de prueba',
         'tema' => 'hielo', 'fecha' => '2026-09-13', 'activa' => false, 'invitados' => []];

// Crear. El INSERT enumeraba los valores a mano y se saltó `gallery_enabled`: quedaba en 15
// para 16 columnas, PDO tiraba "Invalid parameter number" y `cb_save_parties()` devolvía
// false en silencio, así que crear una fiesta no funcionaba y nadie lo veía.
$datos = ['parties' => ['prueba-ficha' => $base + ['edad' => 5, 'gallery_enabled' => true]]];
ok('crear una fiesta guarda de verdad', cb_save_parties($datos));
$leida = cb_load_parties()['parties']['prueba-ficha'] ?? [];
ok('la edad sobrevive al alta', ($leida['edad'] ?? null) === 5);
ok('la galería de papás sobrevive al alta', !empty($leida['gallery_enabled']));
ok('el nombre y la temática también', ($leida['nombre'] ?? '') === 'Samantha' && ($leida['tema'] ?? '') === 'hielo');

// Editar: el caso que reportó Luis, cambiar 5 por 4.
$datos = cb_load_parties();
$datos['parties']['prueba-ficha']['edad'] = 4;
ok('editar la edad guarda', cb_save_parties($datos));
ok('y al releer dice 4, no 5', (cb_load_parties()['parties']['prueba-ficha']['edad'] ?? null) === 4);

// Borrarla: vacío es válido, hay fiestas cargadas antes de que el campo existiera.
$datos = cb_load_parties();
$datos['parties']['prueba-ficha']['edad'] = null;
ok('dejar la edad vacía también guarda', cb_save_parties($datos));
ok('y queda sin edad, no con la anterior', (cb_load_parties()['parties']['prueba-ficha']['edad'] ?? null) === null);

// Una edad imposible no se guarda: casi siempre es un error de tecleo.
$datos = cb_load_parties();
$datos['parties']['prueba-ficha']['edad'] = 99;
cb_save_parties($datos);
ok('una edad fuera de rango se descarta', (cb_load_parties()['parties']['prueba-ficha']['edad'] ?? null) === null);

// ---------- El Resumen del Plan se llena solo con lo que ya está en la ficha ----------
// Pedir de nuevo el valor, el anticipo, la hora y la dirección teniéndolos cargados no es solo
// trabajo repetido: es la forma más fácil de que el contrato diga un número distinto del que
// dice la boleta.
require_once $raiz . '/public/lib.cliente.php';
$datos = cb_load_parties();
$datos['parties']['prueba-ficha']['edad'] = 5;
cb_save_parties($datos);
cb_save_party_contacts('prueba-ficha', [
    ['name' => 'Carolina Pérez', 'email' => 'carolina@ejemplo.cl', 'phone' => '+56 9 1234 5678',
     'relationship' => 'madre', 'is_primary' => 1],
]);
cb_save_party_billing('prueba-ficha', [
    'price_total' => '49995', 'discount_percent' => '20', 'discount_label' => 'Marcha blanca',
    'deposit_amount' => '10000', 'payment_note' => 'Transferencia el día del evento',
]);

$contacto = cb_party_contacto_principal('prueba-ficha');
ok('el contacto de la ficha viene con nombre, correo y teléfono',
    $contacto['name'] === 'Carolina Pérez' && $contacto['email'] === 'carolina@ejemplo.cl' && $contacto['phone'] !== '');

$resumen = cb_party_resumen_plan('prueba-ficha');
// 49.995 menos 20% = 39.996: el contrato dice lo que se paga, no el precio de lista.
ok('el valor total sale del cobro y es lo que se paga', ($resumen['price_total'] ?? '') === cb_format_clp(39996));
ok('el anticipo sale del cobro', ($resumen['deposit'] ?? '') === cb_format_clp(10000));
ok('la forma de pago del cobro se respeta', ($resumen['balance_due'] ?? '') === 'Transferencia el día del evento');
ok('el descuento queda explicado en las observaciones',
    str_contains($resumen['notes'] ?? '', 'Marcha blanca') && str_contains($resumen['notes'] ?? '', cb_format_clp(49995)));
ok('sin invitación publicada no inventa hora ni dirección',
    !isset($resumen['event_time']) && !isset($resumen['event_address']));

// Lo que el admin escribió a mano manda sobre lo deducido: se usa `+=`, que no pisa.
$aMano = ['price_total' => '$1', 'deposit' => '$2'];
$mezcla = $aMano + $resumen;
ok('lo escrito a mano gana sobre lo que sale de la ficha',
    $mezcla['price_total'] === '$1' && $mezcla['deposit'] === '$2' && isset($mezcla['notes']));

// ---------- «Nueva fiesta» muestra el marco de la temática, no uno genérico ----------
// AT-CUMPLECLICK-026 (12-oct): el formulario nacía con el marco genérico 0.32/0.30/0.36/0.28 y
// cambiar la temática solo cambiaba el fondo. Al guardar, esos números quedaban como marco de
// la fiesta y en Coquetería Pop la foto salía chica en el centro. Se prueba por HTTP.
$claveMaestra = 'clave-maestra-marco-1234';
$puerto = 19500 + random_int(0, 200);
$baseHttp = 'http://127.0.0.1:' . $puerto;
$env = array_merge(getenv(), [
    'CC_ADMIN_PASSWORD_HASH' => password_hash($claveMaestra, PASSWORD_DEFAULT),
    'CC_AJUSTES_PATH' => $tmp . '/ajustes.json', 'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CC_PUBLIC_BASE_URL' => $baseHttp,
]);
$servidor = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $raiz . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/servidor.log', 'a'], 2 => ['file', $tmp . '/servidor.log', 'a']],
    $pipesServidor, $raiz . '/public', $env);
register_shutdown_function(static function () use ($servidor): void { if (is_resource($servidor)) { proc_terminate($servidor); } });
for ($i = 0; $i < 50; $i++) {
    $s = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);
    if ($s) { fclose($s); break; }
    usleep(100000);
}
$cookies = [];
$pedir = static function (string $metodo, string $ruta, ?array $datos = null) use ($baseHttp, &$cookies): array {
    $cab = [];
    if ($cookies) { $cab[] = 'Cookie: ' . implode('; ', array_map(static fn($k, $v) => "$k=$v", array_keys($cookies), $cookies)); }
    $cuerpo = $datos === null ? '' : http_build_query($datos);
    if ($datos !== null) { $cab[] = 'Content-Type: application/x-www-form-urlencoded'; $cab[] = 'Content-Length: ' . strlen($cuerpo); }
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cab), 'content' => $cuerpo,
        'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 15]]);
    $html = (string) @file_get_contents($baseHttp . $ruta, false, $ctx);
    $estado = 0;
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $l, $m)) { $estado = (int) $m[1]; }
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $l, $m)) { $cookies[$m[1]] = $m[2]; }
    }
    return ['estado' => $estado, 'html' => $html];
};
$csrfDe = static fn(string $html): string => preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
// Los cuatro números que el formulario muestra en el calibrador.
$camposMarco = static function (string $html): array {
    $box = [];
    foreach (['x', 'y', 'w', 'h'] as $k) {
        $box[$k] = preg_match('/name="frame_' . $k . '"[^>]*value="([^"]*)"/', $html, $m) ? (float) $m[1] : null;
    }
    return $box;
};
// El marco que cada <option> de la temática le pasa al script (sin atributo: null).
$marcoDeOpcion = static function (string $html, string $tema): ?array {
    if (!preg_match('/<option value="' . preg_quote($tema, '/') . '"([^>]*)>/', $html, $m)) { return null; }
    if (!preg_match('/data-frame-box="([^"]*)"/', $m[1], $a)) { return null; }
    $box = json_decode(html_entity_decode($a[1], ENT_QUOTES, 'UTF-8'), true);
    return is_array($box) ? array_map('floatval', $box) : null;
};
$mismoMarco = static fn(?array $a, ?array $b): bool => is_array($a) && is_array($b)
    && array_reduce(['x', 'y', 'w', 'h'], static fn($c, $k) => $c && isset($a[$k], $b[$k]) && abs((float) $a[$k] - (float) $b[$k]) < 0.00005, true);

$temasAdmin = cb_load_themes()['themes'];
$generico = ['x' => 0.32, 'y' => 0.30, 'w' => 0.36, 'h' => 0.28];
$r = $pedir('GET', '/admin/maestro.php');
$pedir('POST', '/admin/maestro.php', ['csrf' => $csrfDe($r['html']), 'password' => $claveMaestra]);
$r = $pedir('GET', '/admin/index.php?action=nueva');
ok('el formulario «Nueva fiesta» abre', $r['estado'] === 200 && str_contains($r['html'], 'id="f-tema"'));
$csrf = $csrfDe($r['html']);

preg_match('/<select id="f-tema".*?<\/select>/s', $r['html'], $select);
$opciones = preg_match_all('/<option value="/', $select[0] ?? '');
$conMarco = preg_match_all('/<option value="[^"]*" data-frame-box="/', $select[0] ?? '');
ok("cada temática del selector lleva su data-frame-box ($conMarco de $opciones)", $opciones === count($temasAdmin) && $conMarco === $opciones);
$malos = [];
foreach ($temasAdmin as $slugTema => $datosTema) {
    $esperado = cb_normalize_frame_box($datosTema['frameBox'] ?? null) ?? $generico;
    if (!$mismoMarco($marcoDeOpcion($r['html'], $slugTema), $esperado)) { $malos[] = $slugTema; }
}
ok('el data-frame-box de cada temática es su frameBox de themes.json' . ($malos ? ' (difieren: ' . implode(', ', $malos) . ')' : ''), $malos === []);
$coqueteria = ['x' => 0.1204, 'y' => 0.1719, 'w' => 0.7593, 'h' => 0.4271];
ok('Coquetería Pop entrega 0.1204/0.1719/0.7593/0.4271 al calibrador', $mismoMarco($marcoDeOpcion($r['html'], 'adulto-coqueteria-pop'), $coqueteria));

// Sin tocar nada, los campos muestran el marco de la temática que viene elegida.
$temaInicial = (string) array_key_first($temasAdmin);
$marcoInicial = cb_normalize_frame_box($temasAdmin[$temaInicial]['frameBox'] ?? null) ?? $generico;
ok("al abrir, el calibrador muestra el marco de «{$temaInicial}» y no el genérico", $mismoMarco($camposMarco($r['html']), $marcoInicial));

// Guardar con lo que muestra el formulario (sin «usar calibración predeterminada»).
$fichaMarco = static fn(array $extra) => array_merge(['csrf' => $csrf, 'action' => 'guardar', 'modo' => 'nueva',
    'admin_label' => 'Prueba Marco', 'birthday_person_name' => 'Prueba Marco', 'fecha' => '2026-12-04',
    'service_plan' => 'booth'], $extra);
$antes = array_keys(cb_load_parties()['parties']);
$nuevaFiesta = static function (array $antes): array {
    $todas = cb_load_parties()['parties'];
    $nuevas = array_values(array_diff(array_keys($todas), $antes));
    return $nuevas ? $todas[end($nuevas)] : [];
};
$enPantalla = $camposMarco($r['html']);
$r2 = $pedir('POST', '/admin/index.php', $fichaMarco(['event_type' => 'child_birthday', 'tema' => $temaInicial,
    'frame_x' => $enPantalla['x'], 'frame_y' => $enPantalla['y'], 'frame_w' => $enPantalla['w'], 'frame_h' => $enPantalla['h']]));
ok("guardar «{$temaInicial}» con lo que muestra el formulario se crea", $r2['estado'] === 302);
ok("y su marco es el de «{$temaInicial}», no el genérico", $mismoMarco($nuevaFiesta($antes)['frameBox'] ?? null, $marcoInicial));

// Elegir Coquetería Pop: el script carga los números de la opción (sin atributo, quedan los de pantalla).
$antes = array_keys(cb_load_parties()['parties']);
$alElegir = $marcoDeOpcion($r['html'], 'adulto-coqueteria-pop') ?? $camposMarco($r['html']);
$r2 = $pedir('POST', '/admin/index.php', $fichaMarco(['event_type' => 'adult_birthday', 'tema' => 'adulto-coqueteria-pop',
    'frame_x' => $alElegir['x'], 'frame_y' => $alElegir['y'], 'frame_w' => $alElegir['w'], 'frame_h' => $alElegir['h']]));
ok('guardar Coquetería Pop tras elegirla se crea', $r2['estado'] === 302);
ok('y su marco es el de Coquetería Pop (0.1204/0.1719/0.7593/0.4271)', $mismoMarco($nuevaFiesta($antes)['frameBox'] ?? null, $coqueteria));

// Si la validación rechaza la ficha, el calibrador devuelve lo que se escribió (no lo pisa la temática).
$r2 = $pedir('POST', '/admin/index.php', $fichaMarco(['event_type' => 'child_birthday', 'tema' => 'adulto-coqueteria-pop',
    'frame_x' => '0.2', 'frame_y' => '0.2', 'frame_w' => '0.5', 'frame_h' => '0.3']));
ok('una ficha rechazada conserva el marco escrito a mano',
    $r2['estado'] === 200 && $mismoMarco($camposMarco($r2['html']), ['x' => 0.2, 'y' => 0.2, 'w' => 0.5, 'h' => 0.3]));
// Apagar el servidor antes del cierre: en Windows retiene la base y el log, y la carpeta temporal no se borraría.
proc_terminate($servidor);
proc_close($servidor);

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
