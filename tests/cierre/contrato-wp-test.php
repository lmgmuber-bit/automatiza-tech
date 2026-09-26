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

// Texto del PDF: descomprime los flujos de FPDF y junta las cadenas que dibuja (Tj), en UTF-8
// y con los espacios colapsados. Los saltos de línea del PDF quedan como un espacio.
function texto_pdf(string $archivo): string {
	$bin = (string) @file_get_contents($archivo);
	$flujos = [];
	if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bin, $m)) {
		foreach ($m[1] as $s) {
			$d = @gzuncompress($s);
			$flujos[] = $d !== false ? $d : $s;
		}
	}
	preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)\s*Tj/s', implode("\n", $flujos), $t);
	$cadenas = array_map(function ($x) { return strtr($x, ['\\\\' => '\\', '\\(' => '(', '\\)' => ')', '\\r' => "\r"]); }, $t[1]);
	$texto = (string) @iconv('ISO-8859-1', 'UTF-8', implode(' ', $cadenas));
	return trim((string) preg_replace('/\s+/u', ' ', $texto));
}
function pdf_de($c): string { return ContractService::storage_dir() . '/' . $c->contract_number . '.pdf'; }
$firma_png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
$datos_firma = ['signer_name' => 'Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba@example.com', 'method' => 'canvas', 'signature_dataurl' => $firma_png];

