<?php
echo "<h1>Verificación de Sistema de Contacto</h1>";

// Verificar si WordPress está cargado
if (!function_exists('wp_send_json_success')) {
    require_once(dirname(__FILE__) . '/wp-load.php');
}

echo "<h2>1. Verificación de WordPress</h2>";
echo "<p>✅ WordPress cargado correctamente</p>";

// Verificar la clase
require_once(get_template_directory() . '/inc/contact-form.php');
echo "<p>✅ Clase AutomatizaTechContactForm cargada</p>";

// Verificar la tabla
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_tech_contacts';
$result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

echo "<h2>2. Verificación de Base de Datos</h2>";
if ($result == $table_name) {
    echo "<p>✅ Tabla existe: $table_name</p>";
    
    // Contar registros
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>📊 Registros en la tabla: $count</p>";
} else {
    echo "<p>❌ Tabla no existe, creando...</p>";
    $contact_form = new AutomatizaTechContactForm();
    $contact_form->create_table();
    echo "<p>✅ Tabla creada</p>";
}

// Verificar hooks de AJAX
echo "<h2>3. Verificación de Hooks AJAX</h2>";
global $wp_filter;
if (isset($wp_filter['wp_ajax_submit_contact_form'])) {
    echo "<p>✅ Hook wp_ajax_submit_contact_form registrado</p>";
} else {
    echo "<p>❌ Hook wp_ajax_submit_contact_form NO registrado</p>";
}

if (isset($wp_filter['wp_ajax_nopriv_submit_contact_form'])) {
    echo "<p>✅ Hook wp_ajax_nopriv_submit_contact_form registrado</p>";
} else {
    echo "<p>❌ Hook wp_ajax_nopriv_submit_contact_form NO registrado</p>";
}

// Verificar nonce
echo "<h2>4. Verificación de Nonce</h2>";
$nonce = wp_create_nonce('automatiza_ajax_nonce');
echo "<p>✅ Nonce generado: $nonce</p>";

// Probar inserción directa
echo "<h2>5. Prueba de Inserción Directa</h2>";
$test_result = $wpdb->insert(
    $table_name,
    array(
        'name' => 'Prueba Sistema',
        'email' => 'prueba@test.com',
        'company' => 'Test Company',
        'phone' => '+57 300 000 0000',
        'message' => 'Este es un mensaje de prueba del sistema.',
        'submitted_at' => current_time('mysql')
    ),
    array('%s', '%s', '%s', '%s', '%s', '%s')
);

if ($test_result !== false) {
    echo "<p>✅ Inserción directa exitosa. ID: " . $wpdb->insert_id . "</p>";
} else {
    echo "<p>❌ Error en inserción directa: " . $wpdb->last_error . "</p>";
}

// Mostrar URL de AJAX
echo "<h2>6. URLs de AJAX</h2>";
echo "<p>URL AJAX: " . admin_url('admin-ajax.php') . "</p>";

// Mostrar registros recientes
echo "<h2>7. Últimos Registros</h2>";
$recent = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC LIMIT 5");
if ($recent) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Empresa</th><th>Fecha</th></tr>";
    foreach ($recent as $record) {
        echo "<tr>";
        echo "<td>{$record->id}</td>";
        echo "<td>{$record->name}</td>";
        echo "<td>{$record->email}</td>";
        echo "<td>{$record->company}</td>";
        echo "<td>{$record->submitted_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay registros en la tabla</p>";
}

echo "<h2>8. Prueba AJAX Manual</h2>";
?>
<form id="ajax-test-form">
    <input type="text" name="name" placeholder="Nombre" value="Juan Test" required><br><br>
    <input type="email" name="email" placeholder="Email" value="juan@test.com" required><br><br>
    <input type="text" name="company" placeholder="Empresa" value="Test Corp"><br><br>
    <input type="text" name="phone" placeholder="Teléfono" value="+57 300 123 4567"><br><br>
    <textarea name="message" placeholder="Mensaje" required>Mensaje de prueba desde la verificación del sistema.</textarea><br><br>
    <button type="submit">Probar AJAX</button>
</form>

<div id="ajax-result" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; display: none;"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
jQuery(document).ready(function($) {
    $('#ajax-test-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'submit_contact_form',
            name: $('input[name="name"]').val(),
            email: $('input[name="email"]').val(),
            company: $('input[name="company"]').val(),
            phone: $('input[name="phone"]').val(),
            message: $('textarea[name="message"]').val(),
            nonce: '<?php echo $nonce; ?>'
        };
        
        console.log('Enviando:', formData);
        
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', formData)
            .done(function(response) {
                console.log('Respuesta:', response);
                $('#ajax-result').show().html('✅ Éxito: ' + JSON.stringify(response));
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.log('Error:', textStatus, errorThrown);
                console.log('Respuesta completa:', jqXHR.responseText);
                $('#ajax-result').show().html('❌ Error: ' + textStatus + '<br>Respuesta: ' + jqXHR.responseText);
            });
    });
});
</script>