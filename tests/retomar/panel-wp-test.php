<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_panel', 'at_rt_panel_ejecutar', 'at_rt_panel_guardar');
global $wpdb;
at_rt_migrar_esquema();
$ids=[]; $props=[]; $users=[]; $crms=[]; $emails=[];
register_shutdown_function(static function() use (&$ids,&$props,&$users,&$crms,&$emails,$wpdb){
 foreach($ids as $id){$wpdb->delete($wpdb->prefix.'at_retomar',['id'=>$id]);}
 foreach($props as $id){$wpdb->delete($wpdb->prefix.'automatiza_propuestas',['id'=>$id]);}
 foreach($crms as $id){$wpdb->delete($wpdb->prefix.'crm_clientes',['id'=>$id]);}
 foreach($emails as $email){$wpdb->delete($wpdb->prefix.'at_retomar_bajas',['email'=>$email]);}
 require_once ABSPATH.'wp-admin/includes/user.php'; foreach($users as $id){wp_delete_user($id);}
});
$tag='rt-panel-'.strtolower(wp_generate_password(9,false,false));
$admin=wp_insert_user(['user_login'=>$tag,'user_pass'=>wp_generate_password(),'user_email'=>$tag.'@example.com','role'=>'administrator']);$users[]=$admin;wp_set_current_user($admin);
$p=['nombre'=>'[PRUEBA] Panel','negocio'=>'[PRUEBA] Negocio','email'=>$tag.'@example.com','telefono'=>'+56900000000','quedamos'=>'[PRUEBA] Conversamos','proponemos'=>'[PRUEBA] Demo','partir'=>'[PRUEBA] Primero','max_envios'=>4,'intervalo_dias'=>3]; $emails[]=$p['email'];
function panel_action($a,$id,$p=[]){return at_rt_panel_ejecutar($a,$p+['id'=>$id,'_wpnonce'=>wp_create_nonce('at_rt_'.$a.'_'.$id)]);}
$r=panel_action('guardar',0,$p);ok(!is_wp_error($r),'crear borrador desde panel');$id=$r['id'];$ids[]=$id;
$s=at_rt_obtener($id);ok($s['intervalo_dias']===7 && $s['estado']==='borrador','intervalo 3 se ajusta a 7 y sigue borrador');
ok(array_keys($s['textos'])===['quedamos','proponemos','partir'],'tres claves de textos sin proyecto obligatorio');
ok(is_wp_error(panel_action('activar',$id)) && at_rt_obtener($id)['estado']==='borrador','activar sin render bloquea con aviso');
$view='https://'.at_pt_host_renderer().'/p/'.$s['token'].'-retomar/';
at_rt_guardar($id,['render'=>['estado'=>'listo','view_url'=>$view]]);
$r=panel_action('activar',$id);$s=at_rt_obtener($id);$now=new DateTimeImmutable('now',new DateTimeZone('America/Santiago'));$hoy=$now->format('Y-m-d');$prox=at_rt_es_habil($hoy)&&$now->format('H:i')<'13:00'?$hoy:at_rt_siguiente_habil($hoy);
ok(!is_wp_error($r)&&$s['estado']==='activo'&&$s['proximo_at']===$prox&&substr($s['activado_at'],0,10)===$hoy,'activar válido programa hoy o siguiente hábil, hora Santiago');
$before=$s['envios'];$r=panel_action('prueba',$id);ok(!is_wp_error($r)&&at_rt_obtener($id)['envios']===$before,'prueba no suma envíos');
$r=panel_action('respondio',$id);ok(!is_wp_error($r)&&at_rt_obtener($id)['estado']==='respondio'&&at_rt_obtener($id)['proximo_at']===null,'Respondió detiene seguimiento');
$r=panel_action('guardar',$id,$p+['estado'=>'activo','envios'=>[]]);ok(!is_wp_error($r)&&at_rt_obtener($id)['estado']==='respondio'&&at_rt_obtener($id)['render']['estado']!=='listo','guardar ignora estado e invalida render anterior sin flujo');
foreach(['guardar','previa','prueba','activar','pausar','respondio','baja','revertir_baja'] as $a){$before=at_rt_obtener($id);$r=at_rt_panel_ejecutar($a,['id'=>$id,'_wpnonce'=>'invalido']);ok(is_wp_error($r)&&at_rt_obtener($id)===$before,$a.' nonce inválido no muta');}
wp_set_current_user(0);
foreach(['guardar','previa','prueba','activar','pausar','respondio','baja','revertir_baja'] as $a){$before=at_rt_obtener($id);$r=panel_action($a,$id,$p);ok(is_wp_error($r)&&$r->get_error_code()==='permiso'&&at_rt_obtener($id)===$before,$a.' exige manage_options en backend');}
wp_set_current_user($admin);
$wpdb->insert($wpdb->prefix.'automatiza_propuestas',['client_name'=>'[PRUEBA] Panel','company_name'=>'[PRUEBA] Negocio','client_email'=>$p['email'],'status'=>'rechazada','unique_link_id'=>$tag]);$pid=(int)$wpdb->insert_id;$props[]=$pid;
at_rt_guardar($id,['propuesta_id'=>$pid,'estado'=>'borrador','render'=>['estado'=>'listo','view_url'=>$view]]);
$r=panel_action('activar',$id);ok(is_wp_error($r)&&str_contains($r->get_error_message(),'rechazada')&&at_rt_obtener($id)['estado']==='borrador','rechazada permanece borrador con aviso claro');
at_rt_guardar($id,['propuesta_id'=>null,'email'=>'']);$r=panel_action('activar',$id);ok(is_wp_error($r)&&$r->get_error_code()==='correo','activar sin correo bloquea');at_rt_guardar($id,['email'=>$p['email']]);
// Sin teléfono en baja: no afectar fixtures ajenos que usan el teléfono convencional.
at_rt_guardar($id,['telefono'=>'']);$r=panel_action('baja',$id);ok(!is_wp_error($r)&&at_rt_en_bajas($p['email'],''),'baja registra supresión');$r=panel_action('activar',$id);ok(is_wp_error($r)&&str_contains($r->get_error_message(),'bajas'),'activar contacto en bajas bloquea');
ok(is_wp_error(panel_action('revertir_baja',$id,['motivo'=>''])),'revertir baja exige motivo');ok(!is_wp_error(panel_action('revertir_baja',$id,['motivo'=>'[PRUEBA] Solicitud registrada']))&&!at_rt_en_bajas($p['email'],'')&&at_rt_obtener($id)['estado']==='baja','revertir no reactiva');
foreach([['nombre'=>str_repeat('á',121)],['quedamos'=>str_repeat('á',601)],['partir'=>implode("\n",array_fill(0,7,'a'))],['demo_1'=>'http://example.com/demo'],['demo_2'=>'https://oculto.easypanel.host/demo'],['intervalo_dias'=>256],['max_envios'=>5],['propuesta_id'=>PHP_INT_MAX],['crm_cliente_id'=>PHP_INT_MAX]] as $bad){$before=at_rt_obtener($id);$r=panel_action('guardar',$id,array_replace($p,$bad));ok(is_wp_error($r)&&at_rt_obtener($id)===$before,'validación rechaza sin truncar ni modificar');}
$r=panel_action('guardar',$id,array_replace($p,['crear_crm'=>1]));if(!is_wp_error($r)){$crms[]=at_rt_obtener($id)['crm_cliente_id'];}ok(!is_wp_error($r)&&$wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id=%d",end($crms)))==='prospecto','crear CRM solo flag explícito y tipo prospecto');
$_GET=['page'=>'at-retomar','id'=>$id];ob_start();at_rt_panel();$html=ob_get_clean();
ok(str_contains($html,'Márcala solo si esta persona te escribió o conversó contigo por WhatsApp')&&str_contains($html,'id="rt-wa"')&&str_contains($html,'id="rt-media"'),'checkbox autorización, ayuda exacta y selector medios');
ok(str_contains($html,'name="demo_2"')&&!str_contains($html,'name="demo_3"')&&str_contains($html,'for="rt-quedamos"'),'hasta dos demos y labels accesibles');
ok(!str_contains($html,'<iframe'),'render pendiente no expone iframe anterior');
foreach(['guardar','previa','prueba','activar','pausar','respondio','baja','revertir_baja'] as $a){ok(has_action('admin_post_at_rt_'.$a)!==false,'admin_post registrado '.$a);}
at_rt_guardar($id,['estado'=>'borrador','propuesta_id'=>null,'render'=>['estado'=>'listo','view_url'=>'https://'.at_pt_host_renderer().'/p/'.str_repeat('a',32).'-retomar/']]);
$r=panel_action('activar',$id);ok(is_wp_error($r)&&at_rt_obtener($id)['estado']==='borrador','activar exige URL render del mismo token');
$wpdb->update($wpdb->prefix.'automatiza_propuestas',['client_email'=>'otro@example.com'],['id'=>$pid]);
$before=at_rt_obtener($id);$r=panel_action('guardar',$id,array_replace($p,['propuesta_id'=>$pid]));ok(is_wp_error($r)&&at_rt_obtener($id)===$before,'propuesta de otro correo no se vincula');
$before=at_rt_obtener($id);$r=panel_action('guardar',$id,array_replace($p,['imagen_url'=>'https://example.com/'.str_repeat('a',500)]));ok(is_wp_error($r)&&at_rt_obtener($id)===$before,'URL excede columna 500 sin truncado silencioso');
at_rt_guardar($id,['envios'=>[['tipo'=>'envio','correo'=>['estado'=>'enviado']]],'render'=>['estado'=>'listo','view_url'=>$view]]);$r=panel_action('activar',$id);ok(!is_wp_error($r)&&at_rt_envios_realizados(at_rt_obtener($id)['envios'])===1,'activar conserva envíos previos');
fin();
