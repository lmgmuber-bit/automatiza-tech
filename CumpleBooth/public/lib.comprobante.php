<?php
/**
 * Comprobante de pago del servicio, en PDF.
 *
 * Es un documento INTERNO: deja constancia de lo cobrado y lo pagado entre CumpleClick y
 * quien contrató. **No es una boleta electrónica del SII** y el propio PDF lo dice, porque
 * entregar algo que parezca un documento tributario sin serlo es un problema de verdad para
 * el cliente y para nosotros. La integración con el SII es una etapa aparte, ya conversada.
 *
 * Los montos salen de `cb_party_billing()` (precio, descuento y anticipo) y los datos de
 * quien contrató de `cb_party_contacts()`. Nada se guarda de nuevo acá: el comprobante se
 * arma en el momento, así que siempre refleja lo que está cargado en la ficha de la fiesta.
 */
require_once __DIR__ . '/lib.pdf.php';
require_once __DIR__ . '/lib.cliente.php';

/**
 * Datos de la empresa que emite. Van acá y no en `data/marca.json` porque ese archivo lo
 * edita el admin y son datos legales: si alguien los cambia sin querer, el comprobante sale
 * mal. Si cambian de verdad, se cambian acá.
 */
function cb_comprobante_emisor(): array
{
    return [
        'razon_social' => 'AUTOMATIZATECH SpA',
        'rut' => '78.363.717-0',
        'marca' => 'CumpleClick',
    ];
}

/**
 * Folio estable y sin tabla nueva: mismo comprobante para la misma fiesta y el mismo día.
 * No pretende ser correlativo —un correlativo de verdad es cosa de la boleta del SII—, solo
 * darle un identificador legible a este documento.
 */
function cb_comprobante_folio(int $partyId, string $fechaEmision): string
{
    return sprintf('CC-%04d-%s', $partyId, str_replace('-', '', substr($fechaEmision, 0, 10)));
}

/**
 * Junta todo lo que va en el comprobante. Devuelve `null` si la fiesta no tiene precio
 * cargado: sin precio no hay nada que comprobar y es mejor decirlo que emitir un papel en
 * blanco.
 */
function cb_comprobante_datos(string $slug): ?array
{
    $party = cb_load_party_raw($slug);
    $partyId = cb_party_db_id($slug);
    if (!$party || $partyId === null) {
        return null;
    }
    $cobro = cb_party_billing($slug);
    if ($cobro['price_total'] === null) {
        return null;
    }
    $contactos = cb_party_contacts($slug);
    $principal = $contactos[0] ?? null;
    $relaciones = cb_contact_relationships();

    $marca = [];
    $rutaMarca = __DIR__ . '/data/marca.json';
    if (is_file($rutaMarca)) {
        $crudo = json_decode((string) @file_get_contents($rutaMarca), true);
        $marca = is_array($crudo) ? $crudo : [];
    }

    $temaSlug = (string) ($party['tema'] ?? '');
    $emision = gmdate('Y-m-d');

    return [
        'folio' => cb_comprobante_folio($partyId, $emision),
        'emision' => $emision,
        'emisor' => cb_comprobante_emisor(),
        'fiesta' => [
            'slug' => $slug,
            'nombre' => (string) ($party['nombre'] ?? ''),
            'fecha' => (string) ($party['fecha'] ?? ''),
            'tema' => cb_theme_public_name($temaSlug),
        ],
        'cliente' => $principal ? [
            'nombre' => $principal['name'],
            'email' => $principal['email'],
            'telefono' => (string) ($principal['phone'] ?? ''),
            'relacion' => $relaciones[$principal['relationship']] ?? '',
        ] : null,
        'otros_contactos' => array_slice($contactos, 1),
        'cobro' => $cobro,
        'marca' => $marca,
    ];
}

/** "20%" o "12,5%": sin decimales cuando son cero, y con coma, como se escribe en Chile. */
function cb_comprobante_porcentaje(float $pct): string
{
    $texto = rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',');
    return ($texto === '' ? '0' : $texto) . '%';
}

/** Fecha larga en español; `strftime` está obsoleto y `IntlDateFormatter` no siempre está. */
function cb_comprobante_fecha_larga(string $iso): string
{
    $meses = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
              '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
              '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
        return $iso;
    }
    return ((int) $m[3]) . ' de ' . ($meses[$m[2]] ?? $m[2]) . ' de ' . $m[1];
}

