<?php
/**
 * Configuración de datos de facturación
 * Permite editar la información de la empresa desde el panel de administración
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Agregar página de configuración al menú de administración
add_action('admin_menu', 'automatiza_invoice_settings_menu');

function automatiza_invoice_settings_menu() {
    add_menu_page(
        'Configuración de Facturas',
        'Datos Facturación',
        'manage_options',
        'automatiza-invoice-settings',
        'automatiza_invoice_settings_page',
        'dashicons-text-page',
        30
    );
}

// Registrar configuraciones
add_action('admin_init', 'automatiza_register_invoice_settings');

function automatiza_register_invoice_settings() {
    // Datos de la empresa
    register_setting('automatiza_invoice_settings', 'company_name');
    register_setting('automatiza_invoice_settings', 'company_rut');
    register_setting('automatiza_invoice_settings', 'company_giro');
    register_setting('automatiza_invoice_settings', 'company_email');
    register_setting('automatiza_invoice_settings', 'company_phone');
    register_setting('automatiza_invoice_settings', 'company_website');
    register_setting('automatiza_invoice_settings', 'company_address');
}

// Página de configuración
function automatiza_invoice_settings_page() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }

    // Obtener valores actuales (con valores por defecto)
    $company_name = get_option('company_name', 'AutomatizaTech SpA');
    $company_rut = get_option('company_rut', '77.123.456-7');
    $company_giro = get_option('company_giro', 'Servicios tecnológicos');
    $company_email = get_option('company_email', 'contacto@automatizatech.cl');
    $company_phone = get_option('company_phone', '+56 9 1234 5678');
    $company_website = get_option('company_website', 'www.automatizatech.cl');
    $company_address = get_option('company_address', 'Santiago, Chile');

    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-text-page" style="font-size: 32px; color: #0096C7;"></span>
            Configuración de Datos de Facturación
        </h1>
        
        <p style="font-size: 14px; color: #666;">
            Configura la información de tu empresa que aparecerá en las facturas PDF generadas por el sistema.
        </p>

        <?php
        // Mostrar mensaje de éxito si se guardó
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>✓ Configuración guardada correctamente.</strong></p></div>';
        }
        ?>

        <form method="post" action="options.php">
            <?php settings_fields('automatiza_invoice_settings'); ?>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px;">
                
                <h2 style="color: #0096C7; border-bottom: 2px solid #0096C7; padding-bottom: 10px;">
                    📋 Información de la Empresa
                </h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="company_name">Nombre de la Empresa</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_name" 
                                       name="company_name" 
                                       value="<?php echo esc_attr($company_name); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: AutomatizaTech SpA">
                                <p class="description">Razón social o nombre comercial de tu empresa.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="company_rut">RUT</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_rut" 
                                       name="company_rut" 
                                       value="<?php echo esc_attr($company_rut); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: 77.123.456-7">
                                <p class="description">RUT de la empresa (con puntos y guión).</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="company_giro">Giro Comercial</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_giro" 
                                       name="company_giro" 
                                       value="<?php echo esc_attr($company_giro); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: Servicios tecnológicos">
                                <p class="description">Actividad económica principal de la empresa.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="company_address">Dirección</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_address" 
                                       name="company_address" 
                                       value="<?php echo esc_attr($company_address); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: Av. Providencia 123, Santiago">
                                <p class="description">Dirección física de la empresa.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="color: #00BFB3; border-bottom: 2px solid #00BFB3; padding-bottom: 10px; margin-top: 40px;">
                    📞 Datos de Contacto
                </h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="company_email">Email</label>
                            </th>
                            <td>
                                <input type="email" 
                                       id="company_email" 
                                       name="company_email" 
                                       value="<?php echo esc_attr($company_email); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: contacto@tuempresa.com">
                                <p class="description">Email de contacto principal.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="company_phone">Teléfono</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_phone" 
                                       name="company_phone" 
                                       value="<?php echo esc_attr($company_phone); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: +56 9 1234 5678">
                                <p class="description">Número de teléfono con código de país.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="company_website">Sitio Web</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_website" 
                                       name="company_website" 
                                       value="<?php echo esc_attr($company_website); ?>" 
                                       class="regular-text"
                                       placeholder="Ej: www.tuempresa.com">
                                <p class="description">URL del sitio web (sin http://).</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #0096C7; margin-top: 30px;">
                    <p style="margin: 0; color: #1976d2;">
                        <strong>ℹ️ Nota:</strong> Estos datos se mostrarán en todas las facturas PDF generadas por el sistema. 
                        Asegúrate de que la información sea correcta antes de guardar.
                    </p>
                </div>

                <?php submit_button('Guardar Configuración', 'primary large', 'submit', false); ?>

            </div>
        </form>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px;">
            <h3 style="color: #666;">📄 Vista Previa</h3>
            <p>Los datos configurados aparecerán en las facturas de la siguiente manera:</p>
            
            <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                <strong style="color: #0096C7;">HEADER:</strong><br>
                <?php echo esc_html($company_name); ?><br>
                RUT: <?php echo esc_html($company_rut); ?><br>
                <?php echo esc_html($company_email); ?><br>
                <?php echo esc_html($company_phone); ?><br>
                <?php echo esc_html($company_website); ?><br><br>
                
                <strong style="color: #00BFB3;">FOOTER:</strong><br>
                RUT: <?php echo esc_html($company_rut); ?><br>
                Giro: <?php echo esc_html($company_giro); ?><br>
                <?php echo esc_html($company_website); ?>/validar
            </div>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <p style="margin: 0; color: #856404;">
                <strong>⚠️ Importante:</strong> Después de modificar estos datos, se recomienda regenerar las facturas anteriores 
                si deseas que reflejen la nueva información.
            </p>
        </div>

    </div>

    <style>
        .form-table th {
            width: 200px;
            font-weight: 600;
        }
        .form-table input[type="text"],
        .form-table input[type="email"] {
            width: 100%;
            max-width: 500px;
        }
        .form-table .description {
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        /* ========== RESPONSIVE STYLES - INVOICE SETTINGS ========== */
        
        /* Tablet (768px - 1024px) */
        @media screen and (max-width: 1024px) {
            .form-table th {
                width: 150px;
            }
            .form-table input[type="text"],
            .form-table input[type="email"] {
                max-width: 400px;
            }
        }
        
        /* Mobile (hasta 767px) */
        @media screen and (max-width: 767px) {
            .wrap {
                padding: 10px !important;
                margin-left: 0 !important;
            }
            .wrap h1 {
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .wrap h1 .dashicons {
                font-size: 24px !important;
            }
            .wrap > p {
                font-size: 13px !important;
            }
            
            /* Form container */
            .wrap > form > div,
            .wrap > div {
                padding: 15px !important;
                margin: 10px 0 !important;
            }
            
            /* Section headers */
            .wrap h2 {
                font-size: 16px;
                padding-bottom: 8px !important;
            }
            .wrap h3 {
                font-size: 15px;
            }
            
            /* Form table responsive */
            .form-table {
                display: block;
            }
            .form-table tbody {
                display: block;
            }
            .form-table tr {
                display: block;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            .form-table tr:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .form-table th {
                display: block;
                width: 100% !important;
                padding: 0 0 5px 0 !important;
                font-size: 14px;
            }
            .form-table td {
                display: block;
                width: 100%;
                padding: 0 !important;
            }
            .form-table input[type="text"],
            .form-table input[type="email"] {
                width: 100% !important;
                max-width: none !important;
                font-size: 16px !important;
                padding: 12px !important;
                box-sizing: border-box;
            }
            .form-table .description {
                font-size: 12px;
            }
            
            /* Info boxes */
            div[style*="background: #e3f2fd"],
            div[style*="background: #fff3cd"] {
                padding: 12px !important;
                margin: 15px 0 !important;
            }
            div[style*="background: #e3f2fd"] p,
            div[style*="background: #fff3cd"] p {
                font-size: 13px !important;
            }
            
            /* Preview box */
            div[style*="background: #f5f5f5"] {
                padding: 12px !important;
                font-size: 11px !important;
                word-break: break-word;
            }
            
            /* Submit button */
            .button-primary.large {
                width: 100% !important;
                padding: 12px 20px !important;
                font-size: 15px !important;
            }
        }
        
        /* Mobile Small (hasta 480px) */
        @media screen and (max-width: 480px) {
            .wrap {
                padding: 5px !important;
            }
            .wrap h1 {
                font-size: 16px;
            }
            .wrap h2 {
                font-size: 14px;
            }
            
            .wrap > form > div,
            .wrap > div {
                padding: 10px !important;
            }
            
            .form-table input[type="text"],
            .form-table input[type="email"] {
                padding: 10px !important;
            }
        }
        
        /* Touch improvements */
        @media (hover: none) and (pointer: coarse) {
            .form-table input[type="text"],
            .form-table input[type="email"],
            .button-primary.large {
                min-height: 48px;
            }
        }
    </style>
    <?php
}
