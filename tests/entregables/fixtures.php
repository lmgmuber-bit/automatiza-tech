<?php
// Cliente de prueba (CRM + ficha operativa + entregable) y limpieza por marca. Se carga después de wp-bootstrap.php.
function en_fx_cliente(string $marca): array {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Cliente ' . $marca, 'email' => $marca . '@example.com', 'empresa' => 'Empresa ' . $marca, 'telefono' => '+56911111111', 'tipo' => 'cliente', 'estado' => 'prueba']);
	$crm = (int) $wpdb->insert_id;
	$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', ['name' => 'Cliente ' . $marca, 'email' => $marca . '@example.com', 'company' => 'Empresa ' . $marca, 'phone' => '+56911111111', 'crm_cliente_id' => $crm]);
	$tech = (int) $wpdb->insert_id;
	$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $tech, 'detail_type' => 'entregable', 'title' => 'Propuestas ' . $marca, 'status' => 'completed', 'attachment_url' => 'https://example.com/v1', 'created_by' => 1]);
	return ['crm' => $crm, 'tech' => $tech, 'detalle' => (int) $wpdb->insert_id];
}

function en_fx_limpiar(string $marca): void {
	global $wpdb;
	$crm_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}crm_clientes WHERE email = %s", $marca . '@example.com'));
	foreach ($crm_ids as $crm) {
		$techs = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm));
		foreach ($techs as $tech) {
			$dets = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}automatiza_clients_details WHERE client_id = %d", $tech));
			foreach ($dets as $d) {
				$e = function_exists('at_en_por_detalle') ? at_en_por_detalle((int) $d) : null;
				if ($e) {
					if (function_exists('at_en_borrar_imagenes')) {
						at_en_borrar_imagenes((int) $e->id);
					}
					$t = at_en_tablas();
					$wpdb->delete($t['n'], ['entregable_id' => $e->id]);
					$wpdb->delete($t['v'], ['entregable_id' => $e->id]);
					$wpdb->delete($t['e'], ['id' => $e->id]);
				}
				$wpdb->delete($wpdb->prefix . 'automatiza_clients_details', ['id' => $d]);
			}
			$wpdb->delete($wpdb->prefix . 'automatiza_tech_clients', ['id' => $tech]);
		}
		$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $crm]);
		$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $crm]);
	}
}
