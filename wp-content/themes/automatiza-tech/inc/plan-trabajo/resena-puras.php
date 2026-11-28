<?php
/** Reglas de reseñas. Sin WordPress, reloj global, IO ni destinatarios implícitos. */
function at_rg_vacio(): array {
    return ['termino' => [], 'excluido' => false, 'motivo_exclusion' => '', 'permiso' => [], 'intentos' => [], 'historial_escrito' => [], 'resena_recibida' => []];
}
/** Proyección barata de trabajo pendiente; las marcas fallidas definitivas se revisan en panel. */
function at_rg_abierto(array $d): int {
    if (!empty($d['anonimizado_at'])) { return 0; }
    $tope = count(at_rg_fallidos_totales($d)) >= 3;
    if ($tope && (!isset($d['historial_escrito']['aviso:limite_fallidos'])
        || $d['historial_escrito']['aviso:limite_fallidos'] === 'en_curso')) { return 1; }
    foreach ($d['intentos'] as $i) {
        foreach (['correo','whatsapp'] as $c) {
            $e = $i[$c]['estado'];
            if ($e === 'en_curso') { return 1; }
            if ($e === 'no_intentado') { continue; }
            $evento = 'resena:' . $i['id'] . ':' . $c . ':' . $e;
            if (!isset($d['historial_escrito'][$evento]) || $d['historial_escrito'][$evento] === 'en_curso') { return 1; }
            if ($d['historial_escrito'][$evento] !== true || in_array($e, ['fallido','desconocido'], true)) {
                if ($tope && at_rg_totalmente_fallido($i)) { continue; }
                if (!isset($d['historial_escrito']['aviso:' . $evento]) || $d['historial_escrito']['aviso:' . $evento] === 'en_curso') { return 1; }
            }
        }
    }
    return 0;
}
function at_rg_fecha(string $f): bool {
    return (bool) preg_match('/^(20\d{2})-(\d{2})-(\d{2})$/D', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}
function at_rg_fecha_envio(string $f): string {
    return at_rg_fecha($f) ? (new DateTimeImmutable($f . ' 12:00:00', new DateTimeZone('America/Santiago')))->modify('+7 days')->format('Y-m-d') : '';
}
function at_rg_email(string $v): string {
    $v = strtolower(trim($v));
    return strlen($v) <= 254 && filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
}
function at_rg_telefono(string $v): string {
    $v = preg_replace('/[ ().-]/', '', trim($v));
    if (preg_match('/^569[0-9]{8}$/D', $v)) { $v = '+' . $v; }
    // crm_clientes no tiene país: un número nacional nunca prueba que sea Chile.
    // Con +56 WhatsApp exige móvil chileno de exactamente nueve dígitos nacionales.
    if (str_starts_with($v, '+56')) { return preg_match('/^\+569[0-9]{8}$/D', $v) ? $v : ''; }
    return preg_match('/^\+[1-9][0-9]{7,14}$/D', $v) ? $v : '';
}
/** Destino administrado: HTTPS, host Google conocido, sin usuario, puerto ni bucle /resena/. */
function at_rg_url_google(string $u): string {
    if (strlen($u) > 1000 || !filter_var($u, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20<>"\x27\\\\]/', $u)
        || str_contains($u, '..') || preg_match('/%2e|%25|%2f|%5c/i', $u)) { return ''; }
    $p = parse_url($u);
    if (($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port']) || isset($p['fragment'])) { return ''; }
    $host = strtolower($p['host'] ?? ''); $path = $p['path'] ?? '';
    $valido = match ($host) {
        'g.page' => (bool) preg_match('#^/(?:r/)?[A-Za-z0-9_-]+/review/?$#D', $path),
        'search.google.com' => $path === '/local/writereview',
        'www.google.com' => (bool) preg_match('#^/maps/place/[A-Za-z0-9_%+.,!:@=~-]+(?:/[A-Za-z0-9_%+.,!:@=~-]+)*/?$#D', $path),
        default => false,
    };
    // Los parámetros de navegación arbitraria tampoco forman parte de un enlace a reseñar.
    if (isset($p['query']) && !preg_match('/^(?:placeid=[A-Za-z0-9_-]+|(?:hl=[a-zA-Z-]+|entry=[A-Za-z0-9_-]+)(?:&(?:hl=[a-zA-Z-]+|entry=[A-Za-z0-9_-]+))*)$/D', $p['query'])) { return ''; }
    return $valido ? $u : '';
}
function at_rg_canales(array $c, array $ctx): array {
    $email = at_rg_email((string) ($c['email'] ?? ''));
    $tel = at_rg_telefono((string) ($c['telefono'] ?? ''));
    $p = $ctx['permiso'] ?? [];
    $canales = ['correo' => $email !== '', 'whatsapp' => $tel !== '' && ($p['autorizado'] ?? false) === true
        && trim((string) ($p['origen'] ?? '')) !== '' && at_rg_fecha((string) ($p['fecha'] ?? '')) && ($p['telefono'] ?? '') === $tel];
    foreach ($ctx['bajas'] ?? [] as $b) {
        if (!in_array($b['alcance'] ?? '', ['resenas', 'todo'], true)) { continue; }
        $coincide = ($email !== '' && $email === at_rg_email((string) ($b['email'] ?? '')))
            || ($tel !== '' && $tel === at_rg_telefono((string) ($b['telefono'] ?? '')));
        if ($coincide) {
            foreach (array_keys($canales) as $canal) {
                if (in_array($b['canal'] ?? '', [$canal, 'todos'], true)) { $canales[$canal] = false; }
            }
        }
    }
    return $canales;
}
function at_rg_reales(array $d): array {
    return array_values(array_filter($d['intentos'] ?? [], 'at_rg_consume_cupo'));
}
/** M4, Luis 19-nov: solo un fallo total comprobado devuelve cupo; lo incierto bloquea. */
function at_rg_consume_cupo(array $i): bool {
    if (($i['modo'] ?? '') !== 'real') { return false; }
    foreach (['correo','whatsapp'] as $canal) {
        if (!in_array($i[$canal]['estado'] ?? 'desconocido', ['fallido','no_intentado'], true)) { return true; }
    }
    return false;
}
function at_rg_totalmente_fallido(array $i): bool {
    return ($i['modo'] ?? '') === 'real' && !at_rg_consume_cupo($i)
        && in_array('fallido', [$i['correo']['estado'] ?? '', $i['whatsapp']['estado'] ?? ''], true);
}
function at_rg_fallidos_totales(array $d): array {
    return array_values(array_filter($d['intentos'] ?? [], 'at_rg_totalmente_fallido'));
}
/** Devuelve motivo de bloqueo, o ''. Manual exceptúa únicamente plazo/corte. Prueba conserva elegibilidad del cliente. */
function at_rg_elegibilidad(array $d, array $c, array $ctx, DateTimeImmutable $ahora): string {
    $hoy = $ahora->setTimezone(new DateTimeZone('America/Santiago'))->format('Y-m-d');
    $fecha = (string) ($d['termino']['fecha'] ?? '');
    if (($c['tipo'] ?? '') !== 'cliente') { return 'cliente'; }
    if (!at_rg_fecha($fecha) || $fecha > $hoy) { return 'termino'; }
    if (!at_rg_url_google((string) ($ctx['url'] ?? ''))) { return 'enlace'; }
    if (!empty($d['anonimizado_at'])) { return 'anonimizado'; }
    if (!empty($d['resena_recibida'])) { return 'resena_recibida'; }
    if (!empty($d['excluido'])) { return 'excluido'; }
    if (!in_array(true, at_rg_canales($c, $ctx), true)) { return 'canales'; }
    $reales = at_rg_reales($d);
    if (empty($ctx['manual']) && !$reales) {
        if ($fecha < '2026-11-19') { return 'historico'; }
        if (at_rg_fecha_envio($fecha) > $hoy) { return 'plazo'; }
    }
    foreach ($reales as $i) {
        foreach (['correo', 'whatsapp'] as $canal) {
            if (in_array($i[$canal]['estado'] ?? '', ['en_curso', 'desconocido'], true)) { return 'bloqueado'; }
        }
    }
    if (!empty($ctx['prueba'])) { return ''; }
    if (count(at_rg_fallidos_totales($d)) >= 3) { return 'limite_fallidos'; }
    if (count($reales) >= 2 || array_filter($d['intentos'], fn($i) => $i['modo'] === 'real' && $i['tipo'] === 'reenvio')) { return 'cupo'; }
    if ($reales) {
        if (!empty($ctx['manual']) && empty($ctx['reenvio'])) { return 'intentado'; }
        if (!empty($ctx['manual']) && (trim((string) ($ctx['motivo'] ?? '')) === '' || mb_strlen((string) $ctx['motivo']) > 500)) { return 'motivo_reenvio'; }
        if (at_rg_tiene_baja($c, $ctx['bajas'] ?? [])) { return 'baja'; }
        $aceptada = array_values(array_filter($reales, fn($i) => $i['tipo'] === 'invitacion'
            && count(array_intersect([$i['correo']['estado'], $i['whatsapp']['estado']], ['aceptado','entregado','leido'])) > 0));
        if (!$aceptada) { return 'sin_invitacion'; }
        $anterior = new DateTimeImmutable($aceptada[0]['creado_at']);
        if ($ahora < $anterior->setTimezone(new DateTimeZone('America/Santiago'))->modify('+7 days')) { return 'plazo_reenvio'; }
    } elseif (!empty($ctx['reenvio'])) { return 'sin_invitacion'; }
    return '';
}
function at_rg_intento(string $id, string $modo, string $origen, bool $reenvio, array $canales, DateTimeImmutable $ahora): array {
    $at = $ahora->format(DATE_ATOM);
    $i = ['id' => $id, 'modo' => $modo, 'origen' => $origen, 'tipo' => $reenvio ? 'reenvio' : 'invitacion', 'creado_at' => $at, 'avisos' => []];
    foreach (['correo', 'whatsapp'] as $c) {
        $i[$c] = ['estado' => !empty($canales[$c]) ? 'en_curso' : 'no_intentado', 'at' => $at, 'detalle' => '', 'wamid' => ''];
    }
    return $i;
}
function at_rg_vencer(array $d, DateTimeImmutable $ahora): array {
    foreach ($d['intentos'] as &$i) {
        foreach (['correo', 'whatsapp'] as $c) {
            if ($i[$c]['estado'] === 'en_curso' && $ahora->getTimestamp() - (new DateTimeImmutable($i[$c]['at']))->getTimestamp() >= 1800) {
                $i[$c]['estado'] = 'desconocido'; $i[$c]['detalle'] = 'Sin confirmación durante 30 minutos; revisar antes de liberar.';
            }
        }
    }
    unset($i); return $d;
}
/** Monótono; callbacks viejos apuntan a su intento. null: contrato inválido, sin mutación. */
function at_rg_resultado(array $d, string $id, string $modo, string $estado, string $wamid, string $detalle): ?array {
    if (!empty($d['anonimizado_at'])) { return null; }
    if (!in_array($estado, ['aceptado', 'fallido', 'desconocido', 'entregado', 'leido'], true) || !in_array($modo, ['real', 'prueba'], true)) { return null; }
    foreach ($d['intentos'] as &$i) {
        if ($i['id'] !== $id) { continue; }
        if ($i['modo'] !== $modo || $i['whatsapp']['estado'] === 'no_intentado') { return null; }
        $w = &$i['whatsapp'];
        if ($w['wamid'] !== '' && $wamid !== '' && $w['wamid'] !== $wamid) { return null; }
        if (in_array($estado, ['aceptado', 'entregado', 'leido'], true) && $wamid === '') { return null; }
        $rango = ['no_intentado' => 0, 'en_curso' => 1, 'desconocido' => 2, 'aceptado' => 3, 'fallido' => 4, 'entregado' => 5, 'leido' => 6];
        if ($rango[$estado] <= $rango[$w['estado']]) { return $d; }
        $w['estado'] = $estado; $w['wamid'] = $wamid ?: $w['wamid']; $w['detalle'] = $detalle;
        return $d;
    }
    return null;
}
function at_rg_estado(array $i): string {
    $e = [$i['correo']['estado'], $i['whatsapp']['estado']];
    if (in_array('desconocido', $e, true)) { return 'En revisión (resultado desconocido)'; }
    if (in_array('en_curso', $e, true)) { return 'En curso'; }
    $aceptados = count(array_intersect($e, ['aceptado', 'entregado', 'leido']));
    if ($aceptados) { return in_array('fallido', $e, true) ? 'Parcial' : 'Tramitada'; }
    return 'Fallida';
}

/** Clasifica intención explícita o duda; no interpreta el sentimiento de una reseña. */
function at_rg_intencion_baja(string $texto): string {
    $texto = strtr(mb_strtolower($texto, 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $texto = preg_replace('/[\p{M}]/u', '', $texto) ?? '';
    $texto = trim(preg_replace('/[^a-z0-9]+/u', ' ', $texto) ?? '');
    foreach (['baja', 'stop', 'darme de baja', 'no me escriban', 'no quiero recibir'] as $inicio) {
        if (str_starts_with($texto, $inicio)) { return 'baja'; }
    }
    return str_contains($texto, 'baja') || str_contains($texto, 'stop') ? 'revisar' : '';
}

/** Plazo desde el último intento de cualquier modo; relación vigente prevalece.
 * Mantiene identificadores técnicos/cupos, estados y fechas, sin contactos ni texto libre.
 */
function at_rg_anonimizar(array $d, DateTimeImmutable $ahora, bool $relacion_vigente): array {
    if ($relacion_vigente || !empty($d['anonimizado_at']) || !$d['intentos']) { return $d; }
    $ultimo = max(array_map(fn($i) => (new DateTimeImmutable($i['creado_at']))->getTimestamp(), $d['intentos']));
    $limite = $ahora->setTimestamp($ultimo)->modify('+24 months');
    if ($ahora <= $limite) { return $d; }
    foreach ($d['intentos'] as &$i) {
        $limpio = array_intersect_key($i, array_flip(['id','modo','origen','tipo','creado_at']));
        foreach (['correo','whatsapp'] as $canal) {
            $limpio[$canal] = ['estado'=>$i[$canal]['estado'], 'at'=>$i[$canal]['at'], 'detalle'=>'', 'wamid'=>''];
        }
        $limpio['destinos'] = ['correo'=>'', 'whatsapp'=>''];
        $limpio['nombre'] = ''; $limpio['proyecto'] = ''; $limpio['despacho_wa'] = true; $limpio['avisos'] = [];
        $i = $limpio;
    }
    unset($i); $d['permiso'] = []; $d['motivo_exclusion'] = '';
    $d['anonimizado_at'] = $ahora->format(DATE_ATOM);
    return $d;
}

/** El recordatorio se detiene ante cualquier baja coincidente, también del otro canal. */
function at_rg_tiene_baja(array $cliente, array $bajas): bool {
    $email = at_rg_email((string) ($cliente['email'] ?? ''));
    $telefono = at_rg_telefono((string) ($cliente['telefono'] ?? ''));
    foreach ($bajas as $b) {
        if (($email !== '' && $email === at_rg_email((string) ($b['email'] ?? '')))
            || ($telefono !== '' && $telefono === at_rg_telefono((string) ($b['telefono'] ?? '')))) { return true; }
    }
    return false;
}

/** Inicio de ventana del resumen de envíos: ISO con desfase; null si no parsea, >5 min futuro o >48 h pasado. */
function at_rg_resumen_desde(string $v, DateTimeImmutable $ahora): ?DateTimeImmutable {
    $v = trim($v);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $v)) { return null; }
    try { $d = new DateTimeImmutable($v); } catch (Exception $e) { return null; }
    $dif = $d->getTimestamp() - $ahora->getTimestamp();
    if ($dif > 300 || $dif < -48 * 3600) { return null; }
    return $d->setTimezone(new DateTimeZone('America/Santiago'));
}
/** Por qué un canal quedó en «no_intentado». Refleja at_rg_canales. */
function at_rg_motivo_sin_canal(string $canal, array $c, array $ctx): string {
    if ($canal === 'correo' && at_rg_email((string) ($c['email'] ?? '')) === '') { return 'sin correo'; }
    if ($canal === 'whatsapp') {
        if (at_rg_telefono((string) ($c['telefono'] ?? '')) === '') { return 'sin teléfono'; }
        $sin = at_rg_canales($c, ['permiso' => $ctx['permiso'] ?? [], 'bajas' => []]);
        if (empty($sin['whatsapp'])) { return 'sin permiso de WhatsApp'; }
    }
    $sin = at_rg_canales($c, ['permiso' => $ctx['permiso'] ?? [], 'bajas' => []]);
    $con = at_rg_canales($c, $ctx);
    if (!empty($sin[$canal]) && empty($con[$canal])) { return 'pidió la baja'; }
    return 'no correspondía';
}
/** Símbolo y texto de un canal para la tabla del resumen. */
function at_rg_resumen_canal(string $canal, array $estado, string $motivo): array {
    $e = (string) ($estado['estado'] ?? 'desconocido');
    $det = trim((string) ($estado['detalle'] ?? ''));
    if ($e === 'no_intentado') { return ['simbolo' => '—', 'texto' => $motivo]; }
    if ($canal === 'correo') {
        if ($e === 'aceptado') { return ['simbolo' => '✓', 'texto' => 'Aceptado por el servidor de correo']; }
        if ($e === 'fallido') { return ['simbolo' => '✗', 'texto' => $det !== '' ? $det : 'No salió']; }
        return ['simbolo' => '⏳', 'texto' => $det !== '' ? $det : 'Sin confirmar'];
    }
    if ($e === 'entregado') { return ['simbolo' => '✓', 'texto' => 'Entregado']; }
    if ($e === 'leido') { return ['simbolo' => '✓', 'texto' => 'Leído']; }
    if ($e === 'fallido') { return ['simbolo' => '✗', 'texto' => $det !== '' ? $det : 'No se entregó']; }
    return ['simbolo' => '⏳', 'texto' => $det !== '' ? $det : 'Meta lo aceptó; sin confirmación de entrega'];
}
/** Filas del resumen: intentos con creado_at >= $desde, orden ascendente, tope 50. total y ok cuentan todos los de la ventana. */
function at_rg_resumen(array $proyectos, DateTimeImmutable $desde): array {
    $filas = [];
    foreach ($proyectos as $p) {
        $cli = $p['cliente'] ?? []; $ctx = $p['ctx'] ?? [];
        $nombre = trim((string) ($cli['nombre'] ?? '')); $empresa = trim((string) ($cli['empresa'] ?? ''));
        foreach (($p['datos']['intentos'] ?? []) as $i) {
            try { $t = new DateTimeImmutable((string) ($i['creado_at'] ?? '')); } catch (Exception $e) { continue; }
            if ($t < $desde) { continue; }
            $fila = ['proyecto_id' => (int) ($p['id'] ?? 0), 'cliente' => $nombre !== '' ? $nombre : $empresa,
                'proyecto' => $empresa !== '' ? $empresa : '—', 'modo' => (string) ($i['modo'] ?? ''),
                'tipo' => (string) ($i['tipo'] ?? ''), 'creado_at' => (string) $i['creado_at'], '_t' => $t->getTimestamp()];
            foreach (['correo', 'whatsapp'] as $canal) {
                $est = $i[$canal] ?? ['estado' => 'desconocido'];
                $motivo = ($est['estado'] ?? '') === 'no_intentado' ? at_rg_motivo_sin_canal($canal, $cli, $ctx) : '';
                $fila[$canal] = at_rg_resumen_canal($canal, $est, $motivo);
            }
            $filas[] = $fila;
        }
    }
    usort($filas, fn($a, $b) => $a['_t'] <=> $b['_t']);
    $ok = 0;
    foreach ($filas as &$f) {
        $s = [$f['correo']['simbolo'], $f['whatsapp']['simbolo']];
        if (in_array('✓', $s, true) && !array_intersect(['✗', '⏳'], $s)) { $ok++; }
        unset($f['_t']);
    }
    unset($f);
    return ['total' => count($filas), 'ok' => $ok, 'filas' => array_slice($filas, 0, 50)];
}
