<?php
/**
 * Panel de Administración para Aprobar Propuestas
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/proposals-flow.php';

if (!defined('AT_N8N_V3_CAMBIOS')) {
    define('AT_N8N_V3_CAMBIOS', 'https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-cambios');
}
if (!defined('AT_N8N_V3_FINAL')) {
    define('AT_N8N_V3_FINAL', 'https://n8n-n8n.kchiba.easypanel.host/webhook/propuesta-v3-final');
}

/** Avisa a n8n; devuelve '' si respondió 2xx o el motivo del fallo. */
function at_v3_llamar_n8n(string $url, int $id): string {
    if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
        return 'AT_REST_SECRET no está configurado.';
    }
    $r = wp_remote_post($url, [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
        'body'    => wp_json_encode(['id' => $id]),
    ]);
    if (is_wp_error($r)) {
        return $r->get_error_message();
    }
    $code = wp_remote_retrieve_response_code($r);
    return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}

/**
 * Agregar menú de administración
 */
function automatiza_tech_proposals_menu() {
    add_menu_page(
        'Aprobar Propuestas',
        'Propuestas',
        'manage_options',
        'automatiza-proposals',
        'automatiza_tech_proposals_page',
        'dashicons-format-aside',
        26
    );
}
add_action('admin_menu', 'automatiza_tech_proposals_menu');

/**
 * Renderizar página de administración
 */
