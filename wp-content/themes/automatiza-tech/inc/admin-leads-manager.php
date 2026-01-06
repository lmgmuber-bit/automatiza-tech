<?php
if (!defined('ABSPATH')) exit;

function automatiza_tech_leads_manager_menu() {
    add_submenu_page(
        'automatiza-reminders', // Parent slug (from admin-reminders.php)
        'Gestión de Citas',
        'Todas las Citas',
        'manage_options',
        'automatiza-leads-manager',
        'automatiza_tech_leads_manager_page'
    );
}
add_action('admin_menu', 'automatiza_tech_leads_manager_menu');

function automatiza_tech_leads_manager_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $logs_table_name = $wpdb->prefix . 'automatiza_leads_logs';

    // Handle Actions
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Eliminar TODAS las citas
    if ($action == 'delete_all' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_all_leads')) {
        $confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';
        if ($confirm === 'yes') {
            // Obtener todas las citas para el log
            $all_leads = $wpdb->get_results("SELECT * FROM $table_name");
            $count = count($all_leads);
            
            // Registrar en logs
            foreach ($all_leads as $lead) {
                $wpdb->insert($logs_table_name, array(
                    'original_lead_id' => $lead->id,
                    'deleted_at' => current_time('mysql'),
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'reason' => 'Eliminación masiva desde Admin'
                ));
            }
            
            // Eliminar todas
            $wpdb->query("TRUNCATE TABLE $table_name");
            
            echo '<div class="notice notice-success"><p>🗑️ Se eliminaron <strong>' . $count . '</strong> citas correctamente.</p></div>';
        }
    }

    if ($action == 'delete' && $id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_lead_' . $id)) {
        // Delete Logic
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if ($lead) {
            $wpdb->insert($logs_table_name, array(
                'original_lead_id' => $lead->id,
                'deleted_at' => current_time('mysql'),
                'name' => $lead->name,
                'email' => $lead->email,
                'reason' => 'Eliminado desde Admin'
            ));
            $wpdb->delete($table_name, array('id' => $id));
            echo '<div class="notice notice-success"><p>Cita eliminada correctamente.</p></div>';
        }
    }

    // Variable para almacenar error de validación y mostrar en formulario
    $validation_error_msg = null;
    $form_data = null; // Datos del formulario si hay error
    
    if ($action == 'edit' && isset($_POST['submit_edit']) && check_admin_referer('edit_lead_' . $id)) {
        // Obtener datos originales para comparar
        $original_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        
        $new_date = sanitize_text_field($_POST['scheduled_date']);
        $new_time = sanitize_text_field($_POST['scheduled_time']);
        
        // Detectar si cambió fecha u hora
        $date_changed = ($original_lead->scheduled_date !== $new_date);
        $time_changed = (substr($original_lead->scheduled_time, 0, 5) !== substr($new_time, 0, 5));
        
        // === VALIDACIONES SI CAMBIÓ FECHA U HORA ===
        $validation_error = null;
        
        if ($date_changed || $time_changed) {
            $validation_error = automatiza_tech_validate_appointment_datetime($new_date, $new_time, $id);
        }
        
        if ($validation_error) {
            $validation_error_msg = $validation_error;
            // Guardar datos del formulario para mostrarlos de nuevo
            $form_data = (object) array(
                'id' => $id,
                'name' => sanitize_text_field($_POST['name']),
                'email' => sanitize_email($_POST['email']),
                'phone' => sanitize_text_field($_POST['phone']),
                'scheduled_date' => $new_date,
                'scheduled_time' => $new_time,
                'confirmed_attendance' => sanitize_text_field($_POST['confirmed_attendance']) === '' ? null : intval($_POST['confirmed_attendance']),
                'status' => $original_lead->status,
                'service_interest' => $original_lead->service_interest,
                'notes' => $original_lead->notes,
                'plan' => $original_lead->plan
            );
            // Mantenemos $action = 'edit' para mostrar el formulario de nuevo
        } else {
            // Update Logic
            $data = array(
                'name' => sanitize_text_field($_POST['name']),
                'email' => sanitize_email($_POST['email']),
                'phone' => sanitize_text_field($_POST['phone']),
                'scheduled_date' => $new_date,
                'scheduled_time' => $new_time,
                'confirmed_attendance' => sanitize_text_field($_POST['confirmed_attendance']) === '' ? null : intval($_POST['confirmed_attendance'])
            );
            
            $wpdb->update($table_name, $data, array('id' => $id));
            
            // Si cambió fecha u hora, enviar email de reprogramación
            if ($date_changed || $time_changed) {
                $updated_lead = (object) array_merge((array) $original_lead, $data);
                $email_sent = automatiza_tech_send_reschedule_email($updated_lead, $original_lead);
                
                if ($email_sent) {
                    echo '<div class="notice notice-success"><p>✅ Cita actualizada correctamente. 📧 <strong>Email de reprogramación enviado.</strong></p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>✅ Cita actualizada correctamente. ⚠️ <strong>No se pudo enviar el email de reprogramación.</strong></p></div>';
                }
            } else {
                echo '<div class="notice notice-success"><p>✅ Cita actualizada correctamente.</p></div>';
            }
            
            $action = 'list'; // Go back to list
        }
    }

    // Views
    if ($action == 'edit' && $id > 0) {
        // Si hay datos del formulario con error, usarlos; sino cargar de BD
        if ($form_data) {
            $lead = $form_data;
        } else {
            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        }
        if (!$lead) {
            echo '<div class="notice notice-error"><p>Cita no encontrada.</p></div>';
            return;
        }
        $is_cancelled = ($lead->status === 'cancelled');
        ?>
        <div class="wrap">
            <h1>Editar Cita #<?php echo $lead->id; ?> <?php if ($is_cancelled): ?><span style="color:#d63638;">(Cancelada)</span><?php endif; ?></h1>
            
            <?php if ($validation_error_msg): ?>
            <div class="notice notice-error">
                <p>❌ <strong>Error de validación:</strong> <?php echo esc_html($validation_error_msg); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($is_cancelled): ?>
            <div class="notice notice-warning">
                <p>⚠️ Esta cita fue <strong>cancelada</strong> el <?php echo date('d/m/Y H:i', strtotime($lead->cancelled_at)); ?>. Si la editas y cambias fecha/hora, se enviará un email de reprogramación.</p>
            </div>
            <?php endif; ?>
            
            <div style="background: #f0f6fc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
                <strong>💡 Nota:</strong> Si cambias la <strong>fecha</strong> o la <strong>hora</strong>, se enviará automáticamente un correo al cliente notificando la reprogramación.
            </div>
            
            <form method="post" action="?page=automatiza-leads-manager&action=edit&id=<?php echo $lead->id; ?>">
                <?php wp_nonce_field('edit_lead_' . $lead->id); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="name">Nombre</label></th>
                        <td><input type="text" name="name" id="name" value="<?php echo esc_attr($lead->name); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" name="email" id="email" value="<?php echo esc_attr($lead->email); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="phone">Teléfono</label></th>
                        <td><input type="text" name="phone" id="phone" value="<?php echo esc_attr($lead->phone); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="scheduled_date">Fecha</label></th>
                        <td>
                            <input type="date" name="scheduled_date" id="scheduled_date" value="<?php echo esc_attr($lead->scheduled_date); ?>" required>
                            <span style="color:#666; margin-left:10px;">Actual: <?php echo date('d/m/Y', strtotime($lead->scheduled_date)); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="scheduled_time">Hora</label></th>
                        <td>
                            <input type="time" name="scheduled_time" id="scheduled_time" value="<?php echo esc_attr(substr($lead->scheduled_time, 0, 5)); ?>" required>
                            <span style="color:#666; margin-left:10px;">Actual: <?php echo substr($lead->scheduled_time, 0, 5); ?> hrs</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="confirmed_attendance">Estado Asistencia</label></th>
                        <td>
                            <select name="confirmed_attendance" id="confirmed_attendance">
                                <option value="" <?php selected($lead->confirmed_attendance, null); ?>>Pendiente</option>
                                <option value="1" <?php selected($lead->confirmed_attendance, '1'); ?>>Confirmado/Asistió</option>
                                <option value="0" <?php selected($lead->confirmed_attendance, '0'); ?>>Rechazado/No Asistió</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit_edit" id="submit" class="button button-primary" value="💾 Guardar Cambios">
                    <a href="?page=automatiza-leads-manager" class="button">Cancelar</a>
                </p>
            </form>
        </div>
        <?php
    } else {
        // Filtros
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
        
        // Construir Query con filtros
        $where = "1=1";
        
        if ($filter_status === 'cancelled') {
            $where .= " AND status = 'cancelled'";
        } elseif ($filter_status === 'active') {
            $where .= " AND (status IS NULL OR status != 'cancelled')";
        }
        
        if ($filter_date) {
            $where .= $wpdb->prepare(" AND scheduled_date = %s", $filter_date);
        }
        
        // List View
        $leads = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY scheduled_date DESC, scheduled_time DESC");
        
        // Contar por estado (sin filtros)
        $total_all = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_cancelled = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'cancelled'");
        $total_active = $total_all - $total_cancelled;
        
        // Helper para iconos de canal - detecta WhatsApp por session_id
        $get_channel_icon = function($source, $session_id = '') {
            $channels = array(
                'web' => array('icon' => '🌐', 'label' => 'Web'),
                'whatsapp' => array('icon' => '📱', 'label' => 'WhatsApp'),
                'instagram' => array('icon' => '📸', 'label' => 'Instagram'),
                'messenger' => array('icon' => '💬', 'label' => 'Messenger'),
                'phone' => array('icon' => '📞', 'label' => 'Teléfono'),
            );
            
            $source = strtolower($source ?? 'web');
            
            // Detectar si viene de WhatsApp por el session_id
            // Los session_id de WhatsApp contienen "whatsapp" (ej: whatsapp_56912345678)
            if ($source === 'web' && !empty($session_id)) {
                if (strpos(strtolower($session_id), 'whatsapp') !== false) {
                    $source = 'whatsapp';
                }
            }
            
            $channel = isset($channels[$source]) ? $channels[$source] : array('icon' => '❓', 'label' => ucfirst($source));
            return $channel['icon'] . ' ' . $channel['label'];
        };
        
        $total_leads = count($leads);
        $delete_all_url = wp_nonce_url(
            admin_url('admin.php?page=automatiza-leads-manager&action=delete_all&confirm=yes'),
            'delete_all_leads'
        );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">📋 Todas las Citas (Historial)</h1>
            
            <!-- Resumen de estadísticas -->
            <div style="display: flex; gap: 15px; margin: 20px 0;">
                <div style="background: #fff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #2271b1;">
                    <strong style="color: #2271b1;">📊 Total:</strong> <?php echo $total_all; ?> citas
                </div>
                <div style="background: #fff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #00a32a;">
                    <strong style="color: #00a32a;">✅ Activas:</strong> <?php echo $total_active; ?>
                </div>
                <div style="background: #fff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #d63638;">
                    <strong style="color: #d63638;">❌ Canceladas:</strong> <?php echo $total_cancelled; ?>
                </div>
            </div>
            
            <!-- Barra de filtros y acciones -->
            <div class="tablenav top" style="margin-top:15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="automatiza-leads-manager">
                    
                    <label style="display:flex; align-items:center; gap:5px;">
                        Estado:
                        <select name="filter_status" style="height: 30px;">
                            <option value="">Todos</option>
                            <option value="active" <?php selected($filter_status, 'active'); ?>>✅ Activas</option>
                            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>❌ Canceladas</option>
                        </select>
                    </label>
                    
                    <label style="display:flex; align-items:center; gap:5px;">
                        📅 <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>" style="height: 30px;">
                    </label>
                    
                    <input type="submit" class="button" value="Filtrar">
                    <?php if ($filter_status || $filter_date): ?>
                        <a href="<?php echo admin_url('admin.php?page=automatiza-leads-manager'); ?>" class="button">Limpiar</a>
                    <?php endif; ?>
                </form>
                
                <div>
                    <?php if ($total_leads > 0): ?>
                    <a href="<?php echo esc_url($delete_all_url); ?>" 
                       class="button button-link-delete" 
                       style="color:#b32d2e; border-color:#b32d2e;"
                       onclick="return confirm('⚠️ ADVERTENCIA: Esto eliminará TODAS las <?php echo $total_all; ?> citas.\n\n¿Estás SEGURO de que deseas eliminar todas las citas?\n\nEsta acción NO se puede deshacer.');">
                        🗑️ Eliminar Todas las Citas
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <p style="margin-top:10px; color:#666;">Vista completa de todas las citas incluyendo activas, pasadas y canceladas. <?php echo $total_leads; ?> resultados encontrados.</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th style="width:90px;">Canal</th>
                        <th style="width:100px;">Fecha</th>
                        <th style="width:70px;">Hora</th>
                        <th style="width:100px;">Estado</th>
                        <th style="width:120px;">Asistencia</th>
                        <th style="width:150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="10">No hay citas para los filtros seleccionados.</td></tr>
                    <?php else: foreach ($leads as $lead): 
                        // Verificar si la cita ya pasó
                        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('America/Santiago');
                        $scheduled_dt = new DateTime($lead->scheduled_date . ' ' . $lead->scheduled_time, $tz);
                        $now_dt = new DateTime('now', $tz);
                        $is_past = $scheduled_dt < $now_dt;
                        $is_cancelled = ($lead->status === 'cancelled');
                        $row_style = $is_cancelled ? 'background-color: #fff0f0;' : '';
                    ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td><?php echo $lead->id; ?></td>
                            <td><?php echo esc_html($lead->name); ?></td>
                            <td><?php echo esc_html($lead->email); ?></td>
                            <td><?php echo esc_html($lead->phone); ?></td>
                            <td><?php echo $get_channel_icon($lead->source ?? 'web', $lead->session_id ?? ''); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($lead->scheduled_date)); ?></td>
                            <td><?php echo substr($lead->scheduled_time, 0, 5); ?></td>
                            <td>
                                <?php if ($is_cancelled): ?>
                                    <span style="color:#d63638; font-weight:bold;">❌ Cancelada</span>
                                    <?php if (!empty($lead->cancelled_at)): ?>
                                        <br><small style="color:#666;"><?php echo date('d/m/Y H:i', strtotime($lead->cancelled_at)); ?></small>
                                    <?php endif; ?>
                                <?php elseif ($is_past): ?>
                                    <span style="color:#666;">📅 Pasada</span>
                                <?php else: ?>
                                    <span style="color:#00a32a;">✅ Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lead->confirmed_attendance === '1'): ?>
                                    <span style="color:green; font-weight:bold;">✓ Asistió</span>
                                <?php elseif ($lead->confirmed_attendance === '0'): ?>
                                    <span style="color:red; font-weight:bold;">✗ No Asistió</span>
                                    <?php if (!empty($lead->no_show_email_sent)): ?>
                                        <br><small style="color:#666;">📧 Enviado</small>
                                    <?php endif; ?>
                                <?php elseif ($is_past && !$is_cancelled): ?>
                                    <div class="attendance-buttons" data-id="<?php echo $lead->id; ?>">
                                        <button type="button" class="button button-small btn-attended" style="background:#46b450; color:white; border-color:#46b450;" title="Marcar como Asistió">✓</button>
                                        <button type="button" class="button button-small btn-no-show" style="background:#dc3232; color:white; border-color:#dc3232;" title="No Asistió (envía correo)">✗</button>
                                    </div>
                                <?php elseif ($is_cancelled): ?>
                                    <span style="color:#999;">-</span>
                                <?php else: ?>
                                    <span style="color:gray;">⏳ Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=automatiza-leads-manager&action=edit&id=<?php echo $lead->id; ?>" class="button button-small">Editar</a>
                                <a href="?page=automatiza-leads-manager&action=delete&id=<?php echo $lead->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_lead_' . $lead->id); ?>" class="button button-small button-link-delete" onclick="return confirm('¿Estás seguro de eliminar esta cita?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            
            <!-- JavaScript para botones de asistencia -->
            <script>
            jQuery(document).ready(function($) {
                // Botón Asistió
                $('.btn-attended').click(function() {
                    var btn = $(this);
                    var container = btn.closest('.attendance-buttons');
                    var leadId = container.data('id');
                    
                    if (!confirm('¿Confirmar que el cliente ASISTIÓ a la cita?')) return;
                    
                    btn.prop('disabled', true).text('...');
                    
                    $.post(ajaxurl, {
                        action: 'mark_attendance',
                        lead_id: leadId,
                        attended: 1,
                        _wpnonce: '<?php echo wp_create_nonce('mark_attendance'); ?>'
                    }, function(response) {
                        if (response.success) {
                            container.html('<span style="color:green; font-weight:bold;">✓ Asistió</span>');
                        } else {
                            alert('Error: ' + response.data);
                            btn.prop('disabled', false).text('✓');
                        }
                    });
                });
                
                // Botón No Asistió
                $('.btn-no-show').click(function() {
                    var btn = $(this);
                    var container = btn.closest('.attendance-buttons');
                    var leadId = container.data('id');
                    
                    if (!confirm('¿Confirmar que el cliente NO ASISTIÓ?\n\nSe enviará un correo corporativo invitándolo a reagendar.')) return;
                    
                    btn.prop('disabled', true).text('...');
                    container.find('.btn-attended').prop('disabled', true);
                    
                    $.post(ajaxurl, {
                        action: 'mark_attendance',
                        lead_id: leadId,
                        attended: 0,
                        send_email: 1,
                        _wpnonce: '<?php echo wp_create_nonce('mark_attendance'); ?>'
                    }, function(response) {
                        if (response.success) {
                            container.html('<span style="color:red; font-weight:bold;">✗ No Asistió</span><br><small style="color:#666;">📧 Correo enviado</small>');
                        } else {
                            alert('Error: ' + response.data);
                            btn.prop('disabled', false).text('✗');
                            container.find('.btn-attended').prop('disabled', false);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
}

/**
 * AJAX Handler para marcar asistencia
 */
add_action('wp_ajax_mark_attendance', 'automatiza_tech_mark_attendance');
function automatiza_tech_mark_attendance() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'mark_attendance')) {
        wp_send_json_error('Sesión expirada. Recarga la página.');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $lead_id = intval($_POST['lead_id']);
    $attended = intval($_POST['attended']);
    $send_email = isset($_POST['send_email']) && $_POST['send_email'] == '1';
    
    // Obtener datos del lead
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    
    if (!$lead) {
        wp_send_json_error('Cita no encontrada');
    }
    
    // Actualizar estado de asistencia
    $update_data = array(
        'confirmed_attendance' => $attended ? '1' : '0'
    );
    
    // Si no asistió y debe enviar email
    if (!$attended && $send_email) {
        $email_sent = automatiza_tech_send_no_show_email($lead);
        if ($email_sent) {
            $update_data['no_show_email_sent'] = current_time('mysql');
        }
    }
    
    $updated = $wpdb->update($table_name, $update_data, array('id' => $lead_id));
    
    if ($updated !== false) {
        wp_send_json_success('Actualizado correctamente');
    } else {
        wp_send_json_error('Error al actualizar');
    }
}

/**
 * Enviar correo corporativo cuando no asistió
 */
function automatiza_tech_send_no_show_email($lead) {
    $to = $lead->email;
    $subject = '😔 Lamentamos no haberte visto - Te invitamos a reagendar | Automatiza Tech';
    
    $name = $lead->name;
    $scheduled_date = date('d/m/Y', strtotime($lead->scheduled_date));
    $scheduled_time = substr($lead->scheduled_time, 0, 5);
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="Automatiza Tech" style="max-width: 200px;">
        </div>
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 24px;">😔 Te echamos de menos</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Esperábamos poder conversar contigo</p>
        </div>
        
        <p>Hola <strong>' . esc_html($name) . '</strong>,</p>
        
        <p>Notamos que no pudiste asistir a nuestra reunión programada para el <strong>' . $scheduled_date . '</strong> a las <strong>' . $scheduled_time . ' hrs</strong>.</p>
        
        <p>Entendemos que pueden surgir imprevistos, ¡no te preocupes! Nos encantaría tener la oportunidad de conversar contigo sobre cómo la <strong>automatización</strong> puede transformar tu negocio.</p>
        
        <div style="background: #f8f9fa; padding: 25px; border-radius: 10px; margin: 25px 0;">
            <h3 style="color: #667eea; margin-top: 0;">🚀 ¿Por qué automatizar tu negocio?</h3>
            <ul style="padding-left: 20px;">
                <li><strong>Ahorra tiempo:</strong> Automatiza tareas repetitivas y enfócate en lo importante</li>
                <li><strong>Reduce errores:</strong> Los procesos automatizados son más precisos</li>
                <li><strong>Mejora la experiencia:</strong> Respuestas instantáneas 24/7 para tus clientes</li>
                <li><strong>Escala tu negocio:</strong> Crece sin aumentar proporcionalmente tus costos</li>
                <li><strong>WhatsApp Business:</strong> Atiende a tus clientes donde están</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="https://automatizatech.cl/agenda-reunion/" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px;">
                📅 Reagendar Mi Reunión
            </a>
        </div>
        
        <div style="background: #e8f5e9; padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #4caf50;">
            <h4 style="color: #2e7d32; margin-top: 0;">💡 Nuestros Servicios</h4>
            <p style="margin-bottom: 0;">
                • Chatbots inteligentes para WhatsApp<br>
                • Automatización de agendamientos<br>
                • Integración con Google Calendar<br>
                • Recordatorios automáticos<br>
                • Flujos de atención personalizados
            </p>
        </div>
        
        <p>Si tienes alguna pregunta o prefieres que te contactemos, simplemente responde a este correo o escríbenos por WhatsApp.</p>
        
        <p>¡Esperamos verte pronto!</p>
        
        <p style="margin-top: 30px;">
            Saludos cordiales,<br>
            <strong>Equipo Automatiza Tech</strong>
        </p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        
        <div style="text-align: center; color: #666; font-size: 12px;">
            <p>
                <a href="https://automatizatech.cl" style="color: #667eea;">automatizatech.cl</a> | 
                <a href="https://wa.me/56962183692" style="color: #25D366;">WhatsApp</a>
            </p>
            <p style="margin-top: 15px; color: #999; font-size: 11px;">
                📧 <strong>Política de comunicaciones:</strong> Este correo fue enviado porque agendaste una reunión con nosotros. 
                Respetamos tu privacidad y no enviamos spam. Si no deseas recibir más comunicaciones, 
                simplemente responde a este correo indicándolo.
            </p>
            <p style="color: #999; font-size: 11px;">
                © ' . date('Y') . ' Automatiza Tech - Todos los derechos reservados<br>
                Santiago, Chile
            </p>
        </div>
        
    </body>
    </html>';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <contacto@automatizatech.cl>',
        'Reply-To: contacto@automatizatech.cl',
        'Bcc: automatizacionesbotcore@gmail.com'
    );
    
    return wp_mail($to, $subject, $body, $headers);
}

/**
 * Validar fecha y hora de cita antes de reprogramar
 * Valida: horarios de atención, fechas bloqueadas, horarios ocupados
 * 
 * @param string $date Fecha en formato Y-m-d
 * @param string $time Hora en formato H:i o H:i:s
 * @param int $exclude_id ID de la cita a excluir (la que se está editando)
 * @return string|null Mensaje de error o null si es válido
 */
function automatiza_tech_validate_appointment_datetime($date, $time, $exclude_id = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // Obtener configuración de horarios
    $schedule_settings = get_option('automatiza_chat_schedule', array());
    
    // Timezone de Chile
    $tz = new DateTimeZone('America/Santiago');
    $now = new DateTime('now', $tz);
    
    // Parsear fecha y hora
    $appointment_dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . substr($time, 0, 5), $tz);
    if (!$appointment_dt) {
        return 'Formato de fecha u hora inválido.';
    }
    
    // 1. Validar que la fecha no sea pasada
    if ($appointment_dt < $now) {
        return 'No puedes agendar una cita en el pasado.';
    }
    
    // 2. Validar día de la semana (horarios de atención)
    $day_names = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
    $day_of_week = $day_names[(int)$appointment_dt->format('w')];
    $dias_es = array('sunday' => 'domingos', 'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miércoles', 'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sábados');
    
    $day_config = isset($schedule_settings[$day_of_week]) ? $schedule_settings[$day_of_week] : null;
    
    if (!$day_config || empty($day_config['enabled'])) {
        return 'Los ' . $dias_es[$day_of_week] . ' no hay atención disponible. Por favor elige otro día.';
    }
    
    // 3. Validar horario dentro del rango del día
    $start_time = isset($day_config['start']) ? $day_config['start'] : '09:00';
    $end_time = isset($day_config['end']) ? $day_config['end'] : '18:00';
    $appointment_time = substr($time, 0, 5);
    
    if ($appointment_time < $start_time || $appointment_time >= $end_time) {
        return 'El horario de atención para los ' . $dias_es[$day_of_week] . ' es de ' . $start_time . ' a ' . $end_time . ' hrs.';
    }
    
    // 4. Validar fechas bloqueadas/feriados
    $holidays_raw = isset($schedule_settings['holidays']) ? $schedule_settings['holidays'] : '';
    if (!empty($holidays_raw)) {
        $holidays = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $holidays_raw)));
        if (in_array($date, $holidays)) {
            return 'La fecha ' . date('d/m/Y', strtotime($date)) . ' está bloqueada (feriado o día no disponible).';
        }
    }
    
    // 5. Validar horarios ocupados (otras citas en el mismo slot)
    $time_hhmm = substr($time, 0, 5);
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE scheduled_date = %s 
         AND LEFT(scheduled_time, 5) = %s 
         AND (status IS NULL OR status != 'cancelled')
         AND id != %d",
        $date,
        $time_hhmm,
        $exclude_id
    ));
    
    if ($existing > 0) {
        return 'Ya existe otra cita agendada para el ' . date('d/m/Y', strtotime($date)) . ' a las ' . $time_hhmm . ' hrs. Por favor elige otro horario.';
    }
    
    // Todo válido
    return null;
}/**
 * Enviar correo cuando se reprograma una cita desde el admin
 */
