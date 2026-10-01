<?php
/**
 * Plan de trabajo, Etapa 2: la página pública ver-plan.php?id=<código>[&agendar=1]. iframe del renderer, barra con los
 * dos botones de agenda (web y WhatsApp con Tech) y el diálogo con el selector de horarios de la portada (at-agenda.js).
 * Sin aceptar ni rechazar. Solo planes «listo» (Luis lo mira antes de enviar) o «enviado»; la agenda web solo en
 * «enviado» (la ruta de agenda.php lo exige igual).
 */
if (!defined('ABSPATH')) {
	exit;
}

/** El plan de ese código si se puede mostrar (listo o enviado, con su vista); null si no. */
function at_pt_fila_ver_plan(string $codigo): ?object {
	$f = at_pt_plan_por_codigo($codigo);
	if (!$f || !in_array((string) $f->estado, ['listo', 'enviado'], true) || trim((string) $f->view_url) === '') {
		return null;
	}
	return $f;
}

/** Documento HTML de ver-plan.php. $abrir_agenda = vino con agendar=1. */
function at_pt_html_ver_plan(?object $fila, bool $abrir_agenda): string {
	$tema = get_template_directory_uri();
	$dir = get_template_directory();
	$ver = function (string $rel) use ($dir): string {
		$f = $dir . $rel;
		return file_exists($f) ? (string) filemtime($f) : '1';
	};
	$cabeza = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
		. '<meta name="robots" content="noindex, nofollow"><title>Plan de trabajo — AutomatizaTech</title>'
		. '<link rel="stylesheet" href="' . esc_url($tema . '/assets/css/plan-ver.css?v=' . $ver('/assets/css/plan-ver.css')) . '"></head>';
	if (!$fila) {
		return $cabeza . '<body class="at-pt-ver at-pt-ver--vacio"><main class="at-pt-vacio"><h1>Este plan de trabajo no está disponible</h1>'
			. '<p>Revisa el enlace que te enviamos o escríbenos a contacto@automatizatech.cl.</p></main></body></html>';
	}
	$codigo = (string) $fila->codigo;
	$enviado = (string) $fila->estado === 'enviado';
	$wa = at_pt_url_whatsapp_agenda(function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '56927002984', $codigo);
	$ya = $enviado ? at_pt_seguimiento_pendiente($codigo) : null;
	$logo = defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
	$html = $cabeza . '<body class="at-pt-ver">'
		. '<iframe src="' . esc_url((string) $fila->view_url) . '" title="Plan de trabajo" allow="fullscreen"></iframe>'
		. '<nav class="at-pt-barra" aria-label="Agenda tu llamada de seguimiento"><p>¿Revisamos juntos tu plan?</p>';
	if ($enviado && $ya) {
		$html .= '<p class="at-pt-ya">Ya tienes tu llamada de seguimiento el ' . esc_html(at_pt_fecha_larga((string) $ya->meeting_date) . ' a las ' . substr((string) $ya->meeting_time, 0, 5)) . '.</p>';
	} elseif ($enviado) {
		$html .= '<button type="button" class="at-pt-btn at-pt-btn--si" data-at-pt-abrir>📅 Agendar mi llamada de seguimiento</button>';
	} else {
		$html .= '<p class="at-pt-ya">Podrás agendar tu llamada de seguimiento aquí cuando te enviemos el plan.</p>';
	}
	if ($wa !== '') {
		$html .= '<a class="at-pt-btn at-pt-btn--sec" href="' . esc_url($wa) . '" target="_blank" rel="noopener">💬 Agendar por WhatsApp con Tech</a>';
	}
	$html .= '</nav>';
	if ($enviado && !$ya) {
		$html .= '<dialog id="at-pt-agenda" class="at-pt-dlg" aria-labelledby="at-pt-agenda-titulo">'
			. '<form id="at-pt-form-agenda" novalidate>'
			. '<div class="at-pt-dlg-cab"><img src="' . esc_url($logo) . '" alt="" width="53" height="44"><span>AutomatizaTech</span></div>'
			. '<h2 id="at-pt-agenda-titulo">Agenda tu llamada de seguimiento</h2>'
			. '<p>Revisamos juntos tu plan de trabajo y aclaramos tus dudas por videollamada. Te llega la invitación por correo.</p>'
			. '<p id="at-pt-agenda-msg" class="at-pt-msg" role="status" hidden></p>'
			. '<div class="at-pt-campos">'
			. '<label for="at-fecha">Día</label><input type="date" id="at-fecha" required>'
			. '<p class="at-pt-etiqueta">Hora</p><div id="at-slots" class="at-pt-horas"><span>Elige una fecha para ver horarios disponibles.</span></div>'
			. '<input type="hidden" id="at-scheduled-time" value=""><input type="hidden" id="at-franja" value="">'
			. '<div class="at-pt-trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>'
			. '</div>'
			. '<div class="at-pt-dlg-botones"><button type="button" class="at-pt-btn at-pt-btn--sec" id="at-pt-cerrar">Cerrar</button>'
			. '<button type="submit" class="at-pt-btn at-pt-btn--si">Agendar</button></div>'
			. '</form></dialog>'
			. '<script>window.AT_AGENDA = ' . wp_json_encode(['configUrl' => esc_url_raw(rest_url('automatiza-tech/v1/appointments-config'))], JSON_HEX_TAG) . ';'
			. 'window.AT_PT_VER = ' . wp_json_encode(['url' => esc_url_raw(rest_url('automatiza-tech/v1/plan-seguimiento')), 'codigo' => $codigo, 'token' => at_pt_token_agenda_hoy($codigo), 'abrir' => $abrir_agenda], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>'
			. '<script src="' . esc_url($tema . '/assets/home-premium/at-agenda.js?v=' . $ver('/assets/home-premium/at-agenda.js')) . '"></script>'
			. '<script src="' . esc_url($tema . '/assets/js/plan-ver.js?v=' . $ver('/assets/js/plan-ver.js')) . '"></script>';
	}
	return $html . '</body></html>';
}
