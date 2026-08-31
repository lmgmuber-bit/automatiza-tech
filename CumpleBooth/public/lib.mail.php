<?php
declare(strict_types=1);

/**
 * lib.mail.php — envío de correo por SMTP autenticado.
 *
 * Por qué SMTP y no `mail()`. En un hosting compartido, `mail()` entrega el
 * mensaje a un sendmail local que sale con la identidad del servidor, no la del
 * dominio: el SPF de cumpleclick.com no lo autoriza, no hay firma DKIM, y el
 * correo cae en spam o se descarta sin dejar rastro. Hablando SMTP contra el
 * buzón del propio dominio, el mensaje sale autenticado como
 * `no-reply@cumpleclick.com` y el proveedor le pone su firma DKIM.
 *
 * Sin dependencias: el proyecto no usa Composer y traer PHPMailer para dos
 * correos transaccionales no se justifica. Lo que sigue es SMTP hablado a mano
 * sobre un socket, que son unas pocas órdenes.
 *
 * TRES cosas de este archivo son anti-spam, no capricho:
 *
 * 1. Todo mensaje va en `multipart/alternative` con una versión de texto plano
 *    de verdad. Un correo que es sólo HTML es una de las señales que más pesa
 *    en los filtros, y además es lo único que se ve en un reloj o en un cliente
 *    que bloquea HTML.
 * 2. El remitente del sobre (`MAIL FROM`) es el MISMO buzón que la cabecera
 *    `From`. Si no coinciden, el SPF se evalúa contra un dominio y el lector ve
 *    otro: eso es "desalineación" y DMARC lo penaliza.
 * 3. Cada mensaje lleva `Message-ID` con el dominio propio y `Date` en formato
 *    RFC. Sin ellos, varios filtros suman puntos de sospecha de una.
 */

/** Configuración del SMTP, ya normalizada. */
function cc_mail_config(): array
{
    $c = cb_config();
    return [
        'host' => trim((string) ($c['smtp_host'] ?? '')),
        'port' => (int) ($c['smtp_port'] ?? 587),
        'user' => trim((string) ($c['smtp_user'] ?? '')),
        'password' => (string) ($c['smtp_password'] ?? ''),
        // Desde qué buzón sale. Si no se declara, se usa el de la cuenta: es lo
        // único que el servidor va a aceptar sin más.
        'from' => trim((string) ($c['smtp_from'] ?? '')) ?: trim((string) ($c['smtp_user'] ?? '')),
        'from_name' => trim((string) ($c['smtp_from_name'] ?? '')) ?: 'CumpleClick',
        // A dónde llegan los avisos internos de solicitud nueva.
        'notify' => trim((string) ($c['leads_notify_email'] ?? '')),
        // A dónde contesta el cliente si responde el correo de confirmación.
        'reply_to' => trim((string) ($c['smtp_reply_to'] ?? '')),
        'timeout' => 12,
    ];
}

/** ¿Hay SMTP configurado? Sin esto, el formulario sigue guardando sin enviar. */
function cc_mail_enabled(): bool
{
    $c = cc_mail_config();
    return $c['host'] !== '' && $c['user'] !== '' && $c['password'] !== '' && $c['from'] !== '';
}

/**
 * Codifica una cabecera que puede llevar acentos (RFC 2047).
 * Sin esto, "Solicitud de Rodrigo Muñoz" llega con la ñ rota en varios clientes.
 */
function cc_mail_encode_header(string $texto): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $texto) === 1) {
        return $texto;
    }
    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

/** Un buzón con nombre, listo para una cabecera. */
function cc_mail_address(string $email, string $nombre = ''): string
{
    $email = trim($email);
    if ($nombre === '') {
        return $email;
    }
    return cc_mail_encode_header($nombre) . ' <' . $email . '>';
}

/** Corta el cuerpo en líneas de 76 caracteres, como pide el formato. */
function cc_mail_quoted_printable(string $texto): string
{
    return quoted_printable_encode(str_replace("\r\n", "\n", $texto));
}

