<?php
/**
 * Panel Super Admin - Omnicanal AutomatizaTech
 * 
 * Vista para que el super admin de AutomatizaTech pueda:
 * - Ver todos los clientes con acceso al portal
 * - Gestionar estados y planes
 * - Ver estadísticas globales
 * - Revisar logs de auditoría
 * - Crear/editar clientes
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Solo super administradores.');
}

require_once __DIR__ . '/omnichannel-controller.php';
$controller = new OmnichannelController();

// Manejar acciones POST
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['omni_action'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'omnichannel_admin')) {
        wp_die('Nonce inválido');
    }

    $action = sanitize_text_field($_POST['omni_action']);

    if ($action === 'create_client') {
        $result = $controller->create_client([
            'company_name' => $_POST['company_name'] ?? '',
            'contact_name' => $_POST['contact_name'] ?? '',
            'email'        => $_POST['email'] ?? '',
            'phone'        => $_POST['phone'] ?? '',
            'plan_type'    => $_POST['plan_type'] ?? 'basic',
            'max_channels' => $_POST['max_channels'] ?? 2,
            'max_agents'   => $_POST['max_agents'] ?? 3,
        ]);
        if ($result && !isset($result['error'])) {
            $action_message = '<div class="alert success">✅ Cliente creado. API Key: <code>' . esc_html($result['api_key']) . '</code></div>';
        } else {
            $action_message = '<div class="alert error">❌ Error al crear cliente</div>';
        }
    }

    if ($action === 'update_client') {
        $client_id = absint($_POST['client_id'] ?? 0);
        $controller->update_client($client_id, [
            'status'       => $_POST['status'] ?? '',
            'plan_type'    => $_POST['plan_type'] ?? '',
            'max_channels' => $_POST['max_channels'] ?? '',
            'max_agents'   => $_POST['max_agents'] ?? '',
        ]);
        $action_message = '<div class="alert success">✅ Cliente actualizado</div>';
    }
}

// Data
$stats = $controller->get_superadmin_stats();
$current_tab = sanitize_text_field($_GET['tab'] ?? 'dashboard');
$page_num = absint($_GET['paged'] ?? 1);

$clients_data = $controller->get_clients([
    'status' => sanitize_text_field($_GET['filter_status'] ?? ''),
    'search' => sanitize_text_field($_GET['s'] ?? ''),
], $page_num, 20);

$audit_data = $controller->get_audit_logs([
    'client_id' => sanitize_text_field($_GET['audit_client'] ?? ''),
    'action'    => sanitize_text_field($_GET['audit_action'] ?? ''),
], absint($_GET['audit_page'] ?? 1), 30);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin | Portal Omnicanal - AutomatizaTech</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        
        .layout { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--sidebar-bg); color: var(--sidebar-text); padding: 24px 16px; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar .logo { display: flex; align-items: center; gap: 12px; padding: 8px 12px; margin-bottom: 32px; }
        .sidebar .logo img { width: 36px; height: 36px; border-radius: 8px; }
        .sidebar .logo h2 { font-size: 16px; font-weight: 700; color: #fff; }
        .sidebar .logo small { display: block; font-size: 11px; color: #94a3b8; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #94a3b8; text-decoration: none; font-size: 14px; margin: 2px 0; transition: all 0.2s; }
        .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar nav .section-title { font-size: 11px; text-transform: uppercase; color: #475569; padding: 16px 14px 6px; letter-spacing: 0.05em; }
        
        /* Main content */
        .main { margin-left: 260px; flex: 1; padding: 32px; max-width: 1400px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .header h1 { font-size: 24px; font-weight: 700; }
        .header .badge { background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: var(--card); border-radius: 12px; padding: 20px; border: 1px solid var(--border); }
        .stat-card .label { font-size: 13px; color: var(--text-muted); margin-bottom: 4px; }
        .stat-card .value { font-size: 28px; font-weight: 700; }
        .stat-card .subtitle { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .stat-card.green .value { color: var(--success); }
        .stat-card.blue .value { color: var(--primary); }
        .stat-card.orange .value { color: var(--warning); }
        .stat-card.red .value { color: var(--danger); }
        
        /* Cards */
        .card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); margin-bottom: 24px; overflow: hidden; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 16px; font-weight: 600; }
        .card-body { padding: 20px; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 14px; font-size: 12px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); background: #f8fafc; letter-spacing: 0.03em; }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        /* Badges */
        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-trial { background: #dbeafe; color: #1e40af; }
        .badge-suspended { background: #fef3c7; color: #92400e; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        
        .badge-channel { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-whatsapp { background: #d1fae5; color: #065f46; }
        .badge-instagram { background: #fce7f3; color: #9d174d; }
        .badge-telegram { background: #dbeafe; color: #1e40af; }
        .badge-messenger { background: #e0e7ff; color: #3730a3; }
        
        /* Forms */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: #fff; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        
        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 500; transition: all 0.2s; text-decoration: none; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        
        /* Alert */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid var(--border); padding-bottom: 0; }
        .tabs a { padding: 8px 16px; border-radius: 8px 8px 0 0; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 500; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tabs a.active { color: var(--primary); border-bottom-color: var(--primary); background: #eff6ff; }
        
        /* Pagination */
        .pagination { display: flex; gap: 4px; margin-top: 16px; justify-content: center; }
        .pagination a { padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; color: var(--text); text-decoration: none; font-size: 13px; }
        .pagination a.current { background: var(--primary); color: #fff; border-color: var(--primary); }
        
        /* Toolbar */
        .toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
        .toolbar input[type="search"] { padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; min-width: 250px; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: white; border-radius: 16px; padding: 24px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal h3 { font-size: 18px; margin-bottom: 16px; }
        
        /* Audit details */
        .audit-json { background: #f1f5f9; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 150px; overflow-y: auto; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="logo">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,#2563eb,#7c3aed);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:14px;">AT</div>
            <div>
                <h2>AutomatizaTech</h2>
                <small>Portal Omnicanal</small>
            </div>
        </div>
        
        <nav>
            <div class="section-title">Principal</div>
            <a href="?tab=dashboard" class="<?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
            <a href="?tab=clients" class="<?php echo $current_tab === 'clients' ? 'active' : ''; ?>">🏢 Clientes</a>
            <a href="?tab=new_client" class="<?php echo $current_tab === 'new_client' ? 'active' : ''; ?>">➕ Nuevo Cliente</a>
            
            <div class="section-title">Monitoreo</div>
            <a href="?tab=audit" class="<?php echo $current_tab === 'audit' ? 'active' : ''; ?>">📋 Auditoría</a>
            <a href="?tab=channels_overview" class="<?php echo $current_tab === 'channels_overview' ? 'active' : ''; ?>">📡 Canales Globales</a>
            
            <div class="section-title">Sistema</div>
            <a href="<?php echo esc_url(admin_url()); ?>">⚙️ WordPress Admin</a>
            <a href="admin-ai-dashboard.php">🤖 AI Dashboard</a>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
        <?php echo $action_message; ?>

        <?php if ($current_tab === 'dashboard'): ?>
        <!-- ===== DASHBOARD ===== -->
        <div class="header">
            <div>
                <h1>Panel Super Admin</h1>
                <p style="color:var(--text-muted);font-size:14px;">Gestión global del Portal Omnicanal</p>
            </div>
            <span class="badge">v1.0</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="label">Total Clientes</div>
                <div class="value"><?php echo esc_html($stats['total_clients']); ?></div>
            </div>
            <div class="stat-card green">
                <div class="label">Clientes Activos</div>
                <div class="value"><?php echo esc_html($stats['active_clients']); ?></div>
            </div>
            <div class="stat-card orange">
                <div class="label">En Trial</div>
                <div class="value"><?php echo esc_html($stats['trial_clients']); ?></div>
            </div>
            <div class="stat-card blue">
                <div class="label">Canales Activos</div>
                <div class="value"><?php echo esc_html($stats['total_channels']); ?></div>
            </div>
            <div class="stat-card green">
                <div class="label">Conversaciones</div>
                <div class="value"><?php echo esc_html($stats['total_conversations']); ?></div>
                <div class="subtitle"><?php echo esc_html($stats['active_conversations']); ?> activas</div>
            </div>
            <div class="stat-card blue">
                <div class="label">Mensajes Hoy</div>
                <div class="value"><?php echo esc_html($stats['total_messages_today']); ?></div>
            </div>
            <div class="stat-card orange">
                <div class="label">Takeovers Activos</div>
                <div class="value"><?php echo esc_html($stats['active_takeovers']); ?></div>
            </div>
        </div>

        <!-- Distribución por canal -->
        <div class="card">
            <div class="card-header"><h3>📊 Mensajes por Canal</h3></div>
            <div class="card-body">
                <table>
                    <tr><th>Canal</th><th>Total Mensajes</th></tr>
                    <?php foreach ($stats['messages_by_channel'] as $ch): ?>
                    <tr>
                        <td><span class="badge-channel badge-<?php echo esc_attr($ch->channel_type); ?>"><?php echo esc_html(ucfirst($ch->channel_type)); ?></span></td>
                        <td><?php echo esc_html(number_format($ch->total)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['messages_by_channel'])): ?>
                    <tr><td colspan="2" style="color:var(--text-muted);text-align:center;">Aún no hay mensajes registrados</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Clientes por plan -->
        <div class="card">
            <div class="card-header"><h3>📦 Clientes por Plan</h3></div>
            <div class="card-body">
                <table>
                    <tr><th>Plan</th><th>Cantidad</th></tr>
                    <?php foreach ($stats['clients_by_plan'] as $p): ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst($p->plan_type)); ?></td>
                        <td><?php echo esc_html($p->total); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['clients_by_plan'])): ?>
                    <tr><td colspan="2" style="color:var(--text-muted);text-align:center;">Sin clientes registrados</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Últimas auditorías -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Actividad Reciente</h3>
                <a href="?tab=audit" class="btn btn-outline btn-sm">Ver todo</a>
            </div>
            <div class="card-body" style="padding:0;">
                <table>
                    <tr><th>Fecha</th><th>Acción</th><th>Entidad</th><th>Usuario</th><th>Descripción</th></tr>
                    <?php foreach ($stats['recent_audit'] as $log): ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:12px;"><?php echo esc_html($log->created_at); ?></td>
                        <td><span class="badge-status badge-active"><?php echo esc_html($log->action); ?></span></td>
                        <td><?php echo esc_html($log->entity_type); ?> #<?php echo esc_html($log->entity_id); ?></td>
                        <td><?php echo esc_html($log->user_email ?: 'Sistema'); ?></td>
                        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html(mb_substr($log->description, 0, 100)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['recent_audit'])): ?>
                    <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:24px;">Sin actividad registrada</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php elseif ($current_tab === 'clients'): ?>
        <!-- ===== CLIENTES ===== -->
        <div class="header">
            <h1>🏢 Clientes del Portal</h1>
            <a href="?tab=new_client" class="btn btn-primary">➕ Nuevo Cliente</a>
        </div>

        <div class="toolbar">
            <form method="GET" style="display:flex;gap:12px;align-items:center;">
                <input type="hidden" name="tab" value="clients">
                <input type="search" name="s" placeholder="Buscar empresa, nombre, email..." value="<?php echo esc_attr($_GET['s'] ?? ''); ?>">
                <select name="filter_status" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="active" <?php selected($_GET['filter_status'] ?? '', 'active'); ?>>Activo</option>
                    <option value="trial" <?php selected($_GET['filter_status'] ?? '', 'trial'); ?>>Trial</option>
                    <option value="suspended" <?php selected($_GET['filter_status'] ?? '', 'suspended'); ?>>Suspendido</option>
                    <option value="cancelled" <?php selected($_GET['filter_status'] ?? '', 'cancelled'); ?>>Cancelado</option>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">🔍 Buscar</button>
            </form>
            <span style="color:var(--text-muted);font-size:13px;"><?php echo esc_html($clients_data['total']); ?> clientes encontrados</span>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table>
                    <tr>
                        <th>ID</th><th>Empresa</th><th>Contacto</th><th>Email</th><th>Plan</th>
                        <th>Estado</th><th>Canales</th><th>Convs</th><th>Agentes</th><th>Creado</th><th>Acciones</th>
                    </tr>
                    <?php foreach ($clients_data['data'] as $client): ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($client->id); ?></strong></td>
                        <td><?php echo esc_html($client->company_name); ?></td>
                        <td><?php echo esc_html($client->contact_name); ?></td>
                        <td><?php echo esc_html($client->email); ?></td>
                        <td><span class="badge-status badge-active"><?php echo esc_html(ucfirst($client->plan_type)); ?></span></td>
                        <td>
                            <span class="badge-status badge-<?php echo esc_attr($client->status); ?>">
                                <?php echo esc_html(ucfirst($client->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($client->active_channels); ?></td>
                        <td><?php echo esc_html($client->total_conversations); ?></td>
                        <td><?php echo esc_html($client->active_agents); ?></td>
                        <td style="white-space:nowrap;font-size:12px;"><?php echo esc_html(substr($client->created_at, 0, 10)); ?></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo esc_attr(wp_json_encode($client)); ?>)">✏️</button>
                            <a href="?tab=audit&audit_client=<?php echo esc_attr($client->id); ?>" class="btn btn-outline btn-sm">📋</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clients_data['data'])): ?>
                    <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:40px;">No se encontraron clientes</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($clients_data['total_pages'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $clients_data['total_pages']; $i++): ?>
            <a href="?tab=clients&paged=<?php echo $i; ?>&s=<?php echo esc_attr($_GET['s'] ?? ''); ?>&filter_status=<?php echo esc_attr($_GET['filter_status'] ?? ''); ?>" 
               class="<?php echo $i === $page_num ? 'current' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <!-- Modal editar cliente -->
        <div class="modal-overlay" id="editModal">
            <div class="modal">
                <h3>✏️ Editar Cliente</h3>
                <form method="POST">
                    <input type="hidden" name="omni_action" value="update_client">
                    <?php wp_nonce_field('omnichannel_admin'); ?>
                    <input type="hidden" name="client_id" id="edit_client_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="status" id="edit_status">
                                <option value="active">Activo</option>
                                <option value="trial">Trial</option>
                                <option value="suspended">Suspendido</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan</label>
                            <select name="plan_type" id="edit_plan">
                                <option value="basic">Basic</option>
                                <option value="professional">Professional</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Máx. Canales</label>
                            <input type="number" name="max_channels" id="edit_channels" min="1" max="20">
                        </div>
                        <div class="form-group">
                            <label>Máx. Agentes</label>
                            <input type="number" name="max_agents" id="edit_agents" min="1" max="50">
                        </div>
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">💾 Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditModal(client) {
            document.getElementById('edit_client_id').value = client.id;
            document.getElementById('edit_status').value = client.status;
            document.getElementById('edit_plan').value = client.plan_type;
            document.getElementById('edit_channels').value = client.max_channels;
            document.getElementById('edit_agents').value = client.max_agents;
            document.getElementById('editModal').classList.add('show');
        }
        </script>

        <?php elseif ($current_tab === 'new_client'): ?>
        <!-- ===== NUEVO CLIENTE ===== -->
        <div class="header">
            <h1>➕ Registrar Nuevo Cliente</h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="omni_action" value="create_client">
                    <?php wp_nonce_field('omnichannel_admin'); ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Empresa *</label>
                            <input type="text" name="company_name" required placeholder="Nombre de la empresa">
                        </div>
                        <div class="form-group">
                            <label>Contacto *</label>
                            <input type="text" name="contact_name" required placeholder="Nombre del contacto">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required placeholder="email@empresa.com">
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="phone" placeholder="+52 55 1234 5678">
                        </div>
                        <div class="form-group">
                            <label>Plan</label>
                            <select name="plan_type">
                                <option value="basic">Basic (2 canales, 3 agentes)</option>
                                <option value="professional">Professional (5 canales, 10 agentes)</option>
                                <option value="enterprise">Enterprise (ilimitado)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Máx. Canales</label>
                            <input type="number" name="max_channels" value="2" min="1" max="20">
                        </div>
                        <div class="form-group">
                            <label>Máx. Agentes</label>
                            <input type="number" name="max_agents" value="3" min="1" max="50">
                        </div>
                    </div>
                    
                    <div style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary">🚀 Crear Cliente</button>
                        <span style="color:var(--text-muted);font-size:13px;margin-left:12px;">Se generará automáticamente un API Key y 14 días de trial</span>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($current_tab === 'audit'): ?>
        <!-- ===== AUDITORÍA ===== -->
        <div class="header">
            <h1>📋 Registro de Auditoría</h1>
        </div>

        <div class="toolbar">
            <form method="GET" style="display:flex;gap:12px;align-items:center;">
                <input type="hidden" name="tab" value="audit">
                <select name="audit_action" onchange="this.form.submit()">
                    <option value="">Todas las acciones</option>
                    <option value="create" <?php selected($_GET['audit_action'] ?? '', 'create'); ?>>Crear</option>
                    <option value="update" <?php selected($_GET['audit_action'] ?? '', 'update'); ?>>Actualizar</option>
                    <option value="delete" <?php selected($_GET['audit_action'] ?? '', 'delete'); ?>>Eliminar</option>
                    <option value="takeover" <?php selected($_GET['audit_action'] ?? '', 'takeover'); ?>>Takeover</option>
                    <option value="release" <?php selected($_GET['audit_action'] ?? '', 'release'); ?>>Release</option>
                    <option value="transfer" <?php selected($_GET['audit_action'] ?? '', 'transfer'); ?>>Transfer</option>
                </select>
                <?php if (!empty($_GET['audit_client'])): ?>
                <span class="badge-status badge-active">Cliente #<?php echo esc_html($_GET['audit_client']); ?></span>
                <a href="?tab=audit" class="btn btn-outline btn-sm">✕ Quitar filtro</a>
                <?php endif; ?>
                <span style="color:var(--text-muted);font-size:13px;"><?php echo esc_html($audit_data['total']); ?> registros</span>
            </form>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table>
                    <tr><th>Fecha</th><th>Cliente</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Descripción</th><th>IP</th><th>Detalles</th></tr>
                    <?php foreach ($audit_data['data'] as $log): ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:12px;"><?php echo esc_html($log->created_at); ?></td>
                        <td>#<?php echo esc_html($log->client_id ?: '-'); ?></td>
                        <td style="font-size:12px;"><?php echo esc_html($log->user_email ?: 'Sistema'); ?><br><small style="color:var(--text-muted);"><?php echo esc_html($log->user_role); ?></small></td>
                        <td><span class="badge-status badge-active"><?php echo esc_html($log->action); ?></span></td>
                        <td><?php echo esc_html($log->entity_type); ?> #<?php echo esc_html($log->entity_id); ?></td>
                        <td style="max-width:250px;"><?php echo esc_html(mb_substr($log->description, 0, 120)); ?></td>
                        <td style="font-size:12px;"><?php echo esc_html($log->ip_address); ?></td>
                        <td>
                            <?php if ($log->old_values_json || $log->new_values_json): ?>
                            <button class="btn btn-outline btn-sm" onclick="toggleDetails(<?php echo esc_attr($log->id); ?>)">👁️</button>
                            <div id="details-<?php echo esc_attr($log->id); ?>" style="display:none;position:absolute;background:white;border:1px solid var(--border);border-radius:8px;padding:12px;z-index:10;max-width:400px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                                <?php if ($log->old_values_json): ?>
                                <p style="font-size:12px;font-weight:600;margin-bottom:4px;">Valores anteriores:</p>
                                <div class="audit-json"><?php echo esc_html(json_encode(json_decode($log->old_values_json), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></div>
                                <?php endif; ?>
                                <?php if ($log->new_values_json): ?>
                                <p style="font-size:12px;font-weight:600;margin:8px 0 4px;">Valores nuevos:</p>
                                <div class="audit-json"><?php echo esc_html(json_encode(json_decode($log->new_values_json), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($audit_data['data'])): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px;">Sin registros de auditoría</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($audit_data['total_pages'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= min($audit_data['total_pages'], 20); $i++): ?>
            <a href="?tab=audit&audit_page=<?php echo $i; ?>&audit_action=<?php echo esc_attr($_GET['audit_action'] ?? ''); ?>&audit_client=<?php echo esc_attr($_GET['audit_client'] ?? ''); ?>" 
               class="<?php echo $i === absint($_GET['audit_page'] ?? 1) ? 'current' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <script>
        function toggleDetails(id) {
            var el = document.getElementById('details-' + id);
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
        </script>

        <?php elseif ($current_tab === 'channels_overview'): ?>
        <!-- ===== CANALES OVERVIEW ===== -->
        <div class="header">
            <h1>📡 Vista Global de Canales</h1>
        </div>

        <?php
        global $wpdb;
        $all_channels = $wpdb->get_results(
            "SELECT ch.*, cl.company_name, cl.status as client_status,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}omnichannel_conversations WHERE channel_id = ch.id) as conv_count,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}omnichannel_messages m 
                     JOIN {$wpdb->prefix}omnichannel_conversations c ON m.conversation_id = c.id 
                     WHERE c.channel_id = ch.id AND DATE(m.created_at) = CURDATE()) as msgs_today
             FROM {$wpdb->prefix}omnichannel_channels ch
             JOIN {$wpdb->prefix}omnichannel_clients cl ON ch.client_id = cl.id
             ORDER BY ch.channel_type, cl.company_name"
        );
        ?>

        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table>
                    <tr><th>Canal</th><th>Tipo</th><th>Cliente</th><th>Estado</th><th>Conversaciones</th><th>Msgs Hoy</th><th>Última Sync</th></tr>
                    <?php foreach ($all_channels as $ch): ?>
                    <tr>
                        <td><strong><?php echo esc_html($ch->channel_name); ?></strong></td>
                        <td><span class="badge-channel badge-<?php echo esc_attr($ch->channel_type); ?>"><?php echo esc_html(ucfirst($ch->channel_type)); ?></span></td>
                        <td><?php echo esc_html($ch->company_name); ?></td>
                        <td>
                            <?php if ($ch->is_active): ?>
                                <span class="badge-status badge-active">Activo</span>
                            <?php else: ?>
                                <span class="badge-status badge-suspended">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($ch->conv_count); ?></td>
                        <td><?php echo esc_html($ch->msgs_today); ?></td>
                        <td style="font-size:12px;"><?php echo esc_html($ch->last_synced_at ?: 'Nunca'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($all_channels)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px;">No hay canales configurados</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>
