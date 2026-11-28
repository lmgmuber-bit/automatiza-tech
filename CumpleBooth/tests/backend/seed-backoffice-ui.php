<?php
if (PHP_SAPI !== 'cli') { exit(2); }
$root=getenv('CC_AUDIT_ROOT');require $root.'/public/lib.php';require $root.'/public/lib.admin-usuarios.php';
require $root.'/tests/backend/_migraciones.php';cb_test_migrar_todo(cb_pdo());$pdo=cb_pdo();$now=gmdate('Y-m-d H:i:s');
$parties=[];foreach(['qa-cumple','qa-baby','qa-ajena']as$i=>$slug){
 $parties[$slug]=['nombre'=>['Fiesta de Prueba','Baby Shower de Prueba','Evento de Otra Persona'][$i],
 'tema'=>$i===1?'baby-nube':'carreras','fecha'=>'2026-09-26','activa'=>true,'creada'=>$now,'service_plan'=>'full','edad'=>7,
 'event_type'=>$i===1?'baby_shower':'child_birthday','gallery_enabled'=>true,'galeriaPin'=>'9182',
 'invitados'=>[['name'=>'Invitada QA','g'=>'f'],['name'=>'Invitado QA','g'=>'m']]];
}
if(!cb_save_parties(['parties'=>$parties])){throw new RuntimeException('No se pudo crear fixture');}
foreach($parties as$slug=>$p){
 $id=cb_party_db_id($slug);
 cb_save_party_contacts($slug,[['name'=>'Contacto de Prueba','email'=>'cliente@example.invalid','phone'=>'+56900000000','relationship'=>'familiar']]);
 cb_save_party_billing($slug,['price_total'=>99990,'discount_percent'=>50,'deposit_amount'=>15000,'payment_note'=>'Registro de prueba']);
 cb_create_invitation(['party_id'=>$id,'theme_slug'=>$p['tema'],'admin_label'=>'Invitación de Prueba','birthday_person_name'=>$p['nombre'],
 'event_type'=>$p['event_type'],'event_date'=>'2026-09-26','event_time'=>'16:00','address'=>'Salón de Prueba, Comuna Ejemplo','message'=>'Te esperamos en esta celebración de prueba.']);
 cb_event_profile_ensure($id);
 $album=(int)cb_album_ensure($id)['id'];
 for($j=1;$j<=4;$j++){
  $img=imagecreatetruecolor(300,400);imagefill($img,0,0,imagecolorallocate($img,210-$j*10,190,230));
  imagestring($img,5,45,170,'IMAGEN DE PRUEBA',imagecolorallocate($img,70,35,110));
  $source=getenv('CC_STATE_DIR').'/fixture.jpg';imagejpeg($img,$source,85);imagedestroy($img);
  $key=cb_album_storage_key($slug,'jpg');$stored=cb_album_store_file($source,$key,false);
  cb_album_record_media($album,$id,['source'=>'guest','media_kind'=>'image','access_token'=>cb_opaque_token(16),'storage_key'=>$key,
  'thumb_storage_key'=>cb_album_make_thumbnail($stored,$slug,'jpg'),'poster_storage_key'=>null,'original_name'=>'foto-de-prueba.jpg','mime'=>'image/jpeg',
  'byte_size'=>filesize($stored),'width'=>300,'height'=>400,'duration_seconds'=>null,'sha256'=>hash_file('sha256',$stored),
  'contributor_name'=>'Invitada de Prueba','contributor_message'=>'Gracias por compartir este momento de prueba.',
  'moderation_status'=>$j<3?'approved':'pending','consent_version'=>cb_album_consent_version(),'uploader_hmac'=>cb_hmac('qa','audit')]);
 }
}
$s=$pdo->prepare('INSERT INTO cc_admin_users(email,nombre,password_hash,rol,activo,debe_cambiar,modulos,created_at,updated_at) VALUES(?,?,?,?,1,0,?,?,?)');
foreach([['super@example.invalid','Super de Prueba','super',[]],['operador@example.invalid','Operador de Prueba','operador',['fotos','juegos','carteles','invitados','mensajes','album','perfil']],['limitado@example.invalid','Operador Limitado','operador',['fotos']]]as$u){
 $s->execute([$u[0],$u[1],password_hash(getenv('CC_QA_PASSWORD'),PASSWORD_DEFAULT),$u[2],json_encode($u[3]),$now,$now]);
 if($u[2]==='operador'){cb_admin_asignar_fiestas((int)$pdo->lastInsertId(),['qa-cumple']);}
}
for($i=1;$i<=5;$i++){
 cb_create_lead(['nombre'=>'Solicitud de Prueba '.$i,'email'=>'consulta'.$i.'@example.invalid','telefono'=>'+56900000000','tipo'=>'Cumpleaños','fecha'=>'2026-10-10','comuna'=>'Comuna Ejemplo','mensaje'=>'Cotización de prueba para una celebración infantil.','consentimiento'=>true,'website'=>''],'127.0.0.1','QA');
}
$pdo->prepare('INSERT INTO cc_finanzas(fecha,tipo,categoria,descripcion,monto,unidades,party_slug,nota,created_at) VALUES(?,?,?,?,?,?,?,?,?)')
 ->execute(['2026-09-24','egreso','insumos','Material de prueba',10000,5,'qa-cumple','Solo datos sintéticos',$now]);
require_once $root.'/public/lib.acceptance.php';
cb_create_plan_acceptance($parties['qa-cumple']+['public_slug'=>'qa-cumple'],['client_name'=>'Contacto de Prueba','client_email'=>'cliente@example.invalid','client_phone'=>'','expires_days'=>14,'summary'=>['plan_name'=>'Plan Premium','price_total'=>'49995','event_date'=>'2026-09-26']],'qa');
echo "FIXTURE_READY\n";
