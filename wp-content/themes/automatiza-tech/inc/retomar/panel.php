<?php
/** Panel privado de Retomar y acciones explícitas protegidas. */
if (!defined('ABSPATH')) { exit; }
add_action('admin_menu', static function () { add_submenu_page('automatiza-crm', 'Retomar prospectos', '🔁 Retomar prospectos', 'manage_options', 'at-retomar', 'at_rt_panel'); });
add_action('admin_enqueue_scripts', static function () {
    if (($_GET['page'] ?? '') !== 'at-retomar' || !current_user_can('manage_options')) { return; }
    wp_enqueue_media();
    wp_add_inline_script('media-editor', 'jQuery(function($){$("#rt-crm").on("change",function(){var o=$(this).find(":selected");if(!this.value||this.value==="0"){return;}["nombre","negocio","email","telefono"].forEach(function(k){$("#rt-"+k).val(o.attr("data-"+k)||"");});});});');
    wp_enqueue_style('at-retomar', get_template_directory_uri() . '/assets/css/retomar.css', [], '1.1');
    wp_add_inline_script('media-editor', 'jQuery(function($){$("#rt-media").on("click",function(){var f=wp.media({title:"Elegir imagen de la demo",multiple:false,library:{type:"image"}});f.on("select",function(){$("#rt-imagen_url").val(f.state().get("selection").first().toJSON().url);});f.open();});});');
});
/**
 * Acciones del panel. No hay «activar»: guardar deja activo un seguimiento nuevo, en borrador o que esperaba una propuesta
 * nueva (si puede recibir recordatorios), nunca uno pausado o detenido; y nada sale al prospecto sin la aprobación semanal
 * de Luis. «config» y «resumen» no son de un seguimiento (su nonce es el de id 0).
 */
function at_rt_acciones_panel(): array {
    return ['guardar','previa','prueba','pausar','respondio','baja','revertir_baja','whatsapp_manual','config','resumen','redactar','reanudar'];
}
/** Registra las acciones del panel dentro de una función: este archivo se carga en el ámbito global y un foreach
 *  suelto dejaba la global $accion pisada (rompía las acciones del plan de trabajo y de entregables). */
