<?php
require_once(dirname(__FILE__) . '/wp-load.php');

echo "<h1>Test del Shortcode</h1>";

// Verificar si el shortcode está registrado
global $shortcode_tags;
if (isset($shortcode_tags['contact_form'])) {
    echo "<p>✅ Shortcode 'contact_form' está registrado</p>";
    echo "<p>Función: " . print_r($shortcode_tags['contact_form'], true) . "</p>";
} else {
    echo "<p>❌ Shortcode 'contact_form' NO está registrado</p>";
    echo "<p>Shortcodes disponibles:</p>";
    echo "<pre>" . print_r(array_keys($shortcode_tags), true) . "</pre>";
}

echo "<h2>Renderizado del Shortcode:</h2>";
$shortcode_output = do_shortcode('[contact_form]');
echo $shortcode_output;

if (empty($shortcode_output)) {
    echo "<p>❌ El shortcode no generó contenido</p>";
    
    // Intentar cargar manualmente
    require_once(get_template_directory() . '/inc/contact-shortcode.php');
    echo "<p>Archivo cargado manualmente, intentando de nuevo...</p>";
    $shortcode_output = do_shortcode('[contact_form]');
    echo $shortcode_output;
}
?>