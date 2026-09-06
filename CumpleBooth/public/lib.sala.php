<?php
/**
 * Salas de ayudantes del juego 3D "Tu Cumple en 3D" (fase 3).
 *
 * La tablet crea una sala y recibe un token de anfitrión; cada celular se une con
 * el código del QR y recibe su propio token. Los ayudantes mandan acciones
 * (ánimo, copos, ayuda) que la tablet consume por polling. Nada de identidad en
 * claro: los tokens se guardan hasheados y la IP solo como HMAC.
 * Contrato completo: app/design/sala-api.md en el repo del juego.
 */

const CB_SALA_ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const CB_SALA_CUPO = 12;
const CB_SALA_VIDA_SEGUNDOS = 21600;
const CB_SALA_TOPE_POR_MINUTO = 60;
const CB_SALA_RESUMEN_MAX_BYTES = 600;
const CB_SALA_ACCIONES_POR_LECTURA = 50;

/** Enfriamiento por ayudante y tipo de acción, en segundos. */
function cb_sala_tipos(): array
{
    return ['animo' => 3, 'copos' => 8, 'ayuda' => 20];
}

function cb_sala_texto($value, int $maxBytes): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim(preg_replace('/[\p{C}<>]/u', '', str_replace("\0", '', $value)) ?? '');
    if (preg_match('//u', $value) !== 1 || strlen($value) > $maxBytes) {
        return '';
    }
    return $value;
}

/** Nombre corto para mostrar en pantalla (1–16 caracteres, sin control ni <>). */
function cb_sala_nombre($value, int $maxChars = 16): string
{
    $texto = cb_sala_texto($value, 80);
    if ($texto === '') {
        return '';
    }
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
    if (preg_match('/^.{1,' . $maxChars . '}/us', $texto, $m) !== 1) {
        return '';
    }
    return trim($m[0]);
}

function cb_sala_ahora(): string
{
    return gmdate('Y-m-d H:i:s');
}

function cb_sala_codigo_valido($codigo): ?string
{
    if (!is_string($codigo)) {
        return null;
    }
    $codigo = strtoupper(trim($codigo));
    // Mismo alfabeto que la generación (sin 0/O/1/I): un código que no pudo
    // generarse se rechaza sin ir a la base de datos.
    return preg_match('/^[' . CB_SALA_ALFABETO . ']{5}$/', $codigo) === 1 ? $codigo : null;
}

function cb_sala_token_valido($token): ?string
{
    return is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token) === 1 ? $token : null;
}

function cb_sala_pdo(): PDO
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('Las salas de ayudantes requieren storage_mode=db.');
    }
    return cb_pdo();
}

/** Limpieza perezosa: salas vencidas hace más de 18 h se borran con sus hijos. */
function cb_sala_limpiar(PDO $pdo, bool $forzar = false): void
{
    if (!$forzar && random_int(1, 25) !== 1) {
        return;
    }
    $limite = gmdate('Y-m-d H:i:s', time() - 18 * 3600);
    $stmt = $pdo->prepare('SELECT id FROM cc_salas WHERE expires_at < ?');
    $stmt->execute([$limite]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $pdo->prepare('DELETE FROM cc_sala_acciones WHERE sala_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM cc_sala_ayudantes WHERE sala_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM cc_salas WHERE id=?')->execute([$id]);
    }
}

function cb_sala_generar_codigo(): string
{
    $codigo = '';
    $n = strlen(CB_SALA_ALFABETO);
    for ($i = 0; $i < 5; $i++) {
        $codigo .= CB_SALA_ALFABETO[random_int(0, $n - 1)];
    }
    return $codigo;
}

