<?php
// Enlace al portal del cliente en wa-contexto: solo para un cliente y solo si el teléfono de su ficha del CRM coincide.
// Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/portales-wp-test.php
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
require_once __DIR__ . '/fallos-wpdb.php';
global $wpdb;

if (!function_exists('at_crm_url_portal') || at_crm_url_portal(1) === '') {
	echo "NOT_RUN: el WordPress local no firma enlaces de fichas (falta la clave de las fichas)\n";
	exit(0);
}

// Cliente con su teléfono en la ficha
$n = wa_numero_libre();
[$crm, $tech] = wa_cliente_crm('p1', wa_con_espacios($n));
wa_contrato($tech, 'servicios', 'signed', true);
$c = at_wa_contexto('56' . $n);
ok($c['situacion'] === 'cliente' && count($c['portales']) === 1, 'cliente con su teléfono en la ficha → 1 portal');
ok(strpos($c['portales'][0]['url'] ?? '', 'crm_view=timeline&cid=' . $crm) !== false && ($c['portales'][0]['nombre'] ?? '') === '[PRUEBA] Empresa p1',
	'el portal trae la url firmada de su ficha y el nombre de la empresa');

// Cliente encontrado por su ficha operativa, pero el teléfono de la ficha del CRM es otro
$n2 = wa_numero_libre();
[$crm2, $tech2] = wa_cliente_crm('p2', wa_con_espacios(wa_numero_libre()));
$wpdb->update($wpdb->prefix . 'automatiza_tech_clients', ['phone' => wa_con_espacios($n2)], ['id' => $tech2]);
wa_contrato($tech2, 'servicios', 'signed', true);
$c2 = at_wa_contexto('56' . $n2);
ok($c2['situacion'] === 'cliente' && $c2['portales'] === [], 'cliente cuyo teléfono no es el de la ficha del CRM → sin portales');

// Prospecto
$n3 = wa_numero_libre();
wa_propuesta(wa_correo('p3'), wa_con_espacios($n3), 'sent', '[PRUEBA] Propuesta p3');
$c3 = at_wa_contexto('56' . $n3);
ok($c3['situacion'] === 'prospecto' && $c3['portales'] === [], 'prospecto → portales vacío');

// Dos fichas del CRM con el mismo teléfono
$n4 = wa_numero_libre();
[$crm4a, $tech4a] = wa_cliente_crm('p4a', wa_con_espacios($n4));
[$crm4b, $tech4b] = wa_cliente_crm('p4b', '56' . $n4);
wa_contrato($tech4a, 'servicios', 'signed', true);
wa_contrato($tech4b, 'soporte', 'signed', true);
$c4 = at_wa_contexto('+56' . $n4);
$nombres = array_column($c4['portales'], 'nombre');
ok(count($c4['portales']) === 2 && count(array_unique($nombres)) === 2, 'dos fichas con el mismo teléfono → 2 portales con nombres distintos');

// Base caída en la ficha del CRM → no_disponible con portales vacío
$c5 = con_falla('crm_clientes', function () use ($n) { return at_wa_contexto('56' . $n); });
ok($c5['situacion'] === 'no_disponible' && $c5['portales'] === [], 'base caída → no_disponible sin portales');

fin();
