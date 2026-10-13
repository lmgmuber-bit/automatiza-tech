<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/pagina-wp-test.php
// T7 ronda 1, hallazgo 1: automatizatech.cl está detrás del CDN de Hostinger (no de Cloudflare), así
// que CF-Connecting-IP y X-Forwarded-For las puede poner el propio cliente. La IP de evidencia debe
// ser siempre REMOTE_ADDR, y el límite de intentos no debe poder saltarse cambiando esas cabeceras.
require __DIR__ . '/wp-bootstrap.php';

$marca = 'prueba-cierre-pagina-' . strtolower(wp_generate_password(6, false, false));

// ---------- at_cc_ip() ignora las cabeceras manipulables; at_cc_ip_reenviada() las recoge aparte ----------
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): sin cabeceras, usa REMOTE_ADDR');
ok(at_cc_ip_reenviada() === '', 'at_cc_ip_reenviada(): sin cabeceras, vacío');

$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.99';
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): con CF-Connecting-IP falsa, sigue siendo REMOTE_ADDR');
ok(at_cc_ip_reenviada() === '198.51.100.99', 'at_cc_ip_reenviada(): recoge la CF-Connecting-IP, solo informativa');

$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 10.0.0.1';
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): con X-Forwarded-For también falso, sigue siendo REMOTE_ADDR');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
ok(at_cc_ip_reenviada() === '198.51.100.1', 'at_cc_ip_reenviada(): sin CF-Connecting-IP, toma el primer valor de X-Forwarded-For');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

// ---------- el límite por código (el principal) no se salta cambiando la cabecera ----------
$accion = 'test_' . $marca;
$codigo = 'codigo-' . $marca;
for ($i = 0; $i < 3; $i++) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.10.10.' . $i; // una cabecera distinta en cada envío
	ok(at_cc_limite_codigo_ok($accion, $codigo, 3, HOUR_IN_SECONDS) === true, 'límite por código: intento ' . ($i + 1) . ' de 3 permitido');
}
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.10.10.99'; // otra cabecera más, distinta a las tres anteriores
ok(at_cc_limite_codigo_ok($accion, $codigo, 3, HOUR_IN_SECONDS) === false, 'límite por código: el 4º intento se bloquea aunque la cabecera cambió en cada envío');
ok(at_cc_limite_codigo_ok($accion, $codigo . '-otro', 3, HOUR_IN_SECONDS) === true, 'límite por código: un código de propuesta distinto tiene su propio contador');

// ---------- el límite amplio por IP real (REMOTE_ADDR) tampoco lo evita la cabecera ----------
$accion_ip = 'test_ip_' . $marca;
$_SERVER['REMOTE_ADDR'] = '203.0.113.30';
for ($i = 0; $i < 2; $i++) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.20.20.' . $i;
	ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === true, 'límite por IP: intento ' . ($i + 1) . ' de 2 permitido');
}
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.20.20.99';
ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === false, 'límite por IP: se bloquea aunque la cabecera cambió (la clave es REMOTE_ADDR, no la cabecera)');
$_SERVER['REMOTE_ADDR'] = '203.0.113.31'; // otra conexión real: no comparte el contador de la anterior
ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === true, 'límite por IP: otra IP real (REMOTE_ADDR distinto) tiene su propio contador');

