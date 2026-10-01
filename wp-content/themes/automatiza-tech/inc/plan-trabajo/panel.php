<?php
/**
 * Pestaña «🗓️ Plan de trabajo» de la ficha del cliente en el CRM (crm-ai-completo.php la dibuja con
 * at_pt_render_pestana()) y sus acciones admin-post. Mismo estilo y botones que el panel de propuestas.
 * Toda escritura: manage_options + nonce por plan ('at_pt_plan_<id>'; «Crear»: 'at_pt_crear_<contrato>') y vuelta
 * a la ficha con pt_msg (at_pt_url_ficha). Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md §6.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_enqueue_scripts', 'at_pt_encolar_assets');

/** Pestaña del plan en la ficha del cliente (el JS de plan-trabajo.js la abre por el #tab-plan). */
function at_pt_url_ficha(int $crm_id, int $plan_id = 0, string $msg = ''): string {
	$args = ['page' => 'automatiza-crm-ficha', 'id' => $crm_id];
	if ($plan_id > 0) {
		$args['pt'] = $plan_id;
	}
	if ($msg !== '') {
		$args['pt_msg'] = $msg;
	}
	return add_query_arg($args, admin_url('admin.php')) . '#tab-plan';
}

/** CSS y JS de la pestaña, solo en la ficha del cliente del CRM. */
function at_pt_encolar_assets(): void {
	if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== 'automatiza-crm-ficha') {
		return;
	}
	$dir = get_template_directory() . '/assets';
	$url = get_template_directory_uri() . '/assets';
	if (file_exists($dir . '/css/plan-trabajo.css')) {
		wp_enqueue_style('at-plan-trabajo', $url . '/css/plan-trabajo.css', [], (string) filemtime($dir . '/css/plan-trabajo.css'));
	}
	if (file_exists($dir . '/js/plan-trabajo.js')) {
		wp_enqueue_script('at-plan-trabajo', $url . '/js/plan-trabajo.js', [], (string) filemtime($dir . '/js/plan-trabajo.js'), true);
	}
}

function at_pt_estados_etiqueta(): array {
	return [
		'generando' => 'Generando borrador', 'borrador' => 'Borrador', 'cambios' => 'Aplicando cambios',
		'aprobando' => 'Generando versión final', 'listo' => 'Listo', 'enviado' => 'Enviado', 'error' => 'Error',
	];
}

/** Avisos que vuelven en pt_msg: clave => [tipo, texto]. */
function at_pt_mensajes_panel(): array {
	return [
		'creado'             => ['ok', 'Plan creado. La IA está armando el borrador; te llegará un correo cuando esté la vista previa.'],
		'ya_existe'          => ['aviso', 'Ese contrato ya tiene plan de trabajo: no se creó otro.'],
		'no_se_pudo'         => ['error', 'No se pudo crear el plan: el contrato debe ser de servicios, estar firmado y ser de este cliente.'],
		'guardado'           => ['ok', 'Guardado y recalculado. La vista previa nueva llega en unos segundos.'],
		'guardado_sin_vista' => ['aviso', 'Guardado y recalculado, pero no se pudo pedir la vista previa a n8n. Vuelve a guardar en un rato.'],
		'error_guardar'      => ['error', 'No se pudo guardar el plan en la base. Inténtalo de nuevo.'],
		'invalido'           => ['error', 'No se guardó: el plan tiene errores.'],
		'json_invalido'      => ['error', 'No se guardó: la tabla no llegó completa. Recarga la página e inténtalo de nuevo.'],
		'no_editable'        => ['error', 'Este plan no se puede editar mientras la IA trabaja en él. Si lleva mucho rato, usa «Destrabar».'],
		'cambios_pedidos'    => ['ok', 'Cambios pedidos. Te llegará un correo con la nueva vista previa.'],
		'sin_comentarios'    => ['error', 'Escribe qué quieres cambiar antes de pedir cambios.'],
		'aprobando'          => ['ok', 'Aprobado. Se están generando las fotos y la versión final; te llegará un correo cuando esté lista.'],
		'destrabado'         => ['ok', 'Plan destrabado: quedó en «error» para poder reintentar.'],
		'reintentando'       => ['ok', 'Se le pidió de nuevo el borrador a la IA.'],
		'vuelto_borrador'    => ['ok', 'El plan volvió a borrador: puedes editarlo, pedir cambios o aprobarlo.'],
		'n8n_fallo'          => ['error', 'No se pudo avisar a n8n: el plan quedó en «error» con el motivo. Puedes reintentar desde aquí.'],
		'enviado'            => ['ok', 'Plan enviado por correo al cliente, con el PDF adjunto.'],
		'enviado_sin_pdf'    => ['aviso', 'Plan enviado por correo, pero sin el PDF adjunto: el correo lleva el enlace para verlo.'],
		'no_listo'           => ['error', 'Solo se envía un plan con la versión final lista.'],
		'sin_correo'         => ['error', 'El cliente no tiene correo en su ficha ni en el contrato: agrégalo en la ficha y vuelve a enviar.'],
		'correo_fallo'       => ['error', 'El correo no salió. El plan sigue «listo»: inténtalo de nuevo en un rato.'],
		'sin_telefono'       => ['error', 'El cliente no tiene teléfono en su ficha ni en el contrato.'],
		'transicion'         => ['error', 'Esa acción no corresponde al estado actual del plan.'],
		'sin_plan'           => ['error', 'Ese plan de trabajo no existe.'],
	];
}

/** Detalle de la última acción (errores de validación o avisos), una sola vez, por usuario. */
function at_pt_guardar_detalles(int $plan_id, array $lineas): void {
	$lineas = array_values(array_filter(array_map('strval', $lineas), 'strlen'));
	if ($lineas) {
		set_transient('at_pt_detalles_' . get_current_user_id(), ['plan_id' => $plan_id, 'lineas' => array_slice($lineas, 0, 20)], 120);
	}
}

function at_pt_tomar_detalles(int $plan_id): array {
	$clave = 'at_pt_detalles_' . get_current_user_id();
	$d = get_transient($clave);
	if (!is_array($d) || (int) ($d['plan_id'] ?? 0) !== $plan_id) {
		return [];
	}
	delete_transient($clave);
	return array_map('strval', (array) ($d['lineas'] ?? []));
}

function at_pt_aviso_panel(int $plan_id): string {
	$m = at_pt_mensajes_panel()[sanitize_key(wp_unslash($_GET['pt_msg'] ?? ''))] ?? null;
	if (!$m) {
		return '';
	}
	$clase = ['ok' => 'notice-success', 'aviso' => 'notice-warning'][$m[0]] ?? 'notice-error';
	$html = '<div class="notice ' . $clase . ' inline at-pt-aviso"><p>' . esc_html($m[1]) . '</p>';
	foreach (at_pt_tomar_detalles($plan_id) as $l) {
		$html .= '<p>• ' . esc_html($l) . '</p>';
	}
	return $html . '</div>';
}

