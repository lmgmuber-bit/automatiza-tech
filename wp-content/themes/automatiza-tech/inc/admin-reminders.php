<?php
/**
 * Panel de Administración para Recordatorios Manuales
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agregar menú de administración
 */
function automatiza_tech_reminders_menu() {
    add_menu_page(
        'Gestión de Recordatorios',
        'Agendamientos',
        'manage_options',
        'automatiza-reminders',
        'automatiza_tech_reminders_page',
        'dashicons-calendar-alt',
        25
    );
    
    // Añadir submenú para Recordatorios (para que aparezca con el nombre correcto en el submenú)
    add_submenu_page(
        'automatiza-reminders',
        'Gestión de Recordatorios',
        'Recordatorios',
        'manage_options',
        'automatiza-reminders',
        'automatiza_tech_reminders_page'
    );
}
add_action('admin_menu', 'automatiza_tech_reminders_menu');

/**
 * Renderizar página de administración
 */
function automatiza_tech_reminders_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // --- FILTROS Y ORDENAMIENTO ---
    $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'scheduled_date';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
    
    // Whitelist para ordenamiento
    $allowed_sort_cols = ['id', 'name', 'scheduled_date', 'scheduled_time'];
    if (!in_array($orderby, $allowed_sort_cols)) $orderby = 'scheduled_date';
    if (!in_array(strtoupper($order), ['ASC', 'DESC'])) $order = 'ASC';

    // Obtener fecha y hora actual según zona horaria de WP
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
    $now_dt = new DateTime('now', $tz);
    $current_date_db = $now_dt->format('Y-m-d');
    $current_time_db = $now_dt->format('H:i:s');

    // Construir Query
    $where = "1=1";
    if ($filter_date) {
        $where .= $wpdb->prepare(" AND scheduled_date = %s", $filter_date);
    } else {
        // Por defecto mostrar solo futuros (incluyendo los de hoy que aún no pasan)
        $where .= $wpdb->prepare(" AND (scheduled_date > %s OR (scheduled_date = %s AND scheduled_time > %s))", $current_date_db, $current_date_db, $current_time_db);
    }

    $query = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order, scheduled_time ASC";
    $leads = $wpdb->get_results($query);
    
    // Helper para URLs de ordenamiento
    $get_sort_url = function($col) use ($orderby, $order, $filter_date) {
        $new_order = ($orderby === $col && $order === 'ASC') ? 'DESC' : 'ASC';
        $url = add_query_arg(array(
            'orderby' => $col,
            'order' => $new_order
        ));
        if ($filter_date) $url = add_query_arg('filter_date', $filter_date, $url);
        return esc_url($url);
    };

    // Icono de ordenamiento
    $sort_icon = function($col) use ($orderby, $order) {
        if ($orderby !== $col) return '';
        return ($order === 'ASC') ? ' &#9650;' : ' &#9660;';
    };
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Gestión de Recordatorios Manuales</h1>
        <p>Utiliza este panel para enviar recordatorios manualmente en caso de fallo de las automatizaciones.</p>
        
        <!-- BARRA DE HERRAMIENTAS -->
        <div class="tablenav top" style="height: auto; padding-bottom: 10px;">
            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="page" value="automatiza-reminders">
                    <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>" style="height: 30px; line-height: normal;">
                    <input type="submit" class="button" value="Filtrar por Fecha">
                    <?php if ($filter_date): ?>
                        <a href="<?php echo admin_url('admin.php?page=automatiza-reminders'); ?>" class="button">Limpiar Filtro</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="alignright actions">
                <strong>Acciones Masivas (Visibles): </strong>
                <button class="button button-primary bulk-send-btn" data-type="72h">Enviar Todos (72h)</button>
                <button class="button button-primary bulk-send-btn" data-type="24h">Enviar Todos (24h)</button>
                <button class="button button-primary bulk-send-btn" data-type="1h">Enviar Todos (1h)</button>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><a href="<?php echo $get_sort_url('id'); ?>">ID<?php echo $sort_icon('id'); ?></a></th>
                    <th><a href="<?php echo $get_sort_url('name'); ?>">Nombre<?php echo $sort_icon('name'); ?></a></th>
                    <th>Email</th>
                    <th><a href="<?php echo $get_sort_url('scheduled_date'); ?>">Fecha Agendada<?php echo $sort_icon('scheduled_date'); ?></a></th>
                    <th><a href="<?php echo $get_sort_url('scheduled_time'); ?>">Hora<?php echo $sort_icon('scheduled_time'); ?></a></th>
                    <th>Estado Asistencia</th>
                    <th>Recordatorio 72h</th>
                    <th>Recordatorio 24h</th>
                    <th>Recordatorio 1h</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr><td colspan="9">No hay agendamientos para los criterios seleccionados.</td></tr>
                <?php else: foreach ($leads as $lead): 
                    // Cálculo robusto usando la zona horaria configurada en WordPress (ej. Chile)
                    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
                    $scheduled_dt = new DateTime($lead->scheduled_date . ' ' . $lead->scheduled_time, $tz);
                    $now_dt = new DateTime('now', $tz);
                    
                    $diff_seconds = $scheduled_dt->getTimestamp() - $now_dt->getTimestamp();
                    $diff_hours = $diff_seconds / 3600;
                    
                    // Determinar estado de botones
                    $can_send_72h = ($diff_hours <= 72 && $diff_hours > 48);
                    $can_send_24h = ($diff_hours <= 24 && $diff_hours > 2);
                    $can_send_1h = ($diff_hours <= 2 && $diff_hours > 0);
                ?>
                    <tr>
                        <td><?php echo $lead->id; ?></td>
                        <td><?php echo esc_html($lead->name); ?></td>
                        <td><?php echo esc_html($lead->email); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($lead->scheduled_date)); ?></td>
                        <td><?php echo substr($lead->scheduled_time, 0, 5); ?></td>
                        <td>
                            <?php 
                            if ($lead->confirmed_attendance === '1') echo '<span style="color:green;font-weight:bold;">Confirmado</span>';
                            elseif ($lead->confirmed_attendance === '0') echo '<span style="color:red;font-weight:bold;">Rechazado</span>';
                            else echo '<span style="color:gray;">Pendiente</span>';
                            ?>
                        </td>
                        <td>
                            <?php if ($lead->recordatorio72h): ?>
                                <span class="dashicons dashicons-yes" style="color:green;"></span> Enviado
                            <?php else: ?>
                                <button class="button action-btn btn-72h" 
                                        data-id="<?php echo $lead->id; ?>" 
                                        data-type="72h"
                                        <?php echo (!$can_send_72h && !isset($_GET['force'])) ? 'disabled title="Fuera de rango (48h-72h)"' : ''; ?>>
                                    Enviar 72h
                                </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lead->recordatorio24h): ?>
                                <span class="dashicons dashicons-yes" style="color:green;"></span> Enviado
                            <?php else: ?>
                                <button class="button action-btn btn-24h" 
                                        data-id="<?php echo $lead->id; ?>" 
                                        data-type="24h"
                                        <?php echo (!$can_send_24h && !isset($_GET['force'])) ? 'disabled title="Fuera de rango (2h-24h)"' : ''; ?>>
                                    Enviar 24h
                                </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lead->recordatorio1h): ?>
                                <span class="dashicons dashicons-yes" style="color:green;"></span> Enviado
                            <?php else: ?>
                                <button class="button action-btn btn-1h" 
                                        data-id="<?php echo $lead->id; ?>" 
                                        data-type="1h"
                                        <?php echo (!$can_send_1h && !isset($_GET['force'])) ? 'disabled title="Fuera de rango (0h-2h)"' : ''; ?>>
                                    Enviar 1h
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <!-- Progress Modal -->
        <div id="bulk-progress-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:white; padding:20px; border-radius:5px; width:300px; text-align:center;">
                <h3>Enviando Correos...</h3>
                <p id="bulk-progress-text">0 / 0</p>
                <div style="width:100%; background:#eee; height:10px; border-radius:5px; overflow:hidden;">
                    <div id="bulk-progress-bar" style="width:0%; background:#2271b1; height:100%; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        <p class="description">Nota: Los botones se habilitan automáticamente cuando el tiempo es el adecuado. Para forzar el envío en cualquier momento, añade <code>&force=true</code> a la URL.</p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Envío Individual
        $('.action-btn').click(function() {
            var btn = $(this);
            var lead_id = btn.data('id');
            var type = btn.data('type');
            
            if (!confirm('¿Estás seguro de enviar el recordatorio de ' + type + ' a este usuario?')) return;
            
            sendReminder(btn, lead_id, type, function(success) {
                if (success) {
                    alert('Correo enviado correctamente');
                    location.reload();
                }
            });
        });

        // Envío Masivo
        $('.bulk-send-btn').click(function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            
            // Seleccionar botones habilitados de ese tipo
            var buttons = $('.action-btn.btn-' + type + ':not(:disabled)');
            
            if (buttons.length === 0) {
                alert('No hay correos pendientes o habilitados para enviar en la categoría ' + type + '.');
                return;
            }

            if (!confirm('Se enviarán ' + buttons.length + ' correos de tipo ' + type + '. ¿Deseas continuar?')) return;

            // Iniciar proceso masivo
            $('#bulk-progress-modal').css('display', 'flex');
            var total = buttons.length;
            var current = 0;
            
            function processNext() {
                if (current >= total) {
                    alert('Proceso completado.');
                    location.reload();
                    return;
                }

                var btn = $(buttons[current]);
                var lead_id = btn.data('id');
                
                $('#bulk-progress-text').text((current + 1) + ' / ' + total);
                $('#bulk-progress-bar').css('width', ((current + 1) / total * 100) + '%');

                sendReminder(btn, lead_id, type, function() {
                    current++;
                    processNext();
                });
            }

            processNext();
        });

        // Función Helper AJAX
        function sendReminder(btn, lead_id, type, callback) {
            btn.prop('disabled', true).text('...');
            
            $.post(ajaxurl, {
                action: 'send_manual_reminder',
                lead_id: lead_id,
                type: type,
                nonce: '<?php echo wp_create_nonce("manual_reminder_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    btn.text('Enviado').removeClass('button-primary').addClass('button-disabled');
                    // Actualizar visualmente la fila si es necesario
                    callback(true);
                } else {
                    console.error('Error ID ' + lead_id + ': ' + (response.data || 'Unknown'));
                    btn.prop('disabled', false).text('Reintentar');
                    callback(false);
                }
            }).fail(function() {
                btn.prop('disabled', false).text('Error');
                callback(false);
            });
        }
    });
    </script>
    <?php
}

