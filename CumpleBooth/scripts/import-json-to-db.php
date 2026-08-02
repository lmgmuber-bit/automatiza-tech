<?php
/** Importación idempotente JSON -> DB, dry-run por defecto y backup fechado. */
require __DIR__ . '/_cli.php';
if (cb_storage_mode() !== 'db') { fwrite(STDERR, "Configura storage_mode=db.\n"); exit(2); }

$source = cb_load_json_file(cb_parties_path());
$themes = cb_load_themes();
if (!is_array($source) || !is_array($source['parties'] ?? null) || !is_array($themes['themes'] ?? null)) {
    fwrite(STDERR, "Catálogos JSON inválidos.\n"); exit(1);
}
$issues = [];
foreach ($source['parties'] as $slug => $party) {
    if (!cb_valid_slug((string) $slug, 2, 40) || !is_array($party)) { $issues[] = "$slug: registro/slug inválido"; continue; }
    if (trim((string) ($party['nombre'] ?? '')) === '') { $issues[] = "$slug: nombre vacío"; }
    $theme = (string) ($party['tema'] ?? '');
    if (!isset($themes['themes'][$theme])) { $issues[] = "$slug: temática inexistente"; }
    if (isset($party['frameBox']) && $party['frameBox'] !== null && cb_normalize_frame_box($party['frameBox']) === null) { $issues[] = "$slug: frameBox inválido"; }
    if (!is_array($party['invitados'] ?? [])) { $issues[] = "$slug: invitados inválidos"; continue; }
    foreach ($party['invitados'] as $index => $guest) {
        if (!is_array($guest) || trim((string) ($guest['name'] ?? '')) === '' || !in_array(($guest['g'] ?? ''), ['f', 'm'], true)) {
            $issues[] = "$slug: invitado #" . ($index + 1) . ' inválido';
        }
    }
    $pin = (string) ($party['galeriaPin'] ?? '');
    if ($pin !== '' && !cb_valid_galeria_pin($pin)) { $issues[] = "$slug: PIN legacy inválido"; }
}
if ($issues) { foreach ($issues as $issue) { fwrite(STDERR, "FAIL $issue\n"); } exit(1); }

$count = count($source['parties']);
fwrite(STDOUT, "Validación OK; fiestas a importar: $count\n");
if (!cc_cli_require_apply()) { exit(0); }

$stamp = gmdate('Ymd-His');
$backupDir = dirname(cb_private_dir((string) cb_config('state_dir'), 'state_dir')) . '/backups/import';
$sourceBackup = "$backupDir/parties-source-$stamp.json";
$dbBackup = "$backupDir/parties-db-before-$stamp.json";
$currentDb = cb_load_parties();
foreach ($currentDb['parties'] as &$party) { unset($party['galeriaHabilitada']); }
unset($party);
if (!cb_save_json_file($sourceBackup, $source) || !cb_save_json_file($dbBackup, $currentDb)) {
    fwrite(STDERR, "No se pudieron crear los backups; importación cancelada.\n"); exit(1);
}
if (!cb_save_parties($source)) { fwrite(STDERR, "Importación fallida; transacción revertida.\n"); exit(1); }
fwrite(STDOUT, "Importación completada. Backups: $sourceBackup y $dbBackup. Los PIN quedaron hasheados.\n");