/** «5 oct» (o «5 oct 2026» con año) desde Y-m-d; '' si no es una fecha. */
function at_pt_panel_fecha(string $ymd, bool $con_anio = false): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
		return '';
	}
	$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	return (int) $m[3] . ' ' . $meses[(int) $m[2] - 1] . ($con_anio ? ' ' . $m[1] : '');
}

/** Fase del payload con esa clave ([] si el plan no la trae). */
function at_pt_fase_de(array $plan, string $clave): array {
	foreach ((array) ($plan['fases'] ?? []) as $f) {
		if (is_array($f) && ($f['clave'] ?? '') === $clave) {
			return $f;
		}
	}
	return [];
}

/** Hitos que edita Luis: todos menos «Entrega estimada», que se calcula sola. */
function at_pt_hitos_editables(array $plan): array {
	return array_values(array_filter((array) ($plan['hitos'] ?? []), function ($h) {
		return is_array($h) && ($h['nombre'] ?? '') !== 'Entrega estimada';
	}));
}

/** Líneas «a | b» (o solo «a» si b está vacío) para los textareas de reuniones e hitos. */
function at_pt_lineas_pares(array $items, string $a, string $b): string {
	$lineas = [];
	foreach ($items as $it) {
		if (!is_array($it) || trim((string) ($it[$a] ?? '')) === '') {
			continue;
		}
		$segundo = trim((string) ($it[$b] ?? ''));
		$lineas[] = trim((string) $it[$a]) . ($segundo !== '' ? ' | ' . $segundo : '');
	}
	return implode("\n", $lineas);
}

/** Una fila editable de actividad (también sirve de plantilla para «+ Actividad»). */
function at_pt_html_actividad(array $a): void {
	$etiquetas = at_pt_origenes(); // Task 2: 'Tabla de tiempos', 'IA · revisar', 'Editado por Luis'
	$ayudas = [
		'tabla' => 'Días de la tabla de tiempos',
		'ia'    => 'Días estimados por la IA: revísalos',
		'luis'  => 'Días que editaste tú: la IA y la tabla no los pisan',
	];
	$origen = isset($etiquetas[$a['origen'] ?? '']) ? (string) $a['origen'] : 'ia';
	$responsables = array_merge(at_pt_responsables(), ['cliente' => 'Cliente']);
	$desde = (string) ($a['desde'] ?? '');
	$hasta = (string) ($a['hasta'] ?? '');
	?>
	<div class="at-pt-act" data-servicio="<?php echo esc_attr((string) ($a['servicio'] ?? '')); ?>" data-etapa="<?php echo esc_attr((string) ($a['etapa'] ?? '')); ?>" data-origen="<?php echo esc_attr($origen); ?>">
		<div class="at-pt-act-l1">
			<input type="text" class="at-pt-a-nombre" value="<?php echo esc_attr((string) ($a['nombre'] ?? '')); ?>" maxlength="120" aria-label="Actividad" placeholder="Nombre de la actividad">
			<button type="button" class="at-pt-quitar at-pt-quitar-act" aria-label="Quitar esta actividad">✕</button>
		</div>
		<div class="at-pt-act-l2">
			<label>Responsable <select class="at-pt-a-responsable">
				<?php foreach ($responsables as $k => $t): ?><option value="<?php echo esc_attr($k); ?>"<?php selected((string) ($a['responsable'] ?? 'at'), $k); ?>><?php echo esc_html($t); ?></option><?php endforeach; ?>
			</select></label>
			<label>Días hábiles <input type="number" class="at-pt-a-dias" min="1" max="60" step="1" value="<?php echo esc_attr((string) (int) ($a['dias_habiles'] ?? 1)); ?>"></label>
			<label class="at-pt-check"><input type="checkbox" class="at-pt-a-paralelo"<?php checked(!empty($a['en_paralelo'])); ?>> En paralelo</label>
			<span class="at-pt-origen at-pt-origen--<?php echo esc_attr($origen); ?>" title="<?php echo esc_attr($ayudas[$origen] ?? ''); ?>"><?php echo esc_html((string) $etiquetas[$origen]); ?></span>
			<?php if ($desde !== ''): ?><span class="at-pt-fechas"><?php echo esc_html(at_pt_panel_fecha($desde) . ($hasta !== '' && $hasta !== $desde ? ' → ' . at_pt_panel_fecha($hasta) : '')); ?></span><?php endif; ?>
		</div>
		<div class="at-pt-act-l3"><input type="text" class="at-pt-a-detalle" value="<?php echo esc_attr((string) ($a['detalle'] ?? '')); ?>" maxlength="300" aria-label="Detalle de la actividad (sale en la lámina de la fase)" placeholder="Detalle (opcional, sale en la lámina)"></div>
	</div>
	<?php
}

/** Un bloque editable con sus actividades (también sirve de plantilla para «+ Bloque»). */
function at_pt_html_bloque(array $b): void {
	$acts = array_values(array_filter((array) ($b['actividades'] ?? []), 'is_array'));
	$desde = '';
	$hasta = '';
	foreach ($acts as $a) {
		$d = (string) ($a['desde'] ?? '');
		$h = (string) ($a['hasta'] ?? '');
		if ($d !== '' && ($desde === '' || $d < $desde)) {
			$desde = $d;
		}
		if ($h !== '' && $h > $hasta) {
			$hasta = $h;
		}
	}
	?>
	<div class="at-pt-bloque">
		<div class="at-pt-bloque-cab">
			<label class="at-pt-campo">Bloque <input type="text" class="at-pt-b-nombre" value="<?php echo esc_attr((string) ($b['nombre'] ?? '')); ?>" maxlength="80"></label>
			<label class="at-pt-campo">Entregable <input type="text" class="at-pt-b-entregable" value="<?php echo esc_attr((string) ($b['entregable'] ?? '')); ?>" maxlength="160"></label>
		</div>
		<div class="at-pt-bloque-pie">
			<label class="at-pt-check"><input type="checkbox" class="at-pt-b-entrega"<?php checked(!empty($b['entrega'])); ?>> Revisión del cliente después (5 días hábiles)</label>
			<?php if ($desde !== ''): ?><span class="at-pt-fechas"><?php echo esc_html(at_pt_panel_fecha($desde) . ' → ' . at_pt_panel_fecha($hasta)); ?></span><?php endif; ?>
			<button type="button" class="at-pt-quitar at-pt-quitar-bloque">Quitar bloque</button>
		</div>
		<div class="at-pt-acts">
			<?php foreach ($acts as $a) { at_pt_html_actividad($a); } ?>
		</div>
		<button type="button" class="button button-small at-pt-agregar-act">+ Actividad</button>
	</div>
	<?php
}

