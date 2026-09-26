<?php
// Correr: php tests/cierre/puras-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Salidas y transiciones
ok(at_cc_salidas() === ['acepta' => 'aceptada', 'evalua' => 'evaluando', 'rechaza' => 'rechazada'], 'salidas');
// Task 8 (ajuste del controlador): tipos de Seguimiento que crm-ai-completo.php debe excluir de
// las dos líneas de tiempo públicas (cliente y prospecto).
ok(at_cc_tipos_internos() === ['cierre_incompleto', 'aviso_operativo', 'pedido_respuesta', 'mensaje_whatsapp'], 'tipos internos: nunca a una página pública');
ok(at_cc_transicion_respuesta_valida('sent', 'aceptada'), 'sent -> aceptada');
ok(at_cc_transicion_respuesta_valida('sent', 'evaluando') && at_cc_transicion_respuesta_valida('sent', 'rechazada'), 'sent -> evaluando / rechazada');
ok(at_cc_transicion_respuesta_valida('evaluando', 'aceptada') && at_cc_transicion_respuesta_valida('evaluando', 'rechazada'), 'evaluando -> aceptada / rechazada');
ok(at_cc_transicion_respuesta_valida('rechazada', 'aceptada'), 'rechazada -> aceptada (cambió de opinión)');
ok(!at_cc_transicion_respuesta_valida('rechazada', 'evaluando'), 'rechazada no vuelve a evaluando');
ok(!at_cc_transicion_respuesta_valida('aceptada', 'rechazada') && !at_cc_transicion_respuesta_valida('aceptada', 'aceptada'), 'aceptada es final');
ok(!at_cc_transicion_respuesta_valida('borrador', 'aceptada') && !at_cc_transicion_respuesta_valida('lista', 'aceptada'), 'sin enviar no se acepta desde la página');
ok(at_cc_transicion_respuesta_valida('lista', 'aceptada', true) && at_cc_transicion_respuesta_valida('pending', 'aceptada', true), 'a mano sí desde lista y pending');
ok(!at_cc_transicion_respuesta_valida('borrador', 'aceptada', true), 'a mano no desde borrador');
ok(!at_cc_transicion_respuesta_valida('pending', 'evaluando', true), 'a mano solo se registra aceptación');
ok(at_cc_puede_pedir_respuesta('sent') && at_cc_puede_pedir_respuesta('evaluando') && at_cc_puede_pedir_respuesta('rechazada'), 'se pide respuesta en sent, evaluando y rechazada');
ok(!at_cc_puede_pedir_respuesta('aceptada') && !at_cc_puede_pedir_respuesta('borrador') && !at_cc_puede_pedir_respuesta('lista'), 'no se pide en aceptada, borrador ni lista');

// Task 14 (aprobada por Luis el 26-sep): estado «Archivada». Se archiva lo viejo o reemplazado; el
// cliente ya no puede responderla y Luis la puede desarchivar o registrar una aceptación a mano.
foreach (['sent', 'evaluando', 'rechazada', 'pending', 'lista', 'borrador', 'draft', 'error'] as $s) {
	ok(at_cc_puede_archivar($s), "se puede archivar desde {$s}");
}
foreach (['aceptada', 'contracted', 'ajustando', 'archivada', 'generando', '', 'raro'] as $s) {
	ok(!at_cc_puede_archivar($s), "no se puede archivar desde «{$s}»");
}
foreach (['aceptada', 'evaluando', 'rechazada'] as $hacia) {
	ok(!at_cc_transicion_respuesta_valida('archivada', $hacia), "el cliente no responde una archivada (archivada -> {$hacia})");
}
// T14 ronda 1 (2ª revisión), hallazgo 1: a mano, una archivada se acepta solo si el estado en que
// estaba antes de archivarla (4º argumento) también lo permitía. Un borrador archivado (v3 sin versión
// final, precios «Por confirmar») nunca se pudo aceptar a mano, y archivarlo no debe habilitarlo.
foreach (['sent', 'evaluando', 'rechazada', 'pending', 'lista'] as $antes) {
	ok(at_cc_transicion_respuesta_valida('archivada', 'aceptada', true, $antes), "a mano sí se registra la aceptación de una archivada desde {$antes}");
}
foreach (['borrador', 'draft', 'error', '', 'archivada', 'aceptada', 'raro'] as $antes) {
	ok(!at_cc_transicion_respuesta_valida('archivada', 'aceptada', true, $antes), "a mano no se acepta una archivada desde «{$antes}»");
}
ok(!at_cc_transicion_respuesta_valida('archivada', 'aceptada', true), 'a mano, sin decir desde qué estado se archivó, no se acepta una archivada');
ok(!at_cc_transicion_respuesta_valida('archivada', 'aceptada', false, 'sent'), 'el cliente no acepta una archivada aunque se haya archivado desde sent');
ok(!at_cc_transicion_respuesta_valida('archivada', 'evaluando', true) && !at_cc_transicion_respuesta_valida('archivada', 'rechazada', true), 'a mano, desde archivada solo se registra aceptación');
ok(!at_cc_transicion_respuesta_valida('sent', 'archivada') && !at_cc_transicion_respuesta_valida('sent', 'archivada', true), 'una respuesta nunca archiva (eso es solo de Luis)');
ok(!at_cc_puede_pedir_respuesta('archivada'), 'no se pide respuesta de una archivada (sin barra pública ni «Pedir respuesta»)');

