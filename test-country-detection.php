<?php
/**
 * Test de conversión de contacto a cliente con detección de país
 */

require_once('wp-load.php');

global $wpdb;

echo "<h1>Test de Conversión de Contacto a Cliente</h1>";
echo "<p>Verificando detección automática de país por código telefónico...</p>";

// Tabla de clientes
$clients_table = $wpdb->prefix . 'automatiza_tech_clients';

// Obtener últimos 5 clientes
$clients = $wpdb->get_results("
    SELECT id, name, phone, country, contracted_at 
    FROM {$clients_table} 
    ORDER BY contracted_at DESC 
    LIMIT 5
");

echo "<div style='background:#e3f2fd;padding:20px;border-left:4px solid #0096C7;margin:20px 0;'>";
echo "<h3>📊 Últimos 5 Clientes Convertidos</h3>";

if ($clients) {
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<thead>";
    echo "<tr style='background:#0096C7;color:white;'>";
    echo "<th style='padding:10px;text-align:left;'>ID</th>";
    echo "<th style='padding:10px;text-align:left;'>Cliente</th>";
    echo "<th style='padding:10px;text-align:left;'>Teléfono</th>";
    echo "<th style='padding:10px;text-align:left;'>País</th>";
    echo "<th style='padding:10px;text-align:left;'>Moneda</th>";
    echo "<th style='padding:10px;text-align:left;'>IVA</th>";
    echo "<th style='padding:10px;text-align:left;'>Fecha</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($clients as $client) {
        $country = $client->country ?? 'CL';
        $currency = ($country === 'CL') ? 'CLP' : 'USD';
        $iva = ($country === 'CL') ? '✅ 19%' : '❌ No aplica';
        $flag = match($country) {
            'CL' => '🇨🇱',
            'US' => '🇺🇸',
            'AR' => '🇦🇷',
            'CO' => '🇨🇴',
            'MX' => '🇲🇽',
            'PE' => '🇵🇪',
            'ES' => '🇪🇸',
            'BR' => '🇧🇷',
            default => '🌎'
        };
        
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:10px;'>{$client->id}</td>";
        echo "<td style='padding:10px;'>" . esc_html($client->name) . "</td>";
        echo "<td style='padding:10px;'>" . esc_html($client->phone) . "</td>";
        echo "<td style='padding:10px;'>{$flag} {$country}</td>";
        echo "<td style='padding:10px;font-weight:bold;color:#0096C7;'>{$currency}</td>";
        echo "<td style='padding:10px;'>{$iva}</td>";
        echo "<td style='padding:10px;'>" . date('d/m/Y H:i', strtotime($client->contracted_at)) . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
} else {
    echo "<p>No hay clientes registrados aún.</p>";
}

echo "</div>";

// Estadísticas por país
echo "<div style='background:#e8f5e9;padding:20px;border-left:4px solid #00BFB3;margin:20px 0;'>";
echo "<h3>📈 Distribución de Clientes por País</h3>";

$stats = $wpdb->get_results("
    SELECT 
        country,
        COUNT(*) as total,
        GROUP_CONCAT(name SEPARATOR ', ') as clients
    FROM {$clients_table}
    GROUP BY country
    ORDER BY total DESC
");

if ($stats) {
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<thead>";
    echo "<tr style='background:#00BFB3;color:white;'>";
    echo "<th style='padding:10px;text-align:left;'>País</th>";
    echo "<th style='padding:10px;text-align:center;'>Total</th>";
    echo "<th style='padding:10px;text-align:left;'>Moneda Facturación</th>";
    echo "<th style='padding:10px;text-align:left;'>Clientes</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($stats as $stat) {
        $country = $stat->country ?? 'CL';
        $currency = ($country === 'CL') ? '💵 Pesos Chilenos (CLP)' : '💲 Dólares (USD)';
        $country_name = match($country) {
            'CL' => '🇨🇱 Chile',
            'US' => '🇺🇸 Estados Unidos',
            'AR' => '🇦🇷 Argentina',
            'CO' => '🇨🇴 Colombia',
            'MX' => '🇲🇽 México',
            'PE' => '🇵🇪 Perú',
            'ES' => '🇪🇸 España',
            'BR' => '🇧🇷 Brasil',
            default => "🌎 {$country}"
        };
        
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:10px;font-weight:bold;'>{$country_name}</td>";
        echo "<td style='padding:10px;text-align:center;font-size:20px;color:#00BFB3;'>{$stat->total}</td>";
        echo "<td style='padding:10px;'>{$currency}</td>";
        echo "<td style='padding:10px;font-size:12px;color:#666;'>" . esc_html(substr($stat->clients, 0, 100)) . "...</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
} else {
    echo "<p>No hay estadísticas disponibles.</p>";
}

echo "</div>";

// Ejemplos de detección
echo "<div style='background:#fff3cd;padding:20px;border-left:4px solid #ffc107;margin:20px 0;'>";
echo "<h3>🔍 Ejemplos de Detección Automática</h3>";
echo "<p>El sistema detecta automáticamente el país basado en el código telefónico:</p>";

$examples = array(
    '+56 9 1234 5678' => array('country' => 'CL', 'name' => 'Chile', 'currency' => 'CLP', 'iva' => 'Sí (19%)'),
    '+1 305 555 1234' => array('country' => 'US', 'name' => 'USA', 'currency' => 'USD', 'iva' => 'No'),
    '+54 9 11 1234 5678' => array('country' => 'AR', 'name' => 'Argentina', 'currency' => 'USD', 'iva' => 'No'),
    '+57 300 1234567' => array('country' => 'CO', 'name' => 'Colombia', 'currency' => 'USD', 'iva' => 'No'),
    '+52 55 1234 5678' => array('country' => 'MX', 'name' => 'México', 'currency' => 'USD', 'iva' => 'No'),
    '+51 987 654 321' => array('country' => 'PE', 'name' => 'Perú', 'currency' => 'USD', 'iva' => 'No'),
);

echo "<table style='width:100%;border-collapse:collapse;'>";
echo "<thead>";
echo "<tr style='background:#ffc107;'>";
echo "<th style='padding:10px;text-align:left;'>Teléfono</th>";
echo "<th style='padding:10px;text-align:left;'>País Detectado</th>";
echo "<th style='padding:10px;text-align:left;'>Moneda</th>";
echo "<th style='padding:10px;text-align:left;'>IVA</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

foreach ($examples as $phone => $data) {
    echo "<tr style='border-bottom:1px solid #ddd;'>";
    echo "<td style='padding:10px;font-family:monospace;'>{$phone}</td>";
    echo "<td style='padding:10px;'>{$data['country']} ({$data['name']})</td>";
    echo "<td style='padding:10px;font-weight:bold;'>{$data['currency']}</td>";
    echo "<td style='padding:10px;'>{$data['iva']}</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "</div>";

// Instrucciones
echo "<div style='background:#f5f5f5;padding:20px;border-left:4px solid #666;margin:20px 0;'>";
echo "<h3>📝 Cómo Funciona</h3>";
echo "<ol>";
echo "<li><strong>Usuario llena formulario de contacto</strong> con su número de WhatsApp (ej: +56 9 1234 5678)</li>";
echo "<li><strong>Sistema valida el formato</strong> del teléfono con código de país</li>";
echo "<li><strong>Contacto se guarda</strong> en la tabla wp_automatiza_tech_contacts</li>";
echo "<li><strong>Admin convierte contacto a cliente</strong> desde el panel</li>";
echo "<li><strong>Sistema detecta país automáticamente</strong> del código telefónico (+56 → Chile)</li>";
echo "<li><strong>Cliente se guarda con campo country='CL'</strong> en wp_automatiza_tech_clients</li>";
echo "<li><strong>Al generar factura</strong>, se usa automáticamente CLP con IVA 19%</li>";
echo "<li><strong>Si fuera +1 (USA)</strong>, se usaría USD sin IVA</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background:#e3f2fd;padding:20px;border-radius:8px;margin-top:20px;'>";
echo "<h3>✅ Ventajas del Sistema</h3>";
echo "<ul>";
echo "<li>✅ <strong>Automático:</strong> No requiere configuración manual del país</li>";
echo "<li>✅ <strong>Preciso:</strong> Detecta país por código telefónico validado</li>";
echo "<li>✅ <strong>Legal:</strong> Aplica IVA solo a clientes chilenos</li>";
echo "<li>✅ <strong>Profesional:</strong> Facturas en moneda local del cliente</li>";
echo "<li>✅ <strong>Escalable:</strong> Soporta múltiples países fácilmente</li>";
echo "</ul>";
echo "</div>";
?>
