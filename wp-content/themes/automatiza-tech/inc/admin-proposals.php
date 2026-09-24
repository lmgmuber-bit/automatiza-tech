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
    $hook = add_menu_page(
        'Aprobar Propuestas',
        'Propuestas',
        'manage_options',
        'automatiza-proposals',
        'automatiza_tech_proposals_page',
        'dashicons-format-aside',
        26
    );
    add_action('load-' . $hook, 'at_pa_opciones_pantalla');
}
add_action('admin_menu', 'automatiza_tech_proposals_menu');

/** «Opciones de pantalla» → Propuestas por página (solo en la lista nueva). */
function at_pa_opciones_pantalla() {
    if (isset($_GET['edit_id']) || isset($_GET['clasico'])) {
        return;
    }
    add_screen_option('per_page', ['label' => 'Propuestas por página', 'default' => 20, 'option' => 'propuestas_por_pagina']);
}
add_filter('set_screen_option_propuestas_por_pagina', function ($status, $option, $value) {
    return min(200, max(5, (int) $value));
}, 10, 3);

/** CSS y JS del módulo nuevo, solo en su página y nunca en el clásico. */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_automatiza-proposals' || isset($_GET['clasico'])) {
        return;
    }
    $dir = get_template_directory() . '/assets';
    $url = get_template_directory_uri() . '/assets';
    if (file_exists($dir . '/css/propuestas-admin.css')) {
        wp_enqueue_style('at-propuestas-admin', $url . '/css/propuestas-admin.css', [], (string) filemtime($dir . '/css/propuestas-admin.css'));
    }
    if (file_exists($dir . '/js/propuestas-admin.js')) {
        wp_enqueue_script('at-propuestas-admin', $url . '/js/propuestas-admin.js', [], (string) filemtime($dir . '/js/propuestas-admin.js'), true);
    }
});

require_once __DIR__ . '/propuestas-admin/consultas.php';
require_once __DIR__ . '/propuestas-admin/acciones.php';
require_once __DIR__ . '/propuestas-admin/clasico.php';
require_once __DIR__ . '/propuestas-admin/lista.php';
require_once __DIR__ . '/propuestas-admin/ficha.php';

/**
 * Página Propuestas. &clasico=1 abre el módulo clásico (red de seguridad). &edit_id=... abre la ficha nueva de 6 pestañas.
 */
function automatiza_tech_proposals_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ver las propuestas.');
    }
    // El clásico procesa sus propios formularios (no tienen action: vuelven a esta misma URL con clasico=1).
    if (isset($_GET['clasico'])) {
        automatiza_tech_proposals_page_clasico();
        return;
    }
    $message = at_pa_procesar_acciones();
    $edit_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
    if ($edit_id > 0) {
        global $wpdb;
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $edit_id));
        if ($p) {
            at_pa_render_ficha($p, $message);
            return;
        }
        $message .= '<div class="notice notice-error"><p>Esa propuesta no existe (id ' . (int) $edit_id . '). <a href="'
            . esc_url(admin_url('admin.php?page=automatiza-proposals')) . '">Ver la lista</a></p></div>';
    }
    at_pa_render_lista($message);
}
?>