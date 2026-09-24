<?php
/** Contenido por HTTP con SQLite temporal. --serve mantiene el sitio de QA hasta leer stop. */
if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/cc-marketing-http-'.bin2hex(random_bytes(5));mkdir($tmp,0700,true);
$base='http://127.0.0.1:'.(getenv('CC_QA_PORT')?:random_int(19000,22000));$key=getenv('CC_QA_PASSWORD')?:bin2hex(random_bytes(16));
$env=array_merge(getenv(),['CC_STORAGE_MODE'=>'db','CC_PDO_DSN'=>'sqlite:'.$tmp.'/test.sqlite','CC_PDO_USER'=>'','CC_PDO_PASSWORD'=>'',
    'CUMPLECLICK_CONFIG_FILE'=>$tmp.'/absent.php','CC_APP_HMAC_KEY'=>bin2hex(random_bytes(32)),'CC_ADMIN_PASSWORD_HASH'=>password_hash($key,PASSWORD_DEFAULT),
    'CC_PUBLIC_BASE_URL'=>$base,'CC_PHOTO_DIR'=>$tmp.'/photos','CC_STATE_DIR'=>$tmp.'/state','CC_AJUSTES_PATH'=>$tmp.'/ajustes.json','CC_SMTP_HOST'=>'']);
foreach($env as$k=>$v){putenv("$k=$v");}
require $root.'/public/lib.marketing.php';require __DIR__.'/_migraciones.php';cb_test_migrar_todo(cb_pdo());
$pdo=cb_pdo();require_once $root.'/public/lib.admin-usuarios.php';
$now=gmdate('Y-m-d H:i:s');$stmt=$pdo->prepare("INSERT INTO cc_admin_users(email,nombre,password_hash,rol,activo,debe_cambiar,modulos,created_at,updated_at) VALUES (?,?,?,'operador',1,0,?,?,?)");
$stmt->execute(['operador@example.invalid','Operador QA',password_hash($key,PASSWORD_DEFAULT),'["marketing"]',$now,$now]);$uid=(int)$pdo->lastInsertId();
mkdir($tmp.'/sessions',0700);
$server=proc_open([PHP_BINARY,'-d','session.save_path='.$tmp.'/sessions','-d','upload_max_filesize=60M','-d','post_max_size=64M','-S',substr($base,7),'-t',$root.'/public'],
    [0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,$root.'/public',$env);
register_shutdown_function(static function()use($server,$tmp){
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as$f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($tmp);
});
for($i=0;$i<50;$i++){$s=@fsockopen('127.0.0.1',(int)parse_url($base,PHP_URL_PORT),$eno,$err,.1);if($s){fclose($s);break;}usleep(100000);}
class MarketingBrowser{
    private array $cookies=[];
    public function __construct(private string $base){}
    public function request(string $method,string $path,?array $data=null,?array $file=null):array{
        $headers=[];$body=$data===null?'':http_build_query($data);
        if($file!==null){
            $boundary='cc'.bin2hex(random_bytes(12));$body='';
            foreach($data??[]as$k=>$v){$body.="--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";}
            $body.="--$boundary\r\nContent-Disposition: form-data; name=\"asset\"; filename=\"cliente.php\"\r\nContent-Type: application/x-php\r\n\r\n".$file['bytes']."\r\n--$boundary--\r\n";
            $headers[]='Content-Type: multipart/form-data; boundary='.$boundary;
        }elseif($data!==null){$headers[]='Content-Type: application/x-www-form-urlencoded';}
        foreach($this->cookies as$k=>$v){$headers[]="Cookie: $k=$v";}
        $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'follow_location'=>0,'ignore_errors'=>true,'timeout'=>10]]);
        $body=(string)@file_get_contents($this->base.$path,false,$ctx);$status=0;$out=[];
        foreach($http_response_header??[]as$h){
            if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m)){$status=(int)$m[1];}
            if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$h,$m)){$this->cookies[$m[1]]=$m[2];}
            if(str_contains($h,':')){[$k,$v]=explode(':',$h,2);$out[strtolower($k)]=trim($v);}
        }
        return ['status'=>$status,'body'=>$body,'headers'=>$out];
    }
    public function csrf(string $html):string{return preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m)?$m[1]:'';}
}
$n=0;function mh(bool $ok,string $msg):void{global$n;$n++;if(!$ok){throw new RuntimeException('FAIL: '.$msg);}}
$admin=new MarketingBrowser($base);$r=$admin->request('GET','/admin/maestro.php');
$admin->request('POST','/admin/maestro.php',['csrf'=>$admin->csrf($r['body']),'password'=>$key]);
$r=$admin->request('GET','/admin/contenido.php?mes=2026-09');mh($r['status']===200,'Contenido abre para super');
mh(str_contains($r['body'],'Calendario')&&str_contains($r['body'],'Semana')&&str_contains($r['body'],'Nueva pieza'),'navegación principal');

