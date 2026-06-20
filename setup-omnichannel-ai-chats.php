<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Omnichannel AI Chat History — DB Migration
 *
 * Crea la tabla omnichannel_ai_chats para persistir el historial
 * del asistente IA en el backend (reemplaza localStorage del frontend).
 *
 * Ejecutar: /setup-omnichannel-ai-chats.php (requiere admin WordPress)
 */

require_once __DIR__ . '/wp-load.php';

if ( ! current_user_can('manage_options') ) {
    wp_die('Acceso denegado.');
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$prefix          = $wpdb->prefix;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$sql = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_ai_chats (
    id          VARCHAR(32)   NOT NULL,
    agent_key   VARCHAR(64)   NOT NULL COMMENT 'agent:{id} or admin:{wp_user_id}',
    messages    LONGTEXT      NOT NULL COMMENT 'JSON array of {role, content}',
    created_at  DATETIME      NOT NULL,
    updated_at  DATETIME      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_agent_key (agent_key),
    KEY idx_updated   (updated_at)
) {$charset_collate};";

dbDelta( $sql );

echo '<p>✅ Tabla <code>' . esc_html($prefix) . 'omnichannel_ai_chats</code> creada/verificada correctamente.</p>';
