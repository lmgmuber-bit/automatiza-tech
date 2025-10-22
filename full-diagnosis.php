<?php
// Incluir WordPress
require_once(dirname(__FILE__) . '/wp-load.php');

echo "<h1>Diagnóstico del Formulario de Contacto</h1>";

// 1. Verificar que la clase existe y está cargada
echo "<h2>1. Verificación de Clase</h2>";
if (class_exists('AutomatizaTechContactForm')) {
    echo "<p>✅ Clase AutomatizaTechContactForm existe</p>";
} else {
    echo "<p>❌ Clase AutomatizaTechContactForm NO existe</p>";
    echo "<p>Intentando cargar...</p>";
    require_once(get_template_directory() . '/inc/contact-form.php');
    if (class_exists('AutomatizaTechContactForm')) {
        echo "<p>✅ Clase cargada exitosamente</p>";
    } else {
        echo "<p>❌ Error al cargar la clase</p>";
    }
}

// 2. Verificar hooks
echo "<h2>2. Verificación de Hooks AJAX</h2>";
global $wp_filter;

$hooks_to_check = [
    'wp_ajax_submit_contact_form',
    'wp_ajax_nopriv_submit_contact_form'
];

foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
        echo "<p>✅ Hook '$hook' está registrado</p>";
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function'])) {
                    $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                    $method = $callback['function'][1];
                    echo "<p>   - Callback: {$class}::{$method}</p>";
                }
            }
        }
    } else {
        echo "<p>❌ Hook '$hook' NO está registrado</p>";
    }
}

// 3. Prueba manual del handler
echo "<h2>3. Prueba Manual del Handler</h2>";

// Simular $_POST
$_POST = [
    'action' => 'submit_contact_form',
    'name' => 'Prueba Manual',
    'email' => 'prueba@manual.com',
    'company' => 'Test Company',
    'phone' => '+57 300 123 4567',
    'message' => 'Este es un mensaje de prueba manual',
    'nonce' => wp_create_nonce('automatiza_ajax_nonce')
];

$_SERVER['REQUEST_METHOD'] = 'POST';

echo "<p>Datos simulados de POST:</p>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

// Intentar ejecutar el handler
try {
    ob_start();
    do_action('wp_ajax_nopriv_submit_contact_form');
    $output = ob_get_clean();
    echo "<p>✅ Handler ejecutado. Salida:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
} catch (Exception $e) {
    echo "<p>❌ Error al ejecutar handler: " . $e->getMessage() . "</p>";
}

// 4. Verificar la tabla
echo "<h2>4. Verificación de Tabla</h2>";
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_tech_contacts';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if ($table_exists) {
    echo "<p>✅ Tabla existe: $table_name</p>";
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>Registros en la tabla: $count</p>";
    
    // Mostrar últimos 3 registros
    $recent = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC LIMIT 3");
    if ($recent) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Fecha</th></tr>";
        foreach ($recent as $record) {
            echo "<tr><td>{$record->id}</td><td>{$record->name}</td><td>{$record->email}</td><td>{$record->submitted_at}</td></tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>❌ Tabla no existe</p>";
}

// 5. Verificar AJAX endpoint
echo "<h2>5. Verificación de Endpoint AJAX</h2>";
$ajax_url = admin_url('admin-ajax.php');
echo "<p>URL AJAX: $ajax_url</p>";

// Test básico de conectividad
$response = wp_remote_get($ajax_url);
if (is_wp_error($response)) {
    echo "<p>❌ Error de conectividad: " . $response->get_error_message() . "</p>";
} else {
    echo "<p>✅ Endpoint AJAX accesible</p>";
}

?>

<h2>6. Prueba AJAX en Vivo</h2>
<form id="live-test-form">
    <p><input type="text" name="name" placeholder="Nombre" value="Test Usuario" required></p>
    <p><input type="email" name="email" placeholder="Email" value="test@usuario.com" required></p>
    <p><input type="text" name="company" placeholder="Empresa" value="Test Corp"></p>
    <p><input type="text" name="phone" placeholder="Teléfono" value="+57 300 000 0000"></p>
    <p><textarea name="message" placeholder="Mensaje" required>Mensaje de prueba desde diagnóstico</textarea></p>
    <p><button type="submit">Probar Envío AJAX</button></p>
</form>

<div id="test-result" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; display: none;"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
jQuery(document).ready(function($) {
    $('#live-test-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'submit_contact_form',
            name: $('input[name="name"]').val(),
            email: $('input[name="email"]').val(),
            company: $('input[name="company"]').val(),
            phone: $('input[name="phone"]').val(),
            message: $('textarea[name="message"]').val(),
            nonce: '<?php echo wp_create_nonce("automatiza_ajax_nonce"); ?>'
        };
        
        console.log('Enviando datos:', formData);
        
        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Respuesta exitosa:', response);
                $('#test-result').show().html('✅ Éxito: ' + JSON.stringify(response, null, 2));
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Error:', textStatus, errorThrown);
                console.log('Respuesta del servidor:', jqXHR.responseText);
                $('#test-result').show().html('❌ Error: ' + textStatus + '<br><strong>Respuesta:</strong><pre>' + jqXHR.responseText + '</pre>');
            }
        });
    });
});
</script>