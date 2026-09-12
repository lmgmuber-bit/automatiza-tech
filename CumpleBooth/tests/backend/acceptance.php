<?php
/** Pruebas aisladas de la aceptación de Términos + firma (SQLite temporal, sin correo). */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-acceptance-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/acceptance.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('c', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_ACCEPTANCE_DIR=' . $tmp . '/acceptances');
putenv('CUMPLECLICK_CONFIG_FILE=' . $tmp . '/no-config.php');
require dirname(__DIR__, 2) . '/public/lib.php';
// El esquema completo, siempre al día. Antes cada prueba listaba las migraciones a mano y
// esa lista se quedaba atrás con cada migración nueva.
require_once __DIR__ . '/_migraciones.php';
cb_test_migrar_todo(cb_pdo());

require dirname(__DIR__, 2) . '/public/lib.acceptance.php';

if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDOUT, "SKIP: la prueba de firma requiere la extensión GD.\n");
    exit(0);
}

$tests = 0;
function acc_check(bool $condition, string $message): void {
    global $tests;
    $tests++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
/** PNG RGBA con fondo transparente; con $ink dibuja trazos, sin $ink queda vacío. */
function acc_signature_png(bool $ink): string {
    $img = imagecreatetruecolor(600, 200);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    if ($ink) {
        $black = imagecolorallocatealpha($img, 20, 20, 20, 0);
        imagesetthickness($img, 4);
        for ($i = 0; $i < 40; $i++) {
            imageline($img, 40 + $i * 12, 60 + (($i % 3) * 30), 52 + $i * 12, 120 - (($i % 4) * 20), $black);
        }
    }
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode($png);
}

$run = static fn(string $file) => (require dirname(__DIR__, 2) . '/database/migrations/' . $file)(cb_pdo());
// Todo el esquema hasta 011: `cb_save_parties()` de esta línea escribe columnas que
// migraciones posteriores a la 004 agregaron (tipo de evento, galería, juegos).
foreach (['001_initial', '002_theme_prompts', '003_invitations_and_plan', '004_gate_a_corrections',
          '005_theme_prompt_history', '006_public_leads', '007_event_album', '008_event_profiles',
          '009_invitation_gender', '010_baby_shower_predictions', '011_gift_mode'] as $m) {
    $run($m . '.php');
}

// Fiestas previas a la migración 013: una activa (queda eximida) y una inactiva.
$pdo = cb_pdo();
$slugLegacy = cb_generate_public_slug($pdo, 'Legado', 'Aventura Perruna');
$slugNew = cb_generate_public_slug($pdo, 'Sofía', 'Aventura Perruna');
$slugDemo = cb_generate_public_slug($pdo, 'Demo', 'Aventura Perruna');
$base = ['tema' => 'familia-canina', 'theme_slug' => 'familia-canina', 'fecha' => '2026-10-15', 'service_plan' => 'full', 'invitados' => [], 'frameBox' => null];
acc_check(cb_save_parties(['parties' => [
    $slugLegacy => $base + ['public_slug' => $slugLegacy, 'admin_label' => 'LEGADO', 'birthday_person_name' => 'Legado', 'activa' => true],
    $slugNew => $base + ['public_slug' => $slugNew, 'admin_label' => 'SOFÍA 6', 'birthday_person_name' => 'Sofía', 'activa' => false],
    $slugDemo => $base + ['public_slug' => $slugDemo, 'admin_label' => 'DEMO', 'birthday_person_name' => 'Demo', 'activa' => false],
]]), 'guarda fiestas de prueba');
$run('013_plan_acceptances.php');
acc_check(cb_party_acceptance_state($slugLegacy)['status'] === 'waived', 'fiesta activa previa queda eximida por la migración');
acc_check(cb_party_acceptance_state($slugNew)['status'] === 'none', 'fiesta inactiva previa queda sin aceptación');
acc_check(cb_party_can_activate($slugLegacy) && !cb_party_can_activate($slugNew), 'solo la eximida puede activarse');

// Documentos legales
$bundle = cb_legal_bundle();
acc_check($bundle['version'] === CB_LEGAL_VERSION && count($bundle['documents']) === 3, 'bundle legal con 3 documentos y versión');
acc_check(preg_match('/^[a-f0-9]{64}$/', $bundle['sha256']) === 1, 'hash del texto legal');
acc_check(strpos($bundle['documents']['terminos']['html'], '<h2>') !== false && strpos($bundle['documents']['privacidad']['html'], '<table>') !== false, 'markdown renderiza títulos y tablas');
$md = cb_markdown_to_html("# T\n\nHola **mundo** <b>x</b>\n\n- a\n- b\n\n1. uno\n2. dos\n");
acc_check(strpos($md, '<strong>mundo</strong>') !== false && strpos($md, '&lt;b&gt;') !== false && strpos($md, '<ul>') !== false && strpos($md, '<ol>') !== false, 'markdown escapa HTML y soporta listas');

// RUT
acc_check(cb_valid_rut('12.345.678-5') && cb_valid_rut('11111111-1') && !cb_valid_rut('12.345.678-9') && !cb_valid_rut('abc'), 'validación de RUT módulo 11');

// Emisión del enlace
$party = cb_load_parties()['parties'][$slugNew];
$missing = cb_create_plan_acceptance($party, ['client_name' => 'Ana Pérez', 'client_email' => 'ana@example.test'], 'test');
acc_check(!$missing['ok'] && isset($missing['errors']['price_total']), 'exige valor total del plan');
$issue = ['client_name' => 'Ana Pérez', 'client_email' => 'Ana@Example.test', 'client_phone' => '+56 9 1111 2222', 'expires_days' => 14,
    'summary' => ['price_total' => '$49.995', 'deposit' => '$25.000', 'event_address' => 'Av. Siempre Viva 123, Maipú', 'event_time' => '16:00']];
$first = cb_create_plan_acceptance($party, $issue, 'test');
acc_check($first['ok'] && preg_match('/^[a-f0-9]{32}$/', $first['token']) === 1, 'emite token opaco de 128 bits');
acc_check(strpos($first['url'], 'https://example.test/cumpleclick/aceptar-plan.php?t=') === 0, 'URL pública de aceptación');
$row = cb_load_acceptance_by_token_hash(cb_hash_token($first['token']));
acc_check($row && $row['status'] === 'pending' && $row['client_email'] === 'ana@example.test' && $row['plan_summary']['plan_name'] === 'Plan Premium', 'fila pending con resumen del plan');
acc_check($row['legal_text_sha256'] === $bundle['sha256'] && $row['legal_version'] === CB_LEGAL_VERSION, 'la fila fija versión y hash del texto enviado');
acc_check(strpos(json_encode($row), $first['token']) === false, 'el token en claro no se persiste');
$second = cb_create_plan_acceptance($party, $issue, 'test');
acc_check($second['ok'] && cb_load_acceptance_by_id((int) $first['id'])['status'] === 'revoked', 'un enlace nuevo revoca el pending anterior');
acc_check(cb_party_acceptance_state($slugNew)['status'] === 'pending' && !cb_party_can_activate($slugNew), 'pending no permite activar');
$row = cb_load_acceptance_by_token_hash(cb_hash_token($second['token']));
cb_acceptance_register_view((int) $row['id']);
acc_check((int) cb_load_acceptance_by_id((int) $row['id'])['view_count'] === 1, 'registra visualización');

// Nonce sin sesión
$nonce = cb_acceptance_form_nonce($row['public_token_hash'], time());
acc_check(cb_acceptance_form_nonce_valid($row['public_token_hash'], $nonce), 'nonce válido');
acc_check(!cb_acceptance_form_nonce_valid($row['public_token_hash'], cb_acceptance_form_nonce($row['public_token_hash'], time() - 90000)), 'nonce vencido');
acc_check(!cb_acceptance_form_nonce_valid('otro', $nonce), 'nonce ligado al enlace');

// Validación del formulario
$good = ['accepted_terms' => '1', 'accepted_privacy' => '1', 'accepted_minors' => '1', 'accepted_marketing' => '1',
    'signer_name' => 'Ana María Pérez Soto', 'signer_rut' => '12.345.678-5', 'signer_email' => 'ana@example.test',
    'signer_relationship' => 'madre', 'signature_dataurl' => acc_signature_png(true), 'website' => ''];
$v = cb_validate_acceptance_submission(array_merge($good, ['accepted_privacy' => '0', 'signer_rut' => '12.345.678-9', 'signature_dataurl' => acc_signature_png(false)]));
acc_check(!$v['ok'] && isset($v['errors']['accepted_privacy'], $v['errors']['signer_rut'], $v['errors']['signature']), 'rechaza casilla faltante, RUT malo y firma vacía');
acc_check(!cb_validate_acceptance_submission(array_merge($good, ['signature_dataurl' => 'data:image/png;base64,' . base64_encode('no-es-png')]))['ok'], 'rechaza firma que no es PNG');
acc_check(!cb_validate_acceptance_submission(array_merge($good, ['website' => 'bot']))['ok'], 'honeypot');
acc_check(cb_validate_acceptance_submission($good)['ok'], 'acepta envío válido');

// Aceptación
$ctx = ['ip' => '203.0.113.9', 'user_agent' => 'CumpleClickTest/1.0', 'meta' => ['zona_horaria' => 'America/Santiago', 'pantalla' => '390x844']];
$accepted = cb_accept_plan($row, $good, $ctx);
acc_check($accepted['ok'] && preg_match('/^[a-f0-9]{32}$/', $accepted['receipt_token']) === 1, 'acepta y emite token de comprobante');
$final = cb_load_acceptance_by_id((int) $row['id']);
acc_check($final['status'] === 'accepted' && $final['signer_rut'] === '12345678-5' && (int) $final['accepted_marketing'] === 1 && $final['ip_address'] === '203.0.113.9', 'fila aceptada con firmante, marketing e IP');
acc_check(cb_load_acceptance_by_token_hash(cb_hash_token($second['token'])) === null, 'el enlace de aceptación deja de servir tras firmar');
acc_check(cb_load_acceptance_by_receipt_hash(cb_hash_token($accepted['receipt_token']))['id'] == $row['id'], 'el comprobante se resuelve por su propio token');
$evidencePath = cb_acceptance_file_path($final['evidence_storage_key']);
$signaturePath = cb_acceptance_file_path($final['signature_storage_key']);
acc_check(is_file($evidencePath) && is_file($signaturePath), 'firma y comprobante en directorio privado');
acc_check(hash_file('sha256', $evidencePath) === $final['evidence_sha256'] && hash_file('sha256', $signaturePath) === $final['signature_sha256'], 'hashes de archivos coinciden con la BD');
$html = (string) file_get_contents($evidencePath);
acc_check(strpos($html, 'Ana María Pérez Soto') !== false && strpos($html, $bundle['sha256']) !== false && strpos($html, '[X] Uso promocional') !== false && strpos($html, 'data:image/png;base64,') !== false, 'comprobante contiene firmante, hash legal, casillas y firma');
acc_check(strpos($html, 'Términos y Condiciones del Servicio CumpleClick') !== false, 'comprobante incluye el texto íntegro');
acc_check(strpos($html, 'America/Santiago') !== false, 'comprobante incluye metadatos del navegador');
acc_check(!cb_accept_plan($final, $good, $ctx)['ok'], 'no se puede aceptar dos veces');
acc_check(cb_party_acceptance_state($slugNew)['status'] === 'accepted' && cb_party_can_activate($slugNew), 'aceptación firmada permite activar');
$afterAccepted = cb_create_plan_acceptance($party, $issue, 'test');
acc_check($afterAccepted['ok'] && cb_party_acceptance_state($slugNew)['status'] === 'accepted', 'un enlace nuevo (re-aceptación) no pisa la aceptación firmada vigente');
$states = cb_acceptance_states_by_slug();
acc_check(($states[$slugNew] ?? '') === 'accepted' && ($states[$slugLegacy] ?? '') === 'waived', 'estados por slug para el listado');

// Exención y revocación
acc_check(!cb_waive_acceptance($party, 'Prueba interna', 'test')['ok'], 'no exime una fiesta ya firmada');
$demo = cb_load_parties()['parties'][$slugDemo];
acc_check(!cb_waive_acceptance($demo, 'x', 'test')['ok'], 'exención exige motivo');
acc_check(cb_waive_acceptance($demo, 'Fiesta demo para ferias', 'test')['ok'] && cb_party_can_activate($slugDemo), 'exención con motivo permite activar');
$waivedRow = cb_party_acceptance_state($slugDemo)['row'];
acc_check(cb_revoke_acceptance((int) $waivedRow['id'], 'test') && !cb_party_can_activate($slugDemo), 'revocar la exención vuelve a bloquear');

// Vencimiento
$pending = cb_create_plan_acceptance($demo, $issue, 'test');
$pdo->prepare('UPDATE cc_plan_acceptances SET expires_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s', time() - 60), (int) $pending['id']]);
$expiredRow = cb_load_acceptance_by_id((int) $pending['id']);
acc_check(cb_acceptance_is_expired($expiredRow) && cb_party_acceptance_state($slugDemo)['status'] === 'expired', 'detecta enlace vencido');
acc_check(!cb_accept_plan($expiredRow, $good, $ctx)['ok'], 'no acepta con enlace vencido');

// La evidencia sobrevive al borrado de la fiesta (FK SET NULL + snapshot).
acc_check(cb_save_parties(['parties' => [$slugLegacy => cb_load_parties()['parties'][$slugLegacy]]]), 'borra fiestas salvo la legado');
$survivor = cb_load_acceptance_by_id((int) $row['id']);
acc_check($survivor && $survivor['status'] === 'accepted' && $survivor['party_id'] === null && $survivor['party_public_slug'] === $slugNew, 'la evidencia sobrevive al borrado de la fiesta');

fwrite(STDOUT, "OK $tests checks aceptación\n");
