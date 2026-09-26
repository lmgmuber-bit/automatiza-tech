<?php
/**
 * Ficha única de cliente (opción A): el CRM (wp_crm_clientes) es la ficha y la ficha operativa
 * (wp_automatiza_tech_clients: contratos, facturas, accesos) queda enlazada por crm_cliente_id.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Columna de enlace y enlace inicial por correo. Idempotente. */
function at_cc_migrar_esquema(): void {
	if (get_option('at_cierre_schema') === '1') {
		return;
	}
	if (get_transient('at_cc_migrar_intento')) {
		// Un intento anterior falló hace poco (tabla bloqueada, sin privilegio ALTER en el
		// hosting, etc.): se espera el enfriamiento en vez de repetir el ALTER en cada
		// admin_init/asegurar_cliente mientras el problema persista.
		return;
	}
	global $wpdb;
	$tech = $wpdb->prefix . 'automatiza_tech_clients';
	$crm = $wpdb->prefix . 'crm_clientes';
	$cols = $wpdb->get_col("SHOW COLUMNS FROM {$tech}");
	if (!$cols) {
		return;
	}
	$error_alter = '';
	if (!in_array('crm_cliente_id', $cols, true)) {
		$wpdb->query("ALTER TABLE {$tech} ADD COLUMN crm_cliente_id BIGINT(20) UNSIGNED NULL DEFAULT NULL, ADD INDEX idx_crm_cliente (crm_cliente_id)");
		$error_alter = $wpdb->last_error;
		$cols = $wpdb->get_col("SHOW COLUMNS FROM {$tech}");
	}
	if (!in_array('crm_cliente_id', $cols, true)) {
		// El ALTER falló (tabla bloqueada, sin privilegio ALTER en el hosting, etc.). No marcamos
		// el esquema como migrado, pero sí un enfriamiento de 5 minutos para no reintentar el
		// ALTER en cada admin_init/asegurar_cliente mientras el problema persista.
		set_transient('at_cc_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
		error_log('at_cc: no se pudo agregar crm_cliente_id a ' . $tech . ': ' . $error_alter);
		return;
	}
	$wpdb->query("UPDATE {$tech} t JOIN {$crm} c ON LOWER(TRIM(c.email)) = LOWER(TRIM(t.email)) SET t.crm_cliente_id = c.id WHERE t.crm_cliente_id IS NULL AND t.email <> ''");
	update_option('at_cierre_schema', '1');
}
add_action('admin_init', 'at_cc_migrar_esquema');

/** Id del CRM por correo; 0 si no hay. */
function at_cc_crm_de_email(string $email): int {
	global $wpdb;
	$email = at_cc_email_normalizado($email);
	if ($email === '') {
		return 0;
	}
	return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}crm_clientes WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
}

/** Ficha operativa enlazada a un cliente del CRM; null si no hay. */
function at_cc_tech_de_crm(int $crm_id): ?object {
	if ($crm_id <= 0) {
		return null;
	}
	global $wpdb;
	at_cc_migrar_esquema();
	// Un mismo cliente del CRM puede tener más de una ficha operativa enlazada (por ejemplo, una
	// vacía creada al convertirlo a cliente en el CRM y otra con el contrato al pasarlo a
	// contratado en Contactos). Se prefiere la que tiene plan o valor de contrato; entre varias
	// así, la más reciente.
	$fila = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d
		 ORDER BY (plan_id IS NOT NULL OR contract_value > 0) DESC, id DESC LIMIT 1",
		$crm_id
	));
	return $fila ?: null;
}

/**
 * Garantiza que la persona sea cliente en el CRM y tenga su ficha operativa enlazada.
 * Un cliente que ya era cliente conserva su fecha de contrato.
 */
