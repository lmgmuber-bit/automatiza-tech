<?php
/**
 * Flush REST API routes - Ejecutar una vez y eliminar
 */
require_once('wp-load.php');

// Flush rewrite rules
flush_rewrite_rules(true);

// Verificar si el mu-plugin está cargado
$mu_plugins = get_mu_plugins();

echo "<h2>MU-Plugins cargados:</h2>";
echo "<pre>";
print_r($mu_plugins);
echo "</pre>";

// Verificar rutas REST registradas
echo "<h2>Rutas REST de automatiza-tech:</h2>";
$server = rest_get_server();
$routes = $server->get_routes();

foreach ($routes as $route => $handlers) {
    if (strpos($route, 'automatiza-tech') !== false) {
        echo "<strong>$route</strong><br>";
        foreach ($handlers as $handler) {
            echo " - Methods: " . implode(', ', $handler['methods']) . "<br>";
        }
    }
}

echo "<hr><p style='color:green;font-weight:bold;'>Flush completado. ELIMINA ESTE ARCHIVO.</p>";
