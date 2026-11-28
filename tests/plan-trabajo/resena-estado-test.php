<?php
/** Integración del motor con dobles de WordPress/BD. No carga wp-config, no usa red ni SMTP. */
define('ABSPATH', __DIR__ . '/fixture-sin-wordpress/');
define('AT_REST_SECRET', 'fixture-sintetica-sin-valor-operativo');
define('ARRAY_A', 'ARRAY_A'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60);
$GLOBALS['opts'] = ['at_resena_schema'=>'2','at_resena_google_url'=>'https://g.page/r/ejemplo/review','at_resena_whatsapp_webhook'=>'https://n8n.ejemplo.cl/webhook/resena'];
$GLOBALS['trans'] = []; $GLOBALS['mails'] = []; $GLOBALS['hooks'] = []; $GLOBALS['history'] = []; $GLOBALS['remote'] = [];
class WP_Error { function __construct(public $code, public $message='', public $data=[]) {} function get_error_code(){return $this->code;} function get_error_message(){return $this->message;} function get_error_data(){return $this->data;} }
class WP_REST_Request { public $p=[]; public $h=[]; public $url=[]; function __construct(...$x){} function get_param($k){return $this->p[$k]??null;} function get_url_params(){return $this->url;} function set_url_params($v){$this->url=$v;} function set_param($k,$v){$this->p[$k]=$v;} function get_header($k){return $this->h[$k]??'';} }
function add_action($n,$cb,$priority=10,...$rest){$GLOBALS['hooks'][$n][$priority][]=$cb;} function add_filter(...$a){add_action(...$a);}
function remove_action($n,$cb,$priority=10){foreach($GLOBALS['hooks'][$n][$priority]??[] as $k=>$v){if($v===$cb)unset($GLOBALS['hooks'][$n][$priority][$k]);}} function remove_filter(...$a){remove_action(...$a);}
function get_option($k,$v=false){return $GLOBALS['opts'][$k]??$v;} function update_option($k,$v,...$x){$GLOBALS['opts'][$k]=$v;return true;}
function add_option($k,$v,...$x){if(isset($GLOBALS['opts'][$k]))return false;$GLOBALS['opts'][$k]=$v;return true;}
function get_transient($k){return $GLOBALS['trans'][$k]??false;} function set_transient($k,$v,...$x){if(isset($GLOBALS['onTransient'])){$cb=$GLOBALS['onTransient'];unset($GLOBALS['onTransient']);$cb();}$GLOBALS['trans'][$k]=$v;} function delete_transient($k){unset($GLOBALS['trans'][$k]);}
function wp_json_encode($v,...$x){return json_encode($v,...$x);} function is_wp_error($v){return $v instanceof WP_Error;}
function current_time($v){return date('Y-m-d H:i:s');} function get_current_user_id(){return 7;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function sanitize_text_field($v){return strip_tags($v);} function absint($v){return abs((int)$v);} function home_url($p=''){return 'https://at.ejemplo.cl'.$p;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);} function esc_url($s){return esc_html($s);} function esc_attr($s){return esc_html($s);}
function get_template_directory_uri(){return 'https://at.ejemplo.cl/wp-content/themes/automatiza-tech';}
function add_query_arg($k,$v,$url=null){if(is_array($k))return $v.'?'.http_build_query($k);return $url.'?'.$k.'='.rawurlencode($v);}
class ResenaTestRedirect extends RuntimeException {}
function wp_safe_redirect($url){throw new ResenaTestRedirect('redireccion interceptada');} function wp_salt($s){return 'sal-ficticia-unitaria';}
function wp_generate_uuid4(){static $i=0;return sprintf('00000000-0000-4000-8000-%012d',++$i);}
function at_cc_correo_avisos(){return 'operacion@ejemplo.cl';}
function wp_mail($to,$subject,$body,$headers=[]){
    if(($GLOBALS['mail_throw_to']??null)===$to || !empty($GLOBALS['mail_throw']))throw new RuntimeException('fallo sintético');
    $args=compact('to','subject','body','headers');
    $hooks=$GLOBALS['hooks']['wp_mail']??[];ksort($hooks);foreach($hooks as $cbs)foreach($cbs as $cb)$args=$cb($args);
    $mailer=new class {
        public $to=['inyectado@ejemplo.cl'];public $cc=['copia@ejemplo.cl'];public $bcc=['oculto@ejemplo.cl'];
        public $AltBody='';
        function clearAllRecipients(){$this->to=$this->cc=$this->bcc=[];}
        function addAddress($v){$this->to[]=$v;}
        function addCC($v){$this->cc[]=$v;}
        function addBCC($v){
            if(($GLOBALS['bcc_fail']??'')==='false')return false;
            $this->bcc[]=$v;
            if(($GLOBALS['bcc_fail']??'')==='throw')throw new RuntimeException('CCO sintética rechazada');
            return true;
        }
        function clearBCCs(){$this->bcc=[];}
    };
    $hooks=$GLOBALS['hooks']['phpmailer_init']??[];ksort($hooks);foreach($hooks as $cbs)foreach($cbs as $cb)$cb($mailer);
    $GLOBALS['mails'][]=['to'=>$args['to'],'subject'=>$subject,'body'=>$args['body'],'headers'=>$args['headers'],'mailer'=>$mailer]; return ($GLOBALS['mail_fail_to']??null)===$to ? false : ($GLOBALS['mail_ok']??true);
}
function wp_safe_remote_post($u,$p){$GLOBALS['remote'][]=$u;$GLOBALS['remote_args'][]=[$u,$p];return $GLOBALS['remote_result']??['status'=>200];}
function wp_remote_retrieve_response_code($r){return $r['status']??0;}
function at_pt_plan($id){return $GLOBALS['plans'][$id]??null;}
function at_av_normalizar($a){return is_array($a)?$a:json_decode($a?:'{}',true);}
function at_av_guardar_si_igual($id,$before,$a){if($GLOBALS['plans'][$id]->avance!==$before)return false;$GLOBALS['plans'][$id]->avance=json_encode($a);return true;}
/** Dobles SQL: la condición de revisión se aplica como UPDATE real; permite intercalar una escritura rival. */
class ResenaDB {
    public $prefix='wp_';public $last_error='';public $insert_id=0;public $rows=[];public $clientes=[];public $bajas=[];public $conflicto=null;public $failHistory=false;public $failBajas=false;public $connectionId=100;public $replayHistory=false;public $queries=[];public $wamids=[];
    function prepare($q,...$args){return json_encode([$q,$args]);} function esc_like($s){return $s;} function get_charset_collate(){return 'DEFAULT CHARACTER SET utf8mb4';}
    function parse($sql){$x=json_decode($sql,true);return is_array($x)?$x:[$sql,[]];}
    function get_row($sql,$format=null){[$q,$a]=$this->parse($sql);$this->last_error='';
        if(str_contains($q,'at_resena_wamids'))return isset($this->wamids[$a[0]])?(object)$this->wamids[$a[0]]:null;
        if(str_contains($q,'crm_clientes'))return $this->clientes[$a[0]]??null;
        if(str_contains($q,'plan_id=')){foreach($this->rows as $r)if((int)$r->plan_id===$a[0])return clone $r;return null;}
        return isset($this->rows[$a[0]??0])?clone $this->rows[$a[0]]:null;
    }
    function get_var($sql){[$q,$a]=$this->parse($sql);if(str_contains($q,'SELECT CONNECTION_ID()'))return $this->connectionId;if(str_contains($q,'GET_LOCK')||str_contains($q,'RELEASE_LOCK'))return 1;
        if(str_contains($q,'WHERE plan_id=')){foreach($this->rows as $r)if((int)$r->plan_id===$a[0] && (int)$r->crm_id===$a[1])return $r->id;} return null;}
    function get_results($sql,$format=null){[$q,$a]=$this->parse($sql);$this->last_error='';
        $this->queries[]=[$q,$a];
        if(str_contains($q,'automatiza_propuestas_details'))return []; // ningun wamid de propuesta en estas fixtures
        if(str_contains($q,'at_resena_bajas')){if($this->failBajas){$this->last_error='fallo sintético';return null;}return $this->bajas;}
        $out=[];foreach($this->rows as $r){
            if(str_contains($q,"c.tipo='cerrado'") && (($this->clientes[$r->crm_id]['tipo']??'')!=='cerrado' || isset(json_decode($r->datos,true)['anonimizado_at'])))continue;
            if(str_contains($q,'abierto=1') && isset($r->abierto) && !$r->abierto)continue;
            if(str_contains($q,'fecha_termino BETWEEN') && !str_contains($q,'OR (') && (($r->fecha_termino??json_decode($r->datos,true)['termino']['fecha']??'')<$a[0] || ($r->fecha_termino??json_decode($r->datos,true)['termino']['fecha']??'')>$a[1]))continue;if(str_contains($q,'crm_id=') && (int)$r->crm_id!==$a[0])continue;
            if(str_contains($q,'datos LIKE') && !str_contains($r->datos,trim($a[0],'%')))continue;
            if(str_contains($q,'fecha_termino IS NOT NULL') && empty(json_decode($r->datos,true)['termino']['fecha']))continue;$out[]=clone $r;}
        usort($out,fn($a,$b)=>strcmp(json_decode($a->datos,true)['termino']['fecha']??'',json_decode($b->datos,true)['termino']['fecha']??''));return $out;
    }
    function query($sql){[$q,$a]=$this->parse($sql);$this->last_error='';
        $this->queries[]=[$q,$a];
        if(str_contains($q,"SET nota=''")){foreach($this->bajas as &$b)$b['nota']='';unset($b);return 1;}
        if(str_contains($q,'DELETE FROM wp_at_resena_wamids')){foreach($this->wamids as $k=>$w)if($w['resena_id']===$a[0])unset($this->wamids[$k]);return 1;}
        if(str_contains($q,'SET datos=')){
            [$json,$fecha,$abierto,$updated,$id,$revision]=$a;if($this->conflicto){$cb=$this->conflicto;$this->conflicto=null;$cb($this,$id);}
            if(!isset($this->rows[$id]) || (int)$this->rows[$id]->revision!==(int)$revision)return 0;
            $this->rows[$id]->datos=$json;$this->rows[$id]->fecha_termino=$fecha;$this->rows[$id]->abierto=$abierto;$this->rows[$id]->revision++;return 1;
        }
        if(str_contains($q,'SET plan_id=')){[$plan,$id]=$a;$this->rows[$id]->plan_id=$plan;$this->rows[$id]->revision++;return 1;}
        if(str_contains($q,'INSERT INTO wp_at_resena_bajas')){if($this->failBajas)return false;[$email,$telefono,$canal,$alcance,$fecha,$origen,$nota,$clave]=$a;
            foreach($this->bajas as $b)if(($b['clave']??'')===$clave)return 0;
            $this->bajas[]=compact('email','telefono','canal','alcance','fecha','origen','nota','clave');return 1;}
        if(str_contains($q,'INSERT INTO wp_at_resena_wamids')){[$w,$id,$intento,$modo]=$a;if(!isset($this->wamids[$w]))$this->wamids[$w]=['resena_id'=>$id,'intento_id'=>$intento,'modo'=>$modo];return 1;}
        if(str_contains($q,'INSERT INTO wp_at_resenas')){
            [$clave,$crm,$json]=$a;foreach($this->rows as $r)if($r->proyecto_clave===$clave)return false;
            preg_match('/SELECT %s,%d,([0-9]+|NULL),/',$q,$m);$id=++$this->insert_id;
            $this->rows[$id]=(object)['id'=>$id,'proyecto_clave'=>$clave,'crm_id'=>$crm,'plan_id'=>$m[1]==='NULL'?null:(int)$m[1],'revision'=>0,'datos'=>$json];return 1;
        }
        if(str_contains($q,'INSERT INTO wp_crm_historial')){
            if($this->failHistory)return false;
            [$crm,$tipo,$titulo,$descripcion,$metadata,$usuario,$fecha,$connection]=$a;
            if(!str_contains($q,'WHERE CONNECTION_ID()=%d') || $connection!==$this->connectionId)return 0;
            $GLOBALS['history'][]=compact('crm','tipo','titulo','descripcion','metadata','usuario','fecha');
            if($this->replayHistory){$this->connectionId++;return 0;} // primer INSERT ejecutado, ack perdido; replay en otra conexión no cumple WHERE
            return 1;
        }
        throw new RuntimeException('SQL sin doble: '.$q);
    }
    function insert($table,$data){if($this->failHistory)return false;$GLOBALS['history'][]=$data;if($this->replayHistory)$GLOBALS['history'][]=$data;return 1;}
}
$wpdb=new ResenaDB();
$base=__DIR__.'/../../wp-content/themes/automatiza-tech/inc/';
require $base.'cierre-cliente/puras.php'; require $base.'cierre-cliente/whatsapp.php';
require $base.'cierre-cliente/whatsapp-recordatorios.php';
foreach(['resena-puras','resena-datos','resena-bajas','resena-correo','resena'] as $mod)require $base.'plan-trabajo/'.$mod.'.php';
// Lector real de avance, sin cargar las demás escrituras del módulo (que tienen dobles aquí).
$srcAvance=file_get_contents($base.'plan-trabajo/avance-datos.php');
$inicio=strpos($srcAvance,'function at_av_leer('); $fin=strpos($srcAvance,"\n}",$inicio);
eval(substr($srcAvance,$inicio,$fin-$inicio+2));
$fallas=0;$n=0;function ok($v,$m){global $fallas,$n;$n++;if(!$v){$fallas++;echo "FAIL $m\n";}}
function fixture($id=1){global $wpdb;$d=at_rg_vacio();$d['termino']=['fecha'=>at_rg_ahora()->modify('-8 days')->format('Y-m-d')];
    $d['permiso']=['autorizado'=>true,'origen'=>'Formulario explícito','fecha'=>'2026-11-19','telefono'=>'+56900000000'];
    $wpdb->rows[$id]=(object)['id'=>$id,'crm_id'=>$id,'plan_id'=>null,'proyecto_clave'=>'crm:'.$id,'revision'=>0,'datos'=>json_encode($d)];
    $wpdb->clientes[$id]=['id'=>$id,'tipo'=>'cliente','nombre'=>'Persona Ejemplo','empresa'=>'Proyecto ficticio','email'=>'persona@ejemplo.cl','telefono'=>'+56900000000'];return $d;
}
function request($data){$r=new WP_REST_Request('POST');$r->p=$data;$r->url=array_intersect_key($data,array_flip(['proyecto','plan']));return $r;}
if (defined('AT_RG_FIXTURES_ONLY')) { return; }
if (isset($argv[1]) && str_starts_with($argv[1], '--v7=')) {
    $punto = substr($argv[1], 5); require __DIR__.'/resena-v7-casos.php';
    echo "V7 $punto: $n comprobaciones, $fallas fallas\n"; exit($fallas ? 1 : 0);
}
fixture();
$r=new WP_REST_Request();ok(at_rg_auth($r)->get_error_data()['status']===401,'401 sin clave');$r->h['x_at_secret']='incorrecta';ok(at_rg_auth($r)->get_error_data()['status']===401,'401 clave incorrecta');$r->h['x_at_secret']=AT_REST_SECRET;ok(at_rg_auth($r)===true,'auth válida');
$pend=at_rg_pendientes();ok(count($pend['pendientes'])===(at_rg_ahora()->format('Y-m-d') >= '2026-11-26' ? 1 : 0),'cola respeta corte histórico según fecha real');
$wpdb->bajas=[['email'=>'persona@ejemplo.cl','telefono'=>'+56900000000','canal'=>'todos','alcance'=>'resenas']];
$antes=count($GLOBALS['mails']);ok(is_wp_error(at_rg_reclamar(1,['manual'=>true])) && count($GLOBALS['mails'])===$antes,'baja entre pendientes y reclamo impide efectos');$wpdb->bajas=[];
$wpdb->failBajas=true;ok(is_wp_error(at_rg_reclamar(1,['manual'=>true])),'consulta bajas fallida bloquea');$wpdb->failBajas=false;
$wpdb->conflicto=function($db,$id){$d=json_decode($db->rows[$id]->datos,true);$d['intentos'][]=at_rg_intento('rival','real','panel',false,['correo'=>true,'whatsapp'=>true],at_rg_ahora());$db->rows[$id]->datos=json_encode($d);$db->rows[$id]->revision++;};
$antes=count($GLOBALS['mails']);ok(is_wp_error(at_rg_reclamar(1,['manual'=>true])) && count($GLOBALS['mails'])===$antes,'panel/reloj intercalados: CAS pierde y no envía');
fixture();$r=at_rg_reclamar(1,['manual'=>true]);ok(!is_wp_error($r),'primer reclamo');ok(count($GLOBALS['history'])>0,'aceptación deja una nota real en el doble SQL');$i=at_rg_datos(at_rg_fila(1))['intentos'][0];
$antes=count($GLOBALS['mails']);ok(is_wp_error(at_rg_reclamar(1,['manual'=>true])) && count($GLOBALS['mails'])===$antes,'segundo reclamo no duplica');
$payload=at_rg_autorizar_wa(1,$i['id'],'real');ok($payload['telefono']==='+56900000000','autoriza WhatsApp una vez');ok(is_wp_error(at_rg_autorizar_wa(1,$i['id'],'real')),'replay webhook bloqueado');
$r=request(['proyecto'=>1,'intento_id'=>$i['id'],'modo'=>'prueba','estado'=>'aceptado','wamid'=>'wamid.ejemplo01']);ok(is_wp_error(at_rg_rest_resultado($r)),'callback modo ajeno rechazado');$r->p['modo']='real';
// Un estado sin correlación no crea basura. Tras resultado, el índice lo reconoce.
at_rg_whatsapp_estado('wamid.ejemplo01','failed',131049);ok(at_rg_rest_resultado($r)===['ok'=>true],'resultado aceptado registra wamid');
at_rg_whatsapp_estado('wamid.ejemplo01','failed',131049);
$d=at_rg_datos(at_rg_fila(1));ok($d['intentos'][0]['whatsapp']['estado']==='fallido','failed correlacionado persistido');
$hist=count($GLOBALS['history']);$mail=count($GLOBALS['mails']);$rev=at_rg_fila(1)->revision;
at_rg_rest_resultado($r);at_rg_whatsapp_estado('wamid.ejemplo01','failed',131049);
ok(count($GLOBALS['history'])===$hist && count($GLOBALS['mails'])===$mail && at_rg_fila(1)->revision===$rev,'repetidos no alteran revisión, historial ni avisos');
// SMTP aceptó pero no hubo resultado de WA: no se vuelve a enviar al vencer.
at_rg_cambiar(1,function($d){$d['intentos'][0]['whatsapp']['estado']='en_curso';$d['intentos'][0]['whatsapp']['at']=at_rg_ahora()->modify('-31 minutes')->format(DATE_ATOM);return $d;});at_rg_mantener();$mail=count($GLOBALS['mails']);ok(at_rg_datos(at_rg_fila(1))['intentos'][0]['whatsapp']['estado']==='desconocido' && is_wp_error(at_rg_reclamar(1,['manual'=>true])) && count($GLOBALS['mails'])===$mail,'timeout desconocido sin reenvío');
// Modo prueba sustituye también copias inyectadas y no escribe CRM ni consume real.
fixture(2);$GLOBALS['opts']['at_resena_prueba_email']='prueba@ejemplo.cl';ok(is_wp_error(at_rg_reclamar(2,['manual'=>true,'prueba'=>true])),'prueba sin teléfono no cae al real');
$GLOBALS['opts']['at_resena_prueba_telefono']='+56900000001';$hist=count($GLOBALS['history']);$r=at_rg_reclamar(2,['manual'=>true,'prueba'=>true]);$p=at_rg_datos(at_rg_fila(2))['intentos'][0];
$payload=at_rg_autorizar_wa(2,$p['id'],'prueba');ok($payload['telefono']==='+56900000001','destino WA de prueba');
$mail=$GLOBALS['mails'][count($GLOBALS['mails'])-1];ok($mail['to']==='prueba@ejemplo.cl' && $mail['mailer']->to===['prueba@ejemplo.cl'] && !$mail['mailer']->cc && !$mail['mailer']->bcc,'para cc y bcc aislados');
ok(count($GLOBALS['history'])===$hist,'prueba no escribe historial cliente');ok(!is_wp_error(at_rg_reclamar(2,['manual'=>true])),'prueba seguida de real');
// Reenvío: segundo solo con intervalo, rival simultáneo y tercer intento.
fixture(3);at_rg_cambiar(3,function($d){$i=at_rg_intento('primero','real','panel',false,['correo'=>true,'whatsapp'=>true],at_rg_ahora()->modify('-8 days'));$i['correo']['estado']='aceptado';$i['whatsapp']['estado']='aceptado';$i['destinos']=['correo'=>'persona@ejemplo.cl','whatsapp'=>'+56900000000'];$i['despacho_wa']=true;$d['intentos'][]=$i;return $d;});
$r=at_rg_reclamar(3,['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP']);ok(!is_wp_error($r),'segundo intento permitido');ok(is_wp_error(at_rg_reclamar(3,['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP'])),'doble clic de reenvío no duplica');
at_rg_cambiar(3,function($d){$d['intentos'][1]['whatsapp']['estado']='fallido';return $d;});ok(is_wp_error(at_rg_reclamar(3,['manual'=>true,'reenvio'=>true,'motivo'=>'Rebote confirmado por SMTP'])),'tercero rechazado aun liberado');
$segundo=at_rg_datos(at_rg_fila(3))['intentos'][1];at_rg_anotar_resultado(3,'primero','real','entregado','wamid.primero01','');ok(at_rg_datos(at_rg_fila(3))['intentos'][1]===$segundo,'devolución tardía conserva segundo');
// Dos fallos, ausencia de canal y fallo del historial no permiten volver a enviar.
fixture(4);$GLOBALS['mail_ok']=false;$r=at_rg_reclamar(4,['manual'=>true]);$ii=at_rg_datos(at_rg_fila(4))['intentos'][0];at_rg_anotar_resultado(4,$ii['id'],'real','fallido','','Rechazo sintético');ok(at_rg_estado(at_rg_datos(at_rg_fila(4))['intentos'][0])==='Fallida','ambos fallan');$GLOBALS['mail_ok']=true;
fixture(5);$wpdb->clientes[5]['telefono']='';$wpdb->failHistory=true;$r=at_rg_reclamar(5,['manual'=>true]);ok(!is_wp_error($r) && at_rg_datos(at_rg_fila(5))['intentos'][0]['whatsapp']['estado']==='no_intentado','canal ausente y fallo historial no deshacen intento');ok(is_wp_error(at_rg_reclamar(5,['manual'=>true])),'historial fallido no repite envío');$wpdb->failHistory=false;
// Token de baja firmado, manipulación y snapshot (el correo actual puede haber cambiado).
$token=at_rg_token_baja(2,$p);ok(at_rg_validar_baja($token)===['email'=>'prueba@ejemplo.cl'],'token válido usa snapshot prueba');ok(at_rg_validar_baja($token.'a')===null && at_rg_validar_baja('1.'.substr($token,2))===null,'token alterado rechazado');
// Identidad: primera fila sin plan, luego vinculada; no se restablece JSON ni contador.
$wpdb->insert_id=100;$f=at_rg_proyecto(99);$d=at_rg_datos($f);$marca=at_rg_intento('conservar','real','panel',false,['correo'=>false,'whatsapp'=>false],at_rg_ahora());$d['intentos']=[$marca];at_rg_cas($f,$d);$GLOBALS['plans'][88]=(object)['id'=>88,'crm_cliente_id'=>99,'avance'=>'{}'];$g=at_rg_proyecto(99,88);$h=at_rg_proyecto(99);
ok($f->id===$g->id && $g->id===$h->id && $g->proyecto_clave==='crm:99' && at_rg_datos($g)['intentos']===[$marca],'vinculación posterior conserva identidad e intentos');
$stale=$g;$fresh=at_rg_fila($g->id);$dd=at_rg_datos($fresh);$dd['excluido']=true;ok(at_rg_cas($fresh,$dd),'CAS ganador');ok(!at_rg_cas($stale,at_rg_vacio()),'CAS antiguo no borra marca');
// Webhook sin confirmación no se reintenta ni se vuelve elegible.
fixture(6);$GLOBALS['remote_result']=new WP_Error('timeout');$r=at_rg_reclamar(6,['manual'=>true]);ok(at_rg_datos(at_rg_fila(6))['intentos'][0]['whatsapp']['estado']==='desconocido','timeout webhook bloquea');unset($GLOBALS['remote_result']);
fixture(7);$GLOBALS['mail_throw']=true;at_rg_reclamar(7,['manual'=>true]);unset($GLOBALS['mail_throw']);ok(at_rg_datos(at_rg_fila(7))['intentos'][0]['correo']['estado']==='desconocido','excepción SMTP es incierta');
function current_user_can($cap){return $GLOBALS['admin']??true;}
function check_admin_referer($a){if(empty($GLOBALS['nonce']))throw new RuntimeException('nonce rechazado');}
function wp_die(...$a){throw new RuntimeException('permiso rechazado');}
function wp_unslash($v){return $v;}
function checked($a,$b,$echo=false){return $a==$b?'checked':'';}
function admin_url($p=''){return 'https://at.ejemplo.cl/wp-admin/'.$p;}
function wp_nonce_field($a,...$x){return '<input name="_wpnonce" value="nonce-sintetico">';}
require $base.'plan-trabajo/avance-panel.php';
$GLOBALS['admin']=false;try{at_rg_panel_accion();ok(false,'capability');}catch(RuntimeException $e){ok($e->getMessage()==='permiso rechazado','panel sin manage_options bloqueado');}
$GLOBALS['admin']=true;$_POST=['proyecto_id'=>'2'];try{at_rg_panel_accion();ok(false,'nonce');}catch(RuntimeException $e){ok($e->getMessage()==='nonce rechazado','panel sin nonce bloqueado');}
ob_start();at_rg_render_panel(2);$html=ob_get_clean();ok(str_contains($html,'_wpnonce') && str_contains($html,'🏁 Marcar proyecto terminado') && str_contains($html,'🔁 Volver a enviar'),'panel muestra controles con nonce e iconos');
$old=['cierres'=>[],'termino'=>['fecha'=>'2026-01-01']];$fresh=['cierres'=>[],'termino'=>['fecha'=>'2026-11-19'],'render'=>[],'render_previa'=>[]];ok(at_av_con_lo_ajeno($old,$fresh,false)['termino']===$fresh['termino'],'guardado de avance conserva término fresco');
// La ruta existente admite entrega/lectura de la reseña y callbacks repetidos.
fixture(8);at_rg_reclamar(8,['manual'=>true]);$ii=at_rg_datos(at_rg_fila(8))['intentos'][0];at_rg_autorizar_wa(8,$ii['id'],'real');at_rg_anotar_resultado(8,$ii['id'],'real','aceptado','wamid.entrega01','');
$req=request(['estados'=>[['wamid'=>'wamid.entrega01','estado'=>'delivered']]]);at_cc_rest_estado_whatsapp($req);ok(at_rg_datos(at_rg_fila(8))['intentos'][0]['whatsapp']['estado']==='entregado','ruta existente persiste delivered');
$req->p['estados'][0]['estado']='read';at_cc_rest_estado_whatsapp($req);$rev=at_rg_fila(8)->revision;at_cc_rest_estado_whatsapp($req);ok(at_rg_datos(at_rg_fila(8))['intentos'][0]['whatsapp']['estado']==='leido' && at_rg_fila(8)->revision===$rev,'ruta persiste read una vez');
fixture(10);at_rg_reclamar(10,['manual'=>true]);$wa10=at_rg_datos(at_rg_fila(10))['intentos'][0];$urlGuardada=$GLOBALS['opts']['at_resena_google_url'];$GLOBALS['opts']['at_resena_google_url']='';ok(is_wp_error(at_rg_autorizar_wa(10,$wa10['id'],'real')),'retirar enlace entre reclamo y despacho bloquea WhatsApp');$GLOBALS['opts']['at_resena_google_url']=$urlGuardada;
fixture(11);$wpdb->rows[11]->plan_id=1234;$GLOBALS['plans'][1234]=(object)['id'=>1234,'crm_cliente_id'=>12];$correoAntes=count($GLOBALS['mails']);ok(is_wp_error(at_rg_reclamar(11,['manual'=>true])) && count($GLOBALS['mails'])===$correoAntes,'plan reasignado bloquea envío al CRM anterior');
fixture(12);$wpdb->replayHistory=true;$antesHistoria=count($GLOBALS['history']);at_rg_reclamar(12,['manual'=>true]);ok(count($GLOBALS['history'])-$antesHistoria<=1,'replay de wpdb tras reconexión no duplica nota');$wpdb->replayHistory=false;
fixture(9);$wpdb->rows[9]->datos='{roto';ok(at_rg_mantener() && is_wp_error(at_rg_reclamar(9,['manual'=>true])),'registro ilegible bloquea sin detener mantenimiento');
require __DIR__ . '/resena-v2-casos.php';
require __DIR__ . '/resena-v3-casos.php';
require __DIR__ . '/resena-v4-casos.php';
require __DIR__ . '/resena-v5-casos.php';
require __DIR__ . '/resena-v6-casos.php';
$punto='todos'; require __DIR__.'/resena-v7-casos.php';
require __DIR__.'/resena-v8-resumen-casos.php';
echo "$n comprobaciones de integración con dobles, $fallas fallas\n";exit($fallas?1:0);
