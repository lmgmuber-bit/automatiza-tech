<?php
/**
 * Confirmaciones de asistencia (RSVP).
 *
 * Quién escribe: cualquier persona con el enlace público de la invitación
 * (rsvp-api.php valida el token opaco). Quién lee la lista completa: la
 * familia, con su token de rol `parents` (asistencia-papas.php) — el mismo
 * token que en baby shower abre predicciones y regalos, así revocar uno
 * cierra todo a la vez.
 *
 * A diferencia de predicciones/regalos, esto vale para TODAS las modalidades:
 * en cumpleaños confirma el apoderado y anota a los niños; en baby shower
 * confirma la persona adulta. Por eso este archivo tiene su propio resolutor
 * de token de rol: el de lib.predictions.php exige baby_shower a propósito
 * (predicciones no existen en cumpleaños) y relajarlo allá abriría esas
 * pantallas a fiestas donde no aplican.
 */

/** Limpia un nombre de texto libre: espacios colapsados, sin controles. */
function cb_rsvp_clean(string $texto, int $max): string
{
    $texto = preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $texto) ?? '';
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    return mb_substr($texto, 0, $max);
}

/**
 * Guarda una confirmación. Devuelve ['ok'=>bool, 'error'=>string|null].
 * Append-only: si la misma familia confirma dos veces, se actualiza su fila
 * (match exacto por nombre, sin distinguir mayúsculas) en vez de duplicarla.
 */
function cb_rsvp_save(int $partyId, string $familyName, string $guestNames): array
{
    $familyName = cb_rsvp_clean($familyName, 120);
    $guestNames = cb_rsvp_clean($guestNames, 400);
    if (mb_strlen($familyName) < 2) {
        return ['ok' => false, 'error' => 'nombre_requerido'];
    }
    $pdo = cb_pdo();
    // Tope defensivo: nadie organiza una fiesta de 500 familias por este
    // canal; pasado el tope es un script, no un invitado.
    $total = (int) $pdo->query('SELECT COUNT(*) FROM cc_rsvps WHERE party_id=' . $partyId)->fetchColumn();
    if ($total >= 500) {
        return ['ok' => false, 'error' => 'lista_llena'];
    }
    $now = gmdate('Y-m-d H:i:s');
    $sel = $pdo->prepare('SELECT id FROM cc_rsvps WHERE party_id=? AND LOWER(family_name)=LOWER(?) LIMIT 1');
    $sel->execute([$partyId, $familyName]);
    $id = (int) ($sel->fetchColumn() ?: 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE cc_rsvps SET guest_names=?, updated_at=? WHERE id=?')
            ->execute([$guestNames !== '' ? $guestNames : null, $now, $id]);
    } else {
        $pdo->prepare(
            'INSERT INTO cc_rsvps (party_id,family_name,guest_names,created_at,updated_at) VALUES (?,?,?,?,?)'
        )->execute([$partyId, $familyName, $guestNames !== '' ? $guestNames : null, $now, $now]);
    }
    return ['ok' => true, 'error' => null];
}

/** Lista de confirmaciones del evento, más reciente primero. */
function cb_rsvp_list(int $partyId): array
{
    $stmt = cb_pdo()->prepare(
        'SELECT family_name, guest_names, created_at, updated_at
         FROM cc_rsvps WHERE party_id=? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([$partyId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Resuelve el token de rol `parents` SIN exigir baby shower: la lista de
 * confirmados existe en todas las modalidades. Mismo esquema de tokens que
 * lib.predictions.php (cc_invitation_tokens, hash, estado, vencimiento).
 */
function cb_rsvp_resolve_parents_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
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
    $stmt->execute([cb_hash_token($token), 'parents']);
    $row = $stmt->fetch();
    if (!$row || (string) $row['token_status'] !== 'active' || empty($row['active'])) {
        return null;
    }
    $expiresAt = (string) ($row['expires_at'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
        return null;
    }
    return $row;
}
