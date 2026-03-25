<?php
/**
 * Update KellsCapilar prompt config: add services catalog, schedule blocks
 * and scheduling definitions (from Excel sheets).
 * Run ONCE in production, then DELETE this file.
 */

define('ABSPATH', '');
$wp_load = file_exists(__DIR__ . '/wp-load.php')
    ? __DIR__ . '/wp-load.php'
    : __DIR__ . '/../wp-load.php';
if (!file_exists($wp_load)) {
    die('wp-load.php not found.');
}
require_once $wp_load;

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';

// ── 1. Find existing KellsCapilar config ─────────────────────────────────────
$config = $wpdb->get_row(
    "SELECT * FROM {$prefix}prompt_configs WHERE config_name LIKE '%KellsCapilar%' LIMIT 1",
    ARRAY_A
);

if (!$config) {
    echo '<pre style="color:red">ERROR: No se encontró la configuración KellsCapilar. Asegúrate de haber ejecutado el script de inserción primero.</pre>';
    exit;
}

echo '<pre>Configuración encontrada: [' . $config['id'] . '] ' . esc_html($config['config_name']) . '</pre>';

// ── 2. Decode existing prompt_data ───────────────────────────────────────────
$prompt_data = json_decode($config['prompt_data'], true);
if (!is_array($prompt_data)) {
    $prompt_data = [];
}

// ── 3. Catálogo de Servicios (JSON array) ─────────────────────────────────────
$catalogo_servicios = [
    ['id' => 1,  'nombre' => 'Alisado Corto',                     'duracion_min' => 120, 'precio' => 45000, 'descripcion' => 'Tratamiento alisado cabello corto'],
    ['id' => 2,  'nombre' => 'Alisado Medio',                     'duracion_min' => 240, 'precio' => 55000, 'descripcion' => 'Tratamiento alisado cabello medio'],
    ['id' => 3,  'nombre' => 'Alisado Largo',                     'duracion_min' => 300, 'precio' => 65000, 'descripcion' => 'Tratamiento alisado cabello largo'],
    ['id' => 4,  'nombre' => 'Botox Corto',                       'duracion_min' => 90,  'precio' => 35000, 'descripcion' => 'Tratamiento botox cabello corto'],
    ['id' => 5,  'nombre' => 'Botox Medio',                       'duracion_min' => 120, 'precio' => 45000, 'descripcion' => 'Tratamiento botox cabello medio'],
    ['id' => 6,  'nombre' => 'Botox Largo',                       'duracion_min' => 120, 'precio' => 55000, 'descripcion' => 'Tratamiento botox cabello largo'],
    ['id' => 7,  'nombre' => 'Corte Bordado',                     'duracion_min' => 30,  'precio' => 15000, 'descripcion' => 'Corte bordado todo largo'],
    ['id' => 8,  'nombre' => 'Corte Bordado + Hidratación',       'duracion_min' => 120, 'precio' => 25000, 'descripcion' => 'Corte bordado con hidratación'],
    ['id' => 9,  'nombre' => 'Corte Bordado + Nutrición',         'duracion_min' => 120, 'precio' => 25000, 'descripcion' => 'Corte bordado con nutrición'],
    ['id' => 10, 'nombre' => 'Corte Bordado + Restauración',      'duracion_min' => 120, 'precio' => 25000, 'descripcion' => 'Corte bordado con restauración'],
    ['id' => 11, 'nombre' => 'Corte Bordado + Nanotecnología',    'duracion_min' => 120, 'precio' => 30000, 'descripcion' => 'Corte bordado con nanotecnología'],
    ['id' => 12, 'nombre' => 'Corte Puntas',                      'duracion_min' => 30,  'precio' => 5000,  'descripcion' => 'Corte de puntas'],
    ['id' => 13, 'nombre' => 'Abundancia',                        'duracion_min' => 15,  'precio' => 5000,  'descripcion' => 'Tratamiento de abundancia'],
    ['id' => 14, 'nombre' => 'Alisado Corto + Corte Bordado',     'duracion_min' => 120, 'precio' => 55000, 'descripcion' => 'Combo alisado corto con corte'],
    ['id' => 15, 'nombre' => 'Alisado Medio + Corte Bordado',     'duracion_min' => 240, 'precio' => 65000, 'descripcion' => 'Combo alisado medio con corte'],
    ['id' => 16, 'nombre' => 'Alisado Largo + Corte Bordado',     'duracion_min' => 300, 'precio' => 75000, 'descripcion' => 'Combo alisado largo con corte'],
    ['id' => 17, 'nombre' => 'Botox Corto + Corte Bordado',       'duracion_min' => 120, 'precio' => 45000, 'descripcion' => 'Combo botox corto con corte'],
    ['id' => 18, 'nombre' => 'Botox Medio + Corte Bordado',       'duracion_min' => 240, 'precio' => 55000, 'descripcion' => 'Combo botox medio con corte'],
    ['id' => 19, 'nombre' => 'Botox Largo + Corte Bordado',       'duracion_min' => 300, 'precio' => 65000, 'descripcion' => 'Combo botox largo con corte'],
    ['id' => 20, 'nombre' => 'Masaje Capilar + Secado',           'duracion_min' => 120, 'precio' => 25000, 'descripcion' => 'Masaje con secado, planchado u ondas'],
    ['id' => 21, 'nombre' => 'Alisado Corto + Hidrat. + Corte',   'duracion_min' => 120, 'precio' => 65000, 'descripcion' => 'Combo completo alisado corto con hidratación'],
];

