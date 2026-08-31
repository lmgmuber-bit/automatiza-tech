<?php
declare(strict_types=1);

require dirname(__DIR__) . '/public/lib.php';

$apply = in_array('--apply', $argv, true);
$replace = in_array('--replace', $argv, true);
$normalizeDisclaimer = in_array('--normalize-disclaimer', $argv, true);
$themes = cb_load_themes()['themes'];

$scenes = [
    'carreras' => 'a bright racing birthday hall with red and yellow balloons, checkered flags, trophies, gifts and a clean toy racetrack',
    'mickey' => 'a cheerful rounded clubhouse birthday garden with red, yellow and black balloons, oversized dots, gifts and a playful cake table',
    'hielo' => 'a luminous winter palace birthday hall with icy blue and lavender balloons, crystal snowflakes, gifts and soft sparkling snow',
    'cachorros' => 'a colorful rescue-station birthday yard with blue, red and yellow balloons, playful vehicle bays, gifts and paw-shaped decorations',
    'heroes' => 'a heroic rooftop birthday scene above a bright city, with red, blue and gold balloons, bunting, gifts and colorful confetti',
    'princesas' => 'an elegant fairytale palace birthday garden with pink, lavender and gold balloons, roses, gifts and a royal cake table',
    'dinos' => 'a lush prehistoric jungle birthday clearing with green and orange balloons, tropical leaves, gifts, friendly footprints and a volcano far away',
    'sirenas' => 'a magical underwater birthday hall with coral arches, turquoise and pearl balloons, shells, bubbles, gifts and soft sun rays',
    'juguetes' => 'a playful toy-room birthday scene with sky-blue walls, colorful blocks, star garlands, balloons, gifts and a polished wooden floor',
    'tropical' => 'a sunny island playground birthday scene with coral, blue and aqua balloons, hibiscus flowers, palm leaves, gifts and green grass',
    'familia-canina' => 'a sunny family playground birthday scene with pastel blue, orange and cream balloons, gifts, cupcakes and green grass',
    'kpop' => 'a dazzling concert-stage birthday scene with magenta, violet and electric-cyan balloons, holographic stars, neon beams, gifts and pink-gold confetti',
];

$descriptors = [
    'mickey' => [
        'mickey.jpg' => 'A friendly short black mouse figure with very large round ears, a peach muzzle, expressive oval eyes, red shorts with two white buttons, white gloves and oversized yellow shoes',
        'minnie.jpg' => 'A cheerful short black mouse figure with very large round ears, long eyelashes, a red polka-dot bow and matching dress, white gloves and yellow shoes',
        'donald.jpg' => 'A lively white duck figure with a wide orange bill, big oval eyes, a blue sailor shirt and cap, red bow tie and orange webbed feet',
        'goofy.jpg' => 'A tall lanky black dog figure with a long peach muzzle, two front teeth, floppy ears, green hat, orange shirt, blue trousers and large brown shoes',
        'daisy.jpg' => 'An elegant white duck figure with long eyelashes, a lavender bow and blouse, wide orange bill, bracelet and pink shoes',
        'pluto.jpg' => 'A playful golden-yellow dog figure on four paws, with long black ears, large oval eyes, a green collar and a happy open-mouth smile',
    ],
    'kpop' => [
        'rumi.jpg' => 'A confident young woman idol figure with very long dark purple hair, one bright magenta streak, expressive violet eyes, a warm determined smile and a fitted deep-violet stage outfit with luminous gold trim',
        'mira.jpg' => 'A tall poised young woman idol figure with sleek long black hair, calm dark eyes, a subtle confident smirk and a black and electric-blue stage outfit with silver geometric accents',
        'zoey.jpg' => 'A cheerful petite young woman idol figure with shoulder-length honey-blonde hair in a high ponytail, bright amber eyes, a joyful smile and a hot-pink and white stage outfit with star accessories',
        'luna.jpg' => 'A graceful young woman idol figure with wavy silver-white hair to her waist, luminous pale-blue eyes, a serene smile and an iridescent white and pale-cyan stage outfit with crescent accents',
        'derpy.jpg' => 'A tiny adorable chubby cartoon tiger-cub mascot figure with oversized round eyes, a goofy lopsided grin, tiny ears, orange-and-cream striped fur and stubby paws',
        'sussie.jpg' => 'A small round cartoon bird mascot figure with fluffy magenta feathers, huge sparkling eyes, a tiny orange beak and small raised wings',
    ],
];

