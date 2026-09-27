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
$datos = ['razon_social_cliente' => '[PRUEBA] Negocio SpA', 'rut_cliente' => '10.000.013-K', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'email_cliente' => 'ana@example.com', 'telefono_cliente' => '+56 9 1111 1111', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'nombre_proyecto' => 'Tienda en línea'];
$emp = ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa'] + $datos);
ok($emp === '**[PRUEBA] Negocio SpA** (en adelante "**EL CLIENTE**"), RUT **10.000.013-K**, representada por **Ana Prueba**, RUT **11.111.111-1**, correo ana@example.com, teléfono +56 9 1111 1111, con domicilio en Calle Falsa 123, Santiago.', 'empresa: comparece representada, con el texto aprobado');
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
$completo_empresa = ['tipo_cliente' => 'empresa', 'razon_social_cliente' => '[PRUEBA] Negocio SpA', 'rut_cliente' => '10.000.013-K', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $completo_persona;
ok(in_array('Tipo de cliente', ContractService::faltantes($ct(array_diff_key($completo_persona, ['tipo_cliente' => 1]))), true), 'sin tipo: falta «Tipo de cliente»');
ok(in_array('Tipo de cliente', ContractService::faltantes($ct(['tipo_cliente' => 'sociedad'] + $completo_persona)), true), 'un tipo desconocido cuenta como sin tipo');
ok(ContractService::faltantes($ct($completo_persona)) === [], 'persona completa: no falta nada');
// Task 15: las etiquetas del número de la persona y del representante pasan a «Documento del cliente»
// y «Documento del representante» (puede ser RUT, DNI o pasaporte); el de la empresa sigue siendo RUT.
ok(ContractService::faltantes($ct(['tipo_cliente' => 'persona'])) === ['Nombre completo del cliente', 'Documento del cliente', 'Domicilio del cliente', 'Precio total, IVA incluido', 'Forma de pago'], 'persona vacía: faltan sus datos, con las etiquetas de persona');
ok(ContractService::faltantes($ct($completo_empresa)) === [], 'empresa completa: no falta nada');
ok(ContractService::faltantes($ct(array_diff_key($completo_empresa, ['representante_cliente_nombre' => 1, 'representante_cliente_rut' => 1]))) === ['Representante (solo si es empresa)', 'Documento del representante'], 'empresa sin representante: lo lista');
ok(ContractService::faltantes($ct(['tipo_cliente' => 'empresa'])) === ['Razón social', 'RUT de la empresa', 'Representante (solo si es empresa)', 'Documento del representante', 'Domicilio del cliente', 'Precio total, IVA incluido', 'Forma de pago'], 'empresa vacía: faltan sus datos, con las etiquetas de empresa');
ok(ContractService::faltantes($ct(['rut_cliente' => '11.111.111-2'] + $completo_persona)) === ['Documento del cliente (no es válido)'], 'RUT inválido: cuenta como faltante');
ok(ContractService::faltantes($ct(['representante_cliente_rut' => '11.111.111-2'] + $completo_empresa)) === ['Documento del representante (no es válido)'], 'RUT del representante inválido: cuenta como faltante');
ok(ContractService::faltantes($ct(['domicilio_cliente' => '   '] + $completo_persona)) === ['Domicilio del cliente'], 'un dato con solo espacios cuenta como vacío');
ok(ContractService::faltantes($ct([], 'soporte')) === [], 'contrato de soporte: no aplica');

// ---------- Task 15: tipo de documento (RUT, DNI o pasaporte) de la persona y del representante ----------
$per_dni = ['tipo_documento_cliente' => 'dni', 'rut_cliente' => '12345678'] + $per_datos;
ok(ContractService::comparecencia_cliente($per_dni) === '**Ana Prueba** (en adelante "**EL CLIENTE**"), DNI N° **12345678**, correo ana@example.com, teléfono +56 9 1111 1111, con domicilio en Calle Falsa 123, Santiago, para su proyecto «Tienda en línea».', 'T15: persona con DNI: comparece con «DNI N° **…**»');
ok(strpos(ContractService::comparecencia_cliente(['tipo_documento_cliente' => 'pasaporte', 'rut_cliente' => 'AB123456'] + $per_datos), 'pasaporte N° **AB123456**, correo') !== false, 'T15: persona con pasaporte: comparece con «pasaporte N° **…**»');
$emp_pas = ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa', 'tipo_documento_representante' => 'pasaporte', 'representante_cliente_rut' => 'AB123456'] + $datos);
ok($emp_pas === '**[PRUEBA] Negocio SpA** (en adelante "**EL CLIENTE**"), RUT **10.000.013-K**, representada por **Ana Prueba**, pasaporte N° **AB123456**, correo ana@example.com, teléfono +56 9 1111 1111, con domicilio en Calle Falsa 123, Santiago.', 'T15: empresa con representante con pasaporte: «representada por **X**, pasaporte N° **…**»');
ok(strpos(ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa', 'tipo_documento_cliente' => 'dni'] + $datos), '"**EL CLIENTE**"), RUT **10.000.013-K**, representada') !== false, 'T15: la empresa siempre muestra RUT para su propio número');
ok(ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa', 'tipo_documento_representante' => 'dni']) === '**_______** (en adelante "**EL CLIENTE**"), RUT **_______**, representada por **_______**, DNI N° **_______**, correo _______, teléfono _______, con domicilio en _______.', 'T15: sin número, el rótulo del tipo con _______');
ok($per === ContractService::comparecencia_cliente(['tipo_documento_cliente' => 'rut'] + $per_datos) && $emp === ContractService::comparecencia_cliente(['tipo_cliente' => 'empresa', 'tipo_documento_representante' => 'rut'] + $datos), 'T15: sin tipo de documento (contratos viejos) sale igual que con RUT');
$completo_persona_dni = ['tipo_documento_cliente' => 'dni', 'rut_cliente' => '12345678'] + $completo_persona;
ok(ContractService::faltantes($ct($completo_persona_dni)) === [], 'T15: persona con DNI y los demás datos: no falta nada');
ok(ContractService::faltantes($ct(['rut_cliente' => '123'] + $completo_persona_dni)) === ['Documento del cliente (no es válido)'], 'T15: DNI de 3 caracteres no es válido');
ok(ContractService::faltantes($ct(['tipo_documento_cliente' => 'pasaporte', 'rut_cliente' => 'AB123456'] + $completo_persona)) === [], 'T15: persona con pasaporte válido: no falta nada');
ok(ContractService::faltantes($ct(['rut_cliente' => '12345678'] + $completo_persona)) === ['Documento del cliente (no es válido)'], 'T15: sin tipo de documento (contrato viejo) se valida como RUT');
$completo_empresa_pas = ['tipo_documento_representante' => 'pasaporte', 'representante_cliente_rut' => 'AB123456'] + $completo_empresa;
ok(ContractService::faltantes($ct($completo_empresa_pas)) === [], 'T15: empresa con representante con pasaporte: no falta nada');
ok(ContractService::faltantes($ct(['representante_cliente_rut' => 'AB'] + $completo_empresa_pas)) === ['Documento del representante (no es válido)'], 'T15: pasaporte del representante inválido: cuenta como faltante');
ok(ContractService::faltantes($ct(['tipo_documento_cliente' => 'dni', 'rut_cliente' => '12345678'] + $completo_empresa)) === ['RUT de la empresa (no es válido)'], 'T15: el RUT de la empresa sigue validándose como RUT aunque el tipo del cliente diga DNI');
ok(ContractService::faltantes($ct(['tipo_documento_cliente' => 'cedula', 'rut_cliente' => '12345678'] + $completo_persona)) === ['Documento del cliente (no es válido)'], 'T15: un tipo de documento desconocido no valida');

$c = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Negocio', 'monto_total' => '$1.000'], 'created_by' => 0]);
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
$c2 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Negocio', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1', 'nombre_proyecto' => '[PRUEBA] Negocio', 'monto_total' => '$1.000', 'forma_pago' => 'Contado.'], 'created_by' => 0]);
$u = ContractService::actualizar_datos_cliente($c2->id, ['tipo_cliente' => 'empresa', 'razon_social_cliente' => '[PRUEBA] Negocio SpA', 'rut_cliente' => '10.000.013-K', 'domicilio_cliente' => " Calle Falsa 123,\r\nSantiago ", 'monto_total' => '$1', 'representante_cliente_nombre' => 'Otra', 'revision_at' => '2026-01-01 00:00:00', 'inventado' => 'x']);
$phu = is_wp_error($u) ? [] : json_decode($u->placeholders, true);
ok(($phu['tipo_cliente'] ?? '') === 'empresa' && ($phu['razon_social_cliente'] ?? '') === '[PRUEBA] Negocio SpA' && ($phu['rut_cliente'] ?? '') === '10.000.013-K' && ($phu['domicilio_cliente'] ?? '') === "Calle Falsa 123,\nSantiago", 'guarda tipo, razón social, RUT y domicilio (limpios)');
ok(($phu['monto_total'] ?? '') === '$1.000' && ($phu['representante_cliente_nombre'] ?? '') === 'Ana Prueba' && !isset($phu['inventado']), 'ignora las claves ajenas');
ok(!isset($phu['revision_at']) && !is_wp_error($u) && ContractService::necesita_revision($u), 'no marca la revisión: Luis igual la revisa');
$txtu = texto_pdf(pdf_de($c2));
ok(strpos($txtu, 'representada por') !== false && strpos($txtu, 'Negocio SpA') !== false && strpos($txtu, 'con domicilio en Calle Falsa 123, Santiago.') !== false, 'el PDF regenerado trae el párrafo de empresa');
// 27-sep: la cláusula de uso de inteligencia artificial sale en el PDF, con la garantía corrida a la decimotercera.
ok(strpos($txtu, 'Uso de inteligencia artificial') !== false && strpos($txtu, '12.1. EL PROVEEDOR ejecuta el Proyecto con su equipo de profesionales') !== false && strpos($txtu, '13.1. EL PROVEEDOR corrige sin costo') !== false, 'el PDF trae la cláusula de inteligencia artificial y la garantía como 13.1');
$u2 = ContractService::actualizar_datos_cliente($c2->id, ['tipo_cliente' => 'persona', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$phu2 = is_wp_error($u2) ? [] : json_decode($u2->placeholders, true);
ok(($phu2['tipo_cliente'] ?? '') === 'persona' && ($phu2['razon_social_cliente'] ?? '') === 'Ana Prueba' && ($phu2['rut_cliente'] ?? '') === '11.111.111-1', 'persona natural: el contrato va a nombre y RUT de quien aceptó, no de la marca');
$txtu2 = texto_pdf(pdf_de($c2));
ok(strpos($txtu2, 'representada por') === false && strpos($txtu2, 'para su proyecto «[PRUEBA] Negocio».') !== false, 'el PDF regenerado trae el párrafo de persona con su proyecto');
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
$c4 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Negocio', 'representante_cliente_nombre' => 'Representante Distinto', 'representante_cliente_rut' => '22.222.222-2', 'monto_total' => '$1.000', 'forma_pago' => 'Contado.'], 'created_by' => 0]);
$v1 = ContractService::actualizar_datos_cliente($c4->id, ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Nombre Propio Del Cliente', 'rut_cliente' => '33.333.333-3', 'domicilio_cliente' => 'Calle Uno 1']);
$phv1 = is_wp_error($v1) ? [] : json_decode($v1->placeholders, true);
ok(!is_wp_error($v1) && ($phv1['razon_social_cliente'] ?? '') === 'Nombre Propio Del Cliente' && ($phv1['rut_cliente'] ?? '') === '33.333.333-3', 'persona: guarda el nombre y RUT que escribió el cliente, distintos del representante');
$v2 = ContractService::actualizar_datos_cliente($c4->id, ['domicilio_cliente' => 'Calle Dos 2']);
$phv2 = is_wp_error($v2) ? [] : json_decode($v2->placeholders, true);
ok(!is_wp_error($v2) && ($phv2['razon_social_cliente'] ?? '') === 'Nombre Propio Del Cliente' && ($phv2['rut_cliente'] ?? '') === '33.333.333-3' && ($phv2['domicilio_cliente'] ?? '') === 'Calle Dos 2', 'una segunda llamada que solo cambia el domicilio no borra el nombre ya personalizado');

// Task 5b ronda 1 (hallazgo 2): un "<" sin cerrar en un dato (plazo) no debe borrar cláusulas
// completas del PDF (el regex que quita etiquetas cruzaba líneas y se comía todo hasta el
// próximo ">", que podía venir de otro marcador varias líneas más abajo).
$c5 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Negocio', 'monto_total' => '$1.000.000'], 'created_by' => 0]);
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
$c6 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Marca Negocio', 'nombre_proyecto' => '[PRUEBA] Marca Negocio', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $marca_precio, 'created_by' => 0]);
$r6 = ContractService::guardar_revision($c6->id, ['tipo_cliente' => 'persona', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph6 = is_wp_error($r6) ? [] : json_decode($r6->placeholders, true);
ok(!is_wp_error($r6) && ($ph6['razon_social_cliente'] ?? '') === 'Ana Prueba', 'persona sin tocar la razón social: toma el nombre de quien aceptó, no el de la marca');
ok(!is_wp_error($r6) && ContractService::faltantes($r6) === [], 'con el nombre corregido ya no falta nada');
$c7 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Marca Negocio', 'nombre_proyecto' => '[PRUEBA] Marca Negocio', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $marca_precio, 'created_by' => 0]);
$r7 = ContractService::guardar_revision($c7->id, ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Otro Nombre Del Cliente', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph7 = is_wp_error($r7) ? [] : json_decode($r7->placeholders, true);
ok(!is_wp_error($r7) && ($ph7['razon_social_cliente'] ?? '') === 'Otro Nombre Del Cliente', 'si Luis escribe un nombre distinto del de la marca, se respeta');
$marca_persona_base = ['tipo_cliente' => 'persona', 'razon_social_cliente' => '[PRUEBA] Marca Negocio', 'nombre_proyecto' => '[PRUEBA] Marca Negocio', 'representante_cliente_nombre' => 'Ana Prueba', 'rut_cliente' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago'] + $marca_precio;
ok(ContractService::faltantes($ct($marca_persona_base)) === ['Nombre completo del cliente (hoy dice el nombre de la marca)'], 'faltantes(): red de seguridad si la razón social sigue igual a la marca y distinta de quien aceptó');
ok(ContractService::faltantes($ct(array_diff_key($marca_persona_base, ['representante_cliente_nombre' => 1]))) === [], 'faltantes(): sin representante en los datos, no hay con qué comparar y no se marca como marca');
ok(ContractService::faltantes($ct(['razon_social_cliente' => 'Ana Prueba', 'nombre_proyecto' => '  ana   PRUEBA '] + array_diff_key($marca_persona_base, ['razon_social_cliente' => 1, 'nombre_proyecto' => 1]))) === [], 'faltantes(): si su propio nombre coincide con el del proyecto, no se marca como marca');

// Task 5b ronda 2: at-sign-contract.php manda SIEMPRE los 13 campos como texto plano, incluido
// representante_cliente_nombre con la etiqueta «Representante (solo si es empresa)». Si Luis
// elige 'persona' y, siguiendo esa etiqueta, borra el nombre del representante en la MISMA
// llamada (deja el RUT tal cual), guardar_revision() hacía unset(representante_cliente_nombre)
// ANTES de llamar a persona_marca_igual_al_proyecto(), así que ni el reemplazo ni la red de
// seguridad de faltantes() se activaban (los dos exigían representante_cliente_nombre !== '' en
// el momento de la llamada): el contrato quedaba a nombre de la marca con el RUT personal.
$c8 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Marca Negocio', 'nombre_proyecto' => '[PRUEBA] Marca Negocio', 'representante_cliente_nombre' => 'Ana Prueba', 'representante_cliente_rut' => '11.111.111-1'] + $marca_precio, 'created_by' => 0]);
$r8 = ContractService::guardar_revision($c8->id, ['tipo_cliente' => 'persona', 'razon_social_cliente' => '[PRUEBA] Marca Negocio', 'representante_cliente_nombre' => '', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph8 = is_wp_error($r8) ? [] : json_decode($r8->placeholders, true);
ok(!is_wp_error($r8) && ($ph8['razon_social_cliente'] ?? '') === 'Ana Prueba' && ($ph8['rut_cliente'] ?? '') === '11.111.111-1', 'persona: si Luis borra el representante en la misma llamada, igual toma el nombre y RUT de quien aceptó, no el de la marca');
ok(!is_wp_error($r8) && ContractService::faltantes($r8) === [], 'con el nombre corregido en la misma llamada, faltantes() ya no lo marca');

// El marcador inmutable 'aceptante_nombre' (lo escribe at_cc_marcadores_servicios() al crear el
// contrato) protege incluso cuando representante_cliente_nombre YA está vacío en lo guardado
// (una revisión anterior a este arreglo, u otra vía): faltantes() lo detecta igual, y una
// revisión posterior que no toca la razón social la corrige usando aceptante_nombre.
$marca_sin_representante = ['tipo_cliente' => 'persona', 'razon_social_cliente' => '[PRUEBA] Marca Negocio', 'nombre_proyecto' => '[PRUEBA] Marca Negocio', 'aceptante_nombre' => 'Ana Prueba', 'rut_cliente' => '11.111.111-1', 'domicilio_cliente' => 'Calle Falsa 123, Santiago'] + $marca_precio;
ok(ContractService::faltantes($ct($marca_sin_representante)) === ['Nombre completo del cliente (hoy dice el nombre de la marca)'], 'faltantes(): aceptante_nombre detecta la marca aunque representante_cliente_nombre ya no esté guardado');
$c9 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => $marca_sin_representante, 'created_by' => 0]);
$r9 = ContractService::guardar_revision($c9->id, ['domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph9 = is_wp_error($r9) ? [] : json_decode($r9->placeholders, true);
ok(!is_wp_error($r9) && ($ph9['razon_social_cliente'] ?? '') === 'Ana Prueba', 'guardar_revision() corrige la marca usando aceptante_nombre aunque representante_cliente_nombre ya esté vacío');
// aceptante_nombre nunca es editable desde la revisión: no está en campos_revision(), así que
// guardar_revision() lo ignora aunque venga en $cambios (at-sign-contract.php no lo manda, pero
// se comprueba igual que la protección no depende de eso).
ok(!array_key_exists('aceptante_nombre', ContractService::campos_revision()), 'aceptante_nombre no está entre los campos editables de la revisión');
$r10 = ContractService::guardar_revision($c9->id, ['aceptante_nombre' => 'Otra Persona']);
$ph10 = is_wp_error($r10) ? [] : json_decode($r10->placeholders, true);
ok(!is_wp_error($r10) && ($ph10['aceptante_nombre'] ?? '') === 'Ana Prueba', 'guardar_revision() no cambia aceptante_nombre aunque venga en $cambios');

// ---------- Task 15: revisión de AT y datos del cliente con tipo de documento ----------
$cr = ContractService::campos_revision();
ok(array_slice(array_keys($cr), 0, 8) === ['tipo_cliente', 'razon_social_cliente', 'tipo_documento_cliente', 'rut_cliente', 'domicilio_cliente', 'representante_cliente_nombre', 'tipo_documento_representante', 'representante_cliente_rut'], 'T15: cada tipo de documento va justo antes de su número en la revisión');
ok($cr['tipo_documento_cliente'] === ['Tipo de documento del cliente', 'documento'] && $cr['tipo_documento_representante'] === ['Tipo de documento del representante', 'documento'], 'T15: campos de tipo de documento con su etiqueta y el tipo «documento»');
ok($cr['rut_cliente'][0] === 'Documento del cliente (RUT si es empresa)' && $cr['representante_cliente_rut'][0] === 'Documento del representante (solo si es empresa)', 'T15: etiquetas de los números: «Documento del cliente (RUT si es empresa)» y «Documento del representante (solo si es empresa)»');
ok(ContractService::tipos_documento() === ['rut' => 'RUT', 'dni' => 'DNI', 'pasaporte' => 'Pasaporte'], 'T15: tipos de documento del selector');
// Aceptó con DNI (marcadores como los arma at_cc_marcadores_servicios()) y el cliente deja sus datos como persona.
$ph_dni = ['razon_social_cliente' => '[PRUEBA] Marca Negocio', 'nombre_proyecto' => '[PRUEBA] Marca Negocio', 'representante_cliente_nombre' => 'Ana Prueba', 'aceptante_nombre' => 'Ana Prueba', 'tipo_documento_cliente' => 'rut', 'tipo_documento_representante' => 'dni', 'representante_cliente_rut' => '12345678'] + $marca_precio;
$c10 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => $ph_dni, 'created_by' => 0]);
$u10 = ContractService::actualizar_datos_cliente($c10->id, ['tipo_cliente' => 'persona', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph10 = is_wp_error($u10) ? [] : json_decode($u10->placeholders, true);
ok(($ph10['rut_cliente'] ?? '') === '12345678' && ($ph10['tipo_documento_cliente'] ?? '') === 'dni' && ($ph10['razon_social_cliente'] ?? '') === 'Ana Prueba', 'T15: persona natural: el contrato toma el tipo y el número de documento de quien aceptó');
ok(!is_wp_error($u10) && ContractService::faltantes($u10) === [], 'T15: persona con DNI y los demás datos: faltantes() vacío');
$txt10 = texto_pdf(pdf_de($c10));
ok(strpos($txt10, 'DNI N° 12345678') !== false && strpos($txt10, 'RUT 12345678') === false, 'T15: el PDF de la persona dice «DNI N° 12345678»');
$antes10 = ContractService::get_by_id($c10->id)->placeholders;
$u10x = ContractService::actualizar_datos_cliente($c10->id, ['tipo_documento_cliente' => 'cedula', 'domicilio_cliente' => 'Otra 1']);
ok(is_wp_error($u10x) && ContractService::get_by_id($c10->id)->placeholders === $antes10, 'T15: datos del cliente con un tipo de documento desconocido: se rechaza y no cambia nada');
$u10b = ContractService::actualizar_datos_cliente($c10->id, ['tipo_cliente' => 'empresa', 'razon_social_cliente' => '[PRUEBA] Negocio SpA', 'rut_cliente' => '10.000.013-K', 'tipo_documento_cliente' => 'rut']);
$ph10b = is_wp_error($u10b) ? [] : json_decode($u10b->placeholders, true);
ok(($ph10b['tipo_documento_cliente'] ?? '') === 'rut' && ($ph10b['rut_cliente'] ?? '') === '10.000.013-K' && ($ph10b['tipo_documento_representante'] ?? '') === 'dni', 'T15: empresa: el cliente es RUT y el representante conserva su DNI');
$txt10b = texto_pdf(pdf_de($c10));
ok(strpos($txt10b, 'RUT 10.000.013-K') !== false && strpos($txt10b, 'representada por') !== false && strpos($txt10b, 'DNI N° 12345678') !== false, 'T15: el PDF de la empresa: RUT de la empresa y DNI del representante');
// Revisión de AT cambiando el tipo: un número de DNI guardado como RUT no es válido hasta elegir DNI.
$c11 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['tipo_cliente' => 'persona', 'razon_social_cliente' => 'Ana Prueba', 'rut_cliente' => '12345678', 'domicilio_cliente' => 'Calle Falsa 123, Santiago'] + $marca_precio, 'created_by' => 0]);
$r11 = ContractService::guardar_revision($c11->id, ['plazo' => 'Ocho semanas']);
ok(!is_wp_error($r11) && ContractService::faltantes($r11) === ['Documento del cliente (no es válido)'], 'T15: contrato sin tipo de documento: el número se valida como RUT');
$r11b = ContractService::guardar_revision($c11->id, ['tipo_documento_cliente' => 'dni']);
$ph11b = is_wp_error($r11b) ? [] : json_decode($r11b->placeholders, true);
ok(($ph11b['tipo_documento_cliente'] ?? '') === 'dni' && !is_wp_error($r11b) && ContractService::faltantes($r11b) === [], 'T15: AT elige DNI en la revisión: se guarda y ya no falta nada');
ok(strpos(texto_pdf(pdf_de($c11)), 'DNI N° 12345678') !== false, 'T15: el PDF regenerado dice «DNI N° 12345678»');
$antes11 = ContractService::get_by_id($c11->id)->placeholders;
$r11x = ContractService::guardar_revision($c11->id, ['tipo_documento_cliente' => 'cedula']);
$r11y = ContractService::guardar_revision($c11->id, ['tipo_documento_representante' => 'RUT']);
ok(is_wp_error($r11x) && is_wp_error($r11y) && ContractService::get_by_id($c11->id)->placeholders === $antes11, 'T15: la revisión solo acepta rut, dni o pasaporte como tipo de documento');
// Persona con el documento del cliente en blanco: la revisión toma el tipo y el número de quien aceptó,
// aunque el selector del cliente llegue en RUT (su valor por defecto en la página).
$c12 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => 'Ana Prueba', 'representante_cliente_nombre' => 'Ana Prueba', 'tipo_documento_representante' => 'pasaporte', 'representante_cliente_rut' => 'AB123456'] + $marca_precio, 'created_by' => 0]);
$r12 = ContractService::guardar_revision($c12->id, ['tipo_cliente' => 'persona', 'tipo_documento_cliente' => 'rut', 'rut_cliente' => '', 'domicilio_cliente' => 'Calle Falsa 123, Santiago']);
$ph12 = is_wp_error($r12) ? [] : json_decode($r12->placeholders, true);
ok(($ph12['rut_cliente'] ?? '') === 'AB123456' && ($ph12['tipo_documento_cliente'] ?? '') === 'pasaporte' && !is_wp_error($r12) && ContractService::faltantes($r12) === [], 'T15: persona con el documento en blanco: toma el pasaporte de quien aceptó (tipo y número)');

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
$c3 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Negocio'], 'created_by' => 0]);
$h = pagina_revision($c3->at_review_token);
ok(strpos($h, '<select name="rev[tipo_cliente]"') !== false && strpos($h, '— Elegir —') !== false && strpos($h, 'Persona natural (a su nombre)') !== false && strpos($h, 'Empresa o persona jurídica') !== false, 'la página pide el tipo de cliente con un selector');
ok(strpos($h, 'Representante (solo si es empresa)') !== false && strpos($h, 'Fases siguientes (una por línea, empezando con «- »)') !== false, 'la página muestra los campos nuevos de la revisión');
ok(strpos($h, 'Para firmar, primero guarda la revisión.') !== false && strpos($h, 'id="signForm"') === false, 'sin revisión no hay bloque de firma');
// Task 15: los tipos de documento se eligen con un selector RUT/DNI/Pasaporte; sin tipo guardado, RUT.
ok(strpos($h, '<select name="rev[tipo_documento_cliente]"') !== false && strpos($h, '<select name="rev[tipo_documento_representante]"') !== false, 'T15: la página pide los tipos de documento con un selector');
ok(substr_count($h, "value=\"rut\" selected='selected'>RUT</option>") === 2 && substr_count($h, '<option value="dni">DNI</option>') === 2 && substr_count($h, '<option value="pasaporte">Pasaporte</option>') === 2, 'T15: el selector ofrece RUT, DNI y Pasaporte, con RUT elegido en un contrato sin tipo');
ok(strpos($h, 'Tipo de documento del cliente') !== false && strpos($h, 'Documento del cliente (RUT si es empresa)') !== false && strpos($h, 'Documento del representante (solo si es empresa)') !== false, 'T15: la página muestra las etiquetas nuevas');
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
// Task 15: la página guarda el tipo de documento del representante y lo muestra elegido.
$c13 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Negocio SpA'] + $marca_precio, 'created_by' => 0]);
$rev13 = ['tipo_cliente' => 'empresa', 'razon_social_cliente' => '[PRUEBA] Negocio SpA', 'tipo_documento_cliente' => 'rut', 'rut_cliente' => '10.000.013-K', 'domicilio_cliente' => 'Calle Falsa 123, Santiago', 'representante_cliente_nombre' => 'Ana Prueba', 'tipo_documento_representante' => 'pasaporte', 'representante_cliente_rut' => 'AB123456', 'monto_total' => '$1.000', 'forma_pago' => 'Contado.'];
$h13 = pagina_revision($c13->at_review_token, ['_at_nonce' => wp_create_nonce('at_sign_' . $c13->id), 'action' => 'revisar', 'rev' => wp_slash($rev13)]);
$ph13 = json_decode(ContractService::get_by_id($c13->id)->placeholders, true);
ok(($ph13['tipo_documento_representante'] ?? '') === 'pasaporte' && ($ph13['representante_cliente_rut'] ?? '') === 'AB123456', 'T15: la página guarda el pasaporte del representante');
ok(strpos($h13, "value=\"pasaporte\" selected='selected'>Pasaporte</option>") !== false && strpos($h13, 'id="signForm"') !== false && strpos($h13, 'Antes de firmar completa') === false, 'T15: el selector muestra el pasaporte elegido y el contrato se puede firmar');
$h13x = pagina_revision($c13->at_review_token, ['_at_nonce' => wp_create_nonce('at_sign_' . $c13->id), 'action' => 'revisar', 'rev' => wp_slash(['tipo_documento_representante' => 'cedula'] + $rev13)]);
ok(strpos($h13x, 'El tipo de documento debe ser RUT, DNI o pasaporte.') !== false && (json_decode(ContractService::get_by_id($c13->id)->placeholders, true)['tipo_documento_representante'] ?? '') === 'pasaporte', 'T15: un tipo de documento desconocido desde la página: aviso y no cambia nada');
wp_set_current_user(0);

// Revisión final (26-sep), hallazgo 5: el contrato de servicios vencía 30 días después de la
// aceptación; con la revisión, los datos del cliente y la reunión de inicio en medio, el cliente
// podía recibir un enlace ya vencido. Al enviárselo, el plazo corre de nuevo (30 días desde el envío).
// $c está firmado por AT (más arriba). Se simula que ya pasaron 32 días desde la aceptación.
$wpdb->update(ContractService::table(), ['expires_at' => date('Y-m-d H:i:s', strtotime('-2 days'))], ['id' => $c->id]);
$correos = [];
$envio = ContractService::send_for_client_signature($c->id, 'cliente@example.com', 'Cliente Prueba');
$c_enviado = ContractService::get_by_id($c->id);
ok($envio === true && $c_enviado->status === 'sent', 'RF5: el contrato de servicios firmado por AT se envía al cliente');
ok(strtotime($c_enviado->expires_at) > strtotime('+29 days'), 'RF5: al enviarlo, el vencimiento pasa a 30 días desde el envío: ' . $c_enviado->expires_at);
$correo_firma = $correos ? end($correos) : null;
ok($correo_firma && strpos((string) $correo_firma['message'], 'Tu enlace personal expira el ' . $c_enviado->expires_at) !== false, 'RF5: el correo al cliente muestra el vencimiento nuevo, no una fecha pasada');
$firma_cliente = ContractService::sign_as_client($c_enviado->sign_token, ['signer_name' => 'Cliente Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'cliente@example.com', 'method' => 'canvas', 'signature_dataurl' => $firma_png]);
ok(!is_wp_error($firma_cliente) && $firma_cliente->status === 'signed', 'RF5: el cliente firma sin «Link expirado»' . (is_wp_error($firma_cliente) ? ' (' . $firma_cliente->get_error_code() . ')' : ''));
if (!is_wp_error($firma_cliente)) {
	@unlink(ContractService::storage_dir() . '/' . $firma_cliente->contract_number . '-FIRMADO.pdf');
	$sig_cliente = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], (string) $firma_cliente->signature_image_url);
	if ($sig_cliente !== '' && strpos($sig_cliente, '/signatures/sig-client-') !== false) {
		@unlink($sig_cliente);
	}
}
// Un contrato de soporte no cambia su vencimiento.
$wpdb->update(ContractService::table(), ['status' => 'at_signed', 'expires_at' => '2026-01-01 00:00:00', 'placeholders' => wp_json_encode(['email_cliente' => 'soporte@example.com'])], ['id' => $sop->id]);
ContractService::send_for_client_signature($sop->id);
ok(ContractService::get_by_id($sop->id)->expires_at === '2026-01-01 00:00:00', 'RF5: el de soporte conserva su vencimiento al enviarse');

// ---------- T15 ronda 1, hallazgo 2: la firma del cliente no reescribe el cuerpo que AT revisó ----------
// Antes, sign_as_client() copiaba el RUT que el firmante escribe sobre representante_cliente_rut sin tocar
// su tipo: el PDF firmado decía «pasaporte N° **11.111.111-1**». Y el bloque de firmas decía siempre «RUT:».
// Revisión (si es de servicios), firma de AT, envío y firma del cliente. AT firma con el RUT público de
// AutomatizaTech para que su columna no se confunda con la del cliente.
function firmar_de_punta_a_punta(int $id, array $firma_cliente) {
	global $firma_png;
	if (ContractService::get_by_id($id)->type === 'servicios') ContractService::guardar_revision($id, []);
	$a = ContractService::sign_as_at($id, ['signer_name' => 'Firma AT', 'signer_rut' => '78.363.717-0', 'signer_email' => 'at@example.com', 'method' => 'canvas', 'signature_dataurl' => $firma_png]);
	if (is_wp_error($a)) return $a;
	$e = ContractService::send_for_client_signature($id, 'cliente@example.com', 'Cliente Prueba');
	if (is_wp_error($e)) return $e;
	return ContractService::sign_as_client(ContractService::get_by_id($id)->sign_token, $firma_cliente + ['method' => 'canvas', 'signature_dataurl' => $firma_png]);
}
function borrar_firmado_r1($x) {
	if (!is_object($x)) return;
	@unlink(ContractService::storage_dir() . '/' . $x->contract_number . '-FIRMADO.pdf');
	$up = wp_upload_dir();
	foreach ([(string) ($x->signature_image_url ?? ''), (string) ($x->at_signature_image_url ?? '')] as $url) {
		$p = str_replace($up['baseurl'], $up['basedir'], $url);
		if ($p !== '' && preg_match('#/signatures/sig-(client|at)-[0-9a-f]+\.(png|jpeg)$#', $p)) @unlink($p);
	}
}
$cuerpo_r1 = ['razon_social_cliente', 'rut_cliente', 'tipo_documento_cliente', 'representante_cliente_nombre', 'representante_cliente_rut', 'tipo_documento_representante', 'email_cliente', 'telefono_cliente'];
$cuerpo_de = function ($x) use ($cuerpo_r1) {
	$ph = json_decode((string) $x->placeholders, true) ?: [];
	return array_map(function ($k) use ($ph) { return $ph[$k] ?? null; }, array_combine($cuerpo_r1, $cuerpo_r1));
};
$contacto_r1 = ['email_cliente' => 'ana@example.com', 'telefono_cliente' => '+56 9 1111 1111'];
// Empresa cuyo representante tiene pasaporte; firma alguien que escribe un RUT.
$c14 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => $completo_empresa_pas + $contacto_r1, 'created_by' => 0]);
$f14 = firmar_de_punta_a_punta($c14->id, ['signer_name' => 'Otro Firmante', 'signer_rut' => '11.111.111-1', 'signer_email' => 'firmante@example.com']);
ok(!is_wp_error($f14) && $f14->status === 'signed', 'T15 r1: la empresa con representante con pasaporte firma' . (is_wp_error($f14) ? ' (' . $f14->get_error_code() . ')' : ''));
$cuerpo14 = is_wp_error($f14) ? [] : $cuerpo_de($f14);
ok(($cuerpo14['representante_cliente_rut'] ?? '') === 'AB123456' && ($cuerpo14['tipo_documento_representante'] ?? '') === 'pasaporte' && ($cuerpo14['representante_cliente_nombre'] ?? '') === 'Ana Prueba' && ($cuerpo14['email_cliente'] ?? '') === 'ana@example.com', 'T15 r1: servicios: el cuerpo conserva el pasaporte, el nombre y el correo que AT revisó');
ok($cuerpo14 === $cuerpo_de((object) ['placeholders' => wp_json_encode($completo_empresa_pas + $contacto_r1)]),'T15 r1: servicios: ningún dato del cuerpo cambia al firmar el cliente');
ok(!is_wp_error($f14) && $f14->signer_rut === '11.111.111-1' && $f14->signer_name === 'Otro Firmante' && $f14->signer_email === 'firmante@example.com', 'T15 r1: lo que escribe el firmante queda en los campos de firma');
ok(!is_wp_error($f14) && !empty(json_decode($f14->placeholders, true)['fecha_firma_cliente']), 'T15 r1: la fecha de firma del cliente se sigue anotando');
$txt14 = is_wp_error($f14) ? '' : texto_pdf(ContractService::storage_dir() . '/' . $f14->contract_number . '-FIRMADO.pdf');
ok(strpos($txt14, 'representada por Ana Prueba') !== false && strpos($txt14, 'pasaporte N° AB123456') !== false && strpos($txt14, 'N° 11.111.111-1') === false, 'T15 r1: el PDF firmado sigue con el pasaporte revisado');
ok(strpos($txt14, 'RUT: 11.111.111-1') !== false && strpos($txt14, 'RUT: 78.363.717-0') !== false && strpos($txt14, 'Documento:') === false, 'T15 r1: el bloque de firmas dice «RUT: 11.111.111-1» para quien firmó con RUT');
ok(strpos($txt14, 'Otro Firmante · RUT 11.111.111-1') !== false, 'T15 r1: el registro de firma dice «RUT» para quien firmó con RUT');
// Persona con DNI que firma con su DNI.
$c15 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => $completo_persona_dni + ['representante_cliente_nombre' => 'Ana Prueba', 'tipo_documento_representante' => 'dni', 'representante_cliente_rut' => '12345678'] + $contacto_r1, 'created_by' => 0]);
$f15 = firmar_de_punta_a_punta($c15->id, ['signer_name' => 'Ana Prueba', 'signer_rut' => '12345678', 'signer_email' => 'ana@example.com']);
ok(!is_wp_error($f15) && $f15->status === 'signed', 'T15 r1: la persona con DNI firma' . (is_wp_error($f15) ? ' (' . $f15->get_error_code() . ')' : ''));
$txt15 = is_wp_error($f15) ? '' : texto_pdf(ContractService::storage_dir() . '/' . $f15->contract_number . '-FIRMADO.pdf');
ok(strpos($txt15, 'DNI N° 12345678') !== false && strpos($txt15, 'Documento: 12345678') !== false && strpos($txt15, 'RUT: 12345678') === false, 'T15 r1: firmante con DNI: el bloque de firmas dice «Documento: 12345678»');
ok(strpos($txt15, 'Ana Prueba · Documento 12345678') !== false && strpos($txt15, 'RUT 12345678') === false, 'T15 r1: firmante con DNI: el registro de firma dice «Documento 12345678»');
// Un contrato de soporte sigue tomando del firmante el nombre, el RUT y el correo, como hoy.
$c16 = ContractService::create_contract(['client_id' => 0, 'type' => 'soporte', 'template_id' => 'soporte_v2', 'placeholders' => ['representante_cliente_nombre' => 'Antes', 'representante_cliente_rut' => '10.000.013-K', 'email_cliente' => 'antes@example.com'], 'created_by' => 0]);
$f16 = firmar_de_punta_a_punta($c16->id, ['signer_name' => 'Cliente Soporte', 'signer_rut' => '11.111.111-1', 'signer_email' => 'soporte-firma@example.com']);
$ph16 = is_wp_error($f16) ? [] : json_decode($f16->placeholders, true);
ok(!is_wp_error($f16) && ($ph16['representante_cliente_nombre'] ?? '') === 'Cliente Soporte' && ($ph16['representante_cliente_rut'] ?? '') === '11.111.111-1' && ($ph16['email_cliente'] ?? '') === 'soporte-firma@example.com', 'T15 r1: soporte: la firma del cliente sigue escribiendo nombre, RUT y correo en el contrato');
// La página de firma rotula el campo como «RUT o documento» (placeholder igual).
$pagina_firma = (string) file_get_contents(ABSPATH . 'contracts/sign-contract.php');
ok(strpos($pagina_firma, '<label>RUT o documento</label>') !== false && strpos($pagina_firma, '<label>RUT</label>') === false && strpos($pagina_firma, 'name="signer_rut" required placeholder="12.345.678-9"') !== false, 'T15 r1: la página de firma pide «RUT o documento»');

