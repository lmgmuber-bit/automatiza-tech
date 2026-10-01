<?php
// ver-plan.php?id=<código>[&agendar=1] — plan de trabajo del cliente (Etapa 2). La lógica vive en
// wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php.
require_once __DIR__ . '/wp-load.php';

$codigo = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['id'])) : '';
// La página lleva el token del día: nunca se guarda en caché.
if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Robots-Tag: noindex, nofollow');
if (!function_exists('at_pt_html_ver_plan')) {
	status_header(503);
	exit('Plan de trabajo no disponible por ahora.');
}
$fila = at_pt_fila_ver_plan((string) $codigo);
if (!$fila) {
	status_header(404);
}
echo at_pt_html_ver_plan($fila, !empty($_GET['agendar']));
