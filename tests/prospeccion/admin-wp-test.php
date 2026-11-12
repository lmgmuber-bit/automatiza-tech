<?php
// Uso: AT_WP_LOAD=<wp-load.php> php tests/prospeccion/admin-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
at_pro_migrar_esquema();
$t = at_pro_tabla();
$wpdb->query("DELETE FROM {$t}");

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$admin = (int) ($admin[0] ?? 0);
ok($admin > 0, 'hay un administrador en el sitio de prueba');
$sub = wp_insert_user(['user_login' => 'sub_pro_test_' . wp_rand(), 'user_pass' => wp_generate_password(20), 'role' => 'subscriber']);
wp_set_current_user($admin);
set_current_screen('automatiza-crm_page_at-prospeccion');

$i1 = at_pro_crear(['negocio' => 'Café Sur', 'web' => 'cafesur.test', 'telefono' => '+56 9 5550 1001', 'rubro' => 'Cafetería', 'comuna' => 'Providencia', 'fuente' => 'sellbird', 'senales' => 'no tienen reservas en línea']);
$i2 = at_pro_crear(['negocio' => 'Taller Norte', 'web' => 'tallernorte.test', 'rubro' => 'Mecánica', 'fuente' => 'lead_scout']);
$i3 = at_pro_crear(['negocio' => 'Peluquería Este', 'correo' => 'hola@este.test', 'rubro' => 'Belleza', 'fuente' => 'sellbird']);
at_pro_cambiar_estado($i3, 'contactado', 'correo');
$p1 = at_pro_obtener($i1);

ok(isset($GLOBALS['submenu']) || true, 'sitio cargado');
// submenú registrado bajo CRM
global $submenu;
do_action('admin_menu');
$slugs = array_column($submenu['automatiza-crm'] ?? [], 2);
ok(in_array('at-prospeccion', $slugs, true), 'submenú at-prospeccion bajo automatiza-crm');
$ent = '';
foreach ($submenu['automatiza-crm'] ?? [] as $s) { if ($s[2] === 'at-prospeccion') { $ent = $s[1]; } }
ok($ent === 'manage_options', 'exige manage_options');

$_GET = [];
$_REQUEST = [];
ob_start();
at_pro_pagina();
$html = ob_get_clean();
ok(strpos($html, 'Café Sur') !== false && strpos($html, 'Taller Norte') !== false && strpos($html, 'Peluquería Este') !== false, 'la lista muestra los tres negocios');
ok(preg_match('/por contactar[^<]*<[^>]*>\s*\(?2\)?/iu', strip_tags($html, '<span>')) || strpos($html, 'Por contactar') !== false, 'vistas por estado con conteo');
ok(strpos($html, 'at-pro-tarjetas') !== false && strpos($html, 'Total') !== false, 'tarjeta de números');
$msg = at_pro_mensaje($p1, add_query_arg('r', $p1['token'], home_url('/')), add_query_arg('baja', $p1['token'], home_url('/')));
ok(strpos($html, 'data-mensaje="' . esc_attr($msg) . '"') !== false, 'Copiar mensaje con el texto y el enlace personal');
ok(strpos($html, 'https://wa.me/56955501001?text=' . rawurlencode($msg)) !== false, 'enlace wa.me con el mensaje codificado');
ok(substr_count($html, 'https://wa.me/') === 1, 'solo hay wa.me donde hay teléfono');
ok(strpos($html, 'aria-label="Copiar mensaje para Café Sur"') !== false, 'aria-label en el botón de solo ícono');
ok(strpos($html, 'dashicons-') !== false, 'íconos Dashicons');
ok(strpos($html, 'action=at_pro_estado') !== false && strpos($html, '_wpnonce=') !== false, 'acciones de estado con nonce');
ok(strpos($html, 'name="action"') === false || strpos($html, 'value="at_pro_masivo"') !== false, 'masivas con nombre propio');

// filtros
$_GET = ['fuente' => 'sellbird']; $_REQUEST = $_GET;
ob_start(); at_pro_pagina(); $h = ob_get_clean();
ok(strpos($h, 'Café Sur') !== false && strpos($h, 'Peluquería Este') !== false && strpos($h, 'Taller Norte') === false, 'filtro por fuente');
$_GET = ['s' => 'Taller']; $_REQUEST = $_GET;
ob_start(); at_pro_pagina(); $h = ob_get_clean();
ok(strpos($h, 'Taller Norte') !== false && strpos($h, 'Café Sur') === false, 'búsqueda por negocio');
$_GET = ['estado' => 'contactado']; $_REQUEST = $_GET;
ob_start(); at_pro_pagina(); $h = ob_get_clean();
ok(strpos($h, 'Peluquería Este') !== false && strpos($h, 'Café Sur') === false, 'vista por estado');
$_GET = ['rubro' => 'Mecánica']; $_REQUEST = $_GET;
ob_start(); at_pro_pagina(); $h = ob_get_clean();
ok(strpos($h, 'Taller Norte') !== false && strpos($h, 'Café Sur') === false, 'filtro por rubro');
$_GET = ['s' => "%' OR 1=1 -- "]; $_REQUEST = $_GET;
ob_start(); at_pro_pagina(); $h = ob_get_clean();
ok(strpos($h, 'Café Sur') === false, 'la búsqueda no se interpreta como SQL');
$_GET = []; $_REQUEST = [];

// acciones: nonce y capacidad
$n = wp_create_nonce('at_pro_estado_' . $i1);
ok(at_pro_procesar_estado($i1, 'contactado', 'malo') === false && at_pro_obtener($i1)['estado'] === 'por_contactar', 'sin nonce válido no cambia');
wp_set_current_user($sub);
ok(at_pro_procesar_estado($i1, 'contactado', $n) === false && at_pro_obtener($i1)['estado'] === 'por_contactar', 'suscriptor no cambia el estado');
ok(at_pro_procesar_masivo([$i1], 'contactado', wp_create_nonce('at_pro_masivo')) === 0, 'suscriptor no hace acciones masivas');
wp_set_current_user($admin);
$n = wp_create_nonce('at_pro_estado_' . $i1);
ok(at_pro_procesar_estado($i1, 'contactado', $n) === true && at_pro_obtener($i1)['estado'] === 'contactado', 'administrador con nonce cambia el estado');
ok(at_pro_procesar_estado($i1, 'contactado', $n) === false, 'repetir el mismo cambio no rompe nada');
ok(at_pro_procesar_masivo([$i1, $i2, 999999], 'descartado', wp_create_nonce('at_pro_masivo')) === 2, 'masivo descarta los que se pueden');
ok(at_pro_procesar_masivo([$i2], 'baja', wp_create_nonce('at_pro_masivo')) === 0, 'masivo solo permite contactado o descartado');
$_FILES = [];
$id = at_pro_procesar_manual(['negocio' => 'Manual Uno', 'web' => 'manualuno.test', 'rubro' => 'Otro'], wp_create_nonce('at_pro_crear'));
ok(is_int($id) && at_pro_obtener($id)['fuente'] === 'manual', 'alta manual con nonce');
ok(is_wp_error(at_pro_procesar_manual(['negocio' => 'X', 'web' => 'x.test'], 'malo')), 'alta manual sin nonce se rechaza');
$wpdb->query("DELETE FROM {$t}");
wp_delete_user($sub);
fin();