function at_rt_registrar_acciones(): void {
    foreach (at_rt_acciones_panel() as $accion) {
        add_action('admin_post_at_rt_' . $accion, static function () use ($accion) {
            $r = at_rt_panel_ejecutar($accion, wp_unslash($_POST));
            if (is_wp_error($r) && in_array($r->get_error_code(), ['permiso','nonce'], true)) { wp_die(esc_html($r->get_error_message()), '', ['response'=>403]); }
            if (!is_wp_error($r) && !empty($r['url'])) { wp_redirect($r['url']); exit; } // wa.me: sale del sitio a propósito
            $id = is_wp_error($r) ? absint($_POST['id'] ?? 0) : $r['id'];
            $msg = is_wp_error($r) ? $r->get_error_message() : $r['mensaje'];
            wp_safe_redirect(add_query_arg(['page'=>'at-retomar','id'=>$id,'rt_msg'=>$msg,'rt_error'=>is_wp_error($r)?1:0], admin_url('admin.php'))); exit;
        });
    }
}
at_rt_registrar_acciones();
/** Por qué un seguimiento no puede entrar o volver a Retomar (motivo de at_rt_motivo_parada), en palabras. */
function at_rt_panel_motivo_texto(string $motivo): string {
    return [
        'rechazada'=>'La propuesta está rechazada.', 'baja'=>'Este contacto está en bajas.', 'agotado'=>'Ya alcanzó el máximo de envíos.',
        'cliente'=>'La ficha ya es cliente.', 'aceptada'=>'La propuesta ya fue aceptada.', 'agendo'=>'El contacto ya agendó una reunión.',
    ][$motivo] ?? 'Este seguimiento debe permanecer detenido.';
}
/** Única entrada de las acciones del panel: también verificable sin redirects en pruebas. */
function at_rt_panel_ejecutar(string $accion, array $p) {
    if (!current_user_can('manage_options')) { return new WP_Error('permiso','No tienes permiso para administrar Retomar.'); }
    if (!in_array($accion, at_rt_acciones_panel(), true)) { return new WP_Error('accion','Acción desconocida.'); }
    $id = absint($p['id'] ?? 0);
    if (!wp_verify_nonce((string)($p['_wpnonce'] ?? ''), 'at_rt_' . $accion . '_' . $id)) { return new WP_Error('nonce','La autorización venció. Recarga el panel.'); }
    if ($accion === 'guardar') { return at_rt_panel_guardar($id, $p); }
    if ($accion === 'config') { return at_rt_panel_config($p); }
    if ($accion === 'resumen') { return at_rt_panel_resumen(); }
    $s = at_rt_obtener($id);
    if (!$s) { return new WP_Error('registro','El seguimiento no existe.'); }
    $mensaje = 'Cambios guardados.';
    if (in_array($accion, ['pausar','respondio'], true)) {
        $ok = at_rt_guardar($id, ['estado'=>$accion === 'pausar'?'pausado':'respondio','proximo_at'=>null]);
    } elseif ($accion === 'reanudar') {
        // Deja el seguimiento activo para el próximo resumen semanal; nunca envía nada por sí mismo. Lo aprobado antes no vale.
        if (!in_array($s['estado'], ['pausado','respondio'], true)) { return new WP_Error('estado','Solo se reanuda un seguimiento pausado o con respuesta.'); }
        if (!is_email($s['email'])) { return new WP_Error('correo','Agrega un correo válido antes de reanudar.'); }
        $ahora = at_rt_ahora_chile();
        $motivo = at_rt_motivo_parada(at_rt_contexto_parada(array_merge($s, ['activado_at'=>$ahora])));
        if ($motivo !== '') { return new WP_Error('detenido', at_rt_panel_motivo_texto($motivo) . ' No se puede reanudar.'); }
        $ok = at_rt_guardar($id, ['estado'=>'activo','activado_at'=>$ahora,'proximo_at'=>substr($ahora, 0, 10),'aprobacion'=>at_rt_aprobacion_muerta(is_array($s['aprobacion'])?$s['aprobacion']:[], $ahora)]);
        $mensaje = 'Seguimiento reanudado. No sale nada solo: aparece en el próximo resumen semanal para que lo apruebes.';
        // Guardar los textos de un seguimiento que no estaba activo no pide la presentación: si están listos y nunca se pidió (o falló), se pide ahora.
        $render = is_array($s['render']) ? $s['render'] : [];
        if ($ok && ($s['redaccion']['estado'] ?? '') === 'lista' && ($render['estado'] ?? '') !== 'listo' && (($render['estado'] ?? '') === 'error' || empty($render['generacion']))) {
            $error = at_rt_pedir_render($id);
            if ($error !== '' && $error !== 'sin_flujo') { $mensaje .= ' No se pudo pedir la presentación: ' . sanitize_text_field($error); }
        }
    } elseif ($accion === 'redactar') {
        if ($s['estado'] !== 'activo') { return new WP_Error('estado','Solo se vuelve a redactar un seguimiento activo.'); }
        $error = at_rt_pedir_redaccion($id);
        if ($error === 'sin_flujo') { return new WP_Error('redaccion','El flujo de redacción no está configurado. Escribe los textos a mano.'); }
        if ($error !== '') { return new WP_Error('redaccion','No se pudo pedir la redacción: ' . sanitize_text_field($error)); }
        $ok = true; $mensaje = 'Redacción solicitada. La IA tarda un momento: recarga el panel para ver el resultado.';
    } elseif ($accion === 'baja') { $ok = at_rt_dar_baja($id, 'panel'); $mensaje = 'Contacto dado de baja de Retomar.';
    } elseif ($accion === 'revertir_baja') {
        global $wpdb;
        $motivo = trim(sanitize_text_field((string)($p['motivo'] ?? '')));
        if ($motivo === '' || mb_strlen($motivo)>255) { return new WP_Error('motivo','Escribe un motivo de 1 a 255 caracteres para revertir la baja.'); }
        $bajas = $wpdb->get_results("SELECT id,email,telefono FROM {$wpdb->prefix}at_retomar_bajas WHERE revertido_at IS NULL", ARRAY_A);
        $ok = false;
        foreach ($bajas as $b) { if (at_rt_contacto_coincide(strtolower($s['email']), at_pt_telefono_wa($s['telefono']), $b)) { $ok = at_rt_revertir_baja((int)$b['id'],$motivo) || $ok; } }
        $mensaje = 'Baja revertida. Este seguimiento sigue detenido: no vuelve a salir en el resumen semanal.';
    } elseif ($accion === 'previa') {
        $error = at_rt_pedir_render($id); $ok = $error === ''; $mensaje = 'Vista previa solicitada. Recarga el panel para ver el resultado.';
        if (!$ok) { return new WP_Error('render', $error === 'sin_flujo'?'El flujo de presentación no está configurado. La vista previa queda pendiente.':$error); }
    } elseif ($accion === 'prueba') {
        $r = at_rt_enviar($id,true); $ok = $r['ok']; $mensaje = 'Prueba enviada solo al equipo, sin sumar envíos ni enviar WhatsApp.';
        if (!$ok) { return new WP_Error('prueba','No se pudo enviar la prueba: ' . sanitize_text_field($r['motivo'])); }
    } elseif ($accion === 'whatsapp_manual') {
        if (in_array($s['estado'], ['baja','rechazado','cliente'], true) || at_rt_en_bajas($s['email'], $s['telefono'])) { return new WP_Error('detenido','Este prospecto no recibe recordatorios (baja, rechazo o ya es cliente).'); }
        $tel = at_pt_telefono_wa((string) $s['telefono']);
        if ($tel === '') { return new WP_Error('telefono','Agrega un teléfono con código de país para abrir WhatsApp. Puedes copiar el mensaje igual.'); }
        $texto = at_rt_panel_texto_manual($s);
        if (at_rt_internas(['texto' => $texto])) { return new WP_Error('interno','El mensaje tiene un enlace interno y no se comparte.'); }
        $ahora = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
        $ok = at_rt_agregar_envio($id, ['tipo'=>'manual','canal'=>'whatsapp','fecha'=>$ahora->format('Y-m-d'),'at'=>$ahora->format('Y-m-d H:i:s')]);
        at_rt_historia($s, 'retomar_whatsapp', 'Recordatorio compartido a mano por WhatsApp');
        return $ok ? ['id'=>$id,'mensaje'=>'WhatsApp abierto con el mensaje.','url'=>at_rt_url_wa_manual($tel, $texto)] : new WP_Error('guardar','No se pudo anotar el envío manual.');
    } else { return new WP_Error('accion','Acción desconocida.'); }
    return $ok ? ['id'=>$id,'mensaje'=>$mensaje] : new WP_Error('guardar','No se pudo completar la acción.');
}
/** Días de la semana (ISO-8601, 1 = lunes) para el resumen semanal. */
function at_rt_panel_dias(): array { return [1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado',7=>'domingo']; }
/** Día, hora (de Chile), destino y copia (CC, opcional) del correo semanal. Valida todo antes de guardar: con un dato malo no cambia nada. */
function at_rt_panel_config(array $p) {
    $dia = trim((string)($p['dia'] ?? '')); $hora = trim((string)($p['hora'] ?? '')); $destino = trim((string)($p['destino'] ?? '')); $copia = trim((string)($p['copia'] ?? ''));
    if (!preg_match('/^[1-7]$/D', $dia)) { return new WP_Error('dia','Elige un día de la semana.'); }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $hora)) { return new WP_Error('hora','Escribe la hora como HH:MM, entre 00:00 y 23:59 (por ejemplo 09:00).'); }
    if (!at_rt_resumen_hora_valida((int)$dia, $hora)) { return new WP_Error('hora','El domingo el resumen puede salir a más tardar a las ' . sprintf('23:%02d', at_rt_minuto_reloj()) . ', la última pasada del reloj de ese día: más tarde ya es otra semana y nunca saldría. Elige una hora más temprana u otro día.'); }
    if ($destino !== '' && (strlen($destino) > 190 || !is_email($destino))) { return new WP_Error('destino','El correo de destino no es válido (máximo 190 caracteres). Déjalo vacío para usar el de avisos del equipo.'); }
    if ($copia !== '' && (strlen($copia) > 190 || !is_email($copia))) { return new WP_Error('copia','El correo de copia no es válido (máximo 190 caracteres). Déjalo vacío si no quieres copia.'); }
    at_rt_resumen_marcar(['at_rt_resumen_dia'=>$dia,'at_rt_resumen_hora'=>$hora,'at_rt_resumen_destino'=>sanitize_email($destino),'at_rt_resumen_copia'=>sanitize_email($copia)]);
    return ['id'=>0,'mensaje'=>'Configuración guardada. El resumen sale los ' . at_rt_panel_dias()[(int)$dia] . ' a las ' . $hora . ' (hora de Chile).'];
}
/** «Enviarme el resumen ahora»: arma y manda el resumen de la semana aunque ya haya salido. */
function at_rt_panel_resumen() {
    $r = at_rt_resumen(at_rt_ahora_chile(), true);
    if ($r['enviado']) {
        $n = (int) $r['tarjetas'];
        return ['id'=>0,'mensaje'=>'Listo: te enviamos el resumen de la semana' . ($n > 0 ? ' (' . $n . ($n === 1 ? ' prospecto' : ' prospectos') . ' para revisar)' : '') . '. Revisa tu correo.'];
    }
    if ($r['motivo'] === 'vacio') { return ['id'=>0,'mensaje'=>'No hay prospectos para revisar esta semana.']; }
    $motivos = ['correo_error'=>'No se pudo enviar el correo del resumen. Inténtalo de nuevo en unos minutos.', 'ocupado'=>'Ya se está armando un resumen. Espera un minuto y revisa tu correo.', 'destino'=>'El correo de destino del resumen no es válido. Corrígelo en la configuración.'];
    return new WP_Error('resumen', $motivos[$r['motivo']] ?? 'No se pudo armar el resumen (' . sanitize_key((string)$r['motivo']) . ').');
}
/**
 * Guarda el seguimiento. Los tres textos van llenos (quedan «lista» a mano y se pide la presentación) o vacíos (los redacta
 * la IA con las notas). Un seguimiento nuevo o en borrador queda activo si puede recibir recordatorios; uno que espera una
 * propuesta sale de la espera al vincularle una v3 (at_rt_panel_vincular). Solo un seguimiento activo pide algo a n8n.
 */
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
    $crudos = [];
    foreach (['quedamos','proponemos','partir'] as $k) { $crudos[$k] = trim((string)($p[$k] ?? '')); }
    $llenos = count(array_filter($crudos, static fn(string $t): bool => $t !== ''));
    if ($llenos !== 0 && $llenos !== 3) { return new WP_Error('texto','Completa los tres textos o deja los tres vacíos.'); }
    $manual = $llenos === 3;
    if ($manual) {
        $d['textos'] = is_array($s['textos'] ?? null) ? $s['textos'] : []; // lo demás (p. ej. la recomendación) se conserva
        foreach ($crudos as $k => $t) {
            if (mb_strlen($t)>600 || count(preg_split('/\r\n|\r|\n/', $t))>6) { return new WP_Error('texto', $k . ': escribe de 1 a 600 caracteres, máximo 6 líneas.'); }
            $d['textos'][$k] = sanitize_textarea_field($t);
        }
    }
    // Las notas para la IA se acotan a sus últimos 4000 caracteres (lo más reciente). Sin el campo en el envío, se conservan.
    if (array_key_exists('notas', $p)) { $d['notas'] = mb_substr(sanitize_textarea_field((string)$p['notas']), -4000); }
    $d['demo_urls'] = array_values(array_filter([trim((string)($p['demo_1'] ?? '')),trim((string)($p['demo_2'] ?? ''))], static fn($v)=>$v!==''));
    foreach (array_merge($d['demo_urls'],[$d['propuesta_url'],$d['imagen_url']]) as $url) { if ($url !== '' && (strlen($url)>500 || at_rt_url_correo($url)==='')) { return new WP_Error('url','Usa enlaces HTTPS públicos válidos (máximo 500 caracteres); no enlaces internos.'); } }
    if (at_rt_internas($d)) { return new WP_Error('interno','El contenido incluye enlaces internos. Usa solo información pública.'); }
    $intervalo = filter_var($p['intervalo_dias'] ?? 7,FILTER_VALIDATE_INT);
    $max = filter_var($p['max_envios'] ?? 4,FILTER_VALIDATE_INT);
    if ($intervalo===false || $intervalo>255 || $intervalo<0 || $max===false || $max<1 || $max>4) { return new WP_Error('limites','El máximo es de 1 a 4 envíos; el intervalo admite hasta 255 días y se ajusta a un mínimo de 7.'); }
    $d += ['intervalo_dias'=>max(7,$intervalo),'max_envios'=>$max,'wa_autorizado'=>empty($p['wa_autorizado'])?0:1,'canales'=>($p['canales']??'correo')==='correo,whatsapp'?'correo,whatsapp':'correo'];
    $crm_id = absint($p['crm_cliente_id'] ?? 0); $propuesta_id = absint($p['propuesta_id'] ?? 0); $prop = null;
    if ($crm_id) {
        $crm = $wpdb->get_row($wpdb->prepare("SELECT id,tipo,email FROM {$wpdb->prefix}crm_clientes WHERE id=%d",$crm_id),ARRAY_A);
        if (!$crm || $crm['tipo']!=='prospecto' || ($crm['email']!=='' && strtolower($crm['email'])!==$d['email'])) { return new WP_Error('crm','Selecciona una ficha de prospecto con el mismo correo. No se convierten clientes existentes.'); }
    }
    if ($propuesta_id) {
        $prop = $wpdb->get_row($wpdb->prepare("SELECT id,client_email,flujo FROM {$wpdb->prefix}automatiza_propuestas WHERE id=%d",$propuesta_id),ARRAY_A);
        if (!$prop || ($prop['client_email']!=='' && strtolower($prop['client_email'])!==$d['email'])) { return new WP_Error('propuesta','La propuesta debe pertenecer al mismo correo del prospecto.'); }
    }
    if (!$crm_id && !empty($p['crear_crm'])) {
        if (!is_email($d['email'])) { return new WP_Error('crm','Para crear la ficha CRM agrega un correo válido.'); }
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}crm_clientes WHERE LOWER(email)=%s LIMIT 1",$d['email']))) { return new WP_Error('crm','Ya existe una ficha con ese correo. Búscala y selecciónala.'); }
        if (!$wpdb->insert($wpdb->prefix.'crm_clientes',['tipo'=>'prospecto','estado'=>'nuevo','nombre'=>$d['nombre'],'empresa'=>$d['negocio'],'email'=>$d['email'],'telefono'=>$d['telefono'],'fecha_contacto'=>current_time('mysql')])) { return new WP_Error('crm','No se pudo crear la ficha CRM.'); }
        $crm_id = (int)$wpdb->insert_id;
    }
    $d += ['crm_cliente_id'=>$crm_id,'propuesta_id'=>$propuesta_id];
    // ¿Queda activo? Un seguimiento nuevo o en borrador entra a Retomar si puede recibir recordatorios; el que espera una
    // propuesta sale de la espera al vincularle una propuesta con la plantilla AT (v3). Activar reinicia activado_at: una reunión
    // anterior a este momento (p. ej. la que dio origen a la propuesta nueva) no lo detiene.
    $ahora = at_rt_ahora_chile(); $hoy = substr($ahora, 0, 10);
    $estado = $s['estado'] ?? 'borrador'; $activar = false; $reemplaza = 0; $aviso = '';
    if ($estado === 'borrador' || ($estado === 'espera_propuesta' && ($prop['flujo'] ?? null) === 'v3')) {
        $motivo = is_email($d['email']) ? at_rt_motivo_parada(at_rt_contexto_parada(array_merge($s ?? [], $d, ['activado_at'=>$ahora]))) : 'correo';
        if ($motivo === '') {
            $activar = true;
            if ($estado === 'espera_propuesta' && (int)$s['propuesta_id'] > 0 && (int)$s['propuesta_id'] !== $propuesta_id) { $reemplaza = (int)$s['propuesta_id']; }
        } else {
            $aviso = $motivo === 'correo' ? 'Agrega un correo válido para que entre al resumen semanal.' : at_rt_panel_motivo_texto($motivo);
            if ($estado === 'espera_propuesta') { return new WP_Error('detenido', $aviso . ' No se vinculó la propuesta.'); }
        }
    }
    if ($activar) { $d += ['estado'=>'activo','activado_at'=>$ahora,'proximo_at'=>$hoy]; }
    // Cada guardado invalida la versión previa ANTES de pedir la nueva. Sin flujo nunca queda lista.
    $d['render'] = ['estado'=>'pendiente','nota'=>$manual ? 'Contenido guardado; falta generar la presentación.' : 'Esperando redacción'];
    // A mano: la redacción queda «lista» con una generación nueva, así un callback tardío de la IA recibe 409 y no pisa lo escrito.
    if ($manual) { $d['redaccion'] = array_merge(is_array($s['redaccion'] ?? null) ? $s['redaccion'] : [], ['estado'=>'lista','origen'=>'manual','generacion'=>bin2hex(random_bytes(8)),'nota'=>'']); }
    if ($reemplaza) {
        $r = at_rt_panel_vincular($id, $d, $reemplaza, $propuesta_id);
        if (is_wp_error($r)) { return $r; }
    } elseif ($id ? !at_rt_panel_escribir($id, $d, $ahora) : !($id=at_rt_crear($d))) { return new WP_Error('guardar','No se pudo guardar el seguimiento.'); }
    $extra = $reemplaza ? ' La propuesta anterior quedó archivada.' : '';
    if (($d['estado'] ?? $estado) !== 'activo') {
        return ['id'=>$id,'mensaje'=>$aviso !== '' ? 'Guardado como borrador: ' . $aviso : 'Guardado. El seguimiento no está activo, así que no se pidió ni la redacción ni la presentación.'];
    }
    // El pedido a n8n va después del guardado (y de la transacción): si falla, lo guardado queda.
    if ($manual) {
        $error = at_rt_pedir_render($id);
        $mensaje = $error === 'sin_flujo' ? 'Guardado. El flujo de presentación no está configurado; la presentación queda pendiente.'
            : ($error !== '' ? 'Guardado. No se pudo pedir la presentación: ' . sanitize_text_field($error) : 'Guardado. Presentación solicitada: cuando esté lista aparecerá en el resumen semanal para que la apruebes.');
    } else {
        $error = at_rt_pedir_redaccion($id);
        $mensaje = $error === 'sin_flujo' ? 'Guardado. El flujo de redacción no está configurado: escribe los textos a mano.'
            : ($error !== '' ? 'Guardado. No se pudo pedir la redacción: ' . sanitize_text_field($error) : 'Guardado. Redacción solicitada: la IA tarda un momento. Recarga el panel para ver el resultado.');
    }
    return ['id'=>$id,'mensaje'=>$mensaje . $extra];
}
/**
 * Vincula la propuesta nueva (v3) a un seguimiento que esperaba una, todo en una transacción: archiva la anterior (si no es
 * v3 ni estaba ya archivada) con at_cc_archivar_propuesta, la anota «Reemplazada por la propuesta N» y guarda el seguimiento
 * ya activo ($d). Si no se puede archivar, devuelve el motivo y no cambia nada.
 */
