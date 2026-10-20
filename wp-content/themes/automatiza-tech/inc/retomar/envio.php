<?php
/** Reloj local y entrega de Retomar. El tick nunca llama a Meta. */
if (!defined('ABSPATH')) { exit; }

/** Interruptor del WhatsApp automático (opción at_rt_wa_automatico = '1'). Apagado por defecto: mientras Meta no
 *  apruebe la plantilla retomar_propuesta, el reloj solo manda correos y Luis comparte a mano desde el panel. */
function at_rt_wa_automatico(): bool {
    return get_option('at_rt_wa_automatico') === '1';
}
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

/**
 * Una aprobación que ya no vale: vuelve a `por_aprobar` con los enlaces muertos (usada) y sin día programado, para que el
 * correo semanal la emita de nuevo. La usan el vencimiento por contenido, la detención y el correo fallido (Ruling 10).
 */
function at_rt_aprobacion_muerta(array $ap, string $ahora): array {
    return array_merge(array_diff_key($ap, ['programado' => true]), ['estado' => 'por_aprobar', 'usada' => true, 'vencida_at' => $ahora]);
}

/**
 * Detiene el seguimiento por $motivo (baja, agendó, cliente…): estado de cierre, sin próximo envío y el evento
 * {tipo:'detenido'}. Ruling 10: lo aprobado no sobrevive a una detención; si había un recordatorio `aprobado` esperando,
 * su aprobación queda muerta (at_rt_aprobacion_muerta). Todo en una transacción bajo FOR UPDATE; el historial, después.
 */
function at_rt_detener(array $s, string $motivo, string $ahora): void {
    global $wpdb;
    $id = (int) $s['id'];
    if ($wpdb->query('START TRANSACTION') === false) { return; }
    $confirmado = false;
    try {
        $fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
        if (!$fila) { return; }
        $actual = at_rt_fila($fila);
        $envios = $actual['envios'];
        $envios[] = ['tipo' => 'detenido', 'fecha' => substr($ahora, 0, 10), 'at' => $ahora, 'motivo' => $motivo];
        $campos = ['estado' => at_rt_estado_por_motivo($motivo), 'proximo_at' => null, 'envios' => $envios];
        if (($actual['aprobacion']['estado'] ?? '') === 'aprobado') { $campos['aprobacion'] = at_rt_aprobacion_muerta($actual['aprobacion'], $ahora); }
        if (!at_rt_guardar($id, $campos)) { return; }
        $confirmado = $wpdb->query('COMMIT') !== false;
    } finally {
        if (!$confirmado) { $wpdb->query('ROLLBACK'); }
    }
    if ($confirmado && $motivo === 'agendo') { at_rt_historia($s, 'retomar_agendo', '🔁 Agendó reunión'); }
}

/** Datos del correo al prospecto (at_rt_correo_html/texto): enlaces a su página pública por token, en HTTPS. Una sola
 *  fuente para el envío y para «Ver el correo tal como le llega» de la aprobación, así la vista previa no se desfasa. */
function at_rt_datos_correo(array $s): array {
    $url = set_url_scheme(home_url('/retomar.php?t=' . $s['token']), 'https');
    return ['nombre' => (string) $s['nombre'], 'negocio' => (string) $s['negocio'], 'quedamos' => (string) ($s['textos']['quedamos'] ?? ''), 'url_ver' => $url, 'url_agendar' => $url . '&agendar=1', 'url_baja' => $url . '&baja=1'];
}

/**
 * ¿Luis aprobó un recordatorio que ya puede salir? Solo `aprobado` con su día programado (Y-m-d legible) hoy o antes.
 * Por aprobar, con cambios pedidos, omitido, ya enviado o sin emitir: no. Lo aprobado se coteja con lo que saldría
 * en at_rt_aprobacion_calza(), justo antes del correo.
 */
