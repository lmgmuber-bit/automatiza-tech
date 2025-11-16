<?php
/**
 * Diagnóstico de Configuración SMTP
 * Verifica que las credenciales estén correctamente configuradas
 */

// Cargar WordPress
require_once('wp-load.php');

// Solo administradores
if (!current_user_can('administrator')) {
    die('⛔ Solo administradores');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico SMTP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        .box {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        .success { background: #e8f5e9; border-color: #4caf50; }
        .error { background: #ffebee; border-color: #f44336; }
        .warning { background: #fff3e0; border-color: #ff9800; }
        .info { background: #e3f2fd; border-color: #2196f3; }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th { background: #667eea; color: white; }
        .check { color: #4caf50; font-size: 24px; }
        .cross { color: #f44336; font-size: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico de Configuración SMTP</h1>
        <p style="color: #666; margin-bottom: 30px;">Verificando credenciales y configuración</p>

        <?php
        echo '<h2 style="color: #667eea; margin-top: 30px;">📋 Verificación de Credenciales</h2>';
        
        echo '<table>';
        echo '<tr><th>Configuración</th><th>Estado</th><th>Valor</th></tr>';
        
        // SMTP_USER
        $smtp_user_defined = defined('SMTP_USER');
        echo '<tr>';
        echo '<td><strong>SMTP_USER</strong></td>';
        echo '<td>' . ($smtp_user_defined ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>';
        echo '<td>' . ($smtp_user_defined ? esc_html(SMTP_USER) : 'NO DEFINIDO') . '</td>';
        echo '</tr>';
        
        // SMTP_PASS
        $smtp_pass_defined = defined('SMTP_PASS');
        echo '<tr>';
        echo '<td><strong>SMTP_PASS</strong></td>';
        echo '<td>' . ($smtp_pass_defined ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>';
        echo '<td>' . ($smtp_pass_defined ? '••••••••' . substr(SMTP_PASS, -3) : 'NO DEFINIDO') . '</td>';
        echo '</tr>';
        
        // SMTP_HOST
        $smtp_host_defined = defined('SMTP_HOST');
        echo '<tr>';
        echo '<td><strong>SMTP_HOST</strong></td>';
        echo '<td>' . ($smtp_host_defined ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>';
        echo '<td>' . ($smtp_host_defined ? esc_html(SMTP_HOST) : 'smtp.hostinger.com (por defecto)') . '</td>';
        echo '</tr>';
        
        // SMTP_PORT
        $smtp_port_defined = defined('SMTP_PORT');
        echo '<tr>';
        echo '<td><strong>SMTP_PORT</strong></td>';
        echo '<td>' . ($smtp_port_defined ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>';
        echo '<td>' . ($smtp_port_defined ? SMTP_PORT : '587 (por defecto)') . '</td>';
        echo '</tr>';
        
        // Email Admin
        $admin_email = get_option('admin_email');
        echo '<tr>';
        echo '<td><strong>Email Admin (WordPress)</strong></td>';
        echo '<td><span class="check">✅</span></td>';
        echo '<td>' . esc_html($admin_email) . '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Análisis
        if ($smtp_user_defined && $smtp_pass_defined) {
            echo '<div class="box success">';
            echo '<h3>✅ Credenciales Configuradas Correctamente</h3>';
            echo '<p>Las credenciales SMTP están definidas en wp-config.php</p>';
            
            // Verificar coincidencia con email admin
            if ($admin_email !== SMTP_USER) {
                echo '<div class="box warning" style="margin-top: 15px;">';
                echo '<h4>⚠️ Advertencia: Email Admin Diferente</h4>';
                echo '<p>El email admin de WordPress (<strong>' . esc_html($admin_email) . '</strong>) es diferente de SMTP_USER (<strong>' . esc_html(SMTP_USER) . '</strong>)</p>';
                echo '<p><strong>Recomendación:</strong> Cambia el email admin en WordPress → Ajustes → Generales a: <strong>' . esc_html(SMTP_USER) . '</strong></p>';
                echo '</div>';
            } else {
                echo '<p style="margin-top: 10px;">✅ El email admin coincide con SMTP_USER</p>';
            }
            
            echo '</div>';
        } else {
            echo '<div class="box error">';
            echo '<h3>❌ Credenciales NO Configuradas</h3>';
            echo '<p>Debes agregar las siguientes líneas en tu archivo <strong>wp-config.php</strong></p>';
            echo '<p>Agrega ANTES de la línea <code>/* That\'s all, stop editing! */</code>:</p>';
            echo '<div class="code">';
            echo '/**<br>';
            echo ' * Configuración SMTP para envío de correos<br>';
            echo ' */<br>';
            echo "define('SMTP_USER', 'info@automatizatech.shop');<br>";
            echo "define('SMTP_PASS', 'tu_contraseña_aqui');<br>";
            echo "define('SMTP_HOST', 'smtp.hostinger.com');<br>";
            echo "define('SMTP_PORT', 587);";
            echo '</div>';
            echo '</div>';
        }
        
        // Verificar archivo smtp-config.php
        echo '<h2 style="color: #667eea; margin-top: 30px;">📁 Archivos del Sistema</h2>';
        
        $smtp_file = get_template_directory() . '/inc/smtp-config.php';
        $smtp_exists = file_exists($smtp_file);
        
        echo '<table>';
        echo '<tr><th>Archivo</th><th>Estado</th><th>Ubicación</th></tr>';
        echo '<tr>';
        echo '<td><strong>smtp-config.php</strong></td>';
        echo '<td>' . ($smtp_exists ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>';
        echo '<td>/wp-content/themes/automatiza-tech/inc/</td>';
        echo '</tr>';
        echo '</table>';
        
        // Test de conexión
        if ($smtp_user_defined && $smtp_pass_defined) {
            echo '<h2 style="color: #667eea; margin-top: 30px;">🧪 Test de Conexión SMTP</h2>';
            echo '<div class="box info">';
            echo '<p>Credenciales que se usarán para el envío:</p>';
            echo '<ul style="margin: 10px 0; padding-left: 20px; line-height: 1.8;">';
            echo '<li><strong>Usuario:</strong> ' . esc_html(SMTP_USER) . '</li>';
            echo '<li><strong>Host:</strong> ' . ($smtp_host_defined ? esc_html(SMTP_HOST) : 'smtp.hostinger.com') . '</li>';
            echo '<li><strong>Puerto:</strong> ' . ($smtp_port_defined ? SMTP_PORT : '587') . ' (TLS)</li>';
            echo '<li><strong>Remitente (From):</strong> ' . esc_html(SMTP_USER) . '</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="box success">';
            echo '<h3>✅ Todo Listo para Probar</h3>';
            echo '<p>La configuración está correcta. Para probar el envío:</p>';
            echo '<ol style="margin: 10px 0; padding-left: 20px; line-height: 1.8;">';
            echo '<li>Sube el archivo <strong>smtp-config.php</strong> actualizado a producción</li>';
            echo '<li>Ve a: <a href="' . admin_url('admin.php?page=automatiza-tech-contacts&test_email=send&_wpnonce=' . wp_create_nonce('test_email')) . '" style="color: #667eea;">Enviar Test de Correo</a></li>';
            echo '<li>Revisa tu bandeja de entrada</li>';
            echo '</ol>';
            echo '</div>';
        }
        
        // Información del servidor
        echo '<h2 style="color: #667eea; margin-top: 30px;">🌐 Información del Servidor</h2>';
        echo '<table>';
        echo '<tr><th>Parámetro</th><th>Valor</th></tr>';
        echo '<tr><td><strong>Servidor</strong></td><td>' . $_SERVER['HTTP_HOST'] . '</td></tr>';
        echo '<tr><td><strong>IP</strong></td><td>' . $_SERVER['SERVER_ADDR'] . '</td></tr>';
        echo '<tr><td><strong>Entorno</strong></td><td>' . (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ? 'Local (WAMP)' : 'Producción') . '</td></tr>';
        echo '</table>';
        
        ?>
        
        <div style="margin-top: 40px; padding: 20px; background: #f8f9ff; border-radius: 10px; text-align: center;">
            <p style="color: #666; font-size: 12px;">
                ⚠️ Elimina este archivo (smtp-diagnostico.php) después de la verificación
            </p>
        </div>
    </div>
</body>
</html>
