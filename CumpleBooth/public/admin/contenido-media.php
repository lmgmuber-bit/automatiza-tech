<?php
/** Assets editoriales privados: solo superusuario, con sesión vigente. */
require __DIR__.'/../lib.marketing.php';
require __DIR__.'/../lib.admin-usuarios.php';
require __DIR__.'/config.php';
header('X-Content-Type-Options: nosniff');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
function contenido_media_error(int $code):void{
    http_response_code($code);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: private, no-store');
    echo $code===404?'No encontrado':'No disponible';exit;
}
// Preflight sin redirecciones, como ver-media.php; el portero común vuelve a validar la sesión.
if(empty($_COOKIE['cc_admin'])){contenido_media_error(403);}
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
session_name('cc_admin');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);session_start();
if(empty($_SESSION['admin_logged'])||time()-(int)($_SESSION['admin_seen']??0)>(int)cb_config('session_idle_seconds')
    ||time()-(int)($_SESSION['admin_started']??0)>(int)cb_config('session_absolute_seconds')){contenido_media_error(403);}
$uid=(int)($_SESSION['admin_usuario_id']??0);
$u=$uid===0?cb_admin_usuario_maestro():cb_admin_usuario_por_id($uid);
if(!$u||!$u['activo']||$u['debe_cambiar']||$u['rol']!=='super'){contenido_media_error(403);}
require __DIR__.'/_acceso.php';admin_exigir_super();session_write_close();
$id=$_GET['id']??'';
if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,17}$/D',$id)){contenido_media_error(404);}
try{$piece=cb_marketing_obtener((int)$id);}
catch(InvalidArgumentException $e){contenido_media_error(404);}
catch(Throwable $e){contenido_media_error(503);}
$key=$piece['asset_key']??'';$path=cb_marketing_media_path($key);
if($path===null||!is_file($path)){contenido_media_error(404);}
$ext=pathinfo($key,PATHINFO_EXTENSION);$types=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','mp4'=>'video/mp4'];
header('Content-Type: '.$types[$ext]);header('Content-Disposition: inline; filename="contenido.'.$ext.'"');
header('Cache-Control: private, no-store');header('Content-Length: '.filesize($path));readfile($path);
