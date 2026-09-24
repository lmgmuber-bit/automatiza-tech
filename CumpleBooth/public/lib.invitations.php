<?php
/**
 * lib.invitations.php — funciones del módulo de invitaciones (Gate A).
 * Requiere storage_mode=db. Los archivos se almacenan fuera del webroot.
 */

function cb_invitation_storage_key(string $publicSlug, string $assetKey, int $version, string $ext): string
{
    if (!cb_valid_public_slug($publicSlug)) {
        throw new InvalidArgumentException('public_slug inválido para almacenar output.');
    }
    if (!preg_match('/^[a-z0-9-]{1,80}$/', $assetKey)) {
        throw new InvalidArgumentException('asset_key inválido.');
    }
    $ext = strtolower(ltrim($ext, '.'));
    // mp3: la narración de inicio (personalized_narration_intro) es una salida
    // de primera clase desde 2026-08 — la validación de subida ya la aceptaba
    // pero esta lista quedó atrás, así que ni el admin podía guardarla: el
    // primer intento real (narración de Samantha, 2026-09-02) reventó aquí.
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'mp4', 'mp3'], true)) {
        throw new InvalidArgumentException('Extensión de archivo no permitida.');
    }
    $rand = bin2hex(random_bytes(4));
    return $publicSlug . '/' . $assetKey . '-v' . $version . '-' . $rand . '.' . $ext;
}

function cb_invitation_file_path(string $storageKey): ?string
{
    if (!preg_match('#^[a-z0-9-]{1,80}/[a-z0-9-]{1,80}-v\d+-[a-f0-9]{8}\.[a-z0-9]{1,6}$#', $storageKey)) {
        return null;
    }
    return cb_invitation_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
}

/** Devuelve los campos obligatorios que faltan o están vacíos. */
function cb_invitation_mandatory_missing(array $invitation): array
{
    $esBabyShower = (string) ($invitation['event_type'] ?? 'child_birthday') === 'baby_shower';
    $missing = [];
    // En un baby shower el nombre puede no existir todavía, y es un caso
    // corriente, no un formulario a medio llenar: muchas familias hacen la
    // fiesta antes de decidirlo, o lo guardan para el nacimiento. Exigirlo
    // dejaba esas invitaciones sin poder publicarse. La fecha, la hora y la
    // dirección sí siguen siendo obligatorias — eso se sabe siempre, porque
    // es el dato de la fiesta y no del bebé.
    $campos = ['event_date' => 'fecha', 'event_time' => 'hora', 'address' => 'dirección'];
    if (!$esBabyShower) {
        $campos = ['birthday_person_name' => 'nombre del cumpleañero'] + $campos;
    }
    foreach ($campos as $field => $label) {
        $value = trim((string) ($invitation[$field] ?? ''));
        if ($value === '' || $value === '0000-00-00') {
            $missing[] = $label;
        }
    }
    return $missing;
}

function cb_invitation_can_publish(array $invitation, array $approvedOutputs): bool
{
    if (!empty(cb_invitation_mandatory_missing($invitation))) {
        return false;
    }
    foreach ($approvedOutputs as $output) {
        if ((string) ($output['status'] ?? '') === 'approved' && (string) ($output['output_type'] ?? '') === 'personalized_image') {
            return true;
        }
    }
    return false;
}

function cb_invitation_approved_outputs(int $invitationId, ?string $type = null): array
{
    // La narración de Alice del INICIO es dinámica (lleva el nombre/fecha del
    // cumpleañero) y se aprueba invitación por invitación, igual que la
    // imagen/video. El resto del audio (despedida, cápsulas del modo video)
    // es texto fijo por tema y vive como archivo estático — no pasa por acá.
    $allowed = ['personalized_image', 'personalized_video', 'personalized_narration_intro'];
    $outputs = cb_load_invitation_outputs($invitationId);
    return array_values(array_filter($outputs, static function (array $o) use ($type, $allowed): bool {
        if ((string) ($o['status'] ?? '') !== 'approved') {
            return false;
        }
        if (!in_array((string) ($o['output_type'] ?? ''), $allowed, true)) {
            return false;
        }
        if ($type !== null && (string) ($o['output_type'] ?? '') !== $type) {
            return false;
        }
        return true;
    }));
}

