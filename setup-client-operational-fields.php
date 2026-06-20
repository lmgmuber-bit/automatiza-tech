<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar campos operativos a la tabla de clientes
 * Ejecutar una sola vez: https://automatizatech.cl/setup-client-operational-fields.php
 */

// Evitar cualquier redirección
define('WP_USE_THEMES', false);

// Cargar WordPress
require_once(dirname(__FILE__) . '/wp-load.php');

// Forzar mostrar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers para evitar cache
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Verificar que el usuario sea admin
if (!is_user_logged_in()) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Login Requerido</title></head><body>';
    echo '<h1>⚠️ Debes iniciar sesión primero</h1>';
    echo '<p>Por favor <a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">inicia sesión</a> como administrador.</p>';
    echo '</body></html>';
    exit;
}

if (!current_user_can('manage_options')) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acceso Denegado</title></head><body>';
    echo '<h1>⛔ Acceso denegado</h1><p>Debes ser administrador para ejecutar este script.</p>';
    echo '</body></html>';
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_tech_clients';

echo "<h1>🔧 Actualización de Tabla de Clientes - Campos Operativos</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #d63384; color: white; }
</style>";

// Verificar que la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    echo "<p class='error'>❌ La tabla $table_name no existe. Primero debe crear la tabla de clientes.</p>";
    exit;
}

// Campos operativos a agregar
$new_columns = array(
    // Información de la aplicación/proyecto
    'app_url' => "VARCHAR(500) DEFAULT NULL COMMENT 'URL principal de la aplicación'",
    'app_admin_url' => "VARCHAR(500) DEFAULT NULL COMMENT 'URL del panel admin'",
    'app_staging_url' => "VARCHAR(500) DEFAULT NULL COMMENT 'URL del ambiente staging'",
    
    // Dominio y hosting
    'domain' => "VARCHAR(255) DEFAULT NULL COMMENT 'Dominio principal'",
    'domain_registrar' => "VARCHAR(100) DEFAULT NULL COMMENT 'Registrador del dominio'",
    'domain_expiry' => "DATE DEFAULT NULL COMMENT 'Fecha expiración dominio'",
    'hosting_provider' => "VARCHAR(100) DEFAULT NULL COMMENT 'Proveedor de hosting'",
    'hosting_plan' => "VARCHAR(100) DEFAULT NULL COMMENT 'Plan de hosting'",
    'server_ip' => "VARCHAR(45) DEFAULT NULL COMMENT 'IP del servidor'",
    
    // Redes sociales
    'social_facebook' => "VARCHAR(255) DEFAULT NULL COMMENT 'URL Facebook'",
    'social_instagram' => "VARCHAR(255) DEFAULT NULL COMMENT 'URL Instagram'",
    'social_linkedin' => "VARCHAR(255) DEFAULT NULL COMMENT 'URL LinkedIn'",
    'social_twitter' => "VARCHAR(255) DEFAULT NULL COMMENT 'URL Twitter/X'",
    'social_tiktok' => "VARCHAR(255) DEFAULT NULL COMMENT 'URL TikTok'",
    'social_youtube' => "VARCHAR(255) DEFAULT NULL COMMENT 'URL YouTube'",
    'social_other' => "TEXT DEFAULT NULL COMMENT 'Otras redes sociales (JSON)'",
    
    // Cuentas de correo
    'email_accounts' => "TEXT DEFAULT NULL COMMENT 'Cuentas de correo configuradas (JSON)'",
    'email_provider' => "VARCHAR(100) DEFAULT NULL COMMENT 'Proveedor de correo'",
    
    // Accesos y credenciales (encriptados o referencias)
    'api_credentials' => "TEXT DEFAULT NULL COMMENT 'Credenciales API (JSON encriptado)'",
    'cms_access' => "TEXT DEFAULT NULL COMMENT 'Accesos CMS (JSON encriptado)'",
    'ftp_access' => "TEXT DEFAULT NULL COMMENT 'Accesos FTP (JSON encriptado)'",
    'db_access' => "TEXT DEFAULT NULL COMMENT 'Accesos BD (JSON encriptado)'",
    
    // Información adicional del negocio
    'business_description' => "TEXT DEFAULT NULL COMMENT 'Descripción del negocio'",
    'business_industry' => "VARCHAR(100) DEFAULT NULL COMMENT 'Industria/Rubro'",
    'business_size' => "VARCHAR(50) DEFAULT NULL COMMENT 'Tamaño empresa'",
    'billing_address' => "TEXT DEFAULT NULL COMMENT 'Dirección de facturación'",
    'billing_contact' => "VARCHAR(255) DEFAULT NULL COMMENT 'Contacto de facturación'",
    
    // Contactos adicionales
    'secondary_contacts' => "TEXT DEFAULT NULL COMMENT 'Contactos secundarios (JSON)'",
    'technical_contact' => "VARCHAR(255) DEFAULT NULL COMMENT 'Contacto técnico'",
    'technical_email' => "VARCHAR(100) DEFAULT NULL COMMENT 'Email técnico'",
    'technical_phone' => "VARCHAR(20) DEFAULT NULL COMMENT 'Teléfono técnico'",
    
    // Integraciones y servicios
    'integrations' => "TEXT DEFAULT NULL COMMENT 'Integraciones activas (JSON)'",
    'google_analytics_id' => "VARCHAR(50) DEFAULT NULL COMMENT 'ID Google Analytics'",
    'google_tag_manager' => "VARCHAR(50) DEFAULT NULL COMMENT 'ID GTM'",
    'facebook_pixel' => "VARCHAR(50) DEFAULT NULL COMMENT 'ID Facebook Pixel'",
    'whatsapp_business' => "VARCHAR(20) DEFAULT NULL COMMENT 'WhatsApp Business'",
    
    // Metadata operativa
    'operational_notes' => "TEXT DEFAULT NULL COMMENT 'Notas operativas internas'",
    'sla_level' => "VARCHAR(50) DEFAULT NULL COMMENT 'Nivel de SLA'",
    'support_hours' => "VARCHAR(100) DEFAULT NULL COMMENT 'Horario de soporte'",
    'monthly_fee' => "DECIMAL(12,2) DEFAULT NULL COMMENT 'Cuota mensual'",
    'payment_day' => "TINYINT DEFAULT NULL COMMENT 'Día de pago mensual'",
    'contract_end_date' => "DATE DEFAULT NULL COMMENT 'Fecha fin de contrato'"
);