/** Contenido de la pestaña «🗓️ Plan de trabajo» de la ficha del cliente ($cliente = fila ARRAY_A de wp_crm_clientes). */
function at_pt_render_pestana(array $cliente): void {
	$crm_id = (int) ($cliente['id'] ?? 0);
	echo '<div class="at-pt"><h3>🗓️ Plan de trabajo</h3>';
	if ($crm_id <= 0 || !current_user_can('manage_options')) {
		echo '<p>Solo un administrador puede ver el plan de trabajo.</p></div>';
		return;
	}
	$planes = array_values(at_pt_planes_de_crm($crm_id));
	$sin_plan = array_values(at_pt_contratos_sin_plan($crm_id));
	$pedido = absint($_GET['pt'] ?? 0);
	$fila = null;
	foreach ($planes as $p) {
		if ((int) $p->id === $pedido) {
			$fila = $p;
		}
	}
	if (!$fila && $planes) {
		$fila = $planes[0];
	}
	echo at_pt_aviso_panel($fila ? (int) $fila->id : 0);
	if (!$planes && !$sin_plan) {
		echo '<p>Este cliente no tiene contratos de servicios firmados. El plan de trabajo se crea solo cuando firma su contrato de servicios.</p></div>';
		return;
	}
	foreach ($sin_plan as $c) {
		at_pt_render_crear($c, $crm_id);
	}
	if ($fila) {
		if (count($planes) > 1) {
			at_pt_render_selector($planes, (int) $fila->id, $crm_id);
		}
		at_pt_render_plan($fila, $crm_id);
	}
	echo '</div>';
}

/** Botón «Crear plan de trabajo» para un contrato de servicios firmado que aún no tiene plan. */
function at_pt_render_crear(object $c, int $crm_id): void {
	$cid = (int) $c->id;
	$numero = (string) ($c->contract_number ?? '');
	$firmado = substr((string) ($c->signed_at ?? ''), 0, 10);
	?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="at-pt-crear">
		<input type="hidden" name="action" value="at_pt_crear">
		<input type="hidden" name="contrato_id" value="<?php echo $cid; ?>">
		<input type="hidden" name="crm_id" value="<?php echo $crm_id; ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('at_pt_crear_' . $cid)); ?>">
		<p>El contrato de servicios <strong><?php echo esc_html($numero !== '' ? $numero : '#' . $cid); ?></strong><?php echo $firmado !== '' ? esc_html(' (firmado el ' . at_pt_panel_fecha($firmado, true) . ')') : ''; ?> todavía no tiene plan de trabajo.</p>
		<p class="at-pt-botones"><button type="submit" class="button button-primary">🗓️ Crear plan de trabajo</button></p>
		<p class="description">La IA arma el borrador con lo contratado y la tabla de tiempos; te llega un correo con la vista previa. Al cliente no le llega nada.</p>
	</form>
	<?php
}

/** Selector cuando el cliente tiene más de un plan (un contrato de servicios firmado por plan). */
function at_pt_render_selector(array $planes, int $actual, int $crm_id): void {
	$etiquetas = at_pt_estados_etiqueta();
	echo '<nav class="at-pt-selector" aria-label="Planes de este cliente">';
	foreach ($planes as $p) {
		$pl = at_pt_payload($p);
		$nombre = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : 'Plan ' . $p->codigo;
		$es = (int) $p->id === $actual;
		printf(
			'<a class="button%s" href="%s"%s>%s</a>',
			$es ? ' button-primary' : '',
			esc_url(at_pt_url_ficha($crm_id, (int) $p->id)),
			$es ? ' aria-current="page"' : '',
			esc_html($nombre . ' · ' . ($etiquetas[$p->estado] ?? $p->estado))
		);
	}
	echo '</nav>';
}

