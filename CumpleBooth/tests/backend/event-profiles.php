<?php
/** Pruebas aisladas del Perfil del protagonista sobre SQLite temporal. */
if (PHP_SAPI !== 'cli') { exit(2); }

$tmp = sys_get_temp_dir() . '/cumpleclick-event-profile-' . bin2hex(random_bytes(4));
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
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/event-profile.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('e', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_INVITATION_DIR=' . $tmp . '/invitations');
putenv('CC_EVENT_PROFILE_DIR=' . $tmp . '/event-profiles');
putenv('CC_EVENT_PROFILE_ENABLED=true');

$root = dirname(__DIR__, 2);
require $root . '/public/lib.php';
foreach ([
    '001_initial', '002_theme_prompts', '003_invitations_and_plan',
    '004_gate_a_corrections', '005_theme_prompt_history', '006_public_leads',
    '007_event_album', '008_event_profiles',
] as $version) {
    $migration = require $root . '/database/migrations/' . $version . '.php';
    $migration(cb_pdo());
}

$tests = 0;
function profile_check(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
function profile_throws(callable $callback, string $message): void
{
    $thrown = false;
    try { $callback(); } catch (Throwable $e) { $thrown = true; }
    profile_check($thrown, $message);
}
$down = require $root . '/database/migrations/008_event_profiles.down.php';
$down(cb_pdo());
$tables = cb_pdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
profile_check(!in_array('cc_event_profiles', $tables, true), 'la reversa elimina solo las tablas del perfil');
profile_check(in_array('cc_parties', $tables, true) && in_array('cc_invitations', $tables, true), 'la reversa conserva las tablas anteriores');
$up = require $root . '/database/migrations/008_event_profiles.php';
$up(cb_pdo());
profile_check(in_array('cc_event_profiles', cb_pdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN), true), 'la migración se puede reaplicar');


profile_check(cb_save_parties(['parties' => [
    'demo' => [
        'nombre' => 'Fiesta Demo', 'tema' => 'carreras', 'fecha' => '2026-09-12',
        'activa' => true, 'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'),
    ],
    'otra' => [
        'nombre' => 'Otra Fiesta', 'tema' => 'tropical', 'fecha' => '2026-09-13',
        'activa' => true, 'invitados' => [], 'creada' => gmdate('Y-m-d H:i:s'),
    ],
]]), 'crea fiestas de prueba');
$partyId = cb_party_db_id('demo');
$otherPartyId = cb_party_db_id('otra');
profile_check(is_int($partyId) && is_int($otherPartyId), 'las fiestas tienen id');
profile_check(cb_event_profile_get($partyId, true) === null, 'un evento existente no cambia sin perfil');

$profile = cb_event_profile_ensure($partyId);
profile_check(empty($profile['is_enabled']), 'el perfil nace desactivado');
profile_throws(static function () use ($partyId): void {
    cb_event_profile_save($partyId, ['enabled' => true, 'public_title' => 'Conoce a Emilia']);
}, 'activar exige confirmar privacidad');

$profile = cb_event_profile_save($partyId, [
    'enabled' => true,
    'privacy_ack' => true,
    'event_type' => 'child_birthday',
    'public_title' => 'Conoce a Emilia y Nico',
    'cta_label' => 'Conoce a los protagonistas',
    'intro_style' => 'epico',
    'intro_phrase' => 'Dos pequeñas estrellas celebran juntas',
    'section_order' => ['gifts', 'favorites', 'sizes', 'custom'],
], 'qa@example.test');
profile_check(!empty($profile['is_enabled']), 'guarda y activa el perfil');
$sectionLabels = array_column($profile['sections'], 'public_label', 'section_key');
profile_check(($sectionLabels['gifts'] ?? '') === 'Ideas para regalar', 'reordenar conserva etiqueta española del preset');

