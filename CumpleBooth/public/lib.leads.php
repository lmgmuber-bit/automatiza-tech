<?php
/** Backend de solicitudes comerciales de la landing CumpleClick. */

function cb_lead_text($value, int $maxBytes): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim(str_replace("\0", '', $value));
    if (preg_match('//u', $value) !== 1 || strlen($value) > $maxBytes) {
        return '';
    }
    return $value;
}

/**
 * Los países que el formulario ofrece, con su código y el largo del número
 * NACIONAL (sin el código de país).
 *
 * Es la ÚNICA fuente de verdad: el `<select>` de `sitio/index.html` se compara
 * contra esta tabla en `tests/frontend/sitioPublico.test.mjs`, así que si
 * alguien agrega un país en un lado y no en el otro, el test lo dice. Sin eso
 * la lista y la validación se separan en silencio y el formulario empieza a
 * rechazar países que él mismo ofrece.
 *
 * `otro` existe a propósito y NO es un descuido: una lista cerrada deja fuera a
 * un cliente real de un país que no anticipamos, y perder un lead por eso es
 * peor que aceptar un largo poco preciso. Ahí se valida solo contra el rango de
 * E.164.
 *
 * Los largos son del número nacional. Si alguno resulta estar mal, se corrige
 * acá y el resto del sistema se entera solo.
 *
 * @return array<string, array{nombre:string, codigo:string, min:int, max:int}>
 */
function cb_lead_paises(): array
{
    return [
        'cl'   => ['nombre' => 'Chile',              'codigo' => '56',  'min' => 9,  'max' => 9],
        'ar'   => ['nombre' => 'Argentina',          'codigo' => '54',  'min' => 10, 'max' => 11],
        'pe'   => ['nombre' => 'Perú',               'codigo' => '51',  'min' => 9,  'max' => 9],
        'co'   => ['nombre' => 'Colombia',           'codigo' => '57',  'min' => 10, 'max' => 10],
        'mx'   => ['nombre' => 'México',             'codigo' => '52',  'min' => 10, 'max' => 10],
        'ec'   => ['nombre' => 'Ecuador',            'codigo' => '593', 'min' => 8,  'max' => 9],
        'bo'   => ['nombre' => 'Bolivia',            'codigo' => '591', 'min' => 8,  'max' => 8],
        'uy'   => ['nombre' => 'Uruguay',            'codigo' => '598', 'min' => 8,  'max' => 9],
        'py'   => ['nombre' => 'Paraguay',           'codigo' => '595', 'min' => 9,  'max' => 9],
        'br'   => ['nombre' => 'Brasil',             'codigo' => '55',  'min' => 10, 'max' => 11],
        've'   => ['nombre' => 'Venezuela',          'codigo' => '58',  'min' => 10, 'max' => 10],
        'cr'   => ['nombre' => 'Costa Rica',         'codigo' => '506', 'min' => 8,  'max' => 8],
        'pa'   => ['nombre' => 'Panamá',             'codigo' => '507', 'min' => 7,  'max' => 8],
        'gt'   => ['nombre' => 'Guatemala',          'codigo' => '502', 'min' => 8,  'max' => 8],
        'do'   => ['nombre' => 'República Dominicana','codigo' => '1',  'min' => 10, 'max' => 10],
        'us'   => ['nombre' => 'Estados Unidos',     'codigo' => '1',   'min' => 10, 'max' => 10],
        'es'   => ['nombre' => 'España',             'codigo' => '34',  'min' => 9,  'max' => 9],
        'otro' => ['nombre' => 'Otro país',          'codigo' => '',    'min' => 6,  'max' => 14],
    ];
}

/**
 * Arma el número final a partir del país elegido y el número nacional.
 *
 * Con el país explícito no queda nada que adivinar. Antes se pedía el número
 * completo y `974940070` —un móvil chileno escrito sin país— se guardaba como
 * `+974940070`, que es un número de Qatar bien formado: nadie se enteraba hasta
 * intentar escribirle.
 *
 * @return array{ok:bool, phone:string, error:string}
 */
