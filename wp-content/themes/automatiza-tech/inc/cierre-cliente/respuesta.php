<?php
/** Registrar la respuesta del cliente y, si acepta, cerrar: cliente, bienvenida y contrato. */
if (!defined('ABSPATH')) {
	exit;
}

function at_cc_propuesta_por_codigo(string $codigo): ?object {
	global $wpdb;
	$codigo = trim($codigo);
	if ($codigo === '' || strlen($codigo) > 50) {
		return null;
	}
	$p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE unique_link_id = %s", $codigo));
	return $p ?: null;
}

function at_cc_propuesta_por_id(int $id): ?object {
	global $wpdb;
	$p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
	return $p ?: null;
}

function at_cc_filas_de_propuesta(object $p): array {
	return at_cc_filas_de_payload((string) $p->gamma_prompt_text);
}

/** Registro simple en Seguimiento; devuelve su id. $descripcion puede llegar a la línea de tiempo
 *  pública del cliente (mismo riesgo documentado en at_cc_anotar_respuesta): nunca lleva datos
 *  personales. Lo que solo debe ver Luis (RUT, razón social, dirección, etc.) va en $metadata. */
function at_cc_anotar_simple(object $p, string $tipo, string $titulo, string $descripcion = '', array $metadata = []): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', [
		'propuesta_id'   => (int) $p->id,
		'detail_type'    => $tipo,
		'title'          => mb_substr($titulo, 0, 250),
		'description'    => $descripcion,
		'status'         => 'completed',
		'completed_date' => current_time('Y-m-d'),
		'metadata'       => $metadata ? wp_json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
		'created_by'     => get_current_user_id() ?: null,
		'created_at'     => current_time('mysql'),
	]);
	return (int) $wpdb->insert_id;
}

/** Registro de la respuesta en Seguimiento. La descripción es lo que puede ver el cliente (llega a
 *  la línea de tiempo pública de «Ver mi portal», protegida solo por un token calculable): salida,
 *  canal, fecha y filas aceptadas. Nombre, RUT, comentario/nota interna y evidencias quedan solo en
 *  metadata (de ahí los leen at_cc_ultima_respuesta y el panel), y attachment_url/attachment_name no
 *  se llenan con la evidencia para que el archivo no aparezca en esa misma línea de tiempo pública. */
function at_cc_anotar_respuesta(object $p, string $salida, array $d, bool $cambio_estado): int {
	global $wpdb;
	$titulos = ['acepta' => 'Aceptó la propuesta', 'evalua' => 'La sigue evaluando', 'rechaza' => 'No aceptó la propuesta'];
	$lineas = ['Cómo: ' . at_cc_canal_texto($d)];
	if (!empty($d['filas'])) {
		$lineas[] = 'Acepta: ' . implode('; ', array_map(function ($f) { return trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? '')); }, $d['filas']));
	}
	if (!$cambio_estado) {
		$lineas[] = '(Sin cambio de estado)';
	}
	$meta = [
		'salida' => $salida, 'canal' => (string) ($d['canal'] ?? ''), 'canal_manual' => (string) ($d['canal_manual'] ?? ''),
		'nombre' => (string) ($d['nombre'] ?? ''), 'rut' => (string) ($d['rut'] ?? ''), 'comentario' => (string) ($d['comentario'] ?? ''), 'filas' => (array) ($d['filas'] ?? []),
		'fecha_declarada' => (string) ($d['fecha'] ?? ''), 'ip' => (string) ($d['ip'] ?? ''), 'agente' => (string) ($d['agente'] ?? ''),
		'telefono' => (string) ($d['telefono'] ?? ''), 'wamid' => (string) ($d['wamid'] ?? ''),
		'huella' => at_cc_huella((string) $p->gamma_prompt_text), 'evidencias' => (array) ($d['evidencias'] ?? []),
		'cambio_estado' => $cambio_estado, 'registrado_por' => (int) ($d['usuario_id'] ?? 0),
	];
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', [
		'propuesta_id'    => (int) $p->id,
		'detail_type'     => 'respuesta_cliente',
		'title'           => $titulos[$salida] ?? $salida,
		'description'     => implode("\n", $lineas),
		'status'          => 'completed',
		'completed_date'  => substr((string) ($d['fecha'] ?? current_time('mysql')), 0, 10),
		'attachment_url'  => null,
		'attachment_name' => null,
		'attachment_type' => null,
		'metadata'        => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
		'created_by'      => ((int) ($d['usuario_id'] ?? 0)) ?: null,
		'created_at'      => current_time('mysql'),
	]);
	return (int) $wpdb->insert_id;
}

