<?php
/**
 * Migración idempotente: agrega la columna `invitees_names` a wp_automatiza_leads.
 * La función automatiza_tech_save_lead inserta esta columna; faltaba en local.
 * Correr una vez por entorno (CLI o web). Seguro: solo agrega si no existe.
 */
require_once __DIR__ . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

// SHOW COLUMNS está scopeado a la base de datos actual (evita falsos positivos
// cuando otra DB del servidor tiene una tabla con el mismo nombre).
$exists = $wpdb->get_var("SHOW COLUMNS FROM `$table_name` LIKE 'invitees_names'");

if (empty($exists)) {
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitees_names text DEFAULT NULL AFTER invitees_emails");
    echo "Column 'invitees_names' added successfully to $table_name.\n";
} else {
    echo "Column 'invitees_names' already exists in $table_name.\n";
}
