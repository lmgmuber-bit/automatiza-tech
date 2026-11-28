<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-panel-wp-test.php
// Estado del proyecto, Etapa 2 — Task 7: sección «Avance» de la pestaña del plan y sus acciones admin-post. Cada acción
// corre en un proceso aparte (accion-wp-test-run.php), que anota y contesta las llamadas a n8n (ok o caído).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_render_seccion', 'at_av_mensajes', 'at_av_accion_importar', 'at_av_accion_publicar');
at_av_migrar_esquema();
if (!at_av_columna_lista()) {
	fwrite(STDERR, "La columna avance no está lista en el sitio de prueba.\n");
	exit(2);
}
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-avance-panel@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-avance-panel@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
$crm = (int) at_pt_plan($id)->crm_cliente_id;
$cod = (string) at_pt_plan($id)->codigo;
$payload_antes = (string) at_pt_plan($id)->payload;
$tmp = sys_get_temp_dir() . '/at-av-panel-' . getmypid() . '/';
wp_mkdir_p($tmp);
register_shutdown_function(function () use ($tmp, $id) {
	foreach (glob($tmp . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($tmp);
	$d = at_av_dir($id)['dir'];
	foreach (glob($d . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($d);
});
$correr = fn(string $accion, array $post, string $n8n = 'ok') => pt_correr_accion($accion, $admin, 'at_pt_plan_' . $id, $post + ['plan_id' => (string) $id, 'crm_id' => (string) $crm], $n8n);
$msg = fn(array $r): string => (string) (pt_query($r['redirect'])['pt_msg'] ?? '');
$leer = fn(): array => at_av_leer(at_pt_plan($id));
$hist = fn(string $tipo): int => (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = %s", $crm, $tipo));
$archivo = [
	'formato' => 'at-cierre-v1', 'plan' => $cod,
	'cierre' => ['titulo' => 'Diseño terminado', 'bloques' => ['Diseño'], 'fecha_real' => '2026-10-10',
		'antes' => ['texto' => 'Sin maqueta.', 'evidencias' => ['visitas']], 'despues' => ['texto' => 'Maqueta aprobada.', 'evidencias' => ['portada', 'sitio']]],
	'evidencias' => [
		['ref' => 'visitas', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4', 'fecha' => '2026-10-09'],
		['ref' => 'portada', 'tipo' => 'captura', 'titulo' => 'Portada', 'archivo' => 'portada.png'],
		['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/'],
	],
];
$im = imagecreatetruecolor(40, 30);
imagepng($im, $tmp . 'portada.png');
imagedestroy($im);

$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo)]);
ok($msg($r) === 'av_no_enviado' && $leer()['cierres'] === [], '1) plan que no está enviado: no se carga avance');
ptc_estado($id, 'enviado');

$malo = $archivo;
$malo['evidencias'][0]['fuente'] = '';
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($malo)]);
ok($msg($r) === 'av_import_error' && $leer()['cierres'] === [], '2) cifra sin fuente: no se guarda nada (Review Focus 4)');
$r = $correr('at_av_importar', ['cierre_texto' => '{roto']);
ok($msg($r) === 'av_import_error' && $leer()['cierres'] === [], '2) JSON roto: no se guarda nada');

$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo)]);
$av = $leer();
$cid = (string) ($av['cierres'][0]['id'] ?? '');
ok($msg($r) === 'av_importado_faltan' && count($av['cierres']) === 1 && $av['cierres'][0]['estado'] === 'borrador' && $r['n8n'] === [], '3) importado en borrador, falta la portada y no se pide render (R4)');
$r = $correr('at_av_publicar', ['cierre_id' => $cid]);
ok($msg($r) === 'av_no_publicable' && $leer()['cierres'][0]['estado'] === 'borrador', '4) con archivos pendientes no se publica');
ok($hist('hito_cierre_publicado') === 0, '21) un cierre que no se pudo publicar no deja «hito_cierre_publicado» en el historial');

$files = ['archivos' => ['name' => ['portada.png'], 'type' => ['image/png'], 'tmp_name' => [$tmp . 'portada.png'], 'error' => [0], 'size' => [filesize($tmp . 'portada.png')]]];
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo), '__files' => $files]);
$av = $leer();
ok($msg($r) === 'av_importado' && count($av['cierres']) === 1 && $av['cierres'][0]['id'] === $cid && at_av_faltan_archivos($av, $cid) === [], '5) reimportar con el archivo: mismo cierre, ya no falta nada');

$r = $correr('at_av_publicar', ['cierre_id' => $cid]);
$n = $r['n8n'][0] ?? ['url' => '', 'cuerpo' => []];
ok($msg($r) === 'av_publicado' && $leer()['cierres'][0]['estado'] === 'publicado' && str_contains($n['url'], 'plan-v1-render') && ($n['cuerpo']['modo'] ?? '') === 'estado' && !isset($n['cuerpo']['previa']), '6) publicar pide «Estado del proyecto» a n8n (modo estado)');
ok((string) at_pt_plan($id)->payload === $payload_antes && (string) at_pt_plan($id)->estado === 'enviado', '6) el plan firmado no cambia (D3)');

