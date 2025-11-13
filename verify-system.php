<?php
/**
 * Script de Verificación Pre y Post Despliegue
 * Ejecutar ANTES y DESPUÉS de aplicar cambios en producción
 */

require_once('wp-load.php');

global $wpdb;

// Configuración
$is_production = (strpos(home_url(), 'localhost') === false);
$env_name = $is_production ? '🌐 PRODUCCIÓN' : '💻 LOCAL';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Sistema Multi-Moneda</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #0096C7, #00BFB3);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,150,199,0.3);
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 16px; }
        .env-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-weight: 600;
            margin-top: 10px;
        }
        .section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .section h2 {
            color: #0096C7;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3f2fd;
            font-size: 24px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #ddd;
            background: #fafafa;
            border-radius: 4px;
        }
        .check-item.success { border-color: #4caf50; background: #f1f8f4; }
        .check-item.warning { border-color: #ff9800; background: #fff8f0; }
        .check-item.error { border-color: #f44336; background: #ffebee; }
        .check-item .icon {
            font-size: 28px;
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }
        .check-item .content { flex: 1; }
        .check-item .label { font-weight: 600; margin-bottom: 5px; font-size: 16px; }
        .check-item .detail { color: #666; font-size: 14px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #0096C7;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:hover td { background: #f5f5f5; }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card.success { background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); }
        .summary-card.warning { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); }
        .summary-card.error { background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); }
        .summary-card .number { font-size: 48px; font-weight: bold; margin: 10px 0; }
        .summary-card .label { opacity: 0.9; font-size: 14px; text-transform: uppercase; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0096C7;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 5px;
            transition: background 0.3s;
        }
        .btn:hover { background: #007a9a; }
        .btn.secondary { background: #00BFB3; }
        .btn.secondary:hover { background: #009688; }
        .code-block {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Verificación Sistema Multi-Moneda</h1>
            <p>Diagnóstico completo de la implementación</p>
            <div class="env-badge"><?php echo $env_name; ?></div>
        </div>

<?php
// ============================================
// 1. VERIFICAR ESTRUCTURA DE BASE DE DATOS
// ============================================
$table_clients = $wpdb->prefix . 'automatiza_tech_clients';
$table_services = $wpdb->prefix . 'automatiza_services';

// Verificar si existe columna country
$column_exists = $wpdb->get_results($wpdb->prepare(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = %s 
    AND TABLE_NAME = %s 
    AND COLUMN_NAME = 'country'",
    DB_NAME,
    $table_clients
));

echo '<div class="section">';
echo '<h2>📊 1. Estructura de Base de Datos</h2>';

if (!empty($column_exists)) {
    echo '<div class="check-item success">';
    echo '<div class="icon">✅</div>';
    echo '<div class="content">';
    echo '<div class="label">Campo "country" existe</div>';
    echo '<div class="detail">La columna country está presente en la tabla ' . $table_clients . '</div>';
    echo '</div></div>';
} else {
    echo '<div class="check-item error">';
    echo '<div class="icon">❌</div>';
    echo '<div class="content">';
    echo '<div class="label">Campo "country" NO existe</div>';
    echo '<div class="detail">Debe ejecutar la migración SQL antes de continuar</div>';
    echo '</div></div>';
    echo '<div class="code-block">mysql -u usuario -p nombre_bd < sql/migration-production-multi-currency.sql</div>';
}

echo '</div>';

// ============================================
// 2. VERIFICAR CLIENTES CON PAÍS
// ============================================
if (!empty($column_exists)) {
    $total_clients = $wpdb->get_var("SELECT COUNT(*) FROM {$table_clients}");
    $clients_with_country = $wpdb->get_var("SELECT COUNT(*) FROM {$table_clients} WHERE country IS NOT NULL AND country != ''");
    $clients_without_country = $total_clients - $clients_with_country;

    $country_distribution = $wpdb->get_results("
        SELECT 
            country,
            COUNT(*) as total,
            ROUND(COUNT(*) * 100.0 / {$total_clients}, 2) as percentage
        FROM {$table_clients}
        WHERE country IS NOT NULL AND country != ''
        GROUP BY country
        ORDER BY total DESC
    ");

    echo '<div class="section">';
    echo '<h2>👥 2. Clientes por País</h2>';
    
    echo '<div class="summary">';
    echo '<div class="summary-card success">';
    echo '<div class="number">' . $total_clients . '</div>';
    echo '<div class="label">Total Clientes</div>';
    echo '</div>';
    
    echo '<div class="summary-card ' . ($clients_with_country == $total_clients ? 'success' : 'warning') . '">';
    echo '<div class="number">' . $clients_with_country . '</div>';
    echo '<div class="label">Con País Asignado</div>';
    echo '</div>';
    
    if ($clients_without_country > 0) {
        echo '<div class="summary-card error">';
        echo '<div class="number">' . $clients_without_country . '</div>';
        echo '<div class="label">Sin País</div>';
        echo '</div>';
    }
    echo '</div>';

    if ($clients_without_country > 0) {
        echo '<div class="check-item error">';
        echo '<div class="icon">⚠️</div>';
        echo '<div class="content">';
        echo '<div class="label">Hay clientes sin país asignado</div>';
        echo '<div class="detail">Ejecutar: UPDATE ' . $table_clients . ' SET country = \'CL\' WHERE country IS NULL;</div>';
        echo '</div></div>';
    }

    if (!empty($country_distribution)) {
        echo '<table>';
        echo '<thead><tr><th>País</th><th>Código</th><th>Total</th><th>%</th><th>Moneda</th><th>IVA</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($country_distribution as $row) {
            $country_names = array(
                'CL' => '🇨🇱 Chile',
                'US' => '🇺🇸 Estados Unidos',
                'AR' => '🇦🇷 Argentina',
                'CO' => '🇨🇴 Colombia',
                'MX' => '🇲🇽 México',
                'PE' => '🇵🇪 Perú',
                'ES' => '🇪🇸 España',
                'BR' => '🇧🇷 Brasil',
            );
            
            $country_name = isset($country_names[$row->country]) ? $country_names[$row->country] : "🌎 {$row->country}";
            $currency = ($row->country === 'CL') ? 'CLP' : 'USD';
            $iva = ($row->country === 'CL') ? '✅ 19%' : '❌ No aplica';
            
            echo "<tr>";
            echo "<td>{$country_name}</td>";
            echo "<td style='font-family:monospace;'>{$row->country}</td>";
            echo "<td><strong>{$row->total}</strong></td>";
            echo "<td>{$row->percentage}%</td>";
            echo "<td><strong>{$currency}</strong></td>";
            echo "<td>{$iva}</td>";
            echo "</tr>";
        }
        
        echo '</tbody></table>';
    }
    
    echo '</div>';
}

// ============================================
// 3. VERIFICAR SERVICIOS CON PRECIOS
// ============================================
$total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status = 'active'");
$services_with_clp = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status = 'active' AND price_clp > 0");
$services_with_usd = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status = 'active' AND price_usd > 0");
$services_with_both = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status = 'active' AND price_clp > 0 AND price_usd > 0");

$services_missing_usd = $wpdb->get_results("
    SELECT id, name, price_clp, price_usd
    FROM {$table_services}
    WHERE status = 'active'
    AND (price_usd IS NULL OR price_usd = 0)
");

echo '<div class="section">';
echo '<h2>💰 3. Servicios y Precios</h2>';

echo '<div class="summary">';
echo '<div class="summary-card">';
echo '<div class="number">' . $total_services . '</div>';
echo '<div class="label">Servicios Activos</div>';
echo '</div>';

echo '<div class="summary-card ' . ($services_with_clp == $total_services ? 'success' : 'error') . '">';
echo '<div class="number">' . $services_with_clp . '</div>';
echo '<div class="label">Con Precio CLP</div>';
echo '</div>';

echo '<div class="summary-card ' . ($services_with_usd == $total_services ? 'success' : 'error') . '">';
echo '<div class="number">' . $services_with_usd . '</div>';
echo '<div class="label">Con Precio USD</div>';
echo '</div>';

echo '<div class="summary-card ' . ($services_with_both == $total_services ? 'success' : 'warning') . '">';
echo '<div class="number">' . $services_with_both . '</div>';
echo '<div class="label">Con Ambos Precios</div>';
echo '</div>';
echo '</div>';

if (!empty($services_missing_usd)) {
    echo '<div class="check-item warning">';
    echo '<div class="icon">⚠️</div>';
    echo '<div class="content">';
    echo '<div class="label">Servicios sin precio USD</div>';
    echo '<div class="detail">Los siguientes servicios necesitan precio en dólares:</div>';
    echo '</div></div>';
    
    echo '<table>';
    echo '<thead><tr><th>ID</th><th>Servicio</th><th>Precio CLP</th><th>Precio USD</th><th>Acción</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($services_missing_usd as $service) {
        $suggested_usd = round($service->price_clp / 875, 2); // Tasa ejemplo
        echo "<tr>";
        echo "<td>{$service->id}</td>";
        echo "<td>{$service->name}</td>";
        echo "<td>\${$service->price_clp}</td>";
        echo "<td style='color:#f44336;'>❌ Sin precio</td>";
        echo "<td><code style='font-size:11px;'>UPDATE {$table_services} SET price_usd = {$suggested_usd} WHERE id = {$service->id};</code></td>";
        echo "</tr>";
    }
    
    echo '</tbody></table>';
}

echo '</div>';

// ============================================
// 4. VERIFICAR CONFIGURACIÓN DE LA EMPRESA
// ============================================
echo '<div class="section">';
echo '<h2>🏢 4. Configuración de Datos de Empresa</h2>';

$company_settings = array(
    'company_name' => get_option('company_name', ''),
    'company_rut' => get_option('company_rut', ''),
    'company_giro' => get_option('company_giro', ''),
    'company_address' => get_option('company_address', ''),
    'company_email' => get_option('company_email', ''),
    'company_phone' => get_option('company_phone', ''),
    'company_website' => get_option('company_website', '')
);

$settings_configured = 0;
foreach ($company_settings as $value) {
    if (!empty($value)) $settings_configured++;
}

if ($settings_configured === 7) {
    echo '<div class="check-item success">';
    echo '<div class="icon">✅</div>';
    echo '<div class="content">';
    echo '<div class="label">Todos los datos de empresa están configurados</div>';
    echo '<div class="detail">7 de 7 campos completados</div>';
    echo '</div></div>';
    
    echo '<table>';
    echo '<thead><tr><th>Campo</th><th>Valor Configurado</th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td>Nombre</td><td>' . esc_html($company_settings['company_name']) . '</td></tr>';
    echo '<tr><td>RUT</td><td>' . esc_html($company_settings['company_rut']) . '</td></tr>';
    echo '<tr><td>Giro</td><td>' . esc_html($company_settings['company_giro']) . '</td></tr>';
    echo '<tr><td>Dirección</td><td>' . esc_html($company_settings['company_address']) . '</td></tr>';
    echo '<tr><td>Email</td><td>' . esc_html($company_settings['company_email']) . '</td></tr>';
    echo '<tr><td>Teléfono</td><td>' . esc_html($company_settings['company_phone']) . '</td></tr>';
    echo '<tr><td>Sitio Web</td><td>' . esc_html($company_settings['company_website']) . '</td></tr>';
    echo '</tbody></table>';
} else {
    echo '<div class="check-item warning">';
    echo '<div class="icon">⚠️</div>';
    echo '<div class="content">';
    echo '<div class="label">Faltan datos de empresa por configurar</div>';
    echo '<div class="detail">' . $settings_configured . ' de 7 campos completados. Ir a: <a href="' . admin_url('admin.php?page=automatiza-invoice-settings') . '">Panel de Configuración</a></div>';
    echo '</div></div>';
}

echo '</div>';

// ============================================
// 5. VERIFICAR SISTEMA DE EMAILS
// ============================================
echo '<div class="section">';
echo '<h2>📧 5. Sistema de Emails</h2>';

// Verificar métodos de email en contact-form.php
$contact_form_file = get_template_directory() . '/inc/contact-form.php';
$email_methods_exist = false;
$smtp_configured = false;

if (file_exists($contact_form_file)) {
    $content = file_get_contents($contact_form_file);
    $email_methods_exist = (
        strpos($content, 'send_notification_email') !== false &&
        strpos($content, 'send_contracted_client_email') !== false &&
        strpos($content, 'send_invoice_email_to_client') !== false
    );
    $smtp_configured = strpos($content, 'configure_smtp') !== false;
}

if ($email_methods_exist) {
    echo '<div class="check-item success">';
    echo '<div class="icon">✅</div>';
    echo '<div class="content">';
    echo '<div class="label">Sistema de emails implementado</div>';
    echo '<div class="detail">3 tipos de emails configurados:</div>';
    echo '<ul style="margin-top:10px;">';
    echo '<li>• Email de notificación interna al recibir contacto</li>';
    echo '<li>• Email al cliente con factura PDF adjunta</li>';
    echo '<li>• Email de notificación interna al contratar cliente</li>';
    echo '</ul>';
    echo '</div></div>';
} else {
    echo '<div class="check-item error">';
    echo '<div class="icon">❌</div>';
    echo '<div class="content">';
    echo '<div class="label">Sistema de emails NO configurado</div>';
    echo '<div class="detail">Falta archivo contact-form.php actualizado</div>';
    echo '</div></div>';
}

if ($smtp_configured) {
    echo '<div class="check-item success">';
    echo '<div class="icon">✅</div>';
    echo '<div class="content">';
    echo '<div class="label">Configuración SMTP presente</div>';
    echo '<div class="detail">Método configure_smtp() detectado</div>';
    echo '</div></div>';
}

// Verificar carpeta de facturas
$invoices_dir = wp_upload_dir()['basedir'] . '/invoices';
if (file_exists($invoices_dir) && is_writable($invoices_dir)) {
    $invoice_count = count(glob($invoices_dir . '/*.pdf'));
    echo '<div class="check-item success">';
    echo '<div class="icon">✅</div>';
    echo '<div class="content">';
    echo '<div class="label">Carpeta de facturas configurada</div>';
    echo '<div class="detail">Ruta: ' . $invoices_dir . ' | Facturas: ' . $invoice_count . '</div>';
    echo '</div></div>';
} else {
    echo '<div class="check-item warning">';
    echo '<div class="icon">⚠️</div>';
    echo '<div class="content">';
    echo '<div class="label">Carpeta de facturas no existe o no es escribible</div>';
    echo '<div class="detail">Crear: mkdir -p ' . $invoices_dir . ' && chmod 755 ' . $invoices_dir . '</div>';
    echo '</div></div>';
}

echo '</div>';

// ============================================
// 6. VERIFICAR ARCHIVOS PHP
// ============================================
$required_files = array(
    'lib/invoice-pdf-fpdf.php' => get_template_directory() . '/lib/invoice-pdf-fpdf.php',
    'inc/contact-form.php' => get_template_directory() . '/inc/contact-form.php',
    'inc/invoice-settings.php' => get_template_directory() . '/inc/invoice-settings.php',
);

echo '<div class="section">';
echo '<h2>📁 6. Archivos PHP del Sistema</h2>';

foreach ($required_files as $name => $path) {
    if (file_exists($path)) {
        $file_size = filesize($path);
        $file_date = date('Y-m-d H:i:s', filemtime($path));
        
        // Verificar contenido específico
        $content = file_get_contents($path);
        $has_country_detection = (strpos($content, 'detect_country') !== false);
        $has_multi_currency = (strpos($content, 'currency') !== false);
        
        echo '<div class="check-item success">';
        echo '<div class="icon">✅</div>';
        echo '<div class="content">';
        echo '<div class="label">' . $name . '</div>';
        echo '<div class="detail">Tamaño: ' . number_format($file_size / 1024, 2) . ' KB | Modificado: ' . $file_date . '</div>';
        if ($has_country_detection || $has_multi_currency) {
            echo '<div class="detail" style="color:#4caf50;">✓ Contiene funcionalidad multi-moneda</div>';
        }
        echo '</div></div>';
    } else {
        echo '<div class="check-item error">';
        echo '<div class="icon">❌</div>';
        echo '<div class="content">';
        echo '<div class="label">' . $name . ' NO ENCONTRADO</div>';
        echo '<div class="detail">Ruta: ' . $path . '</div>';
        echo '</div></div>';
    }
}

echo '</div>';

// ============================================
// 7. PRUEBAS DE FUNCIONALIDAD
// ============================================
echo '<div class="section">';
echo '<h2>🧪 7. Pruebas de Funcionalidad</h2>';

echo '<div style="margin:20px 0;">';
echo '<a href="test-fpdf-invoice.php?country=CL" class="btn" target="_blank">📄 Probar Factura Chile (CLP)</a>';
echo '<a href="test-fpdf-invoice.php?country=US" class="btn secondary" target="_blank">📄 Probar Factura USA (USD)</a>';
echo '<a href="test-country-detection.php" class="btn" target="_blank">🔍 Ver Detección de País</a>';
echo '<a href="' . admin_url('admin.php?page=automatiza-invoice-settings') . '" class="btn secondary" target="_blank">⚙️ Panel Configuración</a>';
echo '</div>';

echo '</div>';

// ============================================
// 8. RESUMEN FINAL
// ============================================
$all_checks_passed = true;
$warnings = array();
$errors = array();

if (empty($column_exists)) {
    $all_checks_passed = false;
    $errors[] = 'Campo country no existe en la base de datos';
}

if (!empty($column_exists) && $clients_without_country > 0) {
    $warnings[] = $clients_without_country . ' clientes sin país asignado';
}

if (!empty($services_missing_usd)) {
    $warnings[] = count($services_missing_usd) . ' servicios sin precio USD';
}

foreach ($required_files as $name => $path) {
    if (!file_exists($path)) {
        $all_checks_passed = false;
        $errors[] = 'Archivo faltante: ' . $name;
    }
}

if ($settings_configured < 7) {
    $warnings[] = 'Faltan ' . (7 - $settings_configured) . ' datos de empresa por configurar';
}

if (!$email_methods_exist) {
    $all_checks_passed = false;
    $errors[] = 'Sistema de emails no implementado';
}

if (!file_exists($invoices_dir) || !is_writable($invoices_dir)) {
    $warnings[] = 'Carpeta de facturas no configurada o sin permisos';
}

echo '<div class="section">';
echo '<h2>📋 8. Resumen General</h2>';

if ($all_checks_passed && empty($warnings)) {
    echo '<div class="check-item success" style="padding:25px;">';
    echo '<div class="icon" style="font-size:48px;">🎉</div>';
    echo '<div class="content">';
    echo '<div class="label" style="font-size:24px;">¡Sistema listo para producción!</div>';
    echo '<div class="detail" style="font-size:16px;margin-top:10px;">Todas las verificaciones pasaron exitosamente. Puede proceder con el despliegue.</div>';
    echo '</div></div>';
} else {
    if (!empty($errors)) {
        echo '<div class="check-item error" style="padding:25px;">';
        echo '<div class="icon" style="font-size:48px;">❌</div>';
        echo '<div class="content">';
        echo '<div class="label" style="font-size:24px;">Errores críticos encontrados</div>';
        echo '<div class="detail" style="margin-top:10px;">';
        foreach ($errors as $error) {
            echo '• ' . $error . '<br>';
        }
        echo '</div></div></div>';
    }
    
    if (!empty($warnings)) {
        echo '<div class="check-item warning" style="padding:25px;">';
        echo '<div class="icon" style="font-size:48px;">⚠️</div>';
        echo '<div class="content">';
        echo '<div class="label" style="font-size:24px;">Advertencias</div>';
        echo '<div class="detail" style="margin-top:10px;">';
        foreach ($warnings as $warning) {
            echo '• ' . $warning . '<br>';
        }
        echo '</div></div></div>';
    }
}

echo '</div>';

?>

    </div>
</body>
</html>
