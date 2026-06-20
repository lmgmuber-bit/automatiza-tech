<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para actualizar el número de WhatsApp en la base de datos
 * Ejecutar una sola vez y luego eliminar
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Número antiguo y nuevo
$old_number = '+56 9 4033 1127';
$new_number = '+56 9 2700 2984';

echo "<h2>Actualización de Número de WhatsApp</h2>";
echo "<p><strong>Número antiguo:</strong> " . esc_html($old_number) . "</p>";
echo "<p><strong>Número nuevo:</strong> " . esc_html($new_number) . "</p>";
echo "<hr>";

// Obtener el tema activo
$theme = get_option('stylesheet');
$theme_mods_option = 'theme_mods_' . $theme;

// Obtener las configuraciones del tema
$theme_mods = get_option($theme_mods_option);

echo "<h3>Estado Actual:</h3>";
if ($theme_mods && isset($theme_mods['whatsapp_number'])) {
    echo "<p>Número guardado en theme_mods: <strong>" . esc_html($theme_mods['whatsapp_number']) . "</strong></p>";
} else {
    echo "<p>No hay número de WhatsApp guardado en theme_mods</p>";
}

// Actualizar el número
if ($theme_mods) {
    $theme_mods['whatsapp_number'] = $new_number;
    $updated = update_option($theme_mods_option, $theme_mods);
    
    if ($updated) {
        echo "<p style='color: green;'>✅ <strong>Número actualizado correctamente</strong></p>";
    } else {
        echo "<p style='color: orange;'>⚠️ El número ya estaba actualizado o no hubo cambios</p>";
    }
} else {
    // Si no existen theme_mods, crearlos
    $theme_mods = array('whatsapp_number' => $new_number);
    $added = add_option($theme_mods_option, $theme_mods);
    
    if ($added) {
        echo "<p style='color: green;'>✅ <strong>Configuración creada con el nuevo número</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Error al crear la configuración</p>";
    }
}

// Verificar el cambio
$theme_mods_updated = get_option($theme_mods_option);
echo "<h3>Estado Después de la Actualización:</h3>";
if ($theme_mods_updated && isset($theme_mods_updated['whatsapp_number'])) {
    echo "<p>Número actual en theme_mods: <strong>" . esc_html($theme_mods_updated['whatsapp_number']) . "</strong></p>";
} else {
    echo "<p style='color: red;'>❌ No se pudo verificar la actualización</p>";
}

// También buscar en otras opciones que puedan tener el número
echo "<hr>";
echo "<h3>Otras configuraciones:</h3>";

$options_to_check = array(
    'default_whatsapp_number',
    'whatsapp_number',
    'contact_phone',
    'company_phone'
);

foreach ($options_to_check as $option_name) {
    $value = get_option($option_name);
    if ($value && (strpos($value, '4033') !== false || strpos($value, '6432') !== false)) {
        echo "<p>⚠️ Encontrado en '{$option_name}': <strong>" . esc_html($value) . "</strong></p>";
        
        // Actualizar si tiene el número antiguo
        if (strpos($value, '4033 1127') !== false || strpos($value, '40331127') !== false) {
            update_option($option_name, $new_number);
            echo "<p style='color: green;'>✅ Actualizado '{$option_name}' al nuevo número</p>";
        }
    } elseif ($value) {
        echo "<p>✓ '{$option_name}': " . esc_html($value) . "</p>";
    }
}

echo "<hr>";
echo "<h3>✅ Proceso completado</h3>";
echo "<p><strong>Importante:</strong> Después de verificar que todo funciona correctamente, elimina este archivo por seguridad.</p>";
echo "<p>Para ver el resultado, recarga tu sitio y limpia el caché del navegador (Ctrl+F5).</p>";
?>
