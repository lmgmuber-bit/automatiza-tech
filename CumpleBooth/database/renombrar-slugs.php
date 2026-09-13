<?php
// Renombra los slugs de dos fiestas reales (Luis, 2026-09-06: "renómbralos, no he compartido ningún enlace").
// Toca exactamente lo que encontró slug-radio.php: cc_parties.public_slug, cc_photos.storage_key
// (<slug>/...), cc_event_media.storage_key/thumb/poster (album/<slug>/...), cc_invitation_outputs
// .file_storage_key (<slug>/...) y las carpetas fotos/<slug>, fotos/album/<slug>, invitaciones/<slug>.
// Orden: respaldo JSON -> carpetas (reversible) -> BD en transacción; si la BD falla, carpetas de vuelta.
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$mapa = ['demo-frozen-vip' => 'isidora-reino-de-hielo', 'qa-spidey' => 'luciano-spidey'];
$fotos = rtrim((string) cb_config('photo_dir'), '/');
$inv = rtrim((string) cb_config('invitation_dir'), '/');

// 0. Validaciones previas
foreach ($mapa as $viejo => $nuevo) {
    if (!cb_valid_public_slug($nuevo)) { exit("slug invalido: $nuevo\n"); }
    if (cb_load_party_raw($nuevo) !== null) { exit("slug ocupado: $nuevo\n"); }
    if (cb_load_party_raw($viejo) === null) { exit("no existe: $viejo\n"); }
    foreach (["$fotos/$viejo", "$fotos/album/$viejo", "$inv/$viejo"] as $d) {
        if (is_dir($d) && is_dir(str_replace($viejo, $nuevo, $d))) { exit("destino ya existe: $d\n"); }
    }
}

// 1. Respaldo de las filas afectadas
$resp = [];
foreach ($mapa as $viejo => $nuevo) {
    $pid = (int) cb_party_db_id($viejo);
    $resp[$viejo] = [
        'party' => $pdo->query("SELECT * FROM cc_parties WHERE id = $pid")->fetch(PDO::FETCH_ASSOC),
        'photos' => $pdo->query("SELECT id, storage_key FROM cc_photos WHERE party_id = $pid")->fetchAll(PDO::FETCH_ASSOC),
        'event_media' => $pdo->query("SELECT id, storage_key, thumb_storage_key, poster_storage_key FROM cc_event_media WHERE party_id = $pid")->fetchAll(PDO::FETCH_ASSOC),
        'invitation_outputs' => $pdo->query("SELECT o.id, o.file_storage_key FROM cc_invitation_outputs o JOIN cc_invitations i ON i.id = o.invitation_id WHERE i.party_id = $pid")->fetchAll(PDO::FETCH_ASSOC),
    ];
}
$archivo = __DIR__ . '/respaldo-slugs-' . gmdate('Ymd-His') . '.json';
file_put_contents($archivo, json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
chmod($archivo, 0600);
echo "respaldo: " . basename($archivo) . "\n";

// 2. Carpetas (reversible)
$hechas = [];
function deshacer(array $hechas): void { foreach (array_reverse($hechas) as [$a, $b]) { @rename($b, $a); } }
foreach ($mapa as $viejo => $nuevo) {
    foreach (["$fotos/$viejo", "$fotos/album/$viejo", "$inv/$viejo"] as $d) {
        if (!is_dir($d)) { continue; }
        $dest = str_replace('/' . $viejo, '/' . $nuevo, $d);
        if (!rename($d, $dest)) { deshacer($hechas); exit("no se pudo renombrar $d\n"); }
        $hechas[] = [$d, $dest];
        echo "carpeta: " . basename(dirname($d)) . "/$viejo -> $nuevo\n";
    }
}

// 3. Base de datos, todo o nada
try {
    $pdo->beginTransaction();
    foreach ($mapa as $viejo => $nuevo) {
        $pid = (int) cb_party_db_id($viejo);
        $n = [];
        $st = $pdo->prepare('UPDATE cc_parties SET public_slug = ?, updated_at = ? WHERE id = ? AND public_slug = ?');
        $st->execute([$nuevo, gmdate('Y-m-d H:i:s'), $pid, $viejo]); $n['parties'] = $st->rowCount();
        $st = $pdo->prepare('UPDATE cc_photos SET storage_key = CONCAT(?, SUBSTRING(storage_key, ?)) WHERE party_id = ? AND storage_key LIKE ?');
        $st->execute([$nuevo, strlen($viejo) + 1, $pid, $viejo . '/%']); $n['photos'] = $st->rowCount();
        foreach (['storage_key', 'thumb_storage_key', 'poster_storage_key'] as $col) {
            $st = $pdo->prepare("UPDATE cc_event_media SET $col = CONCAT(?, SUBSTRING($col, ?)) WHERE party_id = ? AND $col LIKE ?");
            $st->execute(["album/$nuevo", strlen("album/$viejo") + 1, $pid, "album/$viejo/%"]); $n["media.$col"] = $st->rowCount();
        }
        $st = $pdo->prepare('UPDATE cc_invitation_outputs o JOIN cc_invitations i ON i.id = o.invitation_id SET o.file_storage_key = CONCAT(?, SUBSTRING(o.file_storage_key, ?)) WHERE i.party_id = ? AND o.file_storage_key LIKE ?');
        $st->execute([$nuevo, strlen($viejo) + 1, $pid, $viejo . '/%']); $n['invitation_outputs'] = $st->rowCount();
        if ($n['parties'] !== 1) { throw new RuntimeException("cc_parties no actualizó $viejo"); }
        echo "bd $viejo -> $nuevo: " . json_encode($n) . "\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    deshacer($hechas);
    exit("ERROR, todo revertido: " . $e->getMessage() . "\n");
}

// 4. Verificación en disco con las claves nuevas
foreach ($mapa as $viejo => $nuevo) {
    $party = cb_load_party_raw($nuevo);
    $pid = (int) cb_party_db_id($nuevo);
    $lista = cb_list_party_photos($nuevo);
    $ok = 0; $mal = 0;
    foreach ($pdo->query("SELECT storage_key FROM cc_photos WHERE party_id = $pid")->fetchAll(PDO::FETCH_COLUMN) as $k) { $p = cb_photo_absolute_path($k); ($p && is_file($p)) ? $ok++ : $mal++; }
    $mok = 0; $mmal = 0;
    foreach ($pdo->query("SELECT storage_key FROM cc_event_media WHERE party_id = $pid AND storage_key <> ''")->fetchAll(PDO::FETCH_COLUMN) as $k) { $p = cb_photo_absolute_path($k); ($p && is_file($p)) ? $mok++ : $mmal++; }
    $iok = 0; $imal = 0;
    foreach ($pdo->query("SELECT o.file_storage_key FROM cc_invitation_outputs o JOIN cc_invitations i ON i.id = o.invitation_id WHERE i.party_id = $pid")->fetchAll(PDO::FETCH_COLUMN) as $k) { is_file("$inv/$k") ? $iok++ : $imal++; }
    echo "verificado $nuevo: fiesta=" . ($party ? $party['nombre'] : 'NULL') . " fotos_listadas=" . count($lista) . " fotos_en_disco=$ok/" . ($ok + $mal) . " album_en_disco=$mok/" . ($mok + $mmal) . " laminas_en_disco=$iok/" . ($iok + $imal) . " viejo_ya_no_existe=" . var_export(cb_load_party_raw($viejo) === null, true) . "\n";
}