function at_rt_panel_vincular(int $id, array $d, int $vieja_id, int $nueva_id) {
    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('guardar','No se pudo reservar el seguimiento.'); }
    $confirmado = false;
    try {
        $fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
        if (!$fila || $fila['estado'] !== 'espera_propuesta') { return new WP_Error('estado','El seguimiento cambió mientras lo guardabas. Recarga el panel.'); }
        $d = at_rt_panel_bloqueado(at_rt_fila($fila), $d, at_rt_ahora_chile());
        $vieja = function_exists('at_cc_propuesta_por_id') ? at_cc_propuesta_por_id($vieja_id) : null;
        if ($vieja && ($vieja->flujo ?? null) !== 'v3' && ($vieja->status ?? '') !== 'archivada') {
            if (!function_exists('at_cc_archivar_propuesta')) { return new WP_Error('propuesta','No se pudo archivar la propuesta anterior: falta el módulo de cierre de cliente.'); }
            $r = at_cc_archivar_propuesta($vieja, get_current_user_id());
            if (empty($r['ok'])) { return new WP_Error('propuesta', (string)($r['mensaje'] ?? 'No se pudo archivar la propuesta anterior.')); }
            if (function_exists('at_cc_anotar_simple')) {
                // aviso_operativo es un tipo interno: la nota nunca llega al portal del cliente.
                at_cc_anotar_simple($vieja, 'aviso_operativo', 'Reemplazada por la propuesta ' . $nueva_id, 'Retomar vinculó una propuesta nueva con la plantilla AT; esta queda como historial.', ['retomar_id'=>$id,'reemplazada_por'=>$nueva_id]);
            }
        }
        if (!at_rt_guardar($id, $d)) { return new WP_Error('guardar','No se pudo guardar el seguimiento.'); }
        $confirmado = $wpdb->query('COMMIT') !== false;
        return $confirmado ? true : new WP_Error('guardar','No se pudo confirmar el guardado.');
    } finally {
        if (!$confirmado) { $wpdb->query('ROLLBACK'); }
    }
}
/**
 * Lo que un guardado del panel no puede perder ni dejar vivo; se aplica con la fila ya bloqueada ($actual). La versión
 * publicada de la presentación (Ruling 17) pasa al render nuevo, y un recordatorio `aprobado` queda con la aprobación
 * muerta (Ruling 18: Luis aprobó otro contenido u otro destinatario; así, cuando la presentación nueva quede lista, le
 * llega el aviso «Recordatorio corregido» del Ruling 11).
 */
