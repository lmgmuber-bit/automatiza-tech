<?php
/**
 * Automatiza Tech - Client Details Module
 * Sistema de seguimiento detallado para prospectos y clientes finales
 * 
 * Dos tablas de detalles:
 * 1. wp_automatiza_propuestas_details - Seguimiento comercial (hasta contratación)
 * 2. wp_automatiza_clients_details - Seguimiento de proyecto (post contratación)
 */

if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTech_Client_Details {
    
    private $propuestas_details_table;
    private $clients_details_table;
    private $propuestas_table;
    private $clients_table;
    
    public function __construct() {
        global $wpdb;
        $this->propuestas_details_table = $wpdb->prefix . 'automatiza_propuestas_details';
        $this->clients_details_table = $wpdb->prefix . 'automatiza_clients_details';
        $this->propuestas_table = $wpdb->prefix . 'automatiza_propuestas';
        $this->clients_table = $wpdb->prefix . 'automatiza_tech_clients';
        
        // Hooks
        add_action('admin_init', array($this, 'create_tables'));
        add_action('wp_ajax_get_prospect_tracking_details', array($this, 'ajax_get_prospect_details'));
        add_action('wp_ajax_save_prospect_tracking_detail', array($this, 'ajax_save_prospect_detail'));
        add_action('wp_ajax_get_client_tracking_details', array($this, 'ajax_get_client_tracking_details'));
        add_action('wp_ajax_save_client_tracking_detail', array($this, 'ajax_save_client_detail'));
        add_action('wp_ajax_get_tracking_detail_history', array($this, 'ajax_get_detail_history'));
        add_action('wp_ajax_delete_tracking_detail', array($this, 'ajax_delete_detail'));
        
        // Hook para migrar detalles cuando se contrata un cliente
        add_action('automatiza_client_contracted', array($this, 'migrate_details_to_client'), 10, 2);
    }
    
    /**
     * Crear tablas de detalles
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de detalles para PROSPECTOS (seguimiento comercial)
        $sql_propuestas = "CREATE TABLE IF NOT EXISTS {$this->propuestas_details_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            propuesta_id bigint(20) NOT NULL,
            detail_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            status varchar(50) DEFAULT 'pending',
            amount decimal(12,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'CLP',
            scheduled_date date DEFAULT NULL,
            completed_date date DEFAULT NULL,
            related_id bigint(20) DEFAULT NULL,
            related_type varchar(50) DEFAULT NULL,
            attachment_url varchar(500) DEFAULT NULL,
            attachment_name varchar(255) DEFAULT NULL,
            attachment_type varchar(50) DEFAULT NULL,
            metadata longtext,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY propuesta_id (propuesta_id),
            KEY detail_type (detail_type),
            KEY status (status)
        ) $charset_collate;";
        
        // Tabla de detalles para CLIENTES FINALES (seguimiento de proyecto)
        $sql_clients = "CREATE TABLE IF NOT EXISTS {$this->clients_details_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            propuesta_origin_id bigint(20) DEFAULT NULL,
            detail_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            status varchar(50) DEFAULT 'pending',
            amount decimal(12,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'CLP',
            scheduled_date date DEFAULT NULL,
            completed_date date DEFAULT NULL,
            project_start_date date DEFAULT NULL,
            related_id bigint(20) DEFAULT NULL,
            related_type varchar(50) DEFAULT NULL,
            attachment_url varchar(500) DEFAULT NULL,
            attachment_name varchar(255) DEFAULT NULL,
            attachment_type varchar(50) DEFAULT NULL,
            migrated_from_propuesta tinyint(1) DEFAULT 0,
            original_detail_id bigint(20) DEFAULT NULL,
            metadata longtext,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY propuesta_origin_id (propuesta_origin_id),
            KEY detail_type (detail_type),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_propuestas);
        dbDelta($sql_clients);
        
        // Agregar columnas de attachment si no existen (para actualizar tablas existentes)
        $this->maybe_add_attachment_columns();
    }
    
    /**
     * Agregar columnas de attachment a tablas existentes
     */
    private function maybe_add_attachment_columns() {
        global $wpdb;
        
        // Verificar y agregar columnas a propuestas_details
        $cols_propuestas = $wpdb->get_col("DESCRIBE {$this->propuestas_details_table}", 0);
        if (!in_array('attachment_url', $cols_propuestas)) {
            $wpdb->query("ALTER TABLE {$this->propuestas_details_table} 
                ADD COLUMN attachment_url varchar(500) DEFAULT NULL AFTER related_type,
                ADD COLUMN attachment_name varchar(255) DEFAULT NULL AFTER attachment_url,
                ADD COLUMN attachment_type varchar(50) DEFAULT NULL AFTER attachment_name");
        }
        
        // Verificar y agregar columnas a clients_details
        $cols_clients = $wpdb->get_col("DESCRIBE {$this->clients_details_table}", 0);
        if (!in_array('attachment_url', $cols_clients)) {
            $wpdb->query("ALTER TABLE {$this->clients_details_table} 
                ADD COLUMN attachment_url varchar(500) DEFAULT NULL AFTER related_type,
                ADD COLUMN attachment_name varchar(255) DEFAULT NULL AFTER attachment_url,
                ADD COLUMN attachment_type varchar(50) DEFAULT NULL AFTER attachment_name");
        }
    }
    
    /**
     * Tipos de detalle disponibles
     */
    public static function get_detail_types() {
        return array(
            'propuesta_enviada' => array(
                'label' => '📄 Propuesta Enviada',
                'icon' => '📄',
                'color' => '#667eea'
            ),
            'cotizacion' => array(
                'label' => '💰 Cotización',
                'icon' => '💰',
                'color' => '#f59e0b'
            ),
            'reunion' => array(
                'label' => '🤝 Reunión',
                'icon' => '🤝',
                'color' => '#10b981'
            ),
            'llamada' => array(
                'label' => '📞 Llamada',
                'icon' => '📞',
                'color' => '#3b82f6'
            ),
            'email' => array(
                'label' => '📧 Email',
                'icon' => '📧',
                'color' => '#8b5cf6'
            ),
            'boleta' => array(
                'label' => '🧾 Boleta',
                'icon' => '🧾',
                'color' => '#06b6d4'
            ),
            'factura' => array(
                'label' => '📋 Factura',
                'icon' => '📋',
                'color' => '#14b8a6'
            ),
            'pago' => array(
                'label' => '💳 Pago Recibido',
                'icon' => '💳',
                'color' => '#22c55e'
            ),
            'item_proyecto' => array(
                'label' => '📦 Item de Proyecto',
                'icon' => '📦',
                'color' => '#ec4899'
            ),
            'entregable' => array(
                'label' => '✅ Entregable',
                'icon' => '✅',
                'color' => '#84cc16'
            ),
            'nota' => array(
                'label' => '📝 Nota',
                'icon' => '📝',
                'color' => '#94a3b8'
            ),
            'seguimiento' => array(
                'label' => '👁️ Seguimiento',
                'icon' => '👁️',
                'color' => '#f97316'
            ),
            'inicio_proyecto' => array(
                'label' => '🚀 Inicio de Proyecto',
                'icon' => '🚀',
                'color' => '#059669'
            ),
            'milestone' => array(
                'label' => '🎯 Hito/Milestone',
                'icon' => '🎯',
                'color' => '#7c3aed'
            ),
            'contratacion' => array(
                'label' => '🎉 Contratación',
                'icon' => '🎉',
                'color' => '#16a34a'
            ),
            // ===== TIPOS CON DOCUMENTOS ADJUNTOS =====
            'presentacion' => array(
                'label' => '🎨 Presentación (Gamma)',
                'icon' => '🎨',
                'color' => '#a855f7',
                'accepts_file' => true
            ),
            'transcripcion' => array(
                'label' => '🎙️ Transcripción Llamada',
                'icon' => '🎙️',
                'color' => '#06b6d4',
                'accepts_file' => true
            ),
            'documento' => array(
                'label' => '📎 Documento Adjunto',
                'icon' => '📎',
                'color' => '#64748b',
                'accepts_file' => true
            ),
            'contrato' => array(
                'label' => '📜 Contrato',
                'icon' => '📜',
                'color' => '#dc2626',
                'accepts_file' => true
            ),
            'acta_reunion' => array(
                'label' => '📋 Acta de Reunión',
                'icon' => '📋',
                'color' => '#0891b2',
                'accepts_file' => true
            ),
            'brief' => array(
                'label' => '📝 Brief/Requerimientos',
                'icon' => '📝',
                'color' => '#ea580c',
                'accepts_file' => true
            ),
            'mockup' => array(
                'label' => '🖼️ Mockup/Diseño',
                'icon' => '🖼️',
                'color' => '#c026d3',
                'accepts_file' => true
            ),
            'informe' => array(
                'label' => '📊 Informe/Reporte',
                'icon' => '📊',
                'color' => '#2563eb',
                'accepts_file' => true
            )
        );
    }
    
    /**
     * Estados disponibles para detalles
     */
    public static function get_statuses() {
        return array(
            'pending' => array('label' => 'Pendiente', 'color' => '#f59e0b'),
            'scheduled' => array('label' => 'Programado', 'color' => '#3b82f6'),
            'in_progress' => array('label' => 'En Progreso', 'color' => '#8b5cf6'),
            'completed' => array('label' => 'Completado', 'color' => '#22c55e'),
            'cancelled' => array('label' => 'Cancelado', 'color' => '#ef4444'),
            'on_hold' => array('label' => 'En Espera', 'color' => '#94a3b8')
        );
    }
    
    /**
     * Agregar detalle a prospecto
     */
    public function add_prospect_detail($propuesta_id, $data) {
        global $wpdb;
        
        $insert_data = array(
            'propuesta_id' => $propuesta_id,
            'detail_type' => sanitize_text_field($data['detail_type']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'amount' => floatval($data['amount'] ?? 0),
            'currency' => sanitize_text_field($data['currency'] ?? 'CLP'),
            'scheduled_date' => !empty($data['scheduled_date']) ? $data['scheduled_date'] : null,
            'completed_date' => !empty($data['completed_date']) ? $data['completed_date'] : null,
            'related_id' => !empty($data['related_id']) ? intval($data['related_id']) : null,
            'related_type' => !empty($data['related_type']) ? sanitize_text_field($data['related_type']) : null,
            'attachment_url' => !empty($data['attachment_url']) ? esc_url_raw($data['attachment_url']) : null,
            'attachment_name' => !empty($data['attachment_name']) ? sanitize_text_field($data['attachment_name']) : null,
            'attachment_type' => !empty($data['attachment_type']) ? sanitize_text_field($data['attachment_type']) : null,
            'metadata' => !empty($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($this->propuestas_details_table, $insert_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Agregar detalle a cliente final
     */
    public function add_client_detail($client_id, $data) {
        global $wpdb;
        
        $insert_data = array(
            'client_id' => $client_id,
            'propuesta_origin_id' => !empty($data['propuesta_origin_id']) ? intval($data['propuesta_origin_id']) : null,
            'detail_type' => sanitize_text_field($data['detail_type']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'amount' => floatval($data['amount'] ?? 0),
            'currency' => sanitize_text_field($data['currency'] ?? 'CLP'),
            'scheduled_date' => !empty($data['scheduled_date']) ? $data['scheduled_date'] : null,
            'completed_date' => !empty($data['completed_date']) ? $data['completed_date'] : null,
            'project_start_date' => !empty($data['project_start_date']) ? $data['project_start_date'] : null,
            'related_id' => !empty($data['related_id']) ? intval($data['related_id']) : null,
            'related_type' => !empty($data['related_type']) ? sanitize_text_field($data['related_type']) : null,
            'attachment_url' => !empty($data['attachment_url']) ? esc_url_raw($data['attachment_url']) : null,
            'attachment_name' => !empty($data['attachment_name']) ? sanitize_text_field($data['attachment_name']) : null,
            'attachment_type' => !empty($data['attachment_type']) ? sanitize_text_field($data['attachment_type']) : null,
            'metadata' => !empty($data['metadata']) ? json_encode($data['metadata']) : null,
            'migrated_from_propuesta' => !empty($data['migrated_from_propuesta']) ? 1 : 0,
            'original_detail_id' => !empty($data['original_detail_id']) ? intval($data['original_detail_id']) : null,
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($this->clients_details_table, $insert_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Obtener detalles de prospecto
     */
    public function get_prospect_details($propuesta_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->propuestas_details_table}
            WHERE propuesta_id = %d
            ORDER BY created_at DESC
        ", $propuesta_id), ARRAY_A);
    }
    
    /**
     * Obtener detalles de cliente final
     */
    public function get_client_details($client_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->clients_details_table}
            WHERE client_id = %d
            ORDER BY created_at DESC
        ", $client_id), ARRAY_A);
    }
    
    /**
     * MIGRAR detalles de prospecto a cliente cuando se contrata
     * Esta es la función clave para mantener la trazabilidad
     */
    public function migrate_details_to_client($client_id, $propuesta_id) {
        global $wpdb;
        
        // 1. Obtener todos los detalles del prospecto
        $prospect_details = $this->get_prospect_details($propuesta_id);
        
        if (empty($prospect_details)) {
            // Si no hay detalles, solo crear el registro de contratación
            $this->add_client_detail($client_id, array(
                'propuesta_origin_id' => $propuesta_id,
                'detail_type' => 'contratacion',
                'title' => 'Cliente Contratado',
                'description' => 'El prospecto se convirtió en cliente.',
                'status' => 'completed',
                'completed_date' => date('Y-m-d')
            ));
            return true;
        }
        
        // 2. Migrar cada detalle a la tabla de clientes
        foreach ($prospect_details as $detail) {
            $migrated_data = array(
                'propuesta_origin_id' => $propuesta_id,
                'detail_type' => $detail['detail_type'],
                'title' => $detail['title'],
                'description' => $detail['description'],
                'status' => $detail['status'],
                'amount' => $detail['amount'],
                'currency' => $detail['currency'],
                'scheduled_date' => $detail['scheduled_date'],
                'completed_date' => $detail['completed_date'],
                'related_id' => $detail['related_id'],
                'related_type' => $detail['related_type'],
                'metadata' => $detail['metadata'] ? json_decode($detail['metadata'], true) : null,
                'migrated_from_propuesta' => true,
                'original_detail_id' => $detail['id']
            );
            
            $this->add_client_detail($client_id, $migrated_data);
        }
        
        // 3. Agregar registro de contratación
        $this->add_client_detail($client_id, array(
            'propuesta_origin_id' => $propuesta_id,
            'detail_type' => 'contratacion',
            'title' => '🎉 Cliente Contratado',
            'description' => 'El prospecto se convirtió en cliente. Se migró todo el historial de seguimiento.',
            'status' => 'completed',
            'completed_date' => date('Y-m-d'),
            'metadata' => array(
                'details_migrated' => count($prospect_details),
                'migration_date' => date('Y-m-d H:i:s')
            )
        ));
        
        // 4. Marcar los detalles del prospecto como migrados (opcional, para auditoría)
        $wpdb->query($wpdb->prepare("
            UPDATE {$this->propuestas_details_table}
            SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.migrated_to_client', %d, '$.migration_date', %s)
            WHERE propuesta_id = %d
        ", $client_id, date('Y-m-d H:i:s'), $propuesta_id));
        
        return true;
    }
    
    /**
     * Obtener resumen de detalles para prospecto
     */
    public function get_prospect_summary($propuesta_id) {
        global $wpdb;
        
        $summary = $wpdb->get_results($wpdb->prepare("
            SELECT 
                detail_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(amount) as total_amount
            FROM {$this->propuestas_details_table}
            WHERE propuesta_id = %d
            GROUP BY detail_type
        ", $propuesta_id), ARRAY_A);
        
        return $summary;
    }
    
    /**
     * Obtener resumen de detalles para cliente
     */
    public function get_client_summary($client_id) {
        global $wpdb;
        
        $summary = $wpdb->get_results($wpdb->prepare("
            SELECT 
                detail_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(amount) as total_amount,
                SUM(CASE WHEN migrated_from_propuesta = 1 THEN 1 ELSE 0 END) as from_prospect
            FROM {$this->clients_details_table}
            WHERE client_id = %d
            GROUP BY detail_type
        ", $client_id), ARRAY_A);
        
        return $summary;
    }
    
    /**
     * AJAX: Obtener detalles de prospecto
     */
    public function ajax_get_prospect_details() {
        check_ajax_referer('client_details_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $propuesta_id = intval($_POST['propuesta_id']);
        $details = $this->get_prospect_details($propuesta_id);
        $summary = $this->get_prospect_summary($propuesta_id);
        
        wp_send_json_success(array(
            'details' => $details,
            'summary' => $summary,
            'types' => self::get_detail_types(),
            'statuses' => self::get_statuses()
        ));
    }
    
    /**
     * AJAX: Guardar detalle de prospecto
     */
    public function ajax_save_prospect_detail() {
        check_ajax_referer('client_details_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $propuesta_id = intval($_POST['propuesta_id']);
        $data = array(
            'detail_type' => sanitize_text_field($_POST['detail_type']),
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'amount' => floatval($_POST['amount'] ?? 0),
            'scheduled_date' => !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null,
            'completed_date' => !empty($_POST['completed_date']) ? $_POST['completed_date'] : null
        );
        
        // Procesar archivo adjunto si existe
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload = $this->handle_file_upload($_FILES['attachment'], 'prospect', $propuesta_id);
            if ($upload) {
                $data['attachment_url'] = $upload['url'];
                $data['attachment_name'] = $upload['name'];
                $data['attachment_type'] = $upload['type'];
                
                // Si hay análisis del documento, guardarlo en metadata
                if (!empty($upload['document_analysis'])) {
                    $metadata = array(
                        'document_analysis' => $upload['document_analysis'],
                        'analyzed_at' => current_time('mysql')
                    );
                    $data['metadata'] = wp_json_encode($metadata);
                }
            }
        }
        
        // Si viene URL de documento existente (ej: link de Gamma)
        if (!empty($_POST['attachment_url'])) {
            $data['attachment_url'] = esc_url_raw($_POST['attachment_url']);
            $data['attachment_name'] = sanitize_text_field($_POST['attachment_name'] ?? 'Documento');
            $data['attachment_type'] = sanitize_text_field($_POST['attachment_type'] ?? 'link');
        }
        
        $result = $this->add_prospect_detail($propuesta_id, $data);
        
        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error('Error al guardar');
        }
    }
    
    /**
     * AJAX: Obtener detalles de cliente (para widget de tracking)
     */
    public function ajax_get_client_tracking_details() {
        check_ajax_referer('client_details_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $client_id = intval($_POST['client_id']);
        $details = $this->get_client_details($client_id);
        $summary = $this->get_client_summary($client_id);
        
        // Obtener fecha de inicio del proyecto si existe
        global $wpdb;
        $project_start = $wpdb->get_var($wpdb->prepare("
            SELECT MIN(project_start_date) 
            FROM {$this->clients_details_table}
            WHERE client_id = %d AND project_start_date IS NOT NULL
        ", $client_id));
        
        wp_send_json_success(array(
            'details' => $details,
            'summary' => $summary,
            'project_start_date' => $project_start,
            'types' => self::get_detail_types(),
            'statuses' => self::get_statuses()
        ));
    }
    
    /**
     * AJAX: Guardar detalle de cliente
     */
    public function ajax_save_client_detail() {
        check_ajax_referer('client_details_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $client_id = intval($_POST['client_id']);
        $data = array(
            'detail_type' => sanitize_text_field($_POST['detail_type']),
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'amount' => floatval($_POST['amount'] ?? 0),
            'scheduled_date' => !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null,
            'completed_date' => !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
            'project_start_date' => !empty($_POST['project_start_date']) ? $_POST['project_start_date'] : null
        );
        
        // Procesar archivo adjunto si existe
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload = $this->handle_file_upload($_FILES['attachment'], 'client', $client_id);
            if ($upload) {
                $data['attachment_url'] = $upload['url'];
                $data['attachment_name'] = $upload['name'];
                $data['attachment_type'] = $upload['type'];
                
                // Si hay análisis del documento, guardarlo en metadata
                if (!empty($upload['document_analysis'])) {
                    $metadata = array(
                        'document_analysis' => $upload['document_analysis'],
                        'analyzed_at' => current_time('mysql')
                    );
                    $data['metadata'] = wp_json_encode($metadata);
                }
            }
        }
        
        // Si viene URL de documento existente
        if (!empty($_POST['attachment_url'])) {
            $data['attachment_url'] = esc_url_raw($_POST['attachment_url']);
            $data['attachment_name'] = sanitize_text_field($_POST['attachment_name'] ?? 'Documento');
            $data['attachment_type'] = sanitize_text_field($_POST['attachment_type'] ?? 'link');
        }
        
        $detail_id = !empty($_POST['detail_id']) ? intval($_POST['detail_id']) : 0;
        
        if ($detail_id > 0) {
            $result = $this->update_client_detail($detail_id, $data);
            $result = $result ? $detail_id : false;
        } else {
            $result = $this->add_client_detail($client_id, $data);
        }
        
        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error('Error al guardar');
        }
    }

    /**
     * Actualizar detalle de cliente final
     */
    public function update_client_detail($detail_id, $data) {
        global $wpdb;
        
        $update_data = array(
            'detail_type' => sanitize_text_field($data['detail_type']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'amount' => floatval($data['amount'] ?? 0),
            'scheduled_date' => !empty($data['scheduled_date']) ? $data['scheduled_date'] : null,
            'completed_date' => !empty($data['completed_date']) ? $data['completed_date'] : null,
            'project_start_date' => !empty($data['project_start_date']) ? $data['project_start_date'] : null,
        );

        if (!empty($data['attachment_url'])) {
            $update_data['attachment_url'] = esc_url_raw($data['attachment_url']);
            $update_data['attachment_name'] = sanitize_text_field($data['attachment_name']);
            $update_data['attachment_type'] = sanitize_text_field($data['attachment_type']);
        }

        if (!empty($data['metadata'])) {
            $update_data['metadata'] = $data['metadata'];
        }
        
        $result = $wpdb->update($this->clients_details_table, $update_data, array('id' => $detail_id));
        
        return $result !== false;
    }
    
    /**
     * Manejar subida de archivos
     */
    private function handle_file_upload($file, $type, $entity_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Crear carpeta específica
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/client-details/' . $type . '/' . $entity_id;
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Tipos permitidos
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/markdown',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        
        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }
        
        // Generar nombre único
        $filename = sanitize_file_name($file['name']);
        $filename = time() . '_' . $filename;
        $target_path = $target_dir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $result = array(
                'url' => $upload_dir['baseurl'] . '/client-details/' . $type . '/' . $entity_id . '/' . $filename,
                'name' => $file['name'],
                'type' => pathinfo($file['name'], PATHINFO_EXTENSION)
            );
            
            // Analizar documento automáticamente según su tipo
            $analysis = $this->analyze_document_content($target_path, $file['type'], $file['name'], $result['url']);
            if ($analysis) {
                $result['document_analysis'] = $analysis;
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Analizar imagen con GPT-4o Vision API
     */
    private function analyze_image_with_vision($image_url, $context = '') {
        $api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : get_option('maxtech_openai_key', '');
        
        if (empty($api_key)) {
            return null;
        }
        
        $data = array(
            'model' => 'gpt-4o-mini',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => "Analiza esta imagen de forma concisa para un sistema CRM. Contexto: documento de cliente llamado '{$context}'. 
                            Extrae:
                            1) Descripción breve (1-2 oraciones)
                            2) Texto visible importante
                            3) Tipo de documento (logo, captura, diseño, contrato, factura, otro)
                            4) Información relevante para el seguimiento del cliente
                            Máximo 200 palabras."
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array('url' => $image_url)
                        )
                    )
                )
            ),
            'max_tokens' => 400
        );
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ));
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return null;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        return null;
    }
    
    /**
     * Analizar contenido de documento según su tipo
     */
    private function analyze_document_content($file_path, $mime_type, $file_name, $url = '') {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Imágenes - usar Vision API
        if (strpos($mime_type, 'image/') === 0) {
            return $this->analyze_image_with_vision($url, $file_name);
        }
        
        // Word (.docx)
        if ($extension === 'docx' || strpos($mime_type, 'wordprocessingml') !== false) {
            return $this->extract_word_content($file_path, $file_name);
        }
        
        // Excel (.xlsx)
        if ($extension === 'xlsx' || strpos($mime_type, 'spreadsheetml') !== false) {
            return $this->extract_excel_content($file_path, $file_name);
        }
        
        // PowerPoint (.pptx)
        if ($extension === 'pptx' || strpos($mime_type, 'presentationml') !== false) {
            return $this->extract_powerpoint_content($file_path, $file_name);
        }
        
        // PDF
        if ($extension === 'pdf' || strpos($mime_type, 'pdf') !== false) {
            return $this->extract_pdf_content($file_path, $file_name);
        }
        
        // Texto plano
        if (strpos($mime_type, 'text/') === 0 || in_array($extension, array('txt', 'md', 'csv'))) {
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                return substr($content, 0, 2000) . (strlen($content) > 2000 ? '...' : '');
            }
        }
        
        return null;
    }
    
    /**
     * Extraer contenido de Word (.docx)
     */
    private function extract_word_content($docx_path, $name = '') {
        if (!file_exists($docx_path) || !class_exists('ZipArchive')) {
            return null;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($docx_path) !== true) {
            return null;
        }
        
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if (empty($content)) {
            return null;
        }
        
        $text = $this->extract_text_from_xml($content);
        return !empty($text) ? "[Word] " . substr($text, 0, 2000) . (strlen($text) > 2000 ? '...' : '') : null;
    }
    
    /**
     * Extraer contenido de Excel (.xlsx)
     */
    private function extract_excel_content($xlsx_path, $name = '') {
        if (!file_exists($xlsx_path) || !class_exists('ZipArchive')) {
            return null;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($xlsx_path) !== true) {
            return null;
        }
        
        // Obtener strings compartidos
        $shared_strings = array();
        $shared_content = $zip->getFromName('xl/sharedStrings.xml');
        if (!empty($shared_content)) {
            $xml = @simplexml_load_string($shared_content);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $shared_strings[] = (string) $si->t;
                }
            }
        }
        
        // Leer hojas
        $all_text = array();
        $sheet_index = 1;
        
        while (($sheet_content = $zip->getFromName("xl/worksheets/sheet{$sheet_index}.xml")) !== false && $sheet_index <= 3) {
            $xml = @simplexml_load_string($sheet_content);
            if ($xml && isset($xml->sheetData)) {
                $rows = array();
                foreach ($xml->sheetData->row as $row) {
                    $cells = array();
                    foreach ($row->c as $cell) {
                        if (isset($cell->v)) {
                            $v = (string) $cell->v;
                            if (isset($cell['t']) && (string) $cell['t'] === 's') {
                                $cells[] = isset($shared_strings[(int) $v]) ? $shared_strings[(int) $v] : '';
                            } else {
                                $cells[] = $v;
                            }
                        }
                    }
                    if (!empty($cells)) {
                        $rows[] = implode(' | ', $cells);
                    }
                }
                if (!empty($rows)) {
                    $all_text[] = implode("\n", array_slice($rows, 0, 30));
                }
            }
            $sheet_index++;
        }
        
        $zip->close();
        
        $text = implode("\n---\n", $all_text);
        return !empty($text) ? "[Excel] " . substr($text, 0, 2000) . (strlen($text) > 2000 ? '...' : '') : null;
    }
    
    /**
     * Extraer contenido de PowerPoint (.pptx)
     */
    private function extract_powerpoint_content($pptx_path, $name = '') {
        if (!file_exists($pptx_path) || !class_exists('ZipArchive')) {
            return null;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($pptx_path) !== true) {
            return null;
        }
        
        $all_text = array();
        $slide_index = 1;
        
        while (($slide_content = $zip->getFromName("ppt/slides/slide{$slide_index}.xml")) !== false && $slide_index <= 20) {
            $text = $this->extract_text_from_xml($slide_content);
            if (!empty($text)) {
                $all_text[] = "Slide {$slide_index}: {$text}";
            }
            $slide_index++;
        }
        
        $zip->close();
        
        $text = implode("\n", $all_text);
        return !empty($text) ? "[PowerPoint] " . substr($text, 0, 2500) . (strlen($text) > 2500 ? '...' : '') : null;
    }
    
    /**
     * Extraer contenido de PDF
     */
    private function extract_pdf_content($pdf_path, $name = '') {
        if (!file_exists($pdf_path)) {
            return null;
        }
        
        // Intentar con pdftotext si está disponible
        if (function_exists('shell_exec')) {
            $output = @shell_exec("pdftotext -layout -nopgbrk \"$pdf_path\" - 2>/dev/null");
            if (!empty($output)) {
                return "[PDF] " . substr(trim($output), 0, 2000) . (strlen($output) > 2000 ? '...' : '');
            }
        }
        
        // Método básico: buscar texto en el archivo
        $content = file_get_contents($pdf_path);
        preg_match_all('/\((.*?)\)/', $content, $matches);
        
        if (!empty($matches[1])) {
            $text = implode(' ', array_filter($matches[1], function($t) {
                return strlen($t) > 2 && preg_match('/[a-zA-Z]/', $t);
            }));
            if (strlen($text) > 50) {
                return "[PDF] " . substr($text, 0, 2000) . (strlen($text) > 2000 ? '...' : '');
            }
        }
        
        return null;
    }
    
    /**
     * Extraer texto de XML (para docx, pptx)
     */
    private function extract_text_from_xml($xml_content) {
        // Limpiar namespaces
        $xml_content = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_content);
        $xml_content = preg_replace('/<[a-z0-9]+:/i', '<', $xml_content);
        $xml_content = preg_replace('/<\/[a-z0-9]+:/i', '</', $xml_content);
        
        // Extraer texto de tags <t>
        preg_match_all('/<t[^>]*>([^<]*)<\/t>/i', $xml_content, $matches);
        
        if (!empty($matches[1])) {
            $text = implode(' ', $matches[1]);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            return preg_replace('/\s+/', ' ', trim($text));
        }
        
        return '';
    }
    
    /**
     * AJAX: Obtener historial completo (propuesta + cliente si existe)
     */
    public function ajax_get_detail_history() {
        check_ajax_referer('client_details_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        global $wpdb;
        
        $propuesta_id = !empty($_POST['propuesta_id']) ? intval($_POST['propuesta_id']) : null;
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
        
        $history = array();
        
        // Si es cliente, buscar si tiene propuesta asociada
        if ($client_id && !$propuesta_id) {
            // Buscar propuesta origin de los detalles
            $propuesta_id = $wpdb->get_var($wpdb->prepare("
                SELECT propuesta_origin_id FROM {$this->clients_details_table}
                WHERE client_id = %d AND propuesta_origin_id IS NOT NULL
                LIMIT 1
            ", $client_id));
        }
        
        // Obtener detalles de prospecto (si existe)
        if ($propuesta_id) {
            $prospect_details = $this->get_prospect_details($propuesta_id);
            foreach ($prospect_details as $d) {
                $d['source'] = 'prospect';
                $d['source_label'] = '📋 Fase Comercial';
                $history[] = $d;
            }
        }
        
        // Obtener detalles de cliente (si existe)
        if ($client_id) {
            $client_details = $this->get_client_details($client_id);
            foreach ($client_details as $d) {
                $d['source'] = 'client';
                $d['source_label'] = '✅ Fase Proyecto';
                $history[] = $d;
            }
        }
        
        // Ordenar por fecha
        usort($history, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        wp_send_json_success(array(
            'history' => $history,
            'propuesta_id' => $propuesta_id,
            'client_id' => $client_id,
            'types' => self::get_detail_types(),
            'statuses' => self::get_statuses()
        ));
    }
    
    /**
     * AJAX: Eliminar detalle
     */
    public function ajax_delete_detail() {
        check_ajax_referer('client_details_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        global $wpdb;
        
        $detail_id = intval($_POST['detail_id']);
        $source = sanitize_text_field($_POST['source']); // 'prospect' o 'client'
        
        if (!$detail_id || !in_array($source, array('prospect', 'client'))) {
            wp_send_json_error('Parámetros inválidos');
        }
        
        $table = ($source === 'prospect') 
            ? $this->propuestas_details_table 
            : $this->clients_details_table;
        
        $result = $wpdb->delete($table, array('id' => $detail_id), array('%d'));
        
        if ($result) {
            wp_send_json_success(array('deleted' => $detail_id));
        } else {
            wp_send_json_error('No se pudo eliminar');
        }
    }
    
    /**
     * Renderizar widget de detalles para prospecto
     */
    public static function render_prospect_details_widget($propuesta_id) {
        $nonce = wp_create_nonce('client_details_nonce');
        ?>
        <div class="client-details-widget prospect-details" data-propuesta-id="<?php echo esc_attr($propuesta_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="cdw-header">
                <h3>📋 Historial de Seguimiento</h3>
                <button type="button" class="button button-primary add-detail-btn">+ Agregar</button>
            </div>
            <div class="cdw-summary"></div>
            <div class="cdw-timeline"></div>
        </div>
        <?php
        self::render_styles_and_scripts();
    }
    
    /**
     * Renderizar widget de detalles para cliente
     */
    public static function render_client_details_widget($client_id) {
        $nonce = wp_create_nonce('client_details_nonce');
        ?>
        <div class="client-details-widget client-details" data-client-id="<?php echo esc_attr($client_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="cdw-header">
                <h3>📊 Historial del Proyecto</h3>
                <div class="cdw-project-start">
                    <span class="project-start-label">🚀 Inicio:</span>
                    <span class="project-start-date">-</span>
                </div>
                <button type="button" class="button button-primary add-detail-btn">+ Agregar</button>
            </div>
            <div class="cdw-summary"></div>
            <div class="cdw-timeline"></div>
        </div>
        <?php
        self::render_styles_and_scripts();
    }
    
    /**
     * Estilos y scripts del widget
     */
    public static function render_styles_and_scripts() {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;
        ?>
        <style>
        .client-details-widget {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        .cdw-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .cdw-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1f2937;
        }
        .cdw-project-start {
            background: #ecfdf5;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #059669;
        }
        .cdw-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .cdw-summary-item {
            background: #f3f4f6;
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cdw-summary-item .count {
            font-weight: 600;
            color: #1f2937;
        }
        .cdw-timeline {
            border-left: 3px solid #e5e7eb;
            padding-left: 20px;
            margin-left: 10px;
        }
        .cdw-timeline-item {
            position: relative;
            padding: 12px 15px;
            margin-bottom: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .cdw-timeline-item::before {
            content: '';
            position: absolute;
            left: -29px;
            top: 15px;
            width: 12px;
            height: 12px;
            background: #667eea;
            border-radius: 50%;
            border: 2px solid #fff;
        }
        .cdw-timeline-item.migrated {
            border-left-color: #94a3b8;
            background: #f1f5f9;
        }
        .cdw-timeline-item.migrated::before {
            background: #94a3b8;
        }
        .cdw-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .cdw-item-type {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            background: #667eea;
            color: #fff;
        }
        .cdw-item-date {
            font-size: 11px;
            color: #6b7280;
        }
        .cdw-item-title {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .cdw-item-desc {
            font-size: 13px;
            color: #4b5563;
            line-height: 1.4;
        }
        .cdw-item-meta {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
        }
        .cdw-item-status {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }
        .cdw-item-amount {
            font-weight: 600;
            color: #059669;
        }
        .cdw-migrated-badge {
            font-size: 10px;
            background: #e2e8f0;
            color: #475569;
            padding: 2px 6px;
            border-radius: 8px;
        }
        .cdw-item-attachment {
            margin-top: 8px;
            padding: 8px 12px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
        }
        .cdw-attachment-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .cdw-attachment-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .cdw-empty {
            text-align: center;
            padding: 30px;
            color: #9ca3af;
        }
        
        /* Modal para agregar detalle */
        .cdw-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            justify-content: center;
            align-items: center;
        }
        .cdw-modal.active {
            display: flex;
        }
        .cdw-modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            max-width: 500px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .cdw-modal h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
        }
        .cdw-form-group {
            margin-bottom: 15px;
        }
        .cdw-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .cdw-form-group input,
        .cdw-form-group select,
        .cdw-form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .cdw-form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .cdw-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .cdw-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        /* ========== RESPONSIVE ========== */
        @media screen and (max-width: 767px) {
            .client-details-widget {
                padding: 15px;
            }
            .cdw-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .cdw-header h3 {
                font-size: 15px;
            }
            .cdw-summary {
                flex-direction: column;
            }
            .cdw-timeline {
                padding-left: 15px;
                margin-left: 5px;
            }
            .cdw-timeline-item {
                padding: 10px 12px;
            }
            .cdw-timeline-item::before {
                left: -24px;
                width: 10px;
                height: 10px;
            }
            .cdw-item-header {
                flex-direction: column;
                gap: 5px;
            }
            .cdw-item-meta {
                flex-wrap: wrap;
            }
            .cdw-form-row {
                grid-template-columns: 1fr;
            }
            .cdw-modal-content {
                padding: 20px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .client-details-widget {
                padding: 10px;
            }
            .cdw-header h3 {
                font-size: 14px;
            }
            .cdw-timeline-item {
                padding: 8px 10px;
            }
            .cdw-item-title {
                font-size: 13px;
            }
            .cdw-item-desc {
                font-size: 12px;
            }
        }
        
        @media (hover: none) and (pointer: coarse) {
            .cdw-form-group input,
            .cdw-form-group select,
            .cdw-form-group textarea,
            .cdw-modal-actions .button {
                min-height: 44px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            var detailTypes = <?php echo json_encode(self::get_detail_types()); ?>;
            var statuses = <?php echo json_encode(self::get_statuses()); ?>;
            
            // Cargar detalles de prospecto
            $('.prospect-details').each(function() {
                var widget = $(this);
                var propuestaId = widget.data('propuesta-id');
                var nonce = widget.data('nonce');
                
                loadProspectDetails(widget, propuestaId, nonce);
            });
            
            // Cargar detalles de cliente
            $('.client-details').each(function() {
                var widget = $(this);
                var clientId = widget.data('client-id');
                var nonce = widget.data('nonce');
                
                loadClientDetails(widget, clientId, nonce);
            });
            
            function loadProspectDetails(widget, propuestaId, nonce) {
                $.post(ajaxurl, {
                    action: 'get_prospect_tracking_details',
                    propuesta_id: propuestaId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        renderDetails(widget, response.data.details, response.data.summary, 'prospect');
                    }
                });
            }
            
            function loadClientDetails(widget, clientId, nonce) {
                $.post(ajaxurl, {
                    action: 'get_client_tracking_details',
                    client_id: clientId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        renderDetails(widget, response.data.details, response.data.summary, 'client');
                        if (response.data.project_start_date) {
                            widget.find('.project-start-date').text(formatDate(response.data.project_start_date));
                        }
                    }
                });
            }
            
            function renderDetails(widget, details, summary, type) {
                var summaryHtml = '';
                var timelineHtml = '';
                
                // Render summary
                if (summary && summary.length > 0) {
                    summary.forEach(function(s) {
                        var typeInfo = detailTypes[s.detail_type] || {icon: '📌', label: s.detail_type};
                        summaryHtml += '<div class="cdw-summary-item">';
                        summaryHtml += '<span>' + typeInfo.icon + '</span>';
                        summaryHtml += '<span class="count">' + s.total + '</span>';
                        summaryHtml += '<span>' + typeInfo.label.replace(typeInfo.icon + ' ', '') + '</span>';
                        summaryHtml += '</div>';
                    });
                }
                widget.find('.cdw-summary').html(summaryHtml);
                
                // Render timeline
                if (details && details.length > 0) {
                    details.forEach(function(d) {
                        var typeInfo = detailTypes[d.detail_type] || {icon: '📌', label: d.detail_type, color: '#667eea'};
                        var statusInfo = statuses[d.status] || {label: d.status, color: '#94a3b8'};
                        var isMigrated = d.migrated_from_propuesta == 1;
                        var isConversion = d.detail_type === 'contratacion';
                        var hasAttachment = d.attachment_url && d.attachment_url.length > 0;
                        
                        if (isConversion) {
                            timelineHtml += '<div class="cdw-timeline-item conversion-event" style="background: #f0fdf4; border: 2px solid #22c55e; border-left-width: 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin: 25px 0;">';
                            timelineHtml += '<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px; color: #15803d; font-weight: bold; font-size: 16px;">';
                            timelineHtml += '🎉 Convertido a Cliente el ' + formatDate(d.completed_date) + ' 🎉';
                            timelineHtml += '</div>';
                        } else {
                            timelineHtml += '<div class="cdw-timeline-item' + (isMigrated ? ' migrated' : '') + '" style="border-left-color: ' + typeInfo.color + '">';
                        }

                        timelineHtml += '<div class="cdw-item-header">';
                        timelineHtml += '<div>';
                        timelineHtml += '<span class="cdw-item-type" style="background:' + typeInfo.color + '">' + typeInfo.icon + ' ' + typeInfo.label.replace(typeInfo.icon + ' ', '') + '</span>';
                        timelineHtml += ' <span class="cdw-item-date">' + formatDateTime(d.created_at) + '</span>';
                        timelineHtml += '</div>';
                        var detailJson = JSON.stringify(d).replace(/'/g, "&#39;");
                        timelineHtml += '<button type="button" class="cdw-btn-icon edit-detail-btn" data-detail=\'' + detailJson + '\' style="border:none;background:none;cursor:pointer;font-size:16px;" title="Editar">✏️</button>';
                        timelineHtml += '</div>';
                        timelineHtml += '<div class="cdw-item-title">' + escapeHtml(d.title) + '</div>';
                        if (d.description) {
                            timelineHtml += '<div class="cdw-item-desc">' + escapeHtml(d.description) + '</div>';
                        }

                        // Mostrar documento adjunto si existe
                        if (hasAttachment) {
                            var attachName = d.attachment_name || 'Ver documento';
                            var attachIcon = getAttachmentIcon(d.attachment_type);
                            timelineHtml += '<div class="cdw-item-attachment">';
                            timelineHtml += '<a href="' + d.attachment_url + '" target="_blank" class="cdw-attachment-link">';
                            timelineHtml += attachIcon + ' ' + escapeHtml(attachName);
                            timelineHtml += '</a>';
                            timelineHtml += '</div>';
                        }
                        timelineHtml += '<div class="cdw-item-meta">';
                        timelineHtml += '<span class="cdw-item-status" style="background:' + statusInfo.color + '20; color:' + statusInfo.color + '">' + statusInfo.label + '</span>';
                        if (d.amount > 0) {
                            timelineHtml += '<span class="cdw-item-amount">$' + Number(d.amount).toLocaleString('es-CL') + ' ' + d.currency + '</span>';
                        }
                        if (isMigrated) {
                            timelineHtml += '<span class="cdw-migrated-badge">📋 Desde seguimiento</span>';
                        }
                        
                        // Parsear metadata si existe
                        var meta = {};
                        if (d.metadata) {
                            try {
                                meta = (typeof d.metadata === 'string') ? JSON.parse(d.metadata) : d.metadata;
                            } catch(e) {}
                        }
                        
                        if (meta.notified_email) {
                            timelineHtml += '<span class="cdw-notified-badge" title="Notificado el ' + formatDateTime(meta.notified_at) + '" style="font-size: 11px; background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 8px; display: inline-flex; align-items: center; gap: 3px;">📧 Notificado</span>';
                        }
                        
                        timelineHtml += '</div>';
                        timelineHtml += '</div>';
                    });
                } else {
                    timelineHtml = '<div class="cdw-empty">No hay registros aún</div>';
                }
                widget.find('.cdw-timeline').html(timelineHtml);
            }
            
            function getAttachmentIcon(type) {
                var icons = {
                    'pdf': '📕',
                    'doc': '📘',
                    'docx': '📘',
                    'xls': '📗',
                    'xlsx': '📗',
                    'txt': '📄',
                    'md': '📝',
                    'jpg': '🖼️',
                    'jpeg': '🖼️',
                    'png': '🖼️',
                    'gif': '🖼️',
                    'link': '🔗'
                };
                return icons[type] || '📎';
            }
            
            function formatDate(dateStr) {
                if (!dateStr) return '-';
                var d = new Date(dateStr);
                return d.toLocaleDateString('es-CL');
            }
            
            function formatDateTime(dateStr) {
                if (!dateStr) return '-';
                var d = new Date(dateStr);
                return d.toLocaleDateString('es-CL') + ' ' + d.toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'});
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Agregar detalle (modal)
            $(document).on('click', '.add-detail-btn', function() {
                var widget = $(this).closest('.client-details-widget');
                var isClient = widget.hasClass('client-details');
                var id = isClient ? widget.data('client-id') : widget.data('propuesta-id');
                var nonce = widget.data('nonce');
                
                showAddDetailModal(widget, isClient, id, nonce);
            });
            
            // Editar detalle (modal)
            $(document).on('click', '.edit-detail-btn', function() {
                var widget = $(this).closest('.client-details-widget');
                var isClient = widget.hasClass('client-details');
                var id = isClient ? widget.data('client-id') : widget.data('propuesta-id');
                var nonce = widget.data('nonce');
                var detail = $(this).data('detail');
                
                showAddDetailModal(widget, isClient, id, nonce, detail);
            });
            
            function showAddDetailModal(widget, isClient, id, nonce, editData) {
                var typeOptions = '';
                var selectedType = editData ? editData.detail_type : '';
                
                Object.keys(detailTypes).forEach(function(key) {
                    var selected = (key === selectedType) ? 'selected' : '';
                    typeOptions += '<option value="' + key + '" ' + selected + '>' + detailTypes[key].icon + ' ' + detailTypes[key].label.replace(detailTypes[key].icon + ' ', '') + '</option>';
                });
                
                var statusOptions = '';
                var selectedStatus = editData ? editData.status : '';
                Object.keys(statuses).forEach(function(key) {
                    var selected = (key === selectedStatus) ? 'selected' : '';
                    statusOptions += '<option value="' + key + '" ' + selected + '>' + statuses[key].label + '</option>';
                });

                var valTitle = editData ? escapeHtml(editData.title).replace(/"/g, "&quot;") : '';
                var valDesc = editData ? escapeHtml(editData.description) : '';
                var valAmount = editData ? (editData.amount == 0 ? '' : editData.amount) : '';
                var valScheduled = editData && editData.scheduled_date ? editData.scheduled_date : '';
                var valCompleted = editData && editData.completed_date ? editData.completed_date : '';
                var valProjectStart = editData && editData.project_start_date ? editData.project_start_date : '';
                var valAttUrl = editData && editData.attachment_url ? editData.attachment_url : '';

                var modalTitle = editData ? '✏️ Editar Registro' : (isClient ? '📊 Agregar Registro al Proyecto' : '📋 Agregar Seguimiento');
                
                var modalHtml = '<div class="cdw-modal active" id="cdw-add-modal">';
                modalHtml += '<div class="cdw-modal-content">';
                modalHtml += '<h3>' + modalTitle + '</h3>';
                modalHtml += '<form id="cdw-add-form" enctype="multipart/form-data">';
                if (editData) {
                    modalHtml += '<input type="hidden" name="detail_id" value="' + editData.id + '">';
                }
                modalHtml += '<div class="cdw-form-group"><label>Tipo</label><select name="detail_type" id="cdw-detail-type" required>' + typeOptions + '</select></div>';
                modalHtml += '<div class="cdw-form-group"><label>Título</label><input type="text" name="title" required value="' + valTitle + '" placeholder="Ej: Reunión inicial, Pago recibido..."></div>';
                modalHtml += '<div class="cdw-form-group"><label>Descripción</label><textarea name="description" placeholder="Detalles adicionales, notas de reunión, transcripción...">' + valDesc + '</textarea></div>';
                modalHtml += '<div class="cdw-form-row">';
                modalHtml += '<div class="cdw-form-group"><label>Estado</label><select name="status">' + statusOptions + '</select></div>';
                modalHtml += '<div class="cdw-form-group"><label>Monto (opcional)</label><input type="number" name="amount" value="' + valAmount + '" placeholder="0"></div>';
                modalHtml += '</div>';
                modalHtml += '<div class="cdw-form-row">';
                modalHtml += '<div class="cdw-form-group"><label>Fecha Programada</label><input type="date" name="scheduled_date" value="' + valScheduled + '"></div>';
                modalHtml += '<div class="cdw-form-group"><label>Fecha Completado</label><input type="date" name="completed_date" value="' + valCompleted + '"></div>';
                modalHtml += '</div>';
                if (isClient) {
                    modalHtml += '<div class="cdw-form-group"><label>🚀 Fecha Inicio Proyecto</label><input type="date" name="project_start_date" value="' + valProjectStart + '"></div>';
                }
                // Sección de documentos adjuntos
                modalHtml += '<div class="cdw-attachment-section" style="margin-top: 15px; padding: 15px; background: #f0f9ff; border: 1px dashed #0ea5e9; border-radius: 8px;">';
                modalHtml += '<h4 style="margin: 0 0 10px 0; color: #0369a1; font-size: 14px;">📎 Documento Adjunto (opcional)</h4>';
                modalHtml += '<div class="cdw-form-group"><label>Subir archivo</label><input type="file" name="attachment" accept=".pdf,.doc,.docx,.txt,.md,.jpg,.jpeg,.png,.gif,.xls,.xlsx"></div>';
                modalHtml += '<div class="cdw-form-group"><label>O pegar URL (Gamma, Google Docs, etc.)</label><input type="url" name="attachment_url" placeholder="https://..."></div>';
                modalHtml += '<input type="hidden" name="attachment_name" value="">';
                modalHtml += '<input type="hidden" name="attachment_type" value="link">';
                modalHtml += '<p style="font-size: 12px; color: #64748b; margin: 8px 0 0 0;">Formatos: PDF, Word, Excel, TXT, Markdown, Imágenes</p>';
                modalHtml += '</div>';
                modalHtml += '<div class="cdw-modal-actions">';
                modalHtml += '<button type="button" class="button cdw-cancel-btn">Cancelar</button>';
                modalHtml += '<button type="submit" class="button button-primary">Guardar</button>';
                modalHtml += '</div>';
                modalHtml += '</form>';
                modalHtml += '</div></div>';
                
                $('body').append(modalHtml);
                
                $('#cdw-add-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = new FormData(this);
                    formData.append('nonce', nonce);
                    formData.append(isClient ? 'client_id' : 'propuesta_id', id);
                    formData.append('action', isClient ? 'save_client_tracking_detail' : 'save_prospect_tracking_detail');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                $('#cdw-add-modal').remove();
                                if (isClient) {
                                    loadClientDetails(widget, id, nonce);
                                } else {
                                    loadProspectDetails(widget, id, nonce);
                                }
                            } else {
                                alert('Error: ' + (response.data || 'No se pudo guardar'));
                            }
                        },
                        error: function() {
                            alert('Error de conexión');
                        }
                    });
                });
                
                $('.cdw-cancel-btn, #cdw-add-modal').on('click', function(e) {
                    if (e.target === this) {
                        $('#cdw-add-modal').remove();
                    }
                });
            }
            
        });
        </script>
        <?php
    }
}

// Inicializar
new AutomatizaTech_Client_Details();

/**
 * Helper functions para usar en otros archivos
 */
function automatiza_render_prospect_details($propuesta_id) {
    AutomatizaTech_Client_Details::render_prospect_details_widget($propuesta_id);
}

function automatiza_render_client_details($client_id) {
    AutomatizaTech_Client_Details::render_client_details_widget($client_id);
}

/**
 * Trigger para migrar detalles cuando se contrata un cliente
 * Llamar esto desde contact-form.php cuando se mueve a clientes
 */
function automatiza_migrate_prospect_to_client($client_id, $propuesta_id) {
    $details_module = new AutomatizaTech_Client_Details();
    return $details_module->migrate_details_to_client($client_id, $propuesta_id);
}
