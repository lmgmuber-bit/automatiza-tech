<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_migrar_esquema', 'at_rt_esquema_listo');
at_rt_migrar_esquema();
global $wpdb;
$tablas = [
	'at_retomar' => ['id', 'crm_cliente_id', 'propuesta_id', 'nombre', 'negocio', 'email', 'telefono', 'wa_autorizado', 'propuesta_url', 'demo_urls', 'imagen_url', 'textos', 'max_envios', 'intervalo_dias', 'canales', 'estado', 'token', 'render', 'envios', 'activado_at', 'proximo_at', 'enviando', 'creado_at', 'actualizado_at', 'notas', 'redaccion', 'aprobacion', 'fotos'],
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
ok(get_option('at_rt_schema') === '2', "opción de esquema es string '2'");
ok(at_rt_esquema_listo(), 'esquema listo tras migrar');

// Actualización desde la versión 1: una fila anterior conserva sus datos y las columnas nuevas quedan vacías.
$t = $wpdb->prefix . 'at_retomar';
$nuevas = ['notas', 'redaccion', 'aprobacion', 'fotos'];
$legada = 0;
register_shutdown_function(static function () use (&$legada, $wpdb, $t) {
	if ($legada) { $wpdb->delete($t, ['id' => $legada]); }
});
foreach ($nuevas as $c) { $wpdb->query("ALTER TABLE {$t} DROP COLUMN {$c}"); }
update_option('at_rt_schema', '1', false);
ok(!at_rt_esquema_listo(), 'esquema v1 sin columnas nuevas no está listo');
$ahora = current_time('mysql');
$wpdb->insert($t, ['nombre' => '[PRUEBA] Legada', 'email' => 'rt-legada@example.com', 'demo_urls' => '[]', 'textos' => '[]', 'render' => '[]', 'envios' => '[{"tipo":"envio","n":1}]', 'creado_at' => $ahora, 'actualizado_at' => $ahora]);
$legada = (int) $wpdb->insert_id;
ok($legada > 0, 'fila creada antes de migrar');
at_rt_migrar_esquema();
ok(get_option('at_rt_schema') === '2' && at_rt_esquema_listo(), 'migrar desde v1 deja la opción en 2 y el esquema listo');
ok(array_column($wpdb->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A), 'Field') === $tablas['at_retomar'], 'migrar desde v1 deja las columnas en el mismo orden que una instalación nueva');
$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $legada), ARRAY_A);
ok($f['nombre'] === '[PRUEBA] Legada' && $f['envios'] === '[{"tipo":"envio","n":1}]', 'la fila anterior conserva nombre y envios');
ok($f['notas'] === '' && $f['redaccion'] === '' && $f['aprobacion'] === '' && $f['fotos'] === '', 'las columnas nuevas de la fila anterior quedan vacías');
$leida = at_rt_obtener($legada);
ok($leida['notas'] === '' && $leida['redaccion'] === [] && $leida['aprobacion'] === [] && $leida['fotos'] === [], 'la fila anterior se lee con arrays vacíos');
$antes = $wpdb->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A);
at_rt_migrar_esquema();
ok($wpdb->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A) === $antes && get_option('at_rt_schema') === '2', 'migrar dos veces no cambia nada');

// Con la opción en 2 pero sin una columna, el esquema no está listo y migrar la repone.
$wpdb->query("ALTER TABLE {$t} DROP COLUMN fotos");
ok(!at_rt_esquema_listo(), 'sin la columna fotos el esquema no está listo aunque la opción diga 2');
at_rt_migrar_esquema();
ok(at_rt_esquema_listo() && array_column($wpdb->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A), 'Field') === $tablas['at_retomar'], 'migrar repone la columna que faltaba');
fin();
