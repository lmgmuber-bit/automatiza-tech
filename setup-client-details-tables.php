<?php
/**
 * Script para crear las tablas de seguimiento de clientes
 * Ejecutar una sola vez: /setup-client-details-tables.php
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Acceso denegado. Debes ser administrador.');
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

echo "<h1>🔧 Configuración de Tablas de Seguimiento de Clientes</h1>";

// Tabla de detalles para PROSPECTOS (seguimiento comercial)
$table_propuestas_details = $wpdb->prefix . 'automatiza_propuestas_details';
$sql_propuestas = "CREATE TABLE IF NOT EXISTS {$table_propuestas_details} (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    propuesta_id bigint(20) NOT NULL,
    detail_type varchar(50) NOT NULL,
    title varchar(255) NOT NULL,
    description text,
    status varchar(50) DEFAULT 'pending',
    amount decimal(12,2) DEFAULT 0,
    currency varchar(3) DEFAULT 'CLP',
    scheduled_date date DEFAULT NULL,
    completed_date date DEFAULT NULL,
    related_id bigint(20) DEFAULT NULL,
    related_type varchar(50) DEFAULT NULL,
    attachment_url varchar(500) DEFAULT NULL,
    attachment_name varchar(255) DEFAULT NULL,
    attachment_type varchar(50) DEFAULT NULL,
    metadata longtext,
    created_by bigint(20) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY propuesta_id (propuesta_id),
    KEY detail_type (detail_type),
    KEY status (status)
) $charset_collate;";

// Tabla de detalles para CLIENTES FINALES (seguimiento de proyecto)
$table_clients_details = $wpdb->prefix . 'automatiza_clients_details';
$sql_clients = "CREATE TABLE IF NOT EXISTS {$table_clients_details} (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    client_id bigint(20) NOT NULL,
    propuesta_origin_id bigint(20) DEFAULT NULL,
    detail_type varchar(50) NOT NULL,
    title varchar(255) NOT NULL,
    description text,
    status varchar(50) DEFAULT 'pending',
    amount decimal(12,2) DEFAULT 0,
    currency varchar(3) DEFAULT 'CLP',
    scheduled_date date DEFAULT NULL,
    completed_date date DEFAULT NULL,
    project_start_date date DEFAULT NULL,
    related_id bigint(20) DEFAULT NULL,
    related_type varchar(50) DEFAULT NULL,
    attachment_url varchar(500) DEFAULT NULL,
    attachment_name varchar(255) DEFAULT NULL,
    attachment_type varchar(50) DEFAULT NULL,
    migrated_from_propuesta tinyint(1) DEFAULT 0,
    original_detail_id bigint(20) DEFAULT NULL,
    metadata longtext,
    created_by bigint(20) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY client_id (client_id),
    KEY propuesta_origin_id (propuesta_origin_id),
    KEY detail_type (detail_type),
    KEY status (status)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// Crear tabla de propuestas details
$result1 = dbDelta($sql_propuestas);
echo "<h2>📄 Tabla: {$table_propuestas_details}</h2>";
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_propuestas_details}'") === $table_propuestas_details) {
    echo "<p style='color: green;'>✅ Tabla creada exitosamente o ya existía.</p>";
    $cols = $wpdb->get_results("DESCRIBE {$table_propuestas_details}");
    echo "<p>Columnas: " . count($cols) . "</p>";
} else {
    echo "<p style='color: red;'>❌ Error al crear la tabla.</p>";
    if ($wpdb->last_error) {
        echo "<p>Error: " . $wpdb->last_error . "</p>";
    }
}

// Crear tabla de clientes details
$result2 = dbDelta($sql_clients);
echo "<h2>👤 Tabla: {$table_clients_details}</h2>";
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_clients_details}'") === $table_clients_details) {
    echo "<p style='color: green;'>✅ Tabla creada exitosamente o ya existía.</p>";
    $cols = $wpdb->get_results("DESCRIBE {$table_clients_details}");
    echo "<p>Columnas: " . count($cols) . "</p>";
} else {
    echo "<p style='color: red;'>❌ Error al crear la tabla.</p>";
    if ($wpdb->last_error) {
        echo "<p>Error: " . $wpdb->last_error . "</p>";
    }
}

// Mostrar estructura de tablas
echo "<h2>📊 Estructura de Tablas</h2>";

echo "<h3>wp_automatiza_propuestas_details (Seguimiento Comercial)</h3>";
echo "<p><strong>Uso:</strong> Registrar seguimiento de prospectos ANTES de contratar:</p>";
echo "<ul>";
echo "<li>📄 Propuestas enviadas</li>";
echo "<li>💰 Cotizaciones</li>";
echo "<li>🤝 Reuniones de demo/ventas</li>";
echo "<li>📞 Llamadas</li>";
echo "<li>📧 Emails</li>";
echo "<li>📝 Notas de seguimiento</li>";
echo "</ul>";

echo "<h3>wp_automatiza_clients_details (Seguimiento de Proyecto)</h3>";
echo "<p><strong>Uso:</strong> Registrar seguimiento DESPUÉS de contratar:</p>";
echo "<ul>";
echo "<li>🚀 Items del proyecto</li>";
echo "<li>📦 Entregables</li>";
echo "<li>🧾 Boletas emitidas</li>";
echo "<li>📋 Facturas emitidas</li>";
echo "<li>💳 Pagos recibidos</li>";
echo "<li>🤝 Reuniones de avance</li>";
echo "<li>🔧 Soporte técnico</li>";
echo "</ul>";

echo "<h2>🔄 Proceso de Migración</h2>";
echo "<p>Cuando un prospecto (propuesta) se convierte en cliente contratado:</p>";
echo "<ol>";
echo "<li>Todos los detalles de seguimiento de la propuesta se copian a la tabla de clientes</li>";
echo "<li>Se marca como 'migrated_from_propuesta = 1' para mantener trazabilidad</li>";
echo "<li>Se guarda el 'original_detail_id' para referencia</li>";
echo "<li>La fecha de inicio del proyecto se registra automáticamente</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>✅ Configuración completa.</strong></p>";
echo "<p><a href='/wp-admin/admin.php?page=automatiza-proposals'>← Volver a Propuestas</a> | ";
echo "<a href='/wp-admin/admin.php?page=automatiza-tech-clients'>Ir a Clientes →</a></p>";
?>
