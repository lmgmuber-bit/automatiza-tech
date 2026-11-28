<?php
/** Vuelta 8: ruta /resena/resumen y aviso a n8n tras envío manual. Datos ficticios. */
if(!function_exists('register_rest_route')){function register_rest_route($ns,$ruta,$args){$GLOBALS['rutas'][$ns.$ruta]=$args;return true;}}
if(!function_exists('at_rg_render_panel'))require $base.'plan-trabajo/avance-panel.php';
$GLOBALS['rutas']=[];
foreach($GLOBALS['hooks']['rest_api_init']??[] as $cbs)foreach($cbs as $cb)$cb();
$ruta=$GLOBALS['rutas']['at/v1/resena/resumen']??null;
ok($ruta!==null && ($ruta['permission_callback']??'')==='at_rg_auth' && ($ruta['methods']??'')==='GET' && ($ruta['callback']??'')==='at_rg_rest_resumen','V8 ruta resumen registrada GET con at_rg_auth');

$wpdb->rows=[];$wpdb->clientes=[];
$ahora=at_rg_ahora();
foreach([801,802] as $id){
    fixture($id);
    $i=at_rg_intento('v8-'.$id,'real','panel',false,['correo'=>true],$ahora->modify('-10 minutes'));
    at_rg_cambiar($id,static function($d)use($i){$d['intentos']=[$i];return $d;});
}
$desde=$ahora->modify('-1 hour')->format(DATE_ATOM);
$r=at_rg_rest_resumen(request(['desde'=>$desde]));
ok(is_array($r) && $r['total']===2 && $r['aviso_a']==='operacion@ejemplo.cl' && $r['desde']===$desde,'V8 resumen total y aviso_a');
ok(is_array($r) && count($r['filas'])===2 && isset($r['filas'][0]['correo']['simbolo'],$r['filas'][0]['whatsapp']['simbolo']),'V8 filas con simbolos por canal');
foreach(['','ayer',$ahora->modify('-3 days')->format(DATE_ATOM)] as $malo){
    $e=at_rg_rest_resumen(request(['desde'=>$malo]));
    ok(is_wp_error($e) && $e->get_error_code()==='resena_datos' && ($e->get_error_data()['status']??0)===400,'V8 desde invalido rechazado: '.$malo);
}
$e=at_rg_rest_resumen(request([]));
ok(is_wp_error($e) && $e->get_error_code()==='resena_datos','V8 desde ausente rechazado');
fixture(803);$wpdb->rows[803]->datos='{roto';
$r=at_rg_rest_resumen(request(['desde'=>$desde]));
ok(is_array($r) && $r['total']===2,'V8 fila ilegible se salta y el resto responde');

// Panel: enviar -> avisa a n8n; prueba y rechazo no.
$cuenta=static fn()=>count(array_filter($GLOBALS['remote'],static fn($u)=>str_ends_with($u,'/at-resena-resumen')));
$panel=static function(int $id,string $op){$GLOBALS['admin']=true;$GLOBALS['nonce']=true;$_POST=['proyecto_id'=>(string)$id,'operacion'=>$op];try{at_rg_panel_accion();}catch(ResenaTestRedirect $e){}};
fixture(810);$GLOBALS['remote']=[];$GLOBALS['remote_args']=[];
$panel(810,'enviar');
ok($cuenta()===1,'V8 enviar aceptado avisa a n8n una vez');
$args=array_values(array_filter($GLOBALS['remote_args'],static fn($a)=>str_ends_with($a[0],'/at-resena-resumen')))[0]??null;
$cuerpo=json_decode($args[1]['body']??'{}',true);
ok(($cuerpo['origen']??'')==='manual' && !empty($cuerpo['desde']) && ($args[1]['blocking']??true)===false && ($args[1]['headers']['X-AT-Secret']??'')===AT_REST_SECRET,'V8 cuerpo origen manual, desde, sin bloquear y con secreto');
$GLOBALS['opts']['at_resena_prueba_correo']='prueba@ejemplo.cl';$GLOBALS['opts']['at_resena_prueba_telefono']='+56900000009';
fixture(811);$GLOBALS['remote']=[];$panel(811,'prueba');
ok($cuenta()===0,'V8 prueba no avisa a n8n');
fixture(812);$wpdb->clientes[812]['email']='';$wpdb->clientes[812]['telefono']='';$GLOBALS['remote']=[];$panel(812,'enviar');
ok($cuenta()===0,'V8 enviar rechazado (WP_Error) no avisa a n8n');
fixture(813);$GLOBALS['remote']=[];$panel(813,'enviar');$GLOBALS['remote']=[];$panel(813,'reenviar');
ok($cuenta()===0,'V8 reenviar antes de plazo (WP_Error) no avisa');