function cc_prompt_footer(): string
{
    return "\n\nIMPORTANT: no text, letters, words, numbers, logos, brands or watermarks anywhere."
        . "\nNegative prompt: official character, exact branded copy, text, letters, words, numbers, logo, brand, watermark, flat 2D, cheap CGI, blur, low quality."
        . "\nThis is an original toy design, not based on any existing character.";
}

function cc_prompt_for_slot(string $slug, array $slot, string $scene, array $descriptors): string
{
    $name = (string) $slot['name'];
    $kind = (string) $slot['kind'];
    $label = (string) ($slot['label'] ?? 'Asset');
    $descriptor = $descriptors[$slug][$name] ?? '';

    if ($kind === 'video') {
        if (strpos($name, 'saludo-') === 0) {
            return "Animate the attached approved full-body collectible figure in a vertical 9:16 shot. The figure looks directly at camera, smiles, makes one friendly welcoming gesture and says in clear Latin American Spanish: “¡Ahora nos tomaremos una foto!”. Its mouth must move naturally in exact sync with the spoken audio. Keep the face, colors, outfit and proportions exactly as in the reference image. Static camera, no zoom, no cuts, stable background, 5 to 7 seconds, family-friendly premium toy commercial finish."
                . cc_prompt_footer();
        }
        if (strpos($name, 'revelacion-') === 0) {
            return "Create a vertical 9:16 suspense-to-celebration transition based on the attached approved " . $scene . ". Begin with soft particles and a gentle push through colored light, then end in a bright celebratory reveal with confetti. Preserve the original palette and geometry, no new characters, smooth motion, static horizon, 7 to 10 seconds."
                . cc_prompt_footer();
        }
        if (strpos($name, 'despedida-') === 0) {
            return "Create a warm vertical 9:16 farewell animation based on the attached approved " . $scene . ". Balloons sway gently, confetti drifts and the scene finishes with a soft celebratory glow. No dialogue, no camera shake, no cuts, loop-friendly ending, 5 to 8 seconds."
                . cc_prompt_footer();
        }
        if (strpos($name, 'welcome-') === 0) {
            return "Create a joyful vertical 9:16 welcome animation based on the attached approved " . $scene . ". Use a slow cinematic push-in, subtle parallax, gently swaying balloons and drifting confetti. Preserve every approved figure and its proportions, no dialogue, no cuts, 5 to 8 seconds."
                . cc_prompt_footer();
        }
        if (strpos($name, 'marketing/') === 0) {
            return "Animate the attached approved marketing poster as a premium vertical 9:16 birthday commercial. Use a slow push-in, layered parallax, soft light sweeps, swaying balloons and floating confetti. Keep the central composition stable and leave clean safe zones for text added later in post. 8 to 12 seconds, H.264-ready visual style."
                . cc_prompt_footer();
        }
        return "Animate the attached approved " . strtolower($label) . " as an immersive vertical 9:16 birthday moment. Use smooth cinematic parallax, a gentle forward camera move, subtle lighting changes and stable character proportions. Static horizon, no abrupt zoom, no cuts, 8 to 14 seconds."
            . cc_prompt_footer();
    }

    if ($kind === 'png' && str_ends_with($name, '-cut.png')) {
        return "Using the attached approved collectible-figure portrait as the only visual reference, reproduce exactly one full-body figure, centered, with every limb completely visible. Isolate it on a fully transparent background with real alpha, no floor shadow, no scenery, no props and no background elements. Preserve the approved face, colors, outfit, silhouette and proportions."
            . cc_prompt_footer();
    }

    if ($name === 'fondo-sala.jpg') {
        return "Premium photorealistic collectible-figure party hall, vertical 9:16 and perfectly frontal: " . $scene . ". THE MAIN ELEMENT is a large perfectly SQUARE picture frame centered on the back wall, equal width and height, straight sides, softly rounded corners and an ornate golden border. Its interior is completely empty and white. Keep a clean foreground area below the frame for a separate figure overlay. Sharp premium product photography."
            . cc_prompt_footer();
    }
    if ($name === 'fondo-banner.jpg') {
        return "Premium photorealistic collectible-figure birthday invitation scene, vertical 9:16, centered and symmetrical: " . $scene . ". A large clean cream sign occupies the upper third and remains completely blank. A festive cake and six original toy figures fill the lower half without covering the sign. Warm cinematic light, realistic contact shadows, polished luxury toy quality."
            . cc_prompt_footer();
    }
    if ($name === 'grupo-personajes.png') {
        return "Create a cohesive group portrait using the attached approved character references, vertical 9:16. All six original collectible figures stand together in one friendly birthday pose, full bodies visible, consistent scale, lighting and toy materials. Transparent background with real alpha, no floor and no scenery."
            . cc_prompt_footer();
    }
    if (strpos($name, 'roulette/') === 0) {
        return "A clean vertical 9:16 decorative background for a children's prize wheel based on " . $scene . ". Keep the center and middle-lower area visually calm for interface controls. Layered balloons and confetti only around the edges, premium 3D toy-event finish."
            . cc_prompt_footer();
    }
    if (strpos($name, 'fondo-juego-') === 0) {
        return "A photorealistic vertical 9:16 game-stage background derived from " . $scene . ". Composition intentionally empty in the center, no figures, no furniture and no foreground objects. Keep generous clean interaction space, cinematic lighting and sharp focus."
            . cc_prompt_footer();
    }
    if (strpos($name, 'marketing/') === 0) {
        return "Premium vertical 9:16 marketing poster based on " . $scene . ". Centered luxury toy-commercial composition with a large clean safe area in the upper third for text added later in post, festive foreground, realistic contact shadows and polished materials."
            . cc_prompt_footer();
    }
    if (strpos($name, 'teaser') !== false || strpos($name, 'poster') !== false) {
        return "Premium vertical 9:16 cinematic poster based on " . $scene . ". Build an immersive entrance toward the celebration, with a clear central focal area, layered depth, dramatic but child-friendly light and polished collectible-toy materials."
            . cc_prompt_footer();
    }
    if ($descriptor !== '') {
        return "Premium photorealistic collectible figure product photo, vertical 9:16. " . $descriptor . " fills the foreground in a friendly confident pose. Behind the figure, softly blurred " . $scene . ". Full body visible, realistic contact shadow, glossy vinyl and satin textures, sharp focus."
            . cc_prompt_footer();
    }
    return "Using the attached approved visual reference, create one premium photorealistic collectible figure product photo, vertical 9:16. Preserve the physical traits, colors, outfit and proportions of the approved reference while placing the full-body figure in front of softly blurred " . $scene . ". Friendly pose, realistic contact shadow, sharp luxury toy finish."
        . cc_prompt_footer();
}

