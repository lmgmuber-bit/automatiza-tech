<?php
/**
 * Test de wkhtmltopdf - Verificar que está instalado y funciona
 */

// Buscar wkhtmltopdf
$paths = array(
    'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
    'C:\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
);

echo "<h1>Test de wkhtmltopdf</h1>";

$wk_path = false;
foreach ($paths as $path) {
    echo "<p>Verificando: <code>" . htmlspecialchars($path) . "</code> ... ";
    if (file_exists($path)) {
        echo "<strong style='color:green;'>✓ ENCONTRADO</strong></p>";
        $wk_path = $path;
        break;
    } else {
        echo "<span style='color:red;'>✗ No encontrado</span></p>";
    }
}

if (!$wk_path) {
    echo "<div style='background:#ffebee;padding:15px;border-left:4px solid #f44336;'>";
    echo "<h3>wkhtmltopdf NO está instalado</h3>";
    echo "<p>Por favor instala el archivo: <code>C:\\wamp64\\www\\automatiza-tech\\wkhtmltopdf-installer.exe</code></p>";
    echo "<p>Acepta la ruta de instalación predeterminada: <code>C:\\Program Files\\wkhtmltopdf</code></p>";
    echo "</div>";
    exit;
}

echo "<div style='background:#e8f5e9;padding:15px;border-left:4px solid #4caf50;margin:20px 0;'>";
echo "<h3>✓ wkhtmltopdf está instalado correctamente</h3>";
echo "<p>Ruta: <code>" . htmlspecialchars($wk_path) . "</code></p>";
echo "</div>";

// Crear HTML de prueba
$html_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; }
        h1 { color: #2196F3; }
        .box { 
            background: #f5f5f5; 
            padding: 20px; 
            border-radius: 5px; 
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>Factura de Prueba - AutomatizaTech</h1>
    <div class="box">
        <h2>Cliente: Juan Pérez</h2>
        <p><strong>Fecha:</strong> ' . date('d/m/Y') . '</p>
        <p><strong>Plan:</strong> Profesional</p>
        <p><strong>Monto:</strong> $350.000</p>
    </div>
    <p>Este es un PDF de prueba generado con wkhtmltopdf.</p>
</body>
</html>';

// Guardar HTML temporal
$temp_dir = sys_get_temp_dir();
$temp_html = $temp_dir . '/test-invoice.html';
$temp_pdf = $temp_dir . '/test-invoice.pdf';

file_put_contents($temp_html, $html_content);
echo "<p>HTML temporal creado: <code>" . htmlspecialchars($temp_html) . "</code></p>";

// Ejecutar wkhtmltopdf
$cmd = '"' . $wk_path . '" -q -s A4 -T 10mm -B 10mm -L 10mm -R 10mm --enable-local-file-access "' . $temp_html . '" "' . $temp_pdf . '" 2>&1';
echo "<p>Ejecutando comando: <code>" . htmlspecialchars($cmd) . "</code></p>";

exec($cmd, $output, $return_var);

echo "<h3>Resultado de la ejecución:</h3>";
echo "<p><strong>Código de retorno:</strong> " . $return_var . " ";
if ($return_var === 0) {
    echo "<span style='color:green;'>✓ ÉXITO</span>";
} else {
    echo "<span style='color:red;'>✗ ERROR</span>";
}
echo "</p>";

if (!empty($output)) {
    echo "<p><strong>Output:</strong></p>";
    echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;'>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
}

// Verificar archivo PDF
if (file_exists($temp_pdf)) {
    $pdf_size = filesize($temp_pdf);
    echo "<div style='background:#e8f5e9;padding:15px;border-left:4px solid #4caf50;margin:20px 0;'>";
    echo "<h3>✓ PDF generado exitosamente</h3>";
    echo "<p><strong>Ruta:</strong> <code>" . htmlspecialchars($temp_pdf) . "</code></p>";
    echo "<p><strong>Tamaño:</strong> " . number_format($pdf_size) . " bytes</p>";
    echo "<p><a href='data:application/pdf;base64," . base64_encode(file_get_contents($temp_pdf)) . "' download='test-invoice.pdf' style='background:#2196F3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-top:10px;'>📥 Descargar PDF de Prueba</a></p>";
    echo "</div>";
    
    // Limpiar
    @unlink($temp_html);
    // No borrar el PDF para que se pueda descargar
} else {
    echo "<div style='background:#ffebee;padding:15px;border-left:4px solid #f44336;margin:20px 0;'>";
    echo "<h3>✗ No se pudo generar el PDF</h3>";
    echo "<p>El archivo PDF no existe después de ejecutar wkhtmltopdf</p>";
    echo "</div>";
}
