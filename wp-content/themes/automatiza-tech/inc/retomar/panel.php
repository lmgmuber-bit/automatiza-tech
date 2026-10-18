<?php
/** Panel privado de Retomar y acciones explícitas protegidas. */
if (!defined('ABSPATH')) { exit; }
add_action('admin_menu', static function () { add_submenu_page('automatiza-crm', 'Retomar prospectos', '🔁 Retomar prospectos', 'manage_options', 'at-retomar', 'at_rt_panel'); });
add_action('admin_enqueue_scripts', static function () {
    if (($_GET['page'] ?? '') !== 'at-retomar' || !current_user_can('manage_options')) { return; }
    wp_enqueue_media();
    wp_add_inline_script('media-editor', 'jQuery(function($){$("#rt-crm").on("change",function(){var o=$(this).find(":selected");if(!this.value||this.value==="0"){return;}["nombre","negocio","email","telefono"].forEach(function(k){$("#rt-"+k).val(o.attr("data-"+k)||"");});});});');
    wp_enqueue_style('at-retomar', get_template_directory_uri() . '/assets/css/retomar.css', [], '1.0');
    wp_add_inline_script('media-editor', 'jQuery(function($){$("#rt-media").on("click",function(){var f=wp.media({title:"Elegir imagen de la demo",multiple:false,library:{type:"image"}});f.on("select",function(){$("#rt-imagen_url").val(f.state().get("selection").first().toJSON().url);});f.open();});});');
});
foreach (['guardar','previa','prueba','activar','pausar','respondio','baja','revertir_baja'] as $accion) {
    add_action('admin_post_at_rt_' . $accion, static function () use ($accion) {
        $r = at_rt_panel_ejecutar($accion, wp_unslash($_POST));
        if (is_wp_error($r) && in_array($r->get_error_code(), ['permiso','nonce'], true)) { wp_die(esc_html($r->get_error_message()), '', ['response'=>403]); }
        $id = is_wp_error($r) ? absint($_POST['id'] ?? 0) : $r['id'];
        $msg = is_wp_error($r) ? $r->get_error_message() : $r['mensaje'];
        wp_safe_redirect(add_query_arg(['page'=>'at-retomar','id'=>$id,'rt_msg'=>$msg,'rt_error'=>is_wp_error($r)?1:0], admin_url('admin.php'))); exit;
    });
}
/** Única entrada de las acciones del panel: también verificable sin redirects en pruebas. */
function at_rt_panel_ejecutar(string $accion, array $p) {
    if (!current_user_can('manage_options')) { return new WP_Error('permiso','No tienes permiso para administrar Retomar.'); }
    $id = absint($p['id'] ?? 0);
    if (!wp_verify_nonce((string)($p['_wpnonce'] ?? ''), 'at_rt_' . $accion . '_' . $id)) { return new WP_Error('nonce','La autorización venció. Recarga el panel.'); }
    if ($accion === 'guardar') { return at_rt_panel_guardar($id, $p); }
    $s = at_rt_obtener($id);
    if (!$s) { return new WP_Error('registro','El seguimiento no existe.'); }
    $mensaje = 'Cambios guardados.';
    if ($accion === 'activar') {
        if (!is_email($s['email'])) { return new WP_Error('correo','Agrega un correo válido antes de activar.'); }
        $motivo = at_rt_motivo_parada(at_rt_contexto_parada($s));
        if ($motivo !== '') { return new WP_Error('detenido', ['rechazada'=>'La propuesta está rechazada. No se puede activar.','baja'=>'Este contacto está en bajas. Revierte la baja con un motivo antes de activar.','agotado'=>'Ya alcanzó el máximo de envíos.','cliente'=>'La ficha ya es cliente.','aceptada'=>'La propuesta ya fue aceptada.','agendo'=>'El contacto ya agendó una reunión.'][$motivo] ?? 'Este seguimiento debe permanecer detenido.'); }
        if (($s['render']['estado'] ?? '') !== 'listo' || !at_rt_url_render_valida((string)($s['render']['view_url'] ?? ''), $s['token'])) { return new WP_Error('render','La presentación aún no está lista. Pide una vista previa antes de activar.'); }
        $ahora = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
        $hoy = $ahora->format('Y-m-d');
        $proximo = at_rt_es_habil($hoy) && $ahora->format('H:i') < '13:00' ? $hoy : at_rt_siguiente_habil($hoy);
        $ok = at_rt_guardar($id, ['estado'=>'activo','activado_at'=>$ahora->format('Y-m-d H:i:s'),'proximo_at'=>$proximo]);
        $mensaje = 'Seguimiento activo. Próximo envío: ' . $proximo . ' (ventana 10:00 a 13:00, Santiago).';
    } elseif (in_array($accion, ['pausar','respondio'], true)) {
        $ok = at_rt_guardar($id, ['estado'=>$accion === 'pausar'?'pausado':'respondio','proximo_at'=>null]);
    } elseif ($accion === 'baja') { $ok = at_rt_dar_baja($id, 'panel'); $mensaje = 'Contacto dado de baja de Retomar.';
    } elseif ($accion === 'revertir_baja') {
        global $wpdb;
        $motivo = trim(sanitize_text_field((string)($p['motivo'] ?? '')));
        if ($motivo === '' || mb_strlen($motivo)>255) { return new WP_Error('motivo','Escribe un motivo de 1 a 255 caracteres para revertir la baja.'); }
        $bajas = $wpdb->get_results("SELECT id,email,telefono FROM {$wpdb->prefix}at_retomar_bajas WHERE revertido_at IS NULL", ARRAY_A);
        $ok = false;
        foreach ($bajas as $b) { if (at_rt_contacto_coincide(strtolower($s['email']), at_pt_telefono_wa($s['telefono']), $b)) { $ok = at_rt_revertir_baja((int)$b['id'],$motivo) || $ok; } }
        $mensaje = 'Baja revertida. El seguimiento sigue detenido hasta que lo actives explícitamente.';
    } elseif ($accion === 'previa') {
        $error = at_rt_pedir_render($id); $ok = $error === ''; $mensaje = 'Vista previa solicitada. Recarga el panel para ver el resultado.';
        if (!$ok) { return new WP_Error('render', $error === 'sin_flujo'?'El flujo de presentación no está configurado. La vista previa queda pendiente.':$error); }
    } elseif ($accion === 'prueba') {
        $r = at_rt_enviar($id,true); $ok = $r['ok']; $mensaje = 'Prueba enviada solo al equipo, sin sumar envíos ni enviar WhatsApp.';
        if (!$ok) { return new WP_Error('prueba','No se pudo enviar la prueba: ' . sanitize_text_field($r['motivo'])); }
    } else { return new WP_Error('accion','Acción desconocida.'); }
    return $ok ? ['id'=>$id,'mensaje'=>$mensaje] : new WP_Error('guardar','No se pudo completar la acción.');
}
function at_rt_panel_guardar(int $id, array $p) {
    global $wpdb;
    $s = $id ? at_rt_obtener($id) : null;
    if ($id && !$s) { return new WP_Error('registro','El seguimiento no existe.'); }
    $d = [];
    foreach (['nombre','negocio','email','telefono','propuesta_url','imagen_url'] as $k) { $d[$k] = trim((string)($p[$k] ?? '')); }
    foreach (['nombre','negocio'] as $k) { if (mb_strlen($d[$k])>120) { return new WP_Error('largo', $k . ': máximo 120 caracteres.'); } $d[$k] = sanitize_text_field($d[$k]); }
    if ($d['nombre'] === '') { return new WP_Error('nombre','Escribe el nombre del prospecto.'); }
    if ($d['email'] !== '' && (!is_email($d['email']) || strlen($d['email'])>190)) { return new WP_Error('correo','El correo no es válido (máximo 190 caracteres).'); }
    if (strlen($d['telefono'])>50) { return new WP_Error('telefono','El teléfono es demasiado largo.'); }
    $tel = at_pt_telefono_wa($d['telefono']);
    if ($d['telefono'] !== '' && ($tel === '' || !preg_match('/^\+?[1-9][0-9]{7,14}$/D',$tel))) { return new WP_Error('telefono','Escribe un teléfono válido con código de país.'); }
    $d['telefono'] = $tel; $d['email'] = strtolower($d['email']);
    $d['textos'] = [];
    foreach (['quedamos','proponemos','partir'] as $k) {
        $t = trim((string)($p[$k] ?? ''));
        if ($t === '' || mb_strlen($t)>600 || count(preg_split('/\r\n|\r|\n/', $t))>6) { return new WP_Error('texto', $k . ': escribe de 1 a 600 caracteres, máximo 6 líneas.'); }
        $d['textos'][$k] = sanitize_textarea_field($t);
    }
    $d['demo_urls'] = array_values(array_filter([trim((string)($p['demo_1'] ?? '')),trim((string)($p['demo_2'] ?? ''))], static fn($v)=>$v!==''));
    foreach (array_merge($d['demo_urls'],[$d['propuesta_url'],$d['imagen_url']]) as $url) { if ($url !== '' && (strlen($url)>500 || at_rt_url_correo($url)==='')) { return new WP_Error('url','Usa enlaces HTTPS públicos válidos (máximo 500 caracteres); no enlaces internos.'); } }
    if (at_rt_internas($d)) { return new WP_Error('interno','El contenido incluye enlaces internos. Usa solo información pública.'); }
    $intervalo = filter_var($p['intervalo_dias'] ?? 7,FILTER_VALIDATE_INT);
    $max = filter_var($p['max_envios'] ?? 4,FILTER_VALIDATE_INT);
    if ($intervalo===false || $intervalo>255 || $intervalo<0 || $max===false || $max<1 || $max>4) { return new WP_Error('limites','El máximo es de 1 a 4 envíos; el intervalo admite hasta 255 días y se ajusta a un mínimo de 7.'); }
    $d += ['intervalo_dias'=>max(7,$intervalo),'max_envios'=>$max,'wa_autorizado'=>empty($p['wa_autorizado'])?0:1,'canales'=>($p['canales']??'correo')==='correo,whatsapp'?'correo,whatsapp':'correo'];
    $crm_id = absint($p['crm_cliente_id'] ?? 0); $propuesta_id = absint($p['propuesta_id'] ?? 0);
    if ($crm_id) {
        $crm = $wpdb->get_row($wpdb->prepare("SELECT id,tipo,email FROM {$wpdb->prefix}crm_clientes WHERE id=%d",$crm_id),ARRAY_A);
        if (!$crm || $crm['tipo']!=='prospecto' || ($crm['email']!=='' && strtolower($crm['email'])!==$d['email'])) { return new WP_Error('crm','Selecciona una ficha de prospecto con el mismo correo. No se convierten clientes existentes.'); }
    }
    if ($propuesta_id) {
        $prop = $wpdb->get_row($wpdb->prepare("SELECT id,client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id=%d",$propuesta_id),ARRAY_A);
        if (!$prop || ($prop['client_email']!=='' && strtolower($prop['client_email'])!==$d['email'])) { return new WP_Error('propuesta','La propuesta debe pertenecer al mismo correo del prospecto.'); }
    }
    if (!$crm_id && !empty($p['crear_crm'])) {
        if (!is_email($d['email'])) { return new WP_Error('crm','Para crear la ficha CRM agrega un correo válido.'); }
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}crm_clientes WHERE LOWER(email)=%s LIMIT 1",$d['email']))) { return new WP_Error('crm','Ya existe una ficha con ese correo. Búscala y selecciónala.'); }
        if (!$wpdb->insert($wpdb->prefix.'crm_clientes',['tipo'=>'prospecto','estado'=>'nuevo','nombre'=>$d['nombre'],'empresa'=>$d['negocio'],'email'=>$d['email'],'telefono'=>$d['telefono'],'fecha_contacto'=>current_time('mysql')])) { return new WP_Error('crm','No se pudo crear la ficha CRM.'); }
        $crm_id = (int)$wpdb->insert_id;
    }
    $d += ['crm_cliente_id'=>$crm_id,'propuesta_id'=>$propuesta_id];
    // Cada guardado invalida la versión previa ANTES de pedir la nueva. Sin flujo nunca queda listo.
    $d['render'] = ['estado'=>'pendiente','nota'=>'Contenido guardado; falta generar la presentación.'];
    if ($id ? !at_rt_guardar($id,$d) : !($id=at_rt_crear($d))) { return new WP_Error('guardar','No se pudo guardar el seguimiento.'); }
    $error = at_rt_pedir_render($id);
    return ['id'=>$id,'mensaje'=>$error==='sin_flujo'?'Guardado. El flujo de presentación no está configurado; no se puede activar aún.':($error!==''?'Guardado. No se pudo pedir la presentación: '.$error:'Guardado. Presentación solicitada.')];
}
function at_rt_panel_form_inicio(string $accion,int $id): void {
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    echo '<input type="hidden" name="action" value="at_rt_'.esc_attr($accion).'"><input type="hidden" name="id" value="'.$id.'">';
    wp_nonce_field('at_rt_'.$accion.'_'.$id);
}
function at_rt_panel(): void {
    if (!current_user_can('manage_options')) { wp_die('No tienes permiso.', '', ['response'=>403]); }
    global $wpdb;
    $id=absint($_GET['id']??0); $s=$id?at_rt_obtener($id):null;
    echo '<div class="wrap at-retomar"><h1>🔁 Retomar prospectos</h1><p>Recordatorios del equipo de AutomatizaTech. Correo en días hábiles, entre 10:00 y 13:00 de Santiago.</p>';
    if (!empty($_GET['rt_msg'])) { echo '<div role="status" class="notice '.(!empty($_GET['rt_error'])?'notice-error':'notice-success').'"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['rt_msg']))).'</p></div>'; }
    echo '<div class="rt-tabla"><table class="widefat striped"><caption class="screen-reader-text">Seguimientos de prospectos</caption><thead><tr><th>Prospecto</th><th>Estado</th><th>Envíos / máximo</th><th>Próximo</th><th>Último resultado</th></tr></thead><tbody>';
    foreach (at_rt_lista() as $f) {
        $ultimo=end($f['envios']); $res=$ultimo?wp_json_encode($ultimo,JSON_UNESCAPED_UNICODE):'Sin envíos';
        echo '<tr><td><a href="'.esc_url(add_query_arg(['page'=>'at-retomar','id'=>$f['id']],admin_url('admin.php'))).'">'.esc_html($f['nombre'].' · '.$f['negocio']).'</a></td><td>'.esc_html($f['estado']).'</td><td>'.at_rt_envios_realizados($f['envios']).' / '.$f['max_envios'].'</td><td>'.esc_html($f['proximo_at']?:'—').'</td><td class="rt-resultado">'.esc_html($res).'</td></tr>';
    }
    echo '</tbody></table></div><p><a class="button" href="'.esc_url(add_query_arg('page','at-retomar',admin_url('admin.php'))).'">Nuevo seguimiento</a></p><h2>'.($s?'Editar seguimiento #'.$id:'Nuevo seguimiento').'</h2>';
    $id=$s?$id:0;
    echo '<form method="get"><input type="hidden" name="page" value="at-retomar"><input type="hidden" name="id" value="'.$id.'"><label for="rt-buscar">Buscar ficha CRM (nombre, empresa o correo)</label> <input id="rt-buscar" name="buscar" value="'.esc_attr(wp_unslash($_GET['buscar']??'')).'"><button class="button">Buscar</button></form>';
    at_rt_panel_form_inicio('guardar',$id);
    $buscar=sanitize_text_field(wp_unslash($_GET['buscar']??'')); $like='%'.$wpdb->esc_like($buscar).'%';
    $clientes=$wpdb->get_results($wpdb->prepare("SELECT id,nombre,empresa,email,telefono FROM {$wpdb->prefix}crm_clientes WHERE tipo='prospecto' AND (nombre LIKE %s OR empresa LIKE %s OR email LIKE %s OR id=%d) ORDER BY nombre LIMIT 100",$like,$like,$like,(int)($s['crm_cliente_id']??0)),ARRAY_A);
    echo '<p><label for="rt-crm">Ficha de prospecto CRM</label><select id="rt-crm" name="crm_cliente_id"><option value="0">Sin ficha seleccionada</option>';
    foreach ($clientes as $c) { echo '<option data-nombre="'.esc_attr($c['nombre']).'" data-negocio="'.esc_attr($c['empresa']).'" data-email="'.esc_attr($c['email']).'" data-telefono="'.esc_attr($c['telefono']).'" value="'.$c['id'].'" '.selected($s['crm_cliente_id']??0,$c['id'],false).'>'.esc_html('#'.$c['id'].' · '.$c['nombre'].' · '.$c['empresa'].' · '.$c['email']).'</option>'; }
    echo '</select><label><input type="checkbox" name="crear_crm" value="1"> Crear ficha CRM como prospecto si aún no existe</label></p><div class="rt-grid">';
    foreach (['nombre'=>['Nombre',120],'negocio'=>['Negocio',120],'email'=>['Correo',190],'telefono'=>['Teléfono con código de país',50],'propuesta_url'=>['URL externa de la propuesta (opcional)',500],'imagen_url'=>['Imagen de la demo (biblioteca de medios)',500]] as $k=>$meta) {
        echo '<p><label for="rt-'.$k.'">'.esc_html($meta[0]).'</label><input id="rt-'.$k.'" name="'.$k.'" type="'.($k==='email'?'email':(str_ends_with($k,'url')?'url':'text')).'" maxlength="'.$meta[1].'" value="'.esc_attr($s[$k]??'').'" '.($k==='nombre'?'required':'').'></p>';
    }
    echo '</div><button type="button" class="button" id="rt-media">Elegir imagen</button><p><label for="rt-propuesta">Propuesta vinculada (opcional, mismo correo)</label><select id="rt-propuesta" name="propuesta_id"><option value="0">Sin propuesta vinculada</option>';
    $props=$wpdb->get_results("SELECT id,client_name,company_name,client_email,status FROM {$wpdb->prefix}automatiza_propuestas ORDER BY id DESC",ARRAY_A);
    foreach ($props as $p) { echo '<option value="'.$p['id'].'" '.selected($s['propuesta_id']??0,$p['id'],false).'>'.esc_html('#'.$p['id'].' · '.$p['client_name'].' · '.$p['company_name'].' · '.$p['client_email'].' · '.$p['status']).'</option>'; }
    echo '</select></p>';
    foreach ([1,2] as $n) { echo '<p><label for="rt-demo-'.$n.'">Demo '.$n.' (HTTPS pública, opcional)</label><input id="rt-demo-'.$n.'" name="demo_'.$n.'" type="url" maxlength="500" value="'.esc_attr($s['demo_urls'][$n-1]??'').'"></p>'; }
    foreach (['quedamos'=>'En qué quedamos','proponemos'=>'Qué proponemos','partir'=>'Por dónde podemos partir'] as $k=>$label) { echo '<p><label for="rt-'.$k.'">'.$label.'</label><textarea id="rt-'.$k.'" name="'.$k.'" rows="6" maxlength="600" required aria-describedby="rt-textos-ayuda">'.esc_textarea($s['textos'][$k]??'').'</textarea></p>'; }
    echo '<p id="rt-textos-ayuda">Cada texto admite hasta 600 caracteres y 6 líneas. No incluyas enlaces internos. El proyecto toma el nombre del negocio.</p><div class="rt-grid"><p><label for="rt-max">Máximo de envíos</label><input id="rt-max" type="number" min="1" max="4" name="max_envios" value="'.(int)($s['max_envios']??4).'"></p><p><label for="rt-intervalo">Intervalo en días</label><input id="rt-intervalo" type="number" min="7" max="255" name="intervalo_dias" value="'.(int)($s['intervalo_dias']??7).'" aria-describedby="rt-intervalo-ayuda"><span id="rt-intervalo-ayuda">Mínimo 7 días (predeterminado); máximo 255 días.</span></p></div><p><label for="rt-canales">Canales</label><select id="rt-canales" name="canales"><option value="correo">Correo</option><option value="correo,whatsapp" '.selected($s['canales']??'','correo,whatsapp',false).'>Correo y WhatsApp</option></select></p><p><label for="rt-wa"><input id="rt-wa" type="checkbox" name="wa_autorizado" value="1" '.checked($s['wa_autorizado']??0,1,false).' aria-describedby="rt-wa-ayuda"> WhatsApp autorizado</label><span id="rt-wa-ayuda">Márcala solo si esta persona te escribió o conversó contigo por WhatsApp</span></p><button class="button button-primary">Guardar y pedir presentación</button></form>';
    if ($s) {
        echo '<h2>Acciones del seguimiento</h2><div class="rt-acciones">';
        foreach (['previa'=>'Vista previa','prueba'=>'Enviar prueba al equipo','activar'=>'Activar','pausar'=>'Pausar','respondio'=>'Respondió','baja'=>'Dar de baja'] as $a=>$label) { at_rt_panel_form_inicio($a,$id); echo '<button class="button">'.$label.'</button></form>'; }
        echo '</div>'; at_rt_panel_form_inicio('revertir_baja',$id); echo '<p><label for="rt-motivo">Motivo para revertir la baja</label><input id="rt-motivo" name="motivo" maxlength="255" required></p><button class="button">Revertir baja</button></form>';
        echo '<h2>Presentación</h2><p role="status">'.esc_html(($s['render']['estado']??'Sin generar').' · '.($s['render']['nota']??'')).'</p>';
        $view=(string)($s['render']['view_url']??'');
        if (($s['render']['estado']??'')==='listo' && at_rt_url_render_valida($view,$s['token'])) { echo '<a href="'.esc_url($view).'" target="_blank" rel="noopener">Abrir presentación</a><iframe title="Vista previa de Retomar" src="'.esc_url($view).'" loading="lazy"></iframe>'; }
    }
    echo '</div>';
}
