<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Limpieza masiva de backlog viejo - ARGOS Mecánico
 *
 * Script de un solo uso: archiva (status = 'ignored') todos los errores
 * que estaban en 'new' antes de activar el monitoreo automático del
 * Mecánico, para que arranque con la cola limpia y solo procese errores
 * nuevos desde ahora en adelante. No borra filas, solo cambia status.
 *
 * URL: /cleanup-old-errors.php
 *
 * @package AutomatizaTech
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar este script.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_n8n_errors';

$antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'new'");

$affected = false;
$did_run = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';

if ($did_run && $antes > 0) {
    $affected = $wpdb->query($wpdb->prepare(
        "UPDATE $table_name
         SET status = 'ignored', resolution_notes = %s
         WHERE status = 'new'",
        'Limpieza masiva ARGOS Mecánico (' . date('Y-m-d H:i') . ') - archivado en bloque antes de activar monitoreo automático.'
    ));
}

$despues = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'new'");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpieza Backlog - ARGOS Mecánico</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f0f2f5; }
        .container { background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { color: #1e40af; }
        .status { padding: 20px; border-radius: 8px; margin: 20px 0; }
        .status.success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .status.warn { background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e; }
        .btn { display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .btn:hover { background: #b91c1c; }
        code { background: #1f2937; color: #e5e7eb; padding: 3px 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Limpieza de backlog - ARGOS Mecánico</h1>

        <?php if (!$did_run): ?>
            <div class="status warn">
                Hay <strong><?php echo $antes; ?></strong> errores con status <code>new</code>.
                Esta acción los marca como <code>ignored</code> (no los borra, solo archiva)
                para que la cola quede limpia antes de activar el monitoreo automático.
            </div>
            <a class="btn" href="?confirmar=1">Confirmar y archivar <?php echo $antes; ?> errores</a>
        <?php else: ?>
            <div class="status success">
                ✅ Listo. Filas afectadas: <strong><?php echo (int) $affected; ?></strong>.<br>
                Errores con status <code>new</code> antes: <?php echo $antes; ?> → ahora: <?php echo $despues; ?>.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