/**
 * AJAX Handler para envío manual
 */
function automatiza_tech_send_manual_reminder() {
    check_ajax_referer('manual_reminder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $lead_id = intval($_POST['lead_id']);
    $type = sanitize_text_field($_POST['type']);
    
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    
    if (!$lead) {
        wp_send_json_error('Lead no encontrado');
    }
    
    // Construir contenido del correo
    $base_url = 'https://automatizatech.shop/wp-json/automatiza-tech/v1/leads/action';
    $token_param = '&token=' . $lead->token;
    $confirm_url = "$base_url?id=$lead_id&action=confirm$token_param";
    $reject_url = "$base_url?id=$lead_id&action=reject$token_param";
    $delete_url = "$base_url?id=$lead_id&action=delete$token_param";
    
    $context_msg = "";
    $subject = "";
    
    // Formatear fecha y hora (DD-MM-YYYY y HH:mm)
    $formatted_date = date('d-m-Y', strtotime($lead->scheduled_date));
    $formatted_time = substr($lead->scheduled_time, 0, 5);

    if ($type === '72h') {
        $subject = "Recordatorio de Agendamiento (72h)";
        $context_msg = "para el <strong>{$formatted_date}</strong> a las <strong>{$formatted_time}</strong>";
    } elseif ($type === '24h') {
        $subject = "Recordatorio de Agendamiento (24h)";
        $context_msg = "para mañana <strong>{$formatted_date}</strong> a las <strong>{$formatted_time}</strong>";
    } elseif ($type === '1h') {
        $subject = "Recordatorio de Agendamiento (1h)";
        $context_msg = "para hoy <strong>{$formatted_date}</strong> a las <strong>{$formatted_time}</strong> (en aproximadamente 1 hora)";
    } else {
        wp_send_json_error('Tipo inválido');
    }
    
    // Obtener logo y nombre del sitio
    $site_title = get_bloginfo('name');
    $logo_url = 'https://automatizatech.shop/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $footer_text = get_bloginfo('description');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333333; }
            .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
            .header { background-color: #1e40af; padding: 40px 20px; text-align: center; }
            .header img { max-height: 80px; width: auto; margin-bottom: 15px; }
            .header h1 { margin: 0; font-size: 24px; color: #ffffff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            .content { padding: 40px 30px; line-height: 1.6; }
            .cta-container { text-align: center; margin: 30px 0; }
            .btn { display: inline-block; padding: 12px 24px; margin: 5px; color: #ffffff !important; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 14px; transition: all 0.3s ease; }
            .btn-confirm { background-color: #06d6a0; box-shadow: 0 4px 6px rgba(6, 214, 160, 0.3); }
            .btn-reject { background-color: #fca311; color: #fff !important; box-shadow: 0 4px 6px rgba(252, 163, 17, 0.3); }
            .footer { background-color: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #888888; }
            .footer a { color: #1e40af; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '">
                <h1>Recordatorio de Reunión</h1>
            </div>
            <div class="content">
                <p>Hola <strong>' . esc_html($lead->name) . '</strong>,</p>
                <p>Esperamos que estés teniendo un excelente día.</p>
                <p>Te escribimos para recordarte tu cita agendada ' . $context_msg . '.</p>
                <p>Para ayudarnos a organizar mejor nuestra agenda, te agradecemos confirmar tu asistencia haciendo clic en uno de los siguientes botones:</p>
                
                <div class="cta-container">
                    <a href="' . $confirm_url . '" class="btn btn-confirm">Confirmar Asistencia</a>
                    <a href="' . $reject_url . '" class="btn btn-reject">No podré asistir</a>
                </div>
                
                <p style="font-size: 13px; color: #666; margin-top: 20px; text-align: center;">
                    Si necesitas cancelar definitivamente, puedes hacerlo aquí: <a href="' . $delete_url . '" style="color: #dc3545;">Cancelar Cita</a>
                </p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . esc_html($site_title) . '. Todos los derechos reservados.</p>
                <p>' . esc_html($footer_text) . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $sent = wp_mail($lead->email, $subject, $html, $headers);
    
    if ($sent) {
        // Actualizar estado en DB
        $column = 'recordatorio' . $type;
        $wpdb->update($table_name, array($column => 1), array('id' => $lead_id));
        wp_send_json_success();
    } else {
        wp_send_json_error('Fallo al enviar el correo (wp_mail returned false)');
    }
}
add_action('wp_ajax_send_manual_reminder', 'automatiza_tech_send_manual_reminder');
