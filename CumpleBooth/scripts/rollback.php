<?php
/** Rollback de cutover DB -> JSON. Nunca elimina tablas, DB, backups ni fotos. */
require __DIR__ . '/_cli.php';

$apply = cc_cli_require_apply();
$snapshot = cc_cli_option('snapshot');
$stamp = gmdate('Ymd-His');
$privateBase = dirname(cb_private_dir((string) cb_config('state_dir'), 'state_dir'));
$out = (string) cc_cli_option('out', $privateBase . "/backups/cutover/parties-rollback-$stamp.json");

if ($snapshot !== null) {
    $data = cb_load_json_file($snapshot);
    if (!is_array($data) || !is_array($data['parties'] ?? null)) {
        fwrite(STDERR, "Snapshot JSON inválido.\n"); exit(1);
    }
    $source = 'snapshot ' . $snapshot;
} else {
    if (cb_storage_mode() !== 'db') { fwrite(STDERR, "Configura storage_mode=db para exportar antes del rollback.\n"); exit(2); }
    try {
        $data = cb_load_parties();
        $source = 'base de datos activa';
    } catch (Throwable $e) {
        fwrite(STDERR, "BD no disponible. Repite con --snapshot=<snapshot-cutover.json>; RPO = fecha de ese snapshot.\n");
        exit(1);
    }
}
foreach ($data['parties'] as &$party) { unset($party['galeriaHabilitada']); }
unset($party);
fwrite(STDOUT, 'Origen: ' . $source . '; fiestas: ' . count($data['parties']) . "\nDestino JSON privado: $out\n");
if (!$apply) { exit(0); }
if (!cb_save_json_file($out, $data)) { fwrite(STDERR, "No se pudo escribir el snapshot de rollback.\n"); exit(1); }
fwrite(STDOUT, "Snapshot creado. Cambia en la configuración externa storage_mode=json y parties_json_path=$out; conserva DB y snapshots durante estabilización.\n");
