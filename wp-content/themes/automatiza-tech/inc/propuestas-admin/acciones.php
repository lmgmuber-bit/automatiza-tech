<?php
/**
 * Módulo Propuestas: los procesos de POST/GET de siempre, extraídos sin cambiar lógica ni nombres de campo.
 * Se despachan por acción explícita: una acción desconocida no cae en el guardado.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Ejecuta la acción que venga en la petición y devuelve el aviso HTML ('' si no hubo acción). */
function at_pa_procesar_acciones(): string {
    if (!current_user_can('manage_options')) {
        return '';
    }
    if (isset($_GET['delete_id'], $_GET['_wpnonce'])) {
        return at_pa_borrar_una();
    }
    // Borrado masivo de la lista (WP_List_Table, formulario GET): action o action2 = borrar,
    // solo cuando lo envía un botón «Aplicar» real (name="bulk_action" en #doaction/#doaction2,
    // WP 6.9.4 y 7.1.2). Buscar (sin name), Filtrar (name="filtrar") y Enter (botón Buscar) no
    // mandan bulk_action, así que ya no pueden caer en el borrado masivo.
    if (isset($_GET['bulk_action'])) {
        foreach (['action', 'action2'] as $k) {
            $v = sanitize_key(wp_unslash($_GET[$k] ?? ''));
            if ($v === 'borrar' && !empty($_GET['proposal_ids'])) {
                return at_pa_borrar_varias(array_map('intval', (array) $_GET['proposal_ids']));
            }
        }
    }
    // Botón «🗑️ Borrar marcadas» de la lista (name="at_borrar_marcadas" value="1"), con nombre
    // propio porque common.js de WordPress intercepta cualquier submit con name="bulk_action" y
    // lo bloquea si el <select> de acción masiva sigue en "-1" (nuestro botón no lo cambia).
    // Buscar, Filtrar y Enter no mandan ni bulk_action ni at_borrar_marcadas.
    if (isset($_GET['at_borrar_marcadas']) && !empty($_GET['proposal_ids'])) {
        return at_pa_borrar_varias(array_map('intval', (array) $_GET['proposal_ids']));
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return '';
    }
    if (isset($_POST['at_v3_accion'], $_POST['proposal_id'])) {
        return at_pa_accion_v3();
    }
    if (isset($_POST['proposal_id'], $_POST['automatiza_proposal_nonce'])) {
        return at_pa_guardar();
    }
    return '';
}

/** Borrar una propuesta (enlace con nonce delete_proposal_<id>). Líneas 79-88 de d32ae3a. */
function at_pa_borrar_una(): string {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_proposal_' . $_GET['delete_id'])) {
            $delete_id = intval($_GET['delete_id']);
            $wpdb->delete($table_name, ['id' => $delete_id]);
            $message = '<div class="notice notice-success is-dismissible"><p>🗑️ Propuesta eliminada correctamente.</p></div>';
        } else {
            $message = '<div class="notice notice-error"><p>Error de seguridad al eliminar.</p></div>';
        }
    return $message;
}

/** Borrar varias desde la lista (nonce de WP_List_Table: bulk-propuestas). */
function at_pa_borrar_varias(array $ids): string {
    global $wpdb;
    check_admin_referer('bulk-propuestas');
    $ids = array_values(array_filter($ids, function ($i) { return $i > 0; }));
    if (!$ids) {
        return '';
    }
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($ids_placeholder)", ...$ids));
    return '<div class="notice notice-success is-dismissible"><p>🗑️ ' . count($ids) . ' propuesta(s) eliminada(s) correctamente.</p></div>';
}