/** Arma el PDF y devuelve sus bytes. Una hoja carta; el detalle de una fiesta cabe de sobra. */
function cb_comprobante_pdf(array $d): string
{
    $TINTA = [36, 20, 54];
    $GRIS = [110, 104, 128];
    $LINEA = [222, 217, 230];
    $ACENTO = [139, 92, 246];

    $pdf = new CcPdf(215.9, 279.4);          // carta: es el papel que hay en Chile
    $izq = 18.0;
    $der = 215.9 - 18.0;
    $ancho = $der - $izq;

    // ── Cabecera ────────────────────────────────────────────────────────────
    $alto = $pdf->imagenJpeg(__DIR__ . '/brand/pdf-cumpleclick.jpg', $izq, 16, 46);
    $pdf->texto($der, 20, 'COMPROBANTE DE PAGO', 13, true, $TINTA, 'der');
    $pdf->texto($der, 26, 'N° ' . $d['folio'], 10, false, $GRIS, 'der');
    $pdf->texto($der, 31, 'Emitido el ' . cb_comprobante_fecha_larga($d['emision']), 10, false, $GRIS, 'der');
    $y = max(16 + $alto, 34) + 6;
    $pdf->linea($izq, $y, $der, $y, 0.6, $ACENTO);
    $y += 9;

    // ── Quién emite y para quién ────────────────────────────────────────────
    $col2 = $izq + $ancho / 2 + 4;
    $pdf->texto($izq, $y, 'EMITE', 8, true, $GRIS);
    $pdf->texto($col2, $y, 'CLIENTE', 8, true, $GRIS);
    $y += 6;
    $pdf->texto($izq, $y, $d['emisor']['razon_social'], 11, true, $TINTA);
    $pdf->texto($col2, $y, $d['cliente']['nombre'] ?? 'Sin contacto cargado', 11, true, $TINTA);
    $y += 5.4;
    $pdf->texto($izq, $y, 'RUT ' . $d['emisor']['rut'], 9.5, false, $TINTA);
    if (!empty($d['cliente']['relacion'])) {
        $pdf->texto($col2, $y, $d['cliente']['relacion'] . ' de ' . $d['fiesta']['nombre'], 9.5, false, $TINTA);
    }
    $y += 5.4;
    $pdf->texto($izq, $y, 'Marca: ' . $d['emisor']['marca'], 9.5, false, $TINTA);
    if (!empty($d['cliente']['email'])) {
        $pdf->texto($col2, $y, $d['cliente']['email'], 9.5, false, $TINTA);
    }
    $y += 5.4;
    if (!empty($d['marca']['correo'])) {
        $pdf->texto($izq, $y, (string) $d['marca']['correo'], 9.5, false, $TINTA);
    }
    if (!empty($d['cliente']['telefono'])) {
        $pdf->texto($col2, $y, (string) $d['cliente']['telefono'], 9.5, false, $TINTA);
    }
    $y += 5.4;
    if (!empty($d['marca']['web'])) {
        $pdf->texto($izq, $y, (string) $d['marca']['web'], 9.5, false, $TINTA);
    }
    $y += 12;

    // ── El servicio ─────────────────────────────────────────────────────────
    $pdf->rectangulo($izq, $y - 5, $ancho, 8, [246, 243, 250]);
    $pdf->texto($izq + 3, $y, 'DETALLE DEL SERVICIO', 8, true, $GRIS);
    $pdf->texto($der - 3, $y, 'MONTO', 8, true, $GRIS, 'der');
    $y += 9;

    $fiesta = 'Fiesta de ' . $d['fiesta']['nombre'];
    if ($d['fiesta']['tema'] !== '') {
        $fiesta .= ' · ' . $d['fiesta']['tema'];
    }
    $pdf->texto($izq, $y, 'Servicio CumpleClick', 10.5, true, $TINTA);
    $pdf->texto($der, $y, cb_format_clp($d['cobro']['price_total']), 10.5, false, $TINTA, 'der');
    $y += 5;
    $detalle = $fiesta;
    if ($d['fiesta']['fecha'] !== '') {
        $detalle .= ' · ' . cb_comprobante_fecha_larga($d['fiesta']['fecha']);
    }
    $y = $pdf->parrafo($izq, $y, $ancho - 40, $detalle, 9.5, false, $GRIS);
    $y = $pdf->parrafo($izq, $y, $ancho - 40,
        'Incluye cabina de fotos con marco de la temática, galería de fotos para los invitados, '
        . 'Álbum Recuerdo y juego 3D de la fiesta.', 9.5, false, $GRIS);
    $y += 4;

    if (!empty($d['cobro']['discount_amount'])) {
        $pdf->linea($izq, $y, $der, $y, 0.2, $LINEA);
        $y += 6;
        // El porcentaje va en la etiqueta cuando existe: es como se acordó el descuento y
        // deja explicado de dónde sale el monto que está al lado.
        $etiqueta = 'Descuento';
        if (($d['cobro']['discount_percent'] ?? null) !== null) {
            $etiqueta .= ' · ' . cb_comprobante_porcentaje((float) $d['cobro']['discount_percent']);
        }
        if (trim((string) $d['cobro']['discount_label']) !== '') {
            $etiqueta .= ' · ' . $d['cobro']['discount_label'];
        }
        $pdf->texto($izq, $y, $etiqueta, 10.5, false, $TINTA);
        $pdf->texto($der, $y, '- ' . cb_format_clp((int) $d['cobro']['discount_amount']), 10.5, false, [22, 120, 60], 'der');
        $y += 4;
    }

    $y += 4;
    $pdf->rectangulo($izq, $y - 5.5, $ancho, 11, [246, 243, 250]);
    $pdf->texto($izq + 3, $y, 'TOTAL A PAGAR', 11, true, $TINTA);
    $pdf->texto($der - 3, $y, cb_format_clp($d['cobro']['total']), 13, true, $TINTA, 'der');
    $y += 12;

    if ($d['cobro']['deposit_amount'] !== null) {
        $pdf->texto($izq, $y, 'Abono recibido', 10.5, false, $TINTA);
        $pdf->texto($der, $y, cb_format_clp((int) $d['cobro']['deposit_amount']), 10.5, false, $TINTA, 'der');
        $y += 6;
        $pdf->texto($izq, $y, 'Saldo pendiente', 10.5, true, $TINTA);
        $pdf->texto($der, $y, cb_format_clp($d['cobro']['balance']), 11, true, $TINTA, 'der');
        $y += 8;
    }

    if (trim((string) $d['cobro']['payment_note']) !== '') {
        $y += 2;
        $pdf->texto($izq, $y, 'NOTA', 8, true, $GRIS);
        $y += 5;
        $y = $pdf->parrafo($izq, $y, $ancho, (string) $d['cobro']['payment_note'], 9.5, false, $TINTA);
        $y += 4;
    }

    if ($d['otros_contactos']) {
        $y += 2;
        $pdf->texto($izq, $y, 'TAMBIÉN RECIBE ESTE COMPROBANTE', 8, true, $GRIS);
        $y += 5;
        foreach ($d['otros_contactos'] as $c) {
            $pdf->texto($izq, $y, trim($c['name'] . '  ·  ' . $c['email']), 9.5, false, $TINTA);
            $y += 5;
        }
    }

    // ── Pie: la advertencia va sí o sí ──────────────────────────────────────
    $pieY = 279.4 - 34;
    $pdf->linea($izq, $pieY - 8, $der, $pieY - 8, 0.2, $LINEA);
    $pdf->parrafo($izq, $pieY - 3, $ancho,
        'Este documento es un comprobante interno de pago del servicio y NO constituye boleta '
        . 'ni factura electrónica del SII. El documento tributario, cuando corresponda, se emite '
        . 'aparte.', 8.5, false, $GRIS);
    $altoAt = $pdf->imagenJpeg(__DIR__ . '/brand/pdf-automatizatech.jpg', $izq, $pieY + 9, 34);
    $pdf->texto($der, $pieY + 9 + ($altoAt > 0 ? $altoAt / 2 : 3),
        'CumpleClick es un servicio de ' . $d['emisor']['razon_social'], 8.5, false, $GRIS, 'der');

    return $pdf->salida();
}