// Limpieza: cabeceras de prueba y los transients que quedaron con el máximo alcanzado.
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
delete_transient('at_cc_lim_cod_' . md5($accion . '|' . $codigo));
delete_transient('at_cc_lim_cod_' . md5($accion . '|' . $codigo . '-otro'));
delete_transient('at_cc_lim_' . md5($accion_ip . '|203.0.113.30'));
delete_transient('at_cc_lim_' . md5($accion_ip . '|203.0.113.31'));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// ================= Task 15: aceptar en la página con RUT, DNI o pasaporte =================
// Un cliente extranjero no tiene RUT: el diálogo pide el tipo de documento y el número, y solo el RUT
// se valida con dígito verificador. La acción real termina con wp_safe_redirect()+exit, así que se
// corre en un proceso aparte con el mismo hijo de pagina-respuesta-publica-wp-test.php.
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$creadas = [];
function t15_crear(string $marca, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'      => $marca . '-t15-' . count($creadas) . '@example.com',
		'unique_link_id'    => substr(md5($marca . 't15' . count($creadas) . microtime(true)), 0, 12),
		'client_name'       => 'Cliente Prueba', 'company_name' => '[PRUEBA] Documento', 'phone' => '',
		'status'            => 'sent', 'flujo' => 'v3',
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text'   => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
/** Task 18: por defecto manda también los datos del contrato (persona + dirección), que ahora son
 *  obligatorios; un campo en null se quita del POST (para simular un formulario sin él). */
function t15_aceptar(object $p, array $campos): array {
	$post = array_filter(array_merge([
		'action' => 'at_cc_responder', 'codigo' => (string) $p->unique_link_id, 'salida' => 'acepta',
		'nombre' => 'Ana Prueba', 'acepto' => '1', 'filas' => ['0'],
		'tipo' => 'persona', 'direccion' => 'Calle Prueba 123, Santiago',
		'_wpnonce' => wp_create_nonce('at_cc_responder_' . $p->unique_link_id),
	], $campos), function ($v) { return $v !== null; });
	$proc = proc_open([PHP_BINARY, __DIR__ . '/pagina-respuesta-publica-wp-test-run.php', 'responder', 'POST', json_encode($post), '{}'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($proc)) { fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n"); exit(2); }
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	$correos = [];
	if (preg_match_all('/^MAIL:(.*)$/m', $out, $mm)) {
		foreach ($mm[1] as $b) { $correos[] = json_decode((string) base64_decode(trim($b)), true); }
	}
	return ['redirect' => preg_match('/^REDIRECT:(.*)$/m', $out, $m) ? trim($m[1]) : '', 'correos' => $correos, 'stderr' => trim($err)];
}
function t15_meta(int $id): array {
	global $wpdb, $det;
	$m = $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $id));
	return $m ? (json_decode((string) $m, true) ?: []) : [];
}
function t15_correo_luis(array $correos): string {
	foreach ($correos as $c) {
		if (($c['to'] ?? '') === get_option('admin_email') && strpos((string) ($c['subject'] ?? ''), 'aceptó la propuesta') !== false) {
			return (string) $c['message'];
		}
	}
	return '';
}

// El diálogo «Aceptar la propuesta» pide el tipo de documento (RUT elegido) y el número.
$t15_render = t15_crear($marca, $creadas);
ob_start();
at_cc_render_barra($t15_render);
$h15 = (string) ob_get_clean();
ok(strpos($h15, '<label for="at-cc-tipo-doc">Tu documento</label>') !== false && strpos($h15, '<select id="at-cc-tipo-doc" name="tipo_documento">') !== false, 'T15: el diálogo pide «Tu documento» con un selector name="tipo_documento"');
ok(strpos($h15, '<option value="rut" selected>RUT</option>') !== false && strpos($h15, '<option value="dni">DNI</option>') !== false && strpos($h15, '<option value="pasaporte">Pasaporte</option>') !== false, 'T15: el selector ofrece RUT (elegido), DNI y Pasaporte');
ok(strpos($h15, '<label for="at-cc-documento">Número de documento</label>') !== false && strpos($h15, 'name="documento" required placeholder="12.345.678-9"') !== false, 'T15: el número va en «Número de documento» (name="documento", placeholder 12.345.678-9)');
ok(strpos($h15, 'name="rut"') === false && strpos($h15, 'Tu RUT') === false, 'T15: el diálogo ya no pide «Tu RUT»');

// DNI válido: aceptada, con el tipo y el número en la metadata, en el contrato y en el aviso a Luis.
$t15_dni = t15_crear($marca, $creadas);
$r15 = t15_aceptar($t15_dni, ['tipo_documento' => 'dni', 'documento' => ' 12.345.678 ']);
ok(strpos($r15['redirect'], 'respuesta=aceptada') !== false && at_cc_propuesta_por_id((int) $t15_dni->id)->status === 'aceptada', 'T15: aceptar con DNI válido: la propuesta queda aceptada: ' . $r15['redirect'] . ' ' . $r15['stderr']);
$m15 = t15_meta((int) $t15_dni->id);
ok(($m15['tipo_documento'] ?? null) === 'dni' && ($m15['documento'] ?? null) === '12.345.678' && ($m15['rut'] ?? null) === '', 'T15: la metadata guarda tipo_documento=dni y el número formateado, sin RUT: ' . wp_json_encode(array_intersect_key($m15, ['tipo_documento' => 1, 'documento' => 1, 'rut' => 1])));
$c15 = at_cc_contrato_de_propuesta((int) $t15_dni->id);
$ph15 = $c15 ? (json_decode((string) $c15->placeholders, true) ?: []) : [];
ok(($ph15['tipo_documento_representante'] ?? '') === 'dni' && ($ph15['representante_cliente_rut'] ?? '') === '12.345.678', 'T15: el contrato recibe el DNI de quien aceptó como documento del representante');
$luis15 = t15_correo_luis($r15['correos']);
ok(strpos($luis15, 'Documento: DNI 12.345.678') !== false && strpos($luis15, 'RUT:') === false, 'T15: el aviso a Luis dice «Documento: DNI 12.345.678»: ' . wp_strip_all_tags($luis15));

// Pasaporte de 3 caracteres, RUT inválido o tipo desconocido: vuelve con 'datos' y no registra nada.
foreach ([
	'pasaporte de 3 caracteres' => ['tipo_documento' => 'pasaporte', 'documento' => 'X12'],
	'RUT inválido'              => ['tipo_documento' => 'rut', 'documento' => '11.111.111-2'],
	'tipo desconocido'          => ['tipo_documento' => 'cedula', 'documento' => '12345678'],
	'sin número'                => ['tipo_documento' => 'dni', 'documento' => ''],
] as $caso => $campos) {
	$q = t15_crear($marca, $creadas);
	$rq = t15_aceptar($q, $campos);
	$nq = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d", $q->id));
	ok(strpos($rq['redirect'], 'respuesta=datos') !== false && at_cc_propuesta_por_id((int) $q->id)->status === 'sent' && $nq === 0, "T15: {$caso}: vuelve con «datos» y no registra nada: " . $rq['redirect']);
}

// Compatibilidad: el campo 'rut' de antes de la Task 15 se sigue tomando como RUT (con los datos del
// contrato que exige la Task 18).
$t15_viejo = t15_crear($marca, $creadas);
$r15v = t15_aceptar($t15_viejo, ['rut' => '11.111.111-1']);
$m15v = t15_meta((int) $t15_viejo->id);
ok(strpos($r15v['redirect'], 'respuesta=aceptada') !== false && ($m15v['tipo_documento'] ?? null) === 'rut' && ($m15v['documento'] ?? null) === '11.111.111-1' && ($m15v['rut'] ?? null) === '11.111.111-1', 'T15: el campo «rut» de antes se acepta como RUT: ' . $r15v['redirect']);
ok(strpos(t15_correo_luis($r15v['correos']), 'Documento: RUT 11.111.111-1') !== false, 'T15: el aviso a Luis dice «Documento: RUT 11.111.111-1»');

// ================= Task 18: los datos del contrato son obligatorios al aceptar en la página =================
// 27-sep, primer contrato real: el cliente aceptó sin dejar «Datos para tu contrato» (era un diálogo
// aparte y opcional) y hubo que pedirle la dirección y el tipo de cliente por privado para firmar.

// El diálogo de aceptar trae los radios obligatorios sin marcar, los campos de empresa ocultos y la
// dirección obligatoria; el de datos no se dibuja (la propuesta no está aceptada).
ok(strpos($h15, '<input type="radio" name="tipo" value="persona" data-at-cc-tipo required>') !== false && strpos($h15, '<input type="radio" name="tipo" value="empresa" data-at-cc-tipo required>') !== false, 'T18: el diálogo de aceptar pide «¿A nombre de quién va el contrato?» con dos radios obligatorios');
ok(preg_match('/name="tipo"[^>]*checked/', $h15) === 0, 'T18: ningún radio viene marcado por defecto');
ok(strpos($h15, '<div data-at-cc-campos-empresa hidden>') !== false && strpos($h15, 'name="razon_social" maxlength="200"') !== false && strpos($h15, 'name="rut_empresa"') !== false, 'T18: razón social y RUT de la empresa, ocultos hasta elegir empresa');
ok(preg_match('/name="(razon_social|rut_empresa)"[^>]*required/', $h15) === 0, 'T18: los campos de empresa no son obligatorios en el HTML (el script los exige solo cuando se ven)');
ok(strpos($h15, '<input type="text" id="at-cc-a-direccion" name="direccion" required maxlength="300"') !== false && strpos($h15, 'Dirección (calle, número, comuna y ciudad)') !== false, 'T18: la dirección es obligatoria');
ok(strpos($h15, 'id="at-cc-datos"') === false, 'T18: el diálogo de datos no se dibuja junto al de aceptar (comparten los nombres de campo)');
ok(strpos($h15, 'max-height:calc(100dvh - 32px)') !== false && strpos($h15, 'overflow-y:auto') !== false, 'T18: el diálogo se puede desplazar en un celular (max-height con dvh y overflow)');
ok(strpos($h15, 'var inicial = ""') !== false && strpos($h15, 'role="alert"') === false, 'T18: sin respuesta=datos el diálogo no se abre solo ni trae aviso');

// 29-sep (pedido de Luis): el enlace «Aceptar» del correo ya no abre el diálogo al cargar; primero se lee la
// propuesta. «Acepto la propuesta» pregunta «¿Ya revisaste toda la propuesta?» hasta que la presentación avisa
// (postMessage, validando su origen) que el cliente llegó a la última lámina.
$p29 = clone $t15_render;
$p29->gamma_iframe_url = 'https://render.ejemplo.cl:8443/p/abc/index.html';
$_GET['responder'] = 'aceptar';
ob_start();
at_cc_render_barra($p29);
$h29 = (string) ob_get_clean();
$_GET['responder'] = 'evaluar';
ob_start();
at_cc_render_barra($p29);
$h29e = (string) ob_get_clean();
unset($_GET['responder']);
ok(strpos($h29, 'var inicial = ""') !== false, '29-sep: con responder=aceptar el diálogo de aceptar NO se abre al cargar');
ok(strpos($h29, 'Revisa la propuesta hasta la última lámina y, si estás de acuerdo, acéptala aquí.') !== false && strpos($h15, 'Revisa la propuesta hasta la última lámina') === false, '29-sep: la guía de la barra sale solo al llegar por el enlace de aceptar');
ok(strpos($h29e, 'var inicial = "at-cc-evalua"') !== false, '29-sep: responder=evaluar sigue abriendo «La sigo evaluando»');
ok(strpos($h29, '<dialog class="at-cc-dlg" id="at-cc-previo">') !== false && strpos($h29, '¿Ya revisaste toda la propuesta?') !== false && strpos($h29, 'data-at-cc-seguir>Sí, estoy de acuerdo</button>') !== false && strpos($h29, 'data-cerrar>Ver la propuesta</button>') !== false, '29-sep: existe la confirmación previa con «Ver la propuesta» y «Sí, estoy de acuerdo»');
ok(strpos($h29, "if (id === 'at-cc-acepta' && !llegoAlFinal && document.getElementById('at-cc-previo')) { id = 'at-cc-previo'; }") !== false, '29-sep: el botón pasa por la confirmación mientras no llegue al final');
ok(strpos($h29, 'var origenDeck = "https:\/\/render.ejemplo.cl:8443"') !== false && strpos($h29, 'e.origin !== origenDeck') !== false && strpos($h29, "m.type !== 'at-deck-lamina'") !== false, '29-sep: solo se acepta el aviso de lámina que viene del origen de la presentación (con puerto)');
ok(strpos($h15, 'var origenDeck = ""') !== false, '29-sep: sin URL de presentación no se escucha ningún aviso');
$acepta29 = substr($h29, (int) strpos($h29, '<dialog class="at-cc-dlg" id="at-cc-acepta">'), (int) strpos($h29, '<dialog class="at-cc-dlg" id="at-cc-previo">') - (int) strpos($h29, '<dialog class="at-cc-dlg" id="at-cc-acepta">'));
ok(strpos($acepta29, 'data-cerrar>Seguir viendo la propuesta</button>') !== false && strpos($acepta29, 'data-cerrar>Volver</button>') === false, '29-sep: el diálogo de datos invita a seguir viendo la propuesta en vez de «Volver»');
ok(strpos($h29, 'Al confirmar registramos tu aceptación') !== false, '29-sep: el diálogo dice que confirmar registra la aceptación');

// Con respuesta=datos, el diálogo de aceptar se reabre solo y trae el aviso adentro (y en la barra).
$_GET['respuesta'] = 'datos';
ob_start();
at_cc_render_barra($t15_render);
$h18_datos = (string) ob_get_clean();
unset($_GET['respuesta']);
$texto_datos = esc_html(at_cc_mensaje_respuesta('datos')['texto']);
ok(strpos($h18_datos, 'var inicial = "at-cc-acepta"') !== false, 'T18: con respuesta=datos el diálogo de aceptar se abre solo');
$pos_dlg = strpos($h18_datos, '<dialog class="at-cc-dlg" id="at-cc-acepta">');
$pos_aviso = strpos($h18_datos, '<div class="at-cc-msg at-cc-msg--aviso" role="alert">' . $texto_datos . '</div>');
ok($pos_dlg !== false && $pos_aviso !== false && $pos_aviso > $pos_dlg && $pos_aviso < strpos($h18_datos, '</dialog>', $pos_dlg), 'T18: el aviso de «datos» va dentro del diálogo de aceptar');
ok(substr_count($h18_datos, $texto_datos) === 2, 'T18: el aviso también sigue en la barra');
// Revisión (27-sep): lo que el cliente envió se restaura solo en el rebote por 'datos' (sessionStorage de su pestaña,
// con clave por código); sin rebote, no. El comportamiento en el navegador se probó aparte (Chrome del panel).
$clave_js = "'at-cc-acepta-' + " . wp_json_encode((string) $t15_render->unique_link_id);
ok(strpos($h18_datos, $clave_js) !== false && strpos($h18_datos, "typeof guardado === 'object' && true)") !== false, 'T18: con respuesta=datos el script restaura lo enviado (clave por código de propuesta)');
ok(strpos($h15, "typeof guardado === 'object' && false)") !== false && strpos($h15, 'sessionStorage.setItem(claveAcepta') !== false, 'T18: sin rebote no restaura nada, pero guarda lo enviado al confirmar');
ok(substr_count($h15, '<fieldset class="at-cc-grupo"><legend>¿A nombre de quién va el contrato?</legend>') === 1, 'T18: las opciones de tipo van agrupadas con fieldset y legend');

/** Contrato de la propuesta, sus marcadores y la nota de aceptación. */
function t18_contrato(int $id): array {
	$c = at_cc_contrato_de_propuesta($id);
	return [$c, $c ? (json_decode((string) $c->placeholders, true) ?: []) : []];
}
function t18_nota(int $id): ?object {
	global $wpdb, $det;
	return $wpdb->get_row($wpdb->prepare("SELECT description, metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $id));
}

// Sin tipo, sin dirección, empresa sin razón social o con RUT inválido, tipo inventado o un formulario
// viejo (abierto antes del cambio, sin estos campos): vuelve con 'datos' y nada cambia.
foreach ([
	'sin tipo'                   => ['tipo' => null],
	'sin dirección'              => ['direccion' => '   '],
	'tipo inventado'             => ['tipo' => 'fundacion'],
	'empresa sin razón social'   => ['tipo' => 'empresa', 'razon_social' => '', 'rut_empresa' => '10.000.013-K'],
	'empresa con RUT inválido'   => ['tipo' => 'empresa', 'razon_social' => '[PRUEBA] Empresa SpA', 'rut_empresa' => '10.000.013-1'],
	'formulario viejo (T15)'     => ['tipo' => null, 'direccion' => null, 'tipo_documento' => 'rut', 'documento' => '11.111.111-1'],
	'formulario viejo (solo rut)' => ['tipo' => null, 'direccion' => null, 'tipo_documento' => null, 'documento' => null, 'rut' => '11.111.111-1'],
] as $caso => $campos) {
	$q = t15_crear($marca, $creadas);
	$rq = t15_aceptar($q, array_merge(['tipo_documento' => 'rut', 'documento' => '11.111.111-1'], $campos));
	$nq = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d", $q->id));
	ok(strpos($rq['redirect'], 'respuesta=datos') !== false && at_cc_propuesta_por_id((int) $q->id)->status === 'sent' && $nq === 0 && at_cc_contrato_de_propuesta((int) $q->id) === null && !$rq['correos'], "T18: {$caso}: vuelve con «datos», sin cambio de estado, sin nota, sin contrato ni correo: " . $rq['redirect'] . ' ' . $rq['stderr']);
}

// Revisión (27-sep): un formulario viejo (sin los datos del contrato) a una propuesta YA aceptada no queda en 'datos'
// sin salida: at_cc_registrar_respuesta() la resuelve como siempre («ya aceptada») y no crea nada nuevo.
$q_ya = t15_crear($marca, $creadas);
t15_aceptar($q_ya, ['tipo_documento' => 'rut', 'documento' => '11.111.111-1']);
$contratos_ya = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $q_ya->id));
$r_ya = t15_aceptar($q_ya, ['tipo' => null, 'direccion' => null, 'tipo_documento' => 'rut', 'documento' => '11.111.111-1']);
ok(strpos($r_ya['redirect'], 'respuesta=aceptada') !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $q_ya->id)) === $contratos_ya, 'T18: formulario viejo a una propuesta ya aceptada: vuelve como aceptada, sin contrato nuevo: ' . $r_ya['redirect']);

