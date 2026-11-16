<?php
// php tests/agenda-rutas-clave/capi-lead-test.php
// POST /leads solo dispara el Lead de la CAPI cuando la llamada trae la credencial de n8n (16-nov-2026).
require __DIR__ . '/dobles.php';

$api = $tema . '/api-endpoints.php';
$fn = extraer_funcion($api, 'automatiza_tech_save_lead');
$n = substr_count($fn, "do_action('automatiza_tech_lead_guardado'");
comprobar($n === 1, 'save_lead dispara el Lead una sola vez', (string) $n);
$pos = strpos($fn, "do_action('automatiza_tech_lead_guardado'");
$antes = substr($fn, 0, $pos);
$if = strrpos($antes, 'if (');
$cond = $if === false ? '' : substr($antes, $if, $pos - $if);
comprobar($if !== false && strpos($cond, 'at_agenda_credencial_valida($request)') !== false, 'el Lead va dentro del if de la credencial de n8n', trim($cond));
comprobar(strpos($fn, 'no se envia a Meta') !== false, 'sin credencial queda anotado que no va a Meta');

// Nadie más dispara el Lead (si apareciera otro do_action, saltaría la guarda).
$otros = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(raiz() . '/wp-content', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
	if ($f->getExtension() !== 'php') { continue; }
	if (strpos((string) file_get_contents($f->getPathname()), "do_action('automatiza_tech_lead_guardado'") !== false) { $otros[] = $f->getPathname(); }
}
comprobar(count($otros) === 1 && realpath($otros[0]) === realpath($api), 'solo api-endpoints.php dispara automatiza_tech_lead_guardado', implode(' | ', $otros));

echo "-- la guarda en vivo\n";
eval(extraer_funcion($tema . '/cierre-cliente/whatsapp.php', 'at_cc_rest_auth'));
require_once $tema . '/agenda-rutas-clave/puras.php';
comprobar(at_agenda_credencial_valida(new WP_REST_Request('POST', '/automatiza-tech/v1/leads', ['x_at_secret' => AT_REST_SECRET])) === true, 'con la credencial: el Lead sale');
comprobar(at_agenda_credencial_valida(new WP_REST_Request('POST', '/automatiza-tech/v1/leads', [])) === false, 'sin cabecera: no sale');
comprobar(at_agenda_credencial_valida(new WP_REST_Request('POST', '/automatiza-tech/v1/leads', ['x_at_secret' => 'otra-clave'])) === false, 'con cabecera invalida: no sale');
cierre();