function cb_default_invitation_prompt_template(): string
{
    return 'Crea una imagen vibrante y festiva de cumpleaños infantil para [NOMBRE_DEL_CUMPLEAÑERO]. '
        . 'La celebración será el [FECHA_Y_HORA] en [DIRECCIÓN]. '
        . 'Estilo amigable, colorido y sin texto superpuesto, apropiado para una invitación digital.';
}

function cb_validate_invitation_prompt_template(string $promptTemplate, array $themeData): array
{
    $promptTemplate = trim($promptTemplate);
    if ($promptTemplate === '') {
        return ['ok' => false, 'error' => 'El prompt no puede estar vacío.'];
    }
    if (strlen($promptTemplate) > 20000) {
        return ['ok' => false, 'error' => 'El prompt supera el máximo de 20.000 caracteres.'];
    }
    $compiled = cb_compile_invitation_prompt($promptTemplate, [
        'birthday_person_name' => 'Ana',
        'event_date' => '2030-06-15',
        'event_time' => '15:00',
        'address' => 'Salón de prueba',
    ]);
    if (!$compiled['ok']) {
        return ['ok' => false, 'error' => $compiled['error']];
    }
    foreach (cb_theme_prompt_forbidden_terms($themeData) as $term) {
        $pattern = '/(?<![\pL\pN])' . preg_quote($term, '/') . '(?![\pL\pN])/iu';
        if (preg_match($pattern, $promptTemplate) === 1) {
            return ['ok' => false, 'error' => 'El prompt contiene un nombre reservado de franquicia o personaje. Describe únicamente rasgos físicos.'];
        }
    }
    return ['ok' => true, 'prompt' => $promptTemplate];
}

