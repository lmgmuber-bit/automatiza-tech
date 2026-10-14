<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-formulario-wp-test.php
// Estado del proyecto: formulario «Nuevo cierre» del panel (sin escribir JSON). Crear, error que conserva lo escrito,
// editar un borrador (mismo id y mismos archivos, también al cambiar el título) y los límites de editar.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_accion_nuevo', 'at_av_render_formulario', 'at_av_cierre_de_formulario', 'at_av_formulario_de_cierre');
at_av_migrar_esquema();
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-avance-form@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-avance-form@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
ptc_estado($id, 'enviado');
$crm = (int) at_pt_plan($id)->crm_cliente_id;
$tmp = sys_get_temp_dir() . '/at-av-form-' . getmypid() . '/';
wp_mkdir_p($tmp);
register_shutdown_function(function () use ($tmp, $id) {
	foreach (glob($tmp . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($tmp);
	$d = at_av_dir($id)['dir'];
	foreach (glob($d . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($d);
	delete_transient(at_av_clave_form());
});
$correr = fn(string $accion, array $post) => pt_correr_accion($accion, $admin, 'at_pt_plan_' . $id, $post + ['plan_id' => (string) $id, 'crm_id' => (string) $crm]);
$msg = fn(array $r): string => (string) (pt_query($r['redirect'])['pt_msg'] ?? '');
$leer = fn(): array => at_av_leer(at_pt_plan($id));
$dibujar = function () use ($id, $crm): string {
	ob_start();
	at_pt_render_plan(at_pt_plan($id), $crm);
	return (string) ob_get_clean();
};
$im = imagecreatetruecolor(40, 30);
imagepng($im, $tmp . 'foto.png');
imagedestroy($im);
// Nombre como el de un celular: espacios y mayúsculas. Se sube en la fila 1 (la 0 es una cifra).
$files = ['nc_archivo' => ['name' => [1 => 'Captura de pantalla 10.30.PNG'], 'type' => [1 => 'image/png'], 'tmp_name' => [1 => $tmp . 'foto.png'], 'error' => [1 => 0], 'size' => [1 => filesize($tmp . 'foto.png')]]];
$nc = [
	'titulo' => 'Fotos antiguas retiradas', 'fecha_real' => '2026-10-08', 'bloques' => ['Diseño'],
	'antes_texto' => 'Se veían fotos de personal antiguo.', 'antes_puntos' => "Fotos viejas\nSin permiso", 'durante_texto' => 'Las retiramos.', 'despues_texto' => 'Ya no aparecen.',
	'ev' => [
		['tipo' => 'cifra', 'donde' => 'antes', 'titulo' => 'Fotos del personal antiguo', 'valor' => '7', 'unidad' => 'fotos', 'fuente' => 'Revisión del sitio', 'fecha' => '2026-10-07'],
		['tipo' => 'captura', 'donde' => 'despues', 'titulo' => 'Portada sin las fotos'],
		['tipo' => 'captura', 'titulo' => ''],
	],
];

// 1) La sección trae el formulario y el importador queda como opción secundaria.
$h = $dibujar();
ok(str_contains($h, 'value="at_av_nuevo"') && str_contains($h, '➕ Nuevo cierre') && substr_count($h, 'class="at-av-ev"') === AT_AV_FORM_FILAS && substr_count($h, 'class="at-av-ev" hidden') === AT_AV_FORM_FILAS - 2 && str_contains($h, 'name="nc[bloques][]" value="Diseño"'), '1) la sección muestra «Nuevo cierre» con los bloques del plan y dos filas de evidencia a la vista');
ok(str_contains($h, 'value="at_av_importar"') && str_contains($h, 'Importar cierre'), '1) «Importar cierre» sigue disponible');

// 2) Un error no guarda nada y lo escrito vuelve al formulario (una sola vez).
$malo = $nc;
$malo['bloques'] = [];
$r = $correr('at_av_nuevo', ['nc' => $malo]);
$det = implode(' | ', at_pt_tomar_detalles($id));
ok($msg($r) === 'av_form_error' && $leer()['cierres'] === [] && str_contains($det, 'al menos un bloque'), '2) sin bloque: no se guarda y el detalle lo dice');
$h = $dibujar();
ok(str_contains($h, 'value="Fotos antiguas retiradas"') && str_contains($h, 'value="Revisión del sitio"') && get_transient(at_av_clave_form()) === false, '2) lo escrito vuelve al formulario y se olvida al mostrarlo');
$h = $dibujar();
ok(!str_contains($h, 'value="Fotos antiguas retiradas"'), '2) al recargar, el formulario queda vacío');
$malo = $nc;
$malo['ev'][0]['titulo'] = '';
$r = $correr('at_av_nuevo', ['nc' => $malo]);
ok($msg($r) === 'av_form_error' && str_contains(implode(' | ', at_pt_tomar_detalles($id)), 'A la evidencia 1 le falta el título.') && $leer()['cierres'] === [], '2) evidencia sin título: error claro');

// 3) Crear: el archivo del celular se guarda en su evidencia aunque su nombre traiga espacios.
$r = $correr('at_av_nuevo', ['nc' => $nc, '__files' => $files]);
$av = $leer();
$cid = (string) ($av['cierres'][0]['id'] ?? '');
$url = (string) ($av['evidencias'][$cid . '-evidencia-2']['url'] ?? '');
ok($msg($r) === 'av_importado' && count($av['cierres']) === 1 && $av['cierres'][0]['estado'] === 'borrador' && $r['n8n'] === [], '3) crear desde el formulario: borrador sin pedir presentación');
ok($url !== '' && is_file(at_av_dir($id)['dir'] . at_av_nombre_de_url($url)) && at_av_faltan_archivos($av, $cid) === [], '3) la imagen quedó subida y no falta nada');
ok($av['cierres'][0]['antes']['evidencias'] === [$cid . '-evidencia-1'] && $av['cierres'][0]['antes']['puntos'] === ['Fotos viejas', 'Sin permiso'] && $av['cierres'][0]['durante']['actividades'] !== [], '3) antes, puntos y actividades del plan en su lugar');

// 4) Editar: el formulario se llena con el cierre; cambiar el título conserva el id y la imagen.
$_GET['av_editar'] = $cid;
$h = $dibujar();
unset($_GET['av_editar']);
ok(str_contains($h, '✏️ Editar el cierre «Fotos antiguas retiradas»') && str_contains($h, 'name="cierre_id" value="' . $cid . '"') && str_contains($h, 'ya está «evidencia-2.png»'), '4) «Editar» llena el formulario con el cierre y dice qué imagen ya está');
$p = at_av_formulario_de_cierre(at_av_exportar_cierre($av, $cid, (string) at_pt_plan($id)->codigo));
$p['titulo'] = 'Fotos del personal antiguo retiradas';
$r = $correr('at_av_nuevo', ['nc' => $p, 'cierre_id' => $cid]);
$av = $leer();
ok($msg($r) === 'av_importado' && count($av['cierres']) === 1 && $av['cierres'][0]['id'] === $cid && $av['cierres'][0]['titulo'] === 'Fotos del personal antiguo retiradas' && ($av['evidencias'][$cid . '-evidencia-2']['url'] ?? '') === $url && is_file(at_av_dir($id)['dir'] . at_av_nombre_de_url($url)), '4) editar con otro título: mismo cierre, misma imagen, sin duplicados');

// 5) Límites al editar: otro cierre con ese título, y un cierre publicado.
$otro = $nc;
$otro['titulo'] = 'Desarrollo listo';
$otro['bloques'] = ['Desarrollo'];
$otro['ev'] = [];
$correr('at_av_nuevo', ['nc' => $otro]);
$p['titulo'] = 'desarrollo  listo';
$r = $correr('at_av_nuevo', ['nc' => $p, 'cierre_id' => $cid]);
ok($msg($r) === 'av_form_error' && str_contains(implode(' | ', at_pt_tomar_detalles($id)), 'Ya hay otro cierre llamado «Desarrollo listo».') && count($leer()['cierres']) === 2, '5) editar con el título de otro cierre: error y nada cambia');
delete_transient(at_av_clave_form());
$correr('at_av_publicar', ['cierre_id' => $cid]);
$p['titulo'] = 'Fotos del personal antiguo retiradas';
$r = $correr('at_av_nuevo', ['nc' => $p, 'cierre_id' => $cid]);
ok($msg($r) === 'av_form_error' && str_contains(implode(' | ', at_pt_tomar_detalles($id)), 'vuelve a borrador para editarlo') && $leer()['cierres'][0]['estado'] === 'publicado', '5) un cierre publicado no se edita desde el formulario');
delete_transient(at_av_clave_form());
$_GET['av_editar'] = $cid;
$h = $dibujar();
unset($_GET['av_editar']);
ok(str_contains($h, '➕ Nuevo cierre') && !str_contains($h, '✏️ Editar el cierre'), '5) ?av_editar con un cierre publicado muestra el formulario vacío');
fin();
