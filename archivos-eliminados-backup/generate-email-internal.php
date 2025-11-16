<?php
/**
 * Generador de Correo Interno para Previsualización
 */

function generate_internal_email_preview($client_data, $plan_data) {
    $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
    $site_url = get_site_url();
    $admin_url = admin_url('admin.php?page=automatiza-tech-clients');
    
    $html = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 25px; border-radius: 0 0 8px 8px; }
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .label { font-weight: bold; color: #1e3a8a; display: inline-block; width: 120px; }
        .value { color: #495057; }
        .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.9em; }
        .cta { background: #06d6a0; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; display: inline-block; margin: 10px 0; }
        .message-box { background: #e3f2fd; padding: 15px; border-left: 4px solid #1976d2; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='header'>
        <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 140px; height: auto; margin-bottom: 10px;'>
        <h1>🎉 ¡Nuevo Cliente Contratado!</h1>
        <p>Se ha convertido un contacto a cliente en AutomatizaTech</p>
    </div>
    
    <div class='content'>
        <div class='info-box'>
            <h3 style='color: #1e3a8a; margin-top: 0;'>📋 Información del Cliente</h3>
            <p><span class='label'>Nombre:</span> <span class='value'>" . esc_html($client_data->name) . "</span></p>
            <p><span class='label'>Email:</span> <span class='value'>" . esc_html($client_data->email) . "</span></p>
            <p><span class='label'>Empresa:</span> <span class='value'>" . esc_html($client_data->company) . "</span></p>
            <p><span class='label'>Teléfono:</span> <span class='value'>" . esc_html($client_data->phone) . "</span></p>
            <p><span class='label'>Contactado:</span> <span class='value'>" . date('d/m/Y H:i', strtotime($client_data->contacted_at)) . "</span></p>
            <p><span class='label'>Contratado:</span> <span class='value'>" . date('d/m/Y H:i', strtotime($client_data->contracted_at)) . "</span></p>
        </div>
        
        <div class='info-box' style='border-left: 4px solid #06d6a0;'>
            <h3 style='color: #06d6a0; margin-top: 0;'>💼 Plan Contratado</h3>
            <p><span class='label'>Plan:</span> <span class='value' style='font-weight: bold; font-size: 1.1em;'>" . esc_html($plan_data->name) . "</span></p>
            <p><span class='label'>Precio:</span> <span class='value' style='font-weight: bold; color: #06d6a0; font-size: 1.2em;'>$" . number_format($plan_data->price_clp, 0, ',', '.') . "</span></p>
            <p style='margin-top: 10px;'><em>" . esc_html($plan_data->description) . "</em></p>
            <p style='margin-top: 10px; padding: 10px; background: #e8f5f1; border-radius: 5px;'>✉️ <strong>Se ha enviado la factura automáticamente al cliente</strong></p>
        </div>
        
        <div class='message-box'>
            <h4 style='color: #1976d2; margin-top: 0;'>💬 Mensaje Original</h4>
            <p>" . nl2br(esc_html($client_data->original_message)) . "</p>
        </div>
        
        <div style='text-align: center; margin: 20px 0;'>
            <a href='{$admin_url}' class='cta'>👥 Ver Panel de Clientes</a>
        </div>
        
        <div class='footer'>
            <p>📧 Correo enviado automáticamente desde <strong>AutomatizaTech</strong></p>
            <p>🌐 <a href='{$site_url}'>{$site_url}</a></p>
            <p>📅 " . date('d/m/Y H:i:s') . "</p>
        </div>
    </div>
</body>
</html>";
    
    return $html;
}
