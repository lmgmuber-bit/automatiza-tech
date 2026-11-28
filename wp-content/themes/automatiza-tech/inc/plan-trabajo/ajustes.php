<?php
/**
 * CRM › Ajustes del plan: tabla de tiempos de referencia (días hábiles por tipo de servicio y etapa) con la que
 * el borrador reparte los días de cada etapa (at_pt_aplicar_tabla). La edita Luis y se guarda normalizada en la
 * opción at_pt_duraciones (JSON). Sin JavaScript: se agrega un tipo llenando una fila vacía y se quita marcando
 * «Quitar». Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md §3.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'at_pt_menu_ajustes', 20);
add_action('admin_post_at_pt_guardar_duraciones', 'at_pt_accion_guardar_duraciones');

/** Submenú bajo «CRM Clientes» (el menú padre lo registra crm-ai-completo.php con prioridad 10). */
function at_pt_menu_ajustes(): void {
	add_submenu_page('automatiza-crm', 'Ajustes del plan de trabajo', 'Ajustes del plan', 'manage_options', 'at-pt-ajustes', 'at_pt_render_ajustes');
}

/** Columnas de días de la tabla, en el orden en que se muestran (las etapas de la tabla, con su etiqueta; Task 2). */
function at_pt_columnas_duracion(): array {
	$etiquetas = at_pt_etapas();
	$columnas = [];
	foreach (at_pt_etapas_tabla() as $etapa) {
		$columnas[$etapa] = (string) ($etiquetas[$etapa] ?? $etapa);
	}
	return $columnas;
}

/** Clave de servicio desde su nombre: «Automatización móvil» → automatizacion_movil (máximo 40 caracteres). */
function at_pt_clave_de_nombre(string $nombre): string {
	$c = str_replace('-', '_', sanitize_title($nombre));
	$c = (string) preg_replace('/[^a-z0-9_]/', '', $c);
	return substr(trim($c, '_'), 0, 40);
}

/**
 * Filas del formulario → tabla (clave => nombre y días). Ignora las filas vacías y las marcadas «Quitar»;
 * descarta y cuenta las que traen algo inválido (clave fuera de [a-z0-9_]{2,40}, nombre vacío o de más de 60
 * caracteres, días que no son un entero de 0 a 60) o una clave repetida. Un día en blanco vale 0. El resultado
 * pasa además por at_pt_normalizar_duraciones() (Task 2), que es la regla final.
 *
 * @return array{tabla: array, descartadas: int}
 */
function at_pt_duraciones_de_filas(array $filas): array {
	$tabla = [];
	$descartadas = 0;
	foreach ($filas as $f) {
		if (!is_array($f) || !empty($f['quitar'])) {
			continue;
		}
		$nombre = trim(sanitize_text_field((string) ($f['nombre'] ?? '')));
		$clave = trim((string) ($f['clave'] ?? ''));
		$dias = [];
		$vacia = $nombre === '' && $clave === '';
		foreach (at_pt_columnas_duracion() as $k => $_) {
			$v = trim((string) ($f[$k] ?? ''));
			if ($v !== '') {
				$vacia = false;
			}
			$dias[$k] = $v === '' ? '0' : $v;
		}
		if ($vacia) {
			continue;
		}
		if ($clave === '') {
			$clave = at_pt_clave_de_nombre($nombre);
		}
		$valida = (bool) preg_match('/^[a-z0-9_]{2,40}$/', $clave) && $nombre !== '' && mb_strlen($nombre) <= 60 && !isset($tabla[$clave]);
		foreach ($dias as $v) {
			if (!preg_match('/^\d{1,2}$/', $v) || (int) $v > 60) {
				$valida = false;
			}
		}
		if (!$valida) {
			$descartadas++;
			continue;
		}
		$tabla[$clave] = ['nombre' => $nombre] + array_map('intval', $dias);
	}
	$normal = at_pt_normalizar_duraciones($tabla);
	return ['tabla' => $normal, 'descartadas' => $descartadas + count($tabla) - count($normal)];
}

/** Guarda la tabla (o restaura la propuesta) y vuelve a los ajustes con un aviso. Siempre termina la petición. */
function at_pt_accion_guardar_duraciones(): void {
	check_admin_referer('at_pt_guardar_duraciones');
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$url = admin_url('admin.php?page=at-pt-ajustes');
	if (!empty($_POST['restaurar'])) {
		delete_option('at_pt_duraciones');
		wp_safe_redirect(add_query_arg('pt_msg', 'restaurada', $url));
		exit;
	}
	$filas = isset($_POST['filas']) && is_array($_POST['filas']) ? wp_unslash($_POST['filas']) : [];
	$r = at_pt_duraciones_de_filas($filas);
	if (!$r['tabla']) {
		wp_safe_redirect(add_query_arg('pt_msg', 'vacia', $url));
		exit;
	}
	update_option('at_pt_duraciones', wp_json_encode($r['tabla'], JSON_UNESCAPED_UNICODE), false);
	wp_safe_redirect(add_query_arg(['pt_msg' => 'guardada', 'pt_descartadas' => (int) $r['descartadas']], $url));
	exit;
}

