<?php
/**
 * Diagnóstico SMTP — Automatiza Tech
 *
 * Acceso: https://automatizatech.cl/smtp-diagnostic.php?key=AT_SMTP_2026&to=lgonzalez@automatizatech.cl
 *
 * Ejecuta 4 pruebas de envío, cada una más compleja que la anterior:
 *   1. Texto plano simple (sin HTML, sin adjunto)
 *   2. HTML simple (sin adjunto)
 *   3. HTML simple + PDF adjunto pequeño (genera uno on-the-fly)
 *   4. HTML completo + último PDF QA del proyecto (real, el que falla)
 *
 * El primero que falle = la causa raíz del 554.
 *
 * BORRAR ESTE ARCHIVO DESPUÉS DE USAR.
 */

require __DIR__ . '/wp-load.php';

// Seguridad mínima: clave en URL
if (!isset($_GET['key']) || $_GET['key'] !== 'AT_SMTP_2026') {
    http_response_code(403);
    exit('Forbidden');
}

if (!current_user_can('manage_options') && !isset($_GET['allow_anon'])) {
    // Permitir si está logueado como admin, o si pasa allow_anon (solo emergencia)
}

$to = sanitize_email($_GET['to'] ?? 'lgonzalez@automatizatech.cl');
if (!is_email($to)) {
    exit('Email destino inválido');
}

$from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>SMTP Diagnostic</title>';
echo '<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}';
echo '.test{margin:15px 0;padding:15px;border-left:4px solid #569cd6;background:#252526;}';
echo '.ok{border-color:#10b981;}.fail{border-color:#ef4444;}';
echo 'h1{color:#569cd6;}h2{color:#dcdcaa;margin:0 0 10px;}';
echo 'pre{background:#1a1a1a;padding:10px;overflow:auto;color:#ce9178;font-size:12px;}';
echo '.meta{color:#9cdcfe;font-size:13px;}</style></head><body>';

echo '<h1>SMTP Diagnostic — Automatiza Tech</h1>';
echo '<div class="meta">';
echo '<p><strong>Destino:</strong> ' . esc_html($to) . '</p>';
echo '<p><strong>From:</strong> ' . esc_html($from_email) . '</p>';
echo '<p><strong>SMTP_HOST:</strong> ' . (defined('SMTP_HOST') ? SMTP_HOST : '(no definido — fallback smtp.hostinger.com)') . '</p>';
echo '<p><strong>SMTP_PORT:</strong> ' . (defined('SMTP_PORT') ? SMTP_PORT : '(no definido — 587)') . '</p>';
echo '<p><strong>WP_DEBUG_LOG:</strong> ' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'SÍ' : 'NO') . '</p>';
echo '<p><strong>Fecha:</strong> ' . current_time('Y-m-d H:i:s') . '</p>';
echo '</div>';

// Capturar último error PHPMailer
$capture_error = function() {
    global $phpmailer;
    if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
        return $phpmailer->ErrorInfo;
    }
    return '(sin detalle PHPMailer)';
};

// Hook temporal para capturar wp_mail_failed
$last_error = '';
$capture_hook = function($wp_error) use (&$last_error) {
    $last_error = $wp_error->get_error_message();
    $data = $wp_error->get_error_data();
    if (is_array($data) && isset($data['phpmailer_exception_code'])) {
        $last_error .= ' (code=' . $data['phpmailer_exception_code'] . ')';
    }
};
add_action('wp_mail_failed', $capture_hook, 1);

function run_test($num, $title, $callback, &$last_error) {
    $last_error = '';
    echo '<div class="test ' . ($num ? '' : '') . '" id="t' . $num . '">';
    echo '<h2>Test ' . $num . ': ' . esc_html($title) . '</h2>';
    $start = microtime(true);
    try {
        $result = $callback();
    } catch (Throwable $e) {
        $result = false;
        $last_error = 'Exception: ' . $e->getMessage();
    }
    $elapsed = round((microtime(true) - $start) * 1000);

    if ($result) {
        echo '<p style="color:#10b981;"><strong>✓ ENVIADO OK</strong> (' . $elapsed . ' ms)</p>';
        echo '<script>document.getElementById("t' . $num . '").classList.add("ok");</script>';
        return true;
    } else {
        echo '<p style="color:#ef4444;"><strong>✗ FALLÓ</strong> (' . $elapsed . ' ms)</p>';
        echo '<pre>' . esc_html($last_error ?: '(sin detalle)') . '</pre>';
        echo '<script>document.getElementById("t' . $num . '").classList.add("fail");</script>';
        return false;
    }
}

// ─────────────────────────────────────────────────────
// TEST 1: Texto plano simple
// ─────────────────────────────────────────────────────
$ok1 = run_test(1, 'Texto plano simple (sin HTML, sin adjunto)', function() use ($to) {
    return wp_mail($to, 'AT Diagnostic 1 - Plain', 'Test 1 plain text body. Time: ' . time());
}, $last_error);

// ─────────────────────────────────────────────────────
// TEST 2: HTML simple sin adjunto
// ─────────────────────────────────────────────────────
$ok2 = run_test(2, 'HTML simple (sin adjunto)', function() use ($to, $from_email) {
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: AutomatizaTech <' . $from_email . '>'
    );
    $html = '<html><body><h2>Test 2</h2><p>HTML simple. Time: ' . time() . '</p></body></html>';
    return wp_mail($to, 'AT Diagnostic 2 - HTML', $html, $headers);
}, $last_error);