$r = $correr('at_av_borrador', ['cierre_id' => $cid]);
ok($msg($r) === 'av_borrador' && $leer()['cierres'][0]['estado'] === 'borrador' && $leer()['render'] === at_av_registro_vacio() && $r['n8n'] === [], '7) volver a borrador el único publicado: la presentación deja de mostrarse y no se pide render');
$r = $correr('at_av_publicar', ['cierre_id' => $cid], 'caido');
ok($msg($r) === 'av_publicado_sin_render' && $leer()['cierres'][0]['estado'] === 'publicado' && $leer()['render']['estado'] === 'error', '7) n8n caído al publicar: queda publicado y el render en error (Review Focus 5)');
$r = $correr('at_av_reintentar', []);
ok($msg($r) === 'av_reintento' && (($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'estado'), '8) «Reintentar» vuelve a pedir el render');
$r = $correr('at_av_previa', []);
ok($msg($r) === 'av_previa' && (($r['n8n'][0]['cuerpo']['previa'] ?? null) === true), '9) la vista previa va con previa: true (R3)');

// --- Avisar al cliente: correo de cierre (prueba y cliente) y WhatsApp. El proceso hijo nunca envía un correo (pre_wp_mail).
at_pt_tomar_detalles($id);
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'prueba', 'mensaje' => 'Hola X & Y']);
$det_prueba = implode(' | ', at_pt_tomar_detalles($id));
ok($msg($r) === 'av_correo_prueba_sin_pdf' && $leer()['cierres'][0]['correo']['prueba_at'] !== null && $leer()['cierres'][0]['correo']['enviado_at'] === null, '20) «Enviarme una prueba» desde el panel: anota la prueba y no el envío al cliente');
ok(str_contains($det_prueba, 'Motivo: la presentación todavía no está lista'), '20) una prueba que salió sin PDF dice por qué en el detalle y el mensaje lo avisa (no el «ok» de siempre)');
ok($leer()['cierres'][0]['correo']['mensaje'] === 'Hola X & Y', '20) la prueba guarda el mensaje de Luis en el cierre');
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
// AT-CRM-001: el índice sigue el orden de operación; los formularios largos nacen plegados.
preg_match_all('/<details class="at-pt-seccion" id="([^"]+)"/', $h, $secciones);
ok(($secciones[1] ?? []) === array_map(fn($seccion) => $seccion . '-' . $id, ['at-pt-plan', 'at-av-cierres', 'at-av-formulario', 'at-av-bloques', 'at-av-listas', 'at-av-presentacion']), 'CRM-001) las seis secciones plegables mantienen el orden acordado e identifican su plan');
ok(str_contains($h, 'Qué hacer ahora') && strpos($h, 'class="at-pt-ahora"') < strpos($h, 'class="at-pt-indice"') && substr_count($h, '<a href="#at-') === 6, 'CRM-001) resumen antes del índice de seis enlaces a las secciones');
ok(str_contains($h, 'id="at-av-formulario-' . $id . '"><summary>') && str_contains($h, 'id="at-av-bloques-' . $id . '"><summary>') && str_contains($h, 'id="at-av-listas-' . $id . '"><summary>') && str_contains($h, 'id="at-pt-editor-' . $id . '"><summary>'), 'CRM-001) nuevo cierre, bloques, cambios y editor largo empiezan plegados');
ok(str_contains($h, 'id="at-av-nuevo"') && str_contains($h, 'id="at-av-' . $cid . '"') && str_contains($h, 'aria-label="Cierres de hito" tabindex="0"><table') && str_contains($h, 'aria-label="Bloques del plan" tabindex="0"><table'), 'CRM-001) anclas conservadas y tablas con contenedor de desplazamiento accesible');
$plan_indice = ptc_plan(ptc_contrato($c['tech'], $m . '-indice', null));
$id_indice = (int) $plan_indice->id;
ptc_sembrar($id_indice);
ptc_estado($id_indice, 'enviado');
ob_start();
at_pt_render_plan(at_pt_plan($id_indice), $crm);
$h_otro_plan = (string) ob_get_clean();
preg_match_all('/id="(at-(?:pt-(?:ahora-titulo|plan|editor)|av-(?:cierres|formulario|bloques|listas|presentacion))-\d+)"/', $h . $h_otro_plan, $ids_nuevos);
ok(count($ids_nuevos[1]) === 16 && count(array_unique($ids_nuevos[1])) === 16, 'CRM-001) dos planes renderizados no duplican los ocho IDs nuevos de cada plan');
ok(str_contains($h_otro_plan, 'aria-labelledby="at-pt-ahora-titulo-' . $id_indice . '"') && str_contains($h_otro_plan, 'href="#at-av-formulario-' . $id_indice . '"') && !str_contains($h_otro_plan, 'href="#at-av-formulario-' . $id . '"'), 'CRM-001) el resumen y los enlaces del segundo plan apuntan a sus propios elementos');
ok(preg_match('/<textarea name="mensaje"[^>]*>Hola X &amp; Y<\/textarea>/u', $h) === 1, '20) el recuadro del mensaje del correo viene con el mensaje de la prueba, escapado (Luis lo manda al cliente sin reescribirlo)');
ok(str_contains($h, 'Ojo: la presentación del cliente todavía no incluye este cierre; espera el «Lista» antes de mandarlo.'), '20) WhatsApp: con la presentación sin terminar avisa con «Ojo…» (sin bloquear el botón)');
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'cliente']);
ok($msg($r) === 'av_correo_render_no_listo' && $leer()['cierres'][0]['correo']['enviado_at'] === null, '20) «Enviar al cliente» con la presentación sin terminar: no sale y lo dice');
at_pt_tomar_detalles($id);
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'prueba', 'mensaje' => 'Mira http://sitio.easypanel.host/x']);
$det = implode(' | ', at_pt_tomar_detalles($id));
ok($msg($r) === 'av_correo_interno' && str_contains($det, 'Enlace o texto interno: '), '20) un enlace al servidor interno: no se envía y el detalle dice dónde (D8)');
$r = $correr('at_av_correo', ['cierre_id' => 'cXXXXXX', 'destino' => 'prueba']);
ok($msg($r) === 'av_correo_sin_cierre', '20) un cierre que no existe: mensaje propio');
$r = $correr('at_av_correo', ['cierre_id' => 'no-es-un-id', 'destino' => 'cliente']);
ok($msg($r) === 'av_correo_sin_cierre', '20) un id mal formado: se trata como cierre inexistente');
$todas = at_av_mensajes();
$faltan_msgs = array_filter(['av_correo_prueba', 'av_correo_prueba_sin_pdf', 'av_correo_cliente', 'av_correo_cliente_sin_pdf', 'av_correo_cliente_aviso', 'av_correo_no_publicado', 'av_correo_render_no_listo', 'av_correo_sin_correo', 'av_correo_interno', 'av_correo_ya_enviado', 'av_correo_enviando', 'av_correo_correo_fallo', 'av_correo_sin_cierre', 'av_correo_sin_plan', 'av_whatsapp_sin_telefono'], fn($k) => !isset($todas[$k]));
ok($faltan_msgs === [], '20) hay mensaje para cada motivo del correo y del WhatsApp' . ($faltan_msgs ? ' (faltan: ' . implode(', ', $faltan_msgs) . ')' : ''));

