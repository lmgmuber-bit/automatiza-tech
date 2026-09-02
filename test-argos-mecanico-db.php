<?php
require_once __DIR__ . '/at-maintenance-guard.php';
require_once 'wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar este script.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_n8n_errors';

echo "=== Verificando columnas de ARGOS Mecánico en {$table_name} ===" . PHP_EOL;

$columnas_esperadas = array('fix_attempts', 'fix_status', 'fix_history', 'last_fix_attempt_at');
$columnas_reales = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}");

$faltantes = array();
foreach ($columnas_esperadas as $col) {
    if (in_array($col, $columnas_reales, true)) {
        echo "OK {$col} existe" . PHP_EOL;
    } else {
        echo "FALTA {$col}" . PHP_EOL;
        $faltantes[] = $col;
    }
}

if (!empty($faltantes)) {
    echo PHP_EOL . "Faltan columnas. Ejecuta /setup-n8n-errors-db.php de nuevo." . PHP_EOL;
    exit;
}

echo PHP_EOL . "=== Insertando error de prueba y probando el ciclo completo ===" . PHP_EOL;

$test_data = array(
    'workflow_name' => 'TEST - Argos Mecanico DB',
    'workflow_id' => 'test-workflow-id',
    'error_message' => 'Error de prueba para verificar columnas nuevas',
    'error_node' => 'Nodo de prueba',
    'error_timestamp' => current_time('mysql'),
    'status' => 'new',
);
$inserted = $wpdb->insert($table_name, $test_data);
if ($inserted === false) {
    echo "No se pudo insertar el error de prueba: " . $wpdb->last_error . PHP_EOL;
    exit;
}
$test_id = $wpdb->insert_id;
echo "OK error de prueba insertado, id={$test_id}" . PHP_EOL;

$pendientes = $wpdb->get_results(
    "SELECT id FROM {$table_name} WHERE status='new' AND fix_status='pendiente' AND fix_attempts < 3"
);
$encontrado = false;
foreach ($pendientes as $p) {
    if ((int) $p->id === $test_id) $encontrado = true;
}
echo $encontrado
    ? "OK el error de prueba aparece como pendiente de reparar" . PHP_EOL
    : "FALLA: el error de prueba NO aparece como pendiente" . PHP_EOL;

$wpdb->update($table_name, array(
    'fix_attempts' => 1,
    'fix_status' => 'pendiente',
    'fix_history' => wp_json_encode(array(array(
        'intento' => 1, 'fecha' => current_time('mysql'),
        'diagnostico' => 'prueba', 'accion' => 'ninguna', 'resultado' => 'fallido',
    ))),
    'last_fix_attempt_at' => current_time('mysql'),
), array('id' => $test_id));

$row = $wpdb->get_row($wpdb->prepare(
    "SELECT fix_attempts, fix_status, fix_history FROM {$table_name} WHERE id=%d", $test_id
));
echo ((int) $row->fix_attempts === 1)
    ? "OK fix_attempts se actualizo a 1" . PHP_EOL
    : "FALLA: fix_attempts no se actualizo" . PHP_EOL;
$historial = json_decode($row->fix_history, true);
echo (is_array($historial) && count($historial) === 1)
    ? "OK fix_history guardo el intento correctamente" . PHP_EOL
    : "FALLA: fix_history no quedo bien" . PHP_EOL;

$wpdb->delete($table_name, array('id' => $test_id));
echo PHP_EOL . "Limpieza: error de prueba eliminado." . PHP_EOL;
