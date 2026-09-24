<?php
/** AT-CUMPLECLICK-018. Bases desechables; nunca lee configuración de una fiesta real. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cc-agenda-test-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$dsn = 'sqlite:' . $tmp . '/test.sqlite';
$mysql = in_array('--mysql', $argv, true);
if ($mysql) {
    // Instancia de QA dedicada, aislada del MySQL habitual de WAMP.
    $server = new PDO('mysql:host=127.0.0.1;port=33387;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db = 'cc_agenda_test_' . bin2hex(random_bytes(6));
    $server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4");
    $dsn = "mysql:host=127.0.0.1;port=33387;dbname=$db;charset=utf8mb4";
    register_shutdown_function(static function () use ($server, $db) { $server->exec("DROP DATABASE $db"); });
}
foreach (['CUMPLECLICK_CONFIG_FILE'=>$tmp.'/absent.php','CC_STORAGE_MODE'=>'db','CC_PDO_DSN'=>$dsn,
    'CC_PDO_USER'=>$mysql?'root':'', 'CC_PDO_PASSWORD'=>'', 'CC_APP_HMAC_KEY'=>bin2hex(random_bytes(32)),
    'CC_AJUSTES_PATH'=>$tmp.'/ajustes.json','CC_PHOTO_DIR'=>$tmp.'/photos','CC_STATE_DIR'=>$tmp.'/state','CC_SMTP_HOST'=>'',
    'CC_PUBLIC_BASE_URL'=>'http://127.0.0.1:18918'] as $k=>$v) { putenv("$k=$v"); }
register_shutdown_function(static function () use ($tmp) {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $p){$p->isDir()?@rmdir($p->getPathname()):@unlink($p->getPathname());}
    @rmdir($tmp);
});
require $root.'/public/lib.php';
require __DIR__.'/_migraciones.php';
$n = 0;
function check(bool $ok, string $message): void { global $n; $n++; if (!$ok) { throw new RuntimeException("FAIL: $message"); } }
function rejects(callable $f, string $message): void {
    try { $f(); } catch (InvalidArgumentException | DomainException $e) { check(true, $message); return; }
    check(false, $message);
}
check(is_file($root.'/database/migrations/024_agenda_eventos.php'), 'existe migración de agenda');
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);
$up = require $root.'/database/migrations/024_agenda_eventos.php';
$up($pdo); $up($pdo);
check((int)$pdo->query('SELECT COUNT(*) FROM cc_agenda_eventos')->fetchColumn()===0, 'migración repetible sin filas inventadas');
check(is_file($root.'/public/lib.agenda.php'), 'existe librería de agenda');
require $root.'/public/lib.agenda.php';
cb_save_parties(['parties'=>[
    'samantha-hielo'=>['nombre'=>'Samantha','tema'=>'hielo','fecha'=>'2026-09-13','activa'=>true,'creada'=>'2026-09-01 10:00:00','service_plan'=>'full'],
    'luciano-spidey'=>['nombre'=>'Luciano','tema'=>'spidey','fecha'=>'2026-09-13','activa'=>false,'creada'=>'2026-09-01 10:00:00','service_plan'=>'booth'],
]]);
$super = cb_admin_usuario_maestro();
$sid = cb_party_db_id('samantha-hielo'); $lid = cb_party_db_id('luciano-spidey');
$operator = ['id'=>99,'rol'=>'operador','modulos'=>['agenda'],'fiestas'=>['samantha-hielo']];
$rows = cb_agenda_listar('2026-09-01','2026-09-30',$super);
check(count($rows)===2, 'dos fiestas aparecen sin filas manuales, también la inactiva');
check((int)$pdo->query('SELECT COUNT(*) FROM cc_agenda_eventos')->fetchColumn()===0, 'leer no escribe');
check(count(cb_agenda_listar('2026-09-01','2026-09-30',$operator))===1, 'operador ve solo su fiesta');
$sin = $operator; $sin['modulos'] = [];
rejects(fn()=>cb_agenda_listar('2026-09-01','2026-09-30',$sin), 'sin módulo no lee');
$sam = cb_agenda_obtener('p:'.$sid, $super);
check($sam['estado']==='consulta', 'activa no inventa reserva confirmada');
$sam = cb_agenda_guardar(array_merge($sam,['hora_inicio'=>'16:00','hora_fin'=>'18:00','hora_montaje'=>'15:30','estado'=>'confirmada']),$super);
$sam = cb_agenda_guardar(array_merge($sam,['lugar'=>'Sala de ejemplo']),$super);
check((int)$pdo->query('SELECT COUNT(*) FROM cc_agenda_eventos')->fetchColumn()===1, 'editar fiesta hace upsert, no duplica');
check(cb_agenda_obtener('p:'.$sid,$super)['lugar']==='Sala de ejemplo','edición persistida');
rejects(fn()=>cb_agenda_guardar(['ref'=>'p:'.$lid,'titulo'=>'Intrusión','fecha'=>'2026-09-13'],$operator), 'no edita fiesta ajena');
rejects(fn()=>cb_agenda_guardar(['titulo'=>'Reunión','tipo'=>'reunion','fecha'=>'2026-09-26'],$operator), 'generales solo super');

$pdo->prepare('UPDATE cc_parties SET event_date=? WHERE id=?')->execute(['2026-10-04',$sid]);
check(cb_agenda_obtener('p:'.$sid,$super)['fecha']==='2026-10-04','reprogramar fiesta actualiza logística aunque ya tuviera lugar guardado');
check(count(cb_agenda_listar('2026-10-01','2026-10-31',$super))===1,'fiesta reprogramada aparece en el mes nuevo');
rejects(fn()=>cb_agenda_guardar(['ref'=>'p:'.$sid,'fecha'=>'2026-10-05'],$super),'fecha de fiesta solo se cambia desde su fuente canónica');
$pdo->prepare('UPDATE cc_parties SET event_date=? WHERE id=?')->execute(['2026-09-13',$sid]);
$feria = cb_agenda_guardar(['tipo'=>'feria','titulo'=>'Mini Feria','fecha'=>'2026-09-26','hora_inicio'=>'10:00','hora_fin'=>'14:30','estado'=>'confirmada'],$super);
$choque = cb_agenda_guardar(['tipo'=>'visita','titulo'=>'Visita','fecha'=>'2026-09-26','hora_inicio'=>'14:00','hora_fin'=>'15:00'],$super);
check(count(cb_agenda_choques($choque,$super))===1,'choque avisa y ambas filas se guardaron');
$pegado = cb_agenda_guardar(['tipo'=>'entrega','titulo'=>'Entrega','fecha'=>'2026-09-26','hora_inicio'=>'15:00','hora_fin'=>'16:00'],$super);
check(count(cb_agenda_choques($pegado,$super))===0,'intervalos contiguos no chocan');
$choque = cb_agenda_cancelar($choque['ref'],$super);
check($choque['estado']==='cancelada' && count(cb_agenda_choques($feria,$super))===0,'cancelar persiste y deja de ocupar horario');
$montaje = cb_agenda_guardar(['tipo'=>'visita','titulo'=>'Montaje previo','fecha'=>'2026-09-13','hora_inicio'=>'15:00','hora_fin'=>'15:45'],$super);
check(count(cb_agenda_choques($montaje,$super))===1,'montaje también ocupa el horario');
rejects(fn()=>cb_agenda_guardar(['titulo'=>'X','tipo'=>'feria','fecha'=>'2026-02-30'],$super),'fecha imposible');
rejects(fn()=>cb_agenda_guardar(['titulo'=>'X','tipo'=>'feria','fecha'=>'2026-09-26','hora_inicio'=>'18:00','hora_fin'=>'10:00'],$super),'intervalo invertido');
rejects(fn()=>cb_agenda_guardar(['titulo'=>'X','tipo'=>'feria','fecha'=>'2026-09-26','hora_inicio'=>'25:00'],$super),'hora imposible');
rejects(fn()=>cb_agenda_guardar(['titulo'=>'X','tipo'=>'otro','fecha'=>'2026-09-26'],$super),'tipo inválido');
rejects(fn()=>cb_agenda_guardar(['titulo'=>'X','tipo'=>'feria','fecha'=>'2026-09-26','estado'=>'pagada'],$super),'estado inválido');
rejects(fn()=>cb_agenda_obtener('e:999999',$super),'id inexistente');
check(count(cb_agenda_proximos($super,30,'2026-09-24'))===3,'próximos 30 días con fechas reproducibles');
$ck = cb_agenda_checklist('samantha-hielo');
check(!$ck['manual'] && !$ck['album'] && !$ck['invitacion'] && $ck['terminos']==='none','checklist inicial no inventa hechos');
cb_save_party_billing('samantha-hielo',['price_total'=>'50000','deposit_amount'=>'20000']);
cb_envio_registrar('samantha-hielo','manual','prueba@example.invalid',false,false,'fallo de prueba');
check(!cb_agenda_checklist('samantha-hielo')['manual'],'correo fallido no marca enviado');
cb_envio_registrar('samantha-hielo','manual','prueba@example.invalid',false,true);
$aid = (int)cb_album_ensure($sid)['id'];
cb_album_update($aid,['status'=>'published']);
$ck = cb_agenda_checklist('samantha-hielo');
check($ck['manual'] && $ck['album'] && $ck['cobro']['balance']===30000,'lee manual, álbum y cobro existentes');
$pdo->prepare("INSERT INTO cc_plan_acceptances (party_id,party_public_slug,public_token_hash,status,plan_code,plan_summary_json,client_name,client_email,legal_version,legal_text_sha256,created_at,updated_at) VALUES (?,? ,?,'accepted','full','{}','','','test',?, ?,?)")
    ->execute([$sid,'samantha-hielo',hash('sha256',random_bytes(32)),str_repeat('0',64),'2026-09-01 10:00:00','2026-09-01 10:00:00']);
check(cb_agenda_checklist('samantha-hielo')['terminos']==='accepted','términos se calculan de fuente real');
$allDay = cb_agenda_guardar(['tipo'=>'bloqueo','titulo'=>"Día, familiar; ".str_repeat('á',90),'fecha'=>'2026-09-27','notas'=>'privado-no-exportar','contacto_telefono'=>'999999999'],$super);
$ics = cb_agenda_ics(cb_agenda_listar('2026-09-01','2026-09-30',$super));
check(str_starts_with($ics,"BEGIN:VCALENDAR\r\n") && str_ends_with($ics,"END:VCALENDAR\r\n"),'estructura ICS y CRLF');
check(str_contains($ics,'DTSTART:20260926T130000Z') && str_contains($ics,'DTEND:20260926T173000Z'),'horario Chile a UTC correcto');
check(str_contains($ics,'DTSTART;VALUE=DATE:20260927') && str_contains($ics,'DTEND;VALUE=DATE:20260928'),'día completo tiene fin exclusivo');
check(str_contains($ics,'SUMMARY:Día\, familiar\; ') && !str_contains($ics,'privado-no-exportar') && !str_contains($ics,'999999999'),'escapa ICS y excluye notas/contacto');
foreach (explode("\r\n",trim($ics)) as $line) { check(strlen($line)<=75 && mb_check_encoding($line,'UTF-8'),'plegado UTF8 hasta75bytes'); }
check(str_contains($ics,'STATUS:CANCELLED'),'cancelación informa al calendario');
$oldUid = 'UID:agenda-p-'.$sid.'@cumpleclick';
check(str_contains($ics,$oldUid),'UID fiesta estable antes/después del alta logística');
$sign = cb_agenda_firma(0);
check(cb_agenda_usuario_ics(0,$sign)['rol']==='super' && cb_agenda_usuario_ics(0,'incorrecta')===null,'firma válida y falsa');

check(function_exists('cb_agenda_correo_preparar'),'resúmenes de correo disponibles');
$daily=cb_agenda_correo_preparar('diario',new DateTimeImmutable('2026-09-13 08:00:00',new DateTimeZone('America/Santiago')),$super);
check(str_contains($daily['text'],'Samantha')&&str_contains($daily['text'],'Montaje: 15:30')&&str_contains($daily['text'],'Sala de ejemplo'),'diario con detalles operativos');
$weekly=cb_agenda_correo_preparar('semanal',new DateTimeImmutable('2026-09-21 09:00:00',new DateTimeZone('America/Santiago')),$super);
check(str_contains($weekly['text'],'Mini Feria')&&!str_contains($weekly['text'],'privado-no-exportar')&&!str_contains($weekly['text'],'999999999'),'semanal resumido sin datos de contacto ni notas');
$empty=cb_agenda_correo_preparar('diario',new DateTimeImmutable('2026-10-28 08:00:00',new DateTimeZone('America/Santiago')),$super);
check(str_contains($empty['text'],'No hay eventos'),'aviso incluso sin eventos');
cb_agenda_correo_configurar(['email'=>'admin@example.invalid','enabled'=>true],$super);
rejects(fn()=>cb_agenda_correo_configurar(['email'=>'mal','enabled'=>true],$super),'destinatario inválido');
rejects(fn()=>cb_agenda_correo_configurar(['email'=>'admin@example.invalid','enabled'=>true],$operator),'operador no cambia destinatario');
$messages=[];
$receiver=static function(array $m)use(&$messages):array{$messages[]=$m;return ['ok'=>true];};
$tz=new DateTimeZone('America/Santiago');
check(cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-21 07:59:00',$tz),false,$receiver)===[],'antes de las08:00 no envía');
$preview=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-21 09:00:00',$tz),true,$receiver);
check(count($preview)===2&&count($messages)===0,'simulación no envía ni consume la marca de envío');
$sent=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-21 09:00:00',$tz),false,$receiver);
check(count($messages)===2&&$sent['diario']==='enviado'&&$sent['semanal']==='enviado','lunes envía diario y semanal al destino');
check($messages[0]['to']==='admin@example.invalid'&&str_contains($messages[0]['html'],'CumpleClick'),'destino y versiónHTML reales');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-21 09:15:00',$tz),false,$receiver);
check(count($messages)===2,'repetir cron no duplica correos');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-22 09:00:00',$tz),false,$receiver);
check(count($messages)===3,'martes solo diario');
$failed=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-23 09:00:00',$tz),false,static fn($m)=>['ok'=>false]);
check($failed['diario']==='revisar','fallo de envío nunca se declara enviado');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-23 09:15:00',$tz),false,$receiver);
check(count($messages)===3,'fallo incierto no se reenvía automáticamente');
cb_agenda_correo_configurar(['email'=>'admin@example.invalid','enabled'=>false],$super);
check(cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-24 09:00:00',$tz),false,$receiver)===[],'desactivar detiene envíos');



// Los avisos usan las asignaciones reales, no una lista de destinatarios paralela.
$created=cb_admin_usuario_crear(['nombre'=>'Equipo QA','correo'=>'equipo@example.invalid','rol'=>'operador','modulos'=>[],'fiestas'=>['samantha-hielo']]);
$uid=$created['id'];unset($created);
cb_agenda_correo_configurar(['email'=>'admin@example.invalid','enabled'=>true],$super);
$messages=[];
$before=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-28 07:59:00',$tz),false,$receiver);
check($before===[]&&$messages===[],'antes del horario no hay avisos por usuario');
$atEight=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-28 08:00:00',$tz),false,$receiver);
check(count($messages)===2,'lunes08:00 envía solo semanal: admin y usuario asignado incluso sin módulo Agenda');
check(str_contains($messages[1]['text'],'No hay eventos para esta semana.'),'usuario asignado recibe aviso de semana vacía');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-28 08:59:00',$tz),false,$receiver);
check(count($messages)===2,'diario no se adelanta a08:59');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-28 12:00:00',new DateTimeZone('UTC')),false,$receiver);
check(count($messages)===4,'09:00Chile desde relojUTC añade solo diarios');
$messages=[];
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-13 09:00:00',$tz),false,$receiver);
$own=array_values(array_filter($messages,fn($m)=>$m['to']==='equipo@example.invalid'));
check(count($own)===1&&str_contains($own[0]['text'],'Samantha')&&!str_contains($own[0]['text'],'Luciano')&&!str_contains($own[0]['text'],'Montaje previo'),'detalle contiene solo fiestas asignadas y nunca eventos generales');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-07 08:00:00',$tz),false,$receiver);
$own=array_values(array_filter($messages,fn($m)=>$m['to']==='equipo@example.invalid'));
check(count($own)===2&&str_contains($own[1]['text'],'Samantha')&&!str_contains($own[1]['text'],'Luciano'),'resumen semanal respeta las mismas asignaciones');
cb_admin_asignar_fiestas($uid,['luciano-spidey']);
$messages=[];cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-12 08:00:00',$tz),false,$receiver);
check($messages===[],'sábado08:00 no anticipa diario');
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-06 09:00:00',$tz),false,$receiver);
check(count($messages)===2,'usuario activo asignado recibe aunque el día esté vacío');
cb_admin_usuario_activar($uid,false);
$messages=[];cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-14 09:00:00',$tz),false,$receiver);
check(count($messages)===2&&$messages[0]['to']==='admin@example.invalid'&&$messages[1]['to']==='admin@example.invalid','usuario desactivado no recibe diario ni semanal');
cb_admin_usuario_activar($uid,true);cb_admin_asignar_fiestas($uid,[]);
$messages=[];cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-15 09:00:00',$tz),false,$receiver);
check(count($messages)===1&&$messages[0]['to']==='admin@example.invalid','quitar todas las asignaciones detiene avisos del usuario');


cb_guardar_ajustes(['bcc_email'=>'otro-usuario@example.invalid']);
$messages=[]; $bccResult=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-16 09:00:00',$tz),false,$receiver);
check($messages===[]&&$bccResult['diario']==='revisar_copias','copia global ajena no filtra agenda a otra persona');
cb_guardar_ajustes(['bcc_email'=>'admin@example.invalid']);
$messages=[]; cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-16 09:00:00',$tz),false,$receiver);
check(count($messages)===1,'copia global del administrador conserva el envío sin consumir la marca anterior');
cb_guardar_ajustes(['bcc_email'=>'']);

cb_admin_asignar_fiestas($uid,['samantha-hielo']);
$messages=[];
$changing=static function(array $mail)use(&$messages,$pdo,$uid):array{
    $messages[]=$mail;
    if($mail['to']==='admin@example.invalid'){
        $pdo->prepare('UPDATE cc_admin_users SET email=? WHERE id=?')->execute(['nuevo@example.invalid',$uid]);
        cb_admin_asignar_fiestas($uid,['luciano-spidey']);
    }
    return ['ok'=>true];
};
cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-07 09:00:00',$tz),false,$changing);
check(count($messages)===2&&$messages[1]['to']==='nuevo@example.invalid','destino y alcance se resuelven desde la misma lectura fresca');
$messages=[];
$revoking=static function(array $mail)use(&$messages,$uid):array{$messages[]=$mail;cb_admin_usuario_activar($uid,false);return ['ok'=>true];};
$changed=cb_agenda_correo_ejecutar(new DateTimeImmutable('2026-09-17 09:00:00',$tz),false,$revoking);
check(count($messages)===1&&$changed['diario:usuario-'.$uid]==='revocado','revocar durante el lote omite usuario sin abortar');
$proc=proc_open([PHP_BINARY,$root.'/scripts/agenda-correos.php','--simular','--enviar'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pp);
fclose($pp[0]);stream_get_contents($pp[1]);stream_get_contents($pp[2]);fclose($pp[1]);fclose($pp[2]);
check(proc_close($proc)===2,'CLI rechaza simulación y envío a la vez');
$down = require $root.'/database/migrations/024_agenda_eventos.down.php'; $down($pdo); $down($pdo); $up($pdo);
check((int)$pdo->query('SELECT COUNT(*) FROM cc_agenda_eventos')->fetchColumn()===0 && (int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn()===2,'rollback no toca fiestas y permite reaplicar');
echo "Agenda ".($mysql?'MySQL':'SQLite').": OK $n comprobaciones\n";
