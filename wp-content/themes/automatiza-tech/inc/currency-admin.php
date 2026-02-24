<?php
/**
 * Panel de Administración - Actualización de Precios CLP
 * 
 * Interfaz para monitorear y controlar las actualizaciones automáticas de precios
 * 
 * @package AutomatizaTech
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTech_Currency_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Agregar menú en el admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'automatiza-tech-contacts',  // Parent slug correcto
            'Actualización de Precios',
            '💱 Precios CLP',
            'manage_options',
            'automatiza-tech-currency',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Cargar scripts del admin
     */
    public function enqueue_admin_scripts($hook) {
        // El hook correcto cuando el parent es 'automatiza-tech-contacts'
        if ($hook !== 'contactos_page_automatiza-tech-currency') {
            return;
        }
        
        wp_enqueue_script(
            'automatiza-currency-admin',
            get_template_directory_uri() . '/assets/js/currency-admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('automatiza-currency-admin', 'automatizaCurrency', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('automatiza_tech_admin')
        ));
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        // Obtener instancia del updater
        $updater = automatiza_tech_init_currency_updater();
        $info = $updater->get_last_update_info();
        
        // Obtener tipo de cambio actual
        $current_rate = $updater->get_current_exchange_rate();
        
        // Obtener servicios
        global $wpdb;
        $services_table = $wpdb->prefix . 'automatiza_services';
        $services = $wpdb->get_results("
            SELECT id, name, price_usd, price_clp, status
            FROM {$services_table}
            ORDER BY id ASC
        ");
        
        ?>
        <div class="wrap" style="max-width: 1400px;">
            <h1>💱 Actualización Automática de Precios CLP</h1>
            <p class="description">
                Sistema automático que actualiza los precios en CLP basándose en el tipo de cambio oficial USD/CLP del Banco Central de Chile.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
                
                <!-- Tipo de Cambio Actual -->
                <div class="postbox" style="padding: 20px;">
                    <h2 style="margin-top: 0; color: #1e3a8a;">
                        💵 Tipo de Cambio Actual
                    </h2>
                    <div style="font-size: 2.5em; font-weight: bold; color: #06d6a0; margin: 20px 0;">
                        $<?php echo number_format($current_rate, 2); ?>
                    </div>
                    <p style="color: #666; margin: 0;">
                        <strong>1 USD</strong> = <strong><?php echo number_format($current_rate, 2); ?> CLP</strong>
                    </p>
                    <p style="color: #888; font-size: 0.9em; margin-top: 10px;">
                        <em>Fuente: Banco Central de Chile (mindicador.cl)</em>
                    </p>
                </div>
                
                <!-- Última Actualización -->
                <div class="postbox" style="padding: 20px;">
                    <h2 style="margin-top: 0; color: #1e3a8a;">
                        🕐 Última Actualización
                    </h2>
                    <div style="margin: 15px 0;">
                        <strong style="display: block; color: #666; margin-bottom: 5px;">Fecha:</strong>
                        <span style="font-size: 1.2em;">
                            <?php 
                            if ($info['last_update'] === 'Nunca') {
                                echo '<span style="color: #dc3545;">Nunca ejecutada</span>';
                            } else {
                                $date = new DateTime($info['last_update']);
                                echo $date->format('d/m/Y H:i:s');
                            }
                            ?>
                        </span>
                    </div>
                    <div style="margin: 15px 0;">
                        <strong style="display: block; color: #666; margin-bottom: 5px;">Servicios actualizados:</strong>
                        <span style="font-size: 1.5em; color: #06d6a0; font-weight: bold;">
                            <?php echo $info['updated_count']; ?>
                        </span>
                    </div>
                    <div style="margin: 15px 0;">
                        <strong style="display: block; color: #666; margin-bottom: 5px;">Tasa usada:</strong>
                        <span style="font-size: 1.2em;">
                            $<?php echo $info['exchange_rate'] > 0 ? number_format($info['exchange_rate'], 2) : 'N/A'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Próxima Actualización -->
                <div class="postbox" style="padding: 20px;">
                    <h2 style="margin-top: 0; color: #1e3a8a;">
                        ⏰ Próxima Actualización
                    </h2>
                    <div style="margin: 15px 0;">
                        <strong style="display: block; color: #666; margin-bottom: 5px;">Programada para:</strong>
                        <span style="font-size: 1.2em;">
                            <?php 
                            if ($info['next_scheduled']) {
                                $next = new DateTime('@' . $info['next_scheduled']);
                                $next->setTimezone(new DateTimeZone('America/Santiago'));
                                echo $next->format('d/m/Y H:i:s');
                            } else {
                                echo '<span style="color: #dc3545;">No programada</span>';
                            }
                            ?>
                        </span>
                    </div>
                    <div style="margin: 15px 0;">
                        <strong style="display: block; color: #666; margin-bottom: 5px;">Frecuencia:</strong>
                        <span style="font-size: 1.2em;">Diaria (8:00 AM)</span>
                    </div>
                    <div style="margin-top: 20px;">
                        <button id="update-now-btn" class="button button-primary button-large" style="width: 100%; height: 45px; font-size: 1.1em;">
                            🔄 Actualizar Ahora
                        </button>
                    </div>
                </div>
                
            </div>
            
            <!-- Tabla de Servicios -->
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0; color: #1e3a8a;">
                    📊 Servicios y Precios
                </h2>
                
                <div id="update-result" style="display: none; margin-bottom: 20px;"></div>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Nombre del Servicio</th>
                            <th style="width: 120px; text-align: right;">Precio USD</th>
                            <th style="width: 150px; text-align: right;">Precio CLP Actual</th>
                            <th style="width: 150px; text-align: right;">Precio CLP Estimado</th>
                            <th style="width: 100px; text-align: center;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #666;">
                                    No hay servicios registrados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($services as $service): ?>
                                <?php 
                                $estimated_clp = round($service->price_usd * $current_rate / 1000) * 1000;
                                $difference = $service->price_clp > 0 ? (($estimated_clp - $service->price_clp) / $service->price_clp * 100) : 0;
                                $needs_update = abs($difference) >= 2.0;
                                ?>
                                <tr style="<?php echo $needs_update ? 'background: #fff3cd;' : ''; ?>">
                                    <td><strong><?php echo $service->id; ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html($service->name); ?></strong>
                                        <?php if ($needs_update): ?>
                                            <span style="color: #f59e0b; font-size: 0.9em;">
                                                ⚠️ Requiere actualización (<?php echo number_format(abs($difference), 1); ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?php if ($service->price_usd > 0): ?>
                                            $<?php echo number_format($service->price_usd, 2); ?>
                                        <?php else: ?>
                                            <span style="color: #dc3545;">No definido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-size: 1.1em; font-weight: 600;">
                                        $<?php echo number_format($service->price_clp, 0); ?>
                                    </td>
                                    <td style="text-align: right; font-size: 1.1em; color: <?php echo $needs_update ? '#f59e0b' : '#06d6a0'; ?>; font-weight: 600;">
                                        $<?php echo number_format($estimated_clp, 0); ?>
                                        <?php if ($difference != 0 && $service->price_clp > 0): ?>
                                            <span style="font-size: 0.85em; display: block; margin-top: 3px;">
                                                (<?php echo $difference > 0 ? '+' : ''; ?><?php echo number_format($difference, 1); ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($service->status === 'active'): ?>
                                            <span style="color: #06d6a0; font-weight: 600;">✓ Activo</span>
                                        <?php else: ?>
                                            <span style="color: #999;">○ Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #1e3a8a; border-radius: 5px;">
                    <h4 style="margin-top: 0; color: #1e3a8a;">ℹ️ Información Importante</h4>
                    <ul style="margin: 10px 0; padding-left: 20px; color: #666;">
                        <li><strong>Actualización Automática:</strong> Se ejecuta diariamente a las 8:00 AM (hora de Chile)</li>
                        <li><strong>Fuente Oficial:</strong> Banco Central de Chile (mindicador.cl) - Dólar observado</li>
                        <li><strong>Redondeo:</strong> Los precios se redondean a múltiplos de $1.000 CLP</li>
                        <li><strong>Umbral de Actualización:</strong> Solo se actualizan precios con cambios mayores al 2%</li>
                        <li><strong>Fallback:</strong> Si falla la API principal, usa API alternativa automáticamente</li>
                        <li><strong>Precios USD:</strong> Los precios en USD nunca se modifican (son la referencia base)</li>
                    </ul>
                </div>
            </div>
            
        </div>
        
        <style>
            #update-now-btn:hover {
                transform: scale(1.02);
                transition: transform 0.2s;
            }
            
            #update-now-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            
            .update-success {
                padding: 15px;
                background: #d4edda;
                border-left: 4px solid #28a745;
                border-radius: 5px;
                color: #155724;
            }
            
            .update-error {
                padding: 15px;
                background: #f8d7da;
                border-left: 4px solid #dc3545;
                border-radius: 5px;
                color: #721c24;
            }
            
            .update-info {
                padding: 15px;
                background: #d1ecf1;
                border-left: 4px solid #17a2b8;
                border-radius: 5px;
                color: #0c5460;
            }
            
            .spinner-border {
                display: inline-block;
                width: 1rem;
                height: 1rem;
                vertical-align: text-bottom;
                border: 2px solid currentColor;
                border-right-color: transparent;
                border-radius: 50%;
                animation: spinner-border .75s linear infinite;
            }
            
            @keyframes spinner-border {
                to { transform: rotate(360deg); }
            }
            
            /* ==================== ESTILOS RESPONSIVOS CURRENCY ADMIN ==================== */
            
            /* Tablet (1024px y menos) */
            @media screen and (max-width: 1024px) {
                .wrap[style*="max-width: 1400px"] {
                    max-width: 100% !important;
                    padding: 0 15px;
                }
            }
            
            /* Mobile (767px y menos) */
            @media screen and (max-width: 767px) {
                .wrap h1 {
                    font-size: 20px;
                }
                .wrap > p.description {
                    font-size: 14px;
                }
                
                /* Grid de cards */
                div[style*="grid-template-columns"] {
                    grid-template-columns: 1fr !important;
                    gap: 15px !important;
                    padding: 0 !important;
                }
                
                /* Postbox cards */
                .postbox {
                    padding: 15px !important;
                }
                .postbox h2 {
                    font-size: 16px;
                }
                
                /* Tipo de cambio grande */
                div[style*="font-size: 2.5em"] {
                    font-size: 2em !important;
                }
                
                /* Botón actualizar */
                #update-now-btn {
                    height: 50px !important;
                    font-size: 1em !important;
                }
                
                /* Tabla de servicios - scroll horizontal */
                .postbox table.wp-list-table {
                    display: block;
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                .postbox table.wp-list-table {
                    min-width: 600px;
                }
                .postbox table.wp-list-table th,
                .postbox table.wp-list-table td {
                    padding: 10px 8px;
                    font-size: 13px;
                }
                
                /* Info box */
                div[style*="border-left: 4px solid #1e3a8a"] {
                    padding: 12px !important;
                }
                div[style*="border-left: 4px solid #1e3a8a"] h4 {
                    font-size: 15px;
                }
                div[style*="border-left: 4px solid #1e3a8a"] ul {
                    font-size: 13px;
                    padding-left: 15px !important;
                }
            }
            
            /* Móviles pequeños (480px y menos) */
            @media screen and (max-width: 480px) {
                div[style*="font-size: 2.5em"] {
                    font-size: 1.8em !important;
                }
                .postbox table.wp-list-table {
                    min-width: 500px;
                    font-size: 12px;
                }
            }
            
            /* Touch-friendly */
            @media (hover: none) and (pointer: coarse) {
                #update-now-btn {
                    min-height: 50px;
                }
            }
        </style>
        <?php
    }
}

// Inicializar el admin
new AutomatizaTech_Currency_Admin();