// ---------- T15 ronda 1, hallazgo 3: una sola lista de tipos de documento ----------
ok(ContractService::tipos_documento() === at_cc_tipos_documento(), 'T15 r1: ContractService usa la lista de at_cc_tipos_documento()');
// Sin el módulo de cierre (un PHP aparte, sin WordPress), ContractService solo conoce el RUT.
$guion_r1 = tempnam(sys_get_temp_dir(), 'at-cc-r1-');
file_put_contents($guion_r1, '<?php define("ABSPATH", __DIR__ . "/"); function add_action() {} require ' . var_export(ABSPATH . 'contracts/contract-service.php', true) . '; echo json_encode(ContractService::tipos_documento());');
$sin_modulo = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($guion_r1)));
@unlink($guion_r1);
ok($sin_modulo === '{"rut":"RUT"}', 'T15 r1: sin el módulo de cierre, ContractService solo conoce el RUT: ' . $sin_modulo);

// ---------- T15 ronda 2: el rótulo del firmante sale del tipo guardado, no de adivinar ----------
// Antes el PDF decía «RUT» si el número pasaba el dígito verificador chileno. Un DNI hecho solo de
// dígitos pasa por azar (~1 de cada 11): «111111111» es un RUT válido como texto, y el bloque de
// firmas decía «RUT: 111111111» mientras el cuerpo del mismo contrato decía «DNI N° 111111111».
// De punta a punta: persona con DNI «111111111» que firma con ese número.
$c17 = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['tipo_documento_cliente' => 'dni', 'rut_cliente' => '111111111', 'representante_cliente_nombre' => 'Ana Prueba', 'tipo_documento_representante' => 'dni', 'representante_cliente_rut' => '111111111'] + $completo_persona + $contacto_r1, 'created_by' => 0]);
$f17 = firmar_de_punta_a_punta($c17->id, ['signer_name' => 'Ana Prueba', 'signer_rut' => '111111111', 'signer_email' => 'ana@example.com']);
ok(!is_wp_error($f17) && $f17->status === 'signed', 'T15 r2: la persona con un DNI que también pasa como RUT firma' . (is_wp_error($f17) ? ' (' . $f17->get_error_code() . ')' : ''));
$txt17 = is_wp_error($f17) ? '' : texto_pdf(ContractService::storage_dir() . '/' . $f17->contract_number . '-FIRMADO.pdf');
ok(strpos($txt17, 'DNI N° 111111111') !== false && strpos($txt17, 'Documento: 111111111') !== false && strpos($txt17, 'RUT: 111111111') === false, 'T15 r2: DNI que pasa como RUT: el bloque de firmas dice «Documento», como el cuerpo');
ok(strpos($txt17, 'Ana Prueba · Documento 111111111') !== false && strpos($txt17, 'RUT 111111111') === false, 'T15 r2: DNI que pasa como RUT: el registro de firma dice «Documento»');
ok(strpos($txt17, 'RUT: 78.363.717-0') !== false && strpos($txt17, 'Firma AT · RUT 78.363.717-0') !== false, 'T15 r2: la columna y el registro de AT siguen diciendo «RUT»');
// El PDF armado directo desde los marcadores (sin base), para los demás casos.
function rotulos_pdf_r2(array $ph, ?array $firma_cliente): string {
	require_once get_template_directory() . '/lib/contract-pdf-fpdf.php';
	$ph['comparecencia_cliente'] = ContractService::comparecencia_cliente($ph);
	$firmas = ['at' => ['signer_name' => 'Firma AT', 'signer_rut' => '78.363.717-0', 'signed_at' => '2026-09-26 10:00:00']];
	if ($firma_cliente !== null) $firmas['client'] = $firma_cliente + ['signer_email' => 'firmante@example.com', 'signed_at' => '2026-09-26 10:05:00'];
	// El cuerpo empieza en el primer «##» (lo anterior es el título del documento y no se dibuja).
	$pdf = new ContractPDFFPDF($ph, "## COMPARECIENTES\n\n{{comparecencia_cliente}}\n", $firmas);
	$pdf->build();
	$archivo = tempnam(sys_get_temp_dir(), 'at-cc-r2-');
	$pdf->Output('F', $archivo);
	$texto = texto_pdf($archivo);
	@unlink($archivo);
	return $texto;
}
$empresa_dni_r2 = ['tipo_documento_representante' => 'dni', 'representante_cliente_rut' => '111111111'] + $completo_empresa;
$t = rotulos_pdf_r2($empresa_dni_r2, ['signer_name' => 'Ana Prueba', 'signer_rut' => '111111111']);
ok(strpos($t, 'DNI N° 111111111') !== false && strpos($t, 'Documento: 111111111') !== false && strpos($t, 'Ana Prueba · Documento 111111111') !== false && strpos($t, 'RUT: 111111111') === false, 'T15 r2: empresa con representante con DNI que pasa como RUT: firmas y registro dicen «Documento»');
$t = rotulos_pdf_r2($empresa_dni_r2, null);
ok(strpos($t, 'Documento: 111111111') !== false && strpos($t, 'RUT: 111111111') === false && strpos($t, 'Pendiente de firma') !== false, 'T15 r2: antes de que firme el cliente, su columna también usa el tipo guardado');
$t = rotulos_pdf_r2($empresa_dni_r2, ['signer_name' => 'Ana Prueba', 'signer_rut' => '10.000.013-K']);
ok(strpos($t, 'RUT: 10.000.013-K') !== false && strpos($t, 'Ana Prueba · RUT 10.000.013-K') !== false, 'T15 r2: quien firma con el RUT de la empresa lleva «RUT»');
$persona_dni_r2 = ['tipo_documento_cliente' => 'dni', 'rut_cliente' => '111111111'] + $completo_persona;
$t = rotulos_pdf_r2($persona_dni_r2, ['signer_name' => 'Ana Prueba', 'signer_rut' => '111.111.111']);
ok(strpos($t, 'Documento: 111.111.111') !== false && strpos($t, 'RUT: 111.111.111') === false, 'T15 r2: el mismo DNI escrito con puntos se reconoce y dice «Documento»');
// Para una persona manda el documento del cuerpo (tipo_documento_cliente), aunque quede un tipo de representante viejo.
$t = rotulos_pdf_r2(['tipo_documento_cliente' => 'pasaporte', 'rut_cliente' => '111111111', 'tipo_documento_representante' => 'rut', 'representante_cliente_rut' => '111111111'] + $completo_persona, ['signer_name' => 'Ana Prueba', 'signer_rut' => '111111111']);
ok(strpos($t, 'pasaporte N° 111111111') !== false && strpos($t, 'Documento: 111111111') !== false && strpos($t, 'RUT: 111111111') === false, 'T15 r2: persona: el rótulo sigue al documento que imprime el cuerpo');
// Sin tipo guardado (soporte o contratos anteriores a la Task 15) se sigue infiriendo por el dígito verificador.
$soporte_r2 = ['representante_cliente_nombre' => 'Cliente Soporte', 'representante_cliente_rut' => '111111111'];
$t = rotulos_pdf_r2($soporte_r2, ['signer_name' => 'Cliente Soporte', 'signer_rut' => '111111111']);
ok(strpos($t, 'RUT: 111111111') !== false && strpos($t, 'Cliente Soporte · RUT 111111111') !== false, 'T15 r2: sin tipo guardado, un RUT válido sigue diciendo «RUT»');
$t = rotulos_pdf_r2($soporte_r2, ['signer_name' => 'Cliente Soporte', 'signer_rut' => '12345678']);
ok(strpos($t, 'Documento: 12345678') !== false && strpos($t, 'Cliente Soporte · Documento 12345678') !== false, 'T15 r2: sin tipo guardado, un número que no es RUT sigue diciendo «Documento»');

$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id IN (%d, %d, %d, %d, %d, %d, %d, %d)", $c->id, $sop->id, $c2->id, $c3->id, $c4->id, $c5->id, $c6->id, $c7->id));
foreach ([$c, $sop, $c2, $c3, $c4, $c5, $c6, $c7] as $x) { @unlink(pdf_de($x)); }
// Task 15: sus contratos de prueba.
foreach ([$c10, $c11, $c12, $c13] as $x) {
	if (is_object($x)) {
		$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id = %d", $x->id));
		@unlink(pdf_de($x));
	}
}
// T15 rondas 1 y 2: los contratos firmados de punta a punta, con su PDF firmado y sus imágenes de firma.
foreach ([$c14, $c15, $c16, $c17] as $x) {
	if (is_object($x)) {
		borrar_firmado_r1(ContractService::get_by_id($x->id));
		$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id = %d", $x->id));
		@unlink(pdf_de($x));
	}
}
fin();