// Con la presentación lista (rehecha después de publicar), el envío al cliente sale; el PDF no se baja en la prueba.
$av = $leer();
$av['render'] = array_merge(at_av_registro_vacio(), ['estado' => 'listo', 'at' => current_time('mysql'), 'view_url' => 'https://example.com/presentacion']);
at_av_guardar($id, $av);
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
ok(!str_contains($h, 'Ojo: la presentación del cliente todavía no incluye este cierre') && str_contains($h, 'Enviar por mi WhatsApp'), '20) WhatsApp: con la presentación lista y posterior a la publicación no hay «Ojo…» y el botón sigue');
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'cliente', 'mensaje' => 'Gracias']);
$e1 = $leer()['cierres'][0]['correo'];
$det = implode(' | ', at_pt_tomar_detalles($id));
ok($msg($r) === 'av_correo_cliente_sin_pdf' && $e1['enviado_at'] !== null && $e1['a'] === 'prueba-avance-panel@example.com' && str_contains($det, 'Motivo: '), '20) «Enviar al cliente» sale, anota el envío y dice por qué fue sin PDF');
ok($hist('hito_cierre_correo') === 1, '20) el envío al cliente queda en el historial del CRM');
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'cliente']);
ok($msg($r) === 'av_correo_ya_enviado' && $hist('hito_cierre_correo') === 1, '20) sin «Reenviar», un cierre ya enviado no se manda otra vez');
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'cliente', 'reenviar' => '1']);
ok($msg($r) === 'av_correo_cliente_sin_pdf' && $hist('hito_cierre_correo') === 2, '20) con «Reenviar» sí sale otra vez');
at_pt_tomar_detalles($id);

$r = $correr('at_av_whatsapp', ['cierre_id' => $cid]);
ok(str_starts_with($r['redirect'], 'https://wa.me/') && $leer()['cierres'][0]['whatsapp']['abierto_at'] !== null && $hist('hito_cierre_whatsapp') === 1, '20) «Enviar por mi WhatsApp» abre wa.me y anota que se abrió');
$r = $correr('at_av_whatsapp', ['cierre_id' => 'cXXXXXX']);
ok($msg($r) === 'av_correo_sin_cierre' && !str_contains($r['redirect'], 'wa.me'), '20) WhatsApp de un cierre inexistente: vuelve a la ficha con aviso');
$r = pt_correr_accion('at_av_correo', 0, 'at_pt_plan_' . $id, ['cierre_id' => $cid, 'destino' => 'cliente', 'reenviar' => '1', 'plan_id' => (string) $id, 'crm_id' => (string) $crm]);
ok($r['redirect'] === '' && $hist('hito_cierre_correo') === 2, '20) sin sesión ni nonce válido el correo no sale');
ok($hist('hito_cierre_publicado') >= 1, '21) publicar deja «hito_cierre_publicado» en el historial del CRM');

