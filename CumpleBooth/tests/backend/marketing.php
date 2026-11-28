<?php
/** AT-CUMPLECLICK-017: bases y archivos de prueba desechables; ningún servicio externo. */
if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__,2);
$tmp=sys_get_temp_dir().'/cc-marketing-test-'.bin2hex(random_bytes(6));mkdir($tmp,0700,true);
$mysql=in_array('--mysql',$argv,true);$dsn='sqlite:'.$tmp.'/test.sqlite';
if($mysql){
    $server=new PDO('mysql:host=127.0.0.1;port=33387;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db='cc_marketing_test_'.bin2hex(random_bytes(6));$server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4");
    $dsn="mysql:host=127.0.0.1;port=33387;dbname=$db;charset=utf8mb4";
    register_shutdown_function(static fn()=>$server->exec("DROP DATABASE $db"));
}
foreach(['CUMPLECLICK_CONFIG_FILE'=>$tmp.'/absent.php','CC_STORAGE_MODE'=>'db','CC_PDO_DSN'=>$dsn,
    'CC_PDO_USER'=>$mysql?'root':'','CC_PDO_PASSWORD'=>'','CC_APP_HMAC_KEY'=>bin2hex(random_bytes(32)),
    'CC_PHOTO_DIR'=>$tmp.'/photos','CC_STATE_DIR'=>$tmp.'/state','CC_AJUSTES_PATH'=>$tmp.'/ajustes.json',
    'CC_SMTP_HOST'=>'','CC_PUBLIC_BASE_URL'=>'http://127.0.0.1:18917']as$k=>$v){putenv("$k=$v");}
register_shutdown_function(static function()use($tmp){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as$f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($tmp);
});
require $root.'/public/lib.php';require __DIR__.'/_migraciones.php';
$n=0;
function mk_check(bool $ok,string $message):void{global$n;$n++;if(!$ok){throw new RuntimeException('FAIL: '.$message);}}
function mk_reject(callable $fn,string $message):void{
    try{$fn();}catch(InvalidArgumentException|DomainException $e){mk_check(true,$message);return;}
    mk_check(false,$message);
}
mk_check(is_file($root.'/database/migrations/025_marketing_contenido.php'),'existe migración025');
$pdo=cb_pdo();cb_test_migrar_todo($pdo);
$up=require $root.'/database/migrations/025_marketing_contenido.php';$up($pdo);$up($pdo);
mk_check((int)$pdo->query('SELECT COUNT(*) FROM cc_marketing_piezas')->fetchColumn()===0,'migración repetible sin piezas inventadas');
require $root.'/public/lib.marketing.php';
$originalParties=(int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn();
$d=['fecha_programada'=>'2026-09-24','hora'=>'19:00','formato'=>'reel','pilar'=>'educativo','titulo'=>'Cómo empieza la fiesta',
    'gancho'=>'Una invitación para recordar','copy'=>"Una invitación para recordar.\nAgenda por WhatsApp.",
    'hashtags'=>'#CumpleClick #CumpleañosInfantiles #PhotoBoothChile','primer_comentario'=>'#AventuraAracnida','notas'=>'Prueba local'];
$p=cb_marketing_guardar($d);
mk_check($p['id']>0&&$p['estado']==='idea'&&$p['hora']==='19:00:00','alta real, estado inicial y hora normalizada');
mk_check(cb_marketing_obtener($p['id'])['titulo']===$d['titulo'],'lectura persistida');
$p=cb_marketing_guardar(['titulo'=>'La fiesta empieza aquí'],$p['id']);
mk_check($p['copy']===$d['copy']&&$p['titulo']==='La fiesta empieza aquí','edición parcial conserva texto');
$text=cb_marketing_texto_listo($p);
mk_check($text===$d['copy']."\n\n".CB_MARKETING_HASHTAGS&&!str_contains($text,'#AventuraAracnida'),'texto listo separa primer comentario');
$withTags=$p;$withTags['copy'].="\n#CumpleClick #EtiquetaExtra";$withTags['hashtags']='#NoAprobado';
$text=cb_marketing_texto_listo($withTags);
mk_check(substr_count($text,'#CumpleClick')===1&&!str_contains($text,'#EtiquetaExtra')&&!str_contains($text,'#NoAprobado'),'solo tres hashtags fijos incluso al pegar copy etiquetado');
foreach(['publicada','programada','aprobada']as$s){mk_reject(fn()=>cb_marketing_guardar(['estado'=>$s],$p['id']),'no salta aprobación desde idea');}
$p=cb_marketing_guardar(['estado'=>'lista'],$p['id']);
$p=cb_marketing_guardar(['estado'=>'aprobada'],$p['id']);
$p=cb_marketing_guardar(['estado'=>'programada'],$p['id']);
mk_check($p['estado']==='programada'&&$p['publicado_at']===null,'programar no publica ni simula fecha publicada');
mk_reject(fn()=>cb_marketing_guardar(['estado'=>'publicada'],$p['id']),'publicada requiereURL');
mk_reject(fn()=>cb_marketing_guardar(['estado'=>'publicada','publicado_url'=>'javascript:alert(1)'],$p['id']),'URL peligrosa rechazada');
$p=cb_marketing_guardar(['estado'=>'publicada','publicado_url'=>'https://www.instagram.com/p/ejemplo/'],$p['id']);
mk_check($p['publicado_at']!==null&&$p['estado']==='publicada','marca publicada con fecha real');
$publishedAt=$p['publicado_at'];
$p=cb_marketing_guardar(['metricas'=>['alcance'=>500,'guardados'=>12,'compartidos'=>4]],$p['id']);
mk_check(json_decode($p['metricas_json'],true)['alcance']===500&&$p['publicado_at']===$publishedAt,'métricas no cambian fecha de publicación');
mk_reject(fn()=>cb_marketing_guardar(['estado'=>'idea'],$p['id']),'publicada conserva historial');
mk_reject(fn()=>cb_marketing_guardar(['metricas'=>['alcance'=>-1]],$p['id']),'métrica negativa rechazada');
mk_reject(fn()=>cb_marketing_guardar(['metricas'=>['objeto'=>['x'=>1]]],$p['id']),'métricas anidadas desconocidas rechazadas');
foreach([
 ['fecha_programada'=>'2026-02-30'],['hora'=>'25:00'],['formato'=>'video'],['estado'=>'inventada'],
 ['titulo'=>''],['asset_url_externa'=>'data:text/html,mal'],['copy'=>['mal']]
]as$bad){mk_reject(fn()=>cb_marketing_guardar(array_replace($d,$bad)),'validación de campo');}
$q=cb_marketing_guardar(array_replace($d,['titulo'=>'Historia','formato'=>'historia','pilar'=>'comunidad']));
$q=cb_marketing_guardar(['estado'=>'descartada'],$q['id']);$q=cb_marketing_guardar(['estado'=>'idea'],$q['id']);
mk_check($q['estado']==='idea','recuperar descartada a idea');
mk_check(count(cb_marketing_listar('2026-09-01','2026-09-30',['estado'=>'publicada']))===1,'filtro por estado');
mk_check(count(cb_marketing_listar('2026-09-01','2026-09-30',['pilar'=>'comunidad']))===1,'filtro por pilar');
mk_reject(fn()=>cb_marketing_obtener(999999),'id ausente');
$week=cb_marketing_semana_guardar(['semana'=>'2026-09-24','seguidores'=>'100','alcance'=>'3500','guardados'=>'55','mensajes_whatsapp'=>'8','notas'=>'Revisión manual']);
mk_check($week['semana']==='2026-09-21','métricas se agrupan desde el lunes');
cb_marketing_semana_guardar(array_replace($week,['seguidores'=>105]));
$weeks=cb_marketing_semanas();
mk_check(count($weeks)===1&&(int)$weeks[0]['seguidores']===105,'semanas actualiza sin duplicar');
mk_reject(fn()=>cb_marketing_semana_guardar(array_replace($week,['seguidores'=>-2])),'seguidores negativos rechazados');
// Imagen real creada localmente; la extensión que declara el cliente no decide el tipo.
$png=$tmp.'/imagen.php';$im=imagecreatetruecolor(4,4);imagepng($im,$png);imagedestroy($im);
$asset=cb_marketing_store_file($png,false);
mk_check(str_ends_with($asset['key'],'.png')&&$asset['mime']==='image/png','tipo por bytes y extensión canónica');
$path=cb_marketing_media_path($asset['key']);
mk_check(is_file($path)&&str_starts_with($path,cb_photo_root().DIRECTORY_SEPARATOR),'raíz privada igual al Álbum');
mk_check(cb_marketing_media_path('../config.php')===null&&cb_marketing_media_path('marketing/2026/13/'.str_repeat('a',32).'.png')===null,'ruta y mes inválidos rechazados');
$p=cb_marketing_asignar_asset($p['id'],$png,false);
mk_check(is_file(cb_marketing_media_path($p['asset_key'])),'asset ligado a pieza real');
mk_reject(fn()=>cb_marketing_store_file($root.'/public/lib.php',false),'PHP renombrado no entra');
$big=$tmp.'/grande';$f=fopen($big,'w');ftruncate($f,60*1024*1024+1);fclose($f);
mk_reject(fn()=>cb_marketing_store_file($big,false),'más de60MB no entra');
// MP4 mínimo con átomos reales de dimensiones/duración, sin dependencia de ffprobe.
$box=static fn($type,$body)=>pack('N',8+strlen($body)).$type.$body;
$mvhd=str_repeat("\0",12).pack('NN',1000,1000).str_repeat("\0",80);
$tkhd=str_repeat("\0",76).pack('NN',640<<16,480<<16);
$mp4=$box('ftyp','isom'.pack('N',0).'isommp42').$box('moov',$box('mvhd',$mvhd).$box('trak',$box('tkhd',$tkhd))).$box('mdat',str_repeat("\0",20));
file_put_contents($tmp.'/video.fake',$mp4);
$video=cb_marketing_store_file($tmp.'/video.fake',false);
mk_check($video['mime']==='video/mp4'&&str_ends_with($video['key'],'.mp4'),'MP4 por bytes, no por nombre');
file_put_contents($tmp.'/truncado',$box('ftyp','isom'.pack('N',0).'isommp42'));
mk_reject(fn()=>cb_marketing_store_file($tmp.'/truncado',false),'MP4 sin metadatos legibles no entra');
// Importación idempotente y transaccional; no vuelve a pisar el trabajo editorial.
$fixture=[['fecha'=>'2026-10-01','formato'=>'post','pilar'=>'educativo','titulo'=>'Fiesta de prueba','gancho'=>'Hola','copy'=>'Texto aprobado','hashtags'=>CB_MARKETING_HASHTAGS,'primer_comentario'=>'#ReinoDeHielo','asset'=>null]];
$r=cb_marketing_seed($fixture);
$r2=cb_marketing_seed($fixture);
mk_check($r['creadas']===1&&$r2['omitidas']===1,'seed repetido no duplica');
$seeded=cb_marketing_listar('2026-10-01','2026-10-01')[0];cb_marketing_guardar(['copy'=>'Edición humana'],$seeded['id']);
cb_marketing_seed($fixture);
mk_check(cb_marketing_obtener($seeded['id'])['copy']==='Edición humana','seed no pisa edición humana');
$invalid=$fixture;$invalid[0]['titulo']='Otra';$invalid[]=['fecha'=>'invalida'];
mk_reject(fn()=>cb_marketing_seed($invalid),'JSON inválido aborta importación');
mk_check(count(cb_marketing_listar('2026-10-01','2026-10-01'))===1,'seed inválido no deja inserciones parciales');
mk_check((int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn()===$originalParties,'marketing no cambia fiestas');

// Límite exacto y conservación/limpieza al reemplazar un asset.
$exact=$tmp.'/limite.png';copy($png,$exact);$f=fopen($exact,'ab');ftruncate($f,CB_MARKETING_MAX_BYTES);fclose($f);
$maxAsset=cb_marketing_store_file($exact,false);
mk_check($maxAsset['bytes']===CB_MARKETING_MAX_BYTES,'60MB exactos aceptados');
$oldPath=cb_marketing_media_path($p['asset_key']);$p=cb_marketing_asignar_asset($p['id'],$png,false);
mk_check(!is_file($oldPath)&&is_file(cb_marketing_media_path($p['asset_key'])),'reemplazo elimina asset anterior sin referencias');
$before=$p['asset_key'];$p=cb_marketing_guardar(['asset_key'=>'../../archivo.php'],$p['id']);
mk_check($p['asset_key']===$before,'no se asigna storage key por formulario');
$tagged=$p;$tagged['copy']="Hola🕷️#AventuraAracnida, (#ReinoDeHielo) https://ejemplo.test/p#seccion";
$ready=cb_marketing_texto_listo($tagged);
mk_check(!str_contains($ready,'#AventuraAracnida')&&!str_contains($ready,'#ReinoDeHielo')&&str_contains($ready,'https://ejemplo.test/p#seccion'),'hashtags junto a emojis se separan sin romper anclasURL');
$mediaCount=static function()use($tmp):int{
    $count=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp.'/photos/marketing',FilesystemIterator::SKIP_DOTS));
    foreach($it as$f){if($f->isFile()){$count++;}}return $count;
};
$beforeCount=$mediaCount();
$withAsset=$fixture;$withAsset[0]['titulo']='Pieza a revertir';$withAsset[0]['asset']=$png;$withAsset[]=['fecha'=>'inválida'];
mk_reject(fn()=>cb_marketing_seed($withAsset),'importación con asset se revierte ante error posterior');
mk_check($mediaCount()===$beforeCount,'rollback no deja archivo importado huérfano');
$run=static function(array $args):array{
    $proc=proc_open(array_merge([PHP_BINARY],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pp);
    fclose($pp[0]);$out=stream_get_contents($pp[1]);$err=stream_get_contents($pp[2]);fclose($pp[1]);fclose($pp[2]);
    return ['code'=>proc_close($proc),'out'=>$out,'err'=>$err];
};
$cli=$run([$root.'/scripts/seed-marketing-30-dias.php','--ejemplo']);
mk_check($cli['code']===0&&str_contains($cli['out'],'Creadas: 5; omitidas: 0'),'CLI carga cinco textos reales de ejemplo');
$cli=$run([$root.'/scripts/seed-marketing-30-dias.php','--ejemplo']);
mk_check($cli['code']===0&&str_contains($cli['out'],'Creadas: 0; omitidas: 5'),'CLI repetido no duplica los cinco ejemplos');
$pdo->exec('CREATE TABLE IF NOT EXISTS cc_schema_migrations (version VARCHAR(190) NOT NULL PRIMARY KEY,applied_at DATETIME NOT NULL)');
$cli=$run([$root.'/database/aplicar-025.php']);
mk_check($cli['code']===0&&str_contains($cli['out'],'applied 025_marketing_contenido'),'ejecutor registra migración025');
$cli=$run([$root.'/database/aplicar-025.php']);
mk_check($cli['code']===0&&str_contains($cli['out'],'skip 025_marketing_contenido'),'ejecutor repetido omite migración registrada');
// Reemplazo de archivo intercalado entre lectura y UPDATE editorial.
class MarketingAssetRaceStatement extends PDOStatement{
    public static ?Closure $beforeEditorialUpdate=null;
    protected function __construct(){}
    public function execute(?array $params=null):bool{
        if(self::$beforeEditorialUpdate!==null&&str_starts_with($this->queryString,'UPDATE cc_marketing_piezas SET fecha_programada=')){
            $callback=self::$beforeEditorialUpdate;self::$beforeEditorialUpdate=null;$callback();
        }
        return parent::execute($params);
    }
}
$racePiece=cb_marketing_guardar(['fecha_programada'=>'2026-10-02','titulo'=>'Edición y archivo concurrentes','pilar'=>'educativo','copy'=>'Texto inicial']);
$racePiece=cb_marketing_asignar_asset($racePiece['id'],$png,false);
$raceOldPath=cb_marketing_media_path($racePiece['asset_key']);$raceUploaded=null;
$racePreviousClass=$pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS);
try{
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS,[MarketingAssetRaceStatement::class]);
    MarketingAssetRaceStatement::$beforeEditorialUpdate=static function()use($racePiece,$png,&$raceUploaded):void{
        $raceUploaded=cb_marketing_asignar_asset($racePiece['id'],$png,false);
    };
    $raceSaved=cb_marketing_guardar(['copy'=>'Texto editado durante el reemplazo'],$racePiece['id']);
}finally{
    MarketingAssetRaceStatement::$beforeEditorialUpdate=null;$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS,$racePreviousClass);
}
mk_check($raceUploaded!==null,'reemplazo intercalado ejecutado');
mk_check($raceSaved['copy']==='Texto editado durante el reemplazo','edición persiste durante reemplazo');
mk_check($raceSaved['asset_key']===$raceUploaded['asset_key']&&cb_marketing_obtener($racePiece['id'])['asset_key']===$raceUploaded['asset_key'],'edición conserva asset recién reemplazado');
mk_check(is_file(cb_marketing_media_path($raceSaved['asset_key']))&&!is_file($raceOldPath),'referencia existe y asset anterior eliminado');
$down=require $root.'/database/migrations/025_marketing_contenido.down.php';$down($pdo);$down($pdo);$up($pdo);
mk_check((int)$pdo->query('SELECT COUNT(*) FROM cc_marketing_piezas')->fetchColumn()===0,'rollback repetible y reaplicar');
echo 'Marketing '.($mysql?'MySQL':'SQLite').": OK $n comprobaciones\n";
