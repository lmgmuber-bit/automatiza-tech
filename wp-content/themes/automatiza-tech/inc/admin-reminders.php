<?php
/**
 * Panel de Administración para Recordatorios Manuales
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agregar menú de administración
 */
function automatiza_tech_reminders_menu() {
    add_menu_page(
        'Gestión de Recordatorios',
        'Agendamientos',
        'manage_options',
        'automatiza-reminders',
        'automatiza_tech_reminders_page',
        'dashicons-calendar-alt',
        25
    );
    
    // Añadir submenú para Recordatorios (para que aparezca con el nombre correcto en el submenú)
    add_submenu_page(
        'automatiza-reminders',
        'Gestión de Recordatorios',
        'Citas Activas',
        'manage_options',
        'automatiza-reminders',
        'automatiza_tech_reminders_page'
    );
    
    // Nota: El submenú "Todas las Citas" está en admin-leads-manager.php
}
add_action('admin_menu', 'automatiza_tech_reminders_menu');

/**
 * Renderizar página de administración
 */
function automatiza_tech_reminders_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // --- FILTROS Y ORDENAMIENTO ---
    $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
    $filter_channel = isset($_GET['filter_channel']) ? sanitize_text_field($_GET['filter_channel']) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'scheduled_date';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
    
    // Whitelist para ordenamiento
    $allowed_sort_cols = ['id', 'name', 'scheduled_date', 'scheduled_time', 'source'];
    if (!in_array($orderby, $allowed_sort_cols)) $orderby = 'scheduled_date';
    if (!in_array(strtoupper($order), ['ASC', 'DESC'])) $order = 'ASC';

    // Obtener fecha y hora actual según zona horaria de WP
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
    $now_dt = new DateTime('now', $tz);
    $current_date_db = $now_dt->format('Y-m-d');
    $current_time_db = $now_dt->format('H:i:s');

    // Construir Query
    $where = "1=1";
    
    // Excluir citas canceladas de la vista principal
    $where .= " AND (status IS NULL OR status != 'cancelled')";
    
    if ($filter_date) {
        $where .= $wpdb->prepare(" AND scheduled_date = %s", $filter_date);
    } else {
        // Por defecto mostrar solo futuros (incluyendo los de hoy que aún no pasan)
        $where .= $wpdb->prepare(" AND (scheduled_date > %s OR (scheduled_date = %s AND scheduled_time > %s))", $current_date_db, $current_date_db, $current_time_db);
    }
    
    // Filtro por canal
    if ($filter_channel) {
        $where .= $wpdb->prepare(" AND source = %s", $filter_channel);
    }

    $query = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order, scheduled_time ASC";
    $leads = $wpdb->get_results($query);
    
    // Obtener lista de canales únicos para el filtro
    $channels = $wpdb->get_col("SELECT DISTINCT COALESCE(source, 'web') as source FROM $table_name ORDER BY source");
    
    // Helper para URLs de ordenamiento
    $get_sort_url = function($col) use ($orderby, $order, $filter_date, $filter_channel) {
        $new_order = ($orderby === $col && $order === 'ASC') ? 'DESC' : 'ASC';
        $url = add_query_arg(array('orderby' => $col, 'order' => $new_order));
        if ($filter_date) $url = add_query_arg('filter_date', $filter_date, $url);
        if ($filter_channel) $url = add_query_arg('filter_channel', $filter_channel, $url);
        return esc_url($url);
    };

    // Icono de ordenamiento
    $sort_icon = function($col) use ($orderby, $order) {
        if ($orderby !== $col) return '';
        return ($order === 'ASC') ? ' &#9650;' : ' &#9660;';
    };
    
    // Labels para canales
    $channel_labels = array(
        'web' => '🌐 Web',
        'whatsapp' => '📱 WhatsApp',
        'instagram' => '📸 Instagram',
        'messenger' => '💬 Messenger',
        'phone' => '📞 Teléfono',
        'email' => '📧 Email',
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">📅 Gestión de Citas y Recordatorios</h1>
        <p>Panel unificado de todas las citas agendadas (Web, WhatsApp, y otros canales). Los recordatorios manuales se envían por <strong>correo electrónico</strong>.</p>
        
        <!-- BARRA DE HERRAMIENTAS -->
        <div class="tablenav top" style="height: auto; padding-bottom: 10px;">
            <div class="alignleft actions">
                <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="automatiza-reminders">
                    
                    <label style="display:flex; align-items:center; gap:5px;">
                        📅 <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>" style="height: 30px;">
                    </label>
                    
                    <label style="display:flex; align-items:center; gap:5px;">
                        Canal:
                        <select name="filter_channel" style="height: 30px;">
                            <option value="">Todos</option>
                            <?php foreach ($channels as $ch): 
                                $label = isset($channel_labels[$ch]) ? $channel_labels[$ch] : ucfirst($ch);
                            ?>
                                <option value="<?php echo esc_attr($ch); ?>" <?php selected($filter_channel, $ch); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    
                    <input type="submit" class="button" value="Filtrar">
                    <?php if ($filter_date || $filter_channel): ?>
                        <a href="<?php echo admin_url('admin.php?page=automatiza-reminders'); ?>" class="button">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="alignright actions">
                <strong>📧 Envío Masivo por Email: </strong>
                <button class="button button-primary bulk-send-btn" data-type="72h">72h</button>
                <button class="button button-primary bulk-send-btn" data-type="24h">24h</button>
                <button class="button button-primary bulk-send-btn" data-type="1h">1h</button>
            </div>
            <br class="clear">
        </div>

        <?php
        // Función helper para mostrar icono de canal
        $get_channel_icon = function($source) {
            $channels = array(
                'web' => array('icon' => '🌐', 'label' => 'Web', 'color' => '#2271b1'),
                'whatsapp' => array('icon' => '📱', 'label' => 'WhatsApp', 'color' => '#25D366'),
                'instagram' => array('icon' => '📸', 'label' => 'Instagram', 'color' => '#E4405F'),
                'messenger' => array('icon' => '💬', 'label' => 'Messenger', 'color' => '#0084FF'),
                'phone' => array('icon' => '📞', 'label' => 'Teléfono', 'color' => '#FF9800'),
                'email' => array('icon' => '📧', 'label' => 'Email', 'color' => '#EA4335'),
            );
            
            $source = strtolower($source ?? 'web');
            $channel = isset($channels[$source]) ? $channels[$source] : array('icon' => '❓', 'label' => ucfirst($source), 'color' => '#666');
            
            return sprintf(
                '<span style="display:inline-flex;align-items:center;gap:4px;" title="%s"><span style="font-size:16px;">%s</span><span style="color:%s;font-size:11px;">%s</span></span>',
                esc_attr($channel['label']),
                $channel['icon'],
                $channel['color'],
                esc_html($channel['label'])
            );
        };
        ?>

        <style>
            .reminders-table-wrapper {
                overflow-x: auto;
                margin-top: 10px;
            }
            .reminders-table {
                border-collapse: collapse;
                width: 100%;
                min-width: 1100px;
                font-size: 13px;
            }
            .reminders-table th,
            .reminders-table td {
                padding: 10px 8px;
                text-align: left;
                border-bottom: 1px solid #e1e1e1;
                white-space: nowrap;
            }
            .reminders-table th {
                background: #f6f7f7;
                font-weight: 600;
                position: sticky;
                top: 0;
            }
            .reminders-table th a {
                text-decoration: none;
                color: #1d2327;
            }
            .reminders-table th a:hover {
                color: #2271b1;
            }
            .reminders-table tbody tr:hover {
                background-color: #f0f6fc;
            }
            .reminders-table .col-id { width: 50px; }
            .reminders-table .col-name { min-width: 120px; }
            .reminders-table .col-email { min-width: 180px; }
            .reminders-table .col-canal { width: 90px; text-align: center; }
            .reminders-table .col-fecha { width: 95px; }
            .reminders-table .col-hora { width: 60px; }
            .reminders-table .col-estado { width: 100px; }
            .reminders-table .col-reminder-email { width: 80px; text-align: center; }
            .reminders-table .col-reminder-wa { width: 55px; text-align: center; }
            
            .reminders-table .th-group {
                text-align: center;
                background: #e7e8e9;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .reminders-table .th-group-email { background: #dbeafe; color: #1e40af; }
            .reminders-table .th-group-wa { background: #dcfce7; color: #166534; }
            
            .reminder-btn {
                font-size: 11px !important;
                padding: 2px 8px !important;
                min-height: 26px !important;
                line-height: 1.4 !important;
            }
            .reminder-sent {
                color: #166534;
                font-weight: 500;
                font-size: 12px;
            }
            .reminder-pending {
                color: #9ca3af;
            }
            .wa-check {
                color: #25D366;
                font-size: 16px;
            }
            .status-confirmed { color: #166534; font-weight: 600; }
            .status-rejected { color: #dc2626; font-weight: 600; }
            .status-pending { color: #6b7280; }
            
            @media screen and (max-width: 1400px) {
                .reminders-table .col-email {
                    max-width: 150px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
            }
            
            /* ========== RESPONSIVE STYLES ========== */
            
            /* Tablet (768px - 1024px) */
            @media screen and (max-width: 1024px) {
                .reminders-table {
                    min-width: 900px;
                }
                .tablenav.top .alignleft.actions form {
                    flex-wrap: wrap;
                }
                .tablenav.top .alignright.actions {
                    margin-top: 10px;
                    width: 100%;
                    text-align: left;
                }
            }
            
            /* Mobile (hasta 767px) */
            @media screen and (max-width: 767px) {
                .wrap { padding: 10px !important; margin-left: 0 !important; }
                .wrap h1.wp-heading-inline { font-size: 18px; }
                .wrap > p { font-size: 13px; }
                
                .tablenav.top {
                    padding: 10px !important;
                    background: #f6f7f7;
                    border-radius: 8px;
                    margin-bottom: 15px;
                }
                .tablenav.top .alignleft.actions {
                    width: 100%;
                }
                .tablenav.top .alignleft.actions form {
                    flex-direction: column;
                    gap: 10px;
                }
                .tablenav.top .alignleft.actions form label {
                    width: 100%;
                    display: flex;
                    gap: 8px;
                    align-items: center;
                }
                .tablenav.top .alignleft.actions form label input[type="date"],
                .tablenav.top .alignleft.actions form label select {
                    flex: 1;
                    height: 40px;
                    font-size: 16px;
                    padding: 8px 12px;
                }
                .tablenav.top .alignleft.actions form input[type="submit"],
                .tablenav.top .alignleft.actions form .button {
                    width: 48%;
                    height: 40px;
                    font-size: 14px;
                }
                
                .tablenav.top .alignright.actions {
                    width: 100%;
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    align-items: center;
                }
                .tablenav.top .alignright.actions strong {
                    width: 100%;
                    margin-bottom: 5px;
                    font-size: 13px;
                }
                .tablenav.top .alignright.actions .bulk-send-btn {
                    flex: 1;
                    min-width: 60px;
                    height: 40px;
                }
                
                .reminders-table-wrapper {
                    margin: 0 -10px;
                    padding: 0 10px;
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                .reminders-table {
                    min-width: 800px;
                    font-size: 12px;
                }
                .reminders-table th,
                .reminders-table td {
                    padding: 8px 5px;
                }
                .reminders-table .col-id { width: 40px; }
                .reminders-table .col-name { min-width: 100px; }
                .reminders-table .col-email { max-width: 120px; }
                .reminders-table .col-canal { width: 70px; }
                .reminders-table .col-fecha { width: 80px; }
                .reminders-table .col-hora { width: 50px; }
                .reminders-table .col-estado { width: 85px; }
                .reminders-table .col-reminder-email,
                .reminders-table .col-reminder-wa { width: 45px; }
                
                .reminder-btn {
                    font-size: 10px !important;
                    padding: 4px 6px !important;
                    min-height: 28px !important;
                }
                .reminder-sent { font-size: 11px; }
                .wa-check { font-size: 14px; }
                
                #bulk-progress-modal > div {
                    width: 90% !important;
                    max-width: 300px;
                }
            }
            
            /* Mobile Small (hasta 480px) */
            @media screen and (max-width: 480px) {
                .wrap { padding: 5px !important; }
                .wrap h1.wp-heading-inline { font-size: 16px; }
                
                .tablenav.top .alignright.actions .bulk-send-btn {
                    font-size: 12px;
                    padding: 8px 12px;
                }
                
                .reminders-table {
                    font-size: 11px;
                }
                .reminders-table th,
                .reminders-table td {
                    padding: 6px 4px;
                }
            }
            
            /* Touch improvements */
            @media (hover: none) and (pointer: coarse) {
                .tablenav.top .alignleft.actions form input[type="date"],
                .tablenav.top .alignleft.actions form select,
                .tablenav.top .alignleft.actions form .button,
                .bulk-send-btn,
                .reminder-btn {
                    min-height: 44px;
                }
            }
        </style>

        <div class="reminders-table-wrapper">
            <table class="reminders-table">
                <thead>
                    <tr>
                        <th colspan="7" style="background:#fff; border-bottom: 2px solid #e1e1e1;"></th>
                        <th colspan="3" class="th-group th-group-email">📧 Email</th>
                        <th colspan="3" class="th-group th-group-wa">📱 WhatsApp</th>
                    </tr>
                    <tr>
                        <th class="col-id"><a href="<?php echo $get_sort_url('id'); ?>">ID<?php echo $sort_icon('id'); ?></a></th>
                        <th class="col-name"><a href="<?php echo $get_sort_url('name'); ?>">Nombre<?php echo $sort_icon('name'); ?></a></th>
                        <th class="col-email">Email</th>
                        <th class="col-canal">Canal</th>
                        <th class="col-fecha"><a href="<?php echo $get_sort_url('scheduled_date'); ?>">Fecha<?php echo $sort_icon('scheduled_date'); ?></a></th>
                        <th class="col-hora"><a href="<?php echo $get_sort_url('scheduled_time'); ?>">Hora<?php echo $sort_icon('scheduled_time'); ?></a></th>
                        <th class="col-estado">Estado</th>
                        <th class="col-reminder-email" title="Recordatorio Email 72h">72h</th>
                        <th class="col-reminder-email" title="Recordatorio Email 24h">24h</th>
                        <th class="col-reminder-email" title="Recordatorio Email 1h">1h</th>
                        <th class="col-reminder-wa" title="Recordatorio WhatsApp 72h">72h</th>
                        <th class="col-reminder-wa" title="Recordatorio WhatsApp 24h">24h</th>
                        <th class="col-reminder-wa" title="Recordatorio WhatsApp 1h">1h</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                    <tr><td colspan="13" style="text-align:center; padding:20px; color:#666;">No hay agendamientos para los criterios seleccionados.</td></tr>
                <?php else: foreach ($leads as $lead): 
                    // Cálculo robusto usando la zona horaria configurada en WordPress (ej. Chile)
                    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
                    $scheduled_dt = new DateTime($lead->scheduled_date . ' ' . $lead->scheduled_time, $tz);
                    $now_dt = new DateTime('now', $tz);
                    
                    $diff_seconds = $scheduled_dt->getTimestamp() - $now_dt->getTimestamp();
                    $diff_hours = $diff_seconds / 3600;
                    
                    // Determinar estado de botones
                    $can_send_72h = ($diff_hours <= 72 && $diff_hours > 48);
                    $can_send_24h = ($diff_hours <= 24 && $diff_hours > 2);
                    $can_send_1h = ($diff_hours <= 2 && $diff_hours > 0);
                ?>
                    <tr>
                        <td class="col-id"><?php echo $lead->id; ?></td>
                        <td class="col-name"><?php echo esc_html($lead->name); ?></td>
                        <td class="col-email" title="<?php echo esc_attr($lead->email); ?>"><?php echo esc_html($lead->email); ?></td>
                        <td class="col-canal"><?php echo $get_channel_icon($lead->source ?? 'web'); ?></td>
                        <td class="col-fecha"><?php echo date('d-m-Y', strtotime($lead->scheduled_date)); ?></td>
                        <td class="col-hora"><?php echo substr($lead->scheduled_time, 0, 5); ?></td>
                        <td class="col-estado">
                            <?php 
                            if ($lead->confirmed_attendance === '1') echo '<span class="status-confirmed">✓ Confirmado</span>';
                            elseif ($lead->confirmed_attendance === '0') echo '<span class="status-rejected">✗ Rechazado</span>';
                            else echo '<span class="status-pending">⏳ Pendiente</span>';
                            ?>
                        </td>
                        <td class="col-reminder-email">
                            <?php if ($lead->recordatorio72h): ?>
                                <span class="reminder-sent">✓ Enviado</span>
                            <?php else: ?>
                                <button class="button reminder-btn action-btn btn-72h" 
                                        data-id="<?php echo $lead->id; ?>" 
                                        data-type="72h"
                                        <?php echo (!$can_send_72h && !isset($_GET['force'])) ? 'disabled title="Fuera de rango (48h-72h)"' : ''; ?>>
                                    72h
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="col-reminder-email">
                            <?php if ($lead->recordatorio24h): ?>
                                <span class="reminder-sent">✓ Enviado</span>
                            <?php else: ?>
                                <button class="button reminder-btn action-btn btn-24h" 
                                        data-id="<?php echo $lead->id; ?>" 
                                        data-type="24h"
                                        <?php echo (!$can_send_24h && !isset($_GET['force'])) ? 'disabled title="Fuera de rango (2h-24h)"' : ''; ?>>
                                    24h
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="col-reminder-email">
                            <?php if ($lead->recordatorio1h): ?>
                                <span class="reminder-sent">✓ Enviado</span>
                            <?php else: ?>
                                <button class="button reminder-btn action-btn btn-1h" 
                                        data-id="<?php echo $lead->id; ?>" 
                                        data-type="1h"
                                        <?php echo (!$can_send_1h && !isset($_GET['force'])) ? 'disabled title="Fuera de rango (0h-2h)"' : ''; ?>>
                                    1h
                                </button>
                            <?php endif; ?>
                        </td>
                        <!-- WhatsApp Reminder Status (Read-only - Auto-sent by N8N) -->
                        <td class="col-reminder-wa">
                            <?php if (!empty($lead->recordatorio72h_wa)): ?>
                                <span class="wa-check" title="WhatsApp 72h enviado">✓</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="WhatsApp 72h pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-reminder-wa">
                            <?php if (!empty($lead->recordatorio24h_wa)): ?>
                                <span class="wa-check" title="WhatsApp 24h enviado">✓</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="WhatsApp 24h pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-reminder-wa">
                            <?php if (!empty($lead->recordatorio1h_wa)): ?>
                                <span class="wa-check" title="WhatsApp 1h enviado">✓</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="WhatsApp 1h pendiente">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        
        <!-- Progress Modal -->
        <div id="bulk-progress-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:white; padding:20px; border-radius:5px; width:300px; text-align:center;">
                <h3>Enviando Correos...</h3>
                <p id="bulk-progress-text">0 / 0</p>
                <div style="width:100%; background:#eee; height:10px; border-radius:5px; overflow:hidden;">
                    <div id="bulk-progress-bar" style="width:0%; background:#2271b1; height:100%; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        <!-- ========== SECCIÓN DE SEGUIMIENTO (CLIENTES) ========== -->
        <?php
        $followup_table_name = $wpdb->prefix . 'automatiza_followup_meetings';
        
        // Query: solo futuras y no canceladas (misma lógica que citas normales)
        $fu_where = "1=1 AND status != 'cancelled'";
        if ($filter_date) {
            $fu_where .= $wpdb->prepare(" AND meeting_date = %s", $filter_date);
        } else {
            $fu_where .= $wpdb->prepare(
                " AND (meeting_date > %s OR (meeting_date = %s AND meeting_time > %s))",
                $current_date_db, $current_date_db, $current_time_db
            );
        }
        $followup_leads = $wpdb->get_results("SELECT * FROM $followup_table_name WHERE $fu_where ORDER BY meeting_date ASC, meeting_time ASC");
        ?>
        
        <h2 style="margin-top: 35px; padding-top: 20px; border-top: 2px solid #7c3aed;">🔄 Seguimiento de Clientes — Recordatorios</h2>
        <p style="color:#666; margin-bottom:10px;">
            Citas de seguimiento con clientes activos. Los recordatorios se envían automáticamente:
            <strong>📧 Email</strong> y <strong>📱 WhatsApp</strong> a las <strong>8pm</strong> (día anterior) y <strong>8am</strong> (día de la cita).
        </p>
        
        <div class="reminders-table-wrapper">
            <table class="reminders-table">
                <thead>
                    <tr>
                        <th colspan="7" style="background:#fff; border-bottom: 2px solid #e1e1e1;"></th>
                        <th colspan="2" class="th-group" style="background:#e8dff5; color:#5b21b6;">📧 Invitación</th>
                        <th colspan="2" class="th-group" style="background:#fde68a; color:#92400e;">📧 Recordatorio Email</th>
                        <th colspan="2" class="th-group th-group-wa">📱 Recordatorio WA</th>
                    </tr>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-name">Nombre</th>
                        <th style="min-width:100px;">Empresa</th>
                        <th class="col-email">Email</th>
                        <th class="col-fecha">Fecha</th>
                        <th class="col-hora">Hora</th>
                        <th class="col-estado">Estado</th>
                        <th class="col-reminder-email" title="Email de invitación">Email</th>
                        <th class="col-reminder-wa" title="WhatsApp de invitación">WA</th>
                        <th class="col-reminder-email" title="Recordatorio Email 8pm (día anterior)">8pm</th>
                        <th class="col-reminder-email" title="Recordatorio Email 8am (día de la cita)">8am</th>
                        <th class="col-reminder-wa" title="Recordatorio WhatsApp 8pm (día anterior)">8pm</th>
                        <th class="col-reminder-wa" title="Recordatorio WhatsApp 8am (día de la cita)">8am</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($followup_leads)): ?>
                    <tr><td colspan="13" style="text-align:center; padding:20px; color:#666;">No hay citas de seguimiento próximas.</td></tr>
                    <?php else: foreach ($followup_leads as $fu):
                        $fu_dt = new DateTime($fu->meeting_date . ' ' . $fu->meeting_time, $tz);
                        $fu_diff = $fu_dt->getTimestamp() - $now_dt->getTimestamp();
                        $fu_hours = $fu_diff / 3600;
                    ?>
                    <tr>
                        <td class="col-id"><?php echo $fu->id; ?></td>
                        <td class="col-name"><?php echo esc_html($fu->client_name); ?></td>
                        <td><?php echo esc_html($fu->company_name); ?></td>
                        <td class="col-email" title="<?php echo esc_attr($fu->client_email); ?>"><?php echo esc_html($fu->client_email); ?></td>
                        <td class="col-fecha"><?php echo date('d-m-Y', strtotime($fu->meeting_date)); ?></td>
                        <td class="col-hora"><?php echo substr($fu->meeting_time, 0, 5); ?></td>
                        <td class="col-estado">
                            <?php 
                            if ($fu->status === 'confirmed') echo '<span class="status-confirmed">✓ Confirmado</span>';
                            elseif ($fu->status === 'scheduled') echo '<span class="status-pending">⏳ Programada</span>';
                            else echo '<span class="status-pending">⏳ ' . esc_html(ucfirst($fu->status)) . '</span>';
                            ?>
                        </td>
                        <!-- Invitación Email -->
                        <td class="col-reminder-email">
                            <?php if (!empty($fu->email_sent)): ?>
                                <span class="reminder-sent" title="Email de invitación enviado">✓ Enviado</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="Email pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Invitación WhatsApp -->
                        <td class="col-reminder-wa">
                            <?php if (!empty($fu->whatsapp_sent)): ?>
                                <span class="wa-check" title="WhatsApp de invitación enviado">✓</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="WhatsApp pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Recordatorio Email 8pm -->
                        <td class="col-reminder-email">
                            <?php if (!empty($fu->recordatorio_8pm)): ?>
                                <span class="reminder-sent" title="Email 8pm enviado">✓ Enviado</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="Email 8pm pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Recordatorio Email 8am -->
                        <td class="col-reminder-email">
                            <?php if (!empty($fu->recordatorio_8am)): ?>
                                <span class="reminder-sent" title="Email 8am enviado">✓ Enviado</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="Email 8am pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Recordatorio WA 8pm -->
                        <td class="col-reminder-wa">
                            <?php if (!empty($fu->recordatorio_8pm_wa)): ?>
                                <span class="wa-check" title="WA 8pm enviado">✓</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="WA 8pm pendiente">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Recordatorio WA 8am -->
                        <td class="col-reminder-wa">
                            <?php if (!empty($fu->recordatorio_8am_wa)): ?>
                                <span class="wa-check" title="WA 8am enviado">✓</span>
                            <?php else: ?>
                                <span class="reminder-pending" title="WA 8am pendiente">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div style="background:#f0f0f1; padding:15px; border-left:4px solid #2271b1; margin-top:20px;">
            <p style="margin:0;"><strong>📧 Recordatorios por Email (Manual)</strong></p>
            <p style="margin:5px 0 0 0; color:#666;">
                Los botones <strong>📧</strong> envían recordatorios por <strong>correo electrónico</strong> de forma manual.
            </p>
        </div>
        <div style="background:#f0f0f1; padding:15px; border-left:4px solid #25D366; margin-top:10px;">
            <p style="margin:0;"><strong>📱 Recordatorios por WhatsApp (Automático)</strong></p>
            <p style="margin:5px 0 0 0; color:#666;">
                Las columnas <strong>📱</strong> muestran el estado de los recordatorios por <strong>WhatsApp</strong>.<br>
                Estos se envían <strong>automáticamente</strong> mediante N8N según el horario programado.
            </p>
        </div>
        <div style="background:#f0f0f1; padding:15px; border-left:4px solid #7c3aed; margin-top:10px;">
            <p style="margin:0;"><strong>🔄 Seguimiento de Clientes</strong></p>
            <p style="margin:5px 0 0 0; color:#666;">
                Las citas de seguimiento tienen recordatorios automáticos a las <strong>8pm</strong> (día anterior) y <strong>8am</strong> (día de la cita), tanto por Email como por WhatsApp.
            </p>
        </div>
        <p class="description" style="margin-top:10px;">Los botones se habilitan automáticamente cuando el tiempo es el adecuado. Para forzar el envío: añade <code>&force=true</code> a la URL.</p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Envío Individual
        $('.action-btn').click(function() {
            var btn = $(this);
            var lead_id = btn.data('id');
            var type = btn.data('type');
            
            if (!confirm('¿Enviar recordatorio por EMAIL de ' + type + ' a este usuario?')) return;
            
            sendReminder(btn, lead_id, type, function(success) {
                if (success) {
                    alert('📧 Correo enviado correctamente');
                    location.reload();
                }
            });
        });

        // Envío Masivo
        $('.bulk-send-btn').click(function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            
            // Seleccionar botones habilitados de ese tipo
            var buttons = $('.action-btn.btn-' + type + ':not(:disabled)');
            
            if (buttons.length === 0) {
                alert('No hay correos pendientes o habilitados para enviar en la categoría ' + type + '.');
                return;
            }

            if (!confirm('Se enviarán ' + buttons.length + ' correos de tipo ' + type + '. ¿Deseas continuar?')) return;

            // Iniciar proceso masivo
            $('#bulk-progress-modal').css('display', 'flex');
            var total = buttons.length;
            var current = 0;
            
            function processNext() {
                if (current >= total) {
                    alert('Proceso completado.');
                    location.reload();
                    return;
                }

                var btn = $(buttons[current]);
                var lead_id = btn.data('id');
                
                $('#bulk-progress-text').text((current + 1) + ' / ' + total);
                $('#bulk-progress-bar').css('width', ((current + 1) / total * 100) + '%');

                sendReminder(btn, lead_id, type, function() {
                    current++;
                    processNext();
                });
            }

            processNext();
        });

        // Función Helper AJAX
        function sendReminder(btn, lead_id, type, callback) {
            btn.prop('disabled', true).text('...');
            
            $.post(ajaxurl, {
                action: 'send_manual_reminder',
                lead_id: lead_id,
                type: type,
                nonce: '<?php echo wp_create_nonce("manual_reminder_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    btn.text('Enviado').removeClass('button-primary').addClass('button-disabled');
                    // Actualizar visualmente la fila si es necesario
                    callback(true);
                } else {
                    console.error('Error ID ' + lead_id + ': ' + (response.data || 'Unknown'));
                    btn.prop('disabled', false).text('Reintentar');
                    callback(false);
                }
            }).fail(function() {
                btn.prop('disabled', false).text('Error');
                callback(false);
            });
        }
    });
    </script>
    <?php
}

/**
 * AJAX Handler para envío manual
 */
function automatiza_tech_send_manual_reminder() {
    check_ajax_referer('manual_reminder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $lead_id = intval($_POST['lead_id']);
    $type = sanitize_text_field($_POST['type']);
    
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    
    if (!$lead) {
        wp_send_json_error('Lead no encontrado');
    }
    
    // Construir contenido del correo
    $base_url = 'https://automatizatech.cl/wp-json/automatiza-tech/v1/leads/action';
    $token_param = '&token=' . $lead->token;
    $confirm_url = "$base_url?id=$lead_id&action=confirm$token_param";
    $reject_url = "$base_url?id=$lead_id&action=reject$token_param";
    $delete_url = "$base_url?id=$lead_id&action=delete$token_param";
    
    $context_msg = "";
    $subject = "";
    
    // Formatear fecha y hora (DD-MM-YYYY y HH:mm)
    $formatted_date = date('d-m-Y', strtotime($lead->scheduled_date));
    $formatted_time = substr($lead->scheduled_time, 0, 5);

    if ($type === '72h') {
        $subject = "Recordatorio de Agendamiento (72h)";
        $context_msg = "para el <strong>{$formatted_date}</strong> a las <strong>{$formatted_time}</strong>";
    } elseif ($type === '24h') {
        $subject = "Recordatorio de Agendamiento (24h)";
        $context_msg = "para mañana <strong>{$formatted_date}</strong> a las <strong>{$formatted_time}</strong>";
    } elseif ($type === '1h') {
        $subject = "Recordatorio de Agendamiento (1h)";
        $context_msg = "para hoy <strong>{$formatted_date}</strong> a las <strong>{$formatted_time}</strong> (en aproximadamente 1 hora)";
    } else {
        wp_send_json_error('Tipo inválido');
    }
    
    // Obtener logo y nombre del sitio
    $site_title = get_bloginfo('name');
    $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $footer_text = get_bloginfo('description');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333333; }
            .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
            .header { background-color: #1e40af; padding: 40px 20px; text-align: center; }
            .header img { max-height: 80px; width: auto; margin-bottom: 15px; }
            .header h1 { margin: 0; font-size: 24px; color: #ffffff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            .content { padding: 40px 30px; line-height: 1.6; }
            .cta-container { text-align: center; margin: 30px 0; }
            .btn { display: inline-block; padding: 12px 24px; margin: 5px; color: #ffffff !important; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 14px; transition: all 0.3s ease; }
            .btn-confirm { background-color: #06d6a0; box-shadow: 0 4px 6px rgba(6, 214, 160, 0.3); }
            .btn-reject { background-color: #fca311; color: #fff !important; box-shadow: 0 4px 6px rgba(252, 163, 17, 0.3); }
            .footer { background-color: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #888888; }
            .footer a { color: #1e40af; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '">
                <h1>Recordatorio de Reunión</h1>
            </div>
            <div class="content">
                <p>Hola <strong>' . esc_html($lead->name) . '</strong>,</p>
                <p>Esperamos que estés teniendo un excelente día.</p>
                <p>Te escribimos para recordarte tu cita agendada ' . $context_msg . '.</p>
                <p>Para ayudarnos a organizar mejor nuestra agenda, te agradecemos confirmar tu asistencia haciendo clic en uno de los siguientes botones:</p>
                
                <div class="cta-container">
                    <a href="' . $confirm_url . '" class="btn btn-confirm">Confirmar Asistencia</a>
                    <a href="' . $reject_url . '" class="btn btn-reject">No podré asistir</a>
                </div>
                
                <p style="font-size: 13px; color: #666; margin-top: 20px; text-align: center;">
                    Si necesitas cancelar definitivamente, puedes hacerlo aquí: <a href="' . $delete_url . '" style="color: #dc3545;">Cancelar Cita</a>
                </p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . esc_html($site_title) . '. Todos los derechos reservados.</p>
                <p>' . esc_html($footer_text) . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <' . $from_email . '>'
    );
    $sent = wp_mail($lead->email, $subject, $html, $headers);
    
    if ($sent) {
        // Actualizar estado en DB
        $column = 'recordatorio' . $type;
        $wpdb->update($table_name, array($column => 1), array('id' => $lead_id));
        wp_send_json_success();
    } else {
        wp_send_json_error('Fallo al enviar el correo (wp_mail returned false)');
    }
}
add_action('wp_ajax_send_manual_reminder', 'automatiza_tech_send_manual_reminder');

// Nota: La página "Todas las Citas" está implementada en admin-leads-manager.php
