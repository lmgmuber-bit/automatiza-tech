<?php
/** Finanzas AT: panel en wp-admin. Pestañas «Pagos por mes» y «Cuentas» de la página at-finanzas (la de «Suscripciones» es el catálogo de siempre). */

if (!defined('ABSPATH')) exit;

/* ---------- Textos y formatos ---------- */

/** Categorías del catálogo de suscripciones (clave guardada => nombre legible). */
function at_fin_categorias_catalogo(): array {
	return [
		'hosting' => 'Hosting', 'infraestructura' => 'Infraestructura', 'dominios' => 'Dominios',
		'apis_ia' => 'APIs / IA', 'mensajeria' => 'Mensajería (Meta/YCloud)', 'marketing' => 'Marketing',
		'herramientas' => 'Herramientas', 'otros' => 'Otros',
	];
}

/** Nombre legible de una categoría: las del catálogo con su nombre; las demás, tal cual. */
function at_fin_categoria_texto(string $categoria): string {
	return at_fin_categorias_catalogo()[$categoria] ?? $categoria;
}

/** Pesos chilenos sin decimales y con punto de miles: $1.234.567 (negativos: -$1.500). */
function at_fin_fmt_clp($n): string {
	$r = (int) round((float) $n);
	return ($r < 0 ? '-' : '') . '$' . number_format(abs($r), 0, ',', '.');
}

/** Dólares con 2 decimales: US$12,50 (negativos: -US$3,00). */
function at_fin_fmt_usd($n): string {
	$r = round((float) $n, 2);
	return ($r < 0 ? '-' : '') . 'US$' . number_format(abs($r), 2, ',', '.');
}

function at_fin_fmt_monto($n, string $moneda): string {
	return $moneda === 'USD' ? at_fin_fmt_usd($n) : at_fin_fmt_clp($n);
}

/** Monto tal como se interpretó, sin redondear a lo que se ve en las tablas: $15.990, $1,50 (un punto cuenta como decimal) o US$1,26. */
function at_fin_fmt_exacto($n, string $moneda): string {
	$n = (float) $n;
	[$entero, $decimales] = explode(',', number_format(round(abs($n), 4), 4, ',', '.'));
	$decimales = rtrim($decimales, '0');
	$decimales = str_pad($decimales, $moneda === 'USD' || $decimales !== '' ? 2 : 0, '0');
	return ($n < 0 ? '-' : '') . ($moneda === 'USD' ? 'US$' : '$') . $entero . ($decimales !== '' ? ',' . $decimales : '');
}

/** Pesos por dólar con 2 decimales: $950,00. */
function at_fin_fmt_tc($tc): string {
	return '$' . number_format((float) $tc, 2, ',', '.');
}

/** Porcentaje con una decimal y coma, sin «,0» sobrante: 12,5 / 40. */
function at_fin_fmt_pct($p): string {
	return preg_replace('/,0$/', '', number_format((float) $p, 1, ',', '.'));
}

/** 'AAAA-MM-DD' → 'DD-MM-AAAA'. */
function at_fin_fecha_es(string $ymd): string {
	return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $ymd, $m) === 1 ? "{$m[3]}-{$m[2]}-{$m[1]}" : $ymd;
}

/** 'AAAA-MM' → 'octubre 2026'. */
function at_fin_mes_es(string $ym): string {
	$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	return preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/D', $ym, $m) === 1 ? $meses[(int) $m[2]] . ' ' . $m[1] : $ym;
}

function at_fin_tipo_texto(string $tipo): string {
	return ['pago' => 'Pago', 'recarga' => 'Recarga', 'consumo' => 'Consumo', 'ajuste' => 'Ajuste'][$tipo] ?? $tipo;
}

/* ---------- Pestañas ---------- */

function at_fin_panel_pestanas(): array {
	return ['suscripciones' => 'Suscripciones', 'pagos' => 'Pagos por mes', 'cuentas' => 'Cuentas'];
}

/** Pestaña pedida en `?tab=`: una de las tres, y si no, «suscripciones». */
function at_fin_panel_tab_actual($valor): string {
	return is_string($valor) && isset(at_fin_panel_pestanas()[$valor]) ? $valor : 'suscripciones';
}

