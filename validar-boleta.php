<?php
/**
 * Sistema de Validación y Descarga de Boletas
 * URL: /validar-boleta.php
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require('wp-load.php');

require_once __DIR__ . '/at-rate-limit.php';
require_once __DIR__ . '/at-path-safe.php';

// Rate limit: 30 hits/min por IP (anti scraping)
if ( ! at_rate_limit_check( 'validar_boleta', 30, 60 ) ) {
    at_rate_limit_reject( 60 );
}

global $wpdb;

// Obtener ID (numero de boleta) desde la URL
$receipt_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

if (empty($receipt_id)) {
    wp_die('❌ Error: No se proporcionó un número de boleta válido.', 'Error de Validación', ['response' => 400]);
}

// Buscar la boleta
$table_name = $wpdb->prefix . 'automatiza_tech_receipts';

// Verificar si existe la tabla
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
if ( $table_exists !== $table_name ) {
    wp_die('❌ Error: Sistema de boletas no inicializado.', 'Error', ['response' => 500]);
}

$receipt = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE receipt_number = %s AND status = 'active'",
    $receipt_id
));

if (!$receipt) {
    wp_die('❌ Error: Boleta no encontrada o inválida.', 'Boleta No Encontrada', ['response' => 404]);
}

// ---- A2.1 IDOR fix: lazy download_token migration ----
$col_exists_r = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'download_token'" );
if ( ! $col_exists_r ) {
    $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `download_token` VARCHAR(64) DEFAULT NULL" );
    $wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX `idx_receipt_dl_token` (`download_token`)" );
}
if ( empty( $receipt->download_token ) ) {
    $new_token_r = bin2hex( random_bytes( 24 ) );
    $wpdb->update( $table_name, ['download_token' => $new_token_r], ['id' => $receipt->id], ['%s'], ['%d'] );
    $receipt->download_token = $new_token_r;
}
// ---- end A2.1 ----

// Determinar la acción (validar o descargar)
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'validate';

if ($action === 'download') {
    // DESCARGAR BOLETA EN PDF

    // A2.1: Verificar download_token para evitar IDOR
    $submitted_token_r = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    $legacy_until_r    = defined('AT_INVOICE_LEGACY_UNTIL') ? strtotime( AT_INVOICE_LEGACY_UNTIL ) : strtotime('+30 days');
    if ( empty($submitted_token_r) ) {
        if ( time() < $legacy_until_r ) {
            error_log( '[AT-SECURITY] Legacy tokenless download: receipt=' . $receipt->receipt_number . ' IP=' . $_SERVER['REMOTE_ADDR'] );
        } else {
            wp_die( '❌ Acceso denegado: token de descarga inválido o expirado.', 'Error', ['response' => 403] );
        }
    } elseif ( ! hash_equals( (string) $receipt->download_token, $submitted_token_r ) ) {
        wp_die( '❌ Acceso denegado: token de descarga inválido.', 'Error', ['response' => 403] );
    }

    // Ruta del archivo PDF: validar que este dentro de uploads (anti path-traversal)
    $upload_dir   = wp_upload_dir();
    $receipts_dir = $upload_dir['basedir'] . '/automatiza-tech-receipts/';

    // Si pdf_path en BD esta corrupto o apunta fuera, reconstruir desde basename seguro.
    $candidate = (string) ( $receipt->pdf_path ?? '' );
    $pdf_file  = $candidate !== '' ? at_path_inside( $candidate, $receipts_dir ) : false;
    if ( $pdf_file === false ) {
        $safe = at_safe_basename( $receipt->receipt_number ) . '.pdf';
        $pdf_file = at_path_inside( $receipts_dir . $safe, $receipts_dir );
    }

    if ( $pdf_file === false || ! file_exists( $pdf_file ) || ! is_readable( $pdf_file ) ) {
        wp_die( '❌ No se puede acceder al archivo PDF. Contacta al administrador.', 'Error', [ 'response' => 500 ] );
    }
    
    // Actualizar fecha validacion
    $wpdb->update(
        $table_name,
        ['validated_at' => current_time('mysql')],
        ['id' => $receipt->id],
        ['%s'],
        ['%d']
    );
    
    // Enviar headers para descarga
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdf_file) . '"');
    header('Content-Length: ' . filesize($pdf_file));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Limpiar cualquier salida anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    flush();
    
    // Enviar el archivo
    readfile($pdf_file);
    exit;
    
} else {
    // MOSTRAR VISTA HTML DE VALIDACIÓN
    
    // Actualizar fecha de validación si es la primera vez
    if (empty($receipt->validated_at)) {
        $wpdb->update(
            $table_name,
            ['validated_at' => current_time('mysql')],
            ['id' => $receipt->id],
            ['%s'],
            ['%d']
        );
    }
    
    $items = json_decode($receipt->items_json);
    $items_html = '';
    if (is_array($items)) {
        foreach($items as $item) {
            // Manejar tanto array como objeto (por si acaso)
            $name = is_object($item) ? $item->name : $item['name'];
            $qty = is_object($item) ? $item->quantity : $item['quantity'];
            $price = is_object($item) ? $item->price : $item['price'];
            $items_html .= "<li>{$qty}x {$name} - $" . number_format($price * $qty, 0, ',', '.') . "</li>";
        }
    }
    
    // Devolver HTML bonito
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Validación de Boleta - AutomatizaTech</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 400px; width: 90%; text-align: center; }
            .success-icon { color: #00BFB3; font-size: 48px; margin-bottom: 1rem; }
            h1 { color: #333; margin: 0 0 0.5rem 0; font-size: 24px; }
            .subtitle { color: #666; margin-bottom: 1.5rem; }
            .details { text-align: left; background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
            .detail-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 14px; }
            .detail-label { color: #666; }
            .detail-value { font-weight: 600; color: #333; }
            .btn { display: inline-block; background: #0096C7; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 0.2s; border: none; cursor: pointer; width: 100%; box-sizing: border-box; }
            .btn:hover { background: #0077a3; }
            .items-list { margin-top: 10px; padding-left: 20px; font-size: 13px; color: #555; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="success-icon">✅</div>
            <h1>Documento Válido</h1>
            <p class="subtitle">La boleta electrónica es auténtica</p>
            
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Folio:</span>
                    <span class="detail-value"><?php echo esc_html($receipt->receipt_number); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fecha:</span>
                    <span class="detail-value"><?php echo date('d/m/Y', strtotime($receipt->created_at)); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Cliente:</span>
                    <span class="detail-value"><?php echo esc_html($receipt->client_name); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Monto Total:</span>
                    <span class="detail-value">$<?php echo number_format($receipt->total, 0, ',', '.'); ?></span>
                </div>
                
                <hr style="border: 0; border-top: 1px solid #eee; margin: 10px 0;">
                
                <div class="detail-row">
                    <span class="detail-label">Items:</span>
                </div>
                <ul class="items-list">
                    <?php echo $items_html; ?>
                </ul>
            </div>
            
            <a href="?id=<?php echo urlencode($receipt_id); ?>&token=<?php echo urlencode($receipt->download_token); ?>&action=download" class="btn">
                📥 Descargar Boleta PDF
            </a>
            
            <p style="margin-top: 20px; font-size: 12px; color: #999;">
                Validado el: <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>
    </body>
    </html>
    <?php
}
