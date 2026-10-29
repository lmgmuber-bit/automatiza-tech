<?php
// Fichas de un número desde la base. Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/datos-wp-test.php
// Carga el módulo de ESTA copia del repo sobre el WordPress local. Crea sus filas con datos inventados y las borra al final.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/puras.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/datos.php';
global $wpdb;
$wpdb->show_errors(false);

$errores = [];
function sin_error(string $donde) { global $wpdb, $errores; if ($wpdb->last_error !== '') { $errores[] = $donde . ': ' . $wpdb->last_error; } $wpdb->last_error = ''; }

$p = $wpdb->prefix;
$marca = 'prueba-wa-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];
register_shutdown_function(function () use (&$creadas, $p) {
	global $wpdb;
	foreach (array_reverse($creadas) as [$tabla, $id]) { $wpdb->delete($p . $tabla, ['id' => $id]); }
});
function crear(string $tabla, array $fila): int {
	global $wpdb, $creadas;
	if (!$wpdb->insert($wpdb->prefix . $tabla, $fila)) { fwrite(STDERR, "No se pudo insertar en $tabla: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = [$tabla, (int) $wpdb->insert_id];
	return (int) $wpdb->insert_id;
}
// Un número de 9 dígitos que no está en ninguna de las cuatro tablas.
function numero_libre(): string {
	global $wpdb;
	$p = $wpdb->prefix;
	do {
		$n = '9' . str_pad((string) wp_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
		$like = '%' . substr($n, 1, 4) . '%' . substr($n, 5) . '%';
		$usado = 0;
		foreach ([['crm_clientes', 'telefono'], ['automatiza_tech_clients', 'phone'], ['automatiza_propuestas', 'phone'], ['automatiza_leads', 'phone']] as [$t, $c]) {
			$usado += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}{$t} WHERE {$c} LIKE %s", $like));
		}
	} while ($usado > 0);
	return $n;
}
$con_espacios = function (string $n9) { return '+56 ' . substr($n9, 0, 1) . ' ' . substr($n9, 1, 4) . ' ' . substr($n9, 5, 4); };
$correo = function (string $s) use ($marca) { return "{$marca}-{$s}@example.com"; };
$cliente_crm = function (string $s, string $tel) use ($correo) {
	$crm = crear('crm_clientes', ['nombre' => "julio prueba {$s}", 'email' => $correo($s), 'telefono' => $tel, 'empresa' => "[PRUEBA] Empresa {$s}", 'tipo' => 'cliente', 'estado' => 'contratado']);
	$tech = crear('automatiza_tech_clients', ['name' => "Julio Prueba {$s}", 'email' => $correo($s), 'phone' => '', 'crm_cliente_id' => $crm]);
	return [$crm, $tech];
};
$contrato = function (int $tech, string $tipo, string $estado, bool $firmado) use ($marca) {
	return crear('automatiza_contracts', ['client_id' => $tech, 'type' => $tipo, 'status' => $estado, 'contract_number' => 'P-' . substr(md5($marca . microtime(true)), 0, 10),
		'placeholders' => wp_json_encode(['servicios_contratados' => "- Servicio de prueba\n- Otro"]), 'sign_token' => hash('sha256', $marca . microtime(true)),
		'signed_at' => $firmado ? current_time('mysql') : null, 'template_id' => 'servicios_v1']);
};
$plan = function (int $contrato_id, string $proyecto) {
	$codigo = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
	crear('automatiza_planes_trabajo', ['contrato_id' => $contrato_id, 'codigo' => $codigo, 'estado' => 'enviado', 'payload' => wp_json_encode(['proyecto' => $proyecto])]);
	return $codigo;
};
$propuesta = function (string $email, string $tel, string $estado, string $empresa) use ($marca) {
	return crear('automatiza_propuestas', ['client_email' => $email, 'unique_link_id' => substr(md5($marca . $empresa . microtime(true)), 0, 12), 'client_name' => 'María Prueba',
		'company_name' => $empresa, 'phone' => $tel, 'status' => $estado, 'flujo' => 'v3', 'gamma_prompt_text' => '{}', 'transcript_text' => '', 'system_prompt_text' => '',
		'created_at' => current_time('mysql')]);
};

// 1) Cliente del CRM con contrato de servicios firmado y plan
$n1 = numero_libre();
[$crm1, $tech1] = $cliente_crm('a', $con_espacios($n1));
$k1 = $contrato($tech1, 'servicios', 'signed', true);
$codigo1 = $plan($k1, 'Proyecto Prueba WA');
$c = at_wa_contexto('569' . substr($n1, 1)); sin_error('contexto 569');
ok($c['situacion'] === 'cliente', 'cliente del CRM con contrato firmado (569…)');
$op = $c['opciones'][0] ?? [];
ok(($op['tipo'] ?? '') === 'proyecto' && ($op['id'] ?? 0) === $k1 && ($op['nombre'] ?? '') === 'Proyecto Prueba WA' && ($op['plan_codigo'] ?? '') === $codigo1, 'la opción es su proyecto, con el nombre y el código del plan');
ok(($c['agenda']['correo'] ?? '') === $correo('a') && ($c['nombre'] ?? '') === 'Julio', 'agenda con el correo de la ficha y el primer nombre');
$c9 = at_wa_contexto($n1); sin_error('contexto 9…');
ok($c9['situacion'] === 'cliente' && ($c9['opciones'][0]['id'] ?? 0) === $k1, 'el mismo número escrito como 9… da lo mismo');
$ce = at_wa_contexto('56' . $n1, 'Hola, quiero agendar mi seguimiento (código ' . $codigo1 . ')'); sin_error('contexto con código');
ok(($ce['elegida']['id'] ?? 0) === $k1, 'el código del plan en el texto elige ese proyecto');

// 2) Prospecto: propuesta enviada cuyo número solo está en la propuesta (sin ficha del CRM)
$n2 = numero_libre();
$pr2 = $propuesta($correo('b'), $con_espacios($n2), 'sent', '[PRUEBA] Spa Prueba');
$c2 = at_wa_contexto('56' . $n2); sin_error('contexto prospecto');
ok($c2['situacion'] === 'prospecto' && ($c2['opciones'][0]['tipo'] ?? '') === 'propuesta' && ($c2['opciones'][0]['id'] ?? 0) === $pr2, 'prospecto con su propuesta abierta, sin ficha del CRM');
ok(($c2['opciones'][0]['nombre'] ?? '') === '[PRUEBA] Spa Prueba' && ($c2['agenda']['correo'] ?? '') === $correo('b'), 'la propuesta se nombra por la empresa y la agenda sale de la propuesta');

// 3) Número desconocido
$c3 = at_wa_contexto('56' . numero_libre()); sin_error('contexto desconocido');
ok($c3['situacion'] === 'nuevo' && $c3['opciones'] === [] && $c3['elegida'] === null, 'número desconocido: nuevo');

// 4) Mismo número en dos fichas del CRM
$n4 = numero_libre();
[$crm4a, $tech4a] = $cliente_crm('c', $con_espacios($n4));
[$crm4b, $tech4b] = $cliente_crm('d', '56' . $n4);
$k4 = $contrato($tech4a, 'servicios', 'signed', true);
$c4 = at_wa_contexto($n4); sin_error('contexto compartido');
ok($c4['situacion'] === 'cliente' && $c4['nombre'] === '' && ($c4['agenda']['correo'] ?? 'x') === '', 'número en dos fichas: sin nombre y sin agenda de primer nivel');
ok(($c4['opciones'][0]['agenda']['correo'] ?? '') === $correo('c'), 'número en dos fichas: la opción lleva la agenda de su ficha');

// 5) Vínculo de una reunión
$v = at_wa_vinculo_valido($k1, 0, '56' . $n1, $correo('a')); sin_error('vínculo propio');
ok($v === ['contrato_id' => $k1, 'propuesta_id' => 0], 'el contrato propio se acepta');
$v = at_wa_vinculo_valido($k4, $pr2, '56' . $n1, $correo('a')); sin_error('vínculo ajeno');
ok($v === ['contrato_id' => 0, 'propuesta_id' => 0], 'el contrato y la propuesta de otro cliente se descartan');
$v = at_wa_vinculo_valido(0, $pr2, '', $correo('b')); sin_error('vínculo por correo');
ok($v['propuesta_id'] === $pr2, 'la propuesta se acepta por el correo aunque no venga el número');

ok($errores === [], 'ninguna consulta dejó error en la base' . ($errores ? ': ' . implode(' | ', $errores) : ''));
fin();
