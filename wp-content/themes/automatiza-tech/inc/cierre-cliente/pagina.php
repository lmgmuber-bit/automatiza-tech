<?php
/** Barra de respuesta en la página pública de la propuesta y su procesamiento. */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_nopriv_at_cc_responder', 'at_cc_procesar_respuesta_publica');
add_action('admin_post_at_cc_responder', 'at_cc_procesar_respuesta_publica');
add_action('admin_post_nopriv_at_cc_datos_contrato', 'at_cc_procesar_datos_contrato_publica');
add_action('admin_post_at_cc_datos_contrato', 'at_cc_procesar_datos_contrato_publica');

/** IP real del cliente, para la evidencia de la respuesta y para el límite amplio por conexión:
 *  siempre REMOTE_ADDR, nunca una cabecera que el propio cliente puede mandar. automatizatech.cl está
 *  detrás del CDN de Hostinger (Server: hcdn), no de Cloudflare: CF-Connecting-IP y X-Forwarded-For no
 *  vienen de un proxy de confianza, así que cualquiera puede ponerles el valor que quiera y, con uno
 *  distinto en cada envío, saltarse un límite basado en esas cabeceras y falsificar la IP que queda
 *  como evidencia de la aceptación (T7 ronda 1, hallazgo 1). */
function at_cc_ip(): string {
	$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
	return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/** Lo que el cliente pudo declarar en CF-Connecting-IP / X-Forwarded-For: puramente informativo (lo
 *  ve Luis en el panel). Nunca se usa como clave de límite ni como evidencia; ver at_cc_ip(). */
function at_cc_ip_reenviada(): string {
	foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $k) {
		if (empty($_SERVER[$k])) {
			continue;
		}
		$ip = trim(explode(',', (string) $_SERVER[$k])[0]);
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			return $ip;
		}
	}
	return '';
}

/** Límite amplio de intentos por IP real (REMOTE_ADDR) y acción: cubre un ataque que prueba muchos
 *  códigos de propuesta distintos desde la misma conexión. Por sí solo no basta -varias personas
 *  pueden compartir una IP real (NAT, red móvil, oficina) y no hay que bloquearlas entre sí para una
 *  sola propuesta-, así que siempre se usa junto con at_cc_limite_codigo_ok(). */
function at_cc_limite_ip_ok(string $accion, int $max, int $segundos): bool {
	$clave = 'at_cc_lim_' . md5($accion . '|' . at_cc_ip());
	$n = (int) get_transient($clave);
	if ($n >= $max) {
		return false;
	}
	set_transient($clave, $n + 1, $segundos);
	return true;
}

/** Límite de intentos por acción y código de propuesta: no depende de ninguna IP ni cabecera, así que
 *  cambiar CF-Connecting-IP/X-Forwarded-For entre envíos (o compartir REMOTE_ADDR con otra conexión)
 *  no lo evita. Es el límite principal contra un mismo código (T7 ronda 1, hallazgo 1); el amplio por
 *  IP (at_cc_limite_ip_ok) es el respaldo contra un ataque que prueba muchos códigos distintos. */
function at_cc_limite_codigo_ok(string $accion, string $codigo, int $max, int $segundos): bool {
	$clave = 'at_cc_lim_cod_' . md5($accion . '|' . $codigo);
	$n = (int) get_transient($clave);
	if ($n >= $max) {
		return false;
	}
	set_transient($clave, $n + 1, $segundos);
	return true;
}

