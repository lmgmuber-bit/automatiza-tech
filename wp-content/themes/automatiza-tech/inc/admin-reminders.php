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
    
    // Obtener leads futuros
    $leads = $wpdb->get_results("SELECT * FROM $table_name WHERE scheduled_date >= CURDATE() ORDER BY scheduled_date ASC, scheduled_time ASC");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Gestión de Recordatorios Manuales</h1>
        <p>Utiliza este panel para enviar recordatorios manualmente en caso de fallo de las automatizaciones.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Fecha Agendada</th>
                    <th>Hora</th>
                    <th>Estado Asistencia</th>
                    <th>Recordatorio 72h</th>
                    <th>Recordatorio 24h</th>
                    <th>Recordatorio 1h</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr><td colspan="9">No hay agendamientos futuros.</td></tr>
                <?php else: foreach ($leads as $lead): 
                    $scheduled = strtotime($lead->scheduled_date . ' ' . $lead->scheduled_time);
                    $now = current_time('timestamp');
                    $diff_hours = ($scheduled - $now) / 3600;
                    
                    // Determinar estado de botones
                    $can_send_72h = ($diff_hours <= 72 && $diff_hours > 48);
                    $can_send_24h = ($diff_hours <= 24 && $diff_hours > 2);
                    $can_send_1h = ($diff_hours <= 2 && $diff_hours > 0);
                ?>
                    <tr>
                        <td><?php echo $lead->id; ?></td>
                        <td><?php echo esc_html($lead->name); ?></td>
                        <td><?php echo esc_html($lead->email); ?></td>
                        <td><?php echo $lead->scheduled_date; ?></td>
                        <td><?php echo $lead->scheduled_time; ?></td>
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
                                <button class="button action-btn" 
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
                                <button class="button action-btn" 
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
                                <button class="button action-btn" 
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
        <p class="description">Nota: Los botones se habilitan automáticamente cuando el tiempo es el adecuado. Para forzar el envío en cualquier momento, añade <code>&force=true</code> a la URL.</p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.action-btn').click(function() {
            var btn = $(this);
            var lead_id = btn.data('id');
            var type = btn.data('type');
            
            if (!confirm('¿Estás seguro de enviar el recordatorio de ' + type + ' a este usuario?')) return;
            
            btn.prop('disabled', true).text('Enviando...');
            
            $.post(ajaxurl, {
                action: 'send_manual_reminder',
                lead_id: lead_id,
                type: type,
                nonce: '<?php echo wp_create_nonce("manual_reminder_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Correo enviado correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Desconocido'));
                    btn.prop('disabled', false).text('Reintentar');
                }
            });
        });
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
    $confirm_url = "$base_url?id=$lead_id&action=confirm";
    $reject_url = "$base_url?id=$lead_id&action=reject";
    $delete_url = "$base_url?id=$lead_id&action=delete";
    
    $context_msg = "";
    $subject = "";
    
    if ($type === '72h') {
        $subject = "Recordatorio de Agendamiento (72h)";
        $context_msg = "para el <strong>{$lead->scheduled_date}</strong> a las <strong>{$lead->scheduled_time}</strong>";
    } elseif ($type === '24h') {
        $subject = "Recordatorio de Agendamiento (24h)";
        $context_msg = "para mañana <strong>{$lead->scheduled_date}</strong> a las <strong>{$lead->scheduled_time}</strong>";
    } elseif ($type === '1h') {
        $subject = "Recordatorio de Agendamiento (1h)";
        $context_msg = "para hoy <strong>{$lead->scheduled_date}</strong> a las <strong>{$lead->scheduled_time}</strong> (en aproximadamente 1 hora)";
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
