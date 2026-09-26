<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/contrato-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

ok(ContractService::archivo_plantilla('servicios_v1') === 'CONTRATO_SERVICIO_DESARROLLO.md', 'plantilla de servicios por id');
ok(ContractService::archivo_plantilla('soporte_v2') === 'CONTRATO_SOPORTE_POSTPROYECTO.md' && ContractService::archivo_plantilla('otra') === 'CONTRATO_SOPORTE_POSTPROYECTO.md', 'soporte por defecto');
ok(strpos(ContractService::load_template('servicios_v1'), 'DESARROLLO E IMPLEMENTACIÓN') !== false, 'load_template carga la de servicios');
ok(strpos(ContractService::load_template('soporte_v2'), 'POST-PROYECTO') !== false, 'load_template sigue cargando la de soporte');
ok(strpos(ContractService::titulo_por_tipo('servicios'), 'DESARROLLO') !== false && strpos(ContractService::titulo_por_tipo('soporte'), 'POST-PROYECTO') !== false, 'título por tipo');

$c = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles', 'monto_total' => '$1.000'], 'created_by' => 0]);
ok(is_object($c) && $c->type === 'servicios' && $c->template_id === 'servicios_v1' && $c->status === 'at_pending', 'contrato de servicios creado en at_pending');
$ph = json_decode($c->placeholders, true);
ok(strpos($ph['contract_title'], 'DESARROLLO') !== false, 'título de servicios guardado');
ok(ContractService::necesita_revision($c), 'necesita revisión antes de firmar');
$firma = ContractService::sign_as_at($c->id, ['signer_name' => 'Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba@example.com', 'method' => 'canvas', 'signature_dataurl' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==']);
ok(is_wp_error($firma) && $firma->get_error_code() === 'sin_revision', 'no se firma sin revisión');
$r = ContractService::guardar_revision($c->id, ['plazo' => 'Ocho semanas', 'rut_cliente' => '11.111.111-1', 'monto_total' => '', 'inventado' => 'x']);
$ph2 = json_decode($r->placeholders, true);
ok(!is_wp_error($r) && $ph2['plazo'] === 'Ocho semanas' && $ph2['rut_cliente'] === '11.111.111-1' && !isset($ph2['monto_total']) && !isset($ph2['inventado']) && !empty($ph2['revision_at']), 'revisión guardada: cambia, borra lo vaciado e ignora claves desconocidas');
ok(!ContractService::necesita_revision($r), 'ya no necesita revisión');
$firma2 = ContractService::sign_as_at($c->id, ['signer_name' => 'Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba@example.com', 'method' => 'canvas', 'signature_dataurl' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==']);
ok(!is_wp_error($firma2) && $firma2->status === 'at_signed', 'con revisión se firma');
ok(is_wp_error(ContractService::guardar_revision($c->id, ['plazo' => 'x'])), 'firmado ya no se edita');
$sop = ContractService::create_contract(['client_id' => 0, 'type' => 'soporte', 'template_id' => 'soporte_v2', 'placeholders' => [], 'created_by' => 0]);
ok(is_object($sop) && !ContractService::necesita_revision($sop), 'el de soporte no pide revisión');

$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id IN (%d, %d)", $c->id, $sop->id));
fin();
