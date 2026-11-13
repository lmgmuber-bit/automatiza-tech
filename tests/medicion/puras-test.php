<?php
// Correr: php tests/medicion/puras-test.php   (sin WordPress; si hay Node, también ejecuta los scripts generados)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/medicion/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// IDs
ok(at_med_id_ga4_valido('G-ABC123DEF4'), 'GA4 válido');
foreach (['', 'G-', 'UA-123456-1', 'g-abc123def4', 'G-ABC</script>', "G-ABC123DEF4\n", 'AW-17691911857'] as $malo) {
	ok(!at_med_id_ga4_valido($malo), 'GA4 inválido: ' . json_encode($malo));
}
ok(at_med_id_ads_valido('AW-17691911857'), 'Ads válido');
foreach (['', 'AW-', 'AW-12AB', 'G-ABC123DEF4', "AW-123456\n"] as $malo) {
	ok(!at_med_id_ads_valido($malo), 'Ads inválido: ' . json_encode($malo));
}
ok(at_med_ids('G-ABC123DEF4', 'AW-17691911857') === ['G-ABC123DEF4', 'AW-17691911857'], 'GA4 primero, Ads después');
ok(at_med_ids('', 'AW-17691911857') === ['AW-17691911857'], 'solo Ads');
ok(at_med_ids('malo', 'malo') === [], 'nada válido: lista vacía');

// Scripts
ok(at_med_script_cabecera([]) === '', 'sin IDs no se imprime nada');
$cab = at_med_script_cabecera(['G-ABC123DEF4', 'AW-17691911857']);
ok(strpos($cab, 'at_consentimiento=si') !== false, 'la cabecera exige el permiso de cookies');
ok(strpos($cab, '"G-ABC123DEF4","AW-17691911857"') !== false, 'la cabecera configura los dos IDs');
ok(strpos($cab, 'AmZuCJaF') === false && strpos($cab, "'conversion'") === false, 'ya no hay conversión en cada carga');
$lead = at_med_script_lead();
ok(strpos($lead, "'generate_lead'") !== false, 'el pie manda generate_lead');
ok(strpos($lead, 'return original.apply(this,arguments)') !== false, 'el envoltorio devuelve lo del atLead original');

// Plantilla vieja: header.php sin la etiqueta fija de Google Ads
$header = (string) file_get_contents(__DIR__ . '/../../wp-content/themes/automatiza-tech/header.php');
ok(strpos($header, 'AW-17691911857') === false && strpos($header, 'Compra conversion') === false, 'header.php sin la conversión «Compra» en cada carga');

// Ejecución real de los scripts en Node, simulando el navegador
$node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
if ($node === '') {
	echo "NOT_RUN ejecución en Node (no hay node en el PATH)\n";
} else {
	$js = function (string $cookie) use ($cab, $lead) {
		$cuerpo = preg_replace('#</?script>#', '', $cab . $lead);
		$prog = 'var cargados=[],metaLeads=0;'
			. 'var document={cookie:' . json_encode($cookie) . ',createElement:function(){return {};},head:{appendChild:function(s){cargados.push(s.src);}}};'
			. 'var window=globalThis;window.atLead=function(o){metaLeads++;return "original:"+o;};'
			. $cuerpo
			. 'var r=window.atLead("agenda");'
			. 'var eventos=(window.dataLayer||[]).map(function(a){return Array.prototype.slice.call(a);});'
			. 'console.log(JSON.stringify({cargados:cargados,eventos:eventos,r:r,metaLeads:metaLeads}));';
		$f = tempnam(sys_get_temp_dir(), 'med') . '.js';
		file_put_contents($f, $prog);
		$salida = shell_exec('node ' . escapeshellarg($f));
		@unlink($f);
		return json_decode((string) $salida, true);
	};
	$sin = $js('otra=1');
	ok(is_array($sin) && $sin['cargados'] === [] && $sin['eventos'] === [], 'sin permiso: no carga gtag.js ni manda eventos');
	ok(is_array($sin) && $sin['r'] === 'original:agenda' && $sin['metaLeads'] === 1, 'sin permiso: atLead original sigue igual');
	$con = $js('a=1; at_consentimiento=si');
	ok(is_array($con) && count($con['cargados']) === 1 && strpos($con['cargados'][0], 'id=G-ABC123DEF4') !== false, 'con permiso: carga gtag.js con el ID de GA4');
	$tipos = is_array($con) ? array_map(function ($e) { return $e[0] . ':' . (is_string($e[1] ?? null) ? $e[1] : ''); }, $con['eventos']) : [];
	ok(in_array('config:G-ABC123DEF4', $tipos, true) && in_array('config:AW-17691911857', $tipos, true), 'con permiso: configura GA4 y Ads');
	ok(in_array('event:generate_lead', $tipos, true), 'con permiso: atLead manda generate_lead');
	ok(is_array($con) && $con['r'] === 'original:agenda' && $con['metaLeads'] === 1, 'con permiso: el aviso a Meta sigue saliendo una vez');
	$no = $js('at_consentimiento=no');
	ok(is_array($no) && $no['cargados'] === [], 'rechazó: no carga nada');
}

echo $fallas ? "\n{$fallas} FALLAS\n" : "\nTodo bien\n";
exit($fallas ? 1 : 0);
