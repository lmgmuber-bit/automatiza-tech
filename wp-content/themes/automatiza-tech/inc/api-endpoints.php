<?php
/**
 * Custom REST API Endpoints
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Desactivar caché para endpoints de recordatorios
 */
add_filter('rest_post_dispatch', function($response, $server, $request) {
    $route = $request->get_route();
    
    // Desactivar caché para rutas de recordatorios
    if (strpos($route, '/leads/reminders') !== false) {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        $response->header('X-No-Cache', 'true');
    }
    
    return $response;
}, 10, 3);

/**
 * Normalizar teléfono a formato internacional
 * Detecta el país por el prefijo y normaliza el formato
 * Prefijos soportados: América del Sur, Central, Caribe y otros países
 */
function automatiza_tech_normalize_phone($phone) {
    // Remover espacios, guiones y paréntesis
    $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
    
    // Si está vacío, retornar vacío
    if (empty($phone)) {
        return '';
    }
    
    // Si ya tiene + al inicio, asumir que está formateado correctamente
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    
    // Lista de prefijos de países soportados (ordenados por longitud descendente para matching)
    $country_prefixes = array(
        // Prefijos de 4 dígitos
        '1787' => '+1787',  // Puerto Rico
        '1809' => '+1809',  // Rep. Dominicana
        // Prefijos de 3 dígitos
        '591' => '+591',    // Bolivia
        '593' => '+593',    // Ecuador
        '594' => '+594',    // Guyana Francesa
        '592' => '+592',    // Guyana
        '595' => '+595',    // Paraguay
        '597' => '+597',    // Surinam
        '598' => '+598',    // Uruguay
        '501' => '+501',    // Belice
        '506' => '+506',    // Costa Rica
        '503' => '+503',    // El Salvador
        '502' => '+502',    // Guatemala
        '504' => '+504',    // Honduras
        '505' => '+505',    // Nicaragua
        '507' => '+507',    // Panamá
        '509' => '+509',    // Haití
        '351' => '+351',    // Portugal
        // Prefijos de 2 dígitos
        '54' => '+54',      // Argentina
        '55' => '+55',      // Brasil
        '56' => '+56',      // Chile
        '57' => '+57',      // Colombia
        '51' => '+51',      // Perú
        '58' => '+58',      // Venezuela
        '52' => '+52',      // México
        '53' => '+53',      // Cuba
        '34' => '+34',      // España
        '44' => '+44',      // Reino Unido
        '33' => '+33',      // Francia
        // Prefijos de 1 dígito
        '1' => '+1',        // USA/Canadá
    );
    
    // Intentar detectar prefijo existente en el número
    foreach ($country_prefixes as $prefix => $formatted) {
        if (strpos($phone, $prefix) === 0) {
            // Ya tiene el prefijo, solo agregar +
            return '+' . $phone;
        }
    }
    
    // Si no tiene prefijo detectado, asumir Chile (+56) por defecto
    // Celulares chilenos empiezan con 9 y tienen 9 dígitos
    if (preg_match('/^9\d{8}$/', $phone)) {
        return '+56' . $phone;
    }
    
    // Teléfonos fijos chilenos (2 para Santiago, otros para regiones)
    if (preg_match('/^[2-9]\d{8}$/', $phone)) {
        return '+56' . $phone;
    }
    
    // Si no coincide con ningún patrón, retornar con +56 por defecto (Chile)
    // ya que es el mercado principal
    if (preg_match('/^\d{8,9}$/', $phone)) {
        return '+56' . $phone;
    }
    
    // Si no coincide con nada, retornar como está
    return $phone;
}

/**
 * Parsear correos para reminders.
 *
 * Formato soportado:
 * correo1,correo2 / nombre1,nombre2
 */
function automatiza_tech_parse_reminder_emails($raw_emails) {
    $raw = trim((string) $raw_emails);
    if ($raw === '') {
        return array();
    }

    if (strpos($raw, '/') !== false) {
        $split = explode('/', $raw, 2);
        $raw = trim($split[0]);
    }

    $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $valid = array();

    foreach ($parts as $part) {
        $email = sanitize_email(trim($part));
        if ($email !== '' && is_email($email)) {
            $valid[] = strtolower($email);
        }
    }

    return array_values(array_unique($valid));
}

/**
 * Obtener destinatarios consolidados para recordatorios de leads.
 */
function automatiza_tech_get_lead_reminder_recipients($lead) {
    $recipients = array();

    if (isset($lead->email)) {
        $recipients = array_merge($recipients, automatiza_tech_parse_reminder_emails($lead->email));
    }

    $optional_fields = array('invitees_emails', 'copied_emails', 'cc_email', 'secondary_email', 'email_copy');
    foreach ($optional_fields as $field) {
        if (isset($lead->{$field}) && !empty($lead->{$field})) {
            $recipients = array_merge($recipients, automatiza_tech_parse_reminder_emails($lead->{$field}));
        }
    }

    return array_values(array_unique($recipients));
}

/**
 * Obtener perfiles de destinatarios para reminders de leads.
 */
function automatiza_tech_get_lead_reminder_recipient_profiles($lead) {
    $profiles = array();

    if (isset($lead->email) && !empty($lead->email)) {
        $main_email = sanitize_email($lead->email);
        if ($main_email !== '' && is_email($main_email)) {
            $profiles[strtolower($main_email)] = array(
                'email' => strtolower($main_email),
                'name' => !empty($lead->name) ? sanitize_text_field($lead->name) : 'Cliente',
            );
        }
    }

    $invitees = automatiza_tech_parse_reminder_emails($lead->invitees_emails ?? '');
    $invitee_names = array();
    if (!empty($lead->invitees_names)) {
        $invitee_names = preg_split('/\s*[;,]\s*|\r\n|\r|\n/', (string) $lead->invitees_names, -1, PREG_SPLIT_NO_EMPTY);
        $invitee_names = array_map('trim', $invitee_names);
    }

    foreach ($invitees as $index => $invitee_email) {
        $name = isset($invitee_names[$index]) ? sanitize_text_field($invitee_names[$index]) : 'Participante';
        $profiles[$invitee_email] = array(
            'email' => $invitee_email,
            'name' => $name !== '' ? $name : 'Participante',
        );
    }

    $optional_fields = array('copied_emails', 'cc_email', 'secondary_email', 'email_copy');
    foreach ($optional_fields as $field) {
        if (isset($lead->{$field}) && !empty($lead->{$field})) {
            foreach (automatiza_tech_parse_reminder_emails($lead->{$field}) as $extra_email) {
                if (!isset($profiles[$extra_email])) {
                    $profiles[$extra_email] = array(
                        'email' => $extra_email,
                        'name' => 'Participante',
                    );
                }
            }
        }
    }

    return array_values($profiles);
}

/**
 * Parsear copias con soporte de nombres para almacenamiento.
 */
function automatiza_tech_parse_copy_input_with_names($raw_input) {
    $raw = trim((string) $raw_input);
    if ($raw === '') {
        return array(
            'valid' => array(),
            'invalid' => array(),
            'names' => array(),
            'names_by_email' => array(),
        );
    }

    $emails_part = $raw;
    $names_part = '';
    if (strpos($raw, '/') !== false) {
        $split = explode('/', $raw, 2);
        $emails_part = trim($split[0]);
        $names_part = trim($split[1]);
    }

    $parts = preg_split('/[\s,;]+/', $emails_part, -1, PREG_SPLIT_NO_EMPTY);
    $name_parts = array();
    if ($names_part !== '') {
        $name_parts = preg_split('/\s*[;,]\s*|\r\n|\r|\n/', $names_part, -1, PREG_SPLIT_NO_EMPTY);
        $name_parts = array_map('trim', $name_parts);
    }

    $valid = array();
    $invalid = array();
    $names_by_email = array();

    foreach ($parts as $index => $part) {
        $candidate = trim($part);
        $email = sanitize_email($candidate);
        $display_name = isset($name_parts[$index]) ? sanitize_text_field($name_parts[$index]) : '';

        if ($email !== '' && is_email($email)) {
            $email_key = strtolower($email);
            if (!in_array($email_key, $valid, true)) {
                $valid[] = $email_key;
            }
            if ($display_name !== '' && !isset($names_by_email[$email_key])) {
                $names_by_email[$email_key] = $display_name;
            }
        } else {
            $invalid[] = $candidate;
        }
    }

    $ordered_names = array();
    foreach ($valid as $email_key) {
        $ordered_names[] = isset($names_by_email[$email_key]) ? $names_by_email[$email_key] : '';
    }

    return array(
        'valid' => array_values(array_unique($valid)),
        'invalid' => array_values(array_unique($invalid)),
        'names' => $ordered_names,
        'names_by_email' => $names_by_email,
    );
}

