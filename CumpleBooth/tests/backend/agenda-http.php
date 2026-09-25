<?php
/** Agenda por HTTP; fixtures locales, sin correo ni datos de PROD. --serve para QA visual local. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root=dirname(__DIR__,2); $tmp=sys_get_temp_dir().'/cc-agenda-http-'.bin2hex(random_bytes(5)); mkdir($tmp,0700,true);
$base='http://127.0.0.1:'.(getenv('CC_QA_PORT')?:random_int(19000,22000));
$key=getenv('CC_QA_PASSWORD')?:bin2hex(random_bytes(16));
$env=array_merge(getenv(),['CC_STORAGE_MODE'=>'db','CC_PDO_DSN'=>'sqlite:'.$tmp.'/test.sqlite','CC_PDO_USER'=>'','CC_PDO_PASSWORD'=>'',
    'CUMPLECLICK_CONFIG_FILE'=>$tmp.'/absent.php','CC_APP_HMAC_KEY'=>bin2hex(random_bytes(32)),
    'CC_ADMIN_PASSWORD_HASH'=>password_hash($key,PASSWORD_DEFAULT),'CC_PUBLIC_BASE_URL'=>$base,
    'CC_PHOTO_DIR'=>$tmp.'/photos','CC_STATE_DIR'=>$tmp.'/state','CC_SMTP_HOST'=>'','CC_AJUSTES_PATH'=>$tmp.'/ajustes.json']);
foreach($env as $k=>$v){putenv("$k=$v");}
require $root.'/public/lib.php'; require __DIR__.'/_migraciones.php'; cb_test_migrar_todo(cb_pdo());
cb_save_parties(['parties'=>[
    'samantha-hielo'=>['nombre'=>'Samantha','tema'=>'hielo','fecha'=>'2026-09-13','activa'=>true,'creada'=>'2026-09-01 10:00:00','service_plan'=>'full'],
    'luciano-spidey'=>['nombre'=>'Luciano','tema'=>'spidey','fecha'=>'2026-09-13','activa'=>true,'creada'=>'2026-09-01 10:00:00','service_plan'=>'full'],
]]);
require_once $root.'/public/lib.admin-usuarios.php';
$pdo=cb_pdo(); $now=gmdate('Y-m-d H:i:s');
$insert=$pdo->prepare("INSERT INTO cc_admin_users (email,nombre,password_hash,rol,activo,debe_cambiar,modulos,created_at,updated_at) VALUES (?, ?,?,'operador',1,0,?,?,?)");
$insert->execute(['operador@example.invalid','Operador QA',password_hash($key,PASSWORD_DEFAULT),'[]',$now,$now]); $oid=(int)$pdo->lastInsertId();
cb_admin_asignar_fiestas($oid,['samantha-hielo']);
$server=proc_open([PHP_BINARY,'-S',substr($base,7),'-t',$root.'/public'],
    [0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,$root.'/public',$env);
register_shutdown_function(static function()use($server,$tmp){
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($tmp);
});
for($i=0;$i<50;$i++){ $s=@fsockopen('127.0.0.1',(int)parse_url($base,PHP_URL_PORT),$eno,$err,.1);if($s){fclose($s);break;}usleep(100000); }
class AgendaBrowser {
    private array $cookies=[];
    public function __construct(private string $base){}
    public function request(string $method,string $path,?array $data=null):array{
        $headers=[];$body=$data===null?'':http_build_query($data);
        if($data!==null){$headers[]='Content-Type: application/x-www-form-urlencoded';}
        foreach($this->cookies as $k=>$v){$headers[]="Cookie: $k=$v";}
        $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'follow_location'=>0,'ignore_errors'=>true,'timeout'=>10]]);
        $body=(string)@file_get_contents($this->base.$path,false,$ctx);$status=0;$outHeaders=[];
        foreach($http_response_header??[] as $h){
            if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m)){$status=(int)$m[1];}
            if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$h,$m)){$this->cookies[$m[1]]=$m[2];}
            if(str_contains($h,':')){[$k,$v]=explode(':',$h,2);$outHeaders[strtolower($k)]=trim($v);}
        }
        return ['status'=>$status,'body'=>$body,'headers'=>$outHeaders];
    }
    public function csrf(string $html):string{return preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m)?$m[1]:'';}
}
$n=0;
function ah(bool $ok,string $msg):void{global $n;$n++;if(!$ok){throw new RuntimeException('FAIL: '.$msg);}}
$luis=new AgendaBrowser($base);
$r=$luis->request('GET','/admin/maestro.php');
$r=$luis->request('POST','/admin/maestro.php',['csrf'=>$luis->csrf($r['body']),'password'=>$key]);
$r=$luis->request('GET','/admin/agenda.php?mes=2026-09');
ah($r['status']===200,'Agenda abre para super');
ah(str_contains($r['body'],'Samantha')&&str_contains($r['body'],'Luciano'),'fiestas automáticas en calendario septiembre');
ah(str_contains($r['body'],'Lun')&&str_contains($r['body'],'Dom')&&str_contains($r['body'],'Hoy'),'calendario navegable con días visibles');
$new=$luis->request('GET','/admin/agenda.php?nuevo=1&fecha=2026-09-26');
$csrf=$luis->csrf($new['body']);ah($csrf!=='','crear trae CSRF');
$form=['csrf'=>$csrf,'action'=>'guardar','tipo'=>'feria','titulo'=>'Mini Feria de Emprendedores','fecha'=>'2026-09-26','hora_inicio'=>'10:00','hora_fin'=>'14:30','estado'=>'confirmada','lugar'=>'Royal Art Academy'];
$bad=$luis->request('POST','/admin/agenda.php',array_replace($form,['csrf'=>'invalido']));
ah($bad['status']===403&&(int)$pdo->query('SELECT COUNT(*) FROM cc_agenda_eventos')->fetchColumn()===0,'CSRF rechaza y no escribe');
$r=$luis->request('POST','/admin/agenda.php',$form);
ah($r['status']===303,'crear redirige tras guardar');

$badHours=$luis->request('POST','/admin/agenda.php',array_replace($form,['hora_fin'=>'09:00']));
ah($badHours['status']===400&&str_contains($badHours['body'],'id="titulo"')&&str_contains($badHours['body'],'Mini Feria de Emprendedores'),'validación conserva formulario y valores para corregir');
$eid=(int)$pdo->query("SELECT id FROM cc_agenda_eventos WHERE tipo='feria'")->fetchColumn();
ah($eid>0,'creación persiste');
$form['ref']='e:'.$eid;$form['titulo']='Feria actualizada';
$r=$luis->request('POST','/admin/agenda.php',$form);
ah($pdo->query("SELECT titulo FROM cc_agenda_eventos WHERE id=$eid")->fetchColumn()==='Feria actualizada','editar persiste');
$r=$luis->request('POST','/admin/agenda.php',array_replace($form,['ref'=>'','titulo'=>'Visita coincidente','tipo'=>'visita','hora_inicio'=>'14:00','hora_fin'=>'15:00']));
$r=$luis->request('GET','/admin/'.($r['headers']['location']??'agenda.php?mes=2026-09'));
ah(str_contains($r['body'],'Coincide'),'choque visible y guardado');
$r=$luis->request('POST','/admin/agenda.php',['csrf'=>$csrf,'action'=>'cancelar','ref'=>'e:'.$eid]);
ah($pdo->query("SELECT estado FROM cc_agenda_eventos WHERE id=$eid")->fetchColumn()==='cancelada','cancelar por POST persiste');
$r=$luis->request('POST','/admin/agenda.php',array_replace($form,['titulo'=>'<script>alert(1)</script>','estado'=>'confirmada']));
$r=$luis->request('GET','/admin/agenda.php?mes=2026-09');
ah(!str_contains($r['body'],'<script>alert(1)</script>')&&str_contains($r['body'],'&lt;script&gt;'),'título escapado');
$r=$luis->request('POST','/admin/agenda.php',array_replace($form,['titulo'=>'Mini Feria de Emprendedores']));
$r=$luis->request('GET','/admin/agenda.php?vista=proximos&desde=2026-09-24');
ah($r['status']===200&&str_contains($r['body'],'Mini Feria'),'lista próximos 30 días');
$anon=new AgendaBrowser($base);$r=$anon->request('GET','/admin/agenda.php');
ah($r['status']===302,'sin sesión vuelve al login');
$op=new AgendaBrowser($base);$r=$op->request('GET','/admin/login.php');
$r=$op->request('POST','/admin/login.php',['csrf'=>$op->csrf($r['body']),'correo'=>'operador@example.invalid','password'=>$key]);
$r=$op->request('GET','/admin/agenda.php?mes=2026-09');
ah($r['status']===403,'operador sin módulo403');
$pdo->prepare('UPDATE cc_admin_users SET modulos=? WHERE id=?')->execute(['["agenda"]',$oid]);
$r=$op->request('GET','/admin/agenda.php?mes=2026-09');
ah($r['status']===200&&str_contains($r['body'],'Samantha')&&!str_contains($r['body'],'Luciano')&&!str_contains($r['body'],'Mini Feria'),'operador con módulo solo fiesta asignada');
$sid=cb_party_db_id('samantha-hielo');$lid=cb_party_db_id('luciano-spidey');
$r=$op->request('GET','/admin/agenda.php?evento=p:'.$lid);ah($r['status']===403,'GET fiesta ajena403');
$r=$op->request('GET','/admin/agenda.php?evento=p:'.$sid);$oc=$op->csrf($r['body']);
$r=$op->request('POST','/admin/agenda.php',['csrf'=>$oc,'action'=>'guardar','ref'=>'p:'.$lid,'titulo'=>'Intento ajeno','fecha'=>'2026-09-13']);
ah($r['status']===403,'POST fiesta ajena403');
$r=$op->request('GET','/admin/agenda.php?nuevo=1');ah($r['status']===403,'operador no crea generales');
require_once $root.'/public/lib.agenda.php';
$icsPath='/admin/agenda-ics.php?u=0&f='.cb_agenda_firma(0);
$r=$anon->request('GET',$icsPath);
ah($r['status']===200&&str_contains($r['headers']['content-type']??'','text/calendar')&&str_contains($r['body'],'BEGIN:VCALENDAR'),'ICS firmado descargable sin sesión');
$r=$anon->request('GET','/admin/agenda-ics.php?f=invalido');ah($r['status']===403,'ICS sin firma válida403');
$opPath='/admin/agenda-ics.php?u='.$oid.'&f='.cb_agenda_firma($oid);
$r=$anon->request('GET',$opPath);ah($r['status']===200&&str_contains($r['body'],'Samantha')&&!str_contains($r['body'],'Luciano'),'ICS respeta alcance del operador');
$pdo->prepare('UPDATE cc_admin_users SET activo=0 WHERE id=?')->execute([$oid]);
$r=$anon->request('GET',$opPath);ah($r['status']===403,'deshabilitar operador revoca su suscripción');
$r=$luis->request('GET','/admin/agenda.php?mes=2026-99');ah($r['status']===400,'mes inválido400');

// Configuración local persistente de avisos: nunca envía al guardar.
$r=$luis->request('GET','/admin/agenda.php?mes=2026-09');
ah(str_contains($r['body'],'09:00')&&str_contains($r['body'],'08:00')&&str_contains($r['body'],'correo_admin'),'horarios y preferencias de avisos visibles para super');
$mc=$luis->csrf($r['body']);
$bad=$luis->request('POST','/admin/agenda.php',['csrf'=>'mal','action'=>'correos','correo_admin'=>'admin@example.invalid','enabled'=>'1']);
ah($bad['status']===403,'preferencias protegidas conCSRF');
$r=$luis->request('POST','/admin/agenda.php',['csrf'=>$mc,'action'=>'correos','correo_admin'=>'admin@example.invalid','enabled'=>'1']);
ah($r['status']===303&&cb_agenda_correo_config()['enabled']&&cb_agenda_correo_config()['email']==='admin@example.invalid','guardar preferencias persiste y redirige');
$r=$luis->request('POST','/admin/agenda.php',['csrf'=>$mc,'action'=>'correos','correo_admin'=>'invalido','enabled'=>'1']);
ah($r['status']===400&&cb_agenda_correo_config()['email']==='admin@example.invalid','correo inválido no reemplaza la configuración');
$pdo->prepare('UPDATE cc_admin_users SET activo=1 WHERE id=?')->execute([$oid]);
$r=$op->request('POST','/admin/agenda.php',['csrf'=>$oc,'action'=>'correos','correo_admin'=>'intruso@example.invalid','enabled'=>'1']);
ah($r['status']===403&&cb_agenda_correo_config()['email']==='admin@example.invalid','operador no cambia destinatarios ni activación');
$r=$op->request('GET','/admin/agenda.php');
ah(!str_contains($r['body'],'correo_admin')&&!str_contains($r['body'],'admin@example.invalid'),'preferencias y correo global no se exponen al operador');
echo "Agenda HTTP: OK $n comprobaciones\n";
if(in_array('--serve',$argv,true)){
    // Calendario de revisión: ningún dato sale de este servidor local.
    $today=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');
    cb_agenda_guardar(['tipo'=>'reunion','titulo'=>'Preparar próxima fiesta','fecha'=>$today,'hora_inicio'=>'17:00','hora_fin'=>'18:00','estado'=>'reservada'],cb_admin_usuario_maestro());
    echo "QA_READY $base\n"; flush(); while(($line=fgets(STDIN))!==false){if(trim($line)==='stop'){break;}}
}
