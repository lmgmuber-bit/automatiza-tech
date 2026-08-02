<?php
require __DIR__ . '/_cli.php';

$themes = cb_load_themes();
$theme = $themes['themes']['familia-canina'] ?? null;
if (!$theme) { fwrite(STDERR, "Tema familia-canina no encontrado.\n"); exit(1); }

$prompts = [
    'muffin.jpg' => 'The single figure is a friendly cream-colored young puppy with caramel-orange upright ears, irregular caramel patches across the head, back and limbs, cream muzzle and belly, dark-brown oval nose, large warm oval eyes, compact playful proportions and a gentle enthusiastic smile.',
    'chloe.jpg'  => 'The single figure is a playful charcoal-and-white speckled young cattle-dog puppy with dark charcoal upright ears and rounded head patches, crisp irregular black-and-gray mottling over a white body, white rounded muzzle and belly, black oval nose, large warm oval eyes and a bright mischievous smile. Keep the design clearly canine and consistent with the same rounded premium toy family.',
];

foreach ($prompts as $asset => $prompt) {
    $result = cb_save_theme_prompt('familia-canina', $theme, $asset, $prompt);
    if ($result['ok']) {
        fwrite(STDOUT, "OK: $asset guardado en BD\n");
    } else {
        fwrite(STDERR, "ERROR: $asset — " . ($result['error'] ?? 'desconocido') . "\n");
    }
}
