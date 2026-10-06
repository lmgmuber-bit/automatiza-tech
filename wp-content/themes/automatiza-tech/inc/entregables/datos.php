<?php
/**
 * Entregables: tres tablas propias (no se toca wp_automatiza_clients_details) e historial en el CRM.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_tablas(): array {
	global $wpdb;
	return ['e' => $wpdb->prefix . 'at_entregables', 'v' => $wpdb->prefix . 'at_entregable_versiones', 'n' => $wpdb->prefix . 'at_entregable_notas'];
}

/** Crea las tablas con dbDelta. Idempotente (opción at_en_db_version = '1'); si falla, reintenta en 5 minutos. */
function at_en_migrar_esquema(): void {
	if (get_option('at_en_db_version') === '1' || get_transient('at_en_migrar_intento')) {
		return;
	}
	global $wpdb;
	$t = at_en_tablas();
	$c = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta("CREATE TABLE {$t['e']} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  detalle_id BIGINT UNSIGNED NOT NULL,
  crm_id BIGINT UNSIGNED NOT NULL,
  codigo CHAR(12) NOT NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'abierto',
  version_vigente INT UNSIGNED NOT NULL DEFAULT 0,
  creado_at DATETIME NOT NULL,
  actualizado_at DATETIME NULL,
  cerrado_at DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_detalle (detalle_id),
  UNIQUE KEY uniq_codigo (codigo),
  KEY idx_crm (crm_id)
) {$c};");
	dbDelta("CREATE TABLE {$t['v']} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entregable_id BIGINT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  mensaje TEXT NULL,
  enviado_correo_at DATETIME NULL,
  enviado_whatsapp_at DATETIME NULL,
  creado_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_numero (entregable_id,numero)
) {$c};");
	dbDelta("CREATE TABLE {$t['n']} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entregable_id BIGINT UNSIGNED NOT NULL,
  version_numero INT UNSIGNED NOT NULL DEFAULT 0,
  autor VARCHAR(10) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  texto TEXT NOT NULL,
  imagenes TEXT NULL,
  respondida TINYINT(1) NOT NULL DEFAULT 0,
  aviso_ok TINYINT(1) NOT NULL DEFAULT 1,
  ip_hash CHAR(64) NULL,
  creado_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_entregable (entregable_id),
  KEY idx_pendientes (entregable_id,autor,respondida)
) {$c};");
	foreach ($t as $tabla) {
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tabla))) !== $tabla) {
			set_transient('at_en_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
			error_log('at_en: no se pudo crear ' . $tabla . ': ' . $wpdb->last_error);
			return;
		}
	}
	update_option('at_en_db_version', '1');
}
add_action('admin_init', 'at_en_migrar_esquema');

/** Fila de wp_automatiza_clients_details solo si es un entregable; null si no. */
function at_en_detalle(int $detalle_id): ?object {
	if ($detalle_id <= 0) {
		return null;
	}
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_clients_details WHERE id = %d AND detail_type = 'entregable'", $detalle_id));
	return $f ?: null;
}

/** Cliente del CRM de un detalle, por su ficha operativa; 0 si no está enlazada. */
function at_en_crm_de_detalle(object $detalle): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $detalle->client_id));
}

function at_en_por_id(int $id): ?object {
	if ($id <= 0) {
		return null;
	}
	at_en_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['e'] . ' WHERE id = %d', $id));
	return $f ?: null;
}

/** Por código exacto (la columna compara sin mayúsculas: se exige igualdad exacta). */
function at_en_por_codigo(string $codigo): ?object {
	if (!at_en_codigo_valido($codigo)) {
		return null;
	}
	at_en_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['e'] . ' WHERE codigo = %s', $codigo));
	return ($f && hash_equals((string) $f->codigo, $codigo)) ? $f : null;
}

function at_en_por_detalle(int $detalle_id): ?object {
	if ($detalle_id <= 0) {
		return null;
	}
	at_en_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['e'] . ' WHERE detalle_id = %d', $detalle_id));
	return $f ?: null;
}

