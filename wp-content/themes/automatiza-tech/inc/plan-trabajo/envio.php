<?php
/**
 * Plan de trabajo, Etapa 2: Luis envía el plan «listo» al cliente por correo (PDF adjunto si se puede, enlace a
 * ver-plan.php siempre) o por su WhatsApp (wa.me con el enlace). Al salir, el plan queda «enviado» (decisión 5: solo con
 * su clic). Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md, sección 7.
 */
if (!defined('ABSPATH')) {
	exit;
}

// En base64 el adjunto crece ~33 %: 15 MB quedan en ~20 MB, bajo los 25 MB por adjunto de Hostinger y Gmail (igual
// que AT_PA_PDF_MAX_BYTES de las propuestas).
const AT_PT_PDF_MAX_BYTES = 15728640;

function at_pt_host_renderer(): string {
	return defined('AT_PA_RENDERER_HOST') ? (string) AT_PA_RENDERER_HOST : 'n8n-propuesta-renderer.kchiba.easypanel.host';
}

/** Baja el PDF de la versión final a un archivo temporal propio. Solo desde https://<renderer>/p/<código>/presentation.pdf.
 *  WP_Error con el motivo legible si no se pudo. Quien lo use borra el archivo y su carpeta. */
function at_pt_descargar_pdf(string $url, string $codigo) {
	if (!preg_match('#^https://' . preg_quote(at_pt_host_renderer(), '#') . '/p/[A-Za-z0-9]{6,32}/presentation\.pdf$#', trim($url))) {
		return new WP_Error('pdf_url', 'el PDF no es del renderer');
	}
	$r = wp_remote_get(trim($url), ['timeout' => 60, 'limit_response_size' => AT_PT_PDF_MAX_BYTES + 1, 'redirection' => 0]);
	if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
		return new WP_Error('pdf_descarga', 'no se pudo descargar el PDF');
	}
	$cuerpo = (string) wp_remote_retrieve_body($r);
	if (strlen($cuerpo) > AT_PT_PDF_MAX_BYTES) {
		return new WP_Error('pdf_grande', 'el PDF pesa más de 15 MB');
	}
	if (strncmp($cuerpo, '%PDF', 4) !== 0) {
		return new WP_Error('pdf_descarga', 'no se pudo descargar el PDF');
	}
	// Carpeta única por envío: dos envíos a la vez no se pisan el archivo.
	$dir = trailingslashit(get_temp_dir()) . 'at-plan-' . preg_replace('/[^A-Za-z0-9]/', '', $codigo) . '-' . wp_generate_password(8, false, false) . '/';
	wp_mkdir_p($dir);
	$archivo = $dir . 'Plan-de-trabajo.pdf';
	if (file_put_contents($archivo, $cuerpo) === false) {
		@rmdir($dir);
		return new WP_Error('pdf_descarga', 'no se pudo guardar el PDF');
	}
	return $archivo;
}

/** El plan se puede mandar: versión final lista (o ya enviada, para reenviar) y con su vista. */
function at_pt_se_puede_enviar(object $fila): bool {
	return in_array((string) $fila->estado, ['listo', 'enviado'], true) && trim((string) $fila->view_url) !== '';
}

/** Deja el plan «enviado» (si estaba listo) con la fecha de envío. false si otro proceso lo movió entretanto. */
function at_pt_marcar_enviado(object $fila): bool {
	$id = (int) $fila->id;
	if ((string) $fila->estado === 'listo' && !at_pt_cambiar_estado($id, 'enviado', '')) {
		return false;
	}
	return at_pt_guardar($id, ['enviado_at' => current_time('mysql')]);
}

/** Anota en el historial del CRM del cliente (si el módulo del cierre está cargado y hay cliente). */
function at_pt_historial(object $fila, string $tipo, string $titulo, string $detalle): void {
	$crm = at_pt_crm_de_plan($fila);
	if ($crm > 0 && function_exists('at_cc_historial_crm')) {
		at_cc_historial_crm($crm, $tipo, $titulo, $detalle);
	}
}

/**
 * Envía el plan al correo del cliente (at_pt_datos_agenda(): ficha del CRM o, si falta, el contrato). Reply-To al
 * correo principal del cierre y copia oculta de «Ajustes del cierre». El PDF va adjunto si se pudo bajar; si no, el
 * correo sale igual con el enlace y los botones (Review Focus 4). Devuelve ['ok', 'motivo', 'sin_pdf'].
 */
