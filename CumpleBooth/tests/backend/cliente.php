<?php
/** Pruebas de contactos de la fiesta y cobro (SQLite temporal, aislado). */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-cliente-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/cliente.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('d', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/app');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CUMPLECLICK_CONFIG_FILE=' . $tmp . '/no-config.php');
require dirname(__DIR__, 2) . '/public/lib.php';

$tests = 0;
function cli_check(bool $cond, string $msg): void {
    global $tests;
    $tests++;
    if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); }
}

$run = static fn(string $f) => (require dirname(__DIR__, 2) . '/database/migrations/' . $f)(cb_pdo());
foreach (['001_initial', '002_theme_prompts', '003_invitations_and_plan', '004_gate_a_corrections',
          '005_theme_prompt_history', '006_public_leads', '007_event_album', '008_event_profiles',
          '009_invitation_gender', '010_baby_shower_predictions', '011_gift_mode',
          '015_party_contacts_billing', '016_discount_percent'] as $m) {
    $run($m . '.php');
}

$pdo = cb_pdo();
$slug = cb_generate_public_slug($pdo, 'Isidora', 'Reino de Hielo');
cli_check(cb_save_parties(['parties' => [$slug => [
    'public_slug' => $slug, 'admin_label' => 'PRUEBA', 'birthday_person_name' => 'Isidora',
    'tema' => 'hielo', 'theme_slug' => 'hielo', 'fecha' => '2026-09-13',
    'activa' => false, 'service_plan' => 'full', 'invitados' => [], 'frameBox' => null,
]]]), 'guarda la fiesta de prueba');

// ── Contactos ─────────────────────────────────────────────────────────────
cli_check(cb_party_contacts($slug) === [], 'una fiesta nueva no tiene contactos');

$r = cb_save_party_contacts($slug, [
    ['name' => 'Carolina', 'email' => 'MAMA@Ejemplo.CL', 'phone' => '+56 9 1111 1111', 'relationship' => 'madre'],
    ['name' => 'Abuela Rosa', 'email' => 'abuela@ejemplo.cl', 'relationship' => 'familiar'],
    ['name' => '', 'email' => ''],
]);
cli_check(!empty($r['ok']) && $r['saved'] === 2, 'guarda dos contactos e ignora la fila vacía');

$cs = cb_party_contacts($slug);
cli_check(count($cs) === 2, 'devuelve los dos contactos');
cli_check($cs[0]['email'] === 'mama@ejemplo.cl', 'el correo se normaliza a minúsculas');
cli_check($cs[0]['is_primary'] === true, 'sin marcar ninguno, el primero queda principal');
cli_check($cs[1]['is_primary'] === false, 'solo hay un principal');
cli_check($cs[1]['relationship'] === 'familiar', 'guarda la relación (puede no ser la madre ni el padre)');
cli_check(cb_party_contact_emails($slug) === ['mama@ejemplo.cl', 'abuela@ejemplo.cl'], 'lista de correos, principal primero');

$r = cb_save_party_contacts($slug, [['name' => 'X', 'email' => 'no-es-correo']]);
cli_check(empty($r['ok']) && count($r['errors']) === 1, 'rechaza un correo inválido');
cli_check(count(cb_party_contacts($slug)) === 2, 'un rechazo no borra los contactos que ya estaban');

$r = cb_save_party_contacts($slug, [
    ['name' => 'A', 'email' => 'repe@ejemplo.cl'],
    ['name' => 'B', 'email' => 'REPE@ejemplo.cl'],
]);
cli_check(empty($r['ok']), 'rechaza correos repetidos aunque cambie la caja');

$r = cb_save_party_contacts($slug, [
    ['name' => 'Papá', 'email' => 'papa@ejemplo.cl', 'relationship' => 'padre'],
    ['name' => 'Tía', 'email' => 'tia@ejemplo.cl', 'relationship' => 'familiar', 'is_primary' => true],
]);
$cs = cb_party_contacts($slug);
cli_check(!empty($r['ok']) && count($cs) === 2, 'reemplaza la lista completa');
cli_check($cs[0]['email'] === 'tia@ejemplo.cl' && $cs[0]['is_primary'], 'respeta el principal marcado');

cli_check(cb_save_party_contacts($slug, [])['saved'] === 0, 'se puede dejar la fiesta sin contactos');
cli_check(cb_party_contacts($slug) === [], 'y quedan borrados');

// ── Cobro ─────────────────────────────────────────────────────────────────
$b = cb_party_billing($slug);
cli_check($b['price_total'] === null && $b['total'] === null, 'sin precio no hay total');

