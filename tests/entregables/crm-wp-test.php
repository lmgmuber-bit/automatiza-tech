<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/crm-wp-test.php
// Comprueba que el mu-plugin llama al módulo en los tres puntos (pestaña, portal, lista) y que la marca de la lista sale.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
$src = (string) file_get_contents(__DIR__ . '/../../wp-content/mu-plugins/crm-ai-completo.php');
ok(substr_count($src, "function_exists('at_en_render_pestana')") === 2 && strpos($src, 'data-target="tab-entregables"') !== false && strpos($src, 'id="tab-entregables"') !== false && strpos($src, 'at_en_render_pestana($cliente);') !== false, 'botón y contenido de la pestaña Entregables');
ok(strpos($src, "function_exists('at_en_html_resumen_portal') ? at_en_html_resumen_portal(\$h) : ''") !== false, 'resumen en el portal');
ok(strpos($src, "function_exists('at_en_marca_lista') ? at_en_marca_lista((int) (is_array(\$item) ? \$item['id'] : \$item->id)) : ''") !== false, 'marca en la lista');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', '');
at_en_agregar_nota($id, 'cliente', 'C', 'pendiente', [], '');
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
$tabla = new AutomatizaTech_Clientes_List_Table();
ok(strpos($tabla->column_estado(['id' => $fx['crm'], 'estado' => 'prueba']), '🔴 1') !== false, 'la lista muestra 🔴 1 en el estado');
fin();