$profile = cb_event_profile_replace_people($partyId, [
    [
        'display_name' => 'Emilia', 'nickname' => 'Emi', 'intro_text' => 'Le encanta crear historias.',
        'is_public' => true, 'photo_public_consent' => true, 'photo_ai_consent' => true,
        'fields' => [
            ['section_key' => 'favorites', 'field_key' => 'color', 'label' => 'Color favorito', 'value' => 'Morado', 'is_public' => true],
            ['section_key' => 'sizes', 'field_key' => 'shoe', 'label' => 'Calzado', 'value' => '31', 'value_type' => 'size', 'is_public' => false],
        ],
    ],
    [
        'display_name' => 'Nicolás', 'nickname' => 'Nico', 'intro_text' => 'Disfruta los juegos de construcción.',
        'is_public' => true, 'fields' => [
            ['section_key' => 'gifts', 'field_key' => 'idea', 'label' => 'Idea de regalo', 'value' => 'Bloques creativos', 'is_public' => true],
        ],
    ],
], 'qa@example.test');
profile_check(count($profile['featured_people']) === 2, 'admite múltiples protagonistas');
$emiliaId = (int) $profile['featured_people'][0]['id'];

profile_throws(static function () use ($partyId): void {
    cb_event_profile_replace_people($partyId, [[
        'display_name' => 'Dato privado', 'is_public' => true,
        'fields' => [['label' => 'Teléfono', 'value' => '+56 9 1234 5678', 'is_public' => false]],
    ]]);
}, 'rechaza datos sensibles incluso si el campo se marca privado');

$photoKey = cb_event_profile_storage_key('demo', 'photo', 'jpg');
$photoPath = cb_event_profile_media_path($photoKey);
mkdir(dirname($photoPath), 0770, true);
file_put_contents($photoPath, 'imagen-autorizada-de-prueba');
$photo = cb_event_profile_register_media($partyId, $emiliaId, 'photo', $photoKey, [
    'mime' => 'image/jpeg', 'byte_size' => filesize($photoPath), 'width' => 1080, 'height' => 1920,
    'status' => 'ready', 'is_public' => true, 'authorized_for_ai' => true, 'alt_text' => 'Retrato de Emilia',
], 'qa@example.test');
profile_check(preg_match('/^[a-f0-9]{32}$/', (string) $photo['access_token']) === 1, 'media usa token opaco');

$invitation = cb_create_invitation([
    'party_id' => $partyId, 'theme_slug' => 'carreras', 'admin_label' => 'Demo',
    'birthday_person_name' => 'Emilia', 'event_date' => '2026-09-12', 'event_time' => '16:00',
    'address' => 'Lugar informado en la invitación principal', 'created_by' => 'qa',
]);
$otherInvitation = cb_create_invitation([
    'party_id' => $otherPartyId, 'theme_slug' => 'tropical', 'admin_label' => 'Otra',
    'birthday_person_name' => 'Otra', 'event_date' => '2026-09-13', 'event_time' => '17:00',
    'address' => 'Lugar informado en la invitación principal', 'created_by' => 'qa',
]);
profile_check(!empty($invitation['ok']) && !empty($otherInvitation['ok']), 'crea invitaciones de prueba');
cb_pdo()->prepare("UPDATE cc_invitations SET status='published' WHERE id IN (?,?)")
    ->execute([(int) $invitation['id'], (int) $otherInvitation['id']]);
