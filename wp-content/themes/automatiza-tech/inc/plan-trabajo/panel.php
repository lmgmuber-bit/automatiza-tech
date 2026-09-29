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
	foreach ((array) ($pl['hitos'] ?? []) as $h) {
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
			<div class="notice notice-info inline"><p>La IA está trabajando en este plan (<?php echo esc_html(mb_strtolower($etiquetas[$estado])); ?>). Te llegará un correo cuando termine. Si lleva más de 10 minutos, destrábalo.</p>
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

		<?php if ($estado === 'listo'): ?>
			<div class="notice notice-success inline"><p>Versión final lista. Si cambias algo y guardas, el plan vuelve a borrador y hay que aprobarlo de nuevo. El envío al cliente llega en la etapa 2.</p></div>
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
