<?php
/**
 * Script de verificación de permisos - Acceder desde: /debug-permisos-web.php
 * IMPORTANTE: Debes estar logueado en WordPress para usar este script
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Si no hay usuario logueado, redirigir al login
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_site_url() . '/debug-permisos-web.php'));
    exit;
}

// Verificar que sea administrador
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página. Necesitas ser administrador.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug de Permisos - Automatiza Tech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f1f1f1; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; }
        .error { color: red; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
        pre { background: #f9f9f9; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .test-button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .test-button:hover { background: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug de Permisos - Automatiza Tech</h1>
        
        <?php
        $current_user = wp_get_current_user();
        ?>
        
        <div class="info">
            <h3>Usuario Actual</h3>
            <p><strong>ID:</strong> <?php echo $current_user->ID; ?></p>
            <p><strong>Usuario:</strong> <?php echo $current_user->user_login; ?></p>
            <p><strong>Email:</strong> <?php echo $current_user->user_email; ?></p>
            <p><strong>Roles:</strong> <?php echo implode(', ', $current_user->roles); ?></p>
        </div>
        
        <h3>✅ Verificaciones de Permisos</h3>
        <ul>
            <li>current_user_can('manage_options'): <span class="<?php echo current_user_can('manage_options') ? 'success' : 'error'; ?>"><?php echo current_user_can('manage_options') ? '✅ SÍ' : '❌ NO'; ?></span></li>
            <li>current_user_can('administrator'): <span class="<?php echo current_user_can('administrator') ? 'success' : 'error'; ?>"><?php echo current_user_can('administrator') ? '✅ SÍ' : '❌ NO'; ?></span></li>
            <li>current_user_can('edit_posts'): <span class="<?php echo current_user_can('edit_posts') ? 'success' : 'error'; ?>"><?php echo current_user_can('edit_posts') ? '✅ SÍ' : '❌ NO'; ?></span></li>
        </ul>
        
        <h3>🔧 Pruebas de Funcionalidad</h3>
        
        <h4>Test 1: Verificar Nonce</h4>
        <?php
        $nonce = wp_create_nonce('automatiza_services_nonce');
        $nonce_valid = wp_verify_nonce($nonce, 'automatiza_services_nonce');
        ?>
        <p>Nonce generado: <code><?php echo $nonce; ?></code></p>
        <p>Verificación: <span class="<?php echo $nonce_valid ? 'success' : 'error'; ?>"><?php echo $nonce_valid ? '✅ VÁLIDO' : '❌ INVÁLIDO'; ?></span></p>
        
        <h4>Test 2: Conexión a Base de Datos</h4>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'automatiza_services';
        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", 4));
        ?>
        <p>Servicio ID 4 (Atención 24/7): <span class="<?php echo $service ? 'success' : 'error'; ?>"><?php echo $service ? '✅ ENCONTRADO' : '❌ NO ENCONTRADO'; ?></span></p>
        <?php if ($service): ?>
        <pre><?php print_r($service); ?></pre>
        <?php endif; ?>
        
        <h4>Test 3: Simular AJAX get_service_details</h4>
        <div id="ajax-test-result">
            <button class="test-button" onclick="testAjaxCall()">🧪 Probar AJAX</button>
            <div id="ajax-result"></div>
        </div>
        
        <h3>📋 Script para Consola del Navegador</h3>
        <p>Copia y pega este código en la consola del navegador cuando estés en la página de servicios:</p>
        <pre><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/debug-console.js')); ?></pre>
        
        <h3>🔗 Enlaces Útiles</h3>
        <p>
            <a href="<?php echo admin_url('admin.php?page=automatiza-services'); ?>" class="test-button">📝 Ir a Servicios Admin</a>
            <a href="<?php echo admin_url(); ?>" class="test-button">🏠 WordPress Admin</a>
        </p>
    </div>
    
    <script>
    function testAjaxCall() {
        const resultDiv = document.getElementById('ajax-result');
        resultDiv.innerHTML = '⏳ Probando llamada AJAX...';
        
        const data = new FormData();
        data.append('action', 'get_service_details');
        data.append('service_id', '4');
        data.append('nonce', '<?php echo $nonce; ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: data,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.innerHTML = '<h4>Resultado AJAX:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            if (data.success) {
                resultDiv.innerHTML += '<p class="success">✅ AJAX funcionando correctamente</p>';
            } else {
                resultDiv.innerHTML += '<p class="error">❌ Error en AJAX: ' + (data.data || 'Error desconocido') + '</p>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<p class="error">❌ Error de red: ' + error.message + '</p>';
        });
    }
    </script>
</body>
</html>