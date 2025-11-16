<?php
/**
 * Configurar Zona Horaria de Chile en WordPress
 * Ejecutar una sola vez y luego eliminar
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea admin
if (!current_user_can('administrator')) {
    die('Solo administradores pueden ejecutar este script');
}

echo "<h1>🕐 Configuración de Zona Horaria - Chile</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .ok { color: #28a745; font-weight: bold; }
    .info { color: #007bff; font-weight: bold; }
    .code { background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0; font-family: monospace; }
</style>";

echo "<div class='section'>";
echo "<h2>⏰ Zona Horaria Actual</h2>";

// Obtener configuración actual
$current_timezone = get_option('timezone_string');
$current_gmt_offset = get_option('gmt_offset');

echo "<p><strong>Timezone String:</strong> " . ($current_timezone ?: '<em>No configurado</em>') . "</p>";
echo "<p><strong>GMT Offset:</strong> " . ($current_gmt_offset ?: '0') . "</p>";
echo "<p><strong>Fecha/Hora PHP:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Fecha/Hora WordPress:</strong> " . current_time('Y-m-d H:i:s') . "</p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🇨🇱 Configurando Zona Horaria de Chile</h2>";

// Configurar timezone de Chile
$updated_timezone = update_option('timezone_string', 'America/Santiago');
$updated_gmt = update_option('gmt_offset', ''); // Limpiar offset cuando se usa timezone_string

if ($updated_timezone) {
    echo "<p class='ok'>✅ Zona horaria configurada: America/Santiago</p>";
} else {
    echo "<p class='info'>ℹ️ La zona horaria ya estaba configurada</p>";
}

// Actualizar formato de fecha y hora (opcional)
update_option('date_format', 'd/m/Y');
update_option('time_format', 'H:i');

echo "<p class='ok'>✅ Formato de fecha: dd/mm/YYYY</p>";
echo "<p class='ok'>✅ Formato de hora: 24 horas (HH:mm)</p>";

// Forzar PHP timezone
date_default_timezone_set('America/Santiago');

echo "</div>";

echo "<div class='section'>";
echo "<h2>✅ Verificación</h2>";

// Verificar nueva configuración
$new_timezone = get_option('timezone_string');
$new_gmt_offset = get_option('gmt_offset');

echo "<p><strong>Nueva Timezone String:</strong> <span class='ok'>" . $new_timezone . "</span></p>";
echo "<p><strong>Nueva GMT Offset:</strong> " . ($new_gmt_offset ?: '<em>Automático según timezone</em>') . "</p>";
echo "<p><strong>Fecha/Hora PHP ahora:</strong> <span class='ok'>" . date('Y-m-d H:i:s') . "</span></p>";
echo "<p><strong>Fecha/Hora WordPress ahora:</strong> <span class='ok'>" . current_time('Y-m-d H:i:s') . "</span></p>";
echo "<p><strong>Zona horaria PHP:</strong> <span class='ok'>" . date_default_timezone_get() . "</span></p>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>ℹ️ Información de Chile</h2>";
echo "<ul>";
echo "<li><strong>Zona Horaria:</strong> America/Santiago (CLT/CLST)</li>";
echo "<li><strong>UTC Offset:</strong> UTC-3 (horario de verano) / UTC-4 (horario estándar)</li>";
echo "<li><strong>Cambio horario:</strong> Chile cambia automáticamente entre CLT y CLST</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>📝 Cambios Aplicados</h2>";
echo "<ol>";
echo "<li>✅ Configurado timezone_string = 'America/Santiago' en WordPress</li>";
echo "<li>✅ Limpiado gmt_offset para usar timezone automático</li>";
echo "<li>✅ Formato de fecha: d/m/Y (15/11/2025)</li>";
echo "<li>✅ Formato de hora: H:i (formato 24 horas)</li>";
echo "<li>✅ Configurado timezone en PHP (date_default_timezone_set)</li>";
echo "</ol>";

echo "<div class='code'>";
echo "También agregado en wp-config.php:<br>";
echo "date_default_timezone_set('America/Santiago');";
echo "</div>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>🧹 Siguiente Paso</h2>";
echo "<p class='ok'><strong>¡Configuración completada!</strong></p>";
echo "<p>Ahora elimina este archivo del servidor por seguridad:</p>";
echo "<div class='code'>rm set-timezone-chile.php</div>";
echo "<p>O vía FTP/cPanel elimina: <code>/public_html/set-timezone-chile.php</code></p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔍 Verificación en WordPress Admin</h2>";
echo "<p>También puedes verificar en el panel de administración:</p>";
echo "<ol>";
echo "<li>Ve a: <a href='" . admin_url('options-general.php') . "'>Ajustes → Generales</a></li>";
echo "<li>Busca la sección 'Zona horaria'</li>";
echo "<li>Debería estar en: <strong>America/Santiago</strong></li>";
echo "</ol>";
echo "</div>";