/** Nombre del archivo que ve el cliente al descargarlo o recibirlo. */
function cb_comprobante_nombre_archivo(array $d): string
{
    $nombre = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $d['fiesta']['nombre']) ?: 'fiesta';
    return 'comprobante-' . strtolower(trim($nombre, '-')) . '-' . $d['folio'] . '.pdf';
}

/**
 * Enlace público del comprobante, firmado con la clave de la aplicación.
 *
 * Se firma en vez de usar un token en base porque el comprobante no cambia: es el mismo
 * documento cada vez que se pide. Sin firma, cambiar el slug en la URL dejaría ver el cobro
 * de cualquier otra fiesta.
 */
function cb_comprobante_firma(string $slug): string
{
    return substr(cb_hmac($slug, 'comprobante-pago'), 0, 24);
}

function cb_comprobante_url(string $slug): string
{
    return rtrim((string) cb_public_base_url(), '/') . '/comprobante.php?p=' . rawurlencode($slug)
        . '&f=' . cb_comprobante_firma($slug);
}

/** Texto para mandar por WhatsApp con el enlace al comprobante. */
function cb_comprobante_texto_whatsapp(array $d, string $url): string
{
    $lineas = [
        '¡Hola! Te dejo el comprobante de pago del servicio CumpleClick para la '
            . 'fiesta de ' . $d['fiesta']['nombre'] . '.',
        '',
        'Total: ' . cb_format_clp($d['cobro']['total']),
    ];
    if ($d['cobro']['deposit_amount'] !== null) {
        $lineas[] = 'Abono recibido: ' . cb_format_clp((int) $d['cobro']['deposit_amount']);
        $lineas[] = 'Saldo pendiente: ' . cb_format_clp($d['cobro']['balance']);
    }
    $lineas[] = '';
    $lineas[] = 'Lo puedes descargar acá: ' . $url;
    $lineas[] = '';
    $lineas[] = 'Cualquier duda, quedo atento. — CumpleClick';
    return implode("\n", $lineas);
}

