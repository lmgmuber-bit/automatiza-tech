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

    if ($action == 'edit' && isset($_POST['submit_edit']) && check_admin_referer('edit_lead_' . $id)) {
        // Update Logic
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'scheduled_date' => sanitize_text_field($_POST['scheduled_date']),
            'scheduled_time' => sanitize_text_field($_POST['scheduled_time']),
            'confirmed_attendance' => sanitize_text_field($_POST['confirmed_attendance']) === '' ? null : intval($_POST['confirmed_attendance'])
        );
        
        $wpdb->update($table_name, $data, array('id' => $id));
        echo '<div class="notice notice-success"><p>Cita actualizada correctamente.</p></div>';
        $action = 'list'; // Go back to list
    }

    // Views
    if ($action == 'edit' && $id > 0) {
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if (!$lead) {
            echo '<div class="notice notice-error"><p>Cita no encontrada.</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1>Editar Cita #<?php echo $lead->id; ?></h1>
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
                        <td><input type="date" name="scheduled_date" id="scheduled_date" value="<?php echo esc_attr($lead->scheduled_date); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="scheduled_time">Hora</label></th>
                        <td><input type="time" name="scheduled_time" id="scheduled_time" value="<?php echo esc_attr($lead->scheduled_time); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="confirmed_attendance">Estado Asistencia</label></th>
                        <td>
                            <select name="confirmed_attendance" id="confirmed_attendance">
                                <option value="" <?php selected($lead->confirmed_attendance, null); ?>>Pendiente</option>
                                <option value="1" <?php selected($lead->confirmed_attendance, '1'); ?>>Confirmado</option>
                                <option value="0" <?php selected($lead->confirmed_attendance, '0'); ?>>Rechazado</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit_edit" id="submit" class="button button-primary" value="Guardar Cambios">
                    <a href="?page=automatiza-leads-manager" class="button">Cancelar</a>
                </p>
            </form>
        </div>
        <?php
    } else {
        // List View
        $leads = $wpdb->get_results("SELECT * FROM $table_name ORDER BY scheduled_date DESC, scheduled_time DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Gestión de Citas</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="8">No hay citas registradas.</td></tr>
                    <?php else: foreach ($leads as $lead): ?>
                        <tr>
                            <td><?php echo $lead->id; ?></td>
                            <td><?php echo esc_html($lead->name); ?></td>
                            <td><?php echo esc_html($lead->email); ?></td>
                            <td><?php echo esc_html($lead->phone); ?></td>
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
                                <a href="?page=automatiza-leads-manager&action=edit&id=<?php echo $lead->id; ?>" class="button button-small">Editar</a>
                                <a href="?page=automatiza-leads-manager&action=delete&id=<?php echo $lead->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_lead_' . $lead->id); ?>" class="button button-small button-link-delete" onclick="return confirm('¿Estás seguro de eliminar esta cita?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