cli_check(!empty(cb_save_party_billing($slug, [
    'price_total' => '$99.990', 'discount_amount' => '30000',
    'discount_label' => 'Descuento de lanzamiento', 'deposit_amount' => '20.000',
    'payment_note' => 'Transferencia',
])['ok']), 'guarda el cobro leyendo montos escritos como se escriben');

$b = cb_party_billing($slug);
cli_check($b['price_total'] === 99990, 'lee el precio como entero');
cli_check($b['discount_amount'] === 30000, 'lee el descuento');
cli_check($b['total'] === 69990, 'el total es precio menos descuento');
cli_check($b['balance'] === 49990, 'el saldo descuenta el anticipo');
cli_check($b['discount_label'] === 'Descuento de lanzamiento', 'guarda el motivo del descuento');

$r = cb_save_party_billing($slug, ['price_total' => '10000', 'discount_amount' => '20000']);
cli_check(empty($r['ok']), 'rechaza un descuento mayor que el precio');
$r = cb_save_party_billing($slug, ['price_total' => '10000', 'discount_amount' => '0', 'deposit_amount' => '99999']);
cli_check(empty($r['ok']), 'rechaza un anticipo mayor que el total');
cli_check(cb_party_billing($slug)['total'] === 69990, 'un rechazo no pisa el cobro guardado');

cli_check(cb_format_clp(69990) === '$69.990', 'formatea pesos con punto de miles');
cli_check(cb_format_clp(null) === '—', 'sin monto muestra una raya');
cli_check(cb_parse_clp('  $1.234.567 ') === 1234567, 'lee montos con símbolos y espacios');
cli_check(cb_parse_clp('') === null && cb_parse_clp('-5') === null, 'vacío y negativo quedan en null');


// ── Descuento en porcentaje ─────────────────────────────────────────────────
// El porcentaje manda sobre el monto: es como se acuerda el descuento y evita que quede
// un monto en pesos viejo cuando cambia el precio.
$r = cb_save_party_billing($slug, ['price_total' => '100000', 'discount_percent' => '20',
                                   'discount_amount' => '5000', 'discount_label' => 'Prueba']);
cli_check(!empty($r['ok']), 'guarda el cobro con porcentaje');
$b = cb_party_billing($slug);
cli_check($b['discount_percent'] === 20.0, 'el porcentaje queda guardado');
cli_check($b['discount_amount'] === 20000, 'el monto se deriva del porcentaje, no del campo en pesos');
cli_check($b['total'] === 80000, 'el total descuenta el porcentaje');

// Cambiar el precio recalcula el descuento sin tocar nada más.
cb_save_party_billing($slug, ['price_total' => '50000', 'discount_percent' => '20']);
$b = cb_party_billing($slug);
cli_check($b['discount_amount'] === 10000, 'al cambiar el precio, el descuento se recalcula');
cli_check($b['total'] === 40000, 'y el total también');

// 100% es el caso de la fiesta sin costo.
cb_save_party_billing($slug, ['price_total' => '99990', 'discount_percent' => '100',
                              'discount_label' => 'Fiesta de prueba']);
$b = cb_party_billing($slug);
cli_check($b['total'] === 0, 'con 100% el total queda en cero');
cli_check($b['discount_amount'] === 99990, 'y el descuento es todo el precio');

// Sin porcentaje vuelve a mandar el monto en pesos.
cb_save_party_billing($slug, ['price_total' => '100000', 'discount_percent' => '', 'discount_amount' => '7000']);
$b = cb_party_billing($slug);
cli_check($b['discount_percent'] === null, 'sin porcentaje queda nulo');
cli_check($b['discount_amount'] === 7000, 'y manda el monto escrito en pesos');

// Lo que no puede pasar.
$r = cb_save_party_billing($slug, ['price_total' => '100000', 'discount_percent' => '120']);
cli_check(empty($r['ok']), 'rechaza un porcentaje mayor que 100');
$r = cb_save_party_billing($slug, ['price_total' => '', 'discount_percent' => '20']);
cli_check(empty($r['ok']), 'rechaza el porcentaje sin precio cargado');

// Cómo se escribe un porcentaje de verdad.
cli_check(cb_parse_percent('20%') === 20.0, 'lee "20%"');
cli_check(cb_parse_percent('12,5') === 12.5, 'lee "12,5" con coma');
cli_check(cb_parse_percent('') === null, 'un porcentaje vacío es nulo');
cli_check(cb_parse_percent('0') === 0.0, '0% no es lo mismo que vacío');

echo "OK $tests checks cliente (contactos, cobro y descuento en %)
";