function at_rt_aprobacion_para(array $s, string $hoy): bool {
    $ap = is_array($s['aprobacion'] ?? null) ? $s['aprobacion'] : [];
    $programado = is_string($ap['programado'] ?? null) ? $ap['programado'] : '';
    return ($ap['estado'] ?? '') === 'aprobado' && at_rt_fecha($programado) !== null && $programado <= $hoy;
}

/**
 * ¿El contenido de la aprobación es exactamente el que saldría como recordatorio $n? Misma huella en la aprobación, en el
 * render `listo` y en el contenido actual (un texto guardado sin rehacer la presentación también cambia la huella),
 * redacción `lista` y el mismo número de recordatorio. Si algo no calza, el contenido cambió después de emitir o aprobar.
 * Es el núcleo que comparten el envío (at_rt_aprobacion_calza) y los botones del correo (at_rt_aprobacion_motivo), así la
 * página nunca dice «Aprobado» de algo que el reloj cancelaría.
 */
function at_rt_aprobacion_calza_contenido(array $s, int $n): bool {
    $ap = is_array($s['aprobacion'] ?? null) ? $s['aprobacion'] : [];
    $render = is_array($s['render'] ?? null) ? $s['render'] : [];
    $aprobada = is_string($ap['huella'] ?? null) ? $ap['huella'] : '';
    $vigente = is_string($render['huella'] ?? null) ? $render['huella'] : '';
    if ($aprobada === '' || $vigente === '' || ($render['estado'] ?? '') !== 'listo' || ($s['redaccion']['estado'] ?? '') !== 'lista' || (int) ($ap['n'] ?? 0) !== $n || !hash_equals($vigente, $aprobada)) { return false; }
    try { $actual = at_rt_huella_render($s); }
    catch (InvalidArgumentException $e) { return false; }
    return hash_equals($aprobada, $actual);
}

/**
 * ¿Lo aprobado es exactamente lo que saldría como recordatorio $n, y a quien Luis aprobó? El contenido
 * (at_rt_aprobacion_calza_contenido) y además la foto del destinatario (aprobacion.destino, Ruling 18) con el mismo correo
 * y el mismo teléfono que hoy. Un correo o un teléfono distinto vence la aprobación como un cambio de contenido, pero sin
 * rehacer la presentación (su huella no cambia). Sin foto no calza: nunca sale algo sin saber a quién se aprobó.
 */
function at_rt_aprobacion_calza(array $s, int $n): bool {
    if (!at_rt_aprobacion_calza_contenido($s, $n)) { return false; }
    $foto = $s['aprobacion']['destino'] ?? null;
    if (!is_array($foto) || !is_string($foto['email'] ?? null) || !is_string($foto['telefono_wa'] ?? null)) { return false; }
    $actual = at_rt_aprobacion_destino($s);
    return hash_equals($foto['email'], $actual['email']) && hash_equals($foto['telefono_wa'], $actual['telefono_wa']);
}

/**
 * El contenido cambió después de aprobar: la aprobación vuelve a `por_aprobar` con los enlaces muertos (usada) para que
 * el correo semanal la emita de nuevo, y queda anotado {tipo:'diferido', nota:'aprobacion_vencida'}. Bajo FOR UPDATE y
 * solo si sigue siendo la misma aprobación que se revisó ($ap): nunca pisa una decisión tomada entretanto.
 * Si además el contenido actual ya no es el de la presentación (cambió algo fuera de las rutas de guardado de Retomar:
 * la propuesta vinculada, su flujo o sus fotos), el render queda desactualizado bajo el mismo candado —`pendiente`, o
 * `error` si el contenido ya no se puede presentar (at_rt_render_desfasado, el mismo criterio que la emisión)—: la
 * emisión exige `listo`, así nadie vuelve a aprobar una presentación vieja que vencería otra vez en cada reloj.
 * Devuelve '' si no venció nada, 'aprobacion' si solo venció la aprobación, 'render' si hay que pedir otra presentación
 * (lo hace quien llama, fuera del candado) y 'error' si el contenido necesita revisión en el panel.
 */
