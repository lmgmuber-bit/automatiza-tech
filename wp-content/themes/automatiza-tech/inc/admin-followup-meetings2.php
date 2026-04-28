<?php
/**
 * Panel de Administración para Reuniones de Seguimiento
 * 
 * Permite programar reuniones con clientes que ya tuvieron una demo
 * y necesitan una reunión de seguimiento para afinar detalles del proyecto.
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crear tabla en la base de datos al activar
 */
function automatiza_tech_followup_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        client_name varchar(100) NOT NULL,
        client_email varchar(100) NOT NULL,
        invitees_emails text DEFAULT NULL,
        company_name varchar(150) DEFAULT '',
        phone varchar(30) DEFAULT '',
        meeting_date date NOT NULL,
        meeting_time time NOT NULL,
        meet_link varchar(500) DEFAULT '',
        meeting_subject varchar(255) DEFAULT 'Reunión de Seguimiento',
        notes text DEFAULT '',
        status varchar(20) DEFAULT 'scheduled',
        confirmed_at datetime DEFAULT NULL,
        email_sent tinyint(1) DEFAULT 0,
        whatsapp_sent tinyint(1) DEFAULT 0,
        reminder_24h_sent tinyint(1) DEFAULT 0,
        reminder_1h_sent tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_meeting_date (meeting_date),
        KEY idx_status (status),
        KEY idx_client_email (client_email)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'automatiza_tech_followup_create_table');

/**
 * Asegurar nuevas columnas en instalaciones existentes
 */
function automatiza_tech_followup_ensure_columns() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';

    $has_invitees = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'invitees_emails'");
    if (!$has_invitees) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitees_emails text DEFAULT NULL AFTER client_email");
    }
}

// Ejecutar creación de tabla si no existe
add_action('init', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        automatiza_tech_followup_create_table();
    } else {
        automatiza_tech_followup_ensure_columns();
    }
});

/**
 * Sanitizar y validar lista de emails de invitados
 *
 * @param string $raw_input
 * @return array{valid: string[], invalid: string[]}
 */
function automatiza_tech_parse_invitees_emails($raw_input) {
    $raw = (string) $raw_input;
    if ($raw === '') {
        return array('valid' => array(), 'invalid' => array());
    }

    $parts = preg_split('/[\s,;\n\r\t]+/', $raw);
    $valid = array();
    $invalid = array();

    foreach ($parts as $part) {
        $trimmed = trim((string) $part);
        if ($trimmed === '') {
            continue;
        }

        $email = sanitize_email($trimmed);
        if ($email !== '' && is_email($email)) {
            $valid[] = strtolower($email);
        } else {
            $invalid[] = $trimmed;
        }
    }

    return array(
        'valid' => array_values(array_unique($valid)),
        'invalid' => array_values(array_unique($invalid)),
    );
}

/**
 * Obtener destinatarios finales (cliente + invitados válidos, sin duplicados)
 *
 * @param object $meeting
 * @return string[]
 */
function automatiza_tech_get_followup_recipients($meeting) {
    $recipients = array();

    $client_email = sanitize_email((string) ($meeting->client_email ?? ''));
    if ($client_email !== '' && is_email($client_email)) {
        $recipients[] = strtolower($client_email);
    }

    $parsed = automatiza_tech_parse_invitees_emails((string) ($meeting->invitees_emails ?? ''));
    if (!empty($parsed['valid'])) {
        $recipients = array_merge($recipients, $parsed['valid']);
    }

    return array_values(array_unique($recipients));
}

/**
 * Verificar disponibilidad de un horario
 * Consulta tanto la tabla de DEMO (leads) como la de Seguimiento (followup_meetings)
 * 
 * @param string $date Fecha en formato Y-m-d
 * @param string $time Hora en formato H:i o H:i:s
 * @param int|null $exclude_followup_id ID de reunión de seguimiento a excluir (para edición)
 * @return array ['available' => bool, 'conflict_type' => string, 'conflict_details' => string]
 */
function automatiza_tech_check_slot_availability($date, $time, $exclude_followup_id = null) {
    global $wpdb;
    
    $leads_table = $wpdb->prefix . 'automatiza_leads';
    $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
    
    // Normalizar hora a formato H:i
    $time_normalized = substr($time, 0, 5);
    
    // 1. Verificar tabla de DEMO (leads)
    $demo_conflict = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, email, phone, scheduled_date, scheduled_time 
         FROM $leads_table 
         WHERE scheduled_date = %s 
         AND LEFT(scheduled_time, 5) = %s 
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
        $date,
        $time_normalized
    ));
    
    if ($demo_conflict) {
        return array(
            'available' => false,
            'conflict_type' => 'demo',
            'conflict_details' => sprintf(
                'DEMO con %s (%s) - %s %s hrs',
                $demo_conflict->name,
                $demo_conflict->email,
                date('d/m/Y', strtotime($demo_conflict->scheduled_date)),
                substr($demo_conflict->scheduled_time, 0, 5)
            )
        );
    }
    
    // 2. Verificar tabla de Seguimiento (followup_meetings)
    $followup_query = "SELECT id, client_name, client_email, meeting_date, meeting_time 
                       FROM $followup_table 
                       WHERE meeting_date = %s 
                       AND LEFT(meeting_time, 5) = %s 
                       AND status NOT IN ('cancelled', 'completed')";
    
    // Excluir la reunión actual si estamos editando
    if ($exclude_followup_id) {
        $followup_query .= $wpdb->prepare(" AND id != %d", $exclude_followup_id);
    }
    
    $followup_conflict = $wpdb->get_row($wpdb->prepare(
        $followup_query,
        $date,
        $time_normalized
    ));
    
    if ($followup_conflict) {
        return array(
            'available' => false,
            'conflict_type' => 'followup',
            'conflict_details' => sprintf(
                'Seguimiento con %s (%s) - %s %s hrs',
                $followup_conflict->client_name,
                $followup_conflict->client_email,
                date('d/m/Y', strtotime($followup_conflict->meeting_date)),
                substr($followup_conflict->meeting_time, 0, 5)
            )
        );
    }
    
    // Horario disponible
    return array(
        'available' => true,
        'conflict_type' => null,
        'conflict_details' => null
    );
}

/**
 * Agregar menú de administración
 */
function automatiza_tech_followup_menu() {
    add_submenu_page(
        'automatiza-reminders',
        'Reuniones de Seguimiento',
        '📅 Seguimiento',
        'manage_options',
        'automatiza-followup',
        'automatiza_tech_followup_page'
    );
}
add_action('admin_menu', 'automatiza_tech_followup_menu');

/**
 * Renderizar página de administración
 */