$r = $correr('at_av_bloques', ['bloques' => [['nombre' => 'Desarrollo', 'estado' => 'en_curso', 'inicio_real' => '2026-10-12', 'fin_real' => '']]]);
ok($msg($r) === 'av_bloques' && ($leer()['bloques']['Desarrollo']['estado'] ?? '') === 'en_curso' && count($r['n8n']) === 1, '10) bloques guardados y presentación pedida');
$r = $correr('at_av_bloques', ['bloques' => [['nombre' => 'Inventado', 'estado' => 'listo']]]);
ok($msg($r) === 'av_listas_error' && ($leer()['bloques']['Desarrollo']['estado'] ?? '') === 'en_curso', '10) un bloque que no existe: error y no se pierde lo guardado');

$r = $correr('at_av_listas', ['cambios_plan' => '2026-10-13 | Publicidad al mensual | Acordado por correo', 'necesitamos' => "Logo en alta\n✓ Acceso al dominio", 'alcance_fase1' => 'Sitio web', 'alcance_mensual' => 'Publicidad']);
$av = $leer();
ok($msg($r) === 'av_listas' && count($av['cambios_plan']) === 1 && $av['necesitamos'][1]['estado'] === 'resuelto' && $av['alcance'] === ['fase1' => ['Sitio web'], 'mensual' => ['Publicidad']], '11) cambios, necesitamos y alcance guardados');
$r = $correr('at_av_listas', ['cambios_plan' => '13-10 | mal']);
ok($msg($r) === 'av_listas_error' && count($leer()['cambios_plan']) === 1, '11) una línea mal escrita: error y nada cambia');

$r = $correr('at_av_quitar', ['cierre_id' => $cid]);
ok($msg($r) === 'av_sin_cierre' && count($leer()['cierres']) === 1, '12) un cierre publicado no se quita');
$correr('at_av_borrador', ['cierre_id' => $cid]);
$foto = at_av_nombre_de_url((string) ($leer()['evidencias'][$cid . '-portada']['url'] ?? ''));
$r = $correr('at_av_quitar', ['cierre_id' => $cid]);
ok($msg($r) === 'av_quitado' && $leer()['cierres'] === [] && $foto !== '' && !is_file(at_av_dir($id)['dir'] . $foto), '12) quitar un borrador saca el cierre y borra su archivo');

$r = pt_correr_accion('at_av_importar', 0, 'at_pt_plan_' . $id, ['cierre_texto' => wp_json_encode($archivo), 'plan_id' => (string) $id, 'crm_id' => (string) $crm]);
ok($r['redirect'] === '' && $leer()['cierres'] === [], '13) sin sesión ni nonce válido: no hace nada');

// --- Subir sobre un cierre publicado, volver a borrador con otro publicado y la sección dibujada con dos cierres.
$subida = ['name' => 'portada.png', 'type' => 'image/png', 'tmp_name' => $tmp . 'portada.png', 'error' => 0, 'size' => filesize($tmp . 'portada.png')];
$hostil = '"><script>alert(1)</script>';
$otro = $archivo;
$otro['cierre']['titulo'] = 'Cierre ya publicado';
$otro['cierre']['bloques'] = ['Desarrollo'];
$otro['cierre']['fecha_real'] = '2026-10-11';
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($otro), '__files' => $files]);
$todos = $leer()['cierres'];
$cid_p = (string) (end($todos)['id'] ?? '');
$r = $correr('at_av_publicar', ['cierre_id' => $cid_p]);
ok($msg($r) === 'av_publicado' && $cid_p !== '', '15) un primer cierre queda publicado (el que se mantiene publicado)');

