<?php
/**
 * Script de verificación para producción
 * Verifica que todos los componentes necesarios estén configurados
 * 
 * Uso: Subir a la raíz del sitio y acceder via navegador
 * URL: https://tudominio.com/verify-email-setup.php
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea administrador
if (!current_user_can('administrator')) {
    die('⛔ Acceso denegado. Solo administradores pueden ver esta página.');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Configuración de Correo - Automatiza Tech</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .check-item {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 5px solid #ddd;
        }
        
        .check-item.success {
            border-left-color: #4caf50;
            background: #f1f8f4;
        }
        
        .check-item.warning {
            border-left-color: #ff9800;
            background: #fff9f0;
        }
        
        .check-item.error {
            border-left-color: #f44336;
            background: #fef5f5;
        }
        
        .check-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .check-detail {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .icon {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        
        .button:hover {
            transform: translateY(-2px);
        }
        
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .summary h2 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificación de Configuración de Correo</h1>
        <p class="subtitle">Automatiza Tech - Sistema de Envío de Correos</p>
        
        <?php
        $checks = array();
        $total_checks = 0;
        $passed_checks = 0;
        
        // 1. Verificar archivo smtp-config.php
        $smtp_config_exists = file_exists(get_template_directory() . '/inc/smtp-config.php');
        $total_checks++;
        if ($smtp_config_exists) {
            $passed_checks++;
            $checks[] = array(
                'status' => 'success',
                'title' => '✅ Archivo smtp-config.php encontrado',
                'detail' => 'El archivo de configuración SMTP está presente en el tema.'
            );
        } else {
            $checks[] = array(
                'status' => 'error',
                'title' => '❌ Archivo smtp-config.php NO encontrado',
                'detail' => 'Falta el archivo de configuración SMTP. Debe estar en: /wp-content/themes/automatiza-tech/inc/smtp-config.php'
            );
        }
        
        // 2. Verificar constantes SMTP en wp-config.php
        $total_checks++;
        if (defined('SMTP_USER') && defined('SMTP_PASS')) {
            $passed_checks++;
            $checks[] = array(
                'status' => 'success',
                'title' => '✅ Credenciales SMTP configuradas',
                'detail' => 'Usuario SMTP: ' . SMTP_USER . '<br>Las credenciales están definidas en wp-config.php'
            );
        } else {
            $checks[] = array(
                'status' => 'error',
                'title' => '❌ Credenciales SMTP NO configuradas',
                'detail' => 'Debes agregar SMTP_USER y SMTP_PASS en wp-config.php. Ver documentación en CONFIGURACION-CORREO-HOSTINGER.md'
            );
        }
        
        // 3. Verificar correo de administrador
        $total_checks++;
        $admin_email = get_option('admin_email');
        if ($admin_email && strpos($admin_email, 'wordpress@') === false) {
            $passed_checks++;
            $checks[] = array(
                'status' => 'success',
                'title' => '✅ Correo de administrador configurado',
                'detail' => 'Correo actual: ' . $admin_email
            );
        } else {
            $checks[] = array(
                'status' => 'warning',
                'title' => '⚠️ Correo de administrador por defecto',
                'detail' => 'Se recomienda cambiar el correo en Ajustes → Generales a info@automatizatech.cl'
            );
        }
        
        // 4. Verificar logo
        $total_checks++;
        $logo_path = get_template_directory() . '/assets/images/logo-automatiza-tech.png';
        if (file_exists($logo_path)) {
            $passed_checks++;
            $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
            $checks[] = array(
                'status' => 'success',
                'title' => '✅ Logo encontrado',
                'detail' => 'Logo disponible en: ' . $logo_url
            );
        } else {
            $checks[] = array(
                'status' => 'warning',
                'title' => '⚠️ Logo NO encontrado',
                'detail' => 'Sube el logo a: /wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png'
            );
        }
        
        // 5. Verificar función wp_mail
        $total_checks++;
        if (function_exists('wp_mail')) {
            $passed_checks++;
            $checks[] = array(
                'status' => 'success',
                'title' => '✅ Función wp_mail disponible',
                'detail' => 'La función de envío de correos de WordPress está activa.'
            );
        } else {
            $checks[] = array(
                'status' => 'error',
                'title' => '❌ Función wp_mail NO disponible',
                'detail' => 'Problema crítico con WordPress. Contacta a soporte.'
            );
        }
        
        // 6. Verificar tabla de contactos
        global $wpdb;
        $table_name = $wpdb->prefix . 'automatiza_tech_contacts';
        $total_checks++;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $contact_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $new_contacts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'new'");
            $passed_checks++;
            $checks[] = array(
                'status' => 'success',
                'title' => '✅ Tabla de contactos encontrada',
                'detail' => "Total de contactos: $contact_count<br>Contactos nuevos: $new_contacts"
            );
        } else {
            $checks[] = array(
                'status' => 'error',
                'title' => '❌ Tabla de contactos NO encontrada',
                'detail' => 'La tabla de contactos no existe. Activa el plugin/tema para crearla.'
            );
        }
        
        // 7. Verificar planes en base de datos
        $total_checks++;
        $services_table = $wpdb->prefix . 'automatiza_services';
        if ($wpdb->get_var("SHOW TABLES LIKE '$services_table'") == $services_table) {
            $plans_count = $wpdb->get_var("SELECT COUNT(*) FROM $services_table WHERE category = 'pricing' AND active = 1");
            if ($plans_count > 0) {
                $passed_checks++;
                $checks[] = array(
                    'status' => 'success',
                    'title' => '✅ Planes activos en base de datos',
                    'detail' => "Se encontraron $plans_count planes activos para incluir en los correos."
                );
            } else {
                $checks[] = array(
                    'status' => 'warning',
                    'title' => '⚠️ No hay planes activos',
                    'detail' => 'Debes agregar planes en Automatiza Tech → Servicios para que aparezcan en los correos.'
                );
            }
        } else {
            $checks[] = array(
                'status' => 'error',
                'title' => '❌ Tabla de servicios NO encontrada',
                'detail' => 'La tabla de servicios no existe. Activa el módulo de servicios.'
            );
        }
        
        // Calcular porcentaje
        $percentage = round(($passed_checks / $total_checks) * 100);
        $status_color = $percentage >= 80 ? '#4caf50' : ($percentage >= 60 ? '#ff9800' : '#f44336');
        $status_icon = $percentage >= 80 ? '🎉' : ($percentage >= 60 ? '⚠️' : '❌');
        $status_text = $percentage >= 80 ? 'Excelente' : ($percentage >= 60 ? 'Requiere atención' : 'Crítico');
        ?>
        
        <div class="summary" style="background: <?php echo $status_color; ?>;">
            <h2><?php echo $status_icon; ?> Estado: <?php echo $status_text; ?></h2>
            <p style="font-size: 48px; font-weight: bold; margin: 10px 0;"><?php echo $percentage; ?>%</p>
            <p><?php echo $passed_checks; ?> de <?php echo $total_checks; ?> verificaciones pasadas</p>
        </div>
        
        <h2 style="color: #667eea; margin-bottom: 20px;">📋 Detalles de Verificación</h2>
        
        <?php foreach ($checks as $check): ?>
            <div class="check-item <?php echo $check['status']; ?>">
                <div class="check-title"><?php echo $check['title']; ?></div>
                <div class="check-detail"><?php echo $check['detail']; ?></div>
            </div>
        <?php endforeach; ?>
        
        <h2 style="color: #667eea; margin: 30px 0 20px 0;">🧪 Test de Envío</h2>
        
        <div class="check-item" style="border-left-color: #667eea;">
            <div class="check-title">📧 Enviar correo de prueba</div>
            <div class="check-detail">
                Haz clic en el botón para enviar un correo de prueba a: <strong><?php echo $admin_email; ?></strong>
                <br><br>
                <a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts&test_email=send&_wpnonce=' . wp_create_nonce('test_email')); ?>" class="button">
                    📧 Enviar Test de Correo
                </a>
            </div>
        </div>
        
        <h2 style="color: #667eea; margin: 30px 0 20px 0;">📖 Documentación</h2>
        
        <div class="check-item" style="border-left-color: #667eea;">
            <div class="check-title">📚 Guía de configuración completa</div>
            <div class="check-detail">
                Lee la documentación completa en: <strong>CONFIGURACION-CORREO-HOSTINGER.md</strong>
                <br><br>
                Incluye paso a paso:
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Crear cuenta de correo en Hostinger</li>
                    <li>Configurar wp-config.php</li>
                    <li>Configurar SPF y DKIM</li>
                    <li>Solución de problemas comunes</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-top: 40px; padding: 20px; background: #f8f9ff; border-radius: 10px; text-align: center;">
            <p style="color: #666; margin-bottom: 10px;">¿Todo configurado correctamente?</p>
            <a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts'); ?>" class="button">
                🚀 Ir al Panel de Contactos
            </a>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #999; font-size: 12px;">
            <p>Automatiza Tech - Sistema de Envío de Correos v1.0</p>
            <p>⚠️ IMPORTANTE: Elimina este archivo después de verificar la configuración por seguridad</p>
        </div>
    </div>
</body>
</html>
