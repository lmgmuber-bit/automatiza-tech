<?php
/**
 * Usuarios del backoffice (2026-09-13): la tabla maestra de quienes entran al admin con correo y
 * contraseña, y lo que cada uno puede ver.
 *
 * Dos roles: `super` (todo) y `operador` (solo las fiestas que tiene asignadas y los módulos
 * marcados). La clave maestra de siempre no vive acá: sigue en la configuración del servidor y
 * entra por `admin/maestro.php` como superadministrador sin fila en la tabla (id 0).
 *
 * Todo lo que decide permisos está en este archivo para que las páginas del admin no repitan
 * la lógica: `admin/_acceso.php` es el único que la aplica.
 */
require_once __DIR__ . '/lib.php';

/** Lo que se le puede abrir a un operador. El orden es el de las casillas en Usuarios. */
const CB_ADMIN_MODULOS = [
    'fotos'     => ['nombre' => 'Fotos del kiosco', 'detalle' => 'Ver la galería sin PIN, borrar e imprimir fotos'],
    'juegos'    => ['nombre' => 'Juegos 3D', 'detalle' => 'Prender o apagar los juegos y ver la tabla de posiciones'],
    'carteles'  => ['nombre' => 'Carteles QR', 'detalle' => 'Ver e imprimir los carteles de la fiesta'],
    'invitados' => ['nombre' => 'Agregar invitados', 'detalle' => 'Sumar a la lista un niño que llega sin estar anotado'],
    'mensajes'  => ['nombre' => 'Mensajes', 'detalle' => 'La pestaña Mensajes de su fiesta'],
    'album'     => ['nombre' => 'Álbum Recuerdo', 'detalle' => 'Recibir y curar los aportes de los papás'],
    'perfil'    => ['nombre' => 'Perfil del protagonista', 'detalle' => 'Editar el perfil del festejado'],
    'marketing' => ['nombre' => 'Contenido', 'detalle' => 'Organizar publicaciones y métricas'],
];
/** Lo que se marca por defecto al crear un operador: lo que se usa durante la fiesta. */
const CB_ADMIN_MODULOS_RECOMENDADOS = ['fotos', 'juegos', 'carteles', 'invitados'];
const CB_ADMIN_ROLES = ['operador' => 'Operador de fiesta', 'super' => 'Superadministrador'];
/** Igual que la clave maestra: abre fotos de niños y datos de clientes. */
const CB_ADMIN_CLAVE_MINIMA = 10;

/** ¿Existe la tabla? Sin base de datos, o antes de la migración 023, solo entra la clave maestra. */
function cb_admin_usuarios_listo(): bool
{
    static $listo = null;
    if ($listo !== null) {
        return $listo;
    }
    if (cb_storage_mode() !== 'db') {
        return $listo = false;
    }
    try {
        $pdo = cb_pdo();
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "SHOW TABLES LIKE 'cc_admin_users'"
            : "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'cc_admin_users'";
        return $listo = (bool) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return $listo = false;
    }
}

function cb_admin_normalizar_correo(string $correo): string
{
    return mb_strtolower(trim($correo));
}