function automatiza_tech_followup_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    $message = '';
    
    // --- PROCESAR FORMULARIO ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Verificar nonce
        if (!isset($_POST['followup_nonce']) || !wp_verify_nonce($_POST['followup_nonce'], 'save_followup_meeting')) {
            echo '<div class="notice notice-error"><p>Error de seguridad. Intente nuevamente.</p></div>';
            return;
        }
        
        // Acción: Eliminar reunión
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['meeting_id'])) {
            $meeting_id = intval($_POST['meeting_id']);
            $wpdb->delete($table_name, array('id' => $meeting_id));
            $message = '<div class="notice notice-warning is-dismissible"><p>🗑️ Reunión eliminada correctamente.</p></div>';
        }
        // Acción: Guardar/Crear reunión
        elseif (isset($_POST['client_name'])) {
            $client_name = sanitize_text_field($_POST['client_name']);
            $client_email = sanitize_email($_POST['client_email']);
            $invitees_input = sanitize_textarea_field($_POST['invitees_emails'] ?? '');
            $company_name = sanitize_text_field($_POST['company_name']);
            $phone = sanitize_text_field($_POST['phone']);
            $meeting_date = sanitize_text_field($_POST['meeting_date']);
            $meeting_time = sanitize_text_field($_POST['meeting_time']);
            $meet_link = esc_url_raw($_POST['meet_link']);
            $meeting_subject = sanitize_text_field($_POST['meeting_subject']);
            $notes = sanitize_textarea_field($_POST['notes']);
            $send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';
            $create_calendar_event = isset($_POST['create_calendar_event']) && $_POST['create_calendar_event'] === '1';
            $meeting_id_edit = isset($_POST['meeting_id']) && !empty($_POST['meeting_id']) ? intval($_POST['meeting_id']) : null;
            $invitees_parsed = automatiza_tech_parse_invitees_emails($invitees_input);
            $invitees_valid = $invitees_parsed['valid'];
            $invitees_invalid = $invitees_parsed['invalid'];
            
            // Validaciones básicas
            if (empty($client_name) || empty($client_email) || empty($meeting_date) || empty($meeting_time)) {
                $message = '<div class="notice notice-error is-dismissible"><p>❌ Por favor completa todos los campos obligatorios.</p></div>';
            } elseif (!is_email($client_email)) {
                $message = '<div class="notice notice-error is-dismissible"><p>❌ El correo electrónico no es válido.</p></div>';
            } else {
                // Validar disponibilidad (verificar ambas tablas: leads y followup)
                $availability_check = automatiza_tech_check_slot_availability($meeting_date, $meeting_time, $meeting_id_edit);
                
                if (!$availability_check['available']) {
                    $conflict_type = $availability_check['conflict_type'] === 'demo' ? 'DEMO' : 'Reunión de Seguimiento';
                    $message = '<div class="notice notice-error is-dismissible"><p>❌ <strong>Horario no disponible:</strong> Ya existe una ' . $conflict_type . ' programada para el <strong>' . date('d/m/Y', strtotime($meeting_date)) . '</strong> a las <strong>' . substr($meeting_time, 0, 5) . ' hrs</strong>.<br><br>📋 Conflicto con: ' . esc_html($availability_check['conflict_details']) . '</p></div>';
                } else {
                    // Preparar datos para insertar/actualizar
                    $data = array(
                        'client_name' => $client_name,
                        'client_email' => $client_email,
                        'invitees_emails' => !empty($invitees_valid) ? implode(',', $invitees_valid) : null,
                        'company_name' => $company_name,
                        'phone' => $phone,
                        'meeting_date' => $meeting_date,
                        'meeting_time' => $meeting_time,
                        'meet_link' => $meet_link,
                        'meeting_subject' => $meeting_subject ?: 'Reunión de Seguimiento',
                        'notes' => $notes,
                        'status' => 'scheduled'
                    );
                    
                    // Verificar si es actualización o nueva
                    if ($meeting_id_edit) {
                        $meeting_id = $meeting_id_edit;
                        $wpdb->update($table_name, $data, array('id' => $meeting_id));
                        $action_msg = 'actualizada';
                    } else {
                        $wpdb->insert($table_name, $data);
                        $meeting_id = $wpdb->insert_id;
                        $action_msg = 'programada';
                    }
                
                // Crear evento en Google Calendar via N8N si está marcado
                $n8n_result = null;
                if ($create_calendar_event && $meeting_id) {
                    $n8n_result = automatiza_tech_create_followup_calendar_event($meeting_id);
                    if ($n8n_result && $n8n_result['success']) {
                        // Actualizar meet_link si N8N lo devolvió
                        if (!empty($n8n_result['meet_link'])) {
                            $wpdb->update($table_name, array('meet_link' => $n8n_result['meet_link']), array('id' => $meeting_id));
                            $data['meet_link'] = $n8n_result['meet_link'];
                        }
                    }
                }
                
                // Enviar correo si está marcado
                if ($send_email && $meeting_id) {
                    // Re-obtener datos actualizados (con meet_link de N8N si aplica)
                    $meeting_updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
                    $email_sent = automatiza_tech_send_followup_email($meeting_id);
                    if ($email_sent) {
                        $wpdb->update($table_name, array('email_sent' => 1), array('id' => $meeting_id));
                        
                        // Mensaje de éxito con info de calendario
                        $calendar_msg = '';
                        if ($create_calendar_event) {
                            if ($n8n_result && $n8n_result['success']) {
                                $calendar_msg = ' 📅 Evento creado en Google Calendar.';
                                if (!empty($n8n_result['meet_link'])) {
                                    $calendar_msg .= ' 🔗 Link Meet generado automáticamente.';
                                }
                            } else {
                                $calendar_msg = ' ⚠️ No se pudo crear el evento en calendario.';
                            }
                        }

                        $invitees_msg = '';
                        if (!empty($invitees_valid)) {
                            $invitees_msg = ' 👥 Invitados notificados: <strong>' . count($invitees_valid) . '</strong>.';
                        }
                        if (!empty($invitees_invalid)) {
                            $invitees_msg .= ' ⚠️ Correos inválidos omitidos: ' . esc_html(implode(', ', $invitees_invalid)) . '.';
                        }
                        
                        $message = '<div class="notice notice-success is-dismissible"><p>✅ Reunión ' . $action_msg . ' y correo enviado correctamente a <strong>' . esc_html($client_email) . '</strong>.' . $invitees_msg . $calendar_msg . '</p></div>';
                    } else {
                        $message = '<div class="notice notice-warning is-dismissible"><p>⚠️ Reunión ' . $action_msg . ' pero hubo un error al enviar el correo. Revise la configuración SMTP.</p></div>';
                    }
                } else {
                    // Sin envío de correo
                    $calendar_msg = '';
                    if ($create_calendar_event) {
                        if ($n8n_result && $n8n_result['success']) {
                            $calendar_msg = ' 📅 Evento creado en Google Calendar.';
                            if (!empty($n8n_result['meet_link'])) {
                                $calendar_msg .= ' 🔗 Link Meet: <a href="' . esc_url($n8n_result['meet_link']) . '" target="_blank">' . esc_html($n8n_result['meet_link']) . '</a>';
                            }
                        } else {
                            $calendar_msg = ' ⚠️ No se pudo crear el evento en calendario: ' . esc_html($n8n_result['message'] ?? 'Error desconocido');
                        }
                    }

                    $invitees_saved_msg = '';
                    if (!empty($invitees_valid)) {
                        $invitees_saved_msg = ' 👥 Invitados guardados: <strong>' . count($invitees_valid) . '</strong>.';
                    }
                    if (!empty($invitees_invalid)) {
                        $invitees_saved_msg .= ' ⚠️ Correos inválidos omitidos: ' . esc_html(implode(', ', $invitees_invalid)) . '.';
                    }

                    $message = '<div class="notice notice-success is-dismissible"><p>✅ Reunión ' . $action_msg . ' correctamente. No se envió correo (opción desmarcada).' . $invitees_saved_msg . $calendar_msg . '</p></div>';
                }
                } // Cierre del else de disponibilidad
            }
        }
    }
    
    // --- OBTENER REUNIÓN A EDITAR ---
    $edit_meeting = null;
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $edit_meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    }
    
    // --- FILTROS ---
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
    
    // Construir query
    $where = "1=1";
    if ($filter_status) {
        $where .= $wpdb->prepare(" AND status = %s", $filter_status);
    }
    if ($filter_date) {
        $where .= $wpdb->prepare(" AND meeting_date = %s", $filter_date);
    }
    
    // Por defecto mostrar futuras primero
    $meetings = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY meeting_date ASC, meeting_time ASC LIMIT 50");
    
    // Estadísticas
    $total_scheduled = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'scheduled'");
    $total_completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
    $total_cancelled = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'cancelled'");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">📅 Reuniones de Seguimiento</h1>
        <p>Programa reuniones de seguimiento con clientes que ya tuvieron una demo. El correo de invitación incluye los colores corporativos.</p>
        
        <?php echo $message; ?>
        
        <!-- ESTADÍSTICAS -->
        <div style="display:flex; gap:15px; margin-bottom:20px;">
            <div style="background:#dcfce7; padding:15px 25px; border-radius:8px; border-left:4px solid #22c55e;">
                <strong style="font-size:24px; color:#166534;"><?php echo $total_scheduled; ?></strong>
                <p style="margin:0; color:#166534;">Programadas</p>
            </div>
            <div style="background:#dbeafe; padding:15px 25px; border-radius:8px; border-left:4px solid #3b82f6;">
                <strong style="font-size:24px; color:#1e40af;"><?php echo $total_completed; ?></strong>
                <p style="margin:0; color:#1e40af;">Completadas</p>
            </div>
            <div style="background:#fef3c7; padding:15px 25px; border-radius:8px; border-left:4px solid #f59e0b;">
                <strong style="font-size:24px; color:#92400e;"><?php echo $total_cancelled; ?></strong>
                <p style="margin:0; color:#92400e;">Canceladas</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 20px; flex-wrap:wrap;">
            
            <!-- FORMULARIO -->
            <div style="flex: 1; min-width:400px; max-width:500px;">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php echo $edit_meeting ? '✏️ Editar Reunión #' . $edit_meeting->id : '➕ Programar Nueva Reunión'; ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <form method="POST">
                            <?php wp_nonce_field('save_followup_meeting', 'followup_nonce'); ?>
                            <?php if ($edit_meeting): ?>
                                <input type="hidden" name="meeting_id" value="<?php echo $edit_meeting->id; ?>">
                            <?php endif; ?>
                            
                            <?php
                            // Obtener clientes con propuestas (todas)
                            global $wpdb;
                            $proposals_table = $wpdb->prefix . 'automatiza_propuestas';
                            
                            // Verificar si existe el campo phone
                            $has_phone = $wpdb->get_var("SHOW COLUMNS FROM $proposals_table LIKE 'phone'");
                            
                            if ($has_phone) {
                                $clients_with_proposals = $wpdb->get_results("
                                    SELECT id, client_name, client_email, company_name, phone,
                                           unique_link_id as proposal_number, created_at, status
                                    FROM $proposals_table 
                                    WHERE client_email IS NOT NULL AND client_email != ''
                                    ORDER BY created_at DESC
                                    LIMIT 50
                                ");
                            } else {
                                $clients_with_proposals = $wpdb->get_results("
                                    SELECT id, client_name, client_email, company_name, '' as phone,
                                           unique_link_id as proposal_number, created_at, status
                                    FROM $proposals_table 
                                    WHERE client_email IS NOT NULL AND client_email != ''
                                    ORDER BY created_at DESC
                                    LIMIT 50
                                ");
                            }
                            ?>
                            
                            <!-- SELECTOR DE CLIENTES CON PROPUESTA -->
                            <div style="margin-bottom:20px; padding:15px; background:linear-gradient(135deg, #f0fdfa, #ccfbf1); border:2px solid #14b8a6; border-radius:12px;">
                                <label for="proposal_client" style="display:block; margin-bottom:8px; font-weight:600; color:#0f766e; font-size:14px;">
                                    📋 Seleccionar Cliente con Propuesta Enviada
                                </label>
                                <select name="proposal_client" id="proposal_client" style="width:100%; padding:12px; border:2px solid #14b8a6; border-radius:8px; background:#ffffff; font-size:14px; cursor:pointer;">
                                    <option value="">-- Seleccionar cliente con propuesta --</option>
                                    <option value="nuevo">➕ Agregar cliente nuevo (sin propuesta previa)</option>
                                    <?php if (!empty($clients_with_proposals)): ?>
                                        <?php foreach ($clients_with_proposals as $client): 
                                            $display_date = date('d/m/Y', strtotime($client->created_at));
                                            $display_name = $client->client_name ?: explode('@', $client->client_email)[0];
                                            $display_company = $client->company_name ?: 'Sin empresa';
                                        ?>
                                            <option value="<?php echo esc_attr($client->id); ?>"
                                                data-name="<?php echo esc_attr($display_name); ?>"
                                                data-email="<?php echo esc_attr($client->client_email); ?>"
                                                data-company="<?php echo esc_attr($client->company_name); ?>"
                                                data-phone="<?php echo esc_attr($client->phone); ?>"
                                                data-proposal="<?php echo esc_attr($client->proposal_number); ?>"
                                                data-date="<?php echo esc_attr($display_date); ?>">
                                                👤 <?php echo esc_html($display_name); ?> - <?php echo esc_html($display_company); ?>
                                                (📄 <?php echo esc_html(substr($client->proposal_number, 0, 8)); ?> | <?php echo $display_date; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <p style="margin:8px 0 0 0; color:#0d9488; font-size:12px;">
                                    💡 Selecciona un cliente para auto-completar los datos del formulario
                                </p>
                            </div>
                            
                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <th><label for="client_name">👤 Nombre Cliente *</label></th>
                                    <td>
                                        <input type="text" name="client_name" id="client_name" class="regular-text" required
                                            value="<?php echo esc_attr($edit_meeting->client_name ?? ''); ?>"
                                            placeholder="Juan Pérez">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="client_email">📧 Email *</label></th>
                                    <td>
                                        <input type="email" name="client_email" id="client_email" class="regular-text" required
                                            value="<?php echo esc_attr($edit_meeting->client_email ?? ''); ?>"
                                            placeholder="cliente@empresa.com">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="invitees_emails">👥 Invitados (emails)</label></th>
                                    <td>
                                        <textarea name="invitees_emails" id="invitees_emails" class="large-text" rows="2"
                                            placeholder="persona1@empresa.com, persona2@empresa.com"><?php echo esc_textarea($edit_meeting->invitees_emails ?? ''); ?></textarea>
                                        <p class="description">Opcional. Separa múltiples correos con coma, punto y coma o saltos de línea. Recibirán la misma invitación y enlace Meet.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="company_name">🏢 Empresa</label></th>
                                    <td>
                                        <input type="text" name="company_name" id="company_name" class="regular-text"
                                            value="<?php echo esc_attr($edit_meeting->company_name ?? ''); ?>"
                                            placeholder="Empresa S.A.">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="phone">📱 Teléfono</label></th>
                                    <td>
                                        <input type="text" name="phone" id="phone" class="regular-text"
                                            value="<?php echo esc_attr($edit_meeting->phone ?? ''); ?>"
                                            placeholder="+56 9 1234 5678">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="meeting_date">📅 Fecha *</label></th>
                                    <td>
                                        <input type="date" name="meeting_date" id="meeting_date" required
                                            value="<?php echo esc_attr($edit_meeting->meeting_date ?? ''); ?>"
                                            min="<?php echo date('Y-m-d'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="meeting_time">🕐 Hora *</label></th>
                                    <td>
                                        <?php 
                                        $current_time = $edit_meeting ? substr($edit_meeting->meeting_time, 0, 5) : '';
                                        $hours = array(
                                            '08:00' => '08:00 - 09:00',
                                            '09:00' => '09:00 - 10:00',
                                            '10:00' => '10:00 - 11:00',
                                            '11:00' => '11:00 - 12:00',
                                            '12:00' => '12:00 - 13:00',
                                            '14:00' => '14:00 - 15:00',
                                            '15:00' => '15:00 - 16:00',
                                            '16:00' => '16:00 - 17:00',
                                            '17:00' => '17:00 - 18:00',
                                            '18:00' => '18:00 - 19:00',
                                        );
                                        ?>
                                        <select name="meeting_time" id="meeting_time" required style="padding:8px 12px; font-size:14px; min-width:180px;">
                                            <option value="">-- Selecciona horario --</option>
                                            <?php foreach ($hours as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php selected($current_time, $value); ?>>
                                                    <?php echo $label; ?> hrs
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description" style="margin-top:5px;">Reuniones de 1 hora exacta (horario Chile)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="meet_link">🔗 Link Google Meet</label></th>
                                    <td>
                                        <input type="url" name="meet_link" id="meet_link" class="large-text"
                                            value="<?php echo esc_attr($edit_meeting->meet_link ?? ''); ?>"
                                            placeholder="https://meet.google.com/xxx-xxxx-xxx">
                                        <p class="description">Deja en blanco si enviarás el link después.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="meeting_subject">📌 Asunto</label></th>
                                    <td>
                                        <input type="text" name="meeting_subject" id="meeting_subject" class="large-text"
                                            value="<?php echo esc_attr($edit_meeting->meeting_subject ?? 'Reunión de Seguimiento - AutomatizaTech'); ?>"
                                            placeholder="Reunión de Seguimiento - Proyecto X">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="notes">📝 Notas Internas</label></th>
                                    <td>
                                        <textarea name="notes" id="notes" class="large-text" rows="3"
                                            placeholder="Notas sobre el cliente o la reunión (solo visible internamente)..."><?php echo esc_textarea($edit_meeting->notes ?? ''); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Checkbox crear evento en calendario (N8N) -->
                            <div style="margin-top:20px; padding:15px; background:#dbeafe; border:2px solid #3b82f6; border-radius:8px;">
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:15px;">
                                    <input type="checkbox" name="create_calendar_event" value="1" id="create_calendar_event" 
                                        <?php echo (!$edit_meeting || empty($edit_meeting->meet_link)) ? 'checked' : ''; ?>
                                        style="width:20px; height:20px;">
                                    <span style="color:#1e40af; font-weight:600;">📅 Crear evento en Google Calendar + Link Meet automático</span>
                                </label>
                                <p style="margin:8px 0 0 30px; color:#3b82f6; font-size:13px;">
                                    <?php if ($edit_meeting && !empty($edit_meeting->meet_link)): ?>
                                        ✓ Ya existe un link Meet. Marca para crear un nuevo evento.
                                    <?php else: ?>
                                        Se creará automáticamente el evento en el calendario de AutomatizaTech y se generará un link de Google Meet.
                                        <br>Tanto el cliente como AutomatizaTech recibirán la invitación de calendario.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <!-- Checkbox enviar correo -->
                            <div style="margin-top:15px; padding:15px; background:#e0f7f4; border:2px solid #14b8a6; border-radius:8px;">
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:15px;">
                                    <input type="checkbox" name="send_email" value="1" id="send_email" 
                                        <?php echo (!$edit_meeting || !$edit_meeting->email_sent) ? 'checked' : ''; ?>
                                        style="width:20px; height:20px;">
                                    <span style="color:#0f766e; font-weight:600;">📧 Enviar correo de invitación al cliente</span>
                                </label>
                                <p style="margin:8px 0 0 30px; color:#0d9488; font-size:13px;">
                                    <?php if ($edit_meeting && $edit_meeting->email_sent): ?>
                                        ✓ Ya se envió un correo anteriormente. Marca para reenviar.
                                    <?php else: ?>
                                        El cliente recibirá un correo corporativo con los detalles de la reunión y el link de Meet.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <p class="submit" style="display:flex; gap:10px;">
                                <button type="submit" class="button button-primary button-large">
                                    <?php echo $edit_meeting ? '💾 Actualizar Reunión' : '📅 Programar Reunión'; ?>
                                </button>
                                <?php if ($edit_meeting): ?>
                                    <a href="<?php echo admin_url('admin.php?page=automatiza-followup'); ?>" class="button button-large">Cancelar</a>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- LISTA DE REUNIONES -->
            <div style="flex: 2; min-width:500px;">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">📋 Reuniones Programadas</h2>
                    </div>
                    <div class="inside" style="padding:0;">
                        
                        <!-- Filtros -->
                        <div style="padding:10px 15px; background:#f6f7f7; border-bottom:1px solid #ddd;">
                            <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <input type="hidden" name="page" value="automatiza-followup">
                                <label>
                                    Estado:
                                    <select name="filter_status">
                                        <option value="">Todos</option>
                                        <option value="scheduled" <?php selected($filter_status, 'scheduled'); ?>>📅 Programadas</option>
                                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>✓ Completadas</option>
                                        <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>✗ Canceladas</option>
                                    </select>
                                </label>
                                <label>
                                    Fecha:
                                    <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>">
                                </label>
                                <input type="submit" class="button" value="Filtrar">
                                <?php if ($filter_status || $filter_date): ?>
                                    <a href="<?php echo admin_url('admin.php?page=automatiza-followup'); ?>" class="button">Limpiar</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <style>
                            .followup-table { width:100%; border-collapse:collapse; font-size:13px; }
                            .followup-table th, .followup-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e5e5; }
                            .followup-table th { background:#f9f9f9; font-weight:600; }
                            .followup-table tbody tr:hover { background:#f0f6fc; }
                            .status-scheduled { color:#0d9488; font-weight:500; }
                            .status-completed { color:#166534; font-weight:500; }
                            .status-cancelled { color:#dc2626; font-weight:500; }
                            .action-links a { margin-right:10px; text-decoration:none; }
                            
                            /* ========== RESPONSIVE STYLES - SEGUIMIENTOS ========== */
                            
                            /* Tablet (768px - 1024px) */
                            @media screen and (max-width: 1024px) {
                                .followup-table { font-size: 12px; }
                                .followup-table th, .followup-table td { padding: 8px 10px; }
                            }
                            
                            /* Mobile (hasta 767px) */
                            @media screen and (max-width: 767px) {
                                .wrap { padding: 10px !important; margin-left: 0 !important; }
                                .wrap h1 { font-size: 18px; }
                                
                                /* Filtros */
                                .tablenav.top { padding: 10px; background: #f6f7f7; border-radius: 8px; }
                                .tablenav.top .alignleft form {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 10px;
                                }
                                .tablenav.top .alignleft form label {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 5px;
                                    width: 100%;
                                }
                                .tablenav.top .alignleft form select,
                                .tablenav.top .alignleft form input[type="date"] {
                                    width: 100%;
                                    height: 40px;
                                    font-size: 16px;
                                    padding: 8px 12px;
                                }
                                .tablenav.top .alignleft form .button {
                                    width: 48%;
                                    height: 40px;
                                }
                                
                                /* Tabla con scroll horizontal */
                                .followup-table-wrapper {
                                    overflow-x: auto;
                                    -webkit-overflow-scrolling: touch;
                                    margin: 0 -10px;
                                    padding: 0 10px;
                                }
                                .followup-table {
                                    min-width: 650px;
                                    font-size: 12px;
                                }
                                .followup-table th, .followup-table td {
                                    padding: 8px 6px;
                                    white-space: nowrap;
                                }
                                .followup-table td:first-child {
                                    min-width: 120px;
                                    white-space: normal;
                                }
                                .followup-table td:first-child small {
                                    font-size: 10px;
                                    word-break: break-all;
                                }
                                
                                .action-links a {
                                    margin-right: 6px;
                                    font-size: 14px;
                                }
                                
                                /* Formulario de nueva reunión */
                                .form-table { display: block; }
                                .form-table tbody { display: block; }
                                .form-table tr {
                                    display: flex;
                                    flex-direction: column;
                                    margin-bottom: 15px;
                                    padding-bottom: 15px;
                                    border-bottom: 1px solid #eee;
                                }
                                .form-table th {
                                    display: block;
                                    width: 100%;
                                    padding: 0 0 5px 0;
                                    font-size: 13px;
                                }
                                .form-table td {
                                    display: block;
                                    width: 100%;
                                    padding: 0;
                                }
                                .form-table input[type="text"],
                                .form-table input[type="email"],
                                .form-table input[type="tel"],
                                .form-table input[type="date"],
                                .form-table input[type="time"],
                                .form-table select,
                                .form-table textarea {
                                    width: 100% !important;
                                    font-size: 16px !important;
                                    padding: 10px 12px !important;
                                    box-sizing: border-box;
                                }
                            }
                            
                            /* Mobile Small (hasta 480px) */
                            @media screen and (max-width: 480px) {
                                .wrap { padding: 5px !important; }
                                .wrap h1 { font-size: 16px; }
                                
                                .followup-table {
                                    min-width: 550px;
                                    font-size: 11px;
                                }
                                .followup-table th, .followup-table td {
                                    padding: 6px 4px;
                                }
                                
                                .action-links a { margin-right: 4px; font-size: 12px; }
                            }
                            
                            /* Touch improvements */
                            @media (hover: none) and (pointer: coarse) {
                                .tablenav.top .alignleft form select,
                                .tablenav.top .alignleft form input[type="date"],
                                .tablenav.top .alignleft form .button,
                                .form-table input,
                                .form-table select,
                                .form-table textarea {
                                    min-height: 44px;
                                }
                                .action-links a {
                                    display: inline-block;
                                    padding: 8px;
                                    min-width: 44px;
                                    min-height: 44px;
                                    text-align: center;
                                }
                            }
                        </style>
                        
                        <table class="followup-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Empresa</th>
                                    <th>Fecha/Hora</th>
                                    <th>Estado</th>
                                    <th>Notificaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($meetings)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:30px; color:#666;">
                                            No hay reuniones programadas.
                                        </td>
                                    </tr>
                                <?php else: foreach ($meetings as $m): 
                                    $date_formatted = date('d/m/Y', strtotime($m->meeting_date));
                                    $time_formatted = substr($m->meeting_time, 0, 5);
                                    
                                    // Determinar si es pasada
                                    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('America/Santiago');
                                    $meeting_dt = new DateTime($m->meeting_date . ' ' . $m->meeting_time, $tz);
                                    $now_dt = new DateTime('now', $tz);
                                    $is_past = $meeting_dt < $now_dt;
                                ?>
                                    <tr style="<?php echo $is_past && $m->status === 'scheduled' ? 'background:#fef3c7;' : ''; ?>">
                                        <td>
                                            <strong><?php echo esc_html($m->client_name); ?></strong><br>
                                            <small style="color:#666;"><?php echo esc_html($m->client_email); ?></small>
                                            <?php
                                            $invitees_count = 0;
                                            if (!empty($m->invitees_emails)) {
                                                $invitees_count = count(array_filter(array_map('trim', explode(',', (string) $m->invitees_emails))));
                                            }
                                            ?>
                                            <?php if ($invitees_count > 0): ?>
                                                <br><small style="color:#0f766e;">👥 +<?php echo intval($invitees_count); ?> invitados</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($m->company_name ?: '-'); ?></td>
                                        <td>
                                            <strong><?php echo $date_formatted; ?></strong><br>
                                            <span style="color:#666;"><?php echo $time_formatted; ?> hrs</span>
                                        </td>
                                        <td>
                                            <?php 
                                            switch($m->status) {
                                                case 'scheduled':
                                                    echo '<span class="status-scheduled">📅 Programada</span>';
                                                    break;
                                                case 'confirmed':
                                                    echo '<span style="color:#059669; font-weight:500;">✅ Confirmada</span>';
                                                    if ($m->confirmed_at) {
                                                        echo '<br><small style="color:#666;">' . date('d/m H:i', strtotime($m->confirmed_at)) . '</small>';
                                                    }
                                                    break;
                                                case 'completed':
                                                    echo '<span class="status-completed">✓ Completada</span>';
                                                    break;
                                                case 'cancelled':
                                                    echo '<span class="status-cancelled">✗ Cancelada</span>';
                                                    break;
                                                default:
                                                    echo esc_html($m->status);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($m->email_sent): ?>
                                                <span style="color:#22c55e;" title="Correo enviado">📧✓</span>
                                            <?php else: ?>
                                                <span style="color:#9ca3af;" title="Correo pendiente">📧</span>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($m->whatsapp_sent) && $m->whatsapp_sent): ?>
                                                <span style="color:#25D366; margin-left:5px;" title="WhatsApp enviado">💬✓</span>
                                            <?php elseif ($m->phone): ?>
                                                <span style="color:#9ca3af; margin-left:5px;" title="WhatsApp pendiente">💬</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-links">
                                            <a href="<?php echo admin_url('admin.php?page=automatiza-followup&edit_id=' . $m->id); ?>" 
                                               title="Editar">✏️</a>
                                            
                                            <?php if (empty($m->meet_link) && $m->status === 'scheduled'): ?>
                                                <a href="#" class="create-calendar-event" data-id="<?php echo $m->id; ?>" 
                                                   title="Crear evento en calendario + Meet">📅</a>
                                            <?php endif; ?>
                                            
                                            <?php if (!$m->email_sent && $m->status === 'scheduled'): ?>
                                                <a href="#" class="resend-email" data-id="<?php echo $m->id; ?>" 
                                                   title="Enviar correo">📧</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($m->meet_link): ?>
                                                <a href="<?php echo esc_url($m->meet_link); ?>" target="_blank" 
                                                   title="Abrir Meet">🔗</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($m->status === 'scheduled'): ?>
                                                <a href="#" class="mark-complete" data-id="<?php echo $m->id; ?>" 
                                                   title="Marcar completada">✓</a>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display:inline;" 
                                                onsubmit="return confirm('¿Eliminar esta reunión?');">
                                                <?php wp_nonce_field('save_followup_meeting', 'followup_nonce'); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="meeting_id" value="<?php echo $m->id; ?>">
                                                <button type="submit" style="background:none; border:none; cursor:pointer; color:#dc2626;" 
                                                    title="Eliminar">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Info box -->
        <div style="background:#dbeafe; padding:15px; border-left:4px solid #3b82f6; margin-top:20px; border-radius:4px;">
            <p style="margin:0;"><strong>📅 Integración con Google Calendar</strong></p>
            <p style="margin:5px 0 0 0; color:#1e40af;">
                Al marcar la opción "Crear evento en Google Calendar", se conecta automáticamente con N8N para:<br>
                • Crear un evento en el calendario de <strong>AutomatizaTech</strong><br>
                • Enviar invitación de calendario al <strong>cliente</strong><br>
                • Generar automáticamente un <strong>link de Google Meet</strong>
            </p>
        </div>
        
        <div style="background:#e0f7f4; padding:15px; border-left:4px solid #14b8a6; margin-top:10px; border-radius:4px;">
            <p style="margin:0;"><strong>💡 Sobre las Reuniones de Seguimiento</strong></p>
            <p style="margin:5px 0 0 0; color:#0f766e;">
                Este módulo está diseñado para programar reuniones con clientes que ya tuvieron una demo inicial.<br>
                El correo de invitación incluye los colores corporativos (turquesa) y toda la información necesaria para la reunión.
            </p>
        </div>
    </div>
   
    <script>
    jQuery(document).ready(function($) {
        
        // ========================================
        // Validación de disponibilidad en tiempo real
        // ========================================
        var availabilityTimeout;
        var $availabilityMsg = $('<div id="availability-message" style="margin-top:10px;padding:10px;border-radius:6px;display:none;"></div>');
        $('#meeting_time').after($availabilityMsg);
        
        function checkAvailability() {
            var date = $('#meeting_date').val();
            var time = $('#meeting_time').val();
            var meetingId = $('input[name="meeting_id"]').val() || '';
            
            if (!date || !time) {
                $availabilityMsg.hide();
                return;
            }
            
            $availabilityMsg.html('⏳ Verificando disponibilidad...').css({
                'background': '#fef3c7',
                'border': '1px solid #fbbf24',
                'color': '#92400e'
            }).show();
            
            $.post(ajaxurl, {
                action: 'followup_check_availability',
                date: date,
                time: time,
                meeting_id: meetingId,
                nonce: '<?php echo wp_create_nonce("followup_availability_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    if (response.data.available) {
                        $availabilityMsg.html('✅ Horario disponible').css({
                            'background': '#d1fae5',
                            'border': '1px solid #34d399',
                            'color': '#065f46'
                        });
                    } else {
                        var conflictType = response.data.conflict_type === 'demo' ? '🎯 DEMO' : '📋 Seguimiento';
                        $availabilityMsg.html('❌ <strong>Horario ocupado</strong><br><small>' + conflictType + ': ' + response.data.conflict_details + '</small>').css({
                            'background': '#fee2e2',
                            'border': '1px solid #f87171',
                            'color': '#991b1b'
                        });
                    }
                } else {
                    $availabilityMsg.html('⚠️ Error al verificar').css({
                        'background': '#fef3c7',
                        'border': '1px solid #fbbf24',
                        'color': '#92400e'
                    });
                }
            });
        }
        
        // Verificar al cambiar fecha u hora
        $('#meeting_date, #meeting_time').on('change', function() {
            clearTimeout(availabilityTimeout);
            availabilityTimeout = setTimeout(checkAvailability, 300);
        });
        
        // Verificar al cargar si hay valores
        if ($('#meeting_date').val() && $('#meeting_time').val()) {
            checkAvailability();
        }
        
        // ========================================
        // Auto-completar formulario al seleccionar cliente con propuesta
        // ========================================
        $('#proposal_client').change(function() {
            var selected = $(this).find(':selected');
            var val = $(this).val();
            
            if (val === '' || val === 'nuevo') {
                // Limpiar campos si selecciona "nuevo" o vacío
                if (val === 'nuevo') {
                    $('#client_name').val('').focus();
                    $('#client_email').val('');
                    $('#phone').val('');
                    $('#company_name').val('');
                }
                return;
            }
            
            // Auto-completar con datos del cliente seleccionado
            var name = selected.data('name') || '';
            var email = selected.data('email') || '';
            var company = selected.data('company') || '';
            var phone = selected.data('phone') || '';
            var proposal = selected.data('proposal') || '';
            var amount = selected.data('amount') || '';
            var date = selected.data('date') || '';
            
            $('#client_name').val(name);
            $('#client_email').val(email);
            $('#company_name').val(company);
            $('#phone').val(phone);
            
            // Agregar nota sobre la propuesta si existe
            var currentNotes = $('#notes').val();
            if (proposal && currentNotes.indexOf('Propuesta:') === -1) {
                var proposalInfo = '📋 Propuesta: ' + proposal;
                if (amount) proposalInfo += ' | Monto: $' + Number(amount).toLocaleString('es-CL');
                if (date) proposalInfo += ' | Fecha: ' + date;
                proposalInfo += '\n\n';
                $('#notes').val(proposalInfo + currentNotes);
            }
            
            // Feedback visual
            $(this).closest('div').css('background', 'linear-gradient(135deg, #d1fae5, #a7f3d0)');
            setTimeout(function() {
                $('#proposal_client').closest('div').css('background', 'linear-gradient(135deg, #f0fdfa, #ccfbf1)');
            }, 1500);
            
            console.log('✅ Cliente auto-completado:', name, email, company);
        });
        
        // Marcar como completada via AJAX
        $('.mark-complete').click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (!confirm('¿Marcar esta reunión como completada?')) return;
            
            $.post(ajaxurl, {
                action: 'followup_update_status',
                meeting_id: id,
                status: 'completed',
                nonce: '<?php echo wp_create_nonce("followup_status_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });
        
        // Reenviar email via AJAX
        $('.resend-email').click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (!confirm('¿Enviar correo de invitación a este cliente?')) return;
            
            var btn = $(this);
            btn.text('...');
            
            $.post(ajaxurl, {
                action: 'followup_send_email',
                meeting_id: id,
                nonce: '<?php echo wp_create_nonce("followup_email_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    alert('✅ Correo enviado correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.text('📧');
                }
            });
        });
        
        // Crear evento en calendario via AJAX (N8N)
        $('.create-calendar-event').click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (!confirm('¿Crear evento en Google Calendar y generar link de Meet?\n\nEsto enviará una invitación de calendario al cliente y a AutomatizaTech.')) return;
            
            var btn = $(this);
            btn.text('⏳');
            
            $.post(ajaxurl, {
                action: 'followup_create_calendar_event',
                meeting_id: id,
                nonce: '<?php echo wp_create_nonce("followup_calendar_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    var msg = '✅ Evento creado exitosamente en Google Calendar!';
                    if (response.data && response.data.meet_link) {
                        msg += '\n\n🔗 Link Meet: ' + response.data.meet_link;
                    }
                    alert(msg);
                    location.reload();
                } else {
                    alert('❌ Error al crear evento: ' + (response.data || 'Error desconocido'));
                    btn.text('📅');
                }
            }).fail(function() {
                alert('❌ Error de conexión con el servidor');
                btn.text('📅');
            });
        });
    });
    </script>
    <?php
}

/**
 * Enviar correo de invitación para reunión de seguimiento
 * Usa template corporativo con color turquesa
 */
function automatiza_tech_send_followup_email($meeting_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
    
    if (!$meeting) {
        return false;
    }
    
    // Datos del sitio
    $site_title = get_bloginfo('name');
    $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $footer_text = get_bloginfo('description');
    $whatsapp_url = 'https://wa.me/56927002984';
    $website_url = 'https://automatizatech.cl';
    $contact_email = 'contacto@automatizatech.cl';
    $contact_phone = '+56 9 2700 2984';
    
    // Formatear fecha y hora
    $formatted_date = date('d \d\e F \d\e Y', strtotime($meeting->meeting_date));
    $formatted_time = substr($meeting->meeting_time, 0, 5);
    
    // Día de la semana en español
    $days_es = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $day_num = date('w', strtotime($meeting->meeting_date));
    $day_name = $days_es[$day_num];
    
    // Meses en español
    $months_es = array('', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
    $month_num = date('n', strtotime($meeting->meeting_date));
    $day = date('d', strtotime($meeting->meeting_date));
    $year = date('Y', strtotime($meeting->meeting_date));
    $formatted_date = $day_name . ' ' . $day . ' de ' . $months_es[$month_num] . ' de ' . $year;
    
    // Link de Google Meet (si existe)
    $meet_section = '';
    if (!empty($meeting->meet_link)) {
        $meet_section = '
        <div style="background: linear-gradient(135deg, #0d9488, #14b8a6); border-radius: 12px; padding: 20px; margin: 25px 0; text-align: center;">
            <p style="color: #ffffff; margin: 0 0 15px 0; font-size: 16px;"><strong>🔗 Únete a la Reunión</strong></p>
            <a href="' . esc_url($meeting->meet_link) . '" 
               style="display: inline-block; background-color: #ffffff; color: #0d9488 !important; padding: 14px 32px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                📹 Unirse a Google Meet
            </a>
            <p style="color: rgba(255,255,255,0.9); margin: 15px 0 0 0; font-size: 12px;">
                ' . esc_html($meeting->meet_link) . '
            </p>
        </div>';
    }
    
    // Template HTML con colores corporativos TURQUESA
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: "Poppins", "Segoe UI", Arial, sans-serif; 
            background-color: #f0fdfa; 
            margin: 0; 
            padding: 0; 
            color: #333333; 
            line-height: 1.6;
        }
        .container { 
            max-width: 600px; 
            margin: 20px auto; 
            background-color: #ffffff; 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 10px 40px rgba(13, 148, 136, 0.15); 
        }
        .header { 
            background: linear-gradient(135deg, #0d9488, #14b8a6, #2dd4bf); 
            padding: 45px 20px; 
            text-align: center; 
        }
        .header img { 
            max-height: 70px; 
            width: auto; 
            margin-bottom: 15px;
            filter: brightness(0) invert(1);
        }
        .header h1 { 
            margin: 0; 
            font-size: 26px; 
            color: #ffffff; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header p {
            color: rgba(255,255,255,0.9);
            margin: 10px 0 0 0;
            font-size: 14px;
        }
        .content { 
            padding: 40px 35px; 
        }
        .greeting {
            font-size: 18px;
            color: #0d9488;
            margin-bottom: 20px;
        }
        .meeting-card {
            background: linear-gradient(135deg, #f0fdfa, #ccfbf1);
            border: 2px solid #14b8a6;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }
        .meeting-card h3 {
            color: #0f766e;
            margin: 0 0 20px 0;
            font-size: 18px;
            border-bottom: 2px solid #99f6e4;
            padding-bottom: 10px;
        }
        .meeting-detail {
            display: flex;
            align-items: center;
            margin: 12px 0;
            font-size: 15px;
        }
        .meeting-detail .icon {
            display: inline-block;
            margin-right: 12px;
            font-size: 20px;
            vertical-align: middle;
        }
        .meeting-detail strong {
            color: #0f766e;
        }
        .cta-container { 
            text-align: center; 
            margin: 30px 0; 
        }
        .btn { 
            display: inline-block; 
            padding: 14px 32px; 
            margin: 8px; 
            color: #ffffff !important; 
            text-decoration: none; 
            border-radius: 50px; 
            font-weight: 600; 
            font-size: 14px; 
            transition: all 0.3s ease; 
        }
        .btn-whatsapp { 
            background: linear-gradient(135deg, #25D366, #20bd5a); 
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3); 
        }
        .btn-calendar { 
            background: linear-gradient(135deg, #0d9488, #14b8a6); 
            box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3); 
        }
        .tips-box {
            background: #fefce8;
            border-left: 4px solid #eab308;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
        }
        .tips-box h4 {
            color: #854d0e;
            margin: 0 0 10px 0;
        }
        .tips-box ul {
            color: #713f12;
            margin: 0;
            padding-left: 20px;
        }
        .tips-box li {
            margin: 5px 0;
        }
        .footer { 
            background: linear-gradient(135deg, #f0fdfa, #e6fffa); 
            padding: 25px; 
            text-align: center; 
            font-size: 12px; 
            color: #666666;
            border-top: 1px solid #99f6e4;
        }
        .footer a { 
            color: #0d9488; 
            text-decoration: none; 
        }
        .footer p {
            margin: 5px 0;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            padding: 8px 16px;
            background: #14b8a6;
            color: #fff !important;
            border-radius: 20px;
            font-size: 11px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '">
            <h1>Reunión de Seguimiento</h1>
            <p>Tu próximo paso hacia la automatización</p>
        </div>
        <div class="content">
            <p class="greeting">Hola <strong>' . esc_html($meeting->client_name) . '</strong>,</p>
            
            <p>¡Excelente! Hemos programado nuestra reunión de seguimiento para continuar afinando los detalles de tu proyecto de automatización' . ($meeting->company_name ? ' con <strong>' . esc_html($meeting->company_name) . '</strong>' : '') . '.</p>
            
            <div class="meeting-card">
                <h3>Detalles de la Reunion</h3>
                
                <div class="meeting-detail">
                    <span class="icon">&#128197;</span>
                    <div>
                        <strong>Fecha:</strong> ' . $formatted_date . '
                    </div>
                </div>
                
                <div class="meeting-detail">
                    <span class="icon">&#128336;</span>
                    <div>
                        <strong>Hora:</strong> ' . $formatted_time . ' hrs (Chile)
                    </div>
                </div>
                
                <div class="meeting-detail">
                    <span class="icon">&#11088;</span>
                    <div>
                        <strong>Tema:</strong> ' . esc_html($meeting->meeting_subject) . '
                    </div>
                </div>
                
                <div class="meeting-detail">
                    <span class="icon">&#128187;</span>
                    <div>
                        <strong>Modalidad:</strong> Videollamada (Google Meet)
                    </div>
                </div>
            </div>
            
            ' . $meet_section . '
            
            <div class="tips-box">
                <h4 style="color:#0f766e;margin-bottom:10px;">&#128161; Para aprovechar al maximo la reunion:</h4>
                <ul>
                    <li>Prepara tus dudas o preguntas espec&iacute;ficas</li>
                    <li>Ten a mano informaci&oacute;n relevante de tu negocio</li>
                    <li>Aseg&uacute;rate de tener buena conexi&oacute;n a internet</li>
                    <li>Busca un lugar tranquilo para la llamada</li>
                </ul>
            </div>
            
            <p>Si necesitas reagendar o tienes alguna consulta previa, no dudes en contactarnos. &iexcl;Estamos aqu&iacute; para ayudarte!</p>
            
            <div class="cta-container">
                <a href="' . esc_url($whatsapp_url) . '?text=Hola,%20tengo%20una%20consulta%20sobre%20mi%20reunion%20de%20seguimiento" class="btn btn-whatsapp">&#128172; Escribenos por WhatsApp</a>
            </div>
            
            <p style="margin-top: 30px;">¡Nos vemos pronto!</p>
            <p><strong>El equipo de Automatiza Tech</strong> 🚀</p>
        </div>
        <div class="footer">
            <div class="social-links">
                <a href="' . esc_url($website_url) . '">🌐 Sitio Web</a>
                <a href="' . esc_url($whatsapp_url) . '">💬 WhatsApp</a>
            </div>
            <p>&copy; ' . date('Y') . ' ' . esc_html($site_title) . '. Todos los derechos reservados.</p>
            <p>' . esc_html($footer_text) . '</p>
            <p style="margin-top: 10px;">
                📧 <a href="mailto:' . esc_attr($contact_email) . '">' . esc_html($contact_email) . '</a> | 
                📱 ' . esc_html($contact_phone) . '
            </p>
            <p style="margin-top: 15px; font-size: 11px; color: #999;">
                Este correo fue enviado porque tienes una reunión programada con nosotros.<br>
                Si no reconoces esta cita, por favor contáctanos.
            </p>
        </div>
    </div>
</body>
</html>';

    // Configurar headers
    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <' . $from_email . '>',
        'Reply-To: ' . $contact_email,
        'Bcc: automatizacionesbotcore@gmail.com'
    );
    
    $subject = '📅 ' . $meeting->meeting_subject . ' - ' . $formatted_date;

    $recipients = automatiza_tech_get_followup_recipients($meeting);
    if (empty($recipients)) {
        return false;
    }

    return wp_mail($recipients, $subject, $html, $headers);
}

/**
 * AJAX Handler para actualizar estado de reunión
 */
function automatiza_tech_followup_update_status() {
    check_ajax_referer('followup_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = intval($_POST['meeting_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $valid_statuses = array('scheduled', 'completed', 'cancelled');
    if (!in_array($status, $valid_statuses)) {
        wp_send_json_error('Estado inválido');
    }
    
    $result = $wpdb->update(
        $table_name,
        array('status' => $status),
        array('id' => $meeting_id)
    );
    
    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Error al actualizar');
    }
}
add_action('wp_ajax_followup_update_status', 'automatiza_tech_followup_update_status');

/**
 * AJAX Handler para verificar disponibilidad de horario
 */
function automatiza_tech_followup_check_availability_ajax() {
    check_ajax_referer('followup_availability_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $meeting_id = isset($_POST['meeting_id']) && !empty($_POST['meeting_id']) ? intval($_POST['meeting_id']) : null;
    
    if (empty($date) || empty($time)) {
        wp_send_json_error('Fecha y hora son requeridas');
    }
    
    $result = automatiza_tech_check_slot_availability($date, $time, $meeting_id);
    
    wp_send_json_success($result);
}
add_action('wp_ajax_followup_check_availability', 'automatiza_tech_followup_check_availability_ajax');

/**
 * AJAX Handler para enviar correo de reunión
 */
function automatiza_tech_followup_send_email_ajax() {
    check_ajax_referer('followup_email_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = intval($_POST['meeting_id']);
    
    $sent = automatiza_tech_send_followup_email($meeting_id);
    
    if ($sent) {
        $wpdb->update($table_name, array('email_sent' => 1), array('id' => $meeting_id));
        wp_send_json_success();
    } else {
        wp_send_json_error('Error al enviar el correo');
    }
}
add_action('wp_ajax_followup_send_email', 'automatiza_tech_followup_send_email_ajax');

/**
 * Crear evento en Google Calendar via N8N Webhook
 * Crea el evento para el cliente y AutomatizaTech, genera link de Meet automáticamente
 * 
 * @param int $meeting_id ID de la reunión en la tabla followup_meetings
 * @return array Resultado con success, meet_link, event_id, message
 */
function automatiza_tech_create_followup_calendar_event($meeting_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
    
    if (!$meeting) {
        return array(
            'success' => false,
            'meet_link' => '',
            'event_id' => '',
            'message' => 'Reunión no encontrada'
        );
    }
    
    // URL del webhook de N8N para crear eventos de seguimiento
    // IMPORTANTE: Actualizar esta URL con la URL real del webhook de N8N en producción
    $n8n_webhook_url = 'https://n8n-n8n.kchiba.easypanel.host/webhook/followup-meeting';
    
    // Preparar datos para enviar a N8N
    $invitees_parsed = automatiza_tech_parse_invitees_emails((string) ($meeting->invitees_emails ?? ''));
    $payload = array(
        'meeting_id' => $meeting_id,
        'client_name' => $meeting->client_name,
        'client_email' => $meeting->client_email,
        'invitees_emails' => !empty($invitees_parsed['valid']) ? implode(',', $invitees_parsed['valid']) : '',
        'invitees_list' => $invitees_parsed['valid'],
        'company_name' => $meeting->company_name,
        'phone' => $meeting->phone,
        'meeting_date' => $meeting->meeting_date,
        'meeting_time' => $meeting->meeting_time,
        'meeting_subject' => $meeting->meeting_subject,
        'notes' => $meeting->notes
    );
    
    // Realizar petición HTTP a N8N
    $response = wp_remote_post($n8n_webhook_url, array(
        'method' => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($payload)
    ));
    
    // Verificar errores de conexión
    if (is_wp_error($response)) {
        error_log('Error conectando a N8N: ' . $response->get_error_message());
        return array(
            'success' => false,
            'meet_link' => '',
            'event_id' => '',
            'message' => 'Error de conexión: ' . $response->get_error_message()
        );
    }
    
    // Obtener código de respuesta
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    // Log para debugging
    error_log('N8N Followup Response Code: ' . $response_code);
    error_log('N8N Followup Response Body: ' . $response_body);
    
    // Parsear respuesta JSON
    $result = json_decode($response_body, true);
    
    if ($response_code === 200 && isset($result['success']) && $result['success']) {
        return array(
            'success' => true,
            'meet_link' => $result['meet_link'] ?? '',
            'event_id' => $result['event_id'] ?? '',
            'message' => $result['message'] ?? 'Evento creado exitosamente'
        );
    } else {
        return array(
            'success' => false,
            'meet_link' => '',
            'event_id' => '',
            'message' => $result['message'] ?? $result['error'] ?? 'Error desconocido de N8N (HTTP ' . $response_code . ')'
        );
    }
}

/**
 * REST API Endpoint para actualizar meet_link desde N8N
 * Permite que N8N actualice el link de Meet una vez creado el evento
 */
function automatiza_tech_register_followup_api_routes() {
    // Endpoint para obtener detalles de una reunión
    register_rest_route('automatiza-tech/v1', '/followup-meetings/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_followup_meeting',
        'permission_callback' => '__return_true', // N8N llama sin autenticación
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));
    
    // Endpoint para actualizar meet_link
    register_rest_route('automatiza-tech/v1', '/followup-meetings/(?P<id>\d+)/update-meet', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_update_followup_meet_link',
        'permission_callback' => '__return_true', // N8N llama sin autenticación
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));
    
    // Endpoint para verificar si un evento de Google Calendar existe en BD
    register_rest_route('automatiza-tech/v1', '/followup-meetings/check-event', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_check_event_exists',
        'permission_callback' => '__return_true',
        'args' => array(
            'event_id' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // Endpoint para confirmar asistencia desde WhatsApp
    register_rest_route('automatiza-tech/v1', '/followup-meetings/(?P<id>\d+)/confirm', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_confirm_followup_attendance',
        'permission_callback' => '__return_true', // N8N/WhatsApp llama sin autenticación
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));
    
    // Endpoint para cancelar reunión desde WhatsApp
    register_rest_route('automatiza-tech/v1', '/followup-meetings/(?P<id>\d+)/cancel', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_cancel_followup_meeting',
        'permission_callback' => '__return_true', // N8N/WhatsApp llama sin autenticación
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));
    
    // Endpoint para buscar reunión por email, fecha y hora (para reprogramación)
    register_rest_route('automatiza-tech/v1', '/followup-meetings/search', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_search_followup_meeting',
        'permission_callback' => '__return_true',
        'args' => array(
            'email' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_email'
            ),
            'date' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'time' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // Endpoint POST para crear reunión de seguimiento (desde N8N/WhatsApp)
    register_rest_route('automatiza-tech/v1', '/followup-meetings', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_create_followup_meeting_api',
        'permission_callback' => '__return_true',
        'args' => array(
            'name' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'email' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_email'
            ),
            'phone' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'date' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'time' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'subject' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'source' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // Endpoint para verificar si un teléfono es cliente (tiene propuesta)
    register_rest_route('automatiza-tech/v1', '/verify-client', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_verify_client_by_phone',
        'permission_callback' => '__return_true', // N8N/WhatsApp llama sin autenticación
        'args' => array(
            'phone' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // =========================================================================
    // ENDPOINTS PARA ACCIONES DESDE EMAIL (GET con pantalla visual)
    // =========================================================================
    
    // Confirmar asistencia desde Email
    register_rest_route('automatiza-tech/v1', '/followup-meetings/email-action/confirm/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_followup_email_confirm',
        'permission_callback' => '__return_true'
    ));
    
    // Reagendar desde Email  
    register_rest_route('automatiza-tech/v1', '/followup-meetings/email-action/reschedule/(?P<id>\d+)', array(
        'methods' => array('GET', 'POST'),
        'callback' => 'automatiza_tech_followup_email_reschedule',
        'permission_callback' => '__return_true'
    ));
    
    // Cancelar desde Email
    register_rest_route('automatiza-tech/v1', '/followup-meetings/email-action/cancel/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_followup_email_cancel',
        'permission_callback' => '__return_true'
    ));
    
    // POST para procesar reagendamiento
    register_rest_route('automatiza-tech/v1', '/followup-meetings/reschedule', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_followup_reschedule_api',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'automatiza_tech_register_followup_api_routes');

/**
 * Verificar si un teléfono corresponde a un cliente con propuesta
 * Busca en automatiza_propuestas por número de teléfono
 * 
 * @param WP_REST_Request $request
 * @return array
 */
function automatiza_tech_verify_client_by_phone($request) {
    global $wpdb;
    $propuestas_table = $wpdb->prefix . 'automatiza_propuestas';
    $leads_table = $wpdb->prefix . 'automatiza_leads';
    
    $phone = $request->get_param('phone');
    
    // Normalizar teléfono (eliminar espacios, guiones, etc.)
    $phone_normalized = preg_replace('/[^0-9+]/', '', $phone);
    
    // Buscar variantes del número (con y sin código de país)
    $phone_variants = array();
    $phone_variants[] = $phone_normalized;
    
    // Si empieza con +56, agregar versión sin código de país
    if (strpos($phone_normalized, '+56') === 0) {
        $phone_variants[] = substr($phone_normalized, 3); // Sin +56
        $phone_variants[] = '9' . substr($phone_normalized, 4); // Solo 9XXXXXXXX
    }
    // Si empieza con 56 (sin +), agregar versiones
    elseif (strpos($phone_normalized, '56') === 0 && strlen($phone_normalized) >= 11) {
        $phone_variants[] = '+' . $phone_normalized; // Con +
        $phone_variants[] = substr($phone_normalized, 2); // Sin 56
    }
    // Si empieza con 9, agregar versiones con código de país
    elseif (strpos($phone_normalized, '9') === 0 && strlen($phone_normalized) == 9) {
        $phone_variants[] = '+56' . $phone_normalized;
        $phone_variants[] = '56' . $phone_normalized;
    }
    
    // 1. Buscar en tabla de propuestas (clientes con propuesta enviada)
    $proposal = null;
    
    // Verificar si existe columna phone en propuestas
    $has_phone_col = $wpdb->get_var("SHOW COLUMNS FROM $propuestas_table LIKE 'phone'");
    
    if ($has_phone_col) {
        foreach ($phone_variants as $variant) {
            $proposal = $wpdb->get_row($wpdb->prepare(
                "SELECT id, client_name, client_email, company_name, phone, unique_link_id, created_at, status
                 FROM $propuestas_table 
                 WHERE phone LIKE %s
                 ORDER BY created_at DESC 
                 LIMIT 1",
                '%' . $wpdb->esc_like($variant) . '%'
            ));
            if ($proposal) break;
        }
    }
    
    // 2. Buscar en tabla de leads (demos agendadas) si no encontró en propuestas
    $lead = null;
    if (!$proposal) {
        foreach ($phone_variants as $variant) {
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, email, phone, company, created_at, status
                 FROM $leads_table 
                 WHERE phone LIKE %s
                 ORDER BY created_at DESC 
                 LIMIT 1",
                '%' . $wpdb->esc_like($variant) . '%'
            ));
            if ($lead) break;
        }
    }
    
    // Determinar resultado
    if ($proposal) {
        return array(
            'success' => true,
            'is_client' => true,
            'client_type' => 'proposal',
            'client_name' => $proposal->client_name ?: 'Cliente',
            'client_email' => $proposal->client_email,
            'company_name' => $proposal->company_name ?: '',
            'phone' => $proposal->phone,
            'proposal_id' => $proposal->unique_link_id,
            'message' => '¡Hola ' . ($proposal->client_name ?: 'Cliente') . '! Te reconozco como cliente. Podemos agendar tu reunión de seguimiento.'
        );
    }
    
    if ($lead) {
        // Tuvo demo pero aún no es cliente con propuesta
        return array(
            'success' => true,
            'is_client' => true,
            'client_type' => 'lead',
            'client_name' => $lead->name ?: 'Prospecto',
            'client_email' => $lead->email,
            'company_name' => $lead->company ?: '',
            'phone' => $lead->phone,
            'message' => '¡Hola ' . ($lead->name ?: '') . '! Veo que ya tuviste una demo con nosotros. Podemos agendar tu reunión de seguimiento.'
        );
    }
    
    // No es cliente
    return array(
        'success' => true,
        'is_client' => false,
        'client_type' => 'none',
        'client_name' => '',
        'client_email' => '',
        'message' => 'No encontré registros asociados a este número. Si deseas, puedo ayudarte a agendar una demo para conocer nuestros servicios.'
    );
}

/**
 * Callback para crear una reunión de seguimiento desde API (N8N/WhatsApp)
 */
function automatiza_tech_create_followup_meeting_api($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $name = $request->get_param('name');
    $email = $request->get_param('email');
    $phone = $request->get_param('phone') ?: '';
    $date = $request->get_param('date');
    $time = $request->get_param('time');
    $subject = $request->get_param('subject') ?: 'Reunión de Seguimiento';
    $source = $request->get_param('source') ?: 'whatsapp';
    $company_name = $request->get_param('company_name') ?: '';
    
    // Parámetros opcionales que vienen del subworkflow de N8N (ya tiene evento creado)
    $meet_link_param = $request->get_param('meet_link') ?: '';
    $google_event_id_param = $request->get_param('google_event_id') ?: '';
    
    // Validar email
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'El correo electrónico no es válido.', array('status' => 400));
    }
    
    // Validar fecha y hora
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return new WP_Error('invalid_date', 'Formato de fecha inválido. Use YYYY-MM-DD.', array('status' => 400));
    }
    
    // Normalizar tiempo a HH:MM
    $time_normalized = substr($time, 0, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $time_normalized)) {
        return new WP_Error('invalid_time', 'Formato de hora inválido. Use HH:MM.', array('status' => 400));
    }
    
    // Verificar disponibilidad cruzada (DEMOs y Seguimientos)
    $availability_check = automatiza_tech_check_slot_availability($date, $time_normalized, null);
    
    if (!$availability_check['available']) {
        $conflict_type = $availability_check['conflict_type'] === 'demo' ? 'DEMO' : 'reunión de seguimiento';
        return new WP_Error(
            'slot_taken', 
            'Este horario ya está ocupado por una ' . $conflict_type . '. Por favor selecciona otro horario.', 
            array('status' => 400, 'conflict_details' => $availability_check['conflict_details'])
        );
    }
    
    // Verificar límite de agendamientos por email (máximo 2 activos en total: DEMOs + Seguimientos)
    $current_datetime = current_time('mysql');
    $leads_table = $wpdb->prefix . 'automatiza_leads';
    
    // Contar seguimientos activos
    $followup_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE client_email = %s 
         AND CONCAT(meeting_date, ' ', meeting_time) >= %s
         AND status NOT IN ('cancelled', 'completed')",
        $email,
        $current_datetime
    ));
    
    // Contar DEMOs activas
    $demo_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $leads_table 
         WHERE email = %s 
         AND CONCAT(scheduled_date, ' ', scheduled_time) >= %s
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
        $email,
        $current_datetime
    ));
    
    $active_count = intval($followup_count) + intval($demo_count);
    
    if ($active_count >= 2) {
        return new WP_Error(
            'limit_reached', 
            'Ya tienes 2 reuniones activas (' . intval($demo_count) . ' demo(s) y ' . intval($followup_count) . ' seguimiento(s)). Cancela o espera a que pase alguna para agendar otra.', 
            array('status' => 400)
        );
    }
    
    // Insertar reunión
    $data = array(
        'client_name' => $name,
        'client_email' => $email,
        'phone' => $phone,
        'company_name' => $company_name,
        'meeting_date' => $date,
        'meeting_time' => $time_normalized . ':00',
        'meeting_subject' => $subject,
        'notes' => 'Agendada desde ' . $source,
        'status' => 'scheduled',
        'created_at' => current_time('mysql')
    );
    
    // Si ya vienen meet_link y google_event_id del subworkflow, incluirlos
    if (!empty($meet_link_param)) {
        $data['meet_link'] = $meet_link_param;
    }
    if (!empty($google_event_id_param)) {
        $data['google_event_id'] = $google_event_id_param;
    }
    
    $result = $wpdb->insert($table_name, $data);
    
    if ($result === false) {
        return new WP_Error('db_error', 'Error al guardar la reunión en base de datos.', array('status' => 500));
    }
    
    $meeting_id = $wpdb->insert_id;
    
    // Solo crear evento en Google Calendar si NO viene ya creado del subworkflow
    $calendar_result = null;
    $meet_link = $meet_link_param; // Usar el que viene del subworkflow si existe
    
    if (empty($meet_link_param) && empty($google_event_id_param)) {
        // No viene del subworkflow, crear evento via webhook
        try {
            $calendar_result = automatiza_tech_create_followup_calendar_event($meeting_id);
            if ($calendar_result && $calendar_result['success'] && !empty($calendar_result['meet_link'])) {
                $meet_link = $calendar_result['meet_link'];
                // Actualizar meet_link en la reunión
                $wpdb->update($table_name, array('meet_link' => $meet_link), array('id' => $meeting_id));
            }
        } catch (Exception $e) {
            // Log error pero no fallar la creación
            error_log('Error creando evento de calendario para followup meeting ' . $meeting_id . ': ' . $e->getMessage());
        }
    } else {
        error_log("Followup Meeting #{$meeting_id} - Evento ya creado por subworkflow (meet_link: {$meet_link_param})");
    }
    
    return array(
        'success' => true,
        'message' => '¡Tu reunión de seguimiento ha sido agendada exitosamente!',
        'meeting_id' => $meeting_id,
        'meeting_date' => $date,
        'meeting_time' => $time_normalized,
        'meet_link' => $meet_link,
        'result' => '✅ ¡Reunión agendada!' . "\n\n📅 Fecha: " . date('d/m/Y', strtotime($date)) . "\n⏰ Hora: " . $time_normalized . ' hrs' . ($meet_link ? "\n🔗 Meet: " . $meet_link : '') . "\n\nTe enviaremos un recordatorio antes de la reunión."
    );
}