/**
 * Lee una respuesta del servidor. SMTP puede contestar en varias líneas
 * ("250-ALGO" continúa, "250 ALGO" cierra), así que hay que leer hasta la que
 * tiene el espacio o el cliente se desincroniza y todo lo siguiente falla.
 */
function cc_smtp_leer($socket, int $timeout): array
{
    $lineas = [];
    do {
        $linea = fgets($socket, 1024);
        if ($linea === false) {
            return ['codigo' => 0, 'texto' => 'El servidor cortó la conexión.'];
        }
        $lineas[] = rtrim($linea, "\r\n");
        $continua = isset($linea[3]) && $linea[3] === '-';
    } while ($continua);

    $ultima = end($lineas);
    return ['codigo' => (int) substr((string) $ultima, 0, 3), 'texto' => implode(' | ', $lineas)];
}

function cc_smtp_escribir($socket, string $orden): void
{
    fwrite($socket, $orden . "\r\n");
}

/**
 * Envía un mensaje. Devuelve ['ok' => bool, 'error' => string].
 *
 * NUNCA lanza excepción hacia afuera: quien lo llama ya guardó el lead y no
 * puede fallarle al visitante porque un servidor de correo no conteste.
 *
 * @param array $m  ['to','to_name','subject','html','text','reply_to','headers']
 */
function cc_mail_send(array $m): array
{
    if (!cc_mail_enabled()) {
        return ['ok' => false, 'error' => 'SMTP no configurado.'];
    }
    $cfg = cc_mail_config();
    $para = trim((string) ($m['to'] ?? ''));
    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Destinatario inválido.'];
    }

    $seguroDirecto = $cfg['port'] === 465;               // 465 = TLS desde el saludo
    $destino = ($seguroDirecto ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . $cfg['port'];
    $contexto = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'SNI_enabled' => true,
    ]]);

    $socket = @stream_socket_client($destino, $errNo, $errStr, $cfg['timeout'],
        STREAM_CLIENT_CONNECT, $contexto);
    if (!$socket) {
        return ['ok' => false, 'error' => 'No se pudo conectar al SMTP: ' . $errStr];
    }
    stream_set_timeout($socket, $cfg['timeout']);

    $cerrar = function () use ($socket) {
        @cc_smtp_escribir($socket, 'QUIT');
        @fclose($socket);
    };

    try {
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 220) { $cerrar(); return ['ok' => false, 'error' => 'Saludo inesperado: ' . $r['texto']]; }

        // El nombre del EHLO debe parecer un host real; el dominio propio sirve.
        $ehlo = parse_url((string) cb_config('public_base_url'), PHP_URL_HOST) ?: 'cumpleclick.com';
        cc_smtp_escribir($socket, 'EHLO ' . $ehlo);
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 250) { $cerrar(); return ['ok' => false, 'error' => 'EHLO rechazado: ' . $r['texto']]; }

        if (!$seguroDirecto) {
            // Puerto 587: la conexión empieza en claro y se ASCIENDE a TLS. Si el
            // servidor no ofrece STARTTLS se aborta, en vez de mandar la
            // contraseña del buzón por un canal sin cifrar.
            if (stripos($r['texto'], 'STARTTLS') === false) {
                $cerrar();
                return ['ok' => false, 'error' => 'El servidor no ofrece STARTTLS; no se envía sin cifrar.'];
            }
            cc_smtp_escribir($socket, 'STARTTLS');
            $r = cc_smtp_leer($socket, $cfg['timeout']);
            if ($r['codigo'] !== 220) { $cerrar(); return ['ok' => false, 'error' => 'STARTTLS rechazado: ' . $r['texto']]; }

            $cripto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cripto !== true) { @fclose($socket); return ['ok' => false, 'error' => 'No se pudo negociar TLS.']; }

            // Tras TLS hay que volver a saludar: lo anterior se descarta.
            cc_smtp_escribir($socket, 'EHLO ' . $ehlo);
            $r = cc_smtp_leer($socket, $cfg['timeout']);
            if ($r['codigo'] !== 250) { $cerrar(); return ['ok' => false, 'error' => 'EHLO tras TLS rechazado: ' . $r['texto']]; }
        }

        cc_smtp_escribir($socket, 'AUTH LOGIN');
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 334) { $cerrar(); return ['ok' => false, 'error' => 'AUTH no aceptado: ' . $r['texto']]; }
        cc_smtp_escribir($socket, base64_encode($cfg['user']));
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 334) { $cerrar(); return ['ok' => false, 'error' => 'Usuario rechazado.']; }
        cc_smtp_escribir($socket, base64_encode($cfg['password']));
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 235) { $cerrar(); return ['ok' => false, 'error' => 'Credenciales SMTP rechazadas.']; }

        // Remitente del SOBRE igual al del encabezado: es lo que alinea SPF.
        cc_smtp_escribir($socket, 'MAIL FROM:<' . $cfg['from'] . '>');
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 250) { $cerrar(); return ['ok' => false, 'error' => 'MAIL FROM rechazado: ' . $r['texto']]; }

        cc_smtp_escribir($socket, 'RCPT TO:<' . $para . '>');
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 250 && $r['codigo'] !== 251) {
            $cerrar(); return ['ok' => false, 'error' => 'Destinatario rechazado: ' . $r['texto']];
        }

        cc_smtp_escribir($socket, 'DATA');
        $r = cc_smtp_leer($socket, $cfg['timeout']);
        if ($r['codigo'] !== 354) { $cerrar(); return ['ok' => false, 'error' => 'DATA rechazado: ' . $r['texto']]; }

        $cuerpo = cc_mail_build($m, $cfg);

        // "Dot stuffing": una línea que empieza con un punto marcaría el fin del
        // mensaje. Se duplica para que viaje como texto.
        $cuerpo = preg_replace('/^\./m', '..', $cuerpo);
        fwrite($socket, $cuerpo . "\r\n.\r\n");

        $r = cc_smtp_leer($socket, $cfg['timeout']);
        $cerrar();
        if ($r['codigo'] !== 250) {
            return ['ok' => false, 'error' => 'El servidor no aceptó el mensaje: ' . $r['texto']];
        }
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        @fclose($socket);
        return ['ok' => false, 'error' => 'Fallo inesperado enviando: ' . $e->getMessage()];
    }
}

