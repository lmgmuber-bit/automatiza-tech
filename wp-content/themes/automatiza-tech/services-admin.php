<?php
/**
 * Panel de Administración de Servicios
 * Solo accesible para administradores
 */

// Agregar menú de administración
function add_services_admin_menu() {
    add_menu_page(
        'Gestión de Servicios',
        'Servicios',
        'manage_options',
        'automatiza-services',
        'services_admin_page',
        'dashicons-admin-tools',
        30
    );
    
    add_submenu_page(
        'automatiza-services',
        'Nuevo Servicio',
        'Agregar Nuevo',
        'manage_options',
        'automatiza-services-new',
        'new_service_page'
    );
    
    add_submenu_page(
        'automatiza-services',
        'Configuración de Moneda',
        'Tipo de Cambio',
        'manage_options',
        'automatiza-services-currency',
        'currency_settings_page'
    );
}
add_action('admin_menu', 'add_services_admin_menu');

// Página principal de administración
function services_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    // Procesar acciones
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['service_id'])) {
            $wpdb->delete($table_name, array('id' => intval($_POST['service_id'])));
            echo '<div class="notice notice-success"><p>Servicio eliminado correctamente.</p></div>';
        }
        
        if ($_POST['action'] === 'toggle_status' && isset($_POST['service_id'])) {
            $current_status = $wpdb->get_var($wpdb->prepare(
                "SELECT is_active FROM $table_name WHERE id = %d", 
                intval($_POST['service_id'])
            ));
            $new_status = $current_status ? 0 : 1;
            $wpdb->update(
                $table_name,
                array('is_active' => $new_status),
                array('id' => intval($_POST['service_id']))
            );
            echo '<div class="notice notice-success"><p>Estado del servicio actualizado.</p></div>';
        }
        
        if ($_POST['action'] === 'update_prices') {
            update_service_prices();
            echo '<div class="notice notice-success"><p>Precios actualizados con el tipo de cambio actual.</p></div>';
        }
    }
    
    $services = $wpdb->get_results("SELECT * FROM $table_name ORDER BY service_category, service_order");
    $current_rate = get_current_exchange_rate();
    ?>
    
    <div class="wrap">
        <h1>Gestión de Servicios y Precios</h1>
        
        <div class="card" style="margin-bottom: 20px; padding: 15px;">
            <h3>Tipo de Cambio Actual</h3>
            <p><strong>1 USD = <?php echo number_format($current_rate, 0, ',', '.'); ?> CLP</strong></p>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="update_prices">
                <button type="submit" class="button button-secondary">Actualizar Todos los Precios</button>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th>Categoría</th>
                    <th>Precio USD</th>
                    <th>Precio CLP</th>
                    <th>Estado</th>
                    <th>Popular</th>
                    <th>Orden</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($service->service_name); ?></strong><br>
                        <small><?php echo esc_html(substr($service->service_description, 0, 60)); ?>...</small>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $service->service_category; ?>">
                            <?php echo ucfirst($service->service_category); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($service->price_usd > 0): ?>
                            $<?php echo number_format($service->price_usd, 0); ?>
                        <?php else: ?>
                            <em>Gratis</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($service->price_clp > 0): ?>
                            $<?php echo number_format($service->price_clp, 0, ',', '.'); ?> CLP
                        <?php else: ?>
                            <em>Gratis</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($service->is_active): ?>
                            <span style="color: green;">●</span> Activo
                        <?php else: ?>
                            <span style="color: red;">●</span> Inactivo
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $service->is_popular ? '⭐ Sí' : 'No'; ?>
                    </td>
                    <td><?php echo $service->service_order; ?></td>
                    <td>
                        <a href="?page=automatiza-services-new&edit=<?php echo $service->id; ?>" class="button button-small">Editar</a>
                        
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="service_id" value="<?php echo $service->id; ?>">
                            <button type="submit" class="button button-small">
                                <?php echo $service->is_active ? 'Desactivar' : 'Activar'; ?>
                            </button>
                        </form>
                        
                        <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este servicio?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="service_id" value="<?php echo $service->id; ?>">
                            <button type="submit" class="button button-small button-link-delete">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
        .badge { 
            padding: 2px 8px; 
            border-radius: 3px; 
            font-size: 11px; 
            color: white; 
        }
        .badge-pricing { background: #0073aa; }
        .badge-features { background: #00a32a; }
        .badge-special { background: #d63638; }
        .badge-general { background: #8c8f94; }
        </style>
    </div>
    <?php
}

// Página para agregar/editar servicios
function new_service_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $editing = isset($_GET['edit']) ? intval($_GET['edit']) : false;
    $service = null;
    
    if ($editing) {
        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $editing));
        if ($service && $service->service_features) {
            $service->service_features = json_decode($service->service_features, true);
        }
    }
    
    // Procesar formulario
    if (isset($_POST['submit_service'])) {
        $service_name = sanitize_text_field($_POST['service_name']);
        $service_description = sanitize_textarea_field($_POST['service_description']);
        $service_icon = sanitize_text_field($_POST['service_icon']);
        $price_usd = floatval($_POST['price_usd']);
        $service_category = sanitize_text_field($_POST['service_category']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $service_order = intval($_POST['service_order']);
        
        $current_rate = get_current_exchange_rate();
        $price_clp = $price_usd * $current_rate;
        
        // Procesar características
        $features = array();
        if (isset($_POST['features']) && is_array($_POST['features'])) {
            foreach ($_POST['features'] as $feature) {
                if (!empty(trim($feature))) {
                    $features[] = sanitize_text_field($feature);
                }
            }
        }
        
        $data = array(
            'service_name' => $service_name,
            'service_description' => $service_description,
            'service_icon' => $service_icon,
            'price_usd' => $price_usd,
            'price_clp' => $price_clp,
            'exchange_rate' => $current_rate,
            'service_features' => json_encode($features),
            'service_category' => $service_category,
            'is_active' => $is_active,
            'is_popular' => $is_popular,
            'service_order' => $service_order
        );
        
        if ($editing) {
            $wpdb->update($table_name, $data, array('id' => $editing));
            echo '<div class="notice notice-success"><p>Servicio actualizado correctamente.</p></div>';
        } else {
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success"><p>Servicio creado correctamente.</p></div>';
        }
        
        // Refrescar datos si estamos editando
        if ($editing) {
            $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $editing));
            if ($service && $service->service_features) {
                $service->service_features = json_decode($service->service_features, true);
            }
        }
    }
    ?>
    
    <div class="wrap">
        <h1><?php echo $editing ? 'Editar Servicio' : 'Nuevo Servicio'; ?></h1>
        
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="service_name">Nombre del Servicio</label></th>
                    <td>
                        <input type="text" id="service_name" name="service_name" 
                               value="<?php echo $service ? esc_attr($service->service_name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="service_description">Descripción</label></th>
                    <td>
                        <textarea id="service_description" name="service_description" 
                                  rows="4" cols="50" class="large-text"><?php echo $service ? esc_textarea($service->service_description) : ''; ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="service_icon">Icono (Font Awesome)</label></th>
                    <td>
                        <input type="text" id="service_icon" name="service_icon" 
                               value="<?php echo $service ? esc_attr($service->service_icon) : 'fas fa-cogs'; ?>" 
                               class="regular-text">
                        <p class="description">Ejemplo: fas fa-rocket, fas fa-star, etc.</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="price_usd">Precio en USD</label></th>
                    <td>
                        <input type="number" id="price_usd" name="price_usd" 
                               value="<?php echo $service ? $service->price_usd : '0'; ?>" 
                               step="0.01" min="0" class="small-text">
                        <p class="description">Deja en 0 para servicios gratuitos</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="service_category">Categoría</label></th>
                    <td>
                        <select id="service_category" name="service_category">
                            <option value="pricing" <?php echo ($service && $service->service_category === 'pricing') ? 'selected' : ''; ?>>Pricing</option>
                            <option value="features" <?php echo ($service && $service->service_category === 'features') ? 'selected' : ''; ?>>Features</option>
                            <option value="special" <?php echo ($service && $service->service_category === 'special') ? 'selected' : ''; ?>>Special</option>
                            <option value="general" <?php echo ($service && $service->service_category === 'general') ? 'selected' : ''; ?>>General</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>Características/Features</th>
                    <td>
                        <div id="features-container">
                            <?php if ($service && $service->service_features): ?>
                                <?php foreach ($service->service_features as $feature): ?>
                                    <div class="feature-item">
                                        <input type="text" name="features[]" value="<?php echo esc_attr($feature); ?>" class="regular-text">
                                        <button type="button" class="button remove-feature">Eliminar</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="feature-item">
                                    <input type="text" name="features[]" placeholder="Característica del servicio" class="regular-text">
                                    <button type="button" class="button remove-feature">Eliminar</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="add-feature" class="button">Agregar Característica</button>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="service_order">Orden</label></th>
                    <td>
                        <input type="number" id="service_order" name="service_order" 
                               value="<?php echo $service ? $service->service_order : '0'; ?>" 
                               min="0" class="small-text">
                    </td>
                </tr>
                
                <tr>
                    <th>Estado</th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo ($service && $service->is_active) || !$service ? 'checked' : ''; ?>>
                            Activo
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="is_popular" value="1" 
                                   <?php echo ($service && $service->is_popular) ? 'checked' : ''; ?>>
                            Marcar como popular
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit_service" class="button-primary" 
                       value="<?php echo $editing ? 'Actualizar Servicio' : 'Crear Servicio'; ?>">
                <a href="?page=automatiza-services" class="button">Cancelar</a>
            </p>
        </form>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addFeatureBtn = document.getElementById('add-feature');
        const featuresContainer = document.getElementById('features-container');
        
        addFeatureBtn.addEventListener('click', function() {
            const div = document.createElement('div');
            div.className = 'feature-item';
            div.innerHTML = `
                <input type="text" name="features[]" placeholder="Característica del servicio" class="regular-text">
                <button type="button" class="button remove-feature">Eliminar</button>
            `;
            featuresContainer.appendChild(div);
        });
        
        featuresContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-feature')) {
                e.target.parentElement.remove();
            }
        });
    });
    </script>
    
    <style>
    .feature-item {
        margin-bottom: 10px;
    }
    .feature-item input {
        margin-right: 10px;
    }
    </style>
    <?php
}