/**
 * Obtener destinatarios consolidados para recordatorios de seguimiento.
 */
function automatiza_tech_get_followup_reminder_recipients($meeting) {
    $recipients = array();

    if (isset($meeting->email)) {
        $recipients = array_merge($recipients, automatiza_tech_parse_reminder_emails($meeting->email));
    }

    if (isset($meeting->invitees_emails) && !empty($meeting->invitees_emails)) {
        $recipients = array_merge($recipients, automatiza_tech_parse_reminder_emails($meeting->invitees_emails));
    }

    return array_values(array_unique($recipients));
}

/**
 * Obtener perfiles de destinatarios para reminders de seguimiento.
 */
function automatiza_tech_get_followup_reminder_recipient_profiles($meeting) {
    $profiles = array();

    if (isset($meeting->email) && !empty($meeting->email)) {
        $main_email = sanitize_email($meeting->email);
        if ($main_email !== '' && is_email($main_email)) {
            $profiles[strtolower($main_email)] = array(
                'email' => strtolower($main_email),
                'name' => !empty($meeting->name) ? sanitize_text_field($meeting->name) : 'Cliente',
            );
        }
    }

    $invitees = automatiza_tech_parse_reminder_emails($meeting->invitees_emails ?? '');
    $invitee_names = array();
    if (!empty($meeting->invitees_names)) {
        $invitee_names = preg_split('/\s*[;,]\s*|\r\n|\r|\n/', (string) $meeting->invitees_names, -1, PREG_SPLIT_NO_EMPTY);
        $invitee_names = array_map('trim', $invitee_names);
    }

    foreach ($invitees as $index => $invitee_email) {
        $name = isset($invitee_names[$index]) ? sanitize_text_field($invitee_names[$index]) : 'Participante';
        $profiles[$invitee_email] = array(
            'email' => $invitee_email,
            'name' => $name !== '' ? $name : 'Participante',
        );
    }

    return array_values($profiles);
}

/**
 * Adjuntar metadatos de destinatarios a payload de reminder.
 */
function automatiza_tech_attach_recipient_metadata(&$entity, $type = 'lead') {
    $profiles = ($type === 'followup')
        ? automatiza_tech_get_followup_reminder_recipient_profiles($entity)
        : automatiza_tech_get_lead_reminder_recipient_profiles($entity);

    $emails = array_values(array_unique(array_map(function($profile) {
        return strtolower($profile['email']);
    }, $profiles)));

    $entity->recipient_profiles = $profiles;
    $entity->reminder_recipients = $emails;
    $entity->recipient_count = count($emails);

    if (!empty($emails)) {
        $entity->email = implode(',', $emails);
    }
}

/**
 * Adjuntar solo titular como destinatario (uso específico para flujos WhatsApp).
 */
function automatiza_tech_attach_primary_recipient_metadata(&$entity) {
    $primary_email = isset($entity->email) ? sanitize_email((string) $entity->email) : '';
    $primary_name = isset($entity->name) ? sanitize_text_field((string) $entity->name) : 'Cliente';

    $profiles = array();
    $emails = array();

    if ($primary_email !== '' && is_email($primary_email)) {
        $primary_email = strtolower($primary_email);
        $profiles[] = array(
            'email' => $primary_email,
            'name' => $primary_name !== '' ? $primary_name : 'Cliente',
        );
        $emails[] = $primary_email;
    }

    $entity->recipient_profiles = $profiles;
    $entity->reminder_recipients = $emails;
    $entity->recipient_count = count($emails);

    if (!empty($emails)) {
        $entity->email = $emails[0];
    }
}

/**
 * Eliminar evento de Google Calendar vía N8N webhook
 * 
 * @param string $event_id ID del evento de Google Calendar
 * @return bool True si se eliminó correctamente
 */
