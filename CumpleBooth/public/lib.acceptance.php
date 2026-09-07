<?php
/**
 * lib.acceptance.php — aceptación de Términos y Condiciones + firma electrónica
 * simple por plan/fiesta (aceptar-plan.php, comprobante-aceptacion.php, admin/aceptaciones.php).
 *
 * Diseño:
 *  - Un enlace opaco por fiesta (token 128 bits, se guarda solo el SHA-256).
 *  - El cliente acepta con casillas separadas (T&C, privacidad/menores, marketing opcional)
 *    y firma trazada en canvas (PNG). Se guarda: versión y hash del texto aceptado,
 *    fecha UTC, IP, user-agent, metadatos del navegador, PNG de la firma y un
 *    comprobante HTML autocontenido con su propio SHA-256.
 *  - Al aceptar se rota el token público (el enlace original deja de servir) y se
 *    emite un token de comprobante solo-lectura para que el cliente descargue su copia.
 *  - Una fiesta no puede activarse sin aceptación `accepted` o `waived` (demo/interno).
 * Sin dependencias externas. Baseline PHP 8.2; compatible con PHP 8.0+.
 */

const CB_LEGAL_VERSION = '2026-09-05';
const CB_ACCEPTANCE_SIGNATURE_MAX_BYTES = 300000;
const CB_ACCEPTANCE_FORM_NONCE_TTL = 7200;

/** Directorio privado (fuera del webroot) para firmas y comprobantes. */
function cb_acceptance_dir(): string
{
    $configured = (string) cb_config('acceptance_dir');
    if ($configured === '') {
        $configured = dirname((string) cb_config('photo_dir')) . '/acceptances';
    }
    $path = cb_private_dir($configured, 'acceptance_dir');
    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('No se pudo crear o acceder al directorio privado de aceptaciones.');
    }
    return $path;
}

function cb_legal_dir(): string
{
    return __DIR__ . '/legal';
}

/** Documentos legales versionados. El orden es el orden de lectura en la página pública. */
function cb_legal_documents(): array
{
    return [
        'terminos' => [
            'file' => 'terminos-y-condiciones.md',
            'title' => 'Términos y Condiciones del Servicio',
            'checkbox' => 'accepted_terms',
            'required' => true,
            'label' => 'He leído y acepto los Términos y Condiciones del Servicio CumpleClick, incluido el Resumen del Plan.',
        ],
        'privacidad' => [
            'file' => 'politica-de-privacidad.md',
            'title' => 'Política de Privacidad y Tratamiento de Datos',
            'checkbox' => 'accepted_privacy',
            'required' => true,
            'label' => 'He leído y acepto la Política de Privacidad y el tratamiento de mis datos personales descrito en ella.',
        ],
        'menores' => [
            'file' => 'consentimiento-imagen-menores.md',
            'title' => 'Consentimiento de imagen de niños, niñas y adolescentes',
            'checkbox' => 'accepted_minors',
            'required' => true,
            'label' => 'Declaro ser padre, madre o tutor legal del homenajeado (o contar con su autorización) y consiento la captura, procesamiento, almacenamiento y entrega de fotografías de los menores participantes según el documento de Consentimiento.',
        ],
    ];
}

/** Casilla opcional de marketing: separada de los documentos obligatorios. */
function cb_legal_marketing_label(): string
{
    return 'OPCIONAL: Autorizo a CumpleClick a usar fotografías o videos del evento en su sitio web y redes sociales, sin nombres completos ni datos de contacto, según la sección 4 del Consentimiento. Puedo revocar esta autorización en cualquier momento.';
}

/**
 * Carga los tres documentos, valida que declaren la misma versión que el código
 * (fail-closed: si un documento se editó sin subir la versión, no se emiten enlaces)
 * y calcula el hash del texto completo que el cliente acepta.
 */
function cb_legal_bundle(): array
{
    static $bundle = null;
    if ($bundle !== null) {
        return $bundle;
    }
    $documents = [];
    $textParts = [];
    foreach (cb_legal_documents() as $key => $meta) {
        $path = cb_legal_dir() . '/' . $meta['file'];
        $markdown = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($markdown) || trim($markdown) === '') {
            throw new RuntimeException('Falta el documento legal ' . $meta['file'] . '.');
        }
        $markdown = str_replace("\r\n", "\n", $markdown);
        if (preg_match('/^Versión:\s*(\d{4}-\d{2}-\d{2})\s*$/mu', $markdown, $m) !== 1 || $m[1] !== CB_LEGAL_VERSION) {
            throw new RuntimeException('El documento legal ' . $meta['file'] . ' no declara la versión ' . CB_LEGAL_VERSION . '.');
        }
        $documents[$key] = $meta + ['markdown' => $markdown, 'html' => cb_markdown_to_html($markdown)];
        $textParts[] = '===== ' . $meta['title'] . " =====\n" . trim($markdown);
    }
    $text = implode("\n\n", $textParts) . "\n";
    $bundle = ['version' => CB_LEGAL_VERSION, 'documents' => $documents, 'text' => $text, 'sha256' => hash('sha256', $text)];
    return $bundle;
}

/**
 * Markdown mínimo y seguro: escapa todo el HTML primero y solo reconoce
 * encabezados, párrafos, listas, citas, tablas y **negrita**. Suficiente para
 * los documentos legales; no es un parser general.
 */