// ─────────────────────────────────────────────────────
// TEST 3: HTML + PDF pequeño generado on-the-fly
// ─────────────────────────────────────────────────────
$ok3 = run_test(3, 'HTML + PDF pequeño generado en vivo', function() use ($to, $from_email) {
    $fpdf_path = get_template_directory() . '/lib/fpdf.php';
    if (!file_exists($fpdf_path)) {
        global $last_error_ref;
        throw new Exception('FPDF no encontrado en ' . $fpdf_path);
    }
    require_once $fpdf_path;

    $upload = wp_upload_dir();
    $tmp_pdf = $upload['basedir'] . '/at-smtp-test-' . time() . '.pdf';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, 'Test SMTP - Automatiza Tech');
    $pdf->Ln(20);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, 'Este PDF fue generado on-the-fly para diagnostico SMTP. ' . date('Y-m-d H:i:s'));
    $pdf->Output('F', $tmp_pdf);

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: AutomatizaTech <' . $from_email . '>'
    );
    $html = '<html><body><h2>Test 3</h2><p>HTML + PDF chico. Tamaño: ' . filesize($tmp_pdf) . ' bytes</p></body></html>';
    $result = wp_mail($to, 'AT Diagnostic 3 - HTML+PDF small', $html, $headers, array($tmp_pdf));
    @unlink($tmp_pdf);
    return $result;
}, $last_error);

// ─────────────────────────────────────────────────────
// TEST 4: HTML + el último PDF QA real
// ─────────────────────────────────────────────────────
$ok4 = run_test(4, 'HTML + último PDF QA real (el que falla)', function() use ($to, $from_email) {
    $upload = wp_upload_dir();
    $qa_dir = $upload['basedir'] . '/qa-reports';
    if (!is_dir($qa_dir)) {
        throw new Exception('No existe carpeta qa-reports');
    }
    $pdfs = glob($qa_dir . '/*.pdf');
    if (empty($pdfs)) {
        throw new Exception('No hay PDFs QA generados aún');
    }
    // Más reciente
    usort($pdfs, function($a, $b) { return filemtime($b) - filemtime($a); });
    $real_pdf = $pdfs[0];

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: AutomatizaTech <' . $from_email . '>'
    );
    $html = '<html><body><h2>Test 4 — PDF QA REAL</h2>'
        . '<p>Archivo: ' . esc_html(basename($real_pdf)) . '</p>'
        . '<p>Tamaño: ' . number_format(filesize($real_pdf)) . ' bytes</p>'
        . '<p>Si este test falla y los anteriores pasan, el problema es el contenido específico de este PDF.</p>'
        . '</body></html>';
    return wp_mail($to, 'AT Diagnostic 4 - QA PDF real', $html, $headers, array($real_pdf));
}, $last_error);

remove_action('wp_mail_failed', $capture_hook, 1);

// ─────────────────────────────────────────────────────
// VEREDICTO
// ─────────────────────────────────────────────────────
echo '<div class="test" style="border-color:#dcdcaa;">';
echo '<h2>VEREDICTO</h2>';
if ($ok1 && $ok2 && $ok3 && $ok4) {
    echo '<p style="color:#10b981;font-size:18px;"><strong>✓ Todos los tests pasaron — SMTP funcional</strong></p>';
    echo '<p>Si el envío real desde el panel QA sigue fallando, el problema está en el HANDLER (headers, BCC, etc.), no en el SMTP base.</p>';
} elseif (!$ok1) {
    echo '<p style="color:#ef4444;font-size:18px;"><strong>✗ Falla desde el primer test (texto plano)</strong></p>';
    echo '<p><strong>Causa probable:</strong> Cuenta SMTP de Hostinger BLOQUEADA en hPanel.';
    echo ' Entra a hPanel → Email → Email Accounts → contacto@automatizatech.cl → revisa restricciones de envío y cuota diaria.</p>';
} elseif (!$ok2) {
    echo '<p style="color:#eab308;"><strong>HTML falla pero texto plano funciona</strong></p>';
    echo '<p>SpamAssassin rechaza HTML sin alternativa de texto plano.</p>';
} elseif (!$ok3) {
    echo '<p style="color:#eab308;"><strong>El PDF adjunto rompe el envío</strong></p>';
    echo '<p>Hostinger filtra emails con adjuntos PDF. Hay que usar enlace de descarga en lugar de adjuntar.</p>';
} elseif (!$ok4) {
    echo '<p style="color:#eab308;"><strong>El PDF QA REAL específico es el problema</strong></p>';
    echo '<p>El PDF generado por QAReportPDF tiene algo (tamaño, contenido, encoding) que dispara filtros. Hay que rebajar/limpiar el PDF o reemplazar por enlace de descarga.</p>';
}
echo '</div>';

echo '<p style="color:#888;margin-top:30px;font-size:11px;">⚠️ BORRA ESTE ARCHIVO (smtp-diagnostic.php) cuando termines.</p>';
echo '</body></html>';