function automatiza_tech_delete_google_calendar_event($event_id) {
    if (empty($event_id)) {
        return false;
    }
    
    $webhook_url = 'https://n8n-n8n.kchiba.easypanel.host/webhook/delete-calendar-event';
    
    $response = wp_remote_post($webhook_url, array(
        'method' => 'POST',
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array('event_id' => $event_id))
    ));
    
    if (is_wp_error($response)) {
        error_log('Error eliminando evento de Google Calendar: ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    return $response_code >= 200 && $response_code < 300;
}

/**
 * Register API routes
 */
add_action('rest_api_init', function () {
    // Endpoint para obtener el tipo de cambio actual
    register_rest_route('automatiza-tech/v1', '/exchange-rate', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_exchange_rate',
        'permission_callback' => '__return_true' // Endpoint público
    ));

    // Endpoint para guardar leads desde el Chat
    register_rest_route('automatiza-tech/v1', '/leads', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_save_lead',
        'permission_callback' => '__return_true' // Endpoint público (validar origen si es necesario)
    ));

    // Endpoint para verificar disponibilidad (Check Availability)
    register_rest_route('automatiza-tech/v1', '/check-availability', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_check_availability',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para verificar límite de agendamientos
    register_rest_route('automatiza-tech/v1', '/check-limit', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_check_booking_limit',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para obtener leads para recordatorios por CORREO
    // Modificado para aceptar parámetro en la URL (ej: /leads/reminders/72h) para evitar problemas de query params
    register_rest_route('automatiza-tech/v1', '/leads/reminders(?:/(?P<type>[a-zA-Z0-9]+))?', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_for_reminders',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para obtener leads para recordatorios por WHATSAPP
    // Usa campos separados: recordatorio72h_wa, recordatorio24h_wa, recordatorio1h_wa
    register_rest_route('automatiza-tech/v1', '/leads/reminders-wa(?:/(?P<type>[a-zA-Z0-9]+))?', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_for_reminders_wa',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para recordatorio 8PM (citas del día siguiente) - EMAIL
    register_rest_route('automatiza-tech/v1', '/leads/reminders-8pm', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_reminder_8pm',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para recordatorio 8AM (citas del mismo día) - EMAIL
    register_rest_route('automatiza-tech/v1', '/leads/reminders-8am', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_reminder_8am',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para recordatorio 8PM (citas del día siguiente) - WHATSAPP
    register_rest_route('automatiza-tech/v1', '/leads/reminders-wa-8pm', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_reminder_8pm_wa',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para recordatorio 8AM (citas del mismo día) - WHATSAPP
    register_rest_route('automatiza-tech/v1', '/leads/reminders-wa-8am', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_reminder_8am_wa',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para marcar recordatorio 8PM/8AM como enviado - EMAIL
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder-daily/(?P<lead_id>\d+)/(?P<type>8pm|8am)', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_daily_sent',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para marcar recordatorio 8PM/8AM como enviado - WHATSAPP
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder-wa-daily/(?P<lead_id>\d+)/(?P<type>8pm|8am)', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_daily_sent_wa',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para actualizar estado de recordatorio por CORREO (Ruta con parámetros obligatorios en URL)
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder/(?P<lead_id>\d+)/(?P<type>[a-zA-Z0-9]+)', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_sent',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para actualizar estado de recordatorio por WHATSAPP
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder-wa/(?P<lead_id>\d+)/(?P<type>[a-zA-Z0-9]+)', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_sent_wa',
        'permission_callback' => '__return_true'
    ));

    // Endpoint FALLBACK para actualizar estado (para compatibilidad con versiones anteriores de n8n)
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_sent',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para acciones de usuario (Confirmar/Rechazar/Eliminar)
    register_rest_route('automatiza-tech/v1', '/leads/action', array(
        'methods' => 'GET', // GET para que funcione desde enlaces de correo
        'callback' => 'automatiza_tech_handle_lead_action',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para reagendar cita
    register_rest_route('automatiza-tech/v1', '/leads/reschedule', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_reschedule_lead',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para confirmar asistencia desde WhatsApp (recordatorios)
    register_rest_route('automatiza-tech/v1', '/leads/confirm-attendance/(?P<lead_id>\d+)/(?P<type>[a-zA-Z0-9]+)', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_confirm_attendance',
        'permission_callback' => '__return_true'
    ));
    
    // Endpoint para verificar si un evento de DEMO existe en BD (para sync con Google Calendar)
    register_rest_route('automatiza-tech/v1', '/leads/check-event', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_check_lead_event_exists',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para marcar WhatsApp enviado en leads (llamado por N8N)
    register_rest_route('automatiza-tech/v1', '/leads/(?P<lead_id>\d+)/mark-whatsapp-sent', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_mark_lead_whatsapp_sent',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Marcar WhatsApp enviado en tabla leads
 */
function automatiza_tech_mark_lead_whatsapp_sent($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $lead_id = intval($request['lead_id']);

    // Asegurar que exista la columna whatsapp_sent
    $col_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'whatsapp_sent'");
    if (!$col_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN whatsapp_sent tinyint(1) DEFAULT 0");
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN whatsapp_sent_at datetime DEFAULT NULL");
    }

    $result = $wpdb->update($table_name, [
        'whatsapp_sent' => 1,
        'whatsapp_sent_at' => current_time('mysql')
    ], ['id' => $lead_id]);

    if ($result !== false) {
        return new WP_REST_Response(['success' => true, 'message' => 'WhatsApp marcado como enviado'], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => 'Error al actualizar'], 500);
}

/**
 * Crear tabla de leads al activar el tema (o verificar existencia)
 */
function automatiza_tech_create_leads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $logs_table_name = $wpdb->prefix . 'automatiza_leads_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        invitees_emails text DEFAULT NULL,
        invitees_names text DEFAULT NULL,
        copied_emails text DEFAULT NULL,
        phone varchar(20) NOT NULL,
        session_id varchar(100) DEFAULT '' NOT NULL,
        scheduled_date date DEFAULT NULL,
        scheduled_time time DEFAULT NULL,
        confirmed_attendance tinyint(1) DEFAULT NULL,
        confirmed_attendance72h tinyint(1) DEFAULT 0,
        confirmed_attendance24h tinyint(1) DEFAULT 0,
        confirmed_attendance1h tinyint(1) DEFAULT 0,
        recordatorio72h tinyint(1) DEFAULT 0,
        recordatorio24h tinyint(1) DEFAULT 0,
        recordatorio1h tinyint(1) DEFAULT 0,
        token varchar(64) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_logs = "CREATE TABLE $logs_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        original_lead_id mediumint(9) NOT NULL,
        deleted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        reason tinytext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_logs);
}
// Ejecutar creación de tabla al cambiar al tema
add_action('after_switch_theme', 'automatiza_tech_create_leads_table');

// También intentamos crearla si no existe al iniciar (para desarrollo)
add_action('init', function() {
    // Forzamos actualización de tabla v6 - Campos de confirmación por recordatorio
    if (!get_option('automatiza_leads_table_created_v6')) {
        automatiza_tech_create_leads_table();
        
        // Agregar columnas de confirmación si no existen
        global $wpdb;
        $table_name = $wpdb->prefix . 'automatiza_leads';
        
        // Agregar confirmed_attendance72h si no existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_attendance72h'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_attendance72h tinyint(1) DEFAULT 0");
        }
        
        // Agregar confirmed_attendance24h si no existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_attendance24h'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_attendance24h tinyint(1) DEFAULT 0");
        }
        
        // Agregar confirmed_attendance1h si no existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_attendance1h'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_attendance1h tinyint(1) DEFAULT 0");
        }

        // Agregar campos para destinatarios en copia si no existen
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'invitees_emails'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitees_emails text DEFAULT NULL AFTER email");
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'invitees_names'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitees_names text DEFAULT NULL AFTER invitees_emails");
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'copied_emails'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN copied_emails text DEFAULT NULL AFTER invitees_names");
        }
        
        // Populate missing tokens for existing leads
        $leads_without_token = $wpdb->get_results("SELECT id FROM $table_name WHERE token = ''");
        
        if ($leads_without_token) {
            foreach ($leads_without_token as $lead) {
                $wpdb->update(
                    $table_name,
                    array('token' => bin2hex(random_bytes(16))),
                    array('id' => $lead->id)
                );
            }
        }
        
        update_option('automatiza_leads_table_created_v6', true);
    }

    // Migración v7: asegurar columnas de copias en tabla leads
    if (!get_option('automatiza_leads_table_created_v7_copy_fields')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'automatiza_leads';

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'invitees_emails'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitees_emails text DEFAULT NULL AFTER email");
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'invitees_names'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitees_names text DEFAULT NULL AFTER invitees_emails");
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'copied_emails'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN copied_emails text DEFAULT NULL AFTER invitees_names");
        }

        update_option('automatiza_leads_table_created_v7_copy_fields', true);
    }
});

/**
 * Callback para guardar lead
 */
function automatiza_tech_save_lead($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';

    // Obtener parámetros JSON
    $params = $request->get_json_params();

    // Validar datos básicos
    if (empty($params['name']) || empty($params['email'])) {
        return new WP_Error('missing_params', 'Faltan datos obligatorios (nombre, email)', array('status' => 400));
    }

    $name = sanitize_text_field($params['name']);
    $email = sanitize_email($params['email']);
    $phone_raw = isset($params['phone']) ? sanitize_text_field($params['phone']) : '';
    
    // Normalizar teléfono para que siempre tenga formato internacional +56
    $phone = automatiza_tech_normalize_phone($phone_raw);
    
    $session_id = isset($params['session_id']) ? sanitize_text_field($params['session_id']) : '';
    $source = isset($params['source']) ? sanitize_text_field($params['source']) : 'web';
    $invitees_input = isset($params['invitees_emails']) ? sanitize_text_field($params['invitees_emails']) : '';
    $invitees_parsed = automatiza_tech_parse_copy_input_with_names($invitees_input);
    $invitees_valid = $invitees_parsed['valid'];
    $invitees_names = $invitees_parsed['names'] ?? array();
    
    // Auto-detectar source como 'whatsapp' si:
    // 1. session_id contiene "whatsapp" (flujo antiguo)
    // 2. Tiene teléfono y no tiene session_id (indica que viene de N8N/WhatsApp)
    // 3. El source no fue especificado explícitamente como algo diferente a 'web'
    if ($source === 'web') {
        if (strpos(strtolower($session_id), 'whatsapp') !== false) {
            $source = 'whatsapp';
        } elseif (!empty($phone) && empty($session_id)) {
            // Si tiene teléfono pero no session_id, viene del flujo de WhatsApp/N8N
            $source = 'whatsapp';
        }
    }
    
    $scheduled_date = isset($params['scheduled_date']) ? sanitize_text_field($params['scheduled_date']) : null;
    $scheduled_time = isset($params['scheduled_time']) ? sanitize_text_field($params['scheduled_time']) : null;
    $confirmed_attendance = isset($params['confirmed_attendance']) ? (int)$params['confirmed_attendance'] : null;
    $meet_link = isset($params['meet_link']) ? esc_url_raw($params['meet_link']) : '';
    $google_event_id = isset($params['google_event_id']) ? sanitize_text_field($params['google_event_id']) : '';
    
    // Validación: Verificar si el email ya tiene 2 o más agendamientos ACTIVOS (futuros)
    $test_email = 'lmgm.uber@gmail.com';
    if (strtolower($email) !== strtolower($test_email)) {
        $current_datetime = current_time('mysql');
        
        // Contamos solo los agendamientos cuya fecha y hora sean mayores o iguales al momento actual
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE email = %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) >= %s",
            $email,
            $current_datetime
        ));
        
        if ($count >= 2) {
            return new WP_Error('email_limit_reached', 'Este correo ya tiene 2 agendamientos activos. Solo se permiten 2 reuniones pendientes simultáneas.', array('status' => 400));
        }
    }
    
    // Validación CRUZADA: Verificar disponibilidad del slot (DEMO y Seguimiento)
    if ($scheduled_date && $scheduled_time) {
        $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
        $time_normalized = substr($scheduled_time, 0, 5);
        
        // Verificar si existe conflicto con otra DEMO
        $demo_conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name 
             WHERE scheduled_date = %s 
             AND LEFT(scheduled_time, 5) = %s 
             AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
            $scheduled_date,
            $time_normalized
        ));
        
        if ($demo_conflict) {
            return new WP_Error('slot_taken', 'Este horario ya está ocupado por otra DEMO. Por favor selecciona otro horario.', array('status' => 400));
        }
        
        // Verificar si existe conflicto con reunión de Seguimiento
        $followup_conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $followup_table 
             WHERE meeting_date = %s 
             AND LEFT(meeting_time, 5) = %s 
             AND status NOT IN ('cancelled', 'completed')",
            $scheduled_date,
            $time_normalized
        ));
        
        if ($followup_conflict) {
            return new WP_Error('slot_taken', 'Este horario ya está ocupado por una reunión de seguimiento. Por favor selecciona otro horario.', array('status' => 400));
        }
    }
    
    // Generar token de seguridad
    $token = bin2hex(random_bytes(16));

    // Insertar en base de datos
    $data = array(
        'created_at' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'invitees_emails' => !empty($invitees_valid) ? implode(',', $invitees_valid) : null,
        'invitees_names' => !empty($invitees_names) ? implode(',', $invitees_names) : null,
        'copied_emails' => !empty($invitees_valid) ? implode(',', $invitees_valid) : null,
        'phone' => $phone,
        'session_id' => $session_id,
        'meet_link' => $meet_link,
        'google_event_id' => $google_event_id,
        'token' => $token,
        'source' => $source
    );

    if ($scheduled_date) $data['scheduled_date'] = $scheduled_date;
    if ($scheduled_time) $data['scheduled_time'] = $scheduled_time;
    if ($confirmed_attendance !== null) $data['confirmed_attendance'] = $confirmed_attendance;

    $result = $wpdb->insert($table_name, $data);

    if ($result === false) {
        return new WP_Error('db_error', 'Error al guardar en base de datos', array('status' => 500));
    }

    return array(
        'success' => true,
        'message' => 'Lead guardado correctamente',
        'lead_id' => $wpdb->insert_id
    );
}

