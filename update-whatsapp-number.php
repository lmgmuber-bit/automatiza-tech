<?php
/**
 * Script temporal para actualizar el número de WhatsApp en la base de datos
 * Ejecutar una vez y luego eliminar este archivo
 */

// Cargar WordPress
require_once(__DIR__ . '/wp-load.php');

echo "<h2>Actualizando número de WhatsApp...</h2>\n";

// Actualizar el theme mod
update_option('theme_mods_automatiza-tech', array_merge(
    get_option('theme_mods_automatiza-tech', array()),
    array('whatsapp_number' => '+56 9 4033 1127')
));

// Actualizar la opción por defecto
update_option('default_whatsapp_number', '+56 9 4033 1127');

// Actualizar números en la base de datos (planes guardados)
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

// Verificar si la tabla existe
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    // Actualizar números antiguos en whatsapp_message
    $updated = $wpdb->query(
        "UPDATE $table_name 
        SET whatsapp_message = REPLACE(whatsapp_message, '+56940331127', '+56 9 4033 1127')
        WHERE whatsapp_message LIKE '%+56940331127%'"
    );
    echo "<p>✓ Actualizados $updated registros en la tabla de servicios</p>\n";
    
    // También actualizar sin el +
    $updated2 = $wpdb->query(
        "UPDATE $table_name 
        SET whatsapp_message = REPLACE(whatsapp_message, '56940331127', '+56 9 4033 1127')
        WHERE whatsapp_message LIKE '%56940331127%'"
    );
    echo "<p>✓ Actualizados $updated2 registros adicionales</p>\n";
}

// Verificar
$current_number = get_theme_mod('whatsapp_number', 'No configurado');
$default_number = get_option('default_whatsapp_number', 'No configurado');

echo "<p>✓ Número de WhatsApp actualizado correctamente</p>\n";
echo "<p><strong>Theme Mod:</strong> " . esc_html($current_number) . "</p>\n";
echo "<p><strong>Default Option:</strong> " . esc_html($default_number) . "</p>\n";
echo "<p style='color: green;'><strong>Nuevo número:</strong> +56 9 4033 1127</p>\n";
echo "<hr>";
echo "<p><strong>Instrucciones finales:</strong></p>\n";
echo "<ol>\n";
echo "<li>Limpia la caché del navegador (Ctrl + F5)</li>\n";
echo "<li>Si usas algún plugin de caché de WordPress, límpialo</li>\n";
echo "<li style='color: red;'><strong>ELIMINA este archivo (update-whatsapp-number.php)</strong></li>\n";
echo "</ol>\n";
?>
