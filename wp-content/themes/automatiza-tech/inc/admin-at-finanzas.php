<?php
/**
 * Finanzas AT — página de administración general (wp-admin)
 *
 * Registro de gastos de infraestructura de AutomatizaTech (todos los
 * proyectos): hosting, VPS/n8n, APIs/IA, mensajería (Meta/YCloud),
 * dominios, herramientas. Periodicidad mensual/anual/único, CLP/USD,
 * resumen normalizado y costo IA 30d (tabla de consumo del portal).
 *
 * Reusa OmniATFinanceController (raíz del sitio) y sus tablas
 * wp_omnichannel_at_expenses / wp_omnichannel_ai_usage — misma data
 * que consume el endpoint del portal, una sola fuente de verdad.
 */

if (!defined('ABSPATH')) exit;

function at_finanzas_admin_menu() {
    add_menu_page(
        'Finanzas AT',
        'Finanzas AT',
        'manage_options',
        'at-finanzas',
        'at_finanzas_admin_page',
        'dashicons-money-alt',
        58
    );
}
add_action('admin_menu', 'at_finanzas_admin_menu');

function at_finanzas_get_controller() {
    require_once ABSPATH . 'omnichannel-atfinance-controller.php';
    $fin = new OmniATFinanceController();
    $fin->maybe_create_tables();
    return $fin;
}

