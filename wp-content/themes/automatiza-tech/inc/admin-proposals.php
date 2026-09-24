<?php
/**
 * Panel de Administración para Aprobar Propuestas
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/proposals-flow.php';

if (!defined('AT_N8N_V3_CAMBIOS')) {
    define('AT_N8N_V3_CAMBIOS', 'https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-cambios');
}
if (!defined('AT_N8N_V3_FINAL')) {
    define('AT_N8N_V3_FINAL', 'https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-final');
}

/** Avisa a n8n; devuelve '' si respondió 2xx o el motivo del fallo. */
function at_v3_llamar_n8n(string $url, int $id): string {
    if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
        return 'AT_REST_SECRET no está configurado.';
    }
    $r = wp_remote_post($url, [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
        'body'    => wp_json_encode(['id' => $id]),
    ]);
    if (is_wp_error($r)) {
        return $r->get_error_message();
    }
    $code = wp_remote_retrieve_response_code($r);
    return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}

/**
 * Agregar menú de administración
 */
function automatiza_tech_proposals_menu() {
    add_menu_page(
        'Aprobar Propuestas',
        'Propuestas',
        'manage_options',
        'automatiza-proposals',
        'automatiza_tech_proposals_page',
        'dashicons-format-aside',
        26
    );
}
add_action('admin_menu', 'automatiza_tech_proposals_menu');

require_once __DIR__ . '/propuestas-admin/consultas.php';
require_once __DIR__ . '/propuestas-admin/acciones.php';
require_once __DIR__ . '/propuestas-admin/clasico.php';

/**
 * Página Propuestas. &clasico=1 abre el módulo anterior intacto (red de seguridad).
 * Tarea 2 del plan: todavía siempre usa el clásico; las tareas 3 y 4 conectan la lista y la ficha.
 */
function automatiza_tech_proposals_page() {
    automatiza_tech_proposals_page_clasico();
}
?>