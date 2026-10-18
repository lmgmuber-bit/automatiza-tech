<?php
/** Reloj local y entrega de Retomar. El tick nunca llama a Meta. */
if (!defined('ABSPATH')) { exit; }

function at_rt_historia(array $s, string $tipo, string $titulo): void {
    if (!empty($s['crm_cliente_id']) && function_exists('at_cc_historial_crm')) {
        at_cc_historial_crm((int) $s['crm_cliente_id'], $tipo, $titulo, 'Retomar #' . $s['id']);
    }
}
add_action('at_rt_baja_confirmada', static function (int $id): void {
    $s = at_rt_obtener($id);
    if ($s) { at_rt_historia($s, 'retomar_baja', '🔁 No le interesa'); }
});

function at_rt_siguiente_habil(string $hoy): string {
    $d = at_rt_fecha($hoy)->modify('+1 day');
    while ((int) $d->format('N') > 5) { $d = $d->modify('+1 day'); }
    return $d->format('Y-m-d');
}

function at_rt_detener(array $s, string $motivo, string $ahora): void {
    at_rt_guardar($s['id'], ['estado' => at_rt_estado_por_motivo($motivo), 'proximo_at' => null]);
    at_rt_agregar_envio($s['id'], ['tipo' => 'detenido', 'fecha' => substr($ahora, 0, 10), 'at' => $ahora, 'motivo' => $motivo]);
    if ($motivo === 'agendo') { at_rt_historia($s, 'retomar_agendo', '🔁 Agendó reunión'); }
}

