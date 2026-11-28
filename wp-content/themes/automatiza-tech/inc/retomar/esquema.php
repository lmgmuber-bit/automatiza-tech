<?php
/** Esquema local del módulo Retomar, versión 2 (suma notas, redacción, aprobación y fotos). */
if (!defined('ABSPATH')) {
	exit;
}

/** Columnas que suma la versión 2; sin ellas el esquema no está listo aunque la opción lo diga. */
function at_rt_columnas_v2(): array {
	return ['notas', 'redaccion', 'aprobacion', 'fotos'];
}

function at_rt_esquema_listo(): bool {
	global $wpdb;
	if (get_option('at_rt_schema') !== '2') {
		return false;
	}
	foreach (['at_retomar', 'at_retomar_bajas'] as $sufijo) {
		$tabla = $wpdb->prefix . $sufijo;
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tabla))) !== $tabla) {
			return false;
		}
	}
	$existentes = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}at_retomar", 0);
	return !array_diff(at_rt_columnas_v2(), is_array($existentes) ? $existentes : []);
}

/** Crea las tablas o suma las columnas nuevas sin alterar filas existentes; no registra éxito si falta algo. */
function at_rt_migrar_esquema(): void {
	global $wpdb;
	if (at_rt_esquema_listo()) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$tabla = $wpdb->prefix . 'at_retomar';
	$bajas = $wpdb->prefix . 'at_retomar_bajas';
	$cotejo = $wpdb->get_charset_collate();
	dbDelta("CREATE TABLE {$tabla} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		crm_cliente_id int(11) DEFAULT NULL,
		propuesta_id int(11) DEFAULT NULL,
		nombre varchar(190) NOT NULL DEFAULT '',
		negocio varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		telefono varchar(40) NOT NULL DEFAULT '',
		wa_autorizado tinyint(1) NOT NULL DEFAULT 0,
		propuesta_url varchar(500) NOT NULL DEFAULT '',
		demo_urls text NOT NULL,
		imagen_url varchar(500) NOT NULL DEFAULT '',
		textos longtext NOT NULL,
		max_envios tinyint unsigned NOT NULL DEFAULT 4,
		intervalo_dias tinyint unsigned NOT NULL DEFAULT 7,
		canales varchar(40) NOT NULL DEFAULT 'correo,whatsapp',
		estado varchar(20) NOT NULL DEFAULT 'borrador',
		token varchar(64) NOT NULL DEFAULT '',
		render longtext NOT NULL,
		envios longtext NOT NULL,
		activado_at datetime DEFAULT NULL,
		proximo_at date DEFAULT NULL,
		enviando datetime DEFAULT NULL,
		creado_at datetime NOT NULL,
		actualizado_at datetime NOT NULL,
		notas text NOT NULL,
		redaccion longtext NOT NULL,
		aprobacion longtext NOT NULL,
		fotos longtext NOT NULL,
		PRIMARY KEY  (id),
		KEY token (token),
		KEY email (email),
		KEY telefono (telefono),
		KEY estado_proximo (estado,proximo_at)
	) {$cotejo};");
	dbDelta("CREATE TABLE {$bajas} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL DEFAULT '',
		telefono varchar(40) NOT NULL DEFAULT '',
		origen varchar(30) NOT NULL DEFAULT '',
		motivo varchar(255) NOT NULL DEFAULT '',
		creado_at datetime NOT NULL,
		revertido_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY email (email),
		KEY telefono (telefono)
	) {$cotejo};");
	foreach ([$tabla, $bajas] as $t) {
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) !== $t) {
			return;
		}
	}
	$existentes = $wpdb->get_col("SHOW COLUMNS FROM {$tabla}", 0);
	if (!is_array($existentes) || array_diff(at_rt_columnas_v2(), $existentes)) {
		return;
	}
	update_option('at_rt_schema', '2', false);
}
