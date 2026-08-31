<?php
declare(strict_types=1);

/**
 * lib.mail-templates.php — los dos correos que salen del formulario público.
 *
 * Escritos con tablas y estilos EN LÍNEA a propósito. No es HTML antiguo por
 * descuido: Gmail y Outlook descartan el `<style>` del `<head>` y no entienden
 * flexbox ni grid, así que una maquetación moderna se desarma justo en los dos
 * clientes donde va a leerse casi todo. Las tablas anidadas con `style=` en
 * cada celda es lo único que se ve igual en todas partes.
 *
 * Sin imágenes remotas. La mayoría de los clientes bloquea las imágenes hasta
 * que el lector las autoriza, así que un encabezado que ES una imagen llega
 * como un rectángulo vacío en el primer contacto —justo el correo que tiene que
 * dar confianza—. La marca se arma con color y tipografía, que siempre se ven.
 * De paso evita el pixel de rastreo que algunos filtros penalizan.
 */

const CC_MAIL_TINTA   = '#4C2882';
const CC_MAIL_VIOLETA = '#8B5CF6';
const CC_MAIL_FUCSIA  = '#D6307F';
const CC_MAIL_CREMA   = '#FFF8EC';
const CC_MAIL_GRIS    = '#6B6280';

function cc_mail_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * El marco común: banda de marca, contenido y pie.
 *
 * El ancho fijo de 600px es la medida que entra sin recortes en el panel de
 * lectura de Outlook de escritorio; el `max-width:100%` de adentro es lo que la
 * deja encogerse en el teléfono.
 */
function cc_mail_shell(string $titulo, string $contenido, string $pieExtra = ''): string
{
    $t = cc_mail_h($titulo);
    return <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$t}</title></head>
<body style="margin:0;padding:0;background:#F3EFF7;">
<!-- Texto de vista previa: es lo que se lee en la lista de correos, al lado del
     asunto. Sin esto, ahí aparece el primer texto del cuerpo, que suele ser
     "CumpleClick" repetido. Se oculta con tamaño 0 y color del fondo. -->
<div style="display:none;max-height:0;overflow:hidden;font-size:0;line-height:0;color:#F3EFF7;">{$t}</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F3EFF7;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;background:#FFFFFF;border-radius:16px;overflow:hidden;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

    <tr><td style="background:#4C2882;padding:22px 28px;">
      <span style="font-size:22px;font-weight:700;color:#FFFFFF;letter-spacing:-0.3px;">Cumple</span><span style="font-size:22px;font-weight:700;color:#FBBF24;letter-spacing:-0.3px;">Click</span>
    </td></tr>

    <tr><td style="padding:28px;">{$contenido}</td></tr>

    <tr><td style="background:#FFF8EC;padding:20px 28px;border-top:1px solid #EDE4F5;">
      <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#6B6280;">
        CumpleClick — cabina de fotos temática para cumpleaños y eventos.
      </p>
      {$pieExtra}
      <p style="margin:8px 0 0;font-size:12px;line-height:1.5;color:#6B6280;">
        Un servicio de AutomatizaTech · <a href="https://cumpleclick.com" style="color:#8B5CF6;text-decoration:underline;">cumpleclick.com</a>
      </p>
    </td></tr>

  </table>
</td></tr>
</table>
</body></html>
HTML;
}

/** Una fila de la tabla de datos, para no repetir el mismo `style=` diez veces. */
function cc_mail_fila(string $etiqueta, string $valor): string
{
    if (trim($valor) === '') {
        return '';
    }
    $e = cc_mail_h($etiqueta);
    $v = nl2br(cc_mail_h($valor));
    return '<tr>'
        . '<td style="padding:7px 12px 7px 0;font-size:14px;color:#6B6280;vertical-align:top;white-space:nowrap;">' . $e . '</td>'
        . '<td style="padding:7px 0;font-size:14px;color:#2C2140;vertical-align:top;">' . $v . '</td>'
        . '</tr>';
}