// ── 4. Bloqueos de Horario (JSON array) ──────────────────────────────────────
$bloqueos_horario = [
    ['fecha' => '*',          'hora_inicio' => '13:00', 'hora_fin' => '14:00', 'motivo' => 'Almuerzo',           'recurrente' => 'diario'],
    ['fecha' => '2026-01-01', 'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'motivo' => 'Año Nuevo',          'recurrente' => 'anual'],
    ['fecha' => '2026-05-01', 'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'motivo' => 'Día del Trabajo',    'recurrente' => 'anual'],
    ['fecha' => '2026-12-25', 'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'motivo' => 'Navidad',            'recurrente' => 'anual'],
    ['fecha' => '2026-04-15', 'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'motivo' => 'No disponible',      'recurrente' => 'no'],
];

// ── 5. Merge new fields into prompt_data ─────────────────────────────────────
$prompt_data['catalogo_servicios_detallado'] = wp_json_encode($catalogo_servicios, JSON_UNESCAPED_UNICODE);

// Scheduling definitions
$prompt_data['horario_inicio']     = '9:00';
$prompt_data['horario_fin']        = '18:00';
$prompt_data['dias_habiles']       = '1,2,3,4,5';
$prompt_data['intervalo_slots']    = '60';
$prompt_data['buffer_entre_citas'] = '10';
$prompt_data['moneda_codigo']      = 'CLP';
$prompt_data['moneda_simbolo']     = '$';
$prompt_data['moneda_nombre']      = 'Pesos Chilenos';

// Schedule blocks
$prompt_data['bloqueos_horario'] = wp_json_encode($bloqueos_horario, JSON_UNESCAPED_UNICODE);

// ── 6. Update record ──────────────────────────────────────────────────────────
$result = $wpdb->update(
    $prefix . 'prompt_configs',
    [
        'prompt_data' => wp_json_encode($prompt_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'version'     => intval($config['version']) + 1,
        'updated_by'  => 1,
        'updated_at'  => current_time('mysql'),
    ],
    ['id' => $config['id']]
);

if ($result === false) {
    echo '<pre style="color:red">ERROR al actualizar: ' . esc_html($wpdb->last_error) . '</pre>';
} else {
    $new_version = intval($config['version']) + 1;
    echo '<pre style="color:green">✅ Configuración actualizada correctamente a v' . $new_version . '</pre>';
    echo '<pre>ID: ' . $config['id'] . '</pre>';
    echo '<pre>Servicios insertados: ' . count($catalogo_servicios) . '</pre>';
    echo '<pre>Bloqueos insertados: '  . count($bloqueos_horario)   . '</pre>';
    echo '<pre>Campos agenda añadidos: horario_inicio, horario_fin, dias_habiles, intervalo_slots, buffer_entre_citas, moneda_codigo, moneda_simbolo, moneda_nombre</pre>';
    echo '<pre style="color:orange">⚠️  ELIMINA ESTE ARCHIVO DEL SERVIDOR UNA VEZ EJECUTADO.</pre>';
}
