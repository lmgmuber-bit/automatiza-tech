<?php
/** Suite sin dependencias externas para el backend PHP. */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db'); putenv('CC_PDO_DSN=sqlite:' . $tmp . '/test.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('a', 64)); putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos'); putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_INVITATION_DIR=' . $tmp . '/invitations');
require dirname(__DIR__, 2) . '/public/lib.php';

$tests = 0;
function check(bool $condition, string $message): void { global $tests; $tests++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } }
$migration = require dirname(__DIR__, 2) . '/database/migrations/001_initial.php';
$migration(cb_pdo());
$promptMigration = require dirname(__DIR__, 2) . '/database/migrations/002_theme_prompts.php';
$promptMigration(cb_pdo());
$invitationsMigration = require dirname(__DIR__, 2) . '/database/migrations/003_invitations_and_plan.php';
$invitationsMigration(cb_pdo());
$gateACorrections = require dirname(__DIR__, 2) . '/database/migrations/004_gate_a_corrections.php';
$gateACorrections(cb_pdo());
$promptHistoryMigration = require dirname(__DIR__, 2) . '/database/migrations/005_theme_prompt_history.php';
$promptHistoryMigration(cb_pdo());
$leadMigration = require dirname(__DIR__, 2) . '/database/migrations/006_public_leads.php';
$leadMigration(cb_pdo());
$albumMigration = require dirname(__DIR__, 2) . '/database/migrations/007_event_album.php';
$albumMigration(cb_pdo());
$profileMigration = require dirname(__DIR__, 2) . '/database/migrations/008_event_profiles.php';
$profileMigration(cb_pdo());
$genderMigration = require dirname(__DIR__, 2) . '/database/migrations/009_invitation_gender.php';
$genderMigration(cb_pdo());
$babyShowerMigration = require dirname(__DIR__, 2) . '/database/migrations/010_baby_shower_predictions.php';
$babyShowerMigration(cb_pdo());
$narrationMigration = require dirname(__DIR__, 2) . '/database/migrations/013_narration_intro_output.php';
$narrationMigration(cb_pdo());
check(cb_storage_mode() === 'db', 'modo DB');
check(cb_valid_slug('fiesta-1', 2, 40) && !cb_valid_slug('../x', 2, 40), 'slugs estrictos');
check(cb_normalize_frame_box(['x'=>.2,'y'=>.2,'w'=>.4,'h'=>.4]) !== null, 'frame válido');
check(cb_normalize_frame_box(['x'=>.8,'y'=>.2,'w'=>.4,'h'=>.4]) === null, 'frame fuera del lienzo');
check(cb_normalize_frame_box(['x'=>.2,'y'=>.2,'w'=>.01,'h'=>.4]) === null, 'frame mínimo alineado con frontend');
$oldDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$_SERVER['DOCUMENT_ROOT'] = $tmp;
try { cb_private_dir($tmp . '/state', 'state_dir'); $privateRejected = false; } catch (RuntimeException $e) { $privateRejected = true; }
check($privateRejected, 'storage dentro del document root se rechaza');
if ($oldDocRoot === null) { unset($_SERVER['DOCUMENT_ROOT']); } else { $_SERVER['DOCUMENT_ROOT'] = $oldDocRoot; }

