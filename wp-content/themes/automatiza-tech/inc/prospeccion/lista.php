<?php
/** Rastro · Prospección: lista de prospectos (WP_List_Table). */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

function at_pro_etiquetas_estado(): array {
	return ['por_contactar' => 'Por contactar', 'contactado' => 'Contactado', 'respondio' => 'Respondió', 'formulario' => 'Llenó formulario', 'descartado' => 'Descartado', 'baja' => 'Pidió baja'];
}

function at_pro_iconos_estado(): array {
	return ['por_contactar' => 'dashicons-clock', 'contactado' => 'dashicons-email-alt', 'respondio' => 'dashicons-format-chat', 'formulario' => 'dashicons-yes-alt', 'descartado' => 'dashicons-dismiss', 'baja' => 'dashicons-no'];
}

function at_pro_url_formulario(string $token): string {
	return add_query_arg('r', $token, home_url('/'));
}

function at_pro_url_baja(string $token): string {
	return add_query_arg('baja', $token, home_url('/'));
}

/** Filtros desde la URL, ya saneados y limitados a valores conocidos. */
function at_pro_filtros(): array {
	$g = static fn(string $k): string => isset($_GET[$k]) ? trim(sanitize_text_field(wp_unslash((string) $_GET[$k]))) : '';
	$estado = $g('estado');
	$fuente = $g('fuente');
	$orderby = $g('orderby');
	return [
		'estado' => in_array($estado, at_pro_estados(), true) ? $estado : '',
		'fuente' => in_array($fuente, at_pro_fuentes(), true) ? $fuente : '',
		'rubro' => mb_substr($g('rubro'), 0, 100),
		's' => mb_substr($g('s'), 0, 100),
		'orderby' => in_array($orderby, ['negocio', 'estado', 'created_at'], true) ? $orderby : 'created_at',
		'order' => strtolower($g('order')) === 'asc' ? 'ASC' : 'DESC',
		'paged' => max(1, (int) ($_GET['paged'] ?? 1)),
	];
}

/** WHERE y argumentos para prepare(); $sin_estado omite el estado (conteo de las vistas). */
function at_pro_where(array $f, bool $sin_estado = false): array {
	global $wpdb;
	$c = [];
	$a = [];
	if (!$sin_estado && $f['estado'] !== '') { $c[] = 'estado = %s'; $a[] = $f['estado']; }
	if ($f['fuente'] !== '') { $c[] = 'fuente = %s'; $a[] = $f['fuente']; }
	if ($f['rubro'] !== '') { $c[] = 'rubro = %s'; $a[] = $f['rubro']; }
	if ($f['s'] !== '') {
		$like = '%' . $wpdb->esc_like($f['s']) . '%';
		$c[] = '(negocio LIKE %s OR dominio LIKE %s OR correo LIKE %s OR telefono LIKE %s OR comuna LIKE %s)';
		array_push($a, $like, $like, $like, $like, $like);
	}
	return ['sql' => $c ? 'WHERE ' . implode(' AND ', $c) : '', 'args' => $a];
}

class AT_Prospectos_Lista extends WP_List_Table {
	private array $f;

	public function __construct(array $filtros) {
		parent::__construct(['singular' => 'prospecto', 'plural' => 'prospectos', 'ajax' => false]);
		$this->f = $filtros;
	}

	public function get_columns() {
		return ['cb' => '<input type="checkbox">', 'negocio' => 'Negocio', 'contacto' => 'Contacto', 'senales' => 'Lo que vimos', 'estado' => 'Estado', 'acciones' => 'Acciones'];
	}

	protected function get_sortable_columns() {
		return ['negocio' => ['negocio', false], 'estado' => ['estado', false]];
	}

	protected function get_primary_column_name() {
		return 'negocio';
	}

	/** Sin acciones masivas nativas: su selector se llama `action` y pisaría el de admin-post.php. */
	protected function get_bulk_actions() {
		return [];
	}

