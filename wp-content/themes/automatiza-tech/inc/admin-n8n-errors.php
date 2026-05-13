<?php
/**
 * Panel de Administración para Errores N8N - ARGOS
 * 
 * Monitoreo y gestión de errores de automatizaciones N8N
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agregar menú de administración
 */
function automatiza_n8n_errors_menu() {
    add_menu_page(
        'Errores N8N - ARGOS',
        '🛡️ ARGOS Errores',
        'manage_options',
        'automatiza-n8n-errors',
        'automatiza_n8n_errors_page',
        'dashicons-warning',
        27
    );
    
    add_submenu_page(
        'automatiza-n8n-errors',
        'Dashboard de Errores',
        'Dashboard',
        'manage_options',
        'automatiza-n8n-errors',
        'automatiza_n8n_errors_page'
    );
    
    add_submenu_page(
        'automatiza-n8n-errors',
        'Configuración ARGOS',
        'Configuración',
        'manage_options',
        'automatiza-n8n-errors-settings',
        'automatiza_n8n_errors_settings_page'
    );
}
add_action('admin_menu', 'automatiza_n8n_errors_menu');

/**
 * Registrar endpoint REST API para recibir errores
 */
function automatiza_register_n8n_errors_api() {
    register_rest_route('automatiza/v1', '/n8n-errors', array(
        'methods' => 'POST',
        'callback' => 'automatiza_save_n8n_error',
        'permission_callback' => 'automatiza_verify_n8n_api_key',
    ));
    
    register_rest_route('automatiza/v1', '/n8n-errors', array(
        'methods' => 'GET',
        'callback' => 'automatiza_get_n8n_errors',
        'permission_callback' => 'automatiza_verify_n8n_api_key',
    ));
}
add_action('rest_api_init', 'automatiza_register_n8n_errors_api');

/**
 * Verificar API Key
 */
function automatiza_verify_n8n_api_key($request) {
    $api_key = $request->get_header('X-API-Key');
    $stored_key = get_option('automatiza_n8n_api_key', 'argos-automatiza-2024-secret');
    
    // En desarrollo, permitir sin key
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return true;
    }
    
    return $api_key === $stored_key;
}

/**
 * Guardar error desde N8N
 */
function automatiza_save_n8n_error($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_n8n_errors';
    
    $params = $request->get_json_params();
    
    // Validar datos requeridos
    if (empty($params['workflow_name']) || empty($params['error_message'])) {
        return new WP_Error('missing_data', 'Faltan datos requeridos', array('status' => 400));
    }
    
    // Preparar datos para insertar
    $data = array(
        'workflow_name' => sanitize_text_field($params['workflow_name']),
        'workflow_id' => sanitize_text_field($params['workflow_id'] ?? ''),
        'execution_id' => sanitize_text_field($params['execution_id'] ?? ''),
        'error_message' => sanitize_textarea_field($params['error_message']),
        'error_node' => sanitize_text_field($params['error_node'] ?? ''),
        'error_stack' => sanitize_textarea_field($params['error_stack'] ?? ''),
        'error_timestamp' => sanitize_text_field($params['error_timestamp'] ?? current_time('mysql')),
        'execution_mode' => sanitize_text_field($params['execution_mode'] ?? 'production'),
        'argos_analysis' => sanitize_textarea_field($params['argos_analysis'] ?? ''),
        'severity' => in_array($params['severity'] ?? '', ['critical', 'high', 'medium', 'low']) 
            ? $params['severity'] : 'medium',
        'status' => 'new',
        'notification_whatsapp' => 1,
        'notification_email' => 1,
    );
    
    $formats = array(
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'
    );
    
    $result = $wpdb->insert($table_name, $data, $formats);
    
    if ($result === false) {
        return new WP_Error('db_error', 'Error al guardar en base de datos', array('status' => 500));
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Error guardado correctamente',
        'error_id' => $wpdb->insert_id
    ), 201);
}

/**
 * Obtener errores (API)
 */
