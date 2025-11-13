<?php
/**
 * Script de Prueba - Actualizador de Precios CLP
 * 
 * Ejecuta una actualización de prueba y muestra información detallada
 * 
 * Uso: Acceder desde el navegador a este archivo directamente
 * URL: http://localhost/automatiza-tech/test-currency-updater.php
 * 
 * @package AutomatizaTech
 * @version 1.0.0
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Verificar que el usuario sea administrador
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Debes ser administrador.');
}

// Título
echo '<html><head><meta charset="UTF-8"><title>Prueba Actualizador de Precios</title>';
echo '<style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
    .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    h1 { color: #1e3a8a; border-bottom: 3px solid #06d6a0; padding-bottom: 10px; }
    h2 { color: #1e3a8a; margin-top: 30px; }
    .info-box { background: #e3f2fd; padding: 15px; border-left: 4px solid #1976d2; margin: 15px 0; border-radius: 5px; }
    .success-box { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; border-radius: 5px; }
    .warning-box { background: #fff3cd; padding: 15px; border-left: 4px solid #f59e0b; margin: 15px 0; border-radius: 5px; }
    .error-box { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 15px 0; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th { background: #1e3a8a; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #ddd; }
    tr:nth-child(even) { background: #f9f9f9; }
    .highlight { background: #fff3cd !important; }
    .code { background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    .btn { display: inline-block; padding: 12px 24px; background: #06d6a0; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; font-weight: bold; }
    .btn:hover { background: #05c29a; }
    .btn-secondary { background: #1e3a8a; }
    .btn-secondary:hover { background: #162d6b; }
</style>
</head><body><div class="container">';

echo '<h1>🧪 Prueba del Actualizador Automático de Precios CLP</h1>';

// Información del sistema
echo '<div class="info-box">';
echo '<h3>ℹ️ Información del Sistema</h3>';
echo '<p><strong>Fecha actual:</strong> ' . date('d/m/Y H:i:s') . '</p>';
echo '<p><strong>Zona horaria:</strong> ' . date_default_timezone_get() . '</p>';
echo '<p><strong>WordPress versión:</strong> ' . get_bloginfo('version') . '</p>';
echo '</div>';

// Obtener instancia del updater
$updater = automatiza_tech_init_currency_updater();

echo '<h2>📊 Paso 1: Obtener Tipo de Cambio</h2>';
echo '<div class="info-box">';
echo '<p>Consultando APIs del Banco Central de Chile...</p>';
echo '</div>';

$exchange_rate = $updater->get_current_exchange_rate();

if ($exchange_rate && $exchange_rate > 0) {
    echo '<div class="success-box">';
    echo '<h3>✓ Tipo de Cambio Obtenido</h3>';
    echo '<p style="font-size: 1.5em; font-weight: bold; color: #06d6a0;">1 USD = $' . number_format($exchange_rate, 2) . ' CLP</p>';
    echo '<p><em>Fuente: Banco Central de Chile (mindicador.cl)</em></p>';
    echo '</div>';
} else {
    echo '<div class="error-box">';
    echo '<h3>✗ Error al Obtener Tipo de Cambio</h3>';
    echo '<p>No se pudo obtener el tipo de cambio. Verifica la conexión a internet.</p>';
    echo '</div>';
    echo '</div></body></html>';
    exit;
}

// Obtener servicios
global $wpdb;
$services_table = $wpdb->prefix . 'automatiza_services';
$services = $wpdb->get_results("
    SELECT id, name, price_usd, price_clp, status
    FROM {$services_table}
    ORDER BY id ASC
");

echo '<h2>📋 Paso 2: Servicios Actuales</h2>';

if (empty($services)) {
    echo '<div class="warning-box">';
    echo '<p>⚠️ No hay servicios registrados en la base de datos.</p>';
    echo '</div>';
} else {
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Nombre</th>';
    echo '<th style="text-align: right;">Precio USD</th>';
    echo '<th style="text-align: right;">Precio CLP Actual</th>';
    echo '<th style="text-align: right;">Precio CLP Estimado</th>';
    echo '<th style="text-align: right;">Diferencia</th>';
    echo '<th>Estado</th>';
    echo '</tr></thead><tbody>';
    
    $needs_update_count = 0;
    
    foreach ($services as $service) {
        $estimated_clp = round($service->price_usd * $exchange_rate / 1000) * 1000;
        $difference_amount = $estimated_clp - $service->price_clp;
        $difference_percent = $service->price_clp > 0 ? ($difference_amount / $service->price_clp * 100) : 0;
        $needs_update = abs($difference_percent) >= 2.0 || $service->price_clp == 0;
        
        if ($needs_update) $needs_update_count++;
        
        $row_class = $needs_update ? 'highlight' : '';
        
        echo '<tr class="' . $row_class . '">';
        echo '<td><strong>' . $service->id . '</strong></td>';
        echo '<td>' . esc_html($service->name) . '</td>';
        echo '<td style="text-align: right; font-weight: 600;">$' . number_format($service->price_usd, 2) . '</td>';
        echo '<td style="text-align: right; font-weight: 600;">$' . number_format($service->price_clp, 0) . '</td>';
        echo '<td style="text-align: right; font-weight: 600; color: ' . ($needs_update ? '#f59e0b' : '#06d6a0') . ';">$' . number_format($estimated_clp, 0) . '</td>';
        echo '<td style="text-align: right;">';
        
        if ($service->price_clp > 0) {
            $color = $difference_percent > 0 ? '#28a745' : '#dc3545';
            echo '<span style="color: ' . $color . '; font-weight: bold;">';
            echo ($difference_percent > 0 ? '+' : '') . number_format($difference_percent, 1) . '%';
            echo '</span>';
        } else {
            echo '<span style="color: #999;">N/A</span>';
        }
        
        echo '</td>';
        echo '<td>' . ($service->status === 'active' ? '✓ Activo' : '○ Inactivo') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    if ($needs_update_count > 0) {
        echo '<div class="warning-box">';
        echo '<p><strong>⚠️ ' . $needs_update_count . ' servicio(s) requieren actualización</strong></p>';
        echo '<p>Los servicios marcados en amarillo tienen una diferencia mayor al 2% o no tienen precio CLP definido.</p>';
        echo '</div>';
    } else {
        echo '<div class="success-box">';
        echo '<p><strong>✓ Todos los precios están actualizados</strong></p>';
        echo '<p>No hay servicios que requieran actualización en este momento.</p>';
        echo '</div>';
    }
}

// Ejecutar actualización de prueba
echo '<h2>🔄 Paso 3: Ejecutar Actualización</h2>';

if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
    echo '<div class="info-box">';
    echo '<p>⏳ Ejecutando actualización de precios...</p>';
    echo '</div>';
    
    $result = $updater->update_clp_prices();
    
    if ($result['success']) {
        echo '<div class="success-box">';
        echo '<h3>✓ Actualización Completada</h3>';
        echo '<p><strong>Servicios actualizados:</strong> ' . $result['updated'] . '</p>';
        echo '<p><strong>Tipo de cambio usado:</strong> $' . number_format($result['exchange_rate'], 2) . ' CLP</p>';
        
        if (!empty($result['details'])) {
            echo '<h4>Detalles de cambios:</h4>';
            echo '<table>';
            echo '<thead><tr><th>Servicio</th><th>USD</th><th>CLP Anterior</th><th>CLP Nuevo</th><th>Cambio</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($result['details'] as $detail) {
                $change_color = $detail['change_percent'] > 0 ? '#28a745' : '#dc3545';
                echo '<tr>';
                echo '<td><strong>' . esc_html($detail['name']) . '</strong></td>';
                echo '<td>$' . number_format($detail['usd'], 2) . '</td>';
                echo '<td>$' . number_format($detail['old_clp'], 0) . '</td>';
                echo '<td style="color: ' . $change_color . '; font-weight: bold;">$' . number_format($detail['new_clp'], 0) . '</td>';
                echo '<td style="color: ' . $change_color . ';">' . ($detail['change_percent'] > 0 ? '+' : '') . number_format($detail['change_percent'], 1) . '%</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        echo '</div>';
        
        echo '<div class="info-box">';
        echo '<p>✓ Los precios han sido actualizados exitosamente en la base de datos.</p>';
        echo '<p><a href="' . $_SERVER['PHP_SELF'] . '" class="btn btn-secondary">← Volver a Ver Estado Actual</a></p>';
        echo '</div>';
        
    } else {
        echo '<div class="error-box">';
        echo '<h3>✗ Error en la Actualización</h3>';
        echo '<p>' . esc_html($result['message']) . '</p>';
        echo '</div>';
    }
    
} else {
    echo '<div class="info-box">';
    echo '<p>La actualización NO se ha ejecutado aún. Esto es solo una simulación.</p>';
    echo '<p><a href="?execute=yes" class="btn">🚀 Ejecutar Actualización Ahora</a></p>';
    echo '</div>';
}

// Información sobre programación automática
echo '<h2>⏰ Paso 4: Programación Automática</h2>';

$info = $updater->get_last_update_info();

echo '<div class="info-box">';
echo '<h3>Configuración de Cron</h3>';

if ($info['next_scheduled']) {
    $next_date = new DateTime('@' . $info['next_scheduled']);
    $next_date->setTimezone(new DateTimeZone('America/Santiago'));
    
    echo '<p><strong>Estado:</strong> <span style="color: #28a745;">✓ Activo</span></p>';
    echo '<p><strong>Próxima ejecución:</strong> ' . $next_date->format('d/m/Y H:i:s') . ' (Chile)</p>';
    echo '<p><strong>Frecuencia:</strong> Diaria a las 8:00 AM</p>';
} else {
    echo '<p><strong>Estado:</strong> <span style="color: #dc3545;">✗ No programado</span></p>';
    echo '<p>El cron job no está programado. Se activará automáticamente al cargar WordPress.</p>';
}

if ($info['last_update'] !== 'Nunca') {
    $last_date = new DateTime($info['last_update']);
    echo '<p><strong>Última ejecución:</strong> ' . $last_date->format('d/m/Y H:i:s') . '</p>';
    echo '<p><strong>Servicios actualizados:</strong> ' . $info['updated_count'] . '</p>';
    echo '<p><strong>Tasa usada:</strong> $' . number_format($info['exchange_rate'], 2) . ' CLP</p>';
}

echo '</div>';

// Acceso al panel de admin
echo '<h2>🎛️ Panel de Administración</h2>';
echo '<div class="success-box">';
echo '<p>Puedes gestionar las actualizaciones desde el panel de WordPress:</p>';
echo '<p><a href="' . admin_url('admin.php?page=automatiza-tech-currency') . '" class="btn">📊 Ir al Panel de Precios CLP</a></p>';
echo '</div>';

// Instrucciones
echo '<h2>📖 Instrucciones de Uso</h2>';
echo '<div class="info-box">';
echo '<ol style="line-height: 1.8;">';
echo '<li><strong>Automático:</strong> El sistema se ejecuta diariamente a las 8:00 AM (hora de Chile)</li>';
echo '<li><strong>Manual:</strong> Puedes forzar una actualización desde el panel de admin de WordPress</li>';
echo '<li><strong>Umbral:</strong> Solo actualiza precios con cambios mayores al 2%</li>';
echo '<li><strong>Fuente:</strong> Banco Central de Chile (mindicador.cl) - Dólar observado</li>';
echo '<li><strong>Redondeo:</strong> Los precios se redondean a múltiplos de $1.000 CLP</li>';
echo '<li><strong>Fallback:</strong> Si falla la API principal, usa una alternativa automáticamente</li>';
echo '</ol>';
echo '</div>';

echo '<div class="success-box" style="margin-top: 30px;">';
echo '<h3>✓ Sistema Configurado Correctamente</h3>';
echo '<p>El actualizador automático está funcionando. Los precios se actualizarán automáticamente cada día.</p>';
echo '</div>';

echo '</div></body></html>';
