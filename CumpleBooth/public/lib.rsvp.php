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

/**
 * ¿Cuántos confirmaron? Familias y niños, con el mismo criterio que ve la familia en
 * asistencia-papas.php: los niños llegan como texto libre ("Emma y Lucas", "Sofía, Tomás")
 * y se cuentan por los separadores más comunes. Es una cifra aproximada a propósito;
 * inventar exactitud sobre texto escrito a mano sería peor.
 */
function cb_rsvp_resumen(string $publicSlug): array
{
    $vacio = ['familias' => 0, 'ninos' => 0, 'ultima' => '', 'lista' => []];
    if (cb_storage_mode() !== 'db' || !cb_valid_public_slug($publicSlug)) {
        return $vacio;
    }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) {
        return $vacio;
    }
    $lista = cb_rsvp_list($partyId);
    $ninos = 0;
    $ultima = '';
    $filas = [];
    foreach ($lista as $fila) {
        $nombres = trim((string) ($fila['guest_names'] ?? ''));
        $cuantos = $nombres === ''
            ? 0
            : count(preg_split('/\s*(?:,| y | e )\s*/u', $nombres, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $ninos += $cuantos;
        $creada = (string) ($fila['created_at'] ?? '');
        if ($creada > $ultima) {
            $ultima = $creada;
        }
        $filas[] = [
            'familia' => trim((string) ($fila['family_name'] ?? '')),
            'ninos' => $nombres,
            'cuantos' => $cuantos,
            'cuando' => substr($creada, 0, 16),
        ];
    }
    return ['familias' => count($lista), 'ninos' => $ninos, 'ultima' => $ultima, 'lista' => $filas];
}

/**
 * La invitación sobre la que cuelga el enlace de confirmados.
 *
 * El token de rol `parents` se guarda contra una invitación, no contra la fiesta, así que
 * hace falta elegir una. Se usa el mismo criterio que el manual y el formulario de Términos
 * —la publicada, y si no hay, la más reciente sin revocar— para que las tres cosas apunten
 * siempre a la misma. Devuelve null si la fiesta todavía no tiene invitación.
 */
function cb_rsvp_invitacion_de_fiesta(string $publicSlug): ?int
{
    if (cb_storage_mode() !== 'db' || !cb_valid_public_slug($publicSlug)) {
        return null;
    }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) {
        return null;
    }
    $stmt = cb_pdo()->prepare(
        "SELECT id FROM cc_invitations
         WHERE party_id = ? AND status <> 'revoked'
         ORDER BY (status = 'published') DESC, updated_at DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$partyId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/**
 * ¿Ya hay un enlace vivo para los papás? Devuelve la fecha en que se emitió, o ''.
 *
 * El token en claro existe UNA sola vez, cuando se emite: en la base solo queda su hash.
 * Por eso el admin no puede volver a mostrar un enlace ya entregado — solo decir que existe
 * y ofrecer emitir uno nuevo, que anula el anterior.
 */
function cb_rsvp_enlace_papas_emitido(string $publicSlug): string
{
    $invitacion = cb_rsvp_invitacion_de_fiesta($publicSlug);
    if ($invitacion === null) {
        return '';
    }
    $stmt = cb_pdo()->prepare(
        "SELECT created_at FROM cc_invitation_tokens
         WHERE invitation_id = ? AND purpose = 'parents' AND status = 'active'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$invitacion]);
    $fecha = $stmt->fetchColumn();
    return $fecha === false ? '' : (string) $fecha;
}

/** La URL que ve la familia con su token. */
function cb_rsvp_url_papas(string $token): string
{
    return rtrim((string) cb_public_base_url(), '/') . '/asistencia-papas.php?t=' . rawurlencode($token);
}

/**
 * Firma del enlace fijo de confirmados. Mismo patrón que `manual.php`: una firma sobre el
 * slug, no un token guardado.
 *
 * Por qué se cambió: el token aleatorio se guardaba hasheado y solo se mostraba en el momento
 * de emitirlo. Después no había forma de recuperarlo, y para volver a ver el enlace había que
 * generar uno nuevo, matando el que ya estaba compartido. El hash tampoco compraba mucho: lo
 * único que ese enlace muestra son los nombres de quienes confirmaron, que viven en la misma
 * base de datos que guardaba el hash.
 *
 * Cómo se cierra un enlace fijo: desactivando la fiesta. `cb_rsvp_acceso_por_slug` exige
 * `active`, igual que hacía el camino del token.
 */
function cb_rsvp_firma_papas(string $slug): string
{
    return substr(cb_hmac($slug, 'confirmados-papas'), 0, 24);
}

function cb_rsvp_firma_papas_valida(string $slug, string $firma): bool
{
    return $firma !== '' && hash_equals(cb_rsvp_firma_papas($slug), $firma);
}

/** El enlace fijo de una fiesta. No hay que emitirlo: se calcula. */
function cb_rsvp_url_papas_fija(string $slug): string
{
    return rtrim((string) cb_public_base_url(), '/') . '/asistencia-papas.php?p=' . rawurlencode($slug)
        . '&f=' . cb_rsvp_firma_papas($slug);
}

/**
 * Los datos que la pantalla de la familia necesita, entrando por slug + firma.
 * Devuelve la misma forma que `cb_rsvp_resolve_parents_token` para que la página no tenga
 * que distinguir por dónde entró.
 */
function cb_rsvp_acceso_por_slug(string $slug, string $firma): ?array
{
    if (!cb_valid_public_slug($slug) || !cb_rsvp_firma_papas_valida($slug, $firma)) {
        return null;
    }
    $party = cb_load_party_raw($slug);
    if ($party === null || empty($party['activa'])) {
        return null;
    }
    $id = cb_party_db_id($slug);
    if ($id === null) {
        return null;
    }
    return [
        'party_id' => $id,
        'public_slug' => $slug,
        'event_type' => (string) ($party['event_type'] ?? ''),
        'birthday_person_name' => (string) ($party['nombre'] ?? ''),
        'theme_slug' => (string) ($party['tema'] ?? ''),
    ];
}

/**
 * Emite el enlace de confirmados de una fiesta y lo devuelve en claro.
 * Anula cualquier enlace anterior de la misma invitación.
 *
 * @throws RuntimeException si la fiesta no tiene invitación sobre la que colgarlo.
 */
function cb_rsvp_emitir_enlace_papas(string $publicSlug, string $por): string
{
    $invitacion = cb_rsvp_invitacion_de_fiesta($publicSlug);
    if ($invitacion === null) {
        throw new RuntimeException('La fiesta no tiene invitación: crea una antes de generar el enlace.');
    }
    return cb_invitation_issue_role_token($invitacion, 'parents', null, $por);
}

/** Anula el enlace vivo, si lo hay. */
function cb_rsvp_revocar_enlace_papas(string $publicSlug): bool
{
    $invitacion = cb_rsvp_invitacion_de_fiesta($publicSlug);
    if ($invitacion === null) {
        return false;
    }
    cb_invitation_revoke_role_tokens($invitacion, 'parents');
    return true;
}

/** Mensaje listo para mandarle a la familia por WhatsApp con su enlace. */
function cb_rsvp_texto_whatsapp(string $publicSlug, string $url): string
{
    $party = cb_load_party_raw($publicSlug) ?? [];
    $nombre = trim((string) ($party['nombre'] ?? $party['birthday_person_name'] ?? ''));
    $de = $nombre !== '' ? " de $nombre" : '';
    return "Acá puedes ver quiénes confirmaron para el cumpleaños$de 👇\n\n"
        . $url . "\n\n"
        . "Se actualiza sola cada vez que una familia confirma en la invitación, "
        . "así que puedes entrar las veces que quieras. El enlace es tuyo: no lo compartas "
        . "con los invitados, a ellos va la invitación.";
}
