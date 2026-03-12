<?php
/**
 * Sistema de Gestión de Categorías de Servicios
 * Maneja la administración de categorías de forma dinámica
 */

if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTechServiceCategoriesManager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_service_categories';
        
        // Hooks
        add_action('admin_menu', array($this, 'add_submenu'), 20);
        add_action('wp_ajax_save_service_category', array($this, 'save_category'));
        add_action('wp_ajax_delete_service_category', array($this, 'delete_category'));
        add_action('wp_ajax_toggle_category_status', array($this, 'toggle_status'));
        add_action('wp_ajax_get_category_details', array($this, 'get_category_details'));
        
        // Crear tabla si no existe
        add_action('after_setup_theme', array($this, 'create_table'));
    }
    
    /**
     * Crear tabla de categorías
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            slug varchar(50) NOT NULL UNIQUE,
            name varchar(100) NOT NULL,
            description text,
            icon varchar(100) DEFAULT 'fas fa-folder',
            color varchar(20) DEFAULT '#007cba',
            show_in_frontend tinyint(1) DEFAULT 1,
            show_in_quotations tinyint(1) DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            category_order int(11) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY slug (slug),
            KEY status (status),
            KEY category_order (category_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insertar categorías por defecto si la tabla está vacía
        $this->seed_default_categories();
    }
    
    /**
     * Insertar categorías por defecto
     */
    private function seed_default_categories() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        if ($count == 0) {
            $default_categories = array(
                array(
                    'slug' => 'pricing',
                    'name' => 'Planes de Precios',
                    'description' => 'Planes y paquetes de servicios con precios mensuales o únicos',
                    'icon' => 'fas fa-tags',
                    'color' => '#007cba',
                    'show_in_frontend' => 1,
                    'show_in_quotations' => 1,
                    'status' => 'active',
                    'category_order' => 1
                ),
                array(
                    'slug' => 'features',
                    'name' => 'Beneficios/Características',
                    'description' => 'Características y beneficios que se muestran en el sitio',
                    'icon' => 'fas fa-star',
                    'color' => '#28a745',
                    'show_in_frontend' => 1,
                    'show_in_quotations' => 0,
                    'status' => 'active',
                    'category_order' => 2
                ),
                array(
                    'slug' => 'special',
                    'name' => 'Ofertas Especiales',
                    'description' => 'Promociones y ofertas por tiempo limitado',
                    'icon' => 'fas fa-percent',
                    'color' => '#ff9800',
                    'show_in_frontend' => 1,
                    'show_in_quotations' => 1,
                    'status' => 'active',
                    'category_order' => 3
                ),
                array(
                    'slug' => 'custom',
                    'name' => 'Proyectos Personalizados',
                    'description' => 'Desarrollos a medida y proyectos especiales',
                    'icon' => 'fas fa-code',
                    'color' => '#6f42c1',
                    'show_in_frontend' => 0,
                    'show_in_quotations' => 1,
                    'status' => 'active',
                    'category_order' => 4
                )
            );
            
            foreach ($default_categories as $cat) {
                $wpdb->insert($this->table_name, $cat);
            }
        }
    }
    
    /**
     * Agregar submenú
     */
    public function add_submenu() {
        add_submenu_page(
            'automatiza-services',
            'Categorías de Servicios',
            'Categorías',
            'manage_options',
            'automatiza-service-categories',
            array($this, 'render_page')
        );
    }
    
    /**
     * Renderizar página de categorías
     */
    public function render_page() {
        global $wpdb;
        
        $categories = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY category_order ASC, name ASC"
        );
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-category" style="font-size: 30px; margin-right: 10px;"></span>
                Categorías de Servicios
            </h1>
            <button type="button" class="page-title-action" onclick="showNewCategoryModal()">Agregar Nueva</button>
            <hr class="wp-header-end">
            
            <style>
                .categories-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                .category-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 8px;
                    padding: 20px;
                    position: relative;
                    transition: box-shadow 0.2s;
                }
                .category-card:hover {
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .category-card.inactive {
                    opacity: 0.6;
                    background: #f5f5f5;
                }
                .category-header {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 15px;
                }
                .category-icon {
                    width: 50px;
                    height: 50px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    color: #fff;
                }
                .category-title {
                    flex: 1;
                }
                .category-title h3 {
                    margin: 0 0 5px 0;
                    font-size: 16px;
                }
                .category-title code {
                    background: #f0f0f1;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 12px;
                }
                .category-status {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .category-status.active {
                    background: #d4edda;
                    color: #155724;
                }
                .category-status.inactive {
                    background: #f8d7da;
                    color: #721c24;
                }
                .category-description {
                    color: #666;
                    font-size: 13px;
                    margin-bottom: 15px;
                    min-height: 40px;
                }
                .category-meta {
                    display: flex;
                    gap: 15px;
                    font-size: 12px;
                    color: #888;
                    margin-bottom: 15px;
                }
                .category-meta span {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }
                .category-actions {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .category-actions button {
                    font-size: 12px;
                    padding: 5px 10px;
                }
                
                /* Modal */
                .category-modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.7);
                    z-index: 100000;
                    align-items: center;
                    justify-content: center;
                }
                .category-modal.show {
                    display: flex;
                }
                .modal-content {
                    background: #fff;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 500px;
                    max-height: 90vh;
                    overflow-y: auto;
                }
                .modal-header {
                    padding: 15px 20px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .modal-header h2 {
                    margin: 0;
                    font-size: 18px;
                }
                .modal-close {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #666;
                }
                .modal-body {
                    padding: 20px;
                }
                .form-row {
                    margin-bottom: 15px;
                }
                .form-row label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                }
                .form-row input[type="text"],
                .form-row input[type="number"],
                .form-row textarea,
                .form-row select {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .form-row .description {
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }
                .form-row-inline {
                    display: flex;
                    gap: 15px;
                }
                .form-row-inline .form-row {
                    flex: 1;
                }
                .checkbox-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .checkbox-row input {
                    width: auto;
                }
                .color-preview {
                    display: inline-block;
                    width: 30px;
                    height: 30px;
                    border-radius: 4px;
                    vertical-align: middle;
                    margin-left: 10px;
                    border: 1px solid #ddd;
                }
                .modal-footer {
                    padding: 15px 20px;
                    border-top: 1px solid #ddd;
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                }
                
                /* Stats */
                .categories-stats {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 20px;
                }
                .stat-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 8px;
                    padding: 15px 25px;
                    text-align: center;
                }
                .stat-card h3 {
                    margin: 0;
                    font-size: 28px;
                    color: #007cba;
                }
                .stat-card p {
                    margin: 5px 0 0 0;
                    color: #666;
                }
                
                /* ==================== ESTILOS RESPONSIVOS CATEGORÍAS ==================== */
                
                /* Tablet (1024px y menos) */
                @media screen and (max-width: 1024px) {
                    .categories-grid {
                        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                    }
                }
                
                /* Mobile (767px y menos) */
                @media screen and (max-width: 767px) {
                    .wrap h1.wp-heading-inline {
                        font-size: 18px;
                        display: flex;
                        align-items: center;
                        flex-wrap: wrap;
                    }
                    .page-title-action {
                        margin-top: 10px;
                        width: 100%;
                        text-align: center;
                    }
                    
                    /* Stats cards */
                    .categories-stats {
                        flex-direction: column;
                        gap: 10px;
                    }
                    .stat-card {
                        padding: 12px 15px;
                    }
                    .stat-card h3 {
                        font-size: 22px;
                    }
                    
                    /* Categories grid */
                    .categories-grid {
                        grid-template-columns: 1fr;
                        gap: 15px;
                    }
                    .category-card {
                        padding: 15px;
                    }
                    .category-header {
                        flex-wrap: wrap;
                    }
                    .category-actions {
                        width: 100%;
                        justify-content: space-between;
                    }
                    .category-actions button {
                        flex: 1;
                        min-height: 40px;
                    }
                    
                    /* Modal fullscreen en móvil */
                    .modal-content {
                        width: 100% !important;
                        max-width: 100% !important;
                        height: 100vh;
                        max-height: 100vh !important;
                        margin: 0;
                        border-radius: 0;
                    }
                    .modal-body {
                        padding: 15px;
                    }
                    .modal-body input[type="text"],
                    .modal-body input[type="color"],
                    .modal-body select,
                    .modal-body textarea {
                        width: 100% !important;
                        min-height: 44px;
                        font-size: 16px !important;
                    }
                    .modal-footer {
                        flex-direction: column;
                    }
                    .modal-footer button {
                        width: 100%;
                        min-height: 44px;
                    }
                }
                
                /* Touch-friendly */
                @media (hover: none) and (pointer: coarse) {
                    .category-actions button,
                    .modal-body input,
                    .modal-body select,
                    .modal-footer button {
                        min-height: 48px;
                    }
                }
            </style>
            
            <!-- Estadísticas -->
            <div class="categories-stats">
                <div class="stat-card">
                    <h3><?php echo count($categories); ?></h3>
                    <p>Total Categorías</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($categories, function($c) { return $c->status === 'active'; })); ?></h3>
                    <p>Activas</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($categories, function($c) { return $c->show_in_frontend; })); ?></h3>
                    <p>Visibles en Web</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($categories, function($c) { return $c->show_in_quotations; })); ?></h3>
                    <p>En Cotizaciones</p>
                </div>
            </div>
            
            <!-- Grid de categorías -->
            <div class="categories-grid">
                <?php foreach ($categories as $cat): ?>
                <div class="category-card <?php echo $cat->status; ?>" data-id="<?php echo $cat->id; ?>">
                    <span class="category-status <?php echo $cat->status; ?>">
                        <?php echo $cat->status === 'active' ? 'Activa' : 'Inactiva'; ?>
                    </span>
                    
                    <div class="category-header">
                        <div class="category-icon" style="background-color: <?php echo esc_attr($cat->color); ?>">
                            <i class="<?php echo esc_attr($cat->icon); ?>"></i>
                        </div>
                        <div class="category-title">
                            <h3><?php echo esc_html($cat->name); ?></h3>
                            <code><?php echo esc_html($cat->slug); ?></code>
                        </div>
                    </div>
                    
                    <div class="category-description">
                        <?php echo esc_html($cat->description ?: 'Sin descripción'); ?>
                    </div>
                    
                    <div class="category-meta">
                        <span>
                            <i class="dashicons dashicons-visibility"></i>
                            <?php echo $cat->show_in_frontend ? 'Visible en web' : 'Oculta en web'; ?>
                        </span>
                        <span>
                            <i class="dashicons dashicons-media-document"></i>
                            <?php echo $cat->show_in_quotations ? 'En cotizaciones' : 'No en cotizaciones'; ?>
                        </span>
                    </div>
                    
                    <div class="category-actions">
                        <button type="button" class="button button-primary button-small" onclick="editCategory(<?php echo $cat->id; ?>)">
                            <span class="dashicons dashicons-edit" style="font-size: 14px; line-height: 1.4;"></span> Editar
                        </button>
                        <button type="button" class="button button-small" onclick="toggleCategoryStatus(<?php echo $cat->id; ?>)">
                            <?php echo $cat->status === 'active' ? '🔴 Desactivar' : '🟢 Activar'; ?>
                        </button>
                        <?php if (!in_array($cat->slug, array('pricing', 'features', 'special', 'custom'))): ?>
                        <button type="button" class="button button-small" style="color: #dc3545;" onclick="deleteCategory(<?php echo $cat->id; ?>, '<?php echo esc_js($cat->name); ?>')">
                            <span class="dashicons dashicons-trash" style="font-size: 14px; line-height: 1.4;"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Modal para crear/editar categoría -->
            <div id="category-modal" class="category-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="modal-title">Nueva Categoría</h2>
                        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
                    </div>
                    <form id="category-form">
                        <div class="modal-body">
                            <input type="hidden" name="category_id" id="category_id" value="">
                            
                            <div class="form-row">
                                <label for="category_name">Nombre *</label>
                                <input type="text" name="category_name" id="category_name" required>
                                <p class="description">Nombre que se mostrará en el panel y frontend</p>
                            </div>
                            
                            <div class="form-row">
                                <label for="category_slug">Slug (identificador) *</label>
                                <input type="text" name="category_slug" id="category_slug" required pattern="[a-z0-9_-]+" title="Solo letras minúsculas, números, guiones y guiones bajos">
                                <p class="description">Identificador único. Solo letras minúsculas, números y guiones. Ej: desarrollo-web</p>
                            </div>
                            
                            <div class="form-row">
                                <label for="category_description">Descripción</label>
                                <textarea name="category_description" id="category_description" rows="2"></textarea>
                            </div>
                            
                            <div class="form-row-inline">
                                <div class="form-row">
                                    <label for="category_icon">Icono FontAwesome</label>
                                    <input type="text" name="category_icon" id="category_icon" value="fas fa-folder" placeholder="fas fa-folder">
                                    <p class="description"><a href="https://fontawesome.com/icons" target="_blank">Ver iconos</a></p>
                                </div>
                                <div class="form-row">
                                    <label for="category_color">Color</label>
                                    <input type="color" name="category_color" id="category_color" value="#007cba" style="width: 60px; height: 38px; padding: 2px;">
                                    <span class="color-preview" id="color-preview" style="background: #007cba;"></span>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <label for="category_order">Orden de aparición</label>
                                <input type="number" name="category_order" id="category_order" value="0" min="0">
                            </div>
                            
                            <div class="form-row">
                                <div class="checkbox-row">
                                    <input type="checkbox" name="show_in_frontend" id="show_in_frontend" value="1" checked>
                                    <label for="show_in_frontend">Mostrar en el sitio web público</label>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="checkbox-row">
                                    <input type="checkbox" name="show_in_quotations" id="show_in_quotations" value="1" checked>
                                    <label for="show_in_quotations">Disponible para cotizaciones</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button" onclick="closeModal()">Cancelar</button>
                            <button type="submit" class="button button-primary">Guardar Categoría</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-generar slug desde el nombre
            $('#category_name').on('input', function() {
                if (!$('#category_id').val()) { // Solo si es nueva categoría
                    var slug = $(this).val()
                        .toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Quitar acentos
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    $('#category_slug').val(slug);
                }
            });
            
            // Actualizar preview de color
            $('#category_color').on('input', function() {
                $('#color-preview').css('background', $(this).val());
            });
            
            // Submit del formulario
            $('#category-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'save_service_category',
                    nonce: '<?php echo wp_create_nonce('automatiza_categories_nonce'); ?>',
                    category_id: $('#category_id').val(),
                    name: $('#category_name').val(),
                    slug: $('#category_slug').val(),
                    description: $('#category_description').val(),
                    icon: $('#category_icon').val(),
                    color: $('#category_color').val(),
                    category_order: $('#category_order').val(),
                    show_in_frontend: $('#show_in_frontend').is(':checked') ? 1 : 0,
                    show_in_quotations: $('#show_in_quotations').is(':checked') ? 1 : 0
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.message || 'No se pudo guardar'));
                    }
                });
            });
        });
        
        function showNewCategoryModal() {
            document.getElementById('modal-title').textContent = 'Nueva Categoría';
            document.getElementById('category-form').reset();
            document.getElementById('category_id').value = '';
            document.getElementById('category_color').value = '#007cba';
            document.getElementById('color-preview').style.background = '#007cba';
            document.getElementById('category-modal').classList.add('show');
        }
        
        function editCategory(id) {
            jQuery.post(ajaxurl, {
                action: 'get_category_details',
                nonce: '<?php echo wp_create_nonce('automatiza_categories_nonce'); ?>',
                category_id: id
            }, function(response) {
                if (response.success) {
                    var cat = response.data;
                    document.getElementById('modal-title').textContent = 'Editar Categoría';
                    document.getElementById('category_id').value = cat.id;
                    document.getElementById('category_name').value = cat.name;
                    document.getElementById('category_slug').value = cat.slug;
                    document.getElementById('category_description').value = cat.description || '';
                    document.getElementById('category_icon').value = cat.icon;
                    document.getElementById('category_color').value = cat.color;
                    document.getElementById('color-preview').style.background = cat.color;
                    document.getElementById('category_order').value = cat.category_order;
                    document.getElementById('show_in_frontend').checked = cat.show_in_frontend == 1;
                    document.getElementById('show_in_quotations').checked = cat.show_in_quotations == 1;
                    document.getElementById('category-modal').classList.add('show');
                }
            });
        }
        
        function closeModal() {
            document.getElementById('category-modal').classList.remove('show');
        }
        
        function toggleCategoryStatus(id) {
            jQuery.post(ajaxurl, {
                action: 'toggle_category_status',
                nonce: '<?php echo wp_create_nonce('automatiza_categories_nonce'); ?>',
                category_id: id
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
        
        function deleteCategory(id, name) {
            if (confirm('¿Eliminar la categoría "' + name + '"?\n\nLos servicios asociados quedarán sin categoría.')) {
                jQuery.post(ajaxurl, {
                    action: 'delete_service_category',
                    nonce: '<?php echo wp_create_nonce('automatiza_categories_nonce'); ?>',
                    category_id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.message || 'No se pudo eliminar'));
                    }
                });
            }
        }
        
        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('category-modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        </script>
        <?php
    }
    
    /**
     * Guardar categoría (AJAX)
     */
    public function save_category() {
        check_ajax_referer('automatiza_categories_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos'));
        }
        
        global $wpdb;
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug']),
            'description' => sanitize_textarea_field($_POST['description']),
            'icon' => sanitize_text_field($_POST['icon']),
            'color' => sanitize_hex_color($_POST['color']),
            'category_order' => intval($_POST['category_order']),
            'show_in_frontend' => intval($_POST['show_in_frontend']),
            'show_in_quotations' => intval($_POST['show_in_quotations'])
        );
        
        if (empty($data['name']) || empty($data['slug'])) {
            wp_send_json_error(array('message' => 'Nombre y slug son requeridos'));
        }
        
        $category_id = intval($_POST['category_id']);
        
        if ($category_id > 0) {
            // Actualizar
            $result = $wpdb->update(
                $this->table_name,
                $data,
                array('id' => $category_id)
            );
        } else {
            // Verificar que el slug no exista
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s",
                $data['slug']
            ));
            
            if ($exists > 0) {
                wp_send_json_error(array('message' => 'Ya existe una categoría con ese slug'));
            }
            
            // Insertar
            $result = $wpdb->insert($this->table_name, $data);
        }
        
        if ($result !== false) {
            // Limpiar cache
            delete_transient('automatiza_service_categories');
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Error al guardar'));
        }
    }
    
    /**
     * Obtener detalles de categoría (AJAX)
     */
    public function get_category_details() {
        check_ajax_referer('automatiza_categories_nonce', 'nonce');
        
        global $wpdb;
        
        $id = intval($_POST['category_id']);
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if ($category) {
            wp_send_json_success($category);
        } else {
            wp_send_json_error(array('message' => 'Categoría no encontrada'));
        }
    }
    
    /**
     * Cambiar estado de categoría (AJAX)
     */
    public function toggle_status() {
        check_ajax_referer('automatiza_categories_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        global $wpdb;
        
        $id = intval($_POST['category_id']);
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        $new_status = ($current === 'active') ? 'inactive' : 'active';
        
        $wpdb->update(
            $this->table_name,
            array('status' => $new_status),
            array('id' => $id)
        );
        
        delete_transient('automatiza_service_categories');
        wp_send_json_success();
    }
    
    /**
     * Eliminar categoría (AJAX)
     */
    public function delete_category() {
        check_ajax_referer('automatiza_categories_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        global $wpdb;
        
        $id = intval($_POST['category_id']);
        
        // Verificar que no sea una categoría protegida
        $slug = $wpdb->get_var($wpdb->prepare(
            "SELECT slug FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (in_array($slug, array('pricing', 'features', 'special', 'custom'))) {
            wp_send_json_error(array('message' => 'No se pueden eliminar las categorías del sistema'));
        }
        
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result) {
            delete_transient('automatiza_service_categories');
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Error al eliminar'));
        }
    }
}

// Inicializar
new AutomatizaTechServiceCategoriesManager();

/**
 * ============================================================
 * FUNCIONES HELPER GLOBALES PARA OBTENER CATEGORÍAS
 * ============================================================
 */

/**
 * Obtener todas las categorías de servicios
 */
function get_automatiza_service_categories($status = 'active') {
    global $wpdb;
    
    $cache_key = 'automatiza_service_categories_' . $status;
    $categories = get_transient($cache_key);
    
    if ($categories === false) {
        $table_name = $wpdb->prefix . 'automatiza_service_categories';
        
        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        
        if (!$table_exists) {
            // Retornar categorías por defecto si la tabla no existe
            return get_default_service_categories();
        }
        
        $sql = "SELECT * FROM {$table_name}";
        if ($status !== 'all') {
            $sql .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        $sql .= " ORDER BY category_order ASC, name ASC";
        
        $categories = $wpdb->get_results($sql);
        
        if (empty($categories)) {
            return get_default_service_categories();
        }
        
        set_transient($cache_key, $categories, HOUR_IN_SECONDS);
    }
    
    return $categories;
}

/**
 * Obtener categorías para cotizaciones
 */
function get_quotation_service_categories() {
    $all_categories = get_automatiza_service_categories('active');
    return array_filter($all_categories, function($cat) {
        return $cat->show_in_quotations == 1;
    });
}

/**
 * Obtener categorías para el frontend
 */
function get_frontend_service_categories() {
    $all_categories = get_automatiza_service_categories('active');
    return array_filter($all_categories, function($cat) {
        return $cat->show_in_frontend == 1;
    });
}

/**
 * Obtener categoría por slug
 */
function get_service_category_by_slug($slug) {
    $categories = get_automatiza_service_categories('all');
    foreach ($categories as $cat) {
        if ($cat->slug === $slug) {
            return $cat;
        }
    }
    return null;
}

/**
 * Obtener nombre de categoría por slug
 */
function get_service_category_name($slug) {
    $category = get_service_category_by_slug($slug);
    if ($category) {
        return $category->name;
    }
    // Fallback para categorías antiguas
    $defaults = array(
        'pricing' => 'Planes de Precios',
        'features' => 'Beneficios/Características',
        'special' => 'Ofertas Especiales',
        'custom' => 'Proyectos Personalizados'
    );
    return isset($defaults[$slug]) ? $defaults[$slug] : ucfirst($slug);
}

/**
 * Categorías por defecto (fallback)
 */
function get_default_service_categories() {
    return array(
        (object) array(
            'id' => 0,
            'slug' => 'pricing',
            'name' => 'Planes de Precios',
            'description' => '',
            'icon' => 'fas fa-tags',
            'color' => '#007cba',
            'show_in_frontend' => 1,
            'show_in_quotations' => 1,
            'status' => 'active',
            'category_order' => 1
        ),
        (object) array(
            'id' => 0,
            'slug' => 'features',
            'name' => 'Beneficios/Características',
            'description' => '',
            'icon' => 'fas fa-star',
            'color' => '#28a745',
            'show_in_frontend' => 1,
            'show_in_quotations' => 0,
            'status' => 'active',
            'category_order' => 2
        ),
        (object) array(
            'id' => 0,
            'slug' => 'special',
            'name' => 'Ofertas Especiales',
            'description' => '',
            'icon' => 'fas fa-percent',
            'color' => '#ff9800',
            'show_in_frontend' => 1,
            'show_in_quotations' => 1,
            'status' => 'active',
            'category_order' => 3
        ),
        (object) array(
            'id' => 0,
            'slug' => 'custom',
            'name' => 'Proyectos Personalizados',
            'description' => '',
            'icon' => 'fas fa-code',
            'color' => '#6f42c1',
            'show_in_frontend' => 0,
            'show_in_quotations' => 1,
            'status' => 'active',
            'category_order' => 4
        )
    );
}

/**
 * Generar HTML del select de categorías para formularios
 */
function render_service_categories_select($selected = '', $name = 'category', $id = '', $for_quotations = false) {
    $categories = $for_quotations ? get_quotation_service_categories() : get_automatiza_service_categories('active');
    $id = $id ?: $name;
    
    $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '">';
    $html .= '<option value="">Seleccionar categoría</option>';
    
    foreach ($categories as $cat) {
        $is_selected = ($selected === $cat->slug) ? ' selected' : '';
        $html .= '<option value="' . esc_attr($cat->slug) . '"' . $is_selected . '>' . esc_html($cat->name) . '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Generar opciones de categorías para JavaScript
 */
function get_service_categories_js_options($selected_var = 'service.category') {
    $categories = get_automatiza_service_categories('active');
    
    $options = array();
    foreach ($categories as $cat) {
        $options[] = '<option value="' . esc_attr($cat->slug) . '"' . "' + ({$selected_var} === '" . esc_js($cat->slug) . "' ? ' selected' : '') + '>" . esc_js($cat->name) . '</option>';
    }
    
    return implode('', $options);
}