$source = ['parties' => ['demo' => [
    'nombre'=>'Demo','tema'=>'carreras','fecha'=>'2026-08-15','activa'=>true,
    'invitados'=>[['name'=>'Ana','g'=>'f'],['name'=>'Luis','g'=>'m']],
    'frameBox'=>['x'=>.2,'y'=>.2,'w'=>.4,'h'=>.4], 'galeriaPin'=>'1234', 'creada'=>'2026-07-13 12:00:00',
]]];
check(cb_save_parties($source), 'guardar fiesta DB');
$loaded = cb_load_parties(); $party = $loaded['parties']['demo'];
check(!isset($party['galeriaPin']) && !empty($party['galeriaPinHash']) && empty($party['galeriaPinHmac']), 'PIN usa password_hash(HMAC) y no queda en claro');
check(cb_verify_party_pin($party, '1234') && !cb_verify_party_pin($party, '0000'), 'verificación PIN doble');
$resolved = cb_resolve_party('demo');
check($resolved['ok'] && count($resolved['party']['invitados']) === 2, 'resolver party+theme');
check(!isset($resolved['party']['galeriaPinHash']) && !isset($resolved['party']['galeriaPin']), 'API no expone PIN');
$themesData = cb_load_themes();
$carreras = $themesData['themes']['carreras'];
$carrerasBoothPayload = cb_build_theme_payload('carreras', $carreras, null, 'booth');
$carrerasFullPayload = cb_build_theme_payload('carreras', $carreras, null, 'full');
check(
    ($carrerasBoothPayload['videoEstrella'] ?? '') === ''
    && ($carrerasFullPayload['videoEstrella'] ?? '') === 'themes/carreras/rayo-mcqueen-estrella.mp4',
    'API publica el video estrella solo para el plan Full y con ruta segura'
);
$familiaCaninaPayload = cb_build_theme_payload('familia-canina', $themesData['themes']['familia-canina']);
check(
    ($familiaCaninaPayload['photoSession']['video'] ?? '') === 'themes/familia-canina/transicion-sesion-fotos.mp4'
    && ($familiaCaninaPayload['photoSession']['poster'] ?? '') === 'themes/familia-canina/transicion-alfombra-base-v1.png',
    'API publica assets seguros de la sesion fotografica tematica'
);
check(
    ($familiaCaninaPayload['videos']['welcome'] ?? '') === 'themes/familia-canina/welcome-familia-canina.mp4',
    'API publica el video de bienvenida propio del tema'
);
check(
    ($familiaCaninaPayload['images']['roulette'] ?? '') === 'themes/familia-canina/roulette/roulette-background-v1.png',
    'API publica el fondo de ruleta propio del tema'
);
check(
    ($familiaCaninaPayload['nombre'] ?? '') === 'Bluey'
    && !array_key_exists('franquicia', $familiaCaninaPayload)
    && array_column($familiaCaninaPayload['personajes'] ?? [], 'name') === ['Bluey', 'Bingo', 'Bandit', 'Chilli', 'Muffin', 'Chloe'],
    'sitio publica nombres reales de tema/personajes sin exponer la franquicia administrativa'
);
$kpop = $themesData['themes']['kpop'];
$heroes = $themesData['themes']['heroes'];
check(
    cb_sanitize_theme_game(['kind' => 'ritmo', 'lanes' => 9])['lanes'] === 5
    && cb_sanitize_theme_game(['kind' => 'ritmo', 'lanes' => 1])['lanes'] === 3,
    'backend limita ritmo a 3-5 carriles'
);
$escudo = cb_sanitize_theme_game(['kind' => 'escudo', 'cols' => 4, 'filas' => 4]);
check($escudo['kind'] === 'escudo' && !isset($escudo['cols']) && !isset($escudo['filas']), 'escudo no publica grilla');
$heroCopos = cb_sanitize_theme_game(['kind' => 'copos', 'emojis' => ['⚡', '🔨', '💥', '✨']]);
check($heroCopos['emojis'] === ['⚡', '🔨', '💥', '✨'], 'backend publica emojis temáticos de copos');
check(
    array_column($kpop['personajes'][0]['game'], 'kind') === ['ritmo', 'fichas', 'copos']
    && array_column($heroes['personajes'][3]['game'], 'kind') === ['escudo', 'fichas', 'copos'],
    'K-Pop y Héroes conservan cadenas ordenadas de juegos'
);
check(cb_theme_prompt_asset_allowed($carreras, 'fondo-banner.jpg') && !cb_theme_prompt_asset_allowed($carreras, '../fondo-banner.jpg'), 'allowlist estricta de assets para prompts');
$cutSlot = cb_theme_upload_slot_by_name($carreras, 'rayo-mcqueen-cut.png');
check(($cutSlot['requires_alpha'] ?? false) === true, 'recortes de personaje exigen canal alfa');
check(
    !cb_validate_theme_prompt($carreras, 'fondo-banner.jpg', 'An original racing party scene')['ok'],
    'prompt multimedia exige cierre de camuflaje'
);
check(!cb_validate_theme_prompt($carreras, 'fondo-banner.jpg', 'Cars party scene')['ok'], 'camuflaje rechaza franquicia');
check(!cb_validate_theme_prompt($carreras, 'rayo-mcqueen.jpg', 'Rayo McQueen sonríe')['ok'], 'camuflaje rechaza personaje');
$safePrompt = 'An original glossy red race car toy with large friendly windshield eyes in a bright birthday room. No text or logos. This is an original toy design, not based on any existing character.';
check(cb_save_theme_prompt('carreras', $carreras, 'fondo-banner.jpg', $safePrompt)['ok'], 'guardar prompt privado');
$storedPrompts = cb_load_theme_prompts('carreras', $carreras);
check(($storedPrompts['fondo-banner.jpg']['prompt'] ?? '') === $safePrompt, 'leer prompt privado desde BD');
$resolvedAfterPrompt = cb_resolve_party('demo');
check(!isset($resolvedAfterPrompt['theme']['prompts']) && strpos(json_encode($resolvedAfterPrompt), $safePrompt) === false, 'API pública no expone prompts');
$promptDocument = file_get_contents(dirname(__DIR__, 2) . '/docs/PROMPTS-TEMATICAS.md');
$parsedPrompts = cb_parse_theme_prompts_markdown((string) $promptDocument, $themesData['themes']);
$parsedCount = 0;
foreach ($parsedPrompts['prompts'] as $rows) { $parsedCount += count($rows); }
check($parsedCount === 78 && empty($parsedPrompts['issues']), 'importador reconoce 78 prompts camuflados');
check(cb_save_theme_prompt('carreras', $carreras, 'fondo-banner.jpg', '')['ok'] && cb_load_theme_prompts('carreras', $carreras) === [], 'vaciar prompt elimina asociación');

$rate1 = cb_rate_limit('test', '127.0.0.1', 2, 60, 60);
$rate2 = cb_rate_limit('test', '127.0.0.1', 2, 60, 60);
$rate3 = cb_rate_limit('test', '127.0.0.1', 2, 60, 60);
check($rate1['allowed'] && $rate2['allowed'] && !$rate3['allowed'], 'rate limit persistente');

