<?php
/** Ruta pública real en proceso aislado; sin HTTP, red ni WordPress real. */
define('AT_RG_FIXTURES_ONLY', true); require __DIR__.'/resena-estado-test.php';
function nocache_headers(){}
function status_header($code){$GLOBALS['status']=$code;}
fixture(970);
$wpdb->bajas=json_decode($argv[2]??'[]',true,512,JSON_THROW_ON_ERROR);
$i=at_rg_intento('00000000-0000-4000-8000-000000000970','real','reloj',false,['correo'=>true],at_rg_ahora());
$i['destinos']=['correo'=>'maria@ejemplo.cl','whatsapp'=>''];
at_rg_cambiar(970,static function($d)use($i){$d['intentos'][]=$i;return $d;});
$token=at_rg_token_baja(970,$i); $caso=$argv[1]??'oneclick';
$_SERVER['REQUEST_URI']='/resena/baja/'; $_SERVER['REQUEST_METHOD']=$caso==='get'?'GET':'POST';
$_GET=['token'=>$token]; $_POST=[];
if($caso==='oneclick')parse_str('List-Unsubscribe=One-Click',$_POST);
if($caso==='todo')$_POST=['token'=>$token,'alcance'=>'todo'];
if($caso==='resenas')$_POST=['token'=>$token,'alcance'=>'resenas'];
if($caso==='invalido')$_GET=['token'=>'invalido'];
if($caso==='prioridad')$_POST=['token'=>'invalido','List-Unsubscribe'=>'One-Click'];
if($caso==='fallodb'){$wpdb->failBajas=true;$_POST=['token'=>$token,'List-Unsubscribe'=>'One-Click'];}
ob_start();
register_shutdown_function(static function()use($caso){
    global $wpdb; $html=ob_get_clean(); $b=$wpdb->bajas;
    if($caso==='get')$ok=($GLOBALS['status']??0)===200 && !$b && str_contains($html,'value="resenas"') && str_contains($html,'value="todo"');
    elseif(in_array($caso,['invalido','prioridad'],true))$ok=($GLOBALS['status']??0)===400 && !$b;
    elseif($caso==='fallodb')$ok=($GLOBALS['status']??0)===503 && !$b && !str_contains($html,'<form');
    else {
        $ok=($GLOBALS['status']??0)===200 && count($b)===1 && $b[0]['email']==='maria@ejemplo.cl' && $b[0]['alcance']===($caso==='todo'?'todo':'resenas') && !str_contains($html,'<form');
        $ok=$ok && count($wpdb->bajas)===1;
    }
    echo ($ok?'PASS':'FAIL')." V7 ruta $caso\nRESULT_JSON=".json_encode($wpdb->bajas)."\n";exit($ok?0:1);
});
at_rg_ruta_publica();
