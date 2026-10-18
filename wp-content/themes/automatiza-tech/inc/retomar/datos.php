<?php
/** Persistencia de Retomar y supresión por persona, independiente de otras comunicaciones. */
if (!defined('ABSPATH')) {
	exit;
}

/** Solo estos campos pueden cambiar; identidad técnica y token se asignan al crear. */
function at_rt_campos(array $campos): ?array {
	$permitidos = ['crm_cliente_id', 'propuesta_id', 'nombre', 'negocio', 'email', 'telefono', 'wa_autorizado', 'propuesta_url', 'demo_urls', 'imagen_url', 'textos', 'max_envios', 'intervalo_dias', 'canales', 'estado', 'render', 'envios', 'activado_at', 'proximo_at', 'enviando'];
	$d = array_intersect_key($campos, array_flip($permitidos));
	foreach (['crm_cliente_id', 'propuesta_id'] as $k) {
		if (array_key_exists($k, $d)) { $d[$k] = (int) $d[$k] > 0 ? (int) $d[$k] : null; }
	}
	foreach (['nombre', 'negocio', 'canales'] as $k) {
		if (isset($d[$k])) { $d[$k] = sanitize_text_field((string) $d[$k]); }
	}
	if (isset($d['email'])) { $d['email'] = strtolower(sanitize_email(trim((string) $d['email']))); }
	if (isset($d['telefono'])) { $d['telefono'] = at_pt_telefono_wa((string) $d['telefono']); }
	if (isset($d['wa_autorizado'])) { $d['wa_autorizado'] = empty($d['wa_autorizado']) ? 0 : 1; }
	foreach (['propuesta_url', 'imagen_url'] as $k) {
		if (isset($d[$k])) { $d[$k] = esc_url_raw((string) $d[$k], ['https']); }
	}
	foreach (['demo_urls', 'textos', 'render', 'envios'] as $k) {
		if (array_key_exists($k, $d)) {
			if (!is_array($d[$k])) { return null; }
			$d[$k] = wp_json_encode($d[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($d[$k] === false) { return null; }
		}
	}
	if (isset($d['max_envios'])) { $d['max_envios'] = at_rt_max((int) $d['max_envios']); }
	if (isset($d['intervalo_dias'])) { $d['intervalo_dias'] = min(255, at_rt_intervalo((int) $d['intervalo_dias'])); }
	if (isset($d['estado']) && !in_array($d['estado'], ['borrador', 'activo', 'pausado', 'agendo', 'respondio', 'cliente', 'baja', 'agotado', 'rechazado'], true)) { return null; }
	foreach (['activado_at', 'proximo_at', 'enviando'] as $k) {
		if (array_key_exists($k, $d)) {
			if ($d[$k] === '' || $d[$k] === null) { $d[$k] = null; continue; }
			$formato = $k === 'proximo_at' ? 'Y-m-d' : 'Y-m-d H:i:s';
			$fecha = DateTimeImmutable::createFromFormat('!' . $formato, (string) $d[$k]);
			if (!$fecha || $fecha->format($formato) !== $d[$k]) { return null; }
		}
	}
	return $d;
}

/** Normaliza tipos y decodifica JSON sin devolver contenido escalar corrupto. */
function at_rt_fila(array $fila): array {
	foreach (['id', 'max_envios', 'intervalo_dias', 'wa_autorizado'] as $k) { $fila[$k] = (int) $fila[$k]; }
	foreach (['crm_cliente_id', 'propuesta_id'] as $k) { $fila[$k] = $fila[$k] === null ? null : (int) $fila[$k]; }
	foreach (['demo_urls', 'textos', 'render', 'envios'] as $k) {
		$json = json_decode((string) $fila[$k], true);
		$fila[$k] = is_array($json) ? $json : [];
	}
	return $fila;
}

function at_rt_crear(array $campos): int {
	global $wpdb;
	at_rt_migrar_esquema();
	$d = at_rt_campos($campos);
	if ($d === null) { return 0; }
	$ahora = current_time('mysql');
	$d += ['demo_urls' => '[]', 'textos' => '[]', 'render' => '[]', 'envios' => '[]'];
	$d['creado_at'] = $d['actualizado_at'] = $ahora;
	if ($wpdb->insert($wpdb->prefix . 'at_retomar', $d) === false) { return 0; }
	$id = (int) $wpdb->insert_id;
	if ($wpdb->update($wpdb->prefix . 'at_retomar', ['token' => at_rt_token($id, wp_salt('auth'))], ['id' => $id]) === false) {
		$wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]);
		return 0;
	}
	return $id;
}

function at_rt_obtener(int $id): ?array {
	global $wpdb;
	if ($id <= 0) { return null; }
	$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id = %d", $id), ARRAY_A);
	return $f ? at_rt_fila($f) : null;
}

function at_rt_por_token(string $t): ?array {
	global $wpdb;
	if (!preg_match('/^[a-f0-9]{32}$/D', $t)) { return null; }
	$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE token = %s LIMIT 1", $t), ARRAY_A);
	return $f && hash_equals((string) $f['token'], $t) ? at_rt_fila($f) : null;
}

function at_rt_guardar(int $id, array $campos): bool {
	global $wpdb;
	if (!at_rt_obtener($id)) { return false; }
	$d = at_rt_campos($campos);
	if ($d === null) { return false; }
	$d['actualizado_at'] = current_time('mysql');
	return $wpdb->update($wpdb->prefix . 'at_retomar', $d, ['id' => $id]) !== false;
}

