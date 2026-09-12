<?php
/**
 * Los correos de una fiesta: quién los manda, qué quedó registrado y cómo reenviarlos.
 *
 * Antes cada correo se armaba y se mandaba dentro de la pantalla del admin que lo disparaba,
 * y no quedaba rastro. Eso tenía dos consecuencias molestas: al día siguiente no había forma
 * de saber si el manual ya se había enviado, y para reenviar había que reconstruir a mano el
 * contexto de cada pantalla. Acá los cuatro envíos quedan detrás de la misma puerta, con la
 * misma firma y el mismo registro.
 *
 * No se duplica el contenido de ningún correo: el del manual sale de `cb_manual_correo()`, el
 * de la boleta de `cb_comprobante_correo()` y el de Términos firmados de la misma función que
 * lo manda automáticamente al firmar. El único que vive acá es el de la firma, que estaba
 * escrito dentro de `admin/aceptaciones.php` y se movió entero, sin cambiarle una coma.
 *
 * Sobre los reenvíos y los enlaces: los dos tokens del flujo de Términos se guardan hasheados,
 * a propósito, para que una filtración de la base no permita firmar ni descargar el
 * comprobante de nadie. El precio de esa decisión es que el enlace en claro existe una sola
 * vez, así que **reenviar significa emitir uno nuevo y anular el anterior**. Es el mismo trato
 * que hace cualquier recuperación de contraseña, y las funciones de acá lo dicen en el
 * resultado para que la pantalla pueda avisarlo antes de que el usuario apriete.
 */

require_once __DIR__ . '/lib.php';

/**
 * Los cuatro correos, en el orden en que ocurren de verdad en una fiesta.
 *
 * `rota_enlace` marca los que, al reenviarse, dejan muerto el enlace anterior.
 */
function cb_envio_tipos(): array
{
    return [
        'firma' => [
            'etiqueta' => 'Firma de Términos',
            'descripcion' => 'El enlace personal para que quien contrató firme.',
            'pdf' => false,
            'rota_enlace' => true,
        ],
        'manual' => [
            'etiqueta' => 'Manual de la fiesta',
            'descripcion' => 'Qué va a pasar, qué necesitamos y sus enlaces.',
            'pdf' => true,
            'rota_enlace' => false,
        ],
        'boleta' => [
            'etiqueta' => 'Comprobante de pago',
            'descripcion' => 'El detalle de lo contratado y lo pagado.',
            'pdf' => true,
            'rota_enlace' => false,
        ],
        'terminos' => [
            'etiqueta' => 'Términos firmados',
            'descripcion' => 'La copia del contrato firmado. Sale sola al firmar.',
            'pdf' => true,
            'rota_enlace' => true,
        ],
    ];
}

/**
 * Deja constancia de un envío. También se registran los fallidos: cuando el cliente dice que no
 * le llegó, lo primero que uno quiere saber es si salió y no si se ve bonito el mensaje.
 *
 * Nunca lanza: un problema al escribir la bitácora no puede impedir que el correo se mande ni
 * romper la pantalla del admin. Si la tabla todavía no existe (migración sin correr), se
 * anota en el log del servidor y se sigue.
 */
