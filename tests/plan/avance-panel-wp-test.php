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

$hostilito = $archivo;
$hostilito['cierre']['titulo'] = $hostil;
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($hostilito)]);
$cid_h = '';
foreach ($leer()['cierres'] as $c) {
	if ($c['id'] !== $cid_p) { $cid_h = (string) $c['id']; }
}
$eid_h = $cid_h . '-portada';
ok($msg($r) === 'av_importado_faltan' && $cid_h !== '' && ($leer()['evidencias'][$eid_h]['url'] ?? '') === '', '16) segundo cierre en borrador con la portada pendiente');
ok(!str_contains(implode('|', array_column($leer()['cierres'], 'titulo')), '<script'), '16) la importación le quita las etiquetas al título (y el panel igual lo escapa: ver 14)');
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

ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
// El título hostil se guarda a mano (la importación ya lo limpia): así se prueba el escape de la propia sección.
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
ok(substr_count($h, 'value="at_av_subir"') === 1 && str_contains($h, 'name="evidencia_id" value="' . $eid_h . '"') && !str_contains($h, 'value="' . $cid_p . '-portada"'), '14) el formulario de subir archivos solo está en el borrador');
ok(substr_count($h, 'Editar el cierre (formato at-cierre-v1)') === 1, '14) «Editar el cierre» solo está en el borrador');
ok(str_contains($h, 'id="at-av"') && str_contains($h, 'value="at_av_importar"') && str_contains($h, 'value="at_av_bloques"') && str_contains($h, 'value="at_av_listas"') && str_contains($h, 'Importar cierre') && str_contains($h, esc_url(at_pt_url_ver_estado(home_url(), $cod))), '14) la pestaña de un plan enviado muestra la sección «Avance»');
ptc_estado($id, 'listo');
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
ok(!str_contains((string) ob_get_clean(), 'id="at-av"'), '14) un plan que no está enviado no la muestra');
fin();