$r=$admin->request('GET','/admin/contenido.php?vista=semana&semana=2026-09-21');
mh($r['status']===200&&str_contains($r['body'],'Aún no hay métricas'),'semana vacía abre sin datos');
$r=$admin->request('GET','/admin/contenido.php?nueva=1'); $csrf=$admin->csrf($r['body']);
$form=['csrf'=>$csrf,'action'=>'guardar','fecha_programada'=>'2026-09-24','hora'=>'19:00','formato'=>'post','pilar'=>'educativo','titulo'=>'El Álbum Recuerdo','gancho'=>'Los recuerdos siguen','copy'=>'Gracias por compartir la fiesta.','primer_comentario'=>'#AventuraAracnida','estado'=>'idea'];
$r=$admin->request('POST','/admin/contenido.php',array_replace($form,['csrf'=>'malo']));
mh($r['status']===403&&(int)$pdo->query('SELECT COUNT(*) FROM cc_marketing_piezas')->fetchColumn()===0,'CSRF no escribe');
$r=$admin->request('POST','/admin/contenido.php',$form);
mh($r['status']===303,'crear redirige');
$id=(int)$pdo->query('SELECT id FROM cc_marketing_piezas')->fetchColumn();mh($id>0,'crear persiste');
$r=$admin->request('POST','/admin/contenido.php',array_replace($form,['id'=>$id,'titulo'=>'Álbum listo para recordar']));
mh($r['status']===303&&cb_marketing_obtener($id)['titulo']==='Álbum listo para recordar','editar persiste');
$r=$admin->request('POST','/admin/contenido.php',array_replace($form,['id'=>$id,'fecha_programada'=>'2026-02-30']));
mh($r['status']===400&&str_contains($r['body'],'name="titulo"'),'error permite corregir el formulario');
$r=$admin->request('GET','/admin/contenido.php?id='.$id);
mh(str_contains($r['body'],'Copiar texto')&&str_contains($r['body'],CB_MARKETING_HASHTAGS),'texto listo y primer comentario disponibles');

