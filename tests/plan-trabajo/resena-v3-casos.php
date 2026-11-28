<?php
/** Vuelta 3: secuencias e intercalados reproducibles con datos sintéticos. */
fixture(701); at_rg_reclamar(701, ['manual'=>true]);
$int701=at_rg_datos(at_rg_fila(701))['intentos'][0];
$w701='wamid.temprano701';
at_cc_rest_estado_whatsapp(request(['estados'=>[['wamid'=>$w701,'estado'=>'failed','codigo'=>131049]]]));
$avisos=count($GLOBALS['mails']);
$registrado=at_rg_anotar_resultado(701,$int701['id'],'real','aceptado',$w701,'');
ok($registrado && at_rg_datos(at_rg_fila(701))['intentos'][0]['whatsapp']['estado']==='fallido'
    && count($GLOBALS['mails'])===$avisos+1 && get_transient(at_cc_clave_fallo_pendiente($w701))===false,
    'V3-1 failed antes de resultado queda fallido y avisa una vez');
$avisos=count($GLOBALS['mails']);
at_rg_anotar_resultado(701,$int701['id'],'real','aceptado',$w701,'');
at_cc_rest_estado_whatsapp(request(['estados'=>[['wamid'=>$w701,'estado'=>'failed','codigo'=>131049]]]));
ok(count($GLOBALS['mails'])===$avisos,'V3-1 repetidos no duplican aviso');

fixture(702); at_rg_reclamar(702, ['manual'=>true]);
$int702=at_rg_datos(at_rg_fila(702))['intentos'][0]; $w702='wamid.ventana702';
// El POST termina entre el lookup negativo del webhook y su set_transient.
$GLOBALS['onTransient']=function() use($int702,$w702){at_rg_anotar_resultado(702,$int702['id'],'real','aceptado',$w702,'');};
$avisos=count($GLOBALS['mails']);
at_cc_rest_estado_whatsapp(request(['estados'=>[['wamid'=>$w702,'estado'=>'failed','codigo'=>131049]]]));
ok(at_rg_datos(at_rg_fila(702))['intentos'][0]['whatsapp']['estado']==='fallido'
    && count($GLOBALS['mails'])===$avisos+1 && get_transient(at_cc_clave_fallo_pendiente($w702))===false,
    'V3-1 reconsulta cierra lookup anterior al indice y transient posterior');

foreach (['+5691234567','+569123456789','+560912345678','912345678'] as $tel) {
    ok(at_rg_telefono($tel)==='', 'V3-2 rechaza movil chileno mal formado o nacional sin pais '.$tel);
}
ok(at_rg_telefono('56912345678')==='+56912345678' && at_rg_telefono('+51 912 345 678')==='+51912345678',
    'V3-2 conserva prefijo de pais explicito');
fixture(703); $wpdb->clientes[703]['telefono']='912345678';
ob_start(); at_rg_render_panel(703); $html=ob_get_clean();
ok(str_contains($html,'CRM no registra país') && str_contains($html,'WhatsApp no disponible'), 'V3-2 panel explica ausencia de pais');
$wpdb->clientes[703]['telefono']='56 9 1234 5678';
ob_start(); at_rg_render_panel(703); $html=ob_get_clean();
ok(str_contains($html,'WhatsApp: +56912345678'), 'V3-2 panel muestra destino normalizado');
$wpdb->clientes[703]['email']=''; $wpdb->clientes[703]['telefono']='912345678';
at_rg_cambiar(703, function($d){$d['permiso']['telefono']='+56912345678'; return $d;});
ok(is_wp_error(at_rg_reclamar(703,['manual'=>true])), 'V3-2 sin pais no reclama WhatsApp nacional');

fixture(704); at_rg_reclamar(704,['manual'=>true]);
$int704=at_rg_datos(at_rg_fila(704))['intentos'][0];
$respuesta=at_cc_rest_envio_recordatorio(request(['tipo'=>'resena','id'=>704,'intento_id'=>$int704['id'],'modo'=>'real','wamid'=>'wamid.recordatorio704']));
ok($respuesta===['ok'=>true] && at_rg_datos(at_rg_fila(704))['intentos'][0]['whatsapp']['wamid']==='wamid.recordatorio704',
    'V3-3 whatsapp-envio resena usa id de proyecto en URL');

$schemaAntes=$GLOBALS['opts']['at_resena_schema'];
$GLOBALS['opts']['at_resena_schema']='1'; $GLOBALS['admin']=false;
$queriesAntes=count($wpdb->queries); $opcionesAntes=$GLOBALS['opts']; $transAntes=$GLOBALS['trans'];
try { $auto=at_rg_migrar(); } catch(Throwable $e) { $auto='ejecuto-migracion'; }
ok($auto===false && count($wpdb->queries)===$queriesAntes && $GLOBALS['opts']===$opcionesAntes && $GLOBALS['trans']===$transAntes,
    'V3-4 migracion automatica anonima sin efectos SQL opciones ni transients');
$GLOBALS['admin']=true; $GLOBALS['opts']['at_resena_schema']=$schemaAntes;

fixture(705); $d=at_rg_datos(at_rg_fila(705));
$i1=at_rg_intento('primero-705','real','panel',false,['whatsapp'=>true],at_rg_ahora());
$i2=at_rg_intento('segundo-705','real','panel',true,['whatsapp'=>true],at_rg_ahora());
$d['intentos']=[$i1,$i2]; at_rg_cas(at_rg_fila(705),$d);
at_rg_anotar_resultado(705,$i1['id'],'real','aceptado','wamid.unico705','');
$antes=at_rg_fila(705); $avisos=count($GLOBALS['mails']); $hist=count($GLOBALS['history']);
$rechazado=at_rg_anotar_resultado(705,$i2['id'],'real','aceptado','wamid.unico705','');
ok(!$rechazado && at_rg_fila(705)->datos===$antes->datos && at_rg_fila(705)->revision===$antes->revision
    && count($GLOBALS['mails'])===$avisos && count($GLOBALS['history'])===$hist,
    'V3-5 wamid de otro intento rechaza sin escribir JSON revision ni efectos');
fixture(706); at_rg_reclamar(706,['manual'=>true]); $i706=at_rg_datos(at_rg_fila(706))['intentos'][0];
$antes=at_rg_fila(706);
ok(!at_rg_anotar_resultado(706,$i706['id'],'real','aceptado','wamid.unico705','') && at_rg_fila(706)->datos===$antes->datos,
    'V3-5 wamid de otro proyecto tampoco contamina JSON');

$wpdb->queries=[]; at_rg_pendientes();
ok(str_contains(implode("\n",array_column($wpdb->queries,0)), 'JSON_CONTAINS'),
    'V3-7 pendientes excluye en SQL intentos reales que consumen cupo');

$guia=file_get_contents(dirname(__DIR__,2).'/Docs/METODO_AT/ESTADO-DEL-PROYECTO.md');
ok(str_contains($guia,'### Vuelta a v1') && str_contains($guia,'500/30') && str_contains($guia,'delete_transient("at_resena_migrar_intento")'),
    'V3-8 documenta rollback v1 holgura y bloqueo');
$informe=file_get_contents(dirname(__DIR__,2).'/Docs/superpowers/plans/2026-11-19-resena-google-cierre-implementacion-A.md');
ok(str_contains($informe,'## Vuelta 3'), 'V3-8 informe A contiene Vuelta 3');