// F1: importar un borrador NUNCA rehace la presentación del cliente; si el archivo cambió bloques, cambios, «necesitamos» o
// alcance, el detalle avisa que el cliente los verá al publicar un cierre o al «Rehacer la presentación».
wp_set_current_user($admin);
at_pt_tomar_detalles($id);
$con_bloques = $archivo;
$con_bloques['cierre']['titulo'] = 'Cierre con bloques nuevos';
$con_bloques['cierre']['bloques'] = ['Diseño'];
$con_bloques['bloques'] = ['Desarrollo' => ['estado' => 'en_curso', 'inicio_real' => '2026-10-12', 'fin_real' => '']];
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($con_bloques)]);
$cid_b = '';
foreach ($leer()['cierres'] as $cb) {
	if ($cb['titulo'] === 'Cierre con bloques nuevos') { $cid_b = (string) $cb['id']; }
}
$det = implode(' | ', at_pt_tomar_detalles($id));
ok($cid_b !== '' && in_array($msg($r), ['av_importado', 'av_importado_faltan'], true) && $r['n8n'] === [], '15b) importar un borrador con un cierre ya publicado NO pide la presentación a n8n (F1)');
ok(str_contains($det, 'El archivo también cambió bloques, cambios de plan, «necesitamos» o alcance: el cliente los verá cuando publiques un cierre o uses «Rehacer la presentación».'), '15b) el detalle avisa que lo demás se verá al publicar o rehacer (F1)');
ok(($leer()['bloques']['Desarrollo']['estado'] ?? '') === 'en_curso', '15b) los bloques del archivo sí quedaron guardados');
$r = $correr('at_av_quitar', ['cierre_id' => $cid_b]);
ok($msg($r) === 'av_quitado' && $cid_b !== '' && count($leer()['cierres']) === 1, '15b) borrador de prueba quitado');
at_pt_tomar_detalles($id);

// F2: importar con el entregable de un cliente que no es el del plan (o inexistente) se rechaza; con uno propio, se acepta.
global $wpdb;
$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $c['tech'], 'detail_type' => 'entregable', 'title' => '[PRUEBA] Entregable propio', 'status' => 'completed', 'attachment_url' => 'https://example.com/v1', 'created_by' => 1]);
$det_mio = (int) $wpdb->insert_id;
$ent_mio = at_en_activar($det_mio);
register_shutdown_function(function () use ($ent_mio, $det_mio) {
	global $wpdb;
	if (is_int($ent_mio)) { $wpdb->delete(at_en_tablas()['e'], ['id' => $ent_mio]); }
	$wpdb->delete($wpdb->prefix . 'automatiza_clients_details', ['id' => $det_mio]);
});
$con_ent = $archivo;
$con_ent['cierre']['titulo'] = 'Cierre con entregable ajeno';
$con_ent['cierre']['entregable_id'] = 999999991;
$n_antes = count($leer()['cierres']);
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($con_ent)]);
$det = implode(' | ', at_pt_tomar_detalles($id));
ok($msg($r) === 'av_import_error' && count($leer()['cierres']) === $n_antes && str_contains($det, 'El entregable 999999991 no existe o no es de este cliente.'), '15c) importar con un entregable que no existe o no es del cliente: se rechaza y no se guarda nada (F2)');
$con_ent['cierre']['titulo'] = 'Cierre con entregable propio';
$con_ent['cierre']['entregable_id'] = is_int($ent_mio) ? $ent_mio : 0;
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($con_ent)]);
$cid_e = '';
foreach ($leer()['cierres'] as $ce) {
	if ($ce['titulo'] === 'Cierre con entregable propio') { $cid_e = (string) $ce['id']; }
}
ok($cid_e !== '' && is_int($ent_mio) && $ent_mio > 0 && in_array($msg($r), ['av_importado', 'av_importado_faltan'], true), '15c) con un entregable del mismo cliente se acepta (F2)');
$r = $correr('at_av_quitar', ['cierre_id' => $cid_e]);
ok($msg($r) === 'av_quitado' && count($leer()['cierres']) === 1, '15c) borrador de prueba quitado');
at_pt_tomar_detalles($id);

// F8: un archivo de más de 1 MB dice eso, no que «no es un JSON válido».
$grande = ['name' => 'cierre.json', 'type' => 'application/json', 'tmp_name' => $tmp . 'portada.png', 'error' => 0, 'size' => 1048577];
$r = $correr('at_av_importar', ['__files' => ['cierre_json' => $grande]]);
$det = implode(' | ', at_pt_tomar_detalles($id));
ok($msg($r) === 'av_import_error' && str_contains($det, 'El archivo pesa más de 1 MB.') && !str_contains($det, 'JSON válido'), '15d) un archivo de más de 1 MB: «El archivo pesa más de 1 MB.» (F8)');

$hostilito = $archivo;
$hostilito['cierre']['titulo'] = $hostil;
$n_antes = count($leer()['cierres']);
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($hostilito)]);
ok($msg($r) === 'av_import_error' && count($leer()['cierres']) === $n_antes && !str_contains(implode('|', array_column($leer()['cierres'], 'titulo')), '<script'), '16) un título con etiquetas HTML se rechaza entero: no se guarda ni se corta en silencio (F3)');
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo)]);
$cid_h = '';
foreach ($leer()['cierres'] as $c) {
	if ($c['id'] !== $cid_p) { $cid_h = (string) $c['id']; }
}
$eid_h = $cid_h . '-portada';
ok($msg($r) === 'av_importado_faltan' && $cid_h !== '' && ($leer()['evidencias'][$eid_h]['url'] ?? '') === '', '16) segundo cierre en borrador con la portada pendiente');
$r = $correr('at_av_correo', ['cierre_id' => $cid_h, 'destino' => 'cliente']);
ok($msg($r) === 'av_correo_no_publicado', '20) un cierre en borrador no se envía al cliente');
$r = $correr('at_av_whatsapp', ['cierre_id' => $cid_h]);
ok($msg($r) === 'av_correo_no_publicado' && !str_contains($r['redirect'], 'wa.me'), '20) ni se abre su WhatsApp');
$r = $correr('at_av_subir', ['evidencia_id' => $eid_h, 'campo' => 'archivo', '__files' => ['archivo' => $subida]]);
$url_h = (string) ($leer()['evidencias'][$eid_h]['url'] ?? '');
$nombre_h = at_av_nombre_de_url($url_h);
ok($msg($r) === 'av_subido' && $url_h !== '' && $nombre_h !== '' && is_file(at_av_dir($id)['dir'] . $nombre_h), '17) subir una captura a un borrador: guarda el archivo y la evidencia recibe su URL');

