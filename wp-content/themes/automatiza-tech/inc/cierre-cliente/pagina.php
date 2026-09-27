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
	// Ronda 1 de revisión (26-sep), hallazgo 5 (T11): el código solo se usa aquí para armar el
	// redirect a ver-presentacion.php (nunca para el nonce, los límites o buscar la propuesta, que
	// siguen exigiendo POST más abajo); tomarlo también de GET evita que un GET a esta acción
	// devuelva «ID de presentación no válido» en vez de la página correcta con el aviso de error.
	$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? $_GET['codigo'] ?? ''));
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
	// Ronda 1 de revisión (26-sep), hallazgo 1 (T11): sanitize_text_field()/sanitize_textarea_field()
	// comparten _sanitize_text_fields(), que borra '%' seguido de dos caracteres hexadecimales
	// (lo confunde con un byte %-encoded): «¿El 50%de anticipo puede ser en 2 cuotas?» llegaba a Luis
	// como «¿El 50 anticipo puede ser en 2 cuotas?». Se guarda el texto tal cual llegó (solo UTF-8
	// válido y sin caracteres de control) y se escapa recién a la salida (correo a Luis con esc_html;
	// panel y widget ya escapan). Mismo criterio que whatsapp.php (T10b) y contract-service.php (T5b).
	$nombre_bruto = trim(wp_check_invalid_utf8((string) wp_unslash($_POST['nombre'] ?? ''), true));
	$nombre_bruto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $nombre_bruto);
	$comentario_bruto = trim(wp_check_invalid_utf8((string) wp_unslash($_POST['comentario'] ?? ''), true));
	$comentario_bruto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $comentario_bruto);
	$d = [
		'canal'      => 'pagina',
		'nombre'     => $nombre_bruto,
		'comentario' => mb_substr($comentario_bruto, 0, 1000),
		'ip'         => at_cc_ip(),
		'ip_reenviada' => at_cc_ip_reenviada(),
		'agente'     => mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300),
		'fecha'      => current_time('mysql'),
		'bienvenida' => true,
		'usuario_id' => get_current_user_id(),
	];
	if ($salida === 'acepta') {
		// Task 15 (aprobada por Luis el 26-sep): tipo de documento (RUT, DNI o pasaporte) y número; solo
		// el RUT se valida con dígito verificador. Una página abierta desde antes del cambio manda solo
		// 'rut': se toma como RUT, para no rebotar a quien ya tenía el formulario abierto.
		$tipo_doc = sanitize_key(wp_unslash($_POST['tipo_documento'] ?? ''));
		$documento = trim(sanitize_text_field(wp_unslash($_POST['documento'] ?? '')));
		if (!isset($_POST['tipo_documento']) && !isset($_POST['documento']) && isset($_POST['rut'])) {
			$tipo_doc = 'rut';
			$documento = trim(sanitize_text_field(wp_unslash($_POST['rut'])));
		}
		$todas = at_cc_filas_de_propuesta($p);
		$d['filas'] = at_cc_filas_aceptadas($todas, array_map('sanitize_text_field', (array) wp_unslash($_POST['filas'] ?? [])));
		// Task 18 (decisión de Luis, 27-sep): los datos del contrato (a nombre de quién va, dirección y,
		// si es empresa, razón social y RUT) son obligatorios al aceptar. En el primer contrato real el
		// cliente aceptó sin dejarlos -el diálogo aparte era opcional- y hubo que pedírselos por privado
		// para poder firmar. Un formulario abierto desde antes del cambio no los trae y también vuelve
		// con 'datos': al recargar ya ve el formulario nuevo.
		$datos_contrato = at_cc_datos_contrato_de_post(wp_unslash($_POST));
		// Revisión (27-sep): a una propuesta ya aceptada o archivada no se le exigen (at_cc_registrar_respuesta()
		// la resuelve igual: «ya aceptada» o el error de archivada). Antes volvía con 'datos' y un aviso sin salida.
		$exigir_datos = !in_array((string) $p->status, ['aceptada', 'archivada'], true);
		if (mb_strlen(trim($d['nombre'])) < 3 || !isset(at_cc_tipos_documento()[$tipo_doc]) || !at_cc_documento_valido($tipo_doc, $documento) || empty($_POST['acepto']) || ($todas && !$d['filas']) || ($datos_contrato === null && $exigir_datos)) {
			$volver('datos');
		}
		$d['datos_contrato'] = $datos_contrato;
		$d['tipo_documento'] = $tipo_doc;
		$d['documento'] = at_cc_documento_formato($tipo_doc, $documento);
		if ($tipo_doc === 'rut') {
			$d['rut'] = $d['documento']; // Compatibilidad con lo que ya leía 'rut'.
		}
	}
	// T14 ronda 1, hallazgo 2: un cliente con la página abierta desde antes del archivo manda el
	// formulario. at_cc_registrar_respuesta() la rechaza (redirige al error de siempre), pero el intento
	// queda como nota interna para Luis. El límite por código de arriba acota cuántas notas puede dejar.
	if ((string) $p->status === 'archivada' && isset(at_cc_salidas()[$salida])) {
		at_cc_anotar_intento_archivada($p, $salida, 'pagina', [
			'nombre'     => $d['nombre'],
			'rut'        => (string) ($d['rut'] ?? ''),
			'tipo_documento' => (string) ($d['tipo_documento'] ?? ''),
			'documento'  => (string) ($d['documento'] ?? ''),
			'comentario' => $d['comentario'],
			'ip'         => $d['ip'],
		]);
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
 * Task 18: una sola validación de los datos del contrato para los dos formularios de la página
 * («Aceptar la propuesta» y «Datos para tu contrato»), con los mismos nombres de campo: tipo,
 * direccion, razon_social y rut_empresa. El tipo debe ser persona o empresa y la dirección es
 * obligatoria (hasta 300 caracteres); si es empresa, también la razón social (hasta 200) y un RUT
 * válido, que se guarda formateado. Devuelve los datos para ContractService::actualizar_datos_cliente()
 * o null si algo falta o no es válido. $post ya viene sin barras (wp_unslash).
 */
function at_cc_datos_contrato_de_post(array $post): ?array {
	// Un campo que llega como arreglo (tipo[]=…) cuenta como vacío, sin el aviso de PHP al convertirlo.
	$campo = function (string $k) use ($post): string {
		return is_scalar($post[$k] ?? null) ? (string) $post[$k] : '';
	};
	$tipo = sanitize_key($campo('tipo'));
	if (!in_array($tipo, ['persona', 'empresa'], true)) {
		return null;
	}
	$direccion = mb_substr(trim(sanitize_text_field($campo('direccion'))), 0, 300);
	if ($direccion === '') {
		return null;
	}
	$datos = ['tipo_cliente' => $tipo, 'domicilio_cliente' => $direccion];
	if ($tipo === 'empresa') {
		$razon = mb_substr(trim(sanitize_text_field($campo('razon_social'))), 0, 200);
		$rut_crudo = sanitize_text_field($campo('rut_empresa'));
		if ($razon === '' || !at_cc_rut_valido($rut_crudo)) {
			return null;
		}
		$datos['razon_social_cliente'] = $razon;
		$datos['tipo_documento_cliente'] = 'rut'; // Task 15: una empresa siempre se identifica con RUT.
		$datos['rut_cliente'] = at_cc_rut_formato($rut_crudo);
	}
	return $datos;
}

/**
 * Task 18: aplica los datos del contrato (ya validados con at_cc_datos_contrato_de_post()) y completa
 * la ficha operativa (wp_automatiza_tech_clients) solo en lo que estaba vacío: billing_address, tax_id
 * y company. La usan «Datos para tu contrato» y el cierre de una aceptación en la página. Devuelve el
 * contrato actualizado o el WP_Error del servicio (ya revisado, ya firmado…); si FPDF no puede
 * escribir el PDF lanza una excepción con los marcadores ya guardados, y quien llama decide cómo
 * dejarlo anotado.
 */
function at_cc_aplicar_datos_contrato(object $c, array $datos) {
	global $wpdb;
	if (!at_cc_cargar_contract_service()) {
		return new WP_Error('sin_contratos', 'El módulo de contratos no está instalado.');
	}
	$r = ContractService::actualizar_datos_cliente((int) $c->id, $datos);
	if (is_wp_error($r)) {
		return $r;
	}
	$tipo = (string) ($datos['tipo_cliente'] ?? '');
	$ph = json_decode((string) $r->placeholders, true) ?: [];
	$rut_ficha = $tipo === 'empresa' ? (string) ($datos['rut_cliente'] ?? '') : trim((string) ($ph['representante_cliente_rut'] ?? ''));
	$tech = $wpdb->prefix . 'automatiza_tech_clients';
	$fila = $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id, company FROM {$tech} WHERE id = %d", (int) $c->client_id));
	if ($fila) {
		$cambios = [];
		if (trim((string) $fila->billing_address) === '') {
			$cambios['billing_address'] = (string) ($datos['domicilio_cliente'] ?? '');
		}
		if ($rut_ficha !== '' && trim((string) $fila->tax_id) === '') {
			$cambios['tax_id'] = $rut_ficha;
		}
		if ($tipo === 'empresa' && trim((string) $fila->company) === '') {
			$cambios['company'] = (string) ($datos['razon_social_cliente'] ?? '');
		}
		if ($cambios) {
			$wpdb->update($tech, $cambios, ['id' => (int) $c->client_id], array_fill(0, count($cambios), '%s'), ['%d']);
		}
	}
	return $r;
}

/**
 * Datos que deja el cliente después de aceptar para armar su contrato. Desde la Task 18 se piden al
 * aceptar en la página; este formulario queda para las aceptaciones por WhatsApp o a mano y para
 * corregir. Devuelve la clave del mensaje que ve en la página. La propuesta debe estar aceptada y
 * tener contrato; la validación es at_cc_datos_contrato_de_post(). Con la revisión de Luis ya
 * guardada, el contrato no cambia pero igual queda la nota en Seguimiento.
 */
function at_cc_guardar_datos_contrato(object $p, array $post): string {
	if ((string) $p->status !== 'aceptada') {
		return 'recibida';
	}
	$c = at_cc_contrato_de_propuesta((int) $p->id);
	if (!$c) {
		return 'recibida';
	}
	$datos = at_cc_datos_contrato_de_post($post);
	if ($datos === null) {
		return 'datos_contrato';
	}
	if (!at_cc_cargar_contract_service()) {
		return 'recibida';
	}
	try {
		$r = at_cc_aplicar_datos_contrato($c, $datos);
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
	// Descripción genérica a propósito: la descripción puede verla el cliente en su portal. El tipo,
	// la razón social, el RUT y la dirección solo debe verlos Luis, así que van en metadata (la lee el
	// panel interno, nunca la vista del cliente).
	$meta = ['tipo' => $datos['tipo_cliente'], 'direccion' => $datos['domicilio_cliente']];
	if ($datos['tipo_cliente'] === 'empresa') {
		$meta['razon_social'] = $datos['razon_social_cliente'];
		$meta['rut'] = $datos['rut_cliente'];
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
	return 'datos_ok';
}

function at_cc_procesar_datos_contrato_publica(): void {
	// Mismo criterio que at_cc_procesar_respuesta_publica(): T11 ronda 1, hallazgo 5.
	$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? $_GET['codigo'] ?? ''));
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
	$c_datos = $estado === 'aceptada' ? at_cc_contrato_de_propuesta((int) $p->id) : null;
	$mostrar_datos_contrato = $estado === 'aceptada' && at_cc_contrato_admite_datos_cliente($c_datos);
	// Task 18 (revisión, 27-sep): tras aceptar, «Datos para tu contrato» se abre solo únicamente si al
	// contrato le falta la dirección (aceptación por WhatsApp o a mano). Si ya la dejó al aceptar, abrirlo
	// le pedía los datos dos veces y, guardado sin mirar, podía pasar a persona un contrato de empresa.
	$ph_datos = $c_datos ? (json_decode((string) $c_datos->placeholders, true) ?: []) : [];
	$contrato_sin_direccion = trim((string) ($ph_datos['domicilio_cliente'] ?? '')) === '';
	if ($abrir === '' && $mostrar_datos_contrato && $respuesta_clave === 'aceptada' && $contrato_sin_direccion) {
		$abrir = 'at-cc-datos';
	}
	// Task 18: si el formulario volvió por datos que faltan o no son válidos, su diálogo se reabre
	// solo y muestra el aviso adentro (además de la barra): antes el aviso quedaba chico en la barra,
	// el diálogo cerrado, y el cliente creía haber dejado sus datos. Solo si ese diálogo se dibuja
	// (el de aceptar mientras no esté aceptada; el de datos mientras el contrato los admita).
	$aviso_en = '';
	if ($msg && $respuesta_clave === 'datos' && $estado !== 'aceptada') {
		$aviso_en = 'at-cc-acepta';
	} elseif ($msg && $respuesta_clave === 'datos_contrato' && $mostrar_datos_contrato) {
		$aviso_en = 'at-cc-datos';
	}
	if ($aviso_en !== '') {
		$abrir = $aviso_en;
	}
	$aviso_dialogo = function (string $id) use ($aviso_en, $msg): string {
		return $aviso_en === $id ? '<div class="at-cc-msg at-cc-msg--aviso" role="alert">' . esc_html($msg['texto']) . '</div>' : '';
	};
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
dialog.at-cc-dlg{border:0;border-radius:14px;padding:0;max-width:440px;width:calc(100% - 32px);max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);overflow-y:auto;overscroll-behavior:contain;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a}
.at-cc-dlg .at-cc-msg{box-sizing:border-box;margin:0 0 12px;text-align:left}
.at-cc-dlg .at-cc-grupo{border:0;padding:0;margin:10px 0 0;min-width:0}
.at-cc-dlg .at-cc-grupo legend{padding:0;margin:0 0 2px;font-size:14px;font-weight:700;color:#334155}
dialog.at-cc-dlg::backdrop{background:rgba(15,23,42,.7)}
.at-cc-dlg form{padding:22px}
.at-cc-dlg h2{margin:0 0 6px;font-size:19px}
.at-cc-dlg p{margin:6px 0;font-size:14px;color:#334155}
.at-cc-dlg label{display:block;margin:12px 0 4px;font-size:14px;font-weight:600}
.at-cc-dlg input[type=text],.at-cc-dlg textarea,.at-cc-dlg select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;font-family:inherit}
.at-cc-dlg select{background:#fff;color:#0f172a}
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
	<?php elseif ($estado === 'rechazada'): ?>
		<button type="button" class="at-cc-btn at-cc-si" data-abrir="at-cc-acepta">Acepto la propuesta</button>
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
		<?php echo $aviso_dialogo('at-cc-acepta'); ?>
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
		<label for="at-cc-tipo-doc">Tu documento</label>
		<select id="at-cc-tipo-doc" name="tipo_documento">
			<?php foreach (at_cc_tipos_documento() as $valor_doc => $texto_doc): ?>
				<option value="<?php echo esc_attr($valor_doc); ?>"<?php echo $valor_doc === 'rut' ? ' selected' : ''; ?>><?php echo esc_html($texto_doc); ?></option>
			<?php endforeach; ?>
		</select>
		<label for="at-cc-documento">Número de documento</label>
		<input type="text" id="at-cc-documento" name="documento" required placeholder="12.345.678-9" autocomplete="off">
		<?php // Task 18: los datos del contrato van aquí y son obligatorios; sin opción marcada, para que el cliente elija. ?>
		<fieldset class="at-cc-grupo"><legend>¿A nombre de quién va el contrato?</legend>
		<label class="at-cc-fila"><input type="radio" name="tipo" value="persona" data-at-cc-tipo required> <span>A mi nombre (persona natural)</span></label>
		<label class="at-cc-fila"><input type="radio" name="tipo" value="empresa" data-at-cc-tipo required> <span>De una empresa</span></label>
		</fieldset>
		<div data-at-cc-campos-empresa hidden>
			<label for="at-cc-a-razon">Razón social de la empresa</label>
			<input type="text" id="at-cc-a-razon" name="razon_social" maxlength="200" autocomplete="organization">
			<label for="at-cc-a-rut-empresa">RUT de la empresa</label>
			<input type="text" id="at-cc-a-rut-empresa" name="rut_empresa" placeholder="12.345.678-9" autocomplete="off">
		</div>
		<label for="at-cc-a-direccion">Dirección (calle, número, comuna y ciudad)</label>
		<input type="text" id="at-cc-a-direccion" name="direccion" required maxlength="300" autocomplete="street-address">
		<label class="at-cc-fila"><input type="checkbox" name="acepto" value="1" required> <span>Acepto la propuesta y sus condiciones.</span></label>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Confirmar aceptación</button>
		</div>
	</form>
</dialog>
<?php if ($estado !== 'rechazada'): ?>
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
<?php endif; ?>
<?php if ($mostrar_datos_contrato): ?>
<dialog class="at-cc-dlg" id="at-cc-datos">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos_datos; ?>
		<?php echo $aviso_dialogo('at-cc-datos'); ?>
		<h2>Datos para tu contrato</h2>
		<p>Opcional: si nos dejas estos datos ahora, tu contrato llega listo para firmar.</p>
		<?php // Task 18 (revisión): sin opción marcada, como al aceptar: guardar sin mirar no cambia el tipo del contrato. ?>
		<fieldset class="at-cc-grupo"><legend>¿A nombre de quién va el contrato?</legend>
		<label class="at-cc-fila"><input type="radio" name="tipo" value="persona" data-at-cc-tipo required> <span>A mi nombre (persona natural)</span></label>
		<label class="at-cc-fila"><input type="radio" name="tipo" value="empresa" data-at-cc-tipo required> <span>De una empresa</span></label>
		</fieldset>
		<div data-at-cc-campos-empresa hidden>
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
	// Task 18: cada formulario (aceptar y datos del contrato) muestra sus propios campos de empresa, y
	// solo los exige mientras se ven: un campo obligatorio oculto bloquearía el envío sin decir por qué.
	function actualizarTipo(form) {
		var campos = form.querySelector('[data-at-cc-campos-empresa]');
		var marcado = form.querySelector('[data-at-cc-tipo]:checked');
		if (!campos) { return; }
		campos.hidden = !marcado || marcado.value !== 'empresa';
		campos.querySelectorAll('input').forEach(function (i) { i.required = !campos.hidden; });
	}
	document.querySelectorAll('dialog.at-cc-dlg form').forEach(function (form) {
		form.querySelectorAll('[data-at-cc-tipo]').forEach(function (r) { r.addEventListener('change', function () { actualizarTipo(form); }); });
		actualizarTipo(form);
	});
	// Task 18 (revisión, 27-sep): si la aceptación vuelve con 'datos', el diálogo se dibuja de nuevo desde el
	// servidor y se perdía lo escrito; lo peor, «¿Qué aceptas?» volvía con solo la primera fila marcada y el
	// cliente podía aceptar menos servicios sin notarlo. Lo que envió se guarda en esta pestaña (sessionStorage)
	// y se restaura solo en ese rebote; se borra en cuanto se lee. Sin sessionStorage, el diálogo vuelve vacío.
	var formAcepta = document.querySelector('#at-cc-acepta form');
	var claveAcepta = 'at-cc-acepta-' + <?php echo wp_json_encode($codigo); ?>;
	var guardado = null;
	try { guardado = JSON.parse(sessionStorage.getItem(claveAcepta) || 'null'); sessionStorage.removeItem(claveAcepta); } catch (e) { guardado = null; }
	var camposTexto = ['nombre', 'tipo_documento', 'documento', 'razon_social', 'rut_empresa', 'direccion'];
	if (formAcepta && guardado && typeof guardado === 'object' && <?php echo wp_json_encode($aviso_en === 'at-cc-acepta'); ?>) {
		var filas = Array.isArray(guardado.filas) ? guardado.filas.map(String) : null;
		if (filas) { formAcepta.querySelectorAll('input[name="filas[]"]').forEach(function (c) { c.checked = filas.indexOf(c.value) !== -1; }); }
		camposTexto.forEach(function (n) { var i = formAcepta.querySelector('[name="' + n + '"]'); if (i && typeof guardado[n] === 'string') { i.value = guardado[n]; } });
		formAcepta.querySelectorAll('[data-at-cc-tipo]').forEach(function (r) { r.checked = r.value === guardado.tipo; });
		actualizarTipo(formAcepta);
	}
	if (formAcepta) {
		formAcepta.addEventListener('submit', function () {
			try {
				var d = { filas: [], tipo: '' };
				formAcepta.querySelectorAll('input[name="filas[]"]:checked').forEach(function (c) { d.filas.push(c.value); });
				camposTexto.forEach(function (n) { var i = formAcepta.querySelector('[name="' + n + '"]'); d[n] = i ? i.value : ''; });
				var t = formAcepta.querySelector('[data-at-cc-tipo]:checked');
				d.tipo = t ? t.value : '';
				sessionStorage.setItem(claveAcepta, JSON.stringify(d));
			} catch (e) {}
		});
	}
	var inicial = <?php echo wp_json_encode($abrir); ?>;
	if (inicial) { abrir(inicial); }
})();
</script>
<?php
}
