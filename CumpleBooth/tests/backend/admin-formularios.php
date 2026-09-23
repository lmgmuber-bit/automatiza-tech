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

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
