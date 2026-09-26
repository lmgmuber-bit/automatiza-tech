<?php
/**
 * Cierre de cliente: reglas sin WordPress. Pruebas: php tests/cierre/puras-test.php
 * Spec: Docs/superpowers/specs/2026-09-25-cierre-de-cliente-design.md
 */

/** Salida que elige el cliente => estado en que queda la propuesta. */
function at_cc_salidas(): array {
	return ['acepta' => 'aceptada', 'evalua' => 'evaluando', 'rechaza' => 'rechazada'];
}

/** Tipos de Seguimiento que son solo para Luis: nunca deben verse en una página pública
 *  (línea de tiempo del cliente ni la del prospecto, en crm-ai-completo.php).
 *  'pedido_respuesta' (Ronda 2, hallazgo 1): antes se anotaba con el tipo público 'propuesta_enviada',
 *  así que la línea de tiempo pública del prospecto se lo mostraba (incluido «Falló el correo para
 *  pedir la respuesta») y, peor, cualquier fila 'propuesta_enviada' ya existente hacía que esa vista
 *  dejara de agregar la tarjeta automática «Propuesta Creada» con el PDF (crm-ai-completo.php,
 *  render_public_prospect_timeline()). */
// 'mensaje_whatsapp' (Task 10b, ajuste del controlador 26-sep): el mensaje de texto que el cliente le
// escribe al bot de WhatsApp sobre su propuesta. Es una nota interna, no una respuesta real (no cambia
// el estado), así que no puede compartir el tipo público 'respuesta_cliente'.
function at_cc_tipos_internos(): array {
	return ['cierre_incompleto', 'aviso_operativo', 'pedido_respuesta', 'mensaje_whatsapp'];
}

/** Transiciones de una respuesta. A mano (Luis) también se acepta una propuesta en pending, lista o
 *  archivada (Task 14). Desde 'archivada' el cliente no tiene ninguna transición: ni por la página ni
 *  por WhatsApp. */
function at_cc_transicion_respuesta_valida(string $desde, string $hacia, bool $manual = false): bool {
	$permitidas = [
		'sent'      => ['evaluando', 'rechazada', 'aceptada'],
		'evaluando' => ['rechazada', 'aceptada'],
		'rechazada' => ['aceptada'],
	];
	if ($manual && $hacia === 'aceptada' && in_array($desde, ['pending', 'lista', 'archivada'], true)) {
		return true;
	}
	return in_array($hacia, $permitidas[$desde] ?? [], true);
}

/** Task 14 (aprobada por Luis el 26-sep): estados desde los que Luis puede archivar una propuesta
 *  vieja o reemplazada. No se archiva lo aceptado ('aceptada', 'contracted'), lo que n8n está
 *  trabajando ('ajustando', 'generando') ni lo ya archivado. */
function at_cc_puede_archivar(string $status): bool {
	return in_array($status, ['sent', 'evaluando', 'rechazada', 'pending', 'lista', 'borrador', 'draft', 'error'], true);
}

/** Estados en que tiene sentido pedirle la respuesta al cliente. */
function at_cc_puede_pedir_respuesta(string $status): bool {
	return in_array($status, ['sent', 'evaluando', 'rechazada'], true);
}

/** Primer monto en pesos de una etiqueta ('$2.000.000 en 2 pagos' => 2000000); null si no hay. */
function at_cc_monto_de_etiqueta(string $etiqueta): ?int {
	if (!preg_match('/\$\s*(\d{1,3}(?:\.\d{3})+|\d+)/', $etiqueta, $m)) {
		return null;
	}
	return (int) str_replace('.', '', $m[1]);
}

/** La etiqueta describe un cobro mensual. */
function at_cc_es_mensual(string $etiqueta): bool {
	return (bool) preg_match('/al\s+mes|mensual|por\s+mes|\/\s*mes/iu', $etiqueta);
}

/** Suma de los montos únicos (no mensuales) que se pueden leer; null si ninguno se puede leer. */
function at_cc_total_unico(array $filas): ?int {
	$total = 0;
	$hay = false;
	foreach ($filas as $f) {
		$e = (string) ($f['price_label'] ?? '');
		if (at_cc_es_mensual($e)) {
			continue;
		}
		$n = at_cc_monto_de_etiqueta($e);
		if ($n !== null) {
			$total += $n;
			$hay = true;
		}
	}
	return $hay ? $total : null;
}

