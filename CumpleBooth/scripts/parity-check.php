<?php
require __DIR__ . '/_cli.php';
if (cb_storage_mode() !== 'db') { fwrite(STDERR, "Configura storage_mode=db.\n"); exit(2); }
$json = cb_load_json_file(cb_parties_path());
$db = cb_load_parties();
$issues = [];
foreach (($json['parties'] ?? []) as $slug => $party) {
    if (!isset($db['parties'][$slug])) { $issues[] = "$slug falta en DB"; continue; }
    $row = $db['parties'][$slug];
    foreach (['nombre', 'tema', 'fecha'] as $key) { if ((string) ($party[$key] ?? '') !== (string) ($row[$key] ?? '')) { $issues[] = "$slug difiere en $key"; } }
    if ((bool) ($party['activa'] ?? false) !== (bool) ($row['activa'] ?? false)) { $issues[] = "$slug difiere en activa"; }
    if (json_encode(array_values($party['invitados'] ?? [])) !== json_encode(array_values($row['invitados'] ?? []))) { $issues[] = "$slug difiere en invitados"; }
    if (json_encode(cb_normalize_frame_box($party['frameBox'] ?? null)) !== json_encode(cb_normalize_frame_box($row['frameBox'] ?? null))) { $issues[] = "$slug difiere en frameBox"; }
}
foreach ($db['parties'] as $slug => $_) { if (!isset($json['parties'][$slug])) { $issues[] = "$slug solo existe en DB"; } }
$demo = cb_resolve_party('demo');
if (empty($demo['ok'])) { $issues[] = 'demo no resuelve desde DB'; }
if (isset($demo['party']['galeriaPin']) || isset($demo['party']['galeriaPinHash']) || isset($demo['party']['galeriaPinHmac'])) { $issues[] = 'API pública demo expone secretos de galería'; }
if ($issues) { foreach ($issues as $issue) { fwrite(STDERR, "FAIL $issue\n"); } exit(1); }
fwrite(STDOUT, 'OK paridad de ' . count($db['parties']) . " fiestas.\n");
