<?php
/**
 * SERVICIOS ADMIN - Página Simple de Administración
 * Archivo: servicios-admin-simple.php
 * Colocar en la raíz de WordPress
 * Acceder vía: http://tu-sitio.com/servicios-admin-simple.php
 */

// Seguridad básica
session_start();
$password = 'automatiza2024'; // Cambiar esta contraseña

if ($_POST['password'] ?? '' === $password) {
    $_SESSION['admin_logged'] = true;
}

if (!($_SESSION['admin_logged'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>🔐 Acceso Admin - Servicios</title>
        <style>
            body { font-family: Arial; max-width: 400px; margin: 100px auto; padding: 20px; }
            input, button { width: 100%; padding: 10px; margin: 10px 0; }
            button { background: #0073aa; color: white; border: none; cursor: pointer; }
        </style>
    </head>
    <body>
        <h2>🔐 Acceso Admin - Servicios</h2>
        <form method="post">
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Acceder</button>
        </form>
        <p><small>Contraseña predeterminada: automatiza2024</small></p>
    </body>
    </html>
    <?php
    exit;
}

// Cargar WordPress
require_once('wp-config.php');

// Conexión directa a la base de datos
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

// Procesar acciones
if ($_POST['action'] ?? '' === 'create_table') {
    $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `category` varchar(50) DEFAULT 'pricing',
        `price_usd` decimal(10,2) DEFAULT 0.00,
        `price_clp` decimal(12,0) DEFAULT 0,
        `description` text,
        `features` text,
        `icon` varchar(100) DEFAULT 'fas fa-star',
        `highlight` tinyint(1) DEFAULT 0,
        `button_text` varchar(100) DEFAULT '',
        `whatsapp_message` text,
        `status` varchar(20) DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    
    $wpdb->query($sql);
    $success = "Tabla creada correctamente";
}

if ($_POST['action'] ?? '' === 'insert_samples') {
    $services = [
        ['Plan Básico', 'pricing', 99.00, 79200, 'Perfecto para pequeños emprendimientos', '["Hasta 1,000 conversaciones/mes","WhatsApp Business API","Respuestas automáticas básicas"]', 'fas fa-seedling', 0, 'Comenzar Ahora', 'Hola! Me interesa el Plan Básico'],
        ['Plan Profesional', 'pricing', 199.00, 159200, 'Para empresas en crecimiento', '["Todo del Plan Básico","Hasta 5,000 conversaciones/mes","IA avanzada"]', 'fas fa-rocket', 1, 'Más Popular', 'Hola! Me interesa el Plan Profesional'],
        ['Plan Enterprise', 'pricing', 399.00, 319200, 'Solución completa para grandes empresas', '["Todo del Plan Profesional","Conversaciones ilimitadas","Soporte 24/7"]', 'fas fa-crown', 0, 'Contactar', 'Hola! Me interesa el Plan Enterprise'],
        ['Atención 24/7', 'features', 0.00, 0, 'Chatbots inteligentes que nunca descansan', '["Respuesta inmediata","Sin horarios limitados"]', 'fas fa-robot', 0, '', ''],
        ['Aumenta tus Ventas', 'features', 0.00, 0, 'Convierte más leads en clientes', '["Lead scoring automático","Seguimiento personalizado"]', 'fas fa-chart-line', 0, '', ''],
        ['Web + WhatsApp Business', 'special', 299.00, 239200, 'Sitio web + automatización WhatsApp', '["Sitio web completo","WhatsApp Business API","Capacitación incluida"]', 'fas fa-store', 1, '¡Quiero mi Web + WhatsApp!', 'Hola! Me interesa el paquete Web + WhatsApp Business']
    ];
    
    $inserted = 0;
    foreach ($services as $service) {
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $service[0],
                'category' => $service[1],
                'price_usd' => $service[2],
                'price_clp' => $service[3],
                'description' => $service[4],
                'features' => $service[5],
                'icon' => $service[6],
                'highlight' => $service[7],
                'button_text' => $service[8],
                'whatsapp_message' => $service[9],
                'status' => 'active'
            ]
        );
        if ($result) $inserted++;
    }
    $success = "Servicios insertados: $inserted";
}

// Verificar tabla
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
$services = $table_exists ? $wpdb->get_results("SELECT * FROM $table_name ORDER BY id") : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🚀 Admin Servicios - Automatiza Tech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f1f1f1; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #005a87; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .status-active { color: #28a745; font-weight: bold; }
        .highlight { background: #fff3cd; }
        .logout { float: right; background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Admin Servicios - Automatiza Tech</h1>
        
        <a href="?logout=1" class="btn logout">Cerrar Sesión</a>
        
        <?php if ($_GET['logout'] ?? false): session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit; endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="info">
            <h3>📊 Estado del Sistema</h3>
            <ul>
                <li><strong>Base de datos:</strong> <?php echo DB_NAME; ?></li>
                <li><strong>Tabla:</strong> <?php echo $table_name; ?> <?php echo $table_exists ? '✅' : '❌'; ?></li>
                <li><strong>Servicios:</strong> <?php echo count($services); ?></li>
                <li><strong>WordPress:</strong> <?php echo get_option('blogname', 'WordPress'); ?></li>
            </ul>
        </div>
        
        <?php if (!$table_exists): ?>
            <div class="error">
                <h3>⚠️ Tabla no existe</h3>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="create_table">
                    <button type="submit" class="btn btn-success">Crear Tabla</button>
                </form>
            </div>
        <?php elseif (empty($services)): ?>
            <div class="info">
                <h3>📝 Sin servicios</h3>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="insert_samples">
                    <button type="submit" class="btn btn-success">Insertar Servicios de Ejemplo</button>
                </form>
            </div>
        <?php else: ?>
            <h2>📋 Servicios Configurados (<?php echo count($services); ?>)</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Precio USD</th>
                        <th>Precio CLP</th>
                        <th>Estado</th>
                        <th>Destacado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr class="<?php echo $service->highlight ? 'highlight' : ''; ?>">
                            <td><?php echo $service->id; ?></td>
                            <td>
                                <strong><?php echo esc_html($service->name); ?></strong><br>
                                <small><?php echo esc_html(substr($service->description, 0, 50)) . '...'; ?></small>
                            </td>
                            <td><?php echo $service->category; ?></td>
                            <td>$<?php echo number_format($service->price_usd, 2); ?></td>
                            <td>$<?php echo number_format($service->price_clp, 0); ?></td>
                            <td class="status-<?php echo $service->status; ?>"><?php echo $service->status; ?></td>
                            <td><?php echo $service->highlight ? '⭐ SÍ' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="info">
                <h3>🎨 Shortcodes Disponibles</h3>
                <p>Para mostrar los servicios en el frontend, usa estos shortcodes:</p>
                <ul>
                    <li><code>[pricing_services columns="3"]</code> - Servicios de precios</li>
                    <li><code>[features_services columns="3"]</code> - Características/beneficios</li>
                    <li><code>[special_services]</code> - Servicios especiales</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <h3>🔧 Acciones del Sistema</h3>
            <p><strong>URL de acceso:</strong> <code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
            <p><strong>Última actualización:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>