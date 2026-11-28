<?php
/** Supresión por destinatario. La URL de baja identifica el intento y lleva HMAC, nunca correo/teléfono. */
if (!defined('ABSPATH')) { exit; }
function at_rg_bajas(array $cliente): ?array {
    global $wpdb;
    $email = at_rg_email((string) ($cliente['email'] ?? '')); $tel = at_rg_telefono((string) ($cliente['telefono'] ?? ''));
    $rows = $wpdb->get_results($wpdb->prepare('SELECT email,telefono,canal,alcance FROM ' . at_rg_tabla_bajas() . " WHERE (email<>'' AND email=%s) OR (telefono<>'' AND telefono=%s)", $email, $tel), ARRAY_A);
    return $wpdb->last_error === '' && is_array($rows) ? $rows : null; // error DB: jamás habilitar un canal
}
function at_rg_baja_guardar(string $email, string $tel, string $canal, string $alcance, string $origen, string $nota = ''): bool {
    if (!in_array($canal, ['correo','whatsapp','todos'], true) || !in_array($alcance, ['resenas','todo'], true)
        || strlen($origen) > 30 || mb_strlen($nota) > 500 || $origen === '') { return false; }
    $nota = ''; // Evidencia mínima: no conservar texto libre en supresiones.
    $email = at_rg_email($email); $tel = at_rg_telefono($tel);
    if ($email === '' && $tel === '') { return false; }
    global $wpdb;
    $clave = hash('sha256', implode('|', [$email, $tel, $canal, $alcance]));
    $t = at_rg_tabla_bajas();
    $n = $wpdb->query($wpdb->prepare("INSERT INTO {$t} (email,telefono,canal,alcance,fecha,origen,nota,clave) VALUES (%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id=id",
        $email, $tel, $canal, $alcance, gmdate('Y-m-d H:i:s'), $origen, $nota, $clave));
    return $n !== false;
}
function at_rg_token_baja(int $id, array $intento): string {
    $cuerpo = $id . '.' . $intento['id'];
    return $cuerpo . '.' . hash_hmac('sha256', 'at-resena-baja|' . $cuerpo . '|' . $intento['destinos']['correo'], wp_salt('auth'));
}
function at_rg_validar_baja(string $token): ?array {
    if (!preg_match('/^([1-9][0-9]{0,18})\.([a-f0-9-]{36})\.([a-f0-9]{64})$/D', $token, $m)) { return null; }
    $f = at_rg_fila((int) $m[1]); if (!$f) { return null; }
    try { $datos = at_rg_datos($f); } catch (Throwable $e) { return null; }
    foreach ($datos['intentos'] as $i) {
        if ($i['id'] === $m[2] && at_rg_email((string) ($i['destinos']['correo'] ?? '')) !== ''
            && hash_equals(at_rg_token_baja((int) $f->id, $i), $token)) { return ['email' => $i['destinos']['correo']]; }
    }
    return null;
}
function at_rg_ruta_publica(): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $base = rtrim((string) parse_url(home_url('/resena/'), PHP_URL_PATH), '/');
    if (!in_array(rtrim((string) $path, '/'), [$base, $base . '/baja'], true)) { return; }
    nocache_headers(); header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    if (rtrim((string) $path, '/') === $base) {
        $url = at_rg_url_google((string) get_option('at_resena_google_url', ''));
        if ($url !== '') { wp_redirect($url, 302, 'AutomatizaTech'); exit; }
        status_header(404); echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Reseñas</title><p>El enlace de reseñas todavía no está disponible.</p></html>'; exit;
    }
    $raw = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? $_GET['token'] ?? '') : ($_GET['token'] ?? '');
    $token = is_string($raw) ? wp_unslash($raw) : '';
    $b = at_rg_validar_baja($token);
    $confirmada = false;
    $alcance = ($_POST['List-Unsubscribe'] ?? '') === 'One-Click' ? 'resenas' : ($_POST['alcance'] ?? 'resenas');
    if ($b && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $confirmada = is_string($alcance) && at_rg_baja_guardar($b['email'], '', 'correo', $alcance, 'enlace');
    }
    status_header(!$b ? 400 : ($_SERVER['REQUEST_METHOD'] === 'POST' && !$confirmada ? 503 : 200)); header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Baja de invitaciones</title><h1>✉️ Invitaciones a reseñar</h1>';
    if (!$b) { echo '<p>No pudimos validar este enlace. Puedes pedir la baja respondiendo al correo.</p>'; }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$confirmada) { echo '<p>No pudimos registrar la baja. Inténtalo nuevamente o responde al correo.</p>'; }
    elseif ($confirmada) { echo $alcance === 'todo' ? '<p>✅ No recibirás más comunicaciones promocionales de AutomatizaTech por correo.</p>' : '<p>✅ No recibirás más invitaciones a reseñar por correo.</p>'; }
    else {
        echo '<p>Elige qué comunicaciones quieres dejar de recibir por correo.</p><form method="post" action="' . esc_url(home_url('/resena/baja/')) . '"><input type="hidden" name="token" value="' . esc_attr($token) . '"><button type="submit" name="alcance" value="resenas">✉️ Solo dejar de recibir invitaciones a reseñar</button><button type="submit" name="alcance" value="todo">✋ No recibir ninguna comunicación promocional de AutomatizaTech</button></form>';
    }
    echo '</html>'; exit;
}
add_action('template_redirect', 'at_rg_ruta_publica', 0);

