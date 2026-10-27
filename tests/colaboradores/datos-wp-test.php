<?php
// Historial y datos del cliente que ve el colaborador. Uso: bash tests/colaboradores/correr.sh datos
require __DIR__ . '/wp-bootstrap.php';
exigir('at_colab_historial', 'at_colab_cliente');
global $wpdb;

$admin = col_admin_id();
$c = col_crear_cliente('Cliente Historial', 'Funeraria Prueba');
$wpdb->insert($wpdb->prefix . 'crm_historial', ['cliente_id' => $c, 'tipo_evento' => 'nota', 'titulo' => 'Nota del equipo', 'descripcion' => 'Llamamos al cliente', 'usuario_id' => $admin, 'created_at' => '2026-10-20 10:00:00']);
$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $c, 'detail_type' => 'reunion', 'title' => 'Reunión de avance', 'description' => 'Revisamos el sitio', 'amount' => 0, 'created_by' => $admin, 'created_at' => '2026-10-21 10:00:00']);
$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $c, 'detail_type' => 'factura', 'title' => 'Factura 99', 'description' => 'Pago', 'amount' => 777777, 'created_by' => $admin, 'created_at' => '2026-10-22 10:00:00']);

$h = at_colab_historial($c);
$titulos = array_column($h, 'title');
ok(in_array('Nota del equipo', $titulos, true), 'historial: trae la nota de crm_historial');
ok(in_array('Reunión de avance', $titulos, true), 'historial: trae el detalle reunión');
ok(!in_array('Factura 99', $titulos, true), 'historial: no trae la factura');
ok(strpos(wp_json_encode($h), '777777') === false, 'historial: el monto no aparece en ninguna parte');
ok($titulos[0] === 'Reunión de avance', 'historial: lo más nuevo primero');
ok(isset($h[0]['origen_html'], $h[0]['autor']), 'historial: cada fila trae origen_html y autor');
ok($h[0]['autor'] === get_userdata($admin)->display_name, 'historial: autor = nombre de quien lo creó');

$cli = at_colab_cliente($c);
ok($cli === ['id' => $c, 'nombre' => 'Cliente Historial', 'empresa' => 'Funeraria Prueba'], 'cliente: solo id, nombre y empresa');
ok(at_colab_cliente(999999999) === null, 'cliente inexistente: null');

$vacio = col_crear_cliente('Cliente Vacío');
ok(at_colab_historial($vacio) === [], 'cliente sin movimientos: historial vacío');

fin();
