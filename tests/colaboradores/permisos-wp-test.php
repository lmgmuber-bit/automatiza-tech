<?php
// Rol, puerta de permisos y campo del perfil. Uso: bash tests/colaboradores/correr.sh permisos
require __DIR__ . '/wp-bootstrap.php';
exigir('at_colab_puede', 'at_colab_ids_asignados', 'at_colab_es_colaborador', 'at_colab_guardar_perfil');

$c = col_crear_cliente('Cliente Asignado');
$otro = col_crear_cliente('Cliente Ajeno');
$admin = col_admin_id();
$colab = col_crear_usuario('at_colaborador', [$c]);

ok(at_colab_puede($c, $admin), 'administrador: puede ver un cliente existente');
ok(!at_colab_puede(999999999, $admin), 'administrador: un cliente que no existe es no');
ok(at_colab_puede($c, $colab), 'colaborador: puede ver su cliente asignado');
ok(!at_colab_puede($otro, $colab), 'colaborador: no puede ver un cliente no asignado');
ok(!at_colab_puede(0, $colab), 'colaborador: id 0 es no');
ok(at_colab_ids_asignados($colab) === [$c], 'ids asignados del colaborador');
ok(at_colab_es_colaborador($colab) && !at_colab_es_colaborador($admin), 'es_colaborador distingue al colaborador');

$rol = get_role('at_colaborador');
ok($rol && $rol->has_cap('at_colaborador') && $rol->has_cap('read'), 'el rol tiene read y at_colaborador');
ok($rol && !$rol->has_cap('edit_posts'), 'el rol no tiene edit_posts');
ok(get_role('administrator')->has_cap('at_colaborador'), 'el administrador también ve «Mis clientes»');

// Rol cambiado a suscriptor con la meta intacta: ya no pasa.
$degradado = col_crear_usuario('at_colaborador', [$c]);
(new WP_User($degradado))->set_role('subscriber');
ok(!at_colab_puede($c, $degradado), 'suscriptor con la meta intacta: no');
$sin_rol = col_crear_usuario('subscriber', [$c]);
(new WP_User($sin_rol))->set_role('');
ok(!at_colab_puede($c, $sin_rol), 'usuario sin rol: no');

// El colaborador no puede cambiar su propia lista; el administrador sí.
wp_set_current_user($colab);
$_POST['at_clientes_asignados'] = [$c, $otro];
$_POST['_at_colab_perfil'] = wp_create_nonce('at_colab_perfil_' . $colab);
at_colab_guardar_perfil($colab);
ok(at_colab_ids_asignados($colab) === [$c], 'colaborador que se edita a sí mismo: la lista no cambia');

wp_set_current_user($admin);
$_POST['_at_colab_perfil'] = wp_create_nonce('at_colab_perfil_' . $colab);
$_POST['at_clientes_asignados'] = [(string) $otro, (string) $c, 'x'];
at_colab_guardar_perfil($colab);
ok(at_colab_ids_asignados($colab) === array_values(array_unique([min($c, $otro), max($c, $otro)])), 'administrador: guarda la lista normalizada');

$_POST['_at_colab_perfil'] = 'nonce-falso';
$_POST['at_clientes_asignados'] = [];
at_colab_guardar_perfil($colab);
ok(count(at_colab_ids_asignados($colab)) === 2, 'administrador con nonce falso: la lista no cambia');

ob_start();
wp_set_current_user($colab);
at_colab_campo_perfil(new WP_User($colab));
$html_colab = ob_get_clean();
ok($html_colab === '', 'el colaborador no ve el campo «Clientes asignados» en su perfil');
ob_start();
wp_set_current_user($admin);
at_colab_campo_perfil(new WP_User($colab));
$html_admin = ob_get_clean();
ok(strpos($html_admin, 'Clientes asignados') !== false && strpos($html_admin, 'Cliente Asignado') !== false, 'el administrador ve el campo con los clientes');

fin();
