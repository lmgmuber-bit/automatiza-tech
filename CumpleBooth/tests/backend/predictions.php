<?php
/** Pruebas aisladas de migración 010, predicciones y token de los papás. */
if (PHP_SAPI !== 'cli') { exit(2); }

$tmp = sys_get_temp_dir() . '/cumpleclick-predictions-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});

putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/predictions.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('p', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_INVITATION_DIR=' . $tmp . '/invitations');

$root = dirname(__DIR__, 2);
require $root . '/public/lib.php';
foreach ([
    '001_initial', '002_theme_prompts', '003_invitations_and_plan',
    '004_gate_a_corrections', '005_theme_prompt_history', '006_public_leads',
    '007_event_album', '008_event_profiles', '009_invitation_gender',
] as $version) {
    $migration = require $root . '/database/migrations/' . $version . '.php';
    $migration(cb_pdo());
}

$tests = 0;
function prediction_check(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}

$up = require $root . '/database/migrations/010_baby_shower_predictions.php';
$up(cb_pdo());
$up(cb_pdo());
$tables = cb_pdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['cc_gift_items', 'cc_invitation_tokens', 'cc_predictions'] as $table) {
    prediction_check(in_array($table, $tables, true), "010 crea $table y tolera reaplicación");
}
$invitationColumns = array_column(cb_pdo()->query('PRAGMA table_info(cc_invitations)')->fetchAll(), 'name');
prediction_check(in_array('event_type', $invitationColumns, true), '010 agrega event_type a invitaciones');

prediction_check(cb_save_parties(['parties' => [
    'baby-demo' => [
        'admin_label' => 'Baby Demo', 'birthday_person_name' => 'Bebé Demo',
        'event_type' => 'baby_shower', 'tema' => 'hielo', 'fecha' => '2026-10-20',
        'activa' => true, 'service_plan' => 'full', 'invitados' => [],
        'frameBox' => ['x' => .2, 'y' => .2, 'w' => .4, 'h' => .4], 'creada' => gmdate('Y-m-d H:i:s'),
    ],
    'birthday-demo' => [
        'admin_label' => 'Cumple Demo', 'birthday_person_name' => 'Emilia',
        'event_type' => 'child_birthday', 'tema' => 'carreras', 'fecha' => '2026-10-21',
        'activa' => true, 'service_plan' => 'booth', 'invitados' => [],
        'frameBox' => ['x' => .2, 'y' => .2, 'w' => .4, 'h' => .4], 'creada' => gmdate('Y-m-d H:i:s'),
    ],
]]), 'crea eventos de ambas modalidades');
$babyId = cb_party_db_id('baby-demo');
$birthdayId = cb_party_db_id('birthday-demo');
prediction_check(is_int($babyId) && is_int($birthdayId), 'resuelve ids internos');
$resolvedBaby = cb_resolve_party('baby-demo');
prediction_check(($resolvedBaby['party']['event_type'] ?? '') === 'baby_shower', 'API publica modalidad baby shower');
prediction_check(empty((array) ($resolvedBaby['theme']['fullGame'] ?? [])), 'baby shower no recibe Show 3D aunque el plan sea full');

$babyInvitation = cb_create_invitation([
    'party_id' => $babyId, 'theme_slug' => 'hielo', 'event_type' => 'baby_shower',
    'birthday_person_name' => 'Bebé Demo', 'event_date' => '2026-10-20',
    'event_time' => '16:00', 'address' => 'Lugar de prueba', 'created_by' => 'qa',
]);
$birthdayInvitation = cb_create_invitation([
    'party_id' => $birthdayId, 'theme_slug' => 'carreras', 'event_type' => 'child_birthday',
    'birthday_person_name' => 'Emilia', 'event_date' => '2026-10-21',
    'event_time' => '17:00', 'address' => 'Lugar de prueba', 'created_by' => 'qa',
]);
prediction_check($babyInvitation['ok'] && $birthdayInvitation['ok'], 'crea invitaciones con modalidad');

