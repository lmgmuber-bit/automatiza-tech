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

function cb_validate_lead_input(array $input): array
{
    $data = [
        'name' => cb_lead_text($input['nombre'] ?? '', 100),
        'organization' => cb_lead_text($input['organizacion'] ?? '', 160),
        'email' => strtolower(cb_lead_text($input['email'] ?? '', 254)),
        'phone' => cb_lead_text($input['telefono'] ?? '', 30),
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
    $phoneDigits = preg_replace('/\D+/', '', $data['phone']);
    if (preg_match('/^[+0-9() .-]+$/', $data['phone']) !== 1 || strlen((string) $phoneDigits) < 8 || strlen((string) $phoneDigits) > 15) {
        $errors['telefono'] = 'Ingresa un teléfono válido.';
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
            return ['ok' => true, 'reference' => $reference];
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
