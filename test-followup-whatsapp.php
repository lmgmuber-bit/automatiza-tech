<?php
/**
 * DIAGNÓSTICO: Test del webhook Followup_WhatsApp_Send en N8N
 * 
 * Ejecutar en PROD: https://automatizatech.cl/test-followup-whatsapp.php?meeting_id=38
 * O sin meeting_id para un test con datos dummy
 * 
 * ELIMINAR después de diagnosticar.
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Solo admin
if (!current_user_can('manage_options')) {
    die('❌ Acceso denegado. Debes estar logueado como admin.');
}

header('Content-Type: text/html; charset=UTF-8');
echo '<pre style="font-family: monospace; font-size: 14px; padding: 20px; max-width: 900px;">';
echo "🔍 <b>DIAGNÓSTICO WhatsApp Followup</b>\n";
echo str_repeat('=', 60) . "\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_followup_meetings';

// ==========================================================================
// 1. Verificar tabla y columnas
// ==========================================================================
echo "📋 <b>1. Verificando tabla y columnas...</b>\n";

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
if (!$table_exists) {
    echo "   ❌ Tabla '$table_name' NO EXISTE\n";
    die('</pre>');
}
echo "   ✅ Tabla '$table_name' existe\n";

$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
$col_names = array_map(function($c) { return $c->Field; }, $columns);
$required_cols = ['whatsapp_sent', 'google_event_id', 'recordatorio_8pm', 'recordatorio_8am', 'recordatorio_8pm_wa', 'recordatorio_8am_wa'];
foreach ($required_cols as $col) {
    echo "   " . (in_array($col, $col_names) ? '✅' : '❌') . " Columna '$col'\n";
}

// ==========================================================================
// 2. Obtener datos de la reunion
// ==========================================================================
echo "\n📋 <b>2. Datos de reunión...</b>\n";

$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;
if ($meeting_id > 0) {
    $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
} else {
    // Última reunión
    $meeting = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
}

if (!$meeting) {
    echo "   ❌ No se encontró reunión" . ($meeting_id ? " ID #{$meeting_id}" : "") . "\n";
    die('</pre>');
}

echo "   📌 Meeting ID: {$meeting->id}\n";
echo "   👤 Cliente: {$meeting->client_name}\n";
echo "   📧 Email: {$meeting->client_email}\n";
echo "   📱 Teléfono (BD): '{$meeting->phone}'\n";
echo "   📅 Fecha: {$meeting->meeting_date}\n";
echo "   ⏰ Hora: {$meeting->meeting_time}\n";
echo "   🔗 Meet: " . ($meeting->meet_link ?: '(vacío)') . "\n";
echo "   📊 Status: {$meeting->status}\n";
echo "   📧 Email sent: " . (isset($meeting->email_sent) ? $meeting->email_sent : 'N/A') . "\n";
echo "   💬 WA sent: " . (isset($meeting->whatsapp_sent) ? $meeting->whatsapp_sent : 'N/A') . "\n";

// ==========================================================================
// 3. Procesar teléfono
// ==========================================================================
echo "\n📋 <b>3. Procesando teléfono...</b>\n";

$phone_raw = trim($meeting->phone ?? '');
$phone_digits = preg_replace('/[^0-9]/', '', $phone_raw);
echo "   📱 Raw: '{$phone_raw}'\n";
echo "   📱 Digits: '{$phone_digits}' (length: " . strlen($phone_digits) . ")\n";

if (strlen($phone_digits) < 8) {
    echo "   ❌ Teléfono inválido: menos de 8 dígitos\n";
    die('</pre>');
}

if (strlen($phone_digits) === 9 && substr($phone_digits, 0, 1) === '9') {
    $phone_digits = '56' . $phone_digits;
    echo "   🔧 Prefijo +56 agregado: '{$phone_digits}'\n";
}

$phone_final = '+' . $phone_digits;
echo "   ✅ Teléfono final para WhatsApp: <b>{$phone_final}</b>\n";

// ==========================================================================
// 4. Generar ficha URL
// ==========================================================================
echo "\n📋 <b>4. Generando ficha URL...</b>\n";

$ficha_url = '';
if (!empty($meeting->client_email)) {
    $tabla_clientes = $wpdb->prefix . 'crm_clientes';
    $cliente_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, email FROM $tabla_clientes WHERE email = %s LIMIT 1",
        $meeting->client_email
    ));
    if ($cliente_row) {
        $token = md5($cliente_row->id . 'AUTOMATIZA_CRM_V2' . $cliente_row->email);
        $ficha_url = 'https://automatizatech.cl/?crm_view=timeline&cid=' . $cliente_row->id . '&token=' . $token;
        echo "   ✅ Ficha URL: {$ficha_url}\n";
    } else {
        echo "   ⚠️ Cliente no encontrado en tabla crm_clientes por email\n";
    }
} else {
    echo "   ⚠️ Sin email de cliente\n";
}

// ==========================================================================
// 5. Construir payload
// ==========================================================================
$days_es = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
$months_es = array('', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
$day_num = date('w', strtotime($meeting->meeting_date));
$month_num = date('n', strtotime($meeting->meeting_date));
$day = date('d', strtotime($meeting->meeting_date));
$year = date('Y', strtotime($meeting->meeting_date));
$formatted_date = $days_es[$day_num] . ' ' . $day . ' de ' . $months_es[$month_num] . ' de ' . $year;
$formatted_time = substr($meeting->meeting_time, 0, 5);

$payload = array(
    'action' => 'send_followup_whatsapp',
    'meeting_id' => (int)$meeting->id,
    'phone' => $phone_final,
    'client_name' => $meeting->client_name,
    'client_email' => $meeting->client_email,
    'company_name' => $meeting->company_name ?? '',
    'meeting_date' => $meeting->meeting_date,
    'meeting_time' => $meeting->meeting_time,
    'formatted_date' => $formatted_date,
    'formatted_time' => $formatted_time,
    'meeting_subject' => $meeting->meeting_subject ?? 'Reunión de Seguimiento',
    'meet_link' => $meeting->meet_link ?? '',
    'notes' => $meeting->notes ?? '',
    'source' => 'diagnostic_test',
    'ficha_url' => $ficha_url,
);

echo "\n📋 <b>5. Payload a enviar:</b>\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

// ==========================================================================
// 6. Verificar si se debe enviar (solo con parámetro &send=1)
// ==========================================================================
if (!isset($_GET['send']) || $_GET['send'] !== '1') {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "⚠️ <b>MODO DIAGNÓSTICO SOLAMENTE</b>\n";
    echo "Para enviar el WhatsApp real, agrega <b>&send=1</b> a la URL:\n";
    $url = "test-followup-whatsapp.php?meeting_id={$meeting->id}&send=1";
    echo "<a href='{$url}'>{$url}</a>\n";
    echo '</pre>';
    exit;
}

// ==========================================================================
// 7. Llamar al webhook de N8N
// ==========================================================================
echo "\n📋 <b>6. Llamando webhook N8N...</b>\n";

$n8n_webhook_url = 'https://n8n-n8n.kchiba.easypanel.host/webhook/followup-whatsapp';
echo "   🌐 URL: {$n8n_webhook_url}\n";
echo "   ⏳ Enviando...\n";

$start_time = microtime(true);

$response = wp_remote_post($n8n_webhook_url, array(
    'method' => 'POST',
    'timeout' => 30,
    'headers' => array(
        'Content-Type' => 'application/json',
    ),
    'body' => json_encode($payload)
));

$elapsed = round((microtime(true) - $start_time) * 1000);

if (is_wp_error($response)) {
    echo "   ❌ <b>ERROR DE CONEXIÓN:</b> " . $response->get_error_message() . "\n";
    echo "   ⏱️ Tiempo: {$elapsed}ms\n";
    echo "\n🔧 <b>POSIBLES CAUSAS:</b>\n";
    echo "   • El servidor N8N no está accesible\n";
    echo "   • Problema de DNS o firewall\n";
    echo "   • Timeout (30 seg)\n";
    die('</pre>');
}

$response_code = wp_remote_retrieve_response_code($response);
$response_body = wp_remote_retrieve_body($response);
$response_headers = wp_remote_retrieve_headers($response);

echo "   📊 HTTP Status: <b>{$response_code}</b>\n";
echo "   ⏱️ Tiempo: {$elapsed}ms\n";
echo "   📦 Content-Type: " . ($response_headers['content-type'] ?? 'N/A') . "\n";
echo "   📦 Body length: " . strlen($response_body) . " bytes\n";
echo "\n   📦 <b>Response Body:</b>\n";

if (empty(trim($response_body))) {
    echo "   ⚠️ <b>BODY VACÍO!</b>\n";
    echo "\n🔧 <b>DIAGNÓSTICO: Body vacío con HTTP 200 significa:</b>\n";
    echo "   1️⃣ El workflow 'Followup_WhatsApp_Send' NO está ACTIVO en N8N\n";
    echo "      → Ve a: https://n8n-n8n.kchiba.easypanel.host/workflow/daSz1OJSeaQckDVy5q0uS\n";
    echo "      → Activa el toggle (debe estar verde)\n";
    echo "   2️⃣ El workflow está activo pero el Code node lanza error antes de responder\n";
    echo "      → Revisa los logs de ejecución en N8N\n";
    echo "   3️⃣ El workflow fue importado con un webhookId diferente\n";
    echo "      → Verifica que el nodo Webhook tenga path: 'followup-whatsapp'\n";
    echo "   4️⃣ Las credenciales 'WhatsApp Tech' no están configuradas\n";
    echo "      → Ve a Settings > Credentials en N8N\n";
} else {
    echo "   " . $response_body . "\n";
    
    $result = json_decode($response_body, true);
    if (is_array($result)) {
        if (!empty($result['success'])) {
            echo "\n   ✅ <b>WhatsApp ENVIADO correctamente!</b>\n";
            // Actualizar whatsapp_sent en BD
            $wpdb->update($table_name, ['whatsapp_sent' => 1], ['id' => $meeting->id]);
            echo "   ✅ BD actualizada: whatsapp_sent = 1\n";
        } else {
            echo "\n   ❌ <b>N8N reportó error:</b> " . ($result['message'] ?? 'Sin mensaje') . "\n";
        }
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "📝 También revisa el error_log de WordPress para más detalles.\n";
echo '</pre>';
