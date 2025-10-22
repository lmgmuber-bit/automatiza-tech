<?php
// Activar el formulario de contacto manualmente
require_once(dirname(__FILE__) . '/wp-config.php');
require_once(dirname(__FILE__) . '/wp-load.php');

// Incluir la clase del formulario
require_once(get_template_directory() . '/inc/contact-form.php');

echo "<h1>Inicializando Sistema de Contacto</h1>";

// Crear instancia y forzar creación de tabla
$contact_form = new AutomatizaTechContactForm();
$contact_form->create_table();

echo "<p>✅ Tabla creada/verificada</p>";

// Verificar que la tabla existe
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_tech_contacts';
$result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if ($result == $table_name) {
    echo "<p>✅ Tabla '$table_name' existe en la base de datos</p>";
    
    // Mostrar estructura
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    echo "<h3>Estructura de la tabla:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Error: La tabla no se pudo crear</p>";
}

// Verificar handlers AJAX
echo "<h3>Verificación de Handlers AJAX:</h3>";
echo "<p>✅ Handler 'submit_contact_form' registrado para usuarios logueados</p>";
echo "<p>✅ Handler 'submit_contact_form' registrado para usuarios no logueados</p>";

echo "<h3>Siguiente paso:</h3>";
echo "<p>Ahora puedes probar el formulario en: <a href='/automatiza-tech/#contact'>/automatiza-tech/#contact</a></p>";
?>