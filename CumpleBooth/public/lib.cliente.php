<?php
/**
 * Quién contrató la fiesta y cuánto se cobró.
 *
 * Dos cosas que hasta ahora no vivían en ninguna parte: los correos a quienes hay que
 * escribirles (que no siempre son la madre o el padre: puede ser una abuela o una tía, y
 * suelen ser varios) y el precio real del trato con su descuento. El correo del cliente
 * existía solo dentro de la aceptación de Términos, que se emite una vez y no sirve de
 * agenda; el precio solo existía como texto suelto en el resumen del plan.
 *
 * Los montos son enteros en pesos: CLP no usa decimales, y guardarlos como texto obliga a
 * limpiar y convertir en cada lectura. Un total mal sumado en un comprobante es un
 * problema con el cliente, así que el total se calcula siempre aquí y en un solo lugar.
 */

/** Relaciones que puede tener quien contrata con el homenajeado. */
function cb_contact_relationships(): array
{
    return [
        'madre' => 'Madre',
        'padre' => 'Padre',
        'tutor' => 'Tutor legal',
        'familiar' => 'Familiar',
        'otro' => 'Otro',
    ];
}

/** Monto en pesos chilenos, con punto de miles. `null` y 0 se distinguen a propósito. */
function cb_format_clp(?int $monto): string
{
    if ($monto === null) { return '—'; }
    return '$' . number_format($monto, 0, ',', '.');
}

/** Entero en pesos a partir de lo que se escriba en un formulario ("$69.990", "69990 "). */
function cb_parse_clp($valor): ?int
{
    if ($valor === null) { return null; }
    $limpio = preg_replace('/[^0-9-]/', '', (string) $valor);
    if ($limpio === '' || $limpio === '-') { return null; }
    $n = (int) $limpio;
    return $n < 0 ? null : $n;
}

/** Contactos de una fiesta, el principal primero. */
function cb_party_contacts(string $publicSlug): array
{
    if (cb_storage_mode() !== 'db' || !cb_valid_public_slug($publicSlug)) { return []; }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) { return []; }
    $stmt = cb_pdo()->prepare('SELECT id, name, email, phone, relationship, is_primary
                               FROM cc_party_contacts WHERE party_id = ?
                               ORDER BY is_primary DESC, id ASC');
    $stmt->execute([$partyId]);
    $filas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $filas[] = [
            'id' => (int) $f['id'],
            'name' => (string) $f['name'],
            'email' => (string) $f['email'],
            'phone' => (string) ($f['phone'] ?? ''),
            'relationship' => (string) $f['relationship'],
            'is_primary' => (bool) $f['is_primary'],
        ];
    }
    return $filas;
}

/**
 * Reemplaza la lista completa de contactos de una fiesta.
 *
 * Se reemplaza en vez de ir agregando porque el formulario del admin muestra la lista
 * entera: si solo agregara, borrar un contacto desde ahí no tendría efecto. Va en una
 * transacción para que un correo inválido a mitad de camino no deje la fiesta sin nadie.
 *
 * @param array $contactos filas con name, email, phone, relationship, is_primary
 * @return array{ok:bool, errors?:array<string>, saved?:int}
 */