// Persona + dirección: el contrato queda a nombre de quien aceptó, con su documento y la dirección,
// listo para que AT lo revise (faltantes() vacío); la ficha operativa recibe la dirección y el documento.
$t18_persona = t15_crear($marca, $creadas);
$r18p = t15_aceptar($t18_persona, ['tipo_documento' => 'rut', 'documento' => '11.111.111-1', 'direccion' => '  Av. Siempre Viva 742, Providencia, Santiago  ']);
ok(strpos($r18p['redirect'], 'respuesta=aceptada') !== false && at_cc_propuesta_por_id((int) $t18_persona->id)->status === 'aceptada', 'T18: persona + dirección: aceptada: ' . $r18p['redirect'] . ' ' . $r18p['stderr']);
[$c18p, $ph18p] = t18_contrato((int) $t18_persona->id);
ok($c18p && ($ph18p['tipo_cliente'] ?? '') === 'persona' && ($ph18p['domicilio_cliente'] ?? '') === 'Av. Siempre Viva 742, Providencia, Santiago', 'T18: persona: el contrato queda con tipo_cliente persona y la dirección');
ok(($ph18p['razon_social_cliente'] ?? '') === 'Ana Prueba' && ($ph18p['rut_cliente'] ?? '') === '11.111.111-1' && ($ph18p['tipo_documento_cliente'] ?? '') === 'rut', 'T18: persona: razón social = nombre de quien aceptó y su documento');
ok($c18p && ContractService::faltantes($c18p) === [], 'T18: persona: faltantes() vacío: ' . ($c18p ? implode(', ', ContractService::faltantes($c18p)) : 'sin contrato'));
ok($c18p && ContractService::necesita_revision($c18p), 'T18: persona: sigue esperando la revisión de AT (los datos del cliente no la marcan)');
$f18p = $c18p ? $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $c18p->client_id)) : null;
ok($f18p && $f18p->billing_address === 'Av. Siempre Viva 742, Providencia, Santiago' && $f18p->tax_id === '11.111.111-1', 'T18: persona: la ficha operativa recibe billing_address y tax_id');
$n18p = t18_nota((int) $t18_persona->id);
$m18p = json_decode((string) ($n18p->metadata ?? ''), true) ?: [];
ok(($m18p['datos_contrato'] ?? null) === ['tipo' => 'persona', 'direccion' => 'Av. Siempre Viva 742, Providencia, Santiago'], 'T18: persona: la metadata de la nota de aceptación trae los datos del contrato: ' . wp_json_encode($m18p['datos_contrato'] ?? null, JSON_UNESCAPED_UNICODE));
ok($n18p && strpos((string) $n18p->description, 'Siempre Viva') === false && strpos((string) $n18p->description, 'persona') === false, 'T18: persona: la descripción (la puede ver el cliente) no lleva los datos del contrato: ' . str_replace("\n", ' | ', (string) ($n18p->description ?? '')));
$luis18p = t15_correo_luis($r18p['correos']);
ok(strpos($luis18p, 'Contrato a nombre de: persona natural (quien aceptó)') !== false && strpos($luis18p, 'Dirección: Av. Siempre Viva 742, Providencia, Santiago') !== false, 'T18: persona: el aviso a Luis trae a nombre de quién va y la dirección');
ok(strpos($luis18p, '⚠️') === false, 'T18: persona: el cierre no deja avisos: ' . wp_strip_all_tags($luis18p));

