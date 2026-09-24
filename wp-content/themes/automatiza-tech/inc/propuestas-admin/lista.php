<?php
/**
 * Lista de propuestas (WP_List_Table): buscador, vistas por estado, fechas, orden, paginación y borrado masivo.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AT_Propuestas_Lista extends WP_List_Table {
    /** @var array Filtros normalizados (at_pa_normalizar_filtros). */
    private $f;
    /** @var array Conteo por grupo (at_pa_contar_grupos). */
    private $conteos = [];

    public function __construct(array $filtros) {
        parent::__construct(['singular' => 'propuesta', 'plural' => 'propuestas', 'ajax' => false]);
        $this->f = $filtros;
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox">',
            'empresa'  => 'Empresa / cliente',
            'contacto' => 'Contacto',
            'estado'   => 'Estado',
            'creada'   => 'Creada',
        ];
    }

    protected function get_sortable_columns() {
        return ['empresa' => ['company_name', false], 'estado' => ['status', false], 'creada' => ['created_at', true]];
    }

    protected function get_primary_column_name() {
        return 'empresa';
    }

    protected function get_bulk_actions() {
        return ['borrar' => 'Borrar'];
    }

    public function prepare_items() {
        global $wpdb;
        $t = $wpdb->prefix . 'automatiza_propuestas';
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'empresa'];

        $w = at_pa_where($this->f, [$wpdb, 'esc_like']);
        $sql_total = "SELECT COUNT(*) FROM $t {$w['sql']}";
        $total = (int) ($w['args'] ? $wpdb->get_var($wpdb->prepare($sql_total, ...$w['args'])) : $wpdb->get_var($sql_total));
        $lim = at_pa_limites($this->f['paged'], $this->get_items_per_page('propuestas_por_pagina', 20), $total);

        $sql = "SELECT id, unique_link_id, client_name, company_name, client_email, phone, status, flujo, created_at
                FROM $t {$w['sql']} " . at_pa_order_sql($this->f) . ' LIMIT %d OFFSET %d';
        $this->items = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($w['args'], [$lim['per_page'], $lim['offset']])));
        $this->set_pagination_args(['total_items' => $total, 'per_page' => $lim['per_page'], 'total_pages' => $lim['total_pages']]);

        $filas = $wpdb->get_results("SELECT status, COUNT(*) AS n FROM $t GROUP BY status", ARRAY_A);
        $this->conteos = at_pa_contar_grupos(array_column($filas ?: [], 'n', 'status'));
    }

    protected function get_views() {
        $base = admin_url('admin.php?page=automatiza-proposals');
        $q = at_pa_query_volver(array_merge($this->f, ['grupo' => '', 'paged' => 1]));
        $actual = $this->f['grupo'];
        $vista = function ($clave, $etiqueta, $n) use ($base, $q, $actual) {
            $args = $clave === 'todas' ? $q : $q + ['grupo' => $clave];
            $es = ($clave === 'todas' && $actual === '') || $clave === $actual;
            return sprintf('<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg($args, $base)), $es ? ' class="current" aria-current="page"' : '', esc_html($etiqueta), (int) $n);
        };
        $views = ['todas' => $vista('todas', 'Todas', $this->conteos['todas'] ?? 0)];
        foreach (at_pa_grupos_estado() + ['otros' => ['etiqueta' => 'Otros']] as $k => $g) {
            if (!empty($this->conteos[$k])) {
                $views[$k] = $vista($k, $g['etiqueta'], $this->conteos[$k]);
            }
        }
        return $views;
    }

    private function url_ficha($item) {
        $q = ['page' => 'automatiza-proposals', 'edit_id' => (int) $item->id];
        $volver = http_build_query(at_pa_query_volver($this->f));
        if ($volver !== '') {
            // add_query_arg no codifica: sin esto, el «&» interno de $volver se lee como
            // separador de parámetros de nivel superior y la ficha pierde grupo/orden/página.
            $q['volver'] = rawurlencode($volver);
        }
        return add_query_arg($q, admin_url('admin.php'));
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="proposal_ids[]" value="%d">', (int) $item->id);
    }

    protected function column_empresa($item) {
        $empresa = (string) $item->company_name !== '' ? (string) $item->company_name : '(sin empresa)';
        $acciones = ['abrir' => sprintf('<a href="%s">Abrir ficha</a>', esc_url($this->url_ficha($item)))];
        if (!empty($item->unique_link_id)) {
            $acciones['ver'] = sprintf('<a href="%s" target="_blank" rel="noopener">Ver presentación</a>',
                esc_url(get_site_url() . '/ver-presentacion.php?id=' . rawurlencode($item->unique_link_id)));
        }
        $acciones['borrar'] = sprintf('<a href="%s" class="at-borrar" onclick="return confirm(\'¿Borrar esta propuesta? No se puede deshacer.\');">Borrar</a>',
            esc_url(wp_nonce_url(admin_url('admin.php?page=automatiza-proposals&delete_id=' . (int) $item->id), 'delete_proposal_' . (int) $item->id)));
        return sprintf('<strong><a class="row-title" href="%s">%s</a></strong><div class="at-cliente">%s</div>%s',
            esc_url($this->url_ficha($item)), esc_html($empresa), esc_html((string) $item->client_name), $this->row_actions($acciones));
    }

    protected function column_contacto($item) {
        $partes = [];
        if (!empty($item->client_email)) {
            $partes[] = sprintf('<a href="%s">%s</a>', esc_url('mailto:' . $item->client_email), esc_html($item->client_email));
        }
        if (!empty($item->phone)) {
            $partes[] = esc_html($item->phone);
        }
        return $partes ? implode('<br>', $partes) : '—';
    }

    protected function column_estado($item) {
        $e = at_pa_estado_etiqueta($item->status);
        $v3 = $item->flujo === 'v3' ? ' <span class="at-marca-v3">v3</span>' : '';
        return sprintf('<span class="at-estado %s">%s</span>%s', esc_attr($e['clase']), esc_html($e['etiqueta']), $v3);
    }

    protected function column_creada($item) {
        return esc_html(at_pa_fecha_corta($item->created_at));
    }

    public function no_items() {
        printf('No hay propuestas con esos filtros. <a href="%s">Limpiar filtros</a>', esc_url(admin_url('admin.php?page=automatiza-proposals')));
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        echo '<div class="alignleft actions"><button type="submit" name="bulk_action" value="borrar_marcadas" class="button at-borrar-marcadas">🗑️ Borrar marcadas</button></div>';
        printf('<div class="alignleft actions at-fechas"><label>Desde <input type="date" name="desde" value="%s"></label> <label>Hasta <input type="date" name="hasta" value="%s"></label> ',
            esc_attr($this->f['desde']), esc_attr($this->f['hasta']));
        submit_button('Filtrar', '', 'filtrar', false);
        echo '</div>';
    }
}

