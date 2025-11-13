<?php
/**
 * TEST: Previsualización de Factura y Correo Electrónico
 * 
 * Este archivo permite previsualizar:
 * 1. La factura HTML que se genera
 * 2. El correo que se envía al cliente
 * 3. El correo de notificación interna
 * 
 * Uso: http://localhost/automatiza-tech/test-invoice-preview.php
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require('wp-load.php');

// Solo accesible para administradores
if (!current_user_can('administrator')) {
    die('❌ Acceso denegado. Solo administradores pueden ver esta página.');
}

global $wpdb;

// Obtener datos de prueba
$test_client_data = (object) array(
    'id' => 9999,
    'name' => 'Juan Pérez González',
    'email' => 'test@ejemplo.com',
    'company' => 'Empresa Demo S.A.',
    'phone' => '+56 9 6432 4169',
    'contacted_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
    'contracted_at' => date('Y-m-d H:i:s'),
    'original_message' => 'Me interesa automatizar los procesos de mi empresa para mejorar la eficiencia operativa.'
);

// Obtener primer plan activo como ejemplo
$test_plan_data = $wpdb->get_row("
    SELECT * FROM {$wpdb->prefix}automatiza_services 
    WHERE category = 'pricing' AND status = 'active' 
    ORDER BY id ASC 
    LIMIT 1
");


if (!$test_plan_data) {
    die('❌ Error: No hay planes activos en la base de datos. Por favor activa al menos un plan.');
}

// Colores de AutomatizaTech
$primary_color = '#1e3a8a';
$secondary_color = '#06d6a0';
$accent_color = '#f59e0b';

// Generar número de factura de prueba
$invoice_number = 'AT-' . date('Ymd') . '-TEST';

// Calcular totales
$subtotal = floatval($test_plan_data->price_clp);
$iva = $subtotal * 0.19;
$total = $subtotal + $iva;

$site_url = get_site_url();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 TEST - Previsualización de Factura y Correos</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            color: <?php echo $primary_color; ?>;
            font-size: 2.5em;
        }
        .header p {
            margin: 5px 0;
            color: #666;
            font-size: 1.1em;
        }
        .alert {
            background: #fef3c7;
            border-left: 5px solid #f59e0b;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .alert strong {
            color: #92400e;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab-button {
            background: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
            color: #333;
        }
        .tab-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        .tab-button.active {
            background: <?php echo $secondary_color; ?>;
            color: white;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            min-height: 400px;
        }
        .tab-content.active {
            display: block;
        }
        .preview-frame {
            border: 3px solid <?php echo $secondary_color; ?>;
            border-radius: 10px;
            padding: 20px;
            background: #f8f9fa;
            margin: 20px 0;
            max-height: 600px;
            overflow-y: auto;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-card {
            background: linear-gradient(135deg, <?php echo $primary_color; ?>, <?php echo $secondary_color; ?>);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .info-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
        }
        .info-card p {
            margin: 5px 0;
            opacity: 0.95;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: <?php echo $secondary_color; ?>;
            color: white;
        }
        .btn-secondary {
            background: <?php echo $primary_color; ?>;
            color: white;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            margin: 15px 0;
        }
        .success {
            background: #d1fae5;
            border-left: 5px solid #10b981;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 TEST - Sistema de Facturación</h1>
            <p>Previsualización de Factura y Correos Electrónicos</p>
            <p style="font-size: 0.9em; color: #999;">Ambiente de Pruebas - No se enviarán correos reales</p>
        </div>

        <div class="alert">
            <strong>⚠️ IMPORTANTE:</strong> Esta es una previsualización de prueba. Los datos mostrados son ficticios. 
            Ningún correo será enviado desde esta página. Usa esto para verificar el diseño antes de pasar a producción.
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>👤 Cliente de Prueba</h3>
                <p><strong>Nombre:</strong> <?php echo $test_client_data->name; ?></p>
                <p><strong>Email:</strong> <?php echo $test_client_data->email; ?></p>
                <p><strong>Empresa:</strong> <?php echo $test_client_data->company; ?></p>
            </div>
            <div class="info-card">
                <h3>💼 Plan Contratado</h3>
                <p><strong>Plan:</strong> <?php echo $test_plan_data->name; ?></p>
                <p><strong>Precio:</strong> $<?php echo number_format($test_plan_data->price_clp, 0, ',', '.'); ?></p>
                <p><strong>Factura:</strong> <?php echo $invoice_number; ?></p>
            </div>
            <div class="info-card">
                <h3>📊 Totales</h3>
                <p><strong>Subtotal:</strong> $<?php echo number_format($subtotal, 0, ',', '.'); ?></p>
                <p><strong>IVA (19%):</strong> $<?php echo number_format($iva, 0, ',', '.'); ?></p>
                <p><strong>Total:</strong> $<?php echo number_format($total, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="showTab('invoice')">
                📄 Factura HTML
            </button>
            <button class="tab-button" onclick="showTab('email-client')">
                📧 Correo al Cliente
            </button>
            <button class="tab-button" onclick="showTab('email-internal')">
                📨 Correo Interno
            </button>
            <button class="tab-button" onclick="showTab('plain-text')">
                📝 Texto Plano
            </button>
            <button class="tab-button" onclick="showTab('headers')">
                🔧 Headers Anti-Spam
            </button>
        </div>

        <!-- TAB 1: Factura HTML -->
        <div id="tab-invoice" class="tab-content active">
            <h2>📄 Factura HTML (Archivo Adjunto)</h2>
            <p>Esta es la factura que se adjunta al correo del cliente en formato HTML:</p>
            
            <div class="preview-frame">
                <?php
                // Generar factura HTML
                include('generate-invoice-html.php');
                echo generate_invoice_preview($test_client_data, $test_plan_data);
                ?>
            </div>

            <div class="action-buttons">
                <button class="btn btn-primary" onclick="downloadInvoice()">
                    💾 Descargar Factura HTML
                </button>
                <button class="btn btn-secondary" onclick="printInvoice()">
                    🖨️ Imprimir Factura
                </button>
            </div>
        </div>

        <!-- TAB 2: Correo al Cliente -->
        <div id="tab-email-client" class="tab-content">
            <h2>📧 Correo al Cliente (Con Factura Adjunta)</h2>
            <p><strong>Para:</strong> <?php echo $test_client_data->email; ?></p>
            <p><strong>Asunto:</strong> Bienvenido a AutomatizaTech - Factura <?php echo $invoice_number; ?> - <?php echo $test_client_data->name; ?></p>
            
            <div class="success">
                ✅ <strong>Optimizado Anti-Spam:</strong> Este correo incluye headers profesionales, 
                texto plano alternativo y diseño transaccional para llegar a bandeja de entrada.
            </div>

            <div class="preview-frame">
                <?php include('generate-email-client.php'); 
                echo generate_client_email_preview($test_client_data, $test_plan_data, $invoice_number, $site_url);
                ?>
            </div>
        </div>

        <!-- TAB 3: Correo Interno -->
        <div id="tab-email-internal" class="tab-content">
            <h2>📨 Correo de Notificación Interna</h2>
            <p><strong>Para:</strong> automatizatech.bots@gmail.com</p>
            <p><strong>Asunto:</strong> 🎉 ¡Nuevo Cliente Contratado! - <?php echo $test_client_data->name; ?> - Plan: <?php echo $test_plan_data->name; ?></p>
            
            <div class="preview-frame">
                <?php include('generate-email-internal.php');
                echo generate_internal_email_preview($test_client_data, $test_plan_data);
                ?>
            </div>
        </div>

        <!-- TAB 4: Texto Plano -->
        <div id="tab-email-plain" class="tab-content">
            <h2>📝 Versión Texto Plano (AltBody)</h2>
            <p>Esta versión se envía junto con el HTML para mejorar la deliverability:</p>
            
            <div class="code-block">
<?php
$plain_text = "Hola " . $test_client_data->name . ",\n\n";
$plain_text .= "Gracias por confiar en AutomatizaTech para tu proyecto de transformacion digital.\n\n";
$plain_text .= "PLAN CONTRATADO\n";
$plain_text .= "---------------\n";
$plain_text .= "Plan: " . $test_plan_data->name . "\n";
$plain_text .= "Precio: $" . number_format($test_plan_data->price_clp, 0, ',', '.') . "\n\n";
$plain_text .= "FACTURA\n";
$plain_text .= "-------\n";
$plain_text .= "Numero: " . $invoice_number . "\n";
$plain_text .= "Fecha: " . date('d/m/Y H:i') . "\n\n";
$plain_text .= "Encontraras adjunta la factura con el detalle completo de tu contratacion.\n\n";
$plain_text .= "Nuestro equipo se pondra en contacto contigo en las proximas 24-48 horas.\n\n";
$plain_text .= "INFORMACION DE CONTACTO\n";
$plain_text .= "-----------------------\n";
$plain_text .= "Email: info@automatizatech.shop\n";
$plain_text .= "Telefono: +56 9 6432 4169\n";
$plain_text .= "Web: " . $site_url . "\n\n";
$plain_text .= "Saludos cordiales,\n";
$plain_text .= "Equipo AutomatizaTech\n";

echo htmlspecialchars($plain_text);
?>
            </div>
        </div>

        <!-- TAB 5: Headers -->
        <div id="tab-headers" class="tab-content">
            <h2>🔧 Headers Anti-Spam Configurados</h2>
            <p>Estos headers ayudan a que el correo llegue a bandeja de entrada:</p>
            
            <div class="code-block">
Content-Type: text/html; charset=UTF-8
From: AutomatizaTech &lt;info@automatizatech.shop&gt;
Reply-To: info@automatizatech.shop
Bcc: automatizatech.bots@gmail.com
X-Priority: 1 (Highest)
X-MSMail-Priority: High
Importance: High
X-Mailer: AutomatizaTech Invoicing System v1.0
List-Unsubscribe: &lt;mailto:unsubscribe@automatizatech.shop&gt;
Precedence: bulk
X-Auto-Response-Suppress: OOF, DR, RN, NRN, AutoReply
            </div>

            <div class="success">
                <h3 style="margin-top: 0;">✅ Mejores Prácticas Aplicadas:</h3>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Asunto personalizado con nombre del cliente (sin emojis)</li>
                    <li>Headers profesionales de sistema transaccional</li>
                    <li>Versión texto plano alternativa (multipart/alternative)</li>
                    <li>Diseño simple tipo factura (no promocional)</li>
                    <li>Ratio texto/HTML balanceado</li>
                    <li>Sin palabras spam o lenguaje de marketing agresivo</li>
                    <li>From address verificado (info@automatizatech.shop)</li>
                    <li>List-Unsubscribe header (requerido por Gmail)</li>
                </ul>
            </div>

            <div class="alert">
                <strong>📌 Nota:</strong> Para producción, asegúrate de configurar SPF, DKIM y DMARC 
                en los registros DNS de automatizatech.shop para máxima deliverability.
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center; color: white;">
            <p>🚀 Una vez verificado el diseño, puedes proceder a mover el contacto a "Contratado" en el sistema real.</p>
            <div class="action-buttons" style="justify-content: center;">
                <a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts'); ?>" class="btn btn-primary">
                    👥 Ir al Panel de Contactos
                </a>
                <a href="<?php echo admin_url('admin.php?page=automatiza-tech-clients'); ?>" class="btn btn-secondary">
                    💼 Ir al Panel de Clientes
                </a>
                <button class="btn btn-warning" onclick="location.reload()">
                    🔄 Recargar Prueba
                </button>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Mostrar tab seleccionado
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function downloadInvoice() {
            const invoiceContent = document.querySelector('#tab-invoice .preview-frame').innerHTML;
            const blob = new Blob([invoiceContent], { type: 'text/html' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'factura-<?php echo $invoice_number; ?>.html';
            a.click();
            window.URL.revokeObjectURL(url);
            alert('✅ Factura descargada correctamente');
        }

        function printInvoice() {
            const invoiceContent = document.querySelector('#tab-invoice .preview-frame').innerHTML;
            const printWindow = window.open('', '', 'height=800,width=800');
            printWindow.document.write('<html><head><title>Factura</title>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(invoiceContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
