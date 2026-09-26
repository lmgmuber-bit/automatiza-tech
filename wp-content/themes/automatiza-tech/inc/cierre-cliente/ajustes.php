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
	];
}

add_action('admin_init', function () {
	foreach (at_cc_opciones_cierre() as $k => $_) {
		register_setting('at_cc_ajustes', $k, [
			'type'              => 'string',
			'sanitize_callback' => $k === 'at_cc_correo_pago' ? 'sanitize_email' : 'sanitize_text_field',
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
		<form method="post" action="options.php">
			<?php settings_fields('at_cc_ajustes'); ?>
			<table class="form-table" role="presentation">
				<?php foreach (at_cc_opciones_cierre() as $k => $t): ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr($k); ?>"><?php echo esc_html($t); ?></label></th>
					<td><input type="text" class="regular-text" id="<?php echo esc_attr($k); ?>" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr((string) get_option($k, '')); ?>"></td>
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
