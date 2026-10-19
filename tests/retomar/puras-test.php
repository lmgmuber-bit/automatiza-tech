<?php
// PHP sin WordPress. Datos ficticios; nunca envía mensajes.
$archivo = __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/retomar/puras.php';
if (is_file($archivo)) { require_once $archivo; }
$total = 0;
$fallas = 0;
function ok($valor, string $mensaje): void {
	global $total, $fallas;
	$total++;
	if (!$valor) { $fallas++; }
	echo ($valor ? 'ok ' : 'FALLA ') . $mensaje . "\n";
}
$funciones = ['intervalo', 'max', 'es_habil', 'en_ventana', 'proximo', 'canales', 'motivo_parada', 'estado_por_motivo', 'token', 'token_valido', 'asunto', 'correo_html', 'correo_texto', 'cabeceras_baja', 'internas', 'tiene_montos', 'es_candidata', 'necesita_propuesta', 'semana', 'toca_resumen', 'programar', 'firma', 'firma_valida', 'tarjeta_html', 'resumen_html', 'minuto_reloj', 'ultima_pasada', 'resumen_hora_valida', 'marca_vieja'];
foreach ($funciones as $f) { ok(function_exists('at_rt_' . $f), 'interfaz at_rt_' . $f); }
if ($fallas) { echo "$total pruebas, $fallas fallas\n"; exit(1); }
ok(at_rt_intervalo(3) === 7 && at_rt_intervalo(10) === 10, 'intervalo mínimo');
ok(at_rt_max(9) === 4 && at_rt_max(0) === 1, 'tope 1..4');
ok(at_rt_es_habil('2026-10-16') && !at_rt_es_habil('2026-10-17') && !at_rt_es_habil('2026-10-18'), 'días hábiles');
ok(!at_rt_es_habil('2026-02-30') && !at_rt_es_habil('basura'), 'fechas inválidas');
foreach (['09:59' => false, '10:00' => true, '12:59' => true, '13:00' => false, '10:99' => false, '10:00x' => false] as $h => $v) { ok(at_rt_en_ventana($h) === $v, 'ventana ' . $h); }
ok(at_rt_proximo('2026-10-16', 7) === '2026-10-23', 'próximo viernes');
ok(at_rt_proximo('2026-10-17', 7) === '2026-10-26', 'próximo sábado avanza a lunes');
ok(at_rt_proximo('2026-10-16', 1) === '2026-10-23', 'próximo aplica mínimo semanal');
foreach ([1, 2, 3, 4] as $n) { ok(at_rt_canales($n, 4, 'correo,whatsapp', true, '56900000000') === ['correo', 'whatsapp'], 'canales envío ' . $n . ' lleva WhatsApp'); }
ok(at_rt_canales(1, 4, 'correo,whatsapp', false, '56900000000') === ['correo'], 'sin autorización');
ok(at_rt_canales(1, 4, 'correo,whatsapp', true, '') === ['correo'], 'sin teléfono');
ok(at_rt_canales(1, 4, 'correo,no-whatsapp', true, '56900000000') === ['correo'], 'canal exacto');
foreach ([['baja' => true, 'reunion' => true], ['propuesta_estado' => 'rechazada', 'crm_tipo' => 'cliente'], ['propuesta_estado' => 'aceptada'], ['crm_tipo' => 'cliente', 'reunion' => true], ['reunion' => true, 'enviados' => 4, 'max' => 4], ['enviados' => 4, 'max' => 4], []] as $i => $c) { ok(at_rt_motivo_parada($c) === ['baja', 'rechazada', 'aceptada', 'cliente', 'agendo', 'agotado', ''][$i], 'prioridad parada ' . $i); }
foreach (['baja' => 'baja', 'agendo' => 'agendo', 'cliente' => 'cliente', 'aceptada' => 'cliente', 'rechazada' => 'rechazado', 'agotado' => 'agotado', '' => '', 'inventado' => ''] as $m => $e) { ok(at_rt_estado_por_motivo($m) === $e, 'estado ' . $m); }
$token = at_rt_token(5, 'prueba-sin-secreto');
ok($token === substr(hash_hmac('sha256', 'rt|5', 'prueba-sin-secreto'), 0, 32), 'token exacto');
ok(at_rt_token_valido($token, 5, 'prueba-sin-secreto') && !at_rt_token_valido($token, 6, 'prueba-sin-secreto') && !at_rt_token_valido('invalido', 5, 'prueba-sin-secreto'), 'validación token');
foreach ([1 => '¿Retomamos tu proyecto, [PRUEBA]?', 2 => '[PRUEBA], tu demo sigue lista para ti', 3 => 'Podemos ajustarlo a tu presupuesto, [PRUEBA]', 4 => 'Último recordatorio: ¿conversamos 20 minutos?'] as $n => $a) { ok(at_rt_asunto($n, '[PRUEBA]') === $a, 'asunto ' . $n); }
ok(at_rt_asunto(1, '') === '¿Retomamos tu proyecto?' && at_rt_asunto(2, '') === 'tu demo sigue lista para ti' && at_rt_asunto(3, '') === 'Podemos ajustarlo a tu presupuesto', 'asuntos sin nombre');
$d = ['nombre' => '[PRUEBA]<script>alert(1)</script>', 'negocio' => '[PRUEBA] & negocio', 'n' => 1, 'quedamos' => '<script>alert(2)</script>', 'url_ver' => 'https://example.com/ver?a=1&b=2', 'url_agendar' => 'https://example.com/agenda', 'url_baja' => 'https://example.com/baja', 'logo' => 'https://example.com/logo.png'];
$html = at_rt_correo_html($d);
$texto = at_rt_correo_texto($d);
ok(!str_contains($html, '<script') && str_contains($html, '&lt;script&gt;') && str_contains($html, '&amp; negocio'), 'escape XSS');
foreach (['url_ver', 'url_agendar', 'url_baja'] as $k) { ok(str_contains($html, htmlspecialchars($d[$k], ENT_QUOTES, 'UTF-8')) && str_contains($texto, $d[$k]), 'correo incluye ' . $k); }
ok(str_contains($html, 'Ver resumen y demo') && str_contains($html, 'Agendar 20 minutos') && str_contains($html, 'Prefiero que no me escriban más') && str_contains($texto, 'Ver resumen y demo: ') && str_contains($texto, 'te saluda el equipo de AutomatizaTech'), 'CTA de la maqueta aprobada y voz equipo');
foreach (['javascript:alert(1)', 'http://example.com', '//example.com', 'https://example.com/" onerror="alert(1)', "https://example.com/\r\nBcc:test@example.com", 'https://example.com/%0d%0aBcc:test@example.com', 'mailto:test@example.com', 'https://', 'https://demo.easypanel.host/'] as $mal) {
	$hostil = array_replace($d, ['url_ver' => $mal, 'url_agendar' => $mal, 'url_baja' => $mal, 'logo' => $mal]);
	ok(!str_contains(at_rt_correo_html($hostil), 'href=') && !str_contains(at_rt_correo_html($hostil), '<img') && !str_contains(at_rt_correo_texto($hostil), $mal) && at_rt_cabeceras_baja($mal) === [], 'URLs hostiles excluidas ' . json_encode($mal));
}
ok(at_rt_cabeceras_baja($d['url_baja']) === ['List-Unsubscribe: <https://example.com/baja>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'], 'cabeceras HTTPS exactas');
$internas = at_rt_internas(['texto' => 'Servidor EASYpanel interno', 'anidado' => ['https://demo.easypanel.host/', ['texto' => 'Servidor EASYpanel interno']], 'seguro' => 'https://example.com', 'numero' => 1]);
ok($internas === ['Servidor EASYpanel interno', 'https://demo.easypanel.host/'], 'D8 recursivo y único');
ok(!str_contains(strtolower(at_rt_correo_html(array_replace($d, ['quedamos' => 'No mostrar easypanel interno']))), 'easypanel') && !str_contains(strtolower(at_rt_correo_texto(array_replace($d, ['nombre' => 'easypanel']))), 'easypanel'), 'D8 también en textos visibles');
// Compartir a mano mientras Meta aprueba la plantilla (pedido de Luis, 18-oct).
$ver = 'https://automatizatech.cl/retomar.php?t=abc&agendar=1';
$baja = 'https://automatizatech.cl/retomar.php?t=abc&baja=1';
$m = at_rt_texto_manual('Ana', 'Panadería Prueba', $ver, $baja);
ok(str_starts_with($m, 'Hola Ana 👋 Te saluda el equipo de AutomatizaTech.'), 'manual: saludo con el nombre');
ok(str_contains($m, 'conversamos sobre Panadería Prueba y quedó pendiente'), 'manual: negocio en el texto');
ok(str_contains($m, "Ver y agendar: $ver") && str_contains($m, "Si prefieres que no te escribamos más: $baja"), 'manual: los dos enlaces');
$m2 = at_rt_texto_manual('', '', $ver, $baja);
ok(str_starts_with($m2, 'Hola 👋 Te saluda') && str_contains($m2, 'conversamos sobre tu proyecto'), 'manual: sin nombre ni negocio usa frases neutras');
$wa = at_rt_url_wa_manual('56900000000', "Hola & chao\nfin");
ok($wa === 'https://wa.me/56900000000?text=' . rawurlencode("Hola & chao\nfin"), 'manual: wa.me con el texto codificado');
ok(at_rt_url_wa_manual('', 'x') === '', 'manual: sin teléfono no hay enlace');
// Fase 2 (aprobación semanal): reglas puras.
foreach (['$680.000', '680.000', '1.500', 'USD 500', '2 UF', 'CLP', 'unos 1,500 pesos'] as $texto) { ok(at_rt_tiene_montos($texto) === true, 'montos detecta ' . $texto); }
// Ruling 19: también cifras con la moneda o la cantidad en palabras, con o sin espacio, y siglas pegadas a la cifra.
foreach (['300 pesos', '300pesos', '20 dólares', '20 dolares', '20dólares', '50 lucas', '5 mil', '5mil', 'unos 2 millones', '1 millón', 'USD500', '500USD', 'US$500', 'CLP2000', '2000CLP', 'UF3', '3UF', 'cuesta 5 MIL'] as $texto) { ok(at_rt_tiene_montos($texto) === true, 'montos detecta ' . $texto); }
foreach (['en 2 semanas', 'fase 1 en 30 días', 'año 2026', '10:00 a 13:00', 'UFO', '', 'mil gracias', 'son 3 milímetros', 'a 2 millas', 'sufijo', 'el 2 de mayo'] as $texto) { ok(at_rt_tiene_montos($texto) === false, 'montos no confunde «' . $texto . '»'); }
ok(at_rt_tiene_montos("5 mil\xff") === true && at_rt_tiene_montos("en 2 semanas\xff") === false, 'montos: un texto que no es UTF-8 válido se revisa igual');
$hoy = '2026-10-18';
$cliente_ok = ['crm_tipo' => 'prospecto', 'baja' => false, 'existe' => false];
ok(at_rt_es_candidata(['status' => 'sent', 'enviada' => '2026-10-04'], $cliente_ok, $hoy) === '', 'candidata: sent hace exactamente 14 días');
ok(at_rt_es_candidata(['status' => 'sent', 'enviada' => '2026-10-05'], $cliente_ok, $hoy) === 'reciente', 'candidata: sent hace 13 días es reciente');
ok(at_rt_es_candidata(['status' => 'evaluando', 'enviada' => '2026-09-01'], $cliente_ok, $hoy) === '', 'candidata: evaluando antigua');
foreach (['aceptada', 'archivada', 'pending', 'rechazada', 'lista'] as $estado) { ok(at_rt_es_candidata(['status' => $estado, 'enviada' => '2026-09-01'], $cliente_ok, $hoy) === 'estado', 'candidata: estado ' . $estado); }
$antigua = ['status' => 'sent', 'enviada' => '2026-09-01'];
ok(at_rt_es_candidata($antigua, ['crm_tipo' => 'cliente'] + $cliente_ok, $hoy) === 'cliente', 'candidata: cliente del CRM');
ok(at_rt_es_candidata($antigua, ['baja' => true] + $cliente_ok, $hoy) === 'baja', 'candidata: con baja');
ok(at_rt_es_candidata($antigua, ['existe' => true] + $cliente_ok, $hoy) === 'existe', 'candidata: ya tiene seguimiento');
ok(at_rt_es_candidata(['status' => 'lista', 'enviada' => '2026-10-17'], ['crm_tipo' => 'cliente', 'baja' => true, 'existe' => true], $hoy) === 'estado', 'candidata: el estado se revisa primero');
ok(at_rt_es_candidata(['status' => 'sent', 'enviada' => '2026-10-17'], ['crm_tipo' => 'cliente', 'baja' => true, 'existe' => true], $hoy) === 'reciente', 'candidata: luego lo reciente');
ok(at_rt_es_candidata($antigua, ['crm_tipo' => 'cliente', 'baja' => true, 'existe' => true], $hoy) === 'cliente', 'candidata: luego cliente');
ok(at_rt_es_candidata($antigua, ['baja' => true, 'existe' => true] + $cliente_ok, $hoy) === 'baja', 'candidata: luego baja');
ok(at_rt_es_candidata(['status' => 'sent', 'enviada' => ''], $cliente_ok, $hoy) === 'reciente' && at_rt_es_candidata(['status' => 'sent', 'enviada' => 'basura'], $cliente_ok, $hoy) === 'reciente', 'candidata: sin fecha de envío válida no se toca');
ok(at_rt_es_candidata(['status' => 'sent', 'enviada' => '2026-10-11'], $cliente_ok, $hoy, 7) === '' && at_rt_es_candidata(['status' => 'sent', 'enviada' => '2026-10-12'], $cliente_ok, $hoy, 7) === 'reciente', 'candidata: días mínimos configurables');
ok(at_rt_necesita_propuesta('v3') === false && at_rt_necesita_propuesta(null) === true && at_rt_necesita_propuesta('') === true && at_rt_necesita_propuesta('gamma') === true, 'propuesta nueva solo si el flujo no es v3');
ok(at_rt_semana('2026-10-19 09:00:00') === '2026-W43', 'semana ISO del lunes 19-oct');
ok(at_rt_semana('2027-01-01 10:00:00') === '2026-W53', 'semana ISO de fin de año');
ok(at_rt_semana('2026-10-18 23:30:00') === '2026-W42' && at_rt_semana('2026-10-19 00:30:00') === '2026-W43', 'semana: domingo 23:30 y lunes 00:30 son semanas distintas');
ok(at_rt_semana('2026-10-19T09:00:00') === '2026-W43' && at_rt_semana('2026-10-19') === '2026-W43' && at_rt_semana('2026-02-30 10:00:00') === '' && at_rt_semana('basura') === '', 'semana: formatos admitidos y fechas inválidas');
ok(at_rt_toca_resumen('2026-10-19 08:59', 1, '09:00', '') === false, 'toca: lunes antes de la hora');
ok(at_rt_toca_resumen('2026-10-19 09:00', 1, '09:00', '2026-W42') === true, 'toca: lunes a la hora');
ok(at_rt_toca_resumen('2026-10-19 09:00:00', 1, '09:00', '') === true, 'toca: acepta segundos');
ok(at_rt_toca_resumen('2026-10-20 10:00', 1, '09:00', '') === true, 'toca: el martes recupera un lunes perdido');
ok(at_rt_toca_resumen('2026-10-20 10:00', 1, '09:00', '2026-W43') === false, 'toca: ya se envió esta semana');
ok(at_rt_toca_resumen('2026-10-18 23:30', 1, '09:00', '2026-W42') === false, 'toca: domingo 23:30 de una semana ya enviada');
ok(at_rt_toca_resumen('2026-10-18 23:30', 1, '09:00', '2026-W41') === true, 'toca: domingo 23:30 de una semana pendiente');
ok(at_rt_toca_resumen('2026-10-19 00:30', 1, '09:00', '2026-W42') === false, 'toca: lunes 00:30 es antes de la hora');
ok(at_rt_toca_resumen('2026-10-21 08:00', 3, '08:30', '') === false && at_rt_toca_resumen('2026-10-21 08:30', 3, '08:30', '') === true && at_rt_toca_resumen('2026-10-20 23:59', 3, '08:30', '') === false, 'toca: otro día configurado');
ok(at_rt_toca_resumen('basura', 1, '09:00', '') === false, 'toca: fecha inválida no envía');
// Ruling 15: el reloj pasa al minuto 5 de cada hora; la última pasada dentro de la ventana 10:00–13:00 es la de las 12:05.
ok(at_rt_minuto_reloj() === 5 && at_rt_ultima_pasada() === '12:05', 'reloj: minuto 5 y última pasada en la ventana a las 12:05');
ok(at_rt_en_ventana(at_rt_ultima_pasada()) && !at_rt_en_ventana(sprintf('13:%02d', at_rt_minuto_reloj())), 'la última pasada cae en la ventana y la siguiente ya no');
ok(at_rt_programar('2026-10-23 12:04') === '2026-10-23', 'programar: viernes 12:04 (antes de la última pasada) es hoy');
ok(at_rt_programar('2026-10-23 12:05') === '2026-10-26' && at_rt_programar('2026-10-23 12:05:00') === '2026-10-26', 'programar: viernes 12:05 ya no alcanza la pasada: pasa al lunes');
ok(at_rt_programar('2026-10-23 12:59') === '2026-10-26', 'programar: viernes 12:59 pasa al lunes (no hay pasada entre 12:05 y 13:00)');
ok(at_rt_programar('2026-10-23 13:00') === '2026-10-26', 'programar: viernes 13:00 pasa al lunes');
ok(at_rt_programar('2026-10-24 09:00') === '2026-10-26', 'programar: sábado pasa al lunes');
ok(at_rt_programar('2026-10-19 07:00') === '2026-10-19' && at_rt_programar('2026-10-19 13:00:00') === '2026-10-20' && at_rt_programar('2026-10-19 12:30:00') === '2026-10-20', 'programar: lunes temprano es hoy, lunes 12:30 o más tarde es martes');
// Día 7 (domingo) con una hora posterior a la última pasada del domingo (23:05) nunca saldría: la semana siguiente ya es otra.
ok(at_rt_resumen_hora_valida(7, '23:05') && !at_rt_resumen_hora_valida(7, '23:06') && !at_rt_resumen_hora_valida(7, '23:30'), 'resumen: domingo admite hasta las 23:05');
ok(at_rt_resumen_hora_valida(6, '23:30') && at_rt_resumen_hora_valida(1, '09:00') && at_rt_resumen_hora_valida(1, '23:59'), 'resumen: de lunes a sábado cualquier hora (la pasada siguiente lo recupera)');
ok(!at_rt_resumen_hora_valida(1, '25:00') && !at_rt_resumen_hora_valida(1, '9:00') && !at_rt_resumen_hora_valida(0, '09:00') && !at_rt_resumen_hora_valida(8, '09:00'), 'resumen: hora o día fuera de rango no valen');
ok(at_rt_toca_resumen('2026-10-25 23:05', 7, '23:05', '2026-W42') === true, 'toca: domingo 23:05 configurado sale en la pasada de las 23:05');
// Marcas de tiempo («Y-m-d H:i[:s]» de Chile) para los reintentos del reloj: sin marca legible cuenta como vieja.
$f12 = at_rt_ahora('2026-10-19 12:00:00');
ok(at_rt_marca_vieja(null, $f12, 6) && at_rt_marca_vieja('basura', $f12, 6) && at_rt_marca_vieja(['x'], $f12, 6), 'marca vieja: sin marca legible, sí');
ok(at_rt_marca_vieja('2026-10-19 05:59:59', $f12, 6) && !at_rt_marca_vieja('2026-10-19 06:00:00', $f12, 6) && at_rt_marca_vieja('2026-10-19 06:00:00', $f12, 6, true) && !at_rt_marca_vieja('2026-10-19 11:00', $f12, 6) && !at_rt_marca_vieja('2026-10-19 13:00:00', $f12, 6), 'marca vieja: más de N horas; justo N solo con «incluido»; una futura no');
$sal = 'sal-de-prueba-sin-secreto';
$f = at_rt_firma(7, 'aprobar', 'abcdef0123456789', 1792000000, $sal);
ok($f === substr(hash_hmac('sha256', 'rta|7|aprobar|abcdef0123456789|1792000000', $sal), 0, 32) && preg_match('/^[0-9a-f]{32}$/D', $f) === 1, 'firma: 32 hex del HMAC exacto');
ok(at_rt_firma_valida($f, 7, 'aprobar', 'abcdef0123456789', 1792000000, $sal) === true, 'firma válida con los mismos datos');
ok(at_rt_firma_valida($f, 7, 'omitir', 'abcdef0123456789', 1792000000, $sal) === false, 'firma falsa si cambia la acción');
ok(at_rt_firma_valida($f, 7, 'aprobar', 'abcdef0123456780', 1792000000, $sal) === false, 'firma falsa si cambia el nonce');
ok(at_rt_firma_valida($f, 7, 'aprobar', 'abcdef0123456789', 1792000001, $sal) === false, 'firma falsa si cambia el vencimiento');
ok(at_rt_firma_valida($f, 8, 'aprobar', 'abcdef0123456789', 1792000000, $sal) === false, 'firma falsa si cambia el id');
ok(at_rt_firma_valida($f, 7, 'aprobar', 'abcdef0123456789', 1792000000, 'otra-sal') === false && at_rt_firma_valida('', 7, 'aprobar', 'abcdef0123456789', 1792000000, $sal) === false, 'firma falsa con otra sal o vacía');
foreach (['aprobar', 'cambios', 'omitir', 'ver'] as $accion) { ok(at_rt_firma_valida(at_rt_firma(7, $accion, 'abcdef0123456789', 1792000000, $sal), 7, $accion, 'abcdef0123456789', 1792000000, $sal) === true, 'firma acción ' . $accion); }
ok(at_rt_firma_valida(at_rt_firma(7, 'borrar', 'abcdef0123456789', 1792000000, $sal), 7, 'borrar', 'abcdef0123456789', 1792000000, $sal) === false, 'firma: solo valen las cuatro acciones');
// WhatsApp ya no es solo del primero y el último envío.
foreach ([1, 2, 3, 4] as $n) { ok(at_rt_canales($n, 4, 'correo,whatsapp', true, '+56900000000') === ['correo', 'whatsapp'], 'canales (aprobados): WhatsApp en el envío ' . $n); }
ok(at_rt_canales(2, 4, 'correo,whatsapp', false, '+56900000000') === ['correo'] && at_rt_canales(2, 4, 'correo', true, '+56900000000') === ['correo'] && at_rt_canales(2, 4, 'correo,whatsapp', true, '  ') === ['correo'], 'canales (aprobados): sin autorización, sin canal o sin teléfono solo correo');
// Tarjeta y resumen semanal.
$enlaces = ['aprobar' => 'https://example.com/retomar-aprobar.php?a=aprobar&t=1', 'cambios' => 'https://example.com/retomar-aprobar.php?a=cambios&t=2', 'omitir' => 'https://example.com/retomar-aprobar.php?a=omitir&t=3', 'ver' => 'https://example.com/retomar-aprobar.php?a=ver&t=4'];
$tarjeta = ['tipo' => 'normal', 'nombre' => '[PRUEBA] Ana', 'negocio' => '[PRUEBA] Panadería', 'dias' => 20, 'n' => 1, 'max' => 4, 'canales' => ['correo', 'whatsapp'], 'textos' => ['quedamos' => "Conversamos del pedido\nde la semana", 'proponemos' => 'Ajustamos todo al presupuesto', 'partir' => 'Una primera etapa corta', 'recomendacion' => 'Partir por la tienda en línea'], 'url_presentacion' => 'https://example.com/retomar.php?t=abc&v=1', 'enlaces' => $enlaces];
$h = at_rt_tarjeta_html($tarjeta);
foreach (['✅ Aprobar y enviar', '✏️ Pedir cambios', '⏭️ Esta semana no', 'Recordatorio 1 de 4', '[PRUEBA] Ana', '[PRUEBA] Panadería', 'propuesta enviada hace 20 días', 'correo ✓', 'WhatsApp ✓', 'Conversamos del pedido', 'Ajustamos todo al presupuesto', 'Una primera etapa corta', 'Partir por la tienda en línea', 'Ver la presentación completa', 'Ver el correo tal como le llega'] as $esperado) { ok(str_contains($h, $esperado), 'tarjeta normal contiene «' . $esperado . '»'); }
foreach ($enlaces as $k => $u) { ok(str_contains($h, 'href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '"'), 'tarjeta normal enlaza ' . $k); }
ok(str_contains($h, 'href="' . htmlspecialchars($tarjeta['url_presentacion'], ENT_QUOTES, 'UTF-8') . '"') && !str_contains($h, '<img') && !str_contains($h, '<script') && str_contains($h, 'linear-gradient'), 'tarjeta normal: presentación enlazada, miniatura en HTML y sin <img');
ok(!str_contains(at_rt_tarjeta_html(array_replace($tarjeta, ['canales' => ['correo']])), 'WhatsApp'), 'tarjeta: sin WhatsApp si no sale por WhatsApp');
ok(str_contains(at_rt_tarjeta_html(array_replace($tarjeta, ['dias' => 1])), 'hace 1 día') && str_contains(at_rt_tarjeta_html(array_replace($tarjeta, ['dias' => 0])), 'enviada hoy'), 'tarjeta: singular y hoy');
$sin_reco = $tarjeta;
unset($sin_reco['textos']['recomendacion']);
ok(!str_contains(at_rt_tarjeta_html($sin_reco), 'Recomendación'), 'tarjeta: sin recomendación no hay recomendación');
$tp = at_rt_tarjeta_html(array_replace($tarjeta, ['tipo' => 'propuesta', 'url_panel' => 'https://example.com/panel']));
ok(str_contains($tp, 'Necesita propuesta nueva') && !str_contains($tp, 'Aprobar') && !str_contains($tp, 'Pedir cambios') && !str_contains($tp, 'Esta semana no') && !str_contains($tp, 'retomar-aprobar.php') && !str_contains($tp, 'Conversamos del pedido') && str_contains($tp, 'href="https://example.com/panel"'), 'tarjeta propuesta: aviso, sin botones ni enlaces de aprobación, con enlace al panel');
$tpre = at_rt_tarjeta_html(array_replace($tarjeta, ['tipo' => 'preparacion']));
ok(str_contains($tpre, 'En preparación') && !str_contains($tpre, 'Aprobar') && !str_contains($tpre, 'retomar-aprobar.php'), 'tarjeta preparación: sin botones');
$trev = at_rt_tarjeta_html(array_replace($tarjeta, ['tipo' => 'revision']));
ok(str_contains($trev, 'Necesita revisión') && !str_contains($trev, 'Aprobar') && !str_contains($trev, 'retomar-aprobar.php'), 'tarjeta revisión: sin botones');
ok(str_contains($trev, 'la redacción o la presentación') && str_contains($trev, 'correo del prospecto') && !str_contains($trev, 'Conversamos del pedido'), 'tarjeta revisión: texto fijo que cubre redacción, presentación y correo, sin los textos');
$tdesc = at_rt_tarjeta_html(array_replace($tarjeta, ['tipo' => 'aprobado']));
ok(str_contains($tdesc, 'Necesita revisión') && !str_contains($tdesc, 'Aprobar') && !str_contains($tdesc, 'retomar-aprobar.php'), 'tarjeta: un tipo desconocido nunca es aprobable');
$th = at_rt_tarjeta_html(array_replace($tarjeta, ['nombre' => '<script>x</script>', 'negocio' => '<script>x</script>', 'textos' => ['quedamos' => '<script>x</script>', 'proponemos' => '<b onclick="x">', 'partir' => '"><img src=x>', 'recomendacion' => '<script>x</script>']]));
ok(!str_contains($th, '<script') && !str_contains($th, '<img') && !str_contains($th, '<b onclick') && str_contains($th, '&lt;script&gt;x&lt;/script&gt;'), 'tarjeta: todo texto sale escapado');
$tmal = at_rt_tarjeta_html(array_replace($tarjeta, ['url_presentacion' => 'javascript:alert(1)', 'enlaces' => ['aprobar' => 'javascript:alert(1)', 'cambios' => 'https://demo.easypanel.host/x', 'omitir' => "https://example.com/\" onclick=\"x", 'ver' => '//example.com/ver']]));
ok(!str_contains($tmal, 'javascript:') && !str_contains($tmal, 'easypanel') && !str_contains($tmal, 'onclick="x') && !str_contains($tmal, 'href="//') && !str_contains($tmal, 'Aprobar y enviar'), 'tarjeta: URLs hostiles o internas no se dibujan');
$thttp = at_rt_tarjeta_html(array_replace($tarjeta, ['url_presentacion' => 'http://example.com/retomar.php?t=abc', 'enlaces' => ['aprobar' => 'http://example.com/retomar-aprobar.php?a=aprobar&t=1', 'omitir' => 'https://example.com/x%0d%0aBcc:a@example.com'] + $enlaces]));
ok(!str_contains($thttp, 'http://') && !str_contains($thttp, '%0d%0a') && !str_contains($thttp, 'Aprobar y enviar') && !str_contains($thttp, 'Esta semana no') && !str_contains($thttp, 'Ver la presentación completa') && str_contains($thttp, 'Pedir cambios') && str_contains($thttp, 'Ver el correo tal como le llega'), 'tarjeta: un enlace http:// o con %0d%0a se omite (solo HTTPS) y los demás siguen');
ok(!str_contains(at_rt_resumen_html([$tarjeta], [], 'http://example.com/panel'), 'Abrir el panel Retomar') && !str_contains(at_rt_tarjeta_html(array_replace($tarjeta, ['tipo' => 'revision', 'url_panel' => 'http://example.com/panel'])), 'Abrir en el panel'), 'panel: un enlace http:// se omite');
ok(!str_contains(strtolower(at_rt_tarjeta_html(array_replace($tarjeta, ['nombre' => 'easypanel interno', 'textos' => ['quedamos' => 'easypanel', 'proponemos' => 'x', 'partir' => 'y']]))), 'easypanel'), 'tarjeta: D8 también en los textos visibles');
$salieron = [['nombre' => '[PRUEBA] Beto', 'negocio' => '[PRUEBA] Taller', 'motivo' => 'agendo'], ['nombre' => '<script>x</script>', 'negocio' => '', 'motivo' => 'agotado'], ['nombre' => 'Cata', 'negocio' => 'Café', 'motivo' => 'baja'], ['nombre' => 'Dani', 'negocio' => '', 'motivo' => 'Motivo libre']];
$r = at_rt_resumen_html([$tarjeta, array_replace($tarjeta, ['tipo' => 'propuesta', 'nombre' => '[PRUEBA] Eva']), array_replace($tarjeta, ['tipo' => 'preparacion', 'nombre' => '[PRUEBA] Fer'])], $salieron, 'https://example.com/wp-admin/admin.php?page=at-retomar&x=1');
foreach (['Nada sale sin tu aprobación.', '✅ Aprobar y enviar', 'Necesita propuesta nueva', 'En preparación', 'Salieron de Retomar', '[PRUEBA] Beto', '[PRUEBA] Taller', 'agendó', 'Abrir el panel Retomar', '10:00 a 13:00', '3 prospectos'] as $esperado) { ok(str_contains($r, $esperado), 'resumen contiene «' . $esperado . '»'); }
ok(str_contains($r, 'href="https://example.com/wp-admin/admin.php?page=at-retomar&amp;x=1"') && !str_contains($r, '<img') && !str_contains($r, '<script') && str_contains($r, '&lt;script&gt;x&lt;/script&gt;'), 'resumen: panel enlazado, sin <img y con escape');
ok(str_contains($r, 'Motivo libre') && str_contains($r, 'pidió no recibir más') && !str_contains($r, '>agotado<') && !str_contains($r, '>baja<'), 'resumen: los motivos conocidos se traducen y el libre se respeta');
ok(str_contains(at_rt_resumen_html([$tarjeta], [], 'https://example.com/panel'), '1 prospecto ') && !str_contains(at_rt_resumen_html([$tarjeta], [], 'https://example.com/panel'), 'Salieron de Retomar'), 'resumen: singular y sin lista de salidas');
ok(str_contains(at_rt_resumen_html([], $salieron, 'https://example.com/panel'), 'Salieron de Retomar') && !str_contains(at_rt_resumen_html([], $salieron, 'https://example.com/panel'), 'Aprobar y enviar'), 'resumen: solo salidas');
ok(str_contains(at_rt_resumen_html([at_rt_tarjeta_html($tarjeta)], [], 'https://example.com/panel'), '✅ Aprobar y enviar'), 'resumen: acepta tarjetas ya dibujadas');
$rm = at_rt_resumen_html([array_replace($tarjeta, ['url_presentacion' => 'https://demo.easypanel.host/x'])], [], 'javascript:alert(1)');
ok(!str_contains($rm, 'easypanel') && !str_contains($rm, 'javascript:') && !str_contains($rm, 'href="javascript'), 'resumen: enlaces hostiles no se dibujan');
echo "$total pruebas, $fallas fallas\n";
exit($fallas ? 1 : 0);
