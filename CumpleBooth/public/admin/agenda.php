<?php
/** Agenda de eventos: mes al abrir, detalles al tocar una tarjeta. */
require __DIR__.'/../lib.agenda.php';
require __DIR__.'/config.php';
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
session_name('cc_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff');
require __DIR__.'/_acceso.php';
admin_exigir('agenda');
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function admin_csrf_token():string{if(empty($_SESSION['csrf'])){$_SESSION['csrf']=bin2hex(random_bytes(16));}return $_SESSION['csrf'];}
function admin_csrf_check():bool{$t=$_POST['csrf']??'';return is_string($t)&&$t!==''&&hash_equals($_SESSION['csrf']??'',$t);}
function admin_csrf_field():string{return '<input type="hidden" name="csrf" value="'.h(admin_csrf_token()).'">';}
function agenda_query(string $name,string $default=''):string{return isset($_GET[$name])&&is_string($_GET[$name])?$_GET[$name]:$default;}
function agenda_horas(array $e):string{
    if(!$e['hora_inicio']){return $e['tipo']==='bloqueo'?'Todo el día':'Hora por confirmar';}
    return substr($e['hora_inicio'],0,5).($e['hora_fin']?' · '.substr($e['hora_fin'],0,5):'');
}
function agenda_theme(string $slug):string{
    // Se muestran los nombres comerciales aprobados, también para slugs históricos.
    return ['hielo'=>'Reino de Hielo','spidey'=>'Aventura Arácnida','heroes'=>'Aventura Arácnida','carreras'=>'Carreras',
        'familia-canina'=>'Familia Canina','tropical'=>'Tropical','kpop'=>'K-Pop','baby-nube'=>'Entre Nubes',
        'baby-safari'=>'Safari','baby-rosas'=>'Entre Rosas'][$slug]??cb_theme_public_name($slug);
}
function agenda_tarjeta(array $e,bool $detalle=false):void {
    $ck=$detalle&&$e['public_slug']!==''?cb_agenda_checklist($e['public_slug']):null;
    ?><a class="agenda-evento estado-<?= h($e['estado']) ?>" href="?evento=<?= rawurlencode($e['ref']) ?>">
      <span class="agenda-hora"><?= h(agenda_horas($e)) ?></span>
      <strong><?= h($e['titulo']) ?></strong>
      <span class="agenda-estado"><?= h(cb_agenda_estados()[$e['estado']]) ?></span>
      <?php if($detalle): ?>
        <span><?= h(cb_agenda_tipos()[$e['tipo']]) ?><?= $e['public_slug']!==''?' · '.h(agenda_theme($e['theme_slug'])):'' ?></span>
        <?php if($e['public_slug']!==''): ?><span>Plan <?= h($e['event_type']==='baby_shower'?'Baby Shower':($e['service_plan']==='full'?'Premium':'Mágico')) ?></span><?php endif; ?>
        <?php if($e['hora_montaje']): ?><span>Montaje <?= h(substr($e['hora_montaje'],0,5)) ?></span><?php endif; ?>
        <?php if($e['lugar']): ?><span><?= h($e['lugar']) ?></span><?php endif; ?>
        <?php if($ck): ?><span class="agenda-resumen-check">Invitación: <?= $ck['invitacion']?'lista':'pendiente' ?> · Términos: <?= h(['accepted'=>'firmados','waived'=>'eximidos'][$ck['terminos']]??'pendientes') ?><br>Manual: <?= $ck['manual']?'enviado':'sin envío registrado' ?> · Álbum: <?= $ck['album']?'publicado':'pendiente' ?></span><?php endif; ?>
      <?php endif; ?>
    </a><?php
}
$u=admin_usuario_actual();$today=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');
$month=agenda_query('mes',substr($today,0,7));$view=agenda_query('vista','mes');$error='';$event=null;$events=[];$clashes=[];$ck=null;
try {
    if(!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$month)||!cb_agenda_fecha($month.'-01')){throw new InvalidArgumentException('El mes no es válido.');}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!admin_csrf_check()){http_response_code(403);exit('La sesión del formulario venció. Vuelve a abrirlo.');}
        $action=$_POST['action']??'';
        if($action==='correos'){
            admin_exigir_super();
            cb_agenda_correo_configurar(['email'=>$_POST['correo_admin']??'','enabled'=>($_POST['enabled']??'')==='1'],$u);
            header('Location: agenda.php?correos=1',true,303);exit;
        }
        if($action==='guardar'){
            try{$saved=cb_agenda_guardar($_POST,$u);}
            catch(InvalidArgumentException $e){
                // Mantener los valores del formulario permite corregir sin volver a escribir.
                $ref=is_string($_POST['ref']??null)?$_POST['ref']:'';
                $event=$ref!==''?cb_agenda_obtener($ref,$u):['ref'=>'','party_id'=>null,'public_slug'=>'','titulo'=>'','tipo'=>'visita','fecha'=>$today,
                    'hora_inicio'=>'','hora_fin'=>'','hora_montaje'=>'','lugar'=>'','contacto_nombre'=>'','contacto_telefono'=>'','estado'=>'consulta','notas'=>''];
                foreach(['titulo','tipo','fecha','hora_inicio','hora_fin','hora_montaje','lugar','contacto_nombre','contacto_telefono','estado','notas'] as $field){
                    if(is_string($_POST[$field]??null)){$event[$field]=$_POST[$field];}
                }
                if(!empty($event['party_id'])){$event['fecha']=cb_agenda_obtener($ref,$u)['fecha'];$event['tipo']='fiesta';}
                throw $e;
            }
        }
        elseif($action==='cancelar'&&is_string($_POST['ref']??null)){$saved=cb_agenda_cancelar($_POST['ref'],$u);}
        else{throw new InvalidArgumentException('Acción inválida.');}
        header('Location: agenda.php?evento='.rawurlencode($saved['ref']).'&guardado=1',true,303);exit;
    }
    if(agenda_query('evento')!==''){
        $event=cb_agenda_obtener(agenda_query('evento'),$u);$clashes=cb_agenda_choques($event,$u);
        if($event['public_slug']!==''){$ck=cb_agenda_checklist($event['public_slug']);}
        $month=substr($event['fecha'],0,7);
    }elseif(agenda_query('nuevo')==='1'){
        admin_exigir_super();
        $date=agenda_query('fecha',$today);
        if(!cb_agenda_fecha($date)){throw new InvalidArgumentException('La fecha no es válida.');}
        $event=['ref'=>'','titulo'=>'','tipo'=>'visita','fecha'=>$date,'hora_inicio'=>'','hora_fin'=>'','hora_montaje'=>'','lugar'=>'','contacto_nombre'=>'','contacto_telefono'=>'','estado'=>'consulta','notas'=>'','party_id'=>null];
    }
    $first=new DateTimeImmutable($month.'-01');
    $events=$view==='proximos'?cb_agenda_proximos($u,30,agenda_query('desde',$today)):cb_agenda_listar($month.'-01',$first->format('Y-m-t'),$u);
} catch(DomainException $e){admin_denegar('ese evento no está habilitado para tu usuario');}
catch(InvalidArgumentException $e){http_response_code(400);$error=$e->getMessage();}
catch(Throwable $e){http_response_code(503);$error='La agenda no está disponible. Comprueba que la migración 024 esté aplicada.';}
$first=$first??new DateTimeImmutable(substr($today,0,7).'-01');
$months=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$title=ucfirst($months[(int)$first->format('n')-1]).' '.$first->format('Y');
$group=[];foreach($events as $e){$group[$e['fecha']][]=$e;}
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Agenda · CumpleClick</title>
<style><?php require __DIR__.'/_style.css.php'; ?></style>
<style>
.agenda-wrap{max-width:1400px}.agenda-brand{display:flex;align-items:center;gap:14px}.agenda-brand img{width:56px;height:56px}
.agenda-toolbar,.agenda-switch,.agenda-monthbar,.agenda-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.agenda-toolbar{justify-content:space-between;margin:20px 0}.agenda-monthbar{justify-content:space-between;margin:22px 0 14px}.agenda-monthbar h2{font-size:1.8rem;margin:0}
.agenda-switch{padding:5px;border-radius:28px;background:var(--card-bg)}.agenda-switch .activo{background:var(--text);color:var(--card-bg)}
.agenda-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;background:var(--border);box-shadow:var(--shadow)}
.agenda-weekday{padding:10px;text-align:center;font-weight:700;background:var(--bg2)}.agenda-day{min-height:125px;min-width:0;padding:10px;background:var(--card-bg)}.agenda-day.vacio{background:var(--bg2)}
.agenda-dayhead{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}.agenda-dayhead a{min-width:30px;min-height:30px;text-align:center}.agenda-dayhead time{font-weight:700}.agenda-dia-largo{display:none}.agenda-day.hoy{box-shadow:inset 0 0 0 2px var(--primary)}.agenda-day.hoy time{color:var(--primary-dark)}
.agenda-evento{display:flex;flex-direction:column;gap:3px;border-left:4px solid var(--text-muted);border-radius:10px;padding:9px 10px;margin:6px 0;background:var(--bg2);text-decoration:none;color:var(--text);line-height:1.35;overflow-wrap:anywhere}
.agenda-evento:hover{box-shadow:var(--shadow-hover)}.agenda-evento strong{font-size:1rem}.agenda-hora{font-size:.82rem;font-weight:700}.agenda-estado{font-size:.75rem}
.estado-confirmada{border-color:var(--primary);background:var(--primary-soft)}.estado-reservada{border-color:var(--accent);background:var(--warn-soft)}
.estado-realizada{border-color:var(--success);background:var(--success-soft)}.estado-cancelada{border-color:var(--cta);border-left-style:dashed;background:var(--bg2)}
.agenda-leyenda{display:flex;flex-wrap:wrap;gap:16px;font-size:.8rem;margin-top:16px}.agenda-leyenda span{border-left:4px solid var(--text-muted);padding-left:7px}
.agenda-leyenda .estado-confirmada{border-color:var(--primary)}.agenda-leyenda .estado-reservada{border-color:var(--accent)}.agenda-leyenda .estado-realizada{border-color:var(--success)}.agenda-leyenda .estado-cancelada{border-color:var(--cta)}
.agenda-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.agenda-ancho{grid-column:1/-1}.agenda-ficha{max-width:900px;margin:24px auto}.agenda-form input,.agenda-form select,.agenda-form textarea{min-width:0;width:100%}
.agenda-check{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:0;list-style:none}.agenda-check li{background:var(--bg2);padding:12px;border-radius:var(--radius-sm)}
.agenda-list-day{display:grid;grid-template-columns:140px 1fr;gap:18px;margin:18px 0}.agenda-list-items{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}.agenda-list-day h3{margin:12px 0}.agenda-resumen-check{border-top:1px solid var(--border);padding-top:8px;margin-top:6px;font-size:.85rem}
.agenda-ayuda{color:var(--text-muted);font-size:.88rem}.agenda-ics{margin-top:26px}.agenda-ics summary{cursor:pointer;font-weight:700}.agenda-ics input{width:100%;margin:10px 0}.agenda-evento:focus-visible,.btn:focus-visible{outline:3px solid var(--text);outline-offset:3px}
@media(max-width:700px){.agenda-grid{display:block;border:0;background:none;box-shadow:none;overflow:visible}.agenda-weekday,.agenda-day.vacio,.agenda-day.sin-eventos:not(.hoy){display:none}.agenda-day{min-height:0;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:12px;padding:14px}.agenda-dia-largo{display:inline}.agenda-dayhead time{font-size:1.1rem}.agenda-evento{padding:12px}.agenda-form,.agenda-check{grid-template-columns:1fr}.agenda-ancho{grid-column:auto}.agenda-list-day{grid-template-columns:1fr;gap:0}.agenda-monthbar h2{width:100%}.agenda-toolbar>.btn{width:100%}.agenda-list-items{grid-template-columns:1fr}.agenda-brand img{width:44px;height:44px}}
</style><style>
@media(max-width:700px){
.agenda-wrap>.tabs{display:flex;flex-wrap:nowrap;width:100%;max-width:100%;overflow-x:auto;border-radius:var(--radius-sm)}.agenda-wrap>.tabs .tab{flex:0 0 auto}.agenda-brand h1,.agenda-brand p{margin:0}.agenda-wrap .topbar{gap:8px}
.agenda-switch{flex-wrap:nowrap;width:100%}.agenda-switch .btn{width:auto;flex:1;white-space:nowrap;font-size:.9rem;padding:10px 8px}
.agenda-toolbar{gap:10px;margin:14px 0}.agenda-monthbar{margin:18px 0 10px;gap:8px}
.agenda-monthbar .agenda-actions{width:100%;flex-wrap:nowrap}.agenda-monthbar .btn{width:auto;flex:1;min-width:0;padding:10px 6px}
}
</style></head><body><div class="wrap agenda-wrap">
<header class="topbar"><div class="agenda-brand"><img src="../brand/cumpleclick-mark.svg" alt=""><div><h1>Agenda</h1><p class="muted">Tus fiestas y compromisos, día a día.</p></div></div><?= admin_usuario_chip() ?></header>
<?= admin_nav('agenda') ?>
<main>
<?php if($error!==''): ?><p class="alert alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if(agenda_query('guardado')==='1'): ?><p class="alert alert-ok" role="status">Evento guardado.</p><?php endif; ?>
<?php if($event!==null): ?>
<section class="card agenda-ficha">
<a href="?mes=<?= h($month) ?>">← Volver al calendario</a><h2><?= $event['ref']!==''?'Detalle del evento':'Nuevo evento' ?></h2>
<?php if($clashes): ?><p class="alert" style="background:var(--warn-soft)" role="status">Coincide con <?= h(implode(', ',array_column($clashes,'titulo'))) ?>. Puedes mantenerlo o ajustar el horario.</p><?php endif; ?>
<form method="post" class="agenda-form">
<?= admin_csrf_field() ?><input type="hidden" name="action" value="guardar"><input type="hidden" name="ref" value="<?= h($event['ref']) ?>">
<div class="field agenda-ancho"><label for="titulo">Nombre del evento</label><input id="titulo" name="titulo" maxlength="160" value="<?= h($event['titulo']) ?>" required placeholder="Por ejemplo: visita al salón"></div>
<div class="field"><label for="tipo">Tipo de evento</label><select id="tipo" name="tipo"><?php foreach(cb_agenda_tipos() as $k=>$v): if(!empty($event['party_id'])&&$k!=='fiesta'){continue;} ?><option value="<?= h($k) ?>" <?= $event['tipo']===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="estado">Estado de la reserva</label><select id="estado" name="estado"><?php foreach(cb_agenda_estados() as $k=>$v): ?><option value="<?= h($k) ?>" <?= $event['estado']===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="fecha">Fecha</label><input type="date" id="fecha" name="fecha" value="<?= h($event['fecha']) ?>" required <?= !empty($event['party_id'])?'readonly':'' ?>><?php if(!empty($event['party_id'])): ?><small>La fecha se actualiza desde Fiestas.</small><?php endif; ?></div>
<div class="field"><label for="hora_montaje">Hora de montaje</label><input type="time" id="hora_montaje" name="hora_montaje" value="<?= h(substr($event['hora_montaje']??'',0,5)) ?>"></div>
<div class="field"><label for="hora_inicio">Hora de inicio</label><input type="time" id="hora_inicio" name="hora_inicio" value="<?= h(substr($event['hora_inicio']??'',0,5)) ?>"></div>
<div class="field"><label for="hora_fin">Hora de término</label><input type="time" id="hora_fin" name="hora_fin" value="<?= h(substr($event['hora_fin']??'',0,5)) ?>"></div>
<div class="field agenda-ancho"><label for="lugar">Lugar / dirección para el montaje</label><input id="lugar" name="lugar" maxlength="255" value="<?= h($event['lugar']) ?>"></div>
<div class="field"><label for="contacto_nombre">Nombre del contacto</label><input id="contacto_nombre" name="contacto_nombre" maxlength="160" value="<?= h($event['contacto_nombre']) ?>"></div>
<div class="field"><label for="contacto_telefono">Teléfono del contacto</label><input type="tel" id="contacto_telefono" name="contacto_telefono" maxlength="40" value="<?= h($event['contacto_telefono']) ?>"></div>
<div class="field agenda-ancho"><label for="notas">Notas para el equipo</label><textarea id="notas" name="notas" rows="3" maxlength="10000"><?= h($event['notas']) ?></textarea><small>Si faltan horas, no se puede comprobar el cruce de horarios. Los bloqueos sin hora ocupan todo el día.</small></div>
<div class="agenda-actions agenda-ancho"><button class="btn btn-cta">Guardar evento</button><a class="btn btn-ghost" href="?mes=<?= h($month) ?>">Volver</a></div>
</form>
<?php if($event['ref']!==''&&$event['estado']!=='cancelada'): ?>
<form method="post" style="margin-top:18px"><?= admin_csrf_field() ?><input type="hidden" name="action" value="cancelar"><input type="hidden" name="ref" value="<?= h($event['ref']) ?>"><button class="btn btn-ghost">Cancelar evento</button><small class="muted"> Quedará en el historial como cancelado.</small></form>
<?php endif; ?>
<?php if($ck): ?><h3>Preparación de la fiesta</h3><ul class="agenda-check">
<li>Invitación: <strong><?= $ck['invitacion']?'publicada':'pendiente' ?></strong></li>
<li>Términos: <strong><?= h(['accepted'=>'firmados','waived'=>'eximidos'][$ck['terminos']]??'pendientes') ?></strong></li>
<li>Manual: <strong><?= $ck['manual']?'enviado':'sin envío registrado' ?></strong></li><li>Álbum: <strong><?= $ck['album']?'publicado':'pendiente' ?></strong></li>
</ul><p>Abono: <?= $ck['cobro']['deposit_amount']===null?'sin registro':h(cb_format_clp($ck['cobro']['deposit_amount'])) ?> · Saldo: <?= $ck['cobro']['balance']===null?'por definir':h(cb_format_clp($ck['cobro']['balance'])) ?></p>
<p class="agenda-ayuda">Referencia de la invitación: <?= h(($ck['referencia']['event_date']??'').' · '.($ck['referencia']['event_time']??'').' · '.($ck['referencia']['address']??'Sin dirección registrada')) ?>. Editar la logística aquí no cambia la invitación.</p>
<?php endif; ?>
</section>
<?php elseif($error===''): ?>
<div class="agenda-toolbar"><div class="agenda-switch"><a class="btn <?= $view==='mes'?'activo':'' ?>" href="?mes=<?= h($month) ?>" <?= $view==='mes'?'aria-current="page"':'' ?>>Calendario</a><a class="btn <?= $view==='proximos'?'activo':'' ?>" href="?vista=proximos" <?= $view==='proximos'?'aria-current="page"':'' ?>>Próximos 30 días</a></div><?php if(admin_es_super()): ?><a class="btn btn-cta" href="?nuevo=1">+ Nuevo evento</a><?php endif; ?></div>
<?php if($view==='proximos'): ?><h2>Próximos 30 días</h2>
<?php foreach($group as $date=>$items): ?><section class="agenda-list-day"><h3><?= h((new DateTimeImmutable($date))->format('d/m/Y')) ?></h3><div class="agenda-list-items"><?php foreach($items as $e){agenda_tarjeta($e,true);} ?></div></section><?php endforeach; ?>
<?php else: ?>
<div class="agenda-monthbar"><h2><?= h($title) ?></h2><div class="agenda-actions"><a class="btn btn-ghost" href="?mes=<?= h($first->modify('-1 month')->format('Y-m')) ?>" aria-label="Mes anterior">← Anterior</a><a class="btn btn-ghost" href="agenda.php">Hoy</a><a class="btn btn-ghost" href="?mes=<?= h($first->modify('+1 month')->format('Y-m')) ?>" aria-label="Mes siguiente">Siguiente →</a></div></div>
<p class="agenda-ayuda"><?= count($events) ?> eventos registrados. Toca un evento para ver los detalles<?= admin_es_super()?' o el + de un día para agregar uno':'' ?>.</p>
<div class="agenda-grid" aria-label="Calendario de <?= h($title) ?>">
<?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $name): ?><div class="agenda-weekday"><?= $name ?></div><?php endforeach; ?>
<?php for($i=1;$i<(int)$first->format('N');$i++): ?><div class="agenda-day vacio" aria-hidden="true"></div><?php endfor; ?>
<?php for($day=1;$day<=(int)$first->format('t');$day++): $date=$first->format('Y-m-').sprintf('%02d',$day);$items=$group[$date]??[]; ?>
<section class="agenda-day <?= !$items?'sin-eventos ':'' ?><?= $date===$today?'hoy':'' ?>">
<div class="agenda-dayhead"><time datetime="<?= h($date) ?>"><?= $day ?><span class="agenda-dia-largo"> de <?= h($months[(int)$first->format('n')-1]) ?></span><?= $date===$today?' · Hoy':'' ?></time><?php if(admin_es_super()): ?><a href="?nuevo=1&amp;fecha=<?= h($date) ?>" aria-label="Agregar evento el <?= h($date) ?>">+</a><?php endif; ?></div>
<?php foreach($items as $e){agenda_tarjeta($e);} ?>
<?php if(!$items&&$date===$today): ?><span class="agenda-ayuda">Sin eventos hoy</span><?php endif; ?>
</section>
<?php endfor; ?></div>
<div class="agenda-leyenda" aria-label="Estados"><?php foreach(cb_agenda_estados() as $k=>$v): ?><span class="estado-<?= h($k) ?>"><?= h($v) ?></span><?php endforeach; ?></div>
<?php endif; ?>
<?php if(!$events): ?><section class="card"><h3>No hay eventos en este período</h3><p>Las fiestas con fecha aparecerán automáticamente. También puedes agregar visitas, reuniones y otros compromisos.</p></section><?php endif; ?>

