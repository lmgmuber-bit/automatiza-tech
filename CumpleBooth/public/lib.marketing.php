<?php
/** Contenido editorial; no publica en redes ni modifica fiestas. */
require_once __DIR__.'/lib.php';
const CB_MARKETING_HASHTAGS='#CumpleClick #CumpleañosInfantiles #PhotoBoothChile';
const CB_MARKETING_MAX_BYTES=60*1024*1024;
function cb_marketing_formatos():array{return ['reel'=>'Reel','post'=>'Post','carrusel'=>'Carrusel','historia'=>'Historia'];}
function cb_marketing_estados():array{return ['idea'=>'Idea','lista'=>'Lista','aprobada'=>'Aprobada','programada'=>'Programada','publicada'=>'Publicada','descartada'=>'Descartada'];}
function cb_marketing_transiciones(string $estado):array{
    return ['idea'=>['lista','descartada'],'lista'=>['idea','aprobada','descartada'],
        'aprobada'=>['lista','programada','publicada','descartada'],'programada'=>['aprobada','publicada','descartada'],
        'publicada'=>[],'descartada'=>['idea']][$estado]??[];
}
function cb_marketing_fecha(string $value):bool{
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $d!==false&&$d->format('Y-m-d')===$value&&substr($value,0,4)>='1000';
}
function cb_marketing_url($value):?string{
    if($value===null||$value===''){return null;}
    if(!is_string($value)||strlen($value)>2048||!filter_var($value,FILTER_VALIDATE_URL)
        ||!in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true)
        ||parse_url($value,PHP_URL_USER)!==null||parse_url($value,PHP_URL_PASS)!==null){
        throw new InvalidArgumentException('Usa un enlace completo http o https, sin credenciales.');
    }
    return $value;
}
function cb_marketing_texto_listo(array $pieza):string{
    $copy=trim((string)($pieza['copy']??''));
    // Los hashtags de la temática se guardan aparte, en primer_comentario.
    $parts=preg_split('#(https?://[^\\s<>]+)#iu',$copy,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($parts as$i=>$part){
        if(!preg_match('#^https?://#i',$part)){$parts[$i]=preg_replace('/#[\\p{L}\\p{M}\\p{N}_]+/u','',$part);}
    }
    $copy=implode('',$parts);
    $copy=preg_replace('/[ \t]+\n/u',"\n",(string)$copy);
    return trim((string)$copy)."\n\n".CB_MARKETING_HASHTAGS;
}
function cb_marketing_obtener(int $id):array{
    $s=cb_pdo()->prepare('SELECT * FROM cc_marketing_piezas WHERE id=?');$s->execute([$id]);
    $p=$s->fetch(PDO::FETCH_ASSOC);$s->closeCursor();
    if(!$p){throw new InvalidArgumentException('La pieza no existe.');}
    $p['id']=(int)$p['id'];
    if($p['hora']!==null){$p['hora']=substr($p['hora'],0,5).':00';}
    return $p;
}
function cb_marketing_listar(string $desde,string $hasta,array $filtros=[]):array{
    if(!cb_marketing_fecha($desde)||!cb_marketing_fecha($hasta)||$hasta<$desde){throw new InvalidArgumentException('Rango de fechas inválido.');}
    $sql='SELECT * FROM cc_marketing_piezas WHERE fecha_programada BETWEEN ? AND ?';$args=[$desde,$hasta];
    foreach(['estado','pilar']as$key){
        if(!empty($filtros[$key])){
            if(!is_string($filtros[$key])){throw new InvalidArgumentException('Filtro inválido.');}
            $sql.=" AND $key=?";$args[]=$filtros[$key];
        }
    }
    $s=cb_pdo()->prepare($sql.' ORDER BY fecha_programada,hora,id');$s->execute($args);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function cb_marketing_pilares():array{return cb_pdo()->query('SELECT DISTINCT pilar FROM cc_marketing_piezas ORDER BY pilar')->fetchAll(PDO::FETCH_COLUMN);}
function cb_marketing_numero($value):int{
    if((!is_int($value)&&!is_string($value))||!preg_match('/^[0-9]{1,10}$/D',(string)$value)||(int)$value>2147483647){
        throw new InvalidArgumentException('Las métricas deben ser números enteros entre 0 y 2147483647.');
    }
    return (int)$value;
}
function cb_marketing_metricas():array{return ['alcance'=>'Alcance','guardados'=>'Guardados','compartidos'=>'Compartidos','me_gusta'=>'Me gusta','comentarios'=>'Comentarios','clics_enlace'=>'Clics al enlace','mensajes_whatsapp'=>'Conversaciones por WhatsApp'];}
function cb_marketing_guardar(array $input,?int $id=null):array{
    $old=$id!==null?cb_marketing_obtener($id):[];
    $d=array_replace(['fecha_programada'=>'','hora'=>null,'formato'=>'post','pilar'=>'','titulo'=>'','gancho'=>'','copy'=>'',
        'primer_comentario'=>'','asset_url_externa'=>null,'estado'=>'idea','publicado_url'=>null,'notas'=>''],$old,$input);
    foreach(['fecha_programada'=>10,'formato'=>16,'pilar'=>80,'titulo'=>160,'gancho'=>500,'copy'=>20000,'primer_comentario'=>2200,'estado'=>16,'notas'=>10000]as$key=>$max){
        if(!is_string($d[$key])||mb_strlen($d[$key])>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$d[$key])){throw new InvalidArgumentException('Revisa el campo '.$key.'.');}
        $d[$key]=trim($d[$key]);
    }
    if($d['titulo']===''||$d['pilar']===''||!cb_marketing_fecha($d['fecha_programada'])
        ||!isset(cb_marketing_formatos()[$d['formato']])||!isset(cb_marketing_estados()[$d['estado']])){
        throw new InvalidArgumentException('Completa título, pilar, fecha, formato y estado válidos.');
    }
    if($d['hora']===''||$d['hora']===null){$d['hora']=null;}
    elseif(is_string($d['hora'])&&preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D',$d['hora'])){$d['hora']=substr($d['hora'],0,5).':00';}
    else{throw new InvalidArgumentException('Hora inválida.');}
    $from=$old['estado']??'idea';
    if($d['estado']!==$from&&!in_array($d['estado'],cb_marketing_transiciones($from),true)){throw new DomainException('Ese cambio de estado no está permitido.');}
    if(in_array($d['estado'],['lista','aprobada','programada','publicada'],true)&&$d['copy']===''){throw new InvalidArgumentException('Escribe el texto antes de avanzar la pieza.');}
    if($d['estado']==='programada'&&$d['hora']===null){throw new InvalidArgumentException('Indica la hora antes de marcar programada.');}
    $d['asset_url_externa']=cb_marketing_url($d['asset_url_externa']);$d['publicado_url']=cb_marketing_url($d['publicado_url']);
    if($d['estado']==='publicada'&&$d['publicado_url']===null){throw new InvalidArgumentException('Pega la URL de la publicación.');}
    $metrics=$old['metricas_json']??null;
    if(array_key_exists('metricas',$input)){
        if(!is_array($input['metricas'])||array_diff(array_keys($input['metricas']),array_keys(cb_marketing_metricas()))){throw new InvalidArgumentException('Métricas inválidas.');}
        $clean=[];foreach($input['metricas']as$key=>$v){if($v!==''&&$v!==null){$clean[$key]=cb_marketing_numero($v);}}
        $metrics=json_encode($clean,JSON_THROW_ON_ERROR);
    }
    $d['metricas_json']=$metrics;$d['hashtags']=CB_MARKETING_HASHTAGS;
    $d['asset_key']=$old['asset_key']??null; // solo lo asigna el almacén validado
    $d['publicado_at']=$old['publicado_at']??($d['estado']==='publicada'?gmdate('Y-m-d H:i:s'):null);
    $keys=['fecha_programada','hora','formato','pilar','titulo','gancho','copy','hashtags','primer_comentario','asset_key','asset_url_externa','estado','publicado_url','publicado_at','metricas_json','notas'];
    if($id!==null){$keys=array_values(array_diff($keys,['asset_key']));}
    $vals=array_map(static fn($k)=>$d[$k],$keys);$now=gmdate('Y-m-d H:i:s');$pdo=cb_pdo();
    try{
        if($id===null){
            $pdo->prepare('INSERT INTO cc_marketing_piezas ('.implode(',',$keys).',created_at,updated_at) VALUES ('.implode(',',array_fill(0,count($keys)+2,'?')).')')->execute(array_merge($vals,[$now,$now]));
            $id=(int)$pdo->lastInsertId();
        }else{
            $s=$pdo->prepare('UPDATE cc_marketing_piezas SET '.implode(',',array_map(static fn($k)=>"$k=?",$keys)).',updated_at=? WHERE id=? AND estado=?');
            $s->execute(array_merge($vals,[$now,$id,$from]));
            if($s->rowCount()===0&&cb_marketing_obtener($id)['estado']!==$from){throw new DomainException('La pieza cambió en otra sesión. Recarga antes de guardar.');}
        }
    }catch(PDOException $e){
        if(in_array((string)$e->getCode(),['23000','23505'],true)){throw new InvalidArgumentException('Ya hay una pieza con esa fecha y título.');}
        throw $e;
    }
    return cb_marketing_obtener($id);
}
function cb_marketing_semana_guardar(array $d):array{
    $day=$d['semana']??'';
    if(!is_string($day)||!cb_marketing_fecha($day)){throw new InvalidArgumentException('Semana inválida.');}
    $week=(new DateTimeImmutable($day))->modify('monday this week')->format('Y-m-d');
    $notes=$d['notas']??'';
    if(!is_string($notes)||mb_strlen($notes)>10000){throw new InvalidArgumentException('Notas inválidas.');}
    $keys=['seguidores','alcance','guardados','mensajes_whatsapp'];
    $values=array_map(static fn($k)=>cb_marketing_numero($d[$k]??0),$keys);$values[]=trim($notes);$values[]=$week;
    $pdo=cb_pdo();$s=$pdo->prepare('SELECT 1 FROM cc_marketing_semanas WHERE semana=?');$s->execute([$week]);$exists=(bool)$s->fetchColumn();$s->closeCursor();
    $pdo->prepare($exists?'UPDATE cc_marketing_semanas SET seguidores=?,alcance=?,guardados=?,mensajes_whatsapp=?,notas=? WHERE semana=?':
        'INSERT INTO cc_marketing_semanas (seguidores,alcance,guardados,mensajes_whatsapp,notas,semana) VALUES (?,?,?,?,?,?)')->execute($values);
    return array_combine(array_merge($keys,['notas','semana']),$values);
}
function cb_marketing_semana_obtener(string $day):array{
    if(!cb_marketing_fecha($day)){throw new InvalidArgumentException('Semana inválida.');}
    $week=(new DateTimeImmutable($day))->modify('monday this week')->format('Y-m-d');
    $s=cb_pdo()->prepare('SELECT * FROM cc_marketing_semanas WHERE semana=?');$s->execute([$week]);
    $row=$s->fetch(PDO::FETCH_ASSOC);$s->closeCursor();
    return $row?:['semana'=>$week,'seguidores'=>0,'alcance'=>0,'guardados'=>0,'mensajes_whatsapp'=>0,'notas'=>''];
}
function cb_marketing_semanas():array{return cb_pdo()->query('SELECT * FROM cc_marketing_semanas ORDER BY semana DESC LIMIT 52')->fetchAll(PDO::FETCH_ASSOC);}
function cb_marketing_media_path(string $key):?string{
    if(!preg_match('#^marketing/[1-9][0-9]{3}/(?:0[1-9]|1[0-2])/[a-f0-9]{32}\.(?:jpg|png|webp|mp4)$#D',$key)){return null;}
    return cb_photo_root().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);
}
function cb_marketing_store_file(string $source,bool $uploaded):array{
    clearstatcache(true,$source);$size=is_file($source)?filesize($source):false;
    if($size===false||$size<1||$size>CB_MARKETING_MAX_BYTES){throw new InvalidArgumentException('El archivo debe pesar entre 1 byte y 60 MB.');}
    if($uploaded&&!is_uploaded_file($source)){throw new InvalidArgumentException('Subida inválida.');}
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($source);$formats=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4'];
    if(!isset($formats[$mime])){throw new InvalidArgumentException('Sube una imagen JPG, PNG, WebP o un video MP4.');}
    if($mime==='video/mp4'){
        $video=cb_album_probe_mp4($source);
        if(!$video||$video['width']<=0||$video['height']<=0){throw new InvalidArgumentException('El MP4 no contiene un video legible.');}
    }else{
        $info=@getimagesize($source);
        if(!$info||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1){throw new InvalidArgumentException('La imagen no es válida.');}
    }
    $key='marketing/'.gmdate('Y/m').'/'.bin2hex(random_bytes(16)).'.'.$formats[$mime];
    $path=cb_marketing_media_path($key);$dir=dirname($path);
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){throw new RuntimeException('No se pudo preparar el almacén.');}
    $staging=$path.'.tmp.'.bin2hex(random_bytes(4));
    $ok=$uploaded?move_uploaded_file($source,$staging):copy($source,$staging);
    if(!$ok||!rename($staging,$path)){@unlink($staging);throw new RuntimeException('No se pudo guardar el archivo.');}
    @chmod($path,0660);
    return ['key'=>$key,'mime'=>$mime,'bytes'=>$size];
}
function cb_marketing_asignar_asset(int $id,string $source,bool $uploaded):array{
    $old=cb_marketing_obtener($id);$pdo=cb_pdo();$asset=cb_marketing_store_file($source,$uploaded);
    try{$pdo->prepare('UPDATE cc_marketing_piezas SET asset_key=?,updated_at=? WHERE id=?')->execute([$asset['key'],gmdate('Y-m-d H:i:s'),$id]);}
    catch(Throwable $e){@unlink(cb_marketing_media_path($asset['key']));throw $e;}
    // No borrar antes de un commit externo: un rollback podría recuperar la referencia.
    if(!$pdo->inTransaction()&&!empty($old['asset_key'])){
        $s=$pdo->prepare('SELECT COUNT(*) FROM cc_marketing_piezas WHERE asset_key=?');$s->execute([$old['asset_key']]);
        $referenced=(int)$s->fetchColumn();$s->closeCursor();$oldPath=cb_marketing_media_path($old['asset_key']);
        if($referenced===0&&$oldPath!==null){@unlink($oldPath);}
    }
    return cb_marketing_obtener($id);
}
/** Idempotencia fecha+título: las piezas existentes conservan la edición humana. */
function cb_marketing_seed(array $rows,?string $assetBase=null):array{
    if(!array_is_list($rows)||count($rows)>1000){throw new InvalidArgumentException('El JSON debe contener una lista de hasta 1000 piezas.');}
    $pdo=cb_pdo();$pdo->beginTransaction();$files=[];$result=['creadas'=>0,'omitidas'=>0];
    try{
        foreach($rows as$r){
            if(!is_array($r)||!is_string($r['fecha']??null)||!cb_marketing_fecha($r['fecha'])||!is_string($r['titulo']??null)||trim($r['titulo'])===''){throw new InvalidArgumentException('Pieza de importación inválida.');}
            $s=$pdo->prepare('SELECT id FROM cc_marketing_piezas WHERE fecha_programada=? AND titulo=?');$s->execute([$r['fecha'],trim($r['titulo'])]);$exists=$s->fetchColumn();$s->closeCursor();
            if($exists){$result['omitidas']++;continue;}
            // Una importación nunca aprueba ni declara publicada una pieza.
            $r['fecha_programada']=$r['fecha'];$r['estado']='idea';unset($r['publicado_url'],$r['publicado_at'],$r['asset_key']);
            $asset=$r['asset']??null;
            if($asset!==null&&!is_string($asset)){throw new InvalidArgumentException('Asset inválido.');}
            if(is_string($asset)&&preg_match('#^https?://#i',$asset)){$r['asset_url_externa']=cb_marketing_url($asset);$asset=null;}
            $p=cb_marketing_guardar($r);
            if($asset!==null&&$asset!==''){
                $path=preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#',$asset)?$asset:rtrim($assetBase??getcwd(),'/\\').DIRECTORY_SEPARATOR.$asset;
                $p=cb_marketing_asignar_asset($p['id'],$path,false);$files[]=$p['asset_key'];
            }
            $result['creadas']++;
        }
        $pdo->commit();return $result;
    }catch(Throwable $e){
        if($pdo->inTransaction()){$pdo->rollBack();}
        foreach($files as$key){$path=cb_marketing_media_path($key);if($path!==null){@unlink($path);}}
        throw $e;
    }
}
