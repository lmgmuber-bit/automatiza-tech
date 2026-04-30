<?php
/**
 * AutomatizaTech — Admin Page de Contratos
 * Submenú WP: Contactos > Contratos
 * Listado, filtro, detalle, descarga PDFs, eliminación, copiar links.
 */

if (!defined('ABSPATH')) exit;

require_once ABSPATH . 'contracts/contract-service.php';

class AT_Contracts_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 30);
        add_action('admin_post_at_contract_action', array(__CLASS__, 'handle_action'));
    }

    public static function register_menu() {
        // Submenú dentro de Contactos (mismo padre que clientes/leads)
        add_submenu_page(
            'edit.php?post_type=contactos',
            'Contratos AutomatizaTech',
            'Contratos',
            'manage_options',
            'at-contracts',
            array(__CLASS__, 'render_page')
        );
    }

    /* ======================================================
     *  ACCIONES (eliminar, reenviar, regenerar)
     * ====================================================== */
    public static function handle_action() {
        if (!current_user_can('manage_options')) wp_die('Sin permisos.');
        check_admin_referer('at_contract_action');

        $action = sanitize_text_field($_POST['do'] ?? $_GET['do'] ?? '');
        $id     = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        $back   = admin_url('edit.php?post_type=contactos&page=at-contracts' . ($id ? '&id=' . $id : ''));

        if (!$id || !$action) wp_safe_redirect($back); // exit silently

        global $wpdb;
        $table = $wpdb->prefix . 'automatiza_contracts';

        switch ($action) {
            case 'delete':
                $wpdb->delete($table, array('id' => $id));
                wp_safe_redirect(admin_url('edit.php?post_type=contactos&page=at-contracts&msg=deleted'));
                exit;

            case 'resend_at':
                require_once ABSPATH . 'contracts/contract-mailer.php';
                $c = ContractService::get_by_id($id);
                if ($c) ContractMailer::send_internal_review($c);
                wp_safe_redirect($back . '&msg=resent_at');
                exit;

            case 'resend_client':
                $c = ContractService::get_by_id($id);
                if ($c && in_array($c->status, ['at_signed','sent','viewed'])) {
                    ContractService::send_for_client_signature($id);
                }
                wp_safe_redirect($back . '&msg=resent_client');
                exit;

            case 'cancel':
                $wpdb->update($table, array('status' => 'cancelled'), array('id' => $id));
                wp_safe_redirect($back . '&msg=cancelled');
                exit;
        }
        wp_safe_redirect($back);
        exit;
    }

    /* ======================================================
     *  RENDER
     * ====================================================== */
    public static function render_page() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        echo '<div class="wrap">';
        self::notices();
        if ($id > 0) self::render_detail($id);
        else self::render_list();
        echo '</div>';
        self::styles();
    }

    private static function notices() {
        $msg = sanitize_text_field($_GET['msg'] ?? '');
        $map = array(
            'deleted'        => array('success', '🗑️ Contrato eliminado.'),
            'resent_at'      => array('success', '📨 Email interno reenviado al revisor AT.'),
            'resent_client'  => array('success', '📧 Solicitud de firma reenviada al cliente.'),
            'cancelled'      => array('warning', '🚫 Contrato cancelado.'),
        );
        if (isset($map[$msg])) {
            list($cls, $txt) = $map[$msg];
            echo "<div class='notice notice-{$cls} is-dismissible'><p>" . esc_html($txt) . "</p></div>";
        }
    }

    /* -------- LISTA -------- */
    private static function render_list() {
        global $wpdb;
        $table         = $wpdb->prefix . 'automatiza_contracts';
        $clients_table = $wpdb->prefix . 'automatiza_tech_clients';

        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $search        = sanitize_text_field($_GET['s'] ?? '');

        $where = ' WHERE 1=1';
        $params = array();
        if ($status_filter) { $where .= ' AND c.status=%s'; $params[] = $status_filter; }
        if ($search) {
            $where .= ' AND (c.contract_number LIKE %s OR c.signer_email LIKE %s OR c.signer_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like);
        }

        $sql = "SELECT c.*, cli.email AS client_email_lookup, cli.name AS client_name_lookup
                FROM $table c
                LEFT JOIN $clients_table cli ON cli.id = c.client_id
                $where
                ORDER BY c.created_at DESC LIMIT 200";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);

        // Stats
        $stats = $wpdb->get_results("SELECT status, COUNT(*) c FROM $table GROUP BY status", OBJECT_K);

        echo '<h1 style="display:flex;align-items:center;gap:10px;">📜 Contratos AutomatizaTech</h1>';
        echo '<p class="description">Gestión de contratos con doble firma (AT + Cliente)</p>';

        // Stats cards
        echo '<div class="at-stats">';
        $stat_map = array(
            'at_pending'=>['⏳','Pendiente firma AT','#f59e0b'],
            'at_signed' =>['✅','Firmado por AT','#3b82f6'],
            'sent'      =>['📨','Enviado al cliente','#8b5cf6'],
            'viewed'    =>['👁️','Visto por cliente','#06b6d4'],
            'signed'    =>['✔️','FIRMADO COMPLETO','#10b981'],
            'cancelled' =>['🚫','Cancelado','#ef4444'],
            'expired'   =>['⌛','Expirado','#6b7280'],
        );
        foreach ($stat_map as $st => $info) {
            $cnt = isset($stats[$st]) ? intval($stats[$st]->c) : 0;
            echo "<div class='at-stat-card' style='border-left-color:{$info[2]}'>
                    <div class='at-stat-icon'>{$info[0]}</div>
                    <div class='at-stat-content'>
                      <div class='at-stat-num'>{$cnt}</div>
                      <div class='at-stat-label'>{$info[1]}</div>
                    </div>
                  </div>";
        }
        echo '</div>';

        // Filtros
        echo '<form method="get" class="at-filters">
                <input type="hidden" name="post_type" value="contactos">
                <input type="hidden" name="page" value="at-contracts">
                <input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Buscar nº, email, nombre...">
                <select name="status">
                  <option value="">— Todos los estados —</option>';
        foreach ($stat_map as $st => $info) {
            $sel = ($status_filter === $st) ? 'selected' : '';
            echo "<option value='{$st}' {$sel}>{$info[0]} {$info[1]}</option>";
        }
        echo '  </select>
                <button class="button">Filtrar</button>
              </form>';

        if (!$rows) {
            echo '<div class="notice notice-info"><p>No hay contratos aún. Se crean desde la ficha del cliente o vía API.</p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat striped">
                <thead><tr>
                  <th>Nº Contrato</th><th>Cliente</th><th>Email</th>
                  <th>Monto/mes</th><th>Estado</th><th>Creado</th><th>Acciones</th>
                </tr></thead><tbody>';
        foreach ($rows as $r) {
            $st = $r->status;
            $info = $stat_map[$st] ?? ['❓', $st, '#999'];
            $detail = admin_url('edit.php?post_type=contactos&page=at-contracts&id=' . $r->id);
            $cli_name = $r->signer_name ?: $r->client_name_lookup ?: '—';
            $cli_email = $r->signer_email ?: $r->client_email_lookup ?: '—';
            $monto = $r->monthly_amount ? '$ ' . number_format((float)$r->monthly_amount, 0, ',', '.') : '—';
            echo "<tr>
                    <td><a href='{$detail}'><strong>{$r->contract_number}</strong></a></td>
                    <td>" . esc_html($cli_name) . "</td>
                    <td>" . esc_html($cli_email) . "</td>
                    <td>{$monto}</td>
                    <td><span class='at-badge' style='background:{$info[2]}'>{$info[0]} " . esc_html($info[1]) . "</span></td>
                    <td>" . esc_html(date('d-m-Y H:i', strtotime($r->created_at))) . "</td>
                    <td>
                      <a href='{$detail}' class='button button-small'>Ver</a>";
            if ($r->pdf_url) echo " <a href='" . esc_url($r->pdf_url) . "' target='_blank' class='button button-small'>📄 PDF</a>";
            if ($r->signed_pdf_url) echo " <a href='" . esc_url($r->signed_pdf_url) . "' target='_blank' class='button button-small button-primary'>✔️ Final</a>";
            echo "  </td>
                  </tr>";
        }
        echo '</tbody></table>';
    }

    /* -------- DETALLE -------- */
    private static function render_detail($id) {
        $c = ContractService::get_by_id($id);
        if (!$c) { echo '<div class="notice notice-error"><p>Contrato no encontrado.</p></div>'; return; }

        $back = admin_url('edit.php?post_type=contactos&page=at-contracts');
        $at_url     = home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token);
        $client_url = home_url('/contracts/sign-contract.php?token=' . $c->sign_token);

        echo "<a href='{$back}' class='button'>← Volver al listado</a>";
        echo "<h1>📜 {$c->contract_number}</h1>";

        // Status pill
        $st_map = array(
            'at_pending' => ['⏳ Pendiente firma AT', '#f59e0b'],
            'at_signed'  => ['✅ Firmado por AT — listo para enviar', '#3b82f6'],
            'sent'       => ['📨 Enviado al cliente', '#8b5cf6'],
            'viewed'     => ['👁️ Visto por el cliente', '#06b6d4'],
            'signed'     => ['✔️ FIRMADO POR AMBAS PARTES', '#10b981'],
            'cancelled'  => ['🚫 Cancelado', '#ef4444'],
            'expired'    => ['⌛ Expirado', '#6b7280'],
        );
        $info = $st_map[$c->status] ?? ['—', '#999'];
        echo "<p><span class='at-badge' style='background:{$info[1]};font-size:14px;padding:8px 14px'>{$info[0]}</span></p>";

        echo '<div class="at-detail-grid">';

        // Columna 1: Datos
        echo '<div class="at-card"><h3>Datos del contrato</h3>';
        $rows = array(
            'Tipo' => esc_html($c->type ?? 'soporte'),
            'Cliente ID' => intval($c->client_id),
            'Proyecto ID' => intval($c->project_id),
            'Propuesta ID' => intval($c->proposal_id),
            'Monto mensual' => $c->monthly_amount ? '$ ' . number_format((float)$c->monthly_amount, 0, ',', '.') . ' ' . $c->currency : '—',
            'Vigencia' => esc_html($c->starts_at) . ' → ' . esc_html($c->ends_at ?: 'indefinida'),
            'Expira el' => esc_html($c->expires_at),
            'Creado' => esc_html($c->created_at),
        );
        echo '<table class="form-table at-kv">';
        foreach ($rows as $k => $v) echo "<tr><th>{$k}</th><td>{$v}</td></tr>";
        echo '</table></div>';

        // Columna 2: Firmas
        echo '<div class="at-card"><h3>Firmas</h3>';
        echo '<h4>🏢 AutomatizaTech</h4>';
        if ($c->at_signed_at) {
            echo '<p><strong>' . esc_html($c->at_signer_name) . '</strong> — RUT ' . esc_html($c->at_signer_rut) . '<br>';
            echo '<small>' . esc_html($c->at_signed_at) . ' · IP ' . esc_html($c->at_signer_ip) . '</small></p>';
            if ($c->at_signature_image_url) {
                echo "<img src='" . esc_url($c->at_signature_image_url) . "' style='max-height:80px;border:1px solid #ddd;padding:4px;background:#fff'>";
            }
        } else {
            echo '<p><em>Pendiente</em></p>';
        }

        echo '<h4 style="margin-top:18px">👤 Cliente</h4>';
        if ($c->signed_at) {
            echo '<p><strong>' . esc_html($c->signer_name) . '</strong> — RUT ' . esc_html($c->signer_rut) . '<br>';
            echo esc_html($c->signer_email) . '<br>';
            echo '<small>' . esc_html($c->signed_at) . ' · IP ' . esc_html($c->signer_ip) . '</small></p>';
            if ($c->signature_image_url) {
                echo "<img src='" . esc_url($c->signature_image_url) . "' style='max-height:80px;border:1px solid #ddd;padding:4px;background:#fff'>";
            }
        } else {
            echo '<p><em>Pendiente</em></p>';
        }
        echo '</div>';

        // Columna 3: Acciones / Links
        echo '<div class="at-card"><h3>Acciones</h3>';

        if ($c->pdf_url) {
            echo "<p><a href='" . esc_url($c->pdf_url) . "' target='_blank' class='button'>📄 Ver PDF preliminar</a></p>";
        }
        if ($c->signed_pdf_url) {
            echo "<p><a href='" . esc_url($c->signed_pdf_url) . "' target='_blank' class='button button-primary'>✔️ Descargar PDF FINAL firmado</a></p>";
        }

        echo '<h4 style="margin-top:16px">🔗 Links de firma</h4>';
        if (in_array($c->status, ['at_pending'])) {
            echo '<p><label>Link revisión interna AT:</label>
                  <input type="text" readonly value="' . esc_attr($at_url) . '" onclick="this.select()" style="width:100%"></p>';
        }
        if (in_array($c->status, ['at_signed','sent','viewed'])) {
            echo '<p><label>Link firma cliente:</label>
                  <input type="text" readonly value="' . esc_attr($client_url) . '" onclick="this.select()" style="width:100%"></p>';
        }

        // Botones de acción
        echo '<h4 style="margin-top:16px">Operaciones</h4>';
        $nonce = wp_create_nonce('at_contract_action');
        $base = admin_url('admin-post.php?action=at_contract_action&_wpnonce=' . $nonce . '&id=' . $id);

        if ($c->status === 'at_pending') {
            echo "<a href='{$base}&do=resend_at' class='button'>📨 Reenviar email a revisor AT</a> ";
        }
        if (in_array($c->status, ['at_signed','sent','viewed'])) {
            echo "<a href='{$base}&do=resend_client' class='button button-primary'>📧 Reenviar firma al cliente</a> ";
        }
        if (!in_array($c->status, ['signed','cancelled'])) {
            echo "<a href='{$base}&do=cancel' class='button' onclick='return confirm(\"¿Cancelar contrato?\")'>🚫 Cancelar</a> ";
        }
        echo "<a href='{$base}&do=delete' class='button' style='color:#b91c1c' onclick='return confirm(\"¿Eliminar definitivamente?\")'>🗑️ Eliminar</a>";

        echo '</div>'; // card

        echo '</div>'; // grid

        // Hash de integridad
        if ($c->document_hash || $c->signed_document_hash) {
            echo '<div class="at-card"><h3>🔐 Hash de integridad SHA-256</h3>';
            if ($c->document_hash)        echo '<p><strong>Documento preliminar:</strong><br><code>' . esc_html($c->document_hash) . '</code></p>';
            if ($c->signed_document_hash) echo '<p><strong>Documento FINAL firmado:</strong><br><code>' . esc_html($c->signed_document_hash) . '</code></p>';
            echo '</div>';
        }
    }

    /* -------- ESTILOS -------- */
    private static function styles() {
        echo '<style>
            .at-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:18px 0}
            .at-stat-card{background:#fff;border-left:4px solid #999;padding:14px;display:flex;gap:12px;align-items:center;box-shadow:0 1px 2px rgba(0,0,0,.04)}
            .at-stat-icon{font-size:24px}
            .at-stat-num{font-size:22px;font-weight:700;line-height:1}
            .at-stat-label{font-size:12px;color:#666;margin-top:4px}
            .at-filters{margin:18px 0;display:flex;gap:8px;align-items:center}
            .at-filters input[type=search]{min-width:280px}
            .at-badge{display:inline-block;color:#fff;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
            .at-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:18px}
            .at-card{background:#fff;border:1px solid #e5e7eb;padding:18px;border-radius:6px}
            .at-card h3{margin-top:0;border-bottom:1px solid #f0f0f0;padding-bottom:8px}
            .at-card h4{margin:8px 0 6px;color:#004AAD}
            .at-kv th{width:38%;padding:6px 0;color:#555}
            .at-kv td{padding:6px 0}
        </style>';
    }
}

AT_Contracts_Admin::init();