// Página de configuración de moneda
function currency_settings_page() {
    if (isset($_POST['update_exchange_rate'])) {
        update_option('automatiza_manual_exchange_rate', floatval($_POST['manual_rate']));
        update_option('automatiza_use_manual_rate', isset($_POST['use_manual_rate']) ? 1 : 0);
        echo '<div class="notice notice-success"><p>Configuración actualizada.</p></div>';
    }
    
    $manual_rate = get_option('automatiza_manual_exchange_rate', 800);
    $use_manual_rate = get_option('automatiza_use_manual_rate', 0);
    $current_api_rate = get_current_exchange_rate();
    ?>
    
    <div class="wrap">
        <h1>Configuración de Tipo de Cambio</h1>
        
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Tipo de Cambio de API</th>
                    <td>
                        <strong>1 USD = <?php echo number_format($current_api_rate, 4, ',', '.'); ?> CLP</strong>
                        <p class="description">Obtenido automáticamente de exchangerate-api.com</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="manual_rate">Tipo de Cambio Manual</label></th>
                    <td>
                        <input type="number" id="manual_rate" name="manual_rate" 
                               value="<?php echo $manual_rate; ?>" 
                               step="0.0001" min="0" class="regular-text">
                        <p class="description">Define un tipo de cambio fijo</p>
                    </td>
                </tr>
                
                <tr>
                    <th>Configuración</th>
                    <td>
                        <label>
                            <input type="checkbox" name="use_manual_rate" value="1" <?php checked($use_manual_rate); ?>>
                            Usar tipo de cambio manual (ignorar API)
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="update_exchange_rate" class="button-primary" value="Guardar Configuración">
            </p>
        </form>
    </div>
    <?php
}

// Función mejorada para obtener tipo de cambio
function get_current_exchange_rate() {
    // Si está configurado para usar tipo manual
    if (get_option('automatiza_use_manual_rate', 0)) {
        return floatval(get_option('automatiza_manual_exchange_rate', 800));
    }
    
    // Usar API
    $api_url = 'https://api.exchangerate-api.com/v4/latest/USD';
    
    $response = wp_remote_get($api_url, array('timeout' => 10));
    
    if (is_wp_error($response)) {
        return floatval(get_option('automatiza_manual_exchange_rate', 800));
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['rates']['CLP'])) {
        return floatval($data['rates']['CLP']);
    }
    
    return floatval(get_option('automatiza_manual_exchange_rate', 800));
}
?>