function cb_lead_phone_from_country(string $paisClave, string $numero): array
{
    $paises = cb_lead_paises();
    if (!isset($paises[$paisClave])) {
        return ['ok' => false, 'phone' => '', 'error' => 'Selecciona el país de tu teléfono.'];
    }
    $pais = $paises[$paisClave];

    if ($numero === '' || preg_match('/^[+0-9()\s.\-]+$/', $numero) !== 1) {
        return ['ok' => false, 'phone' => '', 'error' => 'Ingresa un teléfono válido.'];
    }
    $digitos = preg_replace('/\D+/', '', $numero);

    // "Otro país": el número tiene que traer su propio código, porque no lo
    // sabemos. Se valida solo contra el rango de E.164.
    if ($pais['codigo'] === '') {
        if (strlen($digitos) < 8 || strlen($digitos) > 15 || $digitos[0] === '0') {
            return ['ok' => false, 'phone' => '', 'error' => 'Ingresa el número con el código del país, sin el signo +.'];
        }
        return ['ok' => true, 'phone' => '+' . $digitos, 'error' => ''];
    }

    // Que la persona haya escrito igual el código del país no es un error: se
    // le saca y se sigue. Es lo que pasa cuando copia y pega el número.
    if (strpos($digitos, $pais['codigo']) === 0
        && strlen($digitos) > $pais['max']
        && strlen($digitos) - strlen($pais['codigo']) >= $pais['min']) {
        $digitos = substr($digitos, strlen($pais['codigo']));
    }
    // Y el 0 de la marcación nacional tampoco: en varios países se escribe.
    $digitos = ltrim($digitos, '0');

    $largo = strlen($digitos);
    if ($largo < $pais['min'] || $largo > $pais['max']) {
        $cuantos = $pais['min'] === $pais['max']
            ? $pais['min'] . ' dígitos'
            : 'entre ' . $pais['min'] . ' y ' . $pais['max'] . ' dígitos';
        return ['ok' => false, 'phone' => '',
                'error' => 'Un número de ' . $pais['nombre'] . ' tiene ' . $cuantos . '. Escríbelo sin el código del país.'];
    }
    return ['ok' => true, 'phone' => '+' . $pais['codigo'] . $digitos, 'error' => ''];
}

/**
 * Normaliza un teléfono a E.164: `+` y solo dígitos.
 *
 * CumpleClick dejó de ser solo Chile, y un número sin código de país no sirve
 * para nada: "974940070" puede ser chileno, y también puede no serlo. Sin el
 * código no se puede llamar, no se puede armar un enlace de WhatsApp, y quien
 * atienda el lead tiene que adivinar.
 *
 * Reglas de E.164: hasta 15 dígitos, el primero nunca es 0. El mínimo de 8 es
 * nuestro, no del estándar (hay países con números más cortos), y se pone
 * porque un número de menos de 8 dígitos en este formulario casi siempre es
 * alguien que escribió a medias.
 *
 * `56 9 7494 0070`, `+56974940070` y `(56) 9-7494-0070` son el mismo número y
 * los tres se guardan igual. Lo que NO se acepta es un número sin país.
 *
 * @return string el número normalizado, o '' si no es válido.
 */
function cb_normalize_phone(string $raw): string
{
    // Solo se admiten los caracteres con los que la gente escribe teléfonos.
    if ($raw === '' || preg_match('/^[+0-9()\s.\-]+$/', $raw) !== 1) {
        return '';
    }
    /* EL CÓDIGO DE PAÍS ES OBLIGATORIO, y esta es la parte que importa.
     *
     * Sin él el número es ambiguo y la ambigüedad no falla: se guarda algo que
     * parece válido y no lo es. `974940070` —un móvil chileno escrito sin país—
     * se normalizaba a `+974940070`, que es un número de Qatar perfectamente
     * bien formado. Nadie se habría enterado hasta intentar escribirle.
     *
     * Se acepta el `+` de siempre y el `00` de la marcación antigua. El
     * formulario ya viene con el código puesto, así que a nadie le cuesta. */
    // El `+` puede venir dentro de paréntesis o con espacios delante: "(+56) 9…"
    // es tan válido como "+56 9…". Lo que se exige es que esté ANTES del primer
    // dígito, no que sea el primer carácter.
    $tieneMarcaInternacional = preg_match('/^[\s(]*\+/', $raw) === 1
        || strpos(preg_replace('/\D+/', '', $raw), '00') === 0;

    $digitos = preg_replace('/\D+/', '', $raw);
    if (strpos($digitos, '00') === 0) {
        $digitos = substr($digitos, 2);
    }
    if (!$tieneMarcaInternacional || $digitos === '' || $digitos[0] === '0') {
        return '';
    }
    if (strlen($digitos) < 8 || strlen($digitos) > 15) {
        return '';
    }
    return '+' . $digitos;
}