/** Activa las notas de un entregable; si ya estaba, devuelve el mismo id. */
function at_en_activar(int $detalle_id) {
	$d = at_en_detalle($detalle_id);
	if (!$d) {
		return new WP_Error('no_entregable', 'Ese detalle no es un entregable.');
	}
	$ya = at_en_por_detalle($detalle_id);
	if ($ya) {
		return (int) $ya->id;
	}
	$crm = at_en_crm_de_detalle($d);
	if ($crm <= 0) {
		return new WP_Error('sin_crm', 'La ficha operativa no está enlazada a un cliente del CRM.');
	}
	global $wpdb;
	$t = at_en_tablas()['e'];
	for ($i = 0; $i < 5; $i++) {
		$codigo = wp_generate_password(12, false);
		$ok = $wpdb->insert($t, ['detalle_id' => $detalle_id, 'crm_id' => $crm, 'codigo' => $codigo, 'estado' => 'abierto', 'version_vigente' => 0, 'creado_at' => current_time('mysql')]);
		if ($ok) {
			return (int) $wpdb->insert_id;
		}
		$ya = at_en_por_detalle($detalle_id); // otro clic lo activó entretanto
		if ($ya) {
			return (int) $ya->id;
		}
	}
	return new WP_Error('no_guardo', 'No se pudo activar el entregable.');
}

/** Crea la versión siguiente dentro de una transacción (dos clics no crean dos v2). Devuelve su número. */
function at_en_crear_version(int $ent_id, string $url, string $mensaje) {
	$url = at_en_url_version_valida($url);
	if ($url === '') {
		return new WP_Error('url_invalida', 'El enlace de la versión debe empezar con http(s):// y no puede ser de easypanel.');
	}
	$m = at_en_validar_mensaje($mensaje);
	if (!$m['ok']) {
		return new WP_Error($m['error'], 'El mensaje es muy largo (máximo 6.000 caracteres).');
	}
	global $wpdb;
	$t = at_en_tablas();
	$wpdb->query('START TRANSACTION');
	$vig = $wpdb->get_var($wpdb->prepare("SELECT version_vigente FROM {$t['e']} WHERE id = %d FOR UPDATE", $ent_id));
	if ($vig === null) {
		$wpdb->query('ROLLBACK');
		return new WP_Error('sin_entregable', 'El entregable no existe.');
	}
	$n = (int) $vig + 1;
	$ok = $wpdb->insert($t['v'], ['entregable_id' => $ent_id, 'numero' => $n, 'url' => $url, 'mensaje' => $m['mensaje'], 'creado_at' => current_time('mysql')]);
	$ok2 = $ok && $wpdb->update($t['e'], ['version_vigente' => $n, 'actualizado_at' => current_time('mysql')], ['id' => $ent_id]) !== false;
	if (!$ok2) {
		$wpdb->query('ROLLBACK');
		return new WP_Error('no_guardo', 'No se pudo guardar la versión.');
	}
	$wpdb->query('COMMIT');
	return $n;
}

function at_en_version(int $ent_id, int $numero): ?object {
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['v'] . ' WHERE entregable_id = %d AND numero = %d', $ent_id, $numero));
	return $f ?: null;
}

function at_en_versiones(int $ent_id): array {
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['v'] . ' WHERE entregable_id = %d ORDER BY numero ASC', $ent_id));
}

function at_en_marcar_version_enviada(int $ent_id, int $numero, string $canal): bool {
	$col = ['correo' => 'enviado_correo_at', 'whatsapp' => 'enviado_whatsapp_at'][$canal] ?? '';
	if ($col === '' || !at_en_version($ent_id, $numero)) {
		return false;
	}
	global $wpdb;
	return $wpdb->update(at_en_tablas()['v'], [$col => current_time('mysql')], ['entregable_id' => $ent_id, 'numero' => $numero]) !== false;
}

