<?php
/**
 * Siembra invitaciones de prueba (admin_label=SMOKE-TEST) en la BD real
 * configurada por config.php — la misma que sirve http-smoke.mjs:
 *  - "published": publicada, con imagen Y video aprobados;
 *  - "draft": creada pero nunca publicada;
 *  - "revoked": publicada y luego revocada;
 *  - "expired": publicada con expires_at en el pasado.
 * Imprime un JSON de una sola línea con los 4 tokens en stdout.
 * cleanup-http-smoke.php borra todas por su admin_label después.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
require dirname(__DIR__, 2) . '/public/lib.php';
if (cb_storage_mode() !== 'db') { fwrite(STDERR, "Solo modo DB.\n"); exit(2); }

$demoPartyId = cb_party_db_id('demo');

function seed_fail(string $stage, string $error): void
{
    fwrite(STDERR, "seed {$stage} failed: {$error}\n");
    exit(1);
}

/** Adjunta y aprueba una imagen y un video dummy a una invitación. */
function seed_attach_outputs(int $invId, string $slugPrefix): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    $imgKey = cb_invitation_storage_key($slugPrefix, 'personalized-image', 1, 'png');
    $imgPath = cb_invitation_file_path($imgKey);
    @mkdir(dirname($imgPath), 0770, true);
    file_put_contents($imgPath, $png);
    $imgSave = cb_save_invitation_output($invId, [
        'output_type' => 'personalized_image', 'asset_key' => 'personalized-image',
        'file_storage_key' => $imgKey, 'status' => 'pending', 'file_mime' => 'image/png',
        'file_byte_size' => strlen($png), 'file_sha256' => hash('sha256', $png),
    ]);
    if (empty($imgSave['ok'])) { seed_fail('image output', $imgSave['error'] ?? '?'); }
    cb_update_invitation_output_status((int) $imgSave['id'], 'approved', 'http-smoke');

    // Video: contenido dummy — no se genera multimedia real, solo bytes de
    // prueba para ejercitar la ruta de descarga (descargar-invitacion.php
    // sirve el archivo tal cual, no re-valida metadata al descargar).
    $mp4 = 'CC-SMOKE-TEST-VIDEO-FIXTURE';
    $vidKey = cb_invitation_storage_key($slugPrefix, 'personalized-video', 1, 'mp4');
    $vidPath = cb_invitation_file_path($vidKey);
    @mkdir(dirname($vidPath), 0770, true);
    file_put_contents($vidPath, $mp4);
    $vidSave = cb_save_invitation_output($invId, [
        'output_type' => 'personalized_video', 'asset_key' => 'personalized-video',
        'file_storage_key' => $vidKey, 'status' => 'pending', 'file_mime' => 'video/mp4',
        'file_byte_size' => strlen($mp4), 'file_sha256' => hash('sha256', $mp4),
    ]);
    if (empty($vidSave['ok'])) { seed_fail('video output', $vidSave['error'] ?? '?'); }
    cb_update_invitation_output_status((int) $vidSave['id'], 'approved', 'http-smoke');
}

function seed_base_data(): array
{
    return [
        'event_date' => '2030-01-01',
        'event_time' => '12:00',
        'address' => 'Dirección de prueba 123',
        'admin_label' => 'SMOKE-TEST',
        'created_by' => 'http-smoke',
    ];
}

// --- published: imagen + video aprobados ---
$created = cb_create_invitation(array_merge(seed_base_data(), [
    'party_id' => $demoPartyId,
    'theme_slug' => 'carreras',
    'birthday_person_name' => 'Smoke Test',
]));
if (empty($created['ok'])) { seed_fail('create published', $created['error'] ?? '?'); }
$publishedId = (int) $created['id'];
seed_attach_outputs($publishedId, 'demo-smoke-published');
$pub = cb_publish_invitation($publishedId, 'http-smoke');
if (empty($pub['ok'])) { seed_fail('publish published', $pub['error'] ?? '?'); }
$publishedToken = cb_regenerate_invitation_token($publishedId);
if ($publishedToken === null) { seed_fail('token published', 'null token'); }

// --- draft: nunca publicada ---
$draft = cb_create_invitation(array_merge(seed_base_data(), [
    'theme_slug' => 'carreras',
    'birthday_person_name' => 'Smoke Draft',
]));
if (empty($draft['ok'])) { seed_fail('create draft', $draft['error'] ?? '?'); }
$draftToken = (string) $draft['token'];

// --- revoked: publicada y luego revocada ---
$rev = cb_create_invitation(array_merge(seed_base_data(), [
    'theme_slug' => 'carreras',
    'birthday_person_name' => 'Smoke Revoked',
]));
if (empty($rev['ok'])) { seed_fail('create revoked', $rev['error'] ?? '?'); }
$revId = (int) $rev['id'];
seed_attach_outputs($revId, 'demo-smoke-revoked');
$pubRev = cb_publish_invitation($revId, 'http-smoke');
if (empty($pubRev['ok'])) { seed_fail('publish revoked', $pubRev['error'] ?? '?'); }
if (!cb_revoke_invitation($revId, 'http-smoke')) { seed_fail('revoke', 'cb_revoke_invitation returned false'); }
$revokedToken = cb_regenerate_invitation_token($revId);
if ($revokedToken === null) { seed_fail('token revoked', 'null token'); }

// --- expired: publicada con expires_at en el pasado ---
$exp = cb_create_invitation(array_merge(seed_base_data(), [
    'theme_slug' => 'carreras',
    'birthday_person_name' => 'Smoke Expired',
]));
if (empty($exp['ok'])) { seed_fail('create expired', $exp['error'] ?? '?'); }
$expId = (int) $exp['id'];
seed_attach_outputs($expId, 'demo-smoke-expired');
$pubExp = cb_publish_invitation($expId, 'http-smoke');
if (empty($pubExp['ok'])) { seed_fail('publish expired', $pubExp['error'] ?? '?'); }
if (!cb_update_invitation($expId, ['expires_at' => '2020-01-01'], 'http-smoke')) {
    seed_fail('set expires_at', 'cb_update_invitation returned false');
}
$expiredToken = cb_regenerate_invitation_token($expId);
if ($expiredToken === null) { seed_fail('token expired', 'null token'); }

echo json_encode([
    'published' => $publishedToken,
    'draft' => $draftToken,
    'revoked' => $revokedToken,
    'expired' => $expiredToken,
]) . "\n";