function at_pt_enviar_plan(int $id): array {
	$fila = at_pt_plan($id);
	if (!$fila) {
		return ['ok' => false, 'motivo' => 'sin_plan', 'sin_pdf' => ''];
	}
	if (!at_pt_se_puede_enviar($fila)) {
		return ['ok' => false, 'motivo' => 'no_listo', 'sin_pdf' => ''];
	}
	$d = at_pt_datos_agenda($id);
	$correo = (string) ($d['client_email'] ?? '');
	if ($correo === '') {
		return ['ok' => false, 'motivo' => 'sin_correo', 'sin_pdf' => ''];
	}
	$pl = at_pt_payload($fila);
	$codigo = (string) $fila->codigo;
	$proyecto = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : (string) ($d['company_name'] ?? '');
	$pdf = at_pt_descargar_pdf((string) $fila->pdf_url, $codigo);
	$adjuntos = is_wp_error($pdf) ? [] : [$pdf];
	$html = at_pt_correo_plan_html([
		'nombre'       => (string) ($d['client_name'] ?? ''),
		'proyecto'     => $proyecto,
		'logo'         => defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png'),
		'url_ver'      => at_pt_url_ver_plan(home_url(), $codigo),
		'url_agendar'  => at_pt_url_ver_plan(home_url(), $codigo, true),
		'url_whatsapp' => at_pt_url_whatsapp_agenda(function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '56927002984', $codigo),
		'inicio'       => (string) ($pl['cronograma']['inicio'] ?? ($fila->fecha_inicio ?? '')),
		'entrega'      => at_pt_entrega_estimada($pl),
		'con_pdf'      => $adjuntos !== [],
	]);
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$cabeceras = array_merge(
		['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'],
		function_exists('at_cc_correo_avisos') ? ['Reply-To: ' . at_cc_correo_avisos()] : [],
		function_exists('at_cc_cabecera_copia') ? at_cc_cabecera_copia($correo) : []
	);
	$enviado = (bool) wp_mail($correo, 'Tu plan de trabajo — ' . ($proyecto !== '' ? $proyecto : 'AutomatizaTech'), $html, $cabeceras, $adjuntos);
	foreach ($adjuntos as $a) {
		@unlink($a);
		@rmdir(dirname($a));
	}
	$sin_pdf = is_wp_error($pdf) ? $pdf->get_error_message() : '';
	if (!$enviado) {
		return ['ok' => false, 'motivo' => 'correo_fallo', 'sin_pdf' => $sin_pdf];
	}
	if (!at_pt_marcar_enviado($fila)) {
		return ['ok' => false, 'motivo' => 'transicion', 'sin_pdf' => $sin_pdf];
	}
	at_pt_historial($fila, 'email', 'Plan de trabajo enviado', 'Se envió por correo el plan de trabajo ' . $codigo
		. ($sin_pdf === '' ? ', con el PDF adjunto.' : ', sin el PDF (' . $sin_pdf . '): el correo lleva el enlace.'));
	return ['ok' => true, 'motivo' => '', 'sin_pdf' => $sin_pdf];
}

/** wa.me al teléfono del cliente (ficha del CRM o contrato) con el mensaje y el enlace del plan; '' sin teléfono. */
function at_pt_url_whatsapp_cliente(object $fila): string {
	$d = at_pt_datos_agenda((int) $fila->id);
	$tel = at_pt_telefono_wa((string) ($d['phone'] ?? ''));
	if ($tel === '') {
		return '';
	}
	$pl = at_pt_payload($fila);
	$proyecto = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : (string) ($d['company_name'] ?? '');
	return 'https://wa.me/' . $tel . '?text=' . rawurlencode(at_pt_texto_whatsapp_envio((string) ($d['client_name'] ?? ''), $proyecto, at_pt_url_ver_plan(home_url(), (string) $fila->codigo)));
}

/** «Enviar por mi WhatsApp»: deja el plan enviado y devuelve el wa.me para abrirlo. Devuelve ['ok', 'motivo', 'url']. */
function at_pt_marcar_enviado_whatsapp(int $id): array {
	$fila = at_pt_plan($id);
	if (!$fila) {
		return ['ok' => false, 'motivo' => 'sin_plan', 'url' => ''];
	}
	if (!at_pt_se_puede_enviar($fila)) {
		return ['ok' => false, 'motivo' => 'no_listo', 'url' => ''];
	}
	$url = at_pt_url_whatsapp_cliente($fila);
	if ($url === '') {
		return ['ok' => false, 'motivo' => 'sin_telefono', 'url' => ''];
	}
	if (!at_pt_marcar_enviado($fila)) {
		return ['ok' => false, 'motivo' => 'transicion', 'url' => ''];
	}
	at_pt_historial($fila, 'whatsapp', 'Plan de trabajo enviado por WhatsApp', 'Se abrió el WhatsApp de Luis con el enlace del plan de trabajo ' . $fila->codigo . '.');
	return ['ok' => true, 'motivo' => '', 'url' => $url];
}
