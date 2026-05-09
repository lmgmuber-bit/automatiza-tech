<?php
/**
 * Panel de QA — Automatiza Tech
 * Sistema general de gestión de pruebas para todos los proyectos/clientes
 * 
 * Arquitectura:
 *   Proyecto QA → Módulos (suites) → Casos de prueba → Evidencias + Comentarios
 * 
 * @package AutomatizaTech
 * @since 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AT_QA_VERSION', '1.3.1');
define('AT_QA_EVIDENCE_DIR', 'qa-evidencias');

// ──────────────────────────────────────────────
// 1. TABLAS
// ──────────────────────────────────────────────
function at_qa_table_names() {
    global $wpdb;
    return [
        'projects'  => $wpdb->prefix . 'at_qa_projects',
        'modules'   => $wpdb->prefix . 'at_qa_modules',
        'cases'     => $wpdb->prefix . 'at_qa_cases',
        'evidence'  => $wpdb->prefix . 'at_qa_evidence',
        'comments'  => $wpdb->prefix . 'at_qa_comments',
    ];
}

// ──────────────────────────────────────────────
// 1b. HELPER: Enviar correo QA con plantilla AT
// ──────────────────────────────────────────────
function at_qa_send_notification($to, $subject, $heading, $subtitle, $body_html, $extra_headers = []) {
    // En localhost no enviar correos (solo funciona en PROD)
    $host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
    if (in_array($host, ['localhost', '127.0.0.1']) || strpos($host, 'localhost') !== false) {
        return false;
    }

    $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $html = '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>' . esc_html($subject) . '</title>
<style>
body{font-family:Arial,sans-serif;background:#f0f0f0;color:#222;margin:0;padding:0;}
.container{background:#fff;max-width:600px;margin:40px auto;border-radius:10px;box-shadow:0 2px 8px #0001;overflow:hidden;}
.header{background:linear-gradient(135deg,#0d9488,#14b8a6,#2dd4bf);color:#fff;text-align:center;padding:32px 20px 20px;}
.header img{max-width:140px;margin-bottom:10px;}
.content{padding:32px 24px;}
.info-box{background:#f0fdfa;border-left:4px solid #0d9488;padding:15px;margin:15px 0;border-radius:4px;}
.cta{display:inline-block;background:linear-gradient(135deg,#0d9488,#14b8a6);color:#fff!important;padding:12px 32px;border-radius:25px;text-decoration:none;font-weight:bold;margin:10px 5px;}
.cta-secondary{display:inline-block;background:#e5e7eb;color:#374151!important;padding:10px 24px;border-radius:25px;text-decoration:none;font-weight:600;margin:10px 5px;}
.footer{background:#f8f9fa;color:#6c757d;text-align:center;font-size:0.9em;padding:18px 10px;}
.badge-pass{background:#059669;color:#fff;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:700;}
.badge-fail{background:#dc2626;color:#fff;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:700;}
.badge-blocked{background:#f59e0b;color:#fff;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:700;}
</style></head><body>
<div class="container">
  <div class="header">
    <img src="' . $logo_url . '" alt="AutomatizaTech Logo">
    <h1 style="margin:10px 0 5px;">' . $heading . '</h1>
    <p style="margin:0;opacity:0.9;">' . $subtitle . '</p>
  </div>
  <div class="content">' . $body_html . '</div>
  <div class="footer">
    &copy; ' . date('Y') . ' AutomatizaTech. Todos los derechos reservados.<br>
    Correo enviado automáticamente &mdash; <a href="https://automatizatech.cl/" style="color:#0d9488;">automatizatech.cl</a>
  </div>
</div></body></html>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <' . $from_email . '>',
        'Bcc: lgonzalez@automatizatech.cl, anamaria.sandoval@automatizatech.cl, automatizacionesbotcore@gmail.com',
    ];
    $headers = array_merge($headers, $extra_headers);

    error_log('[QA-EMAIL] Intentando enviar correo...');
    error_log('[QA-EMAIL] To: ' . $to);
    error_log('[QA-EMAIL] Subject: ' . $subject);
    error_log('[QA-EMAIL] From: ' . $from_email);
    error_log('[QA-EMAIL] Headers: ' . print_r($headers, true));

    $result = wp_mail($to, $subject, $html, $headers);

    if ($result) {
        error_log('[QA-EMAIL] ✅ wp_mail() retornó TRUE — correo enviado a: ' . $to);
    } else {
        error_log('[QA-EMAIL] ❌ wp_mail() retornó FALSE — FALLÓ envío a: ' . $to);
        global $phpmailer;
        if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            error_log('[QA-EMAIL] PHPMailer Error: ' . $phpmailer->ErrorInfo);
        }
    }

    return $result;
}

/**
 * Obtener datos de contexto QA (proyecto, cliente, módulo) a partir de un case_id
 */
function at_qa_get_context($case_db_id) {
    global $wpdb;
    $t = at_qa_table_names();
    $caso = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, m.title as module_name, m.assigned_tester, m.project_id 
         FROM {$t['cases']} c 
         JOIN {$t['modules']} m ON m.id = c.module_id 
         WHERE c.id = %d", $case_db_id
    ));
    if (!$caso) return null;

    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['projects']} WHERE id = %d", $caso->project_id));
    $client = null;
    if ($project && $project->client_id) {
        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $project->client_id));
    }
    $tester_user = $caso->assigned_tester ? get_userdata($caso->assigned_tester) : null;

    return (object)[
        'caso'    => $caso,
        'project' => $project,
        'client'  => $client,
        'tester'  => $tester_user,
    ];
}
// ──────────────────────────────────────────────
function at_qa_setup_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $t = at_qa_table_names();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Proyectos QA (uno por cliente/proyecto)
    dbDelta("CREATE TABLE {$t['projects']} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id INT UNSIGNED DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        description TEXT,
        qa_status ENUM('pending','in_progress','passed','failed','on_hold') DEFAULT 'pending',
        version VARCHAR(50) DEFAULT '1.0',
        environment VARCHAR(100) DEFAULT '',
        md_base_path VARCHAR(500) DEFAULT '',
        total_cases INT UNSIGNED DEFAULT 0,
        assigned_testers TEXT,
        started_at DATETIME DEFAULT NULL,
        finished_at DATETIME DEFAULT NULL,
        last_report_at DATETIME DEFAULT NULL,
        last_report_pdf VARCHAR(255) DEFAULT NULL,
        last_report_sent_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_slug (slug),
        KEY idx_client (client_id),
        KEY idx_status (qa_status)
    ) $charset;");

    // Módulos / Suites
    dbDelta("CREATE TABLE {$t['modules']} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        code VARCHAR(20) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        total_cases INT UNSIGNED DEFAULT 0,
        md_file VARCHAR(255) DEFAULT '',
        assigned_tester INT UNSIGNED DEFAULT NULL,
        sort_order INT UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_project (project_id)
    ) $charset;");

    // Casos de prueba
    dbDelta("CREATE TABLE {$t['cases']} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        module_id INT UNSIGNED NOT NULL,
        case_id VARCHAR(20) NOT NULL,
        section VARCHAR(255) DEFAULT '',
        title VARCHAR(500) NOT NULL,
        precondition TEXT,
        steps TEXT,
        expected_result TEXT,
        priority ENUM('Alta','Media','Baja') DEFAULT 'Media',
        status ENUM('not_tested','pass','fail','blocked','skipped') DEFAULT 'not_tested',
        tester VARCHAR(100) DEFAULT '',
        tested_at DATETIME DEFAULT NULL,
        bug_id VARCHAR(100) DEFAULT '',
        sort_order INT UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_module (module_id),
        KEY idx_status (status)
    ) $charset;");

    // Evidencias
    dbDelta("CREATE TABLE {$t['evidence']} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        case_id INT UNSIGNED NOT NULL,
        file_url VARCHAR(500) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) DEFAULT '',
        file_size INT UNSIGNED DEFAULT 0,
        uploaded_by INT UNSIGNED NOT NULL,
        description VARCHAR(500) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_case (case_id)
    ) $charset;");

    // Comentarios
    dbDelta("CREATE TABLE {$t['comments']} (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        case_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_case (case_id)
    ) $charset;");

    // Migración: agregar columna updated_at si no existe
    $col_exists = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t['comments']}' AND COLUMN_NAME = 'updated_at'");
    if (!$col_exists) {
        $wpdb->query("ALTER TABLE {$t['comments']} ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at");
    }

    // Migración: agregar columna is_internal a proyectos si no existe
    $col_internal = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t['projects']}' AND COLUMN_NAME = 'is_internal'");
    if (!$col_internal) {
        $wpdb->query("ALTER TABLE {$t['projects']} ADD COLUMN is_internal TINYINT(1) NOT NULL DEFAULT 0 AFTER client_id");
    }

    // Migración v1.3.1: agregar columnas de informe si no existen
    $report_cols = [
        'last_report_at'      => "ALTER TABLE {$t['projects']} ADD COLUMN last_report_at DATETIME DEFAULT NULL AFTER finished_at",
        'last_report_pdf'     => "ALTER TABLE {$t['projects']} ADD COLUMN last_report_pdf VARCHAR(255) DEFAULT NULL AFTER last_report_at",
        'last_report_sent_at' => "ALTER TABLE {$t['projects']} ADD COLUMN last_report_sent_at DATETIME DEFAULT NULL AFTER last_report_pdf",
    ];
    foreach ($report_cols as $col_name => $alter_sql) {
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t['projects']}' AND COLUMN_NAME = '{$col_name}'");
        if (!$exists) {
            $wpdb->query($alter_sql);
            error_log("[AT QA] Migración: columna '{$col_name}' creada en {$t['projects']}");
        }
    }

    // Limpiar tablas antiguas si existen (migración desde versión PetsGO-only)
    $old_tables = [
        $wpdb->prefix . 'qa_petsgo_modules',
        $wpdb->prefix . 'qa_petsgo_cases',
        $wpdb->prefix . 'qa_petsgo_evidence',
        $wpdb->prefix . 'qa_petsgo_comments',
    ];
    foreach ($old_tables as $ot) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$ot'") === $ot) {
            // No eliminar — por si hay datos. Solo informar en log.
            error_log("[AT QA] Tabla antigua detectada: $ot — considerar migrar datos.");
        }
    }

    update_option('at_qa_db_version', AT_QA_VERSION);
}

add_action('admin_init', function() {
    if (get_option('at_qa_db_version') !== AT_QA_VERSION) {
        at_qa_setup_tables();
    }
});

// ──────────────────────────────────────────────
// 3. ROL QA TESTER
// ──────────────────────────────────────────────
function at_qa_setup_role() {
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('at_qa_access')) {
        $admin->add_cap('at_qa_access');
    }
    if (!get_role('qa_tester')) {
        add_role('qa_tester', 'QA Tester', [
            'read'           => true,
            'edit_posts'     => true,
            'upload_files'   => true,
            'at_qa_access'   => true,
        ]);
    } else {
        // Asegurar que el rol tiene edit_posts para acceder al CRM
        $qt = get_role('qa_tester');
        if ($qt && !$qt->has_cap('edit_posts')) {
            $qt->add_cap('edit_posts');
        }
    }
}
add_action('admin_init', 'at_qa_setup_role');

// ──────────────────────────────────────────────
// 4. MENÚ ADMIN
// ──────────────────────────────────────────────
function at_qa_admin_menu() {
    add_menu_page(
        'QA — Pruebas',
        '🧪 QA Pruebas',
        'at_qa_access',
        'at-qa',
        'at_qa_router',
        'dashicons-yes-alt',
        28
    );
    add_submenu_page('at-qa', 'Proyectos QA', 'Proyectos', 'at_qa_access', 'at-qa', 'at_qa_router');
    add_submenu_page('at-qa', 'Importar Casos', 'Importar desde MD', 'manage_options', 'at-qa-import', 'at_qa_render_import_page');
}
add_action('admin_menu', 'at_qa_admin_menu');

// ──────────────────────────────────────────────
// 5. ROUTER
// ──────────────────────────────────────────────
function at_qa_router() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');

    $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'projects';

    switch ($view) {
        case 'suite':
            at_qa_render_suite_page();
            break;
        default:
            at_qa_render_projects_page();
            break;
    }
}

// ──────────────────────────────────────────────
// 6. PARSEAR MARKDOWN
// ──────────────────────────────────────────────
function at_qa_parse_md_file($filepath) {
    if (!file_exists($filepath)) return null;

    $content = file_get_contents($filepath);
    $lines   = explode("\n", $content);

    $module_title = '';
    $module_desc  = '';
    $current_section = '';
    $cases = [];
    $in_table = false;

    foreach ($lines as $line) {
        $line = rtrim($line);

        if (preg_match('/^#\s+(.+)$/', $line, $m)) {
            $module_title = trim($m[1]);
            continue;
        }
        if (preg_match('/^\*\*Módulo:\*\*\s*(.+)$/', $line, $m)) {
            $module_desc = trim($m[1]);
            continue;
        }
        if (preg_match('/^##\s+\d+\.\s+(.+)$/', $line, $m)) {
            $current_section = trim($m[1]);
            $in_table = false;
            continue;
        }
        if (preg_match('/^\|\s*ID\s*\|/', $line)) {
            $in_table = true;
            continue;
        }
        if ($in_table && preg_match('/^\|[\s\-\|]+$/', $line)) {
            continue;
        }
        if ($in_table && preg_match('/^\|(.+)\|$/', $line)) {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (count($cells) >= 5 && !empty($cells[0]) && preg_match('/^[A-Z]{2,3}-\d{3}$/', $cells[0])) {
                $cases[] = [
                    'case_id'         => $cells[0],
                    'section'         => $current_section,
                    'title'           => $cells[1] ?? '',
                    'precondition'    => $cells[2] ?? '',
                    'steps'           => $cells[3] ?? '',
                    'expected_result' => $cells[4] ?? '',
                    'priority'        => isset($cells[5]) && in_array(trim($cells[5]), ['Alta','Media','Baja']) ? trim($cells[5]) : 'Media',
                ];
            }
        }
        if ($in_table && trim($line) === '') {
            $in_table = false;
        }
    }

    return ['title' => $module_title, 'description' => $module_desc, 'cases' => $cases];
}

// ──────────────────────────────────────────────
// 7. IMPORTAR CASOS
// ──────────────────────────────────────────────
function at_qa_import_project_from_md($project_id, $md_path, $file_map) {
    global $wpdb;
    $t = at_qa_table_names();
    $imported = 0;
    $sort = 0;

    foreach ($file_map as $code => $filename) {
        $filepath = rtrim($md_path, '/\\') . '/' . $filename;
        $parsed = at_qa_parse_md_file($filepath);
        if (!$parsed || empty($parsed['cases'])) continue;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['modules']} WHERE project_id = %d AND code = %s", $project_id, $code
        ));

        $mod_data = [
            'project_id'  => $project_id,
            'code'        => $code,
            'title'       => $parsed['title'],
            'description' => $parsed['description'],
            'total_cases' => count($parsed['cases']),
            'md_file'     => $filename,
            'sort_order'  => $sort,
        ];

        if ($existing) {
            $wpdb->update($t['modules'], $mod_data, ['id' => $existing]);
            $module_id = $existing;
        } else {
            $wpdb->insert($t['modules'], $mod_data);
            $module_id = $wpdb->insert_id;
        }
        $sort++;

        $case_sort = 0;
        foreach ($parsed['cases'] as $c) {
            $case_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$t['cases']} WHERE module_id = %d AND case_id = %s", $module_id, $c['case_id']
            ));
            $data = [
                'module_id'       => $module_id,
                'case_id'         => $c['case_id'],
                'section'         => $c['section'],
                'title'           => $c['title'],
                'precondition'    => $c['precondition'],
                'steps'           => $c['steps'],
                'expected_result' => $c['expected_result'],
                'priority'        => $c['priority'],
                'sort_order'      => $case_sort,
            ];
            if ($case_exists) {
                $wpdb->update($t['cases'], $data, ['id' => $case_exists]);
            } else {
                $wpdb->insert($t['cases'], $data);
                $imported++;
            }
            $case_sort++;
        }
    }

    // Actualizar total en proyecto
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id = m.id WHERE m.project_id = %d",
        $project_id
    ));
    $wpdb->update($t['projects'], ['total_cases' => $total], ['id' => $project_id]);

    return $imported;
}

// Detectar archivos QA en un directorio
function at_qa_detect_md_files($path) {
    $files = [];
    if (!is_dir($path)) return $files;
    foreach (glob($path . '/QA-*.md') as $f) {
        $basename = basename($f);
        if (preg_match('/^(QA-\d+)/', $basename, $m)) {
            if ($m[1] === 'QA-00' || strpos($basename, 'INFORME') !== false) continue;
            $files[$m[1]] = $basename;
        }
    }
    ksort($files);
    return $files;
}

// ──────────────────────────────────────────────
// 8. AJAX HANDLERS
// ──────────────────────────────────────────────

// Crear/editar proyecto
add_action('wp_ajax_at_qa_save_project', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();

    $id          = intval($_POST['id'] ?? 0);
    $name        = sanitize_text_field($_POST['name'] ?? '');
    $raw_client  = sanitize_text_field($_POST['client_id'] ?? '0');
    $is_internal = ($raw_client === 'internal') ? 1 : 0;
    $client_id   = $is_internal ? 0 : intval($raw_client);
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $qa_status   = sanitize_text_field($_POST['qa_status'] ?? 'pending');
    $version     = sanitize_text_field($_POST['version'] ?? '1.0');
    $environment = sanitize_text_field($_POST['environment'] ?? '');
    $md_base_path = sanitize_text_field($_POST['md_base_path'] ?? '');
    $assigned     = sanitize_text_field($_POST['assigned_testers'] ?? '');

    if (empty($name)) wp_send_json_error('Nombre requerido');

    // Generar slug único — si ya existe, agregar sufijo numérico
    $base_slug = sanitize_title($name);
    $slug = $base_slug;
    $suffix = 2;
    while (true) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['projects']} WHERE slug = %s AND id != %d",
            $slug, $id
        ));
        if (!$existing) break;
        $slug = $base_slug . '-' . $suffix++;
    }

    $data = [
        'name'             => $name,
        'slug'             => $slug,
        'client_id'        => ($is_internal || !$client_id) ? null : $client_id,
        'is_internal'      => $is_internal,
        'description'      => $description,
        'qa_status'        => $qa_status,
        'version'          => $version,
        'environment'      => $environment,
        'md_base_path'     => $md_base_path,
        'assigned_testers' => $assigned,
    ];

    if ($qa_status === 'in_progress') {
        $current = $id ? $wpdb->get_var($wpdb->prepare("SELECT started_at FROM {$t['projects']} WHERE id = %d", $id)) : null;
        if (!$current) {
            $data['started_at'] = current_time('mysql');
        }
    }
    if (in_array($qa_status, ['passed','failed'])) {
        $data['finished_at'] = current_time('mysql');
    }

    if ($id) {
        $result = $wpdb->update($t['projects'], $data, ['id' => $id]);
        if ($result === false) {
            wp_send_json_error('Error BD al actualizar: ' . $wpdb->last_error);
        }
    } else {
        $result = $wpdb->insert($t['projects'], $data);
        if ($result === false) {
            wp_send_json_error('Error BD al insertar: ' . $wpdb->last_error);
        }
        $id = $wpdb->insert_id;
    }

    wp_send_json_success(['id' => $id]);
});

// Eliminar proyecto
add_action('wp_ajax_at_qa_delete_project', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();
    $pid = intval($_POST['project_id']);

    // Obtener módulos del proyecto
    $module_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t['modules']} WHERE project_id = %d", $pid));
    if (!empty($module_ids)) {
        $ph = implode(',', array_fill(0, count($module_ids), '%d'));
        $case_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t['cases']} WHERE module_id IN ($ph)", ...$module_ids));
        if (!empty($case_ids)) {
            $cph = implode(',', array_fill(0, count($case_ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$t['evidence']} WHERE case_id IN ($cph)", ...$case_ids));
            $wpdb->query($wpdb->prepare("DELETE FROM {$t['comments']} WHERE case_id IN ($cph)", ...$case_ids));
            $wpdb->query($wpdb->prepare("DELETE FROM {$t['cases']} WHERE id IN ($cph)", ...$case_ids));
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$t['modules']} WHERE project_id = %d", $pid));
    }
    $wpdb->delete($t['projects'], ['id' => $pid]);

    wp_send_json_success();
});

// Actualizar estado de caso
add_action('wp_ajax_at_qa_update_status', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();

    $case_db_id = intval($_POST['case_db_id']);
    $status     = sanitize_text_field($_POST['status']);
    $valid      = ['not_tested','pass','fail','blocked','skipped'];
    if (!in_array($status, $valid)) wp_send_json_error('Estado inválido');

    // Obtener estado anterior
    $old_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$t['cases']} WHERE id = %d", $case_db_id));

    $user = wp_get_current_user();
    $updated = $wpdb->update($t['cases'], [
        'status'    => $status,
        'tester'    => $user->display_name,
        'tested_at' => $status !== 'not_tested' ? current_time('mysql') : null,
    ], ['id' => $case_db_id]);

    if ($updated === false) {
        wp_send_json_error('Error al actualizar en BD: ' . $wpdb->last_error);
    }

    // ─── Notificaciones por correo al cambiar estado ───
    if ($old_status !== $status && $status !== 'not_tested') {
      try {
        $ctx = at_qa_get_context($case_db_id);
        if ($ctx) {
            $status_labels = [
                'pass'    => ['✅ Aprobado', 'badge-pass'],
                'fail'    => ['❌ Fallido', 'badge-fail'],
                'blocked' => ['⚠️ Bloqueado', 'badge-blocked'],
                'skipped' => ['⏭️ Omitido', 'badge-blocked'],
            ];
            $st_label = $status_labels[$status][0] ?? ucfirst($status);
            $st_class = $status_labels[$status][1] ?? '';
            $old_label = $status_labels[$old_status][0] ?? ($old_status === 'not_tested' ? 'Sin probar' : ucfirst($old_status));

            $project_name = $ctx->project ? $ctx->project->name : 'N/A';
            $module_name = $ctx->caso->module_name;
            $case_name = $ctx->caso->title ?? $ctx->caso->case_id ?? 'Caso #' . $case_db_id;
            $client_name = $ctx->client ? ($ctx->client->empresa ?: $ctx->client->nombre) : '';
            $qa_url = admin_url('admin.php?page=at-qa&view=suite&project=' . ($ctx->project ? $ctx->project->id : ''));

            // Cuerpo del correo
            $body_content = '
      <p>Se ha actualizado el estado de un caso de prueba:</p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
        <p style="margin:0 0 8px;"><strong>🔬 Caso:</strong> ' . esc_html($case_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📊 Estado anterior:</strong> ' . $old_label . '</p>
        <p style="margin:0 0 8px;"><strong>📊 Nuevo estado:</strong> <span class="' . $st_class . '">' . $st_label . '</span></p>
        <p style="margin:0 0 8px;"><strong>👤 Actualizado por:</strong> ' . esc_html($user->display_name) . '</p>
        <p style="margin:0;"><strong>📅 Fecha:</strong> ' . date('d/m/Y H:i') . '</p>
      </div>';

            // Progreso global del proyecto
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d",
                $ctx->project->id
            ));
            $passed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d AND c.status='pass'",
                $ctx->project->id
            ));
            $pct = $total > 0 ? round(($passed / $total) * 100) : 0;
            $body_content .= '
      <div style="margin:15px 0;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Progreso general: <strong>' . $passed . '/' . $total . '</strong> casos aprobados (<strong>' . $pct . '%</strong>)</p>
        <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,#0d9488,#14b8a6);width:' . $pct . '%;height:100%;border-radius:6px;"></div>
        </div>
      </div>';

            $body_content .= '<p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url) . '">🧪 Ver Suite de Pruebas</a>
      </p>';

            // 1) Correo al USUARIO que hizo la acción (confirmación)
            if ($user->user_email) {
                at_qa_send_notification(
                    $user->user_email,
                    '🧪 Confirmación QA: ' . $case_name . ' → ' . $st_label,
                    '🧪 Actualización de Prueba',
                    'Confirmación de tu cambio en caso de prueba',
                    '<p>Hola <strong>' . esc_html($user->display_name) . '</strong>, confirmamos tu actualización:</p>' . $body_content
                );
            }

            // 2) Correo al TESTER asignado (si no es el mismo que hizo el cambio)
            if ($ctx->tester && $ctx->tester->user_email && $ctx->tester->ID !== $user->ID) {
                $tester_body = '<p>Hola <strong>' . esc_html($ctx->tester->display_name) . '</strong>,</p>' . $body_content;
                at_qa_send_notification(
                    $ctx->tester->user_email,
                    '🧪 Actualización QA: ' . $case_name . ' → ' . $st_label,
                    '🧪 Actualización de Prueba',
                    'Cambio en caso de prueba de tu módulo asignado',
                    $tester_body
                );
            }

            // ─── Verificar si el MÓDULO quedó 100% probado ───
            // Nota: se considera exitoso cuando ≥85% de los casos son PASS
            $module_id = $ctx->caso->module_id;
            $mod_total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d", $module_id
            ));
            $mod_tested = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status != 'not_tested'", $module_id
            ));
            $mod_passed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status = 'pass'", $module_id
            ));
            $mod_failed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status = 'fail'", $module_id
            ));
            $mod_blocked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status = 'blocked'", $module_id
            ));

            if ($mod_total > 0 && $mod_tested == $mod_total) {
                $mod_pct_pass = round(($mod_passed / $mod_total) * 100);
                // Veredicto: ≥85% = exitoso
                if ($mod_pct_pass == 100) {
                    $mod_verdict = '✅ 100% Aprobado';
                    $mod_verdict_class = 'badge-pass';
                } elseif ($mod_pct_pass >= 85) {
                    $mod_verdict = '✅ Aprobado con observaciones (' . $mod_pct_pass . '%)';
                    $mod_verdict_class = 'badge-pass';
                } else {
                    $mod_verdict = '❌ No aprobado (' . $mod_pct_pass . '%)';
                    $mod_verdict_class = 'badge-fail';
                }
                $mod_body = '
      <p>🎉 <strong>¡El módulo ha sido completamente probado!</strong></p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📊 Veredicto:</strong> <span class="' . $mod_verdict_class . '">' . $mod_verdict . '</span></p>
        <p style="margin:0 0 8px;"><strong>✅ Casos aprobados:</strong> ' . $mod_passed . '/' . $mod_total . ' (' . $mod_pct_pass . '%)</p>' .
        ($mod_failed > 0 ? '
        <p style="margin:0 0 8px;"><strong>❌ Fallidos:</strong> ' . $mod_failed . '</p>' : '') .
        ($mod_blocked > 0 ? '
        <p style="margin:0 0 8px;"><strong>⚠️ Bloqueados:</strong> ' . $mod_blocked . '</p>' : '') . '
        <p style="margin:0 0 8px;"><strong>👤 Completado por:</strong> ' . esc_html($user->display_name) . '</p>
        <p style="margin:0;"><strong>📅 Fecha:</strong> ' . date('d/m/Y H:i') . '</p>
      </div>
      <p style="font-size:12px;color:#6b7280;margin:10px 0 0;"><em>Nota: Se considera exitoso cuando ≥85% de los casos son aprobados.</em></p>
      <div style="margin:15px 0;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Progreso del módulo: <strong>' . $mod_passed . '/' . $mod_total . '</strong> aprobados (<strong>' . $mod_pct_pass . '%</strong>)</p>
        <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,#0d9488,#14b8a6);width:' . $mod_pct_pass . '%;height:100%;border-radius:6px;"></div>
        </div>
      </div>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url . '&module=' . $module_id) . '">🧪 Ver Módulo Completo</a>
      </p>';

                // Notificar a admin/tester
                $admin_email = get_option('admin_email');
                at_qa_send_notification(
                    $admin_email,
                    '🎉 Módulo 100% Probado: ' . $module_name . ' — ' . $mod_pct_pass . '% aprobado',
                    '🎉 Módulo 100% Probado',
                    $project_name . ' — ' . $module_name,
                    $mod_body
                );
                if ($ctx->tester && $ctx->tester->user_email && $ctx->tester->user_email !== $admin_email) {
                    at_qa_send_notification(
                        $ctx->tester->user_email,
                        '🎉 Módulo 100% Probado: ' . $module_name . ' — ' . $mod_pct_pass . '% aprobado',
                        '🎉 Módulo 100% Probado',
                        $project_name . ' — ' . $module_name,
                        '<p>Hola <strong>' . esc_html($ctx->tester->display_name) . '</strong>,</p>' . $mod_body
                    );
                }

                // Notificar al cliente sobre módulo completo (manual)
                if ($ctx->client && !empty($ctx->client->email)) {
                    $token_mod = md5($ctx->client->id . 'AUTOMATIZA_CRM_V2' . $ctx->client->email);
                    $ficha_url_mod = home_url('/?crm_view=timeline&cid=' . $ctx->client->id . '&token=' . $token_mod);
                    $client_mod_body = '<p>Hola <strong>' . esc_html($ctx->client->nombre) . '</strong>,</p>
      <p>Le informamos que un módulo de pruebas de su proyecto ha sido completamente verificado:</p>' . $mod_body . '
      <p>Puede ver el detalle completo en su ficha de cliente:</p>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($ficha_url_mod) . '">📊 Ver Mi Proyecto</a>
      </p>';
                    at_qa_send_notification(
                        $ctx->client->email,
                        '🎉 Módulo 100% Probado: ' . esc_html($module_name) . ' — ' . $mod_pct_pass . '% aprobado',
                        '🎉 Módulo 100% Probado',
                        esc_html($project_name) . ' — ' . esc_html($module_name),
                        $client_mod_body
                    );
                }
            }

            // ─── Verificar si el PROYECTO quedó 100% completado ───
            if ($pct === 100) {
                $proj_fail = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d AND c.status='fail'",
                    $ctx->project->id
                ));
                $proj_blocked = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d AND c.status='blocked'",
                    $ctx->project->id
                ));
                $proj_body = '
      <p>🏆 <strong>¡El proyecto ha alcanzado el 100% de pruebas aprobadas!</strong></p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>👤 Cliente:</strong> ' . esc_html($client_name) . '</p>
        <p style="margin:0 0 8px;"><strong>✅ Total casos aprobados:</strong> ' . $passed . '/' . $total . '</p>
        <p style="margin:0 0 8px;"><strong>❌ Fallidos:</strong> ' . $proj_fail . '</p>
        <p style="margin:0 0 8px;"><strong>⚠️ Bloqueados:</strong> ' . $proj_blocked . '</p>
        <p style="margin:0 0 8px;"><strong>👤 Última actualización por:</strong> ' . esc_html($user->display_name) . '</p>
        <p style="margin:0;"><strong>📅 Fecha:</strong> ' . date('d/m/Y H:i') . '</p>
      </div>
      <div style="background:#ecfdf5;border:2px solid #10b981;padding:16px;border-radius:8px;text-align:center;margin:15px 0;">
        <span style="font-size:36px;">🏆</span><br>
        <strong style="font-size:18px;color:#065f46;">100% Aprobado</strong><br>
        <p style="color:#047857;margin:8px 0 0;">Todas las pruebas han sido completadas exitosamente. El informe formal puede ser generado.</p>
      </div>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url) . '">📊 Generar Informe QA</a>
      </p>';

                // Notificar a admin
                $admin_email = get_option('admin_email');
                at_qa_send_notification(
                    $admin_email,
                    '🏆 Proyecto 100% Completado: ' . $project_name,
                    '🏆 Proyecto QA 100% Completado',
                    $project_name . ' — Todas las pruebas aprobadas',
                    $proj_body
                );

                // Notificar al cliente
                if ($ctx->client && !empty($ctx->client->email)) {
                    $token_func_proj = function($cid, $email) {
                        return md5($cid . 'AUTOMATIZA_CRM_V2' . $email);
                    };
                    $ficha_url_proj = home_url('/?crm_view=timeline&cid=' . $ctx->client->id . '&token=' . $token_func_proj($ctx->client->id, $ctx->client->email));
                    $client_proj_body = '<p>Hola <strong>' . esc_html($ctx->client->nombre) . '</strong>,</p>
      <p>¡Excelentes noticias! Su proyecto <strong>' . esc_html($project_name) . '</strong> ha completado exitosamente todas las pruebas de calidad.</p>
      <div style="background:#ecfdf5;border:2px solid #10b981;padding:16px;border-radius:8px;text-align:center;margin:15px 0;">
        <span style="font-size:36px;">🏆</span><br>
        <strong style="font-size:18px;color:#065f46;">100% Aprobado</strong><br>
        <p style="color:#047857;margin:8px 0 0;">Todas las ' . $total . ' pruebas han sido completadas exitosamente.</p>
      </div>
      <p>En breve recibirá el informe formal de pruebas QA con todos los detalles.</p>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($ficha_url_proj) . '">📊 Ver Mi Proyecto</a>
      </p>';
                    at_qa_send_notification(
                        $ctx->client->email,
                        '🏆 ¡Su proyecto ' . esc_html($project_name) . ' está 100% aprobado!',
                        '🏆 Proyecto QA Completado',
                        esc_html($client_name) . ' — Pruebas completadas',
                        $client_proj_body
                    );
                }
            }

            // 3) Correo al CLIENTE (si tiene email)
            if ($ctx->client && !empty($ctx->client->email)) {
                $ficha_url = '';
                // Generar link de timeline público si existe el método
                $token_func = function($cid, $email) {
                    return md5($cid . 'AUTOMATIZA_CRM_V2' . $email);
                };
                $ficha_url = home_url('/?crm_view=timeline&cid=' . $ctx->client->id . '&token=' . $token_func($ctx->client->id, $ctx->client->email));

                $client_body = '<p>Hola <strong>' . esc_html($ctx->client->nombre) . '</strong>,</p>
      <p>Le informamos que se ha realizado una actualización en las pruebas de calidad de su proyecto:</p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
        <p style="margin:0 0 8px;"><strong>🔬 Caso:</strong> ' . esc_html($case_name) . '</p>
        <p style="margin:0;"><strong>📊 Resultado:</strong> <span class="' . $st_class . '">' . $st_label . '</span></p>
      </div>
      <div style="margin:15px 0;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Progreso general: <strong>' . $passed . '/' . $total . '</strong> casos aprobados (<strong>' . $pct . '%</strong>)</p>
        <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,#0d9488,#14b8a6);width:' . $pct . '%;height:100%;border-radius:6px;"></div>
        </div>
      </div>
      <p>Puede ver el estado completo de las pruebas en la pestaña <strong>🧪 QA</strong> dentro de su ficha de cliente.</p>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($ficha_url) . '">📊 Ver Mi Proyecto</a>
      </p>';

                at_qa_send_notification(
                    $ctx->client->email,
                    '📊 Actualización QA: ' . esc_html($project_name) . ' — ' . $st_label,
                    '📊 Actualización de Pruebas QA',
                    esc_html($client_name) . ' — Pruebas de calidad',
                    $client_body
                );
            }
        }
      } catch (\Exception $e) {
          error_log('QA Email Error: ' . $e->getMessage());
      } catch (\Throwable $e) {
          error_log('QA Email Error: ' . $e->getMessage());
      }
    }
    // ─── Fin notificaciones ───

    wp_send_json_success(['status' => $status, 'tester' => $user->display_name]);
});