/**
 * Callback para obtener el tipo de cambio
 */
function automatiza_tech_get_exchange_rate() {
    if (!function_exists('automatiza_tech_init_currency_updater')) {
        return new WP_Error('dependency_missing', 'Currency Updater function not found', array('status' => 500));
    }

    $updater = automatiza_tech_init_currency_updater();
    $rate = $updater->get_current_exchange_rate();

    if (!$rate) {
        // Intentar obtener el último guardado si falla la API en tiempo real
        $rate = get_option('automatiza_tech_last_exchange_rate', 0);
    }

    if (!$rate) {
        return new WP_Error('no_rate', 'Could not retrieve exchange rate', array('status' => 500));
    }

    return array(
        'currency_from' => 'USD',
        'currency_to' => 'CLP',
        'rate' => (float) $rate,
        'formatted_rate' => '$' . number_format($rate, 2, ',', '.'),
        'timestamp' => current_time('mysql'),
        'source' => 'Banco Central de Chile / Mindicador.cl'
    );
}

/**
 * Callback para verificar disponibilidad
 */
function automatiza_tech_check_availability($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // Obtener fecha de múltiples fuentes para mayor compatibilidad
    $params = $request->get_json_params();
    $date = isset($params['date']) ? sanitize_text_field($params['date']) : null;
    
    // Fallback a get_param si no viene en JSON
    if (!$date) {
        $date = $request->get_param('date');
    }

    if (!$date) {
        return new WP_Error('missing_date', 'Fecha requerida', array('status' => 400));
    }

    // 1. Check Admin Settings (Holidays & Schedule)
    $settings = get_option('automatiza_chat_schedule', array());

    // Apply defaults if settings are empty (same as in chat-widget.php)
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (!isset($settings[$day])) {
            $settings[$day] = array(
                'enabled' => true,
                'start' => ($day == 'saturday' || $day == 'sunday') ? '15:00' : '09:00',
                'end' => ($day == 'saturday' || $day == 'sunday') ? '17:00' : '21:00'
            );
        }
    }
    
    // Check Holidays
    $holidays = isset($settings['holidays']) ? explode("\n", $settings['holidays']) : [];
    $holidays = array_map('trim', $holidays);
    if (in_array($date, $holidays)) {
        return array('isFullDay' => true, 'reason' => 'Holiday');
    }

    // Check Day Schedule
    $timestamp = strtotime($date);
    $day_name = strtolower(date('l', $timestamp)); // monday, tuesday...
    
    if (!isset($settings[$day_name]) || empty($settings[$day_name]['enabled'])) {
         return array('isFullDay' => true, 'reason' => 'Day disabled');
    }

    $start_time = $settings[$day_name]['start'];
    $end_time = $settings[$day_name]['end'];

    // 2. Get Booked Slots from DB - DEMOS (excluyendo citas canceladas y no_show)
    $booked_results = $wpdb->get_results($wpdb->prepare(
        "SELECT scheduled_time FROM $table_name WHERE scheduled_date = %s AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
        $date
    ));

    $busy_slots = array();
    foreach ($booked_results as $row) {
        // Format to HH:mm
        $busy_slots[] = substr($row->scheduled_time, 0, 5);
    }
    
    // 2.1 Get Booked Slots from FOLLOWUP MEETINGS table (validación cruzada)
    $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
    $followup_results = $wpdb->get_results($wpdb->prepare(
        "SELECT meeting_time FROM $followup_table WHERE meeting_date = %s AND status NOT IN ('cancelled', 'completed')",
        $date
    ));
    
    foreach ($followup_results as $row) {
        $slot = substr($row->meeting_time, 0, 5);
        if (!in_array($slot, $busy_slots)) {
            $busy_slots[] = $slot;
        }
    }

    // 3. Calculate if Full Day
    // Generate all theoretical slots
    $start_hour = (int)explode(':', $start_time)[0];
    $end_hour = (int)explode(':', $end_time)[0];
    $total_slots = 0;
    $available_slots = 0;

    for ($h = $start_hour; $h < $end_hour; $h++) {
        $slot = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
        $total_slots++;
        if (!in_array($slot, $busy_slots)) {
            $available_slots++;
        }
    }

    return array(
        'isFullDay' => ($available_slots === 0),
        'busySlots' => $busy_slots,
        'availableSlotsCount' => $available_slots,
        'workingHours' => array('start' => $start_time, 'end' => $end_time)
    );
}

/**
 * Callback para verificar límite de agendamientos
 */
function automatiza_tech_check_booking_limit($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $params = $request->get_json_params();
    $email = isset($params['email']) ? sanitize_email($params['email']) : '';
    
    if (empty($email)) {
        return new WP_Error('missing_email', 'Email requerido', array('status' => 400));
    }

    $test_email = 'lmgm.uber@gmail.com';
    
    // Si es el email de prueba, siempre permitir
    if (strtolower($email) === strtolower($test_email)) {
        return array(
            'allowed' => true,
            'message' => 'Email de prueba permitido'
        );
    }

    $current_datetime = current_time('mysql');
    
    // Contamos solo los agendamientos cuya fecha y hora sean mayores o iguales al momento actual
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE email = %s 
         AND CONCAT(scheduled_date, ' ', scheduled_time) >= %s",
        $email,
        $current_datetime
    ));
    
    if ($count >= 2) {
        return array(
            'allowed' => false,
            'message' => 'Este correo ya tiene 2 agendamientos activos. Solo se permiten 2 reuniones pendientes simultáneas.'
        );
    }

    return array(
        'allowed' => true,
        'message' => 'Agendamiento permitido'
    );
}

/**
 * Callback para obtener leads para recordatorios
 */
