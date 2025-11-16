<?php
/**
 * Script para agregar campo tax_id (RUT/DNI) a las tablas de contactos y clientes
 */

require_once('wp-load.php');

global $wpdb;

$contacts_table = $wpdb->prefix . 'automatiza_tech_contacts';
$clients_table = $wpdb->prefix . 'automatiza_tech_clients';

echo "<h1>Agregando campo tax_id a las tablas</h1>";

// Verificar y agregar campo tax_id a tabla de contactos
$column_exists_contacts = $wpdb->get_results("SHOW COLUMNS FROM {$contacts_table} LIKE 'tax_id'");

if (empty($column_exists_contacts)) {
    $result = $wpdb->query("ALTER TABLE {$contacts_table} ADD COLUMN tax_id varchar(50) AFTER phone");
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Campo tax_id agregado a tabla de contactos</p>";
    } else {
        echo "<p style='color: red;'>✗ Error al agregar tax_id a contactos: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Campo tax_id ya existe en tabla de contactos</p>";
}

// Verificar y agregar campo tax_id a tabla de clientes
$column_exists_clients = $wpdb->get_results("SHOW COLUMNS FROM {$clients_table} LIKE 'tax_id'");

if (empty($column_exists_clients)) {
    $result = $wpdb->query("ALTER TABLE {$clients_table} ADD COLUMN tax_id varchar(50) AFTER phone");
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Campo tax_id agregado a tabla de clientes</p>";
    } else {
        echo "<p style='color: red;'>✗ Error al agregar tax_id a clientes: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Campo tax_id ya existe en tabla de clientes</p>";
}

echo "<hr>";
echo "<h2>Estructura de tablas actualizada:</h2>";

echo "<h3>Tabla de Contactos:</h3>";
$contacts_columns = $wpdb->get_results("SHOW COLUMNS FROM {$contacts_table}");
echo "<pre>";
print_r($contacts_columns);
echo "</pre>";

echo "<h3>Tabla de Clientes:</h3>";
$clients_columns = $wpdb->get_results("SHOW COLUMNS FROM {$clients_table}");
echo "<pre>";
print_r($clients_columns);
echo "</pre>";

echo "<hr>";
echo "<p><a href='wp-admin/admin.php?page=automatiza-tech-contacts'>← Volver al Panel de Contactos</a></p>";
?>
