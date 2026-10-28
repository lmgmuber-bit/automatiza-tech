<?php
// Página «Mis clientes». Uso: bash tests/colaboradores/correr.sh pagina
require __DIR__ . '/wp-bootstrap.php';
exigir('at_colab_html_pagina', 'at_colab_render_pagina', 'at_colab_redirigir_login');
global $wpdb;

$admin = col_admin_id();
$c = col_crear_cliente('Orly Prueba', 'Funeraria Prueba');
$ficha = col_crear_ficha($c);
$wpdb->insert($wpdb->prefix . 'crm_historial', ['cliente_id' => $c, 'tipo_evento' => 'nota', 'titulo' => 'Nota visible', 'descripcion' => 'Texto de la nota', 'usuario_id' => $admin, 'created_at' => current_time('mysql')]);
$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $ficha, 'detail_type' => 'factura', 'title' => 'Factura secreta', 'description' => '', 'amount' => 123456, 'created_at' => current_time('mysql')]);
$e = col_crear_entregable($c, $ficha, 'Sitio funeraria');
$email = (string) $wpdb->get_var($wpdb->prepare("SELECT email FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $c));

$otro = col_crear_cliente('Cliente Ajeno', 'Otra Empresa');
$vacio = col_crear_cliente('Cliente Vacío', 'Sin Movimientos');
$colab = col_crear_usuario('at_colaborador', [$c, $vacio, 999999999]);
wp_set_current_user($colab);

$lista = at_colab_html_pagina($colab, 0);
ok(strpos($lista, 'Orly Prueba') !== false && strpos($lista, 'Cliente Vacío') !== false, 'lista: trae los clientes asignados');
ok(strpos($lista, 'Cliente Ajeno') === false, 'lista: no trae un cliente no asignado');
ok(substr_count($lista, 'cliente=') === 2, 'lista: un id asignado que ya no existe se omite');

$ajeno = at_colab_html_pagina($colab, $otro);
ok(strpos($ajeno, 'No tienes acceso a este cliente') !== false, 'cliente no asignado: «No tienes acceso a este cliente»');
ok(strpos($ajeno, 'Cliente Ajeno') === false, 'cliente no asignado: no muestra su nombre');

$html = at_colab_html_pagina($colab, $c);
ok(strpos($html, 'Nota visible') !== false, 'ficha: muestra la nota del historial');
ok(strpos($html, 'Factura secreta') === false && strpos($html, '123456') === false && strpos($html, '123.456') === false, 'ficha: ni la factura ni su monto');
ok(strpos($html, 'crm_nonce') === false && strpos($html, 'automatiza-crm-ficha') === false, 'ficha: sin nonce ni enlace a la ficha del CRM');
ok(strpos($html, $email) === false && strpos($html, '+56900000001') === false, 'ficha: sin correo ni teléfono del cliente');
ok(strpos($html, 'Sitio funeraria') !== false && strpos($html, 'https://ejemplo.test/v1/') !== false, 'ficha: muestra el entregable y su versión');
ok(strpos($html, 'Me gustaría otro color en la portada.') !== false, 'ficha: muestra el comentario del cliente');
ok(strpos($html, 'value="at_colab_responder"') !== false && strpos($html, 'name="nota_id" value="' . $e['nota'] . '"') !== false, 'ficha: comentario sin responder trae el formulario de respuesta');
ok(strpos($html, 'value="at_colab_nota"') !== false, 'ficha: trae el formulario para agregar una nota');
ok(strpos($html, 'El cliente también ve estas notas') !== false, 'ficha: el formulario avisa que el cliente ve las notas');

$v = at_colab_html_pagina($colab, $vacio);
ok(strpos($v, 'Todavía no hay movimientos') !== false, 'cliente vacío: estado vacío del historial');
ok(strpos($v, 'Este cliente no tiene entregables') !== false, 'cliente vacío: estado vacío de entregables');

$ok = at_colab_html_pagina($colab, $c, 'nota_ok');
ok(strpos($ok, 'Nota agregada') !== false, 'aviso nota_ok');

ok(at_colab_redirigir_login('https://x/wp-admin/', '', new WP_User($colab)) === admin_url('admin.php?page=at-mis-clientes'), 'al iniciar sesión, el colaborador llega a Mis clientes');
ok(at_colab_redirigir_login('https://x/wp-admin/', '', new WP_User($admin)) === 'https://x/wp-admin/', 'el administrador sigue a donde iba');

fin();