function cb_validate_lead_input(array $input): array
{
    $data = [
        'name' => cb_lead_text($input['nombre'] ?? '', 100),
        'organization' => cb_lead_text($input['organizacion'] ?? '', 160),
        'email' => strtolower(cb_lead_text($input['email'] ?? '', 254)),
        'phone' => cb_lead_text($input['telefono'] ?? '', 30),
        // Clave del país elegido en el select. Por defecto Chile: es de donde
        // viene la mayoría y no cambia nada para quien elige otro.
        'phone_country' => strtolower(cb_lead_text($input['pais_telefono'] ?? 'cl', 8)) ?: 'cl',
        'event_type' => cb_lead_text($input['tipo'] ?? '', 40),
        'event_date' => cb_lead_text($input['fecha'] ?? '', 10),
        'commune' => cb_lead_text($input['comuna'] ?? '', 120),
        'message' => cb_lead_text($input['mensaje'] ?? '', 2000),
    ];
    $errors = [];

    if (strlen($data['name']) < 2) { $errors['nombre'] = 'Ingresa tu nombre.'; }
    if (is_string($input['organizacion'] ?? null) && trim((string) $input['organizacion']) !== '' && $data['organization'] === '') {
        $errors['organizacion'] = 'El nombre de la organización es demasiado largo o inválido.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Ingresa un correo válido.'; }
    /* El teléfono se guarda NORMALIZADO en E.164, no como lo escribió la
       persona. El país viene de un select, así que no hay nada que adivinar, y
       el largo se valida contra ESE país. */
    $telefono = cb_lead_phone_from_country($data['phone_country'], $data['phone']);
    if (!$telefono['ok']) {
        $errors['telefono'] = $telefono['error'];
    } else {
        $data['phone'] = $telefono['phone'];
    }
    $eventTypes = ['Cumpleaños', 'Navidad', 'Día del Niño', 'Colegio o jardín', 'Evento de empresa', 'Otro evento especial'];
    if (!in_array($data['event_type'], $eventTypes, true)) { $errors['tipo'] = 'Selecciona un tipo de evento válido.'; }
    if (is_string($input['fecha'] ?? null) && trim((string) $input['fecha']) !== '' && $data['event_date'] === '') {
        $errors['fecha'] = 'Ingresa una fecha válida.';
    } elseif ($data['event_date'] !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $data['event_date']);
        if (!$parsed || $parsed->format('Y-m-d') !== $data['event_date']) { $errors['fecha'] = 'Ingresa una fecha válida.'; }
    }
    if (strlen($data['commune']) < 2) { $errors['comuna'] = 'Ingresa la comuna o ciudad.'; }
    if (strlen($data['message']) < 10) { $errors['mensaje'] = 'Cuéntanos un poco más sobre el evento.'; }
    if (($input['consentimiento'] ?? false) !== true && ($input['consentimiento'] ?? '') !== '1' && ($input['consentimiento'] ?? 0) !== 1) {
        $errors['consentimiento'] = 'Debes aceptar el uso de los datos para responder tu solicitud.';
    }
    if (cb_lead_text($input['website'] ?? '', 200) !== '') { $errors['website'] = 'Solicitud inválida.'; }

    return ['ok' => $errors === [], 'data' => $data, 'errors' => $errors];
}

function cb_create_lead(array $input, string $identity, string $userAgent = ''): array
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('El formulario requiere storage_mode=db.');
    }
    $validation = cb_validate_lead_input($input);
    if (!$validation['ok']) {
        return $validation;
    }

    $pdo = cb_pdo();
    $data = $validation['data'];
    $now = gmdate('Y-m-d H:i:s');
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $reference = 'CC-' . strtoupper(bin2hex(random_bytes(6)));
        try {
            $stmt = $pdo->prepare('INSERT INTO cc_leads
                (public_ref,name,organization,email,phone,event_type,event_date,commune,message,status,source,privacy_version,consented_at,ip_hmac,user_agent_hmac,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $reference, $data['name'], $data['organization'] !== '' ? $data['organization'] : null,
                $data['email'], $data['phone'], $data['event_type'], $data['event_date'] !== '' ? $data['event_date'] : null,
                $data['commune'], $data['message'], 'new', 'website', '2026-08-01', $now,
                cb_hmac($identity, 'lead-ip'), cb_hmac($userAgent, 'lead-user-agent'), $now, $now,
            ]);
            /* Se devuelven también los datos ya validados: quien llama tiene
               que armar los correos y sin esto habría que volver a leer de la
               base la fila recién escrita. */
            return ['ok' => true, 'reference' => $reference,
                    'lead' => $data + ['public_ref' => $reference]];
        } catch (PDOException $e) {
            $duplicate = (string) $e->getCode() === '23000'
                || strpos(strtolower($e->getMessage()), 'unique') !== false
                || strpos(strtolower($e->getMessage()), 'duplicate') !== false;
            if ($attempt === 3 || !$duplicate) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('No se pudo generar la referencia de la solicitud.');
}