/** Estado, fechas, documento, tabla editable y botones de un plan. */
function at_pt_render_plan(object $fila, int $crm_id): void {
	$id = (int) $fila->id;
	$pl = at_pt_payload($fila);
	$estado = (string) $fila->estado;
	$etiquetas = at_pt_estados_etiqueta();
	$tiene_plan = !empty($pl['fases']) && is_array($pl['fases']);
	$editable = $tiene_plan && in_array($estado, ['borrador', 'listo', 'error'], true);
	$ocupado = in_array($estado, ['generando', 'cambios', 'aprobando'], true);
	$nonce = wp_create_nonce('at_pt_plan_' . $id);
	$accion = admin_url('admin-post.php');
	$ocultos = function (string $a) use ($id, $crm_id, $nonce): string {
		return '<input type="hidden" name="action" value="' . esc_attr($a) . '">'
			. '<input type="hidden" name="plan_id" value="' . $id . '">'
			. '<input type="hidden" name="crm_id" value="' . $crm_id . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
	};
	$crono = is_array($pl['cronograma'] ?? null) ? $pl['cronograma'] : [];
	$entrega = '';
	// «Entrega estimada» (D4: fin del último bloque de implementación) vive solo en cronograma.hitos: at_pt_calcular_fechas()
	// la deja fuera de plan.hitos. Si no aparece, se usa el fin del cronograma (que incluye el soporte).
	foreach ((array) ($crono['hitos'] ?? []) as $h) {
		if (is_array($h) && ($h['nombre'] ?? '') === 'Entrega estimada') {
			$entrega = (string) ($h['fecha'] ?? '');
		}
	}
	if ($entrega === '') {
		$entrega = (string) ($crono['fin'] ?? '');
	}
	$semanas = (int) ($crono['semanas'] ?? 0);
	$proyecto = trim((string) ($pl['proyecto'] ?? ''));
	$nota = trim((string) ($fila->nota ?? ''));
	$sin_portal = function_exists('at_crm_url_portal') && at_crm_url_portal($crm_id) === '';
	// Decisión D8: la garantía del documento es la del contrato firmado (at_pt_db_partes(), Task 5), no la del plan.
	$garantia = (int) at_pt_db_partes($fila)['garantia_meses'];
	?>
	<div class="at-pt-plan" data-plan="<?php echo $id; ?>">
		<p class="at-pt-sub"><strong><?php echo esc_html($proyecto !== '' ? $proyecto : 'Plan sin nombre todavía'); ?></strong> · código <?php echo esc_html((string) $fila->codigo); ?> · contrato #<?php echo (int) $fila->contrato_id; ?></p>
		<div class="at-pt-resumen">
			<div class="at-pt-caja"><h4>Estado</h4>
				<span class="at-pt-estado at-pt-estado--<?php echo esc_attr($estado); ?>"><?php echo esc_html($etiquetas[$estado] ?? $estado); ?></span>
				<?php if ($nota !== '' && $estado !== 'error'): ?><p><?php echo esc_html($nota); ?></p><?php endif; ?>
				<p class="at-pt-sub">Actualizado: <?php echo esc_html((string) ($fila->updated_at ?? '')); ?></p>
			</div>
			<div class="at-pt-caja"><h4>Fechas estimadas</h4>
				<?php if (!empty($crono['inicio'])): ?>
					<p>Inicio: <?php echo esc_html(at_pt_panel_fecha((string) $crono['inicio'], true)); ?></p>
					<p>Entrega estimada: <?php echo esc_html(at_pt_panel_fecha($entrega, true)); ?></p>
					<p><?php echo esc_html($semanas === 1 ? '1 semana' : $semanas . ' semanas'); ?></p>
				<?php else: ?><p>Todavía sin fechas.</p><?php endif; ?>
			</div>
			<div class="at-pt-caja"><h4>Documento</h4>
				<?php if (!empty($fila->view_url)): ?>
					<p class="at-pt-botones"><a class="button" href="<?php echo esc_url((string) $fila->view_url); ?>" target="_blank" rel="noopener">👁️ <?php echo esc_html($estado === 'listo' ? 'Ver versión final' : 'Ver vista previa'); ?></a>
					<?php if (!empty($fila->pdf_url)): ?><a class="button" href="<?php echo esc_url((string) $fila->pdf_url); ?>" target="_blank" rel="noopener">📄 PDF</a><?php endif; ?></p>
				<?php else: ?><p>Aún no hay vista previa.</p><?php endif; ?>
			</div>
		</div>
		<?php if ($sin_portal): ?><div class="notice notice-warning inline"><p>Este cliente no tiene correo en su ficha: la lámina «Sigue tu proyecto» saldrá sin enlace al portal.</p></div><?php endif; ?>
		<?php if (empty($fila->propuesta_id)): ?><div class="notice notice-info inline"><p>Este contrato no tiene propuesta: la portada y el cierre llevarán fotos nuevas.</p></div><?php endif; ?>

		<?php if ($ocupado): ?>
			<div class="notice notice-info inline"><p>La IA está trabajando en este plan (<?php echo esc_html(mb_strtolower($etiquetas[$estado])); ?>). Te llegará un correo cuando termine. Si lleva más de 20 minutos, destrábalo.</p>
			<form method="post" action="<?php echo esc_url($accion); ?>" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('¿Destrabar este plan? Queda en «error» para poder reintentar. No se llama a n8n.', JSON_UNESCAPED_UNICODE)); ?>);">
				<?php echo $ocultos('at_pt_destrabar'); ?>
				<p class="at-pt-botones"><button type="submit" class="button">🔓 Destrabar (pasar a error)</button></p>
			</form></div>
		<?php endif; ?>

		<?php if ($estado === 'error'): ?>
			<div class="notice notice-error inline"><p><strong>El plan quedó en error.</strong> <?php echo esc_html($nota !== '' ? 'Motivo: ' . $nota : 'Sin motivo anotado.'); ?></p>
			<form method="post" action="<?php echo esc_url($accion); ?>">
				<?php echo $ocultos('at_pt_reintentar'); ?>
				<p class="at-pt-botones"><button type="submit" class="button button-primary"><?php echo esc_html($tiene_plan ? '↩️ Volver al borrador' : '🔁 Reintentar borrador'); ?></button></p>
			</form></div>
		<?php endif; ?>

		<?php if (in_array($estado, ['listo', 'enviado'], true)):
			$env = at_pt_datos_agenda($id);
			$correo_cliente = (string) ($env['client_email'] ?? '');
			$wa_cliente = at_pt_url_whatsapp_cliente($fila);
			$listo_para_enviar = at_pt_se_puede_enviar($fila);
		?>
			<div class="notice notice-success inline at-pt-envio">
				<?php if ($estado === 'listo'): ?>
					<p><strong>Versión final lista.</strong> Revísala y envíasela al cliente. Si cambias algo y guardas, el plan vuelve a borrador y hay que aprobarlo de nuevo.</p>
				<?php else: ?>
					<p><strong>Enviado al cliente</strong> el <?php echo esc_html((string) ($fila->enviado_at ?? '')); ?>. Puedes reenviarlo; el cliente ya puede agendar su llamada de seguimiento desde el plan.</p>
				<?php endif; ?>
				<p><?php echo $correo_cliente !== '' ? 'Correo del cliente: <strong>' . esc_html($correo_cliente) . '</strong>.' : 'El cliente no tiene correo en su ficha ni en el contrato: agrégalo en la pestaña General para poder enviarlo.'; ?></p>
				<form method="post" action="<?php echo esc_url($accion); ?>" style="display:inline" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('¿Enviar el plan de trabajo a ' . ($correo_cliente !== '' ? $correo_cliente : 'el cliente') . '? Le llega el correo con el PDF y el enlace para agendar la llamada de seguimiento.', JSON_UNESCAPED_UNICODE)); ?>);">
					<?php echo $ocultos('at_pt_enviar'); ?>
					<button type="submit" class="button button-primary"<?php echo ($correo_cliente === '' || !$listo_para_enviar) ? ' disabled' : ''; ?>>📧 <?php echo esc_html($estado === 'enviado' ? 'Reenviar al cliente' : 'Enviar al cliente'); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url($accion); ?>" target="_blank" style="display:inline" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('Se abrirá tu WhatsApp con el mensaje y el enlace del plan, y el plan quedará como enviado. ¿Seguir?', JSON_UNESCAPED_UNICODE)); ?>);">
					<?php echo $ocultos('at_pt_enviar_whatsapp'); ?>
					<button type="submit" class="button"<?php echo ($wa_cliente === '' || !$listo_para_enviar) ? ' disabled' : ''; ?>>💬 Enviar por mi WhatsApp</button>
				</form>
				<?php if ($wa_cliente === ''): ?><p class="description">El cliente no tiene teléfono: el WhatsApp no se puede abrir.</p><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ($tiene_plan): ?>
		<form method="post" action="<?php echo esc_url($accion); ?>" class="at-pt-form-plan">
			<?php echo $ocultos('at_pt_guardar'); ?>
			<input type="hidden" name="plan_json" class="at-pt-plan-json" value="">
			<fieldset<?php echo $editable ? '' : ' disabled'; ?>>
				<legend class="screen-reader-text">Plan editable</legend>
				<label class="at-pt-campo">Nombre del proyecto
					<input type="text" name="proyecto" value="<?php echo esc_attr($proyecto); ?>" maxlength="120"></label>
				<label class="at-pt-campo">Fecha de inicio
					<input type="date" name="fecha_inicio" value="<?php echo esc_attr((string) ($fila->fecha_inicio ?? '')); ?>"></label>
				<p class="description">Si cae en fin de semana o feriado, el plan parte el día hábil siguiente. Las fechas son estimadas: corren desde que llegan el anticipo y los insumos del cliente.</p>
				<?php foreach (at_pt_fases_validas() as $clave => $titulo): $fase = at_pt_fase_de($pl, $clave); ?>
				<section class="at-pt-fase" data-clave="<?php echo esc_attr($clave); ?>">
					<h4><?php echo esc_html($titulo); ?></h4>
					<label class="at-pt-campo">Descripción de la fase (va en su lámina)
						<textarea class="at-pt-f-descripcion" rows="2"><?php echo esc_textarea((string) ($fase['descripcion'] ?? '')); ?></textarea></label>
					<?php foreach ((array) ($fase['bloques'] ?? []) as $b) { at_pt_html_bloque(is_array($b) ? $b : []); } ?>
					<button type="button" class="button button-small at-pt-agregar-bloque">+ Bloque</button>
				</section>
				<?php endforeach; ?>
				<div class="at-pt-textos">
					<label class="at-pt-campo">Qué necesitamos del cliente (uno por línea)
						<textarea name="necesitamos_de_ti" rows="3"><?php echo esc_textarea(implode("\n", array_map('strval', (array) ($pl['necesitamos_de_ti'] ?? [])))); ?></textarea></label>
					<label class="at-pt-campo">Reuniones (una por línea: «Nombre | detalle»)
						<textarea name="reuniones" rows="3"><?php echo esc_textarea(at_pt_lineas_pares((array) ($pl['reuniones'] ?? []), 'nombre', 'detalle')); ?></textarea></label>
					<label class="at-pt-campo">Hitos (uno por línea: «Nombre | bloque después del cual se cumple»)
						<textarea name="hitos" rows="2"><?php echo esc_textarea(at_pt_lineas_pares(at_pt_hitos_editables($pl), 'nombre', 'despues_de')); ?></textarea></label>
					<p class="description">La «Entrega estimada» se calcula sola.</p>
					<label class="at-pt-campo">Meses de garantía
						<input type="number" class="at-pt-garantia" value="<?php echo esc_attr((string) $garantia); ?>" readonly aria-describedby="at-pt-garantia-nota"></label>
					<p class="description" id="at-pt-garantia-nota">Viene del contrato: se cambia en el contrato, no aquí.</p>
					<label class="at-pt-campo">Servicios mensuales (uno por línea; van en la lámina de reuniones y soporte)
						<textarea name="mensuales" rows="2"><?php echo esc_textarea(implode("\n", array_map('strval', (array) ($pl['soporte']['mensuales'] ?? [])))); ?></textarea></label>
				</div>
				<p class="at-pt-botones"><button type="submit" class="button button-primary">💾 Guardar y recalcular</button></p>
				<p class="description">Recalcula las fechas en días hábiles y pide una vista previa nueva, sin fotos. No usa IA.</p>
			</fieldset>
		</form>
		<template id="at-pt-tpl-actividad"><?php at_pt_html_actividad(['origen' => 'luis', 'responsable' => 'at', 'dias_habiles' => 1]); ?></template>
		<template id="at-pt-tpl-bloque"><?php at_pt_html_bloque(['nombre' => 'Nuevo bloque', 'actividades' => [['origen' => 'luis', 'responsable' => 'at', 'dias_habiles' => 1]]]); ?></template>

		<div class="at-pt-acciones">
			<?php if ($editable):
				$costo = at_pt_costo_fotos((array) ($pl['image_briefs'] ?? []), !empty($fila->propuesta_id));
				$n = (int) $costo['fotos'];
				$lista = number_format((float) $costo['usd_lista'], 4, ',', '.');
				$maximo = number_format((float) $costo['usd_max'], 4, ',', '.');
				// Con fotos nuevas, el costo incluye la revisión de texto con GPT-4o de «3 Render» (D18), como el panel de propuestas.
				$etiqueta_costo = '(' . $n . ($n === 1 ? ' foto' : ' fotos') . ($n > 0 ? ' + revisión' : '') . ' ≈ US$' . $lista . ')';
				$confirmar = $n > 0
					? sprintf('Se generarán %d fotos nuevas, se revisará que no tengan texto (≈ US$%s de lista; hasta US$%s si hay que rehacerlas) y la versión final del plan. ¿Aprobar?', $n, $lista, $maximo)
					: 'No hay fotos nuevas que generar. Se generará la versión final del plan. ¿Aprobar?';
				$ultimo = trim((string) ($fila->comentarios ?? ''));
			?>
			<form method="post" action="<?php echo esc_url($accion); ?>" class="at-pt-form-cambios at-pt-requiere-guardado">
				<?php echo $ocultos('at_pt_cambios'); ?>
				<label class="at-pt-campo">Qué quieres que cambie la IA
					<textarea name="comentarios" rows="3" placeholder="Ej.: separa la capacitación en dos sesiones y agrega la carga de productos"></textarea></label>
				<?php if ($ultimo !== ''): ?><p class="description">Último pedido: <?php echo esc_html($ultimo); ?></p><?php endif; ?>
				<p class="description">La IA aplica tus comentarios y respeta los días que editaste tú. No genera fotos.</p>
				<p class="at-pt-botones"><button type="submit" class="button">✏️ Pedir cambios</button></p>
			</form>
			<form method="post" action="<?php echo esc_url($accion); ?>" class="at-pt-form-aprobar at-pt-requiere-guardado" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode($confirmar, JSON_UNESCAPED_UNICODE)); ?>);">
				<?php echo $ocultos('at_pt_aprobar'); ?>
				<p class="at-pt-botones"><button type="submit" class="button button-primary">✅ Aprobar y generar versión final <?php echo esc_html($etiqueta_costo); ?></button></p>
			</form>
			<?php endif; ?>
			<p class="at-pt-botones"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=automatiza-followup&pt_plan=' . $id)); ?>">📅 Agendar llamada de seguimiento</a></p>
			<p class="description">Abre «Reuniones de seguimiento» con los datos del cliente; el evento, el correo y el WhatsApp salen solo si dejas sus casillas marcadas.</p>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