<?php if(admin_es_super()): $mailCfg=cb_agenda_correo_config(); $mailHistory=cb_agenda_correo_historial(); $recipients=cb_agenda_correo_destinatarios($mailCfg); ?>
<details class="card agenda-ics" id="avisos" <?= agenda_query('correos')==='1'?'open':'' ?>>
<summary>Correos de la agenda · <?= $mailCfg['enabled']?'activados':'pausados' ?></summary>
<p><strong>Diario a las 09:00</strong>, con el detalle del día. <strong>Semanal los lunes a las 08:00</strong>, con el resumen de lunes a domingo. Hora de Chile.</p>
<p>El administrador recibe todos los eventos. Cada usuario activo con fiestas asignadas recibe solo las suyas en su correo registrado, aunque no tenga abierta la pantalla Agenda. También se avisa si no hay eventos en el período.</p>
<form method="post" class="agenda-form">
<?= admin_csrf_field() ?><input type="hidden" name="action" value="correos">
<div class="field agenda-ancho"><label for="correo_admin">Correo del administrador</label><input type="email" id="correo_admin" name="correo_admin" value="<?= h($mailCfg['email']) ?>" maxlength="254" required autocomplete="email"></div>
<div class="agenda-ancho"><label><input type="checkbox" name="enabled" value="1" style="width:auto" <?= $mailCfg['enabled']?'checked':'' ?>> Activar avisos diarios y semanales</label></div>
<div class="agenda-ancho"><button class="btn btn-cta">Guardar preferencias</button></div>
</form>
<p class="agenda-ayuda">Para cambiar el correo o las fiestas de una persona, usa <a href="usuarios.php">Usuarios</a>. Desactivarla o quitarle todas las asignaciones detiene sus avisos. Guardar estas preferencias no envía un correo de inmediato.</p>
<?php if(count($recipients)>1): ?><p><strong>Personas asignadas:</strong> <?= h(implode(', ',array_map(static fn($d)=>$d['usuario']['nombre'],array_slice($recipients,1)))) ?>.</p><?php endif; ?>
<?php if($mailHistory): ?><h3>Últimos avisos</h3><ul>
<?php foreach($mailHistory as $entry): ?><li><?= h(ucfirst($entry['tipo']).' · '.$entry['periodo'].' · '.($entry['destino']==='admin'?'Administrador':str_replace('usuario-','Usuario #',$entry['destino'])).' · '.(['enviado'=>'Aceptado por correo','enviando'=>'Necesita revisión','revisar'=>'Necesita revisión'][$entry['status']]??'Necesita revisión')) ?></li><?php endforeach; ?>
</ul><p class="agenda-ayuda">Un aviso que necesita revisión no se vuelve a enviar automáticamente, para evitar duplicados.</p><?php endif; ?>
</details>
<?php endif; ?>
<details class="card agenda-ics"><summary>Suscribir en el celular</summary><p>Copia este enlace en la opción «Suscribirse a un calendario» de tu teléfono. Es personal: quien lo tenga podrá ver tus eventos.</p>
<div class="field"><label for="ics">Enlace de suscripción</label><input id="ics" readonly value="<?= h(cb_agenda_url_ics($u)) ?>" onclick="this.select()"></div><a class="btn btn-ghost" href="<?= h(cb_agenda_url_ics($u)) ?>">Descargar calendario .ics</a><p class="agenda-ayuda">El calendario del teléfono decide cada cuánto actualizarlo. No incluye notas, teléfonos ni datos de pago.</p></details>
<?php endif; ?></main></div></body></html>