/** Anticipo: 50 % de lo único aceptado, en pesos enteros; null si no se puede calcular. */
function at_cc_anticipo(array $filas): ?int {
	$t = at_cc_total_unico($filas);
	return $t === null ? null : (int) round($t / 2);
}

function at_cc_formato_clp(int $n): string {
	return '$' . number_format($n, 0, ',', '.');
}

function at_cc_email_normalizado(string $e): string {
	return strtolower(trim($e));
}

function at_cc_rut_limpio(string $rut): string {
	return strtoupper((string) preg_replace('/[^0-9kK]/', '', $rut));
}

/** RUT chileno con dígito verificador correcto. */
function at_cc_rut_valido(string $rut): bool {
	$r = at_cc_rut_limpio($rut);
	if (strlen($r) < 2) {
		return false;
	}
	$cuerpo = substr($r, 0, -1);
	$dv = substr($r, -1);
	if (!ctype_digit($cuerpo)) {
		return false;
	}
	$suma = 0;
	$mult = 2;
	for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
		$suma += (int) $cuerpo[$i] * $mult;
		$mult = $mult === 7 ? 2 : $mult + 1;
	}
	$resto = 11 - ($suma % 11);
	$esperado = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string) $resto);
	return $dv === $esperado;
}

function at_cc_rut_formato(string $rut): string {
	$r = at_cc_rut_limpio($rut);
	if (strlen($r) < 2) {
		return '';
	}
	return number_format((int) substr($r, 0, -1), 0, ',', '.') . '-' . substr($r, -1);
}

/** Teléfono chileno en dígitos con 56 adelante ('+56 9 1234 5678' y '912345678' => '56912345678'). */
function at_cc_telefono_normalizado(string $t): string {
	$d = (string) preg_replace('/\D/', '', $t);
	if (strlen($d) === 9 && $d[0] === '9') {
		return '56' . $d;
	}
	return $d;
}

/** El contenido de una propuesta como arreglo: JSON, JSON con barras (propuestas viejas) o JSON dentro de texto. */
function at_cc_json_de_payload(string $s): ?array {
	foreach ([$s, stripslashes($s)] as $intento) {
		$d = json_decode($intento, true);
		if (is_array($d)) {
			return $d;
		}
	}
	if (preg_match('/\{.*\}/s', $s, $m)) {
		foreach ([$m[0], stripslashes($m[0])] as $intento) {
			$d = json_decode($intento, true);
			if (is_array($d)) {
				return $d;
			}
		}
	}
	return null;
}

/** Filas de precio del contenido de una propuesta; [] si no hay. */
function at_cc_filas_de_payload(string $s): array {
	$d = at_cc_json_de_payload($s);
	if (!$d || !is_array($d['pricing_rows'] ?? null)) {
		return [];
	}
	return array_values(array_filter($d['pricing_rows'], 'is_array'));
}

/** Filas elegidas por su índice, en el orden de la propuesta, sin repetir ni aceptar filas sin servicio. */
function at_cc_filas_aceptadas(array $filas, array $indices): array {
	$r = [];
	foreach ($indices as $i) {
		if (!is_numeric($i)) {
			continue;
		}
		$i = (int) $i;
		if (!isset($filas[$i]) || !is_array($filas[$i]) || trim((string) ($filas[$i]['service'] ?? '')) === '') {
			continue;
		}
		$r[$i] = $filas[$i];
	}
	ksort($r);
	return array_values($r);
}

function at_cc_huella(string $contenido): string {
	return hash('sha256', $contenido);
}

/** '2026-09-25 ...' => '25 de septiembre de 2026'; '' si no es una fecha real. */
function at_cc_fecha_larga(string $fecha): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	return (int) $m[3] . ' de ' . $meses[(int) $m[2] - 1] . ' de ' . $m[1];
}

/** Fecha que declara Luis ('AAAA-MM-DD', no futura) a 'AAAA-MM-DD 12:00:00'; '' si no sirve. */
function at_cc_fecha_declarada(string $ymd, string $hoy): string {
	$ymd = trim($ymd);
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $ymd > $hoy) {
		return '';
	}
	return $ymd . ' 12:00:00';
}

