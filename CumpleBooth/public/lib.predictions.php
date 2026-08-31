<?php
/** Funciones de predicciones y tokens privados para baby shower. */

function cb_event_type(string $value): string
{
    return $value === 'baby_shower' ? 'baby_shower' : 'child_birthday';
}

function cb_prediction_validate(array $input): array
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($input['guest_name'] ?? '')) ?? '');
    $parecido = (string) ($input['parecido'] ?? '');
    $peso = (string) ($input['peso'] ?? '');
    $fecha = (string) ($input['fecha'] ?? '');
    $submissionToken = (string) ($input['submission_token'] ?? '');
    $scoreRaw = $input['puntaje_juego'] ?? null;

    if ($name === '' || mb_strlen($name) > 80) {
        return ['ok' => false, 'error' => 'Escribe un nombre de hasta 80 caracteres.'];
    }
    if (!in_array($parecido, ['mama', 'papa', 'ambos'], true)) {
        return ['ok' => false, 'error' => 'Elige a quién crees que se parecerá.'];
    }
    if (!in_array($peso, ['menos3', 'entre', 'mas35'], true)) {
        return ['ok' => false, 'error' => 'Elige un rango de peso.'];
    }
    if (!in_array($fecha, ['antes', 'justo', 'despues'], true)) {
        return ['ok' => false, 'error' => 'Elige cuándo crees que llegará.'];
    }
    if (!preg_match('/^[a-f0-9]{32}$/', $submissionToken)) {
        return ['ok' => false, 'error' => 'La identificación del envío no es válida.'];
    }

    $score = null;
    if ($scoreRaw !== null && $scoreRaw !== '') {
        if (filter_var($scoreRaw, FILTER_VALIDATE_INT) === false) {
            return ['ok' => false, 'error' => 'El puntaje no es válido.'];
        }
        $score = max(0, min(9999, (int) $scoreRaw));
    }
    return ['ok' => true, 'data' => [
        'guest_name' => $name,
        'parecido' => $parecido,
        'peso' => $peso,
        'fecha' => $fecha,
        'puntaje_juego' => $score,
        'submission_token' => $submissionToken,
    ]];
}

function cb_prediction_create_for_party(int $partyId, array $input): array
{
    if (cb_storage_mode() !== 'db' || $partyId < 1) {
        throw new RuntimeException('Las predicciones requieren una fiesta válida en base de datos.');
    }
    $validated = cb_prediction_validate($input);
    if (!$validated['ok']) {
        return $validated;
    }
    $data = $validated['data'];
    $pdo = cb_pdo();
    $submissionHash = cb_hash_token($data['submission_token']);
    $stmt = $pdo->prepare(
        'INSERT INTO cc_predictions (party_id,guest_name,parecido,peso,fecha,puntaje_juego,submission_token_hash,created_at)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    try {
        $stmt->execute([
            $partyId, $data['guest_name'], $data['parecido'], $data['peso'], $data['fecha'],
            $data['puntaje_juego'], $submissionHash, gmdate('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'prediction' => $data, 'duplicate' => false];
    } catch (PDOException $e) {
        $existing = $pdo->prepare('SELECT id FROM cc_predictions WHERE party_id=? AND submission_token_hash=?');
        $existing->execute([$partyId, $submissionHash]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return ['ok' => true, 'id' => (int) $id, 'prediction' => $data, 'duplicate' => true];
        }
        throw $e;
    }
}

function cb_prediction_list_for_party(int $partyId): array
{
    if (cb_storage_mode() !== 'db' || $partyId < 1) {
        return [];
    }
    $stmt = cb_pdo()->prepare(
        'SELECT id,guest_name,parecido,peso,fecha,puntaje_juego,created_at
         FROM cc_predictions WHERE party_id=? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([$partyId]);
    return $stmt->fetchAll() ?: [];
}

function cb_prediction_labels(array $prediction): array
{
    $parecido = ['mama' => 'A mamá', 'papa' => 'A papá', 'ambos' => 'A ambos'];
    $peso = ['menos3' => 'Menos de 3 kg', 'entre' => 'Entre 3 y 3,5 kg', 'mas35' => 'Más de 3,5 kg'];
    $fecha = ['antes' => 'Antes de la fecha', 'justo' => 'Justo en la fecha', 'despues' => 'Después de la fecha'];
    return [
        'parecido' => $parecido[(string) ($prediction['parecido'] ?? '')] ?? '',
        'peso' => $peso[(string) ($prediction['peso'] ?? '')] ?? '',
        'fecha' => $fecha[(string) ($prediction['fecha'] ?? '')] ?? '',
    ];
}

function cb_invitation_issue_role_token(int $invitationId, string $purpose, ?string $expiresAt, ?string $createdBy = null): string
{
    if (!in_array($purpose, ['parents'], true)) {
        throw new InvalidArgumentException('Propósito de token desconocido.');
    }
    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $token = cb_opaque_token(16);
    $pdo->beginTransaction();
    try {
        $revoke = $pdo->prepare(
            "UPDATE cc_invitation_tokens SET status='revoked', revoked_at=?
             WHERE invitation_id=? AND purpose=? AND status='active'"
        );
        $revoke->execute([$now, $invitationId, $purpose]);
        $insert = $pdo->prepare(
            'INSERT INTO cc_invitation_tokens
             (invitation_id,token_hash,purpose,status,expires_at,created_at,created_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $insert->execute([$invitationId, cb_hash_token($token), $purpose, 'active', $expiresAt, $now, $createdBy]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $token;
}

function cb_invitation_revoke_role_tokens(int $invitationId, string $purpose): void
{
    $stmt = cb_pdo()->prepare(
        "UPDATE cc_invitation_tokens SET status='revoked', revoked_at=?
         WHERE invitation_id=? AND purpose=? AND status='active'"
    );
    $stmt->execute([gmdate('Y-m-d H:i:s'), $invitationId, $purpose]);
}

function cb_invitation_resolve_role_token(string $token, string $purpose): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || $purpose !== 'parents') {
        return null;
    }
    $stmt = cb_pdo()->prepare(
        'SELECT t.status AS token_status,t.expires_at,i.id AS invitation_id,i.party_id,
                i.event_type,i.status AS invitation_status,p.public_slug,p.birthday_person_name,
                p.admin_label,p.theme_slug,p.event_date,p.active
         FROM cc_invitation_tokens t
         JOIN cc_invitations i ON i.id=t.invitation_id
         JOIN cc_parties p ON p.id=i.party_id
         WHERE t.token_hash=? AND t.purpose=?'
    );
    $stmt->execute([cb_hash_token($token), $purpose]);
    $row = $stmt->fetch();
    if (!$row || (string) $row['token_status'] !== 'active' || empty($row['active'])) {
        return null;
    }
    $expiresAt = (string) ($row['expires_at'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
        return null;
    }
    if (cb_event_type((string) ($row['event_type'] ?? '')) !== 'baby_shower') {
        return null;
    }
    return $row;
}

function cb_prediction_board_url(string $token): string
{
    return cb_public_base_url() . '/predicciones.php?t=' . rawurlencode($token);
}
