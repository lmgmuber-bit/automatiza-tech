<?php
/**
 * Endpoint REST para leer y actualizar los prompts de una propuesta.
 *
 * Permite a herramientas externas (skill at-proposal-refiner) consultar y
 * refinar el prompt de Gamma y el system prompt del chatbot por ID.
 *
 * Auth: header X-AT-Secret debe coincidir con la constante AT_REST_SECRET
 * (definida en wp-config.php / env). Comparación timing-safe con hash_equals.
 *
 * Rutas:
 *   GET  /wp-json/automatiza-tech/v1/proposal/{id}/prompts
 *   POST /wp-json/automatiza-tech/v1/proposal/{id}/prompts
 *
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/proposals-flow.php';

/**
 * Verifica el secret compartido del header X-AT-Secret.
 */
function automatiza_proposals_rest_auth(WP_REST_Request $request) {
    if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
        return new WP_Error(
            'at_rest_misconfigured',
            'AT_REST_SECRET no está configurado en el servidor.',
            ['status' => 500]
        );
    }

    $provided = (string) $request->get_header('x_at_secret');
    if ($provided === '') {
        return new WP_Error(
            'at_rest_no_secret',
            'Falta el header X-AT-Secret.',
            ['status' => 401]
        );
    }

    if (!hash_equals((string) AT_REST_SECRET, $provided)) {
        return new WP_Error(
            'at_rest_bad_secret',
            'Secret inválido.',
            ['status' => 403]
        );
    }

    return true;
}

/**
 * GET: devuelve los prompts y datos de contexto de la propuesta.
 */
function automatiza_proposals_rest_get(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'automatiza_propuestas';
    $id = (int) $request['id'];

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, client_name, company_name, gamma_prompt_text, system_prompt_text, status
         FROM {$table} WHERE id = %d",
        $id
    ));

    if (!$row) {
        return new WP_Error(
            'at_proposal_not_found',
            "No existe la propuesta con id {$id}.",
            ['status' => 404]
        );
    }

    return new WP_REST_Response([
        'id'                 => (int) $row->id,
        'client_name'        => $row->client_name,
        'company_name'       => $row->company_name,
        'status'             => $row->status,
        'gamma_prompt_text'  => (string) $row->gamma_prompt_text,
        'system_prompt_text' => (string) $row->system_prompt_text,
    ], 200);
}

/**
 * POST: actualiza gamma_prompt_text y/o system_prompt_text.
 * Solo actualiza los campos enviados y no vacíos (igual que el panel admin).
 */
function automatiza_proposals_rest_update(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'automatiza_propuestas';
    $id = (int) $request['id'];

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, flujo FROM {$table} WHERE id = %d",
        $id
    ));
    if (!$row) {
        return new WP_Error(
            'at_proposal_not_found',
            "No existe la propuesta con id {$id}.",
            ['status' => 404]
        );
    }
    if ($row->flujo === 'v3') {
        return new WP_Error(
            'at_v3_usa_estado',
            'Esta propuesta usa el flujo v3: los prompts se manejan por /proposal/{id}/state, no por este endpoint.',
            ['status' => 409]
        );
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $update = [];
    if (isset($params['gamma_prompt_text']) && trim((string) $params['gamma_prompt_text']) !== '') {
        $update['gamma_prompt_text'] = sanitize_textarea_field($params['gamma_prompt_text']);
    }
    if (isset($params['system_prompt_text']) && trim((string) $params['system_prompt_text']) !== '') {
        $update['system_prompt_text'] = sanitize_textarea_field($params['system_prompt_text']);
    }

    if (empty($update)) {
        return new WP_Error(
            'at_no_fields',
            'Envía gamma_prompt_text y/o system_prompt_text (no vacíos).',
            ['status' => 400]
        );
    }

    $result = $wpdb->update($table, $update, ['id' => $id]);

    if ($result === false) {
        return new WP_Error(
            'at_update_failed',
            'Error al actualizar la propuesta en la base de datos.',
            ['status' => 500]
        );
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, client_name, company_name, gamma_prompt_text, system_prompt_text, status
         FROM {$table} WHERE id = %d",
        $id
    ));

    return new WP_REST_Response([
        'updated'            => array_keys($update),
        'id'                 => (int) $row->id,
        'client_name'        => $row->client_name,
        'company_name'       => $row->company_name,
        'status'             => $row->status,
        'gamma_prompt_text'  => (string) $row->gamma_prompt_text,
        'system_prompt_text' => (string) $row->system_prompt_text,
    ], 200);
}

/**
 * Formato común de salida del estado de una propuesta v3.
 */
function automatiza_proposals_state_out($row) {
    $log = json_decode((string) $row->feedback_log, true);
    $log = is_array($log) ? $log : [];
    $ultimo = $log ? end($log) : null;
    return [
        'id'                => (int) $row->id,
        'unique_id'         => $row->unique_link_id,
        'flujo'             => $row->flujo,
        'status'            => $row->status,
        'status_note'       => (string) $row->status_note,
        'client_email'      => $row->client_email,
        'client_name'       => $row->client_name,
        'company_name'      => $row->company_name,
        'payload'           => json_decode((string) $row->gamma_prompt_text, true),
        'system_prompt'     => (string) $row->system_prompt_text,
        'feedback_log'      => $log,
        'ultimo_comentario' => $ultimo ? $ultimo['comentario'] : '',
    ];
}

/**
 * POST /proposal — alta de una propuesta v3 en borrador.
 */
