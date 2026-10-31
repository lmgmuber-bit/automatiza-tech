<?php
// A qué proyecto o propuesta pertenece una reunión. Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/vinculo-texto-wp-test.php
// El texto se prueba con el módulo de ESTA copia del repo; las dos salidas (lista de reuniones y ficha del CRM) se revisan en
// los archivos de la rama, porque el WordPress local ejecuta las copias de PROD.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';

[$crm, $tech] = wa_cliente_crm('v', wa_con_espacios(wa_numero_libre()));
$k = wa_contrato($tech, 'servicios', 'signed', true);
wa_plan($k, 'Proyecto Prueba Texto');
$k2 = wa_contrato($tech, 'soporte', 'signed', true);
$pr = wa_propuesta(wa_correo('v'), '', 'sent', '[PRUEBA] Spa Texto');

ok(at_wa_vinculo_texto((object) ['contrato_id' => (string) $k, 'propuesta_id' => null]) === 'Proyecto: Proyecto Prueba Texto', 'reunión con contrato y plan: el nombre del proyecto');
ok(at_wa_vinculo_texto(['contrato_id' => $k2, 'propuesta_id' => null]) === 'Proyecto: Soporte técnico', 'reunión con contrato de soporte, como arreglo (ficha del CRM)');
ok(at_wa_vinculo_texto(['contrato_id' => null, 'propuesta_id' => (string) $pr]) === 'Propuesta: [PRUEBA] Spa Texto', 'reunión con propuesta');
ok(at_wa_vinculo_texto((object) ['contrato_id' => null, 'propuesta_id' => null]) === '' && at_wa_vinculo_texto((object) ['id' => 1]) === '', 'reunión sin vínculo o de antes de la migración: nada');
ok(at_wa_vinculo_texto(['contrato_id' => 999999999]) === '', 'un contrato que ya no existe: nada');

$base = __DIR__ . '/../../wp-content';
foreach ([['themes/automatiza-tech/inc/admin-followup-meetings.php', '$m'], ['mu-plugins/crm-ai-completo.php', '$fm']] as [$archivo, $var]) {
	$src = file_get_contents("$base/$archivo");
	ok(strpos($src, "function_exists('at_wa_vinculo_texto') && (\$at_wa_vinc = at_wa_vinculo_texto($var)) !== ''") !== false
		&& strpos($src, 'esc_html($at_wa_vinc)') !== false, "$archivo muestra el vínculo con function_exists y esc_html");
}
fin();
