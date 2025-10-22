<?php
/**
 * Script de limpieza simple
 */

$files_to_fix = [
    'wp-content/themes/automatiza-tech/functions.php',
    'wp-content/themes/automatiza-tech/inc/services-manager.php',
    'wp-content/themes/automatiza-tech/inc/services-frontend.php'
];

echo "=== LIMPIEZA DE ARCHIVOS ===\n";

foreach ($files_to_fix as $relative_path) {
    $full_path = __DIR__ . '/' . $relative_path;
    
    if (!file_exists($full_path)) {
        echo "❌ No encontrado: $relative_path\n";
        continue;
    }
    
    echo "🔧 Limpiando: $relative_path\n";
    
    // Leer contenido
    $content = file_get_contents($full_path);
    
    // Crear backup
    $backup_path = $full_path . '.backup-ajax-fix';
    file_put_contents($backup_path, $content);
    
    // Remover BOM UTF-8
    $bom = "\xEF\xBB\xBF";
    if (substr($content, 0, 3) === $bom) {
        $content = substr($content, 3);
        echo "  ✅ BOM removido\n";
    }
    
    // Limpiar espacios al inicio
    $content = ltrim($content);
    
    // Remover todo después del último ?>
    $last_close_pos = strrpos($content, '?>');
    if ($last_close_pos !== false) {
        $content = substr($content, 0, $last_close_pos);
        echo "  ✅ Contenido después de ?> removido\n";
    }
    
    // Limpiar espacios al final
    $content = rtrim($content);
    
    // Escribir archivo limpio
    if (file_put_contents($full_path, $content)) {
        echo "  ✅ Archivo limpio guardado\n";
    } else {
        echo "  ❌ Error al guardar\n";
    }
    
    echo "\n";
}

echo "✅ LIMPIEZA COMPLETADA\n";
echo "Prueba ahora la edición de servicios en el admin de WordPress.\n";