$r = $correr('at_av_publicar', ['cierre_id' => $cid_h]);
$md5_h = md5_file(at_av_dir($id)['dir'] . $nombre_h);
$r = $correr('at_av_subir', ['evidencia_id' => $eid_h, 'campo' => 'archivo', '__files' => ['archivo' => $subida]]);
ok($msg($r) === 'av_subida_error' && ($leer()['evidencias'][$eid_h]['url'] ?? '') === $url_h && is_file(at_av_dir($id)['dir'] . $nombre_h) && md5_file(at_av_dir($id)['dir'] . $nombre_h) === $md5_h && $r['n8n'] === [], '18) subir sobre un cierre publicado: no sube, no reemplaza ni borra el archivo que ve el cliente');

$av = $leer();
$av['render'] = array_merge(at_av_registro_vacio(), ['estado' => 'listo', 'view_url' => 'https://example.com/presentacion']);
at_av_guardar($id, $av);
$r = $correr('at_av_borrador', ['cierre_id' => $cid_h]);
$rr = $leer()['render'];
ok($msg($r) === 'av_borrador' && $rr['estado'] === 'pendiente' && $rr['view_url'] === '' && count($r['n8n']) === 1 && (($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'estado'), '19) volver a borrador con otro cierre publicado: la presentación queda «pendiente», sin enlace viejo, y se pide una vez (modo estado)');

// 19b) El render público está serializado también desde el panel: con uno «pendiente» reciente, publicar o volver a borrador no llama a n8n; queda en cola.
$pedido_prev = $leer()['render']['pedido_at'];
$r = $correr('at_av_publicar', ['cierre_id' => $cid_h]);
ok($msg($r) === 'av_publicado' && $r['n8n'] === [] && $leer()['render']['estado'] === 'pendiente' && $leer()['render']['repetir'] === true && $leer()['render']['pedido_at'] === $pedido_prev, '19b) publicar con una presentación ya pedida y sin terminar: no se llama a n8n, queda en cola');
$r = $correr('at_av_borrador', ['cierre_id' => $cid_h]);
ok($msg($r) === 'av_borrador' && $r['n8n'] === [] && $leer()['render']['estado'] === 'pendiente' && $leer()['render']['view_url'] === '' && $leer()['render']['pedido_at'] === $pedido_prev && $leer()['render']['repetir'] === true, '19b) volver a borrador con un pedido en curso: sigue «pendiente», conserva su hora y su cola y no llama a n8n');

ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
// El título hostil se guarda a mano (la importación ahora lo rechaza): así se prueba el escape de la propia sección.
$_GET['av_editar'] = $cid_h;
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h_edicion = (string) ob_get_clean();
unset($_GET['av_editar']);
ok(str_contains($h_edicion, 'id="at-av-formulario-' . $id . '" open>') && str_contains($h_edicion, 'id="at-av-nuevo">✏️ Editar el cierre') && str_contains($h_edicion, 'name="cierre_id" value="' . $cid_h . '"'), 'CRM-001) editar abre el formulario y conserva ancla y cierre_id');
$av = $leer();
foreach ($av['cierres'] as $k => $c) {
	if ($c['id'] === $cid_h) { $av['cierres'][$k]['titulo'] = $hostil; }
}
at_av_guardar($id, $av);
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
$pos_pub = (int) strpos($h, 'Cierre ya publicado');
ok(str_contains($h, esc_html($hostil)) && !str_contains($h, '<script>alert(1)</script>') && $pos_pub > 0, '14) el título del cierre sale escapado: nunca como HTML');
ok(substr_count($h, 'Publicado</span>') === 1 && substr_count($h, 'Borrador</span>') === 1 && str_contains($h, 'Para editar un cierre publicado, primero vuelve a borrador.'), '14) la lista marca un cierre «Publicado» y otro «Borrador»');
ok(str_contains($h, 'value="at_av_correo"') && str_contains($h, 'Enviarme una prueba') && str_contains($h, 'Enviar al cliente'), '22) el cierre publicado muestra el correo de cierre');
ok(substr_count($h, 'value="at_av_correo"') === 1 && substr_count($h, 'value="at_av_whatsapp"') === 1, '22) solo el publicado tiene correo y WhatsApp (el borrador no)');
ok(str_contains($h, 'Se enviará el correo de cierre a prueba-avance-panel@example.com. ¿Enviar?') && !str_contains($h, 'name="reenviar"'), '22) antes del primer envío, la confirmación muestra el correo del cliente y no hay «Reenviar»');
ok(str_contains($h, 'Correo al cliente: aún no') && str_contains($h, 'Prueba: aún no') && str_contains($h, 'WhatsApp: aún no'), '22) el estado de los envíos dice «aún no»');
ok(substr_count($h, 'value="at_av_subir"') === 1 && str_contains($h, 'name="evidencia_id" value="' . $eid_h . '"') && !str_contains($h, 'value="' . $cid_p . '-portada"'), '14) el formulario de subir archivos solo está en el borrador');
ok(substr_count($h, 'Editar el cierre (formato at-cierre-v1)') === 1, '14) «Editar el cierre» solo está en el borrador');
ok(str_contains($h, 'id="at-av"') && str_contains($h, 'value="at_av_importar"') && str_contains($h, 'value="at_av_bloques"') && str_contains($h, 'value="at_av_listas"') && str_contains($h, 'Importar cierre') && str_contains($h, esc_url(at_pt_url_ver_estado(home_url(), $cod))), '14) la pestaña de un plan enviado muestra la sección «Avance»');
// Cierre ya enviado: «Reenviar», con la fecha y el correo del envío; videos solo si son de nuestro sitio.
$av = $leer();
foreach ($av['cierres'] as $k => $cx) {
	if ($cx['id'] === $cid_p) {
		$av['cierres'][$k]['correo'] = ['enviado_at' => '2026-10-12 10:30:00', 'prueba_at' => '2026-10-12 10:00:00', 'mensaje' => '', 'enviando' => null, 'a' => 'cliente@example.com'];
		$av['cierres'][$k]['whatsapp'] = ['abierto_at' => '2026-10-12 10:45:00'];
		$av['cierres'][$k]['despues']['evidencias'][] = 'v-propio';
		$av['cierres'][$k]['despues']['evidencias'][] = 'v-ajeno';
	}
}
$av['evidencias']['v-propio'] = ['tipo' => 'video', 'titulo' => 'Recorrido propio', 'url' => home_url('/wp-content/uploads/at-estado/recorrido.mp4'), 'poster' => '', 'archivo' => 'recorrido.mp4'];
$av['evidencias']['v-ajeno'] = ['tipo' => 'video', 'titulo' => 'Video ajeno', 'url' => 'https://ajeno.example.org/video.mp4', 'poster' => '', 'archivo' => 'ajeno.mp4'];
at_av_guardar($id, $av);
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
ok(str_contains($h, 'Reenviar al cliente') && str_contains($h, 'name="reenviar" value="1"') && str_contains($h, 'ya se envió') && str_contains($h, 'cliente@example.com'), '22) un cierre ya enviado ofrece «Reenviar» y la confirmación dice cuándo se envió');
ok(str_contains($h, 'Correo al cliente: enviado el') && str_contains($h, 'WhatsApp: abierto el') && !str_contains($h, 'WhatsApp: aún no'), '22) el estado de los envíos muestra las fechas');
ok(str_contains($h, 'href="' . esc_url(home_url('/wp-content/uploads/at-estado/recorrido.mp4')) . '" download') && !str_contains($h, 'href="https://ajeno.example.org/video.mp4" download'), '23) «Descargar el video» solo para videos de nuestro sitio');
$bloque = preg_match('/<div class="at-av-envio">(.*?)<\/div>/s', $h, $mb) ? $mb[1] : '';
ok(str_contains($bloque, 'Enviar por mi WhatsApp') && str_contains($bloque, 'readonly') && str_contains($bloque, 'target="_blank"'), '22) el WhatsApp ofrece el botón (otra pestaña) y el texto para copiar');

// Sin correo del cliente: el botón al cliente queda deshabilitado y dice por qué.
global $wpdb;
$ct = $wpdb->get_row($wpdb->prepare("SELECT id, placeholders FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", (int) at_pt_plan($id)->contrato_id));
$ph0 = (string) $ct->placeholders;
$ph1 = json_decode($ph0, true);
$ph1['email_cliente'] = '';
$wpdb->update($wpdb->prefix . 'automatiza_contracts', ['placeholders' => wp_json_encode($ph1)], ['id' => (int) $ct->id]);
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => ''], ['id' => $crm]);
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
$wpdb->update($wpdb->prefix . 'automatiza_contracts', ['placeholders' => $ph0], ['id' => (int) $ct->id]);
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => 'prueba-avance-panel@example.com'], ['id' => $crm]);
$bloque = preg_match('/<div class="at-av-envio">(.*?)<\/div>/s', $h, $mb) ? $mb[1] : '';
ok(preg_match('/<button[^>]*disabled[^>]*>[^<]*Reenviar al cliente/u', $bloque) === 1 && str_contains($bloque, 'no tiene correo'), '22) sin correo del cliente: «Reenviar al cliente» deshabilitado, con el motivo');

