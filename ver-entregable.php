<?php
// ver-entregable.php?id=<código>[&img=<nombre>][&en_msg=<clave>] — entregable del cliente con sus versiones y notas.
// La lógica vive en wp-content/themes/automatiza-tech/inc/entregables/vista.php.
require_once __DIR__ . '/wp-load.php';

$codigo = isset($_GET['id']) && is_string($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['id'])) : '';
if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Robots-Tag: noindex, nofollow');
if (!function_exists('at_en_html_pagina')) {
	status_header(503);
	exit('Entregable no disponible por ahora.');
}
$ent = at_en_por_codigo((string) $codigo);
if ($ent && isset($_GET['img']) && is_string($_GET['img'])) {
	at_en_servir_imagen($ent, (string) wp_unslash($_GET['img']));
}
if (!$ent || (int) $ent->version_vigente < 1) {
	status_header(404);
	$ent = null;
}
$msg = isset($_GET['en_msg']) && is_string($_GET['en_msg']) ? sanitize_key(wp_unslash($_GET['en_msg'])) : '';
echo at_en_html_pagina($ent, $msg);