/**
 * Callback para buscar una reunión de seguimiento por email, fecha y hora
 */
function automatiza_tech_search_followup_meeting($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $email = $request->get_param('email');
    $date = $request->get_param('date');
    $time = $request->get_param('time');
    
    // Buscar reunión activa con esos datos
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE client_email = %s 
         AND meeting_date = %s 
         AND meeting_time = %s 
         AND status NOT IN ('cancelled', 'completed')
         ORDER BY id DESC 
         LIMIT 1",
        $email,
        $date,
        $time
    ));
    
    if (!$meeting) {
        // Intentar buscar solo por email y fecha (sin hora exacta)
        $meeting = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE client_email = %s 
             AND meeting_date = %s 
             AND status NOT IN ('cancelled', 'completed')
             ORDER BY id DESC 
             LIMIT 1",
            $email,
            $date
        ));
    }
    
    if (!$meeting) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'No se encontró reunión de seguimiento con esos datos'
        ), 200);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'meeting' => array(
            'id' => $meeting->id,
            'client_name' => $meeting->client_name,
            'client_email' => $meeting->client_email,
            'company_name' => $meeting->company_name,
            'phone' => $meeting->phone,
            'meeting_date' => $meeting->meeting_date,
            'meeting_time' => $meeting->meeting_time,
            'meet_link' => $meeting->meet_link,
            'status' => $meeting->status
        )
    ), 200);
}