// Actualizar bug_id
add_action('wp_ajax_at_qa_update_bug_id', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();
    $case_db_id = intval($_POST['case_db_id']);
    $bug_id = sanitize_text_field($_POST['bug_id']);
    $wpdb->update($t['cases'], ['bug_id' => $bug_id], ['id' => $case_db_id]);
    wp_send_json_success();
});

// Agregar comentario
add_action('wp_ajax_at_qa_add_comment', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();

    $case_db_id = intval($_POST['case_db_id']);
    $comment    = sanitize_textarea_field($_POST['comment']);
    if (empty($comment)) wp_send_json_error('Comentario vacío');

    $user = wp_get_current_user();
    $wpdb->insert($t['comments'], [
        'case_id' => $case_db_id,
        'user_id' => $user->ID,
        'comment' => $comment,
    ]);

    // ─── Notificación por correo: nuevo comentario ───
    {
      try {
        $ctx = at_qa_get_context($case_db_id);
        if ($ctx) {
            $project_name = $ctx->project ? $ctx->project->name : 'N/A';
            $module_name  = $ctx->caso->module_name;
            $case_name    = $ctx->caso->title ?? $ctx->caso->case_id ?? 'Caso #' . $case_db_id;
            $qa_url       = admin_url('admin.php?page=at-qa&view=suite&project=' . ($ctx->project ? $ctx->project->id : '') . '&module=' . ($ctx->caso->module_id ?? ''));

            $body = '<p><strong>' . esc_html($user->display_name) . '</strong> agregó un comentario en una prueba QA:</p>
            <div class="info-box">
                <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
                <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
                <p style="margin:0 0 8px;"><strong>🔬 Caso:</strong> ' . esc_html($case_name) . '</p>
                <p style="margin:0 0 8px;"><strong>👤 Por:</strong> ' . esc_html($user->display_name) . '</p>
                <p style="margin:0;"><strong>📅 Fecha:</strong> ' . current_time('d/m/Y H:i') . '</p>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:15px 0;">
                <p style="margin:0 0 6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">💬 Comentario</p>
                <p style="margin:0;font-size:15px;line-height:1.5;">' . nl2br(esc_html($comment)) . '</p>
            </div>
            <p style="text-align:center;margin-top:20px;">
                <a class="cta" href="' . esc_url($qa_url) . '">🧪 Ver Caso de Prueba</a>
            </p>';

            // Notificar al USUARIO que comentó (confirmación)
            if ($user->user_email) {
                at_qa_send_notification(
                    $user->user_email,
                    '💬 Confirmación: Tu comentario en ' . $case_name,
                    '💬 Comentario Registrado',
                    esc_html($project_name) . ' — ' . esc_html($module_name),
                    '<p>Hola <strong>' . esc_html($user->display_name) . '</strong>, tu comentario fue registrado:</p>' . $body
                );
            }

            // Notificar al tester asignado si no fue él quien comentó
            if ($ctx->tester && $ctx->tester->user_email && $ctx->tester->ID !== $user->ID) {
                at_qa_send_notification(
                    $ctx->tester->user_email,
                    '💬 Nuevo comentario QA: ' . $case_name,
                    '💬 Nuevo Comentario QA',
                    esc_html($project_name) . ' — ' . esc_html($module_name),
                    '<p>Hola <strong>' . esc_html($ctx->tester->display_name) . '</strong>,</p>' . $body
                );
            }

            // Notificar al admin principal
            $admin_email = get_option('admin_email');
            if ($admin_email && $user->user_email !== $admin_email) {
                at_qa_send_notification(
                    $admin_email,
                    '💬 Nuevo comentario QA: ' . $case_name . ' — ' . $user->display_name,
                    '💬 Nuevo Comentario QA',
                    esc_html($project_name) . ' — ' . esc_html($module_name),
                    $body
                );
            }
        }
      } catch (\Throwable $e) {
          error_log('QA Comment Email Error: ' . $e->getMessage());
      }
    }
    // ─── Fin notificación comentario ───

    wp_send_json_success([
        'id'         => $wpdb->insert_id,
        'user_name'  => $user->display_name,
        'comment'    => $comment,
        'created_at' => current_time('d/m/Y H:i'),
    ]);
});

// Subir evidencia
add_action('wp_ajax_at_qa_upload_evidence', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    $case_db_id  = intval($_POST['case_db_id']);
    $description = sanitize_text_field($_POST['description'] ?? '');

    if (empty($_FILES['evidence_file'])) wp_send_json_error('No se recibió archivo');

    $allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','application/pdf'];
    if (!in_array($_FILES['evidence_file']['type'], $allowed)) {
        wp_send_json_error('Tipo no permitido. Usa: JPG, PNG, GIF, WEBP, MP4, WEBM, PDF');
    }
    if ($_FILES['evidence_file']['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('Archivo muy grande (máx 10 MB)');
    }

    $upload_dir = wp_upload_dir();
    $qa_dir = $upload_dir['basedir'] . '/' . AT_QA_EVIDENCE_DIR;
    $qa_url = $upload_dir['baseurl'] . '/' . AT_QA_EVIDENCE_DIR;
    wp_mkdir_p($qa_dir);

    $ext  = pathinfo($_FILES['evidence_file']['name'], PATHINFO_EXTENSION);
    $safe = 'qa-' . $case_db_id . '-' . time() . '-' . wp_generate_password(6, false) . '.' . $ext;

    if (!move_uploaded_file($_FILES['evidence_file']['tmp_name'], $qa_dir . '/' . $safe)) {
        wp_send_json_error('Error al guardar archivo');
    }

    global $wpdb;
    $t = at_qa_table_names();
    $wpdb->insert($t['evidence'], [
        'case_id'     => $case_db_id,
        'file_url'    => $qa_url . '/' . $safe,
        'file_name'   => sanitize_file_name($_FILES['evidence_file']['name']),
        'file_type'   => $_FILES['evidence_file']['type'],
        'file_size'   => $_FILES['evidence_file']['size'],
        'uploaded_by' => get_current_user_id(),
        'description' => $description,
    ]);

    $evidence_id = $wpdb->insert_id;
    $user = wp_get_current_user();
    $orig_name = $_FILES['evidence_file']['name'];
    $file_size_fmt = size_format($_FILES['evidence_file']['size']);
    $file_type = $_FILES['evidence_file']['type'];
    $evidence_url = $qa_url . '/' . $safe;

    // ─── Notificación por correo: nueva evidencia ───
    {
      try {
        $ctx = at_qa_get_context($case_db_id);
        if ($ctx) {
            $project_name = $ctx->project ? $ctx->project->name : 'N/A';
            $module_name  = $ctx->caso->module_name;
            $case_name    = $ctx->caso->title ?? $ctx->caso->case_id ?? 'Caso #' . $case_db_id;
            $suite_url    = admin_url('admin.php?page=at-qa&view=suite&project=' . ($ctx->project ? $ctx->project->id : '') . '&module=' . ($ctx->caso->module_id ?? ''));

            // Icono según tipo
            $type_icon = '📎';
            if (strpos($file_type, 'image') !== false) $type_icon = '🖼️';
            elseif (strpos($file_type, 'video') !== false) $type_icon = '🎬';
            elseif (strpos($file_type, 'pdf') !== false) $type_icon = '📄';

            $preview_html = '';
            if (strpos($file_type, 'image') !== false) {
                $preview_html = '<div style="text-align:center;margin:15px 0;"><img src="' . esc_url($evidence_url) . '" alt="Evidencia" style="max-width:100%;max-height:300px;border-radius:8px;border:1px solid #e2e8f0;"></div>';
            }

            $body = '<p><strong>' . esc_html($user->display_name) . '</strong> subió una evidencia en una prueba QA:</p>
            <div class="info-box">
                <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
                <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
                <p style="margin:0 0 8px;"><strong>🔬 Caso:</strong> ' . esc_html($case_name) . '</p>
                <p style="margin:0 0 8px;"><strong>👤 Por:</strong> ' . esc_html($user->display_name) . '</p>
                <p style="margin:0;"><strong>📅 Fecha:</strong> ' . current_time('d/m/Y H:i') . '</p>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:15px 0;">
                <p style="margin:0 0 6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">' . $type_icon . ' Evidencia adjunta</p>
                <p style="margin:0 0 4px;"><strong>Archivo:</strong> ' . esc_html($orig_name) . '</p>
                <p style="margin:0 0 4px;"><strong>Tamaño:</strong> ' . esc_html($file_size_fmt) . '</p>
                ' . (!empty($description) ? '<p style="margin:0;"><strong>Descripción:</strong> ' . esc_html($description) . '</p>' : '') . '
            </div>
            ' . $preview_html . '
            <p style="text-align:center;margin-top:20px;">
                <a class="cta" href="' . esc_url($suite_url) . '">🧪 Ver Caso de Prueba</a>
                <a class="cta-secondary" href="' . esc_url($evidence_url) . '">📎 Ver Evidencia</a>
            </p>';

            // Notificar al USUARIO que subió (confirmación)
            if ($user->user_email) {
                at_qa_send_notification(
                    $user->user_email,
                    $type_icon . ' Confirmación: Evidencia subida en ' . $case_name,
                    $type_icon . ' Evidencia Registrada',
                    esc_html($project_name) . ' — ' . esc_html($module_name),
                    '<p>Hola <strong>' . esc_html($user->display_name) . '</strong>, tu evidencia fue registrada:</p>' . $body
                );
            }

            // Notificar al tester asignado si no fue él quien subió
            if ($ctx->tester && $ctx->tester->user_email && $ctx->tester->ID !== $user->ID) {
                at_qa_send_notification(
                    $ctx->tester->user_email,
                    $type_icon . ' Nueva evidencia QA: ' . $case_name,
                    $type_icon . ' Nueva Evidencia QA',
                    esc_html($project_name) . ' — ' . esc_html($module_name),
                    '<p>Hola <strong>' . esc_html($ctx->tester->display_name) . '</strong>,</p>' . $body
                );
            }

            // Notificar al admin principal
            $admin_email = get_option('admin_email');
            if ($admin_email && $user->user_email !== $admin_email) {
                at_qa_send_notification(
                    $admin_email,
                    $type_icon . ' Nueva evidencia QA: ' . $case_name . ' — ' . $user->display_name,
                    $type_icon . ' Nueva Evidencia QA',
                    esc_html($project_name) . ' — ' . esc_html($module_name),
                    $body
                );
            }
        }
      } catch (\Throwable $e) {
          error_log('QA Evidence Email Error: ' . $e->getMessage());
      }
    }
    // ─── Fin notificación evidencia ───

    wp_send_json_success([
        'id'   => $evidence_id,
        'url'  => $evidence_url,
        'name' => $orig_name,
        'type' => $file_type,
        'size' => $file_size_fmt,
        'user' => $user->display_name,
    ]);
});

// Eliminar evidencia
add_action('wp_ajax_at_qa_delete_evidence', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();
    $eid = intval($_POST['evidence_id']);
    $ev  = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['evidence']} WHERE id = %d", $eid));
    if (!$ev) wp_send_json_error('No encontrada');

    $upload_dir = wp_upload_dir();
    $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $ev->file_url);
    if (file_exists($path)) unlink($path);

    $wpdb->delete($t['evidence'], ['id' => $eid]);
    wp_send_json_success();
});

// Editar comentario
add_action('wp_ajax_at_qa_update_comment', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();
    $comment_id = intval($_POST['comment_id']);
    $new_text   = sanitize_textarea_field($_POST['comment']);
    if (empty($new_text)) wp_send_json_error('El comentario no puede estar vacío');

    // Solo el autor o admin puede editar
    $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['comments']} WHERE id = %d", $comment_id));
    if (!$comment) wp_send_json_error('Comentario no encontrado');

    $user = wp_get_current_user();
    if ($comment->user_id != $user->ID && !current_user_can('manage_options')) {
        wp_send_json_error('Solo puedes editar tus propios comentarios');
    }

    $wpdb->update($t['comments'], [
        'comment'    => $new_text,
        'updated_at' => current_time('mysql'),
    ], ['id' => $comment_id]);

    wp_send_json_success([
        'id'         => $comment_id,
        'comment'    => $new_text,
        'user_name'  => $user->display_name,
        'updated_at' => current_time('mysql'),
    ]);
});

// Eliminar comentario
add_action('wp_ajax_at_qa_delete_comment', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();
    $wpdb->delete($t['comments'], ['id' => intval($_POST['comment_id'])]);
    wp_send_json_success();
});

// Detalle de caso (para modal)
add_action('wp_ajax_at_qa_get_case_detail', function() {
    if (!current_user_can('at_qa_access')) wp_die('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');

    global $wpdb;
    $t = at_qa_table_names();
    $cid = intval($_POST['case_db_id']);

    $case = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['cases']} WHERE id = %d", $cid));
    if (!$case) wp_send_json_error('Caso no encontrado');

    $evidence = $wpdb->get_results($wpdb->prepare(
        "SELECT e.*, u.display_name as user_name FROM {$t['evidence']} e LEFT JOIN {$wpdb->users} u ON e.uploaded_by = u.ID WHERE e.case_id = %d ORDER BY e.created_at DESC", $cid
    ));
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name as user_name FROM {$t['comments']} c LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID WHERE c.case_id = %d ORDER BY c.created_at ASC", $cid
    ));

    wp_send_json_success(['case' => $case, 'evidence' => $evidence, 'comments' => $comments]);
});

