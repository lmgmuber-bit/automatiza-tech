<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-js-wp-test.php
// Task 9: plan-trabajo.js en un navegador de verdad. Dibuja la pestaña con at_pt_render_pestana() y datos [PRUEBA],
// la deja en un HTML temporal con plan-trabajo.css, plan-trabajo.js y un chequeo al final, y la abre con Chrome (o
// Edge) sin ventana (--headless=new --dump-dom). El chequeo anota «ok …» o «FALLA …» en <pre id="at-pt-resultado">.
// El navegador sale de la variable de entorno CHROME o de las rutas de siempre en Windows.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_render_pestana', 'ptc_plan_json');

$navegador = (string) getenv('CHROME');
if ($navegador === '') {
	foreach (['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'] as $p) {
		if (is_file($p)) {
			$navegador = $p;
			break;
		}
	}
}
if ($navegador === '' || !is_file($navegador)) {
	fwrite(STDERR, "No se encontró Chrome ni Edge: define CHROME con la ruta del navegador.\n");
	exit(2);
}
$js = get_template_directory() . '/assets/js/plan-trabajo.js';
$css = get_template_directory() . '/assets/css/plan-trabajo.css';
ok(is_file($js) && is_file($css), 'existen assets/js/plan-trabajo.js y assets/css/plan-trabajo.css');
if (!is_file($js) || !is_file($css)) {
	fin();
}

/** Borra una carpeta temporal entera (el perfil del navegador incluido); lo que no se pueda borrar se deja. */
function ptj_borrar(string $ruta): void {
	if (is_dir($ruta) && !is_link($ruta)) {
		foreach ((array) scandir($ruta) as $e) {
			if ($e !== '.' && $e !== '..') {
				ptj_borrar($ruta . '/' . $e);
			}
		}
		@rmdir($ruta);
	} elseif (file_exists($ruta) || is_link($ruta)) {
		@unlink($ruta);
	}
}

// La pestaña de un plan en borrador, con propuesta, tal como la dibuja el servidor.
wp_set_current_user(pt_admin_id());
$m = ptc_marca();
$c = ptc_cliente($m, "prueba-plan-{$m}@example.com");
$k = ptc_contrato($c['tech'], $m, ptc_propuesta($m));
$f = ptc_plan($k);
$plan = ptc_sembrar((int) $f->id);
$_GET = ['page' => 'automatiza-crm-ficha', 'id' => (string) $c['crm'], 'pt' => (string) $f->id];
ob_start();
at_pt_render_pestana(ptc_cliente_fila($c['crm']));
$pestana = (string) ob_get_clean();
$_GET = [];
ptc_limpiar(); // el HTML ya quedó dibujado: los datos no hacen falta