function automatiza_get_n8n_errors($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_n8n_errors';
    
    $limit = max( 1, min( 500, intval( $request->get_param('limit') ?? 50 ) ) );
    $status = sanitize_text_field($request->get_param('status') ?? '');

    $where_parts  = ['1=1'];
    $where_params = [];
    if ($status) {
        $where_parts[]  = 'status = %s';
        $where_params[] = $status;
    }
    $where_sql = implode( ' AND ', $where_parts );

    $query = empty($where_params)
        ? $wpdb->prepare( "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d", $limit )
        : $wpdb->prepare( "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d", array_merge( $where_params, [$limit] ) );

    $errors = $wpdb->get_results( $query );
    
    return new WP_REST_Response($errors, 200);
}

/**
 * Buscar errores similares (para ARGOS)
 * Busca por workflow, nodo y mensaje de error
 */
function automatiza_search_similar_errors($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_n8n_errors';
    
    $workflow_name = sanitize_text_field($request->get_param('workflow_name') ?? '');
    $error_node = sanitize_text_field($request->get_param('error_node') ?? '');
    $error_message = sanitize_text_field($request->get_param('error_message') ?? '');
    $days = intval($request->get_param('days') ?? 30);
    
    if (empty($workflow_name)) {
        return new WP_REST_Response(array(
            'similar_errors' => array(),
            'count' => 0,
            'is_recurring' => false,
            'message' => 'No se proporcionó workflow_name'
        ), 200);
    }
    
    // Buscar errores del mismo workflow en los últimos X días
    $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    // Query para errores similares
    $query = $wpdb->prepare(
        "SELECT 
            id,
            workflow_name,
            error_node,
            error_message,
            severity,
            status,
            created_at,
            resolved_at,
            resolution_notes
        FROM $table_name 
        WHERE workflow_name = %s 
        AND created_at >= %s
        ORDER BY created_at DESC
        LIMIT 20",
        $workflow_name,
        $date_limit
    );
    
    $similar_errors = $wpdb->get_results($query);
    
    // Buscar errores exactos (mismo nodo Y mensaje similar)
    $exact_matches = array();
    $node_matches = array();
    
    foreach ($similar_errors as $error) {
        // Mismo nodo
        if (!empty($error_node) && $error->error_node === $error_node) {
            $node_matches[] = $error;
            
            // Mensaje similar (contiene las primeras 50 chars)
            $error_snippet = substr($error_message, 0, 50);
            if (!empty($error_message) && strpos($error->error_message, $error_snippet) !== false) {
                $exact_matches[] = $error;
            }
        }
    }
    
    // Estadísticas
    $total_count = count($similar_errors);
    $node_count = count($node_matches);
    $exact_count = count($exact_matches);
    
    // Determinar si es recurrente
    $is_recurring = $exact_count >= 2 || $node_count >= 3;
    
    // Buscar si hubo resoluciones previas
    $resolved_similar = array_filter($node_matches, function($e) {
        return $e->status === 'resolved' && !empty($e->resolution_notes);
    });
    
    // Preparar resumen para ARGOS
    $summary = array(
        'total_errors_workflow' => $total_count,
        'errors_same_node' => $node_count,
        'errors_exact_match' => $exact_count,
        'is_recurring' => $is_recurring,
        'last_occurrence' => !empty($similar_errors) ? $similar_errors[0]->created_at : null,
        'previous_resolutions' => array_map(function($e) {
            return array(
                'date' => $e->resolved_at,
                'notes' => $e->resolution_notes
            );
        }, array_slice($resolved_similar, 0, 3))
    );
    
    // Mensaje para ARGOS
    $argos_context = "";
    if ($is_recurring) {
        $argos_context = "⚠️ ERROR RECURRENTE DETECTADO:\n";
        $argos_context .= "- Este workflow ha tenido {$total_count} errores en los últimos {$days} días\n";
        $argos_context .= "- {$node_count} errores en el mismo nodo ({$error_node})\n";
        $argos_context .= "- {$exact_count} errores con mensaje idéntico\n";
        
        if (!empty($resolved_similar)) {
            $argos_context .= "\n📝 RESOLUCIONES ANTERIORES:\n";
            foreach (array_slice($resolved_similar, 0, 3) as $res) {
                $argos_context .= "- [{$res->resolved_at}]: {$res->resolution_notes}\n";
            }
        }
    } else {
        $argos_context = "✅ Este parece ser un error NUEVO (no recurrente).\n";
        $argos_context .= "- Errores previos del workflow: {$total_count}\n";
    }
    
    return new WP_REST_Response(array(
        'summary' => $summary,
        'argos_context' => $argos_context,
        'similar_errors' => array_slice($similar_errors, 0, 5),
        'node_matches' => array_slice($node_matches, 0, 5)
    ), 200);
}

