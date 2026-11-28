<?php
/** V6: marca y MIME con fixtures; ningún envío ni destinatario real. */
$v6 = ['id'=>'00000000-0000-4000-8000-000000000906','nombre'=>'María','proyecto'=>'el sitio web de Café Aurora',
    'modo'=>'real','destinos'=>['correo'=>'cliente@ejemplo.cl']];
foreach ([
    ['con nombre','María','real','María, ¿cómo te fue con AutomatizaTech?'],
    ['sin nombre','','real','¿Cómo te fue con AutomatizaTech?'],
    ['sin campo',null,'real','¿Cómo te fue con AutomatizaTech?'],
    ['CRLF y controles',"  María\r\n\t\x00\x7f  ",'real','María, ¿cómo te fue con AutomatizaTech?'],
    ['solo controles',"\r\n\x00",'real','¿Cómo te fue con AutomatizaTech?'],
    ['60 caracteres',str_repeat('á',65),'real',str_repeat('á',60).', ¿cómo te fue con AutomatizaTech?'],
    ['prueba','María','prueba','[Prueba] María, ¿cómo te fue con AutomatizaTech?'],
    ['prueba sin nombre','','prueba','[Prueba] ¿Cómo te fue con AutomatizaTech?'],
] as [$caso,$nombre,$modo,$esperado]) {
    $i=$v6; $i['modo']=$modo;
    if($nombre===null)unset($i['nombre']);else $i['nombre']=$nombre;
    at_rg_enviar_correo($i,906); $mail=end($GLOBALS['mails']);
    ok($mail['subject']===$esperado,'V6 asunto '.$caso);
}
at_rg_enviar_correo($v6,906);$mail=end($GLOBALS['mails']);$html=$mail['body'];$texto=$mail['mailer']->AltBody;
$baja=add_query_arg('token',at_rg_token_baja(906,$v6),home_url('/resena/baja/'));
ok(str_contains($html,'Logo-slogan-tagline-2x.png') && str_contains($html,'alt="AutomatizaTech" width="220"'),'V6 logo AT de 220 px');
ok(str_contains($html,'💬') && str_contains($html,'¿Nos cuentas tu experiencia?'),'V6 titulo e icono');
ok(str_contains($html,'Hola María, gracias por confiar en AutomatizaTech para el sitio web de Café Aurora.'),'V6 saludo literal');
$parrafo='Si quieres, puedes contar tu experiencia en Google con una reseña honesta: nos ayuda a mejorar y les sirve a otros negocios. Si necesitas ayuda con algo, respóndenos aquí.';
ok(str_contains($html,$parrafo) && str_contains($texto,$parrafo),'V6 parrafo literal HTML y texto');
ok(str_contains($html,'href="'.home_url('/resena/').'"') && str_contains($html,'⭐ Dejar mi reseña en Google') && str_contains($html,'Toma menos de un minuto.'),'V6 boton aprobado a resena');
ok(str_contains($html,'Un abrazo,<br><strong>Equipo AutomatizaTech</strong>'),'V6 firma de equipo');
ok((bool)preg_match('/display:none[^>]*>Tu opinión nos ayuda a mejorar y les sirve a otros negocios\.<\/div>/u',$html),'V6 preheader oculto literal');
ok(substr_count($html,'⭐')===1 && !preg_match('/5\s*estrellas|cinco\s*estrellas|[★☆🌟]/iu',$html),'V6 sin puntuacion ni pedido de estrellas salvo icono aprobado');
ok(str_contains($html,'role="presentation"') && str_contains($html,'max-width:600px') && !preg_match('/<svg|<link|<script|<style/i',$html),'V6 tablas e inline sin fuentes SVG ni scripts');
foreach ([
    ['web','https://automatizatech.cl','Sitio web'],
    ['correo','mailto:contacto@automatizatech.cl','Correo'],
    ['whatsapp','https://wa.me/56927002984','WhatsApp'],
    ['instagram','https://www.instagram.com/automatizatech.cl','Instagram'],
] as [$icono,$url,$alt]) {
    ok((bool)preg_match('~<a href="'.preg_quote(esc_url($url),'~').'"[^>]*>\s*<img src="'.preg_quote(get_template_directory_uri().'/assets/images/correo/'.$icono.'.png','~').'" alt="'.$alt.'" width="20" height="20"~',$html),'V6 pie enlace e icono '.$icono);
    ok(str_contains($texto,$url),'V6 texto enlace '.$icono);
    $png=__DIR__.'/../../wp-content/themes/automatiza-tech/assets/images/correo/'.$icono.'.png';
    $size=is_file($png)?getimagesize($png):false;
    ok($size && $size[0]===40 && $size[1]===40 && $size[2]===IMAGETYPE_PNG,'V6 PNG 40x40 '.$icono);
}
ok(str_contains($html,esc_url($baja)) && str_contains($html,'No recibir más invitaciones a reseñar por correo'),'V6 baja HTML');
ok(in_array('List-Unsubscribe: <'.$baja.'>',$mail['headers'],true) && in_array('List-Unsubscribe-Post: List-Unsubscribe=One-Click',$mail['headers'],true),'V6 cabeceras baja un clic');
ok(str_contains($texto,'Hola María, gracias por confiar en AutomatizaTech para el sitio web de Café Aurora.') && str_contains($texto,'Equipo AutomatizaTech') && str_contains($texto,home_url('/resena/')) && str_contains($texto,$baja) && !preg_match('/<\/?(?:html|p|a|table)\b/i',$texto),'V6 AltBody texto y enlaces');
$i=$v6;$i['nombre']='<b>María</b>';$i['proyecto']='<img src=x onerror=alert(1)>';
$html=at_rg_correo_html($i,906);
ok(!str_contains($html,$i['proyecto']) && str_contains($html,esc_html($i['proyecto'])) && !str_contains($html,'<b>María</b>'),'V6 escape nombre y proyecto');
// Un correo operativo posterior no hereda baja ni AltBody de la invitación.
at_rg_mail_cerrado('operacion@ejemplo.cl','Aviso sintético','<p>Aviso</p>');$mail=end($GLOBALS['mails']);
ok($mail['mailer']->AltBody==='' && !preg_match('/List-Unsubscribe/i',implode("\n",$mail['headers'])),'V6 sin fuga al siguiente correo');
$inyectorV6=static function($args){$args['headers'][]='Bcc: intruso@ejemplo.cl';$args['headers'][]='List-Unsubscribe: <https://intruso.ejemplo.cl>';return $args;};
add_filter('wp_mail',$inyectorV6);$GLOBALS['opts']['at_resena_copia_oculta']='copia@ejemplo.cl';
$antesV6=count($GLOBALS['mails']);at_rg_enviar_correo($v6,906);$mail=$GLOBALS['mails'][$antesV6];remove_filter('wp_mail',$inyectorV6);
ok($mail['mailer']->to===['cliente@ejemplo.cl'] && !$mail['mailer']->cc && !$mail['mailer']->bcc && !str_contains(implode("\n",$mail['headers']),'intruso') && in_array('List-Unsubscribe: <'.$baja.'>',$mail['headers'],true),'V6 V7 baja conserva cierre sin CCO con filtros');
unset($GLOBALS['opts']['at_resena_copia_oculta']);