/**
 * Correo al cliente: confirmación de que la solicitud llegó.
 *
 * El asunto NO lleva signos de exclamación, mayúsculas sostenidas ni palabras
 * como "gratis" o "promoción": son las señales clásicas que suben el puntaje de
 * spam en un correo que además viene de un dominio nuevo, sin reputación
 * acumulada todavía.
 */
function cc_mail_confirmacion(array $lead): array
{
    $nombre = trim((string) ($lead['name'] ?? ''));
    $primerNombre = $nombre !== '' ? explode(' ', $nombre)[0] : '';
    $saludo = $primerNombre !== '' ? 'Hola ' . cc_mail_h($primerNombre) : 'Hola';
    $ref = cc_mail_h((string) ($lead['public_ref'] ?? ''));

    $fecha = trim((string) ($lead['event_date'] ?? ''));
    if ($fecha !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $fecha = $d ? $d->format('d-m-Y') : $fecha;
    }

    $resumen = cc_mail_fila('Tipo de evento', (string) ($lead['event_type'] ?? ''))
        . cc_mail_fila('Fecha', $fecha)
        . cc_mail_fila('Comuna o ciudad', (string) ($lead['commune'] ?? ''))
        . cc_mail_fila('Teléfono', (string) ($lead['phone'] ?? ''));

    $contenido = <<<HTML
<p style="margin:0 0 14px;font-size:17px;line-height:1.5;color:#2C2140;">{$saludo}, recibimos tu solicitud.</p>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#4A4160;">
  Gracias por escribirnos. Vamos a revisar la disponibilidad para tu fecha y
  <strong style="color:#4C2882;">nos pondremos en contacto contigo a la brevedad posible</strong>
  para contarte cómo funciona y darte el valor exacto.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#FFF8EC;border-radius:12px;padding:16px 18px;margin:0 0 20px;">
  <tr><td>
    <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#4C2882;text-transform:uppercase;letter-spacing:0.4px;">Lo que nos enviaste</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">{$resumen}</table>
  </td></tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4A4160;">
  Tu número de solicitud es <strong style="color:#4C2882;">{$ref}</strong>. Guárdalo por si quieres consultarnos algo antes de que te contactemos.
</p>
<p style="margin:0;font-size:14px;line-height:1.6;color:#6B6280;">
  Si tu evento es pronto y prefieres avanzar ahora, respóndenos este correo y le damos prioridad.
</p>
HTML;

    $texto = <<<TXT
{$saludo}, recibimos tu solicitud.

Gracias por escribirnos. Vamos a revisar la disponibilidad para tu fecha y nos
pondremos en contacto contigo a la brevedad posible para contarte cómo funciona
y darte el valor exacto.

LO QUE NOS ENVIASTE
Tipo de evento: {$lead['event_type']}
Fecha: {$fecha}
Comuna o ciudad: {$lead['commune']}
Teléfono: {$lead['phone']}

Tu número de solicitud es {$ref}. Guárdalo por si quieres consultarnos algo
antes de que te contactemos.

Si tu evento es pronto y prefieres avanzar ahora, responde este correo y le
damos prioridad.

--
CumpleClick — cabina de fotos temática para cumpleaños y eventos.
Un servicio de AutomatizaTech · https://cumpleclick.com
TXT;

    return [
        'subject' => 'Recibimos tu solicitud · CumpleClick (' . (string) ($lead['public_ref'] ?? '') . ')',
        'html' => cc_mail_shell('Recibimos tu solicitud y te contactamos a la brevedad', $contenido),
        'text' => $texto,
        // Marca el correo como respuesta automática. Sirve para que un
        // autorespondedor del otro lado no conteste y se arme un ida y vuelta
        // infinito entre dos máquinas.
        'headers' => ['Auto-Submitted: auto-replied'],
    ];
}

