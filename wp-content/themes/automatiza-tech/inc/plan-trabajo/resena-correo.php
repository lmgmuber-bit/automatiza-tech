<?php
/** Correo sin copias implícitas. wp_mail=true significa aceptado por SMTP, nunca recibido. */
if (!defined('ABSPATH')) { exit; }
/** Casilla corporativa para aviso separado, configurada al desplegar. Nombre de opción histórico. */
function at_rg_copia_oculta(): string {
    $valor = get_option('at_resena_copia_oculta', '');
    return is_string($valor) && !preg_match('/[\r\n,]/', $valor) ? at_rg_email($valor) : '';
}
/** Nombre seguro para cabeceras; el límite se mide en caracteres UTF-8. */
function at_rg_correo_nombre(array $i, int $limite = 60): string {
    $nombre = strip_tags((string) ($i['nombre'] ?? ''));
    $nombre = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $nombre) ?? '';
    $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? '';
    return trim(mb_substr(trim($nombre), 0, $limite, 'UTF-8'));
}
function at_rg_correo_asunto(array $i): string {
    $nombre = at_rg_correo_nombre($i);
    return ($i['modo'] === 'prueba' ? '[Prueba] ' : '')
        . ($nombre !== '' ? $nombre . ', ¿cómo te fue con AutomatizaTech?' : '¿Cómo te fue con AutomatizaTech?');
}
function at_rg_correo_baja(array $i, int $id): string {
    return add_query_arg('token', at_rg_token_baja($id, $i), home_url('/resena/baja/'));
}
function at_rg_correo_parrafo(): string {
    return 'Si quieres, puedes contar tu experiencia en Google con una reseña honesta: nos ayuda a mejorar y les sirve a otros negocios. Si necesitas ayuda con algo, respóndenos aquí.';
}
/** Datos públicos de AT; una sola fuente para HTML y texto plano. */
function at_rg_correo_contactos(): array {
    return [
        ['web', 'https://automatizatech.cl', 'Sitio web', 'automatizatech.cl'],
        ['correo', 'mailto:contacto@automatizatech.cl', 'Correo', 'contacto@automatizatech.cl'],
        ['whatsapp', 'https://wa.me/56927002984', 'WhatsApp', 'WhatsApp'],
        ['instagram', 'https://www.instagram.com/automatizatech.cl', 'Instagram', 'Instagram'],
    ];
}
function at_rg_correo_texto(array $i, int $id, bool $interna = false): string {
    $nombre = at_rg_correo_nombre($i);
    $texto = "¿Nos cuentas tu experiencia?\n\nHola" . ($nombre !== '' ? ' ' . $nombre : '')
        . ', gracias por confiar en AutomatizaTech para ' . (string) ($i['proyecto'] ?? '') . ".\n\n"
        . at_rg_correo_parrafo() . "\n\n⭐ Dejar mi reseña en Google: " . home_url('/resena/')
        . "\nToma menos de un minuto.\n\nUn abrazo,\nEquipo AutomatizaTech\n\n";
    foreach (at_rg_correo_contactos() as [, $url, $alt]) { $texto .= $alt . ': ' . $url . "\n"; }
    return $texto . "\nTe escribimos porque trabajamos contigo en " . (string) ($i['proyecto'] ?? '')
        . ". AUTOMATIZATECH SpA · Política de privacidad: https://automatizatech.cl/privacidad/\n"
        . "No recibir más invitaciones a reseñar por correo: " . ($interna ? '[enlace de baja del cliente]' : at_rg_correo_baja($i, $id))
        . ($interna ? "\nCliente: " . at_rg_email_enmascarado((string) $i['destinos']['correo'])
            . "\nCopia interna; bórrala a los 90 días (política de conservación)" : '');
}
function at_rg_correo_html(array $i, int $id, bool $interna = false): string {
    $baja = $interna ? '' : at_rg_correo_baja($i, $id);
    $nombre = at_rg_correo_nombre($i);
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html(at_rg_correo_asunto($i)); ?></title></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
<div style="display:none;max-height:0;overflow:hidden">Tu opinión nos ayuda a mejorar y les sirve a otros negocios.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb">
<tr><td align="center" style="padding:24px 12px">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td align="center" style="padding:26px 24px 18px;border-bottom:4px solid #0ea5a4">
      <img src="<?= esc_url(get_template_directory_uri() . '/assets/images/Logo-slogan-tagline-2x.png') ?>" alt="AutomatizaTech" width="220" style="display:block;width:220px;max-width:70%;height:auto;border:0">
    </td></tr>
    <tr><td align="center" style="padding:30px 32px 6px">
      <div style="display:inline-block;width:56px;height:56px;line-height:56px;border-radius:28px;background:#e0f2f1;font-size:28px">💬</div>
      <h1 style="margin:16px 0 0;font-size:24px;line-height:1.3;color:#1e40af">¿Nos cuentas tu experiencia?</h1>
    </td></tr>
    <tr><td style="padding:18px 32px 4px;font-size:16px;line-height:1.65">
      <p style="margin:0 0 14px">Hola<?= $nombre !== '' ? ' ' . esc_html($nombre) : '' ?>, gracias por confiar en AutomatizaTech para <?= esc_html((string) ($i['proyecto'] ?? '')) ?>.</p>
      <p style="margin:0 0 14px">Si quieres, puedes contar tu experiencia en Google con una reseña honesta: nos ayuda a mejorar y les sirve a otros negocios. Si necesitas ayuda con algo, respóndenos aquí.</p>
    </td></tr>
    <tr><td align="center" style="padding:14px 32px 8px">
      <a href="<?= esc_url(home_url('/resena/')) ?>" style="display:inline-block;padding:15px 30px;background:#1e40af;color:#ffffff;border-radius:28px;font-size:17px;font-weight:bold;text-decoration:none">⭐ Dejar mi reseña en Google</a>
      <p style="margin:12px 0 0;font-size:13px;color:#64748b">Toma menos de un minuto.</p>
    </td></tr>
    <tr><td style="padding:22px 32px 26px;font-size:15px;line-height:1.6">
      <p style="margin:0">Un abrazo,<br><strong>Equipo AutomatizaTech</strong></p>
    </td></tr>
    <tr><td align="center" style="padding:18px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.7;color:#64748b">
      <?php foreach (at_rg_correo_contactos() as [$icono, $url, $alt, $etiqueta]): ?>
      <a href="<?= esc_url($url) ?>" style="display:inline-block;margin:4px 8px;color:#1e40af;text-decoration:none"><img src="<?= esc_url(get_template_directory_uri() . '/assets/images/correo/' . $icono . '.png') ?>" alt="<?= esc_html($alt) ?>" width="20" height="20" style="display:inline-block;vertical-align:middle;border:0;width:20px;height:20px"> <?= esc_html($etiqueta) ?></a>
      <?php endforeach; ?><br>
      <p style="margin:12px 0 0">Te escribimos porque trabajamos contigo en <?= esc_html((string) ($i['proyecto'] ?? '')) ?>. AUTOMATIZATECH SpA · Política de privacidad: <a href="https://automatizatech.cl/privacidad/" style="color:#64748b">automatizatech.cl/privacidad/</a></p>
      <?php if ($interna): ?>
      <p>[enlace de baja del cliente]</p>
      <p>Cliente: <?= esc_html(at_rg_email_enmascarado((string) $i['destinos']['correo'])) ?><br>Copia interna; bórrala a los 90 días (política de conservación)</p>
      <?php else: ?>
      <a href="<?= esc_url($baja) ?>" style="display:inline-block;margin-top:10px;color:#64748b">No recibir más invitaciones a reseñar por correo</a>
      <?php endif; ?>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>

    <?php
    return (string) ob_get_clean();
}
/** Cada mensaje tiene un solo destinatario: retira To/CC/BCC agregados por filtros. */
function at_rg_mail_cerrado(string $destino, string $asunto, string $html, string $texto = '', string $baja = ''): bool {
    if (!at_rg_email($destino)) { return false; }
    $cabeceras = ['Content-Type: text/html; charset=UTF-8', 'Reply-To: contacto@automatizatech.cl'];
    if ($baja !== '' && !preg_match('/[\x00-\x20<>\x7f]/', $baja)
        && filter_var($baja, FILTER_VALIDATE_URL) && parse_url($baja, PHP_URL_SCHEME) === 'https') {
        $cabeceras[] = 'List-Unsubscribe: <' . $baja . '>';
        $cabeceras[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    $cerrar = static function ($mailer) use ($destino, $texto): void {
        $mailer->AltBody = $texto;
        $mailer->clearAllRecipients(); $mailer->addAddress($destino);
    };
    $filtrar = static function (array $args) use ($destino, $cabeceras): array {
        $args['to'] = $destino;
        $args['headers'] = $cabeceras;
        return $args;
    };
    add_filter('wp_mail', $filtrar, PHP_INT_MAX); add_action('phpmailer_init', $cerrar, PHP_INT_MAX);
    try {
        return wp_mail($destino, $asunto, $html, $cabeceras) === true;
    } finally { remove_filter('wp_mail', $filtrar, PHP_INT_MAX); remove_action('phpmailer_init', $cerrar, PHP_INT_MAX); }
}
function at_rg_enviar_correo(array $i, int $id): bool {
    $aceptado = at_rg_mail_cerrado($i['destinos']['correo'], at_rg_correo_asunto($i), at_rg_correo_html($i, $id),
        at_rg_correo_texto($i, $id), at_rg_correo_baja($i, $id));
    $copia = at_rg_copia_oculta();
    if ($aceptado && $copia !== '' && $copia !== at_rg_email($i['destinos']['correo'])) {
        try {
            at_rg_mail_cerrado($copia, '[Copia] Invitación a reseña enviada · ' . at_rg_correo_nombre(['nombre' => $i['proyecto'] ?? ''], 255),
                at_rg_correo_html($i, $id, true), at_rg_correo_texto($i, $id, true));
        } catch (Throwable $e) { /* La copia interna nunca cambia la aceptación del cliente. */ }
    }
    return $aceptado;
}

/** Sin exponer la casilla completa en panel o copia interna. */
function at_rg_email_enmascarado(string $email): string {
    $email = at_rg_email($email);
    if ($email === '') { return ''; }
    [$local, $dominio] = explode('@', $email, 2);
    return substr($local, 0, 2) . '***@' . $dominio;
}