$r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'guardar','id'=>$id,'asset_url_externa'=>'javascript://ejemplo']);
mh($r['status']===400&&!str_contains($r['body'],'href="javascript:'),'URL rechazada no queda como enlace en el formulario con errores');
$im=imagecreatetruecolor(8,8);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
$r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'asset','id'=>$id],['bytes'=>$png]);
mh($r['status']===303,'subir PNG con extensión clientePHP se valida por bytes');
$p=cb_marketing_obtener($id);mh(str_ends_with($p['asset_key'],'.png'),'extensión del almacenamiento es canónica');
$r=$admin->request('GET','/admin/contenido-media.php?id='.$id);
mh($r['status']===200&&$r['body']===$png&&str_contains($r['headers']['content-type']??'','image/png'),'media con sesión entrega bytes correctos');
mh(str_contains($r['headers']['cache-control']??'','private')&&str_contains($r['headers']['content-disposition']??'','inline'),'cabeceras privadas e inline');
$anon=new MarketingBrowser($base);$r=$anon->request('GET','/admin/contenido-media.php?id='.$id);mh($r['status']===403,'media sin sesión403');
$r=$anon->request('GET','/admin/contenido.php');mh($r['status']===302,'página sin sesión al login');
$op=new MarketingBrowser($base);$r=$op->request('GET','/admin/login.php');
$op->request('POST','/admin/login.php',['csrf'=>$op->csrf($r['body']),'correo'=>'operador@example.invalid','password'=>$key]);
$r=$op->request('GET','/admin/contenido.php');mh($r['status']===403,'operador aunque tenga marketing no accede');
$r=$op->request('GET','/admin/contenido-media.php?id='.$id);mh($r['status']===403,'operador no descarga assets');
$r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'asset','id'=>$id],['bytes'=>'<?php echo "invalido";']);
mh($r['status']===400&&cb_marketing_obtener($id)['asset_key']===$p['asset_key'],'archivo inválido no reemplaza el bueno');
foreach(['lista','aprobada','programada']as$state){
    $r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'estado','id'=>$id,'estado'=>$state]);
    mh($r['status']===303&&cb_marketing_obtener($id)['estado']===$state,'transición HTTP a'.$state);
}
$r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'publicar','id'=>$id,'publicado_url'=>'https://www.instagram.com/p/ejemplo/']);
mh($r['status']===303&&cb_marketing_obtener($id)['estado']==='publicada','marcar publicada con URL');
$r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'metricas','id'=>$id,'metricas'=>['alcance'=>'700','guardados'=>'15']]);
mh($r['status']===303&&json_decode(cb_marketing_obtener($id)['metricas_json'],true)['guardados']===15,'métricas de pieza porHTTP');
$r=$admin->request('POST','/admin/contenido.php',['csrf'=>$csrf,'action'=>'semana','semana'=>'2026-09-21','seguidores'=>'100','alcance'=>'3000','guardados'=>'60','mensajes_whatsapp'=>'8','notas'=>'Revisión local']);
mh($r['status']===303&&count(cb_marketing_semanas())===1,'métricas semanales porHTTP');
$r=$admin->request('GET','/admin/contenido.php?vista=semana&semana=2026-09-21');
mh($r['status']===200&&str_contains($r['body'],'3000'),'vista semanal con gráfico y valores');
$r=$admin->request('GET','/admin/contenido.php?mes=2026-09&estado=idea');
mh(!str_contains($r['body'],'Álbum listo para recordar'),'filtro de estado aplicado');
$r=$admin->request('GET','/admin/contenido.php?mes=2026-99');mh($r['status']===400,'mes inválido400');
$r=$admin->request('GET','/admin/contenido-media.php?id[]=1');mh($r['status']===404,'ID de media mal formado404');
$r=$admin->request('GET','/admin/contenido-media.php?id=999999');
mh($r['status']===404,'asset inexistente con sesión404');
$pdo->prepare("UPDATE cc_admin_users SET rol='super' WHERE id=?")->execute([$uid]);
$r=$op->request('GET','/admin/contenido-media.php?id='.$id);mh($r['status']===200,'rol super vigente permite asset');
$pdo->prepare('UPDATE cc_admin_users SET activo=0 WHERE id=?')->execute([$uid]);
$r=$op->request('GET','/admin/contenido-media.php?id='.$id);mh($r['status']===403,'desactivar usuario revoca acceso al asset en siguiente petición');
$pdo->prepare('UPDATE cc_admin_users SET activo=1,debe_cambiar=1 WHERE id=?')->execute([$uid]);
$r=$op->request('GET','/admin/contenido-media.php?id='.$id);mh($r['status']===403,'contraseña temporal impide descargar asset');
$pdo->prepare("UPDATE cc_admin_users SET debe_cambiar=0,rol='operador' WHERE id=?")->execute([$uid]);
$r=$op->request('GET','/admin/contenido-media.php?id='.$id);mh($r['status']===403,'revocar rol super bloquea siguiente descarga');
$xss=$admin->request('POST','/admin/contenido.php',array_replace($form,['titulo'=>'<img src=x onerror=alert(1)>']));
$xssId=(int)$pdo->query('SELECT MAX(id) FROM cc_marketing_piezas')->fetchColumn();
$r=$admin->request('GET','/admin/contenido.php?mes=2026-09');
mh($xss['status']===303&&!str_contains($r['body'],'<img src=x')&&str_contains($r['body'],'&lt;img src=x'),'título no ejecuta HTML en calendario');
$pdo->prepare('DELETE FROM cc_marketing_piezas WHERE id=?')->execute([$xssId]);
// La semana elegida debe poder editarse aunque quede fuera de las 52 del gráfico.
for($w=1;$w<=52;$w++){
    cb_marketing_semana_guardar(['semana'=>(new DateTimeImmutable('2026-09-21'))->modify("+$w weeks")->format('Y-m-d'),'seguidores'=>200+$w]);
}
$r=$admin->request('GET','/admin/contenido.php?vista=semana&semana=2026-09-21');
mh($r['status']===200&&preg_match('/id="sem_seguidores"[^>]*value="100"/',$r['body'])===1,'semana antigua conserva métricas fuera del límite del gráfico');
$pdo->exec("DELETE FROM cc_marketing_semanas WHERE semana>'2026-09-21'");
echo "Marketing HTTP: OK $n comprobaciones\n";
if(in_array('--serve',$argv,true)){
    cb_marketing_asignar_asset($id,$root.'/design/logo/logo-transparent.png',false);
    $fixture=$root.'/design/generadores/arte/marketing-ejemplos.json';
    if(is_file($fixture)){cb_marketing_seed(json_decode(file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR),dirname($fixture));}
    echo "QA_READY $base\n";flush();while(($line=fgets(STDIN))!==false){if(trim($line)==='stop'){break;}}
}