$token = bin2hex(random_bytes(16));
$record = ['token'=>$token,'storage_key'=>'demo/2026/07/'.$token.'.png','original_name'=>'foto.png','byte_size'=>123,'width'=>100,'height'=>100,'sha256'=>str_repeat('b',64),'created_at'=>'2026-07-13 12:30:00'];
// Los public_slug opacos pueden superar 40 caracteres (el contrato permite 80).
$longPublicSlug = 'demo-bluey-aventura-perruna-' . str_repeat('a', 30);
check(
    cb_valid_public_slug($longPublicSlug)
    && cb_photo_absolute_path($longPublicSlug . '/2026/07/' . $token . '.png') !== null,
    'storage de foto acepta public_slug opaco largo'
);
check(cb_record_photo('demo', $record), 'metadata foto');
$found = cb_find_photo_by_token($token);
check($found !== null && $found['party_slug'] === 'demo', 'lookup token 128-bit');
check(cb_find_photo_by_token('../bad') === null, 'token estricto');
check(cb_photo_usage('demo')['count'] === 1, 'cuota por fiesta');
$token2 = bin2hex(random_bytes(16));
$record2 = array_merge($record, ['token'=>$token2,'storage_key'=>'demo/2026/07/'.$token2.'.png']);
check(cb_record_photo_with_quota('demo', $record2, 1, 1073741824) === 'quota', 'cuota atómica bajo lock');

// Gate A: invitaciones
$demoPartyId = cb_party_db_id('demo');
check($demoPartyId !== null, 'resolver id interno de fiesta demo');

$draft = cb_create_invitation([
    'party_id' => $demoPartyId,
    'theme_slug' => 'carreras',
    'admin_label' => 'TEST',
    'birthday_person_name' => '',
    'event_date' => '',
    'event_time' => '',
    'address' => '',
    'message' => '',
    'language' => 'es',
    'channel' => 'whatsapp',
    'created_by' => 'test',
]);
check(!empty($draft['ok']), 'crear borrador incompleto');
$invId = (int) $draft['id'];
$invToken = (string) $draft['token'];

$loaded = cb_load_invitation_by_id($invId);
check($loaded !== null && (string) $loaded['status'] === 'draft', 'borrador se carga en estado draft');
check(cb_invitation_mandatory_missing($loaded) === ['nombre del cumpleañero', 'fecha', 'hora', 'dirección'], 'detecta todos los datos obligatorios faltantes');

$pubRejected = cb_publish_invitation($invId, 'test');
check(!$pubRejected['ok'], 'publicar borrador incompleto rechazado');
check(strpos($pubRejected['error'] ?? '', 'Faltan datos obligatorios') === 0, 'mensaje de rechazo indica campos faltantes');

$updateOk = cb_update_invitation($invId, [
    'birthday_person_name' => 'Martina',
    'event_date' => '2026-08-15',
    'event_time' => '15:00',
    'address' => 'Salón Arcoíris, Santiago',
    'message' => '¡Nos vemos!',
], 'test');
check($updateOk, 'completar datos de la invitación');

$compiled = cb_compile_invitation_prompt(cb_default_invitation_prompt_template(), [
    'birthday_person_name' => 'Martina',
    'event_date' => '2026-08-15',
    'event_time' => '15:00',
    'address' => 'Salón Arcoíris, Santiago',
]);
check($compiled['ok'] && strpos($compiled['prompt'], '[Martina') === false && strpos($compiled['prompt'], '[') === false, 'prompt compilado sin placeholders residuales');

// Compilador estricto (Gate A hallazgo #5): un campo obligatorio vacío se
// rechaza por separado — no alcanza con que no quede ningún "[...]" residual,
// porque strtr() reemplaza un placeholder ausente por '' y eso no deja rastro.
$fullValues = ['birthday_person_name' => 'Martina', 'event_date' => '2026-08-15', 'event_time' => '15:00', 'address' => 'Salón Arcoíris, Santiago'];
foreach (['birthday_person_name', 'event_date', 'event_time', 'address'] as $field) {
    $withOneEmpty = $fullValues;
    $withOneEmpty[$field] = '';
    $result = cb_compile_invitation_prompt(cb_default_invitation_prompt_template(), $withOneEmpty);
    check(!$result['ok'] && $result['prompt'] === '', "compilador rechaza '$field' vacío por separado (sin dejar placeholder residual detectable)");
}
$withOnlySpaces = $fullValues;
$withOnlySpaces['address'] = '   ';
check(!cb_compile_invitation_prompt(cb_default_invitation_prompt_template(), $withOnlySpaces)['ok'], 'compilador rechaza campo con solo espacios (trim antes de validar)');

$missing = cb_invitation_mandatory_missing(cb_load_invitation_by_id($invId));
check(empty($missing), 'datos obligatorios completos');

$pubNoOutput = cb_publish_invitation($invId, 'test');
check(!$pubNoOutput['ok'], 'publicar sin output aprobado rechazado');

$invDir = cb_invitation_dir();
if (!is_dir($invDir)) { mkdir($invDir, 0770, true); }
$storageKey = cb_invitation_storage_key('demo', 'personalized-image', 1, 'png');
$dstPath = cb_invitation_file_path($storageKey);
if ($dstPath) {
    $dstDir = dirname($dstPath);
    if (!is_dir($dstDir)) { mkdir($dstDir, 0770, true); }
    file_put_contents($dstPath, str_repeat('x', 100));
}
$saveOutput = cb_save_invitation_output($invId, [
    'output_type' => 'personalized_image',
    'asset_key' => 'personalized-image',
    'file_storage_key' => $storageKey,
    'status' => 'pending',
    'file_mime' => 'image/png',
    'file_byte_size' => 100,
    'file_sha256' => hash('sha256', str_repeat('x', 100)),
]);
check(!empty($saveOutput['ok']), 'guardar output pendiente');

