<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Migración: Crear tabla wp_omnichannel_channel_types
 * Mantenedor de tipos de canales (solo admin)
 * 
 * Ejecutar: http://localhost/automatiza-tech/setup-channel-types.php
 */
require_once __DIR__ . '/wp-load.php';
global $wpdb;

$prefix = $wpdb->prefix . 'omnichannel_';
$charset = $wpdb->get_charset_collate();
$results = [];

// 1. Crear tabla de tipos de canal
$table = $prefix . 'channel_types';
$sql = "CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    emoji VARCHAR(10) DEFAULT '📡',
    color VARCHAR(50) DEFAULT 'gray-500',
    fields_json TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug)
) {$charset};";

$wpdb->query($sql);
$results[] = $wpdb->last_error ?: "✅ Tabla {$table} creada/verificada";

// 2. Insertar tipos por defecto si la tabla está vacía
$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
if ($count === 0) {
    $defaults = [
        [
            'slug' => 'whatsapp',
            'label' => 'WhatsApp',
            'emoji' => '📱',
            'color' => 'green-500',
            'fields_json' => json_encode([
                ['key' => 'phone_number', 'label' => 'Número de teléfono', 'placeholder' => '+521234567890']
            ]),
            'sort_order' => 1,
        ],
        [
            'slug' => 'instagram',
            'label' => 'Instagram',
            'emoji' => '📸',
            'color' => 'pink-500',
            'fields_json' => json_encode([
                ['key' => 'page_id', 'label' => 'ID de página/cuenta', 'placeholder' => 'ID de Instagram Business']
            ]),
            'sort_order' => 2,
        ],
        [
            'slug' => 'telegram',
            'label' => 'Telegram',
            'emoji' => '✈️',
            'color' => 'sky-500',
            'fields_json' => json_encode([
                ['key' => 'bot_token', 'label' => 'Bot Token', 'placeholder' => 'Token del BotFather']
            ]),
            'sort_order' => 3,
        ],
        [
            'slug' => 'messenger',
            'label' => 'Messenger',
            'emoji' => '💬',
            'color' => 'blue-500',
            'fields_json' => json_encode([
                ['key' => 'page_id', 'label' => 'Page ID de Facebook', 'placeholder' => 'ID de la página'],
                ['key' => 'bot_token', 'label' => 'Page Access Token', 'placeholder' => 'Token de acceso']
            ]),
            'sort_order' => 4,
        ],
    ];

    foreach ($defaults as $type) {
        $wpdb->insert($table, $type);
        $results[] = $wpdb->last_error ?: "  ✅ Tipo '{$type['slug']}' insertado";
    }
} else {
    $results[] = "ℹ️ Ya existen {$count} tipos de canal, no se insertan defaults";
}

// 3. Modificar la columna channel_type en channels, conversations y messages de ENUM a VARCHAR
$tables_to_alter = [
    $prefix . 'channels',
    $prefix . 'conversations',
    $prefix . 'messages',
];

foreach ($tables_to_alter as $t) {
    $col = $wpdb->get_row("SHOW COLUMNS FROM {$t} WHERE Field = 'channel_type'");
    if ($col && stripos($col->Type, 'enum') !== false) {
        $wpdb->query("ALTER TABLE {$t} MODIFY COLUMN channel_type VARCHAR(50) NOT NULL");
        $results[] = $wpdb->last_error ?: "✅ {$t}.channel_type cambiado de ENUM a VARCHAR(50)";
    } else {
        $results[] = "ℹ️ {$t}.channel_type ya es VARCHAR o no existe";
    }
}

// Output
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Migración: Tipos de Canal</h2><pre>';
foreach ($results as $r) echo $r . "\n";
echo '</pre><p><strong>Completado.</strong></p>';
