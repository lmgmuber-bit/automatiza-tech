<?php
/**
 * Reglas de regalos del baby shower con confirmación (gift_mode = 'rsvp', migración 028).
 *
 * Quien confirma dice qué regalo lleva. Los regalos grandes (coche, cuna...) son de una sola
 * persona; los pañales admiten hasta CB_REGALO_TOPE_PANALES; la ropa, el aseo o el dinero son
 * libres. La categoría la decide la palabra que ENCABEZA el texto, no cualquier palabra:
 * «mantita para la cuna» no choca con una cuna.
 */

const CB_REGALO_TOPE_PANALES = 3;

const CB_REGALO_RELLENO = ['de', 'del', 'para', 'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'mi', 'al', 'y', 'con', 'bebe', 'baby'];

/** Sinónimos de regalos de una sola persona: clave => frases (se normalizan al usarlas). */
const CB_REGALO_GRUPOS_UNICOS = [
    'coche' => ['coche', 'cochecito', 'carrito', 'carriola', 'coche paseo', 'carro paseo', 'travel system'],
    'cuna' => ['cuna', 'cunita'],
    'corral' => ['corral', 'cuna corral', 'practicuna', 'cuna viaje'],
    'moises' => ['moises', 'cuna moises'],
    'silla-auto' => ['silla auto', 'silla carro', 'silla coche', 'butaca'],
    'banera' => ['banera', 'tina', 'tinita'],
    'mochila-portabebe' => ['mochila portabebe', 'portabebe', 'canguro', 'mochila ergonomica'],
    'bolso-maternal' => ['bolso maternal', 'mochila maternal', 'panalera'],
    'monitor' => ['monitor', 'intercomunicador', 'babycall'],
    'cambiador' => ['cambiador', 'mudador'],
    'silla-comer' => ['silla comer', 'silla alta', 'trona'],
    'extractor' => ['extractor leche', 'sacaleche'],
    'esterilizador' => ['esterilizador'],
    'silla-nido' => ['silla nido', 'nido', 'mecedora', 'bouncer'],
];

const CB_REGALO_LIBRES = ['ropa', 'ropita', 'body', 'pilucho', 'osito', 'pijama', 'calcetin', 'gorro', 'mantita', 'manta',
    'frazada', 'enterito', 'conjunto', 'polera', 'pantalon', 'chaleco', 'zapatito', 'babero', 'toallita', 'toallita humeda',
    'shampoo', 'jabon', 'crema', 'colonia', 'aceite', 'algodon', 'aseo', 'kit aseo', 'dinero', 'plata', 'efectivo',
    'gift card', 'giftcard', 'tarjeta regalo', 'transferencia', 'aporte'];

const CB_REGALO_GENERICOS = ['sorpresa', 'sorpresita', 'regalo', 'regalito', 'juguete', 'algo', 'cosa', 'detalle', 'nose',
    'sabe', 'lindo', 'bonito', 'pequeno', 'grande', 'especial', 'otro'];

const CB_REGALO_MENSAJES = [
    'regalo_requerido' => 'Cuéntanos qué regalo llevarás.',
    'regalo_generico' => 'Cuéntanos qué regalo llevarás, por ejemplo: un body, una mantita o un coche.',
    'regalo_tomado' => 'Ese regalo ya lo trae alguien, elige otro.',
    'panales_completos' => 'Ya hay 3 personas que llevan pañales. Elige otro regalo, por favor.',
];

/** Minúsculas, sin tildes, sin puntuación, sin relleno y en singular. Devuelve los tokens unidos por espacio. */
function cb_regalo_normalizar(string $texto): string
{
    return implode(' ', cb_regalo_tokens($texto));
}

/** @return string[] */
function cb_regalo_tokens(string $texto): array
{
    $t = mb_strtolower($texto, 'UTF-8');
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? '';
    $salida = [];
    foreach (preg_split('/\s+/', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
        if (in_array($palabra, CB_REGALO_RELLENO, true)) {
            continue;
        }
        $original = $palabra;
        if (strlen($palabra) > 3) {
            if (preg_match('/[lrndzj]es$/', $palabra)) {
                $palabra = substr($palabra, 0, -2);
            } elseif (substr($palabra, -1) === 's') {
                $palabra = substr($palabra, 0, -1);
            }
        }
        if ($palabra !== $original && in_array($palabra, CB_REGALO_RELLENO, true)) {
            continue; // «bebés» -> «bebe»: también es relleno
        }
        $salida[] = $palabra;
    }
    return $salida;
}

/** @return array{categoria:string, clave:string} */
function cb_regalo_clasificar(string $texto): array
{
    $tokens = cb_regalo_tokens($texto);
    if ($tokens === []) {
        return ['categoria' => 'generico', 'clave' => ''];
    }
    $normal = implode(' ', $tokens);

    // 1. Grupo único: el sinónimo más largo que sea prefijo de los tokens.
    $mejor = null;
    $mejorLargo = 0;
    foreach (CB_REGALO_GRUPOS_UNICOS as $clave => $frases) {
        foreach ($frases as $frase) {
            $ft = cb_regalo_tokens($frase);
            $n = count($ft);
            if ($n > $mejorLargo && array_slice($tokens, 0, $n) === $ft) {
                $mejor = $clave;
                $mejorLargo = $n;
            }
        }
    }
    if ($mejor !== null) {
        return ['categoria' => 'unico', 'clave' => $mejor];
    }

    // 2. Pañales.
    if (in_array('panal', $tokens, true)) {
        return ['categoria' => 'panales', 'clave' => 'panales'];
    }

    // 3. Libres: algún token o bigrama de la lista libre (el primero manda, pero «set ropa» también cuenta).
    $libres = array_map('cb_regalo_normalizar', CB_REGALO_LIBRES);
    $piezas = $tokens;
    for ($i = 0; $i + 1 < count($tokens); $i++) {
        $piezas[] = $tokens[$i] . ' ' . $tokens[$i + 1];
    }
    foreach ($piezas as $pieza) {
        if (in_array($pieza, $libres, true)) {
            return ['categoria' => 'libre', 'clave' => $normal];
        }
    }

    // 4. Genérico: todo son palabras vagas.
    if (array_diff($tokens, array_map('cb_regalo_normalizar', CB_REGALO_GENERICOS)) === []) {
        return ['categoria' => 'generico', 'clave' => $normal];
    }

    // 5. Cualquier otra cosa se trata como regalo único.
    return ['categoria' => 'unico', 'clave' => $normal];
}

/**
 * ¿Puede esta invitación recibir este regalo? Compara contra los regalos aprobados (uno oculto queda libre).
 *
 * @return array{ok:true,categoria:string,clave:string}|array{ok:false,error:string,mensaje:string}
 */
function cb_regalo_validar(int $invitationId, string $texto): array
{
    $falla = static fn(string $error): array => ['ok' => false, 'error' => $error, 'mensaje' => CB_REGALO_MENSAJES[$error]];
    if (trim($texto) === '') {
        return $falla('regalo_requerido');
    }
    $c = cb_regalo_clasificar($texto);
    if ($c['categoria'] === 'generico') {
        return $falla('regalo_generico');
    }
    if ($c['categoria'] !== 'libre') {
        $stmt = cb_pdo()->prepare('SELECT title FROM cc_gift_items WHERE invitation_id = ? AND moderation_status = ?');
        $stmt->execute([$invitationId, 'approved']);
        $mismos = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $titulo) {
            $o = cb_regalo_clasificar((string) $titulo);
            if ($o['categoria'] === $c['categoria'] && $o['clave'] === $c['clave']) {
                $mismos++;
            }
        }
        if ($c['categoria'] === 'unico' && $mismos > 0) {
            return $falla('regalo_tomado');
        }
        if ($c['categoria'] === 'panales' && $mismos >= CB_REGALO_TOPE_PANALES) {
            return $falla('panales_completos');
        }
    }
    return ['ok' => true, 'categoria' => $c['categoria'], 'clave' => $c['clave']];
}

const CB_RSVP_FOTO_MAX_BYTES = 10 * 1024 * 1024;
const CB_RSVP_FOTO_LADO_MAX = 1600;
const CB_RSVP_TOPE_CONFIRMACIONES = 500;

const CB_RSVP_MENSAJES = [
    'nombre_requerido' => 'Escribe tu nombre para confirmar.',
    'nombre_repetido' => 'Ya hay una confirmación con ese nombre. Agrega tu apellido, por favor.',
    'equipo_requerido' => 'Elige Team Mamá o Team Papá.',
    'foto_invalida' => 'No pudimos usar esa foto. Prueba con otra o confirma sin foto.',
    'lista_llena' => 'La lista de confirmados está llena. Avísale a los papás.',
];

/** Rechazo de negocio dentro de la transacción (no es un fallo del servidor). */
final class CbRegaloRechazo extends RuntimeException
{
}

/** @return array{ok:false,error:string,mensaje:string} */
function cb_rsvp_regalo_falla(string $error): array
{
    return ['ok' => false, 'error' => $error, 'mensaje' => CB_RSVP_MENSAJES[$error] ?? CB_REGALO_MENSAJES[$error] ?? ''];
}

/** Ruta absoluta (fuera del webroot) de una foto de bebé, o null si la clave no es válida. */
function cb_rsvp_foto_path(string $key): ?string
{
    if (!preg_match('#^rsvp-bebes/\d{1,12}/[a-f0-9]{32}\.jpg$#', $key)) {
        return null;
    }
    return cb_photo_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
}

/**
 * Valida y reencoda la foto a JPEG (sin EXIF, <= 1600 px). Devuelve la clave guardada, '' si no vino foto,
 * o null si la foto no sirve.
 */
function cb_rsvp_guardar_foto(int $partyId, ?array $foto): ?string
{
    if ($foto === null || (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $tmp = (string) ($foto['tmp_name'] ?? '');
    if ((int) $foto['error'] !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp)) {
        return null;
    }
    $bytes = (int) filesize($tmp);
    if ($bytes <= 0 || $bytes > CB_RSVP_FOTO_MAX_BYTES) {
        return null;
    }
    $info = @getimagesize($tmp);
    if (!is_array($info) || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
        || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1 || $info[0] * $info[1] > 50000000) {
        return null;
    }
    $origen = @imagecreatefromstring((string) file_get_contents($tmp));
    if (!$origen) {
        return null;
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $giro = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($giro !== 0 && ($rotada = imagerotate($origen, $giro, 0))) {
            $origen = $rotada;
        }
    }
    $w = imagesx($origen);
    $h = imagesy($origen);
    $escala = min(1.0, CB_RSVP_FOTO_LADO_MAX / max($w, $h));
    $nw = max(1, (int) round($w * $escala));
    $nh = max(1, (int) round($h * $escala));
    $lienzo = imagecreatetruecolor($nw, $nh);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255)); // PNG con transparencia: fondo blanco
    imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $key = 'rsvp-bebes/' . $partyId . '/' . bin2hex(random_bytes(16)) . '.jpg';
    $ruta = cb_rsvp_foto_path($key);
    $dir = $ruta !== null ? dirname($ruta) : '';
    if ($ruta === null || (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) || !@imagejpeg($lienzo, $ruta, 85)) {
        return null;
    }
    return $key;
}

