<?php
/**
 * Verificar todos los archivos PHP del tema para problemas de encoding
 */

$theme_path = __DIR__ . '/wp-content/themes/automatiza-tech/';
$files_to_check = [
    'functions.php',
    'inc/services-manager.php',
    'inc/services-frontend.php'
];

echo "=== VERIFICACIÓN DE ARCHIVOS DEL TEMA ===\n\n";

foreach ($files_to_check as $file) {
    $full_path = $theme_path . $file;
    
    if (!file_exists($full_path)) {
        echo "❌ Archivo no encontrado: $file\n";
        continue;
    }
    
    echo "📁 Verificando: $file\n";
    
    $content = file_get_contents($full_path);
    $size = strlen($content);
    
    // Verificar BOM
    $bom = "\xEF\xBB\xBF";
    $has_bom = substr($content, 0, 3) === $bom;
    
    echo "  - Tamaño: $size bytes\n";
    echo "  - BOM UTF-8: " . ($has_bom ? "❌ SÍ" : "✅ NO") . "\n";
    
    // Verificar inicio
    $start = substr($content, 0, 10);
    $starts_correctly = substr($content, 0, 5) === '<?php';
    echo "  - Inicia con <?php: " . ($starts_correctly ? "✅ SÍ" : "❌ NO") . "\n";
    
    if (!$starts_correctly) {
        echo "  - Inicio real (hex): ";
        for ($i = 0; $i < min(10, strlen($content)); $i++) {
            echo sprintf('%02X ', ord($content[$i]));
        }
        echo "\n";
        echo "  - Inicio real (texto): '" . substr($content, 0, 20) . "'\n";
    }
    
    // Verificar final del archivo
    $end = substr($content, -10);
    $has_closing_tag = strpos($content, '?>') !== false;
    echo "  - Tiene tag de cierre ?> : " . ($has_closing_tag ? "⚠️ SÍ (puede causar problemas)" : "✅ NO") . "\n";
    
    if ($has_closing_tag) {
        $last_close_pos = strrpos($content, '?>');
        $after_close = substr($content, $last_close_pos + 2);
        if (trim($after_close) !== '') {
            echo "  - ❌ HAY CONTENIDO DESPUÉS DE ?> :\n";
            echo "    Contenido (hex): ";
            for ($i = 0; $i < strlen($after_close); $i++) {
                echo sprintf('%02X ', ord($after_close[$i]));
            }
            echo "\n";
            echo "    Contenido (texto): '" . $after_close . "'\n";
        } else if ($after_close !== '') {
            echo "  - ⚠️ Hay espacios en blanco después de ?>\n";
        }
    }
    
    echo "\n";
}

echo "=== RECOMENDACIONES ===\n";
echo "- Los archivos PHP del tema NO deberían tener tag de cierre ?>\n";
echo "- NO debe haber espacios, saltos de línea o BOM antes de <?php\n";
echo "- NO debe haber contenido después del tag de cierre ?>\n";
echo "\n";

// Verificar también wp-config.php que puede afectar
$wp_config = __DIR__ . '/wp-config.php';
if (file_exists($wp_config)) {
    echo "📁 Verificando wp-config.php\n";
    $config_content = file_get_contents($wp_config);
    $has_closing = strpos($config_content, '?>') !== false;
    echo "  - Tiene tag de cierre ?> : " . ($has_closing ? "⚠️ SÍ" : "✅ NO") . "\n";
    
    if ($has_closing) {
        $last_pos = strrpos($config_content, '?>');
        $after = substr($config_content, $last_pos + 2);
        if ($after !== '') {
            echo "  - ❌ CONTENIDO DESPUÉS DE ?> EN WP-CONFIG\n";
            echo "    Esto DEFINITIVAMENTE causa problemas AJAX\n";
        }
    }
}
?>