function at_cc_procesar_respuesta_publica(): void {
	$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? ''));
	$volver = function (string $clave) use ($codigo): void {
		wp_safe_redirect(home_url('/ver-presentacion.php?id=' . rawurlencode($codigo) . '&respuesta=' . rawurlencode($clave)));
		exit;
	};
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		$volver('error');
	}
	if (!empty($_POST['sitio_web'])) {
		$volver('recibida');
	}
	if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'at_cc_responder_' . $codigo)) {
		$volver('vencida');
	}
	// Límite por código (principal, no lo evita cambiar de IP/cabecera) + límite amplio por IP real
	// (respaldo contra un ataque que prueba muchos códigos): T7 ronda 1, hallazgo 1.
	$limite_codigo_ok = at_cc_limite_codigo_ok('responder', $codigo, 10, HOUR_IN_SECONDS);
	$limite_ip_ok = at_cc_limite_ip_ok('responder', 60, HOUR_IN_SECONDS);
	if (!$limite_codigo_ok || !$limite_ip_ok) {
		$volver('limite');
	}
	$p = at_cc_propuesta_por_codigo($codigo);
	if (!$p) {
		$volver('recibida');
	}
	$salida = sanitize_key(wp_unslash($_POST['salida'] ?? ''));
	$d = [
		'canal'      => 'pagina',
		'nombre'     => sanitize_text_field(wp_unslash($_POST['nombre'] ?? '')),
		'comentario' => sanitize_textarea_field(wp_unslash($_POST['comentario'] ?? '')),
		'ip'         => at_cc_ip(),
		'ip_reenviada' => at_cc_ip_reenviada(),
		'agente'     => mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300),
		'fecha'      => current_time('mysql'),
		'bienvenida' => true,
		'usuario_id' => get_current_user_id(),
	];
	if ($salida === 'acepta') {
		$rut = sanitize_text_field(wp_unslash($_POST['rut'] ?? ''));
		$todas = at_cc_filas_de_propuesta($p);
		$d['filas'] = at_cc_filas_aceptadas($todas, array_map('sanitize_text_field', (array) wp_unslash($_POST['filas'] ?? [])));
		if (mb_strlen(trim($d['nombre'])) < 3 || !at_cc_rut_valido($rut) || empty($_POST['acepto']) || ($todas && !$d['filas'])) {
			$volver('datos');
		}
		$d['rut'] = at_cc_rut_formato($rut);
	}
	$r = at_cc_registrar_respuesta($p, $salida, $d);
	$volver($r['ok'] ? $r['estado'] : 'error');
}

/** Carga ContractService si hace falta; false si el módulo no está instalado. */
function at_cc_cargar_contract_service(): bool {
	if (class_exists('ContractService')) {
		return true;
	}
	$f = ABSPATH . 'contracts/contract-service.php';
	if (!file_exists($f)) {
		return false;
	}
	require_once $f;
	return true;
}

/** El contrato de la propuesta puede recibir «Datos para tu contrato»: existe, es de servicios,
 *  sigue sin firmar y Luis todavía no guardó su revisión. */
function at_cc_contrato_admite_datos_cliente(?object $c): bool {
	if (!$c || $c->type !== 'servicios' || !in_array($c->status, ['draft', 'at_pending'], true)) {
		return false;
	}
	return at_cc_cargar_contract_service() && ContractService::necesita_revision($c);
}

/**
 * Datos que deja el cliente después de aceptar para armar su contrato (opcional). Devuelve la
 * clave del mensaje que ve en la página. La propuesta debe estar aceptada y tener contrato; el
 * tipo debe ser persona o empresa, y la dirección es obligatoria. Si es empresa, la razón social
 * y un RUT válido también son obligatorios. Con la revisión de Luis ya guardada, el contrato no
 * cambia pero igual queda la nota en Seguimiento.
 */