/**
 * Callback para obtener detalles de una reunión de seguimiento
 */
function automatiza_tech_get_followup_meeting($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = $request['id'];
    
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    if (!$meeting) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Reunión no encontrada'
        ), 404);
    }
    
    return new WP_REST_Response(array(
        'id' => $meeting->id,
        'client_name' => $meeting->client_name,
        'client_email' => $meeting->client_email,
        'company_name' => $meeting->company_name,
        'phone' => $meeting->phone,
        'meeting_date' => $meeting->meeting_date,
        'meeting_time' => $meeting->meeting_time,
        'meet_link' => $meeting->meet_link,
        'meeting_subject' => $meeting->meeting_subject,
        'notes' => $meeting->notes,
        'status' => $meeting->status
    ), 200);
}

/**
 * Callback para confirmar asistencia a reunión de seguimiento desde WhatsApp
 */
function automatiza_tech_confirm_followup_attendance($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = $request['id'];
    
    // Verificar que la reunión existe
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    if (!$meeting) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Reunión no encontrada'
        ), 404);
    }
    
    // Actualizar estado a confirmado
    $result = $wpdb->update(
        $table_name,
        array(
            'status' => 'confirmed',
            'confirmed_at' => current_time('mysql')
        ),
        array('id' => $meeting_id)
    );
    
    if ($result !== false) {
        // Log de la confirmación
        error_log("Followup Meeting #{$meeting_id} confirmado por WhatsApp - Cliente: {$meeting->client_name}");
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Asistencia confirmada correctamente',
            'meeting_id' => $meeting_id,
            'client_name' => $meeting->client_name,
            'status' => 'confirmed'
        ), 200);
    } else {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Error al confirmar la asistencia'
        ), 500);
    }
}