/**
 * Confirma asistencia en modo `rsvp`: nombre + regalo validado + equipo (+ foto de bebé opcional).
 * Todo o nada: el regalo y la confirmación se crean en una transacción que bloquea la invitación.
 *
 * @param array{family_name?:mixed,gift?:mixed,team?:mixed} $datos
 * @return array{ok:true,regalo:string}|array{ok:false,error:string,mensaje:string}
 */
function cb_rsvp_confirmar_con_regalo(array $invitation, array $datos, ?array $foto): array
{
    $invitationId = (int) ($invitation['id'] ?? 0);
    $partyId = (int) ($invitation['party_id'] ?? 0);
    if ($invitationId <= 0 || $partyId <= 0) {
        return cb_rsvp_regalo_falla('nombre_requerido');
    }
    $nombre = cb_rsvp_clean((string) ($datos['family_name'] ?? ''), 120);
    if (mb_strlen($nombre) < 2) {
        return cb_rsvp_regalo_falla('nombre_requerido');
    }
    $regalo = cb_gift_clean_text($datos['gift'] ?? '', 120);
    $team = (string) ($datos['team'] ?? '');

    // Lo barato primero, para no guardar una foto que se va a tirar.
    $pre = cb_regalo_validar($invitationId, $regalo);
    if (empty($pre['ok'])) {
        return $pre;
    }
    if (!in_array($team, ['mama', 'papa'], true)) {
        return cb_rsvp_regalo_falla('equipo_requerido');
    }
    $fotoKey = cb_rsvp_guardar_foto($partyId, $foto);
    if ($fotoKey === null) {
        return cb_rsvp_regalo_falla('foto_invalida');
    }

    $pdo = cb_pdo();
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $limpiarFoto = static function () use ($fotoKey): void {
        if ($fotoKey !== '' && ($ruta = cb_rsvp_foto_path($fotoKey)) !== null) {
            @unlink($ruta);
        }
    };
    try {
        $pdo->beginTransaction();
        // Bloquea la invitación: dos confirmaciones simultáneas se atienden una detrás de la otra.
        $pdo->prepare('SELECT id FROM cc_invitations WHERE id = ?' . ($mysql ? ' FOR UPDATE' : ''))->execute([$invitationId]);

        $total = $pdo->prepare('SELECT COUNT(*) FROM cc_rsvps WHERE party_id = ?');
        $total->execute([$partyId]);
        if ((int) $total->fetchColumn() >= CB_RSVP_TOPE_CONFIRMACIONES) {
            throw new CbRegaloRechazo('lista_llena');
        }
        $dup = $pdo->prepare('SELECT 1 FROM cc_rsvps WHERE party_id = ? AND LOWER(family_name) = LOWER(?) LIMIT 1');
        $dup->execute([$partyId, $nombre]);
        if ($dup->fetchColumn()) {
            throw new CbRegaloRechazo('nombre_repetido');
        }
        $val = cb_regalo_validar($invitationId, $regalo); // otra vez, ya con la fila bloqueada
        if (empty($val['ok'])) {
            throw new CbRegaloRechazo((string) $val['error']);
        }

        $ahora = gmdate('Y-m-d H:i:s');
        $pos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 10 FROM cc_gift_items WHERE invitation_id = ?');
        $pos->execute([$invitationId]);
        $pdo->prepare(
            'INSERT INTO cc_gift_items (invitation_id, position, title, notes, added_by, status, claimed_name, claimed_at, moderation_status, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$invitationId, (int) $pos->fetchColumn(), $regalo, 'guest', CB_GIFT_TOMADO, $nombre, $ahora, 'approved', $ahora, $ahora]);
        $giftId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO cc_rsvps (party_id, family_name, guest_names, team, gift_item_id, baby_photo_key, created_at, updated_at)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?)'
        )->execute([$partyId, $nombre, $team, $giftId, $fotoKey !== '' ? $fotoKey : null, $ahora, $ahora]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $limpiarFoto();
        if ($e instanceof CbRegaloRechazo) {
            return cb_rsvp_regalo_falla($e->getMessage());
        }
        throw $e;
    }
    return ['ok' => true, 'regalo' => $regalo];
}

/** Borra una confirmación con su regalo y su foto. */
function cb_rsvp_borrar(int $partyId, int $rsvpId): bool
{
    $pdo = cb_pdo();
    $sel = $pdo->prepare('SELECT gift_item_id, baby_photo_key FROM cc_rsvps WHERE id = ? AND party_id = ?');
    $sel->execute([$rsvpId, $partyId]);
    $fila = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        if (!empty($fila['gift_item_id'])) {
            $pdo->prepare('DELETE FROM cc_gift_items WHERE id = ?')->execute([(int) $fila['gift_item_id']]);
        }
        $pdo->prepare('DELETE FROM cc_rsvps WHERE id = ? AND party_id = ?')->execute([$rsvpId, $partyId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    if (!empty($fila['baby_photo_key']) && ($ruta = cb_rsvp_foto_path((string) $fila['baby_photo_key'])) !== null) {
        @unlink($ruta);
    }
    return true;
}
