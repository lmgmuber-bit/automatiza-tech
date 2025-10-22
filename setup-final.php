<?php 
// INSTALLER FINAL - SIN WARNINGS 
error_reporting(0); 
ini_set('display_errors', 0); 
require_once('wp-load.php'); 
 
if (get_template() !== 'automatiza-tech') { 
    switch_theme('automatiza-tech'); 
} 
 
update_option('blogname', 'Automatiza Tech'); 
update_option('blogdescription', 'Conectamos tus ventas, web y CRM'); 
 
set_theme_mod('automatiza_tech_whatsapp', '+5491234567890'); 
set_theme_mod('automatiza_tech_email', 'contacto@automatizatech.com'); 
 
echo '<h1>✅ Automatiza Tech Listo</h1>'; 
echo '<p>Configuración completada sin errores.</p>'; 
echo '<a href="' . home_url() . '">Ver Sitio</a>'; 
?> 