/**
 * Callback para actualizar el meet_link de una reunión desde N8N
 */
function automatiza_tech_update_followup_meet_link($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = $request['id'];
    $params = $request->get_json_params();
    
    $meet_link = isset($params['meet_link']) ? esc_url_raw($params['meet_link']) : '';
    $event_id = isset($params['event_id']) ? sanitize_text_field($params['event_id']) : '';
    
    if (empty($meet_link)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'meet_link es requerido'
        ), 400);
    }
    
    // Preparar datos a actualizar (incluir google_event_id si viene)
    $update_data = array('meet_link' => $meet_link);
    if (!empty($event_id)) {
        $update_data['google_event_id'] = $event_id;
    }
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $meeting_id)
    );
    
    if ($result !== false) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Meet link actualizado correctamente',
            'meeting_id' => $meeting_id,
            'meet_link' => $meet_link
        ), 200);
    } else {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Error al actualizar la reunión'
        ), 500);
    }
}

/**
 * Verificar si un evento de Google Calendar existe en la BD
 * Usado por el workflow de sincronización para detectar eventos huérfanos
 */
function automatiza_tech_check_event_exists($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $event_id = $request->get_param('event_id');
    
    // Buscar en tabla de seguimientos
    $exists_followup = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE google_event_id = %s AND status != 'cancelled'",
        $event_id
    ));
    
    // También buscar en tabla de leads (demos)
    $leads_table = $wpdb->prefix . 'automatiza_leads';
    $exists_demo = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $leads_table WHERE google_event_id = %s AND (status IS NULL OR status != 'cancelled')",
        $event_id
    ));
    
    $exists = (intval($exists_followup) > 0 || intval($exists_demo) > 0);
    
    return new WP_REST_Response(array(
        'exists' => $exists,
        'event_id' => $event_id,
        'in_followups' => intval($exists_followup) > 0,
        'in_demos' => intval($exists_demo) > 0
    ), 200);
}

