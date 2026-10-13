<?php
/** Propuestas › Ajustes del cierre: datos de transferencia para la bienvenida. Los escribe Luis. */
if (!defined('ABSPATH')) {
	exit;
}

function at_cc_opciones_cierre(): array {
	return [
		'at_cc_banco'         => 'Banco',
		'at_cc_tipo_cuenta'   => 'Tipo de cuenta',
		'at_cc_numero_cuenta' => 'Número de cuenta',
		'at_cc_titular'       => 'Titular',
		'at_cc_rut_titular'   => 'RUT del titular',
		'at_cc_correo_pago'   => 'Correo para avisar el pago',
		'at_cc_whatsapp_at'   => 'WhatsApp de AutomatizaTech (con 56 adelante)',
		'at_cc_correo_avisos' => 'Correo principal del cierre (tus avisos y las respuestas de los clientes)',
		'at_cc_correo_copia'  => 'Copia oculta de los correos del cierre',
	];
}

/** Las tres opciones que son direcciones de correo: se sanean con sanitize_email() y se dibujan con type="email". */
function at_cc_opciones_correo_cierre(): array {
	return ['at_cc_correo_pago', 'at_cc_correo_avisos', 'at_cc_correo_copia'];
}

add_action('admin_init', function () {
	foreach (at_cc_opciones_cierre() as $k => $_) {
		register_setting('at_cc_ajustes', $k, [
			'type'              => 'string',
			'sanitize_callback' => in_array($k, at_cc_opciones_correo_cierre(), true) ? 'sanitize_email' : 'sanitize_text_field',
			'default'           => '',
		]);
	}
});

add_action('admin_menu', function () {
	add_submenu_page('automatiza-proposals', 'Ajustes del cierre', 'Ajustes del cierre', 'manage_options', 'at-cc-ajustes', 'at_cc_render_ajustes');
}, 20);

function at_cc_render_ajustes(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.');
	}
	?>
	<div class="wrap">
		<h1>Ajustes del cierre de cliente</h1>
		<p>Estos datos van en el correo de bienvenida, en el paso del anticipo. Si falta alguno, el correo dice que los datos de pago van por separado.</p>
		<p>El correo principal recibe tus avisos del cierre (cliente aceptó, revisar y firmar, mensajes por WhatsApp) y las respuestas de los clientes. La copia oculta recibe una copia de esos avisos y de los correos de bienvenida y de pedido de respuesta que le llegan al cliente; el cliente no la ve. Las respuestas de los clientes llegan solo al correo principal, y los correos del contrato (revisión, firma y copia firmada) no llevan esta copia. Si el principal queda vacío, se usa el correo de administrador de WordPress.</p>
		<form method="post" action="options.php">
			<?php settings_fields('at_cc_ajustes'); ?>
			<table class="form-table" role="presentation">
				<?php foreach (at_cc_opciones_cierre() as $k => $t): ?>
				<?php $tipo = in_array($k, at_cc_opciones_correo_cierre(), true) ? 'email' : 'text'; ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr($k); ?>"><?php echo esc_html($t); ?></label></th>
					<td><input type="<?php echo esc_attr($tipo); ?>" class="regular-text" id="<?php echo esc_attr($k); ?>" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr((string) get_option($k, '')); ?>"></td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button('Guardar'); ?>
		</form>
	</div>
	<?php
}

function at_cc_datos_banco(): array {
	return [
		'banco'   => (string) get_option('at_cc_banco', ''),
		'tipo'    => (string) get_option('at_cc_tipo_cuenta', ''),
		'numero'  => (string) get_option('at_cc_numero_cuenta', ''),
		'titular' => (string) get_option('at_cc_titular', ''),
		'rut'     => (string) get_option('at_cc_rut_titular', ''),
	];
}

function at_cc_whatsapp_at(): string {
	$n = trim((string) get_option('at_cc_whatsapp_at', ''));
	return $n !== '' ? $n : '56927002984';
}

/** Correo principal del cierre: tus avisos y las respuestas de los clientes. Sin uno válido, el de administrador de WordPress. */
function at_cc_correo_avisos(): string {
	$c = trim((string) get_option('at_cc_correo_avisos', ''));
	return is_email($c) ? $c : (string) get_option('admin_email');
}

/** Copia oculta de los correos del cierre; '' si no hay una válida. */
function at_cc_correo_copia(): string {
	$c = trim((string) get_option('at_cc_correo_copia', ''));
	return is_email($c) ? $c : '';
}

/** Cabecera 'Bcc: <copia>' para un correo del cierre; [] si no hay copia o es igual (sin distinguir
 *  mayúsculas, recortada) al destinatario, para no duplicar el mismo correo. */
function at_cc_cabecera_copia(string $destinatario): array {
	$copia = at_cc_correo_copia();
	if ($copia === '' || strtolower(trim($destinatario)) === strtolower($copia)) {
		return [];
	}
	return ['Bcc: ' . $copia];
}
