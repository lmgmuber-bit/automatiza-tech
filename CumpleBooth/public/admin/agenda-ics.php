<?php
/** Suscripción de calendario: la firma reemplaza la sesión del navegador. */
require __DIR__.'/../lib.agenda.php';
header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer');
$uid=$_GET['u']??'0';$f=$_GET['f']??'';
if(!is_string($uid)||!preg_match('/^(0|[1-9][0-9]{0,17})$/D',$uid)||!is_string($f)){
    http_response_code(403);exit('No disponible');
}
try{
    $user=cb_agenda_usuario_ics((int)$uid,$f);
    if(!$user){http_response_code(403);exit('No disponible');}
    // Historial y futuro completos: cancelar conserva el UID y notifica CANCELLED.
    $ics=cb_agenda_ics(cb_agenda_listar('1000-01-01','9999-12-31',$user));
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="cumpleclick-agenda.ics"');
    echo $ics;
}catch(Throwable $e){http_response_code(503);echo 'Calendario temporalmente no disponible.';}
