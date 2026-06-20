<?php
// admin-approve-proposal.php
require_once('wp-load.php');

// Verificar permisos: solo administradores pueden aprobar/enviar propuestas
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Acceso denegado', 'Acceso denegado', array('response' => 403));
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';
$message = '';

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_id'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'at_approve_proposal')) {
        wp_die('Token de seguridad inválido');
    }
    $id = intval($_POST['proposal_id']);
    $client_name = sanitize_text_field($_POST['client_name']);
    $company_name = sanitize_text_field($_POST['company_name']);
    $gamma_url = esc_url_raw($_POST['gamma_url']);
    $n8n_url = esc_url_raw($_POST['n8n_url']);
    
    // Manejo de PDF
    $pdf_path = '';
    if (!empty($_FILES['pdf_file']['name'])) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        $uploadedfile = $_FILES['pdf_file'];
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $pdf_path = $movefile['url']; // Guardamos la URL pública
            $pdf_file_path = $movefile['file']; // Ruta física para adjuntar al mail
        } else {
            $message = '<div class="alert alert-danger">Error al subir PDF: ' . $movefile['error'] . '</div>';
        }
    }

    // Actualizar BD
    $update_data = [
        'client_name' => $client_name,
        'company_name' => $company_name,
        'gamma_iframe_url' => $gamma_url,
        'n8n_chat_url' => $n8n_url,
        'status' => 'sent'
    ];
    if ($pdf_path) {
        $update_data['pdf_path'] = $pdf_path;
    }

    $wpdb->update($table_name, $update_data, ['id' => $id]);

    // Obtener datos actualizados para el email
    $proposal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
    
    // --- ENVIAR EMAIL ---
    $to = $proposal->client_email;
    $subject = "Propuesta de Automatización Inteligente - $company_name";
    
    $link_presentacion = get_site_url() . '/ver-presentacion.php?id=' . $proposal->unique_link_id;
    $link_demo = get_site_url() . '/ver-demo.php?id=' . $proposal->unique_link_id;

    // Template HTML
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
            .header { background-color: #004aad; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 20px; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 0; color: white; background-color: #004aad; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .btn-secondary { background-color: #28a745; }
            .footer { font-size: 12px; color: #777; text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Automatiza Tech</h1>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>$client_name</strong>,</p>
                <p>Es un placer presentarle nuestra propuesta de automatización diseñada específicamente para <strong>$company_name</strong>.</p>
                <p>Hemos analizado sus requerimientos y preparado una solución que optimizará sus procesos de negocio.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link_presentacion' class='btn'>Ver Presentación Interactiva</a>
                    <br><br>
                    <a href='$link_demo' class='btn btn-secondary'>Probar Demo Chatbot</a>
                </div>

                <p>Adjunto encontrará también una copia en PDF de la presentación para su archivo.</p>
                
                <p>Quedamos atentos a sus comentarios.</p>
                <p>Atentamente,<br>El equipo de Automatiza Tech</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Automatiza Tech. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $attachments = array();
    if (isset($pdf_file_path) && file_exists($pdf_file_path)) {
        $attachments[] = $pdf_file_path;
    }

    $sent = wp_mail($to, $subject, $body, $headers, $attachments);

    if ($sent) {
        $message = '<div class="alert alert-success">Propuesta actualizada y correo enviado a ' . $to . '</div>';
    } else {
        $message = '<div class="alert alert-warning">Propuesta guardada, pero falló el envío del correo. Revise la configuración SMTP.</div>';
    }
}

// --- OBTENER PROPUESTA A EDITAR ---
$edit_proposal = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_proposal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $edit_id));
}

// --- LISTAR ÚLTIMAS PROPUESTAS ---
$proposals = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Aprobar Propuestas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="mb-4">Panel de Aprobación de Propuestas</h1>
        
        <?php echo $message; ?>

        <div class="row">
            <!-- LISTA DE PROPUESTAS -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Últimas Solicitudes</div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($proposals as $p): ?>
                            <li class="list-group-item <?php echo ($edit_proposal && $edit_proposal->id == $p->id) ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo esc_html($p->client_email); ?></strong><br>
                                        <small><?php echo $p->created_at; ?></small>
                                    </div>
                                    <a href="?edit_id=<?php echo $p->id; ?>" class="btn btn-sm btn-primary">Seleccionar</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- FORMULARIO DE EDICIÓN -->
            <div class="col-md-8">
                <?php if ($edit_proposal): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            Aprobar y Enviar: <?php echo esc_html($edit_proposal->client_email); ?>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php wp_nonce_field('at_approve_proposal'); ?>
                                <input type="hidden" name="proposal_id" value="<?php echo $edit_proposal->id; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Nombre del Cliente</label>
                                    <input type="text" name="client_name" class="form-control" required value="<?php echo esc_attr($edit_proposal->client_name); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Nombre de la Empresa</label>
                                    <input type="text" name="company_name" class="form-control" required value="<?php echo esc_attr($edit_proposal->company_name); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">URL Iframe Gamma (Presentación)</label>
                                    <input type="url" name="gamma_url" class="form-control" placeholder="https://gamma.app/embed/..." required value="<?php echo esc_attr($edit_proposal->gamma_iframe_url); ?>">
                                    <div class="form-text">La URL que aparece en "Share" -> "Embed" de Gamma.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">URL Webhook n8n (Chatbot)</label>
                                    <input type="url" name="n8n_url" class="form-control" placeholder="https://n8n.tu-dominio.com/webhook/..." required value="<?php echo esc_attr($edit_proposal->n8n_chat_url); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Adjuntar PDF de Respaldo</label>
                                    <input type="file" name="pdf_file" class="form-control" accept="application/pdf">
                                    <?php if ($edit_proposal->pdf_path): ?>
                                        <div class="form-text text-success">PDF actual: <a href="<?php echo $edit_proposal->pdf_path; ?>" target="_blank">Ver archivo</a></div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">Guardar y Enviar Correo</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Selecciona una propuesta de la lista para editarla y enviarla.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
