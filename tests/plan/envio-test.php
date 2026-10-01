<?php
// Correr: php tests/plan/envio-test.php   (sin WordPress)
// Etapa 2: enlaces, textos, correo, validación de la hora y token de la agenda web.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Enlaces
ok(at_pt_url_ver_plan('https://automatizatech.cl/', 'Ab3dE5fG7hJ9') === 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9', 'enlace a ver-plan.php sin barra doble');
ok(at_pt_url_ver_plan('https://automatizatech.cl', 'Ab3dE5fG7hJ9', true) === 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9&agendar=1', 'con agendar=1 abre el selector de horarios');
ok(at_pt_url_ver_plan('', 'Ab3dE5fG7hJ9') === '' && at_pt_url_ver_plan('https://x.cl', 'malo/../x') === '' && at_pt_url_ver_plan('https://x.cl', '') === '', 'sin sitio o con código raro: sin enlace');
ok(at_pt_url_whatsapp_agenda('56927002984', 'Ab3dE5fG7hJ9') === 'https://wa.me/56927002984?text=Hola%20Tech%2C%20quiero%20agendar%20la%20llamada%20de%20seguimiento%20de%20mi%20plan%20de%20trabajo%20%28c%C3%B3digo%20Ab3dE5fG7hJ9%29', 'WhatsApp con Tech con el código');
ok(at_pt_url_whatsapp_agenda('', 'Ab3dE5fG7hJ9') === '', 'sin número de AT: sin enlace');

// Teléfonos para wa.me
ok(at_pt_telefono_wa('+56 9 1111 1111') === '56911111111' && at_pt_telefono_wa('9 1111 1111') === '56911111111', 'celular chileno con o sin +56');
ok(at_pt_telefono_wa('+51 987 654 321') === '51987654321', 'número extranjero con código de país');
ok(at_pt_telefono_wa('') === '' && at_pt_telefono_wa('1234') === '' && at_pt_telefono_wa('sin número') === '', 'vacío o muy corto: sin teléfono');

// Texto del WhatsApp de Luis al cliente
$t = at_pt_texto_whatsapp_envio('Cliente Prueba', '[PRUEBA] Sitio', 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9');
ok(strpos($t, 'Hola Cliente Prueba, te escribe Luis de AutomatizaTech.') === 0 && strpos($t, 'plan de trabajo de [PRUEBA] Sitio') !== false && strpos($t, 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9') !== false && strpos($t, 'llamada de seguimiento') !== false, 'WhatsApp: saludo, proyecto, enlace e invitación a agendar');
ok(strpos(at_pt_texto_whatsapp_envio('', '', 'https://x.cl/ver-plan.php?id=Ab3dE5fG7hJ9'), 'Hola, te escribe Luis') === 0, 'sin nombre: «Hola,»');

// Entrega estimada
$plan = ['cronograma' => ['fin' => '2027-04-06', 'hitos' => [['nombre' => 'Prototipo', 'fecha' => '2026-11-05'], ['nombre' => 'Entrega estimada', 'fecha' => '2027-02-15']]]];
ok(at_pt_entrega_estimada($plan) === '2027-02-15', 'entrega estimada desde el hito');
ok(at_pt_entrega_estimada(['cronograma' => ['fin' => '2027-04-06', 'hitos' => []]]) === '2027-04-06' && at_pt_entrega_estimada([]) === '', 'sin hito: fin del cronograma; sin cronograma: vacío');

// Correo
$v = [
	'nombre' => 'Cliente <Prueba>', 'proyecto' => '[PRUEBA] Sitio & tienda', 'logo' => 'https://automatizatech.cl/logo.png',
	'url_ver' => 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9', 'url_agendar' => 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9&agendar=1',
	'url_whatsapp' => 'https://wa.me/56927002984?text=Hola', 'inicio' => '2026-10-05', 'entrega' => '2027-02-15', 'con_pdf' => true,
];
$h = at_pt_correo_plan_html($v);
ok(strpos($h, 'Cliente &lt;Prueba&gt;') !== false && strpos($h, '[PRUEBA] Sitio &amp; tienda') !== false, 'correo: nombre y proyecto escapados');
ok(strpos($h, 'href="https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9"') !== false && strpos($h, 'agendar=1') !== false && strpos($h, 'https://wa.me/56927002984') !== false, 'correo: ver el plan, agendar en la web y por WhatsApp');
ok(strpos($h, '5 de octubre de 2026') !== false && strpos($h, '15 de febrero de 2027') !== false && strpos($h, 'estimad') !== false, 'correo: fechas largas y que son estimadas');
ok(strpos($h, 'adjunto') !== false && strpos(at_pt_correo_plan_html(['con_pdf' => false] + $v), 'adjunto') === false, 'correo: menciona el PDF adjunto solo si va adjunto');
ok(stripos($h, 'easypanel') === false, 'correo: ningún enlace al renderer (Hostinger lo rechaza)');
ok(stripos(at_pt_correo_plan_html(['url_ver' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/index.html'] + $v), 'easypanel') === false, 'correo: un enlace del renderer que se cuele no se dibuja');

// Hora de la agenda: martes 6-oct-2026, 09:00-18:00, ocupada a las 11:00. «Ahora» = lunes 5-oct 10:20.
$disp = ['isFullDay' => false, 'busySlots' => ['11:00'], 'workingHours' => ['start' => '09:00', 'end' => '18:00']];
$ahora = '2026-10-05 10:20';
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:00', $ahora, $disp) === '', 'hora libre dentro del horario: sirve');
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:00:00', $ahora, $disp) === '', 'acepta HH:00:00');
ok(at_pt_motivo_hora_agenda('2026-10-06', '11:00', $ahora, $disp) === 'hora_ocupada', 'Review Focus 2: hora ocupada');
ok(at_pt_motivo_hora_agenda('2026-10-06', '08:00', $ahora, $disp) === 'fuera_de_horario' && at_pt_motivo_hora_agenda('2026-10-06', '18:00', $ahora, $disp) === 'fuera_de_horario', 'antes del inicio o desde el fin: fuera de horario');
ok(at_pt_motivo_hora_agenda('2026-10-05', '10:00', $ahora, $disp) === 'pasada' && at_pt_motivo_hora_agenda('2026-10-04', '15:00', $ahora, $disp) === 'pasada', 'Review Focus 2: hoy a una hora ya empezada o un día pasado');
ok(at_pt_motivo_hora_agenda('2026-10-05', '11:00', $ahora, ['busySlots' => []] + $disp) === '', 'hoy, la hora siguiente sí sirve');
ok(at_pt_motivo_hora_agenda('2027-01-04', '10:00', $ahora, $disp) === 'muy_lejos' && at_pt_motivo_hora_agenda('2027-01-03', '10:00', $ahora, $disp) !== 'muy_lejos', 'hasta 90 días hacia adelante');
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:00', $ahora, ['isFullDay' => true]) === 'dia_no_disponible' && at_pt_motivo_hora_agenda('2026-10-06', '10:00', $ahora, []) === 'dia_no_disponible', 'Review Focus 2: día completo, feriado o sin disponibilidad');
ok(at_pt_motivo_hora_agenda('2026-02-30', '10:00', $ahora, $disp) === 'fecha_invalida' && at_pt_motivo_hora_agenda('mañana', '10:00', $ahora, $disp) === 'fecha_invalida', 'fecha que no existe');
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:30', $ahora, $disp) === 'hora_invalida' && at_pt_motivo_hora_agenda('2026-10-06', '25:00', $ahora, $disp) === 'hora_invalida' && at_pt_motivo_hora_agenda('2026-10-06', '', $ahora, $disp) === 'hora_invalida', 'solo horas en punto válidas');
$mensajes = at_pt_mensajes_agenda();
foreach (['fecha_invalida', 'hora_invalida', 'pasada', 'muy_lejos', 'dia_no_disponible', 'fuera_de_horario', 'hora_ocupada', 'plan_no_disponible', 'sesion_vencida', 'muchos_intentos', 'sin_correo', 'ya_agendada', 'no_guardo'] as $k) {
	ok(isset($mensajes[$k]) && is_string($mensajes[$k]) && $mensajes[$k] !== '', "mensaje para «{$k}»");
}

// Token de la agenda
$sal = 'sal-de-prueba';
$tok = at_pt_token_agenda('Ab3dE5fG7hJ9', 20000, $sal);
ok(preg_match('/^[a-f0-9]{24}$/', $tok) === 1, 'token de 24 caracteres hexadecimales');
ok(at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20000, $sal) && at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20001, $sal), 'vale el día que se creó y el siguiente');
ok(!at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20002, $sal), 'a los dos días vence');
ok(!at_pt_token_agenda_valido($tok, 'Zz3dE5fG7hJ9', 20000, $sal) && !at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20000, 'otra-sal') && !at_pt_token_agenda_valido('', 'Ab3dE5fG7hJ9', 20000, $sal), 'otro plan, otra sal o vacío: no vale');

// El render llena la agenda web cuando viene el sitio (sin sitio queda vacío, como en la Etapa 1)
$p = at_pt_validar_plan(['proyecto' => '[PRUEBA] P', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'B', 'actividades' => [['nombre' => 'A', 'responsable' => 'at', 'dias_habiles' => 2]]]]]]]);
$r = at_pt_armar_render($p['plan'], ['codigo' => 'Ab3dE5fG7hJ9', 'company_name' => 'E', 'whatsapp' => '56927002984', 'sitio' => 'https://automatizatech.cl'], true);
ok($r['agenda']['web_url'] === 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9&agendar=1', 'render: agenda.web_url apunta a ver-plan.php con agendar=1');
ok(at_pt_armar_render($p['plan'], ['codigo' => 'Ab3dE5fG7hJ9', 'company_name' => 'E'], true)['agenda']['web_url'] === '', 'render sin sitio: web_url vacío');

fin();