/** Pantalla de lista completa. $message ya viene armado por at_pa_procesar_acciones (partes dinámicas escapadas). */
function at_pa_render_lista(string $message): void {
    $f = at_pa_normalizar_filtros(wp_unslash($_GET));
    // Que la paginación y el orden no repitan una acción ya hecha (borrado) al armar sus enlaces.
    $_SERVER['REQUEST_URI'] = remove_query_arg(['action', 'action2', 'proposal_ids', '_wpnonce', '_wp_http_referer', 'delete_id', 'filtrar', 'bulk_action'], $_SERVER['REQUEST_URI']);
    $tabla = new AT_Propuestas_Lista($f);
    $tabla->prepare_items();
    echo '<div class="wrap at-pa">';
    echo '<h1 class="wp-heading-inline">Propuestas</h1>';
    if ($f['s'] !== '') {
        printf('<span class="subtitle">Resultados para «%s»</span>', esc_html($f['s']));
    }
    echo '<hr class="wp-header-end">';
    echo $message;
    foreach ($f['avisos'] as $a) {
        printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($a));
    }
    $tabla->views();
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="automatiza-proposals">';
    if ($f['grupo'] !== '') {
        printf('<input type="hidden" name="grupo" value="%s">', esc_attr($f['grupo']));
    }
    $tabla->search_box('Buscar', 'at-propuestas');
    $tabla->display();
    echo '</form></div>';
}
