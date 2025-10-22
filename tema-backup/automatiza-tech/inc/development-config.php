<?php
/**
 * Configuraciones adicionales para desarrollo local
 * Incluir este archivo en functions.php cuando esté en desarrollo local
 */

// Solo ejecutar en entorno local
if (defined('WP_DEBUG') && WP_DEBUG && strpos(home_url(), 'localhost') !== false) {

    /**
     * Configuraciones de debug adicionales
     */
    
    // Mostrar errores de consultas SQL
    if (!defined('SAVEQUERIES')) {
        define('SAVEQUERIES', true);
    }
    
    // Log de consultas lentas
    add_action('wp_footer', function() {
        if (current_user_can('administrator') && isset($_GET['debug_queries'])) {
            global $wpdb;
            echo '<div style="background: #000; color: #fff; padding: 20px; margin: 20px; font-family: monospace; font-size: 12px;">';
            echo '<h3>DEBUG: Consultas SQL (' . count($wpdb->queries) . ' total)</h3>';
            foreach ($wpdb->queries as $query) {
                echo '<div style="margin: 10px 0; padding: 10px; background: #333;">';
                echo '<strong>Tiempo:</strong> ' . $query[1] . 's<br>';
                echo '<strong>Query:</strong> ' . htmlspecialchars($query[0]) . '<br>';
                if (isset($query[2])) {
                    echo '<strong>Stack:</strong> ' . htmlspecialchars($query[2]);
                }
                echo '</div>';
            }
            echo '</div>';
        }
    });

    /**
     * Herramientas de desarrollo
     */
    
    // Agregar admin bar para desarrolladores
    add_action('admin_bar_menu', function($wp_admin_bar) {
        $wp_admin_bar->add_menu(array(
            'id' => 'dev-tools',
            'title' => '🔧 Dev Tools',
            'href' => '#'
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'dev-tools',
            'id' => 'debug-queries',
            'title' => 'Ver Queries SQL',
            'href' => add_query_arg('debug_queries', '1')
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'dev-tools',
            'id' => 'clear-cache',
            'title' => 'Limpiar Cache',
            'href' => add_query_arg('clear_cache', '1')
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'dev-tools',
            'id' => 'phpinfo',
            'title' => 'PHP Info',
            'href' => admin_url('admin.php?page=dev-phpinfo')
        ));
    });
    
    // Manejar acciones de desarrollo
    add_action('init', function() {
        if (isset($_GET['clear_cache']) && current_user_can('administrator')) {
            // Limpiar cache si existe
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Limpiar opciones transient
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
            
            wp_redirect(remove_query_arg('clear_cache'));
            exit;
        }
    });
    
    // Agregar página de PHP Info en admin
    add_action('admin_menu', function() {
        add_management_page(
            'PHP Info',
            'PHP Info',
            'administrator',
            'dev-phpinfo',
            function() {
                echo '<div class="wrap">';
                echo '<h1>PHP Information</h1>';
                echo '<div style="background: white; padding: 20px;">';
                phpinfo();
                echo '</div>';
                echo '</div>';
            }
        );
    });

    /**
     * Configuraciones de email para desarrollo local
     */
    
    // Interceptar emails en desarrollo
    add_filter('wp_mail', function($args) {
        // Guardar emails en archivo log en lugar de enviarlos
        $log_file = WP_CONTENT_DIR . '/mail-debug.log';
        $log_content = "\n\n" . date('Y-m-d H:i:s') . " - EMAIL INTERCEPTADO\n";
        $log_content .= "Para: " . $args['to'] . "\n";
        $log_content .= "Asunto: " . $args['subject'] . "\n";
        $log_content .= "Mensaje: " . $args['message'] . "\n";
        $log_content .= str_repeat('-', 50);
        
        file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
        
        // Modificar email para desarrollo
        $args['to'] = 'dev@automatizatech.local';
        $args['subject'] = '[LOCAL] ' . $args['subject'];
        
        return $args;
    });

    /**
     * Datos de prueba y helpers
     */
    
    // Agregar datos de prueba
    add_action('wp_ajax_create_test_data', function() {
        if (!current_user_can('administrator')) {
            wp_die('Sin permisos');
        }
        
        global $wpdb;
        
        // Crear leads de prueba
        $test_leads = array(
            array('Ana García', 'ana.garcia@test.local', 'Test Corp', '+52111222333', 'Lead de prueba 1'),
            array('Pedro López', 'pedro.lopez@demo.local', 'Demo Ltd', '+52444555666', 'Lead de prueba 2'),
            array('Laura Martín', 'laura.martin@ejemplo.local', 'Ejemplo SA', '+52777888999', 'Lead de prueba 3')
        );
        
        foreach ($test_leads as $lead) {
            $wpdb->insert(
                $wpdb->prefix . 'contact_leads',
                array(
                    'name' => $lead[0],
                    'email' => $lead[1],
                    'company' => $lead[2],
                    'phone' => $lead[3],
                    'message' => $lead[4],
                    'source' => 'test',
                    'status' => 'new'
                )
            );
        }
        
        wp_send_json_success('Datos de prueba creados');
    });
    
    // Función helper para debug
    if (!function_exists('dd')) {
        function dd($data) {
            echo '<pre style="background: #000; color: #fff; padding: 20px; margin: 20px; font-family: monospace;">';
            var_dump($data);
            echo '</pre>';
            die();
        }
    }

    /**
     * Modificaciones de tema para desarrollo
     */
    
    // Mostrar indicador de desarrollo
    add_action('wp_footer', function() {
        echo '<div style="position: fixed; bottom: 0; left: 0; background: #ff6b6b; color: white; padding: 5px 10px; font-size: 12px; z-index: 9999;">
                🔧 DESARROLLO LOCAL
              </div>';
    });
    
    // Desactivar optimizaciones en desarrollo
    add_filter('automatiza_tech_optimize_assets', '__return_false');
    add_filter('automatiza_tech_enable_cache', '__return_false');
    add_filter('automatiza_tech_minify_html', '__return_false');
    
    // Forzar recarga de estilos y scripts
    add_filter('style_loader_src', function($src) {
        if (strpos($src, '/wp-content/themes/automatiza-tech/') !== false) {
            return add_query_arg('v', time(), $src);
        }
        return $src;
    });
    
    add_filter('script_loader_src', function($src) {
        if (strpos($src, '/wp-content/themes/automatiza-tech/') !== false) {
            return add_query_arg('v', time(), $src);
        }
        return $src;
    });

    /**
     * Configuración de errores PHP más detallada
     */
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', WP_CONTENT_DIR . '/php-errors.log');
    
    // Log de errores de WordPress
    add_action('wp_footer', function() {
        if (current_user_can('administrator') && file_exists(WP_CONTENT_DIR . '/debug.log')) {
            $debug_log = file_get_contents(WP_CONTENT_DIR . '/debug.log');
            $recent_errors = array_slice(explode("\n", $debug_log), -10);
            
            if (!empty(array_filter($recent_errors))) {
                echo '<div style="position: fixed; top: 32px; right: 0; width: 400px; background: #d63384; color: white; padding: 10px; font-size: 12px; z-index: 9998; max-height: 200px; overflow-y: auto;">
                        <strong>🚨 Errores Recientes:</strong><br>
                        ' . implode('<br>', array_filter($recent_errors)) . '
                      </div>';
            }
        }
    });
}