function cb_create_invitation(array $data): array
{
    if (cb_storage_mode() !== 'db') {
        return ['ok' => false, 'error' => 'Las invitaciones requieren storage_mode=db.'];
    }
    $pdo = cb_pdo();
    $token = cb_opaque_token(16);
    $tokenHash = cb_hash_token($token);
    $now = gmdate('Y-m-d H:i:s');
    $partyId = isset($data['party_id']) && $data['party_id'] !== null ? (int) $data['party_id'] : null;
    $themeSlug = isset($data['theme_slug']) && $data['theme_slug'] !== null ? (string) $data['theme_slug'] : null;
    $adminLabel = (string) ($data['admin_label'] ?? '');
    $birthdayPersonName = trim((string) ($data['birthday_person_name'] ?? ''));
    $eventDate = trim((string) ($data['event_date'] ?? ''));
    $eventTime = trim((string) ($data['event_time'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));
    $eventType = (string) ($data['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
    $language = in_array((string) ($data['language'] ?? ''), ['es', 'en', 'pt'], true) ? (string) $data['language'] : 'es';
    $channel = in_array((string) ($data['channel'] ?? ''), ['whatsapp', 'email', 'print'], true) ? (string) $data['channel'] : 'whatsapp';
    // Elige la narración de cierre de Alice ("cumpleañero" vs "cumpleañera").
    // NULL/vacío = sin especificar, cae al audio neutro (ver invitacion.php).
    $genderRaw = (string) ($data['birthday_person_gender'] ?? '');
    $gender = in_array($genderRaw, ['m', 'f'], true) ? $genderRaw : null;
    $status = 'draft';
    $createdBy = (string) ($data['created_by'] ?? '');
    $promptTemplate = trim((string) ($data['prompt_template'] ?? ''));
    if ($promptTemplate === '') {
        $promptTemplate = cb_default_invitation_prompt_template();
    }

    if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        return ['ok' => false, 'error' => 'La fecha debe tener formato AAAA-MM-DD.'];
    }
    if ($eventTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $eventTime)) {
        return ['ok' => false, 'error' => 'La hora debe tener formato HH:MM.'];
    }

    $check = $pdo->prepare('SELECT 1 FROM cc_invitations WHERE public_token_hash = ?');
    $attempts = 0;
    while ($attempts < 10) {
        $check->execute([$tokenHash]);
        if ($check->fetch() === false) {
            break;
        }
        $token = cb_opaque_token(16);
        $tokenHash = cb_hash_token($token);
        $attempts++;
    }
    if ($attempts >= 10) {
        return ['ok' => false, 'error' => 'No se pudo generar un token de invitación único.'];
    }

    $stmt = $pdo->prepare('INSERT INTO cc_invitations (public_token_hash, party_id, theme_slug, admin_label, birthday_person_name, birthday_person_gender, event_type, event_date, event_time, address, message, language, channel, status, prompt_template, created_at, updated_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$tokenHash, $partyId, $themeSlug, $adminLabel, $birthdayPersonName, $gender, $eventType, $eventDate, $eventTime, $address, $message, $language, $channel, $status, $promptTemplate, $now, $now, $createdBy]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'token' => $token];
}

function cb_load_invitation_by_token_hash(string $tokenHash): ?array
{
    if (cb_storage_mode() !== 'db' || !preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
        return null;
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT * FROM cc_invitations WHERE public_token_hash = ?');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cb_load_invitation_by_id(int $id): ?array
{
    if (cb_storage_mode() !== 'db') {
        return null;
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT * FROM cc_invitations WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Comprobación reutilizable de ownership: devuelve la invitación SOLO si
 * pertenece exactamente a la fiesta indicada. Una invitación sin fiesta
 * asociada (party_id NULL) o de otra fiesta devuelve null, igual que una
 * invitación inexistente — el llamador no debe distinguir el motivo, para no
 * filtrar por el mensaje de error si un ID existe pero es de otro dueño.
 */
function cb_invitation_owned_by_party(int $invitationId, int $partyId): ?array
{
    $invitation = cb_load_invitation_by_id($invitationId);
    if ($invitation === null) {
        return null;
    }
    if ((int) ($invitation['party_id'] ?? 0) !== $partyId) {
        return null;
    }
    return $invitation;
}

/** Igual que cb_invitation_owned_by_party pero trazando output_id -> invitation_id
 * -> party_id de punta a punta. Nunca confiar en un invitation_id posteado junto
 * al output_id: siempre se resuelve el dueño real desde la fila del output. */
function cb_invitation_output_owned_by_party(int $outputId, int $partyId): ?array
{
    if (cb_storage_mode() !== 'db') {
        return null;
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT * FROM cc_invitation_outputs WHERE id = ?');
    $stmt->execute([$outputId]);
    $output = $stmt->fetch();
    if (!$output) {
        return null;
    }
    if (cb_invitation_owned_by_party((int) $output['invitation_id'], $partyId) === null) {
        return null;
    }
    return $output;
}

function cb_list_invitations(?int $partyId = null, ?string $themeSlug = null): array
{
    if (cb_storage_mode() !== 'db') {
        return [];
    }
    $pdo = cb_pdo();
    if ($partyId !== null) {
        $stmt = $pdo->prepare('SELECT * FROM cc_invitations WHERE party_id = ? ORDER BY created_at DESC');
        $stmt->execute([$partyId]);
    } elseif ($themeSlug !== null) {
        $stmt = $pdo->prepare('SELECT * FROM cc_invitations WHERE theme_slug = ? AND party_id IS NULL ORDER BY created_at DESC');
        $stmt->execute([$themeSlug]);
    } else {
        $stmt = $pdo->query('SELECT * FROM cc_invitations ORDER BY created_at DESC');
    }
    return $stmt->fetchAll();
}

function cb_load_invitation_outputs(int $invitationId): array
{
    if (cb_storage_mode() !== 'db') {
        return [];
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT * FROM cc_invitation_outputs WHERE invitation_id = ? ORDER BY created_at DESC');
    $stmt->execute([$invitationId]);
    return $stmt->fetchAll();
}

function cb_save_invitation_output(int $invitationId, array $data): array
{
    if (cb_storage_mode() !== 'db') {
        return ['ok' => false, 'error' => 'Los outputs requieren storage_mode=db.'];
    }
    $pdo = cb_pdo();
    $assetKey = (string) ($data['asset_key'] ?? '');
    $outputType = in_array((string) ($data['output_type'] ?? ''), ['personalized_image', 'personalized_video', 'personalized_narration_intro'], true) ? (string) $data['output_type'] : 'personalized_image';
    $fileStorageKey = (string) ($data['file_storage_key'] ?? '');
    $status = in_array((string) ($data['status'] ?? ''), ['pending', 'approved', 'rejected'], true) ? (string) $data['status'] : 'pending';
    $visualSource = is_array($data['visual_source_json'] ?? null) ? json_encode($data['visual_source_json']) : (string) ($data['visual_source_json'] ?? '');
    $fileMime = (string) ($data['file_mime'] ?? '');
    $fileByteSize = isset($data['file_byte_size']) ? (int) $data['file_byte_size'] : null;
    $fileSha256 = (string) ($data['file_sha256'] ?? '');
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO cc_invitation_outputs (invitation_id, output_type, asset_key, file_storage_key, status, visual_source_json, file_mime, file_byte_size, file_sha256, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$invitationId, $outputType, $assetKey, $fileStorageKey, $status, $visualSource, $fileMime, $fileByteSize, $fileSha256, $now, $now]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

function cb_update_invitation_output_status(int $outputId, string $status, string $by): bool
{
    if (cb_storage_mode() !== 'db' || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }
    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE cc_invitation_outputs SET status=?, reviewed_at=?, reviewed_by=?, updated_at=? WHERE id=?');
    $stmt->execute([$status, $now, $by, $now, $outputId]);
    return $stmt->rowCount() > 0;
}

function cb_update_invitation_status(int $invitationId, string $status, string $by): bool
{
    if (cb_storage_mode() !== 'db' || !in_array($status, ['draft', 'pending', 'approved', 'published', 'revoked', 'archived'], true)) {
        return false;
    }
    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $fields = ['status = ?'];
    $params = [$status];
    if ($status === 'approved') {
        $fields[] = 'approved_at = ?';
        $fields[] = 'approved_by = ?';
        $params[] = $now;
        $params[] = $by;
    }
    if ($status === 'published') {
        $fields[] = 'published_at = ?';
        $fields[] = 'published_by = ?';
        $params[] = $now;
        $params[] = $by;
    }
    if ($status === 'revoked') {
        $fields[] = 'revoked_at = ?';
        $fields[] = 'revoked_by = ?';
        $params[] = $now;
        $params[] = $by;
    }
    $fields[] = 'updated_at = ?';
    $params[] = $now;
    $params[] = $invitationId;
    $stmt = $pdo->prepare('UPDATE cc_invitations SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function cb_publish_invitation(int $invitationId, string $by): array
{
    $invitation = cb_load_invitation_by_id($invitationId);
    if (!$invitation) {
        return ['ok' => false, 'error' => 'Invitación no encontrada.'];
    }
    $approved = cb_invitation_approved_outputs($invitationId);
    if (!cb_invitation_can_publish($invitation, $approved)) {
        $missing = cb_invitation_mandatory_missing($invitation);
        if (!empty($missing)) {
            return ['ok' => false, 'error' => 'Faltan datos obligatorios: ' . implode(', ', $missing) . '.'];
        }
        return ['ok' => false, 'error' => 'Se requiere al menos una imagen personalizada aprobada para publicar.'];
    }
    return ['ok' => cb_update_invitation_status($invitationId, 'published', $by)];
}

function cb_revoke_invitation(int $invitationId, string $by): bool
{
    return cb_update_invitation_status($invitationId, 'revoked', $by);
}

function cb_regenerate_invitation_token(int $invitationId): ?string
{
    if (cb_storage_mode() !== 'db') {
        return null;
    }
    $pdo = cb_pdo();
    $token = cb_opaque_token(16);
    $tokenHash = cb_hash_token($token);
    $check = $pdo->prepare('SELECT 1 FROM cc_invitations WHERE public_token_hash = ?');
    $attempts = 0;
    while ($attempts < 10) {
        $check->execute([$tokenHash]);
        if ($check->fetch() === false) {
            break;
        }
        $token = cb_opaque_token(16);
        $tokenHash = cb_hash_token($token);
        $attempts++;
    }
    if ($attempts >= 10) {
        return null;
    }
    $stmt = $pdo->prepare('UPDATE cc_invitations SET public_token_hash = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$tokenHash, gmdate('Y-m-d H:i:s'), $invitationId]);
    return $stmt->rowCount() > 0 ? $token : null;
}

function cb_increment_invitation_download(int $invitationId): void
{
    if (cb_storage_mode() !== 'db') {
        return;
    }
    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE cc_invitations SET download_count = download_count + 1, last_downloaded_at = ? WHERE id = ?');
    $stmt->execute([$now, $invitationId]);
}

function cb_create_visual_manifest(?int $partyId, ?int $invitationId, ?string $themeSlug, array $manifest, string $by): int
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('Los manifiestos visuales requieren storage_mode=db.');
    }
    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $json = json_encode($manifest);
    if ($partyId !== null) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 AS next FROM cc_visual_manifests WHERE party_id = ?');
        $stmt->execute([$partyId]);
    } elseif ($invitationId !== null) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 AS next FROM cc_visual_manifests WHERE invitation_id = ?');
        $stmt->execute([$invitationId]);
    } elseif ($themeSlug !== null) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 AS next FROM cc_visual_manifests WHERE theme_slug = ?');
        $stmt->execute([$themeSlug]);
    } else {
        throw new InvalidArgumentException('Se requiere party_id, invitation_id o theme_slug.');
    }
    $version = (int) $stmt->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO cc_visual_manifests (party_id, invitation_id, theme_slug, version, manifest_json, created_at, created_by) VALUES (?,?,?,?,?,?,?)');
    $insert->execute([$partyId, $invitationId, $themeSlug, $version, $json, $now, $by]);
    return (int) $pdo->lastInsertId();
}

function cb_load_latest_visual_manifest(?int $partyId = null, ?int $invitationId = null, ?string $themeSlug = null): ?array
{
    if (cb_storage_mode() !== 'db') {
        return null;
    }
    $pdo = cb_pdo();
    if ($partyId !== null) {
        $stmt = $pdo->prepare('SELECT * FROM cc_visual_manifests WHERE party_id = ? ORDER BY version DESC LIMIT 1');
        $stmt->execute([$partyId]);
    } elseif ($invitationId !== null) {
        $stmt = $pdo->prepare('SELECT * FROM cc_visual_manifests WHERE invitation_id = ? ORDER BY version DESC LIMIT 1');
        $stmt->execute([$invitationId]);
    } elseif ($themeSlug !== null) {
        $stmt = $pdo->prepare('SELECT * FROM cc_visual_manifests WHERE theme_slug = ? ORDER BY version DESC LIMIT 1');
        $stmt->execute([$themeSlug]);
    } else {
        return null;
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

/** URL de descarga directa por token. */
function cb_invitation_download_url(string $token, ?string $type = null): string
{
    $url = cb_public_base_url() . '/descargar-invitacion.php?t=' . urlencode($token);
    if ($type !== null) {
        $url .= '&type=' . urlencode($type);
    }
    return $url;
}

/** URL pública de la página de invitación. */
function cb_invitation_public_url(string $token): string
{
    return cb_public_base_url() . '/invitacion.php?t=' . urlencode($token);
}

/** Actualiza campos editables de una invitación. */
function cb_update_invitation(int $id, array $data, string $by): bool
{
    if (cb_storage_mode() !== 'db') {
        return false;
    }
    $allowed = ['birthday_person_name', 'birthday_person_gender', 'event_date', 'event_time', 'address', 'message', 'admin_label', 'language', 'channel', 'prompt_template'];
    $fields = [];
    $params = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "$key = ?";
            $params[] = (string) $data[$key];
        }
    }
    if (array_key_exists('status', $data)) {
        $status = (string) $data['status'];
        if (!in_array($status, ['draft', 'pending', 'approved', 'published', 'revoked', 'archived'], true)) {
            return false;
        }
        // La transición a 'published' exige validar campos obligatorios y que exista
        // una imagen aprobada — eso SOLO lo hace cb_publish_invitation(). Esta función
        // genérica nunca debe poder escribir 'published' directo sin pasar por ese
        // gate: se rechaza la llamada completa en vez de ignorar el campo en silencio.
        if ($status === 'published') {
            return false;
        }
        $fields[] = 'status = ?';
        $params[] = $status;
        $now = gmdate('Y-m-d H:i:s');
        if ($status === 'approved') {
            $fields[] = 'approved_at = ?';
            $params[] = $now;
            $fields[] = 'approved_by = ?';
            $params[] = $by;
        } elseif ($status === 'published') {
            $fields[] = 'published_at = ?';
            $params[] = $now;
            $fields[] = 'published_by = ?';
            $params[] = $by;
        } elseif ($status === 'revoked') {
            $fields[] = 'revoked_at = ?';
            $params[] = $now;
            $fields[] = 'revoked_by = ?';
            $params[] = $by;
        }
    }
    if (array_key_exists('expires_at', $data)) {
        $expires = (string) $data['expires_at'];
        if ($expires === '' || $expires === '0000-00-00 00:00:00') {
            $fields[] = 'expires_at = NULL';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2})?$/', $expires)) {
            $fields[] = 'expires_at = ?';
            $params[] = strlen($expires) === 10 ? $expires . ' 23:59:59' : $expires . ':00';
        }
    }
    if (empty($fields)) {
        return false;
    }
    $fields[] = 'updated_at = ?';
    $params[] = gmdate('Y-m-d H:i:s');
    $params[] = $id;
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('UPDATE cc_invitations SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

/** Elimina un output y su archivo asociado. */
function cb_delete_invitation_output(int $outputId): bool
{
    if (cb_storage_mode() !== 'db') {
        return false;
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT file_storage_key FROM cc_invitation_outputs WHERE id = ?');
    $stmt->execute([$outputId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $path = cb_invitation_file_path((string) $row['file_storage_key']);
    if ($path && is_file($path)) {
        @unlink($path);
        $dir = dirname($path);
        @rmdir($dir);
    }
    $del = $pdo->prepare('DELETE FROM cc_invitation_outputs WHERE id = ?');
    $del->execute([$outputId]);
    return $del->rowCount() > 0;
}

/** Elimina una invitación, sus outputs y los archivos asociados. */
function cb_delete_invitation(int $id): bool
{
    if (cb_storage_mode() !== 'db') {
        return false;
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT file_storage_key FROM cc_invitation_outputs WHERE invitation_id = ?');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $row) {
        $path = cb_invitation_file_path((string) $row['file_storage_key']);
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
    $del = $pdo->prepare('DELETE FROM cc_invitations WHERE id = ?');
    $del->execute([$id]);
    return $del->rowCount() > 0;
}

/** Duplica una invitación dentro de la misma u otra fiesta, sin copiar outputs ni token. */
function cb_duplicate_invitation(int $id, ?int $targetPartyId, string $by): array
{
    if (cb_storage_mode() !== 'db') {
        return ['ok' => false, 'error' => 'Requiere storage_mode=db.'];
    }
    $source = cb_load_invitation_by_id($id);
    if (!$source) {
        return ['ok' => false, 'error' => 'Invitación origen no encontrada.'];
    }
    $partyId = $targetPartyId ?? (int) ($source['party_id'] ?? 0);
    if ($partyId <= 0) {
        return ['ok' => false, 'error' => 'Fiesta destino inválida.'];
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT public_slug, theme_slug, event_type FROM cc_parties WHERE id = ?');
    $stmt->execute([$partyId]);
    $party = $stmt->fetch();
    if (!$party) {
        return ['ok' => false, 'error' => 'Fiesta destino no encontrada.'];
    }
    $label = trim((string) ($source['admin_label'] ?? ''));
    return cb_create_invitation([
        'party_id' => $partyId,
        'theme_slug' => (string) ($party['theme_slug'] ?? $source['theme_slug'] ?? ''),
        'event_type' => (string) ($party['event_type'] ?? $source['event_type'] ?? 'child_birthday'),
        'admin_label' => $label !== '' ? $label . ' (copia)' : '',
        'birthday_person_name' => trim((string) ($source['birthday_person_name'] ?? '')) . ' (copia)',
        'event_date' => (string) ($source['event_date'] ?? ''),
        'event_time' => (string) ($source['event_time'] ?? ''),
        'address' => (string) ($source['address'] ?? ''),
        'message' => (string) ($source['message'] ?? ''),
        'language' => (string) ($source['language'] ?? 'es'),
        'channel' => (string) ($source['channel'] ?? 'whatsapp'),
        'created_by' => $by,
    ]);
}

/** Valida un archivo subido como output de invitación. */
function cb_validate_invitation_upload(array $file, string $outputType): array
{
    if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error'])) {
        return ['ok' => false, 'error' => 'Datos de archivo incompletos.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error de subida (código ' . $file['error'] . ').'];
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Archivo no válido.'];
    }
    $tmpPath = (string) $file['tmp_name'];
    $size = (int) $file['size'];

    $imageMax = cb_theme_image_max_bytes();
    $videoMax = cb_theme_upload_max_bytes();

    if ($outputType === 'personalized_image') {
        if ($size <= 0 || $size > $imageMax) {
            return ['ok' => false, 'error' => 'La imagen supera el tamaño máximo (' . number_format($imageMax / 1048576, 1) . ' MB).'];
        }
        $info = @getimagesize($tmpPath);
        if ($info === false) {
            return ['ok' => false, 'error' => 'No es una imagen válida.'];
        }
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'Formato de imagen no soportado (usa JPG, PNG o WebP).'];
        }
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 320 || $height < 320 || $width > 8192 || $height > 8192) {
            return ['ok' => false, 'error' => 'Dimensiones fuera de rango (mín. 320×320, máx. 8192×8192).'];
        }
        return ['ok' => true, 'mime' => $mime, 'width' => $width, 'height' => $height, 'byte_size' => $size];
    }

    if ($outputType === 'personalized_video') {
        if ($size <= 0 || $size > $videoMax) {
            return ['ok' => false, 'error' => 'El video supera el tamaño máximo (' . number_format($videoMax / 1048576, 1) . ' MB).'];
        }
        if (!cb_sniff_mp4($tmpPath)) {
            return ['ok' => false, 'error' => 'No parece un archivo MP4 válido.'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            return ['ok' => false, 'error' => 'Extensión de video no permitida (solo .mp4).'];
        }
        // La firma de bytes de arriba solo descarta basura obvia; la duración y el
        // stream de video real se validan con metadata inspeccionada de verdad. Si no
        // se puede inspeccionar (binario ausente, ejecución falla, JSON corrupto), se
        // rechaza — nunca se acepta un video sin haberlo podido validar.
        $meta = cb_inspect_video($tmpPath);
        if ($meta === null) {
            return ['ok' => false, 'error' => 'No se pudo validar la duración/metadata del video.'];
        }
        $maxDuration = cb_theme_video_max_duration_seconds();
        if ($meta['duration'] > $maxDuration) {
            return ['ok' => false, 'error' => 'El video supera la duración máxima (' . $maxDuration . 's).'];
        }
        return ['ok' => true, 'mime' => 'video/mp4', 'byte_size' => $size, 'duration' => $meta['duration']];
    }

    if ($outputType === 'personalized_narration_intro') {
        // La narración de inicio es una frase corta (nombre + fecha + lugar):
        // 5 MB de sobra para un MP3 de pocos segundos, nunca debería acercarse.
        $narrationMax = 5 * 1024 * 1024;
        if ($size <= 0 || $size > $narrationMax) {
            return ['ok' => false, 'error' => 'El audio supera el tamaño máximo (' . number_format($narrationMax / 1048576, 1) . ' MB).'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mp3') {
            return ['ok' => false, 'error' => 'Extensión de audio no permitida (solo .mp3).'];
        }
        // Firma de bytes mínima: ID3v2 al inicio, o el sync word de un frame
        // MPEG audio (11 bits en 1). Descarta basura obvia sin exigir ffprobe.
        $head = @file_get_contents($tmpPath, false, null, 0, 4);
        $looksLikeMp3 = $head !== false && (
            substr($head, 0, 3) === 'ID3'
            || (strlen($head) >= 2 && (ord($head[0]) === 0xFF) && ((ord($head[1]) & 0xE0) === 0xE0))
        );
        if (!$looksLikeMp3) {
            return ['ok' => false, 'error' => 'No parece un archivo MP3 válido.'];
        }
        return ['ok' => true, 'mime' => 'audio/mpeg', 'byte_size' => $size];
    }

    return ['ok' => false, 'error' => 'Tipo de output no válido.'];
}
/** Acepta el token aleatorio legado (32 hex) o un alias firmado (48 hex). */
function cb_invitation_public_token_is_valid(string $token): bool
{
    return preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{48})$/', $token) === 1;
}

/**
 * Slug decorativo para la URL bonita: solo cosmético, nunca autoriza nada.
 * Si viene vacío o queda sin caracteres válidos, devuelve 'invitacion'.
 */
function cb_invitation_name_slug(string $name): string
{
    $slug = $name;
    $from = ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','ú','ù','ü','û','ñ','ç',
             'Á','À','Ä','Â','É','È','Ë','Ê','Í','Ì','Ï','Î','Ó','Ò','Ö','Ô','Ú','Ù','Ü','Û','Ñ','Ç'];
    $to   = ['a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c',
             'a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c'];
    $slug = str_replace($from, $to, $slug);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        return 'invitacion';
    }
    return substr($slug, 0, 40);
}

/**
 * URL pública "bonita": /<nombre>-<token>, un solo segmento.
 *
 * El segmento único NO es capricho: invitacion.php enlaza CSS, JS y videos con
 * rutas relativas, así que cualquier formato más profundo (/i/nombre/token) los
 * manda al catch-all del SPA y la invitación se ve sin estilos ni videos.
 *
 * Depende de la regla de reescritura del .htaccess. Si esa regla no está, esta
 * URL da 404 y hay que usar cb_invitation_public_url(). El token es el mismo y
 * conserva sus 128 bits: el nombre es adorno, no credencial.
 */
function cb_invitation_pretty_url(string $token, string $name): string
{
    return cb_public_base_url() . '/' . cb_invitation_name_slug($name) . '-' . rawurlencode($token);
}

/**
 * Plan contratado de la fiesta dueña de una invitación.
 *
 * Regla comercial canónica (docs/CAMPANA-INVITACIONES-BASICO-FULL-2026-08-11.md):
 *   booth = Plan Básico → invitación Scroll
 *   full  = Plan Full   → invitación Automática
 *
 * Falla cerrado a 'booth': ante cualquier duda se entrega lo contratado en el
 * plan menor, nunca de más.
 */
function cb_invitation_service_plan(?int $partyId): string
{
    if ($partyId === null || $partyId < 1 || cb_storage_mode() !== 'db') {
        return 'booth';
    }
    try {
        $stmt = cb_pdo()->prepare('SELECT service_plan FROM cc_parties WHERE id = ?');
        $stmt->execute([$partyId]);
        $plan = (string) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 'booth';
    }
    return in_array($plan, ['booth', 'full'], true) ? $plan : 'booth';
}

/**
 * Firma de vista previa para el admin.
 *
 * Los parámetros `hero` y `capitulos` dejaron de ser públicos: sin esta firma,
 * el enlace de un invitado entrega siempre la variante de su plan y editar la
 * URL no la cambia. La firma NO caduca a propósito — lo que protege no es un
 * secreto, sino que el plan no se pueda subir a mano desde la barra del
 * navegador; quien reciba un enlace de vista previa ve esa variante y ya.
 */
function cb_invitation_preview_mac(int $invitationId, string $hero, string $chapters): string
{
    return substr(cb_hmac($invitationId . '|' . $hero . '|' . $chapters, 'invitation-preview-v1'), 0, 24);
}

function cb_invitation_preview_ok(int $invitationId, string $hero, string $chapters, string $mac): bool
{
    if ($mac === '') {
        return false;
    }
    return hash_equals(cb_invitation_preview_mac($invitationId, $hero, $chapters), $mac);
}

/**
 * Alias público reconstruible desde el ID, sin guardar el token en texto plano.
 * Mantiene 128 bits de firma HMAC y no reemplaza ni revoca el enlace aleatorio.
 */
function cb_invitation_share_token(int $invitationId): string
{
    if ($invitationId < 1) {
        throw new InvalidArgumentException('ID de invitación inválido.');
    }
    $idHex = str_pad(dechex($invitationId), 16, '0', STR_PAD_LEFT);
    if (strlen($idHex) !== 16 || $idHex[0] > '7') {
        throw new InvalidArgumentException('ID de invitación fuera de rango.');
    }
    return $idHex . substr(cb_hmac($idHex, 'invitation-share-token-v1'), 0, 32);
}

function cb_invitation_id_from_share_token(string $token): ?int
{
    if (preg_match('/^[0-7][a-f0-9]{47}$/', $token) !== 1) {
        return null;
    }
    $idHex = substr($token, 0, 16);
    $providedMac = substr($token, 16);
    $expectedMac = substr(cb_hmac($idHex, 'invitation-share-token-v1'), 0, 32);
    if (!hash_equals($expectedMac, $providedMac)) {
        return null;
    }
    $id = hexdec($idHex);
    return is_int($id) && $id > 0 ? $id : null;
}

/** Resuelve enlaces históricos y aliases firmados sin degradar compatibilidad. */
function cb_load_invitation_by_public_token(string $token): ?array
{
    if (cb_storage_mode() !== 'db' || !cb_invitation_public_token_is_valid($token)) {
        return null;
    }
    if (strlen($token) === 32) {
        return cb_load_invitation_by_token_hash(cb_hash_token($token));
    }
    $invitationId = cb_invitation_id_from_share_token($token);
    return $invitationId !== null ? cb_load_invitation_by_id($invitationId) : null;
}
