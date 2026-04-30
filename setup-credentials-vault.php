<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para crear tablas de la Bóveda de Credenciales
 * Ejecutar una sola vez: https://automatizatech.cl/setup-credentials-vault.php
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
$table_name = $wpdb->prefix . 'automatiza_credentials_vault';
$logs_table = $wpdb->prefix . 'automatiza_credentials_logs';

echo "<h1>🔐 Configuración de Bóveda de Credenciales - Automatiza Tech</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f8fafc; }
    h1 { color: #1e3a8a; }
    h2 { color: #d63384; margin-top: 30px; }
    .success { color: #059669; background: #d1fae5; padding: 10px; border-radius: 8px; margin: 10px 0; }
    .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; margin: 10px 0; }
    .info { color: #0284c7; background: #e0f2fe; padding: 10px; border-radius: 8px; margin: 10px 0; }
    .warning { color: #d97706; background: #fef3c7; padding: 10px; border-radius: 8px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
    th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
    th { background: linear-gradient(135deg, #1e3a8a, #d63384); color: white; }
    .category { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    a.button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #1e3a8a, #d63384); color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
</style>";

$charset_collate = $wpdb->get_charset_collate();

// Crear tabla de credenciales
$sql_credentials = "CREATE TABLE IF NOT EXISTS {$table_name} (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    category varchar(50) NOT NULL DEFAULT 'other',
    service_name varchar(255) NOT NULL,
    description text,
    url varchar(500),
    username varchar(255),
    password_encrypted text,
    api_key_encrypted text,
    api_secret_encrypted text,
    extra_data_encrypted text,
    notes text,
    environment varchar(20) DEFAULT 'production',
    is_active tinyint(1) DEFAULT 1,
    created_by bigint(20) UNSIGNED,
    updated_by bigint(20) UNSIGNED,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY category (category),
    KEY is_active (is_active),
    KEY environment (environment)
) $charset_collate;";

// Crear tabla de logs
$sql_logs = "CREATE TABLE IF NOT EXISTS {$logs_table} (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    credential_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    action varchar(50) NOT NULL,
    ip_address varchar(45),
    user_agent text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY credential_id (credential_id),
    KEY user_id (user_id),
    KEY created_at (created_at)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

echo "<h2>📊 Creando Tablas</h2>";

// Tabla de credenciales
$result1 = dbDelta($sql_credentials);
$table_exists1 = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
if ($table_exists1) {
    echo "<div class='success'>✅ Tabla <strong>$table_name</strong> creada/verificada correctamente</div>";
} else {
    echo "<div class='error'>❌ Error creando tabla $table_name: " . $wpdb->last_error . "</div>";
}

// Tabla de logs
$result2 = dbDelta($sql_logs);
$table_exists2 = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;
if ($table_exists2) {
    echo "<div class='success'>✅ Tabla <strong>$logs_table</strong> creada/verificada correctamente</div>";
} else {
    echo "<div class='error'>❌ Error creando tabla $logs_table: " . $wpdb->last_error . "</div>";
}

// Mostrar estructura de la tabla
echo "<h2>📋 Estructura de la Tabla de Credenciales</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
if ($columns) {
    echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col->Field}</td><td>{$col->Type}</td><td>{$col->Null}</td><td>{$col->Default}</td></tr>";
    }
    echo "</table>";
}

// Categorías disponibles
echo "<h2>🏷️ Categorías Disponibles</h2>";
$categories = [
    'server' => ['icon' => '🖥️', 'label' => 'Servidores', 'color' => '#1e3a8a'],
    'domain' => ['icon' => '🌐', 'label' => 'Dominios', 'color' => '#059669'],
    'hosting' => ['icon' => '☁️', 'label' => 'Hosting', 'color' => '#7c3aed'],
    'ftp' => ['icon' => '📁', 'label' => 'FTP/SFTP', 'color' => '#d97706'],
    'database' => ['icon' => '🗄️', 'label' => 'Bases de Datos', 'color' => '#dc2626'],
    'email' => ['icon' => '📧', 'label' => 'Cuentas de Correo', 'color' => '#0891b2'],
    'n8n' => ['icon' => '🔄', 'label' => 'N8N / Automatizaciones', 'color' => '#ea580c'],
    'api' => ['icon' => '🔌', 'label' => 'APIs', 'color' => '#4f46e5'],
    'social' => ['icon' => '📱', 'label' => 'Redes Sociales', 'color' => '#db2777'],
    'payment' => ['icon' => '💳', 'label' => 'Pasarelas de Pago', 'color' => '#16a34a'],
    'analytics' => ['icon' => '📊', 'label' => 'Analytics / Tracking', 'color' => '#f59e0b'],
    'ai' => ['icon' => '🤖', 'label' => 'IA / OpenAI / Claude', 'color' => '#8b5cf6'],
    'whatsapp' => ['icon' => '💬', 'label' => 'WhatsApp / Mensajería', 'color' => '#22c55e'],
    'google' => ['icon' => '🔵', 'label' => 'Google Services', 'color' => '#4285f4'],
    'wordpress' => ['icon' => '📝', 'label' => 'WordPress', 'color' => '#21759b'],
    'other' => ['icon' => '🔐', 'label' => 'Otros', 'color' => '#6b7280']
];

echo "<table><tr><th>Categoría</th><th>Icono</th><th>Etiqueta</th><th>Color</th></tr>";
foreach ($categories as $key => $cat) {
    echo "<tr><td><code>$key</code></td><td style='font-size: 20px;'>{$cat['icon']}</td><td>{$cat['label']}</td><td><span style='display:inline-block; width:20px; height:20px; background:{$cat['color']}; border-radius:4px;'></span> {$cat['color']}</td></tr>";
}
echo "</table>";

// Información de seguridad
echo "<h2>🔒 Características de Seguridad</h2>";
echo "<div class='info'>
<ul>
    <li>✅ <strong>Encriptación AES-256-CBC</strong> para todas las contraseñas y claves API</li>
    <li>✅ <strong>Clave derivada de AUTH_KEY</strong> de WordPress (única por instalación)</li>
    <li>✅ <strong>Verificación de contraseña</strong> requerida para acceder a la bóveda</li>
    <li>✅ <strong>Token temporal de 5 minutos</strong> para operaciones sensibles</li>
    <li>✅ <strong>Registro completo de accesos</strong> con IP, usuario y timestamp</li>
    <li>✅ <strong>Solo admin principal</strong> puede acceder a la bóveda</li>
</ul>
</div>";

echo "<div class='warning'>
<strong>⚠️ IMPORTANTE:</strong><br>
- Las contraseñas se encriptan automáticamente al guardar<br>
- Para revelar una contraseña, deberás confirmar con tu password de WordPress<br>
- Todos los accesos quedan registrados en los logs<br>
- El token de sesión expira cada 5 minutos por seguridad
</div>";

echo "<h2>✅ Instalación Completada</h2>";
echo "<p>Ahora puedes acceder a la bóveda desde el menú de administración.</p>";
echo "<a class='button' href='/automatiza-tech/wp-admin/admin.php?page=automatiza-vault'>🔐 Ir a la Bóveda de Credenciales</a>";
