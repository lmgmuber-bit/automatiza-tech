<?php
declare(strict_types=1);

require dirname(__DIR__) . '/public/lib.php';

if (!in_array('--apply', $argv, true)) {
    echo "DRY-RUN: crearía/actualizaría las cinco demos locales restantes del plan Full.\n";
    echo "Usa --apply para guardar sin tocar ninguna otra fiesta.\n";
    exit(0);
}

$themes = cb_load_themes()['themes'];
$definitions = [
    'demo-carreras' => ['theme' => 'carreras', 'name' => 'DEMO-CARRERAS'],
    'demo-tropical' => ['theme' => 'tropical', 'name' => 'DEMO-TROPICAL'],
    'demo-kpop' => ['theme' => 'kpop', 'name' => 'DEMO-KPOP'],
    'demo-heroes' => ['theme' => 'heroes', 'name' => 'DEMO-HEROES'],
    'demo-hielo' => ['theme' => 'hielo', 'name' => 'DEMO-HIELO'],
];
$guests = [
    ['name' => 'Sofía', 'g' => 'f'],
    ['name' => 'Martina', 'g' => 'f'],
    ['name' => 'Valentina', 'g' => 'f'],
    ['name' => 'Isidora', 'g' => 'f'],
    ['name' => 'Emilia', 'g' => 'f'],
    ['name' => 'Mateo', 'g' => 'm'],
    ['name' => 'Benjamín', 'g' => 'm'],
    ['name' => 'Tomás', 'g' => 'm'],
    ['name' => 'Vicente', 'g' => 'm'],
    ['name' => 'Joaquín', 'g' => 'm'],
];

$data = cb_load_parties();
$parties = $data['parties'];
$now = gmdate('Y-m-d H:i:s');
foreach ($definitions as $publicSlug => $definition) {
    $themeSlug = $definition['theme'];
    if (!isset($themes[$themeSlug])) {
        fwrite(STDERR, "Falta la temática {$themeSlug}; no se guardó nada.\n");
        exit(2);
    }
    if (isset($parties[$publicSlug])) {
        $existingTheme = (string) ($parties[$publicSlug]['tema'] ?? $parties[$publicSlug]['theme_slug'] ?? '');
        if ($existingTheme !== '' && $existingTheme !== $themeSlug) {
            fwrite(STDERR, "El slug {$publicSlug} ya pertenece a {$existingTheme}; no se guardó nada.\n");
            exit(3);
        }
    }
    $created = (string) ($parties[$publicSlug]['creada'] ?? $now);
    $parties[$publicSlug] = [
        'public_slug' => $publicSlug,
        'admin_label' => 'Demo · ' . ($themes[$themeSlug]['nombre'] ?? $themeSlug),
        'birthday_person_name' => $definition['name'],
        'nombre' => $definition['name'],
        'theme_slug' => $themeSlug,
        'tema' => $themeSlug,
        'fecha' => '2026-12-31',
        'activa' => true,
        // Estas fiestas existen para QA local de la experiencia premium.
        // El gate real sigue en backend: una fiesta Booth jamás recibe fullGame.
        'service_plan' => 'full',
        'gallery_enabled' => false,
        'invitados' => $guests,
        'frameBox' => null,
        'creada' => $created,
    ];
}

if (!cb_save_parties(['parties' => $parties])) {
    fwrite(STDERR, "No se pudieron guardar las demos.\n");
    exit(4);
}
echo "OK: demos idempotentes guardadas (10 invitados: 5 niñas y 5 niños).\n";