/** Por dónde aceptó el cliente cuando Luis lo registra a mano. */
function at_cc_canales_manuales(): array {
	return ['whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'llamada' => 'Llamada', 'reunion' => 'Reunión', 'otro' => 'Otro medio'];
}

/** Cómo aceptó, en palabras para el contrato y los avisos. */
function at_cc_canal_texto(array $d): string {
	$canal = (string) ($d['canal'] ?? '');
	if ($canal === 'whatsapp') {
		return 'con un botón en WhatsApp';
	}
	if ($canal === 'manual') {
		$frases = ['whatsapp' => 'por WhatsApp', 'correo' => 'por correo', 'llamada' => 'en una llamada', 'reunion' => 'en una reunión', 'otro' => 'por otro medio'];
		return ($frases[(string) ($d['canal_manual'] ?? '')] ?? 'por otro medio') . ', registrada por AutomatizaTech';
	}
	return 'en la página de la propuesta';
}

function at_cc_url_respuesta(string $base, string $codigo, string $responder = ''): string {
	$u = rtrim($base, '/') . '/ver-presentacion.php?id=' . rawurlencode($codigo);
	return $responder !== '' ? $u . '&responder=' . rawurlencode($responder) : $u;
}

/** Bloque del correo que pide aceptar la propuesta. El botón abre la página: no acepta por sí solo.
 *  Con $url_evaluar vacío no se dibuja el enlace de «¿Tienes dudas…?» (propuesta rechazada). */
function at_cc_bloque_aceptar_html(string $url_aceptar, string $url_evaluar): string {
	$a = htmlspecialchars($url_aceptar, ENT_QUOTES, 'UTF-8');
	$e = htmlspecialchars($url_evaluar, ENT_QUOTES, 'UTF-8');
	return '<div style="background:#ecfdf5;border:1px solid #10b981;border-radius:8px;padding:20px;margin:24px 0;text-align:center">'
		. '<p style="margin:0 0 14px 0;color:#065f46;font-size:15px">Si estás de acuerdo con la propuesta, acéptala aquí. Con tu aceptación te enviamos el contrato y los primeros pasos para partir.</p>'
		. '<a href="' . $a . '" style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 32px;border-radius:30px;font-size:16px">✅ Aceptar la propuesta</a>'
		. ($url_evaluar !== '' ? '<p style="margin:14px 0 0 0;font-size:13px"><a href="' . $e . '" style="color:#047857">¿Tienes dudas o necesitas más tiempo? Cuéntanos aquí</a></p>' : '')
		. '</div>';
}

/** Mensaje que Luis manda desde su WhatsApp con el enlace para aceptar. */
function at_cc_texto_whatsapp(string $nombre, string $empresa, string $url): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	$para = trim($empresa) !== '' ? ' para ' . trim($empresa) : '';
	return $saludo . ', te escribe Luis de AutomatizaTech. Te enviamos la propuesta' . $para . '. Puedes revisarla y, si estás de acuerdo, aceptarla aquí: ' . $url
		. "\nSi tienes dudas o necesitas más tiempo, respóndeme por aquí.";
}

function at_cc_url_wa_me(string $telefono, string $texto): string {
	$n = at_cc_telefono_normalizado($telefono);
	return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode($texto);
}

/** Filas de precio como lista del contrato: '- **Servicio**: etiqueta'. */
function at_cc_servicios_markdown(array $filas): string {
	$l = [];
	foreach ($filas as $f) {
		$s = trim((string) ($f['service'] ?? ''));
		if ($s === '') {
			continue;
		}
		$p = trim((string) ($f['price_label'] ?? ''));
		$l[] = '- **' . str_replace('*', '', $s) . '**' . ($p !== '' ? ': ' . $p : '');
	}
	return implode("\n", $l);
}

/** Marcadores que puede llenar el contrato de servicios con los datos de la propuesta. */
function at_cc_claves_contrato_servicios(): array {
	return [
		'razon_social_cliente', 'rut_cliente', 'representante_cliente_nombre', 'representante_cliente_rut',
		'email_cliente', 'telefono_cliente', 'domicilio_cliente', 'propuesta_codigo', 'fecha_propuesta',
		'fecha_aceptacion', 'canal_aceptacion', 'nombre_proyecto', 'servicios_contratados', 'alcance',
		'entregables', 'plazo', 'monto_total', 'forma_pago', 'fases_siguientes', 'garantia_meses_servicio',
		'tipo_cliente', 'aceptante_nombre',
	];
}