function at_rt_vencer_aprobacion(int $id, array $ap, string $ahora): string {
    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false) { return ''; }
    try {
        $fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
        if (!$fila) { return ''; }
        $s = at_rt_fila($fila);
        if ($s['aprobacion'] !== $ap || ($ap['estado'] ?? '') !== 'aprobado') { return ''; }
        $envios = $s['envios'];
        $envios[] = ['tipo' => 'diferido', 'fecha' => substr($ahora, 0, 10), 'at' => $ahora, 'nota' => 'aprobacion_vencida'];
        $campos = ['aprobacion' => at_rt_aprobacion_muerta($ap, $ahora), 'envios' => $envios];
        $resultado = 'aprobacion';
        $desfasado = at_rt_render_desfasado($s, 'El contenido cambió después de la presentación: hay que rehacerla antes de volver a aprobar.', 'El contenido cambió después de aprobar y la presentación ya no se puede armar:');
        if ($desfasado !== null) {
            $campos['render'] = $desfasado;
            $resultado = $desfasado['estado'] === 'error' ? 'error' : 'render';
        }
        if (!at_rt_guardar($id, $campos)) { return ''; }
        return $wpdb->query('COMMIT') !== false ? $resultado : '';
    } finally {
        $wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno o error.
    }
}

/**
 * Tras un recordatorio aprobado que salió: si la propuesta vinculada es v3 y seguía `lista` (terminada y nunca enviada),
 * pasa a `sent`, porque el recordatorio le llevó la presentación con su enlace. Ningún otro estado se toca: el UPDATE
 * exige `lista` y v3 en la misma sentencia. Deja una nota interna en Seguimiento (at_cc_anotar_simple, si existe).
 */
function at_rt_marcar_propuesta_enviada(array $s, string $ahora): void {
    global $wpdb;
    $pid = (int) ($s['propuesta_id'] ?? 0);
    if ($pid <= 0) { return; }
    $tabla = $wpdb->prefix . 'automatiza_propuestas';
    $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tabla} WHERE id=%d", $pid));
    if (!$p || ($p->status ?? '') !== 'lista' || ($p->flujo ?? null) !== 'v3') { return; }
    if ($wpdb->query($wpdb->prepare("UPDATE {$tabla} SET status='sent' WHERE id=%d AND status='lista' AND flujo='v3'", $pid)) !== 1) { return; }
    if (function_exists('at_cc_anotar_simple')) {
        // aviso_operativo es un tipo interno: la nota nunca llega al portal del cliente.
        at_cc_anotar_simple($p, 'aviso_operativo', 'Enviada con el recordatorio de Retomar', 'El recordatorio aprobado le llevó la presentación con el enlace de la propuesta.', ['retomar_id' => (int) $s['id'], 'at' => $ahora]);
    }
}

