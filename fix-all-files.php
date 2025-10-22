<?php
/**
 * Script de limpieza completa para corregir problemas de encoding
 */

$files_to_fix = [
    'wp-content/themes/automatiza-tech/functions.php',
    'wp-content/themes/automatiza-tech/inc/services-manager.php',
    'wp-content/themes/automatiza-tech/inc/services-frontend.php'
];

echo "=== LIMPIEZA COMPLETA DE ARCHIVOS ===\n\n";

foreach ($files_to_fix as $relative_path) {
    $full_path = __DIR__ . '/' . $relative_path;
    
    if (!file_exists($full_path)) {
        echo "❌ Archivo no encontrado: $relative_path\n";
        continue;
    }
    
    echo "🔧 Limpiando: $relative_path\n";
    
    // Leer contenido
    $content = file_get_contents($full_path);
    $original_size = strlen($content);
    
    // Crear backup
    $backup_path = $full_path . '.backup-' . date('YmdHis');
    file_put_contents($backup_path, $content);
    echo "  ✅ Backup creado\n";
    
    // 1. Remover BOM UTF-8
    $bom = "\xEF\xBB\xBF";
    if (substr($content, 0, 3) === $bom) {
        $content = substr($content, 3);
        echo "  ✅ BOM UTF-8 removido\n";
    }
    
    // 2. Asegurar que empiece con <?php
    $content = ltrim($content);
    if (substr($content, 0, 5) !== '<?php') {
        echo "  ❌ ERROR: No empieza con <?php\n";
        continue;
    }
    
    // 3. Encontrar y limpiar contenido después de ?>
    $last_close_pos = strrpos($content, '?>');
    if ($last_close_pos !== false) {
        // Hay tag de cierre
        $before_close = substr($content, 0, $last_close_pos);
        $after_close = substr($content, $last_close_pos + 2);
        
        if (trim($after_close) !== '') {
            echo "  ⚠️ CONTENIDO DESPUÉS DE ?> ENCONTRADO Y REMOVIDO\n";
            echo "    Contenido removido: " . strlen($after_close) . " bytes\n";
            
            // Quitar todo el contenido después del ?>
            $content = $before_close;
            echo "  ✅ Tag de cierre ?> también removido (buena práctica)\n";
        } else if ($after_close !== '') {
            // Solo espacios en blanco
            echo "  ⚠️ Espacios después de ?> removidos\n";
            $content = $before_close;
            echo "  ✅ Tag de cierre ?> también removido (buena práctica)\n";
        } else {
            // Remover el tag de cierre por buena práctica
            $content = $before_close;
            echo "  ✅ Tag de cierre ?> removido (buena práctica)\n";
        }
    }
    
    // 4. Asegurar que termine sin espacios extra
    $content = rtrim($content);
    
    // 5. Escribir contenido limpio
    if (file_put_contents($full_path, $content)) {
        $new_size = strlen($content);
        $bytes_removed = $original_size - $new_size;
        echo "  ✅ Archivo limpiado exitosamente\n";
        echo "    Tamaño original: $original_size bytes\n";
        echo "    Tamaño nuevo: $new_size bytes\n";
        echo "    Bytes removidos: $bytes_removed\n";
    } else {
        echo "  ❌ Error al escribir archivo\n";
    }
    
    echo "\n";
}

echo "=== VERIFICACIÓN POST-LIMPIEZA ===\n";

foreach ($files_to_fix as $relative_path) {
    $full_path = __DIR__ . '/' . $relative_path;
    
    if (!file_exists($full_path)) continue;
    
    echo "📋 Verificando: $relative_path\n";
    
    $content = file_get_contents($full_path);
    
    // Verificar BOM
    $bom = "\xEF\xBB\xBF";
    $has_bom = substr($content, 0, 3) === $bom;
    echo "  - BOM UTF-8: " . ($has_bom ? "❌ AÚN PRESENTE" : "✅ ELIMINADO") . "\n";
    
    // Verificar inicio
    $starts_correctly = substr($content, 0, 5) === '<?php';
    echo "  - Inicia con <?php: " . ($starts_correctly ? "✅ SÍ" : "❌ NO") . "\n";
    
    // Verificar que no tenga ?>
    $has_closing = strpos($content, '?>') !== false;
    echo "  - Tiene tag ?> : " . ($has_closing ? "❌ SÍ" : "✅ NO") . "\n";
    
    echo "\n";
}

echo "🎉 LIMPIEZA COMPLETADA\n";
echo "Ahora prueba la funcionalidad de editar servicios en el admin.\n";
echo "Las respuestas AJAX deberían funcionar correctamente.\n";
?>