/**
 * Marcadores del contrato de servicios. Los que quedan vacíos no se incluyen: el PDF los muestra
 * como '_______' para llenarlos al revisar.
 */
function at_cc_marcadores_servicios(array $d): array {
	$filas = (array) ($d['filas_aceptadas'] ?? []);
	$total = at_cc_total_unico($filas);
	$mensuales = [];
	foreach ($filas as $f) {
		if (at_cc_es_mensual((string) ($f['price_label'] ?? ''))) {
			$mensuales[] = trim((string) ($f['service'] ?? '')) . ': ' . trim((string) ($f['price_label'] ?? ''));
		}
	}
	if ($total !== null) {
		$primero = (int) round($total / 2);
		$forma = '50 % al firmar este contrato (' . at_cc_formato_clp($primero) . ') y 50 % a la entrega (' . at_cc_formato_clp($total - $primero) . ').';
	} else {
		$forma = 'Según lo indicado para cada servicio contratado.';
	}
	if ($mensuales) {
		$forma .= ' Los servicios mensuales (' . implode('; ', $mensuales) . ') se pagan mes a mes desde que parten.';
	}
	$empresa = trim((string) ($d['empresa'] ?? ''));
	$repr = trim((string) ($d['representante'] ?? ''));
	$siguientes = at_cc_servicios_markdown((array) ($d['filas_siguientes'] ?? []));
	// Opcional: 'persona' (a su nombre) o 'empresa'. Sin tipo (o desconocido) queda sin elegir.
	$tipo = (string) ($d['tipo_cliente'] ?? '');
	$ph = [
		'tipo_cliente'                 => in_array($tipo, ['persona', 'empresa'], true) ? $tipo : '',
		'razon_social_cliente'         => $empresa !== '' ? $empresa : $repr,
		'rut_cliente'                  => (string) ($d['rut_cliente'] ?? ''),
		// Inmutable: nombre de quien aceptó, escrito una sola vez aquí y nunca en campos_revision(),
		// para que ContractService sepa a quién pertenece el contrato aunque representante_cliente_nombre
		// se borre después en la revisión (ese campo dice «solo si es empresa» y es natural borrarlo).
		'aceptante_nombre'             => $repr,
		'representante_cliente_nombre' => $repr,
		'representante_cliente_rut'    => (string) ($d['rut_representante'] ?? ''),
		'email_cliente'                => (string) ($d['email'] ?? ''),
		'telefono_cliente'             => (string) ($d['telefono'] ?? ''),
		'domicilio_cliente'            => (string) ($d['domicilio'] ?? ''),
		'propuesta_codigo'             => (string) ($d['codigo_propuesta'] ?? ''),
		'fecha_propuesta'              => (string) ($d['fecha_propuesta'] ?? ''),
		'fecha_aceptacion'             => (string) ($d['fecha_aceptacion'] ?? ''),
		'canal_aceptacion'             => (string) ($d['canal_aceptacion'] ?? ''),
		'nombre_proyecto'              => $empresa !== '' ? $empresa : $repr,
		'servicios_contratados'        => at_cc_servicios_markdown($filas),
		'alcance'                      => (string) ($d['alcance'] ?? ''),
		'entregables'                  => (string) ($d['entregables'] ?? ''),
		'plazo'                        => (string) ($d['plazo'] ?? ''),
		'monto_total'                  => $total !== null ? at_cc_formato_clp($total) : '',
		'forma_pago'                   => $forma,
		'fases_siguientes'             => $siguientes !== '' ? $siguientes : 'La propuesta no tiene fases siguientes.',
		'garantia_meses_servicio'      => '3',
	];
	$r = [];
	foreach ($ph as $k => $v) {
		$v = trim($v);
		if ($v !== '') {
			$r[$k] = $v;
		}
	}
	return $r;
}

