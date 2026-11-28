<?php
// CLI privado: pone gift_mode='rsvp' en la invitación de la fiesta (la que usa el enlace de confirmados).
// Uso: php database/modo-regalo-confirmacion.php <slug>      Requiere la migración 028 aplicada.
if(PHP_SAPI!=='cli'){exit(2);}
require __DIR__.'/../public/lib.php';
require_once __DIR__.'/../public/lib.rsvp.php';
$slug=(string)($argv[1]??'');
if($slug===''||!cb_valid_public_slug($slug)){fwrite(STDERR,"Uso: php modo-regalo-confirmacion.php <slug>\n");exit(1);}
$pdo=cb_pdo();
$mig=$pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');$mig->execute(['028_rsvp_regalo']);
if(!$mig->fetchColumn()){fwrite(STDERR,"Falta aplicar aplicar-028.php antes.\n");exit(1);}
$id=cb_rsvp_invitacion_de_fiesta($slug);
if($id===null){fwrite(STDERR,"La fiesta no existe o no tiene invitación.\n");exit(1);}
$pdo->prepare('UPDATE cc_invitations SET gift_mode = ?, updated_at = ? WHERE id = ?')->execute(['rsvp',gmdate('Y-m-d H:i:s'),$id]);
echo "gift_mode=rsvp en la invitación $id\n";