function automatiza_tech_send_reschedule_email($lead, $original_lead) {
    $to = $lead->email;
    $subject = '📅 Tu cita ha sido reprogramada | Automatiza Tech';
    
    $name = esc_html($lead->name);
    $new_date = date('d/m/Y', strtotime($lead->scheduled_date));
    $new_time = substr($lead->scheduled_time, 0, 5);
    $old_date = date('d/m/Y', strtotime($original_lead->scheduled_date));
    $old_time = substr($original_lead->scheduled_time, 0, 5);
    
    // Obtener día de la semana en español
    $dias = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $dia_semana = $dias[date('w', strtotime($lead->scheduled_date))];
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png" alt="Automatiza Tech" style="max-width: 200px;">
        </div>
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 24px;">📅 Cita Reprogramada</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Tu reunión ha sido actualizada</p>
        </div>
        
        <p>Hola <strong>' . $name . '</strong>,</p>
        
        <p>Te informamos que tu cita ha sido <strong>reprogramada</strong>. A continuación los nuevos detalles:</p>
        
        <div style="background: #e3f2fd; padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #2196f3;">
            <h3 style="color: #1565c0; margin-top: 0;">📌 Nueva Fecha y Hora</h3>
            <p style="font-size: 18px; margin-bottom: 5px;">
                <strong>📅 Fecha:</strong> ' . $dia_semana . ' ' . $new_date . '
            </p>
            <p style="font-size: 18px; margin-bottom: 0;">
                <strong>🕐 Hora:</strong> ' . $new_time . ' hrs (Chile)
            </p>
        </div>
        
        <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0; color: #666; font-size: 14px;">
                <strong>Cita anterior:</strong> ' . $old_date . ' a las ' . $old_time . ' hrs
            </p>
        </div>
        
        <div style="background: #fff3e0; padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ff9800;">
            <h4 style="color: #e65100; margin-top: 0;">📍 Detalles de la reunión</h4>
            <p style="margin-bottom: 0;">
                <strong>Modalidad:</strong> Videollamada por Google Meet<br>
                <strong>Duración:</strong> Aproximadamente 30 minutos<br>
                <strong>Tema:</strong> Soluciones de automatización para tu negocio
            </p>
        </div>
        
        <p>El link de la reunión será enviado en un <strong>recordatorio automático</strong> antes de la cita.</p>
        
        <p>Si este nuevo horario no te funciona, puedes:</p>
        <ul>
            <li>Responder a este correo para coordinar otra fecha</li>
            <li>Escribirnos por WhatsApp al <a href="https://wa.me/56927002984" style="color: #25D366;">+56 9 2700 2984</a></li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="https://wa.me/56927002984?text=Hola,%20quisiera%20confirmar%20mi%20cita%20reprogramada" style="display: inline-block; background: #25D366; color: white; padding: 15px 40px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px;">
                💬 Confirmar por WhatsApp
            </a>
        </div>
        
        <p>¡Te esperamos!</p>
        
        <p style="margin-top: 30px;">
            Saludos cordiales,<br>
            <strong>Equipo Automatiza Tech</strong>
        </p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        
        <div style="text-align: center; color: #666; font-size: 12px;">
            <p>
                <a href="https://automatizatech.cl" style="color: #667eea;">automatizatech.cl</a> | 
                <a href="https://wa.me/56927002984" style="color: #25D366;">WhatsApp</a> |
                <a href="mailto:contacto@automatizatech.cl" style="color: #667eea;">contacto@automatizatech.cl</a>
            </p>
            <p style="margin-top: 15px; color: #999; font-size: 11px;">
                📧 Este correo fue enviado porque tienes una cita agendada con nosotros.<br>
                Respetamos tu privacidad y no enviamos spam.
            </p>
            <p style="color: #999; font-size: 11px;">
                © ' . date('Y') . ' Automatiza Tech - Todos los derechos reservados<br>
                Santiago, Chile
            </p>
        </div>
        
    </body>
    </html>';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <contacto@automatizatech.cl>',
        'Reply-To: contacto@automatizatech.cl',
        'Bcc: automatizacionesbotcore@gmail.com'
    );
    
    return wp_mail($to, $subject, $body, $headers);
}