/**
 * Asunto y cuerpo del correo con el que se manda el comprobante. Usa la misma plantilla que
 * los demás correos del proyecto (`cc_mail_shell`) para que llegue con la marca de siempre.
 */
function cb_comprobante_correo(array $d, string $url): array
{
    require_once __DIR__ . '/lib.mail-templates.php';

    $nombre = trim((string) ($d['cliente']['nombre'] ?? ''));
    $saludo = $nombre !== '' ? 'Hola ' . cc_mail_h(explode(' ', $nombre)[0]) : 'Hola';
    $filas = cc_mail_fila('Fiesta', cc_mail_h($d['fiesta']['nombre'] . ' · ' . $d['fiesta']['tema']));
    if (!empty($d['cobro']['discount_amount'])) {
        $comoSeAcordo = ($d['cobro']['discount_percent'] ?? null) !== null
            ? cb_comprobante_porcentaje((float) $d['cobro']['discount_percent']) . ' · ' : '';
        $filas .= cc_mail_fila('Precio del plan', cc_mail_h(cb_format_clp($d['cobro']['price_total'])))
            . cc_mail_fila('Descuento', cc_mail_h($comoSeAcordo . '- ' . cb_format_clp((int) $d['cobro']['discount_amount'])));
    }
    $filas .= cc_mail_fila('Total', cc_mail_h(cb_format_clp($d['cobro']['total'])));
    if ($d['cobro']['deposit_amount'] !== null) {
        $filas .= cc_mail_fila('Abono recibido', cc_mail_h(cb_format_clp((int) $d['cobro']['deposit_amount'])))
            . cc_mail_fila('Saldo pendiente', cc_mail_h(cb_format_clp($d['cobro']['balance'])));
    }

    $contenido = '<p style="margin:0 0 16px">' . $saludo . ', te dejamos el comprobante de pago del '
        . 'servicio CumpleClick para la fiesta de <strong>' . cc_mail_h($d['fiesta']['nombre'])
        . '</strong>. Va adjunto en PDF.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
        . 'style="margin:0 0 18px">' . $filas . '</table>'
        . '<p style="margin:0 0 16px">También lo puedes abrir desde acá: '
        . '<a href="' . cc_mail_h($url) . '" style="color:#7C3AED">ver el comprobante</a>.</p>'
        . '<p style="margin:0;font-size:13px;color:#6B6280">Este documento es un comprobante interno '
        . 'de pago y no constituye boleta ni factura electrónica del SII.</p>';

    $texto = $saludo . ", te dejamos el comprobante de pago del servicio CumpleClick para la fiesta de "
        . $d['fiesta']['nombre'] . ". Va adjunto en PDF.

"
        . 'Total: ' . cb_format_clp($d['cobro']['total']) . "
";
    if ($d['cobro']['deposit_amount'] !== null) {
        $texto .= 'Abono recibido: ' . cb_format_clp((int) $d['cobro']['deposit_amount']) . "
"
            . 'Saldo pendiente: ' . cb_format_clp($d['cobro']['balance']) . "
";
    }
    $texto .= "
También lo puedes abrir acá: " . $url
        . "

Este documento es un comprobante interno de pago y no constituye boleta ni factura "
        . "electrónica del SII.

— CumpleClick";

    return [
        'subject' => 'Comprobante de pago · Fiesta de ' . $d['fiesta']['nombre'],
        'html' => cc_mail_shell('Comprobante de pago', $contenido),
        'text' => $texto,
    ];
}