// Chequeo que corre en el navegador después de plan-trabajo.js. alert() y confirm() se reemplazan para anotar lo que
// mostrarían (confirm() contesta «Cancelar»); los envíos son eventos submit sintéticos, que nunca navegan.
$chequeo = <<<'JS'
(function () {
  var r = [];
  function ok(c, m) { r.push((c ? 'ok   ' : 'FALLA ') + m); }
  var avisos = [];
  var confirmaciones = [];
  window.alert = function (m) { avisos.push(String(m)); };
  window.confirm = function (m) { confirmaciones.push(String(m)); return false; };
  function enviar(f) {
    var e = new Event('submit', { bubbles: true, cancelable: true });
    f.dispatchEvent(e);
    return e;
  }
  try {
    var api = window.atPlanTrabajo;
    ok(!!(api && typeof api.serializar === 'function' && typeof api.abrirPestanaPlan === 'function'), 'plan-trabajo.js publica window.atPlanTrabajo con serializar() y abrirPestanaPlan()');
    ok(document.getElementById('tab-plan').classList.contains('active') && document.querySelector('.ficha-tab[data-target="tab-plan"]').classList.contains('active') && !document.getElementById('tab-resumen').classList.contains('active'), 'con #tab-plan en la dirección se abre la pestaña del plan');
    var form = document.querySelector('.at-pt-form-plan');
    ok(JSON.stringify(api.serializar(form)) === JSON.stringify(window.AT_PT_ESPERADO), 'serializar() arma exactamente el plan_json que espera el servidor (ptc_plan_json: claves, orden, tipos, detalle y origen)');
    var aprobar = document.querySelector('.at-pt-form-aprobar');
    var e = enviar(aprobar);
    ok(confirmaciones.length === 1 && confirmaciones[0].indexOf('fotos nuevas') !== -1 && e.defaultPrevented && avisos.length === 0, 'sin cambios, «Aprobar» pide confirmar las fotos y su costo; al cancelar no se envía');
    var dias = form.querySelector('.at-pt-a-dias');
    dias.value = '9';
    dias.dispatchEvent(new Event('input', { bubbles: true }));
    confirmaciones = [];
    e = enviar(aprobar);
    ok(e.defaultPrevented && confirmaciones.length === 0 && avisos.length === 1 && avisos[0].indexOf('cambios sin guardar') !== -1, 'con cambios sin guardar, «Aprobar» se bloquea con un aviso antes de confirmar');
    avisos = [];
    e = enviar(document.querySelector('.at-pt-form-cambios'));
    ok(e.defaultPrevented && avisos.length === 1, 'con cambios sin guardar, «Pedir cambios» también se bloquea');
    ok(api.serializar(form).fases[0].bloques[0].actividades[0].dias_habiles === 9, 'los días editados viajan como número');
    var detalle = form.querySelector('.at-pt-a-detalle');
    detalle.value = '  [PRUEBA] Detalle editado  ';
    ok(api.serializar(form).fases[0].bloques[0].actividades[0].detalle === '[PRUEBA] Detalle editado', 'el detalle editado viaja en plan_json, sin espacios de sobra');
    var bloque = form.querySelector('.at-pt-fase[data-clave="diseno_desarrollo"] .at-pt-bloque');
    var antes = bloque.querySelectorAll('.at-pt-act').length;
    bloque.querySelector('.at-pt-agregar-act').click();
    var filas = bloque.querySelectorAll('.at-pt-act');
    var nueva = filas[filas.length - 1];
    nueva.querySelector('.at-pt-a-nombre').value = '[PRUEBA] Actividad nueva';
    var acts = api.serializar(form).fases[0].bloques[0].actividades;
    var ult = acts[acts.length - 1];
    ok(filas.length === antes + 1 && ult.nombre === '[PRUEBA] Actividad nueva' && ult.origen === 'luis' && ult.dias_habiles === 1 && ult.responsable === 'at' && ult.en_paralelo === false, '«+ Actividad» agrega una fila de Luis: 1 día, AutomatizaTech, no en paralelo');
    nueva.querySelector('.at-pt-quitar-act').click();
    ok(bloque.querySelectorAll('.at-pt-act').length === antes, '✕ quita la actividad');
    var fase = form.querySelector('.at-pt-fase[data-clave="implementacion"]');
    var n = fase.querySelectorAll('.at-pt-bloque').length;
    fase.querySelector('.at-pt-agregar-bloque').click();
    var nuevo = fase.querySelectorAll('.at-pt-bloque')[n];
    nuevo.querySelector('.at-pt-a-nombre').value = '[PRUEBA] Actividad del bloque nuevo';
    var bl = api.serializar(form).fases[1].bloques;
    var b = bl[bl.length - 1];
    ok(fase.querySelectorAll('.at-pt-bloque').length === n + 1 && b.nombre === 'Nuevo bloque' && b.actividades.length === 1 && b.actividades[0].origen === 'luis', '«+ Bloque» agrega al final de la fase un bloque con una actividad de Luis');
    e = enviar(form);
    var pj = JSON.parse(form.querySelector('.at-pt-plan-json').value || 'null');
    ok(!e.defaultPrevented && pj !== null && pj.fases.length === 3 && !form.hasAttribute('data-sucio'), '«Guardar y recalcular» llena plan_json con las tres fases y da la tabla por guardada');
    avisos = [];
    confirmaciones = [];
    enviar(aprobar);
    ok(avisos.length === 0 && confirmaciones.length === 1, 'después de guardar, «Aprobar» vuelve a pedir solo la confirmación');
  } catch (err) {
    ok(false, 'el chequeo se cayó: ' + err.message);
  }
  document.getElementById('at-pt-resultado').textContent = r.join('\n');
}());
JS;

$html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Prueba de plan-trabajo.js</title>'
	. '<style>.ficha-tab-content{display:none}.ficha-tab-content.active{display:block}</style>'
	. '<style>' . file_get_contents($css) . '</style></head><body>'
	. '<div class="ficha-grid"><div class="ficha-col"><div class="ficha-tabs">'
	. '<button class="ficha-tab active" data-target="tab-resumen">Resumen</button><button class="ficha-tab" data-target="tab-plan">🗓️ Plan de trabajo</button></div>'
	. '<div class="ficha-tab-content active" id="tab-resumen">Resumen</div>'
	. '<div class="ficha-tab-content" id="tab-plan"><div class="ficha-card">' . $pestana . '</div></div></div></div>'
	. '<pre id="at-pt-resultado"></pre>'
	. '<script>window.AT_PT_ESPERADO = ' . ptc_plan_json($plan) . ';</script>'
	. '<script>' . file_get_contents($js) . '</script>'
	. '<script>' . $chequeo . '</script></body></html>';

$dir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/at-pt-js-' . $m;
wp_mkdir_p($dir);
file_put_contents($dir . '/pestana.html', $html);
$url = 'file:///' . ltrim($dir, '/') . '/pestana.html#tab-plan';
$proc = proc_open(
	[$navegador, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--user-data-dir=' . $dir . '/perfil', '--dump-dom', $url],
	[1 => ['pipe', 'w'], 2 => ['file', $dir . '/navegador.log', 'w']],
	$pipes
);
if (!is_resource($proc)) {
	fwrite(STDERR, "No se pudo abrir el navegador: {$navegador}\n");
	exit(2);
}
$dom = (string) stream_get_contents($pipes[1]);
fclose($pipes[1]);
proc_close($proc);

$lineas = [];
if (preg_match('#<pre id="at-pt-resultado">(.*?)</pre>#s', $dom, $mm)) {
	$lineas = array_values(array_filter(explode("\n", html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8')), 'strlen'));
}
ok($lineas !== [], 'el navegador corrió el chequeo y devolvió su resultado');
if ($lineas === []) {
	fwrite(STDERR, 'Salida del navegador (primeros 2000 caracteres): ' . substr($dom, 0, 2000) . "\n");
}
foreach ($lineas as $l) {
	ok(strncmp($l, 'ok   ', 5) === 0, (string) preg_replace('/^(ok|FALLA)\s+/u', '', $l));
}
ptj_borrar($dir);
fin();
