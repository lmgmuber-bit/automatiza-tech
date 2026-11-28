<?php
/** Ejecutar SOLO en un WP local desechable con prefijo resenatest_ y este tema/mu-plugin.
 * AT_WP_LOAD + AT_RESENA_LOCAL_TEST=1. Bloquea HTTP externo y todo correo ANTES de los casos.
 * No ejecutar contra una copia con datos reales. La migración usa su modo CLI explícito.
 */
if (getenv('AT_RESENA_LOCAL_TEST') !== '1' || !getenv('AT_WP_LOAD')) {
    fwrite(STDERR, "NOT_RUN: requiere AT_RESENA_LOCAL_TEST=1 y AT_WP_LOAD de un WordPress local aislado (prefijo resenatest_).\n"); exit(2);
}
define('WP_HTTP_BLOCK_EXTERNAL', true); define('DISABLE_WP_CRON', true);
require getenv('AT_WP_LOAD');
global $wpdb;
if (!str_starts_with($wpdb->prefix, 'resenatest_') || !in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost','127.0.0.1','::1'], true)) {
    fwrite(STDERR, "Bloqueado: no es una instalación local aislada con prefijo resenatest_.\n"); exit(2);
}
if (!function_exists('at_rg_reclamar')) { fwrite(STDERR, "NOT_RUN: instalar el tema AT con el módulo de reseñas.\n"); exit(2); }
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
add_filter('pre_http_request', static fn() => ['headers'=>[], 'body'=>'{}', 'response'=>['code'=>200,'message'=>'OK'], 'cookies'=>[]], PHP_INT_MAX);
if (($argv[1] ?? '') === 'worker') {
    $f = at_rg_proyecto((int) $argv[2], (int) ($argv[3] ?? 0)); echo $f ? (int) $f->id : 0; exit($f ? 0 : 1);
}
$fallas=0; $n=0;
function ok($v,$m){global $fallas,$n;$n++;echo ($v?'PASS ':'FAIL ').$m."\n";if(!$v)$fallas++;}
function parallel_identity(int $crm, int $plan=0): array {
    $jobs=[];
    for($n=0;$n<4;$n++){
        $p=proc_open([PHP_BINARY,'-d','xdebug.mode=off',__FILE__,'worker',(string)$crm,(string)$plan],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p))throw new RuntimeException('No se pudo iniciar worker');fclose($pipes[0]);$jobs[]=[$p,$pipes];
    }
    $ids=[];
    foreach($jobs as [$p,$pipes]){$out=trim(stream_get_contents($pipes[1]));stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);if($status!==0 || !ctype_digit($out))throw new RuntimeException('Worker falló; no se imprimen logs de WP');$ids[]=(int)$out;}
    return $ids;
}
$crm=0;$plan=0;$rid=0;$options=['at_resena_google_url','at_resena_prueba_email','at_resena_prueba_telefono','at_resena_whatsapp_webhook'];$saved=[];
foreach($options as $o){$saved[$o]=get_option($o,null);}
try {
    delete_option('at_resena_schema');delete_transient('at_resena_migrar_intento');ok(at_rg_migrar(true),'migración crea/verifica columnas e índices');ok(at_rg_migrar(true),'segunda migración idempotente');
    $col=$wpdb->get_row('SHOW COLUMNS FROM '.at_rg_tabla()." LIKE 'proyecto_clave'",ARRAY_A);ok($col['Null']==='NO','proyecto_clave NOT NULL');
    update_option('at_resena_google_url','https://g.page/r/ejemplo/review');update_option('at_resena_prueba_email','prueba@ejemplo.cl');update_option('at_resena_prueba_telefono','+56900000001');update_option('at_resena_whatsapp_webhook','');
    $wpdb->insert($wpdb->prefix.'crm_clientes',['nombre'=>'Persona ficticia','email'=>'persona@ejemplo.cl','telefono'=>'+56900000000','empresa'=>'Proyecto ficticio','tipo'=>'cliente']);$crm=(int)$wpdb->insert_id;
    if(!$crm)throw new RuntimeException('No se pudo crear fixture CRM');
    $ids=parallel_identity($crm);$rid=$ids[0];ok(count(array_unique($ids))===1 && $rid>0,'4 procesos crean una sola identidad sin plan');
    $d=at_rg_datos(at_rg_fila($rid));$d['termino']=['fecha'=>at_rg_ahora()->format('Y-m-d')];at_rg_cas(at_rg_fila($rid),$d);
    $r=at_rg_reclamar($rid,['manual'=>true,'prueba'=>true]);ok(!is_wp_error($r),'prueba aislada sin webhook');$r=at_rg_reclamar($rid,['manual'=>true]);ok(!is_wp_error($r),'real después de prueba no consume cupo previo');
    $r=at_rg_reclamar($rid,['manual'=>true]);ok(is_wp_error($r),'no hay segundo envío inmediato');
    $before=at_rg_datos(at_rg_fila($rid));
    at_pt_migrar_esquema();at_av_migrar_esquema();
    $wpdb->insert(at_pt_tabla(),['contrato_id'=>987654321,'crm_cliente_id'=>$crm,'codigo'=>'RsTest123456','estado'=>'enviado','avance'=>'{}']);$plan=(int)$wpdb->insert_id;
    if(!$plan)throw new RuntimeException('No se pudo crear fixture plan');
    $linked=parallel_identity($crm,$plan);ok(count(array_unique($linked))===1 && $linked[0]===$rid && at_rg_datos(at_rg_fila($rid))===$before,'vinculación concurrente conserva identidad, pruebas e invitación');
    $stale=at_rg_fila($rid);$a=at_rg_datos($stale);$a['excluido']=true;ok(at_rg_cas(at_rg_fila($rid),$a),'CAS ganador en MySQL');ok(!at_rg_cas($stale,$before),'MySQL rechaza revisión antigua');
    at_rg_reflejar_termino($rid);$p1=at_pt_plan($plan);$a1=at_av_leer($p1);$resena_antes=at_rg_datos(at_rg_fila($rid));
    // MySQL informa cero filas afectadas si se escribe el mismo JSON; cambiar un bloque de verdad.
    $a1['bloques']=['QA local'=>['estado'=>'listo']];
    ok(at_av_guardar_si_igual($plan,(string)$p1->avance,$a1) && at_av_leer(at_pt_plan($plan))['bloques']===$a1['bloques'],'guardado avance');
    ok(at_rg_datos(at_rg_fila($rid))===$resena_antes && count(at_rg_reales($resena_antes))===1,'avance no borra intento');
    $req=new WP_REST_Request('GET','/at/v1/resena/pendientes');$resp=rest_do_request($req);ok($resp->get_status()===401,'REST sin clave 401');
    if(defined('AT_REST_SECRET') && AT_REST_SECRET!==''){
        $req=new WP_REST_Request('POST','/at/v1/resena/proyecto/'.$rid.'/resultado');$req->set_header('X-AT-Secret',AT_REST_SECRET);$req->set_body_params(['intento_id'=>'inexistente','modo'=>'real','estado'=>'aceptado','wamid'=>'wamid.sintetico01']);$resp=rest_do_request($req);ok($resp->get_status()>=400,'callback inexistente rechazado');
    }else{echo "NOT_RUN auth positiva: falta una clave ficticia en la instalación de pruebas.\n";}
    echo "$n comprobaciones WP/MySQL, $fallas fallas\n";
} finally {
    // Solo fixtures creadas por esta ejecución; ni DROP, ni tablas, ni marcas ajenas.
    if($rid)$wpdb->delete(at_rg_tabla(),['id'=>$rid]);
    if($plan)$wpdb->delete(at_pt_tabla(),['id'=>$plan]);
    if($crm){$wpdb->delete($wpdb->prefix.'crm_historial',['cliente_id'=>$crm]);$wpdb->delete($wpdb->prefix.'crm_clientes',['id'=>$crm]);}
    foreach($saved as $o=>$v){$v===null?delete_option($o):update_option($o,$v);}
}
exit($fallas?1:0);