/** Prueba manda sólo al equipo. El reloj inyecta su fecha; las llamadas manuales usan Santiago. */
function at_rt_enviar(int $id, bool $prueba): array {
    return at_rt_entregar($id, $prueba, (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s'));
}
function at_rt_entregar(int $id, bool $prueba, string $ahora): array {
    global $wpdb;
    $r = ['ok' => false, 'motivo' => 'no_disponible', 'whatsapp' => null];
    $s = at_rt_obtener($id);
    if (!$s) { return $r; }
    $hoy = substr($ahora, 0, 10);
    $claim = false;
    if (!$prueba) {
        if (!at_rt_es_habil($hoy) || !at_rt_en_ventana(substr($ahora, 11, 5))) { $r['motivo'] = 'fuera_ventana'; return $r; }
        $limite = (new DateTimeImmutable($ahora, new DateTimeZone('America/Santiago')))->modify('-10 minutes')->format('Y-m-d H:i:s');
        $claim = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET enviando=%s WHERE id=%d AND estado='activo' AND proximo_at<=%s AND (enviando IS NULL OR enviando<%s)", $ahora, $id, $hoy, $limite)) === 1;
        if (!$claim) { $r['motivo'] = 'ocupado_o_inactivo'; return $r; }
    }
    try {
        $s = at_rt_obtener($id);
        if (!$s) { return $r; }
        $motivo = at_rt_motivo_parada(at_rt_contexto_parada($s));
        if ($motivo !== '') {
            if (!$prueba) { at_rt_detener($s, $motivo, $ahora); }
            $r['motivo'] = $motivo; return $r;
        }
        if (!$prueba) {
            if ($s['estado'] !== 'activo') { return $r; }
            foreach ($s['envios'] as $e) {
                if (($e['tipo'] ?? '') === 'envio' && ($e['fecha'] ?? '') === $hoy) { $r['motivo'] = 'ya_intentado'; return $r; }
            }
        }
        if (($s['render']['estado'] ?? '') !== 'listo' || !at_rt_url_render_valida((string) ($s['render']['view_url'] ?? ''), $s['token'])) {
            if (!$prueba) {
                at_rt_guardar($id, ['proximo_at' => at_rt_siguiente_habil($hoy)]);
                at_rt_agregar_envio($id, ['tipo' => 'diferido', 'fecha' => $hoy, 'at' => $ahora, 'nota' => 'presentacion_pendiente']);
            }
            $r['motivo'] = 'diferido'; return $r;
        }
        $url = set_url_scheme(home_url('/retomar.php?t=' . $s['token']), 'https');
        $d = ['nombre' => $s['nombre'], 'negocio' => $s['negocio'], 'quedamos' => (string) ($s['textos']['quedamos'] ?? ''), 'url_ver' => $url, 'url_agendar' => $url . '&agendar=1', 'url_baja' => $url . '&baja=1'];
        // Revisar los campos que salen, nunca render.view_url (iframe autorizado).
        if (at_rt_internas($d) || stripos(wp_json_encode($d), 'easypanel') !== false || !at_rt_url_correo($url)) { $r['motivo'] = 'interno'; return $r; }
        $n = at_rt_envios_realizados($s['envios']) + 1;
        $destino = $prueba ? at_cc_correo_avisos() : $s['email'];
        if (!is_email($destino)) { $r['motivo'] = 'correo_invalido'; return $r; }
        $html = at_rt_correo_html($d); $texto = at_rt_correo_texto($d);
        $alternativa = static function ($mail) use ($texto): void { $mail->AltBody = $texto; };
        add_action('phpmailer_init', $alternativa);
        try { $ok = wp_mail($destino, ($prueba ? '[Prueba] ' : '') . at_rt_asunto($n, $s['nombre']), $html, array_merge(['Content-Type: text/html; charset=UTF-8'], at_rt_cabeceras_baja($d['url_baja']))); }
        catch (Throwable $e) { $ok = false; }
        finally { remove_action('phpmailer_init', $alternativa); }
        $r['ok'] = (bool) $ok; $r['motivo'] = $ok ? 'enviado' : 'correo_error';
        if ($prueba) { return $r; }
        $wa = $ok && in_array('whatsapp', at_rt_canales($n, $s['max_envios'], $s['canales'], (bool) $s['wa_autorizado'], at_pt_telefono_wa($s['telefono'])), true);
        $evento = ['tipo' => 'envio', 'n' => $n, 'fecha' => $hoy, 'at' => $ahora, 'correo' => ['estado' => $ok ? 'enviado' : 'error'], 'whatsapp' => $wa ? ['estado' => 'pendiente'] : null];
        if (!at_rt_agregar_envio($id, $evento)) {
            at_rt_guardar($id, ['estado' => 'pausado', 'proximo_at' => null]);
            return ['ok' => false, 'motivo' => 'registro_error', 'whatsapp' => null];
        }
        at_rt_guardar($id, ['estado' => !$ok ? 'pausado' : ($n >= $s['max_envios'] ? 'agotado' : 'activo'), 'proximo_at' => !$ok || $n >= $s['max_envios'] ? null : at_rt_proximo($hoy, $s['intervalo_dias'])]);
        if ($ok) { at_rt_historia($s, 'retomar_correo', '🔁 Recordatorio enviado'); }
        if ($wa) { $r['whatsapp'] = ['id' => $id, 'n' => $n, 'telefono' => at_pt_telefono_wa($s['telefono']), 'nombre' => $s['nombre'], 'negocio' => $s['negocio'], 'token' => $s['token']]; }
        return $r;
    } finally {
        if ($claim) { $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET enviando=NULL WHERE id=%d AND enviando=%s", $id, $ahora)); }
    }
}

function at_rt_tick(string $ahora): array {
    global $wpdb;
    $r = ['enviados' => 0, 'diferidos' => 0, 'detenidos' => 0, 'whatsapp' => []];
    try { $d = new DateTimeImmutable($ahora, new DateTimeZone('America/Santiago')); } catch (Exception $e) { return $r; }
    $d = $d->setTimezone(new DateTimeZone('America/Santiago'));
    if (!at_rt_es_habil($d->format('Y-m-d')) || !at_rt_en_ventana($d->format('H:i'))) { return $r; }
    $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado='activo' AND proximo_at<=%s ORDER BY id", $d->format('Y-m-d')));
    foreach ($ids as $id) {
        $e = at_rt_entregar((int) $id, false, $d->format('Y-m-d H:i:s'));
        if ($e['ok']) { $r['enviados']++; }
        elseif ($e['motivo'] === 'diferido') { $r['diferidos']++; }
        elseif (at_rt_estado_por_motivo($e['motivo']) !== '') { $r['detenidos']++; }
        if ($e['whatsapp']) { $r['whatsapp'][] = $e['whatsapp']; }
    }
    return $r;
}

/** CAS del JSON completo: un callback jamás pisa eventos agregados concurrentemente. */
function at_rt_whatsapp_resultado(array $d): array {
    global $wpdb;
    $id = (int) ($d['id'] ?? 0); $n = (int) ($d['n'] ?? 0);
    for ($intento = 0; $intento < 3; $intento++) {
        $raw = $wpdb->get_var($wpdb->prepare("SELECT envios FROM {$wpdb->prefix}at_retomar WHERE id=%d", $id));
        $eventos = json_decode((string) $raw, true);
        if (!is_array($eventos)) { return ['ok' => false, 'motivo' => 'no_pendiente']; }
        $encontrado = false;
        foreach ($eventos as &$e) {
            if (($e['tipo'] ?? '') !== 'envio' || (int) ($e['n'] ?? 0) !== $n) { continue; }
            $estado = $e['whatsapp']['estado'] ?? '';
            if ($estado !== 'pendiente') { return ['ok' => in_array($estado, ['enviado', 'error'], true), 'motivo' => $estado === '' ? 'no_pendiente' : 'sin_cambio']; }
            $encontrado = true;
            $error = !empty($d['codigo']) || empty($d['wamid']);
            $e['whatsapp'] = ['estado' => $error ? 'error' : 'enviado'];
            foreach (['wamid', 'codigo', 'titulo'] as $k) {
                if (isset($d[$k]) && is_scalar($d[$k])) { $e['whatsapp'][$k] = mb_substr(sanitize_text_field((string) $d[$k]), 0, 255); }
            }
            break;
        }
        unset($e);
        if (!$encontrado) { return ['ok' => false, 'motivo' => 'no_pendiente']; }
        $cambio = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET envios=%s WHERE id=%d AND BINARY envios=BINARY %s", wp_json_encode($eventos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id, $raw));
        if ($cambio === 1) {
            if (!$error) { at_rt_historia(at_rt_obtener($id), 'retomar_whatsapp', '🔁 Recordatorio por WhatsApp'); }
            return ['ok' => true, 'motivo' => $error ? 'error' : 'enviado'];
        }
    }
    return ['ok' => false, 'motivo' => 'concurrencia'];
}
function at_rt_auth(WP_REST_Request $r): bool {
    return defined('AT_REST_SECRET') && AT_REST_SECRET !== '' && is_string($r->get_header('x-at-secret')) && hash_equals(AT_REST_SECRET, $r->get_header('x-at-secret'));
}
add_action('rest_api_init', static function (): void {
    register_rest_route('automatiza-tech/v1', '/retomar/tick', ['methods' => 'POST', 'permission_callback' => 'at_rt_auth', 'callback' => static fn() => at_rt_tick((new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s'))]);
    register_rest_route('automatiza-tech/v1', '/retomar/whatsapp-resultado', ['methods' => 'POST', 'permission_callback' => 'at_rt_auth', 'callback' => static fn(WP_REST_Request $r) => at_rt_whatsapp_resultado((array) $r->get_json_params())]);
});