function cb_sala_crear(string $festejado, string $identity): array
{
    $pdo = cb_sala_pdo();
    cb_sala_limpiar($pdo);
    $nombre = cb_sala_nombre($festejado, 24);
    if ($nombre === '') {
        $nombre = 'Princesa';
    }
    $ahora = time();
    for ($intento = 0; $intento < 6; $intento++) {
        $codigo = cb_sala_generar_codigo();
        $token = cb_opaque_token(16);
        try {
            $pdo->prepare('INSERT INTO cc_salas (codigo,anfitrion_hash,festejado,resumen,ultimo_seq,estado,identity_hmac,created_at,expires_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $codigo, cb_hash_token($token), $nombre, null, 0, 'activa', cb_hmac($identity, 'sala-ip'),
                    gmdate('Y-m-d H:i:s', $ahora), gmdate('Y-m-d H:i:s', $ahora + CB_SALA_VIDA_SEGUNDOS), gmdate('Y-m-d H:i:s', $ahora),
                ]);
            return ['ok' => true, 'codigo' => $codigo, 'anfitrion' => $token, 'caduca_en' => CB_SALA_VIDA_SEGUNDOS, 'hosts' => cb_sala_ips_lan()];
        } catch (PDOException $e) {
            $duplicado = (string) $e->getCode() === '23000'
                || strpos(strtolower($e->getMessage()), 'unique') !== false
                || strpos(strtolower($e->getMessage()), 'duplicate') !== false;
            if ($intento === 5 || !$duplicado) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('No se pudo generar un código de sala.');
}

/** Fila de la sala con `estado` ya resuelto (una sala vencida se lee como cerrada). */
function cb_sala_buscar(PDO $pdo, $codigo): ?array
{
    $codigo = cb_sala_codigo_valido($codigo);
    if ($codigo === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM cc_salas WHERE codigo=?');
    $stmt->execute([$codigo]);
    $sala = $stmt->fetch();
    if (!$sala) {
        return null;
    }
    if ($sala['estado'] === 'activa' && strcmp((string) $sala['expires_at'], cb_sala_ahora()) < 0) {
        $sala['estado'] = 'cerrada';
    }
    return $sala;
}

function cb_sala_es_anfitrion(array $sala, $token): bool
{
    $token = cb_sala_token_valido($token);
    return $token !== null && hash_equals((string) $sala['anfitrion_hash'], cb_hash_token($token));
}

function cb_sala_contar_ayudantes(PDO $pdo, int $salaId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cc_sala_ayudantes WHERE sala_id=?');
    $stmt->execute([$salaId]);
    return (int) $stmt->fetchColumn();
}

function cb_sala_unirse($codigo, $nombre, string $identity): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    if ($sala['estado'] !== 'activa') {
        return ['ok' => false, 'error' => 'sala_cerrada', 'http' => 409];
    }
    $nombre = cb_sala_nombre($nombre);
    if ($nombre === '') {
        return ['ok' => false, 'error' => 'nombre_invalido', 'http' => 422];
    }
    if (cb_sala_contar_ayudantes($pdo, (int) $sala['id']) >= CB_SALA_CUPO) {
        return ['ok' => false, 'error' => 'sala_llena', 'http' => 409];
    }
    $token = cb_opaque_token(16);
    $ahora = cb_sala_ahora();
    $pdo->prepare('INSERT INTO cc_sala_ayudantes (sala_id,token_hash,nombre,identity_hmac,ultimo_animo,ultimo_copos,ultimo_ayuda,created_at,last_seen_at) VALUES (?,?,?,?,0,0,0,?,?)')
        ->execute([(int) $sala['id'], cb_hash_token($token), $nombre, cb_hmac($identity, 'sala-ip'), $ahora, $ahora]);
    return [
        'ok' => true,
        'ayudante' => $token,
        'id' => (int) $pdo->lastInsertId(),
        'nombre' => $nombre,
        'festejado' => (string) $sala['festejado'],
    ];
}

function cb_sala_ayudante(PDO $pdo, array $sala, $token): ?array
{
    $token = cb_sala_token_valido($token);
    if ($token === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM cc_sala_ayudantes WHERE sala_id=? AND token_hash=?');
    $stmt->execute([(int) $sala['id'], cb_hash_token($token)]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

/** Segundos que faltan por tipo para que el ayudante pueda volver a actuar. */
function cb_sala_esperas(?array $ayudante, int $ahora): array
{
    $esperas = [];
    foreach (cb_sala_tipos() as $tipo => $enfriamiento) {
        $ultimo = $ayudante ? (int) $ayudante['ultimo_' . $tipo] : 0;
        $esperas[$tipo] = max(0, $ultimo + $enfriamiento - $ahora);
    }
    return $esperas;
}

function cb_sala_accion($codigo, $token, $tipo): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    if ($sala['estado'] !== 'activa') {
        return ['ok' => false, 'error' => 'sala_cerrada', 'http' => 409];
    }
    $tipos = cb_sala_tipos();
    if (!is_string($tipo) || !isset($tipos[$tipo])) {
        return ['ok' => false, 'error' => 'tipo_invalido', 'http' => 422];
    }
    $ayudante = cb_sala_ayudante($pdo, $sala, $token);
    if ($ayudante === null) {
        return ['ok' => false, 'error' => 'ayudante_invalido', 'http' => 403];
    }
    $ahora = time();
    $esperas = cb_sala_esperas($ayudante, $ahora);
    if ($esperas[$tipo] > 0) {
        return ['ok' => false, 'error' => 'espera', 'http' => 429, 'espera' => $esperas[$tipo], 'esperas' => $esperas];
    }
    $conteo = $pdo->prepare('SELECT COUNT(*) FROM cc_sala_acciones WHERE sala_id=? AND created_ts>=?');
    $conteo->execute([(int) $sala['id'], $ahora - 60]);
    if ((int) $conteo->fetchColumn() >= CB_SALA_TOPE_POR_MINUTO) {
        return ['ok' => false, 'error' => 'sala_saturada', 'http' => 429, 'espera' => 10, 'esperas' => $esperas];
    }

    $pdo->beginTransaction();
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $pdo->prepare('SELECT ultimo_seq FROM cc_salas WHERE id=?' . ($driver === 'mysql' ? ' FOR UPDATE' : ''));
        $lock->execute([(int) $sala['id']]);
        $seq = (int) $lock->fetchColumn() + 1;
        $fecha = gmdate('Y-m-d H:i:s', $ahora);
        $pdo->prepare('UPDATE cc_salas SET ultimo_seq=?, updated_at=? WHERE id=?')->execute([$seq, $fecha, (int) $sala['id']]);
        $pdo->prepare('INSERT INTO cc_sala_acciones (sala_id,seq,ayudante_id,tipo,created_at,created_ts) VALUES (?,?,?,?,?,?)')
            ->execute([(int) $sala['id'], $seq, (int) $ayudante['id'], $tipo, $fecha, $ahora]);
        $pdo->prepare("UPDATE cc_sala_ayudantes SET ultimo_$tipo=?, last_seen_at=? WHERE id=?")
            ->execute([$ahora, $fecha, (int) $ayudante['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    $ayudante['ultimo_' . $tipo] = $ahora;
    return ['ok' => true, 'seq' => $seq, 'esperas' => cb_sala_esperas($ayudante, $ahora)];
}

function cb_sala_estado($codigo, $token = ''): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    $ahora = time();
    $ayudante = $token !== '' ? cb_sala_ayudante($pdo, $sala, $token) : null;
    if ($ayudante !== null) {
        $pdo->prepare('UPDATE cc_sala_ayudantes SET last_seen_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s', $ahora), (int) $ayudante['id']]);
    }
    $resumen = null;
    if (is_string($sala['resumen']) && $sala['resumen'] !== '') {
        $decodificado = json_decode($sala['resumen'], true);
        $resumen = is_array($decodificado) ? $decodificado : null;
    }
    return [
        'ok' => true,
        'festejado' => (string) $sala['festejado'],
        'resumen' => $resumen,
        'ayudantes' => cb_sala_contar_ayudantes($pdo, (int) $sala['id']),
        'estado' => (string) $sala['estado'],
        'esperas' => cb_sala_esperas($ayudante, $ahora),
        'tu_nombre' => $ayudante ? (string) $ayudante['nombre'] : null,
    ];
}

/** Solo campos escalares cortos: el celular muestra texto, no interpreta nada. */
function cb_sala_resumen_limpio($resumen): ?string
{
    if (!is_array($resumen)) {
        return null;
    }
    $limpio = [];
    foreach ($resumen as $clave => $valor) {
        if (!is_string($clave) || preg_match('/^[a-z_]{1,24}$/', $clave) !== 1) {
            continue;
        }
        if (is_int($valor) || is_bool($valor)) {
            $limpio[$clave] = $valor;
        } elseif (is_float($valor) && is_finite($valor)) {
            $limpio[$clave] = round($valor, 2);
        } elseif (is_string($valor)) {
            // Texto corto para pantalla: se recorta a 160 caracteres, no se rechaza.
            $texto = cb_sala_texto($valor, 2000);
            $limpio[$clave] = preg_match('/^.{0,160}/us', $texto, $m) === 1 ? $m[0] : '';
        }
    }
    $json = json_encode($limpio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || strlen($json) > CB_SALA_RESUMEN_MAX_BYTES) {
        return null;
    }
    return $json;
}

function cb_sala_resumen($codigo, $anfitrion, $resumen): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    if (!cb_sala_es_anfitrion($sala, $anfitrion)) {
        return ['ok' => false, 'error' => 'anfitrion_invalido', 'http' => 403];
    }
    $json = cb_sala_resumen_limpio($resumen);
    if ($json === null) {
        return ['ok' => false, 'error' => 'resumen_invalido', 'http' => 422];
    }
    $pdo->prepare('UPDATE cc_salas SET resumen=?, updated_at=? WHERE id=?')->execute([$json, cb_sala_ahora(), (int) $sala['id']]);
    return ['ok' => true];
}

function cb_sala_acciones($codigo, $anfitrion, $desde): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    if (!cb_sala_es_anfitrion($sala, $anfitrion)) {
        return ['ok' => false, 'error' => 'anfitrion_invalido', 'http' => 403];
    }
    $desde = max(0, (int) $desde);
    $stmt = $pdo->prepare('SELECT a.seq, a.tipo, a.ayudante_id, y.nombre FROM cc_sala_acciones a
        JOIN cc_sala_ayudantes y ON y.id = a.ayudante_id
        WHERE a.sala_id=? AND a.seq>? ORDER BY a.seq ASC LIMIT ' . CB_SALA_ACCIONES_POR_LECTURA);
    $stmt->execute([(int) $sala['id'], $desde]);
    $acciones = [];
    $ultimo = $desde;
    foreach ($stmt->fetchAll() as $fila) {
        $ultimo = (int) $fila['seq'];
        $acciones[] = ['seq' => $ultimo, 'tipo' => (string) $fila['tipo'], 'nombre' => (string) $fila['nombre'], 'id' => (int) $fila['ayudante_id']];
    }
    return [
        'ok' => true,
        'acciones' => $acciones,
        'ayudantes' => cb_sala_contar_ayudantes($pdo, (int) $sala['id']),
        'seq' => $ultimo,
        'estado' => (string) $sala['estado'],
    ];
}

/**
 * IPv4 privadas de este servidor. Cuando el juego corre en la misma máquina que
 * WAMP (localhost), el QR tiene que apuntar a la IP de la LAN o los celulares no entran.
 */
function cb_sala_ips_lan(): array
{
    $host = @gethostname();
    $lista = is_string($host) && $host !== '' ? @gethostbynamel($host) : false;
    $ips = [];
    foreach (is_array($lista) ? $lista : [] as $ip) {
        if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', (string) $ip) === 1) {
            $ips[] = (string) $ip;
        }
    }
    return array_values(array_unique($ips));
}

/**
 * Últimas fotos del kiosco de una fiesta, para colgarlas dentro del mundo del
 * juego. Misma llave que la galería: el PIN de la fiesta. Devuelve rutas
 * relativas a la carpeta pública de CumpleClick (`ver.php?t=...`), nunca tokens sueltos.
 */
function cb_sala_fotos($codigo, $anfitrion, $fiesta, $pin, int $max = 6): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    if (!cb_sala_es_anfitrion($sala, $anfitrion)) {
        return ['ok' => false, 'error' => 'anfitrion_invalido', 'http' => 403];
    }
    if (!is_string($fiesta) || !cb_valid_public_slug($fiesta)) {
        return ['ok' => false, 'error' => 'fiesta_invalida', 'http' => 422];
    }
    $party = cb_load_party_raw($fiesta);
    if ($party === null) {
        return ['ok' => false, 'error' => 'fiesta_no_existe', 'http' => 404];
    }
    if (empty($party['galeriaPinHash']) && empty($party['galeriaPin'])) {
        return ['ok' => false, 'error' => 'sin_pin', 'http' => 409];
    }
    if (!is_string($pin) || !cb_verify_party_pin($party, $pin)) {
        return ['ok' => false, 'error' => 'pin_invalido', 'http' => 403];
    }
    $todas = cb_list_party_photos($fiesta);
    $fotos = [];
    foreach (array_slice($todas, 0, max(1, min(12, $max))) as $f) {
        $token = (string) ($f['access_token'] ?? '');
        if ($token === '') {
            continue;
        }
        $fotos[] = [
            'ver' => 'ver.php?t=' . rawurlencode($token) . '&download=inline',
            'nombre' => (string) ($f['original_name'] ?? ''),
            'w' => (int) ($f['width'] ?? 0),
            'h' => (int) ($f['height'] ?? 0),
            'creada' => (string) ($f['created_at'] ?? ''),
        ];
    }
    return ['ok' => true, 'fotos' => $fotos, 'total' => count($todas)];
}

function cb_sala_cerrar($codigo, $anfitrion): array
{
    $pdo = cb_sala_pdo();
    $sala = cb_sala_buscar($pdo, $codigo);
    if ($sala === null) {
        return ['ok' => false, 'error' => 'sala_no_existe', 'http' => 404];
    }
    if (!cb_sala_es_anfitrion($sala, $anfitrion)) {
        return ['ok' => false, 'error' => 'anfitrion_invalido', 'http' => 403];
    }
    $pdo->prepare("UPDATE cc_salas SET estado='cerrada', updated_at=? WHERE id=?")->execute([cb_sala_ahora(), (int) $sala['id']]);
    return ['ok' => true];
}
