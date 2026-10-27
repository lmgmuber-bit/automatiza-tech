<?php
/**
 * Acciones del colaborador (admin-post.php): agregar una nota al historial y responder un comentario del cliente.
 * Cada una revisa el nonce y vuelve a preguntar at_colab_puede() con el cliente real del dato.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** En pruebas, la redirección se lanza en vez de salir (así una prueba lee a dónde volvió). */
class AtColabRedireccion extends Exception {
	public $url;
	public function __construct(string $url) {
		parent::__construct($url);
		$this->url = $url;
	}
}

function at_colab_volver(string $msg, int $crm_id = 0): void {
	$url = at_colab_url($crm_id, $msg);
	if (defined('AT_COLAB_PRUEBAS')) {
		throw new AtColabRedireccion($url);
	}
	wp_safe_redirect($url);
	exit;
}

function at_colab_accion_nota(): void {
	$crm_id = absint($_POST['crm_id'] ?? 0);
	$nonce = (string) wp_unslash($_POST['_at_colab'] ?? '');
	if ($crm_id <= 0 || !wp_verify_nonce($nonce, 'at_colab_nota_' . $crm_id) || !at_colab_puede($crm_id)) {
		at_colab_volver('sin_acceso');
	}
	$v = at_colab_validar_nota((string) wp_unslash($_POST['nota'] ?? ''));
	if (!$v['ok']) {
		at_colab_volver($v['error'], $crm_id);
	}
	global $wpdb;
	$yo = wp_get_current_user();
	$wpdb->insert($wpdb->prefix . 'crm_historial', [
		'cliente_id' => $crm_id, 'tipo_evento' => 'nota', 'titulo' => 'Nota agregada', 'descripcion' => $v['texto'],
		'usuario_id' => (int) $yo->ID, 'created_at' => current_time('mysql'),
	]);
	$cliente = at_colab_cliente($crm_id);
	$para = function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : (string) get_option('admin_email');
	if ($para !== '' && $cliente) {
		wp_mail(
			$para,
			'Nota de ' . $yo->display_name . ' en ' . $cliente['nombre'],
			$yo->display_name . ' agregó una nota al historial de ' . $cliente['nombre'] . ":\n\n" . $v['texto'] . "\n\nVer: " . at_colab_url($crm_id),
			['Content-Type: text/plain; charset=UTF-8']
		);
	}
	at_colab_volver('nota_ok', $crm_id);
}
add_action('admin_post_at_colab_nota', 'at_colab_accion_nota');

/** La misma secuencia que at_en_accion_responder (inc/entregables/panel.php), con el permiso del colaborador. */
function at_colab_accion_responder(): void {
	$nota_id = absint($_POST['nota_id'] ?? 0);
	$nonce = (string) wp_unslash($_POST['_at_colab'] ?? '');
	$nota = $nota_id > 0 ? at_en_nota($nota_id) : null;
	$e = $nota ? at_en_por_id((int) $nota->entregable_id) : null;
	if (!$nota || !$e || $nota->autor !== 'cliente' || !wp_verify_nonce($nonce, 'at_colab_resp_' . $nota_id) || !at_colab_puede((int) $e->crm_id)) {
		at_colab_volver('sin_acceso');
	}
	$crm = (int) $e->crm_id;
	$nombre = mb_substr((string) wp_get_current_user()->display_name, 0, 120, 'UTF-8');
	$texto = (string) wp_unslash($_POST['texto'] ?? '');
	$v = at_en_validar_nota($nombre, $texto);
	if (!$v['ok']) {
		at_colab_volver($v['error'], $crm);
	}
	// Se reclama la nota antes de guardar nada: dos clics seguidos no crean dos respuestas ni dos correos.
	if (!at_en_reclamar_respuesta((int) $nota->id)) {
		at_colab_volver('ya_respondida', $crm);
	}
	$archivos = at_en_archivos_subidos(isset($_FILES['imagenes']) && is_array($_FILES['imagenes']) ? $_FILES['imagenes'] : []);
	$imagenes = $archivos ? at_en_procesar_imagenes($archivos, (int) $e->id) : [];
	if (is_wp_error($imagenes)) {
		at_en_soltar_respuesta((int) $nota->id);
		at_colab_volver($imagenes->get_error_code(), $crm);
	}
	$id = at_en_agregar_nota((int) $e->id, 'at', $nombre, $texto, $imagenes, '', (int) $nota->version_numero);
	if (is_wp_error($id)) {
		foreach ($imagenes as $img) {
			if (at_en_nombre_imagen_valido((string) $img)) {
				@unlink(at_en_dir_imagenes((int) $e->id) . $img);
			}
		}
		at_en_soltar_respuesta((int) $nota->id);
		at_colab_volver($id->get_error_code(), $crm);
	}
	at_en_historial($crm, 'entregable_respuesta', at_en_cliente($e)['titulo'] . ': respuesta de ' . $nombre, at_en_extracto($v['texto'], 300));
	if (!empty($_POST['avisar']) && !at_en_avisar_respuesta((int) $id)) {
		at_colab_volver('respuesta_sin_aviso', $crm);
	}
	at_colab_volver('respuesta_ok', $crm);
}
add_action('admin_post_at_colab_responder', 'at_colab_accion_responder');