/**
 * Manda la confirmación al cliente y el aviso interno, y deja registrado qué
 * pasó con cada uno.
 *
 * No devuelve nada y no lanza: se llama DESPUÉS de haberle respondido al
 * visitante. Su solicitud ya está guardada; que el correo salga o no es un
 * problema nuestro, no suyo, y no puede convertirse en un error en su pantalla.
 * Por eso todo lo que falle termina en `mail_error` y en el log, que es donde
 * lo vamos a ver nosotros.
 */
function cb_lead_enviar_correos(array $lead): void
{
    if (!function_exists('cc_mail_send')) {
        require_once __DIR__ . '/lib.mail.php';
        require_once __DIR__ . '/lib.mail-templates.php';
    }
    if (!cc_mail_enabled()) {
        cb_lead_marcar_correo($lead['public_ref'] ?? '', null, null, 'SMTP no configurado');
        return;
    }

    $cfg = cc_mail_config();
    $base = rtrim((string) cb_config('public_base_url'), '/');
    $urlAdmin = $base !== '' ? $base . '/admin/leads.php?ref=' . rawurlencode((string) ($lead['public_ref'] ?? '')) : '';

    $errores = [];
    $confirmado = null;
    $avisado = null;
    $ahora = gmdate('Y-m-d H:i:s');

    // --- Confirmación al cliente ---
    $plantilla = cc_mail_confirmacion($lead);
    $envio = cc_mail_send([
        'to' => (string) ($lead['email'] ?? ''),
        'to_name' => (string) ($lead['name'] ?? ''),
        'subject' => $plantilla['subject'],
        'html' => $plantilla['html'],
        'text' => $plantilla['text'],
        'headers' => $plantilla['headers'] ?? [],
    ]);
    if ($envio['ok']) {
        $confirmado = $ahora;
    } else {
        $errores[] = 'confirmación: ' . $envio['error'];
        error_log('CumpleClick correo confirmación: ' . $envio['error']);
    }

    // --- Aviso interno ---
    if ($cfg['notify'] !== '') {
        $aviso = cc_mail_aviso_interno($lead, $urlAdmin);
        $envio = cc_mail_send([
            'to' => $cfg['notify'],
            'subject' => $aviso['subject'],
            'html' => $aviso['html'],
            'text' => $aviso['text'],
            'reply_to' => $aviso['reply_to'] ?? '',
        ]);
        if ($envio['ok']) {
            $avisado = $ahora;
        } else {
            $errores[] = 'aviso: ' . $envio['error'];
            error_log('CumpleClick aviso interno: ' . $envio['error']);
        }
    }

    cb_lead_marcar_correo(
        (string) ($lead['public_ref'] ?? ''),
        $confirmado,
        $avisado,
        $errores === [] ? null : implode(' / ', $errores)
    );
}

/** Anota en la fila del lead qué correos salieron y qué falló. */
function cb_lead_marcar_correo(string $reference, ?string $confirmado, ?string $avisado, ?string $error): void
{
    if ($reference === '' || cb_storage_mode() !== 'db') {
        return;
    }
    try {
        $stmt = cb_pdo()->prepare('UPDATE cc_leads
            SET confirmation_sent_at = ?, notified_at = ?, mail_error = ?
            WHERE public_ref = ?');
        $stmt->execute([$confirmado, $avisado, $error !== null ? substr($error, 0, 255) : null, $reference]);
    } catch (Throwable $e) {
        // La migración 012 puede no estar aplicada todavía. El lead ya está
        // guardado y los correos ya se enviaron: perder el registro no
        // justifica romper nada.
        error_log('CumpleClick registro de correo: ' . $e->getMessage());
    }
}