// Empresa: el contrato queda con la razón social y el RUT de la empresa; el representante es quien aceptó.
$t18_empresa = t15_crear($marca, $creadas);
$r18e = t15_aceptar($t18_empresa, ['tipo_documento' => 'dni', 'documento' => '12345678', 'tipo' => 'empresa', 'razon_social' => '[PRUEBA] Empresa <b>SpA</b>', 'rut_empresa' => '10000013k', 'direccion' => 'Calle Uno 1, Ñuñoa']);
ok(strpos($r18e['redirect'], 'respuesta=aceptada') !== false, 'T18: empresa: aceptada: ' . $r18e['redirect'] . ' ' . $r18e['stderr']);
[$c18e, $ph18e] = t18_contrato((int) $t18_empresa->id);
ok(($ph18e['tipo_cliente'] ?? '') === 'empresa' && ($ph18e['razon_social_cliente'] ?? '') === '[PRUEBA] Empresa SpA' && ($ph18e['rut_cliente'] ?? '') === '10.000.013-K' && ($ph18e['tipo_documento_cliente'] ?? '') === 'rut', 'T18: empresa: razón social y RUT (formateado) de la empresa: ' . wp_json_encode(array_intersect_key($ph18e, ['razon_social_cliente' => 1, 'rut_cliente' => 1]), JSON_UNESCAPED_UNICODE));
ok(($ph18e['representante_cliente_nombre'] ?? '') === 'Ana Prueba' && ($ph18e['representante_cliente_rut'] ?? '') === '12345678' && ($ph18e['tipo_documento_representante'] ?? '') === 'dni', 'T18: empresa: el representante es quien aceptó, con su documento');
ok(($ph18e['domicilio_cliente'] ?? '') === 'Calle Uno 1, Ñuñoa' && $c18e && ContractService::faltantes($c18e) === [], 'T18: empresa: con la dirección, faltantes() vacío: ' . ($c18e ? implode(', ', ContractService::faltantes($c18e)) : 'sin contrato'));
$f18e = $c18e ? $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $c18e->client_id)) : null;
ok($f18e && $f18e->billing_address === 'Calle Uno 1, Ñuñoa' && $f18e->tax_id === '10.000.013-K', 'T18: empresa: la ficha operativa recibe la dirección y el RUT de la empresa');
$n18e = t18_nota((int) $t18_empresa->id);
$m18e = json_decode((string) ($n18e->metadata ?? ''), true) ?: [];
// == y no ===: la columna metadata es JSON en MySQL y ordena las claves a su manera.
ok(($m18e['datos_contrato'] ?? null) == ['tipo' => 'empresa', 'direccion' => 'Calle Uno 1, Ñuñoa', 'razon_social' => '[PRUEBA] Empresa SpA', 'rut_empresa' => '10.000.013-K'], 'T18: empresa: la metadata trae tipo, dirección, razón social y RUT de la empresa: ' . wp_json_encode($m18e['datos_contrato'] ?? null, JSON_UNESCAPED_UNICODE));
ok($n18e && strpos((string) $n18e->description, 'Empresa SpA') === false && strpos((string) $n18e->description, '10.000.013-K') === false && strpos((string) $n18e->description, 'Calle Uno') === false, 'T18: empresa: la descripción no lleva razón social, RUT ni dirección');
ok(strpos(t15_correo_luis($r18e['correos']), 'Contrato a nombre de: empresa [PRUEBA] Empresa SpA, RUT 10.000.013-K') !== false, 'T18: empresa: el aviso a Luis trae la empresa y su RUT');

