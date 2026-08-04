<?php
/** Retención: 30 días desde fecha de fiesta o, si falta, desde su creación. */
require __DIR__ . '/_cli.php';

$apply = cc_cli_require_apply();
$days = max(1, (int) cb_config('retention_days'));
$cutoffDate = gmdate('Y-m-d', time() - $days * 86400);
$now = gmdate('Y-m-d H:i:s');

if (cb_storage_mode() === 'db') {
    $pdo = cb_pdo();
    // substr es portable entre SQLite y MySQL para los valores ISO almacenados.
    $eligibleSql = "anonymized_at IS NULL AND substr(COALESCE(NULLIF(event_date,''),created_at),1,10) <= ?";
    $parties = $pdo->prepare("SELECT id,slug FROM cc_parties WHERE $eligibleSql");
    $parties->execute([$cutoffDate]);
    $partyRows = $parties->fetchAll();
    $partyIds = array_map('intval', array_column($partyRows, 'id'));
    $photoRows = [];
    if ($partyIds) {
        $marks = implode(',', array_fill(0, count($partyIds), '?'));
        // Incluye filas ya marcadas para reintentar un unlink fallido anterior.
        $photos = $pdo->prepare("SELECT id,storage_key,deleted_at FROM cc_photos WHERE party_id IN ($marks)");
        $photos->execute($partyIds);
        $photoRows = $photos->fetchAll();
    }
    fwrite(STDOUT, 'Fiestas vencidas: ' . count($partyRows) . '; fotos privadas: ' . count($photoRows) . "\n");
    if (!$apply) { exit(0); }

    $pdo->beginTransaction();
    try {
        $markPhoto = $pdo->prepare('UPDATE cc_photos SET deleted_at=COALESCE(deleted_at,?) WHERE id=?');
        foreach ($photoRows as $photo) { $markPhoto->execute([$now, (int) $photo['id']]); }
        $dropGuests = $pdo->prepare('DELETE FROM cc_guests WHERE party_id=?');
        $anon = $pdo->prepare("UPDATE cc_parties SET name='Fiesta archivada',active=0,gallery_pin_hash=NULL,gallery_pin_hmac=NULL,anonymized_at=?,updated_at=? WHERE id=?");
        foreach ($partyRows as $party) {
            $dropGuests->execute([(int) $party['id']]);
            $anon->execute([$now, $now, (int) $party['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    $unlinkFailures = 0;
    foreach ($photoRows as $photo) {
        $path = cb_photo_absolute_path((string) $photo['storage_key']);
        if ($path && is_file($path) && !@unlink($path)) { $unlinkFailures++; }
    }
    if ($unlinkFailures > 0) {
        fwrite(STDERR, "No se pudieron eliminar $unlinkFailures archivos; la próxima ejecución los reintentará.\n");
        exit(1);
    }
} else {
    $parties = cb_load_parties();
    $eligible = [];
    foreach (($parties['parties'] ?? []) as $slug => $party) {
        $anchor = (string) (($party['fecha'] ?? '') ?: ($party['creada'] ?? ''));
        if ($anchor !== '' && substr($anchor, 0, 10) <= $cutoffDate && empty($party['anonymizedAt'])) {
            $eligible[$slug] = true;
        }
    }
    $photos = cb_load_json_file(cb_state_path('photos.json'));
    $photoRows = [];
    foreach (($photos['photos'] ?? []) as $token => $photo) {
        if (isset($eligible[(string) ($photo['party'] ?? '')])) { $photoRows[$token] = $photo; }
    }
    fwrite(STDOUT, 'Fiestas vencidas: ' . count($eligible) . '; fotos privadas: ' . count($photoRows) . "\n");
    if (!$apply) { exit(0); }

    cb_mutate_json_state(cb_state_path('photos.json'), static function (&$data) use ($photoRows, $now): bool {
        foreach (array_keys($photoRows) as $token) {
            if (isset($data['photos'][$token])) { $data['photos'][$token]['deleted_at'] = $data['photos'][$token]['deleted_at'] ?? $now; }
        }
        return true;
    });
    foreach (array_keys($eligible) as $slug) {
        $parties['parties'][$slug]['nombre'] = 'Fiesta archivada';
        $parties['parties'][$slug]['activa'] = false;
        $parties['parties'][$slug]['invitados'] = [];
        $parties['parties'][$slug]['anonymizedAt'] = $now;
        unset($parties['parties'][$slug]['galeriaPin'], $parties['parties'][$slug]['galeriaPinHash'], $parties['parties'][$slug]['galeriaPinHmac']);
    }
    if (!cb_save_parties($parties)) { throw new RuntimeException('No se pudo guardar la anonimización JSON.'); }
    $unlinkFailures = 0;
    foreach ($photoRows as $photo) {
        $path = cb_photo_absolute_path((string) ($photo['storage_key'] ?? ''));
        if ($path && is_file($path) && !@unlink($path)) { $unlinkFailures++; }
    }
    if ($unlinkFailures > 0) {
        fwrite(STDERR, "No se pudieron eliminar $unlinkFailures archivos; la próxima ejecución los reintentará.\n");
        exit(1);
    }
}
fwrite(STDOUT, "Retención aplicada.\n");