function cb_markdown_to_html(string $markdown): string
{
    $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
    $html = [];
    $paragraph = [];
    $list = null; // 'ul' | 'ol'
    $table = [];
    $inline = static function (string $text): string {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
    };
    $flushParagraph = static function () use (&$paragraph, &$html, $inline): void {
        if ($paragraph) {
            $html[] = '<p>' . $inline(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        }
    };
    $flushList = static function () use (&$list, &$html): void {
        if ($list !== null) {
            $html[] = '</' . $list . '>';
            $list = null;
        }
    };
    $flushTable = static function () use (&$table, &$html, $inline): void {
        if (!$table) {
            return;
        }
        $out = ['<table>'];
        foreach ($table as $i => $cells) {
            $tag = $i === 0 ? 'th' : 'td';
            $out[] = '<tr>' . implode('', array_map(static fn(string $c): string => "<$tag>" . $inline(trim($c)) . "</$tag>", $cells)) . '</tr>';
        }
        $out[] = '</table>';
        $html[] = implode('', $out);
        $table = [];
    };
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushParagraph();
            $flushList();
            $flushTable();
            continue;
        }
        if (preg_match('/^Versión:\s*\d{4}-\d{2}-\d{2}$/u', $trimmed)) {
            $flushParagraph();
            $flushList();
            $flushTable();
            $html[] = '<p class="legal-version">' . $inline($trimmed) . '</p>';
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+)$/u', $trimmed, $m)) {
            $flushParagraph();
            $flushList();
            $flushTable();
            $level = strlen($m[1]) + 1; // # del documento pasa a h2 para no competir con el título de la página
            $html[] = "<h$level>" . $inline($m[2]) . "</h$level>";
            continue;
        }
        if (strpos($trimmed, '>') === 0) {
            $flushParagraph();
            $flushList();
            $flushTable();
            $html[] = '<blockquote>' . $inline(trim(substr($trimmed, 1))) . '</blockquote>';
            continue;
        }
        if (strpos($trimmed, '|') === 0) {
            $flushParagraph();
            $flushList();
            if (preg_match('/^\|?\s*:?-{2,}/', $trimmed)) {
                continue; // separador de cabecera
            }
            $table[] = explode('|', trim($trimmed, '|'));
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u', $trimmed, $m)) {
            $flushParagraph();
            $flushTable();
            if ($list !== 'ul') {
                $flushList();
                $list = 'ul';
                $html[] = '<ul>';
            }
            $html[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        if (preg_match('/^\d+\.\s+(.+)$/u', $trimmed, $m) && !preg_match('/^\d+\.\d+\./', $trimmed)) {
            $flushParagraph();
            $flushTable();
            if ($list !== 'ol') {
                $flushList();
                $list = 'ol';
                $html[] = '<ol>';
            }
            $html[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        $flushList();
        $flushTable();
        $paragraph[] = $trimmed;
    }
    $flushParagraph();
    $flushList();
    $flushTable();
    return implode("\n", $html);
}

/** Etiquetas comerciales de los planes internos booth/full (nombres del sitio público). */
function cb_acceptance_plan_labels(): array
{
    return ['booth' => 'Plan Mágico', 'full' => 'Plan Premium'];
}

function cb_acceptance_status_label(string $status): string
{
    $map = [
        'pending' => 'Pendiente de aceptación',
        'accepted' => 'Aceptado y firmado',
        'revoked' => 'Revocado',
        'expired' => 'Enlace vencido',
        'waived' => 'Eximido (demo/interno)',
        'none' => 'Sin enviar',
    ];
    return $map[$status] ?? $status;
}

/** Hora Chile para mostrar; la BD guarda UTC. */
function cb_chile_datetime(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone('America/Santiago'))->format('d-m-Y H:i') . ' (Chile)';
    } catch (Throwable $e) {
        return $utc;
    }
}

/** Campos del Resumen del Plan que el admin completa al generar el enlace. */
function cb_acceptance_summary_fields(): array
{
    return [
        'plan_name' => 'Plan contratado',
        'theme_name' => 'Temática',
        'event_date' => 'Fecha del evento',
        'event_time' => 'Hora de inicio',
        'event_address' => 'Lugar / dirección',
        'service_hours' => 'Duración del servicio',
        'price_total' => 'Valor total (CLP, IVA incluido)',
        'deposit' => 'Anticipo para reservar',
        'deposit_due' => 'Plazo para pagar el anticipo',
        'balance_due' => 'Pago del saldo',
        'travel_fee' => 'Recargo por traslado',
        'extras' => 'Extras acordados',
        'replacement_values' => 'Valores de reposición de equipos',
        'at_contact' => 'Contacto de CumpleClick',
        'notes' => 'Observaciones',
    ];
}

function cb_acceptance_text($value, int $maxBytes): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim(str_replace("\0", '', $value));
    if (preg_match('//u', $value) !== 1) {
        return '';
    }
    return strlen($value) > $maxBytes ? '' : $value;
}

/**
 * Crea un enlace de aceptación para una fiesta. Devuelve el token en claro una
 * sola vez; en la BD queda solo el hash. Un enlace `pending` anterior de la misma
 * fiesta se marca `revoked` para que exista un único enlace vigente.
 */