// ---------------------------------------------------------------------------------------------------------------
// Acciones admin-post de la pestaña. Todas terminan en at_pt_volver() (redirect + exit).
// ---------------------------------------------------------------------------------------------------------------

add_action('admin_post_at_pt_crear', 'at_pt_accion_crear');
add_action('admin_post_at_pt_guardar', 'at_pt_accion_guardar');
add_action('admin_post_at_pt_cambios', 'at_pt_accion_cambios');
add_action('admin_post_at_pt_aprobar', 'at_pt_accion_aprobar');
add_action('admin_post_at_pt_destrabar', 'at_pt_accion_destrabar');
add_action('admin_post_at_pt_reintentar', 'at_pt_accion_reintentar');
add_action('admin_post_at_pt_enviar', 'at_pt_accion_enviar');
add_action('admin_post_at_pt_enviar_whatsapp', 'at_pt_accion_enviar_whatsapp');

/** Vuelve a la pestaña del plan con un aviso; siempre termina la petición. */
function at_pt_volver(int $crm_id, int $plan_id, string $msg): void {
	wp_safe_redirect(at_pt_url_ficha($crm_id, $plan_id, $msg));
	exit;
}

/**
 * Cliente del CRM de un plan: el enlace actual de su ficha operativa (el mismo que usa at_pt_planes_de_crm()) o, si la
 * ficha no está enlazada, el que se guardó al crear el plan. 0 si no hay ninguno.
 */
