<?php
// ver-presentacion.php
require_once('wp-load.php');

$unique_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

if (!$unique_id) {
    wp_die('ID de presentación no válido.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';
$proposal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE unique_link_id = %s", $unique_id));

if (!$proposal) {
    wp_die('Presentación no encontrada.');
}

$iframe_url = $proposal->gamma_iframe_url;

// Si no hay URL de iframe (porque el admin aún no la ha subido), mostrar mensaje
if (empty($iframe_url)) {
    $iframe_url = ""; // O una URL de placeholder
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuesta Comercial - AutomatizaTech</title>
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #000; }
        iframe { width: 100%; height: 100%; border: none; }
        .placeholder { color: white; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100%; text-align: center; }
    </style>
</head>
<body>

<?php if ($iframe_url): ?>
    <iframe src="<?php echo esc_url($iframe_url); ?>" allow="fullscreen"></iframe>
<?php else: ?>
    <div class="placeholder">
        <div>
            <h1>Propuesta en Generación</h1>
            <p>Estamos finalizando los detalles de tu presentación.<br>Por favor vuelve a intentar en unos minutos.</p>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