$savedParties = cb_load_parties()['parties'];
$savedParties['baby-demo']['event_type'] = 'child_birthday';
prediction_check(cb_save_parties(['parties' => $savedParties]), 'permite cambiar la modalidad del evento');
$linkedEventType = cb_pdo()->query('SELECT event_type FROM cc_invitations WHERE id=' . (int) $babyInvitation['id'])->fetchColumn();
prediction_check($linkedEventType === 'child_birthday', 'sincroniza modalidad en invitaciones vinculadas');
$savedParties['baby-demo']['event_type'] = 'baby_shower';
prediction_check(cb_save_parties(['parties' => $savedParties]), 'restaura la modalidad baby shower');
$linkedEventType = cb_pdo()->query('SELECT event_type FROM cc_invitations WHERE id=' . (int) $babyInvitation['id'])->fetchColumn();
prediction_check($linkedEventType === 'baby_shower', 'la sincronización funciona en ambos sentidos');

$basePrediction = ['parecido' => 'ambos', 'peso' => 'entre', 'fecha' => 'justo'];
$firstSubmission = null;
foreach ([['Ana', 12], ['Luis', 9], ['Sofía', 15]] as $index => [$name, $score]) {
    $submission = str_pad(dechex($index + 1), 32, '0', STR_PAD_LEFT);
    $firstSubmission ??= $submission;
    $saved = cb_prediction_create_for_party($babyId, array_merge($basePrediction, [
        'guest_name' => $name, 'puntaje_juego' => $score, 'submission_token' => $submission,
    ]));
    prediction_check($saved['ok'], "guarda predicción de $name");
}
$duplicate = cb_prediction_create_for_party($babyId, array_merge($basePrediction, [
    'guest_name' => 'Ana', 'puntaje_juego' => 12, 'submission_token' => $firstSubmission,
]));
prediction_check($duplicate['ok'] && !empty($duplicate['duplicate']), 'un reintento devuelve la predicción existente');
$predictions = cb_prediction_list_for_party($babyId);
prediction_check(count($predictions) === 3, 'tablero del evento reúne tres predicciones');
prediction_check(cb_prediction_list_for_party($birthdayId) === [], 'no mezcla predicciones entre eventos');
prediction_check(!cb_prediction_validate(array_merge($basePrediction, ['guest_name' => '', 'parecido' => 'inventado', 'submission_token' => str_repeat('a', 32)]))['ok'], 'valida nombre y listas blancas');

$parentsToken = cb_invitation_issue_role_token((int) $babyInvitation['id'], 'parents', null, 'qa');
$access = cb_invitation_resolve_role_token($parentsToken, 'parents');
prediction_check($access !== null && (int) $access['party_id'] === $babyId, 'token de papás resuelve invitación y evento');
prediction_check(strpos(cb_prediction_board_url($parentsToken), '/predicciones.php?t=') !== false, 'construye URL privada del tablero');
cb_invitation_revoke_role_tokens((int) $babyInvitation['id'], 'parents');
prediction_check(cb_invitation_resolve_role_token($parentsToken, 'parents') === null, 'token revocado falla cerrado');

$expiredToken = cb_invitation_issue_role_token((int) $babyInvitation['id'], 'parents', gmdate('Y-m-d H:i:s', time() - 60), 'qa');
prediction_check(cb_invitation_resolve_role_token($expiredToken, 'parents') === null, 'token vencido falla cerrado');
$birthdayToken = cb_invitation_issue_role_token((int) $birthdayInvitation['id'], 'parents', null, 'qa');
prediction_check(cb_invitation_resolve_role_token($birthdayToken, 'parents') === null, 'token no abre tablero para cumpleaños');

$down = require $root . '/database/migrations/010_baby_shower_predictions.down.php';
$down(cb_pdo());
$tablesAfterDown = cb_pdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
prediction_check(!in_array('cc_predictions', $tablesAfterDown, true) && !in_array('cc_gift_items', $tablesAfterDown, true), 'down elimina sólo tablas 010');
prediction_check(in_array('cc_parties', $tablesAfterDown, true) && in_array('cc_invitations', $tablesAfterDown, true), 'down conserva datos y tablas anteriores');
$up(cb_pdo());
prediction_check(in_array('cc_predictions', cb_pdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN), true), '010 se puede reaplicar después de down');

echo "OK: {$tests} pruebas de predicciones.\n";
