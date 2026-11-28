<?php
// Sin WordPress, credenciales ni red.
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';
$fallas = 0; $n = 0;
function ok($v, $m) { global $fallas, $n; $n++; if (!$v) { $fallas++; echo "FAIL $m\n"; } }
$cliente = ['tipo' => 'cliente', 'email' => 'persona@ejemplo.cl', 'telefono' => '+56900000000'];
$d = at_rg_vacio(); $d['termino'] = ['fecha' => '2026-11-19'];
$ahora = new DateTimeImmutable('2026-11-26 00:00:00', new DateTimeZone('America/Santiago'));
$ctx = ['url' => 'https://g.page/r/ejemplo/review'];
ok(at_rg_elegibilidad($d, $cliente, $ctx, $ahora) === '', '7 días exactos');
ok(at_rg_elegibilidad($d, $cliente, $ctx, $ahora->modify('-1 second')) === 'plazo', 'antes de 7 días');
$dst = $d; $dst['termino']['fecha'] = '2026-09-01';
ok(at_rg_fecha_envio('2026-09-01') === '2026-09-08', '7 días calendario con DST');
ok(at_rg_elegibilidad($dst, $cliente, $ctx, $ahora) === 'historico', 'corte histórico efectivo');
ok(at_rg_elegibilidad($dst, $cliente, $ctx + ['manual' => true], $ahora) === '', 'manual salta corte y plazo');
foreach (['', '2026-02-30', '19-11-2026', '2026-11-19x'] as $f) {
    $x = $d; $x['termino']['fecha'] = $f;
    ok(at_rg_elegibilidad($x, $cliente, $ctx, $ahora) === 'termino', 'fecha inválida ' . $f);
}
ok(at_rg_elegibilidad($d, array_merge($cliente, ['tipo' => 'lead']), $ctx + ['manual' => true], $ahora) === 'cliente', 'manual no salta tipo');
ok(at_rg_elegibilidad($d, $cliente, ['url' => ''], $ahora) === 'enlace', 'sin enlace bloqueado');
foreach (['javascript:alert(1)', 'https://127.0.0.1/a', 'https://ejemplo.cl/a', 'https://g.page@ejemplo.cl/a', 'https://www.google.com/url?q=https://ejemplo.cl', 'https://search.google.com/otra-ruta'] as $u) {
    ok(at_rg_url_google($u) === '', 'rechazo de destino ' . $u);
}
ok(at_rg_email(' PERSONA@EJEMPLO.CL ') === 'persona@ejemplo.cl', 'correo normalizado');
ok(at_rg_email("a@ejemplo.cl\r\nBcc:b@ejemplo.cl") === '', 'cabecera en correo rechazada');
ok(at_rg_telefono('+56 9 0000 0000') === '+56900000000', 'E164 normalizado');
ok(at_rg_telefono('800000000') === '' && at_rg_telefono('+000000000') === '', 'teléfono inválido');
ok(at_rg_canales($cliente, []) === ['correo' => true, 'whatsapp' => false], 'sin autorización solo correo');
$permiso = ['autorizado' => true, 'origen' => 'Autorización explícita en formulario', 'fecha' => '2026-11-19', 'telefono' => '+56900000000'];
ok(at_rg_canales($cliente, ['permiso' => $permiso])['whatsapp'], 'permiso con origen fecha y teléfono');
$incompleto = $permiso; unset($incompleto['origen']);
ok(!at_rg_canales($cliente, ['permiso' => $incompleto])['whatsapp'], 'conversación sin evidencia no basta');
ok(!at_rg_canales(array_merge($cliente, ['telefono' => '+56900000001']), ['permiso' => $permiso])['whatsapp'], 'permiso no sigue a otro teléfono');
foreach (['correo', 'whatsapp', 'todos'] as $canal) {
    foreach (['resenas', 'todo'] as $alcance) {
        $baja = ['email' => 'persona@ejemplo.cl', 'telefono' => '+56900000000', 'canal' => $canal, 'alcance' => $alcance];
        $c = at_rg_canales($cliente, ['permiso' => $permiso, 'bajas' => [$baja]]);
        ok($c['correo'] === ($canal === 'whatsapp') && $c['whatsapp'] === ($canal === 'correo'), "baja $canal $alcance");
    }
}
$x = $d; $x['excluido'] = true;
ok(at_rg_elegibilidad($x, $cliente, $ctx + ['manual' => true], $ahora) === 'excluido', 'manual respeta exclusión');
$ctxWa = $ctx + ['permiso' => $permiso];
$i = at_rg_intento('uno', 'real', 'panel', false, at_rg_canales($cliente, $ctxWa), $ahora->modify('-7 days'));
$x = $d; $x['intentos'][] = $i;
ok(at_rg_elegibilidad($x, $cliente, $ctx + ['manual' => true, 'reenvio' => true, 'motivo' => 'Rebote confirmado por SMTP'], $ahora) === 'bloqueado', 'en curso impide reenvío');
$x = at_rg_vencer($x, $ahora);
ok($x['intentos'][0]['correo']['estado'] === 'desconocido', 'caída se vuelve desconocido');
ok(at_rg_estado($x['intentos'][0]) === 'En revisión (resultado desconocido)', 'incertidumbre visible');
$i['correo']['estado'] = 'aceptado'; $i['whatsapp']['estado'] = 'fallido'; $x['intentos'] = [$i];
ok(at_rg_estado($i) === 'Parcial', 'parcial');
ok(at_rg_elegibilidad($x, $cliente, $ctx, $ahora) === '', 'V7 reloj habilita unico recordatorio a los 7 dias');
ok(at_rg_elegibilidad($x, $cliente, $ctx + ['manual' => true, 'reenvio' => true, 'motivo' => 'Rebote confirmado por SMTP'], $ahora) === '', 'único reenvío tras 7 días');
ok(at_rg_elegibilidad($x, $cliente, $ctx + ['manual' => true, 'reenvio' => true, 'motivo' => 'Rebote confirmado por SMTP'], $ahora->modify('-1 second')) === 'plazo_reenvio', 'reenvío antes de 7 días');
$x['intentos'][] = array_merge($i, ['id' => 'dos', 'tipo' => 'reenvio']);
ok(at_rg_elegibilidad($x, $cliente, $ctx + ['manual' => true, 'reenvio' => true, 'motivo' => 'Rebote confirmado por SMTP'], $ahora) === 'cupo', 'tercero bloqueado');
$p = $d; $p['intentos'][] = at_rg_intento('prueba', 'prueba', 'panel', false, ['correo' => true, 'whatsapp' => true], $ahora);
ok(at_rg_elegibilidad($p, $cliente, $ctx, $ahora) === '', 'prueba no consume real');
$p['intentos'][0]['whatsapp']['estado'] = 'aceptado';
$nuevo = at_rg_resultado($p, 'prueba', 'real', 'entregado', 'wamid.ejemplo', '');
ok($nuevo === null, 'modo de devolución obligatorio');
$x['intentos'][0]['whatsapp']['estado'] = 'aceptado';
$nuevo = at_rg_resultado($x, 'uno', 'real', 'entregado', 'wamid.ejemplo', '');
ok($nuevo['intentos'][0]['whatsapp']['estado'] === 'entregado' && $nuevo['intentos'][1] === $x['intentos'][1], 'devolución tardía del primero');
ok(at_rg_resultado($nuevo, 'uno', 'real', 'entregado', 'wamid.ejemplo', '') === $nuevo, 'resultado repetido no cambia');
ok(at_rg_resultado($nuevo, 'uno', 'real', 'aceptado', 'wamid.ejemplo', '') === $nuevo, 'estado atrasado no retrocede');
ok(at_rg_resultado($nuevo, 'uno', 'real', 'aceptado', 'wamid.otro', '') === null, 'no se sustituye wamid');
$sin = at_rg_intento('sin', 'real', 'panel', false, ['correo' => true, 'whatsapp' => false], $ahora);
ok($sin['whatsapp']['estado'] === 'no_intentado', 'canal ausente');
$sin['correo']['estado'] = 'fallido'; ok(at_rg_estado($sin) === 'Fallida', 'ningún canal aceptado');
ok(at_av_normalizar(['termino' => ['fecha' => '2026-11-19']])['termino'] === ['fecha' => '2026-11-19'], 'normalizador conserva termino');
// M4: un fracaso total no es una invitación recibida ni consume D10.
$fallido = at_rg_intento('m4-fallo', 'real', 'panel', false, ['correo'=>true,'whatsapp'=>true], $ahora);
$fallido['correo']['estado'] = $fallido['whatsapp']['estado'] = 'fallido';
ok(!at_rg_consume_cupo($fallido), 'M4 todo fallido no consume');
$x = $d; $x['intentos'] = [$fallido];
ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true],$ahora)==='', 'M4 Enviar ahora recupera invitacion sin esperar');
ok(at_rg_elegibilidad($x,$cliente,$ctx,$ahora)==='', 'M4 automatico recupera elegible en siguiente corrida');
ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP'],$ahora)==='sin_invitacion', 'M4 fallo no habilita reenvio sin invitacion');
$fallido['whatsapp']['estado'] = 'no_intentado';
ok(!at_rg_consume_cupo($fallido), 'M4 fallido + no_intentado no consume');
$x['intentos'] = [$fallido];
ok(at_rg_elegibilidad($x,$cliente,$ctx,$ahora)==='', 'M4 canal ausente permite nuevo intento automatico');
foreach (['desconocido','en_curso','aceptado','entregado','leido'] as $estado) {
    $mixto=$fallido; $mixto['whatsapp']['estado']=$estado; $x['intentos']=[$mixto];
    ok(at_rg_consume_cupo($mixto), 'M4 fallido + '.$estado.' consume');
    ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true],$ahora)===
        (in_array($estado,['desconocido','en_curso'],true)?'bloqueado':'intentado'), 'M4 fallido + '.$estado.' bloquea invitacion');
}
$x['intentos'] = array_fill(0,2,$fallido);
ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true],$ahora)==='', 'M4 dos fallos permiten tercer intento');
$x['intentos'][]=$fallido;
ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true],$ahora)==='limite_fallidos'
    && at_rg_elegibilidad($x,$cliente,$ctx,$ahora)==='limite_fallidos', 'M4 tercer fallido total bloquea manual y automatico');
$pruebaFallida=$fallido; $pruebaFallida['modo']='prueba'; $x['intentos']=array_fill(0,3,$pruebaFallida);
ok(at_rg_elegibilidad($x,$cliente,$ctx,$ahora)==='', 'M4 pruebas fallidas no cuentan al tope');
$aceptado=$fallido; $aceptado['id']='m4-aceptado'; $aceptado['correo']['estado']='aceptado';
$fallido['creado_at']=$ahora->modify('-8 days')->format(DATE_ATOM);
$x['intentos']=[$fallido,$aceptado];
ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP'],$ahora->modify('+7 days'))==='', 'M4 reenvio intacto tras fallido + aceptado a los 7 dias');
ok(at_rg_elegibilidad($x,$cliente,$ctx+['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP'],$ahora->modify('+7 days -1 second'))==='plazo_reenvio', 'M4 siete dias parten del aceptado no del fallido');
echo "$n comprobaciones, $fallas fallas\n"; exit($fallas ? 1 : 0);