$pendingOutputs = cb_invitation_approved_outputs($invId);
check(empty($pendingOutputs), 'output pendiente no cuenta como aprobado');

$outputId = (int) $saveOutput['id'];
check(cb_update_invitation_output_status($outputId, 'approved', 'test'), 'aprobar output de imagen');

$approvedOutputs = cb_invitation_approved_outputs($invId);
check(count($approvedOutputs) === 1 && (string) $approvedOutputs[0]['output_type'] === 'personalized_image', 'output aprobado resuelto correctamente');

$pubOk = cb_publish_invitation($invId, 'test');
check($pubOk['ok'], 'publicar invitación completa con output aprobado');

$published = cb_load_invitation_by_token_hash(cb_hash_token($invToken));
check($published !== null && (string) $published['status'] === 'published', 'invitación publicada resuelve por token');

$downloadUrl = cb_invitation_download_url($invToken, 'image');
check(strpos($downloadUrl, 'descargar-invitacion.php?t=' . urlencode($invToken)) !== false && strpos($downloadUrl, 'type=image') !== false, 'URL de descarga opaca incluye token y tipo');

// Ownership cruzado (Gate A hallazgo #3): una segunda fiesta nunca debe poder
// operar sobre la invitación/output de la primera, aunque adivine el ID.
$source2 = ['parties' => ['demo2' => [
    'nombre'=>'Demo Dos','tema'=>'carreras','fecha'=>'2026-08-16','activa'=>true,
    'invitados'=>[], 'frameBox'=>['x'=>.2,'y'=>.2,'w'=>.4,'h'=>.4], 'creada'=>'2026-07-13 12:00:00',
]]];
check(cb_save_parties(array_merge(['parties' => array_merge(cb_load_parties()['parties'], $source2['parties'])])), 'guardar segunda fiesta DB');
$demo2PartyId = cb_party_db_id('demo2');
check($demo2PartyId !== null && $demo2PartyId !== $demoPartyId, 'resolver id interno de fiesta demo2 (distinto de demo)');

check(cb_invitation_owned_by_party($invId, $demoPartyId) !== null, 'ownership: la fiesta dueña sí puede operar su invitación');
check(cb_invitation_owned_by_party($invId, $demo2PartyId) === null, 'ownership: otra fiesta NO puede operar una invitación ajena');
check(cb_invitation_owned_by_party(999999, $demoPartyId) === null, 'ownership: invitación inexistente se rechaza igual (no filtra el motivo)');

check(cb_invitation_output_owned_by_party($outputId, $demoPartyId) !== null, 'ownership de output: la fiesta dueña sí puede operar su output');
check(cb_invitation_output_owned_by_party($outputId, $demo2PartyId) === null, 'ownership de output: otra fiesta NO puede operar un output ajeno (traza output->invitation->party)');

// Publicación atómica (Gate A hallazgo #6): cb_update_invitation() nunca debe
// poder escribir status='published' directo, saltándose cb_publish_invitation().
check(cb_update_invitation($invId, ['status' => 'published'], 'test') === false, 'cb_update_invitation() rechaza status=published directo (no hay atajo genérico)');

// Simular exactamente el bug de orden que tenía admin/invitations.php: guardar
// datos incompletos y RECIÉN DESPUÉS intentar publicar debe fallar la publicación
// (valida los datos recién guardados, no datos viejos completos de antes).
$atomicId = (int) cb_create_invitation([
    'party_id' => $demoPartyId, 'theme_slug' => 'carreras', 'birthday_person_name' => 'Completo',
    'event_date' => '2026-09-01', 'event_time' => '12:00', 'address' => 'Dirección completa 123',
    'created_by' => 'test',
])['id'];
$atomicKey = cb_invitation_storage_key('demo', 'personalized-image', 1, 'png');
$atomicPath = cb_invitation_file_path($atomicKey);
if ($atomicPath) { @mkdir(dirname($atomicPath), 0770, true); file_put_contents($atomicPath, str_repeat('y', 50)); }
$atomicSave = cb_save_invitation_output($atomicId, ['output_type' => 'personalized_image', 'asset_key' => 'personalized-image', 'file_storage_key' => $atomicKey, 'status' => 'pending', 'file_mime' => 'image/png', 'file_byte_size' => 50, 'file_sha256' => hash('sha256', str_repeat('y', 50))]);
cb_update_invitation_output_status((int) $atomicSave['id'], 'approved', 'test');
check(cb_publish_invitation($atomicId, 'test')['ok'], 'invitación de prueba queda lista y publicable (datos completos)');

// Ahora, en la MISMA secuencia que usa el admin al editar: guardar primero los
// campos nuevos (con la dirección vacía a propósito) y recién después intentar
// (re)publicar. Debe fallar — valida contra los datos que se acaban de guardar.
$saveIncomplete = cb_update_invitation($atomicId, ['address' => ''], 'test');
check($saveIncomplete, 'guardar edición con dirección vacía (paso 1, todavía no publica nada)');
$pubAfterIncompleteSave = cb_publish_invitation($atomicId, 'test');
check(!$pubAfterIncompleteSave['ok'], 'publicar después de guardar datos incompletos se rechaza (valida datos NUEVOS, no los viejos completos)');
$afterFailedPublish = cb_load_invitation_by_id($atomicId);
check((string) $afterFailedPublish['address'] === '', 'el guardado de campos sí se aplicó aunque la publicación haya fallado (son dos pasos separados a propósito)');