function automatiza_tech_get_leads_for_reminders($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $type = $request['type']; // Prioridad: Parámetro de ruta
    
    if (empty($type)) {
        $type = $request->get_param('type'); // Fallback: Query param
    }
    
    // Fallback extremo: $_GET/$_REQUEST
    if (empty($type) && isset($_GET['type'])) $type = $_GET['type'];
    if (empty($type) && isset($_REQUEST['type'])) $type = $_REQUEST['type'];

    // Debug logging
    error_log("Reminder API called. Type resolved: " . print_r($type, true));
    
    if (!in_array($type, ['72h', '24h', '1h'])) {
        $debug_info = array(
            'received_params' => $request->get_params(),
            'GET_params' => $_GET,
            'REQUEST_params' => $_REQUEST
        );
        error_log("Invalid type. Debug info: " . print_r($debug_info, true));
        return new WP_Error('invalid_type', 'Tipo de recordatorio inválido. Debug: ' . json_encode($debug_info), array('status' => 400));
    }

    $now = current_time('mysql');
    $leads = [];

    if ($type === '72h') {
        // Citas entre 26 y 96 horas antes (de 1 a 4 días, NO dice "mañana")
        // EMAIL: Solo verificar que no se haya enviado por EMAIL (se envía 1 vez por canal)
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 26 hours'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 96 hours'));
        
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
             AND (recordatorio72h IS NULL OR recordatorio72h = 0)
             AND (status IS NULL OR status != 'cancelled')",
            $start_range, $end_range, $now
        ));
    } elseif ($type === '24h') {
        // Citas entre 2 y 26 horas antes (realmente "mañana", no 2 días)
        // EMAIL: Solo verificar que no se haya enviado por EMAIL (se envía 1 vez por canal)
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 2 hours'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 26 hours'));

        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
             AND (recordatorio24h IS NULL OR recordatorio24h = 0)
             AND (status IS NULL OR status != 'cancelled')",
            $start_range, $end_range, $now
        ));
    } elseif ($type === '1h') {
        // Citas entre 30 minutos y 1 hora 15 minutos antes
        // EMAIL: Solo verificar que no se haya enviado por EMAIL (se envía 1 vez por canal)
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 30 minutes'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour 15 minutes'));

        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
             AND (recordatorio1h IS NULL OR recordatorio1h = 0)
             AND (status IS NULL OR status != 'cancelled')",
            $start_range, $end_range, $now
        ));
    }

    // Formatear fechas para visualización (DD-MM-YYYY)
    if (!empty($leads)) {
        foreach ($leads as $lead) {
            $lead->scheduled_date = date('d-m-Y', strtotime($lead->scheduled_date));
            $lead->scheduled_time = substr($lead->scheduled_time, 0, 5);
            // Asegurar que meet_link esté presente (aunque sea vacío)
            if (!isset($lead->meet_link)) {
                $lead->meet_link = '';
            }

            automatiza_tech_attach_recipient_metadata($lead, 'lead');
        }
    }

    return $leads;
}

/**
 * Callback para marcar recordatorio como enviado
 */
function automatiza_tech_mark_reminder_sent($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // 1. Intentar obtener de la ruta (URL Path)
    $lead_id = isset($request['lead_id']) ? $request['lead_id'] : null;
    $type = isset($request['type']) ? $request['type'] : null;

    // 2. Si no están en la ruta, buscar en Body/Query (Fallback)
    if (empty($lead_id)) {
        $lead_id = $request->get_param('lead_id');
    }
    if (empty($type)) {
        $type = $request->get_param('type');
    }
    
    // 3. Fallback final para JSON Body crudo (si n8n envía JSON pero WP no lo parsea)
    if (empty($lead_id) || empty($type)) {
        $json_params = $request->get_json_params();
        if (!empty($json_params)) {
            if (empty($lead_id) && isset($json_params['lead_id'])) $lead_id = $json_params['lead_id'];
            if (empty($type) && isset($json_params['type'])) $type = $json_params['type'];
        }
    }
    
    $lead_id = (int)$lead_id;

    if (!$lead_id || !in_array($type, ['72h', '24h', '1h'])) {
        // Log para depuración
        error_log("Update Reminder Failed. ID: $lead_id, Type: $type. Request Params: " . print_r($request->get_params(), true));
        return new WP_Error('invalid_params', 'Parámetros inválidos. Recibido ID: ' . $lead_id . ' Type: ' . $type, array('status' => 400));
    }

    $column = 'recordatorio' . $type;
    
    $result = $wpdb->update(
        $table_name,
        array($column => 1),
        array('id' => $lead_id),
        array('%d'),
        array('%d')
    );

    return array('success' => true, 'updated' => $result, 'channel' => 'email');
}

/**
 * Callback para obtener leads para recordatorios por WHATSAPP
 * Usa campos separados: recordatorio72h_wa, recordatorio24h_wa, recordatorio1h_wa
 */
function automatiza_tech_get_leads_for_reminders_wa($request) {
    // Evitar caché - Headers agresivos para LiteSpeed
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('X-Accel-Expires: 0');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $type = $request['type'];
    
    if (empty($type)) {
        $type = $request->get_param('type');
    }
    
    if (empty($type) && isset($_GET['type'])) $type = $_GET['type'];
    if (empty($type) && isset($_REQUEST['type'])) $type = $_REQUEST['type'];

    error_log("Reminder WA API called. Type resolved: " . print_r($type, true));
    
    if (!in_array($type, ['72h', '24h', '1h'])) {
        return new WP_Error('invalid_type', 'Tipo de recordatorio inválido', array('status' => 400));
    }

    $now = current_time('mysql');
    $leads = [];

    if ($type === '72h') {
        // WHATSAPP 72h: Citas entre 26 y 96 horas (NO dice "mañana")
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 26 hours'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 96 hours'));
        
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
             AND (recordatorio72h_wa IS NULL OR recordatorio72h_wa = 0)
             AND (status IS NULL OR status != 'cancelled')
             AND phone IS NOT NULL AND phone != ''",
            $start_range, $end_range, $now
        ));
    } elseif ($type === '24h') {
        // WHATSAPP 24h: Citas entre 2 y 26 horas (realmente "mañana")
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 2 hours'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 26 hours'));

        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
             AND (recordatorio24h_wa IS NULL OR recordatorio24h_wa = 0)
             AND (status IS NULL OR status != 'cancelled')
             AND phone IS NOT NULL AND phone != ''",
            $start_range, $end_range, $now
        ));
    } elseif ($type === '1h') {
        // WHATSAPP 1h: Se envía UNA VEZ cuando la cita está entre 30min y 1h 15min
        // Si el usuario no confirma, puede recibir otro recordatorio en el próximo ciclo
        // PERO solo si han pasado al menos 30 minutos desde el último envío
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 30 minutes'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour 15 minutes'));
        
        // Debug log
        error_log("WA 1h Reminder - Now: $now, Start: $start_range, End: $end_range");

        // Solo enviar si NO se ha enviado aún (recordatorio1h_wa = 0)
        // O si el usuario no ha confirmado aún
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
             AND (recordatorio1h_wa IS NULL OR recordatorio1h_wa = 0)
             AND (confirmed_attendance IS NULL OR confirmed_attendance = 0)
             AND (confirmed_attendance1h IS NULL OR confirmed_attendance1h = 0)
             AND (confirmed_attendance1h_wa IS NULL OR confirmed_attendance1h_wa = 0)
             AND (status IS NULL OR status != 'cancelled')
             AND phone IS NOT NULL AND phone != ''",
            $start_range, $end_range, $now
        ));
        
        error_log("WA 1h Reminder - Found " . count($leads) . " leads");
    }

    // Formatear fechas para visualización
    if (!empty($leads)) {
        foreach ($leads as $lead) {
            $lead->scheduled_date = date('d-m-Y', strtotime($lead->scheduled_date));
            $lead->scheduled_time = substr($lead->scheduled_time, 0, 5);
            if (!isset($lead->meet_link)) {
                $lead->meet_link = '';
            }
        }
    }

    return $leads;
}

/**
 * Callback para marcar recordatorio por WHATSAPP como enviado
 * Usa campos: recordatorio72h_wa, recordatorio24h_wa, recordatorio1h_wa
 */
function automatiza_tech_mark_reminder_sent_wa($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $lead_id = isset($request['lead_id']) ? $request['lead_id'] : null;
    $type = isset($request['type']) ? $request['type'] : null;

    if (empty($lead_id)) {
        $lead_id = $request->get_param('lead_id');
    }
    if (empty($type)) {
        $type = $request->get_param('type');
    }
    
    if (empty($lead_id) || empty($type)) {
        $json_params = $request->get_json_params();
        if (!empty($json_params)) {
            if (empty($lead_id) && isset($json_params['lead_id'])) $lead_id = $json_params['lead_id'];
            if (empty($type) && isset($json_params['type'])) $type = $json_params['type'];
        }
    }
    
    $lead_id = (int)$lead_id;

    if (!$lead_id || !in_array($type, ['72h', '24h', '1h'])) {
        error_log("Update Reminder WA Failed. ID: $lead_id, Type: $type");
        return new WP_Error('invalid_params', 'Parámetros inválidos. ID: ' . $lead_id . ' Type: ' . $type, array('status' => 400));
    }

    // Usar campo _wa para WhatsApp
    $column = 'recordatorio' . $type . '_wa';
    
    $result = $wpdb->update(
        $table_name,
        array($column => 1),
        array('id' => $lead_id),
        array('%d'),
        array('%d')
    );

    return array('success' => true, 'updated' => $result, 'channel' => 'whatsapp', 'column' => $column);
}

