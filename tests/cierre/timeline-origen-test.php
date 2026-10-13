<?php
// Correr: php tests/cierre/timeline-origen-test.php   (sin WordPress)
// Origen de cada evento de la línea de tiempo del CRM. Los casos salen de filas reales de PROD (06-oct-2026).
define('AT_TL_PRUEBAS', true);
require_once __DIR__ . '/../../wp-content/mu-plugins/at-timeline-origen.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function hist(string $tipo, string $titulo, int $usuario, string $desc = '', $meta = null): array {
	return ['source' => 'system', 'detail_type' => at_tl_mapa_historial()[$tipo] ?? 'legacy', 'tipo_evento' => $tipo, 'title' => $titulo, 'description' => $desc, 'usuario_id' => $usuario, 'metadata' => $meta];
}

// Lo que antes salía como «Sistema»
ok(at_tl_mapa_historial()['entregable_nota'] === 'entregable_nota' && isset(at_tl_tipos_extra()['entregable_nota']), 'la nota de un entregable tiene tipo propio');
ok(strpos(at_tl_tipos_extra()['entregable_nota']['label'], 'Comentario del cliente') === 0, 'su etiqueta dice «Comentario del cliente»');
foreach (['entregable_version', 'entregable_respuesta', 'whatsapp', 'email_bienvenida'] as $t) {
	ok(isset(at_tl_tipos_extra()[at_tl_mapa_historial()[$t]]), "$t tiene etiqueta propia");
}
ok(at_tl_mapa_historial()['proyecto_creado'] === 'item_proyecto', 'proyecto creado usa la etiqueta de proyecto que ya existía');
ok(!isset(at_tl_mapa_historial()['nota']) && !isset(at_tl_mapa_historial()['reunion']), 'no pisa los tipos que ya tenían nombre');
ok(at_tl_mapa_historial()['conversion'] === 'conversion' && at_tl_tipos_extra()['conversion']['label'] === 'Conversión', 'la conversión tiene nombre propio (antes «Sistema»)');
ok(strpos(at_tl_tipos_extra()['legacy']['label'], 'Sistema') === false, 'la etiqueta genérica ya no dice «Sistema»');

// Cliente
ok(at_tl_origen(hist('entregable_nota', 'Prototipo 3D: nota del cliente sobre la versión 1', 0)) === 'cliente', 'nota de Julio en su entregable: cliente');
ok(at_tl_origen(hist('entregable_nota', 'x', 1)) === 'cliente', 'nota de entregable con sesión iniciada (prueba del equipo): sigue siendo del cliente');
ok(at_tl_origen(hist('conversion', 'Aceptó la propuesta', 0)) === 'cliente', 'aceptó la propuesta: cliente');
ok(at_tl_origen(hist('reunion', 'Llamada de seguimiento agendada', 0, 'El cliente agendó desde el plan abc la llamada.')) === 'cliente', 'llamada agendada por la web: cliente');
ok(at_tl_origen(['source' => 'client', 'detail_type' => 'respuesta_cliente', 'created_by' => 0]) === 'cliente', 'detalle «respuesta del cliente»: cliente');

// Equipo AutomatizaTech
ok(at_tl_origen(hist('entregable_version', 'Prototipo 3D: versión 1 enviada por correo', 1)) === 'equipo', 'versión enviada desde el CRM: equipo');
ok(at_tl_origen(hist('entregable_respuesta', 'Prototipo 3D: respuesta de AT', 1)) === 'equipo', 'respuesta desde el CRM: equipo');
ok(at_tl_origen(hist('conversion', 'Conversión desde Propuesta', 1)) === 'equipo', 'conversión hecha por el equipo: equipo');
ok(at_tl_origen(hist('reunion', '🤝 Reunión presencial de diagnóstico (Isla de Maipo)', 0)) === 'equipo', 'reunión anotada sin sesión: equipo (no automático)');
ok(at_tl_origen(['source' => 'client', 'detail_type' => 'reunion', 'created_by' => 0]) === 'equipo', 'reunión migrada desde la propuesta (created_by 0): equipo');
ok(at_tl_origen(['source' => 'prospect', 'detail_type' => 'propuesta_enviada', 'created_by' => 1]) === 'equipo', 'propuesta enviada: equipo');
ok(at_tl_origen(['source' => 'client', 'detail_type' => 'contratacion', 'created_by' => 1]) === 'equipo', 'cliente contratado a mano: equipo');

// Automático
ok(at_tl_origen(hist('email_bienvenida', 'Correo de bienvenida enviado', 0)) === 'automatico', 'bienvenida al firmar: automático');
ok(at_tl_origen(hist('email_bienvenida', 'Correo de bienvenida enviado', 1)) === 'equipo', 'bienvenida reenviada desde el CRM: equipo');
ok(at_tl_origen(hist('conversion', 'Conversión automática', 0)) === 'automatico', 'conversión sin sesión: automático');
ok(at_tl_origen(['source' => 'client', 'detail_type' => 'contratacion', 'created_by' => 0]) === 'automatico', 'contratación sin autor: automático');
ok(at_tl_origen(['source' => 'prospect', 'detail_type' => 'aviso_operativo', 'created_by' => null]) === 'automatico', 'aviso operativo: automático');
ok(at_tl_origen(['source' => 'prospect', 'detail_type' => 'whatsapp_no_entregado']) === 'automatico', 'WhatsApp no entregado: automático');
ok(at_tl_origen(['source' => 'system', 'detail_type' => 'separator']) === 'automatico', 'hito de conversión: automático');

// metadata.origen explícito gana; datos raros no rompen
ok(at_tl_origen(hist('email_bienvenida', 'x', 0, '', '{"origen":"equipo"}')) === 'equipo', 'metadata.origen explícito gana');
ok(at_tl_origen(hist('email_bienvenida', 'x', 0, '', '{"origen":"hacker"}')) === 'automatico', 'metadata.origen inválido se ignora');
ok(at_tl_origen(hist('email_bienvenida', 'x', 0, '', 'no-json')) === 'automatico', 'metadata que no es JSON se ignora');
ok(at_tl_origen([]) === '' && at_tl_origen_html([]) === '', 'evento sin fuente: sin etiqueta');

// HTML: textos fijos, en plural de equipo, nunca «Luis»
$h = at_tl_origen_html(hist('entregable_respuesta', 'x', 1));
ok(strpos($h, 'Equipo AutomatizaTech') !== false && stripos($h, 'Luis') === false, 'etiqueta del equipo: «Equipo AutomatizaTech», sin «Luis»');
ok(strpos(at_tl_origen_html(hist('entregable_nota', '<script>', 0, '<b>')), '<script>') === false, 'la etiqueta no copia el título ni la descripción');
ok(strpos(at_tl_origen_html(hist('email_bienvenida', 'x', 0)), 'Automático') !== false && strpos(at_tl_origen_html(hist('entregable_nota', 'x', 0)), '👤 Cliente') !== false, 'etiquetas Cliente y Automático');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