// Asignar tester a módulo
add_action('wp_ajax_at_qa_assign_module_tester', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');
    global $wpdb;
    $t = at_qa_table_names();
    $module_id = intval($_POST['module_id'] ?? 0);
    $tester_id = intval($_POST['tester_id'] ?? 0);
    if (!$module_id) wp_send_json_error('Módulo inválido');
    $wpdb->update($t['modules'], ['assigned_tester' => $tester_id ?: null], ['id' => $module_id]);
    $tester_name = '';
    $tester_email = '';
    $module_title = '';
    $project_name = '';
    $email_sent = false;

    // Obtener info del módulo y proyecto
    $module = $wpdb->get_row($wpdb->prepare(
        "SELECT m.*, p.name as project_name, p.environment as project_env FROM {$t['modules']} m JOIN {$t['projects']} p ON p.id = m.project_id WHERE m.id = %d", $module_id
    ));
    if ($module) {
        $module_title = $module->title ?? '';
        $project_name = $module->project_name ?? '';
    }

    if ($tester_id) {
        $tester = get_userdata($tester_id);
        $tester_name = $tester ? $tester->display_name : '';
        $tester_email = $tester ? $tester->user_email : '';
        // ─── Enviar correo de asignación al tester ───
        if ($tester && $tester->user_email && $module) {
            $client_name = '';
            $client_empresa = '';
            if ($module && $module->client_id) {
                $client = $wpdb->get_row($wpdb->prepare("SELECT nombre, empresa FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $module->client_id));
                if ($client) {
                    $client_name = $client->nombre;
                    $client_empresa = $client->empresa;
                }
            }
            $admin_url = admin_url();
            $qa_url = admin_url('admin.php?page=at-qa&view=suite&project=' . ($module ? $module->project_id : '') . '&module=' . $module_id);
            $ficha_url = $module && $module->client_id ? admin_url('admin.php?page=automatiza-crm-ficha&id=' . $module->client_id) : '';
            $assigned_by = wp_get_current_user();
            $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';

            $email_body = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Asignación de Módulo QA</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f0f0f0; color: #222; margin:0; padding:0; }
    .container { background: #fff; max-width: 600px; margin: 40px auto; border-radius: 10px; box-shadow: 0 2px 8px #0001; overflow: hidden; }
    .header { background: linear-gradient(135deg, #0d9488, #14b8a6, #2dd4bf); color: #fff; text-align: center; padding: 32px 20px 20px 20px; }
    .header img { max-width: 140px; margin-bottom: 10px; }
    .content { padding: 32px 24px; }
    .info-box { background: #f0fdfa; border-left: 4px solid #0d9488; padding: 15px; margin: 15px 0; border-radius: 4px; }
    .cta { display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #fff !important; padding: 12px 32px; border-radius: 25px; text-decoration: none; font-weight: bold; margin: 10px 5px; }
    .cta-secondary { display: inline-block; background: #e5e7eb; color: #374151 !important; padding: 10px 24px; border-radius: 25px; text-decoration: none; font-weight: 600; margin: 10px 5px; }
    .footer { background: #f8f9fa; color: #6c757d; text-align: center; font-size: 0.9em; padding: 18px 10px; }
    .steps { background: #f9fafb; border-radius: 8px; padding: 16px 20px; margin: 15px 0; }
    .steps li { margin-bottom: 8px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="' . $logo_url . '" alt="AutomatizaTech Logo">
      <h1 style="margin:10px 0 5px;">🧪 Asignación de Módulo QA</h1>
      <p style="margin:0; opacity:0.9;">Se te ha asignado un módulo para realizar pruebas</p>
    </div>
    <div class="content">
      <p>Hola <strong>' . esc_html($tester_name) . '</strong>,</p>
      <p>' . esc_html($assigned_by->display_name) . ' te ha asignado un módulo de pruebas QA. A continuación los detalles:</p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($module ? $module->project_name : 'N/A') . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_title ?: 'N/A') . '</p>' .
        ($client_empresa ? '<p style="margin:0 0 8px;"><strong>🏢 Cliente:</strong> ' . esc_html($client_empresa) . ($client_name ? ' — ' . esc_html($client_name) : '') . '</p>' : '') .
        (!empty($module->project_env) ? '<p style="margin:0 0 8px;"><strong>🌐 Entorno:</strong> <a href="' . esc_url($module->project_env) . '" style="color:#0d9488;">' . esc_html($module->project_env) . '</a></p>' : '') .
        '<p style="margin:0;"><strong>📅 Fecha:</strong> ' . date('d/m/Y H:i') . '</p>
      </div>

      <h3 style="color:#0d9488;">¿Cómo acceder?</h3>
      <div class="steps">
        <ol style="margin:0; padding-left:20px;">
          <li>Ingresa al panel de administración de WordPress</li>
          <li>En el menú lateral, busca <strong>🧪 QA Pruebas</strong></li>
          <li>Selecciona el proyecto <strong>' . esc_html($module ? $module->project_name : '') . '</strong></li>
          <li>Encontrarás tu módulo asignado: <strong>' . esc_html($module_title ?: '') . '</strong></li>
          <li>Para cada caso de prueba: verifica, marca el resultado y sube evidencia</li>
        </ol>
      </div>' .
      (!empty($module->project_env) ? '
      <h3 style="color:#0d9488;">🌐 ¿Dónde probar?</h3>
      <div class="info-box" style="text-align:center;">
        <p style="margin:0 0 12px; font-size:14px;">Entorno de pruebas:</p>
        <p style="margin:0 0 15px;"><strong style="font-size:16px;">' . esc_html($module->project_env) . '</strong></p>
        <a class="cta" href="' . esc_url($module->project_env) . '" style="margin:5px;">🌐 Frontend</a>
        <a class="cta" href="' . esc_url(rtrim($module->project_env, '/') . '/wp-login.php') . '" style="margin:5px; background:linear-gradient(135deg, #1e40af, #3b82f6);">🔧 Backend (wp-admin)</a>
      </div>' : '') . '

      <p style="text-align: center; margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url) . '">🧪 Ir al Módulo QA</a>
      </p>' .
      ($ficha_url ? '<p style="text-align:center;"><a class="cta-secondary" href="' . esc_url($ficha_url) . '">👤 Ver Ficha del Cliente</a></p>' : '') .
      '
      <p style="background:#fef3c7; padding:12px; border-radius:6px; border-left:4px solid #f59e0b; font-size:14px;">
        💡 <strong>Recuerda:</strong> Para ver la información del cliente, puedes acceder a <strong>CRM Clientes → Ficha de Cliente</strong> en el menú lateral.
      </p>
    </div>
    <div class="footer">
      © ' . date('Y') . ' AutomatizaTech. Todos los derechos reservados.<br>
      Correo enviado automáticamente — <a href="https://automatizatech.cl/" style="color:#0d9488;">automatizatech.cl</a>
    </div>
  </div>
</body>
</html>';

            $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Automatiza Tech <' . $from_email . '>',
                'Bcc: lgonzalez@automatizatech.cl',
                'Bcc: anamaria.sandoval@automatizatech.cl',
                'Bcc: automatizacionesbotcore@gmail.com',
            ];
            try {
                $email_sent = @wp_mail($tester->user_email, '🧪 Se te asignó un módulo QA: ' . $module_title, $email_body, $headers);
            } catch (\Throwable $e) {
                $email_sent = false;
            }
        }
        // ─── Fin correo ───
    }
    wp_send_json_success([
        'tester_name'  => $tester_name,
        'tester_email' => $tester_email,
        'module_name'  => $module_title,
        'project_name' => $project_name,
        'email_sent'   => $email_sent,
        'assigned_by'  => wp_get_current_user()->display_name,
        'date'         => date('d/m/Y H:i'),
    ]);
});

// Generar informe QA
add_action('wp_ajax_at_qa_generate_report', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');
    global $wpdb;
    $t = at_qa_table_names();
    $project_id = intval($_POST['project_id'] ?? 0);
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['projects']} WHERE id=%d", $project_id));
    if (!$project) wp_send_json_error('Proyecto no encontrado');

    // Datos del cliente
    $client = null;
    if (!empty($project->is_internal)) {
        // Proyecto interno AT — se representa como cliente ficticio
        $client = (object) [
            'nombre'   => 'Automatiza Tech',
            'empresa'  => 'Proyecto Interno',
            'email'    => 'contacto@automatizatech.cl',
            'telefono' => '+56 9 2700 2984',
        ];
    } elseif ($project->client_id) {
        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_clientes WHERE id=%d", $project->client_id));
    }

    // Módulos y casos
    $modules = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['modules']} WHERE project_id=%d ORDER BY sort_order", $project_id));
    $all_cases = [];
    $global_stats = ['total'=>0,'pass'=>0,'fail'=>0,'blocked'=>0,'skipped'=>0,'not_tested'=>0];
    foreach ($modules as $m) {
        $cases = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['cases']} WHERE module_id=%d ORDER BY sort_order", $m->id));
        $m_stats = ['total'=>0,'pass'=>0,'fail'=>0,'blocked'=>0,'skipped'=>0,'not_tested'=>0];
        foreach ($cases as $c) {
            $m_stats[$c->status]++;
            $m_stats['total']++;
            $global_stats[$c->status]++;
            $global_stats['total']++;
        }
        $tester_name = $m->assigned_tester ? get_userdata($m->assigned_tester)->display_name : 'No asignado';
        $all_cases[] = ['module' => $m, 'cases' => $cases, 'stats' => $m_stats, 'tester' => $tester_name];
    }

    $pass_rate = $global_stats['total'] > 0 ? round(($global_stats['pass']/$global_stats['total'])*100, 1) : 0;
    $verdict = $pass_rate >= 95 ? 'APROBADO' : ($pass_rate >= 70 ? 'APROBADO CON OBSERVACIONES' : 'RECHAZADO');

    // Generar HTML del informe
    $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
    $date_now = wp_date('d \d\e F \d\e Y', null, new DateTimeZone('America/Santiago'));

    ob_start();
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe QA — <?php echo esc_html($project->name); ?></title>
<style>
    @page { margin: 30px 40px; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; color: #333; font-size: 13px; line-height: 1.5; margin: 0; padding: 0; }
    .report-header { background: linear-gradient(135deg, #0d9488, #14b8a6, #2dd4bf); color: #fff; padding: 35px 40px; text-align: center; }
    .report-header img { max-height: 55px; margin-bottom: 10px; }
    .report-header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; }
    .report-header p { margin: 5px 0 0; opacity: .85; font-size: 13px; }
    .report-body { padding: 30px 40px; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
    .info-box { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 15px; }
    .info-box h4 { margin: 0 0 8px; color: #0d9488; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
    .info-box p { margin: 3px 0; font-size: 13px; }
    .verdict-box { text-align: center; padding: 20px; border-radius: 10px; margin: 20px 0 25px; font-size: 22px; font-weight: 700; letter-spacing: 1px; }
    .verdict-aprobado { background: #ecfdf5; color: #065f46; border: 2px solid #10b981; }
    .verdict-observaciones { background: #fefce8; color: #713f12; border: 2px solid #eab308; }
    .verdict-rechazado { background: #fef2f2; color: #991b1b; border: 2px solid #ef4444; }
    .stats-row { display: flex; gap: 10px; justify-content: center; margin-bottom: 25px; flex-wrap: wrap; }
    .stat-box { padding: 10px 18px; border-radius: 8px; text-align: center; min-width: 80px; }
    .stat-box .num { font-size: 24px; font-weight: 700; }
    .stat-box .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
    .sb-total { background: #f0fdfa; color: #0d9488; }
    .sb-pass { background: #ecfdf5; color: #065f46; }
    .sb-fail { background: #fef2f2; color: #991b1b; }
    .sb-blocked { background: #fffbeb; color: #92400e; }
    .sb-skipped { background: #f5f3ff; color: #5b21b6; }
    .sb-untested { background: #f3f4f6; color: #6b7280; }
    .module-section { margin-bottom: 20px; page-break-inside: avoid; }
    .module-title { background: #f0fdfa; padding: 10px 15px; border-left: 4px solid #0d9488; border-radius: 0 8px 8px 0; margin-bottom: 10px; }
    .module-title h3 { margin: 0; font-size: 14px; color: #0f766e; }
    .module-title .mod-meta { font-size: 11px; color: #666; margin-top: 3px; }
    table.qa-report { width: 100%; border-collapse: collapse; font-size: 12px; }
    table.qa-report th { background: #f8fafc; padding: 7px 10px; text-align: left; font-size: 10px; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e7eb; }
    table.qa-report td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; }
    .st-pass { color: #065f46; font-weight: 600; }
    .st-fail { color: #991b1b; font-weight: 600; }
    .st-blocked { color: #92400e; font-weight: 600; }
    .st-skipped { color: #5b21b6; }
    .st-not_tested { color: #9ca3af; }
    .progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 5px; }
    .progress-bar .fill { height: 100%; }
    .report-footer { text-align: center; padding: 20px 40px; font-size: 11px; color: #999; border-top: 1px solid #e5e7eb; margin-top: 30px; }
    .report-footer a { color: #0d9488; text-decoration: none; }
    @media print { .no-print { display: none; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="report-header">
    <img src="<?php echo esc_url($logo_url); ?>" alt="Automatiza Tech">
    <h1>Informe de Pruebas QA</h1>
    <p><?php echo esc_html($project->name); ?> — <?php echo $date_now; ?></p>
</div>
<div class="report-body">
    <div class="info-grid">
        <div class="info-box">
            <h4>📋 Proyecto</h4>
            <p><strong><?php echo esc_html($project->name); ?></strong></p>
            <p>Versión: <?php echo esc_html($project->version ?: '1.0'); ?></p>
            <p>Fecha inicio: <?php echo $project->started_at ? wp_date('d/m/Y', strtotime($project->started_at)) : 'N/A'; ?></p>
            <p>Fecha cierre: <?php echo $date_now; ?></p>
        </div>
        <div class="info-box">
            <h4>👤 Cliente</h4>
            <?php if ($client): ?>
            <p><strong><?php echo esc_html($client->nombre); ?></strong></p>
            <p><?php echo esc_html($client->empresa); ?></p>
            <p>📧 <?php echo esc_html($client->email); ?></p>
            <p>📱 <?php echo esc_html($client->telefono); ?></p>
            <?php elseif (!empty($project->is_internal)): ?>
            <p><strong>Automatiza Tech</strong></p>
            <p>Proyecto Interno</p>
            <?php else: ?>
            <p><em>Sin cliente vinculado</em></p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $verdict_class = $pass_rate >= 95 ? 'verdict-aprobado' : ($pass_rate >= 70 ? 'verdict-observaciones' : 'verdict-rechazado');
    ?>
    <div class="verdict-box <?php echo $verdict_class; ?>">
        <?php echo $pass_rate >= 95 ? '✅' : ($pass_rate >= 70 ? '⚠️' : '❌'); ?>
        <?php echo $verdict; ?> — <?php echo $pass_rate; ?>% Pass Rate
    </div>

    <div class="stats-row">
        <div class="stat-box sb-total"><div class="num"><?php echo $global_stats['total']; ?></div><div class="lbl">Total</div></div>
        <div class="stat-box sb-pass"><div class="num"><?php echo $global_stats['pass']; ?></div><div class="lbl">Pass</div></div>
        <div class="stat-box sb-fail"><div class="num"><?php echo $global_stats['fail']; ?></div><div class="lbl">Fail</div></div>
        <div class="stat-box sb-blocked"><div class="num"><?php echo $global_stats['blocked']; ?></div><div class="lbl">Bloqueados</div></div>
        <div class="stat-box sb-skipped"><div class="num"><?php echo $global_stats['skipped']; ?></div><div class="lbl">Omitidos</div></div>
        <div class="stat-box sb-untested"><div class="num"><?php echo $global_stats['not_tested']; ?></div><div class="lbl">Sin probar</div></div>
    </div>

    <h2 style="color:#0f766e; font-size:16px; border-bottom:2px solid #99f6e4; padding-bottom:6px;">📊 Detalle por Módulo</h2>

    <?php foreach ($all_cases as $mc):
        $m = $mc['module'];
        $m_rate = $mc['stats']['total'] > 0 ? round(($mc['stats']['pass']/$mc['stats']['total'])*100,1) : 0;
    ?>
    <div class="module-section">
        <div class="module-title">
            <h3><?php echo esc_html($m->title); ?></h3>
            <div class="mod-meta">
                Tester: <?php echo esc_html($mc['tester']); ?> ·
                <?php echo $mc['stats']['total']; ?> casos ·
                ✅ <?php echo $mc['stats']['pass']; ?> ❌ <?php echo $mc['stats']['fail']; ?> ⚠️ <?php echo $mc['stats']['blocked']; ?> ·
                <?php echo $m_rate; ?>%
            </div>
            <div class="progress-bar">
                <div style="display:flex; width:100%; height:100%;">
                    <div class="fill" style="width:<?php echo $mc['stats']['total']>0?($mc['stats']['pass']/$mc['stats']['total'])*100:0; ?>%;background:#10b981"></div>
                    <div class="fill" style="width:<?php echo $mc['stats']['total']>0?($mc['stats']['fail']/$mc['stats']['total'])*100:0; ?>%;background:#ef4444"></div>
                    <div class="fill" style="width:<?php echo $mc['stats']['total']>0?($mc['stats']['blocked']/$mc['stats']['total'])*100:0; ?>%;background:#f59e0b"></div>
                </div>
            </div>
        </div>
        <table class="qa-report">
            <thead><tr><th>ID</th><th>Caso</th><th>Prioridad</th><th>Estado</th><th>Bug ID</th><th>Tester</th></tr></thead>
            <tbody>
            <?php foreach ($mc['cases'] as $c): ?>
            <tr>
                <td><strong><?php echo esc_html($c->case_id); ?></strong></td>
                <td><?php echo esc_html($c->title); ?></td>
                <td><?php echo esc_html($c->priority); ?></td>
                <td class="st-<?php echo $c->status; ?>">
                    <?php echo ['pass'=>'✅ PASS','fail'=>'❌ FAIL','blocked'=>'⚠️ BLOQ.','skipped'=>'⏭️ OMIT.','not_tested'=>'🔘 Sin probar'][$c->status] ?? $c->status; ?>
                </td>
                <td><?php echo esc_html($c->bug_id); ?></td>
                <td><?php echo esc_html($c->tester); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

</div>
<div class="report-footer">
    <p>Generado por <strong>Automatiza Tech</strong> · <a href="https://automatizatech.cl">automatizatech.cl</a></p>
    <p>📧 contacto@automatizatech.cl · 📱 +56 9 2700 2984</p>
    <p>© <?php echo date('Y'); ?> Automatiza Tech. Todos los derechos reservados.</p>
</div>
</body>
</html><?php
    $html = ob_get_clean();

    // Guardar informe
    $upload_dir = wp_upload_dir();
    $reports_dir = $upload_dir['basedir'] . '/qa-reports';
    if (!is_dir($reports_dir)) wp_mkdir_p($reports_dir);

    // Versionado: timestamp evita sobrescritura cuando hay varias versiones el mismo día
    $stamp = date('Y-m-d_His');
    $base_name = 'QA-Report-' . sanitize_file_name($project->name) . '-' . $stamp;

    $filename = $base_name . '.html';
    $filepath = $reports_dir . '/' . $filename;
    file_put_contents($filepath, $html);
    $file_url = $upload_dir['baseurl'] . '/qa-reports/' . $filename;

    // Generar PDF con FPDF (mismo motor que cotizaciones/contratos)
    $pdf_url = '';
    $pdf_filename = '';
    try {
        require_once get_template_directory() . '/lib/qa-report-pdf-fpdf.php';
        $pdf = new QAReportPDF($project, $client, $all_cases, $global_stats, $verdict, $pass_rate, date('d-m-Y'));
        $pdf->build();
        $pdf_filename = $base_name . '.pdf';
        $pdf_path = $reports_dir . '/' . $pdf_filename;
        $pdf->Output('F', $pdf_path);
        $pdf_url = $upload_dir['baseurl'] . '/qa-reports/' . $pdf_filename;
    } catch (\Throwable $e) {
        error_log('[QA] Error generando PDF: ' . $e->getMessage());
    }

    // Marcar proyecto como finalizado y registrar última versión
    $update_data = [
        'qa_status'   => $pass_rate >= 70 ? 'passed' : 'failed',
        'finished_at' => current_time('mysql'),
    ];
    // Campos opcionales (si la columna existe, se actualiza; ignorar errores silenciosos)
    @$wpdb->update($t['projects'], $update_data, ['id' => $project_id]);
    @$wpdb->query($wpdb->prepare(
        "UPDATE {$t['projects']} SET last_report_at = %s, last_report_pdf = %s WHERE id = %d",
        current_time('mysql'), $pdf_filename, $project_id
    ));

    wp_send_json_success([
        'url'          => $file_url,
        'pdf_url'      => $pdf_url,
        'pdf_filename' => $pdf_filename,
        'verdict'      => $verdict,
        'pass_rate'    => $pass_rate,
        'filename'     => $filename,
    ]);
});

// ─────────────────────────────────────────────────────────────────────
// Enviar informe QA por correo al cliente (con PDF adjunto + BCC admin)
// ─────────────────────────────────────────────────────────────────────
add_action('wp_ajax_at_qa_send_report_email', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');
    global $wpdb;
    $t = at_qa_table_names();
    $project_id   = intval($_POST['project_id'] ?? 0);
    $pdf_filename = sanitize_file_name($_POST['pdf_filename'] ?? '');
    $to_override  = sanitize_email($_POST['to_email'] ?? '');
    $custom_msg   = wp_kses_post($_POST['custom_message'] ?? '');

    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['projects']} WHERE id=%d", $project_id));
    if (!$project) wp_send_json_error('Proyecto no encontrado');
    if (!$pdf_filename) wp_send_json_error('Falta PDF — genera el informe primero');

    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/qa-reports/' . $pdf_filename;
    if (!file_exists($pdf_path)) wp_send_json_error('PDF no encontrado en servidor');

    // Resolver destinatario
    $to_email = $to_override;
    $client_name = '';
    $cli = null; // inicializar para evitar undefined variable en PHP 8
    if (!$to_email && $project->client_id) {
        $cli = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_clientes WHERE id=%d", $project->client_id));
        if ($cli) { $to_email = $cli->email; $client_name = $cli->nombre; }
    }
    // Si se pasó to_override pero no hay $cli, intentar cargar el cliente de igual forma
    if ($cli === null && $project->client_id) {
        $cli = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_clientes WHERE id=%d", $project->client_id));
        if ($cli && empty($client_name)) $client_name = $cli->nombre;
    }
    if (!$to_email) wp_send_json_error('No hay email destino. Pasa to_email o vincula cliente al proyecto.');

    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';

    // Construir adjunto igual que receipts-module.php
    $attachments = [];
    if (file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    // Stats para el cuerpo del email
    $modules = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['modules']} WHERE project_id=%d", $project_id));
    $g = ['total'=>0,'pass'=>0,'fail'=>0,'blocked'=>0,'skipped'=>0,'not_tested'=>0];
    foreach ($modules as $m) {
        $cases = $wpdb->get_results($wpdb->prepare("SELECT status FROM {$t['cases']} WHERE module_id=%d", $m->id));
        foreach ($cases as $c) { $g[$c->status]++; $g['total']++; }
    }
    $pass_rate = $g['total'] > 0 ? round(($g['pass']/$g['total'])*100, 1) : 0;
    $verdict = $pass_rate >= 95 ? 'APROBADO' : ($pass_rate >= 70 ? 'APROBADO CON OBSERVACIONES' : 'RECHAZADO');
    $verdict_color = $pass_rate >= 95 ? '#10b981' : ($pass_rate >= 70 ? '#eab308' : '#ef4444');

    $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
    $project_name = esc_html($project->name);
    // Asunto sin "%" para evitar filtros anti-spam (SpamAssassin penaliza % en subjects)
    $subject = 'Informe de Pruebas QA: ' . $project->name;

    // URL personalizada de la ficha del cliente con token de acceso
    $ficha_url = home_url('/');
    if (!empty($project->client_id) && !empty($cli)) {
        $client_token = md5($cli->id . 'AUTOMATIZA_CRM_V2' . $cli->email);
        $ficha_url = home_url('/?crm_view=timeline&cid=' . $cli->id . '&token=' . $client_token);
    }

    // URL de descarga directa del PDF (siempre incluida en el cuerpo)
    $pdf_download_url = '';
    if ($pdf_filename) {
        $upload_dir = wp_upload_dir();
        $pdf_download_url = $upload_dir['baseurl'] . '/qa-reports/' . $pdf_filename;
    }

    ob_start(); ?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title><?php echo $subject; ?></title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Tahoma,sans-serif;color:#333;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:30px 0;">
    <tr><td align="center">
      <table width="620" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08);">
        <tr><td style="background:linear-gradient(135deg,#0d9488,#14b8a6,#2dd4bf);padding:30px 40px;text-align:center;color:#fff;">
          <img src="<?php echo esc_url($logo_url); ?>" alt="AutomatizaTech" style="max-height:50px;margin-bottom:10px;">
          <h1 style="margin:0;font-size:22px;letter-spacing:1px;">Informe de Pruebas QA</h1>
          <p style="margin:6px 0 0;opacity:.9;font-size:13px;"><?php echo $project_name; ?></p>
        </td></tr>
        <tr><td style="padding:30px 40px;">
          <p style="margin:0 0 12px;font-size:15px;">Hola <strong><?php echo esc_html($client_name ?: 'estimado/a cliente'); ?></strong>,</p>
          <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
            Adjuntamos el <strong>Informe Final de Pruebas QA</strong> correspondiente a tu proyecto
            <strong><?php echo $project_name; ?></strong>. Este documento detalla el resultado de cada caso de prueba
            ejecutado por nuestro equipo de calidad.
          </p>
          <?php if ($custom_msg): ?>
          <div style="background:#f0fdfa;border-left:4px solid #0d9488;padding:12px 16px;margin:16px 0;border-radius:4px;font-size:13.5px;">
            <?php echo $custom_msg; ?>
          </div>
          <?php endif; ?>
          <div style="background:<?php echo $verdict_color; ?>;color:#fff;text-align:center;padding:18px;border-radius:8px;margin:20px 0;">
            <div style="font-size:13px;opacity:.9;letter-spacing:1px;">RESULTADO</div>
            <div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo $verdict; ?></div>
            <div style="font-size:18px;margin-top:6px;"><?php echo $pass_rate; ?>% Pass Rate</div>
          </div>
          <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin:16px 0;">
            <tr style="background:#f0fdfa;color:#0d9488;font-weight:600;text-align:center;">
              <td style="border-radius:6px 0 0 6px;">Total</td><td>Pass</td><td>Fail</td><td>Bloq.</td><td>Omit.</td><td style="border-radius:0 6px 6px 0;">Sin probar</td>
            </tr>
            <tr style="text-align:center;font-size:18px;font-weight:700;">
              <td style="color:#0d9488;"><?php echo $g['total']; ?></td>
              <td style="color:#065f46;"><?php echo $g['pass']; ?></td>
              <td style="color:#991b1b;"><?php echo $g['fail']; ?></td>
              <td style="color:#92400e;"><?php echo $g['blocked']; ?></td>
              <td style="color:#5b21b6;"><?php echo $g['skipped']; ?></td>
              <td style="color:#6b7280;"><?php echo $g['not_tested']; ?></td>
            </tr>
          </table>
          <p style="margin:16px 0;font-size:13.5px;line-height:1.6;color:#555;">
            Encontrarás el detalle completo en el PDF adjunto. Si tienes dudas o comentarios sobre los resultados,
            no dudes en responder a este correo.
          </p>
          <div style="text-align:center;margin:24px 0;">
            <a href="<?php echo esc_url($ficha_url); ?>" style="background:#0d9488;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;display:inline-block;margin:6px;">Ver evidencias en mi portal →</a>
            <?php if ($pdf_download_url): ?>
            <a href="<?php echo esc_url($pdf_download_url); ?>" style="background:#1e40af;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;display:inline-block;margin:6px;">Descargar informe PDF ↓</a>
            <?php endif; ?>
          </div>
        </td></tr>
        <tr><td style="background:#f8fafc;padding:20px 40px;text-align:center;font-size:11px;color:#888;border-top:1px solid #e5e7eb;">
          <p style="margin:0 0 4px;"><strong>AutomatizaTech SpA</strong> &middot; RUT 78.363.717-0</p>
          <p style="margin:0 0 4px;">contacto@automatizatech.cl &middot; +56 9 2700 2984</p>
          <p style="margin:0 0 4px;">Santa Beatriz 170, Of. 903 (9P), Providencia, Santiago</p>
          <p style="margin:8px 0 0;color:#aaa;">&copy; <?php echo date('Y'); ?> AutomatizaTech. Todos los derechos reservados.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
<?php
    $html_body = ob_get_clean();

    // Patrón idéntico a proposals (que funciona con PDF + BCC + gmail)
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $headers[] = 'From: Automatiza Tech <' . $from_email . '>';
    $headers[] = 'Reply-To: ' . $from_email;
    $headers[] = 'Bcc: lgonzalez@automatizatech.cl';

    // AltBody en texto plano → evita penalización SpamAssassin MIME_HTML_ONLY
    $plain_name   = $client_name ?: 'estimado/a cliente';
    $plain_verdict = $verdict . ' - ' . $pass_rate . '% de casos aprobados';
    $at_qa_altbody = implode("\n", [
        "Informe de Pruebas QA: {$project->name}",
        str_repeat('-', 50),
        "Hola {$plain_name},",
        "",
        "Adjuntamos el Informe Final de Pruebas QA de tu proyecto {$project->name}.",
        "",
        "RESULTADO: {$plain_verdict}",
        "Total: {$g['total']} | Pass: {$g['pass']} | Fail: {$g['fail']} | Bloqueados: {$g['blocked']}",
        "",
        "Ver portal: " . $ficha_url,
        ($pdf_download_url ? "Descargar PDF: " . $pdf_download_url : ""),
        "",
        "AutomatizaTech SpA | contacto@automatizatech.cl | +56 9 2700 2984",
    ]);

    // Hook puntual para inyectar AltBody en el próximo wp_mail (reset automático)
    $altbody_hook = function($pm) use ($at_qa_altbody, &$altbody_hook) {
        if (empty($pm->AltBody)) {
            $pm->AltBody = $at_qa_altbody;
        }
        remove_action('phpmailer_init', $altbody_hook, 20);
    };
    add_action('phpmailer_init', $altbody_hook, 20);

    // Enviar con PDF adjunto al cliente (mismo patrón que receipts-module.php)
    $sent = wp_mail($to_email, $subject, $html_body, $headers, $attachments);
    if (!$sent) {
        remove_action('phpmailer_init', $altbody_hook, 20); // limpiar si no se ejecutó
        global $phpmailer;
        $detail = (isset($phpmailer) && isset($phpmailer->ErrorInfo) && $phpmailer->ErrorInfo)
            ? $phpmailer->ErrorInfo
            : 'Sin detalle — revisa error_log del servidor';
        error_log('[QA-EMAIL-FAIL] To:' . $to_email . ' | Subject:' . $subject . ' | Error:' . $detail);
        wp_send_json_error('Error enviando correo: ' . $detail);
    }

    @$wpdb->query($wpdb->prepare(
        "UPDATE {$t['projects']} SET last_report_sent_at = %s WHERE id = %d",
        current_time('mysql'), $project_id
    ));

    wp_send_json_success([
        'to'      => $to_email,
        'subject' => $subject,
    ]);
});

// ──────────────────────────────────────────────
// 8b. GENERAR INFORME DE ERRORES QA (.md)
// ──────────────────────────────────────────────
add_action('wp_ajax_at_qa_generate_error_report', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
    check_ajax_referer('at_qa_nonce', 'nonce');
    global $wpdb;
    $t = at_qa_table_names();
    $project_id = intval($_POST['project_id'] ?? 0);
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['projects']} WHERE id=%d", $project_id));
    if (!$project) wp_send_json_error('Proyecto no encontrado');

    $modules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t['modules']} WHERE project_id=%d ORDER BY sort_order", $project_id
    ));

    $global_stats = ['total' => 0, 'pass' => 0, 'fail' => 0, 'blocked' => 0, 'skipped' => 0, 'not_tested' => 0];
    $error_sections = [];

    foreach ($modules as $m) {
        $all_cases = $wpdb->get_results($wpdb->prepare(
            "SELECT status FROM {$t['cases']} WHERE module_id=%d", $m->id
        ));
        foreach ($all_cases as $row) {
            $global_stats[$row->status]++;
            $global_stats['total']++;
        }

        $failed_cases = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t['cases']} WHERE module_id=%d AND status IN ('fail','blocked') ORDER BY sort_order",
            $m->id
        ));
        if (empty($failed_cases)) continue;

        $tester_name = $m->assigned_tester ? (get_userdata($m->assigned_tester)->display_name ?? 'No asignado') : 'No asignado';
        $cases_detail = [];
        foreach ($failed_cases as $c) {
            $comments = $wpdb->get_results($wpdb->prepare(
                "SELECT cm.comment, cm.created_at, u.display_name
                 FROM {$t['comments']} cm
                 LEFT JOIN {$wpdb->users} u ON u.ID = cm.user_id
                 WHERE cm.case_id = %d ORDER BY cm.created_at",
                $c->id
            ));
            $evidences = $wpdb->get_results($wpdb->prepare(
                "SELECT file_name, file_url, description FROM {$t['evidence']} WHERE case_id=%d ORDER BY created_at",
                $c->id
            ));
            $cases_detail[] = ['case' => $c, 'comments' => $comments, 'evidences' => $evidences];
        }
        $error_sections[] = ['module' => $m, 'tester' => $tester_name, 'cases' => $cases_detail];
    }

    $pass_rate    = $global_stats['total'] > 0 ? round(($global_stats['pass'] / $global_stats['total']) * 100, 1) : 0;
    $total_errors = $global_stats['fail'] + $global_stats['blocked'];
    $tz           = new DateTimeZone('America/Santiago');
    $date_now     = wp_date('d/m/Y H:i', null, $tz);

    // ── Helpers de análisis ────────────────────
    // Determina nivel de severidad en base a prioridad + estado
    $get_severity = function(string $priority, string $status): string {
        if ($status === 'blocked') return '🔴 CRÍTICO';
        return match (strtolower($priority)) {
            'alta'  => '🔴 ALTO',
            'media' => '🟡 MEDIO',
            default => '🟢 BAJO',
        };
    };

    // Genera un análisis de impacto orientado al dev a partir del título, módulo y comentarios
    $get_impact_analysis = function(object $c, string $module_title, array $comments): string {
        $title_lower = strtolower($c->title);
        $notes       = implode(' ', array_column($comments, 'comment'));
        $notes_lower = strtolower($notes);

        if ($c->status === 'blocked') {
            return 'Este caso quedó **bloqueado** durante la ejecución, lo que impidió completar la prueba. '
                 . 'Es necesario resolver el bloqueo antes de poder validar el comportamiento esperado. '
                 . 'Revisar dependencias o condiciones previas del entorno.';
        }

        // Patrones comunes para generar análisis contextual
        $patterns = [
            ['keywords' => ['login','autenticac','session','token','acceso'],
             'impact'   => 'Afecta directamente el flujo de autenticación. Un fallo aquí bloquea el acceso de usuarios al sistema completo.'],
            ['keywords' => ['registro','signup','crear cuenta','nuevo usuario'],
             'impact'   => 'El proceso de onboarding de usuarios está comprometido. Usuarios nuevos no pueden completar el registro correctamente.'],
            ['keywords' => ['pago','checkout','carrito','orden','pedido','compra'],
             'impact'   => 'Fallo crítico en el flujo de conversión. Impacta directamente en transacciones y revenue del negocio.'],
            ['keywords' => ['correo','email','notificac','notificación'],
             'impact'   => 'Las notificaciones automáticas no están funcionando. Usuarios no reciben comunicaciones del sistema.'],
            ['keywords' => ['permiso','rol','admin','acceso','autorización'],
             'impact'   => 'Problema de control de acceso. Puede exponer funciones restringidas o denegar acceso legítimo.'],
            ['keywords' => ['responsive','móvil','mobile','pantalla','dispositivo'],
             'impact'   => 'La interfaz no se adapta correctamente en dispositivos móviles. Afecta la experiencia del usuario en pantallas pequeñas.'],
            ['keywords' => ['upload','subir','imagen','archivo','adjunto'],
             'impact'   => 'La carga de archivos o imágenes falla. Funcionalidades que dependen de media adjunta no operan correctamente.'],
            ['keywords' => ['búsqueda','buscar','filtro','filtrar','resultado'],
             'impact'   => 'El sistema de búsqueda o filtros devuelve resultados incorrectos o no responde. Dificulta la navegación y localización de contenido.'],
            ['keywords' => ['formulario','form','campo','validación','input'],
             'impact'   => 'El formulario no valida correctamente los datos ingresados. Puede permitir datos incorrectos o bloquear datos válidos.'],
            ['keywords' => ['base de datos','query','sql','dato','registro'],
             'impact'   => 'Posible inconsistencia en la capa de datos. Revisar queries, migraciones o integridad referencial.'],
        ];

        foreach ($patterns as $pattern) {
            foreach ($pattern['keywords'] as $kw) {
                if (strpos($title_lower, $kw) !== false || strpos($notes_lower, $kw) !== false) {
                    return $pattern['impact'];
                }
            }
        }

        return 'El comportamiento observado no coincide con el esperado para el módulo **' . $module_title . '**. '
             . 'Se requiere revisión del flujo completo del caso de uso y su implementación.';
    };

    // Extrae el resultado actual (lo que realmente pasó) de los comentarios del tester
    $get_actual_result = function(array $comments): string {
        foreach ($comments as $cm) {
            $text  = trim($cm->comment);
            $lower = strtolower($text);
            // Buscar comentarios que describan lo que pasó
            if (strpos($lower, 'resultado') !== false
                || strpos($lower, 'error') !== false
                || strpos($lower, 'falla') !== false
                || strpos($lower, 'muestra') !== false
                || strpos($lower, 'aparece') !== false
                || strpos($lower, 'ocurre') !== false
                || strpos($lower, 'sucede') !== false
                || strpos($lower, 'observ') !== false
                || strpos($lower, 'se ve') !== false
                || strpos($lower, 'no funciona') !== false
                || strpos($lower, 'no carga') !== false
                || strpos($lower, 'no responde') !== false) {
                return $text;
            }
        }
        // Si no hay comentario descriptivo, usar el primero disponible
        return !empty($comments) ? trim($comments[0]->comment) : '';
    };

    // ── Construir Markdown ──────────────────────
    $L = [];

    // Encabezado del documento
    $verdict_text = $pass_rate >= 95 ? '✅ APROBADO' : ($pass_rate >= 70 ? '⚠️ APROBADO CON OBSERVACIONES' : '❌ RECHAZADO');
    $L[] = '# Reporte de Defectos QA — ' . $project->name;
    $L[] = '';
    $L[] = '> **Documento dirigido a:** Equipo de Desarrollo';
    $L[] = '> **Elaborado por:** Área QA — Automatiza Tech';
    $L[] = '> **Fecha:** ' . $date_now . ' (hora Chile)';
    $L[] = '> **Versión evaluada:** ' . ($project->version ?: '1.0');
    $L[] = '> **Veredicto:** ' . $verdict_text;
    $L[] = '';
    $L[] = '---';
    $L[] = '';

    // Introducción narrativa
    $L[] = '## Introducción';
    $L[] = '';
    if ($total_errors === 0) {
        $L[] = 'Se completó el ciclo de pruebas funcionales sobre el proyecto **' . $project->name . '** '
             . 'con un total de **' . $global_stats['total'] . ' casos de uso** evaluados. '
             . 'El resultado es satisfactorio: **todos los casos pasaron correctamente**, alcanzando un pass rate del **' . $pass_rate . '%**. '
             . 'No se registraron defectos ni bloqueos durante la ejecución.';
    } else {
        $alta_count = 0;
        foreach ($error_sections as $sec) {
            foreach ($sec['cases'] as $item) {
                if (strtolower($item['case']->priority) === 'alta' || $item['case']->status === 'blocked') $alta_count++;
            }
        }
        $L[] = 'Se completó el ciclo de pruebas funcionales sobre el proyecto **' . $project->name . '** '
             . 'evaluando un total de **' . $global_stats['total'] . ' casos de uso**. '
             . 'El equipo de QA identificó **' . $total_errors . ' defecto(s)** que requieren atención por parte del equipo de desarrollo '
             . 'antes de poder aprobar el pase a producción.';
        $L[] = '';
        if ($alta_count > 0) {
            $L[] = '> ⚠️ **Atención:** Se detectaron **' . $alta_count . ' defecto(s) de prioridad Alta o bloqueantes** que impiden el funcionamiento correcto de flujos críticos del sistema.';
        }
    }
    $L[] = '';
    $L[] = '---';
    $L[] = '';

    // Resumen estadístico
    $L[] = '## Resumen de Ejecución';
    $L[] = '';
    $L[] = '| Métrica | Resultado |';
    $L[] = '|---------|-----------|';
    $L[] = '| Casos ejecutados | ' . ($global_stats['total'] - $global_stats['not_tested']) . ' / ' . $global_stats['total'] . ' |';
    $L[] = '| ✅ Pasaron | ' . $global_stats['pass'] . ' |';
    $L[] = '| ❌ Fallaron | ' . $global_stats['fail'] . ' |';
    $L[] = '| ⚠️ Bloqueados | ' . $global_stats['blocked'] . ' |';
    $L[] = '| ⏭️ Omitidos | ' . $global_stats['skipped'] . ' |';
    $L[] = '| 🔘 Sin ejecutar | ' . $global_stats['not_tested'] . ' |';
    $L[] = '| **Pass Rate** | **' . $pass_rate . '%** |';
    $L[] = '| **Defectos a resolver** | **' . $total_errors . '** |';
    $L[] = '';

    // Índice de errores si los hay
    if ($total_errors > 0) {
        $L[] = '---';
        $L[] = '';
        $L[] = '## Índice de Defectos';
        $L[] = '';
        $L[] = '| # | ID Caso | Módulo | Descripción | Severidad | Estado |';
        $L[] = '|---|---------|--------|-------------|-----------|--------|';
        $idx = 0;
        foreach ($error_sections as $section) {
            foreach ($section['cases'] as $item) {
                $idx++;
                $c = $item['case'];
                $sev = $get_severity($c->priority ?: 'Media', $c->status);
                $st  = $c->status === 'fail' ? '❌ FAIL' : '⚠️ BLOQUEADO';
                $L[] = '| ' . $idx . ' | `' . $c->case_id . '` | ' . $section['module']->title . ' | ' . $c->title . ' | ' . $sev . ' | ' . $st . ' |';
            }
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';
        $L[] = '## Detalle de Defectos';
        $L[] = '';
        $L[] = '_Cada defecto incluye descripción del problema, pasos para reproducirlo, resultado observado, impacto y recomendación de corrección._';
        $L[] = '';

        $error_num = 0;
        foreach ($error_sections as $section) {
            $m             = $section['module'];
            $fail_count    = count(array_filter($section['cases'], fn($x) => $x['case']->status === 'fail'));
            $blocked_count = count(array_filter($section['cases'], fn($x) => $x['case']->status === 'blocked'));

            $L[] = '---';
            $L[] = '';
            $L[] = '### Módulo: ' . $m->title;
            $tester_disp = ($section['tester'] && $section['tester'] !== 'No asignado') ? $section['tester'] : 'QA Automatiza Tech';
            $L[] = '_Tester: ' . $tester_disp . ' · ' . ($fail_count ? '❌ ' . $fail_count . ' fallo(s) ' : '') . ($blocked_count ? '⚠️ ' . $blocked_count . ' bloqueado(s)' : '') . '_';
            $L[] = '';

            foreach ($section['cases'] as $item) {
                $error_num++;
                $c              = $item['case'];
                $severity       = $get_severity($c->priority ?: 'Media', $c->status);
                $status_label   = $c->status === 'fail' ? '❌ FAIL' : '⚠️ BLOQUEADO';
                $actual_result  = $get_actual_result($item['comments']);
                $impact         = $get_impact_analysis($c, $m->title, $item['comments']);
                $tester_name    = $c->tester ?: $section['tester'];

                $L[] = '#### DEF-' . str_pad($error_num, 3, '0', STR_PAD_LEFT) . ' · ' . $status_label . ' · `' . $c->case_id . '` — ' . $c->title;
                $L[] = '';

                // Ficha técnica
                $L[] = '| Atributo | Valor |';
                $L[] = '|----------|-------|';
                $L[] = '| **Severidad** | ' . $severity . ' |';
                $L[] = '| **Prioridad** | ' . ($c->priority ?: 'Media') . ' |';
                $L[] = '| **Estado** | ' . $status_label . ' |';
                $L[] = '| **Tester** | ' . $tester_name . ' |';
                if ($c->tested_at) {
                    $L[] = '| **Fecha de prueba** | ' . wp_date('d/m/Y H:i', strtotime($c->tested_at), $tz) . ' |';
                }
                if ($c->bug_id) {
                    $L[] = '| **Bug ID / Ticket** | `' . $c->bug_id . '` |';
                }
                $L[] = '';

                // Descripción del defecto
                $L[] = '**🔍 Descripción del defecto**';
                $L[] = '';
                if ($actual_result) {
                    $L[] = $actual_result;
                } else {
                    $L[] = 'El caso de prueba **' . $c->title . '** no superó la validación durante la ejecución de QA. '
                         . 'El comportamiento del sistema no coincidió con el resultado esperado para este flujo.';
                }
                $L[] = '';

                // Precondición
                if ($c->precondition) {
                    $L[] = '**📋 Precondición / Entorno de prueba**';
                    $L[] = '';
                    $L[] = '> ' . str_replace("\n", "\n> ", trim($c->precondition));
                    $L[] = '';
                }

                // Pasos para reproducir
                if ($c->steps) {
                    $L[] = '**🔁 Pasos para reproducir**';
                    $L[] = '';
                    $step_lines = array_values(array_filter(array_map('trim', explode("\n", $c->steps))));
                    foreach ($step_lines as $idx_s => $step) {
                        $L[] = ($idx_s + 1) . '. ' . $step;
                    }
                    $L[] = '';
                }

                // Resultado esperado vs obtenido
                $L[] = '**✅ Resultado esperado**';
                $L[] = '';
                $expected = $c->expected_result ?: 'El sistema debería ejecutar el flujo sin errores y confirmar la operación al usuario.';
                $L[] = '> ' . str_replace("\n", "\n> ", trim($expected));
                $L[] = '';

                $L[] = '**❌ Resultado obtenido**';
                $L[] = '';
                if ($actual_result) {
                    $L[] = '> ' . str_replace("\n", "\n> ", $actual_result);
                } else {
                    $L[] = '> El sistema no respondió conforme a lo esperado. Ver comentarios del tester para mayor detalle.';
                }
                $L[] = '';

                // Impacto y análisis QA
                $L[] = '**⚡ Análisis de impacto**';
                $L[] = '';
                $L[] = $impact;
                $L[] = '';

                // Observaciones del tester (todos los comentarios)
                if (!empty($item['comments'])) {
                    $L[] = '**💬 Observaciones del tester**';
                    $L[] = '';
                    foreach ($item['comments'] as $cm) {
                        $cm_date = wp_date('d/m/Y H:i', strtotime($cm->created_at), $tz);
                        $author  = $cm->display_name ?: 'QA';
                        $L[] = '> **' . $author . '** — ' . $cm_date . ':';
                        $L[] = '> ' . str_replace("\n", "\n> ", trim($cm->comment));
                        $L[] = '';
                    }
                }

                // Recomendación para el dev
                $L[] = '**🛠️ Recomendación para el desarrollador**';
                $L[] = '';
                if ($c->status === 'blocked') {
                    $L[] = 'Verificar las condiciones previas y dependencias que impiden ejecutar este caso. '
                         . 'Revisar si existe algún error de configuración, dato faltante o dependencia de otro módulo no resuelta. '
                         . 'Una vez desbloqueado, el caso deberá ser re-ejecutado por QA.';
                } else {
                    $title_lower_rec = strtolower($c->title);
                    if (strpos($title_lower_rec, 'validac') !== false || strpos($title_lower_rec, 'formulario') !== false || strpos($title_lower_rec, 'campo') !== false) {
                        $L[] = 'Revisar la lógica de validación del formulario o campo involucrado. '
                             . 'Asegurarse de que los mensajes de error sean claros, que se valide tanto en frontend como backend, '
                             . 'y que los casos límite (campos vacíos, formatos inválidos) estén cubiertos.';
                    } elseif (strpos($title_lower_rec, 'pago') !== false || strpos($title_lower_rec, 'checkout') !== false || strpos($title_lower_rec, 'orden') !== false) {
                        $L[] = 'Revisar el flujo de pago completo: integración con pasarela, manejo de errores de transacción, '
                             . 'estados de la orden y rollback en caso de fallo. Verificar logs de la pasarela de pago.';
                    } elseif (strpos($title_lower_rec, 'login') !== false || strpos($title_lower_rec, 'sesión') !== false || strpos($title_lower_rec, 'autenticac') !== false) {
                        $L[] = 'Revisar el flujo de autenticación: generación y validación de tokens, manejo de sesiones, '
                             . 'respuestas de error claras al usuario y comportamiento con credenciales inválidas.';
                    } else {
                        $L[] = 'Reproducir el caso siguiendo los pasos indicados y revisar la consola del navegador y los logs del servidor '
                             . 'para identificar el punto exacto del fallo. Corregir, realizar prueba unitaria y marcar para re-testing en QA.';
                    }
                }
                $L[] = '';

                // Evidencias
                if (!empty($item['evidences'])) {
                    $L[] = '**📎 Evidencias adjuntas**';
                    $L[] = '';
                    foreach ($item['evidences'] as $ev) {
                        $desc = $ev->description ? ' — _' . $ev->description . '_' : '';
                        $L[] = '- [' . $ev->file_name . '](' . esc_url($ev->file_url) . ')' . $desc;
                    }
                    $L[] = '';
                }

                $L[] = '';
            }
        }

        // Sección de próximos pasos
        $L[] = '---';
        $L[] = '';
        $L[] = '## Próximos Pasos';
        $L[] = '';
        $L[] = '1. El equipo de desarrollo revisa cada defecto listado en este reporte.';
        $L[] = '2. Se asigna responsable y estimación de corrección por cada ítem.';
        $L[] = '3. Una vez corregido, el desarrollador notifica a QA para **re-testing**.';
        $L[] = '4. QA valida la corrección ejecutando nuevamente el caso de prueba.';
        $L[] = '5. Si el caso pasa, se cierra el defecto. Si falla, se documenta el regreso.';
        $L[] = '6. El pase a producción queda condicionado a la resolución de todos los defectos de severidad **Alta** y **Crítica**.';
        $L[] = '';
    }

    $L[] = '---';
    $L[] = '';
    $L[] = '## Firma del Área QA';
    $L[] = '';
    $L[] = '| | |';
    $L[] = '|-|-|';
    $L[] = '| **Elaborado por** | Área QA — Automatiza Tech |';
    $L[] = '| **Fecha** | ' . $date_now . ' |';
    $L[] = '| **Proyecto** | ' . $project->name . ' |';
    $L[] = '| **Versión** | ' . ($project->version ?: '1.0') . ' |';
    $L[] = '| **Pass Rate** | ' . $pass_rate . '% |';
    $L[] = '| **Veredicto** | ' . $verdict_text . ' |';
    $L[] = '';
    $L[] = '---';
    $L[] = '';
    $L[] = '_Reporte generado por el módulo QA de **[Automatiza Tech](https://automatizatech.cl)**._';
    $L[] = '_Para consultas: contacto@automatizatech.cl_';

    $md_content = implode("\n", $L);

    $upload_dir  = wp_upload_dir();
    $reports_dir = $upload_dir['basedir'] . '/qa-reports';
    if (!is_dir($reports_dir)) wp_mkdir_p($reports_dir);

    $filename = 'QA-Errores-' . sanitize_file_name($project->name) . '-' . date('Y-m-d') . '.md';
    // BOM UTF-8 garantiza que cualquier editor/navegador interprete bien el encoding
    file_put_contents($reports_dir . '/' . $filename, "\xEF\xBB\xBF" . $md_content);

    wp_send_json_success([
        'url'          => $upload_dir['baseurl'] . '/qa-reports/' . $filename,
        'filename'     => $filename,
        'total_errors' => $total_errors,
        'pass_rate'    => $pass_rate,
    ]);
});

// ──────────────────────────────────────────────
// 9. ESTILOS COMPARTIDOS
// ──────────────────────────────────────────────
function at_qa_shared_styles() {
    ?>
    <style>
    .at-qa * { box-sizing: border-box; }

    /* Header */
    .at-qa-header { background: linear-gradient(135deg,#0d9488,#14b8a6,#2dd4bf); color:#fff; padding:24px 30px; border-radius:12px; margin-bottom:20px; display:flex; align-items:center; gap:20px; box-shadow:0 4px 20px rgba(13,148,136,.3); }
    .at-qa-header h1 { margin:0; font-size:26px; color:#fff; }
    .at-qa-header .subtitle { opacity:.85; font-size:13px; margin-top:4px; color:#fff; }
    .at-qa-header .qa-btn { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.3); }
    .at-qa-header .qa-btn:hover { background:rgba(255,255,255,.25); color:#fff; }

    /* Cards */
    .at-qa-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; margin-bottom:24px; }
    @media (max-width:768px) { .at-qa-cards { grid-template-columns:1fr; } }
    .at-qa-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.06); border-left:4px solid #0d9488; transition:transform .15s,box-shadow .15s; }
    .at-qa-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.1); }
    .at-qa-card h3 { margin:0 0 6px; font-size:17px; }
    .at-qa-card .card-meta { font-size:12px; color:#888; margin-bottom:10px; }
    .at-qa-card .card-stats { display:flex; gap:10px; flex-wrap:wrap; margin:10px 0; }

    /* Stat pills */
    .stat-pill { display:inline-flex; align-items:center; gap:3px; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; }
    .pill-total { background:#ccfbf1; color:#0d9488; }
    .pill-pass { background:#ecfdf5; color:#065f46; }
    .pill-fail { background:#fef2f2; color:#991b1b; }
    .pill-blocked { background:#fffbeb; color:#92400e; }
    .pill-untested { background:#f3f4f6; color:#6b7280; }

    /* Status badge */
    .qa-status { display:inline-block; padding:3px 12px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; }
    .qa-status-pending { background:#f3f4f6; color:#6b7280; }
    .qa-status-in_progress { background:#ccfbf1; color:#0f766e; }
    .qa-status-passed { background:#ecfdf5; color:#065f46; }
    .qa-status-failed { background:#fef2f2; color:#991b1b; }
    .qa-status-on_hold { background:#fffbeb; color:#92400e; }

    /* Buttons */
    .qa-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border:1px solid #d1d5db; border-radius:8px; background:#fff; cursor:pointer; font-size:13px; transition:all .15s; text-decoration:none; color:#333; }
    .qa-btn:hover { background:#f3f4f6; border-color:#9ca3af; }
    .qa-btn-primary { background:#0d9488; color:#fff; border-color:#0d9488; }
    .qa-btn-primary:hover { background:#0f766e; color:#fff; }
    .qa-btn-danger { background:#ef4444; color:#fff; border-color:#ef4444; }
    .qa-btn-danger:hover { background:#dc2626; color:#fff; }
    .qa-btn-sm { padding:4px 10px; font-size:12px; }

    /* Progress bar */
    .qa-progress { height:8px; background:#e5e7eb; border-radius:4px; overflow:hidden; margin:6px 0; }
    .qa-progress .fill { height:100%; transition:width .3s; }
    .fill-pass { background:#10b981; }
    .fill-fail { background:#ef4444; }
    .fill-blocked { background:#f59e0b; }
    .fill-skipped { background:#8b5cf6; }

    /* Modal */
    .at-qa-modal-bg { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); z-index:100001; justify-content:center; align-items:flex-start; padding:40px 20px; }
    .at-qa-modal-bg.active { display:flex; }
    .at-qa-modal { background:#fff; border-radius:12px; width:100%; max-width:700px; max-height:85vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
    .at-qa-modal-hd { padding:18px 24px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:#fff; z-index:10; border-radius:12px 12px 0 0; }
    .at-qa-modal-hd h3 { margin:0; font-size:17px; }
    .at-qa-modal-close { background:none; border:none; font-size:22px; cursor:pointer; color:#999; padding:4px 8px; }
    .at-qa-modal-close:hover { color:#333; }
    .at-qa-modal-body { padding:24px; }

    .at-qa-field { margin-bottom:14px; }
    .at-qa-field label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.3px; }
    .at-qa-field input, .at-qa-field select, .at-qa-field textarea { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
    .at-qa-field textarea { min-height:70px; resize:vertical; }

    /* Toast */
    .at-qa-toast { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) scale(.8); padding:20px 36px; background:#333; color:#fff; border-radius:12px; font-size:16px; font-weight:600; z-index:1000001; opacity:0; transition:all .3s ease; pointer-events:none; box-shadow:0 8px 40px rgba(0,0,0,.35); text-align:center; min-width:220px; }
    .at-qa-toast.show { opacity:1; transform:translate(-50%,-50%) scale(1); }
    .at-qa-toast.success { background:linear-gradient(135deg,#065f46,#0d9488); }
    .at-qa-toast.error { background:linear-gradient(135deg,#991b1b,#dc2626); }

    /* Loading */
    .qa-spin { display:inline-block; width:16px; height:16px; border:2px solid #ddd; border-top-color:#0d9488; border-radius:50%; animation:qaspin .6s linear infinite; }
    @keyframes qaspin { to { transform:rotate(360deg); } }
    .qa-btn:disabled { opacity:0.65; cursor:not-allowed; pointer-events:none; }

    /* Empty state */
    .at-qa-empty { text-align:center; padding:60px 20px; color:#999; }
    .at-qa-empty .empty-icon { font-size:50px; display:block; margin-bottom:12px; }
    </style>
    <script>document.body.classList.add('at-qa-page');</script>
    <?php
}

// ══════════════════════════════════════════════
// 10. PÁGINA DE PROYECTOS (LISTADO)
// ══════════════════════════════════════════════
function at_qa_render_projects_page() {
    global $wpdb;
    $t = at_qa_table_names();
    $nonce = wp_create_nonce('at_qa_nonce');

    // Proyectos con stats
    $projects = $wpdb->get_results("SELECT p.*, 
        (SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=p.id) as total,
        (SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=p.id AND c.status='pass') as passed,
        (SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=p.id AND c.status='fail') as failed,
        (SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=p.id AND c.status='blocked') as blocked,
        (SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=p.id AND c.status='not_tested') as untested
        FROM {$t['projects']} p ORDER BY p.updated_at DESC
    ");

    // Clientes y prospectos del CRM real
    $clients_table = $wpdb->prefix . 'crm_clientes';
    $clients = [];
    if ($wpdb->get_var("SHOW TABLES LIKE '$clients_table'") === $clients_table) {
        $clients = $wpdb->get_results("SELECT id, nombre, empresa, tipo, estado FROM $clients_table ORDER BY nombre ASC");
    }

    // Testers disponibles
    $testers = get_users(['role__in' => ['qa_tester', 'administrator'], 'orderby' => 'display_name']);

    ?>
    <div class="wrap at-qa">
    <?php at_qa_shared_styles(); ?>

    <div class="at-qa-header">
        <div>
            <h1>🧪 QA — Gestión de Pruebas</h1>
            <div class="subtitle">Automatiza Tech · <?php echo count($projects); ?> proyecto(s) en QA</div>
        </div>
        <div style="margin-left:auto;">
            <?php if (current_user_can('manage_options')): ?>
            <button class="qa-btn qa-btn-primary" onclick="atQaOpenProjectModal()">➕ Nuevo Proyecto QA</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($projects)): ?>
        <div class="at-qa-empty">
            <span class="empty-icon">🧪</span>
            <h2>No hay proyectos QA</h2>
            <p>Crea un proyecto para comenzar a gestionar pruebas de un cliente.</p>
            <?php if (current_user_can('manage_options')): ?>
            <button class="qa-btn qa-btn-primary" onclick="atQaOpenProjectModal()" style="margin-top:12px;">➕ Crear Primer Proyecto</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="at-qa-cards">
        <?php foreach ($projects as $p):
            $rate = $p->total > 0 ? round(($p->passed / $p->total) * 100, 1) : 0;
            $client_name = '';
            if (!empty($p->is_internal)) {
                $client_name = '🏠 Automatiza Tech';
            } elseif ($p->client_id) {
                foreach ($clients as $cl) {
                    if ($cl->id == $p->client_id) { $client_name = $cl->empresa ?: $cl->nombre; break; }
                }
            }
        ?>
        <div class="at-qa-card" style="border-left-color:<?php
            echo $p->qa_status === 'passed' ? '#10b981' : ($p->qa_status === 'failed' ? '#ef4444' : ($p->qa_status === 'in_progress' ? '#14b8a6' : '#9ca3af'));
        ?>">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h3><?php echo esc_html($p->name); ?></h3>
                    <div class="card-meta">
                        <?php if ($client_name): ?>👤 <?php echo esc_html($client_name); ?> · <?php endif; ?>
                        v<?php echo esc_html($p->version); ?>
                        <?php if ($p->environment): ?> · 🌐 <?php echo esc_html($p->environment); ?><?php endif; ?>
                    </div>
                </div>
                <span class="qa-status qa-status-<?php echo $p->qa_status; ?>">
                    <?php
                    $labels = ['pending'=>'Pendiente','in_progress'=>'En progreso','passed'=>'Aprobado','failed'=>'Fallido','on_hold'=>'En pausa'];
                    echo $labels[$p->qa_status] ?? $p->qa_status;
                    ?>
                </span>
            </div>

            <?php if ($p->description): ?>
            <p style="font-size:13px; color:#666; margin:6px 0;"><?php echo esc_html(mb_strimwidth($p->description, 0, 120, '…')); ?></p>
            <?php endif; ?>

            <div class="card-stats">
                <span class="stat-pill pill-total">📋 <?php echo $p->total; ?></span>
                <span class="stat-pill pill-pass">✅ <?php echo $p->passed; ?></span>
                <span class="stat-pill pill-fail">❌ <?php echo $p->failed; ?></span>
                <span class="stat-pill pill-blocked">⚠️ <?php echo $p->blocked; ?></span>
                <span class="stat-pill pill-untested">🔘 <?php echo $p->untested; ?></span>
            </div>

            <div class="qa-progress">
                <?php if ($p->total > 0): ?>
                <div style="display:flex; width:100%; height:100%;">
                    <div class="fill fill-pass" style="width:<?php echo ($p->passed/$p->total)*100; ?>%"></div>
                    <div class="fill fill-fail" style="width:<?php echo ($p->failed/$p->total)*100; ?>%"></div>
                    <div class="fill fill-blocked" style="width:<?php echo ($p->blocked/$p->total)*100; ?>%"></div>
                </div>
                <?php endif; ?>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                <span style="font-size:13px; font-weight:700; color:<?php echo $rate >= 95 ? '#10b981' : ($rate >= 70 ? '#d97706' : '#ef4444'); ?>"><?php echo $rate; ?>% Pass Rate</span>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <a href="<?php echo admin_url('admin.php?page=at-qa&view=suite&project=' . $p->id); ?>" class="qa-btn qa-btn-sm qa-btn-primary">📋 Ver Casos</a>
                    <?php if (current_user_can('manage_options')): ?>
                    <button class="qa-btn qa-btn-sm" onclick="atQaGenerateReport(<?php echo $p->id; ?>)" title="Generar informe HTML + PDF">📄 Informe</button>
                    <button class="qa-btn qa-btn-sm" onclick="atQaSendReportEmail(<?php echo $p->id; ?>)" title="Enviar último informe por correo al cliente" style="color:#0d9488;">📧 Enviar al cliente</button>
                    <button class="qa-btn qa-btn-sm" onclick="atQaGenerateErrorReport(<?php echo $p->id; ?>)" title="Informe de errores en .md" style="color:#991b1b;">📋 Errores .md</button>
                    <button class="qa-btn qa-btn-sm" onclick='atQaOpenProjectModal(<?php echo json_encode($p); ?>)'>✏️</button>
                    <button class="qa-btn qa-btn-sm qa-btn-danger" onclick="atQaDeleteProject(<?php echo $p->id; ?>)">🗑️</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal crear/editar proyecto -->
    <div id="projectModal" class="at-qa-modal-bg">
        <div class="at-qa-modal">
            <div class="at-qa-modal-hd">
                <h3 id="projModalTitle">Nuevo Proyecto QA</h3>
                <button class="at-qa-modal-close" onclick="atQaCloseProjectModal()">&times;</button>
            </div>
            <div class="at-qa-modal-body">
                <input type="hidden" id="projId" value="0">

                <div class="at-qa-field">
                    <label>Nombre del Proyecto *</label>
                    <input type="text" id="projName" placeholder="Ej: PetsGO Marketplace, Landing MaxTech...">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="at-qa-field">
                        <label>Cliente vinculado</label>
                        <select id="projClient">
                            <option value="0">— Sin vincular —</option>
                            <option value="internal" style="color:#0d9488; font-weight:600;">🏠 Proyecto Interno AT</option>
                            <?php foreach ($clients as $cl): ?>
                            <option value="<?php echo $cl->id; ?>"><?php echo esc_html(($cl->empresa ? $cl->empresa . ' — ' : '') . $cl->nombre); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="at-qa-field">
                        <label>Estado QA</label>
                        <select id="projStatus">
                            <option value="pending">⏳ Pendiente</option>
                            <option value="in_progress">🔄 En progreso</option>
                            <option value="passed">✅ Aprobado</option>
                            <option value="failed">❌ Fallido</option>
                            <option value="on_hold">⏸️ En pausa</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="at-qa-field">
                        <label>Versión</label>
                        <input type="text" id="projVersion" value="1.0" placeholder="1.0">
                    </div>
                    <div class="at-qa-field">
                        <label>Entorno</label>
                        <input type="text" id="projEnv" placeholder="localhost / petsgo.cl / staging...">
                    </div>
                </div>

                <div class="at-qa-field">
                    <label>Ruta a archivos MD (para importar)</label>
                    <input type="text" id="projMdPath" placeholder="Ej: Clientes/PetsGO/QA/">
                </div>

                <div class="at-qa-field">
                    <label>Testers asignados</label>
                    <select id="projTesters" multiple style="min-height:80px;">
                        <?php foreach ($testers as $u): ?>
                        <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?> (<?php echo implode(', ', $u->roles); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#888;">Ctrl+Click para seleccionar múltiples</small>
                </div>

                <div class="at-qa-field">
                    <label>Descripción</label>
                    <textarea id="projDesc" rows="12" style="min-height:200px;resize:vertical;" placeholder="Descripción del proyecto y alcance del QA..."></textarea>
                </div>

                <div style="text-align:right; margin-top:16px;">
                    <button class="qa-btn" onclick="atQaCloseProjectModal()">Cancelar</button>
                    <button class="qa-btn qa-btn-primary" onclick="atQaSaveProject()">💾 Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="atQaToast" class="at-qa-toast"></div>

    <script>
    (function(){
        const NONCE = '<?php echo $nonce; ?>';
        const AJAX  = '<?php echo admin_url("admin-ajax.php"); ?>';

        /* Parseo seguro: ignora warnings/notices de PHP antes del JSON */
        function safeJson(r){
            return r.text().then(function(t){
                const start=t.indexOf('{'), end=t.lastIndexOf('}');
                if(start===-1||end===-1) return {success:false,data:'Respuesta inválida del servidor'};
                try{ return JSON.parse(t.substring(start,end+1)); }
                catch(e){ return {success:false,data:'Respuesta inválida del servidor'}; }
            });
        }

        function toast(msg, type) {
            const t = document.getElementById('atQaToast');
            t.textContent = msg; t.className = 'at-qa-toast show ' + (type||'');
            setTimeout(() => t.className = 'at-qa-toast', 3000);
        }

        window.atQaOpenProjectModal = function(data) {
            document.getElementById('projModalTitle').textContent = data ? 'Editar Proyecto QA' : 'Nuevo Proyecto QA';
            document.getElementById('projId').value       = data ? data.id : 0;
            document.getElementById('projName').value     = data ? data.name : '';
            document.getElementById('projClient').value   = data ? (data.is_internal ? 'internal' : (data.client_id || 0)) : 0;
            document.getElementById('projStatus').value   = data ? data.qa_status : 'pending';
            document.getElementById('projVersion').value  = data ? data.version : '1.0';
            document.getElementById('projEnv').value      = data ? (data.environment || '') : '';
            document.getElementById('projMdPath').value   = data ? (data.md_base_path || '') : '';
            document.getElementById('projDesc').value     = data ? (data.description || '') : '';

            // Testers
            const sel = document.getElementById('projTesters');
            const assigned = data && data.assigned_testers ? data.assigned_testers.split(',') : [];
            for (let opt of sel.options) {
                opt.selected = assigned.includes(opt.value);
            }

            document.getElementById('projectModal').classList.add('active');
        };

        window.atQaCloseProjectModal = function() {
            document.getElementById('projectModal').classList.remove('active');
        };

        window.atQaSaveProject = function() {
            const name = document.getElementById('projName').value.trim();
            if (!name) { toast('Nombre requerido', 'error'); return; }

            const sel = document.getElementById('projTesters');
            const testers = Array.from(sel.selectedOptions).map(o => o.value).join(',');

            const fd = new FormData();
            fd.append('action', 'at_qa_save_project');
            fd.append('nonce', NONCE);
            fd.append('id', document.getElementById('projId').value);
            fd.append('name', name);
            fd.append('client_id', document.getElementById('projClient').value);
            fd.append('qa_status', document.getElementById('projStatus').value);
            fd.append('version', document.getElementById('projVersion').value);
            fd.append('environment', document.getElementById('projEnv').value);
            fd.append('md_base_path', document.getElementById('projMdPath').value);
            fd.append('description', document.getElementById('projDesc').value);
            fd.append('assigned_testers', testers);

            fetch(AJAX, {method:'POST', body:fd}).then(r=>safeJson(r)).then(res => {
                if (res.success) {
                    toast('Proyecto guardado ✅', 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    toast(res.data||'Error al guardar proyecto', 'error');
                    console.error('[QA Save]', res);
                }
            }).catch(err => {
                toast('Error de conexión: ' + err.message, 'error');
                console.error('[QA Save] catch:', err);
            });
        };

        window.atQaDeleteProject = function(pid) {
            if (!confirm('¿Eliminar este proyecto QA y todos sus casos, evidencias y comentarios? Esta acción no se puede deshacer.')) return;
            const fd = new FormData();
            fd.append('action', 'at_qa_delete_project');
            fd.append('nonce', NONCE);
            fd.append('project_id', pid);
            fetch(AJAX, {method:'POST', body:fd}).then(r=>safeJson(r)).then(res => {
                if (res.success) { toast('Proyecto eliminado ✅', 'success'); setTimeout(()=>location.reload(), 600); }
                else toast(res.data||'Error al eliminar', 'error');
            }).catch(err => { toast('Error: ' + err.message, 'error'); });
        };

        // Cache: último PDF generado por proyecto (para enviarlo sin regenerar)
        window.atQaLastPdf = window.atQaLastPdf || {};

        // Generar informe QA (HTML + PDF)
        window.atQaGenerateReport = function(pid) {
            if (!confirm('¿Generar informe QA (HTML + PDF) de este proyecto?')) return;
            const fd = new FormData();
            fd.append('action', 'at_qa_generate_report');
            fd.append('nonce', NONCE);
            fd.append('project_id', pid);
            toast('Generando informe...', '');
            fetch(AJAX, {method:'POST', body:fd}).then(r=>safeJson(r)).then(res => {
                if (res.success) {
                    toast('✅ Informe generado: ' + res.data.verdict + ' — ' + res.data.pass_rate + '%', 'success');
                    window.atQaLastPdf[pid] = res.data.pdf_filename || '';
                    window.open(res.data.url, '_blank');
                    if (res.data.pdf_url) window.open(res.data.pdf_url, '_blank');
                } else toast(res.data || 'Error', 'error');
            }).catch(err => { toast('Error: ' + err.message, 'error'); });
        };

        // Enviar el último informe PDF por correo al cliente (BCC admin)
        window.atQaSendReportEmail = function(pid) {
            let pdfFile = window.atQaLastPdf[pid];
            if (!pdfFile) {
                if (!confirm('No hay informe generado en esta sesión. ¿Generar primero el informe y luego enviarlo?')) return;
                return atQaGenerateReport(pid);
            }
            const toEmail = prompt('Email del destinatario (deja vacío para usar el del cliente vinculado):', '');
            if (toEmail === null) return;
            const customMsg = prompt('Mensaje adicional (opcional, se mostrará en el correo):', '') || '';
            if (!confirm('¿Enviar el informe QA por correo?\n\nDestino: ' + (toEmail || 'cliente del proyecto') + '\nPDF: ' + pdfFile)) return;
            const fd = new FormData();
            fd.append('action', 'at_qa_send_report_email');
            fd.append('nonce', NONCE);
            fd.append('project_id', pid);
            fd.append('pdf_filename', pdfFile);
            if (toEmail) fd.append('to_email', toEmail);
            if (customMsg) fd.append('custom_message', customMsg);
            toast('Enviando correo...', '');
            fetch(AJAX, {method:'POST', body:fd}).then(r=>safeJson(r)).then(res => {
                if (res.success) toast('📧 Correo enviado a ' + res.data.to, 'success');
                else toast(res.data || 'Error enviando correo', 'error');
            }).catch(err => { toast('Error: ' + err.message, 'error'); });
        };

        // Generar informe de errores (.md)
        window.atQaGenerateErrorReport = function(pid) {
            if (!confirm('¿Generar informe de errores (.md) de este proyecto?')) return;
            const fd = new FormData();
            fd.append('action', 'at_qa_generate_error_report');
            fd.append('nonce', NONCE);
            fd.append('project_id', pid);
            toast('Generando informe de errores...', '');
            fetch(AJAX, {method:'POST', body:fd}).then(r=>safeJson(r)).then(res => {
                if (res.success) {
                    const msg = res.data.total_errors === 0
                        ? '✅ Sin errores — ' + res.data.pass_rate + '% Pass Rate'
                        : '📋 Informe listo: ' + res.data.total_errors + ' error(es) — ' + res.data.pass_rate + '% Pass Rate';
                    toast(msg, 'success');
                    const a = document.createElement('a');
                    a.href = res.data.url;
                    a.download = res.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                } else toast(res.data || 'Error', 'error');
            }).catch(err => { toast('Error: ' + err.message, 'error'); });
        };

        // Cerrar modales con ESC / click fuera
        document.addEventListener('keydown', e => { if (e.key==='Escape') atQaCloseProjectModal(); });
        document.getElementById('projectModal').addEventListener('click', function(e) { if (e.target===this) atQaCloseProjectModal(); });
    })();
    </script>

    </div>
    <?php
}

// ══════════════════════════════════════════════
// 11. PÁGINA DE SUITE (CASOS POR PROYECTO)
// ══════════════════════════════════════════════
function at_qa_render_suite_page() {
    global $wpdb;
    $t = at_qa_table_names();
    $nonce = wp_create_nonce('at_qa_nonce');

    $project_id = intval($_GET['project'] ?? 0);
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['projects']} WHERE id = %d", $project_id));
    if (!$project) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Proyecto no encontrado.</p></div></div>';
        return;
    }

    $modules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t['modules']} WHERE project_id = %d ORDER BY sort_order ASC", $project_id
    ));

    if (empty($modules)) {
        ?>
        <div class="wrap at-qa">
        <?php at_qa_shared_styles(); ?>
        <div class="at-qa-header">
            <div>
                <h1><?php echo esc_html($project->name); ?></h1>
                <div class="subtitle">Sin módulos importados</div>
            </div>
            <div style="margin-left:auto;">
                <a href="<?php echo admin_url('admin.php?page=at-qa'); ?>" class="qa-btn">&larr; Proyectos</a>
                <?php if (current_user_can('manage_options')): ?>
                <a href="<?php echo admin_url('admin.php?page=at-qa-import'); ?>" class="qa-btn qa-btn-primary">📥 Importar Casos</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="at-qa-empty">
            <span class="empty-icon">📋</span>
            <h2>Sin casos de prueba</h2>
            <p>Importa los casos desde los archivos Markdown del proyecto.</p>
        </div>
        </div>
        <?php
        return;
    }

    // Módulo activo
    $active_mod_id = intval($_GET['module'] ?? $modules[0]->id);
    $active_mod = null;
    foreach ($modules as $m) { if ($m->id == $active_mod_id) { $active_mod = $m; break; } }
    if (!$active_mod) $active_mod = $modules[0];

    // Filtros
    $f_status   = sanitize_text_field($_GET['filter_status'] ?? '');
    $f_priority = sanitize_text_field($_GET['filter_priority'] ?? '');
    $f_search   = sanitize_text_field($_GET['filter_search'] ?? '');

    $where = $wpdb->prepare("WHERE c.module_id = %d", $active_mod->id);
    if ($f_status)   $where .= $wpdb->prepare(" AND c.status = %s", $f_status);
    if ($f_priority) $where .= $wpdb->prepare(" AND c.priority = %s", $f_priority);
    if ($f_search)   { $s = '%'.$wpdb->esc_like($f_search).'%'; $where .= $wpdb->prepare(" AND (c.case_id LIKE %s OR c.title LIKE %s)", $s, $s); }

    $cases = $wpdb->get_results("SELECT c.* FROM {$t['cases']} c $where ORDER BY c.sort_order ASC");

    // Estadísticas globales del proyecto
    $gs_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT c.status, COUNT(*) as cnt FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d GROUP BY c.status", $project_id
    ));
    $gs = ['not_tested'=>0,'pass'=>0,'fail'=>0,'blocked'=>0,'skipped'=>0,'total'=>0];
    foreach ($gs_raw as $s) { $gs[$s->status]=(int)$s->cnt; $gs['total']+=(int)$s->cnt; }

    // Stats del módulo activo
    $ms_raw = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) as cnt FROM {$t['cases']} WHERE module_id=%d GROUP BY status", $active_mod->id));
    $ms = ['not_tested'=>0,'pass'=>0,'fail'=>0,'blocked'=>0,'skipped'=>0,'total'=>0];
    foreach ($ms_raw as $s) { $ms[$s->status]=(int)$s->cnt; $ms['total']+=(int)$s->cnt; }

    // Counts de evidencia/comentarios
    $case_ids = array_map(function($c){return $c->id;}, $cases);
    $ev_counts = $cm_counts = [];
    if (!empty($case_ids)) {
        $ph = implode(',', array_fill(0, count($case_ids), '%d'));
        foreach ($wpdb->get_results($wpdb->prepare("SELECT case_id, COUNT(*) as cnt FROM {$t['evidence']} WHERE case_id IN ($ph) GROUP BY case_id", ...$case_ids)) as $r) $ev_counts[$r->case_id]=(int)$r->cnt;
        foreach ($wpdb->get_results($wpdb->prepare("SELECT case_id, COUNT(*) as cnt FROM {$t['comments']} WHERE case_id IN ($ph) GROUP BY case_id", ...$case_ids)) as $r) $cm_counts[$r->case_id]=(int)$r->cnt;
    }

    $pass_rate = $gs['total']>0 ? round(($gs['pass']/$gs['total'])*100,1) : 0;
    $mod_rate  = $ms['total']>0 ? round(($ms['pass']/$ms['total'])*100,1) : 0;

    // Testers disponibles
    $testers = get_users(['role__in' => ['qa_tester', 'administrator'], 'orderby' => 'display_name']);

    ?>
    <div class="wrap at-qa">
    <?php at_qa_shared_styles(); ?>
    <style>
    /* Layout del suite */
    .qa-layout { display:grid; grid-template-columns:240px 1fr; gap:16px; padding-bottom:80px; }
    @media (max-width:1100px) { .qa-layout { grid-template-columns:1fr; } }
    @media (max-width:768px) {
        .at-qa-header { flex-direction:column; gap:10px; padding:14px 16px; }
        .at-qa-header h1 { font-size:20px; }
        .at-qa-header .subtitle { font-size:11px; }
        .at-qa-header > div:last-child { margin-left:0; }

        /* Sidebar módulos en mobile: horizontal scroll */
        .qa-sidebar { position:relative; top:auto; padding:8px; overflow:hidden; }
        .qa-sidebar h3 { display:none; }
        .qa-mod-list { display:flex; overflow-x:auto; gap:4px; padding-bottom:4px; -webkit-overflow-scrolling:touch; scroll-snap-type:x mandatory; }
        .qa-mod-list li { flex:0 0 auto; scroll-snap-align:center; }
        .qa-mod-list a { white-space:nowrap; padding:6px 10px; font-size:11px; border-radius:20px; background:#f3f4f6; }
        .qa-mod-list a.active { background:#0d9488; color:#fff; }
        .qa-mod-list .mc { min-width:auto; font-size:10px; }
        .qa-mod-list .mb { font-size:9px; padding:1px 5px; margin-left:4px; }
        .qa-mod-list a span:nth-child(2) { max-width:80px; overflow:hidden; text-overflow:ellipsis; }

        /* Header del módulo */
        .qa-mod-header { padding:12px; }
        .qa-mod-header h2 { font-size:14px; }
        .qa-mod-header > div:first-child { flex-direction:column; align-items:stretch !important; }

        /* Tester selector en mobile */
        #modTesterSelect { width:100%; font-size:13px; padding:8px 10px; }

        /* Tabla responsive */
        .qa-table { font-size:12px; }
        .qa-table thead { display:none; }
        .qa-table tbody tr { display:flex; flex-wrap:wrap; padding:10px 12px; border-bottom:1px solid #eee; gap:4px; align-items:center; }
        .qa-table td { border:none; padding:2px 0; }
        .qa-table .c-id { width:auto; font-size:11px; margin-right:6px; }
        .qa-table .c-name { flex:1 1 100%; order:1; font-size:13px; font-weight:500; }
        .qa-table .c-pri { width:auto; order:2; text-align:left; }
        .qa-table .c-st { width:auto; order:3; }
        .qa-table .c-meta { width:auto; order:4; font-size:11px; display:inline-flex !important; gap:4px; }
        .qa-table .c-act { width:auto; order:5; display:inline-flex !important; }
        .qa-table .section-header td { flex:1 1 100%; }

        /* Filtros */
        .qa-filters { flex-direction:column; align-items:stretch; gap:6px; }
        .qa-filters select, .qa-filters input[type=text] { width:100%; font-size:13px; padding:8px 10px; }

        /* Modal */
        .at-qa-modal { margin:5px; max-height:95vh; max-width:100% !important; }
        .at-qa-modal-body { padding:14px !important; }
        .qa-detail-grid { grid-template-columns:1fr; }
        .evidence-grid { grid-template-columns:repeat(auto-fill,minmax(80px,1fr)); }

        /* Stats */
        .qa-mod-header .qa-progress { min-width:100px; }

        /* Chatbot en mobile - FULLSCREEN */
        body.at-qa-page #aria-toggle { width:50px !important; height:50px !important; bottom:15px !important; right:15px !important; }
        body.at-qa-page #aria-panel { position:fixed !important; top:0 !important; left:0 !important; right:0 !important; bottom:0 !important; width:100% !important; height:100% !important; max-height:100% !important; border-radius:0 !important; z-index:9999999 !important; }
        body.at-qa-page #aria-panel.active ~ #aria-toggle { display:none !important; }
        body.at-qa-page.aria-open #aria-toggle { display:none !important; }

        /* Toast responsive */
        .at-qa-toast { min-width:180px; max-width:85vw; padding:16px 20px; font-size:14px; }

        /* Lightbox responsive */
        .qa-lb-img-wrap img { max-width:95vw; max-height:82vh; border-radius:4px; }
        .qa-lightbox-close { top:10px; right:12px; width:38px; height:38px; font-size:24px; }
        .qa-lightbox-nav { width:38px; height:38px; font-size:22px; }
        .qa-lightbox-nav.prev { left:8px; }
        .qa-lightbox-nav.next { right:8px; }
        .qa-lb-toolbar button { width:32px; height:32px; font-size:14px; }
        .qa-lb-counter { font-size:12px; }

        /* Edit comment responsive */
        .cm-item .cm-edit-area { font-size:13px; min-height:50px; }
        .cm-item .cm-edit-actions { flex-wrap:wrap; }
        .cm-item .cm-edit-actions button { flex:1; min-width:80px; padding:6px 10px; font-size:12px; }
    }
    @media (max-width:480px) {
        .qa-layout { gap:8px; padding-bottom:60px; }
        .at-qa-header { padding:12px; }
        .at-qa-header h1 { font-size:17px; }
        .qa-mod-header h2 { font-size:13px; }
        .qa-table .c-name { font-size:12px; }
        .badge { font-size:9px; padding:2px 6px; }
        .st-sel { font-size:11px; padding:4px 6px; }
        .qa-global-stats { gap:5px; }
        .qa-global-stats .stat-pill { font-size:11px !important; padding:3px 8px !important; }
        .qa-global-stats .qa-progress { min-width:100% !important; }
    }

    .qa-sidebar { background:#fff; border-radius:10px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.06); height:fit-content; position:sticky; top:40px; }
    .qa-sidebar h3 { margin:0 0 10px; font-size:13px; color:#888; text-transform:uppercase; letter-spacing:.4px; }
    .qa-mod-list { list-style:none; margin:0; padding:0; }
    .qa-mod-list a { display:flex; align-items:center; gap:8px; padding:9px 10px; border-radius:7px; text-decoration:none; color:#333; font-size:12px; transition:.15s; }
    .qa-mod-list a:hover { background:#f3f4f6; }
    .qa-mod-list a.active { background:#0d9488; color:#fff; font-weight:600; }
    .qa-mod-list .mc { font-weight:700; min-width:40px; font-size:11px; }
    .qa-mod-list .mb { margin-left:auto; background:#e5e7eb; color:#666; font-size:10px; padding:2px 7px; border-radius:10px; }
    .qa-mod-list a.active .mb { background:rgba(255,255,255,.25); color:#fff; }

    .qa-mod-header { background:#fff; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:14px; position:relative; z-index:10; }
    .qa-mod-header h2 { margin:0 0 6px; font-size:18px; }

    /* Mover chatbot ARIA para que no interfiera con controles del QA */
    body.at-qa-page #aria-toggle { bottom:100px !important; right:15px !important; width:50px !important; height:50px !important; z-index:99999 !important; transition:all .3s ease !important; }
    body.at-qa-page #aria-panel { bottom:160px !important; right:15px !important; }
    body.at-qa-page #automatiza-chat-toggle-container,
    body.at-qa-page #maxtech-widget { bottom:100px !important; right:15px !important; z-index:9998 !important; transition:all .3s ease !important; }
    body.at-qa-page #automatiza-ai-chat-widget { bottom:160px !important; }
    /* Cuando el select de tester está abierto, ocultar el chatbot */
    body.at-qa-chatbot-hidden #aria-toggle,
    body.at-qa-chatbot-hidden #aria-panel,
    body.at-qa-chatbot-hidden #automatiza-chat-toggle-container,
    body.at-qa-chatbot-hidden #maxtech-widget { right:-120px !important; opacity:0 !important; pointer-events:none !important; transition:all .3s ease !important; }
    #modTesterSelect { position:relative; z-index:11; }

    .qa-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding-top:12px; border-top:1px solid #eee; margin-top:12px; }
    .qa-filters select, .qa-filters input[type=text] { padding:5px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:12px; }
    .qa-filters input[type=text] { width:200px; }

    /* Tabla */
    .qa-table { width:100%; border-collapse:separate; border-spacing:0; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    .qa-table thead th { background:#f8fafc; padding:10px 12px; text-align:left; font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #e5e7eb; position:sticky; top:0; z-index:5; }
    .qa-table tbody tr { transition:.1s; }
    .qa-table tbody tr:hover { background:#f9fafb; }
    .qa-table td { padding:9px 12px; border-bottom:1px solid #f0f0f0; font-size:13px; vertical-align:middle; }
    .qa-table .c-id { width:75px; font-weight:700; font-family:monospace; font-size:11px; }
    .qa-table .c-pri { width:65px; text-align:center; }
    .qa-table .c-st { width:130px; }
    .qa-table .c-meta { width:90px; text-align:center; }
    .qa-table .c-act { width:90px; text-align:center; }

    .badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:10px; font-weight:600; text-transform:uppercase; }
    .badge-alta { background:#fee2e2; color:#dc2626; }
    .badge-media { background:#fef3c7; color:#d97706; }
    .badge-baja { background:#ccfbf1; color:#0d9488; }

    .st-sel { padding:5px 8px; border-radius:6px; font-size:11px; font-weight:600; border:2px solid #e5e7eb; cursor:pointer; width:125px; }
    .st-sel.status-not_tested { border-color:#d1d5db; background:#f9fafb; }
    .st-sel.status-pass { border-color:#10b981; background:#ecfdf5; color:#065f46; }
    .st-sel.status-fail { border-color:#ef4444; background:#fef2f2; color:#991b1b; }
    .st-sel.status-blocked { border-color:#f59e0b; background:#fffbeb; color:#92400e; }
    .st-sel.status-skipped { border-color:#8b5cf6; background:#f5f3ff; color:#5b21b6; }

    .meta-pill { display:inline-flex; align-items:center; gap:2px; font-size:10px; color:#888; background:#f3f4f6; padding:2px 7px; border-radius:10px; }
    .meta-pill.has { background:#ccfbf1; color:#0d9488; }

    .section-row td { background:#f0fdfa !important; font-weight:700; color:#0d9488; font-size:12px; padding:7px 12px !important; }

    .tester-info { font-size:10px; color:#999; margin-top:2px; }

    /* Detail modal extras */
    .qa-detail-section { margin-bottom:22px; }
    .qa-detail-section h4 { font-size:12px; text-transform:uppercase; letter-spacing:.4px; color:#0d9488; margin:0 0 8px; padding-bottom:5px; border-bottom:1px solid #99f6e4; }
    .qa-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .qa-detail-item label { font-size:10px; color:#999; display:block; margin-bottom:2px; }
    .qa-detail-item p { margin:0; font-size:13px; }

    .evidence-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px; }
    .ev-card { border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; position:relative; }
    .ev-card img { width:100%; height:110px; object-fit:cover; display:block; }
    .ev-card .ev-info { padding:6px 8px; font-size:10px; }
    .ev-card .ev-del { position:absolute; top:3px; right:3px; background:rgba(239,68,68,.9); color:#fff; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:11px; display:flex; align-items:center; justify-content:center; }
    .ev-file-icon { width:100%; height:110px; display:flex; align-items:center; justify-content:center; background:#f8fafc; font-size:36px; }
    .ev-card img { cursor:zoom-in; }

    /* Lightbox Gallery */
    .qa-lightbox-bg { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.92); z-index:999999; display:none; align-items:center; justify-content:center; flex-direction:column; }
    .qa-lightbox-bg.active { display:flex; }
    .qa-lb-img-wrap { position:relative; display:flex; align-items:center; justify-content:center; overflow:hidden; max-width:94vw; max-height:80vh; }
    .qa-lb-img-wrap img { max-width:92vw; max-height:78vh; border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,.5); object-fit:contain; cursor:default; transition:transform .25s ease; user-select:none; }
    .qa-lightbox-close { position:absolute; top:14px; right:18px; color:#fff; font-size:28px; cursor:pointer; z-index:1000000; background:rgba(0,0,0,.5); border:none; border-radius:50%; width:44px; height:44px; display:flex; align-items:center; justify-content:center; transition:background .2s; }
    .qa-lightbox-close:hover { background:rgba(239,68,68,.85); }
    .qa-lightbox-nav { position:absolute; top:50%; transform:translateY(-50%); color:#fff; font-size:24px; cursor:pointer; z-index:1000000; background:rgba(255,255,255,.15); border:none; border-radius:50%; width:48px; height:48px; display:flex; align-items:center; justify-content:center; transition:background .2s; user-select:none; backdrop-filter:blur(4px); }
    .qa-lightbox-nav:hover { background:rgba(255,255,255,.3); }
    .qa-lightbox-nav.prev { left:18px; }
    .qa-lightbox-nav.next { right:18px; }
    .qa-lb-toolbar { display:flex; gap:10px; align-items:center; margin-top:14px; }
    .qa-lb-toolbar button { background:rgba(255,255,255,.12); border:none; color:#fff; width:38px; height:38px; border-radius:50%; cursor:pointer; font-size:17px; display:flex; align-items:center; justify-content:center; transition:background .2s; backdrop-filter:blur(4px); }
    .qa-lb-toolbar button:hover { background:rgba(255,255,255,.25); }
    .qa-lb-counter { color:rgba(255,255,255,.85); font-size:14px; font-weight:600; letter-spacing:.5px; }
    .qa-download-all { display:inline-flex; align-items:center; gap:6px; background:#0d9488; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:12px; cursor:pointer; transition:.2s; margin-left:8px; vertical-align:middle; }
    .qa-download-all:hover { background:#0f766e; }

    .qa-upload-area { border:2px dashed #d1d5db; border-radius:8px; padding:18px; text-align:center; cursor:pointer; transition:.15s; margin-top:10px; }
    .qa-upload-area:hover { border-color:#0d9488; background:#f0fdfa; }
    .qa-upload-area.dragover { border-color:#0d9488; background:#ccfbf1; }

    .comment-list { max-height:280px; overflow-y:auto; }
    .cm-item { padding:8px 12px; border-left:3px solid #0d9488; background:#f0fdfa; border-radius:0 6px 6px 0; margin-bottom:6px; }
    .cm-item .cm-meta { font-size:10px; color:#999; margin-bottom:3px; }
    .cm-item .cm-text { font-size:13px; margin:0; }
    .cm-item .cm-del { float:right; background:none; border:none; color:#ccc; cursor:pointer; font-size:13px; }
    .cm-item .cm-del:hover { color:#ef4444; }
    .cm-item .cm-edit { float:right; background:none; border:none; color:#ccc; cursor:pointer; font-size:12px; margin-right:6px; }
    .cm-item .cm-edit:hover { color:#0d9488; }
    .cm-item .cm-edit-area { width:100%; border:1px solid #0d9488; border-radius:6px; padding:6px 10px; font-size:12px; resize:vertical; min-height:40px; margin-top:4px; font-family:inherit; }
    .cm-item .cm-edit-actions { display:flex; gap:6px; margin-top:6px; justify-content:flex-end; }
    .cm-item .cm-edit-actions button { font-size:11px; padding:4px 12px; border-radius:5px; border:none; cursor:pointer; }
    .cm-item .cm-save-btn { background:#0d9488; color:#fff; }
    .cm-item .cm-save-btn:hover { background:#0f766e; }
    .cm-item .cm-cancel-btn { background:#e5e7eb; color:#374151; }
    .cm-item .cm-cancel-btn:hover { background:#d1d5db; }

    .cm-form { display:flex; gap:8px; margin-top:10px; }
    .cm-form textarea { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; resize:vertical; min-height:50px; }
    </style>

    <!-- HEADER -->
    <div class="at-qa-header">
        <div>
            <h1>📋 <?php echo esc_html($project->name); ?></h1>
            <div class="subtitle">
                <?php echo count($modules); ?> módulos · <?php echo $gs['total']; ?> casos ·
                <span style="font-weight:700; color:<?php echo $pass_rate >= 95 ? '#a7f3d0' : ($pass_rate >= 70 ? '#fde68a' : '#fca5a5'); ?>"><?php echo $pass_rate; ?>% Pass Rate</span>
            </div>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="qa-btn" onclick="document.getElementById('modalEstados').classList.add('active')" style="background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.3);" title="Ver significado de cada estado">🚦 Estados</button>
            <button class="qa-btn" onclick="document.getElementById('modalGlosario').classList.add('active')" style="background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.3);" title="Glosario de términos técnicos">📚 Glosario</button>
            <?php if (current_user_can('manage_options')): ?>
            <button class="qa-btn" onclick="atQaGenerateReport(<?php echo $project_id; ?>)" style="background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.3);">📄 Generar Informe</button>
            <button class="qa-btn" onclick="atQaSendReportEmail(<?php echo $project_id; ?>)" style="background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.3);">📧 Enviar al cliente</button>
            <button class="qa-btn" onclick="atQaGenerateErrorReport(<?php echo $project_id; ?>)" style="background:rgba(220,38,38,.25); color:#fca5a5; border-color:rgba(220,38,38,.4);" title="Descargar informe detallado de errores en formato .md">📋 Errores .md</button>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=at-qa'); ?>" class="qa-btn">&larr; Proyectos</a>
        </div>
    </div>

    <!-- GLOBAL STATS -->
    <div class="qa-global-stats" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
        <span class="stat-pill pill-total" style="font-size:13px; padding:5px 14px;">📋 <?php echo $gs['total']; ?> Total</span>
        <span class="stat-pill pill-pass" style="font-size:13px; padding:5px 14px;">✅ <?php echo $gs['pass']; ?> Pass</span>
        <span class="stat-pill pill-fail" style="font-size:13px; padding:5px 14px;">❌ <?php echo $gs['fail']; ?> Fail</span>
        <span class="stat-pill pill-blocked" style="font-size:13px; padding:5px 14px;">⚠️ <?php echo $gs['blocked']; ?> Bloqueados</span>
        <span class="stat-pill pill-untested" style="font-size:13px; padding:5px 14px;">🔘 <?php echo $gs['not_tested']; ?> Sin probar</span>
        <div class="qa-progress" style="flex:1; min-width:200px; align-self:center;">
            <?php if ($gs['total']>0): ?>
            <div style="display:flex; width:100%; height:100%;">
                <div class="fill fill-pass" style="width:<?php echo ($gs['pass']/$gs['total'])*100; ?>%"></div>
                <div class="fill fill-fail" style="width:<?php echo ($gs['fail']/$gs['total'])*100; ?>%"></div>
                <div class="fill fill-blocked" style="width:<?php echo ($gs['blocked']/$gs['total'])*100; ?>%"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LAYOUT -->
    <div class="qa-layout">

        <!-- SIDEBAR -->
        <div class="qa-sidebar">
            <h3>Módulos</h3>
            <ul class="qa-mod-list">
            <?php foreach ($modules as $m):
                $url = admin_url('admin.php?page=at-qa&view=suite&project=' . $project_id . '&module=' . $m->id);
                $is_active = ($m->id == $active_mod->id);
                $m_s = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) as cnt FROM {$t['cases']} WHERE module_id=%d GROUP BY status", $m->id));
                $m_p = 0; $m_t = 0;
                foreach ($m_s as $s) { if ($s->status==='pass') $m_p=(int)$s->cnt; $m_t+=(int)$s->cnt; }
            ?>
            <li><a href="<?php echo esc_url($url); ?>" class="<?php echo $is_active?'active':''; ?>">
                <span class="mc"><?php echo esc_html($m->code); ?></span>
                <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?php echo esc_html(preg_replace('/^QA-\d+\s*—?\s*/', '', $m->title)); ?>
                </span>
                <span class="mb"><?php echo $m_p; ?>/<?php echo $m_t; ?></span>
            </a></li>
            <?php endforeach; ?>
            </ul>
        </div>

        <!-- MAIN -->
        <div>
            <div class="qa-mod-header">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <h2 style="margin:0;"><?php echo esc_html($active_mod->title); ?></h2>
                            <?php if (!empty($project->environment)): 
                                $env_url = esc_url($project->environment);
                                $env_backend = esc_url(rtrim($project->environment, '/') . '/wp-login.php');
                            ?>
                            <div style="display:inline-flex; gap:5px;">
                                <a href="<?php echo $env_url; ?>" target="_blank" style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; border-radius:15px; text-decoration:none; font-size:11px; font-weight:600;" title="Abrir sitio frontend">🌐 Frontend</a>
                                <a href="<?php echo $env_backend; ?>" target="_blank" style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; border-radius:15px; text-decoration:none; font-size:11px; font-weight:600;" title="Acceder al backend wp-admin">🔧 Backend</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($active_mod->description): ?><p style="color:#666; font-size:13px; margin:4px 0 8px;"><?php echo esc_html($active_mod->description); ?></p><?php endif; ?>
                    </div>
                    <?php if (current_user_can('manage_options')): ?>
                    <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
                        <span style="color:#888;">👤 Tester:</span>
                        <select id="modTesterSelect" style="padding:4px 8px; border:1px solid #d1d5db; border-radius:6px; font-size:12px;" onchange="atQaAssignTester(<?php echo $active_mod->id; ?>, this.value)">
                            <option value="0">— Sin asignar —</option>
                            <?php foreach ($testers as $tst): ?>
                            <option value="<?php echo $tst->ID; ?>" <?php selected($active_mod->assigned_tester, $tst->ID); ?>><?php echo esc_html($tst->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <?php if ($active_mod->assigned_tester): ?>
                    <span style="font-size:12px; color:#0d9488;">👤 <?php echo esc_html(get_userdata($active_mod->assigned_tester)->display_name ?? 'Desconocido'); ?></span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-top:8px;">
                    <span style="font-size:12px;">✅ <?php echo $ms['pass']; ?> ❌ <?php echo $ms['fail']; ?> ⚠️ <?php echo $ms['blocked']; ?> ⏭️ <?php echo $ms['skipped']; ?> 🔘 <?php echo $ms['not_tested']; ?></span>
                    <span style="font-size:12px; font-weight:700; color:<?php echo $mod_rate>=95?'#10b981':($mod_rate>=70?'#d97706':'#ef4444'); ?>"><?php echo $mod_rate; ?>%</span>
                    <div class="qa-progress" style="flex:1; min-width:160px;">
                        <?php if($ms['total']>0): ?>
                        <div style="display:flex; width:100%; height:100%;">
                            <div class="fill fill-pass" style="width:<?php echo ($ms['pass']/$ms['total'])*100; ?>%"></div>
                            <div class="fill fill-fail" style="width:<?php echo ($ms['fail']/$ms['total'])*100; ?>%"></div>
                            <div class="fill fill-blocked" style="width:<?php echo ($ms['blocked']/$ms['total'])*100; ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="qa-filters" method="get">
                    <input type="hidden" name="page" value="at-qa">
                    <input type="hidden" name="view" value="suite">
                    <input type="hidden" name="project" value="<?php echo $project_id; ?>">
                    <input type="hidden" name="module" value="<?php echo $active_mod->id; ?>">
                    <select name="filter_status">
                        <option value="">— Estado —</option>
                        <option value="not_tested" <?php selected($f_status,'not_tested'); ?>>🔘 Sin probar</option>
                        <option value="pass" <?php selected($f_status,'pass'); ?>>✅ Pass</option>
                        <option value="fail" <?php selected($f_status,'fail'); ?>>❌ Fail</option>
                        <option value="blocked" <?php selected($f_status,'blocked'); ?>>⚠️ Bloqueado</option>
                        <option value="skipped" <?php selected($f_status,'skipped'); ?>>⏭️ Omitido</option>
                    </select>
                    <select name="filter_priority">
                        <option value="">— Prioridad —</option>
                        <option value="Alta" <?php selected($f_priority,'Alta'); ?>>🔴 Alta</option>
                        <option value="Media" <?php selected($f_priority,'Media'); ?>>🟡 Media</option>
                        <option value="Baja" <?php selected($f_priority,'Baja'); ?>>🔵 Baja</option>
                    </select>
                    <input type="text" name="filter_search" value="<?php echo esc_attr($f_search); ?>" placeholder="Buscar caso...">
                    <button type="submit" class="qa-btn qa-btn-sm qa-btn-primary">Filtrar</button>
                    <a href="<?php echo admin_url('admin.php?page=at-qa&view=suite&project='.$project_id.'&module='.$active_mod->id); ?>" class="qa-btn qa-btn-sm">Limpiar</a>
                </form>
            </div>

            <!-- TABLA -->
            <table class="qa-table">
                <thead><tr>
                    <th class="c-id">ID</th>
                    <th>Caso de Uso</th>
                    <th class="c-pri">Prior.</th>
                    <th class="c-st">Estado</th>
                    <th class="c-meta">📎 / 💬</th>
                    <th class="c-act">Acción</th>
                </tr></thead>
                <tbody>
                <?php
                $cur_sec = '';
                foreach ($cases as $c):
                    if ($c->section !== $cur_sec) {
                        $cur_sec = $c->section;
                        echo '<tr class="section-row section-header"><td colspan="6">' . esc_html($cur_sec) . '</td></tr>';
                    }
                    $ev = $ev_counts[$c->id] ?? 0;
                    $cm = $cm_counts[$c->id] ?? 0;
                ?>
                <tr data-cid="<?php echo $c->id; ?>">
                    <td class="c-id"><?php echo esc_html($c->case_id); ?></td>
                    <td class="c-name">
                        <strong><?php echo esc_html($c->title); ?></strong>
                        <?php if ($c->tester): ?><div class="tester-info"><?php echo esc_html($c->tester); ?> · <?php echo $c->tested_at ? date('d/m H:i', strtotime($c->tested_at)) : ''; ?></div><?php endif; ?>
                    </td>
                    <td class="c-pri"><span class="badge badge-<?php echo strtolower($c->priority); ?>"><?php echo $c->priority; ?></span></td>
                    <td class="c-st">
                        <select class="st-sel status-<?php echo $c->status; ?>" onchange="atQaStatus(<?php echo $c->id; ?>,this.value,this)">
                            <option value="not_tested" <?php selected($c->status,'not_tested'); ?>>🔘 Sin probar</option>
                            <option value="pass" <?php selected($c->status,'pass'); ?>>✅ PASS</option>
                            <option value="fail" <?php selected($c->status,'fail'); ?>>❌ FAIL</option>
                            <option value="blocked" <?php selected($c->status,'blocked'); ?>>⚠️ BLOQ.</option>
                            <option value="skipped" <?php selected($c->status,'skipped'); ?>>⏭️ OMIT.</option>
                        </select>
                    </td>
                    <td class="c-meta">
                        <span class="meta-pill <?php echo $ev?'has':''; ?>">📎<?php echo $ev; ?></span>
                        <span class="meta-pill <?php echo $cm?'has':''; ?>">💬<?php echo $cm; ?></span>
                    </td>
                    <td class="c-act">
                        <button class="qa-btn qa-btn-sm" onclick="atQaDetail(<?php echo $c->id; ?>)">🔍</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cases)): ?>
                <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">Sin casos que coincidan.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL DETALLE -->
    <div id="caseModal" class="at-qa-modal-bg">
        <div class="at-qa-modal" style="max-width:900px;">
            <div class="at-qa-modal-hd">
                <h3 id="cmTitle">Detalle</h3>
                <button class="at-qa-modal-close" onclick="atQaCloseDetail()">&times;</button>
            </div>
            <div class="at-qa-modal-body" id="cmBody"><div style="text-align:center; padding:30px;"><div class="qa-spin"></div></div></div>
        </div>
    </div>

    <div id="atQaToast" class="at-qa-toast"></div>

    <!-- Modal: Estados -->
    <div id="modalEstados" class="at-qa-modal-bg" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="at-qa-modal" style="max-width:560px;">
            <div class="at-qa-modal-hd">
                <h3>🚦 Significado de los Estados</h3>
                <button class="at-qa-modal-close" onclick="document.getElementById('modalEstados').classList.remove('active')">&times;</button>
            </div>
            <div class="at-qa-modal-body">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead><tr style="background:#f0fdfa;"><th style="padding:10px 14px;text-align:left;border-bottom:2px solid #0d9488;color:#0d9488;">Estado</th><th style="padding:10px 14px;text-align:left;border-bottom:2px solid #0d9488;color:#0d9488;">Descripción</th></tr></thead>
                    <tbody>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:10px 14px;"><span style="background:#e5e7eb;color:#374151;padding:3px 12px;border-radius:20px;font-weight:700;font-size:12px;">🔘 Sin probar</span></td><td style="padding:10px 14px;color:#555;">El caso de prueba aún no ha sido ejecutado. Estado inicial por defecto.</td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;background:#f9fafb;"><td style="padding:10px 14px;"><span style="background:#059669;color:#fff;padding:3px 12px;border-radius:20px;font-weight:700;font-size:12px;">✅ PASS</span></td><td style="padding:10px 14px;color:#555;">El caso fue ejecutado y el resultado obtenido coincide con el resultado esperado. Sin errores.</td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;"><td style="padding:10px 14px;"><span style="background:#dc2626;color:#fff;padding:3px 12px;border-radius:20px;font-weight:700;font-size:12px;">❌ FAIL</span></td><td style="padding:10px 14px;color:#555;">El caso fue ejecutado pero el resultado es incorrecto. Se encontró un defecto o error en el sistema.</td></tr>
                        <tr style="border-bottom:1px solid #f0f0f0;background:#f9fafb;"><td style="padding:10px 14px;"><span style="background:#f59e0b;color:#fff;padding:3px 12px;border-radius:20px;font-weight:700;font-size:12px;">⚠️ Bloqueado</span></td><td style="padding:10px 14px;color:#555;">No se puede ejecutar el caso porque existe un impedimento externo (otro bug, dependencia, acceso, etc).</td></tr>
                        <tr><td style="padding:10px 14px;"><span style="background:#6366f1;color:#fff;padding:3px 12px;border-radius:20px;font-weight:700;font-size:12px;">⏭️ Omitido</span></td><td style="padding:10px 14px;color:#555;">El caso fue excluido intencionalmente de esta iteración (fuera de alcance, sin tiempo, no aplica).</td></tr>
                    </tbody>
                </table>
                <p style="margin-top:16px;font-size:12px;color:#999;">💡 El <strong>Pass Rate</strong> se calcula como: (PASS ÷ Total ejecutados) × 100. Se considera exitoso cuando supera el 95%.</p>
            </div>
        </div>
    </div>

    <!-- Modal: Glosario -->
    <div id="modalGlosario" class="at-qa-modal-bg" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="at-qa-modal" style="max-width:640px;">
            <div class="at-qa-modal-hd">
                <h3>📚 Glosario de Términos</h3>
                <button class="at-qa-modal-close" onclick="document.getElementById('modalGlosario').classList.remove('active')">&times;</button>
            </div>
            <div class="at-qa-modal-body">
                <input type="text" id="glosarioBuscar" placeholder="🔍 Buscar término..." oninput="filtrarGlosario(this.value)" style="width:100%;padding:9px 13px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;margin-bottom:14px;box-sizing:border-box;">
                <div style="display:grid;gap:12px;" id="glosarioGrid">
                    <?php
                    $glosario = [
                        ['QA', 'Quality Assurance', 'Proceso de garantía de calidad del software. Conjunto de actividades para asegurar que el producto cumple los requisitos y estándares definidos.'],
                        ['Caso de Prueba', 'Test Case', 'Documento que especifica las condiciones, pasos a seguir, datos de entrada y resultado esperado para verificar una funcionalidad específica.'],
                        ['Módulo', 'Module', 'Agrupación lógica de casos de prueba relacionados con una sección o funcionalidad del sistema (ej: Registro de Cliente, Carrito de Compras).'],
                        ['Bug', 'Defecto / Error', 'Comportamiento incorrecto o inesperado encontrado en el sistema durante la ejecución de pruebas. Se documenta con un Bug ID o ticket.'],
                        ['Bug ID / Ticket', 'Issue ID', 'Código de referencia del defecto en el sistema de gestión (ej: BUG-042, JIRA-123). Permite trazar el error y su resolución.'],
                        ['Pass Rate', 'Tasa de Aprobación', 'Porcentaje de casos de prueba que pasaron exitosamente sobre el total de ejecutados. Meta recomendada: ≥ 95%.'],
                        ['Precondición', 'Precondition', 'Estado del sistema o requisitos previos que deben cumplirse antes de ejecutar el caso de prueba (ej: usuario logueado, datos cargados).'],
                        ['Evidencia', 'Evidence / Artifact', 'Captura de pantalla, video o archivo que demuestra el resultado de una prueba. Documenta tanto los PASS como los FAIL.'],
                        ['Regresión', 'Regression', 'Pruebas que verifican que cambios recientes en el código no rompieron funcionalidades que ya funcionaban correctamente.'],
                        ['Sprint', 'Iteración', 'Período de tiempo fijo (usualmente 1-4 semanas) en metodologías ágiles durante el cual se desarrollan y prueban funcionalidades.'],
                        ['Tester', 'Ejecutor de Pruebas', 'Persona responsable de ejecutar los casos de prueba, documentar resultados y reportar defectos encontrados.'],
                        ['Alcance', 'Scope', 'Conjunto de funcionalidades o módulos incluidos en una sesión de pruebas. Lo que queda fuera del alcance se marca como Omitido.'],
                        // Frontend / Web
                        ['LocalStorage', 'Almacenamiento Local del Navegador', 'Espacio del navegador donde una web puede guardar datos de forma persistente en el dispositivo del usuario (sin fecha de expiración). Si algo "queda guardado aunque cierres el navegador", probablemente usa LocalStorage.'],
                        ['SessionStorage', 'Almacenamiento de Sesión', 'Similar al LocalStorage pero los datos se borran al cerrar la pestaña o el navegador. Ideal para datos temporales de sesión.'],
                        ['Cookie', 'Galleta / Cookie', 'Pequeño archivo que el servidor guarda en el navegador del usuario. Se usa para recordar sesiones, preferencias o rastrear visitas. Tiene fecha de expiración configurable.'],
                        ['Caché', 'Cache', 'Copia temporal de datos (páginas, imágenes, respuestas) guardada para no tener que descargarla de nuevo. Cuando algo "no se actualiza", a veces es problema de caché.'],
                        ['API', 'Application Programming Interface', 'Canal de comunicación entre sistemas. Permite que una aplicación solicite datos o acciones a otra (ej: el frontend pide datos al backend a través de una API).'],
                        ['AJAX', 'Asynchronous JavaScript and XML', 'Técnica que permite actualizar partes de una página web sin recargarla completamente. Es lo que hace que los botones "Enviar" respondan sin refrescar la pantalla.'],
                        ['Endpoint', 'Punto de Acceso / Ruta de API', 'URL específica a la que se envían solicitudes para ejecutar una acción o recibir datos. Ej: /wp-admin/admin-ajax.php es un endpoint de WordPress.'],
                        ['Token', 'Token de Autenticación', 'Código único generado al iniciar sesión que identifica al usuario en cada solicitud. Como un "pase temporal" que confirma identidad sin re-ingresar contraseña.'],
                        ['Frontend', 'Capa de Presentación', 'Todo lo que el usuario ve e interactúa en pantalla: botones, formularios, estilos, animaciones. Se ejecuta en el navegador.'],
                        ['Backend', 'Capa de Servidor / Lógica de Negocio', 'Parte del sistema que el usuario no ve: servidor, base de datos, lógica de negocio, envío de correos. Se ejecuta en el servidor.'],
                        ['Base de Datos', 'Database / DB', 'Sistema donde se almacena y organiza toda la información de la aplicación (usuarios, pedidos, configuraciones). Ej: MySQL, PostgreSQL.'],
                        ['Query', 'Consulta SQL', 'Instrucción que se envía a la base de datos para obtener, insertar, actualizar o eliminar información. Ej: SELECT, INSERT, UPDATE, DELETE.'],
                        ['Deploy / Despliegue', 'Deployment', 'Proceso de subir y activar una nueva versión del sistema en el servidor de producción (PROD). "Deployar" = publicar los cambios en vivo.'],
                        ['Entorno PROD', 'Production Environment', 'El servidor real donde corre la aplicación y los usuarios reales la usan. Los errores aquí tienen impacto directo.'],
                        ['Entorno Local', 'Local / Development Environment', 'El computador del desarrollador donde se prueba antes de subir a PROD. Los errores aquí no afectan a usuarios reales.'],
                        ['Debug / Depuración', 'Debugging', 'Proceso de encontrar y corregir errores en el código. "Debuggear" = investigar por qué algo falla.'],
                        ['Log / Registro', 'Log File', 'Archivo donde el sistema anota automáticamente eventos, errores y acciones. Es la "caja negra" que se revisa cuando algo falla.'],
                        ['Responsive', 'Diseño Responsivo', 'Característica de una web que adapta su diseño automáticamente a distintos tamaños de pantalla (celular, tablet, escritorio).'],
                        ['Nonce', 'Token de Seguridad WordPress', 'Código único y temporal que WordPress genera para validar que una solicitud proviene de un lugar autorizado. Previene ataques de falsificación.'],
                        ['SMTP', 'Simple Mail Transfer Protocol', 'Protocolo estándar para enviar correos electrónicos. La configuración SMTP define qué servidor se usa para mandar los emails del sistema.'],
                        ['Hook / Acción', 'WordPress Hook', 'Punto de enganche en WordPress que permite ejecutar código en momentos específicos del ciclo de vida (ej: al guardar un post, al cargar la página).'],
                        ['Workflow / Flujo', 'Automated Workflow', 'Secuencia automatizada de pasos que se ejecuta ante un evento (ej: cuando se crea un lead, automáticamente se envía un correo y se agenda una reunión).'],
                        ['n8n', 'Motor de Automatización', 'Herramienta de automatización de flujos de trabajo que conecta distintos sistemas y servicios sin necesidad de programar manualmente cada integración.'],
                    ];
                    foreach ($glosario as $g): ?>
                    <div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px 15px;" class="glosario-item">
                        <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:4px;">
                            <strong style="color:#0d9488;font-size:14px;"><?php echo esc_html($g[0]); ?></strong>
                            <span style="font-size:11px;color:#9ca3af;font-style:italic;"><?php echo esc_html($g[1]); ?></span>
                        </div>
                        <p style="margin:0;font-size:13px;color:#555;line-height:1.5;"><?php echo esc_html($g[2]); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox para evidencias -->
    <div class="qa-lightbox-bg" id="qaLightbox">
        <button class="qa-lightbox-close" onclick="closeLightbox()">&times;</button>
        <button class="qa-lightbox-nav prev" id="lbPrev" onclick="lbNav(-1)">&lsaquo;</button>
        <button class="qa-lightbox-nav next" id="lbNext" onclick="lbNav(1)">&rsaquo;</button>
        <div class="qa-lb-img-wrap" onclick="lbBgClick(event)">
            <img id="qaLightboxImg" src="" alt="Vista previa" onclick="event.stopPropagation()">
        </div>
        <div class="qa-lb-toolbar">
            <button onclick="lbZoom(-1)" title="Alejar">&#8722;</button>
            <button onclick="lbZoom(0)" title="Restablecer">&#8634;</button>
            <button onclick="lbZoom(1)" title="Acercar">&#43;</button>
            <span class="qa-lb-counter" id="lbCounter"></span>
        </div>
    </div>

    <script>
    (function(){
        const N = '<?php echo $nonce; ?>', A = '<?php echo admin_url("admin-ajax.php"); ?>';

        function toast(m,t){
            const e=document.getElementById('atQaToast');
            const icon = (t==='success') ? '✅ ' : (t==='error') ? '❌ ' : 'ℹ️ ';
            e.innerHTML = '<div style="font-size:28px;margin-bottom:6px;">' + ((t==='success')?'✅':'❌') + '</div>' + m;
            e.className='at-qa-toast show '+(t||'');
            setTimeout(()=>e.className='at-qa-toast',3500);
        }
        function esc(s){ if(!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

        /* Parseo seguro: ignora warnings/notices de PHP antes del JSON */
        function safeJson(r){
            return r.text().then(function(t){
                const start=t.indexOf('{'), end=t.lastIndexOf('}');
                if(start===-1||end===-1) return {success:false,data:'Respuesta inválida del servidor'};
                try{ return JSON.parse(t.substring(start,end+1)); }
                catch(e){ return {success:false,data:'Respuesta inválida del servidor'}; }
            });
        }

        /* Lightbox con navegación y zoom */
        var lbImages=[], lbCaptions=[], lbIndex=0, lbZoomLevel=1;
        function lbCollectImages(){
            lbImages=[]; lbCaptions=[];
            document.querySelectorAll('#evGrid .ev-card img[onclick]').forEach(function(img){
                var m=img.getAttribute('onclick').match(/openLightbox\('([^']+)'\)/);
                if(m){ lbImages.push(m[1]); lbCaptions.push(img.getAttribute('title')||''); }
            });
        }
        function lbUpdateNav(){
            var prev=document.getElementById('lbPrev'), next=document.getElementById('lbNext'), cnt=document.getElementById('lbCounter');
            if(lbImages.length<=1){ prev.style.display='none'; next.style.display='none'; }
            else { prev.style.display='flex'; next.style.display='flex'; }
            cnt.textContent=(lbIndex+1)+' / '+lbImages.length;
        }
        function lbUpdateCaption(){
            var cap=document.getElementById('lbCaption');
            if(!cap) return;
            cap.textContent=lbCaptions[lbIndex]||'';
            cap.style.display=lbCaptions[lbIndex]?'block':'none';
        }
        window.openLightbox = function(url){
            lbCollectImages();
            lbIndex=lbImages.indexOf(url); if(lbIndex<0) lbIndex=0;
            lbZoomLevel=1;
            var lb=document.getElementById('qaLightbox'), img=document.getElementById('qaLightboxImg');
            img.src=url; img.style.transform='scale(1)';
            lb.classList.add('active');
            lbUpdateNav(); lbUpdateCaption();
            document.addEventListener('keydown', lbKeys);
        };
        window.closeLightbox = function(){
            document.getElementById('qaLightbox').classList.remove('active');
            document.removeEventListener('keydown', lbKeys);
        };
        window.lbNav = function(dir){
            if(event) event.stopPropagation();
            var ni=lbIndex+dir;
            if(ni<0) ni=lbImages.length-1;
            if(ni>=lbImages.length) ni=0;
            lbIndex=ni;
            lbZoomLevel=1;
            var img=document.getElementById('qaLightboxImg');
            img.src=lbImages[lbIndex]; img.style.transform='scale(1)';
            lbUpdateNav(); lbUpdateCaption();
        };
        window.lbZoom = function(dir){
            if(event) event.stopPropagation();
            var img=document.getElementById('qaLightboxImg');
            if(!img) return;
            if(dir===0){ lbZoomLevel=1; }
            else { lbZoomLevel += dir * 0.3; }
            lbZoomLevel = Math.max(0.3, Math.min(lbZoomLevel, 5));
            img.style.transform='scale('+lbZoomLevel+')';
        };
        window.lbBgClick = function(e){
            if(e.target.classList.contains('qa-lb-img-wrap') || e.target.id==='qaLightbox') closeLightbox();
        };
        function lbKeys(e){
            if(e.key==='Escape') closeLightbox();
            else if(e.key==='ArrowLeft') lbNav(-1);
            else if(e.key==='ArrowRight') lbNav(1);
            else if(e.key==='+'||e.key==='=') lbZoom(1);
            else if(e.key==='-') lbZoom(-1);
        }
        // Mouse wheel zoom in lightbox
        document.getElementById('qaLightbox').addEventListener('wheel', function(e){
            if(!this.classList.contains('active')) return;
            e.preventDefault();
            lbZoom(e.deltaY < 0 ? 1 : -1);
        }, {passive: false});

        /* Descargar todas las evidencias */
        window.atQaDownloadAll = function(){
            var links=[];
            document.querySelectorAll('#evGrid .ev-card a[href], #evGrid .ev-card img[onclick]').forEach(function(el){
                if(el.tagName==='A') links.push(el.href);
                else { var m=el.getAttribute('onclick').match(/openLightbox\('([^']+)'\)/); if(m) links.push(m[1]); }
            });
            if(!links.length){ toast('No hay evidencias para descargar','error'); return; }
            links.forEach(function(url,i){
                setTimeout(function(){
                    var a=document.createElement('a'); a.href=url; a.download=''; a.target='_blank';
                    a.style.display='none'; document.body.appendChild(a); a.click(); document.body.removeChild(a);
                }, i*300);
            });
            toast('Descargando '+links.length+' archivo'+(links.length>1?'s':'')+'...','success');
        };

        window.atQaStatus = function(id,st,el){
            el.disabled=true;
            const fd=new FormData(); fd.append('action','at_qa_update_status'); fd.append('nonce',N); fd.append('case_db_id',id); fd.append('status',st);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                el.disabled=false;
                if(res.success){
                    el.className='st-sel status-'+st; toast('Actualizado','success');
                    const row=el.closest('tr'), td=row.querySelector('td:nth-child(2)');
                    let ti=td.querySelector('.tester-info');
                    if(!ti){ ti=document.createElement('div'); ti.className='tester-info'; td.appendChild(ti); }
                    ti.textContent = st!=='not_tested' ? res.data.tester+' · Ahora' : '';
                    setTimeout(()=>location.reload(),2500);
                } else toast('Error','error');
            }).catch(()=>{ el.disabled=false; toast('Error de conexión','error'); });
        };

        window.atQaDetail = function(id){
            document.getElementById('caseModal').classList.add('active');
            document.getElementById('cmBody').innerHTML='<div style="text-align:center;padding:30px"><div class="qa-spin"></div></div>';
            const fd=new FormData(); fd.append('action','at_qa_get_case_detail'); fd.append('nonce',N); fd.append('case_db_id',id);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success) renderDetail(res.data,id); else document.getElementById('cmBody').innerHTML='<p style="color:red">Error</p>';
            });
        };
        window.atQaCloseDetail = function(){ document.getElementById('caseModal').classList.remove('active'); };
        document.getElementById('caseModal').addEventListener('click',function(e){ if(e.target===this) atQaCloseDetail(); });
        document.addEventListener('keydown',e=>{ if(e.key==='Escape') atQaCloseDetail(); });

        function renderDetail(d,cid){
            const c=d.case;
            document.getElementById('cmTitle').textContent=c.case_id+' — '+c.title;
            let h='';

            // Info
            h+='<div class="qa-detail-section"><h4>📋 Información</h4>';
            h+='<div class="qa-detail-grid">';
            h+='<div class="qa-detail-item"><label>ID</label><p><strong>'+esc(c.case_id)+'</strong></p></div>';
            h+='<div class="qa-detail-item"><label>Sección</label><p>'+esc(c.section)+'</p></div>';
            h+='<div class="qa-detail-item"><label>Prioridad</label><p><span class="badge badge-'+c.priority.toLowerCase()+'">'+esc(c.priority)+'</span></p></div>';
            h+='<div class="qa-detail-item"><label>Estado</label><p>';
            h+='<select class="st-sel status-'+c.status+'" onchange="atQaStatusModal('+c.id+',this.value,this)">';
            ['not_tested','pass','fail','blocked','skipped'].forEach(s=>{
                const lbl={'not_tested':'🔘 Sin probar','pass':'✅ PASS','fail':'❌ FAIL','blocked':'⚠️ BLOQUEADO','skipped':'⏭️ OMITIDO'};
                h+='<option value="'+s+'"'+(c.status===s?' selected':'')+'>'+lbl[s]+'</option>';
            });
            h+='</select></p></div></div>';

            if(c.precondition) h+='<div class="qa-detail-item" style="margin-top:10px"><label>Precondición</label><p>'+esc(c.precondition)+'</p></div>';
            if(c.steps) h+='<div class="qa-detail-item" style="margin-top:10px"><label>Pasos</label><p>'+esc(c.steps).replace(/(\d+\.)/g,'<br>$1')+'</p></div>';
            if(c.expected_result) h+='<div class="qa-detail-item" style="margin-top:10px"><label>Resultado Esperado</label><p style="color:#065f46;background:#ecfdf5;padding:8px 10px;border-radius:6px;">'+esc(c.expected_result)+'</p></div>';
            if(c.tester) h+='<div class="qa-detail-item" style="margin-top:10px"><label>Probado por</label><p>'+esc(c.tester)+(c.tested_at?' — '+c.tested_at:'')+'</p></div>';

            // Bug ID
            h+='<div class="qa-detail-item" style="margin-top:10px"><label>Bug ID / Ticket</label>';
            h+='<input type="text" id="bugIdInput" value="'+esc(c.bug_id||'')+'" placeholder="Ej: BUG-042, JIRA-123..." style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;" onchange="atQaSaveBug('+c.id+',this.value)">';
            h+='</div></div>';

            // Evidencias
            h+='<div class="qa-detail-section"><h4>📎 Evidencias ('+d.evidence.length+')';
            if(d.evidence.length>0) h+=' <button class="qa-download-all" onclick="atQaDownloadAll()" title="Descargar todas">⬇️ Descargar todo</button>';
            h+='</h4>';
            h+='<div class="evidence-grid" id="evGrid">';
            d.evidence.forEach(ev=>{ h+=evCard(ev); });
            if(!d.evidence.length) h+='<p style="color:#999;grid-column:1/-1;">Sin evidencias</p>';
            h+='</div>';
            h+='<div class="qa-upload-area" id="qaUpArea" onclick="document.getElementById(\'qaFI\').click()">📤 <strong>Click aquí</strong> o arrastra archivos<br><small style="color:#999">JPG, PNG, GIF, WEBP, MP4, WEBM, PDF — Máx 10MB</small></div>';
            h+='<input type="file" id="qaFI" style="display:none" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.pdf" onchange="atQaPreview(this)">';
            h+='<div id="qaUpPreview" style="display:none;margin-top:10px;border:1px solid #d1d5db;border-radius:8px;padding:12px;background:#f8fafc;">';
            h+='<div style="display:flex;gap:12px;align-items:flex-start;">';
            h+='<div id="qaUpThumb" style="flex-shrink:0;width:100px;height:80px;border-radius:6px;overflow:hidden;background:#e5e7eb;display:flex;align-items:center;justify-content:center;"></div>';
            h+='<div style="flex:1;min-width:0;">';
            h+='<div id="qaUpFileName" style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>';
            h+='<input type="text" id="evDesc" placeholder="Descripción (opcional)" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;">';
            h+='<div style="display:flex;gap:8px;margin-top:8px;">';
            h+='<button class="qa-btn qa-btn-primary" onclick="atQaUpload('+cid+')" style="font-size:12px;padding:5px 16px;">📤 Subir evidencia</button>';
            h+='<button class="qa-btn" onclick="atQaCancelPreview()" style="font-size:12px;padding:5px 12px;background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;">Cancelar</button>';
            h+='</div></div></div></div>';
            h+='</div>';

            // Comentarios
            h+='<div class="qa-detail-section"><h4>💬 Comentarios ('+d.comments.length+')</h4>';
            h+='<div class="comment-list" id="cmList">';
            d.comments.forEach(cm=>{ h+=cmItem(cm); });
            if(!d.comments.length) h+='<p style="color:#999" id="noCm">Sin comentarios</p>';
            h+='</div>';
            h+='<div class="cm-form"><textarea id="newCm" placeholder="Escribe un comentario... (Ctrl+Enter para enviar)"></textarea>';
            h+='<button id="cmSendBtn" class="qa-btn qa-btn-primary" onclick="atQaComment('+cid+')" style="height:auto;">Enviar</button></div></div>';

            document.getElementById('cmBody').innerHTML=h;
            setupDrop(cid);
        }

        function evCard(ev){
            let h='<div class="ev-card" id="ev-'+ev.id+'">';
            h+='<button class="ev-del" onclick="atQaDelEv('+ev.id+')">&times;</button>';
            if(ev.file_type&&ev.file_type.startsWith('image/'))
                h+='<img src="'+esc(ev.file_url)+'" alt="" onclick="openLightbox(\''+esc(ev.file_url)+'\')">'; 
            else if(ev.file_type&&ev.file_type.startsWith('video/'))
                h+='<div class="ev-file-icon"><a href="'+esc(ev.file_url)+'" target="_blank">🎬</a></div>';
            else
                h+='<div class="ev-file-icon"><a href="'+esc(ev.file_url)+'" target="_blank">📄</a></div>';
            h+='<div class="ev-info"><div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(ev.file_name)+'">'+esc(ev.file_name)+'</div>';
            if(ev.description) h+='<div style="color:#666">'+esc(ev.description)+'</div>';
            h+='<div style="color:#999">'+esc(ev.user_name||'')+'</div></div></div>';
            return h;
        }

        function cmItem(cm){
            let h='<div class="cm-item" id="cm-'+cm.id+'">';
            h+='<button class="cm-del" onclick="atQaDelCm('+cm.id+')">&times;</button>';
            h+='<button class="cm-edit" onclick="atQaEditCm('+cm.id+')" title="Editar">✏️</button>';
            h+='<div class="cm-meta"><strong>'+esc(cm.user_name||'')+'</strong> · '+esc(cm.created_at);
            if(cm.updated_at) h+=' · <em style="color:#0d9488">editado</em>';
            h+='</div>';
            h+='<p class="cm-text" id="cmText-'+cm.id+'">'+esc(cm.comment)+'</p></div>';
            return h;
        }

        window.atQaStatusModal = function(id,st,el){
            const stLabels={'not_tested':'Sin probar','pass':'✅ PASS','fail':'❌ FAIL','blocked':'⚠️ Bloqueado','skipped':'⏭️ Omitido'};
            const prevClass=el.className, prevVal=el.value;
            el.disabled=true; el.style.opacity='0.6';
            const fd=new FormData(); fd.append('action','at_qa_update_status'); fd.append('nonce',N); fd.append('case_db_id',id); fd.append('status',st);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                el.disabled=false; el.style.opacity='';
                if(res.success){
                    // Actualizar select en modal
                    el.className='st-sel status-'+st;
                    // Actualizar fila en la grilla de fondo
                    const row=document.querySelector('tr[data-cid="'+id+'"]');
                    if(row){ const s=row.querySelector('.st-sel'); if(s){ s.value=st; s.className='st-sel status-'+st; }}
                    // Feedback visual inline junto al select
                    const wrap=el.closest('.qa-detail-item')||el.parentNode;
                    const old=wrap.querySelector('.st-inline-ok'); if(old) old.remove();
                    const ok=document.createElement('span');
                    ok.className='st-inline-ok';
                    ok.textContent='✔ Guardado';
                    ok.style.cssText='display:inline-block;margin-left:8px;color:#059669;font-size:11px;font-weight:700;animation:qaspin 0s;';
                    el.parentNode.appendChild(ok);
                    setTimeout(()=>{ if(ok.parentNode) ok.remove(); }, 3000);
                    toast('Estado actualizado: '+(stLabels[st]||st),'success');
                } else {
                    el.className=prevClass; el.value=prevVal;
                    toast(res.data||'Error al actualizar estado','error');
                }
            }).catch(()=>{
                el.disabled=false; el.style.opacity='';
                el.className=prevClass; el.value=prevVal;
                toast('Error de conexión','error');
            });
        };

        window.atQaSaveBug = function(id,val){
            const fd=new FormData(); fd.append('action','at_qa_update_bug_id'); fd.append('nonce',N); fd.append('case_db_id',id); fd.append('bug_id',val);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success) toast('Bug ID guardado','success');
            });
        };

        /* Preview de archivo antes de subir */
        window.atQaPreview = function(inp){
            var f=inp.files[0]; if(!f) return;
            if(f.size>10*1024*1024){ toast('Máx 10MB','error'); inp.value=''; return; }
            var prev=document.getElementById('qaUpPreview'), thumb=document.getElementById('qaUpThumb'), fname=document.getElementById('qaUpFileName');
            fname.textContent=f.name;
            thumb.innerHTML='';
            if(f.type.startsWith('image/')){
                var img=document.createElement('img'); img.style.cssText='width:100%;height:100%;object-fit:cover;';
                img.src=URL.createObjectURL(f); thumb.appendChild(img);
            } else if(f.type.startsWith('video/')){
                thumb.innerHTML='<span style="font-size:32px;">🎬</span>';
            } else {
                thumb.innerHTML='<span style="font-size:32px;">📄</span>';
            }
            prev.style.display='block';
            document.getElementById('qaUpArea').style.display='none';
            document.getElementById('evDesc').value='';
            document.getElementById('evDesc').focus();
        };
        window.atQaCancelPreview = function(){
            document.getElementById('qaUpPreview').style.display='none';
            document.getElementById('qaUpArea').style.display='';
            document.getElementById('qaFI').value='';
        };
        window.atQaUpload = function(cid){
            var inp=document.getElementById('qaFI');
            var f=inp.files[0]; if(!f){ toast('Selecciona un archivo','error'); return; }
            var fd=new FormData(); fd.append('action','at_qa_upload_evidence'); fd.append('nonce',N); fd.append('case_db_id',cid); fd.append('evidence_file',f);
            fd.append('description', document.getElementById('evDesc')?.value||'');
            var prev=document.getElementById('qaUpPreview');
            prev.innerHTML='<div style="text-align:center;padding:10px;"><div class="qa-spin"></div> Subiendo...</div>';
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success){ toast('Evidencia subida','success'); atQaDetail(cid); }
                else { toast(res.data||'Error','error'); atQaCancelPreview(); }
            }).catch(()=>{ toast('Error de conexión','error'); atQaCancelPreview(); });
            inp.value='';
        };

        function setupDrop(cid){
            var a=document.getElementById('qaUpArea'); if(!a) return;
            ['dragenter','dragover'].forEach(e=>a.addEventListener(e,ev=>{ev.preventDefault();a.classList.add('dragover');}));
            ['dragleave','drop'].forEach(e=>a.addEventListener(e,ev=>{ev.preventDefault();a.classList.remove('dragover');}));
            a.addEventListener('drop',e=>{
                var f=e.dataTransfer.files[0]; if(f){ var inp=document.getElementById('qaFI'); var dt=new DataTransfer(); dt.items.add(f); inp.files=dt.files; atQaPreview(inp); }
            });
        }

        window.atQaComment = function(cid){
            const ta=document.getElementById('newCm'), txt=ta.value.trim();
            if(!txt){ toast('Escribe un comentario','error'); return; }
            const btn=document.getElementById('cmSendBtn');
            if(btn){ btn.disabled=true; btn.innerHTML='<span style="display:inline-flex;align-items:center;gap:6px;"><svg style="animation:qaspin .6s linear infinite;width:15px;height:15px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10" stroke-opacity="0.3"/><path d="M12 2a10 10 0 0 1 10 10" /></svg>Enviando...</span>'; }
            const fd=new FormData(); fd.append('action','at_qa_add_comment'); fd.append('nonce',N); fd.append('case_db_id',cid); fd.append('comment',txt);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success){
                    const list=document.getElementById('cmList');
                    const nc=document.getElementById('noCm'); if(nc) nc.remove();
                    list.insertAdjacentHTML('beforeend',cmItem(res.data));
                    ta.value=''; list.scrollTop=list.scrollHeight; toast('Comentario agregado','success');
                    setTimeout(()=>location.reload(),2500);
                } else {
                    if(btn){ btn.disabled=false; btn.innerHTML='Enviar'; }
                    toast(res.data||'Error','error');
                }
            }).catch(()=>{
                if(btn){ btn.disabled=false; btn.innerHTML='Enviar'; }
                toast('Error de conexión','error');
            });
        };

        window.atQaDelEv = function(id){
            if(!confirm('¿Eliminar evidencia?')) return;
            const fd=new FormData(); fd.append('action','at_qa_delete_evidence'); fd.append('nonce',N); fd.append('evidence_id',id);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{ if(res.success){ const el=document.getElementById('ev-'+id); if(el) el.remove(); toast('Eliminada','success'); }});
        };

        window.atQaEditCm = function(id){
            const container=document.getElementById('cm-'+id);
            const textEl=document.getElementById('cmText-'+id);
            if(!textEl||!container) return;
            const original=textEl.textContent;
            textEl.style.display='none';
            // Insertar textarea y botones
            let editDiv=document.createElement('div'); editDiv.id='cmEditWrap-'+id;
            editDiv.innerHTML='<textarea class="cm-edit-area" id="cmEditTA-'+id+'">'+esc(original)+'</textarea>'
                +'<div class="cm-edit-actions"><button class="cm-cancel-btn" onclick="atQaCancelEdit('+id+')">Cancelar</button>'
                +'<button class="cm-save-btn" onclick="atQaSaveCm('+id+')">💾 Guardar</button></div>';
            textEl.parentNode.insertBefore(editDiv,textEl.nextSibling);
            document.getElementById('cmEditTA-'+id).focus();
        };
        window.atQaCancelEdit = function(id){
            const wrap=document.getElementById('cmEditWrap-'+id);
            if(wrap) wrap.remove();
            const textEl=document.getElementById('cmText-'+id);
            if(textEl) textEl.style.display='';
        };
        window.atQaSaveCm = function(id){
            const ta=document.getElementById('cmEditTA-'+id);
            const txt=ta.value.trim();
            if(!txt){ toast('El comentario no puede estar vacío','error'); return; }
            const fd=new FormData(); fd.append('action','at_qa_update_comment'); fd.append('nonce',N); fd.append('comment_id',id); fd.append('comment',txt);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success){
                    const textEl=document.getElementById('cmText-'+id);
                    textEl.textContent=res.data.comment; textEl.style.display='';
                    const wrap=document.getElementById('cmEditWrap-'+id);
                    if(wrap) wrap.remove();
                    // Añadir indicador "editado" si no existe
                    const meta=document.querySelector('#cm-'+id+' .cm-meta');
                    if(meta && !meta.querySelector('em')) meta.insertAdjacentHTML('beforeend',' · <em style="color:#0d9488">editado</em>');
                    toast('Comentario actualizado','success');
                } else toast(res.data||'Error al actualizar','error');
            });
        };

        window.atQaDelCm = function(id){
            if(!confirm('¿Eliminar comentario?')) return;
            const fd=new FormData(); fd.append('action','at_qa_delete_comment'); fd.append('nonce',N); fd.append('comment_id',id);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{ if(res.success){ const el=document.getElementById('cm-'+id); if(el) el.remove(); toast('Eliminado','success'); }});
        };

        /* Asignar tester a módulo */
        window.atQaAssignTester = function(moduleId, testerId){
            const fd=new FormData();
            fd.append('action','at_qa_assign_module_tester');
            fd.append('nonce',N);
            fd.append('module_id',moduleId);
            fd.append('tester_id',testerId);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success){
                    const d = res.data;
                    if(!testerId || testerId==='0'){
                        toast('Tester desasignado','success');
                        return;
                    }
                    // Mostrar modal de confirmación
                    let modal = document.getElementById('testerAssignModal');
                    if(!modal){
                        modal = document.createElement('div');
                        modal.id = 'testerAssignModal';
                        modal.className = 'at-qa-modal-bg';
                        modal.innerHTML = `
                            <div class="at-qa-modal" style="max-width:520px;">
                                <div class="at-qa-modal-hd" style="background:linear-gradient(135deg,#0d9488,#14b8a6);">
                                    <h3 style="color:#fff;margin:0;">✅ Tester Asignado Exitosamente</h3>
                                    <button class="at-qa-modal-close" style="color:#fff;" onclick="document.getElementById('testerAssignModal').classList.remove('active')">&times;</button>
                                </div>
                                <div class="at-qa-modal-body" id="testerAssignBody" style="padding:24px;"></div>
                            </div>`;
                        modal.addEventListener('click', function(e){ if(e.target===modal) modal.classList.remove('active'); });
                        document.body.appendChild(modal);
                    }
                    let html = '';
                    html += '<div style="text-align:center;margin-bottom:20px;"><span style="font-size:48px;">🧪</span></div>';
                    html += '<div style="background:#f0fdfa;border-left:4px solid #0d9488;padding:16px;border-radius:8px;margin-bottom:16px;">';
                    html += '<p style="margin:0 0 8px;"><strong>👤 Tester:</strong> ' + esc(d.tester_name) + '</p>';
                    if(d.tester_email) html += '<p style="margin:0 0 8px;"><strong>📧 Email:</strong> ' + esc(d.tester_email) + '</p>';
                    if(d.module_name)  html += '<p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' + esc(d.module_name) + '</p>';
                    if(d.project_name) html += '<p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' + esc(d.project_name) + '</p>';
                    html += '<p style="margin:0 0 8px;"><strong>👤 Asignado por:</strong> ' + esc(d.assigned_by) + '</p>';
                    html += '<p style="margin:0;"><strong>📅 Fecha:</strong> ' + esc(d.date) + '</p>';
                    html += '</div>';
                    // Estado del correo
                    if(d.email_sent){
                        html += '<div style="background:#ecfdf5;border:1px solid #10b981;padding:12px;border-radius:8px;text-align:center;margin-bottom:16px;">';
                        html += '<span style="font-size:20px;">📨</span> <strong style="color:#065f46;">Correo de notificación enviado</strong><br>';
                        html += '<small style="color:#6b7280;">Se envió un email a ' + esc(d.tester_email) + ' con los detalles de la asignación</small>';
                        html += '</div>';
                    } else if(d.tester_email) {
                        html += '<div style="background:#fef3c7;border:1px solid #f59e0b;padding:12px;border-radius:8px;text-align:center;margin-bottom:16px;">';
                        html += '<span style="font-size:20px;">⚠️</span> <strong style="color:#92400e;">No se pudo enviar el correo</strong><br>';
                        html += '<small style="color:#6b7280;">Verifica la configuración SMTP del servidor</small>';
                        html += '</div>';
                    }
                    html += '<div style="text-align:center;margin-top:20px;">';
                    html += '<button class="qa-btn qa-btn-primary" onclick="document.getElementById(\'testerAssignModal\').classList.remove(\'active\')">Entendido</button>';
                    html += '</div>';
                    document.getElementById('testerAssignBody').innerHTML = html;
                    modal.classList.add('active');
                } else {
                    toast(res.data||'Error al asignar tester','error');
                }
            }).catch(err=>{
                console.error('QA Assign Tester error:', err);
                toast('Error de conexión al asignar tester','error');
            });
        };

        /* Generar informe QA (HTML + PDF) */
        window.atQaLastPdf = window.atQaLastPdf || {};
        window.atQaGenerateReport = function(pid){
            if(!confirm('¿Generar informe formal (HTML + PDF) del proyecto?')) return;
            const fd=new FormData();
            fd.append('action','at_qa_generate_report');
            fd.append('nonce',N);
            fd.append('project_id',pid);
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success){
                    toast('Informe generado','success');
                    window.atQaLastPdf[pid] = res.data.pdf_filename || '';
                    if(res.data.url) window.open(res.data.url,'_blank');
                    if(res.data.pdf_url) window.open(res.data.pdf_url,'_blank');
                } else {
                    toast(res.data||'Error generando informe','danger');
                }
            });
        };

        /* Enviar el último informe QA por correo al cliente (BCC admin) */
        window.atQaSendReportEmail = function(pid){
            let pdfFile = window.atQaLastPdf[pid];
            if(!pdfFile){
                if(!confirm('No hay informe generado en esta sesión. ¿Generarlo ahora?')) return;
                return atQaGenerateReport(pid);
            }
            const toEmail = prompt('Email destinatario (vacío = email del cliente vinculado):','');
            if(toEmail===null) return;
            const customMsg = prompt('Mensaje adicional (opcional):','') || '';
            if(!confirm('¿Enviar el informe por correo?\n\nDestino: '+(toEmail||'cliente del proyecto')+'\nPDF: '+pdfFile)) return;
            const fd=new FormData();
            fd.append('action','at_qa_send_report_email');
            fd.append('nonce',N);
            fd.append('project_id',pid);
            fd.append('pdf_filename',pdfFile);
            if(toEmail) fd.append('to_email',toEmail);
            if(customMsg) fd.append('custom_message',customMsg);
            toast('Enviando correo...','');
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success) toast('📧 Correo enviado a '+res.data.to,'success');
                else toast(res.data||'Error enviando correo','danger');
            }).catch(err=>{ toast('Error: '+err.message,'danger'); });
        };

        /* Generar informe de errores .md */
        window.atQaGenerateErrorReport = function(pid){
            if(!confirm('¿Generar informe de errores (.md) del proyecto?')) return;
            const fd=new FormData();
            fd.append('action','at_qa_generate_error_report');
            fd.append('nonce',N);
            fd.append('project_id',pid);
            toast('Generando informe de errores...','');
            fetch(A,{method:'POST',body:fd}).then(r=>safeJson(r)).then(res=>{
                if(res.success){
                    const msg = res.data.total_errors===0
                        ? '✅ Sin errores — '+res.data.pass_rate+'% Pass Rate'
                        : '📋 '+res.data.total_errors+' error(es) — '+res.data.pass_rate+'% Pass Rate';
                    toast(msg,'success');
                    if(res.data.url){
                        const a=document.createElement('a');
                        a.href=res.data.url;
                        a.download=res.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }
                } else {
                    toast(res.data||'Error generando informe','danger');
                }
            });
        };

        document.addEventListener('keydown',e=>{ if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){ const ta=document.getElementById('newCm'); if(ta&&document.activeElement===ta){ const btn=ta.closest('.cm-form').querySelector('.qa-btn-primary'); if(btn) btn.click(); }}});

        /* Mover chatbot cuando se interactúa con el selector de tester */
        const testerSel = document.getElementById('modTesterSelect');
        if (testerSel) {
            testerSel.addEventListener('focus', function(){ document.body.classList.add('at-qa-chatbot-hidden'); });
            testerSel.addEventListener('blur', function(){ setTimeout(()=>{ document.body.classList.remove('at-qa-chatbot-hidden'); }, 300); });
            testerSel.addEventListener('mousedown', function(){ document.body.classList.add('at-qa-chatbot-hidden'); });
        }

        /* Filtro buscador del Glosario */
        window.filtrarGlosario = function(q){
            const items = document.querySelectorAll('#glosarioGrid .glosario-item');
            const term = q.toLowerCase().trim();
            let found = 0;
            items.forEach(function(el){
                const txt = el.textContent.toLowerCase();
                const show = !term || txt.indexOf(term) !== -1;
                el.style.display = show ? '' : 'none';
                if(show) found++;
            });
            let noRes = document.getElementById('glosarioNoRes');
            if(!noRes){
                noRes = document.createElement('p');
                noRes.id = 'glosarioNoRes';
                noRes.style.cssText = 'text-align:center;color:#9ca3af;padding:20px 0;grid-column:1/-1;';
                noRes.textContent = 'No se encontraron términos.';
                document.getElementById('glosarioGrid').appendChild(noRes);
            }
            noRes.style.display = (found === 0 && term) ? '' : 'none';
        };
        document.querySelector('#modalGlosario .at-qa-modal-close').addEventListener('click', function(){
            const inp = document.getElementById('glosarioBuscar');
            if(inp){ inp.value=''; filtrarGlosario(''); }
        });

        /* Auto-scroll módulo activo al centro en mobile */
        const activeMod = document.querySelector('.qa-mod-list a.active');
        if (activeMod && window.innerWidth <= 768) {
            setTimeout(function(){
                activeMod.scrollIntoView({ behavior:'smooth', inline:'center', block:'nearest' });
            }, 100);
        }
    })();
    </script>
    </div>
    <?php
}

// ══════════════════════════════════════════════
// 12. PÁGINA DE IMPORTACIÓN
// ══════════════════════════════════════════════
function at_qa_render_import_page() {
    if (!current_user_can('manage_options')) wp_die('Acceso denegado');

    global $wpdb;
    $t = at_qa_table_names();
    $message = '';

    // Procesar importación
    if (isset($_POST['qa_import_action']) && wp_verify_nonce($_POST['qa_import_nonce'], 'at_qa_import')) {
        $project_id = intval($_POST['project_id']);
        $md_path    = sanitize_text_field($_POST['md_path']);

        if (!$project_id) {
            $message = '<div class="notice notice-error"><p>Selecciona un proyecto.</p></div>';
        } elseif (empty($md_path) || !is_dir(ABSPATH . $md_path)) {
            $message = '<div class="notice notice-error"><p>Ruta no encontrada: <code>' . esc_html(ABSPATH . $md_path) . '</code></p></div>';
        } else {
            $full_path = ABSPATH . $md_path;
            $files = at_qa_detect_md_files($full_path);
            if (empty($files)) {
                $message = '<div class="notice notice-error"><p>No se encontraron archivos QA-*.md en la ruta.</p></div>';
            } else {
                $imported = at_qa_import_project_from_md($project_id, $full_path, $files);
                // Guardar md_base_path en el proyecto
                $wpdb->update($t['projects'], ['md_base_path' => $md_path], ['id' => $project_id]);
                $message = '<div class="notice notice-success"><p>✅ Importación completada. <strong>' . $imported . '</strong> casos nuevos importados de <strong>' . count($files) . '</strong> archivos.</p></div>';
            }
        }
    }

    $projects = $wpdb->get_results("SELECT * FROM {$t['projects']} ORDER BY name ASC");

    // Escanear carpeta Clientes/ para detectar QA
    $clients_dir = ABSPATH . 'Clientes';
    $detected_paths = [];
    if (is_dir($clients_dir)) {
        foreach (glob($clients_dir . '/*/QA') as $qa_dir) {
            $rel = str_replace(ABSPATH, '', $qa_dir) . '/';
            $md_count = count(at_qa_detect_md_files($qa_dir));
            if ($md_count > 0) {
                $detected_paths[] = ['path' => $rel, 'files' => $md_count, 'name' => basename(dirname($qa_dir))];
            }
        }
    }

    ?>
    <div class="wrap">
        <div style="background:linear-gradient(135deg,#0d9488,#14b8a6,#2dd4bf); color:#fff; padding:24px 30px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 20px rgba(13,148,136,.3);">
            <h1 style="margin:0; color:#fff; font-size:26px;">📥 Importar Casos QA</h1>
            <p style="margin:4px 0 0; opacity:.85; font-size:13px;">Importa casos de prueba desde archivos Markdown a tus proyectos QA</p>
        </div>
        <?php echo $message; ?>

        <div class="card" style="max-width:750px; padding:20px;">
            <h2>Importar desde archivos Markdown</h2>
            <p>Selecciona el proyecto QA destino y la ruta donde se encuentran los archivos <code>QA-*.md</code>.</p>
            <p><strong>Nota:</strong> Los casos existentes se actualizan sin perder resultados ni evidencias.</p>

            <form method="post">
                <?php wp_nonce_field('at_qa_import', 'qa_import_nonce'); ?>
                <input type="hidden" name="qa_import_action" value="1">

                <p><label><strong>Proyecto QA destino:</strong></label><br>
                <select name="project_id" style="width:100%; padding:8px;" required>
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?php echo $p->id; ?>" <?php if ($p->md_base_path) echo 'data-path="'.esc_attr($p->md_base_path).'"'; ?>>
                        <?php echo esc_html($p->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                </p>

                <?php if (empty($projects)): ?>
                <div class="notice notice-warning inline"><p>No hay proyectos QA. <a href="<?php echo admin_url('admin.php?page=at-qa'); ?>">Crea uno primero</a>.</p></div>
                <?php endif; ?>

                <p><label><strong>Ruta a los archivos MD (relativa a la raíz de WordPress):</strong></label><br>
                <input type="text" name="md_path" id="importMdPath" style="width:100%; padding:8px;" placeholder="Ej: Clientes/PetsGO/QA/" required>
                <small style="color:#888;">Ruta relativa desde la raíz del sitio (ej: <code>Clientes/PetsGO/QA/</code>)</small>
                </p>

                <p><button type="submit" class="button button-primary button-hero" onclick="return confirm('¿Importar/actualizar casos?')">📥 Importar Casos de Prueba</button></p>
            </form>
        </div>

        <?php if (!empty($detected_paths)): ?>
        <div class="card" style="max-width:750px; padding:20px; margin-top:20px;">
            <h2>📂 Carpetas QA detectadas</h2>
            <table class="widefat striped">
                <thead><tr><th>Cliente</th><th>Ruta</th><th>Archivos MD</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($detected_paths as $dp): ?>
                <tr>
                    <td><strong><?php echo esc_html($dp['name']); ?></strong></td>
                    <td><code><?php echo esc_html($dp['path']); ?></code></td>
                    <td><?php echo $dp['files']; ?> archivos</td>
                    <td><button class="button button-small" onclick="document.getElementById('importMdPath').value='<?php echo esc_attr($dp['path']); ?>'">Usar esta ruta</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <script>
        document.querySelector('select[name=project_id]')?.addEventListener('change', function(){
            const opt = this.options[this.selectedIndex];
            const path = opt.getAttribute('data-path');
            if (path) document.getElementById('importMdPath').value = path;
        });
        </script>
    </div>
    <?php
}
// ══════════════════════════════════════════════
// AGENTE QA AUTOMATIZADO — API Key Auth
// Permite peticiones desde Playwright/Node.js
// sin requerir nonce de navegador
// ══════════════════════════════════════════════
define('AT_QA_AGENT_KEY', 'petsgo-qa-agent-2026');

function at_qa_verify_agent() {
    $key = $_POST['agent_key'] ?? $_SERVER['HTTP_X_QA_AGENT_KEY'] ?? '';
    return $key === AT_QA_AGENT_KEY;
}

// Obtener detalle de caso (para agente)
add_action('wp_ajax_nopriv_at_qa_agent_get_case', function() {
    if (!at_qa_verify_agent()) wp_send_json_error('Unauthorized', 403);
    global $wpdb;
    $t = at_qa_table_names();
    $cid = intval($_POST['case_db_id']);
    $case = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['cases']} WHERE id = %d", $cid));
    if (!$case) wp_send_json_error('Caso no encontrado');
    $evidence = $wpdb->get_results($wpdb->prepare(
        "SELECT e.*, u.display_name as user_name FROM {$t['evidence']} e LEFT JOIN {$wpdb->users} u ON e.uploaded_by = u.ID WHERE e.case_id = %d ORDER BY e.created_at DESC", $cid
    ));
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name as user_name FROM {$t['comments']} c LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID WHERE c.case_id = %d ORDER BY c.created_at ASC", $cid
    ));
    wp_send_json_success(['case' => $case, 'evidence' => $evidence, 'comments' => $comments]);
});

// Actualizar estado (para agente) — notifica SOLO cuando pasa a PASS
add_action('wp_ajax_nopriv_at_qa_agent_update_status', function() {
    if (!at_qa_verify_agent()) wp_send_json_error('Unauthorized', 403);
    global $wpdb;
    $t = at_qa_table_names();

    $cid    = intval($_POST['case_db_id']);
    $status = sanitize_text_field($_POST['status']);
    $tester = sanitize_text_field($_POST['tester'] ?? 'Agente QA Automatizado — Playwright');
    $valid  = ['not_tested','pass','fail','blocked','skipped'];
    if (!in_array($status, $valid)) wp_send_json_error('Estado inválido');

    // Obtener estado anterior
    $old_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$t['cases']} WHERE id = %d", $cid
    ));

    // Actualizar en BD
    $updated = $wpdb->update($t['cases'], [
        'status'    => $status,
        'tester'    => $tester,
        'tested_at' => current_time('mysql'),
    ], ['id' => $cid]);

    if ($updated === false) {
        wp_send_json_error('Error al actualizar en BD: ' . $wpdb->last_error);
    }

    // ─── Notificaciones: solo cuando el estado CAMBIA a PASS ───
    if ($old_status !== $status && $status === 'pass') {
      try {
        $ctx = at_qa_get_context($cid);
        if ($ctx) {
            $project_name = $ctx->project ? $ctx->project->name : 'N/A';
            $module_name  = $ctx->caso->module_name;
            $case_name    = $ctx->caso->title ?? $ctx->caso->case_id ?? 'Caso #' . $cid;
            $client_name  = $ctx->client ? ($ctx->client->empresa ?: $ctx->client->nombre) : '';
            $qa_url       = admin_url('admin.php?page=at-qa&view=suite&project=' . ($ctx->project ? $ctx->project->id : ''));

            // Progreso global del proyecto
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d",
                $ctx->project->id
            ));
            $passed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d AND c.status='pass'",
                $ctx->project->id
            ));
            $pct = $total > 0 ? round(($passed / $total) * 100) : 0;

            // Cuerpo del correo — estilo idéntico al manual
            $body_content = '
      <p>El <strong>🤖 Agente QA Automatizado (Playwright)</strong> ha verificado exitosamente un caso de prueba:</p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
        <p style="margin:0 0 8px;"><strong>🔬 Caso:</strong> ' . esc_html($case_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📊 Resultado:</strong> <span class="badge-pass">✅ PASS</span></p>
        <p style="margin:0 0 8px;"><strong>🤖 Ejecutado por:</strong> ' . esc_html($tester) . '</p>
        <p style="margin:0;"><strong>📅 Fecha:</strong> ' . current_time('d/m/Y H:i') . '</p>
      </div>';

            $body_content .= '
      <div style="margin:15px 0;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Progreso general: <strong>' . $passed . '/' . $total . '</strong> casos aprobados (<strong>' . $pct . '%</strong>)</p>
        <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,#0d9488,#14b8a6);width:' . $pct . '%;height:100%;border-radius:6px;"></div>
        </div>
      </div>';

            // ─── Últimos 3 comentarios del caso ───
            $last_comments = $wpdb->get_results($wpdb->prepare(
                "SELECT c.comment, c.created_at, COALESCE(u.display_name, 'Agente QA') as user_name
                 FROM {$t['comments']} c
                 LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                 WHERE c.case_id = %d
                 ORDER BY c.created_at DESC LIMIT 3", $cid
            ));
            if (!empty($last_comments)) {
                $body_content .= '
      <div style="margin:20px 0 10px;">
        <p style="margin:0 0 10px;font-size:14px;font-weight:bold;color:#374151;">💬 Últimos comentarios</p>';
                foreach (array_reverse($last_comments) as $cmt) {
                    $cmt_date = date('d/m/Y H:i', strtotime($cmt->created_at));
                    $body_content .= '
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:8px 0;">
          <p style="margin:0 0 4px;font-size:11px;color:#64748b;"><strong>' . esc_html($cmt->user_name) . '</strong> — ' . $cmt_date . '</p>
          <p style="margin:0;font-size:13px;line-height:1.4;">' . nl2br(esc_html($cmt->comment)) . '</p>
        </div>';
                }
                $body_content .= '
      </div>';
            }

            // ─── Evidencias adjuntas del caso ───
            $evidences = $wpdb->get_results($wpdb->prepare(
                "SELECT e.file_url, e.file_name, e.file_type, e.file_size, e.description, e.created_at,
                        COALESCE(u.display_name, 'Agente QA') as user_name
                 FROM {$t['evidence']} e
                 LEFT JOIN {$wpdb->users} u ON e.uploaded_by = u.ID
                 WHERE e.case_id = %d
                 ORDER BY e.created_at DESC", $cid
            ));
            if (!empty($evidences)) {
                $body_content .= '
      <div style="margin:20px 0 10px;">
        <p style="margin:0 0 10px;font-size:14px;font-weight:bold;color:#374151;">📎 Evidencias adjuntas (' . count($evidences) . ')</p>';
                foreach ($evidences as $ev) {
                    $ev_size = $ev->file_size > 1048576
                        ? round($ev->file_size / 1048576, 1) . ' MB'
                        : round($ev->file_size / 1024, 1) . ' KB';
                    $ev_date = date('d/m/Y H:i', strtotime($ev->created_at));
                    $is_image = strpos($ev->file_type, 'image/') === 0;
                    $type_icon = $is_image ? '🖼️' : (strpos($ev->file_type, 'video/') === 0 ? '🎬' : '📄');

                    $body_content .= '
        <div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;padding:12px;margin:8px 0;">';
                    // Miniatura para imágenes
                    if ($is_image) {
                        $body_content .= '
          <div style="margin-bottom:8px;text-align:center;">
            <img src="' . esc_url($ev->file_url) . '" alt="' . esc_attr($ev->file_name) . '" style="max-width:100%;max-height:200px;border-radius:6px;border:1px solid #e2e8f0;">
          </div>';
                    }
                    $body_content .= '
          <p style="margin:0 0 4px;font-size:12px;color:#64748b;">' . $type_icon . ' <strong>' . esc_html($ev->file_name) . '</strong> (' . $ev_size . ') — ' . $ev_date . '</p>';
                    if (!empty($ev->description)) {
                        $body_content .= '
          <p style="margin:0 0 4px;font-size:12px;color:#475569;">' . esc_html($ev->description) . '</p>';
                    }
                    $body_content .= '
          <p style="margin:0;"><a href="' . esc_url($ev->file_url) . '" style="color:#0d9488;font-size:12px;font-weight:600;text-decoration:none;">📥 Ver / Descargar</a></p>
        </div>';
                }
                $body_content .= '
      </div>';
            }

            $body_content .= '<p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url) . '">🧪 Ver Suite de Pruebas</a>
      </p>';

            // 1) Correo al ADMIN principal
            $admin_email = get_option('admin_email');
            at_qa_send_notification(
                $admin_email,
                '🤖 Agente QA ✅: ' . $case_name . ' — PASS',
                '🤖 Caso Aprobado por Agente QA',
                $project_name . ' — ' . $module_name,
                $body_content
            );

            // 2) Correo al TESTER asignado (si existe y es diferente al admin)
            if ($ctx->tester && $ctx->tester->user_email && $ctx->tester->user_email !== $admin_email) {
                at_qa_send_notification(
                    $ctx->tester->user_email,
                    '🤖 Agente QA ✅: ' . $case_name . ' — PASS',
                    '🤖 Caso Aprobado por Agente QA',
                    $project_name . ' — ' . $module_name,
                    '<p>Hola <strong>' . esc_html($ctx->tester->display_name) . '</strong>,</p>' . $body_content
                );
            }

            // 3) Correo al CLIENTE (si tiene email)
            if ($ctx->client && !empty($ctx->client->email)) {
                $token_func_agent = function($cid_val, $email) {
                    return md5($cid_val . 'AUTOMATIZA_CRM_V2' . $email);
                };
                $ficha_url_agent = home_url('/?crm_view=timeline&cid=' . $ctx->client->id . '&token=' . $token_func_agent($ctx->client->id, $ctx->client->email));
                $client_agent_body = '<p>Hola <strong>' . esc_html($ctx->client->nombre) . '</strong>,</p>
      <p>Le informamos que se ha verificado exitosamente un caso de prueba en su proyecto:</p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
        <p style="margin:0 0 8px;"><strong>🔬 Caso:</strong> ' . esc_html($case_name) . '</p>
        <p style="margin:0;"><strong>📊 Resultado:</strong> <span class="badge-pass">✅ PASS</span></p>
      </div>
      <div style="margin:15px 0;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Progreso general: <strong>' . $passed . '/' . $total . '</strong> casos aprobados (<strong>' . $pct . '%</strong>)</p>
        <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,#0d9488,#14b8a6);width:' . $pct . '%;height:100%;border-radius:6px;"></div>
        </div>
      </div>
      <p>Puede ver el estado completo de las pruebas en la pestaña <strong>🧪 QA</strong> dentro de su ficha de cliente.</p>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($ficha_url_agent) . '">📊 Ver Mi Proyecto</a>
      </p>';
                at_qa_send_notification(
                    $ctx->client->email,
                    '📊 Actualización QA: ' . esc_html($project_name) . ' — ✅ PASS',
                    '📊 Actualización de Pruebas QA',
                    esc_html($client_name) . ' — Pruebas de calidad',
                    $client_agent_body
                );
            }

            // ─── Verificar si el MÓDULO quedó 100% probado ───
            // Nota: se considera exitoso cuando ≥85% de los casos son PASS
            $module_id = $ctx->caso->module_id;
            $mod_total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d", $module_id
            ));
            $mod_tested = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status != 'not_tested'", $module_id
            ));
            $mod_passed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status = 'pass'", $module_id
            ));
            $mod_failed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status = 'fail'", $module_id
            ));
            $mod_blocked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['cases']} WHERE module_id = %d AND status = 'blocked'", $module_id
            ));

            if ($mod_total > 0 && $mod_tested == $mod_total) {
                $mod_pct_pass = round(($mod_passed / $mod_total) * 100);
                // Veredicto: ≥85% = exitoso
                if ($mod_pct_pass == 100) {
                    $mod_verdict = '✅ 100% Aprobado';
                    $mod_verdict_class = 'badge-pass';
                } elseif ($mod_pct_pass >= 85) {
                    $mod_verdict = '✅ Aprobado con observaciones (' . $mod_pct_pass . '%)';
                    $mod_verdict_class = 'badge-pass';
                } else {
                    $mod_verdict = '❌ No aprobado (' . $mod_pct_pass . '%)';
                    $mod_verdict_class = 'badge-fail';
                }
                $mod_body = '
      <p>🎉 <strong>¡El módulo ha sido completamente probado!</strong></p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📦 Módulo:</strong> ' . esc_html($module_name) . '</p>
        <p style="margin:0 0 8px;"><strong>📊 Veredicto:</strong> <span class="' . $mod_verdict_class . '">' . $mod_verdict . '</span></p>
        <p style="margin:0 0 8px;"><strong>✅ Casos aprobados:</strong> ' . $mod_passed . '/' . $mod_total . ' (' . $mod_pct_pass . '%)</p>' .
        ($mod_failed > 0 ? '
        <p style="margin:0 0 8px;"><strong>❌ Fallidos:</strong> ' . $mod_failed . '</p>' : '') .
        ($mod_blocked > 0 ? '
        <p style="margin:0 0 8px;"><strong>⚠️ Bloqueados:</strong> ' . $mod_blocked . '</p>' : '') . '
        <p style="margin:0 0 8px;"><strong>🤖 Completado por:</strong> ' . esc_html($tester) . '</p>
        <p style="margin:0;"><strong>📅 Fecha:</strong> ' . current_time('d/m/Y H:i') . '</p>
      </div>
      <p style="font-size:12px;color:#6b7280;margin:10px 0 0;"><em>Nota: Se considera exitoso cuando ≥85% de los casos son aprobados.</em></p>
      <div style="margin:15px 0;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Progreso del módulo: <strong>' . $mod_passed . '/' . $mod_total . '</strong> aprobados (<strong>' . $mod_pct_pass . '%</strong>)</p>
        <div style="background:#e5e7eb;border-radius:6px;height:10px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,#0d9488,#14b8a6);width:' . $mod_pct_pass . '%;height:100%;border-radius:6px;"></div>
        </div>
      </div>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url . '&module=' . $module_id) . '">🧪 Ver Módulo Completo</a>
      </p>';

                at_qa_send_notification(
                    $admin_email,
                    '🎉 Módulo 100% Probado: ' . $module_name . ' — ' . $mod_pct_pass . '% aprobado',
                    '🎉 Módulo 100% Probado',
                    $project_name . ' — ' . $module_name,
                    $mod_body
                );
                if ($ctx->tester && $ctx->tester->user_email && $ctx->tester->user_email !== $admin_email) {
                    at_qa_send_notification(
                        $ctx->tester->user_email,
                        '🎉 Módulo 100% Probado: ' . $module_name . ' — ' . $mod_pct_pass . '% aprobado',
                        '🎉 Módulo 100% Probado',
                        $project_name . ' — ' . $module_name,
                        '<p>Hola <strong>' . esc_html($ctx->tester->display_name) . '</strong>,</p>' . $mod_body
                    );
                }

                // Notificar al cliente sobre módulo completo (agente)
                if ($ctx->client && !empty($ctx->client->email)) {
                    $token_mod_agent = md5($ctx->client->id . 'AUTOMATIZA_CRM_V2' . $ctx->client->email);
                    $ficha_url_mod_agent = home_url('/?crm_view=timeline&cid=' . $ctx->client->id . '&token=' . $token_mod_agent);
                    $client_mod_agent_body = '<p>Hola <strong>' . esc_html($ctx->client->nombre) . '</strong>,</p>
      <p>Le informamos que un módulo de pruebas de su proyecto ha sido completamente verificado:</p>' . $mod_body . '
      <p>Puede ver el detalle completo en su ficha de cliente:</p>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($ficha_url_mod_agent) . '">📊 Ver Mi Proyecto</a>
      </p>';
                    at_qa_send_notification(
                        $ctx->client->email,
                        '🎉 Módulo 100% Probado: ' . esc_html($module_name) . ' — ' . $mod_pct_pass . '% aprobado',
                        '🎉 Módulo 100% Probado',
                        esc_html($project_name) . ' — ' . esc_html($module_name),
                        $client_mod_agent_body
                    );
                }
            }

            // ─── Verificar si el PROYECTO quedó 100% completado ───
            if ($pct === 100) {
                $proj_fail = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d AND c.status='fail'",
                    $ctx->project->id
                ));
                $proj_blocked = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t['cases']} c JOIN {$t['modules']} m ON c.module_id=m.id WHERE m.project_id=%d AND c.status='blocked'",
                    $ctx->project->id
                ));
                $proj_body = '
      <p>🏆 <strong>¡El proyecto ha alcanzado el 100% de pruebas aprobadas!</strong></p>
      <div class="info-box">
        <p style="margin:0 0 8px;"><strong>📋 Proyecto:</strong> ' . esc_html($project_name) . '</p>
        <p style="margin:0 0 8px;"><strong>👤 Cliente:</strong> ' . esc_html($client_name) . '</p>
        <p style="margin:0 0 8px;"><strong>✅ Total casos aprobados:</strong> ' . $passed . '/' . $total . '</p>
        <p style="margin:0 0 8px;"><strong>❌ Fallidos:</strong> ' . $proj_fail . '</p>
        <p style="margin:0 0 8px;"><strong>⚠️ Bloqueados:</strong> ' . $proj_blocked . '</p>
        <p style="margin:0 0 8px;"><strong>🤖 Última actualización por:</strong> ' . esc_html($tester) . '</p>
        <p style="margin:0;"><strong>📅 Fecha:</strong> ' . current_time('d/m/Y H:i') . '</p>
      </div>
      <div style="background:#ecfdf5;border:2px solid #10b981;padding:16px;border-radius:8px;text-align:center;margin:15px 0;">
        <span style="font-size:36px;">🏆</span><br>
        <strong style="font-size:18px;color:#065f46;">100% Aprobado</strong><br>
        <p style="color:#047857;margin:8px 0 0;">Todas las pruebas han sido completadas exitosamente. El informe formal puede ser generado.</p>
      </div>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($qa_url) . '">📊 Generar Informe QA</a>
      </p>';

                at_qa_send_notification(
                    $admin_email,
                    '🏆 Proyecto 100% Completado: ' . $project_name,
                    '🏆 Proyecto QA 100% Completado',
                    $project_name . ' — Todas las pruebas aprobadas',
                    $proj_body
                );

                // Notificar al cliente cuando el proyecto está 100% completado
                if ($ctx->client && !empty($ctx->client->email)) {
                    $token_func_proj = function($cid_val, $email) {
                        return md5($cid_val . 'AUTOMATIZA_CRM_V2' . $email);
                    };
                    $ficha_url_proj = home_url('/?crm_view=timeline&cid=' . $ctx->client->id . '&token=' . $token_func_proj($ctx->client->id, $ctx->client->email));
                    $client_proj_body = '<p>Hola <strong>' . esc_html($ctx->client->nombre) . '</strong>,</p>
      <p>¡Excelentes noticias! Su proyecto <strong>' . esc_html($project_name) . '</strong> ha completado exitosamente todas las pruebas de calidad.</p>
      <div style="background:#ecfdf5;border:2px solid #10b981;padding:16px;border-radius:8px;text-align:center;margin:15px 0;">
        <span style="font-size:36px;">🏆</span><br>
        <strong style="font-size:18px;color:#065f46;">100% Aprobado</strong><br>
        <p style="color:#047857;margin:8px 0 0;">Todas las ' . $total . ' pruebas han sido completadas exitosamente.</p>
      </div>
      <p>En breve recibirá el informe formal de pruebas QA con todos los detalles.</p>
      <p style="text-align:center;margin-top:20px;">
        <a class="cta" href="' . esc_url($ficha_url_proj) . '">📊 Ver Mi Proyecto</a>
      </p>';
                    at_qa_send_notification(
                        $ctx->client->email,
                        '🏆 ¡Su proyecto ' . esc_html($project_name) . ' está 100% aprobado!',
                        '🏆 Proyecto QA Completado',
                        esc_html($client_name) . ' — Pruebas completadas',
                        $client_proj_body
                    );
                }
            }
        }
      } catch (\Throwable $e) {
          error_log('[Agente QA] Error notificación PASS: ' . $e->getMessage());
      }
    }
    // ─── Fin notificaciones agente ───

    wp_send_json_success(['status' => $status, 'tester' => $tester]);
});

// Subir evidencia (para agente)
add_action('wp_ajax_nopriv_at_qa_agent_upload_evidence', function() {
    if (!at_qa_verify_agent()) wp_send_json_error('Unauthorized', 403);
    if (empty($_FILES['evidence_file'])) wp_send_json_error('No se recibió archivo');
    $cid         = intval($_POST['case_db_id']);
    $description = sanitize_text_field($_POST['description'] ?? '');
    $allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','application/pdf'];
    if (!in_array($_FILES['evidence_file']['type'], $allowed)) wp_send_json_error('Tipo no permitido');
    if ($_FILES['evidence_file']['size'] > 10 * 1024 * 1024) wp_send_json_error('Archivo muy grande');
    $upload_dir = wp_upload_dir();
    $qa_dir = $upload_dir['basedir'] . '/' . AT_QA_EVIDENCE_DIR;
    $qa_url = $upload_dir['baseurl'] . '/' . AT_QA_EVIDENCE_DIR;
    wp_mkdir_p($qa_dir);
    $ext  = pathinfo($_FILES['evidence_file']['name'], PATHINFO_EXTENSION);
    $safe = 'qa-' . $cid . '-' . time() . '-' . wp_generate_password(6, false) . '.' . $ext;
    if (!move_uploaded_file($_FILES['evidence_file']['tmp_name'], $qa_dir . '/' . $safe)) wp_send_json_error('Error al guardar');
    global $wpdb;
    $t = at_qa_table_names();
    $wpdb->insert($t['evidence'], [
        'case_id'     => $cid,
        'file_url'    => $qa_url . '/' . $safe,
        'file_name'   => sanitize_file_name($_FILES['evidence_file']['name']),
        'file_type'   => $_FILES['evidence_file']['type'],
        'file_size'   => $_FILES['evidence_file']['size'],
        'uploaded_by' => 1,
        'description' => $description,
    ]);
    wp_send_json_success(['url' => $qa_url . '/' . $safe]);
});

// Agregar comentario (para agente)
add_action('wp_ajax_nopriv_at_qa_agent_add_comment', function() {
    if (!at_qa_verify_agent()) wp_send_json_error('Unauthorized', 403);
    global $wpdb;
    $t = at_qa_table_names();
    $cid     = intval($_POST['case_db_id']);
    $comment = sanitize_textarea_field($_POST['comment']);
    if (empty($comment)) wp_send_json_error('Comentario vacío');
    $wpdb->insert($t['comments'], [
        'case_id' => $cid,
        'user_id' => 1,
        'comment' => $comment,
    ]);
    wp_send_json_success(['id' => $wpdb->insert_id]);
});

// Buscar caso por case_id textual (ej: "AU-041")
add_action('wp_ajax_nopriv_at_qa_agent_get_case_by_text_id', function() {
    if (!at_qa_verify_agent()) wp_send_json_error('Unauthorized', 403);
    global $wpdb;
    $t = at_qa_table_names();
    $case_id = sanitize_text_field($_POST['case_id']);
    $case = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$t['cases']} WHERE case_id = %s", $case_id
    ));
    if (!$case) wp_send_json_error('Caso no encontrado: ' . $case_id);
    $evidence = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t['evidence']} WHERE case_id = %d ORDER BY created_at DESC", $case->id
    ));
    wp_send_json_success(['case' => $case, 'evidence' => $evidence]);
});