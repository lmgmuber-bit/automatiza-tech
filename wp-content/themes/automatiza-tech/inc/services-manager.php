<?php
/**
 * Sistema de Gestión de Servicios para Automatiza Tech
 * Maneja la administración completa de servicios con CRUD
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTechServicesManager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_services';
        
        // Hooks de WordPress
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_save_service', array($this, 'save_service'));
        add_action('wp_ajax_delete_service', array($this, 'delete_service'));
        add_action('wp_ajax_toggle_service_status', array($this, 'toggle_service_status'));
        add_action('wp_ajax_get_service_details', array($this, 'get_service_details'));
        add_action('wp_ajax_duplicate_service', array($this, 'duplicate_service'));
        
        // Crear la tabla si no existe
        add_action('after_setup_theme', array($this, 'create_table'));
    }
    
    /**
     * Crear tabla de servicios si no existe
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category varchar(50) DEFAULT 'pricing',
            price_usd decimal(10,2) DEFAULT 0.00,
            price_clp decimal(12,0) DEFAULT 0,
            description text,
            features text,
            icon varchar(100) DEFAULT 'fas fa-star',
            highlight tinyint(1) DEFAULT 0,
            button_text varchar(100) DEFAULT '',
            whatsapp_message text,
            status varchar(20) DEFAULT 'active',
            service_order int(11) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY status (status),
            KEY service_order (service_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Gestión de Servicios',
            'Servicios AT',
            'manage_options',
            'automatiza-services',
            array($this, 'admin_page'),
            'dashicons-admin-tools',
            25
        );
        
        add_submenu_page(
            'automatiza-services',
            'Agregar Servicio',
            'Agregar Nuevo',
            'manage_options',
            'automatiza-services-new',
            array($this, 'new_service_page')
        );
        
        add_submenu_page(
            'automatiza-services',
            'Editor Frontend',
            'Editor Frontend',
            'manage_options',
            'automatiza-services-frontend',
            array($this, 'frontend_editor_page')
        );
        
        add_submenu_page(
            'automatiza-services',
            'Editor Planes',
            'Editor Planes',
            'manage_options',
            'automatiza-services-plans',
            array($this, 'plans_editor_page')
        );
        
        add_submenu_page(
            'automatiza-services',
            'Configuración',
            'Configuración',
            'manage_options',
            'automatiza-services-config',
            array($this, 'config_page')
        );
    }
    
    /**
     * Enqueue scripts y estilos del admin
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'automatiza-services') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style('automatiza-services-admin', get_template_directory_uri() . '/assets/css/admin-services.css', array(), '1.0.0');
            
            // JavaScript personalizado
            wp_add_inline_script('jquery', $this->get_admin_js());
            
            // Localize script para AJAX
            wp_localize_script('jquery', 'automatiza_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('automatiza_services_nonce'),
                'confirm_delete' => '¿Estás seguro de que quieres eliminar este servicio?',
                'confirm_duplicate' => '¿Quieres duplicar este servicio?'
            ));
        }
    }
    
    /**
     * Página principal de administración
     */
    public function admin_page() {
        global $wpdb;
        
        // Obtener servicios
        $services = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY category, service_order, name ASC"
        );
        
        // Agrupar por categoría
        $services_by_category = array();
        foreach ($services as $service) {
            $services_by_category[$service->category][] = $service;
        }
        
        ?>
        <div class="wrap">
            <h1>Gestión de Servicios <a href="<?php echo admin_url('admin.php?page=automatiza-services-new'); ?>" class="page-title-action">Agregar Nuevo</a></h1>
            
            <div id="service-message" class="notice" style="display:none;"></div>
            
            <div class="services-admin-container">
                
                <!-- Estadísticas -->
                <div class="services-stats">
                    <div class="stat-box">
                        <h3><?php echo count($services); ?></h3>
                        <p>Total Servicios</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo count(array_filter($services, function($s) { return $s->status === 'active'; })); ?></h3>
                        <p>Activos</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo count(array_filter($services, function($s) { return $s->category === 'pricing'; })); ?></h3>
                        <p>Planes</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo count(array_filter($services, function($s) { return $s->category === 'features'; })); ?></h3>
                        <p>Beneficios</p>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="services-filters">
                    <select id="filter-category">
                        <option value="">Todas las categorías</option>
                        <option value="pricing">Planes (Pricing)</option>
                        <option value="features">Beneficios (Features)</option>
                        <option value="special">Especiales</option>
                    </select>
                    
                    <select id="filter-status">
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                    
                    <button type="button" class="button" onclick="clearFilters()">Limpiar Filtros</button>
                </div>
                
                <!-- Servicios por categoría -->
                <?php foreach ($services_by_category as $category => $category_services): ?>
                <div class="category-section" data-category="<?php echo esc_attr($category); ?>">
                    <h2 class="category-title">
                        <?php 
                        switch($category) {
                            case 'pricing': echo 'Planes de Precios'; break;
                            case 'features': echo 'Beneficios/Características'; break;
                            case 'special': echo 'Ofertas Especiales'; break;
                            default: echo ucfirst($category); break;
                        }
                        ?>
                        <span class="category-count">(<?php echo count($category_services); ?>)</span>
                    </h2>
                    
                    <div class="services-grid" id="services-<?php echo esc_attr($category); ?>">
                        <?php foreach ($category_services as $service): ?>
                        <div class="service-card" data-id="<?php echo $service->id; ?>" data-category="<?php echo esc_attr($service->category); ?>" data-status="<?php echo esc_attr($service->status); ?>">
                            <div class="service-header">
                                <h3>
                                    <i class="<?php echo esc_attr($service->icon); ?>"></i>
                                    <?php echo esc_html($service->name); ?>
                                </h3>
                                <div class="service-status-badge <?php echo $service->status; ?>">
                                    <?php echo $service->status === 'active' ? 'Activo' : 'Inactivo'; ?>
                                </div>
                            </div>
                            
                            <div class="service-meta">
                                <p><strong>Categoría:</strong> <?php echo esc_html($service->category); ?></p>
                                <?php if ($service->price_usd > 0): ?>
                                <p><strong>Precio:</strong> $<?php echo number_format($service->price_usd, 0); ?> USD / $<?php echo number_format($service->price_clp, 0, ',', '.'); ?> CLP</p>
                                <?php endif; ?>
                                <?php if ($service->highlight): ?>
                                <p><span class="highlight-badge">⭐ Destacado</span></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="service-description">
                                <p><?php echo esc_html(wp_trim_words($service->description, 15)); ?></p>
                            </div>
                            
                            <div class="service-actions">
                                <button type="button" class="button button-primary button-small" onclick="editService(<?php echo $service->id; ?>)">
                                    <span class="dashicons dashicons-edit"></span> Editar
                                </button>
                                <button type="button" class="button button-small" onclick="duplicateService(<?php echo $service->id; ?>)">
                                    <span class="dashicons dashicons-admin-page"></span> Duplicar
                                </button>
                                <button type="button" class="button button-small" onclick="toggleServiceStatus(<?php echo $service->id; ?>)">
                                    <span class="dashicons dashicons-<?php echo $service->status === 'active' ? 'hidden' : 'visibility'; ?>"></span>
                                    <?php echo $service->status === 'active' ? 'Desactivar' : 'Activar'; ?>
                                </button>
                                <button type="button" class="button button-link-delete button-small" onclick="deleteService(<?php echo $service->id; ?>)">
                                    <span class="dashicons dashicons-trash"></span> Eliminar
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($services)): ?>
                <div class="no-services">
                    <h3>No hay servicios configurados</h3>
                    <p>¡Comienza agregando tu primer servicio!</p>
                    <a href="<?php echo admin_url('admin.php?page=automatiza-services-new'); ?>" class="button button-primary">Agregar Primer Servicio</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Modal para editar servicio -->
        <div id="edit-service-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2>Editar Servicio</h2>
                <form id="edit-service-form">
                    <!-- El contenido se cargará dinámicamente -->
                </form>
            </div>
        </div>
        
        <style>
        .services-admin-container { margin-top: 20px; }
        
        .services-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            min-width: 120px;
        }
        
        .stat-box h3 {
            font-size: 2em;
            margin: 0;
            color: #0073aa;
        }
        
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        .services-filters {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .category-section {
            margin-bottom: 40px;
        }
        
        .category-title {
            color: #23282d;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .category-count {
            font-size: 0.8em;
            color: #666;
            font-weight: normal;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .service-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .service-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-color: #0073aa;
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .service-header h3 {
            margin: 0;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .service-status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .service-status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .service-status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .service-meta {
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        
        .service-meta p {
            margin: 5px 0;
            color: #666;
        }
        
        .highlight-badge {
            background: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
        }
        
        .service-description {
            margin-bottom: 15px;
            color: #555;
            line-height: 1.4;
        }
        
        .service-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .service-actions .button-small {
            padding: 4px 8px;
            font-size: 11px;
            height: auto;
            line-height: 1.4;
        }
        
        .no-services {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
        }
        
        .no-services h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 6px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: #000;
        }
        </style>
        <?php
    }
    
    /**
     * Página para agregar nuevo servicio
     */
    public function new_service_page() {
        ?>
        <div class="wrap">
            <h1>Agregar Nuevo Servicio</h1>
            
            <form method="post" id="new-service-form" class="service-form">
                <?php wp_nonce_field('automatiza_new_service', 'service_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="service_name">Nombre del Servicio *</label></th>
                            <td>
                                <input name="service_name" type="text" id="service_name" class="regular-text" required>
                                <p class="description">Nombre que aparecerá en el frontend</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="service_category">Categoría *</label></th>
                            <td>
                                <select name="service_category" id="service_category" required>
                                    <option value="">Seleccionar categoría</option>
                                    <option value="pricing">Planes de Precios</option>
                                    <option value="features">Beneficios/Características</option>
                                    <option value="special">Ofertas Especiales</option>
                                </select>
                                <p class="description">Determina dónde aparecerá en el sitio web</p>
                            </td>
                        </tr>
                        
                        <tr class="pricing-fields">
                            <th scope="row"><label for="price_usd">Precio USD</label></th>
                            <td>
                                <input name="price_usd" type="number" id="price_usd" class="small-text" step="0.01" min="0">
                                <p class="description">Precio en dólares (solo para planes)</p>
                            </td>
                        </tr>
                        
                        <tr class="pricing-fields">
                            <th scope="row"><label for="price_clp">Precio CLP</label></th>
                            <td>
                                <input name="price_clp" type="number" id="price_clp" class="regular-text" step="1" min="0">
                                <p class="description">Precio en pesos chilenos</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="service_description">Descripción</label></th>
                            <td>
                                <textarea name="service_description" id="service_description" rows="3" class="large-text"></textarea>
                                <p class="description">Descripción que aparecerá en el frontend</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="service_features">Características</label></th>
                            <td>
                                <textarea name="service_features" id="service_features" rows="5" class="large-text" placeholder='["Característica 1","Característica 2","Característica 3"]'></textarea>
                                <p class="description">Lista de características en formato JSON. Una por línea entre comillas y separadas por comas.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="service_icon">Icono</label></th>
                            <td>
                                <input name="service_icon" type="text" id="service_icon" value="fas fa-star" class="regular-text">
                                <p class="description">Clase CSS del icono FontAwesome (ej: fas fa-star)</p>
                            </td>
                        </tr>
                        
                        <tr class="pricing-fields">
                            <th scope="row"><label for="service_highlight">Destacado</label></th>
                            <td>
                                <input name="service_highlight" type="checkbox" id="service_highlight" value="1">
                                <label for="service_highlight">Marcar como plan destacado</label>
                            </td>
                        </tr>
                        
                        <tr class="pricing-fields">
                            <th scope="row"><label for="button_text">Texto del Botón</label></th>
                            <td>
                                <input name="button_text" type="text" id="button_text" class="regular-text" placeholder="Comenzar">
                                <p class="description">Texto que aparecerá en el botón</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="whatsapp_message">Mensaje WhatsApp</label></th>
                            <td>
                                <textarea name="whatsapp_message" id="whatsapp_message" rows="3" class="large-text"></textarea>
                                <p class="description">Mensaje predefinido para WhatsApp cuando hagan clic</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="service_order">Orden</label></th>
                            <td>
                                <input name="service_order" type="number" id="service_order" value="0" class="small-text">
                                <p class="description">Orden de aparición (0 = primero)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="service_status">Estado</label></th>
                            <td>
                                <select name="service_status" id="service_status">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button('Guardar Servicio', 'primary', 'save_service'); ?>
            </form>
        </div>
        
        <style>
        .service-form .form-table th {
            width: 200px;
        }
        
        .pricing-fields {
            display: none;
        }
        
        .pricing-fields.show {
            display: table-row;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Mostrar/ocultar campos según categoría
            $('#service_category').on('change', function() {
                var category = $(this).val();
                if (category === 'pricing' || category === 'special') {
                    $('.pricing-fields').addClass('show');
                } else {
                    $('.pricing-fields').removeClass('show');
                }
            });
            
            // Manejar envío del formulario
            $('#new-service-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'save_service');
                formData.append('nonce', '<?php echo wp_create_nonce('automatiza_services_nonce'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Servicio guardado exitosamente');
                            window.location.href = '<?php echo admin_url('admin.php?page=automatiza-services'); ?>';
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Página del Editor Frontend - Vista previa exacta
     */
    public function frontend_editor_page() {
        global $wpdb;
        
        // Obtener el servicio especial "Web + WhatsApp Business"
        $special_service = $wpdb->get_row(
            "SELECT * FROM {$this->table_name} WHERE category = 'special' AND status = 'active' ORDER BY service_order ASC LIMIT 1"
        );
        
        // Procesar guardado si se envió el formulario
        if (isset($_POST['save_frontend_service']) && wp_verify_nonce($_POST['frontend_nonce'], 'save_frontend_service')) {
            $this->save_frontend_service();
        }
        
        ?>
        <div class="wrap">
            <h1>Editor Frontend - Servicios Especializados</h1>
            <p class="description">Edita la sección "Nuestros Servicios Especializados" con vista previa en tiempo real del frontend.</p>
            
            <div class="frontend-editor-container">
                <div class="row">
                    <!-- Panel de Edición -->
                    <div class="col-md-6">
                        <div class="edit-panel">
                            <h2>Panel de Edición</h2>
                            
                            <form method="post" id="frontend-editor-form">
                                <?php wp_nonce_field('save_frontend_service', 'frontend_nonce'); ?>
                                <input type="hidden" name="service_id" value="<?php echo $special_service ? $special_service->id : ''; ?>">
                                
                                <table class="form-table">
                                    <tr>
                                        <th><label for="service_title">Título del Servicio</label></th>
                                        <td>
                                            <input type="text" id="service_title" name="service_title" 
                                                   value="<?php echo esc_attr($special_service ? $special_service->name : 'Web + WhatsApp Business'); ?>" 
                                                   class="regular-text" onchange="updatePreview()">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="service_subtitle">Subtítulo</label></th>
                                        <td>
                                            <input type="text" id="service_subtitle" name="service_subtitle" 
                                                   value="Para Emprendimientos" 
                                                   class="regular-text" onchange="updatePreview()">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="service_description">Descripción Principal</label></th>
                                        <td>
                                            <textarea id="service_description" name="service_description" rows="4" class="large-text" onchange="updatePreview()"><?php echo esc_textarea($special_service ? $special_service->description : 'Impulsa tu emprendimiento con una solución completa que incluye sitio web profesional y automatización de WhatsApp Business para generar más ventas y mejorar la atención al cliente.'); ?></textarea>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="service_price">Precio (USD)</label></th>
                                        <td>
                                            <input type="number" id="service_price" name="service_price" 
                                                   value="<?php echo esc_attr($special_service ? $special_service->price_usd : '299'); ?>" 
                                                   class="small-text" step="1" onchange="updatePreview()">
                                            <span class="description">USD/mes</span>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="setup_cost">Setup Inicial</label></th>
                                        <td>
                                            <input type="number" id="setup_cost" name="setup_cost" 
                                                   value="500" 
                                                   class="small-text" step="1" onchange="updatePreview()">
                                            <span class="description">USD</span>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="button_text">Texto del Botón</label></th>
                                        <td>
                                            <input type="text" id="button_text" name="button_text" 
                                                   value="<?php echo esc_attr($special_service ? $special_service->button_text : '¡Quiero mi Web + WhatsApp!'); ?>" 
                                                   class="regular-text" onchange="updatePreview()">
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="service_features">Características (una por línea)</label></th>
                                        <td>
                                            <textarea id="service_features" name="service_features" rows="8" class="large-text" onchange="updatePreview()"><?php 
                                            $features = $special_service && $special_service->features ? json_decode($special_service->features, true) : [
                                                'Sitio web responsivo',
                                                'Catálogo de productos', 
                                                'WhatsApp Business API',
                                                'Chat automatizado',
                                                'Botón de WhatsApp integrado',
                                                'Respuestas automáticas',
                                                'Horarios de atención',
                                                'Soporte técnico'
                                            ];
                                            echo implode("\n", $features);
                                            ?></textarea>
                                            <p class="description">Una característica por línea</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><label for="whatsapp_message">Mensaje WhatsApp</label></th>
                                        <td>
                                            <textarea id="whatsapp_message" name="whatsapp_message" rows="3" class="large-text"><?php echo esc_textarea($special_service ? $special_service->whatsapp_message : 'Hola, estoy interesado en el servicio Web + WhatsApp Business para mi emprendimiento. ¿Pueden darme más información?'); ?></textarea>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php submit_button('Guardar Cambios', 'primary', 'save_frontend_service'); ?>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Vista Previa -->
                    <div class="col-md-6">
                        <div class="preview-panel">
                            <h2>Vista Previa Frontend</h2>
                            <div class="frontend-preview" id="frontend-preview">
                                <!-- La vista previa se generará aquí -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .frontend-editor-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .row {
            display: flex;
            width: 100%;
        }
        
        .col-md-6 {
            flex: 1;
        }
        
        .edit-panel, .preview-panel {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .preview-panel {
            position: sticky;
            top: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .frontend-preview {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
            min-height: 500px;
        }
        
        /* Estilos de la vista previa para simular el frontend */
        .preview-title {
            color: #007cba;
            font-size: 2em;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .preview-service-card {
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            position: relative;
        }
        
        .preview-service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .preview-service-title {
            font-size: 1.5em;
            font-weight: bold;
            margin: 0;
        }
        
        .preview-service-subtitle {
            font-size: 1em;
            opacity: 0.9;
            margin: 5px 0 0 0;
        }
        
        .preview-icon {
            font-size: 3em;
            opacity: 0.8;
        }
        
        .preview-description {
            font-size: 1em;
            line-height: 1.6;
            margin-bottom: 20px;
            color: #555;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        
        .preview-content-grid {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .preview-features {
            flex: 1;
        }
        
        .preview-features h4 {
            color: #007cba;
            font-size: 1.3em;
            margin-bottom: 15px;
        }
        
        .preview-features-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .preview-features-list li {
            font-size: 0.9em;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .preview-features-list li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .preview-price-card {
            background: white;
            border: 3px solid #90ee90;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            min-width: 200px;
            position: relative;
        }
        
        .preview-offer-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #90ee90;
            color: #333;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .preview-price {
            font-size: 1.2em;
            color: #007cba;
            margin: 10px 0;
        }
        
        .preview-price-amount {
            font-size: 2.5em;
            font-weight: bold;
            color: #007cba;
        }
        
        .preview-setup {
            font-size: 0.9em;
            color: #666;
            margin: 10px 0;
        }
        
        .preview-button {
            background: #90ee90;
            color: #333;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin-top: 10px;
        }
        
        .preview-footer-note {
            font-size: 0.8em;
            color: #666;
            margin-top: 10px;
        }
        </style>
        
        <script>
        function updatePreview() {
            const title = document.getElementById('service_title').value;
            const subtitle = document.getElementById('service_subtitle').value;
            const description = document.getElementById('service_description').value;
            const price = document.getElementById('service_price').value;
            const setup = document.getElementById('setup_cost').value;
            const buttonText = document.getElementById('button_text').value;
            const features = document.getElementById('service_features').value.split('\n').filter(f => f.trim());
            
            let featuresHtml = '';
            features.forEach(feature => {
                if (feature.trim()) {
                    featuresHtml += `<li>${feature.trim()}</li>`;
                }
            });
            
            const previewHtml = `
                <div class="preview-title">Nuestros Servicios Especializados</div>
                
                <div class="preview-service-card">
                    <div class="preview-service-header">
                        <div>
                            <h3 class="preview-service-title">${title}</h3>
                            <p class="preview-service-subtitle">${subtitle}</p>
                        </div>
                        <div class="preview-icon">🏪</div>
                    </div>
                    
                    <div class="preview-content-grid">
                        <div class="preview-features">
                            <div class="preview-description">${description}</div>
                            
                            <h4>¿Qué incluye?</h4>
                            <ul class="preview-features-list">
                                ${featuresHtml}
                            </ul>
                        </div>
                        
                        <div class="preview-price-card">
                            <div class="preview-offer-badge">OFERTA ESPECIAL</div>
                            <div class="preview-price">
                                <div style="color: #007cba; font-weight: bold;">Precio Especial</div>
                                <div class="preview-price-amount">$${price}</div>
                                <div style="font-size: 0.9em; color: #666;">/mes</div>
                            </div>
                            <div class="preview-setup">Setup inicial: $${setup}</div>
                            <a href="#" class="preview-button">${buttonText}</a>
                            <div class="preview-footer-note">Sin permanencia</div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('frontend-preview').innerHTML = previewHtml;
        }
        
        // Actualizar vista previa al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });
        </script>
        <?php
    }
    
    /**
     * Página del Editor de Planes - Vista previa exacta
     */
    public function plans_editor_page() {
        global $wpdb;
        
        // Obtener todos los planes
        $plans = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC"
        );
        
        // Procesar guardado si se envió el formulario
        if (isset($_POST['save_plans']) && wp_verify_nonce($_POST['plans_nonce'], 'save_plans')) {
            $this->save_plans();
        }
        
        ?>
        <div class="wrap">
            <h1>Editor de Planes - Planes y Precios</h1>
            <p class="description">Edita la sección "Planes y Precios" con vista previa en tiempo real del frontend.</p>
            
            <div class="plans-editor-container">
                <div class="row">
                    <!-- Panel de Edición -->
                    <div class="col-md-4">
                        <div class="edit-panel">
                            <h2>Panel de Edición</h2>
                            
                            <form method="post" id="plans-editor-form">
                                <?php wp_nonce_field('save_plans', 'plans_nonce'); ?>
                                
                                <div class="plan-tabs">
                                    <?php foreach ($plans as $index => $plan): ?>
                                    <button type="button" class="plan-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
                                            onclick="showPlan(<?php echo $index; ?>)"><?php echo esc_html($plan->name); ?></button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php foreach ($plans as $index => $plan): 
                                    $features = json_decode($plan->features, true) ?: [];
                                ?>
                                <div class="plan-editor" id="plan-<?php echo $index; ?>" style="<?php echo $index === 0 ? 'display:block;' : 'display:none;'; ?>">
                                    <input type="hidden" name="plans[<?php echo $index; ?>][id]" value="<?php echo $plan->id; ?>">
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th><label>Nombre del Plan</label></th>
                                            <td>
                                                <input type="text" name="plans[<?php echo $index; ?>][name]" 
                                                       value="<?php echo esc_attr($plan->name); ?>" 
                                                       class="regular-text" onchange="updatePlansPreview()">
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Precio (USD)</label></th>
                                            <td>
                                                <input type="number" name="plans[<?php echo $index; ?>][price]" 
                                                       value="<?php echo esc_attr($plan->price_usd); ?>" 
                                                       class="small-text" step="1" onchange="updatePlansPreview()">
                                                <span class="description">USD/mes</span>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Descripción</label></th>
                                            <td>
                                                <textarea name="plans[<?php echo $index; ?>][description]" rows="3" 
                                                          class="large-text" onchange="updatePlansPreview()"><?php echo esc_textarea($plan->description); ?></textarea>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Características</label></th>
                                            <td>
                                                <textarea name="plans[<?php echo $index; ?>][features]" rows="8" 
                                                          class="large-text" onchange="updatePlansPreview()"><?php echo implode("\n", $features); ?></textarea>
                                                <p class="description">Una característica por línea</p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Plan Destacado</label></th>
                                            <td>
                                                <input type="checkbox" name="plans[<?php echo $index; ?>][highlight]" 
                                                       value="1" <?php checked($plan->highlight, 1); ?> onchange="updatePlansPreview()">
                                                <label>Marcar como "Más Popular"</label>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Texto del Botón</label></th>
                                            <td>
                                                <input type="text" name="plans[<?php echo $index; ?>][button_text]" 
                                                       value="<?php echo esc_attr($plan->button_text ?: 'Comenzar'); ?>" 
                                                       class="regular-text" onchange="updatePlansPreview()">
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Icono</label></th>
                                            <td>
                                                <input type="text" name="plans[<?php echo $index; ?>][icon]" 
                                                       value="<?php echo esc_attr($plan->icon); ?>" 
                                                       class="regular-text" onchange="updatePlansPreview()">
                                                <p class="description">Clase FontAwesome (ej: fas fa-star)</p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Color de Tarjeta</label></th>
                                            <td>
                                                <input type="color" name="plans[<?php echo $index; ?>][card_color]" 
                                                       value="<?php echo esc_attr($plan->card_color ?: '#007cba'); ?>" 
                                                       class="color-picker" onchange="updatePlansPreview()">
                                                <p class="description">Color de fondo de la tarjeta</p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Color del Botón</label></th>
                                            <td>
                                                <input type="color" name="plans[<?php echo $index; ?>][button_color]" 
                                                       value="<?php echo esc_attr($plan->button_color ?: '#28a745'); ?>" 
                                                       class="color-picker" onchange="updatePlansPreview()">
                                                <p class="description">Color de fondo del botón</p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th><label>Color de Texto</label></th>
                                            <td>
                                                <input type="color" name="plans[<?php echo $index; ?>][text_color]" 
                                                       value="<?php echo esc_attr($plan->text_color ?: '#ffffff'); ?>" 
                                                       class="color-picker" onchange="updatePlansPreview()">
                                                <p class="description">Color del texto en la tarjeta</p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php submit_button('Guardar Todos los Planes', 'primary', 'save_plans'); ?>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Vista Previa -->
                    <div class="col-md-8">
                        <div class="preview-panel">
                            <h2>Vista Previa Frontend</h2>
                            <div class="plans-preview" id="plans-preview">
                                <!-- La vista previa se generará aquí -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .plans-editor-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .col-md-4 {
            flex: 0 0 400px;
        }
        
        .col-md-8 {
            flex: 1;
        }
        
        .edit-panel, .preview-panel {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .preview-panel {
            position: sticky;
            top: 20px;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .plan-tabs {
            display: flex;
            margin-bottom: 20px;
            gap: 5px;
        }
        
        .plan-tab {
            padding: 8px 12px;
            border: 1px solid #ccd0d4;
            background: #f1f1f1;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
            font-size: 12px;
        }
        
        .plan-tab.active {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .plans-preview {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
            min-height: 600px;
        }
        
        /* Estilos de vista previa */
        .preview-plans-title {
            color: #333;
            font-size: 2em;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .preview-plans-subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        
        .preview-plans-grid {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .preview-plan-card {
            background: white;
            border: 2px solid transparent;
            border-radius: 15px;
            padding: 30px 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            min-width: 280px;
            flex: 1;
            max-width: 320px;
        }
        
        .preview-plan-card.highlighted {
            border-color: #007cba;
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .preview-plan-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #90ee90;
            color: #333;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .preview-plan-badge.popular {
            background: #007cba;
            color: white;
        }
        
        .preview-plan-icon {
            font-size: 3em;
            color: #007cba;
            margin-bottom: 15px;
        }
        
        .preview-plan-name {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        
        .preview-plan-price {
            margin-bottom: 20px;
        }
        
        .preview-plan-amount {
            font-size: 2.5em;
            font-weight: bold;
            color: #007cba;
        }
        
        .preview-plan-period {
            color: #666;
            font-size: 0.9em;
        }
        
        .preview-plan-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .preview-plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            text-align: left;
        }
        
        .preview-plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        
        .preview-plan-features li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .preview-plan-button {
            background: #007cba;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin-top: 15px;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        
        .preview-plan-button.highlighted {
            background: #28a745;
        }
        
        /* Color picker styles */
        .color-picker {
            width: 60px;
            height: 40px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .color-picker:hover {
            border-color: #0073aa;
        }
        </style>
        
        <script>
        let plansData = <?php echo json_encode($plans); ?>;
        
        function showPlan(index) {
            // Ocultar todos los editores
            document.querySelectorAll('.plan-editor').forEach(editor => {
                editor.style.display = 'none';
            });
            
            // Mostrar el editor seleccionado
            document.getElementById('plan-' + index).style.display = 'block';
            
            // Actualizar tabs
            document.querySelectorAll('.plan-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.plan-tab')[index].classList.add('active');
        }
        
        function updatePlansPreview() {
            const plans = [];
            
            // Recopilar datos de todos los planes
            for (let i = 0; i < plansData.length; i++) {
                const planData = {
                    name: document.querySelector(`input[name="plans[${i}][name]"]`)?.value || '',
                    price: document.querySelector(`input[name="plans[${i}][price]"]`)?.value || '0',
                    description: document.querySelector(`textarea[name="plans[${i}][description]"]`)?.value || '',
                    features: document.querySelector(`textarea[name="plans[${i}][features]"]`)?.value.split('\\n').filter(f => f.trim()) || [],
                    highlight: document.querySelector(`input[name="plans[${i}][highlight]"]`)?.checked || false,
                    button_text: document.querySelector(`input[name="plans[${i}][button_text]"]`)?.value || 'Comenzar',
                    icon: document.querySelector(`input[name="plans[${i}][icon]"]`)?.value || 'fas fa-star',
                    card_color: document.querySelector(`input[name="plans[${i}][card_color]"]`)?.value || '#007cba',
                    button_color: document.querySelector(`input[name="plans[${i}][button_color]"]`)?.value || '#28a745',
                    text_color: document.querySelector(`input[name="plans[${i}][text_color]"]`)?.value || '#ffffff'
                };
                plans.push(planData);
            }
            
            let plansHtml = '';
            plans.forEach((plan, index) => {
                const badgeText = plan.highlight ? 'MÁS POPULAR' : 'OFERTA ESPECIAL';
                const badgeClass = plan.highlight ? 'popular' : '';
                const cardClass = plan.highlight ? 'highlighted' : '';
                const buttonClass = plan.highlight ? 'highlighted' : '';
                
                let featuresHtml = '';
                plan.features.forEach(feature => {
                    if (feature.trim()) {
                        featuresHtml += `<li>${feature.trim()}</li>`;
                    }
                });
                
                plansHtml += `
                    <div class="preview-plan-card ${cardClass}" style="background: linear-gradient(135deg, ${plan.card_color}, ${plan.card_color}dd); color: ${plan.text_color};">
                        <div class="preview-plan-badge ${badgeClass}">${badgeText}</div>
                        <div class="preview-plan-icon" style="color: ${plan.text_color};">
                            <i class="${plan.icon}"></i>
                        </div>
                        <div class="preview-plan-name" style="color: ${plan.text_color};">${plan.name}</div>
                        <div class="preview-plan-price">
                            <div class="preview-plan-amount" style="color: ${plan.text_color};">$${plan.price}</div>
                            <div class="preview-plan-period" style="color: ${plan.text_color}99;">/mes</div>
                        </div>
                        <div class="preview-plan-description" style="color: ${plan.text_color}cc;">${plan.description}</div>
                        <ul class="preview-plan-features" style="color: ${plan.text_color};">
                            ${featuresHtml}
                        </ul>
                        <button class="preview-plan-button ${buttonClass}" style="background: ${plan.button_color}; color: white;">${plan.button_text}</button>
                    </div>
                `;
            });
            
            const previewHtml = `
                <div class="preview-plans-title">Planes y Precios</div>
                <div class="preview-plans-subtitle">Elige el plan que mejor se adapte a tu negocio</div>
                <div class="preview-plans-grid">
                    ${plansHtml}
                </div>
            `;
            
            document.getElementById('plans-preview').innerHTML = previewHtml;
        }
        
        // Actualizar vista previa al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updatePlansPreview();
        });
        </script>
        <?php
    }
    
    /**
     * Guardar datos de los planes
     */
    private function save_plans() {
        global $wpdb;
        
        if (!isset($_POST['plans']) || !is_array($_POST['plans'])) {
            return;
        }
        
        foreach ($_POST['plans'] as $plan_data) {
            $service_data = array(
                'name' => sanitize_text_field($plan_data['name']),
                'category' => 'pricing',
                'price_usd' => floatval($plan_data['price']),
                'description' => sanitize_textarea_field($plan_data['description']),
                'features' => json_encode(array_filter(array_map('trim', explode("\n", $plan_data['features'])))),
                'button_text' => sanitize_text_field($plan_data['button_text']),
                'highlight' => isset($plan_data['highlight']) ? 1 : 0,
                'icon' => sanitize_text_field($plan_data['icon']),
                'card_color' => sanitize_text_field($plan_data['card_color']),
                'button_color' => sanitize_text_field($plan_data['button_color']),
                'text_color' => sanitize_text_field($plan_data['text_color']),
                'status' => 'active'
            );
            
            if (!empty($plan_data['id'])) {
                $wpdb->update(
                    $this->table_name,
                    $service_data,
                    array('id' => intval($plan_data['id'])),
                    array('%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            }
        }
        
        echo '<div class="notice notice-success"><p>¡Planes guardados exitosamente!</p></div>';
    }
    
    /**
     * Guardar datos del editor frontend
     */
    private function save_frontend_service() {
        global $wpdb;
        
        $service_data = array(
            'name' => sanitize_text_field($_POST['service_title']),
            'category' => 'special',
            'price_usd' => floatval($_POST['service_price']),
            'description' => sanitize_textarea_field($_POST['service_description']),
            'features' => json_encode(array_filter(array_map('trim', explode("\n", $_POST['service_features'])))),
            'button_text' => sanitize_text_field($_POST['button_text']),
            'whatsapp_message' => sanitize_textarea_field($_POST['whatsapp_message']),
            'status' => 'active',
            'highlight' => 1,
            'icon' => 'fas fa-store'
        );
        
        if (!empty($_POST['service_id'])) {
            // Actualizar servicio existente
            $result = $wpdb->update(
                $this->table_name,
                $service_data,
                array('id' => intval($_POST['service_id'])),
                array('%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // Crear nuevo servicio
            $result = $wpdb->insert(
                $this->table_name,
                $service_data,
                array('%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );
        }
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>¡Servicio guardado exitosamente!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error al guardar el servicio.</p></div>';
        }
    }
    
    /**
     * Página de configuración
     */
    public function config_page() {
        ?>
        <div class="wrap">
            <h1>Configuración de Servicios</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('automatiza_services_config');
                do_settings_sections('automatiza_services_config');
                ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Tipo de Cambio USD a CLP</th>
                            <td>
                                <input name="usd_to_clp_rate" type="number" value="<?php echo esc_attr(get_option('usd_to_clp_rate', 800)); ?>" class="regular-text" step="0.01">
                                <p class="description">Tipo de cambio actual para convertir automáticamente precios</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">WhatsApp por Defecto</th>
                            <td>
                                <input name="default_whatsapp_number" type="text" value="<?php echo esc_attr(get_option('default_whatsapp_number', '+56912345678')); ?>" class="regular-text">
                                <p class="description">Número de WhatsApp por defecto (incluir código de país)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Mostrar Precios en CLP</th>
                            <td>
                                <input name="show_clp_prices" type="checkbox" value="1" <?php checked(1, get_option('show_clp_prices', 1)); ?>>
                                <label>Mostrar precios en pesos chilenos en el frontend</label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Obtener servicios por categoría
     */
    public function get_services_by_category($category = '', $status = 'active') {
        global $wpdb;
        
        $where_clause = "WHERE status = %s";
        $params = array($status);
        
        if (!empty($category)) {
            $where_clause .= " AND category = %s";
            $params[] = $category;
        }
        
        $sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY service_order ASC, name ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Obtener servicios activos para el frontend
     */
    public function get_active_services($category = '') {
        return $this->get_services_by_category($category, 'active');
    }
    
    /**
     * JavaScript para administración
     */
    private function get_admin_js() {
        return "
        // Funciones para gestión de servicios
        function editService(serviceId) {
            jQuery.ajax({
                url: automatiza_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_service_details',
                    service_id: serviceId,
                    nonce: automatiza_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showEditModal(response.data);
                    } else {
                        alert('Error al cargar el servicio');
                    }
                }
            });
        }
        
        function deleteService(serviceId) {
            if (confirm(automatiza_ajax.confirm_delete)) {
                jQuery.ajax({
                    url: automatiza_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'delete_service',
                        service_id: serviceId,
                        nonce: automatiza_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error al eliminar el servicio');
                        }
                    }
                });
            }
        }
        
        function toggleServiceStatus(serviceId) {
            jQuery.ajax({
                url: automatiza_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'toggle_service_status',
                    service_id: serviceId,
                    nonce: automatiza_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error al cambiar el estado');
                    }
                }
            });
        }
        
        function duplicateService(serviceId) {
            if (confirm(automatiza_ajax.confirm_duplicate)) {
                jQuery.ajax({
                    url: automatiza_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'duplicate_service',
                        service_id: serviceId,
                        nonce: automatiza_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error al duplicar el servicio');
                        }
                    }
                });
            }
        }
        
        function clearFilters() {
            jQuery('#filter-category, #filter-status').val('');
            filterServices();
        }
        
        function filterServices() {
            var category = jQuery('#filter-category').val();
            var status = jQuery('#filter-status').val();
            
            jQuery('.service-card').each(function() {
                var card = jQuery(this);
                var cardCategory = card.data('category');
                var cardStatus = card.data('status');
                
                var showCard = true;
                
                if (category && cardCategory !== category) {
                    showCard = false;
                }
                
                if (status && cardStatus !== status) {
                    showCard = false;
                }
                
                if (showCard) {
                    card.show();
                } else {
                    card.hide();
                }
            });
        }
        
        // Event listeners
        jQuery(document).ready(function($) {
            $('#filter-category, #filter-status').on('change', filterServices);
            
            // Cerrar modal
            $('.modal-close').on('click', function() {
                $('#edit-service-modal').hide();
            });
            
            // Cerrar modal al hacer clic fuera
            $('#edit-service-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        });
        
        function showEditModal(service) {
            var modal = jQuery('#edit-service-modal');
            var form = jQuery('#edit-service-form');
            
            // Construir formulario de edición
            var formHtml = generateEditForm(service);
            form.html(formHtml);
            
            modal.show();
        }
        
        function generateEditForm(service) {
            return '<input type=\"hidden\" name=\"service_id\" value=\"' + service.id + '\">' +
                   '<table class=\"form-table\">' +
                   '<tr><th>Nombre:</th><td><input type=\"text\" name=\"name\" value=\"' + service.name + '\" class=\"regular-text\" required></td></tr>' +
                   '<tr><th>Categoría:</th><td><select name=\"category\"><option value=\"pricing\"' + (service.category === 'pricing' ? ' selected' : '') + '>Planes</option><option value=\"features\"' + (service.category === 'features' ? ' selected' : '') + '>Beneficios</option><option value=\"special\"' + (service.category === 'special' ? ' selected' : '') + '>Especiales</option></select></td></tr>' +
                   '<tr><th>Precio USD:</th><td><input type=\"number\" name=\"price_usd\" value=\"' + service.price_usd + '\" step=\"0.01\"></td></tr>' +
                   '<tr><th>Precio CLP:</th><td><input type=\"number\" name=\"price_clp\" value=\"' + service.price_clp + '\"></td></tr>' +
                   '<tr><th>Descripción:</th><td><textarea name=\"description\" rows=\"3\" class=\"large-text\">' + service.description + '</textarea></td></tr>' +
                   '<tr><th>Icono:</th><td><input type=\"text\" name=\"icon\" value=\"' + service.icon + '\" class=\"regular-text\"></td></tr>' +
                   '<tr><th>Destacado:</th><td><input type=\"checkbox\" name=\"highlight\" value=\"1\"' + (service.highlight ? ' checked' : '') + '></td></tr>' +
                   '<tr><th>Texto Botón:</th><td><input type=\"text\" name=\"button_text\" value=\"' + service.button_text + '\"></td></tr>' +
                   '<tr><th>Estado:</th><td><select name=\"status\"><option value=\"active\"' + (service.status === 'active' ? ' selected' : '') + '>Activo</option><option value=\"inactive\"' + (service.status === 'inactive' ? ' selected' : '') + '>Inactivo</option></select></td></tr>' +
                   '</table>' +
                   '<p class=\"submit\"><button type=\"button\" class=\"button button-primary\" onclick=\"saveService()\">Guardar Cambios</button></p>';
        }
        
        function saveService() {
            var formData = new FormData(document.getElementById('edit-service-form'));
            formData.append('action', 'save_service');
            formData.append('nonce', automatiza_ajax.nonce);
            
            jQuery.ajax({
                url: automatiza_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error al guardar: ' + response.data.message);
                    }
                }
            });
        }
        ";
    }
    
    /**
     * AJAX: Guardar servicio
     */
    public function save_service() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Asegurar headers UTF-8
        header('Content-Type: application/json; charset=utf-8');
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'automatiza_services_nonce')) {
            wp_die('Error de seguridad');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        global $wpdb;
        
        // Sanitizar datos
        $service_data = array(
            'name' => sanitize_text_field($_POST['service_name'] ?? $_POST['name']),
            'category' => sanitize_text_field($_POST['service_category'] ?? $_POST['category']),
            'price_usd' => floatval($_POST['price_usd'] ?? 0),
            'price_clp' => intval($_POST['price_clp'] ?? 0),
            'description' => sanitize_textarea_field($_POST['service_description'] ?? $_POST['description']),
            'features' => sanitize_textarea_field($_POST['service_features'] ?? $_POST['features']),
            'icon' => sanitize_text_field($_POST['service_icon'] ?? $_POST['icon']),
            'highlight' => isset($_POST['service_highlight']) || isset($_POST['highlight']) ? 1 : 0,
            'button_text' => sanitize_text_field($_POST['button_text'] ?? ''),
            'whatsapp_message' => sanitize_textarea_field($_POST['whatsapp_message'] ?? ''),
            'status' => sanitize_text_field($_POST['service_status'] ?? $_POST['status']),
            'service_order' => intval($_POST['service_order'] ?? 0)
        );
        
        // Validar datos requeridos
        if (empty($service_data['name']) || empty($service_data['category'])) {
            wp_send_json_error(array('message' => 'Nombre y categoría son requeridos'));
        }
        
        // Actualizar o insertar
        if (isset($_POST['service_id']) && !empty($_POST['service_id'])) {
            // Actualizar
            $result = $wpdb->update(
                $this->table_name,
                $service_data,
                array('id' => intval($_POST['service_id'])),
                array('%s', '%s', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d'),
                array('%d')
            );
        } else {
            // Insertar
            $result = $wpdb->insert(
                $this->table_name,
                $service_data,
                array('%s', '%s', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Servicio guardado exitosamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar el servicio'));
        }
    }
    
    /**
     * AJAX: Eliminar servicio
     */
    public function delete_service() {
        if (!wp_verify_nonce($_POST['nonce'], 'automatiza_services_nonce')) {
            wp_die('Error de seguridad');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }
        
        global $wpdb;
        
        $service_id = intval($_POST['service_id']);
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $service_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * AJAX: Cambiar estado del servicio
     */
    public function toggle_service_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'automatiza_services_nonce')) {
            wp_die('Error de seguridad');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }
        
        global $wpdb;
        
        $service_id = intval($_POST['service_id']);
        
        // Obtener estado actual
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->table_name} WHERE id = %d",
            $service_id
        ));
        
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $new_status),
            array('id' => $service_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * AJAX: Obtener detalles del servicio
     */
    public function get_service_details() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Asegurar headers UTF-8
        header('Content-Type: application/json; charset=utf-8');
        
        if (!wp_verify_nonce($_POST['nonce'], 'automatiza_services_nonce')) {
            wp_die('Error de seguridad');
        }
        
        global $wpdb;
        
        $service_id = intval($_POST['service_id']);
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $service_id
        ));
        
        if ($service) {
            wp_send_json_success($service);
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * AJAX: Duplicar servicio
     */
    public function duplicate_service() {
        if (!wp_verify_nonce($_POST['nonce'], 'automatiza_services_nonce')) {
            wp_die('Error de seguridad');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos');
        }
        
        global $wpdb;
        
        $service_id = intval($_POST['service_id']);
        
        // Obtener servicio original
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $service_id
        ), ARRAY_A);
        
        if ($original) {
            // Remover ID y modificar nombre
            unset($original['id']);
            $original['name'] = $original['name'] . ' (Copia)';
            $original['status'] = 'inactive'; // Crear copia inactiva
            
            $result = $wpdb->insert($this->table_name, $original);
            
            if ($result !== false) {
                wp_send_json_success();
            } else {
                wp_send_json_error();
            }
        } else {
            wp_send_json_error();
        }
    }
}

// Inicializar la clase
new AutomatizaTechServicesManager();

/**
 * Función helper para obtener servicios en el frontend
 */
function get_automatiza_services($category = '', $status = 'active') {
    $manager = new AutomatizaTechServicesManager();
    return $manager->get_services_by_category($category, $status);
}

/**
 * Función helper para obtener servicios activos
 */
function get_active_automatiza_services($category = '') {
    return get_automatiza_services($category, 'active');
}