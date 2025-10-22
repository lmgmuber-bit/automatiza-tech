<?php
/**
 * Script para limpiar BOM y caracteres invisibles de archivos PHP
 */

$file_path = __DIR__ . '/wp-content/themes/automatiza-tech/inc/services-manager.php';

// Leer el contenido del archivo
$content = file_get_contents($file_path);

echo "=== ANÁLISIS DEL ARCHIVO ===\n";
echo "Archivo: $file_path\n";
echo "Tamaño original: " . strlen($content) . " bytes\n";

// Detectar BOM UTF-8
$bom = "\xEF\xBB\xBF";
$has_bom = substr($content, 0, 3) === $bom;
echo "BOM UTF-8 detectado: " . ($has_bom ? "❌ SÍ" : "✅ NO") . "\n";

// Mostrar los primeros caracteres en hexadecimal
echo "Primeros 10 bytes (hex): ";
for ($i = 0; $i < min(10, strlen($content)); $i++) {
    echo sprintf('%02X ', ord($content[$i]));
}
echo "\n";

// Mostrar caracteres invisibles al inicio
echo "Primeros 20 caracteres visibles: '" . substr(trim($content), 0, 20) . "'\n";

// Limpiar el contenido
$cleaned_content = $content;

// Remover BOM si existe
if ($has_bom) {
    $cleaned_content = substr($cleaned_content, 3);
    echo "✅ BOM removido\n";
}

// Remover espacios en blanco al inicio y final
$original_length = strlen($cleaned_content);
$cleaned_content = trim($cleaned_content);
$trimmed_bytes = $original_length - strlen($cleaned_content);

if ($trimmed_bytes > 0) {
    echo "✅ Removidos $trimmed_bytes bytes de espacios en blanco\n";
}

// Asegurar que empiece con <?php
if (substr($cleaned_content, 0, 5) !== '<?php') {
    echo "❌ ADVERTENCIA: El archivo no empieza con <?php\n";
    echo "Contenido actual al inicio: '" . substr($cleaned_content, 0, 50) . "'\n";
} else {
    echo "✅ El archivo empieza correctamente con <?php\n";
}

// Crear backup
$backup_path = $file_path . '.backup-' . date('YmdHis');
if (file_put_contents($backup_path, $content)) {
    echo "✅ Backup creado: $backup_path\n";
} else {
    echo "❌ Error al crear backup\n";
    exit(1);
}

// Escribir contenido limpio
if (file_put_contents($file_path, $cleaned_content)) {
    echo "✅ Archivo limpiado exitosamente\n";
    echo "Nuevo tamaño: " . strlen($cleaned_content) . " bytes\n";
    echo "Bytes removidos: " . (strlen($content) - strlen($cleaned_content)) . "\n";
} else {
    echo "❌ Error al escribir archivo limpio\n";
    exit(1);
}

echo "\n=== VERIFICACIÓN POST-LIMPIEZA ===\n";
$new_content = file_get_contents($file_path);
$new_bom = substr($new_content, 0, 3) === $bom;
echo "BOM después de limpieza: " . ($new_bom ? "❌ TODAVÍA PRESENTE" : "✅ ELIMINADO") . "\n";

echo "Primeros 10 bytes después de limpieza (hex): ";
for ($i = 0; $i < min(10, strlen($new_content)); $i++) {
    echo sprintf('%02X ', ord($new_content[$i]));
}
echo "\n";

echo "\n✅ LIMPIEZA COMPLETADA\n";
echo "Ahora prueba nuevamente la funcionalidad de editar servicios.\n";
?>