function at_cc_guardar_datos_contrato(object $p, array $post): string {
	global $wpdb;
	if ((string) $p->status !== 'aceptada') {
		return 'recibida';
	}
	$c = at_cc_contrato_de_propuesta((int) $p->id);
	if (!$c) {
		return 'recibida';
	}
	$tipo = sanitize_key((string) ($post['tipo'] ?? ''));
	if (!in_array($tipo, ['persona', 'empresa'], true)) {
		return 'datos_contrato';
	}
	$direccion = mb_substr(trim(sanitize_text_field((string) ($post['direccion'] ?? ''))), 0, 300);
	if ($direccion === '') {
		return 'datos_contrato';
	}
	$datos = ['tipo_cliente' => $tipo, 'domicilio_cliente' => $direccion];
	$razon = '';
	$rut_empresa = '';
	if ($tipo === 'empresa') {
		$razon = mb_substr(trim(sanitize_text_field((string) ($post['razon_social'] ?? ''))), 0, 200);
		$rut_crudo = sanitize_text_field((string) ($post['rut_empresa'] ?? ''));
		if ($razon === '' || !at_cc_rut_valido($rut_crudo)) {
			return 'datos_contrato';
		}
		$rut_empresa = at_cc_rut_formato($rut_crudo);
		$datos['razon_social_cliente'] = $razon;
		$datos['rut_cliente'] = $rut_empresa;
	}
	if (!at_cc_cargar_contract_service()) {
		return 'recibida';
	}
	try {
		$r = ContractService::actualizar_datos_cliente((int) $c->id, $datos);
	} catch (\Throwable $e) {
		// Igual que at_cc_crear_contrato_servicios(): actualizar_datos_cliente() guarda los
		// marcadores y recién después regenera el PDF (ContractService::guardar_marcadores()); si
		// FPDF no puede escribirlo (cuota de disco, permisos), lanza una excepción con los
		// marcadores ya guardados pero el PDF desactualizado. No se deja escapar -el cliente vería
		// un error crítico y ni la nota en Seguimiento ni la actualización de la ficha quedarían-,
		// y queda como 'cierre_incompleto' (mismo tipo que usa at_cc_ejecutar_cierre(), excluido de
		// la línea de tiempo pública en crm-ai-completo.php) para que Luis revise el PDF (T7 ronda
		// 1, hallazgo 2).
		at_cc_anotar_simple($p, 'cierre_incompleto', 'Datos del contrato no se guardaron del todo', 'No se pudo terminar de guardar los datos del contrato: ' . $e->getMessage());
		return 'datos_recibidos';
	}
	// Descripción genérica a propósito: llega a la línea de tiempo pública del cliente (token
	// calculable, ver crm-ai-completo.php). El tipo, la razón social, el RUT y la dirección solo
	// debe verlos Luis, así que van en metadata (la lee el panel interno, nunca la vista pública).
	$meta = ['tipo' => $tipo, 'direccion' => $direccion];
	if ($tipo === 'empresa') {
		$meta['razon_social'] = $razon;
		$meta['rut'] = $rut_empresa;
	}
	at_cc_anotar_simple($p, 'respuesta_cliente', 'Datos para el contrato', 'El cliente dejó sus datos para el contrato.', $meta);
	if (is_wp_error($r)) {
		// Ninguno de los códigos que el servicio puede devolver a esta altura es un error de
		// validación del cliente: tipo, dirección y (si aplica) razón social/RUT de empresa ya se
		// validaron arriba. 'ya_revisado', 'bad_status' (contrato ya firmado), 'bad_type' o
		// 'not_found' son todos "esto ya no se puede tocar", no "escribiste mal algo": devolver
		// 'datos_contrato' aquí sería un falso aviso de validación (el cliente creería que se
		// equivocó y, al recargar, ni siquiera vuelve a ver el botón). T7 ronda 1, hallazgo 3.
		return 'datos_recibidos';
	}
	// Ficha operativa (wp_automatiza_tech_clients): completa solo lo que estaba vacío.
	$ph = json_decode((string) $r->placeholders, true) ?: [];
	$rut_ficha = $tipo === 'empresa' ? $rut_empresa : trim((string) ($ph['representante_cliente_rut'] ?? ''));
	$tech = $wpdb->prefix . 'automatiza_tech_clients';
	$fila = $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id, company FROM {$tech} WHERE id = %d", (int) $c->client_id));
	if ($fila) {
		$cambios = [];
		if (trim((string) $fila->billing_address) === '') {
			$cambios['billing_address'] = $direccion;
		}
		if ($rut_ficha !== '' && trim((string) $fila->tax_id) === '') {
			$cambios['tax_id'] = $rut_ficha;
		}
		if ($tipo === 'empresa' && trim((string) $fila->company) === '') {
			$cambios['company'] = $razon;
		}
		if ($cambios) {
			$wpdb->update($tech, $cambios, ['id' => (int) $c->client_id], array_fill(0, count($cambios), '%s'), ['%d']);
		}
	}
	return 'datos_ok';
}

function at_cc_procesar_datos_contrato_publica(): void {
	$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? ''));
	$volver = function (string $clave) use ($codigo): void {
		wp_safe_redirect(home_url('/ver-presentacion.php?id=' . rawurlencode($codigo) . '&respuesta=' . rawurlencode($clave)));
		exit;
	};
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		$volver('error');
	}
	if (!empty($_POST['sitio_web'])) {
		$volver('recibida');
	}
	if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'at_cc_datos_contrato_' . $codigo)) {
		$volver('vencida');
	}
	// Mismo esquema de límite que at_cc_procesar_respuesta_publica(): T7 ronda 1, hallazgo 1.
	$limite_codigo_ok = at_cc_limite_codigo_ok('datos_contrato', $codigo, 10, HOUR_IN_SECONDS);
	$limite_ip_ok = at_cc_limite_ip_ok('datos_contrato', 60, HOUR_IN_SECONDS);
	if (!$limite_codigo_ok || !$limite_ip_ok) {
		$volver('limite');
	}
	$p = at_cc_propuesta_por_codigo($codigo);
	if (!$p) {
		$volver('recibida');
	}
	$volver(at_cc_guardar_datos_contrato($p, wp_unslash($_POST)));
}