function at_finanzas_admin_page() {
    if (!current_user_can('manage_options')) wp_die('Sin permisos');
    $fin = at_finanzas_get_controller();

    $cat_labels = [
        'hosting' => 'Hosting', 'infraestructura' => 'Infraestructura', 'dominios' => 'Dominios',
        'apis_ia' => 'APIs / IA', 'mensajeria' => 'Mensajería (Meta/YCloud)', 'marketing' => 'Marketing',
        'herramientas' => 'Herramientas', 'otros' => 'Otros',
    ];
    $period_labels = ['monthly' => 'Mensual', 'annual' => 'Anual', 'one_time' => 'Único'];

    // ---- acciones ----
    if (!empty($_POST['at_fin_action']) && check_admin_referer('at_finanzas_save')) {
        // Categoría nueva: opción "__custom__" del select + texto libre
        $posted_cat = $_POST['category'] ?? 'otros';
        if ($posted_cat === '__custom__') {
            $posted_cat = trim($_POST['category_new'] ?? '') !== '' ? $_POST['category_new'] : 'otros';
        }
        $data = [
            'category'    => $posted_cat,
            'provider'    => $_POST['provider'] ?? '',
            'description' => $_POST['description'] ?? '',
            'amount'      => $_POST['amount'] ?? 0,
            'currency'    => $_POST['currency'] ?? 'CLP',
            'period'      => $_POST['period'] ?? 'monthly',
            'start_date'  => $_POST['start_date'] ?? '',
            'active'      => !empty($_POST['active']),
            'notes'       => $_POST['notes'] ?? '',
        ];
        $edit_id = absint($_POST['expense_id'] ?? 0);
        $result = $edit_id ? $fin->update_expense($edit_id, $data) : $fin->create_expense($data);
        if (!empty($result['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Gasto ' . ($edit_id ? 'actualizado' : 'creado') . ' correctamente.</p></div>';
        }
    }
    if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'at_fin_delete_' . absint($_GET['id']))) {
        $fin->delete_expense(absint($_GET['id']));
        echo '<div class="notice notice-success"><p>Gasto eliminado.</p></div>';
    }
    if (in_array($_GET['action'] ?? '', ['renew_paid', 'renew_skip'], true) && !empty($_GET['id'])
        && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'at_fin_renew_' . absint($_GET['id']))) {
        $res = $fin->renew_expense(absint($_GET['id']), ($_GET['action'] === 'renew_paid'));
        if (!empty($res['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($res['error']) . '</p></div>';
        } elseif (!empty($res['renewed'])) {
            echo '<div class="notice notice-success"><p>Renovación registrada: nuevo período desde el ' . esc_html($res['new_start']) . '. El período anterior quedó como historial (inactivo).</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Gasto marcado como no renovado (inactivo). Ya no cuenta en el resumen.</p></div>';
        }
    }

    $expenses = $fin->get_expenses();
    $due_renewals = $fin->get_due_renewals();
    $summary  = $fin->finance_summary();

    // gasto en edición (formulario precargado)
    $editing = null;
    if (($_GET['action'] ?? '') === 'edit' && !empty($_GET['id'])) {
        foreach ($expenses as $e) {
            if ((int) $e->id === absint($_GET['id'])) { $editing = $e; break; }
        }
    }

    $fmt = function ($n, $cur) {
        return $cur === 'USD'
            ? 'US$ ' . number_format((float) $n, 2, '.', ',')
            : '$ ' . number_format((float) $n, 0, ',', '.');
    };
    $base_url = admin_url('admin.php?page=at-finanzas');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">💰 Finanzas AT</h1>
        <p style="color:#646970;margin-top:2px">Gastos de infraestructura de todos los proyectos AutomatizaTech · resumen mensual/anual normalizado</p>

        <?php if (!empty($due_renewals)): ?>
        <div style="background:#fcf9e8;border:1px solid #dba617;border-left-width:4px;border-radius:6px;padding:14px 16px;margin:16px 0">
            <div style="font-weight:700;color:#674e00;margin-bottom:8px">🔔 Renovaciones pendientes — ¿pagaste este período?</div>
            <?php foreach ($due_renewals as $d): ?>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #f0e6c8">
                <div style="flex:1;min-width:240px">
                    <strong><?php echo esc_html($d->provider); ?></strong>
                    — <?php echo esc_html($fmt($d->amount, $d->currency)); ?>/<?php echo $d->period === 'annual' ? 'año' : 'mes'; ?>
                    <span style="color:#8a6d1a">· venció el <?php echo esc_html($d->next_due); ?></span>
                </div>
                <a class="button button-primary button-small"
                   href="<?php echo esc_url(wp_nonce_url($base_url . '&action=renew_paid&id=' . $d->id, 'at_fin_renew_' . $d->id)); ?>">✅ Sí, lo pagué</a>
                <a class="button button-small"
                   href="<?php echo esc_url(wp_nonce_url($base_url . '&action=renew_skip&id=' . $d->id, 'at_fin_renew_' . $d->id)); ?>"
                   onclick="return confirm('¿Marcar <?php echo esc_js($d->provider); ?> como NO renovado? Quedará inactivo y dejará de contar en el resumen.')">❌ No / cancelado</a>
            </div>
            <?php endforeach; ?>
            <p style="margin:8px 0 0;color:#8a6d1a;font-size:12px">"Sí, lo pagué" cierra el período anterior (queda como historial) y crea el registro del nuevo período automáticamente. Si aún no decides, puedes dejarlo aquí — te lo seguirá recordando.</p>
        </div>
        <?php endif; ?>

        <!-- Resumen -->
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">
            <?php
            $cards = [
                ['Gasto mensual', $fmt($summary['monthly']['CLP'], 'CLP') . ($summary['monthly']['USD'] > 0 ? ' + ' . $fmt($summary['monthly']['USD'], 'USD') : '')],
                ['Proyección anual', $fmt($summary['annual']['CLP'], 'CLP') . ($summary['annual']['USD'] > 0 ? ' + ' . $fmt($summary['annual']['USD'], 'USD') : '')],
                ['Costo IA 30 días (portal)', $fmt($summary['ai_cost_usd_30d'], 'USD')],
                ['Gastos activos', (string) $summary['active_count']],
            ];
            foreach ($cards as $c): ?>
            <div style="flex:1;min-width:200px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#8c8f94;font-weight:600"><?php echo esc_html($c[0]); ?></div>
                <div style="font-size:20px;font-weight:700;color:#1d2327;margin-top:4px"><?php echo esc_html($c[1]); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($summary['by_category'])): ?>
        <p>
            <?php foreach ($summary['by_category'] as $cat => $vals): ?>
                <span style="display:inline-block;background:#f0f0f1;border-radius:999px;padding:4px 12px;margin:0 6px 6px 0;font-size:12px">
                    <strong><?php echo esc_html($cat_labels[$cat] ?? $cat); ?></strong>:
                    <?php echo $vals['CLP'] > 0 ? esc_html($fmt($vals['CLP'], 'CLP')) : ''; ?><?php echo ($vals['CLP'] > 0 && $vals['USD'] > 0) ? ' · ' : ''; ?><?php echo $vals['USD'] > 0 ? esc_html($fmt($vals['USD'], 'USD')) : ''; ?>/mes
                </span>
            <?php endforeach; ?>
        </p>
        <?php endif; ?>

        <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start">
            <!-- Tabla -->
            <div style="flex:2;min-width:480px">
                <table class="widefat striped">
                    <thead><tr>
                        <th>Categoría</th><th>Proveedor</th><th>Descripción</th>
                        <th style="text-align:right">Monto</th><th>Periodicidad</th><th>Vence</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#8c8f94;padding:24px">Sin gastos registrados. Usa el formulario para agregar el primero (Hostinger, VPS n8n, OpenAI, YCloud, dominios…).</td></tr>
                    <?php else: foreach ($expenses as $e): ?>
                        <tr style="<?php echo (int) $e->active !== 1 ? 'opacity:.55' : ''; ?>">
                            <td><?php echo esc_html($cat_labels[$e->category] ?? $e->category); ?></td>
                            <td><strong><?php echo esc_html($e->provider); ?></strong></td>
                            <td><?php echo esc_html($e->description); ?></td>
                            <td style="text-align:right;white-space:nowrap"><?php echo esc_html($fmt($e->amount, $e->currency)); ?></td>
                            <td><?php echo esc_html($period_labels[$e->period] ?? $e->period); ?></td>
                            <td style="white-space:nowrap"><?php
                                $next = $fin->next_due($e);
                                if (!$next) {
                                    echo '<span style="color:#8c8f94">—</span>';
                                } elseif ($next <= current_time('Y-m-d')) {
                                    echo '<span style="color:#b32d2e;font-weight:600">' . esc_html($next) . ' ⚠️</span>';
                                } else {
                                    echo esc_html($next);
                                }
                            ?></td>
                            <td><?php echo (int) $e->active === 1 ? '<span style="color:#00a32a;font-weight:600">Activo</span>' : '<span style="color:#8c8f94">Inactivo</span>'; ?></td>
                            <td style="white-space:nowrap">
                                <a class="button button-small" href="<?php echo esc_url($base_url . '&action=edit&id=' . $e->id); ?>">Editar</a>
                                <a class="button button-small" style="color:#b32d2e"
                                   href="<?php echo esc_url(wp_nonce_url($base_url . '&action=delete&id=' . $e->id, 'at_fin_delete_' . $e->id)); ?>"
                                   onclick="return confirm('¿Eliminar el gasto de <?php echo esc_js($e->provider); ?>?')">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Formulario -->
            <div style="flex:1;min-width:300px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px">
                <h2 style="margin-top:0"><?php echo $editing ? 'Editar gasto' : 'Nuevo gasto'; ?></h2>
                <?php if ($editing): ?><p><a href="<?php echo esc_url($base_url); ?>">← volver a "nuevo gasto"</a></p><?php endif; ?>
                <form method="post">
                    <?php wp_nonce_field('at_finanzas_save'); ?>
                    <input type="hidden" name="at_fin_action" value="save">
                    <input type="hidden" name="expense_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                    <table class="form-table" style="margin-top:0">
                        <tr><th scope="row"><label for="at-fin-cat">Categoría</label></th><td>
                            <?php
                            // Opciones = categorías conocidas + las personalizadas ya usadas en la BD
                            $cat_options = $cat_labels;
                            foreach ($expenses as $exp_cat) {
                                if (!isset($cat_options[$exp_cat->category])) {
                                    $cat_options[$exp_cat->category] = $exp_cat->category;
                                }
                            }
                            ?>
                            <select id="at-fin-cat" name="category">
                                <?php foreach ($cat_options as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($editing->category ?? 'hosting', $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__">➕ Nueva categoría…</option>
                            </select>
                            <div id="at-fin-cat-new-wrap" style="display:none;margin-top:6px">
                                <input name="category_new" type="text" class="regular-text" placeholder="Nombre de la nueva categoría (ej: Publicidad)">
                            </div>
                            <script>
                            document.getElementById('at-fin-cat').addEventListener('change', function () {
                                document.getElementById('at-fin-cat-new-wrap').style.display = this.value === '__custom__' ? '' : 'none';
                            });
                            </script>
                        </td></tr>
                        <tr><th scope="row"><label for="at-fin-prov">Proveedor *</label></th><td>
                            <input id="at-fin-prov" name="provider" type="text" class="regular-text" required
                                   placeholder="Hostinger, YCloud, OpenAI…" value="<?php echo esc_attr($editing->provider ?? ''); ?>">
                        </td></tr>
                        <tr><th scope="row"><label for="at-fin-desc">Descripción</label></th><td>
                            <input id="at-fin-desc" name="description" type="text" class="regular-text"
                                   value="<?php echo esc_attr($editing->description ?? ''); ?>">
                        </td></tr>
                        <tr><th scope="row"><label for="at-fin-amount">Monto *</label></th><td>
                            <input id="at-fin-amount" name="amount" type="number" step="0.01" min="0" required
                                   style="width:120px" value="<?php echo esc_attr($editing->amount ?? ''); ?>">
                            <select name="currency">
                                <option <?php selected($editing->currency ?? 'CLP', 'CLP'); ?>>CLP</option>
                                <option <?php selected($editing->currency ?? '', 'USD'); ?>>USD</option>
                            </select>
                        </td></tr>
                        <tr><th scope="row"><label for="at-fin-period">Periodicidad</label></th><td>
                            <select id="at-fin-period" name="period">
                                <?php foreach ($period_labels as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($editing->period ?? 'monthly', $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th scope="row"><label for="at-fin-date">Fecha inicio</label></th><td>
                            <input id="at-fin-date" name="start_date" type="date" value="<?php echo esc_attr($editing->start_date ?? ''); ?>">
                        </td></tr>
                        <tr><th scope="row">Estado</th><td>
                            <label><input type="checkbox" name="active" <?php checked((int) ($editing->active ?? 1), 1); ?>> Gasto activo (cuenta en el resumen)</label>
                        </td></tr>
                        <tr><th scope="row"><label for="at-fin-notes">Notas</label></th><td>
                            <textarea id="at-fin-notes" name="notes" rows="2" class="large-text"><?php echo esc_textarea($editing->notes ?? ''); ?></textarea>
                        </td></tr>
                    </table>
                    <?php submit_button($editing ? 'Guardar cambios' : 'Agregar gasto'); ?>
                </form>
            </div>
        </div>

        <p style="color:#8c8f94;font-size:12px;margin-top:18px">
            El "Costo IA 30 días" viene de la tabla de consumo de tokens que alimentan el Asistente IA del portal
            y los bots vía n8n (<code>usage-ingest</code>). Las estadísticas por cliente/canal/bot viven en el
            portal OmniCliente → Consumo IA.
        </p>
    </div>
    <?php
}
