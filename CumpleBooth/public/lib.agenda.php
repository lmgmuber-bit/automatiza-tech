<?php
/** Agenda operativa. Leer nunca crea logística ni cambia fiestas, pagos o checklist. */
require_once __DIR__.'/lib.php';
require_once __DIR__.'/lib.admin-usuarios.php';
require_once __DIR__.'/lib.cliente.php';
require_once __DIR__.'/lib.acceptance.php';
require_once __DIR__.'/lib.envios.php';

function cb_agenda_tipos(): array {
    return ['fiesta'=>'Fiesta','visita'=>'Visita','reunion'=>'Reunión','feria'=>'Feria','entrega'=>'Entrega','bloqueo'=>'Día bloqueado'];
}
function cb_agenda_estados(): array {
    return ['consulta'=>'Consulta','reservada'=>'Reservada','confirmada'=>'Confirmada','realizada'=>'Realizada','cancelada'=>'Cancelada'];
}
function cb_agenda_fecha(string $value): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value && substr($value,0,4)>='1000';
}
function cb_agenda_exigir(array $u, ?array $evento = null): void {
    if (!cb_admin_usuario_puede($u,'agenda') ||
        ($evento !== null && $u['rol'] !== 'super' &&
         (empty($evento['public_slug']) || !cb_admin_usuario_ve_fiesta($u,$evento['public_slug'])))) {
        throw new DomainException('No tienes acceso a ese evento.');
    }
}
function cb_agenda_select_fiestas(): string {
    return "SELECT a.*, p.id AS party_id, p.public_slug, p.birthday_person_name,
        p.admin_label, p.theme_slug, p.service_plan, p.event_type, p.active,
        p.event_date AS fecha_fiesta, p.updated_at AS fiesta_updated_at,
        p.event_date AS fecha
        FROM cc_parties p LEFT JOIN cc_agenda_eventos a ON a.party_id = p.id";
}
function cb_agenda_normalizar(array $r): array {
    $party = !empty($r['party_id']);
    return array_replace([
        'id'=>null,'party_id'=>null,'tipo'=>'fiesta','titulo'=>'','fecha'=>'',
        'hora_inicio'=>null,'hora_fin'=>null,'hora_montaje'=>null,'lugar'=>'',
        'contacto_nombre'=>'','contacto_telefono'=>'','estado'=>'consulta','notas'=>'',
        'created_at'=>'','updated_at'=>'','public_slug'=>'','theme_slug'=>'','service_plan'=>'',
        'birthday_person_name'=>'','event_type'=>'','active'=>null,
    ], array_filter($r, static fn($v)=>$v!==null), [
        'ref'=>($party?'p:'.$r['party_id']:'e:'.$r['id']),
        'titulo'=>(string)($r['titulo'] ?: ($r['admin_label'] ?? '') ?: ($r['birthday_person_name'] ?? 'Evento')),
        'updated_at'=>(string)($r['updated_at'] ?: ($r['fiesta_updated_at'] ?? '')),
    ]);
}
function cb_agenda_listar(string $desde, string $hasta, array $u): array {
    cb_agenda_exigir($u);
    if (!cb_agenda_fecha($desde) || !cb_agenda_fecha($hasta) || $hasta<$desde) {
        throw new InvalidArgumentException('El rango de fechas no es válido.');
    }
    $s = cb_pdo()->prepare(cb_agenda_select_fiestas().' WHERE p.event_date BETWEEN ? AND ?');
    $s->execute([$desde,$hasta]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    if ($u['rol']==='super') {
        $s=cb_pdo()->prepare('SELECT * FROM cc_agenda_eventos WHERE party_id IS NULL AND fecha BETWEEN ? AND ?');
        $s->execute([$desde,$hasta]); $rows=array_merge($rows,$s->fetchAll(PDO::FETCH_ASSOC));
    }
    $out=[];
    foreach ($rows as $r) {
        $e=cb_agenda_normalizar($r);
        if ($u['rol']==='super' || cb_admin_usuario_ve_fiesta($u,$e['public_slug'])) { $out[]=$e; }
    }
    usort($out, static fn($a,$b)=>[$a['fecha'],$a['hora_inicio']??'', $a['titulo'],$a['ref']]<=>[$b['fecha'],$b['hora_inicio']??'',$b['titulo'],$b['ref']]);
    return $out;
}
function cb_agenda_obtener(string $ref, array $u): array {
    cb_agenda_exigir($u);
    if (!preg_match('/^([pe]):([1-9][0-9]*)$/D',$ref,$m)) { throw new InvalidArgumentException('Evento inválido.'); }
    $sql=$m[1]==='p' ? cb_agenda_select_fiestas().' WHERE p.id = ?' : 'SELECT * FROM cc_agenda_eventos WHERE id = ? AND party_id IS NULL';
    $s=cb_pdo()->prepare($sql); $s->execute([(int)$m[2]]); $r=$s->fetch(PDO::FETCH_ASSOC); $s->closeCursor();
    if (!$r) { throw new InvalidArgumentException('El evento no existe.'); }
    $e=cb_agenda_normalizar($r); cb_agenda_exigir($u,$e); return $e;
}
function cb_agenda_proximos(array $u, int $dias=30, ?string $hoy=null): array {
    $hoy=$hoy??(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');
    if (!cb_agenda_fecha($hoy) || $dias<1 || $dias>366) { throw new InvalidArgumentException('Rango inválido.'); }
    return cb_agenda_listar($hoy,(new DateTimeImmutable($hoy))->modify('+'.($dias-1).' days')->format('Y-m-d'),$u);
}
function cb_agenda_guardar(array $input, array $u): array {
    cb_agenda_exigir($u);
    $ref=$input['ref']??'';
    if (!is_string($ref)) { throw new InvalidArgumentException('Evento inválido.'); }
    $old=$ref!=='' ? cb_agenda_obtener($ref,$u) : [];
    if (!$old && $u['rol']!=='super') { throw new DomainException('Solo el superadministrador crea eventos generales.'); }
    $d=array_replace(['tipo'=>'fiesta','estado'=>'consulta','titulo'=>'','fecha'=>'','lugar'=>'','contacto_nombre'=>'','contacto_telefono'=>'','notas'=>'',
        'hora_inicio'=>null,'hora_fin'=>null,'hora_montaje'=>null],$old,$input);
    if (!empty($old['party_id'])) {
        if ($d['fecha']!==$old['fecha']) { throw new InvalidArgumentException('La fecha de una fiesta se cambia desde Fiestas; la agenda la actualiza automáticamente.'); }
        $d['tipo']='fiesta';
    }
    foreach (['tipo'=>16,'estado'=>16,'titulo'=>160,'fecha'=>10,'lugar'=>255,'contacto_nombre'=>160,'contacto_telefono'=>40,'notas'=>10000] as $k=>$max) {
        if (!is_string($d[$k]) || mb_strlen($d[$k])>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$d[$k])) {
            throw new InvalidArgumentException('Revisa el campo '.$k.'.');
        }
        $d[$k]=trim($d[$k]);
    }
    if ($d['titulo']==='' || !cb_agenda_fecha($d['fecha']) || !isset(cb_agenda_tipos()[$d['tipo']]) || !isset(cb_agenda_estados()[$d['estado']])) {
        throw new InvalidArgumentException('Completa título, fecha, tipo y estado válidos.');
    }
    foreach (['hora_inicio','hora_fin','hora_montaje'] as $k) {
        if ($d[$k]===null || $d[$k]==='') { $d[$k]=null; continue; }
        if (!is_string($d[$k]) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D',$d[$k])) {
            throw new InvalidArgumentException('La hora no es válida.');
        }
        $d[$k]=substr($d[$k],0,5);
    }
    if (($d['hora_fin'] && (!$d['hora_inicio'] || $d['hora_fin']<=$d['hora_inicio'])) ||
        ($d['hora_montaje'] && (!$d['hora_inicio'] || $d['hora_montaje']>$d['hora_inicio']))) {
        throw new InvalidArgumentException('El fin debe ser posterior al inicio y el montaje anterior o igual.');
    }
    // No se acepta party_id del cliente: la relación procede del evento autorizado.
    $keys=['tipo','titulo','fecha','hora_inicio','hora_fin','hora_montaje','lugar','contacto_nombre','contacto_telefono','estado','notas'];
    $values=array_map(static fn($k)=>$d[$k],$keys); $now=gmdate('Y-m-d H:i:s'); $pdo=cb_pdo();
    if (!empty($old['id'])) {
        $pdo->prepare('UPDATE cc_agenda_eventos SET '.implode(', ',array_map(static fn($k)=>"$k = ?",$keys)).', updated_at = ? WHERE id = ?')
            ->execute(array_merge($values,[$now,$old['id']]));
    } else {
        try {
            $pdo->prepare('INSERT INTO cc_agenda_eventos ('.implode(',',$keys).',party_id,created_at,updated_at) VALUES ('.implode(',',array_fill(0,count($keys)+3,'?')).')')
                ->execute(array_merge($values,[$old['party_id']??null,$now,$now]));
            $ref=empty($old['party_id'])?'e:'.$pdo->lastInsertId():$ref;
        } catch (PDOException $e) {
            // Dos primeras ediciones simultáneas de la misma fiesta: UNIQUE es la autoridad.
            if (empty($old['party_id']) || !in_array((string)$e->getCode(),['23000','23505'],true)) { throw $e; }
            $fresh=cb_agenda_obtener($ref,$u);
            if (empty($fresh['id'])) { throw $e; }
            return cb_agenda_guardar(array_replace($d,['ref'=>$ref]),$u);
        }
    }
    return cb_agenda_obtener($ref,$u);
}
function cb_agenda_cancelar(string $ref,array $u): array {
    return cb_agenda_guardar(['ref'=>$ref,'estado'=>'cancelada'],$u);
}
function cb_agenda_intervalo(array $e): ?array {
    if ($e['estado']==='cancelada') { return null; }
    if ($e['tipo']==='bloqueo' && !$e['hora_inicio'] && !$e['hora_fin']) { return ['00:00','24:00']; }
    return $e['hora_inicio'] && $e['hora_fin'] ? [substr($e['hora_montaje']?:$e['hora_inicio'],0,5),substr($e['hora_fin'],0,5)] : null;
}
function cb_agenda_choques(array $evento,array $u): array {
    $a=cb_agenda_intervalo($evento); if (!$a) { return []; }
    $out=[];
    foreach (cb_agenda_listar($evento['fecha'],$evento['fecha'],$u) as $e) {
        $b=cb_agenda_intervalo($e);
        if ($e['ref']!==$evento['ref'] && $b && $a[0]<$b[1] && $b[0]<$a[1]) { $out[]=$e; }
    }
    return $out;
}
function cb_agenda_checklist(string $slug): array {
    $pid=cb_party_db_id($slug); if ($pid===null) { throw new InvalidArgumentException('Fiesta inválida.'); }
    $s=cb_pdo()->prepare("SELECT 1 FROM cc_invitations WHERE party_id = ? AND status = 'published'");
    $s->execute([$pid]); $album=cb_album_find_by_party($pid);
    return ['invitacion'=>(bool)$s->fetchColumn(),'terminos'=>cb_party_acceptance_state($slug)['status'],
        'manual'=>!empty(cb_envios_de_fiesta($slug)['manual']['ultimo']),
        'album'=>($album['status']??'')==='published','cobro'=>cb_party_billing($slug),
        'referencia'=>cb_party_invitacion_datos($slug)];
}
function cb_agenda_firma(int $uid): string { return cb_hmac('usuario:'.$uid,'agenda-ics'); }
function cb_agenda_usuario_ics(int $uid,string $firma): ?array {
    if ($uid<0 || !preg_match('/^[a-f0-9]{64}$/D',$firma) || !hash_equals(cb_agenda_firma($uid),$firma)) { return null; }
    $u=$uid===0?cb_admin_usuario_maestro():cb_admin_usuario_por_id($uid);
    return $u && !empty($u['activo']) && empty($u['debe_cambiar']) && cb_admin_usuario_puede($u,'agenda') ? $u : null;
}
function cb_agenda_url_ics(array $u): string {
    cb_agenda_exigir($u);
    return rtrim(cb_public_base_url(),'/').'/admin/agenda-ics.php?u='.(int)$u['id'].'&f='.cb_agenda_firma((int)$u['id']);
}
function cb_agenda_ics_texto(string $s): string {
    return str_replace(["\\","\r\n","\r","\n",';',','],["\\\\","\\n","\\n","\\n","\\;","\\,"],$s);
}
function cb_agenda_ics(array $eventos): string {
    $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//CumpleClick//Agenda//ES','CALSCALE:GREGORIAN','X-WR-CALNAME:CumpleClick'];
    foreach ($eventos as $e) {
        if (!cb_agenda_fecha($e['fecha'])) { continue; }
        $lines[]='BEGIN:VEVENT';
        $lines[]='UID:agenda-'.str_replace(':','-',$e['ref']).'@cumpleclick';
        $lines[]='DTSTAMP:'.gmdate('Ymd\THis\Z');
        if ($e['updated_at']!=='') { $lines[]='LAST-MODIFIED:'.gmdate('Ymd\THis\Z',strtotime($e['updated_at'].' UTC')); }
        $lines[]='CLASS:PRIVATE'; $lines[]='SUMMARY:'.cb_agenda_ics_texto($e['titulo']);
        $lines[]='LOCATION:'.cb_agenda_ics_texto($e['lugar']);
        if ($e['hora_inicio']) {
            foreach (['DTSTART'=>'hora_inicio','DTEND'=>'hora_fin'] as $prop=>$key) {
                if (!$e[$key]) { continue; }
                $d=new DateTimeImmutable($e['fecha'].' '.$e[$key],new DateTimeZone('America/Santiago'));
                $lines[]=$prop.':'.$d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            }
        } else {
            $lines[]='DTSTART;VALUE=DATE:'.str_replace('-','',$e['fecha']);
            $lines[]='DTEND;VALUE=DATE:'.(new DateTimeImmutable($e['fecha']))->modify('+1 day')->format('Ymd');
        }
        $lines[]='STATUS:'.($e['estado']==='cancelada'?'CANCELLED':(in_array($e['estado'],['consulta','reservada'],true)?'TENTATIVE':'CONFIRMED'));
        $lines[]='END:VEVENT';
    }
    $lines[]='END:VCALENDAR'; $out=[];
    foreach ($lines as $line) {
        $prefix='';
        while (strlen($line)>75-strlen($prefix)) {
            $chunk=mb_strcut($line,0,75-strlen($prefix),'UTF-8');
            $out[]=$prefix.$chunk; $line=substr($line,strlen($chunk)); $prefix=' ';
        }
        $out[]=$prefix.$line;
    }
    return implode("\r\n",$out)."\r\n";
}

/** Notificaciones internas de Agenda (ampliación solicitada por Luis).
 * Preferencias y marcas de envío privadas; nunca credenciales ni cuerpos de correos.
 */
function cb_agenda_correos_dir(): string {
    $dir=cb_private_dir((string)cb_config('state_dir'),'state_dir').DIRECTORY_SEPARATOR.'agenda-correos';
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)){throw new RuntimeException('No se pudo preparar el registro de notificaciones.');}
    return $dir;
}
function cb_agenda_correo_config(): array {
    $path=cb_agenda_correos_dir().'/preferencias.json';
    if(!is_file($path)){return ['email'=>'','enabled'=>false];}
    $d=json_decode((string)file_get_contents($path),true);
    return ['email'=>is_array($d)&&filter_var($d['email']??'',FILTER_VALIDATE_EMAIL)?$d['email']:'',
        'enabled'=>is_array($d)&&($d['enabled']??false)===true];
}
function cb_agenda_correo_configurar(array $d,array $u):void {
    if($u['rol']!=='super'){throw new DomainException('Solo el superadministrador configura los correos.');}
    $email=$d['email']??'';
    if(!is_string($email)||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)){
        throw new InvalidArgumentException('Escribe un correo válido para recibir la agenda.');
    }
    $path=cb_agenda_correos_dir().'/preferencias.json';
    $tmp=$path.'.'.bin2hex(random_bytes(5)).'.tmp';
    $json=json_encode(['email'=>$email,'enabled'=>!empty($d['enabled'])],JSON_THROW_ON_ERROR);
    if(file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('No se guardaron las preferencias.');}
    @chmod($path,0600);
}
function cb_agenda_correo_preparar(string $tipo,DateTimeImmutable $now,array $u):array {
    if(!in_array($tipo,['diario','semanal'],true)){throw new InvalidArgumentException('Resumen inválido.');}
    $u=cb_agenda_correo_alcance($u);
    $local=$now->setTimezone(new DateTimeZone('America/Santiago'));
    $from=$tipo==='diario'?$local:$local->modify('monday this week');
    $to=$tipo==='diario'?$from:$from->modify('+6 days');
    $events=array_values(array_filter(cb_agenda_listar($from->format('Y-m-d'),$to->format('Y-m-d'),$u),
        static fn($e)=>$e['estado']!=='cancelada'));
    $title=($tipo==='diario'?'Tus eventos de hoy':'Tu semana en CumpleClick').' · '.$from->format('d/m/Y')
        .($tipo==='semanal'?' al '.$to->format('d/m/Y'):'');
    $parts=[$title,count($events).' evento(s) registrados.'];
    if(!$events){$parts[]=$tipo==='diario'?'No hay eventos para hoy.':'No hay eventos para esta semana.';}
    foreach($events as $e){
        $line=(new DateTimeImmutable($e['fecha']))->format('d/m').' · '.$e['titulo'].' · '
            .($e['hora_inicio']?substr($e['hora_inicio'],0,5):'Hora por confirmar')
            .($e['hora_fin']?' a '.substr($e['hora_fin'],0,5):'').' · '.cb_agenda_estados()[$e['estado']];
        if($tipo==='diario'){
            $line.="\nMontaje: ".($e['hora_montaje']?substr($e['hora_montaje'],0,5):'por confirmar')
                ."\nLugar: ".($e['lugar']?:'por confirmar')
                ."\nContacto: ".trim($e['contacto_nombre'].' '.$e['contacto_telefono']);
            if($e['public_slug']!==''){
                $c=cb_agenda_checklist($e['public_slug']);
                $line.="\nInvitación: ".($c['invitacion']?'publicada':'pendiente')
                    .'; términos: '.(['accepted'=>'firmados','waived'=>'eximidos'][$c['terminos']]??'pendientes')
                    .'; manual: '.($c['manual']?'enviado':'sin envío registrado')
                    .'; álbum: '.($c['album']?'publicado':'pendiente');
                $line.="\nAbono: ".($c['cobro']['deposit_amount']===null?'sin registro':cb_format_clp($c['cobro']['deposit_amount']))
                    .'; saldo: '.($c['cobro']['balance']===null?'por definir':cb_format_clp($c['cobro']['balance']));
                $r=$c['referencia'];
                if($r){$line.="\nReferencia de invitación: ".trim(($r['event_time']??'').' '.($r['address']??''));}
            }
            if($e['notas']!==''){$line.="\nNotas: ".$e['notas'];}
            $overlap=cb_agenda_choques($e,$u);
            if($overlap){$line.="\nCoincide con: ".implode(', ',array_column($overlap,'titulo'));}
        }
        $parts[]=$line;
    }
    if($u['rol']==='super'||!empty($u['enlace_agenda'])){$parts[]='Ver agenda: '.rtrim(cb_public_base_url(),'/').'/admin/agenda.php';}
    $parts[]='Horarios de Chile (America/Santiago).';
    require_once __DIR__.'/lib.mail-templates.php';
    $html=cc_mail_shell($title,implode('',array_map(static fn($p)=>'<p style="line-height:1.6">'.nl2br(cc_mail_h($p)).'</p>',$parts)));
    return ['subject'=>'CumpleClick · '.$title,'text'=>implode("\n\n",$parts),'html'=>$html,'count'=>count($events),'period'=>$from->format('Y-m-d'),'to'=>$u['email']??''];
}
/** Destinatarios actuales: el admin global y personas activas con fiestas asignadas. */
function cb_agenda_correo_destinatarios(array $cfg):array {
    $dest=[['key'=>'admin','email'=>$cfg['email'],'usuario'=>cb_admin_usuario_maestro()]];
    foreach(cb_admin_usuarios() as $u){
        if(!$u['activo']||!$u['fiestas']||!cb_admin_correo_valido($u['email'])){continue;}
        // El admin global ya recibe todo. No duplicar su correo como usuario asignado.
        if(strcasecmp($u['email'],$cfg['email'])===0){continue;}
        $dest[]=['key'=>'usuario-'.$u['id'],'email'=>$u['email'],'usuario'=>$u];
    }
    return $dest;
}
/** La asignación autoriza el aviso, aunque no esté habilitada la pantalla Agenda.
 * Nunca amplía permisos de sesión: el alcance de cualquier usuario con fila es solo sus fiestas.
 */
