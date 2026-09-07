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
require_once __DIR__ . '/omnichannel-atfinance-controller.php';
require_once __DIR__ . '/at-rate-limit.php';
require_once __DIR__ . '/at-mime-check.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS centralizado (whitelist por ambiente)
require_once __DIR__ . '/at-cors.php';
at_cors_apply([
    'methods' => 'GET, POST, PUT, DELETE, OPTIONS',
    'headers' => 'Content-Type, Authorization, X-API-Key, X-Admin-Token, X-Agent-Token',
]);

$controller = new OmnichannelController();
$atfinance = new OmniATFinanceController();
$atfinance->maybe_create_tables(); // no-op salvo primera vez / upgrade (option-gated)

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['route']) ? sanitize_text_field($_GET['route']) : '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];

/* ─── E3: helper compartido para upload de imágenes (evita 3 bloques idénticos) ─── */
if ( ! function_exists( 'at_omni_process_image_uploads' ) ) {
    /**
     * Valida y sube un conjunto de imágenes vía `$_FILES[$key]`.
     * Usa `at_verify_upload_mime()` (magic bytes) + `wp_handle_upload()`.
     *
     * @param string $files_key   Clave en $_FILES (ej. 'images', 'avatar').
     * @param array  $allowed     MIMEs permitidos.
     * @param int    $max_size    Tamaño máximo en bytes por archivo.
     * @param int    $max_count   Máximo de archivos a procesar.
     * @return array              Array de URLs subidas.
     */
    function at_omni_process_image_uploads( string $files_key, array $allowed, int $max_size, int $max_count = 5 ): array {
        if ( empty( $_FILES[ $files_key ] ) ) {
            return [];
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $files = $_FILES[ $files_key ];
        // Normalizar al formato multi-archivo
        if ( ! is_array( $files['name'] ) ) {
            $files = [
                'name'     => [ $files['name'] ],
                'type'     => [ $files['type'] ],
                'tmp_name' => [ $files['tmp_name'] ],
                'error'    => [ $files['error'] ],
                'size'     => [ $files['size'] ],
            ];
        }

        $count = min( count( $files['name'] ), $max_count );
        $urls  = [];
        for ( $i = 0; $i < $count; $i++ ) {
            if ( $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
                continue;
            }
            $real_mime = at_verify_upload_mime( $files['tmp_name'][ $i ], $allowed );
            if ( ! $real_mime ) {
                continue;
            }
            if ( $files['size'][ $i ] > $max_size ) {
                continue;
            }
            $single = [
                'name'     => $files['name'][ $i ],
                'type'     => $real_mime,  // MIME verificado, nunca el declarado por el cliente
                'tmp_name' => $files['tmp_name'][ $i ],
                'error'    => $files['error'][ $i ],
                'size'     => $files['size'][ $i ],
            ];
            $upload = wp_handle_upload( $single, [ 'test_form' => false ] );
            if ( ! isset( $upload['error'] ) ) {
                $urls[] = $upload['url'];
            }
        }
        return $urls;
    }
}

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

/* ==== Contrato de suscripción (Fase 5 §8-bis): aceptación click-wrap + cancelación 1 clic ==== */

define('AT_SUBSCRIPTION_CONTRACT_VERSION', 'v1-2026-07');

function at_omni_subscription_table() {
    if (get_option('omni_subscription_events_db') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE {$wpdb->prefix}omnichannel_subscription_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        event_type VARCHAR(20) NOT NULL DEFAULT 'accept',
        contract_version VARCHAR(30) NOT NULL DEFAULT '',
        ip VARCHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY client_event (client_id, event_type)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('omni_subscription_events_db', '1');
}

function at_omni_subscription_status($client) {
    global $wpdb;
    $accept = $wpdb->get_row($wpdb->prepare(
        "SELECT contract_version, ip, created_at FROM {$wpdb->prefix}omnichannel_subscription_events
         WHERE client_id = %d AND event_type = 'accept' AND contract_version = %s
         ORDER BY id DESC LIMIT 1",
        $client->id, AT_SUBSCRIPTION_CONTRACT_VERSION
    ));
    $cancel = $wpdb->get_row($wpdb->prepare(
        "SELECT created_at FROM {$wpdb->prefix}omnichannel_subscription_events
         WHERE client_id = %d AND event_type = 'cancel' ORDER BY id DESC LIMIT 1",
        $client->id
    ));
    return [
        'contract_version' => AT_SUBSCRIPTION_CONTRACT_VERSION,
        'accepted'         => (bool) $accept,
        'accepted_at'      => $accept->created_at ?? null,
        'cancel_requested' => (bool) $cancel,
        'cancel_requested_at' => $cancel->created_at ?? null,
        'period_end'       => $client->period_end ?? null,
        'contract_html'    => $accept ? '' : at_omni_subscription_contract_html($client),
    ];
}

/** Contrato de suscripción generado por plan (plantilla Fase 5 §8-bis + correcciones C1-C11). */
function at_omni_subscription_contract_html($client) {
    $plans = [
        'basic'        => ['nombre' => 'Básico',      'precio' => 'USD 99/mes',  'promo' => true],
        'professional' => ['nombre' => 'Profesional', 'precio' => 'USD 199/mes', 'promo' => true],
        'enterprise'   => ['nombre' => 'Enterprise',  'precio' => 'USD 399/mes (o según cotización)', 'promo' => false],
    ];
    $p = $plans[$client->plan_type ?? 'basic'] ?? $plans['basic'];
    $empresa = esc_html($client->company_name ?? 'el Cliente');
    $promo = $p['promo']
        ? '<h3>2. Período promocional "1 mes gratis"</h3>
<p>El primer mes no se factura; la facturación inicia al término del Período Promocional. La promoción aplica una sola vez por cliente/RUT y durante ella rigen íntegramente estos términos. Terminado el período, la suscripción se renueva automáticamente de forma mensual al precio de lista vigente, salvo que el Cliente cancele mediante el botón <strong>"Cancelar Suscripción"</strong> de este portal. AutomatizaTech avisará por correo el término del período promocional con al menos 7 días de anticipación al primer cobro. El Cliente que califique como micro o pequeña empresa (Ley 20.416) conserva su derecho a retracto conforme a la Ley 19.496.</p>'
        : '<h3>2. Facturación desde el inicio</h3><p>El plan Enterprise no incluye período promocional; la facturación rige desde la fecha de inicio del servicio.</p>';

    return '<h2>Contrato de Suscripción — Portal OmniCliente</h2>
<p><strong>AUTOMATIZATECH SpA</strong>, RUT 78.363.717-0, Providencia, Chile ("AT") y <strong>' . $empresa . '</strong> (el "Cliente") acuerdan la suscripción al Portal OmniCliente bajo los <a href="https://automatizatech.cl/terminos/" target="_blank" rel="noopener">Términos de Servicio</a> y la <a href="https://automatizatech.cl/privacidad/" target="_blank" rel="noopener">Política de Privacidad</a>, que forman parte integrante de este contrato, con las siguientes condiciones particulares:</p>
<h3>1. Plan contratado</h3>
<p>Plan <strong>' . esc_html($p['nombre']) . '</strong> — ' . esc_html($p['precio']) . ', facturación mensual anticipada. Tarifas en USD convertidas a CLP según el Dólar Observado del Banco Central de Chile al día de emisión de cada factura. Los límites del plan (conversaciones, canales, agentes) son los informados al contratar y visibles en el portal.</p>
' . $promo . '
<h3>3. Cancelación</h3>
<p>El Cliente puede cancelar en cualquier momento desde el botón "Cancelar Suscripción" del portal, con efecto al término del período facturado en curso. La suspensión por no pago procede tras 7 días de gracia con aviso.</p>
<h3>4. Datos personales</h3>
<p>Cuando AT procese datos de los clientes finales del Cliente a través de bots o del portal, actuará como encargado de tratamiento conforme al DPA descrito en los Términos de Servicio y la Ley 21.719. Al término, el Cliente puede exportar sus datos dentro de los 30 días siguientes.</p>
<h3>5. Naturaleza del servicio de IA</h3>
<p>Los asistentes con IA constituyen obligaciones de medios y no de resultado; el Cliente es responsable de mantener actualizada la información de su negocio.</p>
<h3>6. Aceptación electrónica</h3>
<p>La aceptación mediante el checkbox de este portal, con registro de IP y fecha/hora, constituye consentimiento válido y evidencia de celebración de este contrato (Ley 19.799 sobre documentos electrónicos).</p>
<p style="opacity:.75;font-size:12px">Versión del contrato: ' . AT_SUBSCRIPTION_CONTRACT_VERSION . '</p>';
}

function authenticate_admin() {
    // 1. WordPress session auth (wp-admin)
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return;
    }
    // 2. Token auth: header (legacy) or HttpOnly cookie (preferred)
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($_COOKIE['at_admin_token'] ?? '');
    if (!empty($token)) {
        $valid = validate_admin_token($token);
        if ($valid) {
            wp_set_current_user($valid);
            return;
        }
    }
    // C4: log admin auth failures for security monitoring
    if ( function_exists( 'at_secmon_log_event' ) ) {
        at_secmon_log_event( 'api_auth_failed', [ 'endpoint' => $_SERVER['REQUEST_URI'] ?? '' ] );
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
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
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
        // Rate limit: 60 checks/min per IP (N8N polls at most every few seconds)
        if ( ! at_rate_limit_check( 'omni_health', 60, 60 ) ) {
            at_rate_limit_reject( 60, 'omni_health' );
        }
        send_json(['status' => 'ok', 'timestamp' => current_time('c')]);
    }

    // ---- PROMPT CONFIG para N8N (autenticado via HMAC) ----
    if (isset($segments[0]) && $segments[0] === 'prompt-config' && isset($segments[1]) && $method === 'GET') {
        $pc_channel_id = absint($segments[1]);
        $pc_token = sanitize_text_field($_GET['token'] ?? '');
        $pc_secret = defined('OMNI_ADMIN_SECRET') ? OMNI_ADMIN_SECRET : 'omni_default_secret';
        $pc_expected = hash_hmac('sha256', 'prompt-config:' . $pc_channel_id, $pc_secret);
        if (empty($pc_token) || !hash_equals($pc_expected, $pc_token)) {
            send_json(['error' => 'Token inválido'], 403);
        }
        $pc = $controller->get_active_prompt_config_for_channel($pc_channel_id);
        if (!$pc) send_json(['error' => 'No hay configuración activa para este canal'], 404);
        $decoded = json_decode($pc->prompt_data, true);

        // Include escalation agents with schedule data
        $escalation_agents = [];
        if (!empty($decoded['agentes_escalacion']) && is_array($decoded['agentes_escalacion'])) {
            $agent_ids = array_filter(array_map(function($a) { return absint($a['agent_id'] ?? 0); }, $decoded['agentes_escalacion']));
            if (!empty($agent_ids)) {
                $placeholders = implode(',', array_fill(0, count($agent_ids), '%d'));
                $agents_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, department, status, schedule_start, schedule_end, available_days FROM {$wpdb->prefix}omnichannel_agents WHERE id IN ($placeholders)",
                    ...$agent_ids
                ));
                $agents_map = [];
                foreach ($agents_rows as $ar) { $agents_map[$ar->id] = $ar; }
                foreach ($decoded['agentes_escalacion'] as $ea) {
                    $aid = absint($ea['agent_id'] ?? 0);
                    $ag = $agents_map[$aid] ?? null;
                    $escalation_agents[] = [
                        'agent_id'       => $aid,
                        'area'           => $ea['area'] ?? '',
                        'es_defecto'     => !empty($ea['es_defecto']),
                        'name'           => $ag ? $ag->name : '',
                        'department'     => $ag ? ($ag->department ?? '') : '',
                        'status'         => $ag ? $ag->status : 'inactive',
                        'schedule_start' => $ag ? ($ag->schedule_start ?? '') : '',
                        'schedule_end'   => $ag ? ($ag->schedule_end ?? '') : '',
                        'available_days' => $ag ? ($ag->available_days ?? '1,2,3,4,5') : '',
                    ];
                }
            }
        }

        send_json([
            'id'           => (int) $pc->id,
            'channel_id'   => (int) $pc->channel_id,
            'config_name'  => $pc->config_name,
            'prompt_data'  => is_array($decoded) ? $decoded : [],
            'escalation_agents' => $escalation_agents,
            'version'      => (int) $pc->version,
            'updated_at'   => $pc->updated_at,
        ]);
    }

    // ---- CONSUMO DE TOKENS IA para N8N (autenticado via HMAC, mismo esquema que prompt-config) ----
    // Los workflows de bots (channel_id fijo por clon) reportan tokens sin necesitar la
    // API key del cliente; el channel_id se resuelve a client_id server-side (no confiar
    // en un client_id que mande n8n).
    if (isset($segments[0]) && $segments[0] === 'bot-usage-ingest' && $method === 'POST') {
        $bi_channel_id = absint($body['channel_id'] ?? 0);
        $bi_token = sanitize_text_field($body['token'] ?? '');
        $bi_secret = defined('OMNI_ADMIN_SECRET') ? OMNI_ADMIN_SECRET : 'omni_default_secret';
        $bi_expected = hash_hmac('sha256', 'bot-usage-ingest:' . $bi_channel_id, $bi_secret);
        if (empty($bi_token) || !hash_equals($bi_expected, $bi_token)) {
            send_json(['error' => 'Token inválido'], 403);
        }
        $bi_owner_client_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$wpdb->prefix}omnichannel_channels WHERE id = %d", $bi_channel_id
        ));
        if (!$bi_owner_client_id) {
            send_json(['error' => 'Canal no encontrado'], 404);
        }
        $result = $atfinance->log_usage([
            'client_id'         => $bi_owner_client_id,
            'channel_id'        => $bi_channel_id,
            'bot_name'          => $body['bot_name'] ?? '',
            'source'            => 'bot',
            'model'             => $body['model'] ?? '',
            'prompt_tokens'     => $body['prompt_tokens'] ?? 0,
            'completion_tokens' => $body['completion_tokens'] ?? 0,
            'total_tokens'      => $body['total_tokens'] ?? 0,
            'cost_usd'          => $body['cost_usd'] ?? null,
        ]);
        send_json($result, isset($result['error']) ? 400 : 201);
    }

    // ---- REEL DIARIO AUTOMÁTICO: sube/borra el video final para poder publicarlo ----
    // Secret AISLADO (REEL_DIARIO_SECRET, header X-Reel-Diario-Secret) — a propósito
    // NO usa OMNI_ADMIN_SECRET ni ninguna tabla de clientes: este pipeline es 100%
    // ajeno al portal (ver project_at_reel_diario_automatico). Solo mueve un archivo
    // de video a una carpeta dedicada y devuelve/borra su URL pública.
    if (isset($segments[0]) && $segments[0] === 'reel-diario') {
        $rd_provided = $_SERVER['HTTP_X_REEL_DIARIO_SECRET'] ?? '';
        if (!defined('REEL_DIARIO_SECRET') || REEL_DIARIO_SECRET === '' || !hash_equals((string) REEL_DIARIO_SECRET, (string) $rd_provided)) {
            send_json(['error' => 'Secret inválido'], 403);
        }

        // GET/POST reel-diario/token-check — pre-flight barato del token de Instagram.
        // Se llama al INICIO del pipeline para abortar antes de descargar clips, correr el
        // QA de vision y renderizar. Sin esto, un token vencido se descubria recien en
        // media-create, despues de ~90s de render y del gasto de IA (incidente 2026-08-14).
        // Devuelve 502 si el token no sirve, para que el nodo de n8n corte la ejecucion solo.
        if (($segments[1] ?? '') === 'token-check' && ($method === 'GET' || $method === 'POST')) {
            $rd_channel_id = absint($body['channel_id'] ?? ($_GET['channel_id'] ?? (defined('REEL_DIARIO_CHANNEL_ID') ? REEL_DIARIO_CHANNEL_ID : 0)));
            if (!$rd_channel_id) {
                send_json(['error' => 'Falta channel_id (o define REEL_DIARIO_CHANNEL_ID en wp-config-secrets.php)'], 400);
            }
            $result = $controller->check_instagram_token($rd_channel_id);
            send_json($result, empty($result['ok']) ? 502 : 200);
        }

        // POST reel-diario/upload — multipart, campo "video" (mp4). Devuelve URL pública.
        if (($segments[1] ?? '') === 'upload' && $method === 'POST') {
            if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                send_json(['error' => 'Falta el archivo "video" o hubo un error de subida'], 400);
            }
            $rd_mime = at_verify_upload_mime($_FILES['video']['tmp_name'], ['video/mp4']);
            if (!$rd_mime) {
                send_json(['error' => 'El archivo no es un mp4 válido'], 400);
            }
            $rd_max_bytes = 100 * 1024 * 1024; // 100MB, generoso para un reel de 20s
            if ($_FILES['video']['size'] > $rd_max_bytes) {
                send_json(['error' => 'Video demasiado grande'], 400);
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $rd_subdir_filter = function ($dirs) {
                $dirs['subdir'] = '/reel-diario';
                $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
                $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
                return $dirs;
            };
            add_filter('upload_dir', $rd_subdir_filter);
            $rd_file = [
                'name'     => 'reel-diario-' . gmdate('Ymd-His') . '.mp4',
                'type'     => $rd_mime,
                'tmp_name' => $_FILES['video']['tmp_name'],
                'error'    => $_FILES['video']['error'],
                'size'     => $_FILES['video']['size'],
            ];
            $rd_upload = wp_handle_upload($rd_file, ['test_form' => false]);
            remove_filter('upload_dir', $rd_subdir_filter);
            if (isset($rd_upload['error'])) {
                send_json(['error' => $rd_upload['error']], 500);
            }
            send_json(['url' => $rd_upload['url'], 'file' => basename($rd_upload['file'])], 201);
        }

        // POST reel-diario/media-create — body {video_url, caption?, media_type?, channel_id?}.
        // Crea el contenedor de Instagram y devuelve creation_id sin hacer polling bloqueante.
        if (($segments[1] ?? '') === 'media-create' && $method === 'POST') {
            $rd_video_url = esc_url_raw($body['video_url'] ?? '');
            if ($rd_video_url === '') {
                send_json(['error' => 'Falta video_url'], 400);
            }
            $rd_media_type = ($body['media_type'] ?? 'REELS') === 'STORIES' ? 'STORIES' : 'REELS';
            $rd_caption = sanitize_textarea_field($body['caption'] ?? '');
            $rd_channel_id = absint($body['channel_id'] ?? (defined('REEL_DIARIO_CHANNEL_ID') ? REEL_DIARIO_CHANNEL_ID : 0));
            if (!$rd_channel_id) {
                send_json(['error' => 'Falta channel_id (o define REEL_DIARIO_CHANNEL_ID en wp-config-secrets.php)'], 400);
            }
            $result = $controller->create_instagram_media_container($rd_channel_id, $rd_video_url, $rd_caption, $rd_media_type);
            send_json($result, isset($result['error']) ? 502 : 201);
        }

        // GET/POST reel-diario/media-status — query/body {creation_id, channel_id?}.
        // n8n hace el polling y evita que nginx/Hostinger corte un request largo.
        if (($segments[1] ?? '') === 'media-status' && ($method === 'GET' || $method === 'POST')) {
            $rd_creation_id = sanitize_text_field($body['creation_id'] ?? ($_GET['creation_id'] ?? ''));
            if ($rd_creation_id === '') {
                send_json(['error' => 'Falta creation_id'], 400);
            }
            $rd_channel_id = absint($body['channel_id'] ?? ($_GET['channel_id'] ?? (defined('REEL_DIARIO_CHANNEL_ID') ? REEL_DIARIO_CHANNEL_ID : 0)));
            if (!$rd_channel_id) {
                send_json(['error' => 'Falta channel_id (o define REEL_DIARIO_CHANNEL_ID en wp-config-secrets.php)'], 400);
            }
            $result = $controller->get_instagram_media_status($rd_channel_id, $rd_creation_id);
            send_json($result, isset($result['error']) ? 502 : 200);
        }

        // POST reel-diario/media-publish — body {creation_id, channel_id?}.
        // Publica un contenedor cuyo status_code ya es FINISHED.
        if (($segments[1] ?? '') === 'media-publish' && $method === 'POST') {
            $rd_creation_id = sanitize_text_field($body['creation_id'] ?? '');
            if ($rd_creation_id === '') {
                send_json(['error' => 'Falta creation_id'], 400);
            }
            $rd_channel_id = absint($body['channel_id'] ?? (defined('REEL_DIARIO_CHANNEL_ID') ? REEL_DIARIO_CHANNEL_ID : 0));
            if (!$rd_channel_id) {
                send_json(['error' => 'Falta channel_id (o define REEL_DIARIO_CHANNEL_ID en wp-config-secrets.php)'], 400);
            }
            $result = $controller->publish_instagram_media_container($rd_channel_id, $rd_creation_id);
            send_json($result, isset($result['error']) ? 502 : 201);
        }

        // POST reel-diario/media-delete — body {media_id, channel_id?}.
        // Elimina una publicación ya publicada en Instagram cuando el post salió mal.
        if (($segments[1] ?? '') === 'media-delete' && $method === 'POST') {
            $rd_media_id = sanitize_text_field($body['media_id'] ?? '');
            if ($rd_media_id === '') {
                send_json(['error' => 'Falta media_id'], 400);
            }
            $rd_channel_id = absint($body['channel_id'] ?? (defined('REEL_DIARIO_CHANNEL_ID') ? REEL_DIARIO_CHANNEL_ID : 0));
            if (!$rd_channel_id) {
                send_json(['error' => 'Falta channel_id (o define REEL_DIARIO_CHANNEL_ID en wp-config-secrets.php)'], 400);
            }
            $result = $controller->delete_instagram_media($rd_channel_id, $rd_media_id);
            send_json($result, isset($result['error']) ? 502 : 200);
        }

        // POST reel-diario/publish — body {video_url, caption?, media_type?, channel_id?}.
        // Compatibilidad: hace el flujo completo server-side. Para n8n usar media-create/status/publish
        // porque esta ruta puede bloquear varios minutos y chocar con timeouts de nginx/Hostinger.
        if (($segments[1] ?? '') === 'publish' && $method === 'POST') {
            set_time_limit(320); // el poll de media_publish puede tardar hasta 5 min
            $rd_video_url = esc_url_raw($body['video_url'] ?? '');
            if ($rd_video_url === '') {
                send_json(['error' => 'Falta video_url'], 400);
            }
            $rd_media_type = ($body['media_type'] ?? 'REELS') === 'STORIES' ? 'STORIES' : 'REELS';
            $rd_caption = sanitize_textarea_field($body['caption'] ?? '');
            $rd_channel_id = absint($body['channel_id'] ?? (defined('REEL_DIARIO_CHANNEL_ID') ? REEL_DIARIO_CHANNEL_ID : 0));
            if (!$rd_channel_id) {
                send_json(['error' => 'Falta channel_id (o define REEL_DIARIO_CHANNEL_ID en wp-config-secrets.php)'], 400);
            }
            $result = $controller->publish_instagram_media($rd_channel_id, $rd_video_url, $rd_caption, $rd_media_type);
            send_json($result, isset($result['error']) ? 502 : 201);
        }

        // POST reel-diario/delete — body {"file":"reel-diario-....mp4"}. Limpieza post-publish.
        if (($segments[1] ?? '') === 'delete' && $method === 'POST') {
            $rd_name = sanitize_file_name($body['file'] ?? '');
            if ($rd_name === '' || strpos($rd_name, '..') !== false) {
                send_json(['error' => 'Nombre de archivo inválido'], 400);
            }
            $rd_upload_dir = wp_upload_dir();
            $rd_path = $rd_upload_dir['basedir'] . '/reel-diario/' . $rd_name;
            if (file_exists($rd_path)) {
                @unlink($rd_path);
            }
            send_json(['deleted' => true]);
        }

        send_json(['error' => 'Ruta reel-diario no encontrada'], 404);
    }

    // ---- N8N CALLBACK (respuesta del bot) ----
    if (isset($segments[0]) && $segments[0] === 'webhook' &&
        isset($segments[1]) && $segments[1] === 'n8n-callback' && $method === 'POST') {
        $result = $controller->handle_n8n_callback($body);
        send_json($result, isset($result['error']) ? 400 : 200);
    }

    // ---- WEBHOOK (sin auth) ----
    if (isset($segments[0]) && $segments[0] === 'webhook' && $method === 'POST') {
        // channel_id from route segment (webhook/{id}) or query param (?channel_id={id})
        $channel_id = absint($segments[1] ?? 0);
        if (!$channel_id && isset($_GET['channel_id'])) {
            $channel_id = absint($_GET['channel_id']);
        }
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
        if (!defined('OMNICHANNEL_CRON_SECRET') || !OMNICHANNEL_CRON_SECRET) {
            send_json(['error' => 'Cron secret no configurado'], 500);
        }
        $cron_secret = OMNICHANNEL_CRON_SECRET;

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
        // E3: usar helper centralizado de upload
        $urls = at_omni_process_image_uploads(
            'images',
            ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            3 * 1024 * 1024,
            5
        );
        send_json(['urls' => $urls]);
    }

    // ---- ADMIN ROUTES (requiere WordPress admin) ----
    if (isset($segments[0]) && $segments[0] === 'admin') {
        // Ruta especial: verificar sesión admin (no requiere auth previa)
        if (($segments[1] ?? '') === 'session-check' && $method === 'GET') {
            // Check token: header (legacy) o cookie HttpOnly (preferido)
            $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($_COOKIE['at_admin_token'] ?? '');
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
            // Rate limit: 5 intentos/hora por IP → anti brute-force
            if ( ! at_rate_limit_check( 'omni_admin_login', 5, 3600 ) ) {
                at_rate_limit_reject( 3600, 'omni_admin_login' );
            }
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

            // Establecer cookie HttpOnly+Secure (evita acceso desde JavaScript/localStorage)
            $cookie_opts = [
                'expires'  => time() + 7 * 24 * 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            setcookie( 'at_admin_token', $token, $cookie_opts );

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
                    $result = $controller->send_agent_message($conv_id, $body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'takeover') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->takeover_conversation($conv_id, absint($body['agent_id'] ?? 0), sanitize_text_field($body['reason'] ?? ''));
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'release') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->release_conversation($conv_id, absint($body['agent_id'] ?? 0));
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'transfer') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->transfer_conversation(
                        $conv_id,
                        absint($body['from_agent_id'] ?? 0),
                        absint($body['to_agent_id'] ?? 0),
                        sanitize_text_field($body['notes'] ?? '')
                    );
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                // Admin: export any conversation history (no restrictions)
                if ($method === 'GET' && isset($segments[2]) && ($segments[3] ?? '') === 'export-history') {
                    $conv_id = absint($segments[2]);
                    $result = $controller->export_conversation_history($conv_id);
                    send_json($result, isset($result['error']) ? 404 : 200);
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
                if ($method === 'DELETE' && isset($segments[2])) {
                    $result = $controller->delete_channel(absint($segments[2]));
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

            // Admin: prompt configs (CRUD completo)
            case 'prompts':
                if ($method === 'GET' && !isset($segments[2])) {
                    $channel_filter = absint($_GET['channel_id'] ?? 0);
                    send_json($controller->get_prompt_configs($channel_filter));
                }
                if ($method === 'GET' && isset($segments[2])) {
                    $pc = $controller->get_prompt_config(absint($segments[2]));
                    send_json($pc ?: ['error' => 'No encontrado'], $pc ? 200 : 404);
                }
                if ($method === 'POST') {
                    $body['created_by'] = wp_get_current_user()->user_login ?? 'admin';
                    $result = $controller->create_prompt_config($body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    $body['updated_by'] = wp_get_current_user()->user_login ?? 'admin';
                    $result = $controller->update_prompt_config(absint($segments[2]), $body);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                if ($method === 'DELETE' && isset($segments[2])) {
                    $result = $controller->delete_prompt_config(absint($segments[2]));
                    send_json($result, isset($result['error']) ? 400 : 200);
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
                // PUT /admin/agents/{id} - update agent (password, skills, fields)
                if ($method === 'PUT' && isset($segments[2])) {
                    $agent_id = absint($segments[2]);
                    $results = [];
                    if (!empty($body['password'])) {
                        $results[] = $controller->set_agent_password($agent_id, $body['password']);
                    }
                    if (isset($body['skills'])) {
                        $results[] = $controller->update_agent_skills($agent_id, $body['skills'], $body['department'] ?? null);
                    }
                    // Also update standard fields (name, role, status, department, channel_id, etc.)
                    $results[] = $controller->update_agent($agent_id, $body);
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

            // Admin: Finanzas AT — gastos de infraestructura (mensual/anual)
            case 'finance':
                if ($method === 'GET' && !isset($segments[2])) {
                    send_json($atfinance->get_expenses(isset($_GET['active']) && $_GET['active'] === '1'));
                }
                if ($method === 'POST' && !isset($segments[2])) {
                    $result = $atfinance->create_expense($body);
                    send_json($result, isset($result['error']) ? 400 : 201);
                }
                if ($method === 'PUT' && isset($segments[2])) {
                    send_json($atfinance->update_expense(absint($segments[2]), $body));
                }
                if ($method === 'DELETE' && isset($segments[2])) {
                    send_json($atfinance->delete_expense(absint($segments[2])));
                }
                break;

            case 'finance-summary':
                if ($method === 'GET') {
                    send_json($atfinance->finance_summary());
                }
                break;

            // Admin: consumo de tokens IA (todos los clientes; filtro client_id opcional,
            // incluido '0' explícito para ver solo AT/plataforma/demos internos)
            case 'usage-stats':
                if ($method === 'GET') {
                    $has_filter = isset($_GET['client_id']) && $_GET['client_id'] !== '';
                    send_json($atfinance->usage_stats(
                        $has_filter ? absint($_GET['client_id']) : -1,
                        absint($_GET['days'] ?? 30)
                    ));
                }
                break;

            // Admin: AI Assistant Prompt management
            case 'ai-assistant-prompt':
                if ($method === 'GET') {
                    send_json([
                        'template' => $controller->get_ai_prompt_template(),
                        'default'  => $controller->get_default_ai_prompt_template(),
                        'placeholders' => ['{user_name}', '{user_role}', '{company_name}', '{client_id}', '{plan_type}'],
                    ]);
                }
                if ($method === 'PUT') {
                    $template = $body['template'] ?? '';
                    if (empty(trim($template))) {
                        send_json(['error' => 'El prompt no puede estar vacío'], 400);
                    }
                    $result = $controller->save_ai_prompt_template($template);
                    send_json($result, isset($result['error']) ? 400 : 200);
                }
                break;

            // Admin: AI Assistant Chat (global platform context)
            case 'ai-assistant':
                if ($method === 'POST') {
                    $user_message = trim($body['message'] ?? '');
                    if (empty($user_message)) {
                        send_json(['error' => 'Mensaje vacío'], 400);
                    }
                    $history = is_array($body['history'] ?? null) ? $body['history'] : [];
                    $current_user = wp_get_current_user();
                    $admin_name = $current_user->display_name ?: 'Admin';
                    $result = $controller->ai_admin_chat($admin_name, $user_message, $history);
                    $code = isset($result['error']) ? ($result['code'] ?? 400) : 200;
                    send_json($result, $code);
                }
                break;

            // Admin: AI Chat History (backend persistence for Omni Assistant)
            case 'ai-chat-history':
                $adm_key     = 'admin:' . get_current_user_id();
                $adm_chat_id = sanitize_text_field($segments[2] ?? '');
                if ($method === 'GET') {
                    $rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT id, messages, created_at, updated_at FROM {$wpdb->prefix}omnichannel_ai_chats WHERE agent_key = %s ORDER BY updated_at DESC LIMIT 30",
                            $adm_key
                        )
                    );
                    $chats = array_map(function($r) {
                        $r->messages  = json_decode($r->messages, true) ?: [];
                        $r->createdAt = (int) (strtotime($r->created_at) * 1000);
                        $r->updatedAt = (int) (strtotime($r->updated_at) * 1000);
                        return $r;
                    }, $rows ?: []);
                    send_json(['chats' => $chats]);
                }
                if ($method === 'POST') {
                    $chat_id = sanitize_text_field($body['id'] ?? '');
                    $messages = is_array($body['messages'] ?? null) ? $body['messages'] : [];
                    if (empty($chat_id)) send_json(['error' => 'id requerido'], 400);
                    $existing_key = $wpdb->get_var($wpdb->prepare(
                        "SELECT agent_key FROM {$wpdb->prefix}omnichannel_ai_chats WHERE id = %s", $chat_id
                    ));
                    if ($existing_key && $existing_key !== $adm_key) send_json(['error' => 'Forbidden'], 403);
                    $now = current_time('mysql');
                    if ($existing_key) {
                        $wpdb->update($wpdb->prefix . 'omnichannel_ai_chats', [
                            'messages'   => wp_json_encode($messages, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $now,
                        ], ['id' => $chat_id]);
                    } else {
                        $wpdb->insert($wpdb->prefix . 'omnichannel_ai_chats', [
                            'id'         => $chat_id,
                            'agent_key'  => $adm_key,
                            'messages'   => wp_json_encode($messages, JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                    send_json(['saved' => true]);
                }
                if ($method === 'DELETE' && !empty($adm_chat_id)) {
                    $existing_key = $wpdb->get_var($wpdb->prepare(
                        "SELECT agent_key FROM {$wpdb->prefix}omnichannel_ai_chats WHERE id = %s", $adm_chat_id
                    ));
                    if (!$existing_key) send_json(['error' => 'Chat no encontrado'], 404);
                    if ($existing_key !== $adm_key) send_json(['error' => 'Forbidden'], 403);
                    $wpdb->delete($wpdb->prefix . 'omnichannel_ai_chats', ['id' => $adm_chat_id]);
                    send_json(['deleted' => true]);
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
            // Rate limit: 5 intentos/hora por IP → anti brute-force
            if ( ! at_rate_limit_check( 'omni_agent_login', 5, 3600 ) ) {
                at_rate_limit_reject( 3600, 'omni_agent_login' );
            }
            $email = sanitize_email($body['email'] ?? '');
            $password = $body['password'] ?? '';
            if (empty($email) || empty($password)) {
                send_json(['error' => 'Email y contraseña requeridos'], 400);
            }
            $result = $controller->authenticate_agent($email, $password);
            // Si el login fue exitoso, establecer cookie HttpOnly para el token
            if ( empty($result['error']) && ! empty($result['token']) ) {
                $cookie_opts = [
                    'expires'  => time() + 7 * 24 * 3600,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ];
                setcookie( 'at_agent_token', $result['token'], $cookie_opts );
            }
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

        // Session check: validate existing token (header or cookie)
        if (($segments[1] ?? '') === 'session-check' && $method === 'GET') {
            $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_COOKIE['at_agent_token'] ?? '');
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

        // All other agent routes require valid token (header or cookie)
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_COOKIE['at_agent_token'] ?? '');
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
                    $result = $controller->send_agent_message($conv_id, $body);
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
                // Export full conversation history as plain text
                if ($method === 'GET' && isset($segments[2]) && ($segments[3] ?? '') === 'export-history') {
                    $conv_id = absint($segments[2]);
                    // Regular agents: only their own assigned conversations
                    if (!$is_supervisor) {
                        $assigned = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT assigned_agent_id FROM {$wpdb->prefix}omnichannel_conversations WHERE id = %d AND client_id = %d",
                            $conv_id, $agent_client_id
                        ));
                        if ($assigned !== $agent_id) {
                            send_json(['error' => 'Acceso denegado: solo puedes exportar el historial de conversaciones que tienes asignadas. Esta conversación pertenece a otro agente.'], 403);
                        }
                    } else {
                        // Supervisor: only conversations of their company
                        $conv_client = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT client_id FROM {$wpdb->prefix}omnichannel_conversations WHERE id = %d",
                            $conv_id
                        ));
                        if ($conv_client !== $agent_client_id) {
                            send_json(['error' => 'Acceso denegado: no tienes permisos para acceder a conversaciones de otra empresa.'], 403);
                        }
                    }
                    $result = $controller->export_conversation_history($conv_id);
                    send_json($result, isset($result['error']) ? 404 : 200);
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

            // Agent: prompt configs (supervisor = read-only, admin of client cannot edit either — only AT admin)
            case 'prompts':
                if ($method === 'GET' && !isset($segments[2]) && $is_supervisor) {
                    $channel_filter = absint($_GET['channel_id'] ?? 0);
                    send_json($controller->get_prompt_configs($channel_filter));
                }
                if ($method === 'GET' && isset($segments[2]) && $is_supervisor) {
                    $pc = $controller->get_prompt_config(absint($segments[2]));
                    send_json($pc ?: ['error' => 'No encontrado'], $pc ? 200 : 404);
                }
                if ($method !== 'GET') {
                    send_json(['error' => 'Solo el administrador de AT puede gestionar prompts'], 403);
                }
                if (!$is_supervisor) {
                    send_json(['error' => 'No tienes permisos para ver configuración de prompts'], 403);
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
                    // E3: usar helper centralizado (single-file: max_count=1)
                    $avatar_urls = at_omni_process_image_uploads(
                        'avatar',
                        ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        2 * 1024 * 1024,
                        1
                    );
                    if (empty($avatar_urls)) {
                        send_json(['error' => 'Tipo de archivo no permitido, supera 2MB, o error al subir.'], 400);
                    }
                    $avatar_url = $avatar_urls[0];
                    $wpdb->update($wpdb->prefix . 'omnichannel_agents', ['avatar_url' => esc_url_raw($avatar_url)], ['id' => $agent_id]);
                    $controller->audit_log('update', 'agent', $agent_id, "Avatar actualizado", null, ['avatar_url' => $avatar_url], $agent_client_id);
                    send_json(['avatar_url' => $avatar_url]);
                }
                break;

            // Agent: upload ticket images (authenticated)
            case 'ticket-images':
                if ($method === 'POST') {
                    if (empty($_FILES['images'])) send_json(['error' => 'No se enviaron imágenes'], 400);
                    // E3: usar helper centralizado
                    $urls = at_omni_process_image_uploads(
                        'images',
                        ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        3 * 1024 * 1024,
                        5
                    );
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
                    $logo_url = get_site_url() . '/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
                    $message = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f8fafc;">'
                        . '<div style="background:linear-gradient(135deg,#0d9488,#14b8a6,#06d6a0);padding:28px 24px;border-radius:12px 12px 0 0;text-align:center;">'
                        . '<img src="' . esc_url($logo_url) . '" alt="AutomatizaTech" style="height:60px;width:auto;border-radius:12px;margin-bottom:12px;" />'
                        . '<h1 style="color:#fff;margin:0;font-size:20px;font-weight:bold;">AutomatizaTech</h1>'
                        . '<p style="color:#a7f3d0;margin:6px 0 0;font-size:12px;letter-spacing:0.5px;">Portal Omnicanal de Clientes</p>'
                        . '</div>'
                        . '<div style="background:#ffffff;padding:28px 24px;border:1px solid #e2e8f0;border-top:none;">'
                        . '<h2 style="color:#1e293b;margin:0 0 16px;font-size:18px;">Código de verificación</h2>'
                        . '<p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($current_agent->name) . '</strong>,</p>'
                        . '<p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 20px;">Tu código de verificación para cambiar la contraseña es:</p>'
                        . '<div style="text-align:center;margin:0 0 20px;">'
                        . '<span style="display:inline-block;font-size:36px;font-weight:bold;letter-spacing:10px;color:#0d9488;background:#f0fdfa;padding:16px 32px;border-radius:12px;border:2px solid #a7f3d0;">' . $code . '</span>'
                        . '</div>'
                        . '<p style="color:#94a3b8;font-size:13px;line-height:1.5;">Este código expira en <strong>5 minutos</strong>. Si no solicitaste este cambio, ignora este mensaje.</p>'
                        . '</div>'
                        . '<div style="padding:16px 24px;text-align:center;background:#f1f5f9;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;">'
                        . '<p style="margin:0 0 4px;font-size:11px;color:#64748b;font-weight:600;">AutomatizaTech</p>'
                        . '<p style="margin:0 0 4px;font-size:10px;color:#94a3b8;">Automatización Inteligente para tu Negocio</p>'
                        . '<p style="margin:0;font-size:10px;color:#94a3b8;">soporte@automatizatech.cl · automatizatech.cl</p>'
                        . '</div>'
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

            // Agent: AI Assistant chat
            case 'ai-assistant':
                if ($method === 'POST') {
                    $user_message = trim($body['message'] ?? '');
                    if (empty($user_message)) {
                        send_json(['error' => 'Mensaje vacío'], 400);
                    }
                    $history = is_array($body['history'] ?? null) ? $body['history'] : [];
                    $result = $controller->ai_assistant_chat(
                        $agent_client_id,
                        $current_agent->role ?? 'agent',
                        $current_agent->name ?? 'Agente',
                        $user_message,
                        $history,
                        $current_agent->id ?? 0
                    );
                    $code = isset($result['error']) ? ($result['code'] ?? 400) : 200;
                    send_json($result, $code);
                }
                break;

            // Agent: AI Chat History (backend persistence for Omni Assistant)
            case 'ai-chat-history':
                $ai_key      = 'agent:' . $agent_id;
                $ai_chat_seg = sanitize_text_field($segments[2] ?? '');
                if ($method === 'GET') {
                    $rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT id, messages, created_at, updated_at FROM {$wpdb->prefix}omnichannel_ai_chats WHERE agent_key = %s ORDER BY updated_at DESC LIMIT 30",
                            $ai_key
                        )
                    );
                    $chats = array_map(function($r) {
                        $r->messages  = json_decode($r->messages, true) ?: [];
                        $r->createdAt = (int) (strtotime($r->created_at) * 1000);
                        $r->updatedAt = (int) (strtotime($r->updated_at) * 1000);
                        return $r;
                    }, $rows ?: []);
                    send_json(['chats' => $chats]);
                }
                if ($method === 'POST') {
                    $chat_id = sanitize_text_field($body['id'] ?? '');
                    $messages = is_array($body['messages'] ?? null) ? $body['messages'] : [];
                    if (empty($chat_id)) send_json(['error' => 'id requerido'], 400);
                    $existing_key = $wpdb->get_var($wpdb->prepare(
                        "SELECT agent_key FROM {$wpdb->prefix}omnichannel_ai_chats WHERE id = %s", $chat_id
                    ));
                    if ($existing_key && $existing_key !== $ai_key) send_json(['error' => 'Forbidden'], 403);
                    $now = current_time('mysql');
                    if ($existing_key) {
                        $wpdb->update($wpdb->prefix . 'omnichannel_ai_chats', [
                            'messages'   => wp_json_encode($messages, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $now,
                        ], ['id' => $chat_id]);
                    } else {
                        $wpdb->insert($wpdb->prefix . 'omnichannel_ai_chats', [
                            'id'         => $chat_id,
                            'agent_key'  => $ai_key,
                            'messages'   => wp_json_encode($messages, JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                    send_json(['saved' => true]);
                }
                if ($method === 'DELETE' && !empty($ai_chat_seg)) {
                    $existing_key = $wpdb->get_var($wpdb->prepare(
                        "SELECT agent_key FROM {$wpdb->prefix}omnichannel_ai_chats WHERE id = %s", $ai_chat_seg
                    ));
                    if (!$existing_key) send_json(['error' => 'Chat no encontrado'], 404);
                    if ($existing_key !== $ai_key) send_json(['error' => 'Forbidden'], 403);
                    $wpdb->delete($wpdb->prefix . 'omnichannel_ai_chats', ['id' => $ai_chat_seg]);
                    send_json(['deleted' => true]);
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

        // --- CONTRATO DE SUSCRIPCIÓN (aceptación click-wrap Fase 5 §8-bis) ---
        // GET  subscription           -> estado (aceptado/pendiente/cancelación) + HTML del contrato por plan
        // POST subscription/accept    -> registra aceptación con IP + timestamp + versión (evidencia Ley 21.719)
        // POST subscription/cancel    -> cancelación en 1 clic (Ley 21.398), efectiva al fin del ciclo, avisa a AT
        case 'subscription':
            at_omni_subscription_table();
            $sub_action = $segments[1] ?? '';
            if ($method === 'GET' && $sub_action === '') {
                send_json(at_omni_subscription_status($client));
            }
            if ($method === 'POST' && $sub_action === 'accept') {
                if (empty($body['accept'])) {
                    send_json(['error' => 'Debes aceptar el contrato para continuar'], 400);
                }
                global $wpdb;
                $status = at_omni_subscription_status($client);
                if (!$status['accepted']) {
                    $wpdb->insert($wpdb->prefix . 'omnichannel_subscription_events', [
                        'client_id'        => $client_id,
                        'event_type'       => 'accept',
                        'contract_version' => AT_SUBSCRIPTION_CONTRACT_VERSION,
                        'ip'               => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                        'user_agent'       => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                        'created_at'       => current_time('mysql'),
                    ]);
                }
                send_json(at_omni_subscription_status($client));
            }
            if ($method === 'POST' && $sub_action === 'cancel') {
                global $wpdb;
                $wpdb->insert($wpdb->prefix . 'omnichannel_subscription_events', [
                    'client_id'        => $client_id,
                    'event_type'       => 'cancel',
                    'contract_version' => AT_SUBSCRIPTION_CONTRACT_VERSION,
                    'ip'               => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                    'user_agent'       => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'created_at'       => current_time('mysql'),
                ]);
                // Aviso a AT (falla silenciosa: la cancelación queda registrada igual)
                @wp_mail(
                    'contacto@automatizatech.cl',
                    '⚠️ Cancelación de suscripción — ' . ($client->company_name ?? ('cliente #' . $client_id)),
                    "El cliente {$client->company_name} (id $client_id, email {$client->email}) solicitó cancelar su suscripción desde el portal.\n\n" .
                    "Fecha: " . current_time('mysql') . "\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n" .
                    "Plan: " . ($client->plan_type ?? '-') . "\nFin del período en curso: " . ($client->period_end ?? '-') . "\n\n" .
                    "La cancelación es efectiva al término del período facturado en curso (ToS §4)."
                );
                send_json(at_omni_subscription_status($client));
            }
            break;

        // --- CONSUMO IA (solo datos del propio cliente) ---
        case 'usage-stats':
            if ($method === 'GET') {
                send_json($atfinance->usage_stats($client_id, absint($_GET['days'] ?? 30)));
            }
            break;

        // --- INGESTA DE USO IA desde n8n (autenticada por API key del cliente) ---
        case 'usage-ingest':
            if ($method === 'POST') {
                $result = $atfinance->ingest_from_n8n($client_id, $body);
                send_json($result, isset($result['error']) ? ($result['code'] ?? 400) : 201);
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
                $result = $controller->send_agent_message($conv_id, $body);
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

            // Export full conversation history as plain text (client-admin: only their company)
            if ($method === 'GET' && isset($segments[1]) && ($segments[2] ?? '') === 'export-history') {
                $conv_id = absint($segments[1]);
                $conv_client = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT client_id FROM {$wpdb->prefix}omnichannel_conversations WHERE id = %d",
                    $conv_id
                ));
                if ($conv_client !== $client_id) {
                    send_json(['error' => 'Acceso denegado: no tienes permisos para acceder a conversaciones de otra empresa.'], 403);
                }
                $result = $controller->export_conversation_history($conv_id);
                send_json($result, isset($result['error']) ? 404 : 200);
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

        // --- PROMPT CONFIGS (empresa: solo lectura) ---
        case 'prompts':
            if ($method === 'GET' && !isset($segments[1])) {
                $channel_filter = absint($_GET['channel_id'] ?? 0);
                send_json($controller->get_prompt_configs($channel_filter));
            }
            if ($method === 'GET' && isset($segments[1])) {
                $pc = $controller->get_prompt_config(absint($segments[1]));
                send_json($pc ?: ['error' => 'No encontrado'], $pc ? 200 : 404);
            }
            if ($method !== 'GET') {
                send_json(['error' => 'Solo el administrador de AT puede gestionar prompts'], 403);
            }
            break;

        // --- AGENTES ---
        case 'agents':
            if ($method === 'GET') {
                $page = absint($_GET['page'] ?? 0);
                $per_page = min(absint($_GET['per_page'] ?? 0), 100);
                $agent_params = [
                    'search'     => sanitize_text_field($_GET['search'] ?? ''),
                    'orderby'    => sanitize_text_field($_GET['orderby'] ?? 'created_at'),
                    'order'      => sanitize_text_field($_GET['order'] ?? 'DESC'),
                    'channel_id' => absint($_GET['channel_id'] ?? 0),
                ];
                if ($page > 0 && $per_page > 0) {
                    send_json($controller->get_agents($client_id, $agent_params, $page, $per_page));
                } else {
                    send_json($controller->get_agents($client_id, $agent_params));
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

        // --- AI ASSISTANT (Professional/Enterprise) ---
        case 'ai-assistant':
            if ($method === 'POST') {
                $user_message = trim($body['message'] ?? '');
                if (empty($user_message)) {
                    send_json(['error' => 'Mensaje vacío'], 400);
                }
                $history = is_array($body['history'] ?? null) ? $body['history'] : [];
                $result = $controller->ai_assistant_chat(
                    $client_id,
                    'client',
                    $client->contact_name ?? $client->company_name ?? 'Cliente',
                    $user_message,
                    $history
                );
                $code = isset($result['error']) ? ($result['code'] ?? 400) : 200;
                send_json($result, $code);
            }
            break;

        default:
            send_json(['error' => 'Ruta no encontrada', 'available_routes' => [
                'GET /conversations', 'GET /conversations/{id}/messages', 'POST /conversations/{id}/messages',
                'POST /conversations/{id}/takeover', 'POST /conversations/{id}/release', 'POST /conversations/{id}/transfer',
                'GET /channels', 'POST /channels', 'PUT /channels/{id}',
                'GET /bots', 'PUT /bots/{id}',
                'GET /agents', 'POST /agents',
                'GET /audit', 'POST /ai-assistant',
            ]], 404);
    }
    
} catch (Exception $e) {
    send_json(['error' => 'Error interno del servidor'], 500);
}
