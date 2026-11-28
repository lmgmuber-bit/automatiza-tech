<?php
/** M4, decisión de Luis: únicamente fixtures sintéticas y proveedores dobles. */
fixture(801); $GLOBALS['mail_ok']=false;
$r=at_rg_reclamar(801,['manual'=>true]);
$i=at_rg_datos(at_rg_fila(801))['intentos'][0];
at_rg_anotar_resultado(801,$i['id'],'real','fallido','','Rechazo sintético');
$GLOBALS['mail_ok']=true;
ob_start(); at_rg_render_panel(801); $html=ob_get_clean();
ok(preg_match('/value="enviar"><button class="button">/',$html)
    && preg_match('/value="reenviar">.*?<button[^>]*disabled/s',$html), 'M4 panel permite invitacion inmediata sin ofrecer reenvio');
$r=at_rg_reclamar(801,['manual'=>true]);
$intentos=at_rg_datos(at_rg_fila(801))['intentos'];
ok(!is_wp_error($r) && count($intentos)===2 && $intentos[1]['tipo']==='invitacion',
    'M4 reclamo tras todo fallido es invitacion inmediata');
ob_start(); at_rg_render_panel(801); $html=ob_get_clean();
ok(str_contains($html,'no consumen cupo') && str_contains($html,'3 intentos reales totalmente fallidos'),
    'M4 panel explica cupo y tope');

// El tercer fallo es la terminación tardía de WhatsApp: reserva un solo aviso de tope.
fixture(802); $d=at_rg_datos(at_rg_fila(802));
for($k=1;$k<=3;$k++) {
    $i=at_rg_intento('m4-802-'.$k,'real','panel',false,['correo'=>true,'whatsapp'=>true],at_rg_ahora());
    $i['correo']['estado']='fallido'; $i['whatsapp']['estado']=$k===3?'en_curso':'fallido';
    $d['intentos'][]=$i;
}
at_rg_cas(at_rg_fila(802),$d); at_rg_observar(802); $antes=count($GLOBALS['mails']);
at_rg_anotar_resultado(802,'m4-802-3','real','fallido','','Rechazo sintético');
$tope=array_filter(array_slice($GLOBALS['mails'],$antes),fn($m)=>str_contains($m['body'],'3 intentos reales totalmente fallidos'));
$r=at_rg_reclamar(802,['manual'=>true]);
ok(is_wp_error($r) && $r->get_error_code()==='resena_limite_fallidos' && count($tope)===1
    && count($GLOBALS['mails'])===$antes+1, 'M4 tercer fallido total bloquea y avisa una vez');
$antes=count($GLOBALS['mails']);
at_rg_anotar_resultado(802,'m4-802-3','real','fallido','','Repetido'); at_rg_observar(802); at_rg_mantener();
ok(count($GLOBALS['mails'])===$antes, 'M4 callback observador y mantenimiento no duplican aviso de tope');
ok(at_rg_abierto(at_rg_datos(at_rg_fila(802)))===0, 'M4 aviso de tope completo no deja mantenimiento abierto sin trabajo');
ob_start(); at_rg_render_panel(802); $html=ob_get_clean();
ok(str_contains($html,'⛔') && str_contains($html,'3 intentos reales totalmente fallidos')
    && preg_match('/value="enviar"><button[^>]*disabled/',$html)
    && preg_match('/value="reenviar">.*?<button[^>]*disabled/s',$html), 'M4 panel bloquea invitacion y reenvio al tercero');

// Un solo canal disponible fallido también devuelve cupo; las pruebas no suman al tope.
fixture(803); $wpdb->clientes[803]['telefono']=''; $GLOBALS['mail_ok']=false;
for($k=1;$k<=3;$k++) {
    $r=at_rg_reclamar(803,['manual'=>true]);
    ok(!is_wp_error($r), 'M4 fallo + ausente permite intento real '.$k);
}
$GLOBALS['mail_ok']=true; $r=at_rg_reclamar(803,['manual'=>true]);
ok(is_wp_error($r) && $r->get_error_code()==='resena_limite_fallidos', 'M4 cuarto intento real se rechaza sin despacho');
$antes=count($GLOBALS['mails']); at_rg_observar(803); at_rg_mantener();
ok(at_rg_datos(at_rg_fila(803))['historial_escrito']['aviso:limite_fallidos']==='fallido'
    && count($GLOBALS['mails'])===$antes, 'M4 aviso de tope con SMTP fallido conserva reserva sin repetir');
fixture(804); $d=at_rg_datos(at_rg_fila(804));
for($k=1;$k<=3;$k++) {
    $i=at_rg_intento('m4-prueba-'.$k,'prueba','panel',false,['correo'=>true],at_rg_ahora());
    $i['correo']['estado']='fallido'; $d['intentos'][]=$i;
}
at_rg_cas(at_rg_fila(804),$d); $antes=count($GLOBALS['mails']); at_rg_observar(804);
ok(!array_filter(array_slice($GLOBALS['mails'],$antes),fn($m)=>str_contains($m['body'],'3 intentos reales totalmente fallidos'))
    && !is_wp_error(at_rg_reclamar(804,['manual'=>true])), 'M4 tres pruebas fallidas no bloquean ni avisan tope');
