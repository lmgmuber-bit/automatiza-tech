<?php
// php tests/agenda-rutas-clave/recorte-test.php
// Las respuestas llevan solo los campos que usan los flujos; AT_AGENDA_RESPUESTA=completa devuelve todo.
require __DIR__ . '/dobles.php';
require $tema . '/agenda-rutas-clave/puras.php';

$reunion = [
	'id' => 7, 'client_name' => 'Ana', 'client_email' => 'ana@x.test', 'company_name' => 'Empresa', 'phone' => '+56911112222',
	'meeting_date' => '2027-01-01', 'meeting_time' => '10:00:00', 'meet_link' => 'https://meet.test/abc', 'meeting_subject' => 'Tema',
	'notes' => 'nota interna', 'status' => 'scheduled', 'contrato_id' => 3, 'propuesta_id' => 0,
];
$r = at_agenda_recortar_reunion($reunion);
foreach (['client_name', 'client_email', 'company_name', 'meeting_date', 'meeting_time'] as $k) { comprobar(array_key_exists($k, $r), "GET {id} conserva $k"); }
foreach (['id', 'status', 'contrato_id', 'propuesta_id'] as $k) { comprobar(array_key_exists($k, $r), "GET {id} conserva $k (imprescindible o con duda)"); }
foreach (['phone', 'meet_link', 'meeting_subject', 'notes'] as $k) { comprobar(!array_key_exists($k, $r), "GET {id} quita $k"); }

$b = at_agenda_recortar_busqueda($reunion);
comprobar(array_keys($b) === ['id', 'meeting_date', 'meeting_time', 'status'] || array_diff(array_keys($b), ['id', 'meeting_date', 'meeting_time', 'status']) === [], 'search solo deja id, fecha, hora y estado');
comprobar(!isset($b['client_email']) && !isset($b['client_name']) && !isset($b['phone']) && !isset($b['meet_link']), 'search no devuelve datos personales');

$e = at_agenda_recortar_evento(['exists' => true, 'event_id' => 'abc', 'in_followups' => true, 'in_demos' => false]);
comprobar($e === ['exists' => true, 'event_id' => 'abc'], 'check-event deja exists y event_id');

echo "-- vuelta atras\n";
$GLOBALS['opciones']['at_agenda_respuesta'] = 'completa';
comprobar(at_agenda_recortar_reunion($reunion) === $reunion, 'completa devuelve la reunion entera');
comprobar(at_agenda_recortar_busqueda($reunion) === $reunion, 'completa devuelve la busqueda entera');
comprobar(count(at_agenda_recortar_evento(['exists' => true, 'event_id' => 'a', 'in_demos' => true])) === 3, 'completa devuelve check-event entero');
cierre();