function at_pt_crm_de_plan(object $fila): int {
	global $wpdb;
	$actual = (int) ($fila->tech_id ?? 0) > 0
		? (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $fila->tech_id))
		: 0;
	return $actual > 0 ? $actual : (int) ($fila->crm_cliente_id ?? 0);
}

/** Nonce del plan, permiso y plan de una acción del panel. Devuelve [fila, crm_id para volver]. */
function at_pt_accion_plan(): array {
	$id = absint($_POST['plan_id'] ?? 0);
	check_admin_referer('at_pt_plan_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$crm_post = absint($_POST['crm_id'] ?? 0);
	$fila = at_pt_plan($id);
	if (!$fila) {
		at_pt_volver($crm_post, 0, 'sin_plan');
	}
	$crm = at_pt_crm_de_plan($fila);
	return [$fila, $crm > 0 ? $crm : $crm_post];
}

/** Si n8n no recibió el aviso y el plan sigue en el estado intermedio, lo deja en «error» con esa nota. */
function at_pt_error_si_sigue(int $plan_id, string $estado_intermedio, string $nota): void {
	$f = at_pt_plan($plan_id);
	if ($f && (string) $f->estado === $estado_intermedio) {
		at_pt_cambiar_estado($plan_id, 'error', $nota);
	}
}

/** El contrato es de una ficha operativa enlazada a ese cliente del CRM. */
function at_pt_contrato_es_del_cliente(int $contrato_id, int $crm_id): bool {
	if ($contrato_id <= 0 || $crm_id <= 0) {
		return false;
	}
	global $wpdb;
	$crm = $wpdb->get_var($wpdb->prepare(
		"SELECT t.crm_cliente_id FROM {$wpdb->prefix}automatiza_contracts c
		 JOIN {$wpdb->prefix}automatiza_tech_clients t ON t.id = c.client_id WHERE c.id = %d",
		$contrato_id
	));
	return (int) $crm === $crm_id;
}

/**
 * Fecha de inicio para recalcular: la que escribió Luis, o la guardada, o la de defecto (primer lunes hábil
 * después de la firma). Si cae en fin de semana o feriado, se corre al hábil siguiente.
 */
function at_pt_fecha_inicio_elegida(string $pedida, object $fila, array $plan, array $feriados): string {
	$es_fecha = function (string $f): bool {
		return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
	};
	$inicio = trim($pedida);
	if (!$es_fecha($inicio)) {
		$inicio = trim((string) ($fila->fecha_inicio ?? ''));
	}
	if (!$es_fecha($inicio)) {
		$firma = (string) ($plan['fecha_firma'] ?? '');
		return at_pt_inicio_por_defecto($es_fecha($firma) ? $firma : current_time('Y-m-d'), $feriados);
	}
	return at_pt_es_habil($inicio, $feriados) ? $inicio : at_pt_siguiente_habil($inicio, $feriados);
}

/**
 * Texto tal cual llegó, sin tocarlo: '' si no es un valor simple (un arreglo o un objeto no es un texto). Los textos del
 * plan no pasan por sanitize_text_field/sanitize_textarea_field: quitan «<…>» y los «%xx», y dejan «< 50» como «&lt; 50»,
 * con lo que el panel cambiaba lo que escribió la IA y la clave de la actividad (at_pt_marcar_ediciones la marcaba «luis»
 * sin que Luis la tocara). Lo que llega de plan_json ya viene sin barras (se decodifica de wp_unslash); los campos
 * sueltos de $_POST se pasan por wp_unslash() antes. at_pt_validar_plan() limpia los caracteres de control y recorta
 * los largos, y todo lo que se dibuja se escapa (esc_attr, esc_textarea, esc_html).
 */
function at_pt_cadena(mixed $v): string {
	return is_scalar($v) ? (string) $v : '';
}

/**
 * Plan guardado + lo que llegó del panel: las fases que armó plan-trabajo.js (plan_json, con el detalle de cada
 * actividad) y los textos de las láminas (proyecto, qué necesitamos, reuniones, hitos y servicios mensuales).
 * Conserva lo que el panel no edita (image_briefs, fecha_firma, soporte.garantia_meses…): la garantía viene del
 * contrato (D8) y, aunque $textos traiga 'garantia_meses', no se toca. Sin validar: lo valida
 * at_pt_validar_plan() después.
 */
function at_pt_plan_desde_panel(array $anterior, array $editado, array $textos): array {
	$plan = $anterior;
	$fases = [];
	foreach ((array) ($editado['fases'] ?? []) as $f) {
		if (!is_array($f)) {
			continue;
		}
		$bloques = [];
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			if (!is_array($b)) {
				continue;
			}
			$acts = [];
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				if (!is_array($a)) {
					continue;
				}
				$acts[] = [
					'nombre'       => at_pt_cadena($a['nombre'] ?? ''),
					'detalle'      => at_pt_cadena($a['detalle'] ?? ''),
					'responsable'  => sanitize_key((string) ($a['responsable'] ?? '')),
					'dias_habiles' => is_numeric($a['dias_habiles'] ?? null) ? (int) $a['dias_habiles'] : 0,
					'servicio'     => sanitize_key((string) ($a['servicio'] ?? '')),
					'etapa'        => sanitize_key((string) ($a['etapa'] ?? '')),
					'origen'       => sanitize_key((string) ($a['origen'] ?? '')),
					'en_paralelo'  => !empty($a['en_paralelo']),
				];
			}
			$bloques[] = [
				'nombre'      => at_pt_cadena($b['nombre'] ?? ''),
				'entregable'  => at_pt_cadena($b['entregable'] ?? ''),
				'entrega'     => !empty($b['entrega']),
				'actividades' => $acts,
			];
		}
		$fases[] = [
			'clave'       => sanitize_key((string) ($f['clave'] ?? '')),
			'descripcion' => at_pt_cadena($f['descripcion'] ?? ''),
			'bloques'     => $bloques,
		];
	}
	$plan['fases'] = $fases;
	$lineas = function (string $texto): array {
		return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $texto)), 'strlen'));
	};
	if (trim((string) ($textos['proyecto'] ?? '')) !== '') {
		$plan['proyecto'] = trim((string) $textos['proyecto']);
	}
	$plan['necesitamos_de_ti'] = $lineas((string) ($textos['necesitamos_de_ti'] ?? ''));
	$plan['reuniones'] = [];
	foreach ($lineas((string) ($textos['reuniones'] ?? '')) as $l) {
		$p = array_map('trim', explode('|', $l, 2));
		$plan['reuniones'][] = ['nombre' => $p[0], 'detalle' => $p[1] ?? ''];
	}
	$plan['hitos'] = [];
	foreach ($lineas((string) ($textos['hitos'] ?? '')) as $l) {
		$p = array_map('trim', explode('|', $l, 2));
		if ($p[0] !== 'Entrega estimada') {
			$plan['hitos'][] = ['nombre' => $p[0], 'despues_de' => $p[1] ?? ''];
		}
	}
	$soporte = is_array($plan['soporte'] ?? null) ? $plan['soporte'] : ['garantia_meses' => 3, 'mensuales' => []];
	if (array_key_exists('mensuales', $textos)) {
		$soporte['mensuales'] = $lineas((string) $textos['mensuales']);
	}
	$plan['soporte'] = $soporte;
	return $plan;
}