function automatiza_proposals_rest_create(WP_REST_Request $request) {
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $p = $request->get_json_params();
    $payload = is_array($p['payload'] ?? null) ? $p['payload'] : null;
    if (!$payload) {
        return new WP_Error('at_no_payload', 'Falta payload (objeto).', ['status' => 422]);
    }
    $uid = wp_generate_password(12, false);
    $payload['unique_id'] = $uid;
    $errores = at_propuesta_errores_payload($payload);
    if ($errores) {
        return new WP_Error('at_payload_invalido', implode('; ', $errores), ['status' => 422]);
    }
    $email = sanitize_email((string) ($p['client_email'] ?? ''));
    $renderer = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $uid . '/index.html';
    $ok = $wpdb->insert($t, [
        'client_email'       => $email,
        'unique_link_id'     => $uid,
        'transcript_text'    => (string) ($p['transcript'] ?? ''),
        'gamma_prompt_text'  => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'system_prompt_text' => (string) ($p['system_prompt'] ?? ''),
        'gamma_iframe_url'   => $renderer,
        'n8n_chat_url'       => 'https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat',
        'client_name'        => sanitize_text_field((string) $payload['client_name']),
        'company_name'       => sanitize_text_field((string) $payload['company_name']),
        'phone'              => sanitize_text_field((string) ($p['phone'] ?? '')),
        'status'             => 'borrador',
        'flujo'              => 'v3',
        'created_at'         => current_time('mysql'),
    ]);
    if (!$ok) {
        return new WP_Error('at_insert_failed', $wpdb->last_error, ['status' => 500]);
    }
    $id = (int) $wpdb->insert_id;
    return new WP_REST_Response([
        'id'        => $id,
        'unique_id' => $uid,
        'view_url'  => $renderer,
        'panel_url' => admin_url('admin.php?page=automatiza-proposals&edit_id=' . $id),
    ], 201);
}

/**
 * GET /proposal/{id}/state
 */
function automatiza_proposals_rest_state_get(WP_REST_Request $request) {
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", (int) $request['id']));
    if (!$row) {
        return new WP_Error('at_proposal_not_found', 'No existe la propuesta.', ['status' => 404]);
    }
    return new WP_REST_Response(automatiza_proposals_state_out($row), 200);
}

/**
 * POST /proposal/{id}/state — cambio de estado (+ payload opcional).
 */
function automatiza_proposals_rest_state_set(WP_REST_Request $request) {
    global $wpdb;
    $t = $wpdb->prefix . 'automatiza_propuestas';
    $id = (int) $request['id'];
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
    if (!$row) {
        return new WP_Error('at_proposal_not_found', 'No existe la propuesta.', ['status' => 404]);
    }
    $p = $request->get_json_params();
    $nuevo = (string) ($p['status'] ?? '');
    if (!at_propuesta_estado_permitido_por_api($nuevo)) {
        return new WP_Error('at_envio_solo_panel', 'El envío al cliente (sent) solo se hace desde el panel.', ['status' => 409]);
    }
    if (!at_propuesta_transicion_valida((string) $row->status, $nuevo)) {
        return new WP_Error('at_transicion', "Transición no permitida: {$row->status} → {$nuevo}", ['status' => 409]);
    }
    $update = ['status' => $nuevo, 'status_note' => sanitize_textarea_field((string) ($p['note'] ?? ''))];
    if (is_array($p['payload'] ?? null)) {
        $guardado = json_decode((string) $row->gamma_prompt_text, true) ?: [];
        $payload = at_propuesta_conservar_precios($guardado, $p['payload']);
        $errores = at_propuesta_errores_payload($payload);
        if ($errores) {
            return new WP_Error('at_payload_invalido', implode('; ', $errores), ['status' => 422]);
        }
        $update['gamma_prompt_text'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($wpdb->update($t, $update, ['id' => $id]) === false) {
        return new WP_Error('at_update_failed', $wpdb->last_error, ['status' => 500]);
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
    return new WP_REST_Response(automatiza_proposals_state_out($row), 200);
}

/**
 * Registrar las rutas REST.
 */
function automatiza_proposals_rest_routes() {
    register_rest_route('automatiza-tech/v1', '/proposal/(?P<id>\d+)/prompts', [
        [
            'methods'             => 'GET',
            'callback'            => 'automatiza_proposals_rest_get',
            'permission_callback' => 'automatiza_proposals_rest_auth',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($v) { return is_numeric($v); },
                ],
            ],
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'automatiza_proposals_rest_update',
            'permission_callback' => 'automatiza_proposals_rest_auth',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($v) { return is_numeric($v); },
                ],
            ],
        ],
    ]);
    $id_arg = ['id' => ['required' => true, 'validate_callback' => function ($v) { return is_numeric($v); }]];
    register_rest_route('automatiza-tech/v1', '/proposal', [
        'methods'             => 'POST',
        'callback'            => 'automatiza_proposals_rest_create',
        'permission_callback' => 'automatiza_proposals_rest_auth',
    ]);
    register_rest_route('automatiza-tech/v1', '/proposal/(?P<id>\d+)/state', [
        ['methods' => 'GET', 'callback' => 'automatiza_proposals_rest_state_get', 'permission_callback' => 'automatiza_proposals_rest_auth', 'args' => $id_arg],
        ['methods' => 'POST', 'callback' => 'automatiza_proposals_rest_state_set', 'permission_callback' => 'automatiza_proposals_rest_auth', 'args' => $id_arg],
    ]);
}
add_action('rest_api_init', 'automatiza_proposals_rest_routes');
