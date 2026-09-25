<?php
/**
 * Ficha de una propuesta: un solo formulario con 6 pestañas (Revisión solo en v3) y barra de guardado fija.
 * Los nombres de campo y los procesos son los mismos del módulo anterior (acciones.php).
 */
if (!defined('ABSPATH')) {
    exit;
}

function at_pa_render_ficha($p, string $message): void {
    $es_v3 = ($p->flujo ?? '') === 'v3';
    $pestanas = at_pa_pestanas($es_v3);
    $tab = sanitize_key(wp_unslash($_POST['at_tab'] ?? ($_GET['tab'] ?? 'resumen')));
    if (!isset($pestanas[$tab])) {
        $tab = 'resumen';
    }
    $volver_get = is_string($_GET['volver'] ?? null) ? wp_unslash($_GET['volver']) : '';
    $url_lista = add_query_arg(array_map('rawurlencode', at_pa_volver_desde_param($volver_get)) + ['page' => 'automatiza-proposals'], admin_url('admin.php'));
    $estado = at_pa_estado_etiqueta($p->status);
    $link_pres = get_site_url() . '/ver-presentacion.php?id=' . rawurlencode((string) $p->unique_link_id);
    $link_demo = get_site_url() . '/ver-demo.php?id=' . rawurlencode((string) $p->unique_link_id);
    $precios = at_pa_precios_de_payload($p->gamma_prompt_text);
    $ultimo = at_pa_ultimo_comentario($p->feedback_log ?? null);
    $paso = at_pa_siguiente_paso($p->flujo ?? null, $p->status);
    $sub = array_filter([(string) $p->client_name, 'id ' . (int) $p->id, 'creada ' . at_pa_fecha_corta($p->created_at)]);
    ?>
    <div class="wrap at-pa at-pa-ficha">
      <p class="at-pa-volver"><a href="<?php echo esc_url($url_lista); ?>">← Volver a la lista</a></p>
      <div class="at-pa-cabecera">
        <div>
          <h1><?php echo esc_html((string) $p->company_name !== '' ? $p->company_name : '(sin empresa)'); ?></h1>
          <p class="at-pa-sub"><?php echo esc_html(implode(' · ', $sub)); ?></p>
        </div>
        <div><span class="at-estado <?php echo esc_attr($estado['clase']); ?>"><?php echo esc_html($estado['etiqueta']); ?></span><?php echo $es_v3 ? ' <span class="at-marca-v3">v3</span>' : ''; ?></div>
      </div>
      <hr class="wp-header-end">
      <?php echo $message; ?>

      <form method="POST" enctype="multipart/form-data" class="at-pa-form" data-tab-inicial="<?php echo esc_attr($tab); ?>">
        <button type="submit" style="display:none" tabindex="-1" aria-hidden="true"></button>
        <?php wp_nonce_field('save_proposal', 'automatiza_proposal_nonce'); ?>
        <input type="hidden" name="proposal_id" value="<?php echo (int) $p->id; ?>">
        <input type="hidden" name="at_tab" value="<?php echo esc_attr($tab); ?>" class="at-pa-tab-actual">

        <nav class="at-pa-tabs" role="tablist" aria-label="Secciones de la propuesta">
          <?php foreach ($pestanas as $k => $titulo): ?>
            <button type="button" role="tab" class="at-pa-tab" data-tab="<?php echo esc_attr($k); ?>" aria-selected="<?php echo $k === $tab ? 'true' : 'false'; ?>"><?php echo esc_html($titulo); ?></button>
          <?php endforeach; ?>
        </nav>
        <select class="at-pa-tabs-movil" aria-label="Sección">
          <?php foreach ($pestanas as $k => $titulo): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($k, $tab); ?>><?php echo esc_html($titulo); ?></option>
          <?php endforeach; ?>
        </select>

        <section class="at-pa-panel" data-panel="resumen" role="tabpanel">
          <div class="at-pa-resumen">
            <div class="at-pa-caja"><h3>Contacto</h3>
              <?php echo $p->client_email ? sprintf('<a href="%s">%s</a>', esc_url('mailto:' . $p->client_email), esc_html($p->client_email)) : '—'; ?>
              <?php echo $p->phone ? '<br>' . esc_html($p->phone) : ''; ?>
            </div>
            <div class="at-pa-caja"><h3>Enlaces</h3>
              <div class="at-pa-enlaces">
                <a class="button" href="<?php echo esc_url($link_pres); ?>" target="_blank" rel="noopener">📊 Presentación</a>
                <a class="button" href="<?php echo esc_url($link_demo); ?>" target="_blank" rel="noopener">🤖 Demo chatbot</a>
                <?php if (!empty($p->pdf_path)): ?><a class="button" href="<?php echo esc_url($p->pdf_path); ?>" target="_blank" rel="noopener">📄 PDF</a><?php endif; ?>
              </div>
            </div>
            <div class="at-pa-caja"><h3>Precios</h3>
              <?php if ($precios): ?><ul class="at-pa-lista-precios"><?php foreach ($precios as $r): ?><li><?php echo esc_html($r['service'] . ' · ' . $r['price_label']); ?></li><?php endforeach; ?></ul>
              <?php else: ?>—<?php endif; ?>
            </div>
            <div class="at-pa-caja"><h3>Último movimiento</h3>
              <?php if (!empty($p->status_note)): ?><p><?php echo esc_html($p->status_note); ?></p><?php endif; ?>
              <?php if ($ultimo['total']): ?><p><?php echo esc_html('Último comentario (' . $ultimo['total'] . '): ' . $ultimo['texto']); ?></p><?php endif; ?>
              <?php if (empty($p->status_note) && !$ultimo['total']): ?>—<?php endif; ?>
            </div>
          </div>
          <?php if ($paso): ?>
            <p class="at-pa-botones"><button type="button" class="button button-primary at-pa-ir" data-ir="<?php echo esc_attr($paso['tab']); ?>"><?php echo esc_html($paso['texto']); ?> →</button></p>
          <?php endif; ?>
        </section>

        <section class="at-pa-panel" data-panel="cliente" role="tabpanel">
          <table class="form-table">
            <tr>
              <th scope="row"><label for="client_email">📧 Email del Cliente</label></th>
              <td>
                <input type="email" name="client_email" id="client_email" class="regular-text" required value="<?php echo esc_attr($p->client_email); ?>">
                <p class="description">Este es el correo donde se enviará la propuesta.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="client_name">Nombre del Cliente</label></th>
              <td><input type="text" name="client_name" id="client_name" class="regular-text" required value="<?php echo esc_attr($p->client_name); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label for="company_name">Nombre de la Empresa</label></th>
              <td><input type="text" name="company_name" id="company_name" class="regular-text" required value="<?php echo esc_attr($p->company_name); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label for="phone">📱 Teléfono</label></th>
              <td>
                <input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($p->phone ?? ''); ?>" placeholder="+56 9 1234 5678">
                <p class="description">Teléfono de contacto del cliente (para seguimientos)</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="gamma_url">URL de la presentación</label></th>
              <td>
                <input type="text" name="gamma_url" id="gamma_url" class="large-text" placeholder="https://n8n-propuesta-renderer.kchiba.easypanel.host/p/…/index.html" required value="<?php echo esc_attr($p->gamma_iframe_url); ?>">
                <p class="description">La dirección de la presentación que genera el renderer de AutomatizaTech (servidor de n8n). El cliente la ve en automatizatech.cl/ver-presentacion.php.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="n8n_url">URL Webhook n8n</label></th>
              <td>
                <?php
                // URL por defecto del webhook dinámico
                $default_n8n_url = 'https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat';
                $current_n8n_url = !empty($p->n8n_chat_url) ? $p->n8n_chat_url : $default_n8n_url;
                ?>
                <input type="url" name="n8n_url" id="n8n_url" class="large-text" placeholder="https://n8n.tu-dominio.com/webhook/..." required value="<?php echo esc_attr($current_n8n_url); ?>">
                <p class="description">URL del webhook de n8n para el chatbot. Por defecto usa el Agente Dinámico que carga el prompt automáticamente.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="pdf_file">Adjuntar PDF de Respaldo</label></th>
              <td>
                <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf">
                <?php if ($p->pdf_path): ?>
                  <p class="description">PDF actual: <a href="<?php echo esc_url($p->pdf_path); ?>" target="_blank" rel="noopener">Ver archivo</a></p>
                <?php endif; ?>
              </td>
            </tr>
          </table>
        </section>

        <?php if ($es_v3):
            $pl = json_decode((string) $p->gamma_prompt_text, true) ?: [];
            $costo = at_propuesta_costo_fotos($pl);
            $filas = is_array($pl['pricing_rows'] ?? null) ? array_values($pl['pricing_rows']) : [];
            for ($i = count($filas); $i < 6; $i++) { $filas[] = ['service' => '', 'price_label' => '']; }
            $log = json_decode((string) $p->feedback_log, true) ?: [];
        ?>
        <section class="at-pa-panel v3-section" data-panel="revision" role="tabpanel">
          <p>Estado: <strong><?php echo esc_html($p->status); ?></strong>
             <?php if ($p->status_note): ?> — <?php echo esc_html($p->status_note); ?><?php endif; ?>
             · <a href="<?php echo esc_url($p->gamma_iframe_url); ?>" target="_blank" rel="noopener">Ver vista previa</a></p>
          <?php wp_nonce_field('at_v3_' . $p->id); ?>
          <table class="widefat at-pa-precios">
            <thead><tr><th>Servicio</th><th>Precio (texto tal cual)</th><th>Destacar</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $i => $f): $f = is_array($f) ? $f : []; ?>
              <tr>
                <td data-titulo="Servicio"><input type="text" name="at_precio[<?php echo (int) $i; ?>][service]" value="<?php echo esc_attr($f['service'] ?? ''); ?>"></td>
                <td data-titulo="Precio"><input type="text" name="at_precio[<?php echo (int) $i; ?>][price_label]" value="<?php echo esc_attr($f['price_label'] ?? ''); ?>"></td>
                <td data-titulo="Destacar"><input type="checkbox" name="at_precio[<?php echo (int) $i; ?>][emphasis]" value="1" <?php checked(!empty($f['emphasis'])); ?>></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <p><label>Nota de precios<br><textarea name="at_nota_precio" rows="2" class="large-text"><?php echo esc_textarea($pl['pricing_note'] ?? ''); ?></textarea></label></p>
          <p><label>Comentarios para ajustar (qué cambiar en textos, láminas o chatbot)<br><textarea name="at_comentario" rows="4" class="large-text"></textarea></label></p>
          <?php if ($log): ?><details><summary>Historial de comentarios (<?php echo count($log); ?>)</summary><ul>
            <?php foreach ($log as $c): ?><li><?php echo esc_html(($c['fecha'] ?? '') . ' — ' . ($c['comentario'] ?? '')); ?></li><?php endforeach; ?>
          </ul></details><?php endif; ?>
          <p class="at-pa-aviso">Los precios, la nota y los comentarios se guardan con «Pedir cambios» o «Aprobar»; el botón Guardar no los guarda.</p>
          <p class="at-pa-botones">
            <button type="submit" name="at_v3_accion" value="cambios" class="button">✏️ Pedir cambios (sin costo)</button>
            <button type="submit" name="at_v3_accion" value="aprobar" class="button button-primary"
              onclick="return confirm('Se generarán <?php echo (int) $costo['fotos']; ?> fotos (≈ US$<?php echo esc_js(number_format($costo['usd_lista'], 4, ',', '.')); ?> de lista) y la versión final. ¿Aprobar?');">
              ✅ Aprobar y generar versión final (<?php echo (int) $costo['fotos']; ?> fotos ≈ US$<?php echo esc_html(number_format($costo['usd_lista'], 4, ',', '.')); ?>)</button>
            <?php if (in_array($p->status, ['ajustando', 'generando'], true)): ?>
            <button type="submit" name="at_v3_accion" value="destrabar" class="button"
              onclick="return confirm('¿Destrabar esta propuesta? Va a quedar en estado «error» para poder reintentar. No se llama a n8n ni se tocan precios.');">
              🔓 Destrabar (pasar a error)</button>
            <?php endif; ?>
          </p>
        </section>
        <?php endif; ?>

        <section class="at-pa-panel" data-panel="contenido" role="tabpanel">
          <h2>Transcripción de la reunión</h2>
          <?php if (trim((string) $p->transcript_text) !== ''): ?>
            <details><summary>Ver transcripción</summary>
              <textarea id="at-transcripcion" class="large-text" rows="14" readonly><?php echo esc_textarea($p->transcript_text); ?></textarea>
              <p><button type="button" class="button at-pa-copiar" data-copiar="#at-transcripcion">Copiar</button></p>
            </details>
          <?php else: ?><p>Esta propuesta no tiene transcripción guardada.</p><?php endif; ?>
          <div>
            <h4>📊 Contenido de la presentación</h4>
            <details>
              <summary>👁️ Ver prompt actual (clic para expandir)</summary>
              <div class="at-pa-codigo"><?php echo esc_html($p->gamma_prompt_text ?: '(Sin contenido guardado)'); ?></div>
            </details>
            <label for="gamma_prompt">✏️ Editar contenido:</label>
            <?php $gamma_prompt_es_v3 = ($p->flujo ?? '') === 'v3'; ?>
            <textarea name="gamma_prompt" id="gamma_prompt" class="large-text" rows="6" <?php echo $gamma_prompt_es_v3 ? 'readonly' : ''; ?>
                placeholder="Deja vacío para mantener el actual..."><?php echo esc_textarea($p->gamma_prompt_text); ?></textarea>
            <?php if ($gamma_prompt_es_v3): ?><p class="at-pa-aviso">En propuestas v3 este campo lo maneja el flujo; los cambios se piden en la pestaña «Revisión y precios».</p><?php endif; ?>
          </div>
          <div>
            <h4>🤖 Prompt del Chatbot (System Prompt)</h4>
            <details>
              <summary>👁️ Ver prompt actual (clic para expandir)</summary>
              <div class="at-pa-codigo"><?php echo esc_html($p->system_prompt_text ?: '(Sin prompt guardado)'); ?></div>
            </details>
            <label for="system_prompt">✏️ Editar prompt:</label>
            <textarea name="system_prompt" id="system_prompt" class="large-text" rows="8"
                placeholder="Deja vacío para mantener el actual..."><?php echo esc_textarea($p->system_prompt_text); ?></textarea>
          </div>
        </section>

        <section class="at-pa-panel" data-panel="seguimiento" role="tabpanel">
          <?php if (function_exists('automatiza_render_prospect_details')) { automatiza_render_prospect_details($p->id); } else { echo '<p>El módulo de seguimiento no está disponible.</p>'; } ?>
        </section>

        <?php
          $payload = json_decode((string) $p->gamma_prompt_text, true);
          $correo = at_pa_correo_textos(is_array($payload) ? $payload : null, (string) $p->company_name);
        ?>
        <section class="at-pa-panel" data-panel="envio" role="tabpanel">
          <div class="checkbox-section">
            <label>
              <?php
              $puede = at_propuesta_puede_enviarse($p->flujo ?? null, (string) $p->status);
              $es_v3_checkbox = ($p->flujo ?? '') === 'v3';
              $send_email_attr = !$puede ? 'disabled' : ($es_v3_checkbox ? '' : 'checked');
              ?>
              <input type="checkbox" name="send_email" value="1" id="send_email" <?php echo $send_email_attr; ?>>
              <span>📧 Enviar correo con la propuesta al cliente</span>
            </label>
            <p>Si desmarcas esta opción, solo se guardarán los datos sin enviar el correo.</p>
            <?php if (at_pa_url_pdf_renderer((string) $p->gamma_iframe_url, (string) $p->pdf_path) !== ''): ?>
            <p class="description">Se adjunta el PDF que subas en «Cliente y enlaces»; si no subiste uno, se adjunta el de la presentación (hasta 15 MB). Si pesa más, el correo lleva solo los botones.</p>
            <?php else: ?>
            <p class="description">Se adjunta el PDF que subas en «Cliente y enlaces» o el que ya esté guardado; sin PDF, el correo lleva solo los botones.</p>
            <?php endif; ?>
            <?php if (!$puede): ?><p class="at-pa-aviso">Se habilita cuando la propuesta esté <strong>lista</strong>.</p><?php endif; ?>
          </div>

          <div class="email-section">
            <h3>✉️ Personalizar Contenido del Correo</h3>
            <p>Texto sugerido según la reunión con el cliente. Puedes editarlo; se guarda al apretar Guardar.</p>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="email_subject">Asunto del Correo</label></th>
                <td>
                  <input type="text" name="email_subject" id="email_subject" class="large-text"
                      placeholder="Propuesta de Automatización Inteligente - <?php echo esc_attr($p->company_name ?: '[Nombre Empresa]'); ?>"
                      value="<?php echo esc_attr($correo['asunto']); ?>">
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="email_intro">Párrafo de Introducción</label></th>
                <td>
                  <textarea name="email_intro" id="email_intro" class="large-text" rows="3"><?php echo esc_textarea($correo['introduccion']); ?></textarea>
                  <p class="description">Texto después del saludo "Estimado/a [Nombre],"</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="email_highlight">Caja Destacada (¿Qué incluye?)</label></th>
                <td>
                  <textarea name="email_highlight" id="email_highlight" class="large-text" rows="3"><?php echo esc_textarea($correo['que_incluye']); ?></textarea>
                  <p class="description">Texto dentro del recuadro azul destacado.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="email_closing">Párrafo de Cierre</label></th>
                <td>
                  <textarea name="email_closing" id="email_closing" class="large-text" rows="2"><?php echo esc_textarea($correo['cierre']); ?></textarea>
                  <p class="description">Texto antes de "Atentamente, El equipo de Automatiza Tech"</p>
                </td>
              </tr>
            </table>
          </div>
        </section>

        <div class="at-pa-guardar">
          <p class="at-pa-guardar-aviso" hidden></p>
          <button type="submit" class="button button-primary button-large">💾 Guardar</button>
        </div>
      </form>
    </div>
    <?php
}
