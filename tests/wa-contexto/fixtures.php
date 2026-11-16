<?php
// Datos de prueba inventados para las pruebas de wa-contexto: se crean en el WordPress local y se borran al terminar.
// Requiere tests/cierre/wp-bootstrap.php antes.
global $wpdb;
$GLOBALS['wa_marca'] = 'prueba-wa-' . strtolower(wp_generate_password(6, false, false));
$GLOBALS['wa_creadas'] = [];
register_shutdown_function(function () {
	global $wpdb;
	foreach (array_reverse($GLOBALS['wa_creadas']) as [$tabla, $id]) {
		$wpdb->delete($wpdb->prefix . $tabla, ['id' => $id]);
	}
});

function wa_crear(string $tabla, array $fila): int {
	global $wpdb;
	if (!$wpdb->insert($wpdb->prefix . $tabla, $fila)) {
		fwrite(STDERR, "No se pudo insertar en $tabla: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['wa_creadas'][] = [$tabla, (int) $wpdb->insert_id];
	return (int) $wpdb->insert_id;
}

function wa_correo(string $s): string {
	return $GLOBALS['wa_marca'] . "-{$s}@example.com";
}

/** Un número de 9 dígitos que no está en ninguna de las cuatro tablas. */
function wa_numero_libre(): string {
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

function wa_con_espacios(string $n9): string {
	return '+56 ' . substr($n9, 0, 1) . ' ' . substr($n9, 1, 4) . ' ' . substr($n9, 5, 4);
}

/** Cliente del CRM con su ficha operativa enlazada: [crm_id, tech_id]. */
function wa_cliente_crm(string $s, string $tel): array {
	$crm = wa_crear('crm_clientes', ['nombre' => "julio prueba {$s}", 'email' => wa_correo($s), 'telefono' => $tel, 'empresa' => "[PRUEBA] Empresa {$s}", 'tipo' => 'cliente', 'estado' => 'contratado']);
	$tech = wa_crear('automatiza_tech_clients', ['name' => "Julio Prueba {$s}", 'email' => wa_correo($s), 'phone' => '', 'crm_cliente_id' => $crm]);
	return [$crm, $tech];
}

function wa_contrato(int $tech, string $tipo, string $estado, bool $firmado): int {
	$m = $GLOBALS['wa_marca'];
	return wa_crear('automatiza_contracts', ['client_id' => $tech, 'type' => $tipo, 'status' => $estado, 'contract_number' => 'P-' . substr(md5($m . microtime(true) . wp_rand()), 0, 10),
		'placeholders' => wp_json_encode(['servicios_contratados' => "- Servicio de prueba\n- Otro"]), 'sign_token' => hash('sha256', $m . microtime(true) . wp_rand()),
		'signed_at' => $firmado ? current_time('mysql') : null, 'template_id' => 'servicios_v1']);
}

/** Plan de trabajo del contrato; devuelve su código. */
function wa_plan(int $contrato_id, string $proyecto): string {
	$codigo = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
	wa_crear('automatiza_planes_trabajo', ['contrato_id' => $contrato_id, 'codigo' => $codigo, 'estado' => 'enviado', 'payload' => wp_json_encode(['proyecto' => $proyecto])]);
	return $codigo;
}

function wa_propuesta(string $email, string $tel, string $estado, string $empresa): int {
	return wa_crear('automatiza_propuestas', ['client_email' => $email, 'unique_link_id' => substr(md5($GLOBALS['wa_marca'] . $empresa . microtime(true) . wp_rand()), 0, 12),
		'client_name' => 'María Prueba', 'company_name' => $empresa, 'phone' => $tel, 'status' => $estado, 'flujo' => 'v3', 'gamma_prompt_text' => '{}',
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
}

/** Diagnóstico gratis (automatiza_leads) de prueba; devuelve el id. */
function wa_lead(string $correo, string $tel, string $fecha, string $hora, string $estado = 'active'): int {
	return wa_crear('automatiza_leads', ['name' => 'Ana Prueba', 'email' => $correo, 'phone' => $tel, 'scheduled_date' => $fecha,
		'scheduled_time' => $hora . ':00', 'status' => $estado, 'source' => 'prueba', 'created_at' => current_time('mysql'),
		'google_event_id' => 'evento-prueba-' . $GLOBALS['wa_marca']]);
}

/** Reunión de seguimiento de prueba; devuelve el id. */
function wa_seguimiento(string $correo, string $tel, string $fecha, string $hora, string $estado = 'scheduled'): int {
	return wa_crear('automatiza_followup_meetings', ['client_name' => 'Ana Prueba', 'client_email' => $correo, 'phone' => $tel,
		'company_name' => '[PRUEBA] Empresa', 'meeting_date' => $fecha, 'meeting_time' => $hora . ':00', 'status' => $estado,
		'meeting_subject' => 'Seguimiento de prueba', 'google_event_id' => 'evento-seg-' . $GLOBALS['wa_marca'], 'created_at' => current_time('mysql')]);
}

/** Una fecha futura 'Y-m-d' a $dias días de hoy. */
function wa_dia(int $dias): string {
	return wp_date('Y-m-d', time() + $dias * DAY_IN_SECONDS);
}