function cb_create_plan_acceptance(array $party, array $input, string $by): array
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('La aceptación de Términos requiere storage_mode=db.');
    }
    $publicSlug = (string) ($party['public_slug'] ?? '');
    if (!cb_valid_public_slug($publicSlug)) {
        return ['ok' => false, 'errors' => ['party' => 'Fiesta inválida.']];
    }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) {
        return ['ok' => false, 'errors' => ['party' => 'No se pudo resolver la fiesta en la base de datos.']];
    }
    $errors = [];
    $clientName = cb_acceptance_text($input['client_name'] ?? '', 160);
    $clientEmail = strtolower(cb_acceptance_text($input['client_email'] ?? '', 254));
    $clientPhone = cb_acceptance_text($input['client_phone'] ?? '', 30);
    if (strlen($clientName) < 2) {
        $errors['client_name'] = 'Ingresa el nombre del cliente.';
    }
    if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['client_email'] = 'Ingresa un correo válido del cliente.';
    }
    $days = (int) ($input['expires_days'] ?? 14);
    if ($days < 1 || $days > 90) {
        $errors['expires_days'] = 'La vigencia del enlace debe estar entre 1 y 90 días.';
    }
    $summary = [];
    foreach (cb_acceptance_summary_fields() as $key => $label) {
        $value = cb_acceptance_text($input['summary'][$key] ?? '', 600);
        if ($value !== '') {
            $summary[$key] = $value;
        }
    }
    $servicePlan = in_array((string) ($party['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $party['service_plan'] : 'booth';
    $summary['plan_name'] = $summary['plan_name'] ?? (cb_acceptance_plan_labels()[$servicePlan] ?? $servicePlan);
    $summary['event_date'] = $summary['event_date'] ?? (string) ($party['fecha'] ?? '');
    if (!isset($summary['theme_name'])) {
        $summary['theme_name'] = cb_theme_public_name((string) ($party['tema'] ?? $party['theme_slug'] ?? ''));
    }
    if (!isset($summary['price_total'])) {
        $errors['price_total'] = 'Indica el valor total del plan (TODO-LUIS: definir tarifa vigente).';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    try {
        $bundle = cb_legal_bundle();
    } catch (Throwable $e) {
        return ['ok' => false, 'errors' => ['legal' => $e->getMessage()]];
    }

    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $token = cb_opaque_token(16);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE cc_plan_acceptances SET status=\'revoked\', revoked_at=?, updated_at=? WHERE party_id=? AND status=\'pending\'')
            ->execute([$now, $now, $partyId]);
        $stmt = $pdo->prepare('INSERT INTO cc_plan_acceptances
            (party_id, party_public_slug, party_admin_label, public_token_hash, status, plan_code, plan_summary_json, client_name, client_email, client_phone,
             legal_version, legal_text_sha256, expires_at, created_at, updated_at, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $partyId, $publicSlug, (string) ($party['admin_label'] ?? ''), cb_hash_token($token), 'pending', $servicePlan,
            json_encode($summary, JSON_UNESCAPED_UNICODE), $clientName, $clientEmail, $clientPhone !== '' ? $clientPhone : null,
            $bundle['version'], $bundle['sha256'], gmdate('Y-m-d H:i:s', time() + $days * 86400), $now, $now, $by,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['ok' => true, 'id' => $id, 'token' => $token, 'url' => cb_acceptance_public_url($token)];
}

function cb_acceptance_public_url(string $token): string
{
    return cb_public_base_url() . '/aceptar-plan.php?t=' . rawurlencode($token);
}

function cb_acceptance_receipt_url(string $receiptToken): string
{
    return cb_public_base_url() . '/comprobante-aceptacion.php?r=' . rawurlencode($receiptToken);
}

function cb_acceptance_decode_row(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    $summary = json_decode((string) ($row['plan_summary_json'] ?? ''), true);
    $row['plan_summary'] = is_array($summary) ? $summary : [];
    $meta = json_decode((string) ($row['client_meta_json'] ?? ''), true);
    $row['client_meta'] = is_array($meta) ? $meta : [];
    return $row;
}

function cb_load_acceptance_by_token_hash(string $hash): ?array
{
    $stmt = cb_pdo()->prepare('SELECT * FROM cc_plan_acceptances WHERE public_token_hash = ?');
    $stmt->execute([$hash]);
    return cb_acceptance_decode_row($stmt->fetch() ?: null);
}

function cb_load_acceptance_by_receipt_hash(string $hash): ?array
{
    $stmt = cb_pdo()->prepare('SELECT * FROM cc_plan_acceptances WHERE receipt_token_hash = ? AND status = \'accepted\'');
    $stmt->execute([$hash]);
    return cb_acceptance_decode_row($stmt->fetch() ?: null);
}

function cb_load_acceptance_by_id(int $id): ?array
{
    $stmt = cb_pdo()->prepare('SELECT * FROM cc_plan_acceptances WHERE id = ?');
    $stmt->execute([$id]);
    return cb_acceptance_decode_row($stmt->fetch() ?: null);
}

function cb_list_party_acceptances(string $publicSlug): array
{
    $stmt = cb_pdo()->prepare('SELECT * FROM cc_plan_acceptances WHERE party_public_slug = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$publicSlug]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = cb_acceptance_decode_row($row);
    }
    return $rows;
}

function cb_acceptance_is_expired(array $row): bool
{
    return (string) $row['status'] === 'pending'
        && !empty($row['expires_at'])
        && strtotime((string) $row['expires_at']) < time();
}

/**
 * Estado consolidado de una fiesta: la aceptación vigente manda.
 * accepted/waived bloquean por encima de cualquier pending posterior.
 */
function cb_party_acceptance_state(string $publicSlug): array
{
    if (cb_storage_mode() !== 'db') {
        return ['status' => 'none', 'row' => null];
    }
    $rows = cb_list_party_acceptances($publicSlug);
    foreach ($rows as $row) {
        if (in_array((string) $row['status'], ['accepted', 'waived'], true)) {
            return ['status' => (string) $row['status'], 'row' => $row];
        }
    }
    foreach ($rows as $row) {
        if ((string) $row['status'] === 'pending') {
            return ['status' => cb_acceptance_is_expired($row) ? 'expired' : 'pending', 'row' => $row];
        }
    }
    return ['status' => $rows ? (string) $rows[0]['status'] : 'none', 'row' => $rows[0] ?? null];
}

/** Regla de negocio: activar la fiesta (cierre del plan) exige aceptación registrada o exención explícita. */
function cb_party_can_activate(string $publicSlug): bool
{
    $state = cb_party_acceptance_state($publicSlug);
    return in_array($state['status'], ['accepted', 'waived'], true);
}

/** Cuenta todas las fiestas con su estado de aceptación en una sola consulta (para el listado del admin). */
function cb_acceptance_states_by_slug(): array
{
    if (cb_storage_mode() !== 'db') {
        return [];
    }
    $rows = cb_pdo()->query('SELECT party_public_slug, status, expires_at FROM cc_plan_acceptances ORDER BY created_at DESC, id DESC')->fetchAll();
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(string) $row['party_public_slug']][] = $row;
    }
    $states = [];
    foreach ($grouped as $slug => $list) {
        $state = 'none';
        foreach ($list as $row) {
            if (in_array((string) $row['status'], ['accepted', 'waived'], true)) {
                $state = (string) $row['status'];
                break;
            }
        }
        if ($state === 'none') {
            foreach ($list as $row) {
                if ((string) $row['status'] === 'pending') {
                    $state = cb_acceptance_is_expired($row) ? 'expired' : 'pending';
                    break;
                }
            }
        }
        if ($state === 'none') {
            $state = (string) $list[0]['status'];
        }
        $states[$slug] = $state;
    }
    return $states;
}

function cb_acceptance_register_view(int $id): void
{
    $now = gmdate('Y-m-d H:i:s');
    cb_pdo()->prepare('UPDATE cc_plan_acceptances SET view_count = view_count + 1, first_viewed_at = COALESCE(first_viewed_at, ?), updated_at = ? WHERE id = ?')
        ->execute([$now, $now, $id]);
}

/** Nonce anti-CSRF sin sesión: ligado al enlace y con caducidad. */
function cb_acceptance_form_nonce(string $tokenHash, int $issuedAt): string
{
    return $issuedAt . '.' . cb_hmac($tokenHash . '|' . $issuedAt, 'accept-form');
}

function cb_acceptance_form_nonce_valid(string $tokenHash, string $nonce): bool
{
    if (preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $nonce, $m) !== 1) {
        return false;
    }
    $issuedAt = (int) $m[1];
    if ($issuedAt > time() + 60 || time() - $issuedAt > CB_ACCEPTANCE_FORM_NONCE_TTL) {
        return false;
    }
    return hash_equals(cb_hmac($tokenHash . '|' . $issuedAt, 'accept-form'), $m[2]);
}

/** Valida RUT chileno (módulo 11). Acepta puntos y guion. */
function cb_valid_rut(string $rut): bool
{
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $rut) ?? '');
    if (strlen($clean) < 8 || strlen($clean) > 9) {
        return false;
    }
    $dv = substr($clean, -1);
    $number = substr($clean, 0, -1);
    $sum = 0;
    $factor = 2;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $sum += (int) $number[$i] * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }
    $expected = 11 - ($sum % 11);
    $expectedDv = $expected === 11 ? '0' : ($expected === 10 ? 'K' : (string) $expected);
    return $dv === $expectedDv;
}