// ---------- Task 5b: el párrafo del cliente según sea persona natural o empresa ----------
$datos = ['razon_social_cliente' => '[PRUEBA] Muebles SpA', 'rut_cliente' => '10.000.013-K', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'email_cliente' => 'ana@example.com', 'telefono_cliente' => '+56 9 1111 1111', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'nombre_proyecto' => 'Tienda en línea'];
$emp = ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa'] + $datos);
ok($emp === '**[PRUEBA] Muebles SpA** (en adelante "**EL CLIENTE**"), RUT **10.000.013-K**, representada por **Ana Prueba**, RUT **11.111.111-1**, correo ana@example.com, teléfono +56 9 1111 1111, con domicilio en Calle Falsa 123, Santiago.', 'empresa: comparece representada, con el texto aprobado');
$per_datos = ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Ana Prueba', 'rut_cliente' => '11.111.111-1'] + $datos;
$per = ContractService::comparecencia_cliente($per_datos);
ok($per === '**Ana Prueba** (en adelante "**EL CLIENTE**"), RUT **11.111.111-1**, correo ana@example.com, teléfono +56 9 1111 1111, con domicilio en Calle Falsa 123, Santiago, para su proyecto «Tienda en línea».', 'persona: con su proyecto y el texto aprobado');
ok(strpos($per, 'representada por') === false && substr($per, -strlen('para su proyecto «Tienda en línea».')) === 'para su proyecto «Tienda en línea».', 'persona: sin «representada por» y termina con su proyecto');
$fin_domicilio = 'con domicilio en Calle Falsa 123, Santiago.';
$per_igual = ContractService::comparecencia_cliente(['nombre_proyecto' => '  ana   PRUEBA '] + $per_datos);
ok(substr($per_igual, -strlen($fin_domicilio)) === $fin_domicilio && strpos($per_igual, 'para su proyecto') === false, 'persona: si el proyecto se llama como ella (sin mayúsculas ni espacios de más), no se repite');
$per_sin = ContractService::comparecencia_cliente(['nombre_proyecto' => ''] + $per_datos);
ok(substr($per_sin, -strlen($fin_domicilio)) === $fin_domicilio && strpos($per_sin, 'para su proyecto') === false, 'persona sin proyecto: termina en el domicilio');
$sin_tipo = ContractService::comparecencia_cliente($datos);
ok(strpos($sin_tipo, 'representada por') === false && strpos($sin_tipo, 'para su proyecto') === false && substr($sin_tipo, -strlen($fin_domicilio)) === $fin_domicilio, 'sin tipo: borrador neutro, sin representante ni proyecto');
ok(ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa']) === '**_______** (en adelante "**EL CLIENTE**"), RUT **_______**, representada por **_______**, RUT **_______**, correo _______, teléfono _______, con domicilio en _______.', 'los datos vacíos salen como _______');

// ---------- Task 5b: no se firma con datos esenciales en blanco ----------
$ct = function (array $ph, string $tipo = 'servicios') { return (object) ['type' => $tipo, 'placeholders' => wp_json_encode($ph)]; };
$completo_persona = ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Ana Prueba', 'rut_cliente' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'monto_total' => '$1.000', 'forma_pago' => '50 % al firmar y 50 % a la entrega.'];
$completo_empresa = ['tipo_cliente' => 'empresa', 'razon_social_cliente' => '[PRUEBA] Muebles SpA', 'rut_cliente' => '10.000.013-K', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $completo_persona;
ok(in_array('Tipo de cliente', ContractService::faltantes($ct(array_diff_key($completo_persona, ['tipo_cliente' => 1]))), true), 'sin tipo: falta «Tipo de cliente»');
ok(in_array('Tipo de cliente', ContractService::faltantes($ct(['tipo_cliente' => 'sociedad'] + $completo_persona)), true), 'un tipo desconocido cuenta como sin tipo');
ok(ContractService::faltantes($ct($completo_persona)) === [], 'persona completa: no falta nada');
ok(ContractService::faltantes($ct(['tipo_cliente' => 'persona'])) === ['Nombre completo del cliente', 'RUT del cliente', 'Domicilio del cliente', 'Precio total, IVA incluido', 'Forma de pago'], 'persona vacía: faltan sus datos, con las etiquetas de persona');
ok(ContractService::faltantes($ct($completo_empresa)) === [], 'empresa completa: no falta nada');
ok(ContractService::faltantes($ct(array_diff_key($completo_empresa, ['representante_cliente_nombre' => 1, 'representante_cliente_rut' => 1]))) === ['Representante (solo si es empresa)', 'RUT del representante (solo si es empresa)'], 'empresa sin representante: lo lista');
ok(ContractService::faltantes($ct(['tipo_cliente' => 'empresa'])) === ['Razón social', 'RUT de la empresa', 'Representante (solo si es empresa)', 'RUT del representante (solo si es empresa)', 'Domicilio del cliente', 'Precio total, IVA incluido', 'Forma de pago'], 'empresa vacía: faltan sus datos, con las etiquetas de empresa');
ok(ContractService::faltantes($ct(['rut_cliente' => '11.111.111-2'] + $completo_persona)) === ['RUT del cliente (no es válido)'], 'RUT inválido: cuenta como faltante');
ok(ContractService::faltantes($ct(['representante_cliente_rut' => '11.111.111-2'] + $completo_empresa)) === ['RUT del representante (solo si es empresa) (no es válido)'], 'RUT del representante inválido: cuenta como faltante');
ok(ContractService::faltantes($ct(['domicilio_cliente' => '   '] + $completo_persona)) === ['Domicilio del cliente'], 'un dato con solo espacios cuenta como vacío');
ok(ContractService::faltantes($ct([], 'soporte')) === [], 'contrato de soporte: no aplica');

$c = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles', 'monto_total' => '$1.000'], 'created_by' => 0]);
ok(is_object($c) && $c->type === 'servicios' && $c->template_id === 'servicios_v1' && $c->status === 'at_pending', 'contrato de servicios creado en at_pending');
$ph = json_decode($c->placeholders, true);
ok(strpos($ph['contract_title'], 'DESARROLLO') !== false, 'título de servicios guardado');
ok(ContractService::necesita_revision($c), 'necesita revisión antes de firmar');
$token_antes = $c->at_review_token;
$firma = ContractService::sign_as_at($c->id, $datos_firma);
ok(is_wp_error($firma) && $firma->get_error_code() === 'sin_revision', 'no se firma sin revisión');
$r = ContractService::guardar_revision($c->id, ['plazo' => 'Ocho semanas', 'rut_cliente' => '11.111.111-1', 'monto_total' => '', 'inventado' => 'x']);
$ph2 = json_decode($r->placeholders, true);
ok(!is_wp_error($r) && $ph2['plazo'] === 'Ocho semanas' && $ph2['rut_cliente'] === '11.111.111-1' && !isset($ph2['monto_total']) && !isset($ph2['inventado']) && !empty($ph2['revision_at']), 'revisión guardada: cambia, borra lo vaciado e ignora claves desconocidas');
ok(!ContractService::necesita_revision($r), 'ya no necesita revisión');
// Task 5b: con la revisión guardada pero con datos esenciales en blanco, todavía no se firma.
$firma_incompleta = ContractService::sign_as_at($c->id, $datos_firma);
ok(is_wp_error($firma_incompleta) && $firma_incompleta->get_error_code() === 'faltan_datos', 'con revisión pero datos en blanco no se firma (faltan_datos)');
ok(is_wp_error($firma_incompleta) && strpos($firma_incompleta->get_error_message(), 'Antes de firmar completa: Tipo de cliente') === 0 && strpos($firma_incompleta->get_error_message(), 'Precio total, IVA incluido') !== false && substr($firma_incompleta->get_error_message(), -1) === '.', 'el error dice qué falta');
ok(ContractService::get_by_id($c->id)->status === 'at_pending', 'sin los datos sigue sin firmar');
ok(is_wp_error(ContractService::guardar_revision($c->id, ['tipo_cliente' => 'sociedad'])) && !isset(json_decode(ContractService::get_by_id($c->id)->placeholders, true)['tipo_cliente']), 'la revisión solo acepta persona, empresa o vacío como tipo');
// Task 5b: el texto de la revisión se guarda tal cual (antes «50%de» salía «50 anticipo» y «<24 horas» con entidades).
$plazo = 'Respuesta en <24 horas, "comillas" y \'simples\'; 50%de anticipo';
// Persona natural: si el nombre y el RUT del cliente vienen vacíos, son los de quien aceptó (el representante).
$r3 = ContractService::guardar_revision($c->id, ['tipo_cliente' => 'persona', 'razon_social_cliente' => '', 'rut_cliente' => '', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'monto_total' => '$1.000', 'forma_pago' => "50 % al firmar\r\ny 50 % a la entrega.\0", 'plazo' => $plazo]);
$ph3 = is_wp_error($r3) ? [] : json_decode($r3->placeholders, true);
ok(($ph3['plazo'] ?? null) === $plazo, 'el plazo con <, comillas y 50%de se guarda idéntico');
ok(($ph3['forma_pago'] ?? null) === "50 % al firmar\ny 50 % a la entrega.", 'fines de línea normalizados y sin bytes nulos');
ok(($ph3['razon_social_cliente'] ?? null) === 'Ana Prueba' && ($ph3['rut_cliente'] ?? null) === '11.111.111-1', 'persona natural: nombre y RUT de quien aceptó');
ok(!is_wp_error($r3) && ContractService::faltantes($r3) === [], 'con los datos completos ya no falta nada');
$txt3 = texto_pdf(pdf_de($c));
ok(strpos($txt3, 'Ana Prueba') !== false && strpos($txt3, 'con domicilio en Calle Falsa 123, Santiago.') !== false && strpos($txt3, 'representada por') === false, 'el PDF regenerado trae el párrafo de persona natural');
ok(strpos($txt3, 'Respuesta en <24 horas, "comillas" y \'simples\'; 50%de anticipo') !== false, 'el PDF muestra el plazo tal cual');
$firma2 = ContractService::sign_as_at($c->id, $datos_firma);
ok(!is_wp_error($firma2) && $firma2->status === 'at_signed', 'con revisión y datos completos se firma');
// T5 ronda 1: sign_as_at() rota at_review_token (E4). at-sign-contract.php debe
// redirigir (PRG) al token nuevo tras firmar, porque el viejo queda muerto:
// si no redirige, el siguiente POST "Enviar al cliente" a la URL vieja (que
// no trae action) llega con el token muerto y la página responde 404.
ok($firma2->at_review_token !== $token_antes, 'firmar rota el token de revisión');
ok(ContractService::get_by_at_token($token_antes) === null, 'el token de revisión viejo ya no resuelve tras firmar');
ok(is_object(ContractService::get_by_at_token($firma2->at_review_token)) && ContractService::get_by_at_token($firma2->at_review_token)->id === $firma2->id, 'el token de revisión nuevo sí resuelve');
ok(is_wp_error(ContractService::guardar_revision($c->id, ['plazo' => 'x'])), 'firmado ya no se edita');
$sop = ContractService::create_contract(['client_id' => 0, 'type' => 'soporte', 'template_id' => 'soporte_v2', 'placeholders' => [], 'created_by' => 0]);
ok(is_object($sop) && !ContractService::necesita_revision($sop), 'el de soporte no pide revisión');

// ---------- Task 5b: datos que da el cliente después de aceptar (los usa la Task 7) ----------
$c2 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'nombre_proyecto' => '[PRUEBA] Muebles', 'monto_total' => '$1.000', 'forma_pago' => 'Contado.'], 'created_by' => 0]);
$u = ContractService::actualizar_datos_cliente($c2->id, ['tipo_cliente' => 'empresa', 'razon_social_cliente' => '[PRUEBA] Muebles SpA', 'rut_cliente' => '10.000.013-K', 'domicilio_cliente' => " Calle Falsa 123,\r\nSantiago ", 'monto_total' => '$1', 'representante_cliente_nombre' => 'Otra', 'revision_at' => '2026-01-01 00:00:00', 'inventado' => 'x']);
$phu = is_wp_error($u) ? [] : json_decode($u->placeholders, true);
ok(($phu['tipo_cliente'] ?? '') === 'empresa' && ($phu['razon_social_cliente'] ?? '') === '[PRUEBA] Muebles SpA' && ($phu['rut_cliente'] ?? '') === '10.000.013-K' && ($phu['domicilio_cliente'] ?? '') === "Calle Falsa 123,\nSantiago", 'guarda tipo, razón social, RUT y domicilio (limpios)');
ok(($phu['monto_total'] ?? '') === '$1.000' && ($phu['representante_cliente_nombre'] ?? '') === 'Ana Prueba' && !isset($phu['inventado']), 'ignora las claves ajenas');
ok(!isset($phu['revision_at']) && !is_wp_error($u) && ContractService::necesita_revision($u), 'no marca la revisión: Luis igual la revisa');
$txtu = texto_pdf(pdf_de($c2));
ok(strpos($txtu, 'representada por') !== false && strpos($txtu, 'Muebles SpA') !== false && strpos($txtu, 'con domicilio en Calle Falsa 123, Santiago.') !== false, 'el PDF regenerado trae el párrafo de empresa');
$u2 = ContractService::actualizar_datos_cliente($c2->id, ['tipo_cliente' => 'persona', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$phu2 = is_wp_error($u2) ? [] : json_decode($u2->placeholders, true);
ok(($phu2['tipo_cliente'] ?? '') === 'persona' && ($phu2['razon_social_cliente'] ?? '') === 'Ana Prueba' && ($phu2['rut_cliente'] ?? '') === '11.111.111-1', 'persona natural: el contrato va a nombre y RUT de quien aceptó, no de la marca');
$txtu2 = texto_pdf(pdf_de($c2));
ok(strpos($txtu2, 'representada por') === false && strpos($txtu2, 'para su proyecto «[PRUEBA] Muebles».') !== false, 'el PDF regenerado trae el párrafo de persona con su proyecto');
$antes = ContractService::get_by_id($c2->id)->placeholders;
$u3 = ContractService::actualizar_datos_cliente($c2->id, ['tipo_cliente' => 'sociedad', 'domicilio_cliente' => 'Otra 1']);
ok(is_wp_error($u3) && ContractService::get_by_id($c2->id)->placeholders === $antes, 'un tipo desconocido se rechaza y no cambia nada');
ContractService::guardar_revision($c2->id, ['plazo' => 'Ocho semanas']);
$antes = ContractService::get_by_id($c2->id)->placeholders;
$u4 = ContractService::actualizar_datos_cliente($c2->id, ['domicilio_cliente' => 'Otra 1']);
ok(is_wp_error($u4) && $u4->get_error_code() === 'ya_revisado' && ContractService::get_by_id($c2->id)->placeholders === $antes, 'con la revisión de Luis guardada: ya_revisado y no cambia nada');
$u5 = ContractService::actualizar_datos_cliente($c->id, ['domicilio_cliente' => 'Otra 1']);
ok(is_wp_error($u5) && $u5->get_error_code() !== 'ya_revisado', 'un contrato ya firmado no se toca');
ok(is_wp_error(ContractService::actualizar_datos_cliente($sop->id, ['tipo_cliente' => 'persona'])), 'un contrato de soporte no recibe estos datos');

// Task 5b ronda 1 (hallazgo 1): una segunda llamada que no repite el nombre no debe borrar el
// que el cliente ya había escrito explícitamente en una llamada anterior (antes se borraba
// porque el bloque de "persona natural" no distinguía "esta llamada no lo trae" de "nunca se
// personalizó"; lo confundía con el nombre por defecto de la propuesta).
$c4 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles', 'representante_cliente_nombre' => 'Representante Distinto', 'representante_cliente_rut' => '22.222.222-2', 'monto_total' => '$1.000', 'forma_pago' => 'Contado.'], 'created_by' => 0]);
$v1 = ContractService::actualizar_datos_cliente($c4->id, ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Nombre Propio Del Cliente', 'rut_cliente' => '33.333.333-3', 'domicilio_cliente' => 'Calle Uno 1']);
$phv1 = is_wp_error($v1) ? [] : json_decode($v1->placeholders, true);
ok(!is_wp_error($v1) && ($phv1['razon_social_cliente'] ?? '') === 'Nombre Propio Del Cliente' && ($phv1['rut_cliente'] ?? '') === '33.333.333-3', 'persona: guarda el nombre y RUT que escribió el cliente, distintos del representante');
$v2 = ContractService::actualizar_datos_cliente($c4->id, ['domicilio_cliente' => 'Calle Dos 2']);
$phv2 = is_wp_error($v2) ? [] : json_decode($v2->placeholders, true);
ok(!is_wp_error($v2) && ($phv2['razon_social_cliente'] ?? '') === 'Nombre Propio Del Cliente' && ($phv2['rut_cliente'] ?? '') === '33.333.333-3' && ($phv2['domicilio_cliente'] ?? '') === 'Calle Dos 2', 'una segunda llamada que solo cambia el domicilio no borra el nombre ya personalizado');

// Task 5b ronda 1 (hallazgo 2): un "<" sin cerrar en un dato (plazo) no debe borrar cláusulas
// completas del PDF (el regex que quita etiquetas cruzaba líneas y se comía todo hasta el
// próximo ">", que podía venir de otro marcador varias líneas más abajo).
$c5 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles', 'monto_total' => '$1.000.000'], 'created_by' => 0]);
ContractService::guardar_revision($c5->id, ['tipo_cliente' => 'empresa', 'rut_cliente' => '10.000.013-K', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'plazo' => 'Entrega <a convenir en la reunion de inicio', 'forma_pago' => '50 % al firmar -> 50 % a la entrega']);
$txt5 = texto_pdf(pdf_de($c5));
ok(strpos($txt5, '5.1. El precio total') !== false, 'un "<" sin cerrar en el plazo no borra la cláusula del precio (5.1)');
ok(strpos($txt5, 'CLÁUSULA QUINTA') !== false, 'tampoco borra el título de la cláusula quinta');
ok(strpos($txt5, 'Entrega <a convenir en la reunion de inicio') !== false && strpos($txt5, '50 % al firmar -> 50 % a la entrega') !== false, 'el plazo y la forma de pago se ven tal cual, con el "<" y el "->"');

// Task 5b ronda 1 (hallazgo 3): el formulario de revisión precarga razon_social_cliente con el
// nombre de la marca de la propuesta (igual que nombre_proyecto). Si Luis elige 'persona' y
// guarda sin tocar la razón social, guardar_revision() debe reemplazarla por el nombre de quien
// aceptó (representante_cliente_nombre), no dejar el contrato a nombre de la marca. Si Luis
// escribe otro nombre, se respeta. faltantes() además detecta como red de seguridad el caso en
// que, por otra vía, la razón social siga igual a la marca.
$marca_precio = ['monto_total' => '$1.000', 'forma_pago' => 'Contado.'];
$c6 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Marca Muebles', 'nombre_proyecto' => '[PRUEBA] Marca Muebles', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $marca_precio, 'created_by' => 0]);
$r6 = ContractService::guardar_revision($c6->id, ['tipo_cliente' => 'persona', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph6 = is_wp_error($r6) ? [] : json_decode($r6->placeholders, true);
ok(!is_wp_error($r6) && ($ph6['razon_social_cliente'] ?? '') === 'Ana Prueba', 'persona sin tocar la razón social: toma el nombre de quien aceptó, no el de la marca');
ok(!is_wp_error($r6) && ContractService::faltantes($r6) === [], 'con el nombre corregido ya no falta nada');
$c7 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Marca Muebles', 'nombre_proyecto' => '[PRUEBA] Marca Muebles', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $marca_precio, 'created_by' => 0]);
$r7 = ContractService::guardar_revision($c7->id, ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Otro Nombre Del Cliente', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph7 = is_wp_error($r7) ? [] : json_decode($r7->placeholders, true);
ok(!is_wp_error($r7) && ($ph7['razon_social_cliente'] ?? '') === 'Otro Nombre Del Cliente', 'si Luis escribe un nombre distinto del de la marca, se respeta');
$marca_persona_base = ['tipo_cliente' => 'persona', 'razon_social_cliente' => '[PRUEBA] Marca Muebles', 'nombre_proyecto' => '[PRUEBA] Marca Muebles', 'representante_cliente_nombre' => 'Ana Prueba', 'rut_cliente' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago'] + $marca_precio;
ok(ContractService::faltantes($ct($marca_persona_base)) === ['Nombre completo del cliente (hoy dice el nombre de la marca)'], 'faltantes(): red de seguridad si la razón social sigue igual a la marca y distinta de quien aceptó');
ok(ContractService::faltantes($ct(array_diff_key($marca_persona_base, ['representante_cliente_nombre' => 1]))) === [], 'faltantes(): sin representante en los datos, no hay con qué comparar y no se marca como marca');
ok(ContractService::faltantes($ct(['razon_social_cliente' => 'Ana Prueba', 'nombre_proyecto' => '  ana   PRUEBA '] + array_diff_key($marca_persona_base, ['razon_social_cliente' => 1, 'nombre_proyecto' => 1]))) === [], 'faltantes(): si su propio nombre coincide con el del proyecto, no se marca como marca');

// ---------- Task 5b: la página de revisión (contracts/at-sign-contract.php) ----------
function pagina_revision(string $token, ?array $post = null): string {
	$_GET = ['token' => $token];
	$_POST = $post ?? [];
	$_SERVER['REQUEST_METHOD'] = $post === null ? 'GET' : 'POST';
	ob_start();
	include ABSPATH . 'contracts/at-sign-contract.php';
	return (string) ob_get_clean();
}
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) ($admins[0] ?? 0));
$c3 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles'], 'created_by' => 0]);
$h = pagina_revision($c3->at_review_token);
ok(strpos($h, '<select name="rev[tipo_cliente]"') !== false && strpos($h, '— Elegir —') !== false && strpos($h, 'Persona natural (a su nombre)') !== false && strpos($h, 'Empresa o persona jurídica') !== false, 'la página pide el tipo de cliente con un selector');
ok(strpos($h, 'Representante (solo si es empresa)') !== false && strpos($h, 'Fases siguientes (una por línea, empezando con «- »)') !== false, 'la página muestra los campos nuevos de la revisión');
ok(strpos($h, 'Para firmar, primero guarda la revisión.') !== false && strpos($h, 'id="signForm"') === false, 'sin revisión no hay bloque de firma');
// WordPress agrega barras a $_POST (magic quotes): se simulan para comprobar que la página las quita y no limpia de más.
$h = pagina_revision($c3->at_review_token, ['_at_nonce' => wp_create_nonce('at_sign_' . $c3->id), 'action' => 'revisar', 'rev' => wp_slash(['tipo_cliente' => 'empresa', 'plazo' => $plazo])]);
$ph_pag = json_decode(ContractService::get_by_id($c3->id)->placeholders, true);
ok(($ph_pag['plazo'] ?? null) === $plazo && ($ph_pag['tipo_cliente'] ?? null) === 'empresa', 'la página guarda la revisión tal cual (sin barras, entidades ni cortes)');
ok(strpos($h, 'Respuesta en &lt;24 horas, &quot;comillas&quot;') !== false, 'la página muestra el texto escapado');
ok(strpos($h, "value=\"empresa\" selected='selected'") !== false, 'el selector muestra el tipo guardado');
ok(strpos($h, 'Antes de firmar completa: Razón social') === false && strpos($h, 'Antes de firmar completa: RUT de la empresa, Representante (solo si es empresa)') !== false && strpos($h, 'id="signForm"') === false, 'revisión guardada con faltantes: aviso con lo que falta y sin bloque de firma');
ContractService::guardar_revision($c3->id, ['rut_cliente' => '10.000.013-K', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'monto_total' => '$1.000', 'forma_pago' => 'Contado.']);
$h = pagina_revision($c3->at_review_token);
ok(strpos($h, 'id="signForm"') !== false && strpos($h, 'Antes de firmar completa') === false, 'con los datos completos aparece el bloque de firma');
wp_set_current_user(0);

$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id IN (%d, %d, %d, %d, %d, %d, %d, %d)", $c->id, $sop->id, $c2->id, $c3->id, $c4->id, $c5->id, $c6->id, $c7->id));
foreach ([$c, $sop, $c2, $c3, $c4, $c5, $c6, $c7] as $x) { @unlink(pdf_de($x)); }
fin();