$published = cb_load_invitation_by_id((int) $invitation['id']);
$shareToken = cb_invitation_share_token((int) $invitation['id']);
profile_check(preg_match('/^[a-f0-9]{48}$/', $shareToken) === 1, 'admin reconstruye un alias público firmado sin token plano');
profile_check((int) (cb_load_invitation_by_public_token($shareToken)['id'] ?? 0) === (int) $invitation['id'], 'alias firmado resuelve la invitación publicada');
$tamperedShareToken = substr($shareToken, 0, -1) . ($shareToken[-1] === '0' ? '1' : '0');
profile_check(cb_load_invitation_by_public_token($tamperedShareToken) === null, 'alias público alterado falla cerrado');
profile_check(strpos(cb_invitation_public_url($shareToken), $shareToken) !== false, 'admin puede construir el enlace de invitación pública');
profile_check(cb_event_profile_find_public_media_for_invitation($shareToken, (string) $photo['access_token']) !== null, 'alias firmado autoriza media pública del mismo evento');
$public = cb_event_profile_public_for_invitation($published, (string) $invitation['token']);
profile_check(is_array($public) && count($public['featured_people']) === 2, 'invitación publicada obtiene el perfil público');
profile_check(count($public['featured_people'][0]['fields']) === 1, 'filtra campos con visibilidad privada');
$publicJson = json_encode($public, JSON_UNESCAPED_UNICODE);
profile_check(strpos($publicJson, 'photo_ai_consent') === false && strpos($publicJson, 'storage_key') === false && strpos($publicJson, 'generations') === false, 'agregado público no filtra consentimientos, rutas ni cotizaciones');
profile_check(strpos((string) $public['featured_people'][0]['photo']['url'], '?t=') !== false && strpos((string) $public['featured_people'][0]['photo']['url'], '&mt=') !== false, 'URL pública combina token de invitación y token de media');
profile_check(cb_event_profile_find_public_media_for_invitation((string) $invitation['token'], (string) $photo['access_token']) !== null, 'media abre para invitación publicada del mismo evento');
profile_check(cb_event_profile_find_public_media_for_invitation((string) $otherInvitation['token'], (string) $photo['access_token']) === null, 'media no cruza entre eventos');

$basePrompt = 'Vertical cinematic celebration with layered lights, original decorations, playful depth and a premium camera move; no visible text.';
$draft = cb_event_profile_prepare_generation($partyId, [
    'prompt' => $basePrompt, 'duration_seconds' => 5, 'aspect_ratio' => '9:16',
    'reference_person_ids' => [$emiliaId],
], 'qa@example.test');
profile_check($draft['status'] === 'draft', 'sin modelo y costo solo prepara borrador');
profile_throws(static function () use ($draft): void {
    cb_event_profile_approve_generation((int) $draft['id'], 'qa@example.test');
}, 'un borrador nunca puede aprobarse');

$quoted = cb_event_profile_prepare_generation($partyId, [
    'prompt' => $basePrompt . ' Quoted version.', 'duration_seconds' => 5, 'aspect_ratio' => '9:16',
    'provider' => 'higgsfield', 'model_key' => 'model-to-confirm', 'quote_amount' => 1.25,
    'quote_currency' => 'USD', 'quote_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    'reference_person_ids' => [$emiliaId],
], 'qa@example.test');
profile_check($quoted['status'] === 'quoted', 'modelo y costo crean cotización sin generar');
profile_check(cb_event_profile_approve_generation((int) $quoted['id'], 'qa@example.test')['status'] === 'approved', 'admin puede aprobar una cotización vigente');
$private = cb_event_profile_get($partyId, true);
profile_check(count($private['generations'] ?? []) === 2, 'admin recupera historial privado de cotizaciones');

cb_pdo()->prepare('UPDATE cc_featured_people SET photo_ai_consent=0 WHERE id=?')->execute([$emiliaId]);
profile_throws(static function () use ($partyId, $basePrompt, $emiliaId): void {
    cb_event_profile_prepare_generation($partyId, [
        'prompt' => $basePrompt . ' Another version.', 'duration_seconds' => 5, 'aspect_ratio' => '9:16',
        'reference_person_ids' => [$emiliaId],
    ]);
}, 'referencia sin consentimiento IA falla cerrada');

cb_event_profile_save($partyId, ['enabled' => false]);
profile_check(cb_event_profile_public_for_invitation($published, (string) $invitation['token']) === null, 'desactivar restaura el comportamiento sin perfil');


echo "OK: {$tests} pruebas de perfiles de evento.\n";