function cb_format_rut(string $rut): string
{
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $rut) ?? '');
    return substr($clean, 0, -1) . '-' . substr($clean, -1);
}

/**
 * Decodifica el PNG de la firma (data URL) y verifica que sea un PNG real,
 * de tamaño razonable y con trazos (no un lienzo vacío). Devuelve el binario o null.
 */
function cb_acceptance_decode_signature(string $dataUrl): ?string
{
    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m) !== 1) {
        return null;
    }
    $binary = base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);
    if (!is_string($binary) || strlen($binary) < 200 || strlen($binary) > CB_ACCEPTANCE_SIGNATURE_MAX_BYTES) {
        return null;
    }
    if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }
    $info = @getimagesizefromstring($binary);
    if (!is_array($info) || ($info['mime'] ?? '') !== 'image/png' || $info[0] < 100 || $info[0] > 2400 || $info[1] < 40 || $info[1] > 1200) {
        return null;
    }
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($binary);
        if ($img === false) {
            return null;
        }
        $inked = 0;
        $w = imagesx($img);
        $h = imagesy($img);
        for ($y = 0; $y < $h; $y += 3) {
            for ($x = 0; $x < $w; $x += 3) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 64) { // píxel visible (alpha GD: 0 opaco, 127 transparente)
                    $inked++;
                    if ($inked >= 40) {
                        break 2;
                    }
                }
            }
        }
        imagedestroy($img);
        if ($inked < 40) {
            return null;
        }
    }
    return $binary;
}

function cb_validate_acceptance_submission(array $input): array
{
    $errors = [];
    foreach (cb_legal_documents() as $meta) {
        $key = $meta['checkbox'];
        if (($input[$key] ?? '') !== '1' && ($input[$key] ?? false) !== true) {
            $errors[$key] = 'Debes aceptar este documento para continuar.';
        }
    }
    $signerName = cb_acceptance_text($input['signer_name'] ?? '', 160);
    if (strlen($signerName) < 3 || preg_match('/\s/', $signerName) !== 1) {
        $errors['signer_name'] = 'Ingresa tu nombre y apellido tal como aparecen en tu cédula.';
    }
    $signerRut = cb_acceptance_text($input['signer_rut'] ?? '', 20);
    if (!cb_valid_rut($signerRut)) {
        $errors['signer_rut'] = 'Ingresa un RUT válido (ej: 12.345.678-5).';
    }
    $signerEmail = strtolower(cb_acceptance_text($input['signer_email'] ?? '', 254));
    if (!filter_var($signerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['signer_email'] = 'Ingresa un correo válido para enviarte el comprobante.';
    }
    $relationship = cb_acceptance_text($input['signer_relationship'] ?? '', 40);
    if (!in_array($relationship, ['madre', 'padre', 'tutor', 'autorizado'], true)) {
        $errors['signer_relationship'] = 'Indica tu relación con el niño o niña homenajeado.';
    }
    $signature = cb_acceptance_decode_signature((string) ($input['signature_dataurl'] ?? ''));
    if ($signature === null) {
        $errors['signature'] = 'Dibuja tu firma en el recuadro antes de enviar.';
    }
    if (cb_acceptance_text($input['website'] ?? '', 200) !== '') {
        $errors['website'] = 'Solicitud inválida.';
    }
    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'data' => [
            'signer_name' => $signerName,
            'signer_rut' => cb_valid_rut($signerRut) ? cb_format_rut($signerRut) : $signerRut,
            'signer_email' => $signerEmail,
            'signer_relationship' => $relationship,
            'accepted_marketing' => ($input['accepted_marketing'] ?? '') === '1' || ($input['accepted_marketing'] ?? false) === true,
            'signature' => $signature,
        ],
    ];
}