/** Última respuesta REAL del cliente registrada en Seguimiento: ignora las notas simples con el
 *  mismo detail_type (las agrega Luis a mano desde el widget de Seguimiento, o at_cc_anotar_simple()
 *  en las Tasks 10/10b), que no traen 'salida' en su metadata y no deben tapar la respuesta real. Con
 *  $salida se exige además que coincida (bienvenida y panel piden la última con salida 'acepta'). */
function at_cc_ultima_respuesta(int $propuesta_id, ?string $salida = null): ?array {
	global $wpdb;
	$filas = $wpdb->get_results($wpdb->prepare(
		"SELECT title, created_at, metadata FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC",
		$propuesta_id
	));
	foreach ((array) $filas as $f) {
		$m = json_decode((string) $f->metadata, true) ?: [];
		$s = (string) ($m['salida'] ?? '');
		if ($s === '' || ($salida !== null && $s !== $salida)) {
			continue;
		}
		return [
			'titulo'     => (string) $f->title,
			'fecha'      => (string) $f->created_at,
			'salida'     => $s,
			'filas'      => (array) ($m['filas'] ?? []),
			'evidencias' => (array) ($m['evidencias'] ?? []),
		];
	}
	return null;
}

/** Registra la respuesta y, si acepta, ejecuta el cierre. Idempotente frente a una segunda aceptación. */
function at_cc_registrar_respuesta(object $p, string $salida, array $d): array {
	global $wpdb;
	$base = ['ok' => false, 'estado' => (string) $p->status, 'mensaje' => '', 'avisos' => [], 'avisos_operativos' => [], 'crm_id' => null, 'contrato_id' => null];
	$salidas = at_cc_salidas();
	if (!isset($salidas[$salida])) {
		return array_merge($base, ['mensaje' => 'Respuesta no válida.']);
	}
	$hacia = $salidas[$salida];
	$desde = (string) $p->status;
	if ($desde === 'aceptada') {
		return array_merge($base, ['ok' => true, 'mensaje' => 'ya_aceptada']);
	}
	if ($desde === $hacia) {
		at_cc_anotar_respuesta($p, $salida, $d, false);
		if (!empty($d['comentario'])) {
			at_cc_avisar_luis($p, $salida, $d, $base);
		}
		return array_merge($base, ['ok' => true, 'mensaje' => 'anotada']);
	}
	$manual = ($d['canal'] ?? '') === 'manual';
	if (!at_cc_transicion_respuesta_valida($desde, $hacia, $manual)) {
		// Una propuesta rechazada sigue ofreciendo «la sigo evaluando» / «no, gracias» (barra
		// pública y correo «Pedir respuesta»): no es un error, se anota y se avisa a Luis sin
		// cambiar el estado.
		if ($desde === 'rechazada' && $salida !== 'acepta') {
			at_cc_anotar_respuesta($p, $salida, $d, false);
			at_cc_avisar_luis($p, $salida, $d, $base);
			return array_merge($base, ['ok' => true, 'mensaje' => 'anotada']);
		}
		return array_merge($base, ['mensaje' => 'Esta propuesta no está esperando respuesta.']);
	}
	$n = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}automatiza_propuestas SET status = %s WHERE id = %d AND status = %s", $hacia, (int) $p->id, $desde));
	if ($n !== 1) {
		return array_merge($base, ['mensaje' => 'La propuesta cambió mientras respondías.']);
	}
	$p->status = $hacia;
	at_cc_anotar_respuesta($p, $salida, $d, true);
	$r = array_merge($base, ['ok' => true, 'estado' => $hacia, 'mensaje' => 'registrada']);
	if ($salida === 'acepta') {
		$r = array_merge($r, at_cc_ejecutar_cierre($p, $d));
	}
	if (!empty($r['avisos'])) {
		// Un paso del cierre falló (p. ej. correo inválido: no se pudo pasar a cliente): además del
		// correo a Luis (que un canal manual ni manda), queda un rastro en Seguimiento. Tipo propio
		// para no romper at_cc_ultima_respuesta() ni aparecer en la línea de tiempo pública del
		// cliente (excluido en crm-ai-completo.php).
		at_cc_anotar_simple($p, 'cierre_incompleto', 'Cierre incompleto', implode("\n", $r['avisos']));
	}
	if (!empty($r['avisos_operativos'])) {
		// T6 ronda 2, hallazgo 2: un recordatorio operativo (p. ej. datos bancarios sin configurar) no
		// es un paso fallido -el cliente se creó, el contrato quedó en borrador y la bienvenida salió-,
		// así que no comparte el 'cierre_incompleto' de los fallos reales; queda con su propio tipo,
		// también excluido de la línea de tiempo pública del cliente (crm-ai-completo.php).
		at_cc_anotar_simple($p, 'aviso_operativo', 'Revisar datos bancarios', implode("\n", $r['avisos_operativos']));
	}
	at_cc_avisar_luis($p, $salida, $d, $r);
	return $r;
}

