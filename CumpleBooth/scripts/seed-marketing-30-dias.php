<?php
/** Importa un JSON editorial, idempotente por fecha+título. Nunca publica ni gasta créditos. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit(2);}
require __DIR__.'/../public/lib.marketing.php';
if(count($argv)!==2){fwrite(STDERR,"Uso: php scripts/seed-marketing-30-dias.php <plan.json|--ejemplo>\n");exit(2);}
$file=$argv[1]==='--ejemplo'?__DIR__.'/../design/generadores/arte/marketing-ejemplos.json':$argv[1];
try{
    if(!is_file($file)||filesize($file)>5*1024*1024){throw new InvalidArgumentException('El JSON no existe o supera 5 MB.');}
    $rows=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($rows)){throw new InvalidArgumentException('Se esperaba una lista de piezas.');}
    $result=cb_marketing_seed($rows,dirname(realpath($file)));
    echo 'Creadas: '.$result['creadas'].'; omitidas: '.$result['omitidas'].". Estado inicial: idea.\n";
}catch(InvalidArgumentException|DomainException|JsonException $e){fwrite(STDERR,"Importación rechazada: ".$e->getMessage()."\n");exit(1);}
catch(Throwable $e){fwrite(STDERR,"No se completó la importación. Revisa migración025 y almacenamiento privado.\n");exit(1);}