function at_rt_panel_bloqueado(array $actual, array $d, string $ahora): array {
    if (is_array($d['render'] ?? null)) { $d['render'] = at_rt_render_heredar(is_array($actual['render'] ?? null) ? $actual['render'] : [], $d['render'], false); }
    if (($actual['aprobacion']['estado'] ?? '') === 'aprobado') { $d['aprobacion'] = at_rt_aprobacion_muerta($actual['aprobacion'], $ahora); }
    return $d;
}
/** Guarda un seguimiento existente desde el panel bajo START TRANSACTION + FOR UPDATE (at_rt_panel_bloqueado). */
function at_rt_panel_escribir(int $id, array $d, string $ahora): bool {
    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false) { return false; }
    try {
        $fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
        if (!$fila || !at_rt_guardar($id, at_rt_panel_bloqueado(at_rt_fila($fila), $d, $ahora))) { return false; }
        return $wpdb->query('COMMIT') !== false;
    } finally {
        $wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno o error.
    }
}
/**
 * Estado de la revisión de un seguimiento, en palabras (sin datos internos ni enlaces): qué falta para que Luis lo apruebe o
 * cuándo sale. Se apoya en la misma regla que arma las tarjetas del resumen semanal (at_rt_resumen_tipo). $ahora: «Y-m-d
 * H:i:s» de Chile (o solo «Y-m-d»), para saber si una presentación lleva más de 24 h esperando.
 */
