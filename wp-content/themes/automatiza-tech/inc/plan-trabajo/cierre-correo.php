<?php
/**
 * Estado del proyecto, Etapa 3 (spec §8): correo de cierre de hito (prueba a Luis o al cliente) y WhatsApp de Luis.
 * Nada sale sin su clic (D5). Al cliente solo con el cierre publicado y la presentación rehecha después de publicarlo;
 * el envío se reclama con una escritura condicional (at_av_guardar_si_igual) para no mandarlo dos veces.
 *
 * El estado de envío vive en el propio cierre (spec §3): correo {enviado_at, prueba_at, mensaje, enviando, a} y
 * whatsapp {abierto_at}. Se lee siempre con at_av_envio_de_cierre().
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_AV_ENVIO_VENCE = 600; // segundos: un reclamo más viejo se considera caído y se puede volver a enviar
const AT_AV_MENSAJE_MAX = 2000; // caracteres del mensaje de Luis en el correo

/** Índice del cierre en el avance, o -1. */
function at_av_indice_cierre(array $avance, string $id): int {
	foreach ((array) ($avance['cierres'] ?? []) as $k => $c) {
		if (is_array($c) && ($c['id'] ?? '') === $id) {
			return (int) $k;
		}
	}
	return -1;
}

/**
 * Anota el envío de un cierre sin pisar lo que otro proceso haya escrito: mezcla $correo y $whatsapp sobre lo que el
 * cierre ya tiene y guarda con at_av_guardar_si_igual (reintenta 3 veces si el avance cambió entretanto). true si
 * quedó anotado (o ya estaba igual); false si el plan o el cierre no existen, o si no se logró.
 */
function at_av_anotar_envio(int $plan_id, string $cierre_id, array $correo, array $whatsapp = []): bool {
	for ($i = 0; $i < 3; $i++) {
		$f = at_pt_plan($plan_id);
		if (!$f) {
			return false;
		}
		$antes = (string) $f->avance; // tal cual salió de la base: es la condición del guardado
		$av = at_av_leer($f);
		$k = at_av_indice_cierre($av, $cierre_id);
		if ($k < 0) {
			return false;
		}
		$actual = at_av_envio_de_cierre($av['cierres'][$k]);
		$nuevo = ['correo' => array_merge($actual['correo'], $correo), 'whatsapp' => array_merge($actual['whatsapp'], $whatsapp)];
		if ($nuevo === $actual && is_array($av['cierres'][$k]['correo'] ?? null) && is_array($av['cierres'][$k]['whatsapp'] ?? null)) {
			return true; // ya está así: guardar lo mismo no cambia la fila y no es un fallo
		}
		$av['cierres'][$k]['correo'] = $nuevo['correo'];
		$av['cierres'][$k]['whatsapp'] = $nuevo['whatsapp'];
		if (at_av_guardar_si_igual($plan_id, $antes, $av)) {
			return true;
		}
	}
	return false;
}

/** Datos comunes del correo y del WhatsApp con la fila y el avance ya leídos: [fila, avance, cierre, resumen, datos del cliente, url del estado] o el motivo. */
function at_av_preparar_envio_de(object $f, array $av, string $cierre_id): array|string {
	$c = at_av_cierre_de_avance($av, $cierre_id);
	if ($c === null) {
		return 'sin_cierre';
	}
	if (($c['estado'] ?? '') !== 'publicado') {
		return 'no_publicado';
	}
	return [$f, $av, $c, at_av_resumen_cierre(at_pt_payload($f), $av, $cierre_id), at_pt_datos_agenda((int) $f->id), at_pt_url_ver_estado(home_url(), (string) $f->codigo)];
}

function at_av_preparar_envio(int $plan_id, string $cierre_id): array|string {
	$f = at_pt_plan($plan_id);
	return $f ? at_av_preparar_envio_de($f, at_av_leer($f), $cierre_id) : 'sin_plan';
}

/** La presentación «Estado del proyecto» está lista y se rehízo después de publicar el cierre (Review Focus 2). */
function at_av_render_listo_para(array $avance, array $cierre): bool {
	$render = (array) ($avance['render'] ?? []);
	return ($render['estado'] ?? '') === 'listo' && ($render['at'] ?? '') !== '' && (string) $render['at'] >= (string) ($cierre['publicado_at'] ?? '');
}

