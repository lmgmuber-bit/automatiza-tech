<?php
/**
 * Script para crear tabla de facturas
 */

require_once('wp-load.php');

global $wpdb;

$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}automatiza_tech_invoices` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_email` varchar(255) NOT NULL,
  `plan_id` mediumint(9) DEFAULT NULL,
  `plan_name` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `iva` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `invoice_html` longtext NOT NULL,
  `invoice_file_path` varchar(500) DEFAULT NULL,
  `qr_code_data` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `validated_at` datetime DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `client_id` (`client_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$result = $wpdb->query($sql);

if ($result === false) {
    echo "❌ Error al crear la tabla: " . $wpdb->last_error . "\n";
} else {
    echo "✅ Tabla {$wpdb->prefix}automatiza_tech_invoices creada exitosamente\n";
    
    // Verificar
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}automatiza_tech_invoices'");
    if ($table_exists) {
        echo "✅ Tabla verificada correctamente\n";
    }
}
