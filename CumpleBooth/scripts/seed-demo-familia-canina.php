<?php
declare(strict_types=1);

require __DIR__ . '/_cli.php';

if (!in_array('--apply', $argv, true)) {
    fwrite(STDOUT, "Dry-run: use --apply para crear/actualizar la fiesta local DEMO-BLUEY.\n");
    exit(0);
}

$loaded = cb_load_parties();
$parties = $loaded['parties'] ?? [];
$publicSlug = '';

foreach ($parties as $slug => $party) {
    if (($party['admin_label'] ?? '') === 'DEMO-BLUEY') {
        $publicSlug = (string) $slug;
        break;
    }
}

if ($publicSlug === '') {
    if (cb_storage_mode() === 'db') {
        $publicSlug = cb_generate_public_slug(cb_pdo(), 'DEMO-BLUEY', 'Aventura Perruna');
    } else {
        $publicSlug = 'demo-bluey-' . bin2hex(random_bytes(5));
    }
}

$existing = is_array($parties[$publicSlug] ?? null) ? $parties[$publicSlug] : [];
$parties[$publicSlug] = array_merge($existing, [
    'public_slug'          => $publicSlug,
    'admin_label'         => 'DEMO-BLUEY',
    'birthday_person_name'=> 'DEMO-BLUEY',
    'nombre'              => 'DEMO-BLUEY',
    'theme_slug'          => 'familia-canina',
    'tema'                => 'familia-canina',
    'event_date'          => '2026-12-31',
    'fecha'               => '2026-12-31',
    'active'              => true,
    'activa'              => true,
    'frameBox'            => null,
    'service_plan'        => 'booth',
    'gallery_enabled'     => false,
    'invitados'           => [
        ['name' => 'Sofía', 'g' => 'f'],
        ['name' => 'Martina', 'g' => 'f'],
        ['name' => 'Emilia', 'g' => 'f'],
        ['name' => 'Isidora', 'g' => 'f'],
        ['name' => 'Antonia', 'g' => 'f'],
        ['name' => 'Mateo', 'g' => 'm'],
        ['name' => 'Benjamín', 'g' => 'm'],
        ['name' => 'Lucas', 'g' => 'm'],
        ['name' => 'Vicente', 'g' => 'm'],
        ['name' => 'Joaquín', 'g' => 'm'],
    ],
    'created_at'          => (string) ($existing['created_at'] ?? date('Y-m-d H:i:s')),
    'creada'              => (string) ($existing['creada'] ?? date('Y-m-d H:i:s')),
]);

if (!cb_save_parties(['parties' => $parties])) {
    fwrite(STDERR, "No se pudo guardar DEMO-BLUEY.\n");
    exit(1);
}

fwrite(STDOUT, $publicSlug . PHP_EOL);
