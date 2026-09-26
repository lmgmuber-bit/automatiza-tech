<?php
// Uso interno de panel-timeline-wp-test.php: genera el HTML real de
// render_public_prospect_timeline() en un proceso PHP aparte, porque ese método siempre termina
// con exit; y no se puede llamar dentro del mismo proceso que sigue evaluando la prueba.
// Argumentos: <propuesta_id> <email>
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$propuesta_id = (int) ($argv[1] ?? 0);
$email = (string) ($argv[2] ?? '');
if ($propuesta_id <= 0 || $email === '') {
	fwrite(STDERR, "Uso: panel-timeline-wp-test-render.php <propuesta_id> <email>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
require $at_cc_wp_load;
if (!isset($GLOBALS['at_crm_ai']) || !method_exists($GLOBALS['at_crm_ai'], 'render_public_prospect_timeline')) {
	fwrite(STDERR, "El mu-plugin crm-ai-completo.php no está cargado en ese sitio.\n");
	exit(2);
}
// El token sale de la URL real que arma get_prospect_timeline_url(): no se repite aquí su receta.
$url_prospecto = AutomatizaTech_CRM_AI::get_prospect_timeline_url($propuesta_id);
parse_str((string) wp_parse_url($url_prospecto, PHP_URL_QUERY), $q_prospecto);
$_GET['crm_view'] = 'prospect_timeline';
$_GET['pid'] = $propuesta_id;
$_GET['token'] = (string) ($q_prospecto['token'] ?? '');
$GLOBALS['at_crm_ai']->render_public_prospect_timeline();
// No debería llegar aquí: el método real siempre termina con exit; si esto se ve en stderr, revisa
// is_admin() en el entorno del WordPress local de prueba.
fwrite(STDERR, "ADVERTENCIA: render_public_prospect_timeline no llamó exit.\n");
