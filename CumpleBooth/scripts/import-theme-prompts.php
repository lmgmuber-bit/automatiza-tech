<?php
/** Importa prompts del documento histórico. Dry-run por defecto; usa --apply. */
require __DIR__ . '/_cli.php';

$apply = in_array('--apply', $argv, true);
$source = dirname(__DIR__) . '/docs/PROMPTS-TEMATICAS.md';
foreach ($argv as $arg) {
    if (strpos($arg, '--source=') === 0) {
        $source = substr($arg, strlen('--source='));
    }
}
if (!is_file($source)) {
    throw new RuntimeException('No existe el documento de prompts: ' . $source);
}
$themesData = cb_load_themes();
$themes = is_array($themesData['themes'] ?? null) ? $themesData['themes'] : [];
$markdown = file_get_contents($source);
if ($markdown === false) {
    throw new RuntimeException('No se pudo leer el documento de prompts.');
}
$parsed = cb_parse_theme_prompts_markdown($markdown, $themes);
$count = 0;
foreach ($parsed['prompts'] as $themePrompts) {
    $count += count($themePrompts);
}
fwrite(STDOUT, ($apply ? 'APPLY' : 'DRY-RUN') . ": $count prompts válidos\n");
foreach ($parsed['issues'] as $issue) {
    fwrite(STDERR, "omitido $issue\n");
}
if (!$apply) {
    fwrite(STDOUT, "Sin cambios. Ejecuta con --apply después de revisar el resultado.\n");
    exit(0);
}
if (cb_storage_mode() !== 'db') {
    throw new RuntimeException('La importación requiere storage_mode=db.');
}
$pdo = cb_pdo();
$pdo->beginTransaction();
try {
    foreach ($parsed['prompts'] as $themeSlug => $themePrompts) {
        foreach ($themePrompts as $assetKey => $promptText) {
            $result = cb_save_theme_prompt($themeSlug, $themes[$themeSlug], $assetKey, $promptText);
            if (!$result['ok']) {
                throw new RuntimeException($themeSlug . '/' . $assetKey . ': ' . $result['error']);
            }
        }
    }
    $pdo->commit();
    fwrite(STDOUT, "Importación completada: $count prompts.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
