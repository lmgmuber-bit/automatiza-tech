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

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE id = %d",
        $id
    ));
    if (!$exists) {
        return new WP_Error(
            'at_proposal_not_found',
            "No existe la propuesta con id {$id}.",
            ['status' => 404]
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
}
add_action('rest_api_init', 'automatiza_proposals_rest_routes');
