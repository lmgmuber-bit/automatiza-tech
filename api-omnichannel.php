<?php
/**
 * API REST Omnicanal - Endpoints para el portal de clientes
 * 
 * Provee endpoints JSON para:
 * - Autenticación por API key (clientes)
 * - Gestión de conversaciones, mensajes, canales, bots, agentes
 * - Toma de control por ejecutivo
 * - Webhook receptor para canales
 * 
 * Todos los endpoints requieren autenticación excepto webhook.
 */

ob_start();
require_once __DIR__ . '/wp-load.php';
ob_end_clean();

require_once __DIR__ . '/omnichannel-controller.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS para el portal React
$allowed_origins = [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://localhost:5175',
    'http://localhost:5176',
    'http://localhost:3000',
    'http://localhost',
    rtrim(get_site_url(), '/'),
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} 
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Admin-Token, X-Agent-Token');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$controller = new OmnichannelController();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['route']) ? sanitize_text_field($_GET['route']) : '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];

// ============================
// AUTENTICACIÓN
// ============================

function authenticate_client($controller) {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
    if (empty($api_key)) {
        send_json(['error' => 'API key requerida'], 401);
    }
    $client = $controller->validate_api_key($api_key);
    if (!$client) {
        // Check if the key exists but is expired
        global $wpdb;
        $raw = $wpdb->get_row($wpdb->prepare(
            "SELECT status, period_end, is_free FROM {$wpdb->prefix}omnichannel_clients WHERE api_key = %s",
            sanitize_text_field($api_key)
        ));
        if ($raw && $raw->status === 'suspended' && !empty($raw->period_end) && strtotime($raw->period_end) < time()) {
            send_json(['error' => 'Tu período de servicio ha expirado. Contacta al administrador para renovar.', 'expired' => true], 403);
        }
        send_json(['error' => 'API key inválida o cuenta suspendida'], 403);
    }
    return $client;
}

function authenticate_admin() {
    // 1. WordPress session auth (wp-admin)
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return;
    }
    // 2. Token auth (mobile / external)
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if (!empty($token)) {
        $valid = validate_admin_token($token);
        if ($valid) {
            // Set WP current user so audit_log picks up user info
            wp_set_current_user($valid);
            return;
        }
    }
    send_json(['error' => 'Acceso denegado. Requiere permisos de administrador.'], 403);
}

/**
 * Generate a time-limited admin token using WordPress nonce system.
 * Token = base64( user_id : expiry : hmac )
 */
function generate_admin_token($user_id) {
    $expiry = time() + (7 * 24 * 3600); // 7 days
    $data = $user_id . ':' . $expiry;
    $hmac = hash_hmac('sha256', $data, wp_salt('auth'));
    return base64_encode($data . ':' . $hmac);
}

function validate_admin_token($token) {
    $decoded = base64_decode($token, true);
    if (!$decoded) return false;
    
    $parts = explode(':', $decoded);
    if (count($parts) !== 3) return false;
    
    list($user_id, $expiry, $hmac) = $parts;
    
    // Check expiry
    if ((int)$expiry < time()) return false;
    
    // Check HMAC
    $data = $user_id . ':' . $expiry;
    $expected = hash_hmac('sha256', $data, wp_salt('auth'));
    if (!hash_equals($expected, $hmac)) return false;
    
    // Verify user is still admin
    $user = get_user_by('ID', (int)$user_id);
    if (!$user || !user_can($user, 'manage_options')) return false;
    
    return (int)$user_id;
}

function send_json($data, $code = 200) {
    http_response_code($code);
    echo wp_json_encode($data);
    exit;
}

// ============================
// ROUTING
// ============================

// Parse route segments
$segments = array_filter(explode('/', $path));
$segments = array_values($segments);

