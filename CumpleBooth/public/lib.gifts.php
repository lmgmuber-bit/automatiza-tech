<?php
/**
 * Lista de regalos con reserva — el corazón del baby shower.
 *
 * Los papás cargan lo que necesitan y cada invitado marca uno para que nadie
 * lleve lo mismo. Sin cuentas de usuario: la identidad del que reserva es un
 * token opaco que vive en su navegador.
 *
 * QUÉ VE CADA UNO (decisión de Luis, 2026-08-26, corrige la del 2026-08-25):
 * el invitado ve que un regalo está tomado, NUNCA por quién. El nombre se
 * guarda igual y lo ven solo los papás y el admin.
 *
 * La razón no es privacidad genérica: no hay cuentas, así que `claimed_name`
 * es texto libre que el invitado escribe. Mostrarlo en público lo vuelve un
 * dato imposible de verificar pero socialmente creído — cualquiera puede tomar
 * el regalo caro escribiendo el nombre de otro. Y como el enlace es público,
 * la lista de nombres convierte la invitación en una lista de invitados que
 * cualquiera con la URL puede leer. Que nadie repita el regalo se cumple igual
 * con "ya lo tomaron".
 */

/** Estados de `status`. La columna nace en 'available' por defecto. */
const CB_GIFT_DISPONIBLE = 'available';
const CB_GIFT_TOMADO     = 'taken';

/** Cuántos regalos puede reservar un mismo navegador en una invitación. */
const CB_GIFT_MAX_POR_INVITADO = 3;

function cb_gift_require_db(): PDO
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('La lista de regalos requiere storage_mode=db.');
    }
    return cb_pdo();
}

/**
 * Texto de un campo que escribe una persona: recortado, sin caracteres de
 * control y con el largo que aguanta la columna.
 */
function cb_gift_clean_text($value, int $maxLength): string
{
    $text = is_scalar($value) ? trim((string) $value) : '';
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return mb_substr($text, 0, $maxLength, 'UTF-8');
}

/**
 * La lista como la ve un INVITADO.
 *
 * `mine` sale de comparar el token del navegador, así que quien reservó ve
 * cuál es suyo y puede soltarlo, y nadie más se entera de quién lo tiene.
 * Los tomados van al final para que lo disponible quede arriba.
 */
function cb_gift_list_public(int $invitationId, string $visitorToken = ''): array
{
    $pdo = cb_gift_require_db();
    $stmt = $pdo->prepare(
        'SELECT id, position, title, notes, status, claimed_token, added_by
           FROM cc_gift_items
          WHERE invitation_id = ? AND moderation_status = ?
       ORDER BY CASE WHEN status = ? THEN 1 ELSE 0 END, position, id'
    );
    $stmt->execute([$invitationId, 'approved', CB_GIFT_TOMADO]);

    $items = [];
    $tomados = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tomado = (string) $row['status'] === CB_GIFT_TOMADO;
        if ($tomado) {
            $tomados++;
        }
        $items[] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'notes' => (string) ($row['notes'] ?? ''),
            'tomado' => $tomado,
            // El único dato de identidad que sale de acá, y solo dice "es tuyo".
            'mio' => $tomado && $visitorToken !== '' && hash_equals((string) $row['claimed_token'], $visitorToken),
            'de_invitado' => (string) ($row['added_by'] ?? 'parents') === 'guest',
        ];
    }
    return ['items' => $items, 'total' => count($items), 'tomados' => $tomados];
}