// Si aplicar los datos falla, el cierre sigue (cliente, contrato, aviso a Luis) y queda el aviso. Estas
// dos corren en este mismo proceso: el aviso a Luis no sale de verdad.
add_filter('pre_wp_mail', '__return_true');
$t18_falla = t15_crear($marca, $creadas);
$r18f = at_cc_registrar_respuesta($t18_falla, 'acepta', [
	'canal' => 'pagina', 'nombre' => 'Ana Prueba', 'tipo_documento' => 'rut', 'documento' => '11.111.111-1', 'rut' => '11.111.111-1',
	'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($t18_falla), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => false,
	'datos_contrato' => ['tipo_cliente' => 'fundacion', 'domicilio_cliente' => 'Calle Falla 1'],
]);
ok($r18f['ok'] && $r18f['estado'] === 'aceptada' && $r18f['contrato_id'] && $r18f['crm_id'], 'T18: datos que el servicio rechaza: el cierre igual queda (aceptada, cliente y contrato)');
ok(count(array_filter((array) $r18f['avisos'], function ($a) { return strpos($a, 'Los datos del contrato que dejó el cliente no se aplicaron') === 0; })) === 1, 'T18: datos que el servicio rechaza: queda un aviso claro: ' . implode(' | ', (array) $r18f['avisos']));