/** Prueba manda sólo al equipo. El reloj inyecta su fecha; las llamadas manuales usan Santiago. */
function at_rt_enviar(int $id, bool $prueba): array {
    return at_rt_entregar($id, $prueba, (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s'));
}

/**
 * Persistir la entrega aceptada bajo bloqueo, respetando paradas ocurridas durante SMTP. $aprobacion es la aprobación
 * revisada antes del correo: si salió y la fila aún tiene esa misma aprobación, se consume en esta misma transacción
 * ({estado:'enviado', n, at}); si cambió entretanto no se pisa y tampoco se pide el WhatsApp. El WhatsApp sale solo si
 * estaba en la foto del destinatario de la aprobación Y hoy sigue autorizado Y el interruptor está encendido (Ruling 18).
 * Ruling 17: cuando el correo aprobado sale, render.publicada = {view_url, pdf_url, huella, generacion} del render que se
 * revisó antes del correo ($render_aprobado, el que calzó), y no del que haya ahora: si la presentación se rehízo o la
 * aprobación cambió durante SMTP, el prospecto igual ve la que Luis aprobó y recibió. La página del prospecto (retomar.php)
 * muestra esa versión.
 */
function at_rt_finalizar_entrega(int $id, int $n, bool $ok, string $ahora, array $aprobacion, array $render_aprobado): ?array {
    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false) { return null; }
    $confirmado = false;
    try {
        $raw = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
        if (!$raw) { return null; }
        $s = at_rt_fila($raw);
        $motivo = at_rt_motivo_parada(at_rt_contexto_parada($s));
        $propio = $s['estado'] === 'activo' && $s['enviando'] === $ahora;
        $aprobada = ($aprobacion['estado'] ?? '') === 'aprobado' && $s['aprobacion'] === $aprobacion;
        $wa = $propio && $aprobada && $motivo === '' && $ok && at_rt_wa_automatico() && !empty($aprobacion['destino']['whatsapp']) && in_array('whatsapp', at_rt_canales($n, $s['max_envios'], $s['canales'], (bool) $s['wa_autorizado'], at_pt_telefono_wa($s['telefono'])), true);
        $s['envios'][] = ['tipo' => 'envio', 'n' => $n, 'fecha' => substr($ahora, 0, 10), 'at' => $ahora, 'correo' => ['estado' => $ok ? 'enviado' : 'error'], 'whatsapp' => $wa ? ['estado' => 'pendiente', 'pedidos' => 1, 'pedido_at' => $ahora] : null];
        $campos = ['envios' => $s['envios']];
        // El correo ya salió: la aprobación queda usada aunque una parada (baja, agendó) llegara durante SMTP.
        // Ruling 10: si el correo falló, lo aprobado no sobrevive: el seguimiento queda en pausa y la aprobación muerta.
        if ($aprobada) { $campos['aprobacion'] = $s['aprobacion'] = $ok ? ['estado' => 'enviado', 'n' => $n, 'at' => $ahora] : at_rt_aprobacion_muerta($s['aprobacion'], $ahora); }
        // El correo que salió era el aprobado ($aprobacion se revisó contra él antes de SMTP): se publica aunque la aprobación
        // de la fila haya cambiado durante el envío (p. ej. un Guardar del panel). Si no, la página del prospecto diría «Estamos
        // preparando» y después mostraría una versión que nadie aprobó. El consumo de la aprobación sigue exigiendo $aprobada.
        if ($ok && ($aprobacion['estado'] ?? '') === 'aprobado') {
            $publicada = [];
            foreach (['view_url', 'pdf_url', 'huella', 'generacion'] as $k) { $publicada[$k] = is_string($render_aprobado[$k] ?? null) ? $render_aprobado[$k] : ''; }
            $campos['render'] = $s['render'] = array_merge(is_array($s['render']) ? $s['render'] : [], ['publicada' => $publicada]);
        }
        // La transición sólo pertenece al claim vigente y a una fila aún activa.
        if ($propio) {
            $estado = $motivo !== '' ? at_rt_estado_por_motivo($motivo) : (!$ok ? 'pausado' : ($n >= $s['max_envios'] ? 'agotado' : 'activo'));
            $campos += ['estado' => $estado, 'proximo_at' => $estado === 'activo' ? at_rt_proximo(substr($ahora, 0, 10), $s['intervalo_dias']) : null];
        }
        $d = at_rt_campos($campos);
        $d['actualizado_at'] = current_time('mysql');
        if ($wpdb->update($wpdb->prefix . 'at_retomar', $d, ['id' => $id, 'estado' => $s['estado'], 'enviando' => $s['enviando']]) === false) { return null; }
        $confirmado = $wpdb->query('COMMIT') !== false;
        return $confirmado ? ['seguimiento' => $s, 'whatsapp' => $wa] : null;
    } finally {
        if (!$confirmado) { $wpdb->query('ROLLBACK'); }
    }
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
            // Solo sale lo que Luis aprobó para hoy o antes. Sin eso no se toca nada: el finally libera el claim.
            if (!at_rt_aprobacion_para($s, $hoy)) { $r['motivo'] = 'sin_aprobacion'; return $r; }
        }
        if (($s['render']['estado'] ?? '') !== 'listo' || !at_rt_url_render_valida((string) ($s['render']['view_url'] ?? ''), $s['token'])) {
            if (!$prueba) {
                at_rt_guardar($id, ['proximo_at' => at_rt_siguiente_habil($hoy)]);
                at_rt_agregar_envio($id, ['tipo' => 'diferido', 'fecha' => $hoy, 'at' => $ahora, 'nota' => 'presentacion_pendiente']);
            }
            $r['motivo'] = 'diferido'; return $r;
        }
        $d = at_rt_datos_correo($s);
        // Revisar los campos que salen, nunca render.view_url (iframe autorizado).
        if (at_rt_internas($d) || stripos(wp_json_encode($d), 'easypanel') !== false || !at_rt_url_correo($d['url_ver'])) { $r['motivo'] = 'interno'; return $r; }
        $n = at_rt_envios_realizados($s['envios']) + 1;
        // Lo aprobado debe ser exactamente lo que sale y a quien se aprobó (correo y teléfono de la foto, Ruling 18); si
        // cambió después de aprobar, vuelve a pedir aprobación.
        if (!$prueba && !at_rt_aprobacion_calza($s, $n)) {
            if (at_rt_vencer_aprobacion($id, $s['aprobacion'], $ahora) === 'render') {
                // Ya confirmado y fuera del candado: la presentación nueva se pide sola, con la huella del contenido actual
                // (las fotos de encargos sin cambios se reutilizan). Sin flujo configurado queda `pendiente` con su nota; si
                // n8n falla, at_rt_pedir_render lo deja en `error`. En ambos casos la emisión sigue detenida (exige `listo`).
                // Solo el reloj llega aquí (no es prueba): espera lo mismo que sus otros pedidos a n8n (Ruling 12).
                try { at_rt_pedir_render($id, at_rt_timeout_reloj()); }
                catch (Throwable $e) { error_log('Retomar: no se pudo pedir la presentación tras vencer la aprobación: ' . $e->getMessage()); }
            }
            $r['motivo'] = 'aprobacion_vencida'; return $r;
        }
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
        $entrega = at_rt_finalizar_entrega($id, $n, (bool) $ok, $ahora, $s['aprobacion'], $s['render']);
        if (!$entrega) {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET estado='pausado',proximo_at=NULL WHERE id=%d AND estado='activo' AND enviando=%s", $id, $ahora));
            return ['ok' => false, 'motivo' => 'registro_error', 'whatsapp' => null];
        }
        $s = $entrega['seguimiento'];
        if ($ok) {
            at_rt_historia($s, 'retomar_correo', '🔁 Recordatorio enviado');
            // El envío ya quedó registrado: un fallo al marcar la propuesta no debe cortar el reloj de los demás.
            try { at_rt_marcar_propuesta_enviada($s, $ahora); }
            catch (Throwable $e) { error_log('Retomar: no se pudo marcar la propuesta como enviada: ' . $e->getMessage()); }
        }
        if ($entrega['whatsapp']) { $r['whatsapp'] = ['id' => $id, 'n' => $n, 'telefono' => at_pt_telefono_wa($s['telefono']), 'nombre' => $s['nombre'], 'negocio' => $s['negocio'], 'token' => $s['token']]; }
        return $r;
    } finally {
        if ($claim) { $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET enviando=NULL WHERE id=%d AND enviando=%s", $id, $ahora)); }
    }
}

