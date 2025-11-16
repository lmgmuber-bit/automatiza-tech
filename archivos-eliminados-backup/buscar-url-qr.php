<?php
/**
 * Script para buscar referencias a la URL del QR
 */

echo "<h1>Buscando referencias a la URL del QR</h1>";

$files_to_check = array(
    'wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php',
    'wp-content/themes/automatiza-tech/lib/invoice-handlers.php',
    'wp-content/themes/automatiza-tech/inc/invoice-ajax.php',
);

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo "<p style='color: gray;'>⊘ Archivo no existe: $file</p>";
        continue;
    }
    
    $content = file_get_contents($full_path);
    
    // Buscar validar-factura con cualquier parámetro
    if (preg_match_all('/validar-factura[^\s]*/', $content, $matches)) {
        echo "<h3>$file:</h3>";
        echo "<pre>";
        print_r($matches[0]);
        echo "</pre>";
    } else {
        echo "<p style='color: green;'>✓ No hay referencias en: $file</p>";
    }
}

echo "<hr>";
echo "<h2>URL actual en invoice-pdf-fpdf.php:</h2>";
$invoice_pdf = file_get_contents(__DIR__ . '/wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php');
if (preg_match('/\$validation_url\s*=\s*[\'"]([^\'"]+)[\'"]/', $invoice_pdf, $match)) {
    echo "<p><strong>URL encontrada:</strong> <code>{$match[1]}</code></p>";
}
?>