try {
    // ---- HEALTH CHECK (para fallback N8N) ----
    if (isset($segments[0]) && $segments[0] === 'health') {
        send_json(['status' => 'ok', 'timestamp' => current_time('c')]);
    }

    // ---- N8N CALLBACK (respuesta del bot) ----
    if (isset($segments[0]) && $segments[0] === 'webhook' &&
        isset($segments[1]) && $segments[1] === 'n8n-callback' && $method === 'POST') {
        $result = $controller->handle_n8n_callback($body);
        send_json($result, isset($result['error']) ? 400 : 200);
    }

    // ---- WEBHOOK (sin auth) ----
    if (isset($segments[0]) && $segments[0] === 'webhook' && $method === 'POST') {
        $channel_id = absint($segments[1] ?? 0);
        $provided_secret = sanitize_text_field($_GET['secret'] ?? '');
        
        if (!$channel_id || empty($provided_secret)) {
            send_json(['error' => 'Canal o secreto inválido'], 400);
        }
        
        // Verificar webhook secret
        global $wpdb;
        $channel = $wpdb->get_row($wpdb->prepare(
            "SELECT webhook_secret, provider FROM {$wpdb->prefix}omnichannel_channels WHERE id = %d AND is_active = 1",
            $channel_id
        ));
        
        if (!$channel || !hash_equals($channel->webhook_secret, $provided_secret)) {
            send_json(['error' => 'Webhook no autorizado'], 403);
        }

        // Route to YCloud handler if provider is ycloud
        if (($channel->provider ?? '') === 'ycloud' || isset($body['whatsappInboundMessage']) || ($body['type'] ?? '') === 'whatsapp.inbound_message.received') {
            $result = $controller->handle_ycloud_webhook($channel_id, $body);
            send_json($result, isset($result['error']) ? 400 : 200);
        }
        
        $result = $controller->receive_message($channel_id, $body);
        send_json($result, isset($result['error']) ? 400 : 200);
    }

    // ---- CRON: Expiry reminders (called by N8N, secured with secret) ----
    if (isset($segments[0]) && $segments[0] === 'cron' && isset($segments[1]) && $segments[1] === 'expiry-reminders') {
        $provided_secret = sanitize_text_field($_GET['secret'] ?? ($body['secret'] ?? ''));
        $cron_secret = defined('OMNICHANNEL_CRON_SECRET') ? OMNICHANNEL_CRON_SECRET : 'omni_cron_2026_s3cur3';

        if (empty($provided_secret) || !hash_equals($cron_secret, $provided_secret)) {
            send_json(['error' => 'No autorizado'], 403);
        }

        $controller->process_expiry_reminders();

        // Return summary of what was processed
        global $wpdb;
        $pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}omnichannel_clients 
             WHERE period_end IS NOT NULL AND is_free = 0 AND status IN ('active','trial')"
        );
        $expired = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}omnichannel_clients 
             WHERE period_end IS NOT NULL AND is_free = 0 AND status = 'suspended' 
               AND period_end <= CURDATE()"
        );

        send_json([
            'success' => true,
            'message' => 'Expiry reminders processed',
            'checked_clients' => $pending,
            'expired_clients' => $expired,
            'timestamp' => current_time('mysql'),
        ]);
    }

    // ---- PUBLIC: Support ticket from login screen (no auth) ----
    if (isset($segments[0]) && $segments[0] === 'public' && isset($segments[1]) && $segments[1] === 'support-ticket' && $method === 'POST') {
        $result = $controller->create_public_ticket($body);
        send_json($result, isset($result['error']) ? 400 : 201);
    }

    // ---- PUBLIC: Upload ticket images (no auth, rate limited by max 5 files) ----
    if (isset($segments[0]) && $segments[0] === 'public' && isset($segments[1]) && $segments[1] === 'upload-images' && $method === 'POST') {
        if (empty($_FILES['images'])) send_json(['error' => 'No se enviaron imágenes'], 400);
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $max_size = 3 * 1024 * 1024; // 3MB per file
        $urls = [];
        $files = $_FILES['images'];

        // Normalize to array format
        if (!is_array($files['name'])) {
            $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
        }

        $count = min(count($files['name']), 5);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($files['type'][$i], $allowed_types, true)) continue;
            if ($files['size'][$i] > $max_size) continue;

            $single = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            $upload = wp_handle_upload($single, ['test_form' => false]);
            if (!isset($upload['error'])) {
                $urls[] = $upload['url'];
            }
        }

        send_json(['urls' => $urls]);
    }

    // ---- ADMIN ROUTES (requiere WordPress admin) ----
    if (isset($segments[0]) && $segments[0] === 'admin') {
        // Ruta especial: verificar sesión admin (no requiere auth previa)
        if (($segments[1] ?? '') === 'session-check' && $method === 'GET') {
            // Check token auth
            $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
            if (!empty($token)) {
                $user_id = validate_admin_token($token);
                if ($user_id) {
                    $user = get_user_by('ID', $user_id);
                    send_json([
                        'authenticated' => true,
                        'role'          => 'admin',
                        'user'          => $user->display_name,
                        'email'         => $user->user_email,
                    ]);
                }
            }
            // Check WP session
            if (is_user_logged_in() && current_user_can('manage_options')) {
                $user = wp_get_current_user();
                send_json([
                    'authenticated' => true,
                    'role'          => 'admin',
                    'user'          => $user->display_name,
                    'email'         => $user->user_email,
                ]);
            }
            send_json(['authenticated' => false], 200);
        }

        // Ruta: login admin con usuario/contraseña → retorna token
        if (($segments[1] ?? '') === 'login' && $method === 'POST') {
            $username = sanitize_text_field($body['username'] ?? '');
            $password = $body['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                send_json(['error' => 'Usuario y contraseña requeridos'], 400);
            }

            // Check prohibited characters
            if (preg_match('/[<>"\';\\\\\`{}|]/', $username) || preg_match('/[<>"\';\\\\\`{}|]/', $password)) {
                send_json(['error' => 'Caracteres no permitidos: < > " \' ; \\ ` { } |'], 400);
            }
            
            $user = wp_authenticate($username, $password);
            
            if (is_wp_error($user)) {
                send_json(['error' => 'Credenciales inválidas'], 401);
            }
            
            if (!user_can($user, 'manage_options')) {
                send_json(['error' => 'Este usuario no tiene permisos de administrador'], 403);
            }
            
            $token = generate_admin_token($user->ID);
            
            send_json([
                'success' => true,
                'token'   => $token,
                'user'    => $user->display_name,
                'email'   => $user->user_email,
                'role'    => 'admin',
            ]);
        }

        authenticate_admin();
        
        $admin_action = $segments[1] ?? '';
        
        switch ($admin_action) {
            case 'stats':
                send_json($controller->get_superadmin_stats());
                
            case 'clients':
                if ($method === 'GET' && !isset($segments[2])) {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 20), 100);
                    send_json($controller->get_clients([
                        'status'    => sanitize_text_field($_GET['status'] ?? ''),
                        'search'    => sanitize_text_field($_GET['search'] ?? ''),
                        'plan_type' => sanitize_text_field($_GET['plan_type'] ?? ''),
                    ], $page, $per_page));
                }
                if ($method === 'POST' && !isset($segments[2])) {
                    $result = $controller->create_client($body);
                    send_json($result, $result ? 201 : 400);
                }
                if ($method === 'GET' && isset($segments[2])) {
                    $client = $controller->get_client(absint($segments[2]));
                    if (!$client) send_json(['error' => 'No encontrado'], 404);
                    send_json($client);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_client(absint($segments[2]), $body);
                    if (is_array($result) && isset($result['error'])) {
                        send_json($result, 400);
                    }
                    send_json(['success' => $result]);
                }
                if ($method === 'DELETE' && isset($segments[2])) {
                    $result = $controller->delete_client(absint($segments[2]));
                    send_json(['success' => $result]);
                }
                break;
                
            case 'audit':
                if ($method === 'GET') {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 50), 200);
                    send_json($controller->get_audit_logs([
                        'client_id'   => sanitize_text_field($_GET['client_id'] ?? ''),
                        'action'      => sanitize_text_field($_GET['action'] ?? ''),
                        'entity_type' => sanitize_text_field($_GET['entity_type'] ?? ''),
                        'date_from'   => sanitize_text_field($_GET['date_from'] ?? ''),
                        'date_to'     => sanitize_text_field($_GET['date_to'] ?? ''),
                        'user_id'     => sanitize_text_field($_GET['user_id'] ?? ''),
                        'search'      => sanitize_text_field($_GET['search'] ?? ''),
                        'orderby'     => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                        'order'       => sanitize_text_field($_GET['order'] ?? 'DESC'),
                    ], $page, $per_page));
                }
                break;

            // Admin: ver TODAS las conversaciones de todos los clientes
            case 'conversations':
                if ($method === 'GET' && !isset($segments[2])) {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 30), 100);
                    send_json($controller->get_all_conversations([
                        'status'       => sanitize_text_field($_GET['status'] ?? ''),
                        'channel_type' => sanitize_text_field($_GET['channel_type'] ?? ''),
                        'search'       => sanitize_text_field($_GET['search'] ?? ''),
                        'client_id'    => sanitize_text_field($_GET['client_id'] ?? ''),
                    ], $page, $per_page));
                }
                if ($method === 'GET' && isset($segments[2]) && ($segments[3] ?? '') === 'messages') {
                    $conv_id = absint($segments[2]);
                    $page = absint($_GET['page'] ?? 1);
                    send_json($controller->get_messages($conv_id, $page));
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'messages') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->send_message($conv_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'takeover') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->takeover_conversation($conv_id, absint($body['agent_id'] ?? 0), sanitize_text_field($body['reason'] ?? ''));
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                break;

            // Admin: CRUD de tipos de canal
            case 'channel-types':
                if ($method === 'GET') {
                    send_json($controller->get_channel_types(true));
                }
                if ($method === 'POST') {
                    $result = $controller->create_channel_type($body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_channel_type(absint($segments[2]), $body);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                if ($method === 'DELETE' && isset($segments[2])) {
                    $result = $controller->delete_channel_type(absint($segments[2]));
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                break;

            // Admin: ver TODOS los canales de todos los clientes
            case 'channels':
                if ($method === 'GET') {
                    send_json($controller->get_all_channels());
                }
                if ($method === 'POST') {
                    $client_id = absint($body['client_id'] ?? 0);
                    if (!$client_id) send_json(['error' => 'client_id requerido'], 400);
                    $result = $controller->create_channel($client_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_channel(absint($segments[2]), $body);
                    send_json(['success' => $result]);
                }
                break;

            // Admin: ver TODOS los bots de todos los clientes
            case 'bots':
                if ($method === 'GET' && !isset($segments[2])) {
                    send_json($controller->get_all_bot_configs());
                }
                if ($method === 'GET' && isset($segments[2])) {
                    $config = $controller->get_bot_config(absint($segments[2]));
                    send_json($config ?: ['error' => 'No encontrado']);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_bot_config(absint($segments[2]), $body);
                    send_json(['success' => $result]);
                }
                break;

            // Admin: ver TODOS los agentes de todos los clientes
            case 'agents':
                if ($method === 'GET') {
                    $page = absint($_GET['page'] ?? 0);
                    $per_page = min(absint($_GET['per_page'] ?? 0), 100);
                    if ($page > 0 && $per_page > 0) {
                        send_json($controller->get_all_agents([
                            'search'  => sanitize_text_field($_GET['search'] ?? ''),
                            'orderby' => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                            'order'   => sanitize_text_field($_GET['order'] ?? 'DESC'),
                        ], $page, $per_page));
                    } else {
                        send_json($controller->get_all_agents());
                    }
                }
                if ($method === 'POST') {
                    $client_id = absint($body['client_id'] ?? 0);
                    if (!$client_id) send_json(['error' => 'client_id requerido'], 400);
                    $result = $controller->create_agent($client_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                // PUT /admin/agents/{id} - update agent (password, skills, etc.)
                if ($method === 'PUT' && isset($segments[2])) {
                    $agent_id = absint($segments[2]);
                    $results = [];
                    if (!empty($body['password'])) {
                        $results[] = $controller->set_agent_password($agent_id, $body['password']);
                    }
                    if (isset($body['skills'])) {
                        $results[] = $controller->update_agent_skills($agent_id, $body['skills'], $body['department'] ?? null);
                    }
                    $errors = array_filter($results, fn($r) => isset($r['error']));
                    if ($errors) send_json(reset($errors), 400);
                    send_json(['success' => true]);
                }
                // DELETE /admin/agents/{id} - only AT admin can delete agents
                if ($method === 'DELETE' && isset($segments[2])) {
                    $agent_id = absint($segments[2]);
                    // Get the agent to know its client_id
                    $agent_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT client_id FROM {$wpdb->prefix}omnichannel_agents WHERE id = %d", $agent_id
                    ));
                    if (!$agent_row) send_json(['error' => 'Agente no encontrado'], 404);
                    $result = $controller->delete_agent($agent_id, $agent_row->client_id);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                // POST /admin/agents/{id}/reset-password — AT admin can reset any agent's password
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'reset-password') {
                    $agent_id = absint($segments[2]);
                    $new_password = $body['new_password'] ?? '';
                    if (empty($new_password) || strlen($new_password) < 6) {
                        send_json(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);
                    }
                    $result = $controller->set_agent_password($agent_id, $new_password);
                    if (isset($result['error'])) send_json($result, 400);
                    $controller->audit_log('update', 'agent', $agent_id, 'Contraseña reseteada por admin AT');
                    send_json(['success' => true, 'message' => 'Contraseña actualizada']);
                }
                break;

            // Admin: Bot Templates CRUD
            case 'bot-templates':
                if ($method === 'GET' && !isset($segments[2])) {
                    $client_id = absint($_GET['client_id'] ?? 0);
                    send_json($client_id ? $controller->get_bot_templates($client_id) : $controller->get_all_bot_templates());
                }
                if ($method === 'GET' && isset($segments[2])) {
                    $tpl = $controller->get_bot_template(absint($segments[2]));
                    send_json($tpl ?: ['error' => 'Template no encontrado'], $tpl ? 200 : 404);
                }
                if ($method === 'POST') {
                    $client_id = absint($body['client_id'] ?? 0);
                    if (!$client_id) send_json(['error' => 'client_id requerido'], 400);
                    $result = $controller->create_bot_template($client_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_bot_template(absint($segments[2]), $body);
                    send_json(['success' => $result]);
                }
                if ($method === 'DELETE' && isset($segments[2])) {
                    $result = $controller->delete_bot_template(absint($segments[2]));
                    send_json(['success' => $result]);
                }
                break;

            // Admin: N8N Workflows CRUD
            case 'workflows':
                if ($method === 'GET' && !isset($segments[2])) {
                    $client_id = absint($_GET['client_id'] ?? 0);
                    send_json($client_id ? $controller->get_n8n_workflows($client_id) : $controller->get_all_n8n_workflows());
                }
                if ($method === 'POST') {
                    $client_id = absint($body['client_id'] ?? 0);
                    if (!$client_id) send_json(['error' => 'client_id requerido'], 400);
                    $result = $controller->create_n8n_workflow($client_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_n8n_workflow(absint($segments[2]), $body);
                    send_json(['success' => $result]);
                }
                if ($method === 'DELETE' && isset($segments[2])) {
                    $result = $controller->delete_n8n_workflow(absint($segments[2]));
                    send_json(['success' => $result]);
                }
                break;

            // Admin: Import WP users as clients
            case 'wp-users':
                if ($method === 'GET') {
                    $search = sanitize_text_field($_GET['search'] ?? '');
                    send_json($controller->get_importable_wp_users($search));
                }
                if ($method === 'POST') {
                    $wp_user_id = absint($body['wp_user_id'] ?? 0);
                    if (!$wp_user_id) send_json(['error' => 'wp_user_id requerido'], 400);
                    $result = $controller->import_wp_user_as_client($wp_user_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                break;

            // Admin: Import CRM prospects (from wp_crm_clientes)
            case 'crm-prospects':
                if ($method === 'GET') {
                    $search = sanitize_text_field($_GET['search'] ?? '');
                    send_json($controller->get_importable_crm_prospects($search));
                }
                if ($method === 'POST') {
                    $crm_id = absint($body['crm_id'] ?? 0);
                    if (!$crm_id) send_json(['error' => 'crm_id requerido'], 400);
                    $result = $controller->import_crm_prospect($crm_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                break;

            // Admin: Support Tickets management
            case 'tickets':
                // GET /admin/tickets — all tickets
                if ($method === 'GET' && !isset($segments[2])) {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 15), 100);
                    send_json($controller->get_tickets([
                        'status'   => sanitize_text_field($_GET['status'] ?? ''),
                        'category' => sanitize_text_field($_GET['category'] ?? ''),
                        'priority' => sanitize_text_field($_GET['priority'] ?? ''),
                        'search'   => sanitize_text_field($_GET['search'] ?? ''),
                    ], $page, $per_page));
                }
                // GET /admin/tickets/count — open ticket count
                if ($method === 'GET' && isset($segments[2]) && $segments[2] === 'count') {
                    send_json(['count' => $controller->get_open_ticket_count()]);
                }
                // GET /admin/tickets/{id} — single ticket detail
                if ($method === 'GET' && isset($segments[2]) && $segments[2] !== 'count') {
                    $ticket = $controller->get_ticket(absint($segments[2]));
                    if (!$ticket) send_json(['error' => 'Ticket no encontrado'], 404);
                    send_json($ticket);
                }
                // PUT /admin/tickets/{id} — update status / notes
                if ($method === 'PUT' && isset($segments[2])) {
                    $result = $controller->update_ticket_status(absint($segments[2]), $body);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                // POST /admin/tickets/{id}/messages — admin reply
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'messages') {
                    $user = wp_get_current_user();
                    $body['sender_type']  = 'admin';
                    $body['sender_name']  = $user->display_name ?: 'Admin';
                    $body['sender_email'] = $user->user_email ?: '';
                    $result = $controller->add_ticket_message(absint($segments[2]), $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                // POST /admin/tickets/upload-images — upload images for ticket
                if ($method === 'POST' && isset($segments[2]) && $segments[2] === 'upload-images') {
                    if (empty($_FILES['images'])) send_json(['error' => 'No se enviaron imágenes'], 400);
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $max_size = 3 * 1024 * 1024;
                    $urls = [];
                    $files = $_FILES['images'];
                    if (!is_array($files['name'])) {
                        $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
                    }
                    $count = min(count($files['name']), 5);
                    for ($i = 0; $i < $count; $i++) {
                        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                        if (!in_array($files['type'][$i], $allowed_types, true)) continue;
                        if ($files['size'][$i] > $max_size) continue;
                        $single = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                        $upload = wp_handle_upload($single, ['test_form' => false]);
                        if (!isset($upload['error'])) {
                            $urls[] = $upload['url'];
                        }
                    }
                    send_json(['urls' => $urls]);
                }
                break;

            // Admin: Auto-assign conversation to best agent
            case 'auto-assign':
                if ($method === 'POST') {
                    $conv_id = absint($body['conversation_id'] ?? 0);
                    $skill = sanitize_text_field($body['skill'] ?? '');
                    if (!$conv_id) send_json(['error' => 'conversation_id requerido'], 400);
                    $result = $controller->auto_assign_conversation($conv_id, $skill ?: null);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                break;

            // Admin: Human intervention endpoints
            case 'intervention':
                if ($method === 'POST' && ($segments[2] ?? '') === 'start') {
                    $conv_id = absint($body['conversation_id'] ?? 0);
                    $agent_id = absint($body['agent_id'] ?? 0);
                    if (!$conv_id || !$agent_id) send_json(['error' => 'conversation_id y agent_id requeridos'], 400);
                    send_json($controller->start_intervention($conv_id, $agent_id));
                }
                if ($method === 'POST' && ($segments[2] ?? '') === 'end') {
                    $conv_id = absint($body['conversation_id'] ?? 0);
                    $agent_id = absint($body['agent_id'] ?? 0);
                    if (!$conv_id || !$agent_id) send_json(['error' => 'conversation_id y agent_id requeridos'], 400);
                    send_json($controller->end_intervention($conv_id, $agent_id));
                }
                if ($method === 'POST' && ($segments[2] ?? '') === 'send') {
                    $conv_id = absint($body['conversation_id'] ?? 0);
                    if (!$conv_id) send_json(['error' => 'conversation_id requerido'], 400);
                    send_json($controller->send_agent_message($conv_id, $body));
                }
                break;

            default:
                send_json(['error' => 'Ruta admin no encontrada'], 404);
        }
    }

    // ---- AGENT ROUTES (requiere token de agente) ----
    if (isset($segments[0]) && $segments[0] === 'agent') {
        // Login: no requiere auth previa
        if (($segments[1] ?? '') === 'login' && $method === 'POST') {
            $email = sanitize_email($body['email'] ?? '');
            $password = $body['password'] ?? '';
            if (empty($email) || empty($password)) {
                send_json(['error' => 'Email y contraseña requeridos'], 400);
            }
            $result = $controller->authenticate_agent($email, $password);
            send_json($result, isset($result['error']) ? 401 : 200);
        }

        // Forgot password: no requiere auth previa
        if (($segments[1] ?? '') === 'forgot-password' && $method === 'POST') {
            $email = sanitize_email($body['email'] ?? '');
            if (empty($email)) {
                send_json(['error' => 'Email requerido'], 400);
            }
            $result = $controller->request_password_reset($email);
            send_json($result, isset($result['error']) ? 404 : 200);
        }

        // Validate reset token: no requiere auth previa
        if (($segments[1] ?? '') === 'validate-reset-token' && $method === 'POST') {
            $email = sanitize_email($body['email'] ?? '');
            $token = sanitize_text_field($body['token'] ?? '');
            if (empty($email) || empty($token)) {
                send_json(['error' => 'Email y token requeridos'], 400);
            }
            $result = $controller->validate_reset_token($email, $token);
            send_json($result, isset($result['error']) ? 400 : 200);
        }

        // Reset password with token: no requiere auth previa
        if (($segments[1] ?? '') === 'reset-password' && $method === 'POST') {
            $email = sanitize_email($body['email'] ?? '');
            $token = sanitize_text_field($body['token'] ?? '');
            $new_password = $body['new_password'] ?? '';
            if (empty($email) || empty($token) || empty($new_password)) {
                send_json(['error' => 'Email, token y nueva contraseña requeridos'], 400);
            }
            $result = $controller->reset_password_with_token($email, $token, $new_password);
            send_json($result, isset($result['error']) ? 400 : 200);
        }

        // Session check: validate existing token
        if (($segments[1] ?? '') === 'session-check' && $method === 'GET') {
            $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
            if (!empty($token)) {
                $agent = $controller->validate_agent_token($token);
                if ($agent) {
                    // Check client period
                    $client_row = $controller->get_client($agent->client_id);
                    $period_warning = $client_row ? $controller->check_client_period($client_row) : null;
                    if ($period_warning && !empty($period_warning['expired'])) {
                        send_json(['authenticated' => false, 'expired' => true, 'error' => 'El período de servicio ha expirado.'], 200);
                    }
                    $skills = $agent->skills ? json_decode($agent->skills, true) : [];
                    send_json([
                        'authenticated' => true,
                        'role'          => 'agent',
                        'agent'         => [
                            'id'          => (int) $agent->id,
                            'client_id'   => (int) $agent->client_id,
                            'client_name' => $agent->client_name,
                            'name'        => $agent->name,
                            'email'       => $agent->email,
                            'role'        => $agent->role,
                            'skills'      => $skills,
                            'department'  => $agent->department,
                            'avatar_url'  => $agent->avatar_url,
                            'max_concurrent_chats' => (int) $agent->max_concurrent_chats,
                        ],
                        'period_warning' => $period_warning,
                    ]);
                }
            }
            send_json(['authenticated' => false], 200);
        }

        // All other agent routes require valid token
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        $current_agent = $controller->validate_agent_token($token);
        if (!$current_agent) {
            send_json(['error' => 'Token de agente inválido o expirado'], 401);
        }
        $agent_id = (int) $current_agent->id;
        $agent_client_id = (int) $current_agent->client_id;

        // Set actor for audit logging (wp_get_current_user is empty in agent sessions)
        $controller->set_actor($current_agent->email, $current_agent->name, $current_agent->role);

        $agent_action = $segments[1] ?? '';

        $agent_role = $current_agent->role; // 'admin', 'supervisor', 'agent'
        $is_supervisor = in_array($agent_role, ['supervisor', 'admin'], true);

        switch ($agent_action) {
            // Agent: all conversations of company (with is_mine / is_readonly flags)
            case 'conversations':
                if ($method === 'GET' && !isset($segments[2])) {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 30), 100);
                    send_json($controller->get_agent_conversations($agent_id, [
                        'status' => sanitize_text_field($_GET['status'] ?? ''),
                        'search' => sanitize_text_field($_GET['search'] ?? ''),
                        'scope'  => sanitize_text_field($_GET['scope'] ?? ''),
                    ], $page, $per_page));
                }
                if ($method === 'GET' && isset($segments[2]) && ($segments[3] ?? '') === 'messages') {
                    $conv_id = absint($segments[2]);
                    $page = absint($_GET['page'] ?? 1);
                    send_json($controller->get_messages($conv_id, $page));
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'messages') {
                    $conv_id = absint($segments[2]);
                    // Regular agents can only send to conversations assigned to them
                    if (!$is_supervisor) {
                        $assigned = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT assigned_agent_id FROM {$wpdb->prefix}omnichannel_conversations WHERE id = %d AND client_id = %d",
                            $conv_id, $agent_client_id
                        ));
                        if ($assigned !== $agent_id) {
                            send_json(['error' => 'Solo puedes enviar mensajes en conversaciones asignadas a ti'], 403);
                        }
                    }
                    $result = $controller->send_message($conv_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'takeover') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->takeover_conversation($conv_id, $agent_id, sanitize_text_field($body['reason'] ?? ''));
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'release') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->release_conversation($conv_id, $agent_id);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'transfer') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->transfer_conversation(
                        $conv_id, $agent_id,
                        absint($body['to_agent_id'] ?? 0),
                        sanitize_text_field($body['notes'] ?? '')
                    );
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                break;

            // Agent: agents of same company
            case 'agents':
                if ($method === 'GET') {
                    $page = absint($_GET['page'] ?? 0);
                    $per_page = min(absint($_GET['per_page'] ?? 0), 100);
                    if ($page > 0 && $per_page > 0) {
                        send_json($controller->get_agents($agent_client_id, [
                            'search'  => sanitize_text_field($_GET['search'] ?? ''),
                            'orderby' => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                            'order'   => sanitize_text_field($_GET['order'] ?? 'DESC'),
                        ], $page, $per_page));
                    } else {
                        send_json($controller->get_agents($agent_client_id));
                    }
                }
                // Supervisor: create agent (cannot assign 'admin' role)
                if ($method === 'POST' && $is_supervisor) {
                    if ($agent_role === 'supervisor' && ($body['role'] ?? '') === 'admin') {
                        send_json(['error' => 'Los supervisores no pueden crear agentes con rol administrador'], 403);
                    }
                    $result = $controller->create_agent($agent_client_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                // Supervisor: update agent (cannot change role to 'admin' unless caller is admin)
                if ($method === 'PUT' && isset($segments[2]) && $is_supervisor) {
                    if ($agent_role === 'supervisor' && ($body['role'] ?? '') === 'admin') {
                        send_json(['error' => 'Los supervisores no pueden asignar rol administrador'], 403);
                    }
                    $target_id = absint($segments[2]);
                    $results = [];
                    if (!empty($body['password'])) {
                        $results[] = $controller->set_agent_password($target_id, $body['password']);
                    }
                    $results[] = $controller->update_agent($target_id, $body, $current_agent);
                    $errors = array_filter($results, fn($r) => isset($r['error']));
                    if ($errors) send_json(reset($errors), 400);
                    send_json(['success' => true]);
                }
                if (($method === 'POST' || $method === 'PUT') && !$is_supervisor) {
                    send_json(['error' => 'Solo supervisores y admins pueden gestionar agentes'], 403);
                }
                break;

            // Agent: channels of their company
            // Agent: channel types (read-only, for selects)
            case 'channel-types':
                if ($method === 'GET') {
                    send_json($controller->get_channel_types(false));
                }
                break;

            case 'channels':
                if ($method === 'GET') {
                    send_json($controller->get_channels($agent_client_id));
                }
                // Channels management is admin/client only — agents/supervisors cannot create/update
                if ($method === 'POST' || $method === 'PUT') {
                    send_json(['error' => 'Solo el administrador de AT o el cliente pueden gestionar canales'], 403);
                }
                break;

            // Agent: bots config (supervisor can edit)
            case 'bots':
                if ($method === 'GET' && !isset($segments[2])) {
                    send_json($controller->get_bot_configs($agent_client_id));
                }
                if ($method === 'GET' && isset($segments[2])) {
                    $config = $controller->get_bot_config(absint($segments[2]));
                    send_json($config ?: ['error' => 'No encontrado']);
                }
                if ($method === 'PUT' && isset($segments[2]) && $is_supervisor) {
                    $result = $controller->update_bot_config(absint($segments[2]), $body);
                    send_json(['success' => $result]);
                }
                if ($method === 'PUT' && !$is_supervisor) {
                    send_json(['error' => 'Solo supervisores y admins pueden editar bots'], 403);
                }
                break;

            // Agent: audit logs (supervisor only)
            case 'audit':
                if ($method === 'GET' && $is_supervisor) {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 15), 200);
                    send_json($controller->get_audit_logs([
                        'client_id' => $agent_client_id,
                        'search'    => sanitize_text_field($_GET['search'] ?? ''),
                        'orderby'   => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                        'order'     => sanitize_text_field($_GET['order'] ?? 'DESC'),
                    ], $page, $per_page));
                }
                if (!$is_supervisor) {
                    send_json(['error' => 'Solo supervisores y admins pueden ver auditoría'], 403);
                }
                break;

            // Agent: profile
            case 'profile':
                if ($method === 'GET') {
                    $skills_raw = $current_agent->skills ?? '';
                    $skills = !empty($skills_raw) ? (json_decode($skills_raw, true) ?: []) : [];
                    send_json([
                        'id'          => (int) $current_agent->id,
                        'client_id'   => (int) ($current_agent->client_id ?? 0),
                        'client_name' => $current_agent->client_name ?? '',
                        'name'        => $current_agent->name ?? '',
                        'email'       => $current_agent->email ?? '',
                        'role'        => $current_agent->role ?? 'agent',
                        'skills'      => $skills,
                        'department'  => $current_agent->department ?? '',
                        'avatar_url'  => $current_agent->avatar_url ?? '',
                        'active_chats' => (int) ($current_agent->active_chats ?? 0),
                        'max_concurrent_chats' => (int) ($current_agent->max_concurrent_chats ?? 0),
                    ]);
                }
                // PUT /agent/profile — update own profile (name, department)
                if ($method === 'PUT') {
                    $allowed = [];
                    if (isset($body['name'])) $allowed['name'] = sanitize_text_field($body['name']);
                    if (isset($body['department'])) $allowed['department'] = sanitize_text_field($body['department']);
                    if (empty($allowed)) send_json(['error' => 'Nada que actualizar'], 400);
                    $result = $wpdb->update($wpdb->prefix . 'omnichannel_agents', $allowed, ['id' => $agent_id]);
                    if ($result !== false) {
                        $controller->audit_log('update', 'agent', $agent_id, "Perfil actualizado por el propio agente", null, $allowed, $agent_client_id);
                        send_json(['success' => true]);
                    }
                    send_json(['error' => 'Error al actualizar perfil'], 500);
                }
                break;

            // Agent: upload avatar
            case 'avatar':
                if ($method === 'POST') {
                    if (empty($_FILES['avatar'])) send_json(['error' => 'No se envió imagen'], 400);
                    $file = $_FILES['avatar'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    if (!in_array($file['type'], $allowed_types, true)) {
                        send_json(['error' => 'Tipo de archivo no permitido. Use JPG, PNG, WebP o GIF'], 400);
                    }
                    if ($file['size'] > 2 * 1024 * 1024) {
                        send_json(['error' => 'La imagen no debe superar 2MB'], 400);
                    }
                    // Use WordPress upload
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    if (isset($upload['error'])) {
                        send_json(['error' => $upload['error']], 400);
                    }
                    $avatar_url = $upload['url'];
                    $wpdb->update($wpdb->prefix . 'omnichannel_agents', ['avatar_url' => esc_url_raw($avatar_url)], ['id' => $agent_id]);
                    $controller->audit_log('update', 'agent', $agent_id, "Avatar actualizado", null, ['avatar_url' => $avatar_url], $agent_client_id);
                    send_json(['avatar_url' => $avatar_url]);
                }
                break;

            // Agent: upload ticket images (authenticated)
            case 'ticket-images':
                if ($method === 'POST') {
                    if (empty($_FILES['images'])) send_json(['error' => 'No se enviaron imágenes'], 400);
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $max_size = 3 * 1024 * 1024;
                    $urls = [];
                    $files = $_FILES['images'];
                    if (!is_array($files['name'])) {
                        $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
                    }
                    $count = min(count($files['name']), 5);
                    for ($i = 0; $i < $count; $i++) {
                        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                        if (!in_array($files['type'][$i], $allowed_types, true)) continue;
                        if ($files['size'][$i] > $max_size) continue;
                        $single = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                        $upload = wp_handle_upload($single, ['test_form' => false]);
                        if (!isset($upload['error'])) {
                            $urls[] = $upload['url'];
                        }
                    }
                    send_json(['urls' => $urls]);
                }
                break;

            // Agent: request password change verification code
            case 'request-password-code':
                if ($method === 'POST') {
                    $old_password = $body['old_password'] ?? '';
                    if (empty($old_password)) send_json(['error' => 'Contraseña actual requerida'], 400);
                    // Verify old password
                    if (!wp_check_password($old_password, $current_agent->password_hash)) {
                        send_json(['error' => 'Contraseña actual incorrecta'], 403);
                    }
                    // Generate 6-digit code
                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes
                    $wpdb->update($wpdb->prefix . 'omnichannel_agents', [
                        'password_reset_code' => wp_hash_password($code),
                        'password_reset_expires' => $expires,
                    ], ['id' => $agent_id]);
                    // Send code via email
                    $subject = 'Código de verificación — OmniCliente';
                    $message = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;">'
                        . '<h2 style="color:#4F46E5;">Código de verificación</h2>'
                        . '<p>Hola <strong>' . esc_html($current_agent->name) . '</strong>,</p>'
                        . '<p>Tu código de verificación para cambiar la contraseña es:</p>'
                        . '<div style="text-align:center;margin:20px 0;">'
                        . '<span style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#1E40AF;background:#EEF2FF;padding:12px 24px;border-radius:8px;">' . $code . '</span>'
                        . '</div>'
                        . '<p style="color:#6B7280;font-size:13px;">Este código expira en <strong>5 minutos</strong>. Si no solicitaste este cambio, ignora este mensaje.</p>'
                        . '</div>';
                    $headers = OmnichannelController::email_headers();
                    wp_mail($current_agent->email, $subject, $message, $headers);
                    send_json(['success' => true, 'message' => 'Código enviado a tu correo']);
                }
                break;

            // Agent: change password with verification code
            case 'change-password':
                if ($method === 'POST') {
                    $code = $body['code'] ?? '';
                    $new_password = $body['new_password'] ?? '';
                    if (empty($code) || empty($new_password)) {
                        send_json(['error' => 'Código y nueva contraseña requeridos'], 400);
                    }
                    // Prohibited characters
                    if (preg_match('/[<>"\';\\\\\`{}|]/', $new_password)) {
                        send_json(['error' => 'La contraseña contiene caracteres no permitidos: < > " \' ; \\ ` { } |'], 400);
                    }
                    if (strlen($new_password) < 8) {
                        send_json(['error' => 'La contraseña debe tener al menos 8 caracteres'], 400);
                    }
                    if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
                        send_json(['error' => 'La contraseña debe incluir mayúscula, número y carácter especial'], 400);
                    }
                    // Verify code and expiry
                    $agent_fresh = $wpdb->get_row($wpdb->prepare(
                        "SELECT password_reset_code, password_reset_expires, password_hash FROM {$wpdb->prefix}omnichannel_agents WHERE id = %d", $agent_id
                    ));
                    if (empty($agent_fresh->password_reset_code) || empty($agent_fresh->password_reset_expires)) {
                        send_json(['error' => 'No hay código de verificación pendiente'], 400);
                    }
                    if (strtotime($agent_fresh->password_reset_expires) < time()) {
                        send_json(['error' => 'El código ha expirado. Solicita uno nuevo'], 400);
                    }
                    if (!wp_check_password($code, $agent_fresh->password_reset_code)) {
                        send_json(['error' => 'Código de verificación incorrecto'], 400);
                    }
                    // Ensure new password != old
                    if (wp_check_password($new_password, $agent_fresh->password_hash)) {
                        send_json(['error' => 'La nueva contraseña no puede ser igual a la actual'], 400);
                    }
                    // Update password and clear code
                    $wpdb->update($wpdb->prefix . 'omnichannel_agents', [
                        'password_hash' => wp_hash_password($new_password),
                        'password_reset_code' => null,
                        'password_reset_expires' => null,
                    ], ['id' => $agent_id]);
                    $controller->audit_log('update', 'agent', $agent_id, "Contraseña cambiada por el propio agente", null, null, $agent_client_id);
                    send_json(['success' => true, 'message' => 'Contraseña actualizada exitosamente']);
                }
                break;

            // Agent: Support Tickets
            case 'tickets':
                // GET /agent/tickets — list own tickets
                if ($method === 'GET' && !isset($segments[2])) {
                    $page = absint($_GET['page'] ?? 1);
                    $per_page = min(absint($_GET['per_page'] ?? 15), 100);
                    send_json($controller->get_tickets([
                        'agent_id' => $agent_id,
                        'status'   => sanitize_text_field($_GET['status'] ?? ''),
                        'category' => sanitize_text_field($_GET['category'] ?? ''),
                        'search'   => sanitize_text_field($_GET['search'] ?? ''),
                    ], $page, $per_page));
                }
                // POST /agent/tickets — create ticket
                if ($method === 'POST' && !isset($segments[2])) {
                    $body['agent_email'] = $current_agent->email;
                    $body['agent_name']  = $current_agent->name;
                    $result = $controller->create_ticket($body, $agent_id, $agent_client_id);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                // GET /agent/tickets/{id} — get single ticket with messages
                if ($method === 'GET' && isset($segments[2]) && !isset($segments[3])) {
                    $ticket = $controller->get_ticket(absint($segments[2]));
                    if (!$ticket || (int)$ticket->agent_id !== $agent_id) {
                        send_json(['error' => 'Ticket no encontrado'], 404);
                    }
                    send_json($ticket);
                }
                // POST /agent/tickets/{id}/messages — add message to own ticket
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'messages') {
                    $ticket = $controller->get_ticket(absint($segments[2]));
                    if (!$ticket || (int)$ticket->agent_id !== $agent_id) {
                        send_json(['error' => 'Ticket no encontrado'], 404);
                    }
                    $body['sender_type']  = 'agent';
                    $body['sender_name']  = $current_agent->name;
                    $body['sender_email'] = $current_agent->email;
                    $result = $controller->add_ticket_message(absint($segments[2]), $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                break;

            default:
                send_json(['error' => 'Ruta de agente no encontrada'], 404);
        }
    }

    // ---- CLIENT ROUTES (requiere API key) ----
    $client = authenticate_client($controller);
    $client_id = $client->id;

    // Set actor for audit logging (wp_get_current_user is empty in API-key sessions)
    $controller->set_actor($client->email ?? '', $client->company_name ?? '', 'client');

    // Attach period warning to every client response header
    $period_warning = $client->period_warning ?? null;

    $resource = $segments[0] ?? '';

    switch ($resource) {
        // --- PERIOD STATUS ---
        case 'period-status':
            if ($method === 'GET') {
                $pw = $controller->check_client_period($client);
                $pw['period_start'] = $client->period_start;
                $pw['period_end'] = $client->period_end;
                $pw['is_free'] = (bool) $client->is_free;
                $pw['max_channels'] = (int) ($client->max_channels ?? 1);
                $pw['max_agents'] = (int) ($client->max_agents ?? 3);
                $pw['plan_type'] = $client->plan_type ?? 'basic';
                send_json($pw);
            }
            break;

        // --- CONVERSACIONES ---
        case 'conversations':
            if ($method === 'GET' && !isset($segments[1])) {
                $page = absint($_GET['page'] ?? 1);
                $per_page = min(absint($_GET['per_page'] ?? 30), 100);
                send_json($controller->get_conversations($client_id, [
                    'status'            => sanitize_text_field($_GET['status'] ?? ''),
                    'channel_type'      => sanitize_text_field($_GET['channel_type'] ?? ''),
                    'assigned_agent_id' => sanitize_text_field($_GET['assigned_agent_id'] ?? ''),
                    'search'            => sanitize_text_field($_GET['search'] ?? ''),
                    'priority'          => sanitize_text_field($_GET['priority'] ?? ''),
                ], $page, $per_page));
            }
            
            if ($method === 'GET' && isset($segments[1]) && ($segments[2] ?? '') === 'messages') {
                $conv_id = absint($segments[1]);
                $page = absint($_GET['page'] ?? 1);
                send_json($controller->get_messages($conv_id, $page));
            }
            
            if ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'messages') {
                $conv_id = absint($segments[1]);
                $result = $controller->send_message($conv_id, $body);
                send_json($result, isset($result['error']) ? 400 : 201);
            }

            if ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'takeover') {
                $conv_id = absint($segments[1]);
                $result = $controller->takeover_conversation($conv_id, absint($body['agent_id'] ?? 0), sanitize_text_field($body['reason'] ?? ''));
                send_json($result, isset($result['error']) ? 400 : 200);
            }

            if ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'release') {
                $conv_id = absint($segments[1]);
                $result = $controller->release_conversation($conv_id, absint($body['agent_id'] ?? 0));
                send_json($result, isset($result['error']) ? 400 : 200);
            }

            if ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'transfer') {
                $conv_id = absint($segments[1]);
                $result = $controller->transfer_conversation(
                    $conv_id, 
                    absint($body['from_agent_id'] ?? 0), 
                    absint($body['to_agent_id'] ?? 0), 
                    sanitize_text_field($body['notes'] ?? '')
                );
                send_json($result, isset($result['error']) ? 400 : 200);
            }
            break;

        // --- TIPOS DE CANAL (lectura) ---
        case 'channel-types':
            if ($method === 'GET') {
                send_json($controller->get_channel_types(false));
            }
            break;

        // --- CANALES ---
        case 'channels':
            if ($method === 'GET') {
                send_json($controller->get_channels($client_id));
            }
            if ($method === 'POST') {
                $result = $controller->create_channel($client_id, $body);
                send_json($result, isset($result['error']) ? 400 : 201);
            }
            if ($method === 'PUT' && isset($segments[1])) {
                $result = $controller->update_channel(absint($segments[1]), $body);
                send_json(['success' => $result]);
            }
            if ($method === 'DELETE' && isset($segments[1])) {
                $confirm_key = $body['confirm_api_key'] ?? '';
                if (empty($confirm_key)) {
                    send_json(['error' => 'Debe confirmar con su API Key para eliminar'], 400);
                }
                if (!$controller->validate_client_api_key_for_action($client_id, $confirm_key)) {
                    send_json(['error' => 'API Key de confirmación inválida'], 403);
                }
                $result = $controller->delete_channel(absint($segments[1]));
                send_json(['success' => $result]);
            }
            break;

        // --- BOT CONFIGS ---
        case 'bots':
            if ($method === 'GET' && !isset($segments[1])) {
                send_json($controller->get_bot_configs($client_id));
            }
            if ($method === 'GET' && isset($segments[1])) {
                $config = $controller->get_bot_config(absint($segments[1]));
                send_json($config ?: ['error' => 'No encontrado']);
            }
            if ($method === 'PUT' && isset($segments[1])) {
                $result = $controller->update_bot_config(absint($segments[1]), $body);
                send_json(['success' => $result]);
            }
            break;

        // --- AGENTES ---
        case 'agents':
            if ($method === 'GET') {
                $page = absint($_GET['page'] ?? 0);
                $per_page = min(absint($_GET['per_page'] ?? 0), 100);
                if ($page > 0 && $per_page > 0) {
                    send_json($controller->get_agents($client_id, [
                        'search'  => sanitize_text_field($_GET['search'] ?? ''),
                        'orderby' => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                        'order'   => sanitize_text_field($_GET['order'] ?? 'DESC'),
                    ], $page, $per_page));
                } else {
                    send_json($controller->get_agents($client_id));
                }
            }
            if ($method === 'POST') {
                $result = $controller->create_agent($client_id, $body);
                send_json($result, isset($result['error']) ? 400 : 201);
            }
            if ($method === 'PUT' && isset($segments[1])) {
                $target_id = absint($segments[1]);
                $results = [];
                if (!empty($body['password'])) {
                    $results[] = $controller->set_agent_password($target_id, $body['password']);
                }
                $results[] = $controller->update_agent($target_id, $body);
                $errors = array_filter($results, fn($r) => isset($r['error']));
                if ($errors) send_json(reset($errors), 400);
                send_json(['success' => true]);
            }
            if ($method === 'DELETE' && isset($segments[1])) {
                // Destructive: requires API key confirmation
                $confirm_key = $body['confirm_api_key'] ?? '';
                if (empty($confirm_key)) {
                    send_json(['error' => 'Debe confirmar con su API Key para eliminar'], 400);
                }
                if (!$controller->validate_client_api_key_for_action($client_id, $confirm_key)) {
                    send_json(['error' => 'API Key de confirmación inválida'], 403);
                }
                $result = $controller->delete_agent(absint($segments[1]), $client_id);
                send_json($result, isset($result['error']) ? 400 : 200);
            }
            break;

        // --- AUDITORÍA (del cliente) ---
        case 'audit':
            if ($method === 'GET') {
                $page = absint($_GET['page'] ?? 1);
                $per_page = min(absint($_GET['per_page'] ?? 15), 200);
                send_json($controller->get_audit_logs([
                    'client_id' => $client_id,
                    'search'    => sanitize_text_field($_GET['search'] ?? ''),
                    'orderby'   => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                    'order'     => sanitize_text_field($_GET['order'] ?? 'DESC'),
                ], $page, $per_page));
            }
            break;

        default:
            send_json(['error' => 'Ruta no encontrada', 'available_routes' => [
                'GET /conversations', 'GET /conversations/{id}/messages', 'POST /conversations/{id}/messages',
                'POST /conversations/{id}/takeover', 'POST /conversations/{id}/release', 'POST /conversations/{id}/transfer',
                'GET /channels', 'POST /channels', 'PUT /channels/{id}',
                'GET /bots', 'PUT /bots/{id}',
                'GET /agents', 'POST /agents',
                'GET /audit',
            ]], 404);
    }
    
} catch (Exception $e) {
    send_json(['error' => 'Error interno del servidor'], 500);
}