/** Página «Ajustes del plan». */
function at_pt_render_ajustes(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.');
	}
	$avisos = [
		'guardada'   => ['notice-success', 'Tabla guardada. Se usa en los borradores nuevos (al crear un plan o con «Reintentar borrador»); «Pedir cambios» y «Guardar y recalcular» no la vuelven a aplicar, y los días que editaste a mano nunca se tocan.'],
		'vacia'      => ['notice-error', 'La tabla quedó sin filas válidas: no se guardó nada.'],
		'restaurada' => ['notice-success', 'Se restauró la tabla propuesta.'],
	];
	$msg = sanitize_key(wp_unslash($_GET['pt_msg'] ?? ''));
	$descartadas = absint($_GET['pt_descartadas'] ?? 0);
	$filas = [];
	foreach (at_pt_duraciones() as $clave => $t) {
		$filas[] = ['clave' => (string) $clave] + $t;
	}
	$vacia = ['clave' => '', 'nombre' => ''] + array_fill_keys(array_keys(at_pt_columnas_duracion()), '');
	$filas[] = $vacia;
	$filas[] = $vacia;
	$nonce = wp_create_nonce('at_pt_guardar_duraciones');
	$accion = admin_url('admin-post.php');
	?>
	<div class="wrap at-pt-ajustes">
		<h1>Ajustes del plan de trabajo</h1>
		<?php if (isset($avisos[$msg])): ?>
			<div class="notice <?php echo esc_attr($avisos[$msg][0]); ?> is-dismissible"><p><?php echo esc_html($avisos[$msg][1]); ?></p>
			<?php if ($descartadas > 0): ?><p><?php echo esc_html(sprintf('%d fila(s) no se guardaron por tener datos inválidos o una clave repetida.', $descartadas)); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>
		<p>Días hábiles de referencia por tipo de servicio. Con esta tabla el borrador del plan reparte los días de cada etapa entre sus actividades; lo que no calza con la tabla queda como estimación de la IA para que la revises.</p>
		<p>Se suman aparte el arranque (reunión de inicio, 1 día, y entrega de logo, textos y accesos, 3 días; cláusula 4.2 del contrato) y la revisión del cliente (5 días hábiles después de cada entrega; cláusula 6.1).</p>
		<form method="post" action="<?php echo esc_url($accion); ?>">
			<input type="hidden" name="action" value="at_pt_guardar_duraciones">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
			<div style="overflow-x:auto">
			<table class="widefat striped at-pt-duraciones">
				<thead><tr><th scope="col">Tipo de servicio</th><th scope="col">Clave</th>
				<?php foreach (at_pt_columnas_duracion() as $titulo): ?><th scope="col"><?php echo esc_html($titulo); ?></th><?php endforeach; ?>
				<th scope="col">Suma</th><th scope="col">Quitar</th></tr></thead>
				<tbody>
				<?php foreach ($filas as $i => $f): $suma = 0; ?>
					<tr>
						<td><input type="text" class="regular-text" name="filas[<?php echo (int) $i; ?>][nombre]" value="<?php echo esc_attr((string) $f['nombre']); ?>" maxlength="60" aria-label="Tipo de servicio"></td>
						<td><input type="text" class="regular-text" name="filas[<?php echo (int) $i; ?>][clave]" value="<?php echo esc_attr((string) $f['clave']); ?>" maxlength="40" pattern="[a-z0-9_]{2,40}" aria-label="Clave" placeholder="se arma del nombre"></td>
						<?php foreach (at_pt_columnas_duracion() as $k => $titulo): $suma += (int) $f[$k]; ?>
						<td><input type="number" class="small-text" min="0" max="60" step="1" name="filas[<?php echo (int) $i; ?>][<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr((string) $f[$k]); ?>" aria-label="<?php echo esc_attr($titulo); ?>"></td>
						<?php endforeach; ?>
						<td><?php echo $f['nombre'] !== '' ? (int) $suma : ''; ?></td>
						<td><input type="checkbox" name="filas[<?php echo (int) $i; ?>][quitar]" value="1" aria-label="Quitar esta fila"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p class="description">Para agregar un tipo de servicio, llena una fila vacía (la clave se arma sola desde el nombre). La IA elige el servicio de cada actividad por su clave: si cambias una clave, los planes nuevos dejan de reconocer la anterior.</p>
			<p class="submit"><button type="submit" class="button button-primary">Guardar tabla</button></p>
		</form>
		<form method="post" action="<?php echo esc_url($accion); ?>" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('¿Volver a la tabla propuesta? Se pierden tus cambios en esta tabla.', JSON_UNESCAPED_UNICODE)); ?>);">
			<input type="hidden" name="action" value="at_pt_guardar_duraciones">
			<input type="hidden" name="restaurar" value="1">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
			<p><button type="submit" class="button">Restaurar la tabla propuesta</button></p>
		</form>
	</div>
	<?php
}