function at_fin_panel_url(string $tab = 'suscripciones', array $extra = []): string {
	$args = ['page' => 'at-finanzas'] + ($tab !== 'suscripciones' ? ['tab' => $tab] : []) + $extra;
	return admin_url('admin.php?' . http_build_query($args, '', '&'));
}

/** Barra de pestañas bajo el título. */
function at_fin_panel_tabs(string $activa): void {
	echo '<nav class="nav-tab-wrapper" aria-label="Secciones de Finanzas AT">';
	foreach (at_fin_panel_pestanas() as $slug => $nombre) {
		$es = $slug === $activa;
		echo '<a href="' . esc_url(at_fin_panel_url($slug)) . '" class="nav-tab' . ($es ? ' nav-tab-active' : '') . '"' . ($es ? ' aria-current="page"' : '') . '>' . esc_html($nombre) . '</a>';
	}
	echo '</nav>';
}

/** Campo oculto de seguridad de los formularios del panel (acción `at_fin_panel`), sin el id repetido. */
function at_fin_panel_nonce(): string {
	return (string) preg_replace('/ id="[^"]*"/', '', wp_nonce_field('at_fin_panel', '_wpnonce', false, false));
}

function at_fin_panel_estilos(): void {
	?>
	<style>
		.at-fin-panel .at-fin-negativo{color:#b32d2e}
		.at-fin-panel .at-fin-tarjetas{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}
		.at-fin-panel .at-fin-tarjeta{flex:1;min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px}
		.at-fin-panel .at-fin-etiqueta{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#646970;font-weight:600}
		.at-fin-panel .at-fin-cifra{font-size:22px;font-weight:700;color:#1d2327;margin-top:4px;word-break:break-word}
		.at-fin-panel .at-fin-columnas{display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start}
		.at-fin-panel .at-fin-columnas>div{flex:1;min-width:min(100%,280px)}
		.at-fin-panel .at-fin-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%}
		.at-fin-panel .at-fin-scroll table{width:100%}
		.at-fin-panel .at-fin-scroll table.at-fin-tabla-ancha{min-width:760px}
		.at-fin-panel .at-fin-der{text-align:right;white-space:nowrap}
		.at-fin-panel .at-fin-nosuma{color:#646970}
		.at-fin-panel .at-fin-pendiente{display:inline-block;background:#fcf9e8;border:1px solid #dba617;border-radius:999px;padding:0 8px;font-size:11px;color:#674e00}
		.at-fin-panel .at-fin-mes{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}
		.at-fin-panel .at-fin-barra{display:block;background:#f0f0f1;border-radius:4px;height:12px;min-width:80px}
		.at-fin-panel .at-fin-barra i{display:block;background:#2271b1;border-radius:4px;height:12px}
		.at-fin-panel .at-fin-cuentas{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:14px;margin:16px 0}
		.at-fin-panel .at-fin-cuenta{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px}
		.at-fin-panel .at-fin-cuenta.at-fin-inactiva{opacity:.65}
		.at-fin-panel .at-fin-saldo{font-size:26px;font-weight:700;color:#1d2327;margin:6px 0}
		.at-fin-panel .at-fin-saldo.at-fin-negativo{color:#b32d2e}
		.at-fin-panel .at-fin-medidor{background:#f0f0f1;border-radius:6px;height:14px;overflow:hidden;margin:8px 0 4px}
		.at-fin-panel .at-fin-medidor span{display:block;height:14px;border-radius:6px;background:#00a32a}
		.at-fin-panel .at-fin-medidor.at-fin-alerta span{background:#dba617}
		.at-fin-panel .at-fin-medidor.at-fin-critico span{background:#d63638}
		.at-fin-panel .at-fin-nota{color:#646970;font-size:12px;margin:2px 0 8px}
		.at-fin-panel form.at-fin-form label{display:block;font-weight:600;margin:8px 0 2px}
		.at-fin-panel form.at-fin-form .at-fin-fila{display:flex;flex-wrap:wrap;gap:12px}
		.at-fin-panel form.at-fin-form .at-fin-fila>div{flex:1;min-width:min(100%,160px)}
		.at-fin-panel form.at-fin-form input[type=text],.at-fin-panel form.at-fin-form input[type=date],.at-fin-panel form.at-fin-form select{width:100%;max-width:100%;box-sizing:border-box}
		.at-fin-panel .at-fin-cuadrar{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-top:10px}
		.at-fin-panel .at-fin-cuadrar input[type=text]{width:130px}
		.at-fin-panel details.at-fin-registro{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;margin:16px 0}
		.at-fin-panel details>summary{cursor:pointer;font-weight:600}
	</style>
	<?php
}

/* ---------- Acciones (registrar y cuadrar) ---------- */

/**
 * Ejecuta una acción del panel: 'registrar' (un movimiento) o 'cuadrar' (ajuste para que el saldo estimado sea el real).
 * No revisa permisos ni nonce: eso lo hace at_fin_panel_procesar(), que es quien lee el formulario.
 *
 * @return array{ok:bool,mensaje:string}
 */
function at_fin_panel_accion(array $post): array {
	$accion = is_scalar($post['accion'] ?? null) ? (string) $post['accion'] : '';
	if ($accion === 'registrar') { return at_fin_panel_registrar($post); }
	if ($accion === 'cuadrar') { return at_fin_panel_cuadrar($post); }
	return ['ok' => false, 'mensaje' => 'Acción no reconocida.'];
}

/** Lee el formulario del panel (POST) con nonce y permisos. Null si no hay nada del panel que procesar. */
function at_fin_panel_procesar(): ?array {
	if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') { return null; }
	$accion = isset($_POST['accion']) && is_string($_POST['accion']) ? (string) wp_unslash($_POST['accion']) : '';
	if (!in_array($accion, ['registrar', 'cuadrar'], true)) { return null; }
	if (!current_user_can('manage_options')) { return ['ok' => false, 'mensaje' => 'No tienes permisos para esta acción.']; }
	$nonce = isset($_POST['_wpnonce']) && is_string($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
	if (!wp_verify_nonce($nonce, 'at_fin_panel')) {
		return ['ok' => false, 'mensaje' => 'El formulario venció o no es válido: recarga la página e inténtalo de nuevo. No se guardó nada.'];
	}
	return at_fin_panel_accion((array) wp_unslash($_POST));
}

function at_fin_panel_registrar(array $post): array {
	$campos = [];
	foreach (['tipo', 'fecha', 'cuenta', 'proveedor', 'categoria', 'descripcion', 'proyecto', 'monto', 'moneda', 'tipo_cambio', 'notas'] as $k) {
		if (array_key_exists($k, $post)) { $campos[$k] = $post[$k]; }
	}
	if (at_fin_texto($campos['fecha'] ?? '', 10) === '') { $campos['fecha'] = at_fin_hoy(); }
	$campos['registrado_por'] = 'panel';
	$res = at_fin_registrar($campos, 'panel');
	if (is_wp_error($res)) {
		return ['ok' => false, 'mensaje' => 'No se registró: ' . $res->get_error_message()];
	}
	$moneda = strtoupper(at_fin_texto($campos['moneda'] ?? '', 3));
	$resumen = strtolower(at_fin_texto($campos['tipo'] ?? '', 20)) . ' de ' . at_fin_fmt_exacto(at_fin_monto($campos['monto'] ?? null) ?? 0.0, $moneda);
	$proveedor = at_fin_texto($campos['proveedor'] ?? '', 120);
	$detalle = ($proveedor !== '' ? ' · ' . $proveedor : '') . ' · ' . at_fin_fecha_es(at_fin_texto($campos['fecha'], 10));
	if ($res['estado'] === 'existente') {
		return ['ok' => true, 'mensaje' => 'Ese movimiento ya estaba registrado (' . $resumen . $detalle . '): no se duplicó.'];
	}
	$mensaje = 'Registrado: ' . $resumen . $detalle . '.';
	$fila = at_fin_movimiento($res['id']);
	if ($fila && $moneda === 'USD') {
		if ($fila['tc_estado'] === 'pendiente') {
			$mensaje .= ' Se guardó sin el valor en pesos; se completará solo cuando la fuente del dólar responda.';
		} else {
			$mensaje .= ' Al dólar de ' . at_fin_fmt_tc($fila['tipo_cambio']) . ' son ' . at_fin_fmt_clp($fila['monto_clp']) . '.';
		}
	}
	return ['ok' => true, 'mensaje' => $mensaje];
}

/** Id del último movimiento de una cuenta (0 si no tiene): el formulario de cuadre lo manda como `base` para detectar que la cuenta cambió. */
function at_fin_panel_base_cuenta(int $cuenta_id): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare('SELECT COALESCE(MAX(id), 0) FROM ' . at_fin_tabla_movimientos() . ' WHERE cuenta_id = %d', $cuenta_id));
}

function at_fin_panel_cuadrar(array $post): array {
	$slug = strtolower(at_fin_texto($post['cuenta'] ?? '', 60));
	$cuenta = $slug === '' ? null : at_fin_cuenta($slug);
	if ($cuenta === null) { return ['ok' => false, 'mensaje' => 'La cuenta indicada no existe.']; }
	$real = at_fin_monto($post['saldo_real'] ?? null);
	if ($real === null) { return ['ok' => false, 'mensaje' => 'El saldo real debe ser un número (por ejemplo 30 o 30,50).']; }
	// Si la cuenta tuvo movimientos desde que se abrió la página, el saldo que vio la persona ya no vale: no se cuadra a ciegas.
	$base_actual = at_fin_panel_base_cuenta($cuenta['id']);
	$base = array_key_exists('base', $post) ? (is_scalar($post['base']) ? (int) $post['base'] : -1) : $base_actual;
	if ($base !== $base_actual) {
		return ['ok' => false, 'nivel' => 'warning', 'mensaje' => 'La cuenta cambió desde que abriste la página. Revisa el saldo y vuelve a cuadrar.'];
	}
	$estimado = at_fin_saldo(at_fin_movimientos_cuenta($cuenta['id'], 1000000))['saldo'];
	$diferencia = round($real - $estimado, 4);
	$moneda = $cuenta['moneda'];
	$nombre = $cuenta['nombre'];
	if (abs($diferencia) < 0.00005) {
		return ['ok' => true, 'mensaje' => 'La cuenta «' . $nombre . '» ya coincide: el saldo estimado es ' . at_fin_fmt_exacto($estimado, $moneda) . '. No se registró ningún ajuste.'];
	}
	$real_texto = rtrim(rtrim(number_format($real, 4, '.', ''), '0'), '.');
	$res = at_fin_registrar([
		'tipo' => 'ajuste', 'fecha' => at_fin_hoy(), 'cuenta' => $cuenta['slug'], 'proveedor' => $cuenta['proveedor'],
		'descripcion' => 'Cuadre de saldo (panel)', 'monto' => $diferencia, 'moneda' => $moneda,
		// Determinista (cuenta + último movimiento visto + saldo real): dos envíos iguales a la vez chocan en el UNIQUE y el segundo devuelve el mismo ajuste.
		// Un cuadre posterior parte de otro último movimiento (el ajuste mismo), así que no se confunde con este.
		'referencia' => 'cuadre-' . $cuenta['slug'] . '-' . $base . '-' . $real_texto,
		'notas' => 'saldo real del panel: ' . $real_texto,
		'registrado_por' => 'panel',
	], 'panel');
	if (is_wp_error($res)) { return ['ok' => false, 'mensaje' => 'No se pudo cuadrar: ' . $res->get_error_message()]; }
	if ($res['estado'] === 'existente') {
		return ['ok' => true, 'mensaje' => 'Ese cuadre de «' . $nombre . '» ya estaba registrado: no se duplicó.'];
	}
	return ['ok' => true, 'mensaje' => 'Cuenta «' . $nombre . '» cuadrada: el saldo estimado era ' . at_fin_fmt_exacto($estimado, $moneda) . ' y el real es ' . at_fin_fmt_exacto($real, $moneda) . '; ajuste de ' . at_fin_fmt_exacto($diferencia, $moneda) . '.'];
}

/**
 * Registra como pago real la renovación confirmada de un gasto del catálogo (fila de OmniATFinanceController, leída antes de renovar).
 * La referencia `renov-<id>-<Y-m>` hace que repetir el clic no duplique el pago.
 *
 * @return array|WP_Error lo mismo que at_fin_registrar()
 */
function at_fin_registrar_renovacion($gasto) {
	$hoy = at_fin_hoy();
	return at_fin_registrar([
		'tipo' => 'pago', 'fecha' => $hoy, 'proveedor' => (string) $gasto->provider, 'categoria' => (string) $gasto->category,
		'descripcion' => $gasto->period === 'annual' ? 'Renovación anual' : 'Renovación mensual',
		'monto' => $gasto->amount, 'moneda' => $gasto->currency, 'gasto_id' => (int) $gasto->id,
		'referencia' => 'renov-' . (int) $gasto->id . '-' . substr($hoy, 0, 7), 'registrado_por' => 'panel',
	], 'panel');
}

/* ---------- Página ---------- */

/** Pestañas «Pagos por mes» y «Cuentas» completas: título, pestañas, resultado de la acción enviada y contenido. */
function at_fin_panel_pagina(string $tab): void {
	if (!current_user_can('manage_options')) { wp_die('Sin permisos'); }
	$resultado = at_fin_panel_procesar();
	echo '<div class="wrap at-fin-panel"><h1 class="wp-heading-inline">💰 Finanzas AT</h1>';
	at_fin_panel_tabs($tab);
	at_fin_panel_estilos();
	if ($resultado !== null) {
		echo '<div class="notice ' . ($resultado['ok'] ? 'notice-success' : (($resultado['nivel'] ?? '') === 'warning' ? 'notice-warning' : 'notice-error')) . '"><p>' . esc_html($resultado['mensaje']) . '</p></div>';
	}
	if ($tab === 'cuentas') {
		at_fin_panel_cuentas();
	} else {
		at_fin_panel_pagos(isset($_GET['mes']) && is_string($_GET['mes']) ? $_GET['mes'] : '');
	}
	echo '</div>';
}

/** Pestaña «Pagos por mes». `$mes` es 'AAAA-MM' (si no es válido, el mes actual de Chile). */
function at_fin_panel_pagos(string $mes): void {
	if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $mes) !== 1) { $mes = substr(at_fin_hoy(), 0, 7); }
	// Al abrir la pestaña se completan los dólares que no se pudieron obtener antes (un fallo no debe romper la página).
	try { at_fin_completar_pendientes(5); } catch (Throwable $e) { /* se ignora a propósito */ }

	$movs = at_fin_movimientos_mes($mes);
	$resumen = at_fin_resumen_mes($movs);
	$comparacion = at_fin_comparacion($mes);
	$comparacion[] = ['mes' => $mes, 'total_clp' => $resumen['total_clp']];
	$primero = new DateTimeImmutable($mes . '-01');
	$anterior = $primero->modify('-1 month')->format('Y-m');
	$siguiente = $primero->modify('+1 month')->format('Y-m');
	// Solo los pagos y recargas en dólares esperan un valor en pesos que falta en el total (un consumo pendiente no lo cambia).
	$esperan = 0;
	foreach ($movs as $m) {
		if ($m['tc_estado'] === 'pendiente' && in_array($m['tipo'], ['pago', 'recarga'], true)) { $esperan++; }
	}
	arsort($resumen['por_categoria']);
	arsort($resumen['por_proveedor']);
	$cuentas = at_fin_cuentas();
	?>
	<div class="at-fin-mes">
		<a class="button" href="<?php echo esc_url(at_fin_panel_url('pagos', ['mes' => $anterior])); ?>">← <?php echo esc_html(at_fin_mes_es($anterior)); ?></a>
		<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex;gap:6px;align-items:center">
			<input type="hidden" name="page" value="at-finanzas">
			<input type="hidden" name="tab" value="pagos">
			<label class="screen-reader-text" for="at-fin-mes">Mes</label>
			<input type="month" id="at-fin-mes" name="mes" value="<?php echo esc_attr($mes); ?>">
			<button class="button" type="submit">Ver</button>
		</form>
		<a class="button" href="<?php echo esc_url(at_fin_panel_url('pagos', ['mes' => $siguiente])); ?>"><?php echo esc_html(at_fin_mes_es($siguiente)); ?> →</a>
	</div>

	<div class="at-fin-tarjetas">
		<div class="at-fin-tarjeta"><div class="at-fin-etiqueta">Gasto de <?php echo esc_html(at_fin_mes_es($mes)); ?></div><div class="at-fin-cifra"><?php echo esc_html(at_fin_fmt_clp($resumen['total_clp'])); ?></div></div>
		<div class="at-fin-tarjeta"><div class="at-fin-etiqueta">Movimientos del mes</div><div class="at-fin-cifra"><?php echo esc_html((string) count($movs)); ?></div></div>
	</div>
	<p class="at-fin-nota">Suman los pagos y las recargas; el consumo de una cuenta y los ajustes se muestran pero no suman (el consumo ya se contó al recargar).</p>
	<?php if ($esperan > 0): ?>
	<div class="notice notice-warning inline"><p><?php echo esc_html($esperan . ($esperan === 1 ? ' pago o recarga en dólares espera su valor en pesos' : ' pagos o recargas en dólares esperan su valor en pesos') . ' (marcados «pendiente»): todavía no suman al total. Se reintenta cada vez que abres esta pestaña.'); ?></p></div>
	<?php endif; ?>

	<?php if ($movs === []): ?>
	<p><strong>Mes sin datos:</strong> no hay movimientos registrados en <?php echo esc_html(at_fin_mes_es($mes)); ?>.</p>
	<?php else: ?>
	<div class="at-fin-columnas">
		<div>
			<h2>Por categoría</h2>
			<div class="at-fin-scroll"><table class="widefat striped"><tbody>
				<?php foreach ($resumen['por_categoria'] as $nombre => $total): ?>
				<tr><td><?php echo esc_html(at_fin_categoria_texto((string) $nombre)); ?></td><td class="at-fin-der"><?php echo esc_html(at_fin_fmt_clp($total)); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table></div>
		</div>
		<div>
			<h2>Por proveedor</h2>
			<div class="at-fin-scroll"><table class="widefat striped"><tbody>
				<?php foreach ($resumen['por_proveedor'] as $nombre => $total): ?>
				<tr><td><?php echo esc_html((string) $nombre); ?></td><td class="at-fin-der"><?php echo esc_html(at_fin_fmt_clp($total)); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table></div>
		</div>
	</div>
	<?php endif; ?>

	<h2>Comparación con los 6 meses anteriores</h2>
	<?php $maximo = max(1, ...array_column($comparacion, 'total_clp')); ?>
	<div class="at-fin-scroll"><table class="widefat striped"><tbody>
		<?php foreach ($comparacion as $fila): ?>
		<tr<?php echo $fila['mes'] === $mes ? ' style="font-weight:700"' : ''; ?>>
			<td style="white-space:nowrap"><?php echo esc_html(at_fin_mes_es($fila['mes'])); ?></td>
			<td class="at-fin-der"><?php echo esc_html(at_fin_fmt_clp($fila['total_clp'])); ?></td>
			<td style="width:45%"><span class="at-fin-barra"><i style="width:<?php echo (int) round(max(0, $fila['total_clp']) / $maximo * 100); ?>%"></i></span></td>
		</tr>
		<?php endforeach; ?>
	</tbody></table></div>

	<?php if ($movs !== []): ?>
	<h2>Movimientos de <?php echo esc_html(at_fin_mes_es($mes)); ?></h2>
	<div class="at-fin-scroll"><table class="widefat striped at-fin-tabla-ancha">
		<thead><tr><th>Fecha</th><th>Tipo</th><th>Proveedor</th><th>Categoría</th><th>Descripción</th><th>Cuenta</th><th class="at-fin-der">Monto</th><th class="at-fin-der">Dólar</th><th class="at-fin-der">En pesos</th></tr></thead>
		<tbody>
		<?php foreach ($movs as $m): $suma = in_array($m['tipo'], ['pago', 'recarga'], true); ?>
			<tr<?php echo $suma ? '' : ' class="at-fin-nosuma" title="No suma al gasto del mes"'; ?>>
				<td style="white-space:nowrap"><?php echo esc_html(at_fin_fecha_es((string) $m['fecha'])); ?></td>
				<td><?php echo esc_html(at_fin_tipo_texto((string) $m['tipo'])); ?></td>
				<td><?php echo esc_html((string) $m['proveedor']); ?></td>
				<td><?php echo esc_html(at_fin_categoria_texto((string) $m['categoria'])); ?></td>
				<td><?php echo esc_html((string) $m['descripcion']); ?></td>
				<td><?php echo esc_html((string) ($m['cuenta'] ?? '')); ?></td>
				<td class="at-fin-der"><?php echo esc_html(at_fin_fmt_monto($m['monto'], (string) $m['moneda'])); ?></td>
				<td class="at-fin-der"><?php echo $m['moneda'] === 'USD' && $m['tipo_cambio'] !== null ? esc_html(at_fin_fmt_tc($m['tipo_cambio'])) . ($m['tc_estado'] === 'manual' ? ' <small>(manual)</small>' : '') : '—'; ?></td>
				<td class="at-fin-der"><?php echo $m['tc_estado'] === 'pendiente' ? '<span class="at-fin-pendiente">pendiente</span>' : esc_html(at_fin_fmt_clp($m['monto_clp'])); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div>
	<?php endif; ?>

	<details class="at-fin-registro">
		<summary>➕ Registrar un movimiento a mano</summary>
		<form method="post" class="at-fin-form" action="<?php echo esc_url(at_fin_panel_url('pagos', ['mes' => $mes])); ?>">
			<?php echo at_fin_panel_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="hidden" name="accion" value="registrar">
			<div class="at-fin-fila">
				<div><label for="at-fin-tipo">Tipo</label>
					<select id="at-fin-tipo" name="tipo">
						<option value="pago">Pago (gasto directo)</option>
						<option value="recarga">Recarga a una cuenta</option>
						<option value="consumo">Consumo de una cuenta</option>
						<option value="ajuste">Ajuste de una cuenta</option>
					</select></div>
				<div><label for="at-fin-fecha">Fecha</label><input type="date" id="at-fin-fecha" name="fecha" value="<?php echo esc_attr(at_fin_hoy()); ?>" max="<?php echo esc_attr(at_fin_hoy()); ?>"></div>
				<div><label for="at-fin-cuenta">Cuenta (solo recarga, consumo y ajuste)</label>
					<select id="at-fin-cuenta" name="cuenta">
						<option value="">— ninguna —</option>
						<?php foreach ($cuentas as $c): ?>
						<option value="<?php echo esc_attr($c['slug']); ?>"><?php echo esc_html($c['nombre'] . ' (' . $c['moneda'] . ')'); ?></option>
						<?php endforeach; ?>
					</select></div>
			</div>
			<div class="at-fin-fila">
				<div><label for="at-fin-proveedor">Proveedor</label><input type="text" id="at-fin-proveedor" name="proveedor" maxlength="120" placeholder="Higgsfield, Hostinger…"></div>
				<div><label for="at-fin-categoria">Categoría</label><input type="text" id="at-fin-categoria" name="categoria" maxlength="60" list="at-fin-categorias" placeholder="IA, hosting…">
					<datalist id="at-fin-categorias"><?php foreach (at_fin_categorias_catalogo() as $clave => $nombre): ?><option value="<?php echo esc_attr($clave); ?>"><?php echo esc_html($nombre); ?></option><?php endforeach; ?></datalist></div>
				<div><label for="at-fin-descripcion">Descripción</label><input type="text" id="at-fin-descripcion" name="descripcion" maxlength="255"></div>
			</div>
			<div class="at-fin-fila">
				<div><label for="at-fin-monto">Monto</label><input type="text" id="at-fin-monto" name="monto" inputmode="decimal" required placeholder="12,50">
					<p class="at-fin-nota">Usa coma para los decimales (1,26). Ojo: un punto también cuenta como decimal, así que «1.500» es 1,5 y no mil quinientos. Al guardar te mostramos cómo se entendió.</p></div>
				<div><label for="at-fin-moneda">Moneda</label><select id="at-fin-moneda" name="moneda"><option value="CLP">CLP (pesos)</option><option value="USD">USD (dólares)</option></select></div>
				<div><label for="at-fin-tc">Dólar (opcional)</label><input type="text" id="at-fin-tc" name="tipo_cambio" inputmode="decimal" placeholder="el del día">
					<p class="at-fin-nota">Solo si quieres fijar un valor distinto al dólar observado de esa fecha.</p></div>
			</div>
			<button type="submit" class="button button-primary">Registrar movimiento</button>
		</form>
	</details>
	<?php
}

/** Pestaña «Cuentas»: saldo estimado, medidor de uso desde la última recarga y cuadre. */
function at_fin_panel_cuentas(): void {
	$cuentas = at_fin_cuentas();
	if ($cuentas === []) {
		echo '<p>Aún no hay cuentas. Se crean desde la API (<code>POST /finanzas/cuenta</code>) y aparecen aquí con su saldo.</p>';
		return;
	}
	echo '<p class="at-fin-nota">El saldo es una estimación: recargas + ajustes − consumos registrados. Si el proveedor muestra otro saldo, usa «Cuadrar» para dejarlo igual.</p>';
	echo '<div class="at-fin-cuentas">';
	foreach ($cuentas as $c) {
		$s = $c['saldo'];
		$moneda = (string) $c['moneda'];
		$negativo = $s['saldo'] < 0;
		$pct = $s['pct_usado'];
		$nivel = $pct === null || $pct < 70 ? '' : ($pct < 90 ? ' at-fin-alerta' : ' at-fin-critico');
		$movs = array_slice(at_fin_movimientos_cuenta((int) $c['id'], 10), 0, 10);
		?>
		<div class="at-fin-cuenta<?php echo (int) $c['activa'] === 1 ? '' : ' at-fin-inactiva'; ?>" data-cuenta="<?php echo esc_attr($c['slug']); ?>">
			<strong><?php echo esc_html($c['nombre']); ?></strong>
			<div class="at-fin-nota"><?php echo esc_html($c['proveedor'] !== '' ? $c['proveedor'] . ' · ' . $moneda : $moneda); ?><?php echo (int) $c['activa'] === 1 ? '' : ' · inactiva'; ?></div>
			<div class="at-fin-etiqueta">Saldo estimado</div>
			<div class="at-fin-saldo<?php echo $negativo ? ' at-fin-negativo' : ''; ?>"><?php echo esc_html(at_fin_fmt_monto($s['saldo'], $moneda)); ?></div>
			<?php if ($pct === null): ?>
				<div class="at-fin-nota">Sin recargas registradas: no se puede medir cuánto se ha usado.</div>
			<?php else: ?>
				<div class="at-fin-medidor<?php echo esc_attr($nivel); ?>" role="img" aria-label="Usado <?php echo esc_attr(at_fin_fmt_pct($pct)); ?> % de la última recarga"><span style="width:<?php echo (int) round(min(100, max(0, $pct))); ?>%"></span></div>
				<div class="at-fin-nota">Usado desde la última recarga: <strong><?php echo esc_html(at_fin_fmt_pct($pct)); ?> %</strong>
					(<?php echo esc_html(at_fin_fmt_monto($s['usado_desde_recarga'], $moneda) . ' de ' . at_fin_fmt_monto($s['ultima_recarga']['monto'], $moneda) . ', recarga del ' . at_fin_fecha_es($s['ultima_recarga']['fecha'])); ?>)</div>
			<?php endif; ?>
			<div class="at-fin-nota">Recargado <?php echo esc_html(at_fin_fmt_monto($s['recargado'], $moneda)); ?> · consumido <?php echo esc_html(at_fin_fmt_monto($s['consumido'], $moneda)); ?> · ajustes <?php echo esc_html(at_fin_fmt_monto($s['ajustes'], $moneda)); ?></div>
			<form method="post" class="at-fin-cuadrar" action="<?php echo esc_url(at_fin_panel_url('cuentas')); ?>">
				<?php echo at_fin_panel_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<input type="hidden" name="accion" value="cuadrar">
				<input type="hidden" name="cuenta" value="<?php echo esc_attr($c['slug']); ?>">
				<input type="hidden" name="base" value="<?php echo esc_attr((string) at_fin_panel_base_cuenta((int) $c['id'])); ?>">
				<div><label class="at-fin-etiqueta" for="at-fin-real-<?php echo esc_attr($c['slug']); ?>">Saldo real (<?php echo esc_html($moneda); ?>)</label><br>
					<input type="text" id="at-fin-real-<?php echo esc_attr($c['slug']); ?>" name="saldo_real" inputmode="decimal" required placeholder="30,00"></div>
				<button class="button" type="submit">Cuadrar</button>
			</form>
			<?php if ($movs !== []): ?>
			<details style="margin-top:10px"><summary>Últimos movimientos</summary>
				<div class="at-fin-scroll"><table class="widefat striped" style="margin-top:6px"><tbody>
					<?php foreach ($movs as $m): ?>
					<tr><td style="white-space:nowrap"><?php echo esc_html(at_fin_fecha_es((string) $m['fecha'])); ?></td><td><?php echo esc_html(at_fin_tipo_texto((string) $m['tipo'])); ?></td><td><?php echo esc_html((string) $m['descripcion']); ?></td><td class="at-fin-der"><?php echo esc_html(at_fin_fmt_monto($m['monto'], $moneda)); ?></td></tr>
					<?php endforeach; ?>
				</tbody></table></div>
			</details>
			<?php endif; ?>
		</div>
		<?php
	}
	echo '</div>';
}