/** La lista como la ven los PAPÁS y el admin: con nombres. */
function cb_gift_list_for_parents(int $invitationId): array
{
    $pdo = cb_gift_require_db();
    $stmt = $pdo->prepare(
        'SELECT id, position, title, notes, status, claimed_name, claimed_at,
                added_by, moderation_status
           FROM cc_gift_items
          WHERE invitation_id = ?
       ORDER BY position, id'
    );
    $stmt->execute([$invitationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Reserva un regalo.
 *
 * La escritura es CONDICIONAL a propósito: leer y después escribir deja que
 * dos invitados que tocan el mismo regalo con dos segundos de diferencia se
 * lleven los dos el mismo coche. Acá el que pierde la carrera cambia cero
 * filas y recibe un aviso amable, no un error.
 */
function cb_gift_claim(int $invitationId, int $giftId, string $name, string $visitorToken): array
{
    $pdo = cb_gift_require_db();
    $nombre = cb_gift_clean_text($name, 80);
    if ($nombre === '') {
        return ['ok' => false, 'error' => 'nombre_requerido'];
    }
    if (!preg_match('/^[0-9a-f]{32}$/', $visitorToken)) {
        return ['ok' => false, 'error' => 'token_invalido'];
    }

    // El tope es por navegador y por invitación, no global: el enlace es
    // público y sin esto una sola persona vacía la lista.
    $ya = $pdo->prepare(
        'SELECT COUNT(*) FROM cc_gift_items
          WHERE invitation_id = ? AND status = ? AND claimed_token = ?'
    );
    $ya->execute([$invitationId, CB_GIFT_TOMADO, $visitorToken]);
    if ((int) $ya->fetchColumn() >= CB_GIFT_MAX_POR_INVITADO) {
        return ['ok' => false, 'error' => 'limite_alcanzado', 'limite' => CB_GIFT_MAX_POR_INVITADO];
    }

    $stmt = $pdo->prepare(
        'UPDATE cc_gift_items
            SET status = ?, claimed_name = ?, claimed_token = ?, claimed_at = ?, updated_at = ?
          WHERE id = ? AND invitation_id = ? AND status = ? AND moderation_status = ?'
    );
    $ahora = gmdate('Y-m-d H:i:s');
    $stmt->execute([
        CB_GIFT_TOMADO, $nombre, $visitorToken, $ahora, $ahora,
        $giftId, $invitationId, CB_GIFT_DISPONIBLE, 'approved',
    ]);
    if ($stmt->rowCount() === 0) {
        // Cero filas = alguien se adelantó, o el regalo no existe / está oculto.
        return ['ok' => false, 'error' => 'ya_tomado'];
    }
    return ['ok' => true, 'id' => $giftId];
}

/**
 * Suelta un regalo. Solo puede el navegador que lo tomó.
 *
 * Si el invitado borra sus datos pierde el token y con él la posibilidad de
 * soltarlo: tiene que pedírselo a los papás. Es un costo aceptable de no tener
 * cuentas, y hay que decirlo en pantalla en vez de esconderlo.
 */
function cb_gift_release(int $invitationId, int $giftId, string $visitorToken): array
{
    $pdo = cb_gift_require_db();
    if (!preg_match('/^[0-9a-f]{32}$/', $visitorToken)) {
        return ['ok' => false, 'error' => 'token_invalido'];
    }
    $stmt = $pdo->prepare(
        'UPDATE cc_gift_items
            SET status = ?, claimed_name = NULL, claimed_token = NULL, claimed_at = NULL, updated_at = ?
          WHERE id = ? AND invitation_id = ? AND status = ? AND claimed_token = ?'
    );
    $stmt->execute([
        CB_GIFT_DISPONIBLE, gmdate('Y-m-d H:i:s'),
        $giftId, $invitationId, CB_GIFT_TOMADO, $visitorToken,
    ]);
    return $stmt->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'no_es_tuyo'];
}

/**
 * Agrega un regalo. Lo usan los papás desde su enlace y los invitados desde la
 * invitación ("voy a regalar otra cosa").
 *
 * Lo que agrega un invitado entra directo, sin aprobación (decisión de Luis,
 * 2026-08-25); los papás pueden ocultarlo después.
 */
function cb_gift_add(int $invitationId, array $data, string $addedBy = 'guest'): array
{
    $pdo = cb_gift_require_db();
    $title = cb_gift_clean_text($data['title'] ?? '', 120);
    if ($title === '') {
        return ['ok' => false, 'error' => 'titulo_requerido'];
    }
    $notes = cb_gift_clean_text($data['notes'] ?? '', 400);
    $addedBy = $addedBy === 'parents' ? 'parents' : 'guest';

    $pos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 10 FROM cc_gift_items WHERE invitation_id = ?');
    $pos->execute([$invitationId]);

    $ahora = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO cc_gift_items
            (invitation_id, position, title, notes, added_by, status, moderation_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $invitationId, (int) $pos->fetchColumn(), $title, ($notes !== '' ? $notes : null),
        $addedBy, CB_GIFT_DISPONIBLE, 'approved', $ahora, $ahora,
    ]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/**
 * Oculta o vuelve a mostrar un regalo. Solo los papás.
 *
 * Un regalo YA TOMADO no se puede ocultar (decisión de Luis, 2026-08-25):
 * alguien ya se comprometió con él y hacerlo desaparecer lo deja sin saber
 * qué pasó.
 */
function cb_gift_set_hidden(int $invitationId, int $giftId, bool $hidden): array
{
    $pdo = cb_gift_require_db();
    $stmt = $pdo->prepare(
        'UPDATE cc_gift_items SET moderation_status = ?, updated_at = ?
          WHERE id = ? AND invitation_id = ? AND status = ?'
    );
    $stmt->execute([
        $hidden ? 'hidden' : 'approved', gmdate('Y-m-d H:i:s'),
        $giftId, $invitationId, CB_GIFT_DISPONIBLE,
    ]);
    return $stmt->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'no_se_puede_ocultar'];
}