// Registrar endpoint de búsqueda de errores similares
add_action('rest_api_init', function() {
    register_rest_route('automatiza/v1', '/n8n-errors/search', array(
        'methods' => 'GET',
        'callback' => 'automatiza_search_similar_errors',
        'permission_callback' => 'automatiza_verify_n8n_api_key',
    ));
});

/**
 * Renderizar página principal de errores
 */
function automatiza_n8n_errors_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_n8n_errors';
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        echo '<div class="wrap">';
        echo '<h1>🛡️ ARGOS - Sistema de Monitoreo de Errores N8N</h1>';
        echo '<div class="notice notice-error"><p>';
        echo 'La tabla de errores no existe. <a href="' . get_site_url() . '/setup-n8n-errors-db.php">Ejecuta el setup</a> primero.';
        echo '</p></div></div>';
        return;
    }
    
    // Procesar acciones
    if (isset($_POST['action']) && isset($_POST['error_id'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'argos_error_action')) {
            wp_die('Error de seguridad');
        }
        
        $error_id = intval($_POST['error_id']);
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'resolve':
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'resolved',
                        'resolved_at' => current_time('mysql'),
                        'resolved_by' => get_current_user_id(),
                        'resolution_notes' => sanitize_textarea_field($_POST['resolution_notes'] ?? '')
                    ),
                    array('id' => $error_id),
                    array('%s', '%s', '%d', '%s'),
                    array('%d')
                );
                echo '<div class="notice notice-success is-dismissible"><p>Error marcado como resuelto.</p></div>';
                break;
                
            case 'ignore':
                $wpdb->update(
                    $table_name,
                    array('status' => 'ignored'),
                    array('id' => $error_id),
                    array('%s'),
                    array('%d')
                );
                echo '<div class="notice notice-info is-dismissible"><p>Error ignorado.</p></div>';
                break;
                
            case 'delete':
                $wpdb->delete($table_name, array('id' => $error_id), array('%d'));
                echo '<div class="notice notice-warning is-dismissible"><p>Error eliminado.</p></div>';
                break;
        }
    }
    
    // Filtros
    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $filter_severity = isset($_GET['severity']) ? sanitize_text_field($_GET['severity']) : '';
    $filter_workflow = isset($_GET['workflow']) ? sanitize_text_field($_GET['workflow']) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Construir query
    $where = "1=1";
    if ($filter_status) {
        $where .= $wpdb->prepare(" AND status = %s", $filter_status);
    }
    if ($filter_severity) {
        $where .= $wpdb->prepare(" AND severity = %s", $filter_severity);
    }
    if ($filter_workflow) {
        $where .= $wpdb->prepare(" AND workflow_name LIKE %s", '%' . $wpdb->esc_like($filter_workflow) . '%');
    }
    
    $allowed_orderby = ['id', 'workflow_name', 'severity', 'status', 'error_timestamp', 'created_at'];
    if (!in_array($orderby, $allowed_orderby)) $orderby = 'created_at';
    
    $errors = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order LIMIT 100"
    );
    
    // Estadísticas
    $stats = array(
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
        'new' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'new'"),
        'critical' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE severity = 'critical' AND status = 'new'"),
        'today' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        )),
    );
    
    // Obtener workflows únicos para filtro
    $workflows = $wpdb->get_col("SELECT DISTINCT workflow_name FROM $table_name ORDER BY workflow_name");
    
    ?>
    <div class="wrap argos-admin">
        <style>
            .argos-admin {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .argos-header {
                background: linear-gradient(135deg, #1e40af, #3b82f6);
                color: white;
                padding: 30px;
                border-radius: 12px;
                margin-bottom: 30px;
                box-shadow: 0 4px 20px rgba(30, 64, 175, 0.3);
            }
            .argos-header h1 {
                margin: 0;
                font-size: 28px;
                display: flex;
                align-items: center;
                gap: 10px;
                color: white;
            }
            .argos-header p {
                margin: 10px 0 0;
                opacity: 0.9;
                color: white;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                border-left: 4px solid #3b82f6;
            }
            .stat-card.critical { border-left-color: #ef4444; }
            .stat-card.new { border-left-color: #f59e0b; }
            .stat-card.today { border-left-color: #10b981; }
            .stat-number {
                font-size: 36px;
                font-weight: bold;
                color: #1f2937;
            }
            .stat-label {
                color: #6b7280;
                font-size: 14px;
                margin-top: 5px;
            }
            .filters-bar {
                background: white;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                display: flex;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .filters-bar select, .filters-bar input[type="text"] {
                padding: 10px 15px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                font-size: 14px;
            }
            .filters-bar .button {
                padding: 10px 20px;
                border-radius: 8px;
            }
            .errors-table {
                width: 100%;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                border-collapse: collapse;
            }
            .errors-table th {
                background: #f8fafc;
                padding: 15px;
                text-align: left;
                font-weight: 600;
                color: #374151;
                border-bottom: 2px solid #e5e7eb;
            }
            .errors-table td {
                padding: 15px;
                border-bottom: 1px solid #f3f4f6;
                vertical-align: top;
            }
            .errors-table tr:hover {
                background: #f8fafc;
            }
            .severity-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .severity-critical { background: #fecaca; color: #dc2626; }
            .severity-high { background: #fed7aa; color: #ea580c; }
            .severity-medium { background: #fef08a; color: #ca8a04; }
            .severity-low { background: #d1fae5; color: #059669; }
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
            }
            .status-new { background: #dbeafe; color: #2563eb; }
            .status-reviewing { background: #fef3c7; color: #d97706; }
            .status-resolved { background: #d1fae5; color: #059669; }
            .status-ignored { background: #f3f4f6; color: #6b7280; }
            .error-message {
                max-width: 300px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                color: #374151;
            }
            .error-workflow {
                font-weight: 600;
                color: #1e40af;
            }
            .error-node {
                font-size: 12px;
                color: #6b7280;
                margin-top: 5px;
            }
            .actions-cell {
                white-space: nowrap;
            }
            .action-btn {
                display: inline-block;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                text-decoration: none;
                margin-right: 5px;
                cursor: pointer;
                border: none;
            }
            .btn-view { background: #eff6ff; color: #2563eb; }
            .btn-resolve { background: #d1fae5; color: #059669; }
            .btn-ignore { background: #f3f4f6; color: #6b7280; }
            .btn-delete { background: #fee2e2; color: #dc2626; }
            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 9999;
                align-items: center;
                justify-content: center;
            }
            .modal.active { display: flex; }
            .modal-content {
                background: white;
                border-radius: 16px;
                max-width: 800px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                padding: 30px;
            }
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #e5e7eb;
            }
            .modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #6b7280;
            }
            .detail-section {
                margin-bottom: 20px;
            }
            .detail-section h4 {
                color: #374151;
                margin-bottom: 10px;
            }
            .detail-box {
                background: #f8fafc;
                padding: 15px;
                border-radius: 8px;
                font-family: monospace;
                font-size: 13px;
                white-space: pre-wrap;
                word-break: break-word;
            }
            .argos-analysis {
                background: #eff6ff;
                border-left: 4px solid #3b82f6;
            }
            .timestamp {
                font-size: 12px;
                color: #9ca3af;
            }
            .no-errors {
                text-align: center;
                padding: 60px 20px;
                color: #6b7280;
            }
            .no-errors .icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            
            /* ==================== ESTILOS RESPONSIVOS ARGOS ==================== */
            
            /* Tablet (1024px y menos) */
            @media screen and (max-width: 1024px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                .errors-table th:nth-child(1),
                .errors-table td:nth-child(1) {
                    display: none; /* Ocultar columna ID en tablet */
                }
                .modal-content {
                    width: 95%;
                    max-width: none;
                }
            }
            
            /* Mobile (767px y menos) */
            @media screen and (max-width: 767px) {
                .argos-admin {
                    margin: 0 -10px;
                }
                .argos-header {
                    padding: 20px 15px;
                    border-radius: 0;
                    margin: 0 -10px 20px;
                }
                .argos-header h1 {
                    font-size: 20px;
                    flex-wrap: wrap;
                    justify-content: center;
                    text-align: center;
                }
                .argos-header p {
                    font-size: 14px;
                    text-align: center;
                }
                
                /* Stats Grid - 2x2 en móvil */
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                    padding: 0 5px;
                }
                .stat-card {
                    padding: 15px;
                    border-radius: 8px;
                }
                .stat-number {
                    font-size: 24px;
                }
                .stat-label {
                    font-size: 12px;
                }
                
                /* Filtros - Stack vertical */
                .filters-bar {
                    flex-direction: column;
                    align-items: stretch;
                    padding: 15px;
                    gap: 10px;
                }
                .filters-bar select,
                .filters-bar input[type="text"] {
                    width: 100%;
                    min-height: 44px; /* Touch-friendly */
                    font-size: 16px; /* Evita zoom iOS */
                }
                .filters-bar .button {
                    min-height: 44px;
                    width: 100%;
                    justify-content: center;
                }
                
                /* Tabla de errores - Scroll horizontal */
                .table-wrapper {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                    margin: 0 -15px;
                    padding: 0 15px;
                }
                .errors-table {
                    min-width: 700px;
                    font-size: 13px;
                }
                .errors-table th,
                .errors-table td {
                    padding: 12px 10px;
                }
                .errors-table th:nth-child(1),
                .errors-table td:nth-child(1) {
                    display: none;
                }
                .error-message {
                    max-width: 200px;
                }
                .action-btn {
                    padding: 8px 10px;
                    min-height: 36px;
                }
                
                /* Modal fullscreen en móvil */
                .modal-content {
                    width: 100%;
                    height: 100%;
                    max-height: 100vh;
                    border-radius: 0;
                    padding: 20px 15px;
                }
                .modal-header {
                    padding-bottom: 15px;
                }
                .modal-header h3 {
                    font-size: 18px;
                }
                .modal-close {
                    font-size: 28px;
                    padding: 10px;
                    min-width: 44px;
                    min-height: 44px;
                }
                .detail-box {
                    font-size: 12px;
                    padding: 12px;
                }
                
                /* No errors message */
                .no-errors {
                    padding: 40px 15px;
                }
                .no-errors .icon {
                    font-size: 48px;
                }
            }
            
            /* Móviles pequeños (480px y menos) */
            @media screen and (max-width: 480px) {
                .stats-grid {
                    grid-template-columns: 1fr 1fr;
                    gap: 8px;
                }
                .stat-card {
                    padding: 12px;
                }
                .stat-number {
                    font-size: 20px;
                }
                .stat-label {
                    font-size: 11px;
                }
                .argos-header h1 {
                    font-size: 18px;
                }
                .severity-badge,
                .status-badge {
                    font-size: 10px;
                    padding: 3px 8px;
                }
            }
            
            /* Touch-friendly improvements */
            @media (hover: none) and (pointer: coarse) {
                .action-btn {
                    min-height: 44px;
                    min-width: 44px;
                }
                .filters-bar select,
                .filters-bar input {
                    min-height: 48px;
                }
            }
            
            /* Safe area para iPhones con notch */
            @supports (padding-bottom: env(safe-area-inset-bottom)) {
                @media screen and (max-width: 767px) {
                    .modal-content {
                        padding-bottom: calc(20px + env(safe-area-inset-bottom));
                    }
                }
            }
        </style>
        
        <div class="argos-header">
            <h1>🛡️ ARGOS - Sistema de Monitoreo de Errores N8N</h1>
            <p>Panel de control para monitorear y gestionar errores de automatizaciones</p>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Errores Totales</div>
            </div>
            <div class="stat-card new">
                <div class="stat-number"><?php echo number_format($stats['new']); ?></div>
                <div class="stat-label">Sin Resolver</div>
            </div>
            <div class="stat-card critical">
                <div class="stat-number"><?php echo number_format($stats['critical']); ?></div>
                <div class="stat-label">Críticos Pendientes</div>
            </div>
            <div class="stat-card today">
                <div class="stat-number"><?php echo number_format($stats['today']); ?></div>
                <div class="stat-label">Errores Hoy</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="get" class="filters-bar">
            <input type="hidden" name="page" value="automatiza-n8n-errors">
            
            <select name="status">
                <option value="">Todos los estados</option>
                <option value="new" <?php selected($filter_status, 'new'); ?>>🔵 Nuevo</option>
                <option value="reviewing" <?php selected($filter_status, 'reviewing'); ?>>🟡 En Revisión</option>
                <option value="resolved" <?php selected($filter_status, 'resolved'); ?>>🟢 Resuelto</option>
                <option value="ignored" <?php selected($filter_status, 'ignored'); ?>>⚪ Ignorado</option>
            </select>
            
            <select name="severity">
                <option value="">Todas las severidades</option>
                <option value="critical" <?php selected($filter_severity, 'critical'); ?>>🔴 Crítico</option>
                <option value="high" <?php selected($filter_severity, 'high'); ?>>🟠 Alto</option>
                <option value="medium" <?php selected($filter_severity, 'medium'); ?>>🟡 Medio</option>
                <option value="low" <?php selected($filter_severity, 'low'); ?>>🟢 Bajo</option>
            </select>
            
            <select name="workflow">
                <option value="">Todos los workflows</option>
                <?php foreach ($workflows as $wf): ?>
                    <option value="<?php echo esc_attr($wf); ?>" <?php selected($filter_workflow, $wf); ?>>
                        <?php echo esc_html($wf); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="button button-primary">Filtrar</button>
            <a href="<?php echo admin_url('admin.php?page=automatiza-n8n-errors'); ?>" class="button">Limpiar</a>
        </form>
        
        <!-- Tabla de Errores -->
        <?php if (empty($errors)): ?>
            <div class="no-errors">
                <div class="icon">✅</div>
                <h3>¡Sin errores!</h3>
                <p>No hay errores que coincidan con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
            <table class="errors-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Workflow / Nodo</th>
                        <th>Error</th>
                        <th>Severidad</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($errors as $error): ?>
                        <tr data-error-id="<?php echo $error->id; ?>">
                            <td>#<?php echo $error->id; ?></td>
                            <td>
                                <div class="error-workflow"><?php echo esc_html($error->workflow_name); ?></div>
                                <div class="error-node">🔧 <?php echo esc_html($error->error_node ?: 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="error-message" title="<?php echo esc_attr($error->error_message); ?>">
                                    <?php echo esc_html($error->error_message); ?>
                                </div>
                            </td>
                            <td>
                                <span class="severity-badge severity-<?php echo esc_attr($error->severity); ?>">
                                    <?php echo esc_html(ucfirst($error->severity)); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($error->status); ?>">
                                    <?php 
                                    $status_labels = [
                                        'new' => 'Nuevo',
                                        'reviewing' => 'En Revisión',
                                        'resolved' => 'Resuelto',
                                        'ignored' => 'Ignorado'
                                    ];
                                    echo esc_html($status_labels[$error->status] ?? $error->status);
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="timestamp">
                                    <?php echo esc_html(date('d/m/Y H:i', strtotime($error->created_at))); ?>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <button type="button" class="action-btn btn-view" 
                                        onclick="viewErrorDetails(<?php echo $error->id; ?>)">
                                    👁️ Ver
                                </button>
                                <?php if ($error->status !== 'resolved'): ?>
                                    <button type="button" class="action-btn btn-resolve"
                                            onclick="resolveError(<?php echo $error->id; ?>)">
                                        ✅
                                    </button>
                                <?php endif; ?>
                                <?php if ($error->status === 'new'): ?>
                                    <button type="button" class="action-btn btn-ignore"
                                            onclick="ignoreError(<?php echo $error->id; ?>)">
                                        🚫
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div><!-- /.table-wrapper -->
        <?php endif; ?>
        
        <!-- Modal para detalles del error -->
        <div id="errorModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🔍 Detalles del Error #<span id="modalErrorId"></span></h2>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div id="modalContent">
                    <!-- Contenido dinámico -->
                </div>
            </div>
        </div>
        
        <!-- Formularios ocultos para acciones -->
        <form id="actionForm" method="post" style="display: none;">
            <?php wp_nonce_field('argos_error_action'); ?>
            <input type="hidden" name="error_id" id="actionErrorId">
            <input type="hidden" name="action" id="actionType">
            <input type="hidden" name="resolution_notes" id="resolutionNotes">
        </form>
        
        <script>
        // Datos de errores para el modal
        const errorsData = <?php echo json_encode($errors); ?>;
        
        function viewErrorDetails(errorId) {
            const error = errorsData.find(e => e.id == errorId);
            if (!error) return;
            
            document.getElementById('modalErrorId').textContent = error.id;
            
            let html = `
                <div class="detail-section">
                    <h4>📋 Información del Workflow</h4>
                    <div class="detail-box">
<strong>Workflow:</strong> ${escapeHtml(error.workflow_name)}
<strong>Workflow ID:</strong> ${escapeHtml(error.workflow_id || 'N/A')}
<strong>Execution ID:</strong> ${escapeHtml(error.execution_id || 'N/A')}
<strong>Nodo:</strong> ${escapeHtml(error.error_node || 'N/A')}
<strong>Modo:</strong> ${escapeHtml(error.execution_mode)}
<strong>Timestamp:</strong> ${escapeHtml(error.error_timestamp)}
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>💬 Mensaje de Error</h4>
                    <div class="detail-box" style="background: #fef2f2; border-left: 4px solid #ef4444;">
${escapeHtml(error.error_message)}
                    </div>
                </div>
            `;
            
            if (error.argos_analysis) {
                html += `
                <div class="detail-section">
                    <h4>🤖 Análisis de ARGOS</h4>
                    <div class="detail-box argos-analysis">
${escapeHtml(error.argos_analysis)}
                    </div>
                </div>
                `;
            }
            
            if (error.error_stack && error.error_stack !== 'No disponible') {
                html += `
                <div class="detail-section">
                    <h4>📜 Stack Trace</h4>
                    <div class="detail-box" style="background: #1f2937; color: #e5e7eb; font-size: 11px;">
${escapeHtml(error.error_stack)}
                    </div>
                </div>
                `;
            }
            
            if (error.status === 'resolved') {
                html += `
                <div class="detail-section">
                    <h4>✅ Resolución</h4>
                    <div class="detail-box" style="background: #d1fae5; border-left: 4px solid #10b981;">
<strong>Resuelto:</strong> ${escapeHtml(error.resolved_at || 'N/A')}
<strong>Notas:</strong> ${escapeHtml(error.resolution_notes || 'Sin notas')}
                    </div>
                </div>
                `;
            }
            
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('errorModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('errorModal').classList.remove('active');
        }
        
        function resolveError(errorId) {
            const notes = prompt('Notas de resolución (opcional):');
            if (notes === null) return; // Cancelado
            
            document.getElementById('actionErrorId').value = errorId;
            document.getElementById('actionType').value = 'resolve';
            document.getElementById('resolutionNotes').value = notes;
            document.getElementById('actionForm').submit();
        }
        
        function ignoreError(errorId) {
            if (!confirm('¿Ignorar este error?')) return;
            
            document.getElementById('actionErrorId').value = errorId;
            document.getElementById('actionType').value = 'ignore';
            document.getElementById('actionForm').submit();
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Cerrar modal con Escape o click fuera
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        document.getElementById('errorModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        </script>
    </div>
    <?php
}

/**
 * Página de configuración
 */
function automatiza_n8n_errors_settings_page() {
    // Guardar configuración
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'argos_save_settings')) {
            wp_die('Error de seguridad');
        }
        
        update_option('automatiza_n8n_api_key', sanitize_text_field($_POST['api_key']));
        update_option('automatiza_n8n_notification_email', sanitize_email($_POST['notification_email']));
        update_option('automatiza_n8n_notification_phone', sanitize_text_field($_POST['notification_phone']));
        
        echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
    }
    
    $api_key = get_option('automatiza_n8n_api_key', 'argos-automatiza-' . wp_generate_password(12, false));
    $notification_email = get_option('automatiza_n8n_notification_email', get_option('admin_email'));
    $notification_phone = get_option('automatiza_n8n_notification_phone', '56974940070');
    
    ?>
    <div class="wrap">
        <h1>⚙️ Configuración de ARGOS</h1>
        
        <form method="post">
            <?php wp_nonce_field('argos_save_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_key">API Key</label>
                    </th>
                    <td>
                        <input type="text" name="api_key" id="api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text" style="font-family: monospace;">
                        <p class="description">
                            Usa esta key en el header <code>X-API-Key</code> cuando envíes errores desde N8N.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_email">Email de Notificaciones</label>
                    </th>
                    <td>
                        <input type="email" name="notification_email" id="notification_email" 
                               value="<?php echo esc_attr($notification_email); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_phone">WhatsApp</label>
                    </th>
                    <td>
                        <input type="text" name="notification_phone" id="notification_phone" 
                               value="<?php echo esc_attr($notification_phone); ?>" 
                               class="regular-text">
                        <p class="description">
                            Número con código de país (ej: 56974940070)
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="save_settings" class="button button-primary">
                    Guardar Configuración
                </button>
            </p>
        </form>
        
        <hr>
        
        <h2>📡 Endpoint API</h2>
        <table class="form-table">
            <tr>
                <th>URL</th>
                <td><code><?php echo get_rest_url(null, 'automatiza/v1/n8n-errors'); ?></code></td>
            </tr>
            <tr>
                <th>Método</th>
                <td><code>POST</code></td>
            </tr>
            <tr>
                <th>Headers</th>
                <td>
                    <code>Content-Type: application/json</code><br>
                    <code>X-API-Key: <?php echo esc_html($api_key); ?></code>
                </td>
            </tr>
            <tr>
                <th>Body (JSON)</th>
                <td>
                    <pre style="background: #1f2937; color: #e5e7eb; padding: 15px; border-radius: 8px; font-size: 12px;">
{
  "workflow_name": "Nombre del Workflow",
  "workflow_id": "abc123",
  "execution_id": "exec_123",
  "error_message": "Descripción del error",
  "error_node": "Nombre del nodo",
  "error_stack": "Stack trace",
  "error_timestamp": "2026-01-11T10:00:00Z",
  "execution_mode": "production",
  "argos_analysis": "Análisis de ARGOS",
  "severity": "high"
}</pre>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