// Montos y anticipo
ok(at_cc_monto_de_etiqueta('$2.000.000 en 2 pagos') === 2000000, 'monto con puntos');
ok(at_cc_monto_de_etiqueta('$250.000 en 2 pagos') === 250000, 'monto $250.000');
ok(at_cc_monto_de_etiqueta('$1.250.000 en 2 pagos (estimado)') === 1250000, 'monto estimado');
ok(at_cc_monto_de_etiqueta('$120.000 al mes') === 120000, 'monto mensual se lee');
ok(at_cc_monto_de_etiqueta('Incluido') === null && at_cc_monto_de_etiqueta('Por confirmar') === null, 'sin monto');
ok(at_cc_es_mensual('$120.000 al mes') && at_cc_es_mensual('$50.000 mensual') && !at_cc_es_mensual('$2.000.000 en 2 pagos'), 'mensual');
$filas = [
	['service' => 'Fase 1', 'price_label' => '$250.000 en 2 pagos'],
	['service' => 'Fase 2', 'price_label' => '$400.000 en 2 pagos'],
	['service' => 'Google Ads', 'price_label' => '$120.000 al mes'],
	['service' => 'Soporte', 'price_label' => 'Incluido'],
];
ok(at_cc_total_unico($filas) === 650000, 'total único sin mensuales ni incluidos');
ok(at_cc_anticipo($filas) === 325000, 'anticipo 50 %');
ok(at_cc_anticipo([['service' => 'X', 'price_label' => '$2.000.000 en 2 pagos']]) === 1000000, 'anticipo de una fase');
ok(at_cc_anticipo([['service' => 'X', 'price_label' => 'Por confirmar']]) === null, 'anticipo sin montos');
ok(at_cc_anticipo([['service' => 'X', 'price_label' => '$333']]) === 167, 'anticipo redondea');
ok(at_cc_formato_clp(1000000) === '$1.000.000' && at_cc_formato_clp(0) === '$0', 'formato CLP');

// Correo, RUT y teléfono
ok(at_cc_email_normalizado('  Ana@Example.COM ') === 'ana@example.com', 'correo normalizado');
ok(at_cc_rut_valido('78.363.717-0'), 'RUT de AutomatizaTech');
ok(at_cc_rut_valido('11.111.111-1') && at_cc_rut_valido('111111111'), 'RUT con y sin formato');
ok(at_cc_rut_valido('10.000.013-K') && at_cc_rut_valido('10000013-k'), 'RUT con K');
ok(!at_cc_rut_valido('11.111.111-2') && !at_cc_rut_valido('') && !at_cc_rut_valido('K') && !at_cc_rut_valido('abc'), 'RUT inválidos');
ok(at_cc_rut_formato('111111111') === '11.111.111-1' && at_cc_rut_formato('10000013k') === '10.000.013-K', 'formato de RUT');
ok(at_cc_telefono_normalizado('+56 9 1234 5678') === '56912345678', 'teléfono +56 9 con espacios');
ok(at_cc_telefono_normalizado('912345678') === '56912345678', 'teléfono de 9 dígitos');
ok(at_cc_telefono_normalizado('56912345678') === '56912345678', 'teléfono ya normalizado');
ok(at_cc_telefono_normalizado('') === '', 'teléfono vacío');