/**
 * Segundos que espera un pedido a n8n iniciado por el reloj (redacción y presentación). Más corto que los 15 s del panel:
 * n8n corta la llamada al reloj a los 30 s y los pedidos de WhatsApp solo le llegan en la respuesta (Ruling 12).
 */
function at_rt_timeout_reloj(): int { return 5; }

/** Pedidos a n8n que inicia una pasada del reloj (redacciones de la detección y de los reintentos, y presentaciones
 *  atascadas, todo junto); el resto espera la siguiente hora. */
function at_rt_tope_pedidos_reloj(): int { return 3; }

/**
 * Reloj por hora (n8n lo llama cada hora). Ruling 12: primero, solo en día hábil y dentro de la ventana 10:00–13:00,
 * entrega lo que Luis aprobó; así la lista de WhatsApp queda armada antes de cualquier trabajo lento. Después, en toda
 * pasada: detecta propuestas estancadas, vuelve a pedir las redacciones atascadas (Ruling 7), vuelve a pedir las
 * presentaciones que llevan más de 6 h esperando (I4, at_rt_reintentar_renders) y manda el correo semanal si le toca
 * (at_rt_resumen decide día y hora). Detección, reintentos y las presentaciones desactualizadas que encuentra la emisión del
 * resumen inician juntos como máximo at_rt_tope_pedidos_reloj() pedidos a n8n, cada uno con at_rt_timeout_reloj(): un
 * detectado que no cupo queda con la redacción vacía, y una presentación que no cupo queda `pendiente`; los toma el
 * reintento de una pasada siguiente. Cada paso corre aunque el anterior falle. Sin el esquema listo no hace nada.
 * Devuelve {enviados, diferidos, detenidos, whatsapp, detectados, redacciones, renders, resumen}.
 */