function cb_agenda_correo_alcance(array $u):array {
    if(($u['id']??-1)===0&&($u['rol']??'')==='super'){return cb_admin_usuario_maestro();}
    $fresh=cb_admin_usuario_por_id((int)($u['id']??0));
    if(!$fresh||!$fresh['activo']||!$fresh['fiestas']||!cb_admin_correo_valido($fresh['email'])){throw new DomainException('Sin asignaciones activas para recibir avisos.');}
    $fresh['enlace_agenda']=cb_admin_usuario_puede($fresh,'agenda');
    $fresh['rol']='operador';$fresh['modulos']=['agenda'];
    return $fresh;
}
/** La inyección sustituye solo el transporte; datos, permisos y marcas siempre son reales. */
function cb_agenda_correo_ejecutar(DateTimeImmutable $now,bool $simular=true,?callable $transport=null):array {
    $cfg=cb_agenda_correo_config();
    if(!$cfg['enabled']||$cfg['email']===''){return [];}
    $local=$now->setTimezone(new DateTimeZone('America/Santiago'));$jobs=[];
    if($local->format('H:i')>='09:00'){$jobs[]='diario';}
    if($local->format('N')==='1'&&$local->format('H:i')>='08:00'){$jobs[]='semanal';}
    $result=[];
    foreach($jobs as $type){
        foreach(cb_agenda_correo_destinatarios($cfg) as $dest){
            $key=$type.($dest['key']==='admin'?'':':'.$dest['key']);
            try{$mail=cb_agenda_correo_preparar($type,$local,$dest['usuario']);}
            catch(DomainException $e){$result[$key]='revocado';continue;}
            // Dirección y alcance salen del mismo usuario recién leído.
            if($dest['key']==='admin'){$mail['to']=$cfg['email'];}
            elseif(strcasecmp($mail['to'],$cfg['email'])===0){$result[$key]='duplicado_admin';continue;}
            if($simular){$result[$key]='simulacion';continue;}
            require_once __DIR__.'/lib.mail.php';
            $copies=cc_mail_ocultos(['to'=>$mail['to']]);
            $allowed=[strtolower($cfg['email']),strtolower($mail['to'])];
            if(array_filter($copies,static fn($email)=>!in_array(strtolower($email),$allowed,true))){
                $result[$key]='revisar_copias';continue;
            }
            if($transport===null&&!cc_mail_enabled()){$result[$key]='sin_smtp';continue;}
            $file=cb_agenda_correos_dir().'/'.$type.'-'.$mail['period'].'-'.$dest['key'].'.json';
            $handle=fopen($file,'c+');if($handle===false){throw new RuntimeException('No se abrió el registro de envío.');}
            if(!flock($handle,LOCK_EX|LOCK_NB)){fclose($handle);$result[$key]='ocupado';continue;}
            try{
                $old=json_decode(stream_get_contents($handle),true);
                if(is_array($old)&&isset($old['status'])){$result[$key]=$old['status']==='enviado'?'ya_enviado':'revisar';continue;}
                $record=static function(string $state)use($handle,$local):void{
                    rewind($handle);
                    if(!ftruncate($handle,0)||fwrite($handle,json_encode(['status'=>$state,'at'=>$local->format(DATE_ATOM)],JSON_THROW_ON_ERROR))===false||!fflush($handle)){
                        throw new RuntimeException('No se pudo registrar el intento de envío.');
                    }
                };
                // Un resultado SMTP incierto exige revisión: nunca reenvío ciego.
                $record('enviando');unset($mail['count'],$mail['period']);
                try{$sent=$transport!==null?$transport($mail):cc_mail_send($mail);}
                catch(Throwable $e){$sent=['ok'=>false];}
                $result[$key]=!empty($sent['ok'])?'enviado':'revisar';$record($result[$key]);
            }finally{flock($handle,LOCK_UN);fclose($handle);}
        }
    }
    return $result;
}
function cb_agenda_correo_historial():array {
    $out=[];
    foreach(glob(cb_agenda_correos_dir().'/*.json')?:[] as $f){
        if(!preg_match('/^(diario|semanal)-([0-9]{4}-[0-9]{2}-[0-9]{2})-(admin|usuario-[1-9][0-9]*)\\.json$/D',basename($f),$m)){continue;}
        $d=json_decode((string)file_get_contents($f),true);
        if(is_array($d)){$out[]=['tipo'=>$m[1],'periodo'=>$m[2],'destino'=>$m[3],'status'=>$d['status']??'revisar'];}
    }
    usort($out,static fn($a,$b)=>[$b['periodo'],$b['tipo'],$b['destino']]<=>[$a['periodo'],$a['tipo'],$a['destino']]);return array_slice($out,0,20);
}