// Payload y filas
$json = '{"pricing_rows":[{"service":"A","price_label":"$1"}]}';
ok(at_cc_json_de_payload($json)['pricing_rows'][0]['service'] === 'A', 'JSON normal');
ok(at_cc_json_de_payload(addslashes($json))['pricing_rows'][0]['service'] === 'A', 'JSON con barras (propuestas viejas)');
ok(at_cc_json_de_payload("Texto antes\n" . $json . "\nDespués")['pricing_rows'][0]['service'] === 'A', 'JSON dentro de texto');
ok(at_cc_json_de_payload('no es json') === null, 'sin JSON');
ok(at_cc_filas_de_payload($json) === [['service' => 'A', 'price_label' => '$1']], 'filas del payload');
ok(at_cc_filas_de_payload('nada') === [], 'sin filas');
ok(at_cc_filas_aceptadas($filas, ['2', 0, 0, 9, 'x']) === [$filas[0], $filas[2]], 'filas aceptadas en orden, sin repetir ni inválidas');
ok(at_cc_filas_aceptadas($filas, []) === [], 'ninguna fila');
ok(at_cc_filas_aceptadas([['service' => '', 'price_label' => '$1']], [0]) === [], 'fila sin servicio no cuenta');
ok(at_cc_huella('abc') === hash('sha256', 'abc'), 'huella sha256');

// Fechas y canales
ok(at_cc_fecha_larga('2026-09-25 14:00:00') === '25 de septiembre de 2026' && at_cc_fecha_larga('2026-01-05') === '5 de enero de 2026', 'fecha larga');
ok(at_cc_fecha_larga('basura') === '' && at_cc_fecha_larga('2026-02-30') === '', 'fecha larga inválida');
ok(at_cc_fecha_declarada('2026-09-20', '2026-09-25') === '2026-09-20 12:00:00', 'fecha declarada anterior');
ok(at_cc_fecha_declarada('2026-09-26', '2026-09-25') === '' && at_cc_fecha_declarada('20-09-2026', '2026-09-25') === '', 'fecha futura o mal escrita');
ok(array_keys(at_cc_canales_manuales()) === ['whatsapp', 'correo', 'llamada', 'reunion', 'otro'], 'canales manuales');
ok(at_cc_canal_texto(['canal' => 'pagina']) === 'en la página de la propuesta', 'canal página');
ok(at_cc_canal_texto(['canal' => 'whatsapp']) === 'con un botón en WhatsApp', 'canal botón de WhatsApp');
ok(at_cc_canal_texto(['canal' => 'manual', 'canal_manual' => 'whatsapp']) === 'por WhatsApp, registrada por AutomatizaTech', 'canal manual');

// URL, bloque del correo y WhatsApp
ok(at_cc_url_respuesta('https://ejemplo.cl/', 'aB3 x', 'aceptar') === 'https://ejemplo.cl/ver-presentacion.php?id=aB3%20x&responder=aceptar', 'URL de respuesta');
ok(at_cc_url_respuesta('https://ejemplo.cl', 'abc') === 'https://ejemplo.cl/ver-presentacion.php?id=abc', 'URL sin responder');
$bl = at_cc_bloque_aceptar_html('https://e.cl/?a=1&b=2', 'https://e.cl/ev');
ok(strpos($bl, 'Aceptar la propuesta') !== false && strpos($bl, 'href="https://e.cl/?a=1&amp;b=2"') !== false && strpos($bl, 'https://e.cl/ev') !== false, 'bloque de aceptar con enlaces escapados');
// Revisión final (26-sep), hallazgo 8: sin URL de evaluar (propuesta rechazada) no va el enlace de dudas.
$bl_sin = at_cc_bloque_aceptar_html('https://e.cl/?a=1', '');
ok(strpos($bl_sin, 'Aceptar la propuesta') !== false && strpos($bl_sin, '¿Tienes dudas') === false && substr_count($bl_sin, '<a ') === 1, 'RF8: bloque sin URL de evaluar: solo el botón de aceptar');
ok(strpos($bl, '¿Tienes dudas') !== false && substr_count($bl, '<a ') === 2, 'RF8: con URL de evaluar sigue el enlace de dudas');
$t = at_cc_texto_whatsapp('Ana', 'Muebles', 'https://e.cl/x');
ok(strpos($t, 'Hola Ana') === 0 && strpos($t, 'para Muebles') !== false && strpos($t, 'https://e.cl/x') !== false, 'texto de WhatsApp');
ok(at_cc_url_wa_me('+56 9 1234 5678', 'a b') === 'https://wa.me/56912345678?text=a%20b', 'enlace wa.me');
ok(at_cc_url_wa_me('', 'x') === '', 'sin teléfono no hay enlace');

