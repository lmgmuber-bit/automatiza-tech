<?php
/** Reseñas: reclamo CAS antes del proveedor, sin reintentos automáticos de efectos externos. */
if (!defined('ABSPATH')) { exit; }
const AT_N8N_RESENA_WHATSAPP = 'https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-whatsapp';
const AT_N8N_RESENA_RESUMEN = 'https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-resumen';
function at_rg_ahora(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('America/Santiago')); }
function at_rg_error(string $motivo, int $status = 409): WP_Error {
    $mensajes = ['enlace'=>'Falta configurar un enlace válido al perfil de Google.', 'cliente'=>'La ficha debe existir y ser de tipo cliente.', 'termino'=>'Marca una fecha real de término válida.',
        'historico'=>'Los proyectos terminados antes del 19-nov-2026 requieren Enviar ahora.', 'plazo'=>'Todavía no pasan siete días desde el término.', 'canales'=>'No hay un canal válido y permitido para enviar.',
        'excluido'=>'El proyecto está excluido de estas invitaciones.', 'bloqueado'=>'Hay un intento en curso o desconocido: revisa sus estados.', 'intentado'=>'Ya existe una invitación real; revisa el botón de reenvío.',
        'cupo'=>'Ya se utilizaron las invitaciones permitidas o el único reenvío.', 'plazo_reenvio'=>'Todavía no pasan siete días desde la primera invitación.', 'destinos_prueba'=>'Configura tanto el correo como el teléfono de prueba; no se usó ningún contacto del cliente.',
        'limite_pruebas'=>'Se alcanzó el límite de veinte pruebas conservadas en este proyecto.', 'sin_invitacion'=>'Todavía no hay una invitación para volver a enviar.', 'no_autorizado'=>'Se requiere la clave de máquina.',
        'anonimizado'=>'Registro anonimizado por conservación: no admite nuevos envíos.',
        'resena_recibida'=>'El cliente ya dejó su reseña: no se envían más invitaciones.',
        'motivo_reenvio'=>'Indica un motivo objetivo de reparación; no puede ser una expectativa de opinión.',
        'baja'=>'Hay una baja del destinatario: no se envía un recordatorio.',
        'plan_reasignado'=>'el plan fue reasignado a otro cliente: revisar a mano',
        'limite_fallidos'=>'Bloqueado: 3 intentos reales totalmente fallidos en este proyecto. Revisar con Luis antes de continuar.',
        'registro_ilegible'=>'Registro bloqueado: requiere revisar la base sin borrar las marcas.', 'despacho_usado'=>'Este despacho ya se utilizó o no está en curso.', 'despacho_vencido'=>'El despacho venció: revisar sin reintentar.',
        'whatsapp_no_permitido'=>'El permiso, la baja o el contacto cambió antes del WhatsApp.', 'consulta_bajas'=>'No se pudo comprobar la baja del destinatario; envío bloqueado.', 'correo_sin_confirmar'=>'El correo pudo salir, pero no se pudo guardar la confirmación. Revisar sin repetir.'];
    return new WP_Error('resena_' . $motivo, $mensajes[$motivo] ?? 'No se pudo completar la acción de reseña. Revisa los datos y los estados antes de repetir.', ['status' => $status]);
}
function at_rg_cliente(int $crm): array {
    global $wpdb;
    $c = $wpdb->get_row($wpdb->prepare("SELECT id,tipo,nombre,empresa,email,telefono FROM {$wpdb->prefix}crm_clientes WHERE id=%d", $crm), ARRAY_A);
    return is_array($c) ? $c : [];
}
/** Un plan reasignado o borrado no habilita envíos al CRM que antes estaba asociado. */
function at_rg_cliente_proyecto(object $f): array {
    if ($f->plan_id) {
        $p = at_pt_plan((int) $f->plan_id);
        if (!$p || (int) $p->crm_cliente_id !== (int) $f->crm_id) { return []; }
    }
    return at_rg_cliente((int) $f->crm_id);
}
function at_rg_plan_reasignado(object $f): bool {
    $p = !empty($f->plan_id) ? at_pt_plan((int) $f->plan_id) : null;
    return $p && (int) $p->crm_cliente_id !== (int) $f->crm_id;
}
function at_rg_contexto(array $d, array $c, array $opciones = []): ?array {
    $bajas = at_rg_bajas($c);
    return $bajas === null ? null : $opciones + ['url' => (string) get_option('at_resena_google_url', ''), 'permiso' => $d['permiso'], 'bajas' => $bajas];
}
/** Solo el panel puede pasar manual/reenvío/prueba. REST de máquina no acepta esas excepciones. */
function at_rg_reclamar(int $id, array $opciones = []) {
    $prueba = !empty($opciones['prueba']); $ahora = at_rg_ahora();
    for ($n = 0; $n < 4; $n++) {
        $f = at_rg_fila($id); if (!$f) { return at_rg_error('proyecto', 404); }
        if (at_rg_plan_reasignado($f)) { return at_rg_error('plan_reasignado'); }
        try { $d = at_rg_vencer(at_rg_datos($f), $ahora); } catch (Throwable $e) { return at_rg_error('registro_ilegible', 503); }
        $c = at_rg_cliente_proyecto($f);
        $ctx = at_rg_contexto($d, $c, $opciones);
        if ($ctx === null) { return at_rg_error('consulta_bajas', 503); }
        $motivo = at_rg_elegibilidad($d, $c, $ctx, $ahora);
        if ($motivo !== '') { return at_rg_error($motivo); }
        $canales = at_rg_canales($c, $ctx);
        $destinos = ['correo' => at_rg_email((string) ($c['email'] ?? '')), 'whatsapp' => at_rg_telefono((string) ($c['telefono'] ?? ''))];
        if ($prueba) {
            // Ambos exigidos, sin fallback al cliente aunque falte un solo dato de prueba.
            $destinos = ['correo' => at_rg_email((string) get_option('at_resena_prueba_email', '')), 'whatsapp' => at_rg_telefono((string) get_option('at_resena_prueba_telefono', ''))];
            if (in_array('', $destinos, true)) { return at_rg_error('destinos_prueba'); }
            if (count(array_filter($d['intentos'], fn($i) => $i['modo'] === 'prueba')) >= 20) { return at_rg_error('limite_pruebas'); }
        }
        $reenvio = !empty($ctx['reenvio']) || (empty($ctx['manual']) && (bool) at_rg_reales($d));
        $i = at_rg_intento(wp_generate_uuid4(), $prueba ? 'prueba' : 'real', !empty($ctx['manual']) ? 'panel' : 'reloj', $reenvio, $canales, $ahora);
        if ($reenvio && !empty($ctx['manual'])) { $i['motivo'] = trim((string) ($ctx['motivo'] ?? '')); }
        $i['destinos'] = $destinos; $i['despacho_wa'] = false;
        $i['nombre'] = preg_split('/\s+/u', trim((string) ($c['nombre'] ?? '')))[0] ?: 'hola';
        $i['proyecto'] = trim((string) ($c['empresa'] ?? '')) ?: 'tu proyecto';
        $d['intentos'][] = $i;
        if (at_rg_cas($f, $d)) { break; }
        if ($n === 3) { return at_rg_error('concurrencia'); }
    }
    if ($canales['correo']) {
        // Rechequeo cercano al efecto; cambios de contacto/bajas posteriores al reclamo bloquean.
        $fresco = at_rg_fila($id); $dc = at_rg_datos($fresco); $cliente = at_rg_cliente_proyecto($f); $contexto = at_rg_contexto($dc, $cliente);
        $permitido = $contexto !== null && at_rg_url_google((string) $contexto['url']) !== '' && at_rg_canales($cliente, $contexto)['correo']
            && ($i['tipo'] !== 'reenvio' || !at_rg_tiene_baja($cliente, $contexto['bajas'] ?? [])) && ($prueba || at_rg_email((string) ($cliente['email'] ?? '')) === $destinos['correo']) && empty($dc['anonimizado_at']) && empty($dc['resena_recibida']) && empty($dc['excluido']) && ($cliente['tipo'] ?? '') === 'cliente';
        $estado = 'fallido'; $detalle = 'Contacto o baja cambió antes del correo.';
        if ($permitido) {
            try { $estado = at_rg_enviar_correo($i, $id) ? 'aceptado' : 'fallido'; $detalle = $estado === 'aceptado' ? 'Aceptado por el servidor de correo; entrega no confirmada.' : 'El servidor de correo no aceptó el mensaje.'; }
            catch (Throwable $e) { $estado = 'desconocido'; $detalle = 'Excepción durante correo; pudo haber salido.'; }
        }
        if (!at_rg_cambiar($id, static function ($d) use ($i, $estado, $detalle) {
            foreach ($d['intentos'] as &$int) { if ($int['id'] === $i['id'] && $int['correo']['estado'] === 'en_curso') { $int['correo']['estado'] = $estado; $int['correo']['detalle'] = $detalle; $int['correo']['at'] = at_rg_ahora()->format(DATE_ATOM); } } unset($int); return $d;
        })) { return at_rg_error('correo_sin_confirmar', 503); }
    }
    if ($canales['whatsapp']) { at_rg_disparar($id, $i); }
    at_rg_observar($id);
    // Solo referencias y booleano de canal; el contacto se canjea por autorizar-whatsapp de un uso.
    return ['ok' => true, 'proyecto_id' => $id, 'plan_id' => $f->plan_id ? (int) $f->plan_id : null, 'intento_id' => $i['id'], 'modo' => $i['modo'], 'whatsapp' => $canales['whatsapp']];
}
/** El webhook recibe solo referencias. Debe canjearlas por una autorización de despacho de un uso. */
function at_rg_disparar(int $id, array $i): void {
    $url = AT_N8N_RESENA_WHATSAPP;
    if (!str_starts_with($url, 'https://') || !defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
        at_rg_anotar_resultado($id, $i['id'], $i['modo'], 'fallido', '', 'Webhook o autenticación sin configurar.'); return;
    }
    try {
        $r = wp_safe_remote_post($url, ['timeout' => 15, 'redirection' => 0, 'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
            'body' => wp_json_encode(['proyecto_id' => $id, 'intento_id' => $i['id'], 'modo' => $i['modo']])]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) < 200 || wp_remote_retrieve_response_code($r) >= 300) {
            at_rg_anotar_resultado($id, $i['id'], $i['modo'], 'desconocido', '', 'Webhook sin confirmación; no se repite.');
        }
    } catch (Throwable $e) { at_rg_anotar_resultado($id, $i['id'], $i['modo'], 'desconocido', '', 'Excepción de webhook; revisar ejecución.'); }
}
function at_rg_autorizar_wa(int $id, string $intento, string $modo) {
    for ($n = 0; $n < 4; $n++) {
        $f = at_rg_fila($id); if (!$f) { return at_rg_error('proyecto', 404); }
        if (at_rg_plan_reasignado($f)) { return at_rg_error('plan_reasignado'); }
        $d = at_rg_datos($f); $c = at_rg_cliente_proyecto($f); $ctx = at_rg_contexto($d, $c);
        if ($ctx === null) { return at_rg_error('consulta_bajas', 503); }
        if (!at_rg_url_google((string) $ctx['url'])) { return at_rg_error('enlace'); }
        foreach ($d['intentos'] as &$i) {
            if ($i['id'] !== $intento || $i['modo'] !== $modo) { continue; }
            if (!empty($i['despacho_wa']) || $i['whatsapp']['estado'] !== 'en_curso') { return at_rg_error('despacho_usado'); }
            if ((at_rg_ahora()->getTimestamp() - (new DateTimeImmutable($i['creado_at']))->getTimestamp()) >= 1800) { return at_rg_error('despacho_vencido'); }
            if (($c['tipo'] ?? '') !== 'cliente' || !empty($d['anonimizado_at']) || !empty($d['resena_recibida']) || !empty($d['excluido']) || !at_rg_canales($c, $ctx)['whatsapp']
                || ($i['tipo'] === 'reenvio' && at_rg_tiene_baja($c, $ctx['bajas'] ?? []))
                || ($modo === 'real' && at_rg_telefono((string) $c['telefono']) !== $i['destinos']['whatsapp'])) { return at_rg_error('whatsapp_no_permitido'); }
            if ($modo === 'prueba' && $i['destinos']['whatsapp'] !== at_rg_telefono((string) get_option('at_resena_prueba_telefono', ''))) { return at_rg_error('destinos_prueba'); }
            $i['despacho_wa'] = true;
            $salida = ['ok' => true, 'proyecto_id' => $id, 'intento_id' => $intento, 'modo' => $modo, 'telefono' => $i['destinos']['whatsapp'], 'nombre' => $i['nombre'], 'proyecto' => $i['proyecto'], 'plantilla' => 'at_resena_google_cierre', 'idioma' => 'es'];
            unset($i);
            if (at_rg_cas($f, $d)) { return $salida; }
            continue 2;
        }
        unset($i); return at_rg_error('intento');
    }
    return at_rg_error('concurrencia');
}
function at_rg_anotar_resultado(int $id, string $intento, string $modo, string $estado, string $wamid, string $detalle): bool {
    $ok = at_rg_cambiar($id, static function ($d) use ($id, $intento, $modo, $estado, $wamid, $detalle) {
        $nuevo = at_rg_resultado($d, $intento, $modo, $estado, $wamid, $detalle);
        // Reserva única antes de cualquier CAS. Un conflicto nunca alcanza el JSON.
        // Si el CAS agota reintentos, la reserva permanece: no se reasigna a otro envío.
        return is_array($nuevo) && ($wamid === '' || at_rg_indexar_wamids($id, $nuevo)) ? $nuevo : null;
    });
    if ($ok && $wamid !== '') {
        $clave = at_cc_clave_fallo_pendiente($wamid);
        $pendiente = get_transient($clave);
        if (is_array($pendiente)) {
            $detalle_fallo = !empty($pendiente['codigo']) ? 'Meta: código ' . (int) $pendiente['codigo'] : 'Fallo de Meta anterior al registro.';
            if (!at_rg_cambiar($id, fn($d) => at_rg_resultado($d, $intento, $modo, 'fallido', $wamid, $detalle_fallo))) { return false; }
            delete_transient($clave);
        }
    }
    if ($ok) { at_rg_observar($id); }
    return $ok;
}
/** Retorno null si el wamid no pertenece a una reseña registrada. Sin escrituras por ajenos. */
function at_rg_whatsapp_estado(string $wamid, string $estado, int $codigo = 0): ?bool {
    $mapa = ['sent' => 'aceptado', 'delivered' => 'entregado', 'read' => 'leido', 'failed' => 'fallido'];
    if (!isset($mapa[$estado]) || !at_cc_wamid_valido($wamid)) { return null; }
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare('SELECT resena_id,intento_id,modo FROM ' . at_rg_tabla_wamids() . ' WHERE wamid=%s', $wamid));
    if (!$r) { return null; }
    return at_rg_anotar_resultado((int) $r->resena_id, $r->intento_id, $r->modo, $mapa[$estado], $wamid, $codigo ? 'Meta: código ' . $codigo : 'Estado confirmado por Meta.');
}
/** Reserva duradera antes de escribir historial/avisar: una caída se revisa, nunca repite el proveedor. */
function at_rg_reservar_evento(int $id, string $clave): bool {
    for ($n = 0; $n < 4; $n++) {
        $f = at_rg_fila($id); if (!$f) { return false; } $d = at_rg_datos($f);
        if (isset($d['historial_escrito'][$clave])) { return false; }
        $d['historial_escrito'][$clave] = 'en_curso'; if (at_rg_cas($f, $d)) { return true; }
    }
    return false;
}
function at_rg_observar(int $id): void {
    $f = at_rg_fila($id); if (!$f) { return; }
    $d = at_rg_datos($f); if (!empty($d['anonimizado_at'])) { return; } $fallidos = at_rg_fallidos_totales($d); $tope = count($fallidos) >= 3;
    if ($tope) {
        at_rg_avisar($id, $fallidos[2], 'aviso:limite_fallidos', 'Bloqueado: 3 intentos reales totalmente fallidos. Revisar canales e historial con Luis; no se permiten nuevos envíos reales.');
    }
    foreach ($d['intentos'] as $i) {
        foreach (['correo','whatsapp'] as $canal) {
            $estado = $i[$canal]['estado'];
            if (in_array($estado, ['en_curso','no_intentado'], true)) { continue; }
            $evento = 'resena:' . $i['id'] . ':' . $canal . ':' . $estado;
            if (!at_rg_reservar_evento($id, $evento)) { continue; }
            $ok = true;
            if ($i['modo'] === 'real') {
                $ok = at_rg_insertar_historial($f, $i, $evento, $canal, $estado);
            }
            at_rg_cambiar($id, static function ($d) use ($evento, $ok) { $d['historial_escrito'][$evento] = $ok ? true : 'fallido'; return $d; });
            if (!$ok || in_array($estado, ['fallido','desconocido'], true)) {
                // El aviso único de tope cubre estos fallos, sin otro correo por canal.
                if ($tope && at_rg_totalmente_fallido($i)) { continue; }
                at_rg_avisar($id, $i, 'aviso:' . $evento, !$ok ? 'No se pudo escribir el historial; revisar la marca reservada.' : $canal . ': ' . $estado . '. ' . $i[$canal]['detalle']);
            }
        }
    }
}
function at_rg_avisar(int $id, array $i, string $clave, string $mensaje): void {
    if (!at_rg_reservar_evento($id, $clave)) { return; }
    $correo = $i['modo'] === 'prueba' ? (string) get_option('at_resena_prueba_email', '') : at_cc_correo_avisos();
    $reintentable = at_rg_totalmente_fallido($i) && count(at_rg_fallidos_totales(at_rg_datos(at_rg_fila($id)))) < 3;
    $instruccion = $reintentable ? ' Puede volver a intentarse si sigue siendo elegible; tope de 3 intentos reales totalmente fallidos.' : ' No reintentar automáticamente.';
    try { $ok = at_rg_email($correo) !== '' && at_rg_mail_cerrado($correo, 'Reseña · requiere revisión', 'Proyecto #' . $id . ' · ' . esc_html($mensaje) . $instruccion); } catch (Throwable $e) { $ok = false; }
    at_rg_cambiar($id, static function ($d) use ($clave, $ok) { $d['historial_escrito'][$clave] = $ok ? true : 'fallido'; return $d; });
}
function at_rg_mantener(?DateTimeImmutable $ahora = null): bool {
    $ahora ??= at_rg_ahora();
    if ((string) get_option('at_resena_schema') !== AT_RG_SCHEMA) { return false; }
    global $wpdb;
    // La relación acaba al cerrar la ficha CRM; terminar un proyecto no la termina.
    // Lectura fallida o ficha vigente: no se anonimiza. Se incluyen registros ya cerrados en abierto.
    $cerrados = $wpdb->get_results("SELECT r.* FROM " . at_rg_tabla() . " r INNER JOIN {$wpdb->prefix}crm_clientes c ON c.id=r.crm_id
        WHERE c.tipo='cerrado' AND JSON_VALID(r.datos)=1
        AND JSON_CONTAINS_PATH(CASE WHEN JSON_VALID(r.datos) THEN r.datos ELSE '{}' END, 'one', '$.anonimizado_at')=0");
    if ($wpdb->last_error !== '' || !is_array($cerrados)) { return false; }
    foreach ($cerrados as $f) {
        if (!at_rg_cambiar((int) $f->id, fn($d) => at_rg_anonimizar($d, $ahora, (at_rg_cliente((int) $f->crm_id)['tipo'] ?? '') !== 'cerrado'))) { return false; }
    }
    // Repetible también tras un fallo de DELETE: no deja wamids personales fuera del JSON.
    $anonimos = $wpdb->get_results("SELECT * FROM " . at_rg_tabla() . " WHERE JSON_VALID(datos)=1
        AND JSON_CONTAINS_PATH(CASE WHEN JSON_VALID(datos) THEN datos ELSE '{}' END, 'one', '$.anonimizado_at')=1");
    if ($wpdb->last_error !== '' || !is_array($anonimos)) { return false; }
    foreach ($anonimos as $f) {
        try { if (empty(at_rg_datos($f)['anonimizado_at'])) { continue; } } catch (Throwable $e) { continue; }
        if ($wpdb->query($wpdb->prepare('DELETE FROM ' . at_rg_tabla_wamids() . ' WHERE resena_id=%d', $f->id)) === false) { return false; }
    }
    if ($wpdb->query("UPDATE " . at_rg_tabla_bajas() . " SET nota='' WHERE nota<>''") === false) { return false; }
    $rows = $wpdb->get_results('SELECT * FROM ' . at_rg_tabla() . ' WHERE abierto=1');
    if ($wpdb->last_error !== '') { return false; }
    foreach ((array) $rows as $f) {
        try { at_rg_cambiar((int) $f->id, fn($d) => at_rg_vencer($d, $ahora)); at_rg_observar((int) $f->id); }
        catch (Throwable $e) { at_rg_avisar_ilegible((int) $f->id); }
    }
    return true;
}
function at_rg_pendientes() {
    if (!at_rg_url_google((string) get_option('at_resena_google_url', ''))) { return at_rg_error('enlace'); }
    if (!at_rg_mantener()) { return at_rg_error('base', 503); }
    // No LIMIT previo a la elegibilidad: los bloqueados no tapan la cola.
    $rows = at_rg_filas_pendientes(at_rg_ahora()->modify('-7 days')->format('Y-m-d'));
    if ($rows === null) { return at_rg_error('base', 503); }
    $out = [];
    foreach ($rows as $f) {
        try { $d = at_rg_datos($f); } catch (Throwable $e) { continue; }
        $c = at_rg_cliente_proyecto($f); $ctx = at_rg_contexto($d, $c);
        if ($ctx === null) { return at_rg_error('consulta_bajas', 503); }
        if (at_rg_elegibilidad($d, $c, $ctx, at_rg_ahora()) === '') { $out[] = ['proyecto_id' => (int) $f->id, 'plan_id' => $f->plan_id ? (int) $f->plan_id : null, 'fecha' => $d['termino']['fecha']]; }
        if (count($out) >= 10) { break; }
    }
    update_option('at_resena_ultima_corrida', time(), false);
    return ['pendientes' => $out];
}
function at_rg_auth(WP_REST_Request $r) {
    // Mantener el patrón, garantizando 401 sin clave aun si el servidor no fue configurado.
    if ((string) $r->get_header('x_at_secret') === '') { return at_rg_error('no_autorizado', 401); }
    return at_cc_rest_auth($r);
}
function at_rg_id_request(WP_REST_Request $r): int {
    $ruta = $r->get_url_params();
    if (isset($ruta['proyecto'])) { return absint($ruta['proyecto']); }
    $plan = absint($ruta['plan'] ?? 0); $p = at_pt_plan($plan);
    if (!$p) { return 0; }
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . at_rg_tabla() . ' WHERE plan_id=%d AND crm_id=%d', $plan, (int) $p->crm_cliente_id));
}
function at_rg_rest_reclamar(WP_REST_Request $r) { return at_rg_reclamar(at_rg_id_request($r)); }
function at_rg_rest_autorizar(WP_REST_Request $r) {
    $id = $r->get_param('intento_id'); $modo = $r->get_param('modo');
    return is_string($id) && is_string($modo) ? at_rg_autorizar_wa(at_rg_id_request($r), $id, $modo) : at_rg_error('datos', 400);
}
function at_rg_rest_resultado(WP_REST_Request $r) {
    $id = $r->get_param('intento_id'); $modo = $r->get_param('modo'); $estado = $r->get_param('estado'); $wamid = $r->get_param('wamid') ?? ''; $detalle = $r->get_param('detalle') ?? '';
    if (!is_string($id) || !is_string($modo) || !is_string($estado) || !is_string($wamid) || !is_string($detalle) || strlen($id) > 36 || strlen($detalle) > 500
        || ($wamid !== '' && !at_cc_wamid_valido($wamid))) { return at_rg_error('datos', 400); }
    // Resultados del proveedor inicial: delivered/read/failed también llegan a la ruta de estados existente.
    if (!in_array($estado, ['aceptado','fallido','desconocido'], true)) { return at_rg_error('estado', 400); }
    return at_rg_anotar_resultado(at_rg_id_request($r), $id, $modo, $estado, $wamid, sanitize_text_field($detalle)) ? ['ok' => true] : at_rg_error('intento');
}
/** Lo que lee n8n para armar el correo de resumen de envíos. */
function at_rg_rest_resumen(WP_REST_Request $r) {
    $desde = $r->get_param('desde');
    $d = is_string($desde) ? at_rg_resumen_desde($desde, at_rg_ahora()) : null;
    if ($d === null) { return at_rg_error('datos', 400); }
    $filas = at_rg_todas_filas();
    if ($filas === null) { return at_rg_error('base', 503); }
    $proyectos = [];
    foreach ($filas as $f) {
        try { $datos = at_rg_datos($f); } catch (Throwable $e) { continue; }
        $cliente = at_rg_cliente_proyecto($f);
        $ctx = at_rg_contexto($datos, $cliente);
        if ($ctx === null) { return at_rg_error('base', 503); }
        $proyectos[] = ['id' => (int) $f->id, 'cliente' => $cliente, 'ctx' => $ctx, 'datos' => $datos];
    }
    $res = at_rg_resumen($proyectos, $d);
    return ['desde' => $d->format(DATE_ATOM), 'aviso_a' => at_cc_correo_avisos(), 'total' => $res['total'], 'ok' => $res['ok'], 'filas' => $res['filas']];
}
/** Avisa a n8n que mande el resumen; sin esperar respuesta y sin lanzar nunca. */
function at_rg_pedir_resumen(string $desde, string $origen): void {
    if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') { return; }
    try {
        wp_safe_remote_post(AT_N8N_RESENA_RESUMEN, ['timeout' => 3, 'blocking' => false, 'redirection' => 0,
            'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
            'body' => wp_json_encode(['desde' => $desde, 'origen' => $origen])]);
    } catch (Throwable $e) { /* el resumen es un aviso; nunca debe romper el envío */ }
}
function at_rg_rest_baja(WP_REST_Request $r) {
    $tel = $r->get_param('telefono'); $texto = $r->get_param('texto');
    if (!is_string($tel) || !at_rg_telefono($tel) || !is_string($texto)) { return at_rg_error('datos', 400); }
    return at_cc_resena_respuesta($tel, $texto);
}
add_action('rest_api_init', static function (): void {
    register_rest_route('at/v1', '/resena/pendientes', ['methods' => 'GET', 'callback' => 'at_rg_pendientes', 'permission_callback' => 'at_rg_auth']);
    register_rest_route('at/v1', '/resena/resumen', ['methods' => 'GET', 'callback' => 'at_rg_rest_resumen', 'permission_callback' => 'at_rg_auth']);
    register_rest_route('at/v1', '/resena/baja', ['methods' => 'POST', 'callback' => 'at_rg_rest_baja', 'permission_callback' => 'at_rg_auth']);
    foreach (['/resena/(?P<plan>[1-9][0-9]*)', '/resena/proyecto/(?P<proyecto>[1-9][0-9]*)'] as $base) {
        foreach (['reclamar' => 'at_rg_rest_reclamar', 'resultado' => 'at_rg_rest_resultado', 'autorizar-whatsapp' => 'at_rg_rest_autorizar'] as $ruta => $callback) {
            register_rest_route('at/v1', $base . '/' . $ruta, ['methods' => 'POST', 'callback' => $callback, 'permission_callback' => 'at_rg_auth']);
        }
    }
});
/** Mantenimiento sin enviar al cliente; WP-Cron también detecta silencio del Schedule. */
add_action('init', static function (): void {
    if (at_rg_url_google((string) get_option('at_resena_google_url', '')) && !get_option('at_resena_monitor_desde')) { update_option('at_resena_monitor_desde', time(), false); }
    if (!wp_next_scheduled('at_rg_vigilar')) { wp_schedule_event(time() + 3600, 'hourly', 'at_rg_vigilar'); }
});
add_action('at_rg_vigilar', static function (): void {
    at_rg_mantener(); $ultima = (int) get_option('at_resena_ultima_corrida', 0) ?: (int) get_option('at_resena_monitor_desde', 0);
    if (at_rg_url_google((string) get_option('at_resena_google_url', '')) && $ultima && time() - $ultima > 26 * HOUR_IN_SECONDS) {
        $clave = 'at_resena_alerta_' . $ultima;
        if (add_option($clave, 'reservado', '', false)) { try { at_rg_mail_cerrado(at_cc_correo_avisos(), 'Reseña · reloj sin corrida', 'Más de 26 horas sin consulta exitosa de pendientes. Revisar n8n; no reenviar intentos.'); } catch (Throwable $e) { /* Reserva visible: revisar el correo operativo. */ } }
    }
});
function at_rg_avisar_ilegible(int $id): void {
    if (!add_option('at_resena_ilegible_' . $id, 'revisar', '', false)) { return; }
    try { at_rg_mail_cerrado(at_cc_correo_avisos(), 'Reseña · registro bloqueado', 'Proyecto #' . $id . ': registro ilegible. Revisar la base; no borrar marcas ni reenviar.'); } catch (Throwable $e) { /* Reserva persistente para revisión. */ }
}
/** wpdb puede reconectar y repetir una consulta cuyo INSERT sí llegó a ejecutarse.
 * La reserva serializa solicitudes y CONNECTION_ID impide repetir ese INSERT en otra conexión.
 * Una confirmación perdida deja la nota pendiente de revisión, nunca vuelve a enviar al cliente.
 */
function at_rg_insertar_historial(object $f, array $i, string $evento, string $canal, string $estado): bool {
    global $wpdb;
    $conexion = (int) $wpdb->get_var('SELECT CONNECTION_ID()');
    if ($conexion < 1) { return false; }
    return $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}crm_historial (cliente_id,tipo_evento,titulo,descripcion,metadata,usuario_id,created_at)
        SELECT %d,%s,%s,%s,%s,%d,%s WHERE CONNECTION_ID()=%d",
        (int) $f->crm_id, 'aviso_operativo', 'Reseña · ' . $canal . ': ' . $estado,
        $evento . ' · ' . $i['tipo'] . '. ' . $i[$canal]['detalle'], wp_json_encode(['resena_clave' => $evento]),
        get_current_user_id(), current_time('mysql'), $conexion)) === 1;
}
