<?php
echo "✅ PHP funciona correctamente<br>";
echo "📅 Fecha: " . date('Y-m-d H:i:s') . "<br>";
echo "🌐 Servidor: " . $_SERVER['SERVER_NAME'] . "<br>";

// Verificar si WordPress está disponible
if (file_exists('wp-config.php')) {
    echo "✅ wp-config.php encontrado<br>";
    
    try {
        require_once('wp-config.php');
        echo "✅ WordPress cargado correctamente<br>";
        
        // Verificar base de datos
        global $wpdb;
        if ($wpdb) {
            echo "✅ Conexión a base de datos OK<br>";
            
            // Verificar tabla de servicios
            $table_name = $wpdb->prefix . 'automatiza_services';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            echo ($table_exists ? "✅" : "❌") . " Tabla de servicios: $table_name<br>";
            
            if ($table_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo "📊 Servicios en la tabla: $count<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error cargando WordPress: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ wp-config.php no encontrado<br>";
}

echo "<br><h3>🔗 Enlaces de prueba:</h3>";
echo '<a href="/">🏠 Sitio Principal</a><br>';
echo '<a href="/wp-admin/">⚙️ Admin WordPress</a><br>';
echo '<a href="/servicios-admin-simple.php">🛠️ Admin Servicios</a><br>';
?>