// Marcadores del contrato de servicios
$m = at_cc_marcadores_servicios([
	'empresa' => 'Muebles', 'representante' => 'Ana', 'rut_representante' => '11.111.111-1',
	'email' => 'ana@example.com', 'telefono' => '+56 9 1111 1111', 'codigo_propuesta' => 'abc123',
	'fecha_propuesta' => '24 de septiembre de 2026', 'fecha_aceptacion' => '25 de septiembre de 2026',
	'canal_aceptacion' => 'en la página de la propuesta', 'filas_aceptadas' => [$filas[0], $filas[2]],
	'filas_siguientes' => [$filas[1]], 'alcance' => 'Sitio', 'entregables' => '- Sitio', 'plazo' => 'Ocho semanas',
]);
ok($m['monto_total'] === '$250.000', 'monto total sin mensuales');
ok(strpos($m['forma_pago'], '$125.000') !== false && strpos($m['forma_pago'], 'Google Ads') !== false, 'forma de pago con anticipo y mensual');
ok($m['servicios_contratados'] === "- **Fase 1**: \$250.000 en 2 pagos\n- **Google Ads**: \$120.000 al mes", 'servicios en lista');
ok($m['fases_siguientes'] === '- **Fase 2**: $400.000 en 2 pagos', 'fases siguientes');
ok(!isset($m['rut_cliente']) && !isset($m['domicilio_cliente']), 'vacíos no se incluyen (quedan en blanco para llenar)');
ok(array_diff(array_keys($m), at_cc_claves_contrato_servicios()) === [], 'solo claves conocidas');
$m2 = at_cc_marcadores_servicios(['representante' => 'Ana', 'filas_aceptadas' => [['service' => 'X', 'price_label' => 'Por confirmar']]]);
ok(!isset($m2['monto_total']) && $m2['razon_social_cliente'] === 'Ana' && $m2['fases_siguientes'] === 'La propuesta no tiene fases siguientes.', 'sin montos ni empresa');
// Task 5b ronda 2: 'aceptante_nombre' es un marcador inmutable con el nombre de quien aceptó,
// escrito siempre (haya empresa o no), para que ContractService lo use como fuente estable
// aunque representante_cliente_nombre se borre después en la revisión.
ok(($m['aceptante_nombre'] ?? null) === 'Ana' && ($m2['aceptante_nombre'] ?? null) === 'Ana', "aceptante_nombre guarda el nombre de quien aceptó");
// Task 5b: tipo de cliente opcional (persona o empresa); sin tipo, todo sigue como antes.
ok(in_array('tipo_cliente', at_cc_claves_contrato_servicios(), true), 'tipo_cliente es un marcador conocido');
ok(!isset($m['tipo_cliente']) && !isset($m2['tipo_cliente']), 'sin tipo no hay marcador de tipo');
$m3 = at_cc_marcadores_servicios(['representante' => 'Ana', 'tipo_cliente' => 'persona']);
$m4 = at_cc_marcadores_servicios(['empresa' => 'Muebles', 'representante' => 'Ana', 'tipo_cliente' => 'empresa']);
$m5 = at_cc_marcadores_servicios(['representante' => 'Ana', 'tipo_cliente' => 'otra cosa']);
ok(($m3['tipo_cliente'] ?? '') === 'persona' && ($m4['tipo_cliente'] ?? '') === 'empresa', 'tipo_cliente persona o empresa pasa al marcador');
ok(!isset($m5['tipo_cliente']), 'un tipo desconocido no pasa al marcador');
ok(array_diff(array_keys($m4), at_cc_claves_contrato_servicios()) === [], 'con tipo, solo claves conocidas');

// Mensajes de la página
ok(at_cc_mensaje_respuesta('aceptada')['tipo'] === 'ok' && at_cc_mensaje_respuesta('datos')['tipo'] === 'aviso' && at_cc_mensaje_respuesta('error')['tipo'] === 'error', 'mensajes');
ok(at_cc_mensaje_respuesta('inventado') === null, 'mensaje desconocido');
// Task 7 (ajuste): mensajes de «Datos para tu contrato».
ok(at_cc_mensaje_respuesta('datos_ok') === ['tipo' => 'ok', 'texto' => '¡Listo! Con estos datos preparamos tu contrato.'], 'mensaje datos_ok');
ok(at_cc_mensaje_respuesta('datos_recibidos') === ['tipo' => 'ok', 'texto' => 'Recibimos tus datos. Luis los revisa junto con tu contrato.'], 'mensaje datos_recibidos');
ok(at_cc_mensaje_respuesta('datos_contrato') === ['tipo' => 'aviso', 'texto' => 'Revisa los datos del contrato: la dirección y, si es una empresa, su razón social y un RUT válido.'], 'mensaje datos_contrato');