/**
 * Callback para confirmar asistencia desde WhatsApp (recordatorios con botones)
 * Lógica igual que correos: marca confirmed_attendance{tipo} = 1
 * Si type='auto', detecta automáticamente cuál recordatorio fue el último enviado
 */
function automatiza_tech_confirm_attendance($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // Obtener parámetros de la URL
    $lead_id = isset($request['lead_id']) ? (int)$request['lead_id'] : 0;
    $type = isset($request['type']) ? $request['type'] : null;

    if (!$lead_id) {
        error_log("Confirm Attendance Failed. ID: $lead_id");
        return new WP_Error('invalid_params', 'ID de lead inválido', array('status' => 400));
    }

    // Si el tipo es 'auto', detectar cuál recordatorio fue el último enviado
    if ($type === 'auto') {
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT recordatorio1h, recordatorio24h, recordatorio72h, 
                    confirmed_attendance1h, confirmed_attendance24h, confirmed_attendance72h 
             FROM $table_name WHERE id = %d", 
            $lead_id
        ));
        
        if (!$lead) {
            return new WP_Error('not_found', 'Lead no encontrado', array('status' => 404));
        }
        
        // Prioridad: 1h > 24h > 72h (el más reciente primero)
        // Solo confirmar si el recordatorio fue enviado Y aún no está confirmado
        if ($lead->recordatorio1h == 1 && $lead->confirmed_attendance1h == 0) {
            $type = '1h';
        } elseif ($lead->recordatorio24h == 1 && $lead->confirmed_attendance24h == 0) {
            $type = '24h';
        } elseif ($lead->recordatorio72h == 1 && $lead->confirmed_attendance72h == 0) {
            $type = '72h';
        } else {
            // Si todos ya están confirmados o ninguno enviado, confirmar el más reciente enviado
            if ($lead->recordatorio1h == 1) {
                $type = '1h';
            } elseif ($lead->recordatorio24h == 1) {
                $type = '24h';
            } elseif ($lead->recordatorio72h == 1) {
                $type = '72h';
            } else {
                // Ningún recordatorio enviado, usar 72h por defecto
                $type = '72h';
            }
        }
    }

    if (!in_array($type, ['72h', '24h', '1h'])) {
        error_log("Confirm Attendance Failed. Invalid Type: $type");
        return new WP_Error('invalid_type', 'Tipo de recordatorio inválido', array('status' => 400));
    }

    // Columna de confirmación según el tipo de recordatorio
    $confirm_column = 'confirmed_attendance' . $type;
    
    // Preparar datos para actualizar
    $update_data = array($confirm_column => 1);
    $update_format = array('%d');
    
    // Solo para recordatorio de 1h: también actualizar confirmed_attendance general
    if ($type === '1h') {
        $update_data['confirmed_attendance'] = 1;
        $update_format[] = '%d';
    }
    
    // Actualizar la confirmación de asistencia
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $lead_id),
        $update_format,
        array('%d')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Error al actualizar la confirmación', array('status' => 500));
    }

    // Obtener datos del lead para la respuesta
    $lead = $wpdb->get_row($wpdb->prepare("SELECT name, email, scheduled_date, scheduled_time FROM $table_name WHERE id = %d", $lead_id));

    return array(
        'success' => true, 
        'updated' => $result,
        'lead_id' => $lead_id,
        'type' => $type,
        'confirmed_column' => $confirm_column,
        'confirmed_attendance' => ($type === '1h') ? 1 : null,
        'lead_name' => $lead ? $lead->name : null,
        'scheduled_date' => $lead ? $lead->scheduled_date : null,
        'scheduled_time' => $lead ? $lead->scheduled_time : null
    );
}

/**
 * Callback para manejar acciones de usuario (Confirmar/Rechazar/Eliminar)
 */