/** Mensaje que ve el cliente después de responder en la página. */
function at_cc_mensaje_respuesta(string $clave): ?array {
	$m = [
		'aceptada'  => ['ok', '¡Gracias! Recibimos tu aceptación. Te enviamos por correo los primeros pasos, y el contrato llega en un correo aparte para firmarlo.'],
		'evaluando' => ['ok', 'Gracias por contarnos. Te escribimos pronto para resolver tus dudas.'],
		'rechazada' => ['ok', 'Gracias por tu respuesta. Si algo cambia, aquí estamos.'],
		'recibida'  => ['ok', 'Recibimos tu respuesta. Gracias.'],
		'datos'     => ['aviso', 'Revisa tu nombre y tu RUT, marca lo que aceptas y la casilla «Acepto la propuesta».'],
		'datos_ok'        => ['ok', '¡Listo! Con estos datos preparamos tu contrato.'],
		'datos_recibidos' => ['ok', 'Recibimos tus datos. Luis los revisa junto con tu contrato.'],
		'datos_contrato'  => ['aviso', 'Revisa los datos del contrato: la dirección y, si es una empresa, su razón social y un RUT válido.'],
		'vencida'   => ['aviso', 'La página estuvo abierta mucho rato. Recárgala e inténtalo de nuevo.'],
		'limite'    => ['aviso', 'Recibimos muchos intentos desde tu conexión. Espera un rato y vuelve a intentarlo.'],
		'error'     => ['error', 'No pudimos registrar tu respuesta. Escríbenos por WhatsApp y lo vemos.'],
	];
	return isset($m[$clave]) ? ['tipo' => $m[$clave][0], 'texto' => $m[$clave][1]] : null;
}

function at_cc_mimes_evidencia(): array {
	return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

/** '' si el archivo subido es una imagen aceptada de hasta 5 MB; si no, el motivo. El tipo se mira en el contenido. */
function at_cc_error_evidencia(array $f, int $max = 5242880): string {
	if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return 'no se pudo subir';
	}
	$tam = (int) ($f['size'] ?? 0);
	if ($tam <= 0 || $tam > $max) {
		return 'pesa más de 5 MB';
	}
	$info = @getimagesize((string) ($f['tmp_name'] ?? ''));
	if (!$info || !isset(at_cc_mimes_evidencia()[$info['mime'] ?? ''])) {
		return 'no es una imagen JPG, PNG o WEBP';
	}
	return '';
}

