<?php
/** Vuelta 7: fixtures sintéticas y procesos de prueba aislados. */
if(in_array($punto,['A1','todos'],true)) {
    foreach(['oneclick','invalido','prioridad','fallodb'] as $caso) {
        $proceso=proc_open([PHP_BINARY,'-d','xdebug.mode=off',__DIR__.'/resena-v7-publica.php',$caso],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $valido=proc_close($proceso)===0 && str_contains($out,'PASS');
        if($caso==='oneclick' && $valido) {
            preg_match('/RESULT_JSON=(.+)/',$out,$match);$previas=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
            // Segundo POST completo, con la misma query/cuerpo y la baja del primer proceso.
            $proceso=proc_open([PHP_BINARY,'-d','xdebug.mode=off',__DIR__.'/resena-v7-publica.php',$caso,json_encode($previas)],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            $out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            $valido=proc_close($proceso)===0 && str_contains($out,'PASS');
            preg_match('/RESULT_JSON=(.+)/',$out,$match);
            $valido=$valido && json_decode($match[1]??'null',true)===$previas;
        }
        ok($valido,'V7 A1 '.$caso.' ruta publica e idempotencia');
    }
}

if(!function_exists('at_rg_render_panel'))require $base.'plan-trabajo/avance-panel.php';
$v7=['id'=>'00000000-0000-4000-8000-000000000977','nombre'=>'María','proyecto'=>'Proyecto Ejemplo','modo'=>'real','destinos'=>['correo'=>'maria@ejemplo.cl']];
if(in_array($punto,['A2','todos'],true)) {
    $GLOBALS['opts']['at_resena_copia_oculta']='corporativa@ejemplo.cl';
    foreach(['real','prueba','reenvio'] as $modo) {
        $i=$v7;$i['modo']=$modo==='prueba'?'prueba':'real';$i['tipo']=$modo==='reenvio'?'reenvio':'invitacion';
        $antes=count($GLOBALS['mails']);$ok=at_rg_enviar_correo($i,977);$m=array_slice($GLOBALS['mails'],$antes);
        ok($ok && count($m)===2,'V7 A2 '.$modo.' dos mensajes separados');
        $cliente=$m[0];$copia=$m[1]??$m[0];
        ok($cliente['mailer']->to===['maria@ejemplo.cl'] && !$cliente['mailer']->bcc && !$cliente['mailer']->cc,'V7 A2 '.$modo.' cliente sin BCC');
        ok($copia['to']==='corporativa@ejemplo.cl' && $copia['subject']==='[Copia] Invitación a reseña enviada · Proyecto Ejemplo','V7 A2 '.$modo.' copia asunto y destino');
        ok(str_contains($copia['body'],'ma***@ejemplo.cl') && !str_contains($copia['body'],'maria@ejemplo.cl') && str_contains($copia['body'],'[enlace de baja del cliente]')
            && !str_contains($copia['body'],'token=') && !str_contains($copia['mailer']->AltBody,'token=') && !str_contains(implode("\n",$copia['headers']),'List-Unsubscribe')
            && str_contains($copia['body'],'Copia interna; bórrala a los 90 días (política de conservación)'),'V7 A2 '.$modo.' copia inerte enmascarada sin cabeceras');
    }
    $GLOBALS['mail_fail_to']='corporativa@ejemplo.cl';ok(at_rg_enviar_correo($v7,977),'V7 A2 copia rechazada no cambia cliente');unset($GLOBALS['mail_fail_to']);
    $GLOBALS['mail_throw_to']='corporativa@ejemplo.cl';try{$aceptado=at_rg_enviar_correo($v7,977);}catch(Throwable $e){$aceptado=false;}
    ok($aceptado,'V7 A2 excepcion copia no cambia cliente');unset($GLOBALS['mail_throw_to']);
    $GLOBALS['mail_ok']=false;$antes=count($GLOBALS['mails']);at_rg_enviar_correo($v7,977);
    ok(count($GLOBALS['mails'])===$antes+1,'V7 A2 sin copia si cliente falla');unset($GLOBALS['mail_ok']);
    fixture(977);ob_start();at_rg_render_panel(977);$panel=ob_get_clean();
    ok(str_contains($panel,'Aviso interno activo: co***@ejemplo.cl') && !str_contains($panel,'corporativa@ejemplo.cl'),'V7 A2 panel enmascarado');
    $largo=$v7;$largo['proyecto']='Proyecto '.str_repeat('á',100);at_rg_enviar_correo($largo,977);$copia=end($GLOBALS['mails']);
    ok($copia['subject']==='[Copia] Invitación a reseña enviada · '.$largo['proyecto'],'V7 A2 asunto interno conserva proyecto completo mayor de 60 caracteres');
    unset($GLOBALS['opts']['at_resena_copia_oculta']);
}
if(in_array($punto,['A4','todos'],true)) {
    $html=at_rg_correo_html($v7,977);$alt=at_rg_correo_texto($v7,977);
    foreach(['Te escribimos porque trabajamos contigo en Proyecto Ejemplo.','AUTOMATIZATECH SpA','Política de privacidad:','https://automatizatech.cl/privacidad/'] as $frase)
        ok(str_contains($html,$frase) && str_contains($alt,$frase),'V7 A4 HTML y AltBody '.$frase);
    ok(strpos($html,'Te escribimos')<strpos($html,'No recibir más') && str_contains($html,'href="https://automatizatech.cl/privacidad/"'),'V7 A4 informacion sobre baja');
}
if(in_array($punto,['A5','todos'],true)) {
    foreach(['get','resenas','todo'] as $caso) {
        $proc=proc_open([PHP_BINARY,'-d','xdebug.mode=off',__DIR__.'/resena-v7-publica.php',$caso],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);ok(proc_close($proc)===0 && str_contains($out,'PASS'),'V7 A5 pagina '.$caso);
    }
    $wpdb->bajas=[];
    foreach(['¡BÁJA!','stop por favor','Darme de baja.','No me escriban más','No quiero recibir nada'] as $texto) {
        $r=at_rg_rest_baja(request(['telefono'=>'+56900000000','texto'=>$texto]));
        ok(!is_wp_error($r) && count($wpdb->bajas)===1 && $wpdb->bajas[0]['canal']==='whatsapp' && $wpdb->bajas[0]['alcance']==='todo','V7 A5 frase '.$texto);
    }
    $antes=count($GLOBALS['mails']);$nBajas=count($wpdb->bajas);
    foreach(['¿Cómo es lo de la baja?','si digo stop qué pasa','quiero saber sobre baja'] as $texto)
        at_rg_rest_baja(request(['telefono'=>'+56900000001','texto'=>$texto]));
    ok(count($wpdb->bajas)===$nBajas && count($GLOBALS['mails'])===$antes+1,'V7 A5 ambiguos sin baja y un aviso por conversacion dia delta='.(count($GLOBALS['mails'])-$antes).' bajas='.count($wpdb->bajas).'/'.$nBajas);
    at_rg_rest_baja(request(['telefono'=>'+56900000002','texto'=>'Consulta sobre BAJA']));
    ok(count($GLOBALS['mails'])===$antes+2,'V7 A5 otra conversacion otro aviso delta='.(count($GLOBALS['mails'])-$antes));
    $wpdb->bajas=[['email'=>'','telefono'=>'+56900000000','canal'=>'whatsapp','alcance'=>'todo']];
    ok(function_exists('at_rg_baja_total') && at_rg_baja_total('', '56900000000'),'V7 A5 funcion publica baja total normalizada');
    $wpdb->bajas=[['email'=>'maria@ejemplo.cl','telefono'=>'','canal'=>'correo','alcance'=>'resenas']];
    ok(function_exists('at_rg_baja_total') && !at_rg_baja_total('MARIA@ejemplo.cl',''),'V7 A5 resenas no es baja total');
    if(function_exists('at_rg_baja_total')){$wpdb->failBajas=true;ok(at_rg_baja_total('maria@ejemplo.cl',''),'V7 A5 fallo DB bloquea promocion');$wpdb->failBajas=false;}
    $wpdb->bajas=[];
}
if(in_array($punto,['A7','todos'],true)) {
    $ahora=new DateTimeImmutable('2026-12-10T10:00:00-03:00');
    $d=at_rg_vacio();$d['termino']=['fecha'=>'2026-11-19'];
    $c=['tipo'=>'cliente','email'=>'maria@ejemplo.cl','telefono'=>''];$ctx=['url'=>'https://g.page/r/ejemplo/review','bajas'=>[]];
    $i=at_rg_intento('v7-invitacion','real','panel',false,['correo'=>true],$ahora->modify('-7 days'));
    $i['correo']['estado']='aceptado';$i['destinos']=$v7['destinos'];$d['intentos']=[$i];
    ok(at_rg_elegibilidad($d,$c,$ctx,$ahora)==='','V7 A7 automatico exactos 7 dias');
    ok(at_rg_elegibilidad($d,$c,$ctx,$ahora->modify('-1 second'))==='plazo_reenvio','V7 A7 no antes de 7 dias');
    $x=$d;$x['resena_recibida']=['fecha'=>$ahora->format(DATE_ATOM),'por'=>7];
    foreach([[],['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado'],['manual'=>true,'prueba'=>true]] as $op)
        ok(at_rg_elegibilidad($x,$c,$ctx+$op,$ahora)==='resena_recibida','V7 A7 marca bloquea automatico manual prueba');
    $baja=['email'=>'maria@ejemplo.cl','canal'=>'correo','alcance'=>'resenas'];
    ok(at_rg_elegibilidad($d,$c,array_replace($ctx,['bajas'=>[$baja]]),$ahora)==='canales','V7 A7 baja bloquea');
    $x=$d;$j=$i;$j['id']='v7-reenvio';$j['tipo']='reenvio';$j['correo']['estado']='fallido';$x['intentos'][]=$j;
    ok(at_rg_elegibilidad($x,$c,$ctx,$ahora)==='cupo','V7 A7 un reenvio incluso fallido');
    ok(at_rg_elegibilidad($d,$c,$ctx+['manual'=>true,'reenvio'=>true],$ahora)==='motivo_reenvio','V7 A7 manual sin motivo rechazado');
    fixture(978);$wpdb->clientes[978]['telefono']='';$i['creado_at']=at_rg_ahora()->modify('-8 days')->format(DATE_ATOM);
    at_rg_cambiar(978,static function($d)use($i){$d['termino']['fecha']='2026-01-01';$d['intentos']=[$i];return $d;});
    $pend=at_rg_pendientes();ok(in_array(978,array_column($pend['pendientes']??[],'proyecto_id'),true),'V7 A7 pendientes incluye historico con invitacion real');
    $r=at_rg_reclamar(978);$is=at_rg_datos(at_rg_fila(978))['intentos'];
    ok(!is_wp_error($r) && count($is)===2 && $is[1]['tipo']==='reenvio' && $is[1]['origen']==='reloj','V7 A7 reclamar automatico reserva reenvio');
    ok(is_wp_error(at_rg_reclamar(978)),'V7 A7 replay automatico no duplica');
    fixture(979);$wpdb->clientes[979]['telefono']='';
    at_rg_cambiar(979,static function($d)use($i){$d['intentos']=[$i];return $d;});
    $r=at_rg_reclamar(979,['manual'=>true,'reenvio'=>true]);
    ok(is_wp_error($r) && $r->get_error_code()==='resena_motivo_reenvio','V7 A7 reclamar manual exige motivo');
    $r=at_rg_reclamar(979,['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP']);
    $is=at_rg_datos(at_rg_fila(979))['intentos'];
    ok(!is_wp_error($r) && ($is[1]['motivo']??'')==='Rebote confirmado por SMTP','V7 A7 motivo guardado en intento');
    ob_start();at_rg_render_panel(979);$html=ob_get_clean();
    ok(str_contains($html,'✅ Ya dejó su reseña') && str_contains($html,'name="motivo"') && str_contains($html,'expectativa de opinión'),'V7 A7 panel marca motivo y ayuda');
    fixture(982);$GLOBALS['admin']=true;$GLOBALS['nonce']=true;
    $_POST=['proyecto_id'=>'982','operacion'=>'resena_recibida'];
    try{at_rg_panel_accion();}catch(ResenaTestRedirect $e){}
    $marca=at_rg_datos(at_rg_fila(982))['resena_recibida'];
    ok(($marca['por']??0)===7 && !empty($marca['fecha']),'V7 A7 admin-post guarda fecha y autor');
    $antes=count($GLOBALS['mails']);$r=at_rg_reclamar(982,['manual'=>true]);
    ok(is_wp_error($r) && count($GLOBALS['mails'])===$antes,'V7 A7 marca en DB impide envio manual');
    $GLOBALS['admin']=false;$_POST['operacion']='deshacer_resena';
    try{at_rg_panel_accion();}catch(RuntimeException $e){}
    ok(at_rg_datos(at_rg_fila(982))['resena_recibida']===$marca,'V7 A7 sin capability no deshace');
    $GLOBALS['admin']=true;$GLOBALS['nonce']=false;
    try{at_rg_panel_accion();}catch(RuntimeException $e){}
    ok(at_rg_datos(at_rg_fila(982))['resena_recibida']===$marca,'V7 A7 sin nonce no deshace');
    $GLOBALS['nonce']=true;try{at_rg_panel_accion();}catch(ResenaTestRedirect $e){}
    ok(at_rg_datos(at_rg_fila(982))['resena_recibida']===[],'V7 A7 deshacer habilitado con autorizacion');
    fixture(983);$wpdb->conflicto=static function($db,$id){
        $d=json_decode($db->rows[$id]->datos,true);$d['resena_recibida']=['fecha'=>at_rg_ahora()->format(DATE_ATOM),'por'=>7];
        $db->rows[$id]->datos=json_encode($d);$db->rows[$id]->revision++;
    };
    $antes=count($GLOBALS['mails']);$r=at_rg_reclamar(983,['manual'=>true]);
    ok(is_wp_error($r) && count($GLOBALS['mails'])===$antes,'V7 A7 marca rival gana CAS sin envio');
    fixture(984);$r=at_rg_reclamar(984,['manual'=>true]);$int=at_rg_datos(at_rg_fila(984))['intentos'][0];
    at_rg_cambiar(984,static function($d){$d['resena_recibida']=['fecha'=>at_rg_ahora()->format(DATE_ATOM),'por'=>7];return $d;});
    ok(is_wp_error(at_rg_autorizar_wa(984,$int['id'],'real')),'V7 A7 marca antes de despacho impide WhatsApp');
    fixture(985);at_rg_cambiar(985,static function($d)use($i){$d['intentos']=[$i];return $d;});
    $wpdb->conflicto=static function($db,$id){$db->bajas=[['email'=>'persona@ejemplo.cl','telefono'=>'+56900000000','canal'=>'whatsapp','alcance'=>'resenas']];};
    $antes=count($GLOBALS['mails']);at_rg_reclamar(985);
    $enviosCliente=array_filter(array_slice($GLOBALS['mails'],$antes),fn($m)=>$m['to']==='persona@ejemplo.cl');
    ok(!$enviosCliente,'V7 A7 baja WhatsApp entre reserva y correo detiene recordatorio completo');
    $wpdb->bajas=[];fixture(986);at_rg_cambiar(986,static function($d)use($i){$d['intentos']=[$i];return $d;});
    $r=at_rg_reclamar(986);$ints=at_rg_datos(at_rg_fila(986))['intentos'];$ultimo=end($ints);
    $wpdb->bajas=[['email'=>'persona@ejemplo.cl','telefono'=>'','canal'=>'correo','alcance'=>'resenas']];
    ok(is_wp_error(at_rg_autorizar_wa(986,$ultimo['id'],'real')),'V7 A7 baja correo antes de despacho detiene WhatsApp de recordatorio');
    $wpdb->bajas=[];


}
if(in_array($punto,['A6','todos'],true)) {
    $ahora=new DateTimeImmutable('2029-01-10T10:00:00-03:00');$d=at_rg_vacio();
    $i=at_rg_intento('v7-retencion','real','panel',false,['correo'=>true],$ahora->modify('-24 months -1 second'));
    $i+=['destinos'=>['correo'=>'maria@ejemplo.cl','whatsapp'=>'+56900000000'],'nombre'=>'María','proyecto'=>'Proyecto Ejemplo','motivo'=>'Rebote con datos personales'];
    $i['correo']['estado']='aceptado';$i['correo']['detalle']='Detalle sensible';$d['intentos']=[$i];
    ok(function_exists('at_rg_anonimizar'),'V7 A6 funcion de conservacion con reloj');
    if(function_exists('at_rg_anonimizar')) {
        $x=at_rg_anonimizar($d,$ahora,true);ok($x===$d,'V7 A6 relacion vigente conserva');
        $x=at_rg_anonimizar($d,$ahora->modify('-1 second'),false);ok($x===$d,'V7 A6 limite exacto 24 meses conserva');
        $x=at_rg_anonimizar($d,$ahora,false);$json=json_encode($x);
        ok(!str_contains($json,'maria@') && !str_contains($json,'Detalle sensible') && !str_contains($json,'María') && !str_contains($json,'Rebote con datos') && ($x['intentos'][0]['correo']['estado']??'')==='aceptado' && $x['intentos'][0]['creado_at']===$i['creado_at'],'V7 A6 anonimiza conserva estados y fechas');
        ok(at_rg_anonimizar($x,$ahora,false)===$x,'V7 A6 anonimizado idempotente');
        $j=$i;$j['creado_at']=$ahora->modify('-1 month')->format(DATE_ATOM);$d['intentos'][]=$j;
        ok(at_rg_anonimizar($d,$ahora,false)===$d,'V7 A6 plazo desde ultimo intento');
    }
    fixture(980);$wpdb->clientes[980]['tipo']='cerrado';
    $d['intentos']=[$i];at_rg_cas(at_rg_fila(980),$d);$wpdb->wamids['wamid.retencion980']=['resena_id'=>980,'intento_id'=>$i['id'],'modo'=>'real'];
    $wpdb->bajas=[['email'=>'maria@ejemplo.cl','telefono'=>'','canal'=>'correo','alcance'=>'todo','nota'=>'Nota antigua','fecha'=>'2026-11-22','origen'=>'panel']];
    at_rg_mantener($ahora);$guardado=at_rg_datos(at_rg_fila(980));
    ok(isset($guardado['anonimizado_at']) && !str_contains(json_encode($guardado),'maria@') && $wpdb->bajas[0]['nota']==='','V7 A6 cron anonimiza cerrado y vacia notas antiguas');
    ok(!isset($wpdb->wamids['wamid.retencion980']) && !at_rg_anotar_resultado(980,$i['id'],'real','aceptado','wamid.retencion980','Texto sensible'),'V7 A6 limpia indice y rechaza callback tardio');
    fixture(981);at_rg_cas(at_rg_fila(981),$d);at_rg_mantener($ahora);
    ok(!isset(at_rg_datos(at_rg_fila(981))['anonimizado_at']),'V7 A6 cron conserva relacion cliente vigente');
    $wpdb->bajas=[];at_rg_baja_guardar('MARIA@ejemplo.cl','','correo','todo','panel','Nota personal');
    ok(($wpdb->bajas[0]['nota']??null)==='' && $wpdb->bajas[0]['email']==='maria@ejemplo.cl','V7 A6 bajas sin nota desde escritura');
    $wpdb->bajas=[];
}
if(in_array($punto,['A3','todos'],true)) {
    $politica=file_get_contents(__DIR__.'/../../wp-content/themes/automatiza-tech/assets/legal/privacidad.html');
    foreach(['Versión 2026-11-22','Invitar a clientes con un proyecto terminado','una invitación y, como máximo, un recordatorio','Interés legítimo de AutomatizaTech en conocer la opinión','24 meses desde el último intento','sin plazo','90 días'] as $frase)
        ok(str_contains($politica,$frase),'V7 A3 politica '.$frase);
}