function automatiza_tech_handle_lead_action($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $logs_table_name = $wpdb->prefix . 'automatiza_leads_logs';
    
    $lead_id = $request->get_param('id');
    $token = $request->get_param('token');
    $action = $request->get_param('action'); // confirm, reject, delete

    if (!$lead_id || !in_array($action, ['confirm', 'reject', 'delete'])) {
        wp_die('Enlace inválido o expirado.', 'Error', array('response' => 400));
    }

    // Verificar Token de Seguridad
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    
    if (!$lead || !$token || !hash_equals($lead->token, $token)) {
         wp_die('Enlace no autorizado o token inválido.', 'Acceso Denegado', array('response' => 403));
    }

    // Configuración visual común
    $site_title = get_bloginfo('name');
    $home_url = home_url();
    $logo_src = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    
    // Forzar cabecera HTML
    header('Content-Type: text/html; charset=UTF-8');

    // --- LÓGICA PARA REAGENDAR (REJECT) ---
    if ($action === 'reject') {
        // No actualizamos nada aún, mostramos formulario de reagendamiento
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo get_bloginfo('language'); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reagendar Cita - <?php echo esc_html($site_title); ?></title>
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
                #message-box { margin-top: 15px; font-size: 13px; display: none; }
                .success { color: #06d6a0; }
                .error { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="header">
                    <img src="<?php echo esc_url($logo_src); ?>" alt="<?php echo esc_attr($site_title); ?>" class="logo">
                </div>
                <div class="content" id="reschedule-container">
                    <h2>Reagendar Cita</h2>
                    <p>Lamentamos que no puedas asistir. Por favor selecciona una nueva fecha y hora para tu reunión.</p>
                    
                    <form id="reschedule-form">
                        <input type="hidden" id="lead_id" value="<?php echo esc_attr($lead_id); ?>">
                        <input type="hidden" id="token" value="<?php echo esc_attr($token); ?>">
                        
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

                        <button type="submit" class="btn btn-primary" id="submit-btn">Confirmar Nuevo Horario</button>
                    </form>

                    <div id="message-box"></div>

                    <a href="<?php echo esc_url(home_url('/wp-json/automatiza-tech/v1/leads/action?id=' . $lead_id . '&token=' . $token . '&action=delete')); ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas cancelar definitivamente la cita?');">Cancelar Cita Definitivamente</a>
                </div>
            </div>

            <script>
                const dateInput = document.getElementById('date');
                const timeSelect = document.getElementById('time');
                const form = document.getElementById('reschedule-form');
                const submitBtn = document.getElementById('submit-btn');
                const msgBox = document.getElementById('message-box');
                const leadId = document.getElementById('lead_id').value;
                const token = document.getElementById('token').value;

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
                            timeSelect.innerHTML = '<option value="">Día completo</option>';
                        } else {
                            // Usar horarios devueltos por el backend (respetando configuración del panel)
                            const startHour = parseInt(data.workingHours.start.split(':')[0]); 
                            const endHour = parseInt(data.workingHours.end.split(':')[0]);
                            let hasSlots = false;
                            
                            timeSelect.innerHTML = '<option value="">Selecciona una hora</option>';

                            for (let h = startHour; h < endHour; h++) {
                                const timeStr = h.toString().padStart(2, '0') + ':00';
                                if (!data.busySlots.includes(timeStr)) {
                                    const option = document.createElement('option');
                                    option.value = timeStr;
                                    option.textContent = timeStr;
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

                    fetch('/wp-json/automatiza-tech/v1/leads/reschedule', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ lead_id: leadId, token: token, date: date, time: time })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Formatear fecha para mostrar
                            const [year, month, day] = date.split('-');
                            const formattedDate = `${day}-${month}-${year}`;

                            document.getElementById('reschedule-container').innerHTML = `
                                <h2>¡Cita Reagendada!</h2>
                                <p>Tu nueva cita ha sido confirmada para el <strong>${formattedDate}</strong> a las <strong>${time}</strong>.</p>
                                <a href="<?php echo esc_url($home_url); ?>" class="btn btn-primary">Volver al Inicio</a>
                            `;
                        } else {
                            throw new Error(data.message || 'Error desconocido');
                        }
                    })
                    .catch(err => {
                        msgBox.style.display = 'block';
                        msgBox.className = 'error';
                        msgBox.textContent = err.message;
                        submitBtn.textContent = 'Confirmar Nuevo Horario';
                        submitBtn.classList.remove('loading');
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    // --- LÓGICA PARA CONFIRMAR O ELIMINAR ---
    if ($action === 'confirm') {
        // Obtener tipo de recordatorio desde el parámetro (72h, 24h, 1h)
        $reminder_type = $request->get_param('reminder_type');
        
        // Determinar qué campo actualizar según el tipo de recordatorio
        $update_data = array('confirmed_attendance' => 1); // Campo general siempre se actualiza
        
        if ($reminder_type === '72h') {
            $update_data['confirmed_attendance72h'] = 1;
        } elseif ($reminder_type === '24h') {
            $update_data['confirmed_attendance24h'] = 1;
        } elseif ($reminder_type === '1h') {
            $update_data['confirmed_attendance1h'] = 1;
        } else {
            // Si no se especifica tipo, actualizar todos (compatibilidad hacia atrás)
            $update_data['confirmed_attendance72h'] = 1;
            $update_data['confirmed_attendance24h'] = 1;
            $update_data['confirmed_attendance1h'] = 1;
        }
        
        $wpdb->update($table_name, $update_data, array('id' => $lead_id));
        $message = '¡Gracias! Tu asistencia ha sido confirmada.';
    } elseif ($action === 'delete') {
        // Obtener datos antes de borrar
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
        
        if ($lead) {
            // Eliminar de Google Calendar si existe el event_id
            if (!empty($lead->google_event_id)) {
                automatiza_tech_delete_google_calendar_event($lead->google_event_id);
            }
            
            // Mover a logs
            $wpdb->insert($logs_table_name, array(
                'original_lead_id' => $lead->id,
                'deleted_at' => current_time('mysql'),
                'name' => $lead->name,
                'email' => $lead->email,
                'reason' => 'Usuario eliminó agendamiento desde correo'
            ));
            
            // Borrar de leads
            $wpdb->delete($table_name, array('id' => $lead_id));
            $message = 'Lamentamos que no puedas asistir, pero entendemos que surgen imprevistos.<br><br>Te invitamos a seguir visitando nuestro sitio web y, cuando estés listo, volver a coordinar una llamada para descubrir cómo nuestras automatizaciones pueden potenciar tu negocio.<br><br>¡Esperamos verte pronto!';
        } else {
            $message = 'El agendamiento ya no existe.';
        }
    }

    echo '<!DOCTYPE html>
    <html lang="' . get_bloginfo('language') . '">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . esc_html($site_title) . ' - Respuesta</title>
        <style>
            body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; overflow: hidden; }
            .header { background-color: #1e40af; padding: 30px 20px; }
            .logo { max-height: 60px; width: auto; display: block; margin: 0 auto; }
            .content { padding: 40px 30px; }
            p { color: #555; font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem; }
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
                <p>' . $message . '</p>
                <a href="' . esc_url($home_url) . '" class="btn">Volver al Inicio</a>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

/**
 * Callback para reagendar cita
 */
function automatiza_tech_reschedule_lead($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $params = $request->get_json_params();
    $lead_id = isset($params['lead_id']) ? (int)$params['lead_id'] : 0;
    $token = isset($params['token']) ? sanitize_text_field($params['token']) : '';
    $new_date = isset($params['date']) ? sanitize_text_field($params['date']) : '';
    $new_time = isset($params['time']) ? sanitize_text_field($params['time']) : '';
    $meet_link = isset($params['meet_link']) ? esc_url_raw($params['meet_link']) : '';

    if (!$lead_id || !$new_date || !$new_time) {
        return new WP_Error('missing_params', 'Faltan datos para reagendar', array('status' => 400));
    }

    // Verificar Token
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    if (!$lead || !hash_equals($lead->token, $token)) {
        return new WP_Error('invalid_token', 'Token inválido', array('status' => 403));
    }

    // Verificar disponibilidad nuevamente (doble check)
    $availability_req = new WP_REST_Request('POST', '/automatiza-tech/v1/check-availability');
    $availability_req->set_param('date', $new_date);
    $availability = automatiza_tech_check_availability($availability_req);

    if (is_wp_error($availability) || (isset($availability['isFullDay']) && $availability['isFullDay'])) {
         return new WP_Error('unavailable', 'El día seleccionado ya no está disponible', array('status' => 400));
    }
    
    if (in_array(substr($new_time, 0, 5), $availability['busySlots'])) {
        return new WP_Error('unavailable_slot', 'El horario seleccionado ya no está disponible', array('status' => 400));
    }

    // Actualizar cita
    $update_data = array(
        'scheduled_date' => $new_date,
        'scheduled_time' => $new_time,
        'confirmed_attendance' => 1, // Se asume confirmado al reagendar
        'recordatorio72h' => 0, // Resetear recordatorios
        'recordatorio24h' => 0,
        'recordatorio1h' => 0
    );
    
    $update_format = array('%s', '%s', '%d', '%d', '%d', '%d');

    if (!empty($meet_link)) {
        $update_data['meet_link'] = $meet_link;
        $update_format[] = '%s';
    }

    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $lead_id),
        $update_format,
        array('%d')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Error al actualizar la cita', array('status' => 500));
    }

    return array(
        'success' => true, 
        'message' => 'Cita reagendada correctamente'
    );
}

/**
 * Verificar si un evento de DEMO existe en la base de datos (para sync con Google Calendar)
 */
function automatiza_tech_check_lead_event_exists($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $event_id = $request->get_param('event_id');
    
    if (empty($event_id)) {
        return array(
            'exists' => false,
            'event_id' => null,
            'error' => 'event_id requerido'
        );
    }
    
    // Buscar evento en la tabla de leads (demos)
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, email, scheduled_date, scheduled_time, google_event_id 
         FROM $table_name 
         WHERE google_event_id = %s 
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
        $event_id
    ));
    
    if ($lead) {
        return array(
            'exists' => true,
            'event_id' => $event_id,
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'scheduled_date' => $lead->scheduled_date,
            'source' => 'leads'
        );
    }
    
    // También buscar en followup_meetings (por si el título contiene "Demo" pero es un seguimiento)
    $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
    if ($wpdb->get_var("SHOW TABLES LIKE '$followup_table'") === $followup_table) {
        $followup = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followup_table WHERE google_event_id = %s AND status != 'cancelled'",
            $event_id
        ));
        if (intval($followup) > 0) {
            return array(
                'exists' => true,
                'event_id' => $event_id,
                'source' => 'followup_meetings'
            );
        }
    }
    
    return array(
        'exists' => false,
        'event_id' => $event_id
    );
}

/**
 * Recordatorio 8PM - Seguimientos del día siguiente (para EMAIL)
 * Se ejecuta a las 8PM Chile y envía recordatorios para citas de seguimiento del día siguiente
 */
function automatiza_tech_get_leads_reminder_8pm($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    // Forzar zona horaria de Chile
    $chile_tz = new DateTimeZone('America/Santiago');
    $now_chile = new DateTime('now', $chile_tz);
    $tomorrow_chile = clone $now_chile;
    $tomorrow_chile->modify('+1 day');
    $tomorrow = $tomorrow_chile->format('Y-m-d');
    
    error_log("Reminder 8PM EMAIL Followup - Chile now: " . $now_chile->format('Y-m-d H:i:s') . " - Tomorrow: $tomorrow");
    
    // Buscar seguimientos con citas mañana que no hayan recibido el recordatorio 8PM por email
    $meetings = $wpdb->get_results($wpdb->prepare(
        "SELECT id, client_name as name, client_email as email, company_name, phone, 
            meeting_date, meeting_time, meet_link, meeting_subject, status, invitees_emails, invitees_names,
                recordatorio_8pm, recordatorio_8am, recordatorio_8pm_wa, recordatorio_8am_wa
         FROM $table_name 
         WHERE meeting_date = %s 
         AND (recordatorio_8pm IS NULL OR recordatorio_8pm = 0)
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))
         AND client_email IS NOT NULL AND client_email != ''",
        $tomorrow
    ));

    // Formatear fechas para visualización
    if (!empty($meetings)) {
        foreach ($meetings as $meeting) {
            $meeting->scheduled_date = date('d-m-Y', strtotime($meeting->meeting_date));
            $meeting->scheduled_time = substr($meeting->meeting_time, 0, 5);
            if (!isset($meeting->meet_link) || empty($meeting->meet_link)) {
                $meeting->meet_link = '';
            }
            automatiza_tech_attach_recipient_metadata($meeting, 'followup');
        }
    }

    error_log("Reminder 8PM EMAIL Followup - Found " . count($meetings) . " meetings");
    return $meetings;
}