function at_rt_panel_revision(array $s, string $ahora): string {
    $hoy = substr($ahora, 0, 10);
    $tipo = at_rt_resumen_tipo($s, $ahora);
    if ($tipo === 'propuesta') { return 'Necesita propuesta nueva'; }
    if ($tipo === 'revision') { return 'Necesita revisión'; }
    $ap = is_array($s['aprobacion'] ?? null) ? $s['aprobacion'] : [];
    $render = is_array($s['render'] ?? null) ? $s['render'] : [];
    $listo = ($render['estado'] ?? '') === 'listo' && is_string($render['huella'] ?? null) && $render['huella'] !== '';
    if ($tipo === 'normal') {
        $resuelto = is_string($ap['resuelto_at'] ?? null) ? $ap['resuelto_at'] : '';
        $semana = $resuelto !== '' ? at_rt_semana($resuelto) : '';
        return ($ap['estado'] ?? '') === 'omitido' && $semana !== '' && $semana === at_rt_semana($hoy) ? 'Omitido esta semana' : 'Lista para aprobar';
    }
    if ($tipo === 'preparacion') {
        if (($ap['estado'] ?? '') === 'aprobado' && !$listo) { return 'Aprobado, esperando la presentación'; }
        return ($s['redaccion']['estado'] ?? '') === 'lista' ? 'Preparando la presentación' : 'Redactando';
    }
    $programado = is_string($ap['programado'] ?? null) ? $ap['programado'] : '';
    if (($s['estado'] ?? '') === 'activo' && ($ap['estado'] ?? '') === 'aprobado' && at_rt_fecha($programado) !== null) { return 'Aprobado, sale el ' . $programado; }
    return '—';
}
/** Botón «🔁 Retomar» de la ficha CRM: solo para un prospecto; abre el panel con sus datos ya cargados. */
function at_rt_boton_ficha(array $cliente): void {
    $id = absint($cliente['id'] ?? 0);
    if (($cliente['tipo'] ?? '') !== 'prospecto' || $id === 0) { return; }
    echo '<p class="at-rt-ficha-boton" style="margin:0 0 12px"><a class="button" href="' . esc_attr(admin_url('admin.php?page=at-retomar&crm=' . $id)) . '">🔁 Retomar</a></p>';
}
/** Texto para compartir a mano: el de la plantilla de Meta con los enlaces del prospecto. «Ver y agendar» abre su página
 *  (la presentación, «Agendar» y «No me interesa»), como el botón de la plantilla; la baja va aparte. */