function at_cc_asegurar_cliente(array $d) {
	global $wpdb;
	at_cc_migrar_esquema();
	$crm = $wpdb->prefix . 'crm_clientes';
	$tech = $wpdb->prefix . 'automatiza_tech_clients';
	$email = at_cc_email_normalizado((string) ($d['email'] ?? ''));
	if ($email === '' || !is_email($email)) {
		return new WP_Error('sin_correo', 'El cliente no tiene un correo válido.');
	}
	$nombre = trim((string) ($d['nombre'] ?? ''));
	$empresa = trim((string) ($d['empresa'] ?? ''));
	$telefono = trim((string) ($d['telefono'] ?? ''));
	$fecha = trim((string) ($d['fecha_contrato'] ?? '')) !== '' ? (string) $d['fecha_contrato'] : current_time('mysql');
	$nombre_ficha = $nombre !== '' ? $nombre : ($empresa !== '' ? $empresa : $email);

	$fila = $wpdb->get_row($wpdb->prepare("SELECT id, tipo, fecha_contrato FROM {$crm} WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
	if ($fila) {
		$crm_id = (int) $fila->id;
		$cambios = [];
		if ($fila->tipo !== 'cliente') {
			$cambios = ['tipo' => 'cliente', 'estado' => 'contratado', 'fecha_contrato' => $fecha];
		} elseif (empty($fila->fecha_contrato)) {
			$cambios = ['fecha_contrato' => $fecha];
		}
		if ($cambios) {
			$wpdb->update($crm, $cambios, ['id' => $crm_id]);
		}
	} else {
		$ok = $wpdb->insert($crm, [
			'nombre'         => $nombre_ficha,
			'email'          => $email,
			'empresa'        => $empresa,
			'telefono'       => $telefono,
			'tipo'           => 'cliente',
			'estado'         => 'contratado',
			'fecha_contrato' => $fecha,
			'fecha_contacto' => $fecha,
			'origen'         => (string) ($d['origen'] ?? 'propuesta_aceptada'),
		]);
		$crm_id = $ok ? (int) $wpdb->insert_id : 0;
		if (!$crm_id) {
			return new WP_Error('crm', 'No se pudo crear el cliente en el CRM: ' . $wpdb->last_error);
		}
	}

	$tech_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tech} WHERE crm_cliente_id = %d ORDER BY id ASC LIMIT 1", $crm_id));
	if (!$tech_id) {
		$tech_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tech} WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
		if ($tech_id) {
			$wpdb->update($tech, ['crm_cliente_id' => $crm_id], ['id' => $tech_id]);
		}
	}
	if (!$tech_id) {
		$nueva = [
			'crm_cliente_id'  => $crm_id,
			'name'            => mb_substr($nombre_ficha, 0, 100),
			'email'           => mb_substr($email, 0, 100),
			'company'         => mb_substr($empresa, 0, 100),
			'phone'           => mb_substr($telefono, 0, 20),
			'contracted_at'   => $fecha,
			'contract_status' => 'active',
			'project_type'    => mb_substr(trim((string) ($d['servicios'] ?? '')), 0, 100),
		];
		if (isset($d['valor']) && $d['valor'] !== null) {
			$nueva['contract_value'] = (float) $d['valor'];
		}
		$ok = $wpdb->insert($tech, $nueva);
		$tech_id = $ok ? (int) $wpdb->insert_id : 0;
		if (!$tech_id) {
			return new WP_Error('tech', 'No se pudo crear la ficha operativa: ' . $wpdb->last_error);
		}
	}
	return ['crm_id' => $crm_id, 'tech_id' => $tech_id];
}

/** «Crear ficha operativa» desde la ficha del CRM (solo para clientes). */
function at_cc_accion_crear_ficha_operativa(): void {
	$crm_id = (int) ($_POST['crm_id'] ?? 0);
	check_admin_referer('at_cc_crear_ficha_operativa_' . $crm_id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, empresa, telefono, tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	if ($c && $c->tipo === 'cliente') {
		at_cc_asegurar_cliente(['nombre' => $c->nombre, 'email' => $c->email, 'empresa' => $c->empresa, 'telefono' => $c->telefono, 'origen' => 'crm_manual']);
	}
	wp_safe_redirect(admin_url('admin.php?page=automatiza-crm-ficha&id=' . $crm_id));
	exit;
}
add_action('admin_post_at_cc_crear_ficha_operativa', 'at_cc_accion_crear_ficha_operativa');