/** Cliente oficial, Seguimiento migrado, bienvenida y contrato. Cada paso que falla queda como aviso,
 *  incluida una excepción (p. ej. FPDF sin poder escribir el PDF): nunca se deja escapar, o
 *  at_cc_avisar_luis() (que corre justo después, en at_cc_registrar_respuesta) no llegaría a correr.
 *  'avisos' son pasos que realmente fallaron; 'avisos_operativos' son recordatorios de configuración
 *  (p. ej. datos bancarios pendientes) que no representan ningún fallo del cierre (T6 ronda 2,
 *  hallazgo 2): los dos llegan al correo de Luis, pero solo 'avisos' deja el rastro de
 *  'cierre_incompleto' en Seguimiento. */
function at_cc_ejecutar_cierre(object $p, array $d): array {
	$avisos = [];
	$avisos_operativos = [];
	$filas = (array) ($d['filas'] ?? []);
	try {
		$cli = at_cc_asegurar_cliente([
			'nombre'         => trim((string) ($d['nombre'] ?? '')) !== '' ? (string) $d['nombre'] : (string) $p->client_name,
			'email'          => (string) $p->client_email,
			'empresa'        => (string) $p->company_name,
			'telefono'       => (string) $p->phone,
			'origen'         => 'propuesta_aceptada',
			'valor'          => at_cc_total_unico($filas),
			'servicios'      => implode(' + ', array_map(function ($f) { return trim((string) ($f['service'] ?? '')); }, $filas)),
			'fecha_contrato' => (string) ($d['fecha'] ?? current_time('mysql')),
		]);
	} catch (\Throwable $e) {
		return ['avisos' => ['No se pudo pasar a cliente: ' . $e->getMessage()], 'crm_id' => null, 'contrato_id' => null];
	}
	if (is_wp_error($cli)) {
		return ['avisos' => ['No se pudo pasar a cliente: ' . $cli->get_error_message()], 'crm_id' => null, 'contrato_id' => null];
	}
	try {
		if (function_exists('automatiza_migrate_prospect_to_client')) {
			automatiza_migrate_prospect_to_client($cli['tech_id'], (int) $p->id);
		}
	} catch (\Throwable $e) {
		$avisos[] = 'El Seguimiento no se migró: ' . $e->getMessage();
	}
	try {
		at_cc_historial_crm($cli['crm_id'], 'conversion', 'Aceptó la propuesta', 'Propuesta ' . $p->unique_link_id . ' aceptada ' . at_cc_canal_texto($d) . '.');
	} catch (\Throwable $e) {
		$avisos[] = 'No se pudo anotar el historial del CRM: ' . $e->getMessage();
	}
	if (!empty($d['bienvenida'])) {
		try {
			if (!at_cc_enviar_bienvenida($cli['crm_id'], $p, $filas)) {
				$avisos[] = 'El correo de bienvenida no salió (revisa el SMTP).';
			} elseif (!at_cc_banco_completo(at_cc_datos_banco())) {
				$avisos_operativos[] = 'Faltan los datos bancarios (Propuestas › Ajustes del cierre): envíale al cliente los datos de transferencia.';
			}
		} catch (\Throwable $e) {
			$avisos[] = 'El correo de bienvenida no salió: ' . $e->getMessage();
		}
	}
	$contrato_id = null;
	try {
		$contrato = at_cc_crear_contrato_servicios($p, $cli['tech_id'], $filas, [
			'nombre'      => (string) ($d['nombre'] ?? ''),
			'rut'         => (string) ($d['rut'] ?? ''),
			'fecha'       => (string) ($d['fecha'] ?? current_time('mysql')),
			'canal_texto' => at_cc_canal_texto($d),
		]);
		if (is_wp_error($contrato)) {
			$avisos[] = 'El contrato no se creó: ' . $contrato->get_error_message();
		} else {
			$contrato_id = $contrato;
		}
	} catch (\Throwable $e) {
		$avisos[] = 'El contrato no se creó: ' . $e->getMessage();
	}
	return ['avisos' => $avisos, 'avisos_operativos' => $avisos_operativos, 'crm_id' => $cli['crm_id'], 'contrato_id' => $contrato_id];
}