echo "<h2>📋 Columnas a agregar:</h2>";
echo "<table><tr><th>Columna</th><th>Definición</th><th>Estado</th></tr>";

$added = 0;
$existed = 0;
$errors = 0;

foreach ($new_columns as $column => $definition) {
    // Verificar si la columna ya existe
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
    
    if (!empty($column_exists)) {
        echo "<tr><td>$column</td><td>$definition</td><td class='info'>⏭️ Ya existe</td></tr>";
        $existed++;
    } else {
        // Agregar columna
        $sql = "ALTER TABLE $table_name ADD COLUMN $column $definition";
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo "<tr><td>$column</td><td>$definition</td><td class='success'>✅ Agregada</td></tr>";
            $added++;
        } else {
            echo "<tr><td>$column</td><td>$definition</td><td class='error'>❌ Error: " . $wpdb->last_error . "</td></tr>";
            $errors++;
        }
    }
}

echo "</table>";

echo "<h2>📊 Resumen:</h2>";
echo "<ul>";
echo "<li class='success'>✅ Columnas agregadas: $added</li>";
echo "<li class='info'>⏭️ Ya existían: $existed</li>";
echo "<li class='error'>❌ Errores: $errors</li>";
echo "</ul>";

// Mostrar estructura final
echo "<h2>📋 Estructura final de la tabla:</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td>{$col->Field}</td><td>{$col->Type}</td><td>{$col->Null}</td><td>{$col->Default}</td></tr>";
}
echo "</table>";

echo "<h2>✅ Actualización completada</h2>";
echo "<p>Ahora puede editar los datos operativos de cada cliente desde el panel de administración.</p>";
echo "<p><a href='/automatiza-tech/wp-admin/admin.php?page=automatiza-panel'>← Volver al Panel</a></p>";