/** Un campo de $_FILES (simple o múltiple) como lista de archivos, sin los vacíos. */
function at_cc_archivos_normalizados(array $campo): array {
	if (!isset($campo['name'])) {
		return [];
	}
	if (!is_array($campo['name'])) {
		return (int) ($campo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$campo];
	}
	$r = [];
	foreach ($campo['name'] as $i => $nombre) {
		$error = (int) ($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE);
		if ($error === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$r[] = [
			'name'     => (string) $nombre,
			'type'     => (string) ($campo['type'][$i] ?? ''),
			'tmp_name' => (string) ($campo['tmp_name'][$i] ?? ''),
			'error'    => $error,
			'size'     => (int) ($campo['size'][$i] ?? 0),
		];
	}
	return $r;
}

function at_cc_banco_completo(array $b): bool {
	foreach (['banco', 'tipo', 'numero', 'titular', 'rut'] as $k) {
		if (trim((string) ($b[$k] ?? '')) === '') {
			return false;
		}
	}
	return true;
}

/**
 * Correo de bienvenida con la lista de arranque.
 * $v: nombre, empresa, anticipo (?int), banco (array), correo_pago, whatsapp, url_portal, logo, con_propuesta (bool).
 */
function at_cc_bienvenida_html(array $v): string {
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$empresa = trim((string) ($v['empresa'] ?? ''));
	$pasos = [];
	if (!empty($v['con_propuesta'])) {
		$pasos[] = ['Tu contrato', 'Te llega en un correo aparte para que lo firmes en línea. Nosotros lo revisamos y lo firmamos primero.'];
		$anticipo = $v['anticipo'] ?? null;
		$pago = $anticipo !== null
			? 'El primer pago es de <strong>' . $h(at_cc_formato_clp((int) $anticipo)) . '</strong>, el 50 % de lo que aceptaste.'
			: 'El monto del primer pago va en tu contrato.';
		$banco = (array) ($v['banco'] ?? []);
		if (at_cc_banco_completo($banco)) {
			$pago .= '<br>Transferencia a <strong>' . $h($banco['banco']) . '</strong>, ' . $h($banco['tipo']) . ' N° <strong>' . $h($banco['numero']) . '</strong>, a nombre de ' . $h($banco['titular']) . ', RUT ' . $h($banco['rut']) . '.';
		} else {
			$pago .= '<br>Te enviamos los datos para la transferencia por separado.';
		}
		$correo_pago = trim((string) ($v['correo_pago'] ?? ''));
		$pago .= $correo_pago !== ''
			? '<br>Cuando transfieras, avísanos a ' . $h($correo_pago) . ' y te enviamos la boleta o factura.'
			: '<br>Cuando transfieras, avísanos respondiendo este correo y te enviamos la boleta o factura.';
		$pasos[] = ['El anticipo', $pago];
	}
	$wa = at_cc_telefono_normalizado((string) ($v['whatsapp'] ?? ''));
	$pasos[] = ['Tu marca y tus accesos', 'Responde este correo con tu logo, tus colores, los textos de tu empresa y los accesos que tengas (dominio, hosting, redes sociales)'
		. ($wa !== '' ? ', o envíanoslos por <a href="https://wa.me/' . $h($wa) . '" style="color:#1e40af">WhatsApp</a>' : '') . '.'];
	$pasos[] = ['La reunión de inicio', 'Te vamos a contactar para coordinar la reunión de inicio, donde te damos la bienvenida oficial y repasamos juntos los pasos a seguir y los ítems de trabajo.'];
	$lista = '';
	foreach ($pasos as $i => $p) {
		$lista .= '<tr><td style="vertical-align:top;padding:10px 12px 10px 0;font-weight:bold;color:#1e40af;font-size:18px">' . ($i + 1) . '</td>'
			. '<td style="padding:10px 0"><strong>' . $h($p[0]) . '</strong><br>' . $p[1] . '</td></tr>';
	}
	$portal = trim((string) ($v['url_portal'] ?? ''));
	$logo = trim((string) ($v['logo'] ?? ''));
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;color:#ffffff;text-align:center;padding:28px 20px">'
		. ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:70px"><br>' : '')
		. '<h1 style="margin:12px 0 0;font-size:22px">¡Te damos la bienvenida a AutomatizaTech!</h1></div>'
		. '<div style="padding:28px 26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h($nombre !== '' ? $nombre : 'cliente') . '</strong>,</p>'
		. '<p>Gracias por confiar en nosotros' . ($empresa !== '' ? ' para <strong>' . $h($empresa) . '</strong>' : '') . '. Estos son tus primeros pasos:</p>'
		. '<table role="presentation" style="width:100%;border-collapse:collapse">' . $lista . '</table>'
		. ($portal !== '' ? '<p style="text-align:center;margin:26px 0"><a href="' . $h($portal) . '" style="background:#059669;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:24px;font-weight:bold">Ver mi portal</a></p>'
			. '<p style="font-size:13px;color:#555">En tu portal vas a ver el avance de tu proyecto y la historia de lo que hemos hecho juntos.</p>' : '')
		. (!empty($v['con_propuesta'])
			? '<p>Para preparar tu contrato necesitamos saber a nombre de quién va (tú o tu empresa), el RUT y la dirección. Si ya los completaste en la página de la propuesta, no tienes que hacer nada; si no, respóndenos este correo con esos datos.</p>'
			: '')
		. '<p>Cualquier duda, responde este correo.</p><p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}

/** Correo corto que pide la respuesta, con el bloque de aceptar. */
function at_cc_pedido_respuesta_html(string $nombre, string $empresa, string $bloque, string $logo, string $url_ver): string {
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;text-align:center;padding:24px 20px">' . ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:64px">' : '') . '</div>'
		. '<div style="padding:26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h(trim($nombre) !== '' ? trim($nombre) : 'cliente') . '</strong>,</p>'
		. '<p>Te escribimos por la propuesta que te enviamos' . (trim($empresa) !== '' ? ' para <strong>' . $h(trim($empresa)) . '</strong>' : '')
		. '. Puedes volver a verla <a href="' . $h($url_ver) . '" style="color:#1e40af">aquí</a>.</p>'
		. $bloque
		. '<p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}
