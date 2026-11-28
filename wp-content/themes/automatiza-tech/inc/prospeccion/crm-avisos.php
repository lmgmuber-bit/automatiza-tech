<?php
/**
 * Rastro · Prospección: paso al CRM y avisos cuando un prospecto llena el formulario.
 * El CRM recibe un *prospecto* (nunca convierte en cliente a nadie: por eso no se usa at_cc_asegurar_cliente).
 */
if (!defined('ABSPATH')) { exit; }

/** Id de la fila del CRM para esa persona: la existente (sin tocar tipo, estado ni un origen ya puesto) o una nueva de tipo prospecto. */
function at_pro_crm_prospecto(array $persona, array $p): int {
	global $wpdb;
	$crm = $wpdb->prefix . 'crm_clientes';
	$email = strtolower(trim((string) ($persona['email'] ?? '')));
	if (!is_email($email)) { return 0; }
	$fila = $wpdb->get_row($wpdb->prepare("SELECT id, origen FROM {$crm} WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
	if ($fila) {
		if (trim((string) $fila->origen) === '') { $wpdb->update($crm, ['origen' => 'prospeccion'], ['id' => (int) $fila->id]); }
		return (int) $fila->id;
	}
	$nombre = trim(sanitize_text_field((string) ($persona['nombre'] ?? '')));
	$ok = $wpdb->insert($crm, [
		'tipo' => 'prospecto', 'estado' => 'nuevo', 'origen' => 'prospeccion',
		'nombre' => $nombre !== '' ? $nombre : (string) $p['negocio'],
		'email' => $email,
		'telefono' => trim(sanitize_text_field((string) ($persona['telefono'] ?? ''))),
		'empresa' => (string) $p['negocio'], 'rubro' => (string) $p['rubro'],
		'fecha_contacto' => current_time('mysql'),
		'notas' => 'Viene de Prospección (Rastro): ' . (string) $p['negocio'],
	]);
	return $ok ? (int) $wpdb->insert_id : 0;
}

/** Lo que pasa cuando el prospecto llena el formulario: vínculo con el CRM, correo y WhatsApp a Luis. Se engancha a at_pro_llenado. */
function at_pro_al_llenar(array $p, array $persona): void {
	global $wpdb;
	$crm_id = at_pro_crm_prospecto($persona, $p);
	if ($crm_id > 0) {
		$wpdb->update(at_pro_tabla(), ['crm_cliente_id' => $crm_id, 'updated_at' => current_time('mysql')], ['id' => (int) $p['id']]);
	}
	$ficha = $crm_id > 0 ? admin_url('admin.php?page=automatiza-crm-ficha&id=' . $crm_id) : admin_url('admin.php?page=at-prospeccion');
	$contactado = !empty($p['contactado_at']) ? mysql2date('d-m-Y', $p['contactado_at']) : 'sin registrar';
	$canal = (string) ($p['canal'] ?? '') !== '' ? (string) $p['canal'] : 'sin registrar';
	$quien = trim((string) ($persona['nombre'] ?? '')) . ' <' . (string) ($persona['email'] ?? '') . '>';
	$tel = trim((string) ($persona['telefono'] ?? ''));

	$para = (string) get_option('at_pro_aviso_correo', get_option('admin_email'));
	if (is_email($para)) {
		$cuerpo = "Viene de Prospección: {$p['negocio']}, contactado el {$contactado} (canal: {$canal}).\n\n"
			. "Llenó el formulario: {$quien}" . ($tel !== '' ? "\nTeléfono: {$tel}" : '') . "\n\n"
			. "Ficha en el CRM: {$ficha}\nLista de prospección: " . admin_url('admin.php?page=at-prospeccion') . "\n";
		wp_mail($para, 'Prospección: ' . $p['negocio'] . ' llenó el formulario', $cuerpo);
	}

	// WhatsApp por n8n con plantilla aprobada (Utilidad). Sin las constantes del servidor o si falla, el correo ya salió.
	if (defined('AT_N8N_RASTRO_AVISO') && defined('AT_RASTRO_AVISO_KEY') && AT_N8N_RASTRO_AVISO !== '' && AT_RASTRO_AVISO_KEY !== '') {
		$r = wp_remote_post((string) AT_N8N_RASTRO_AVISO, [
			'timeout' => 8,
			'headers' => ['Content-Type' => 'application/json', 'X-AT-Rastro-Key' => (string) AT_RASTRO_AVISO_KEY],
			'body' => wp_json_encode(['negocio' => (string) $p['negocio'], 'nombre' => (string) ($persona['nombre'] ?? ''), 'fecha_contacto' => $contactado, 'crm_url' => $ficha]),
		]);
		$codigo = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
		if (is_wp_error($r) || $codigo < 200 || $codigo >= 300) {
			error_log('[Rastro] El aviso por WhatsApp no salió (' . (is_wp_error($r) ? $r->get_error_code() : 'HTTP ' . $codigo) . '); el correo sí.');
		}
	}
}
add_action('at_pro_llenado', 'at_pro_al_llenar', 10, 2);
