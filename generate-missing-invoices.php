<?php
/**
 * Generar Facturas Faltantes
 * Genera facturas para todos los clientes que no tienen una
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea admin
if (!current_user_can('administrator')) {
    die('Solo administradores pueden ejecutar este script');
}

global $wpdb;

echo "<h1>🔧 Generación de Facturas Faltantes</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .ok { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .progress { background: #e9ecef; border-radius: 4px; height: 30px; margin: 10px 0; }
    .progress-bar { background: #007bff; height: 100%; border-radius: 4px; text-align: center; color: white; line-height: 30px; }
</style>";

// Verificar tabla de facturas
$invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$invoices_table}'");

if (!$table_exists) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ ERROR: La tabla de facturas no existe.</p>";
    echo "<p>Primero ejecuta el script de diagnóstico y crea la tabla.</p>";
    echo "</div>";
    exit;
}

// Verificar que exista el generador de facturas FPDF
$invoice_generator = get_template_directory() . '/lib/invoice-pdf-fpdf.php';
if (!file_exists($invoice_generator)) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ ERROR: No se encontró el generador de facturas FPDF.</p>";
    echo "<p>Ruta esperada: {$invoice_generator}</p>";
    echo "</div>";
    exit;
}

require_once($invoice_generator);

// Obtener todos los clientes
$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
$clients = $wpdb->get_results("SELECT * FROM {$clients_table} ORDER BY id ASC");

if (empty($clients)) {
    echo "<div class='section'>";
    echo "<p class='warning'>⚠️ No hay clientes contratados para generar facturas.</p>";
    echo "</div>";
    exit;
}

echo "<div class='section'>";
echo "<h2>📊 Resumen Inicial</h2>";
echo "<p>Total de clientes: <strong>" . count($clients) . "</strong></p>";
echo "</div>";

// Verificar directorio de facturas
$upload_dir = wp_upload_dir();
$invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';

if (!file_exists($invoices_dir)) {
    echo "<div class='section'>";
    echo "<p class='warning'>⚠️ Creando directorio de facturas...</p>";
    if (mkdir($invoices_dir, 0755, true)) {
        echo "<p class='ok'>✅ Directorio creado: {$invoices_dir}</p>";
    } else {
        echo "<p class='error'>❌ No se pudo crear el directorio. Verifica permisos.</p>";
        exit;
    }
    echo "</div>";
}

// Procesar cada cliente
echo "<div class='section'>";
echo "<h2>🔄 Generando Facturas</h2>";

$generated = 0;
$skipped = 0;
$errors = 0;

foreach ($clients as $index => $client) {
    $progress = round((($index + 1) / count($clients)) * 100);
    
    echo "<div style='margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;'>";
    echo "<h3>Cliente #{$client->id}: {$client->name}</h3>";
    
    // Generar número de factura
    $invoice_number = 'AT-' . date('Ymd', strtotime($client->contracted_at)) . '-' . str_pad($client->id, 4, '0', STR_PAD_LEFT);
    
    // Verificar si ya existe en BD
    $exists_in_db = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$invoices_table} WHERE invoice_number = %s",
        $invoice_number
    ));
    
    if ($exists_in_db) {
        echo "<p class='warning'>⏭️ La factura {$invoice_number} ya existe en BD (ID: {$exists_in_db}). Omitiendo...</p>";
        $skipped++;
    } else {
        echo "<p>📄 Generando factura: <strong>{$invoice_number}</strong></p>";
        
        // Obtener plan(es) del cliente
        $plans = [];
        if (!empty($client->plan_id)) {
            $plan_ids = explode(',', $client->plan_id);
            $services_table = $wpdb->prefix . 'automatiza_services';
            
            foreach ($plan_ids as $plan_id) {
                $plan = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$services_table} WHERE id = %d",
                    trim($plan_id)
                ));
                
                if ($plan) {
                    $plans[] = $plan;
                }
            }
        }
        
        if (empty($plans)) {
            echo "<p class='error'>⚠️ No se encontraron planes para este cliente. Usando plan por defecto.</p>";
            // Crear un plan por defecto
            $default_plan = new stdClass();
            $default_plan->name = 'Servicio Contratado';
            $default_plan->price_clp = $client->contract_value > 0 ? $client->contract_value : 0;
            $default_plan->price_usd = 0;
            $default_plan->description = 'Servicio de automatización';
            $plans[] = $default_plan;
        }
        
        // Preparar datos del cliente
        $client_data = [
            'name' => $client->name,
            'email' => $client->email,
            'rut' => $client->rut ?? '',
            'phone' => $client->phone ?? '',
            'address' => $client->address ?? '',
            'company' => $client->company ?? ''
        ];
        
        // Calcular totales
        $subtotal = 0;
        foreach ($plans as $plan) {
            $subtotal += floatval($plan->price_clp);
        }
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        // Generar PDF usando FPDF
        try {
            $pdf_result = generate_invoice_pdf_fpdf($client_data, $plans, $invoice_number);
            
            if ($pdf_result && isset($pdf_result['success']) && $pdf_result['success']) {
                echo "<p class='ok'>✅ PDF generado correctamente</p>";
                
                // Registrar en base de datos
                $insert_result = $wpdb->insert(
                    $invoices_table,
                    [
                        'invoice_number' => $invoice_number,
                        'client_name' => $client->name,
                        'client_email' => $client->email,
                        'client_rut' => $client->rut ?? '',
                        'client_phone' => $client->phone ?? '',
                        'client_address' => $client->address ?? '',
                        'subtotal' => $subtotal,
                        'iva' => $iva,
                        'total' => $total,
                        'status' => 'active',
                        'download_count' => 0,
                        'created_at' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s']
                );
                
                if ($insert_result) {
                    echo "<p class='ok'>✅ Factura registrada en base de datos (ID: {$wpdb->insert_id})</p>";
                    $generated++;
                } else {
                    echo "<p class='error'>❌ Error al registrar en BD: " . $wpdb->last_error . "</p>";
                    $errors++;
                }
            } else {
                $error_msg = isset($pdf_result['message']) ? $pdf_result['message'] : 'Error desconocido';
                echo "<p class='error'>❌ Error al generar PDF: {$error_msg}</p>";
                $errors++;
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Excepción: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
    
    // Barra de progreso
    echo "<div class='progress'>";
    echo "<div class='progress-bar' style='width: {$progress}%'>{$progress}%</div>";
    echo "</div>";
    
    echo "</div>";
    
    // Pausar un momento para no sobrecargar
    usleep(100000); // 0.1 segundos
}

echo "</div>";

// Resumen final
echo "<div class='section'>";
echo "<h2>📈 Resumen Final</h2>";
echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #007bff; color: white;'><th style='padding: 10px;'>Métrica</th><th style='padding: 10px;'>Cantidad</th></tr>";
echo "<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'>Total Clientes</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>" . count($clients) . "</strong></td></tr>";
echo "<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'>Facturas Generadas</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong class='ok'>" . $generated . "</strong></td></tr>";
echo "<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'>Omitidas (ya existían)</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong class='warning'>" . $skipped . "</strong></td></tr>";
echo "<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'>Errores</td><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong class='error'>" . $errors . "</strong></td></tr>";
echo "</table>";

if ($errors > 0) {
    echo "<p class='warning'>⚠️ Hubo {$errors} error(es). Revisa los detalles arriba.</p>";
} else if ($generated > 0) {
    echo "<p class='ok'>✅ Proceso completado exitosamente. Se generaron {$generated} factura(s).</p>";
} else {
    echo "<p class='warning'>ℹ️ No se generaron nuevas facturas (todas ya existían).</p>";
}

echo "<p><a href='" . admin_url('admin.php?page=automatiza-tech-clients') . "' class='button button-primary'>← Volver al Panel de Clientes</a></p>";
echo "</div>";
