<?php
/** Calendario editorial del admin; publicación y métricas se registran manualmente. */
require __DIR__.'/../lib.marketing.php';
require __DIR__.'/config.php';
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
session_name('cc_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);session_start();
header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');
require __DIR__.'/_acceso.php';admin_exigir_super();
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function admin_csrf_token():string{if(empty($_SESSION['csrf'])){$_SESSION['csrf']=bin2hex(random_bytes(16));}return $_SESSION['csrf'];}
function admin_csrf_check():bool{$t=$_POST['csrf']??'';return is_string($t)&&$t!==''&&hash_equals($_SESSION['csrf']??'',$t);}
function admin_csrf_field():string{return '<input type="hidden" name="csrf" value="'.h(admin_csrf_token()).'">';}
function contenido_query(string $key,string $default=''):string{return isset($_GET[$key])&&is_string($_GET[$key])?$_GET[$key]:$default;}
function contenido_enlace_seguro($value):?string{
    try{return cb_marketing_url($value);}catch(InvalidArgumentException $e){return null;}
}
function contenido_id($value):int{
    if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,17}$/D',$value)){throw new InvalidArgumentException('Pieza inválida.');}
    return (int)$value;
}
function contenido_vacia(string $date):array{
    return ['id'=>null,'fecha_programada'=>$date,'hora'=>'','formato'=>'post','pilar'=>'educativo','titulo'=>'','gancho'=>'','copy'=>'',
        'primer_comentario'=>'','asset_key'=>null,'asset_url_externa'=>'','estado'=>'idea','publicado_url'=>'','publicado_at'=>null,'metricas_json'=>null,'notas'=>''];
}
function contenido_tarjeta(array $p):void{?>
<a class="contenido-pieza formato-<?= h($p['formato']) ?>" href="?id=<?= (int)$p['id'] ?>">
<span><?= h(cb_marketing_formatos()[$p['formato']]) ?> · <?= h($p['hora']?substr($p['hora'],0,5):'Sin hora') ?></span>
<strong><?= h($p['titulo']) ?></strong><small><?= h(cb_marketing_estados()[$p['estado']]) ?></small>
</a><?php }
$today=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');
$month=contenido_query('mes',substr($today,0,7));$view=contenido_query('vista','mes');$error='';$piece=null;$items=[];$weeks=[];$pillars=[];
$filters=['estado'=>contenido_query('estado'),'pilar'=>contenido_query('pilar')];$weekDay=contenido_query('semana',$today);
$weekStart=(new DateTimeImmutable($today))->modify('monday this week')->format('Y-m-d');
try{
    if(!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$month)||!cb_marketing_fecha($month.'-01')){throw new InvalidArgumentException('Mes inválido.');}
    if(!in_array($view,['mes','semana'],true)||!cb_marketing_fecha($weekDay)){throw new InvalidArgumentException('Vista o semana inválida.');}
    if($filters['estado']!==''&&!isset(cb_marketing_estados()[$filters['estado']])){throw new InvalidArgumentException('Estado inválido.');}
    $weekStart=(new DateTimeImmutable($weekDay))->modify('monday this week')->format('Y-m-d');
    if(contenido_query('id')!==''){$piece=cb_marketing_obtener(contenido_id(contenido_query('id')));$month=substr($piece['fecha_programada'],0,7);}
    elseif(contenido_query('nueva')==='1'){
        $date=contenido_query('fecha',$today);if(!cb_marketing_fecha($date)){throw new InvalidArgumentException('Fecha inválida.');}
        $piece=contenido_vacia($date);
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(empty($_POST)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){http_response_code(413);throw new InvalidArgumentException('La subida supera el límite del servidor. Prueba un archivo más pequeño.');}
        if(!admin_csrf_check()){http_response_code(403);exit('La sesión del formulario venció. Vuelve a abrirlo.');}
        $action=$_POST['action']??'';
        if($action==='semana'){
            $savedWeek=cb_marketing_semana_guardar($_POST);
            header('Location: contenido.php?vista=semana&semana='.$savedWeek['semana'].'&guardado=1',true,303);exit;
        }
        $id=empty($_POST['id'])?null:contenido_id($_POST['id']);
        $piece=$id!==null?cb_marketing_obtener($id):contenido_vacia($today);
        if($action==='guardar'){
            $allowed=['fecha_programada','hora','formato','pilar','titulo','gancho','copy','primer_comentario','asset_url_externa','estado','publicado_url','notas'];
            $data=array_intersect_key($_POST,array_flip($allowed));
            // Preservar la edición al mostrar un error; toda salida pasa por h().
            foreach($data as$k=>$v){if(is_string($v)){$piece[$k]=$v;}}
            $piece=cb_marketing_guardar($data,$id);
        }elseif($id===null){throw new InvalidArgumentException('Primero guarda la pieza.');}
        elseif($action==='estado'){$piece=cb_marketing_guardar(['estado'=>$_POST['estado']??''],$id);}
        elseif($action==='publicar'){$piece=cb_marketing_guardar(['estado'=>'publicada','publicado_url'=>$_POST['publicado_url']??''],$id);}
        elseif($action==='metricas'){$piece=cb_marketing_guardar(['metricas'=>$_POST['metricas']??[]],$id);}
        elseif($action==='asset'){
            $file=$_FILES['asset']??null;
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)){throw new InvalidArgumentException('La subida no se completó. Selecciona un archivo de hasta 60 MB.');}
            $piece=cb_marketing_asignar_asset($id,$file['tmp_name'],true);
        }else{throw new InvalidArgumentException('Acción inválida.');}
        header('Location: contenido.php?id='.$piece['id'].'&guardado=1',true,303);exit;
    }
    $first=new DateTimeImmutable($month.'-01');
    $items=$view==='semana'?cb_marketing_listar($weekStart,(new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d'),$filters):
        cb_marketing_listar($month.'-01',$first->format('Y-m-t'),$filters);
    $pillars=cb_marketing_pilares();$weeks=cb_marketing_semanas();$selectedWeek=cb_marketing_semana_obtener($weekStart);
}catch(InvalidArgumentException|DomainException $e){if(http_response_code()!==413){http_response_code(400);}$error=$e->getMessage();}
catch(Throwable $e){http_response_code(503);$error='Contenido no está disponible. Comprueba la migración 025 y el almacén privado.';}
$first=$first??new DateTimeImmutable(substr($today,0,7).'-01');
$months=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$monthLabel=ucfirst($months[(int)$first->format('n')-1]).' '.$first->format('Y');
$group=[];foreach($items as$p){$group[$p['fecha_programada']][]=$p;}
$selectedWeek=$selectedWeek??['semana'=>$weekStart,'seguidores'=>0,'alcance'=>0,'guardados'=>0,'mensajes_whatsapp'=>0,'notas'=>''];

?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contenido · CumpleClick</title><style><?php require __DIR__.'/_style.css.php'; ?></style>
<style>
.contenido-wrap{max-width:1400px}.contenido-brand{display:flex;align-items:center;gap:14px}.contenido-brand img{width:52px;height:52px}.contenido-brand h1,.contenido-brand p{margin:0}
.contenido-bar,.contenido-nav,.contenido-filtros,.contenido-monthbar{display:flex;align-items:center;flex-wrap:wrap;gap:10px}.contenido-bar,.contenido-monthbar{justify-content:space-between;margin:20px 0}
.contenido-nav{padding:5px;background:var(--card-bg);border-radius:30px}.contenido-nav .activo{background:var(--text);color:white}.contenido-monthbar h2{margin:0}.contenido-filtros{align-items:end;margin:16px 0}.contenido-filtros label{display:block}.contenido-filtros select{min-width:160px}
.contenido-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;background:var(--border);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.contenido-weekday{text-align:center;padding:10px;background:var(--bg2);font-weight:700}.contenido-day{min-width:0;min-height:125px;background:white;padding:10px}.contenido-day.empty{background:var(--bg2)}.contenido-day.today{box-shadow:inset 0 0 0 2px var(--primary)}
.contenido-dayhead{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}.contenido-dayhead a{min-width:30px;min-height:30px;text-align:center}.contenido-dia-largo{display:none}
.contenido-pieza{display:flex;flex-direction:column;padding:9px 10px;gap:3px;margin:6px 0;border-left:4px solid var(--primary);border-radius:10px;background:var(--primary-soft);color:var(--text);text-decoration:none;overflow-wrap:anywhere;line-height:1.35}
.contenido-pieza span,.contenido-pieza small{font-size:.8rem}.contenido-pieza:hover{box-shadow:var(--shadow-hover)}.formato-post{border-color:var(--cta);background:var(--bg2)}.formato-carrusel{border-color:var(--primary-dark);background:var(--bg2)}.formato-historia{border-color:var(--accent);background:var(--warn-soft)}
.contenido-ficha{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:22px;margin:24px 0}.contenido-ficha>.card{min-width:0;margin:0;align-self:start}.contenido-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.contenido-wide{grid-column:1/-1}.contenido-form input,.contenido-form select,.contenido-form textarea{width:100%;min-width:0}
.contenido-preview{display:block;max-width:100%;max-height:380px;object-fit:contain;margin:16px auto;border-radius:var(--radius-sm);background:var(--bg2)}.contenido-texto{width:100%;min-height:190px;resize:vertical;border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;font:inherit;color:var(--text);background:var(--bg2)}.contenido-estado{padding:6px 14px;border-radius:25px;background:var(--primary-soft);font-weight:700;display:inline-block}
.contenido-seccion{border-top:1px solid var(--border);margin-top:24px;padding-top:20px}.contenido-seccion form{display:grid;gap:10px}.contenido-seccion .btn{justify-self:start}.contenido-ayuda{font-size:.87rem;color:var(--text-muted)}.contenido-feedback{min-height:24px}
.contenido-week{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.contenido-week h3{margin:0}.contenido-metrics{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:22px;margin-top:24px}.contenido-metrics .card{margin:0;min-width:0}.contenido-chart{display:grid;gap:14px;padding:0;list-style:none}.contenido-chart span{display:block}.contenido-track{height:12px;background:var(--bg2);border-radius:8px;overflow:hidden}.contenido-track i{display:block;height:100%;background:var(--primary)}.contenido-track.guardados i{background:var(--cta)}
@media(max-width:760px){.contenido-wrap>.tabs{flex-wrap:nowrap;width:100%;max-width:100%;overflow-x:auto;border-radius:var(--radius-sm)}.contenido-wrap>.tabs .tab{flex:0 0 auto}.contenido-grid{display:block;background:none;border:0;box-shadow:none;overflow:visible}.contenido-weekday,.contenido-day.empty,.contenido-day.sin-piezas:not(.today){display:none}.contenido-day{min-height:0;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:12px}.contenido-dia-largo{display:inline}.contenido-ficha,.contenido-metrics,.contenido-form{grid-template-columns:1fr}.contenido-wide{grid-column:auto}.contenido-monthbar .contenido-nav{width:100%;flex-wrap:nowrap}.contenido-monthbar .btn{width:auto;flex:1;min-width:0;padding:0 8px}.contenido-bar>.btn{width:100%}.contenido-nav .btn{width:auto}.contenido-filtros{display:grid;grid-template-columns:1fr 1fr}.contenido-filtros select{min-width:0;width:100%}.contenido-filtros>.btn{width:100%}.contenido-ficha{gap:16px}.contenido-preview{max-height:320px}}
.contenido-wrap>main{gap:12px}.contenido-filtros select,.contenido-seccion select,.contenido-seccion input[type=url]{min-height:44px;border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 12px;font:inherit;color:var(--text);background:var(--card-bg)}
@media(max-width:760px){.contenido-wrap .topbar{gap:8px;margin-bottom:14px}.contenido-brand img{width:42px;height:42px}.contenido-brand h1{font-size:1.8rem}.contenido-brand p{font-size:.85rem}.contenido-wrap>.tabs{margin-bottom:12px}.contenido-bar,.contenido-monthbar{margin:6px 0}.contenido-bar{display:grid;grid-template-columns:1fr auto;gap:6px}.contenido-bar>.btn{width:auto;padding:0 12px;font-size:.88rem}.contenido-bar .contenido-nav{gap:2px}.contenido-bar .contenido-nav .btn{padding:0 10px;font-size:.88rem}.contenido-filtros{margin:6px 0}.contenido-wrap>main>p.contenido-ayuda{margin:0}}
</style></head><body><div class="wrap contenido-wrap">
<header class="topbar"><div class="contenido-brand"><img src="../brand/cumpleclick-mark.svg" alt=""><div><h1>Contenido</h1><p class="muted">Cada publicación, a tu ritmo.</p></div></div><?= admin_usuario_chip() ?></header>
<?= admin_nav('marketing') ?><main>
<?php if($error): ?><p class="alert alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if(contenido_query('guardado')==='1'): ?><p class="alert alert-ok" role="status">Cambios guardados.</p><?php endif; ?>
<?php if($piece!==null): ?>
<a href="?mes=<?= h(substr($piece['fecha_programada'],0,7)) ?>">← Volver al calendario</a>
<div class="contenido-ficha"><section class="card">
<h2><?= $piece['id']?'Editar publicación':'Nueva pieza' ?></h2><span class="contenido-estado"><?= h(cb_marketing_estados()[$piece['estado']]??'Revisa el estado') ?></span>
<form method="post" class="contenido-form" style="margin-top:20px">
<?= admin_csrf_field() ?><input type="hidden" name="action" value="guardar"><input type="hidden" name="id" value="<?= (int)$piece['id'] ?>">
<div class="field contenido-wide"><label for="titulo">Título para organizarte</label><input id="titulo" name="titulo" value="<?= h($piece['titulo']) ?>" maxlength="160" required></div>
<div class="field"><label for="fecha_programada">Fecha</label><input type="date" id="fecha_programada" name="fecha_programada" value="<?= h($piece['fecha_programada']) ?>" required></div>
<div class="field"><label for="hora">Hora de Chile</label><input type="time" id="hora" name="hora" value="<?= h(substr($piece['hora']??'',0,5)) ?>"></div>
<div class="field"><label for="formato">Formato</label><select id="formato" name="formato"><?php foreach(cb_marketing_formatos()as$k=>$v): ?><option value="<?= h($k) ?>" <?= $piece['formato']===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="pilar">Pilar del contenido</label><input id="pilar" name="pilar" list="pilares" value="<?= h($piece['pilar']) ?>" maxlength="80" required><datalist id="pilares"><?php foreach(array_unique(array_merge(['educativo','prueba social','temáticas','detrás de cámaras','oferta','comunidad'],$pillars))as$v): ?><option value="<?= h($v) ?>"><?php endforeach; ?></datalist></div>
<div class="field contenido-wide"><label for="gancho">Gancho</label><textarea id="gancho" name="gancho" rows="2" maxlength="500"><?= h($piece['gancho']) ?></textarea></div>
<div class="field contenido-wide"><label for="copy">Texto de la publicación</label><textarea id="copy" name="copy" rows="9" maxlength="20000"><?= h($piece['copy']) ?></textarea><small>Incluye el gancho en este texto si quieres publicarlo. Los tres hashtags se agregan al copiar.</small></div>
<div class="field contenido-wide"><label for="primer_comentario">Primer comentario</label><textarea id="primer_comentario" name="primer_comentario" rows="2" maxlength="2200"><?= h($piece['primer_comentario']) ?></textarea><small>El hashtag de la temática va aquí.</small></div>
<div class="field contenido-wide"><label for="asset_url_externa">Enlace externo del material (opcional)</label><input type="url" id="asset_url_externa" name="asset_url_externa" value="<?= h($piece['asset_url_externa']??'') ?>" maxlength="2048"></div>
<?php if($piece['estado']==='publicada'): ?><div class="field contenido-wide"><label for="publicado_url">URL de la publicación</label><input type="url" id="publicado_url" name="publicado_url" value="<?= h($piece['publicado_url']??'') ?>" required></div><?php endif; ?>
<div class="field contenido-wide"><label for="notas">Notas</label><textarea id="notas" name="notas" rows="3" maxlength="10000"><?= h($piece['notas']) ?></textarea></div>
<div class="contenido-wide"><button class="btn btn-cta">Guardar pieza</button></div>
</form>
<?php if($piece['id']): ?>
<section class="contenido-seccion"><h3>Estado de la pieza</h3><p class="contenido-ayuda">«Programada» organiza tu calendario. La publicación en Instagram se hace manualmente.</p>
<?php $next=cb_marketing_transiciones($piece['estado']); if($next): ?><form method="post"><?= admin_csrf_field() ?><input type="hidden" name="action" value="estado"><input type="hidden" name="id" value="<?= (int)$piece['id'] ?>">
<label for="estado">Pasar a</label><select id="estado" name="estado" required><option value="">Elige el siguiente estado</option><?php foreach($next as$s): if($s==='publicada'){continue;} ?><option value="<?= h($s) ?>"><?= h(cb_marketing_estados()[$s]) ?></option><?php endforeach; ?></select><button class="btn btn-ghost">Cambiar estado</button></form><?php endif; ?>
<?php if(in_array($piece['estado'],['aprobada','programada'],true)): ?><form method="post" style="margin-top:18px"><?= admin_csrf_field() ?><input type="hidden" name="action" value="publicar"><input type="hidden" name="id" value="<?= (int)$piece['id'] ?>"><label for="url_publicada">Enlace del post que ya publicaste</label><input type="url" id="url_publicada" name="publicado_url" required maxlength="2048" placeholder="https://www.instagram.com/p/..."><button class="btn btn-cta">Marcar publicada</button></form><?php endif; ?>
<?php if($publishedUrl=contenido_enlace_seguro($piece['publicado_url'])): ?><p><a href="<?= h($publishedUrl) ?>" target="_blank" rel="noopener noreferrer">Ver publicación ↗</a></p><?php endif; ?>
</section>
<section class="contenido-seccion"><h3>Resultados de esta pieza</h3><form method="post" class="contenido-form"><?= admin_csrf_field() ?><input type="hidden" name="action" value="metricas"><input type="hidden" name="id" value="<?= (int)$piece['id'] ?>">
<?php $metrics=json_decode($piece['metricas_json']??'{}',true)?:[]; foreach(cb_marketing_metricas()as$k=>$v): ?><div class="field"><label for="metrica_<?= h($k) ?>"><?= h($v) ?></label><input type="number" id="metrica_<?= h($k) ?>" name="metricas[<?= h($k) ?>]" min="0" max="2147483647" step="1" value="<?= h($metrics[$k]??'') ?>"></div><?php endforeach; ?>
<button class="btn btn-ghost contenido-wide">Guardar métricas</button></form></section>
<?php endif; ?>
</section><aside class="card"><h2>Material y texto listo</h2>
<?php if($piece['asset_key']): $src='contenido-media.php?id='.(int)$piece['id']; if(str_ends_with($piece['asset_key'],'.mp4')): ?><video class="contenido-preview" controls preload="metadata" src="<?= h($src) ?>"></video><?php else: ?><img class="contenido-preview" src="<?= h($src) ?>" alt="Material de la publicación"><?php endif; ?><a href="<?= h($src) ?>" target="_blank" rel="noopener">Abrir archivo</a><?php else: ?><p class="contenido-ayuda">Aún no hay un archivo. Puedes subirlo después de guardar la pieza.</p><?php endif; ?>
<?php if($externalUrl=contenido_enlace_seguro($piece['asset_url_externa'])): ?><p><a href="<?= h($externalUrl) ?>" target="_blank" rel="noopener noreferrer">Abrir material externo ↗</a></p><?php endif; ?>
<?php if($piece['id']): ?><form method="post" enctype="multipart/form-data" class="contenido-seccion"><?= admin_csrf_field() ?><input type="hidden" name="action" value="asset"><input type="hidden" name="id" value="<?= (int)$piece['id'] ?>"><label for="asset">Imagen o video</label><input type="file" id="asset" name="asset" accept="image/jpeg,image/png,image/webp,video/mp4" required><p class="contenido-ayuda">JPG, PNG, WebP o MP4, hasta 60 MB. Para un carrusel, sube la portada y guarda el enlace del conjunto en «Enlace externo».</p><button class="btn btn-ghost">Subir archivo</button></form><?php endif; ?>
<section class="contenido-seccion"><h3>Texto listo para pegar</h3><p class="contenido-ayuda">Versión guardada, con los tres hashtags fijos. Guarda los cambios antes de copiar.</p><textarea id="texto-listo" class="contenido-texto" readonly><?= h(cb_marketing_texto_listo($piece)) ?></textarea><button type="button" class="btn btn-cta" data-copy="texto-listo">Copiar texto</button>
<p><button type="button" class="btn btn-ghost" data-copy="primer_comentario">Copiar primer comentario</button></p><p class="contenido-feedback" id="copy-status" role="status" aria-live="polite"></p></section>
</aside></div>
<?php elseif($error===''): ?>
<div class="contenido-bar"><div class="contenido-nav"><a class="btn <?= $view==='mes'?'activo':'' ?>" href="?mes=<?= h($month) ?>">Calendario</a><a class="btn <?= $view==='semana'?'activo':'' ?>" href="?vista=semana">Semana</a></div><a class="btn btn-cta" href="?nueva=1">+ Nueva pieza</a></div>
<form method="get" class="contenido-filtros"><input type="hidden" name="mes" value="<?= h($month) ?>"><input type="hidden" name="vista" value="<?= h($view) ?>"><input type="hidden" name="semana" value="<?= h($weekStart) ?>">
<div><label for="filtro_estado">Estado</label><select id="filtro_estado" name="estado"><option value="">Todos</option><?php foreach(cb_marketing_estados()as$k=>$v): ?><option value="<?= h($k) ?>" <?= $filters['estado']===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
<div><label for="filtro_pilar">Pilar</label><select id="filtro_pilar" name="pilar"><option value="">Todos</option><?php foreach($pillars as$v): ?><option value="<?= h($v) ?>" <?= $filters['pilar']===$v?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></div><button class="btn btn-ghost">Filtrar</button><a href="?vista=<?= h($view) ?>&mes=<?= h($month) ?>">Limpiar filtros</a></form>
<?php if($view==='mes'): ?>
<div class="contenido-monthbar"><h2><?= h($monthLabel) ?></h2><div class="contenido-nav"><a class="btn btn-ghost" href="?mes=<?= h($first->modify('-1 month')->format('Y-m')) ?>">← Anterior</a><a class="btn btn-ghost" href="contenido.php">Hoy</a><a class="btn btn-ghost" href="?mes=<?= h($first->modify('+1 month')->format('Y-m')) ?>">Siguiente →</a></div></div>
<p class="contenido-ayuda"><?= count($items) ?> piezas. Toca una tarjeta para editar o el + de un día para crear.</p>
<div class="contenido-grid" aria-label="Calendario editorial">
<?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']as$name): ?><div class="contenido-weekday"><?= $name ?></div><?php endforeach; ?>
<?php for($i=1;$i<(int)$first->format('N');$i++): ?><div class="contenido-day empty" aria-hidden="true"></div><?php endfor; ?>
<?php for($day=1;$day<=(int)$first->format('t');$day++): $date=$first->format('Y-m-').sprintf('%02d',$day);$dayItems=$group[$date]??[]; ?>
<section class="contenido-day <?= !$dayItems?'sin-piezas ':'' ?><?= $date===$today?'today':'' ?>"><div class="contenido-dayhead"><time datetime="<?= h($date) ?>"><strong><?= $day ?></strong><span class="contenido-dia-largo"> de <?= h($months[(int)$first->format('n')-1]) ?></span><?= $date===$today?' · Hoy':'' ?></time><a href="?nueva=1&amp;fecha=<?= h($date) ?>" aria-label="Crear pieza el <?= h($date) ?>">+</a></div>
<?php foreach($dayItems as$p){contenido_tarjeta($p);} if(!$dayItems&&$date===$today): ?><span class="contenido-ayuda">Sin publicaciones para hoy</span><?php endif; ?></section>
<?php endfor; ?></div>
<?php if(!$items): ?><section class="card"><h3>Tu calendario está listo para empezar</h3><p>Agrega una pieza o carga el plan aprobado. Las publicaciones aparecerán en su fecha.</p></section><?php endif; ?>
<?php else: ?>
<div class="contenido-monthbar"><h2>Semana del <?= h((new DateTimeImmutable($weekStart))->format('d/m/Y')) ?></h2><div class="contenido-nav"><a class="btn btn-ghost" href="?vista=semana&semana=<?= h((new DateTimeImmutable($weekStart))->modify('-7 days')->format('Y-m-d')) ?>">← Anterior</a><a class="btn btn-ghost" href="?vista=semana">Esta semana</a><a class="btn btn-ghost" href="?vista=semana&semana=<?= h((new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d')) ?>">Siguiente →</a></div></div>
<div class="contenido-week"><?php foreach($group as$date=>$pieces): ?><section><h3><?= h((new DateTimeImmutable($date))->format('d/m')) ?></h3><?php foreach($pieces as$p){contenido_tarjeta($p);} ?></section><?php endforeach; ?></div>
<?php if(!$items): ?><p class="card">No hay piezas para esta semana con los filtros elegidos.</p><?php endif; ?>
<div class="contenido-metrics"><section class="card"><h2>Revisión semanal</h2><p class="contenido-ayuda">Copia los resultados desde Instagram Insights. En WhatsApp cuenta conversaciones nuevas, no cada mensaje.</p>
<form method="post" class="contenido-form"><?= admin_csrf_field() ?><input type="hidden" name="action" value="semana"><div class="field contenido-wide"><label for="semana">Semana</label><input type="date" id="semana" name="semana" value="<?= h($weekStart) ?>" required><small>Se guarda con el lunes de la semana elegida.</small></div>
<?php foreach(['seguidores'=>'Seguidores totales','alcance'=>'Alcance','guardados'=>'Guardados','mensajes_whatsapp'=>'Conversaciones por WhatsApp']as$k=>$v): ?><div class="field"><label for="sem_<?= h($k) ?>"><?= h($v) ?></label><input id="sem_<?= h($k) ?>" name="<?= h($k) ?>" type="number" min="0" max="2147483647" step="1" value="<?= (int)$selectedWeek[$k] ?>" required></div><?php endforeach; ?>
<div class="field contenido-wide"><label for="notas_semana">Notas de la semana</label><textarea id="notas_semana" name="notas" rows="3" maxlength="10000"><?= h($selectedWeek['notas']) ?></textarea><small>Anota aquí compartidos, clics al enlace y lo que quieras probar la próxima semana.</small></div><button class="btn btn-cta contenido-wide">Guardar semana</button></form></section>
<section class="card"><h2>Cómo va el contenido</h2><p class="contenido-ayuda">Alcance en violeta · Guardados en fucsia. Cada serie usa su propia escala.</p>
<?php $chart=array_slice($weeks,0,8);$maxReach=max(array_merge([1],array_map(static fn($w)=>(int)$w['alcance'],$chart)));$maxSave=max(array_merge([1],array_map(static fn($w)=>(int)$w['guardados'],$chart))); ?>
<ul class="contenido-chart"><?php foreach(array_reverse($chart)as$w): ?><li><strong>Semana <?= h((new DateTimeImmutable($w['semana']))->format('d/m')) ?></strong><span>Alcance: <?= (int)$w['alcance'] ?></span><div class="contenido-track" aria-hidden="true"><i style="width:<?= round(100*(int)$w['alcance']/$maxReach,2) ?>%"></i></div><span>Guardados: <?= (int)$w['guardados'] ?></span><div class="contenido-track guardados" aria-hidden="true"><i style="width:<?= round(100*(int)$w['guardados']/$maxSave,2) ?>%"></i></div><small><?= (int)$w['seguidores'] ?> seguidores · <?= (int)$w['mensajes_whatsapp'] ?> conversaciones por WhatsApp</small></li><?php endforeach; ?></ul>
<?php if(!$chart): ?><p>Aún no hay métricas. Guarda la primera semana para ver el gráfico.</p><?php endif; ?></section></div>
<?php endif; endif; ?>
</main></div>
<script>
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{
 const field=document.getElementById(button.dataset.copy),status=document.getElementById('copy-status');
 if(!field||!status)return;
 try{await navigator.clipboard.writeText(field.value);status.textContent='Copiado. Ya puedes pegarlo en Instagram.';}
 catch(error){field.focus();field.select();status.textContent='Texto seleccionado. Usa Copiar en tu dispositivo.';}
}));
</script></body></html>
