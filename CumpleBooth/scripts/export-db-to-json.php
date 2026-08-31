<?php
require __DIR__ . '/_cli.php';
if (cb_storage_mode() !== 'db') { fwrite(STDERR, "Configura storage_mode=db.\n"); exit(2); }
$out = cc_cli_option('out', dirname(__DIR__) . '/storage/state/parties-export.json');
$data = cb_load_parties();
foreach ($data['parties'] as &$party) { unset($party['galeriaHabilitada']); }
unset($party);
fwrite(STDOUT, 'Fiestas a exportar: ' . count($data['parties']) . "\nDestino: $out\n");
if (!cc_cli_require_apply()) { exit(0); }
if (!cb_save_json_file((string) $out, $data)) { fwrite(STDERR, "No se pudo exportar.\n"); exit(1); }
fwrite(STDOUT, "Exportación completada (sin PIN en claro).\n");