/** Arma el mensaje completo: cabeceras + las dos versiones del cuerpo. */
function cc_mail_build(array $m, array $cfg): string
{
    $limite = 'cc' . bin2hex(random_bytes(12));
    $dominio = parse_url((string) cb_config('public_base_url'), PHP_URL_HOST) ?: 'cumpleclick.com';

    $replyTo = trim((string) ($m['reply_to'] ?? '')) ?: $cfg['reply_to'];

    $cabeceras = [
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
        'From: ' . cc_mail_address($cfg['from'], $cfg['from_name']),
        'To: ' . cc_mail_address((string) $m['to'], (string) ($m['to_name'] ?? '')),
        'Subject: ' . cc_mail_encode_header((string) ($m['subject'] ?? '')),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $limite . '"',
    ];
    if ($replyTo !== '') {
        $cabeceras[] = 'Reply-To: ' . $replyTo;
    }
    foreach ((array) ($m['headers'] ?? []) as $extra) {
        $cabeceras[] = $extra;
    }

    $texto = (string) ($m['text'] ?? '');
    $html = (string) ($m['html'] ?? '');

    // El orden importa: de menos a más rico. El cliente muestra la ÚLTIMA parte
    // que sabe representar, así que el texto plano va primero y el HTML después.
    $partes = [
        '--' . $limite,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        '',
        cc_mail_quoted_printable($texto),
        '--' . $limite,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        '',
        cc_mail_quoted_printable($html),
        '--' . $limite . '--',
    ];

    return implode("\r\n", $cabeceras) . "\r\n\r\n" . implode("\r\n", $partes);
}