function cb_acceptance_storage_key(int $id, string $suffix): string
{
    return sprintf('acc-%06d-%s', $id, $suffix);
}

function cb_acceptance_file_path(?string $storageKey): ?string
{
    if ($storageKey === null || $storageKey === '' || preg_match('/^acc-\d{6}-[a-z0-9-]+\.(png|html)$/', $storageKey) !== 1) {
        return null;
    }
    return cb_acceptance_dir() . DIRECTORY_SEPARATOR . $storageKey;
}

/**
 * Registra la aceptación: valida, guarda firma y comprobante en el directorio
 * privado, actualiza la fila, rota el token público y emite el token de comprobante.
 * $ctx: ['ip' => string, 'user_agent' => string, 'meta' => array].
 */
function cb_accept_plan(array $row, array $input, array $ctx): array
{
    if ((string) $row['status'] !== 'pending') {
        return ['ok' => false, 'errors' => ['status' => 'Este enlace ya no está disponible para aceptar.']];
    }
    if (cb_acceptance_is_expired($row)) {
        return ['ok' => false, 'errors' => ['status' => 'Este enlace venció. Pide a CumpleClick uno nuevo.']];
    }
    $validation = cb_validate_acceptance_submission($input);
    if (!$validation['ok']) {
        return ['ok' => false, 'errors' => $validation['errors']];
    }
    $bundle = cb_legal_bundle();
    if ($bundle['sha256'] !== (string) $row['legal_text_sha256'] || $bundle['version'] !== (string) $row['legal_version']) {
        // El texto cambió después de emitir el enlace: no se puede aceptar algo distinto a lo enviado.
        return ['ok' => false, 'errors' => ['status' => 'Los documentos cambiaron desde que se emitió este enlace. Pide a CumpleClick uno nuevo.']];
    }
    $data = $validation['data'];
    $id = (int) $row['id'];
    $now = gmdate('Y-m-d H:i:s');
    $ip = substr((string) ($ctx['ip'] ?? ''), 0, 45);
    $userAgent = substr((string) ($ctx['user_agent'] ?? ''), 0, 400);
    $meta = is_array($ctx['meta'] ?? null) ? $ctx['meta'] : [];

    $signatureKey = cb_acceptance_storage_key($id, 'firma.png');
    $evidenceKey = cb_acceptance_storage_key($id, 'comprobante.html');
    $signaturePath = cb_acceptance_file_path($signatureKey);
    $evidencePath = cb_acceptance_file_path($evidenceKey);
    if ($signaturePath === null || $evidencePath === null) {
        throw new RuntimeException('Clave de almacenamiento inválida.');
    }
    if (file_put_contents($signaturePath, $data['signature'], LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar la firma.');
    }
    @chmod($signaturePath, 0660);
    $signatureSha = hash('sha256', $data['signature']);

    $accepted = $row;
    $accepted['accepted_at'] = $now;
    $accepted['signer_name'] = $data['signer_name'];
    $accepted['signer_rut'] = $data['signer_rut'];
    $accepted['signer_email'] = $data['signer_email'];
    $accepted['signer_relationship'] = $data['signer_relationship'];
    $accepted['accepted_terms'] = 1;
    $accepted['accepted_privacy'] = 1;
    $accepted['accepted_minors'] = 1;
    $accepted['accepted_marketing'] = $data['accepted_marketing'] ? 1 : 0;
    $accepted['ip_address'] = $ip;
    $accepted['user_agent'] = $userAgent;
    $accepted['client_meta'] = $meta;
    $accepted['signature_sha256'] = $signatureSha;
    $evidenceHtml = cb_acceptance_evidence_html($accepted, $bundle, base64_encode($data['signature']));
    if (file_put_contents($evidencePath, $evidenceHtml, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar el comprobante.');
    }
    @chmod($evidencePath, 0660);
    $evidenceSha = hash('sha256', $evidenceHtml);

    $receiptToken = cb_opaque_token(16);
    cb_pdo()->prepare('UPDATE cc_plan_acceptances SET
            status=\'accepted\', accepted_at=?, signer_name=?, signer_rut=?, signer_email=?, signer_relationship=?,
            accepted_terms=1, accepted_privacy=1, accepted_minors=1, accepted_marketing=?,
            signature_storage_key=?, signature_sha256=?, evidence_storage_key=?, evidence_sha256=?,
            ip_address=?, user_agent=?, client_meta_json=?, public_token_hash=?, receipt_token_hash=?, updated_at=?
        WHERE id=? AND status=\'pending\'')
        ->execute([
            $now, $data['signer_name'], $data['signer_rut'], $data['signer_email'], $data['signer_relationship'],
            $data['accepted_marketing'] ? 1 : 0,
            $signatureKey, $signatureSha, $evidenceKey, $evidenceSha,
            $ip !== '' ? $ip : null, $userAgent !== '' ? $userAgent : null, json_encode($meta, JSON_UNESCAPED_UNICODE),
            // Rotación: el enlace de aceptación deja de funcionar; queda un hash inalcanzable.
            hash('sha256', 'used:' . $row['public_token_hash']), cb_hash_token($receiptToken), $now,
            $id,
        ]);
    return ['ok' => true, 'id' => $id, 'receipt_token' => $receiptToken, 'evidence_sha256' => $evidenceSha, 'signature_sha256' => $signatureSha];
}

/** Comprobante HTML autocontenido (texto íntegro + firma embebida + evidencia técnica). */
function cb_acceptance_evidence_html(array $row, array $bundle, string $signaturePngBase64): string
{
    $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $summary = is_array($row['plan_summary'] ?? null) ? $row['plan_summary'] : [];
    $labels = cb_acceptance_summary_fields();
    $summaryRows = '';
    foreach ($labels as $key => $label) {
        if (isset($summary[$key]) && $summary[$key] !== '') {
            $summaryRows .= '<tr><th>' . $e($label) . '</th><td>' . nl2br($e($summary[$key])) . '</td></tr>';
        }
    }
    $meta = is_array($row['client_meta'] ?? null) ? $row['client_meta'] : [];
    $metaRows = '';
    foreach ($meta as $key => $value) {
        $metaRows .= '<tr><th>' . $e($key) . '</th><td>' . $e(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . '</td></tr>';
    }
    $documentsHtml = '';
    foreach ($bundle['documents'] as $doc) {
        $documentsHtml .= '<section class="doc">' . $doc['html'] . '</section>';
    }
    $checks = [
        ['Términos y Condiciones', !empty($row['accepted_terms'])],
        ['Política de Privacidad', !empty($row['accepted_privacy'])],
        ['Consentimiento de imagen de menores', !empty($row['accepted_minors'])],
        ['Uso promocional de imágenes (opcional)', !empty($row['accepted_marketing'])],
    ];
    $checksHtml = '';
    foreach ($checks as [$label, $on]) {
        $checksHtml .= '<li>' . ($on ? '[X]' : '[ ]') . ' ' . $e($label) . '</li>';
    }
    $relationships = ['madre' => 'Madre', 'padre' => 'Padre', 'tutor' => 'Tutor/a legal', 'autorizado' => 'Adulto autorizado por los padres'];
    $acceptedAt = (string) ($row['accepted_at'] ?? '');
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Comprobante de aceptación CumpleClick #' . $e($row['id']) . '</title>'
        // Documento probatorio: colores fijos (sin modo oscuro) para que se lea e imprima igual en cualquier visor.
        . '<style>:root{color-scheme:light only}html,body{background:#fff}body{font-family:Georgia,"Times New Roman",serif;max-width:860px;margin:0 auto;padding:2rem 1.25rem;color:#1a1a1a;line-height:1.5}'
        . 'h1{font-size:1.5rem}h2{font-size:1.2rem;margin-top:2rem;border-bottom:1px solid #ccc}h3{font-size:1.05rem}table{border-collapse:collapse;width:100%;margin:.75rem 0}'
        . 'th,td{border:1px solid #ccc;padding:.4rem .6rem;text-align:left;vertical-align:top;font-size:.92rem}th{background:#f3f3f3;width:32%}'
        . '.box{border:1px solid #999;padding:1rem;margin:1rem 0;background:#fafafa}.sig{max-width:420px;border:1px solid #999;background:#fff;display:block}'
        . 'code{font-family:Consolas,monospace;font-size:.85rem;word-break:break-all}.doc{margin-top:1.5rem}blockquote{border-left:4px solid #c9a227;margin:1rem 0;padding:.5rem 1rem;background:#fff8e1}'
        . '.legal-version{color:#666;font-size:.85rem}</style></head><body>'
        . '<h1>Comprobante de aceptación de Términos y firma electrónica simple</h1>'
        . '<p><strong>CumpleClick</strong> · AUTOMATIZATECH SpA · Comprobante N° <strong>' . $e($row['id']) . '</strong> · Fiesta: <code>' . $e($row['party_public_slug']) . '</code></p>'
        . '<h2>1. Resumen del Plan</h2><table>' . $summaryRows . '</table>'
        . '<h2>2. Cliente y firmante</h2><table>'
        . '<tr><th>Cliente (según enlace emitido)</th><td>' . $e($row['client_name']) . ' · ' . $e($row['client_email']) . ($row['client_phone'] ? ' · ' . $e($row['client_phone']) : '') . '</td></tr>'
        . '<tr><th>Nombre del firmante</th><td>' . $e($row['signer_name']) . '</td></tr>'
        . '<tr><th>RUT</th><td>' . $e($row['signer_rut']) . '</td></tr>'
        . '<tr><th>Correo del firmante</th><td>' . $e($row['signer_email']) . '</td></tr>'
        . '<tr><th>Relación con el homenajeado</th><td>' . $e($relationships[(string) ($row['signer_relationship'] ?? '')] ?? $row['signer_relationship']) . '</td></tr>'
        . '</table>'
        . '<h2>3. Declaraciones aceptadas</h2><ul>' . $checksHtml . '</ul>'
        . '<h2>4. Firma electrónica simple</h2><div class="box"><img class="sig" alt="Firma trazada por el cliente" src="data:image/png;base64,' . $signaturePngBase64 . '">'
        . '<p>SHA-256 de la imagen de firma: <code>' . $e($row['signature_sha256']) . '</code></p>'
        . '<p>Firma electrónica simple conforme a la Ley N° 19.799 (Chile). El firmante trazó su firma en pantalla y marcó las casillas de aceptación de manera consciente e inequívoca.</p></div>'
        . '<h2>5. Evidencia técnica</h2><table>'
        . '<tr><th>Fecha y hora de aceptación (UTC)</th><td>' . $e($acceptedAt) . '</td></tr>'
        . '<tr><th>Fecha y hora (Chile)</th><td>' . $e(cb_chile_datetime($acceptedAt)) . '</td></tr>'
        . '<tr><th>Dirección IP</th><td>' . $e($row['ip_address']) . '</td></tr>'
        . '<tr><th>Navegador (user-agent)</th><td>' . $e($row['user_agent']) . '</td></tr>'
        . '<tr><th>Versión de los documentos</th><td>' . $e($bundle['version']) . '</td></tr>'
        . '<tr><th>SHA-256 del texto aceptado</th><td><code>' . $e($bundle['sha256']) . '</code></td></tr>'
        . '<tr><th>Enlace emitido el (UTC)</th><td>' . $e($row['created_at']) . ' por ' . $e($row['created_by']) . '</td></tr>'
        . '<tr><th>Primera visualización (UTC)</th><td>' . $e($row['first_viewed_at'] ?? '') . '</td></tr>'
        . $metaRows
        . '</table>'
        . '<p>El SHA-256 de este archivo se calcula al guardarlo y queda registrado en la base de datos de CumpleClick; cualquier alteración posterior del archivo cambia ese valor.</p>'
        . '<h2>6. Texto íntegro aceptado</h2>' . $documentsHtml
        . '</body></html>';
}

function cb_revoke_acceptance(int $id, string $by): bool
{
    $now = gmdate('Y-m-d H:i:s');
    $stmt = cb_pdo()->prepare('UPDATE cc_plan_acceptances SET status=\'revoked\', revoked_at=?, updated_at=?, created_by=COALESCE(created_by, ?) WHERE id=? AND status IN (\'pending\',\'waived\')');
    $stmt->execute([$now, $now, $by, $id]);
    return $stmt->rowCount() > 0;
}

/** Exención explícita (fiestas demo o internas). Queda auditada con motivo y autor. */
function cb_waive_acceptance(array $party, string $reason, string $by): array
{
    $publicSlug = (string) ($party['public_slug'] ?? '');
    $reason = cb_acceptance_text($reason, 255);
    if (strlen($reason) < 5) {
        return ['ok' => false, 'errors' => ['reason' => 'Indica un motivo (mínimo 5 caracteres).']];
    }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) {
        return ['ok' => false, 'errors' => ['party' => 'Fiesta inválida.']];
    }
    $state = cb_party_acceptance_state($publicSlug);
    if ($state['status'] === 'accepted') {
        return ['ok' => false, 'errors' => ['status' => 'La fiesta ya tiene una aceptación firmada; no hace falta eximirla.']];
    }
    $now = gmdate('Y-m-d H:i:s');
    cb_pdo()->prepare('INSERT INTO cc_plan_acceptances
        (party_id, party_public_slug, party_admin_label, public_token_hash, status, plan_code, plan_summary_json, client_name, client_email,
         legal_version, legal_text_sha256, waived_reason, created_at, updated_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $partyId, $publicSlug, (string) ($party['admin_label'] ?? ''), hash('sha256', 'waived:' . cb_opaque_token(16)), 'waived',
            (string) ($party['service_plan'] ?? 'booth'), json_encode(['motivo' => $reason], JSON_UNESCAPED_UNICODE), '', '',
            CB_LEGAL_VERSION, str_repeat('0', 64), $reason, $now, $now, $by,
        ]);
    return ['ok' => true];
}

/**
 * Correo de la aceptación. Fail-soft: nunca rompe la firma si el envío falla.
 *
 * `$html` es opcional: cuando viene, el correo sale con la plantilla de la marca y el texto
 * plano queda como alternativa para los clientes que no muestran HTML. El respaldo por
 * `mail()` nativo manda solo el texto, que es lo que ese camino sabe hacer.
 */
function cb_send_mail(string $to, string $subject, string $body, string $html = ''): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = (string) cb_config('mail_from');
    if ($from === '') {
        $host = (string) parse_url(cb_public_base_url(), PHP_URL_HOST);
        $from = 'no-reply@' . ($host !== '' ? preg_replace('/^www\./', '', $host) : 'localhost');
    }
    $replyTo = (string) cb_config('notify_email');
    $headers = 'From: CumpleClick <' . $from . ">\r\n"
        . ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? 'Reply-To: ' . $replyTo . "\r\n" : '')
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    try {
        // SMTP real cuando está configurado (el mismo que confirma las solicitudes del
        // sitio). mail() queda de respaldo para entornos sin SMTP, como el WAMP local.
        if (function_exists('cc_mail_send') && function_exists('cc_mail_enabled') && cc_mail_enabled()) {
            $envio = cc_mail_send([
                'to' => $to,
                'subject' => $subject,
                'text' => $body,
                'html' => $html,
                'reply_to' => $replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : '',
            ]);
            if (!empty($envio['ok'])) {
                return true;
            }
            error_log('CumpleClick mail SMTP: ' . (string) ($envio['error'] ?? 'sin detalle'));
        }
        return @mail($to, $encodedSubject, $body, $headers);
    } catch (Throwable $e) {
        error_log('CumpleClick mail: ' . $e->getMessage());
        return false;
    }
}

