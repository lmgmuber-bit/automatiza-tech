<?php
/**
 * Generador de Correo al Cliente para Previsualización
 */

function generate_client_email_preview($client_data, $plan_data, $invoice_number, $site_url) {
    $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
    $primary_color = '#1e3a8a';
    $secondary_color = '#06d6a0';
    
    $html = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333;
            background: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .email-header {
            background: {$primary_color};
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 4px solid {$secondary_color};
        }
        .email-header h1 {
            font-size: 1.8em;
            margin-bottom: 8px;
        }
        .email-header p {
            font-size: 1em;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 1.2em;
            color: {$primary_color};
            margin-bottom: 20px;
            font-weight: 600;
        }
        .message-text {
            margin: 15px 0;
            color: #555;
            line-height: 1.8;
        }
        .plan-highlight {
            background: #f8f9fa;
            border-left: 4px solid {$secondary_color};
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .plan-highlight h3 {
            color: {$primary_color};
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .plan-name {
            font-size: 1.4em;
            color: {$secondary_color};
            font-weight: bold;
            margin: 10px 0;
        }
        .plan-price {
            font-size: 2em;
            color: {$primary_color};
            font-weight: bold;
            margin: 15px 0;
        }
        .invoice-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .invoice-info h4 {
            color: {$primary_color};
            margin-bottom: 10px;
        }
        .invoice-number {
            font-size: 1.3em;
            font-weight: bold;
            color: {$secondary_color};
            margin: 10px 0;
        }
        .support-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .support-box h4 {
            color: {$primary_color};
            margin-bottom: 10px;
        }
        .contact-info {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        .email-footer {
            background: {$primary_color};
            color: white;
            padding: 30px;
            text-align: center;
        }
        .email-footer p {
            margin: 8px 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 160px; height: auto; margin-bottom: 15px;'>
            <h1>Bienvenido a AutomatizaTech</h1>
            <p>Gracias por confiar en nosotros</p>
        </div>
        
        <div class='email-body'>
            <div class='greeting'>
                Hola " . esc_html($client_data->name) . ",
            </div>
            
            <p class='message-text'>
                Gracias por confiar en AutomatizaTech para tu proyecto de transformación digital. 
                Adjuntamos la factura correspondiente a tu contratación.
            </p>
            
            <div class='plan-highlight'>
                <h3>Plan Contratado</h3>
                <div class='plan-name'>" . esc_html($plan_data->name) . "</div>
                <div class='plan-price'>$" . number_format($plan_data->price_clp, 0, ',', '.') . "</div>
                <p class='message-text' style='margin-top: 15px;'>" . esc_html($plan_data->description) . "</p>
            </div>
            
            <p class='message-text'>
                Encontrarás adjunta la factura con el detalle completo de tu contratación. 
                Te recomendamos guardar este documento para tus registros contables.
            </p>
            
            <div class='invoice-info'>
                <h4>Informacion de la Factura</h4>
                <div class='invoice-number'>{$invoice_number}</div>
                <p style='color: #666;'>Fecha: " . date('d/m/Y H:i') . "</p>
            </div>
            
            <p class='message-text'>
                Nuestro equipo se pondrá en contacto contigo en las próximas 24-48 horas para coordinar 
                el inicio de tu proyecto y resolver cualquier consulta que puedas tener.
            </p>
            
            <div class='support-box'>
                <h4>Informacion de Contacto</h4>
                <p style='color: #666; margin-bottom: 10px;'>Si tienes consultas, puedes contactarnos:</p>
                <div class='contact-info'>
                    Email: <strong>info@automatizatech.shop</strong><br>
                    Teléfono: <strong>+56 9 4033 1127</strong><br>
                    Sitio web: <strong>{$site_url}</strong>
                </div>
            </div>
            
            <p class='message-text' style='margin-top: 20px; color: #666;'>
                Saludos cordiales,<br>
                <strong>Equipo AutomatizaTech</strong>
            </p>
        </div>
        
        <div class='email-footer'>
            <p style='font-size: 1em; margin-bottom: 10px;'><strong>AutomatizaTech</strong></p>
            <p style='font-size: 0.9em;'>Soluciones de automatizacion digital</p>
            <p style='font-size: 0.85em; margin-top: 15px;'>
                {$site_url} | info@automatizatech.shop<br>
                Copyright " . date('Y') . " AutomatizaTech. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>";
    
    return $html;
}
