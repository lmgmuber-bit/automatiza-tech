<?php
// cumpleclick.com: PIN de galería 1234 para TODAS las fiestas (pedido de Luis, 2026-09-06),
// con las mismas funciones que el admin. Respalda cc_parties en JSON en esta carpeta privada.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI.\n"); exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$respaldo = __DIR__ . '/respaldo-cc_parties-' . gmdate('Ymd-His') . '.json';
$filas = $pdo->query('SELECT * FROM cc_parties')->fetchAll(PDO::FETCH_ASSOC);
file_put_contents($respaldo, json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
chmod($respaldo, 0600);
echo "respaldo: " . basename($respaldo) . " (" . count($filas) . " fiestas)\n";
$data = cb_load_parties();
if (!$data || !is_array($data['parties'] ?? null)) { fwrite(STDERR, "no se pudieron cargar las fiestas\n"); exit(1); }
foreach ($data['parties'] as $slug => &$party) { $party['galeriaPin'] = '1234'; }
unset($party);
if (!cb_save_parties($data)) { fwrite(STDERR, "cb_save_parties falló\n"); exit(1); }
echo "guardado\n";
foreach (cb_load_parties()['parties'] as $slug => $party) {
    echo str_pad($slug, 28) . ' pin1234=' . var_export(cb_verify_party_pin($party, '1234'), true) . "\n";
}
