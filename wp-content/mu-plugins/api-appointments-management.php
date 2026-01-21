<?php
/**
 * Plugin Name: Automatiza Tech - Appointments Management API
 * Description: REST API para gestionar citas/reuniones (CRUD completo)
 * Version: 1.0.0
 * Author: Automatiza Tech
 */

if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTech_Appointments_API {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_leads';
        
        add_action('rest_api_init', array($this, 'register_routes'));
        // NOTA: Menú de admin eliminado - se usa admin-reminders.php para unificar pantallas
    }
    
    /**
     * Registrar rutas REST API
     */
    public function register_routes() {
        // GET /appointments - Listar todas las citas
        register_rest_route('automatiza-tech/v1', '/appointments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_appointments'),
            'permission_callback' => '__return_true'
        ));
        
        // GET /appointments/{id} - Obtener una cita específica
        register_rest_route('automatiza-tech/v1', '/appointments/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_appointment'),
            'permission_callback' => '__return_true'
        ));
        
        // GET /appointments/search - Buscar citas por email/phone
        register_rest_route('automatiza-tech/v1', '/appointments/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_appointments'),
            'permission_callback' => '__return_true'
        ));
        
        // POST /appointments - Crear nueva cita (ya existe como /leads pero lo duplicamos)
        register_rest_route('automatiza-tech/v1', '/appointments', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_appointment'),
            'permission_callback' => '__return_true'
        ));
        
        // PUT /appointments/{id} - Actualizar cita (reprogramar)
        register_rest_route('automatiza-tech/v1', '/appointments/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_appointment'),
            'permission_callback' => '__return_true'
        ));
        
        // PATCH /appointments/{id} - Actualizar parcialmente
        register_rest_route('automatiza-tech/v1', '/appointments/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array($this, 'patch_appointment'),
            'permission_callback' => '__return_true'
        ));
        
        // DELETE /appointments/{id} - Eliminar/cancelar cita
        register_rest_route('automatiza-tech/v1', '/appointments/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_appointment'),
            'permission_callback' => '__return_true'
        ));
        
        // POST /appointments/{id}/cancel - Cancelar cita (alternativa a DELETE)
        register_rest_route('automatiza-tech/v1', '/appointments/(?P<id>\d+)/cancel', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_appointment'),
            'permission_callback' => '__return_true'
        ));
        
        // POST /send-email - Enviar correo corporativo
        register_rest_route('automatiza-tech/v1', '/send-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_email'),
            'permission_callback' => '__return_true'
        ));
        
        // GET /appointments/debug - Debug de búsqueda
        register_rest_route('automatiza-tech/v1', '/appointments/debug', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_search'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * GET /appointments - Listar todas las citas
     */
    public function get_appointments($request) {
        global $wpdb;
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 50;
        $offset = ($page - 1) * $per_page;
        $status = $request->get_param('status'); // 'active', 'cancelled'
        
        $where = "WHERE 1=1";
        if ($status === 'active') {
            $where .= " AND (status IS NULL OR status != 'cancelled')";
        } elseif ($status === 'cancelled') {
            $where .= " AND status = 'cancelled'";
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$where}");
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $results,
            'pagination' => array(
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$per_page,
                'total_pages' => ceil($total / $per_page)
            )
        ), 200);
    }
    
    /**
     * GET /appointments/{id} - Obtener una cita específica
     */
    public function get_appointment($request) {
        global $wpdb;
        
        $id = $request['id'];
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (!$result) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Cita no encontrada'
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $result
        ), 200);
    }
    
    /**
     * GET /appointments/search - Buscar citas por email, phone, event_id, scheduled_date, scheduled_time
     */
    public function search_appointments($request) {
        global $wpdb;
        
        $email = $request->get_param('email');
        $phone = $request->get_param('phone');
        $event_id = $request->get_param('event_id');
        $name = $request->get_param('name');
        $scheduled_date = $request->get_param('scheduled_date');
        $scheduled_time = $request->get_param('scheduled_time');
        $status = $request->get_param('status');
        $future_only = $request->get_param('future_only') === 'true';
        
        $where = array("1=1");
        $params = array();
        
        if ($email) {
            // Email: TRIM y UPPER tanto en input como en BD
            $where[] = "UPPER(TRIM(email)) = UPPER(TRIM(%s))";
            $params[] = trim($email);
        }
        
        if ($phone) {
            $where[] = "phone = %s";
            $params[] = $phone;
        }
        
        if ($event_id) {
            $where[] = "event_id = %s";
            $params[] = $event_id;
        }
        
        if ($name) {
            // Nombre: TRIM y UPPER tanto en input como en BD
            $where[] = "UPPER(TRIM(name)) = UPPER(TRIM(%s))";
            $params[] = trim($name);
        }
        
        // Filtrar por fecha programada
        if ($scheduled_date) {
            $where[] = "scheduled_date = %s";
            $params[] = $scheduled_date;
        }
        
        // Filtrar por hora programada (comparar solo HH:MM)
        if ($scheduled_time) {
            // Extraer solo HH:MM para comparar (ignora segundos)
            $time_parts = explode(':', $scheduled_time);
            $hour_minute = $time_parts[0] . ':' . (isset($time_parts[1]) ? $time_parts[1] : '00');
            $where[] = "LEFT(scheduled_time, 5) = %s";
            $params[] = $hour_minute;
        }
        
        // Filtrar solo futuras
        if ($future_only) {
            $where[] = "CONCAT(scheduled_date, ' ', scheduled_time) > NOW()";
        }
        
        // Filtrar por status específico si se proporciona
        if ($status === 'active') {
            $where[] = "(status IS NULL OR status != 'cancelled')";
        } elseif ($status === 'cancelled') {
            $where[] = "status = 'cancelled'";
        } elseif ($status) {
            $where[] = "status = %s";
            $params[] = $status;
        } else {
            // Por defecto, solo citas activas (no canceladas)
            $where[] = "(status IS NULL OR status != 'cancelled')";
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY scheduled_date DESC, scheduled_time DESC",
                ...$params
            );
        } else {
            $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY scheduled_date DESC, scheduled_time DESC";
        }
        
        $results = $wpdb->get_results($query);
        
        // Si se busca por fecha y hora específicas, también verificar tabla de seguimientos
        // Esto permite validación cruzada DEMO/Seguimiento
        $followup_results = array();
        if ($scheduled_date && $scheduled_time) {
            $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
            $time_parts = explode(':', $scheduled_time);
            $hour_minute = $time_parts[0] . ':' . (isset($time_parts[1]) ? $time_parts[1] : '00');
            
            $followup_query = $wpdb->prepare(
                "SELECT id, client_name as name, client_email as email, phone, meeting_date as scheduled_date, 
                        meeting_time as scheduled_time, meet_link, status, 'followup' as appointment_type
                 FROM $followup_table 
                 WHERE meeting_date = %s 
                 AND LEFT(meeting_time, 5) = %s 
                 AND status NOT IN ('cancelled', 'completed')",
                $scheduled_date,
                $hour_minute
            );
            $followup_results = $wpdb->get_results($followup_query);
        }
        
        // Combinar resultados de DEMOs y Seguimientos
        $all_results = array_merge($results, $followup_results);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $all_results,
            'count' => count($all_results)
        ), 200);
    }
    
    /**
     * POST /appointments - Crear nueva cita
     */
    public function create_appointment($request) {
        global $wpdb;
        
        $body = json_decode($request->get_body(), true);
        
        // Validar campos requeridos
        $required = array('name', 'email', 'scheduled_date', 'scheduled_time');
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => "Campo requerido: {$field}"
                ), 400);
            }
        }
        
        $data = array(
            'name' => sanitize_text_field($body['name']),
            'email' => sanitize_email($body['email']),
            'phone' => sanitize_text_field($body['phone'] ?? ''),
            'scheduled_date' => sanitize_text_field($body['scheduled_date']),
            'scheduled_time' => sanitize_text_field($body['scheduled_time']),
            'session_id' => sanitize_text_field($body['session_id'] ?? ''),
            'meet_link' => esc_url_raw($body['meet_link'] ?? ''),
            'event_id' => sanitize_text_field($body['event_id'] ?? ''),
            'source' => sanitize_text_field($body['source'] ?? 'web'),
            'status' => 'active',
            'created_at' => current_time('mysql')
        );
        
        $inserted = $wpdb->insert($this->table_name, $data);
        
        if ($inserted === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Error al guardar la cita',
                'error' => $wpdb->last_error
            ), 500);
        }
        
        $appointment_id = $wpdb->insert_id;
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Cita creada exitosamente',
            'appointment_id' => $appointment_id,
            'data' => $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $appointment_id
            ))
        ), 201);
    }
    
    /**
     * PUT /appointments/{id} - Actualizar cita completa (reprogramar)
     */
    public function update_appointment($request) {
        global $wpdb;
        
        $id = $request['id'];
        $body = json_decode($request->get_body(), true);
        
        // Verificar que la cita existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (!$exists) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Cita no encontrada'
            ), 404);
        }
        
        $data = array();
        
        if (isset($body['name'])) {
            $data['name'] = sanitize_text_field($body['name']);
        }
        if (isset($body['email'])) {
            $data['email'] = sanitize_email($body['email']);
        }
        if (isset($body['phone'])) {
            $data['phone'] = sanitize_text_field($body['phone']);
        }
        if (isset($body['scheduled_date'])) {
            $data['scheduled_date'] = sanitize_text_field($body['scheduled_date']);
        }
        if (isset($body['scheduled_time'])) {
            $data['scheduled_time'] = sanitize_text_field($body['scheduled_time']);
        }
        if (isset($body['meet_link'])) {
            $data['meet_link'] = esc_url_raw($body['meet_link']);
        }
        if (isset($body['event_id'])) {
            $data['event_id'] = sanitize_text_field($body['event_id']);
        }
        if (isset($body['status'])) {
            $data['status'] = sanitize_text_field($body['status']);
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $updated = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id)
        );
        
        if ($updated === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Error al actualizar la cita',
                'error' => $wpdb->last_error
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Cita actualizada exitosamente',
            'data' => $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ))
        ), 200);
    }
    
    /**
     * PATCH /appointments/{id} - Actualizar parcialmente
     */
    public function patch_appointment($request) {
        return $this->update_appointment($request);
    }
    
    /**
     * DELETE /appointments/{id} - Eliminar/cancelar cita
     */
    public function delete_appointment($request) {
        global $wpdb;
        
        $id = $request['id'];
        $hard_delete = $request->get_param('hard_delete') === 'true';
        
        // Verificar que existe
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (!$appointment) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Cita no encontrada'
            ), 404);
        }
        
        if ($hard_delete) {
            // Eliminar permanentemente
            $deleted = $wpdb->delete($this->table_name, array('id' => $id));
            
            if ($deleted === false) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Error al eliminar la cita',
                    'error' => $wpdb->last_error
                ), 500);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Cita eliminada permanentemente',
                'appointment' => $appointment
            ), 200);
        } else {
            // Soft delete - marcar como cancelada
            $updated = $wpdb->update(
                $this->table_name,
                array(
                    'status' => 'cancelled',
                    'cancelled_at' => current_time('mysql')
                ),
                array('id' => $id)
            );
            
            if ($updated === false) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Error al cancelar la cita',
                    'error' => $wpdb->last_error
                ), 500);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Cita cancelada exitosamente',
                'data' => $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $id
                ))
            ), 200);
        }
    }
    
    /**
     * POST /send-email - Enviar correo corporativo
     */
    public function send_email($request) {
        $params = $request->get_json_params();
        
        $to = sanitize_email($params['to'] ?? '');
        $subject = sanitize_text_field($params['subject'] ?? '');
        $template = sanitize_text_field($params['template'] ?? 'default');
        $data = $params['data'] ?? array();
        
        // Validar email
        if (empty($to) || !is_email($to)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Email inválido o no proporcionado',
                'to' => $to
            ), 400);
        }
        
        // Generar contenido según template
        $html_body = $this->generate_email_template($template, $data);
        
        // Headers para correo HTML - usar SMTP configurado
        $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Automatiza Tech <' . $from_email . '>',
            'Bcc: automatizacionesbotcore@gmail.com'
        );
        
        // Enviar correo
        $sent = wp_mail($to, $subject, $html_body, $headers);
        
        if ($sent) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Correo enviado exitosamente',
                'to' => $to
            ), 200);
        } else {
            // Obtener error de wp_mail
            global $phpmailer;
            $error_msg = 'Error desconocido';
            if (isset($phpmailer) && is_object($phpmailer)) {
                $error_msg = $phpmailer->ErrorInfo;
            }
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Error al enviar el correo',
                'error' => $error_msg,
                'to' => $to
            ), 500);
        }
    }
    
    /**
     * Generar template de correo HTML
     */
    private function generate_email_template($template, $data) {
        $client_name = sanitize_text_field($data['clientName'] ?? 'Cliente');
        $summary = sanitize_text_field($data['summary'] ?? 'tu cita');
        
        switch ($template) {
            case 'cancellation':
                return $this->get_cancellation_email_template($client_name, $summary);
            default:
                return $this->get_default_email_template($client_name, $data);
        }
    }
    
    /**
     * Template de correo para cancelación de cita
     * Usa la misma estructura que los recordatorios para cumplir políticas antispam
     */
    private function get_cancellation_email_template($client_name, $summary) {
        $site_title = get_bloginfo('name');
        $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
        $footer_text = get_bloginfo('description');
        $whatsapp_url = 'https://wa.me/56927002984';
        $website_url = 'https://automatizatech.cl';
        $contact_email = 'contacto@automatizatech.cl';
        $contact_phone = '+56 9 2700 2984';
        
        return '
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
        .btn-whatsapp { background-color: #25D366; box-shadow: 0 4px 6px rgba(37, 211, 102, 0.3); }
        .btn-web { background-color: #1e40af; box-shadow: 0 4px 6px rgba(30, 64, 175, 0.3); }
        .benefits { background-color: #f8f9fa; border-left: 4px solid #1e40af; padding: 20px; margin: 25px 0; border-radius: 0 4px 4px 0; }
        .footer { background-color: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #888888; }
        .footer a { color: #1e40af; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '">
            <h1>Lamentamos tu Cancelación</h1>
        </div>
        <div class="content">
            <p>Hola <strong>' . esc_html($client_name) . '</strong>,</p>
            <p>Hemos recibido tu solicitud de cancelación de la cita <strong>"' . esc_html($summary) . '"</strong>.</p>
            <p>Entendemos que a veces los planes cambian, pero queremos que sepas que estaremos encantados de recibirte cuando lo desees.</p>
            
            <div class="benefits">
                <h3 style="color: #333; margin: 0 0 15px 0;">✨ Beneficios que te esperan:</h3>
                <ul style="color: #555; margin: 0; padding-left: 20px;">
                    <li>Automatización de procesos de negocio</li>
                    <li>Integración de WhatsApp Business</li>
                    <li>Chatbots inteligentes con IA</li>
                    <li>Ahorro de tiempo y recursos</li>
                    <li>Atención personalizada</li>
                </ul>
            </div>
            
            <p>Si deseas agendar una nueva cita o tienes alguna pregunta, no dudes en contactarnos. ¡Estamos aquí para ayudarte!</p>
            
            <div class="cta-container">
                <a href="' . esc_url($whatsapp_url) . '?text=Hola,%20me%20gustaría%20agendar%20una%20nueva%20cita" class="btn btn-whatsapp">📅 Agendar Nueva Cita</a>
                <a href="' . esc_url($website_url) . '" class="btn btn-web">🌐 Visitar Sitio Web</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . esc_html($site_title) . '. Todos los derechos reservados.</p>
            <p>' . esc_html($footer_text) . '</p>
            <p style="margin-top: 10px;">
                📧 <a href="mailto:' . esc_attr($contact_email) . '">' . esc_html($contact_email) . '</a> | 
                📱 ' . esc_html($contact_phone) . '
            </p>
            <p style="margin-top: 15px; font-size: 11px; color: #aaa;">
                Este correo fue enviado porque cancelaste una cita en nuestro sistema.<br>
                Si no reconoces esta acción, por favor contáctanos.
            </p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template de correo por defecto
     */
    private function get_default_email_template($client_name, $data) {
        $message = isset($data['message']) ? sanitize_textarea_field($data['message']) : 'Gracias por contactarnos.';
        
        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AutomatizaTech</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
        <h2 style="color: #667eea;">Hola ' . esc_html($client_name) . ',</h2>
        <p style="color: #555; line-height: 1.6;">' . nl2br(esc_html($message)) . '</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #888; font-size: 12px;">AutomatizaTech - Automatización inteligente para tu negocio</p>
    </div>
</body>
</html>';
    }
    
    /**
     * GET /appointments/debug - Debug de búsqueda
     */
    public function debug_search($request) {
        global $wpdb;
        
        $email = $request->get_param('email');
        $name = $request->get_param('name');
        $scheduled_date = $request->get_param('scheduled_date');
        $scheduled_time = $request->get_param('scheduled_time');
        
        // Obtener registro ID 70 directamente
        $record = $wpdb->get_row("SELECT id, name, email, scheduled_date, scheduled_time, status FROM {$this->table_name} WHERE id = 70");
        
        // Preparar comparaciones
        $debug = array(
            'input' => array(
                'email' => $email,
                'email_trimmed_upper' => strtoupper(trim($email)),
                'name' => $name,
                'name_trimmed_upper' => strtoupper(trim($name)),
                'scheduled_date' => $scheduled_date,
                'scheduled_time' => $scheduled_time,
                'time_hhmm' => substr($scheduled_time, 0, 5)
            ),
            'record_70' => $record ? array(
                'id' => $record->id,
                'name' => $record->name,
                'name_trimmed_upper' => strtoupper(trim($record->name)),
                'email' => $record->email,
                'email_trimmed_upper' => strtoupper(trim($record->email)),
                'scheduled_date' => $record->scheduled_date,
                'scheduled_time' => $record->scheduled_time,
                'time_hhmm' => substr($record->scheduled_time, 0, 5),
                'status' => $record->status
            ) : 'NOT FOUND',
            'comparisons' => $record ? array(
                'email_match' => strtoupper(trim($email)) === strtoupper(trim($record->email)),
                'name_match' => strtoupper(trim($name)) === strtoupper(trim($record->name)),
                'date_match' => $scheduled_date === $record->scheduled_date,
                'time_match' => substr($scheduled_time, 0, 5) === substr($record->scheduled_time, 0, 5),
                'status_ok' => ($record->status === null || $record->status !== 'cancelled')
            ) : 'N/A'
        );
        
        return new WP_REST_Response($debug, 200);
    }
    
    // NOTA: El menú de admin se maneja desde admin-reminders.php para evitar duplicados
    // Esta clase solo proporciona la API REST
}

// Inicializar
new AutomatizaTech_Appointments_API();