// Evidencia
$tmp = sys_get_temp_dir() . '/at-cc-prueba-' . getmypid();
file_put_contents($tmp . '.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
file_put_contents($tmp . '.pdf.png', "%PDF-1.4\n%falso\n");
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_OK, 'size' => 70, 'tmp_name' => $tmp . '.png']) === '', 'PNG válido');
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_OK, 'size' => 20, 'tmp_name' => $tmp . '.pdf.png']) !== '', 'PDF renombrado a .png se rechaza');
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024, 'tmp_name' => $tmp . '.png']) !== '', 'más de 5 MB se rechaza');
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_INI_SIZE, 'size' => 0, 'tmp_name' => '']) !== '', 'error de subida se rechaza');
@unlink($tmp . '.png');
@unlink($tmp . '.pdf.png');
$multi = ['name' => ['a.png', '', 'b.jpg'], 'type' => ['image/png', '', 'image/jpeg'], 'tmp_name' => ['/t/a', '', '/t/b'], 'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK], 'size' => [10, 0, 20]];
$n = at_cc_archivos_normalizados($multi);
ok(count($n) === 2 && $n[0]['name'] === 'a.png' && $n[1]['tmp_name'] === '/t/b', 'archivos múltiples normalizados sin los vacíos');
ok(at_cc_archivos_normalizados([]) === [], 'sin archivos');
ok(at_cc_mimes_evidencia() === ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 'tipos aceptados');

// Correos
$banco = ['banco' => 'Banco de Prueba', 'tipo' => 'Cuenta corriente', 'numero' => '123', 'titular' => 'AutomatizaTech SpA', 'rut' => '78.363.717-0'];
ok(at_cc_banco_completo($banco) && !at_cc_banco_completo(['banco' => 'X']), 'banco completo');
$b = at_cc_bienvenida_html(['nombre' => 'Ana <b>', 'empresa' => 'Muebles', 'anticipo' => 1000000, 'banco' => $banco, 'correo_pago' => 'pagos@example.com', 'whatsapp' => '+56 9 2700 2984', 'url_portal' => 'https://example.com/portal?a=1&b=2', 'logo' => '', 'con_propuesta' => true]);
ok(strpos($b, '$1.000.000') !== false && strpos($b, 'Banco de Prueba') !== false, 'bienvenida con anticipo y banco');
ok(strpos($b, 'Ana &lt;b&gt;') !== false, 'nombre escapado');
ok(strpos($b, 'https://wa.me/56927002984') !== false, 'enlace al WhatsApp de AT');
ok(strpos($b, 'reunión de inicio') !== false && strpos($b, 'bienvenida oficial') !== false, 'anuncia la reunión de inicio');
ok(strpos($b, 'https://example.com/portal?a=1&amp;b=2') !== false, 'portal escapado');
$b2 = at_cc_bienvenida_html(['nombre' => 'Ana', 'empresa' => '', 'anticipo' => null, 'banco' => [], 'con_propuesta' => true]);
ok(strpos($b2, 'por separado') !== false && strpos($b2, 'va en tu contrato') !== false, 'sin banco ni anticipo');
$b3 = at_cc_bienvenida_html(['nombre' => 'Ana', 'con_propuesta' => false]);
ok(strpos($b3, 'anticipo') === false && strpos($b3, 'Tu contrato') === false, 'sin propuesta no habla de contrato ni anticipo');
// Task 5b: la bienvenida pide los datos del contrato (a nombre de quién, RUT y dirección).
$linea_datos = 'Para preparar tu contrato necesitamos saber a nombre de quién va (tú o tu empresa), el RUT y la dirección. Si ya los completaste en la página de la propuesta, no tienes que hacer nada; si no, respóndenos este correo con esos datos.';
ok(strpos($b, $linea_datos) !== false && strpos($b2, $linea_datos) !== false, 'con propuesta pide los datos del contrato (texto exacto)');
ok(strpos($b3, 'Para preparar tu contrato') === false, 'sin propuesta no pide los datos del contrato');
$pos_datos = strpos($b, $linea_datos);
ok($pos_datos !== false && $pos_datos < strpos($b, 'Un abrazo'), 'la línea de los datos va antes del cierre del correo');
$pd = at_cc_pedido_respuesta_html('Ana', 'Muebles', '<div>BLOQUE</div>', '', 'https://e.cl/v');
ok(strpos($pd, 'Hola <strong>Ana</strong>') !== false && strpos($pd, '<div>BLOQUE</div>') !== false && strpos($pd, 'https://e.cl/v') !== false, 'correo para pedir respuesta');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
