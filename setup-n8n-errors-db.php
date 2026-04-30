<?php
/**
 * Setup de Base de Datos para Errores N8N - ARGOS
 * 
 * Ejecutar una vez para crear la tabla de errores
 * URL: /setup-n8n-errors-db.php
 * 
 * @package AutomatizaTech
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Verificar permisos de administrador
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar este script.');
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$table_name = $wpdb->prefix . 'automatiza_n8n_errors';

// Crear tabla de errores N8N
$sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    workflow_name varchar(255) NOT NULL,
    workflow_id varchar(100) DEFAULT NULL,
    execution_id varchar(100) DEFAULT NULL,
    error_message text NOT NULL,
    error_node varchar(255) DEFAULT NULL,
    error_stack longtext DEFAULT NULL,
    error_timestamp datetime NOT NULL,
    execution_mode varchar(50) DEFAULT 'production',
    argos_analysis longtext DEFAULT NULL,
    severity enum('critical','high','medium','low') DEFAULT 'medium',
    status enum('new','reviewing','resolved','ignored') DEFAULT 'new',
    resolved_at datetime DEFAULT NULL,
    resolved_by bigint(20) unsigned DEFAULT NULL,
    resolution_notes text DEFAULT NULL,
    notification_whatsapp tinyint(1) DEFAULT 0,
    notification_email tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_name (workflow_name),
    KEY idx_status (status),
    KEY idx_severity (severity),
    KEY idx_error_timestamp (error_timestamp),
    KEY idx_created_at (created_at)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$result = dbDelta($sql);

// Verificar si la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup N8N Errors DB - ARGOS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f2f5;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e40af;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
        }
        .status {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status.success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        .status.error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-box h3 {
            margin-top: 0;
            color: #374151;
        }
        code {
            background: #1f2937;
            color: #e5e7eb;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 13px;
        }
        .table-structure {
            font-family: monospace;
            font-size: 12px;
            background: #1f2937;
            color: #e5e7eb;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            white-space: pre;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #1e40af;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #1e3a8a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ ARGOS - Setup Base de Datos</h1>
        <p class="subtitle">Sistema de Monitoreo de Errores N8N</p>
        
        <?php if ($table_exists): ?>
            <div class="status success">
                <strong>✅ Tabla creada correctamente</strong><br>
                La tabla <code><?php echo $table_name; ?></code> está lista para usar.
            </div>
        <?php else: ?>
            <div class="status error">
                <strong>❌ Error al crear la tabla</strong><br>
                Hubo un problema al crear la tabla. Revisa los permisos de la base de datos.
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 Estructura de la Tabla</h3>
            <div class="table-structure">
<?php
// Mostrar estructura de la tabla
$columns = $wpdb->get_results("DESCRIBE $table_name");
if ($columns) {
    echo "Tabla: $table_name\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($columns as $col) {
        echo sprintf("%-25s %-20s %s\n", 
            $col->Field, 
            $col->Type, 
            $col->Null === 'NO' ? 'NOT NULL' : 'NULL'
        );
    }
}
?>
            </div>
        </div>
        
        <div class="info-box">
            <h3>🔧 Próximos Pasos</h3>
            <ol>
                <li>Ve al panel de administración: <a href="/wp-admin/admin.php?page=automatiza-n8n-errors">Errores N8N</a></li>
                <li>Configura el workflow en N8N con las credenciales correctas</li>
                <li>Activa el workflow "Argos detección de Errores N8N"</li>
                <li>Configura otros workflows para usar ARGOS como Error Workflow</li>
            </ol>
        </div>
        
        <div class="info-box">
            <h3>🔑 API Endpoint</h3>
            <p>URL para guardar errores desde N8N:</p>
            <code>POST <?php echo get_site_url(); ?>/wp-json/automatiza/v1/n8n-errors</code>
            <p style="margin-top: 15px;">Headers requeridos:</p>
            <code>X-API-Key: [tu-api-key]</code>
        </div>
        
        <a href="<?php echo admin_url('admin.php?page=automatiza-n8n-errors'); ?>" class="btn">
            Ver Panel de Errores →
        </a>
    </div>
</body>
</html>
<?php
// Opcional: Eliminar este archivo después de ejecutar
// unlink(__FILE__);