/** Notifica al cliente (copia del comprobante) y a AT. Registra en la fila qué se envió. */
function cb_acceptance_send_notifications(array $row, string $receiptToken): array
{
    $receiptUrl = cb_acceptance_receipt_url($receiptToken);
    $summary = is_array($row['plan_summary'] ?? null) ? $row['plan_summary'] : [];
    $planName = (string) ($summary['plan_name'] ?? $row['plan_code']);
    $eventDate = (string) ($summary['event_date'] ?? '');
    $sent = ['client' => false, 'internal' => false];
    $now = gmdate('Y-m-d H:i:s');

    require_once __DIR__ . '/lib.mail-templates.php';

    $aceptado = cb_chile_datetime((string) $row['accepted_at']);
    // La fecha del evento viene en ISO desde el resumen del plan; al cliente se le muestra
    // como se escribe en Chile.
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $eventDate, $iso)) {
        $eventDate = $iso[3] . '-' . $iso[2] . '-' . $iso[1];
    }
    $huella = (string) $row['evidence_sha256'];

    // El texto plano se mantiene: es la alternativa del correo y lo que sale por el
    // respaldo de mail(). El HTML es lo que ve casi todo el mundo.
    $clientBody = "Hola " . $row['signer_name'] . ",\n\n"
        . "Recibimos tu aceptación de los Términos y Condiciones, la Política de Privacidad y el Consentimiento de imagen de menores de CumpleClick.\n\n"
        . "Plan: $planName\n" . ($eventDate !== '' ? "Fecha del evento: $eventDate\n" : '')
        . "Fecha de aceptación: $aceptado\n"
        . "Versión de los documentos: " . $row['legal_version'] . "\n"
        . "Huella (SHA-256) del comprobante: $huella\n\n"
        . "Puedes descargar tu copia del comprobante firmado aquí (guárdala; el enlace es personal):\n$receiptUrl\n\n"
        . "Recuerda que la reserva queda confirmada al recibir el anticipo indicado en el Resumen del Plan.\n\n"
        . "CumpleClick · AutomatizaTech\n";

    $filasCliente = cc_mail_fila('Plan', $planName)
        . cc_mail_fila('Fecha del evento', $eventDate)
        . cc_mail_fila('Aceptado el', $aceptado)
        . cc_mail_fila('Versión de los documentos', (string) $row['legal_version']);
    $contenidoCliente = '<p style="margin:0 0 16px">Hola ' . cc_mail_h((string) $row['signer_name'])
        . ', recibimos tu aceptación de los <strong>Términos y Condiciones</strong>, la '
        . '<strong>Política de Privacidad</strong> y el <strong>Consentimiento de imagen de menores</strong>.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px">'
        . $filasCliente . '</table>'
        . '<p style="margin:0 0 8px"><a href="' . cc_mail_h($receiptUrl) . '" '
        . 'style="display:inline-block;background:#7C3AED;color:#ffffff;text-decoration:none;'
        . 'padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px">Descargar mi comprobante firmado</a></p>'
        . '<p style="margin:0 0 18px;font-size:13px;color:#6B6280">Guarda este enlace: es personal y es tu copia del documento firmado.</p>'
        . '<p style="margin:0 0 16px">La reserva queda confirmada al recibir el anticipo indicado en el Resumen del Plan.</p>'
        // La huella va al final y en letra chica: es respaldo legal, no lo que la persona
        // vino a leer, pero tiene que ir en el correo para que quede en su bandeja.
        . '<p style="margin:0;font-size:11px;color:#8B85A0;word-break:break-all">'
        . 'Huella SHA-256 del comprobante: ' . cc_mail_h($huella) . '</p>';

    $clientTo = (string) ($row['signer_email'] ?: $row['client_email']);
    if (cb_send_mail($clientTo, 'CumpleClick: comprobante de aceptación de Términos', $clientBody,
                     cc_mail_shell('Comprobante de aceptación', $contenidoCliente))) {
        $sent['client'] = true;
        cb_pdo()->prepare('UPDATE cc_plan_acceptances SET client_mail_sent_at=? WHERE id=?')->execute([$now, (int) $row['id']]);
    }

    $notify = (string) cb_config('notify_email');
    if ($notify !== '') {
        $urlAdmin = cb_public_base_url() . '/admin/aceptaciones.php?party=' . rawurlencode((string) $row['party_public_slug']);
        $internalBody = "Aceptación registrada en CumpleClick.\n\n"
            . "Comprobante N°: " . $row['id'] . "\nFiesta: " . $row['party_public_slug'] . " (" . $row['party_admin_label'] . ")\n"
            . "Cliente: " . $row['client_name'] . " <" . $row['client_email'] . ">\n"
            . "Firmante: " . $row['signer_name'] . " · RUT " . $row['signer_rut'] . " · " . $row['signer_email'] . "\n"
            . "Plan: $planName" . ($eventDate !== '' ? " · Evento: $eventDate" : '') . "\n"
            . "Marketing autorizado: " . (!empty($row['accepted_marketing']) ? 'SÍ' : 'NO') . "\n"
            . "Aceptado: $aceptado · IP " . $row['ip_address'] . "\n"
            . "SHA-256 comprobante: $huella\n\n"
            . "Descarga la evidencia desde el backoffice: $urlAdmin\n";

        $filasInternas = cc_mail_fila('Comprobante N°', (string) $row['id'])
            . cc_mail_fila('Fiesta', (string) $row['party_public_slug'] . ' (' . (string) $row['party_admin_label'] . ')')
            . cc_mail_fila('Cliente', (string) $row['client_name'] . ' · ' . (string) $row['client_email'])
            . cc_mail_fila('Firmante', (string) $row['signer_name'] . ' · RUT ' . (string) $row['signer_rut'])
            . cc_mail_fila('Plan', $planName . ($eventDate !== '' ? ' · Evento: ' . $eventDate : ''))
            . cc_mail_fila('Marketing autorizado', !empty($row['accepted_marketing']) ? 'SÍ' : 'NO')
            . cc_mail_fila('Aceptado', $aceptado . ' · IP ' . (string) $row['ip_address']);
        $contenidoInterno = '<p style="margin:0 0 16px">Se registró una aceptación firmada.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px">'
            . $filasInternas . '</table>'
            . '<p style="margin:0 0 18px"><a href="' . cc_mail_h($urlAdmin) . '" '
            . 'style="display:inline-block;background:#7C3AED;color:#ffffff;text-decoration:none;'
            . 'padding:12px 24px;border-radius:999px;font-weight:700;font-size:15px">Ver la evidencia en el admin</a></p>'
            . '<p style="margin:0;font-size:11px;color:#8B85A0;word-break:break-all">SHA-256: ' . cc_mail_h($huella) . '</p>';

        if (cb_send_mail($notify, 'CumpleClick: nueva aceptación firmada (#' . $row['id'] . ')', $internalBody,
                         cc_mail_shell('Nueva aceptación firmada', $contenidoInterno))) {
            $sent['internal'] = true;
            cb_pdo()->prepare('UPDATE cc_plan_acceptances SET internal_mail_sent_at=? WHERE id=?')->execute([$now, (int) $row['id']]);
        }
    }
    return $sent;
}