function at_cc_render_barra(object $p): void {
	$estado = (string) $p->status;
	if (!at_cc_puede_pedir_respuesta($estado) && $estado !== 'aceptada') {
		return;
	}
	$codigo = (string) $p->unique_link_id;
	$respuesta_clave = sanitize_key(wp_unslash($_GET['respuesta'] ?? ''));
	$msg = at_cc_mensaje_respuesta($respuesta_clave);
	$abrir = ['aceptar' => 'at-cc-acepta', 'evaluar' => 'at-cc-evalua'][sanitize_key(wp_unslash($_GET['responder'] ?? ''))] ?? '';
	$filas = at_cc_filas_de_propuesta($p);
	$accion = admin_url('admin-post.php');
	$nonce = wp_create_nonce('at_cc_responder_' . $codigo);
	$mostrar_datos_contrato = $estado === 'aceptada' && at_cc_contrato_admite_datos_cliente(at_cc_contrato_de_propuesta((int) $p->id));
	if ($abrir === '' && $mostrar_datos_contrato && $respuesta_clave === 'aceptada') {
		$abrir = 'at-cc-datos';
	}
	$ocultos = function (string $salida) use ($codigo, $nonce): string {
		return '<input type="hidden" name="action" value="at_cc_responder">'
			. '<input type="hidden" name="salida" value="' . esc_attr($salida) . '">'
			. '<input type="hidden" name="codigo" value="' . esc_attr($codigo) . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
			. '<div class="at-cc-trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>';
	};
	$nonce_datos = wp_create_nonce('at_cc_datos_contrato_' . $codigo);
	$ocultos_datos = '<input type="hidden" name="action" value="at_cc_datos_contrato">'
		. '<input type="hidden" name="codigo" value="' . esc_attr($codigo) . '">'
		. '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce_datos) . '">'
		. '<div class="at-cc-trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>';
	?>
<style>
html,body{height:100%}
body{display:flex;flex-direction:column}
body>iframe{flex:1 1 auto;height:auto;min-height:0}
.at-cc-barra{flex:0 0 auto;background:#0f172a;color:#f8fafc;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap}
.at-cc-barra p{margin:0;font-size:15px}
.at-cc-btn{border:0;border-radius:999px;padding:11px 20px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
.at-cc-si{background:#10b981;color:#052e16}
.at-cc-sec{background:#1e293b;color:#e2e8f0;border:1px solid #334155}
.at-cc-msg{width:100%;text-align:center;padding:8px 12px;border-radius:8px;font-size:14px}
.at-cc-msg--ok{background:#064e3b;color:#d1fae5}.at-cc-msg--aviso{background:#78350f;color:#fef3c7}.at-cc-msg--error{background:#7f1d1d;color:#fee2e2}
dialog.at-cc-dlg{border:0;border-radius:14px;padding:0;max-width:440px;width:calc(100% - 32px);font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a}
dialog.at-cc-dlg::backdrop{background:rgba(15,23,42,.7)}
.at-cc-dlg form{padding:22px}
.at-cc-dlg h2{margin:0 0 6px;font-size:19px}
.at-cc-dlg p{margin:6px 0;font-size:14px;color:#334155}
.at-cc-dlg label{display:block;margin:12px 0 4px;font-size:14px;font-weight:600}
.at-cc-dlg input[type=text],.at-cc-dlg textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;font-family:inherit}
.at-cc-dlg label.at-cc-fila{display:flex;gap:8px;align-items:flex-start;font-weight:400;margin:6px 0}
.at-cc-dlg .at-cc-acciones{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}
.at-cc-trampa{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
@media (max-width:600px){.at-cc-barra{padding:8px 10px;gap:8px}.at-cc-btn{padding:10px 14px;font-size:14px}}
</style>
<div class="at-cc-barra" role="region" aria-label="Responder la propuesta">
	<?php if ($msg): ?><div class="at-cc-msg at-cc-msg--<?php echo esc_attr($msg['tipo']); ?>" role="status"><?php echo esc_html($msg['texto']); ?></div><?php endif; ?>
	<?php if ($estado === 'aceptada'): ?>
		<p>✅ Esta propuesta ya está aceptada. Te escribimos con los próximos pasos.</p>
		<?php if ($mostrar_datos_contrato): ?>
			<button type="button" class="at-cc-btn at-cc-sec" data-abrir="at-cc-datos">Datos para tu contrato</button>
		<?php endif; ?>
	<?php else: ?>
		<button type="button" class="at-cc-btn at-cc-si" data-abrir="at-cc-acepta">Acepto la propuesta</button>
		<button type="button" class="at-cc-btn at-cc-sec" data-abrir="at-cc-evalua">La sigo evaluando</button>
		<button type="button" class="at-cc-btn at-cc-sec" data-abrir="at-cc-rechaza">No, gracias</button>
	<?php endif; ?>
</div>
<?php if ($estado !== 'aceptada'): ?>
<dialog class="at-cc-dlg" id="at-cc-acepta">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos('acepta'); ?>
		<h2>Aceptar la propuesta</h2>
		<p>Con tu aceptación te enviamos el contrato para firmar y los primeros pasos para partir.</p>
		<?php if ($filas): ?>
			<p><strong>¿Qué aceptas?</strong></p>
			<?php foreach ($filas as $i => $f): ?>
				<label class="at-cc-fila"><input type="checkbox" name="filas[]" value="<?php echo (int) $i; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>> <span><?php echo esc_html(trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? ''))); ?></span></label>
			<?php endforeach; ?>
		<?php endif; ?>
		<label for="at-cc-nombre">Tu nombre completo</label>
		<input type="text" id="at-cc-nombre" name="nombre" required minlength="3" autocomplete="name" value="<?php echo esc_attr((string) $p->client_name); ?>">
		<label for="at-cc-rut">Tu RUT</label>
		<input type="text" id="at-cc-rut" name="rut" required placeholder="12.345.678-9" autocomplete="off">
		<label class="at-cc-fila"><input type="checkbox" name="acepto" value="1" required> <span>Acepto la propuesta y sus condiciones.</span></label>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Confirmar aceptación</button>
		</div>
	</form>
</dialog>
<dialog class="at-cc-dlg" id="at-cc-evalua">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos('evalua'); ?>
		<h2>La sigo evaluando</h2>
		<label for="at-cc-com-ev">¿Qué te falta o qué dudas tienes? (opcional)</label>
		<textarea id="at-cc-com-ev" name="comentario" rows="4"></textarea>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Enviar</button>
		</div>
	</form>
</dialog>
<dialog class="at-cc-dlg" id="at-cc-rechaza">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos('rechaza'); ?>
		<h2>No, gracias</h2>
		<label for="at-cc-com-re">¿Nos cuentas por qué? (opcional)</label>
		<textarea id="at-cc-com-re" name="comentario" rows="4"></textarea>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Enviar respuesta</button>
		</div>
	</form>
</dialog>
<?php endif; ?>
<?php if ($mostrar_datos_contrato): ?>
<dialog class="at-cc-dlg" id="at-cc-datos">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos_datos; ?>
		<h2>Datos para tu contrato</h2>
		<p>Opcional: si nos dejas estos datos ahora, tu contrato llega listo para firmar.</p>
		<p><strong>¿A nombre de quién va el contrato?</strong></p>
		<label class="at-cc-fila"><input type="radio" name="tipo" value="persona" data-at-cc-tipo checked> <span>A mi nombre (persona natural)</span></label>
		<label class="at-cc-fila"><input type="radio" name="tipo" value="empresa" data-at-cc-tipo> <span>De una empresa</span></label>
		<div id="at-cc-campos-empresa" hidden>
			<label for="at-cc-razon">Razón social de la empresa</label>
			<input type="text" id="at-cc-razon" name="razon_social" maxlength="200">
			<label for="at-cc-rut-empresa">RUT de la empresa</label>
			<input type="text" id="at-cc-rut-empresa" name="rut_empresa" placeholder="12.345.678-9">
		</div>
		<label for="at-cc-direccion">Dirección (calle, número, comuna y ciudad)</label>
		<input type="text" id="at-cc-direccion" name="direccion" required maxlength="300">
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Ahora no</button>
			<button type="submit" class="at-cc-btn at-cc-si">Guardar datos</button>
		</div>
	</form>
</dialog>
<?php endif; ?>
<script>
(function () {
	function abrir(id) { var d = document.getElementById(id); if (d && d.showModal) { d.showModal(); } }
	document.querySelectorAll('[data-abrir]').forEach(function (b) { b.addEventListener('click', function () { abrir(b.getAttribute('data-abrir')); }); });
	document.querySelectorAll('dialog.at-cc-dlg [data-cerrar]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });
	function actualizarTipo() {
		var campos = document.getElementById('at-cc-campos-empresa');
		var marcado = document.querySelector('[data-at-cc-tipo]:checked');
		if (campos) { campos.hidden = !marcado || marcado.value !== 'empresa'; }
	}
	document.querySelectorAll('[data-at-cc-tipo]').forEach(function (r) { r.addEventListener('change', actualizarTipo); });
	actualizarTipo();
	var inicial = <?php echo wp_json_encode($abrir); ?>;
	if (inicial) { abrir(inicial); }
})();
</script>
<?php
}