function cb_envio_registrar(string $slug, string $tipo, string $destinatario, bool $conPdf,
                            bool $ok, string $detalle = '', array $opciones = [],
                            string $por = 'admin'): void
{
    try {
        cb_pdo()->prepare(
            'INSERT INTO cc_envios (party_slug, tipo, destinatario, con_pdf, ok, detalle, opciones, enviado_por, enviado_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $slug, $tipo, $destinatario, $conPdf ? 1 : 0, $ok ? 1 : 0,
            mb_substr($detalle, 0, 250),
            $opciones ? json_encode($opciones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $por, gmdate('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('CumpleClick bitacora de envios: ' . $e->getMessage());
    }
}

/**
 * Estado de los cuatro correos de una fiesta: el último de cada tipo y cuántas veces se mandó.
 *
 * Devuelve siempre las cuatro entradas, aunque no se haya mandado ninguna, para que la
 * pantalla no tenga que decidir qué mostrar cuando falta un tipo.
 *
 * @return array<string,array{etiqueta:string,descripcion:string,pdf:bool,rota_enlace:bool,
 *                            ultimo:?array,veces:int,fallidos:int}>
 */
function cb_envios_de_fiesta(string $slug): array
{
    $estado = [];
    foreach (cb_envio_tipos() as $tipo => $meta) {
        $estado[$tipo] = $meta + ['ultimo' => null, 'veces' => 0, 'fallidos' => 0];
    }
    try {
        $stmt = cb_pdo()->prepare('SELECT * FROM cc_envios WHERE party_slug = ? ORDER BY enviado_at ASC, id ASC');
        $stmt->execute([$slug]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $tipo = (string) $fila['tipo'];
            if (!isset($estado[$tipo])) { continue; }
            if ((int) $fila['ok'] === 1) {
                // Solo un envío que salió cuenta como "enviado": si el último intento falló,
                // lo que importa mostrar es el último que sí llegó, más el aviso del fallo.
                $estado[$tipo]['ultimo'] = $fila;
                $estado[$tipo]['veces']++;
            } else {
                $estado[$tipo]['fallidos']++;
            }
        }
    } catch (Throwable $e) {
        error_log('CumpleClick bitacora de envios: ' . $e->getMessage());
    }
    return $estado;
}

/** Las opciones guardadas del último envío de un tipo, para que el reenvío repita lo mismo. */
function cb_envio_opciones_previas(array $estado, string $tipo): array
{
    $crudo = $estado[$tipo]['ultimo']['opciones'] ?? null;
    $datos = is_string($crudo) ? json_decode($crudo, true) : null;
    return is_array($datos) ? $datos : [];
}

// ── Mensajes listos para copiar o mandar por WhatsApp ──────────────────────

/**
 * El mismo contenido del correo, en un mensaje corto para pegar en WhatsApp.
 *
 * Solo existe para los dos que tienen un enlace **estable**: el manual y el comprobante van
 * firmados con HMAC y su dirección es siempre la misma, así que se pueden compartir por donde
 * sea. Los otros dos no: el enlace de firma y el de descarga del comprobante de Términos se
 * guardan hasheados y existen en claro una sola vez, así que no hay nada que copiar acá sin
 * emitir uno nuevo y matar el anterior. Para esos, el enlace se copia en Aceptaciones, en el
 * momento en que se genera.
 *
 * Devuelve '' cuando el tipo no tiene mensaje o la fiesta no da los datos.
 */
function cb_envio_texto_whatsapp(string $slug, string $tipo): string
{
    if ($tipo === 'boleta') {
        require_once __DIR__ . '/lib.comprobante.php';
        $d = cb_comprobante_datos($slug);
        return $d === null ? '' : cb_comprobante_texto_whatsapp($d, cb_comprobante_url($slug));
    }
    if ($tipo !== 'manual') {
        return '';
    }
    require_once __DIR__ . '/lib.manual.php';
    $d = cb_manual_datos($slug, ['pin' => '']);
    if ($d === null) {
        return '';
    }
    $quien = trim((string) ($d['contacto']['nombre'] ?? ''));
    $saludo = $quien !== '' ? 'Hola ' . explode(' ', $quien)[0] . '!' : '¡Hola!';
    $cuando = $d['fecha'] !== '' ? ', ' . $d['fecha_larga'] : '';

    return $saludo . ' Te dejamos el manual de la fiesta de ' . $d['nombre']
        . ' (' . $d['tema_nombre'] . $cuando . '): qué va a pasar, qué necesitamos el día del '
        . "evento y todos tus enlaces.\n\n" . $d['enlaces']['manual'] . "\n\n"
        . 'También te llegó por correo con el PDF adjunto. Cualquier duda nos escribes por acá. '
        . '— CumpleClick';
}

// ── Los cuatro envíos ──────────────────────────────────────────────────────

/**
 * Manual de la fiesta, con el PDF adjunto, al contacto principal de la ficha.
 *
 * `$opciones` trae `pin` y `invitacion`: son datos que la ficha no conoce (el token de la
 * invitación se guarda hasheado y no se puede reconstruir). Se registran junto al envío
 * justamente para que el reenvío no salga sin la invitación que el primero sí llevaba.
 *
 * @return array{ok:bool,mensaje:string,para?:string}
 */
function cb_envio_manual(string $slug, array $opciones = []): array
{
    require_once __DIR__ . '/lib.manual.php';
    require_once __DIR__ . '/lib.mail.php';

    $opciones = [
        'pin' => trim((string) ($opciones['pin'] ?? '')) ?: '1234',
        'invitacion' => trim((string) ($opciones['invitacion'] ?? '')),
    ];
    $datos = cb_manual_datos($slug, $opciones);
    if ($datos === null) {
        return ['ok' => false, 'mensaje' => 'La fiesta no existe.'];
    }
    $para = (string) ($datos['contacto']['email'] ?? '');
    if ($para === '') {
        return ['ok' => false, 'mensaje' => 'La ficha no tiene un contacto con correo: carga uno antes de mandar el manual.'];
    }
    $correo = cb_manual_correo($datos);
    $envio = cc_mail_send([
        'to' => $para,
        'subject' => $correo['subject'],
        'text' => $correo['text'],
        'html' => $correo['html'],
        'attachments' => [[
            'filename' => $correo['filename'],
            'type' => 'application/pdf',
            'data' => cb_manual_pdf($datos),
        ]],
    ]);
    $ok = !empty($envio['ok']);
    cb_envio_registrar($slug, 'manual', $para, true, $ok,
        $ok ? '' : (string) ($envio['error'] ?? 'sin detalle'), $opciones);
    return $ok
        ? ['ok' => true, 'mensaje' => 'Manual enviado a ' . $para . ', con el PDF adjunto.', 'para' => $para]
        : ['ok' => false, 'mensaje' => 'No se pudo enviar el manual: ' . (string) ($envio['error'] ?? 'sin detalle')];
}

/**
 * Comprobante de pago con el PDF adjunto. Sin destinatarios explícitos va a todos los correos
 * de la ficha, que es lo que hace el reenvío desde el panel; la pantalla del comprobante sigue
 * pudiendo elegir a cuál.
 *
 * @param string[] $destinos
 * @return array{ok:bool,mensaje:string}
 */
function cb_envio_boleta(string $slug, array $destinos = []): array
{
    require_once __DIR__ . '/lib.comprobante.php';
    require_once __DIR__ . '/lib.cliente.php';
    require_once __DIR__ . '/lib.mail.php';

    $datos = cb_comprobante_datos($slug);
    if ($datos === null) {
        return ['ok' => false, 'mensaje' => 'Esta fiesta todavía no tiene el cobro cargado.'];
    }
    // Los correos válidos son los de la ficha y nada más: un POST armado a mano no puede
    // usar el admin para mandarle el comprobante de un cliente a cualquier dirección.
    $permitidos = cb_party_contact_emails($slug);
    $destinos = $destinos ? array_values(array_intersect($destinos, $permitidos)) : $permitidos;
    if (!$destinos) {
        return ['ok' => false, 'mensaje' => 'La ficha no tiene ningún correo cargado.'];
    }
    $pdf = cb_comprobante_pdf($datos);
    $archivo = cb_comprobante_nombre_archivo($datos);
    $correo = cb_comprobante_correo($datos, cb_comprobante_url($slug));

    $enviados = [];
    $fallaron = [];
    foreach ($destinos as $destino) {
        $envio = cc_mail_send([
            'to' => $destino,
            'subject' => $correo['subject'],
            'text' => $correo['text'],
            'html' => $correo['html'],
            'attachments' => [['filename' => $archivo, 'type' => 'application/pdf', 'data' => $pdf]],
        ]);
        $ok = !empty($envio['ok']);
        cb_envio_registrar($slug, 'boleta', $destino, true, $ok, $ok ? '' : (string) ($envio['error'] ?? 'sin detalle'));
        if ($ok) { $enviados[] = $destino; } else { $fallaron[] = $destino; }
    }
    if (!$enviados) {
        return ['ok' => false, 'mensaje' => 'No se pudo enviar el comprobante a ' . implode(', ', $fallaron) . '.'];
    }
    return ['ok' => true, 'mensaje' => 'Comprobante enviado a ' . implode(', ', $enviados) . ', con el PDF adjunto.'
        . ($fallaron ? ' No salió a ' . implode(', ', $fallaron) . '.' : '')];
}

/**
 * El correo con el enlace de firma. Vive acá y no en la pantalla porque ahora lo usan dos:
 * Aceptaciones, al generar el enlace, y el panel de la ficha, al reenviarlo.
 *
 * @return array{subject:string,text:string,html:string}
 */
function cb_envio_firma_correo(array $row, array $party, string $url): array
{
    require_once __DIR__ . '/lib.mail-templates.php';
    require_once __DIR__ . '/lib.manual.php';   // por cb_manual_fecha_larga()

    $nombrePila = explode(' ', trim((string) $row['client_name']))[0] ?: 'Hola';
    $summary = is_array($row['plan_summary'] ?? null) ? $row['plan_summary'] : [];
    $festejado = (string) (($party['birthday_person_name'] ?? '') ?: ($party['nombre'] ?? ''));
    $vence = (string) ($row['expires_at'] ?? '');
    $filas = cc_mail_fila('Fiesta', cc_mail_h($festejado . (!empty($party['fecha']) ? ' · ' . cb_manual_fecha_larga((string) $party['fecha']) : '')));
    if (($summary['plan_name'] ?? '') !== '') { $filas .= cc_mail_fila('Plan', cc_mail_h((string) $summary['plan_name'])); }
    $html = '<p style="margin:0 0 16px">Hola ' . cc_mail_h($nombrePila) . ', gracias por elegirnos para el cumpleaños de <strong>'
        . cc_mail_h($festejado) . '</strong>. Ya tenemos todo reservado.</p>'
        . '<p style="margin:0 0 16px">Antes de la fiesta hay un solo paso: <strong>firmar los Términos y Condiciones</strong>. '
        . 'Son dos minutos: se revisa el resumen del plan, se marcan tres casillas y se firma con el dedo en la pantalla.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 18px">' . $filas . '</table>'
        . '<p style="margin:0 0 8px"><a href="' . cc_mail_h($url) . '" style="display:inline-block;background:#7C3AED;color:#ffffff;'
        . 'text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px">Firmar los Términos</a></p>'
        . '<p style="margin:0 0 16px;font-size:13px;color:#6B6280">El enlace es personal y de un solo uso'
        . ($vence !== '' ? '; vence el ' . cc_mail_h(cb_chile_datetime($vence)) : '') . '. Si se pierde, pídenos otro.</p>'
        . '<p style="margin:0">Después de firmar te llega el comprobante, y por separado el manual de la fiesta con todo lo que necesitamos el día del evento.</p>';
    $texto = "Hola $nombrePila, gracias por elegirnos para el cumpleaños de $festejado. Ya tenemos todo reservado.\n\n"
        . "Antes de la fiesta hay un solo paso: firmar los Términos y Condiciones. Son dos minutos.\n\n$url\n\n"
        . "El enlace es personal y de un solo uso. Si se pierde, pídenos otro.\n";

    return [
        'subject' => 'Firma los Términos de tu fiesta · CumpleClick',
        'text' => $texto,
        'html' => cc_mail_shell('Firma los Términos', $html),
    ];
}

/** Manda el correo de firma de una fila pendiente y lo registra. */
function cb_envio_firma(array $row, array $party, string $url): array
{
    require_once __DIR__ . '/lib.acceptance.php';

    $para = (string) $row['client_email'];
    $correo = cb_envio_firma_correo($row, $party, $url);
    $ok = cb_send_mail($para, $correo['subject'], $correo['text'], $correo['html']);
    cb_envio_registrar((string) $row['party_public_slug'], 'firma', $para, false, $ok,
        $ok ? '' : 'el servidor de correo rechazó el envío');
    return $ok
        ? ['ok' => true, 'mensaje' => 'Enlace de firma enviado a ' . $para . '.']
        : ['ok' => false, 'mensaje' => 'No se pudo enviar el correo a ' . $para . '. Revisa la configuración de correo.'];
}

/**
 * Reenvía el enlace de firma de una fiesta.
 *
 * 🔴 Emite un enlace nuevo y **anula el anterior**. No es un capricho: el token se guarda
 * hasheado, así que el enlace en claro dejó de existir en cuanto se mostró. Rotarlo es la
 * única forma de volver a mandarlo, y es además lo que uno quiere si el correo anterior se
 * fue a la casilla equivocada.
 */
function cb_envio_reenviar_firma(string $slug): array
{
    require_once __DIR__ . '/lib.acceptance.php';

    $row = cb_acceptance_pendiente_de_fiesta($slug);
    if ($row === null) {
        return ['ok' => false, 'mensaje' => 'No hay un enlace de firma pendiente en esta fiesta. Genera uno desde Aceptaciones.'];
    }
    $url = cb_acceptance_rotar_token((int) $row['id']);
    if ($url === null) {
        return ['ok' => false, 'mensaje' => 'No se pudo emitir un enlace nuevo.'];
    }
    $party = cb_load_parties()['parties'][$slug] ?? [];
    $resultado = cb_envio_firma(cb_load_acceptance_by_id((int) $row['id']) ?? $row, $party, $url);
    if ($resultado['ok']) {
        $resultado['mensaje'] .= ' El enlace anterior quedó anulado.';
    }
    return $resultado;
}

/**
 * Reenvía a quien firmó su copia del contrato, con el PDF adjunto.
 *
 * Igual que la firma, el enlace de descarga se guarda hasheado y hay que emitir uno nuevo; el
 * anterior deja de funcionar. Por eso el correo lleva el PDF adjunto: aunque quien firmó tenga
 * guardado el enlace viejo, el documento le llega igual.
 */
function cb_envio_reenviar_terminos(string $slug): array
{
    require_once __DIR__ . '/lib.acceptance.php';

    $row = cb_acceptance_firmada_de_fiesta($slug);
    if ($row === null) {
        return ['ok' => false, 'mensaje' => 'Esta fiesta todavía no tiene los Términos firmados.'];
    }
    $token = cb_acceptance_rotar_receipt_token((int) $row['id']);
    if ($token === null) {
        return ['ok' => false, 'mensaje' => 'No se pudo emitir un enlace nuevo del comprobante.'];
    }
    $fila = cb_load_acceptance_by_id((int) $row['id']) ?? $row;
    $para = (string) ($fila['signer_email'] ?: $fila['client_email']);
    $enviado = cb_acceptance_send_notifications($fila, $token, true);
    $ok = !empty($enviado['client']);
    cb_envio_registrar($slug, 'terminos', $para, true, $ok, $ok ? '' : 'el servidor de correo rechazó el envío');
    return $ok
        ? ['ok' => true, 'mensaje' => 'Comprobante de Términos reenviado a ' . $para . ', con el PDF adjunto. El enlace de descarga anterior quedó anulado.']
        : ['ok' => false, 'mensaje' => 'No se pudo reenviar el comprobante a ' . $para . '.'];
}
