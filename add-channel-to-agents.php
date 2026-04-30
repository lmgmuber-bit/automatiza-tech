<?php
/**
 * Migration: Add channel_id column to agents table
 * Allows agents to be associated with specific channels
 * Run once: /add-channel-to-agents.php?token=xxxx
 */
require_once __DIR__ . '/wp-load.php';

if (empty($_GET['token']) || !hash_equals(wp_hash('add-channel-agents-2026'), $_GET['token'])) {
    wp_die('Token inválido. Genera con: echo wp_hash("add-channel-agents-2026");');
}

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';
$table = $prefix . 'agents';
$results = [];

// Add channel_id column if not exists
$col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'channel_id'");
if (!$col_exists) {
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN channel_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER client_id");
    $results[] = "✅ Columna channel_id agregada a {$table}";

    // Add index
    $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_channel (channel_id)");
    $results[] = "✅ Índice idx_channel creado";
} else {
    $results[] = "ℹ️ Columna channel_id ya existe en {$table}";
}

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Migración: channel_id en agents</h2>';
echo '<pre>' . implode("\n", $results) . '</pre>';
echo '<p>Listo. Ahora los agentes pueden asociarse a un canal específico.</p>';