function at_rt_tick(string $ahora): array {
    global $wpdb;
    $r = ['enviados' => 0, 'diferidos' => 0, 'detenidos' => 0, 'whatsapp' => [], 'detectados' => 0, 'redacciones' => 0, 'renders' => 0, 'resumen' => ['enviado' => false, 'tarjetas' => 0, 'motivo' => '']];
    if (!at_rt_esquema_listo()) { $r['resumen']['motivo'] = 'esquema'; return $r; }
    try { $d = new DateTimeImmutable($ahora, new DateTimeZone('America/Santiago')); } catch (Exception $e) { return $r; }
    $d = $d->setTimezone(new DateTimeZone('America/Santiago'));
    $cuando = $d->format('Y-m-d H:i:s');
    if (at_rt_es_habil($d->format('Y-m-d')) && at_rt_en_ventana($d->format('H:i'))) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado='activo' AND proximo_at<=%s ORDER BY id", $d->format('Y-m-d')));
        foreach ($ids as $id) {
            try { $e = at_rt_entregar((int) $id, false, $cuando); }
            catch (Throwable $ex) { error_log('Retomar: el envío #' . (int) $id . ' del reloj falló: ' . $ex->getMessage()); continue; }
            if ($e['ok']) { $r['enviados']++; }
            elseif ($e['motivo'] === 'diferido') { $r['diferidos']++; }
            elseif (at_rt_estado_por_motivo($e['motivo']) !== '') { $r['detenidos']++; }
            if ($e['whatsapp']) { $r['whatsapp'][] = $e['whatsapp']; }
        }
        try { foreach (at_rt_whatsapp_rezagados($cuando) as $w) { $r['whatsapp'][] = $w; } }
        catch (Throwable $ex) { error_log('Retomar: no se pudieron revisar los WhatsApp rezagados: ' . $ex->getMessage()); }
    }
    $cupo = at_rt_tope_pedidos_reloj();
    try {
        $detectados = at_rt_detectar($cuando, $cupo);
        $r['detectados'] = count($detectados['creados']) + count($detectados['espera']);
        // La detección pide la redacción de los primeros activos que crea, hasta el cupo (at_rt_detectar).
        $cupo -= min($cupo, count($detectados['creados']));
    } catch (Throwable $e) { error_log('Retomar: la detección del reloj falló: ' . $e->getMessage()); }
    try {
        $r['redacciones'] = count(at_rt_reintentar_redacciones($cuando, $cupo));
        $cupo -= min($cupo, $r['redacciones']);
    } catch (Throwable $e) { error_log('Retomar: el reintento de redacciones del reloj falló: ' . $e->getMessage()); }
    try {
        $r['renders'] = $cupo > 0 ? count(at_rt_reintentar_renders($cuando, $cupo)) : 0;
        $cupo -= min($cupo, $r['renders']);
    } catch (Throwable $e) { error_log('Retomar: el reintento de presentaciones del reloj falló: ' . $e->getMessage()); }
    // El resumen gasta lo que quede del cupo: una emisión que encuentra la presentación desactualizada la pide de nuevo.
    try { $r['resumen'] = at_rt_resumen($cuando, false, $cupo); }
    catch (Throwable $e) { error_log('Retomar: el resumen semanal del reloj falló: ' . $e->getMessage()); }
    return $r;
}