/**
 * D8 completo: '' si nada de lo que llega al correo apunta al servidor interno (Easypanel); si no, dónde está (las URL tal
 * cual, o el nombre del lugar: «el mensaje», «Después»…). Mira las URL de las evidencias y del estado, el mensaje de Luis y
 * todos los textos del cierre que el correo dibuja.
 */
function at_av_hallar_interno(array $r, string $url_estado, string $proyecto, string $mensaje): string {
	$internas = at_av_urls_internas(array_merge((array) ($r['urls'] ?? []), [$url_estado]));
	if ($internas) {
		return implode(', ', $internas);
	}
	$cifras = [];
	foreach ((array) ($r['cifras'] ?? []) as $x) {
		$cifras = array_merge($cifras, [$x['titulo'] ?? '', $x['valor'] ?? '', $x['unidad'] ?? '', $x['fuente'] ?? '']);
	}
	$sigue = [];
	foreach ((array) ($r['sigue'] ?? []) as $x) {
		$sigue = array_merge($sigue, [$x['nombre'] ?? '', $x['cuando'] ?? '']);
	}
	$lugares = [
		'el mensaje' => [$mensaje], 'el proyecto' => [$proyecto], 'el título' => [$r['titulo'] ?? ''], 'la frase del plan' => [$r['frase'] ?? ''],
		'Antes' => (array) ($r['antes'] ?? []), 'Durante' => (array) ($r['durante'] ?? []),
		'Después' => array_merge((array) ($r['despues'] ?? []), [$r['linea_despues'] ?? '']),
		'las cifras' => $cifras, 'Lo que sigue' => $sigue, 'Lo que necesitamos de ti' => (array) ($r['necesitamos'] ?? []),
		'los videos' => array_column((array) ($r['videos'] ?? []), 'titulo'),
	];
	$donde = [];
	foreach ($lugares as $lugar => $textos) {
		foreach ($textos as $t) {
			if (is_string($t) && stripos($t, 'easypanel') !== false) {
				$donde[] = $lugar;
				break;
			}
		}
	}
	return $donde ? 'texto con «easypanel» en ' . implode(', ', $donde) : '';
}

/**
 * Todo lo que el correo usa, calculado de la fila y el avance dados, con las comprobaciones previas al envío (cierre
 * publicado, D8, destinatario y, para el cliente, presentación lista). ['motivo' => '', 'detalle' => '', 'ctx' => [...]]
 * o, si no se puede, el motivo y el detalle sin 'ctx'.
 */
function at_av_contexto_envio(object $f, array $av, string $cierre_id, bool $prueba, string $mensaje): array {
	$p = at_av_preparar_envio_de($f, $av, $cierre_id);
	if (is_string($p)) {
		return ['motivo' => $p, 'detalle' => ''];
	}
	[$f, $av, $c, $r, $d, $url_estado] = $p;
	$pl = at_pt_payload($f);
	$proyecto = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : (string) ($d['company_name'] ?? '');
	$interno = at_av_hallar_interno($r, $url_estado, $proyecto, $mensaje);
	if ($interno !== '') {
		return ['motivo' => 'interno', 'detalle' => $interno]; // D8: ningún correo sale con algo del servidor interno
	}
	$para = $prueba ? (function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : '') : (string) ($d['client_email'] ?? '');
	if ($para === '' || !is_email($para)) {
		return ['motivo' => 'sin_correo', 'detalle' => ''];
	}
	if (!$prueba && !at_av_render_listo_para($av, $c)) {
		return ['motivo' => 'render_no_listo', 'detalle' => ''];
	}
	return ['motivo' => '', 'detalle' => '', 'ctx' => compact('f', 'av', 'c', 'r', 'd', 'url_estado', 'proyecto', 'para')];
}

/**
 * Envía el cierre: $destino 'prueba' (a Luis, con «[Prueba]» en el asunto) o 'cliente'. Al cliente: solo publicado y con
 * la presentación lista; se reclama el envío antes de bajar el PDF y, ya reclamado, se vuelve a calcular todo lo que usa
 * el correo con el avance releído en el reclamo (el texto nunca es más viejo que el PDF). El reclamo se suelta siempre
 * al terminar, salga como salga. ['ok', 'motivo', 'detalle', 'sin_pdf'] (sin_pdf: por qué salió sin adjunto, '' si lo lleva).
 */
