<?php
/** Limpia exclusivamente fotos creadas por http-smoke.mjs en la BD local. */
if (PHP_SAPI !== 'cli') { exit(2); }
require dirname(__DIR__, 2) . '/public/lib.php';
if (cb_storage_mode() !== 'db') { fwrite(STDERR, "Solo modo DB.\n"); exit(2); }
$pdo = cb_pdo();
$rows = $pdo->query("SELECT id,storage_key FROM cc_photos WHERE original_name='smoke-test.png'")->fetchAll();
$delete = $pdo->prepare('DELETE FROM cc_photos WHERE id=?');
foreach ($rows as $row) {
    $path = cb_photo_absolute_path((string) $row['storage_key']);
    if ($path && is_file($path)) { @unlink($path); }
    $delete->execute([(int) $row['id']]);
}
fwrite(STDOUT, 'OK limpieza smoke: ' . count($rows) . " fotos.\n");

// Invitaciones sembradas por seed-invitation-smoke.php (http-smoke.mjs), marcadas
// admin_label='SMOKE-TEST'. Los outputs se borran en cascada (FK ON DELETE CASCADE).
if (cb_storage_mode() === 'db') {
    $invRows = $pdo->query("SELECT id FROM cc_invitations WHERE admin_label='SMOKE-TEST'")->fetchAll();
    $deleteInv = $pdo->prepare('DELETE FROM cc_invitations WHERE id=?');
    foreach ($invRows as $inv) {
        foreach (cb_load_invitation_outputs((int) $inv['id']) as $output) {
            $path = cb_invitation_file_path((string) $output['file_storage_key']);
            if ($path && is_file($path)) { @unlink($path); }
        }
        $deleteInv->execute([(int) $inv['id']]);
    }
    fwrite(STDOUT, 'OK limpieza smoke: ' . count($invRows) . " invitaciones.\n");
}
