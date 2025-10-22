<?php 
// Suprimir warnings de funciones obsoletas 
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); 
ini_set('display_errors', 0); 
 
// Cargar WordPress 
require_once(dirname(__FILE__) . '/wp-load.php'); 
 
// Activar tema si no está activo 
if (get_template() !== 'automatiza-tech') { 
    switch_theme('automatiza-tech'); 
} 
 
// Configurar opciones básicas 
update_option('blogname', 'Automatiza Tech'); 
update_option('blogdescription', 'Conectamos tus ventas, web y CRM'); 
 
// Configurar tema 
set_theme_mod('automatiza_tech_whatsapp', '+5491234567890'); 
set_theme_mod('automatiza_tech_email', 'contacto@automatizatech.com'); 
 
echo '<h1>✅ Configuración Completada</h1>'; 
echo '<p>El tema Automatiza Tech ha sido configurado correctamente.</p>'; 
echo '<p><a href="' . home_url() . '">Ver Sitio Web</a> | <a href="' . admin_url('customize.php') . '">Personalizar</a></p>'; 
?> 