/** Aviso interno: llegó una solicitud nueva. */
function cc_mail_aviso_interno(array $lead, string $urlAdmin = ''): array
{
    $ref = cc_mail_h((string) ($lead['public_ref'] ?? ''));
    $nombre = cc_mail_h((string) ($lead['name'] ?? ''));
    $email = (string) ($lead['email'] ?? '');
    $telefono = (string) ($lead['phone'] ?? '');

    $fecha = trim((string) ($lead['event_date'] ?? ''));
    if ($fecha !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $fecha = $d ? $d->format('d-m-Y') : $fecha;
    }

    // El teléfono en E.164 sirve tal cual para abrir el chat.
    $wa = preg_replace('/[^0-9]/', '', $telefono);
    $filas = cc_mail_fila('Nombre', (string) ($lead['name'] ?? ''))
        . cc_mail_fila('Organización', (string) ($lead['organization'] ?? ''))
        . cc_mail_fila('Correo', $email)
        . cc_mail_fila('Teléfono', $telefono)
        . cc_mail_fila('Tipo de evento', (string) ($lead['event_type'] ?? ''))
        . cc_mail_fila('Fecha', $fecha)
        . cc_mail_fila('Comuna o ciudad', (string) ($lead['commune'] ?? ''))
        . cc_mail_fila('Mensaje', (string) ($lead['message'] ?? ''));

    $botones = '<a href="https://wa.me/' . cc_mail_h((string) $wa) . '" style="display:inline-block;background:#D6307F;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;padding:11px 18px;border-radius:999px;margin:0 8px 8px 0;">Responder por WhatsApp</a>'
        . '<a href="mailto:' . cc_mail_h($email) . '" style="display:inline-block;background:#FFFFFF;color:#4C2882;border:1px solid #C9B8E8;font-size:14px;font-weight:700;text-decoration:none;padding:10px 18px;border-radius:999px;margin:0 8px 8px 0;">Responder por correo</a>';
    if ($urlAdmin !== '') {
        $botones .= '<a href="' . cc_mail_h($urlAdmin) . '" style="display:inline-block;background:#FFFFFF;color:#4C2882;border:1px solid #C9B8E8;font-size:14px;font-weight:700;text-decoration:none;padding:10px 18px;border-radius:999px;margin:0 8px 8px 0;">Ver en el admin</a>';
    }

    $contenido = <<<HTML
<p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#D6307F;text-transform:uppercase;letter-spacing:0.4px;">Solicitud nueva</p>
<p style="margin:0 0 18px;font-size:19px;line-height:1.4;color:#2C2140;font-weight:700;">{$nombre} · {$ref}</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">{$filas}</table>
<div>{$botones}</div>
HTML;

    $texto = "SOLICITUD NUEVA · {$ref}\n\n"
        . "Nombre: {$lead['name']}\n"
        . (trim((string) ($lead['organization'] ?? '')) !== '' ? "Organización: {$lead['organization']}\n" : '')
        . "Correo: {$email}\n"
        . "Teléfono: {$telefono}\n"
        . "Tipo de evento: {$lead['event_type']}\n"
        . ($fecha !== '' ? "Fecha: {$fecha}\n" : '')
        . "Comuna o ciudad: {$lead['commune']}\n\n"
        . "Mensaje:\n{$lead['message']}\n\n"
        . ($urlAdmin !== '' ? "Ver en el admin: {$urlAdmin}\n" : '');

    return [
        'subject' => 'Solicitud nueva: ' . (string) ($lead['name'] ?? '') . ' · ' . (string) ($lead['event_type'] ?? ''),
        'html' => cc_mail_shell('Solicitud nueva desde el sitio', $contenido),
        'text' => $texto,
        // Responder al aviso escribe DIRECTO al cliente, que es lo que uno
        // quiere hacer al leerlo.
        'reply_to' => $email,
    ];
}
