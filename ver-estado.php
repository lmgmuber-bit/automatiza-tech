<?php
// ver-estado.php?id=<código del plan> — «Estado del proyecto» del cliente. La lógica vive en
// wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php.
require_once __DIR__ . '/wp-load.php';

$codigo = isset($_GET['id']) && is_string($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['id'])) : '';
if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Robots-Tag: noindex, nofollow');
if (!function_exists('at_pt_html_ver_estado')) {
	status_header(503);
	exit('Estado del proyecto no disponible por ahora.');
}
$fila = at_pt_fila_ver_estado((string) $codigo);
if (!$fila) {
	status_header(404);
}
echo at_pt_html_ver_estado($fila, isset($_GET['agendar']) && $_GET['agendar'] === '1');
