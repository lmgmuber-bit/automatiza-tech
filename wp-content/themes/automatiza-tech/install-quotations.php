<?php
/**
 * Script de instalación para el sistema de cotizaciones
 * Ejecutar una sola vez para crear la tabla wp_automatiza_tech_quotations
 */

// No permitir acceso directo
if (!defined('ABSPATH')) {
    // Si no está en WordPress, verificar si se está llamando directamente
    if (!isset($_SERVER['HTTP_HOST'])) {
        die('Acceso directo no permitido. Ejecutar desde WordPress.');
    }
    
    // Cargar WordPress
    $wp_load_path = '../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die('No se puede cargar WordPress. Asegúrate de que este archivo esté en wp-content/themes/automatiza-tech/');
    }
}

global $wpdb;

echo "<h2>🚀 Instalación del Sistema de Cotizaciones</h2>";
echo "<p>Creando tabla de cotizaciones...</p>";

$table_name = $wpdb->prefix . 'automatiza_tech_quotations';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL COMMENT 'Número de cotización: C-AT-YYYYMMDD-XXXX',
  `contact_id` int(11) NOT NULL COMMENT 'ID del contacto',
  `contact_name` varchar(255) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_company` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL COMMENT 'ID del primer plan (para compatibilidad)',
  `plan_name` text NOT NULL COMMENT 'Nombre(s) de plan(es), separados por + si son múltiples',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quotation_html` longtext COMMENT 'HTML completo de la cotización',
  `quotation_file_path` varchar(500) DEFAULT NULL COMMENT 'Ruta al archivo PDF generado',
  `qr_code_data` text COMMENT 'Datos codificados en el QR',
  `status` enum('pending','accepted','rejected','expired') NOT NULL DEFAULT 'pending',
  `valid_until` datetime NOT NULL COMMENT 'Fecha de vencimiento (3 días desde creación)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text COMMENT 'Notas adicionales',
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `contact_id` (`contact_id`),
  KEY `contact_email` (`contact_email`),
  KEY `status` (`status`),
  KEY `valid_until` (`valid_until`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB {$charset_collate} COMMENT='Cotizaciones enviadas a contactos interesados';";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

// Verificar si la tabla se creó correctamente
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name;

if ($table_exists) {
    echo "<p style='color: green; font-weight: bold;'>✅ Tabla creada exitosamente: {$table_name}</p>";
    
    // Verificar estructura
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    echo "<h3>📋 Estructura de la tabla:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Comentario</th></tr>";
    foreach ($columns as $column) {
        $comment = isset($column->Comment) ? $column->Comment : '';
        echo "<tr><td>{$column->Field}</td><td>{$column->Type}</td><td>{$comment}</td></tr>";
    }
    echo "</table>";
    
    echo "<br><hr><br>";
    echo "<h3>✅ INSTALACIÓN COMPLETADA</h3>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ol>";
    echo "<li>Ve al panel de contactos: <a href='" . admin_url('admin.php?page=automatiza-tech-contacts') . "'>Gestión de Contactos</a></li>";
    echo "<li>Selecciona un contacto y cambia su estado a \"💰 Interesado\"</li>";
    echo "<li>Selecciona uno o más planes (CTRL + Click)</li>";
    echo "<li>Confirma para generar la cotización</li>";
    echo "<li>El contacto recibirá un email con el PDF de la cotización (validez: 3 días)</li>";
    echo "</ol>";
    
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Puedes eliminar este archivo después de ejecutarlo una vez.</p>";
    
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: No se pudo crear la tabla {$table_name}</p>";
    echo "<p>Error de MySQL: " . $wpdb->last_error . "</p>";
}

echo "<br><br>";
echo "<p><a href='" . admin_url('admin.php?page=automatiza-tech-contacts') . "'>← Volver a Contactos</a></p>";
?>