/** «Crear plan de trabajo» (contrato firmado sin plan): crea el plan y pide el borrador a n8n. */
function at_pt_accion_crear(): void {
	$contrato_id = absint($_POST['contrato_id'] ?? 0);
	check_admin_referer('at_pt_crear_' . $contrato_id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$crm_id = absint($_POST['crm_id'] ?? 0);
	$ya = at_pt_plan_de_contrato($contrato_id);
	if ($ya) {
		at_pt_volver($crm_id, (int) $ya->id, 'ya_existe');
	}
	if (!at_pt_contrato_es_del_cliente($contrato_id, $crm_id)) {
		at_pt_volver($crm_id, 0, 'no_se_pudo');
	}
	$id = at_pt_crear_plan($contrato_id);
	if (is_wp_error($id) || (int) $id <= 0) {
		at_pt_volver($crm_id, 0, 'no_se_pudo');
	}
	$id = (int) $id;
	// Si n8n no recibe el aviso, at_pt_iniciar_borrador() (Task 6) deja el plan en «error» con el motivo.
	$motivo = at_pt_iniciar_borrador($id);
	at_pt_volver($crm_id, $id, $motivo === '' ? 'creado' : 'n8n_fallo');
}

/**
 * «Guardar y recalcular» (sin IA), en el orden de uso único de la Task 2: validar lo que llegó → marcar lo que
 * editó Luis → validar otra vez → fechas. Guarda, deja el plan en borrador y pide la vista previa.
 */
function at_pt_accion_guardar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$anterior = at_pt_payload($fila);
	if (empty($anterior['fases']) || !in_array((string) $fila->estado, ['borrador', 'listo', 'error'], true)) {
		at_pt_volver($crm, $id, 'no_editable');
	}
	$editado = json_decode((string) wp_unslash($_POST['plan_json'] ?? ''), true);
	if (!is_array($editado) || !isset($editado['fases']) || !is_array($editado['fases'])) {
		at_pt_volver($crm, $id, 'json_invalido');
	}
	$textos = [
		'proyecto'          => at_pt_cadena(wp_unslash($_POST['proyecto'] ?? '')),
		'necesitamos_de_ti' => at_pt_cadena(wp_unslash($_POST['necesitamos_de_ti'] ?? '')),
		'reuniones'         => at_pt_cadena(wp_unslash($_POST['reuniones'] ?? '')),
		'hitos'             => at_pt_cadena(wp_unslash($_POST['hitos'] ?? '')),
		'mensuales'         => at_pt_cadena(wp_unslash($_POST['mensuales'] ?? '')),
	];
	$v = at_pt_validar_plan(at_pt_plan_desde_panel($anterior, $editado, $textos));
	if (empty($v['ok'])) {
		at_pt_guardar_detalles($id, (array) ($v['errores'] ?? []));
		at_pt_volver($crm, $id, 'invalido');
	}
	$v2 = at_pt_validar_plan(at_pt_marcar_ediciones($anterior, (array) $v['plan'], 'luis'));
	if (empty($v2['ok'])) {
		at_pt_guardar_detalles($id, (array) ($v2['errores'] ?? []));
		at_pt_volver($crm, $id, 'invalido');
	}
	$feriados = at_pt_feriados();
	$plan = (array) $v2['plan'];
	$inicio = at_pt_fecha_inicio_elegida((string) wp_unslash($_POST['fecha_inicio'] ?? ''), $fila, $plan, $feriados);
	$plan = at_pt_calcular_fechas($plan, $inicio, $feriados);
	// Desde «listo» o «error» vuelve a borrador (la versión final hay que aprobarla de nuevo).
	if ((string) $fila->estado !== 'borrador' && !at_pt_cambiar_estado($id, 'borrador', '')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	// La nota vieja (p. ej. «No se pudo pedir la vista previa…») se borra: describe un intento anterior.
	if (!at_pt_guardar($id, ['payload' => $plan, 'fecha_inicio' => $inicio, 'nota' => ''])) {
		at_pt_volver($crm, $id, 'error_guardar');
	}
	at_pt_guardar_detalles($id, (array) ($v['avisos'] ?? []));
	$motivo = at_pt_pedir_render($id, 'draft', false);
	at_pt_volver($crm, $id, $motivo === '' ? 'guardado' : 'guardado_sin_vista');
}

/** «Pedir cambios»: guarda los comentarios y llama al flujo «Plan de trabajo · 2 Cambios» con {id, codigo}. */
function at_pt_accion_cambios(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$comentarios = trim(sanitize_textarea_field(wp_unslash($_POST['comentarios'] ?? '')));
	if ($comentarios === '') {
		at_pt_volver($crm, $id, 'sin_comentarios');
	}
	if (empty(at_pt_payload($fila)['fases']) || !at_pt_transicion_valida((string) $fila->estado, 'cambios')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	if (!at_pt_guardar($id, ['comentarios' => mb_substr($comentarios, 0, 4000)])) {
		at_pt_volver($crm, $id, 'error_guardar');
	}
	if (!at_pt_cambiar_estado($id, 'cambios', '')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_CAMBIOS, ['id' => $id, 'codigo' => (string) $fila->codigo]);
	if ($motivo !== '') {
		at_pt_error_si_sigue($id, 'cambios', 'No se pudo pedir los cambios: ' . $motivo);
	}
	at_pt_volver($crm, $id, $motivo === '' ? 'cambios_pedidos' : 'n8n_fallo');
}

/** «Aprobar»: versión final con fotos (el costo se confirmó en el navegador). */
function at_pt_accion_aprobar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if (empty(at_pt_payload($fila)['fases']) || !at_pt_transicion_valida((string) $fila->estado, 'aprobando')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	if (!at_pt_cambiar_estado($id, 'aprobando', '')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	// Si n8n no recibe el aviso, at_pt_pedir_render() (Task 6) pasa el plan de «aprobando» a «error» con el motivo.
	$motivo = at_pt_pedir_render($id, 'final', true);
	at_pt_volver($crm, $id, $motivo === '' ? 'aprobando' : 'n8n_fallo');
}

/** «Destrabar»: un plan que quedó esperando a n8n pasa a «error» para poder reintentar. */
function at_pt_accion_destrabar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if (!in_array((string) $fila->estado, ['generando', 'cambios', 'aprobando'], true)) {
		at_pt_volver($crm, $id, 'transicion');
	}
	at_pt_cambiar_estado($id, 'error', 'Destrabado por Luis');
	at_pt_volver($crm, $id, 'destrabado');
}

/**
 * Desde «error»: con payload vuelve a borrador sin llamar a nadie; sin payload pide otra vez el borrador
 * (at_pt_iniciar_borrador() lo pasa a «generando» y, si n8n no recibe el aviso, lo deja en «error» con el motivo).
 */
function at_pt_accion_reintentar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if ((string) $fila->estado !== 'error') {
		at_pt_volver($crm, $id, 'transicion');
	}
	if (!empty(at_pt_payload($fila)['fases'])) {
		if (!at_pt_cambiar_estado($id, 'borrador', '')) {
			at_pt_volver($crm, $id, 'transicion');
		}
		at_pt_volver($crm, $id, 'vuelto_borrador');
	}
	$motivo = at_pt_iniciar_borrador($id);
	at_pt_volver($crm, $id, $motivo === '' ? 'reintentando' : 'n8n_fallo');
}

/** «Enviar al cliente» (decisión 5: solo con el clic de Luis): correo con el PDF y el enlace; el plan queda enviado. */
function at_pt_accion_enviar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$r = at_pt_enviar_plan($id);
	if (!$r['ok']) {
		at_pt_volver($crm, $id, $r['motivo']);
	}
	if ($r['sin_pdf'] !== '') {
		at_pt_guardar_detalles($id, ['Motivo: ' . $r['sin_pdf'] . '.']);
		at_pt_volver($crm, $id, 'enviado_sin_pdf');
	}
	at_pt_volver($crm, $id, 'enviado');
}