/** Agrega una nota sobre la versión vigente. $imagenes: nombres internos ya guardados (0 a 3). */
function at_en_agregar_nota(int $ent_id, string $autor, string $nombre, string $texto, array $imagenes, string $ip_hash) {
	if (!in_array($autor, ['cliente', 'at'], true)) {
		return new WP_Error('autor', 'Autor desconocido.');
	}
	$v = at_en_validar_nota($nombre, $texto);
	if (!$v['ok']) {
		return new WP_Error($v['error'], at_en_mensajes()[$v['error']] ?? 'Nota inválida.');
	}
	if (count($imagenes) > AT_EN_MAX_IMAGENES) {
		return new WP_Error('muchas_imagenes', at_en_mensajes()['muchas_imagenes']);
	}
	$e = at_en_por_id($ent_id);
	if (!$e) {
		return new WP_Error('sin_entregable', 'El entregable no existe.');
	}
	global $wpdb;
	$ok = $wpdb->insert(at_en_tablas()['n'], [
		'entregable_id' => $ent_id, 'version_numero' => (int) $e->version_vigente, 'autor' => $autor,
		'nombre' => $v['nombre'], 'texto' => $v['texto'], 'imagenes' => wp_json_encode(array_values($imagenes)),
		'respondida' => 0, 'aviso_ok' => 1, 'ip_hash' => $ip_hash !== '' ? $ip_hash : null, 'creado_at' => current_time('mysql'),
	]);
	return $ok ? (int) $wpdb->insert_id : new WP_Error('no_guardo', at_en_mensajes()['no_guardo']);
}

function at_en_nota(int $id): ?object {
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['n'] . ' WHERE id = %d', $id));
	return $f ?: null;
}

function at_en_notas(int $ent_id): array {
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['n'] . ' WHERE entregable_id = %d ORDER BY id ASC', $ent_id));
}

function at_en_marcar_respondida(int $nota_id): void {
	global $wpdb;
	$wpdb->update(at_en_tablas()['n'], ['respondida' => 1], ['id' => $nota_id, 'autor' => 'cliente']);
}

function at_en_marcar_aviso(int $nota_id, bool $ok): void {
	global $wpdb;
	$wpdb->update(at_en_tablas()['n'], ['aviso_ok' => $ok ? 1 : 0], ['id' => $nota_id]);
}

function at_en_cambiar_estado(int $ent_id, string $estado): bool {
	if (!in_array($estado, ['abierto', 'cerrado'], true)) {
		return false;
	}
	global $wpdb;
	$campos = ['estado' => $estado, 'actualizado_at' => current_time('mysql'), 'cerrado_at' => $estado === 'cerrado' ? current_time('mysql') : null];
	return (bool) $wpdb->update(at_en_tablas()['e'], $campos, ['id' => $ent_id]);
}

function at_en_sin_responder(int $ent_id): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_en_tablas()['n'] . " WHERE entregable_id = %d AND autor = 'cliente' AND respondida = 0", $ent_id));
}

function at_en_sin_responder_crm(int $crm_id): int {
	at_en_migrar_esquema();
	global $wpdb;
	$t = at_en_tablas();
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['n']} n JOIN {$t['e']} e ON e.id = n.entregable_id WHERE e.crm_id = %d AND n.autor = 'cliente' AND n.respondida = 0", $crm_id));
}

/** Entregables de las fichas operativas del cliente, con los datos del módulo si están activados. */
function at_en_de_crm(int $crm_id): array {
	if ($crm_id <= 0) {
		return [];
	}
	at_en_migrar_esquema();
	global $wpdb;
	$t = at_en_tablas();
	return (array) $wpdb->get_results($wpdb->prepare(
		"SELECT d.*, e.id AS en_id, e.codigo AS en_codigo, e.estado AS en_estado, e.version_vigente AS en_version
		 FROM {$wpdb->prefix}automatiza_clients_details d
		 JOIN {$wpdb->prefix}automatiza_tech_clients c ON c.id = d.client_id
		 LEFT JOIN {$t['e']} e ON e.detalle_id = d.id
		 WHERE c.crm_cliente_id = %d AND d.detail_type = 'entregable' ORDER BY d.id DESC",
		$crm_id
	));
}

function at_en_historial(int $crm_id, string $tipo, string $titulo, string $desc): void {
	if ($crm_id > 0 && function_exists('at_cc_historial_crm')) {
		at_cc_historial_crm($crm_id, $tipo, $titulo, $desc);
	}
}