function at_rt_panel_texto_manual(array $s): string {
    $base = home_url('/retomar.php?t=' . rawurlencode((string) $s['token']));
    return at_rt_texto_manual((string) $s['nombre'], (string) $s['negocio'], $base, $base . '&baja=1');
}
function at_rt_panel_form_inicio(string $accion,int $id): void {
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    echo '<input type="hidden" name="action" value="at_rt_'.esc_attr($accion).'"><input type="hidden" name="id" value="'.$id.'">';
    wp_nonce_field('at_rt_'.$accion.'_'.$id);
}
function at_rt_panel(): void {
    if (!current_user_can('manage_options')) { wp_die('No tienes permiso.', '', ['response'=>403]); }
    global $wpdb;
    $id=absint($_GET['id']??0); $crm_id=absint($_GET['crm']??0);
    // Desde la ficha CRM (?crm=): si ya tiene seguimiento se abre ese; si no, el formulario nuevo sale con sus datos.
    if (!$id && $crm_id) { $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE crm_cliente_id=%d ORDER BY id DESC LIMIT 1",$crm_id)); }
    $s=$id?at_rt_obtener($id):null; $pre=[];
    if (!$s && $crm_id) {
        $f=$wpdb->get_row($wpdb->prepare("SELECT id,nombre,empresa,email,telefono FROM {$wpdb->prefix}crm_clientes WHERE id=%d AND tipo='prospecto'",$crm_id),ARRAY_A);
        if ($f) { $pre=['crm_cliente_id'=>(int)$f['id'],'nombre'=>$f['nombre'],'negocio'=>$f['empresa'],'email'=>$f['email'],'telefono'=>$f['telefono']]; }
    }
    $v=$s?:$pre; $id=$s?$id:0; $hoy=at_rt_ahora_chile(); $cfg=at_rt_config(); // $hoy con la hora: la revisión mide esperas de 24 h
    echo '<div class="wrap at-retomar"><h1>🔁 Retomar prospectos</h1><p>Recordatorios del equipo de AutomatizaTech. Cada semana te llega un correo para aprobarlos; nada sale al prospecto sin tu aprobación, en días hábiles entre 10:00 y 13:00 de Santiago.</p>';
    echo at_rt_wa_automatico()
        ? '<div class="notice notice-success inline rt-wa-estado"><p><strong>🟢 WhatsApp automático: encendido.</strong> El WhatsApp sale en cada recordatorio que apruebes a quien tenga la casilla «WhatsApp autorizado».</p></div>'
        : '<div class="notice notice-warning inline rt-wa-estado"><p><strong>⏸️ WhatsApp automático: en pausa (apagado).</strong> Mientras Meta revisa la plantilla, el reloj solo manda correos. Para avisar por WhatsApp, usa «💬 Compartir a mano» en cada prospecto.</p></div>';
    if (!empty($_GET['rt_msg'])) { echo '<div role="status" class="notice '.(!empty($_GET['rt_error'])?'notice-error':'notice-success').'"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['rt_msg']))).'</p></div>'; }
    echo '<div class="rt-resumen"><h2>📬 Resumen semanal</h2><p>Un correo por semana con los recordatorios listos para aprobar, pedir cambios u omitir. «Enviarme el resumen ahora» cuenta como el de esta semana.</p>';
    at_rt_panel_form_inicio('config',0);
    echo '<div class="rt-grid"><p><label for="rt-dia">Día de envío</label><select id="rt-dia" name="dia">';
    foreach (at_rt_panel_dias() as $n=>$nombre) { echo '<option value="'.$n.'" '.selected($cfg['dia'],$n,false).'>'.esc_html(ucfirst($nombre)).'</option>'; }
    echo '</select></p><p><label for="rt-hora">Hora de envío (Santiago)</label><input id="rt-hora" type="time" name="hora" value="'.esc_attr($cfg['hora']).'" required></p></div>';
    echo '<p><label for="rt-destino">Correo de destino (vacío = el de avisos del equipo)</label><input id="rt-destino" type="email" name="destino" maxlength="190" value="'.esc_attr((string)get_option('at_rt_resumen_destino','')).'" placeholder="'.esc_attr($cfg['destino']).'"></p>';
    echo '<p><label for="rt-copia">Copia (opcional)</label><input id="rt-copia" type="email" name="copia" maxlength="190" value="'.esc_attr((string)get_option('at_rt_resumen_copia','')).'"></p><button class="button">Guardar configuración</button></form>';
    at_rt_panel_form_inicio('resumen',0); echo '<button class="button button-primary">📬 Enviarme el resumen ahora</button></form></div>';
    echo '<div class="rt-tabla"><table class="widefat striped"><caption class="screen-reader-text">Seguimientos de prospectos</caption><thead><tr><th>Prospecto</th><th>Estado</th><th>Revisión</th><th>Envíos / máximo</th><th>Próximo</th><th>Último resultado</th></tr></thead><tbody>';
    foreach (at_rt_lista() as $f) {
        $ultimo=end($f['envios']); $res=$ultimo?wp_json_encode($ultimo,JSON_UNESCAPED_UNICODE):'Sin envíos';
        echo '<tr><td><a href="'.esc_url(add_query_arg(['page'=>'at-retomar','id'=>$f['id']],admin_url('admin.php'))).'">'.esc_html($f['nombre'].' · '.$f['negocio']).'</a></td><td>'.esc_html($f['estado']).'</td><td>'.esc_html(at_rt_panel_revision($f,$hoy)).'</td><td>'.at_rt_envios_realizados($f['envios']).' / '.$f['max_envios'].'</td><td>'.esc_html($f['proximo_at']?:'—').'</td><td class="rt-resultado">'.esc_html($res).'</td></tr>';
    }
    echo '</tbody></table></div><p><a class="button" href="'.esc_url(add_query_arg('page','at-retomar',admin_url('admin.php'))).'">Nuevo seguimiento</a></p><h2>'.($s?'Editar seguimiento #'.$id:'Nuevo seguimiento').'</h2>';
    echo '<form method="get"><input type="hidden" name="page" value="at-retomar"><input type="hidden" name="id" value="'.$id.'"><label for="rt-buscar">Buscar ficha CRM (nombre, empresa o correo)</label> <input id="rt-buscar" name="buscar" value="'.esc_attr(wp_unslash($_GET['buscar']??'')).'"><button class="button">Buscar</button></form>';
    at_rt_panel_form_inicio('guardar',$id);
    $buscar=sanitize_text_field(wp_unslash($_GET['buscar']??'')); $like='%'.$wpdb->esc_like($buscar).'%';
    $clientes=$wpdb->get_results($wpdb->prepare("SELECT id,nombre,empresa,email,telefono FROM {$wpdb->prefix}crm_clientes WHERE tipo='prospecto' AND (nombre LIKE %s OR empresa LIKE %s OR email LIKE %s OR id=%d) ORDER BY nombre LIMIT 100",$like,$like,$like,(int)($v['crm_cliente_id']??0)),ARRAY_A);
    echo '<p><label for="rt-crm">Ficha de prospecto CRM</label><select id="rt-crm" name="crm_cliente_id"><option value="0">Sin ficha seleccionada</option>';
    foreach ($clientes as $c) { echo '<option data-nombre="'.esc_attr($c['nombre']).'" data-negocio="'.esc_attr($c['empresa']).'" data-email="'.esc_attr($c['email']).'" data-telefono="'.esc_attr($c['telefono']).'" value="'.$c['id'].'" '.selected($v['crm_cliente_id']??0,$c['id'],false).'>'.esc_html('#'.$c['id'].' · '.$c['nombre'].' · '.$c['empresa'].' · '.$c['email']).'</option>'; }
    echo '</select><label><input type="checkbox" name="crear_crm" value="1"> Crear ficha CRM como prospecto si aún no existe</label></p><div class="rt-grid">';
    foreach (['nombre'=>['Nombre',120],'negocio'=>['Negocio',120],'email'=>['Correo',190],'telefono'=>['Teléfono con código de país',50],'propuesta_url'=>['URL externa de la propuesta (opcional)',500],'imagen_url'=>['Imagen de la demo (biblioteca de medios)',500]] as $k=>$meta) {
        echo '<p><label for="rt-'.$k.'">'.esc_html($meta[0]).'</label><input id="rt-'.$k.'" name="'.$k.'" type="'.($k==='email'?'email':(str_ends_with($k,'url')?'url':'text')).'" maxlength="'.$meta[1].'" value="'.esc_attr($v[$k]??'').'" '.($k==='nombre'?'required':'').'></p>';
    }
    echo '</div><button type="button" class="button" id="rt-media">Elegir imagen</button><p><label for="rt-propuesta">Propuesta vinculada (opcional, mismo correo)</label><select id="rt-propuesta" name="propuesta_id"><option value="0">Sin propuesta vinculada</option>';
    $props=$wpdb->get_results("SELECT id,client_name,company_name,client_email,status FROM {$wpdb->prefix}automatiza_propuestas ORDER BY id DESC",ARRAY_A);
    foreach ($props as $p) { echo '<option value="'.$p['id'].'" '.selected($s['propuesta_id']??0,$p['id'],false).'>'.esc_html('#'.$p['id'].' · '.$p['client_name'].' · '.$p['company_name'].' · '.$p['client_email'].' · '.$p['status']).'</option>'; }
    echo '</select></p>';
    foreach ([1,2] as $n) { echo '<p><label for="rt-demo-'.$n.'">Demo '.$n.' (HTTPS pública, opcional)</label><input id="rt-demo-'.$n.'" name="demo_'.$n.'" type="url" maxlength="500" value="'.esc_attr($s['demo_urls'][$n-1]??'').'"></p>'; }
    echo '<fieldset class="rt-textos"><legend>Texto final (opcional: si lo dejas vacío, lo redacta la IA)</legend>';
    foreach (['quedamos'=>'En qué quedamos','proponemos'=>'Qué proponemos','partir'=>'Por dónde podemos partir'] as $k=>$label) { echo '<p><label for="rt-'.$k.'">'.$label.'</label><textarea id="rt-'.$k.'" name="'.$k.'" rows="6" maxlength="600" aria-describedby="rt-textos-ayuda">'.esc_textarea($s['textos'][$k]??'').'</textarea></p>'; }
    echo '<p id="rt-textos-ayuda">Llena los tres o deja los tres vacíos. Cada texto admite hasta 600 caracteres y 6 líneas. No incluyas enlaces internos. El proyecto toma el nombre del negocio.</p></fieldset>';
    echo '<p><label for="rt-notas">Notas para la IA</label><textarea id="rt-notas" name="notas" rows="4" maxlength="4000" aria-describedby="rt-notas-ayuda">'.esc_textarea($s['notas']??'').'</textarea><span id="rt-notas-ayuda">Lo que la IA debe tener en cuenta al redactar (se conservan los últimos 4000 caracteres). La IA no usa montos ni precios.</span></p>';
    echo '<div class="rt-grid"><p><label for="rt-max">Máximo de envíos</label><input id="rt-max" type="number" min="1" max="4" name="max_envios" value="'.(int)($s['max_envios']??4).'"></p><p><label for="rt-intervalo">Intervalo en días</label><input id="rt-intervalo" type="number" min="7" max="255" name="intervalo_dias" value="'.(int)($s['intervalo_dias']??7).'" aria-describedby="rt-intervalo-ayuda"><span id="rt-intervalo-ayuda">Mínimo 7 días (predeterminado); máximo 255 días.</span></p></div><p><label for="rt-canales">Canales</label><select id="rt-canales" name="canales"><option value="correo">Correo</option><option value="correo,whatsapp" '.selected($s['canales']??'','correo,whatsapp',false).'>Correo y WhatsApp</option></select></p><p><label for="rt-wa"><input id="rt-wa" type="checkbox" name="wa_autorizado" value="1" '.checked($s['wa_autorizado']??0,1,false).' aria-describedby="rt-wa-ayuda"> WhatsApp autorizado</label><span id="rt-wa-ayuda">Márcala solo si esta persona te escribió o conversó contigo por WhatsApp</span></p><button class="button button-primary">Guardar</button></form>';
    if ($s) {
        echo '<h2>Acciones del seguimiento</h2><div class="rt-acciones">';
        $acciones = [];
        if ($s['estado'] === 'activo') { $acciones['redactar'] = '✨ Volver a redactar'; }
        if (in_array($s['estado'], ['pausado','respondio'], true)) { $acciones['reanudar'] = '▶️ Reanudar'; }
        $acciones += ['previa'=>'Vista previa','prueba'=>'Enviar prueba al equipo','pausar'=>'Pausar','respondio'=>'Respondió','baja'=>'Dar de baja'];
        foreach ($acciones as $a=>$label) { at_rt_panel_form_inicio($a,$id); echo '<button class="button">'.$label.'</button></form>'; }
        echo '</div>';
        // Compartir a mano mientras Meta aprueba la plantilla: copiar el mensaje o abrir el WhatsApp de Luis.
        if (!in_array($s['estado'], ['baja','rechazado','cliente'], true)) {
            $tel = at_pt_telefono_wa((string) $s['telefono']);
            echo '<div class="rt-manual"><h2>💬 Compartir a mano</h2><p>' . (at_rt_wa_automatico() ? 'El WhatsApp automático está encendido; esta opción sirve para escribirle además a mano.' : '<strong>El WhatsApp automático está en pausa</strong> mientras Meta aprueba la plantilla.') . ' Copia este mensaje o ábrelo en tu WhatsApp. Queda anotado en el historial y no cuenta como recordatorio automático.</p>'
                . '<p><label for="rt-texto-manual">Mensaje para el prospecto</label><textarea id="rt-texto-manual" rows="7" readonly>' . esc_textarea(at_rt_panel_texto_manual($s)) . '</textarea></p>'
                . '<p class="rt-acciones"><button type="button" class="button" onclick="(function(b){var t=document.getElementById(\'rt-texto-manual\');var listo=function(){b.textContent=\'✅ Copiado\';};if(navigator.clipboard){navigator.clipboard.writeText(t.value).then(listo,function(){t.select();document.execCommand(\'copy\');listo();});}else{t.select();document.execCommand(\'copy\');listo();}})(this)">📋 Copiar</button> ';
            if ($tel !== '') {
                echo '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" target="_blank" rel="noopener"><input type="hidden" name="action" value="at_rt_whatsapp_manual"><input type="hidden" name="id" value="' . $id . '">'
                    . wp_nonce_field('at_rt_whatsapp_manual_' . $id, '_wpnonce', true, false) . '<button class="button button-primary">💬 Abrir mi WhatsApp</button></form>';
            } else {
                echo '<span class="description">Agrega un teléfono con código de país para abrir WhatsApp.</span></p>';
            }
            echo '</div>';
        }
        at_rt_panel_form_inicio('revertir_baja',$id); echo '<p><label for="rt-motivo">Motivo para revertir la baja</label><input id="rt-motivo" name="motivo" maxlength="255" required></p><button class="button">Revertir baja</button></form>';
        echo '<h2>Revisión</h2><p role="status"><strong>'.esc_html(at_rt_panel_revision($s,$hoy)).'</strong></p>';
        $nota_red=(string)($s['redaccion']['nota']??'');
        if ($nota_red !== '') { echo '<p>Nota de la redacción: '.esc_html($nota_red).'</p>'; }
        echo '<h2>Presentación</h2><p role="status">'.esc_html(($s['render']['estado']??'Sin generar').' · '.($s['render']['nota']??'')).'</p>';
        $view=(string)($s['render']['view_url']??'');
        if (($s['render']['estado']??'')==='listo' && at_rt_url_render_valida($view,$s['token'])) { echo '<a href="'.esc_url($view).'" target="_blank" rel="noopener">Abrir presentación</a><iframe title="Vista previa de Retomar" src="'.esc_url($view).'" loading="lazy"></iframe>'; }
    }
    echo '</div>';
}