/**
 * Recordatorio 8AM - Seguimientos del mismo día (para EMAIL)
 * Se ejecuta a las 8AM Chile y envía recordatorios para citas de seguimiento de ese mismo día
 */
function automatiza_tech_get_leads_reminder_8am($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    // Forzar zona horaria de Chile
    $chile_tz = new DateTimeZone('America/Santiago');
    $now_chile = new DateTime('now', $chile_tz);
    $today = $now_chile->format('Y-m-d');
    
    error_log("Reminder 8AM EMAIL Followup - Chile now: " . $now_chile->format('Y-m-d H:i:s') . " - Today: $today");
    
    // Buscar seguimientos con citas hoy que no hayan recibido el recordatorio 8AM por email
    // Solo citas a partir de las 09:00 para dar tiempo
    $meetings = $wpdb->get_results($wpdb->prepare(
        "SELECT id, client_name as name, client_email as email, company_name, phone, 
            meeting_date, meeting_time, meet_link, meeting_subject, status, invitees_emails, invitees_names,
                recordatorio_8pm, recordatorio_8am, recordatorio_8pm_wa, recordatorio_8am_wa
         FROM $table_name 
         WHERE meeting_date = %s 
         AND meeting_time >= '09:00:00'
         AND (recordatorio_8am IS NULL OR recordatorio_8am = 0)
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))
         AND client_email IS NOT NULL AND client_email != ''",
        $today
    ));

    // Formatear fechas para visualización
    if (!empty($meetings)) {
        foreach ($meetings as $meeting) {
            $meeting->scheduled_date = date('d-m-Y', strtotime($meeting->meeting_date));
            $meeting->scheduled_time = substr($meeting->meeting_time, 0, 5);
            if (!isset($meeting->meet_link) || empty($meeting->meet_link)) {
                $meeting->meet_link = '';
            }
            automatiza_tech_attach_recipient_metadata($meeting, 'followup');
        }
    }

    error_log("Reminder 8AM EMAIL Followup - Found " . count($meetings) . " meetings");
    return $meetings;
}

/**
 * Recordatorio 8PM - Seguimientos del día siguiente (para WHATSAPP)
 * Se ejecuta a las 8PM Chile y envía recordatorios para citas de seguimiento del día siguiente
 */
function automatiza_tech_get_leads_reminder_8pm_wa($request) {
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    // Forzar zona horaria de Chile
    $chile_tz = new DateTimeZone('America/Santiago');
    $now_chile = new DateTime('now', $chile_tz);
    $tomorrow_chile = clone $now_chile;
    $tomorrow_chile->modify('+1 day');
    $tomorrow = $tomorrow_chile->format('Y-m-d');
    
    error_log("Reminder 8PM WA Followup - Chile now: " . $now_chile->format('Y-m-d H:i:s') . " - Tomorrow: $tomorrow");
    
    // Buscar seguimientos con citas mañana que no hayan recibido el recordatorio 8PM por WhatsApp
    $meetings = $wpdb->get_results($wpdb->prepare(
        "SELECT id, client_name as name, client_email as email, company_name, phone, 
            meeting_date, meeting_time, meet_link, meeting_subject, status, invitees_emails, invitees_names,
                recordatorio_8pm, recordatorio_8am, recordatorio_8pm_wa, recordatorio_8am_wa
         FROM $table_name 
         WHERE meeting_date = %s 
         AND (recordatorio_8pm_wa IS NULL OR recordatorio_8pm_wa = 0)
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))
         AND phone IS NOT NULL AND phone != ''",
        $tomorrow
    ));

    // Formatear fechas para visualización
    if (!empty($meetings)) {
        foreach ($meetings as $meeting) {
            $meeting->scheduled_date = date('d-m-Y', strtotime($meeting->meeting_date));
            $meeting->scheduled_time = substr($meeting->meeting_time, 0, 5);
            if (!isset($meeting->meet_link) || empty($meeting->meet_link)) {
                $meeting->meet_link = '';
            }
            // WhatsApp solo al titular (no incluir copias).
            automatiza_tech_attach_primary_recipient_metadata($meeting);
        }
    }

    error_log("Reminder 8PM WA Followup - Found " . count($meetings) . " meetings");
    return $meetings;
}

/**
 * Recordatorio 8AM - Seguimientos del mismo día (para WHATSAPP)
 * Se ejecuta a las 8AM Chile y envía recordatorios para citas de seguimiento de ese mismo día
 * También pregunta confirmación de asistencia
 */
function automatiza_tech_get_leads_reminder_8am_wa($request) {
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    // Forzar zona horaria de Chile
    $chile_tz = new DateTimeZone('America/Santiago');
    $now_chile = new DateTime('now', $chile_tz);
    $today = $now_chile->format('Y-m-d');
    
    error_log("Reminder 8AM WA Followup - Chile now: " . $now_chile->format('Y-m-d H:i:s') . " - Today: $today");
    
    // Buscar seguimientos con citas hoy que no hayan recibido el recordatorio 8AM por WhatsApp
    // Solo citas a partir de las 09:00 para dar tiempo
    $meetings = $wpdb->get_results($wpdb->prepare(
        "SELECT id, client_name as name, client_email as email, company_name, phone, 
            meeting_date, meeting_time, meet_link, meeting_subject, status, invitees_emails, invitees_names,
                recordatorio_8pm, recordatorio_8am, recordatorio_8pm_wa, recordatorio_8am_wa
         FROM $table_name 
         WHERE meeting_date = %s 
         AND meeting_time >= '09:00:00'
         AND (recordatorio_8am_wa IS NULL OR recordatorio_8am_wa = 0)
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))
         AND phone IS NOT NULL AND phone != ''",
        $today
    ));

    // Formatear fechas para visualización
    if (!empty($meetings)) {
        foreach ($meetings as $meeting) {
            $meeting->scheduled_date = date('d-m-Y', strtotime($meeting->meeting_date));
            $meeting->scheduled_time = substr($meeting->meeting_time, 0, 5);
            if (!isset($meeting->meet_link) || empty($meeting->meet_link)) {
                $meeting->meet_link = '';
            }
            // WhatsApp solo al titular (no incluir copias).
            automatiza_tech_attach_primary_recipient_metadata($meeting);
        }
    }

    error_log("Reminder 8AM WA Followup - Found " . count($meetings) . " meetings");
    return $meetings;
}

/**
 * Marcar recordatorio 8PM/8AM como enviado (EMAIL) - Para Seguimientos
 */
function automatiza_tech_mark_reminder_daily_sent($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = isset($request['lead_id']) ? (int)$request['lead_id'] : 0;
    $type = isset($request['type']) ? $request['type'] : null;

    if (!$meeting_id || !in_array($type, ['8pm', '8am'])) {
        error_log("Update Reminder Daily EMAIL Followup Failed. ID: $meeting_id, Type: $type");
        return new WP_Error('invalid_params', 'Parámetros inválidos', array('status' => 400));
    }

    $column = 'recordatorio_' . $type;
    
    $result = $wpdb->update(
        $table_name,
        array($column => 1),
        array('id' => $meeting_id),
        array('%d'),
        array('%d')
    );

    return array('success' => true, 'updated' => $result, 'channel' => 'email', 'type' => $type);
}

/**
 * Marcar recordatorio 8PM/8AM como enviado (WHATSAPP) - Para Seguimientos
 */
function automatiza_tech_mark_reminder_daily_sent_wa($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
    
    $meeting_id = isset($request['lead_id']) ? (int)$request['lead_id'] : 0;
    $type = isset($request['type']) ? $request['type'] : null;

    if (!$meeting_id || !in_array($type, ['8pm', '8am'])) {
        error_log("Update Reminder Daily WA Followup Failed. ID: $meeting_id, Type: $type");
        return new WP_Error('invalid_params', 'Parámetros inválidos', array('status' => 400));
    }

    $column = 'recordatorio_' . $type . '_wa';
    
    $result = $wpdb->update(
        $table_name,
        array($column => 1),
        array('id' => $meeting_id),
        array('%d'),
        array('%d')
    );

    return array('success' => true, 'updated' => $result, 'channel' => 'whatsapp', 'type' => $type);
}