/** Consulta pública para Retomar/Prospección: cualquier canal con alcance todo.
 * Ante fallo de lectura bloquea por precaución; no autoriza promociones sin verificar.
 */
function at_rg_baja_total(string $email, string $telefono): bool {
    $bajas = at_rg_bajas(['email' => $email, 'telefono' => $telefono]);
    if ($bajas === null) { return true; }
    $email = at_rg_email($email); $telefono = at_rg_telefono($telefono);
    foreach ($bajas as $b) {
        if (($b['alcance'] ?? '') === 'todo' && (
            ($email !== '' && $email === at_rg_email((string) ($b['email'] ?? '')))
            || ($telefono !== '' && $telefono === at_rg_telefono((string) ($b['telefono'] ?? ''))))) { return true; }
    }
    return false;
}
function at_rg_whatsapp_baja(string $telefono, string $texto, ?DateTimeImmutable $ahora = null) {
    $telefono = at_rg_telefono($telefono);
    if ($telefono === '' || mb_strlen($texto) > 5000) { return at_rg_error('datos', 400); }
    $intencion = at_rg_intencion_baja($texto);
    if ($intencion === 'baja') {
        return at_rg_baja_guardar('', $telefono, 'whatsapp', 'todo', 'bot') ? ['ok'=>true, 'baja'=>true] : at_rg_error('base', 503);
    }
    if ($intencion === 'revisar') {
        $dia = ($ahora ?? at_rg_ahora())->setTimezone(new DateTimeZone('America/Santiago'))->format('Y-m-d');
        $clave = 'at_resena_baja_revisar_' . $dia . '_' . hash('sha256', $telefono);
        if (add_option($clave, 'reservado', '', false)) {
            try {
                $ok = at_rg_mail_cerrado(at_cc_correo_avisos(), 'Reseña · revisar solicitud de baja',
                    '<p>Revisa a mano la conversación de WhatsApp ' . esc_html($telefono) . '. Contiene baja o stop, pero no empieza con una solicitud inequívoca. No se registró una baja automática.</p>');
            } catch (Throwable $e) { $ok = false; }
            update_option($clave, $ok ? 'avisado' : 'fallido', false);
        }
        return ['ok'=>true, 'revision'=>true];
    }
    return ['ok'=>true, 'baja'=>false];
}
