<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Diagnóstico y creación directa de la tabla wp_omnichannel_clients
 */
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado.');
}

global $wpdb;
$prefix = $wpdb->prefix;
$table = $prefix . 'omnichannel_clients';
$charset_collate = $wpdb->get_charset_collate();

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace; font-size:14px; padding:20px;">';

// 1. Check if table exists
$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
echo "1. Tabla existe? " . ($exists ? "SI: $exists" : "NO") . "\n\n";

// 2. If exists in broken state, drop it
if ($exists) {
    echo "2. Tabla ya existe. Intentando DROP y recrear...\n";
    $wpdb->query("DROP TABLE IF EXISTS `$table`");
    $drop_err = $wpdb->last_error;
    echo "   DROP resultado: " . ($drop_err ? "ERROR: $drop_err" : "OK") . "\n\n";
} else {
    echo "2. Tabla no existe, creando desde cero...\n\n";
}

// 3. MySQL version
$mysql_version = $wpdb->get_var("SELECT VERSION()");
echo "3. MySQL version: $mysql_version\n\n";

// 4. Try creating with simplified SQL (no ON UPDATE)
$sql = "CREATE TABLE `$table` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(191) NOT NULL,
    contact_name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL,
    plan_type VARCHAR(20) NOT NULL DEFAULT 'basic',
    status VARCHAR(20) NOT NULL DEFAULT 'trial',
    max_channels INT UNSIGNED DEFAULT 2,
    max_agents INT UNSIGNED DEFAULT 3,
    api_key VARCHAR(64) DEFAULT NULL,
    trial_ends_at DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_email (email),
    UNIQUE KEY idx_api_key (api_key),
    KEY idx_status (status),
    KEY idx_wp_user (wp_user_id)
) $charset_collate;";

echo "4. SQL a ejecutar:\n$sql\n\n";

$result = $wpdb->query($sql);
$create_err = $wpdb->last_error;

echo "5. Resultado query(): " . var_export($result, true) . "\n";
echo "   Last error: " . ($create_err ? $create_err : "(ninguno)") . "\n\n";

// 5. Verify
$exists_now = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
echo "6. Tabla existe ahora? " . ($exists_now ? "SI ✅" : "NO ❌") . "\n\n";

if ($exists_now) {
    // Add missing columns if needed
    $cols_list = array_column($wpdb->get_results("DESCRIBE `$table`"), 'Field');
    $missing = [];
    if (!in_array('phone', $cols_list)) {
        $wpdb->query("ALTER TABLE `$table` ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER email");
        $missing[] = 'phone';
    }
    if (!in_array('wp_user_id', $cols_list)) {
        $wpdb->query("ALTER TABLE `$table` ADD COLUMN wp_user_id BIGINT UNSIGNED DEFAULT NULL AFTER phone");
        $missing[] = 'wp_user_id';
    }
    if ($missing) {
        echo "   Columnas agregadas: " . implode(', ', $missing) . "\n";
    }

    $cols = $wpdb->get_results("DESCRIBE `$table`");
    echo "7. Columnas:\n";
    foreach ($cols as $col) {
        echo "   - {$col->Field} ({$col->Type}) {$col->Null} {$col->Key} Default:{$col->Default}\n";
    }
    echo "\n✅ TABLA CREADA EXITOSAMENTE\n";
    echo "Ahora ve a: http://localhost/automatiza-tech/setup-omnichannel-test-client.php\n";
} else {
    echo "❌ FALLÓ. Intentando sin ON UPDATE CURRENT_TIMESTAMP...\n\n";
    
    $sql2 = "CREATE TABLE `$table` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(191) NOT NULL,
        contact_name VARCHAR(191) NOT NULL,
        email VARCHAR(191) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        wp_user_id BIGINT UNSIGNED DEFAULT NULL,
        plan_type VARCHAR(20) NOT NULL DEFAULT 'basic',
        status VARCHAR(20) NOT NULL DEFAULT 'trial',
        max_channels INT UNSIGNED DEFAULT 2,
        max_agents INT UNSIGNED DEFAULT 3,
        api_key VARCHAR(64) DEFAULT NULL,
        trial_ends_at DATETIME DEFAULT NULL,
        activated_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_email (email),
        UNIQUE KEY idx_api_key (api_key)
    ) $charset_collate;";
    
    $result2 = $wpdb->query($sql2);
    $err2 = $wpdb->last_error;
    echo "   Resultado: " . var_export($result2, true) . "\n";
    echo "   Error: " . ($err2 ? $err2 : "(ninguno)") . "\n";
    
    $exists_final = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    echo "   Tabla existe? " . ($exists_final ? "SI ✅" : "NO ❌") . "\n";
    
    if (!$exists_final) {
        echo "\n❌ IMPOSIBLE CREAR LA TABLA. Revisa los permisos de MySQL.\n";
        echo "   Usuario DB: " . DB_USER . "\n";
        echo "   Base de datos: " . DB_NAME . "\n";
    }
}

echo '</pre>';