// Cobertura real (Gate A hallazgo #10): expirado, y output de video además del
// de imagen ya cubierto arriba — mismos casos que exige invitacion.php/descargar-invitacion.php.
check(cb_update_invitation($atomicId, ['address' => 'Dirección completa 123'], 'test'), 'restaurar dirección para poder publicar de nuevo');
check(cb_publish_invitation($atomicId, 'test')['ok'], 'publicar de nuevo con datos completos');
$pdoExpire = cb_pdo();
$pdoExpire->prepare('UPDATE cc_invitations SET expires_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 3600), $atomicId]);
$expiredRow = cb_load_invitation_by_id($atomicId);
check(!empty($expiredRow['expires_at']) && strtotime((string) $expiredRow['expires_at']) < time(), 'expires_at en el pasado queda persistido (invitacion.php/descargar-invitacion.php lo rechazan con este mismo chequeo)');

$videoKey = cb_invitation_storage_key('demo', 'personalized-video', 1, 'mp4');
$videoPath = cb_invitation_file_path($videoKey);
if ($videoPath) { @mkdir(dirname($videoPath), 0770, true); file_put_contents($videoPath, str_repeat('z', 60)); }
$videoSave = cb_save_invitation_output($atomicId, ['output_type' => 'personalized_video', 'asset_key' => 'personalized-video', 'file_storage_key' => $videoKey, 'status' => 'pending', 'file_mime' => 'video/mp4', 'file_byte_size' => 60, 'file_sha256' => hash('sha256', str_repeat('z', 60))]);
cb_update_invitation_output_status((int) $videoSave['id'], 'approved', 'test');
$approvedVideoOutputs = cb_invitation_approved_outputs($atomicId, 'personalized_video');
check(count($approvedVideoOutputs) === 1, 'output de video aprobado se resuelve igual que el de imagen');
$videoDownloadUrl = cb_invitation_download_url('token-de-prueba', 'video');
check(strpos($videoDownloadUrl, 'type=video') !== false, 'URL de descarga de tipo video se arma correctamente');

check(cb_revoke_invitation($invId, 'test'), 'revocar invitación');
$revoked = cb_load_invitation_by_token_hash(cb_hash_token($invToken));
check($revoked === null || (string) $revoked['status'] === 'revoked', 'token revocado no resuelve o está revocado');

// Video: sin ffprobe_path configurado (el default de este entorno de test aislado,
// igual que en cualquier host sin el binario), la inspección debe fallar cerrado —
// nunca aceptar un video sin haberlo podido validar de verdad.
check(cb_config('ffprobe_path') === '', 'ffprobe no configurado por defecto en el entorno de test');
check(cb_inspect_video($tmp . '/no-existe.mp4') === null, 'sin ffprobe, inspección de video falla cerrado (no acepta a ciegas)');

// Rate limiting (Gate A hallazgo #4): descargas públicas de invitación y subida
// de outputs en admin, mismos límites que descargar-invitacion.php/admin/invitations.php.
$dlBurst = ['allowed' => true];
for ($i = 0; $i < 60; $i++) { $dlBurst = cb_rate_limit('invitation-download-burst-test', '203.0.113.9', 60, 600, 600); }
check($dlBurst['allowed'], '60 descargas seguidas todavía permitidas (dentro del límite)');
$dlBurst61 = cb_rate_limit('invitation-download-burst-test', '203.0.113.9', 60, 600, 600);
check(!$dlBurst61['allowed'] && $dlBurst61['retry_after'] > 0, 'descarga número 61 bloqueada por rate limit, con retry_after');

$upBurst = ['allowed' => true];
for ($i = 0; $i < 20; $i++) { $upBurst = cb_rate_limit('invitation-upload-burst-test:demo', '203.0.113.9', 20, 600, 600); }
check($upBurst['allowed'], '20 subidas seguidas todavía permitidas (dentro del límite)');
$upBurst21 = cb_rate_limit('invitation-upload-burst-test:demo', '203.0.113.9', 20, 600, 600);
check(!$upBurst21['allowed'], 'subida número 21 bloqueada por rate limit');

// ── Juegos habilitados por fiesta (Luis, 2026-07-27) ───────────────────────
// El admin marca qué juegos van en cada fiesta según el plan contratado
// (Mágico = 1 juego, Premium = 3). El filtro se aplica en el payload, así que
// el frontend no necesita saber nada del plan.
$hieloRaw = $themesData['themes']['hielo'];
$kindsHielo = cb_theme_available_game_kinds($hieloRaw);
check(in_array('armar-muneco', $kindsHielo, true) && in_array('fichas', $kindsHielo, true),
    'los juegos ofrecidos por una temática se detectan desde sus personajes');
check(!in_array('ritmo', $kindsHielo, true),
    'no se ofrecen juegos que la temática no tiene (Reino de Hielo no tiene ritmo)');

$juegosDe = function (array $payload, string $nombre): array {
    foreach ($payload['personajes'] as $p) {
        if ($p['name'] === $nombre) {
            return is_array($p['game']) ? array_column($p['game'], 'kind') : [];
        }
    }
    return [];
};

// Sin elección: nada cambia. Es lo que protege a las fiestas ya creadas.
$sinElegir = cb_build_theme_payload('hielo', $hieloRaw, null);
check($juegosDe($sinElegir, 'Olaf') === ['armar-muneco', 'fichas', 'copos'],
    'una fiesta sin elección juega la cadena completa de la temática');

// Plan Mágico: un solo juego para todos.
$unJuego = cb_build_theme_payload('hielo', $hieloRaw, ['fichas']);
check($juegosDe($unJuego, 'Olaf') === ['fichas'] && $juegosDe($unJuego, 'Elsa') === ['fichas'],
    'con un solo juego habilitado, ningún personaje juega de más');

// El orden lo define la temática, no el orden en que se marcaron las casillas.
$desordenado = cb_build_theme_payload('hielo', $hieloRaw, ['copos', 'armar-muneco', 'fichas']);
check($juegosDe($desordenado, 'Olaf') === ['armar-muneco', 'fichas', 'copos'],
    'el orden de los juegos lo manda la temática, no el orden de las casillas');

// Elegir cero juegos es una decisión válida y distinta de no elegir.
$ninguno = cb_build_theme_payload('hielo', $hieloRaw, []);
check($juegosDe($ninguno, 'Olaf') === [] && $juegosDe($ninguno, 'Elsa') === [],
    'marcar cero juegos deja la fiesta sin juegos (distinto de no elegir)');

// Un juego que la temática no tiene se ignora en vez de romper el payload.
$inexistente = cb_build_theme_payload('hielo', $hieloRaw, ['ritmo']);
check($juegosDe($inexistente, 'Olaf') === [],
    'habilitar un juego que la temática no ofrece no inventa nada');

check(cb_sanitize_party_games(null) === null, 'null se conserva como "no eligió"');
check(cb_sanitize_party_games(['fichas', 'fichas', 'basura']) === ['fichas'],
    'la selección se sanea: sin duplicados ni tipos inventados');

// Mision 3D exclusiva del plan Full (2026-07-29).
$mundo3d = cb_sanitize_theme_game([
    'kind' => 'mundo3d',
    'world' => 'neon-stage',
    'seconds' => 99,
    'targetScore' => 2,
    'collectible' => '⭐',
    'hazard' => '🔊',
]);
check(
    $mundo3d['kind'] === 'mundo3d'
    && $mundo3d['world'] === 'neon-stage'
    && $mundo3d['seconds'] === 30
    && $mundo3d['targetScore'] === 5,
    'backend sanea mundo, tiempo y meta de la mision 3D'
);
$mundo3dInvalido = cb_sanitize_theme_game([
    'kind' => 'mundo3d',
    'world' => '../../otro',
    'targetScore' => 999,
    'collectible' => 'demasiado-largo',
]);
check(
    $mundo3dInvalido['world'] === 'puppy-park'
    && $mundo3dInvalido['targetScore'] === 30
    && $mundo3dInvalido['collectible'] === '⭐',
    'backend rechaza mundo y simbolo 3D fuera de lista blanca'
);

// La mision Full que le toca a cada tematica completa. NO se asume una sola:
// 'concierto3d' (El Show, src/StageConcert3D.jsx) reemplazo al runner de
// carriles 'mundo3d' en las seis, pero el saneador sigue aceptando ambos, asi
// que cada tematica declara aca la que espera. Cada kind trae su propia clave
// de vestuario: 'stage' para El Show, 'world' para el runner.
$misionFullPorTematica = [
    'carreras' => ['kind' => 'concierto3d', 'stage' => 'podium-night'],
    'familia-canina' => ['kind' => 'concierto3d', 'stage' => 'backyard-fiesta'],
    'tropical' => ['kind' => 'concierto3d', 'stage' => 'beach-luau'],
    'hielo' => ['kind' => 'concierto3d', 'stage' => 'ice-gala'],
    // K-Pop no declara 'stage' en themes.json: el saneador lo deja en
    // 'neon-arena', que es justo el vestuario que le corresponde.
    'kpop' => ['kind' => 'concierto3d', 'stage' => 'neon-arena'],
    'heroes' => ['kind' => 'concierto3d', 'stage' => 'rooftop-city'],
];
foreach ($misionFullPorTematica as $slug => $esperado) {
    $rawTheme = $themesData['themes'][$slug];
    $boothPayload = cb_build_theme_payload($slug, $rawTheme, null, 'booth');
    $fullPayload = cb_build_theme_payload($slug, $rawTheme, null, 'full');
    $misionPublicada = (array) ($fullPayload['fullGame'] ?? []);
    $claveVestuario = $esperado['kind'] === 'concierto3d' ? 'stage' : 'world';
    check(
        empty((array) ($boothPayload['fullGame'] ?? [])),
        $slug . ': Booth no recibe configuracion premium'
    );
    check(
        ($misionPublicada['kind'] ?? '') === $esperado['kind']
        && ($misionPublicada[$claveVestuario] ?? '') === $esperado[$claveVestuario],
        $slug . ': Full recibe la mision premium que le corresponde'
    );
    $atlasMatch = count($fullPayload['personajes'] ?? []) === 6;
    foreach (($rawTheme['personajes'] ?? []) as $index => $rawCharacter) {
        $baseName = pathinfo((string) ($rawCharacter['img'] ?? ''), PATHINFO_FILENAME);
        $expected = $baseName !== ''
            && is_file(cb_themes_dir() . '/' . $slug . '/game3d/' . $baseName . '-run-atlas.png');
        $published = $fullPayload['personajes'][$index] ?? [];
        $atlasMatch = $atlasMatch
            && ($published['runnerAtlasExists'] ?? false) === $expected
            && (
                $expected
                    ? ($published['runnerAtlas'] ?? '') ===
                        'themes/' . $slug . '/game3d/' . $baseName . '-run-atlas.png'
                    : ($published['runnerAtlas'] ?? '') === ''
            );
    }
    check(
        $atlasMatch,
        $slug . ': Full publica solo atlas multivista existentes'
    );
    check(
        array_reduce(
            $boothPayload['personajes'] ?? [],
            static fn (bool $ok, array $p): bool =>
                $ok
                && ($p['runnerAtlasExists'] ?? false) === false
                && ($p['runnerAtlas'] ?? '') === '',
            true
        ),
        $slug . ': Booth no recibe rutas de atlas premium'
    );
}
check(
    !in_array('mundo3d', cb_theme_available_game_kinds($themesData['themes']['hielo']), true),
    'la mision Full no aparece como casilla manual entre los juegos normales'
);

// Formulario comercial público (landing).
$leadInput = [
    'nombre' => 'María González',
    'organizacion' => 'Colegio Demo',
    'email' => 'maria@example.test',
    'telefono' => '+56 9 1234 5678',
    'tipo' => 'Día del Niño',
    'fecha' => '2026-10-15',
    'comuna' => 'Santiago Centro',
    'mensaje' => 'Actividad para aproximadamente 80 asistentes.',
    'consentimiento' => true,
    'website' => '',
];
$leadValidation = cb_validate_lead_input($leadInput);
check($leadValidation['ok'] && $leadValidation['data']['email'] === 'maria@example.test', 'lead válido se normaliza');
$invalidLead = cb_validate_lead_input(array_merge($leadInput, ['email' => 'malo', 'consentimiento' => false]));
check(!$invalidLead['ok'] && isset($invalidLead['errors']['email'], $invalidLead['errors']['consentimiento']), 'lead exige correo y consentimiento válidos');
$botLead = cb_validate_lead_input(array_merge($leadInput, ['website' => 'https://spam.test']));
check(!$botLead['ok'] && isset($botLead['errors']['website']), 'honeypot rechaza bots');
$createdLead = cb_create_lead($leadInput, '203.0.113.8', 'CumpleClickTest/1.0');
check($createdLead['ok'] && preg_match('/^CC-[A-F0-9]{12}$/', $createdLead['reference']) === 1, 'lead crea referencia opaca');
$storedLeadStmt = cb_pdo()->prepare('SELECT * FROM cc_leads WHERE public_ref=?');
$storedLeadStmt->execute([$createdLead['reference']]);
$storedLead = $storedLeadStmt->fetch();
check(
    $storedLead
    && $storedLead['status'] === 'new'
    && $storedLead['ip_hmac'] === cb_hmac('203.0.113.8', 'lead-ip')
    && strpos(json_encode($storedLead), '203.0.113.8') === false,
    'lead persiste sin guardar IP en claro'
);

// -- Color de la barra del navegador en las paginas PHP ---------------------
// El kiosco y el album lo resuelven en JS; invitacion, regalos, predicciones y
// galeria son PHP puro y se quedaban con la barra gris del navegador. Ahora
// sale de la tematica, igual que en el front y con el mismo token (accent).
check(cb_theme_meta_color('hielo') === '#29b6f6', 'la barra toma el accent de Reino de Hielo');
check(cb_theme_meta_color('carreras') === '#e8000d', 'la barra toma el accent de Carreras');
check(cb_theme_meta_color('baby-safari') === '#7A9455', 'la barra toma el accent de Bebe Safari');
check(cb_theme_meta_color('princesas') === '#ab47bc', 'la barra toma el accent de Princesas');
// Sin tematica, o con una inventada, no se inventa un color: mejor la barra por
// defecto del navegador que una que no corresponde a nada.
check(cb_theme_meta_color('') === '', 'sin tematica no se imprime color de barra');
check(cb_theme_meta_color('no-existe') === '', 'una tematica inexistente no inventa color');

// TODAS las tematicas publicadas tienen que poder pintar la barra. Si manana
// alguien agrega una sin accent, el navegador le pondria gris y nadie se
// enteraria hasta abrirla en un celular.
$temasSinBarra = [];
foreach (array_keys(cb_load_themes()['themes'] ?? []) as $slugTema) {
    if (cb_theme_meta_color((string) $slugTema) === '') { $temasSinBarra[] = $slugTema; }
}
check($temasSinBarra === [], 'todas las tematicas definen color de barra; sin el: ' . implode(', ', $temasSinBarra));

// Y las cuatro paginas PHP tienen que imprimirlo. Es un chequeo sobre el
// archivo porque montar cada pagina con su token cuesta mas de lo que protege;
// lo que se fija es que nadie borre el meta sin darse cuenta.
foreach (['invitacion.php', 'predicciones.php', 'regalos-papas.php', 'galeria.php'] as $paginaConBarra) {
    $fuente = (string) file_get_contents(dirname(__DIR__, 2) . '/public/' . $paginaConBarra);
    check(strpos($fuente, 'name="theme-color"') !== false, "$paginaConBarra imprime el color de la barra");
}

// -- Miniaturas de fotos de cabina en el Album Recuerdo ---------------------
// El album servia las fotos de cabina en tamano completo (2-3MB cada una: un
// album de 9 cargaba mas de 20MB en el celular). cb_photo_thumbnail_path()
// genera una miniatura JPEG de 640px cacheada junto al original.
$fotoGrande = $tmp . '/foto-cabina-test.png';
$imgGrande = imagecreatetruecolor(1080, 1920);
imagefilledrectangle($imgGrande, 0, 0, 1079, 1919, imagecolorallocate($imgGrande, 40, 90, 160));
imagepng($imgGrande, $fotoGrande);
imagedestroy($imgGrande);

$rutaThumb = cb_photo_thumbnail_path($fotoGrande);
check($rutaThumb !== null && is_file($rutaThumb), 'la miniatura de cabina se genera');
$infoThumb = getimagesize((string) $rutaThumb);
check(($infoThumb['mime'] ?? '') === 'image/jpeg', 'la miniatura es JPEG');
check(max((int) $infoThumb[0], (int) $infoThumb[1]) <= 640, 'la miniatura no pasa de 640px de lado');
check(filesize((string) $rutaThumb) < filesize($fotoGrande), 'la miniatura pesa menos que el original');

// El cache: la segunda llamada devuelve el MISMO archivo sin regenerarlo.
$mtimeAntes = filemtime((string) $rutaThumb);
clearstatcache();
check(cb_photo_thumbnail_path($fotoGrande) === $rutaThumb, 'la segunda llamada reusa el cache');
check(filemtime((string) $rutaThumb) === $mtimeAntes, 'el cache no se regenera si el original no cambio');

// Contenido JPEG bajo clave .png: el formato se detecta por contenido, no por
// extension (imagecreatefrompng sobre un JPEG devuelve false sin avisar).
$jpegDisfrazado = $tmp . '/foto-jpeg-disfrazada.png';
$imgJ = imagecreatetruecolor(800, 1200);
imagefilledrectangle($imgJ, 0, 0, 799, 1199, imagecolorallocate($imgJ, 120, 60, 30));
imagejpeg($imgJ, $jpegDisfrazado, 90);
imagedestroy($imgJ);
check(cb_photo_thumbnail_path($jpegDisfrazado) !== null, 'un JPEG guardado bajo clave .png tambien genera miniatura');

// Un archivo que no es imagen no revienta nada: devuelve null y se sirve el
// original.
$noImagen = $tmp . '/no-imagen.png';
file_put_contents($noImagen, 'esto no es una imagen');
check(cb_photo_thumbnail_path($noImagen) === null, 'un archivo corrupto devuelve null, no un fatal');

// Y el API del album tiene que PEDIR la miniatura para las fotos de cabina:
// sin el `v=thumb` en la URL, todo lo de arriba no sirve de nada.
$fuenteAlbumApi = (string) file_get_contents(dirname(__DIR__, 2) . '/public/album-api.php');
// No basta buscar `v=thumb` (la rama de invitados tambien lo tiene): lo que
// delata la regresion es que reaparezca la asignacion vieja sin miniatura.
check(strpos($fuenteAlbumApi, '$thumb = $url;') === false, 'la rama de cabina de album-api no volvio a servir el original como miniatura');

// -- Version de assets del tema (rompe-cache) -------------------------------
// El payload publica assetsVersion = mtime mas nuevo de la carpeta del tema.
// El front lo agrega como ?v= a cada URL: reemplazar un archivo con el mismo
// nombre cambia la URL y el cache (navegador/CDN) queda fuera de la jugada.
$dirTemaPrueba = $tmp . '/tema-version';
mkdir($dirTemaPrueba, 0770, true);
file_put_contents($dirTemaPrueba . '/a.jpg', 'x');
touch($dirTemaPrueba . '/a.jpg', 1700000100);
mkdir($dirTemaPrueba . '/sub', 0770, true);
file_put_contents($dirTemaPrueba . '/sub/b.mp4', 'y');
touch($dirTemaPrueba . '/sub/b.mp4', 1700000900);
check(cb_theme_assets_version($dirTemaPrueba . '/') === 1700000900, 'assetsVersion toma el mtime mas nuevo, tambien de subcarpetas');
check(cb_theme_assets_version($tmp . '/no-existe/') === 0, 'carpeta inexistente da 0 y el front no agrega ?v=');
$payloadVersion = cb_build_theme_payload('hielo', cb_load_themes()['themes']['hielo']);
check(($payloadVersion['assetsVersion'] ?? 0) > 0, 'el payload del tema publica assetsVersion');

// La clave de almacenamiento acepta mp3: la validacion de subida ya lo
// aceptaba (narracion de inicio) pero la lista del constructor quedo atras y
// ni el admin podia guardar una narracion. Se piso con la primera real.
check(is_string(cb_invitation_storage_key('fiesta-x', 'narracion-intro', 1, 'mp3')), 'storage key acepta mp3 para la narracion');

fwrite(STDOUT, "OK $tests checks backend\n");
