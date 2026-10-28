<?php
// Las agendas del sitio le pasan a atLead los datos de la huella; el respaldo de contacto ya no cuenta como Lead.
// Uso: php tests/meta-capi/plantillas-test.php
$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
$tema = __DIR__ . '/../../wp-content/themes/automatiza-tech';
$footer = (string) file_get_contents("$tema/footer.php");
$home = (string) file_get_contents("$tema/assets/home-premium/at-home.js");
$consent = (string) file_get_contents(__DIR__ . '/../../wp-content/mu-plugins/at-consentimiento.php');

ok(preg_match("/window\.atLead\('Agenda de demo', \{ *email: *email, *fecha: *date, *hora: *time *\}\)/", $footer) === 1,
	'footer: «Agenda de demo» pasa correo, fecha y hora');
ok(strpos($footer, "atLead('Agenda del sitio'") === false, 'footer: el respaldo de contacto ya no dispara Lead');
ok(substr_count($footer, 'atLead(') === 1, 'footer: un solo llamado a atLead');
ok(preg_match("/window\.atLead\('Agenda diagnóstico \(portada\)', \{ *email: *payload\.email, *fecha: *payload\.scheduled_date, *hora: *payload\.scheduled_time *\}\)/", $home) === 1,
	'portada: pasa correo, fecha y hora');
ok(strpos($consent, 'at-lead-id.js') !== false && strpos($consent, 'file_get_contents') !== false, 'el aviso de cookies incluye at-lead-id.js en la página');
ok(strpos($consent, "leer() !== 'si'") !== false, 'atLead sigue sin enviar nada sin permiso');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
