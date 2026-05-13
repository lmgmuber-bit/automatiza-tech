<?php
/**
 * Plugin Name: AutomatizaTech CRM & AI
 * Description: Gestión de clientes, prospectos y asistente IA para AutomatizaTech.
 * Version: 2.1
 * Author: AutomatizaTech
 */

if (!defined('ABSPATH')) exit;

// Asegurarse de que WP_List_Table esté disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (!class_exists('AutomatizaTech_Clientes_List_Table')) {
    class AutomatizaTech_Clientes_List_Table extends WP_List_Table {
        
        public function get_columns() {
            return [
                'cb' => '<input type="checkbox" />',
                'tipo' => 'Tipo',
                'estado' => 'Estado',
                'nombre' => 'Nombre',
                'empresa' => 'Empresa',
                'email' => 'Email',
                'telefono' => 'Teléfono',
                'ai_identifier' => 'AI Identifier',
                'fecha_contacto' => 'Fecha Contacto',
                'acciones' => 'Acciones'
            ];
        }
        
        public function prepare_items() {
            global $wpdb;
            $table_clientes = $wpdb->prefix . 'crm_clientes';
            $table_propuestas = $wpdb->prefix . 'automatiza_propuestas';
            
            $columns = $this->get_columns();
            $hidden = [];
            $sortable = ['nombre' => ['nombre', true], 'fecha_contacto' => ['fecha_contacto', false]];
            $this->_column_headers = [$columns, $hidden, $sortable];
            
            $per_page = 20;
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;
            
            // Consulta UNION para traer clientes y propuestas no convertidas
            $sql = "
                SELECT 
                    id, 
                    tipo, 
                    estado, 
                    nombre, 
                    empresa, 
                    email, 
                    telefono, 
                    ai_identifier, 
                    fecha_contacto, 
                    'cliente' as origen_tabla
                FROM $table_clientes
                
                UNION ALL
                
                SELECT 
                    id, 
                    'prospecto' as tipo, 
                    'propuesta' as estado, 
                    client_name as nombre, 
                    company_name as empresa, 
                    client_email as email, 
                    phone as telefono, 
                    unique_link_id as ai_identifier, 
                    created_at as fecha_contacto,
                    'propuesta' as origen_tabla
                FROM $table_propuestas
                WHERE client_email NOT IN (SELECT email FROM $table_clientes)
                
                ORDER BY fecha_contacto DESC
                LIMIT $offset, $per_page
            ";
            
            // Contar total para paginación
            $total_sql = "
                SELECT COUNT(*) FROM (
                    SELECT email FROM $table_clientes
                    UNION ALL
                    SELECT client_email FROM $table_propuestas WHERE client_email NOT IN (SELECT email FROM $table_clientes)
                ) as total
            ";
            
            $total_items = $wpdb->get_var($total_sql);
            
            // Asegurar que offset no sea negativo
            if ($offset < 0) $offset = 0;
            
            $this->items = $wpdb->get_results($sql, ARRAY_A);
            
            $this->set_pagination_args([
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => ceil($total_items / $per_page)
            ]);
        }
        
        public function column_default($item, $column_name) {
            $val = is_array($item) ? ($item[$column_name] ?? '') : ($item->$column_name ?? '');
            return esc_html($val);
        }
        
        public function column_cb($item) {
            $id = is_array($item) ? $item['id'] : $item->id;
            return sprintf('<input type="checkbox" name="cliente[]" value="%s" />', $id);
        }
        
        public function column_tipo($item) {
            $val = is_array($item) ? $item['tipo'] : $item->tipo;
            $colors = ['prospecto' => '#17a2b8', 'cliente' => '#28a745', 'cerrado' => '#6c757d'];
            $color = $colors[$val] ?? '#17a2b8';
            return sprintf('<span style="background:%s; color:#fff; padding:3px 8px; border-radius:10px; font-size:11px;">%s</span>', $color, ucfirst($val));
        }
        
        public function column_estado($item) {
            $val = is_array($item) ? $item['estado'] : $item->estado;
            return sprintf('<span class="crm-badge">%s</span>', ucfirst($val));
        }
        
        public function column_acciones($item) {
            $id = is_array($item) ? $item['id'] : $item->id;
            $origen = is_array($item) ? ($item['origen_tabla'] ?? '') : ($item->origen_tabla ?? '');
            $tipo = is_array($item) ? $item['tipo'] : $item->tipo;
            
            // ACL Check for Designer
            $cu = wp_get_current_user();
            $is_designer = ($cu->user_email === 'Adriana.perez@automatizatech.cl');

            $actions = [];
            
            if ($origen === 'cliente') {
                $actions[] = sprintf('<a href="?page=automatiza-crm-ficha&id=%s" class="button button-small">Ver Ficha</a>', $id);
                // Botón Informe IA
                $actions[] = sprintf(
                    '<a href="%s" class="button button-small" style="background:#0d6efd;color:white;border-color:#0d6efd;" target="_blank"><span class="dashicons dashicons-media-document" style="font-size:14px;line-height:26px;"></span> Informe IA</a>',
                    admin_url('admin-ajax.php?action=download_client_report_pdf&client_id=' . $id)
                );
                
                if (!$is_designer) {
                    /* Botón oculto - se mueve a otro módulo
                    if ($tipo === 'cliente') {
                        // Botón para regenerar y reenviar factura
                        $actions[] = sprintf(
                            '<button type="button" class="button button-primary button-small regenerate-invoice-btn" data-id="%s" data-nonce="%s" style="background-color:#f59e0b;border-color:#d97706;">Regenerar Factura</button>',
                            $id,
                            wp_create_nonce('regenerate_invoice_' . $id)
                        );
                    }
                    */
                    
                    if ($tipo === 'prospecto') {
                        $actions[] = sprintf('<button type="button" class="button button-small button-convertir" data-id="%s" data-type="cliente">Convertir a Cliente</button>', $item['id']);
                    }
                }
            } else {
                if (!$is_designer) {
                    $actions[] = sprintf('<button type="button" class="button button-small button-convertir" data-id="%s" data-type="propuesta" style="background:#826eb4;color:white;">Importar & Convertir</button>', $id);
                }
            }
            
            return implode(' ', $actions);
        }
    }
}

class AutomatizaTech_CRM_AI {
    
    private $tabla_clientes;
    private $tabla_historial; // Historial de eventos de negocio
    private $tabla_chat_historial; // Historial de chat (mensajes)
    private $tabla_proyectos;
    private $tabla_ai;
    
    public function __construct() {
        global $wpdb;
        $this->tabla_clientes = $wpdb->prefix . 'crm_clientes';
        $this->tabla_historial = $wpdb->prefix . 'crm_historial';
        $this->tabla_chat_historial = $wpdb->prefix . 'crm_chat_historial';
        $this->tabla_proyectos = $wpdb->prefix . 'crm_proyectos';
        $this->tabla_ai = $wpdb->prefix . 'ai_usage_log';
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_crm_buscar', [$this, 'ajax_buscar']);
        add_action('wp_ajax_crm_agente_consulta', [$this, 'ajax_agente_consulta']);
        add_action('wp_ajax_crm_guardar_nota', [$this, 'ajax_guardar_nota']);
        add_action('wp_ajax_crm_enviar_notificacion_historial', [$this, 'crm_enviar_notificacion_historial']);
        add_action('wp_ajax_ajax_convertir_cliente', [$this, 'ajax_convertir_cliente']);
        add_action('wp_ajax_crm_convertir_propuesta', [$this, 'ajax_convertir_propuesta']);
        add_action('wp_ajax_crm_crear_proyecto', [$this, 'ajax_crear_proyecto']);
        add_action('wp_ajax_crm_actualizar_proyecto', [$this, 'ajax_actualizar_proyecto']);
        add_action('wp_ajax_crm_update_timeline_item', [$this, 'ajax_update_timeline_item']);
        add_action('wp_ajax_crm_agendar_seguimiento', [$this, 'ajax_agendar_seguimiento']);
        add_action('wp_ajax_crm_eliminar_seguimiento', [$this, 'ajax_eliminar_seguimiento']);
        
        // Public Timeline View
        add_action('template_redirect', [$this, 'render_public_timeline']);
        add_action('template_redirect', [$this, 'render_public_prospect_timeline']);
        
        // Chat Público Cliente
        add_action('wp_ajax_nopriv_crm_chat_cliente', [$this, 'ajax_chat_cliente']);
        add_action('wp_ajax_crm_chat_cliente', [$this, 'ajax_chat_cliente']);
        add_action('wp_ajax_nopriv_crm_chat_history', [$this, 'ajax_crm_recover_chat_history']);
        add_action('wp_ajax_crm_chat_history', [$this, 'ajax_crm_recover_chat_history']);
        
        // Crear tablas si no existen
        add_action('admin_init', [$this, 'crear_tablas']);
    }
    
    public function crear_tablas() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        // Tabla de clientes/prospectos unificada
        $sql1 = "CREATE TABLE {$this->tabla_clientes} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo ENUM('prospecto', 'cliente', 'cerrado') DEFAULT 'prospecto',
            estado VARCHAR(50) DEFAULT 'nuevo',
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            telefono VARCHAR(50),
            empresa VARCHAR(255),
            rubro VARCHAR(100),
            origen VARCHAR(100),
            ai_identifier VARCHAR(100),
            notas TEXT,
            logo_url VARCHAR(500),
            manual_url VARCHAR(500),
            color_principal VARCHAR(20),
            color_secundario_1 VARCHAR(20),
            color_secundario_2 VARCHAR(20),
            tipografia VARCHAR(100),
            drive_folder_id VARCHAR(100),
            fecha_contacto DATETIME,
            fecha_demo DATETIME,
            fecha_contrato DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_tipo (tipo),
            KEY idx_estado (estado),
            KEY idx_ai_id (ai_identifier)
        ) $charset";
        
        // Tabla de historial/timeline
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->tabla_historial} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT(20) UNSIGNED NOT NULL,
            tipo_evento VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT,
            metadata JSON,
            usuario_id BIGINT(20) UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cliente (cliente_id),
            INDEX idx_tipo (tipo_evento)
        ) $charset";
        
        // Tabla de proyectos
        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->tabla_proyectos} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT(20) UNSIGNED NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            estado ENUM('pendiente', 'en_progreso', 'completado', 'pausado', 'cancelado') DEFAULT 'pendiente',
            tipo_servicio VARCHAR(100),
            precio_acordado DECIMAL(10,2),
            moneda VARCHAR(10) DEFAULT 'USD',
            fecha_inicio DATE,
            fecha_entrega DATE,
            repositorio_url VARCHAR(500),
            credenciales TEXT,
            notas TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cliente (cliente_id),
            INDEX idx_estado (estado)
        ) $charset";
        
        // Tabla de CHAT historial (Añadido para MaxTech Cliente)
        $sql4 = "CREATE TABLE IF NOT EXISTS {$this->tabla_chat_historial} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(100) NOT NULL,
            user_id BIGINT(20) UNSIGNED,
            rol ENUM('user', 'assistant') NOT NULL,
            mensaje TEXT NOT NULL,
            archivos JSON,
            audio_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_user (user_id)
        ) $charset";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }
    
    public function add_admin_menu() {
        // Menú principal - Acceso para Editor (diseñadora) o Admin
        $cap = 'edit_posts'; // Permitimos editores
        
        add_menu_page(
            'CRM AutomatizaTech',
            'CRM Clientes',
            $cap,
            'automatiza-crm',
            [$this, 'render_dashboard'],
            'dashicons-businessperson',
            26
        );
        
        // Use $cap (edit_posts) for Dashboard and Client List so Adriana can see them
        add_submenu_page('automatiza-crm', 'Dashboard', 'Dashboard', $cap, 'automatiza-crm', [$this, 'render_dashboard']);
        add_submenu_page('automatiza-crm', 'Clientes', 'Clientes', $cap, 'automatiza-crm-clientes', [$this, 'render_clientes_page']);
        
        // Others remain for Admins only
        add_submenu_page('automatiza-crm', 'Búsqueda Avanzada', '🔍 Búsqueda Avanzada', 'manage_options', 'automatiza-crm-busqueda', [$this, 'render_busqueda_avanzada']);
        add_submenu_page('automatiza-crm', 'Ficha de Cliente: Gestión del Servicio y Métricas', 'Ficha de Cliente', $cap, 'automatiza-crm-ficha', [$this, 'render_ficha_cliente']);
        add_submenu_page('automatiza-crm', 'Consumo AI', 'Consumo AI', 'manage_options', 'automatiza-crm-ai', [$this, 'render_consumo_ai']);
        add_submenu_page('automatiza-crm', 'MAXTECH', '🤖 MAXTECH', 'manage_options', 'automatiza-crm-agente', [$this, 'render_agente']);
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'automatiza-crm') === false) return;
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
        wp_enqueue_style('automatiza-crm-css', plugins_url('assets/crm-style.css', __FILE__));
        wp_localize_script('jquery', 'crmAjax', ['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('crm_nonce')]);
    }
    
    // ========== LISTA CLIENTES/PROSPECTOS ==========
    public function render_clientes_page() {
        if (!current_user_can('edit_posts')) wp_die('No tienes permisos.');
        
        if (isset($_GET['mensaje'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($_GET['mensaje']) . '</p></div>';
        }
        // TEST PROD 2026-01-29
        if (isset($_GET['testprod'])) {
            echo '<div style="background:#06d6a0;color:#fff;padding:10px 20px;font-size:18px;border-radius:8px;margin:20px 0;">TEST PROD 2026-01-29 ACTIVO</div>';
        }
        ?>
        <div id="modalConversion" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4); align-items: center; justify-content: center;">
            <div style="background-color:#fefefe; padding:20px; border-radius: 8px; border:1px solid #888; width:90%; max-width: 500px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
                <h2 id="modalConversionTitle" style="margin-top:0;"></h2>
                <p id="modalConversionBody"></p>
                <p><label><input type="checkbox" id="modalEnviarBienvenida" checked> Enviar correo de bienvenida</label></p>
                <div style="text-align:right;">
                    <button type="button" id="modalConversionCancelar" class="button">Cancelar</button>
                    <button type="button" id="modalConversionConfirmar" class="button button-primary">Confirmar</button>
                </div>
            </div>
        </div>
        <div class="wrap crm-wrap">
            <h1 class="wp-heading-inline">Gestión de Clientes y Prospectos</h1>
            <a href="?page=automatiza-crm-ficha" class="page-title-action">➕ Nuevo Cliente</a>
            
            <?php
            $lista = new AutomatizaTech_Clientes_List_Table();
            $lista->prepare_items();
            $lista->display();
            ?>
        </div>
        
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('modalConversion');
            const btnConfirmar = document.getElementById('modalConversionConfirmar');
            const btnCancelar = document.getElementById('modalConversionCancelar');
            
            // Cerrar modal
            btnCancelar.addEventListener('click', () => modal.style.display = 'none');
            window.addEventListener('click', (event) => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });

            // Abrir modal
            document.querySelectorAll('.button-convertir').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-type') || 'cliente';
                    const msg = type === 'propuesta' ? '¿Estás seguro de que deseas importar esta propuesta y crear un nuevo cliente a partir de ella?' : '¿Estás seguro de que deseas convertir este prospecto en cliente?';
                    
                    document.getElementById('modalConversionTitle').textContent = 'Confirmar Conversión';
                    document.getElementById('modalConversionBody').textContent = msg;
                    
                    // Store data in the confirm button
                    btnConfirmar.setAttribute('data-id', id);
                    btnConfirmar.setAttribute('data-type', type);
                    
                    modal.style.display = 'flex';
                });
            });

            // Acción de confirmar
            btnConfirmar.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                const enviarBienvenida = document.getElementById('modalEnviarBienvenida').checked;

                const action = (type === 'propuesta') ? 'crm_convertir_propuesta' : 'ajax_convertir_cliente';
                const idParam = (type === 'propuesta') ? 'propuesta_id' : 'id';
                const nonceName = (type === 'propuesta') ? 'crm_nonce' : 'convertir_cliente_nonce';
                
                // We need to get the nonce value from the localized script if available, or generate it.
                // As we can't easily access the PHP nonces here without a more complex setup, let's create a hidden field or data attribute.
                // For now, let's assume a generic nonce is available via a JS variable. The plugin already localizes crmAjax.nonce. We will use that.
                
                const data = new URLSearchParams();
                data.append('action', action);
                data.append(idParam, id);
                data.append('enviar_bienvenida', enviarBienvenida);
                // Nonce is critical. Let's create a hidden input or find it.
                // The original code used different nonces. We'll try to find them or use a general one.
                // A better approach would be to add the nonce to the button data attributes in PHP.
                // Let's assume the main crm_nonce is sufficient for both actions for simplicity now.
                data.append('nonce', '<?php echo wp_create_nonce("crm_nonce"); ?>');
                
                // The original code used two different nonces, let's adjust for that.
                const nonceValue = (type === 'propuesta') 
                    ? '<?php echo wp_create_nonce("crm_nonce"); ?>'
                    : '<?php echo wp_create_nonce("convertir_cliente_nonce"); ?>';
                data.append('_ajax_nonce', nonceValue);
                
                const postData = {
                    action: action,
                    id: id,
                    propuesta_id: id,
                    enviar_bienvenida: enviarBienvenida,
                    nonce: nonceValue
                };

                jQuery.post(ajaxurl, postData, function(response) {
                    // Check if response is JSON
                    if (typeof response === 'object' && response.success) {
                         location.reload();
                    } else if (typeof response === 'string' && response.includes('success')) {
                         location.reload();
                    } else {
                        const errorMessage = (typeof response === 'object' && response.data) ? response.data : 'Ocurrió un error desconocido.';
                        alert('Error: ' + errorMessage);
                    }
                }).fail(function() {
                    alert('Error de conexión al intentar realizar la conversión.');
                });

                modal.style.display = 'none';
            });

            // Acción para regenerar factura
            document.querySelectorAll('.regenerate-invoice-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm('¿Estás seguro de que deseas regenerar y reenviar la factura a este cliente?')) {
                        return;
                    }
                    
                    const id = this.getAttribute('data-id');
                    const nonce = this.getAttribute('data-nonce');
                    
                    this.textContent = 'Enviando...';
                    this.disabled = true;

                    jQuery.post(ajaxurl, {
                        action: 'regenerate_and_resend_invoice',
                        id: id,
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            alert(response.data);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            btn.textContent = 'Regenerar Factura';
                            btn.disabled = false;
                        }
                    }).fail(function() {
                        alert('Error de conexión.');
                        btn.textContent = 'Regenerar Factura';
                        btn.disabled = false;
                    });
                });
            });
        });
        </script>

        <style>
            .button-convertir {
                background: #46b450;
                color: #fff;
                margin-left: 5px;
                border-color: #3a9d40;
            }
            .button-convertir:hover {
                background: #3a9d40;
                color: #fff;
            }
        </style>
        <?php
        $this->render_styles();
    }

    public function ajax_convertir_cliente() {
        check_ajax_referer('convertir_cliente_nonce', 'nonce');
        // Check for edit_posts capability
        if (!current_user_can('edit_posts')) {
            wp_die('forbidden');
        }
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_die('error');
        }
        $res = $wpdb->update($this->tabla_clientes, 
            [ 'tipo' => 'cliente', 'estado' => 'contratado', 'fecha_contrato' => current_time('mysql') ], 
            ['id' => $id]
        );
        if ($res !== false) {
            if (isset($_POST['enviar_bienvenida']) && $_POST['enviar_bienvenida'] === 'true') {
                $this->_enviar_correo_bienvenida($id);
            }
            echo 'success';
        } else {
            echo 'error';
        }
        wp_die();
    }

    public function ajax_convertir_propuesta() {
        check_ajax_referer('crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) wp_die('forbidden');
        
        global $wpdb;
        $propuesta_id = intval($_POST['propuesta_id']);
        
        // Obtener datos de la propuesta
        $tabla_propuestas = $wpdb->prefix . 'automatiza_propuestas';
        $propuesta = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla_propuestas WHERE id = %d", $propuesta_id));
        
        if (!$propuesta) wp_send_json_error('Propuesta no encontrada');
        
        // Verificar si ya existe el cliente por email
        $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->tabla_clientes} WHERE email = %s", $propuesta->client_email));
        
        if ($existe) {
            // Si ya existe, actualizamos a cliente
            $wpdb->update($this->tabla_clientes, [
                'tipo' => 'cliente',
                'estado' => 'contratado',
                'fecha_contrato' => current_time('mysql')
            ], ['id' => $existe]);
            $cliente_id = $existe;
        } else {
            // Si no existe, creamos nuevo cliente
            $wpdb->insert($this->tabla_clientes, [
                'nombre' => $propuesta->client_name,
                'email' => $propuesta->client_email,
                'empresa' => $propuesta->company_name,
                'telefono' => $propuesta->phone,
                'tipo' => 'cliente',
                'estado' => 'contratado',
                'fecha_contrato' => current_time('mysql'),
                'fecha_contacto' => $propuesta->created_at,
                'origen' => 'propuesta_web'
            ]);
            $cliente_id = $wpdb->insert_id;
        }
        
        // Registrar evento en historial
        $wpdb->insert($this->tabla_historial, [
            'cliente_id' => $cliente_id,
            'tipo_evento' => 'conversion',
            'titulo' => 'Conversión desde Propuesta',
            'descripcion' => "Cliente convertido desde propuesta ID #{$propuesta->unique_link_id}",
            'usuario_id' => get_current_user_id()
        ]);
        
        if (isset($_POST['enviar_bienvenida']) && $_POST['enviar_bienvenida'] === 'true') {
            $this->_enviar_correo_bienvenida($cliente_id);
        }
        
        wp_send_json_success(['cliente_id' => $cliente_id]);
    }
    
    // ========== DASHBOARD (CONSUMO AI) ==========
    public function render_dashboard() {
        if (!current_user_can('edit_posts')) wp_die('No tienes permisos.');

        global $wpdb;
        
        $mes = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('m'));
        $anio = isset($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
        $cliente_filtro = isset($_GET['cliente']) ? sanitize_text_field($_GET['cliente']) : '';
        $modo = isset($_GET['modo']) ? sanitize_text_field($_GET['modo']) : 'mensual'; // mensual | comparar
        $mes2 = isset($_GET['mes2']) ? intval($_GET['mes2']) : ($mes == 1 ? 12 : $mes - 1);
        $anio2 = isset($_GET['anio2']) ? intval($_GET['anio2']) : ($mes == 1 ? $anio - 1 : $anio);
        
        // Nombres de meses en español
        $meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        // Filtro SQL principal
        $where = "WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d";
        $params = [$mes, $anio];
        
        if ($cliente_filtro && $cliente_filtro != 'Todos') {
            $where .= " AND client_identifier = %s";
            $params[] = $cliente_filtro;
        }
        
        // KPIs mes actual
        $kpis = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as peticiones,
                COALESCE(SUM(total_tokens), 0) as tokens,
                COALESCE(SUM(cost_estimated), 0) as costo
            FROM {$this->tabla_ai}
            $where
        ", ...$params), ARRAY_A);
        
        // Comparativa mes anterior automática
        $mes_ant = $mes == 1 ? 12 : $mes - 1;
        $anio_ant = $mes == 1 ? $anio - 1 : $anio;
        $costo_ant = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(cost_estimated), 0) 
            FROM {$this->tabla_ai} 
            WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d
        ", $mes_ant, $anio_ant));
        
        $variacion = $costo_ant > 0 ? (($kpis['costo'] - $costo_ant) / $costo_ant) * 100 : 0;
        $var_class = $variacion >= 0 ? 'red' : 'green';
        $var_symbol = $variacion >= 0 ? '▲' : '▼';
        
        // ========== DATOS DIARIOS (todos los días del mes) ==========
        $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $hoy_dia = ($mes == date('n') && $anio == date('Y')) ? intval(date('j')) : $dias_en_mes;
        
        // Query datos reales agrupados por día
        $where_diario = "WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d";
        $params_diario = [$mes, $anio];
        if ($cliente_filtro && $cliente_filtro != 'Todos') {
            $where_diario .= " AND client_identifier = %s";
            $params_diario[] = $cliente_filtro;
        }
        
        $dataDiariaRaw = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DAY(created_at) as dia,
                SUM(cost_estimated) as costo, 
                SUM(total_tokens) as tokens,
                COUNT(*) as peticiones
            FROM {$this->tabla_ai}
            $where_diario
            GROUP BY DAY(created_at)
            ORDER BY dia ASC
        ", ...$params_diario), ARRAY_A);
        
        // Indexar por día
        $dataPorDia = [];
        foreach ($dataDiariaRaw as $d) {
            $dataPorDia[intval($d['dia'])] = $d;
        }
        
        // Generar array completo (todos los días del 1 al último)
        $labelsD = [];
        $costosD = [];
        $tokensD = [];
        $peticionesD = [];
        for ($d = 1; $d <= $hoy_dia; $d++) {
            $labelsD[] = $d;
            $costosD[] = isset($dataPorDia[$d]) ? round(floatval($dataPorDia[$d]['costo']), 6) : 0;
            $tokensD[] = isset($dataPorDia[$d]) ? intval($dataPorDia[$d]['tokens']) : 0;
            $peticionesD[] = isset($dataPorDia[$d]) ? intval($dataPorDia[$d]['peticiones']) : 0;
        }
        
        // ========== DATOS PARA COMPARACIÓN MES vs MES ==========
        $compData = null;
        if ($modo === 'comparar') {
            $dias_mes2 = cal_days_in_month(CAL_GREGORIAN, $mes2, $anio2);
            $hoy_dia2 = ($mes2 == date('n') && $anio2 == date('Y')) ? intval(date('j')) : $dias_mes2;
            
            $where_comp = "WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d";
            $params_comp = [$mes2, $anio2];
            if ($cliente_filtro && $cliente_filtro != 'Todos') {
                $where_comp .= " AND client_identifier = %s";
                $params_comp[] = $cliente_filtro;
            }
            
            $dataComp = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    DAY(created_at) as dia,
                    SUM(cost_estimated) as costo, 
                    SUM(total_tokens) as tokens,
                    COUNT(*) as peticiones
                FROM {$this->tabla_ai}
                $where_comp
                GROUP BY DAY(created_at)
                ORDER BY dia ASC
            ", ...$params_comp), ARRAY_A);
            
            $kpis2 = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as peticiones,
                    COALESCE(SUM(total_tokens), 0) as tokens,
                    COALESCE(SUM(cost_estimated), 0) as costo
                FROM {$this->tabla_ai}
                $where_comp
            ", ...$params_comp), ARRAY_A);
            
            $compPorDia = [];
            foreach ($dataComp as $d) {
                $compPorDia[intval($d['dia'])] = $d;
            }
            
            $maxDias = max($hoy_dia, $hoy_dia2);
            $compLabels = [];
            $compCostos1 = [];
            $compCostos2 = [];
            $compTokens1 = [];
            $compTokens2 = [];
            for ($d = 1; $d <= $maxDias; $d++) {
                $compLabels[] = $d;
                $compCostos1[] = ($d <= $hoy_dia && isset($dataPorDia[$d])) ? round(floatval($dataPorDia[$d]['costo']), 6) : 0;
                $compCostos2[] = ($d <= $hoy_dia2 && isset($compPorDia[$d])) ? round(floatval($compPorDia[$d]['costo']), 6) : 0;
                $compTokens1[] = ($d <= $hoy_dia && isset($dataPorDia[$d])) ? intval($dataPorDia[$d]['tokens']) : 0;
                $compTokens2[] = ($d <= $hoy_dia2 && isset($compPorDia[$d])) ? intval($compPorDia[$d]['tokens']) : 0;
            }
            
            // Acumulados para gráfico de línea acumulada
            $acum1 = []; $acum2 = [];
            $sum1 = 0; $sum2 = 0;
            for ($d = 0; $d < $maxDias; $d++) {
                $sum1 += $compCostos1[$d];
                $sum2 += $compCostos2[$d];
                $acum1[] = round($sum1, 6);
                $acum2[] = round($sum2, 6);
            }
            
            $compData = [
                'labels' => $compLabels,
                'costos1' => $compCostos1,
                'costos2' => $compCostos2,
                'tokens1' => $compTokens1,
                'tokens2' => $compTokens2,
                'acum1' => $acum1,
                'acum2' => $acum2,
                'kpis2' => $kpis2,
                'label1' => $meses_es[$mes] . ' ' . $anio,
                'label2' => $meses_es[$mes2] . ' ' . $anio2,
            ];
        }
        
        // ========== DATOS POR HORA (heatmap del día) ==========
        $dataPorHora = $wpdb->get_results($wpdb->prepare("
            SELECT 
                HOUR(created_at) as hora,
                COUNT(*) as peticiones,
                SUM(total_tokens) as tokens
            FROM {$this->tabla_ai}
            $where_diario
            GROUP BY HOUR(created_at)
            ORDER BY hora ASC
        ", ...$params_diario), ARRAY_A);
        
        $horasLabels = [];
        $horasData = [];
        for ($h = 0; $h < 24; $h++) {
            $horasLabels[] = sprintf('%02d:00', $h);
            $found = false;
            foreach ($dataPorHora as $dh) {
                if (intval($dh['hora']) === $h) {
                    $horasData[] = intval($dh['peticiones']);
                    $found = true;
                    break;
                }
            }
            if (!$found) $horasData[] = 0;
        }
        
        // Top Clientes
        $dataClientes = $wpdb->get_results($wpdb->prepare("
            SELECT 
                l.client_identifier, 
                COUNT(*) as requests, 
                SUM(l.total_tokens) as tokens, 
                SUM(l.cost_estimated) as costo,
                c.tipo as tipo_crm
            FROM {$this->tabla_ai} l
            LEFT JOIN {$this->tabla_clientes} c ON l.client_identifier = c.ai_identifier
            WHERE MONTH(l.created_at) = %d AND YEAR(l.created_at) = %d
            GROUP BY l.client_identifier
            ORDER BY costo DESC
            LIMIT 10
        ", $mes, $anio), ARRAY_A);
        
        // Lista clientes para filtro
        $clientes_list = $wpdb->get_col("SELECT DISTINCT client_identifier FROM {$this->tabla_ai} ORDER BY client_identifier ASC");
        
        // Meses con datos (para el selector inteligente)
        $meses_con_datos = $wpdb->get_results("
            SELECT DISTINCT MONTH(created_at) as mes, YEAR(created_at) as anio 
            FROM {$this->tabla_ai} 
            ORDER BY anio DESC, mes DESC
        ", ARRAY_A);
        
        ?>
        <div class="wrap crm-wrap">
            <h1>📊 Dashboard CRM - Consumo AI</h1>
            
            <!-- Pestañas de modo -->
            <div class="dash-mode-tabs">
                <a href="?page=automatiza-crm&modo=mensual&mes=<?php echo $mes; ?>&anio=<?php echo $anio; ?>&cliente=<?php echo urlencode($cliente_filtro); ?>" 
                   class="dash-mode-tab <?php echo $modo !== 'comparar' ? 'active' : ''; ?>">
                    📅 Vista Mensual
                </a>
                <a href="?page=automatiza-crm&modo=comparar&mes=<?php echo $mes; ?>&anio=<?php echo $anio; ?>&mes2=<?php echo $mes2; ?>&anio2=<?php echo $anio2; ?>&cliente=<?php echo urlencode($cliente_filtro); ?>" 
                   class="dash-mode-tab <?php echo $modo === 'comparar' ? 'active' : ''; ?>">
                    🔄 Comparar Meses
                </a>
            </div>
            
            <!-- Filtros -->
            <div class="crm-filters">
                <form method="get" id="formFiltros">
                    <input type="hidden" name="page" value="automatiza-crm">
                    <input type="hidden" name="modo" value="<?php echo esc_attr($modo); ?>">
                    
                    <div class="filtro-grupo">
                        <label><?php echo $modo === 'comparar' ? 'Mes A:' : 'Mes:'; ?></label>
                        <select name="mes">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php selected($m, $mes); ?>><?php echo $meses_es[$m]; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="anio">
                            <?php for ($y = 2024; $y <= intval(date('Y')); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php selected($y, $anio); ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <?php if ($modo === 'comparar'): ?>
                    <div class="filtro-grupo filtro-comparar">
                        <label>vs Mes B:</label>
                        <select name="mes2">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php selected($m, $mes2); ?>><?php echo $meses_es[$m]; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="anio2">
                            <?php for ($y = 2024; $y <= intval(date('Y')); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php selected($y, $anio2); ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filtro-grupo">
                        <label>Cliente:</label>
                        <select name="cliente">
                            <option value="Todos">Todos</option>
                            <?php foreach ($clientes_list as $c): ?>
                                <option value="<?php echo esc_attr($c); ?>" <?php selected($cliente_filtro, $c); ?>>
                                    <?php echo esc_html(str_replace(['cliente_', 'demo_', '_'], ['', '', ' '], $c)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="button button-primary">🔍 Filtrar</button>
                    <a href="?page=automatiza-crm" class="button">✖ Limpiar</a>
                </form>
            </div>
            
            <!-- KPIs -->
            <div class="crm-kpis">
                <div class="kpi-card total">
                    <div class="kpi-icon">📈</div>
                    <div>
                        <div class="kpi-value"><?php echo number_format($kpis['peticiones']); ?></div>
                        <div class="kpi-label">Peticiones</div>
                    </div>
                </div>
                <div class="kpi-card blue">
                    <div class="kpi-icon">🔢</div>
                    <div>
                        <div class="kpi-value"><?php echo number_format($kpis['tokens']); ?></div>
                        <div class="kpi-label">Tokens</div>
                    </div>
                </div>
                <div class="kpi-card green">
                    <div class="kpi-icon">💰</div>
                    <div>
                        <div class="kpi-value">$<?php echo number_format($kpis['costo'] * 1.3, 2); ?></div>
                        <div class="kpi-label">A Facturar (+30%)</div>
                    </div>
                </div>
                <div class="kpi-card <?php echo $var_class; ?>">
                    <div class="kpi-icon">📊</div>
                    <div>
                        <div class="kpi-value"><?php echo $var_symbol . number_format(abs($variacion), 1); ?>%</div>
                        <div class="kpi-label">vs Mes Anterior</div>
                    </div>
                </div>
                <div class="kpi-card total">
                    <div class="kpi-icon">💵</div>
                    <div>
                        <div class="kpi-value">$<?php echo number_format($kpis['costo'], 4); ?></div>
                        <div class="kpi-label">Costo OpenAI</div>
                    </div>
                </div>
            </div>
            
            <?php if ($modo === 'comparar' && $compData): ?>
            <!-- ===== MODO COMPARAR ===== -->
            
            <!-- KPIs Comparativa -->
            <div class="comp-kpis-wrapper">
                <div class="comp-kpi-col">
                    <h4 style="color:#0073aa;">📅 <?php echo $compData['label1']; ?></h4>
                    <div class="comp-kpi-row">
                        <span><strong><?php echo number_format($kpis['peticiones']); ?></strong> peticiones</span>
                        <span><strong><?php echo number_format($kpis['tokens']); ?></strong> tokens</span>
                        <span><strong>$<?php echo number_format($kpis['costo'], 4); ?></strong> costo</span>
                        <span style="color:#46b450;"><strong>$<?php echo number_format($kpis['costo'] * 1.3, 2); ?></strong> facturar</span>
                    </div>
                </div>
                <div class="comp-vs">VS</div>
                <div class="comp-kpi-col">
                    <h4 style="color:#dc3232;">📅 <?php echo $compData['label2']; ?></h4>
                    <div class="comp-kpi-row">
                        <span><strong><?php echo number_format($compData['kpis2']['peticiones']); ?></strong> peticiones</span>
                        <span><strong><?php echo number_format($compData['kpis2']['tokens']); ?></strong> tokens</span>
                        <span><strong>$<?php echo number_format($compData['kpis2']['costo'], 4); ?></strong> costo</span>
                        <span style="color:#46b450;"><strong>$<?php echo number_format($compData['kpis2']['costo'] * 1.3, 2); ?></strong> facturar</span>
                    </div>
                </div>
                <?php 
                    $diff_costo = $kpis['costo'] - $compData['kpis2']['costo'];
                    $diff_pct = $compData['kpis2']['costo'] > 0 ? ($diff_costo / $compData['kpis2']['costo']) * 100 : 0;
                    $diff_color = $diff_costo >= 0 ? '#dc3232' : '#46b450';
                    $diff_icon = $diff_costo >= 0 ? '▲' : '▼';
                ?>
                <div class="comp-resultado" style="border-color: <?php echo $diff_color; ?>;">
                    <span style="color:<?php echo $diff_color; ?>; font-size:20px; font-weight:700;">
                        <?php echo $diff_icon . ' ' . number_format(abs($diff_pct), 1); ?>%
                    </span>
                    <small>Diferencia: $<?php echo number_format(abs($diff_costo), 4); ?></small>
                </div>
            </div>
            
            <!-- Gráfico Comparativo Diario -->
            <div class="ficha-card full-width">
                <h3>📊 Comparativa Diaria: <?php echo $compData['label1']; ?> vs <?php echo $compData['label2']; ?></h3>
                <div style="height: 350px;">
                    <canvas id="chartComparativo"></canvas>
                </div>
            </div>
            
            <!-- Gráfico Acumulado -->
            <div class="ficha-card full-width">
                <h3>📈 Costo Acumulado: <?php echo $compData['label1']; ?> vs <?php echo $compData['label2']; ?></h3>
                <div style="height: 300px;">
                    <canvas id="chartAcumulado"></canvas>
                </div>
            </div>
            
            <?php else: ?>
            <!-- ===== MODO MENSUAL (DIARIO) ===== -->
            
            <div class="ficha-grid">
                <!-- Gráfico Diario Costo -->
                <div class="ficha-card full-width">
                    <h3>📅 Consumo Diario - <?php echo $meses_es[$mes] . ' ' . $anio; ?></h3>
                    <div style="height: 350px;">
                        <canvas id="chartDiario"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="ficha-grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Distribución por hora -->
                <div class="ficha-card">
                    <h3>🕐 Actividad por Hora del Día</h3>
                    <div style="height: 250px;">
                        <canvas id="chartHoras"></canvas>
                    </div>
                </div>
                
                <!-- Gráfico Clientes -->
                <div class="ficha-card">
                    <h3>👥 Distribución por Cliente</h3>
                    <div style="height: 250px;">
                        <canvas id="chartClientes"></canvas>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            
            <!-- Tabla Top Clientes (siempre visible) -->
            <div class="ficha-card full-width">
                <h3>🏆 Top Clientes - <?php echo $meses_es[$mes] . ' ' . $anio; ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Peticiones</th>
                            <th>Tokens</th>
                            <th>Costo</th>
                            <th>Facturar</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dataClientes)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px; color:#999;">Sin datos para este período</td></tr>
                        <?php endif; ?>
                        <?php foreach ($dataClientes as $c): 
                            $tipo_label = 'Nuevo';
                            $bg_color = '#f0ad4e';
                            
                            if (!empty($c['tipo_crm'])) {
                                $tipo_label = ucfirst($c['tipo_crm']);
                                $bg_color = $c['tipo_crm'] == 'cliente' ? '#46b450' : '#2271b1';
                            } elseif (strpos($c['client_identifier'], 'interno_') === 0) {
                                $tipo_label = 'Interno';
                                $bg_color = '#6c757d';
                            } elseif (strpos($c['client_identifier'], 'demo_') === 0) {
                                $tipo_label = 'Demo';
                                $bg_color = '#17a2b8';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(str_replace(['cliente_', 'demo_', '_'], ['', '', ' '], $c['client_identifier'])); ?></strong>
                                <br><small style="color:#999;"><?php echo $c['client_identifier']; ?></small>
                            </td>
                            <td>
                                <span style="background:<?php echo $bg_color; ?>; color:white; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                                    <?php echo $tipo_label; ?>
                                </span>
                            </td>
                            <td><?php echo $c['requests']; ?></td>
                            <td><?php echo number_format($c['tokens']); ?></td>
                            <td>$<?php echo number_format($c['costo'], 4); ?></td>
                            <td style="color:#46b450;font-weight:bold;">$<?php echo number_format($c['costo'] * 1.3, 2); ?></td>
                            <td>
                                <a href="?page=automatiza-crm-ficha&ai_id=<?php echo urlencode($c['client_identifier']); ?>" class="button button-small">Ver Ficha</a>
                                <a href="?page=automatiza-crm&cliente=<?php echo urlencode($c['client_identifier']); ?>&mes=<?php echo $mes; ?>&anio=<?php echo $anio; ?>" class="button button-small">Filtrar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            var modo = '<?php echo $modo; ?>';
            
            if (modo === 'comparar') {
                // ===== GRÁFICO COMPARATIVO BARRAS =====
                var compData = <?php echo json_encode($compData ?? []); ?>;
                
                new Chart(document.getElementById('chartComparativo'), {
                    type: 'bar',
                    data: {
                        labels: compData.labels.map(d => 'Día ' + d),
                        datasets: [{
                            label: compData.label1 + ' (Costo USD)',
                            data: compData.costos1,
                            backgroundColor: 'rgba(0,115,170,0.7)',
                            borderColor: '#0073aa',
                            borderWidth: 1,
                            barPercentage: 0.4,
                            categoryPercentage: 0.8,
                            order: 2
                        }, {
                            label: compData.label2 + ' (Costo USD)',
                            data: compData.costos2,
                            backgroundColor: 'rgba(220,50,50,0.5)',
                            borderColor: '#dc3232',
                            borderWidth: 1,
                            barPercentage: 0.4,
                            categoryPercentage: 0.8,
                            order: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return ctx.dataset.label + ': $' + ctx.parsed.y.toFixed(6);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                title: { display: true, text: 'Costo USD' },
                                ticks: { callback: v => '$' + v.toFixed(4) }
                            }
                        }
                    }
                });
                
                // ===== GRÁFICO ACUMULADO =====
                new Chart(document.getElementById('chartAcumulado'), {
                    type: 'line',
                    data: {
                        labels: compData.labels.map(d => 'Día ' + d),
                        datasets: [{
                            label: compData.label1 + ' (Acumulado)',
                            data: compData.acum1,
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0,115,170,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            borderWidth: 2.5
                        }, {
                            label: compData.label2 + ' (Acumulado)',
                            data: compData.acum2,
                            borderColor: '#dc3232',
                            backgroundColor: 'rgba(220,50,50,0.08)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            borderWidth: 2.5,
                            borderDash: [6, 3]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return ctx.dataset.label + ': $' + ctx.parsed.y.toFixed(4);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                title: { display: true, text: 'Costo Acumulado USD' },
                                ticks: { callback: v => '$' + v.toFixed(4) }
                            }
                        }
                    }
                });
                
            } else {
                // ===== GRÁFICO DIARIO (MODO MENSUAL) =====
                new Chart(document.getElementById('chartDiario'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($labelsD); ?>.map(d => 'Día ' + d),
                        datasets: [{
                            label: 'Costo USD',
                            data: <?php echo json_encode($costosD); ?>,
                            backgroundColor: function(ctx) {
                                var val = ctx.parsed ? ctx.parsed.y : 0;
                                return val > 0 ? 'rgba(0,115,170,0.75)' : 'rgba(200,200,200,0.3)';
                            },
                            borderColor: 'rgba(0,115,170,1)',
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 2
                        }, {
                            label: 'Tokens',
                            data: <?php echo json_encode($tokensD); ?>,
                            type: 'line',
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70,180,80,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: function(ctx) {
                                return ctx.parsed && ctx.parsed.y > 0 ? 4 : 0;
                            },
                            borderWidth: 2,
                            yAxisID: 'y1',
                            order: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    afterBody: function(items) {
                                        var idx = items[0].dataIndex;
                                        var pets = <?php echo json_encode($peticionesD); ?>;
                                        return 'Peticiones: ' + pets[idx];
                                    },
                                    label: function(ctx) {
                                        if (ctx.dataset.yAxisID === 'y') return 'Costo: $' + ctx.parsed.y.toFixed(6);
                                        return 'Tokens: ' + ctx.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                type: 'linear', position: 'left', beginAtZero: true,
                                title: { display: true, text: 'Costo USD' },
                                ticks: { callback: v => '$' + v.toFixed(4) }
                            },
                            y1: { 
                                type: 'linear', position: 'right', beginAtZero: true,
                                grid: { drawOnChartArea: false }, 
                                title: { display: true, text: 'Tokens' } 
                            }
                        }
                    }
                });
                
                // ===== GRÁFICO ACTIVIDAD POR HORA =====
                new Chart(document.getElementById('chartHoras'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($horasLabels); ?>,
                        datasets: [{
                            label: 'Peticiones',
                            data: <?php echo json_encode($horasData); ?>,
                            backgroundColor: function(ctx) {
                                var v = ctx.parsed ? ctx.parsed.y : 0;
                                var max = Math.max(...<?php echo json_encode($horasData); ?>);
                                var intensity = max > 0 ? (v / max) : 0;
                                return 'rgba(255,185,0,' + (0.3 + intensity * 0.7) + ')';
                            },
                            borderColor: '#ffb900',
                            borderWidth: 1,
                            borderRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Peticiones' }, ticks: { stepSize: 1 } }
                        }
                    }
                });
                
                // ===== GRÁFICO CLIENTES (DOUGHNUT) =====
                var clientesData = <?php echo json_encode($dataClientes); ?>;
                if (clientesData.length > 0) {
                    new Chart(document.getElementById('chartClientes'), {
                        type: 'doughnut',
                        data: {
                            labels: clientesData.map(c => c.client_identifier.replace('cliente_', '').replace(/_/g, ' ')),
                            datasets: [{
                                data: clientesData.map(c => parseFloat(c.costo)),
                                backgroundColor: ['#0073aa', '#46b450', '#dc3232', '#ffb900', '#00a0d2', '#826eb4', '#f56e28', '#1e1e1e', '#50575e', '#72aee6']
                            }]
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false,
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            return ctx.label + ': $' + ctx.parsed.toFixed(4);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        });
        </script>
        
        <style>
        .dash-mode-tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 3px solid #0073aa; }
        .dash-mode-tab { 
            padding: 12px 24px; text-decoration: none; color: #50575e; font-weight: 600; font-size: 14px;
            background: #f0f0f1; border: 1px solid #c3c4c7; border-bottom: none; border-radius: 6px 6px 0 0;
            margin-right: 2px; transition: all 0.2s;
        }
        .dash-mode-tab:hover { background: #e2e4e7; color: #1d2327; }
        .dash-mode-tab.active { 
            background: #fff; color: #0073aa; border-color: #0073aa; 
            border-bottom: 3px solid #fff; margin-bottom: -3px; 
        }
        
        .crm-filters { background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .crm-filters form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .filtro-grupo { display: flex; align-items: center; gap: 6px; }
        .filtro-grupo label { font-weight: 600; color: #1d2327; white-space: nowrap; }
        .filtro-comparar { background: #fef3cd; padding: 6px 12px; border-radius: 6px; border: 1px solid #ffc107; }
        .filtro-comparar label { color: #856404; }
        
        .comp-kpis-wrapper { 
            display: flex; align-items: center; gap: 16px; background: #fff; padding: 20px; 
            border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); flex-wrap: wrap;
        }
        .comp-kpi-col { flex: 1; min-width: 250px; }
        .comp-kpi-col h4 { margin: 0 0 8px; font-size: 16px; }
        .comp-kpi-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .comp-kpi-row span { font-size: 13px; color: #50575e; }
        .comp-vs { 
            font-size: 22px; font-weight: 800; color: #999; padding: 0 12px;
            display: flex; align-items: center;
        }
        .comp-resultado { 
            text-align: center; padding: 12px 20px; border-radius: 8px;
            border: 2px solid; background: #fafafa; min-width: 120px;
        }
        .comp-resultado small { display: block; margin-top: 4px; color: #666; }
        </style>
        
        <?php
        $this->render_styles();
    }
    
    // ========== FICHA CLIENTE ==========
    public function render_ficha_cliente() {
        echo '<h1>Ficha de Cliente: Gestión del Servicio y Métricas</h1>';
        global $wpdb;

        // Lógica para guardar nuevo cliente
        if (isset($_POST['crear_cliente']) && check_admin_referer('crear_cliente_nonce')) {
            $nombre = sanitize_text_field($_POST['nombre']);
            $email = sanitize_email($_POST['email']);
            $empresa = sanitize_text_field($_POST['empresa']);
            $telefono = sanitize_text_field($_POST['telefono']);
            $tipo = sanitize_text_field($_POST['tipo']);
            
            $res = $wpdb->insert($this->tabla_clientes, [
                'nombre' => $nombre,
                'email' => $email,
                'empresa' => $empresa,
                'telefono' => $telefono,
                'tipo' => $tipo,
                'estado' => $tipo === 'cliente' ? 'contratado' : 'nuevo',
                'fecha_contacto' => current_time('mysql')
            ]);
            
            if ($res) {
                $new_id = $wpdb->insert_id;
                if (isset($_POST['enviar_bienvenida'])) {
                    $this->_enviar_correo_bienvenida($new_id);
                }
                echo "<script>window.location='?page=automatiza-crm-ficha&id=$new_id';</script>";
                return;
            }
        }
        
        $ai_id = isset($_GET['ai_id']) ? sanitize_text_field($_GET['ai_id']) : '';
        $cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$ai_id && !$cliente_id) {
            // Mostrar formulario de creación
            ?>
            <div class="wrap crm-wrap">
                <h1>➕ Nuevo Cliente / Prospecto</h1>
                <div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
                    <form method="post">
                        <?php wp_nonce_field('crear_cliente_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="nombre">Nombre Completo</label></th>
                                <td><input name="nombre" type="text" id="nombre" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="empresa">Empresa</label></th>
                                <td><input name="empresa" type="text" id="empresa" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="email">Email</label></th>
                                <td><input name="email" type="email" id="email" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="telefono">Teléfono</label></th>
                                <td><input name="telefono" type="text" id="telefono" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="drive_folder_id">Google Drive Folder ID</label></th>
                                <td><input name="drive_folder_id" type="text" id="drive_folder_id" class="regular-text" placeholder="ID de la carpeta"></td>
                            </tr>
                            <tr>
                                <th><label for="tipo">Tipo</label></th>
                                <td>
                                    <select name="tipo" id="tipo">
                                        <option value="prospecto">Prospecto</option>
                                        <option value="cliente">Cliente</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="enviar_bienvenida">Notificación</label></th>
                                <td>
                                    <label><input type="checkbox" name="enviar_bienvenida" id="enviar_bienvenida" checked> Enviar correo de bienvenida</label>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="crear_cliente" id="submit" class="button button-primary" value="Guardar Cliente">
                        </p>
                    </form>
                </div>
            </div>
            <?php
            return;
        }
        
        // Buscar cliente por ai_identifier o id
        if ($ai_id) {
            $cliente = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->tabla_clientes} WHERE ai_identifier = %s", $ai_id
            ), ARRAY_A);
            
            // Si no existe, crearlo desde datos de AI
            if (!$cliente) {
                $nombre = ucwords(str_replace(['cliente_', 'demo_', '_'], ['', '', ' '], $ai_id));
                // CAMBIO: Por defecto 'prospecto' para evitar falsos clientes
                $tipo = 'prospecto';
                
                $wpdb->insert($this->tabla_clientes, [
                    'tipo' => $tipo,
                    'estado' => 'nuevo',
                    'nombre' => $nombre,
                    'ai_identifier' => $ai_id,
                    'fecha_contacto' => current_time('mysql')
                ]);
                $cliente_id = $wpdb->insert_id;
                $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id), ARRAY_A);
            } else {
                $cliente_id = $cliente['id'];
            }
        } else {
            $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id), ARRAY_A);
            $ai_id = $cliente['ai_identifier'] ?? '';
        }

                // Guardar datos generales (Edición manual)
                if (isset($_POST['guardar_datos_generales']) && check_admin_referer('guardar_datos_generales')) {
                    $nombre = sanitize_text_field($_POST['nombre']);
                    $email = sanitize_email($_POST['email']);
                    $empresa = sanitize_text_field($_POST['empresa']);
                    $telefono = sanitize_text_field($_POST['telefono']);
                    $rubro = sanitize_text_field($_POST['rubro'] ?? '');
                    // Limpiar mensajes de error PHP en notas
                    $notas = isset($_POST['notas']) ? preg_replace('/Deprecated:.*?\.php on line \d+/is', '', $_POST['notas']) : '';
                    $notas = sanitize_textarea_field($notas);
                    $tipo = sanitize_text_field($_POST['tipo']);
                    $drive_id = sanitize_text_field($_POST['drive_folder_id'] ?? '');
                    
                    $datos_update = [
                        'nombre' => $nombre,
                        'email' => $email,
                        'empresa' => $empresa,
                        'telefono' => $telefono,
                        'rubro' => $rubro,
                        'notas' => $notas,
                        'tipo' => $tipo,
                        // Si cambia a cliente, asegurar estado contratado
                        'estado' => $tipo === 'cliente' ? 'contratado' : 'nuevo'
                    ];
                    
                    // Solo intentar guardar drive_id si la columna existe (prevención de errores)
                    // Ojo: Esto asume que la columna existe. Si no, fallará silenciosamente o dará error.
                    // Para evitar error fatal, idealmente deberíamos verificar, pero lo agregaremos al array
                    // y confiaremos en que el usuario añadirá la columna.
                    $datos_update['drive_folder_id'] = $drive_id;
                    
                    $wpdb->update($this->tabla_clientes, $datos_update, ['id' => $cliente_id]);
                    
                    // Recargar datos
                    $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id), ARRAY_A);
                }

                // Identidad corporativa: manejo de archivos y campos
                if ((isset($_POST['guardar_identidad']) || (isset($_POST['crm_action']) && $_POST['crm_action'] === 'guardar_identidad')) && check_admin_referer('guardar_identidad')) {
                    $datos_identidad = [];

                    // Manejo de subida de logo
                    if (!empty($_FILES['logo']['name'])) {
                        if (!function_exists('media_handle_upload')) {
                            require_once(ABSPATH . 'wp-admin/includes/file.php');
                            require_once(ABSPATH . 'wp-admin/includes/media.php');
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                        }
                        $logo_id = media_handle_upload('logo', 0);
                        if (!is_wp_error($logo_id)) {
                            $datos_identidad['logo_url'] = wp_get_attachment_url($logo_id);
                        }
                    }

                    // Manejo de Variaciones de Logo
                    $variaciones = ['logo_nombre', 'logo_isotipo', 'logo_tagline'];
                    foreach ($variaciones as $var_field) {
                        if (!empty($_FILES[$var_field]['name'])) {
                            if (!function_exists('media_handle_upload')) {
                                require_once(ABSPATH . 'wp-admin/includes/file.php');
                                require_once(ABSPATH . 'wp-admin/includes/media.php');
                                require_once(ABSPATH . 'wp-admin/includes/image.php');
                            }
                            $var_id = media_handle_upload($var_field, 0);
                            if (!is_wp_error($var_id)) {
                                $datos_identidad[$var_field] = wp_get_attachment_url($var_id);
                            }
                        }
                    }
                    
                    // Manejo de subida de manual
                    if (!empty($_FILES['manual']['name'])) {
                        if (!function_exists('media_handle_upload')) {
                           require_once(ABSPATH . 'wp-admin/includes/file.php');
                           require_once(ABSPATH . 'wp-admin/includes/media.php');
                        }
                        $manual_id = media_handle_upload('manual', 0);
                        if (!is_wp_error($manual_id)) {
                            $datos_identidad['manual_url'] = wp_get_attachment_url($manual_id);
                        }
                    }
                    
                    // Recoger datos de texto y colores.
                    // Usar sanitize_text_field para colores si hex falla o viene vacio, para no perder datos si hay formato raro.
                    // Pero idealmente sanitize_hex_color. Si devuelve null (por vacio), pasamos cadena vacia.
                    $c_p = sanitize_hex_color($_POST['color_principal'] ?? '');
                    $datos_identidad['color_principal'] = $c_p ? $c_p : '';
                    
                    $c_s1 = sanitize_hex_color($_POST['color_secundario_1'] ?? '');
                    $datos_identidad['color_secundario_1'] = $c_s1 ? $c_s1 : '';
                    
                    $c_s2 = sanitize_hex_color($_POST['color_secundario_2'] ?? '');
                    $datos_identidad['color_secundario_2'] = $c_s2 ? $c_s2 : '';
                    
                    $datos_identidad['tipografia'] = sanitize_text_field($_POST['tipografia'] ?? '');
                    
                    $result = $wpdb->update(
                        $this->tabla_clientes,
                        $datos_identidad,
                        ['id' => $cliente_id],
                        null,
                        ['%d']
                    );

                    if ($result === false) {
                        echo '<div class="notice notice-error inline"><p><strong>Error Crítico de BD:</strong> ' . esc_html($wpdb->last_error) . '</p></div>';
                        // Intento de diagnóstico: Verificar si las columnas existen
                        $cols = $wpdb->get_results("SHOW COLUMNS FROM {$this->tabla_clientes} LIKE 'color_principal'");
                        if (empty($cols)) {
                            echo '<div class="notice notice-warning inline"><p><strong>Diagnóstico:</strong> Las columnas nuevas no existen. Ejecutando corrección de tabla...</p></div>';
                            $this->crear_tablas(); // Intentar crear las columnas faltantes
                        }
                    } else {
                        // --- EMAIL NOTIFICATION SYSTEM ---
                        $designer_email = 'Adriana.perez@automatizatech.cl';
                        $cc_email = 'lgonzalez@automatizatech.cl';
                        $current_u = wp_get_current_user();
                        $editor_email = $current_u->user_email;
                        
                        $subject = 'Actualización Identidad Corporativa: ' . ($cliente['nombre'] ?? 'Cliente');
                        
                        $message = '<html><body style="font-family:Arial, sans-serif;">';
                        $message .= '<div style="background:#f0f9ff; padding:20px; border-left:4px solid #1e3a8a;">';
                        $message .= '<h3 style="margin-top:0; color:#1e3a8a;">🎨 Actualización de Identidad Corporativa</h3>';
                        $message .= '<p>Se han realizado modificaciones en la ficha del cliente:</p>';
                        $message .= '<p><strong>Cliente:</strong> ' . ($cliente['nombre'] ?? 'Desconocido') . '</p>';
                        $message .= '<p><strong>Editado por:</strong> ' . $editor_email . ' (' . date('Y-m-d H:i') . ')</p>';
                        $message .= '</div>';
                        
                        $message .= '<h4>Detalle de cambios guardados:</h4>';
                        $message .= '<ul>';
                        if(!empty($datos_identidad['color_principal'])) 
                            $message .= '<li><strong>Color Principal:</strong> <span style="background:'.$datos_identidad['color_principal'].'; display:inline-block; width:15px; height:15px; border:1px solid #ccc;"></span> ' . $datos_identidad['color_principal'] . '</li>';
                        if(!empty($datos_identidad['color_secundario_1'])) 
                            $message .= '<li><strong>Color Secundario 1:</strong> <span style="background:'.$datos_identidad['color_secundario_1'].'; display:inline-block; width:15px; height:15px; border:1px solid #ccc;"></span> ' . $datos_identidad['color_secundario_1'] . '</li>';
                        if(!empty($datos_identidad['color_secundario_2'])) 
                            $message .= '<li><strong>Color Secundario 2:</strong> <span style="background:'.$datos_identidad['color_secundario_2'].'; display:inline-block; width:15px; height:15px; border:1px solid #ccc;"></span> ' . $datos_identidad['color_secundario_2'] . '</li>';
                        if(!empty($datos_identidad['logo_url'])) 
                            $message .= '<li><strong>Nuevo Logo:</strong> <a href="'.$datos_identidad['logo_url'].'">Ver archivo</a></li>';
                        if(!empty($datos_identidad['manual_url'])) 
                            $message .= '<li><strong>Manual de Marca:</strong> <a href="'.$datos_identidad['manual_url'].'">Ver archivo</a></li>';
                        if(!empty($datos_identidad['tipografia'])) 
                            $message .= '<li><strong>Tipografía:</strong> ' . $datos_identidad['tipografia'] . '</li>';
                        $message .= '</ul>';
                        
                        $message .= '<br><hr><p style="font-size:12px; color:#888;">Notificación automática del CRM MaxTech.</p>';
                        $message .= '</body></html>';
                        
                        $headers = array('Content-Type: text/html; charset=UTF-8');
                        $headers[] = 'Cc: ' . $cc_email;
                        
                        wp_mail($designer_email, $subject, $message, $headers);
                        // ---------------------------------

                        // --- LOG CHANGE TO TIMELINE ---
                        $wpdb->insert(
                            $this->tabla_historial,
                            [
                                'cliente_id' => $cliente_id,
                                'tipo_evento' => 'update',
                                'titulo' => 'Actualización Identidad Corporativa',
                                'descripcion' => 'Actualización realizada por: ' . $editor_email . '. Cambios: ' . implode(', ', array_keys(array_filter($datos_identidad))),
                                'usuario_id' => $current_u->ID,
                                'created_at' => current_time('mysql')
                            ]
                        );
                        // ------------------------------

                        echo '<div class="notice notice-success inline"><p>Datos de Identidad Corporativa guardados y notificación enviada a diseño.</p></div>';
                    }
                    
                    // Recargar siempre los datos del cliente desde la BD
                    $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id), ARRAY_A);
                }

                // Obtener datos de identidad para mostrar en el formulario, ya sea antes o después de guardar.
                $logo_url = $cliente['logo_url'] ?? '';
                $manual_url = $cliente['manual_url'] ?? '';
                $color_principal = $cliente['color_principal'] ?? '';
                $color_secundario_1 = $cliente['color_secundario_1'] ?? '';
                $color_secundario_2 = $cliente['color_secundario_2'] ?? '';
                $tipografia = $cliente['tipografia'] ?? '';

        
        // Consumo AI
        $consumoAI = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as requests,
                COALESCE(SUM(total_tokens), 0) as tokens,
                COALESCE(SUM(cost_estimated), 0) as costo,
                MIN(created_at) as primera,
                MAX(created_at) as ultima
            FROM {$this->tabla_ai}
            WHERE client_identifier = %s
        ", $ai_id), ARRAY_A);
        
        // Consumo por mes
        $consumoMensual = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as mes,
                SUM(total_tokens) as tokens,
                SUM(cost_estimated) as costo,
                COUNT(*) as requests
            FROM {$this->tabla_ai}
            WHERE client_identifier = %s
            GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
            ORDER BY mes DESC
            LIMIT 12
        ", $ai_id), ARRAY_A);
        
        // Consumo diario del mes actual (para gráfico detallado en ficha)
        $fichaConsumoDiario = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DAY(created_at) as dia,
                SUM(total_tokens) as tokens,
                SUM(cost_estimated) as costo,
                COUNT(*) as requests
            FROM {$this->tabla_ai}
            WHERE client_identifier = %s
              AND MONTH(created_at) = MONTH(NOW())
              AND YEAR(created_at) = YEAR(NOW())
            GROUP BY DAY(created_at)
            ORDER BY dia ASC
        ", $ai_id), ARRAY_A);
        
        $fichaDiarioPorDia = [];
        foreach ($fichaConsumoDiario as $fd) {
            $fichaDiarioPorDia[intval($fd['dia'])] = $fd;
        }
        $fichaDiarioLabels = [];
        $fichaDiarioCostos = [];
        $fichaDiarioTokens = [];
        $fichaDias = intval(date('j'));
        for ($fd = 1; $fd <= $fichaDias; $fd++) {
            $fichaDiarioLabels[] = $fd;
            $fichaDiarioCostos[] = isset($fichaDiarioPorDia[$fd]) ? round(floatval($fichaDiarioPorDia[$fd]['costo']), 6) : 0;
            $fichaDiarioTokens[] = isset($fichaDiarioPorDia[$fd]) ? intval($fichaDiarioPorDia[$fd]['tokens']) : 0;
        }
        
        // Proyectos (ordenados: pendientes/activos primero, luego por fecha reciente)
        $proyectos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tabla_proyectos} WHERE cliente_id = %d 
             ORDER BY FIELD(estado, 'en_progreso', 'pendiente', 'pausado', 'completado', 'cancelado'), created_at DESC", $cliente_id
        ), ARRAY_A);
        
        // Historial Legacy
        $historia_legacy = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tabla_historial} WHERE cliente_id = %d ORDER BY created_at DESC LIMIT 50", $cliente_id
        ), ARRAY_A);

        // --- NUEVO: Timeline Unificado para Ficha Admin ---
        $unified_timeline = [];

        // 1. Detalles de CLIENTE (wp_automatiza_clients_details)
        $table_client_details = $wpdb->prefix . 'automatiza_clients_details';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_client_details'") == $table_client_details) {
            $client_details = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_client_details WHERE client_id = %d", $cliente_id), ARRAY_A);
            foreach ($client_details as $d) {
                $d['source'] = 'client';
                // Prioridad de fecha: completed_date > scheduled_date > created_at
                if (!empty($d['completed_date'])) {
                    $ts = strtotime($d['completed_date']);
                } elseif (!empty($d['scheduled_date'])) {
                    $ts = strtotime($d['scheduled_date']);
                } else {
                    $ts = strtotime($d['created_at']);
                }
                $d['timestamp'] = $ts;
                $d['display_date'] = date('Y-m-d H:i:s', $ts);
                $unified_timeline[] = $d;
            }
        }

        // 2. Detalles de PROSPECTO (wp_automatiza_propuestas_details)
        $table_propuestas = $wpdb->prefix . 'automatiza_propuestas';
        $table_propuestas_details = $wpdb->prefix . 'automatiza_propuestas_details';
        
        if (!empty($cliente['email']) && $wpdb->get_var("SHOW TABLES LIKE '$table_propuestas'") == $table_propuestas) {
            $propuesta_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_propuestas WHERE client_email = %s ORDER BY id DESC LIMIT 1", $cliente['email']));
            
            if ($propuesta_id && $wpdb->get_var("SHOW TABLES LIKE '$table_propuestas_details'") == $table_propuestas_details) {
                $prospect_details = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_propuestas_details WHERE propuesta_id = %d", $propuesta_id), ARRAY_A);
                foreach ($prospect_details as $d) {
                    $d['source'] = 'prospect';
                    // Prioridad de fecha: completed_date > scheduled_date > created_at
                    if (!empty($d['completed_date'])) {
                        $ts = strtotime($d['completed_date']);
                    } elseif (!empty($d['scheduled_date'])) {
                        $ts = strtotime($d['scheduled_date']);
                    } else {
                        $ts = strtotime($d['created_at']);
                    }
                    $d['timestamp'] = $ts;
                    $d['display_date'] = date('Y-m-d H:i:s', $ts);
                    $unified_timeline[] = $d;
                }
            }
        }
        
        // 3. Agregar Legacy (historial CRM)
        // Mapear tipo_evento a detail_type del timeline unificado
        $legacy_type_map = [
            'nota'              => 'nota',
            'email'             => 'email',
            'reunion'           => 'reunion',
            'llamada'           => 'llamada',
            'pago'              => 'pago',
            'boleta'            => 'boleta',
            'factura'           => 'factura',
            'cotizacion'        => 'cotizacion',
            'proyecto_update'   => 'item_proyecto',
            'conversion'        => 'legacy',
            'update'            => 'legacy',
        ];
        foreach ($historia_legacy as $h) {
             $ts = strtotime($h['created_at']);
             $tipo = $h['tipo_evento'] ?? 'legacy';
             $detail_type = $legacy_type_map[$tipo] ?? 'legacy';
             $unified_timeline[] = [
                'id' => $h['id'],
                'detail_type' => $detail_type,
                'title' => $h['titulo'],
                'description' => $h['descripcion'],
                'created_at' => $h['created_at'],
                'display_date' => $h['created_at'],
                'timestamp' => $ts,
                'source' => 'system',
                'metadata' => $h['metadata']
            ];
        }

        // 4. Agregar Hito de Conversión (Separador)
        if (!empty($cliente['fecha_contrato'])) {
            $ts_conversion = strtotime($cliente['fecha_contrato']);
            $unified_timeline[] = [
                'detail_type' => 'separator',
                'title' => '🎉 ¡Inicio de Cliente!',
                'description' => 'Conversión de Prospecto a Cliente.',
                'created_at' => $cliente['fecha_contrato'],
                'display_date' => $cliente['fecha_contrato'],
                'timestamp' => $ts_conversion,
                'source' => 'system'
            ];
        }

        // Ordenar
        usort($unified_timeline, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        // Configuración visual (igual que en public view)
        $detail_types = [
            'propuesta_enviada' => ['label' => '📄 Propuesta Enviada', 'icon' => '📄', 'color' => '#667eea', 'bg' => '#ebf4ff'],
            'cotizacion' => ['label' => '💰 Cotización', 'icon' => '💰', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
            'reunion' => ['label' => '🤝 Reunión', 'icon' => '🤝', 'color' => '#10b981', 'bg' => '#d1fae5'],
            'llamada' => ['label' => '📞 Llamada', 'icon' => '📞', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
            'email' => ['label' => '📧 Email', 'icon' => '📧', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
            'boleta' => ['label' => '🧾 Boleta', 'icon' => '🧾', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
            'factura' => ['label' => '📋 Factura', 'icon' => '📋', 'color' => '#14b8a6', 'bg' => '#f0fdfa'],
            'pago' => ['label' => '💳 Pago Recibido', 'icon' => '💳', 'color' => '#22c55e', 'bg' => '#dcfce7'],
            'item_proyecto' => ['label' => '📦 Item de Proyecto', 'icon' => '📦', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
            'entregable' => ['label' => '✅ Entregable', 'icon' => '✅', 'color' => '#84cc16', 'bg' => '#ecfccb'],
            'nota' => ['label' => '📝 Nota', 'icon' => '📝', 'color' => '#94a3b8', 'bg' => '#f1f5f9'],
            'legacy' => ['label' => '🤖 Sistema', 'icon' => '⚙️', 'color' => '#64748b', 'bg' => '#f8fafc'],
            'separator' => ['label' => '🎉 Conversión', 'icon' => '🎉', 'color' => '#7c3aed', 'bg' => '#f5f3ff'] // Morado vibrante
        ];

        // Propuestas y demos como prospecto (por email)
        $tabla_propuestas = $wpdb->prefix . 'automatiza_propuestas';
        $propuestas = [];
        if (!empty($cliente['email'])) {
            $propuestas = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $tabla_propuestas WHERE client_email = %s ORDER BY id DESC", $cliente['email']
            ), ARRAY_A);
        }

        // Evento de conversión a cliente
        $evento_conversion = null;
        if ($cliente['tipo'] === 'cliente' && !empty($cliente['fecha_contrato'])) {
            $evento_conversion = [
                'created_at' => $cliente['fecha_contrato'],
                'titulo' => 'Convertido a cliente',
                'descripcion' => 'El prospecto fue convertido a cliente final.',
            ];
        }
        
        // Últimas interacciones AI
        $ultimasAI = $wpdb->get_results($wpdb->prepare("
            SELECT created_at, model_used, prompt_tokens, completion_tokens, total_tokens, cost_estimated
            FROM {$this->tabla_ai}
            WHERE client_identifier = %s
            ORDER BY created_at DESC
            LIMIT 20
        ", $ai_id), ARRAY_A);
        
        // --- Access Control for Designer ---
        $current_user_obj = wp_get_current_user();
        $is_designer_only = ($current_user_obj->user_email === 'Adriana.perez@automatizatech.cl');
        // -----------------------------------

        // ─── QA Data for this client ───
        $qa_projects = [];
        $qa_table_projects  = $wpdb->prefix . 'at_qa_projects';
        $qa_table_modules   = $wpdb->prefix . 'at_qa_modules';
        $qa_table_cases     = $wpdb->prefix . 'at_qa_cases';
        $qa_table_evidence  = $wpdb->prefix . 'at_qa_evidence';
        $qa_table_comments  = $wpdb->prefix . 'at_qa_comments';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$qa_table_projects}'") === $qa_table_projects) {
            $qa_projects = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$qa_table_projects} WHERE client_id = %d ORDER BY created_at DESC",
                $cliente_id
            ), ARRAY_A);
            foreach ($qa_projects as &$qp) {
                $qp['modules'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.*, 
                        (SELECT COUNT(*) FROM {$qa_table_cases} WHERE module_id = m.id) as total_cases,
                        (SELECT COUNT(*) FROM {$qa_table_cases} WHERE module_id = m.id AND status = 'pass') as passed,
                        (SELECT COUNT(*) FROM {$qa_table_cases} WHERE module_id = m.id AND status = 'fail') as failed,
                        (SELECT COUNT(*) FROM {$qa_table_cases} WHERE module_id = m.id AND status = 'blocked') as blocked
                    FROM {$qa_table_modules} m WHERE m.project_id = %d ORDER BY sort_order",
                    $qp['id']
                ), ARRAY_A);
                $qp['total'] = 0; $qp['passed_total'] = 0; $qp['failed_total'] = 0;
                foreach ($qp['modules'] as &$mod) {
                    $qp['total'] += (int)$mod['total_cases'];
                    $qp['passed_total'] += (int)$mod['passed'];
                    $qp['failed_total'] += (int)$mod['failed'];
                    // Fetch cases for this module
                    $mod['cases'] = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$qa_table_cases} WHERE module_id = %d ORDER BY sort_order, id",
                        $mod['id']
                    ), ARRAY_A);
                    // Fetch evidence and comments per case
                    foreach ($mod['cases'] as &$caso) {
                        $caso['evidence'] = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$qa_table_evidence} WHERE case_id = %d ORDER BY created_at DESC",
                            $caso['id']
                        ), ARRAY_A);
                        $caso['comments'] = $wpdb->get_results($wpdb->prepare(
                            "SELECT c.*, u.display_name as author_name FROM {$qa_table_comments} c
                             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                             WHERE c.case_id = %d ORDER BY c.created_at ASC",
                            $caso['id']
                        ), ARRAY_A);
                    }
                    unset($caso);
                }
                unset($mod);
                $qp['progress'] = $qp['total'] > 0 ? round(($qp['passed_total'] / $qp['total']) * 100) : 0;
                // Check for report
                $upload_dir = wp_upload_dir();
                $report_file = $upload_dir['basedir'] . '/qa-reports/qa-report-' . $qp['id'] . '.html';
                $qp['report_url'] = file_exists($report_file) ? $upload_dir['baseurl'] . '/qa-reports/qa-report-' . $qp['id'] . '.html' : '';
            }
            unset($qp);
        }
        // ─── End QA Data ───
        
        ?>
        <div class="wrap crm-wrap">
            <h1>
                <?php echo isset($cliente['tipo']) && $cliente['tipo'] == 'cliente' ? '⭐' : '🧪'; ?>
                Ficha: <?php echo esc_html($cliente['nombre'] ?? ''); ?>
                <span class="cliente-badge <?php echo esc_attr($cliente['tipo'] ?? ''); ?>"><?php echo ucfirst($cliente['tipo'] ?? ''); ?></span>
            </h1>
            
            <div class="ficha-grid">
                <!-- Columna Izquierda: Datos del Cliente -->
                <div class="ficha-col">
                    <!-- Tabs de navegación izquierda -->
                    <div class="ficha-tabs">
                        <button class="ficha-tab active" data-target="tab-identidad">🎨 Identidad</button>
                        <?php if (!$is_designer_only): ?>
                        <button class="ficha-tab" data-target="tab-general">📋 General</button>
                        <button class="ficha-tab" data-target="tab-proyectos">🚀 Proyectos <span class="ficha-tab-badge"><?php echo count($proyectos); ?></span></button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab: Identidad Corporativa -->
                    <div class="ficha-tab-content active" id="tab-identidad">
                                        <div class="ficha-card">
                                            <h3>🎨 Identidad Corporativa</h3>
                                            <form method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="crm_action" value="guardar_identidad">
                                                <?php wp_nonce_field('guardar_identidad'); ?>
                                                <table class="form-table">
                                                    <tr>
                                                        <th>Logo Principal (Completo)</th>
                                                        <td>
                                                            <?php if ($logo_url): ?>
                                                                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width:120px;max-height:80px;display:block;margin-bottom:5px;">
                                                            <?php endif; ?>
                                                            <input type="file" name="logo" accept="image/*">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Logo Variación: Nombre</th>
                                                        <td>
                                                            <?php if (isset($cliente['logo_nombre']) && $cliente['logo_nombre']): ?>
                                                                <img src="<?php echo esc_url($cliente['logo_nombre']); ?>" alt="Logo Nombre" style="max-width:120px;max-height:80px;display:block;margin-bottom:5px;">
                                                            <?php endif; ?>
                                                            <input type="file" name="logo_nombre" accept="image/*">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Logo Variación: Isotipo</th>
                                                        <td>
                                                            <?php if (isset($cliente['logo_isotipo']) && $cliente['logo_isotipo']): ?>
                                                                <img src="<?php echo esc_url($cliente['logo_isotipo']); ?>" alt="Logo Isotipo" style="max-width:120px;max-height:80px;display:block;margin-bottom:5px;">
                                                            <?php endif; ?>
                                                            <input type="file" name="logo_isotipo" accept="image/*">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Logo Variación: Tagline</th>
                                                        <td>
                                                            <?php if (isset($cliente['logo_tagline']) && $cliente['logo_tagline']): ?>
                                                                <img src="<?php echo esc_url($cliente['logo_tagline']); ?>" alt="Logo Tagline" style="max-width:120px;max-height:80px;display:block;margin-bottom:5px;">
                                                            <?php endif; ?>
                                                            <input type="file" name="logo_tagline" accept="image/*">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Manual de Marca</th>
                                                        <td>
                                                            <?php if ($manual_url): ?>
                                                                <a href="<?php echo esc_url($manual_url); ?>" target="_blank">Ver Manual</a>
                                                            <?php endif; ?>
                                                            <input type="file" name="manual" accept="application/pdf">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Paleta de Colores</th>
                                                        <td>
                                                            <div style="display:flex; gap:10px; flex-direction:column;">
                                                                <label style="display:flex; align-items:center; gap:10px;">
                                                                    Principal: 
                                                                    <input type="color" name="color_principal" value="<?php echo esc_attr($color_principal); ?>">
                                                                    <input type="text" value="<?php echo esc_attr($color_principal); ?>" class="small-text" oninput="this.previousElementSibling.value=this.value" onchange="this.previousElementSibling.value=this.value" style="width: 90px;">
                                                                </label>
                                                                <label style="display:flex; align-items:center; gap:10px;">
                                                                    Secundario 1: 
                                                                    <input type="color" name="color_secundario_1" value="<?php echo esc_attr($color_secundario_1); ?>">
                                                                    <input type="text" value="<?php echo esc_attr($color_secundario_1); ?>" class="small-text" oninput="this.previousElementSibling.value=this.value" onchange="this.previousElementSibling.value=this.value" style="width: 90px;">
                                                                </label>
                                                                <label style="display:flex; align-items:center; gap:10px;">
                                                                    Secundario 2: 
                                                                    <input type="color" name="color_secundario_2" value="<?php echo esc_attr($color_secundario_2); ?>">
                                                                    <input type="text" value="<?php echo esc_attr($color_secundario_2); ?>" class="small-text" oninput="this.previousElementSibling.value=this.value" onchange="this.previousElementSibling.value=this.value" style="width: 90px;">
                                                                </label>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Tipografía</th>
                                                        <td><input type="text" name="tipografia" value="<?php echo esc_attr($tipografia); ?>" class="regular-text"></td>
                                                    </tr>
                                                </table>
                                                <p><button type="submit" name="guardar_identidad" class="button">Guardar Identidad</button></p>
                                            </form>
                                        </div>
                    </div><!-- /tab-identidad -->
                    
                    <?php if (!$is_designer_only): ?>
                    <!-- Tab: Información General -->
                    <div class="ficha-tab-content" id="tab-general">
                    <div class="ficha-card">
                        <h3>📋 Información General</h3>
                        <form method="post">
                            <?php wp_nonce_field('guardar_datos_generales'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th>Nombre</th>
                                    <td><input type="text" name="nombre" value="<?php echo esc_attr($cliente['nombre'] ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td><input type="email" name="email" value="<?php echo esc_attr($cliente['email'] ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Teléfono</th>
                                    <td><input type="text" name="telefono" value="<?php echo esc_attr($cliente['telefono'] ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Empresa</th>
                                    <td><input type="text" name="empresa" value="<?php echo esc_attr($cliente['empresa'] ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Rubro</th>
                                    <td><input type="text" name="rubro" value="<?php echo esc_attr($cliente['rubro'] ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Google Drive ID</th>
                                    <td>
                                        <input type="text" name="drive_folder_id" value="<?php echo esc_attr($cliente['drive_folder_id'] ?? ''); ?>" class="regular-text" placeholder="Ej: 1abcDEfghIjkLMnoPqrstUVwxYZ">
                                        <br><span class="description">ID de la carpeta compartido de Google Drive.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tipo</th>
                                    <td>
                                        <select name="tipo">
                                            <option value="prospecto" <?php selected($cliente['tipo'], 'prospecto'); ?>>Prospecto</option>
                                            <option value="cliente" <?php selected($cliente['tipo'], 'cliente'); ?>>Cliente</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Portal Cliente</th>
                                    <td>
                                        <?php
                                        $token = $this->_generar_token($cliente['id'] ?? 0, $cliente['email'] ?? '');
                                        $link_timeline = home_url('/?crm_view=timeline&cid=' . ($cliente['id'] ?? 0) . '&token=' . $token);
                                        ?>
                                        <a href="<?php echo esc_url($link_timeline); ?>" target="_blank" class="button button-small">🔗 Ver Vista de Cliente</a>
                                        <br><small style="color:#666;">Enlace público seguro para el cliente.</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>AI Identifier</th>
                                    <td><code><?php echo esc_html($ai_id); ?></code></td>
                                </tr>
                                <tr>
                                    <th>Notas</th>
                                    <td><textarea name="notas" rows="4" class="large-text"><?php echo esc_textarea($cliente['notas'] ?? ''); ?></textarea></td>
                                </tr>
                            </table>
                            <p><button type="submit" name="guardar_datos_generales" class="button button-primary">Guardar Cambios</button></p>
                        </form>
                    </div>
                    </div><!-- /tab-general -->
                    
                    <!-- Tab: Proyectos -->
                    <div class="ficha-tab-content" id="tab-proyectos">
                    <div class="ficha-card">
                        <h3>🚀 Proyectos</h3>
                        <?php if (empty($proyectos)): ?>
                            <p>No hay proyectos registrados.</p>
                        <?php else: ?>
                            <?php foreach ($proyectos as $p): ?>
                                <div class="proyecto-item" id="proyecto-<?php echo $p['id']; ?>">
                                    <form class="form-actualizar-proyecto" enctype="multipart/form-data">
                                        <div style="margin-bottom: 10px;">
                                            <label style="font-weight:600; font-size:12px;">Nombre del Proyecto:</label>
                                            <input type="text" name="nombre_proyecto" value="<?php echo esc_attr($p['nombre']); ?>" style="width:100%; font-weight:bold; font-size:14px; margin-top:4px;">
                                        </div>
                                        <p><?php echo esc_html($p['descripcion']); ?></p>
                                        

                                        <input type="hidden" name="proyecto_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">

                                        <div class="proyecto-campos-actualizacion">
                                            <div>
                                                <label>Estado:</label>
                                                <select name="estado">
                                                    <option value="pendiente" <?php selected($p['estado'], 'pendiente'); ?>>Pendiente</option>
                                                    <option value="en_progreso" <?php selected($p['estado'], 'en_progreso'); ?>>En Progreso</option>
                                                    <option value="completado" <?php selected($p['estado'], 'completado'); ?>>Completado</option>
                                                    <option value="pausado" <?php selected($p['estado'], 'pausado'); ?>>Pausado</option>
                                                    <option value="cancelado" <?php selected($p['estado'], 'cancelado'); ?>>Cancelado</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Nota de actualización:</label>
                                                <textarea name="nota_actualizacion" rows="8" style="min-height:150px;resize:vertical;" placeholder="Añadir nota sobre la actualización..."></textarea>
                                            </div>
                                            <div style="margin-top:10px;">
                                                <label>📸 Evidencia (Pantallazos):</label>
                                                <input type="file" name="evidencia[]" multiple accept="image/*">
                                            </div>
                                        </div>
                                        

                                        <div class="proyecto-acciones">
                                            <?php 
                                            global $wpdb;
                                            $last_notif = $wpdb->get_var($wpdb->prepare("
                                                SELECT created_at FROM {$wpdb->prefix}crm_historial 
                                                WHERE cliente_id = %d 
                                                AND tipo_evento = 'proyecto_update'
                                                AND metadata LIKE %s
                                                ORDER BY created_at DESC LIMIT 1
                                            ", $cliente_id, '%"proyecto_id":' . intval($p['id']) . '%"notificado":true%'));
                                            
                                            if ($last_notif): ?>
                                                <div style="margin-bottom:5px; font-size:11px; color:#15803d; background:#dcfce7; padding:3px 8px; border-radius:10px; display:block; width: fit-content;">
                                                    ✅ Última notificación: <strong><?php echo date('d/m H:i', strtotime($last_notif)); ?></strong>
                                                </div>
                                            <?php endif; ?>
                                            <label><input type="checkbox" name="notificar_actualizacion"> Notificar al cliente</label>
                                            <button type="submit" class="button button-primary button-small">Guardar Cambios</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p><button class="button" id="btnAbrirModalProyecto">+ Agregar Proyecto</button></p>
                    </div>
                    </div><!-- /tab-proyectos -->
                    <?php endif; ?>
                </div>
                
                <?php if (!$is_designer_only): ?>
                <!-- Columna Derecha: Consumo AI y Métricas -->
                <div class="ficha-col">
                    <!-- Tabs de navegación derecha -->
                    <div class="ficha-tabs">
                        <button class="ficha-tab active" data-target="tab-consumo">🤖 Consumo AI</button>
                        <button class="ficha-tab" data-target="tab-grafico">📊 Gráfico</button>
                        <button class="ficha-tab" data-target="tab-interacciones">🕐 Interacciones</button>
                        <?php if (!empty($qa_projects)): ?>
                        <button class="ficha-tab" data-target="tab-qa">🧪 QA <span class="ficha-tab-badge"><?php echo count($qa_projects); ?></span></button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab: Consumo AI -->
                    <div class="ficha-tab-content active" id="tab-consumo">
                    <div class="ficha-card highlight">
                        <h3>🤖 Consumo AI Total</h3>
                        <div class="metricas-grid">
                            <div class="metrica">
                                <div class="metrica-valor"><?php echo number_format($consumoAI['requests']); ?></div>
                                <div class="metrica-label">Peticiones</div>
                            </div>
                            <div class="metrica">
                                <div class="metrica-valor"><?php echo number_format($consumoAI['tokens']); ?></div>
                                <div class="metrica-label">Tokens</div>
                            </div>
                            <div class="metrica">
                                <div class="metrica-valor">$<?php echo number_format($consumoAI['costo'], 4); ?></div>
                                <div class="metrica-label">Costo OpenAI</div>
                            </div>
                            <div class="metrica green">
                                <div class="metrica-valor">$<?php echo number_format($consumoAI['costo'] * 1.3, 2); ?></div>
                                <div class="metrica-label">A Facturar (+30%)</div>
                            </div>
                        </div>
                        <p><small>Primera actividad: <?php echo $consumoAI['primera'] ? date('d/m/Y', strtotime($consumoAI['primera'])) : 'N/A'; ?> | 
                        Última: <?php echo $consumoAI['ultima'] ? date('d/m/Y H:i', strtotime($consumoAI['ultima'])) : 'N/A'; ?></small></p>
                    </div>
                    </div><!-- /tab-consumo -->
                    
                    <!-- Tab: Gráfico mensual -->
                    <div class="ficha-tab-content" id="tab-grafico">
                    <div class="ficha-card">
                        <h3>� Consumo Diario - <?php echo date('F Y'); ?></h3>
                        <canvas id="chartFichaDiario" height="200"></canvas>
                    </div>
                    <div class="ficha-card" style="margin-top:15px;">
                        <h3>📊 Consumo Mensual (Histórico)</h3>
                        <canvas id="chartMensual" height="180"></canvas>
                    </div>
                    </div><!-- /tab-grafico -->
                    
                    <!-- Tab: Últimas interacciones -->
                    <div class="ficha-tab-content" id="tab-interacciones">
                    <div class="ficha-card">
                        <h3>🕐 Últimas Interacciones AI</h3>
                        <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Modelo</th>
                                    <th>Tokens</th>
                                    <th>Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimasAI as $u): ?>
                                <tr>
                                    <td><?php echo date('d/m H:i', strtotime($u['created_at'])); ?></td>
                                    <td><?php echo esc_html(str_replace('-2024-', '-', $u['model_used'])); ?></td>
                                    <td><?php echo $u['total_tokens']; ?></td>
                                    <td>$<?php echo number_format($u['cost_estimated'], 5); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div><!-- /tab-interacciones -->

                    <?php if (!empty($qa_projects)): ?>
                    <!-- Tab: QA Pruebas -->
                    <div class="ficha-tab-content" id="tab-qa">
                    <style>
                        .qa-mod-toggle{cursor:pointer;user-select:none;transition:background .2s;}
                        .qa-mod-toggle:hover{background:#f0fdfa !important;}
                        .qa-mod-toggle .qa-arrow{transition:transform .2s;display:inline-block;}
                        .qa-mod-toggle.open .qa-arrow{transform:rotate(90deg);}
                        .qa-cases-wrap{display:none;animation:qaSlide .2s ease;}
                        .qa-cases-wrap.open{display:block;}
                        @keyframes qaSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
                        .qa-case-row{border-left:3px solid #e5e7eb;padding:8px 12px;margin:4px 0;border-radius:0 6px 6px 0;background:#fafafa;transition:background .15s;}
                        .qa-case-row:hover{background:#f0fdfa;}
                        .qa-case-row.st-pass{border-left-color:#059669;}
                        .qa-case-row.st-fail{border-left-color:#dc2626;}
                        .qa-case-row.st-blocked{border-left-color:#f59e0b;}
                        .qa-case-row.st-not_tested{border-left-color:#9ca3af;}
                        .qa-case-row.st-skipped{border-left-color:#6366f1;}
                        .adm-qa-carousel{position:relative;overflow:hidden;border-radius:10px;background:#f9fafb;border:1px solid #e5e7eb;margin:8px auto 0;width:100%;max-width:680px;}
                        .adm-qa-carousel-track{display:flex;transition:transform .3s ease;}
                        .adm-qa-carousel-slide{min-width:100%;display:flex;align-items:center;justify-content:center;padding:12px;}
                        .adm-qa-carousel-slide img{max-height:420px;max-width:100%;width:auto;border-radius:8px;cursor:zoom-in;object-fit:contain;}
                        .adm-qa-carousel-btn{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.45);color:#fff;border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;z-index:2;transition:background .15s;}
                        .adm-qa-carousel-btn:hover{background:rgba(0,0,0,.7);}
                        .adm-qa-carousel-btn.prev{left:8px;}
                        .adm-qa-carousel-btn.next{right:8px;}
                        .adm-qa-carousel-dots{display:flex;justify-content:center;gap:5px;padding:6px 0;}
                        .adm-qa-carousel-dot{width:8px;height:8px;border-radius:50%;background:#d1d5db;border:none;cursor:pointer;padding:0;transition:background .15s;}
                        .adm-qa-carousel-dot.active{background:#0d9488;}
                        .adm-qa-carousel-counter{text-align:center;font-size:11px;color:#6b7280;margin-top:2px;}
                        @media(max-width:768px){.adm-qa-carousel{max-width:100%;}.adm-qa-carousel-slide img{max-height:280px;}.adm-qa-carousel-btn{width:30px;height:30px;font-size:15px;}}
                        @media(max-width:480px){.adm-qa-carousel-slide{padding:6px;}.adm-qa-carousel-slide img{max-height:200px;}.adm-qa-carousel-btn{width:26px;height:26px;font-size:13px;}}
                        .adm-qa-lightbox{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:99999;align-items:center;justify-content:center;flex-direction:column;}
                        .adm-qa-lightbox.show{display:flex;}
                        .adm-qa-lightbox-img-wrap{position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;max-width:94vw;max-height:82vh;}
                        .adm-qa-lightbox-img-wrap img{max-width:92vw;max-height:80vh;border-radius:8px;box-shadow:0 0 40px rgba(0,0,0,.5);transition:transform .25s ease;cursor:grab;user-select:none;}
                        .adm-qa-lightbox-close{position:absolute;top:12px;right:16px;background:rgba(0,0,0,.5);border:none;color:#fff;font-size:28px;cursor:pointer;z-index:100000;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .2s;}
                        .adm-qa-lightbox-close:hover{background:rgba(255,255,255,.2);}
                        .adm-qa-lb-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;width:44px;height:44px;border-radius:50%;cursor:pointer;font-size:22px;display:flex;align-items:center;justify-content:center;transition:background .2s;z-index:100000;backdrop-filter:blur(4px);}
                        .adm-qa-lb-nav:hover{background:rgba(255,255,255,.3);}
                        .adm-qa-lb-nav.prev{left:16px;}
                        .adm-qa-lb-nav.next{right:16px;}
                        .adm-qa-lb-toolbar{display:flex;gap:10px;align-items:center;margin-top:12px;}
                        .adm-qa-lb-toolbar button{background:rgba(255,255,255,.12);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .2s;backdrop-filter:blur(4px);}
                        .adm-qa-lb-toolbar button:hover{background:rgba(255,255,255,.25);}
                        .adm-qa-lb-counter{color:rgba(255,255,255,.8);font-size:13px;font-weight:600;letter-spacing:.5px;}
                        .qa-comment-item{background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;margin-top:4px;font-size:12px;}
                        .qa-section-header{background:#0d9488;color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:700;margin:8px 0 4px;letter-spacing:.3px;}
                        .qa-filter-bar{display:flex;gap:6px;margin:8px 0;flex-wrap:wrap;}
                        .qa-filter-btn{padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;}
                        .qa-filter-btn:hover,.qa-filter-btn.active{background:#0d9488;color:#fff;border-color:#0d9488;}
                    </style>
                    <?php foreach ($qa_projects as $qp_idx => $qp): ?>
                        <div class="ficha-card" style="margin-bottom:15px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                <h3 style="margin:0;">🧪 <?php echo esc_html($qp['name']); ?></h3>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <?php
                                    $badge_color = '#6b7280';
                                    $badge_label = ucfirst($qp['qa_status']);
                                    if ($qp['qa_status'] === 'approved') { $badge_color = '#059669'; $badge_label = 'Aprobado'; }
                                    elseif ($qp['qa_status'] === 'rejected') { $badge_color = '#dc2626'; $badge_label = 'Rechazado'; }
                                    elseif ($qp['qa_status'] === 'in_progress') { $badge_color = '#d97706'; $badge_label = 'En Progreso'; }
                                    elseif ($qp['qa_status'] === 'observations') { $badge_color = '#f59e0b'; $badge_label = 'Observaciones'; }
                                    elseif ($qp['qa_status'] === 'pending') { $badge_color = '#6b7280'; $badge_label = 'Pendiente'; }
                                    elseif ($qp['qa_status'] === 'passed') { $badge_color = '#059669'; $badge_label = 'Aprobado'; }
                                    elseif ($qp['qa_status'] === 'failed') { $badge_color = '#dc2626'; $badge_label = 'Fallido'; }
                                    ?>
                                    <span style="background:<?php echo $badge_color; ?>; color:#fff; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;"><?php echo $badge_label; ?></span>
                                    <?php if ($qp['report_url']): ?>
                                        <a href="<?php echo esc_url($qp['report_url']); ?>" target="_blank" class="button button-small" style="background:linear-gradient(135deg,#0d9488,#14b8a6); color:#fff; border:none; font-size:11px;">📄 Ver Informe</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Summary stats -->
                            <div style="display:flex; gap:16px; margin:10px 0 4px; flex-wrap:wrap;">
                                <span style="font-size:12px;">📋 <strong><?php echo $qp['total']; ?></strong> Total</span>
                                <span style="font-size:12px; color:#059669;">✅ <strong><?php echo $qp['passed_total']; ?></strong> Pass</span>
                                <span style="font-size:12px; color:#dc2626;">❌ <strong><?php echo $qp['failed_total']; ?></strong> Fail</span>
                                <?php
                                $blocked_total = 0;
                                $not_tested_total = 0;
                                foreach ($qp['modules'] as $m) {
                                    $blocked_total += (int)$m['blocked'];
                                    $not_tested_total += (int)$m['total_cases'] - (int)$m['passed'] - (int)$m['failed'] - (int)$m['blocked'];
                                }
                                ?>
                                <span style="font-size:12px; color:#f59e0b;">⚠️ <strong><?php echo $blocked_total; ?></strong> Bloqueados</span>
                                <span style="font-size:12px; color:#9ca3af;">⏳ <strong><?php echo max(0, $not_tested_total); ?></strong> Sin probar</span>
                            </div>

                            <!-- Progress bar -->
                            <div style="margin:8px 0;">
                                <div style="display:flex; justify-content:space-between; font-size:12px; color:#6b7280; margin-bottom:4px;">
                                    <span><?php echo $qp['passed_total']; ?> / <?php echo $qp['total']; ?> casos aprobados</span>
                                    <span style="font-weight:600;"><?php echo $qp['progress']; ?>%</span>
                                </div>
                                <div style="background:#e5e7eb; border-radius:6px; height:10px; overflow:hidden;">
                                    <?php
                                    $pct_pass = $qp['total'] > 0 ? round(($qp['passed_total'] / $qp['total']) * 100, 1) : 0;
                                    $pct_fail = $qp['total'] > 0 ? round(($qp['failed_total'] / $qp['total']) * 100, 1) : 0;
                                    $pct_block = $qp['total'] > 0 ? round(($blocked_total / $qp['total']) * 100, 1) : 0;
                                    ?>
                                    <div style="display:flex; height:100%;">
                                        <div style="background:#059669; width:<?php echo $pct_pass; ?>%; height:100%;"></div>
                                        <div style="background:#dc2626; width:<?php echo $pct_fail; ?>%; height:100%;"></div>
                                        <div style="background:#f59e0b; width:<?php echo $pct_block; ?>%; height:100%;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modules with expandable cases -->
                            <?php if (!empty($qp['modules'])): ?>
                            <?php foreach ($qp['modules'] as $mod_idx => $mod):
                                $mod_pct = $mod['total_cases'] > 0 ? round(($mod['passed'] / $mod['total_cases']) * 100) : 0;
                                $mod_uid = 'qa-mod-' . $qp_idx . '-' . $mod_idx;
                                $tested_count = (int)$mod['passed'] + (int)$mod['failed'] + (int)$mod['blocked'];
                                $has_cases = !empty($mod['cases']);
                            ?>
                            <div style="border:1px solid #e5e7eb; border-radius:8px; margin-top:10px; overflow:hidden;">
                                <!-- Module header (clickable) -->
                                <div class="qa-mod-toggle" id="<?php echo $mod_uid; ?>-hdr"
                                     onclick="var w=document.getElementById('<?php echo $mod_uid; ?>-body');var h=this;if(w.classList.contains('open')){w.classList.remove('open');h.classList.remove('open');}else{w.classList.add('open');h.classList.add('open');}"
                                     style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; background:#f9fafb; border-bottom:1px solid #e5e7eb; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                                        <span class="qa-arrow" style="font-size:10px; color:#6b7280;">▶</span>
                                        <span style="font-weight:600; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($mod['code'] . ' — ' . $mod['title']); ?></span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                                        <span style="font-size:11px; color:#059669; font-weight:600;">✅<?php echo $mod['passed']; ?></span>
                                        <span style="font-size:11px; color:#dc2626; font-weight:600;">❌<?php echo $mod['failed']; ?></span>
                                        <span style="font-size:11px; color:#f59e0b; font-weight:600;">⚠️<?php echo $mod['blocked']; ?></span>
                                        <span style="font-size:11px; color:#6b7280;"><?php echo $tested_count; ?>/<?php echo $mod['total_cases']; ?></span>
                                        <div style="display:inline-flex; align-items:center; gap:4px;">
                                            <div style="background:#e5e7eb; border-radius:4px; height:6px; width:50px; overflow:hidden;">
                                                <div style="background:<?php echo $mod_pct === 100 ? '#059669' : '#14b8a6'; ?>; width:<?php echo $mod_pct; ?>%; height:100%;"></div>
                                            </div>
                                            <span style="font-size:11px; font-weight:600; color:<?php echo $mod_pct === 100 ? '#059669' : '#6b7280'; ?>;"><?php echo $mod_pct; ?>%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cases list (hidden by default) -->
                                <div class="qa-cases-wrap" id="<?php echo $mod_uid; ?>-body" style="padding:8px 12px;">
                                    <?php if ($has_cases):
                                        // Group cases by section
                                        $sections = [];
                                        foreach ($mod['cases'] as $caso) {
                                            $sec = !empty($caso['section']) ? $caso['section'] : '__sin_seccion__';
                                            $sections[$sec][] = $caso;
                                        }
                                        // Filter bar
                                    ?>
                                    <div class="qa-filter-bar" id="<?php echo $mod_uid; ?>-filters">
                                        <button class="qa-filter-btn active" onclick="qaFilterCases('<?php echo $mod_uid; ?>','all',this)">Todos</button>
                                        <button class="qa-filter-btn" onclick="qaFilterCases('<?php echo $mod_uid; ?>','pass',this)">✅ Pass</button>
                                        <button class="qa-filter-btn" onclick="qaFilterCases('<?php echo $mod_uid; ?>','fail',this)">❌ Fail</button>
                                        <button class="qa-filter-btn" onclick="qaFilterCases('<?php echo $mod_uid; ?>','blocked',this)">⚠️ Bloqueado</button>
                                        <button class="qa-filter-btn" onclick="qaFilterCases('<?php echo $mod_uid; ?>','not_tested',this)">⏳ Sin probar</button>
                                    </div>

                                    <?php foreach ($sections as $sec_name => $sec_cases): ?>
                                        <?php if ($sec_name !== '__sin_seccion__'): ?>
                                            <div class="qa-section-header"><?php echo esc_html(strtoupper($sec_name)); ?></div>
                                        <?php endif; ?>
                                        <?php foreach ($sec_cases as $caso):
                                            $status_labels = [
                                                'pass'       => ['✅ PASS', '#059669'],
                                                'fail'       => ['❌ FAIL', '#dc2626'],
                                                'blocked'    => ['⚠️ BLOQUEADO', '#f59e0b'],
                                                'not_tested' => ['⏳ Sin probar', '#9ca3af'],
                                                'skipped'    => ['⏭️ Omitido', '#6366f1'],
                                            ];
                                            $st = $caso['status'];
                                            $st_info = $status_labels[$st] ?? ['❓ ' . $st, '#6b7280'];
                                            $has_ev = !empty($caso['evidence']);
                                            $has_cm = !empty($caso['comments']);
                                            $case_uid = $mod_uid . '-case-' . $caso['id'];
                                        ?>
                                        <div class="qa-case-row st-<?php echo esc_attr($st); ?>" data-qa-status="<?php echo esc_attr($st); ?>" data-qa-mod="<?php echo $mod_uid; ?>">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                                <div style="flex:1; min-width:0;">
                                                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                        <span style="font-size:11px; color:#6b7280; font-weight:600;"><?php echo esc_html($caso['case_id']); ?></span>
                                                        <span style="font-size:13px; font-weight:500;"><?php echo esc_html($caso['title']); ?></span>
                                                        <?php if ($caso['priority'] === 'Alta'): ?>
                                                            <span style="background:#fee2e2; color:#dc2626; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600;">ALTA</span>
                                                        <?php elseif ($caso['priority'] === 'Media'): ?>
                                                            <span style="background:#fef3c7; color:#d97706; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600;">MEDIA</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($caso['tester'])): ?>
                                                        <span style="font-size:10px; color:#9ca3af;"><?php echo esc_html($caso['tester']); ?><?php echo $caso['tested_at'] ? ' · ' . date('d/m/Y H:i', strtotime($caso['tested_at'])) : ''; ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($caso['bug_id'])): ?>
                                                        <span style="font-size:10px; color:#dc2626; margin-left:6px;">🐛 <?php echo esc_html($caso['bug_id']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="flex-shrink:0; display:flex; align-items:center; gap:6px;">
                                                    <span style="background:<?php echo $st_info[1]; ?>; color:#fff; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; white-space:nowrap;"><?php echo $st_info[0]; ?></span>
                                                    <?php if ($has_ev || $has_cm): ?>
                                                        <button onclick="var d=document.getElementById('<?php echo $case_uid; ?>-detail');d.style.display=d.style.display==='none'?'block':'none';" style="background:none;border:1px solid #d1d5db;border-radius:6px;padding:2px 6px;cursor:pointer;font-size:11px;" title="Ver detalle">
                                                            <?php if ($has_ev): ?>📸<?php echo count($caso['evidence']); ?><?php endif; ?>
                                                            <?php if ($has_cm): ?>💬<?php echo count($caso['comments']); ?><?php endif; ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Expandable detail: evidence + comments -->
                                            <?php if ($has_ev || $has_cm): ?>
                                            <div id="<?php echo $case_uid; ?>-detail" style="display:none; margin-top:8px; padding-top:8px; border-top:1px dashed #e5e7eb;">
                                                <?php if ($has_ev): ?>
                                                <div style="margin-bottom:6px;">
                                                    <span style="font-size:11px; font-weight:600; color:#0d9488;">📸 Evidencias (<?php echo count($caso['evidence']); ?>)</span>
                                                    <?php
                                                    $adm_images = array_filter($caso['evidence'], function($e){ return in_array($e['file_type'], ['image/png','image/jpeg','image/gif','image/webp','']); });
                                                    $adm_files  = array_filter($caso['evidence'], function($e){ return !in_array($e['file_type'], ['image/png','image/jpeg','image/gif','image/webp','']); });
                                                    $adm_images = array_values($adm_images);
                                                    if (count($adm_images) > 0):
                                                        $adm_car_id = $case_uid . '-acar';
                                                    ?>
                                                    <div class="adm-qa-carousel">
                                                        <div class="adm-qa-carousel-track" id="<?php echo $adm_car_id; ?>-track">
                                                            <?php foreach ($adm_images as $ai): ?>
                                                            <div class="adm-qa-carousel-slide">
                                                                <img src="<?php echo esc_url($ai['file_url']); ?>" alt="<?php echo esc_attr($ai['description']?:$ai['file_name']); ?>" onclick="admQaLightbox(this.src)" loading="lazy">
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <?php if (count($adm_images) > 1): ?>
                                                        <button class="adm-qa-carousel-btn prev" onclick="admQaCarouselNav('<?php echo $adm_car_id; ?>',-1)">&#8249;</button>
                                                        <button class="adm-qa-carousel-btn next" onclick="admQaCarouselNav('<?php echo $adm_car_id; ?>',1)">&#8250;</button>
                                                        <div class="adm-qa-carousel-dots" id="<?php echo $adm_car_id; ?>-dots">
                                                            <?php for ($di=0; $di<count($adm_images); $di++): ?>
                                                            <button class="adm-qa-carousel-dot<?php echo $di===0?' active':''; ?>" onclick="admQaCarouselGo('<?php echo $adm_car_id; ?>',<?php echo $di; ?>)"></button>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <div class="adm-qa-carousel-counter" id="<?php echo $adm_car_id; ?>-counter">1 / <?php echo count($adm_images); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (count($adm_files) > 0): ?>
                                                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                                                        <?php foreach ($adm_files as $af): ?>
                                                        <a href="<?php echo esc_url($af['file_url']); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:4px 8px;background:#f3f4f6;border-radius:6px;font-size:11px;text-decoration:none;color:#374151;border:1px solid #e5e7eb;" title="<?php echo esc_attr($af['description']); ?>">📎 <?php echo esc_html($af['file_name']); ?></a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php
                                                    foreach ($caso['evidence'] as $ev) {
                                                        if (!empty($ev['description'])) {
                                                            echo '<p style="font-size:11px;color:#6b7280;margin:4px 0 0;">📝 <em>' . esc_html($ev['description']) . '</em></p>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <?php endif; ?>

                                                <?php if ($has_cm): ?>
                                                <div>
                                                    <span style="font-size:11px; font-weight:600; color:#6366f1;">💬 Comentarios (<?php echo count($caso['comments']); ?>)</span>
                                                    <?php foreach ($caso['comments'] as $cm): ?>
                                                    <div class="qa-comment-item">
                                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                                            <strong style="font-size:11px; color:#374151;"><?php echo esc_html($cm['author_name'] ?: 'Usuario #' . $cm['user_id']); ?></strong>
                                                            <span style="font-size:10px; color:#9ca3af;"><?php echo date('d/m/Y H:i', strtotime($cm['created_at'])); ?></span>
                                                        </div>
                                                        <p style="margin:0; font-size:12px; color:#4b5563; white-space:pre-line;"><?php echo esc_html($cm['comment']); ?></p>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="font-size:12px; color:#9ca3af; margin:4px 0;">No hay casos de prueba registrados.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if ($qp['finished_at']): ?>
                            <p style="margin:10px 0 0; font-size:11px; color:#6b7280;">Finalizado: <?php echo date('d/m/Y H:i', strtotime($qp['finished_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <script>
                    function qaFilterCases(modUid, status, btn) {
                        // Update active button
                        var bar = document.getElementById(modUid + '-filters');
                        if (bar) {
                            bar.querySelectorAll('.qa-filter-btn').forEach(function(b){ b.classList.remove('active'); });
                        }
                        btn.classList.add('active');
                        // Filter case rows
                        document.querySelectorAll('.qa-case-row[data-qa-mod="' + modUid + '"]').forEach(function(row) {
                            if (status === 'all') { row.style.display = ''; }
                            else { row.style.display = row.getAttribute('data-qa-status') === status ? '' : 'none'; }
                        });
                        // Show/hide section headers
                        var body = document.getElementById(modUid + '-body');
                        if (body) {
                            body.querySelectorAll('.qa-section-header').forEach(function(hdr) {
                                var next = hdr.nextElementSibling;
                                var anyVisible = false;
                                while (next && !next.classList.contains('qa-section-header')) {
                                    if (next.classList.contains('qa-case-row') && next.style.display !== 'none') anyVisible = true;
                                    next = next.nextElementSibling;
                                }
                                hdr.style.display = anyVisible ? '' : 'none';
                            });
                        }
                    }
                    // Admin QA Carousel
                    var admQaState = {};
                    function admQaCarouselNav(carId, dir) {
                        var track = document.getElementById(carId + '-track');
                        if (!track) return;
                        var total = track.querySelectorAll('.adm-qa-carousel-slide').length;
                        if (!admQaState[carId]) admQaState[carId] = 0;
                        admQaState[carId] += dir;
                        if (admQaState[carId] < 0) admQaState[carId] = total - 1;
                        if (admQaState[carId] >= total) admQaState[carId] = 0;
                        admQaCarouselGo(carId, admQaState[carId]);
                    }
                    function admQaCarouselGo(carId, idx) {
                        var track = document.getElementById(carId + '-track');
                        if (!track) return;
                        var total = track.querySelectorAll('.adm-qa-carousel-slide').length;
                        admQaState[carId] = idx;
                        track.style.transform = 'translateX(-' + (idx * 100) + '%)';
                        var counter = document.getElementById(carId + '-counter');
                        if (counter) counter.textContent = (idx + 1) + ' / ' + total;
                        var dots = document.getElementById(carId + '-dots');
                        if (dots) {
                            dots.querySelectorAll('.adm-qa-carousel-dot').forEach(function(d, i) {
                                d.classList.toggle('active', i === idx);
                            });
                        }
                    }
                    // Admin QA Lightbox - Gallery with prev/next/zoom
                    var admQaLbImages = [];
                    var admQaLbIdx = 0;
                    var admQaLbZoomLevel = 1;
                    function admQaLightbox(src) {
                        // Collect all carousel images from same carousel
                        var clicked = event.target;
                        var carousel = clicked.closest('.adm-qa-carousel');
                        admQaLbImages = [];
                        if (carousel) {
                            carousel.querySelectorAll('.adm-qa-carousel-slide img').forEach(function(im) {
                                admQaLbImages.push(im.src);
                            });
                            admQaLbIdx = admQaLbImages.indexOf(src);
                            if (admQaLbIdx < 0) admQaLbIdx = 0;
                        } else {
                            admQaLbImages = [src];
                            admQaLbIdx = 0;
                        }
                        admQaLbZoomLevel = 1;
                        admQaLbShow();
                    }
                    function admQaLbShow() {
                        var overlay = document.getElementById('adm-qa-lightbox-overlay');
                        var img = document.getElementById('adm-qa-lightbox-img');
                        var counter = document.getElementById('adm-qa-lb-counter');
                        if (!overlay || !img) return;
                        img.src = admQaLbImages[admQaLbIdx];
                        img.style.transform = 'scale(1)';
                        admQaLbZoomLevel = 1;
                        overlay.classList.add('show');
                        if (counter) counter.textContent = (admQaLbIdx + 1) + ' / ' + admQaLbImages.length;
                        // Show/hide nav arrows
                        overlay.querySelectorAll('.adm-qa-lb-nav').forEach(function(b) {
                            b.style.display = admQaLbImages.length > 1 ? '' : 'none';
                        });
                    }
                    function admQaLbNav(dir) {
                        event.stopPropagation();
                        admQaLbIdx += dir;
                        if (admQaLbIdx < 0) admQaLbIdx = admQaLbImages.length - 1;
                        if (admQaLbIdx >= admQaLbImages.length) admQaLbIdx = 0;
                        admQaLbShow();
                    }
                    function admQaLbZoom(dir) {
                        event.stopPropagation();
                        var img = document.getElementById('adm-qa-lightbox-img');
                        if (!img) return;
                        if (dir === 0) { admQaLbZoomLevel = 1; }
                        else { admQaLbZoomLevel += dir * 0.3; }
                        admQaLbZoomLevel = Math.max(0.3, Math.min(admQaLbZoomLevel, 5));
                        img.style.transform = 'scale(' + admQaLbZoomLevel + ')';
                    }
                    function admQaLightboxClose() {
                        var overlay = document.getElementById('adm-qa-lightbox-overlay');
                        if (overlay) overlay.classList.remove('show');
                    }
                    document.addEventListener('keydown', function(e){
                        var overlay = document.getElementById('adm-qa-lightbox-overlay');
                        if (!overlay || !overlay.classList.contains('show')) return;
                        if (e.key === 'Escape') admQaLightboxClose();
                        else if (e.key === 'ArrowLeft') admQaLbNav(-1);
                        else if (e.key === 'ArrowRight') admQaLbNav(1);
                        else if (e.key === '+' || e.key === '=') admQaLbZoom(1);
                        else if (e.key === '-') admQaLbZoom(-1);
                    });
                    </script>
                    <!-- Lightbox overlay -->
                    <div class="adm-qa-lightbox" id="adm-qa-lightbox-overlay">
                        <button class="adm-qa-lightbox-close" onclick="admQaLightboxClose()">&times;</button>
                        <button class="adm-qa-lb-nav prev" onclick="admQaLbNav(-1)">&#8249;</button>
                        <button class="adm-qa-lb-nav next" onclick="admQaLbNav(1)">&#8250;</button>
                        <div class="adm-qa-lightbox-img-wrap" onclick="admQaLightboxClose()">
                            <img id="adm-qa-lightbox-img" src="" alt="Evidencia" onclick="event.stopPropagation()">
                        </div>
                        <div class="adm-qa-lb-toolbar">
                            <button onclick="admQaLbZoom(-1)" title="Alejar">&#8722;</button>
                            <button onclick="admQaLbZoom(0)" title="Restablecer">&#8634;</button>
                            <button onclick="admQaLbZoom(1)" title="Acercar">&#43;</button>
                            <span class="adm-qa-lb-counter" id="adm-qa-lb-counter"></span>
                        </div>
                    </div>
                    </div><!-- /tab-qa -->
                    <?php endif; ?>

                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$is_designer_only): ?>
            <!-- Timeline / Historial Unificado con Pestañas -->
            <div class="ficha-card full-width">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
                    <h3 style="margin-bottom:0;">📜 Historial / Timeline (Prospecto + Cliente)</h3>
                    <div style="display:flex; gap:8px;">
                        <button class="button" onclick="abrirModalNota(<?php echo $cliente_id; ?>)">📝 Agregar Nota</button>
                        <button class="button button-primary" onclick="abrirModalSeguimiento(<?php echo $cliente_id; ?>)" style="background:#0d9488; border-color:#0d9488;">📅 Agendar Seguimiento</button>
                    </div>
                </div>

                <?php
                // Clasificar timeline items por tipo para las pestañas
                $timeline_tabs = [
                    'todos' => $unified_timeline,
                    'reuniones' => [],
                    'notas' => [],
                    'pagos' => [],
                    'sistema' => [],
                    'seguimientos' => [],
                ];
                
                // Obtener reuniones de seguimiento existentes
                $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
                $followup_meetings = [];
                if ($wpdb->get_var("SHOW TABLES LIKE '$followup_table'") == $followup_table && !empty($cliente['email'])) {
                    $followup_meetings = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM $followup_table WHERE client_email = %s ORDER BY meeting_date DESC, meeting_time DESC",
                        $cliente['email']
                    ), ARRAY_A);
                }
                
                foreach ($unified_timeline as $item) {
                    $dtype = $item['detail_type'] ?? 'nota';
                    if (in_array($dtype, ['reunion', 'llamada'])) {
                        $timeline_tabs['reuniones'][] = $item;
                    }
                    if (in_array($dtype, ['nota', 'email'])) {
                        $timeline_tabs['notas'][] = $item;
                    }
                    if (in_array($dtype, ['pago', 'boleta', 'factura', 'cotizacion'])) {
                        $timeline_tabs['pagos'][] = $item;
                    }
                    if (in_array($dtype, ['legacy', 'separator', 'item_proyecto', 'entregable'])) {
                        $timeline_tabs['sistema'][] = $item;
                    }
                }
                
                $tab_counts = [
                    'todos' => count($unified_timeline),
                    'reuniones' => count($timeline_tabs['reuniones']),
                    'notas' => count($timeline_tabs['notas']),
                    'pagos' => count($timeline_tabs['pagos']),
                    'sistema' => count($timeline_tabs['sistema']),
                    'seguimientos' => count($followup_meetings),
                ];
                ?>

                <!-- Pestañas de navegación -->
                <div class="timeline-tabs" style="display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin:15px 0 0 0; overflow-x:auto;">
                    <button class="tl-tab active" data-tab="todos" onclick="switchTimelineTab('todos')">📋 Todos <span class="tl-tab-count"><?php echo $tab_counts['todos']; ?></span></button>
                    <button class="tl-tab" data-tab="reuniones" onclick="switchTimelineTab('reuniones')">🤝 Reuniones <span class="tl-tab-count"><?php echo $tab_counts['reuniones']; ?></span></button>
                    <button class="tl-tab" data-tab="seguimientos" onclick="switchTimelineTab('seguimientos')">📅 Seguimientos <span class="tl-tab-count"><?php echo $tab_counts['seguimientos']; ?></span></button>
                    <button class="tl-tab" data-tab="notas" onclick="switchTimelineTab('notas')">📝 Notas <span class="tl-tab-count"><?php echo $tab_counts['notas']; ?></span></button>
                    <button class="tl-tab" data-tab="pagos" onclick="switchTimelineTab('pagos')">💰 Pagos <span class="tl-tab-count"><?php echo $tab_counts['pagos']; ?></span></button>
                    <button class="tl-tab" data-tab="sistema" onclick="switchTimelineTab('sistema')">⚙️ Sistema <span class="tl-tab-count"><?php echo $tab_counts['sistema']; ?></span></button>
                </div>

                <?php
                // Función helper para renderizar un item de timeline
                $render_timeline_item = function($h) use ($detail_types, $cliente_id) {
                    $type = $h['detail_type'] ?? 'nota';
                    $config = $detail_types[$type] ?? $detail_types['nota'];
                    $amount_display = '';
                    if (!empty($h['amount']) && $h['amount'] > 0) {
                        $amount_display = '<span style="color:#22c55e; font-weight:bold; margin-left:10px;">$' . number_format($h['amount'], 0, ',', '.') . ' ' . ($h['currency'] ?? 'CLP') . '</span>';
                    }
                    // Helper: detect image URLs
                    $is_img = function($url) {
                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
                    };
                    $attachment_html = '';
                    if (!empty($h['attachment_url'])) {
                        $file_name = !empty($h['attachment_name']) ? $h['attachment_name'] : basename($h['attachment_url']);
                        $preview = '';
                        if ($is_img($h['attachment_url'])) {
                            $preview = '<div style="margin-top:8px;"><a href="' . esc_url($h['attachment_url']) . '" target="_blank"><img src="' . esc_url($h['attachment_url']) . '" alt="' . esc_attr($file_name) . '" loading="lazy" style="max-width:220px; max-height:160px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;"></a></div>';
                        }
                        $attachment_html .= '<div style="margin-top:10px; padding:8px; background:white; border:1px solid #e2e8f0; border-radius:6px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:20px;">📎</span>
                                <a href="' . esc_url($h['attachment_url']) . '" target="_blank" style="text-decoration:none; color:#3b82f6; font-weight:500;">' . esc_html($file_name) . '</a>
                                <a href="' . esc_url($h['attachment_url']) . '" download class="button button-small" style="margin-left:auto;">⬇ Descargar</a>
                            </div>' . $preview . '
                        </div>';
                    }
                    if (!empty($h['metadata'])) {
                        $meta = json_decode($h['metadata'], true);
                        if (!empty($meta['evidencia']) && is_array($meta['evidencia'])) {
                            foreach ($meta['evidencia'] as $link) {
                                $file_name = basename($link);
                                $preview = '';
                                if ($is_img($link)) {
                                    $preview = '<div style="margin-top:8px;"><a href="' . esc_url($link) . '" target="_blank"><img src="' . esc_url($link) . '" alt="' . esc_attr($file_name) . '" loading="lazy" style="max-width:220px; max-height:160px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;"></a></div>';
                                }
                                $attachment_html .= '<div style="margin-top:5px; padding:8px; background:white; border:1px solid #e2e8f0; border-radius:6px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-size:20px;">📎</span>
                                        <a href="' . esc_url($link) . '" target="_blank" style="text-decoration:none; color:#3b82f6; font-weight:500;">' . esc_html($file_name) . '</a>
                                        <a href="' . esc_url($link) . '" download class="button button-small" style="margin-left:auto;">⬇ Descargar</a>
                                    </div>' . $preview . '
                                </div>';
                            }
                        }
                    }
                    ?>
                    <div class="timeline-item <?php echo $type === 'separator' ? 'timeline-separator' : ''; ?>">
                        <div class="timeline-marker" style="background:<?php echo $config['color']; ?>; box-shadow: 0 0 0 2px <?php echo $config['color']; ?>;"></div>
                        <div class="timeline-date"><?php echo date('d/m/Y H:i', $h['timestamp']); ?></div>
                        <div class="timeline-content" style="border-left: 4px solid <?php echo $config['color']; ?>; padding: 15px; margin-bottom: 20px; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); <?php echo $type === 'separator' ? 'background:#fdf2f8; border:2px dashed #7c3aed;' : ''; ?>">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="badge" style="background:<?php echo $config['color']; ?>; color: #fff; padding: 3px 8px; border-radius: 12px; font-size: 11px;"><?php echo $config['icon'] . ' ' . $config['label']; ?></span>
                                    <?php 
                                    $is_notified = false;
                                    if (!empty($h['metadata'])) {
                                        $meta_check = json_decode($h['metadata'], true);
                                        if (isset($meta_check['notificado']) && $meta_check['notificado'] === true) {
                                            $is_notified = true;
                                            $notif_date = isset($meta_check['notificado_at']) ? date('H:i', strtotime($meta_check['notificado_at'])) : '';
                                            echo '<span title="Cliente notificado via email ' . $notif_date . '" style="font-size: 11px; background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 8px; display: inline-flex; align-items: center; gap: 3px;">📧 Notificado</span>';
                                        }
                                    }
                                    if (!$is_notified && !empty($h['id']) && (strpos($h['title'] ?? $h['titulo'], 'ctualiz') !== false)) {
                                        echo '<button type="button" class="button button-small btn-notificar-historial" data-id="' . $h['id'] . '" data-client="' . $cliente_id . '" style="margin-left:5px; font-size:10px; background:#f0fdf4; border:1px solid #16a34a; color:#166534;">📧 Enviar ahora</button>';
                                    }
                                    ?>
                                </div>
                                <div style="display:flex; align-items:center;">
                                    <?php echo $amount_display; ?>
                                    <?php if ($type !== 'separator' && !empty($h['id'])): ?>
                                        <button type="button" class="button button-small btn-editar-timeline" 
                                                data-id="<?php echo $h['id']; ?>" 
                                                data-source="<?php echo $h['source']; ?>"
                                                data-title="<?php echo esc_attr($h['title'] ?? $h['titulo']); ?>"
                                                data-description="<?php echo esc_attr($h['description'] ?? $h['descripcion']); ?>"
                                                data-date="<?php echo date('Y-m-d\TH:i', $h['timestamp']); ?>"
                                                data-amount="<?php echo esc_attr($h['amount'] ?? 0); ?>"
                                                style="margin-left:8px; height: 24px; line-height: 22px; padding: 0 6px;" title="Editar">✎</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h4 style="margin:5px 0 8px; font-size: 14px;"><?php echo esc_html($h['title'] ?? $h['titulo']); ?></h4>
                            <p style="white-space: pre-wrap; margin-bottom: 0; color: #555;"><?php echo nl2br(esc_html($h['description'] ?? $h['descripcion'])); ?></p>
                            <?php echo $attachment_html; ?>
                        </div>
                    </div>
                    <?php
                };
                
                // Función para renderizar contenido vacío
                $render_empty = function($msg) {
                    echo '<p style="color:#888; font-style:italic; padding:20px 0;">' . $msg . '</p>';
                };
                
                // Función para renderizar lista de items con paginación
                $render_tab_content = function($items, $tab_name, $max_visible = 10) use ($render_timeline_item, $render_empty) {
                    if (empty($items)) {
                        $render_empty('No hay registros en esta categoría.');
                        return;
                    }
                    $total = count($items);
                    $shown = 0;
                    foreach ($items as $idx => $h) {
                        $hidden = ($idx >= $max_visible) ? 'style="display:none;"' : '';
                        echo '<div class="tl-page-item tl-page-' . $tab_name . '" ' . $hidden . '>';
                        $render_timeline_item($h);
                        echo '</div>';
                        $shown++;
                    }
                    if ($total > $max_visible) {
                        echo '<div class="tl-show-more" id="tl-more-' . $tab_name . '">
                            <button class="button" onclick="mostrarMasTimeline(\'' . $tab_name . '\', ' . $total . ')" style="width:100%; text-align:center; margin-top:10px;">
                                📄 Ver más (' . ($total - $max_visible) . ' registros restantes)
                            </button>
                        </div>';
                    }
                };
                ?>

                <!-- Pestaña: Todos -->
                <div class="tl-tab-content" id="tl-content-todos" style="display:block;">
                    <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                        <?php $render_tab_content($timeline_tabs['todos'], 'todos', 10); ?>
                    </div>
                </div>

                <!-- Pestaña: Reuniones -->
                <div class="tl-tab-content" id="tl-content-reuniones" style="display:none;">
                    <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                        <?php $render_tab_content($timeline_tabs['reuniones'], 'reuniones', 10); ?>
                    </div>
                </div>

                <!-- Pestaña: Seguimientos -->
                <div class="tl-tab-content" id="tl-content-seguimientos" style="display:none;">
                    <?php if (empty($followup_meetings)): ?>
                        <p style="color:#888; font-style:italic; padding:20px 0;">No hay reuniones de seguimiento agendadas. Usa el botón "📅 Agendar Seguimiento" para programar una.</p>
                    <?php else: ?>
                        <div style="display:grid; gap:12px; margin-top:15px;">
                        <?php foreach ($followup_meetings as $fm): 
                            $status_colors = ['scheduled' => '#3b82f6', 'completed' => '#22c55e', 'cancelled' => '#ef4444', 'no_show' => '#f59e0b'];
                            $status_labels = ['scheduled' => '📅 Programada', 'completed' => '✅ Completada', 'cancelled' => '❌ Cancelada', 'no_show' => '⚠️ No Asistió'];
                            $fm_status = $fm['status'] ?? 'scheduled';
                            $s_color = $status_colors[$fm_status] ?? '#94a3b8';
                            $s_label = $status_labels[$fm_status] ?? ucfirst($fm_status);
                            $fm_date_f = date('d/m/Y', strtotime($fm['meeting_date']));
                            $fm_time_f = substr($fm['meeting_time'], 0, 5);
                        ?>
                            <div style="border:1px solid #e2e8f0; border-left:4px solid <?php echo $s_color; ?>; border-radius:8px; padding:15px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <span style="background:<?php echo $s_color; ?>; color:#fff; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600;"><?php echo $s_label; ?></span>
                                        <strong style="margin-left:8px; font-size:14px;"><?php echo esc_html($fm['meeting_subject'] ?? 'Reunión de Seguimiento'); ?></strong>
                                    </div>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <?php if (!empty($fm['meet_link'])): ?>
                                            <a href="<?php echo esc_url($fm['meet_link']); ?>" target="_blank" class="button button-small" style="background:#0d9488; color:#fff; border-color:#0d9488;">📹 Meet</a>
                                        <?php endif; ?>
                                        <?php if ($fm_status === 'scheduled'): ?>
                                            <a href="<?php echo admin_url('admin.php?page=automatiza-followup&edit_id=' . $fm['id']); ?>" class="button button-small">✏️ Editar</a>
                                        <?php endif; ?>
                                        <button type="button" class="button button-small btn-eliminar-seguimiento" data-id="<?php echo $fm['id']; ?>" style="color:#ef4444; border-color:#fca5a5; background:#fef2f2;" title="Eliminar cita">🗑️</button>
                                    </div>
                                </div>
                                <div style="margin-top:10px; display:flex; gap:20px; flex-wrap:wrap; font-size:13px; color:#555;">
                                    <span>📅 <?php echo $fm_date_f; ?></span>
                                    <span>🕐 <?php echo $fm_time_f; ?> hrs</span>
                                    <?php if (!empty($fm['notes'])): ?>
                                        <span>📝 <?php echo esc_html(wp_trim_words($fm['notes'], 10)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top:8px; display:flex; gap:10px; font-size:11px;">
                                    <?php if ($fm['email_sent']): ?>
                                        <span style="color:#166534; background:#dcfce7; padding:2px 6px; border-radius:6px;">📧 Email enviado</span>
                                    <?php endif; ?>
                                    <?php if (!empty($fm['confirmed_at'])): ?>
                                        <span style="color:#1e40af; background:#dbeafe; padding:2px 6px; border-radius:6px;">✅ Confirmada <?php echo date('d/m H:i', strtotime($fm['confirmed_at'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($fm['whatsapp_sent'])): ?>
                                        <span style="color:#166534; background:#dcfce7; padding:2px 6px; border-radius:6px;">💬 WhatsApp enviado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pestaña: Notas -->
                <div class="tl-tab-content" id="tl-content-notas" style="display:none;">
                    <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                        <?php $render_tab_content($timeline_tabs['notas'], 'notas', 10); ?>
                    </div>
                </div>

                <!-- Pestaña: Pagos -->
                <div class="tl-tab-content" id="tl-content-pagos" style="display:none;">
                    <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                        <?php $render_tab_content($timeline_tabs['pagos'], 'pagos', 10); ?>
                    </div>
                </div>

                <!-- Pestaña: Sistema -->
                <div class="tl-tab-content" id="tl-content-sistema" style="display:none;">
                    <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                        <?php $render_tab_content($timeline_tabs['sistema'], 'sistema', 10); ?>
                    </div>
                </div>

            </div>
            <?php endif; ?>
        </div>
        
        <!-- Modal Nuevo Proyecto -->
        <div id="modalNuevoProyecto" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:25px; border-radius:8px; width:90%; max-width:600px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
                <h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:10px;">Nuevo Proyecto</h3>
                <form id="formNuevoProyecto">
                    <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">
                    <table class="form-table">
                        <tr>
                            <th><label for="nombre_proyecto">Nombre</label></th>
                            <td><input name="nombre" type="text" id="nombre_proyecto" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="descripcion_proyecto">Descripción</label></th>
                            <td><textarea name="descripcion" id="descripcion_proyecto" rows="4" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="tipo_servicio_proyecto">Tipo de Servicio</label></th>
                            <td><input name="tipo_servicio" type="text" id="tipo_servicio_proyecto" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="precio_proyecto">Precio Acordado</label></th>
                            <td><input name="precio_acordado" type="number" step="0.01" id="precio_proyecto" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="fecha_inicio_proyecto">Fecha Inicio</label></th>
                            <td><input name="fecha_inicio" type="date" id="fecha_inicio_proyecto"></td>
                        </tr>
                        <tr>
                            <th><label for="fecha_entrega_proyecto">Fecha Entrega</label></th>
                            <td><input name="fecha_entrega" type="date" id="fecha_entrega_proyecto"></td>
                        </tr>
                        <tr>
                            <th>Notificación</th>
                            <td><label><input type="checkbox" name="notificar_cliente"> Notificar al cliente por correo</label></td>
                        </tr>
                    </table>
                    <p class="submit" style="text-align:right;">
                        <button type="button" class="button" id="btnCerrarModalProyecto">Cancelar</button>
                        <button type="submit" class="button button-primary">Guardar Proyecto</button>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Modal Agregar Nota -->
        <div id="modalNota" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:400px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0;">Agregar Nota</h3>
                <label>Fecha y Hora:</label>
                <input type="datetime-local" id="notaFecha" style="width:100%; margin-bottom:10px;">
                
                <label>Nota:</label>
                <textarea id="notaTexto" rows="4" style="width:100%; margin-bottom:15px;"></textarea>
                
                <div style="text-align:right;">
                    <button class="button" onclick="cerrarModalNota()">Cancelar</button>
                    <button class="button button-primary" onclick="guardarNota()">Guardar</button>
                </div>
            </div>
        </div>
        
        <!-- Modal Editar Timeline -->
        <div id="modalEditarTimeline" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10001; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:25px; border-radius:8px; width:90%; max-width:500px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
                <h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:10px;">Editar Item del Historial</h3>
                <form id="formEditarTimeline">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    <input type="hidden" name="source" id="edit_item_source">
                    
                    <label style="display:block; margin-bottom:5px;">Título</label>
                    <input type="text" name="title" id="edit_item_title" class="regular-text" style="width:100%; margin-bottom:10px;">
                    
                    <label style="display:block; margin-bottom:5px;">Descripción</label>
                    <textarea name="description" id="edit_item_description" rows="5" style="width:100%; margin-bottom:10px;"></textarea>
                    
                    <label style="display:block; margin-bottom:5px;">Fecha y Hora</label>
                    <input type="datetime-local" name="date" id="edit_item_date" style="width:100%; margin-bottom:10px;">
                    
                    <label style="display:block; margin-bottom:5px;">Monto (Opcional)</label>
                    <input type="number" step="0.01" name="amount" id="edit_item_amount" class="regular-text" style="width:100%; margin-bottom:20px;">
                    
                    <div style="text-align:right;">
                        <button type="button" class="button" id="btnCerrarEditarTimeline">Cancelar</button>
                        <button type="submit" class="button button-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Agendar Reunión de Seguimiento -->
        <div id="modalSeguimiento" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10002; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:25px; border-radius:12px; width:90%; max-width:550px; box-shadow:0 10px 40px rgba(0,0,0,0.3); max-height:90vh; overflow-y:auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #0d9488; padding-bottom:12px; margin-bottom:15px;">
                    <h3 style="margin:0; color:#0d9488;">📅 Agendar Reunión de Seguimiento</h3>
                    <button type="button" onclick="cerrarModalSeguimiento()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#999;">&times;</button>
                </div>
                <form id="formSeguimiento">
                    <input type="hidden" name="cliente_id" id="seg_cliente_id" value="<?php echo $cliente_id; ?>">
                    <input type="hidden" name="client_name" id="seg_client_name" value="<?php echo esc_attr($cliente['nombre'] ?? ''); ?>">
                    <input type="hidden" name="client_email" id="seg_client_email" value="<?php echo esc_attr($cliente['email'] ?? ''); ?>">
                    <input type="hidden" name="company_name" id="seg_company_name" value="<?php echo esc_attr($cliente['empresa'] ?? ''); ?>">
                    <input type="hidden" name="phone" id="seg_phone" value="<?php echo esc_attr($cliente['telefono'] ?? ''); ?>">
                    
                    <!-- Info del cliente -->
                    <div style="background:linear-gradient(135deg, #f0fdfa, #ccfbf1); border:1px solid #14b8a6; border-radius:8px; padding:12px; margin-bottom:15px;">
                        <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                            <span style="font-size:24px;">👤</span>
                            <div>
                                <strong style="color:#0f766e;"><?php echo esc_html($cliente['nombre'] ?? ''); ?></strong><br>
                                <span style="font-size:12px; color:#555;"><?php echo esc_html($cliente['email'] ?? ''); ?> | <?php echo esc_html($cliente['telefono'] ?? ''); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="padding:8px 10px 8px 0; width:130px;"><label for="seg_date">📅 Fecha *</label></th>
                            <td style="padding:8px 0;">
                                <input type="date" name="meeting_date" id="seg_date" required min="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px;">
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:8px 10px 8px 0;"><label for="seg_time">🕐 Hora *</label></th>
                            <td style="padding:8px 0;">
                                <select name="meeting_time" id="seg_time" required style="width:100%; padding:8px; font-size:14px;">
                                    <option value="">-- Selecciona horario --</option>
                                    <option value="08:00">08:00 - 09:00 hrs</option>
                                    <option value="09:00">09:00 - 10:00 hrs</option>
                                    <option value="10:00">10:00 - 11:00 hrs</option>
                                    <option value="11:00">11:00 - 12:00 hrs</option>
                                    <option value="12:00">12:00 - 13:00 hrs</option>
                                    <option value="14:00">14:00 - 15:00 hrs</option>
                                    <option value="15:00">15:00 - 16:00 hrs</option>
                                    <option value="16:00">16:00 - 17:00 hrs</option>
                                    <option value="17:00">17:00 - 18:00 hrs</option>
                                    <option value="18:00">18:00 - 19:00 hrs</option>
                                </select>
                                <div id="seg_availability" style="margin-top:8px; display:none;"></div>
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:8px 10px 8px 0;"><label for="seg_subject">📌 Asunto</label></th>
                            <td style="padding:8px 0;">
                                <input type="text" name="meeting_subject" id="seg_subject" class="large-text" 
                                    value="Reunión de Seguimiento - <?php echo esc_attr($cliente['nombre'] ?? ''); ?>"
                                    placeholder="Reunión de Seguimiento" style="width:100%; padding:8px;">
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:8px 10px 8px 0;"><label for="seg_notes">📝 Notas</label></th>
                            <td style="padding:8px 0;">
                                <textarea name="notes" id="seg_notes" rows="3" class="large-text" 
                                    placeholder="Notas sobre la reunión (solo visible internamente)..." style="width:100%; padding:8px;"></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Opciones Google Calendar y Email -->
                    <div style="margin-top:15px; padding:12px; background:#dbeafe; border:2px solid #3b82f6; border-radius:8px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                            <input type="checkbox" name="create_calendar_event" value="1" checked style="width:18px; height:18px;">
                            <span style="color:#1e40af; font-weight:600;">📅 Crear evento en Google Calendar + Link Meet automático</span>
                        </label>
                    </div>
                    
                    <div style="margin-top:10px; padding:12px; background:#f0fdfa; border:2px solid #14b8a6; border-radius:8px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                            <input type="checkbox" name="send_email" value="1" checked style="width:18px; height:18px;">
                            <span style="color:#0f766e; font-weight:600;">📧 Enviar correo de invitación al cliente</span>
                        </label>
                        <p style="margin:5px 0 0 28px; color:#0d9488; font-size:12px;">Se usará la plantilla corporativa de seguimiento con colores turquesa.</p>
                    </div>
                    
                    <!-- WhatsApp checkbox -->
                    <div style="margin-top:10px; padding:12px; background:#dcfce7; border:2px solid #25D366; border-radius:8px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                            <input type="checkbox" name="send_whatsapp" value="1" <?php echo !empty($cliente['telefono']) ? 'checked' : ''; ?> <?php echo empty($cliente['telefono']) ? 'disabled' : ''; ?> style="width:18px; height:18px;">
                            <span style="color:#166534; font-weight:600;">💬 Enviar WhatsApp con datos de la reunión</span>
                        </label>
                        <?php if (empty($cliente['telefono'])): ?>
                            <p style="margin:5px 0 0 28px; color:#dc2626; font-size:12px;">⚠️ El cliente no tiene teléfono registrado.</p>
                        <?php else: ?>
                            <p style="margin:5px 0 0 28px; color:#15803d; font-size:12px;">Se enviará mensaje con fecha, hora, enlace Meet y botones de confirmar/reagendar/cancelar.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align:right; margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="button" onclick="cerrarModalSeguimiento()">Cancelar</button>
                        <button type="submit" class="button button-primary" id="btnGuardarSeguimiento" style="background:#0d9488; border-color:#0d9488; font-size:14px; padding:4px 20px;">
                            📅 Agendar Reunión
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        var clienteIdActual = 0;
        
        // ========== FICHA TABS (Sección) ==========
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ficha-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var target = this.getAttribute('data-target');
                    var container = this.closest('.ficha-col');
                    // Desactivar tabs hermanos
                    container.querySelectorAll('.ficha-tab').forEach(function(t) { t.classList.remove('active'); });
                    container.querySelectorAll('.ficha-tab-content').forEach(function(c) { c.classList.remove('active'); });
                    // Activar seleccionado
                    this.classList.add('active');
                    var panel = document.getElementById(target);
                    if (panel) panel.classList.add('active');
                });
            });
        });
        
        // ========== TIMELINE TABS ==========
        function switchTimelineTab(tabName) {
            // Ocultar todos los contenidos
            document.querySelectorAll('.tl-tab-content').forEach(function(el) {
                el.style.display = 'none';
            });
            // Desactivar todas las pestañas
            document.querySelectorAll('.tl-tab').forEach(function(el) {
                el.classList.remove('active');
            });
            // Mostrar el contenido seleccionado
            var content = document.getElementById('tl-content-' + tabName);
            if (content) content.style.display = 'block';
            // Activar la pestaña
            var tab = document.querySelector('.tl-tab[data-tab="' + tabName + '"]');
            if (tab) tab.classList.add('active');
        }
        
        function mostrarMasTimeline(tabName, total) {
            document.querySelectorAll('.tl-page-' + tabName).forEach(function(el) {
                el.style.display = 'block';
            });
            var moreBtn = document.getElementById('tl-more-' + tabName);
            if (moreBtn) moreBtn.style.display = 'none';
        }
        
        // ========== MODAL SEGUIMIENTO ==========
        function abrirModalSeguimiento(clienteId) {
            document.getElementById('seg_cliente_id').value = clienteId;
            document.getElementById('seg_date').value = '';
            document.getElementById('seg_time').value = '';
            document.getElementById('seg_notes').value = '';
            document.getElementById('seg_availability').style.display = 'none';
            document.getElementById('modalSeguimiento').style.display = 'flex';
        }
        
        function cerrarModalSeguimiento() {
            document.getElementById('modalSeguimiento').style.display = 'none';
        }
        
        // Verificar disponibilidad en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            var segDate = document.getElementById('seg_date');
            var segTime = document.getElementById('seg_time');
            
            if (segDate && segTime) {
                function checkAvailability() {
                    var date = segDate.value;
                    var time = segTime.value;
                    var availDiv = document.getElementById('seg_availability');
                    
                    if (!date || !time) {
                        availDiv.style.display = 'none';
                        return;
                    }
                    
                    availDiv.style.display = 'block';
                    availDiv.innerHTML = '<span style="color:#666;">⏳ Verificando disponibilidad...</span>';
                    
                    jQuery.post(crmAjax.url, {
                        action: 'followup_check_availability',
                        nonce: crmAjax.nonce,
                        date: date,
                        time: time
                    }, function(response) {
                        if (response.success) {
                            if (response.data.available) {
                                availDiv.innerHTML = '<span style="color:#16a34a; font-weight:600; background:#dcfce7; padding:4px 10px; border-radius:6px;">✅ Horario disponible</span>';
                            } else {
                                availDiv.innerHTML = '<span style="color:#dc2626; font-weight:600; background:#fee2e2; padding:4px 10px; border-radius:6px;">❌ Horario ocupado: ' + (response.data.conflict_details || '') + '</span>';
                            }
                        } else {
                            availDiv.innerHTML = '<span style="color:#f59e0b;">⚠️ No se pudo verificar</span>';
                        }
                    }).fail(function() {
                        availDiv.innerHTML = '<span style="color:#f59e0b;">⚠️ Error al verificar disponibilidad</span>';
                    });
                }
                
                segDate.addEventListener('change', checkAvailability);
                segTime.addEventListener('change', checkAvailability);
            }
            
            // Form submit seguimiento
            var formSeg = document.getElementById('formSeguimiento');
            if (formSeg) {
                formSeg.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var btn = document.getElementById('btnGuardarSeguimiento');
                    var originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '⏳ Agendando...';
                    
                    var formData = new FormData(this);
                    var data = {};
                    formData.forEach(function(value, key) { data[key] = value; });
                    data.action = 'crm_agendar_seguimiento';
                    data.nonce = crmAjax.nonce;
                    
                    jQuery.post(crmAjax.url, data, function(response) {
                        if (response.success) {
                            alert(response.data.message || '✅ Reunión agendada con éxito.');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (response.data || 'Error al agendar.'));
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }).fail(function() {
                        alert('Error de conexión');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
                });
            }
        });
        
        // ========== ELIMINAR SEGUIMIENTO ==========
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-eliminar-seguimiento');
            if (!btn) return;
            var meetingId = btn.getAttribute('data-id');
            if (!confirm('⚠️ ¿Estás seguro de eliminar esta cita de seguimiento?\n\nEsto eliminará la reunión y el evento de Google Calendar asociado (si existe). Esta acción no se puede deshacer.')) {
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '⏳';
            jQuery.post(crmAjax.url, {
                action: 'crm_eliminar_seguimiento',
                nonce: crmAjax.nonce,
                meeting_id: meetingId
            }, function(response) {
                if (response.success) {
                    alert(response.data.message || '🗑️ Eliminado correctamente.');
                    location.reload();
                } else {
                    alert('❌ Error: ' + (response.data || 'No se pudo eliminar.'));
                    btn.disabled = false;
                    btn.innerHTML = '🗑️';
                }
            }).fail(function() {
                alert('Error de conexión');
                btn.disabled = false;
                btn.innerHTML = '🗑️';
            });
        });
        
        // ========== EXISTING FUNCTIONS ==========
        function abrirModalNota(id) {
            clienteIdActual = id;
            var now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('notaFecha').value = now.toISOString().slice(0,16);
            document.getElementById('notaTexto').value = '';
            document.getElementById('modalNota').style.display = 'flex';
        }
        
        function cerrarModalNota() {
            document.getElementById('modalNota').style.display = 'none';
        }
        
        function guardarNota() {
            var nota = document.getElementById('notaTexto').value;
            var fecha = document.getElementById('notaFecha').value;
            
            if (nota) {
                jQuery.post(crmAjax.url, {
                    action: 'crm_guardar_nota',
                    nonce: crmAjax.nonce,
                    cliente_id: clienteIdActual,
                    nota: nota,
                    fecha: fecha
                }, function(r) {
                    if (r.success) location.reload();
                    else alert('Error al guardar');
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico diario del mes actual (ficha cliente)
            var fichaDiarioCanvas = document.getElementById('chartFichaDiario');
            if (fichaDiarioCanvas) {
                new Chart(fichaDiarioCanvas, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($fichaDiarioLabels); ?>.map(d => 'Día ' + d),
                        datasets: [{
                            label: 'Costo USD',
                            data: <?php echo json_encode($fichaDiarioCostos); ?>,
                            backgroundColor: 'rgba(0,115,170,0.7)',
                            borderColor: '#0073aa',
                            borderWidth: 1,
                            yAxisID: 'y'
                        }, {
                            label: 'Tokens',
                            data: <?php echo json_encode($fichaDiarioTokens); ?>,
                            type: 'line',
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70,180,80,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            borderWidth: 2,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Costo USD' }, ticks: { callback: v => '$' + v.toFixed(4) } },
                            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Tokens' } }
                        }
                    }
                });
            }
            
            // Gráfico mensual histórico
            var dataMensual = <?php echo json_encode(array_reverse($consumoMensual)); ?>;
            new Chart(document.getElementById('chartMensual'), {
                type: 'bar',
                data: {
                    labels: dataMensual.map(d => d.mes),
                    datasets: [{
                        label: 'Costo USD',
                        data: dataMensual.map(d => parseFloat(d.costo)),
                        backgroundColor: 'rgba(70, 180, 80, 0.7)'
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });

            // --- Lógica para Proyectos ---
            const modal = document.getElementById('modalNuevoProyecto');
            const btnAbrir = document.getElementById('btnAbrirModalProyecto');
            const btnCerrar = document.getElementById('btnCerrarModalProyecto');
            
            if (btnAbrir) {
                btnAbrir.addEventListener('click', () => modal.style.display = 'flex');
            }
            if (btnCerrar) {
                btnCerrar.addEventListener('click', () => modal.style.display = 'none');
            }
            window.addEventListener('click', (event) => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });

            // AJAX para crear nuevo proyecto
            const formNuevoProyecto = document.getElementById('formNuevoProyecto');
            if(formNuevoProyecto) {
                formNuevoProyecto.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData.entries());
                    data.action = 'crm_crear_proyecto';
                    data.nonce = crmAjax.nonce;
                    data.notificar_cliente = formData.has('notificar_cliente');

                    jQuery.post(crmAjax.url, data, function(response) {
                        if (response.success) {
                            alert('Proyecto creado con éxito.');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data || 'Ocurrió un error.'));
                        }
                    });
                });
            }

            // --- Lógica Editar Timeline ---
            const modalEditTL = document.getElementById('modalEditarTimeline');
            const btnCerrarEditTL = document.getElementById('btnCerrarEditarTimeline');

            document.querySelectorAll('.btn-editar-timeline').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('edit_item_id').value = this.getAttribute('data-id');
                    document.getElementById('edit_item_source').value = this.getAttribute('data-source');
                    document.getElementById('edit_item_title').value = this.getAttribute('data-title');
                    document.getElementById('edit_item_description').value = this.getAttribute('data-description');
                    document.getElementById('edit_item_date').value = this.getAttribute('data-date');
                    document.getElementById('edit_item_amount').value = this.getAttribute('data-amount');
                    
                    modalEditTL.style.display = 'flex';
                });
            });

            if(btnCerrarEditTL) {
                btnCerrarEditTL.addEventListener('click', () => modalEditTL.style.display = 'none');
            }
            
            window.addEventListener('click', (event) => {
                if (event.target == modalEditTL) {
                    modalEditTL.style.display = 'none';
                }
                var modalSeg = document.getElementById('modalSeguimiento');
                if (event.target == modalSeg) {
                    modalSeg.style.display = 'none';
                }
            });

            const formEditarTimeline = document.getElementById('formEditarTimeline');
            if(formEditarTimeline) {
                formEditarTimeline.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData.entries());
                    data.action = 'crm_update_timeline_item';
                    data.nonce = crmAjax.nonce; 
                    
                    jQuery.post(crmAjax.url, data, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data || 'Error al actualizar.'));
                        }
                    });
                });
            }

            // AJAX para actualizar proyectos existentes
            const formsActualizar = document.querySelectorAll('.form-actualizar-proyecto');
            formsActualizar.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'crm_actualizar_proyecto');
                    formData.append('nonce', crmAjax.nonce);
                    if (!formData.has('notificar_actualizacion')) {
                        formData.append('notificar_actualizacion', 'false');
                    }

                    jQuery.ajax({
                        url: crmAjax.url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message || 'Proyecto actualizado con éxito.');
                                location.reload();
                            } else {
                                alert('Error: ' + (response.data || 'Ocurrió un error.'));
                            }
                        },
                        error: function() {
                            alert('Error de conexión.');
                        }
                    });
                });
            });

            // LOGICA PARA ENVIAR NOTIFICACION MANUAL EN TIMELINE
            document.body.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('btn-notificar-historial')) {
                    e.preventDefault();
                    const btn = e.target;
                    const histId = btn.getAttribute('data-id');
                    const clientId = btn.getAttribute('data-client');
                    
                    if(confirm('¿Confirmas enviar la notificación por correo al cliente ahora?')) {
                        const originalText = btn.textContent;
                        btn.disabled = true;
                        btn.textContent = 'Enviando...';
                        
                        jQuery.post(crmAjax.url, {
                            action: 'crm_enviar_notificacion_historial',
                            nonce: crmAjax.nonce,
                            historial_id: histId,
                            cliente_id: clientId
                        }, function(response) {
                            if (response.success) {
                                alert('Notificación enviada correctamente.');
                                location.reload();
                            } else {
                                alert('Error: ' + (response.data || 'Error desconocido'));
                                btn.disabled = false;
                                btn.textContent = originalText;
                            }
                        }).fail(function() {
                            alert('Error de conexión');
                            btn.disabled = false;
                            btn.textContent = originalText;
                        });
                    }
                }
            });
        });
        </script>
        
        <?php $this->render_styles(); ?>
        <?php
    }
    
    // ========== AGENTE IA CONSULTOR ==========
    public function render_agente() {
        $user_id = get_current_user_id();
        $session_id = 'aria_' . $user_id . '_' . time();
        $nonce = wp_create_nonce('aria_nonce');
        ?>
        <div class="wrap crm-wrap">
            <h1>🤖 MAXTECH - Manager de AutomatizaTech eXpert</h1>
            <p>Tu asistente IA con soporte para archivos, voz e historial de conversaciones.</p>
            
            <div class="aria-main-container">
                <!-- Sidebar con historial -->
                <div class="aria-sidebar">
                    <button class="aria-new-chat" onclick="ariaNuevaSesionMain()">➕ Nueva conversación</button>
                    <h4>📜 Historial</h4>
                    <div id="ariaSidebarHistorial" class="aria-historial-list"></div>
                </div>
                
                <!-- Chat principal -->
                <div class="aria-chat-main">
                    <div class="aria-chat-messages" id="ariaMainMessages">
                        <div class="aria-welcome">
                            <div class="aria-welcome-avatar">🤖</div>
                            <h2>¡Hola! Soy MAXTECH</h2>
                            <p>Tu Manager eXpert de AutomatizaTech</p>
                            <div class="aria-capabilities">
                                <div class="capability">📊 Consultas del CRM</div>
                                <div class="capability">📎 Análisis de archivos</div>
                                <div class="capability">🎤 Entrada por voz</div>
                                <div class="capability">🔊 Respuestas en audio</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Archivos adjuntos -->
                    <div class="aria-main-attachments" id="ariaMainAttachments" style="display:none;"></div>
                    
                    <!-- Controles de grabación -->
                    <div class="aria-main-recording" id="ariaMainRecording" style="display:none;">
                        🎙️ Grabando... <span id="mainRecordTime">0:00</span>
                        <button onclick="stopMainRecording()" class="button">Detener</button>
                    </div>
                    
                    <!-- Input área -->
                    <div class="aria-main-input">
                        <div class="aria-input-wrapper">
                            <button class="aria-main-btn attach" onclick="document.getElementById('ariaMainFileInput').click()" title="Adjuntar archivo">📎</button>
                            <input type="text" id="ariaMainInput" placeholder="Escribe tu mensaje o usa el micrófono...">
                            <button class="aria-main-btn mic" id="ariaMainMic" onclick="toggleMainRecording()" title="Grabar voz">🎤</button>
                            <button class="aria-main-btn send" onclick="ariaMainEnviar()">Enviar ➤</button>
                        </div>
                        <div class="aria-main-options">
                            <label><input type="checkbox" id="ariaMainVoice"> 🔊 Responder con voz</label>
                        </div>
                    </div>
                    
                    <input type="file" id="ariaMainFileInput" style="display:none;" accept="image/*,.pdf,.txt,.csv" onchange="ariaMainAdjuntar(this)">
                </div>
                
                <!-- Consultas rápidas -->
                <div class="aria-quick-panel">
                    <h4>⚡ Consultas rápidas</h4>
                    <button onclick="ariaQuick('¿Cuánto debo facturar este mes?')">💰 Facturación del mes</button>
                    <button onclick="ariaQuick('¿Cuál es el cliente con mayor consumo?')">🏆 Top cliente</button>
                    <button onclick="ariaQuick('Dame un resumen de los demos activos')">🧪 Demos activos</button>
                    <button onclick="ariaQuick('Compara este mes con el anterior')">📊 Comparar meses</button>
                    <button onclick="ariaQuick('Dame un resumen ejecutivo del negocio')">📋 Resumen ejecutivo</button>
                    <button onclick="ariaQuick('¿Qué proyectos tienen entregas pendientes?')">🚀 Proyectos pendientes</button>
                </div>
            </div>
        </div>
        
        <style>
        .aria-main-container {
            display: grid;
            grid-template-columns: 250px 1fr 200px;
            gap: 20px;
            max-width: 1400px;
            height: calc(100vh - 150px);
            min-height: 600px;
        }
        
        /* Sidebar */
        .aria-sidebar {
            background: #1e293b;
            border-radius: 12px;
            padding: 16px;
            color: white;
            overflow-y: auto;
        }
        .aria-new-chat {
            width: 100%;
            padding: 12px;
            background: #6366f1;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background 0.2s;
        }
        .aria-new-chat:hover { background: #4f46e5; }
        .aria-sidebar h4 { margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; color: #94a3b8; }
        .aria-historial-list { display: flex; flex-direction: column; gap: 8px; }
        .aria-historial-item {
            padding: 10px;
            background: #334155;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }
        .aria-historial-item:hover { background: #475569; }
        .aria-historial-item .fecha { color: #94a3b8; font-size: 10px; }
        
        /* Chat principal */
        .aria-chat-main {
            background: white;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .aria-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: #f8fafc;
        }
        
        /* Welcome */
        .aria-welcome {
            text-align: center;
            padding: 60px 20px;
        }
        .aria-welcome-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        .aria-welcome h2 { margin: 0 0 8px 0; color: #1e293b; }
        .aria-welcome p { color: #64748b; margin: 0 0 30px 0; }
        .aria-capabilities {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .capability {
            padding: 10px 16px;
            background: white;
            border-radius: 20px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
        }
        
        /* Mensajes */
        .aria-main-msg {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            animation: fadeIn 0.3s ease;
        }
        .aria-main-msg.user { flex-direction: row-reverse; }
        .aria-main-msg .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .aria-main-msg.bot .avatar { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .aria-main-msg.user .avatar { background: #64748b; }
        .aria-main-msg .content {
            max-width: 70%;
            padding: 14px 18px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.6;
        }
        .aria-main-msg.bot .content {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px 16px 16px 4px;
        }
        .aria-main-msg.user .content {
            background: #6366f1;
            color: white;
            border-radius: 16px 16px 4px 16px;
        }
        
        /* Input área */
        .aria-main-input {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }
        .aria-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .aria-input-wrapper input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 30px;
            font-size: 14px;
            outline: none;
            transition: border 0.2s;
        }
        .aria-input-wrapper input:focus { border-color: #6366f1; }
        .aria-main-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s;
        }
        .aria-main-btn.attach { background: #f1f5f9; }
        .aria-main-btn.attach:hover { background: #e2e8f0; }
        .aria-main-btn.mic { background: #f1f5f9; }
        .aria-main-btn.mic:hover { background: #fef3c7; }
        .aria-main-btn.mic.recording { background: #fee2e2; animation: pulse 1s infinite; }
        .aria-main-btn.send { background: #6366f1; color: white; width: auto; padding: 0 24px; border-radius: 30px; }
        .aria-main-btn.send:hover { background: #4f46e5; }
        .aria-main-options { margin-top: 10px; font-size: 13px; color: #64748b; }
        
        /* Attachments */
        .aria-main-attachments {
            padding: 10px 24px;
            background: #f1f5f9;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .aria-main-attach-item {
            display: flex;
            align-items: center;
            gap: 6px;
            background: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
        }
        .aria-main-attach-item .remove { color: #ef4444; cursor: pointer; }
        
        /* Recording */
        .aria-main-recording {
            padding: 12px 24px;
            background: #fee2e2;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        /* Quick panel */
        .aria-quick-panel {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            height: fit-content;
        }
        .aria-quick-panel h4 { margin: 0 0 16px 0; font-size: 14px; color: #1e293b; }
        .aria-quick-panel button {
            display: block;
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-align: left;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .aria-quick-panel button:hover {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
        }
        
        /* Audio player */
        .aria-audio-inline {
            margin-top: 10px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .aria-audio-inline button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: #6366f1;
            color: white;
            cursor: pointer;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        </style>
        
        <script>
        var ariaMainSession = '<?php echo $session_id; ?>';
        var ariaMainNonce = '<?php echo $nonce; ?>';
        var ariaMainArchivos = [];
        var mainRecognition = null;
        var mainRecordInterval = null;
        var welcomeShown = true;
        
        // Cargar historial al iniciar
        jQuery(document).ready(function() {
            cargarHistorialSidebar();
        });
        
        function cargarHistorialSidebar() {
            jQuery.post(ajaxurl, {
                action: 'aria_historial',
                nonce: ariaMainNonce
            }, function(response) {
                if (response.success) {
                    var html = '';
                    response.data.forEach(function(s) {
                        var preview = (s.primer_mensaje || 'Sin título').substring(0, 30);
                        html += '<div class="aria-historial-item" onclick="cargarSesionMain(\'' + s.session_id + '\')">';
                        html += '<div>' + preview + '...</div>';
                        html += '<div class="fecha">' + s.mensajes + ' msgs</div>';
                        html += '</div>';
                    });
                    document.getElementById('ariaSidebarHistorial').innerHTML = html || '<div style="color:#94a3b8;font-size:12px;">Sin conversaciones</div>';
                }
            });
        }
        
        function ariaMainEnviar() {
            var input = document.getElementById('ariaMainInput');
            var mensaje = input.value.trim();
            if (!mensaje && ariaMainArchivos.length === 0) return;
            
            // Ocultar welcome si está visible
            if (welcomeShown) {
                document.querySelector('.aria-welcome').style.display = 'none';
                welcomeShown = false;
            }
            
            var messages = document.getElementById('ariaMainMessages');
            
            // Mensaje del usuario
            var attachStr = ariaMainArchivos.length > 0 ? '<br><small>📎 ' . ariaMainArchivos.length . ' archivo(s)</small>' : '';
            messages.innerHTML += '<div class="aria-main-msg user"><div class="avatar">👤</div><div class="content">' + (mensaje || 'Archivo adjunto') + attachStr + '</div></div>';
            
            // Loading
            var loadId = 'mainload-' + Date.now();
            messages.innerHTML += '<div class="aria-main-msg bot" id="' + loadId + '"><div class="avatar">🤖</div><div class="content" style="color:#94a3b8;">Pensando...</div></div>';
            messages.scrollTop = messages.scrollHeight;
            
            input.value = '';
            
            jQuery.post(ajaxurl, {
                action: 'aria_chat',
                nonce: ariaMainNonce,
                mensaje: mensaje,
                session_id: ariaMainSession,
                archivos: JSON.stringify(ariaMainArchivos)
            }, function(response) {
                var el = document.getElementById(loadId);
                if (response.success) {
                    var texto = response.data.respuesta;
                    texto = texto.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                    texto = texto.replace(/\n/g, '<br>');
                    
                    var audioHtml = '';
                    if (document.getElementById('ariaMainVoice').checked) {
                        audioHtml = '<div class="aria-audio-inline"><button onclick="playMainAudio(\'' + loadId + '\', \'' + encodeURIComponent(response.data.respuesta.substring(0, 500)) + '\')">▶️</button><span>Reproducir</span></div>';
                    }
                    
                    el.querySelector('.content').innerHTML = texto + audioHtml;
                } else {
                    el.querySelector('.content').innerHTML = '❌ Error al procesar';
                }
                messages.scrollTop = messages.scrollHeight;
            });
            
            ariaMainArchivos = [];
            document.getElementById('ariaMainAttachments').style.display = 'none';
            document.getElementById('ariaMainAttachments').innerHTML = '';
        }
        
        function ariaQuick(texto) {
            document.getElementById('ariaMainInput').value = texto;
            ariaMainEnviar();
        }
        
        function ariaNuevaSesionMain() {
            jQuery.post(ajaxurl, {
                action: 'aria_nueva_sesion',
                nonce: ariaMainNonce
            }, function(response) {
                if (response.success) {
                    ariaMainSession = response.data.session_id;
                    document.getElementById('ariaMainMessages').innerHTML = '<div class="aria-welcome"><div class="aria-welcome-avatar">🤖</div><h2>Nueva conversación</h2><p>¿En qué te puedo ayudar?</p></div>';
                    welcomeShown = true;
                    cargarHistorialSidebar();
                }
            });
        }
        
        function cargarSesionMain(sessionId) {
            ariaMainSession = sessionId;
            
            // Ocultar welcome
            var welcome = document.querySelector('.aria-welcome');
            if (welcome) welcome.style.display = 'none';
            welcomeShown = false;
            
            // Cargar mensajes de esa sesión
            var chatDiv = document.getElementById('ariaMainMessages');
            chatDiv.innerHTML = '<div class="aria-main-msg bot"><div class="msg-avatar">⏳</div><div class="msg-content"><p>Cargando conversación...</p></div></div>';
            
            jQuery.post(ajaxurl, {
                action: 'aria_cargar_sesion',
                nonce: ariaMainNonce,
                session_id: sessionId
            }, function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    response.data.forEach(function(msg) {
                        var isBot = msg.role === 'assistant';
                        var avatar = isBot ? '🤖' : '👤';
                        var clase = isBot ? 'bot' : 'user';
                        var contenido = msg.content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
                        html += '<div class="aria-main-msg ' + clase + '"><div class="msg-avatar">' + avatar + '</div><div class="msg-content"><p>' + contenido + '</p></div></div>';
                    });
                    chatDiv.innerHTML = html;
                    chatDiv.scrollTop = chatDiv.scrollHeight;
                } else {
                    chatDiv.innerHTML = '<div class="aria-main-msg bot"><div class="msg-avatar">🤖</div><div class="msg-content"><p>No se encontraron mensajes en esta sesión.</p></div></div>';
                }
            });
        }
        
        function ariaMainAdjuntar(input) {
            if (!input.files.length) return;
            var file = input.files[0];
            var formData = new FormData();
            formData.append('archivo', file);
            formData.append('action', 'aria_upload');
            formData.append('nonce', ariaMainNonce);
            
            var attachDiv = document.getElementById('ariaMainAttachments');
            attachDiv.style.display = 'flex';
            var tempId = 'mainatt-' + Date.now();
            attachDiv.innerHTML += '<div class="aria-main-attach-item" id="' + tempId + '">📄 ' + file.name + ' <span class="remove" onclick="removeMainAttach(\'' + tempId + '\')">×</span></div>';
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        ariaMainArchivos.push(response.data);
                    }
                }
            });
            input.value = '';
        }
        
        function removeMainAttach(id) {
            document.getElementById(id).remove();
            if (document.getElementById('ariaMainAttachments').children.length === 0) {
                document.getElementById('ariaMainAttachments').style.display = 'none';
            }
        }
        
        function toggleMainRecording() {
            if (mainRecognition) {
                stopMainRecording();
            } else {
                startMainRecording();
            }
        }
        
        function startMainRecording() {
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                mainRecognition = new SpeechRecognition();
                mainRecognition.lang = 'es-ES';
                mainRecognition.continuous = true;
                mainRecognition.interimResults = true;
                
                document.getElementById('ariaMainMic').classList.add('recording');
                document.getElementById('ariaMainRecording').style.display = 'flex';
                
                var seconds = 0;
                mainRecordInterval = setInterval(function() {
                    seconds++;
                    document.getElementById('mainRecordTime').textContent = Math.floor(seconds/60) + ':' + String(seconds%60).padStart(2, '0');
                }, 1000);
                
                mainRecognition.onresult = function(event) {
                    var transcript = '';
                    for (var i = event.resultIndex; i < event.results.length; i++) {
                        transcript += event.results[i][0].transcript;
                    }
                    document.getElementById('ariaMainInput').value = transcript;
                };
                
                mainRecognition.start();
            } else {
                alert('Tu navegador no soporta reconocimiento de voz');
            }
        }
        
        function stopMainRecording() {
            if (mainRecognition) {
                mainRecognition.stop();
                mainRecognition = null;
            }
            clearInterval(mainRecordInterval);
            document.getElementById('ariaMainMic').classList.remove('recording');
            document.getElementById('ariaMainRecording').style.display = 'none';
        }
        
        function playMainAudio(id, texto) {
            jQuery.post(ajaxurl, {
                action: 'aria_tts',
                nonce: ariaMainNonce,
                texto: decodeURIComponent(texto)
            }, function(response) {
                if (response.success) {
                    var audio = new Audio(response.data.audio_url);
                    audio.play();
                }
            });
        }
        
        document.getElementById('ariaMainInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') ariaMainEnviar();
        });
        </script>
        
        <?php $this->render_styles(); ?>
        <?php
    }
    
    // ========== AJAX: AGENTE CONSULTA ==========
    public function ajax_agente_consulta() {
        check_ajax_referer('crm_nonce', 'nonce');
        
        global $wpdb;
        $consulta = sanitize_text_field($_POST['consulta']);
        $respuesta = $this->procesar_consulta_agente($consulta);
        
        wp_send_json_success($respuesta);
    }
    
    private function procesar_consulta_agente($consulta) {
        global $wpdb;
        $consulta_lower = strtolower($consulta);
        
        // Facturación del mes
        if (strpos($consulta_lower, 'factur') !== false && strpos($consulta_lower, 'mes') !== false) {
            $stats = $wpdb->get_row("
                SELECT 
                    COALESCE(SUM(cost_estimated), 0) as total,
                    COUNT(DISTINCT client_identifier) as clientes
                FROM {$this->tabla_ai}
                WHERE client_identifier LIKE 'cliente_%'
                AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
            ", ARRAY_A);
            
            $facturar = $stats['total'] * 1.3;
            return "💰 <strong>Facturación del mes actual:</strong><br>
                    • Costo OpenAI: \${$stats['total']}<br>
                    • <strong>A facturar (+30%): \$" . number_format($facturar, 2) . "</strong><br>
                    • Clientes activos: {$stats['clientes']}";
        }
        
        // Top cliente
        if (strpos($consulta_lower, 'top') !== false || strpos($consulta_lower, 'más consumo') !== false || strpos($consulta_lower, 'mayor') !== false) {
            $top = $wpdb->get_row("
                SELECT client_identifier, SUM(cost_estimated) as total, SUM(total_tokens) as tokens
                FROM {$this->tabla_ai}
                WHERE client_identifier LIKE 'cliente_%'
                AND MONTH(created_at) = MONTH(NOW())
                GROUP BY client_identifier
                ORDER BY total DESC
                LIMIT 1
            ", ARRAY_A);
            
            if ($top) {
                $nombre = ucwords(str_replace(['cliente_', 'demo_', '_'], ['', '', ' '], $top['client_identifier']));
                return "🏆 <strong>Cliente con mayor consumo:</strong><br>
                        • <strong>{$nombre}</strong><br>
                        • Tokens: " . number_format($top['tokens']) . "<br>
                        • Costo: \$" . number_format($top['total'], 4) . "<br>
                        • <a href='?page=automatiza-crm-ficha&ai_id=" . urlencode($top['client_identifier']) . "'>Ver ficha completa</a>";
            }
            return "No hay datos de clientes este mes.";
        }
        
        // Demos activos
        if (strpos($consulta_lower, 'demo') !== false) {
            $demos = $wpdb->get_results("
                SELECT client_identifier, SUM(cost_estimated) as costo, COUNT(*) as interacciones
                FROM {$this->tabla_ai}
                WHERE client_identifier LIKE 'demo_%'
                GROUP BY client_identifier
            ", ARRAY_A);
            
            if (empty($demos)) return "🧪 No hay demos activos actualmente.";
            
            $lista = "🧪 <strong>Demos activos (" . count($demos) . "):</strong><br>";
            foreach ($demos as $d) {
                $nombre = ucwords(str_replace(['demo_', '_'], ['', ' '], $d['client_identifier']));
                $lista .= "• {$nombre}: {$d['interacciones']} interacciones, \${$d['costo']} invertido<br>";
            }
            return $lista;
        }
        
        // Comparar meses
        if (strpos($consulta_lower, 'comparar') !== false || strpos($consulta_lower, 'anterior') !== false) {
            $actual = $wpdb->get_var("SELECT COALESCE(SUM(cost_estimated), 0) FROM {$this->tabla_ai} WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
            $anterior = $wpdb->get_var("SELECT COALESCE(SUM(cost_estimated), 0) FROM {$this->tabla_ai} WHERE MONTH(created_at) = MONTH(NOW()) - 1");
            
            $variacion = $anterior > 0 ? (($actual - $anterior) / $anterior) * 100 : 0;
            $emoji = $variacion > 0 ? '📈' : '📉';
            
            return "📊 <strong>Comparación mensual:</strong><br>
                    • Mes actual: \$" . number_format($actual, 4) . "<br>
                    • Mes anterior: \$" . number_format($anterior, 4) . "<br>
                    • {$emoji} Variación: " . ($variacion >= 0 ? '+' : '') . number_format($variacion, 1) . "%";
        }
        
        // Buscar cliente específico
        if (strpos($consulta_lower, 'cliente') !== false || strpos($consulta_lower, 'consumo de') !== false) {
            // Extraer nombre del cliente
            preg_match('/(?:cliente|consumo de)\s+([a-záéíóúñ\s]+)/i', $consulta, $matches);
            if (!empty($matches[1])) {
                $buscar = '%' . trim($matches[1]) . '%';
                $cliente = $wpdb->get_row($wpdb->prepare("
                    SELECT client_identifier, SUM(cost_estimated) as total, SUM(total_tokens) as tokens, COUNT(*) as requests
                    FROM {$this->tabla_ai}
                    WHERE client_identifier LIKE %s
                    GROUP BY client_identifier
                ", $buscar), ARRAY_A);
                
                if ($cliente) {
                    $nombre = ucwords(str_replace(['cliente_', 'demo_', '_'], ['', '', ' '], $cliente['client_identifier']));
                    return "📋 <strong>Información de {$nombre}:</strong><br>
                            • Peticiones totales: {$cliente['requests']}<br>
                            • Tokens: " . number_format($cliente['tokens']) . "<br>
                            • Costo total: \$" . number_format($cliente['total'], 4) . "<br>
                            • <a href='?page=automatiza-crm-ficha&ai_id=" . urlencode($cliente['client_identifier']) . "'>Ver ficha completa</a>";
                }
                return "No encontré un cliente con ese nombre. Intenta con otro término.";
            }
        }
        
        // Respuesta genérica
        return "🤔 No entendí completamente tu consulta. Puedo ayudarte con:<br>
                • <em>¿Cuánto debo facturar este mes?</em><br>
                • <em>¿Cuál es el cliente con más consumo?</em><br>
                • <em>Consumo del cliente [nombre]</em><br>
                • <em>Comparar este mes con el anterior</em><br>
                • <em>Dame un resumen de los demos</em>";
    }
    
    // ========== VISTA PÚBLICA TIMELINE ==========
    
    private function _generar_token($cliente_id, $email) {
        return md5($cliente_id . 'AUTOMATIZA_CRM_V2' . $email);
    }
    
    public function render_public_timeline() {
        if (is_admin()) return;
        
        if (isset($_GET['crm_view']) && $_GET['crm_view'] === 'timeline') {
            global $wpdb;
            
            $cliente_id = intval($_GET['cid'] ?? 0);
            $token = $_GET['token'] ?? '';
            
            if (!$cliente_id || !$token) {
                wp_die('Enlace no válido.');
            }
            
            $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));
            
            if (!$cliente || $this->_generar_token($cliente->id, $cliente->email) !== $token) {
                wp_die('Acceso denegado o enlace expirado.');
            }
            
            // Datos para la vista
            $proyectos = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tabla_proyectos} WHERE cliente_id = %d ORDER BY created_at DESC", $cliente_id), ARRAY_A);
            
            // --- NUEVO: Timeline Unificado (Cliente + Prospecto) ---
            $unified_timeline = [];
            
            // 1. Detalles de CLIENTE (wp_automatiza_clients_details)
            $table_client_details = $wpdb->prefix . 'automatiza_clients_details';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_client_details'") == $table_client_details) {
                $client_details = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_client_details WHERE client_id = %d", $cliente_id), ARRAY_A);
                foreach ($client_details as $d) {
                    $d['source'] = 'client';
                    // Prioridad de fecha: completed_date > scheduled_date > created_at
                    if (!empty($d['completed_date'])) {
                        $ts = strtotime($d['completed_date']);
                    } elseif (!empty($d['scheduled_date'])) {
                        $ts = strtotime($d['scheduled_date']);
                    } else {
                        $ts = strtotime($d['created_at']);
                    }
                    $d['timestamp'] = $ts;
                    $d['display_date'] = date('Y-m-d H:i:s', $ts);
                    $unified_timeline[] = $d;
                }
            }
            
            // 2. Detalles de PROSPECTO (wp_automatiza_propuestas_details)
            // Buscar propuesta asociada por email
            $table_propuestas = $wpdb->prefix . 'automatiza_propuestas';
            $table_propuestas_details = $wpdb->prefix . 'automatiza_propuestas_details';
            
            if ($cliente->email && $wpdb->get_var("SHOW TABLES LIKE '$table_propuestas'") == $table_propuestas) {
                $propuesta_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_propuestas WHERE client_email = %s ORDER BY id DESC LIMIT 1", $cliente->email));
                
                if ($propuesta_id && $wpdb->get_var("SHOW TABLES LIKE '$table_propuestas_details'") == $table_propuestas_details) {
                    $prospect_details = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_propuestas_details WHERE propuesta_id = %d", $propuesta_id), ARRAY_A);
                    foreach ($prospect_details as $d) {
                        $d['source'] = 'prospect';
                        // Prioridad de fecha: completed_date > scheduled_date > created_at
                        if (!empty($d['completed_date'])) {
                            $ts = strtotime($d['completed_date']);
                        } elseif (!empty($d['scheduled_date'])) {
                            $ts = strtotime($d['scheduled_date']);
                        } else {
                            $ts = strtotime($d['created_at']);
                        }
                        $d['timestamp'] = $ts;
                        $d['display_date'] = date('Y-m-d H:i:s', $ts);
                        $unified_timeline[] = $d;
                    }
                }
            }
            
            // 3. Historial Legacy (wp_automatiza_historial)
            $legacy_type_map = [
                'nota'              => 'nota',
                'email'             => 'email',
                'reunion'           => 'reunion',
                'llamada'           => 'llamada',
                'pago'              => 'pago',
                'boleta'            => 'boleta',
                'factura'           => 'factura',
                'cotizacion'        => 'cotizacion',
                'proyecto_update'   => 'item_proyecto',
                'conversion'        => 'legacy',
                'update'            => 'legacy',
            ];
            $historia_legacy = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tabla_historial} WHERE cliente_id = %d ORDER BY created_at DESC", $cliente_id), ARRAY_A);
            foreach ($historia_legacy as $h) {
                $ts = strtotime($h['created_at']);
                $tipo = $h['tipo_evento'] ?? 'legacy';
                $detail_type = $legacy_type_map[$tipo] ?? 'legacy';
                $unified_timeline[] = [
                    'id' => $h['id'],
                    'detail_type' => $detail_type,
                    'title' => $h['titulo'],
                    'description' => $h['descripcion'],
                    'created_at' => $h['created_at'],
                    'display_date' => $h['created_at'],
                    'timestamp' => $ts,
                    'source' => 'system',
                    'metadata' => $h['metadata']
                ];
            }
            
            // 4. Agregar Hito de Conversión (Separador)
            if (!empty($cliente->fecha_contrato)) {
                $ts_conversion = strtotime($cliente->fecha_contrato);
                $unified_timeline[] = [
                    'detail_type' => 'separator',
                    'title' => '🎉 ¡Inicio de Cliente!',
                    'description' => 'Conversión de Prospecto a Cliente.',
                    'created_at' => $cliente->fecha_contrato,
                    'display_date' => $cliente->fecha_contrato,
                    'timestamp' => $ts_conversion,
                    'source' => 'system'
                ];
            }
            
            // Ordenar todo por fecha cronológica inversa
            usort($unified_timeline, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            // ─── QA Data for public timeline ───
            $qa_projects_pub = [];
            $qa_t_projects  = $wpdb->prefix . 'at_qa_projects';
            $qa_t_modules   = $wpdb->prefix . 'at_qa_modules';
            $qa_t_cases     = $wpdb->prefix . 'at_qa_cases';
            $qa_t_evidence  = $wpdb->prefix . 'at_qa_evidence';
            $qa_t_comments  = $wpdb->prefix . 'at_qa_comments';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$qa_t_projects}'") === $qa_t_projects) {
                $qa_projects_pub = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$qa_t_projects} WHERE client_id = %d ORDER BY created_at DESC",
                    $cliente_id
                ), ARRAY_A);
                foreach ($qa_projects_pub as &$_qp) {
                    $_qp['modules'] = $wpdb->get_results($wpdb->prepare(
                        "SELECT m.*,
                            (SELECT COUNT(*) FROM {$qa_t_cases} WHERE module_id = m.id) as total_cases,
                            (SELECT COUNT(*) FROM {$qa_t_cases} WHERE module_id = m.id AND status = 'pass') as passed,
                            (SELECT COUNT(*) FROM {$qa_t_cases} WHERE module_id = m.id AND status = 'fail') as failed,
                            (SELECT COUNT(*) FROM {$qa_t_cases} WHERE module_id = m.id AND status = 'blocked') as blocked
                        FROM {$qa_t_modules} m WHERE m.project_id = %d ORDER BY sort_order",
                        $_qp['id']
                    ), ARRAY_A);
                    $_qp['total'] = 0; $_qp['passed_total'] = 0; $_qp['failed_total'] = 0; $_qp['blocked_total'] = 0;
                    foreach ($_qp['modules'] as &$_mod) {
                        $_qp['total'] += (int)$_mod['total_cases'];
                        $_qp['passed_total'] += (int)$_mod['passed'];
                        $_qp['failed_total'] += (int)$_mod['failed'];
                        $_qp['blocked_total'] += (int)$_mod['blocked'];
                        $_mod['cases'] = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$qa_t_cases} WHERE module_id = %d ORDER BY sort_order, id",
                            $_mod['id']
                        ), ARRAY_A);
                        foreach ($_mod['cases'] as &$_caso) {
                            $_caso['evidence'] = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$qa_t_evidence} WHERE case_id = %d ORDER BY created_at DESC",
                                $_caso['id']
                            ), ARRAY_A);
                            $_caso['comments'] = $wpdb->get_results($wpdb->prepare(
                                "SELECT c.*, u.display_name as author_name FROM {$qa_t_comments} c
                                 LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                                 WHERE c.case_id = %d ORDER BY c.created_at ASC",
                                $_caso['id']
                            ), ARRAY_A);
                        }
                        unset($_caso);
                    }
                    unset($_mod);
                    $_qp['progress'] = $_qp['total'] > 0 ? round(($_qp['passed_total'] / $_qp['total']) * 100) : 0;
                }
                unset($_qp);
            }
            // ─── End QA Data ───

            // Configuración de tipos de detalle (iconos y colores)
            $detail_types = [
                'propuesta_enviada' => ['label' => '📄 Propuesta Enviada', 'icon' => '📄', 'color' => '#667eea', 'bg' => '#ebf4ff'],
                'cotizacion' => ['label' => '💰 Cotización', 'icon' => '💰', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
                'reunion' => ['label' => '🤝 Reunión', 'icon' => '🤝', 'color' => '#10b981', 'bg' => '#d1fae5'],
                'llamada' => ['label' => '📞 Llamada', 'icon' => '📞', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
                'email' => ['label' => '📧 Email', 'icon' => '📧', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
                'boleta' => ['label' => '🧾 Boleta', 'icon' => '🧾', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
                'factura' => ['label' => '📋 Factura', 'icon' => '📋', 'color' => '#14b8a6', 'bg' => '#f0fdfa'],
                'pago' => ['label' => '💳 Pago Recibido', 'icon' => '💳', 'color' => '#22c55e', 'bg' => '#dcfce7'],
                'item_proyecto' => ['label' => '📦 Item de Proyecto', 'icon' => '📦', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
                'entregable' => ['label' => '✅ Entregable', 'icon' => '✅', 'color' => '#84cc16', 'bg' => '#ecfccb'],
                'nota' => ['label' => '📝 Nota', 'icon' => '📝', 'color' => '#94a3b8', 'bg' => '#f1f5f9'],
                'legacy' => ['label' => '🤖 Sistema', 'icon' => '⚙️', 'color' => '#64748b', 'bg' => '#f8fafc'],
                'separator' => ['label' => '🎉 Conversión', 'icon' => '🎉', 'color' => '#7c3aed', 'bg' => '#f5f3ff'] // Morado vibrante
            ];
            
            // Datos de identidad corporativa (desde la tabla)
            $logo_cliente = $cliente->logo_url;
            $color_p = $cliente->color_principal;
            $color_s1 = $cliente->color_secundario_1;
            $color_s2 = $cliente->color_secundario_2;

            // Renderizar HTML
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Timeline del Cliente - AutomatizaTech</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; margin: 0; padding: 0; line-height: 1.6; }
                    .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: white; padding: 40px 20px; text-align: center; }
                    .header img { max-width: 180px; margin-bottom: 15px; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; }
                    .container { max-width: 900px; margin: -30px auto 40px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 40px; position: relative; }
                    h1 { margin: 0 0 10px; font-size: 2.2em; }
                    h2 { color: #1e3a8a; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 40px; }
                    .client-info { background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 5px solid #1e3a8a; }
                    
                    /* Timeline Styles */
                    .timeline { position: relative; padding-left: 30px; margin-top: 20px; }
                    .timeline::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #e0e0e0; }
                    .timeline-item { margin-bottom: 30px; position: relative; }
                    .timeline-marker { position: absolute; left: -36px; top: 5px; width: 15px; height: 15px; background: #06d6a0; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #06d6a0; }
                    .timeline-date { font-size: 0.85em; color: #888; margin-bottom: 5px; font-weight: 600; }
                    .timeline-content { background: #f9f9f9; padding: 15px 20px; border-radius: 8px; border: 1px solid #eee; }
                    .timeline-content h3 { margin: 0 0 5px; color: #333; font-size: 1.1em; }
                    
                    /* Project Carousel */
                    .proj-carousel-wrap { position:relative; margin-bottom:24px; }
                    .proj-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin-bottom:0; overflow-x:auto; scrollbar-width:none; }
                    .proj-tabs::-webkit-scrollbar { display:none; }
                    .proj-tab { padding:10px 18px; border:none; background:none; cursor:pointer; font-size:13px; font-weight:500; color:#64748b; border-bottom:3px solid transparent; transition:all .2s; white-space:nowrap; display:flex; align-items:center; gap:6px; font-family:inherit; }
                    .proj-tab:hover { color:#1e3a8a; background:#eff6ff; }
                    .proj-tab.active { color:#1e3a8a; border-bottom-color:#1e3a8a; font-weight:600; }
                    .proj-tab .proj-tab-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; color:#fff; }
                    .proj-tab .proj-tab-badge.pendiente { background:#f59e0b; color:#333; }
                    .proj-tab .proj-tab-badge.en_progreso { background:#0ea5e9; }
                    .proj-tab .proj-tab-badge.completado { background:#22c55e; }
                    .proj-viewport { position:relative; overflow:hidden; border-radius:10px; background:#fff; border:1px solid #e5e7eb; min-height:140px; }
                    .proj-track { display:flex; transition:transform .45s cubic-bezier(.4,0,.2,1); will-change:transform; }
                    .proj-slide { min-width:100%; box-sizing:border-box; padding:24px 56px; text-align:justify; }
                    .proj-slide h3 { margin:0 0 10px; font-size:1.1em; color:#1e293b; text-align:left; }
                    .proj-slide p { margin:0 0 10px; color:#475569; line-height:1.6; }
                    .proj-slide small { color:#94a3b8; font-size:12px; }
                    .proj-slide .proj-meta { display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; font-size:12px; color:#64748b; }
                    .proj-nav-btn { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,.35); color:#fff; border:none; width:34px; height:34px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; z-index:3; transition:background .2s; }
                    .proj-nav-btn:hover { background:rgba(0,0,0,.6); }
                    .proj-nav-btn.prev { left:8px; }
                    .proj-nav-btn.next { right:8px; }
                    .proj-progress { height:3px; background:#e5e7eb; border-radius:0 0 10px 10px; overflow:hidden; }
                    .proj-progress-bar { height:100%; background:linear-gradient(90deg,#0d9488,#14b8a6); transition:width .3s ease; width:0%; }
                    .proj-timer-bar { height:3px; background:#e2e8f0; overflow:hidden; margin-top:-1px; border-radius:0 0 10px 10px; }
                    .proj-timer-fill { height:100%; background:linear-gradient(90deg,#3b82f6,#60a5fa); width:0%; transition:width 15s linear; }
                    .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8em; font-weight: bold; color: white; background: #6c757d; }
                    .badge.pendiente { background: #ffc107; color: #333; }
                    .badge.en_progreso { background: #17a2b8; }
                    .badge.completado { background: #28a745; }
                    
                    .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; }
                    .footer { text-align: center; margin-top: 50px; color: #999; font-size: 0.9em; }
                    
                    /* Tab Styles */
                    .timeline-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin:0 0 0 0; overflow-x:auto; }
                    .tl-tab { padding: 10px 18px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 500; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 6px; font-family: inherit; }
                    .tl-tab:hover { color: #1e3a8a; background: #eff6ff; }
                    .tl-tab.active { color: #1e3a8a; border-bottom-color: #1e3a8a; font-weight: 600; }
                    .tl-tab-count { background: #e2e8f0; color: #475569; padding: 1px 7px; border-radius: 10px; font-size: 11px; font-weight: 600; }
                    .tl-tab.active .tl-tab-count { background: #dbeafe; color: #1e3a8a; }
                    .tl-tab-content { padding-top: 15px; }
                    .img-preview { margin-top:8px; }
                    .img-preview img { max-width:220px; max-height:160px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer; transition: transform 0.2s, box-shadow 0.2s; }
                    .img-preview img:hover { transform: scale(1.03); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
                    
                    /* ========== RESPONSIVE - Vista Cliente ========== */
                    
                    /* Tablet */
                    @media screen and (max-width: 768px) {
                        .header { padding: 25px 15px; }
                        .header img { max-width: 140px; }
                        h1 { font-size: 1.6em; }
                        h2 { font-size: 1.2em; }
                        .container { padding: 25px 20px; margin: -20px 10px 30px; border-radius: 10px; }
                        .client-info { padding: 15px; font-size: 14px; }
                        .proj-slide { padding:15px 48px; }
                        .proj-slide h3 { font-size:.95em; }
                        .proj-tab { padding:8px 12px; font-size:12px; }
                        .proj-nav-btn { width:28px; height:28px; font-size:13px; }
                        .badge { font-size: 0.7em; padding: 3px 8px; }
                        .timeline-tabs { scrollbar-width: none; -webkit-overflow-scrolling: touch; }
                        .timeline-tabs::-webkit-scrollbar { display: none; }
                        .tl-tab { padding: 8px 12px; font-size: 12px; }
                        .tl-tab-count { font-size: 10px; padding: 1px 5px; }
                        .timeline-content h3 { font-size: 0.95em; }
                        .timeline-content { padding: 12px 15px; }
                        .img-preview img { max-width: 180px; max-height: 130px; }
                    }
                    
                    /* Mobile */
                    @media screen and (max-width: 480px) {
                        body { font-size: 14px; }
                        .header { padding: 20px 12px; }
                        .header img { max-width: 120px; padding: 8px; }
                        .header p { font-size: 0.9em; }
                        h1 { font-size: 1.3em; }
                        h2 { font-size: 1.1em; margin-top: 30px; }
                        .container { padding: 18px 14px; margin: -15px 6px 25px; border-radius: 8px; }
                        .client-info { padding: 12px; font-size: 13px; border-left-width: 3px; }
                        .client-info div[style*="display:flex"] { flex-wrap: wrap; }
                        .proj-slide { padding:12px 42px; }
                        .proj-slide h3 { font-size:.9em; }
                        .proj-tab { padding:7px 10px; font-size:11px; }
                        .proj-nav-btn { width:26px; height:26px; font-size:12px; }
                        .badge { font-size: 0.7em; }
                        .timeline { padding-left: 22px; }
                        .timeline::before { width: 2px; }
                        .timeline-marker { width: 12px; height: 12px; left: -30px; }
                        .timeline-date { font-size: 0.78em; }
                        .timeline-content { padding: 10px 12px; font-size: 0.9em; border-left-width: 3px !important; }
                        .timeline-content h3 { font-size: 0.9em; }
                        .img-preview img { max-width: 100%; max-height: 200px; }
                        .tl-tab { padding: 7px 10px; font-size: 11px; }
                        .tl-tab-count { font-size: 9px; }
                        .footer { font-size: 0.8em; margin-top: 30px; }
                        
                        /* Chat widget mobile - FULLSCREEN */
                        #maxtech-widget { bottom: 15px !important; right: 15px !important; }
                        #maxtech-chat {
                            position: fixed !important;
                            top: 0 !important;
                            left: 0 !important;
                            width: 100vw !important;
                            height: 100vh !important;
                            height: 100dvh !important;
                            max-height: 100vh !important;
                            max-height: 100dvh !important;
                            border-radius: 0 !important;
                            z-index: 99999 !important;
                        }
                        #maxtech-chat > div:first-child {
                            padding-top: calc(15px + env(safe-area-inset-top, 0px)) !important;
                        }
                        #chat-history-sidebar { width: 100% !important; }
                        #maxtech-tooltip { display: none !important; }
                    }
                    
                    /* Touch improvements */
                    @media (hover: none) and (pointer: coarse) {
                        .tl-tab { min-height: 44px; display: flex; align-items: center; }
                        .proj-viewport:hover { transform: none; }
                        .timeline-content { -webkit-tap-highlight-color: transparent; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="AutomatizaTech">
                    <p>Portal de Cliente</p>
                </div>
                
                <div class="container">
                    <?php if ($logo_cliente): ?>
                        <div style="text-align:center; margin-bottom:20px;">
                            <img src="<?php echo esc_url($logo_cliente); ?>" alt="Logo Cliente" style="max-height:80px; max-width:200px;">
                        </div>
                    <?php endif; ?>

                    <h1>Hola, <?php echo esc_html($cliente->nombre); ?></h1>
                    <p>Aquí puedes ver el estado actual de tus proyectos y el historial de nuestra colaboración.</p>
                    
                    <div class="client-info">
                        <strong>Empresa:</strong> <?php echo esc_html($cliente->empresa); ?><br>
                        <strong>Estado:</strong> <?php echo ucfirst($cliente->estado); ?><br>
                        <strong>Email:</strong> <?php echo esc_html($cliente->email); ?>
                        
                        <?php if ($color_p || $color_s1 || $color_s2): ?>
                            <div style="margin-top:15px; border-top:1px solid #cce5ff; padding-top:10px;">
                                <strong>Identidad Corporativa:</strong><br>
                                <div style="display:flex; gap:10px; margin-top:5px;">
                                    <?php if ($color_p): ?>
                                        <div title="Principal: <?php echo $color_p; ?>" style="width:30px; height:30px; border-radius:50%; background:<?php echo $color_p; ?>; border:2px solid #fff; box-shadow:0 0 3px rgba(0,0,0,0.2);"></div>
                                    <?php endif; ?>
                                    <?php if ($color_s1): ?>
                                        <div title="Secundario 1: <?php echo $color_s1; ?>" style="width:30px; height:30px; border-radius:50%; background:<?php echo $color_s1; ?>; border:2px solid #fff; box-shadow:0 0 3px rgba(0,0,0,0.2);"></div>
                                    <?php endif; ?>
                                    <?php if ($color_s2): ?>
                                        <div title="Secundario 2: <?php echo $color_s2; ?>" style="width:30px; height:30px; border-radius:50%; background:<?php echo $color_s2; ?>; border:2px solid #fff; box-shadow:0 0 3px rgba(0,0,0,0.2);"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                    // ─── CONTRATOS (todos los estados) ───
                    // Los contratos se guardan con el ID de wp_automatiza_tech_clients,
                    // pero el portal usa el ID de wp_crm_clientes. Cruzamos por email.
                    $contracts_table = $wpdb->prefix . 'automatiza_contracts';
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$contracts_table}'") === $contracts_table) {
                        $at_clients_table = $wpdb->prefix . 'automatiza_tech_clients';
                        $at_client_id = null;
                        if (!empty($cliente->email)) {
                            $at_client_id = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$at_clients_table} WHERE email = %s LIMIT 1",
                                $cliente->email
                            ));
                        }
                        if (!$at_client_id) { $at_client_id = $cliente_id; }
                        $all_contracts = $wpdb->get_results($wpdb->prepare(
                            "SELECT id, contract_number, type, status, signed_at, sent_at, created_at,
                                    signed_pdf_url, pdf_url, sign_token, monthly_amount, currency
                             FROM {$contracts_table}
                             WHERE client_id = %d
                             ORDER BY created_at DESC",
                            $at_client_id
                        ), ARRAY_A);
                    ?>
                    <h2>📄 Tus Contratos</h2>
                    <?php if (empty($all_contracts)): ?>
                        <p style="color:#888; font-style:italic; padding:8px 0;">No tienes contratos registrados por el momento.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px;">
                            <?php
                            $type_labels = [
                                'soporte'   => 'Contrato de Soporte Post-Proyecto',
                                'servicios' => 'Contrato de Servicios',
                                'sla'       => 'Acuerdo de Nivel de Servicio (SLA)',
                                'nda'       => 'Acuerdo de Confidencialidad (NDA)',
                                'handover'  => 'Acta de Entrega y Cierre',
                            ];
                            foreach ($all_contracts as $c):
                                $label      = $type_labels[$c['type']] ?? ucfirst($c['type']);
                                $status     = $c['status'];
                                $is_signed  = $status === 'signed';
                                $needs_sign = in_array($status, ['sent', 'viewed']);
                                $in_prep    = in_array($status, ['at_pending', 'at_signed']);

                                if ($is_signed) {
                                    $bg = '#f0fdf4'; $border = '#bbf7d0'; $accent = '#22c55e';
                                    $badge_bg = '#22c55e'; $badge_text = '✅ FIRMADO';
                                    $date_label = 'Firmado el';
                                    $date_val   = !empty($c['signed_at']) ? date('d/m/Y', strtotime($c['signed_at'])) : '—';
                                } elseif ($needs_sign) {
                                    $bg = '#fffbeb'; $border = '#fde68a'; $accent = '#f59e0b';
                                    $badge_bg = '#f59e0b'; $badge_text = '✍️ PENDIENTE TU FIRMA';
                                    $date_label = 'Enviado el';
                                    $date_val   = !empty($c['sent_at']) ? date('d/m/Y', strtotime($c['sent_at'])) : '—';
                                } else {
                                    $bg = '#f8fafc'; $border = '#e2e8f0'; $accent = '#94a3b8';
                                    $badge_bg = '#94a3b8'; $badge_text = '🔄 EN PREPARACIÓN';
                                    $date_label = 'Creado el';
                                    $date_val   = !empty($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '—';
                                }
                            ?>
                            <div style="background:<?php echo $bg; ?>; border:1px solid <?php echo $border; ?>; border-left:5px solid <?php echo $accent; ?>; border-radius:10px; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                <div>
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
                                        <span style="font-size:18px;">📜</span>
                                        <strong style="color:#1e293b; font-size:15px;"><?php echo esc_html($label); ?></strong>
                                        <span style="background:<?php echo $badge_bg; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap;"><?php echo $badge_text; ?></span>
                                    </div>
                                    <div style="font-size:12px; color:#6b7280;">
                                        N° <?php echo esc_html($c['contract_number']); ?>
                                        &nbsp;·&nbsp; <?php echo esc_html($date_label); ?> <?php echo esc_html($date_val); ?>
                                        <?php if (!empty($c['monthly_amount']) && $c['monthly_amount'] > 0): ?>
                                            &nbsp;·&nbsp; $<?php echo number_format((float)$c['monthly_amount'], 0, ',', '.'); ?> <?php echo esc_html($c['currency']); ?>/mes
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($is_signed && !empty($c['signed_pdf_url'])): ?>
                                    <a href="<?php echo esc_url($c['signed_pdf_url']); ?>"
                                       download target="_blank"
                                       style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;box-shadow:0 2px 8px rgba(34,197,94,.3);white-space:nowrap;"
                                       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                        ⬇️ Descargar PDF
                                    </a>
                                <?php elseif ($is_signed): ?>
                                    <span style="font-size:12px;color:#9ca3af;font-style:italic;white-space:nowrap;">PDF en proceso…</span>
                                <?php elseif ($needs_sign && !empty($c['sign_token'])): ?>
                                    <a href="<?php echo esc_url(home_url('/contracts/sign-contract.php?token=' . urlencode($c['sign_token']))); ?>"
                                       target="_blank"
                                       style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;box-shadow:0 2px 8px rgba(245,158,11,.3);white-space:nowrap;"
                                       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                        ✍️ Firmar ahora
                                    </a>
                                <?php elseif ($in_prep): ?>
                                    <span style="font-size:12px;color:#9ca3af;font-style:italic;white-space:nowrap;">Aguarda, estamos preparando el contrato…</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php } ?>

                    <h2>🚀 Tus Proyectos</h2>
                    <?php if (empty($proyectos)): ?>
                        <p>No hay proyectos activos por el momento.</p>
                    <?php else: ?>
                        <div class="proj-carousel-wrap" id="projCarousel">
                            <!-- Tabs -->
                            <div class="proj-tabs" id="projTabs">
                                <?php foreach ($proyectos as $pi => $p): ?>
                                <button class="proj-tab<?php echo $pi===0?' active':''; ?>" onclick="projGoTo(<?php echo $pi; ?>)" data-idx="<?php echo $pi; ?>">
                                    🚀 <?php echo esc_html(mb_strimwidth($p['nombre'], 0, 35, '…')); ?>
                                    <span class="proj-tab-badge <?php echo esc_attr($p['estado']); ?>"><?php echo ucfirst(str_replace('_', ' ', $p['estado'])); ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <!-- Carousel -->
                            <div class="proj-viewport">
                                <?php if (count($proyectos) > 1): ?>
                                <button class="proj-nav-btn prev" onclick="projNav(-1)">&#8249;</button>
                                <button class="proj-nav-btn next" onclick="projNav(1)">&#8250;</button>
                                <?php endif; ?>
                                <div class="proj-track" id="projTrack">
                                    <?php foreach ($proyectos as $pi => $p): ?>
                                    <div class="proj-slide">
                                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                                            <h3>🚀 <?php echo esc_html($p['nombre']); ?></h3>
                                            <span class="badge <?php echo esc_attr($p['estado']); ?>"><?php echo ucfirst(str_replace('_', ' ', $p['estado'])); ?></span>
                                        </div>
                                        <p><?php echo nl2br(esc_html($p['descripcion'])); ?></p>
                                        <div class="proj-meta">
                                            <span>📅 Inicio: <?php echo $p['fecha_inicio'] ? date('d/m/Y', strtotime($p['fecha_inicio'])) : 'Pendiente'; ?></span>
                                            <span>🎯 Entrega: <?php echo $p['fecha_entrega'] ? date('d/m/Y', strtotime($p['fecha_entrega'])) : 'Por definir'; ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- Timer bar -->
                            <?php if (count($proyectos) > 1): ?>
                            <div class="proj-timer-bar">
                                <div class="proj-timer-fill" id="projTimerFill"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h2>📜 Historial / Timeline</h2>

                    <?php
                    // Clasificar timeline items por tipo para las pestañas
                    $timeline_tabs = [
                        'todos' => $unified_timeline,
                        'reuniones' => [],
                        'notas' => [],
                        'pagos' => [],
                        'sistema' => [],
                    ];
                    
                    foreach ($unified_timeline as $item) {
                        $dtype = $item['detail_type'] ?? 'nota';
                        if (in_array($dtype, ['reunion', 'llamada'])) {
                            $timeline_tabs['reuniones'][] = $item;
                        }
                        if (in_array($dtype, ['nota', 'email'])) {
                            $timeline_tabs['notas'][] = $item;
                        }
                        if (in_array($dtype, ['pago', 'boleta', 'factura', 'cotizacion'])) {
                            $timeline_tabs['pagos'][] = $item;
                        }
                        if (in_array($dtype, ['legacy', 'separator', 'item_proyecto', 'entregable'])) {
                            $timeline_tabs['sistema'][] = $item;
                        }
                    }
                    
                    $tab_counts = [
                        'todos' => count($unified_timeline),
                        'reuniones' => count($timeline_tabs['reuniones']),
                        'notas' => count($timeline_tabs['notas']),
                        'pagos' => count($timeline_tabs['pagos']),
                        'sistema' => count($timeline_tabs['sistema']),
                    ];
                    
                    // Helper: check if URL points to an image
                    $is_image_url = function($url) {
                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
                    };
                    
                    // Helper: render a single timeline item (public - no admin buttons)
                    $render_public_item = function($h) use ($detail_types, $is_image_url) {
                        $type = $h['detail_type'] ?? 'nota';
                        $config = $detail_types[$type] ?? $detail_types['nota'];
                        $amount_display = '';
                        if (!empty($h['amount']) && $h['amount'] > 0) {
                            $amount_display = '<span style="color:#22c55e; font-weight:bold; margin-left:10px;">$' . number_format($h['amount'], 0, ',', '.') . ' ' . ($h['currency'] ?? 'CLP') . '</span>';
                        }
                        $attachment_html = '';
                        // 1. Adjunto único
                        if (!empty($h['attachment_url'])) {
                            $file_name = !empty($h['attachment_name']) ? $h['attachment_name'] : basename($h['attachment_url']);
                            $preview = '';
                            if ($is_image_url($h['attachment_url'])) {
                                $preview = '<div class="img-preview"><a href="' . esc_url($h['attachment_url']) . '" target="_blank"><img src="' . esc_url($h['attachment_url']) . '" alt="' . esc_attr($file_name) . '" loading="lazy"></a></div>';
                            }
                            $attachment_html .= '<div style="margin-top:10px; padding:8px; background:white; border:1px solid #e2e8f0; border-radius:6px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:20px;">📎</span>
                                    <a href="' . esc_url($h['attachment_url']) . '" target="_blank" style="text-decoration:none; color:#3b82f6; font-weight:500;">' . esc_html($file_name) . '</a>
                                    <a href="' . esc_url($h['attachment_url']) . '" download style="margin-left:auto; font-size:11px; padding:2px 8px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#555; background:#eee;">⬇ Descargar</a>
                                </div>' . $preview . '
                            </div>';
                        }
                        // 2. Adjuntos múltiples (metadata evidence)
                        if (!empty($h['metadata'])) {
                            $meta = json_decode($h['metadata'], true);
                            if (!empty($meta['evidencia']) && is_array($meta['evidencia'])) {
                                foreach ($meta['evidencia'] as $link) {
                                    $file_name = basename($link);
                                    $preview = '';
                                    if ($is_image_url($link)) {
                                        $preview = '<div class="img-preview"><a href="' . esc_url($link) . '" target="_blank"><img src="' . esc_url($link) . '" alt="' . esc_attr($file_name) . '" loading="lazy"></a></div>';
                                    }
                                    $attachment_html .= '<div style="margin-top:5px; padding:8px; background:white; border:1px solid #e2e8f0; border-radius:6px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span style="font-size:20px;">📎</span>
                                            <a href="' . esc_url($link) . '" target="_blank" style="text-decoration:none; color:#3b82f6; font-weight:500;">' . esc_html($file_name) . '</a>
                                            <a href="' . esc_url($link) . '" download style="margin-left:auto; font-size:11px; padding:2px 8px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#555; background:#eee;">⬇ Descargar</a>
                                        </div>' . $preview . '
                                    </div>';
                                }
                            }
                        }
                        ?>
                        <div class="timeline-item <?php echo $type === 'separator' ? 'timeline-separator' : ''; ?>">
                            <div class="timeline-marker" style="background:<?php echo $config['color']; ?>; box-shadow: 0 0 0 2px <?php echo $config['color']; ?>;"></div>
                            <div class="timeline-date"><?php echo date('d/m/Y H:i', $h['timestamp']); ?></div>
                            <div class="timeline-content" style="border-left: 4px solid <?php echo $config['color']; ?>; <?php echo $type === 'separator' ? 'background:#fdf2f8; border:2px dashed #7c3aed;' : ''; ?>">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                    <span class="badge" style="background:<?php echo $config['color']; ?>;"><?php echo $config['icon'] . ' ' . $config['label']; ?></span>
                                    <?php echo $amount_display; ?>
                                </div>
                                <h3 style="margin-top:8px;"><?php echo esc_html($h['title'] ?? $h['titulo']); ?></h3>
                                <p style="white-space: pre-wrap; margin-bottom: 0;"><?php echo nl2br(esc_html($h['description'] ?? $h['descripcion'])); ?></p>
                                <?php echo $attachment_html; ?>
                            </div>
                        </div>
                        <?php
                    };
                    
                    // Helper: render tab content with pagination
                    $render_pub_tab = function($items, $tab_name, $max_visible = 10) use ($render_public_item) {
                        if (empty($items)) {
                            echo '<p style="color:#888; font-style:italic; padding:20px 0;">No hay registros en esta categoría.</p>';
                            return;
                        }
                        foreach ($items as $idx => $h) {
                            $hidden = ($idx >= $max_visible) ? 'style="display:none;"' : '';
                            echo '<div class="tl-page-item tl-page-' . $tab_name . '" ' . $hidden . '>';
                            $render_public_item($h);
                            echo '</div>';
                        }
                        $total = count($items);
                        if ($total > $max_visible) {
                            echo '<div class="tl-show-more" id="tl-more-' . $tab_name . '">
                                <button onclick="mostrarMasTimeline(\'' . $tab_name . '\', ' . $total . ')" style="width:100%; text-align:center; margin-top:10px; padding:8px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:6px; cursor:pointer; font-family:inherit;">
                                    📄 Ver más (' . ($total - $max_visible) . ' registros restantes)
                                </button>
                            </div>';
                        }
                    };
                    ?>

                    <!-- Pestañas de navegación -->
                    <div class="timeline-tabs">
                        <button class="tl-tab active" data-tab="todos" onclick="switchTimelineTab('todos')">📋 Todos <span class="tl-tab-count"><?php echo $tab_counts['todos']; ?></span></button>
                        <?php if (!empty($qa_projects_pub)): ?>
                        <button class="tl-tab" data-tab="qa" onclick="switchTimelineTab('qa')">🧪 QA <span class="tl-tab-count"><?php echo count($qa_projects_pub); ?></span></button>
                        <?php endif; ?>
                        <button class="tl-tab" data-tab="reuniones" onclick="switchTimelineTab('reuniones')">🤝 Reuniones <span class="tl-tab-count"><?php echo $tab_counts['reuniones']; ?></span></button>
                        <button class="tl-tab" data-tab="notas" onclick="switchTimelineTab('notas')">📝 Notas <span class="tl-tab-count"><?php echo $tab_counts['notas']; ?></span></button>
                        <button class="tl-tab" data-tab="pagos" onclick="switchTimelineTab('pagos')">💰 Pagos <span class="tl-tab-count"><?php echo $tab_counts['pagos']; ?></span></button>
                        <button class="tl-tab" data-tab="sistema" onclick="switchTimelineTab('sistema')">⚙️ Sistema <span class="tl-tab-count"><?php echo $tab_counts['sistema']; ?></span></button>
                    </div>

                    <!-- Pestaña: Todos -->
                    <div class="tl-tab-content" id="tl-content-todos" style="display:block;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_pub_tab($timeline_tabs['todos'], 'todos', 10); ?>
                        </div>
                    </div>

                    <!-- Pestaña: Reuniones -->
                    <div class="tl-tab-content" id="tl-content-reuniones" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_pub_tab($timeline_tabs['reuniones'], 'reuniones', 10); ?>
                        </div>
                    </div>

                    <!-- Pestaña: Notas -->
                    <div class="tl-tab-content" id="tl-content-notas" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_pub_tab($timeline_tabs['notas'], 'notas', 10); ?>
                        </div>
                    </div>

                    <!-- Pestaña: Pagos -->
                    <div class="tl-tab-content" id="tl-content-pagos" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_pub_tab($timeline_tabs['pagos'], 'pagos', 10); ?>
                        </div>
                    </div>

                    <!-- Pestaña: Sistema -->
                    <div class="tl-tab-content" id="tl-content-sistema" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_pub_tab($timeline_tabs['sistema'], 'sistema', 10); ?>
                        </div>
                    </div>

                    <?php if (!empty($qa_projects_pub)): ?>
                    <!-- Pestaña: QA -->
                    <div class="tl-tab-content" id="tl-content-qa" style="display:none;">
                        <style>
                            .pub-qa-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;}
                            .pub-qa-mod{border:1px solid #e5e7eb;border-radius:8px;margin-top:12px;overflow:hidden;}
                            .pub-qa-mod-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9fafb;cursor:pointer;user-select:none;transition:background .15s;gap:8px;border-bottom:1px solid #e5e7eb;}
                            .pub-qa-mod-hdr:hover{background:#f0fdfa;}
                            .pub-qa-mod-hdr .arrow{transition:transform .2s;display:inline-block;font-size:10px;color:#6b7280;}
                            .pub-qa-mod-hdr.open .arrow{transform:rotate(90deg);}
                            .pub-qa-mod-body{display:none;padding:10px 14px;}
                            .pub-qa-mod-body.open{display:block;animation:qaFadeIn .2s ease;}
                            @keyframes qaFadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
                            .pub-qa-case{border-left:3px solid #e5e7eb;padding:10px 14px;margin:6px 0;border-radius:0 8px 8px 0;background:#fafafa;transition:background .15s;}
                            .pub-qa-case:hover{background:#f0fdfa;}
                            .pub-qa-case.st-pass{border-left-color:#059669;}
                            .pub-qa-case.st-fail{border-left-color:#dc2626;}
                            .pub-qa-case.st-blocked{border-left-color:#f59e0b;}
                            .pub-qa-case.st-not_tested{border-left-color:#9ca3af;}
                            .pub-qa-case.st-skipped{border-left-color:#6366f1;}
                            /* Carousel */
                            /* Carousel */
                            .qa-carousel{position:relative;overflow:hidden;border-radius:10px;background:#f3f4f6;margin:10px auto 0;width:100%;max-width:720px;}
                            .qa-carousel-track{display:flex;transition:transform .3s ease;will-change:transform;}
                            .qa-carousel-slide{min-width:100%;display:flex;align-items:center;justify-content:center;padding:14px;}
                            .qa-carousel-slide img{max-width:100%;max-height:440px;width:auto;border-radius:8px;object-fit:contain;cursor:pointer;}
                            .qa-carousel-btn{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.5);color:#fff;border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;z-index:2;transition:background .15s;}
                            .qa-carousel-btn:hover{background:rgba(0,0,0,0.7);}
                            .qa-carousel-btn.prev{left:10px;}
                            .qa-carousel-btn.next{right:10px;}
                            .qa-carousel-dots{display:flex;justify-content:center;gap:6px;padding:8px 0;}
                            .qa-carousel-dot{width:9px;height:9px;border-radius:50%;background:#d1d5db;border:none;cursor:pointer;padding:0;transition:background .15s;}
                            .qa-carousel-dot.active{background:#0d9488;}
                            .qa-carousel-counter{text-align:center;font-size:12px;color:#6b7280;padding:0 0 8px;}
                            @media(max-width:768px){.qa-carousel{max-width:100%;border-radius:8px;}.qa-carousel-slide{padding:8px;}.qa-carousel-slide img{max-height:300px;}.qa-carousel-btn{width:34px;height:34px;font-size:17px;}}
                            @media(max-width:480px){.qa-carousel-slide{padding:4px;}.qa-carousel-slide img{max-height:220px;}.qa-carousel-btn{width:28px;height:28px;font-size:14px;}.qa-carousel-btn.prev{left:4px;}.qa-carousel-btn.next{right:4px;}}
                            .pub-qa-comment{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;margin-top:6px;font-size:13px;}
                            .pub-qa-section{background:linear-gradient(135deg,#0d9488,#14b8a6);color:#fff;padding:5px 12px;border-radius:5px;font-size:11px;font-weight:700;margin:10px 0 6px;letter-spacing:.3px;display:inline-block;}
                            .pub-qa-filter{display:flex;gap:6px;margin:10px 0;flex-wrap:wrap;}
                            .pub-qa-fbtn{padding:4px 12px;border-radius:14px;font-size:11px;font-weight:600;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;}
                            .pub-qa-fbtn:hover,.pub-qa-fbtn.active{background:#0d9488;color:#fff;border-color:#0d9488;}
                            /* Lightbox Gallery */
                            .qa-lightbox{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:99999;align-items:center;justify-content:center;flex-direction:column;}
                            .qa-lightbox.show{display:flex;}
                            .qa-lb-img-wrap{position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;max-width:94vw;max-height:82vh;}
                            .qa-lb-img-wrap img{max-width:92vw;max-height:80vh;border-radius:8px;box-shadow:0 0 40px rgba(0,0,0,.5);transition:transform .25s ease;cursor:grab;user-select:none;}
                            .qa-lightbox-close{position:absolute;top:12px;right:16px;background:rgba(0,0,0,.5);border:none;color:#fff;font-size:28px;cursor:pointer;z-index:100000;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .2s;}
                            .qa-lightbox-close:hover{background:rgba(255,255,255,.2);}
                            .qa-lb-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;width:44px;height:44px;border-radius:50%;cursor:pointer;font-size:22px;display:flex;align-items:center;justify-content:center;transition:background .2s;z-index:100000;backdrop-filter:blur(4px);}
                            .qa-lb-nav:hover{background:rgba(255,255,255,.3);}
                            .qa-lb-nav.prev{left:16px;}
                            .qa-lb-nav.next{right:16px;}
                            .qa-lb-toolbar{display:flex;gap:10px;align-items:center;margin-top:12px;}
                            .qa-lb-toolbar button{background:rgba(255,255,255,.12);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .2s;backdrop-filter:blur(4px);}
                            .qa-lb-toolbar button:hover{background:rgba(255,255,255,.25);}
                            .qa-lb-counter{color:rgba(255,255,255,.8);font-size:13px;font-weight:600;letter-spacing:.5px;}
                        </style>

                        <?php foreach ($qa_projects_pub as $qp_pub_idx => $qp_pub): ?>
                        <div class="pub-qa-card">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                <h3 style="margin:0;font-size:1.2em;">🧪 <?php echo esc_html($qp_pub['name']); ?></h3>
                                <?php
                                $pub_badge = '#6b7280'; $pub_blabel = ucfirst($qp_pub['qa_status']);
                                if ($qp_pub['qa_status'] === 'passed') { $pub_badge = '#059669'; $pub_blabel = 'Aprobado'; }
                                elseif ($qp_pub['qa_status'] === 'failed') { $pub_badge = '#dc2626'; $pub_blabel = 'Fallido'; }
                                elseif ($qp_pub['qa_status'] === 'in_progress') { $pub_badge = '#d97706'; $pub_blabel = 'En Progreso'; }
                                elseif ($qp_pub['qa_status'] === 'pending') { $pub_badge = '#6b7280'; $pub_blabel = 'Pendiente'; }
                                ?>
                                <span style="background:<?php echo $pub_badge;?>;color:#fff;padding:4px 12px;border-radius:14px;font-size:12px;font-weight:600;"><?php echo $pub_blabel;?></span>
                            </div>

                            <!-- Stats -->
                            <div style="display:flex;gap:14px;margin:12px 0;flex-wrap:wrap;font-size:13px;">
                                <span>📋 <strong><?php echo $qp_pub['total'];?></strong> Total</span>
                                <span style="color:#059669;">✅ <strong><?php echo $qp_pub['passed_total'];?></strong> Pass</span>
                                <span style="color:#dc2626;">❌ <strong><?php echo $qp_pub['failed_total'];?></strong> Fail</span>
                                <span style="color:#f59e0b;">⚠️ <strong><?php echo $qp_pub['blocked_total'];?></strong> Bloqueados</span>
                                <?php $nt = max(0, $qp_pub['total'] - $qp_pub['passed_total'] - $qp_pub['failed_total'] - $qp_pub['blocked_total']); ?>
                                <span style="color:#9ca3af;">⏳ <strong><?php echo $nt;?></strong> Sin probar</span>
                            </div>

                            <!-- Progress bar multicolor -->
                            <div style="margin-bottom:6px;">
                                <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:4px;">
                                    <span><?php echo $qp_pub['passed_total'];?> / <?php echo $qp_pub['total'];?> aprobados</span>
                                    <span style="font-weight:700;"><?php echo $qp_pub['progress'];?>%</span>
                                </div>
                                <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
                                    <?php
                                    $pp = $qp_pub['total'] > 0 ? round(($qp_pub['passed_total']/$qp_pub['total'])*100,1) : 0;
                                    $pf = $qp_pub['total'] > 0 ? round(($qp_pub['failed_total']/$qp_pub['total'])*100,1) : 0;
                                    $pb = $qp_pub['total'] > 0 ? round(($qp_pub['blocked_total']/$qp_pub['total'])*100,1) : 0;
                                    ?>
                                    <div style="display:flex;height:100%;">
                                        <div style="background:#059669;width:<?php echo $pp;?>%;height:100%;"></div>
                                        <div style="background:#dc2626;width:<?php echo $pf;?>%;height:100%;"></div>
                                        <div style="background:#f59e0b;width:<?php echo $pb;?>%;height:100%;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modules -->
                            <?php foreach ($qp_pub['modules'] as $pm_idx => $pm):
                                $pm_pct = $pm['total_cases'] > 0 ? round(($pm['passed']/$pm['total_cases'])*100) : 0;
                                $pm_tested = (int)$pm['passed'] + (int)$pm['failed'] + (int)$pm['blocked'];
                                $pm_uid = 'pub-qa-' . $qp_pub_idx . '-' . $pm_idx;
                            ?>
                            <div class="pub-qa-mod">
                                <div class="pub-qa-mod-hdr" id="<?php echo $pm_uid;?>-hdr"
                                     onclick="var b=document.getElementById('<?php echo $pm_uid;?>-body'),h=this;if(b.classList.contains('open')){b.classList.remove('open');h.classList.remove('open');}else{b.classList.add('open');h.classList.add('open');}">
                                    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                                        <span class="arrow">▶</span>
                                        <span style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($pm['code'] . ' — ' . $pm['title']);?></span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;font-size:11px;">
                                        <span style="color:#059669;font-weight:600;">✅<?php echo $pm['passed'];?></span>
                                        <span style="color:#dc2626;font-weight:600;">❌<?php echo $pm['failed'];?></span>
                                        <span style="color:#f59e0b;font-weight:600;">⚠️<?php echo $pm['blocked'];?></span>
                                        <span style="color:#6b7280;"><?php echo $pm_tested;?>/<?php echo $pm['total_cases'];?></span>
                                        <div style="display:inline-flex;align-items:center;gap:4px;">
                                            <div style="background:#e5e7eb;border-radius:4px;height:6px;width:50px;overflow:hidden;">
                                                <div style="background:<?php echo $pm_pct===100?'#059669':'#14b8a6';?>;width:<?php echo $pm_pct;?>%;height:100%;"></div>
                                            </div>
                                            <span style="font-weight:600;color:<?php echo $pm_pct===100?'#059669':'#6b7280';?>;"><?php echo $pm_pct;?>%</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pub-qa-mod-body" id="<?php echo $pm_uid;?>-body">
                                    <?php if (!empty($pm['cases'])):
                                        $sections_pub = [];
                                        foreach ($pm['cases'] as $c) {
                                            $sec = !empty($c['section']) ? $c['section'] : '__none__';
                                            $sections_pub[$sec][] = $c;
                                        }
                                    ?>
                                    <!-- Filter bar -->
                                    <div class="pub-qa-filter" id="<?php echo $pm_uid;?>-filters">
                                        <button class="pub-qa-fbtn active" onclick="pubQaFilter('<?php echo $pm_uid;?>','all',this)">Todos</button>
                                        <button class="pub-qa-fbtn" onclick="pubQaFilter('<?php echo $pm_uid;?>','pass',this)">✅ Pass</button>
                                        <button class="pub-qa-fbtn" onclick="pubQaFilter('<?php echo $pm_uid;?>','fail',this)">❌ Fail</button>
                                        <button class="pub-qa-fbtn" onclick="pubQaFilter('<?php echo $pm_uid;?>','blocked',this)">⚠️ Bloqueado</button>
                                        <button class="pub-qa-fbtn" onclick="pubQaFilter('<?php echo $pm_uid;?>','not_tested',this)">⏳ Sin probar</button>
                                    </div>

                                    <?php foreach ($sections_pub as $sec_name => $sec_cases): ?>
                                        <?php if ($sec_name !== '__none__'): ?>
                                            <div class="pub-qa-section pub-qa-sec-<?php echo $pm_uid;?>"><?php echo esc_html(strtoupper($sec_name));?></div>
                                        <?php endif; ?>
                                        <?php foreach ($sec_cases as $caso):
                                            $st = $caso['status'];
                                            $st_map = [
                                                'pass'=>['✅ PASS','#059669'],'fail'=>['❌ FAIL','#dc2626'],
                                                'blocked'=>['⚠️ BLOQUEADO','#f59e0b'],'not_tested'=>['⏳ Sin probar','#9ca3af'],
                                                'skipped'=>['⏭️ Omitido','#6366f1']
                                            ];
                                            $st_i = $st_map[$st] ?? ['❓ '.$st,'#6b7280'];
                                            $has_ev = !empty($caso['evidence']);
                                            $has_cm = !empty($caso['comments']);
                                            $c_uid = $pm_uid . '-c-' . $caso['id'];
                                        ?>
                                        <div class="pub-qa-case st-<?php echo esc_attr($st);?>" data-qa-st="<?php echo esc_attr($st);?>" data-qa-mod="<?php echo $pm_uid;?>">
                                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                                                <div style="flex:1;min-width:0;">
                                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                        <span style="font-size:11px;color:#6b7280;font-weight:600;"><?php echo esc_html($caso['case_id']);?></span>
                                                        <span style="font-size:13px;font-weight:500;"><?php echo esc_html($caso['title']);?></span>
                                                        <?php if ($caso['priority']==='Alta'): ?>
                                                            <span style="background:#fee2e2;color:#dc2626;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;">ALTA</span>
                                                        <?php elseif ($caso['priority']==='Media'): ?>
                                                            <span style="background:#fef3c7;color:#d97706;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;">MEDIA</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($caso['tester'])): ?>
                                                        <div style="font-size:10px;color:#9ca3af;margin-top:2px;"><?php echo esc_html($caso['tester']);?><?php echo $caso['tested_at'] ? ' · '.date('d/m/Y H:i',strtotime($caso['tested_at'])) : '';?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($caso['bug_id'])): ?>
                                                        <span style="font-size:10px;color:#dc2626;">🐛 <?php echo esc_html($caso['bug_id']);?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="flex-shrink:0;display:flex;align-items:center;gap:6px;">
                                                    <span style="background:<?php echo $st_i[1];?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;white-space:nowrap;"><?php echo $st_i[0];?></span>
                                                    <?php if ($has_ev || $has_cm): ?>
                                                        <button onclick="var d=document.getElementById('<?php echo $c_uid;?>-det');d.style.display=d.style.display==='none'?'block':'none';" style="background:none;border:1px solid #d1d5db;border-radius:6px;padding:3px 8px;cursor:pointer;font-size:11px;" title="Ver detalle">
                                                            <?php if($has_ev):?>📸<?php echo count($caso['evidence']);?><?php endif;?><?php if($has_cm):?> 💬<?php echo count($caso['comments']);?><?php endif;?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($has_ev || $has_cm): ?>
                                            <div id="<?php echo $c_uid;?>-det" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #e5e7eb;">
                                                <?php if ($has_ev):
                                                    $images = []; $files = [];
                                                    foreach ($caso['evidence'] as $ev) {
                                                        if (in_array($ev['file_type'],['image/png','image/jpeg','image/gif','image/webp',''])) {
                                                            $images[] = $ev;
                                                        } else {
                                                            $files[] = $ev;
                                                        }
                                                    }
                                                ?>
                                                <div style="margin-bottom:8px;">
                                                    <span style="font-size:12px;font-weight:600;color:#0d9488;">📸 Evidencias (<?php echo count($caso['evidence']);?>)</span>
                                                    <?php if (!empty($images)): $car_id = $c_uid . '-car'; ?>
                                                    <!-- Image Carousel -->
                                                    <div class="qa-carousel" id="<?php echo $car_id;?>">
                                                        <div class="qa-carousel-track" id="<?php echo $car_id;?>-track">
                                                            <?php foreach ($images as $img_i => $img): ?>
                                                            <div class="qa-carousel-slide">
                                                                <img src="<?php echo esc_url($img['file_url']);?>" alt="<?php echo esc_attr($img['description']?:$img['file_name']);?>" onclick="qaLightbox(this.src)" loading="lazy">
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <?php if (count($images) > 1): ?>
                                                        <button class="qa-carousel-btn prev" onclick="qaCarouselNav('<?php echo $car_id;?>',-1)">‹</button>
                                                        <button class="qa-carousel-btn next" onclick="qaCarouselNav('<?php echo $car_id;?>',1)">›</button>
                                                        <div class="qa-carousel-counter" id="<?php echo $car_id;?>-counter">1 / <?php echo count($images);?></div>
                                                        <div class="qa-carousel-dots" id="<?php echo $car_id;?>-dots">
                                                            <?php foreach ($images as $di => $dimg): ?>
                                                            <button class="qa-carousel-dot<?php echo $di===0?' active':'';?>" onclick="qaCarouselGo('<?php echo $car_id;?>',<?php echo $di;?>)"></button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                    // Descriptions for images
                                                    foreach ($images as $img) {
                                                        if (!empty($img['description'])) {
                                                            echo '<p style="font-size:11px;color:#6b7280;margin:4px 0 0;">📝 <em>'.esc_html($img['description']).'</em></p>';
                                                        }
                                                    }
                                                    endif; // images
                                                    // Non-image files
                                                    foreach ($files as $f): ?>
                                                        <a href="<?php echo esc_url($f['file_url']);?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#f3f4f6;border-radius:6px;font-size:12px;text-decoration:none;color:#374151;border:1px solid #e5e7eb;margin-top:4px;">📎 <?php echo esc_html($f['file_name']);?></a>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; // has_ev ?>

                                                <?php if ($has_cm): ?>
                                                <div>
                                                    <span style="font-size:12px;font-weight:600;color:#6366f1;">💬 Comentarios (<?php echo count($caso['comments']);?>)</span>
                                                    <?php foreach ($caso['comments'] as $cm): ?>
                                                    <div class="pub-qa-comment">
                                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                                                            <strong style="font-size:12px;color:#374151;"><?php echo esc_html($cm['author_name']?:'Usuario #'.$cm['user_id']);?></strong>
                                                            <span style="font-size:10px;color:#9ca3af;"><?php echo date('d/m/Y H:i',strtotime($cm['created_at']));?></span>
                                                        </div>
                                                        <p style="margin:0;font-size:13px;color:#4b5563;white-space:pre-line;"><?php echo esc_html($cm['comment']);?></p>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; // cases ?>
                                    <?php endforeach; // sections ?>
                                    <?php else: ?>
                                        <p style="font-size:12px;color:#9ca3af;margin:4px 0;">No hay casos registrados aún.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; // modules ?>
                        </div>
                        <?php endforeach; // qa_projects ?>

                        <!-- Lightbox Gallery overlay -->
                        <div class="qa-lightbox" id="qa-lightbox-overlay">
                            <button class="qa-lightbox-close" onclick="qaLightboxClose()">&times;</button>
                            <button class="qa-lb-nav prev" onclick="qaLbNav(-1)">&#8249;</button>
                            <button class="qa-lb-nav next" onclick="qaLbNav(1)">&#8250;</button>
                            <div class="qa-lb-img-wrap" onclick="qaLightboxClose()">
                                <img id="qa-lightbox-img" src="" alt="Evidencia" onclick="event.stopPropagation()">
                            </div>
                            <div class="qa-lb-toolbar">
                                <button onclick="qaLbZoom(-1)" title="Alejar">&#8722;</button>
                                <button onclick="qaLbZoom(0)" title="Restablecer">&#8634;</button>
                                <button onclick="qaLbZoom(1)" title="Acercar">&#43;</button>
                                <span class="qa-lb-counter" id="qa-lb-counter"></span>
                            </div>
                        </div>
                    </div><!-- /tl-content-qa -->
                    <?php endif; ?>
                    
                    <div class="footer">
                        &copy; <?php echo date('Y'); ?> AutomatizaTech. Todos los derechos reservados.
                    </div>
                </div>

                <!-- Chat Flotante MAXTECH -->
                <div id="maxtech-widget" style="position:fixed; bottom:20px; right:20px; z-index:9999; font-family:'Segoe UI', sans-serif;">
                    
                    <!-- Chat Container -->
                    <div id="maxtech-chat" style="display:none; width:380px; height:600px; background:white; box-shadow:0 5px 30px rgba(0,0,0,0.25); border-radius:16px; overflow:hidden; flex-direction:column; position:relative;">
                        
                        <!-- Header -->
                        <div style="background:linear-gradient(135deg, #1e3a8a, #06d6a0); color:white; padding:15px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.1); z-index:10;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:40px; height:40px; background:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px;">🤖</div>
                                <div>
                                    <strong style="display:block; font-size:16px;">MAXTECH</strong>
                                    <div style="font-size:11px; opacity:0.9; display:flex; align-items:center; gap:4px;">
                                        <span style="width:8px; height:8px; background:#00ff88; border-radius:50%; display:inline-block;"></span> En línea
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <!-- Botón Audio Header (Global TTS) -->
                                <div style="position:relative;">
                                    <button id="btn-tts-header" onclick="toggleTTS()" title="Voz Activada" style="background:rgba(255,255,255,0.4); border:none; color:white; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px;">🔊</button>
                                    <div id="tts-helper-bubble" style="display:none; position:absolute; top:45px; right:-10px; background:white; color:#333; padding:10px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.2); font-size:12px; width:160px; z-index:1000; line-height:1.4; border:1px solid #ddd; text-align:center;">
                                        <div style="position:absolute; top:-6px; right:15px; width:10px; height:10px; background:white; transform:rotate(45deg); border-left:1px solid #ddd; border-top:1px solid #ddd;"></div>
                                        Si quieres desactivar el audio, lo puedes hacer aquí
                                    </div>
                                </div>
                                
                                <button onclick="toggleHistory()" title="Ver Historial" style="background:rgba(255,255,255,0.2); border:none; color:white; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">📜</button>
                                <button onclick="toggleChat()" title="Cerrar" style="background:rgba(255,255,255,0.2); border:none; color:white; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                            </div>
                        </div>

                        <!-- Sidebar Historial -->
                        <div id="chat-history-sidebar" style="display:flex; flex-direction:column; position:absolute; top:70px; left:0; bottom:0; width:280px; background:#fff; border-right:1px solid #eee; transform:translateX(-100%); transition:transform 0.3s ease; z-index:9; padding:0;">
                            
                            <!-- Sidebar Header -->
                            <div style="flex:0 0 auto; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding:15px; background:#fafafa;">
                                <h4 style="margin:0; color:#1e3a8a;">📜 Historial</h4>
                                <button onclick="toggleHistory()" style="background:none; border:none; cursor:pointer; font-size:20px; color:#999; line-height:1;">&times;</button>
                            </div>
                            
                            <!-- Botones de Acción (Sort) -->
                            <div style="flex:0 0 auto; padding:10px 15px; border-bottom:1px solid #eee;">
                                <button id="btn-order-toggle" onclick="toggleOrder()" type="button" style="width:100%; background:#f3f4f6; border:1px solid #e5e7eb; padding:8px; border-radius:6px; font-size:12px; cursor:pointer; color:#374151; font-weight:500;">⬇️ Más Recientes</button>
                            </div>

                            <!-- Content -->
                            <div id="history-content" style="flex:1; overflow-y:auto; padding:15px; font-size:13px; color:#555;">
                                <p style="text-align:center;">Cargando...</p>
                            </div>

                            <!-- Footer Close Button -->
                            <div style="flex:0 0 auto; padding:15px; border-top:1px solid #eee; background:#fff;">
                                <button onclick="toggleHistory()" style="width:100%; border:1px solid #ddd; background:#f5f5f5; padding:8px; border-radius:6px; cursor:pointer; color:#555;">Cerrar Historial</button>
                            </div>
                        </div>

                        <!-- Messages Area -->
                        <div id="chat-messages" style="flex:1; padding:15px; overflow-y:auto; background:#f9fafb; scroll-behavior:smooth;">
                            <div style="display:flex; gap:10px; margin-bottom:15px;">
                                <div style="font-size:20px;">🤖</div>
                                <div style="background:white; padding:12px 16px; border-radius:0 16px 16px 16px; box-shadow:0 2px 5px rgba(0,0,0,0.05); max-width:85%; color:#374151; line-height:1.5;">
                                    Hola <strong><?php echo esc_html($cliente->nombre); ?></strong>, soy MAXTECH. <br>
                                    Puedo ayudarte con información de tus proyectos, leer documentos que me compartas o conversar por voz. <br><br>
                                    ¿En qué te ayudo hoy?
                                </div>
                            </div>
                        </div>

                        <!-- Attachment Preview -->
                        <div id="attachment-preview" style="display:none; padding:10px 15px; background:white; border-top:1px solid #eee; overflow-x:auto; gap:10px;"></div>

                        <!-- Input Area -->
                        <div style="padding:15px; background:white; border-top:1px solid #eaecf0; display:flex; align-items:flex-end; gap:8px;">
                            <!-- File Upload -->
                            <input type="file" id="file-input" multiple style="display:none;" onchange="handleFileSelect(this)">
                            <button onclick="document.getElementById('file-input').click()" title="Adjuntar archivo o imagen" style="padding:10px; color:#6b7280; background:none; border:none; cursor:pointer; transition:color 0.2s;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            </button>
                            
                            <!-- Mic Input -->
                            <button id="mic-btn" onclick="toggleRecording()" title="Hablar con MaxTech" style="padding:10px; color:#6b7280; background:none; border:none; cursor:pointer; transition:all 0.2s;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
                            </button>

                            <!-- Text Input -->
                            <div style="flex:1; position:relative;">
                                <textarea id="chat-input" rows="1" placeholder="Escribe o habla..." style="width:100%; padding:10px 40px 10px 10px; border:1px solid #d1d5db; border-radius:20px; outline:none; resize:none; font-family:inherit; max-height:100px; min-height:44px; overflow-y:auto; box-sizing:border-box;"></textarea>
                            </div>

                            <!-- Send Button -->
                            <button onclick="sendMessage()" style="background:linear-gradient(135deg, #1e3a8a, #2563eb); color:white; border:none; width:44px; height:44px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 6px rgba(37,99,235,0.3);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Mensaje Popup Animado -->
                    <div id="maxtech-tooltip" style="position: absolute; bottom: 80px; right: 0; background: white; padding: 12px 16px; border-radius: 12px 12px 0 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 200px; font-size: 14px; line-height: 1.4; color: #444; opacity: 0; transform: translateY(20px); transition: all 0.5s ease; pointer-events: none;">
                        👋 ¡Hola <strong><?php echo esc_html($cliente->nombre); ?></strong>! <br>Estoy aquí para ayudarte con tu proyecto.
                    </div>

                    <button id="maxtech-launcher" onclick="toggleChat()" style="width:65px; height:65px; border-radius:50%; background:linear-gradient(135deg, #1e3a8a, #06d6a0); color:white; border:none; box-shadow:0 4px 15px rgba(0,0,0,0.3); cursor:pointer; font-size:32px; display:flex; align-items:center; justify-content:center; transition:transform 0.2s;">
                        🤖
                    </button>
                </div>

                <script>
                    let mediaRecorder;
                    let audioChunks = [];
                    let isRecording = false;
                    let selectedFiles = [];
                    
                    // Inicialización
                    document.addEventListener('DOMContentLoaded', function() {
                        var tooltip = document.getElementById('maxtech-tooltip');
                        if (tooltip) {
                            setTimeout(function() {
                                tooltip.style.opacity = '1';
                                tooltip.style.transform = 'translateY(0)';
                            }, 1500); 

                            setTimeout(function() {
                                tooltip.style.opacity = '0';
                                tooltip.style.transform = 'translateY(10px)';
                            }, 8000); 
                        }
                        
                        // Auto-resize textarea
                        const tx = document.getElementById('chat-input');
                        tx.addEventListener("input", function(){
                            this.style.height = 'auto';
                            this.style.height = (this.scrollHeight) + "px";
                        });
                        tx.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                sendMessage();
                            }
                        });
                        
                        // Cargar historial en background
                        fetchHistory();
                    });

                    function toggleChat() {
                        var chat = document.getElementById('maxtech-chat');
                        var btn = document.getElementById('maxtech-launcher');
                        var tooltip = document.getElementById('maxtech-tooltip');

                        if (chat.style.display === 'none') {
                            chat.style.display = 'flex';
                            btn.style.display = 'none'; // ocultar launcher
                            if(tooltip) tooltip.style.display = 'none';
                        } else {
                            chat.style.display = 'none';
                            btn.style.display = 'flex'; // mostrar launcher
                        }
                    }
                    
                    function toggleHistory() {
                        const sidebar = document.getElementById('chat-history-sidebar');
                        if (sidebar.style.transform === 'translateX(0%)') {
                            sidebar.style.transform = 'translateX(-100%)';
                        } else {
                            sidebar.style.transform = 'translateX(0%)';
                            fetchHistory(true); // Recargar
                        }
                    }

                    function handleFileSelect(input) {
                        const files = Array.from(input.files);
                        selectedFiles = selectedFiles.concat(files);
                        renderAttachments();
                    }

                    function renderAttachments() {
                        const container = document.getElementById('attachment-preview');
                        container.innerHTML = '';
                        if (selectedFiles.length > 0) {
                            container.style.display = 'flex';
                            selectedFiles.forEach((file, index) => {
                                const div = document.createElement('div');
                                div.style.cssText = 'min-width:60px; height:60px; background:#f3f4f6; border-radius:8px; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; border:1px solid #ddd;';
                                
                                div.innerHTML = `
                                    <div style="font-size:24px;">${file.type.startsWith('image/') ? '🖼️' : '📄'}</div>
                                    <div style="font-size:9px; max-width:50px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${file.name}</div>
                                    <button onclick="removeFile(${index})" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:16px; height:16px; font-size:10px; cursor:pointer;">&times;</button>
                                `;
                                container.appendChild(div);
                            });
                        } else {
                            container.style.display = 'none';
                        }
                    }
                    
                    function removeFile(index) {
                        selectedFiles.splice(index, 1);
                        renderAttachments();
                    }
                    
                    // GRABACIÓN DE AUDIO
                    async function toggleRecording() {
                        const btn = document.getElementById('mic-btn');
                        
                        if (!isRecording) {
                            try {
                                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                mediaRecorder = new MediaRecorder(stream);
                                audioChunks = [];
                                
                                mediaRecorder.addEventListener("dataavailable", event => {
                                    audioChunks.push(event.data);
                                });
                                
                                mediaRecorder.addEventListener("stop", () => {
                                    const audioBlob = new Blob(audioChunks, { type: 'audio/mp3' }); // o webm
                                    sendAudio(audioBlob);
                                });
                                
                                mediaRecorder.start();
                                isRecording = true;
                                btn.style.color = 'red';
                                btn.classList.add('recording-pulse');
                                document.getElementById('chat-input').placeholder = "Grabando... (Haz clic en microfono para enviar)";
                            } catch(err) {
                                alert("No se pudo acceder al micrófono: " + err);
                            }
                        } else {
                            mediaRecorder.stop();
                            isRecording = false;
                            btn.style.color = '#6b7280';
                            btn.classList.remove('recording-pulse');
                            document.getElementById('chat-input').placeholder = "Escribe o habla...";
                        }
                    }
                    
                    function sendAudio(blob) {
                         const chat = document.getElementById('chat-messages');
                         // Show user msg bubble (audio icon)
                         chat.innerHTML += `
                            <div style="display:flex; gap:10px; margin-bottom:15px; justify-content:flex-end;">
                                <div style="background:#1e3a8a; color:white; padding:10px 15px; border-radius:12px 12px 0 12px; max-width:80%;">
                                    🎤 [Audio Enviado]
                                </div>
                                <div style="font-size:20px;">👤</div>
                            </div>
                        `;
                        chat.scrollTop = chat.scrollHeight;
                        
                        // Show loading
                        const loadId = showLoading();
                        
                        const formData = new FormData();
                        formData.append('action', 'crm_chat_cliente');
                        formData.append('audio', blob, 'recording.mp3');
                        formData.append('cid', '<?php echo $cliente_id; ?>');
                        formData.append('token', '<?php echo $token; ?>');
                        formData.append('tts', 'true'); // Siempre pedimos audio de vuelta si hablamos
                        
                        doFetch(formData, loadId);
                    }

                    // --- NEW: TTS Toggle & Sorting State ---
                    let isTTSActive = true;
                    let currentSort = 'ASC';

                    function toggleTTS() {
                        const btn = document.getElementById('btn-tts-header'); // Botón en Header
                        isTTSActive = !isTTSActive;
                        if(isTTSActive) {
                            btn.innerHTML = '🔊';
                            btn.title = "Voz Activada";
                            btn.style.background = 'rgba(255,255,255,0.4)';
                        } else {
                            btn.innerHTML = '🔇';
                            btn.title = "Voz Desactivada";
                            btn.style.background = 'rgba(255,255,255,0.2)';
                        }
                    }

                    function toggleOrder() {
                        const btn = document.getElementById('btn-order-toggle');
                        currentSort = currentSort === 'ASC' ? 'DESC' : 'ASC';
                        btn.innerHTML = currentSort === 'ASC' ? '⬇️ Recientes abajo' : '⬆️ Recientes arriba';
                        fetchHistory(true); // Force reload
                    }

                    // --- Timer Bubble for TTS ---
                    setInterval(() => {
                        const bubble = document.getElementById('tts-helper-bubble');
                        // Show only if Audio is Active
                        if(bubble && isTTSActive) {
                            bubble.style.display = 'block';
                            // Hide after 6 seconds
                            setTimeout(() => {
                                bubble.style.display = 'none';
                            }, 6000);
                        }
                    }, 120000); // 120000 ms = 2 minutes
                    // ---------------------------------------

                    function sendMessage() {
                        const input = document.getElementById('chat-input');
                        const msg = input.value.trim();
                        
                        if (!msg && selectedFiles.length === 0) return;

                        var chat = document.getElementById('chat-messages');
                        
                        // Preview User Message
                        let userHtml = `<div style="display:flex; gap:10px; margin-bottom:15px; justify-content:flex-end;">
                                <div style="background:#1e3a8a; color:white; padding:12px 16px; border-radius:12px 12px 0 12px; max-width:85%; box-shadow:0 2px 5px rgba(0,0,0,0.1);">`;
                        
                        if (msg) userHtml += `<div>${msg.replace(/\n/g, '<br>')}</div>`;
                        
                        if (selectedFiles.length > 0) {
                            userHtml += `<div style="margin-top:5px; border-top:1px solid rgba(255,255,255,0.3); padding-top:5px; font-size:0.9em;">
                                ${selectedFiles.length} archivo(s) adjunto(s)
                            </div>`;
                        }
                        
                        userHtml += `</div><div style="font-size:20px;">👤</div></div>`;
                        chat.innerHTML += userHtml;
                        
                        input.value = '';
                        input.style.height = 'auto'; // Reset size
                        chat.scrollTop = chat.scrollHeight;

                        var loadId = showLoading();

                        var formData = new FormData();
                        formData.append('action', 'crm_chat_cliente');
                        formData.append('mensaje', msg);
                        formData.append('cid', '<?php echo $cliente_id; ?>');
                        formData.append('token', '<?php echo $token; ?>');
                        formData.append('tts', isTTSActive); // Send TTS preference
                        
                        if(selectedFiles.length > 0) {
                            selectedFiles.forEach(file => {
                                formData.append('file[]', file);
                            });
                        }
                        
                        // Clear files
                        selectedFiles = [];
                        renderAttachments();

                        doFetch(formData, loadId);
                    }
                    
                    function showLoading() {
                        const chat = document.getElementById('chat-messages');
                        const loadId = 'load-' + Date.now();
                        chat.innerHTML += `
                            <div id="${loadId}" style="display:flex; gap:10px; margin-bottom:15px;">
                                <div style="font-size:20px;">🤖</div>
                                <div style="background:white; padding:10px 15px; border-radius:0 12px 12px 12px; box-shadow:0 2px 5px rgba(0,0,0,0.05); color:#888;">
                                    <span class="typing-dots">⠓⠒⠁</span> Analizando...
                                </div>
                            </div>
                        `;
                        chat.scrollTop = chat.scrollHeight;
                        return loadId;
                    }

                    async function doFetch(formData, loadId) {
                        try {
                            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: formData
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }

                            const text = await response.text();
                            
                            try {
                                const data = JSON.parse(text);
                                var loadEl = document.getElementById(loadId);
                                
                                if (data.success) {
                                    let content = data.data.respuesta; // Texto
                                    let audio = data.data.audio_url;   // Audio URL si existe
                                    
                                    // Formatear Markdown básico
                                    content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                                    
                                    // Parsear Links Markdown [text](url)
                                    content = content.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" style="color:#2563eb; text-decoration:underline;">$1</a>');
                                    
                                    // Auto-link URLs sueltas que no estén ya en un tag <a> (simple fallback)
                                    // Reemplaza urls que empiezan por http/https y están rodeadas de espacios o al inicio/fin
                                    content = content.replace(/(^|[^"'])((https?:\/\/)[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener noreferrer" style="color:#2563eb; text-decoration:underline;">$2</a>');

                                    content = content.replace(/\n/g, '<br>');

                                    let html = `<div style="font-size:20px;">🤖</div>
                                        <div style="background:white; padding:12px 16px; border-radius:0 16px 16px 16px; box-shadow:0 2px 5px rgba(0,0,0,0.05); max-width:85%; color:#374151; line-height:1.5;">
                                            ${content}`;
                                    
                                    if (audio) {
                                        html += `<div style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">
                                            <audio controls autoplay style="width:100%; height:30px;">
                                                <source src="${audio}" type="audio/mpeg">
                                                Tu navegador no soporta audio.
                                            </audio>
                                        </div>`;
                                    }
                                    
                                    html += `</div>`;
                                    loadEl.innerHTML = html;
                                } else {
                                    console.error('API Error Data:', data);
                                    loadEl.innerHTML = `⚠️ Error: ${data.data || 'Error desconocido'}`;
                                }
                            } catch (e) {
                                console.error('JSON Parse Error:', e);
                                console.log('Raw Response:', text);
                                document.getElementById(loadId).innerHTML = `⚠️ Error de respuesta (No JSON). <br><small>${text.substring(0, 100)}...</small>`;
                            }

                        } catch (err) {
                             console.error('Fetch Error:', err);
                             document.getElementById(loadId).innerHTML = `⚠️ Error de conexión: ${err.message}`;
                        }
                        
                        document.getElementById('chat-messages').scrollTop = document.getElementById('chat-messages').scrollHeight;
                    }

                    function fetchHistory(forceRender = false) {
                        const formData = new FormData();
                        formData.append('action', 'crm_chat_history');
                        formData.append('cid', '<?php echo $cliente_id; ?>');
                        formData.append('token', '<?php echo $token; ?>');
                        formData.append('order', currentSort);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => {
                            const ct = r.headers.get('content-type') || '';
                            if (!ct.includes('application/json')) {
                                throw new Error('Respuesta no es JSON');
                            }
                            return r.json();
                        })
                        .then(d => {
                            if(d.success) {
                                const container = document.getElementById('history-content');
                                if(d.data.length === 0) {
                                    container.innerHTML = '<p>No hay historial previo.</p>';
                                    return;
                                }
                                
                                let html = '';
                                d.data.forEach(msg => {
                                    let bg = msg.role === 'user' ? '#eef2ff' : '#f9f9f9';
                                    let icon = msg.role === 'user' ? '👤' : '🤖';
                                    let time = new Date(msg.timestamp).toLocaleString();
                                    let excerpt = msg.content.replace(/<[^>]*>/g, '').substring(0, 80);

                                    html += `<div style="background:${bg}; padding:8px; border-radius:6px; margin-bottom:8px; border:1px solid #eee;">
                                        <div style="display:flex; justify-content:space-between; font-size:11px; color:#888; margin-bottom:4px;">
                                            <span>${icon} ${msg.role === 'user' ? 'Tú' : 'MAXTECH'}</span>
                                            <span>${time}</span>
                                        </div>
                                        <div style="font-size:12px; color:#333;">${excerpt}...</div>
                                    </div>`;
                                });
                                container.innerHTML = html;
                            }
                        })
                        .catch(() => {
                            const container = document.getElementById('history-content');
                            if (container) container.innerHTML = '<p style="color:#888;">Historial no disponible.</p>';
                        });
                    }
                </script>
                <style>
                    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
                    .recording-pulse { animation: pulse 1.5s infinite; }
                    .typing-dots { font-family: monospace; }
                </style>
                <script>
                // ========== PROJECT CAROUSEL ==========
                (function(){
                    var track = document.getElementById('projTrack');
                    var tabs = document.querySelectorAll('#projTabs .proj-tab');
                    var timerFill = document.getElementById('projTimerFill');
                    if (!track || !tabs.length) return;
                    var total = tabs.length;
                    var current = 0;
                    var interval = null;
                    var DURATION = 15000; // 15 seconds

                    function goTo(idx) {
                        current = idx;
                        if (current < 0) current = total - 1;
                        if (current >= total) current = 0;
                        track.style.transform = 'translateX(-' + (current * 100) + '%)';
                        tabs.forEach(function(t, i) { t.classList.toggle('active', i === current); });
                        // Scroll active tab into view
                        if (tabs[current]) tabs[current].scrollIntoView({ behavior:'smooth', block:'nearest', inline:'center' });
                        resetTimer();
                    }
                    function resetTimer() {
                        if (timerFill) {
                            timerFill.style.transition = 'none';
                            timerFill.style.width = '0%';
                            // Force reflow
                            void timerFill.offsetWidth;
                            timerFill.style.transition = 'width ' + (DURATION/1000) + 's linear';
                            timerFill.style.width = '100%';
                        }
                        clearInterval(interval);
                        if (total > 1) {
                            interval = setInterval(function() { goTo(current + 1); }, DURATION);
                        }
                    }
                    // Pause on hover
                    var wrap = document.getElementById('projCarousel');
                    if (wrap) {
                        wrap.addEventListener('mouseenter', function() {
                            clearInterval(interval);
                            if (timerFill) { timerFill.style.transition = 'none'; }
                        });
                        wrap.addEventListener('mouseleave', function() { resetTimer(); });
                    }
                    // Touch swipe support
                    var startX = 0;
                    track.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, {passive:true});
                    track.addEventListener('touchend', function(e) {
                        var diff = startX - e.changedTouches[0].clientX;
                        if (Math.abs(diff) > 50) goTo(current + (diff > 0 ? 1 : -1));
                    }, {passive:true});

                    window.projGoTo = function(idx) { goTo(idx); };
                    window.projNav = function(dir) { goTo(current + dir); };

                    // Start autoplay
                    resetTimer();
                })();

                // ========== TIMELINE TABS (Public) ==========
                function switchTimelineTab(tabName) {
                    document.querySelectorAll('.tl-tab-content').forEach(function(el) { el.style.display = 'none'; });
                    document.querySelectorAll('.tl-tab').forEach(function(el) { el.classList.remove('active'); });
                    var content = document.getElementById('tl-content-' + tabName);
                    if (content) content.style.display = 'block';
                    var tab = document.querySelector('.tl-tab[data-tab="' + tabName + '"]');
                    if (tab) tab.classList.add('active');
                }
                function mostrarMasTimeline(tabName, total) {
                    document.querySelectorAll('.tl-page-' + tabName).forEach(function(el) { el.style.display = 'block'; });
                    var moreBtn = document.getElementById('tl-more-' + tabName);
                    if (moreBtn) moreBtn.style.display = 'none';
                }

                // ========== QA CAROUSEL ==========
                var qaCarouselState = {};
                function qaCarouselNav(carId, dir) {
                    var track = document.getElementById(carId + '-track');
                    if (!track) return;
                    var slides = track.querySelectorAll('.qa-carousel-slide');
                    var total = slides.length;
                    if (!qaCarouselState[carId]) qaCarouselState[carId] = 0;
                    qaCarouselState[carId] += dir;
                    if (qaCarouselState[carId] < 0) qaCarouselState[carId] = total - 1;
                    if (qaCarouselState[carId] >= total) qaCarouselState[carId] = 0;
                    qaCarouselGo(carId, qaCarouselState[carId]);
                }
                function qaCarouselGo(carId, idx) {
                    var track = document.getElementById(carId + '-track');
                    if (!track) return;
                    var total = track.querySelectorAll('.qa-carousel-slide').length;
                    qaCarouselState[carId] = idx;
                    track.style.transform = 'translateX(-' + (idx * 100) + '%)';
                    // Update counter
                    var counter = document.getElementById(carId + '-counter');
                    if (counter) counter.textContent = (idx + 1) + ' / ' + total;
                    // Update dots
                    var dots = document.getElementById(carId + '-dots');
                    if (dots) {
                        dots.querySelectorAll('.qa-carousel-dot').forEach(function(d, i) {
                            d.classList.toggle('active', i === idx);
                        });
                    }
                }

                // ========== QA LIGHTBOX GALLERY ==========
                var qaLbImages = [];
                var qaLbIdx = 0;
                var qaLbZoomLevel = 1;
                function qaLightbox(src) {
                    // Collect all images from the same carousel
                    var clicked = event.target;
                    var carousel = clicked.closest('.qa-carousel');
                    qaLbImages = [];
                    if (carousel) {
                        carousel.querySelectorAll('.qa-carousel-slide img').forEach(function(im) {
                            qaLbImages.push(im.src);
                        });
                        qaLbIdx = qaLbImages.indexOf(src);
                        if (qaLbIdx < 0) qaLbIdx = 0;
                    } else {
                        qaLbImages = [src];
                        qaLbIdx = 0;
                    }
                    qaLbZoomLevel = 1;
                    qaLbShow();
                }
                function qaLbShow() {
                    var overlay = document.getElementById('qa-lightbox-overlay');
                    var img = document.getElementById('qa-lightbox-img');
                    var counter = document.getElementById('qa-lb-counter');
                    if (!overlay || !img) return;
                    img.src = qaLbImages[qaLbIdx];
                    img.style.transform = 'scale(1)';
                    qaLbZoomLevel = 1;
                    overlay.classList.add('show');
                    if (counter) counter.textContent = (qaLbIdx + 1) + ' / ' + qaLbImages.length;
                    overlay.querySelectorAll('.qa-lb-nav').forEach(function(b) {
                        b.style.display = qaLbImages.length > 1 ? '' : 'none';
                    });
                }
                function qaLbNav(dir) {
                    event.stopPropagation();
                    qaLbIdx += dir;
                    if (qaLbIdx < 0) qaLbIdx = qaLbImages.length - 1;
                    if (qaLbIdx >= qaLbImages.length) qaLbIdx = 0;
                    qaLbShow();
                }
                function qaLbZoom(dir) {
                    event.stopPropagation();
                    var img = document.getElementById('qa-lightbox-img');
                    if (!img) return;
                    if (dir === 0) { qaLbZoomLevel = 1; }
                    else { qaLbZoomLevel += dir * 0.3; }
                    qaLbZoomLevel = Math.max(0.3, Math.min(qaLbZoomLevel, 5));
                    img.style.transform = 'scale(' + qaLbZoomLevel + ')';
                }
                function qaLightboxClose() {
                    var overlay = document.getElementById('qa-lightbox-overlay');
                    if (overlay) overlay.classList.remove('show');
                }
                document.addEventListener('keydown', function(e) {
                    var overlay = document.getElementById('qa-lightbox-overlay');
                    if (!overlay || !overlay.classList.contains('show')) return;
                    if (e.key === 'Escape') qaLightboxClose();
                    else if (e.key === 'ArrowLeft') qaLbNav(-1);
                    else if (e.key === 'ArrowRight') qaLbNav(1);
                    else if (e.key === '+' || e.key === '=') qaLbZoom(1);
                    else if (e.key === '-') qaLbZoom(-1);
                });
                // Mouse wheel zoom in lightbox
                document.addEventListener('wheel', function(e) {
                    var overlay = document.getElementById('qa-lightbox-overlay');
                    if (!overlay || !overlay.classList.contains('show')) return;
                    e.preventDefault();
                    qaLbZoom(e.deltaY < 0 ? 1 : -1);
                }, {passive: false});

                // ========== QA FILTER (Public) ==========
                function pubQaFilter(modUid, status, btn) {
                    var bar = document.getElementById(modUid + '-filters');
                    if (bar) bar.querySelectorAll('.pub-qa-fbtn').forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    document.querySelectorAll('.pub-qa-case[data-qa-mod="' + modUid + '"]').forEach(function(row) {
                        row.style.display = (status === 'all' || row.getAttribute('data-qa-st') === status) ? '' : 'none';
                    });
                    // Show/hide section headers
                    var body = document.getElementById(modUid + '-body');
                    if (body) {
                        body.querySelectorAll('.pub-qa-sec-' + modUid).forEach(function(hdr) {
                            var next = hdr.nextElementSibling, anyVisible = false;
                            while (next && !next.classList.contains('pub-qa-section')) {
                                if (next.classList.contains('pub-qa-case') && next.style.display !== 'none') anyVisible = true;
                                next = next.nextElementSibling;
                            }
                            hdr.style.display = anyVisible ? '' : 'none';
                        });
                    }
                }
                </script>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // ========== VISTA PÚBLICA PROSPECT TIMELINE ==========
    
    private function _generar_token_prospecto($propuesta_id, $email) {
        return md5($propuesta_id . 'AUTOMATIZA_PROSPECT_V1' . $email);
    }
    
    public static function get_prospect_timeline_url($propuesta_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'automatiza_propuestas';
        $propuesta = $wpdb->get_row($wpdb->prepare("SELECT id, client_email FROM $table WHERE id = %d", $propuesta_id));
        if (!$propuesta || empty($propuesta->client_email)) return '';
        $token = md5($propuesta->id . 'AUTOMATIZA_PROSPECT_V1' . $propuesta->client_email);
        return 'https://automatizatech.cl/?crm_view=prospect_timeline&pid=' . $propuesta->id . '&token=' . $token;
    }
    
    /**
     * Buscar propuesta por email y generar URL pública de timeline de prospecto
     */
    public static function get_prospect_timeline_url_by_email($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'automatiza_propuestas';
        $propuesta = $wpdb->get_row($wpdb->prepare("SELECT id, client_email FROM $table WHERE client_email = %s ORDER BY id DESC LIMIT 1", $email));
        if (!$propuesta) return '';
        return self::get_prospect_timeline_url($propuesta->id);
    }
    
    public function render_public_prospect_timeline() {
        if (is_admin()) return;
        
        if (!isset($_GET['crm_view']) || $_GET['crm_view'] !== 'prospect_timeline') return;
        
        global $wpdb;
        
        $propuesta_id = intval($_GET['pid'] ?? 0);
        $token = $_GET['token'] ?? '';
        
        if (!$propuesta_id || !$token) {
            wp_die('Enlace no válido.');
        }
        
        $table_propuestas = $wpdb->prefix . 'automatiza_propuestas';
        $table_details = $wpdb->prefix . 'automatiza_propuestas_details';
        
        $propuesta = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_propuestas WHERE id = %d", $propuesta_id));
        
        if (!$propuesta || $this->_generar_token_prospecto($propuesta->id, $propuesta->client_email) !== $token) {
            wp_die('Acceso denegado o enlace expirado.');
        }
        
        // Obtener detalles de seguimiento
        $timeline_items = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_details'") == $table_details) {
            $details = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_details WHERE propuesta_id = %d", $propuesta_id), ARRAY_A);
            foreach ($details as $d) {
                if (!empty($d['completed_date'])) {
                    $ts = strtotime($d['completed_date']);
                } elseif (!empty($d['scheduled_date'])) {
                    $ts = strtotime($d['scheduled_date']);
                } else {
                    $ts = strtotime($d['created_at']);
                }
                $d['timestamp'] = $ts;
                $d['display_date'] = date('Y-m-d H:i:s', $ts);
                $timeline_items[] = $d;
            }
        }
        
        // Agregar item auto de propuesta enviada si existe PDF/Gamma
        if (!empty($propuesta->pdf_url) || !empty($propuesta->gamma_iframe_url)) {
            $has_propuesta_item = false;
            foreach ($timeline_items as $item) {
                if (($item['detail_type'] ?? '') === 'propuesta_enviada') {
                    $has_propuesta_item = true;
                    break;
                }
            }
            if (!$has_propuesta_item) {
                $ts = strtotime($propuesta->created_at);
                $timeline_items[] = [
                    'id' => 0,
                    'detail_type' => 'propuesta_enviada',
                    'title' => 'Propuesta Creada',
                    'description' => 'Se generó tu propuesta personalizada.' . (!empty($propuesta->gamma_iframe_url) ? ' Puedes verla en línea.' : ''),
                    'created_at' => $propuesta->created_at,
                    'timestamp' => $ts,
                    'display_date' => $propuesta->created_at,
                    'attachment_url' => $propuesta->pdf_url ?? '',
                    'attachment_name' => 'Propuesta.pdf',
                    'amount' => 0,
                    'currency' => '',
                    'metadata' => '',
                ];
            }
        }
        
        // Ordenar cronológico inverso
        usort($timeline_items, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Configuración de tipos
        $detail_types = [
            'propuesta_enviada' => ['label' => 'Propuesta Enviada', 'icon' => '📄', 'color' => '#667eea', 'bg' => '#ebf4ff'],
            'cotizacion' => ['label' => 'Cotización', 'icon' => '💰', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
            'reunion' => ['label' => 'Reunión', 'icon' => '🤝', 'color' => '#10b981', 'bg' => '#d1fae5'],
            'llamada' => ['label' => 'Llamada', 'icon' => '📞', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
            'email' => ['label' => 'Email', 'icon' => '📧', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
            'boleta' => ['label' => 'Boleta', 'icon' => '🧾', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
            'factura' => ['label' => 'Factura', 'icon' => '📋', 'color' => '#14b8a6', 'bg' => '#f0fdfa'],
            'pago' => ['label' => 'Pago Recibido', 'icon' => '💳', 'color' => '#22c55e', 'bg' => '#dcfce7'],
            'item_proyecto' => ['label' => 'Item de Proyecto', 'icon' => '📦', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
            'entregable' => ['label' => 'Entregable', 'icon' => '✅', 'color' => '#84cc16', 'bg' => '#ecfccb'],
            'nota' => ['label' => 'Nota', 'icon' => '📝', 'color' => '#94a3b8', 'bg' => '#f1f5f9'],
        ];
        
        // Clasificar para pestañas
        $tabs = [
            'todos' => $timeline_items,
            'reuniones' => [],
            'documentos' => [],
            'pagos' => [],
        ];
        foreach ($timeline_items as $item) {
            $dtype = $item['detail_type'] ?? 'nota';
            if (in_array($dtype, ['reunion', 'llamada'])) $tabs['reuniones'][] = $item;
            if (in_array($dtype, ['propuesta_enviada', 'cotizacion', 'nota', 'email', 'entregable'])) $tabs['documentos'][] = $item;
            if (in_array($dtype, ['pago', 'boleta', 'factura'])) $tabs['pagos'][] = $item;
        }

        $tab_counts = array_map('count', $tabs);
        
        // Helper: check if URL is image
        $is_image_url = function($url) {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
        };
        
        // Helper: render single item
        $render_item = function($h) use ($detail_types, $is_image_url) {
            $type = $h['detail_type'] ?? 'nota';
            $config = $detail_types[$type] ?? $detail_types['nota'];
            $amount_display = '';
            if (!empty($h['amount']) && $h['amount'] > 0) {
                $amount_display = '<span style="color:#22c55e; font-weight:bold; margin-left:10px;">$' . number_format($h['amount'], 0, ',', '.') . ' ' . ($h['currency'] ?? 'CLP') . '</span>';
            }
            $attachment_html = '';
            if (!empty($h['attachment_url'])) {
                $file_name = !empty($h['attachment_name']) ? $h['attachment_name'] : basename($h['attachment_url']);
                $preview = '';
                if ($is_image_url($h['attachment_url'])) {
                    $preview = '<div class="img-preview"><a href="' . esc_url($h['attachment_url']) . '" target="_blank"><img src="' . esc_url($h['attachment_url']) . '" alt="' . esc_attr($file_name) . '" loading="lazy"></a></div>';
                }
                $attachment_html .= '<div style="margin-top:10px; padding:8px; background:white; border:1px solid #e2e8f0; border-radius:6px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:20px;">📎</span>
                        <a href="' . esc_url($h['attachment_url']) . '" target="_blank" style="text-decoration:none; color:#3b82f6; font-weight:500;">' . esc_html($file_name) . '</a>
                        <a href="' . esc_url($h['attachment_url']) . '" download style="margin-left:auto; font-size:11px; padding:2px 8px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#555; background:#eee;">⬇ Descargar</a>
                    </div>' . $preview . '
                </div>';
            }
            if (!empty($h['metadata'])) {
                $meta = json_decode($h['metadata'], true);
                if (!empty($meta['evidencia']) && is_array($meta['evidencia'])) {
                    foreach ($meta['evidencia'] as $link) {
                        $file_name = basename($link);
                        $preview = '';
                        if ($is_image_url($link)) {
                            $preview = '<div class="img-preview"><a href="' . esc_url($link) . '" target="_blank"><img src="' . esc_url($link) . '" alt="' . esc_attr($file_name) . '" loading="lazy"></a></div>';
                        }
                        $attachment_html .= '<div style="margin-top:5px; padding:8px; background:white; border:1px solid #e2e8f0; border-radius:6px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:20px;">📎</span>
                                <a href="' . esc_url($link) . '" target="_blank" style="text-decoration:none; color:#3b82f6; font-weight:500;">' . esc_html($file_name) . '</a>
                                <a href="' . esc_url($link) . '" download style="margin-left:auto; font-size:11px; padding:2px 8px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#555; background:#eee;">⬇ Descargar</a>
                            </div>' . $preview . '
                        </div>';
                    }
                }
            }
            ?>
            <div class="timeline-item">
                <div class="timeline-marker" style="background:<?php echo $config['color']; ?>; box-shadow: 0 0 0 2px <?php echo $config['color']; ?>;"></div>
                <div class="timeline-date"><?php echo date('d/m/Y H:i', $h['timestamp']); ?></div>
                <div class="timeline-content" style="border-left: 4px solid <?php echo $config['color']; ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <span class="badge" style="background:<?php echo $config['color']; ?>;"><?php echo $config['icon'] . ' ' . $config['label']; ?></span>
                        <?php echo $amount_display; ?>
                    </div>
                    <h3 style="margin-top:8px;"><?php echo esc_html($h['title'] ?? ''); ?></h3>
                    <p style="white-space: pre-wrap; margin-bottom: 0;"><?php echo nl2br(esc_html($h['description'] ?? '')); ?></p>
                    <?php echo $attachment_html; ?>
                </div>
            </div>
            <?php
        };
        
        // Helper: render tab with pagination
        $render_tab = function($items, $tab_name, $max_visible = 10) use ($render_item) {
            if (empty($items)) {
                echo '<p style="color:#888; font-style:italic; padding:20px 0;">No hay registros en esta categoría.</p>';
                return;
            }
            foreach ($items as $idx => $h) {
                $hidden = ($idx >= $max_visible) ? 'style="display:none;"' : '';
                echo '<div class="tl-page-item tl-page-' . $tab_name . '" ' . $hidden . '>';
                $render_item($h);
                echo '</div>';
            }
            $total = count($items);
            if ($total > $max_visible) {
                echo '<div class="tl-show-more" id="tl-more-' . $tab_name . '">
                    <button onclick="mostrarMasTimeline(\'' . $tab_name . '\', ' . $total . ')" style="width:100%; text-align:center; margin-top:10px; padding:8px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:6px; cursor:pointer; font-family:inherit;">
                        📄 Ver más (' . ($total - $max_visible) . ' registros restantes)
                    </button>
                </div>';
            }
        };
        
        // Propuesta link (gamma)
        $gamma_url = $propuesta->gamma_iframe_url ?? '';
        $pdf_url = $propuesta->pdf_url ?? '';
        
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Seguimiento - <?php echo esc_html($propuesta->client_name ?: 'Prospecto'); ?> - AutomatizaTech</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; margin: 0; padding: 0; line-height: 1.6; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 40px 20px; text-align: center; }
                .header img { max-width: 180px; margin-bottom: 15px; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; }
                .container { max-width: 900px; margin: -30px auto 40px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 40px; position: relative; }
                h1 { margin: 0 0 10px; font-size: 2em; }
                h2 { color: #667eea; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 40px; }
                .prospect-info { background: #f0f0ff; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 5px solid #667eea; }
                .prospect-info strong { color: #4c51bf; }
                
                /* Demo Section */
                .demo-section { background: linear-gradient(135deg, #ebf4ff, #e0e7ff); border: 2px solid #667eea; border-radius: 12px; overflow: hidden; margin: 15px 0 30px; }
                .demo-section-header { padding: 20px 24px 12px; }
                .demo-section-header h3 { color: #4c51bf; margin: 0 0 6px; font-size: 1.25em; }
                .demo-section-header p { color: #64748b; margin: 0; font-size: 0.95em; }
                .demo-iframe-wrapper { position: relative; width: 100%; padding-bottom: 56.25%; /* 16:9 */ background: #1a1a2e; overflow: hidden; }
                .demo-iframe-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
                .demo-actions { display: flex; flex-wrap: wrap; gap: 10px; padding: 16px 24px 20px; }
                .btn-demo { display: inline-flex; align-items: center; justify-content: center; padding: 11px 26px; color: #fff !important; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 14px; transition: transform 0.15s, box-shadow 0.15s; flex: 1 1 auto; min-width: 180px; text-align: center; }
                .btn-demo:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.2); }
                .btn-demo-view { background: linear-gradient(135deg, #667eea, #764ba2); }
                .btn-demo-pdf { background: linear-gradient(135deg, #ef4444, #dc2626); }
                
                /* Legacy proposal-card (keep for safety) */
                .proposal-card { background: linear-gradient(135deg, #ebf4ff, #e0e7ff); border: 2px solid #667eea; border-radius: 12px; padding: 20px; margin: 15px 0 30px; }
                .proposal-card h3 { color: #4c51bf; margin: 0 0 10px; }
                .proposal-card .btn-proposal { display: inline-block; padding: 10px 24px; margin: 5px 5px 5px 0; color: #fff !important; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 13px; }
                .proposal-card .btn-view { background: linear-gradient(135deg, #667eea, #764ba2); }
                .proposal-card .btn-pdf { background: linear-gradient(135deg, #ef4444, #dc2626); }
                
                /* Timeline */
                .timeline { position: relative; padding-left: 30px; margin-top: 20px; }
                .timeline::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #e0e0e0; }
                .timeline-item { margin-bottom: 30px; position: relative; }
                .timeline-marker { position: absolute; left: -36px; top: 5px; width: 15px; height: 15px; background: #667eea; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #667eea; }
                .timeline-date { font-size: 0.85em; color: #888; margin-bottom: 5px; font-weight: 600; }
                .timeline-content { background: #f9f9f9; padding: 15px 20px; border-radius: 8px; border: 1px solid #eee; }
                .timeline-content h3 { margin: 0 0 5px; color: #333; font-size: 1.1em; }
                .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8em; font-weight: bold; color: white; }
                .footer { text-align: center; margin-top: 50px; color: #999; font-size: 0.9em; }
                
                /* Tabs */
                .timeline-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin:0; overflow-x:auto; }
                .tl-tab { padding: 10px 18px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 500; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 6px; font-family: inherit; }
                .tl-tab:hover { color: #667eea; background: #f0f0ff; }
                .tl-tab.active { color: #667eea; border-bottom-color: #667eea; font-weight: 600; }
                .tl-tab-count { background: #e2e8f0; color: #475569; padding: 1px 7px; border-radius: 10px; font-size: 11px; font-weight: 600; }
                .tl-tab.active .tl-tab-count { background: #e0e7ff; color: #667eea; }
                .tl-tab-content { padding-top: 15px; }
                .img-preview { margin-top:8px; }
                .img-preview img { max-width:220px; max-height:160px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer; transition: transform 0.2s, box-shadow 0.2s; }
                .img-preview img:hover { transform: scale(1.03); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
                
                /* Empty state */
                .empty-state { text-align: center; padding: 60px 20px; }
                .empty-state .icon { font-size: 64px; margin-bottom: 20px; }
                .empty-state h3 { color: #667eea; margin: 0 0 10px; font-size: 1.3em; }
                .empty-state p { color: #888; max-width: 400px; margin: 0 auto; }
                
                /* Responsive Tablet */
                @media screen and (max-width: 768px) {
                    .header { padding: 25px 15px; }
                    .header img { max-width: 140px; }
                    h1 { font-size: 1.5em; }
                    h2 { font-size: 1.2em; }
                    .container { padding: 25px 20px; margin: -20px 10px 30px; border-radius: 10px; }
                    .prospect-info { padding: 15px; font-size: 14px; }
                    .demo-section-header { padding: 16px 18px 10px; }
                    .demo-section-header h3 { font-size: 1.1em; }
                    .demo-section-header p { font-size: 0.88em; }
                    .demo-actions { padding: 12px 18px 16px; gap: 8px; }
                    .btn-demo { padding: 10px 20px; font-size: 13px; min-width: 150px; }
                    .timeline-tabs { scrollbar-width: none; -webkit-overflow-scrolling: touch; }
                    .timeline-tabs::-webkit-scrollbar { display: none; }
                    .tl-tab { padding: 8px 12px; font-size: 12px; }
                    .tl-tab-count { font-size: 10px; padding: 1px 5px; }
                    .timeline-content h3 { font-size: 0.95em; }
                    .timeline-content { padding: 12px 15px; }
                    .img-preview img { max-width: 180px; max-height: 130px; }
                    .proposal-card .btn-proposal { display: block; text-align: center; margin: 5px 0; }
                }
                
                /* Responsive Mobile */
                @media screen and (max-width: 480px) {
                    body { font-size: 14px; }
                    .header { padding: 20px 12px; }
                    .header img { max-width: 120px; padding: 8px; }
                    h1 { font-size: 1.3em; }
                    h2 { font-size: 1.1em; margin-top: 30px; }
                    .container { padding: 18px 14px; margin: -15px 6px 25px; border-radius: 8px; }
                    .prospect-info { padding: 12px; font-size: 13px; border-left-width: 3px; }
                    .demo-section { border-radius: 8px; margin: 10px 0 20px; border-width: 1.5px; }
                    .demo-section-header { padding: 14px 14px 8px; }
                    .demo-section-header h3 { font-size: 1em; }
                    .demo-section-header p { font-size: 0.82em; }
                    .demo-iframe-wrapper { padding-bottom: 62%; /* taller ratio on mobile */ }
                    .demo-actions { flex-direction: column; padding: 10px 14px 14px; gap: 8px; }
                    .btn-demo { width: 100%; min-width: unset; padding: 12px 16px; font-size: 13px; }
                    .timeline { padding-left: 22px; }
                    .timeline::before { width: 2px; }
                    .timeline-marker { width: 12px; height: 12px; left: -30px; }
                    .timeline-date { font-size: 0.78em; }
                    .timeline-content { padding: 10px 12px; font-size: 0.9em; border-left-width: 3px !important; }
                    .timeline-content h3 { font-size: 0.9em; }
                    .img-preview img { max-width: 100%; max-height: 200px; }
                    .tl-tab { padding: 7px 10px; font-size: 11px; }
                    .tl-tab-count { font-size: 9px; }
                    .footer { font-size: 0.8em; margin-top: 30px; }
                }
                
                /* Touch */
                @media (hover: none) and (pointer: coarse) {
                    .tl-tab { min-height: 44px; display: flex; align-items: center; }
                    .timeline-content { -webkit-tap-highlight-color: transparent; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="AutomatizaTech">
                <p>Seguimiento de Prospecto</p>
            </div>
            
            <div class="container">
                <h1>Hola, <?php echo esc_html($propuesta->client_name ?: 'Estimado/a'); ?></h1>
                <p>Aquí puedes ver el estado de tu proceso y el historial de seguimiento con AutomatizaTech.</p>
                
                <div class="prospect-info">
                    <?php if ($propuesta->client_name): ?>
                        <strong>Nombre:</strong> <?php echo esc_html($propuesta->client_name); ?><br>
                    <?php endif; ?>
                    <?php if ($propuesta->company_name): ?>
                        <strong>Empresa:</strong> <?php echo esc_html($propuesta->company_name); ?><br>
                    <?php endif; ?>
                    <strong>Email:</strong> <?php echo esc_html($propuesta->client_email); ?>
                    <?php if ($propuesta->status): ?>
                        <br><strong>Estado:</strong> 
                        <?php 
                        $status_labels = [
                            'pending' => '⏳ Pendiente',
                            'sent' => '📤 Enviada',
                            'viewed' => '👁️ Vista',
                            'accepted' => '✅ Aceptada',
                            'rejected' => '❌ Rechazada',
                            'converted' => '🎉 Convertido',
                        ];
                        echo $status_labels[$propuesta->status] ?? ucfirst($propuesta->status);
                        ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($gamma_url || $pdf_url): ?>
                    <div class="demo-section">
                        <div class="demo-section-header">
                            <h3>🎬 Tu Demo / Propuesta</h3>
                            <p>Revisa la presentación interactiva que preparamos especialmente para ti.</p>
                        </div>
                        
                        <?php if ($gamma_url): ?>
                            <div class="demo-iframe-wrapper">
                                <iframe src="<?php echo esc_url($gamma_url); ?>" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                            </div>
                        <?php endif; ?>
                        
                        <div class="demo-actions">
                            <?php if ($gamma_url): ?>
                                <a href="<?php echo esc_url($gamma_url); ?>" target="_blank" class="btn-demo btn-demo-view">🌐 Ver en Pantalla Completa</a>
                            <?php endif; ?>
                            <?php if ($pdf_url): ?>
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="btn-demo btn-demo-pdf">📥 Descargar PDF</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <h2>📜 Historial de Seguimiento</h2>
                
                <?php if (empty($timeline_items)): ?>
                    <div class="empty-state">
                        <div class="icon">📋</div>
                        <h3>Aún no hay registros</h3>
                        <p>Tu historial de seguimiento aparecerá aquí a medida que avance el proceso. Pronto verás reuniones, notas y documentos relacionados.</p>
                    </div>
                <?php else: ?>
                    <!-- Pestañas -->
                    <div class="timeline-tabs">
                        <button class="tl-tab active" data-tab="todos" onclick="switchTab('todos')">📋 Todos <span class="tl-tab-count"><?php echo $tab_counts['todos']; ?></span></button>
                        <button class="tl-tab" data-tab="reuniones" onclick="switchTab('reuniones')">🤝 Reuniones <span class="tl-tab-count"><?php echo $tab_counts['reuniones']; ?></span></button>
                        <button class="tl-tab" data-tab="documentos" onclick="switchTab('documentos')">📄 Documentos <span class="tl-tab-count"><?php echo $tab_counts['documentos']; ?></span></button>
                        <button class="tl-tab" data-tab="pagos" onclick="switchTab('pagos')">💰 Pagos <span class="tl-tab-count"><?php echo $tab_counts['pagos']; ?></span></button>
                    </div>
                    
                    <!-- Tab: Todos -->
                    <div class="tl-tab-content" id="tl-content-todos" style="display:block;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_tab($tabs['todos'], 'todos', 10); ?>
                        </div>
                    </div>
                    
                    <!-- Tab: Reuniones -->
                    <div class="tl-tab-content" id="tl-content-reuniones" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_tab($tabs['reuniones'], 'reuniones', 10); ?>
                        </div>
                    </div>
                    
                    <!-- Tab: Documentos -->
                    <div class="tl-tab-content" id="tl-content-documentos" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_tab($tabs['documentos'], 'documentos', 10); ?>
                        </div>
                    </div>
                    
                    <!-- Tab: Pagos -->
                    <div class="tl-tab-content" id="tl-content-pagos" style="display:none;">
                        <div class="timeline" style="max-height:600px; overflow-y:auto; padding-right:10px;">
                            <?php $render_tab($tabs['pagos'], 'pagos', 10); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div style="text-align:center; margin-top:40px; padding:20px; background:#f0f9ff; border-radius:8px;">
                    <p style="margin:0 0 10px; color:#667eea; font-weight:600;">¿Tienes preguntas?</p>
                    <a href="https://wa.me/56927002984?text=Hola,%20tengo%20una%20consulta%20sobre%20mi%20propuesta" style="display:inline-block; padding:12px 28px; background:linear-gradient(135deg,#25D366,#20bd5a); color:white; text-decoration:none; border-radius:25px; font-weight:600; font-size:14px;">💬 Escríbenos por WhatsApp</a>
                </div>
                
                <div class="footer">
                    &copy; <?php echo date('Y'); ?> AutomatizaTech. Todos los derechos reservados.
                </div>
            </div>
            
            <script>
            function switchTab(tabName) {
                document.querySelectorAll('.tl-tab-content').forEach(function(el) { el.style.display = 'none'; });
                document.querySelectorAll('.tl-tab').forEach(function(el) { el.classList.remove('active'); });
                var content = document.getElementById('tl-content-' + tabName);
                if (content) content.style.display = 'block';
                var tab = document.querySelector('.tl-tab[data-tab="' + tabName + '"]');
                if (tab) tab.classList.add('active');
            }
            function mostrarMasTimeline(tabName, total) {
                document.querySelectorAll('.tl-page-' + tabName).forEach(function(el) { el.style.display = 'block'; });
                var moreBtn = document.getElementById('tl-more-' + tabName);
                if (moreBtn) moreBtn.style.display = 'none';
            }
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    // ========== HELPER: UPGRADE DB SCHEMA ==========
    private function _ensure_db_schema() {
        global $wpdb;

        $tabla = $this->tabla_chat_historial;

        // 1. Si la tabla no existe, crearla completa con dbDelta
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tabla}'") != $tabla) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$tabla} (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              session_id varchar(100) NOT NULL,
              user_id bigint(20) unsigned DEFAULT NULL,
              role enum('user','assistant') NOT NULL,
              content text NOT NULL,
              archivos json DEFAULT NULL,
              audio_url varchar(500) DEFAULT NULL,
              created_at timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY  (id),
              KEY idx_session (session_id),
              KEY idx_user (user_id)
            ) $charset;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            return;
        }

        // 2. Tabla existe: agregar columnas faltantes con ALTER TABLE
        $columnas_requeridas = [
            'archivos'  => "ALTER TABLE {$tabla} ADD COLUMN archivos json DEFAULT NULL AFTER content",
            'audio_url' => "ALTER TABLE {$tabla} ADD COLUMN audio_url varchar(500) DEFAULT NULL AFTER archivos",
        ];

        foreach ($columnas_requeridas as $col_name => $alter_sql) {
            $col = $wpdb->get_results("SHOW COLUMNS FROM {$tabla} LIKE '{$col_name}'");
            if (empty($col)) {
                $wpdb->query($alter_sql);
            }
        }
    }

    // ========== API: RECUPERAR HISTORIAL DE CHAT ==========
    public function ajax_crm_recover_chat_history() {
        // Limpiar output buffer
        @ini_set('display_errors', '0');
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();

        try {
            $this->_ajax_chat_history_inner();
        } catch (\Throwable $e) {
            error_log('MAXTECH Chat History Error: ' . $e->getMessage());
            ob_end_clean();
            wp_send_json_error('Error al cargar historial.');
        }
    }

    private function _ajax_chat_history_inner() {
        global $wpdb;
        
        // Ensure Schema
        $this->_ensure_db_schema();

        $cliente_id = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $order = isset($_POST['order']) && strtoupper($_POST['order']) === 'DESC' ? 'DESC' : 'ASC';

        $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));

        if (!$cliente || $this->_generar_token($cliente->id, $cliente->email) !== $token) {
            wp_send_json_error('Acceso denegado.');
        }

        $session_id = 'client_' . $cliente_id . '_chat';

        // Recuperar últimos 50 mensajes
        // Usamos 'role' y 'content' para compatibilidad con plugins existentes
        $mensajes = $wpdb->get_results($wpdb->prepare(
            "SELECT id, role, content, archivos, created_at, audio_url 
             FROM {$this->tabla_chat_historial} 
             WHERE session_id = %s 
             ORDER BY created_at {$order} 
             LIMIT 50", 
            $session_id
        ));

        // Formatear para el frontend
        $chat_history = [];
        foreach ($mensajes as $msg) {
            $files_html = '';
            if (!empty($msg->archivos)) {
                $files = json_decode($msg->archivos, true);
                if (is_array($files)) {
                    foreach ($files as $f) {
                        $files_html .= '<div class="chat-file-attachment"><a href="'.esc_url($f).'" target="_blank">📎 Adjunto</a></div>';
                    }
                }
            }
            
            // Add Audio Player if exists
            $audio_html = '';
            if (!empty($msg->audio_url)) {
                 $audio_html = '<div style="margin-top:5px; border-top:1px solid #eee; padding-top:5px;">
                                    <audio controls style="width:100%; height:25px;">
                                        <source src="'.esc_url($msg->audio_url).'" type="audio/mpeg">
                                    </audio>
                                </div>';
            }

            $chat_history[] = [
                'role' => $msg->role, 
                'content' => $msg->content . $files_html . $audio_html,
                'timestamp' => $msg->created_at
            ];
        }

        wp_send_json_success($chat_history);
    }
    
    // ========== CHAT MAXTECH CLIENTE ==========
    public function ajax_chat_cliente() {
        // Limpiar output buffer para evitar que warnings/notices corrompan la respuesta JSON
        @ini_set('display_errors', '0');
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();

        try {
            $this->_ajax_chat_cliente_inner();
        } catch (\Throwable $e) {
            error_log('MAXTECH Client Chat Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            ob_end_clean();
            wp_send_json_error('Error interno del chat. Por favor intenta de nuevo.');
        }
    }

    private function _ajax_chat_cliente_inner() {
        global $wpdb;

        // Ensure Schema
        $this->_ensure_db_schema();

        $cliente_id = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $mensaje = isset($_POST['mensaje']) ? sanitize_textarea_field($_POST['mensaje']) : '';
        $tts_requested = isset($_POST['tts']) && $_POST['tts'] === 'true';

        $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));

        if (!$cliente || $this->_generar_token($cliente->id, $cliente->email) !== $token) {
            wp_send_json_error('Acceso denegado o token inválido.');
        }

        $session_id = 'client_' . $cliente->id . '_chat';
        $uploaded_files_urls = [];
        
        // 1. PROCESAR ARCHIVOS (Imágenes/PDFs)
        if (!empty($_FILES['file'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $files = $_FILES['file']; // Puede ser múltiple o simple
            // Normalizar a array si es un solo archivo
            if (!is_array($files['name'])) {
                $files = [
                    'name' => [$files['name']],
                    'type' => [$files['type']],
                    'tmp_name' => [$files['tmp_name']],
                    'error' => [$files['error']],
                    'size' => [$files['size']]
                ];
            }

            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {
                    $file_array = [
                        'name'     => $files['name'][$key],
                        'type'     => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error'    => $files['error'][$key],
                        'size'     => $files['size'][$key]
                    ];
                    
                    // Subir archivo
                    $upload = wp_handle_upload($file_array, ['test_form' => false]);
                    if (!isset($upload['error']) && isset($upload['url'])) {
                        $uploaded_files_urls[] = $upload['url'];
                    }
                }
            }
        }

        // 2. PROCESAR AUDIO (Whisper)
        if (!empty($_FILES['audio'])) {
             require_once(ABSPATH . 'wp-admin/includes/file.php');
             $upload = wp_handle_upload($_FILES['audio'], ['test_form' => false]);
             if (!isset($upload['error']) && isset($upload['file'])) {
                 $audio_path = $upload['file'];
                 // API key desde wp-config.php
                 $api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
                 
                 $curl = curl_init();
                 curl_setopt_array($curl, [
                    CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => [
                        'file' => new CURLFile($audio_path),
                        'model' => 'whisper-1'
                    ],
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key],
                    CURLOPT_SSL_VERIFYPEER => false // WAMP Fix
                 ]);
                 $whisper_response = curl_exec($curl);
                 curl_close($curl);
                 
                 $whisper_json = json_decode($whisper_response, true);
                 if (!empty($whisper_json['text'])) {
                     $mensaje .= " " . $whisper_json['text']; // Concatenar o setear mensaje
                 }
             }
        }
        
        // Guardar mensaje de usuario en Historial Chat
        $wpdb->insert($this->tabla_chat_historial, [
            'session_id' => $session_id,
            'user_id' => $cliente->id, // Usamos ID cliente como user_id
            'role' => 'user', 
            'content' => $mensaje, 
            'archivos' => !empty($uploaded_files_urls) ? json_encode($uploaded_files_urls) : null,
            'created_at' => current_time('mysql')
        ]);

        // Construir contexto del cliente (Igual que antes)
        $proyectos = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tabla_proyectos} WHERE cliente_id = %d ORDER BY created_at DESC", $cliente_id));
        $historial = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tabla_historial} WHERE cliente_id = %d ORDER BY created_at DESC LIMIT 10", $cliente_id));
        
        // Contexto System Prompt
        $contexto = "Eres MAXTECH, el asistente virtual oficial de AutomatizaTech. Estás hablando con el cliente {$cliente->nombre}.\n";
        $contexto .= "Tu misión es asistir al cliente con información sobre SU cuenta y SUS proyectos, O dar información general sobre los servicios de AutomatizaTech. "
                  . "Si el cliente te envía IMÁGENES o ARCHIVOS, analízalos detalladamente y explica qué ves o responde a su consulta sobre ellos. "
                  . "Si te envía un audio, responde como si te estuviera hablando.\n"
                  . "IMPORTANTE: Si proporcionas enlaces web, por favor usa formato Markdown estándar: [Texto del Link](URL).\n"
                  . "ATENCIÓN OCR: Al leer documentos, es común que la letra 'I' (i mayúscula) se confunda con el número '1' o la letra 'l'. "
                  . "Si encuentras enlaces con IDs (ej: 'id=...'), sé extremadamente cuidadoso. Si el ID parece ser un hash alfanumérico, y ves un '1' que parece fuera de lugar (ej: rodeado de mayúsculas), considera que podría ser una 'I'. Trata de preservar la exactitud del enlace tal cual es funcional.\n";
        $contexto .= "\n--- DATOS DEL CLIENTE ---\n";
        $contexto .= "Empresa: {$cliente->empresa}\nEmail: {$cliente->email}\nEstado: {$cliente->estado}\n";

        // ... Drive integration ... (RAG con Google Drive)
        if (!empty($cliente->drive_folder_id) && class_exists('Google_Drive_OAuth')) {
            $drive = new Google_Drive_OAuth();
            $drive_response = $drive->list_files($cliente->drive_folder_id);
            
            if (!empty($drive_response) && isset($drive_response['files']) && is_array($drive_response['files'])) {
                $file_list = $drive_response['files'];
                $unique_files = array_slice($file_list, 0, 10); // Analizar últimos 10 archivos
                
                $contexto .= "\n--- ARCHIVOS EN GOOGLE DRIVE (RAG) ---\n";
                $archivos_texto = "";
                
                foreach ($unique_files as $f) {
                    $contexto .= "- [{$f['mimeType']}] {$f['name']} (Modificado: {$f['modifiedTime']})\n";
                    
                    // Si el nombre del archivo sugiere que es una boleta/factura/doc relevante, intentar leer contenido
                    // Agrego 'bol-', 'fac-' como prefijos comunes
                    $fname_lower = strtolower($f['name']);
                    
                    // Condición: Nombre relevante O usuario pide info de boletas
                    if (strpos($fname_lower, 'boleta') !== false || 
                        strpos($fname_lower, 'bol-') !== false || 
                        strpos($fname_lower, 'factura') !== false ||
                        strpos($fname_lower, 'informe') !== false ||
                        strpos($fname_lower, 'propuesta') !== false ||
                        strpos($fname_lower, 'demo') !== false ||
                        strpos($mensaje, 'boleta') !== false ||
                        strpos($mensaje, 'demo') !== false) { // Si el usuario PREGUNTA por boleta, forzamos lectura
                        
                        // Solo leer PDFs o Docs (ahorro de tokens y tiempo)
                        if ($f['mimeType'] == 'application/pdf') {
                             $pdf_text = $drive->convert_pdf_to_text($f['id']);
                             if ($pdf_text) {
                                 $archivos_texto .= "\n>>> CONTENIDO DE {$f['name']}:\n" . substr($pdf_text, 0, 3000) . "\n<<< FIN CONTENIDO\n";
                             }
                        } else if (strpos($f['mimeType'], 'google-apps.document') !== false || $f['mimeType'] == 'text/plain') {
                             $doc_content = $drive->get_file_content($f['id']);
                             if ($doc_content && !empty($doc_content['content'])) {
                                  $archivos_texto .= "\n>>> CONTENIDO DE {$f['name']}:\n" . substr($doc_content['content'], 0, 3000) . "\n<<< FIN CONTENIDO\n";
                             }
                        }
                    }
                }
                $contexto .= $archivos_texto;
                $contexto .= "\n(Usa esta información de archivos SOLO si es relevante para la consulta del usuario)\n";
            }
        }

        $contexto .= "\n--- SUS PROYECTOS ---\n";
        if (empty($proyectos)) {
            $contexto .= "No tiene proyectos activos.\n";
        } else {
            foreach ($proyectos as $p) {
                $contexto .= "- Proyecto: {$p->nombre} (Estado: {$p->estado}). Descripción: {$p->descripcion}.\n";
            }
        }
        
        $contexto .= "\n--- HISTORIAL DE INTERACCIONES (Emails/Notas) ---\n";
        if (empty($historial)) {
            $contexto .= "No hay interacciones previas registradas.\n";
        } else {
            foreach ($historial as $h) {
                // Asumiendo columnas: tipo, descripcion/detalle, created_at
                $tipo = $h->tipo ?? 'Info';
                $desc = $h->descripcion ?? ($h->mensaje ?? '');
                $fecha = $h->created_at ?? '';
                $contexto .= "- [{$fecha}] ({$tipo}): {$desc}\n";
            }
        }
        
        // Recuperar historial de CHAT reciente (memoria conversacional)
        $chat_memory = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content, archivos FROM {$this->tabla_chat_historial} WHERE session_id = %s ORDER BY created_at DESC LIMIT 6", // Últimos 3 turnos
            $session_id
        ));
        $chat_memory = array_reverse($chat_memory); // Cronológico

        // Construir mensajes para OpenAI
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $contexto];
        
        foreach ($chat_memory as $chat) {
            // Evitar enviar el mensaje actual duplicado si ya lo guardamos
            // (La query incluye el actual, así que cuidado. Mejor excluir el ultimo insert o manejar localmente)
            // Simplemente usaremos lo que hay en BD, pero el mensaje actual ya lo insertamos, así que está ahí.
            // Para evitar duplicados en el prompt, podríamos no insertar antes, pero queremos persistencia.
            // Asumiremos que el mensaje actual está en chat_memory.
            
            // Adaptar formato a OpenAI Vision si tiene archivos
            $role = ($chat->role == 'assistant') ? 'assistant' : 'user';
            $content = $chat->content;
            
            $msg_obj = ['role' => $role, 'content' => $content];
            
            if ($role === 'user' && !empty($chat->archivos)) {
                $files_arr = json_decode($chat->archivos, true);
                if (is_array($files_arr)) {
                    $content_parts = [['type' => 'text', 'text' => $content]];
                    foreach ($files_arr as $url_file) {
                        // Solo imágenes para GPT Vision. PDFs necesitan OCR previo o Assistant API (Retrieval).
                        // Por simplicidad, asumimos imágenes.
                        $ext = strtolower(pathinfo($url_file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                            $content_parts[] = [
                                'type' => 'image_url',
                                'image_url' => ['url' => $url_file]
                            ];
                        } else {
                            $content_parts[0]['text'] .= "\n[Se adjuntó un archivo no-imagen: $url_file]";
                        }
                    }
                    $msg_obj['content'] = $content_parts;
                }
            }
            $messages[] = $msg_obj;
        }

        // API key desde wp-config.php
        $api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

        $payload = [
            'model' => 'gpt-4o', // Usamos 4o para todo (visión y texto)
            'messages' => $messages,
            'max_tokens' => 800
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for WAMP/Localhost SSL issues
        
        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            wp_send_json_success(['respuesta' => "Error técnico (Conexión): " . $curl_error]); 
        }
        
        $json = json_decode($result, true);
        
        if (isset($json['error'])) {
             $api_error = $json['error']['message'] ?? 'Unknown API Error';
             wp_send_json_success(['respuesta' => "Error técnico (OpenAI): " . $api_error]);
        }
        
        $respuesta_texto = $json['choices'][0]['message']['content'] ?? 'Lo siento, tuve un problema técnico (Respuesta vacía). Intenta de nuevo.';
        
        // --- LOGICA TTS (Text to Speech) ---
        $audio_url_response = null;
        if ($tts_requested) {
            $ch_tts = curl_init('https://api.openai.com/v1/audio/speech');
            $tts_payload = [
                'model' => 'tts-1',
                'input' => substr($respuesta_texto, 0, 4000), // Límite TTS
                'voice' => 'alloy'
            ];
            curl_setopt($ch_tts, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_tts, CURLOPT_POST, true);
            curl_setopt($ch_tts, CURLOPT_POSTFIELDS, json_encode($tts_payload));
            curl_setopt($ch_tts, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ]);
            curl_setopt($ch_tts, CURLOPT_SSL_VERIFYPEER, false); // Fix for WAMP

            $audio_data = curl_exec($ch_tts);
            if (curl_getinfo($ch_tts, CURLINFO_HTTP_CODE) == 200) {
                 $upload_dir = wp_upload_dir();
                 $filename = 'maxtech_tts_' . md5(uniqid()) . '.mp3';
                 file_put_contents($upload_dir['path'] . '/' . $filename, $audio_data);
                 $audio_url_response = $upload_dir['url'] . '/' . $filename;
            }
            curl_close($ch_tts);
        }

        // Guardar respuesta asistente
        $wpdb->insert($this->tabla_chat_historial, [
            'session_id' => $session_id,
            'user_id' => $cliente->id,
            'role' => 'assistant',
            'content' => $respuesta_texto,
            'audio_url' => $audio_url_response, // columna 'audio_url' creada en setup? Si no existe, no importa tanto, se envía en JSON
            'created_at' => current_time('mysql')
        ]);

        wp_send_json_success([
            'respuesta' => $respuesta_texto,
            'audio_url' => $audio_url_response
        ]);
    }
    
    // ========== AJAX: GUARDAR NOTA ==========
    public function ajax_guardar_nota() {
        check_ajax_referer('crm_nonce', 'nonce');
        
        global $wpdb;
        $cliente_id = intval($_POST['cliente_id']);
        $nota = sanitize_textarea_field($_POST['nota']);
        $fecha = !empty($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : current_time('mysql');
        
        $wpdb->insert($this->tabla_historial, [
            'cliente_id' => $cliente_id,
            'tipo_evento' => 'nota',
            'titulo' => 'Nota agregada',
            'descripcion' => $nota,
            'usuario_id' => get_current_user_id(),
            'created_at' => $fecha
        ]);
        
        wp_send_json_success();
    }

    public function ajax_crear_proyecto() {
        check_ajax_referer('crm_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos.');
        }

        global $wpdb;
        $cliente_id = intval($_POST['cliente_id']);
        if (!$cliente_id) {
            wp_send_json_error('ID de cliente no válido.');
        }

        // Sanitize and validate
        $nombre = sanitize_text_field($_POST['nombre']);
        $descripcion = sanitize_textarea_field($_POST['descripcion']);
        $tipo_servicio = sanitize_text_field($_POST['tipo_servicio']);
        $precio_acordado = floatval($_POST['precio_acordado']);
        $fecha_inicio = sanitize_text_field($_POST['fecha_inicio']);
        $fecha_entrega = sanitize_text_field($_POST['fecha_entrega']);
        $notificar_cliente = isset($_POST['notificar_cliente']) && $_POST['notificar_cliente'] === 'true';

        if (empty($nombre) || empty($descripcion)) {
            wp_send_json_error('El nombre y la descripción son obligatorios.');
        }
        
        $data = [
            'cliente_id' => $cliente_id,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_servicio' => $tipo_servicio,
            'precio_acordado' => $precio_acordado,
            'fecha_inicio' => empty($fecha_inicio) ? null : $fecha_inicio,
            'fecha_entrega' => empty($fecha_entrega) ? null : $fecha_entrega,
            'estado' => 'pendiente'
        ];
        
        $result = $wpdb->insert($this->tabla_proyectos, $data);

        if ($result === false) {
            wp_send_json_error('Error al guardar el proyecto en la base de datos.');
        }

        $proyecto_id = $wpdb->insert_id;

        // Add to history
        $wpdb->insert($this->tabla_historial, [
            'cliente_id' => $cliente_id,
            'tipo_evento' => 'proyecto_creado',
            'titulo' => 'Nuevo Proyecto: ' . $nombre,
            'descripcion' => 'Se ha creado un nuevo proyecto.',
            'usuario_id' => get_current_user_id()
        ]);

        if ($notificar_cliente) {
            $cliente = $wpdb->get_row($wpdb->prepare("SELECT email, nombre FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));
            if ($cliente && !empty($cliente->email)) {
                $asunto = "Nuevo Proyecto Iniciado: " . $nombre;
                
                // Historial y Link
                $historial_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tabla_historial} WHERE cliente_id = %d ORDER BY created_at DESC LIMIT 5", $cliente_id));
                $historial_html = '<ul style="padding-left:20px; color:#555;">';
                foreach ($historial_items as $h) {
                    $historial_html .= '<li style="margin-bottom:5px;"><strong>' . date('d/m/Y', strtotime($h->created_at)) . ':</strong> ' . esc_html($h->titulo) . '</li>';
                }
                $historial_html .= '</ul>';
                
                $token = $this->_generar_token($cliente_id, $cliente->email);
                $link_timeline = home_url('/?crm_view=timeline&cid=' . $cliente_id . '&token=' . $token);
                
                // Plantilla HTML Nuevo Proyecto
                $cuerpo = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Nuevo Proyecto Creado - AutomatizaTech</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f0f0f0; color: #222; }
    .container { background: #fff; max-width: 600px; margin: 40px auto; border-radius: 10px; box-shadow: 0 2px 8px #0001; overflow: hidden; }
    .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: #fff; text-align: center; padding: 32px 20px 20px 20px; }
    .header img { max-width: 140px; margin-bottom: 10px; }
    .content { padding: 32px 24px; }
    .info-box { background: #f8f9fa; border-left: 4px solid #06d6a0; padding: 18px 20px; border-radius: 8px; margin-bottom: 18px; }
    .history-box { background: #fff; border: 1px solid #eee; padding: 15px; border-radius: 8px; margin-top: 20px; }
    .cta { display: inline-block; background: #06d6a0; color: #fff; padding: 12px 32px; border-radius: 25px; text-decoration: none; font-weight: bold; margin: 24px 0; }
    .footer { background: #f8f9fa; color: #6c757d; text-align: center; font-size: 0.95em; padding: 18px 10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="AutomatizaTech Logo">
      <h1>¡Tu proyecto ha sido creado!</h1>
      <p>AutomatizaTech te acompaña en cada paso 🚀</p>
    </div>
    <div class="content">
      <p>Hola <strong>' . esc_html($cliente->nombre) . '</strong>,</p>
      <div class="info-box">
        <b>Proyecto:</b> ' . esc_html($nombre) . '<br>
        <b>Descripción:</b> ' . nl2br(esc_html($descripcion)) . '<br>
        <b>Tipo:</b> ' . esc_html($tipo_servicio) . '<br>
        <b>Inicio:</b> ' . ($fecha_inicio ? date('d/m/Y', strtotime($fecha_inicio)) : 'Pendiente') . '<br>
        <b>Entrega estimada:</b> ' . ($fecha_entrega ? date('d/m/Y', strtotime($fecha_entrega)) : 'Por definir') . '<br>
        <b>Estado inicial:</b> Pendiente
      </div>
      
      <div class="history-box">
        <h3 style="margin-top:0; color:#1e3a8a;">📜 Historial Reciente</h3>
        ' . $historial_html . '
      </div>

      <p>
        Nuestro equipo comenzará a trabajar contigo para lograr los objetivos de este proyecto.<br>
        Si tienes dudas o necesitas información adicional, estamos a tu disposición.
      </p>
      <p style="background:#eef2ff; padding:10px; border-radius:5px; border-left: 4px solid #6366f1;">
         🤖 <strong>Tip:</strong> Tu asistente virtual <strong>MAXTECH</strong> ya está activo en tu portal. Puedes preguntarle cualquier detalle sobre el estado de tu proyecto 24/7.
      </p>
      <p style="text-align: center;">
        <a class="cta" href="' . esc_url($link_timeline) . '">Ver Proyecto y Timeline Completo</a>
      </p>
    </div>
    <div class="footer">
      Correo enviado automáticamente por <b>AutomatizaTech</b> &mdash; <a href="https://automatizatech.cl/" style="color:#1e3a8a;">automatizatech.cl</a>
    </div>
  </div>
</body>
</html>';
                
                $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
                $headers = array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: Automatiza Tech <' . $from_email . '>',
                    'Bcc: lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl'
                );
                wp_mail($cliente->email, $asunto, $cuerpo, $headers);
            }
        }

        wp_send_json_success(['message' => 'Proyecto creado con éxito.', 'proyecto_id' => $proyecto_id]);
    }

    public function ajax_update_timeline_item() {
        check_ajax_referer('crm_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos.');
        }

        global $wpdb;
        $id = intval($_POST['item_id']);
        $source = sanitize_text_field($_POST['source']);
        
        $title = sanitize_text_field($_POST['title']);
        $description = isset($_POST['description']) ? sanitize_textarea_field( $_POST['description'] ) : ''; // Preserve newlines
        $date = sanitize_text_field($_POST['date']);
        $amount = floatval($_POST['amount']);

        if (!$id || !$source) {
            wp_send_json_error('Datos inválidos.');
        }

        $table = '';
        $data = [];
        $where = ['id' => $id];

        // Normalización de fecha para que sea válida en MySQL/MariaDB
        $date_formatted = date('Y-m-d H:i:s', strtotime($date));

        if ($source === 'client') {
            $table = $wpdb->prefix . 'automatiza_clients_details';
            $data = [
                'title' => $title,
                'description' => $description,
                'completed_date' => $date_formatted, // Usamos completed_date como fecha principal editable
                'amount' => $amount
            ];
        } elseif ($source === 'prospect') {
            $table = $wpdb->prefix . 'automatiza_propuestas_details';
             $data = [
                'title' => $title,
                'description' => $description,
                'completed_date' => $date_formatted,
                'amount' => $amount
            ];
        } elseif ($source === 'system') {
             // Legacy
             $table = $this->tabla_historial;
             $data = [
                'titulo' => $title,
                'descripcion' => $description,
                'created_at' => $date_formatted
            ];
        }

        if ($table) {
            $result = $wpdb->update($table, $data, $where);
            if ($result !== false) {
                wp_send_json_success('Elemento actualizado.');
            } else {
                wp_send_json_error('Error al actualizar en BD: ' . $wpdb->last_error);
            }
        } else {
            wp_send_json_error('Fuente de datos desconocida.');
        }
    }

    /**
     * AJAX: Agendar reunión de seguimiento desde la ficha de cliente
     * Reutiliza la tabla followup_meetings y las funciones de email/calendar del módulo de prospectos
     */
    public function ajax_agendar_seguimiento() {
        check_ajax_referer('crm_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos.');
        }

        global $wpdb;
        
        $cliente_id = intval($_POST['cliente_id']);
        $client_name = sanitize_text_field($_POST['client_name']);
        $client_email = sanitize_email($_POST['client_email']);
        $company_name = sanitize_text_field($_POST['company_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $meeting_date = sanitize_text_field($_POST['meeting_date']);
        $meeting_time = sanitize_text_field($_POST['meeting_time']);
        $meeting_subject = sanitize_text_field($_POST['meeting_subject'] ?? 'Reunión de Seguimiento');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $create_calendar = isset($_POST['create_calendar_event']) && $_POST['create_calendar_event'] === '1';
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';
        $send_whatsapp = isset($_POST['send_whatsapp']) && $_POST['send_whatsapp'] === '1';

        // Validaciones
        if (empty($client_name) || empty($client_email) || empty($meeting_date) || empty($meeting_time)) {
            wp_send_json_error('Por favor completa todos los campos obligatorios.');
        }
        if (!is_email($client_email)) {
            wp_send_json_error('El correo electrónico no es válido.');
        }

        $table_name = $wpdb->prefix . 'automatiza_followup_meetings';

        // Verificar disponibilidad usando la función del módulo de prospectos si existe
        if (function_exists('automatiza_tech_check_slot_availability')) {
            $availability = automatiza_tech_check_slot_availability($meeting_date, $meeting_time);
            if (!$availability['available']) {
                $conflict_type = $availability['conflict_type'] === 'demo' ? 'DEMO' : 'Reunión de Seguimiento';
                wp_send_json_error('Horario no disponible: Ya existe una ' . $conflict_type . ' programada. ' . ($availability['conflict_details'] ?? ''));
            }
        }

        // Insertar reunión en la tabla de followup_meetings
        $data = [
            'client_name' => $client_name,
            'client_email' => $client_email,
            'company_name' => $company_name,
            'phone' => $phone,
            'meeting_date' => $meeting_date,
            'meeting_time' => $meeting_time,
            'meet_link' => '',
            'meeting_subject' => $meeting_subject ?: 'Reunión de Seguimiento',
            'notes' => $notes,
            'status' => 'scheduled'
        ];

        $inserted = $wpdb->insert($table_name, $data);
        if (!$inserted) {
            wp_send_json_error('Error al guardar en base de datos: ' . $wpdb->last_error);
        }

        $meeting_id = $wpdb->insert_id;
        $messages = ['✅ Reunión agendada con éxito.'];

        // Crear evento en Google Calendar (reutilizar función del módulo prospectos)
        $n8n_result = null;
        $n8n_email_sent = false;
        $n8n_wa_sent = false;
        if ($create_calendar && function_exists('automatiza_tech_create_followup_calendar_event')) {
            $n8n_result = automatiza_tech_create_followup_calendar_event($meeting_id);
            if ($n8n_result && $n8n_result['success']) {
                if (!empty($n8n_result['meet_link'])) {
                    $wpdb->update($table_name, ['meet_link' => $n8n_result['meet_link']], ['id' => $meeting_id]);
                }
                if (!empty($n8n_result['event_id'])) {
                    $wpdb->update($table_name, ['google_event_id' => $n8n_result['event_id']], ['id' => $meeting_id]);
                }
                $messages[] = '📅 Evento creado en Google Calendar.';
                if (!empty($n8n_result['meet_link'])) {
                    $messages[] = '🔗 Link Meet generado automáticamente.';
                }
                // El workflow principal de N8N puede haber enviado email y/o WhatsApp
                $n8n_email_sent = !empty($n8n_result['email_sent']);
                $n8n_wa_sent = !empty($n8n_result['whatsapp_sent']);
                if ($n8n_email_sent) {
                    $wpdb->update($table_name, ['email_sent' => 1], ['id' => $meeting_id]);
                    error_log("Followup #{$meeting_id}: Email ya enviado por N8N workflow principal");
                }
                if ($n8n_wa_sent) {
                    $wpdb->update($table_name, ['whatsapp_sent' => 1], ['id' => $meeting_id]);
                    error_log("Followup #{$meeting_id}: WhatsApp ya enviado por N8N workflow principal");
                }
            } else {
                $messages[] = '⚠️ No se pudo crear el evento en calendario.';
            }
        }

        // Enviar correo — solo si N8N no lo envió ya
        if ($send_email && !$n8n_email_sent && function_exists('automatiza_tech_send_followup_email')) {
            $email_sent = automatiza_tech_send_followup_email($meeting_id);
            if ($email_sent) {
                $wpdb->update($table_name, ['email_sent' => 1], ['id' => $meeting_id]);
                $messages[] = '📧 Correo de invitación enviado.';
            } else {
                $messages[] = '⚠️ Error al enviar el correo.';
            }
        } elseif ($n8n_email_sent) {
            $messages[] = '📧 Correo enviado por N8N.';
        }

        // Enviar WhatsApp — solo si N8N no lo envió ya
        if ($send_whatsapp && !$n8n_wa_sent && !empty($phone) && function_exists('automatiza_tech_send_followup_whatsapp')) {
            $wa_sent = automatiza_tech_send_followup_whatsapp($meeting_id);
            if ($wa_sent) {
                $messages[] = '💬 WhatsApp enviado al cliente.';
            } else {
                $messages[] = '⚠️ No se pudo enviar el WhatsApp (webhook dedicado).';
                error_log("Followup #{$meeting_id}: Falló envío WhatsApp por webhook dedicado. Revisar N8N workflow Followup_WhatsApp_Send.");
            }
        } elseif ($n8n_wa_sent) {
            $messages[] = '💬 WhatsApp enviado por N8N.';
        } elseif ($send_whatsapp && empty($phone)) {
            $messages[] = '⚠️ WhatsApp no enviado: el cliente no tiene teléfono.';
        }

        // Registrar en el timeline del CRM
        $formatted_date = date('d/m/Y', strtotime($meeting_date));
        $formatted_time = substr($meeting_time, 0, 5);
        $current_user = wp_get_current_user();
        
        $wpdb->insert($this->tabla_historial, [
            'cliente_id' => $cliente_id,
            'tipo_evento' => 'reunion',
            'titulo' => '📅 Reunión de Seguimiento Agendada',
            'descripcion' => "Se agendó reunión de seguimiento:\n- Fecha: {$formatted_date}\n- Hora: {$formatted_time} hrs\n- Asunto: {$meeting_subject}\n- Notas: {$notes}",
            'usuario_id' => $current_user->ID,
            'metadata' => json_encode([
                'followup_meeting_id' => $meeting_id,
                'meeting_date' => $meeting_date,
                'meeting_time' => $meeting_time,
                'tipo' => 'seguimiento_cliente'
            ]),
            'created_at' => current_time('mysql')
        ]);

        wp_send_json_success([
            'message' => implode(' ', $messages),
            'meeting_id' => $meeting_id
        ]);
    }

    public function ajax_eliminar_seguimiento() {
        check_ajax_referer('crm_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos.');
        }

        global $wpdb;
        $meeting_id = intval($_POST['meeting_id']);
        if (!$meeting_id) {
            wp_send_json_error('ID de reunión no válido.');
        }

        $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
        $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
        if (!$meeting) {
            wp_send_json_error('Reunión no encontrada.');
        }

        // Eliminar evento de Google Calendar si existe
        if (!empty($meeting->google_event_id) && function_exists('automatiza_tech_delete_google_calendar_event')) {
            automatiza_tech_delete_google_calendar_event($meeting->google_event_id);
        }

        // Eliminar de la base de datos
        $deleted = $wpdb->delete($table_name, ['id' => $meeting_id]);
        if ($deleted) {
            wp_send_json_success(['message' => '🗑️ Reunión de seguimiento eliminada correctamente.']);
        } else {
            wp_send_json_error('Error al eliminar la reunión.');
        }
    }

    public function ajax_actualizar_proyecto() {
        check_ajax_referer('crm_nonce', 'nonce');
        // Allow editors (Adriana) to update if needed, checking edit_posts
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('No tienes permisos.');
        }

        global $wpdb;
        $proyecto_id = intval($_POST['proyecto_id']);
        $cliente_id = intval($_POST['cliente_id']);

        if (!$proyecto_id || !$cliente_id) {
            wp_send_json_error('IDs no válidos.');
        }

        // Sanitize
        $estado = sanitize_text_field($_POST['estado']);
        $nombre_proyecto = isset($_POST['nombre_proyecto']) ? sanitize_text_field($_POST['nombre_proyecto']) : '';
        $nota_actualizacion = sanitize_textarea_field($_POST['nota_actualizacion']);
        
        // Fix para checkbox: acepta 'true', 'on' o '1'
        $notificar_val = isset($_POST['notificar_actualizacion']) ? $_POST['notificar_actualizacion'] : 'false';
        $notificar_cliente = ($notificar_val === 'true' || $notificar_val === 'on' || $notificar_val === '1');

        // Check previous status for email logic
        $prev_project = $wpdb->get_row($wpdb->prepare("SELECT estado FROM {$this->tabla_proyectos} WHERE id = %d", $proyecto_id));
        $was_completed = ($prev_project && $prev_project->estado === 'completado');

        // Manejo de Evidencias (Imágenes)
        $evidencias_links = [];
        if (!empty($_FILES['evidencia']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $files = $_FILES['evidencia'];
            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {
                    $file = array(
                        'name'     => $files['name'][$key],
                        'type'     => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error'    => $files['error'][$key],
                        'size'     => $files['size'][$key]
                    );
                    
                    // Truco para media_handle_upload
                    $_FILES['upload_file'] = $file;
                    $attachment_id = media_handle_upload('upload_file', 0);
                    
                    if (!is_wp_error($attachment_id)) {
                        $evidencias_links[] = wp_get_attachment_url($attachment_id);
                    }
                }
            }
        }

        // Update estado y nombre
        $update_data = ['estado' => $estado];
        if (!empty($nombre_proyecto)) {
            $update_data['nombre'] = $nombre_proyecto;
        }

        $wpdb->update(
            $this->tabla_proyectos,
            $update_data,
            ['id' => $proyecto_id]
        );

        $proyecto = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_proyectos} WHERE id = %d", $proyecto_id));

        if (!empty($nota_actualizacion) || !empty($evidencias_links)) {
            // Append note
            $nueva_nota = "";
            if ($nota_actualizacion) $nueva_nota .= "\n[" . current_time('mysql') . "] " . $nota_actualizacion;
            if ($evidencias_links) $nueva_nota .= "\n[EVIDENCIA]: " . implode(", ", $evidencias_links);

            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->tabla_proyectos} SET notas = CONCAT(IFNULL(notas, ''), %s) WHERE id = %d",
                $nueva_nota,
                $proyecto_id
            ));

            // Add to history
            $desc_historial = "Nuevo estado: " . ucfirst($estado);
            if ($nota_actualizacion) $desc_historial .= ". Nota: " . $nota_actualizacion;
            if ($evidencias_links) $desc_historial .= ". (" . count($evidencias_links) . " archivos adjuntos)";

            $wpdb->insert($this->tabla_historial, [
                'cliente_id' => $cliente_id,
                'tipo_evento' => 'proyecto_update',
                'titulo' => 'Actualización de Proyecto: ' . ($nombre_proyecto ? $nombre_proyecto : $proyecto->nombre),
                'descripcion' => $desc_historial,
                'metadata' => json_encode([
                    'evidencia' => $evidencias_links,
                    'notificado' => $notificar_cliente,
                    'notificado_at' => $notificar_cliente ? current_time('mysql') : null,
                    'proyecto_id' => $proyecto_id
                ]),
                'usuario_id' => get_current_user_id()
            ]);
        }

        if ($notificar_cliente) {
            $cliente = $wpdb->get_row($wpdb->prepare("SELECT email, nombre FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));
            if ($cliente && !empty($cliente->email)) {
                
                // --- Variables Comunes ---
                $token = $this->_generar_token($cliente_id, $cliente->email);
                $link_timeline = home_url('/?crm_view=timeline&cid=' . $cliente_id . '&token=' . $token);
                
                // Historial para email
                $historial_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tabla_historial} WHERE cliente_id = %d ORDER BY created_at DESC LIMIT 5", $cliente_id));
                $historial_html = '<ul style="padding-left:20px; color:#555;">';
                foreach ($historial_items as $h) {
                    $historial_html .= '<li style="margin-bottom:5px;"><strong>' . date('d/m/Y', strtotime($h->created_at)) . ':</strong> ' . esc_html($h->titulo) . '</li>';
                }
                $historial_html .= '</ul>';

                // Construir bloque de nota opcional
                $bloque_nota = '';
                if (!empty($nota_actualizacion) || !empty($evidencias_links)) {
                    $bloque_nota = '<div class="note-box">
                        <b>Nota de esta actualización:</b><br>';
                    if ($nota_actualizacion) $bloque_nota .= nl2br(esc_html($nota_actualizacion)) . '<br><br>';
                    if ($evidencias_links) {
                        $bloque_nota .= '<b>📸 Evidencias:</b><br>';
                        foreach ($evidencias_links as $link) {
                            $bloque_nota .= '<a href="' . esc_url($link) . '" target="_blank">Ver archivo adjunto</a><br>';
                        }
                    }
                    $bloque_nota .= '</div>';
                }

                // --- LOGICA DIFERENCIADA: ACTUALIZACION vs COMPLETADO ---
                // Solo enviar email de "Finalizado" si el estado es nuevo 'completado' y ANTES no lo era.
                // Si ya era completado, enviamos actualización normal.
                $is_new_completion = ($estado === 'completado' && !$was_completed);
                
                if ($is_new_completion) {
                    
                    // === EMAIL: PROYECTO COMPLETADO ===
                    $asunto = "¡Proyecto Finalizado! 🎉 " . $proyecto->nombre;
                    
                    $cuerpo = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Proyecto Finalizado - AutomatizaTech</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f0f0f0; color: #222; }
    .container { background: #fff; max-width: 600px; margin: 40px auto; border-radius: 10px; box-shadow: 0 2px 8px #0001; overflow: hidden; }
    .header { background: linear-gradient(135deg, #059669, #34d399); color: #fff; text-align: center; padding: 32px 20px 20px 20px; }
    .header img { max-width: 140px; margin-bottom: 10px; }
    .content { padding: 32px 24px; }
    .info-box { background: #ecfdf5; border-left: 4px solid #059669; padding: 18px 20px; border-radius: 8px; margin-bottom: 18px; }
    .note-box { background: #e3f2fd; border-left: 4px solid #06d6a0; padding: 16px 18px; border-radius: 8px; margin-bottom: 18px; }
    .cta { display: inline-block; background: #059669; color: #fff; padding: 12px 32px; border-radius: 25px; text-decoration: none; font-weight: bold; margin: 24px 0; }
    .footer { background: #f8f9fa; color: #6c757d; text-align: center; font-size: 0.95em; padding: 18px 10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="AutomatizaTech Logo">
      <h1>¡Proyecto Finalizado Exitosamente!</h1>
      <p>Un placer haber trabajado contigo 🚀</p>
    </div>
    <div class="content">
      <p>Hola <strong>' . esc_html($cliente->nombre) . '</strong>,</p>
      
      <p>Estamos felices de informarte que tu proyecto ha sido marcado como <strong>COMPLETADO</strong>.</p>

      <div class="info-box">
        <b>Proyecto:</b> ' . esc_html($proyecto->nombre) . '<br>
        <b>Fecha de Finalización:</b> ' . date("d/m/Y") . '
      </div>
      ' . $bloque_nota . '
      
      <p>
        Ha sido un gusto colaborar en este proceso. El historial completo del proyecto y todas las evidencias están disponibles en tu timeline.
      </p>

      <p style="text-align: center;">
        <a class="cta" href="' . esc_url($link_timeline) . '">Ver Resultado Final</a>
      </p>
      
      <p style="background:#eef2ff; padding:10px; border-radius:5px; border-left: 4px solid #6366f1;">
        🤖 Recuerda que <strong>MAXTECH</strong> tiene todos los detalles finales y documentos en tu portal, listo para responder cualquier consulta sobre el cierre de este proyecto.
      </p>

      <p>
        ¡Gracias por confiar en AutomatizaTech para tus soluciones digitales!
      </p>
    </div>
    <div class="footer">
      © ' . date('Y') . ' AutomatizaTech. Todos los derechos reservados.<br>
      Correo enviado automáticamente por <b>AutomatizaTech</b> &mdash; <a href="https://automatizatech.cl/" style="color:#1e3a8a;">automatizatech.cl</a>
    </div>
  </div>
</body>
</html>';
                
                } else {
                    
                    // === EMAIL: ACTUALIZACION REGULAR ===
                    $asunto = "Actualización de tu Proyecto: " . $proyecto->nombre;
                    
                    $cuerpo = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Actualización de Proyecto - AutomatizaTech</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f0f0f0; color: #222; }
    .container { background: #fff; max-width: 600px; margin: 40px auto; border-radius: 10px; box-shadow: 0 2px 8px #0001; overflow: hidden; }
    .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: #fff; text-align: center; padding: 32px 20px 20px 20px; }
    .header img { max-width: 140px; margin-bottom: 10px; }
    .content { padding: 32px 24px; }
    .info-box { background: #f8f9fa; border-left: 4px solid #1e3a8a; padding: 18px 20px; border-radius: 8px; margin-bottom: 18px; }
    .note-box { background: #e3f2fd; border-left: 4px solid #06d6a0; padding: 16px 18px; border-radius: 8px; margin-bottom: 18px; }
    .history-box { background: #fff; border: 1px solid #eee; padding: 15px; border-radius: 8px; margin-top: 20px; }
    .cta { display: inline-block; background: #06d6a0; color: #fff; padding: 12px 32px; border-radius: 25px; text-decoration: none; font-weight: bold; margin: 24px 0; }
    .footer { background: #f8f9fa; color: #6c757d; text-align: center; font-size: 0.95em; padding: 18px 10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="AutomatizaTech Logo">
      <h1>Actualización de tu proyecto</h1>
      <p>¡Seguimos avanzando juntos!</p>
    </div>
    <div class="content">
      <p>Hola <strong>' . esc_html($cliente->nombre) . '</strong>,</p>
      <div class="info-box">
        <b>Proyecto:</b> ' . esc_html($proyecto->nombre) . '
      </div>
      ' . $bloque_nota . '
      
      <div class="history-box">
        <h3 style="margin-top:0; color:#1e3a8a;">📜 Historial de Cambios</h3>
        ' . $historial_html . '
      </div>
      
      <p>
        Si tienes preguntas o necesitas más información, estamos a tu disposición.<br>
        🤖 También puedes consultar detalles específicos de este avance con <strong>MAXTECH</strong>, tu asistente inteligente en el portal.
      </p>
      <p style="text-align: center;">
        <a class="cta" href="' . esc_url($link_timeline) . '">Ver Proyecto y Timeline Completo</a>
      </p>
    </div>
    <div class="footer">
      © ' . date('Y') . ' AutomatizaTech. Todos los derechos reservados.<br>
      Correo enviado automáticamente por <b>AutomatizaTech</b> &mdash; <a href="https://automatizatech.cl/" style="color:#1e3a8a;">automatizatech.cl</a>
    </div>
  </div>
</body>
</html>';
                }

                $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
                $headers = array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: Automatiza Tech <' . $from_email . '>',
                    'Bcc: lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl'
                );
                wp_mail($cliente->email, $asunto, $cuerpo, $headers);
            }
        }

        wp_send_json_success([
            'message' => 'Proyecto actualizado con éxito.' . ($notificar_cliente ? ' 📧 Cliente notificado.' : ''),
            'notificado' => $notificar_cliente
        ]);
    }
    
    private function _enviar_correo_bienvenida($cliente_id) {
        global $wpdb;

        if (!$cliente_id) {
            return;
        }

        $cliente = $wpdb->get_row($wpdb->prepare("SELECT nombre, email FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));

        if (!$cliente || empty($cliente->email)) {
            return; // Abort if no client or no email
        }

        $to = $cliente->email;
        $subject = '¡Bienvenido a Automatiza.tech!';
        
        // Generar enlace al timeline público
        $token = $this->_generar_token($cliente_id, $cliente->email);
        $link_timeline = home_url('/?crm_view=timeline&cid=' . $cliente_id . '&token=' . $token);
        
        // Obtener nombre del proyecto (si existe alguno reciente, o genérico)
        $proyecto_reciente = $wpdb->get_row($wpdb->prepare("SELECT nombre FROM {$this->tabla_proyectos} WHERE cliente_id = %d ORDER BY created_at DESC LIMIT 1", $cliente_id));
        $nombre_proyecto = $proyecto_reciente ? $proyecto_reciente->nombre : 'Transformación Digital';

        // Plantilla HTML de Bienvenida (integrada)
        $body = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>¡Bienvenido a AutomatizaTech!</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f0f0f0; color: #222; }
    .container { background: #fff; max-width: 600px; margin: 40px auto; border-radius: 10px; box-shadow: 0 2px 8px #0001; overflow: hidden; }
    .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: #fff; text-align: center; padding: 32px 20px 20px 20px; }
    .header img { max-width: 140px; margin-bottom: 10px; }
    .content { padding: 32px 24px; }
    .cta { display: inline-block; background: #06d6a0; color: #fff; padding: 12px 32px; border-radius: 25px; text-decoration: none; font-weight: bold; margin: 24px 0; }
    .footer { background: #f8f9fa; color: #6c757d; text-align: center; font-size: 0.95em; padding: 18px 10px; }
    .project-box { background: #e3f2fd; border-left: 4px solid #1e3a8a; padding: 15px; margin: 15px 0; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="AutomatizaTech Logo">
      <h1>¡Bienvenido a AutomatizaTech! 🚀</h1>
      <p>¡Felicitaciones por dar el primer paso hacia la automatización!</p>
    </div>
    <div class="content">
      <p>Hola <strong>' . esc_html($cliente->nombre) . '</strong>,</p>
      <div class="project-box">
        <b>Proyecto:</b> ' . esc_html($nombre_proyecto) . '
      </div>
      <p>
        Nos alegra darte la bienvenida a la familia <b>AutomatizaTech</b>.<br>
        A partir de hoy, cuentas con el apoyo de nuestro equipo y nuestras herramientas para llevar tu empresa al siguiente nivel.
      </p>
      <p>
        Para ver todo el historial de interacciones, estado de tu cuenta y detalles de tus proyectos, haz clic en el siguiente enlace:
      </p>
      <p style="text-align: center;">
        <a class="cta" href="' . esc_url($link_timeline) . '">Ver Historial y Timeline</a>
      </p>
      <p style="background:#eef2ff; padding:15px; border-radius:5px; border-left: 4px solid #6366f1;">
        🤖 <strong>¡Conoce a MAXTECH!</strong><br>
        En tu portal encontrarás a <strong>MAXTECH</strong>, tu asistente personal con Inteligencia Artificial. Él conoce todos los detalles de tu proyecto y puede responder tus dudas, mostrarte documentos o darte información al instante, 24/7.
      </p>
      <p>
        Si tienes dudas o necesitas ayuda personalizada, <b>estamos para apoyarte</b>.<br>
        ¡Gracias por confiar en nosotros!
      </p>
    </div>
        <div class="footer">
      © ' . date('Y') . ' AutomatizaTech. Todos los derechos reservados.<br>
      Correo enviado automáticamente por <b>AutomatizaTech</b> &mdash; <a href="https://automatizatech.cl/" style="color:#1e3a8a;">automatizatech.cl</a>
    </div>
  </div>
</body>
</html>';
        
        // Configurar remitente seguro
        $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Automatiza Tech <' . $from_email . '>',
            'Bcc: lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl'
        );

        // LOGGING PARA DEBUG
        error_log("CRM DEBUG: Intentando enviar correo bienvenida a: " . $to);

        $enviado = wp_mail($to, $subject, $body, $headers);
        
        if ($enviado) {
            error_log("CRM DEBUG: Correo enviado EXITOSAMENTE a: " . $to);
        } else {
            error_log("CRM DEBUG: FALLO envio de correo a: " . $to . ". Verifica configuración SMTP.");
        }
        
        // Add to history
        $wpdb->insert($this->tabla_historial, [
            'cliente_id' => $cliente_id,
            'tipo_evento' => 'email_bienvenida',
            'titulo' => 'Correo de bienvenida enviado',
            'descripcion' => $enviado ? 'Se envió el correo de bienvenida al cliente.' : 'Error al intentar enviar el correo de bienvenida.',
            'usuario_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ]);
    }
    
    // ========== ESTILOS ==========
    private function render_styles() {
        ?>
        <style>
        /* ========== FICHA TABS ========== */
        .ficha-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
            border-bottom: 2px solid #e1e1e1;
            background: #f6f7f7;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }
        .ficha-tab {
            padding: 10px 18px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #50575e;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ficha-tab:hover { background: #eaecef; color: #1d2327; }
        .ficha-tab.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
            background: #fff;
        }
        .ficha-tab-badge {
            background: #2271b1;
            color: #fff;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: 700;
            line-height: 1.4;
        }
        .ficha-tab-content {
            display: none;
        }
        .ficha-tab-content.active {
            display: block;
        }
        .ficha-tab-content > .ficha-card:first-child {
            border-radius: 0 0 8px 8px;
            margin-top: 0;
        }
        
        .crm-wrap { max-width: 1400px; }
        .crm-filters { background: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .crm-filters form { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .crm-filters label { font-weight: 600; }
        .crm-filters select { padding: 5px 10px; }
        
        .crm-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 20px; }
        .kpi-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; border-left: 4px solid #0073aa; }
        .kpi-card.green { border-left-color: #46b450; }
        .kpi-card.red { border-left-color: #dc3232; }
        .kpi-card.total { border-left-color: #826eb4; }
        .kpi-icon { font-size: 32px; margin-right: 15px; }
        .kpi-value { font-size: 24px; font-weight: bold; }
        .kpi-label { color: #666; font-size: 12px; }
        
        .crm-charts { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
        .chart-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .chart-box.large { grid-column: span 1; }
        .chart-box h3 { margin-top: 0; }
        
        .crm-table-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .crm-table-box h3 { margin-top: 0; }
        
        /* Ficha cliente */
        .ficha-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .ficha-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .ficha-card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .ficha-card.highlight { border-left: 4px solid #46b450; }
        .ficha-card.full-width { grid-column: span 2; }
        
        .metricas-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; text-align: center; }
        .metrica { padding: 15px; background: #f9f9f9; border-radius: 8px; }
        .metrica.green { background: #e8f5e9; }
        .metrica-valor { font-size: 24px; font-weight: bold; }
        .metrica-label { font-size: 12px; color: #666; }
        
        .cliente-badge { font-size: 12px; padding: 3px 10px; border-radius: 20px; margin-left: 10px; }
        .cliente-badge.cliente { background: #46b450; color: #fff; }
        .cliente-badge.prospecto { background: #00a0d2; color: #fff; }
        
        .timeline { border-left: 3px solid #0073aa; padding-left: 20px; margin: 20px 0; }
        .timeline-item { margin-bottom: 15px; position: relative; }
        .timeline-item:before { content: ''; position: absolute; left: -26px; top: 5px; width: 10px; height: 10px; background: #0073aa; border-radius: 50%; }
        .timeline-date { font-size: 12px; color: #666; }
        
        /* Timeline Tabs */
        .timeline-tabs { display: flex; gap: 0; border-bottom: 2px solid #e2e8f0; margin: 15px 0 0 0; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .tl-tab { padding: 10px 18px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 500; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .tl-tab:hover { color: #0d9488; background: #f0fdfa; }
        .tl-tab.active { color: #0d9488; border-bottom-color: #0d9488; font-weight: 600; }
        .tl-tab-count { background: #e2e8f0; color: #475569; padding: 1px 7px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .tl-tab.active .tl-tab-count { background: #ccfbf1; color: #0d9488; }
        .tl-tab-content { padding-top: 15px; }
        
        /* Timeline scrollable area */
        .timeline::-webkit-scrollbar { width: 6px; }
        .timeline::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
        .timeline::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .timeline::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .proyecto-item {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        .proyecto-item h4 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 16px;
        }
        .proyecto-campos-actualizacion {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 15px;
            align-items: center;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .proyecto-acciones {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .estado-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #eee; }
        .estado-badge.completado { background: #46b450; color: #fff; }
        .estado-badge.en_progreso { background: #00a0d2; color: #fff; }
        
        /* ========== RESPONSIVE STYLES ========== */
        
        /* Tablets (768px - 1024px) */
        @media screen and (max-width: 1024px) {
            .crm-kpis { grid-template-columns: repeat(3, 1fr); }
            .crm-charts { grid-template-columns: 1fr; }
            .chart-box.large { grid-column: span 1; }
            .ficha-grid { grid-template-columns: 1fr; }
            .ficha-card.full-width { grid-column: span 1; }
            .metricas-grid { grid-template-columns: repeat(2, 1fr); }
            .proyecto-campos-actualizacion { grid-template-columns: 1fr; }
        }
        
        /* Mobile Large (481px - 767px) */
        @media screen and (max-width: 767px) {
            .crm-wrap { padding: 10px; }
            .crm-wrap h1 { font-size: 20px; }
            
            /* Ficha Tabs responsive */
            .ficha-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .ficha-tabs::-webkit-scrollbar { display: none; }
            .ficha-tab {
                padding: 8px 12px;
                font-size: 12px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .ficha-tab-badge { font-size: 9px; padding: 1px 5px; }
            
            .tl-tab { padding: 8px 12px; font-size: 12px; }
            .tl-tab-count { font-size: 10px; padding: 1px 5px; }
            .timeline-tabs { gap: 0; scrollbar-width: none; }
            .timeline-tabs::-webkit-scrollbar { display: none; }
            
            .crm-filters { padding: 10px 15px; }
            .crm-filters form { flex-direction: column; align-items: stretch; gap: 10px; }
            .crm-filters label { display: block; margin-bottom: 3px; }
            .crm-filters select { width: 100%; padding: 10px; font-size: 16px; }
            .crm-filters .button { width: 100%; padding: 12px; text-align: center; }
            
            .crm-kpis { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .kpi-card { padding: 15px 12px; flex-direction: column; text-align: center; border-left: none; border-top: 4px solid #0073aa; }
            .kpi-card.green { border-top-color: #46b450; border-left: none; }
            .kpi-card.red { border-top-color: #dc3232; border-left: none; }
            .kpi-card.total { border-top-color: #826eb4; border-left: none; }
            .kpi-icon { font-size: 28px; margin: 0 0 8px 0; }
            .kpi-value { font-size: 20px; }
            .kpi-label { font-size: 11px; }
            
            .crm-charts { gap: 15px; }
            .chart-box { padding: 15px; }
            .chart-box h3 { font-size: 14px; margin-bottom: 15px; }
            
            .crm-table-box { padding: 10px; overflow-x: auto; }
            .crm-table-box h3 { font-size: 16px; }
            .crm-table-box table { font-size: 12px; min-width: 600px; }
            .crm-table-box th, .crm-table-box td { padding: 8px 5px; }
            .crm-table-box .button-small { display: block; margin: 2px 0; width: 100%; text-align: center; }
            
            .metricas-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .metrica { padding: 10px; }
            .metrica-valor { font-size: 18px; }
            .metrica-label { font-size: 10px; }
            
            .ficha-card { padding: 15px; }
            .ficha-card .form-table th,
            .ficha-card .form-table td {
                display: block;
                width: 100%;
                padding: 4px 0;
            }
            .ficha-card .form-table th { padding-top: 10px; font-size: 13px; }
            .ficha-card .regular-text,
            .ficha-card .large-text,
            .ficha-card select { width: 100% !important; max-width: 100%; box-sizing: border-box; font-size: 16px; }
            
            .proyecto-item { padding: 12px; }
            .proyecto-item h4 { font-size: 14px; }
            .proyecto-campos-actualizacion { grid-template-columns: 1fr; gap: 10px; }
            .proyecto-acciones { flex-direction: column; gap: 8px; }
            .proyecto-acciones .button { width: 100%; text-align: center; }
        }
        
        /* Mobile Small (hasta 480px) */
        @media screen and (max-width: 480px) {
            .crm-wrap { padding: 5px; margin-left: -10px; }
            .crm-wrap h1 { font-size: 18px; margin-bottom: 15px; }
            
            .ficha-tab { padding: 7px 10px; font-size: 11px; gap: 4px; }
            .ficha-tab-badge { font-size: 8px; padding: 0 4px; }
            
            .crm-kpis { grid-template-columns: 1fr 1fr; }
            .kpi-card:last-child { grid-column: span 2; }
            .kpi-value { font-size: 18px; }
            
            .chart-box { padding: 10px; }
            .chart-box canvas { max-height: 200px; }
            
            .crm-table-box table { min-width: 500px; font-size: 11px; }
            .crm-table-box .button-small { display: block; margin: 2px 0; width: 100%; text-align: center; }
            
            .ficha-card { padding: 12px; }
            .ficha-card h3 { font-size: 14px; }
            
            .metricas-grid { grid-template-columns: 1fr 1fr; }
            .metrica-valor { font-size: 16px; }
        }
        
        /* Touch improvements for mobile */
        @media (hover: none) and (pointer: coarse) {
            .crm-filters select,
            .crm-filters .button,
            .crm-table-box .button-small,
            .ficha-tab,
            .tl-tab {
                min-height: 44px;
                line-height: 44px;
            }
            .crm-filters select { line-height: normal; }
            .ficha-tab, .tl-tab { line-height: normal; display: flex; align-items: center; }
        }
        
        /* Print styles */
        @media print {
            .crm-filters, .crm-table-box .button-small { display: none; }
            .crm-kpis, .crm-charts { page-break-inside: avoid; }
            .ficha-tabs { display: none; }
            .ficha-tab-content { display: block !important; }
        }
        </style>
        
        <?php
    }
    
    public function render_busqueda_avanzada() {
        global $wpdb;
        
        // Recoger filtros
        $filtro_cliente = isset($_GET['f_cliente']) ? sanitize_text_field($_GET['f_cliente']) : '';
        $filtro_modelo = isset($_GET['f_modelo']) ? sanitize_text_field($_GET['f_modelo']) : '';
        $filtro_tipo = isset($_GET['f_tipo']) ? sanitize_text_field($_GET['f_tipo']) : '';
        $fecha_desde = isset($_GET['f_desde']) ? sanitize_text_field($_GET['f_desde']) : '';
        $fecha_hasta = isset($_GET['f_hasta']) ? sanitize_text_field($_GET['f_hasta']) : '';
        $ordenar = isset($_GET['ordenar']) ? sanitize_text_field($_GET['ordenar']) : 'created_at';
        $direccion = isset($_GET['dir']) ? sanitize_text_field($_GET['dir']) : 'DESC';
        
        // Construir query
        $where = "1=1";
        if ($filtro_cliente) $where .= $wpdb->prepare(" AND client_identifier LIKE %s", "%{$filtro_cliente}%");
        if ($filtro_modelo) $where .= $wpdb->prepare(" AND model_used = %s", $filtro_modelo);
        if ($filtro_tipo == 'cliente') $where .= " AND client_identifier LIKE 'cliente_%'";
        if ($filtro_tipo == 'demo') $where .= " AND client_identifier LIKE 'demo_%'";
        if ($filtro_tipo == 'interno') $where .= " AND client_identifier LIKE 'interno_%'";
        if ($fecha_desde) $where .= $wpdb->prepare(" AND DATE(created_at) >= %s", $fecha_desde);
        if ($fecha_hasta) $where .= $wpdb->prepare(" AND DATE(created_at) <= %s", $fecha_hasta);
        
        $order = in_array($ordenar, ['created_at', 'total_tokens', 'cost_estimated', 'client_identifier']) ? $ordenar : 'created_at';
        $dir = $direccion == 'ASC' ? 'ASC' : 'DESC';
        
        // Obtener resultados
        $resultados = $wpdb->get_results("
            SELECT * FROM {$this->tabla_ai}
            WHERE $where
            ORDER BY $order $dir
            LIMIT 500
        ", ARRAY_A);
        
        // Stats de la búsqueda
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                COALESCE(SUM(total_tokens), 0) as tokens,
                COALESCE(SUM(cost_estimated), 0) as costo
            FROM {$this->tabla_ai}
            WHERE $where
        ", ARRAY_A);
        
        // Obtener opciones para filtros
        $modelos = $wpdb->get_col("SELECT DISTINCT model_used FROM {$this->tabla_ai}");
        $clientes_lista = $wpdb->get_col("SELECT DISTINCT client_identifier FROM {$this->tabla_ai} ORDER BY client_identifier");
        
        ?>
        <div class="wrap crm-wrap">
            <h1>🔍 Búsqueda Avanzada de Consumo AI</h1>
            
            <!-- Filtros -->
            <div class="busqueda-filtros">
                <form method="get">
                    <input type="hidden" name="page" value="automatiza-crm-busqueda">
                    
                    <div class="filtros-grid">
                        <div class="filtro-item">
                            <label>Cliente/Identifier:</label>
                            <input type="text" name="f_cliente" value="<?php echo esc_attr($filtro_cliente); ?>" placeholder="Buscar...">
                        </div>
                        
                        <div class="filtro-item">
                            <label>Tipo:</label>
                            <select name="f_tipo">
                                <option value="">Todos</option>
                                <option value="cliente" <?php selected($filtro_tipo, 'cliente'); ?>>👤 Clientes</option>
                                <option value="demo" <?php selected($filtro_tipo, 'demo'); ?>>🧪 Demos</option>
                                <option value="interno" <?php selected($filtro_tipo, 'interno'); ?>>🏠 Interno</option>
                            </select>
                        </div>
                        
                        <div class="filtro-item">
                            <label>Modelo:</label>
                            <select name="f_modelo">
                                <option value="">Todos</option>
                                <?php foreach ($modelos as $m): ?>
                                <option value="<?php echo esc_attr($m); ?>" <?php selected($filtro_modelo, $m); ?>><?php echo esc_html($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-item">
                            <label>Desde:</label>
                            <input type="date" name="f_desde" value="<?php echo esc_attr($fecha_desde); ?>">
                        </div>
                        
                        <div class="filtro-item">
                            <label>Hasta:</label>
                            <input type="date" name="f_hasta" value="<?php echo esc_attr($fecha_hasta); ?>">
                        </div>
                        
                        <div class="filtro-item">
                            <label>Ordenar por:</label>
                            <select name="ordenar">
                                <option value="created_at" <?php selected($ordenar, 'created_at'); ?>>Fecha</option>
                                <option value="total_tokens" <?php selected($ordenar, 'total_tokens'); ?>>Tokens</option>
                                <option value="cost_estimated" <?php selected($ordenar, 'cost_estimated'); ?>>Costo</option>
                                <option value="client_identifier" <?php selected($ordenar, 'client_identifier'); ?>>Cliente</option>
                            </select>
                        </div>
                        
                        <div class="filtro-item">
                            <label>Dirección:</label>
                            <select name="dir">
                                <option value="DESC" <?php selected($direccion, 'DESC'); ?>>↓ Mayor a menor</option>
                                <option value="ASC" <?php selected($direccion, 'ASC'); ?>>↑ Menor a mayor</option>
                            </select>
                        </div>
                        
                        <div class="filtro-item filtro-buttons">
                            <button type="submit" class="button button-primary">🔍 Buscar</button>
                            <a href="?page=automatiza-crm-busqueda" class="button">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Stats de la búsqueda -->
            <div class="busqueda-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
                    <span class="stat-label">Registros encontrados</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['tokens']); ?></span>
                    <span class="stat-label">Total tokens</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">$<?php echo number_format($stats['costo'], 4); ?></span>
                    <span class="stat-label">Costo total</span>
                </div>
                <div class="stat-item green">
                    <span class="stat-number">$<?php echo number_format($stats['costo'] * 1.3, 2); ?></span>
                    <span class="stat-label">A facturar (+30%)</span>
                </div>
            </div>
            
            <!-- Resultados -->
            <div class="crm-table-box">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Cliente/Identifier</th>
                            <th>Modelo</th>
                            <th>Prompt Tokens</th>
                            <th>Completion Tokens</th>
                            <th>Total Tokens</th>
                            <th>Costo</th>
                            <th>Endpoint</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $r): 
                            $tipo_class = '';
                            if (strpos($r['client_identifier'], 'cliente_') === 0) $tipo_class = 'row-cliente';
                            elseif (strpos($r['client_identifier'], 'demo_') === 0) $tipo_class = 'row-demo';
                            else $tipo_class = 'row-interno';
                        ?>
                        <tr class="<?php echo $tipo_class; ?>">
                            <td><?php echo date('d/m/Y H:i:s', strtotime($r['created_at'])); ?></td>
                            <td>
                                <a href="?page=automatiza-crm-ficha&ai_id=<?php echo urlencode($r['client_identifier']); ?>">
                                    <?php echo esc_html($r['client_identifier']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($r['model_used']); ?></td>
                            <td><?php echo number_format($r['prompt_tokens']); ?></td>
                            <td><?php echo number_format($r['completion_tokens']); ?></td>
                            <td><strong><?php echo number_format($r['total_tokens']); ?></strong></td>
                            <td>$<?php echo number_format($r['cost_estimated'], 5); ?></td>
                            <td><?php echo esc_html($r['request_endpoint']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Exportar -->
            <div class="exportar-section">
                <button class="button" onclick="exportarCSV()">📥 Exportar a CSV</button>
            </div>
        </div>
        
        <script>
        function exportarCSV() {
            var table = document.querySelector('.wp-list-table');
            var rows = table.querySelectorAll('tr');
            var csv = [];
            
            rows.forEach(function(row) {
                var cols = row.querySelectorAll('td, th');
                var rowData = [];
                cols.forEach(function(col) {
                    rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
                });
                csv.push(rowData.join(','));
            });
            
            var csvContent = csv.join('\n');
            var blob = new Blob([csvContent], { type: 'text/csv' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'consumo_ai_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
        }
        </script>
        
        <style>
        .busqueda-filtros { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .filtros-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; align-items: end; }
        .filtro-item label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 12px; }
        .filtro-item input, .filtro-item select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .filtro-buttons { display: flex; gap: 10px; }
        
        .busqueda-stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-item { background: #fff; padding: 15px 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
        .stat-item.green { background: #e8f5e9; border-left: 4px solid #46b450; }
        .stat-number { display: block; font-size: 24px; font-weight: bold; color: #0073aa; }
        .stat-item.green .stat-number { color: #46b450; }
        .stat-label { font-size: 12px; color: #666; }
        
        .row-cliente { border-left: 3px solid #46b450; }
        .row-demo { border-left: 3px solid #ffb900; }
        .row-interno { border-left: 3px solid #0073aa; }
        
        .exportar-section { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; }
        
        /* ========== RESPONSIVE - BÚSQUEDA AVANZADA ========== */
        @media screen and (max-width: 1024px) {
            .filtros-grid { grid-template-columns: repeat(3, 1fr); }
            .busqueda-stats { flex-wrap: wrap; }
            .stat-item { flex: 1 1 calc(50% - 10px); min-width: 120px; }
        }
        @media screen and (max-width: 767px) {
            .busqueda-filtros { padding: 15px; }
            .filtros-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .filtro-item input, .filtro-item select { font-size: 16px; padding: 10px; }
            .filtro-buttons { flex-direction: column; gap: 8px; }
            .filtro-buttons .button { width: 100%; padding: 12px; text-align: center; }
            
            .busqueda-stats { flex-direction: column; gap: 10px; }
            .stat-item { padding: 12px 15px; }
            .stat-number { font-size: 20px; }
            .stat-label { font-size: 11px; }
            
            .crm-table-box .wp-list-table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 700px; font-size: 12px; }
            
            .exportar-section { padding: 10px; }
            .exportar-section .button { width: 100%; padding: 12px; }
        }
        @media screen and (max-width: 480px) {
            .filtros-grid { grid-template-columns: 1fr; }
            .stat-number { font-size: 18px; }
        }
        @media (hover: none) and (pointer: coarse) {
            .filtro-item input, .filtro-item select, .filtro-buttons .button, .exportar-section .button { min-height: 48px; }
        }
        </style>
        
        <?php $this->render_styles();
    }
    
    public function render_consumo_ai() { $this->render_dashboard(); }

    public function crm_enviar_notificacion_historial() {
        check_admin_referer('crm_nonce', 'nonce');
        
        $historial_id = intval($_POST['historial_id']);
        $cliente_id = intval($_POST['cliente_id']);
        
        global $wpdb;

        // Obtener datos del historial item
        $historial = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tabla_historial} WHERE id = %d", $historial_id));
        if (!$historial) {
            wp_send_json_error('No se encontró el item del historial.');
        }

        // Obtener cliente para el email
        $cliente = $wpdb->get_row($wpdb->prepare("SELECT email, nombre FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));
        if (!$cliente || empty($cliente->email)) {
             wp_send_json_error('No se encontró el cliente o no tiene email.');
        }

        // Determinar proyecto ID si es posible
        $proyecto_id = 0;
        $meta = !empty($historial->metadata) ? json_decode($historial->metadata, true) : [];
        if (!empty($meta['proyecto_id'])) {
            $proyecto_id = $meta['proyecto_id'];
        }

        $nombre_proyecto = 'Proyecto';
        if ($proyecto_id) {
            $proyecto = $wpdb->get_row($wpdb->prepare("SELECT nombre FROM {$this->tabla_proyectos} WHERE id = %d", $proyecto_id));
            if ($proyecto) $nombre_proyecto = $proyecto->nombre;
        } else {
             if (preg_match('/Actualización de Proyecto:\s*(.+)/', $historial->titulo, $matches)) {
                 $nombre_proyecto = trim($matches[1]);
             }
        }

        $evidencias_links = [];
        if (!empty($meta['evidencia'])) {
            $evidencias_links = $meta['evidencia'];
        }
        if (!empty($historial->attachment_url)) {
            $evidencias_links[] = $historial->attachment_url;
        }

        $token = $this->_generar_token($cliente_id, $cliente->email);
        $link_timeline = home_url('/?crm_view=timeline&cid=' . $cliente_id . '&token=' . $token);

        $asunto = "Actualización de tu Proyecto: " . $nombre_proyecto;
        
        $bloque_nota = '';
        if (!empty($historial->descripcion)) {
            $bloque_nota = '<div class="note-box">
                <strong>📝 Nota de Actualización:</strong><br>
                '.nl2br($historial->descripcion).'
            </div>';
        }

        $cuerpo = '<!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
                .header { background: #0f172a; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; }
                .footer { font-size: 12px; color: #666; text-align: center; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
                .button { background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                .note-box { background: #f8fafc; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; font-size: 14px; }
                .evidence-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 6px; margin-top: 15px; }
                .evidence-item { margin-bottom: 5px; }
                .evidence-item a { color: #166534; text-decoration: none; font-weight: bold; }
                .evidence-item a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin:0;">🚀 Actualización de Proyecto</h2>
                </div>
                <div class="content">
                    <p>Hola <strong>'.$cliente->nombre.'</strong>,</p>
                    <p>Te informamos que hubo una actualización en tu proyecto <strong>'.$nombre_proyecto.'</strong>.</p>
                    
                    '.$bloque_nota.'

                    ' . (!empty($evidencias_links) ? '
                    <div class="evidence-box">
                        <strong>📎 Archivos Adjuntos / Evidencia:</strong><br>
                        ' . implode('<br>', array_map(function($link) {
                            return '<div class="evidence-item">📄 <a href="'.$link.'">Descargar Archivo</a></div>';
                        }, $evidencias_links)) . '
                    </div>' : '') . '

                    <p>Puedes ver el historial completo y descargar tus archivos en tu portal de cliente:</p>
                    <p style="text-align: center;">
                        <a href="'.$link_timeline.'" class="button">Ver Historial Completo</a>
                    </p>
                </div>
                <div class="footer">
                    <p>AutomatizaTech - Soluciones Tecnológicas</p>
                    <p><small>Este es un mensaje automático. Por favor no respondas a este correo.</small></p>
                </div>
            </div>
        </body>
        </html>';

        $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <' . $from_email . '>'
        );

        $headers[] = 'Bcc: lgonzalez@automatizatech.cl';
        $headers[] = 'Bcc: Adriana.perez@automatizatech.cl';

        $enviado = wp_mail($cliente->email, $asunto, $cuerpo, $headers);

        if ($enviado) {
            $meta['notificado'] = true;
            $meta['notificado_at'] = current_time('mysql');
            $wpdb->update($this->tabla_historial, ['metadata' => json_encode($meta)], ['id' => $historial_id]);
            wp_send_json_success('Notificación enviada correctamente');
        } else {
            wp_send_json_error('Error al enviar el correo.');
        }
    }
}

// Inicializar
new AutomatizaTech_CRM_AI();