/** CAS del JSON completo: un callback jamás pisa eventos agregados concurrentemente. */
/**
 * WhatsApp pedidos a n8n que nunca volvieron con resultado (20-oct-2026, PROD: la pasada de las 10:05 mandó los correos
 * pero a n8n le llegó una respuesta vacía, así que el pedido se perdió y quedó «pendiente» para siempre). En una pasada
 * posterior, pasados 50 minutos del pedido anterior, se vuelve a pedir UNA vez (máximo dos pedidos por recordatorio), solo
 * si el envío es de hoy o de ayer, el WhatsApp automático está encendido y el prospecto no se detuvo (baja, rechazo,
 * cliente o reunión; «agotado» sí recibe el WhatsApp de su último recordatorio). Costo aceptado: si Meta lo aceptó pero el
 * aviso de vuelta (whatsapp-resultado) se perdió, al prospecto le puede llegar dos veces, nunca más.
 * Lo marca bajo CAS sobre los eventos (como at_rt_whatsapp_resultado): un aviso que llega entretanto gana.
 */
function at_rt_whatsapp_rezagados(string $cuando): array {
    global $wpdb;
    if (!at_rt_wa_automatico()) { return []; }
    $zona = new DateTimeZone('America/Santiago');
    $limite = (new DateTimeImmutable($cuando, $zona))->modify('-50 minutes')->format('Y-m-d H:i:s');
    $desde = (new DateTimeImmutable(substr($cuando, 0, 10), $zona))->modify('-1 day')->format('Y-m-d');
    $items = [];
    $ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado IN ('activo','agotado') AND envios LIKE '%pendiente%' ORDER BY id");
    foreach ($ids as $id) {
        $id = (int) $id;
        $s = at_rt_obtener($id);
        if (!$s || !in_array(at_rt_motivo_parada(at_rt_contexto_parada($s)), ['', 'agotado'], true)) { continue; }
        $tel = at_pt_telefono_wa((string) $s['telefono']);
        if ($tel === '') { continue; }
        $raw = $wpdb->get_var($wpdb->prepare("SELECT envios FROM {$wpdb->prefix}at_retomar WHERE id=%d", $id));
        $eventos = json_decode((string) $raw, true);
        if (!is_array($eventos)) { continue; }
        $pedir = [];
        foreach ($eventos as &$e) {
            if (($e['tipo'] ?? '') !== 'envio' || !is_array($e['whatsapp'] ?? null) || ($e['whatsapp']['estado'] ?? '') !== 'pendiente') { continue; }
            if ((string) ($e['fecha'] ?? '') < $desde) { continue; }
            $pedidos = (int) ($e['whatsapp']['pedidos'] ?? 1);
            $ultimo = (string) ($e['whatsapp']['pedido_at'] ?? ($e['at'] ?? ''));
            if ($pedidos >= 2 || $ultimo === '' || $ultimo > $limite) { continue; }
            $e['whatsapp']['pedidos'] = $pedidos + 1;
            $e['whatsapp']['pedido_at'] = $cuando;
            $pedir[] = (int) ($e['n'] ?? 0);
        }
        unset($e);
        if (!$pedir) { continue; }
        $cambio = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET envios=%s WHERE id=%d AND BINARY envios=BINARY %s", wp_json_encode($eventos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id, $raw));
        if ($cambio !== 1) { continue; }
        foreach ($pedir as $n) { $items[] = ['id' => $id, 'n' => $n, 'telefono' => $tel, 'nombre' => $s['nombre'], 'negocio' => $s['negocio'], 'token' => $s['token']]; }
    }
    return $items;
}
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
