<?php
/**
 * Debug: Verificar por qué no se devuelven seguimientos para recordatorios
 */
require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_followup_meetings';

echo "<h2>🔍 Debug Recordatorios Seguimientos</h2>";
echo "<pre>";

// 1. Verificar estructura de la tabla
echo "=== 1. ESTRUCTURA DE LA TABLA ===\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
$column_names = array_map(function($col) { return $col->Field; }, $columns);
echo "Columnas: " . implode(", ", $column_names) . "\n\n";

// Verificar si existen las columnas de recordatorio
$required_columns = ['recordatorio_8pm', 'recordatorio_8am', 'recordatorio_8pm_wa', 'recordatorio_8am_wa'];
echo "Columnas de recordatorio:\n";
foreach ($required_columns as $col) {
    $exists = in_array($col, $column_names) ? '✅ Existe' : '❌ NO EXISTE';
    echo "  - $col: $exists\n";
}
echo "\n";

// 2. Calcular fecha de mañana
echo "=== 2. CÁLCULO DE FECHAS ===\n";

// Hora del servidor
echo "Hora servidor (PHP date): " . date('Y-m-d H:i:s') . "\n";

// Hora de Chile forzada
$chile_tz = new DateTimeZone('America/Santiago');
$now_chile = new DateTime('now', $chile_tz);
$tomorrow_chile = clone $now_chile;
$tomorrow_chile->modify('+1 day');

echo "Hora Chile (forzada): " . $now_chile->format('Y-m-d H:i:s') . "\n";
echo "Mañana Chile: " . $tomorrow_chile->format('Y-m-d') . "\n\n";

// 3. Ver todos los seguimientos programados
echo "=== 3. TODOS LOS SEGUIMIENTOS ===\n";
$all_meetings = $wpdb->get_results("SELECT id, client_name, phone, meeting_date, meeting_time, status FROM $table_name ORDER BY meeting_date DESC LIMIT 10");
if (empty($all_meetings)) {
    echo "❌ No hay seguimientos en la tabla\n";
} else {
    foreach ($all_meetings as $m) {
        echo "ID:{$m->id} | {$m->client_name} | Tel:{$m->phone} | {$m->meeting_date} {$m->meeting_time} | Status:{$m->status}\n";
    }
}
echo "\n";

// 4. Ver seguimientos de mañana
echo "=== 4. SEGUIMIENTOS DE MAÑANA ===\n";
$tomorrow_meetings = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name WHERE meeting_date = %s",
    date('Y-m-d', strtotime('+1 day'))
));
if (empty($tomorrow_meetings)) {
    echo "❌ No hay seguimientos programados para mañana (" . date('Y-m-d', strtotime('+1 day')) . ")\n";
} else {
    foreach ($tomorrow_meetings as $m) {
        echo "ID:{$m->id} | {$m->client_name} | Tel:" . ($m->phone ?: 'SIN TELÉFONO') . "\n";
        echo "  meeting_date: {$m->meeting_date}\n";
        echo "  meeting_time: {$m->meeting_time}\n";
        echo "  status: " . ($m->status ?: 'NULL') . "\n";
        
        // Verificar columnas de recordatorio si existen
        if (in_array('recordatorio_8pm_wa', $column_names)) {
            echo "  recordatorio_8pm_wa: " . (isset($m->recordatorio_8pm_wa) ? $m->recordatorio_8pm_wa : 'NULL') . "\n";
        }
    }
}
echo "\n";

// 5. Probar la query exacta del endpoint
echo "=== 5. QUERY EXACTA DEL ENDPOINT ===\n";

// Check si existen las columnas antes de incluirlas en la query
if (in_array('recordatorio_8pm_wa', $column_names)) {
    $query = $wpdb->prepare(
        "SELECT id, client_name as name, client_email as email, company_name, phone, 
                meeting_date, meeting_time, meet_link, meeting_subject, status,
                recordatorio_8pm, recordatorio_8am, recordatorio_8pm_wa, recordatorio_8am_wa
         FROM $table_name 
         WHERE CONCAT(meeting_date, ' ', meeting_time) BETWEEN %s AND %s 
         AND (recordatorio_8pm_wa IS NULL OR recordatorio_8pm_wa = 0)
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))
         AND phone IS NOT NULL AND phone != ''",
        $tomorrow_start, $tomorrow_end
    );
    echo "Query: " . $query . "\n\n";
    
    $results = $wpdb->get_results($query);
    echo "Resultados: " . count($results) . "\n";
    
    if (!empty($results)) {
        foreach ($results as $r) {
            print_r($r);
        }
    } else {
        echo "❌ Query no devuelve resultados\n";
        
        // Debug adicional - sin filtro de recordatorio
        echo "\n=== Query SIN filtro de recordatorio ===\n";
        $query2 = $wpdb->prepare(
            "SELECT id, client_name, phone, meeting_date, meeting_time, status
             FROM $table_name 
             WHERE CONCAT(meeting_date, ' ', meeting_time) BETWEEN %s AND %s",
            $tomorrow_start, $tomorrow_end
        );
        $results2 = $wpdb->get_results($query2);
        echo "Resultados sin filtro: " . count($results2) . "\n";
        foreach ($results2 as $r) {
            echo "ID:{$r->id} | {$r->client_name} | Tel:{$r->phone} | Status:{$r->status}\n";
        }
    }
} else {
    echo "❌ Columna 'recordatorio_8pm_wa' NO EXISTE - Debes ejecutar add-daily-reminder-columns.php primero\n";
}

echo "</pre>";
?>