// Aceptación a mano (sin datos_contrato): el contrato nace como siempre, sin tipo ni dirección y sin avisos.
$t18_manual = t15_crear($marca, $creadas);
$r18m = at_cc_registrar_respuesta($t18_manual, 'acepta', [
	'canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Ana Prueba', 'tipo_documento' => 'rut', 'documento' => '11.111.111-1', 'rut' => '11.111.111-1',
	'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($t18_manual), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => false,
]);
[$c18m, $ph18m] = t18_contrato((int) $t18_manual->id);
ok($r18m['ok'] && empty($r18m['avisos']) && ($ph18m['tipo_cliente'] ?? '') === '' && ($ph18m['domicilio_cliente'] ?? '') === '', 'T18: aceptación a mano: sin datos del contrato, todo sigue igual (sin tipo ni dirección, sin avisos)');
$m18m = json_decode((string) (t18_nota((int) $t18_manual->id)->metadata ?? ''), true) ?: [];
ok(!array_key_exists('datos_contrato', $m18m), 'T18: aceptación a mano: la metadata no trae datos del contrato');

// Completar un cierre a medias (el contrato no quedó): el reintento aplica los datos guardados en la
// nota de la aceptación. Con el contrato ya creado y otra dirección (el cliente la corrigió), no la pisa.
@unlink(ContractService::storage_dir() . '/' . $c18e->contract_number . '.pdf');
$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE proposal_id = %d", (int) $t18_empresa->id));
$rc18 = at_cc_completar_cierre(at_cc_propuesta_por_id((int) $t18_empresa->id), false);
[$c18c, $ph18c] = t18_contrato((int) $t18_empresa->id);
ok($rc18['ok'] && empty($rc18['avisos']) && $c18c && ($ph18c['razon_social_cliente'] ?? '') === '[PRUEBA] Empresa SpA' && ($ph18c['domicilio_cliente'] ?? '') === 'Calle Uno 1, Ñuñoa', 'T18: completar el cierre: el contrato nuevo recibe los datos de la aceptación: ' . implode(' | ', (array) $rc18['avisos']));
ContractService::actualizar_datos_cliente((int) $c18c->id, ['domicilio_cliente' => 'Calle Corregida 2, Ñuñoa']);
$rc18b = at_cc_completar_cierre(at_cc_propuesta_por_id((int) $t18_empresa->id), false);
[, $ph18d] = t18_contrato((int) $t18_empresa->id);
ok($rc18b['ok'] && empty($rc18b['avisos']) && ($ph18d['domicilio_cliente'] ?? '') === 'Calle Corregida 2, Ñuñoa', 'T18: completar de nuevo con el contrato ya creado: no pisa la dirección corregida');

// Limpieza Task 15: contratos y PDF, Seguimiento, cliente CRM, propuestas y los límites de intentos.
$ids = implode(',', array_map('intval', $creadas));
foreach ($wpdb->get_results("SELECT id, contract_number FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})") as $cx) {
	@unlink(ContractService::storage_dir() . '/' . $cx->contract_number . '.pdf');
}
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$codigos = $wpdb->get_col("SELECT unique_link_id FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$wpdb->query("DELETE FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})");
$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
if ($crm_ids) {
	$lista = implode(',', array_map('intval', $crm_ids));
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN ({$lista})");
}
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
foreach ($codigos as $cod) {
	delete_transient('at_cc_lim_cod_' . md5('responder|' . $cod));
}
delete_transient('at_cc_lim_' . md5('responder|198.51.100.77'));

fin();