$planned = [];
$issues = [];
foreach ($themes as $slug => $theme) {
    $scene = $scenes[$slug] ?? 'a colorful premium children’s birthday celebration with balloons, gifts, confetti and a cake';
    $existing = cb_load_theme_prompts((string) $slug, $theme);
    foreach (cb_theme_upload_slots($theme) as $slot) {
        if (empty($slot['promptable']) || !empty($slot['derived'])) {
            continue;
        }
        $assetKey = (string) $slot['name'];
        $existingPrompt = isset($existing[$assetKey])
            ? trim((string) ($existing[$assetKey]['prompt'] ?? ''))
            : '';
        if (!$replace && $existingPrompt !== '') {
            if (!$normalizeDisclaimer || strpos($existingPrompt, 'This is an original toy design, not based on any existing character.') !== false) {
                continue;
            }
            // Preserva el prompt aprobado y solo agrega el guardarraíl faltante.
            $prompt = $existingPrompt . "\n\nThis is an original toy design, not based on any existing character.";
        } else {
            $prompt = cc_prompt_for_slot((string) $slug, $slot, $scene, $descriptors);
        }
        $validation = cb_validate_theme_prompt($theme, $assetKey, $prompt);
        if (!$validation['ok']) {
            $issues[] = $slug . '/' . $assetKey . ': ' . $validation['error'];
            continue;
        }
        $planned[] = [$slug, $theme, $assetKey, $prompt];
    }
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . ': ' . count($planned) . " prompts válidos.\n";
foreach ($issues as $issue) {
    fwrite(STDERR, "ERROR {$issue}\n");
}
if ($issues) {
    exit(2);
}
if (!$apply) {
    echo "Ejecuta con --apply para guardarlos. Los existentes no se reemplazan salvo --replace; usa --normalize-disclaimer para conservarlos y añadir solo el cierre obligatorio.\n";
    exit(0);
}
foreach ($planned as [$slug, $theme, $assetKey, $prompt]) {
    $result = cb_save_theme_prompt($slug, $theme, $assetKey, $prompt);
    if (!$result['ok']) {
        fwrite(STDERR, "ERROR guardando {$slug}/{$assetKey}: {$result['error']}\n");
        exit(3);
    }
}
echo "OK: prompts guardados con historial en BD.\n";
