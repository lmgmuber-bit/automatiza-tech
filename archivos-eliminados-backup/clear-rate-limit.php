<?php
/**
 * Limpiar Rate Limit del Formulario de Contacto
 * Usar este archivo para limpiar los intentos bloqueados durante pruebas
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea una petición directa
if (php_sapi_name() !== 'cli' && !isset($_GET['clear'])) {
    die('Acceso no permitido. Usa: ?clear=1');
}

// Obtener IP actual
$current_ip = $_SERVER['REMOTE_ADDR'] ?? 'localhost';
$transient_key = 'contact_form_' . md5($current_ip);

echo "<html><head><meta charset='UTF-8'><title>Limpiar Rate Limit</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { border-left: 4px solid #28a745; background: #d4edda; color: #155724; }
.info { border-left: 4px solid #17a2b8; background: #d1ecf1; color: #0c5460; }
.warning { border-left: 4px solid #ffc107; background: #fff3cd; color: #856404; }
h1 { color: #1e3a8a; }
.btn { display: inline-block; padding: 10px 20px; background: #06d6a0; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
.btn:hover { background: #05b08a; }
.btn-danger { background: #dc3545; }
.btn-danger:hover { background: #c82333; }
code { background: #2d2d2d; color: #f8f8f2; padding: 2px 6px; border-radius: 3px; }
</style></head><body>";

echo "<h1>🧹 Limpiar Rate Limit - Formulario de Contacto</h1>";

// Información actual
echo "<div class='box info'>";
echo "<h2>📊 Información Actual</h2>";
echo "<p><strong>Tu IP:</strong> <code>$current_ip</code></p>";
echo "<p><strong>Transient Key:</strong> <code>$transient_key</code></p>";

$current_attempts = get_transient($transient_key);
if ($current_attempts !== false) {
    echo "<p><strong>Intentos actuales:</strong> <code>$current_attempts / 5</code></p>";
    echo "<p><strong>Estado:</strong> ";
    if ($current_attempts >= 5) {
        echo "<span style='color: #dc3545; font-weight: bold;'>❌ BLOQUEADO</span></p>";
    } else {
        echo "<span style='color: #28a745; font-weight: bold;'>✓ Permitido</span></p>";
    }
} else {
    echo "<p><strong>Intentos actuales:</strong> <code>0 / 5</code></p>";
    echo "<p><strong>Estado:</strong> <span style='color: #28a745; font-weight: bold;'>✓ Sin restricción</span></p>";
}
echo "</div>";

// Limpiar si se solicitó
if (isset($_GET['clear'])) {
    $deleted = delete_transient($transient_key);
    
    if ($deleted) {
        echo "<div class='box success'>";
        echo "<h2>✅ Límite Limpiado Exitosamente</h2>";
        echo "<p>El contador de intentos ha sido reiniciado para tu IP.</p>";
        echo "<p>Ahora puedes hacer nuevos envíos del formulario de contacto.</p>";
        echo "</div>";
    } else {
        echo "<div class='box warning'>";
        echo "<h2>⚠️ No había límite activo</h2>";
        echo "<p>No había restricción para tu IP. Ya puedes enviar formularios.</p>";
        echo "</div>";
    }
    
    echo "<div class='box info'>";
    echo "<h3>🔄 Acciones Disponibles</h3>";
    echo "<p><a href='?' class='btn'>Verificar Estado</a></p>";
    echo "<p><a href='?clear=all' class='btn btn-danger'>Limpiar TODAS las IPs</a></p>";
    echo "</div>";
}

// Limpiar TODAS las IPs (útil en desarrollo)
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    global $wpdb;
    
    // Buscar todos los transients relacionados con el formulario de contacto
    $deleted_count = $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_contact_form_%' 
         OR option_name LIKE '_transient_timeout_contact_form_%'"
    );
    
    echo "<div class='box success'>";
    echo "<h2>🧹 Limpieza Completa Realizada</h2>";
    echo "<p>Se eliminaron <strong>$deleted_count</strong> transients del sistema.</p>";
    echo "<p>Todos los límites de rate limiting han sido reiniciados.</p>";
    echo "</div>";
    
    echo "<div class='box info'>";
    echo "<p><a href='?' class='btn'>Verificar Estado</a></p>";
    echo "</div>";
}

// Si no hay parámetro, mostrar opciones
if (!isset($_GET['clear'])) {
    echo "<div class='box info'>";
    echo "<h2>🔧 Opciones Disponibles</h2>";
    echo "<p><a href='?clear=1' class='btn'>Limpiar Mi IP</a></p>";
    echo "<p><a href='?clear=all' class='btn btn-danger'>Limpiar TODAS las IPs</a></p>";
    echo "</div>";
    
    echo "<div class='box warning'>";
    echo "<h2>⚠️ Información del Rate Limiting</h2>";
    echo "<ul>";
    echo "<li><strong>Límite:</strong> 5 envíos por hora por IP</li>";
    echo "<li><strong>Duración:</strong> 1 hora (3600 segundos)</li>";
    echo "<li><strong>Protección:</strong> Previene spam y abuso</li>";
    echo "</ul>";
    echo "<p><strong>Nota:</strong> En producción, este límite protege el servidor de ataques de spam.</p>";
    echo "</div>";
}

// Información adicional
echo "<div class='box info'>";
echo "<h2>🛠️ Uso</h2>";
echo "<ul>";
echo "<li><strong>Ver estado:</strong> <code>http://localhost/automatiza-tech/clear-rate-limit.php</code></li>";
echo "<li><strong>Limpiar tu IP:</strong> <code>http://localhost/automatiza-tech/clear-rate-limit.php?clear=1</code></li>";
echo "<li><strong>Limpiar todas:</strong> <code>http://localhost/automatiza-tech/clear-rate-limit.php?clear=all</code></li>";
echo "</ul>";
echo "</div>";

echo "<div class='box warning'>";
echo "<h2>💡 Modo de Desarrollo</h2>";
echo "<p>Si estás desarrollando y quieres desactivar temporalmente el rate limiting, puedes:</p>";
echo "<ol>";
echo "<li>Editar <code>wp-content/themes/automatiza-tech/inc/contact-form.php</code></li>";
echo "<li>En la función <code>check_rate_limit()</code> (línea ~579)</li>";
echo "<li>Cambiar el límite de 5 a un número mayor (ej: 100)</li>";
echo "<li>O comentar la validación completa</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
