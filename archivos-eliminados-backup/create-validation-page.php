<?php
/**
 * Script para crear automáticamente la página de validación de facturas
 */

require_once('wp-load.php');

echo "<h1>📄 Crear Página de Validación de Facturas</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
</style>";

// Verificar si la página ya existe
$existing_page = get_page_by_path('validar-factura');

if ($existing_page) {
    echo "<p class='info'>⚠️ La página 'validar-factura' ya existe</p>";
    echo "<p>URL: <a href='" . get_permalink($existing_page->ID) . "' target='_blank'>" . get_permalink($existing_page->ID) . "</a></p>";
    echo "<p>Estado: " . $existing_page->post_status . "</p>";
    
    if ($existing_page->post_status !== 'publish') {
        echo "<p class='error'>La página existe pero NO está publicada</p>";
        
        // Publicar la página
        $result = wp_update_post(array(
            'ID' => $existing_page->ID,
            'post_status' => 'publish'
        ));
        
        if ($result) {
            echo "<p class='success'>✅ Página publicada correctamente</p>";
        }
    } else {
        echo "<p class='success'>✅ La página ya está publicada y lista para usar</p>";
    }
} else {
    // Crear la página
    $page_data = array(
        'post_title'    => 'Validar Factura',
        'post_content'  => '[validar_factura]',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_author'   => 1,
        'post_name'     => 'validar-factura'
    );
    
    $page_id = wp_insert_post($page_data);
    
    if ($page_id) {
        echo "<p class='success'>✅ Página creada exitosamente</p>";
        echo "<p><strong>URL:</strong> <a href='" . get_permalink($page_id) . "' target='_blank'>" . get_permalink($page_id) . "</a></p>";
        echo "<p class='info'>Ya puedes compartir esta URL con tus clientes para que validen sus facturas</p>";
    } else {
        echo "<p class='error'>❌ Error al crear la página</p>";
    }
}

echo "<hr>";
echo "<h2>🧪 Testing</h2>";

// Probar el shortcode
if (shortcode_exists('validar_factura')) {
    echo "<p class='success'>✅ Shortcode [validar_factura] está registrado</p>";
} else {
    echo "<p class='error'>❌ Shortcode [validar_factura] NO está registrado</p>";
}

// Probar AJAX endpoint
$test_url = admin_url('admin-ajax.php') . '?action=validate_invoice&invoice_number=AT-20251112-0008';
echo "<p><strong>Test directo del endpoint AJAX:</strong></p>";
echo "<p><a href='{$test_url}' target='_blank'>{$test_url}</a></p>";

echo "<hr>";
echo "<h2>📋 Resumen</h2>";
echo "<p>Todo listo para usar el sistema de validación de facturas.</p>";
?>