/**
 * Callback para cancelar una reunión de seguimiento desde WhatsApp
 */
function automatiza_tech_cancel_followup_meeting($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = $request['id'];
    
    // Verificar que la reunión existe
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    if (!$meeting) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Reunión no encontrada'
        ), 404);
    }
    
    // Actualizar estado a cancelado
    $result = $wpdb->update(
        $table_name,
        array(
            'status' => 'cancelled',
            'updated_at' => current_time('mysql')
        ),
        array('id' => $meeting_id)
    );
    
    if ($result !== false) {
        // Log de la cancelación
        error_log("Followup Meeting #{$meeting_id} CANCELADO por WhatsApp - Cliente: {$meeting->client_name}");
        
        // Eliminar evento de Google Calendar si existe el ID
        if (!empty($meeting->google_event_id)) {
            automatiza_tech_delete_google_calendar_event($meeting->google_event_id);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Reunión cancelada correctamente',
            'meeting_id' => $meeting_id,
            'client_name' => $meeting->client_name,
            'status' => 'cancelled'
        ), 200);
    } else {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Error al cancelar la reunión'
        ), 500);
    }
}

/**
 * AJAX Handler para crear evento de calendario manualmente
 */
function automatiza_tech_create_calendar_event_ajax() {
    check_ajax_referer('followup_calendar_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = intval($_POST['meeting_id']);
    
    $result = automatiza_tech_create_followup_calendar_event($meeting_id);
    
    if ($result['success']) {
        // Actualizar meet_link en la base de datos
        if (!empty($result['meet_link'])) {
            $wpdb->update($table_name, array('meet_link' => $result['meet_link']), array('id' => $meeting_id));
        }
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_followup_create_calendar_event', 'automatiza_tech_create_calendar_event_ajax');

// =========================================================================
// FUNCIONES PARA ACCIONES DESDE EMAIL (SEGUIMIENTOS)
// =========================================================================

/**
 * Confirmar asistencia a seguimiento desde Email
 */
function automatiza_tech_followup_email_confirm($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = (int) $request['id'];
    
    // Obtener reunión
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    $site_title = get_bloginfo('name');
    $home_url = home_url();
    $logo_src = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    
    header('Content-Type: text/html; charset=UTF-8');
    
    if (!$meeting) {
        automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url, 
            'Reunión no encontrada', 
            'El enlace que utilizaste ya no es válido o la reunión no existe.');
        exit;
    }
    
    if ($meeting->status === 'cancelled') {
        automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
            'Reunión Cancelada',
            'Esta reunión ya fue cancelada anteriormente.');
        exit;
    }
    
    // Confirmar asistencia
    $wpdb->update(
        $table_name,
        array(
            'confirmed_attendance' => 1,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $meeting_id)
    );
    
    // Formatear fecha para mostrar
    $fecha_formateada = date('d/m/Y', strtotime($meeting->meeting_date));
    
    automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
        '¡Asistencia Confirmada!',
        "Gracias <strong>{$meeting->client_name}</strong>, tu asistencia ha sido confirmada.<br><br>
         <div style='background: #f0f4ff; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: left;'>
            <p style='margin: 5px 0;'><strong>📋 Proyecto:</strong> " . ($meeting->meeting_subject ?: 'Reunión de Seguimiento') . "</p>
            <p style='margin: 5px 0;'><strong>📅 Fecha:</strong> {$fecha_formateada}</p>
            <p style='margin: 5px 0;'><strong>🕐 Hora:</strong> {$meeting->meeting_time} hrs</p>
            <p style='margin: 5px 0;'><strong>📍 Enlace:</strong> " . ($meeting->meet_link ?: 'Te llegará por correo') . "</p>
         </div>
         ¡Te esperamos! 🚀",
        'success');
    exit;
}

