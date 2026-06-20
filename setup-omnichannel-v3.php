<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Migración v3 - Login individual de agentes + Skills/Especialidad
 * 
 * Agrega columnas a omnichannel_agents:
 *   - password_hash  VARCHAR(255) - bcrypt hash para login
 *   - access_token   VARCHAR(191) - token único de sesión (UNIQUE KEY)
 *   - token_expires  DATETIME     - expiración del token
 *   - skills         TEXT         - JSON array de habilidades (e.g. ["ventas","soporte"])
 *   - department     VARCHAR(100) - departamento/área opcional
 * 
 * Uso: Acceder desde navegador como admin de WordPress
 *       http://localhost/automatiza-tech/setup-omnichannel-v3.php
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Requiere ser administrador de WordPress.');
}

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';
$table = $prefix . 'agents';

// Verificar que la tabla existe
$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
if (!$exists) {
    wp_die("La tabla {$table} no existe. Ejecuta primero setup-omnichannel-db.php");
}

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Migración v3 - Agent Login & Skills</h2>';
echo '<pre>';

$alterations = [
    [
        'column'  => 'password_hash',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `email`",
        'desc'    => 'password_hash VARCHAR(255) - hash bcrypt para login',
    ],
    [
        'column'  => 'access_token',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `access_token` VARCHAR(191) DEFAULT NULL AFTER `password_hash`",
        'desc'    => 'access_token VARCHAR(191) - token de sesión',
    ],
    [
        'column'  => 'token_expires',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `token_expires` DATETIME DEFAULT NULL AFTER `access_token`",
        'desc'    => 'token_expires DATETIME - expiración del token',
    ],
    [
        'column'  => 'skills',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `skills` TEXT DEFAULT NULL AFTER `role`",
        'desc'    => 'skills TEXT - JSON array de habilidades',
    ],
    [
        'column'  => 'department',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `department` VARCHAR(100) DEFAULT NULL AFTER `skills`",
        'desc'    => 'department VARCHAR(100) - departamento/área',
    ],
    [
        'column'  => 'reset_token',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `reset_token` VARCHAR(191) DEFAULT NULL AFTER `token_expires`",
        'desc'    => 'reset_token VARCHAR(191) - token para recuperar contraseña',
    ],
    [
        'column'  => 'reset_token_expires',
        'sql'     => "ALTER TABLE `{$table}` ADD COLUMN `reset_token_expires` DATETIME DEFAULT NULL AFTER `reset_token`",
        'desc'    => 'reset_token_expires DATETIME - expiración del token de reset',
    ],
];

$ok = 0;
$skip = 0;

foreach ($alterations as $alt) {
    $col_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE '{$alt['column']}'");
    if (!empty($col_exists)) {
        echo "⏭️  {$alt['column']} — ya existe, omitido\n";
        $skip++;
        continue;
    }
    $result = $wpdb->query($alt['sql']);
    if ($result !== false) {
        echo "✅ {$alt['desc']}\n";
        $ok++;
    } else {
        echo "❌ Error al agregar {$alt['column']}: {$wpdb->last_error}\n";
    }
}

// Agregar UNIQUE KEY en access_token si no existe
$indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_access_token'");
if (empty($indexes)) {
    $result = $wpdb->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `idx_access_token` (`access_token`)");
    if ($result !== false) {
        echo "✅ UNIQUE KEY idx_access_token creado\n";
        $ok++;
    } else {
        echo "❌ Error al crear índice: {$wpdb->last_error}\n";
    }
} else {
    echo "⏭️  idx_access_token — ya existe, omitido\n";
    $skip++;
}

// Agregar índice en skills para búsquedas fulltext (opcional, skip if not supported)
$indexes_skills = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_department'");
if (empty($indexes_skills)) {
    $result = $wpdb->query("ALTER TABLE `{$table}` ADD KEY `idx_department` (`department`)");
    if ($result !== false) {
        echo "✅ KEY idx_department creado\n";
        $ok++;
    } else {
        echo "❌ Error al crear índice department: {$wpdb->last_error}\n";
    }
} else {
    echo "⏭️  idx_department — ya existe, omitido\n";
    $skip++;
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Resultado: {$ok} creados, {$skip} omitidos\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Mostrar estructura final de la tabla
echo "\nEstructura actual de {$table}:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
foreach ($columns as $col) {
    $extra = $col->Extra ? " ({$col->Extra})" : '';
    $null = $col->Null === 'YES' ? 'NULL' : 'NOT NULL';
    echo "  {$col->Field} — {$col->Type} {$null} Default:{$col->Default}{$extra}\n";
}

echo '</pre>';
echo '<p style="color:green;font-weight:bold;">✅ Migración v3 completada</p>';
