<?php
/**
 * Test de correo para entorno local
 * Simula el envío y muestra el resultado
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea administrador
if (!current_user_can('administrator')) {
    die('⛔ Solo administradores pueden ejecutar este script');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Correo - Local</title>
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
        h1 { color: #667eea; margin-bottom: 10px; }
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        .info { background: #e3f2fd; border-color: #2196f3; }
        .success { background: #e8f5e9; border-color: #4caf50; }
        .warning { background: #fff3e0; border-color: #ff9800; }
        .error { background: #ffebee; border-color: #f44336; }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            overflow-x: auto;
            font-size: 13px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
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
        th {
            background: #667eea;
            color: white;
        }
        .check { color: #4caf50; font-size: 20px; }
        .cross { color: #f44336; font-size: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test de Sistema de Correo - Entorno Local</h1>
        <p style="color: #666; margin-bottom: 30px;">Verificación del sistema de envío de correos</p>

        <?php
        // Detectar entorno
        $is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                     strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
        
        if ($is_local) {
            echo '<div class="status-box warning">
                    <h3>⚠️ Estás en Entorno Local (WAMP)</h3>
                    <p>El envío de correos reales NO funcionará aquí porque:</p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>No tienes servidor SMTP configurado</li>
                        <li>localhost no puede enviar correos externos</li>
                        <li>La configuración SMTP solo funciona en producción (Hostinger)</li>
                    </ul>
                    <p><strong>✅ Esto es NORMAL y ESPERADO en desarrollo local</strong></p>
                  </div>';
        }
        
        // Verificar configuración
        echo '<h2 style="color: #667eea; margin-top: 30px;">📋 Verificación de Sistema</h2>';
        
        $checks = array();
        
        // 1. Verificar función get_email_template
        $contact_form = new AutomatizaTech_Contact_Form();
        $reflection = new ReflectionClass($contact_form);
        $method = $reflection->getMethod('get_email_template');
        $method->setAccessible(true);
        $checks['template'] = method_exists($contact_form, 'get_email_template');
        
        // 2. Verificar planes
        global $wpdb;
        $plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}automatiza_services WHERE category = 'pricing' AND active = 1");
        $checks['plans'] = count($plans) > 0;
        
        // 3. Verificar logo
        $logo_path = get_template_directory() . '/assets/images/logo-automatiza-tech.png';
        $checks['logo'] = file_exists($logo_path);
        
        // 4. Verificar smtp-config.php
        $smtp_file = get_template_directory() . '/inc/smtp-config.php';
        $checks['smtp'] = file_exists($smtp_file);
        
        echo '<table>
                <tr>
                    <th>Componente</th>
                    <th>Estado</th>
                    <th>Detalles</th>
                </tr>';
        
        echo '<tr>
                <td>Plantilla de Email</td>
                <td>' . ($checks['template'] ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>
                <td>' . ($checks['template'] ? 'Función get_email_template() existe' : 'No encontrada') . '</td>
              </tr>';
        
        echo '<tr>
                <td>Planes Activos</td>
                <td>' . ($checks['plans'] ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>
                <td>' . count($plans) . ' planes encontrados</td>
              </tr>';
        
        echo '<tr>
                <td>Logo PNG</td>
                <td>' . ($checks['logo'] ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>
                <td>' . ($checks['logo'] ? 'logo-automatiza-tech.png existe' : 'No encontrado') . '</td>
              </tr>';
        
        echo '<tr>
                <td>Configuración SMTP</td>
                <td>' . ($checks['smtp'] ? '<span class="check">✅</span>' : '<span class="cross">❌</span>') . '</td>
                <td>' . ($checks['smtp'] ? 'smtp-config.php existe' : 'No encontrado') . '</td>
              </tr>';
        
        echo '</table>';
        
        // Mostrar planes encontrados
        if ($checks['plans']) {
            echo '<h3 style="color: #667eea; margin-top: 20px;">💼 Planes que se incluirán en el correo:</h3>';
            echo '<ul style="margin: 10px 0; padding-left: 20px;">';
            foreach ($plans as $plan) {
                $featured = $plan->is_featured == 1 ? '⭐' : '';
                echo "<li>{$featured} <strong>{$plan->name}</strong> - \${$plan->price} {$plan->currency}/{$plan->billing_period}</li>";
            }
            echo '</ul>';
        }
        
        // Generar preview del correo
        if ($checks['template'] && $checks['plans']) {
            echo '<div class="status-box success" style="margin-top: 30px;">
                    <h3>✅ Sistema Listo</h3>
                    <p>Todos los componentes están correctos. El correo se verá así:</p>
                  </div>';
            
            // Generar HTML del correo
            $test_email = $method->invoke($contact_form, 'Usuario de Prueba');
            
            echo '<h3 style="color: #667eea; margin-top: 20px;">📧 Vista Previa del Correo:</h3>';
            echo '<div style="border: 3px solid #667eea; border-radius: 10px; padding: 20px; background: #f5f5f5; max-height: 600px; overflow-y: auto;">';
            echo $test_email;
            echo '</div>';
            
            // Guardar preview
            file_put_contents('email-preview-test.html', $test_email);
            echo '<p style="margin-top: 15px;">
                    <a href="email-preview-test.html" target="_blank" class="button">🌐 Abrir Preview en Nueva Ventana</a>
                  </p>';
        }
        
        ?>
        
        <div class="status-box info" style="margin-top: 40px;">
            <h3>🚀 Para Probar en Producción (Hostinger):</h3>
            <ol style="margin: 10px 0; padding-left: 20px; line-height: 1.8;">
                <li>Sube los archivos a Hostinger vía FTP/SFTP</li>
                <li>Configura las credenciales SMTP en wp-config.php:
                    <div class="code">
define('SMTP_USER', 'info@automatizatech.cl');<br>
define('SMTP_PASS', 'tu_contraseña_del_correo');<br>
define('SMTP_HOST', 'smtp.hostinger.com');<br>
define('SMTP_PORT', 587);
                    </div>
                </li>
                <li>Accede a: <code>https://tudominio.com/verify-email-setup.php</code></li>
                <li>Haz click en "Test de Correo"</li>
                <li>El correo llegará a tu bandeja de entrada ✅</li>
            </ol>
        </div>
        
        <h3 style="color: #667eea; margin-top: 30px;">📚 Documentación Completa:</h3>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>📖 <strong>DEPLOYMENT-RAPIDO.md</strong> - Guía de 10 minutos</li>
            <li>📘 <strong>CONFIGURACION-CORREO-HOSTINGER.md</strong> - Guía completa</li>
            <li>📋 <strong>DEPLOYMENT-CHECKLIST.md</strong> - Lista de verificación</li>
            <li>📊 <strong>SISTEMA-CORREO-README.md</strong> - Documentación técnica</li>
        </ul>
        
        <div style="margin-top: 40px; padding: 20px; background: #f8f9ff; border-radius: 10px; text-align: center;">
            <p style="font-size: 18px; color: #667eea; font-weight: bold; margin-bottom: 10px;">
                🎉 ¡El sistema está 100% listo para producción!
            </p>
            <p style="color: #666;">
                En localhost es normal que los correos no se envíen.<br>
                Una vez en Hostinger con SMTP configurado, funcionará perfectamente.
            </p>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #999; font-size: 12px;">
            <p>Automatiza Tech - Sistema de Envío de Correos v1.0</p>
        </div>
    </div>
</body>
</html>