/**
 * Cancelar seguimiento desde Email - PASO 1: Mostrar confirmación
 */
function automatiza_tech_followup_email_cancel($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = (int) $request['id'];
    $confirmed = $request->get_param('confirmed'); // Si viene ?confirmed=1 entonces procesar
    
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    $site_title = get_bloginfo('name');
    $home_url = home_url();
    $logo_src = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    
    header('Content-Type: text/html; charset=UTF-8');
    
    if (!$meeting) {
        automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
            'Reunión no encontrada',
            'El enlace que utilizaste ya no es válido o la reunión no existe.');
        exit;
    }
    
    if ($meeting->status === 'cancelled') {
        automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
            'Reunión ya cancelada',
            'Esta reunión ya había sido cancelada anteriormente.');
        exit;
    }
    
    // Si NO viene confirmación, mostrar página de confirmación
    if ($confirmed !== '1') {
        $fecha_formateada = date('d/m/Y', strtotime($meeting->meeting_date));
        $confirm_url = home_url("/wp-json/automatiza-tech/v1/followup-meetings/email-action/cancel/{$meeting_id}?confirmed=1");
        $reschedule_url = home_url("/wp-json/automatiza-tech/v1/followup-meetings/email-action/reschedule/{$meeting_id}");
        
        echo '<!DOCTYPE html>
        <html lang="' . get_bloginfo('language') . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmar Cancelación - ' . esc_html($site_title) . '</title>
            <style>
                body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                .card { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; overflow: hidden; }
                .header { background-color: #fca311; padding: 30px 20px; }
                .logo { max-height: 60px; width: auto; display: block; margin: 0 auto; }
                .content { padding: 40px 30px; }
                h2 { color: #333; margin-top: 0; }
                p { color: #555; font-size: 1rem; line-height: 1.6; margin-bottom: 1.5rem; }
                .info-box { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left; border-left: 4px solid #ffc107; }
                .info-box p { margin: 5px 0; font-size: 14px; }
                .btn-container { display: flex; flex-direction: column; gap: 10px; margin-top: 25px; }
                .btn { display: block; padding: 14px 30px; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; transition: all 0.3s; font-size: 14px; text-align: center; }
                .btn-danger { background-color: #dc3545; }
                .btn-danger:hover { background-color: #c82333; }
                .btn-secondary { background-color: #6c757d; }
                .btn-secondary:hover { background-color: #5a6268; }
                .btn-primary { background-color: #1e40af; }
                .btn-primary:hover { background-color: #15308a; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="header">
                    <img src="' . esc_url($logo_src) . '" alt="' . esc_attr($site_title) . '" class="logo">
                </div>
                <div class="content">
                    <h2>⚠️ ¿Confirmas la cancelación?</h2>
                    <p>Hola <strong>' . esc_html($meeting->client_name) . '</strong>, estás a punto de cancelar tu reunión de seguimiento.</p>
                    
                    <div class="info-box">
                        <p><strong>📋 Proyecto:</strong> ' . esc_html($meeting->meeting_subject ?: 'Reunión de Seguimiento') . '</p>
                        <p><strong>📅 Fecha:</strong> ' . $fecha_formateada . '</p>
                        <p><strong>🕐 Hora:</strong> ' . esc_html($meeting->meeting_time) . ' hrs</p>
                    </div>
                    
                    <p style="font-size: 14px; color: #666;">Esta acción no se puede deshacer. Si prefieres, puedes reagendar la reunión para otra fecha.</p>
                    
                    <div class="btn-container">
                        <a href="' . esc_url($confirm_url) . '" class="btn btn-danger">❌ Sí, cancelar definitivamente</a>
                        <a href="' . esc_url($reschedule_url) . '" class="btn btn-primary">🔄 Prefiero reagendar</a>
                        <a href="' . esc_url($home_url) . '" class="btn btn-secondary">← Volver al inicio</a>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        exit;
    }
    
    // Si viene confirmed=1, proceder con la cancelación
    $wpdb->update(
        $table_name,
        array(
            'status' => 'cancelled',
            'updated_at' => current_time('mysql')
        ),
        array('id' => $meeting_id)
    );
    
    // Eliminar de Google Calendar si existe
    if (!empty($meeting->google_event_id)) {
        automatiza_tech_delete_google_calendar_event($meeting->google_event_id);
    }
    
    error_log("Followup Meeting #{$meeting_id} CANCELADO desde Email (confirmado) - Cliente: {$meeting->client_name}");
    
    automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
        'Reunión Cancelada',
        "Lamentamos que no puedas asistir, <strong>{$meeting->client_name}</strong>.<br><br>
         Entendemos que surgen imprevistos. Te invitamos a seguir visitando nuestro sitio y, cuando estés listo, 
         volver a coordinar una reunión para continuar conversando sobre tu proyecto.<br><br>
         ¡Esperamos verte pronto!",
        'warning');
    exit;
}

/**
 * Reagendar seguimiento desde Email (mostrar formulario)
 */
function automatiza_tech_followup_email_reschedule($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = (int) $request['id'];
    
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    $site_title = get_bloginfo('name');
    $home_url = home_url();
    $logo_src = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    
    header('Content-Type: text/html; charset=UTF-8');
    
    if (!$meeting) {
        automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
            'Reunión no encontrada',
            'El enlace que utilizaste ya no es válido o la reunión no existe.');
        exit;
    }
    
    if ($meeting->status === 'cancelled') {
        automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url,
            'Reunión Cancelada',
            'Esta reunión fue cancelada y no se puede reagendar.');
        exit;
    }
    
    // Mostrar formulario de reagendamiento
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo get_bloginfo('language'); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reagendar Seguimiento - <?php echo esc_html($site_title); ?></title>
        <style>
            body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; overflow: hidden; }
            .header { background-color: #1e40af; padding: 30px 20px; }
            .logo { max-height: 60px; width: auto; display: block; margin: 0 auto; }
            .content { padding: 40px 30px; }
            h2 { color: #1e40af; margin-top: 0; }
            p { color: #555; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; }
            .form-group { margin-bottom: 15px; text-align: left; }
            label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #666; }
            input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
            .btn { display: block; width: 100%; padding: 12px 0; margin-top: 10px; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; transition: all 0.3s; font-size: 14px; border: none; cursor: pointer; }
            .btn-primary { background-color: #1e40af; }
            .btn-primary:hover { background-color: #15308a; }
            .btn-danger { background-color: white; color: #dc3545; border: 1px solid #dc3545; margin-top: 15px; }
            .btn-danger:hover { background-color: #fff5f5; }
            .loading { opacity: 0.6; pointer-events: none; }
            #message-box { margin-top: 15px; font-size: 13px; display: none; padding: 10px; border-radius: 6px; }
            .success { background: #d4edda; color: #155724; }
            .error { background: #f8d7da; color: #721c24; }
            .info-box { background: #f0f4ff; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: left; font-size: 13px; }
            .info-box strong { color: #1e40af; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="header">
                <img src="<?php echo esc_url($logo_src); ?>" alt="<?php echo esc_attr($site_title); ?>" class="logo">
            </div>
            <div class="content" id="reschedule-container">
                <h2>🔄 Reagendar Seguimiento</h2>
                <p>Hola <strong><?php echo esc_html($meeting->client_name); ?></strong>, selecciona una nueva fecha y hora para tu reunión.</p>
                
                <div class="info-box">
                    <p style="margin: 5px 0;"><strong>📋 Proyecto:</strong> <?php echo esc_html($meeting->meeting_subject ?: 'Reunión de Seguimiento'); ?></p>
                    <p style="margin: 5px 0;"><strong>📅 Cita actual:</strong> <?php echo date('d/m/Y', strtotime($meeting->meeting_date)); ?> a las <?php echo esc_html($meeting->meeting_time); ?> hrs</p>
                </div>
                
                <form id="reschedule-form">
                    <input type="hidden" id="meeting_id" value="<?php echo esc_attr($meeting_id); ?>">
                    
                    <div class="form-group">
                        <label>Nueva Fecha:</label>
                        <input type="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nuevo Horario:</label>
                        <select id="time" required disabled>
                            <option value="">Selecciona una fecha primero</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-btn">✅ Confirmar Nuevo Horario</button>
                </form>

                <div id="message-box"></div>

                <a href="<?php echo esc_url(home_url('/wp-json/automatiza-tech/v1/followup-meetings/email-action/cancel/' . $meeting_id)); ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('¿Estás seguro de que deseas cancelar definitivamente la reunión?');">
                   ❌ Cancelar Reunión Definitivamente
                </a>
            </div>
        </div>

        <script>
            const dateInput = document.getElementById('date');
            const timeSelect = document.getElementById('time');
            const form = document.getElementById('reschedule-form');
            const submitBtn = document.getElementById('submit-btn');
            const msgBox = document.getElementById('message-box');
            const meetingId = document.getElementById('meeting_id').value;

            dateInput.addEventListener('change', function() {
                const dateVal = this.value;
                if (!dateVal) return;

                timeSelect.innerHTML = '<option>Cargando horarios...</option>';
                timeSelect.disabled = true;

                fetch('/wp-json/automatiza-tech/v1/check-availability', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date: dateVal })
                })
                .then(response => response.json())
                .then(data => {
                    timeSelect.innerHTML = '';
                    if (data.isFullDay) {
                        timeSelect.innerHTML = '<option value="">Día completo - no disponible</option>';
                    } else {
                        const startHour = parseInt(data.workingHours.start.split(':')[0]); 
                        const endHour = parseInt(data.workingHours.end.split(':')[0]);
                        let hasSlots = false;
                        
                        // Verificar si la fecha seleccionada es hoy
                        const today = new Date();
                        const selectedDate = new Date(dateVal + 'T00:00:00');
                        const isToday = today.toDateString() === selectedDate.toDateString();
                        const currentHour = today.getHours();
                        
                        timeSelect.innerHTML = '<option value="">Selecciona una hora</option>';

                        for (let h = startHour; h < endHour; h++) {
                            // Si es hoy, solo mostrar horas futuras (al menos 1 hora después)
                            if (isToday && h <= currentHour) {
                                continue; // Saltar horas pasadas
                            }
                            
                            const timeStr = h.toString().padStart(2, '0') + ':00';
                            if (!data.busySlots.includes(timeStr)) {
                                const option = document.createElement('option');
                                option.value = timeStr;
                                option.textContent = timeStr + ' hrs';
                                timeSelect.appendChild(option);
                                hasSlots = true;
                            }
                        }
                        
                        if (!hasSlots) {
                            timeSelect.innerHTML = '<option value="">Sin horarios disponibles</option>';
                            timeSelect.disabled = true;
                        } else {
                            timeSelect.disabled = false;
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    timeSelect.innerHTML = '<option>Error al cargar</option>';
                });
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const date = dateInput.value;
                const time = timeSelect.value;

                if (!date || !time) return;

                submitBtn.textContent = 'Procesando...';
                submitBtn.classList.add('loading');

                fetch('/wp-json/automatiza-tech/v1/followup-meetings/reschedule', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ meeting_id: meetingId, date: date, time: time })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const [year, month, day] = date.split('-');
                        const formattedDate = day + '/' + month + '/' + year;

                        document.getElementById('reschedule-container').innerHTML = 
                            '<h2>✅ ¡Reunión Reagendada!</h2>' +
                            '<p>Tu nueva reunión ha sido confirmada para el <strong>' + formattedDate + '</strong> a las <strong>' + time + ' hrs</strong>.</p>' +
                            '<p>Recibirás un correo con los detalles y el nuevo enlace de Google Meet.</p>' +
                            '<a href="<?php echo esc_url($home_url); ?>" class="btn btn-primary">Volver al Inicio</a>';
                    } else {
                        throw new Error(data.message || 'Error desconocido');
                    }
                })
                .catch(err => {
                    msgBox.style.display = 'block';
                    msgBox.className = 'error';
                    msgBox.textContent = err.message;
                    submitBtn.textContent = '✅ Confirmar Nuevo Horario';
                    submitBtn.classList.remove('loading');
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * API para procesar reagendamiento de seguimiento
 */
function automatiza_tech_followup_reschedule_api($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $params = $request->get_json_params();
    $meeting_id = isset($params['meeting_id']) ? (int) $params['meeting_id'] : 0;
    $new_date = isset($params['date']) ? sanitize_text_field($params['date']) : '';
    $new_time = isset($params['time']) ? sanitize_text_field($params['time']) : '';
    
    if (!$meeting_id || !$new_date || !$new_time) {
        return array('success' => false, 'message' => 'Faltan datos para reagendar');
    }
    
    // Verificar que la reunión existe
    $meeting = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $meeting_id
    ));
    
    if (!$meeting) {
        return array('success' => false, 'message' => 'Reunión no encontrada');
    }
    
    if ($meeting->status === 'cancelled') {
        return array('success' => false, 'message' => 'No se puede reagendar una reunión cancelada');
    }
    
    // Verificar disponibilidad
    $availability_req = new WP_REST_Request('POST', '/automatiza-tech/v1/check-availability');
    $availability_req->set_param('date', $new_date);
    $availability = automatiza_tech_check_availability($availability_req);
    
    if (is_wp_error($availability) || (isset($availability['isFullDay']) && $availability['isFullDay'])) {
        return array('success' => false, 'message' => 'El día seleccionado no está disponible');
    }
    
    $time_check = substr($new_time, 0, 5);
    if (in_array($time_check, $availability['busySlots'])) {
        return array('success' => false, 'message' => 'El horario seleccionado ya no está disponible');
    }
    
    // Eliminar evento anterior de Google Calendar si existe (el workflow también lo hace, pero por si acaso)
    $old_event_id = $meeting->google_event_id;
    
    // Actualizar reunión (sin event_id y meet_link, se actualizarán desde el workflow)
    $update_data = array(
        'meeting_date' => $new_date,
        'meeting_time' => $new_time,
        'status' => 'scheduled',
        'google_event_id' => null, // Se creará nuevo evento desde N8N
        'meet_link' => null, // Se generará nuevo link desde N8N
        'recordatorio_8pm' => 0,
        'recordatorio_8am' => 0,
        'recordatorio_8pm_wa' => 0,
        'recordatorio_8am_wa' => 0,
        'confirmed_attendance_8am' => 0,
        'confirmed_attendance_8am_wa' => 0,
        'updated_at' => current_time('mysql')
    );
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $meeting_id)
    );
    
    if ($result === false) {
        error_log("Error al actualizar followup meeting #{$meeting_id}: " . $wpdb->last_error);
        return array('success' => false, 'message' => 'Error al actualizar la reunión: ' . $wpdb->last_error);
    }
    
    error_log("Followup Meeting #{$meeting_id} REAGENDADO desde Email - Nueva fecha: {$new_date} {$new_time}");
    
    // Llamar al workflow de N8N para crear evento en Calendar, enviar email y WhatsApp
    $n8n_result = automatiza_tech_call_followup_reschedule_workflow($meeting, $new_date, $new_time, $old_event_id);
    
    if (!$n8n_result['success']) {
        error_log("Error al llamar workflow de reagendamiento: " . $n8n_result['message'] . " - Enviando correo como fallback");
        // FALLBACK: Si el workflow falla, enviar correo directamente
        automatiza_tech_send_followup_reschedule_email($meeting_id, $new_date, $new_time);
    }
    
    return array(
        'success' => true,
        'message' => 'Reunión reagendada correctamente',
        'meeting_id' => $meeting_id,
        'new_date' => $new_date,
        'new_time' => $new_time,
        'n8n_result' => $n8n_result
    );
}

/**
 * Llamar al workflow de N8N para procesar reagendamiento de seguimiento
 */
function automatiza_tech_call_followup_reschedule_workflow($meeting, $new_date, $new_time, $old_event_id = null) {
    $webhook_url = 'https://n8n-n8n.kchiba.easypanel.host/webhook/followup-reschedule';
    
    $payload = array(
        'meeting_id' => $meeting->id,
        'date' => $new_date,
        'time' => $new_time,
        'client_email' => $meeting->client_email,
        'client_name' => $meeting->client_name,
        'company_name' => $meeting->company_name,
        'phone' => $meeting->phone,
        'meeting_subject' => $meeting->meeting_subject ?: 'Reunión de Seguimiento',
        'old_event_id' => $old_event_id
    );
    
    // Debug: Log del payload que se envía
    error_log("FOLLOWUP RESCHEDULE WEBHOOK - Payload enviado: " . json_encode($payload));
    
    $response = wp_remote_post($webhook_url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($payload)
    ));
    
    if (is_wp_error($response)) {
        error_log("FOLLOWUP RESCHEDULE WEBHOOK - Error conexión: " . $response->get_error_message());
        return array(
            'success' => false,
            'message' => 'Error de conexión: ' . $response->get_error_message()
        );
    }
    
    $body = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);
    
    // Debug: Log de la respuesta
    error_log("FOLLOWUP RESCHEDULE WEBHOOK - HTTP {$http_code} - Respuesta: " . $body);
    
    $data = json_decode($body, true);
    
    if (isset($data['success']) && $data['success']) {
        return array(
            'success' => true,
            'message' => $data['message'] ?? 'Workflow ejecutado correctamente',
            'meet_link' => $data['meet_link'] ?? null,
            'event_id' => $data['event_id'] ?? null
        );
    }
    
    return array(
        'success' => false,
        'message' => $data['message'] ?? 'Error desconocido del workflow'
    );
}

/**
 * Enviar correo de confirmación de reagendamiento de seguimiento
 */
function automatiza_tech_send_followup_reschedule_email($meeting_id, $new_date, $new_time) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
    
    if (!$meeting) {
        error_log("No se encontró meeting #{$meeting_id} para enviar email de reagendamiento");
        return false;
    }
    
    // Datos del sitio
    $site_title = get_bloginfo('name');
    $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $whatsapp_url = 'https://wa.me/56927002984';
    $website_url = 'https://automatizatech.cl';
    $contact_email = 'contacto@automatizatech.cl';
    $contact_phone = '+56 9 2700 2984';
    
    // Formatear fecha
    $days_es = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $months_es = array('', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
    
    $day_num = date('w', strtotime($new_date));
    $day_name = $days_es[$day_num];
    $month_num = date('n', strtotime($new_date));
    $day = date('d', strtotime($new_date));
    $year = date('Y', strtotime($new_date));
    $formatted_date = $day_name . ' ' . $day . ' de ' . $months_es[$month_num] . ' de ' . $year;
    $formatted_time = substr($new_time, 0, 5);
    
    $client_name = $meeting->client_name ?: 'Cliente';
    $subject_text = $meeting->meeting_subject ?: 'Reunión de Seguimiento';
    
    // Template HTML
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f0fdfa; margin: 0; padding: 0; color: #333;">
    <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(13, 148, 136, 0.15);">
        
        <div style="background: linear-gradient(135deg, #0d9488, #14b8a6); padding: 40px 20px; text-align: center;">
            <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '" style="max-height: 60px; width: auto; margin-bottom: 15px;">
            <h1 style="margin: 0; font-size: 24px; color: #ffffff;">🔄 Reunión Reagendada</h1>
        </div>
        
        <div style="padding: 35px;">
            <p style="font-size: 16px; margin-bottom: 20px;">
                Hola <strong>' . esc_html($client_name) . '</strong>,
            </p>
            
            <p style="font-size: 15px; margin-bottom: 25px;">
                Tu reunión de <strong>' . esc_html($subject_text) . '</strong> ha sido reagendada exitosamente.
            </p>
            
            <div style="background: linear-gradient(135deg, #f0fdfa, #ccfbf1); border-radius: 12px; padding: 25px; margin: 25px 0; border-left: 4px solid #0d9488;">
                <p style="margin: 0 0 12px 0; font-size: 15px;">
                    <strong>📅 Nueva Fecha:</strong> ' . $formatted_date . '
                </p>
                <p style="margin: 0 0 12px 0; font-size: 15px;">
                    <strong>🕐 Hora:</strong> ' . $formatted_time . ' hrs (Chile)
                </p>
                <p style="margin: 0; font-size: 15px;">
                    <strong>📋 Proyecto:</strong> ' . esc_html($subject_text) . '
                </p>
            </div>
            
            <div style="background: #fffbeb; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                <p style="margin: 0; font-size: 14px; color: #92400e;">
                    📹 <strong>Nota:</strong> El enlace de Google Meet para tu reunión se generará automáticamente y lo recibirás en un correo de recordatorio.
                </p>
            </div>
            
            <p style="font-size: 14px; color: #666; margin-top: 25px;">
                Si tienes alguna pregunta, no dudes en contactarnos por WhatsApp.
            </p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . esc_url($whatsapp_url) . '" style="display: inline-block; background-color: #25d366; color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 14px;">
                    💬 Contactar por WhatsApp
                </a>
            </div>
        </div>
        
        <div style="background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748b;">
                ' . esc_html($site_title) . ' | ' . esc_html($contact_email) . '
            </p>
            <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                <a href="' . esc_url($website_url) . '" style="color: #0d9488; text-decoration: none;">' . esc_html($website_url) . '</a>
            </p>
        </div>
    </div>
</body>
</html>';

    // Enviar correo
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_title . ' <noreply@automatizatech.cl>'
    );
    
    $email_subject = '🔄 Reunión Reagendada - ' . $formatted_date . ' a las ' . $formatted_time . ' hrs';

    $recipients = automatiza_tech_get_followup_recipients($meeting);
    if (empty($recipients)) {
        error_log("No hay destinatarios válidos para email de reagendamiento en meeting #{$meeting_id}");
        return false;
    }

    $sent = wp_mail($recipients, $email_subject, $html, $headers);

    if ($sent) {
        error_log("Email de reagendamiento enviado a " . implode(', ', $recipients) . " para meeting #{$meeting_id}");
    } else {
        error_log("Error al enviar email de reagendamiento a " . implode(', ', $recipients));
    }
    
    return $sent;
}

/**
 * Helper para renderizar página de acción de seguimiento
 */
function automatiza_tech_render_followup_action_page($logo_src, $site_title, $home_url, $title, $message, $type = 'info') {
    $header_color = '#1e40af';
    if ($type === 'success') $header_color = '#06d6a0';
    if ($type === 'warning') $header_color = '#fca311';
    if ($type === 'error') $header_color = '#dc3545';
    
    echo '<!DOCTYPE html>
    <html lang="' . get_bloginfo('language') . '">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . esc_html($title) . ' - ' . esc_html($site_title) . '</title>
        <style>
            body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; overflow: hidden; }
            .header { background-color: ' . $header_color . '; padding: 30px 20px; }
            .logo { max-height: 60px; width: auto; display: block; margin: 0 auto; }
            .content { padding: 40px 30px; }
            h2 { color: #333; margin-top: 0; }
            p { color: #555; font-size: 1rem; line-height: 1.6; margin-bottom: 1.5rem; }
            .btn { display: inline-block; padding: 12px 30px; background-color: #1e40af; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; transition: background 0.3s; font-size: 14px; }
            .btn:hover { background-color: #15308a; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="header">
                <img src="' . esc_url($logo_src) . '" alt="' . esc_attr($site_title) . '" class="logo">
            </div>
            <div class="content">
                <h2>' . $title . '</h2>
                <p>' . $message . '</p>
                <a href="' . esc_url($home_url) . '" class="btn">Volver al Inicio</a>
            </div>
        </div>
    </body>
    </html>';
}

?>
