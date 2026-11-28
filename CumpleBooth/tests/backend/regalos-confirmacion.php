<?php
/**
 * Reglas de regalos del baby shower con confirmación (migración 028, modo `rsvp`).
 * Base SQLite temporal; no toca ninguna base real.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-regalos-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db'); putenv('CC_PDO_DSN=sqlite:' . $tmp . '/t.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('d', 64)); putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos'); putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_INVITATION_DIR=' . $tmp . '/invitations');
require dirname(__DIR__, 2) . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);
cb_test_migrar_todo($pdo); // idempotente: aplicar dos veces no rompe

$tests = 0;
function r_check(bool $c, string $m): void { global $tests; $tests++; if (!$c) { throw new RuntimeException('FAIL: ' . $m); } }

// Migración 028
$cols = array_column($pdo->query('PRAGMA table_info(cc_rsvps)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (["team", "gift_item_id", "baby_photo_key"] as $col) { r_check(in_array($col, $cols, true), "cc_rsvps.$col existe"); }

r_check(CB_REGALO_TOPE_PANALES === 3, 'tope de pañales 3');
r_check(cb_regalo_normalizar('Pañales  de  los Bebés!') === 'panal', 'normaliza tildes, relleno y plural (' . cb_regalo_normalizar('Pañales  de  los Bebés!') . ')');

$k = static fn(string $t): string => cb_regalo_clasificar($t)['clave'];
$c = static fn(string $t): string => cb_regalo_clasificar($t)['categoria'];

r_check($k('Coche de paseo') === 'coche' && $k('cochecito') === 'coche' && $k('COCHES') === 'coche', 'coche y sinónimos');
r_check($c('Coche de paseo') === 'unico', 'coche es único');
r_check($k('Silla para auto') === $k('silla de auto'), 'silla de auto equivalentes');
r_check($k('Cuna corral') === 'corral' && $k('cuna') === 'cuna' && $k('Moisés') === 'moises', 'cuna / corral / moisés');
r_check($c('Pañales talla RN') === 'panales' && $c('torta de pañales') === 'panales', 'pañales');
r_check($k('pañalera') === 'bolso-maternal', 'pañalera es bolso maternal');
foreach (['Mantita para la cuna', 'body', 'Gift card', 'dinero', 'toallitas húmedas', 'ropa para el coche', 'set ropa'] as $t) {
    r_check($c($t) === 'libre', "$t es libre");
}
foreach (['sorpresa', 'un regalito', 'Juguete', ''] as $t) { r_check($c($t) === 'generico', "'$t' es genérico"); }
r_check($c('juguete de madera') === 'unico', 'juguete de madera es único');

// Validación contra la invitación
$inv = (int) cb_create_invitation(['event_type' => 'baby_shower', 'admin_label' => 'prueba', 'birthday_person_name' => 'Bebé de prueba', 'created_by' => 'test'])['id'];
$inv2 = (int) cb_create_invitation(['event_type' => 'baby_shower', 'admin_label' => 'otra', 'birthday_person_name' => 'Otro', 'created_by' => 'test'])['id'];
r_check($inv > 0 && $inv2 > 0 && $inv !== $inv2, 'invitaciones de prueba creadas');
r_check(cb_regalo_validar($inv, '')['error'] === 'regalo_requerido', 'vacío = regalo_requerido');
r_check(cb_regalo_validar($inv, '   ')['error'] === 'regalo_requerido', 'espacios = regalo_requerido');
$g = cb_regalo_validar($inv, 'sorpresa');
r_check($g['ok'] === false && $g['error'] === 'regalo_generico' && $g['mensaje'] === 'Cuéntanos qué regalo llevarás, por ejemplo: un body, una mantita o un coche.', 'genérico con mensaje exacto');
r_check(cb_regalo_validar($inv, '')['mensaje'] === 'Cuéntanos qué regalo llevarás.', 'mensaje sin regalo exacto');
$ok = cb_regalo_validar($inv, 'Coche');
r_check($ok['ok'] === true && $ok['categoria'] === 'unico' && $ok['clave'] === 'coche', 'coche libre pasa');

function r_anotar(int $inv, string $titulo, string $mod = 'approved'): int {
    $id = cb_gift_add($inv, ['title' => $titulo], 'guest')['id'];
    cb_pdo()->prepare("UPDATE cc_gift_items SET status='taken', claimed_name='X', moderation_status=? WHERE id=?")->execute([$mod, $id]);
    return $id;
}
$cocheId = r_anotar($inv, 'Coche');
$t = cb_regalo_validar($inv, 'Cochecito');
r_check($t['ok'] === false && $t['error'] === 'regalo_tomado' && $t['mensaje'] === 'Ese regalo ya lo trae alguien, elige otro.', 'coche repetido');
r_check(cb_regalo_validar($inv, 'cuna')['ok'] === true, 'cuna no choca con coche');
r_check(cb_regalo_validar($inv, 'Mantita para la cuna')['ok'] === true, 'mantita para la cuna pasa');
for ($i = 0; $i < 3; $i++) { r_anotar($inv, 'Pañales talla ' . $i); }
$p = cb_regalo_validar($inv, 'pañales');
r_check($p['ok'] === false && $p['error'] === 'panales_completos' && $p['mensaje'] === 'Ya hay 3 personas que llevan pañales. Elige otro regalo, por favor.', 'cuarto pañal rechazado');
for ($i = 0; $i < 5; $i++) { r_anotar($inv, 'body'); }
r_check(cb_regalo_validar($inv, 'body')['ok'] === true, 'body libre aun con 5 anotados');
// un coche oculto libera el cupo
$pdo->prepare("UPDATE cc_gift_items SET moderation_status='hidden' WHERE id=?")->execute([$cocheId]);
r_check(cb_regalo_validar($inv, 'coche')['ok'] === true, 'coche oculto ya no bloquea');
r_check(cb_regalo_validar($inv2, 'pañales')['ok'] === true, 'otra invitación no se mezcla');

echo "OK $tests checks regalos-confirmacion\n";