/** Aviso a Luis por correo (WhatsApp a Luis no llega: 131047). No se avisa lo que él mismo registró. */
function at_cc_avisar_luis(object $p, string $salida, array $d, array $r): void {
	if (($d['canal'] ?? '') === 'manual') {
		return;
	}
	$titulos = ['acepta' => 'aceptó la propuesta ✅', 'evalua' => 'la sigue evaluando 🤔', 'rechaza' => 'no aceptó la propuesta ❌'];
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$lineas = ['Cómo: ' . at_cc_canal_texto($d)];
	foreach (['nombre' => 'Nombre', 'rut' => 'RUT', 'comentario' => 'Comentario'] as $k => $t) {
		if (!empty($d[$k])) {
			$lineas[] = $t . ': ' . $d[$k];
		}
	}
	if (!empty($d['filas'])) {
		$lineas[] = 'Acepta: ' . implode('; ', array_map(function ($f) { return trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? '')); }, $d['filas']));
	}
	foreach ((array) ($r['avisos'] ?? []) as $a) {
		$lineas[] = '⚠️ ' . $a;
	}
	foreach ((array) ($r['avisos_operativos'] ?? []) as $a) {
		$lineas[] = 'ℹ️ ' . $a;
	}
	$html = '<p>' . implode('<br>', array_map('esc_html', $lineas)) . '</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$c = !empty($r['contrato_id']) ? at_cc_contrato_de_propuesta((int) $p->id) : null;
	if ($c && in_array($c->status, ['draft', 'at_pending'], true)) {
		$html .= '<p><a href="' . esc_url(home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token)) . '">Revisar, ajustar y firmar el contrato</a></p>';
	}
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	wp_mail((string) get_option('admin_email'), $quien . ' ' . ($titulos[$salida] ?? $salida), $html, ['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>']);
}

/** Correo corto que pide la respuesta, con el bloque de aceptar. */
function at_cc_enviar_pedido_respuesta(object $p): bool {
	if (!is_email((string) $p->client_email)) {
		return false;
	}
	$base = get_site_url();
	$bloque = at_cc_bloque_aceptar_html(at_cc_url_respuesta($base, (string) $p->unique_link_id, 'aceptar'), at_cc_url_respuesta($base, (string) $p->unique_link_id, 'evaluar'));
	$html = at_cc_pedido_respuesta_html((string) $p->client_name, (string) $p->company_name, $bloque, AT_CC_LOGO, at_cc_url_respuesta($base, (string) $p->unique_link_id));
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$asunto = 'Tu propuesta de AutomatizaTech' . (trim((string) $p->company_name) !== '' ? ' para ' . $p->company_name : '');
	return (bool) wp_mail((string) $p->client_email, $asunto, $html, [
		'Content-Type: text/html; charset=UTF-8',
		'From: Automatiza Tech <' . $from . '>',
		'Reply-To: ' . get_option('admin_email'),
		'Bcc: automatizacionesbotcore@gmail.com',
	]);
}
