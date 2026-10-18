<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_migrar_esquema', 'at_rt_esquema_listo');
at_rt_migrar_esquema();
global $wpdb;
$tablas = [
	'at_retomar' => ['id', 'crm_cliente_id', 'propuesta_id', 'nombre', 'negocio', 'email', 'telefono', 'wa_autorizado', 'propuesta_url', 'demo_urls', 'imagen_url', 'textos', 'max_envios', 'intervalo_dias', 'canales', 'estado', 'token', 'render', 'envios', 'activado_at', 'proximo_at', 'enviando', 'creado_at', 'actualizado_at'],
	'at_retomar_bajas' => ['id', 'email', 'telefono', 'origen', 'motivo', 'creado_at', 'revertido_at'],
];
$primera = [];
foreach ($tablas as $sufijo => $esperadas) {
	$t = $wpdb->prefix . $sufijo;
	$columnas = $wpdb->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A);
	$primera[$sufijo] = $columnas;
	ok(array_column($columnas, 'Field') === $esperadas, "{$sufijo}: todas las columnas en orden");
}
at_rt_migrar_esquema();
foreach ($tablas as $sufijo => $esperadas) {
	$t = $wpdb->prefix . $sufijo;
	ok($wpdb->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A) === $primera[$sufijo], "{$sufijo}: segunda migración conserva esquema");
}
$por_nombre = array_column($primera['at_retomar'], null, 'Field');
foreach (['max_envios' => '4', 'intervalo_dias' => '7', 'canales' => 'correo,whatsapp', 'estado' => 'borrador'] as $campo => $valor) {
	ok(($por_nombre[$campo]['Default'] ?? null) === $valor, "default {$campo} = {$valor}");
}
foreach (['crm_cliente_id', 'propuesta_id', 'activado_at', 'proximo_at', 'enviando'] as $campo) {
	ok(($por_nombre[$campo]['Null'] ?? '') === 'YES', "{$campo} admite NULL");
}
$t_bajas = $wpdb->prefix . 'at_retomar_bajas';
$indices = $wpdb->get_results("SHOW INDEX FROM {$t_bajas}", ARRAY_A);
foreach (['email', 'telefono'] as $campo) {
	ok(in_array($campo, array_column($indices, 'Column_name'), true), "bajas: índice por {$campo}");
}
ok(get_option('at_rt_schema') === '1', "opción de esquema es string '1'");
ok(at_rt_esquema_listo(), 'esquema listo tras migrar');
fin();
