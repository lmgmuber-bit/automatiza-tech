<?php
/** V5: CCO explícita; sin red, SMTP ni destinatarios reales. */
$clienteV5='cliente@ejemplo.cl'; $copiaV5='luis-fixture@ejemplo.cl';
foreach ([
    'opcion ausente'=>null, 'opcion vacia'=>'', 'opcion valida'=>$copiaV5,
    'igual al destino'=>$clienteV5, 'igual normalizada'=>'CLIENTE@ejemplo.cl',
    'invalida'=>'sin-arroba', 'CRLF'=>"copia@ejemplo.cl\r\nBcc: otro@ejemplo.cl",
    'CRLF final'=>"copia@ejemplo.cl\r\n", 'coma'=>'uno@ejemplo.cl,dos@ejemplo.cl',
    'tipo inesperado'=>['copia@ejemplo.cl'],
] as $caso=>$valor) {
    unset($GLOBALS['opts']['at_resena_copia_oculta']);
    if ($valor!==null) $GLOBALS['opts']['at_resena_copia_oculta']=$valor;
    $enviado=at_rg_mail_cerrado($clienteV5,'Invitación sintética','<p>Contenido sintético</p>');
    $mail=end($GLOBALS['mails']);
    $bcc=[]; // V7: el transporte cerrado nunca agrega BCC.
    ok($enviado && $mail['mailer']->bcc===$bcc, 'V5 '.$caso.' V7 conserva envio sin BCC');
    ok($mail['to']===$clienteV5 && $mail['mailer']->to===[$clienteV5] && !$mail['mailer']->cc
        && !preg_match('/^(?:To|Cc|Bcc):/mi',implode("\n",$mail['headers']))
        && !str_contains(implode("\n",$mail['headers']).$mail['body'],$copiaV5),
        'V5 '.$caso.' To solo cliente sin copia visible');
}
$GLOBALS['opts']['at_resena_copia_oculta']=$copiaV5;
$inyectarArgsV5=static function($args){$args['to']='intruso@ejemplo.cl';$args['headers'][]='Cc: intruso@ejemplo.cl';$args['headers'][]='Bcc: intruso@ejemplo.cl';return $args;};
$inyectarMailerV5=static function($m){$m->addAddress('intruso@ejemplo.cl');$m->addCC('intruso@ejemplo.cl');$m->addBCC('intruso@ejemplo.cl');};
add_filter('wp_mail',$inyectarArgsV5); add_action('phpmailer_init',$inyectarMailerV5);
at_rg_mail_cerrado($clienteV5,'Invitación sintética','<p>Contenido sintético</p>');$mail=end($GLOBALS['mails']);
ok($mail['to']===$clienteV5 && $mail['mailer']->to===[$clienteV5] && !$mail['mailer']->cc
    && !$mail['mailer']->bcc && !str_contains(implode("\n",$mail['headers']),'intruso@'),
    'V5 filtros no agregan To CC ni BCC adicionales');
remove_filter('wp_mail',$inyectarArgsV5); remove_action('phpmailer_init',$inyectarMailerV5);
foreach (['false','throw'] as $falloV5) {
    $GLOBALS['bcc_fail']=$falloV5;
    try {$enviado=at_rg_mail_cerrado($clienteV5,'Invitación sintética','<p>Contenido sintético</p>');}
    catch(Throwable $e){$enviado=false;}
    $mail=end($GLOBALS['mails']);
    ok($enviado && $mail['mailer']->to===[$clienteV5] && !$mail['mailer']->cc && !$mail['mailer']->bcc,
        'V5 fallo addBCC '.$falloV5.' no bloquea cliente');
}
unset($GLOBALS['bcc_fail']);

// Camino real de invitación, prueba y reenvío: no solo la función de correo aislada.
foreach (['invitacion','prueba','reenvio'] as $kV5=>$modoV5) {
    $idV5=901+$kV5; fixture($idV5); $wpdb->clientes[$idV5]['email']=$clienteV5;
    $GLOBALS['opts']['at_resena_prueba_email']='prueba@ejemplo.cl';
    $GLOBALS['opts']['at_resena_prueba_telefono']='+56900000002';
    if($modoV5==='reenvio')at_rg_cambiar($idV5,static function($d) use($clienteV5){
        $i=at_rg_intento('v5-primero','real','panel',false,['correo'=>true,'whatsapp'=>false],at_rg_ahora()->modify('-8 days'));
        $i['correo']['estado']='aceptado';$i['destinos']=['correo'=>$clienteV5,'whatsapp'=>''];$d['intentos'][]=$i;return $d;
    });
    $antesV5=count($GLOBALS['mails']);
    $r=at_rg_reclamar($idV5,['manual'=>true,'prueba'=>$modoV5==='prueba','reenvio'=>$modoV5==='reenvio','motivo'=>'Rebote confirmado por SMTP']);
    $mail=$GLOBALS['mails'][$antesV5];$destinoV5=$modoV5==='prueba'?'prueba@ejemplo.cl':$clienteV5;
    $intentosV5=at_rg_datos(at_rg_fila($idV5))['intentos'];$i=end($intentosV5);
    ok(!is_wp_error($r) && count($GLOBALS['mails'])===$antesV5+2 && !$mail['mailer']->bcc
        && $mail['mailer']->to===[$destinoV5] && !$mail['mailer']->cc
        && $i['modo']===($modoV5==='prueba'?'prueba':'real') && $i['tipo']===($modoV5==='reenvio'?'reenvio':'invitacion'),
        'V5 '.$modoV5.' V7 aviso separado y cliente al destino correcto');
}
fixture(904);$wpdb->clientes[904]['email']=$clienteV5;
foreach (['false','throw'] as $falloV5) {
    $GLOBALS['bcc_fail']=$falloV5;
    $r=at_rg_reclamar(904,['manual'=>true,'prueba'=>true]);$intentosV5=at_rg_datos(at_rg_fila(904))['intentos'];$i=end($intentosV5);
    ok(!is_wp_error($r) && $i['correo']['estado']==='aceptado','V5 fallo CCO '.$falloV5.' mantiene estado aceptado');
}
unset($GLOBALS['bcc_fail']);

foreach (['activa'=>$copiaV5,'inactiva'=>'','invalida'=>"copia@ejemplo.cl\r\n"] as $estadoV5=>$valorV5) {
    $GLOBALS['opts']['at_resena_copia_oculta']=$valorV5;
    ob_start();at_rg_render_panel(904);$panelV5=ob_get_clean();
    ok(str_contains($panelV5,$estadoV5==='activa'?'Aviso interno activo: lu***@ejemplo.cl':'Aviso interno inactivo')
        && !str_contains($panelV5,$copiaV5) && !str_contains($panelV5,'name="at_resena_copia_oculta"'),
        'V5 panel '.$estadoV5.' enmascara y no permite editar');
}
unset($GLOBALS['opts']['at_resena_copia_oculta']);