/** Botones v3 (cambios, aprobar, destrabar). Cuerpo de las líneas 92-135 de d32ae3a. */
function at_pa_accion_v3(): string {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';
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
            // Si vinieron los cuatro campos del correo (siempre, porque son del mismo formulario), se guardan
            // en el payload también aquí, para que lo que Luis editó viaje a n8n aunque falte un precio.
            if (isset($_POST['email_subject'], $_POST['email_intro'], $_POST['email_highlight'], $_POST['email_closing'])) {
                $payload = at_pa_payload_con_correo($payload, [
                    'asunto'       => sanitize_text_field(wp_unslash($_POST['email_subject'])),
                    'introduccion' => sanitize_textarea_field(wp_unslash($_POST['email_intro'])),
                    'que_incluye'  => sanitize_textarea_field(wp_unslash($_POST['email_highlight'])),
                    'cierre'       => sanitize_textarea_field(wp_unslash($_POST['email_closing'])),
                ]);
            }
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
    return $message;
}

/** Guardado normal (y envío del correo si corresponde). Cuerpo de las líneas 140-371 de d32ae3a. */
function at_pa_guardar(): string {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';
        // Verificar nonce para seguridad
        if (!isset($_POST['automatiza_proposal_nonce']) || !wp_verify_nonce($_POST['automatiza_proposal_nonce'], 'save_proposal')) {
            return '<div class="notice notice-error"><p>Error de seguridad. Intente nuevamente.</p></div>';
        }

        $id = intval($_POST['proposal_id']);
        $client_name = sanitize_text_field($_POST['client_name']);
        $company_name = sanitize_text_field($_POST['company_name']);
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $client_email = sanitize_email($_POST['client_email']);
        $email_subject = sanitize_text_field(wp_unslash($_POST['email_subject']));
        $email_intro = sanitize_textarea_field(wp_unslash($_POST['email_intro']));
        $email_highlight = sanitize_textarea_field(wp_unslash($_POST['email_highlight']));
        $email_closing = sanitize_textarea_field(wp_unslash($_POST['email_closing']));
        $textos = [
            'asunto'       => $email_subject,
            'introduccion' => $email_intro,
            'que_incluye'  => $email_highlight,
            'cierre'       => $email_closing,
        ];
        
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
                $message = '<div class="notice notice-error"><p>Error al subir PDF: ' . esc_html($movefile['error']) . '</p></div>';
            }
        }

        // Actualizar BD
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';
        $actual = $wpdb->get_row($wpdb->prepare("SELECT flujo, status, gamma_prompt_text FROM {$table_name} WHERE id = %d", $id));
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

        // Guardar los cuatro textos del correo en el payload (correo_cliente), salvo si n8n
        // podría estar escribiendo el mismo payload en paralelo (v3 en ajustando/generando).
        $actual_status = $actual ? (string) $actual->status : '';
        if (!($es_v3 && in_array($actual_status, ['ajustando', 'generando'], true))) {
            $base_json = array_key_exists('gamma_prompt_text', $update_data) ? $update_data['gamma_prompt_text'] : (string) ($actual->gamma_prompt_text ?? '');
            $base_decodificada = json_decode((string) $base_json, true);
            if (is_array($base_decodificada)) {
                $update_data['gamma_prompt_text'] = wp_json_encode(at_pa_payload_con_correo($base_decodificada, $textos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
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
            // Texto sugerido si el campo vino vacío (el que escribió la IA, o el genérico armado del contenido).
            $payload_envio = json_decode((string) $proposal->gamma_prompt_text, true);
            $correo_defecto = at_pa_correo_textos(is_array($payload_envio) ? $payload_envio : null, (string) $company_name);

            $subject = !empty($email_subject) ? $email_subject : $correo_defecto['asunto'];

            $link_presentacion = get_site_url() . '/ver-presentacion.php?id=' . $proposal->unique_link_id;
            $link_demo = get_site_url() . '/ver-demo.php?id=' . $proposal->unique_link_id;

            // Obtener logo y datos del sitio
            $site_title = get_bloginfo('name');
            $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
            $footer_text = get_bloginfo('description');

            // Contenido personalizable del email
            $intro_text = !empty($email_intro) ? nl2br(esc_html($email_intro)) : nl2br(esc_html($correo_defecto['introduccion']));
            $highlight_text = !empty($email_highlight) ? nl2br(esc_html($email_highlight)) : nl2br(esc_html($correo_defecto['que_incluye']));
            $closing_text = !empty($email_closing) ? nl2br(esc_html($email_closing)) : nl2br(esc_html($correo_defecto['cierre']));

            // Preparar adjuntos - Verificar si hay PDF actual o recién subido
            $attachments = array();
            $pdf_tmp_path = '';
            $pdf_tmp_dir = '';
            $pdf_omitido_motivo = '';

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

            // Si sigue sin haber adjunto, bajar el PDF de la presentación desde el renderer (tope AT_PA_PDF_MAX_BYTES).
            if (empty($attachments)) {
                $pdf_renderer_url = at_pa_url_pdf_renderer((string) $proposal->gamma_iframe_url, (string) $proposal->pdf_path);
                if ($pdf_renderer_url !== '') {
                    $pdf_tmp_dir = trailingslashit(get_temp_dir()) . 'at-propuesta-' . $id;
                    $tmp = trailingslashit($pdf_tmp_dir) . 'Propuesta-' . sanitize_file_name($company_name ?: 'AutomatizaTech') . '.pdf';
                    wp_mkdir_p($pdf_tmp_dir);
                    $pdf_resp = wp_remote_get($pdf_renderer_url, [
                        'timeout'             => 60,
                        'stream'              => true,
                        'filename'            => $tmp,
                        'limit_response_size' => AT_PA_PDF_MAX_BYTES + 1,
                    ]);
                    $pdf_codigo = is_wp_error($pdf_resp) ? 0 : (int) wp_remote_retrieve_response_code($pdf_resp);
                    $pdf_tamano = file_exists($tmp) ? filesize($tmp) : 0;
                    $pdf_cabecera = $pdf_tamano ? (string) @file_get_contents($tmp, false, null, 0, 4) : '';
                    if (is_wp_error($pdf_resp) || $pdf_codigo !== 200) {
                        $pdf_omitido_motivo = 'no se pudo descargar el PDF de la presentación';
                    } elseif ($pdf_tamano > AT_PA_PDF_MAX_BYTES) {
                        $pdf_omitido_motivo = 'el PDF pesa más de 15 MB';
                    } elseif ($pdf_cabecera !== '%PDF') {
                        $pdf_omitido_motivo = 'no se pudo descargar el PDF de la presentación';
                    } else {
                        $attachments[] = $tmp;
                        $pdf_tmp_path = $tmp;
                    }
                    // Si no quedó adjunto, no dejar el archivo ni la carpeta a medio bajar.
                    if ($pdf_tmp_path === '') {
                        if (file_exists($tmp)) {
                            @unlink($tmp);
                        }
                        @rmdir($pdf_tmp_dir);
                        $pdf_tmp_dir = '';
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

            // El PDF bajado del renderer es temporal: se borra se haya enviado el correo o no.
            if ($pdf_tmp_path !== '') {
                @unlink($pdf_tmp_path);
            }
            if ($pdf_tmp_dir !== '') {
                @rmdir($pdf_tmp_dir);
            }

            if ($sent) {
                if (!empty($attachments)) {
                    $attachment_msg = ' (con PDF adjunto)';
                } elseif ($pdf_omitido_motivo !== '') {
                    $attachment_msg = ' (sin PDF adjunto: ' . $pdf_omitido_motivo . '; el cliente lo baja desde la presentación)';
                } else {
                    $attachment_msg = ' (sin PDF adjunto)';
                }
                $message = '<div class="notice notice-success is-dismissible"><p>Propuesta actualizada y correo enviado a ' . esc_html($to) . esc_html($attachment_msg) . '</p></div>';
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
    return $message;
}
