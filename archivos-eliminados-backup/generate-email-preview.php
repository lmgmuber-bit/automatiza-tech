<?php
/**
 * Vista previa de la plantilla de email mejorada
 */

require_once('wp-load.php');

// Simular un contacto
$test_name = "Juan Pérez";

// Obtener la instancia del formulario de contacto
$contact_form = new AutomatizaTechContactForm();

// Usar reflection para acceder al método privado
$reflection = new ReflectionClass($contact_form);
$method = $reflection->getMethod('get_email_template');
$method->setAccessible(true);

// Generar el HTML del email
$email_html = $method->invoke($contact_form, $test_name);

// Guardar el HTML en un archivo temporal
$preview_file = 'email-preview.html';
file_put_contents($preview_file, $email_html);

echo "✅ Vista previa del email generada exitosamente!\n\n";
echo "📧 Archivo guardado en: " . __DIR__ . "/" . $preview_file . "\n\n";
echo "🌐 Abre el archivo en tu navegador para ver la vista previa:\n";
echo "   file:///" . str_replace('\\', '/', __DIR__) . "/" . $preview_file . "\n\n";
echo "🎨 El nuevo diseño incluye:\n";
echo "   ✅ Gradientes coloridos y modernos\n";
echo "   ✅ Bots y emojis simpáticos\n";
echo "   ✅ Diseño responsive\n";
echo "   ✅ Planes con diseño de tarjeta mejorado\n";
echo "   ✅ CTA destacados con efectos visuales\n";
echo "   ✅ Footer profesional con información de contacto\n\n";

// Mostrar estadísticas
$plans = get_active_automatiza_services('pricing');
echo "📊 Planes incluidos: " . count($plans) . "\n";
foreach ($plans as $index => $plan) {
    echo "   " . ($index + 1) . ". " . $plan->name . " - $" . $plan->price_usd . " USD/mes\n";
}
?>
