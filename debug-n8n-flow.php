<?php
/**
 * Diagnóstico temporal del flujo YCloud → Portal → N8N
 * ELIMINAR DESPUÉS DE USAR
 */
require_once __DIR__ . '/wp-load.php';
header('Content-Type: application/json');

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';

// 1. Canal id=1
$channel = $wpdb->get_row("SELECT id, channel_name, channel_type, phone_number, provider, is_active, webhook_secret, ycloud_api_key FROM {$prefix}channels WHERE id = 1");

// 2. Bot config para canal 1
$bot = $wpdb->get_row("SELECT * FROM {$prefix}bot_configs WHERE channel_id = 1");

// 3. Últimas conversaciones
$convs = $wpdb->get_results("SELECT id, channel_id, status, intervention_mode, external_contact_id, last_message_at FROM {$prefix}conversations ORDER BY id DESC LIMIT 5");

// 4. Últimos mensajes
$msgs = $wpdb->get_results("SELECT id, conversation_id, direction, sender_type, content, created_at FROM {$prefix}messages ORDER BY id DESC LIMIT 5");

echo json_encode([
    'channel_1' => $channel,
    'bot_config_channel_1' => $bot,
    'recent_conversations' => $convs,
    'recent_messages' => $msgs,
    'OMNI_ADMIN_SECRET_defined' => defined('OMNI_ADMIN_SECRET'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