	protected function extra_tablenav($which) {
		if ($which !== 'top') { return; }
		echo '<div class="alignleft actions"><label class="screen-reader-text" for="at-pro-accion-masiva">Acción para los marcados</label>'
			. '<select name="accion_masiva" id="at-pro-accion-masiva"><option value="">Acción para los marcados</option><option value="contactado">Marcar contactado</option><option value="descartado">Descartar</option></select> '
			. '<button type="submit" class="button"><span class="dashicons dashicons-yes" aria-hidden="true"></span> Aplicar</button></div>';
	}

	public function prepare_items() {
		global $wpdb;
		$t = at_pro_tabla();
		$this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'negocio'];
		$w = at_pro_where($this->f);
		$total = (int) ($w['args'] ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} {$w['sql']}", ...$w['args'])) : $wpdb->get_var("SELECT COUNT(*) FROM {$t}"));
		$por_pagina = 20;
		$paginas = max(1, (int) ceil($total / $por_pagina));
		$pag = min($this->f['paged'], $paginas);
		$orden = $this->f['orderby'] . ' ' . $this->f['order'] . ', id DESC'; // columnas y sentido salen de lista blanca
		$sql = "SELECT * FROM {$t} {$w['sql']} ORDER BY {$orden} LIMIT %d OFFSET %d";
		$this->items = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($w['args'], [$por_pagina, ($pag - 1) * $por_pagina])), ARRAY_A);
		$this->set_pagination_args(['total_items' => $total, 'per_page' => $por_pagina, 'total_pages' => $paginas]);
	}

	public function column_cb($i) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $i['id'] . '" aria-label="Marcar ' . esc_attr($i['negocio']) . '">';
	}

	public function column_negocio($i) {
		$h = '<strong>' . esc_html($i['negocio']) . '</strong>';
		$sub = array_filter([$i['rubro'], $i['comuna']]);
		if ($sub) { $h .= '<br><span class="description">' . esc_html(implode(' · ', $sub)) . '</span>'; }
		if ($i['web'] !== '') {
			$h .= '<br><a href="' . esc_url($i['web'], ['http', 'https']) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> ' . esc_html($i['dominio'] ?: $i['web']) . '</a>';
		}
		return $h . '<br><span class="description">' . esc_html(['lead_scout' => 'Lead Scout', 'sellbird' => 'Sellbird', 'manual' => 'Manual', 'instagram' => 'Instagram'][$i['fuente']] ?? $i['fuente']) . '</span>';
	}

	public function column_contacto($i) {
		$h = [];
		if ($i['telefono'] !== '') { $h[] = '<span class="dashicons dashicons-phone" aria-hidden="true"></span> ' . esc_html('+' . $i['telefono']); }
		if ($i['correo'] !== '') { $h[] = '<span class="dashicons dashicons-email" aria-hidden="true"></span> ' . esc_html($i['correo']); }
		return $h ? implode('<br>', $h) : '<span class="description">Solo web</span>';
	}

	public function column_senales($i) {
		$h = $i['senales'] !== '' ? esc_html($i['senales']) : '<span class="description">Sin señales anotadas</span>';
		if ($i['servicio_sugerido'] !== '') { $h .= '<br><span class="description">Servicio: ' . esc_html($i['servicio_sugerido']) . '</span>'; }
		return $h;
	}

	public function column_estado($i) {
		$et = at_pro_etiquetas_estado();
		$ic = at_pro_iconos_estado();
		$h = '<span class="at-pro-estado at-pro-estado--' . esc_attr($i['estado']) . '"><span class="dashicons ' . esc_attr($ic[$i['estado']] ?? 'dashicons-marker') . '" aria-hidden="true"></span> ' . esc_html($et[$i['estado']] ?? $i['estado']) . '</span>';
		foreach (['contactado_at' => 'Contactado', 'respondio_at' => 'Respondió', 'formulario_at' => 'Formulario'] as $c => $et2) {
			if (!empty($i[$c])) { $h .= '<br><span class="description">' . esc_html($et2 . ' ' . mysql2date('d-m-Y', $i[$c])) . '</span>'; }
		}
		return $h;
	}

	private function url_estado(array $i, string $estado, string $canal = ''): string {
		$args = ['action' => 'at_pro_estado', 'id' => (int) $i['id'], 'estado' => $estado, '_wpnonce' => wp_create_nonce('at_pro_estado_' . (int) $i['id'])];
		if ($canal !== '') { $args['canal'] = $canal; }
		return esc_url(add_query_arg($args, admin_url('admin-post.php')));
	}

	public function column_acciones($i) {
		$msg = at_pro_mensaje($i, at_pro_url_formulario($i['token']), at_pro_url_baja($i['token']));
		$neg = esc_attr($i['negocio']);
		$b = [];
		if ($i['estado'] !== 'baja') {
			$b[] = '<button type="button" class="button button-small at-pro-copiar" data-mensaje="' . esc_attr($msg) . '" aria-label="Copiar mensaje para ' . $neg . '"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copiar mensaje</button>';
			$asunto = at_pro_correo_asunto($i);
			$html_correo = at_pro_correo_html($i, at_pro_url_formulario($i['token']), at_pro_url_baja($i['token']));
			$b[] = '<button type="button" class="button button-small at-pro-ver-correo" aria-label="Ver correo para ' . $neg . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> Ver correo</button>';
			$b[] = '<button type="button" class="button button-small at-pro-copiar-correo" data-asunto="' . esc_attr($asunto) . '" data-html="' . esc_attr($html_correo) . '" data-texto="' . esc_attr($msg) . '" data-cco="' . esc_attr(at_pro_cco()) . '" aria-label="Copiar correo con diseño para ' . $neg . '"><span class="dashicons dashicons-email-alt2" aria-hidden="true"></span> Copiar correo con diseño</button>';
			if ($i['correo'] !== '') {
				$b[] = '<a class="button button-small" href="' . esc_attr('mailto:' . $i['correo'] . '?subject=' . rawurlencode($asunto) . (at_pro_cco() !== '' ? '&bcc=' . rawurlencode(at_pro_cco()) : '') . '&body=' . rawurlencode($msg)) . '" aria-label="Abrir en el correo: ' . $neg . '"><span class="dashicons dashicons-external" aria-hidden="true"></span> Abrir en el correo</a>';
			}
			// Primer contacto solo por correo (decisión de Luis, 15-nov-2026): escribir en frío por WhatsApp va contra la
			// política de WhatsApp Business y arriesga el bloqueo del número. WhatsApp aparece recién cuando el prospecto respondió.
			if ($i['telefono'] !== '' && in_array($i['estado'], ['respondio', 'formulario'], true)) {
				$b[] = '<a class="button button-small" href="' . esc_attr('https://wa.me/' . $i['telefono']) . '" target="_blank" rel="noopener noreferrer" aria-label="Abrir WhatsApp con ' . $neg . '"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> Abrir WhatsApp</a>';
			}
			$canal = $i['correo'] !== '' ? 'correo' : 'web';
			foreach ([['contactado', 'Marcar contactado', 'dashicons-email-alt', $canal], ['respondio', 'Marcar respondió', 'dashicons-format-chat', ''], ['descartado', 'Descartar', 'dashicons-dismiss', '']] as [$est, $txt, $ico, $can]) {
				if (at_pro_transicion_valida($i['estado'], $est)) {
					$b[] = '<a class="button button-small" href="' . $this->url_estado($i, $est, $can) . '" aria-label="' . esc_attr($txt . ': ' . $i['negocio']) . '"><span class="dashicons ' . $ico . '" aria-hidden="true"></span> ' . esc_html($txt) . '</a>';
				}
			}
		}
		if (!empty($i['crm_cliente_id'])) {
			$b[] = '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=automatiza-crm-ficha&id=' . (int) $i['crm_cliente_id'])) . '"><span class="dashicons dashicons-id" aria-hidden="true"></span> Ver en CRM</a>';
		}
		return '<div class="at-pro-acciones">' . implode(' ', $b) . '</div>';
	}

	public function no_items() {
		echo 'Todavía no hay prospectos aquí. Importa un CSV o agrega uno a mano.';
	}
}
