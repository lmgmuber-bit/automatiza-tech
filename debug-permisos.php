<?php
/**
 * Debug de permisos de usuario
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Verificar si hay un usuario logueado
if (!is_user_logged_in()) {
    echo "❌ No hay usuario logueado en WordPress\n";
    echo "Por favor, accede al admin de WordPress primero\n";
    exit;
}

// Obtener usuario actual
$current_user = wp_get_current_user();

echo "=== DEBUG DE PERMISOS DE USUARIO ===\n\n";

echo "Usuario actual:\n";
echo "- ID: " . $current_user->ID . "\n";
echo "- Nombre de usuario: " . $current_user->user_login . "\n";
echo "- Email: " . $current_user->user_email . "\n";
echo "- Roles: " . implode(', ', $current_user->roles) . "\n\n";

echo "Capacidades del usuario:\n";
$capabilities = $current_user->allcaps;
foreach ($capabilities as $cap => $has) {
    if ($has) {
        echo "- $cap: ✅\n";
    }
}

echo "\nVerificaciones específicas:\n";
echo "- current_user_can('manage_options'): " . (current_user_can('manage_options') ? "✅ SÍ" : "❌ NO") . "\n";
echo "- current_user_can('administrator'): " . (current_user_can('administrator') ? "✅ SÍ" : "❌ NO") . "\n";
echo "- current_user_can('edit_posts'): " . (current_user_can('edit_posts') ? "✅ SÍ" : "❌ NO") . "\n";
echo "- user_can(user_id, 'manage_options'): " . (user_can($current_user->ID, 'manage_options') ? "✅ SÍ" : "❌ NO") . "\n";

echo "\n=== COOKIES DE SESIÓN ===\n";
if (isset($_COOKIE)) {
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'wordpress') !== false || strpos($name, 'wp') !== false) {
            echo "- $name: " . substr($value, 0, 50) . "...\n";
        }
    }
}

echo "\n=== VERIFICACIÓN DE NONCE ===\n";
$nonce = wp_create_nonce('automatiza_services_nonce');
echo "- Nonce generado: $nonce\n";
echo "- Verificación: " . (wp_verify_nonce($nonce, 'automatiza_services_nonce') ? "✅ VÁLIDO" : "❌ INVÁLIDO") . "\n";

echo "\n=== CONFIGURACIÓN DE WORDPRESS ===\n";
echo "- Versión WP: " . get_bloginfo('version') . "\n";
echo "- URL admin: " . admin_url() . "\n";
echo "- URL actual: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A') . "\n";
echo "- Multisite: " . (is_multisite() ? "✅ SÍ" : "❌ NO") . "\n";
?>