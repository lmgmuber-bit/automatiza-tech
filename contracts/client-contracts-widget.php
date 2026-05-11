<?php
/**
 * AutomatizaTech — Widget de Contratos en la ficha del cliente
 * - Lista contratos del cliente
 * - Botón "Nuevo contrato" (abre form simple)
 * - Acciones rápidas: ver PDF, copiar link firma, eliminar
 *
 * Función pública: at_render_client_contracts_widget($client_obj)
 */

if (!defined('ABSPATH')) exit;

require_once ABSPATH . 'contracts/contract-service.php';

/* ==========================================================
 *  WIDGET — se renderiza en la pestaña "Contratos" de la ficha
 * ========================================================== */
function at_render_client_contracts_widget($client) {
    if (!$client || !isset($client->id)) {
        echo '<div class="cfm-section"><p>Cliente inválido.</p></div>'; return;
    }
    $client_id = intval($client->id);
    $contracts = ContractService::list_by_client($client_id);
    $nonce     = wp_create_nonce('at_contract_widget_' . $client_id);

    // Cargar servicios activos para el selector
    global $wpdb;
    $services_table = $wpdb->prefix . 'automatiza_services';
    $all_services = $wpdb->get_results(
        "SELECT id, name, category, price_clp, price_usd FROM {$services_table} WHERE status = 'active' ORDER BY category, name ASC"
    );

    $st_map = array(
        'draft'      => ['📝','Borrador',         '#9ca3af'],
        'at_pending' => ['⏳','Pendiente firma AT','#f59e0b'],
        'at_signed'  => ['✅','Firmado por AT',   '#3b82f6'],
        'sent'       => ['📨','Enviado al cliente','#8b5cf6'],
        'viewed'     => ['👁️','Visto por cliente','#06b6d4'],
        'signed'     => ['✔️','FIRMADO COMPLETO','#10b981'],
        'cancelled'  => ['🚫','Cancelado',        '#ef4444'],
        'expired'    => ['⌛','Expirado',         '#6b7280'],
    );
    ?>
    <div class="cfm-section at-contracts-widget"
         data-client-id="<?php echo $client_id; ?>"
         data-nonce="<?php echo esc_attr($nonce); ?>">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="margin:0">📜 Contratos del cliente</h3>
            <button type="button" class="button button-primary" onclick="atOpenNewContract(<?php echo $client_id; ?>)">
                ➕ Generar contrato
            </button>
        </div>

        <?php if (empty($contracts)): ?>
            <div style="background:#f9fafb;border:1px dashed #d1d5db;padding:24px;text-align:center;border-radius:6px">
                <p style="color:#666;margin:0">Aún no hay contratos generados para este cliente.</p>
                <p style="margin:8px 0 0;font-size:12px;color:#999">Pulsa "Generar contrato" para crear el primero (doble firma AT + cliente).</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat striped" style="margin-top:8px">
                <thead><tr>
                    <th>Nº</th><th>Tipo</th><th>Monto/mes</th><th>Estado</th><th>Firmas</th><th>Creado</th><th>Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($contracts as $c):
                    $st = $st_map[$c->status] ?? ['❓', $c->status, '#999'];
                    $detail = admin_url('admin.php?page=at-contracts&id=' . $c->id);
                    $monto = $c->monthly_amount ? '$ ' . number_format((float)$c->monthly_amount,0,',','.') : '—';
                ?>
                    <tr>
                        <td><a href="<?php echo esc_url($detail); ?>"><strong><?php echo esc_html($c->contract_number); ?></strong></a></td>
                        <td><?php echo esc_html(ucfirst($c->type ?: 'soporte')); ?></td>
                        <td><?php echo $monto; ?></td>
                        <td><span style="display:inline-block;color:#fff;background:<?php echo $st[2]; ?>;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600"><?php echo $st[0].' '.esc_html($st[1]); ?></span></td>
                        <td style="font-size:13px">
                            <span title="AutomatizaTech"><?php echo $c->at_signed_at ? '✅' : '⏳'; ?> AT</span>
                            &nbsp;·&nbsp;
                            <span title="Cliente"><?php echo $c->signed_at ? '✔️' : '⏳'; ?> Cliente</span>
                        </td>
                        <td><?php echo esc_html(date('d-m-Y', strtotime($c->created_at))); ?></td>
                        <td>
                            <?php if ($c->signed_pdf_url): ?>
                                <a href="<?php echo esc_url($c->signed_pdf_url); ?>" target="_blank" class="button button-small button-primary">✔️ Final</a>
                            <?php elseif ($c->pdf_url): ?>
                                <a href="<?php echo esc_url($c->pdf_url); ?>" target="_blank" class="button button-small">📄 PDF</a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($detail); ?>" class="button button-small">Ver</a>
                            <?php if (in_array($c->status, ['at_signed','sent','viewed'])): ?>
                                <?php $link = home_url('/contracts/sign-contract.php?token=' . $c->sign_token); ?>
                                <button type="button" class="button button-small" onclick="atCopyLink('<?php echo esc_js($link); ?>', this)">📋 Link cliente</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Modal: Nuevo contrato -->
        <div id="at-new-contract-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center">
            <div style="background:#fff;width:560px;max-width:95vw;max-height:90vh;overflow:auto;border-radius:10px;padding:24px;box-shadow:0 25px 60px rgba(0,0,0,.3)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
                    <h2 style="margin:0">📜 Nuevo contrato de soporte</h2>
                    <button type="button" onclick="atCloseNewContract()" style="background:none;border:0;font-size:24px;cursor:pointer">&times;</button>
                </div>
                <form id="at-new-contract-form" onsubmit="atSubmitContract(event)">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <label>Razón social cliente
                            <input type="text" name="razon_social_cliente" required value="<?php echo esc_attr($client->company_name ?? $client->name ?? ''); ?>" style="width:100%">
                        </label>
                        <label>RUT cliente
                            <input type="text" name="rut_cliente" required placeholder="76.123.456-7" style="width:100%">
                        </label>
                        <label>Representante (nombre)
                            <input type="text" name="representante_cliente_nombre" required value="<?php echo esc_attr($client->name ?? ''); ?>" style="width:100%">
                        </label>
                        <label>RUT representante
                            <input type="text" name="representante_cliente_rut" required style="width:100%">
                        </label>
                        <label style="grid-column:1/-1">Domicilio cliente
                            <input type="text" name="domicilio_cliente" required style="width:100%">
                        </label>
                        <label>Email cliente (firma)
                            <input type="email" name="email_cliente" required value="<?php echo esc_attr($client->email ?? ''); ?>" style="width:100%">
                        </label>
                        <label>Teléfono cliente
                            <input type="text" name="telefono_cliente" value="<?php echo esc_attr($client->phone ?? ''); ?>" style="width:100%">
                        </label>
                        <label style="grid-column:1/-1">Nombre del proyecto
                            <input type="text" name="nombre_proyecto" required style="width:100%">
                        </label>

                        <!-- Servicios contratados -->
                        <div style="grid-column:1/-1">
                            <label style="font-weight:600;color:#1e3a8a;font-size:13px;margin-bottom:6px;display:block">
                                📦 Servicios contratados <small style="font-weight:400;color:#6b7280">(selecciona uno o más)</small>
                            </label>
                            <?php if (!empty($all_services)): ?>
                                <?php
                                // Agrupar por categoría
                                $by_cat = array();
                                foreach ($all_services as $svc) {
                                    $by_cat[$svc->category][] = $svc;
                                }
                                ?>
                                <div class="at-services-grid">
                                <?php foreach ($by_cat as $cat => $svcs): ?>
                                    <div class="at-services-category">
                                        <div class="at-services-cat-label"><?php echo esc_html(ucfirst($cat)); ?></div>
                                        <?php foreach ($svcs as $svc): ?>
                                        <label class="at-service-checkbox-label">
                                            <input type="checkbox" name="servicios_ids[]" value="<?php echo intval($svc->id); ?>"
                                                   data-name="<?php echo esc_attr($svc->name); ?>"
                                                   data-price-clp="<?php echo intval($svc->price_clp); ?>">
                                            <span class="at-svc-name"><?php echo esc_html($svc->name); ?></span>
                                            <?php if ($svc->price_clp > 0): ?>
                                                <span class="at-svc-price">$<?php echo number_format((float)$svc->price_clp, 0, ',', '.'); ?> CLP</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <div id="at-services-summary" style="display:none;margin-top:8px;padding:8px 10px;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;font-size:12px;color:#166534">
                                    ✅ <strong id="at-services-count">0</strong> servicio(s) seleccionado(s)
                                </div>
                            <?php else: ?>
                                <div style="padding:10px;background:#fef9c3;border:1px solid #fde047;border-radius:6px;font-size:12px;color:#854d0e">
                                    ⚠️ No hay servicios activos. <a href="<?php echo admin_url('admin.php?page=automatiza-services'); ?>" target="_blank">Agregar servicios</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label>Plan de soporte
                            <select name="nombre_plan_soporte" style="width:100%">
                                <option>Básico</option><option selected>Estándar</option><option>Premium</option>
                            </select>
                        </label>
                        <label>Horas evolutivas/mes
                            <input type="number" name="horas_evolutivas_mes" min="0" value="4" style="width:100%">
                        </label>
                        <label>Monto mensual (CLP)
                            <input type="number" name="monthly_amount" min="0" step="1000" required style="width:100%">
                        </label>
                        <label>Inicio vigencia
                            <input type="date" name="starts_at" required value="<?php echo date('Y-m-d'); ?>" style="width:100%">
                        </label>
                        <label>Vigencia (meses)
                            <input type="number" name="vigencia_meses" min="1" value="12" style="width:100%">
                        </label>
                        <label>Expira link en (días)
                            <input type="number" name="expires_in_days" min="1" value="14" style="width:100%">
                        </label>
                    </div>

                    <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px;margin-top:14px;border-radius:4px;font-size:13px">
                        ℹ️ Al generar el contrato, recibirás un email para revisarlo y firmarlo internamente. Tras tu firma, podrás enviarlo al cliente.
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">
                        <button type="button" class="button" onclick="atCloseNewContract()">Cancelar</button>
                        <button type="submit" class="button button-primary">📜 Generar contrato</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .at-contracts-widget label{font-size:12px;color:#374151;display:block}
        .at-contracts-widget input,.at-contracts-widget select{margin-top:4px;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px}

        /* Servicios grid */
        .at-services-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
            gap:10px;
            margin-top:6px;
            max-height:200px;
            overflow-y:auto;
            padding:10px;
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:6px;
        }
        .at-services-category {}
        .at-services-cat-label {
            font-size:10px;font-weight:700;text-transform:uppercase;
            color:#6b7280;letter-spacing:.06em;margin-bottom:4px;
        }
        .at-service-checkbox-label {
            display:flex !important;
            align-items:flex-start;
            gap:6px;
            padding:5px 6px;
            border-radius:4px;
            cursor:pointer;
            transition:background .15s;
            margin-bottom:3px;
            font-size:12px !important;
            color:#374151 !important;
        }
        .at-service-checkbox-label:hover { background:#f0f9ff; }
        .at-service-checkbox-label input[type=checkbox] {
            margin-top:2px; flex-shrink:0; width:14px; height:14px;
        }
        .at-svc-name { flex:1; line-height:1.3; }
        .at-svc-price { color:#059669; font-weight:600; white-space:nowrap; font-size:11px; }
        .at-service-checkbox-label:has(input:checked) {
            background:#eff6ff; border:1px solid #93c5fd;
        }
    </style>

    <script>
    function atOpenNewContract(cid){ document.getElementById('at-new-contract-modal').style.display='flex'; }
    function atCloseNewContract(){ document.getElementById('at-new-contract-modal').style.display='none'; }
    function atCopyLink(url,btn){
        navigator.clipboard.writeText(url).then(()=>{
            const old=btn.textContent; btn.textContent='✅ Copiado'; setTimeout(()=>btn.textContent=old,1800);
        });
    }

    // Actualizar resumen de servicios seleccionados
    document.addEventListener('change', function(e){
        if (e.target && e.target.name === 'servicios_ids[]') {
            const checked = document.querySelectorAll('input[name="servicios_ids[]"]:checked');
            const summary = document.getElementById('at-services-summary');
            const count   = document.getElementById('at-services-count');
            if (summary && count) {
                if (checked.length > 0) {
                    count.textContent = checked.length;
                    summary.style.display = 'block';
                } else {
                    summary.style.display = 'none';
                }
            }
        }
    });
    async function atSubmitContract(e){
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        fd.append('action','at_create_contract_widget');
        fd.append('nonce', document.querySelector('.at-contracts-widget').dataset.nonce);
        const btn = form.querySelector('button[type=submit]');
        btn.disabled=true; btn.textContent='Generando...';
        try{
            const r = await fetch(ajaxurl, { method:'POST', body: fd });
            const j = await r.json();
            if (j.success){
                alert('✅ Contrato generado: '+j.data.contract_number+'\n\nRevisa tu correo para firmar internamente.');
                location.reload();
            } else {
                alert('❌ Error: '+(j.data?.message || 'desconocido'));
                btn.disabled=false; btn.textContent='📜 Generar contrato';
            }
        }catch(err){
            alert('❌ Error de red: '+err.message);
            btn.disabled=false; btn.textContent='📜 Generar contrato';
        }
    }
    </script>
    <?php
}

/* ==========================================================
 *  AJAX — crear contrato desde la ficha del cliente
 * ========================================================== */
add_action('wp_ajax_at_create_contract_widget', function(){
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Sin permisos.'));
    }
    $client_id = intval($_POST['client_id'] ?? 0);
    if (!$client_id) wp_send_json_error(array('message' => 'client_id requerido.'));

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'at_contract_widget_' . $client_id)) {
        wp_send_json_error(array('message' => 'Nonce inválido.'));
    }

    $monthly  = floatval($_POST['monthly_amount'] ?? 0);
    $starts   = sanitize_text_field($_POST['starts_at'] ?? date('Y-m-d'));
    $vig_m    = max(1, intval($_POST['vigencia_meses'] ?? 12));
    $ends     = date('Y-m-d', strtotime($starts . ' +' . $vig_m . ' months'));
    $expires  = max(1, intval($_POST['expires_in_days'] ?? 14));

    $ph = array(
        'razon_social_cliente'         => sanitize_text_field($_POST['razon_social_cliente'] ?? ''),
        'rut_cliente'                  => sanitize_text_field($_POST['rut_cliente'] ?? ''),
        'representante_cliente_nombre' => sanitize_text_field($_POST['representante_cliente_nombre'] ?? ''),
        'representante_cliente_rut'    => sanitize_text_field($_POST['representante_cliente_rut'] ?? ''),
        'domicilio_cliente'            => sanitize_text_field($_POST['domicilio_cliente'] ?? ''),
        'email_cliente'                => sanitize_email($_POST['email_cliente'] ?? ''),
        'telefono_cliente'             => sanitize_text_field($_POST['telefono_cliente'] ?? ''),
        'nombre_proyecto'              => sanitize_text_field($_POST['nombre_proyecto'] ?? ''),
        'nombre_plan_soporte'          => sanitize_text_field($_POST['nombre_plan_soporte'] ?? 'Estándar'),
        'horas_evolutivas_mes'         => intval($_POST['horas_evolutivas_mes'] ?? 4),
        'vigencia_meses'               => $vig_m,
        'monto_mensual'                => number_format($monthly, 0, ',', '.'),
        'fecha_aceptacion'             => date('d-m-Y'),
        'fecha_entrega'                => date('d-m-Y'),
        'fecha_pago_final'             => date('d-m-Y'),
    );

    // Resolver servicios seleccionados
    $servicios_ids = array_map('intval', (array)($_POST['servicios_ids'] ?? array()));
    $servicios_ids = array_filter($servicios_ids); // quitar 0s
    if (!empty($servicios_ids)) {
        global $wpdb;
        $st = $wpdb->prefix . 'automatiza_services';
        $placeholders_in = implode(',', array_fill(0, count($servicios_ids), '%d'));
        $services_rows = $wpdb->get_results(
            $wpdb->prepare("SELECT name, price_clp FROM {$st} WHERE id IN ({$placeholders_in})", ...$servicios_ids)
        );
        if (!empty($services_rows)) {
            $lines = array();
            foreach ($services_rows as $sr) {
                $price = $sr->price_clp > 0 ? ' — $' . number_format((float)$sr->price_clp, 0, ',', '.') . ' CLP' : '';
                $lines[] = '• ' . $sr->name . $price;
            }
            $ph['servicios_contratados'] = implode("\n", $lines);
            $ph['servicios_contratados_lista'] = implode(', ', array_column($services_rows, 'name'));
        }
    } else {
        $ph['servicios_contratados'] = '';
        $ph['servicios_contratados_lista'] = '';
    }

    try {
        $contract = ContractService::create_contract(array(
            'client_id'       => $client_id,
            'monthly_amount'  => $monthly,
            'currency'        => 'CLP',
            'starts_at'       => $starts,
            'ends_at'         => $ends,
            'expires_in_days' => $expires,
            'placeholders'    => $ph,
        ));
        wp_send_json_success(array(
            'id'              => $contract->id,
            'contract_number' => $contract->contract_number,
            'at_review_url'   => home_url('/contracts/at-sign-contract.php?token=' . $contract->at_review_token),
        ));
    } catch (\Throwable $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
});