function at_av_enviar_cierre(int $plan_id, string $cierre_id, string $destino, string $mensaje, bool $reenviar): array {
	$no = fn(string $m, string $d = '') => ['ok' => false, 'motivo' => $m, 'detalle' => $d, 'sin_pdf' => ''];
	$prueba = $destino !== 'cliente';
	$mensaje = mb_substr(trim((string) sanitize_textarea_field($mensaje)), 0, AT_AV_MENSAJE_MAX);
	$f0 = at_pt_plan($plan_id);
	if (!$f0) {
		return $no('sin_plan');
	}
	$x = at_av_contexto_envio($f0, at_av_leer($f0), $cierre_id, $prueba, $mensaje);
	if ($x['motivo'] !== '') {
		return $no($x['motivo'], $x['detalle']);
	}

	if (!$prueba) {
		// Reclamo atómico (Review Focus 1): se relee todo y se escribe con la condición «el avance sigue siendo el que leí».
		// Si otro proceso escribió entretanto, esta escritura no pasa y no se envía nada.
		$f2 = at_pt_plan($plan_id);
		if (!$f2) {
			return $no('sin_plan');
		}
		$antes = (string) $f2->avance;
		$av2 = at_av_leer($f2);
		$k = at_av_indice_cierre($av2, $cierre_id);
		if ($k < 0) {
			return $no('sin_cierre');
		}
		$env = at_av_envio_de_cierre($av2['cierres'][$k]);
		$ahora = current_time('mysql');
		if ($env['correo']['enviando'] !== null && abs(strtotime($ahora) - (int) strtotime($env['correo']['enviando'])) < AT_AV_ENVIO_VENCE) {
			return $no('enviando');
		}
		if ($env['correo']['enviado_at'] !== null && !$reenviar) {
			return $no('ya_enviado');
		}
		$av2['cierres'][$k]['correo'] = array_merge($env['correo'], ['enviando' => $ahora]);
		$av2['cierres'][$k]['whatsapp'] = $env['whatsapp'];
		if (!at_av_guardar_si_igual($plan_id, $antes, $av2)) {
			return $no('enviando'); // otro proceso se adelantó: false = «reintenta», nunca «enviado»
		}
		// Reclamado: todo lo que usa el correo sale de este avance, no del anterior. Si ya no se puede, se suelta el reclamo.
		$x = at_av_contexto_envio($f2, $av2, $cierre_id, false, $mensaje);
		if ($x['motivo'] !== '') {
			at_av_anotar_envio($plan_id, $cierre_id, ['enviando' => null]);
			return $no($x['motivo'], $x['detalle']);
		}
	}
	['f' => $f, 'av' => $av, 'c' => $c, 'r' => $r, 'd' => $d, 'url_estado' => $url_estado, 'proyecto' => $proyecto, 'para' => $para] = $x['ctx'];

	$enviado = false;
	$sin_pdf = '';
	$detalle = '';
	$adjuntos = [];
	$titulo = (string) ($r['titulo'] ?? ($c['titulo'] ?? ''));
	try {
		$render = (array) ($av['render'] ?? []);
		$pdf = (at_av_render_listo_para($av, $c) && trim((string) ($render['pdf_url'] ?? '')) !== '')
			? at_pt_descargar_pdf((string) $render['pdf_url'], (string) $f->codigo, 'Estado-del-proyecto.pdf')
			: new WP_Error('sin_pdf', 'la presentación todavía no está lista');
		$adjuntos = is_wp_error($pdf) ? [] : [$pdf];
		$sin_pdf = is_wp_error($pdf) ? $pdf->get_error_message() : '';
		$html = at_av_correo_cierre_html([
			'nombre' => (string) ($d['client_name'] ?? ''), 'proyecto' => $proyecto,
			'logo' => defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png'),
			'resumen' => $r, 'mensaje' => $mensaje, 'url_estado' => $url_estado, 'con_pdf' => $adjuntos !== [],
		]);
		$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
		$cabeceras = array_merge(
			['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'],
			function_exists('at_cc_correo_avisos') ? ['Reply-To: ' . at_cc_correo_avisos()] : [],
			(!$prueba && function_exists('at_cc_cabecera_copia')) ? at_cc_cabecera_copia($para) : []
		);
		$asunto = ($prueba ? '[Prueba] ' : '') . 'Terminamos: ' . $titulo . ' — ' . ($proyecto !== '' ? $proyecto : 'AutomatizaTech');
		$enviado = (bool) wp_mail($para, $asunto, $html, $cabeceras, $adjuntos);
	} catch (Throwable $e) {
		// Cualquier fallo antes de saber si salió: se trata como no enviado, se suelta el reclamo y se dice qué pasó.
		$enviado = false;
		$detalle = 'error interno al armar o enviar el correo: ' . get_class($e) . ': ' . $e->getMessage();
	}
	foreach ($adjuntos as $a) {
		@unlink($a);
		@rmdir(dirname($a));
	}

	if ($prueba) {
		if ($enviado) {
			at_av_anotar_envio($plan_id, $cierre_id, ['prueba_at' => current_time('mysql')]);
		}
		return ['ok' => $enviado, 'motivo' => $enviado ? '' : 'correo_fallo', 'detalle' => $detalle, 'sin_pdf' => $sin_pdf];
	}
	if (!$enviado) {
		at_av_anotar_envio($plan_id, $cierre_id, ['enviando' => null]);
		return ['ok' => false, 'motivo' => 'correo_fallo', 'detalle' => $detalle, 'sin_pdf' => $sin_pdf];
	}
	$anotado = at_av_anotar_envio($plan_id, $cierre_id, ['enviando' => null, 'enviado_at' => current_time('mysql'), 'a' => $para, 'mensaje' => $mensaje]);
	at_pt_historial($f, 'hito_cierre_correo', 'Cierre de hito enviado por correo', 'Enviamos por correo el cierre «' . $titulo . '»'
		. ($sin_pdf === '' ? ', con el estado del proyecto en PDF.' : ', sin el PDF (' . $sin_pdf . '): el correo lleva el enlace.'));
	return ['ok' => true, 'motivo' => '', 'detalle' => $anotado ? '' : 'El correo salió, pero no pudimos anotar el envío: revisa el historial antes de reenviar.', 'sin_pdf' => $sin_pdf];
}