function cb_admin_correo_valido(string $correo): bool
{
    return $correo !== '' && mb_strlen($correo) <= 190 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Contraseña temporal: 12 letras y números en tres grupos, sin 0/O, 1/l/I. Se lee por teléfono
 * sin equivocarse y se cambia en la primera entrada, así que no necesita más.
 */
function cb_admin_contrasena_temporal(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $grupos = [];
    for ($g = 0; $g < 3; $g++) {
        $grupo = '';
        for ($i = 0; $i < 4; $i++) {
            $grupo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $grupos[] = $grupo;
    }
    return implode('-', $grupos);
}

/** Deja solo módulos conocidos, sin repetidos y en el orden canónico. */
function cb_admin_modulos_limpiar($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $pedidos = array_map('strval', $raw);
    $out = [];
    foreach (array_keys(CB_ADMIN_MODULOS) as $clave) {
        if (in_array($clave, $pedidos, true)) {
            $out[] = $clave;
        }
    }
    return $out;
}

function cb_admin_rol_limpiar($raw): string
{
    return (string) $raw === 'super' ? 'super' : 'operador';
}

/** Fila de la base → arreglo con tipos y con las fiestas (slugs) ya cargadas. */
function cb_admin_usuario_hidratar(array $row): array
{
    $modulos = json_decode((string) ($row['modulos'] ?? '[]'), true);
    return [
        'id' => (int) $row['id'],
        'email' => (string) $row['email'],
        'nombre' => (string) $row['nombre'],
        'rol' => cb_admin_rol_limpiar($row['rol'] ?? 'operador'),
        'activo' => (bool) $row['activo'],
        'debe_cambiar' => (bool) $row['debe_cambiar'],
        'modulos' => cb_admin_modulos_limpiar($modulos),
        'ultimo_acceso' => (string) ($row['ultimo_acceso_at'] ?? ''),
        'creado' => (string) ($row['created_at'] ?? ''),
        'fiestas' => cb_admin_fiestas_de((int) $row['id']),
    ];
}

/** Todos los usuarios: los activos primero, después por nombre. */
function cb_admin_usuarios(): array
{
    if (!cb_admin_usuarios_listo()) {
        return [];
    }
    $rows = cb_pdo()->query('SELECT * FROM cc_admin_users ORDER BY activo DESC, nombre ASC, id ASC')->fetchAll();
    return array_map('cb_admin_usuario_hidratar', $rows);
}

function cb_admin_usuario_por_id(int $id): ?array
{
    if ($id <= 0 || !cb_admin_usuarios_listo()) {
        return null;
    }
    $stmt = cb_pdo()->prepare('SELECT * FROM cc_admin_users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? cb_admin_usuario_hidratar($row) : null;
}

function cb_admin_usuario_por_correo(string $correo): ?array
{
    $correo = cb_admin_normalizar_correo($correo);
    if ($correo === '' || !cb_admin_usuarios_listo()) {
        return null;
    }
    $stmt = cb_pdo()->prepare('SELECT * FROM cc_admin_users WHERE email = ?');
    $stmt->execute([$correo]);
    $row = $stmt->fetch();
    return $row ? cb_admin_usuario_hidratar($row) : null;
}

/** Slugs de las fiestas de un usuario. */
function cb_admin_fiestas_de(int $id): array
{
    if ($id <= 0 || !cb_admin_usuarios_listo()) {
        return [];
    }
    $stmt = cb_pdo()->prepare('SELECT p.public_slug FROM cc_admin_user_parties up JOIN cc_parties p ON p.id = up.party_id WHERE up.user_id = ? ORDER BY p.created_at DESC, p.id DESC');
    $stmt->execute([$id]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Reemplaza las fiestas de un usuario por la lista dada (slugs que no existen se ignoran). */
function cb_admin_asignar_fiestas(int $id, array $slugs): void
{
    $pdo = cb_pdo();
    $pdo->prepare('DELETE FROM cc_admin_user_parties WHERE user_id = ?')->execute([$id]);
    $insertar = $pdo->prepare('INSERT INTO cc_admin_user_parties (user_id, party_id) VALUES (?, ?)');
    $vistos = [];
    foreach ($slugs as $slug) {
        $slug = (string) $slug;
        if (!cb_valid_public_slug($slug) || isset($vistos[$slug])) {
            continue;
        }
        $partyId = cb_party_db_id($slug);
        if ($partyId === null) {
            continue;
        }
        $vistos[$slug] = true;
        $insertar->execute([$id, $partyId]);
    }
}

/** Valida nombre, correo, rol y módulos. Devuelve ['ok', 'errors', 'datos']. */
function cb_admin_usuario_validar(array $d, ?int $idActual = null): array
{
    $errores = [];
    $nombre = trim((string) ($d['nombre'] ?? ''));
    $correo = cb_admin_normalizar_correo((string) ($d['correo'] ?? ''));
    if ($nombre === '' || mb_strlen($nombre) > 80) {
        $errores[] = 'Escribe el nombre de la persona (hasta 80 letras).';
    }
    if (!cb_admin_correo_valido($correo)) {
        $errores[] = 'El correo no parece válido.';
    } else {
        $otro = cb_admin_usuario_por_correo($correo);
        if ($otro !== null && $otro['id'] !== $idActual) {
            $errores[] = 'Ya hay un usuario con ese correo.';
        }
    }
    $datos = [
        'nombre' => $nombre,
        'correo' => $correo,
        'rol' => cb_admin_rol_limpiar($d['rol'] ?? 'operador'),
        'modulos' => cb_admin_modulos_limpiar($d['modulos'] ?? []),
        'fiestas' => is_array($d['fiestas'] ?? null) ? array_map('strval', $d['fiestas']) : [],
    ];
    return ['ok' => !$errores, 'errors' => $errores, 'datos' => $datos];
}

/**
 * Crea un usuario con contraseña temporal. Devuelve la temporal EN CLARO una sola vez, para el
 * correo de bienvenida (o para la pantalla, si el correo no sale). En la base queda el hash.
 */
function cb_admin_usuario_crear(array $d, int $creadoPor = 0): array
{
    if (!cb_admin_usuarios_listo()) {
        return ['ok' => false, 'errors' => ['Los usuarios necesitan la base de datos (migración 023).']];
    }
    $v = cb_admin_usuario_validar($d);
    if (!$v['ok']) {
        return ['ok' => false, 'errors' => $v['errors']];
    }
    $datos = $v['datos'];
    $temporal = cb_admin_contrasena_temporal();
    $ahora = gmdate('Y-m-d H:i:s');
    $pdo = cb_pdo();
    $pdo->prepare('INSERT INTO cc_admin_users (email, nombre, password_hash, rol, activo, debe_cambiar, modulos, creado_por, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 1, ?, ?, ?, ?)')
        ->execute([$datos['correo'], $datos['nombre'], password_hash($temporal, PASSWORD_DEFAULT), $datos['rol'],
            json_encode($datos['modulos']), $creadoPor > 0 ? $creadoPor : null, $ahora, $ahora]);
    $id = (int) $pdo->lastInsertId();
    cb_admin_asignar_fiestas($id, $datos['fiestas']);
    return ['ok' => true, 'errors' => [], 'id' => $id, 'temporal' => $temporal];
}

/** Cambia nombre, correo, rol, módulos y fiestas. La contraseña no se toca por acá. */
function cb_admin_usuario_actualizar(int $id, array $d): array
{
    $usuario = cb_admin_usuario_por_id($id);
    if ($usuario === null) {
        return ['ok' => false, 'errors' => ['Ese usuario no existe.']];
    }
    $v = cb_admin_usuario_validar($d, $id);
    if (!$v['ok']) {
        return ['ok' => false, 'errors' => $v['errors']];
    }
    $datos = $v['datos'];
    cb_pdo()->prepare('UPDATE cc_admin_users SET email = ?, nombre = ?, rol = ?, modulos = ?, updated_at = ? WHERE id = ?')
        ->execute([$datos['correo'], $datos['nombre'], $datos['rol'], json_encode($datos['modulos']), gmdate('Y-m-d H:i:s'), $id]);
    cb_admin_asignar_fiestas($id, $datos['fiestas']);
    return ['ok' => true, 'errors' => []];
}

function cb_admin_usuario_activar(int $id, bool $activo): bool
{
    if (cb_admin_usuario_por_id($id) === null) {
        return false;
    }
    cb_pdo()->prepare('UPDATE cc_admin_users SET activo = ?, updated_at = ? WHERE id = ?')
        ->execute([$activo ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]);
    return true;
}

/** Contraseña temporal nueva; la anterior deja de servir y hay que cambiarla al entrar. */
function cb_admin_usuario_reiniciar_contrasena(int $id): array
{
    if (cb_admin_usuario_por_id($id) === null) {
        return ['ok' => false, 'errors' => ['Ese usuario no existe.']];
    }
    $temporal = cb_admin_contrasena_temporal();
    cb_pdo()->prepare('UPDATE cc_admin_users SET password_hash = ?, debe_cambiar = 1, updated_at = ? WHERE id = ?')
        ->execute([password_hash($temporal, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'errors' => [], 'temporal' => $temporal];
}

/** El propio usuario cambia su contraseña: pide la actual (o la temporal). */
function cb_admin_usuario_cambiar_contrasena(int $id, string $actual, string $nueva, string $repetida): array
{
    $stmt = cb_pdo()->prepare('SELECT password_hash FROM cc_admin_users WHERE id = ?');
    $stmt->execute([$id]);
    $hash = (string) $stmt->fetchColumn();
    $errores = [];
    if ($hash === '' || !password_verify($actual, $hash)) {
        $errores[] = 'La contraseña actual no es correcta.';
    }
    if (mb_strlen($nueva) < CB_ADMIN_CLAVE_MINIMA) {
        $errores[] = 'La contraseña nueva tiene que tener al menos ' . CB_ADMIN_CLAVE_MINIMA . ' caracteres.';
    }
    if ($nueva !== $repetida) {
        $errores[] = 'Las dos contraseñas nuevas no coinciden.';
    }
    if (!$errores && password_verify($nueva, $hash)) {
        $errores[] = 'La contraseña nueva tiene que ser distinta de la actual.';
    }
    if ($errores) {
        return ['ok' => false, 'errors' => $errores];
    }
    cb_pdo()->prepare('UPDATE cc_admin_users SET password_hash = ?, debe_cambiar = 0, updated_at = ? WHERE id = ?')
        ->execute([password_hash($nueva, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'errors' => []];
}

function cb_admin_usuario_renombrar(int $id, string $nombre): array
{
    $nombre = trim($nombre);
    if ($nombre === '' || mb_strlen($nombre) > 80) {
        return ['ok' => false, 'errors' => ['Escribe tu nombre (hasta 80 letras).']];
    }
    cb_pdo()->prepare('UPDATE cc_admin_users SET nombre = ?, updated_at = ? WHERE id = ?')
        ->execute([$nombre, gmdate('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'errors' => []];
}

/**
 * Entrar con correo y contraseña. `error` es `credenciales` (correo o clave mal: no se dice
 * cuál, para no confirmar qué correos existen) o `deshabilitado` (la clave era correcta, pero
 * el acceso está apagado: eso sí se avisa, para que hable con quien se lo dio).
 */
function cb_admin_login_verificar(string $correo, string $clave): array
{
    static $hashSenuelo = null;
    if ($hashSenuelo === null) {
        $hashSenuelo = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    }
    $usuario = cb_admin_usuario_por_correo($correo);
    if ($usuario === null) {
        // Se verifica igual contra un hash de mentira: un correo inexistente tarda lo mismo.
        password_verify($clave, $hashSenuelo);
        return ['ok' => false, 'error' => 'credenciales'];
    }
    $stmt = cb_pdo()->prepare('SELECT password_hash FROM cc_admin_users WHERE id = ?');
    $stmt->execute([$usuario['id']]);
    if (!password_verify($clave, (string) $stmt->fetchColumn())) {
        return ['ok' => false, 'error' => 'credenciales'];
    }
    if (!$usuario['activo']) {
        return ['ok' => false, 'error' => 'deshabilitado'];
    }
    cb_pdo()->prepare('UPDATE cc_admin_users SET ultimo_acceso_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $usuario['id']]);
    return ['ok' => true, 'usuario' => $usuario];
}

/** El superadministrador de la clave maestra, sin fila en la tabla. */
function cb_admin_usuario_maestro(): array
{
    return ['id' => 0, 'email' => '', 'nombre' => 'Clave maestra', 'rol' => 'super', 'activo' => true,
        'debe_cambiar' => false, 'modulos' => array_keys(CB_ADMIN_MODULOS), 'ultimo_acceso' => '', 'creado' => '', 'fiestas' => []];
}

/** ¿Este usuario puede ver esa fiesta? */
function cb_admin_usuario_ve_fiesta(array $usuario, string $slug): bool
{
    return $usuario['rol'] === 'super' || in_array($slug, $usuario['fiestas'], true);
}

/** ¿Este usuario puede usar ese módulo, en esa fiesta si se indica? */
function cb_admin_usuario_puede(array $usuario, string $modulo, ?string $slug = null): bool
{
    if ($usuario['rol'] === 'super') {
        return true;
    }
    if ($slug !== null && !cb_admin_usuario_ve_fiesta($usuario, $slug)) {
        return false;
    }
    return in_array($modulo, $usuario['modulos'], true);
}

/**
 * Para las páginas públicas que abren la sesión de admin por su cuenta (galería, visor): con el
 * id guardado en la sesión, ¿puede ver esa fiesta? El id 0 es la clave maestra. Un usuario
 * deshabilitado o borrado no puede nada, aunque su cookie siga viva.
 */
function cb_admin_usuario_puede_fiesta(int $usuarioId, string $slug): bool
{
    if ($usuarioId === 0) {
        return true;
    }
    static $cache = [];
    if (!array_key_exists($usuarioId, $cache)) {
        $cache[$usuarioId] = cb_admin_usuario_por_id($usuarioId);
    }
    $usuario = $cache[$usuarioId];
    return $usuario !== null && $usuario['activo'] && cb_admin_usuario_ve_fiesta($usuario, $slug);
}

/**
 * El correo de bienvenida (o el de contraseña nueva). Devuelve subject, text y html para
 * cc_mail_send. Lleva la contraseña temporal en claro: es la única vez que viaja.
 */
function cb_admin_correo_bienvenida(array $usuario, string $temporal, string $urlLogin, array $fiestasNombres, string $quien, bool $reinicio = false): array
{
    $nombre = $usuario['nombre'] !== '' ? $usuario['nombre'] : 'Hola';
    $fiestas = $fiestasNombres ? implode(', ', $fiestasNombres) : '';
    $esSuper = $usuario['rol'] === 'super';
    if ($reinicio) {
        $intro = $quien . ' te dio una contraseña temporal nueva para el panel de CumpleClick.';
    } elseif ($esSuper) {
        $intro = $quien . ' te dio acceso al panel de CumpleClick como superadministrador.';
    } else {
        $intro = $quien . ' te dio acceso al panel de CumpleClick para ayudar con '
            . ($fiestas !== '' ? 'la fiesta de ' . $fiestas : 'una fiesta') . '.';
    }
    $lineas = [
        'Hola, ' . $nombre . '.',
        '',
        $intro,
        '',
        'Entra acá: ' . $urlLogin,
        'Tu correo: ' . $usuario['email'],
        'Contraseña temporal: ' . $temporal,
        '',
        'La primera vez te vamos a pedir que la cambies por una tuya, de al menos ' . CB_ADMIN_CLAVE_MINIMA . ' caracteres.',
        $esSuper ? '' : 'Vas a ver solo tu fiesta: sus fotos, sus juegos y sus carteles, según lo que te hayan habilitado.',
        '',
        'Si no esperabas este correo, ignóralo.',
    ];
    $texto = implode("\n", $lineas) . "\n";
    $html = '<p style="margin:0 0 16px">Hola, <strong>' . cc_mail_h($nombre) . '</strong>.</p>'
        . '<p style="margin:0 0 18px">' . cc_mail_h($intro) . '</p>'
        . '<p style="margin:0 0 18px"><a href="' . cc_mail_h($urlLogin) . '" '
        . 'style="display:inline-block;background:#7C3AED;color:#ffffff;text-decoration:none;'
        . 'padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px">Entrar al panel</a></p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;font-size:15px">'
        . '<tr><td style="padding:4px 14px 4px 0;color:#6B6280">Tu correo</td><td style="padding:4px 0"><strong>' . cc_mail_h($usuario['email']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 14px 4px 0;color:#6B6280">Contraseña temporal</td><td style="padding:4px 0"><code style="font-size:17px;letter-spacing:1px;background:#F3EFF7;padding:4px 10px;border-radius:8px">' . cc_mail_h($temporal) . '</code></td></tr>'
        . '</table>'
        . '<p style="margin:0 0 12px;font-size:14px;color:#6B6280">La primera vez te vamos a pedir que la cambies por una tuya, de al menos ' . CB_ADMIN_CLAVE_MINIMA . ' caracteres.</p>'
        . ($esSuper ? '' : '<p style="margin:0 0 12px;font-size:14px;color:#6B6280">Vas a ver solo tu fiesta: sus fotos, sus juegos y sus carteles, según lo que te hayan habilitado.</p>')
        . '<p style="margin:0;font-size:13px;color:#6B6280">Si no esperabas este correo, ignóralo.</p>';
    return [
        'subject' => $reinicio ? 'CumpleClick: tu contraseña temporal nueva' : 'CumpleClick: tu acceso al panel de la fiesta',
        'text' => $texto,
        'html' => cc_mail_shell($reinicio ? 'Contraseña temporal nueva' : 'Bienvenida al panel', $html),
    ];
}
