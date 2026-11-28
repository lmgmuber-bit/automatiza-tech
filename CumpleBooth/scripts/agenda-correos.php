<?php
/** Cron CLI de Agenda. Simula por defecto. --enviar activa el SMTP ya configurado. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(2);}
require __DIR__.'/../public/lib.agenda.php';
if(array_diff(array_slice($argv,1),['--enviar','--simular'])||(in_array('--enviar',$argv,true)&&in_array('--simular',$argv,true))){fwrite(STDERR,"Uso: php scripts/agenda-correos.php [--simular|--enviar]\n");exit(2);}
try{
    $result=cb_agenda_correo_ejecutar(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')),!in_array('--enviar',$argv,true));
    foreach($result as $tipo=>$estado){echo "$tipo: $estado\n";}
    if(!$result){echo "Sin envíos pendientes o notificaciones desactivadas.\n";}
    exit(array_intersect($result,['revisar','sin_smtp','revisar_copias'])?1:0);
}catch(Throwable $e){fwrite(STDERR,"Agenda: no se completó la ejecución; revisar configuración y almacenamiento privados.\n");exit(1);}