/** wa.me con el texto del cierre; '' si la ficha no tiene teléfono o el cierre no está publicado. */
function at_av_url_whatsapp_cierre(object $fila, string $cierre_id): string {
	$p = at_av_preparar_envio((int) $fila->id, $cierre_id);
	if (is_string($p)) {
		return '';
	}
	[, , , $r, $d, $url_estado] = $p;
	$tel = at_pt_telefono_wa((string) ($d['phone'] ?? ''));
	return $tel === '' ? '' : 'https://wa.me/' . $tel . '?text=' . rawurlencode(at_av_texto_whatsapp_cierre((string) ($d['client_name'] ?? ''), $r, $url_estado));
}

/** «Enviar por mi WhatsApp»: anota el envío y el historial y devuelve el wa.me. ['ok', 'motivo', 'url']. */
function at_av_registrar_whatsapp_cierre(int $plan_id, string $cierre_id): array {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return ['ok' => false, 'motivo' => 'sin_plan', 'url' => ''];
	}
	$c = at_av_cierre_de_avance(at_av_leer($f), $cierre_id);
	if ($c === null || ($c['estado'] ?? '') !== 'publicado') {
		return ['ok' => false, 'motivo' => $c === null ? 'sin_cierre' : 'no_publicado', 'url' => ''];
	}
	$url = at_av_url_whatsapp_cierre($f, $cierre_id);
	if ($url === '') {
		return ['ok' => false, 'motivo' => 'sin_telefono', 'url' => ''];
	}
	$anotado = at_av_anotar_envio($plan_id, $cierre_id, [], ['abierto_at' => current_time('mysql')]);
	at_pt_historial($f, 'hito_cierre_whatsapp', 'Cierre de hito enviado por WhatsApp', 'Abrimos el WhatsApp del equipo con el cierre «' . (string) ($c['titulo'] ?? '') . '» y el enlace al estado del proyecto.');
	return ['ok' => true, 'motivo' => $anotado ? '' : 'no_anotado', 'url' => $url];
}