/** «Enviar por mi WhatsApp»: deja el plan enviado y abre el wa.me del cliente (el formulario va en otra pestaña). */
function at_pt_accion_enviar_whatsapp(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$r = at_pt_marcar_enviado_whatsapp($id);
	if (!$r['ok']) {
		at_pt_volver($crm, $id, $r['motivo']);
	}
	wp_redirect($r['url']); // wa.me no es del sitio: wp_safe_redirect lo cambiaría por el escritorio
	exit;
}

// ---------------------------------------------------------------------------------------------------------------
// «Agendar llamada de seguimiento» (decisión 9): datos para precargar inc/admin-followup-meetings.php (?pt_plan=).
// ---------------------------------------------------------------------------------------------------------------

/**
 * Datos del cliente de un plan para el formulario de «Reuniones de seguimiento»: la ficha del CRM manda y, si le
 * falta algo, lo completan los marcadores del contrato. El tipo es fijo («Seguimiento del plan de trabajo», en el
 * asunto). [] si el plan no existe. Largos recortados a las columnas de wp_automatiza_followup_meetings.
 */
function at_pt_datos_agenda(int $plan_id): array {
	$fila = $plan_id > 0 ? at_pt_plan($plan_id) : null;
	if (!$fila) {
		return [];
	}
	global $wpdb;
	$crm = null;
	$crm_id = at_pt_crm_de_plan($fila); // el enlace actual de la ficha operativa, o el guardado al crear el plan
	if ($crm_id > 0) {
		$crm = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, empresa, telefono FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	}
	$contrato = $wpdb->get_row($wpdb->prepare("SELECT contract_number, placeholders FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", (int) $fila->contrato_id));
	$ph = $contrato ? json_decode((string) $contrato->placeholders, true) : [];
	$ph = is_array($ph) ? $ph : [];
	$primero = function (...$valores): string {
		foreach ($valores as $v) {
			$v = trim(sanitize_text_field((string) $v));
			if ($v !== '') {
				return $v;
			}
		}
		return '';
	};
	$correo = $primero($crm->email ?? '', $ph['email_cliente'] ?? '');
	$proyecto = $primero(at_pt_payload($fila)['proyecto'] ?? '', $ph['nombre_proyecto'] ?? '');
	$numero = $contrato ? trim((string) $contrato->contract_number) : '';
	return [
		'client_name'     => mb_substr($primero($crm->nombre ?? '', $ph['representante_cliente_nombre'] ?? '', $ph['razon_social_cliente'] ?? ''), 0, 100),
		'client_email'    => is_email($correo) ? $correo : '',
		'company_name'    => mb_substr($primero($crm->empresa ?? '', $ph['razon_social_cliente'] ?? ''), 0, 150),
		'phone'           => mb_substr($primero($crm->telefono ?? '', $ph['telefono_cliente'] ?? ''), 0, 30),
		'meeting_subject' => mb_substr('Seguimiento del plan de trabajo' . ($proyecto !== '' ? ' — ' . $proyecto : ''), 0, 255),
		'notes'           => 'Plan de trabajo ' . $fila->codigo . ($numero !== '' ? ' (contrato ' . $numero . ')' : '') . '. Llamada para revisar el plan con el cliente y aclarar sus dudas.',
	];
}
