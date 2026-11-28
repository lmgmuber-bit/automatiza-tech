<?php
/** Motor efímero creado por resena-motor-test.py. No carga WordPress/config/credenciales. */
if (getenv('AT_RG_MOTOR_FIXTURE') !== '1' || !ctype_digit(getenv('AT_RG_MOTOR_PORT') ?: '')) { fwrite(STDERR,"NOT_RUN: usar resena-motor-test.py\n"); exit(2); }
define('ABSPATH', 'C:/wamp64/www/automatiza-tech/');
define('WP_DEBUG', false); define('WP_DEBUG_DISPLAY', false); define('DB_CHARSET','utf8mb4'); define('DB_COLLATE','');
$opts=['at_resena_schema'=>'1']; $trans=['at_resena_migrar_intento'=>1];
function add_action(...$a) {} function add_filter(...$a) {} function has_filter(...$a) { return false; }
function apply_filters($tag,$value,...$a) { return $tag==='query' && !empty($GLOBALS['bloquear_alter']) && preg_match('/^ALTER TABLE/i',$value) ? 'SELECT 1' : $value; }
function wp_debug_backtrace_summary(...$a) { return 'fixture-resena'; }
function is_multisite() { return false; }
function absint($v) { return abs((int)$v); }
function current_user_can($cap) { return $GLOBALS['admin']??false; } // Manual también se prueba sin sesión.
function get_option($k,$default=false) { global $opts; return $opts[$k]??$default; }
function update_option($k,$v,...$a) { global $opts; $opts[$k]=$v; return true; }
function get_transient($k) { global $trans; return $trans[$k]??false; }
function set_transient($k,$v,...$a) { global $trans; $trans[$k]=$v; }
function delete_transient($k) { global $trans; unset($trans[$k]); }
function wp_json_encode($v,...$a) { return json_encode($v,...$a); }
require ABSPATH.'wp-includes/class-wpdb.php';
// Se carga únicamente la función oficial dbDelta, sin los includes de instalación ni código del sitio.
$upgrade=file_get_contents(ABSPATH.'wp-admin/includes/upgrade.php');
$inicio=strpos($upgrade,'function dbDelta('); $fin=strpos($upgrade,"\n}",$inicio);
if ($inicio===false || $fin===false) { throw new RuntimeException('No se localizó dbDelta oficial.'); }
eval(substr($upgrade,$inicio,$fin-$inicio+2));
$wpdb = new wpdb('root','','mysql','127.0.0.1:'.getenv('AT_RG_MOTOR_PORT'));
$wpdb->set_prefix('resenatest_'); $wpdb->suppress_errors(true);
// El runner garantiza un datadir recién inicializado; nunca se conecta al WAMP en uso.
$wpdb->query('CREATE DATABASE resena_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$wpdb->select('resena_fixture');
$base=__DIR__.'/../../wp-content/themes/automatiza-tech/inc/';
require $base.'cierre-cliente/puras.php';
require $base.'plan-trabajo/resena-puras.php'; require $base.'plan-trabajo/resena-datos.php'; require $base.'plan-trabajo/resena-bajas.php';
$n=0; $fallas=0;
function ok($v,$nombre) { global $n,$fallas; $n++; echo ($v?'PASS ':'FAIL ').$nombre."\n"; if (!$v) { $fallas++; } }
// Fixture v1 EXACTA del commit revisado. Solo evalúa su función de DDL renombrada.
$git=proc_open(['git','show','50c9410:wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));
$v1=stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
if (proc_close($git)!==0) { throw new RuntimeException('Falta el commit v1.'); }
$inicio=strpos($v1,'function at_rg_sql_esquema(): array {'); $fin=strpos($v1,"\n}",$inicio);
eval(str_replace('at_rg_sql_esquema()', 'at_rg_sql_esquema_v1()',substr($v1,$inicio,$fin-$inicio+2)));
foreach (at_rg_sql_esquema_v1() as $sql) { dbDelta($sql); }
$d=at_rg_vacio(); $d['termino']=['fecha'=>'2026-11-19'];
$i=at_rg_intento('00000000-0000-4000-8000-000000000001','real','panel',false,['correo'=>false,'whatsapp'=>true],new DateTimeImmutable('2026-11-19T12:00:00-03:00'));
$i['whatsapp']['estado']='aceptado'; $i['whatsapp']['wamid']='wamid.migracion01'; $d['intentos']=[$i];
$json=wp_json_encode($d); $jsonRoto='{roto';
$wpdb->query($wpdb->prepare('INSERT INTO '.at_rg_tabla()." (proyecto_clave,crm_id,plan_id,fecha_termino,revision,datos,updated_at) VALUES ('crm:1',1,NULL,'2026-11-19',7,%s,'2026-11-19 15:00:00'),('crm:2',2,NULL,NULL,3,%s,'2026-11-19 15:00:00')",$json,$jsonRoto));
at_rg_baja_guardar('sintetico@ejemplo.cl','','correo','resenas','fixture','conservar');
$bajaAntes=$wpdb->get_row('SELECT * FROM '.at_rg_tabla_bajas(),ARRAY_A);
ok(!at_rg_migrar() && get_option('at_resena_schema')==='1','H7 migración automática respeta bloqueo');
ok(at_rg_migrar(true) && get_option('at_resena_schema')==='2','H5 H6 H7 migración manual v1 a v2 con bloqueo');
$f=at_rg_fila(1); $roto=at_rg_fila(2);
ok($f && $f->datos===$json && (int)$f->revision===7 && $f->plan_id===null && $roto->datos===$jsonRoto && (int)$roto->revision===3,'v1 conserva JSON íntegro e ilegible revisiones e identidad');
ok($wpdb->get_row('SELECT * FROM '.at_rg_tabla_bajas(),ARRAY_A)===$bajaAntes,'v1 conserva baja completa');
$cols=$wpdb->get_results('SHOW COLUMNS FROM '.at_rg_tabla_bajas(),OBJECT_K);
ok($cols['nota']->Type==='varchar(1000)' && $cols['origen']->Type==='varchar(50)','H5 holgura efectiva en motor');
$mapa=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.at_rg_tabla_wamids().' WHERE wamid=%s','wamid.migracion01'));
ok($mapa && (int)$mapa->resena_id===1 && $mapa->intento_id===$i['id'],'H3 reconstruye índice wamid desde v1');
ok((int)$f->abierto===1 && (int)$roto->abierto===1,'H4 mantiene trabajo pendiente e ilegible');
$cambios=[]; foreach(at_rg_sql_esquema() as $sql) { $cambios=array_merge($cambios,dbDelta($sql,false)); }
ok(!$cambios,'H2 dbDelta segunda pasada sin ALTER repetido');
ok(at_rg_migrar(true),'H6 segunda migración idempotente');
ok(at_rg_cas(at_rg_fila(1),at_rg_vacio()) && at_rg_fila(1)->fecha_termino===null,'Vigia-I2 fecha vacía queda NULL con NULLIF');
ok(at_rg_fila(1)->abierto==='0','H4 proyección cierra fila sin trabajo');
ok(at_rg_baja_guardar('sintetico@ejemplo.cl','','correo','resenas','fixture','otro') && (int)$wpdb->get_var('SELECT COUNT(*) FROM '.at_rg_tabla_bajas())===1 && $wpdb->get_row('SELECT * FROM '.at_rg_tabla_bajas(),ARRAY_A)===$bajaAntes,'H10 baja repetida no altera evidencia');
$wpdb->query('ALTER TABLE '.at_rg_tabla_bajas().' MODIFY nota varchar(500) NOT NULL DEFAULT \'\'');
$opts['at_resena_schema']='1'; $GLOBALS['bloquear_alter']=true;
ok(!at_rg_migrar(true) && get_option('at_resena_schema')==='1','H5 H6 no marca v2 si ALTER de holgura no se aplicó');
$GLOBALS['bloquear_alter']=false;
ok(at_rg_migrar(true) && get_option('at_resena_schema')==='2','H5 H7 recupera migración manual tras fallo parcial');
$max=$i; $max['whatsapp']['wamid']='wamid.'.str_repeat('A',250); $dMax=at_rg_vacio(); $dMax['intentos']=[$max];
ok(at_cc_wamid_valido($max['whatsapp']['wamid']) && at_rg_indexar_wamids(99,$dMax),'H3 índice admite wamid de 256 bytes válido para servidor');
// Un backfill con identificador ya reservado no debe repetirse en cada admin_init.
$wpdb->query($wpdb->prepare('INSERT INTO '.at_rg_tabla()." (proyecto_clave,crm_id,datos,updated_at) VALUES ('crm:3',3,%s,'2026-11-19 15:00:00')",$json));
$opts['at_resena_schema']='1'; unset($trans['at_resena_migrar_intento']);
ok(!at_rg_migrar(true) && get_transient('at_resena_migrar_intento') && get_option('at_resena_schema')==='1',
    'V3-5 backfill fallido conserva bloqueo temporal');
$GLOBALS['admin']=true; $consultasAntes=$wpdb->num_queries;
ok(!at_rg_migrar() && $wpdb->num_queries===$consultasAntes, 'V3-5 admin_init no repite backfill bloqueado');
$wpdb->query('DELETE FROM '.at_rg_tabla()." WHERE proyecto_clave='crm:3'"); // solo fixture sintetica del motor efimero
ok(at_rg_migrar(true) && !get_transient('at_resena_migrar_intento'), 'V3-5 manual recupera backfill y libera bloqueo');
$cola=at_rg_vacio(); $cola['termino']=['fecha'=>'2026-11-19'];
foreach([4=>'ninguno',5=>'real',6=>'prueba',7=>'ilegible'] as $crm=>$modo) {
    $dc=$cola;
    if(in_array($modo,['real','prueba'],true)){$ic=$i;$ic['modo']=$modo;$dc['intentos']=[$ic];}
    $wpdb->query($wpdb->prepare('INSERT INTO '.at_rg_tabla()." (proyecto_clave,crm_id,fecha_termino,datos,updated_at) VALUES (%s,%d,'2026-11-19',%s,'2026-11-19 15:00:00')",'crm:'.$crm,$crm,$modo==='ilegible'?'{roto':wp_json_encode($dc)));
}
$candidatos=function_exists('at_rg_filas_pendientes')?at_rg_filas_pendientes('2026-12-01'):[];
if (!is_array($candidatos) || array_map('intval',array_column($candidatos,'crm_id'))!==[4,5,6]) {
    echo 'Diagnostico cola sintetica: '.json_encode(['ids'=>is_array($candidatos)?array_column($candidatos,'crm_id'):null,'error'=>$wpdb->last_error])."\n";
}
ok(is_array($candidatos) && array_map('intval',array_column($candidatos,'crm_id'))===[4,5,6] && $wpdb->last_error==='',
    'V7 SQL incluye recordatorios tramitados y conserva primera invitacion y pruebas');
// M4: mismo intento real, ambos canales; la incertidumbre nunca entra a la cola.
$esperados=[4,5,6,8,9,10,12,15];
foreach([8=>['fallido','fallido'],9=>['fallido','no_intentado'],10=>['no_intentado','fallido'],
    11=>['fallido','desconocido'],12=>['fallido','aceptado'],13=>['en_curso','fallido'],
    14=>['entregado','fallido'],15=>['fallido','leido']] as $crm=>$estados) {
    $dc=$cola; $ic=$i; $ic['correo']['estado']=$estados[0]; $ic['whatsapp']['estado']=$estados[1];
    $dc['intentos']=[$ic];
    $wpdb->query($wpdb->prepare('INSERT INTO '.at_rg_tabla()." (proyecto_clave,crm_id,fecha_termino,datos,updated_at) VALUES (%s,%d,'2026-11-19',%s,'2026-11-19 15:00:00')",'crm:'.$crm,$crm,wp_json_encode($dc)));
}
$candidatos=at_rg_filas_pendientes('2026-12-01');
if (!is_array($candidatos) || array_map('intval',array_column($candidatos,'crm_id'))!==$esperados) {
    echo 'Diagnostico M4 sintetico: '.json_encode(['ids'=>is_array($candidatos)?array_column($candidatos,'crm_id'):null,'error'=>$wpdb->last_error])."\n";
}
ok(is_array($candidatos) && array_map('intval',array_column($candidatos,'crm_id'))===$esperados && $wpdb->last_error==='',
    'V7 SQL conserva fallidos y agrega recordatorios aceptados sin inciertos solos');

// V7: SQL no debe rescatar un reenvío fallido ni una reseña recibida.
foreach([16=>'reenvio',17=>'recibida',18=>'anonimo'] as $crm=>$caso) {
    $dc=$cola;$ic=$i;$ic['correo']['estado']='aceptado';$dc['intentos']=[$ic];
    if($caso==='reenvio'){$ic['tipo']='reenvio';$ic['correo']['estado']=$ic['whatsapp']['estado']='fallido';$dc['intentos'][]=$ic;}
    if($caso==='recibida')$dc['resena_recibida']=['fecha'=>'2026-12-01','por'=>7];
    if($caso==='anonimo')$dc['anonimizado_at']='2029-01-01';
    $wpdb->query($wpdb->prepare('INSERT INTO '.at_rg_tabla()." (proyecto_clave,crm_id,fecha_termino,datos,updated_at) VALUES (%s,%d,'2026-11-19',%s,'2026-11-19 15:00:00')",'crm:'.$crm,$crm,wp_json_encode($dc)));
}
$ids=array_map('intval',array_column(at_rg_filas_pendientes('2026-12-01')??[],'crm_id'));
ok(!array_intersect([16,17,18],$ids),'V7 SQL excluye reenvio incluso fallido marca recibida y anonimizado');
echo 'Motor '.$wpdb->db_server_info()."; $n comprobaciones migración, $fallas fallas\n";
exit($fallas?1:0);