// --- Guardar sin borrar lo que otro proceso escribió desde que la acción leyó el avance (marcas de envío y render).
$pub_x = array_values(array_filter($leer()['cierres'], fn($x) => ($x['estado'] ?? '') === 'publicado'))[0] ?? null;
ok($pub_x !== null, '24) hay un cierre publicado para probar el guardado');
$pid = (string) $pub_x['id'];
$marca = function (string $texto) use ($id, $pid, $wpdb): void { // lo que escribiría un envío (correo reclamado/anotado, WhatsApp) y un render que vuelve
	$x = at_av_leer(at_pt_plan($id));
	foreach ($x['cierres'] as $k => $cx) {
		if ($cx['id'] === $pid) {
			$x['cierres'][$k]['correo'] = ['enviado_at' => '2026-10-15 11:00:00', 'prueba_at' => '2026-10-15 10:00:00', 'mensaje' => $texto, 'enviando' => null, 'a' => 'cliente@example.com'];
			$x['cierres'][$k]['whatsapp'] = ['abierto_at' => '2026-10-15 11:05:00'];
		}
	}
	$x['render'] = array_merge(at_av_registro_vacio(), ['estado' => 'pendiente', 'at' => '2026-10-15 11:06:00', 'pedido_at' => '2026-10-15 11:06:00', 'repetir' => true]);
	$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}automatiza_planes_trabajo SET avance = %s WHERE id = %d", wp_json_encode(at_av_normalizar($x), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id));
};
$envio_x = fn(): array => (array) (at_av_cierre_de_avance($leer(), $pid) ?? []);
// A) la marca se escribe DESPUÉS de que la acción leyó el avance y ANTES de que lo guarde («Guardar bloques», publicar…).
$leido = $leer();
$marca('Mensaje A');
$leido['alcance']['fase1'] = ['Cambio de la acción A'];
at_av_guardar_o_volver($id, $crm, $leido);
$x = $envio_x();
ok($leer()['alcance']['fase1'] === ['Cambio de la acción A'] && ($x['correo']['enviado_at'] ?? null) === '2026-10-15 11:00:00' && ($x['correo']['mensaje'] ?? '') === 'Mensaje A' && ($x['whatsapp']['abierto_at'] ?? null) === '2026-10-15 11:05:00', '24) lo que guarda una acción no borra las marcas de envío escritas entretanto');
ok($leer()['render']['estado'] === 'pendiente' && $leer()['render']['repetir'] === true && $leer()['render']['pedido_at'] === '2026-10-15 11:06:00', '24) ni la cola ni la hora del render público que otro proceso dejó');
// B) la marca llega entre la relectura y el UPDATE: el guardado condicional falla, se relee y se reintenta.
$leido = $leer();
$concurrente_b = true;
$filtro_b = function ($q) use (&$concurrente_b, $marca) {
	if ($concurrente_b && strpos($q, 'UPDATE') === 0 && strpos($q, 'SET avance') !== false) {
		$concurrente_b = false;
		$marca('Mensaje B');
	}
	return $q;
};
add_filter('query', $filtro_b);
$leido['alcance']['fase1'] = ['Cambio de la acción B'];
at_av_guardar_o_volver($id, $crm, $leido);
remove_filter('query', $filtro_b);
$x = $envio_x();
ok(!$concurrente_b && $leer()['alcance']['fase1'] === ['Cambio de la acción B'] && ($x['correo']['mensaje'] ?? '') === 'Mensaje B' && ($x['correo']['enviado_at'] ?? null) === '2026-10-15 11:00:00', '24) si la marca llega entre la relectura y la escritura, se reintenta y tampoco se pierde');
// C) $render_propio: la acción que decide el render (volver a borrador) lo deja como lo armó, pero las marcas de envío siguen intactas.
$leido = $leer();
$leido['render'] = array_merge(at_av_registro_vacio(), ['estado' => 'pendiente', 'at' => '2026-10-15 12:00:00']);
at_av_guardar_o_volver($id, $crm, $leido, true);
ok($leer()['render']['pedido_at'] === '' && $leer()['render']['repetir'] === false && ($envio_x()['correo']['mensaje'] ?? '') === 'Mensaje B', '24) una acción que decide el render lo guarda como lo armó y conserva igual las marcas de envío');
// D) un cierre quitado no reaparece por la marca de la fila.
$leido = $leer();
$leido['cierres'] = array_values(array_filter($leido['cierres'], fn($cx) => $cx['id'] !== $pid));
at_av_guardar_o_volver($id, $crm, $leido, true);
ok(at_av_cierre_de_avance($leer(), $pid) === null, '24) quitar un cierre lo quita aunque la fila tuviera sus marcas');

ptc_estado($id, 'listo');
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
ok(!str_contains((string) ob_get_clean(), 'id="at-av"'), '14) un plan que no está enviado no la muestra');
fin();