function cb_save_party_contacts(string $publicSlug, array $contactos): array
{
    if (cb_storage_mode() !== 'db') { return ['ok' => false, 'errors' => ['Los contactos requieren storage_mode=db.']]; }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) { return ['ok' => false, 'errors' => ['La fiesta no existe.']]; }

    $limpios = [];
    $errores = [];
    $relaciones = cb_contact_relationships();
    $vistos = [];
    foreach ($contactos as $i => $c) {
        $email = strtolower(trim((string) ($c['email'] ?? '')));
        $name = trim((string) ($c['name'] ?? ''));
        // Una fila completamente vacía es una fila que el operador dejó sin llenar: se ignora.
        if ($email === '' && $name === '') { continue; }
        $n = $i + 1;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "Contacto $n: el correo no es válido.";
            continue;
        }
        if (isset($vistos[$email])) {
            $errores[] = "Contacto $n: el correo $email está repetido.";
            continue;
        }
        $vistos[$email] = true;
        $rel = (string) ($c['relationship'] ?? 'otro');
        $limpios[] = [
            'name' => mb_substr($name !== '' ? $name : $email, 0, 120),
            'email' => mb_substr($email, 0, 254),
            'phone' => mb_substr(trim((string) ($c['phone'] ?? '')), 0, 40),
            'relationship' => isset($relaciones[$rel]) ? $rel : 'otro',
            'is_primary' => !empty($c['is_primary']),
        ];
    }
    if ($errores) { return ['ok' => false, 'errors' => $errores]; }

    // Siempre hay exactamente un principal: es a quien se le manda todo por defecto.
    $hayPrincipal = false;
    foreach ($limpios as $c) { if ($c['is_primary']) { $hayPrincipal = true; break; } }
    if (!$hayPrincipal && $limpios) { $limpios[0]['is_primary'] = true; }
    $primeroPrincipal = false;
    foreach ($limpios as $k => $c) {
        if ($c['is_primary'] && $primeroPrincipal) { $limpios[$k]['is_primary'] = false; }
        if ($c['is_primary']) { $primeroPrincipal = true; }
    }

    $pdo = cb_pdo();
    $ahora = gmdate('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM cc_party_contacts WHERE party_id = ?')->execute([$partyId]);
        $ins = $pdo->prepare('INSERT INTO cc_party_contacts (party_id, name, email, phone, relationship, is_primary, created_at, updated_at)
                              VALUES (?,?,?,?,?,?,?,?)');
        foreach ($limpios as $c) {
            $ins->execute([$partyId, $c['name'], $c['email'], $c['phone'], $c['relationship'], $c['is_primary'] ? 1 : 0, $ahora, $ahora]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('CumpleClick contactos: ' . $e->getMessage());
        return ['ok' => false, 'errors' => ['No se pudieron guardar los contactos.']];
    }
    return ['ok' => true, 'saved' => count($limpios)];
}

/** Correos de una fiesta, el principal primero. Para mandar el correo de bienvenida. */
function cb_party_contact_emails(string $publicSlug): array
{
    $correos = [];
    foreach (cb_party_contacts($publicSlug) as $c) { $correos[] = $c['email']; }
    return $correos;
}

/**
 * Cobro de la fiesta, con el total ya calculado.
 *
 * `total` nunca se guarda: se deriva de precio − descuento en cada lectura, para que no
 * pueda quedar un total viejo si alguien edita el precio y olvida el resto.
 */
function cb_party_billing(string $publicSlug): array
{
    $vacio = ['price_total' => null, 'discount_amount' => null, 'discount_label' => '',
              'deposit_amount' => null, 'payment_note' => '', 'total' => null, 'balance' => null];
    if (cb_storage_mode() !== 'db' || !cb_valid_public_slug($publicSlug)) { return $vacio; }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) { return $vacio; }
    $stmt = cb_pdo()->prepare('SELECT price_total, discount_amount, discount_label, deposit_amount, payment_note
                               FROM cc_parties WHERE id = ?');
    $stmt->execute([$partyId]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$f) { return $vacio; }

    $precio = $f['price_total'] !== null ? (int) $f['price_total'] : null;
    $descuento = $f['discount_amount'] !== null ? (int) $f['discount_amount'] : null;
    $anticipo = $f['deposit_amount'] !== null ? (int) $f['deposit_amount'] : null;
    $total = $precio === null ? null : max(0, $precio - (int) $descuento);
    return [
        'price_total' => $precio,
        'discount_amount' => $descuento,
        'discount_label' => (string) ($f['discount_label'] ?? ''),
        'deposit_amount' => $anticipo,
        'payment_note' => (string) ($f['payment_note'] ?? ''),
        'total' => $total,
        'balance' => $total === null ? null : max(0, $total - (int) $anticipo),
    ];
}

/** Guarda el cobro. Los montos llegan como se escriben en el formulario. */
function cb_save_party_billing(string $publicSlug, array $datos): array
{
    if (cb_storage_mode() !== 'db') { return ['ok' => false, 'errors' => ['El cobro requiere storage_mode=db.']]; }
    $partyId = cb_party_db_id($publicSlug);
    if ($partyId === null) { return ['ok' => false, 'errors' => ['La fiesta no existe.']]; }

    $precio = cb_parse_clp($datos['price_total'] ?? null);
    $descuento = cb_parse_clp($datos['discount_amount'] ?? null);
    $anticipo = cb_parse_clp($datos['deposit_amount'] ?? null);
    $errores = [];
    if ($precio !== null && $descuento !== null && $descuento > $precio) {
        $errores[] = 'El descuento no puede ser mayor que el precio.';
    }
    if ($precio !== null && $anticipo !== null && $anticipo > max(0, $precio - (int) $descuento)) {
        $errores[] = 'El anticipo no puede ser mayor que el total con descuento.';
    }
    if ($errores) { return ['ok' => false, 'errors' => $errores]; }

    $stmt = cb_pdo()->prepare('UPDATE cc_parties SET price_total = ?, discount_amount = ?, discount_label = ?,
                               deposit_amount = ?, payment_note = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([
        $precio,
        $descuento,
        mb_substr(trim((string) ($datos['discount_label'] ?? '')), 0, 80),
        $anticipo,
        mb_substr(trim((string) ($datos['payment_note'] ?? '')), 0, 160),
        gmdate('Y-m-d H:i:s'),
        $partyId,
    ]);
    return ['ok' => true];
}
