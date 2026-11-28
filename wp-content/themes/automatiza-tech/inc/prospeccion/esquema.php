<?php
/** Esquema de Rastro · Prospección, versión 1: tabla {prefix}at_prospectos. */
if (!defined('ABSPATH')) { exit; }

function at_pro_tabla(): string {
	global $wpdb;
	return $wpdb->prefix . 'at_prospectos';
}

function at_pro_esquema_listo(): bool {
	global $wpdb;
	if (get_option('at_pro_db_version') !== '1') { return false; }
	$t = at_pro_tabla();
	return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) === $t;
}

/** Crea la tabla sin tocar filas existentes; solo registra la versión si la tabla quedó creada. */
function at_pro_migrar_esquema(): void {
	global $wpdb;
	if (at_pro_esquema_listo()) { return; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = at_pro_tabla();
	$cotejo = $wpdb->get_charset_collate();
	dbDelta("CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		negocio varchar(190) NOT NULL DEFAULT '',
		rubro varchar(100) NOT NULL DEFAULT '',
		comuna varchar(100) NOT NULL DEFAULT '',
		web varchar(500) NOT NULL DEFAULT '',
		dominio varchar(190) DEFAULT NULL,
		telefono varchar(30) NOT NULL DEFAULT '',
		correo varchar(190) NOT NULL DEFAULT '',
		fuente varchar(30) NOT NULL DEFAULT 'manual',
		senales text NOT NULL,
		servicio_sugerido varchar(100) NOT NULL DEFAULT '',
		estado varchar(20) NOT NULL DEFAULT 'por_contactar',
		canal varchar(20) NOT NULL DEFAULT '',
		token char(16) NOT NULL,
		crm_cliente_id bigint(20) unsigned DEFAULT NULL,
		contactado_at datetime DEFAULT NULL,
		respondio_at datetime DEFAULT NULL,
		formulario_at datetime DEFAULT NULL,
		baja_at datetime DEFAULT NULL,
		notas text NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		UNIQUE KEY dominio (dominio),
		KEY estado (estado),
		KEY telefono (telefono),
		KEY correo (correo)
	) {$cotejo};");
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) !== $t) { return; }
	update_option('at_pro_db_version', '1', false);
}

/** Camino normal: solo compara la versión guardada (sin consultas extra a la base en cada petición). */
add_action('init', function () {
	if (get_option('at_pro_db_version') !== '1') { at_pro_migrar_esquema(); }
}, 20);