function at_rt_lista(): array {
	global $wpdb;
	return array_map('at_rt_fila', $wpdb->get_results("SELECT * FROM {$wpdb->prefix}at_retomar ORDER BY id DESC", ARRAY_A));
}

/**
 * Un contacto: {tipo:'envio',n,fecha,at,correo:{estado},whatsapp:null|{estado,wamid?,codigo?,titulo?}}.
 * Los eventos {tipo:'diferido'|'detenido',fecha,nota/motivo} no consumen recordatorios.
 * El resultado WhatsApp actualiza el subobjeto del mismo contacto, sin agregar otro envío.
 */
function at_rt_envios_realizados(array $eventos): int {
	$realizados = array_filter($eventos, static fn($e) => is_array($e) && ($e['tipo'] ?? '') === 'envio' && ($e['correo']['estado'] ?? '') === 'enviado');
	return count($realizados);
}

/** Bloquea la fila durante la lectura/escritura para no perder eventos concurrentes. */
function at_rt_agregar_envio(int $id, array $evento): bool {
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return false; }
	$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id = %d FOR UPDATE", $id), ARRAY_A);
	if (!$f) { $wpdb->query('ROLLBACK'); return false; }
	$envios = at_rt_fila($f)['envios'];
	$envios[] = $evento;
	$ok = at_rt_guardar($id, ['envios' => $envios]);
	if (!$ok) { $wpdb->query('ROLLBACK'); return false; }
	return $wpdb->query('COMMIT') !== false;
}

/** Compara también teléfonos legados que aún conservan espacios o prefijos locales. */
function at_rt_contacto_coincide(string $email, string $telefono, array $fila): bool {
	return ($email !== '' && strtolower(trim((string) $fila['email'])) === $email)
		|| ($telefono !== '' && at_pt_telefono_wa((string) $fila['telefono']) === $telefono);
}

function at_rt_en_bajas(string $email, string $telefono_wa): bool {
	global $wpdb;
	$email = strtolower(trim($email));
	$telefono = at_pt_telefono_wa($telefono_wa);
	if ($email === '' && $telefono === '') { return false; }
	$filas = $wpdb->get_results("SELECT email, telefono FROM {$wpdb->prefix}at_retomar_bajas WHERE revertido_at IS NULL", ARRAY_A);
	foreach ($filas as $f) {
		if (at_rt_contacto_coincide($email, $telefono, $f)) { return true; }
	}
	return false;
}

function at_rt_dar_baja(int $id, string $origen): bool {
	global $wpdb;
	$s = at_rt_obtener($id);
	if (!$s) { return false; }
	$email = strtolower(trim($s['email']));
	$telefono = at_pt_telefono_wa($s['telefono']);
	if ($wpdb->query('START TRANSACTION') === false) { return false; }
	// Conservar ambos identificadores también cuando uno ya aparecía en otra supresión.
	$misma_baja = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar_bajas WHERE email = %s AND telefono = %s AND revertido_at IS NULL LIMIT 1", $email, $telefono));
	if (($email !== '' || $telefono !== '') && !$misma_baja) {
		if ($wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $email, 'telefono' => $telefono, 'origen' => substr(sanitize_key($origen), 0, 30), 'creado_at' => current_time('mysql')]) === false) {
			$wpdb->query('ROLLBACK'); return false;
		}
	}
	$filas = $wpdb->get_results("SELECT id, email, telefono FROM {$wpdb->prefix}at_retomar", ARRAY_A);
	foreach ($filas as $f) {
		if ((int) $f['id'] !== $id && !at_rt_contacto_coincide($email, $telefono, $f)) { continue; }
		if (!at_rt_guardar((int) $f['id'], ['estado' => 'baja', 'proximo_at' => null])) {
			$wpdb->query('ROLLBACK'); return false;
		}
	}
	return $wpdb->query('COMMIT') !== false;
}

function at_rt_revertir_baja(int $baja_id, string $motivo): bool {
	global $wpdb;
	$motivo = trim(sanitize_text_field($motivo));
	if ($motivo === '') { return false; }
	// Solo cambia la supresión; todos los seguimientos permanecen detenidos.
	return $wpdb->update($wpdb->prefix . 'at_retomar_bajas', ['revertido_at' => current_time('mysql'), 'motivo' => mb_substr($motivo, 0, 255)], ['id' => $baja_id, 'revertido_at' => null]) === 1;
}

function at_rt_contexto_parada(array $seg): array {
	global $wpdb;
	$email = strtolower(trim((string) ($seg['email'] ?? '')));
	$crm = (int) ($seg['crm_cliente_id'] ?? 0);
	$propuesta = (int) ($seg['propuesta_id'] ?? 0);
	$activado = (string) ($seg['activado_at'] ?? '');
	return [
		'baja' => at_rt_en_bajas($email, (string) ($seg['telefono'] ?? '')),
		'reunion' => $email !== '' && $activado !== '' && (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}automatiza_followup_meetings WHERE LOWER(client_email) = %s AND created_at > %s LIMIT 1", $email, $activado)),
		'crm_tipo' => $crm > 0 ? (string) $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm)) : '',
		'propuesta_estado' => $propuesta > 0 ? (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $propuesta)) : '',
		'enviados' => at_rt_envios_realizados($seg['envios'] ?? []),
		'max' => at_rt_max((int) ($seg['max_envios'] ?? 4)),
	];
}