function automatiza_tech_proposals_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';

    // --- PROCESAR ELIMINACIÓN MASIVA ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['proposal_ids'])) {
        if (!isset($_POST['automatiza_bulk_nonce']) || !wp_verify_nonce($_POST['automatiza_bulk_nonce'], 'bulk_delete_proposals')) {
            $message = '<div class="notice notice-error"><p>Error de seguridad. Intente nuevamente.</p></div>';
        } else {
            $ids = array_map('intval', $_POST['proposal_ids']);
            if (!empty($ids)) {
                $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN ($ids_placeholder)",
                    ...$ids
                ));
                $message = '<div class="notice notice-success is-dismissible"><p>🗑️ ' . count($ids) . ' propuesta(s) eliminada(s) correctamente.</p></div>';
            }
        }
    }

    // --- PROCESAR ELIMINACIÓN INDIVIDUAL ---
    if (isset($_GET['delete_id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_proposal_' . $_GET['delete_id'])) {
            $delete_id = intval($_GET['delete_id']);
            $wpdb->delete($table_name, ['id' => $delete_id]);
            $message = '<div class="notice notice-success is-dismissible"><p>🗑️ Propuesta eliminada correctamente.</p></div>';
        } else {
            $message = '<div class="notice notice-error"><p>Error de seguridad al eliminar.</p></div>';
        }
    }

    // --- PROCESAR BOTONES DEL PANEL v3 (pedir cambios / aprobar / destrabar) ---
    if (isset($_POST['at_v3_accion'], $_POST['proposal_id']) && current_user_can('manage_options')) {
        $id = (int) $_POST['proposal_id'];
        check_admin_referer('at_v3_' . $id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
        $accion = sanitize_key($_POST['at_v3_accion']);
        if ($row && $row->flujo === 'v3' && $accion === 'destrabar') {
            if (!at_propuesta_transicion_valida((string) $row->status, 'error')) {
                $message = '<div class="notice notice-error"><p>No se puede pasar de <strong>' . esc_html($row->status) . '</strong> a <strong>error</strong>.</p></div>';
            } else {
                $wpdb->update($table_name, [
                    'status'      => 'error',
                    'status_note' => 'Destrabada a mano desde el panel (' . current_time('mysql') . ')',
                ], ['id' => $id]);
                $message = '<div class="notice notice-success"><p>Propuesta destrabada: quedó en <strong>error</strong> para poder reintentar.</p></div>';
            }
        } elseif ($row && $row->flujo === 'v3' && in_array($accion, ['cambios', 'aprobar'], true)) {
            $payload = json_decode((string) $row->gamma_prompt_text, true) ?: [];
            $filas = isset($_POST['at_precio']) && is_array($_POST['at_precio']) ? wp_unslash($_POST['at_precio']) : [];
            // Antes de aplicar (que descarta en silencio una fila con servicio y sin precio).
            $filas_con_precio_vacio = at_propuesta_filas_con_precio_vacio($filas);
            $payload = at_propuesta_aplicar_precios($payload, $filas, sanitize_textarea_field(wp_unslash($_POST['at_nota_precio'] ?? '')));
            $hacia = $accion === 'cambios' ? 'ajustando' : 'generando';
            if (!at_propuesta_transicion_valida((string) $row->status, $hacia)) {
                $message = '<div class="notice notice-error"><p>No se puede pasar de <strong>' . esc_html($row->status) . '</strong> a <strong>' . esc_html($hacia) . '</strong>.</p></div>';
            } elseif ($accion === 'aprobar' && ($filas_con_precio_vacio || at_propuesta_precios_pendientes($payload))) {
                $wpdb->update($table_name, ['gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id' => $id]);
                $message = '<div class="notice notice-error"><p>Escribe los precios antes de aprobar.</p></div>';
            } else {
                $update = ['gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'status' => $hacia, 'status_note' => ''];
                $comentario = trim(sanitize_textarea_field(wp_unslash($_POST['at_comentario'] ?? '')));
                if ($accion === 'cambios') {
                    $update['feedback_log'] = at_propuesta_agregar_comentario($row->feedback_log, $comentario, current_time('mysql'));
                }
                $wpdb->update($table_name, $update, ['id' => $id]);
                $fallo = at_v3_llamar_n8n($accion === 'cambios' ? AT_N8N_V3_CAMBIOS : AT_N8N_V3_FINAL, $id);
                if ($fallo !== '') {
                    $wpdb->update($table_name, ['status' => 'error', 'status_note' => 'No se pudo avisar a n8n: ' . $fallo], ['id' => $id]);
                    $message = '<div class="notice notice-error"><p>' . esc_html('No se pudo avisar a n8n: ' . $fallo) . '</p></div>';
                } else {
                    $message = '<div class="notice notice-success"><p>' . ($accion === 'cambios'
                        ? 'Cambios enviados. Te llegará un correo con la nueva vista previa.'
                        : 'Aprobada. Se están generando las fotos y la versión final; te llegará un correo cuando esté verificada.') . '</p></div>';
                }
            }
        }
    }

    // --- PROCESAR FORMULARIO ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_id']) && !isset($_POST['at_v3_accion'])) {
        // Verificar nonce para seguridad
        if (!isset($_POST['automatiza_proposal_nonce']) || !wp_verify_nonce($_POST['automatiza_proposal_nonce'], 'save_proposal')) {
            echo '<div class="notice notice-error"><p>Error de seguridad. Intente nuevamente.</p></div>';
            return;
        }

        $id = intval($_POST['proposal_id']);
        $client_name = sanitize_text_field($_POST['client_name']);
        $company_name = sanitize_text_field($_POST['company_name']);
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $client_email = sanitize_email($_POST['client_email']);
        $email_subject = sanitize_text_field($_POST['email_subject']);
        $email_intro = wp_kses_post($_POST['email_intro']);
        $email_highlight = wp_kses_post($_POST['email_highlight']);
        $email_closing = wp_kses_post($_POST['email_closing']);
        
        // Capturar prompts editados
        $gamma_prompt = isset($_POST['gamma_prompt']) ? sanitize_textarea_field($_POST['gamma_prompt']) : '';
        $system_prompt = isset($_POST['system_prompt']) ? sanitize_textarea_field($_POST['system_prompt']) : '';
        
        // Procesar URL de Gamma (puede venir como URL pura o como tag <iframe>)
        $raw_gamma_input = stripslashes($_POST['gamma_url']);
        $gamma_url = '';
        
        if (preg_match('/src="([^"]+)"/', $raw_gamma_input, $matches)) {
            $gamma_url = esc_url_raw($matches[1]); // Extraer URL del iframe
        } else {
            $gamma_url = esc_url_raw($raw_gamma_input); // Asumir que es URL directa
        }

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
                $message = '<div class="notice notice-error"><p>Error al subir PDF: ' . $movefile['error'] . '</p></div>';
            }
        }

        // Actualizar BD
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';
        $actual = $wpdb->get_row($wpdb->prepare("SELECT flujo, status FROM {$table_name} WHERE id = %d", $id));
        $es_v3 = $actual && $actual->flujo === 'v3';
        if ($send_email && $actual && !at_propuesta_puede_enviarse($actual->flujo, (string) $actual->status)) {
            $send_email = false;
            $bloqueo_envio = true;
        }
        $update_data = [
            'client_name' => $client_name,
            'company_name' => $company_name,
            'phone' => $phone,
            'client_email' => $client_email,
            'gamma_iframe_url' => $gamma_url,
            'n8n_chat_url' => $n8n_url,
        ];
        // v3: el guardado normal no debe reescribir el estado del flujo; solo lo toca al enviar.
        if ($send_email) {
            $update_data['status'] = 'sent';
        } elseif (!$es_v3) {
            $update_data['status'] = 'pending';
        }
        // Solo actualizar prompts si se enviaron (no vacíos). En v3 el payload lo maneja
        // el flujo (n8n / botones de Revisión); el guardado normal no debe pisarlo.
        if (!empty($gamma_prompt) && !$es_v3) {
            $update_data['gamma_prompt_text'] = $gamma_prompt;
        }
        if (!empty($system_prompt)) {
            $update_data['system_prompt_text'] = $system_prompt;
        }
        if ($pdf_path) {
            $update_data['pdf_path'] = $pdf_path;
        }

        $wpdb->update($table_name, $update_data, ['id' => $id]);

        if (!empty($bloqueo_envio)) {
            $message = '<div class="notice notice-warning"><p>Propuesta guardada, pero <strong>no se envió</strong>: una propuesta v3 solo se envía cuando está <strong>lista</strong> (versión final verificada).</p></div>';
        }

        // Obtener datos actualizados para el email
        $proposal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));

        // --- ENVIAR EMAIL (solo si el checkbox está marcado) ---
        if (!$send_email) {
            if (empty($bloqueo_envio)) {
                $message = '<div class="notice notice-success is-dismissible"><p>✅ Propuesta guardada correctamente. <strong>No se envió correo</strong> (checkbox desmarcado).</p></div>';
            }
        } else {
            $to = $proposal->client_email;
        
        // Validar email de destino
        if (!is_email($to)) {
             $message = '<div class="notice notice-error is-dismissible"><p>Error: El email del cliente (' . esc_html($to) . ') no es válido. La propuesta se guardó pero no se envió el correo.</p></div>';
        } else {
            $subject = !empty($email_subject) ? $email_subject : "Propuesta de Automatización Inteligente - $company_name";
            
            $link_presentacion = get_site_url() . '/ver-presentacion.php?id=' . $proposal->unique_link_id;
            $link_demo = get_site_url() . '/ver-demo.php?id=' . $proposal->unique_link_id;
            
            // Obtener logo y datos del sitio
            $site_title = get_bloginfo('name');
            $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
            $footer_text = get_bloginfo('description');

            // Contenido personalizable del email
            $intro_text = !empty($email_intro) ? nl2br(esc_html($email_intro)) : 'Es un placer presentarle nuestra propuesta de automatización inteligente diseñada específicamente para <strong>' . esc_html($company_name) . '</strong>.';
            $highlight_text = !empty($email_highlight) ? nl2br(esc_html($email_highlight)) : 'Hemos analizado sus requerimientos y preparado una solución personalizada que optimizará sus procesos de negocio mediante inteligencia artificial.';
            $closing_text = !empty($email_closing) ? nl2br(esc_html($email_closing)) : 'Quedamos atentos a sus comentarios y consultas.';

            // Preparar adjuntos - Verificar si hay PDF actual o recién subido
            $attachments = array();

            // Primero verificar si se subió un nuevo PDF
            if (isset($pdf_file_path) && file_exists($pdf_file_path)) {
                $attachments[] = $pdf_file_path;
            }
            // Si no hay nuevo, verificar si hay uno existente en la BD
            elseif (!empty($proposal->pdf_path)) {
                // Convertir URL a ruta física
                $upload_dir = wp_upload_dir();
                $pdf_url = $proposal->pdf_path;

                // Intentar obtener ruta física desde URL
                if (strpos($pdf_url, $upload_dir['baseurl']) !== false) {
                    $pdf_file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url);
                    if (file_exists($pdf_file_path)) {
                        $attachments[] = $pdf_file_path;
                    }
                }
            }

            // Template HTML (mismo estilo que recordatorios)
            $body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333333; }
                    .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                    .header { background-color: #1e40af; padding: 40px 20px; text-align: center; }
                    .header img { max-height: 80px; width: auto; margin-bottom: 15px; }
                    .header h1 { margin: 0; font-size: 22px; color: #ffffff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
                    .content { padding: 40px 30px; line-height: 1.8; }
                    .content p { margin: 0 0 15px 0; }
                    .cta-container { text-align: center; margin: 30px 0; }
                    .btn { display: inline-block; padding: 14px 28px; margin: 8px; color: #ffffff !important; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 14px; transition: all 0.3s ease; }
                    .btn-primary { background: linear-gradient(135deg, #1e40af, #3b82f6); box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3); }
                    .btn-secondary { background: linear-gradient(135deg, #06d6a0, #10b981); box-shadow: 0 4px 15px rgba(6, 214, 160, 0.3); }
                    .footer { background-color: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #888888; }
                    .footer a { color: #1e40af; text-decoration: none; }
                    .highlight-box { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-left: 4px solid #1e40af; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '">
                        <h1>Propuesta de Automatización</h1>
                    </div>
                    <div class="content">
                        <p>Estimado/a <strong>' . esc_html($client_name) . '</strong>,</p>
                        
                        <p>' . $intro_text . '</p>
                        
                        <div class="highlight-box">
                            <p style="margin: 0;"><strong>🎯 ¿Qué incluye esta propuesta?</strong></p>
                            <p style="margin: 10px 0 0 0;">' . $highlight_text . '</p>
                        </div>
                        
                        <p>A continuación encontrará los enlaces para explorar su propuesta:</p>
                        
                        <div class="cta-container">
                            <a href="' . esc_url($link_presentacion) . '" class="btn btn-primary">📊 Ver Presentación</a>
                            <br>
                            <a href="' . esc_url($link_demo) . '" class="btn btn-secondary">🤖 Probar Demo Chatbot</a>
                        </div>
                        
                        ' . (!empty($attachments)
                            ? '<p style="font-size: 14px; color: #666; text-align: center;">Adjunto encontrará también una copia en PDF de la presentación para su archivo.</p>'
                            : '<p style="font-size: 14px; color: #666; text-align: center;">Puede descargar la presentación en PDF desde el botón del final de la presentación.</p>') . '

                        <p>' . $closing_text . '</p>
                        
                        <p>Atentamente,<br><strong>El equipo de Automatiza Tech</strong></p>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' ' . esc_html($site_title) . '. Todos los derechos reservados.</p>
                        <p>' . esc_html($footer_text) . '</p>
                    </div>
                </div>
            </body>
            </html>';

            $headers = array('Content-Type: text/html; charset=UTF-8');
            // Forzar remitente para evitar bloqueos SMTP (Debe coincidir con el usuario SMTP)
            $sender_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
            $headers[] = 'From: Automatiza Tech <' . $sender_email . '>';
            // Agregar Reply-To para que el cliente responda al admin real
            $admin_email = get_option('admin_email');
            $headers[] = 'Reply-To: ' . $admin_email;
            // Copia oculta para registro interno
            $headers[] = 'Bcc: automatizacionesbotcore@gmail.com';

            // Capturar errores de envío
            global $phpmailer;
            $sent = wp_mail($to, $subject, $body, $headers, $attachments);

            if ($sent) {
                $attachment_msg = !empty($attachments) ? ' (con PDF adjunto)' : ' (sin PDF adjunto)';
                $message = '<div class="notice notice-success is-dismissible"><p>Propuesta actualizada y correo enviado a ' . esc_html($to) . $attachment_msg . '</p></div>';
            } else {
                // Intentar obtener detalles del error (si están disponibles en global $phpmailer)
                $error_details = '';
                if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                    $error_details = ' Detalle: ' . $phpmailer->ErrorInfo;
                }
                $message = '<div class="notice notice-warning is-dismissible"><p>Propuesta guardada, pero falló el envío del correo a ' . esc_html($to) . '.' . esc_html($error_details) . ' Revise la configuración SMTP.</p></div>';
            }
        } // cierre del else is_email
        } // cierre del else send_email
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

    <div class="wrap proposals-admin">
        <style>
            /* ==================== ESTILOS PROPUESTAS ADMIN ==================== */
            .proposals-admin .proposals-layout {
                display: flex;
                gap: 20px;
                margin-top: 20px;
            }
            .proposals-admin .proposals-sidebar {
                flex: 1;
                max-width: 400px;
                min-width: 280px;
            }
            .proposals-admin .proposals-main {
                flex: 2;
                min-width: 0;
            }
            .proposals-admin .proposal-list {
                list-style: none;
                margin: 0;
                max-height: 500px;
                overflow-y: auto;
            }
            .proposals-admin .proposal-item {
                padding: 12px;
                border-bottom: 1px solid #eee;
            }
            .proposals-admin .proposal-item.active {
                background-color: #f0f0f1;
            }
            .proposals-admin .proposal-item-content {
                display: flex;
                gap: 8px;
                align-items: flex-start;
            }
            .proposals-admin .proposal-email {
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .proposals-admin .proposal-actions {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .proposals-admin .bulk-actions {
                padding: 10px;
                background: #f6f7f7;
                border-bottom: 1px solid #ddd;
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .proposals-admin .status-badge {
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 10px;
                margin-left: 5px;
                white-space: nowrap;
            }
            .proposals-admin .status-sent {
                background: #d1fae5;
                color: #065f46;
            }
            .proposals-admin .status-pending {
                background: #fef3c7;
                color: #92400e;
            }
            .proposals-admin .preview-box {
                margin-bottom: 20px;
                padding: 15px;
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                border-left: 4px solid #2271b1;
            }
            .proposals-admin .preview-buttons {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .proposals-admin .prompts-section {
                margin-top: 20px;
                padding: 20px;
                background: #f0f9ff;
                border: 1px solid #0284c7;
                border-radius: 8px;
            }
            .proposals-admin .email-section {
                margin-top: 20px;
                padding: 20px;
                background: #fff8e1;
                border: 1px solid #ffcc02;
                border-radius: 8px;
            }
            .proposals-admin .checkbox-section {
                margin-top: 20px;
                padding: 15px;
                background: #d1fae5;
                border: 2px solid #10b981;
                border-radius: 8px;
            }
            
            /* ==================== ESTILOS RESPONSIVOS ==================== */
            
            /* Tablet (1024px y menos) */
            @media screen and (max-width: 1024px) {
                .proposals-admin .proposals-layout {
                    flex-direction: column;
                }
                .proposals-admin .proposals-sidebar {
                    max-width: 100%;
                    order: 2;
                }
                .proposals-admin .proposals-main {
                    order: 1;
                }
                .proposals-admin .proposal-list {
                    max-height: 300px;
                }
            }
            
            /* Mobile (767px y menos) */
            @media screen and (max-width: 767px) {
                .proposals-admin {
                    margin: 0 -10px;
                }
                .proposals-admin h1.wp-heading-inline {
                    font-size: 20px;
                    padding: 0 10px;
                }
                .proposals-admin .proposals-layout {
                    gap: 15px;
                    padding: 0 10px;
                }
                .proposals-admin .proposals-sidebar {
                    min-width: 100%;
                }
                
                /* Formulario */
                .proposals-admin .form-table th,
                .proposals-admin .form-table td {
                    display: block;
                    width: 100%;
                    padding: 10px 0;
                }
                .proposals-admin .form-table th {
                    padding-bottom: 5px;
                }
                .proposals-admin input[type="text"],
                .proposals-admin input[type="email"],
                .proposals-admin input[type="url"],
                .proposals-admin textarea,
                .proposals-admin select {
                    width: 100% !important;
                    max-width: 100% !important;
                    font-size: 16px !important; /* Evita zoom iOS */
                    min-height: 44px;
                }
                .proposals-admin textarea {
                    min-height: 100px;
                }
                
                /* Botones touch-friendly */
                .proposals-admin .button {
                    min-height: 44px;
                    padding: 10px 16px;
                    font-size: 14px;
                }
                .proposals-admin .button-large {
                    width: 100%;
                    justify-content: center;
                    display: flex;
                    align-items: center;
                }
                
                /* Preview buttons */
                .proposals-admin .preview-buttons {
                    flex-direction: column;
                }
                .proposals-admin .preview-buttons .button {
                    width: 100%;
                    justify-content: center;
                }
                
                /* Bulk actions */
                .proposals-admin .bulk-actions {
                    flex-wrap: wrap;
                }
                .proposals-admin .bulk-actions .button {
                    flex: 1;
                    min-width: 120px;
                    text-align: center;
                }
                
                /* Proposal items */
                .proposals-admin .proposal-item {
                    padding: 15px 10px;
                }
                .proposals-admin .proposal-item-content {
                    flex-wrap: wrap;
                }
                .proposals-admin .proposal-actions {
                    flex-direction: row;
                    width: 100%;
                    margin-top: 10px;
                    justify-content: flex-end;
                }
                .proposals-admin .proposal-actions .button {
                    min-width: 44px;
                    min-height: 44px;
                }
                
                /* Secciones colapsables */
                .proposals-admin .prompts-section,
                .proposals-admin .email-section,
                .proposals-admin .checkbox-section {
                    padding: 15px;
                }
                .proposals-admin .prompts-section h3,
                .proposals-admin .prompts-section h4,
                .proposals-admin .email-section h3 {
                    font-size: 16px;
                }
                
                /* Details/Summary */
                .proposals-admin details summary {
                    padding: 12px;
                    min-height: 44px;
                }
                
                /* Postbox */
                .proposals-admin .postbox {
                    margin: 0;
                }
                .proposals-admin .postbox-header {
                    padding: 12px;
                }
                .proposals-admin .inside {
                    padding: 15px !important;
                }
            }
            
            /* Móviles pequeños (480px y menos) */
            @media screen and (max-width: 480px) {
                .proposals-admin h1.wp-heading-inline {
                    font-size: 18px;
                }
                .proposals-admin .proposal-email {
                    font-size: 13px;
                }
                .proposals-admin .status-badge {
                    display: block;
                    margin: 5px 0 0 0;
                    width: fit-content;
                }
            }
            
            /* Touch-friendly improvements */
            @media (hover: none) and (pointer: coarse) {
                .proposals-admin input[type="checkbox"] {
                    width: 22px;
                    height: 22px;
                }
                .proposals-admin .button,
                .proposals-admin input,
                .proposals-admin select,
                .proposals-admin textarea {
                    min-height: 48px;
                }
                .proposals-admin .proposal-checkbox {
                    margin-top: 0 !important;
                }
            }
            
            /* Safe area para iPhones con notch */
            @supports (padding-bottom: env(safe-area-inset-bottom)) {
                @media screen and (max-width: 767px) {
                    .proposals-admin .submit {
                        padding-bottom: calc(20px + env(safe-area-inset-bottom));
                    }
                }
            }
        </style>
        
        <h1 class="wp-heading-inline">Panel de Aprobación de Propuestas</h1>
        
        <?php echo $message; ?>

        <div class="proposals-layout">
            <!-- LISTA DE PROPUESTAS -->
            <div class="proposals-sidebar">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">Últimas Solicitudes</h2>
                    </div>
                    <div class="inside" style="padding: 0;">
                        <form method="POST" id="bulk-delete-form">
                            <?php wp_nonce_field('bulk_delete_proposals', 'automatiza_bulk_nonce'); ?>
                            
                            <!-- Acciones masivas -->
                            <div class="bulk-actions">
                                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                    <input type="checkbox" id="select-all-proposals" style="margin: 0;">
                                    <span style="font-size: 12px;">Todos</span>
                                </label>
                                <button type="submit" name="bulk_delete" value="1" class="button button-small" style="color: #b32d2e;" 
                                    onclick="return confirm('¿Estás seguro de eliminar las propuestas seleccionadas?');">
                                    🗑️ Eliminar seleccionadas
                                </button>
                            </div>
                            
                            <ul class="proposal-list">
                                <?php foreach ($proposals as $p): ?>
                                    <li class="proposal-item <?php echo ($edit_proposal && $edit_proposal->id == $p->id) ? 'active' : ''; ?>">
                                        <div class="proposal-item-content">
                                            <input type="checkbox" name="proposal_ids[]" value="<?php echo $p->id; ?>" class="proposal-checkbox" style="margin-top: 3px;">
                                            <div style="flex: 1; min-width: 0;">
                                                <strong class="proposal-email" title="<?php echo esc_attr($p->client_email); ?>">
                                                    <?php echo esc_html($p->client_email); ?>
                                                </strong>
                                                <small style="color: #666;"><?php echo $p->created_at; ?></small>
                                                <?php if ($p->status === 'sent'): ?>
                                                    <span class="status-badge status-sent">Enviada</span>
                                                <?php elseif ($p->status === 'pending'): ?>
                                                    <span class="status-badge status-pending">Pendiente</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="proposal-actions">
                                                <a href="<?php echo admin_url('admin.php?page=automatiza-proposals&edit_id=' . $p->id); ?>" class="button button-small" title="Editar">✏️</a>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=automatiza-proposals&delete_id=' . $p->id), 'delete_proposal_' . $p->id); ?>" 
                                                   class="button button-small" style="color: #b32d2e;" title="Eliminar"
                                                   onclick="return confirm('¿Estás seguro de eliminar esta propuesta?');">🗑️</a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($proposals)): ?>
                                    <li class="proposal-item" style="text-align: center; color: #666;">No hay propuestas registradas</li>
                                <?php endif; ?>
                            </ul>
                        </form>
                    </div>
                </div>
                
                <script>
                document.getElementById('select-all-proposals')?.addEventListener('change', function() {
                    document.querySelectorAll('.proposal-checkbox').forEach(cb => cb.checked = this.checked);
                });
                </script>
            </div>

            <!-- FORMULARIO DE EDICIÓN -->
            <div class="proposals-main">
                <?php if ($edit_proposal): ?>
                    <div class="postbox">
                        <div class="postbox-header"><h2 class="hndle">Aprobar y Enviar: <?php echo esc_html($edit_proposal->client_email); ?></h2></div>
                        <div class="inside">
                            
                            <div class="preview-box">
                                <h3 style="margin-top: 0;">👁️ Vista Previa</h3>
                                <p>Verifica cómo verá el cliente la propuesta antes de enviarla:</p>
                                <div class="preview-buttons">
                                    <a href="<?php echo get_site_url() . '/ver-presentacion.php?id=' . $edit_proposal->unique_link_id; ?>" target="_blank" class="button button-secondary">
                                        <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> Ver Presentación
                                    </a>
                                    <a href="<?php echo get_site_url() . '/ver-demo.php?id=' . $edit_proposal->unique_link_id; ?>" target="_blank" class="button button-secondary">
                                        <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> Ver Demo Chatbot
                                    </a>
                                </div>
                            </div>

                            <form method="POST" enctype="multipart/form-data">
                                <button type="submit" style="display:none" tabindex="-1" aria-hidden="true"></button>
                                <?php wp_nonce_field('save_proposal', 'automatiza_proposal_nonce'); ?>
                                <input type="hidden" name="proposal_id" value="<?php echo $edit_proposal->id; ?>">
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="client_email">📧 Email del Cliente</label></th>
                                        <td>
                                            <input type="email" name="client_email" id="client_email" class="regular-text" required value="<?php echo esc_attr($edit_proposal->client_email); ?>">
                                            <p class="description">Este es el correo donde se enviará la propuesta.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="client_name">Nombre del Cliente</label></th>
                                        <td><input type="text" name="client_name" id="client_name" class="regular-text" required value="<?php echo esc_attr($edit_proposal->client_name); ?>"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="company_name">Nombre de la Empresa</label></th>
                                        <td><input type="text" name="company_name" id="company_name" class="regular-text" required value="<?php echo esc_attr($edit_proposal->company_name); ?>"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="phone">📱 Teléfono</label></th>
                                        <td>
                                            <input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($edit_proposal->phone ?? ''); ?>" placeholder="+56 9 1234 5678">
                                            <p class="description">Teléfono de contacto del cliente (para seguimientos)</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="gamma_url">URL Iframe Gamma</label></th>
                                        <td>
                                            <input type="text" name="gamma_url" id="gamma_url" class="large-text" placeholder="Pega aquí el código <iframe> o la URL directa..." required value="<?php echo esc_attr($edit_proposal->gamma_iframe_url); ?>">
                                            <p class="description">Puedes pegar el código completo del Embed de Gamma (&lt;iframe...&gt;) o solo la URL.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="n8n_url">URL Webhook n8n</label></th>
                                        <td>
                                            <?php 
                                            // URL por defecto del webhook dinámico
                                            $default_n8n_url = 'https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat';
                                            $current_n8n_url = !empty($edit_proposal->n8n_chat_url) ? $edit_proposal->n8n_chat_url : $default_n8n_url;
                                            ?>
                                            <input type="url" name="n8n_url" id="n8n_url" class="large-text" placeholder="https://n8n.tu-dominio.com/webhook/..." required value="<?php echo esc_attr($current_n8n_url); ?>">
                                            <p class="description">URL del webhook de n8n para el chatbot. Por defecto usa el Agente Dinámico que carga el prompt automáticamente.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="pdf_file">Adjuntar PDF de Respaldo</label></th>
                                        <td>
                                            <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf">
                                            <?php if ($edit_proposal->pdf_path): ?>
                                                <p class="description" style="color: green;">PDF actual: <a href="<?php echo $edit_proposal->pdf_path; ?>" target="_blank">Ver archivo</a></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>

                                <!-- SECCIÓN DE PROMPTS -->
                                <div class="prompts-section">
                                    <h3 style="margin-top: 0; color: #0369a1;">🤖 Prompts del Sistema</h3>
                                    <p style="color: #0369a1; margin-bottom: 15px;">Visualiza y edita los prompts generados por la IA.</p>
                                    
                                    <!-- PROMPT GAMMA -->
                                    <div style="margin-bottom: 25px;">
                                        <h4 style="margin: 0 0 10px 0; color: #0369a1;">📊 Prompt para Gamma (Presentación)</h4>
                                        
                                        <details style="margin-bottom: 10px;">
                                            <summary style="cursor: pointer; color: #0369a1; font-weight: 500; padding: 8px; background: #e0f2fe; border-radius: 4px;">
                                                👁️ Ver prompt actual (clic para expandir)
                                            </summary>
                                            <div style="margin-top: 10px; padding: 15px; background: #fff; border: 1px solid #bae6fd; border-radius: 4px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 12px; line-height: 1.5;">
<?php echo esc_html($edit_proposal->gamma_prompt_text ?: '(Sin prompt guardado)'); ?>
                                            </div>
                                        </details>
                                        
                                        <label for="gamma_prompt" style="display: block; margin-bottom: 5px; font-weight: 500;">✏️ Editar prompt:</label>
                                        <?php $gamma_prompt_es_v3 = ($edit_proposal->flujo ?? '') === 'v3'; ?>
                                        <textarea name="gamma_prompt" id="gamma_prompt" class="large-text" rows="6" <?php echo $gamma_prompt_es_v3 ? 'readonly' : ''; ?>
                                            placeholder="Deja vacío para mantener el actual..."><?php echo esc_textarea($edit_proposal->gamma_prompt_text); ?></textarea>
                                        <?php if ($gamma_prompt_es_v3): ?><p style="margin:5px 0 0 0;color:#b45309;font-size:13px;">En propuestas v3 este campo lo maneja el flujo; los cambios se piden en «Revisión de la propuesta».</p><?php endif; ?>
                                    </div>
                                    
                                    <!-- PROMPT CHATBOT -->
                                    <div>
                                        <h4 style="margin: 0 0 10px 0; color: #0369a1;">🤖 Prompt del Chatbot (System Prompt)</h4>
                                        
                                        <details style="margin-bottom: 10px;">
                                            <summary style="cursor: pointer; color: #0369a1; font-weight: 500; padding: 8px; background: #e0f2fe; border-radius: 4px;">
                                                👁️ Ver prompt actual (clic para expandir)
                                            </summary>
                                            <div style="margin-top: 10px; padding: 15px; background: #fff; border: 1px solid #bae6fd; border-radius: 4px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 12px; line-height: 1.5;">
<?php echo esc_html($edit_proposal->system_prompt_text ?: '(Sin prompt guardado)'); ?>
                                            </div>
                                        </details>
                                        
                                        <label for="system_prompt" style="display: block; margin-bottom: 5px; font-weight: 500;">✏️ Editar prompt:</label>
                                        <textarea name="system_prompt" id="system_prompt" class="large-text" rows="8" 
                                            placeholder="Deja vacío para mantener el actual..."><?php echo esc_textarea($edit_proposal->system_prompt_text); ?></textarea>
                                    </div>
                                </div>

                                <!-- SECCIÓN DE DETALLES DE SEGUIMIENTO -->
                                <?php if (function_exists('automatiza_render_prospect_details')): ?>
                                <div class="tracking-section" style="margin-top: 20px; padding: 20px; background: #faf5ff; border: 1px solid #a855f7; border-radius: 8px;">
                                    <h3 style="margin-top: 0; color: #7c3aed;">📋 Detalles de Seguimiento</h3>
                                    <p style="color: #7c3aed; margin-bottom: 15px;">Registra reuniones, cotizaciones, estados y notas del prospecto.</p>
                                    <?php automatiza_render_prospect_details($edit_proposal->id); ?>
                                </div>
                                <?php endif; ?>

                                <?php if (($edit_proposal->flujo ?? '') === 'v3'):
                                    $pl = json_decode((string) $edit_proposal->gamma_prompt_text, true) ?: [];
                                    $costo = at_propuesta_costo_fotos($pl);
                                    $filas = $pl['pricing_rows'] ?? [];
                                    for ($i = count($filas); $i < 6; $i++) { $filas[] = ['service' => '', 'price_label' => '']; }
                                    $log = json_decode((string) $edit_proposal->feedback_log, true) ?: [];
                                ?>
                                <div class="v3-section" style="margin-top:20px;padding:20px;background:#f0fdfa;border:1px solid #14b8a6;border-radius:8px;">
                                  <h3 style="margin-top:0;color:#0f766e;">🔁 Revisión de la propuesta (flujo v3)</h3>
                                  <p>Estado: <strong><?php echo esc_html($edit_proposal->status); ?></strong>
                                     <?php if ($edit_proposal->status_note): ?> — <?php echo esc_html($edit_proposal->status_note); ?><?php endif; ?>
                                     · <a href="<?php echo esc_url($edit_proposal->gamma_iframe_url); ?>" target="_blank">Ver vista previa</a></p>
                                  <?php wp_nonce_field('at_v3_' . $edit_proposal->id); ?>
                                  <table class="widefat" style="max-width:820px;">
                                    <thead><tr><th>Servicio</th><th>Precio (texto tal cual)</th><th>Destacar</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($filas as $i => $f): ?>
                                      <tr>
                                        <td><input type="text" class="regular-text" name="at_precio[<?php echo $i; ?>][service]" value="<?php echo esc_attr($f['service'] ?? ''); ?>"></td>
                                        <td><input type="text" class="regular-text" name="at_precio[<?php echo $i; ?>][price_label]" value="<?php echo esc_attr($f['price_label'] ?? ''); ?>"></td>
                                        <td><input type="checkbox" name="at_precio[<?php echo $i; ?>][emphasis]" value="1" <?php checked(!empty($f['emphasis'])); ?>></td>
                                      </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                  <p><label>Nota de precios<br><textarea name="at_nota_precio" rows="2" class="large-text"><?php echo esc_textarea($pl['pricing_note'] ?? ''); ?></textarea></label></p>
                                  <p><label>Comentarios para ajustar (qué cambiar en textos, láminas o chatbot)<br><textarea name="at_comentario" rows="4" class="large-text"></textarea></label></p>
                                  <?php if ($log): ?><details><summary>Historial de comentarios (<?php echo count($log); ?>)</summary><ul>
                                    <?php foreach ($log as $c): ?><li><?php echo esc_html($c['fecha'] . ' — ' . $c['comentario']); ?></li><?php endforeach; ?>
                                  </ul></details><?php endif; ?>
                                  <p>
                                    <button type="submit" name="at_v3_accion" value="cambios" class="button">✏️ Pedir cambios (sin costo)</button>
                                    <button type="submit" name="at_v3_accion" value="aprobar" class="button button-primary"
                                      onclick="return confirm('Se generarán <?php echo (int) $costo['fotos']; ?> fotos (≈ US$<?php echo esc_js(number_format($costo['usd_lista'], 4, ',', '.')); ?> de lista) y la versión final. ¿Aprobar?');">
                                      ✅ Aprobar y generar versión final (<?php echo (int) $costo['fotos']; ?> fotos ≈ US$<?php echo esc_html(number_format($costo['usd_lista'], 4, ',', '.')); ?>)</button>
                                    <?php if (in_array($edit_proposal->status, ['ajustando', 'generando'], true)): ?>
                                    <button type="submit" name="at_v3_accion" value="destrabar" class="button"
                                      onclick="return confirm('¿Destrabar esta propuesta? Va a quedar en estado «error» para poder reintentar. No se llama a n8n ni se tocan precios.');">
                                      🔓 Destrabar (pasar a error)</button>
                                    <?php endif; ?>
                                  </p>
                                </div>
                                <script>
                                (function () {
                                    document.querySelectorAll('.v3-section input').forEach(function (el) {
                                        el.addEventListener('keydown', function (e) {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                            }
                                        });
                                    });
                                })();
                                </script>
                                <?php endif; ?>

                                <!-- CHECKBOX PARA ENVIAR CORREO -->
                                <div class="checkbox-section">
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 15px;">
                                        <?php
                                        $puede = at_propuesta_puede_enviarse($edit_proposal->flujo ?? null, (string) $edit_proposal->status);
                                        $es_v3_checkbox = ($edit_proposal->flujo ?? '') === 'v3';
                                        $send_email_attr = !$puede ? 'disabled' : ($es_v3_checkbox ? '' : 'checked');
                                        ?>
                                        <input type="checkbox" name="send_email" value="1" id="send_email" <?php echo $send_email_attr; ?> style="width: 20px; height: 20px;">
                                        <span style="color: #065f46; font-weight: 600;">📧 Enviar correo con la propuesta al cliente</span>
                                    </label>
                                    <p style="margin: 8px 0 0 30px; color: #047857; font-size: 13px;">Si desmarcas esta opción, solo se guardarán los datos sin enviar el correo.</p>
                                    <?php if (!$puede): ?><p style="margin:8px 0 0 30px;color:#b45309;">Se habilita cuando la propuesta esté <strong>lista</strong>.</p><?php endif; ?>
                                </div>

                                <!-- SECCIÓN DE PERSONALIZACIÓN DEL CORREO -->
                                <div class="email-section">
                                    <h3 style="margin-top: 0; color: #856404;">✉️ Personalizar Contenido del Correo</h3>
                                    <p style="color: #856404; margin-bottom: 15px;">Edita el contenido del correo antes de enviarlo. Deja en blanco para usar el texto por defecto.</p>
                                    
                                    <table class="form-table" style="margin: 0;">
                                        <tr>
                                            <th scope="row"><label for="email_subject">Asunto del Correo</label></th>
                                            <td>
                                                <input type="text" name="email_subject" id="email_subject" class="large-text" 
                                                    placeholder="Propuesta de Automatización Inteligente - <?php echo esc_attr($edit_proposal->company_name ?: '[Nombre Empresa]'); ?>"
                                                    value="">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="email_intro">Párrafo de Introducción</label></th>
                                            <td>
                                                <textarea name="email_intro" id="email_intro" class="large-text" rows="3" 
                                                    placeholder="Es un placer presentarle nuestra propuesta de automatización inteligente diseñada específicamente para [Nombre Empresa]."></textarea>
                                                <p class="description">Texto después del saludo "Estimado/a [Nombre],"</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="email_highlight">Caja Destacada (¿Qué incluye?)</label></th>
                                            <td>
                                                <textarea name="email_highlight" id="email_highlight" class="large-text" rows="3" 
                                                    placeholder="Hemos analizado sus requerimientos y preparado una solución personalizada que optimizará sus procesos de negocio mediante inteligencia artificial."></textarea>
                                                <p class="description">Texto dentro del recuadro azul destacado.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="email_closing">Párrafo de Cierre</label></th>
                                            <td>
                                                <textarea name="email_closing" id="email_closing" class="large-text" rows="2" 
                                                    placeholder="Quedamos atentos a sus comentarios y consultas."></textarea>
                                                <p class="description">Texto antes de "Atentamente, El equipo de Automatiza Tech"</p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <p class="submit">
                                    <button type="submit" class="button button-primary button-large">💾 Guardar Propuesta</button>
                                </p>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="notice notice-info inline"><p>Selecciona una propuesta de la lista para editarla y enviarla.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>