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
$funciones = ['intervalo', 'max', 'es_habil', 'en_ventana', 'proximo', 'canales', 'motivo_parada', 'estado_por_motivo', 'token', 'token_valido', 'asunto', 'correo_html', 'correo_texto', 'cabeceras_baja', 'internas'];
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
foreach ([1, 2, 3, 4] as $n) { ok(at_rt_canales($n, 4, 'correo,whatsapp', true, '56900000000') === (($n === 1 || $n === 4) ? ['correo', 'whatsapp'] : ['correo']), 'canales envío ' . $n); }
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
ok(str_contains($html, 'Ver la presentación') && str_contains($html, 'Agendar una reunión') && str_contains($html, 'No me interesa, no volver a escribirme') && str_contains($texto, 'te saluda el equipo de AutomatizaTech'), 'CTA y voz equipo');
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
echo "$total pruebas, $fallas fallas\n";
exit($fallas ? 1 : 0);
